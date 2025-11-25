<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['username'])) {
    header('Location: /index.php');
    exit();
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

$game_types_result = pg_query($conn, "SELECT * FROM game_types WHERE active = true ORDER BY numbers_count");
$game_types = $game_types_result ? pg_fetch_all($game_types_result) : [];

function getBonWinnings($bon, $winning_numbers, $game_types) {
    if (empty($winning_numbers) || in_array('-', $winning_numbers)) {
        return null;
    }
    
    $bon_numbers = array_map('trim', explode(',', $bon['numbers']));
    $matches = count(array_intersect($bon_numbers, $winning_numbers));
    
    foreach ($game_types as $gt) {
        if ($gt['name'] === $bon['game_type']) {
            $multipliers = json_decode($gt['multipliers'], true);
            return floatval($bon['bet']) * ($multipliers[$matches] ?? 0);
        }
    }
    return 0;
}

$overall_stats = pg_query($conn, 
    "SELECT 
        COALESCE(SUM(bet), 0) as total_bet,
        COUNT(*) as total_bons,
        COUNT(DISTINCT player_id) as unique_players,
        COUNT(DISTINCT date) as days_played
     FROM bons"
);
$stats = $overall_stats ? pg_fetch_assoc($overall_stats) : [];

$player_stats = pg_query($conn, 
    "SELECT 
        p.id, p.name, p.alias, p.color,
        COALESCE(SUM(b.bet), 0) as total_bet,
        COUNT(b.id) as total_bons,
        MIN(b.date) as first_play,
        MAX(b.date) as last_play
     FROM players p
     LEFT JOIN bons b ON b.player_id = p.id
     GROUP BY p.id, p.name, p.alias, p.color
     HAVING COUNT(b.id) > 0
     ORDER BY total_bet DESC"
);
$players = $player_stats ? pg_fetch_all($player_stats) : [];

$all_bons = pg_query($conn, 
    "SELECT b.*, p.name as player_name
     FROM bons b
     JOIN players p ON b.player_id = p.id
     ORDER BY b.date DESC, b.player_id"
);
$bons_list = $all_bons ? pg_fetch_all($all_bons) : [];

$winning_numbers_cache = [];

if ($bons_list) {
    $unique_dates = array_unique(array_column($bons_list, 'date'));
    if (!empty($unique_dates)) {
        $wn_result = pg_query_params($conn, 
            "SELECT date, numbers FROM winning_numbers WHERE date = ANY($1)",
            ['{' . implode(',', $unique_dates) . '}']
        );
        if ($wn_result) {
            while ($row = pg_fetch_assoc($wn_result)) {
                $winning_numbers_cache[$row['date']] = explode(',', $row['numbers']);
            }
        }
    }
}

$total_winnings = 0;
$player_winnings = [];

foreach ($bons_list as $bon) {
    $date = $bon['date'];
    $winning_nums = $winning_numbers_cache[$date] ?? [];
    
    $winnings = getBonWinnings($bon, $winning_nums, $game_types);
    if ($winnings !== null) {
        $total_winnings += $winnings;
        
        if (!isset($player_winnings[$bon['player_id']])) {
            $player_winnings[$bon['player_id']] = 0;
        }
        $player_winnings[$bon['player_id']] += $winnings;
    }
}

$total_bet = floatval($stats['total_bet'] ?? 0);
$total_profit = $total_winnings - $total_bet;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balans - Lucky Day</title>
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
        }
        .btn-secondary { background: #F3F4F6; color: #374151; }
        .btn-secondary:hover { background: #E5E7EB; }
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .stat-card {
            background: #FFFFFF;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .stat-card.highlight {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
        }
        .stat-card.highlight .stat-label { color: rgba(255,255,255,0.8); }
        .stat-label {
            color: #6B7280;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
        }
        .stat-positive { color: #10B981; }
        .stat-negative { color: #EF4444; }
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
        .players-table {
            width: 100%;
            border-collapse: collapse;
        }
        .players-table th {
            text-align: left;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #6B7280;
            border-bottom: 1px solid #E5E7EB;
        }
        .players-table td {
            padding: 1rem;
            border-bottom: 1px solid #F3F4F6;
        }
        .players-table tr:hover { background: #F9FAFB; }
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
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6B7280;
        }
        .progress-bar {
            height: 8px;
            background: #E5E7EB;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        .progress-fill {
            height: 100%;
            background: #10B981;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo"><span>🍀</span> Lucky Day</div>
            <div class="nav-links">
                <a href="/dashboard.php">Dashboard</a>
                <a href="/spelers.php">Spelers</a>
                <a href="/balans.php" class="active">Balans</a>
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
            <h1 class="page-title">Balans Overzicht</h1>
            <p class="page-subtitle">Totaaloverzicht van alle inzetten en winsten</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Totale Inzet</div>
                <div class="stat-value">€<?php echo number_format($total_bet, 2, ',', '.'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Totale Winst</div>
                <div class="stat-value">€<?php echo number_format($total_winnings, 2, ',', '.'); ?></div>
            </div>
            <div class="stat-card highlight">
                <div class="stat-label">Netto Resultaat</div>
                <div class="stat-value">
                    <?php echo $total_profit >= 0 ? '+' : ''; ?>€<?php echo number_format($total_profit, 2, ',', '.'); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Totaal Bonnen</div>
                <div class="stat-value"><?php echo $stats['total_bons'] ?? 0; ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Spelers Saldo</h2>
            </div>
            
            <?php if ($players && count($players) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="players-table">
                    <thead>
                        <tr>
                            <th>Speler</th>
                            <th>Bonnen</th>
                            <th>Inzet</th>
                            <th>Winst</th>
                            <th>Resultaat</th>
                            <th>ROI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($players as $player): ?>
                            <?php 
                            $bet = floatval($player['total_bet']);
                            $win = $player_winnings[$player['id']] ?? 0;
                            $profit = $win - $bet;
                            $roi = $bet > 0 ? (($profit / $bet) * 100) : 0;
                            ?>
                            <tr>
                                <td>
                                    <span class="player-tag" style="background: <?php echo $player['color']; ?>20;">
                                        <span class="player-dot" style="background: <?php echo $player['color']; ?>;"></span>
                                        <?php echo htmlspecialchars($player['name']); ?>
                                        <?php if ($player['alias']): ?>
                                            <small>(<?php echo htmlspecialchars($player['alias']); ?>)</small>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td><?php echo $player['total_bons']; ?></td>
                                <td>€<?php echo number_format($bet, 2, ',', '.'); ?></td>
                                <td>€<?php echo number_format($win, 2, ',', '.'); ?></td>
                                <td class="<?php echo $profit >= 0 ? 'stat-positive' : 'stat-negative'; ?>">
                                    <?php echo $profit >= 0 ? '+' : ''; ?>€<?php echo number_format($profit, 2, ',', '.'); ?>
                                </td>
                                <td class="<?php echo $roi >= 0 ? 'stat-positive' : 'stat-negative'; ?>">
                                    <?php echo $roi >= 0 ? '+' : ''; ?><?php echo number_format($roi, 1, ',', '.'); ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p style="font-size: 3rem; margin-bottom: 1rem;">📊</p>
                <p>Nog geen data beschikbaar</p>
                <a href="/dashboard.php" class="btn btn-secondary" style="margin-top: 1rem; display: inline-block;">Ga naar Dashboard</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
