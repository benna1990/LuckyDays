<?php

require_once __DIR__ . '/php/simple_html_dom.php';

function getWinningNumbersFromDatabase($selected_date, $conn) {
    $result = pg_query_params($conn, "SELECT numbers FROM winning_numbers WHERE date = $1", [$selected_date]);
    
    if ($result && pg_num_rows($result) > 0) {
        $row = pg_fetch_assoc($result);
        return explode(',', $row['numbers']);
    }
    return null;
}

function saveWinningNumbersToDatabase($selected_date, $winning_numbers, $conn) {
    if (is_array($winning_numbers)) {
        $numbers = implode(',', $winning_numbers);
    } else {
        $numbers = $winning_numbers;
    }

    $check_result = pg_query_params($conn, "SELECT * FROM winning_numbers WHERE date = $1", [$selected_date]);
    
    if ($check_result && pg_num_rows($check_result) > 0) {
        pg_query_params($conn, "UPDATE winning_numbers SET numbers = $1 WHERE date = $2", [$numbers, $selected_date]);
    } else {
        pg_query_params($conn, "INSERT INTO winning_numbers (date, numbers) VALUES ($1, $2)", [$selected_date, $numbers]);
    }
    
    return true;
}

function scrapeLuckyDayNumbers($date) {
    $url = "https://luckyday.nederlandseloterij.nl/uitslag?date=" . urlencode($date);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || empty($html)) {
        return ['success' => false, 'error' => 'Kon de pagina niet ophalen (HTTP ' . $httpCode . ')'];
    }
    
    $dom = new \simplehtmldom_1_5\simple_html_dom();
    $dom->load($html);
    
    $numbers = [];
    
    $elements = $dom->find('ul.base-ticket-numbers li span');
    
    if (count($elements) > 0) {
        foreach ($elements as $element) {
            $num = trim($element->plaintext);
            if (is_numeric($num)) {
                $numbers[] = $num;
            }
        }
    }
    
    if (count($numbers) === 0) {
        $elements = $dom->find('.winning-numbers span, .number-ball, .lottery-number');
        foreach ($elements as $element) {
            $num = trim($element->plaintext);
            if (is_numeric($num) && $num >= 1 && $num <= 48) {
                $numbers[] = $num;
            }
        }
    }
    
    $dom->clear();
    
    if (count($numbers) >= 20) {
        return ['success' => true, 'numbers' => array_slice($numbers, 0, 20)];
    } elseif (count($numbers) > 0) {
        return ['success' => true, 'numbers' => $numbers, 'warning' => 'Minder dan 20 nummers gevonden'];
    }
    
    return ['success' => false, 'error' => 'Geen winnende nummers gevonden voor deze datum'];
}

function getOrScrapeWinningNumbers($selected_date, $conn) {
    $numbers = getWinningNumbersFromDatabase($selected_date, $conn);
    
    if ($numbers !== null) {
        return ['source' => 'database', 'numbers' => $numbers];
    }
    
    $scrapeResult = scrapeLuckyDayNumbers($selected_date);
    
    if ($scrapeResult['success']) {
        saveWinningNumbersToDatabase($selected_date, $scrapeResult['numbers'], $conn);
        return ['source' => 'scraped', 'numbers' => $scrapeResult['numbers']];
    }
    
    return ['source' => 'none', 'numbers' => array_fill(0, 20, '-'), 'error' => $scrapeResult['error'] ?? 'Onbekende fout'];
}

function generateDateRange($selected_date) {
    $today = new DateTime($selected_date);
    $days = [];

    for ($i = 6; $i > 0; $i--) {
        $days[] = (clone $today)->modify("-$i day")->format('Y-m-d');
    }

    $days[] = $today->format('Y-m-d');

    for ($i = 1; $i <= 6; $i++) {
        $days[] = (clone $today)->modify("+$i day")->format('Y-m-d');
    }

    return $days;
}

function getDayAndAbbreviatedMonth($date) {
    $dagen = ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'];
    $maanden = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];

    $dayOfWeek = $dagen[date('w', strtotime($date))];
    $day = date('d', strtotime($date));
    $month = $maanden[date('n', strtotime($date)) - 1];

    return "$dayOfWeek $day $month";
}

?>
