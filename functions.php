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
    $output = shell_exec("node scraper.js " . escapeshellarg($date) . " 2>&1");
    
    if ($output) {
        $result = json_decode(trim($output), true);
        if ($result && isset($result['success'])) {
            return $result;
        }
    }
    
    $url = "https://luckyday.nederlandseloterij.nl/uitslag?date=" . $date;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: nl-NL,nl;q=0.9,en;q=0.8'
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || empty($html)) {
        return ['success' => false, 'error' => 'Kon de pagina niet ophalen (HTTP ' . $httpCode . ')'];
    }
    
    $numbers = [];
    
    if (preg_match_all('/<span[^>]*class="[^"]*number[^"]*"[^>]*>(\d+)<\/span>/i', $html, $matches)) {
        $numbers = array_map('intval', $matches[1]);
    }
    
    if (count($numbers) < 20) {
        if (preg_match_all('/data-number="(\d+)"/i', $html, $matches)) {
            $numbers = array_map('intval', $matches[1]);
        }
    }
    
    if (count($numbers) < 20) {
        $dom = new HtmlDocument();
        $dom->load($html);
        
        $selectors = [
            '.winning-numbers .number',
            '.luckyday-getallen li',
            '.result-numbers span',
            '.numbers span',
            '[class*="number"]'
        ];
        
        foreach ($selectors as $selector) {
            $elements = $dom->find($selector);
            if ($elements && count($elements) >= 20) {
                $numbers = [];
                foreach ($elements as $el) {
                    $num = trim($el->plaintext);
                    if (is_numeric($num) && $num >= 1 && $num <= 80) {
                        $numbers[] = intval($num);
                    }
                }
                if (count($numbers) >= 20) {
                    break;
                }
            }
        }
        
        $dom->clear();
    }
    
    $numbers = array_unique($numbers);
    $numbers = array_filter($numbers, function($n) { return $n >= 1 && $n <= 80; });
    $numbers = array_slice(array_values($numbers), 0, 20);
    
    if (count($numbers) === 20) {
        sort($numbers);
        return ['success' => true, 'numbers' => $numbers, 'date' => $date];
    } elseif (count($numbers) > 0) {
        sort($numbers);
        return ['success' => true, 'numbers' => $numbers, 'date' => $date, 'warning' => 'Minder dan 20 nummers gevonden (' . count($numbers) . ')'];
    }
    
    return ['success' => false, 'error' => 'Geen uitslag gevonden voor ' . $date];
}

function getOrScrapeWinningNumbers($selected_date, $conn) {
    $numbers = getWinningNumbersFromDatabase($selected_date, $conn);
    
    if ($numbers !== null) {
        return ['source' => 'database', 'numbers' => $numbers, 'date' => $selected_date];
    }
    
    $scrapeResult = scrapeLuckyDayNumbers($selected_date);
    
    if ($scrapeResult['success']) {
        saveWinningNumbersToDatabase($selected_date, $scrapeResult['numbers'], $conn);
        return ['source' => 'scraped', 'numbers' => $scrapeResult['numbers'], 'date' => $selected_date];
    }
    
    return ['source' => 'none', 'numbers' => [], 'error' => $scrapeResult['error'] ?? 'Geen uitslag gevonden voor deze datum', 'date' => $selected_date];
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

function getGameTypeFromCount($count) {
    if ($count >= 1 && $count <= 10) {
        return $count . '-getallen';
    }
    return null;
}

function getMultipliers() {
    return [
        '1-getallen' => [1 => 4],
        '2-getallen' => [2 => 14],
        '3-getallen' => [3 => 50, 2 => 10],
        '4-getallen' => [4 => 100, 3 => 20, 2 => 2],
        '5-getallen' => [5 => 300, 4 => 80, 3 => 5],
        '6-getallen' => [6 => 1500, 5 => 100, 4 => 10, 3 => 2],
        '7-getallen' => [7 => 5000, 6 => 500, 5 => 25, 4 => 5],
        '8-getallen' => [8 => 10000, 7 => 1000, 6 => 100, 5 => 10, 4 => 2],
        '9-getallen' => [9 => 25000, 8 => 2500, 7 => 250, 6 => 25, 5 => 5],
        '10-getallen' => [10 => 100000, 9 => 5000, 8 => 500, 7 => 50, 6 => 10, 5 => 2]
    ];
}

function calculateWinnings($numbers, $winningNumbers, $bet) {
    $count = count($numbers);
    $gameType = getGameTypeFromCount($count);
    
    if (!$gameType) {
        return ['matches' => 0, 'multiplier' => 0, 'winnings' => 0];
    }
    
    $matches = count(array_intersect($numbers, $winningNumbers));
    $multipliers = getMultipliers();
    
    $multiplier = 0;
    if (isset($multipliers[$gameType][$matches])) {
        $multiplier = $multipliers[$gameType][$matches];
    }
    
    $winnings = $bet * $multiplier;
    
    return [
        'matches' => $matches,
        'multiplier' => $multiplier,
        'winnings' => $winnings,
        'game_type' => $gameType
    ];
}

function getAllPlayers($conn) {
    $result = pg_query($conn, "SELECT id, name, alias, color FROM players ORDER BY name");
    return $result ? pg_fetch_all($result) : [];
}

function getPlayerById($conn, $id) {
    $result = pg_query_params($conn, "SELECT id, name, alias, color FROM players WHERE id = $1", [$id]);
    return $result && pg_num_rows($result) > 0 ? pg_fetch_assoc($result) : null;
}

function addPlayer($conn, $name, $alias = '', $color = '#3B82F6') {
    $result = pg_query_params($conn, 
        "INSERT INTO players (name, alias, color, created_at) VALUES ($1, $2, $3, NOW()) RETURNING id",
        [$name, $alias, $color]
    );
    return $result ? pg_fetch_assoc($result)['id'] : false;
}

function getRowsByDate($conn, $date) {
    $result = pg_query_params($conn, 
        "SELECT b.*, p.name as player_name, p.alias as player_alias, p.color as player_color 
         FROM bons b 
         JOIN players p ON b.player_id = p.id 
         WHERE b.date = $1 
         ORDER BY p.name, b.created_at DESC",
        [$date]
    );
    return $result ? pg_fetch_all($result) : [];
}

function addRow($conn, $playerId, $date, $numbers, $bet, $winningNumbers) {
    $numbersArray = is_array($numbers) ? $numbers : explode(',', $numbers);
    $winningArray = is_array($winningNumbers) ? $winningNumbers : explode(',', $winningNumbers);
    
    $result = calculateWinnings($numbersArray, $winningArray, $bet);
    
    $numbersStr = implode(',', $numbersArray);
    
    $insertResult = pg_query_params($conn,
        "INSERT INTO bons (player_id, date, numbers, bet, game_type, matches, multiplier, winnings, created_at) 
         VALUES ($1, $2, $3, $4, $5, $6, $7, $8, NOW()) RETURNING id",
        [
            $playerId, 
            $date, 
            $numbersStr, 
            $bet, 
            $result['game_type'],
            $result['matches'],
            $result['multiplier'],
            $result['winnings']
        ]
    );
    
    return $insertResult ? pg_fetch_assoc($insertResult)['id'] : false;
}

function deleteRow($conn, $id) {
    return pg_query_params($conn, "DELETE FROM bons WHERE id = $1", [$id]);
}

function getDayStats($conn, $date) {
    $result = pg_query_params($conn,
        "SELECT 
            COUNT(*) as total_rows,
            COALESCE(SUM(bet), 0) as total_bet,
            COALESCE(SUM(winnings), 0) as total_winnings
         FROM bons WHERE date = $1",
        [$date]
    );
    return $result ? pg_fetch_assoc($result) : ['total_rows' => 0, 'total_bet' => 0, 'total_winnings' => 0];
}

function getPlayerStats($conn, $date) {
    $result = pg_query_params($conn,
        "SELECT 
            p.id, p.name, p.alias, p.color,
            COUNT(b.id) as total_rows,
            COALESCE(SUM(b.bet), 0) as total_bet,
            COALESCE(SUM(b.winnings), 0) as total_winnings
         FROM players p
         LEFT JOIN bons b ON p.id = b.player_id AND b.date = $1
         GROUP BY p.id, p.name, p.alias, p.color
         HAVING COUNT(b.id) > 0
         ORDER BY p.name",
        [$date]
    );
    return $result ? pg_fetch_all($result) : [];
}

function getPlayerDayStats($conn, $date) {
    $result = pg_query_params($conn,
        "SELECT 
            p.id, p.name, p.alias, p.color,
            COUNT(b.id) as total_rows,
            COALESCE(SUM(b.bet), 0) as total_bet,
            COALESCE(SUM(b.winnings), 0) as total_winnings,
            COALESCE(SUM(b.winnings), 0) - COALESCE(SUM(b.bet), 0) as saldo
         FROM players p
         JOIN bons b ON p.id = b.player_id AND b.date = $1
         GROUP BY p.id, p.name, p.alias, p.color
         ORDER BY saldo DESC",
        [$date]
    );
    return $result ? pg_fetch_all($result) : [];
}

function getWeekStats($conn, $start_date, $end_date) {
    $result = pg_query_params($conn,
        "SELECT 
            p.id, p.name, p.alias, p.color,
            COUNT(b.id) as total_rows,
            COALESCE(SUM(b.bet), 0) as total_bet,
            COALESCE(SUM(b.winnings), 0) as total_winnings,
            COALESCE(SUM(b.winnings), 0) - COALESCE(SUM(b.bet), 0) as saldo
         FROM players p
         JOIN bons b ON p.id = b.player_id AND b.date BETWEEN $1 AND $2
         GROUP BY p.id, p.name, p.alias, p.color
         ORDER BY saldo DESC",
        [$start_date, $end_date]
    );
    return $result ? pg_fetch_all($result) : [];
}

function getWeekTotals($conn, $start_date, $end_date) {
    $result = pg_query_params($conn,
        "SELECT 
            COUNT(*) as total_rows,
            COALESCE(SUM(bet), 0) as total_bet,
            COALESCE(SUM(winnings), 0) as total_winnings,
            COALESCE(SUM(winnings), 0) - COALESCE(SUM(bet), 0) as saldo
         FROM bons WHERE date BETWEEN $1 AND $2",
        [$start_date, $end_date]
    );
    return $result ? pg_fetch_assoc($result) : ['total_rows' => 0, 'total_bet' => 0, 'total_winnings' => 0, 'saldo' => 0];
}

function getPlayerHistory($conn, $player_id, $start_date = null, $end_date = null) {
    $sql = "SELECT b.*, p.name as player_name, p.alias as player_alias, p.color as player_color 
            FROM bons b 
            JOIN players p ON b.player_id = p.id 
            WHERE b.player_id = $1";
    $params = [$player_id];
    
    if ($start_date && $end_date) {
        $sql .= " AND b.date BETWEEN $2 AND $3";
        $params[] = $start_date;
        $params[] = $end_date;
    }
    
    $sql .= " ORDER BY b.date DESC, b.created_at DESC LIMIT 500";
    
    $result = pg_query_params($conn, $sql, $params);
    return $result ? pg_fetch_all($result) : [];
}

function getPlayerTotals($conn, $player_id, $start_date = null, $end_date = null) {
    $sql = "SELECT 
                COUNT(*) as total_rows,
                COALESCE(SUM(bet), 0) as total_bet,
                COALESCE(SUM(winnings), 0) as total_winnings,
                COALESCE(SUM(winnings), 0) - COALESCE(SUM(bet), 0) as saldo
            FROM bons WHERE player_id = $1";
    $params = [$player_id];
    
    if ($start_date && $end_date) {
        $sql .= " AND date BETWEEN $2 AND $3";
        $params[] = $start_date;
        $params[] = $end_date;
    }
    
    $result = pg_query_params($conn, $sql, $params);
    return $result ? pg_fetch_assoc($result) : ['total_rows' => 0, 'total_bet' => 0, 'total_winnings' => 0, 'saldo' => 0];
}

function getISOWeekRange($date) {
    $dt = new DateTime($date);
    $dt->setISODate((int)$dt->format('o'), (int)$dt->format('W'), 1);
    $start = $dt->format('Y-m-d');
    $dt->modify('+6 days');
    $end = $dt->format('Y-m-d');
    return ['start' => $start, 'end' => $end, 'week' => $dt->format('W'), 'year' => $dt->format('o')];
}

?>
