<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit();
}

$tableCheck = pg_query($conn, "SELECT to_regclass('public.audit_log') as tbl");
$row = $tableCheck ? pg_fetch_assoc($tableCheck) : null;
if (!$row || empty($row['tbl'])) {
    echo json_encode(['success' => false, 'error' => 'audit_log tabel ontbreekt (draai migratie 005_create_audit_log.sql)']);
    exit();
}

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = min(100, max(10, intval($_GET['per_page'] ?? 50)));
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if (!empty($_GET['start_date'])) {
    $params[] = $_GET['start_date'];
    $where[] = "created_at >= $".count($params);
}
if (!empty($_GET['end_date'])) {
    $params[] = $_GET['end_date'] . ' 23:59:59';
    $where[] = "created_at <= $".count($params);
}
if (!empty($_GET['user_id'])) {
    $params[] = intval($_GET['user_id']);
    $where[] = "user_id = $".count($params);
}
if (!empty($_GET['action'])) {
    $params[] = $_GET['action'];
    $where[] = "action = $".count($params);
}
if (!empty($_GET['entity_type'])) {
    $params[] = $_GET['entity_type'];
    $where[] = "entity_type = $".count($params);
}
if (!empty($_GET['entity_id'])) {
    $params[] = $_GET['entity_id'];
    $where[] = "entity_id = $".count($params);
}
if (!empty($_GET['search'])) {
    $params[] = '%' . $_GET['search'] . '%';
    $where[] = "(username ILIKE $".count($params)." OR action ILIKE $".count($params)." OR details::text ILIKE $".count($params)." )";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countSql = "SELECT COUNT(*) FROM audit_log $whereSql";
$countRes = pg_query_params($conn, $countSql, $params);
$total = $countRes ? intval(pg_fetch_result($countRes, 0, 0)) : 0;

$querySql = "
    SELECT id, created_at, user_id, username, session_id, ip_address, user_agent, action, entity_type, entity_id, details
    FROM audit_log
    $whereSql
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
";
$res = pg_query_params($conn, $querySql, $params);

$items = [];
if ($res) {
    while ($row = pg_fetch_assoc($res)) {
        $decoded = null;
        if (!empty($row['details'])) {
            $decoded = json_decode($row['details'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = $row['details'];
            }
        }
        $items[] = [
            'id' => (int)$row['id'],
            'created_at' => $row['created_at'],
            'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
            'username' => $row['username'],
            'session_id' => $row['session_id'],
            'ip_address' => $row['ip_address'],
            'user_agent' => $row['user_agent'],
            'action' => $row['action'],
            'entity_type' => $row['entity_type'],
            'entity_id' => $row['entity_id'],
            'details' => $decoded
        ];
    }
}

echo json_encode([
    'success' => true,
    'items' => $items,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage
]);
