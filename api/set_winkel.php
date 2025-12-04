<?php
session_start();

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$winkelId = $input['winkel_id'] ?? null;

// null betekent "Alles"
if ($winkelId === 'null' || $winkelId === null) {
    $_SESSION['selected_winkel'] = null;
} else {
    $_SESSION['selected_winkel'] = intval($winkelId);
}

echo json_encode([
    'success' => true,
    'winkel_id' => $_SESSION['selected_winkel']
]);
?>



