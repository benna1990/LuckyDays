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

// Parameters: direction (before/after), referenceDate
$direction = $_GET['direction'] ?? 'after'; // 'before' or 'after'
$referenceDate = $_GET['reference_date'] ?? date('Y-m-d');
$limit = intval($_GET['limit'] ?? 14); // Default: 2 weken (14 dagen)

// Generate date range
$dates = [];
$start = strtotime($referenceDate);

if ($direction === 'before') {
    // Load dates BEFORE reference date
    for ($i = 1; $i <= $limit; $i++) {
        $date = date('Y-m-d', strtotime("-$i days", $start));
        $dates[] = $date;
    }
    // Reverse zodat oudste eerst komt
    $dates = array_reverse($dates);
} else {
    // Load dates AFTER reference date
    for ($i = 1; $i <= $limit; $i++) {
        $date = date('Y-m-d', strtotime("+$i days", $start));
        $dates[] = $date;
    }
}

// Voor elke datum: check of er bonnen of winning numbers zijn
$datesData = [];
foreach ($dates as $date) {
    $timestamp = strtotime($date);
    $dayOfWeek = date('N', $timestamp); // 1 = Monday, 7 = Sunday
    $weekNum = date('W', $timestamp);
    $year = date('o', $timestamp);
    $weekKey = $year . '-W' . str_pad($weekNum, 2, '0', STR_PAD_LEFT);
    
    // Check for winning numbers
    $winningNumbers = getWinningNumbersFromDatabase($date, $conn);
    $hasWinning = !empty($winningNumbers);
    
    // Check for bonnen
    $bonnen = getBonnenByDate($conn, $date, null); // null = alle winkels
    $hasBonnen = !empty($bonnen);
    
    $datesData[] = [
        'date' => $date,
        'day_name' => ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'][$dayOfWeek - 1],
        'day_number' => date('d', $timestamp),
        'month_name' => ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'][date('n', $timestamp) - 1],
        'week_key' => $weekKey,
        'week_num' => $weekNum,
        'year' => $year,
        'is_first_of_week' => ($dayOfWeek == 1), // Monday
        'has_winning' => $hasWinning,
        'has_bonnen' => $hasBonnen
    ];
}

echo json_encode([
    'success' => true,
    'direction' => $direction,
    'reference_date' => $referenceDate,
    'dates' => $datesData,
    'count' => count($datesData)
]);
?>


