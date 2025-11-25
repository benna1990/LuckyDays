<?php

require_once __DIR__ . '/php/simple_html_dom.php';

use simplehtmldom\HtmlDocument;

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
    $url = "https://www.loten.nl/luckyday/";
    
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
    
    $dom = new HtmlDocument();
    $dom->load($html);
    
    $targetDate = new DateTime($date);
    $targetDay = (int)$targetDate->format('j');
    $targetMonth = strtolower($targetDate->format('F'));
    
    $dutchMonths = [
        'january' => 'januari', 'february' => 'februari', 'march' => 'maart',
        'april' => 'april', 'may' => 'mei', 'june' => 'juni',
        'july' => 'juli', 'august' => 'augustus', 'september' => 'september',
        'october' => 'oktober', 'november' => 'november', 'december' => 'december'
    ];
    $targetMonthDutch = $dutchMonths[$targetMonth] ?? $targetMonth;
    
    $rows = $dom->find('tr');
    
    foreach ($rows as $row) {
        $dateCell = $row->find('td', 0);
        if ($dateCell) {
            $dateText = strtolower(trim($dateCell->plaintext));
            
            if (strpos($dateText, (string)$targetDay) !== false && strpos($dateText, $targetMonthDutch) !== false) {
                $numbersCell = $row->find('ul.luckyday-getallen', 0);
                if ($numbersCell) {
                    $numbers = [];
                    $lis = $numbersCell->find('li');
                    foreach ($lis as $li) {
                        $num = trim($li->plaintext);
                        if (is_numeric($num)) {
                            $numbers[] = $num;
                        }
                    }
                    
                    $dom->clear();
                    
                    if (count($numbers) === 20) {
                        return ['success' => true, 'numbers' => $numbers];
                    } elseif (count($numbers) > 0) {
                        return ['success' => true, 'numbers' => $numbers, 'warning' => 'Minder dan 20 nummers gevonden'];
                    }
                }
            }
        }
    }
    
    $firstNumbers = $dom->find('ul.luckyday-getallen', 0);
    if ($firstNumbers) {
        $numbers = [];
        $lis = $firstNumbers->find('li');
        foreach ($lis as $li) {
            $num = trim($li->plaintext);
            if (is_numeric($num)) {
                $numbers[] = $num;
            }
        }
        
        $dom->clear();
        
        if (count($numbers) === 20) {
            return ['success' => true, 'numbers' => $numbers, 'warning' => 'Exacte datum niet gevonden, meest recente uitslag gebruikt'];
        }
    }
    
    $dom->clear();
    
    return ['success' => false, 'error' => 'Geen winnende nummers gevonden voor ' . $date];
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
