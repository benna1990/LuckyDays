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

$bonId = intval($_GET['id'] ?? $_GET['bon_id'] ?? 0);

if (!$bonId) {
    echo json_encode(['success' => false, 'error' => 'Geen bon ID']);
    exit();
}

$bon = getBonById($conn, $bonId);
if (!$bon) {
    echo json_encode(['success' => false, 'error' => 'Bon niet gevonden']);
    exit();
}

$rijen = getRijenByBonId($conn, $bonId);
$winningNumbers = getWinningNumbersFromDatabase($bon['date'], $conn);

// Safety net: always recalc rijen when winnende nummers bekend zijn,
// zodat eerder opgeslagen bonnen live up-to-date blijven.
if ($winningNumbers && is_array($winningNumbers) && count($winningNumbers) > 0 && $rijen) {
    foreach ($rijen as &$rij) {
        $calc = recalculateRijWinnings($conn, $rij['id'], $winningNumbers);
        if ($calc) {
            $rij['matches'] = $calc['matches'];
            $rij['multiplier'] = $calc['multiplier'];
            $rij['winnings'] = $calc['winnings'];
            $rij['game_type'] = $calc['game_type'];
        }
    }
    unset($rij);
}

// Check if this bon is part of a trekking group
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
        $trekkingInfo = [
            'aantal_trekkingen' => (int)$groepData['aantal_trekkingen'],
            'start_datum' => $groepData['start_datum'],
            'eind_datum' => $groepData['eind_datum']
        ];
    }
}

$totalBet = 0;
$totalWinnings = 0;

$rijenData = [];
if ($rijen && is_array($rijen)) {
    foreach ($rijen as $rij) {
        $numbers = array_map('intval', explode(',', $rij['numbers']));
        $bet = floatval($rij['bet']);
        $winnings = floatval($rij['winnings']);
        $matches = intval($rij['matches']);
        $multiplier = floatval($rij['multiplier']);

        $totalBet += $bet;
        $totalWinnings += $winnings;

        // HET HUIS LOGICA: inzet - winnings (niet gebruikt in frontend, maar consistent houden)
        $rijenData[] = [
            'id' => $rij['id'],
            'numbers' => $numbers,
            'bet' => $bet,
            'winnings' => $winnings,
            'matches' => $matches,
            'multiplier' => $multiplier
        ];
    }
}

echo json_encode([
    'success' => true,
    'bon' => [
        'id' => $bon['id'],
        'player_name' => $bon['player_name'],
        'player_color' => $bon['player_color'],
        'date' => $bon['date'],
        'name' => $bon['name'],
        'bonnummer' => $bon['bonnummer'] ?? null,
        'winkel_naam' => $bon['winkel_naam'] ?? 'Geen winkel'
    ],
    'rijen' => $rijenData,
    'winning_numbers' => $winningNumbers ? array_map('intval', $winningNumbers) : [],
    'totals' => [
        'bet' => $totalBet,
        'winnings' => $totalWinnings
        // Saldo wordt nu in JavaScript berekend met correcte huis-logica
    ],
    'trekking_info' => $trekkingInfo
]);
