<?php
declare(strict_types=1);

/**
 * LoggerService
 *
 * Forensisch logging systeem voor audit trails en error logging.
 *
 * Features:
 * - logChange(): Forensische voor/na diff tracking in JSON
 * - logError(): File-based error logging naar /logs/error.log
 * - GEEN error suppression (@) - alle failures gooien exceptions
 * - Voorkomt duplicate logs door eerst te checken op recente duplicaten
 * - Export functionaliteit voor audit logs
 *
 * Usage:
 * ```php
 * $logger = new LoggerService($conn);
 *
 * // Voor een update:
 * $oldValues = ['numbers' => '1,2,3', 'bet' => '5.00'];
 * $newValues = ['numbers' => '4,5,6', 'bet' => '5.00'];
 * $logger->logChange(
 *     $userId,
 *     'update_rij_numbers',
 *     'rij',
 *     $rijId,
 *     $oldValues,
 *     $newValues
 * );
 *
 * // Error logging:
 * try {
 *     // risky code
 * } catch (Exception $e) {
 *     $logger->logError('Database update failed', $e->getTraceAsString());
 *     throw $e;
 * }
 * ```
 *
 * @package LuckyDays
 * @version 1.0.0
 */
class LoggerService
{
    private $conn;
    private string $logDirectory;
    private int $duplicateCheckWindowSeconds = 5;

    /**
     * Constructor
     *
     * @param resource $conn PostgreSQL database connection
     * @param string $logDirectory Directory voor error logs (default: /logs)
     * @throws \InvalidArgumentException Als $conn geen geldige resource is
     */
    public function __construct($conn, string $logDirectory = '/logs')
    {
        if (!is_resource($conn) && !is_object($conn)) {
            throw new \InvalidArgumentException("Invalid database connection provided");
        }

        $this->conn = $conn;
        $this->logDirectory = rtrim($logDirectory, '/');

        // Maak log directory als die niet bestaat
        if (!is_dir($this->logDirectory)) {
            if (!@mkdir($this->logDirectory, 0755, true)) {
                throw new \RuntimeException("Failed to create log directory: {$this->logDirectory}");
            }
        }

        // Check schrijfrechten
        if (!is_writable($this->logDirectory)) {
            throw new \RuntimeException("Log directory is not writable: {$this->logDirectory}");
        }
    }

    /**
     * Log een wijziging met voor/na diff tracking
     *
     * Deze methode berekent automatisch het verschil tussen oude en nieuwe waarden
     * en slaat dit forensisch op in de audit_log tabel.
     *
     * @param int $userId ID van de gebruiker die de wijziging maakte
     * @param string $action Actie code (bv. 'update_rij_numbers', 'delete_bon')
     * @param string $entityType Type entiteit (bv. 'rij', 'bon', 'player')
     * @param int $entityId ID van de gewijzigde entiteit
     * @param array $oldValues Oude waarden (key => value)
     * @param array $newValues Nieuwe waarden (key => value)
     * @param array $context Extra context (optional, bv. ['reason' => 'correction', 'ticket' => '#123'])
     *
     * @throws \RuntimeException Als de log niet geschreven kan worden
     * @throws \InvalidArgumentException Als verplichte parameters ontbreken
     *
     * @return int Het ID van de nieuwe audit log entry
     */
    public function logChange(
        int $userId,
        string $action,
        string $entityType,
        int $entityId,
        array $oldValues,
        array $newValues,
        array $context = []
    ): int {
        // Validatie
        if (empty($action)) {
            throw new \InvalidArgumentException("Action cannot be empty");
        }
        if (empty($entityType)) {
            throw new \InvalidArgumentException("Entity type cannot be empty");
        }
        if ($entityId <= 0) {
            throw new \InvalidArgumentException("Entity ID must be positive");
        }

        // Bereken diff (alleen de gewijzigde velden)
        $diff = $this->calculateDiff($oldValues, $newValues);

        // Check voor duplicate logs (binnen 5 seconden met zelfde data)
        if ($this->isDuplicateLog($action, $entityType, $entityId, $diff)) {
            // Return 0 om aan te geven dat we GEEN nieuwe log hebben gemaakt
            return 0;
        }

        // Bouw details object
        $details = [
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'diff' => $diff,
            'changed_fields' => array_keys($diff),
            'change_count' => count($diff),
        ];

        // Voeg context toe als die er is
        if (!empty($context)) {
            $details['context'] = $context;
        }

        // Session info
        $username = $_SESSION['username'] ?? $_SESSION['admin_username'] ?? 'system';
        $sessionId = session_id() ?: null;
        $ip = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($detailsJson === false) {
            throw new \RuntimeException("Failed to encode details to JSON: " . json_last_error_msg());
        }

        // Insert in audit_log - GEEN error suppression!
        $result = pg_query_params(
            $this->conn,
            "INSERT INTO audit_log
                (action, entity_type, entity_id, user_id, username, session_id, ip_address, user_agent, details, created_at)
             VALUES
                ($1, $2, $3, $4, $5, $6, $7, $8, $9, NOW())
             RETURNING id",
            [
                $action,
                $entityType,
                (string)$entityId,
                $userId,
                $username,
                $sessionId,
                $ip,
                $userAgent,
                $detailsJson
            ]
        );

        if ($result === false) {
            $error = pg_last_error($this->conn);
            throw new \RuntimeException("Failed to write audit log: " . $error);
        }

        $row = pg_fetch_assoc($result);
        if (!$row || !isset($row['id'])) {
            throw new \RuntimeException("Audit log was inserted but no ID was returned");
        }

        return (int)$row['id'];
    }

    /**
     * Log een error naar bestand
     *
     * Schrijft kritieke fouten naar /logs/error.log voor later onderzoek.
     * Format: [timestamp] [ERROR] Message\nTrace\n---\n
     *
     * @param string $message Error message
     * @param string $trace Stack trace (gebruik Exception::getTraceAsString())
     * @param array $context Extra context (optional)
     *
     * @throws \RuntimeException Als het logbestand niet geschreven kan worden
     */
    public function logError(string $message, string $trace, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $sessionId = session_id() ?: 'no-session';
        $userId = $_SESSION['user_id'] ?? $_SESSION['admin_username'] ?? 'unknown';
        $ip = $this->getClientIp();

        $logEntry = sprintf(
            "[%s] [ERROR] [Session: %s] [User: %s] [IP: %s]\n%s\n\nStack Trace:\n%s\n",
            $timestamp,
            $sessionId,
            $userId,
            $ip ?: 'unknown',
            $message,
            $trace
        );

        if (!empty($context)) {
            $logEntry .= "\nContext:\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }

        $logEntry .= str_repeat("-", 80) . "\n\n";

        $logFile = $this->logDirectory . '/error.log';

        // Probeer te schrijven - GEEN error suppression
        $result = file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            throw new \RuntimeException("Failed to write to error log: {$logFile}");
        }

        // Optioneel: log ook naar syslog voor production monitoring
        error_log("LuckyDays Error: {$message}");
    }

    /**
     * Export audit logs naar CSV
     *
     * @param string|null $startDate Start datum (YYYY-MM-DD), null = geen filter
     * @param string|null $endDate Eind datum (YYYY-MM-DD), null = geen filter
     * @param string|null $action Filter op actie code, null = alle acties
     * @param string|null $entityType Filter op entiteit type, null = alle types
     * @param int|null $userId Filter op user ID, null = alle users
     *
     * @return string CSV string (klaar voor download)
     * @throws \RuntimeException Als de query faalt
     */
    public function exportAuditLogCsv(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $action = null,
        ?string $entityType = null,
        ?int $userId = null
    ): string {
        // Bouw query met filters
        $query = "SELECT
                    id,
                    created_at,
                    action,
                    entity_type,
                    entity_id,
                    user_id,
                    username,
                    ip_address,
                    details
                  FROM audit_log
                  WHERE 1=1";

        $params = [];
        $paramCount = 0;

        if ($startDate !== null) {
            $paramCount++;
            $query .= " AND created_at >= $" . $paramCount;
            $params[] = $startDate . ' 00:00:00';
        }

        if ($endDate !== null) {
            $paramCount++;
            $query .= " AND created_at <= $" . $paramCount;
            $params[] = $endDate . ' 23:59:59';
        }

        if ($action !== null) {
            $paramCount++;
            $query .= " AND action = $" . $paramCount;
            $params[] = $action;
        }

        if ($entityType !== null) {
            $paramCount++;
            $query .= " AND entity_type = $" . $paramCount;
            $params[] = $entityType;
        }

        if ($userId !== null) {
            $paramCount++;
            $query .= " AND user_id = $" . $paramCount;
            $params[] = $userId;
        }

        $query .= " ORDER BY created_at DESC";

        // Execute query - GEEN error suppression
        $result = empty($params)
            ? pg_query($this->conn, $query)
            : pg_query_params($this->conn, $query, $params);

        if ($result === false) {
            throw new \RuntimeException("Failed to export audit logs: " . pg_last_error($this->conn));
        }

        // CSV header
        $csv = "ID,Timestamp,Action,Entity Type,Entity ID,User ID,Username,IP Address,Changed Fields,Details\n";

        while ($row = pg_fetch_assoc($result)) {
            $details = json_decode($row['details'] ?? '{}', true) ?: [];
            $changedFields = implode('; ', $details['changed_fields'] ?? []);
            $diffSummary = $this->formatDiffForCsv($details['diff'] ?? []);

            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s,%s,\"%s\",\"%s\"\n",
                $row['id'],
                $row['created_at'],
                $row['action'],
                $row['entity_type'] ?? '',
                $row['entity_id'] ?? '',
                $row['user_id'] ?? '',
                $row['username'] ?? '',
                $row['ip_address'] ?? '',
                $changedFields,
                $diffSummary
            );
        }

        return $csv;
    }

    /**
     * Haal recente audit logs op (voor UI/rapportage)
     *
     * @param int $limit Aantal records (default: 100)
     * @param int $offset Offset voor paginering (default: 0)
     * @param array $filters Associatieve array met filters (action, entity_type, user_id)
     *
     * @return array Array van audit log records
     * @throws \RuntimeException Als de query faalt
     */
    public function getRecentLogs(int $limit = 100, int $offset = 0, array $filters = []): array
    {
        $query = "SELECT * FROM audit_log WHERE 1=1";
        $params = [];
        $paramCount = 0;

        if (isset($filters['action'])) {
            $paramCount++;
            $query .= " AND action = $" . $paramCount;
            $params[] = $filters['action'];
        }

        if (isset($filters['entity_type'])) {
            $paramCount++;
            $query .= " AND entity_type = $" . $paramCount;
            $params[] = $filters['entity_type'];
        }

        if (isset($filters['user_id'])) {
            $paramCount++;
            $query .= " AND user_id = $" . $paramCount;
            $params[] = $filters['user_id'];
        }

        $query .= " ORDER BY created_at DESC LIMIT $" . ($paramCount + 1) . " OFFSET $" . ($paramCount + 2);
        $params[] = $limit;
        $params[] = $offset;

        $result = pg_query_params($this->conn, $query, $params);

        if ($result === false) {
            throw new \RuntimeException("Failed to fetch audit logs: " . pg_last_error($this->conn));
        }

        $logs = [];
        while ($row = pg_fetch_assoc($result)) {
            // Decode details JSON
            if (!empty($row['details'])) {
                $row['details_parsed'] = json_decode($row['details'], true);
            }
            $logs[] = $row;
        }

        return $logs;
    }

    // ==================== PRIVATE HELPERS ====================

    /**
     * Bereken het verschil tussen oude en nieuwe waarden
     *
     * @param array $oldValues
     * @param array $newValues
     * @return array Diff met alleen gewijzigde velden: ['field' => ['old' => ..., 'new' => ...]]
     */
    private function calculateDiff(array $oldValues, array $newValues): array
    {
        $diff = [];

        // Check alle velden in $newValues
        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;

            // Vergelijk strict (=== voor types, maar ook string '0' vs int 0)
            if ($oldValue !== $newValue) {
                $diff[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }

        // Check voor verwijderde velden (in old maar niet in new)
        foreach ($oldValues as $key => $oldValue) {
            if (!array_key_exists($key, $newValues)) {
                $diff[$key] = [
                    'old' => $oldValue,
                    'new' => null
                ];
            }
        }

        return $diff;
    }

    /**
     * Check of deze log een duplicate is (binnen N seconden met zelfde data)
     *
     * Dit voorkomt het probleem waar meerdere AJAX calls of page refreshes
     * dezelfde log 11x achter elkaar schrijven.
     *
     * @param string $action
     * @param string $entityType
     * @param int $entityId
     * @param array $diff
     * @return bool True als dit waarschijnlijk een duplicate is
     */
    private function isDuplicateLog(string $action, string $entityType, int $entityId, array $diff): bool
    {
        $windowStart = date('Y-m-d H:i:s', time() - $this->duplicateCheckWindowSeconds);

        // Check of er al een log bestaat in de laatste N seconden met zelfde actie/entity
        // We checken de diff later in PHP omdat JSON comparison in PostgreSQL onbetrouwbaar is
        $result = pg_query_params(
            $this->conn,
            "SELECT id, details FROM audit_log
             WHERE action = $1
               AND entity_type = $2
               AND entity_id = $3
               AND created_at >= $4
             ORDER BY created_at DESC
             LIMIT 5",
            [
                $action,
                $entityType,
                (string)$entityId,
                $windowStart
            ]
        );

        if ($result === false) {
            // Als de check faalt, beter een duplicate log dan een exception
            return false;
        }

        // Check of er een log is met identieke diff
        // Gebruik sortArrayRecursive voor consistente volgorde (ook in nested arrays)
        $sortedDiff = $this->sortArrayRecursive($diff);
        $diffJson = json_encode($sortedDiff, JSON_UNESCAPED_UNICODE);

        while ($row = pg_fetch_assoc($result)) {
            if (empty($row['details'])) {
                continue;
            }

            $existingDetails = json_decode($row['details'], true);
            if (!$existingDetails || !isset($existingDetails['diff'])) {
                continue;
            }

            $existingDiff = $this->sortArrayRecursive($existingDetails['diff']);
            $existingDiffJson = json_encode($existingDiff, JSON_UNESCAPED_UNICODE);

            // Vergelijk de diff JSON strings
            if ($diffJson === $existingDiffJson) {
                return true;  // Duplicate gevonden!
            }
        }

        return false;  // Geen duplicate
    }

    /**
     * Sort array recursively voor consistente JSON vergelijking
     *
     * @param array $array
     * @return array
     */
    private function sortArrayRecursive(array $array): array
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = $this->sortArrayRecursive($value);
            }
        }
        ksort($array);
        return $array;
    }

    /**
     * Haal client IP op (met X-Forwarded-For support)
     *
     * @return string|null
     */
    private function getClientIp(): ?string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);

        if (is_string($ip) && strpos($ip, ',') !== false) {
            // X-Forwarded-For kan meerdere IPs bevatten, pak de eerste
            $parts = explode(',', $ip);
            $ip = trim($parts[0]);
        }

        return $ip;
    }

    /**
     * Format diff voor CSV export (leesbaarder)
     *
     * @param array $diff
     * @return string
     */
    private function formatDiffForCsv(array $diff): string
    {
        if (empty($diff)) {
            return '';
        }

        $parts = [];
        foreach ($diff as $field => $change) {
            $old = $change['old'] ?? 'null';
            $new = $change['new'] ?? 'null';
            $parts[] = "{$field}: {$old} → {$new}";
        }

        return implode('; ', $parts);
    }

    /**
     * Health check - test of de logger goed werkt
     *
     * @return bool
     */
    public function healthCheck(): bool
    {
        // Check database connectie
        $result = pg_query($this->conn, "SELECT 1");
        if ($result === false) {
            return false;
        }

        // Check of audit_log tabel bestaat
        $result = pg_query($this->conn, "SELECT COUNT(*) FROM audit_log LIMIT 1");
        if ($result === false) {
            return false;
        }

        // Check log directory schrijfrechten
        if (!is_writable($this->logDirectory)) {
            return false;
        }

        return true;
    }
}
