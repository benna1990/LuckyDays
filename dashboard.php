<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'Gebruiker';
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

$selected_date = isset($_GET['date']) ? $_GET['date'] : (isset($_GET['selected_date']) ? $_GET['selected_date'] : date('Y-m-d'));
$date_range = generateDateRange($selected_date);

$result = getOrScrapeWinningNumbers($selected_date, $conn);
$winning_numbers = $result['numbers'];
$data_source = $result['source'];
$scrape_error = $result['error'] ?? null;

$players = getAllPlayers($conn);
$rows = getRowsByDate($conn, $selected_date);
$day_stats = getDayStats($conn, $selected_date);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_row':
                $playerId = intval($_POST['player_id']);
                $numbers = array_map('intval', explode(',', $_POST['numbers']));
                $bet = floatval($_POST['bet']);
                
                if ($playerId && count($numbers) >= 1 && count($numbers) <= 10 && $bet > 0) {
                    $rowId = addRow($conn, $playerId, $selected_date, $numbers, $bet, $winning_numbers);
                    if ($rowId) {
                        echo json_encode(['success' => true, 'id' => $rowId]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Kon rij niet opslaan']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Ongeldige invoer']);
                }
                exit;
                
            case 'delete_row':
                $rowId = intval($_POST['row_id']);
                if (deleteRow($conn, $rowId)) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Kon rij niet verwijderen']);
                }
                exit;
                
            case 'add_player':
                $name = trim($_POST['name']);
                $alias = trim($_POST['alias'] ?? '');
                $color = $_POST['color'] ?? '#3B82F6';
                
                if ($name) {
                    $playerId = addPlayer($conn, $name, $alias, $color);
                    if ($playerId) {
                        echo json_encode(['success' => true, 'id' => $playerId, 'name' => $name, 'alias' => $alias, 'color' => $color]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Kon speler niet toevoegen']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Naam is verplicht']);
                }
                exit;
                
            case 'save_numbers':
                $numbers = array_map('intval', explode(',', $_POST['numbers']));
                if (count($numbers) === 20) {
                    saveWinningNumbersToDatabase($selected_date, $numbers, $conn);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Voer exact 20 nummers in']);
                }
                exit;
        }
    }
}

$multipliers = getMultipliers();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LuckyDays Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .number-pill { transition: all 0.15s ease; }
        .number-pill.selected { transform: scale(1.05); }
        .number-pill.winning {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        .toast { animation: slideIn 0.3s ease; }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div id="toast" class="fixed top-4 right-4 z-50 hidden"></div>

    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <span class="text-xl font-semibold text-gray-900">LuckyDays</span>
                    <div class="hidden md:flex space-x-6">
                        <a href="dashboard.php" class="text-gray-900 font-medium">Dashboard</a>
                        <a href="spelers.php" class="text-gray-500 hover:text-gray-900">Spelers</a>
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
        <div class="flex flex-wrap gap-2 mb-8 justify-center">
            <?php foreach ($date_range as $date): ?>
                <a href="?date=<?= $date ?>" 
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-all
                          <?= $date === $selected_date 
                              ? 'bg-gray-900 text-white' 
                              : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' ?>">
                    <?= getDayAndAbbreviatedMonth($date) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Uitslag <?= date('d-m-Y', strtotime($selected_date)) ?></h2>
                <div class="flex items-center gap-2">
                    <?php if ($data_source === 'database'): ?>
                        <span class="text-xs text-gray-400">Uit database</span>
                    <?php elseif ($data_source === 'scraped'): ?>
                        <span class="text-xs text-green-500">Zojuist opgehaald</span>
                    <?php endif; ?>
                    <?php if ($isAdmin): ?>
                        <button onclick="showEditNumbersModal()" class="text-xs text-blue-600 hover:text-blue-700">Bewerken</button>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($data_source === 'none' || empty($winning_numbers)): ?>
                <p class="text-gray-500">Geen uitslag gevonden voor deze datum</p>
            <?php else: ?>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($winning_numbers as $num): ?>
                        <span class="number-pill winning w-10 h-10 flex items-center justify-center rounded-full text-sm font-semibold">
                            <?= htmlspecialchars($num) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl p-5 border border-gray-100">
                <p class="text-sm text-gray-500 mb-1">Totaal Inzet</p>
                <p class="text-2xl font-semibold text-gray-900">&euro;<?= number_format($day_stats['total_bet'], 2, ',', '.') ?></p>
            </div>
            <div class="bg-white rounded-xl p-5 border border-gray-100">
                <p class="text-sm text-gray-500 mb-1">Totaal Winst</p>
                <p class="text-2xl font-semibold text-green-600">&euro;<?= number_format($day_stats['total_winnings'], 2, ',', '.') ?></p>
            </div>
            <div class="bg-white rounded-xl p-5 border border-gray-100">
                <p class="text-sm text-gray-500 mb-1">Netto</p>
                <?php $profit = $day_stats['total_winnings'] - $day_stats['total_bet']; ?>
                <p class="text-2xl font-semibold <?= $profit >= 0 ? 'text-green-600' : 'text-red-500' ?>">
                    &euro;<?= number_format($profit, 2, ',', '.') ?>
                </p>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-semibold text-gray-900">Nieuwe Rij</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Speler</label>
                    <select id="playerSelect" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent">
                        <option value="">Selecteer speler...</option>
                        <?php if ($players): foreach ($players as $player): ?>
                            <option value="<?= $player['id'] ?>" data-color="<?= htmlspecialchars($player['color'] ?? '#3B82F6') ?>">
                                <?= htmlspecialchars($player['name']) ?><?= !empty($player['alias']) ? ' (' . htmlspecialchars($player['alias']) . ')' : '' ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                    <button onclick="showAddPlayerModal()" class="mt-2 text-sm text-blue-600 hover:text-blue-700">+ Nieuwe speler</button>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Inzet (&euro;)</label>
                    <input type="number" id="betInput" step="0.50" min="0.50" value="1.00" 
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Speltype</label>
                    <div id="gameTypeDisplay" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-500">
                        Automatisch (0 nummers)
                    </div>
                </div>
                <div class="flex items-end">
                    <button onclick="saveRow()" class="w-full bg-gray-900 text-white px-4 py-2 rounded-lg font-medium hover:bg-gray-800 transition-colors">
                        Opslaan
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">Gekozen nummers</label>
                    <button onclick="clearNumbers()" class="text-sm text-gray-500 hover:text-gray-700">Wissen</button>
                </div>
                <div id="selectedNumbers" class="min-h-[40px] p-3 bg-gray-50 rounded-lg flex flex-wrap gap-2">
                    <span class="text-gray-400 text-sm">Klik op nummers hieronder...</span>
                </div>
            </div>

            <div class="grid grid-cols-8 sm:grid-cols-10 gap-2" id="numberGrid">
                <?php for ($i = 1; $i <= 80; $i++): ?>
                    <?php $isWinning = in_array($i, array_map('intval', $winning_numbers)); ?>
                    <button onclick="toggleNumber(<?= $i ?>)" 
                            data-num="<?= $i ?>"
                            class="number-pill w-full aspect-square flex items-center justify-center rounded-lg text-sm font-medium border transition-all
                                   <?= $isWinning ? 'border-green-300 bg-green-50 text-green-700' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </button>
                <?php endfor; ?>
            </div>
            
            <p class="text-xs text-gray-400 mt-4">
                Shortcuts: <kbd class="px-1.5 py-0.5 bg-gray-100 rounded text-gray-600">Enter</kbd> opslaan &bull; 
                <kbd class="px-1.5 py-0.5 bg-gray-100 rounded text-gray-600">Backspace</kbd> laatste verwijderen &bull;
                <kbd class="px-1.5 py-0.5 bg-gray-100 rounded text-gray-600">Esc</kbd> wissen
            </p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-6">Rijen van <?= date('d-m-Y', strtotime($selected_date)) ?></h2>
            
            <?php if (empty($rows)): ?>
                <p class="text-gray-500 text-center py-8">Nog geen rijen ingevoerd voor deze datum</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-500 border-b border-gray-100">
                                <th class="pb-3 font-medium">Speler</th>
                                <th class="pb-3 font-medium">Nummers</th>
                                <th class="pb-3 font-medium">Speltype</th>
                                <th class="pb-3 font-medium text-right">Inzet</th>
                                <th class="pb-3 font-medium text-center">Treffers</th>
                                <th class="pb-3 font-medium text-right">Winst</th>
                                <th class="pb-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($rows as $row): 
                                $rowNumbers = explode(',', $row['numbers']);
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-3 h-3 rounded-full" style="background: <?= htmlspecialchars($row['player_color'] ?? '#3B82F6') ?>"></span>
                                        <span class="font-medium text-gray-900"><?= htmlspecialchars($row['player_name']) ?></span>
                                        <?php if (!empty($row['player_alias'])): ?>
                                            <span class="text-gray-400 text-sm">(<?= htmlspecialchars($row['player_alias']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($rowNumbers as $num): 
                                            $isMatch = in_array($num, array_map('intval', $winning_numbers));
                                        ?>
                                            <span class="w-7 h-7 flex items-center justify-center rounded-full text-xs font-medium
                                                         <?= $isMatch ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-600' ?>">
                                                <?= $num ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="py-3 text-sm text-gray-600"><?= htmlspecialchars($row['game_type'] ?? '') ?></td>
                                <td class="py-3 text-sm text-gray-900 text-right">&euro;<?= number_format($row['bet'], 2, ',', '.') ?></td>
                                <td class="py-3 text-center">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium
                                                 <?= ($row['matches'] ?? 0) > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                                        <?= $row['matches'] ?? 0 ?>
                                    </span>
                                </td>
                                <td class="py-3 text-right font-medium <?= ($row['winnings'] ?? 0) > 0 ? 'text-green-600' : 'text-gray-400' ?>">
                                    &euro;<?= number_format($row['winnings'] ?? 0, 2, ',', '.') ?>
                                </td>
                                <td class="py-3 text-right">
                                    <button onclick="deleteRow(<?= $row['id'] ?>)" class="text-gray-400 hover:text-red-500 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="addPlayerModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Nieuwe Speler</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Naam</label>
                    <input type="text" id="newPlayerName" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alias (optioneel)</label>
                    <input type="text" id="newPlayerAlias" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kleur</label>
                    <input type="color" id="newPlayerColor" value="#3B82F6" class="w-full h-10 rounded-lg cursor-pointer">
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button onclick="hideAddPlayerModal()" class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">Annuleren</button>
                <button onclick="addPlayer()" class="flex-1 px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800">Toevoegen</button>
            </div>
        </div>
    </div>

    <div id="editNumbersModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Winnende Nummers Bewerken</h3>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">20 nummers (kommagescheiden)</label>
                <textarea id="editNumbersInput" rows="4" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent"><?= implode(',', $winning_numbers) ?></textarea>
            </div>
            <div class="flex gap-3 mt-6">
                <button onclick="hideEditNumbersModal()" class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">Annuleren</button>
                <button onclick="saveNumbers()" class="flex-1 px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800">Opslaan</button>
            </div>
        </div>
    </div>

    <script>
        let selectedNumbers = [];
        const winningNumbers = <?= json_encode(array_map('intval', $winning_numbers)) ?>;
        const maxNumbers = 10;

        function toggleNumber(num) {
            const idx = selectedNumbers.indexOf(num);
            if (idx > -1) {
                selectedNumbers.splice(idx, 1);
            } else if (selectedNumbers.length < maxNumbers) {
                selectedNumbers.push(num);
            } else {
                showToast('Maximum ' + maxNumbers + ' nummers', 'warning');
                return;
            }
            updateUI();
        }

        function clearNumbers() {
            selectedNumbers = [];
            updateUI();
        }

        function updateUI() {
            document.querySelectorAll('#numberGrid button').forEach(btn => {
                const num = parseInt(btn.dataset.num);
                const isSelected = selectedNumbers.includes(num);
                
                btn.classList.remove('ring-2', 'ring-gray-900', 'bg-gray-900', 'text-white');
                
                if (isSelected) {
                    btn.classList.add('ring-2', 'ring-gray-900', 'bg-gray-900', 'text-white');
                }
            });

            const container = document.getElementById('selectedNumbers');
            if (selectedNumbers.length === 0) {
                container.innerHTML = '<span class="text-gray-400 text-sm">Klik op nummers hieronder...</span>';
            } else {
                container.innerHTML = selectedNumbers.map(num => {
                    const isWinning = winningNumbers.includes(num);
                    return `<span class="w-8 h-8 flex items-center justify-center rounded-full text-sm font-medium cursor-pointer
                                  ${isWinning ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-700'}"
                                  onclick="toggleNumber(${num})">${num}</span>`;
                }).join('');
            }

            const gameType = selectedNumbers.length > 0 ? selectedNumbers.length + '-getallen' : 'Automatisch';
            document.getElementById('gameTypeDisplay').textContent = gameType + ' (' + selectedNumbers.length + ' nummers)';
        }

        function saveRow() {
            const playerId = document.getElementById('playerSelect').value;
            const bet = parseFloat(document.getElementById('betInput').value);

            if (!playerId) {
                showToast('Selecteer een speler', 'error');
                return;
            }
            if (selectedNumbers.length < 1) {
                showToast('Selecteer minimaal 1 nummer', 'error');
                return;
            }
            if (bet <= 0) {
                showToast('Voer een geldige inzet in', 'error');
                return;
            }

            fetch('dashboard.php?date=<?= $selected_date ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add_row&player_id=${playerId}&numbers=${selectedNumbers.join(',')}&bet=${bet}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Rij opgeslagen!', 'success');
                    selectedNumbers = [];
                    updateUI();
                    location.reload();
                } else {
                    showToast(data.error || 'Fout bij opslaan', 'error');
                }
            })
            .catch(() => showToast('Netwerkfout', 'error'));
        }

        function deleteRow(id) {
            if (!confirm('Weet je zeker dat je deze rij wilt verwijderen?')) return;
            
            fetch('dashboard.php?date=<?= $selected_date ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_row&row_id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Rij verwijderd', 'success');
                    location.reload();
                } else {
                    showToast(data.error || 'Fout bij verwijderen', 'error');
                }
            });
        }

        function showAddPlayerModal() {
            document.getElementById('addPlayerModal').classList.remove('hidden');
            document.getElementById('newPlayerName').focus();
        }

        function hideAddPlayerModal() {
            document.getElementById('addPlayerModal').classList.add('hidden');
        }

        function showEditNumbersModal() {
            document.getElementById('editNumbersModal').classList.remove('hidden');
        }

        function hideEditNumbersModal() {
            document.getElementById('editNumbersModal').classList.add('hidden');
        }

        function saveNumbers() {
            const input = document.getElementById('editNumbersInput').value;
            
            fetch('dashboard.php?date=<?= $selected_date ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=save_numbers&numbers=${encodeURIComponent(input)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Nummers opgeslagen!', 'success');
                    location.reload();
                } else {
                    showToast(data.error || 'Fout bij opslaan', 'error');
                }
            });
        }

        function addPlayer() {
            const name = document.getElementById('newPlayerName').value.trim();
            const alias = document.getElementById('newPlayerAlias').value.trim();
            const color = document.getElementById('newPlayerColor').value;

            if (!name) {
                showToast('Naam is verplicht', 'error');
                return;
            }

            fetch('dashboard.php?date=<?= $selected_date ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add_player&name=${encodeURIComponent(name)}&alias=${encodeURIComponent(alias)}&color=${encodeURIComponent(color)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Speler toegevoegd!', 'success');
                    const select = document.getElementById('playerSelect');
                    const option = document.createElement('option');
                    option.value = data.id;
                    option.textContent = data.name + (data.alias ? ' (' + data.alias + ')' : '');
                    select.appendChild(option);
                    select.value = data.id;
                    hideAddPlayerModal();
                    document.getElementById('newPlayerName').value = '';
                    document.getElementById('newPlayerAlias').value = '';
                } else {
                    showToast(data.error || 'Fout bij toevoegen', 'error');
                }
            });
        }

        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-gray-800'
            };
            toast.className = `toast px-4 py-2 rounded-lg text-white text-sm font-medium ${colors[type]}`;
            toast.textContent = message;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 3000);
        }

        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') {
                if (e.key === 'Enter' && e.target.id !== 'newPlayerName' && e.target.id !== 'newPlayerAlias' && e.target.id !== 'editNumbersInput') {
                    e.preventDefault();
                    saveRow();
                }
                return;
            }

            if (e.key === 'Enter') {
                e.preventDefault();
                saveRow();
            } else if (e.key === 'Backspace') {
                e.preventDefault();
                if (selectedNumbers.length > 0) {
                    selectedNumbers.pop();
                    updateUI();
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                if (!document.getElementById('addPlayerModal').classList.contains('hidden')) {
                    hideAddPlayerModal();
                } else if (!document.getElementById('editNumbersModal').classList.contains('hidden')) {
                    hideEditNumbersModal();
                } else {
                    clearNumbers();
                }
            }
        });

        updateUI();
    </script>
</body>
</html>
