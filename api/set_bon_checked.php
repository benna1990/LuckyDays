<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
require_once '../php/services/LoggerService.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$bonId = intval($input['bon_id'] ?? 0);
$checked = filter_var($input['checked'] ?? false, FILTER_VALIDATE_BOOLEAN);
$user = $_SESSION['admin_username'] ?? 'admin';
$userId = $_SESSION['user_id'] ?? 0;

if ($bonId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige bon']);
    exit();
}

// Haal huidige status op VOOR de update
$current = pg_query_params($conn, "SELECT checked_at, checked_by FROM bons WHERE id = $1", [$bonId]);
$currentRow = $current ? pg_fetch_assoc($current) : null;
$alreadyChecked = $currentRow && !empty($currentRow['checked_at']);

// Check of dit een no-op is
if (($checked && $alreadyChecked) || (!$checked && !$alreadyChecked)) {
    echo json_encode(['success' => true]);
    exit();
}

// Bewaar oude waarden voor logging
$oldValues = [
    'checked_at' => $currentRow['checked_at'] ?? null,
    'checked_by' => $currentRow['checked_by'] ?? null
];

// Voer update uit
if ($checked) {
    $res = db_query("UPDATE bons SET checked_at = NOW(), checked_by = $1 WHERE id = $2", [$user, $bonId]);
} else {
    $res = db_query("UPDATE bons SET checked_at = NULL, checked_by = NULL WHERE id = $1", [$bonId]);
}

if ($res) {
    // Nieuwe waarden
    $newValues = [
        'checked_at' => $checked ? date('Y-m-d H:i:s') : null,
        'checked_by' => $checked ? $user : null
    ];

    // ✅ Gebruik LoggerService - voorkomt duplicates!
    try {
        $logger = new LoggerService($conn, __DIR__ . '/../logs');
        $logger->logChange(
            $userId,
            $checked ? 'bon_checked' : 'bon_unchecked',
            'bon',
            $bonId,
            $oldValues,
            $newValues,
            ['user' => $user]
        );
    } catch (Exception $e) {
        // Log failure, maar laat de bon update succesvol zijn
        error_log("Failed to log bon check: " . $e->getMessage());
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Update mislukt']);
}
