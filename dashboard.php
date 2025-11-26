<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

if (!isset($_GET['date'])) {
    header('Location: dashboard.php?date=' . date('Y-m-d'));
    exit();
}

$selected_date = $_GET['date'];
$date_range = generateDateRange($selected_date);

$bonnen = getBonnenByDate($conn, $selected_date);
$dayStats = getDayStats($conn, $selected_date);
$allPlayers = getAllPlayers($conn);

$winningData = getWinningNumbersFromDatabase($selected_date, $conn);
$hasWinningNumbers = !empty($winningData);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lucky Day - Dagoverzicht</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 12px rgba(0,0,0,0.04); }
        .card-hover:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1), 0 8px 24px rgba(0,0,0,0.08); transform: translateY(-1px); }
        .date-btn { transition: all 0.15s ease; }
        .date-btn:hover { background: #f3f4f6; }
        .date-btn.active { background: #10b981; color: white; }
        .modal-overlay { backdrop-filter: blur(4px); }
        .popup-input { font-size: 24px; text-align: center; letter-spacing: 2px; }
        .number-chip { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; font-weight: 600; font-size: 14px; }
        .number-chip.match { background: #d1fae5; color: #065f46; }
        .number-chip.no-match { background: #f3f4f6; color: #6b7280; }
        .number-chip.pending { background: #fef3c7; color: #92400e; }
        .player-suggestion { transition: background 0.1s; }
        .player-suggestion:hover, .player-suggestion.selected { background: #f0fdf4; }
        .scraper-status { font-size: 12px; padding: 4px 8px; border-radius: 6px; }
        .scraper-status.loading { background: #fef3c7; color: #92400e; }
        .scraper-status.error { background: #fee2e2; color: #991b1b; }
        .scraper-status.success { background: #d1fae5; color: #065f46; }
        .fade-in { animation: fadeIn 0.2s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-6xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🍀</span>
                    <h1 class="text-lg font-semibold text-gray-800">Lucky Day</h1>
                </div>
                <div class="flex items-center gap-2">
                    <a href="weekoverzicht.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Weekoverzicht</a>
                    <a href="beheer.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Beheer</a>
                    <a href="logout.php" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">Uitloggen</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
        <div class="flex items-center justify-center gap-2 mb-8">
            <div class="flex items-center gap-1 overflow-x-auto pb-2">
                <?php foreach ($date_range as $date): ?>
                    <a href="?date=<?= $date ?>" 
                       class="date-btn px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap <?= $date === $selected_date ? 'active' : 'text-gray-600' ?>">
                        <?= getDayAndAbbreviatedMonth($date) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <input type="date" id="date-picker" value="<?= $selected_date ?>" 
                   class="px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 cursor-pointer"
                   onchange="window.location.href='?date=' + this.value">
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="lg:col-span-2 card p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-gray-800">Bonnen van <?= getDayAndAbbreviatedMonth($selected_date) ?></h2>
                    <button onclick="startNewBon()" class="flex items-center gap-2 px-4 py-2 bg-emerald-500 text-white text-sm font-medium rounded-lg hover:bg-emerald-600 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Nieuwe bon
                    </button>
                </div>

                <?php if (empty($bonnen) || $bonnen === false): ?>
                    <div class="text-center py-12 text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p class="text-sm">Nog geen bonnen voor deze dag</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($bonnen as $bon): 
                            $bonWinnings = floatval($bon['total_winnings']);
                            $bonBet = floatval($bon['total_bet']);
                            $teBetalen = $bonWinnings - $bonBet;
                        ?>
                            <div onclick="openBonPopup(<?= $bon['id'] ?>)" class="cursor-pointer p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition group">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-full flex items-center justify-center text-white text-lg font-semibold" style="background: <?= htmlspecialchars($bon['player_color']) ?>">
                                            <?= strtoupper(substr($bon['player_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($bon['player_name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= $bon['rijen_count'] ?> rij<?= $bon['rijen_count'] != 1 ? 'en' : '' ?> · Inzet €<?= number_format($bonBet, 2, ',', '.') ?></div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <?php if ($teBetalen > 0): ?>
                                            <div class="text-2xl font-bold text-emerald-600">+€<?= number_format($teBetalen, 2, ',', '.') ?></div>
                                            <div class="text-xs text-emerald-600">Te betalen</div>
                                        <?php elseif ($teBetalen < 0): ?>
                                            <div class="text-2xl font-bold text-red-500">–€<?= number_format(abs($teBetalen), 2, ',', '.') ?></div>
                                            <div class="text-xs text-red-500">Ontvangen</div>
                                        <?php else: ?>
                                            <div class="text-2xl font-bold text-gray-500">€0,00</div>
                                            <div class="text-xs text-gray-500">Quitte</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="space-y-6">
                <div class="card p-6">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-4">Dagtotaal</h3>
                    <?php 
                    $inzetOntvangen = floatval($dayStats['total_bet']);
                    $uitbetaaldAanSpelers = floatval($dayStats['total_winnings']);
                    $eindSaldo = $inzetOntvangen - $uitbetaaldAanSpelers;
                    ?>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Bonnen</span>
                            <span class="font-medium"><?= $dayStats['total_bons'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Rijen</span>
                            <span class="font-medium"><?= $dayStats['total_rijen'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Inzet ontvangen</span>
                            <span class="font-medium text-emerald-600">+€<?= number_format($inzetOntvangen, 2, ',', '.') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Uitbetaald aan spelers</span>
                            <span class="font-medium text-red-500">-€<?= number_format($uitbetaaldAanSpelers, 2, ',', '.') ?></span>
                        </div>
                        <hr class="border-gray-100">
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-800">Eindsaldo</span>
                            <span class="font-bold text-lg <?= $eindSaldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                                <?= $eindSaldo >= 0 ? '+' : '' ?>€<?= number_format($eindSaldo, 2, ',', '.') ?>
                            </span>
                        </div>
                        <div class="text-center text-xs <?= $eindSaldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                            <?= $eindSaldo >= 0 ? 'Winst voor teller' : 'Verlies voor teller' ?>
                        </div>
                    </div>
                </div>

                <div class="card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Winnende nummers</h3>
                        <div class="flex items-center gap-2">
                            <span id="scraper-status" class="scraper-status hidden"></span>
                            <button onclick="fetchWinningNumbers()" id="fetch-btn" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">
                                Ophalen
                            </button>
                        </div>
                    </div>
                    <div id="winning-numbers-container">
                        <?php if ($hasWinningNumbers): ?>
                            <div class="space-y-1.5">
                                <div class="grid grid-cols-10 gap-1">
                                    <?php for ($i = 0; $i < 10 && $i < count($winningData); $i++): ?>
                                        <span class="w-7 h-7 flex items-center justify-center text-xs font-medium bg-emerald-100 text-emerald-700 rounded-md"><?= $winningData[$i] ?></span>
                                    <?php endfor; ?>
                                </div>
                                <div class="grid grid-cols-10 gap-1">
                                    <?php for ($i = 10; $i < 20 && $i < count($winningData); $i++): ?>
                                        <span class="w-7 h-7 flex items-center justify-center text-xs font-medium bg-emerald-100 text-emerald-700 rounded-md"><?= $winningData[$i] ?></span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-gray-400">Nog niet beschikbaar</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-4">Klantoverzicht</h3>
            
            <div id="players-list" class="">
                <?php 
                $playerStats = getPlayerDayStats($conn, $selected_date);
                if (empty($playerStats) || $playerStats === false): 
                ?>
                    <p class="text-sm text-gray-400">Geen spelers actief vandaag</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($playerStats as $player): 
                            $playerWinnings = floatval($player['total_winnings'] ?? 0);
                            $playerBet = floatval($player['total_bet'] ?? 0);
                            $teBetalen = $playerWinnings - $playerBet;
                        ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-semibold" style="background: <?= htmlspecialchars($player['color']) ?>">
                                        <?= strtoupper(substr($player['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($player['name']) ?></div>
                                        <div class="text-xs text-gray-500"><?= $player['total_bons'] ?> bon<?= $player['total_bons'] != 1 ? 'nen' : '' ?> · Inzet €<?= number_format($playerBet, 2, ',', '.') ?></div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <?php if ($teBetalen > 0): ?>
                                        <div class="text-xl font-bold text-emerald-600">+€<?= number_format($teBetalen, 2, ',', '.') ?></div>
                                        <div class="text-xs text-emerald-600">Te betalen</div>
                                    <?php elseif ($teBetalen < 0): ?>
                                        <div class="text-xl font-bold text-red-500">–€<?= number_format(abs($teBetalen), 2, ',', '.') ?></div>
                                        <div class="text-xs text-red-500">Ontvangen</div>
                                    <?php else: ?>
                                        <div class="text-xl font-bold text-gray-500">€0,00</div>
                                        <div class="text-xs text-gray-500">Quitte</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="popup-overlay" class="fixed inset-0 bg-black/50 modal-overlay hidden items-center justify-center z-50">
        <div id="popup-content" class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6 fade-in max-h-[90vh] overflow-y-auto">
            <div id="name-popup" class="hidden">
                <div class="text-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Spelernaam</h3>
                    <p class="text-sm text-gray-500 mt-1">Typ naam en druk Enter</p>
                </div>
                <input type="text" id="name-input" class="w-full px-4 py-4 text-xl text-center bg-gray-50 border-0 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none" placeholder="Naam..." autocomplete="off" enterkeyhint="go">
                <div id="player-suggestions" class="mt-3 max-h-48 overflow-y-auto hidden"></div>
                <p class="text-xs text-gray-400 text-center mt-4">Enter = selecteren/aanmaken · Leeg + Enter = sluiten</p>
            </div>

            <div id="number-popup" class="hidden">
                <div class="text-center mb-3">
                    <h3 class="text-lg font-semibold text-gray-800" id="popup-player-name"></h3>
                </div>
                
                <div id="popup-winning-numbers" class="mb-4 p-3 bg-emerald-50 rounded-xl hidden">
                    <p class="text-xs text-emerald-600 font-medium mb-2 text-center">Winnende nummers</p>
                    <div id="popup-winning-display" class="flex flex-wrap gap-1 justify-center"></div>
                </div>
                
                <div id="saved-rows-container" class="mb-4 space-y-2 hidden"></div>
                
                <div class="bg-gray-50 rounded-xl p-4 mb-4">
                    <p class="text-sm text-gray-500 text-center mb-2">Rij <span id="current-row-num">1</span></p>
                    <div id="current-numbers" class="flex flex-wrap gap-2 justify-center min-h-[44px] mb-3"></div>
                    <div class="flex gap-2">
                        <input type="text" id="number-input" class="flex-1 px-4 py-4 text-2xl text-center bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none popup-input" placeholder="Nummer..." autocomplete="off" inputmode="numeric" enterkeyhint="done">
                        <button type="button" id="number-ok-btn" class="px-6 py-4 bg-emerald-500 text-white text-xl font-semibold rounded-xl hover:bg-emerald-600 active:bg-emerald-700 transition-colors">OK</button>
                    </div>
                </div>
                <p class="text-xs text-gray-400 text-center">OK/Enter = toevoegen · 0 = naar inzet</p>
            </div>

            <div id="bet-popup" class="hidden">
                <div class="text-center mb-3">
                    <h3 class="text-lg font-semibold text-gray-800" id="bet-player-name"></h3>
                </div>
                
                <div id="bet-winning-numbers" class="mb-4 p-3 bg-emerald-50 rounded-xl hidden">
                    <p class="text-xs text-emerald-600 font-medium mb-2 text-center">Winnende nummers</p>
                    <div id="bet-winning-display" class="flex flex-wrap gap-1 justify-center"></div>
                </div>
                
                <div id="bet-saved-rows" class="mb-4 space-y-2 hidden"></div>
                
                <div class="bg-gray-50 rounded-xl p-4 mb-4">
                    <p class="text-sm text-gray-500 text-center mb-2">Inzet voor rij <span id="bet-row-num">1</span></p>
                    <div id="bet-numbers-display" class="flex flex-wrap gap-1.5 justify-center mb-3"></div>
                    <div class="flex gap-2">
                        <input type="text" id="bet-input" class="flex-1 px-4 py-4 text-2xl text-center bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none popup-input" placeholder="1.00" autocomplete="off" inputmode="decimal" enterkeyhint="done">
                        <button type="button" id="bet-ok-btn" class="px-6 py-4 bg-emerald-500 text-white text-xl font-semibold rounded-xl hover:bg-emerald-600 active:bg-emerald-700 transition-colors">OK</button>
                    </div>
                </div>
                <p class="text-xs text-gray-400 text-center">OK/Enter = opslaan en volgende rij</p>
            </div>

            <div id="bon-detail-popup" class="hidden">
                <div id="bon-detail-content"></div>
            </div>
        </div>
    </div>

    <script>
        const selectedDate = '<?= $selected_date ?>';
        const allPlayers = <?= json_encode($allPlayers ?: []) ?>;
        const winningNumbers = <?= json_encode($winningData ? array_map('intval', $winningData) : []) ?>;
        
        const multipliers = {
            1: {1: 2},
            2: {2: 5},
            3: {3: 16, 2: 2},
            4: {4: 20, 3: 5, 2: 1},
            5: {5: 200, 4: 8, 3: 2},
            6: {6: 1000, 5: 20, 4: 5, 3: 1},
            7: {7: 2000, 6: 100, 5: 10, 4: 2, 3: 1},
            8: {8: 20000, 7: 200, 6: 20, 5: 8, 4: 2},
            9: {9: 100000, 8: 2000, 7: 100, 6: 8, 5: 2},
            10: {10: 300000, 9: 4000, 8: 200, 7: 20, 6: 5, 5: 2}
        };
        
        let currentBonId = null;
        let currentPlayerId = null;
        let currentPlayerName = '';
        let currentNumbers = [];
        let currentRowNum = 1;
        let savedRows = [];
        let scraperRetryInterval = null;
        
        function calculateWinnings(numbers, bet) {
            if (winningNumbers.length === 0) return { matches: 0, multiplier: 0, winnings: 0 };
            const matches = numbers.filter(n => winningNumbers.includes(n)).length;
            const gameType = numbers.length;
            const mult = multipliers[gameType]?.[matches] || 0;
            return { matches, multiplier: mult, winnings: bet * mult };
        }
        
        function renderWinningNumbersInPopup(containerId) {
            const container = document.getElementById(containerId);
            const display = document.getElementById(containerId.replace('-numbers', '-display'));
            if (winningNumbers.length > 0) {
                container.classList.remove('hidden');
                display.innerHTML = winningNumbers.map(n => 
                    `<span class="w-6 h-6 flex items-center justify-center text-xs font-medium bg-emerald-100 text-emerald-700 rounded">${n}</span>`
                ).join('');
            } else {
                container.classList.add('hidden');
            }
        }
        
        function renderSavedRows(containerId) {
            const container = document.getElementById(containerId);
            if (savedRows.length === 0) {
                container.classList.add('hidden');
                return;
            }
            container.classList.remove('hidden');
            
            const hasPending = winningNumbers.length === 0;
            let totalBet = 0;
            let totalWinnings = 0;
            
            const rowsHtml = savedRows.map((row, i) => {
                totalBet += row.bet;
                const result = calculateWinnings(row.numbers, row.bet);
                totalWinnings += result.winnings;
                const saldo = result.winnings - row.bet;
                
                return `
                    <div class="bg-white border border-gray-100 rounded-lg p-3">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-gray-500">Rij ${i + 1}</span>
                            ${hasPending 
                                ? '<span class="text-xs font-medium text-amber-600">In afwachting</span>'
                                : `<span class="text-xs font-medium ${saldo >= 0 ? 'text-emerald-600' : 'text-red-500'}">${saldo >= 0 ? '+' : ''}€${saldo.toFixed(2)}</span>`
                            }
                        </div>
                        <div class="flex flex-wrap gap-1">
                            ${row.numbers.map(n => {
                                const isMatch = winningNumbers.includes(n);
                                const cls = hasPending ? 'bg-amber-100 text-amber-700' : (isMatch ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500');
                                return `<span class="w-6 h-6 flex items-center justify-center text-xs font-medium ${cls} rounded">${n}</span>`;
                            }).join('')}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Inzet: €${row.bet.toFixed(2)}${!hasPending ? ` · ${result.matches} goed${result.multiplier > 0 ? ` · ${result.multiplier}x` : ''}` : ''}
                        </div>
                    </div>
                `;
            }).join('');
            
            const totalSaldo = totalWinnings - totalBet;
            const totalsHtml = `
                <div class="bg-gray-100 rounded-lg p-3 mt-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Totaal ${savedRows.length} rij${savedRows.length > 1 ? 'en' : ''}</span>
                        ${hasPending 
                            ? '<span class="font-medium text-amber-600">In afwachting</span>'
                            : `<span class="font-medium ${totalSaldo >= 0 ? 'text-emerald-600' : 'text-red-500'}">${totalSaldo >= 0 ? '+' : ''}€${totalSaldo.toFixed(2)}</span>`
                        }
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        Inzet: €${totalBet.toFixed(2)}${!hasPending ? ` · Winst: €${totalWinnings.toFixed(2)}` : ''}
                    </div>
                </div>
            `;
            
            container.innerHTML = rowsHtml + totalsHtml;
        }

        function togglePlayers() {
            const list = document.getElementById('players-list');
            const chevron = document.getElementById('players-chevron');
            list.classList.toggle('hidden');
            chevron.classList.toggle('rotate-180');
        }

        function showPopup(popupId) {
            document.getElementById('popup-overlay').classList.remove('hidden');
            document.getElementById('popup-overlay').classList.add('flex');
            document.querySelectorAll('#popup-content > div').forEach(el => el.classList.add('hidden'));
            document.getElementById(popupId).classList.remove('hidden');
        }

        function hidePopup() {
            document.getElementById('popup-overlay').classList.add('hidden');
            document.getElementById('popup-overlay').classList.remove('flex');
        }

        let popupBonId = null;
        let popupBon = null;
        let popupTotals = null;
        let popupRijen = [];
        let popupOriginalRijen = [];
        let popupWinNums = [];
        let popupHasPending = false;
        let popupSaving = false;

        async function openBonPopup(bonId) {
            try {
                const response = await fetch(`api/get_bon.php?id=${bonId}`);
                const data = await response.json();
                
                if (!data.success) {
                    alert(data.error || 'Kon bon niet laden');
                    return;
                }
                
                popupBonId = bonId;
                popupBon = data.bon;
                popupTotals = data.totals;
                popupRijen = data.rijen.map(r => ({...r, numbers: [...r.numbers]}));
                popupOriginalRijen = data.rijen.map(r => ({...r, numbers: [...r.numbers]}));
                popupWinNums = data.winning_numbers;
                popupHasPending = popupWinNums.length === 0;
                
                renderBonPopupContent();
                showPopup('bon-detail-popup');
                
            } catch (error) {
                alert('Fout bij laden bon');
                console.error(error);
            }
        }

        function renderBonPopupContent() {
            const bon = popupBon;
            const totals = popupTotals;
            let rijenHtml = popupRijen.map((rij, i) => {
                const teBetalen = rij.winnings - rij.bet;
                return `
                    <div class="p-3 bg-gray-50 rounded-lg" data-rij-index="${i}">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-gray-500">Rij ${i + 1}</span>
                            <div class="flex items-center gap-2">
                                ${popupHasPending 
                                    ? '<span class="text-xs font-medium text-amber-600">In afwachting</span>'
                                    : (teBetalen > 0 
                                        ? `<span class="text-xs font-medium text-emerald-600">+€${teBetalen.toFixed(2)}</span>`
                                        : (teBetalen < 0 
                                            ? `<span class="text-xs font-medium text-red-500">–€${Math.abs(teBetalen).toFixed(2)}</span>`
                                            : '<span class="text-xs font-medium text-gray-500">€0,00</span>'))
                                }
                                <button onclick="deleteRijFromPopup(${rij.id})" class="text-gray-400 hover:text-red-500 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1 mb-2">
                            ${rij.numbers.map((n, j) => {
                                const isMatch = popupWinNums.includes(n);
                                const cls = popupHasPending ? 'bg-amber-100 text-amber-700 hover:bg-amber-200' : (isMatch ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-gray-200 text-gray-500 hover:bg-gray-300');
                                return `<span class="w-8 h-8 flex items-center justify-center text-sm font-medium ${cls} rounded cursor-pointer transition" onclick="editNumber(${i}, ${j})" data-rij="${i}" data-num="${j}">${n}</span>`;
                            }).join('')}
                        </div>
                        <div class="text-xs text-gray-500">
                            Inzet: €${rij.bet.toFixed(2)}${!popupHasPending ? ` · ${rij.matches} goed${rij.multiplier > 0 ? ` · ${rij.multiplier}x · €${rij.winnings.toFixed(2)}` : ''}` : ''}
                        </div>
                    </div>
                `;
            }).join('');
            
            const saldo = totals.saldo;
            const hasChanges = checkForChanges();
            
            const content = `
                <div class="text-center mb-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-3" style="background: ${bon.player_color}">
                        ${bon.player_name.charAt(0).toUpperCase()}
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">${escapeHtml(bon.player_name)}</h3>
                    <p class="text-sm text-gray-500">${popupRijen.length} rij${popupRijen.length !== 1 ? 'en' : ''}</p>
                </div>
                
                ${popupWinNums.length > 0 ? `
                    <div class="mb-4 p-3 bg-emerald-50 rounded-xl">
                        <p class="text-xs text-emerald-600 font-medium mb-2 text-center">Winnende nummers</p>
                        <div class="flex flex-wrap gap-1 justify-center">
                            ${popupWinNums.map(n => `<span class="w-8 h-8 flex items-center justify-center text-sm font-medium bg-emerald-100 text-emerald-700 rounded">${n}</span>`).join('')}
                        </div>
                    </div>
                ` : ''}
                
                <div class="space-y-2 mb-4 max-h-64 overflow-y-auto">
                    ${rijenHtml || '<p class="text-center text-gray-400 py-4">Geen rijen</p>'}
                </div>
                
                <div class="bg-gray-100 rounded-xl p-4 mb-4">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">Inzet ontvangen</span>
                        <span class="font-medium">€${totals.bet.toFixed(2)}</span>
                    </div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-600">Uitbetaald</span>
                        <span class="font-medium">€${totals.winnings.toFixed(2)}</span>
                    </div>
                    <hr class="border-gray-200 my-2">
                    <div class="flex justify-between">
                        <span class="font-semibold text-gray-800">Resultaat</span>
                        ${popupHasPending 
                            ? '<span class="font-bold text-lg text-amber-600">In afwachting</span>'
                            : (saldo > 0 
                                ? `<span class="font-bold text-lg text-emerald-600">+€${saldo.toFixed(2)}</span>`
                                : (saldo < 0 
                                    ? `<span class="font-bold text-lg text-red-500">–€${Math.abs(saldo).toFixed(2)}</span>`
                                    : '<span class="font-bold text-lg text-gray-500">€0,00</span>'))
                        }
                    </div>
                    ${!popupHasPending ? `
                        <div class="text-center text-xs mt-2 ${saldo > 0 ? 'text-emerald-600' : (saldo < 0 ? 'text-red-500' : 'text-gray-500')}">
                            ${saldo > 0 ? 'Te betalen aan speler' : (saldo < 0 ? 'Ontvangen van speler' : 'Quitte')}
                        </div>
                    ` : ''}
                </div>
                
                <div class="flex gap-2">
                    ${hasChanges ? `
                        <button onclick="savePopupChanges()" id="save-popup-btn" class="flex-1 py-3 text-sm font-medium text-white bg-emerald-500 rounded-xl hover:bg-emerald-600 transition ${popupSaving ? 'opacity-50 cursor-not-allowed' : ''}" ${popupSaving ? 'disabled' : ''}>
                            ${popupSaving ? 'Opslaan...' : 'Opslaan'}
                        </button>
                    ` : ''}
                    <button onclick="deleteBonFromPopup(${bon.id})" class="flex-1 py-3 text-sm font-medium text-red-600 bg-red-50 rounded-xl hover:bg-red-100 transition">
                        Verwijderen
                    </button>
                </div>
            `;
            
            document.getElementById('bon-detail-content').innerHTML = content;
        }

        function checkForChanges() {
            for (let i = 0; i < popupRijen.length; i++) {
                const current = popupRijen[i].numbers;
                const original = popupOriginalRijen[i].numbers;
                if (current.length !== original.length) return true;
                for (let j = 0; j < current.length; j++) {
                    if (current[j] !== original[j]) return true;
                }
            }
            return false;
        }

        function editNumber(rijIndex, numIndex) {
            const currentNum = popupRijen[rijIndex].numbers[numIndex];
            const newNum = prompt(`Wijzig nummer (1-80):`, currentNum);
            
            if (newNum === null) return;
            
            const parsed = parseInt(newNum);
            if (isNaN(parsed) || parsed < 1 || parsed > 80) {
                alert('Voer een nummer in tussen 1 en 80');
                return;
            }
            
            popupRijen[rijIndex].numbers[numIndex] = parsed;
            
            renderBonPopupContent();
        }

        async function savePopupChanges() {
            if (popupSaving) return;
            
            try {
                const changes = [];
                for (let i = 0; i < popupRijen.length; i++) {
                    const current = popupRijen[i].numbers;
                    const original = popupOriginalRijen[i].numbers;
                    let changed = current.length !== original.length;
                    if (!changed) {
                        for (let j = 0; j < current.length; j++) {
                            if (current[j] !== original[j]) {
                                changed = true;
                                break;
                            }
                        }
                    }
                    if (changed) {
                        changes.push({
                            rij_id: popupRijen[i].id,
                            numbers: current
                        });
                    }
                }
                
                if (changes.length === 0) return;
                
                popupSaving = true;
                renderBonPopupContent();
                
                const response = await fetch('api/update_rij_numbers.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ changes })
                });
                
                const data = await response.json();
                if (data.success) {
                    const refreshResponse = await fetch(`api/get_bon.php?id=${popupBonId}`);
                    const refreshData = await refreshResponse.json();
                    
                    if (refreshData.success) {
                        popupBon = refreshData.bon;
                        popupTotals = refreshData.totals;
                        popupRijen = refreshData.rijen.map(r => ({...r, numbers: [...r.numbers]}));
                        popupOriginalRijen = refreshData.rijen.map(r => ({...r, numbers: [...r.numbers]}));
                        popupWinNums = refreshData.winning_numbers;
                        popupHasPending = popupWinNums.length === 0;
                    } else {
                        location.reload();
                        return;
                    }
                } else {
                    alert(data.error || 'Fout bij opslaan');
                }
                
                popupSaving = false;
                renderBonPopupContent();
                
            } catch (e) {
                popupSaving = false;
                alert('Fout bij opslaan');
                console.error(e);
            }
        }

        async function deleteRijFromPopup(rijId) {
            if (!confirm('Rij verwijderen?')) return;
            
            try {
                const formData = new FormData();
                formData.append('rij_id', rijId);
                
                const response = await fetch('api/delete_rij.php', {
                    method: 'POST',
                    body: formData
                });
                
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

        async function deleteBonFromPopup(bonId) {
            if (!confirm('Bon verwijderen? Alle rijen worden ook verwijderd.')) return;
            
            try {
                const formData = new FormData();
                formData.append('bon_id', bonId);
                
                const response = await fetch('api/delete_bon.php', {
                    method: 'POST',
                    body: formData
                });
                
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

        function startNewBon() {
            currentBonId = null;
            currentPlayerId = null;
            currentPlayerName = '';
            currentNumbers = [];
            currentRowNum = 1;
            savedRows = [];
            
            showPopup('name-popup');
            const nameInput = document.getElementById('name-input');
            nameInput.value = '';
            nameInput.focus();
            updatePlayerSuggestions('');
        }

        function updatePlayerSuggestions(query) {
            const container = document.getElementById('player-suggestions');
            const q = query.toLowerCase().trim();
            
            if (!q) {
                container.classList.add('hidden');
                return;
            }
            
            const matches = allPlayers.filter(p => p.name.toLowerCase().includes(q));
            
            if (matches.length === 0) {
                container.innerHTML = `<div class="p-3 text-sm text-gray-500 text-center">Nieuwe speler: <strong>${escapeHtml(query)}</strong></div>`;
            } else {
                container.innerHTML = matches.map((p, i) => `
                    <div class="player-suggestion p-3 rounded-lg cursor-pointer flex items-center gap-3 ${i === 0 ? 'selected' : ''}" data-id="${p.id}" data-name="${escapeHtml(p.name)}">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-medium" style="background: ${p.color}">
                            ${p.name.charAt(0).toUpperCase()}
                        </div>
                        <span class="font-medium text-gray-800">${escapeHtml(p.name)}</span>
                    </div>
                `).join('');
            }
            
            container.classList.remove('hidden');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.getElementById('name-input').addEventListener('input', function() {
            updatePlayerSuggestions(this.value);
        });

        document.getElementById('name-input').addEventListener('keydown', async function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const name = this.value.trim();
                
                if (!name) {
                    hidePopup();
                    return;
                }
                
                const existingPlayer = allPlayers.find(p => p.name.toLowerCase() === name.toLowerCase());
                
                if (existingPlayer) {
                    currentPlayerId = existingPlayer.id;
                    currentPlayerName = existingPlayer.name;
                } else {
                    const response = await fetch('api/create_player.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name: name })
                    });
                    const data = await response.json();
                    if (data.success) {
                        currentPlayerId = data.id;
                        currentPlayerName = name;
                        allPlayers.push({ id: data.id, name: name, color: data.color });
                    } else {
                        alert(data.error || 'Kon speler niet aanmaken');
                        return;
                    }
                }
                
                const bonResponse = await fetch('api/create_bon.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ player_id: currentPlayerId, date: selectedDate })
                });
                const bonData = await bonResponse.json();
                
                if (bonData.success) {
                    currentBonId = bonData.id;
                    startNumberEntry();
                } else {
                    alert(bonData.error || 'Kon bon niet aanmaken');
                }
            }
        });

        function startNumberEntry() {
            currentNumbers = [];
            showPopup('number-popup');
            document.getElementById('popup-player-name').textContent = currentPlayerName;
            document.getElementById('current-row-num').textContent = currentRowNum;
            renderWinningNumbersInPopup('popup-winning-numbers');
            renderSavedRows('saved-rows-container');
            renderCurrentNumbers();
            const input = document.getElementById('number-input');
            input.value = '';
            input.focus();
        }

        function renderCurrentNumbers() {
            const container = document.getElementById('current-numbers');
            if (currentNumbers.length === 0) {
                container.innerHTML = '<span class="text-sm text-gray-400">Voer nummers in...</span>';
                return;
            }
            
            container.innerHTML = currentNumbers.map(num => {
                const isMatch = winningNumbers.includes(num);
                const chipClass = winningNumbers.length > 0 ? (isMatch ? 'match' : 'no-match') : 'pending';
                return `<span class="number-chip ${chipClass}">${num}</span>`;
            }).join('');
        }

        function handleNumberInput() {
            const input = document.getElementById('number-input');
            const val = input.value.trim();
            
            if (val === '0' || val === '') {
                if (currentNumbers.length > 0) {
                    goToBetEntry();
                }
                return;
            }
            
            const num = parseInt(val);
            if (isNaN(num) || num < 1 || num > 80) {
                input.value = '';
                return;
            }
            
            if (currentNumbers.includes(num)) {
                input.value = '';
                return;
            }
            
            if (currentNumbers.length >= 10) {
                input.value = '';
                return;
            }
            
            currentNumbers.push(num);
            renderCurrentNumbers();
            input.value = '';
            input.focus();
        }

        document.getElementById('number-input').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleNumberInput();
            }
        });
        
        document.getElementById('number-ok-btn').addEventListener('click', handleNumberInput);

        function goToBetEntry() {
            showPopup('bet-popup');
            document.getElementById('bet-player-name').textContent = currentPlayerName;
            document.getElementById('bet-row-num').textContent = currentRowNum;
            renderWinningNumbersInPopup('bet-winning-numbers');
            renderSavedRows('bet-saved-rows');
            
            const display = document.getElementById('bet-numbers-display');
            display.innerHTML = currentNumbers.map(num => {
                const isMatch = winningNumbers.includes(num);
                const chipClass = winningNumbers.length > 0 ? (isMatch ? 'match' : 'no-match') : 'pending';
                return `<span class="number-chip ${chipClass}" style="width:28px;height:28px;font-size:12px;">${num}</span>`;
            }).join('');
            
            const input = document.getElementById('bet-input');
            input.value = '1.00';
            input.focus();
            input.select();
        }

        async function handleBetInput() {
            const input = document.getElementById('bet-input');
            let bet = parseFloat(input.value.replace(',', '.'));
            if (isNaN(bet) || bet < 0.50) bet = 1.00;
            
            const response = await fetch('api/add_rij.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `bon_id=${currentBonId}&numbers=${currentNumbers.join(',')}&bet=${bet}`
            });
            const data = await response.json();
            
            if (data.success) {
                savedRows.push({ numbers: [...currentNumbers], bet: bet });
                currentRowNum++;
                currentNumbers = [];
                startNextRow();
            } else {
                alert(data.error || 'Kon rij niet opslaan');
            }
        }

        document.getElementById('bet-input').addEventListener('keydown', async function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleBetInput();
            }
        });
        
        document.getElementById('bet-ok-btn').addEventListener('click', handleBetInput);

        function startNextRow() {
            showPopup('number-popup');
            document.getElementById('current-row-num').textContent = currentRowNum;
            renderWinningNumbersInPopup('popup-winning-numbers');
            renderSavedRows('saved-rows-container');
            renderCurrentNumbers();
            const input = document.getElementById('number-input');
            input.value = '';
            input.focus();
            
            input.removeEventListener('keydown', checkFirstZero);
            input.addEventListener('keydown', checkFirstZero);
        }

        function checkFirstZero(e) {
            if (e.key === 'Enter') {
                const val = this.value.trim();
                if (val === '0' && currentNumbers.length === 0) {
                    e.preventDefault();
                    finishBon();
                    this.removeEventListener('keydown', checkFirstZero);
                }
            }
        }

        function finishBon() {
            hidePopup();
            location.reload();
        }

        document.getElementById('popup-overlay').addEventListener('click', function(e) {
            if (e.target === this) {
                if (currentBonId && currentNumbers.length === 0) {
                    finishBon();
                } else {
                    hidePopup();
                }
            }
        });

        let scraperRetrying = false;
        async function fetchWinningNumbers(isRetry = false) {
            const container = document.getElementById('winning-numbers-container');
            const statusEl = document.getElementById('scraper-status');
            const fetchBtn = document.getElementById('fetch-btn');
            
            if (!isRetry) {
                container.innerHTML = '<p class="text-sm text-gray-400">Ophalen...</p>';
            }
            statusEl.classList.remove('hidden', 'error', 'success');
            statusEl.classList.add('loading');
            statusEl.textContent = 'Bezig...';
            fetchBtn.disabled = true;
            
            try {
                const response = await fetch('api/scrape_numbers.php?date=' + selectedDate);
                const data = await response.json();
                
                if (data.success && data.numbers) {
                    statusEl.classList.remove('loading');
                    statusEl.classList.add('success');
                    statusEl.textContent = 'Gelukt!';
                    stopScraperRetry();
                    
                    let html = '<div class="space-y-1.5">';
                    html += '<div class="grid grid-cols-10 gap-1">';
                    data.numbers.slice(0, 10).forEach(num => {
                        html += `<span class="w-7 h-7 flex items-center justify-center text-xs font-medium bg-emerald-100 text-emerald-700 rounded-md">${num}</span>`;
                    });
                    html += '</div>';
                    html += '<div class="grid grid-cols-10 gap-1">';
                    data.numbers.slice(10, 20).forEach(num => {
                        html += `<span class="w-7 h-7 flex items-center justify-center text-xs font-medium bg-emerald-100 text-emerald-700 rounded-md">${num}</span>`;
                    });
                    html += '</div></div>';
                    container.innerHTML = html;
                    
                    setTimeout(() => location.reload(), 1000);
                } else {
                    statusEl.classList.remove('loading');
                    statusEl.classList.add('error');
                    
                    const errorMsg = data.error || 'Uitslag niet gevonden';
                    statusEl.textContent = errorMsg;
                    
                    if (!isRetry) {
                        container.innerHTML = `<p class="text-sm text-amber-600">${errorMsg}</p>`;
                    }
                    
                    if (data.retry !== false) {
                        startScraperRetry();
                    } else {
                        stopScraperRetry();
                    }
                }
            } catch (e) {
                statusEl.classList.remove('loading');
                statusEl.classList.add('error');
                statusEl.textContent = 'Verbindingsfout';
                
                if (!isRetry) {
                    container.innerHTML = '<p class="text-sm text-amber-600">Kon geen verbinding maken</p>';
                }
                
                startScraperRetry();
            }
            
            fetchBtn.disabled = false;
        }

        function startScraperRetry() {
            if (scraperRetrying) return;
            scraperRetrying = true;
            
            scraperRetryInterval = setInterval(() => {
                fetchWinningNumbers(true);
            }, 10000);
        }

        function stopScraperRetry() {
            scraperRetrying = false;
            if (scraperRetryInterval) {
                clearInterval(scraperRetryInterval);
                scraperRetryInterval = null;
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const overlay = document.getElementById('popup-overlay');
                if (!overlay.classList.contains('hidden')) {
                    if (currentBonId && currentNumbers.length === 0) {
                        finishBon();
                    } else {
                        hidePopup();
                    }
                }
            }
        });
    </script>
</body>
</html>
