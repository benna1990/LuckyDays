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
    
    return ['success' => false, 'error' => 'Scraper niet beschikbaar'];
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

// Player functions
function getAllPlayers($conn) {
    $result = pg_query($conn, "SELECT id, name, color FROM players ORDER BY name");
    return $result ? pg_fetch_all($result) : [];
}

function getPlayerById($conn, $id) {
    $result = pg_query_params($conn, "SELECT id, name, color FROM players WHERE id = $1", [$id]);
    return $result && pg_num_rows($result) > 0 ? pg_fetch_assoc($result) : null;
}

function getPlayerByName($conn, $name) {
    $result = pg_query_params($conn, "SELECT id, name, color FROM players WHERE LOWER(name) = LOWER($1)", [$name]);
    return $result && pg_num_rows($result) > 0 ? pg_fetch_assoc($result) : null;
}

function playerNameExists($conn, $name, $excludeId = null) {
    if ($excludeId) {
        $result = pg_query_params($conn, "SELECT id FROM players WHERE LOWER(name) = LOWER($1) AND id != $2", [$name, $excludeId]);
    } else {
        $result = pg_query_params($conn, "SELECT id FROM players WHERE LOWER(name) = LOWER($1)", [$name]);
    }
    return $result && pg_num_rows($result) > 0;
}

function addPlayer($conn, $name, $color = '#3B82F6') {
    if (playerNameExists($conn, $name)) {
        return ['success' => false, 'error' => 'Een speler met deze naam bestaat al'];
    }
    
    $result = pg_query_params($conn, 
        "INSERT INTO players (name, color, created_at) VALUES ($1, $2, NOW()) RETURNING id",
        [$name, $color]
    );
    
    if ($result) {
        return ['success' => true, 'id' => pg_fetch_assoc($result)['id']];
    }
    return ['success' => false, 'error' => 'Kon speler niet toevoegen'];
}

function updatePlayer($conn, $id, $name, $color) {
    if (playerNameExists($conn, $name, $id)) {
        return ['success' => false, 'error' => 'Een speler met deze naam bestaat al'];
    }
    
    $result = pg_query_params($conn, 
        "UPDATE players SET name = $1, color = $2 WHERE id = $3",
        [$name, $color, $id]
    );
    return ['success' => $result ? true : false];
}

function deletePlayer($conn, $id) {
    $result = pg_query_params($conn, "DELETE FROM players WHERE id = $1", [$id]);
    return $result ? true : false;
}

// Bon functions (new structure)
function getBonnenByDate($conn, $date) {
    $result = pg_query_params($conn, 
        "SELECT b.*, p.name as player_name, p.color as player_color,
                (SELECT COUNT(*) FROM rijen r WHERE r.bon_id = b.id) as rijen_count,
                (SELECT COALESCE(SUM(bet), 0) FROM rijen r WHERE r.bon_id = b.id) as total_bet,
                (SELECT COALESCE(SUM(winnings), 0) FROM rijen r WHERE r.bon_id = b.id) as total_winnings
         FROM bons b 
         JOIN players p ON b.player_id = p.id 
         WHERE b.date = $1 
         ORDER BY b.created_at DESC",
        [$date]
    );
    return $result ? pg_fetch_all($result) : [];
}

function getBonById($conn, $id) {
    $result = pg_query_params($conn, 
        "SELECT b.*, p.name as player_name, p.color as player_color 
         FROM bons b 
         JOIN players p ON b.player_id = p.id 
         WHERE b.id = $1",
        [$id]
    );
    return $result && pg_num_rows($result) > 0 ? pg_fetch_assoc($result) : null;
}

function createBon($conn, $playerId, $date, $name = null) {
    if (!$name) {
        $maanden = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
        $day = date('d', strtotime($date));
        $month = $maanden[date('n', strtotime($date)) - 1];
        $name = "Bon $day $month";
    }
    
    $result = pg_query_params($conn,
        "INSERT INTO bons (player_id, name, date, created_at) VALUES ($1, $2, $3, NOW()) RETURNING id",
        [$playerId, $name, $date]
    );
    
    return $result ? pg_fetch_assoc($result)['id'] : false;
}

function updateBonName($conn, $id, $name) {
    return pg_query_params($conn, "UPDATE bons SET name = $1 WHERE id = $2", [$name, $id]);
}

function deleteBon($conn, $id) {
    return pg_query_params($conn, "DELETE FROM bons WHERE id = $1", [$id]);
}

// Rij functions
function getRijenByBonId($conn, $bonId) {
    $result = pg_query_params($conn, 
        "SELECT * FROM rijen WHERE bon_id = $1 ORDER BY created_at ASC",
        [$bonId]
    );
    return $result ? pg_fetch_all($result) : [];
}

function addRij($conn, $bonId, $numbers, $bet, $winningNumbers = []) {
    $numbersArray = is_array($numbers) ? $numbers : explode(',', $numbers);
    $winningArray = is_array($winningNumbers) ? $winningNumbers : explode(',', $winningNumbers);
    
    $result = calculateWinnings($numbersArray, $winningArray, $bet);
    
    $numbersStr = implode(',', $numbersArray);
    
    $insertResult = pg_query_params($conn,
        "INSERT INTO rijen (bon_id, numbers, bet, game_type, matches, multiplier, winnings, created_at) 
         VALUES ($1, $2, $3, $4, $5, $6, $7, NOW()) RETURNING id",
        [
            $bonId, 
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

function deleteRij($conn, $id) {
    return pg_query_params($conn, "DELETE FROM rijen WHERE id = $1", [$id]);
}

function recalculateRijWinnings($conn, $rijId, $winningNumbers) {
    $result = pg_query_params($conn, "SELECT * FROM rijen WHERE id = $1", [$rijId]);
    if (!$result || pg_num_rows($result) == 0) return false;
    
    $rij = pg_fetch_assoc($result);
    $numbers = explode(',', $rij['numbers']);
    $winningArray = is_array($winningNumbers) ? $winningNumbers : explode(',', $winningNumbers);
    
    $calcResult = calculateWinnings($numbers, $winningArray, $rij['bet']);
    
    pg_query_params($conn,
        "UPDATE rijen SET matches = $1, multiplier = $2, winnings = $3, game_type = $4 WHERE id = $5",
        [$calcResult['matches'], $calcResult['multiplier'], $calcResult['winnings'], $calcResult['game_type'], $rijId]
    );
    
    return $calcResult;
}

function recalculateAllRijenForDate($conn, $date, $winningNumbers) {
    $bonnen = getBonnenByDate($conn, $date);
    if (!$bonnen) return;
    
    foreach ($bonnen as $bon) {
        $rijen = getRijenByBonId($conn, $bon['id']);
        if ($rijen) {
            foreach ($rijen as $rij) {
                recalculateRijWinnings($conn, $rij['id'], $winningNumbers);
            }
        }
    }
}

// Stats functions
function getDayStats($conn, $date) {
    $result = pg_query_params($conn,
        "SELECT 
            (SELECT COUNT(*) FROM bons WHERE date = $1) as total_bons,
            COUNT(*) as total_rijen,
            COALESCE(SUM(r.bet), 0) as total_bet,
            COALESCE(SUM(r.winnings), 0) as total_winnings
         FROM rijen r
         JOIN bons b ON r.bon_id = b.id
         WHERE b.date = $1",
        [$date]
    );
    return $result ? pg_fetch_assoc($result) : ['total_bons' => 0, 'total_rijen' => 0, 'total_bet' => 0, 'total_winnings' => 0];
}

function getPlayersByDate($conn, $date) {
    $result = pg_query_params($conn,
        "SELECT DISTINCT p.id, p.name, p.color
         FROM players p
         JOIN bons b ON p.id = b.player_id
         WHERE b.date = $1
         ORDER BY p.name",
        [$date]
    );
    return $result ? pg_fetch_all($result) : [];
}

function getPlayerDayStats($conn, $date) {
    $result = pg_query_params($conn,
        "SELECT 
            p.id, p.name, p.color,
            COUNT(DISTINCT b.id) as total_bons,
            COUNT(r.id) as total_rijen,
            COALESCE(SUM(r.bet), 0) as total_bet,
            COALESCE(SUM(r.winnings), 0) as total_winnings,
            COALESCE(SUM(r.winnings), 0) - COALESCE(SUM(r.bet), 0) as saldo
         FROM players p
         JOIN bons b ON p.id = b.player_id AND b.date = $1
         LEFT JOIN rijen r ON r.bon_id = b.id
         GROUP BY p.id, p.name, p.color
         ORDER BY saldo DESC",
        [$date]
    );
    return $result ? pg_fetch_all($result) : [];
}

function getWeekStats($conn, $start_date, $end_date) {
    $result = pg_query_params($conn,
        "SELECT 
            p.id, p.name, p.color,
            COUNT(DISTINCT b.id) as total_bons,
            COUNT(r.id) as total_rijen,
            COALESCE(SUM(r.bet), 0) as total_bet,
            COALESCE(SUM(r.winnings), 0) as total_winnings,
            COALESCE(SUM(r.winnings), 0) - COALESCE(SUM(r.bet), 0) as saldo
         FROM players p
         JOIN bons b ON p.id = b.player_id AND b.date BETWEEN $1 AND $2
         LEFT JOIN rijen r ON r.bon_id = b.id
         GROUP BY p.id, p.name, p.color
         ORDER BY saldo DESC",
        [$start_date, $end_date]
    );
    return $result ? pg_fetch_all($result) : [];
}

function getWeekTotals($conn, $start_date, $end_date) {
    $result = pg_query_params($conn,
        "SELECT 
            (SELECT COUNT(*) FROM bons WHERE date BETWEEN $1 AND $2) as total_bons,
            COUNT(r.id) as total_rijen,
            COALESCE(SUM(r.bet), 0) as total_bet,
            COALESCE(SUM(r.winnings), 0) as total_winnings,
            COALESCE(SUM(r.winnings), 0) - COALESCE(SUM(r.bet), 0) as saldo
         FROM rijen r
         JOIN bons b ON r.bon_id = b.id
         WHERE b.date BETWEEN $1 AND $2",
        [$start_date, $end_date]
    );
    return $result ? pg_fetch_assoc($result) : ['total_bons' => 0, 'total_rijen' => 0, 'total_bet' => 0, 'total_winnings' => 0, 'saldo' => 0];
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
