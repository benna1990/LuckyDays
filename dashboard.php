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

// Bepaal default datum op basis van 19:00 regel
if (!isset($_GET['date'])) {
    $tz = new DateTimeZone('Europe/Amsterdam');
    $now = new DateTimeImmutable('now', $tz);
    $currentHour = (int)$now->format('H');
    $today = $now->format('Y-m-d');

    // Na 19:00: gebruik vandaag
    // Voor 19:00: gebruik gisteren
    if ($currentHour >= 19) {
        $defaultDate = $today;
    } else {
        $defaultDate = $now->modify('-1 day')->format('Y-m-d');
    }

    header('Location: dashboard.php?date=' . $defaultDate);
    exit();
}

$selected_date = $_GET['date'];

// Compacte initial range: 1 maand beide kanten
// Meer wordt dynamisch geladen via infinite scroll
$start_date = date('Y-m-d', strtotime('-1 month', strtotime($selected_date)));
$end_date = date('Y-m-d', strtotime('+1 month', strtotime($selected_date)));

// Generate date range
$date_range = [];
$current = strtotime($start_date);
$end = strtotime($end_date);
while ($current <= $end) {
    $date_range[] = date('Y-m-d', $current);
    $current = strtotime('+1 day', $current);
}

// Groepeer dagen per ISO week
$weeks = [];
foreach ($date_range as $date) {
    $weekNum = date('W', strtotime($date));
    $year = date('o', strtotime($date)); // ISO year
    $weekKey = $year . '-W' . str_pad($weekNum, 2, '0', STR_PAD_LEFT);

    if (!isset($weeks[$weekKey])) {
        $weeks[$weekKey] = [
            'label' => 'Week ' . $weekNum,
            'year' => $year,
            'week' => $weekNum,
            'dates' => []
        ];
    }
    $weeks[$weekKey]['dates'][] = $date;
}

// Check which days have winning numbers for green border
$daysWithWinningNumbers = [];
foreach ($date_range as $dateInRange) {
    $winningNumbersForDay = getWinningNumbersFromDatabase($dateInRange, $conn);
    if ($winningNumbersForDay && !empty($winningNumbersForDay)) {
        $daysWithWinningNumbers[] = $dateInRange;
    }
}

// Winkel selectie - altijd uit sessie
$winkels = getAllWinkels($conn);
$selectedWinkel = $_SESSION['selected_winkel'] ?? null; // null = "Alles"

$activeWinkelTheme = resolveActiveWinkelTheme($winkels, $selectedWinkel);
$winkelPalette = getWinkelPalette();

// Helper function to convert hex to RGB
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "$r, $g, $b";
}

// ✅ NIEUWE PATTERNS: Repository + Money Pattern
$lotteryRepo = new LotteryRepository($conn);

$bonnen = $lotteryRepo->getBonnenByDate($selected_date, $selectedWinkel);
$dayStats = $lotteryRepo->getDayStats($selected_date, $selectedWinkel);
$allPlayers = getAllPlayers($conn, $selectedWinkel);

$winningData = getWinningNumbersFromDatabase($selected_date, $conn);
$hasWinningNumbers = !empty($winningData);

// Timezone consistent
$tz = new DateTimeZone('Europe/Amsterdam');
$now = new DateTimeImmutable('now', $tz);
$currentHour = (int)$now->format('H');
$today = $now->format('Y-m-d');

// Functie om laatste geldige uitslag te vinden (20 nummers)
$latestValidResult = null;
for ($i = 0; $i <= 7; $i++) {
    $checkDate = $now->modify("-$i days")->format('Y-m-d');
    $nums = getWinningNumbersFromDatabase($checkDate, $conn);
    
    // Valideer dat we exact 20 nummers hebben
    if ($nums && count($nums) === 20) {
        $latestValidResult = [
            'date' => $checkDate,
            'numbers' => $nums,
            'valid' => true
        ];
        break;
    }
}

// Bepaal button state
$buttonState = 'unavailable';
$buttonDate = null;
$buttonText = 'Geen uitslag beschikbaar';
$buttonHint = '';

if ($currentHour >= 19) {
    // Na 19:00: check of vandaag geldig is
    $todayNums = getWinningNumbersFromDatabase($today, $conn);
    if ($todayNums && count($todayNums) === 20) {
        $buttonState = 'today-available';
        $buttonDate = $today;
        $buttonText = 'Nieuwste uitslag';
        $buttonHint = 'Vandaag';
    } elseif ($latestValidResult) {
        $buttonState = 'latest-available';
        $buttonDate = $latestValidResult['date'];
        $buttonText = 'Laatste uitslag';
        $buttonHint = getDayAndAbbreviatedMonth($buttonDate);
    }
} else {
    // Voor 19:00: gebruik laatste geldige
    if ($latestValidResult) {
        $buttonState = 'latest-available';
        $buttonDate = $latestValidResult['date'];
        $buttonText = 'Laatste uitslag';
        $buttonHint = getDayAndAbbreviatedMonth($buttonDate);
    }
}

// Error detectie voor huidige pagina
$pageHasValidData = ($hasWinningNumbers && count($winningData) === 20);
$isBeforePublishTime = ($selected_date === $today && $currentHour < 19);
$isFutureDate = (strtotime($selected_date) > strtotime($today));
$showDataWarning = !$pageHasValidData && !$isFutureDate && !$isBeforePublishTime;
$noResultsYet = !$hasWinningNumbers && !$isBeforePublishTime && !$isFutureDate && !$showDataWarning;

// Bepaal welke dagen bonnen hebben
$daysWithBonnen = [];
if ($selectedWinkel !== null) {
    // Specifieke winkel: check bonnen voor deze winkel
    $bonnenQuery = pg_query_params($conn, 
        "SELECT DISTINCT DATE(date) as day FROM bons WHERE winkel_id = $1 AND DATE(date) BETWEEN $2 AND $3",
        [$selectedWinkel, $date_range[0], $date_range[count($date_range)-1]]
    );
    while ($row = pg_fetch_assoc($bonnenQuery)) {
        $daysWithBonnen[] = $row['day'];
    }
} else {
    // "Alles": check of er bonnen zijn van welke winkel dan ook
    $bonnenQuery = pg_query_params($conn, 
        "SELECT DISTINCT DATE(date) as day FROM bons WHERE DATE(date) BETWEEN $1 AND $2",
        [$date_range[0], $date_range[count($date_range)-1]]
    );
    while ($row = pg_fetch_assoc($bonnenQuery)) {
        $daysWithBonnen[] = $row['day'];
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lucky Day - Dagoverzicht</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/design-system.css?v=3.2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Hybrid Dagselector - v2.0 - Cache Bust */
        body { 
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            overflow-y: scroll;
            min-height: 100vh;
            background-color: #F8F9FA;
        }
        .card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 12px rgba(0,0,0,0.04); }
        .card-hover:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1), 0 8px 24px rgba(0,0,0,0.08); transform: translateY(-1px); }
        
        .modal-overlay { backdrop-filter: blur(4px); }
        .popup-input { font-size: 24px; text-align: center; letter-spacing: 2px; }
        .number-chip { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; font-weight: 600; font-size: 14px; position: relative; cursor: pointer; transition: all 0.15s; }
        .number-chip:hover { transform: scale(1.05); }
        .number-chip .delete-x { position: absolute; top: -4px; right: -4px; width: 16px; height: 16px; background: #ef4444; color: white; border-radius: 50%; display: none; align-items: center; justify-content: center; font-size: 10px; font-weight: bold; cursor: pointer; border: 2px solid white; z-index: 10; }
        .number-chip:hover .delete-x { display: flex; }
        .number-chip.match { background: #d1fae5; color: #065f46; }
        .number-chip.no-match { background: #f3f4f6; color: #6b7280; }
        .number-chip.pending { background: #fef3c7; color: #92400e; }
        span[data-rij]:hover .delete-x { display: flex !important; }
        .player-suggestion { transition: background 0.1s; }
        .player-suggestion:hover, .player-suggestion.selected { background: #f0fdf4; }
        .scraper-status { font-size: 12px; padding: 4px 8px; border-radius: 6px; }
        .scraper-status.loading { background: #fef3c7; color: #92400e; }
        .scraper-status.error { background: #fee2e2; color: #991b1b; }
        .scraper-status.success { background: #d1fae5; color: #065f46; }
        .fade-in { animation: fadeIn 0.2s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

        /* v1-1 Compact List Styles - Exact copy from design-showcase.php */
        .v1-section-title {
            font-size: 11px;
            font-weight: 700;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        .winning-numbers-box {
            background: linear-gradient(135deg, #FFF5E6 0%, #FFE6CC 100%);
            border: 2px solid #FFD699;
            border-radius: 12px;
            padding: 16px;
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 6px;
            margin-bottom: 24px;
        }
        .number-badge {
            background: white;
            border: 1px solid #FFD699;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
            color: #D97706;
        }
        .rij-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.15s;
        }
        .rij-row:last-child { border-bottom: none; }
        .rij-row:hover { background: #f9f9f9; }
        .rij-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }
        .rij-number {
            background: #667eea;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
        }
        .rij-type {
            background: #e8eaf6;
            color: #667eea;
            padding: 4px 9px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            flex-shrink: 0;
        }
        .rij-numbers {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            flex: 1;
        }
        .rij-number-badge {
            background: white;
            border: 1.5px solid #e0e0e0;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            color: #666;
            transition: all 0.15s;
            cursor: pointer;
        }
        .rij-number-badge.match {
            border-color: #2ECC71;
            background: #E8F8F0;
            color: #2ECC71;
        }
        .rij-amounts {
            display: flex;
            gap: 20px;
            font-size: 13px;
            min-width: 160px;
            justify-content: flex-end;
        }
        .rij-amount {
            min-width: 60px;
            text-align: right;
        }
        .rij-amount.bet {
            color: #999;
        }
        .rij-amount.result {
            font-weight: 700;
            min-width: 70px;
        }
        .rij-amount.result.negative {
            color: #EF4444;
        }
        .rij-amount.result.positive {
            color: #2ECC71;
        }
        .summary-section {
            background: #FAFAFA;
            border: 1px solid #e8e8e8;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
            color: #666;
        }
        .summary-row.total {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 2px solid #e0e0e0;
            font-size: 16px;
            font-weight: 700;
        }
        .summary-row .value {
            font-weight: 700;
        }
        .summary-row .value.positive {
            color: #2ECC71;
        }
        .summary-row .value.negative {
            color: #EF4444;
        }
        .summary-note {
            text-align: center;
            font-size: 12px;
            color: #999;
            margin-top: 8px;
        }

        .container-fixed {
            max-width: 1280px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 1rem;
            padding-right: 1rem;
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
        
        /* Modal Styling met winkelkleur */
        .modal-winkel-container {
            background: white;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .modal-winkel-header {
            padding: 20px 24px;
            border-bottom: 2px solid <?= $activeWinkelTheme['accent'] ?>15;
            background: <?= $activeWinkelTheme['accent'] ?>06;
        }
        .modal-winkel-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: <?= $activeWinkelTheme['accent'] ?>;
            margin: 0 0 4px 0;
        }
        .modal-winkel-header p {
            font-size: 14px;
            color: #6B7280;
            margin: 0;
        }
        .modal-winkel-body {
            padding: 24px;
        }
        .winkel-option {
            padding: 16px 20px;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            background: white;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s;
            cursor: pointer;
            margin-bottom: 8px;
        }
        .winkel-option:hover {
            transform: translateX(4px);
        }
        .winkel-option .name {
            font-size: 16px;
            font-weight: 600;
            color: #374151;
        }
        
        @media (max-width: 768px) {
            .hide-on-mobile { display: none; }
        }
    </style>
</head>
<body>
    <?php include 'components/main_nav.php'; ?>

    <?php include 'components/old_data_warning.php'; ?>

    <?php include 'components/winkel_bar.php'; ?>

    <main class="container-fixed py-4 sm:py-6">
        <div class="mb-6">
            <!-- Hybrid Compact Date Selector -->
            <div class="date-selector-fullwidth" style="--date-accent-color: <?= $activeWinkelTheme['accent'] ?>; --date-active-bg: <?= $activeWinkelTheme['accent'] ?>; --date-active-border: <?= $activeWinkelTheme['accent'] ?>; --date-active-shadow: rgba(<?= hexToRgb($activeWinkelTheme['accent']) ?>, 0.35);">
                
                <!-- Compact header met alle controls op één rij -->
                <div class="date-selector-header">
                    <!-- Title -->
                    <h2 class="date-selector-title">Dagoverzicht</h2>
                    
                    <!-- Days Section -->
                    <div class="date-scroll-container">
                        <button type="button" class="btn-icon" onclick="scrollDateTrack('left')" title="Scroll naar links">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        
                        <div class="date-track" id="dateTrack">
                            <div class="date-track-inner">
                                <?php 
                                $weekIndex = 0;
                                foreach ($weeks as $weekKey => $week): 
                                    if ($weekIndex > 0): ?>
                                        <div class="week-separator"></div>
                                    <?php endif; ?>
                                    
                                    <div class="week-group">
                                        <span class="week-label">Week <?= $week['week'] ?> <span class="week-year">'<?= substr($week['year'], 2) ?></span></span>
                                        <?php foreach ($week['dates'] as $date): 
                                            $isSelected = ($date === $selected_date);
                                            $hasWinning = in_array($date, $daysWithWinningNumbers);
                                            $hasBonnen = in_array($date, $daysWithBonnen);
                                            
                                            // Parse date
                                            $dt = new DateTime($date);
                                            $dayOfWeek = (int)$dt->format('N'); // 1 (monday) to 7 (sunday)
                                            $dayNumber = $dt->format('j'); // 21, 22, etc.
                                            $monthNum = (int)$dt->format('n'); // 1-12
                                            
                                            // Dutch dag namen (kort)
                                            $dutchDays = ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];
                                            $dayNameDutch = $dutchDays[$dayOfWeek - 1];
                                            
                                            // Dutch maand namen (kort)
                                            $dutchMonths = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
                                            $monthNameDutch = $dutchMonths[$monthNum - 1];
                                        ?>
                                            <button type="button"
                                               data-date="<?= $date ?>"
                                               class="date-btn <?= $isSelected ? 'active' : '' ?>"
                                               <?= $isSelected ? 'id="selectedDay"' : '' ?>
                                               onclick="changeDateSmooth('<?= $date ?>')">
                                                <span class="day-name"><?= $dayNameDutch ?></span>
                                                <span class="day-number"><?= $dayNumber ?></span>
                                                <span class="month-name"><?= $monthNameDutch ?></span>
                                                
                                                <?php if ($hasWinning && $hasBonnen): ?>
                                                    <div class="indicator-container">
                                                        <div class="indicator-line thick"></div>
                                                    </div>
                                                <?php elseif ($hasWinning && !$hasBonnen): ?>
                                                    <div class="indicator-container">
                                                        <div class="indicator-line thin"></div>
                                                    </div>
                                                <?php endif; ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <?php $weekIndex++; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <button type="button" class="btn-icon" onclick="scrollDateTrack('right')" title="Scroll naar rechts">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="date-quick-actions">
                        <?php if ($buttonState !== 'unavailable'): ?>
                            <button type="button"
                               onclick="goToNewestDate('<?= $buttonDate ?>')"
                               class="btn-secondary btn-newest"
                               <?= $buttonState === 'today-available' 
                                   ? 'style="color: ' . $activeWinkelTheme['accent'] . '; border-color: ' . $activeWinkelTheme['accent'] . ';"' 
                                   : '' ?>
                               title="<?= $buttonState === 'today-available' ? 'Ga naar nieuwste uitslag' : 'Ga naar laatste beschikbare uitslag' ?>">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                                <span>Nieuwste</span>
                            </button>
                        <?php endif; ?>
                    <input type="date" id="date-picker" value="<?= $selected_date ?>"
                               class="px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:border-transparent cursor-pointer transition date-picker-input"
                               style="--tw-ring-color: <?= $activeWinkelTheme['accent'] ?>;"
                           onchange="changeDateSmooth(this.value)">
                </div>
            </div>
                
                <!-- Legenda -->
                <div class="date-legend">
                    <div class="legend-item">
                        <div class="legend-indicator thin"></div>
                        <span>Dunne lijn = nummers opgehaald</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-indicator thick"></div>
                        <span>Dikke lijn = bonnen toegevoegd</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="lg:col-span-2 card p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-gray-800" data-bonnen-date-header>Bonnen van <?= getDayAndAbbreviatedMonth($selected_date) ?></h2>
                    <?php if ($selectedWinkel !== null): ?>
                        <button type="button" data-trigger="new-bon" 
                                class="btn-primary"
                                style="--btn-bg: <?= $activeWinkelTheme['accent'] ?>; --btn-shadow: 0 2px 8px <?= $activeWinkelTheme['accent'] ?>30; --btn-hover-shadow: 0 4px 12px <?= $activeWinkelTheme['accent'] ?>40;">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>Nieuwe bon</span>
                            <span class="text-xs opacity-80"><?= htmlspecialchars($activeWinkelTheme['naam']) ?></span>
                    </button>
                    <?php else: ?>
                        <button type="button" data-trigger="new-bon" class="btn-primary neutral">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>Nieuwe bon</span>
                        </button>
                    <?php endif; ?>
                </div>

                <div data-bonnen-container>
                <?php if (empty($bonnen) || $bonnen === false): ?>
                    <div class="text-center py-12 text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p class="text-sm">Nog geen bonnen voor deze dag</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-gray-500 border-b border-gray-100 uppercase tracking-wide">
                                    <th class="pb-3 font-medium">Datum</th>
                                    <th class="pb-3 font-medium">Bonnummer</th>
                                    <th class="pb-3 font-medium text-right">Rijen</th>
                                    <th class="pb-3 font-medium text-right">Inzet</th>
                                    <th class="pb-3 font-medium text-right">Uitbetaald</th>
                                    <th class="pb-3 font-medium text-right">Huisresultaat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            <?php foreach ($bonnen as $bon):
                                $bonWinnings = floatval($bon['total_winnings']);
                                $bonBet = floatval($bon['total_bet']);
                                $huisSaldo = $bonBet - $bonWinnings;
                            ?>
                                <tr class="hover:bg-gray-50 cursor-pointer" data-bon-id="<?= $bon['id'] ?>">
                                    <td class="py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-medium flex-shrink-0" style="background: <?= htmlspecialchars($bon['player_color']) ?>">
                                                <?= strtoupper(mb_substr($bon['winkel_name'], 0, 1)) ?>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-800 truncate"><?= htmlspecialchars(getDayAndAbbreviatedMonth($selected_date)) ?></div>
                                                <div class="text-xs text-gray-500 truncate"><?= htmlspecialchars($bon['player_name']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 text-gray-800">
                                        <?= !empty($bon['bonnummer']) ? htmlspecialchars($bon['bonnummer']) : '—' ?>
                                    </td>
                                    <td class="py-3 text-right text-gray-600"><?= $bon['rijen_count'] ?></td>
                                    <td class="py-3 text-right text-gray-900">€<?= number_format($bonBet, 2, ',', '.') ?></td>
                                    <td class="py-3 text-right <?= $bonWinnings > 0 ? 'text-red-500' : 'text-gray-500' ?>">
                                        <?= $bonWinnings > 0 ? '€' . number_format($bonWinnings, 2, ',', '.') : '—' ?>
                                    </td>
                                    <td class="py-3 text-right font-semibold <?= $huisSaldo > 0 ? 'text-emerald-600' : ($huisSaldo < 0 ? 'text-red-500' : 'text-gray-600') ?>">
                                        <?= $huisSaldo > 0 ? '↑' : ($huisSaldo < 0 ? '↓' : '→') ?> €<?= number_format(abs($huisSaldo), 2, ',', '.') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            </div>

            <div class="space-y-6">
                <?php
                // Check for old data (> 60 days)
                $oldDataCount = checkOldData($conn, 60, $selectedWinkel);
                if ($oldDataCount > 0):
                ?>
                <div class="card p-4 border-2 border-amber-200 bg-amber-50">
                    <div class="flex items-start gap-3">
                        <div class="text-2xl">⚠️</div>
                        <div class="flex-1">
                            <h4 class="text-sm font-semibold text-amber-900 mb-1">Oude Data Gevonden</h4>
                            <p class="text-xs text-amber-700 mb-3">Er zijn <?= $oldDataCount ?> bonnen ouder dan 60 dagen voor deze winkel.</p>
                            <button onclick="showDataCleanupModal()" class="text-xs px-3 py-1.5 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition">
                                Opschonen
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="card p-6">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-4">Dagtotaal</h3>
                    <?php
                    $inzetOntvangen = floatval($dayStats['total_bet']);
                    $uitbetaaldAanSpelers = floatval($dayStats['total_winnings']);
                    $commissie = $inzetOntvangen * 0.30;
                    $huispot = $inzetOntvangen * 0.70;
                    $nettoHuis = $huispot - $uitbetaaldAanSpelers;
                    ?>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Bonnen</span>
                            <span class="font-medium"><?= $dayStats['total_bons'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Spelers</span>
                            <span class="font-medium"><?= $dayStats['total_players'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Rijen</span>
                            <span class="font-medium"><?= $dayStats['total_rijen'] ?></span>
                        </div>
                        <?php if ($dayStats['total_bons'] > 0): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Inzet</span>
                            <span class="font-medium text-emerald-600">€<?= number_format($inzetOntvangen, 2, ',', '.') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Commissie (30%)</span>
                            <span class="font-medium text-amber-600">€<?= number_format($commissie, 2, ',', '.') ?></span>
                        </div>
                        <hr class="border-gray-100">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Huispot (70%)</span>
                            <span class="font-medium text-emerald-600">€<?= number_format($huispot, 2, ',', '.') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Uitbetaling</span>
                            <span class="font-medium text-red-500">€<?= number_format($uitbetaaldAanSpelers, 2, ',', '.') ?></span>
                        </div>
                        <hr class="border-gray-100">
                        <div class="flex justify-between">
                            <span class="font-semibold text-gray-900">Netto huis</span>
                            <span class="font-bold text-lg <?= $nettoHuis >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                                <?= $nettoHuis >= 0 ? '+' : '–' ?>€<?= number_format(abs($nettoHuis), 2, ',', '.') ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($showDataWarning && $buttonDate): ?>
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-3 mb-4">
                        <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div class="flex-1">
                            <h4 class="text-sm font-semibold text-amber-900 mb-1">Incomplete data voor deze datum</h4>
                            <p class="text-xs text-amber-700 mb-2">De winnende nummers zijn mogelijk nog niet volledig beschikbaar.</p>
                            <a href="?date=<?= $buttonDate ?>" 
                               class="inline-flex items-center gap-1 text-xs font-medium text-amber-800 hover:text-amber-900 underline">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                                Ga naar laatste geldige uitslag (<?= getDayAndAbbreviatedMonth($buttonDate) ?>)
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($isBeforePublishTime): ?>
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="flex-1">
                            <h3 class="text-sm font-semibold text-amber-900 mb-1">Uitslag nog niet beschikbaar</h3>
                            <p class="text-xs text-amber-700">De winnende nummers worden dagelijks om 19:00 gepubliceerd.</p>
                        </div>
                    </div>
                <?php elseif ($isFutureDate): ?>
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl flex items-start gap-3">
                        <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="flex-1">
                            <h3 class="text-sm font-semibold text-blue-900 mb-1">Datum in de toekomst</h3>
                            <p class="text-xs text-blue-700">Deze datum heeft nog geen winnende nummers.</p>
                        </div>
                    </div>
                <?php elseif ($noResultsYet): ?>
                    <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl flex items-start gap-3">
                        <svg class="w-5 h-5 text-gray-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                        <div class="flex-1">
                            <h3 class="text-sm font-semibold text-gray-900 mb-1">Geen uitslag gevonden</h3>
                            <p class="text-xs text-gray-700">De winnende nummers zijn nog niet beschikbaar voor deze datum.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Winnende nummers</h3>
                        <div class="flex items-center gap-2">
                            <span id="scraper-status" class="scraper-status hidden"></span>
                            <?php if ($hasWinningNumbers): ?>
                            <button onclick="editWinningNumbers()" class="text-xs text-blue-600 hover:text-blue-700 font-medium">
                                Wijzig
                            </button>
                            <?php endif; ?>
                            <button onclick="fetchWinningNumbers()" id="fetch-btn" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">
                                Ophalen
                            </button>
                        </div>
                    </div>
                    <div id="winning-numbers-container">
                        <?php if ($hasWinningNumbers): ?>
                            <div class="space-y-1.5">
                                <div class="grid grid-cols-10 gap-1">
                                    <?php for ($i = 0; $i < 10 && $i < count($winningData); $i++): ?>
                                        <span class="w-7 h-7 flex items-center justify-center text-xs font-medium rounded-md"
                                              style="background: <?= $activeWinkelTheme['accent'] ?>15; color: <?= $activeWinkelTheme['accent'] ?>; border: 1px solid <?= $activeWinkelTheme['accent'] ?>40;">
                                            <?= $winningData[$i] ?>
                                        </span>
                                    <?php endfor; ?>
                                </div>
                                <div class="grid grid-cols-10 gap-1">
                                    <?php for ($i = 10; $i < 20 && $i < count($winningData); $i++): ?>
                                        <span class="w-7 h-7 flex items-center justify-center text-xs font-medium rounded-md"
                                              style="background: <?= $activeWinkelTheme['accent'] ?>15; color: <?= $activeWinkelTheme['accent'] ?>; border: 1px solid <?= $activeWinkelTheme['accent'] ?>40;">
                                            <?= $winningData[$i] ?>
                                        </span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <p class="text-sm font-medium text-gray-900 mb-1">Geen winnende nummers</p>
                                <p class="text-xs text-gray-500">Nog niet beschikbaar voor <?= getDayAndAbbreviatedMonth($selected_date) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- Data Cleanup Modal -->
    <div id="data-cleanup-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[60]" style="backdrop-filter: blur(4px);" onclick="if(event.target === this) closeDataCleanupModal()">
        <div class="modal-winkel-container fade-in">
            <div class="modal-winkel-header">
                <div style="text-align: center; font-size: 48px; margin-bottom: 12px;">🗑️</div>
                <h3 style="text-align: center;">Data Opschonen</h3>
                <p style="text-align: center; color: #6B7280;">Dit verwijdert alle bonnen en rijen ouder dan 60 dagen voor de geselecteerde winkel.</p>
            </div>
            
            <div class="modal-winkel-body">
            
                <div style="background: #FFFBEB; border: 2px solid #FCD34D; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                    <div style="display: flex; align-items: flex-start; gap: 8px;">
                        <span style="font-size: 20px;">⚠️</span>
                        <div>
                            <p style="font-size: 14px; font-weight: 600; color: #78350F; margin-bottom: 4px;">Let op!</p>
                            <p style="font-size: 12px; color: #92400E;">Deze actie kan niet ongedaan worden gemaakt. Zorg dat je een backup hebt gemaakt als je de data nog nodig hebt.</p>
                        </div>
                    </div>
                </div>
                
                <div style="font-size: 14px; color: #374151; margin-bottom: 24px;">
                    <p style="margin-bottom: 8px;"><strong>Winkel:</strong> <?php 
                        $currentWinkelData = array_filter($winkels, fn($w) => $w['id'] == $selectedWinkel);
                        echo htmlspecialchars(reset($currentWinkelData)['naam'] ?? 'Onbekend');
                    ?></p>
                    <p><strong>Te verwijderen:</strong> <span id="cleanup-count"><?= $oldDataCount ?></span> bonnen</p>
                </div>
                
                <div class="modal-footer">
                    <button onclick="closeDataCleanupModal()" class="btn-secondary">
                        Annuleren
                    </button>
                    <button onclick="confirmDataCleanup()" class="btn-destructive">
                        Verwijderen
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Winkel Select Modal (voor "Alles" -> Nieuwe bon) -->
    <div id="winkel-select-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[60]" style="backdrop-filter: blur(4px);" onclick="if(event.target === this) closeWinkelSelectModal()">
        <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-lg fade-in" style="border: 1px solid #E5E7EB;">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Selecteer winkel</h3>
                    <p class="text-sm text-gray-500">Kies de winkel voor deze bon</p>
                </div>
                <button onclick="closeWinkelSelectModal()" class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                <?php foreach ($winkels as $winkel): 
                    $theme = getWinkelThemeByName($winkel['naam']);
                    $accent = $theme['accent'] ?? '#2563EB';
                ?>
                    <button onclick="selectWinkelAndCreateBon(<?= $winkel['id'] ?>, '<?= addslashes($winkel['naam']) ?>')"
                            class="w-full text-left border border-gray-200 rounded-lg px-4 py-3 hover:border-gray-300 hover:bg-gray-50 transition flex items-center justify-between"
                            style="color: <?= $accent ?>;">
                        <span class="font-medium" style="color: <?= $accent ?>;"><?= htmlspecialchars($winkel['naam']) ?></span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: <?= $accent ?>;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                <?php endforeach; ?>
                <button onclick="selectWinkelAndCreateBon(null, 'Niet gekoppeld')"
                        class="w-full text-left border border-gray-200 rounded-lg px-4 py-3 hover:border-gray-400 hover:bg-gray-50 transition flex flex-col">
                    <span class="font-medium text-gray-800">Niet gekoppeld</span>
                    <span class="text-xs text-gray-500">Algemene bon</span>
                </button>
            </div>
            <div class="flex justify-end">
                <button onclick="closeWinkelSelectModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                    Annuleren
                </button>
            </div>
        </div>
    </div>

    <!-- Edit nummer modal -->
    <div id="edit-number-modal" class="fixed inset-0 bg-black/30 hidden items-center justify-center z-[60]" onclick="if(event.target === this) closeEditNumberModal()">
        <div class="bg-white rounded-xl shadow-2xl p-6 mx-4 w-full max-w-sm fade-in">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Nummer wijzigen</h3>
            <input type="number" id="edit-number-input" min="1" max="80"
                   class="w-full px-4 py-3 text-2xl text-center border-2 border-gray-200 rounded-lg focus:ring-2 focus:border-transparent outline-none transition"
                   style="--tw-ring-color: <?= $activeWinkelTheme['accent'] ?>;"
                   placeholder="1-80">
            <div class="flex gap-2 mt-4">
                <button onclick="closeEditNumberModal()" class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                    Annuleren
                </button>
                <button onclick="saveEditedNumber()" class="flex-1 px-4 py-2 text-sm font-medium text-white rounded-lg transition"
                        style="background: <?= $activeWinkelTheme['accent'] ?>;"
                        onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                    Opslaan
                </button>
            </div>
        </div>
    </div>

    <!-- Edit winning numbers modal -->
    <div id="edit-winning-modal" class="fixed inset-0 bg-black/30 hidden items-center justify-center z-[60]" onclick="if(event.target === this) closeEditWinningModal()">
        <div class="bg-white rounded-xl shadow-2xl p-6 mx-4 w-full max-w-md fade-in">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Winnende Nummers Wijzigen</h3>
            <p class="text-sm text-gray-500 mb-4">Voer 20 nummers in, gescheiden door komma's, spaties of enters</p>
            <textarea id="edit-winning-input" rows="8"
                   class="w-full px-4 py-3 text-base border-2 border-gray-200 rounded-lg focus:ring-2 focus:border-transparent outline-none transition"
                   style="--tw-ring-color: <?= $activeWinkelTheme['accent'] ?>;"
                   placeholder="1, 4, 5, 12, 14, 17, 21, 26, 27, 32, 35, 39, 53, 54, 56, 58, 59, 62, 70, 72"></textarea>
            <div class="flex gap-2 mt-4">
                <button onclick="closeEditWinningModal()" class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                    Annuleren
                </button>
                <button onclick="saveWinningNumbers()" class="flex-1 px-4 py-2 text-sm font-medium text-white rounded-lg transition"
                        style="background: <?= $activeWinkelTheme['accent'] ?>;"
                        onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                    Opslaan
                </button>
            </div>
        </div>
    </div>

    <div id="popup-overlay" class="fixed inset-0 bg-black/50 modal-overlay hidden items-center justify-center z-50">
        <div id="popup-content" class="bg-white rounded-2xl shadow-xl w-full max-w-3xl mx-4 p-6 fade-in max-h-[90vh] overflow-y-auto">
            <div id="name-popup" class="hidden">
                <div class="text-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Nieuwe Bon</h3>
                    <p class="text-sm text-gray-500 mt-1">Vul details in</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Spelernaam</label>
                    <input type="text" id="name-input" class="w-full px-4 py-3 bg-gray-50 border-0 rounded-xl focus:ring-2 focus:outline-none transition"
                           style="--tw-ring-color: <?= $activeWinkelTheme['accent'] ?>;"
                           placeholder="Naam..." autocomplete="off">
                    <div id="player-suggestions" class="mt-2 max-h-48 overflow-y-auto hidden"></div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Bonnummer (optioneel)</label>
                    <input type="text" id="bon-nummer-input" class="w-full px-4 py-3 bg-gray-50 border-0 rounded-xl focus:ring-2 focus:outline-none transition"
                           style="--tw-ring-color: <?= $activeWinkelTheme['accent'] ?>;"
                           placeholder="0 = geen bonnummer">
                    <p class="text-xs text-gray-500 mt-1">Voer 0 in of laat leeg voor geen bonnummer</p>
                </div>

                <button onclick="confirmBonDetails()" class="w-full px-4 py-3 text-white font-medium rounded-xl transition"
                        style="background: <?= $activeWinkelTheme['accent'] ?>;"
                        onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                    Doorgaan naar Nummers
                </button>

                <p class="text-xs text-gray-400 text-center mt-4">Enter = doorgaan · Esc = sluiten</p>
            </div>

            <div id="number-popup" class="hidden">
                <div class="text-center mb-3">
                    <h3 class="text-lg font-semibold text-gray-800" id="popup-player-name"></h3>
                </div>
                
                <div class="flex justify-end mb-3">
                    <button id="bon-log-btn" class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-200 transition">
                        <span>📄</span> Logboek
                    </button>
                </div>
                <div id="bon-log-panel" class="hidden mb-4 bg-gray-50 border border-gray-200 rounded-lg p-3 text-sm max-h-40 overflow-y-auto"></div>
                
                <div id="popup-winning-numbers" class="hidden">
                    <h3 class="v1-section-title">Winnende nummers</h3>
                    <div id="popup-winning-display" class="winning-numbers-box"></div>
                </div>
                
                <div id="saved-rows-container" class="mb-4 space-y-2 hidden"></div>
                
                <div id="recent-sets-container" class="mb-3"></div>
                
                <div class="bg-gray-50 rounded-xl p-4 mb-4">
                    <p class="text-sm text-gray-500 text-center mb-2">Rij <span id="current-row-num">1</span></p>
                    <div id="current-numbers" class="flex flex-wrap gap-2 justify-center min-h-[44px] mb-3"></div>
                    <div class="flex gap-2">
                        <input type="text" id="number-input" class="flex-1 px-4 py-4 text-2xl text-center bg-white border border-gray-200 rounded-xl focus:ring-2 focus:border-transparent focus:outline-none popup-input transition"
                               style="--tw-ring-color: <?= $activeWinkelTheme['accent'] ?>;"
                               placeholder="Nummer..." autocomplete="off" inputmode="numeric" enterkeyhint="done">
                        <button type="button" id="number-ok-btn" class="px-6 py-4 text-white text-xl font-semibold rounded-xl transition-colors"
                                style="background: <?= $activeWinkelTheme['accent'] ?>;"
                                onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">OK</button>
                    </div>
                </div>
                <p class="text-xs text-gray-400 text-center">OK/Enter = toevoegen · 0 = naar inzet</p>
            </div>

            <div id="bet-popup" class="hidden">
                <div class="text-center mb-3">
                    <h3 class="text-lg font-semibold text-gray-800" id="bet-player-name"></h3>
                </div>
                
                <div id="bet-winning-numbers" class="hidden">
                    <h3 class="v1-section-title">Winnende nummers</h3>
                    <div id="bet-winning-display" class="winning-numbers-box"></div>
                </div>
                
                <div id="bet-saved-rows" class="mb-4 space-y-2 hidden"></div>
                
                <div class="bg-gray-50 rounded-xl p-4 mb-4">
                    <p class="text-sm text-gray-500 text-center mb-2">Inzet voor rij <span id="bet-row-num">1</span></p>
                    <div id="bet-numbers-display" class="flex flex-wrap gap-1.5 justify-center mb-3"></div>
                    <div class="flex gap-2">
                        <input type="text" id="bet-input" class="flex-1 px-4 py-4 text-2xl text-center bg-white border border-gray-200 rounded-xl focus:ring-2 focus:border-transparent focus:outline-none popup-input transition"
                               style="--tw-ring-color: <?= $activeWinkelTheme['accent'] ?>;"
                               placeholder="1.00" autocomplete="off" inputmode="decimal" enterkeyhint="done">
                        <button type="button" id="bet-ok-btn" class="px-6 py-4 text-white text-xl font-semibold rounded-xl transition-colors"
                                style="background: <?= $activeWinkelTheme['accent'] ?>;"
                                onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">OK</button>
                    </div>
                </div>
                <p class="text-xs text-gray-400 text-center">OK/Enter = opslaan en volgende rij</p>
            </div>

            <!-- Aantal Trekkingen Popup -->
            <div id="trekkingen-popup" class="hidden">
                <div class="text-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800" id="trekkingen-player-name"></h3>
                </div>

                <div class="bg-gray-50 rounded-xl p-6 mb-4">
                    <p class="text-sm text-gray-500 text-center mb-4">Voor hoeveel trekkingen geldt deze bon?</p>
                    <p class="text-xs text-gray-400 text-center mb-4">0 of 1 = 1 trekking · 2 = vandaag + morgen · 3 = vandaag + morgen + overmorgen</p>

                    <div class="flex gap-2">
                        <input type="text" id="trekkingen-input" class="flex-1 px-4 py-4 text-2xl text-center bg-white border border-gray-200 rounded-xl focus:ring-2 focus:border-transparent focus:outline-none popup-input transition"
                               style="--tw-ring-color: <?= $activeWinkelTheme['accent'] ?>;"
                               placeholder="1" autocomplete="off" inputmode="numeric" enterkeyhint="done" maxlength="1">
                        <button type="button" id="trekkingen-ok-btn" class="px-6 py-4 text-white text-xl font-semibold rounded-xl transition-colors"
                                style="background: <?= $activeWinkelTheme['accent'] ?>;"
                                onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">OK</button>
                    </div>
                </div>

                <div id="trekkingen-saved-rows" class="mb-4 space-y-2"></div>

                <p class="text-xs text-gray-400 text-center">Enter = voltooien</p>
            </div>

            <div id="bon-detail-popup" class="hidden">
                <div id="bon-detail-content"></div>
            </div>
        </div>
    </div>

    <script>
        let selectedDate = '<?= $selected_date ?>';
        const selectedWinkelId = <?= $selectedWinkel === null ? 'null' : (int)$selectedWinkel ?>;
        const selectedWinkelName = <?= $selectedWinkel === null ? "'Alles'" : "'" . addslashes($activeWinkelTheme['naam']) . "'" ?>;
        const allWinkels = <?= json_encode(array_values(array_map(function($w){ return ['id'=>(int)$w['id'],'naam'=>$w['naam']]; }, $winkels))) ?>;
        let activeBonWinkelId = selectedWinkelId;
        let activeBonWinkelName = selectedWinkelName;
        const allPlayers = <?= json_encode($allPlayers ?: []) ?>;
        let winningNumbers = <?= json_encode($winningData ? array_map('intval', $winningData) : []) ?>;
        const shopAccentColor = '<?= $activeWinkelTheme['accent'] ?>';
        
        // Nieuwe multipliers matrix (max 7 nummers)
        const multipliers = {
            1: {1: 2},
            2: {2: 8},
            3: {3: 25, 2: 1},
            4: {4: 60, 3: 8, 2: 2},
            5: {5: 300, 4: 9, 3: 2},
            6: {6: 1300, 5: 30, 4: 6, 3: 1},
            7: {7: 2500, 6: 80, 5: 15, 4: 2}
        };
        
        let currentBonId = null;
        let currentPlayerId = null;
        let currentPlayerName = '';
        let currentNumbers = [];
        let currentRowNum = 1;
        let savedRows = [];
        let scraperRetryInterval = null;
        
        // Recent sets storage (LocalStorage)
        let recentSets = JSON.parse(localStorage.getItem('luckyDaysRecentSets') || '{}');
        
        function saveRecentSet(playerId, numbers) {
            if (!recentSets[playerId]) recentSets[playerId] = [];
            
            const setStr = numbers.sort((a,b) => a-b).join(',');
            recentSets[playerId] = recentSets[playerId].filter(s => s !== setStr);
            recentSets[playerId].unshift(setStr);
            recentSets[playerId] = recentSets[playerId].slice(0, 10);
            
            localStorage.setItem('luckyDaysRecentSets', JSON.stringify(recentSets));
        }
        
        function showRecentSets() {
            if (!currentPlayerId || !recentSets[currentPlayerId] || recentSets[currentPlayerId].length === 0) {
                document.getElementById('recent-sets-container').innerHTML = '';
                return;
            }
            
            const container = document.getElementById('recent-sets-container');
            let html = '<div class="mb-3"><p class="text-xs font-medium text-gray-600 mb-2">💾 Recent gespeeld:</p>';
            html += '<div class="flex flex-wrap gap-2">';
            
            recentSets[currentPlayerId].slice(0, 5).forEach(setStr => {
                const nums = setStr.split(',');
                html += `<button onclick="useRecentSet('${setStr}')" class="px-2 py-1 text-xs bg-blue-50 text-blue-700 rounded hover:bg-blue-100 border border-blue-200 transition">
                    ${nums.join(', ')}
                </button>`;
            });
            
            html += '</div></div>';
            container.innerHTML = html;
        }
        
        function useRecentSet(setStr) {
            currentNumbers = setStr.split(',').map(n => parseInt(n));
            renderCurrentNumbers();
            document.getElementById('number-input').focus();
        }
        
        function calculateWinnings(numbers, bet) {
            if (winningNumbers.length === 0) return { matches: 0, multiplier: 0, winnings: 0 };
            const matches = numbers.filter(n => winningNumbers.includes(n)).length;
            const gameType = numbers.length;
            const mult = multipliers[gameType]?.[matches] || 0;
            return { matches, multiplier: mult, winnings: bet * mult };
        }
        
        function renderWinningNumbersInPopup(containerId) {
            const container = document.getElementById(containerId);
            const display = document.getElementById(containerId.replace('-numbers', '-display'));
            if (winningNumbers.length > 0) {
                container.classList.remove('hidden');
                // v1-1 style: single row grid with number-badge class
                let html = '';
                winningNumbers.forEach(n => {
                    html += `<div class="number-badge">${n}</div>`;
                });
                display.innerHTML = html;
            } else {
                container.classList.add('hidden');
            }
        }
        
        function renderSavedRows(containerId) {
            const container = document.getElementById(containerId);
            if (savedRows.length === 0) {
                container.classList.add('hidden');
                return;
            }
            container.classList.remove('hidden');

            const hasPending = winningNumbers.length === 0;
            let totalBet = 0;
            let totalWinnings = 0;

            // v1-1 Compact List Layout
            const rowsHtml = savedRows.map((row, i) => {
                totalBet += row.bet;
                const result = calculateWinnings(row.numbers, row.bet);
                totalWinnings += result.winnings;
                // SPELER LOGICA: winnings - inzet
                const spelerSaldo = result.winnings - row.bet;

                return `
                    <div class="rij-row" data-row-index="${i}">
                        <div class="rij-left">
                            <div class="rij-number">${i + 1}</div>
                            <div class="rij-type">${row.numbers.length}-getallen</div>
                            <div class="rij-numbers">
                                ${row.numbers.map(n => {
                                    const isMatch = winningNumbers.includes(n);
                                    return `<div class="rij-number-badge ${isMatch ? 'match' : ''}">${n}</div>`;
                                }).join('')}
                            </div>
                        </div>
                        <div class="rij-amounts flex flex-col sm:flex-row sm:items-center sm:gap-3 text-sm">
                            <span class="rij-amount bet">Inzet: €${row.bet.toFixed(2).replace('.', ',')}</span>
                            ${hasPending
                                ? '<span class="rij-amount result" style="color: #F59E0B;">Prijs: pending</span>'
                                : `<span class="rij-amount result" style="color:#10B981;">Prijs: €${result.winnings.toFixed(2).replace('.', ',')}</span>`
                            }
                            ${hasPending
                                ? '<span class="rij-amount result" style="color: #F59E0B;">Netto: pending</span>'
                                : `<span class="rij-amount result ${spelerSaldo >= 0 ? 'positive' : 'negative'}">Netto: ${spelerSaldo >= 0 ? '+' : ''}€${spelerSaldo.toFixed(2).replace('.', ',')}</span>`
                            }
                        </div>
                    </div>
                `;
            }).join('');

            // HET HUIS LOGICA: inzet - winnings
            const totalHuisSaldo = totalBet - totalWinnings;

            const fullHtml = `
                <h3 class="v1-section-title">Rijen (${savedRows.length}/10)</h3>
                <div class="mb-4">
                    ${rowsHtml}
                </div>
                <div class="summary-section">
                    <h3 class="v1-section-title">Samenvatting</h3>
                    <div class="summary-row">
                        <span>Inzet ontvangen</span>
                        <span class="value positive">+€${totalBet.toFixed(2).replace('.', ',')}</span>
                    </div>
                    <div class="summary-row">
                        <span>Uitbetaald</span>
                        <span class="value negative">−€${totalWinnings.toFixed(2).replace('.', ',')}</span>
                    </div>
                    <div class="summary-row total">
                        <span>Resultaat (Het Huis)</span>
                        ${hasPending
                            ? '<span class="value" style="color: #F59E0B;">In afwachting</span>'
                            : `<span class="value ${totalHuisSaldo >= 0 ? 'positive' : 'negative'}">${totalHuisSaldo >= 0 ? '+' : ''}€${totalHuisSaldo.toFixed(2).replace('.', ',')}</span>`
                        }
                    </div>
                    ${!hasPending ? `
                        <div class="summary-note">
                            ${totalHuisSaldo < 0 ? 'Te betalen' : 'Ontvangen'}
                        </div>
                    ` : ''}
                </div>
            `;

            container.innerHTML = fullHtml;
        }

        function togglePlayers() {
            const list = document.getElementById('players-list');
            const chevron = document.getElementById('players-chevron');
            list.classList.toggle('hidden');
            chevron.classList.toggle('rotate-180');
        }

        function showPopup(popupId) {
            document.getElementById('popup-overlay').classList.remove('hidden');
            document.getElementById('popup-overlay').classList.add('flex');
            document.querySelectorAll('#popup-content > div').forEach(el => el.classList.add('hidden'));
            document.getElementById(popupId).classList.remove('hidden');
        }

        function hidePopup() {
            document.getElementById('popup-overlay').classList.add('hidden');
            document.getElementById('popup-overlay').classList.remove('flex');
        }

        let popupBonId = null;
        let popupBon = null;
        let popupTotals = null;
        let popupRijen = [];
        let popupOriginalRijen = [];
        let popupWinNums = [];
        let popupHasPending = false;
        let popupSaving = false;
        let popupIsDirty = false;
        let popupTrekkingInfo = null;

        async function openBonPopup(bonId) {
            try {
                const response = await fetch(`api/get_bon.php?id=${bonId}`);
                const data = await response.json();
                
                if (!data.success) {
                    alert(data.error || 'Kon bon niet laden');
                    return;
                }
                
                popupBonId = bonId;
                popupBon = data.bon;
                popupTotals = data.totals;
                popupRijen = data.rijen.map(r => ({...r, numbers: [...r.numbers]}));
                popupOriginalRijen = data.rijen.map(r => ({...r, numbers: [...r.numbers]}));
                popupWinNums = data.winning_numbers;
                popupHasPending = popupWinNums.length === 0;
                popupIsDirty = false;
                popupTrekkingInfo = data.trekking_info || null;

                renderBonPopupContent();
                showPopup('bon-detail-popup');
                
            } catch (error) {
                alert('Fout bij laden bon');
                console.error(error);
            }
        }

        function renderBonPopupContent() {
            const bon = popupBon;
            const totals = popupTotals;

            // Tabelweergave per rij: Datum/Bonnummer-achtig layout vervangen door Rij/Numbers/Inzet/Uitbetaald/Huisresultaat
            let rijenHtml = `
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-100 uppercase tracking-wide">
                                <th class="pb-3 font-medium">Rij</th>
                                <th class="pb-3 font-medium">Nummers</th>
                                <th class="pb-3 font-medium text-right">Inzet</th>
                                <th class="pb-3 font-medium text-right">Uitbetaald</th>
                                <th class="pb-3 font-medium text-right">Huisresultaat</th>
                                <th class="pb-3 font-medium text-center">Actie</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            ${popupRijen.map((rij, i) => {
                                const huisSaldo = rij.bet - rij.winnings;
                                return `
                                    <tr data-rij-index="${i}" class="hover:bg-gray-50">
                                        <td class="py-3 font-medium text-gray-800">${i + 1}</td>
                                        <td class="py-3">
                                            <div class="flex flex-wrap gap-1">
                                                ${rij.numbers.map((n, j) => {
                                                    const isMatch = popupWinNums.includes(n);
                                                    return `<span class="rij-number-badge ${isMatch ? 'match' : ''}" onclick="openEditNumberPopup(${i}, ${j}, ${n})">${n}</span>`;
                                                }).join('')}
                                            </div>
                                        </td>
                                        <td class="py-3 text-right text-gray-900">€${rij.bet.toFixed(2).replace('.', ',')}</td>
                                        <td class="py-3 text-right ${rij.winnings > 0 ? 'text-red-500' : 'text-gray-500'}">${rij.winnings > 0 ? '€' + rij.winnings.toFixed(2).replace('.', ',') : '—'}</td>
                                        <td class="py-3 text-right font-semibold ${huisSaldo > 0 ? 'text-emerald-600' : (huisSaldo < 0 ? 'text-red-500' : 'text-gray-600')}">
                                            ${huisSaldo > 0 ? '↑' : (huisSaldo < 0 ? '↓' : '→')} €${Math.abs(huisSaldo).toFixed(2).replace('.', ',')}
                                        </td>
                                        <td class="py-3 text-center">
                                            <button onclick="deleteRijFromPopup(${rij.id})" class="text-gray-400 hover:text-red-500 transition" title="Verwijderen" style="width: 20px; height: 20px; flex-shrink: 0;">
                                                <svg class="w-3.5 h-3.5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            `;

            // HET HUIS LOGICA: inzet - winnings
            const saldo = totals.bet - totals.winnings;
            const hasChanges = checkForChanges();
            
            const content = `
                ${popupTrekkingInfo ? `
                    <div class="mb-4 p-3 rounded-lg" style="background: linear-gradient(135deg, ${bon.player_color}15, ${bon.player_color}05); border-left: 4px solid ${bon.player_color};">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" style="color: ${bon.player_color};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <div class="flex-1">
                                <p class="text-sm font-semibold" style="color: ${bon.player_color};">Herhalende Bon (${popupTrekkingInfo.aantal_trekkingen} trekkingen)</p>
                                <p class="text-xs text-gray-600">Van ${formatDate(popupTrekkingInfo.start_datum)} t/m ${formatDate(popupTrekkingInfo.eind_datum)}</p>
                            </div>
                        </div>
                    </div>
                ` : ''}

                <div class="flex items-center gap-4 mb-4 pb-4 border-b border-gray-200">
                    <div class="w-14 h-14 rounded-full flex items-center justify-center text-white text-xl font-bold flex-shrink-0" style="background: ${bon.player_color}">
                        ${bon.winkel_name ? bon.winkel_name.charAt(0).toUpperCase() : '?'}
                    </div>
                    <div class="flex-1">
                        <h3 class="text-xl font-bold text-gray-800">${escapeHtml(bon.player_name)}</h3>
                        <p class="text-sm text-gray-500">Bon #${bon.id} · ${popupRijen.length} rij${popupRijen.length !== 1 ? 'en' : ''}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <select onchange="moveBonToWinkel(${bon.id}, this.value)" class="px-3 py-1.5 text-xs rounded-lg border border-gray-200 bg-white text-gray-700">
                            <option value="">Verplaats...</option>
                            ${window.winkelOptions || ''}
                        </select>
                    </div>
                </div>

                ${popupWinNums.length > 0 ? `
                    <h3 class="v1-section-title">Winnende nummers</h3>
                    <div class="winning-numbers-box">
                        ${popupWinNums.map(n => `<div class="number-badge">${n}</div>`).join('')}
                    </div>
                ` : ''}

                <div class="mb-4">
                    <h3 class="v1-section-title">Rijen (${popupRijen.length}/10)</h3>
                    <div id="popup-rijen-container">
                        ${rijenHtml || '<p class="text-center text-gray-400 py-8">Geen rijen</p>'}
                    </div>
                    <div id="new-rij-form-container"></div>
                    <button onclick="showInlineRijForm()" id="add-rij-btn" class="w-full mt-3 py-2 px-4 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 rounded-lg transition flex items-center justify-center gap-2 font-medium text-sm border-2 border-dashed border-emerald-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Rij Toevoegen
                    </button>
                </div>

                <div class="summary-section">
                    <h3 class="v1-section-title">Samenvatting</h3>
                    <div class="summary-row">
                        <span>Inzet ontvangen</span>
                        <span class="value positive">+€${totals.bet.toFixed(2).replace('.', ',')}</span>
                    </div>
                    <div class="summary-row">
                        <span>Uitbetaald</span>
                        <span class="value negative">−€${totals.winnings.toFixed(2).replace('.', ',')}</span>
                    </div>
                    <div class="summary-row total">
                        <span>Resultaat (Het Huis)</span>
                        ${popupHasPending
                            ? '<span class="value" style="color: #F59E0B;">In afwachting</span>'
                            : `<span class="value ${saldo >= 0 ? 'positive' : 'negative'}">${saldo >= 0 ? '+' : ''}€${saldo.toFixed(2).replace('.', ',')}</span>`
                        }
                    </div>
                    ${!popupHasPending ? `
                        <div class="summary-note">
                            ${saldo < 0 ? 'Te betalen' : 'Ontvangen'}
                        </div>
                    ` : ''}
                </div>

                <div class="flex gap-2 mt-4">
                    <button onclick="deleteBonFromPopup(${bon.id})" class="px-4 py-3 text-sm font-medium text-red-600 hover:text-red-700 transition">
                        <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Verwijderen
                    </button>
                    ${hasChanges
                        ? `<button onclick="savePopupChanges()" class="flex-1 py-3 text-sm font-medium text-white bg-emerald-500 hover:bg-emerald-600 rounded-xl transition">
                            💾 Opslaan
                        </button>`
                        : `<button onclick="hidePopup()" class="flex-1 py-3 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-xl transition">
                            Gereed
                        </button>`
                    }
                </div>
            `;
            
            document.getElementById('bon-detail-content').innerHTML = content;
        }

        function checkForChanges() {
            for (let i = 0; i < popupRijen.length; i++) {
                const current = popupRijen[i].numbers;
                const original = popupOriginalRijen[i].numbers;
                if (current.length !== original.length) return true;
                for (let j = 0; j < current.length; j++) {
                    if (current[j] !== original[j]) return true;
                }
            }
            return false;
        }

        let editingRijIndex = null;
        let editingNumIndex = null;

        function editNumber(rijIndex, numIndex) {
            editingRijIndex = rijIndex;
            editingNumIndex = numIndex;
            const currentNum = popupRijen[rijIndex].numbers[numIndex];

            const modal = document.getElementById('edit-number-modal');
            const input = document.getElementById('edit-number-input');

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            input.value = currentNum;
            input.focus();
            input.select();

            // Enter key support
            input.onkeydown = (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveEditedNumber();
                } else if (e.key === 'Escape') {
                    closeEditNumberModal();
                }
            };
        }

        function showDataCleanupModal() {
            const modal = document.getElementById('data-cleanup-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        
        function closeDataCleanupModal() {
            const modal = document.getElementById('data-cleanup-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        
        async function confirmDataCleanup() {
            if (!confirm('Weet je zeker dat je alle data ouder dan 60 dagen wilt verwijderen? Dit kan niet ongedaan worden gemaakt.')) {
                return;
            }
            
            try {
                const response = await fetch('api/cleanup_old_data.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        days: 60,
                        winkel_id: selectedWinkelId
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    alert('Data succesvol verwijderd');
                    location.reload();
                } else {
                    alert(data.error || 'Fout bij verwijderen');
                }
            } catch (e) {
                alert('Fout bij verwijderen');
                console.error(e);
            }
        }

        function closeEditNumberModal() {
            const modal = document.getElementById('edit-number-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            editingRijIndex = null;
            editingNumIndex = null;
        }
        
        function openWinkelSelectModal() {
            const modal = document.getElementById('winkel-select-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        
        function closeWinkelSelectModal() {
            const modal = document.getElementById('winkel-select-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        
        async function selectWinkelAndCreateBon(winkelId, winkelNaam = '') {
            closeWinkelSelectModal();
            if (winkelId === null) {
                alert('Functionaliteit voor niet-gekoppelde bonnen komt binnenkort');
                return;
            }
            try {
                // Zet sessie-winkel, daarna navigeer en open nieuw-bon flow via param
                await fetch('api/set_winkel.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ winkel_id: winkelId })
                });
            } catch (e) {
                console.error('Kon winkel niet instellen voor nieuwe bon', e);
            }
            const params = new URLSearchParams(window.location.search);
            params.set('date', selectedDate);
            params.set('winkel', winkelId);
            params.set('open_new_bon', '1');
            window.location.search = params.toString();
        }

        function saveEditedNumber() {
            const input = document.getElementById('edit-number-input');
            const parsed = parseInt(input.value);

            // Validatie: Tussen 1 en 80
            if (isNaN(parsed) || parsed < 1 || parsed > 80) {
                alert('Fout: voer een cijfer tussen de 1 en 80 in');
                return;
            }

            // Validatie: Duplicaat in dezelfde rij (behalve huidige positie)
            const rij = popupRijen[editingRijIndex];
            const isDuplicate = rij.numbers.some((num, idx) => idx !== editingNumIndex && num === parsed);
            if (isDuplicate) {
                alert('Dit nummer is al ingevoerd in deze rij');
                return;
            }

            // Live update
            popupRijen[editingRijIndex].numbers[editingNumIndex] = parsed;
            popupIsDirty = true;

            // Herbereken winnings voor deze rij
            const result = calculateWinningsForRij(rij.numbers, rij.bet, popupWinNums);
            rij.matches = result.matches;
            rij.multiplier = result.multiplier;
            rij.winnings = result.winnings;

            // Update totals
            recalculateTotals();

            closeEditNumberModal();
            renderBonPopupContent();
        }
        
        function deleteNumberFromPopup(rijIndex, numIndex, event) {
            event.stopPropagation();
            popupIsDirty = true;
            
            popupRijen[rijIndex].numbers.splice(numIndex, 1);
            
            // Herbereken winnings voor deze rij
            const rij = popupRijen[rijIndex];
            if (rij.numbers.length > 0) {
                const result = calculateWinningsForRij(rij.numbers, rij.bet, popupWinNums);
                rij.matches = result.matches;
                rij.multiplier = result.multiplier;
                rij.winnings = result.winnings;
                rij.game_type = rij.numbers.length + '-getallen';
            }
            
            // Update totals
            recalculateTotals();
            renderBonPopupContent();
        }

        function calculateWinningsForRij(numbers, bet, winningNums) {
            if (winningNums.length === 0) return { matches: 0, multiplier: 0, winnings: 0 };
            const matches = numbers.filter(n => winningNums.includes(n)).length;
            const gameType = numbers.length;
            const mult = multipliers[gameType]?.[matches] || 0;
            return { matches, multiplier: mult, winnings: bet * mult };
        }

        function recalculateTotals() {
            popupTotals.bet = 0;
            popupTotals.winnings = 0;
            popupRijen.forEach(rij => {
                popupTotals.bet += rij.bet;
                popupTotals.winnings += rij.winnings;
            });
        }

        async function savePopupChanges() {
            if (popupSaving) return;
            
            try {
                const changes = [];
                for (let i = 0; i < popupRijen.length; i++) {
                    const current = popupRijen[i].numbers;
                    const original = popupOriginalRijen[i].numbers;
                    let changed = current.length !== original.length;
                    if (!changed) {
                        for (let j = 0; j < current.length; j++) {
                            if (current[j] !== original[j]) {
                                changed = true;
                                break;
                            }
                        }
                    }
                    if (changed) {
                        changes.push({
                            rij_id: popupRijen[i].id,
                            numbers: current
                        });
                    }
                }
                
                if (changes.length === 0) return;
                
                popupSaving = true;
                renderBonPopupContent();
                
                const response = await fetch('api/update_rij_numbers.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ changes })
                });
                
                const data = await response.json();
                if (data.success) {
                    const refreshResponse = await fetch(`api/get_bon.php?id=${popupBonId}`);
                    const refreshData = await refreshResponse.json();
                    
                    if (refreshData.success) {
                        popupBon = refreshData.bon;
                        popupTotals = refreshData.totals;
                        popupRijen = refreshData.rijen.map(r => ({...r, numbers: [...r.numbers]}));
                        popupOriginalRijen = refreshData.rijen.map(r => ({...r, numbers: [...r.numbers]}));
                        popupWinNums = refreshData.winning_numbers;
                        popupHasPending = popupWinNums.length === 0;
                    } else {
                        location.reload();
                        return;
                    }
                } else {
                    alert(data.error || 'Fout bij opslaan');
                }
                
                popupSaving = false;
                renderBonPopupContent();
                
            } catch (e) {
                popupSaving = false;
                alert('Fout bij opslaan');
                console.error(e);
            }
        }

        async function deleteRijFromPopup(rijId) {
            if (!confirm('Rij verwijderen?')) return;
            
            popupIsDirty = true;
            
            try {
                const formData = new FormData();
                formData.append('rij_id', rijId);
                
                const response = await fetch('api/delete_rij.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Fout bij verwijderen');
                }
            } catch (e) {
                alert('Fout bij verwijderen');
            }
        }

        async function addNewRijToExistingBon() {
            // Reset huidige nummers en ga naar nummer invoer
            currentNumbers = [];
            currentBonId = popupBonId;
            currentPlayerId = popupBon.player_id;
            currentPlayerName = popupBon.player_name;
            currentRowNum = popupRijen.length + 1;

            // Sluit bon popup en open nummer popup
            closePopup();
            startNumberEntry();
        }

        // ===== INLINE EDITING FUNCTIES =====

        let isAddingNewRij = false;

        function showInlineRijForm() {
            if (isAddingNewRij) return;
            isAddingNewRij = true;

            const container = document.getElementById('new-rij-form-container');
            const addBtn = document.getElementById('add-rij-btn');
            addBtn.classList.add('hidden');

            container.innerHTML = `
                <div class="mt-3 p-4 bg-gradient-to-br from-emerald-50 to-blue-50 rounded-xl border-2 border-emerald-200">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <h4 class="text-sm font-semibold text-emerald-700">Nieuwe Rij Toevoegen</h4>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Nummers (gescheiden door komma's)</label>
                            <input
                                type="text"
                                id="inline-numbers-input"
                                placeholder="Bijv: 5, 12, 23, 34, 45"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                onkeydown="if(event.key==='Enter') saveInlineRij(); if(event.key==='Escape') cancelInlineRij();"
                            />
                            <p class="text-xs text-gray-500 mt-1">💡 Tip: Type nummers tussen 1-80, gescheiden door komma's</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Inzet (€)</label>
                            <input
                                type="number"
                                id="inline-bet-input"
                                placeholder="2.00"
                                step="0.01"
                                min="0.50"
                                value="2.00"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                onkeydown="if(event.key==='Enter') saveInlineRij(); if(event.key==='Escape') cancelInlineRij();"
                            />
                        </div>

                        <div class="flex gap-2">
                            <button
                                onclick="saveInlineRij()"
                                class="flex-1 py-2 px-4 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition font-medium text-sm flex items-center justify-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Opslaan
                            </button>
                            <button
                                onclick="cancelInlineRij()"
                                class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition font-medium text-sm"
                            >
                                Annuleren
                            </button>
                        </div>
                    </div>
                </div>
            `;

            // Focus op input
            setTimeout(() => {
                document.getElementById('inline-numbers-input').focus();
            }, 100);
        }

        function cancelInlineRij() {
            isAddingNewRij = false;
            document.getElementById('new-rij-form-container').innerHTML = '';
            document.getElementById('add-rij-btn').classList.remove('hidden');
        }

        async function saveInlineRij() {
            const numbersInput = document.getElementById('inline-numbers-input').value.trim();
            const betInput = parseFloat(document.getElementById('inline-bet-input').value);

            // Validatie: nummers
            if (!numbersInput) {
                alert('Voer minimaal 1 nummer in');
                return;
            }

            // Parse nummers
            const numbers = numbersInput
                .split(/[,\s]+/)
                .map(n => parseInt(n.trim()))
                .filter(n => !isNaN(n) && n >= 1 && n <= 80);

            if (numbers.length === 0) {
                alert('Geen geldige nummers gevonden (1-80)');
                return;
            }

            if (numbers.length > 7) {
                alert('Maximaal 7 nummers toegestaan');
                return;
            }

            // Check duplicaten
            const unique = [...new Set(numbers)];
            if (unique.length !== numbers.length) {
                alert('Dubbele nummers niet toegestaan');
                return;
            }

            // Validatie: inzet
            if (isNaN(betInput) || betInput < 0.50) {
                alert('Inzet moet minimaal €0,50 zijn');
                return;
            }

            // Sorteer nummers
            const sortedNumbers = numbers.sort((a, b) => a - b);

            // Bereken winnings (als winning numbers bekend zijn)
            const result = calculateWinnings(sortedNumbers, betInput);

            try {
                // Verstuur naar server
                const response = await fetch('api/add_rij.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        bon_id: popupBonId,
                        numbers: sortedNumbers.join(','),
                        bet: betInput
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    alert(data.error || 'Fout bij toevoegen rij');
                    return;
                }

                // Voeg toe aan popup state
                popupRijen.push({
                    id: data.rij_id,
                    numbers: sortedNumbers,
                    bet: betInput,
                    winnings: result.winnings,
                    matches: result.matches,
                    multiplier: result.multiplier
                });

                // Update totals
                popupTotals.bet += betInput;
                popupTotals.winnings += result.winnings;

                // Re-render popup
                renderBonPopupContent();

                // Reset form
                cancelInlineRij();

            } catch (error) {
                console.error('Error saving rij:', error);
                alert('Fout bij opslaan rij');
            }
        }

        // ===== INLINE NUMMER BEWERKEN =====

        let editingNumber = null; // {rijIndex, numberIndex, originalValue}

        function openEditNumberPopup(rijIndex, numberIndex, currentNumber) {
            if (editingNumber) return; // Al aan het bewerken

            editingNumber = { rijIndex, numberIndex, originalValue: currentNumber };

            // Vind het nummer badge element
            const rijRow = document.querySelector(`[data-rij-index="${rijIndex}"]`);
            const numberBadges = rijRow.querySelectorAll('.rij-number-badge');
            const targetBadge = numberBadges[numberIndex];

            // Vervang badge door input
            const currentHTML = targetBadge.outerHTML;
            const isMatch = targetBadge.classList.contains('match');

            targetBadge.outerHTML = `
                <input
                    type="number"
                    id="edit-number-input"
                    value="${currentNumber}"
                    min="1"
                    max="80"
                    class="rij-number-badge editing"
                    style="width: 32px; text-align: center; padding: 4px 2px; border: 2px solid #10B981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);"
                    onkeydown="handleEditNumberKeydown(event, ${rijIndex}, ${numberIndex})"
                    onblur="cancelEditNumber()"
                />
            `;

            // Focus en selecteer
            setTimeout(() => {
                const input = document.getElementById('edit-number-input');
                if (input) {
                    input.focus();
                    input.select();
                }
            }, 50);
        }

        function handleEditNumberKeydown(event, rijIndex, numberIndex) {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveEditedNumber(rijIndex, numberIndex);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                cancelEditNumber();
            }
        }

        function cancelEditNumber() {
            if (!editingNumber) return;

            // Re-render om terug te keren naar normale view
            renderBonPopupContent();
            editingNumber = null;
        }

        async function saveEditedNumber(rijIndex, numberIndex) {
            const input = document.getElementById('edit-number-input');
            if (!input) return;

            const newNumber = parseInt(input.value);

            // Validatie
            if (isNaN(newNumber) || newNumber < 1 || newNumber > 80) {
                alert('Nummer moet tussen 1 en 80 zijn');
                input.focus();
                return;
            }

            const rij = popupRijen[rijIndex];

            // Check duplicaat binnen dezelfde rij
            if (rij.numbers.some((n, i) => i !== numberIndex && n === newNumber)) {
                alert('Dit nummer staat al in deze rij');
                input.focus();
                return;
            }

            // Als nummer niet veranderd is
            if (newNumber === editingNumber.originalValue) {
                cancelEditNumber();
                return;
            }

            try {
                // Update nummer in array
                const oldNumbers = [...rij.numbers];
                rij.numbers[numberIndex] = newNumber;
                rij.numbers.sort((a, b) => a - b);

                // Herbereken winnings
                const result = calculateWinnings(rij.numbers, rij.bet);
                rij.winnings = result.winnings;
                rij.matches = result.matches;
                rij.multiplier = result.multiplier;

                // Verstuur naar server
                const response = await fetch('api/update_rij_numbers.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        rij_id: rij.id,
                        numbers: rij.numbers.join(',')
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    // Revert
                    rij.numbers = oldNumbers;
                    alert(data.error || 'Fout bij opslaan');
                    cancelEditNumber();
                    return;
                }

                // Update totals
                popupTotals.winnings = popupRijen.reduce((sum, r) => sum + r.winnings, 0);

                // Re-render
                editingNumber = null;
                renderBonPopupContent();

            } catch (error) {
                console.error('Error updating number:', error);
                alert('Fout bij opslaan nummer');
                cancelEditNumber();
            }
        }

        async function deleteBonFromPopup(bonId) {
            if (!confirm('Bon verwijderen? Alle rijen worden ook verwijderd.')) return;
            
            try {
                const formData = new FormData();
                formData.append('bon_id', bonId);
                
                const response = await fetch('api/delete_bon.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Fout bij verwijderen');
                }
            } catch (e) {
                alert('Fout bij verwijderen');
            }
        }

        function startNewBon() {
            if (activeBonWinkelId === null) {
                openWinkelSelectModal();
                return;
            }
            currentBonId = null;
            currentPlayerId = null;
            currentPlayerName = '';
            currentNumbers = [];
            currentRowNum = 1;
            savedRows = [];
            
            showPopup('name-popup');
            const nameInput = document.getElementById('name-input');
            nameInput.value = '';
            nameInput.focus();
            updatePlayerSuggestions('');
        }

        function updatePlayerSuggestions(query) {
            const container = document.getElementById('player-suggestions');
            const q = query.toLowerCase().trim();
            
            if (!q) {
                container.classList.add('hidden');
                return;
            }
            
            const matches = allPlayers.filter(p => p.name.toLowerCase().includes(q));
            
            if (matches.length === 0) {
                container.innerHTML = `<div class="p-3 text-sm text-gray-500 text-center">Nieuwe speler: <strong>${escapeHtml(query)}</strong></div>`;
            } else {
                container.innerHTML = matches.map((p, i) => `
                    <div class="player-suggestion p-3 rounded-lg cursor-pointer flex items-center gap-3 ${i === 0 ? 'selected' : ''}" data-id="${p.id}" data-name="${escapeHtml(p.name)}" onclick="selectPlayer(${p.id}, '${escapeHtml(p.name)}')">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-medium" style="background: ${p.color}">
                            ${p.name.charAt(0).toUpperCase()}
                        </div>
                        <div class="flex-1">
                        <span class="font-medium text-gray-800">${escapeHtml(p.name)}</span>
                            <span class="text-xs text-gray-400 ml-1">#${p.id}</span>
                        </div>
                    </div>
                `).join('');
            }
            
            container.classList.remove('hidden');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}-${month}-${year}`;
        }

        document.getElementById('name-input').addEventListener('input', function() {
            updatePlayerSuggestions(this.value);
        });

        async function selectPlayer(playerId, playerName) {
            if (activeBonWinkelId === null) {
                alert('Selecteer eerst een winkel');
                openWinkelSelectModal();
                return;
            }
            currentPlayerId = playerId;
            currentPlayerName = playerName;
            
            const bonResponse = await fetch('api/create_bon.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ player_id: currentPlayerId, date: selectedDate, winkel_id: activeBonWinkelId })
            });
            const bonData = await bonResponse.json();
            
            if (bonData.success) {
                currentBonId = bonData.id;
                startNumberEntry();
            } else {
                alert(bonData.error || 'Kon bon niet aanmaken');
            }
        }

        document.getElementById('name-input').addEventListener('keydown', async function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const name = this.value.trim();
                
                if (!name) {
                    hidePopup();
                    return;
                }
                
                const existingPlayer = allPlayers.find(p => p.name.toLowerCase() === name.toLowerCase());
                
                if (existingPlayer) {
                    selectPlayer(existingPlayer.id, existingPlayer.name);
                } else {
                    if (activeBonWinkelId === null) {
                        alert('Selecteer eerst een winkel');
                        openWinkelSelectModal();
                        return;
                    }
                    const response = await fetch('api/create_player.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name: name })
                    });
                    const data = await response.json();
                    if (data.success) {
                        currentPlayerId = data.id;
                        currentPlayerName = name;
                        allPlayers.push({ id: data.id, name: name, color: data.color });
                
                const bonResponse = await fetch('api/create_bon.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ player_id: currentPlayerId, date: selectedDate, winkel_id: activeBonWinkelId })
                });
                const bonData = await bonResponse.json();
                
                if (bonData.success) {
                    currentBonId = bonData.id;
                    startNumberEntry();
                } else {
                    alert(bonData.error || 'Kon bon niet aanmaken');
                        }
                    } else {
                        alert(data.error || 'Kon speler niet aanmaken');
                        return;
                    }
                }
            }
        });

        function startNumberEntry() {
            currentNumbers = [];
            showPopup('number-popup');
            document.getElementById('popup-player-name').textContent = currentPlayerName;
            document.getElementById('current-row-num').textContent = currentRowNum;
            renderWinningNumbersInPopup('popup-winning-numbers');
            renderSavedRows('saved-rows-container');
            renderCurrentNumbers();
            showRecentSets();
            const logBtn = document.getElementById('bon-log-btn');
            if (logBtn) {
                logBtn.onclick = () => loadBonLogs();
            }
            const input = document.getElementById('number-input');
            input.value = '';
            input.focus();
        }

        function renderCurrentNumbers() {
            const container = document.getElementById('current-numbers');
            if (currentNumbers.length === 0) {
                container.innerHTML = '<span class="text-sm text-gray-400">Voer nummers in...</span>';
                return;
            }
            
            container.innerHTML = currentNumbers.map((num, index) => {
                const isMatch = winningNumbers.includes(num);
                const chipClass = winningNumbers.length > 0 ? (isMatch ? 'match' : 'no-match') : 'pending';
                return `<span class="number-chip ${chipClass}" onclick="editNumber(${index}, event)">
                    ${num}
                    <span class="delete-x" onclick="deleteNumber(${index}, event)">✕</span>
                </span>`;
            }).join('');
        }
        
        function deleteNumber(index, event) {
            event.stopPropagation();
            currentNumbers.splice(index, 1);
            renderCurrentNumbers();
            document.getElementById('number-input').focus();
        }
        
        function editNumber(index, event) {
            if (event.target.classList.contains('delete-x')) return;
            
            const newNum = prompt('Nieuw nummer (1-80):', currentNumbers[index]);
            if (newNum === null) return;
            
            const parsed = parseInt(newNum);
            if (isNaN(parsed) || parsed < 1 || parsed > 80) {
                alert('Voer een nummer tussen 1 en 80 in');
                return;
            }
            
            if (currentNumbers.includes(parsed) && currentNumbers[index] !== parsed) {
                alert('Dit nummer is al geselecteerd');
                return;
            }
            
            currentNumbers[index] = parsed;
            renderCurrentNumbers();
        }

        function showInputError(message) {
            const input = document.getElementById('number-input');
            const container = input.parentElement;

            // Remove existing error
            const existingError = container.querySelector('.input-error-message');
            if (existingError) existingError.remove();

            // Create error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'input-error-message absolute -bottom-6 left-0 right-0 text-xs text-red-600 font-medium text-center';
            errorDiv.textContent = message;
            container.style.position = 'relative';
            container.appendChild(errorDiv);

            // Add error styling to input
            input.classList.add('border-red-500', 'ring-red-500');

            // Remove after 3 seconds
            setTimeout(() => {
                errorDiv.remove();
                input.classList.remove('border-red-500', 'ring-red-500');
            }, 3000);
        }

        function handleNumberInput() {
            const input = document.getElementById('number-input');
            const val = input.value.trim();

            if (val === '0' || val === '') {
                if (currentNumbers.length > 0) {
                    goToBetEntry();
                }
                return;
            }

            const num = parseInt(val);

            // Validatie: Tussen 1 en 80
            if (isNaN(num) || num < 1 || num > 80) {
                showInputError('Fout: voer een cijfer tussen de 1 en 80 in');
                input.value = '';
                return;
            }

            // Validatie: Duplicaat
            if (currentNumbers.includes(num)) {
                showInputError('Dit nummer is al ingevoerd');
                input.value = '';
                return;
            }

            // Validatie: Maximum 7 nummers
            if (currentNumbers.length >= 7) {
                showInputError('Maximum 7 nummers per rij bereikt');
                input.value = '';
                return;
            }

            currentNumbers.push(num);
            renderCurrentNumbers();
            input.value = '';
            input.focus();

            // Auto naar volgende rij na 7 nummers
            if (currentNumbers.length === 7) {
                setTimeout(() => {
                    goToBetEntry();
                }, 500);
            }
        }

        document.getElementById('number-input').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleNumberInput();
            }
        });
        
        document.getElementById('number-ok-btn').addEventListener('click', handleNumberInput);

        function goToBetEntry() {
            showPopup('bet-popup');
            document.getElementById('bet-player-name').textContent = currentPlayerName;
            document.getElementById('bet-row-num').textContent = currentRowNum;
            renderWinningNumbersInPopup('bet-winning-numbers');
            renderSavedRows('bet-saved-rows');
            
            const display = document.getElementById('bet-numbers-display');
            display.innerHTML = currentNumbers.map(num => {
                const isMatch = winningNumbers.includes(num);
                const chipClass = winningNumbers.length > 0 ? (isMatch ? 'match' : 'no-match') : 'pending';
                return `<span class="number-chip ${chipClass}" style="width:28px;height:28px;font-size:12px;">${num}</span>`;
            }).join('');
            
            const input = document.getElementById('bet-input');
            input.value = '1.00';
            input.focus();
            input.select();
        }

        async function handleBetInput() {
            const input = document.getElementById('bet-input');
            let bet = parseFloat(input.value.replace(',', '.'));
            if (isNaN(bet) || bet < 0.50) bet = 1.00;
            
            const response = await fetch('api/add_rij.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `bon_id=${currentBonId}&numbers=${currentNumbers.join(',')}&bet=${bet}`
            });
            const data = await response.json();
            
            if (data.success) {
                savedRows.push({ numbers: [...currentNumbers], bet: bet });
                
                // Save to recent sets
                if (currentPlayerId && currentNumbers.length > 0) {
                    saveRecentSet(currentPlayerId, [...currentNumbers]);
                }
                
                currentRowNum++;
                currentNumbers = [];
                startNextRow();
            } else {
                alert(data.error || 'Kon rij niet opslaan');
            }
        }

        document.getElementById('bet-input').addEventListener('keydown', async function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleBetInput();
            }
        });
        
        document.getElementById('bet-ok-btn').addEventListener('click', handleBetInput);

        function startNextRow() {
            showPopup('number-popup');
            document.getElementById('current-row-num').textContent = currentRowNum;
            renderWinningNumbersInPopup('popup-winning-numbers');
            renderSavedRows('saved-rows-container');
            renderCurrentNumbers();
            showRecentSets();
            const input = document.getElementById('number-input');
            input.value = '';
            input.focus();
            
            input.removeEventListener('keydown', checkFirstZero);
            input.addEventListener('keydown', checkFirstZero);
        }

        function checkFirstZero(e) {
            if (e.key === 'Enter') {
                const val = this.value.trim();
                if (val === '0' && currentNumbers.length === 0) {
                    e.preventDefault();
                    finishBon();
                    this.removeEventListener('keydown', checkFirstZero);
                }
            }
        }

        function finishBon() {
            hidePopup();
            location.reload();
        }

        // Bon log weergave
        async function loadBonLogs() {
            const panel = document.getElementById('bon-log-panel');
            if (!panel) return;
            if (!currentBonId) {
                panel.innerHTML = '<p class="text-gray-500">Geen bon actief</p>';
                panel.classList.remove('hidden');
                return;
            }
            panel.classList.remove('hidden');
            panel.innerHTML = '<p class="text-gray-500">Laden...</p>';
            try {
                const res = await fetch(`api/get_bon_logs.php?bon_id=${currentBonId}`);
                const data = await res.json();
                if (data.success && Array.isArray(data.logs) && data.logs.length) {
                    panel.innerHTML = data.logs.map(log => {
                        const dt = new Date(log.created_at);
                        const dstr = `${dt.toLocaleDateString('nl-NL')} ${dt.toLocaleTimeString('nl-NL')}`;
                        const details = log.details_parsed || log.details || null;
                        let detailsHtml = '';
                        if (details && typeof details === 'object') {
                            detailsHtml = `<div class="text-xs text-gray-600 break-words">${escapeHtml(JSON.stringify(details))}</div>`;
                        } else if (details) {
                            detailsHtml = `<div class="text-xs text-gray-600 break-words">${escapeHtml(details)}</div>`;
                        }
                        return `<div class="py-1 border-b border-gray-100">
                            <div class="text-xs text-gray-500">${dstr} • ${escapeHtml(log.user_name || 'onbekend')}</div>
                            <div class="text-sm font-medium text-gray-800">${escapeHtml(log.action)}</div>
                            ${detailsHtml}
                        </div>`;
                    }).join('');
                } else {
                    panel.innerHTML = '<p class="text-gray-500">Geen logs gevonden</p>';
                }
            } catch (e) {
                panel.innerHTML = '<p class="text-red-500">Kon log niet laden</p>';
            }
        }

        function showTrekkingenPopup() {
            showPopup('trekkingen-popup');
            document.getElementById('trekkingen-player-name').textContent = currentPlayerName;
            renderSavedRows('trekkingen-saved-rows');
            const input = document.getElementById('trekkingen-input');
            input.value = '1';
            input.focus();
            input.select();
        }

        async function handleTrekkingenInput() {
            const input = document.getElementById('trekkingen-input');
            let aantal = parseInt(input.value);

            // 0 or 1 means 1 drawing
            if (isNaN(aantal) || aantal <= 0) {
                aantal = 1;
            }

            // Maximum 7 drawings
            if (aantal > 7) {
                alert('Maximaal 7 trekkingen toegestaan');
                return;
            }

            // If only 1 drawing, just finish
            if (aantal === 1) {
                hidePopup();
                location.reload();
                return;
            }

            // Duplicate bon for multiple drawings
            try {
                const response = await fetch('api/duplicate_bon.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        bon_id: currentBonId,
                        aantal_trekkingen: aantal
                    })
                });

                const data = await response.json();
                if (data.success) {
                    hidePopup();
                    location.reload();
                } else {
                    alert(data.error || 'Fout bij dupliceren bon');
                }
            } catch (e) {
                console.error('Error duplicating bon:', e);
                alert('Fout bij dupliceren bon');
            }
        }

        document.getElementById('trekkingen-input').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleTrekkingenInput();
            }
        });

        document.getElementById('trekkingen-ok-btn').addEventListener('click', handleTrekkingenInput);

        document.getElementById('popup-overlay').addEventListener('click', function(e) {
            if (e.target === this) {
                if (currentBonId && currentNumbers.length === 0) {
                    finishBon();
                } else {
                    hidePopup();
                }
            }
        });

        let scraperRetrying = false;
        async function fetchWinningNumbers(isRetry = false) {
            const container = document.getElementById('winning-numbers-container');
            const statusEl = document.getElementById('scraper-status');
            const fetchBtn = document.getElementById('fetch-btn');
            
            if (!isRetry) {
                container.innerHTML = '<p class="text-sm text-gray-400">Ophalen...</p>';
            }
            statusEl.classList.remove('hidden', 'error', 'success');
            statusEl.classList.add('loading');
            statusEl.textContent = 'Bezig...';
            fetchBtn.disabled = true;
            
            try {
                const response = await fetch('api/scrape_numbers.php?date=' + selectedDate);
                const data = await response.json();
                
                if (data.success && data.numbers) {
                    statusEl.classList.remove('loading');
                    statusEl.classList.add('success');
                    statusEl.textContent = 'Gelukt!';
                    stopScraperRetry();
                    
                    let html = '<div class="space-y-1.5">';
                    html += '<div class="grid grid-cols-10 gap-1">';
                    data.numbers.slice(0, 10).forEach(num => {
                        html += `<span class="w-7 h-7 flex items-center justify-center text-xs font-medium rounded-md" style="background: ${shopAccentColor}15; color: ${shopAccentColor}; border: 1px solid ${shopAccentColor}40;">${num}</span>`;
                    });
                    html += '</div>';
                    html += '<div class="grid grid-cols-10 gap-1">';
                    data.numbers.slice(10, 20).forEach(num => {
                        html += `<span class="w-7 h-7 flex items-center justify-center text-xs font-medium rounded-md" style="background: ${shopAccentColor}15; color: ${shopAccentColor}; border: 1px solid ${shopAccentColor}40;">${num}</span>`;
                    });
                    html += '</div></div>';
                    container.innerHTML = html;
                    
                    setTimeout(() => location.reload(), 1000);
                } else {
                    statusEl.classList.remove('loading');
                    statusEl.classList.add('error');
                    
                    const errorMsg = data.error || 'Uitslag niet gevonden';
                    statusEl.textContent = errorMsg;
                    
                    if (!isRetry) {
                        container.innerHTML = `<p class="text-sm text-amber-600">${errorMsg}</p>`;
                    }
                    
                    if (data.retry !== false) {
                        startScraperRetry();
                    } else {
                        stopScraperRetry();
                    }
                }
            } catch (e) {
                statusEl.classList.remove('loading');
                statusEl.classList.add('error');
                statusEl.textContent = 'Verbindingsfout';
                
                if (!isRetry) {
                    container.innerHTML = '<p class="text-sm text-amber-600">Kon geen verbinding maken</p>';
                }
                
                startScraperRetry();
            }
            
            fetchBtn.disabled = false;
        }

        function startScraperRetry() {
            if (scraperRetrying) return;
            scraperRetrying = true;
            
            scraperRetryInterval = setInterval(() => {
                fetchWinningNumbers(true);
            }, 10000);
        }

        function stopScraperRetry() {
            scraperRetrying = false;
            if (scraperRetryInterval) {
                clearInterval(scraperRetryInterval);
                scraperRetryInterval = null;
            }
        }

        function editWinningNumbers() {
            const modal = document.getElementById('edit-winning-modal');
            const input = document.getElementById('edit-winning-input');
            
            if (winningNumbers.length > 0) {
                input.value = winningNumbers.join('\n');
            } else {
                input.value = '';
            }
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            input.focus();
        }

        function closeEditWinningModal() {
            const modal = document.getElementById('edit-winning-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        async function saveWinningNumbers() {
            const input = document.getElementById('edit-winning-input');
            const text = input.value;
            
            // Parse numbers from text (supports comma, space, or newline separated)
            const numbers = text.split(/[,\s\n]+/)
                .map(n => parseInt(n.trim()))
                .filter(n => !isNaN(n) && n >= 1 && n <= 80);
            
            if (numbers.length !== 20) {
                alert(`Je moet exact 20 nummers invoeren. Je hebt er ${numbers.length} ingevoerd.`);
                return;
            }
            
            // Check for duplicates
            const unique = [...new Set(numbers)];
            if (unique.length !== 20) {
                alert('Er zitten dubbele nummers in je invoer. Alle nummers moeten uniek zijn.');
                return;
            }
            
            try {
                const response = await fetch('api/save_winning_numbers.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ date: selectedDate, numbers: numbers.sort((a, b) => a - b) })
                });
                
                const data = await response.json();
                if (data.success) {
                    closeEditWinningModal();
                    location.reload();
                } else {
                    alert(data.error || 'Kon winnende nummers niet opslaan');
                }
            } catch (e) {
                alert('Fout bij opslaan');
                console.error(e);
            }
        }

        function bindWinkelButtons() {
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

        function bindBonList() {
            document.querySelectorAll('[data-bon-id]').forEach(card => {
                if (card.dataset.bonBound === 'true') {
                    return;
                }
                card.dataset.bonBound = 'true';
                card.addEventListener('click', () => {
                    const bonId = parseInt(card.dataset.bonId, 10);
                    if (!Number.isNaN(bonId)) {
                        openBonPopup(bonId);
                    }
                });
            });
        }

        function bindNewBonButton() {
            const trigger = document.querySelector('[data-trigger="new-bon"]');
            if (!trigger || trigger.dataset.bound === 'true') {
                return;
            }
            trigger.dataset.bound = 'true';
            trigger.addEventListener('click', startNewBon);
        }

        document.addEventListener('DOMContentLoaded', () => {
            bindWinkelButtons();
            bindBonList();
            bindNewBonButton();
            const urlParams = new URLSearchParams(window.location.search);
            const urlWinkelParam = parseInt(urlParams.get('winkel'), 10);
            if (!Number.isNaN(urlWinkelParam) && selectedWinkelId !== null) {
                activeBonWinkelId = urlWinkelParam;
                const found = allWinkels.find(w => w.id === urlWinkelParam);
                if (found) activeBonWinkelName = found.naam;
            } else if (selectedWinkelId === null) {
                // Force clear when in "Alles"
                activeBonWinkelId = null;
                activeBonWinkelName = 'Alles';
                urlParams.delete('open_new_bon');
                urlParams.delete('winkel');
                window.history.replaceState({}, '', window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : ''));
            }
            const shouldOpen = urlParams.get('open_new_bon') === '1';
            if (shouldOpen && activeBonWinkelId !== null) {
                // Kleine delay zodat de UI klaar is
                setTimeout(() => {
                    startNewBon();
                    urlParams.delete('open_new_bon');
                    const newUrl = window.location.pathname + '?' + urlParams.toString();
                    window.history.replaceState({}, '', newUrl);
                }, 150);
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const overlay = document.getElementById('popup-overlay');
                if (!overlay.classList.contains('hidden')) {
                    if (currentBonId && currentNumbers.length === 0) {
                        finishBon();
                    } else {
                        hidePopup();
                    }
                }
            }
        });
        
        // Winkel selector function
        async function selectWinkel(winkelId) {
            console.log('selectWinkel called with:', winkelId);
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
        
        // Move bon to different winkel
        async function moveBonToWinkel(bonId, winkelId) {
            if (!winkelId || !confirm('Bon verplaatsen naar deze winkel?')) {
                document.querySelector('select[onchange*="moveBonToWinkel"]').value = '';
                return;
            }
            
            try {
                const response = await fetch('api/update_bon_winkel.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ bon_id: bonId, winkel_id: winkelId })
                });
                
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Kon bon niet verplaatsen');
                }
            } catch (e) {
                alert('Fout bij verplaatsen');
                console.error(e);
            }
        }

        // Check for old data on page load and show popup
        window.addEventListener('DOMContentLoaded', async function() {
            // Only check once per session
            if (sessionStorage.getItem('old_data_popup_shown')) {
                return;
            }
            
            try {
                const response = await fetch('api/check_old_data.php');
                const data = await response.json();
                
                if (data.success && data.has_old_data) {
                    sessionStorage.setItem('old_data_popup_shown', 'true');
                    
                    const message = `⚠️ Er staat nog data ouder dan 2 maanden in het systeem.\n\n${data.count} bonnen gevonden voor ${data.cutoff_date}.\n\nWil je deze data verwijderen via Instellingen?`;
                    
                    if (confirm(message)) {
                        window.location.href = 'beheer.php';
                    }
                }
            } catch (e) {
                console.error('Could not check old data:', e);
            }
        });
        
        // Date Track Scroll Functionaliteit
        // Scroll controls - scroll per WEEK en center de week
        function scrollDateTrack(direction) {
            const track = document.getElementById('dateTrack');
            if (!track) return;
            
            const trackRect = track.getBoundingClientRect();
            const weekGroups = Array.from(track.querySelectorAll('.week-group'));
            
            if (weekGroups.length === 0) return;
            
            // Vind huidige gecentreerde week
            let currentCenterWeekIndex = -1;
            const trackCenter = trackRect.left + (trackRect.width / 2);
            let minDistance = Infinity;
            
            weekGroups.forEach((week, index) => {
                const rect = week.getBoundingClientRect();
                const weekCenter = rect.left + (rect.width / 2);
                const distance = Math.abs(weekCenter - trackCenter);
                
                if (distance < minDistance) {
                    minDistance = distance;
                    currentCenterWeekIndex = index;
                }
            });
            
            // Bepaal target week - ZONDER limiet check (mag door het einde heen)
            let targetIndex;
            if (direction === 'left') {
                targetIndex = currentCenterWeekIndex - 1;
                // Als we voorbij het begin gaan, blijf bij eerste week
                if (targetIndex < 0) targetIndex = 0;
            } else {
                targetIndex = currentCenterWeekIndex + 1;
                // Geen maximum check - laat infinite scroll het afhandelen
                // Als we voorbij het einde gaan, scroll gewoon door
                if (targetIndex >= weekGroups.length) {
                    targetIndex = weekGroups.length - 1;
                }
            }
            
            const targetWeek = weekGroups[targetIndex];
            if (targetWeek) {
                scrollToCenterElement(track, targetWeek);
            }
        }
        
        // Helper: center een element in de track
        function scrollToCenterElement(track, element) {
            const trackRect = track.getBoundingClientRect();
            const elementRect = element.getBoundingClientRect();
            
            const elementCenter = elementRect.left + (elementRect.width / 2);
            const trackCenter = trackRect.left + (trackRect.width / 2);
            const scrollOffset = elementCenter - trackCenter;
            
            track.scrollBy({
                left: scrollOffset,
                behavior: 'smooth'
            });
        }
        
        // Go to newest date (met instant positionering na load, zoals page load)
        async function goToNewestDate(date) {
            const track = document.getElementById('dateTrack');
            const targetBtn = document.querySelector(`.date-btn[data-date="${date}"]`);
            
            // Als de dag al geladen is, gebruik normale flow
            if (targetBtn) {
                changeDateSmooth(date);
                return;
            }
            
            // Anders: navigeer met page reload (dag is niet geladen)
            // Na reload wordt de dag automatisch gecentreerd (zoals initial load)
            window.location.href = '?date=' + date;
        }
        
        // OPTIE 1: Smooth Center Scroll (met getBoundingClientRect)
        function scrollToCenter(track, element, instant = false) {
            const trackRect = track.getBoundingClientRect();
            const elementRect = element.getBoundingClientRect();
            
            const elementCenter = elementRect.left + (elementRect.width / 2);
            const trackCenter = trackRect.left + (trackRect.width / 2);
            const scrollOffset = elementCenter - trackCenter;
            
            if (instant) {
                // Instant positioning (page load)
                track.scrollLeft = track.scrollLeft + scrollOffset;
            } else {
                // Smooth scroll (user interaction)
                track.scrollBy({
                    left: scrollOffset,
                    behavior: 'smooth'
                });
            }
        }
        
        // Helper: format datum naar "di 02 dec" formaat
        function formatDutchDate(dateStr) {
            const dagen = ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'];
            const maanden = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];

            const d = new Date(dateStr + 'T00:00:00');
            const dayOfWeek = dagen[d.getDay()];
            const day = String(d.getDate()).padStart(2, '0');
            const month = maanden[d.getMonth()];

            return `${dayOfWeek} ${day} ${month}`;
        }

        // Update dashboard content zonder page reload
        function updateDashboardContent(data) {
            console.log('Dashboard data received:', data);
            // Update datum header
            const dateHeader = document.querySelector('[data-bonnen-date-header]');
            if (dateHeader && data.date) {
                dateHeader.textContent = 'Bonnen van ' + formatDutchDate(data.date);
            }

            // Update bonnen lijst
            const bonnenContainer = document.querySelector('[data-bonnen-container]');
            if (bonnenContainer && data.bonnen !== undefined) {
                if (data.bonnen.length === 0) {
                    bonnenContainer.innerHTML = `
                        <div class="text-center py-12 text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="text-sm">Nog geen bonnen voor deze dag</p>
                        </div>
                    `;
                } else {
                    bonnenContainer.innerHTML = `
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-gray-500 border-b border-gray-100 uppercase tracking-wide">
                                        <th class="pb-3 font-medium">Datum</th>
                                        <th class="pb-3 font-medium">Bonnummer</th>
                                        <th class="pb-3 font-medium text-right">Rijen</th>
                                        <th class="pb-3 font-medium text-right">Inzet</th>
                                        <th class="pb-3 font-medium text-right">Uitbetaald</th>
                                        <th class="pb-3 font-medium text-right">Huisresultaat</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    ${data.bonnen.map(bon => {
                                        const bonBet = bon.bet;
                                        const bonWinnings = bon.winnings;
                                        const huisSaldo = bonBet - bonWinnings;
                                        const initial = bon.winkel_name ? bon.winkel_name.charAt(0).toUpperCase() : '?';
                                        return `
                                            <tr class="hover:bg-gray-50 cursor-pointer" data-bon-id="\${bon.id}">
                                                <td class="py-3">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-medium flex-shrink-0" style="background: \${bon.player_color}">
                                                            \${initial}
                                                        </div>
                                                        <div class="min-w-0">
                                                            <div class="font-medium text-gray-800 truncate">\${formatDutchDate(bon.date)}</div>
                                                            <div class="text-xs text-gray-500 truncate">\${bon.player_name}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="py-3 text-gray-800">\${bon.bonnummer || '—'}</td>
                                                <td class="py-3 text-right text-gray-600">\${bon.rijen_count}</td>
                                                <td class="py-3 text-right text-gray-900">€\${bonBet.toFixed(2).replace('.', ',')}</td>
                                                <td class="py-3 text-right \${bonWinnings > 0 ? 'text-red-500' : 'text-gray-500'}">\${bonWinnings > 0 ? '€' + bonWinnings.toFixed(2).replace('.', ',') : '—'}</td>
                                                <td class="py-3 text-right font-semibold \${huisSaldo > 0 ? 'text-emerald-600' : (huisSaldo < 0 ? 'text-red-500' : 'text-gray-600')}">
                                                    \${huisSaldo > 0 ? '↑' : (huisSaldo < 0 ? '↓' : '→')} €\${Math.abs(huisSaldo).toFixed(2).replace('.', ',')}
                                                </td>
                                            </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;

                    // Rebind click events for new bon cards
                    bindBonList();
                }
            }
            
            // Update totals
            if (data.totals) {
                const betEl = document.querySelector('[data-total-bet]');
                const winningsEl = document.querySelector('[data-total-winnings]');
                const saldoEl = document.querySelector('[data-total-saldo]');
                
                if (betEl) betEl.textContent = '€' + data.totals.bet.toFixed(2);
                if (winningsEl) winningsEl.textContent = '€' + data.totals.winnings.toFixed(2);
                if (saldoEl) {
                    saldoEl.textContent = '€' + Math.abs(data.totals.saldo).toFixed(2);
                    saldoEl.className = data.totals.saldo > 0 ? 'text-green-600 font-bold' : 
                                       data.totals.saldo < 0 ? 'text-red-600 font-bold' : 
                                       'text-gray-600 font-bold';
                }
            }
            
            // Update winning numbers display
            const winningNumbersContainer = document.getElementById('winning-numbers-container');
            if (winningNumbersContainer) {
                if (data.has_winning_numbers && data.winning_numbers && data.winning_numbers.length === 20) {
                    const firstRow = data.winning_numbers.slice(0, 10);
                    const secondRow = data.winning_numbers.slice(10, 20);

                    winningNumbersContainer.innerHTML = `
                        <div class="space-y-1.5">
                            <div class="grid grid-cols-10 gap-1">
                                ${firstRow.map(num => `
                                    <span class="w-7 h-7 flex items-center justify-center text-xs font-medium rounded-md"
                                          style="background: ${shopAccentColor}15; color: ${shopAccentColor}; border: 1px solid ${shopAccentColor}40;">
                                        ${num}
                                    </span>
                                `).join('')}
                            </div>
                            <div class="grid grid-cols-10 gap-1">
                                ${secondRow.map(num => `
                                    <span class="w-7 h-7 flex items-center justify-center text-xs font-medium rounded-md"
                                          style="background: ${shopAccentColor}15; color: ${shopAccentColor}; border: 1px solid ${shopAccentColor}40;">
                                        ${num}
                                    </span>
                                `).join('')}
                            </div>
                        </div>
                    `;
                } else {
                    winningNumbersContainer.innerHTML = `
                        <div class="text-center py-8">
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900 mb-1">Geen winnende nummers</p>
                            <p class="text-xs text-gray-500">Nog niet beschikbaar voor ${formatDutchDate(data.date)}</p>
                        </div>
                    `;
                }
            }

            // Update global winningNumbers variable voor popup gebruik
            if (data.winning_numbers) {
                winningNumbers = data.winning_numbers;
            }
        }
        
        // Smooth date change MET AJAX (GEEN page reload!)
        async function changeDateSmooth(newDate) {
            const track = document.getElementById('dateTrack');
            const clickedBtn = document.querySelector(`.date-btn[data-date="${newDate}"]`);

            if (!track || !clickedBtn) {
                return;
            }

            // Verwijder focus ring onmiddellijk
            clickedBtn.blur();

            // Voeg loading state toe voor visuele feedback
            clickedBtn.classList.add('date-btn-loading');

            // Update active state visueel (zonder flicker)
            document.querySelectorAll('.date-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            clickedBtn.classList.add('active');

            // Smooth scroll naar nieuwe dag
            scrollToCenter(track, clickedBtn, false);

            // Wacht even en fetch dan de data via AJAX
            setTimeout(async () => {
                try {
                    // Voeg winkel parameter toe als die geselecteerd is
                    let apiUrl = `api/get_dashboard_data.php?date=${newDate}`;
                    const response = await fetch(apiUrl);
                    const data = await response.json();

                    if (data.success) {
                        // Update global selectedDate variable
                        selectedDate = newDate;

                        // Update URL zonder page reload - behoud winkel parameter
                        let newUrl = `?date=${newDate}`;
                        if (selectedWinkelId !== null) {
                            newUrl += `&winkel=${selectedWinkelId}`;
                        }
                        window.history.pushState({date: newDate}, '', newUrl);

                        // Update dashboard content
                        updateDashboardContent(data);

                        // Verwijder loading state
                        clickedBtn.classList.remove('date-btn-loading');
                    } else {
                        // Fallback: reload als AJAX faalt
                        let fallbackUrl = `?date=${newDate}`;
                        if (selectedWinkelId !== null) {
                            fallbackUrl += `&winkel=${selectedWinkelId}`;
                        }
                        window.location.href = fallbackUrl;
                    }
                } catch (error) {
                    console.error('AJAX error:', error);
                    // Fallback: reload
                    let fallbackUrl = `?date=${newDate}`;
                    if (selectedWinkelId !== null) {
                        fallbackUrl += `&winkel=${selectedWinkelId}`;
                    }
                    window.location.href = fallbackUrl;
                }
            }, 400); // Korte delay zodat scroll smooth blijft
        }
        
        // Smooth positioneren op GESELECTEERDE dag bij page load
        document.addEventListener('DOMContentLoaded', function() {
            const track = document.getElementById('dateTrack');
            const activeDay = document.querySelector('.date-btn.active');
            
            if (track && activeDay) {
                // Direct instant positioneren VOOR de pagina zichtbaar is
                // Dit voorkomt "scroll van links naar rechts" effect
                const trackRect = track.getBoundingClientRect();
                const dayRect = activeDay.getBoundingClientRect();
                const elementCenter = dayRect.left + (dayRect.width / 2);
                const trackCenter = trackRect.left + (trackRect.width / 2);
                const scrollOffset = elementCenter - trackCenter;
                
                // Instant scroll ZONDER animatie (voorkomt visuele jump)
                track.scrollLeft = track.scrollLeft + scrollOffset;
            }
            
            // Handle browser back/forward buttons
            window.addEventListener('popstate', function(e) {
                if (e.state && e.state.date) {
                    // Reload page when using back/forward
                    window.location.reload();
                }
            });
            
            // INFINITE SCROLL SETUP
            if (track) {
                setupInfiniteScroll(track);
            }
        });
        
        // Infinite Scroll Implementation
        let isLoadingDates = false;
        let hasMoreBefore = true;
        let hasMoreAfter = true;
        
        function setupInfiniteScroll(track) {
            let scrollTimeout;
            
            track.addEventListener('scroll', function() {
                // Debounce scroll events
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    checkScrollEdges(track);
                }, 150);
            });
        }
        
        function checkScrollEdges(track) {
            if (isLoadingDates) return;
            
            const scrollLeft = track.scrollLeft;
            const scrollWidth = track.scrollWidth;
            const clientWidth = track.clientWidth;
            const scrollRight = scrollWidth - scrollLeft - clientWidth;
            
            // Threshold: load meer wanneer binnen 500px van edge
            const threshold = 500;
            
            if (scrollLeft < threshold && hasMoreBefore) {
                loadMoreDates('before', track);
            } else if (scrollRight < threshold && hasMoreAfter) {
                loadMoreDates('after', track);
            }
        }
        
        async function loadMoreDates(direction, track) {
            if (isLoadingDates) return;
            isLoadingDates = true;
            
            // Vind eerste of laatste dag als reference
            const trackInner = track.querySelector('.date-track-inner');
            const allDays = trackInner.querySelectorAll('.date-btn');
            const referenceBtn = direction === 'before' ? allDays[0] : allDays[allDays.length - 1];
            const referenceDate = referenceBtn.dataset.date;
            
            // Bewaar huidige scroll positie (voor 'before' moeten we compenseren)
            const oldScrollLeft = track.scrollLeft;
            const oldScrollWidth = track.scrollWidth;
            
            try {
                const response = await fetch(`api/get_date_range.php?direction=${direction}&reference_date=${referenceDate}&limit=14`);
                const data = await response.json();
                
                if (data.success && data.dates.length > 0) {
                    appendDatesToDOM(data.dates, direction, trackInner, track, oldScrollLeft, oldScrollWidth);
                } else {
                    // Geen meer data beschikbaar
                    if (direction === 'before') hasMoreBefore = false;
                    else hasMoreAfter = false;
                }
            } catch (error) {
                console.error('Failed to load more dates:', error);
            }
            
            isLoadingDates = false;
        }
        
        function appendDatesToDOM(dates, direction, trackInner, track, oldScrollLeft, oldScrollWidth) {
            const accentColor = '<?= $activeWinkelTheme['accent'] ?>';
            const fragment = document.createDocumentFragment();
            
            let currentWeekKey = null;
            let weekGroup = null;
            
            dates.forEach((dateData, index) => {
                // Nieuwe week starten?
                if (dateData.is_first_of_week || weekGroup === null) {
                    // Week separator (behalve voor eerste week)
                    if (weekGroup !== null) {
                        const separator = document.createElement('div');
                        separator.className = 'week-separator';
                        fragment.appendChild(separator);
                    }
                    
                    // Nieuwe week group
                    weekGroup = document.createElement('div');
                    weekGroup.className = 'week-group';
                    
                    // Week label
                    const weekLabel = document.createElement('div');
                    weekLabel.className = 'week-label';
                    weekLabel.innerHTML = 'Week ' + dateData.week_num + ' <span class="week-year">\'' + dateData.year.toString().substr(2) + '</span>';
                    weekGroup.appendChild(weekLabel);
                    
                    fragment.appendChild(weekGroup);
                    currentWeekKey = dateData.week_key;
                }
                
                // Create day button
                const dayBtn = document.createElement('button');
                dayBtn.type = 'button';
                dayBtn.className = 'date-btn';
                dayBtn.dataset.date = dateData.date;
                dayBtn.onclick = () => changeDateSmooth(dateData.date);
                
                dayBtn.innerHTML = `
                    <span class="day-name">${dateData.day_name}</span>
                    <span class="day-number">${dateData.day_number}</span>
                    <span class="month-name">${dateData.month_name}</span>
                    ${(dateData.has_winning && dateData.has_bonnen) ? 
                        '<div class="indicator-container"><div class="indicator-line thick"></div></div>' :
                      (dateData.has_winning && !dateData.has_bonnen) ?
                        '<div class="indicator-container"><div class="indicator-line thin"></div></div>' :
                      ''}
                `;
                
                weekGroup.appendChild(dayBtn);
            });
            
            // Append of prepend
            if (direction === 'before') {
                trackInner.insertBefore(fragment, trackInner.firstChild);
                
                // Compenseer scroll positie zodat gebruiker op zelfde plek blijft
                const newScrollWidth = track.scrollWidth;
                const scrollDiff = newScrollWidth - oldScrollWidth;
                track.scrollLeft = oldScrollLeft + scrollDiff;
            } else {
                trackInner.appendChild(fragment);
            }
        }
    </script>
</body>
</html>
