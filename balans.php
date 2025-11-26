<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Get date range from query parameters or use defaults (last 30 days)
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Get all data for the date range
$query = "
    SELECT
        DATE(b.date) as day,
        COUNT(DISTINCT b.id) as total_bons,
        COUNT(r.id) as total_rijen,
        COALESCE(SUM(r.bet), 0) as total_bet,
        COALESCE(SUM(r.winnings), 0) as total_winnings
    FROM bons b
    LEFT JOIN rijen r ON b.id = r.bon_id
    WHERE DATE(b.date) BETWEEN $1 AND $2
    GROUP BY DATE(b.date)
    ORDER BY day DESC
";

$result = db_query($query, [$startDate, $endDate]);
$dailyData = db_fetch_all($result) ?: [];

// Calculate totals
$totalBet = 0;
$totalWinnings = 0;
$totalBons = 0;
$totalRijen = 0;
$bestDay = null;
$worstDay = null;
$bestDaySaldo = PHP_INT_MIN;
$worstDaySaldo = PHP_INT_MAX;

foreach ($dailyData as $day) {
    $totalBet += floatval($day['total_bet']);
    $totalWinnings += floatval($day['total_winnings']);
    $totalBons += intval($day['total_bons']);
    $totalRijen += intval($day['total_rijen']);

    $daySaldo = floatval($day['total_bet']) - floatval($day['total_winnings']);
    if ($daySaldo > $bestDaySaldo) {
        $bestDaySaldo = $daySaldo;
        $bestDay = $day;
    }
    if ($daySaldo < $worstDaySaldo) {
        $worstDaySaldo = $daySaldo;
        $worstDay = $day;
    }
}

$totalHuisSaldo = $totalBet - $totalWinnings;
$avgPerDay = count($dailyData) > 0 ? $totalHuisSaldo / count($dailyData) : 0;
$avgBetPerBon = $totalBons > 0 ? $totalBet / $totalBons : 0;

// Get player stats for the period (only players with actual bons/rijen)
$playerQuery = "
    SELECT
        p.name,
        p.color,
        COUNT(DISTINCT b.id) as total_bons,
        COUNT(r.id) as total_rijen,
        COALESCE(SUM(r.bet), 0) as total_bet,
        COALESCE(SUM(r.winnings), 0) as total_winnings
    FROM players p
    LEFT JOIN bons b ON p.id = b.player_id AND DATE(b.date) BETWEEN $1 AND $2
    LEFT JOIN rijen r ON b.id = r.bon_id
    WHERE b.id IS NOT NULL AND r.id IS NOT NULL
    GROUP BY p.id, p.name, p.color
    HAVING COUNT(DISTINCT b.id) > 0 AND COUNT(r.id) > 0
    ORDER BY (COALESCE(SUM(r.bet), 0) - COALESCE(SUM(r.winnings), 0)) DESC
";

$playerResult = db_query($playerQuery, [$startDate, $endDate]);
$playerData = db_fetch_all($playerResult) ?: [];

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balans Overzicht - Lucky Day</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 12px rgba(0,0,0,0.04); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="dashboard.php" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                    <a href="dashboard.php" class="flex items-center gap-3 hover:opacity-80 transition">
                        <span class="text-2xl">🍀</span>
                        <h1 class="text-lg font-semibold text-gray-800">Lucky Day</h1>
                    </a>
                </div>
                <div class="flex items-center gap-2">
                    <a href="dashboard.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Dashboard</a>
                    <a href="weekoverzicht.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Weekoverzicht</a>
                    <a href="balans.php" class="px-3 py-1.5 text-sm text-emerald-600 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition font-medium">Balans</a>
                    <a href="beheer.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Beheer</a>
                    <a href="logout.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Uitloggen</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Balans Overzicht</h2>

            <!-- Date Range Selector -->
            <div class="card p-6 mb-6">
                <form method="GET" class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Startdatum</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>"
                               class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Einddatum</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>"
                               class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition font-medium">
                            Bijwerken
                        </button>
                        <a href="?start_date=<?= date('Y-m-d', strtotime('-30 days')) ?>&end_date=<?= date('Y-m-d') ?>"
                           class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium">
                            Laatste 30 dagen
                        </a>
                        <a href="?start_date=<?= date('Y-m-d', strtotime('-7 days')) ?>&end_date=<?= date('Y-m-d') ?>"
                           class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium">
                            Laatste 7 dagen
                        </a>
                    </div>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="card p-6">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Totaal Resultaat (Het Huis)</p>
                    <p class="text-3xl font-bold <?= $totalHuisSaldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                        <?= $totalHuisSaldo >= 0 ? '+' : '–' ?>€<?= number_format(abs($totalHuisSaldo), 2, ',', '.') ?>
                    </p>
                    <p class="text-xs text-gray-400 mt-1"><?= $totalHuisSaldo >= 0 ? 'Ontvangen' : 'Te betalen' ?></p>
                </div>
                <div class="card p-6">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Gemiddeld per dag</p>
                    <p class="text-3xl font-bold <?= $avgPerDay >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                        <?= $avgPerDay >= 0 ? '+' : '–' ?>€<?= number_format(abs($avgPerDay), 2, ',', '.') ?>
                    </p>
                    <p class="text-xs text-gray-400 mt-1"><?= count($dailyData) ?> dagen</p>
                </div>
                <div class="card p-6">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Totaal Inzet</p>
                    <p class="text-3xl font-bold text-gray-900">€<?= number_format($totalBet, 2, ',', '.') ?></p>
                    <p class="text-xs text-gray-400 mt-1"><?= $totalBons ?> bonnen</p>
                </div>
                <div class="card p-6">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Totaal Uitbetaald</p>
                    <p class="text-3xl font-bold text-gray-900">€<?= number_format($totalWinnings, 2, ',', '.') ?></p>
                    <p class="text-xs text-gray-400 mt-1"><?= $totalRijen ?> rijen</p>
                </div>
            </div>

            <!-- Best/Worst Day -->
            <?php if ($bestDay && $worstDay): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="card p-6">
                    <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-3">Beste Dag</h3>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-lg font-semibold text-gray-900"><?= date('d M Y', strtotime($bestDay['day'])) ?></p>
                            <p class="text-sm text-gray-500"><?= $bestDay['total_bons'] ?> bonnen · <?= $bestDay['total_rijen'] ?> rijen</p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold text-emerald-600">+€<?= number_format($bestDaySaldo, 2, ',', '.') ?></p>
                        </div>
                    </div>
                </div>
                <div class="card p-6">
                    <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-3">Slechtste Dag</h3>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-lg font-semibold text-gray-900"><?= date('d M Y', strtotime($worstDay['day'])) ?></p>
                            <p class="text-sm text-gray-500"><?= $worstDay['total_bons'] ?> bonnen · <?= $worstDay['total_rijen'] ?> rijen</p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold text-red-500">–€<?= number_format(abs($worstDaySaldo), 2, ',', '.') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Chart -->
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Dagelijks Resultaat (Het Huis)</h3>
                <div style="position: relative; height: 300px;">
                    <canvas id="balanceChart"></canvas>
                </div>
            </div>

            <!-- Player Stats -->
            <?php if (!empty($playerData)): ?>
            <div class="card p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Spelers Overzicht</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-100 uppercase tracking-wide">
                                <th class="pb-3 font-medium">Speler</th>
                                <th class="pb-3 font-medium text-right">Bonnen</th>
                                <th class="pb-3 font-medium text-right">Rijen</th>
                                <th class="pb-3 font-medium text-right">Inzet</th>
                                <th class="pb-3 font-medium text-right">Winst</th>
                                <th class="pb-3 font-medium text-right">Het Huis</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($playerData as $player):
                                $playerBet = floatval($player['total_bet']);
                                $playerWinnings = floatval($player['total_winnings']);
                                $huisSaldo = $playerBet - $playerWinnings;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-3 h-3 rounded-full" style="background: <?= htmlspecialchars($player['color'] ?? '#3B82F6') ?>"></span>
                                        <span class="font-medium text-gray-800"><?= htmlspecialchars($player['name']) ?></span>
                                    </div>
                                </td>
                                <td class="py-3 text-right text-gray-600"><?= $player['total_bons'] ?></td>
                                <td class="py-3 text-right text-gray-600"><?= $player['total_rijen'] ?></td>
                                <td class="py-3 text-right text-gray-900">€<?= number_format($playerBet, 2, ',', '.') ?></td>
                                <td class="py-3 text-right text-gray-900">€<?= number_format($playerWinnings, 2, ',', '.') ?></td>
                                <td class="py-3 text-right font-semibold <?= $huisSaldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                                    <?= $huisSaldo >= 0 ? '+' : '–' ?>€<?= number_format(abs($huisSaldo), 2, ',', '.') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Prepare data for chart
        const dailyData = <?= json_encode(array_reverse($dailyData)) ?>;
        const labels = dailyData.map(d => {
            const date = new Date(d.day);
            return date.toLocaleDateString('nl-NL', { day: 'numeric', month: 'short' });
        });
        const data = dailyData.map(d => parseFloat(d.total_bet) - parseFloat(d.total_winnings));

        // Create chart
        const ctx = document.getElementById('balanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Het Huis Resultaat',
                    data: data,
                    backgroundColor: data.map(v => v >= 0 ? 'rgba(16, 185, 129, 0.8)' : 'rgba(239, 68, 68, 0.8)'),
                    borderColor: data.map(v => v >= 0 ? 'rgb(16, 185, 129)' : 'rgb(239, 68, 68)'),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '€' + value.toFixed(0);
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                const value = context.parsed.y;
                                label += (value >= 0 ? '+' : '–') + '€' + Math.abs(value).toFixed(2);
                                return label;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
