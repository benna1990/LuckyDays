<?php
/**
 * Centrale audit logging helper.
 * Gebruik: add_audit_log($conn, 'action_code', 'entity_type', $entityId, ['key' => 'value']);
 */

if (!function_exists('add_audit_log')) {
    function add_audit_log($conn, string $action, ?string $entityType = null, $entityId = null, array $details = []): void
    {
        if (!$conn || empty($action)) {
            return;
        }

        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? null;
        $sessionId = session_id();

        // Best-effort client info
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $parts = explode(',', $ip);
            $ip = trim($parts[0]);
        }
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $detailsJson = !empty($details) ? json_encode($details) : null;

        @pg_query_params(
            $conn,
            "INSERT INTO audit_log (action, entity_type, entity_id, user_id, username, session_id, ip_address, user_agent, details)
             VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)",
            [
                $action,
                $entityType,
                $entityId !== null ? (string)$entityId : null,
                $userId,
                $username,
                $sessionId ?: null,
                $ip,
                $userAgent,
                $detailsJson
            ]
        );
    }
}
