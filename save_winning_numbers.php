<?php
require_once 'config.php';

if (!isset($_POST['date']) || !isset($_POST['numbers'])) {
    echo 'Datum of nummers ontbreken.';
    exit;
}

$date = $_POST['date'];
$numbers = $_POST['numbers'];

// Controleer of de datum en nummers goed zijn ontvangen
if (empty($date) || empty($numbers)) {
    echo "Datum of nummers ontbreken.";
    exit;
}

// Sla de winnende nummers op in de database
$stmt = $conn->prepare("INSERT INTO winning_numbers (date, numbers) VALUES (?, ?) ON DUPLICATE KEY UPDATE numbers = VALUES(numbers)");
$stmt->bind_param('ss', $date, $numbers);

if ($stmt->execute()) {
    echo "Winnende nummers succesvol opgeslagen!";
} else {
    echo "Fout bij het opslaan van de winnende nummers.";
}

$stmt->close();
?>