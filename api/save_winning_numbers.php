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

$input = json_decode(file_get_contents('php://input'), true);
$date = $input['date'] ?? '';
$numbers = $input['numbers'] ?? [];

if (empty($date) || empty($numbers)) {
    echo json_encode(['success' => false, 'error' => 'Datum en nummers zijn verplicht']);
    exit();
}

if (count($numbers) !== 20) {
    echo json_encode(['success' => false, 'error' => 'Er moeten exact 20 nummers zijn']);
    exit();
}

// Validate all numbers are between 1 and 80
foreach ($numbers as $num) {
    if (!is_numeric($num) || $num < 1 || $num > 80) {
        echo json_encode(['success' => false, 'error' => 'Alle nummers moeten tussen 1 en 80 zijn']);
        exit();
    }
}

// Check for duplicates
if (count($numbers) !== count(array_unique($numbers))) {
    echo json_encode(['success' => false, 'error' => 'Dubbele nummers zijn niet toegestaan']);
    exit();
}

// Save to database
saveWinningNumbersToDatabase($date, $numbers, $conn);

// Recalculate all rijen for this date
recalculateAllRijenForDate($conn, $date, $numbers);

echo json_encode(['success' => true]);
?>





