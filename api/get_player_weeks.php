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

$playerId = $_GET['player_id'] ?? null;

if (!$playerId || !is_numeric($playerId)) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige player_id']);
    exit();
}

// Get current week/year or provided week/year
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('o');
$currentWeek = isset($_GET['week']) ? (int)$_GET['week'] : (int)date('W');

// Generate week range
$weeks = generateWeekRange($currentYear, $currentWeek);

// Get all dates that have bonnen for this player
$query = "SELECT DISTINCT DATE(date) as date FROM bons WHERE player_id = $1 ORDER BY date DESC";
$result = db_query($query, [$playerId]);

$datesWithBonnen = [];
while ($row = db_fetch_assoc($result)) {
    $datesWithBonnen[] = $row['date'];
}

// Mark weeks that have bonnen
foreach ($weeks as &$week) {
    $week['has_bonnen'] = false;

    foreach ($datesWithBonnen as $date) {
        if ($date >= $week['start'] && $date <= $week['end']) {
            $week['has_bonnen'] = true;
            break;
        }
    }
}

echo json_encode([
    'success' => true,
    'weeks' => $weeks,
    'current' => [
        'year' => $currentYear,
        'week' => $currentWeek
    ]
]);
