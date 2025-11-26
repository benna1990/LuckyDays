<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $playerName = trim($_POST['player_name'] ?? '');
    $date = $_POST['date'] ?? date('Y-m-d');
    $bonName = trim($_POST['name'] ?? '');
    
    if (empty($playerName)) {
        header('Location: ../dashboard.php?date=' . $date . '&error=no_player');
        exit();
    }
    
    $player = getPlayerByName($conn, $playerName);
    
    if (!$player) {
        $colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];
        $randomColor = $colors[array_rand($colors)];
        $result = addPlayer($conn, $playerName, $randomColor);
        
        if (!$result['success']) {
            header('Location: ../dashboard.php?date=' . $date . '&error=create_player_failed');
            exit();
        }
        $playerId = $result['id'];
    } else {
        $playerId = $player['id'];
    }
    
    $bonId = createBon($conn, $playerId, $date, $bonName ?: null);
    
    if ($bonId) {
        header('Location: ../bon.php?id=' . $bonId);
    } else {
        header('Location: ../dashboard.php?date=' . $date . '&error=create_failed');
    }
    exit();
}

header('Location: ../dashboard.php');
exit();
?>
