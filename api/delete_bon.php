<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../config.php';
require_once '../functions.php';
require_once '../audit_log.php';

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

if ($bonId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige bon']);
    exit();
}

$bon = getBonById($conn, $bonId);
if (!$bon) {
    echo json_encode(['success' => false, 'error' => 'Bon niet gevonden']);
    exit();
}

$date = $bon['date'];

if (deleteBon($conn, $bonId)) {
    logBonAction($conn, $bonId, 'delete_bon', [
        'bonnummer' => $bon['bonnummer'],
        'date' => $bon['date'],
        'player' => $bon['player_name'] ?? null,
        'winkel' => $bon['winkel_naam'] ?? null
    ]);
    echo json_encode(['success' => true, 'date' => $date]);
} else {
    echo json_encode(['success' => false, 'error' => 'Kon bon niet verwijderen']);
}
?>
