<?php
session_start();
require_once '../config.php'; // Database connection

if (isset($_POST['change_password'])) {
    $username = $_POST['username'];
    $new_password = $_POST['new_password'];

    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE username = ?");
        if ($stmt === false) {
            die('Prepare-fout: ' . $conn->error);
        }
        $stmt->bind_param('ss', $hashed_password, $username);

        if ($stmt->execute()) {
            echo "Wachtwoord succesvol gewijzigd!";
            // You can redirect to a confirmation page or back to the dashboard
        } else {
            echo "Er ging iets mis bij het wijzigen van het wachtwoord: " . $stmt->error;
        }
    } else {
        echo "Vul een nieuw wachtwoord in.";
    }
}
?>