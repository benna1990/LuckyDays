<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../config.php';
require_once '../functions.php';
require_once '../audit_log.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
        exit();
    }
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $playerId = intval($input['player_id'] ?? 0);
        $date = $input['date'] ?? date('Y-m-d');
        $bonName = trim($input['name'] ?? '');
        $bonnummer = trim($input['bonnummer'] ?? '');
        $activeWinkelId = $_SESSION['selected_winkel'] ?? null;
        
        if ($playerId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Ongeldige speler']);
            exit();
        }
        
        if ($activeWinkelId === null) {
            echo json_encode(['success' => false, 'error' => 'Selecteer eerst een winkel']);
            exit();
        }
        
        $bonId = createBon($conn, $playerId, $date, $bonName ?: null, $bonnummer ?: null, $activeWinkelId);
        
        if ($bonId) {
            logBonAction($conn, $bonId, 'create', [
                'player_id' => $playerId,
                'date' => $date,
                'bonnummer' => $bonnummer ?: null,
                'winkel_id' => $activeWinkelId
            ]);
            add_audit_log($conn, 'bon_create', 'bon', $bonId, [
                'player_id' => $playerId,
                'date' => $date,
                'bonnummer' => $bonnummer ?: null,
                'winkel_id' => $activeWinkelId
            ]);
            echo json_encode(['success' => true, 'id' => $bonId]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Kon bon niet aanmaken']);
        }
        exit();
    }
    
    $playerName = trim($_POST['player_name'] ?? '');
    $date = $_POST['date'] ?? date('Y-m-d');
    $bonName = trim($_POST['name'] ?? '');
    $bonnummer = trim($_POST['bonnummer'] ?? '');
    
    if (empty($playerName)) {
        header('Location: ../dashboard.php?date=' . $date . '&error=no_player');
        exit();
    }
    
    $player = getPlayerByName($conn, $playerName);
    
    if (!$player) {
        $color = generateUniqueColor($conn);
        $activeWinkelId = $_SESSION['selected_winkel'] ?? null;
        if ($activeWinkelId === null) {
            header('Location: ../dashboard.php?date=' . $date . '&error=no_winkel');
            exit();
        }

        $result = addPlayer($conn, $playerName, $color, $activeWinkelId);
        
        if (!$result['success']) {
            header('Location: ../dashboard.php?date=' . $date . '&error=create_player_failed');
            exit();
        }
        $playerId = $result['id'];
        add_audit_log($conn, 'player_create', 'player', $playerId, [
            'name' => $playerName,
            'color' => $color,
            'winkel_id' => $activeWinkelId
        ]);
    } else {
        $playerId = $player['id'];
    }
    
    $activeWinkelId = $_SESSION['selected_winkel'] ?? null;
    if ($activeWinkelId === null) {
        header('Location: ../dashboard.php?date=' . $date . '&error=no_winkel');
        exit();
    }

    $bonId = createBon($conn, $playerId, $date, $bonName ?: null, $bonnummer ?: null, $activeWinkelId);
    
    if ($bonId) {
        logBonAction($conn, $bonId, 'create', [
            'player_id' => $playerId,
            'date' => $date,
            'bonnummer' => $bonnummer ?: null,
            'winkel_id' => $activeWinkelId
        ]);
        add_audit_log($conn, 'bon_create', 'bon', $bonId, [
            'player_id' => $playerId,
            'date' => $date,
            'bonnummer' => $bonnummer ?: null,
            'winkel_id' => $activeWinkelId
        ]);
        header('Location: ../bon.php?id=' . $bonId);
    } else {
        header('Location: ../dashboard.php?date=' . $date . '&error=create_failed');
    }
    exit();
}

header('Location: ../dashboard.php');
exit();
?>
