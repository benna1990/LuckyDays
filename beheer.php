<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

$players = getAllPlayers($conn);
$allBonnen = pg_fetch_all(pg_query($conn, "SELECT b.*, p.name as player_name, p.color as player_color FROM bons b JOIN players p ON b.player_id = p.id ORDER BY b.date DESC, b.created_at DESC LIMIT 50"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_player':
            $name = trim($_POST['name'] ?? '');
            $color = $_POST['color'] ?? '#3B82F6';
            
            if (empty($name)) {
                echo json_encode(['success' => false, 'error' => 'Naam is verplicht']);
                exit();
            }
            
            $result = addPlayer($conn, $name, $color);
            echo json_encode($result);
            exit();
            
        case 'update_player':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $color = $_POST['color'] ?? '#3B82F6';
            
            if ($id <= 0 || empty($name)) {
                echo json_encode(['success' => false, 'error' => 'Ongeldige gegevens']);
                exit();
            }
            
            $result = updatePlayer($conn, $id, $name, $color);
            echo json_encode($result);
            exit();
            
        case 'delete_player':
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Ongeldige speler']);
                exit();
            }
            
            if (deletePlayer($conn, $id)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Kon speler niet verwijderen']);
            }
            exit();
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beheer - Lucky Day</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 12px rgba(0,0,0,0.04); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-6xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="dashboard.php" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                    <span class="text-2xl">🍀</span>
                    <h1 class="text-lg font-semibold text-gray-800">Beheer</h1>
                </div>
                <div class="flex items-center gap-2">
                    <a href="dashboard.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Dashboard</a>
                    <a href="logout.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Uitloggen</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-gray-800">Spelers</h2>
                    <button onclick="openAddPlayerModal()" class="flex items-center gap-2 px-4 py-2 bg-emerald-500 text-white text-sm font-medium rounded-lg hover:bg-emerald-600 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Nieuwe speler
                    </button>
                </div>
                
                <p class="text-sm text-gray-500 mb-4">Spelernamen moeten uniek zijn</p>
                
                <div id="playersContainer">
                    <?php if (empty($players) || $players === false): ?>
                        <p class="text-center py-8 text-gray-400">Nog geen spelers</p>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach ($players as $player): ?>
                                <div class="player-item flex items-center justify-between p-3 bg-gray-50 rounded-xl" data-player-id="<?= $player['id'] ?>">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-medium" style="background: <?= htmlspecialchars($player['color']) ?>">
                                            <?= strtoupper(substr($player['name'], 0, 1)) ?>
                                        </div>
                                        <span class="font-medium text-gray-800"><?= htmlspecialchars($player['name']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button onclick="editPlayer(<?= $player['id'] ?>, '<?= htmlspecialchars(addslashes($player['name'])) ?>', '<?= htmlspecialchars($player['color']) ?>')" 
                                                class="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button onclick="deletePlayer(<?= $player['id'] ?>, '<?= htmlspecialchars(addslashes($player['name'])) ?>')" 
                                                class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-6">Recente bonnen</h2>
                
                <div id="bonnenContainer">
                    <?php if (empty($allBonnen) || $allBonnen === false): ?>
                        <p class="text-center py-8 text-gray-400">Nog geen bonnen</p>
                    <?php else: ?>
                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            <?php foreach ($allBonnen as $bon): ?>
                                <a href="bon.php?id=<?= $bon['id'] ?>" class="block p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-medium" style="background: <?= htmlspecialchars($bon['player_color']) ?>">
                                                <?= strtoupper(substr($bon['player_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-800 text-sm"><?= htmlspecialchars($bon['name']) ?></div>
                                                <div class="text-xs text-gray-500"><?= htmlspecialchars($bon['player_name']) ?></div>
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-400"><?= getDayAndAbbreviatedMonth($bon['date']) ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <div id="add-player-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Nieuwe speler</h3>
                <button onclick="closeAddPlayerModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            
            <form id="addPlayerForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Naam</label>
                    <input type="text" id="newPlayerName" required placeholder="Naam van speler..." class="w-full px-4 py-3 bg-gray-50 border-0 rounded-xl focus:ring-2 focus:ring-emerald-500">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kleur</label>
                    <input type="color" id="newPlayerColor" value="#3B82F6" class="w-full h-12 rounded-xl cursor-pointer">
                </div>
                
                <div id="addPlayerError" class="hidden mb-4 p-3 bg-red-50 text-red-600 text-sm rounded-lg"></div>
                
                <div class="flex gap-3">
                    <button type="button" onclick="closeAddPlayerModal()" class="flex-1 px-4 py-3 text-gray-700 font-medium bg-gray-100 rounded-xl hover:bg-gray-200 transition">Annuleren</button>
                    <button type="submit" class="flex-1 px-4 py-3 text-white font-medium bg-emerald-500 rounded-xl hover:bg-emerald-600 transition">Toevoegen</button>
                </div>
            </form>
        </div>
    </div>

    <div id="edit-player-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Speler bewerken</h3>
                <button onclick="closeEditPlayerModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            
            <form id="editPlayerForm">
                <input type="hidden" id="editPlayerId">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Naam</label>
                    <input type="text" id="editPlayerName" required class="w-full px-4 py-3 bg-gray-50 border-0 rounded-xl focus:ring-2 focus:ring-emerald-500">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kleur</label>
                    <input type="color" id="editPlayerColor" class="w-full h-12 rounded-xl cursor-pointer">
                </div>
                
                <div id="editPlayerError" class="hidden mb-4 p-3 bg-red-50 text-red-600 text-sm rounded-lg"></div>
                
                <div class="flex gap-3">
                    <button type="button" onclick="closeEditPlayerModal()" class="flex-1 px-4 py-3 text-gray-700 font-medium bg-gray-100 rounded-xl hover:bg-gray-200 transition">Annuleren</button>
                    <button type="submit" class="flex-1 px-4 py-3 text-white font-medium bg-emerald-500 rounded-xl hover:bg-emerald-600 transition">Opslaan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddPlayerModal() {
            document.getElementById('add-player-modal').classList.remove('hidden');
            document.getElementById('add-player-modal').classList.add('flex');
            document.getElementById('newPlayerName').focus();
        }
        
        function closeAddPlayerModal() {
            document.getElementById('add-player-modal').classList.add('hidden');
            document.getElementById('add-player-modal').classList.remove('flex');
            document.getElementById('newPlayerName').value = '';
            document.getElementById('newPlayerColor').value = '#3B82F6';
            document.getElementById('addPlayerError').classList.add('hidden');
        }
        
        function editPlayer(id, name, color) {
            document.getElementById('editPlayerId').value = id;
            document.getElementById('editPlayerName').value = name;
            document.getElementById('editPlayerColor').value = color;
            document.getElementById('edit-player-modal').classList.remove('hidden');
            document.getElementById('edit-player-modal').classList.add('flex');
            document.getElementById('editPlayerName').focus();
        }
        
        function closeEditPlayerModal() {
            document.getElementById('edit-player-modal').classList.add('hidden');
            document.getElementById('edit-player-modal').classList.remove('flex');
            document.getElementById('editPlayerError').classList.add('hidden');
        }
        
        document.getElementById('addPlayerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const name = document.getElementById('newPlayerName').value.trim();
            const color = document.getElementById('newPlayerColor').value;
            const errorEl = document.getElementById('addPlayerError');
            
            const formData = new FormData();
            formData.append('action', 'add_player');
            formData.append('name', name);
            formData.append('color', color);
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    errorEl.textContent = data.error || 'Fout bij toevoegen';
                    errorEl.classList.remove('hidden');
                }
            } catch (e) {
                errorEl.textContent = 'Fout bij toevoegen';
                errorEl.classList.remove('hidden');
            }
        });
        
        document.getElementById('editPlayerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const id = document.getElementById('editPlayerId').value;
            const name = document.getElementById('editPlayerName').value.trim();
            const color = document.getElementById('editPlayerColor').value;
            const errorEl = document.getElementById('editPlayerError');
            
            const formData = new FormData();
            formData.append('action', 'update_player');
            formData.append('id', id);
            formData.append('name', name);
            formData.append('color', color);
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    errorEl.textContent = data.error || 'Fout bij opslaan';
                    errorEl.classList.remove('hidden');
                }
            } catch (e) {
                errorEl.textContent = 'Fout bij opslaan';
                errorEl.classList.remove('hidden');
            }
        });
        
        async function deletePlayer(id, name) {
            if (!confirm(`Weet je zeker dat je "${name}" wilt verwijderen? Alle gekoppelde bonnen worden ook verwijderd.`)) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_player');
            formData.append('id', id);
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
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
        
        document.getElementById('add-player-modal').addEventListener('click', function(e) {
            if (e.target === this) closeAddPlayerModal();
        });
        
        document.getElementById('edit-player-modal').addEventListener('click', function(e) {
            if (e.target === this) closeEditPlayerModal();
        });
    </script>
</body>
</html>
