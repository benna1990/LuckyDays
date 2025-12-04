<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit();
}

$start = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
$end = $_GET['end'] ?? date('Y-m-d');
$winkel = $_GET['winkel'] ?? null;

$params = [$start, $end];
$where = "DATE(b.date) BETWEEN $1 AND $2";
if ($winkel !== null && $winkel !== '' && $winkel !== 'all') {
    $where .= " AND b.winkel_id = $3";
    $params[] = intval($winkel);
}

// Try query with checked columns; if DB doesn't have them yet, fallback without.
$queryBase = "SELECT b.id, b.date, b.player_id, p.name as player_name, p.color as player_color,
                 b.bonnummer, b.winkel_id, w.naam as winkel_name,
                 COALESCE(SUM(r.bet),0) as total_bet,
                 COALESCE(SUM(r.winnings),0) as total_winnings,
                 COUNT(r.id) as rijen_count";
$queryChecked = $queryBase . ",
                 b.checked_at, b.checked_by
          FROM bons b
          LEFT JOIN players p ON b.player_id = p.id
          LEFT JOIN winkels w ON b.winkel_id = w.id
          LEFT JOIN rijen r ON b.id = r.bon_id
          WHERE $where
          GROUP BY b.id, b.date, b.player_id, p.name, p.color, b.bonnummer, b.winkel_id, w.naam, b.checked_at, b.checked_by
          ORDER BY b.date DESC, b.id DESC";

$queryFallback = $queryBase . "
          FROM bons b
          LEFT JOIN players p ON b.player_id = p.id
          LEFT JOIN winkels w ON b.winkel_id = w.id
          LEFT JOIN rijen r ON b.id = r.bon_id
          WHERE $where
          GROUP BY b.id, b.date, b.player_id, p.name, p.color, b.bonnummer, b.winkel_id, w.naam
          ORDER BY b.date DESC, b.id DESC";

$bonnen = [];
$result = db_query($queryChecked, $params);
if ($result === false) {
    // fallback zonder checked kolommen
    $result = db_query($queryFallback, $params);
    $bonnen = db_fetch_all($result) ?: [];
    // voeg lege velden toe
    $bonnen = array_map(function($b){
        $b['checked_at'] = null;
        $b['checked_by'] = null;
        return $b;
    }, $bonnen);
} else {
    $bonnen = db_fetch_all($result) ?: [];
}

echo json_encode([
    'success' => true,
    'bonnen' => array_map(function($b){
        return [
            'id' => (int)$b['id'],
            'date' => $b['date'],
            'player_name' => $b['player_name'] ?? 'Onbekend',
            'player_color' => $b['player_color'] ?? '#4B5563',
            'bonnummer' => $b['bonnummer'] ?? '',
            'winkel_id' => $b['winkel_id'] ?? null,
            'winkel_name' => $b['winkel_name'] ?? 'Onbekend',
            'bet' => floatval($b['total_bet']),
            'winnings' => floatval($b['total_winnings']),
            'rijen_count' => (int)$b['rijen_count'],
            'checked_at' => $b['checked_at'],
            'checked_by' => $b['checked_by']
        ];
    }, $bonnen)
]);
