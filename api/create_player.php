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

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');
$activeWinkelId = $_SESSION['selected_winkel'] ?? null;

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Naam is verplicht']);
    exit();
}

if ($activeWinkelId === null) {
    echo json_encode(['success' => false, 'error' => 'Selecteer eerst een winkel']);
    exit();
}

$existingPlayer = getPlayerByName($conn, $name);
if ($existingPlayer) {
    echo json_encode(['success' => true, 'id' => $existingPlayer['id'], 'color' => $existingPlayer['color'], 'existing' => true]);
    exit();
}

$color = generateUniqueColor($conn);
$result = addPlayer($conn, $name, $color, $activeWinkelId);

if ($result['success']) {
    add_audit_log($conn, 'player_create', 'player', $result['id'], [
        'name' => $name,
        'color' => $color,
        'winkel_id' => $activeWinkelId
    ]);
    echo json_encode(['success' => true, 'id' => $result['id'], 'color' => $color, 'existing' => false]);
} else {
    echo json_encode(['success' => false, 'error' => $result['error']]);
}
?>
