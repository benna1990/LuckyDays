<?php
require_once 'config.php'; // Database verbinding
require_once 'functions.php'; // Laad de functies

// Controleer of de benodigde data binnenkomt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['date']) && isset($_POST['numbers'])) {
    $selected_date = $_POST['date'];
    $winning_numbers = $_POST['numbers'];

    // Sla de winnende nummers op in de database
    saveWinningNumbersToDatabase($selected_date, $winning_numbers, $db);
    echo 'Winnende nummers succesvol opgeslagen!';
} else {
    echo 'Fout: geen geldige data ontvangen.';
}