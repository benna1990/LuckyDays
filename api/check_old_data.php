<?php
session_start();

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit();
}

try {
    $cutoff_date = date('Y-m-d', strtotime('-2 months'));
    
    $query = "SELECT COUNT(*) as count FROM bons WHERE date < $1";
    $result = db_query($query, [$cutoff_date]);
    $row = db_fetch_assoc($result);
    
    $has_old_data = intval($row['count'] ?? 0) > 0;
    
    echo json_encode([
        'success' => true,
        'has_old_data' => $has_old_data,
        'count' => intval($row['count'] ?? 0),
        'cutoff_date' => $cutoff_date
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>



