<?php
require_once '../config.php'; // Verbind met de database

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? null;

    if ($date) {
        // Haal de winnende nummers op uit de database voor de opgegeven datum
        $stmt = $conn->prepare("SELECT numbers FROM winning_numbers WHERE date = ?");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $stmt->bind_result($numbers);
        $stmt->fetch();
        
        if ($numbers) {
            echo json_encode(explode(',', $numbers));
        } else {
            echo json_encode(['error' => 'Geen winnende nummers gevonden voor deze datum.']);
        }

        $stmt->close();
        $conn->close();
    } else {
        echo json_encode(['error' => 'Geen datum opgegeven.']);
    }
}
?>