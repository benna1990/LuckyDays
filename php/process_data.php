<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $names = $_POST['name'];
    $dates = $_POST['date'];
    $bets = $_POST['bet'];

    $playedNumbers = [];
    for ($i = 1; $i <= 10; $i++) {
        $playedNumbers[$i] = $_POST['played_num' . $i];
    }

    for ($i = 0; $i < count($names); $i++) {
        $name = htmlspecialchars($names[$i], ENT_QUOTES, 'UTF-8');
        $date = $dates[$i];
        $bet = $bets[$i];

        $played = [];
        for ($j = 1; $j <= 10; $j++) {
            $played[] = htmlspecialchars($playedNumbers[$j][$i], ENT_QUOTES, 'UTF-8');
        }
        $playedString = implode(',', $played);

        if (empty($name) || empty($date) || !is_numeric($bet) || empty($playedString)) {
            echo "Ongeldige invoer, rij $i niet opgeslagen.";
            continue;
        }

        $result = pg_query_params($conn, 
            "INSERT INTO players (name, date, bet, played) VALUES ($1, $2, $3, $4)",
            [$name, $date, $bet, $playedString]
        );

        if ($result) {
            echo "Rij $i succesvol toegevoegd.";
        } else {
            echo "Fout bij het toevoegen van rij $i: " . pg_last_error($conn);
        }
    }

    header('Location: ../dashboard.php');
}
?>
