<?php

// Haal de winnende nummers op uit de database
function getWinningNumbersFromDatabase($selected_date, $conn) {
    $stmt = $conn->prepare("SELECT numbers FROM winning_numbers WHERE date = ?");
    $stmt->bind_param('s', $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return explode(',', $row['numbers']);
    }
    return null;
}

// Sla de winnende nummers op in de database
function saveWinningNumbersToDatabase($selected_date, $winning_numbers, $conn) {
    $numbers = implode(',', $winning_numbers);

    // Controleer of de datum al bestaat
    $stmt = $conn->prepare("SELECT * FROM winning_numbers WHERE date = ?");
    $stmt->bind_param('s', $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update de winnende nummers voor de geselecteerde datum
        $stmt = $conn->prepare("UPDATE winning_numbers SET numbers = ? WHERE date = ?");
        $stmt->bind_param('ss', $numbers, $selected_date);
    } else {
        // Voeg nieuwe winnende nummers toe
        $stmt = $conn->prepare("INSERT INTO winning_numbers (date, numbers) VALUES (?, ?)");
        $stmt->bind_param('ss', $selected_date, $numbers);
    }

    $stmt->execute(); // Vergeet niet om de statement daadwerkelijk uit te voeren
}

// Functie om een reeks datums te genereren
function generateDateRange($selected_date) {
    $today = new DateTime($selected_date);
    $days = [];

    // Voeg de voorgaande 6 dagen toe
    for ($i = 6; $i > 0; $i--) {
        $days[] = (clone $today)->modify("-$i day")->format('Y-m-d');
    }

    // Voeg de geselecteerde datum toe (midden)
    $days[] = $today->format('Y-m-d');

    // Voeg de volgende 6 dagen toe
    for ($i = 1; $i <= 6; $i++) {
        $days[] = (clone $today)->modify("+$i day")->format('Y-m-d');
    }

    return $days;
}

// Functie om de dag en afgekorte maand in het Nederlands te krijgen
function getDayAndAbbreviatedMonth($date) {
    $dagen = ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'];
    $maanden = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];

    $dayOfWeek = $dagen[date('w', strtotime($date))]; // Dag van de week
    $day = date('d', strtotime($date)); // Dag van de maand
    $month = $maanden[date('n', strtotime($date)) - 1]; // Maand

    return "$dayOfWeek $day $month";
}

?>