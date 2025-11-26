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

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Naam is verplicht']);
    exit();
}

$existingPlayer = getPlayerByName($conn, $name);
if ($existingPlayer) {
    echo json_encode(['success' => true, 'id' => $existingPlayer['id'], 'color' => $existingPlayer['color'], 'existing' => true]);
    exit();
}

$color = generateUniqueColor($conn);
$result = addPlayer($conn, $name, $color);

if ($result['success']) {
    echo json_encode(['success' => true, 'id' => $result['id'], 'color' => $color, 'existing' => false]);
} else {
    echo json_encode(['success' => false, 'error' => $result['error']]);
}
?>
