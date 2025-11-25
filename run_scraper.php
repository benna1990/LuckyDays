<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_GET['date'])) {
    echo "Geen datum meegegeven.";
    exit;
}

$selected_date = $_GET['date'];
$force = isset($_GET['force']) && $_GET['force'] == '1';

if ($force) {
    $result = scrapeLuckyDayNumbers($selected_date);
    
    if ($result['success']) {
        saveWinningNumbersToDatabase($selected_date, $result['numbers'], $conn);
        echo "Winnende nummers succesvol opgehaald en opgeslagen!\n";
        echo "Nummers: " . implode(', ', $result['numbers']);
    } else {
        echo "Fout bij scrapen: " . $result['error'];
    }
} else {
    $dbNumbers = getWinningNumbersFromDatabase($selected_date, $conn);
    
    if ($dbNumbers !== null) {
        echo "Data bestaat al in database voor " . $selected_date;
    } else {
        $result = scrapeLuckyDayNumbers($selected_date);
        
        if ($result['success']) {
            saveWinningNumbersToDatabase($selected_date, $result['numbers'], $conn);
            echo "Winnende nummers succesvol opgehaald en opgeslagen!\n";
            echo "Nummers: " . implode(', ', $result['numbers']);
        } else {
            echo "Fout bij scrapen: " . $result['error'];
        }
    }
}
?>
