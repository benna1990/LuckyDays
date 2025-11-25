<?php
require_once 'config.php';

if (!isset($_POST['date']) || !isset($_POST['numbers'])) {
    echo 'Datum of nummers ontbreken.';
    exit;
}

$date = $_POST['date'];
$numbers = $_POST['numbers'];

if (empty($date) || empty($numbers)) {
    echo "Datum of nummers ontbreken.";
    exit;
}

$result = pg_query_params($conn, 
    "INSERT INTO winning_numbers (date, numbers) VALUES ($1, $2) 
     ON CONFLICT (date) DO UPDATE SET numbers = EXCLUDED.numbers",
    [$date, $numbers]
);

if ($result) {
    echo "Winnende nummers succesvol opgeslagen!";
} else {
    echo "Fout bij het opslaan van de winnende nummers: " . pg_last_error($conn);
}
?>
