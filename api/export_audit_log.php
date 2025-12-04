<?php
/**
 * Export Audit Log naar CSV
 *
 * Gebruik dit endpoint om audit logs te exporteren voor rapportage.
 *
 * Usage:
 * POST met JSON body:
 * {
 *   "start_date": "2024-12-01",
 *   "end_date": "2024-12-04",
 *   "action": "update_rij_numbers",  // optional
 *   "entity_type": "rij",             // optional
 *   "user_id": 5                      // optional
 * }
 *
 * Of via form POST:
 * <form action="api/export_audit_log.php" method="POST">
 *   <input type="date" name="start_date" value="2024-12-01">
 *   <input type="date" name="end_date" value="2024-12-04">
 *   <select name="action">...</select>
 *   <button type="submit">Export CSV</button>
 * </form>
 */

session_start();
require_once '../config.php';
require_once '../php/services/LoggerService.php';

// Auth check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die('Niet ingelogd');
}

// Parse input (support both JSON and form POST)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $input = $_POST;
}

// Extract filters
$startDate = $input['start_date'] ?? null;
$endDate = $input['end_date'] ?? null;
$action = !empty($input['action']) ? $input['action'] : null;
$entityType = !empty($input['entity_type']) ? $input['entity_type'] : null;
$userId = !empty($input['user_id']) ? intval($input['user_id']) : null;

// Defaults: laatste 7 dagen
if ($startDate === null) {
    $startDate = date('Y-m-d', strtotime('-7 days'));
}
if ($endDate === null) {
    $endDate = date('Y-m-d');
}

try {
    $logger = new LoggerService($conn);

    $csv = $logger->exportAuditLogCsv(
        $startDate,
        $endDate,
        $action,
        $entityType,
        $userId
    );

    // Generate filename
    $filename = 'audit_log_' . $startDate . '_to_' . $endDate;
    if ($action) {
        $filename .= '_' . $action;
    }
    $filename .= '.csv';

    // Send CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $csv;

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Export failed: ' . $e->getMessage()
    ]);
}
