<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit();
}

$bonId = intval($_GET['bon_id'] ?? 0);
if ($bonId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Geen bon id']);
    exit();
}

$tableCheck = pg_query($conn, "SELECT to_regclass('public.audit_log') as tbl");
$useAudit = false;
if ($tableCheck && ($row = pg_fetch_assoc($tableCheck)) && !empty($row['tbl'])) {
    $useAudit = true;
}

if ($useAudit) {
    $res = pg_query_params(
        $conn,
        "SELECT id, action, username as user_name, details, created_at 
         FROM audit_log 
         WHERE entity_type = 'bon' AND entity_id = $1 
         ORDER BY created_at DESC 
         LIMIT 200",
        [$bonId]
    );
} else {
    $res = pg_query_params($conn, "SELECT id, action, user_name, details, created_at FROM bon_logs WHERE bon_id = $1 ORDER BY created_at DESC", [$bonId]);
}
$logs = [];
if ($res) {
    while ($row = pg_fetch_assoc($res)) {
        $decoded = null;
        if (!empty($row['details'])) {
            $decoded = json_decode($row['details'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = $row['details'];
            }
        }

        $logs[] = [
            'id' => (int)$row['id'],
            'action' => $row['action'],
            'user_name' => $row['user_name'],
            'details_parsed' => $decoded,
            'created_at' => $row['created_at']
        ];
    }
}

echo json_encode(['success' => true, 'logs' => $logs]);
