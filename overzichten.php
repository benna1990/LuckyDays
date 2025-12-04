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

$view = $_GET['view'] ?? 'daily';
$currentDate = date('Y-m-d');
$currentYear = date('Y');
$currentMonth = date('Y-m');

// Winkel selectie - altijd uit sessie
$winkels = getAllWinkels($conn);
$selectedWinkel = $_SESSION['selected_winkel'] ?? null; // null = "Alles"
$activeWinkelTheme = resolveActiveWinkelTheme($winkels, $selectedWinkel);
$winkelPalette = getWinkelPalette();

// ✅ NIEUWE PATTERNS: Repository + Money Pattern
$lotteryRepo = new LotteryRepository($conn);

// Prepare data based on view
$chartData = [];
$labels = [];
$periodTitle = '';

switch ($view) {
    case 'daily':
        // Last 30 days
        $periodTitle = "Laatste 30 Dagen";
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));

            // ✅ Gebruik Repository + Money Pattern
            $dayStats = $lotteryRepo->getDayStats($date, $selectedWinkel);

            $betCents = MoneyCalculator::toCents($dayStats['total_bet']);
            $winCents = MoneyCalculator::toCents($dayStats['total_winnings']);

            // Gebruik FinancialService voor consistente berekeningen
            $breakdown = FinancialService::calculateFinancialBreakdown($betCents, $winCents);
            $saldo = $breakdown['net_house_euros'];

            $labels[] = date('d M', strtotime($date));
            $chartData[] = $saldo;
        }
        break;
        
    case 'weekly':
        // Last 12 weeks
        $periodTitle = "Laatste 12 Weken";
        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i weeks"));
            $weekRange = getISOWeekRange($date);

            // ✅ Gebruik Repository + Money Pattern
            $weekTotals = $lotteryRepo->getWeekTotals($weekRange['start'], $weekRange['end'], $selectedWinkel);

            $betCents = MoneyCalculator::toCents($weekTotals['total_bet']);
            $winCents = MoneyCalculator::toCents($weekTotals['total_winnings']);

            $breakdown = FinancialService::calculateFinancialBreakdown($betCents, $winCents);
            $saldo = $breakdown['net_house_euros'];

            $labels[] = "Week " . $weekRange['week'];
            $chartData[] = $saldo;
        }
        break;
        
    case 'monthly':
        // Last 12 months
        $periodTitle = "Laatste 12 Maanden";
        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m-01', strtotime("-$i months"));
            $startDate = date('Y-m-01', strtotime($date));
            $endDate = date('Y-m-t', strtotime($date));
            
            $params = [$startDate, $endDate];
            $query = "SELECT COALESCE(SUM(r.bet), 0) as bet, COALESCE(SUM(r.winnings), 0) as winnings
                      FROM bons b LEFT JOIN rijen r ON b.id = r.bon_id
                      WHERE DATE(b.date) BETWEEN $1 AND $2";
            if ($selectedWinkel !== null) {
                $query .= " AND b.winkel_id = $3";
                $params[] = $selectedWinkel;
            }
            $result = db_query($query, $params);
            $data = db_fetch_assoc($result);
            $house_pot = floatval($data['bet']) * 0.70;
            $saldo = $house_pot - floatval($data['winnings']);
            
            $labels[] = date('M Y', strtotime($date));
            $chartData[] = $saldo;
        }
        break;
        
    case 'yearly':
        // All years with data
        $periodTitle = "Per Jaar";
        $yearQuery = "SELECT DISTINCT EXTRACT(YEAR FROM date) as year FROM bons ORDER BY year ASC";
        $yearResult = db_query($yearQuery, []);
        $years = db_fetch_all($yearResult) ?: [];
        
        foreach ($years as $yearData) {
            $year = intval($yearData['year']);
            $startDate = "$year-01-01";
            $endDate = "$year-12-31";
            
            $params = [$startDate, $endDate];
            $query = "SELECT COALESCE(SUM(r.bet), 0) as bet, COALESCE(SUM(r.winnings), 0) as winnings
                      FROM bons b LEFT JOIN rijen r ON b.id = r.bon_id
                      WHERE DATE(b.date) BETWEEN $1 AND $2";
            if ($selectedWinkel !== null) {
                $query .= " AND b.winkel_id = $3";
                $params[] = $selectedWinkel;
            }
            $result = db_query($query, $params);
            $data = db_fetch_assoc($result);
            $house_pot = floatval($data['bet']) * 0.70;
            $saldo = $house_pot - floatval($data['winnings']);
            
            $labels[] = "$year";
            $chartData[] = $saldo;
        }
        break;
}

// Calculate totals
$totalSaldo = array_sum($chartData);
$avgSaldo = count($chartData) > 0 ? $totalSaldo / count($chartData) : 0;
$bestPeriod = count($chartData) > 0 ? max($chartData) : 0;
$worstPeriod = count($chartData) > 0 ? min($chartData) : 0;
$bestIndex = count($chartData) > 0 ? array_search(max($chartData), $chartData) : 0;
$worstIndex = count($chartData) > 0 ? array_search(min($chartData), $chartData) : 0;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyses - Lucky Day</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        body { 
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            overflow-y: scroll;
            min-height: 100vh;
            background-color: #F8F9FA;
        }
        .card { 
            background: white; 
            border-radius: 16px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 12px rgba(0,0,0,0.04); 
        }
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
            .winkel-btn { padding: 8px 16px; font-size: 13px; }
        }
    </style>
</head>
<body>
    <?php include 'components/main_nav.php'; ?>

    <?php include 'components/old_data_warning.php'; ?>

    <?php include 'components/winkel_bar.php'; ?>

    <main class="container-fixed py-6">
        <!-- Header -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Tijd Analyses</h2>
            <p class="text-sm text-gray-600">Bekijk trends over dagen, weken, maanden en jaren</p>
        </div>

        <!-- Period Tabs -->
        <div class="flex gap-2 mb-6 overflow-x-auto pb-2">
            <a href="?view=daily" 
               class="tab-pill <?= $view === 'daily' ? 'active' : 'inactive' ?>"
               style="--tab-bg: <?= $activeWinkelTheme['accent'] ?>; --tab-color: <?= $activeWinkelTheme['accent'] ?>;">
                Dagelijks
            </a>
            <a href="?view=weekly" 
               class="tab-pill <?= $view === 'weekly' ? 'active' : 'inactive' ?>"
               style="--tab-bg: <?= $activeWinkelTheme['accent'] ?>; --tab-color: <?= $activeWinkelTheme['accent'] ?>;">
                Wekelijks
            </a>
            <a href="?view=monthly" 
               class="tab-pill <?= $view === 'monthly' ? 'active' : 'inactive' ?>"
               style="--tab-bg: <?= $activeWinkelTheme['accent'] ?>; --tab-color: <?= $activeWinkelTheme['accent'] ?>;">
                Maandelijks
            </a>
            <a href="?view=yearly" 
               class="tab-pill <?= $view === 'yearly' ? 'active' : 'inactive' ?>"
               style="--tab-bg: <?= $activeWinkelTheme['accent'] ?>; --tab-color: <?= $activeWinkelTheme['accent'] ?>;">
                Jaarlijks
            </a>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-4 sm:mb-6">
            <div class="card p-4 sm:p-6">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Totaal</p>
                <p class="text-xl sm:text-2xl lg:text-3xl font-bold <?= $totalSaldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                    <?= $totalSaldo >= 0 ? '+' : '–' ?>€<?= number_format(abs($totalSaldo), 0, ',', '.') ?>
                </p>
                <p class="text-xs text-gray-400 mt-1"><?= count($chartData) ?> periodes</p>
            </div>
            <div class="card p-4 sm:p-6">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Gemiddeld</p>
                <p class="text-xl sm:text-2xl lg:text-3xl font-bold <?= $avgSaldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                    <?= $avgSaldo >= 0 ? '+' : '–' ?>€<?= number_format(abs($avgSaldo), 0, ',', '.') ?>
                </p>
                <p class="text-xs text-gray-400 mt-1">per periode</p>
            </div>
            <div class="card p-4 sm:p-6">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Beste</p>
                <p class="text-xl sm:text-2xl lg:text-3xl font-bold text-emerald-600">
                    +€<?= number_format($bestPeriod, 0, ',', '.') ?>
                </p>
                <p class="text-xs text-gray-400 mt-1"><?= $labels[$bestIndex] ?? '-' ?></p>
            </div>
            <div class="card p-4 sm:p-6">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Slechtste</p>
                <p class="text-xl sm:text-2xl lg:text-3xl font-bold text-red-500">
                    <?= $worstPeriod >= 0 ? '+' : '–' ?>€<?= number_format(abs($worstPeriod), 0, ',', '.') ?>
                </p>
                <p class="text-xs text-gray-400 mt-1"><?= $labels[$worstIndex] ?? '-' ?></p>
            </div>
        </div>

        <!-- Chart -->
        <div class="card p-4 sm:p-6 mb-4 sm:mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base sm:text-lg font-semibold text-gray-800"><?= $periodTitle ?></h3>
                <div class="flex items-center gap-2 text-xs sm:text-sm">
                    <span class="w-3 h-3 bg-emerald-500 rounded"></span>
                    <span class="text-gray-600">Winst</span>
                    <span class="w-3 h-3 bg-red-500 rounded ml-2"></span>
                    <span class="text-gray-600">Verlies</span>
                </div>
            </div>
            <div style="position: relative; height: 300px;">
                <canvas id="periodChart"></canvas>
            </div>
        </div>

        <!-- Period Details -->
        <div class="card p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-800 mb-4">Gedetailleerd Overzicht</h3>
            <div class="overflow-x-auto">
                <div class="grid gap-2 min-w-[500px]">
                    <?php foreach ($labels as $index => $label): 
                        $value = $chartData[$index];
                        $isPositive = $value >= 0;
                    ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <span class="font-medium text-gray-800 text-sm"><?= $label ?></span>
                        <div class="flex items-center gap-3">
                            <div class="w-24 sm:w-32 h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full <?= $isPositive ? 'bg-emerald-500' : 'bg-red-500' ?>" 
                                     style="width: <?= min(100, abs($value) / (max(abs($bestPeriod), abs($worstPeriod)) ?: 1) * 100) ?>%"></div>
                            </div>
                            <span class="font-semibold <?= $isPositive ? 'text-emerald-600' : 'text-red-500' ?> text-sm sm:text-base w-20 sm:w-24 text-right">
                                <?= $isPositive ? '+' : '–' ?>€<?= number_format(abs($value), 0, ',', '.') ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Quick Link -->
        <div class="mt-4 sm:mt-6 text-center">
            <a href="balans.php" class="inline-flex items-center gap-2 px-4 sm:px-6 py-2 sm:py-3 bg-white text-gray-700 rounded-lg hover:bg-gray-50 transition text-sm sm:text-base">
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Bekijk Spelerbalans
            </a>
        </div>
    </main>

    <script>
        const labels = <?= json_encode($labels) ?>;
        const data = <?= json_encode($chartData) ?>;

        const ctx = document.getElementById('periodChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Resultaat',
                    data: data,
                    backgroundColor: data.map(v => v >= 0 ? 'rgba(16, 185, 129, 0.8)' : 'rgba(239, 68, 68, 0.8)'),
                    borderColor: data.map(v => v >= 0 ? 'rgb(16, 185, 129)' : 'rgb(239, 68, 68)'),
                    borderWidth: 2,
                    borderRadius: 6
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
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed.y;
                                return (value >= 0 ? '+' : '–') + '€' + Math.abs(value).toFixed(2);
                            }
                        }
                    }
                }
            }
        });
        
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
    </script>
</body>
</html>
