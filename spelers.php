<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    header('Location: /index.php');
    exit();
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_player'])) {
        $name = trim($_POST['player_name']);
        $alias = trim($_POST['player_alias'] ?? '');
        $color = $_POST['player_color'] ?? '#10B981';
        
        if (!empty($name)) {
            pg_query_params($conn, 
                "INSERT INTO players (name, alias, color, date) VALUES ($1, $2, $3, CURRENT_DATE)",
                [$name, $alias, $color]
            );
        }
        header("Location: spelers.php");
        exit();
    }
    
    if (isset($_POST['update_player'])) {
        $id = intval($_POST['player_id']);
        $name = trim($_POST['player_name']);
        $alias = trim($_POST['player_alias'] ?? '');
        $color = $_POST['player_color'] ?? '#10B981';
        
        pg_query_params($conn, 
            "UPDATE players SET name = $1, alias = $2, color = $3 WHERE id = $4",
            [$name, $alias, $color, $id]
        );
        header("Location: spelers.php");
        exit();
    }
    
    if (isset($_POST['delete_player'])) {
        $id = intval($_POST['player_id']);
        pg_query_params($conn, "DELETE FROM players WHERE id = $1", [$id]);
        header("Location: spelers.php");
        exit();
    }
}

$players_result = pg_query($conn, 
    "SELECT DISTINCT ON (name) p.id, p.name, p.alias, p.color,
            (SELECT COALESCE(SUM(bet), 0) FROM bons WHERE player_id = p.id) as total_bet,
            (SELECT COUNT(*) FROM bons WHERE player_id = p.id) as total_bons
     FROM players p 
     ORDER BY name, id DESC"
);
$players = $players_result ? pg_fetch_all($players_result) : [];

$recent_bons = pg_query($conn, 
    "SELECT b.*, p.name as player_name, p.alias, p.color 
     FROM bons b 
     JOIN players p ON b.player_id = p.id 
     ORDER BY b.created_at DESC 
     LIMIT 20"
);
$bons = $recent_bons ? pg_fetch_all($recent_bons) : [];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spelers - Lucky Day</title>
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
        .players-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        .player-card {
            background: #F9FAFB;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
            transition: all 0.2s;
        }
        .player-card:hover {
            border-color: #10B981;
        }
        .player-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
        }
        .player-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }
        .player-alias {
            color: #6B7280;
            font-size: 0.875rem;
            margin-bottom: 0.75rem;
        }
        .player-stats {
            display: flex;
            justify-content: center;
            gap: 1rem;
            font-size: 0.8rem;
            color: #6B7280;
        }
        .player-stats strong {
            color: #1A1A1A;
        }
        .player-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            justify-content: center;
        }
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
            max-width: 400px;
            width: 95%;
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
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }
        .form-input:focus {
            outline: none;
            border-color: #10B981;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo"><span>🍀</span> Lucky Day</div>
            <div class="nav-links">
                <a href="/dashboard.php">Dashboard</a>
                <a href="/spelers.php" class="active">Spelers</a>
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
            <h1 class="page-title">Spelers</h1>
            <p class="page-subtitle">Beheer spelers en bekijk hun activiteit</p>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Alle Spelers</h2>
                <button onclick="openModal('add')" class="btn btn-primary">+ Nieuwe Speler</button>
            </div>

            <?php if ($players && count($players) > 0): ?>
            <div class="players-grid">
                <?php foreach ($players as $player): ?>
                    <div class="player-card">
                        <div class="player-avatar" style="background: <?php echo $player['color'] ?? '#10B981'; ?>;">
                            <?php echo strtoupper(substr($player['name'], 0, 1)); ?>
                        </div>
                        <div class="player-name"><?php echo htmlspecialchars($player['name']); ?></div>
                        <?php if ($player['alias']): ?>
                            <div class="player-alias"><?php echo htmlspecialchars($player['alias']); ?></div>
                        <?php endif; ?>
                        <div class="player-stats">
                            <span><strong><?php echo $player['total_bons']; ?></strong> bonnen</span>
                            <span><strong>€<?php echo number_format($player['total_bet'], 0, ',', '.'); ?></strong></span>
                        </div>
                        <div class="player-actions">
                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($player)); ?>)" class="btn btn-secondary btn-sm">Bewerken</button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Speler verwijderen?');">
                                <input type="hidden" name="player_id" value="<?php echo $player['id']; ?>">
                                <button type="submit" name="delete_player" class="btn btn-danger btn-sm">×</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p style="font-size: 3rem; margin-bottom: 1rem;">👥</p>
                <p>Nog geen spelers</p>
                <button onclick="openModal('add')" class="btn btn-primary" style="margin-top: 1rem;">+ Eerste Speler Toevoegen</button>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($bons && count($bons) > 0): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recente Bonnen</h2>
            </div>
            <div style="overflow-x: auto;">
                <table class="bons-table">
                    <thead>
                        <tr>
                            <th>Speler</th>
                            <th>Datum</th>
                            <th>Nummers</th>
                            <th>Type</th>
                            <th>Inzet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bons as $bon): ?>
                            <tr>
                                <td>
                                    <span class="player-tag" style="background: <?php echo $bon['color']; ?>20;">
                                        <span class="player-dot" style="background: <?php echo $bon['color']; ?>;"></span>
                                        <?php echo htmlspecialchars($bon['player_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="/dashboard.php?selected_date=<?php echo $bon['date']; ?>" style="color: #10B981;">
                                        <?php echo date('d M Y', strtotime($bon['date'])); ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="bon-numbers">
                                        <?php 
                                        $nums = array_map('trim', explode(',', $bon['numbers']));
                                        foreach ($nums as $num): ?>
                                            <span class="bon-number"><?php echo htmlspecialchars($num); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($bon['game_type']); ?></td>
                                <td>€<?php echo number_format($bon['bet'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Player Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Nieuwe Speler</h3>
                <button class="modal-close" onclick="closeModal('add')">&times;</button>
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

    <!-- Edit Player Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Speler Bewerken</h3>
                <button class="modal-close" onclick="closeModal('edit')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="player_id" id="editPlayerId">
                <div class="form-group">
                    <label class="form-label">Naam</label>
                    <input type="text" name="player_name" id="editPlayerName" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Alias (optioneel)</label>
                    <input type="text" name="player_alias" id="editPlayerAlias" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Kleur</label>
                    <input type="color" name="player_color" id="editPlayerColor" style="width: 100%; height: 50px; border: none; cursor: pointer;">
                </div>
                <button type="submit" name="update_player" class="btn btn-primary" style="width: 100%;">Opslaan</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(name) {
            document.getElementById(name + 'Modal').classList.add('active');
        }

        function closeModal(name) {
            document.getElementById(name + 'Modal').classList.remove('active');
        }

        function openEditModal(player) {
            document.getElementById('editPlayerId').value = player.id;
            document.getElementById('editPlayerName').value = player.name;
            document.getElementById('editPlayerAlias').value = player.alias || '';
            document.getElementById('editPlayerColor').value = player.color || '#10B981';
            openModal('edit');
        }

        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', e => {
                if (e.target === m) closeModal(m.id.replace('Modal', ''));
            });
        });
    </script>
</body>
</html>
