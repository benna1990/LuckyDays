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
    $nodePath = '/opt/homebrew/bin/node';
    $scraperPath = __DIR__ . '/scraper.js';
    
    if (!file_exists($nodePath)) {
        $nodePath = 'node';
    }
    
    $command = $nodePath . ' ' . escapeshellarg($scraperPath) . ' ' . escapeshellarg($date) . ' 2>&1';
    $output = shell_exec($command);
    
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

    // 2 maanden terug
    $startDate = (clone $today)->modify("-2 months");
    // 2 weken vooruit
    $endDate = (clone $today)->modify("+14 days");
    
    $current = clone $startDate;
    while ($current <= $endDate) {
        $days[] = $current->format('Y-m-d');
        $current->modify("+1 day");
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
    if ($count >= 1 && $count <= 7) {
        return $count . '-getallen';
    }
    return null;
}

function getMultipliers() {
    // Nieuwe matrix gebaseerd op officiële Lucky Day tabel (max 7 nummers)
    // Matrix: [gespeelde nummers][aantal goed] = multiplier
    return [
        '1-getallen' => [1 => 2],
        '2-getallen' => [2 => 8],
        '3-getallen' => [3 => 25, 2 => 1],
        '4-getallen' => [4 => 60, 3 => 8, 2 => 2],
        '5-getallen' => [5 => 300, 4 => 9, 3 => 2],
        '6-getallen' => [6 => 1300, 5 => 30, 4 => 6, 3 => 1],
        '7-getallen' => [7 => 2500, 6 => 80, 5 => 15, 4 => 2]
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

// Logging helper for bonnen
function logBonAction($conn, $bonId, $action, $details = null) {
    $user = $_SESSION['admin_username'] ?? 'admin';
    $detailsJson = $details ? json_encode($details) : null;
    @pg_query_params($conn,
        "INSERT INTO bon_logs (bon_id, action, user_name, details, created_at) VALUES ($1, $2, $3, $4, NOW())",
        [$bonId, $action, $user, $detailsJson]
    );
}

// Commission split helper: 30% winkel, 70% huispot
// ✅ GEMIGREERD naar FinancialService voor consistentie
function calculateCommissionSplit($bet, $winnings) {
    // Lazy load services
    static $servicesLoaded = false;
    if (!$servicesLoaded) {
        require_once __DIR__ . '/php/services/MoneyCalculator.php';
        require_once __DIR__ . '/php/services/FinancialService.php';
        $servicesLoaded = true;
    }

    // Gebruik nieuwe FinancialService voor consistente berekeningen
    $betCents = MoneyCalculator::toCents($bet);
    $winCents = MoneyCalculator::toCents($winnings);

    $breakdown = FinancialService::calculateCommission($betCents, $winCents);

    // Return floats voor backwards compatibility
    return [
        'commission' => $breakdown['commission_euros'],
        'house_pot' => $breakdown['house_pot_euros'],
        'net_house' => $breakdown['net_house_euros']
    ];
}

// Generate unique color for new player
function generateUniqueColor($conn) {
    // Speler-specifieke kleuren niet meer gebruiken; neutraal grijs
    return '#CBD5E0';
}

// Player functions
function getAllPlayers($conn, $winkelId = null) {
    $query = "SELECT id, name, color FROM players";
    $params = [];
    
    if ($winkelId !== null) {
        $query .= " WHERE winkel_id = $1";
        $params = [$winkelId];
    }
    
    $query .= " ORDER BY name";
    $result = pg_query_params($conn, $query, $params);
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

function addPlayer($conn, $name, $color = '#3B82F6', $winkelId = null) {
    if (playerNameExists($conn, $name)) {
        return ['success' => false, 'error' => 'Een speler met deze naam bestaat al'];
    }
    
    if ($winkelId === null && isset($_SESSION['selected_winkel'])) {
        $winkelId = $_SESSION['selected_winkel'];
    }
    if ($winkelId === null) {
        return ['success' => false, 'error' => 'Selecteer eerst een winkel'];
    }
    
    $result = pg_query_params($conn, 
        "INSERT INTO players (name, color, winkel_id, created_at) VALUES ($1, $2, $3, NOW()) RETURNING id",
        [$name, $color, $winkelId]
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
function getBonnenByDate($conn, $date, $winkelId = null) {
    // Note: player_color is now derived from winkel, not from players table
    $query = "SELECT b.*, p.name as player_name, w.naam as winkel_name,
                (SELECT COUNT(*) FROM rijen r WHERE r.bon_id = b.id) as rijen_count,
                (SELECT COALESCE(SUM(bet), 0) FROM rijen r WHERE r.bon_id = b.id) as total_bet,
                (SELECT COALESCE(SUM(winnings), 0) FROM rijen r WHERE r.bon_id = b.id) as total_winnings
         FROM bons b
         JOIN players p ON b.player_id = p.id
         LEFT JOIN winkels w ON b.winkel_id = w.id
         WHERE b.date = $1";

    $params = [$date];

    if ($winkelId !== null) {
        $query .= " AND b.winkel_id = $2";
        $params[] = $winkelId;
    }

    $query .= " AND EXISTS (SELECT 1 FROM rijen r WHERE r.bon_id = b.id)
         ORDER BY b.created_at DESC";

    $result = pg_query_params($conn, $query, $params);
    $bonnen = $result ? pg_fetch_all($result) : [];

    // Add player_color based on winkel
    if ($bonnen) {
        foreach ($bonnen as &$bon) {
            $displayInfo = getPlayerDisplayInfoByWinkel($bon['winkel_name']);
            $bon['player_color'] = $displayInfo['color'];
        }
    }

    return $bonnen;
}

function getBonById($conn, $id) {
    $result = pg_query_params($conn,
        "SELECT b.*, p.name as player_name, w.naam as winkel_naam
         FROM bons b
         JOIN players p ON b.player_id = p.id
         LEFT JOIN winkels w ON b.winkel_id = w.id
         WHERE b.id = $1",
        [$id]
    );

    $bon = $result && pg_num_rows($result) > 0 ? pg_fetch_assoc($result) : null;

    if ($bon) {
        $displayInfo = getPlayerDisplayInfoByWinkel($bon['winkel_naam']);
        $bon['player_color'] = $displayInfo['color'];
    }

    return $bon;
}

function createBon($conn, $playerId, $date, $name = null, $bonnummer = null, $winkelId = null) {
    if (!$name) {
        $maanden = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
        $day = date('d', strtotime($date));
        $month = $maanden[date('n', strtotime($date)) - 1];
        $name = "Bon $day $month";
    }
    
    // Bonnummer normaliseren: "0" of empty string wordt NULL
    if ($bonnummer === '0' || $bonnummer === '' || empty(trim($bonnummer))) {
        $bonnummer = null;
    }
    
    if ($winkelId === null && isset($_SESSION['selected_winkel'])) {
        $winkelId = $_SESSION['selected_winkel'];
    }
    if ($winkelId === null) {
        return false;
    }
    
    $result = pg_query_params($conn,
        "INSERT INTO bons (player_id, name, date, bonnummer, winkel_id, created_at) VALUES ($1, $2, $3, $4, $5, NOW()) RETURNING id",
        [$playerId, $name, $date, $bonnummer, $winkelId]
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
    // Sanitize numbers: ints, 1-80, uniek, max 7
    $numbers = array_values(array_unique(array_filter(array_map('intval', explode(',', $rij['numbers'])), function($n) {
        return $n >= 1 && $n <= 80;
    })));
    if (count($numbers) > 7) {
        $numbers = array_slice($numbers, 0, 7);
    }
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
function getDayStats($conn, $date, $winkelId = null) {
    $bonQuery = "SELECT COUNT(*) FROM bons WHERE date = $1";
    $playerQuery = "SELECT COUNT(DISTINCT player_id) FROM bons WHERE date = $1";
    $params = [$date];
    
    if ($winkelId !== null) {
        $bonQuery .= " AND winkel_id = $2";
        $playerQuery .= " AND winkel_id = $2";
        $params[] = $winkelId;
    }
    
    $query = "SELECT 
            ($bonQuery) as total_bons,
            ($playerQuery) as total_players,
            COUNT(*) as total_rijen,
            COALESCE(SUM(r.bet), 0) as total_bet,
            COALESCE(SUM(r.winnings), 0) as total_winnings
         FROM rijen r
         JOIN bons b ON r.bon_id = b.id
         WHERE b.date = $1" . ($winkelId ? " AND b.winkel_id = $2" : "");
    
    $result = pg_query_params($conn, $query, $params);
    return $result ? pg_fetch_assoc($result) : ['total_bons' => 0, 'total_players' => 0, 'total_rijen' => 0, 'total_bet' => 0, 'total_winnings' => 0];
}

function getPlayersByDate($conn, $date, $winkelId = null) {
    $query = "SELECT DISTINCT p.id, p.name, p.color
         FROM players p
         JOIN bons b ON p.id = b.player_id
         WHERE b.date = $1";
    
    $params = [$date];
    
    if ($winkelId !== null) {
        $query .= " AND b.winkel_id = $2";
        $params[] = $winkelId;
    }
    
    $query .= " ORDER BY p.name";
    
    $result = pg_query_params($conn, $query, $params);
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
         JOIN rijen r ON r.bon_id = b.id
         GROUP BY p.id, p.name, p.color
         HAVING COUNT(DISTINCT b.id) > 0 AND COUNT(r.id) > 0
         ORDER BY saldo DESC",
        [$date]
    );
    return $result ? pg_fetch_all($result) : [];
}

function getWeekStats($conn, $start_date, $end_date, $winkelId = null) {
    $query = "SELECT
            p.id, p.name, p.color,
            COUNT(DISTINCT b.id) as total_bons,
            COUNT(r.id) as total_rijen,
            COALESCE(SUM(r.bet), 0) as total_bet,
            COALESCE(SUM(r.winnings), 0) as total_winnings,
            COALESCE(SUM(r.winnings), 0) - COALESCE(SUM(r.bet), 0) as saldo
         FROM players p
         JOIN bons b ON p.id = b.player_id AND b.date BETWEEN $1 AND $2";
    
    $params = [$start_date, $end_date];
    if ($winkelId !== null) {
        $query .= " AND b.winkel_id = $3";
        $params[] = $winkelId;
    }
    
    $query .= " JOIN rijen r ON r.bon_id = b.id
         GROUP BY p.id, p.name, p.color
         HAVING COUNT(DISTINCT b.id) > 0 AND COUNT(r.id) > 0
         ORDER BY saldo DESC";
    
    $result = pg_query_params($conn, $query, $params);
    return $result ? pg_fetch_all($result) : [];
}

function getWeekTotals($conn, $start_date, $end_date, $winkelId = null) {
    $query = "SELECT 
            (SELECT COUNT(*) FROM bons WHERE date BETWEEN $1 AND $2" . ($winkelId ? " AND winkel_id = $3" : "") . ") as total_bons,
            COUNT(r.id) as total_rijen,
            COALESCE(SUM(r.bet), 0) as total_bet,
            COALESCE(SUM(r.winnings), 0) as total_winnings,
            COALESCE(SUM(r.winnings), 0) - COALESCE(SUM(r.bet), 0) as saldo
         FROM rijen r
         JOIN bons b ON r.bon_id = b.id
         WHERE b.date BETWEEN $1 AND $2";
    
    $params = [$start_date, $end_date];
    if ($winkelId !== null) {
        $query .= " AND b.winkel_id = $3";
        $params[] = $winkelId;
    }
    
    $result = pg_query_params($conn, $query, $params);
    return $result ? pg_fetch_assoc($result) : ['total_bons' => 0, 'total_rijen' => 0, 'total_bet' => 0, 'total_winnings' => 0, 'saldo' => 0];
}

function getPlayerBonnenByPeriod($conn, $playerId, $start_date, $end_date) {
    $query = "SELECT 
            b.id, b.date, b.bonnummer, b.name as bon_name,
            (SELECT COUNT(*) FROM rijen WHERE bon_id = b.id) as rijen_count,
            COALESCE(SUM(r.bet), 0) as total_bet,
            COALESCE(SUM(r.winnings), 0) as total_winnings,
            COALESCE(SUM(r.winnings), 0) - COALESCE(SUM(r.bet), 0) as saldo
         FROM bons b
         LEFT JOIN rijen r ON r.bon_id = b.id
         WHERE b.player_id = $1 AND b.date BETWEEN $2 AND $3
         GROUP BY b.id, b.date, b.bonnummer, b.name
         ORDER BY b.date DESC, b.created_at DESC";
    
    $result = pg_query_params($conn, $query, [$playerId, $start_date, $end_date]);
    return $result ? pg_fetch_all($result) : [];
}

function getISOWeekRange($date = null, $year = null, $week = null) {
    if ($year && $week) {
        // Use provided year and week
        $dt = new DateTime();
        $dt->setISODate((int)$year, (int)$week, 1);
    } else {
        // Use provided date or current date
        $dt = new DateTime($date ?? 'now');
        $dt->setISODate((int)$dt->format('o'), (int)$dt->format('W'), 1);
    }

    $start = $dt->format('Y-m-d');
    $dt->modify('+6 days');
    $end = $dt->format('Y-m-d');
    return ['start' => $start, 'end' => $end, 'week' => (int)$dt->format('W'), 'year' => (int)$dt->format('o')];
}

function generateWeekRange($currentYear = null, $currentWeek = null) {
    // Start from current week or provided week
    $now = new DateTime();
    if ($currentYear && $currentWeek) {
        $now->setISODate((int)$currentYear, (int)$currentWeek, 1);
    } else {
        $now->setISODate((int)$now->format('o'), (int)$now->format('W'), 1);
    }

    $weeks = [];

    // 12 weeks back
    $start = clone $now;
    $start->modify('-12 weeks');

    // 4 weeks forward
    $end = clone $now;
    $end->modify('+4 weeks');

    $current = clone $start;
    while ($current <= $end) {
        $year = (int)$current->format('o');
        $week = (int)$current->format('W');
        $weekStart = clone $current;
        $weekEnd = clone $current;
        $weekEnd->modify('+6 days');

        $weeks[] = [
            'year' => $year,
            'week' => $week,
            'start' => $weekStart->format('Y-m-d'),
            'end' => $weekEnd->format('Y-m-d'),
            'label' => 'W' . $week
        ];

        $current->modify('+7 days');
    }

    return $weeks;
}

// Commissie berekening (30% van huis saldo)
function calculateCommission($huisSaldo, $commissiePercentage = 30) {
    $commissie = $huisSaldo * ($commissiePercentage / 100);
    $netto = $huisSaldo - $commissie;
    return [
        'bruto' => $huisSaldo,
        'commissie' => $commissie,
        'commissie_percentage' => $commissiePercentage,
        'netto' => $netto
    ];
}

// Data cleanup helpers
function checkOldData($conn, $daysOld = 60) {
    $cutoffDate = date('Y-m-d', strtotime("-$daysOld days"));
    $result = db_query("SELECT COUNT(*) as count FROM bons WHERE date < $1", [$cutoffDate]);
    if ($result) {
        $row = db_fetch_assoc($result);
        return intval($row['count']);
    }
    return 0;
}

function deleteDataBeforeDate($conn, $beforeDate) {
    // Delete rijen first (foreign key)
    db_query("DELETE FROM rijen WHERE bon_id IN (SELECT id FROM bons WHERE date < $1)", [$beforeDate]);
    
    // Delete bons
    db_query("DELETE FROM bons WHERE date < $1", [$beforeDate]);
    
    // Delete winning numbers
    db_query("DELETE FROM winning_numbers WHERE date < $1", [$beforeDate]);
    
    return true;
}

function deleteOldData($conn, $daysOld = 60, $winkelId = null) {
    $cutoffDate = date('Y-m-d', strtotime("-$daysOld days"));
    
    // Delete rijen first (foreign key)
    $rijenQuery = "DELETE FROM rijen WHERE bon_id IN (SELECT id FROM bons WHERE date < $1";
    $params = [$cutoffDate];
    
    if ($winkelId !== null) {
        $rijenQuery .= " AND winkel_id = $2";
        $params[] = $winkelId;
    }
    
    $rijenQuery .= ")";
    db_query($rijenQuery, $params);
    
    // Delete bons
    $bonsQuery = "DELETE FROM bons WHERE date < $1";
    $params = [$cutoffDate];
    
    if ($winkelId !== null) {
        $bonsQuery .= " AND winkel_id = $2";
        $params[] = $winkelId;
    }
    
    db_query($bonsQuery, $params);
    
    // Delete winning numbers (alleen als geen winkel specifiek, anders niet)
    if ($winkelId === null) {
        db_query("DELETE FROM winning_numbers WHERE date < $1", [$cutoffDate]);
    }
    
    return true;
}

// Winkel helpers
function getAllWinkels($conn) {
    $result = db_query("SELECT * FROM winkels ORDER BY naam ASC", []);
    return $result ? db_fetch_all($result) : [];
}

function getWinkelPalette() {
    return [
        'default' => [
            'slug' => 'default',
            'naam' => 'Standaard',
            'accent' => '#2ECC71',
            'emoji' => '🏪',
        ],
        'all' => [
            'slug' => 'all',
            'naam' => 'Alles',
            'accent' => '#64748B',
            'emoji' => '📊',
        ],
        'Dapper' => [
            'slug' => 'dapper',
            'naam' => 'Dapper',
            'accent' => '#FF9F40',
            'emoji' => '🟡',
        ],
        'Banne' => [
            'slug' => 'banne',
            'naam' => 'Banne',
            'accent' => '#4A9EFF',
            'emoji' => '🔵',
        ],
        'Plein' => [
            'slug' => 'plein',
            'naam' => 'Plein',
            'accent' => '#2ECC71',
            'emoji' => '🟢',
        ],
        'Jordy' => [
            'slug' => 'jordy',
            'naam' => 'Jordy',
            'accent' => '#E74C8C',
            'emoji' => '🔴',
        ],
    ];
}

function getWinkelThemeByName($name) {
    $palette = getWinkelPalette();
    return $palette[$name] ?? $palette['default'];
}

function resolveActiveWinkelTheme(array $winkels, ?int $selectedWinkel) {
    $palette = getWinkelPalette();
    if ($selectedWinkel === null) {
        return $palette['all'];
    }
    foreach ($winkels as $winkel) {
        if ((int)$winkel['id'] === (int)$selectedWinkel) {
            $theme = $palette[$winkel['naam']] ?? $palette['default'];
            $theme['naam'] = $winkel['naam']; // Ensure actual winkel name is included
            return $theme;
        }
    }
    return $palette['all'];
}

/**
 * Get player display info (color and letter) based on their winkel
 * Returns array with 'color' and 'letter'
 */
function getPlayerDisplayInfo($conn, $playerId) {
    // Get player's primary winkel (the winkel they have most bonnen in)
    $query = "SELECT w.naam, w.id, COUNT(b.id) as bon_count
              FROM winkels w
              JOIN bons b ON b.winkel_id = w.id
              WHERE b.player_id = $1
              GROUP BY w.id, w.naam
              ORDER BY bon_count DESC
              LIMIT 1";

    $result = db_query($query, [$playerId]);
    $winkel = $result ? db_fetch_assoc($result) : null;

    if ($winkel) {
        $palette = getWinkelPalette();
        $theme = $palette[$winkel['naam']] ?? $palette['default'];
        return [
            'color' => $theme['accent'],
            'letter' => strtoupper(mb_substr($winkel['naam'], 0, 1)),
            'winkel_naam' => $winkel['naam']
        ];
    }

    // Fallback to default
    $palette = getWinkelPalette();
    return [
        'color' => $palette['default']['accent'],
        'letter' => '?',
        'winkel_naam' => 'Onbekend'
    ];
}

/**
 * Get player display info by winkel name directly
 */
function getPlayerDisplayInfoByWinkel($winkelNaam) {
    $palette = getWinkelPalette();
    $naam = $winkelNaam ?? '';
    $theme = $palette[$naam] ?? $palette['default'];
    $letter = $naam ? strtoupper(mb_substr($naam, 0, 1)) : '?';
    return [
        'color' => $theme['accent'],
        'letter' => $letter,
        'winkel_naam' => $naam ?: 'Onbekend'
    ];
}

?>
