<?php
// Verbind met de database
require_once 'config.php';
require_once 'functions.php';

// Controleer of een datum is meegegeven
if (isset($_GET['date'])) {
    $selected_date = $_GET['date'];

    // Gebruik het volledige pad naar Node.js
    $command = "/opt/homebrew/bin/node scrape.js " . escapeshellarg($selected_date);
    
    // Debug: toon het volledige commando
    echo "Uitvoeren van het commando: " . $command . "<br>";

    // Voer het commando uit en vang de output op
    $output = shell_exec($command);

    // Controleer de output van het commando
    if ($output === null) {
        echo "Het shell_exec commando werkt niet of is geblokkeerd.<br>";
    } else {
        echo "Output van de scraper: " . $output . "<br>";
    }
} else {
    echo "Geen datum meegegeven.<br>";
}
?>