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

// Get player_id from query
$playerId = $_GET['player_id'] ?? null;

if (!$playerId || !is_numeric($playerId)) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige player_id']);
    exit;
}

// Live recalculatie: herbereken alle rijen van deze speler zodra winnende nummers bekend zijn
$recalcQuery = "SELECT id, date FROM bons WHERE player_id = $1";
$recalcResult = pg_query_params($conn, $recalcQuery, [$playerId]);
if ($recalcResult) {
    $seenDates = [];
    while ($row = pg_fetch_assoc($recalcResult)) {
        $dateKey = $row['date'];
        if (isset($seenDates[$dateKey])) {
            continue;
        }
        $winningNums = getWinningNumbersFromDatabase($dateKey, $conn);
        if ($winningNums && is_array($winningNums) && count($winningNums) > 0) {
            recalculateAllRijenForDate($conn, $dateKey, $winningNums);
            $seenDates[$dateKey] = true;
        }
    }
}

try {
    // Get player info with aggregate stats
    $playerQuery = "
        SELECT 
            p.id, 
            p.name, 
            p.color, 
            p.winkel_id,
            w.naam as winkel_naam,
            COALESCE(SUM(r.bet), 0) as total_bet,
            COALESCE(SUM(r.winnings), 0) as total_winnings,
            COUNT(DISTINCT b.id) as total_bons
        FROM players p
        LEFT JOIN winkels w ON p.winkel_id = w.id
        LEFT JOIN bons b ON p.id = b.player_id
        LEFT JOIN rijen r ON b.id = r.bon_id
        WHERE p.id = $1
        GROUP BY p.id, p.name, p.color, p.winkel_id, w.naam
    ";
    
    $playerResult = pg_query_params($conn, $playerQuery, [$playerId]);
    
    if (!$playerResult) {
        throw new Exception('Database query failed');
    }
    
    $player = pg_fetch_assoc($playerResult);
    
    if (!$player) {
        echo json_encode(['success' => false, 'error' => 'Speler niet gevonden']);
        exit;
    }
    
    // Get all bonnen for this player with details
    $bonnenQuery = "
        SELECT 
            b.id as bon_id,
            b.date,
            b.bonnummer,
            b.name as bon_name,
            w.naam as winkel_naam,
            COUNT(r.id) as rijen_count,
            COALESCE(SUM(r.bet), 0) as total_bet,
            COALESCE(SUM(r.winnings), 0) as total_winnings,
            (COALESCE(SUM(r.winnings), 0) - COALESCE(SUM(r.bet), 0)) as saldo
        FROM bons b
        LEFT JOIN winkels w ON b.winkel_id = w.id
        LEFT JOIN rijen r ON b.id = r.bon_id
        WHERE b.player_id = $1
        GROUP BY b.id, b.date, b.bonnummer, b.name, w.naam
        ORDER BY b.date DESC, b.created_at DESC
    ";
    
    $bonnenResult = pg_query_params($conn, $bonnenQuery, [$playerId]);
    
    if (!$bonnenResult) {
        throw new Exception('Database query failed');
    }
    
    $bonnen = [];
    while ($row = pg_fetch_assoc($bonnenResult)) {
        $bonnen[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'player' => $player,
        'bonnen' => $bonnen
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching player detail: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database fout: ' . $e->getMessage()]);
}
