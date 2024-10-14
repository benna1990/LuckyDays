<?php
require_once 'config.php'; // Verbind met de database

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Haal de arrays met gegevens op uit het formulier
    $names = $_POST['name'];
    $dates = $_POST['date'];
    $bets = $_POST['bet'];

    // Voor de 10 individuele gespeelde nummers
    $playedNumbers = [];
    for ($i = 1; $i <= 10; $i++) {
        $playedNumbers[$i] = $_POST['played_num' . $i];
    }

    // Loop door de arrays en sla elke rij op in de database
    for ($i = 0; $i < count($names); $i++) {
        $name = htmlspecialchars($names[$i], ENT_QUOTES, 'UTF-8');
        $date = $dates[$i];
        $bet = $bets[$i];

        // Combineer de gespeelde nummers van de huidige speler
        $played = [];
        for ($j = 1; $j <= 10; $j++) {
            $played[] = htmlspecialchars($playedNumbers[$j][$i], ENT_QUOTES, 'UTF-8');
        }
        $playedString = implode(',', $played);  // Maak een string van de nummers (bijv. "4,10,14,...")

        // Inputvalidatie
        if (empty($name) || empty($date) || !is_numeric($bet) || empty($playedString)) {
            echo "Ongeldige invoer, rij $i niet opgeslagen.";
            continue; // Ga naar de volgende invoer zonder deze op te slaan
        }

        // Sla de gegevens op in de database
        $stmt = $conn->prepare("INSERT INTO players (name, date, bet, played) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssis', $name, $date, $bet, $playedString);

        if ($stmt->execute()) {
            echo "Rij $i succesvol toegevoegd.";
        } else {
            echo "Fout bij het toevoegen van rij $i: " . $conn->error;
        }
        $stmt->close();
    }

    // Redirect terug naar het dashboard
    header('Location: ../dashboard.php');
}