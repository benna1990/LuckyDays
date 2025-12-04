<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

// Get player_id from query
$playerId = $_GET['player_id'] ?? null;
$selectedWeek = $_GET['week'] ?? null;
$selectedYear = $_GET['year'] ?? null;

if (!$playerId || !is_numeric($playerId)) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige player_id']);
    exit;
}

try {
    // Calculate week range if week is selected
    $weekStartDate = null;
    $weekEndDate = null;
    if ($selectedWeek && $selectedYear) {
        require_once '../functions.php';
        $weekRange = getISOWeekRange(null, $selectedYear, $selectedWeek);
        $weekStartDate = $weekRange['start'];
        $weekEndDate = $weekRange['end'];
    }

    // Live recalculatie: herbereken alle rijen van deze speler (en weekfilter) zodra winnende nummers bekend zijn
    $recalcQuery = "SELECT id, date FROM bons WHERE player_id = $1";
    $recalcParams = [$playerId];
    if ($weekStartDate && $weekEndDate) {
        $recalcQuery .= " AND DATE(date) BETWEEN $2 AND $3";
        $recalcParams[] = $weekStartDate;
        $recalcParams[] = $weekEndDate;
    }
    $recalcResult = db_query($recalcQuery, $recalcParams);
    $recalcBonnen = db_fetch_all($recalcResult) ?: [];
    $seenDates = [];
    foreach ($recalcBonnen as $bonRow) {
        $dateKey = $bonRow['date'];
        if (isset($seenDates[$dateKey])) continue;
        $winningNums = getWinningNumbersFromDatabase($dateKey, $conn);
        if ($winningNums && is_array($winningNums) && count($winningNums) > 0) {
            recalculateAllRijenForDate($conn, $dateKey, $winningNums);
            $seenDates[$dateKey] = true;
        }
    }

    // Get player info with aggregate stats
    $playerQuery = "
        SELECT
            p.id,
            p.name,
            p.color,
            p.winkel_id,
            w.naam as winkel_naam,
            COALESCE(SUM(r.bet), 0) as total_bet,
            COALESCE(SUM(r.winnings), 0) as total_winnings,
            COUNT(DISTINCT b.id) as total_bons
        FROM players p
        LEFT JOIN winkels w ON p.winkel_id = w.id
        LEFT JOIN bons b ON p.id = b.player_id";

    // Add week filter if selected
    $playerParams = [$playerId];
    if ($weekStartDate && $weekEndDate) {
        $playerQuery .= " AND DATE(b.date) BETWEEN $2 AND $3";
        $playerParams[] = $weekStartDate;
        $playerParams[] = $weekEndDate;
    }

    $playerQuery .= " LEFT JOIN rijen r ON b.id = r.bon_id
        WHERE p.id = $1
        GROUP BY p.id, p.name, p.color, p.winkel_id, w.naam
    ";

    $playerResult = db_query($playerQuery, $playerParams);
    $player = db_fetch_assoc($playerResult);
    
    if (!$player) {
        echo json_encode(['success' => false, 'error' => 'Speler niet gevonden']);
        exit;
    }
    
    // Get all bonnen for this player with details
    $bonnenQuery = "
        SELECT
            b.id as bon_id,
            b.date,
            b.bonnummer,
            b.name as bon_name,
            b.player_id,
            w.naam as winkel_naam,
            COUNT(r.id) as rijen_count,
            COALESCE(SUM(r.bet), 0) as total_bet,
            COALESCE(SUM(r.winnings), 0) as total_winnings,
            (COALESCE(SUM(r.winnings), 0) - COALESCE(SUM(r.bet), 0)) as saldo
        FROM bons b
        LEFT JOIN winkels w ON b.winkel_id = w.id
        LEFT JOIN rijen r ON b.id = r.bon_id
        WHERE b.player_id = $1";

    $bonnenParams = [$playerId];
    if ($weekStartDate && $weekEndDate) {
        $bonnenQuery .= " AND DATE(b.date) BETWEEN $2 AND $3";
        $bonnenParams[] = $weekStartDate;
        $bonnenParams[] = $weekEndDate;
    }

    $bonnenQuery .= " GROUP BY b.id, b.date, b.bonnummer, b.name, b.player_id, w.naam
        ORDER BY b.date DESC, b.created_at DESC
    ";

    $bonnenResult = db_query($bonnenQuery, $bonnenParams);
    $bonnen = db_fetch_all($bonnenResult) ?: [];

    // Add trekking info for each bon
    foreach ($bonnen as &$bon) {
        $bon['trekking_info'] = null;

        // Get trekking_groep_id from full bon data
        $bonFullQuery = "SELECT trekking_groep_id FROM bons WHERE id = $1";
        $bonFullResult = db_query($bonFullQuery, [$bon['bon_id']]);
        $bonFull = db_fetch_assoc($bonFullResult);

        if ($bonFull && !empty($bonFull['trekking_groep_id'])) {
            $groepQuery = "
                SELECT
                    COUNT(*) as aantal_trekkingen,
                    MIN(date) as start_datum,
                    MAX(date) as eind_datum
                FROM bons
                WHERE trekking_groep_id = $1
            ";
            $groepResult = db_query($groepQuery, [$bonFull['trekking_groep_id']]);
            $groepData = db_fetch_assoc($groepResult);

            if ($groepData && $groepData['aantal_trekkingen'] > 1) {
                $bon['trekking_info'] = [
                    'aantal_trekkingen' => (int)$groepData['aantal_trekkingen'],
                    'start_datum' => $groepData['start_datum'],
                    'eind_datum' => $groepData['eind_datum']
                ];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'player' => $player,
        'bonnen' => $bonnen
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching player bonnen: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database fout: ' . $e->getMessage()]);
}
