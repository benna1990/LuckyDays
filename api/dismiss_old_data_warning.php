<?php
session_start();

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit();
}

$_SESSION['old_data_warning_dismissed'] = true;

echo json_encode(['success' => true]);
?>



