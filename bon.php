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
        .kbd { display: inline-flex; align-items: center; padding: 2px 8px; font-size: 12px; font-weight: 500; color: #374151; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 6px; font-family: ui-monospace, SFMono-Regular, monospace; }
        .number-block { 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            width: 32px; 
            height: 32px; 
            border-radius: 8px; 
            font-size: 13px; 
            font-weight: 500;
        }
        .number-block.neutral { background: #f3f4f6; color: #374151; }
        .number-block.winner { background: #10b981; color: white; }
        @keyframes popIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .pop-in { animation: popIn 0.15s ease-out; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-4xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="dashboard.php?date=<?= $bon['date'] ?>" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                    <span class="text-2xl">🍀</span>
                    <h1 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($bon['name']) ?></h1>
                </div>
                <button onclick="deleteBon()" class="px-3 py-1.5 text-sm text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition">
                    Bon verwijderen
                </button>
            </div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-6">
        <div class="card p-6 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-medium" style="background: <?= htmlspecialchars($bon['player_color']) ?>">
                        <?= strtoupper(substr($bon['player_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($bon['player_name']) ?></div>
                        <div class="text-sm text-gray-500"><?= getDayAndAbbreviatedMonth($bon['date']) ?></div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Totaal saldo</div>
                    <div class="text-xl font-semibold <?= $saldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                        <?= $saldo >= 0 ? '+' : '' ?>€<?= number_format($saldo, 2, ',', '.') ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($hasWinningNumbers): ?>
        <div class="card p-4 mb-6">
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-500">Winnende nummers:</span>
                <div class="flex flex-wrap gap-1">
                    <?php foreach ($winningNumbers as $num): ?>
                        <span class="w-6 h-6 flex items-center justify-center text-xs font-medium bg-emerald-100 text-emerald-700 rounded"><?= $num ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card p-4 mb-6 bg-amber-50 border border-amber-200">
            <p class="text-sm text-amber-700">Nog geen winnende nummers beschikbaar voor deze datum.</p>
        </div>
        <?php endif; ?>

        <div class="card p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Nieuwe rij toevoegen</h2>
            
            <div class="mb-6 p-4 bg-gray-50 rounded-xl">
                <div class="flex flex-wrap gap-2 text-sm text-gray-600">
                    <span class="flex items-center gap-2">
                        <span class="kbd">1-80</span>
                        <span>Nummer invoeren</span>
                    </span>
                    <span class="text-gray-300">|</span>
                    <span class="flex items-center gap-2">
                        <span class="kbd">Enter</span>
                        <span>Nummer toevoegen</span>
                    </span>
                    <span class="text-gray-300">|</span>
                    <span class="flex items-center gap-2">
                        <span class="kbd">Backspace</span>
                        <span>Laatste verwijderen</span>
                    </span>
                    <span class="text-gray-300">|</span>
                    <span class="flex items-center gap-2">
                        <span class="kbd">0</span>
                        <span>Klaar met nummers</span>
                    </span>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nummers (1-80)</label>
                    <div class="flex gap-3">
                        <input type="text" id="numberInput" 
                               placeholder="Typ nummer..." 
                               autocomplete="off"
                               class="flex-1 px-4 py-3 bg-gray-50 border-0 rounded-xl focus:ring-2 focus:ring-emerald-500 text-lg font-mono">
                        <span id="numberCount" class="flex items-center text-sm text-gray-400">0/10</span>
                    </div>
                </div>
                
                <div id="selectedNumbers" class="flex flex-wrap gap-2 min-h-[40px] p-3 bg-gray-50 rounded-xl">
                    <span class="text-sm text-gray-400">Geselecteerde nummers verschijnen hier</span>
                </div>
                
                <div id="betSection" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Inzet</label>
                    <div class="flex gap-3">
                        <div class="relative flex-1">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">€</span>
                            <input type="number" id="betInput" 
                                   step="0.50" min="0.50" value="1.00"
                                   class="w-full pl-10 pr-4 py-3 bg-gray-50 border-0 rounded-xl focus:ring-2 focus:ring-emerald-500 text-lg">
                        </div>
                        <button onclick="saveRij()" class="px-6 py-3 bg-emerald-500 text-white font-medium rounded-xl hover:bg-emerald-600 transition">
                            Opslaan
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Druk <span class="kbd">Enter</span> om op te slaan</p>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-semibold text-gray-800">Rijen</h2>
                <div class="text-sm text-gray-500">
                    <?= $rijen ? count($rijen) : 0 ?> rij<?= (!$rijen || count($rijen) != 1) ? 'en' : '' ?>
                </div>
            </div>
            
            <div id="rijenContainer">
                <?php if (empty($rijen) || $rijen === false): ?>
                    <p class="text-center py-8 text-gray-400">Nog geen rijen in deze bon</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($rijen as $rij): 
                            $rijNumbers = explode(',', $rij['numbers']);
                        ?>
                            <div class="rij-item p-4 bg-gray-50 rounded-xl" data-rij-id="<?= $rij['id'] ?>">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex flex-wrap gap-1.5 mb-3">
                                            <?php foreach ($rijNumbers as $num): 
                                                $isWinner = in_array(intval($num), array_map('intval', $winningNumbers));
                                            ?>
                                                <span class="number-block <?= $isWinner ? 'winner' : 'neutral' ?>"><?= $num ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="flex items-center gap-4 text-sm">
                                            <span class="text-gray-500">Inzet: <strong class="text-gray-700">€<?= number_format($rij['bet'], 2, ',', '.') ?></strong></span>
                                            <span class="text-gray-500">Matches: <strong class="<?= $rij['matches'] > 0 ? 'text-emerald-600' : 'text-gray-700' ?>"><?= $rij['matches'] ?></strong></span>
                                            <?php if ($rij['multiplier'] > 0): ?>
                                                <span class="text-gray-500">×<strong class="text-gray-700"><?= $rij['multiplier'] ?></strong></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="text-right">
                                            <div class="text-xs text-gray-500">Winst</div>
                                            <div class="font-semibold <?= $rij['winnings'] > 0 ? 'text-emerald-600' : 'text-gray-400' ?>">
                                                €<?= number_format($rij['winnings'], 2, ',', '.') ?>
                                            </div>
                                        </div>
                                        <button onclick="deleteRij(<?= $rij['id'] ?>)" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($rijen && count($rijen) > 0): ?>
            <div class="mt-6 pt-6 border-t border-gray-100">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Totale inzet</span>
                    <span class="font-medium">€<?= number_format($totalBet, 2, ',', '.') ?></span>
                </div>
                <div class="flex justify-between text-sm mt-2">
                    <span class="text-gray-500">Totale winst</span>
                    <span class="font-medium text-emerald-600">€<?= number_format($totalWinnings, 2, ',', '.') ?></span>
                </div>
                <div class="flex justify-between mt-3 pt-3 border-t border-gray-100">
                    <span class="font-medium text-gray-700">Saldo</span>
                    <span class="font-semibold text-lg <?= $saldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                        <?= $saldo >= 0 ? '+' : '' ?>€<?= number_format($saldo, 2, ',', '.') ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const bonId = <?= $bonId ?>;
        const winningNumbers = <?= json_encode(array_map('intval', $winningNumbers)) ?>;
        let selectedNumbers = [];
        let inBetMode = false;
        
        const numberInput = document.getElementById('numberInput');
        const betInput = document.getElementById('betInput');
        const selectedNumbersEl = document.getElementById('selectedNumbers');
        const numberCountEl = document.getElementById('numberCount');
        const betSection = document.getElementById('betSection');
        
        numberInput.focus();
        
        numberInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = this.value.trim();
                
                if (val === '0') {
                    if (selectedNumbers.length > 0) {
                        enterBetMode();
                    }
                    this.value = '';
                    return;
                }
                
                const num = parseInt(val);
                if (num >= 1 && num <= 80 && selectedNumbers.length < 10) {
                    if (!selectedNumbers.includes(num)) {
                        selectedNumbers.push(num);
                        renderNumbers();
                    }
                }
                this.value = '';
            } else if (e.key === 'Backspace' && this.value === '') {
                if (selectedNumbers.length > 0) {
                    selectedNumbers.pop();
                    renderNumbers();
                }
            }
        });
        
        betInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveRij();
            }
        });
        
        function renderNumbers() {
            numberCountEl.textContent = selectedNumbers.length + '/10';
            
            if (selectedNumbers.length === 0) {
                selectedNumbersEl.innerHTML = '<span class="text-sm text-gray-400">Geselecteerde nummers verschijnen hier</span>';
                return;
            }
            
            let html = '';
            selectedNumbers.forEach(num => {
                const isWinner = winningNumbers.includes(num);
                html += `<span class="number-block pop-in ${isWinner ? 'winner' : 'neutral'}">${num}</span>`;
            });
            selectedNumbersEl.innerHTML = html;
        }
        
        function enterBetMode() {
            inBetMode = true;
            betSection.classList.remove('hidden');
            numberInput.disabled = true;
            betInput.focus();
            betInput.select();
        }
        
        function exitBetMode() {
            inBetMode = false;
            betSection.classList.add('hidden');
            numberInput.disabled = false;
            numberInput.focus();
        }
        
        async function saveRij() {
            if (selectedNumbers.length === 0) return;
            
            const bet = parseFloat(betInput.value) || 1.00;
            
            try {
                const formData = new FormData();
                formData.append('bon_id', bonId);
                formData.append('numbers', selectedNumbers.join(','));
                formData.append('bet', bet);
                
                const response = await fetch('api/add_rij.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Fout bij opslaan');
                }
            } catch (e) {
                alert('Fout bij opslaan');
            }
        }
        
        async function deleteRij(rijId) {
            if (!confirm('Weet je zeker dat je deze rij wilt verwijderen?')) return;
            
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
        
        async function deleteBon() {
            if (!confirm('Weet je zeker dat je deze hele bon wilt verwijderen? Alle rijen worden ook verwijderd.')) return;
            
            try {
                const formData = new FormData();
                formData.append('bon_id', bonId);
                
                const response = await fetch('api/delete_bon.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = 'dashboard.php?date=' + data.date;
                } else {
                    alert(data.error || 'Fout bij verwijderen');
                }
            } catch (e) {
                alert('Fout bij verwijderen');
            }
        }
    </script>
</body>
</html>
