<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit();
}

$date = $_GET['date'] ?? date('Y-m-d');
$selectedWinkel = $_SESSION['selected_winkel'] ?? null;

// Get bonnen for this date
$bonnen = getBonnenByDate($conn, $date, $selectedWinkel);

// Get winning numbers
$winningNumbers = getWinningNumbersFromDatabase($date, $conn);

// Safety net: recalculates all rijen for this date when winnende nummers bestaan,
// zodat alle bonnen/overzichten live kloppen.
if ($winningNumbers && is_array($winningNumbers) && count($winningNumbers) > 0) {
    recalculateAllRijenForDate($conn, $date, $winningNumbers);
}

// Format bonnen data
$bonnenData = [];
$totalBet = 0;
$totalWinnings = 0;

if ($bonnen && is_array($bonnen)) {
    foreach ($bonnen as $bon) {
        $rijen = getRijenByBonId($conn, $bon['id']);
        $bonBet = 0;
        $bonWinnings = 0;
        
        if ($rijen && is_array($rijen)) {
            foreach ($rijen as $rij) {
                $bonBet += floatval($rij['bet']);
                $bonWinnings += floatval($rij['winnings']);
            }
        }
        
        $bonSaldo = $bonBet - $bonWinnings;

        $totalBet += $bonBet;
        $totalWinnings += $bonWinnings;
        // No saldo accumulation; use commission split later

        // Check trekking info
        $trekkingInfo = null;
        if (!empty($bon['trekking_groep_id'])) {
            $groepQuery = "
                SELECT
                    COUNT(*) as aantal_trekkingen,
                    MIN(date) as start_datum,
                    MAX(date) as eind_datum
                FROM bons
                WHERE trekking_groep_id = $1
            ";
            $groepResult = db_query($groepQuery, [$bon['trekking_groep_id']]);
            $groepData = db_fetch_assoc($groepResult);

            if ($groepData && $groepData['aantal_trekkingen'] > 1) {
                // Calculate current position in series
                $positionQuery = "
                    SELECT COUNT(*) + 1 as positie
                    FROM bons
                    WHERE trekking_groep_id = $1 AND date < $2
                ";
                $positionResult = db_query($positionQuery, [$bon['trekking_groep_id'], $bon['date']]);
                $positionData = db_fetch_assoc($positionResult);

                $trekkingInfo = [
                    'aantal_trekkingen' => (int)$groepData['aantal_trekkingen'],
                    'huidige_trekking' => (int)$positionData['positie'],
                    'start_datum' => $groepData['start_datum'],
                    'eind_datum' => $groepData['eind_datum']
                ];
            }
        }

        $bonnenData[] = [
            'id' => $bon['id'],
            'name' => $bon['name'],
            'player_id' => $bon['player_id'] ?? null,
            'player_name' => $bon['player_name'],
            'player_color' => $bon['player_color'],
            'winkel_id' => $bon['winkel_id'],
            'winkel_name' => $bon['winkel_name'] ?? 'Onbekend',
            'bet' => $bonBet,
            'winnings' => $bonWinnings,
            'rijen_count' => count($rijen ?? []),
            'trekking_info' => $trekkingInfo
        ];
    }
}

// Check scraper status
$today = date('Y-m-d');
$currentHour = (int)date('H');
$isToday = ($date === $today);
$isFuture = (strtotime($date) > strtotime($today));
$hasWinningNumbers = !empty($winningNumbers);

$buttonState = 'unavailable';
$buttonDate = $today;
$buttonLabel = '';

// Vandaag: alleen scrapen na 19:00
if ($isToday && $currentHour >= 19 && !$hasWinningNumbers) {
    $buttonState = 'today-available';
    $buttonDate = $today;
    $buttonLabel = 'Vandaag';
}
// Verleden: altijd scrapen mogelijk (geen 19:00 restrictie)
elseif (!$isToday && !$isFuture && !$hasWinningNumbers) {
    $buttonState = 'past-available';
    $buttonDate = $date;
    $buttonLabel = date('d-m-Y', strtotime($date));
}
// Toekomst: niet scrapen
// else: buttonState blijft 'unavailable'

echo json_encode([
    'success' => true,
    'date' => $date,
    'bonnen' => $bonnenData,
    'winning_numbers' => $winningNumbers ? array_map('intval', $winningNumbers) : [],
    'has_winning_numbers' => $hasWinningNumbers,
    'totals' => [
        'bet' => $totalBet,
        'winnings' => $totalWinnings,
        'commission' => $totalBet * 0.30,
        'house_pot' => $totalBet * 0.70,
        'net_house' => ($totalBet * 0.70) - $totalWinnings
    ],
    'scraper_button' => [
        'state' => $buttonState,
        'date' => $buttonDate,
        'label' => $buttonLabel
    ]
]);
?>
