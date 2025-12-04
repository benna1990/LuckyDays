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

$rijId = intval($_POST['rij_id'] ?? 0);

if ($rijId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige rij']);
    exit();
}

$rijData = pg_query_params($conn, "SELECT id, bon_id, numbers, bet FROM rijen WHERE id = $1", [$rijId]);
if (!$rijData || pg_num_rows($rijData) === 0) {
    echo json_encode(['success' => false, 'error' => 'Rij niet gevonden']);
    exit();
}
$rijRow = pg_fetch_assoc($rijData);
$bonId = intval($rijRow['bon_id']);

if (deleteRij($conn, $rijId)) {
    logBonAction($conn, $bonId, 'delete_row', [
        'rij_id' => $rijId,
        'numbers' => $rijRow['numbers'],
        'bet' => floatval($rijRow['bet'])
    ]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Kon rij niet verwijderen']);
}
?>
