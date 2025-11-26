<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['username'])) {
    header('Location: /index.php');
    exit();
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$username = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'Gebruiker';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_player'])) {
        $name = trim($_POST['player_name']);
        $color = $_POST['player_color'] ?? '#10B981';
        
        if (!empty($name)) {
            pg_query_params($conn, 
                "INSERT INTO players (name, alias, color, created_at) VALUES ($1, '', $2, NOW())",
                [$name, $color]
            );
        }
        header("Location: spelers.php");
        exit();
    }
    
    if (isset($_POST['update_player'])) {
        $id = intval($_POST['player_id']);
        $name = trim($_POST['player_name']);
        $color = $_POST['player_color'] ?? '#10B981';
        
        pg_query_params($conn, 
            "UPDATE players SET name = $1, color = $2 WHERE id = $3",
            [$name, $color, $id]
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

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;

$where = '';
$params = [];
if (!empty($search)) {
    $where = "WHERE LOWER(name) LIKE $1 OR id::text LIKE $1";
    $params = ['%' . strtolower($search) . '%'];
}

$count_result = pg_query_params($conn, "SELECT COUNT(*) FROM players $where", $params);
$total_players = pg_fetch_result($count_result, 0, 0);
$total_pages = ceil($total_players / $per_page);
$offset = ($page - 1) * $per_page;

$sql = "SELECT p.id, p.name, p.color,
               (SELECT COALESCE(SUM(bet), 0) FROM bons WHERE player_id = p.id) as total_bet,
               (SELECT COALESCE(SUM(winnings), 0) FROM bons WHERE player_id = p.id) as total_winnings,
               (SELECT COUNT(*) FROM bons WHERE player_id = p.id) as total_bons
        FROM players p $where ORDER BY p.id DESC LIMIT $per_page OFFSET $offset";

$players_result = pg_query_params($conn, $sql, $params);
$players = $players_result ? pg_fetch_all($players_result) : [];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spelers - LuckyDays Casino</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <span class="text-xl font-semibold text-gray-900">LuckyDays <span class="text-xs text-gray-400">Casino</span></span>
                    <div class="hidden md:flex space-x-6">
                        <a href="dashboard.php" class="text-gray-500 hover:text-gray-900">Dashboard</a>
                        <a href="weekoverzicht.php" class="text-gray-500 hover:text-gray-900">Week</a>
                        <a href="spelers.php" class="text-gray-900 font-medium">Spelers</a>
                        <a href="balans.php" class="text-gray-500 hover:text-gray-900">Balans</a>
                        <?php if ($isAdmin): ?>
                        <a href="spellen.php" class="text-gray-500 hover:text-gray-900">Spellen</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center">
                    <span class="text-sm text-gray-500 mr-4"><?= htmlspecialchars($username) ?></span>
                    <a href="logout.php" class="text-sm text-gray-500 hover:text-gray-900">Uitloggen</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Spelers</h1>
                <p class="text-sm text-gray-500"><?= $total_players ?> totaal</p>
            </div>
            <button onclick="openModal('add')" class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800">
                + Nieuwe Speler
            </button>
        </div>

        <!-- Search -->
        <div class="bg-white rounded-xl p-4 border border-gray-100 mb-6">
            <form method="GET" class="flex items-center gap-4">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Zoek op naam of #nummer..." 
                       class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm">
                <button type="submit" class="px-4 py-2 bg-gray-100 rounded-lg text-sm font-medium hover:bg-gray-200">Zoeken</button>
                <?php if (!empty($search)): ?>
                    <a href="spelers.php" class="text-sm text-gray-500 hover:text-gray-700">Wissen</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Players Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
            <?php if ($players && count($players) > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 border-b border-gray-100 uppercase tracking-wide">
                            <th class="px-6 py-4 font-medium">#</th>
                            <th class="px-6 py-4 font-medium">Speler</th>
                            <th class="px-6 py-4 font-medium text-right">Rijen</th>
                            <th class="px-6 py-4 font-medium text-right">Inzet</th>
                            <th class="px-6 py-4 font-medium text-right">Winst</th>
                            <th class="px-6 py-4 font-medium text-right">Saldo</th>
                            <th class="px-6 py-4 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($players as $player): 
                            $saldo = floatval($player['total_winnings']) - floatval($player['total_bet']);
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 text-gray-500"><?= $player['id'] ?></td>
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs font-semibold" 
                                          style="background: <?= htmlspecialchars($player['color'] ?? '#3B82F6') ?>">
                                        <?= strtoupper(substr($player['name'], 0, 1)) ?>
                                    </span>
                                    <span class="font-medium text-gray-900"><?= htmlspecialchars($player['name']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-3 text-right text-gray-600"><?= $player['total_bons'] ?></td>
                            <td class="px-6 py-3 text-right text-gray-900">&euro;<?= number_format($player['total_bet'], 2, ',', '.') ?></td>
                            <td class="px-6 py-3 text-right text-gray-900">&euro;<?= number_format($player['total_winnings'], 2, ',', '.') ?></td>
                            <td class="px-6 py-3 text-right font-semibold <?= $saldo > 0 ? 'text-green-600' : ($saldo < 0 ? 'text-red-600' : 'text-gray-600') ?>">
                                <?= $saldo >= 0 ? '+' : '' ?>&euro;<?= number_format($saldo, 2, ',', '.') ?>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="speler_detail.php?id=<?= $player['id'] ?>" class="text-blue-600 hover:text-blue-700 text-xs">Details</a>
                                    <button onclick="openEditModal(<?= htmlspecialchars(json_encode($player)) ?>)" class="text-gray-500 hover:text-gray-700 text-xs">Bewerk</button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Speler verwijderen?');">
                                        <input type="hidden" name="player_id" value="<?= $player['id'] ?>">
                                        <button type="submit" name="delete_player" class="text-red-500 hover:text-red-700 text-xs">Verwijder</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="flex items-center justify-center gap-2 p-4 border-t border-gray-100">
                <?php if ($page > 1): ?>
                    <a href="?search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>" class="px-3 py-1 border border-gray-200 rounded text-sm hover:bg-gray-50">Vorige</a>
                <?php endif; ?>
                <span class="text-sm text-gray-500">Pagina <?= $page ?> van <?= $total_pages ?></span>
                <?php if ($page < $total_pages): ?>
                    <a href="?search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>" class="px-3 py-1 border border-gray-200 rounded text-sm hover:bg-gray-50">Volgende</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="p-8 text-center">
                <p class="text-gray-500">Geen spelers gevonden</p>
                <button onclick="openModal('add')" class="mt-4 px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-medium">+ Eerste Speler</button>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add Player Modal -->
    <div class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center" id="addModal">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Nieuwe Speler</h3>
            <p class="text-xs text-gray-500 mb-4">Spelersnummer wordt automatisch toegekend</p>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Naam</label>
                    <input type="text" name="player_name" class="w-full px-3 py-2 border border-gray-200 rounded-lg" required placeholder="Naam">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kleur</label>
                    <input type="color" name="player_color" value="#3B82F6" class="w-full h-10 rounded-lg cursor-pointer">
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="closeModal('add')" class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">Annuleren</button>
                    <button type="submit" name="add_player" class="flex-1 px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800">Toevoegen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Player Modal -->
    <div class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center" id="editModal">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Speler Bewerken</h3>
            <form method="POST">
                <input type="hidden" name="player_id" id="editPlayerId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Speler #</label>
                    <input type="text" id="editPlayerNumber" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-500" disabled>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Naam</label>
                    <input type="text" name="player_name" id="editPlayerName" class="w-full px-3 py-2 border border-gray-200 rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kleur</label>
                    <input type="color" name="player_color" id="editPlayerColor" class="w-full h-10 rounded-lg cursor-pointer">
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="closeModal('edit')" class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">Annuleren</button>
                    <button type="submit" name="update_player" class="flex-1 px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800">Opslaan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(name) {
            document.getElementById(name + 'Modal').classList.remove('hidden');
        }
        function closeModal(name) {
            document.getElementById(name + 'Modal').classList.add('hidden');
        }
        function openEditModal(player) {
            document.getElementById('editPlayerId').value = player.id;
            document.getElementById('editPlayerNumber').value = '#' + player.id;
            document.getElementById('editPlayerName').value = player.name;
            document.getElementById('editPlayerColor').value = player.color || '#3B82F6';
            openModal('edit');
        }
        document.querySelectorAll('[id$="Modal"]').forEach(m => {
            m.addEventListener('click', e => { if (e.target === m) closeModal(m.id.replace('Modal', '')); });
        });
    </script>
</body>
</html>
