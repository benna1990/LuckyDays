<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'components/winkel_selector.php';
require_once 'php/services/MoneyCalculator.php';
require_once 'php/services/FinancialService.php';
require_once 'php/repositories/LotteryRepository.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$week_range = getISOWeekRange($selected_date);

// Winkel selectie - altijd uit sessie
$winkels = getAllWinkels($conn);
$selectedWinkel = $_SESSION['selected_winkel'] ?? null; // null = "Alles"

$activeWinkelTheme = resolveActiveWinkelTheme($winkels, $selectedWinkel);
$winkelPalette = getWinkelPalette();

// Always prepare data (even for "Alles")
$showSelectionPage = false;
$prev_week = date('Y-m-d', strtotime($week_range['start'] . ' -7 days'));
$next_week = date('Y-m-d', strtotime($week_range['start'] . ' +7 days'));

// ✅ NIEUWE REPOSITORY PATTERN: Centrale data access zonder SQL duplicatie
$lotteryRepo = new LotteryRepository($conn);

// Haal week data op via repository (geen NULL errors, geen dubbele tellingen!)
$week_stats = $lotteryRepo->getWeekStats($week_range['start'], $week_range['end'], $selectedWinkel);
$week_totals = $lotteryRepo->getWeekTotals($week_range['start'], $week_range['end'], $selectedWinkel);

// Repository garandeert altijd arrays (geen null checks meer nodig!)
// Maar voor extra zekerheid behouden we defensive programming
$week_stats = $week_stats ?: [];
$week_totals = $week_totals ?: ['total_bet' => 0, 'total_winnings' => 0, 'total_bons' => 0, 'total_rijen' => 0];

// HET HUIS LOGICA + commissie: Gebruik FinancialService voor correcte berekeningen
// Converteer database floats naar centen voor precisie
$total_bet_cents = MoneyCalculator::toCents($week_totals['total_bet'] ?? 0);
$total_winnings_cents = MoneyCalculator::toCents($week_totals['total_winnings'] ?? 0);

// Bereken financiële breakdown met Money Pattern (geen float errors!)
$financialBreakdown = FinancialService::calculateFinancialBreakdown($total_bet_cents, $total_winnings_cents);

// Voor backwards compatibility met oude templates, behoud variabelen als floats
$total_bet = $financialBreakdown['inzet_euros'];
$total_winnings = $financialBreakdown['winst_euros'];
$commission = $financialBreakdown['commission_euros'];
$house_pot = $financialBreakdown['house_pot_euros'];
$net_house = $financialBreakdown['net_house_euros'];

// HET HUIS PERSPECTIEF: spelers die WINNEN = het huis moet betalen (negatief voor het huis)
// HET HUIS PERSPECTIEF: spelers die VERLIEZEN = het huis ontvangt (positief voor het huis)
$spelers_die_wonnen = []; // Het huis moet betalen
$spelers_die_verloren = []; // Het huis ontvangt
if ($week_stats && is_array($week_stats)) {
    foreach ($week_stats as $ps) {
        // Converteer speler-saldo naar huis-saldo
        $speler_saldo = floatval($ps['saldo']); // winnings - bet vanuit speler perspectief
        $huis_saldo = -$speler_saldo; // omkeren voor het huis perspectief
        $ps['huis_saldo'] = $huis_saldo;

        if ($speler_saldo > 0) { // Speler wint
            $spelers_die_wonnen[] = $ps; // Het huis moet betalen
        } elseif ($speler_saldo < 0) { // Speler verliest
            $spelers_die_verloren[] = $ps; // Het huis ontvangt
        }
    }
    usort($spelers_die_wonnen, fn($a, $b) => floatval($b['saldo']) - floatval($a['saldo'])); // Hoogste winst eerst
    usort($spelers_die_verloren, fn($a, $b) => floatval($a['saldo']) - floatval($b['saldo'])); // Grootste verlies eerst
    $spelers_die_wonnen = array_slice($spelers_die_wonnen, 0, 10);
    $spelers_die_verloren = array_slice($spelers_die_verloren, 0, 10);
}

$huis_moet_betalen = 0; // Aan winnende spelers
$huis_ontvangt = 0; // Van verliezende spelers
if ($week_stats && is_array($week_stats)) {
    foreach ($week_stats as $ps) {
        $speler_saldo = floatval($ps['saldo']);
        $huis_saldo = -$speler_saldo; // inzet - uitbetaling
        if ($huis_saldo < 0) { // Huis verliest, moet betalen
            $huis_moet_betalen += abs($huis_saldo);
        } else { // Huis wint/quit
            $huis_ontvangt += $huis_saldo;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_csv') {
    $startDate = new DateTime($week_range['start']);
    $dayMonth = $startDate->format('d-m');

    // Als specifieke winkel geselecteerd: export alleen die winkel
    // Als "Alles": export alle winkels als aparte secties
    if ($selectedWinkel) {
        $winkel_result = db_query("SELECT naam FROM winkels WHERE id = $1", [$selectedWinkel]);
        $winkel = $winkel_result ? db_fetch_assoc($winkel_result) : null;
        $winkel_naam = $winkel ? $winkel['naam'] : 'Onbekend';
        $filename = 'weekoverzicht_' . $winkel_naam . '_week' . $week_range['week'] . '_' . $dayMonth . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Enkele winkel export
        fputcsv($output, ['Weekoverzicht Week ' . $week_range['week'] . ' ' . $week_range['year']], ';');
        fputcsv($output, ['Winkel: ' . $winkel_naam], ';');
        fputcsv($output, ['Periode: ' . $week_range['start'] . ' t/m ' . $week_range['end']], ';');
        fputcsv($output, [], ';');
        fputcsv($output, ['SPELERS OVERZICHT (HUISRESULTAAT)'], ';');
        fputcsv($output, ['Speler', 'Bonnen', 'Rijen', 'Inzet', 'Uitbetaald', 'Huisresultaat', 'Richting'], ';');

        if ($week_stats) {
            foreach ($week_stats as $ps) {
                $speler_saldo = floatval($ps['saldo']);
                $huis_saldo = floatval($ps['total_bet']) - floatval($ps['total_winnings']); // inzet - uitbetaling
                $richting = $huis_saldo > 0 ? '↑ Huis wint' : ($huis_saldo < 0 ? '↓ Huis verliest' : '→ Gelijk');
                fputcsv($output, [
                    $ps['name'],
                    $ps['total_bons'],
                    $ps['total_rijen'],
                    number_format($ps['total_bet'], 2, ',', '.'),
                    number_format($ps['total_winnings'], 2, ',', '.'),
                    number_format($huis_saldo, 2, ',', '.'),
                    $richting
                ], ';');
            }
        }

        fputcsv($output, [], ';');
        fputcsv($output, ['HET HUIS BALANS'], ';');
        fputcsv($output, ['Inzet ontvangen', number_format($total_bet, 2, ',', '.')], ';');
        fputcsv($output, ['Uitbetaald aan spelers', number_format($total_winnings, 2, ',', '.')], ';');
        fputcsv($output, ['Het Huis resultaat (bruto)', number_format($total_huis_saldo, 2, ',', '.')], ';');

        fputcsv($output, [], ';');
        fputcsv($output, ['COMMISSIE BEREKENING'], ';');
        // Gebruik FinancialService voor consistente berekeningen (zelfde als UI)
        $csvBreakdown = FinancialService::calculateFinancialBreakdown(
            MoneyCalculator::toCents($total_bet),
            MoneyCalculator::toCents($total_winnings)
        );
        fputcsv($output, ['Huispot (70%)', number_format($csvBreakdown['house_pot_euros'], 2, ',', '.')], ';');
        fputcsv($output, ['Commissie (30%)', number_format($csvBreakdown['commission_euros'], 2, ',', '.')], ';');
        fputcsv($output, ['Netto huis', number_format($csvBreakdown['net_house_euros'], 2, ',', '.')], ';');

        fclose($output);
        exit;
    } else {
        // Multi-winkel export: elke winkel als aparte sectie
        $filename = 'weekoverzicht_Alles_week' . $week_range['week'] . '_' . $dayMonth . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['Weekoverzicht Week ' . $week_range['week'] . ' ' . $week_range['year']], ';');
        fputcsv($output, ['Periode: ' . $week_range['start'] . ' t/m ' . $week_range['end']], ';');
        fputcsv($output, ['Alle winkels (gescheiden secties)'], ';');
        fputcsv($output, [], ';');

        // Loop door alle winkels
        foreach ($winkels as $winkel) {
            $winkel_id = $winkel['id'];
            $winkel_naam = $winkel['naam'];

            // Haal stats op voor deze winkel
            $query = "SELECT
                p.id as player_id,
                p.name,
                COUNT(DISTINCT b.id) as total_bons,
                COUNT(r.id) as total_rijen,
                COALESCE(SUM(r.bet), 0) as total_bet,
                COALESCE(SUM(r.winnings), 0) as total_winnings,
                COALESCE(SUM(r.winnings), 0) - COALESCE(SUM(r.bet), 0) as saldo
            FROM players p
            LEFT JOIN bons b ON p.id = b.player_id AND b.date >= $1 AND b.date <= $2 AND b.winkel_id = $3
            LEFT JOIN rijen r ON b.id = r.bon_id
            GROUP BY p.id, p.name
            HAVING COUNT(DISTINCT b.id) > 0
            ORDER BY saldo DESC";

            $result = db_query($query, [$week_range['start'], $week_range['end'], $winkel_id]);
            $winkel_stats = [];
            while ($row = db_fetch_assoc($result)) {
                $winkel_stats[] = $row;
            }

            // Bereken totalen met Money Pattern voor precisie
            $winkel_total_bet_cents = 0;
            $winkel_total_winnings_cents = 0;
            foreach ($winkel_stats as $stat) {
                $winkel_total_bet_cents = MoneyCalculator::add(
                    $winkel_total_bet_cents,
                    MoneyCalculator::toCents($stat['total_bet'])
                );
                $winkel_total_winnings_cents = MoneyCalculator::add(
                    $winkel_total_winnings_cents,
                    MoneyCalculator::toCents($stat['total_winnings'])
                );
            }

            // Bereken commissie met FinancialService (consistent met UI)
            $winkel_breakdown = FinancialService::calculateFinancialBreakdown(
                $winkel_total_bet_cents,
                $winkel_total_winnings_cents
            );

            // Voor CSV output als floats
            $winkel_total_bet = $winkel_breakdown['inzet_euros'];
            $winkel_total_winnings = $winkel_breakdown['winst_euros'];
            $winkel_huis_saldo = MoneyCalculator::toEuros(
                MoneyCalculator::subtract($winkel_total_bet_cents, $winkel_total_winnings_cents)
            );
            $winkel_commissie = [
                'bruto' => $winkel_huis_saldo,
                'commissie' => $winkel_breakdown['commission_euros'],
                'netto' => $winkel_breakdown['net_house_euros']
            ];

            // Winkel sectie
            fputcsv($output, ['TAB: ' . strtoupper($winkel_naam)], ';');
            fputcsv($output, [], ';');
            fputcsv($output, ['SPELERS OVERZICHT (HUISRESULTAAT)'], ';');
            fputcsv($output, ['Speler', 'Bonnen', 'Rijen', 'Inzet', 'Uitbetaald', 'Huisresultaat', 'Richting'], ';');

            if ($winkel_stats) {
                foreach ($winkel_stats as $ps) {
                    $huis_saldo = floatval($ps['total_bet']) - floatval($ps['total_winnings']);
                    $richting = $huis_saldo > 0 ? '↑ Huis wint' : ($huis_saldo < 0 ? '↓ Huis verliest' : '→ Gelijk');
                    fputcsv($output, [
                        $ps['name'],
                        $ps['total_bons'],
                        $ps['total_rijen'],
                        number_format($ps['total_bet'], 2, ',', '.'),
                        number_format($ps['total_winnings'], 2, ',', '.'),
                        number_format($huis_saldo, 2, ',', '.'),
                        $richting
                    ], ';');
                }
            }

            fputcsv($output, [], ';');
            fputcsv($output, ['HET HUIS BALANS'], ';');
            fputcsv($output, ['Inzet ontvangen', number_format($winkel_total_bet, 2, ',', '.')], ';');
            fputcsv($output, ['Uitbetaald aan spelers', number_format($winkel_total_winnings, 2, ',', '.')], ';');
            fputcsv($output, ['Het Huis resultaat (bruto)', number_format($winkel_huis_saldo, 2, ',', '.')], ';');

            fputcsv($output, [], ';');
            fputcsv($output, ['COMMISSIE BEREKENING'], ';');
            fputcsv($output, ['Bruto saldo', number_format($winkel_commissie['bruto'], 2, ',', '.')], ';');
            fputcsv($output, ['Commissie (30%)', '-' . number_format($winkel_commissie['commissie'], 2, ',', '.')], ';');
            fputcsv($output, ['Netto saldo', number_format($winkel_commissie['netto'], 2, ',', '.')], ';');

            fputcsv($output, [], ';');
            fputcsv($output, [], ';');
        }

        fclose($output);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekoverzicht - Lucky Day</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            overflow-y: scroll;
            min-height: 100vh;
            background-color: #F8F9FA;
        }
        .card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 12px rgba(0,0,0,0.04); }
        .container-fixed {
            max-width: 1280px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 1rem;
            padding-right: 1rem;
        }
        .winkel-btn { 
            padding: 10px 24px; 
            font-size: 14px; 
            font-weight: 500; 
            color: var(--btn-text, #6B7280); 
            background: white; 
            border: 2px solid #E5E7EB; 
            border-radius: 20px; 
            transition: all 0.2s ease; 
            cursor: pointer; 
        }
        .winkel-btn:hover { 
            background: var(--btn-hover-bg, #F9FAFB); 
            border-color: var(--btn-hover-border, #D1D5DB); 
            color: var(--btn-hover-text, #374151);
        }
        .winkel-btn.active { 
            background: var(--btn-active-bg, #2ECC710F); 
            color: var(--btn-active-text, #2ECC71); 
            border-color: var(--btn-active-border, #2ECC71); 
            font-weight: 600;
        }
        .sort-arrow {
            display: inline-flex;
            width: 10px;
            height: 10px;
            align-items: center;
            justify-content: center;
        }
        .sort-arrow::before {
            content: '▲';
            font-size: 10px;
            color: #cbd5e1;
            transition: transform 0.15s, color 0.15s;
        }
        .sort-arrow.asc::before { color: #6b7280; transform: rotate(0deg); }
        .sort-arrow.desc::before { color: #6b7280; transform: rotate(180deg); }
        
        /* Nav link underline */
        .nav-link {
            position: relative;
        }
        .nav-link.active {
            font-weight: 600;
        }
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 8px;
            right: 8px;
            height: 3px;
            background: <?= $activeWinkelTheme['accent'] ?>;
            border-radius: 3px 3px 0 0;
        }
        @media (max-width: 768px) {
            .hide-on-mobile { display: none; }
            .winkel-btn { padding: 0.4rem 1rem; font-size: 0.75rem; }
        }
    </style>
</head>
<body>
    <?php include 'components/main_nav.php'; ?>

    <?php include 'components/old_data_warning.php'; ?>

    <?php include 'components/winkel_bar.php'; ?>

    <main class="container-fixed py-4 sm:py-6">
        <!-- Normale Weekoverzicht Weergave -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <a href="?date=<?= $prev_week ?>&winkel=<?=$selectedWinkel?>" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Vorige
                </a>
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-gray-900">Week <?= $week_range['week'] ?>, <?= $week_range['year'] ?></h2>
                    <p class="text-sm text-gray-500"><?= date('d-m', strtotime($week_range['start'])) ?> t/m <?= date('d-m', strtotime($week_range['end'])) ?></p>
                </div>
                <a href="?date=<?= $next_week ?>&winkel=<?=$selectedWinkel?>" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 flex items-center gap-2">
                    Volgende
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
            <div>
                <a href="api/export_week_csv.php?week=<?= $week_range['week'] ?>&year=<?= $week_range['year'] ?>&winkel=<?= $selectedWinkel !== null ? intval($selectedWinkel) : 'all' ?>" class="btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Export CSV
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-8">
            <div class="card p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Inzet</p>
                <p class="text-lg font-semibold text-emerald-600">€<?= number_format($total_bet, 2, ',', '.') ?></p>
                <p class="text-xs text-gray-400 mt-1">Ontvangen</p>
            </div>
            <div class="card p-4">
                <p class="text-xs text-amber-700 uppercase tracking-wide mb-1">Commissie (30%)</p>
                <p class="text-lg font-semibold text-amber-600">€<?= number_format($commission, 2, ',', '.') ?></p>
                <p class="text-xs text-amber-600 mt-1">30%</p>
            </div>
            <div class="card p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Huispot (70%)</p>
                <p class="text-lg font-semibold text-emerald-600">
                    €<?= number_format($house_pot, 2, ',', '.') ?>
                </p>
                <p class="text-xs text-gray-400 mt-1">Beschikbaar voor uitbetaling</p>
            </div>
            <div class="card p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Uitbetaling</p>
                <p class="text-lg font-semibold text-red-500">€<?= number_format($total_winnings, 2, ',', '.') ?></p>
                <p class="text-xs text-gray-400 mt-1">Aan spelers</p>
            </div>
            <div class="card p-4 bg-gradient-to-br from-emerald-500 to-teal-500 text-white">
                <p class="text-xs uppercase tracking-wide mb-1 text-emerald-50">Netto huis</p>
                <p class="text-lg font-bold">
                    <?= $net_house >= 0 ? '+' : '–' ?>€<?= number_format(abs($net_house), 2, ',', '.') ?>
                </p>
                <p class="text-xs text-emerald-100 mt-1">Eindresultaat</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="card p-6">
                <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-4">Te betalen aan spelers</h3>
                <div class="text-3xl font-bold text-red-500 mb-2">–€<?= number_format($huis_moet_betalen, 2, ',', '.') ?></div>
                <p class="text-xs text-gray-500"><?= count($spelers_die_wonnen) ?> speler<?= count($spelers_die_wonnen) != 1 ? 's' : '' ?> wonnen</p>
            </div>
            <div class="card p-6">
                <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-4">Ontvangen van spelers</h3>
                <div class="text-3xl font-bold text-emerald-600 mb-2">+€<?= number_format($huis_ontvangt, 2, ',', '.') ?></div>
                <p class="text-xs text-gray-500"><?= count($spelers_die_verloren) ?> speler<?= count($spelers_die_verloren) != 1 ? 's' : '' ?> verloren</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <?php if (!empty($spelers_die_wonnen)): ?>
            <div class="card p-6">
                <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-4">Spelers die wonnen (Het Huis betaalt)</h3>
                <div class="space-y-2">
                    <?php foreach ($spelers_die_wonnen as $i => $w): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold <?= $i === 0 ? 'bg-yellow-400 text-yellow-900' : 'bg-gray-200 text-gray-600' ?>">
                                <?= $i + 1 ?>
                            </span>
                            <span class="font-medium text-gray-800"><?= htmlspecialchars($w['name']) ?></span>
                        </div>
                        <span class="font-semibold text-red-500">–€<?= number_format($w['saldo'], 2, ',', '.') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($spelers_die_verloren)): ?>
            <div class="card p-6">
                <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-4">Spelers die verloren (Het Huis ontvangt)</h3>
                <div class="space-y-2">
                    <?php foreach ($spelers_die_verloren as $i => $l): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold bg-gray-200 text-gray-600">
                                <?= $i + 1 ?>
                            </span>
                            <span class="font-medium text-gray-800"><?= htmlspecialchars($l['name']) ?></span>
                        </div>
                        <span class="font-semibold text-emerald-600">+€<?= number_format(abs($l['saldo']), 2, ',', '.') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="card p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-semibold text-gray-800">Alle spelers</h2>
            </div>
            
            <?php if (empty($week_stats) || $week_stats === false): ?>
                <p class="text-gray-400 text-center py-8">Geen data voor deze week</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-100 uppercase tracking-wide">
                                <th class="pb-3 font-medium">Speler</th>
                                <th class="pb-3 font-medium text-right cursor-pointer" onclick="sortWeekPlayers('bonnen')">Bonnen <span class="sort-arrow" data-week-sort="bonnen"></span></th>
                                <th class="pb-3 font-medium text-right cursor-pointer" onclick="sortWeekPlayers('rijen')">Rijen <span class="sort-arrow" data-week-sort="rijen"></span></th>
                                <th class="pb-3 font-medium text-right cursor-pointer" onclick="sortWeekPlayers('inzet')">Inzet <span class="sort-arrow" data-week-sort="inzet"></span></th>
                                <th class="pb-3 font-medium text-right cursor-pointer" onclick="sortWeekPlayers('uitbetaald')">Uitbetaald <span class="sort-arrow" data-week-sort="uitbetaald"></span></th>
                                <th class="pb-3 font-medium text-right cursor-pointer" onclick="sortWeekPlayers('huis')">Huisresultaat <span class="sort-arrow" data-week-sort="huis"></span></th>
                                <th class="pb-3 font-medium text-center">Richting</th>
                            </tr>
                        </thead>
                        <tbody id="week-players-table" class="divide-y divide-gray-50">
                            <?php foreach ($week_stats as $ps):
                                $huis_saldo = floatval($ps['total_bet']) - floatval($ps['total_winnings']); // Huis: inzet - uitbetaling
                            ?>
                            <tr class="hover:bg-gray-50 cursor-pointer"
                                data-naam="<?= htmlspecialchars($ps['name']) ?>"
                                data-bonnen="<?= $ps['total_bons'] ?>"
                                data-rijen="<?= $ps['total_rijen'] ?>"
                                data-inzet="<?= $ps['total_bet'] ?>"
                                data-uitbetaald="<?= $ps['total_winnings'] ?>"
                                data-huis="<?= $huis_saldo ?>"
                                onclick="openPlayerBonnenPopup(<?= $ps['id'] ?>, '<?= addslashes($ps['name']) ?>', '<?= $ps['color'] ?? '#3B82F6' ?>')">
                                <td class="py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-3 h-3 rounded-full" style="background: <?= htmlspecialchars($ps['color'] ?? '#3B82F6') ?>"></span>
                                        <span class="font-medium text-gray-800"><?= htmlspecialchars($ps['name']) ?></span>
                                    </div>
                                </td>
                                <td class="py-3 text-right text-gray-600"><?= $ps['total_bons'] ?></td>
                                <td class="py-3 text-right text-gray-600"><?= $ps['total_rijen'] ?></td>
                                <td class="py-3 text-right text-gray-900">€<?= number_format($ps['total_bet'], 2, ',', '.') ?></td>
                                <td class="py-3 text-right <?= floatval($ps['total_winnings']) > 0 ? 'text-red-500' : 'text-gray-500' ?>">
                                    <?= floatval($ps['total_winnings']) > 0 ? '€' . number_format($ps['total_winnings'], 2, ',', '.') : '—' ?>
                                </td>
                                <td class="py-3 text-right font-semibold <?= $huis_saldo > 0 ? 'text-emerald-600' : ($huis_saldo < 0 ? 'text-red-500' : 'text-gray-600') ?>">
                                    <?= $huis_saldo >= 0 ? '+' : '' ?>€<?= number_format($huis_saldo, 2, ',', '.') ?>
                                </td>
                                <td class="py-3 text-center">
                                    <?php if ($huis_saldo > 0): ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">↑ Huis wint</span>
                                    <?php elseif ($huis_saldo < 0): ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">↓ Huis verliest</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">→ Gelijk</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Per dag</h2>
            <div class="grid grid-cols-7 gap-2">
                <?php
                $current = new DateTime($week_range['start']);
                $end = new DateTime($week_range['end']);
                while ($current <= $end):
                    $dayStr = $current->format('Y-m-d');
                    // ✅ Gebruik repository voor consistent data ophalen
                    $dayStats = $lotteryRepo->getDayStats($dayStr, $selectedWinkel);
                    // HET HUIS LOGICA: inzet ontvangen - uitbetaald (met Money Pattern voor precisie)
                    $dayBetCents = MoneyCalculator::toCents($dayStats['total_bet']);
                    $dayWinCents = MoneyCalculator::toCents($dayStats['total_winnings']);
                    $dayHuisSaldoCents = MoneyCalculator::subtract($dayBetCents, $dayWinCents);
                    $dayHuisSaldo = MoneyCalculator::toEuros($dayHuisSaldoCents);
                ?>
                <a href="dashboard.php?date=<?= $dayStr ?>"
                   class="p-3 rounded-xl border <?= intval($dayStats['total_bons']) > 0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50' ?> border-gray-200 text-center transition">
                    <p class="text-xs text-gray-500"><?= getDayAndAbbreviatedMonth($dayStr) ?></p>
                    <p class="text-sm font-semibold text-gray-900 mt-1"><?= $dayStats['total_bons'] ?> bon<?= $dayStats['total_bons'] != 1 ? 'nen' : '' ?></p>
                    <?php if (floatval($dayStats['total_bet']) > 0): ?>
                        <p class="text-xs <?= $dayHuisSaldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?> mt-1">
                            <?= $dayHuisSaldo >= 0 ? '+' : '–' ?>€<?= number_format(abs($dayHuisSaldo), 0, ',', '.') ?>
                        </p>
                    <?php endif; ?>
                </a>
                <?php
                    $current->modify('+1 day');
                endwhile;
                ?>
            </div>
        </div>
    </main>

    <!-- Player Bonnen Modal (aligned with spelers pagina) -->
    <div id="player-bonnen-popup" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 modal-overlay" onclick="if(event.target === this) closePlayerBonnenPopup()">
        <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden flex flex-col m-4">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div id="player-avatar" class="w-12 h-12 rounded-full flex items-center justify-center text-white text-lg font-bold"></div>
                    <div>
                        <h3 id="player-name" class="text-xl font-bold text-gray-800"></h3>
                        <p id="bon-counter" class="text-sm text-gray-500"></p>
                    </div>
                </div>
                <button onclick="closePlayerBonnenPopup()" class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <div id="bon-content" class="flex-1 overflow-y-auto p-6">
                <div class="text-center py-12">
                    <div class="inline-block w-8 h-8 border-2 border-gray-300 border-t-emerald-500 rounded-full animate-spin"></div>
                    <p class="text-sm text-gray-500 mt-3">Laden...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bon Detail Modal (uit spelers overzicht) -->
    <div id="bonDetailModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[60] flex items-center justify-center p-4" onclick="if(event.target === this) closeBonDetail()">
        <div style="background: white; border-radius: 20px; max-width: 600px; width: 100%; max-height: 90vh; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3); display: flex; flex-direction: column;">
            <div style="padding: 20px 24px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="font-size: 18px; font-weight: 600; color: #1a1a1a;">Bon Details</h2>
                <button onclick="closeBonDetail()" style="background: #f5f5f5; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 18px; color: #666;">✕</button>
            </div>
            <div id="bonDetailBody" style="flex: 1; overflow-y: auto;"></div>
        </div>
    </div>
    
    <script>
        async function selectWinkel(winkelId) {
            try {
                const response = await fetch('api/set_winkel.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ winkel_id: winkelId })
                });
                
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert('Fout bij selecteren winkel');
                }
            } catch (e) {
                console.error('Winkel selectie fout:', e);
                location.reload();
            }
        }
        
        function hydrateWinkelButtons() {
            document.querySelectorAll('[data-role="winkel-button"]').forEach(button => {
                if (button.dataset.winkelBound === 'true') {
                    return;
                }
                button.dataset.winkelBound = 'true';
                button.addEventListener('click', () => {
                    const target = button.dataset.winkelTarget;
                    if (target === 'all') {
                        selectWinkel(null);
                        return;
                    }
                    const winkelId = parseInt(target, 10);
                    selectWinkel(Number.isNaN(winkelId) ? null : winkelId);
                });
            });
        }

        document.addEventListener('DOMContentLoaded', hydrateWinkelButtons);
        
        // Sort week players table
        let weekSortOrder = {};
        function sortWeekPlayers(column) {
            const tbody = document.querySelector('#week-players-table');
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll('tr[data-bonnen]'));
            if (rows.length === 0) return;

            weekSortOrder[column] = !weekSortOrder[column];
            const ascending = weekSortOrder[column];

            rows.sort((a, b) => {
                let aVal, bVal;
                if (column === 'naam') {
                    aVal = (a.dataset.naam || '').toLowerCase();
                    bVal = (b.dataset.naam || '').toLowerCase();
                    return ascending ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                }
                aVal = parseFloat(a.dataset[column]) || 0;
                bVal = parseFloat(b.dataset[column]) || 0;
                return ascending ? aVal - bVal : bVal - aVal;
            });

            rows.forEach(r => tbody.appendChild(r));
            updateWeekSortArrows(column, ascending);
        }

        function updateWeekSortArrows(column, ascending) {
            document.querySelectorAll('[data-week-sort]').forEach(el => {
                el.classList.remove('asc', 'desc');
                if (el.dataset.weekSort === column) {
                    el.classList.add(ascending ? 'asc' : 'desc');
                }
            });
        }

        // Player Bonnen Popup (matching spelers-overzicht)
        let currentModalPlayerId = null;
        let currentModalPlayerName = '';
        let currentModalPlayerColor = '';

        async function openPlayerBonnenPopup(playerId, playerName, playerColor) {
            currentModalPlayerId = playerId;
            currentModalPlayerName = playerName;
            currentModalPlayerColor = playerColor;

            const popup = document.getElementById('player-bonnen-popup');
            const avatar = document.getElementById('player-avatar');
            const nameEl = document.getElementById('player-name');
            const counter = document.getElementById('bon-counter');
            const body = document.getElementById('bon-content');

            avatar.style.background = playerColor;
            avatar.textContent = playerName.charAt(0).toUpperCase();
            nameEl.textContent = playerName;
            counter.textContent = 'Laden...';

            popup.classList.remove('hidden');
            popup.classList.add('flex');

            await loadPlayerBonnen();
        }

        function closePlayerBonnenPopup() {
            document.getElementById('player-bonnen-popup').classList.add('hidden');
            document.getElementById('player-bonnen-popup').classList.remove('flex');
        }

        async function loadPlayerBonnen() {
            const body = document.getElementById('bon-content');
            const counter = document.getElementById('bon-counter');
            body.innerHTML = '<div class="text-center py-12"><div class="inline-block w-8 h-8 border-2 border-gray-300 border-t-emerald-500 rounded-full animate-spin"></div><p class="mt-4 text-gray-500">Bonnen laden...</p></div>';

            try {
                const params = new URLSearchParams();
                params.append('player_id', currentModalPlayerId);
                params.append('week', '<?= $week_range['week'] ?>');
                params.append('year', '<?= $week_range['year'] ?>');

                const response = await fetch('api/get_player_bonnen.php?' + params);
                const data = await response.json();

                if (data.success) {
                    counter.textContent = `${data.bonnen.length} bonnen`;
                    renderPlayerBonnenCompact(data);
                } else {
                    body.innerHTML = '<p class="text-center text-red-500 py-8">Fout bij laden data</p>';
                }
            } catch (e) {
                console.error('Fetch error:', e);
                body.innerHTML = '<p class="text-center text-red-500 py-8">Netwerkfout bij laden van gegevens</p>';
            }
        }

        function renderPlayerBonnenCompact(data) {
            const body = document.getElementById('bon-content');
            const bonnen = data.bonnen || [];

            if (bonnen.length === 0) {
                body.innerHTML = '<p class="text-center text-gray-400 py-12">Nog geen bonnen voor deze speler</p>';
                return;
            }

            let html = `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 16px; background: #f9f9f9; border-radius: 8px; margin-bottom: 12px;">
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <div style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: 0.5px; min-width: 80px;">Datum</div>
                        <div style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: 0.5px; min-width: 80px;">Bonnummer</div>
                        <div style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Rijen</div>
                    </div>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <div style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: 0.5px; min-width: 60px; text-align: right;">Inzet</div>
                        <div style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: 0.5px; min-width: 70px; text-align: right;">Uitbetaald</div>
                        <div style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: 0.5px; min-width: 80px; text-align: center;">Huisresultaat</div>
                    </div>
                </div>
                <div class="space-y-2">
            `;

            bonnen.forEach(bon => {
                const bonBet = parseFloat(bon.total_bet);
                const bonWin = parseFloat(bon.total_winnings);
                const bonSaldo = bonBet - bonWin; // Huisresultaat
                const saldoClass = bonSaldo >= 0 ? 'positive' : 'negative';
                const saldoSymbol = bonSaldo >= 0 ? '+' : '−';

                let trekkingLabel = '';
                if (bon.trekking_info) {
                    trekkingLabel = `
                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 6px; border-radius: 4px;
                                     background: linear-gradient(135deg, ${'<?= $activeWinkelTheme['accent'] ?>'}25, ${'<?= $activeWinkelTheme['accent'] ?>'}10);
                                     color: ${'<?= $activeWinkelTheme['accent'] ?>'}; border: 1px solid ${'<?= $activeWinkelTheme['accent'] ?>'}50;
                                     font-size: 9px; font-weight: 600; white-space: nowrap;">
                            <svg style="width: 10px; height: 10px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            ${bon.trekking_info.aantal_trekkingen}x
                        </span>
                    `;
                }

                html += `
                    <div class="bon-item-compact" onclick="openBonDetail(${bon.bon_id}, '${bon.date}')">
                        <div class="bon-item-left">
                            <div class="bon-date">${formatDate(bon.date)}</div>
                            <div class="bon-bonnr">
                                Bon ${bon.bonnummer || '#' + bon.bon_id}
                                ${trekkingLabel}
                            </div>
                            <div class="bon-rijen">${bon.rijen_count} rijen</div>
                        </div>
                        <div class="bon-item-right">
                            <div class="bon-bet">€${bonBet.toFixed(2)}</div>
                            <div class="bon-win ${saldoClass}">€${bonWin.toFixed(2)}</div>
                            <div class="bon-saldo ${saldoClass}">
                                ${saldoSymbol}€${Math.abs(bonSaldo).toFixed(2)}
                            </div>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            body.innerHTML = html;
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}-${month}-${year}`;
        }

        async function openBonDetail(bonId, bonDate) {
            const modal = document.getElementById('bonDetailModal');
            const body = document.getElementById('bonDetailBody');

            modal.classList.remove('hidden');
            body.innerHTML = '<div class="text-center py-12"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500 mx-auto"></div><p class="mt-4 text-gray-500">Bon laden...</p></div>';

            try {
                const bonResponse = await fetch(`api/get_bon.php?bon_id=${bonId}`);
                const bonData = await bonResponse.json();
                const numbersResponse = await fetch(`api/get_winning_numbers.php?date=${bonDate}`);
                const numbersData = await numbersResponse.json();

                if (bonData.success && numbersData.success) {
                    renderBonDetail(bonData.bon, bonData.rijen, numbersData.numbers);
                } else {
                    const error = bonData.error || numbersData.error || 'Onbekende fout';
                    body.innerHTML = `<p class="text-center text-red-500 py-8">Fout bij laden bon: ${error}</p>`;
                }
            } catch (e) {
                console.error('Fetch error:', e);
                body.innerHTML = `<p class="text-center text-red-500 py-8">Netwerkfout: ${e.message}</p>`;
            }
        }

        function closeBonDetail() {
            document.getElementById('bonDetailModal').classList.add('hidden');
        }

        function renderBonDetail(bon, rijen, winningNumbers) {
            const body = document.getElementById('bonDetailBody');

            const totalBet = rijen.reduce((sum, r) => sum + parseFloat(r.bet), 0);
            const totalWin = rijen.reduce((sum, r) => sum + parseFloat(r.winnings), 0);
            const saldo = totalWin - totalBet;
            const saldoColor = saldo >= 0 ? '#2ECC71' : '#EF4444';

            let html = `
                <div style="padding: 20px 24px; border-bottom: 1px solid #f0f0f0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                        <h2 style="font-size: 18px; font-weight: 600; color: #1a1a1a; margin: 0;">
                            Bon ${bon.bonnummer || '#' + bon.id}
                        </h2>
                        <span style="font-size: 12px; color: #999; font-weight: 600;">${formatDate(bon.date)}</span>
                    </div>
                    <p style="font-size: 13px; color: #666; margin: 0;">
                        ${bon.player_name} • ${bon.winkel_naam || 'Geen winkel'}
                    </p>
                </div>

                <div style="padding: 24px;">
                    <div style="margin-bottom: 24px;">
                        <h3 style="font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 12px 0;">Winnende Nummers</h3>
                        <div style="background: linear-gradient(135deg, #FFF5E6 0%, #FFE6CC 100%); border: 2px solid #FFD699; border-radius: 12px; padding: 14px; display: grid; grid-template-columns: repeat(10, 1fr); gap: 5px;">
            `;

            winningNumbers.forEach(num => {
                html += `
                    <div style="background: white; border: 1px solid #FFD699; aspect-ratio: 1; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; color: #D97706;">
                        ${num}
                    </div>
                `;
            });

            html += `
                        </div>
                    </div>

                    <div>
                        <h3 style="font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 12px 0;">Rijen (${rijen.length})</h3>
            `;

            rijen.forEach((rij, index) => {
                const playerNumbers = Array.isArray(rij.numbers) ? rij.numbers : rij.numbers.split(',').map(n => parseInt(n.trim()));
                const hasWin = parseFloat(rij.winnings) > 0;

                html += `
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; transition: background 0.15s;">
                        <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                            <div style="background: #667eea; color: white; width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0;">${index + 1}</div>
                            <div style="display: flex; gap: 5px; flex-wrap: wrap; flex: 1;">
                `;

                playerNumbers.forEach(num => {
                    const isMatch = winningNumbers.includes(num);
                    html += `
                        <div style="background: ${isMatch ? '#E8F8F0' : 'white'}; border: 1.5px solid ${isMatch ? '#2ECC71' : '#e0e0e0'}; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: ${isMatch ? '#2ECC71' : '#666'}; transition: all 0.15s;">${num}</div>
                    `;
                });

                html += `
                            </div>
                        </div>
                        <div style="display: flex; gap: 20px; font-size: 13px; min-width: 160px; justify-content: flex-end;">
                            <div style="min-width: 60px; text-align: right; color: #999;">€${parseFloat(rij.bet).toFixed(2)}</div>
                            <div style="min-width: 70px; text-align: right; font-weight: 700; color: ${hasWin ? '#2ECC71' : '#999'};">€${parseFloat(rij.winnings).toFixed(2)}</div>
                        </div>
                    </div>
                `;
            });

            html += `
                    </div>
                    <div style="background: #FAFAFA; border: 1px solid #e8e8e8; border-radius: 12px; padding: 20px; margin-top: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <span style="font-size: 13px; color: #666;">Totaal Inzet</span>
                            <span style="font-size: 15px; font-weight: 600; color: #1a1a1a;">€${totalBet.toFixed(2)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <span style="font-size: 13px; color: #666;">Totaal Winst</span>
                            <span style="font-size: 15px; font-weight: 600; color: #1a1a1a;">€${totalWin.toFixed(2)}</span>
                        </div>
                        <div style="height: 1px; background: #e0e0e0; margin: 12px 0;"></div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 14px; font-weight: 600; color: #1a1a1a;">Resultaat</span>
                            <span style="font-size: 18px; font-weight: 700; color: ${saldoColor};">${saldo >= 0 ? '+' : ''}€${Math.abs(saldo).toFixed(2)}</span>
                        </div>
                    </div>
                </div>
            `;

            body.innerHTML = html;
        }

        function formatNumber(num) {
            return parseFloat(num).toFixed(2).replace('.', ',');
        }
    </script>

    <style>
        /* Compact bonnenlijst (consistent met spelers-overzicht) */
        .bon-item-compact {
            background: #f9f9f9;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
            cursor: pointer;
        }
        .bon-item-compact:hover { background: #f0f0f0; transform: translateX(4px); }
        .bon-item-left { display: flex; gap: 12px; align-items: center; }
        .bon-item-right { display: flex; gap: 12px; align-items: center; }
        .bon-date { font-size: 11px; font-weight: 600; color: #999; min-width: 80px; }
        .bon-bonnr { font-size: 13px; font-weight: 600; color: #333; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .bon-rijen { font-size: 12px; color: #666; background: white; padding: 4px 8px; border-radius: 4px; }
        .bon-bet { font-size: 13px; color: #666; min-width: 60px; text-align: right; }
        .bon-win { font-size: 14px; font-weight: 700; min-width: 70px; text-align: right; }
        .bon-win.positive { color: #34C759; }
        .bon-win.negative { color: #FF3B30; }
        .bon-saldo { font-size: 14px; font-weight: 700; padding: 6px 12px; border-radius: 6px; min-width: 80px; text-align: center; }
        .bon-saldo.positive { background: #D1FAE5; color: #059669; }
        .bon-saldo.negative { background: #FEE2E2; color: #DC2626; }
    </style>
</body>
</html>
