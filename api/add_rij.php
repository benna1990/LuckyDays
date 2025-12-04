<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../config.php';
require_once '../functions.php';
require_once '../audit_log.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit();
}

// Support both JSON and form data
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $bonId = intval($input['bon_id'] ?? 0);
    $numbers = $input['numbers'] ?? '';
    $bet = floatval($input['bet'] ?? 1.00);
} else {
    $bonId = intval($_POST['bon_id'] ?? 0);
    $numbers = $_POST['numbers'] ?? '';
    $bet = floatval($_POST['bet'] ?? 1.00);
}

if ($bonId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige bon']);
    exit();
}

$bon = getBonById($conn, $bonId);
if (!$bon) {
    echo json_encode(['success' => false, 'error' => 'Bon niet gevonden']);
    exit();
}

$numbersArray = array_map('intval', array_filter(explode(',', $numbers)));
if (count($numbersArray) < 1 || count($numbersArray) > 7) {
    echo json_encode(['success' => false, 'error' => 'Voer 1-7 nummers in']);
    exit();
}

foreach ($numbersArray as $num) {
    if ($num < 1 || $num > 80) {
        echo json_encode(['success' => false, 'error' => 'Nummers moeten tussen 1 en 80 zijn']);
        exit();
    }
}

if ($bet < 0.50) {
    echo json_encode(['success' => false, 'error' => 'Minimum inzet is €0.50']);
    exit();
}

$winningNumbers = getWinningNumbersFromDatabase($bon['date'], $conn) ?? [];

// Max 10 rijen check (tenzij expliciet overflow toegestaan)
$rijCountRes = pg_query_params($conn, "SELECT COUNT(*) FROM rijen WHERE bon_id = $1", [$bonId]);
$currentCount = $rijCountRes ? intval(pg_fetch_result($rijCountRes, 0, 0)) : 0;
$allowOverflow = filter_var($input['allow_overflow'] ?? $_POST['allow_overflow'] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($currentCount >= 10 && !$allowOverflow) {
    echo json_encode(['success' => false, 'error' => 'MAX_RIJEN_BEREIKT']);
    exit();
}

$rijId = addRij($conn, $bonId, $numbersArray, $bet, $winningNumbers);

if ($rijId) {
    $rij = pg_fetch_assoc(pg_query_params($conn, "SELECT * FROM rijen WHERE id = $1", [$rijId]));
    add_audit_log($conn, 'bon_row_create', 'bon', $bonId, [
        'rij_id' => $rijId,
        'numbers' => $numbersArray,
        'bet' => $bet,
        'winnings' => floatval($rij['winnings'] ?? 0),
        'matches' => intval($rij['matches'] ?? 0)
    ]);
    echo json_encode([
        'success' => true,
        'rij_id' => $rijId,
        'id' => $rijId,
        'rij' => $rij,
        'winning_numbers' => $winningNumbers,
        'winnings' => floatval($rij['winnings'] ?? 0),
        'matches' => intval($rij['matches'] ?? 0),
        'multiplier' => floatval($rij['multiplier'] ?? 0)
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Kon rij niet opslaan']);
}
?>
