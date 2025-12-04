<?php
session_start();

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit();
}

// Check role: admin or beheerder only
$userRole = $_SESSION['role'] ?? 'user';
if (!in_array($userRole, ['admin', 'beheerder'])) {
    echo json_encode(['success' => false, 'error' => 'Geen toegang']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$password = $input['password'] ?? '';
$username = $_SESSION['username'] ?? '';

// Verify password
if (empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Wachtwoord is verplicht']);
    exit();
}

$userQuery = db_query("SELECT id, password FROM admins WHERE username = $1", [$username]);
$user = db_fetch_assoc($userQuery);

if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'error' => 'Onjuist wachtwoord']);
    exit();
}

// Get parameters
$days = isset($input['days']) ? intval($input['days']) : null;
$before_date = $input['before_date'] ?? null;

if ($days === null && $before_date === null) {
    echo json_encode(['success' => false, 'error' => 'Geef dagen of datum op']);
    exit();
}

if ($days !== null && $days < 30) {
    echo json_encode(['success' => false, 'error' => 'Minimum 30 dagen']);
    exit();
}

try {
    if ($before_date !== null) {
        // Delete data before specific date
        $result = deleteDataBeforeDate($conn, $before_date);
    } else {
        // Delete data older than X days
        $result = deleteOldData($conn, $days, null);
    }
    
    if ($result) {
        $message = $before_date 
            ? "Data voor $before_date is verwijderd" 
            : "Data ouder dan $days dagen is verwijderd";
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Kon data niet verwijderen']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

