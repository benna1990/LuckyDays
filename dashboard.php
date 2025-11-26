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
                $date = $_POST['date'] ?? $selected_date;
                
                if ($playerId && count($numbers) >= 1 && count($numbers) <= 10 && $bet > 0) {
                    $rowId = addRow($conn, $playerId, $date, $numbers, $bet, $winning_numbers);
                    if ($rowId) {
                        $rowData = pg_fetch_assoc(pg_query_params($conn, 
                            "SELECT b.*, p.name as player_name, p.alias as player_alias, p.color as player_color 
                             FROM bons b JOIN players p ON b.player_id = p.id WHERE b.id = $1", [$rowId]));
                        echo json_encode(['success' => true, 'id' => $rowId, 'row' => $rowData]);
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
                
            case 'search_players':
                $query = trim($_POST['query'] ?? '');
                $results = [];
                if ($players) {
                    foreach ($players as $p) {
                        $searchStr = strtolower($p['name'] . ' ' . ($p['alias'] ?? '') . ' ' . $p['id']);
                        if (empty($query) || strpos($searchStr, strtolower($query)) !== false) {
                            $results[] = $p;
                        }
                    }
                }
                echo json_encode(['success' => true, 'players' => array_slice($results, 0, 10)]);
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
$hasWinningNumbers = !empty($winning_numbers) && $data_source !== 'none';
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
        .number-badge { 
            transition: all 0.15s ease;
            animation: popIn 0.2s ease;
        }
        @keyframes popIn {
            from { transform: scale(0); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .toast { 
            animation: slideIn 0.3s ease;
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 100;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .player-item.selected {
            background: #F3F4F6;
        }
        .input-focus:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div id="toast" class="toast hidden px-4 py-3 rounded-lg shadow-lg text-sm font-medium"></div>

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
        <!-- Date Navigation -->
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

        <!-- Winning Numbers Display -->
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
            
            <?php if (!$hasWinningNumbers): ?>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <p class="text-amber-700 text-sm">Geen uitslag gevonden voor deze datum. Winstberekening is uitgeschakeld.</p>
                </div>
            <?php else: ?>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($winning_numbers as $num): ?>
                        <span class="w-10 h-10 flex items-center justify-center rounded-full text-sm font-semibold bg-gradient-to-br from-green-400 to-green-600 text-white shadow-sm">
                            <?= htmlspecialchars($num) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Stats -->
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

        <!-- Keyboard-First Bon Entry -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-semibold text-gray-900">Bon Invoer</h2>
                <span class="text-xs text-gray-400">Volledig toetsenbord-gestuurd</span>
            </div>

            <!-- Player Search -->
            <div id="playerSearchSection" class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Zoek Speler</label>
                <div class="relative">
                    <input type="text" id="playerSearch" 
                           placeholder="Typ naam of spelersnummer..." 
                           autocomplete="off"
                           class="w-full px-4 py-3 border border-gray-200 rounded-lg input-focus text-lg">
                    <div id="playerResults" class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg hidden z-50 max-h-64 overflow-y-auto">
                    </div>
                </div>
                <p id="playerSearchHint" class="text-xs text-gray-400 mt-2">Enter = selecteer, Pijltjes = navigeer</p>
            </div>

            <!-- Active Bon Entry (hidden until player selected) -->
            <div id="bonEntrySection" class="hidden">
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span id="selectedPlayerDot" class="w-4 h-4 rounded-full"></span>
                            <span id="selectedPlayerName" class="font-semibold text-gray-900"></span>
                            <span id="selectedPlayerAlias" class="text-gray-500 text-sm"></span>
                        </div>
                        <button onclick="cancelBon()" class="text-sm text-gray-500 hover:text-gray-700">Andere speler</button>
                    </div>
                </div>

                <!-- Current Row Entry -->
                <div id="currentRowEntry" class="border-2 border-dashed border-gray-200 rounded-lg p-4 mb-4">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nummers</label>
                            <div class="flex items-center gap-2">
                                <input type="text" id="numberInput" 
                                       placeholder="Typ nummer en Enter..." 
                                       autocomplete="off"
                                       class="flex-1 px-4 py-3 border border-gray-200 rounded-lg input-focus text-lg font-mono">
                                <span id="numberCount" class="text-sm text-gray-400 whitespace-nowrap">0 nummers</span>
                            </div>
                        </div>
                    </div>
                    
                    <div id="currentNumbers" class="flex flex-wrap gap-2 min-h-[48px] mb-4">
                        <span class="text-gray-400 text-sm py-2">Typ nummers 1-80, Enter om toe te voegen, 0 = klaar</span>
                    </div>

                    <div id="betSection" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Inzet</label>
                        <div class="flex items-center gap-4">
                            <div class="relative flex-1">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500">&euro;</span>
                                <input type="number" id="betInput" 
                                       step="0.50" min="0.50" value="1.00"
                                       class="w-full pl-8 pr-4 py-3 border border-gray-200 rounded-lg input-focus text-lg">
                            </div>
                            <span id="gameTypeLabel" class="text-sm text-gray-500"></span>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">Enter = opslaan en volgende rij</p>
                    </div>
                </div>

                <!-- Saved Rows for Current Bon -->
                <div id="bonRows" class="space-y-2 mb-4">
                </div>

                <p class="text-xs text-gray-400">
                    <kbd class="px-1.5 py-0.5 bg-gray-100 rounded">Backspace</kbd> verwijder laatste nummer &bull;
                    <kbd class="px-1.5 py-0.5 bg-gray-100 rounded">0</kbd> als eerste = bon afsluiten
                </p>
            </div>
        </div>

        <!-- Existing Rows Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-6">Rijen van <?= date('d-m-Y', strtotime($selected_date)) ?></h2>
            
            <div id="rowsContainer">
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
                            <tbody id="rowsBody" class="divide-y divide-gray-50">
                                <?php foreach ($rows as $row): 
                                    $rowNumbers = explode(',', $row['numbers']);
                                ?>
                                <tr class="hover:bg-gray-50" data-row-id="<?= $row['id'] ?>">
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
                                                $isMatch = in_array(intval($num), array_map('intval', $winning_numbers));
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
        </div>
    </main>

    <!-- Add Player Modal -->
    <div id="addPlayerModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Nieuwe Speler Toevoegen</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Naam</label>
                    <input type="text" id="newPlayerName" class="w-full px-3 py-2 border border-gray-200 rounded-lg input-focus">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Spelersnummer (optioneel)</label>
                    <input type="text" id="newPlayerAlias" class="w-full px-3 py-2 border border-gray-200 rounded-lg input-focus">
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

    <!-- Edit Numbers Modal -->
    <div id="editNumbersModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Winnende Nummers Bewerken</h3>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">20 nummers (kommagescheiden)</label>
                <textarea id="editNumbersInput" rows="4" class="w-full px-3 py-2 border border-gray-200 rounded-lg input-focus"><?= implode(',', $winning_numbers) ?></textarea>
            </div>
            <div class="flex gap-3 mt-6">
                <button onclick="hideEditNumbersModal()" class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">Annuleren</button>
                <button onclick="saveNumbers()" class="flex-1 px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800">Opslaan</button>
            </div>
        </div>
    </div>

    <script>
        const selectedDate = '<?= $selected_date ?>';
        const winningNumbers = <?= json_encode(array_map('intval', $winning_numbers)) ?>;
        const hasWinningNumbers = <?= $hasWinningNumbers ? 'true' : 'false' ?>;
        const allPlayers = <?= json_encode($players ?: []) ?>;
        
        let currentPlayer = null;
        let currentNumbers = [];
        let bonRows = [];
        let playerSearchIndex = -1;
        let filteredPlayers = [];

        // Toast notification
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            toast.className = 'toast px-4 py-3 rounded-lg shadow-lg text-sm font-medium ' + 
                (type === 'error' ? 'bg-red-500 text-white' : 
                 type === 'success' ? 'bg-green-500 text-white' : 
                 type === 'warning' ? 'bg-amber-500 text-white' : 
                 'bg-gray-800 text-white');
            toast.textContent = message;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 3000);
        }

        // Player Search
        const playerSearchInput = document.getElementById('playerSearch');
        const playerResults = document.getElementById('playerResults');

        playerSearchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            playerSearchIndex = -1;
            
            if (query === '') {
                filteredPlayers = allPlayers.slice(0, 10);
            } else {
                filteredPlayers = allPlayers.filter(p => {
                    const searchStr = (p.name + ' ' + (p.alias || '') + ' ' + p.id).toLowerCase();
                    return searchStr.includes(query);
                }).slice(0, 10);
            }
            
            renderPlayerResults();
        });

        playerSearchInput.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                playerSearchIndex = Math.min(playerSearchIndex + 1, filteredPlayers.length - 1);
                updatePlayerSelection();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                playerSearchIndex = Math.max(playerSearchIndex - 1, -1);
                updatePlayerSelection();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (playerSearchIndex >= 0 && filteredPlayers[playerSearchIndex]) {
                    selectPlayer(filteredPlayers[playerSearchIndex]);
                } else if (filteredPlayers.length > 0) {
                    selectPlayer(filteredPlayers[0]);
                } else if (this.value.trim()) {
                    showAddPlayerModalWithName(this.value.trim());
                }
            }
        });

        playerSearchInput.addEventListener('focus', function() {
            if (filteredPlayers.length === 0) {
                filteredPlayers = allPlayers.slice(0, 10);
            }
            renderPlayerResults();
        });

        function renderPlayerResults() {
            if (filteredPlayers.length === 0 && playerSearchInput.value.trim()) {
                playerResults.innerHTML = `
                    <div class="p-4 text-center">
                        <p class="text-gray-500 mb-2">Speler niet gevonden</p>
                        <button onclick="showAddPlayerModalWithName('${playerSearchInput.value.trim()}')" 
                                class="text-blue-600 hover:text-blue-700 font-medium">+ Toevoegen?</button>
                    </div>
                `;
                playerResults.classList.remove('hidden');
            } else if (filteredPlayers.length > 0) {
                playerResults.innerHTML = filteredPlayers.map((p, i) => `
                    <div class="player-item px-4 py-3 cursor-pointer hover:bg-gray-50 flex items-center gap-3 ${i === playerSearchIndex ? 'selected' : ''}"
                         onclick="selectPlayer(${JSON.stringify(p).replace(/"/g, '&quot;')})">
                        <span class="w-3 h-3 rounded-full" style="background: ${p.color || '#3B82F6'}"></span>
                        <span class="font-medium">${p.name}</span>
                        ${p.alias ? `<span class="text-gray-400 text-sm">(${p.alias})</span>` : ''}
                        <span class="text-gray-300 text-xs ml-auto">#${p.id}</span>
                    </div>
                `).join('');
                playerResults.classList.remove('hidden');
            } else {
                playerResults.classList.add('hidden');
            }
        }

        function updatePlayerSelection() {
            document.querySelectorAll('.player-item').forEach((el, i) => {
                el.classList.toggle('selected', i === playerSearchIndex);
            });
        }

        function selectPlayer(player) {
            currentPlayer = player;
            currentNumbers = [];
            bonRows = [];
            
            document.getElementById('playerSearchSection').classList.add('hidden');
            document.getElementById('bonEntrySection').classList.remove('hidden');
            
            document.getElementById('selectedPlayerDot').style.background = player.color || '#3B82F6';
            document.getElementById('selectedPlayerName').textContent = player.name;
            document.getElementById('selectedPlayerAlias').textContent = player.alias ? `(${player.alias})` : '';
            
            resetRowEntry();
            document.getElementById('numberInput').focus();
        }

        function cancelBon() {
            currentPlayer = null;
            currentNumbers = [];
            bonRows = [];
            
            document.getElementById('playerSearchSection').classList.remove('hidden');
            document.getElementById('bonEntrySection').classList.add('hidden');
            document.getElementById('bonRows').innerHTML = '';
            
            playerSearchInput.value = '';
            playerSearchInput.focus();
            playerResults.classList.add('hidden');
        }

        function resetRowEntry() {
            currentNumbers = [];
            document.getElementById('numberInput').value = '';
            document.getElementById('numberInput').disabled = false;
            document.getElementById('betSection').classList.add('hidden');
            document.getElementById('currentNumbers').innerHTML = '<span class="text-gray-400 text-sm py-2">Typ nummers 1-80, Enter om toe te voegen, 0 = klaar</span>';
            document.getElementById('numberCount').textContent = '0 nummers';
            document.getElementById('numberInput').focus();
        }

        // Number Input
        const numberInput = document.getElementById('numberInput');
        const betInput = document.getElementById('betInput');

        numberInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = this.value.trim();
                
                if (val === '0') {
                    if (currentNumbers.length === 0) {
                        // 0 as first input = close bon
                        finishBon();
                    } else {
                        // 0 after numbers = finish this row's numbers
                        finishNumberEntry();
                    }
                    this.value = '';
                } else if (val !== '') {
                    const num = parseInt(val);
                    if (isNaN(num) || num < 1 || num > 80) {
                        showToast('Nummer moet tussen 1 en 80 zijn', 'error');
                    } else if (currentNumbers.includes(num)) {
                        showToast('Nummer al gekozen', 'warning');
                    } else if (currentNumbers.length >= 10) {
                        showToast('Maximum 10 nummers per rij', 'warning');
                    } else {
                        currentNumbers.push(num);
                        updateCurrentNumbers();
                    }
                    this.value = '';
                }
            } else if (e.key === 'Backspace' && this.value === '' && currentNumbers.length > 0) {
                e.preventDefault();
                currentNumbers.pop();
                updateCurrentNumbers();
            }
        });

        betInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveCurrentRow();
            }
        });

        function updateCurrentNumbers() {
            const container = document.getElementById('currentNumbers');
            if (currentNumbers.length === 0) {
                container.innerHTML = '<span class="text-gray-400 text-sm py-2">Typ nummers 1-80, Enter om toe te voegen, 0 = klaar</span>';
            } else {
                container.innerHTML = currentNumbers.map(num => {
                    const isWinning = winningNumbers.includes(num);
                    return `<span class="number-badge w-10 h-10 flex items-center justify-center rounded-full text-sm font-semibold 
                                  ${isWinning ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-700'}">${num}</span>`;
                }).join('');
            }
            
            document.getElementById('numberCount').textContent = currentNumbers.length + ' nummers';
            document.getElementById('gameTypeLabel').textContent = currentNumbers.length > 0 ? currentNumbers.length + '-getallen' : '';
        }

        function finishNumberEntry() {
            if (currentNumbers.length === 0) {
                showToast('Voer minimaal 1 nummer in', 'error');
                return;
            }
            
            document.getElementById('numberInput').disabled = true;
            document.getElementById('betSection').classList.remove('hidden');
            document.getElementById('betInput').focus();
            document.getElementById('betInput').select();
        }

        function saveCurrentRow() {
            const bet = parseFloat(document.getElementById('betInput').value);
            
            if (bet <= 0 || isNaN(bet)) {
                showToast('Voer een geldige inzet in', 'error');
                return;
            }

            // Save to server
            fetch(`dashboard.php?date=${selectedDate}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add_row&player_id=${currentPlayer.id}&numbers=${currentNumbers.join(',')}&bet=${bet}&date=${selectedDate}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Add to bon rows display
                    addBonRowDisplay(data.row);
                    
                    // Add to main table
                    addRowToTable(data.row);
                    
                    showToast('Rij opgeslagen', 'success');
                    
                    // Reset for next row
                    resetRowEntry();
                } else {
                    showToast(data.error || 'Fout bij opslaan', 'error');
                }
            })
            .catch(() => showToast('Netwerkfout', 'error'));
        }

        function addBonRowDisplay(row) {
            const container = document.getElementById('bonRows');
            const numbers = row.numbers.split(',');
            const matches = parseInt(row.matches) || 0;
            const winnings = parseFloat(row.winnings) || 0;
            
            const rowHtml = `
                <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg">
                    <div class="flex flex-wrap gap-1 flex-1">
                        ${numbers.map(n => {
                            const isMatch = winningNumbers.includes(parseInt(n));
                            return `<span class="w-7 h-7 flex items-center justify-center rounded-full text-xs font-medium
                                          ${isMatch ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'}">${n}</span>`;
                        }).join('')}
                    </div>
                    <span class="text-sm text-gray-500">${numbers.length}-getallen</span>
                    <span class="text-sm text-gray-900">&euro;${parseFloat(row.bet).toFixed(2).replace('.', ',')}</span>
                    <span class="px-2 py-0.5 rounded-full text-xs ${matches > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}">${matches}</span>
                    <span class="text-sm font-medium ${winnings > 0 ? 'text-green-600' : 'text-gray-400'}">&euro;${winnings.toFixed(2).replace('.', ',')}</span>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', rowHtml);
        }

        function addRowToTable(row) {
            const tbody = document.getElementById('rowsBody');
            if (!tbody) {
                // Refresh page if table doesn't exist yet
                location.reload();
                return;
            }
            
            const numbers = row.numbers.split(',');
            const matches = parseInt(row.matches) || 0;
            const winnings = parseFloat(row.winnings) || 0;
            
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50';
            tr.dataset.rowId = row.id;
            tr.innerHTML = `
                <td class="py-3">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full" style="background: ${row.player_color || '#3B82F6'}"></span>
                        <span class="font-medium text-gray-900">${row.player_name}</span>
                        ${row.player_alias ? `<span class="text-gray-400 text-sm">(${row.player_alias})</span>` : ''}
                    </div>
                </td>
                <td class="py-3">
                    <div class="flex flex-wrap gap-1">
                        ${numbers.map(n => {
                            const isMatch = winningNumbers.includes(parseInt(n));
                            return `<span class="w-7 h-7 flex items-center justify-center rounded-full text-xs font-medium
                                          ${isMatch ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-600'}">${n}</span>`;
                        }).join('')}
                    </div>
                </td>
                <td class="py-3 text-sm text-gray-600">${row.game_type || ''}</td>
                <td class="py-3 text-sm text-gray-900 text-right">&euro;${parseFloat(row.bet).toFixed(2).replace('.', ',')}</td>
                <td class="py-3 text-center">
                    <span class="px-2 py-1 rounded-full text-xs font-medium ${matches > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}">${matches}</span>
                </td>
                <td class="py-3 text-right font-medium ${winnings > 0 ? 'text-green-600' : 'text-gray-400'}">
                    &euro;${winnings.toFixed(2).replace('.', ',')}
                </td>
                <td class="py-3 text-right">
                    <button onclick="deleteRow(${row.id})" class="text-gray-400 hover:text-red-500 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </td>
            `;
            tbody.insertBefore(tr, tbody.firstChild);
            
            // Remove "no rows" message if present
            const emptyMessage = document.querySelector('#rowsContainer > p');
            if (emptyMessage) {
                emptyMessage.remove();
            }
        }

        function finishBon() {
            showToast('Bon afgesloten', 'info');
            cancelBon();
        }

        function deleteRow(id) {
            if (!confirm('Rij verwijderen?')) return;
            
            fetch(`dashboard.php?date=${selectedDate}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_row&row_id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const row = document.querySelector(`tr[data-row-id="${id}"]`);
                    if (row) row.remove();
                    showToast('Rij verwijderd', 'success');
                } else {
                    showToast(data.error || 'Fout bij verwijderen', 'error');
                }
            });
        }

        // Player Modal
        function showAddPlayerModalWithName(name) {
            document.getElementById('newPlayerName').value = name;
            document.getElementById('addPlayerModal').classList.remove('hidden');
            document.getElementById('newPlayerName').focus();
        }

        function showAddPlayerModal() {
            document.getElementById('addPlayerModal').classList.remove('hidden');
            document.getElementById('newPlayerName').focus();
        }

        function hideAddPlayerModal() {
            document.getElementById('addPlayerModal').classList.add('hidden');
            document.getElementById('newPlayerName').value = '';
            document.getElementById('newPlayerAlias').value = '';
        }

        function addPlayer() {
            const name = document.getElementById('newPlayerName').value.trim();
            const alias = document.getElementById('newPlayerAlias').value.trim();
            const color = document.getElementById('newPlayerColor').value;

            if (!name) {
                showToast('Naam is verplicht', 'error');
                return;
            }

            fetch(`dashboard.php?date=${selectedDate}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add_player&name=${encodeURIComponent(name)}&alias=${encodeURIComponent(alias)}&color=${encodeURIComponent(color)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const newPlayer = { id: data.id, name: data.name, alias: data.alias, color: data.color };
                    allPlayers.unshift(newPlayer);
                    hideAddPlayerModal();
                    selectPlayer(newPlayer);
                    showToast('Speler toegevoegd', 'success');
                } else {
                    showToast(data.error || 'Fout bij toevoegen', 'error');
                }
            });
        }

        // Edit Numbers Modal
        function showEditNumbersModal() {
            document.getElementById('editNumbersModal').classList.remove('hidden');
        }

        function hideEditNumbersModal() {
            document.getElementById('editNumbersModal').classList.add('hidden');
        }

        function saveNumbers() {
            const input = document.getElementById('editNumbersInput').value;
            
            fetch(`dashboard.php?date=${selectedDate}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=save_numbers&numbers=${encodeURIComponent(input)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Nummers opgeslagen', 'success');
                    setTimeout(() => location.reload(), 500);
                } else {
                    showToast(data.error || 'Fout bij opslaan', 'error');
                }
            });
        }

        // Close results when clicking outside
        document.addEventListener('click', function(e) {
            if (!playerSearchInput.contains(e.target) && !playerResults.contains(e.target)) {
                playerResults.classList.add('hidden');
            }
        });

        // Initial focus
        document.addEventListener('DOMContentLoaded', function() {
            playerSearchInput.focus();
        });
    </script>
</body>
</html>
