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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit();
}

$bonId = intval($_POST['bon_id'] ?? 0);
$numbers = $_POST['numbers'] ?? '';
$bet = floatval($_POST['bet'] ?? 1.00);

if ($bonId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige bon']);
    exit();
}

$bon = getBonById($conn, $bonId);
if (!$bon) {
    echo json_encode(['success' => false, 'error' => 'Bon niet gevonden']);
    exit();
}

$numbersArray = array_map('intval', array_filter(explode(',', $numbers)));
if (count($numbersArray) < 1 || count($numbersArray) > 10) {
    echo json_encode(['success' => false, 'error' => 'Voer 1-10 nummers in']);
    exit();
}

foreach ($numbersArray as $num) {
    if ($num < 1 || $num > 80) {
        echo json_encode(['success' => false, 'error' => 'Nummers moeten tussen 1 en 80 zijn']);
        exit();
    }
}

if ($bet < 0.50) {
    echo json_encode(['success' => false, 'error' => 'Minimum inzet is €0.50']);
    exit();
}

$winningNumbers = getWinningNumbersFromDatabase($bon['date'], $conn) ?? [];

$rijId = addRij($conn, $bonId, $numbersArray, $bet, $winningNumbers);

if ($rijId) {
    $rij = pg_fetch_assoc(pg_query_params($conn, "SELECT * FROM rijen WHERE id = $1", [$rijId]));
    echo json_encode([
        'success' => true, 
        'id' => $rijId,
        'rij' => $rij,
        'winning_numbers' => $winningNumbers
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Kon rij niet opslaan']);
}
?>
