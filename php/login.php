<?php
session_start();
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Controleer op lege invoervelden
    if (empty($username) || empty($password)) {
        echo "Gebruikersnaam en wachtwoord zijn verplicht.";
        exit;
    }

    // Zoek de gebruiker in de database
    $stmt = $conn->prepare('SELECT * FROM admins WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if ($admin && password_verify($password, $admin['password'])) {
        // Sessie veilig opstarten
        session_regenerate_id(true);
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $admin['role']; // Rol van admin ophalen voor rechtenbeheer

        header('Location: ../dashboard.php');
        exit;
    } else {
        echo "Ongeldige gebruikersnaam of wachtwoord";
    }
}
?>