<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo 'Niet ingelogd';
    exit;
}

$week = isset($_GET['week']) ? intval($_GET['week']) : intval(date('W'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('o'));
$winkelParam = $_GET['winkel'] ?? 'all';
$selectedWinkel = ($winkelParam === 'all') ? null : intval($winkelParam);

$weekRange = getISOWeekRange(null, $year, $week);
$winkels = getAllWinkels($conn);

function fetchWeekStatsForWinkel($conn, $start, $end, $winkelId = null) {
    $query = "SELECT
                p.id,
                p.name,
                p.color,
                COUNT(DISTINCT b.id) as total_bons,
                COUNT(r.id) as total_rijen,
                COALESCE(SUM(r.bet), 0) as total_bet,
                COALESCE(SUM(r.winnings), 0) as total_winnings
              FROM players p
              LEFT JOIN bons b ON p.id = b.player_id AND DATE(b.date) BETWEEN $1 AND $2";
    $params = [$start, $end];
    if ($winkelId !== null) {
        $query .= " AND b.winkel_id = $3";
        $params[] = $winkelId;
    }
    $query .= " LEFT JOIN rijen r ON b.id = r.bon_id
               WHERE b.id IS NOT NULL AND r.id IS NOT NULL
               GROUP BY p.id, p.name, p.color
               HAVING COUNT(DISTINCT b.id) > 0 AND COUNT(r.id) > 0
               ORDER BY (COALESCE(SUM(r.bet), 0) - COALESCE(SUM(r.winnings), 0)) DESC";

    $res = db_query($query, $params);
    return db_fetch_all($res) ?: [];
}

header('Content-Type: text/csv; charset=utf-8');
$filename = 'weekoverzicht_week' . $week . '_' . $weekRange['start'] . '_tot_' . $weekRange['end'] . '.csv';
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');
// BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

function writeWinkelSection($output, $weekRange, $week, $year, $winkelNaam, $stats) {
    $totalBet = array_sum(array_map(fn($p) => floatval($p['total_bet']), $stats));
    $totalWin = array_sum(array_map(fn($p) => floatval($p['total_winnings']), $stats));
    $commission = $totalBet * 0.30;
    $housePot = $totalBet * 0.70;
    $netHouse = $housePot - $totalWin;

    fputcsv($output, ['Weekoverzicht Week ' . $week . ' ' . $year], ';');
    fputcsv($output, ['Periode: ' . $weekRange['start'] . ' t/m ' . $weekRange['end']], ';');
    fputcsv($output, ['Winkel: ' . $winkelNaam], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Totale inzet', number_format($totalBet, 2, ',', '.')], ';');
    fputcsv($output, ['Commissie (30%)', number_format($commission, 2, ',', '.')], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Huispot (70%)', number_format($housePot, 2, ',', '.')], ';');
    fputcsv($output, ['Uitbetaling', number_format($totalWin, 2, ',', '.')], ';');
    fputcsv($output, ['Netto huis', number_format($netHouse, 2, ',', '.')], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Speler', 'Bonnen', 'Rijen', 'Inzet', 'Uitbetaald', 'Huisresultaat', 'Richting'], ';');

    foreach ($stats as $ps) {
        $huis = floatval($ps['total_bet']) - floatval($ps['total_winnings']);
        $richting = $huis > 0 ? 'Huis wint' : ($huis < 0 ? 'Huis verliest' : 'Gelijk');
        fputcsv($output, [
            $ps['name'],
            $ps['total_bons'],
            $ps['total_rijen'],
            number_format($ps['total_bet'], 2, ',', '.'),
            number_format($ps['total_winnings'], 2, ',', '.'),
            ($huis >= 0 ? '+' : '-') . number_format(abs($huis), 2, ',', '.'),
            $richting
        ], ';');
    }
    fputcsv($output, [], ';');
    fputcsv($output, [], ';');
}

if ($selectedWinkel !== null) {
    $stats = fetchWeekStatsForWinkel($conn, $weekRange['start'], $weekRange['end'], $selectedWinkel);
    $winkelNaam = 'Winkel ' . $selectedWinkel;
    foreach ($winkels as $w) {
        if (intval($w['id']) === $selectedWinkel) { $winkelNaam = $w['naam']; break; }
    }
    writeWinkelSection($output, $weekRange, $week, $year, $winkelNaam, $stats);
} else {
    foreach ($winkels as $w) {
        $stats = fetchWeekStatsForWinkel($conn, $weekRange['start'], $weekRange['end'], $w['id']);
        if (empty($stats)) continue;
        writeWinkelSection($output, $weekRange, $week, $year, $w['naam'], $stats);
    }
}

fclose($output);
exit;
