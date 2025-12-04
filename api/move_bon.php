<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
require_once '../audit_log.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$bonId = intval($input['bon_id'] ?? 0);
$newWinkelId = isset($input['winkel_id']) ? intval($input['winkel_id']) : null;
$newDate = $input['date'] ?? null;
$moveSeries = filter_var($input['move_series'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($bonId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige bon']);
    exit();
}

$bon = getBonById($conn, $bonId);
if (!$bon) {
    echo json_encode(['success' => false, 'error' => 'Bon niet gevonden']);
    exit();
}

if ($newWinkelId === null && !$newDate) {
    echo json_encode(['success' => false, 'error' => 'Geen wijzigingen opgegeven']);
    exit();
}

$idsToMove = [$bonId];
if ($moveSeries && !empty($bon['trekking_groep_id'])) {
    $serieRes = pg_query_params($conn, "SELECT id FROM bons WHERE trekking_groep_id = $1", [$bon['trekking_groep_id']]);
    if ($serieRes) {
        while ($row = pg_fetch_assoc($serieRes)) {
            $idsToMove[] = intval($row['id']);
        }
    }
}
$idsToMove = array_values(array_unique($idsToMove));

$dateToSet = $newDate ?: $bon['date'];
$winkelToSet = $newWinkelId ?: $bon['winkel_id'];

$inParams = '{' . implode(',', $idsToMove) . '}';
$update = pg_query_params(
    $conn,
    "UPDATE bons SET date = $1, winkel_id = $2 WHERE id = ANY($3)",
    [$dateToSet, $winkelToSet, $inParams]
);

if ($update) {
    foreach ($idsToMove as $id) {
        add_audit_log($conn, 'bon_move', 'bon', $id, [
            'from_date' => $bon['date'],
            'to_date' => $dateToSet,
            'from_winkel_id' => $bon['winkel_id'],
            'to_winkel_id' => $winkelToSet,
            'series_moved' => $moveSeries
        ]);
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Kon bon niet verplaatsen']);
}
