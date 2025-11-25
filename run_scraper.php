<?php
// Verbind met de database
require_once 'config.php';
require_once 'functions.php';

// Controleer of een datum is meegegeven
if (isset($_GET['date'])) {
    $selected_date = $_GET['date'];

    $command = "node scrape.js " . escapeshellarg($selected_date) . " 2>&1";
    
    $output = shell_exec($command);

    if ($output === null) {
        echo "Het shell_exec commando werkt niet of is geblokkeerd.";
    } else {
        echo "Output van de scraper: " . nl2br(htmlspecialchars($output));
    }
} else {
    echo "Geen datum meegegeven.<br>";
}
?>