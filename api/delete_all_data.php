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

try {
    // Delete all rijen first (foreign key)
    db_query("DELETE FROM rijen", []);
    
    // Delete all bons
    db_query("DELETE FROM bons", []);
    
    // Delete all winning numbers
    db_query("DELETE FROM winning_numbers", []);
    
    // Optional: Reset player statistics (keep players but reset their data)
    // db_query("UPDATE players SET created_at = NOW()", []);
    
    echo json_encode([
        'success' => true,
        'message' => 'Alle data is verwijderd. Gebruikers en spelers zijn behouden.'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>



