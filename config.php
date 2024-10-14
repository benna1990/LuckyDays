<?php
$host = 'localhost'; // Meestal 'localhost' voor XAMPP
$db = 'luckyday_db';  // Naam van je database
$user = 'root';       // Standaard XAMPP MySQL-gebruiker
$pass = '';           // Leeg wachtwoord voor XAMPP

// Maak verbinding met de database
$conn = new mysqli($host, $user, $pass, $db);

// Controleer de verbinding
if ($conn->connect_error) {
    die("Verbinding mislukt: " . $conn->connect_error);
}
?>