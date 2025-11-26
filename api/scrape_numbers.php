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

$scrapeResult = scrapeLuckyDayNumbers($date);

if ($scrapeResult['success']) {
    saveWinningNumbersToDatabase($date, $scrapeResult['numbers'], $conn);
    recalculateAllRijenForDate($conn, $date, $scrapeResult['numbers']);
    echo json_encode(['success' => true, 'numbers' => $scrapeResult['numbers']]);
} else {
    $today = date('Y-m-d');
    $isToday = ($date === $today);
    $currentHour = (int)date('H');
    
    if ($isToday && $currentHour < 19) {
        echo json_encode([
            'success' => false, 
            'error' => 'Trekking is om 19:00 uur',
            'retry' => false
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => $scrapeResult['error'] ?? 'Uitslag niet gevonden',
            'retry' => true
        ]);
    }
}
?>
