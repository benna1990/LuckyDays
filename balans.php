<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    header('Location: /index.php');
    exit();
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

$stats = pg_query($conn, 
    "SELECT 
        SUM(bet) as total_bet,
        COUNT(*) as total_plays,
        COUNT(DISTINCT name) as unique_players,
        COUNT(DISTINCT date) as days_played
     FROM players"
);
$overall_stats = $stats ? pg_fetch_assoc($stats) : [];

$player_stats = pg_query($conn, 
    "SELECT 
        name,
        SUM(bet) as total_bet,
        COUNT(*) as plays,
        MIN(date) as first_play,
        MAX(date) as last_play
     FROM players
     GROUP BY name
     ORDER BY total_bet DESC"
);
$players = $player_stats ? pg_fetch_all($player_stats) : [];
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
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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
        .page-subtitle {
            color: #6B7280;
            font-size: 1rem;
        }
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
        .stat-label {
            color: #6B7280;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
        }
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
        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
        }
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
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6B7280;
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
            <p class="page-subtitle">Totaaloverzicht van alle inzetten en spelers</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Totale Inzet</div>
                <div class="stat-value">€<?php echo number_format($overall_stats['total_bet'] ?? 0, 2, ',', '.'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Totaal Spellen</div>
                <div class="stat-value"><?php echo $overall_stats['total_plays'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Unieke Spelers</div>
                <div class="stat-value"><?php echo $overall_stats['unique_players'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Dagen Gespeeld</div>
                <div class="stat-value"><?php echo $overall_stats['days_played'] ?? 0; ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Spelers Statistieken</h2>
            </div>
            
            <?php if ($players && count($players) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="players-table">
                    <thead>
                        <tr>
                            <th>Speler</th>
                            <th>Totale Inzet</th>
                            <th>Aantal Spellen</th>
                            <th>Eerste Spel</th>
                            <th>Laatste Spel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($players as $player): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($player['name']); ?></strong></td>
                                <td>€<?php echo number_format($player['total_bet'], 2, ',', '.'); ?></td>
                                <td><?php echo $player['plays']; ?></td>
                                <td><?php echo date('d M Y', strtotime($player['first_play'])); ?></td>
                                <td><?php echo date('d M Y', strtotime($player['last_play'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>Nog geen data beschikbaar</p>
                <a href="/dashboard.php" class="btn btn-secondary" style="margin-top: 1rem;">Ga naar Dashboard</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
