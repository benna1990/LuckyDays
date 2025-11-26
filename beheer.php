<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Get all players with their lifetime stats
$playersQuery = "
    SELECT
        p.id, p.name, p.color, p.created_at,
        COUNT(DISTINCT b.id) as total_bons,
        COUNT(r.id) as total_rijen,
        COALESCE(SUM(r.bet), 0) as total_bet,
        COALESCE(SUM(r.winnings), 0) as total_winnings,
        COALESCE(SUM(r.winnings), 0) - COALESCE(SUM(r.bet), 0) as saldo
    FROM players p
    LEFT JOIN bons b ON p.id = b.player_id
    LEFT JOIN rijen r ON r.bon_id = b.id
    GROUP BY p.id, p.name, p.color, p.created_at
    ORDER BY p.name ASC
";

$playersResult = db_query($playersQuery, []);
$players = db_fetch_all($playersResult) ?: [];

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

        case 'change_password':
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                echo json_encode(['success' => false, 'error' => 'Alle velden zijn verplicht']);
                exit();
            }

            // Default admin credentials check
            $result = pg_query_params($conn, "SELECT password FROM admin WHERE username = $1", ['admin']);
            $admin = pg_fetch_assoc($result);

            if (!$admin || !password_verify($currentPassword, $admin['password'])) {
                echo json_encode(['success' => false, 'error' => 'Huidig wachtwoord is onjuist']);
                exit();
            }

            if ($newPassword !== $confirmPassword) {
                echo json_encode(['success' => false, 'error' => 'Nieuwe wachtwoorden komen niet overeen']);
                exit();
            }

            if (strlen($newPassword) < 6) {
                echo json_encode(['success' => false, 'error' => 'Wachtwoord moet minimaal 6 tekens zijn']);
                exit();
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateResult = pg_query_params($conn, "UPDATE admin SET password = $1 WHERE username = $2", [$hashedPassword, 'admin']);

            if ($updateResult) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Kon wachtwoord niet bijwerken']);
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
        .tab-active { background: #10b981 !important; color: white !important; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="dashboard.php" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                    <a href="dashboard.php" class="flex items-center gap-3 hover:opacity-80 transition">
                        <span class="text-2xl">🍀</span>
                        <h1 class="text-lg font-semibold text-gray-800">Lucky Day</h1>
                    </a>
                </div>
                <div class="flex items-center gap-2">
                    <a href="dashboard.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Dashboard</a>
                    <a href="weekoverzicht.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Weekoverzicht</a>
                    <a href="balans.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Balans</a>
                    <a href="beheer.php" class="px-3 py-1.5 text-sm text-emerald-600 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition font-medium">Beheer</a>
                    <a href="logout.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Uitloggen</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Beheer</h2>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6">
            <button onclick="showTab('spelers')" id="tab-spelers" class="tab-active px-4 py-2 rounded-lg transition text-sm font-medium">
                Spelers
            </button>
            <button onclick="showTab('instellingen')" id="tab-instellingen" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                Instellingen
            </button>
        </div>

        <!-- Spelers Tab -->
        <div id="content-spelers" class="tab-content">
            <div class="card p-6 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Spelers Overzicht</h3>
                    <button onclick="openAddPlayerModal()" class="px-4 py-2 bg-emerald-500 text-white text-sm font-medium rounded-lg hover:bg-emerald-600 transition">
                        + Nieuwe Speler
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-100 uppercase tracking-wide">
                                <th class="pb-3 font-medium">Speler</th>
                                <th class="pb-3 font-medium text-right">Totaal Bonnen</th>
                                <th class="pb-3 font-medium text-right">Totaal Rijen</th>
                                <th class="pb-3 font-medium text-right">Totaal Inzet</th>
                                <th class="pb-3 font-medium text-right">Totaal Winst</th>
                                <th class="pb-3 font-medium text-right">Balans</th>
                                <th class="pb-3 font-medium text-center">Acties</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($players as $player):
                                $saldo = floatval($player['saldo']);
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-3 h-3 rounded-full" style="background: <?= htmlspecialchars($player['color']) ?>"></span>
                                        <span class="font-medium text-gray-800"><?= htmlspecialchars($player['name']) ?></span>
                                    </div>
                                </td>
                                <td class="py-3 text-right text-gray-600"><?= $player['total_bons'] ?></td>
                                <td class="py-3 text-right text-gray-600"><?= $player['total_rijen'] ?></td>
                                <td class="py-3 text-right text-gray-900">€<?= number_format($player['total_bet'], 2, ',', '.') ?></td>
                                <td class="py-3 text-right text-gray-900">€<?= number_format($player['total_winnings'], 2, ',', '.') ?></td>
                                <td class="py-3 text-right font-semibold <?= $saldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                                    <?= $saldo >= 0 ? '+' : '' ?>€<?= number_format($saldo, 2, ',', '.') ?>
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <button onclick='editPlayer(<?= json_encode($player) ?>)' class="text-blue-600 hover:text-blue-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button onclick="deletePlayer(<?= $player['id'] ?>, '<?= htmlspecialchars($player['name']) ?>')" class="text-red-600 hover:text-red-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Instellingen Tab -->
        <div id="content-instellingen" class="tab-content hidden">
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-6">Wachtwoord Wijzigen</h3>
                <form id="password-form" onsubmit="changePassword(event)" class="max-w-md space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Huidig Wachtwoord</label>
                        <input type="password" name="current_password" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nieuw Wachtwoord</label>
                        <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="text-xs text-gray-500 mt-1">Minimaal 6 tekens</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bevestig Nieuw Wachtwoord</label>
                        <input type="password" name="confirm_password" required minlength="6" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <button type="submit" class="px-6 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium">
                        Wachtwoord Wijzigen
                    </button>
                </form>
            </div>

            <div class="card p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Systeem Informatie</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Database:</span>
                        <span class="font-medium">PostgreSQL</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Totaal Spelers:</span>
                        <span class="font-medium"><?= count($players) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Admin Gebruiker:</span>
                        <span class="font-medium">admin</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Player Modal -->
    <div id="player-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
            <h3 id="modal-title" class="text-lg font-semibold text-gray-800 mb-4">Nieuwe Speler</h3>
            <form id="player-form" onsubmit="savePlayer(event)">
                <input type="hidden" id="player-id" name="id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Naam</label>
                        <input type="text" id="player-name" name="name" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kleur</label>
                        <input type="color" id="player-color" name="color" value="#3B82F6" class="w-full h-12 rounded-lg cursor-pointer">
                    </div>
                </div>
                <div class="flex gap-2 mt-6">
                    <button type="button" onclick="closePlayerModal()" class="flex-1 px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                        Annuleren
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 text-white bg-emerald-500 rounded-lg hover:bg-emerald-600 transition">
                        Opslaan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('[id^="tab-"]').forEach(el => {
                el.classList.remove('tab-active', 'bg-emerald-500', 'text-white');
                el.classList.add('bg-gray-100', 'text-gray-700');
            });

            // Show selected tab
            document.getElementById('content-' + tab).classList.remove('hidden');
            document.getElementById('tab-' + tab).classList.add('tab-active');
            document.getElementById('tab-' + tab).classList.remove('bg-gray-100', 'text-gray-700');
        }

        function openAddPlayerModal() {
            document.getElementById('modal-title').textContent = 'Nieuwe Speler';
            document.getElementById('player-form').reset();
            document.getElementById('player-id').value = '';
            document.getElementById('player-modal').classList.remove('hidden');
            document.getElementById('player-modal').classList.add('flex');
        }

        function editPlayer(player) {
            document.getElementById('modal-title').textContent = 'Speler Bewerken';
            document.getElementById('player-id').value = player.id;
            document.getElementById('player-name').value = player.name;
            document.getElementById('player-color').value = player.color;
            document.getElementById('player-modal').classList.remove('hidden');
            document.getElementById('player-modal').classList.add('flex');
        }

        function closePlayerModal() {
            document.getElementById('player-modal').classList.add('hidden');
            document.getElementById('player-modal').classList.remove('flex');
        }

        async function savePlayer(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const playerId = formData.get('id');
            formData.append('action', playerId ? 'update_player' : 'add_player');

            try {
                const response = await fetch('beheer.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Er is een fout opgetreden');
                }
            } catch (error) {
                alert('Er is een fout opgetreden');
                console.error(error);
            }
        }

        async function deletePlayer(id, name) {
            if (!confirm(`Weet je zeker dat je ${name} wilt verwijderen?`)) return;

            const formData = new FormData();
            formData.append('action', 'delete_player');
            formData.append('id', id);

            try {
                const response = await fetch('beheer.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Kon speler niet verwijderen');
                }
            } catch (error) {
                alert('Er is een fout opgetreden');
                console.error(error);
            }
        }

        async function changePassword(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'change_password');

            try {
                const response = await fetch('beheer.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    alert('Wachtwoord succesvol gewijzigd!');
                    e.target.reset();
                } else {
                    alert(data.error || 'Er is een fout opgetreden');
                }
            } catch (error) {
                alert('Er is een fout opgetreden');
                console.error(error);
            }
        }
    </script>
</body>
</html>
