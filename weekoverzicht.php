<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$week_range = getISOWeekRange($selected_date);

$prev_week = date('Y-m-d', strtotime($week_range['start'] . ' -7 days'));
$next_week = date('Y-m-d', strtotime($week_range['start'] . ' +7 days'));

$week_stats = getWeekStats($conn, $week_range['start'], $week_range['end']);
$week_totals = getWeekTotals($conn, $week_range['start'], $week_range['end']);

$total_bet = floatval($week_totals['total_bet']);
$total_winnings = floatval($week_totals['total_winnings']);
$total_saldo = $total_winnings - $total_bet;

$winners = [];
$losers = [];
if ($week_stats && is_array($week_stats)) {
    foreach ($week_stats as $ps) {
        $saldo = floatval($ps['saldo']);
        if ($saldo > 0) {
            $winners[] = $ps;
        } elseif ($saldo < 0) {
            $losers[] = $ps;
        }
    }
    usort($winners, fn($a, $b) => floatval($b['saldo']) - floatval($a['saldo']));
    usort($losers, fn($a, $b) => floatval($a['saldo']) - floatval($b['saldo']));
    $winners = array_slice($winners, 0, 10);
    $losers = array_slice($losers, 0, 10);
}

$toPay = 0;
$toReceive = 0;
if ($week_stats && is_array($week_stats)) {
    foreach ($week_stats as $ps) {
        $saldo = floatval($ps['saldo']);
        if ($saldo > 0) {
            $toPay += $saldo;
        } else {
            $toReceive += abs($saldo);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="week' . $week_range['week'] . '_' . $week_range['year'] . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Weekoverzicht Week ' . $week_range['week'] . ' ' . $week_range['year']], ';');
    fputcsv($output, ['Periode: ' . $week_range['start'] . ' t/m ' . $week_range['end']], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['SPELERS OVERZICHT'], ';');
    fputcsv($output, ['Speler', 'Bonnen', 'Rijen', 'Inzet', 'Winst', 'Saldo', 'Status'], ';');
    
    if ($week_stats) {
        foreach ($week_stats as $ps) {
            $saldo = floatval($ps['saldo']);
            $status = $saldo > 0 ? 'KRIJGT' : ($saldo < 0 ? 'BETAALT' : 'GELIJK');
            fputcsv($output, [
                $ps['name'],
                $ps['total_bons'],
                $ps['total_rijen'],
                number_format($ps['total_bet'], 2, ',', '.'),
                number_format($ps['total_winnings'], 2, ',', '.'),
                number_format($saldo, 2, ',', '.'),
                $status
            ], ';');
        }
    }
    
    fputcsv($output, [], ';');
    fputcsv($output, ['BALANS'], ';');
    fputcsv($output, ['Totaal inzet', number_format($total_bet, 2, ',', '.')], ';');
    fputcsv($output, ['Totaal uitbetaald', number_format($total_winnings, 2, ',', '.')], ';');
    fputcsv($output, ['Resultaat', number_format(-$total_saldo, 2, ',', '.')], ';');
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekoverzicht - Lucky Day</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 12px rgba(0,0,0,0.04); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-6xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="dashboard.php" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                    <span class="text-2xl">🍀</span>
                    <h1 class="text-lg font-semibold text-gray-800">Weekoverzicht</h1>
                </div>
                <div class="flex items-center gap-2">
                    <a href="dashboard.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Dashboard</a>
                    <a href="beheer.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Beheer</a>
                    <a href="logout.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Uitloggen</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
        <div class="flex items-center justify-between mb-8">
            <a href="?date=<?= $prev_week ?>" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Vorige
            </a>
            
            <div class="text-center">
                <h2 class="text-2xl font-bold text-gray-900">Week <?= $week_range['week'] ?>, <?= $week_range['year'] ?></h2>
                <p class="text-sm text-gray-500"><?= date('d M', strtotime($week_range['start'])) ?> - <?= date('d M Y', strtotime($week_range['end'])) ?></p>
            </div>
            
            <a href="?date=<?= $next_week ?>" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 flex items-center gap-2">
                Volgende
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="card p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Totaal Inzet</p>
                <p class="text-2xl font-semibold text-gray-900">€<?= number_format($total_bet, 2, ',', '.') ?></p>
            </div>
            <div class="card p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Totaal Uitbetaald</p>
                <p class="text-2xl font-semibold text-emerald-600">€<?= number_format($total_winnings, 2, ',', '.') ?></p>
            </div>
            <div class="card p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Resultaat</p>
                <p class="text-2xl font-semibold <?= -$total_saldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                    <?= -$total_saldo >= 0 ? '+' : '' ?>€<?= number_format(-$total_saldo, 2, ',', '.') ?>
                </p>
                <p class="text-xs text-gray-400 mt-1"><?= -$total_saldo >= 0 ? 'Winst' : 'Verlies' ?></p>
            </div>
            <div class="card p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Activiteit</p>
                <p class="text-2xl font-semibold text-gray-900"><?= $week_totals['total_bons'] ?></p>
                <p class="text-xs text-gray-400 mt-1"><?= $week_totals['total_rijen'] ?> rijen</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="card p-6">
                <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-4">Te betalen aan spelers</h3>
                <div class="text-3xl font-bold text-red-500 mb-2">€<?= number_format($toPay, 2, ',', '.') ?></div>
                <p class="text-xs text-gray-500"><?= count($winners) ?> speler<?= count($winners) != 1 ? 's' : '' ?> met winst</p>
            </div>
            <div class="card p-6">
                <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-4">Te ontvangen van spelers</h3>
                <div class="text-3xl font-bold text-emerald-600 mb-2">€<?= number_format($toReceive, 2, ',', '.') ?></div>
                <p class="text-xs text-gray-500"><?= count($losers) ?> speler<?= count($losers) != 1 ? 's' : '' ?> met verlies</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <?php if (!empty($winners)): ?>
            <div class="card p-6">
                <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-4">Top Winnaars</h3>
                <div class="space-y-2">
                    <?php foreach ($winners as $i => $w): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold <?= $i === 0 ? 'bg-yellow-400 text-yellow-900' : 'bg-gray-200 text-gray-600' ?>">
                                <?= $i + 1 ?>
                            </span>
                            <span class="font-medium text-gray-800"><?= htmlspecialchars($w['name']) ?></span>
                        </div>
                        <span class="font-semibold text-emerald-600">+€<?= number_format($w['saldo'], 2, ',', '.') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($losers)): ?>
            <div class="card p-6">
                <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-4">Top Verliezers</h3>
                <div class="space-y-2">
                    <?php foreach ($losers as $i => $l): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold bg-gray-200 text-gray-600">
                                <?= $i + 1 ?>
                            </span>
                            <span class="font-medium text-gray-800"><?= htmlspecialchars($l['name']) ?></span>
                        </div>
                        <span class="font-semibold text-red-500">€<?= number_format($l['saldo'], 2, ',', '.') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="card p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-semibold text-gray-800">Alle spelers</h2>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="export_csv">
                    <button type="submit" class="flex items-center gap-2 px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export CSV
                    </button>
                </form>
            </div>
            
            <?php if (empty($week_stats) || $week_stats === false): ?>
                <p class="text-gray-400 text-center py-8">Geen data voor deze week</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-100 uppercase tracking-wide">
                                <th class="pb-3 font-medium">Speler</th>
                                <th class="pb-3 font-medium text-right">Bonnen</th>
                                <th class="pb-3 font-medium text-right">Rijen</th>
                                <th class="pb-3 font-medium text-right">Inzet</th>
                                <th class="pb-3 font-medium text-right">Winst</th>
                                <th class="pb-3 font-medium text-right">Saldo</th>
                                <th class="pb-3 font-medium text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($week_stats as $ps): 
                                $saldo = floatval($ps['saldo']);
                                $needsPay = $saldo < 0;
                                $getsWin = $saldo > 0;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-3 h-3 rounded-full" style="background: <?= htmlspecialchars($ps['color'] ?? '#3B82F6') ?>"></span>
                                        <span class="font-medium text-gray-800"><?= htmlspecialchars($ps['name']) ?></span>
                                    </div>
                                </td>
                                <td class="py-3 text-right text-gray-600"><?= $ps['total_bons'] ?></td>
                                <td class="py-3 text-right text-gray-600"><?= $ps['total_rijen'] ?></td>
                                <td class="py-3 text-right text-gray-900">€<?= number_format($ps['total_bet'], 2, ',', '.') ?></td>
                                <td class="py-3 text-right text-gray-900">€<?= number_format($ps['total_winnings'], 2, ',', '.') ?></td>
                                <td class="py-3 text-right font-semibold <?= $getsWin ? 'text-emerald-600' : ($needsPay ? 'text-red-500' : 'text-gray-600') ?>">
                                    <?= $saldo >= 0 ? '+' : '' ?>€<?= number_format($saldo, 2, ',', '.') ?>
                                </td>
                                <td class="py-3 text-center">
                                    <?php if ($getsWin): ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Krijgt</span>
                                    <?php elseif ($needsPay): ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">Betaalt</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Quitte</span>
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
                    $dayStats = getDayStats($conn, $dayStr);
                    $daySaldo = floatval($dayStats['total_winnings']) - floatval($dayStats['total_bet']);
                ?>
                <a href="dashboard.php?date=<?= $dayStr ?>" 
                   class="p-3 rounded-xl border <?= intval($dayStats['total_bons']) > 0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50' ?> border-gray-200 text-center transition">
                    <p class="text-xs text-gray-500"><?= getDayAndAbbreviatedMonth($dayStr) ?></p>
                    <p class="text-sm font-semibold text-gray-900 mt-1"><?= $dayStats['total_bons'] ?> bon<?= $dayStats['total_bons'] != 1 ? 'nen' : '' ?></p>
                    <?php if (floatval($dayStats['total_bet']) > 0): ?>
                        <p class="text-xs <?= $daySaldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?> mt-1">
                            <?= $daySaldo >= 0 ? '+' : '' ?>€<?= number_format($daySaldo, 0, ',', '.') ?>
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
</body>
</html>
