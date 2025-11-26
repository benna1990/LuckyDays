<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $playerId = intval($_POST['player_id'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    $name = trim($_POST['name'] ?? '');
    
    if ($playerId <= 0) {
        header('Location: ../dashboard.php?date=' . $date . '&error=no_player');
        exit();
    }
    
    $bonId = createBon($conn, $playerId, $date, $name ?: null);
    
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
