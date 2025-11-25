<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    header('Location: /index.php');
    exit();
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if (!$isAdmin) {
    header('Location: /dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_game'])) {
        $name = trim($_POST['game_name']);
        $count = intval($_POST['numbers_count']);
        $min_bet = floatval($_POST['min_bet']);
        $bet_step = floatval($_POST['bet_step']);
        
        $multipliers = [];
        for ($i = 0; $i <= $count; $i++) {
            $multipliers[$i] = floatval($_POST['mult_' . $i] ?? 0);
        }
        
        pg_query_params($conn, 
            "INSERT INTO game_types (name, numbers_count, min_bet, bet_step, multipliers) VALUES ($1, $2, $3, $4, $5)",
            [$name, $count, $min_bet, $bet_step, json_encode($multipliers)]
        );
        header("Location: spellen.php");
        exit();
    }
    
    if (isset($_POST['update_game'])) {
        $id = intval($_POST['game_id']);
        $name = trim($_POST['game_name']);
        $count = intval($_POST['numbers_count']);
        $min_bet = floatval($_POST['min_bet']);
        $bet_step = floatval($_POST['bet_step']);
        
        $multipliers = [];
        for ($i = 0; $i <= $count; $i++) {
            $multipliers[$i] = floatval($_POST['mult_' . $i] ?? 0);
        }
        
        pg_query_params($conn, 
            "UPDATE game_types SET name = $1, numbers_count = $2, min_bet = $3, bet_step = $4, multipliers = $5 WHERE id = $6",
            [$name, $count, $min_bet, $bet_step, json_encode($multipliers), $id]
        );
        header("Location: spellen.php");
        exit();
    }
    
    if (isset($_POST['toggle_game'])) {
        $id = intval($_POST['game_id']);
        pg_query_params($conn, "UPDATE game_types SET active = NOT active WHERE id = $1", [$id]);
        header("Location: spellen.php");
        exit();
    }
    
    if (isset($_POST['delete_game'])) {
        $id = intval($_POST['game_id']);
        pg_query_params($conn, "DELETE FROM game_types WHERE id = $1", [$id]);
        header("Location: spellen.php");
        exit();
    }
}

$games_result = pg_query($conn, "SELECT * FROM game_types ORDER BY numbers_count");
$games = $games_result ? pg_fetch_all($games_result) : [];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spellen Beheer - Lucky Day</title>
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
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1rem;
        }
        .game-card {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 1.25rem;
        }
        .game-card.inactive {
            opacity: 0.6;
        }
        .game-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .game-name {
            font-weight: 600;
            font-size: 1.1rem;
        }
        .game-badge {
            background: #D1FAE5;
            color: #065F46;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .game-badge.inactive {
            background: #FEE2E2;
            color: #991B1B;
        }
        .multiplier-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 0.5rem;
            margin: 1rem 0;
        }
        .multiplier-item {
            background: white;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            padding: 0.5rem;
            text-align: center;
        }
        .multiplier-item .matches {
            font-size: 0.7rem;
            color: #6B7280;
        }
        .multiplier-item .value {
            font-weight: 600;
            color: #10B981;
        }
        .game-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
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
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
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
            max-width: 600px;
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
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo"><span>🍀</span> Lucky Day</div>
            <div class="nav-links">
                <a href="/dashboard.php">Dashboard</a>
                <a href="/spelers.php">Spelers</a>
                <a href="/balans.php">Balans</a>
                <a href="/spellen.php" class="active">Spellen</a>
                <a href="/php/admin_beheer.php">Beheer</a>
            </div>
            <div class="user-menu">
                <span class="user-badge"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="/logout.php" class="btn btn-secondary btn-sm">Uitloggen</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Spellen Beheer</h1>
            <p class="page-subtitle">Configureer speltypes en multipliers</p>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Beschikbare Spellen</h2>
                <button onclick="openAddModal()" class="btn btn-primary">+ Nieuw Spel</button>
            </div>

            <?php if ($games && count($games) > 0): ?>
            <div class="games-grid">
                <?php foreach ($games as $game): ?>
                    <?php $multipliers = json_decode($game['multipliers'], true); ?>
                    <div class="game-card <?php echo $game['active'] ? '' : 'inactive'; ?>">
                        <div class="game-header">
                            <span class="game-name"><?php echo htmlspecialchars($game['name']); ?></span>
                            <span class="game-badge <?php echo $game['active'] ? '' : 'inactive'; ?>">
                                <?php echo $game['active'] ? 'Actief' : 'Inactief'; ?>
                            </span>
                        </div>
                        
                        <p style="font-size: 0.875rem; color: #6B7280;">
                            <?php echo $game['numbers_count']; ?> nummers kiezen | 
                            Min. €<?php echo number_format($game['min_bet'], 2, ',', '.'); ?> |
                            Stap €<?php echo number_format($game['bet_step'], 2, ',', '.'); ?>
                        </p>
                        
                        <div class="multiplier-grid">
                            <?php foreach ($multipliers as $matches => $mult): ?>
                                <?php if ($mult > 0): ?>
                                <div class="multiplier-item">
                                    <div class="matches"><?php echo $matches; ?> goed</div>
                                    <div class="value"><?php echo $mult; ?>×</div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="game-actions">
                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($game)); ?>)" class="btn btn-secondary btn-sm">Bewerken</button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                <button type="submit" name="toggle_game" class="btn btn-secondary btn-sm">
                                    <?php echo $game['active'] ? 'Deactiveren' : 'Activeren'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="text-align: center; color: #6B7280; padding: 2rem;">Nog geen spellen geconfigureerd</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Game Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Nieuw Spel Toevoegen</h3>
                <button class="modal-close" onclick="closeModal('add')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Naam</label>
                    <input type="text" name="game_name" class="form-input" required placeholder="Bijv. 5 getallen">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Aantal nummers</label>
                        <input type="number" name="numbers_count" id="addNumbersCount" class="form-input" required min="1" max="10" value="5" onchange="updateMultiplierFields('add')">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Minimum inzet (€)</label>
                        <input type="number" name="min_bet" class="form-input" required min="0.01" step="0.01" value="1.00">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Inzet stap (€)</label>
                    <input type="number" name="bet_step" class="form-input" required min="0.01" step="0.01" value="1.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Multipliers per aantal goed</label>
                    <div id="addMultipliers" class="multiplier-grid"></div>
                </div>
                <button type="submit" name="add_game" class="btn btn-primary" style="width: 100%;">Spel Toevoegen</button>
            </form>
        </div>
    </div>

    <!-- Edit Game Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Spel Bewerken</h3>
                <button class="modal-close" onclick="closeModal('edit')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="game_id" id="editGameId">
                <div class="form-group">
                    <label class="form-label">Naam</label>
                    <input type="text" name="game_name" id="editGameName" class="form-input" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Aantal nummers</label>
                        <input type="number" name="numbers_count" id="editNumbersCount" class="form-input" required min="1" max="10" onchange="updateMultiplierFields('edit')">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Minimum inzet (€)</label>
                        <input type="number" name="min_bet" id="editMinBet" class="form-input" required min="0.01" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Inzet stap (€)</label>
                    <input type="number" name="bet_step" id="editBetStep" class="form-input" required min="0.01" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">Multipliers per aantal goed</label>
                    <div id="editMultipliers" class="multiplier-grid"></div>
                </div>
                <button type="submit" name="update_game" class="btn btn-primary" style="width: 100%;">Opslaan</button>
            </form>
        </div>
    </div>

    <script>
        let currentEditMultipliers = {};

        function openModal(name) {
            document.getElementById(name + 'Modal').classList.add('active');
        }

        function closeModal(name) {
            document.getElementById(name + 'Modal').classList.remove('active');
        }

        function openAddModal() {
            updateMultiplierFields('add');
            openModal('add');
        }

        function openEditModal(game) {
            document.getElementById('editGameId').value = game.id;
            document.getElementById('editGameName').value = game.name;
            document.getElementById('editNumbersCount').value = game.numbers_count;
            document.getElementById('editMinBet').value = game.min_bet;
            document.getElementById('editBetStep').value = game.bet_step;
            currentEditMultipliers = JSON.parse(game.multipliers);
            updateMultiplierFields('edit');
            openModal('edit');
        }

        function updateMultiplierFields(prefix) {
            const count = parseInt(document.getElementById(prefix + 'NumbersCount').value);
            const container = document.getElementById(prefix + 'Multipliers');
            let html = '';
            
            for (let i = 0; i <= count; i++) {
                const val = prefix === 'edit' ? (currentEditMultipliers[i] || 0) : 0;
                html += `
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.7rem; color: #6B7280;">${i} goed</label>
                        <input type="number" name="mult_${i}" class="form-input" value="${val}" min="0" step="0.01" style="padding: 0.5rem;">
                    </div>
                `;
            }
            container.innerHTML = html;
        }

        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', e => {
                if (e.target === m) closeModal(m.id.replace('Modal', ''));
            });
        });

        updateMultiplierFields('add');
    </script>
</body>
</html>
