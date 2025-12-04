<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

// Get date from query
$date = $_GET['date'] ?? null;

if (!$date) {
    echo json_encode(['success' => false, 'error' => 'Geen datum opgegeven']);
    exit;
}

try {
    // Get winning numbers for this date
    $query = "
        SELECT numbers 
        FROM winning_numbers 
        WHERE date = $1 
        LIMIT 1
    ";
    
    $result = db_query($query, [$date]);
    $row = db_fetch_assoc($result);
    
    if ($row && $row['numbers']) {
        // Convert comma-separated string to array of integers
        $numbers = array_map('intval', explode(',', $row['numbers']));
        
        echo json_encode([
            'success' => true,
            'date' => $date,
            'numbers' => $numbers
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Geen winnende nummers voor deze datum'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error fetching winning numbers: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database fout']);
}


