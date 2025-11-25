<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['username'])) {
    header('Location: /index.php');
    exit();
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if (!isset($_GET['selected_date']) && !isset($_SESSION['selected_date'])) {
    $_SESSION['selected_date'] = date('Y-m-d');
}

$selected_date = $_GET['selected_date'] ?? $_SESSION['selected_date'];
$_SESSION['selected_date'] = $selected_date;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_bon'])) {
        $player_id = intval($_POST['player_id']);
        $game_type = $_POST['game_type'];
        $numbers = trim($_POST['bon_numbers']);
        $bet = floatval($_POST['bon_bet']);
        
        if ($player_id > 0 && !empty($numbers) && $bet > 0) {
            pg_query_params($conn, 
                "INSERT INTO bons (player_id, game_type, numbers, bet, date) VALUES ($1, $2, $3, $4, $5)",
                [$player_id, $game_type, $numbers, $bet, $selected_date]
            );
        }
        header("Location: dashboard.php?selected_date=" . $selected_date);
        exit();
    }
    
    if (isset($_POST['delete_bon'])) {
        $bon_id = intval($_POST['bon_id']);
        pg_query_params($conn, "DELETE FROM bons WHERE id = $1", [$bon_id]);
        header("Location: dashboard.php?selected_date=" . $selected_date);
        exit();
    }
    
    if (isset($_POST['add_player'])) {
        $name = trim($_POST['player_name']);
        $alias = trim($_POST['player_alias'] ?? '');
        $color = $_POST['player_color'] ?? '#10B981';
        
        if (!empty($name)) {
            pg_query_params($conn, 
                "INSERT INTO players (name, alias, color, date) VALUES ($1, $2, $3, $4)",
                [$name, $alias, $color, $selected_date]
            );
        }
        header("Location: dashboard.php?selected_date=" . $selected_date);
        exit();
    }
    
    if (isset($_POST['set_winning_numbers'])) {
        $winning_numbers = explode(PHP_EOL, trim($_POST['winning_numbers']));
        $winning_numbers = array_map('trim', $winning_numbers);
        $winning_numbers = array_filter($winning_numbers);
        
        if (count($winning_numbers) === 20) {
            saveWinningNumbersToDatabase($selected_date, $winning_numbers, $conn);
        }
        header("Location: dashboard.php?selected_date=" . $selected_date);
        exit();
    }
}

$result = getOrScrapeWinningNumbers($selected_date, $conn);
$winning_numbers = $result['numbers'];
$data_source = $result['source'];
$has_valid_numbers = $data_source !== 'none' && !in_array('-', $winning_numbers);

$all_players_result = pg_query($conn, "SELECT DISTINCT ON (name) id, name, alias, color FROM players ORDER BY name, id DESC");
$all_players = $all_players_result ? pg_fetch_all($all_players_result) : [];

$game_types_result = pg_query($conn, "SELECT * FROM game_types WHERE active = true ORDER BY numbers_count");
$game_types = $game_types_result ? pg_fetch_all($game_types_result) : [];

$bons_result = pg_query_params($conn, 
    "SELECT b.*, p.name as player_name, p.alias, p.color 
     FROM bons b 
     JOIN players p ON b.player_id = p.id 
     WHERE b.date = $1 
     ORDER BY b.created_at DESC", 
    [$selected_date]
);
$bons = $bons_result ? pg_fetch_all($bons_result) : [];

function calculateBonResults($bon, $winning_numbers, $game_types, $has_valid_numbers) {
    if (!$has_valid_numbers) {
        return ['match_count' => null, 'multiplier' => null, 'winnings' => null, 'matches' => []];
    }
    
    $bon_numbers = array_map('trim', explode(',', $bon['numbers']));
    $matches = array_intersect($bon_numbers, $winning_numbers);
    $match_count = count($matches);
    
    $multiplier = 0;
    foreach ($game_types as $gt) {
        if ($gt['name'] === $bon['game_type']) {
            $multipliers = json_decode($gt['multipliers'], true);
            $multiplier = $multipliers[$match_count] ?? 0;
            break;
        }
    }
    
    $winnings = floatval($bon['bet']) * $multiplier;
    
    return [
        'match_count' => $match_count,
        'multiplier' => $multiplier,
        'winnings' => $winnings,
        'matches' => $matches
    ];
}

$total_bet = 0;
$total_winnings = 0;
$bon_results = [];

if ($bons) {
    foreach ($bons as $bon) {
        $res = calculateBonResults($bon, $winning_numbers, $game_types, $has_valid_numbers);
        $bon_results[$bon['id']] = $res;
        $total_bet += floatval($bon['bet']);
        if ($res['winnings'] !== null) {
            $total_winnings += $res['winnings'];
        }
    }
}
$total_profit = $has_valid_numbers ? ($total_winnings - $total_bet) : null;

$game_types_json = json_encode($game_types);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lucky Day Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #F8F9FA;
            color: #1A1A1A;
            line-height: 1.6;
        }
        .navbar {
            background: #FFFFFF;
            border-bottom: 1px solid #E5E7EB;
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #10B981;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        .nav-links a {
            color: #6B7280;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        .nav-links a:hover, .nav-links a.active { color: #10B981; }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .user-badge {
            background: #F3F4F6;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.875rem;
        }
        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary { background: #10B981; color: white; }
        .btn-primary:hover { background: #059669; }
        .btn-secondary { background: #F3F4F6; color: #374151; }
        .btn-secondary:hover { background: #E5E7EB; }
        .btn-danger { background: #FEE2E2; color: #DC2626; }
        .btn-danger:hover { background: #FECACA; }
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.75rem; }
        .btn-lg { padding: 1rem 2rem; font-size: 1rem; }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        .page-header { margin-bottom: 2rem; }
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .page-subtitle { color: #6B7280; font-size: 1rem; }
        .card {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .card-title { font-size: 1.125rem; font-weight: 600; }
        .date-selector {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        .date-btn {
            padding: 0.75rem 1rem;
            border: 1px solid #E5E7EB;
            background: white;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            min-width: 80px;
            text-decoration: none;
            color: #374151;
        }
        .date-btn:hover { border-color: #10B981; background: #F0FDF4; }
        .date-btn.active { background: #10B981; border-color: #10B981; color: white; }
        .date-btn .day { font-size: 0.75rem; text-transform: uppercase; opacity: 0.7; }
        .date-btn .date { font-weight: 600; font-size: 1rem; }
        .date-picker-row {
            display: flex;
            justify-content: center;
            margin-top: 1rem;
            gap: 1rem;
            align-items: center;
        }
        .date-input {
            padding: 0.75rem 1rem;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            font-size: 1rem;
            font-family: inherit;
        }
        .numbers-grid {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 0.75rem;
        }
        @media (max-width: 768px) {
            .numbers-grid { grid-template-columns: repeat(5, 1fr); }
        }
        .number-ball {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
            margin: 0 auto;
        }
        .number-ball.empty {
            background: #F3F4F6;
            color: #9CA3AF;
            box-shadow: none;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-database { background: #D1FAE5; color: #065F46; }
        .status-scraped { background: #DBEAFE; color: #1E40AF; }
        .status-none { background: #FEE2E2; color: #991B1B; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
        .stat-card {
            background: #FFFFFF;
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid #E5E7EB;
        }
        .stat-label { color: #6B7280; font-size: 0.875rem; margin-bottom: 0.25rem; }
        .stat-value { font-size: 1.5rem; font-weight: 700; }
        .stat-positive { color: #10B981; }
        .stat-negative { color: #EF4444; }
        .bons-table {
            width: 100%;
            border-collapse: collapse;
        }
        .bons-table th {
            text-align: left;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #6B7280;
            border-bottom: 1px solid #E5E7EB;
        }
        .bons-table td {
            padding: 1rem;
            border-bottom: 1px solid #F3F4F6;
            vertical-align: middle;
        }
        .bons-table tr:hover { background: #F9FAFB; }
        .player-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.875rem;
        }
        .player-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .bon-numbers {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }
        .bon-number {
            background: #F3F4F6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .bon-number.match {
            background: #D1FAE5;
            color: #065F46;
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6B7280;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            max-width: 800px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .modal-title { font-size: 1.25rem; font-weight: 600; }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6B7280;
        }
        .form-group { margin-bottom: 1rem; }
        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        .form-input, .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #10B981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        .number-grid {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 0.5rem;
            margin: 1rem 0;
        }
        @media (max-width: 600px) {
            .number-grid { grid-template-columns: repeat(8, 1fr); }
        }
        .number-btn {
            width: 100%;
            aspect-ratio: 1;
            border: 2px solid #E5E7EB;
            background: white;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.15s;
        }
        .number-btn:hover { border-color: #10B981; background: #F0FDF4; }
        .number-btn.selected {
            background: #10B981;
            border-color: #10B981;
            color: white;
        }
        .number-btn.winning {
            background: #FEF3C7;
            border-color: #F59E0B;
        }
        .number-btn.selected.winning {
            background: linear-gradient(135deg, #10B981 50%, #F59E0B 50%);
            color: white;
        }
        .selected-numbers {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            min-height: 40px;
            padding: 0.75rem;
            background: #F9FAFB;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .selected-num {
            background: #10B981;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .selected-num .remove {
            cursor: pointer;
            opacity: 0.8;
        }
        .selected-num .remove:hover { opacity: 1; }
        .keyboard-hint {
            background: #F0FDF4;
            border: 1px solid #D1FAE5;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.8rem;
            color: #065F46;
            margin-bottom: 1rem;
        }
        .keyboard-hint kbd {
            background: white;
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
            font-family: monospace;
            border: 1px solid #D1FAE5;
        }
        .player-select-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .player-option {
            padding: 0.75rem;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.15s;
        }
        .player-option:hover { border-color: #10B981; }
        .player-option.selected { border-color: #10B981; background: #F0FDF4; }
        .player-option .name { font-weight: 600; }
        .player-option .alias { font-size: 0.75rem; color: #6B7280; }
        .section-divider {
            height: 1px;
            background: #E5E7EB;
            margin: 1.5rem 0;
        }
        .game-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .game-type-btn {
            padding: 0.75rem;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            text-align: center;
            font-weight: 500;
            transition: all 0.15s;
        }
        .game-type-btn:hover { border-color: #10B981; }
        .game-type-btn.selected { border-color: #10B981; background: #10B981; color: white; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo"><span>🍀</span> Lucky Day</div>
            <div class="nav-links">
                <a href="/dashboard.php" class="active">Dashboard</a>
                <a href="/spelers.php">Spelers</a>
                <a href="/balans.php">Balans</a>
                <?php if ($isAdmin): ?>
                    <a href="/spellen.php">Spellen</a>
                    <a href="/php/admin_beheer.php">Beheer</a>
                <?php endif; ?>
            </div>
            <div class="user-menu">
                <span class="user-badge"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="/logout.php" class="btn btn-secondary btn-sm">Uitloggen</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Lucky Day uitslagen en bonnen voor <?php echo date('d M Y', strtotime($selected_date)); ?></p>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Selecteer Datum</h2>
            </div>
            <div class="date-selector">
                <?php foreach (generateDateRange($selected_date) as $day): ?>
                    <?php $isActive = ($day == $selected_date) ? 'active' : ''; ?>
                    <a href="?selected_date=<?php echo $day; ?>" class="date-btn <?php echo $isActive; ?>">
                        <div class="day"><?php echo getDayAndAbbreviatedMonth($day); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="date-picker-row">
                <input type="date" id="datePicker" class="date-input" value="<?php echo $selected_date; ?>">
                <button onclick="goToDate()" class="btn btn-primary">Ga naar datum</button>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Winnende Nummers</h2>
                <?php if ($data_source === 'database'): ?>
                    <span class="status-badge status-database">✓ Database</span>
                <?php elseif ($data_source === 'scraped'): ?>
                    <span class="status-badge status-scraped">↓ Opgehaald</span>
                <?php else: ?>
                    <span class="status-badge status-none">✕ Geen data</span>
                <?php endif; ?>
            </div>
            <div class="numbers-grid">
                <?php foreach ($winning_numbers as $number): ?>
                    <div class="number-ball <?php echo ($number === '-') ? 'empty' : ''; ?>">
                        <?php echo htmlspecialchars($number); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($isAdmin): ?>
                <div class="section-divider"></div>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <button onclick="openModal('editNumbers')" class="btn btn-secondary">Nummers Bewerken</button>
                    <button onclick="forceScrape()" class="btn btn-secondary">Opnieuw Ophalen</button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($bons && count($bons) > 0): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Totale Inzet</div>
                <div class="stat-value">€<?php echo number_format($total_bet, 2, ',', '.'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Totale Winst</div>
                <div class="stat-value"><?php echo $has_valid_numbers ? '€' . number_format($total_winnings, 2, ',', '.') : '-'; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Netto Resultaat</div>
                <?php if ($has_valid_numbers): ?>
                <div class="stat-value <?php echo $total_profit >= 0 ? 'stat-positive' : 'stat-negative'; ?>">
                    <?php echo $total_profit >= 0 ? '+' : ''; ?>€<?php echo number_format($total_profit, 2, ',', '.'); ?>
                </div>
                <?php else: ?>
                <div class="stat-value" style="color: #9CA3AF;">Wacht op uitslag</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Bonnen</h2>
                <button onclick="openModal('addBon')" class="btn btn-primary btn-lg">+ Nieuwe Bon</button>
            </div>
            
            <?php if ($bons && count($bons) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="bons-table">
                    <thead>
                        <tr>
                            <th>Speler</th>
                            <th>Nummers</th>
                            <th>Type</th>
                            <th>Inzet</th>
                            <th>Treffers</th>
                            <th>Winst</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bons as $bon): ?>
                            <?php $res = $bon_results[$bon['id']]; ?>
                            <tr>
                                <td>
                                    <span class="player-tag" style="background: <?php echo $bon['color']; ?>20;">
                                        <span class="player-dot" style="background: <?php echo $bon['color']; ?>;"></span>
                                        <?php echo htmlspecialchars($bon['player_name']); ?>
                                        <?php if ($bon['alias']): ?>
                                            <small>(<?php echo htmlspecialchars($bon['alias']); ?>)</small>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="bon-numbers">
                                        <?php 
                                        $bon_nums = array_map('trim', explode(',', $bon['numbers']));
                                        foreach ($bon_nums as $num): 
                                            $isMatch = $has_valid_numbers && in_array($num, $winning_numbers);
                                        ?>
                                            <span class="bon-number <?php echo $isMatch ? 'match' : ''; ?>">
                                                <?php echo htmlspecialchars($num); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($bon['game_type']); ?></td>
                                <td>€<?php echo number_format($bon['bet'], 2, ',', '.'); ?></td>
                                <?php if ($has_valid_numbers): ?>
                                <td><strong><?php echo $res['match_count']; ?></strong></td>
                                <td class="<?php echo ($res['winnings'] - $bon['bet']) >= 0 ? 'stat-positive' : 'stat-negative'; ?>">
                                    €<?php echo number_format($res['winnings'], 2, ',', '.'); ?>
                                </td>
                                <?php else: ?>
                                <td style="color: #9CA3AF;">-</td>
                                <td style="color: #9CA3AF;">Wacht</td>
                                <?php endif; ?>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Bon verwijderen?');">
                                        <input type="hidden" name="bon_id" value="<?php echo $bon['id']; ?>">
                                        <button type="submit" name="delete_bon" class="btn btn-danger btn-sm">×</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p style="font-size: 3rem; margin-bottom: 1rem;">🎫</p>
                <p>Nog geen bonnen voor deze datum</p>
                <button onclick="openModal('addBon')" class="btn btn-primary btn-lg" style="margin-top: 1rem;">+ Eerste Bon Toevoegen</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Bon Modal -->
    <div class="modal-overlay" id="addBonModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Nieuwe Bon</h3>
                <button class="modal-close" onclick="closeModal('addBon')">&times;</button>
            </div>
            
            <div class="keyboard-hint">
                <strong>Sneltoetsen:</strong> 
                <kbd>Enter</kbd> Opslaan | 
                <kbd>Backspace</kbd> Laatste nummer wissen | 
                <kbd>Esc</kbd> Sluiten |
                Typ nummers direct (bijv. "12" + spatie)
            </div>

            <form method="POST" id="bonForm">
                <div class="form-group">
                    <label class="form-label">Speler</label>
                    <div class="player-select-grid" id="playerGrid">
                        <?php if (empty($all_players)): ?>
                            <p style="color: #6B7280; grid-column: 1/-1;">Geen spelers. <a href="#" onclick="openModal('addPlayer'); return false;">Voeg eerst een speler toe</a></p>
                        <?php else: ?>
                            <?php foreach ($all_players as $player): ?>
                                <div class="player-option" data-id="<?php echo $player['id']; ?>" onclick="selectPlayer(this, <?php echo $player['id']; ?>)" style="border-left: 4px solid <?php echo $player['color'] ?? '#10B981'; ?>;">
                                    <div class="name"><?php echo htmlspecialchars($player['name']); ?></div>
                                    <?php if ($player['alias']): ?>
                                        <div class="alias"><?php echo htmlspecialchars($player['alias']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="player_id" id="selectedPlayerId" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Speltype</label>
                    <div class="game-type-grid" id="gameTypeGrid">
                        <?php foreach ($game_types as $gt): ?>
                            <div class="game-type-btn" data-type="<?php echo htmlspecialchars($gt['name']); ?>" data-max="<?php echo $gt['numbers_count']; ?>" onclick="selectGameType(this)">
                                <?php echo htmlspecialchars($gt['name']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="game_type" id="selectedGameType" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Gekozen Nummers <span id="numberCount">(0/5)</span></label>
                    <div class="selected-numbers" id="selectedNumbers"></div>
                    <input type="hidden" name="bon_numbers" id="bonNumbersInput" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Kies nummers (1-80)</label>
                    <div class="number-grid" id="numberGrid">
                        <?php for ($i = 1; $i <= 80; $i++): ?>
                            <?php $isWinning = $has_valid_numbers && in_array((string)$i, $winning_numbers); ?>
                            <button type="button" class="number-btn <?php echo $isWinning ? 'winning' : ''; ?>" data-num="<?php echo $i; ?>" onclick="toggleNumber(<?php echo $i; ?>)">
                                <?php echo $i; ?>
                            </button>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Inzet (€)</label>
                    <input type="number" name="bon_bet" id="bonBet" class="form-input" required min="1" step="1" value="1" style="font-size: 1.25rem; font-weight: 600;">
                </div>

                <button type="submit" name="add_bon" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 1rem;">
                    Bon Opslaan
                </button>
            </form>
        </div>
    </div>

    <!-- Add Player Modal -->
    <div class="modal-overlay" id="addPlayerModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title">Nieuwe Speler</h3>
                <button class="modal-close" onclick="closeModal('addPlayer')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Naam</label>
                    <input type="text" name="player_name" class="form-input" required placeholder="Bijv. Jan">
                </div>
                <div class="form-group">
                    <label class="form-label">Alias (optioneel)</label>
                    <input type="text" name="player_alias" class="form-input" placeholder="Bijv. J.">
                </div>
                <div class="form-group">
                    <label class="form-label">Kleur</label>
                    <input type="color" name="player_color" value="#10B981" style="width: 100%; height: 50px; border: none; cursor: pointer;">
                </div>
                <button type="submit" name="add_player" class="btn btn-primary" style="width: 100%;">Speler Toevoegen</button>
            </form>
        </div>
    </div>

    <!-- Edit Numbers Modal -->
    <div class="modal-overlay" id="editNumbersModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title">Winnende Nummers</h3>
                <button class="modal-close" onclick="closeModal('editNumbers')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">20 nummers (elk op nieuwe regel)</label>
                    <textarea name="winning_numbers" class="form-input" rows="12" required style="font-family: monospace;"><?php echo implode("\n", $winning_numbers); ?></textarea>
                </div>
                <button type="submit" name="set_winning_numbers" class="btn btn-primary" style="width: 100%;">Opslaan</button>
            </form>
        </div>
    </div>

    <script>
        const gameTypes = <?php echo $game_types_json; ?>;
        let selectedNumbers = [];
        let maxNumbers = 5;
        let numberInputBuffer = '';
        let bufferTimeout = null;

        function goToDate() {
            const date = document.getElementById('datePicker').value;
            if (date) window.location.href = '?selected_date=' + date;
        }

        function openModal(name) {
            document.getElementById(name + 'Modal').classList.add('active');
            if (name === 'addBon') {
                document.addEventListener('keydown', handleKeyboard);
            }
        }

        function closeModal(name) {
            document.getElementById(name + 'Modal').classList.remove('active');
            if (name === 'addBon') {
                document.removeEventListener('keydown', handleKeyboard);
            }
        }

        function selectPlayer(el, id) {
            document.querySelectorAll('.player-option').forEach(p => p.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('selectedPlayerId').value = id;
        }

        function selectGameType(el) {
            document.querySelectorAll('.game-type-btn').forEach(g => g.classList.remove('selected'));
            el.classList.add('selected');
            const type = el.dataset.type;
            maxNumbers = parseInt(el.dataset.max);
            document.getElementById('selectedGameType').value = type;
            document.getElementById('numberCount').textContent = `(${selectedNumbers.length}/${maxNumbers})`;
            
            if (selectedNumbers.length > maxNumbers) {
                selectedNumbers = selectedNumbers.slice(0, maxNumbers);
                updateSelectedDisplay();
            }
        }

        function toggleNumber(num) {
            const idx = selectedNumbers.indexOf(num);
            if (idx > -1) {
                selectedNumbers.splice(idx, 1);
            } else if (selectedNumbers.length < maxNumbers) {
                selectedNumbers.push(num);
            }
            updateSelectedDisplay();
        }

        function removeNumber(num) {
            const idx = selectedNumbers.indexOf(num);
            if (idx > -1) {
                selectedNumbers.splice(idx, 1);
                updateSelectedDisplay();
            }
        }

        function updateSelectedDisplay() {
            const container = document.getElementById('selectedNumbers');
            container.innerHTML = selectedNumbers.map(n => 
                `<span class="selected-num">${n}<span class="remove" onclick="removeNumber(${n})">×</span></span>`
            ).join('');
            
            document.getElementById('bonNumbersInput').value = selectedNumbers.join(',');
            document.getElementById('numberCount').textContent = `(${selectedNumbers.length}/${maxNumbers})`;
            
            document.querySelectorAll('.number-btn').forEach(btn => {
                const num = parseInt(btn.dataset.num);
                if (selectedNumbers.includes(num)) {
                    btn.classList.add('selected');
                } else {
                    btn.classList.remove('selected');
                }
            });
        }

        function handleKeyboard(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            if (e.key === 'Escape') {
                closeModal('addBon');
                return;
            }
            
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('bonForm').submit();
                return;
            }
            
            if (e.key === 'Backspace') {
                e.preventDefault();
                if (selectedNumbers.length > 0) {
                    selectedNumbers.pop();
                    updateSelectedDisplay();
                }
                return;
            }
            
            if (/^\d$/.test(e.key)) {
                e.preventDefault();
                numberInputBuffer += e.key;
                
                clearTimeout(bufferTimeout);
                bufferTimeout = setTimeout(() => {
                    const num = parseInt(numberInputBuffer);
                    if (num >= 1 && num <= 80 && !selectedNumbers.includes(num) && selectedNumbers.length < maxNumbers) {
                        selectedNumbers.push(num);
                        updateSelectedDisplay();
                    }
                    numberInputBuffer = '';
                }, 300);
            }
            
            if (e.key === ' ' || e.key === ',') {
                e.preventDefault();
                if (numberInputBuffer) {
                    const num = parseInt(numberInputBuffer);
                    if (num >= 1 && num <= 80 && !selectedNumbers.includes(num) && selectedNumbers.length < maxNumbers) {
                        selectedNumbers.push(num);
                        updateSelectedDisplay();
                    }
                    numberInputBuffer = '';
                    clearTimeout(bufferTimeout);
                }
            }
        }

        function forceScrape() {
            if (confirm('Nummers opnieuw ophalen?')) {
                fetch('run_scraper.php?date=<?php echo $selected_date; ?>&force=1')
                    .then(r => r.text())
                    .then(() => location.reload());
            }
        }

        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', e => {
                if (e.target === m) {
                    const name = m.id.replace('Modal', '');
                    closeModal(name);
                }
            });
        });

        const firstGameType = document.querySelector('.game-type-btn');
        if (firstGameType) selectGameType(firstGameType);
    </script>
</body>
</html>
