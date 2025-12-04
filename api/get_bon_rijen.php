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

// Get bon_id from query
$bonId = $_GET['bon_id'] ?? null;

if (!$bonId || !is_numeric($bonId)) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige bon_id']);
    exit;
}

try {
    // Haal datum + winnende nummers op om rijen live te herberekenen
    $bonDateResult = pg_query_params($conn, "SELECT date FROM bons WHERE id = $1", [$bonId]);
    if (!$bonDateResult || pg_num_rows($bonDateResult) === 0) {
        echo json_encode(['success' => false, 'error' => 'Bon niet gevonden']);
        exit;
    }
    $bonDateRow = pg_fetch_assoc($bonDateResult);
    $bonDate = $bonDateRow['date'];
    $winningNumbers = getWinningNumbersFromDatabase($bonDate, $conn);

    // Get all rijen for this bon
    $rijenQuery = "
        SELECT 
            r.id,
            r.numbers,
            r.bet,
            r.winnings,
            r.matches,
            r.multiplier,
            b.date
        FROM rijen r
        JOIN bons b ON r.bon_id = b.id
        WHERE r.bon_id = $1
        ORDER BY r.created_at ASC
    ";
    
    $rijenResult = pg_query_params($conn, $rijenQuery, [$bonId]);
    
    if (!$rijenResult) {
        throw new Exception('Database query failed');
    }
    
    $rijen = [];
    while ($row = pg_fetch_assoc($rijenResult)) {
        // Live recalculatie wanneer winnende nummers bestaan
        if ($winningNumbers && is_array($winningNumbers) && count($winningNumbers) > 0) {
            $calc = recalculateRijWinnings($conn, $row['id'], $winningNumbers);
            if ($calc) {
                $row['matches'] = $calc['matches'];
                $row['multiplier'] = $calc['multiplier'];
                $row['winnings'] = $calc['winnings'];
            }
        }
        $rijen[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'rijen' => $rijen
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching bon rijen: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database fout: ' . $e->getMessage()]);
}
