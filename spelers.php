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

// Get view type and calculate date ranges
$view = $_GET['view'] ?? 'custom';
$currentDate = date('Y-m-d');

switch ($view) {
    case 'daily':
        $startDate = $currentDate;
        $endDate = $currentDate;
        break;
    case 'weekly':
        $weekRange = getISOWeekRange($currentDate);
        $startDate = $weekRange['start'];
        $endDate = $weekRange['end'];
        break;
    case 'monthly':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        break;
    case 'yearly':
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');
        break;
    case 'last7':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = $currentDate;
        break;
    case 'last30':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = $currentDate;
        break;
    default: // custom
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
        break;
}

// Winkel selectie - altijd uit sessie
$selectedWinkel = $_SESSION['selected_winkel'] ?? null; // null = "Alles"
$winkels = getAllWinkels($conn);
$activeWinkelTheme = resolveActiveWinkelTheme($winkels, $selectedWinkel);
$winkelPalette = getWinkelPalette();

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
    WHERE DATE(b.date) BETWEEN $1 AND $2";

$params = [$startDate, $endDate];
if ($selectedWinkel !== null) {
    $query .= " AND b.winkel_id = $3";
    $params[] = $selectedWinkel;
}

$query .= " GROUP BY DATE(b.date) ORDER BY day DESC";

$result = db_query($query, $params);
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

// Get unique players count for the period
$playersCountQuery = "SELECT COUNT(DISTINCT player_id) as count FROM bons WHERE DATE(date) BETWEEN $1 AND $2";
$playersCountParams = [$startDate, $endDate];

if ($selectedWinkel !== null) {
    $playersCountQuery .= " AND winkel_id = $3";
    $playersCountParams[] = $selectedWinkel;
}

$playersCountResult = db_query($playersCountQuery, $playersCountParams);
$playersCount = db_fetch_assoc($playersCountResult);
$totalPlayers = intval($playersCount['count'] ?? 0);

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
    LEFT JOIN bons b ON p.id = b.player_id AND DATE(b.date) BETWEEN $1 AND $2";

$playerQueryParams = [$startDate, $endDate];

if ($selectedWinkel !== null) {
    $playerQuery .= " AND b.winkel_id = $3";
    $playerQueryParams[] = $selectedWinkel;
}

$playerQuery .= "
    LEFT JOIN rijen r ON b.id = r.bon_id
    WHERE b.id IS NOT NULL AND r.id IS NOT NULL
    GROUP BY p.id, p.name, p.color
    HAVING COUNT(DISTINCT b.id) > 0 AND COUNT(r.id) > 0
    ORDER BY (COALESCE(SUM(r.bet), 0) - COALESCE(SUM(r.winnings), 0)) DESC
";

$playerResult = db_query($playerQuery, $playerQueryParams);
$playerData = db_fetch_all($playerResult) ?: [];

// Get top/bottom performers (after $playerData is defined)
$mostProfitable = !empty($playerData) ? array_slice($playerData, 0, 5) : [];
$leastProfitable = !empty($playerData) ? array_slice(array_reverse($playerData), 0, 5) : [];

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spelers - Lucky Day</title>
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
        
        /* Week selector scrollbar styling */
        #week-selector-container,
        #modal-week-selector-container {
            scrollbar-width: thin;
            scrollbar-color: <?= $activeWinkelTheme['accent'] ?>40 #F3F4F6;
        }

        #week-selector-container::-webkit-scrollbar,
        #modal-week-selector-container::-webkit-scrollbar {
            height: 6px;
        }

        #week-selector-container::-webkit-scrollbar-track,
        #modal-week-selector-container::-webkit-scrollbar-track {
            background: #F3F4F6;
            border-radius: 3px;
        }

        #week-selector-container::-webkit-scrollbar-thumb,
        #modal-week-selector-container::-webkit-scrollbar-thumb {
            background: <?= $activeWinkelTheme['accent'] ?>40;
            border-radius: 3px;
        }

        #week-selector-container::-webkit-scrollbar-thumb:hover,
        #modal-week-selector-container::-webkit-scrollbar-thumb:hover {
            background: <?= $activeWinkelTheme['accent'] ?>60;
        }

        .week-btn {
            white-space: nowrap;
        }

        .week-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Speler Overzicht</h2>
            <p class="text-sm text-gray-600">Bekijk statistieken per speler</p>
        </div>

            <!-- Tabs -->
            <div class="flex gap-2 mb-6 border-b border-gray-200">
                <button onclick="switchTab('lifetime')" id="tab-lifetime" 
                        class="tab-underline active"
                        style="--tab-color: <?= $activeWinkelTheme['accent'] ?>; --tab-color-light: <?= $activeWinkelTheme['accent'] ?>80;">
                    Lifetime Statistieken
                </button>
                <button onclick="switchTab('period')" id="tab-period" 
                        class="tab-underline inactive"
                        style="--tab-color: <?= $activeWinkelTheme['accent'] ?>; --tab-color-light: <?= $activeWinkelTheme['accent'] ?>80;">
                    Per Periode
                </button>
            </div>

            <!-- Tab Content: Lifetime -->
            <div id="content-lifetime">
                <?php
                // Get selected winkel from session
                $selectedWinkelFilter = $_SESSION['selected_winkel'] ?? null;
                
                $lifetimeQuery = "
                    SELECT
                        p.id, p.name, p.color, p.winkel_id,
                        w.naam as winkel_naam,
                        COUNT(DISTINCT b.id) as total_bons,
                        COUNT(r.id) as total_rijen,
                        COALESCE(SUM(r.bet), 0) as total_bet,
                        COALESCE(SUM(r.winnings), 0) as total_winnings,
                        COALESCE(SUM(r.winnings), 0) - COALESCE(SUM(r.bet), 0) as saldo
                    FROM players p
                    LEFT JOIN winkels w ON p.winkel_id = w.id
                    LEFT JOIN bons b ON p.id = b.player_id
                    LEFT JOIN rijen r ON r.bon_id = b.id";

                $lifetimeParams = [];
                if ($selectedWinkelFilter) {
                    $lifetimeQuery .= " WHERE p.winkel_id = $1";
                    $lifetimeParams[] = $selectedWinkelFilter;
                }
                
                $lifetimeQuery .= " GROUP BY p.id, p.name, p.color, p.winkel_id, w.naam
                    ORDER BY saldo DESC";
                
                $lifetimeResult = db_query($lifetimeQuery, $lifetimeParams);
                $lifetimePlayers = db_fetch_all($lifetimeResult) ?: [];
                
                // Winkel filter toevoegen
                $showWinkelColumn = $selectedWinkelFilter === null; // Only show winkel column when "Alles" selected
                ?>

                <div class="card p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Alle Spelers (Totaal sinds Begin)</h3>
                    </div>
                    
                    <!-- Live Search Filter -->
                    <div class="mb-4">
                        <input type="text" 
                               id="player-search" 
                               placeholder="Zoek op naam of winkel..." 
                               class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:border-transparent transition-all"
                               style="--tw-ring-color: <?= $activeWinkelTheme['accent'] ?>;">
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                                    <th class="text-left py-3 px-4 cursor-pointer hover:bg-gray-50 font-semibold" onclick="sortLifetimeTable('name')">
                                        <span class="inline-flex items-center gap-1">Speler <span class="sort-arrow" data-sort="name"></span></span>
                                    </th>
                                    <?php if ($showWinkelColumn): ?>
                                    <th class="text-left py-3 px-4 cursor-pointer hover:bg-gray-50 font-semibold" onclick="sortLifetimeTable('winkel')">
                                        <span class="inline-flex items-center gap-1">Winkel <span class="sort-arrow" data-sort="winkel"></span></span>
                                    </th>
                                    <?php endif; ?>
                                    <th class="text-right py-3 px-4 cursor-pointer hover:bg-gray-50 font-semibold" onclick="sortLifetimeTable('bonnen')">
                                        <span class="inline-flex items-center gap-1">Bonnen <span class="sort-arrow" data-sort="bonnen"></span></span>
                                    </th>
                                    <th class="text-right py-3 px-4 cursor-pointer hover:bg-gray-50 font-semibold" onclick="sortLifetimeTable('rijen')">
                                        <span class="inline-flex items-center gap-1">Rijen <span class="sort-arrow" data-sort="rijen"></span></span>
                                    </th>
                                    <th class="text-right py-3 px-4 cursor-pointer hover:bg-gray-50 font-semibold" onclick="sortLifetimeTable('inzet')">
                                        <span class="inline-flex items-center gap-1">Inzet <span class="sort-arrow" data-sort="inzet"></span></span>
                                    </th>
                                    <th class="text-right py-3 px-4 cursor-pointer hover:bg-gray-50 font-semibold" onclick="sortLifetimeTable('winst')">
                                        <span class="inline-flex items-center gap-1">Uitbetaald <span class="sort-arrow" data-sort="winst"></span></span>
                                    </th>
                                    <th class="text-right py-3 px-4 cursor-pointer hover:bg-gray-50 font-semibold" onclick="sortLifetimeTable('saldo')">
                                        <span class="inline-flex items-center gap-1">Huisresultaat <span class="sort-arrow" data-sort="saldo"></span></span>
                                    </th>
                                    <th class="text-center py-3 px-4 font-semibold">Richting</th>
                                </tr>
                            </thead>
                            <tbody id="lifetime-tbody">
                                <?php
                                if (empty($lifetimePlayers)):
                                ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-gray-400">Nog geen spelerdata</td>
                                </tr>
                                <?php
                                else:
                                    foreach ($lifetimePlayers as $player):
                                        $huisSaldo = floatval($player['total_bet']) - floatval($player['total_winnings']);
                                        $saldoClass = $huisSaldo >= 0 ? 'text-emerald-600' : 'text-red-500';
                                ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50" 
                                    data-name="<?= htmlspecialchars($player['name']) ?>"
                                    data-player-id="<?= $player['id'] ?>"
                                    data-player-color="<?= htmlspecialchars($player['color']) ?>"
                                    data-winkel="<?= $player['winkel_id'] ?? '' ?>"
                                    data-bonnen="<?= $player['total_bons'] ?>"
                                    data-rijen="<?= $player['total_rijen'] ?>"
                                    data-inzet="<?= $player['total_bet'] ?>"
                                    data-winst="<?= $player['total_winnings'] ?>"
                                    data-saldo="<?= $huisSaldo ?>">
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-medium" style="background: <?= htmlspecialchars($player['color']) ?>">
                                                <?= strtoupper(substr($player['name'], 0, 1)) ?>
                                            </div>
                                            <span class="font-medium cursor-pointer hover:text-blue-600 hover:underline transition-colors" 
                                                  onclick="openPlayerModal(<?= $player['id'] ?>, '<?= htmlspecialchars($player['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($player['color'], ENT_QUOTES) ?>')">
                                                <?= htmlspecialchars($player['name']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <?php if ($showWinkelColumn): ?>
                                    <td class="py-3 px-4"><?= htmlspecialchars($player['winkel_naam'] ?? 'Geen') ?></td>
                                    <?php endif; ?>
                                    <td class="py-3 px-4 text-right"><?= $player['total_bons'] ?></td>
                                    <td class="py-3 px-4 text-right"><?= $player['total_rijen'] ?></td>
                                    <td class="py-3 px-4 text-right">€<?= number_format($player['total_bet'], 2, ',', '.') ?></td>
                                    <td class="py-3 px-4 text-right">€<?= number_format($player['total_winnings'], 2, ',', '.') ?></td>
                                    <td class="py-3 px-4 text-right font-semibold <?= $saldoClass ?>">
                                        <?= $huisSaldo >= 0 ? '+' : '−' ?>€<?= number_format(abs($huisSaldo), 2, ',', '.') ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <?php if ($huisSaldo > 0): ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">↑ Huis wint</span>
                                        <?php elseif ($huisSaldo < 0): ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">↓ Huis verliest</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">→ Gelijk</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                                    endforeach;
                                endif;
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Period (existing content) -->
            <div id="content-period" class="hidden">

            <!-- Period Selector -->
            <div class="flex gap-2 mb-6 flex-wrap">
                <a href="?view=daily" class="px-4 py-2 rounded-lg text-sm font-medium transition <?= $view === 'daily' ? 'bg-emerald-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                    Vandaag
                </a>
                <a href="?view=weekly" class="px-4 py-2 rounded-lg text-sm font-medium transition <?= $view === 'weekly' ? 'bg-emerald-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                    Deze Week
                </a>
                <a href="?view=monthly" class="px-4 py-2 rounded-lg text-sm font-medium transition <?= $view === 'monthly' ? 'bg-emerald-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                    Deze Maand
                </a>
                <a href="?view=yearly" class="px-4 py-2 rounded-lg text-sm font-medium transition <?= $view === 'yearly' ? 'bg-emerald-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                    Dit Jaar
                </a>
                <a href="?view=last7" class="px-4 py-2 rounded-lg text-sm font-medium transition <?= $view === 'last7' ? 'bg-emerald-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                    Laatste 7 Dagen
                </a>
                <a href="?view=last30" class="px-4 py-2 rounded-lg text-sm font-medium transition <?= $view === 'last30' ? 'bg-emerald-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                    Laatste 30 Dagen
                </a>
                <button onclick="document.getElementById('custom-range').classList.toggle('hidden')" class="px-4 py-2 rounded-lg text-sm font-medium transition <?= $view === 'custom' ? 'bg-emerald-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                    Aangepast
                </button>
            </div>

            <!-- Custom Date Range Selector -->
            <div id="custom-range" class="card p-6 mb-6 <?= $view === 'custom' ? '' : 'hidden' ?>">
                <form method="GET" class="flex flex-wrap items-end gap-4">
                    <input type="hidden" name="view" value="custom">
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
                    <button type="submit" class="px-6 py-2 text-white rounded-lg transition font-medium"
                            style="background: <?= $activeWinkelTheme['accent'] ?>;"
                            onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                        Bijwerken
                    </button>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="card p-6">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Totaal Resultaat</p>
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
                    <p class="text-xs text-gray-400 mt-1"><?= $totalBons ?> bonnen · <?= $totalPlayers ?> spelers</p>
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
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Dagelijks Resultaat</h3>
                <div style="position: relative; height: 300px;">
                    <canvas id="balanceChart"></canvas>
                </div>
            </div>

            <!-- Top Performers -->
            <?php if (!empty($mostProfitable) && count($playerData) > 1): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="card p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Meest Winstgevend (Top 5)</h3>
                    <div class="space-y-3">
                        <?php foreach ($mostProfitable as $player):
                            $playerBet = floatval($player['total_bet']);
                            $playerWinnings = floatval($player['total_winnings']);
                            $huisSaldo = $playerBet - $playerWinnings;
                        ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full" style="background: <?= htmlspecialchars($player['color']) ?>"></span>
                                <span class="font-medium text-gray-800"><?= htmlspecialchars($player['name']) ?></span>
                            </div>
                            <span class="font-semibold text-emerald-600">+€<?= number_format($huisSaldo, 2, ',', '.') ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if (!empty($leastProfitable)): ?>
                <div class="card p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Minst Winstgevend (Top 5)</h3>
                    <div class="space-y-3">
                        <?php foreach ($leastProfitable as $player):
                            $playerBet = floatval($player['total_bet']);
                            $playerWinnings = floatval($player['total_winnings']);
                            $huisSaldo = $playerBet - $playerWinnings;
                        ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full" style="background: <?= htmlspecialchars($player['color']) ?>"></span>
                                <span class="font-medium text-gray-800"><?= htmlspecialchars($player['name']) ?></span>
                            </div>
                            <span class="font-semibold <?= $huisSaldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                                <?= $huisSaldo >= 0 ? '+' : '–' ?>€<?= number_format(abs($huisSaldo), 2, ',', '.') ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Player Stats -->
            <?php if (!empty($playerData)): ?>
            <div class="card p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Alle Spelers</h3>
                <div class="overflow-x-auto">
                    <table id="player-table" class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-100 uppercase tracking-wide">
                                <th class="pb-3 font-medium cursor-pointer hover:text-gray-700" onclick="sortTable(0)">
                                    Speler <span class="sort-arrow">↕</span>
                                </th>
                                <th class="pb-3 font-medium text-right cursor-pointer hover:text-gray-700" onclick="sortTable(1)">
                                    Bonnen <span class="sort-arrow">↕</span>
                                </th>
                                <th class="pb-3 font-medium text-right cursor-pointer hover:text-gray-700" onclick="sortTable(2)">
                                    Rijen <span class="sort-arrow">↕</span>
                                </th>
                                <th class="pb-3 font-medium text-right cursor-pointer hover:text-gray-700" onclick="sortTable(3)">
                                    Inzet <span class="sort-arrow">↕</span>
                                </th>
                                <th class="pb-3 font-medium text-right cursor-pointer hover:text-gray-700" onclick="sortTable(4)">
                                    Winst <span class="sort-arrow">↕</span>
                                </th>
                                <th class="pb-3 font-medium text-right cursor-pointer hover:text-gray-700" onclick="sortTable(5)">
                                    Resultaat <span class="sort-arrow">↕</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($playerData as $player):
                                $playerBet = floatval($player['total_bet']);
                                $playerWinnings = floatval($player['total_winnings']);
                                $huisSaldo = $playerBet - $playerWinnings;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3" data-value="<?= htmlspecialchars($player['name']) ?>">
                                    <div class="flex items-center gap-2">
                                        <span class="w-3 h-3 rounded-full" style="background: <?= htmlspecialchars($player['color'] ?? '#3B82F6') ?>"></span>
                                        <span class="font-medium text-gray-800"><?= htmlspecialchars($player['name']) ?></span>
                                    </div>
                                </td>
                                <td class="py-3 text-right text-gray-600" data-value="<?= $player['total_bons'] ?>"><?= $player['total_bons'] ?></td>
                                <td class="py-3 text-right text-gray-600" data-value="<?= $player['total_rijen'] ?>"><?= $player['total_rijen'] ?></td>
                                <td class="py-3 text-right text-gray-900" data-value="<?= $playerBet ?>">€<?= number_format($playerBet, 2, ',', '.') ?></td>
                                <td class="py-3 text-right text-gray-900" data-value="<?= $playerWinnings ?>">€<?= number_format($playerWinnings, 2, ',', '.') ?></td>
                                <td class="py-3 text-right font-semibold <?= $huisSaldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>" data-value="<?= $huisSaldo ?>">
                                    <?= $huisSaldo >= 0 ? '+' : '–' ?>€<?= number_format(abs($huisSaldo), 2, ',', '.') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            </div><!-- End content-period -->
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
                    label: 'Resultaat',
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

        // Table sorting
        let sortDirection = [];
        function sortTable(columnIndex) {
            const table = document.getElementById('player-table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Toggle direction
            if (!sortDirection[columnIndex]) sortDirection[columnIndex] = 'asc';
            else if (sortDirection[columnIndex] === 'asc') sortDirection[columnIndex] = 'desc';
            else sortDirection[columnIndex] = 'asc';
            
            const dir = sortDirection[columnIndex];
            
            rows.sort((a, b) => {
                const aCell = a.querySelectorAll('td')[columnIndex];
                const bCell = b.querySelectorAll('td')[columnIndex];
                
                const aValue = aCell.getAttribute('data-value');
                const bValue = bCell.getAttribute('data-value');
                
                let comparison = 0;
                
                // Try numeric comparison first
                const aNum = parseFloat(aValue);
                const bNum = parseFloat(bValue);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    comparison = aNum - bNum;
                } else {
                    // String comparison
                    comparison = aValue.localeCompare(bValue, 'nl');
                }
                
                return dir === 'asc' ? comparison : -comparison;
            });
            
            // Re-append sorted rows
            rows.forEach(row => tbody.appendChild(row));
            
            // Update arrows
            document.querySelectorAll('.sort-arrow').forEach(arrow => arrow.textContent = '↕');
            const arrow = table.querySelectorAll('thead th')[columnIndex].querySelector('.sort-arrow');
            arrow.textContent = dir === 'asc' ? '↑' : '↓';
        }
        
        // Tab switching
        function switchTab(tab) {
            // Update tab buttons with new class system
            const lifetimeTab = document.getElementById('tab-lifetime');
            const periodTab = document.getElementById('tab-period');
            
            if (tab === 'lifetime') {
                lifetimeTab.classList.remove('inactive');
                lifetimeTab.classList.add('active');
                periodTab.classList.remove('active');
                periodTab.classList.add('inactive');
            } else {
                periodTab.classList.remove('inactive');
                periodTab.classList.add('active');
                lifetimeTab.classList.remove('active');
                lifetimeTab.classList.add('inactive');
            }
            
            // Update content
            document.getElementById('content-lifetime').classList.toggle('hidden', tab !== 'lifetime');
            document.getElementById('content-period').classList.toggle('hidden', tab !== 'period');
        }

        // Sortable lifetime table
        let lifetimeSortOrder = {};
        function sortLifetimeTable(column) {
            const tbody = document.getElementById('lifetime-tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Skip if only one row or no data
            if (rows.length <= 1 || !rows[0].dataset.name) return;
            
            lifetimeSortOrder[column] = !lifetimeSortOrder[column];
            const ascending = lifetimeSortOrder[column];
            
            rows.sort((a, b) => {
                let aVal, bVal;
                
                if (column === 'name') {
                    aVal = (a.dataset.name || '').toLowerCase();
                    bVal = (b.dataset.name || '').toLowerCase();
                    return ascending ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                } else {
                    aVal = parseFloat(a.dataset[column]) || 0;
                    bVal = parseFloat(b.dataset[column]) || 0;
                    return ascending ? aVal - bVal : bVal - aVal;
                }
            });
            
            rows.forEach(row => tbody.appendChild(row));
            updateLifetimeSortArrows(column, ascending);
        }

        function updateLifetimeSortArrows(column, ascending) {
            document.querySelectorAll('.sort-arrow[data-sort]').forEach(el => {
                el.classList.remove('asc', 'desc');
                if (el.dataset.sort === column) {
                    el.classList.add(ascending ? 'asc' : 'desc');
                }
            });
        }
        
        // Winkel filter functionaliteit
        function filterByWinkel(winkelId) {
            const tbody = document.getElementById('lifetime-tbody');
            const rows = Array.from(tbody.querySelectorAll('tr[data-winkel]'));
            
            rows.forEach(row => {
                if (!winkelId || row.dataset.winkel == winkelId) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
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

        // Live search filter for lifetime table
        document.getElementById('player-search')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const tbody = document.getElementById('lifetime-tbody');
            const rows = tbody.querySelectorAll('tr[data-name]');
            
            rows.forEach(row => {
                const name = row.dataset.name?.toLowerCase() || '';
                const winkelCell = row.querySelector('td:nth-child(2)');
                const winkelName = winkelCell?.textContent.toLowerCase() || '';
                
                if (name.includes(searchTerm) || winkelName.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Player Modal Functions
        async function openPlayerModal(playerId, playerName, playerColor) {
            const modal = document.getElementById('playerModal');
            const avatar = document.getElementById('modalPlayerAvatar');
            const name = document.getElementById('modalPlayerName');
            const stats = document.getElementById('modalPlayerStats');
            const body = document.getElementById('modalBody');
            
            // Set header
            avatar.style.background = playerColor;
            avatar.textContent = playerName.charAt(0).toUpperCase();
            name.textContent = playerName;
            stats.textContent = 'Laden...';
            
            // Show modal with loading
            modal.classList.remove('hidden');
            body.innerHTML = `
                <div class="text-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500 mx-auto"></div>
                    <p class="mt-4 text-gray-500">Laden van bonnen...</p>
                </div>
            `;
            
            // Fetch data
            try {
                const response = await fetch(`api/get_player_detail.php?player_id=${playerId}`);
                const data = await response.json();
                
                if (data.success) {
                    renderPlayerBonnen(data);
                } else {
                    body.innerHTML = '<p class="text-center text-red-500 py-8">Fout bij laden data: ' + (data.error || 'Onbekende fout') + '</p>';
                }
            } catch (e) {
                console.error('Fout bij laden player detail:', e);
                body.innerHTML = '<p class="text-center text-red-500 py-8">Netwerkfout bij laden van gegevens</p>';
            }
        }

        function closePlayerModal() {
            document.getElementById('playerModal').classList.add('hidden');
        }

        function renderPlayerBonnen(data) {
            const stats = document.getElementById('modalPlayerStats');
            const body = document.getElementById('modalBody');
            
            const player = data.player;
            const bonnen = data.bonnen || [];
            
            // Update stats
            stats.textContent = `${bonnen.length} bonnen · €${formatNumber(player.total_bet)} totale inzet`;
            
            if (bonnen.length === 0) {
                body.innerHTML = '<p class="text-center text-gray-500 py-8">Geen bonnen gevonden voor deze speler</p>';
                return;
            }
            
            // Group bonnen by month
            const grouped = {};
            bonnen.forEach(bon => {
                const date = new Date(bon.date);
                const monthKey = date.toLocaleDateString('nl-NL', { year: 'numeric', month: 'long' });
                if (!grouped[monthKey]) grouped[monthKey] = [];
                grouped[monthKey].push(bon);
            });
            
            // Render timeline with expandable rijen
            let html = '';
            Object.keys(grouped).forEach(month => {
                html += `<div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-400 uppercase tracking-wide mb-3 border-b pb-2">${month}</h4>
                    <div class="space-y-2">`;
                
                grouped[month].forEach(bon => {
                    const saldo = parseFloat(bon.saldo);
                    const saldoClass = saldo >= 0 ? 'text-emerald-600' : 'text-red-500';
                    const saldoSymbol = saldo >= 0 ? '+' : '';
                    
                    const date = new Date(bon.date);
                    const dateStr = date.toLocaleDateString('nl-NL', { day: 'numeric', month: 'short' });
                    
                    html += `
                        <div class="bon-item border border-gray-200 rounded-lg p-4 hover:shadow-md transition-all cursor-pointer" 
                             onclick="toggleBonRijen(${bon.bon_id})">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-gray-400 bon-chevron transition-transform" id="chevron-${bon.bon_id}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                    <div>
                                        <div class="font-medium text-gray-900">${dateStr} · Bon #${bon.bonnummer}</div>
                                        <div class="text-sm text-gray-500">${bon.rijen_count} rijen · ${bon.winkel_naam || 'Geen winkel'}</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm text-gray-600">€${formatNumber(bon.total_bet)} → €${formatNumber(bon.total_winnings)}</div>
                                    <div class="font-semibold ${saldoClass}">${saldoSymbol}€${formatNumber(Math.abs(saldo))}</div>
                                </div>
                            </div>
                            
                            <!-- Expandable rijen section -->
                            <div id="rijen-${bon.bon_id}" class="rijen-expand mt-3 pt-3 border-t border-gray-100" style="max-height: 0; overflow: hidden;">
                                <div class="text-center text-gray-400 py-2">
                                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-emerald-500 mx-auto"></div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += `</div></div>`;
            });
            
            body.innerHTML = html;
        }

        async function toggleBonRijen(bonId) {
            const rijenDiv = document.getElementById(`rijen-${bonId}`);
            const chevron = document.getElementById(`chevron-${bonId}`);
            
            // Toggle open/closed
            if (rijenDiv.style.maxHeight && rijenDiv.style.maxHeight !== '0px') {
                rijenDiv.style.maxHeight = '0';
                chevron.style.transform = 'rotate(0deg)';
                return;
            }
            
            // If not yet loaded, fetch rijen
            if (rijenDiv.dataset.loaded !== 'true') {
                try {
                    const response = await fetch(`api/get_bon_rijen.php?bon_id=${bonId}`);
                    const data = await response.json();
                    
                    if (data.success && data.rijen) {
                        let html = '<div class="space-y-2">';
                        data.rijen.forEach((rij, index) => {
                            const matchClass = rij.winnings > 0 ? 'text-emerald-600' : 'text-gray-500';
                            html += `
                                <div class="flex items-center justify-between p-2 bg-gray-50 rounded text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-400">${index + 1}.</span>
                                        <span class="font-mono text-xs">[${rij.numbers}]</span>
                                        <span class="text-gray-500">€${formatNumber(rij.bet)}</span>
                                        ${rij.multiplier > 1 ? `<span class="text-blue-600 text-xs">×${rij.multiplier}</span>` : ''}
                                    </div>
                                    <div class="${matchClass} font-medium">
                                        ${rij.matches} match${rij.matches !== 1 ? 'es' : ''} → €${formatNumber(rij.winnings)}
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        rijenDiv.innerHTML = html;
                        rijenDiv.dataset.loaded = 'true';
                    } else {
                        rijenDiv.innerHTML = '<p class="text-gray-400 text-sm text-center py-2">Geen rijen gevonden</p>';
                    }
                } catch (e) {
                    console.error('Fout bij laden rijen:', e);
                    rijenDiv.innerHTML = '<p class="text-red-500 text-sm text-center py-2">Fout bij laden</p>';
                }
            }
            
            // Expand
            rijenDiv.style.maxHeight = rijenDiv.scrollHeight + 'px';
            chevron.style.transform = 'rotate(90deg)';
        }

        function formatNumber(num) {
            return parseFloat(num).toFixed(2).replace('.', ',');
        }
    </script>

    <script>
        // Player Modal Functions
        let currentModalPlayerId = null;
        let currentModalPlayerName = null;
        let currentModalPlayerColor = null;
        let modalSelectedWeek = null;
        let modalSelectedYear = null;
        let modalAllWeeks = [];

        async function openPlayerModal(playerId, playerName, playerColor) {
            currentModalPlayerId = playerId;
            currentModalPlayerName = playerName;
            currentModalPlayerColor = playerColor;
            modalSelectedWeek = null;
            modalSelectedYear = null;

            const modal = document.getElementById('playerModal');
            const avatar = document.getElementById('modalPlayerAvatar');
            const name = document.getElementById('modalPlayerName');
            const stats = document.getElementById('modalPlayerStats');
            const body = document.getElementById('modalBody');

            // Set header
            avatar.style.background = playerColor;
            avatar.textContent = playerName.charAt(0).toUpperCase();
            name.textContent = playerName;
            stats.textContent = 'Laden...';

            // Show modal
            modal.classList.remove('hidden');

            // Load week selector
            await loadModalWeekSelector();

            // Load bonnen
            await loadPlayerBonnen();
        }

        async function loadModalWeekSelector() {
            try {
                const params = new URLSearchParams();
                params.append('player_id', currentModalPlayerId);
                if (modalSelectedYear) params.append('year', modalSelectedYear);
                if (modalSelectedWeek) params.append('week', modalSelectedWeek);

                const response = await fetch('api/get_player_weeks.php?' + params);
                const data = await response.json();

                if (data.success) {
                    modalAllWeeks = data.weeks;
                    renderModalWeekSelector();
                }
            } catch (e) {
                console.error('Error loading weeks:', e);
            }
        }

        function renderModalWeekSelector() {
            const container = document.getElementById('modal-week-selector');
            if (!container) return;

            container.innerHTML = '';

            const currentYear = <?= date('o') ?>;
            const currentWeek = <?= date('W') ?>;

            modalAllWeeks.forEach(week => {
                const isSelected = modalSelectedYear === week.year && modalSelectedWeek === week.week;
                const isCurrentWeek = !modalSelectedYear && !modalSelectedWeek && week.year === currentYear && week.week === currentWeek;

                const button = document.createElement('button');
                button.className = 'week-btn flex-shrink-0 px-3 py-2 text-xs font-medium rounded-lg transition-all';

                // Styling based on state
                if (isSelected || isCurrentWeek) {
                    button.style.background = '<?= $activeWinkelTheme['accent'] ?>';
                    button.style.color = 'white';
                    button.style.fontWeight = '600';
                } else if (week.has_bonnen) {
                    button.style.background = 'white';
                    button.style.color = '#374151';
                    button.style.border = '2px solid <?= $activeWinkelTheme['accent'] ?>';
                } else {
                    button.style.background = '#F3F4F6';
                    button.style.color = '#9CA3AF';
                    button.style.border = '1px solid #E5E7EB';
                }

                // Format date range (dd-mm)
                const startDate = new Date(week.start);
                const endDate = new Date(week.end);
                const startStr = String(startDate.getDate()).padStart(2, '0') + '-' + String(startDate.getMonth() + 1).padStart(2, '0');
                const endStr = String(endDate.getDate()).padStart(2, '0') + '-' + String(endDate.getMonth() + 1).padStart(2, '0');

                // Create week label with date range
                button.innerHTML = `
                    <div style="line-height: 1.2; text-align: center;">
                        <div style="font-weight: 600;">${week.label}</div>
                        <div style="font-size: 10px; opacity: 0.8; margin-top: 2px;">${startStr}</div>
                    </div>
                `;
                button.onclick = () => selectModalWeek(week.year, week.week);

                container.appendChild(button);

                // Scroll to selected/current week
                if (isSelected || isCurrentWeek) {
                    setTimeout(() => {
                        button.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    }, 100);
                }
            });
        }

        async function selectModalWeek(year, week) {
            modalSelectedYear = year;
            modalSelectedWeek = week;
            renderModalWeekSelector();
            await loadPlayerBonnen();
        }

        async function resetModalWeekFilter() {
            modalSelectedYear = null;
            modalSelectedWeek = null;
            renderModalWeekSelector();
            await loadPlayerBonnen();
        }

        async function changeModalWeek(direction) {
            const currentYear = modalSelectedYear || <?= date('o') ?>;
            const currentWeek = modalSelectedWeek || <?= date('W') ?>;

            const currentIndex = modalAllWeeks.findIndex(w => w.year === currentYear && w.week === currentWeek);
            if (currentIndex === -1) return;

            const newIndex = currentIndex + direction;
            if (newIndex >= 0 && newIndex < modalAllWeeks.length) {
                const newWeek = modalAllWeeks[newIndex];
                await selectModalWeek(newWeek.year, newWeek.week);
            }
        }

        async function loadPlayerBonnen() {
            const body = document.getElementById('modalBody');
            body.innerHTML = '<div class="text-center py-12"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500 mx-auto"></div><p class="mt-4 text-gray-500">Bonnen laden...</p></div>';

            try {
                const params = new URLSearchParams();
                params.append('player_id', currentModalPlayerId);
                if (modalSelectedYear) params.append('year', modalSelectedYear);
                if (modalSelectedWeek) params.append('week', modalSelectedWeek);

                const response = await fetch('api/get_player_bonnen.php?' + params);
                const data = await response.json();

                if (data.success) {
                    renderPlayerBonnenCompact(data);
                } else {
                    body.innerHTML = '<p class="text-center text-red-500 py-8">Fout bij laden data: ' + (data.error || 'Onbekende fout') + '</p>';
                }
            } catch (e) {
                console.error('Fetch error:', e);
                body.innerHTML = '<p class="text-center text-red-500 py-8">Netwerkfout bij laden van gegevens</p>';
            }
        }

        function closePlayerModal() {
            document.getElementById('playerModal').classList.add('hidden');
        }

        function renderPlayerBonnenCompact(data) {
            const stats = document.getElementById('modalPlayerStats');
            const body = document.getElementById('modalBody');
            
            const player = data.player;
            const bonnen = data.bonnen || [];
            
            // Update stats
            const totalBet = parseFloat(player.total_bet || 0);
            const totalWin = parseFloat(player.total_winnings || 0);
            const huisSaldo = totalBet - totalWin; // huis: inzet - uitbetaald
            const saldoClass = huisSaldo >= 0 ? 'text-emerald-600' : 'text-red-500';
            
            stats.innerHTML = `${bonnen.length} bonnen • €${totalBet.toFixed(2)} inzet • <span class="${saldoClass}">${huisSaldo >= 0 ? '+' : '−'}€${Math.abs(huisSaldo).toFixed(2)}</span> huisresultaat`;
            
            if (bonnen.length === 0) {
                body.innerHTML = '<p class="text-center text-gray-400 py-12">Nog geen bonnen voor deze speler</p>';
                return;
            }
            
            // Render compact list with headers
            let html = `
                <!-- Column Headers -->
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
                
                <!-- Bonnen List -->
                <div class="space-y-2">
            `;
            
            bonnen.forEach(bon => {
                const bonBet = parseFloat(bon.total_bet);
                const bonWin = parseFloat(bon.total_winnings);
                const bonSaldo = bonBet - bonWin; // huisresultaat
                const saldoClass = bonSaldo >= 0 ? 'positive' : 'negative';
                const saldoSymbol = bonSaldo >= 0 ? '+' : '−';

                // Trekking info label (compact, in de rij zelf)
                let trekkingLabel = '';
                if (bon.trekking_info) {
                    trekkingLabel = `
                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 6px; border-radius: 4px;
                                     background: linear-gradient(135deg, #4A9EFF25, #4A9EFF10);
                                     color: #4A9EFF; border: 1px solid #4A9EFF50;
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
                            <div class="bon-win">€${bonWin.toFixed(2)}</div>
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

        // Bon Detail Modal
        async function openBonDetail(bonId, bonDate) {
            console.log('Opening bon detail:', bonId, bonDate);
            
            const modal = document.getElementById('bonDetailModal');
            const body = document.getElementById('bonDetailBody');
            
            modal.classList.remove('hidden');
            body.innerHTML = '<div class="text-center py-12"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500 mx-auto"></div><p class="mt-4 text-gray-500">Bon laden...</p></div>';
            
            try {
                // Fetch bon details
                console.log('Fetching bon:', `api/get_bon.php?bon_id=${bonId}`);
                const bonResponse = await fetch(`api/get_bon.php?bon_id=${bonId}`);
                const bonData = await bonResponse.json();
                console.log('Bon data:', bonData);
                
                // Fetch winning numbers for that date
                console.log('Fetching winning numbers:', `api/get_winning_numbers.php?date=${bonDate}`);
                const numbersResponse = await fetch(`api/get_winning_numbers.php?date=${bonDate}`);
                const numbersData = await numbersResponse.json();
                console.log('Winning numbers data:', numbersData);
                
                if (bonData.success && numbersData.success) {
                    renderBonDetail(bonData.bon, bonData.rijen, numbersData.numbers);
                } else {
                    const error = bonData.error || numbersData.error || 'Onbekende fout';
                    body.innerHTML = `<p class="text-center text-red-500 py-8">Fout bij laden bon: ${error}</p>`;
                    console.error('Error in response:', bonData, numbersData);
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
            
            console.log('Rendering bon detail:', bon, rijen, winningNumbers);
            
            const totalBet = rijen.reduce((sum, r) => sum + parseFloat(r.bet), 0);
            const totalWin = rijen.reduce((sum, r) => sum + parseFloat(r.winnings), 0);
            const saldo = totalWin - totalBet;
            const saldoColor = saldo >= 0 ? '#2ECC71' : '#EF4444';
            
            // Header section
            let html = `
                <div style="padding: 20px 24px; border-bottom: 1px solid #f0f0f0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                        <h2 style="font-size: 18px; font-weight: 600; color: #1a1a1a; margin: 0;">
                            Bon ${bon.bonnummer || '#' + bon.id}
                        </h2>
                        <span style="font-size: 12px; color: #999; font-weight: 600;">
                            ${formatDate(bon.date)}
                        </span>
                    </div>
                    <p style="font-size: 13px; color: #666; margin: 0;">
                        ${bon.player_name} • ${bon.winkel_naam || 'Geen winkel'}
                    </p>
                </div>

                <div style="padding: 24px;">
                    <!-- Winning Numbers -->
                    <div style="margin-bottom: 24px;">
                        <h3 style="font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 12px 0;">
                            Winnende Nummers
                        </h3>
                        <div style="background: linear-gradient(135deg, #FFF5E6 0%, #FFE6CC 100%); border: 2px solid #FFD699; border-radius: 12px; padding: 14px; display: grid; grid-template-columns: repeat(10, 1fr); gap: 5px;">
            `;
            
            // Winning numbers grid
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

                    <!-- Rijen Section -->
                    <div>
                        <h3 style="font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 12px 0;">
                            Rijen (${rijen.length})
                        </h3>
            `;
            
            // Render each rij
            rijen.forEach((rij, index) => {
                // Handle both array and comma-separated string formats
                let playerNumbers;
                if (Array.isArray(rij.numbers)) {
                    playerNumbers = rij.numbers;
                } else {
                    playerNumbers = rij.numbers.split(',').map(n => parseInt(n.trim()));
                }
                
                const matchCount = playerNumbers.filter(n => winningNumbers.includes(n)).length;
                const hasWin = parseFloat(rij.winnings) > 0;
                const gameType = playerNumbers.length === 7 ? '7-Game' : 
                                playerNumbers.length === 6 ? '6-Game' : 
                                playerNumbers.length === 5 ? '5-Game' : 
                                playerNumbers.length === 4 ? '4-Game' : 
                                `${playerNumbers.length}-Game`;
                
                html += `
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; transition: background 0.15s;">
                        <!-- Left: Number + Type + Numbers -->
                        <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                            <!-- Row number -->
                            <div style="background: #667eea; color: white; width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0;">
                                ${index + 1}
                            </div>
                            
                            <!-- Game type badge -->
                            <div style="background: #e8eaf6; color: #667eea; padding: 4px 9px; border-radius: 6px; font-size: 11px; font-weight: 600; flex-shrink: 0;">
                                ${gameType}
                            </div>
                            
                            <!-- Number chips -->
                            <div style="display: flex; gap: 5px; flex-wrap: wrap; flex: 1;">
                `;
                
                playerNumbers.forEach(num => {
                    const isMatch = winningNumbers.includes(num);
                    html += `
                        <div style="background: ${isMatch ? '#E8F8F0' : 'white'}; border: 1.5px solid ${isMatch ? '#2ECC71' : '#e0e0e0'}; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: ${isMatch ? '#2ECC71' : '#666'}; transition: all 0.15s;">
                            ${num}
                        </div>
                    `;
                });
                
                html += `
                            </div>
                        </div>
                        
                        <!-- Right: Amounts -->
                        <div style="display: flex; gap: 20px; font-size: 13px; min-width: 160px; justify-content: flex-end;">
                            <div style="min-width: 60px; text-align: right; color: #999;">
                                €${parseFloat(rij.bet).toFixed(2)}
                            </div>
                            <div style="min-width: 70px; text-align: right; font-weight: 700; color: ${hasWin ? '#2ECC71' : '#999'};">
                                €${parseFloat(rij.winnings).toFixed(2)}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += `
                    </div>

                    <!-- Summary Section -->
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
                            <span style="font-size: 18px; font-weight: 700; color: ${saldoColor};">
                                ${saldo >= 0 ? '+' : ''}€${Math.abs(saldo).toFixed(2)}
                            </span>
                        </div>
                    </div>
                </div>
            `;
            
            body.innerHTML = html;
        }
    </script>

    <style>
        /* Compact List Styles */
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

        .bon-item-compact:hover {
            background: #f0f0f0;
            transform: translateX(4px);
        }

        .bon-item-left {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .bon-date {
            font-size: 11px;
            font-weight: 600;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-width: 80px;
        }

        .bon-bonnr {
            font-size: 13px;
            font-weight: 600;
            color: #333;
        }

        .bon-rijen {
            font-size: 12px;
            color: #666;
            background: white;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .bon-item-right {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .bon-bet {
            font-size: 13px;
            color: #666;
            min-width: 60px;
            text-align: right;
        }

        .bon-win {
            font-size: 14px;
            font-weight: 700;
            min-width: 70px;
            text-align: right;
            color: #666;
        }

        .bon-saldo {
            font-size: 14px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 6px;
            min-width: 80px;
            text-align: center;
        }

        .bon-saldo.positive {
            background: #D1FAE5;
            color: #059669;
        }

        .bon-saldo.negative {
            background: #FEE2E2;
            color: #DC2626;
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
    </style>

    <!-- Bon Detail Modal -->
    <div id="bonDetailModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[60] flex items-center justify-center p-4" onclick="if(event.target === this) closeBonDetail()">
        <div style="background: white; border-radius: 20px; max-width: 600px; width: 100%; max-height: 90vh; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3); display: flex; flex-direction: column;">
            <!-- Header -->
            <div style="padding: 20px 24px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="font-size: 18px; font-weight: 600; color: #1a1a1a;">Bon Details</h2>
                <button onclick="closeBonDetail()" style="background: #f5f5f5; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 18px; color: #666;">✕</button>
            </div>
            
            <!-- Body -->
            <div id="bonDetailBody" style="flex: 1; overflow-y: auto;">
                <!-- Dynamic content -->
            </div>
        </div>
    </div>

    <!-- Player Detail Modal -->
    <div id="playerModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4" onclick="if(event.target === this) closePlayerModal()">
        <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden shadow-2xl">
            <!-- Modal Header -->
            <div class="sticky top-0 bg-white border-b border-gray-200 p-6 z-10">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div id="modalPlayerAvatar" class="w-12 h-12 rounded-full flex items-center justify-center text-white text-lg font-bold"></div>
                        <div>
                            <h2 id="modalPlayerName" class="text-2xl font-bold text-gray-900"></h2>
                            <p id="modalPlayerStats" class="text-sm text-gray-500"></p>
                        </div>
                    </div>
                    <button onclick="closePlayerModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Week Selector in Modal -->
            <div class="bg-gray-50 border-b border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <button onclick="changeModalWeek(-1)" class="px-3 py-1.5 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition text-xs font-medium">
                        ← Vorige
                    </button>

                    <div id="modal-week-selector-container" class="flex-1 overflow-x-auto">
                        <div id="modal-week-selector" class="flex gap-2 py-1">
                            <!-- Weeks will be loaded here via JavaScript -->
                        </div>
                    </div>

                    <button onclick="resetModalWeekFilter()" class="px-3 py-1.5 text-xs font-medium transition bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Toon Alles
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div id="modalBody" class="p-6 overflow-y-auto" style="max-height: calc(90vh - 220px);">
                <!-- Dynamic content loaded here -->
            </div>
        </div>
    </div>

</body>
</html>
