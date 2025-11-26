<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

$bonId = intval($_GET['id'] ?? 0);
if ($bonId <= 0) {
    header('Location: dashboard.php');
    exit();
}

$bon = getBonById($conn, $bonId);
if (!$bon) {
    header('Location: dashboard.php?error=bon_not_found');
    exit();
}

$rijen = getRijenByBonId($conn, $bonId);
$winningNumbers = getWinningNumbersFromDatabase($bon['date'], $conn) ?? [];
$hasWinningNumbers = !empty($winningNumbers);

$totalBet = 0;
$totalWinnings = 0;
if ($rijen) {
    foreach ($rijen as $rij) {
        $totalBet += floatval($rij['bet']);
        $totalWinnings += floatval($rij['winnings']);
    }
}
$saldo = $totalWinnings - $totalBet;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($bon['name']) ?> - Lucky Day</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 12px rgba(0,0,0,0.04); }
        .number-chip { 
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 32px; height: 32px; padding: 0 8px;
            border-radius: 8px; font-size: 14px; font-weight: 600;
        }
        .number-chip.match { background: #10b981; color: white; }
        .number-chip.no-match { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
        .number-chip.pending { background: #fef3c7; color: #92400e; }
        .modal-overlay { backdrop-filter: blur(4px); }
        .popup-input { font-size: 24px; text-align: center; letter-spacing: 2px; }
        .fade-in { animation: fadeIn 0.2s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-2xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <a href="dashboard.php?date=<?= $bon['date'] ?>" class="flex items-center gap-2 text-gray-600 hover:text-gray-900 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    <span class="text-sm font-medium">Terug</span>
                </a>
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🍀</span>
                    <h1 class="text-lg font-semibold text-gray-800">Lucky Day</h1>
                </div>
                <div class="w-16"></div>
            </div>
        </div>
    </nav>

    <main class="max-w-2xl mx-auto px-4 py-6">
        <div class="card p-6 mb-6">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center text-white text-lg font-semibold" style="background: <?= htmlspecialchars($bon['player_color']) ?>">
                    <?= strtoupper(substr($bon['player_name'], 0, 1)) ?>
                </div>
                <div class="flex-1">
                    <h2 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($bon['name']) ?></h2>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($bon['player_name']) ?> · <?= getDayAndAbbreviatedMonth($bon['date']) ?></p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Saldo</div>
                    <div class="font-bold text-lg <?= $saldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                        <?= $saldo >= 0 ? '+' : '' ?>€<?= number_format($saldo, 2, ',', '.') ?>
                    </div>
                </div>
            </div>
            
            <?php if ($hasWinningNumbers): ?>
            <div class="pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Winnende nummers</p>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach ($winningNumbers as $num): ?>
                        <span class="w-7 h-7 flex items-center justify-center text-xs font-medium bg-emerald-100 text-emerald-700 rounded-md"><?= $num ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="card p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-800">Rijen</h3>
                <button onclick="startNewRij()" class="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-500 text-white text-sm font-medium rounded-lg hover:bg-emerald-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nieuwe rij
                </button>
            </div>

            <?php if (empty($rijen) || $rijen === false): ?>
                <div class="text-center py-8 text-gray-400">
                    <p class="text-sm">Nog geen rijen</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($rijen as $index => $rij): 
                        $numbers = explode(',', $rij['numbers']);
                        $matches = intval($rij['matches']);
                        $isWinner = floatval($rij['winnings']) > 0;
                    ?>
                        <div class="p-4 bg-gray-50 rounded-xl <?= $isWinner ? 'ring-2 ring-emerald-200' : '' ?>">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="text-xs font-medium text-gray-400">Rij <?= $index + 1 ?></span>
                                        <span class="text-xs px-2 py-0.5 bg-gray-200 text-gray-600 rounded-full"><?= htmlspecialchars($rij['game_type']) ?></span>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <?php foreach ($numbers as $num): 
                                            $isMatch = in_array(intval($num), array_map('intval', $winningNumbers));
                                            $chipClass = $hasWinningNumbers ? ($isMatch ? 'match' : 'no-match') : 'pending';
                                        ?>
                                            <span class="number-chip <?= $chipClass ?>"><?= $num ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm text-gray-500">€<?= number_format($rij['bet'], 2, ',', '.') ?></div>
                                    <?php if ($hasWinningNumbers): ?>
                                        <div class="font-medium <?= $isWinner ? 'text-emerald-600' : 'text-gray-400' ?>">
                                            <?= $isWinner ? '+€' . number_format($rij['winnings'], 2, ',', '.') : $matches . ' goed' ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button onclick="deleteRij(<?= $rij['id'] ?>)" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-500">Totale inzet</span>
                        <span class="font-medium">€<?= number_format($totalBet, 2, ',', '.') ?></span>
                    </div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-500">Totale winst</span>
                        <span class="font-medium text-emerald-600">€<?= number_format($totalWinnings, 2, ',', '.') ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium text-gray-700">Saldo</span>
                        <span class="font-bold text-lg <?= $saldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                            <?= $saldo >= 0 ? '+' : '' ?>€<?= number_format($saldo, 2, ',', '.') ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="popup-overlay" class="fixed inset-0 bg-black/50 modal-overlay hidden items-center justify-center z-50">
        <div id="popup-content" class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6 fade-in">
            <div id="number-popup" class="hidden">
                <div class="text-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Nieuwe rij</h3>
                    <p class="text-sm text-gray-500">Voer nummers in</p>
                </div>
                <div id="current-numbers" class="flex flex-wrap gap-2 justify-center min-h-[44px] mb-4"></div>
                <input type="tel" id="number-input" class="w-full px-4 py-4 text-2xl text-center bg-gray-50 border-0 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none popup-input" placeholder="Nummer..." autocomplete="off" inputmode="numeric" pattern="[0-9]*">
                <p class="text-xs text-gray-400 text-center mt-4">Enter = toevoegen · 0 = naar inzet</p>
            </div>

            <div id="bet-popup" class="hidden">
                <div class="text-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Inzet</h3>
                    <div id="bet-numbers-display" class="flex flex-wrap gap-1.5 justify-center mt-3"></div>
                </div>
                <input type="tel" id="bet-input" class="w-full px-4 py-4 text-2xl text-center bg-gray-50 border-0 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none popup-input" placeholder="1.00" autocomplete="off" inputmode="numeric" pattern="[0-9]*">
                <p class="text-xs text-gray-400 text-center mt-4">Enter = opslaan</p>
            </div>
        </div>
    </div>

    <script>
        const bonId = <?= $bonId ?>;
        const winningNumbers = <?= json_encode(array_map('intval', $winningNumbers)) ?>;
        let currentNumbers = [];

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

        function startNewRij() {
            currentNumbers = [];
            showPopup('number-popup');
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

        document.getElementById('number-input').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = this.value.trim();
                
                if (val === '0' || val === '') {
                    if (currentNumbers.length > 0) {
                        goToBetEntry();
                    }
                    return;
                }
                
                const num = parseInt(val);
                if (isNaN(num) || num < 1 || num > 80) {
                    this.value = '';
                    return;
                }
                
                if (currentNumbers.includes(num)) {
                    this.value = '';
                    return;
                }
                
                if (currentNumbers.length >= 10) {
                    this.value = '';
                    return;
                }
                
                currentNumbers.push(num);
                renderCurrentNumbers();
                this.value = '';
            }
        });

        function goToBetEntry() {
            showPopup('bet-popup');
            
            const display = document.getElementById('bet-numbers-display');
            display.innerHTML = currentNumbers.map(num => {
                const isMatch = winningNumbers.includes(num);
                const chipClass = winningNumbers.length > 0 ? (isMatch ? 'match' : 'no-match') : 'pending';
                return `<span class="number-chip ${chipClass}">${num}</span>`;
            }).join('');
            
            const input = document.getElementById('bet-input');
            input.value = '1.00';
            input.focus();
            input.select();
        }

        document.getElementById('bet-input').addEventListener('keydown', async function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                let bet = parseFloat(this.value.replace(',', '.'));
                if (isNaN(bet) || bet < 0.50) bet = 1.00;
                
                const response = await fetch('api/add_rij.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `bon_id=${bonId}&numbers=${currentNumbers.join(',')}&bet=${bet}`
                });
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Kon rij niet opslaan');
                }
            }
        });

        async function deleteRij(rijId) {
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

        document.getElementById('popup-overlay').addEventListener('click', function(e) {
            if (e.target === this) {
                hidePopup();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hidePopup();
            }
        });
    </script>
</body>
</html>
