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

$bonId = intval($_GET['id'] ?? 0);

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
        'name' => $bon['name']
    ],
    'rijen' => $rijenData,
    'winning_numbers' => $winningNumbers ? array_map('intval', $winningNumbers) : [],
    'totals' => [
        'bet' => $totalBet,
        'winnings' => $totalWinnings
        // Saldo wordt nu in JavaScript berekend met correcte huis-logica
    ]
]);
