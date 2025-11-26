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
        .kbd { display: inline-flex; align-items: center; padding: 2px 6px; font-size: 11px; font-weight: 500; color: #374151; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 4px; font-family: ui-monospace, monospace; }
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
        <div class="flex items-center justify-center gap-1 mb-8 overflow-x-auto pb-2">
            <?php foreach ($date_range as $date): ?>
                <a href="?date=<?= $date ?>" 
                   class="date-btn px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap <?= $date === $selected_date ? 'active' : 'text-gray-600' ?>">
                    <?= getDayAndAbbreviatedMonth($date) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="lg:col-span-2 card p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-gray-800">Bonnen van <?= getDayAndAbbreviatedMonth($selected_date) ?></h2>
                    <button onclick="openNewBonModal()" class="flex items-center gap-2 px-4 py-2 bg-emerald-500 text-white text-sm font-medium rounded-lg hover:bg-emerald-600 transition">
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
                            $saldo = floatval($bon['total_winnings']) - floatval($bon['total_bet']);
                        ?>
                            <a href="bon.php?id=<?= $bon['id'] ?>" class="block p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition group">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-medium" style="background: <?= htmlspecialchars($bon['player_color']) ?>">
                                            <?= strtoupper(substr($bon['player_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-800"><?= htmlspecialchars($bon['name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($bon['player_name']) ?> · <?= $bon['rijen_count'] ?> rij<?= $bon['rijen_count'] != 1 ? 'en' : '' ?></div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm text-gray-500">Inzet: €<?= number_format($bon['total_bet'], 2, ',', '.') ?></div>
                                        <div class="font-medium <?= $saldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                                            <?= $saldo >= 0 ? '+' : '' ?>€<?= number_format($saldo, 2, ',', '.') ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="space-y-6">
                <div class="card p-6">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-4">Dagtotaal</h3>
                    <?php 
                    $daySaldo = floatval($dayStats['total_winnings']) - floatval($dayStats['total_bet']);
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
                            <span class="text-gray-600">Totale inzet</span>
                            <span class="font-medium">€<?= number_format($dayStats['total_bet'], 2, ',', '.') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Totale winst</span>
                            <span class="font-medium">€<?= number_format($dayStats['total_winnings'], 2, ',', '.') ?></span>
                        </div>
                        <hr class="border-gray-100">
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-800">Saldo</span>
                            <span class="font-semibold <?= $daySaldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                                <?= $daySaldo >= 0 ? '+' : '' ?>€<?= number_format($daySaldo, 2, ',', '.') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Winnende nummers</h3>
                        <button onclick="fetchWinningNumbers()" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">
                            Ophalen
                        </button>
                    </div>
                    <div id="winning-numbers-container">
                        <?php if ($hasWinningNumbers): ?>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach ($winningData as $num): ?>
                                    <span class="w-7 h-7 flex items-center justify-center text-xs font-medium bg-emerald-100 text-emerald-700 rounded-md"><?= $num ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-gray-400">Nog niet beschikbaar</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <button onclick="togglePlayers()" class="w-full flex items-center justify-between text-left">
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Spelers van vandaag</h3>
                <svg id="players-chevron" class="w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            
            <div id="players-list" class="hidden mt-4">
                <?php 
                $playerStats = getPlayerDayStats($conn, $selected_date);
                if (empty($playerStats) || $playerStats === false): 
                ?>
                    <p class="text-sm text-gray-400">Geen spelers actief vandaag</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($playerStats as $player): 
                            $playerSaldo = floatval($player['saldo']);
                        ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-medium" style="background: <?= htmlspecialchars($player['color']) ?>">
                                        <?= strtoupper(substr($player['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-800"><?= htmlspecialchars($player['name']) ?></div>
                                        <div class="text-xs text-gray-500"><?= $player['total_bons'] ?> bon<?= $player['total_bons'] != 1 ? 'nen' : '' ?>, <?= $player['total_rijen'] ?> rij<?= $player['total_rijen'] != 1 ? 'en' : '' ?></div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-medium <?= $playerSaldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                                        <?= $playerSaldo >= 0 ? '+' : '' ?>€<?= number_format($playerSaldo, 2, ',', '.') ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?= $playerSaldo > 0 ? 'Krijgt geld' : ($playerSaldo < 0 ? 'Moet betalen' : 'Quitte') ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="new-bon-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Nieuwe bon</h3>
                <button onclick="closeNewBonModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            
            <form action="api/create_bon.php" method="POST">
                <input type="hidden" name="date" value="<?= $selected_date ?>">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Spelernaam</label>
                    <input type="text" name="player_name" required placeholder="Typ de naam van de speler" class="w-full px-4 py-3 bg-gray-50 border-0 rounded-xl focus:ring-2 focus:ring-emerald-500" autocomplete="off">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Naam (optioneel)</label>
                    <input type="text" name="name" placeholder="Bijv. Avond bon" class="w-full px-4 py-3 bg-gray-50 border-0 rounded-xl focus:ring-2 focus:ring-emerald-500">
                </div>
                
                <div class="flex gap-3">
                    <button type="button" onclick="closeNewBonModal()" class="flex-1 px-4 py-3 text-gray-700 font-medium bg-gray-100 rounded-xl hover:bg-gray-200 transition">Annuleren</button>
                    <button type="submit" class="flex-1 px-4 py-3 text-white font-medium bg-emerald-500 rounded-xl hover:bg-emerald-600 transition">Aanmaken</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePlayers() {
            const list = document.getElementById('players-list');
            const chevron = document.getElementById('players-chevron');
            list.classList.toggle('hidden');
            chevron.classList.toggle('rotate-180');
        }

        function openNewBonModal() {
            document.getElementById('new-bon-modal').classList.remove('hidden');
            document.getElementById('new-bon-modal').classList.add('flex');
        }

        function closeNewBonModal() {
            document.getElementById('new-bon-modal').classList.add('hidden');
            document.getElementById('new-bon-modal').classList.remove('flex');
        }

        async function fetchWinningNumbers() {
            const container = document.getElementById('winning-numbers-container');
            container.innerHTML = '<p class="text-sm text-gray-400">Ophalen...</p>';
            
            try {
                const response = await fetch('api/scrape_numbers.php?date=<?= $selected_date ?>');
                const data = await response.json();
                
                if (data.success && data.numbers) {
                    let html = '<div class="flex flex-wrap gap-1.5">';
                    data.numbers.forEach(num => {
                        html += `<span class="w-7 h-7 flex items-center justify-center text-xs font-medium bg-emerald-100 text-emerald-700 rounded-md">${num}</span>`;
                    });
                    html += '</div>';
                    container.innerHTML = html;
                    location.reload();
                } else {
                    container.innerHTML = `<p class="text-sm text-red-500">${data.error || 'Kon nummers niet ophalen'}</p>`;
                }
            } catch (e) {
                container.innerHTML = '<p class="text-sm text-red-500">Fout bij ophalen</p>';
            }
        }

        document.getElementById('new-bon-modal').addEventListener('click', function(e) {
            if (e.target === this) closeNewBonModal();
        });
    </script>
</body>
</html>
