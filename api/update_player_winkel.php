<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once '../config.php';
require_once '../functions.php';
require_once '../audit_log.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$playerId = intval($input['player_id'] ?? 0);
$winkelId = intval($input['winkel_id'] ?? 0);

if ($playerId <= 0 || $winkelId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige parameters']);
    exit();
}

try {
    $old = pg_fetch_assoc(pg_query_params($conn, "SELECT winkel_id FROM players WHERE id = $1", [$playerId]));
    $result = db_query("UPDATE players SET winkel_id = $1 WHERE id = $2", [$winkelId, $playerId]);
    
    if ($result) {
        // Also update all bons from this player
        db_query("UPDATE bons SET winkel_id = $1 WHERE player_id = $2", [$winkelId, $playerId]);
        add_audit_log($conn, 'player_update', 'player', $playerId, [
            'old_winkel_id' => $old['winkel_id'] ?? null,
            'new_winkel_id' => $winkelId
        ]);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Kon speler niet verplaatsen']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


