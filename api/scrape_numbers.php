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

// Ensure we use the correct date format and timezone
$tz = new DateTimeZone('Europe/Amsterdam');
$now = new DateTimeImmutable('now', $tz);
$currentHour = (int)$now->format('H');
$today = $now->format('Y-m-d');

try {
    if (isset($_GET['date']) && !empty($_GET['date'])) {
        $requestedDate = new DateTimeImmutable($_GET['date'], $tz);
    } else {
        $requestedDate = $now;
    }
} catch (Exception $e) {
    $requestedDate = $now;
}
$date = $requestedDate->format('Y-m-d');

// BELANGRIJKE REGEL: voorkom scrapen voor 19:00 op de huidige dag
if ($date === $today && $currentHour < 19) {
    echo json_encode([
        'success' => false,
        'error' => 'Trekking is om 19:00 uur. Nummers kunnen pas na 19:00 worden opgehaald.',
        'retry' => false,
        'time_remaining' => (19 - $currentHour) . ' uur'
    ]);
    exit();
}

// Log the date being scraped for debugging
error_log("Scraping winning numbers for date: " . $date . " at " . $now->format('H:i:s'));

$scrapeResult = scrapeLuckyDayNumbers($date);

if ($scrapeResult['success']) {
    // Sla ALTIJD op met de juiste datum - niet vandaag maar de gevraagde datum
    saveWinningNumbersToDatabase($date, $scrapeResult['numbers'], $conn);
    recalculateAllRijenForDate($conn, $date, $scrapeResult['numbers']);

    echo json_encode([
        'success' => true,
        'numbers' => $scrapeResult['numbers'],
        'date' => $date,
        'saved_at' => $now->format('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => $scrapeResult['error'] ?? 'Uitslag niet gevonden voor ' . $date,
        'retry' => true,
        'date' => $date
    ]);
}
?>
