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

// Get current week/year or provided week/year
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('o');
$currentWeek = isset($_GET['week']) ? (int)$_GET['week'] : (int)date('W');

// Get winkel filter from session
$selectedWinkel = $_SESSION['selected_winkel'] ?? null;

// Generate week range
$weeks = generateWeekRange($currentYear, $currentWeek);

// Get all dates that have bonnen
$query = "SELECT DISTINCT DATE(date) as date FROM bons WHERE 1=1";
$params = [];

if ($selectedWinkel !== null) {
    $query .= " AND winkel_id = $1";
    $params[] = $selectedWinkel;
}

$query .= " ORDER BY date DESC";

$result = db_query($query, $params);
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
