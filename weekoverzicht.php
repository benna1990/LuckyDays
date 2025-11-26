<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'Gebruiker';
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$week_range = getISOWeekRange($selected_date);

$prev_week = date('Y-m-d', strtotime($week_range['start'] . ' -7 days'));
$next_week = date('Y-m-d', strtotime($week_range['start'] . ' +7 days'));

$week_stats = getWeekStats($conn, $week_range['start'], $week_range['end']);
$week_totals = getWeekTotals($conn, $week_range['start'], $week_range['end']);

$total_saldo = floatval($week_totals['saldo']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="week' . $week_range['week'] . '_' . $week_range['year'] . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Weekoverzicht Week ' . $week_range['week'] . ' ' . $week_range['year']], ';');
    fputcsv($output, ['Periode: ' . $week_range['start'] . ' t/m ' . $week_range['end']], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Speler', 'Speler #', 'Rijen', 'Inzet', 'Winst', 'Saldo', 'Status'], ';');
    
    foreach ($week_stats as $ps) {
        $saldo = floatval($ps['saldo']);
        $status = $saldo > 0 ? 'KRIJGT' : ($saldo < 0 ? 'BETAALT' : 'GELIJK');
        fputcsv($output, [
            $ps['name'],
            $ps['id'],
            $ps['total_rows'],
            number_format($ps['total_bet'], 2, ',', '.'),
            number_format($ps['total_winnings'], 2, ',', '.'),
            number_format(abs($saldo), 2, ',', '.'),
            $status
        ], ';');
    }
    
    fputcsv($output, [], ';');
    fputcsv($output, ['TOTALEN', '', $week_totals['total_rows'], 
                      number_format($week_totals['total_bet'], 2, ',', '.'),
                      number_format($week_totals['total_winnings'], 2, ',', '.'),
                      number_format(abs($total_saldo), 2, ',', '.'),
                      $total_saldo <= 0 ? 'CASINO WINST' : 'CASINO VERLIES'], ';');
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekoverzicht - LuckyDays Casino</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <span class="text-xl font-semibold text-gray-900">LuckyDays <span class="text-xs text-gray-400">Casino</span></span>
                    <div class="hidden md:flex space-x-6">
                        <a href="dashboard.php" class="text-gray-500 hover:text-gray-900">Dashboard</a>
                        <a href="weekoverzicht.php" class="text-gray-900 font-medium">Week</a>
                        <a href="spelers.php" class="text-gray-500 hover:text-gray-900">Spelers</a>
                        <a href="balans.php" class="text-gray-500 hover:text-gray-900">Balans</a>
                        <?php if ($isAdmin): ?>
                        <a href="spellen.php" class="text-gray-500 hover:text-gray-900">Spellen</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center">
                    <span class="text-sm text-gray-500 mr-4"><?= htmlspecialchars($username) ?></span>
                    <a href="logout.php" class="text-sm text-gray-500 hover:text-gray-900">Uitloggen</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Week Navigation -->
        <div class="flex items-center justify-between mb-8">
            <a href="?date=<?= $prev_week ?>" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Vorige week
            </a>
            
            <div class="text-center">
                <h1 class="text-2xl font-bold text-gray-900">Week <?= $week_range['week'] ?>, <?= $week_range['year'] ?></h1>
                <p class="text-sm text-gray-500"><?= date('d M', strtotime($week_range['start'])) ?> - <?= date('d M Y', strtotime($week_range['end'])) ?></p>
            </div>
            
            <a href="?date=<?= $next_week ?>" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 flex items-center gap-2">
                Volgende week
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <!-- Week Totals -->
        <div class="grid grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl p-5 border border-gray-100">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Rijen</p>
                <p class="text-2xl font-semibold text-gray-900"><?= $week_totals['total_rows'] ?></p>
            </div>
            <div class="bg-white rounded-xl p-5 border border-gray-100">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Totaal Inzet</p>
                <p class="text-2xl font-semibold text-gray-900">&euro;<?= number_format($week_totals['total_bet'], 2, ',', '.') ?></p>
            </div>
            <div class="bg-white rounded-xl p-5 border border-gray-100">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Totaal Uitbetalen</p>
                <p class="text-2xl font-semibold text-green-600">&euro;<?= number_format($week_totals['total_winnings'], 2, ',', '.') ?></p>
            </div>
            <div class="bg-white rounded-xl p-5 border border-gray-100 <?= $total_saldo <= 0 ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' ?>">
                <p class="text-xs <?= $total_saldo <= 0 ? 'text-green-600' : 'text-red-600' ?> uppercase tracking-wide mb-1">Casino Saldo</p>
                <p class="text-2xl font-semibold <?= $total_saldo <= 0 ? 'text-green-700' : 'text-red-700' ?>">
                    <?= $total_saldo <= 0 ? '+' : '-' ?>&euro;<?= number_format(abs($total_saldo), 2, ',', '.') ?>
                </p>
                <p class="text-xs <?= $total_saldo <= 0 ? 'text-green-500' : 'text-red-500' ?>">
                    <?= $total_saldo <= 0 ? 'Winst voor casino' : 'Verlies voor casino' ?>
                </p>
            </div>
        </div>

        <!-- Per Player Stats -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-semibold text-gray-900">Spelers Afrekening</h2>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="export_csv">
                    <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export CSV
                    </button>
                </form>
            </div>
            
            <?php if (empty($week_stats)): ?>
                <p class="text-gray-500 text-center py-8">Geen data voor deze week</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-100 uppercase tracking-wide">
                                <th class="pb-3 font-medium">Speler</th>
                                <th class="pb-3 font-medium text-right">Rijen</th>
                                <th class="pb-3 font-medium text-right">Inzet</th>
                                <th class="pb-3 font-medium text-right">Winst</th>
                                <th class="pb-3 font-medium text-right">Saldo</th>
                                <th class="pb-3 font-medium text-center">Status</th>
                                <th class="pb-3 font-medium"></th>
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
                                        <span class="font-medium text-gray-900"><?= htmlspecialchars($ps['name']) ?></span>
                                        <span class="text-gray-400 text-xs">#<?= $ps['id'] ?></span>
                                    </div>
                                </td>
                                <td class="py-3 text-right text-gray-600"><?= $ps['total_rows'] ?></td>
                                <td class="py-3 text-right text-gray-900">&euro;<?= number_format($ps['total_bet'], 2, ',', '.') ?></td>
                                <td class="py-3 text-right text-gray-900">&euro;<?= number_format($ps['total_winnings'], 2, ',', '.') ?></td>
                                <td class="py-3 text-right font-semibold <?= $getsWin ? 'text-green-600' : ($needsPay ? 'text-red-600' : 'text-gray-600') ?>">
                                    &euro;<?= number_format(abs($saldo), 2, ',', '.') ?>
                                </td>
                                <td class="py-3 text-center">
                                    <?php if ($getsWin): ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">KRIJGT</span>
                                    <?php elseif ($needsPay): ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">BETAALT</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">GELIJK</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 text-right">
                                    <a href="speler_detail.php?id=<?= $ps['id'] ?>&start=<?= $week_range['start'] ?>&end=<?= $week_range['end'] ?>" 
                                       class="text-blue-600 hover:text-blue-700 text-sm">Details</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Day Breakdown -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mt-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Per Dag</h2>
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
                   class="p-3 rounded-lg border <?= floatval($dayStats['total_bet']) > 0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50' ?> border-gray-200 text-center">
                    <p class="text-xs text-gray-500"><?= getDayAndAbbreviatedMonth($dayStr) ?></p>
                    <p class="text-sm font-semibold text-gray-900 mt-1"><?= $dayStats['total_rows'] ?> rij</p>
                    <?php if (floatval($dayStats['total_bet']) > 0): ?>
                        <p class="text-xs <?= $daySaldo <= 0 ? 'text-green-600' : 'text-red-600' ?> mt-1">
                            <?= $daySaldo <= 0 ? '+' : '-' ?>&euro;<?= number_format(abs($daySaldo), 0, ',', '.') ?>
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
