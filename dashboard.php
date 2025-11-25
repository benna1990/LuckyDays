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
    if (isset($_POST['add_player'])) {
        $name = trim($_POST['player_name']);
        $numbers = trim($_POST['player_numbers']);
        $bet = floatval($_POST['player_bet']);
        $game_type = $_POST['game_type'];
        
        if (!empty($name) && !empty($numbers) && $bet > 0) {
            pg_query_params($conn, 
                "INSERT INTO players (name, numbers, bet, game_type, date) VALUES ($1, $2, $3, $4, $5)",
                [$name, $numbers, $bet, $game_type, $selected_date]
            );
        }
        header("Location: dashboard.php?selected_date=" . $selected_date);
        exit();
    }
    
    if (isset($_POST['delete_player'])) {
        $player_id = intval($_POST['player_id']);
        pg_query_params($conn, "DELETE FROM players WHERE id = $1", [$player_id]);
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

$players_result = pg_query_params($conn, 
    "SELECT * FROM players WHERE date = $1 ORDER BY created_at DESC", 
    [$selected_date]
);
$players = $players_result ? pg_fetch_all($players_result) : [];

$multipliers_result = pg_query($conn, "SELECT * FROM game_multipliers ORDER BY game_type, matches");
$all_multipliers = $multipliers_result ? pg_fetch_all($multipliers_result) : [];

$multipliers = [];
foreach ($all_multipliers as $m) {
    $multipliers[$m['game_type']][$m['matches']] = floatval($m['multiplier']);
}

function calculatePlayerResults($player, $winning_numbers, $multipliers, $has_valid_numbers) {
    if (!$has_valid_numbers) {
        return [
            'matches' => [],
            'match_count' => null,
            'multiplier' => null,
            'winnings' => null,
            'profit' => null
        ];
    }
    
    $player_numbers = array_map('trim', explode(',', $player['numbers']));
    $matches = array_intersect($player_numbers, $winning_numbers);
    $match_count = count($matches);
    
    $game_type = $player['game_type'];
    $multiplier = $multipliers[$game_type][$match_count] ?? 0;
    $bet = floatval($player['bet']);
    $winnings = $bet * $multiplier;
    
    return [
        'matches' => $matches,
        'match_count' => $match_count,
        'multiplier' => $multiplier,
        'winnings' => $winnings,
        'profit' => $winnings - $bet
    ];
}

$total_bet = 0;
$total_winnings = 0;
$player_results = [];

if ($players) {
    foreach ($players as $player) {
        $result_data = calculatePlayerResults($player, $winning_numbers, $multipliers, $has_valid_numbers);
        $player_results[$player['id']] = $result_data;
        $total_bet += floatval($player['bet']);
        if ($result_data['winnings'] !== null) {
            $total_winnings += $result_data['winnings'];
        }
    }
}
$total_profit = $has_valid_numbers ? ($total_winnings - $total_bet) : null;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lucky Day Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
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
        
        .nav-links a:hover, .nav-links a.active {
            color: #10B981;
        }
        
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
        
        .btn-primary {
            background: #10B981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
        }
        
        .btn-secondary {
            background: #F3F4F6;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #E5E7EB;
        }
        
        .btn-danger {
            background: #FEE2E2;
            color: #DC2626;
        }
        
        .btn-danger:hover {
            background: #FECACA;
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: #6B7280;
            font-size: 1rem;
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
        
        .date-btn:hover {
            border-color: #10B981;
            background: #F0FDF4;
        }
        
        .date-btn.active {
            background: #10B981;
            border-color: #10B981;
            color: white;
        }
        
        .date-btn .day {
            font-size: 0.75rem;
            text-transform: uppercase;
            opacity: 0.7;
        }
        
        .date-btn .date {
            font-weight: 600;
            font-size: 1rem;
        }
        
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
            .numbers-grid {
                grid-template-columns: repeat(5, 1fr);
            }
        }
        
        .number-pill {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            font-weight: 700;
            font-size: 1.25rem;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .number-pill.empty {
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
        
        .status-database {
            background: #D1FAE5;
            color: #065F46;
        }
        
        .status-scraped {
            background: #DBEAFE;
            color: #1E40AF;
        }
        
        .status-none {
            background: #FEE2E2;
            color: #991B1B;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-card {
            background: #FFFFFF;
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid #E5E7EB;
        }
        
        .stat-label {
            color: #6B7280;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .stat-positive {
            color: #10B981;
        }
        
        .stat-negative {
            color: #EF4444;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #10B981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
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
            vertical-align: middle;
        }
        
        .players-table tr:hover {
            background: #F9FAFB;
        }
        
        .player-numbers {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }
        
        .player-number {
            background: #F3F4F6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .player-number.match {
            background: #D1FAE5;
            color: #065F46;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6B7280;
        }
        
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
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
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6B7280;
        }
        
        .section-divider {
            height: 1px;
            background: #E5E7EB;
            margin: 2rem 0;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo">
                <span>🍀</span> Lucky Day
            </div>
            <div class="nav-links">
                <a href="/dashboard.php" class="active">Dashboard</a>
                <a href="/spelers.php">Spelers</a>
                <a href="/balans.php">Balans</a>
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
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Lucky Day uitslagen en spelerresultaten</p>
        </div>

        <!-- Date Selection -->
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

        <!-- Winning Numbers -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Winnende Nummers - <?php echo date('d M Y', strtotime($selected_date)); ?></h2>
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
                    <div class="number-pill <?php echo ($number === '-') ? 'empty' : ''; ?>">
                        <?php echo htmlspecialchars($number); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($isAdmin): ?>
                <div class="section-divider"></div>
                <div style="display: flex; gap: 1rem;">
                    <button onclick="openModal('editNumbers')" class="btn btn-secondary">Nummers Bewerken</button>
                    <button onclick="forceScrape()" class="btn btn-secondary">Opnieuw Ophalen</button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Stats Overview -->
        <?php if ($players && count($players) > 0): ?>
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
                <div class="stat-value" style="color: #9CA3AF;">Uitslag ontbreekt</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Players Section -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Spelers</h2>
                <button onclick="openModal('addPlayer')" class="btn btn-primary">+ Speler Toevoegen</button>
            </div>
            
            <?php if ($players && count($players) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="players-table">
                    <thead>
                        <tr>
                            <th>Speler</th>
                            <th>Nummers</th>
                            <th>Type</th>
                            <th>Inzet</th>
                            <th>Treffers</th>
                            <th>Multiplier</th>
                            <th>Winst</th>
                            <th>Resultaat</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($players as $player): ?>
                            <?php $res = $player_results[$player['id']]; ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($player['name']); ?></strong></td>
                                <td>
                                    <div class="player-numbers">
                                        <?php 
                                        $player_nums = array_map('trim', explode(',', $player['numbers']));
                                        foreach ($player_nums as $num): 
                                            $isMatch = in_array($num, $winning_numbers);
                                        ?>
                                            <span class="player-number <?php echo $isMatch ? 'match' : ''; ?>">
                                                <?php echo htmlspecialchars($num); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($player['game_type']); ?></td>
                                <td>€<?php echo number_format($player['bet'], 2, ',', '.'); ?></td>
                                <?php if ($has_valid_numbers): ?>
                                <td><strong><?php echo $res['match_count']; ?></strong></td>
                                <td><?php echo $res['multiplier']; ?>x</td>
                                <td>€<?php echo number_format($res['winnings'], 2, ',', '.'); ?></td>
                                <td class="<?php echo $res['profit'] >= 0 ? 'stat-positive' : 'stat-negative'; ?>">
                                    <?php echo $res['profit'] >= 0 ? '+' : ''; ?>€<?php echo number_format($res['profit'], 2, ',', '.'); ?>
                                </td>
                                <?php else: ?>
                                <td style="color: #9CA3AF;">-</td>
                                <td style="color: #9CA3AF;">-</td>
                                <td style="color: #9CA3AF;">-</td>
                                <td style="color: #9CA3AF;">Wacht op uitslag</td>
                                <?php endif; ?>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Weet je zeker dat je deze speler wilt verwijderen?');">
                                        <input type="hidden" name="player_id" value="<?php echo $player['id']; ?>">
                                        <button type="submit" name="delete_player" class="btn btn-danger btn-sm">Verwijder</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <p>Nog geen spelers voor deze datum</p>
                <button onclick="openModal('addPlayer')" class="btn btn-primary" style="margin-top: 1rem;">+ Voeg eerste speler toe</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Player Modal -->
    <div class="modal-overlay" id="addPlayerModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Speler Toevoegen</h3>
                <button class="modal-close" onclick="closeModal('addPlayer')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Naam Speler</label>
                    <input type="text" name="player_name" class="form-input" required placeholder="Bijv. Jan">
                </div>
                <div class="form-group">
                    <label class="form-label">Gekozen Nummers (gescheiden door komma's)</label>
                    <input type="text" name="player_numbers" class="form-input" required placeholder="Bijv. 5, 12, 23, 45, 67">
                    <small style="color: #6B7280;">Kies 1 tot 10 nummers tussen 1 en 80</small>
                </div>
                <div class="form-row" style="grid-template-columns: 1fr 1fr;">
                    <div class="form-group">
                        <label class="form-label">Type Spel</label>
                        <select name="game_type" class="form-select" required>
                            <option value="3-getallen">3 Getallen</option>
                            <option value="4-getallen">4 Getallen</option>
                            <option value="5-getallen" selected>5 Getallen</option>
                            <option value="6-getallen">6 Getallen</option>
                            <option value="7-getallen">7 Getallen</option>
                            <option value="8-getallen">8 Getallen</option>
                            <option value="9-getallen">9 Getallen</option>
                            <option value="10-getallen">10 Getallen</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Inzet (€)</label>
                        <input type="number" name="player_bet" class="form-input" required min="0.01" step="0.01" placeholder="1.00">
                    </div>
                </div>
                <button type="submit" name="add_player" class="btn btn-primary" style="width: 100%;">Speler Toevoegen</button>
            </form>
        </div>
    </div>

    <!-- Edit Numbers Modal -->
    <div class="modal-overlay" id="editNumbersModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Winnende Nummers Bewerken</h3>
                <button class="modal-close" onclick="closeModal('editNumbers')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Voer 20 winnende nummers in (elk op een nieuwe regel)</label>
                    <textarea name="winning_numbers" class="form-textarea" rows="10" required placeholder="1&#10;2&#10;3&#10;..."><?php echo implode("\n", $winning_numbers); ?></textarea>
                </div>
                <button type="submit" name="set_winning_numbers" class="btn btn-primary" style="width: 100%;">Opslaan</button>
            </form>
        </div>
    </div>

    <script>
        function goToDate() {
            const date = document.getElementById('datePicker').value;
            if (date) {
                window.location.href = '?selected_date=' + date;
            }
        }
        
        function openModal(name) {
            document.getElementById(name + 'Modal').classList.add('active');
        }
        
        function closeModal(name) {
            document.getElementById(name + 'Modal').classList.remove('active');
        }
        
        function forceScrape() {
            if (confirm('Wil je de nummers opnieuw ophalen?')) {
                fetch('run_scraper.php?date=<?php echo $selected_date; ?>&force=1')
                    .then(response => response.text())
                    .then(data => {
                        alert(data);
                        location.reload();
                    });
            }
        }
        
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
