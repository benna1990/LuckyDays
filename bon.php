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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($bon['name']) ?> - Lucky Day</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; padding-bottom: 320px; }
        .card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 12px rgba(0,0,0,0.04); }
        
        .number-chip { 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            min-width: 36px; 
            height: 36px; 
            padding: 0 10px;
            border-radius: 10px; 
            font-size: 15px; 
            font-weight: 600;
            transition: all 0.15s ease;
        }
        .number-chip.match { background: #10b981; color: white; }
        .number-chip.no-match { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
        .number-chip.pending { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }
        .shake { animation: shake 0.3s ease; }
        
        @keyframes popIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .pop-in { animation: popIn 0.15s ease-out; }
        
        .keyboard-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e5e7eb;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
            z-index: 50;
        }
        
        .key-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-size: 20px;
            font-weight: 600;
            color: #374151;
            transition: all 0.1s ease;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        .key-btn:active {
            background: #e5e7eb;
            transform: scale(0.95);
        }
        .key-btn.action {
            background: #10b981;
            color: white;
            border-color: #059669;
        }
        .key-btn.action:active {
            background: #059669;
        }
        .key-btn.danger {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }
        .key-btn.danger:active {
            background: #fee2e2;
        }
        
        .warning-msg {
            font-size: 12px;
            color: #dc2626;
            padding: 4px 8px;
            background: #fef2f2;
            border-radius: 6px;
            border: 1px solid #fecaca;
        }
        
        .input-display {
            font-size: 28px;
            font-weight: 700;
            min-width: 60px;
            text-align: center;
            padding: 8px 16px;
            background: #f9fafb;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
        }
        .input-display.error {
            border-color: #fca5a5;
            background: #fef2f2;
        }
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
                    Verwijderen
                </button>
            </div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-4">
        <div class="card p-4 mb-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-medium text-sm" style="background: <?= htmlspecialchars($bon['player_color']) ?>">
                        <?= strtoupper(substr($bon['player_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($bon['player_name']) ?></div>
                        <div class="text-xs text-gray-500"><?= getDayAndAbbreviatedMonth($bon['date']) ?></div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-gray-500">Saldo</div>
                    <div class="text-lg font-semibold <?= $saldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                        <?= $saldo >= 0 ? '+' : '' ?>€<?= number_format($saldo, 2, ',', '.') ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($hasWinningNumbers): ?>
        <div class="card p-3 mb-4">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs text-gray-500 font-medium">Winnend:</span>
                <?php foreach ($winningNumbers as $num): ?>
                    <span class="w-6 h-6 flex items-center justify-center text-xs font-semibold bg-emerald-100 text-emerald-700 rounded"><?= $num ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card p-3 mb-4 bg-amber-50 border border-amber-200">
            <p class="text-xs text-amber-700">Nog geen winnende nummers voor deze datum.</p>
        </div>
        <?php endif; ?>

        <div class="card p-4 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-800">Nieuwe rij</h2>
                <span id="gameType" class="text-xs text-gray-500 font-medium"></span>
            </div>
            
            <div id="selectedNumbers" class="flex flex-wrap gap-2 min-h-[44px] mb-3">
                <span class="text-sm text-gray-400 italic">Gebruik toetsenbord hieronder</span>
            </div>
            
            <div id="warningMessage" class="hidden mb-3"></div>
            
            <div id="betSection" class="hidden">
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-600">Inzet:</span>
                    <div class="flex items-center gap-2">
                        <button onclick="adjustBet(-0.5)" class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-lg text-gray-600 font-bold">-</button>
                        <span id="betDisplay" class="text-lg font-semibold text-gray-800 min-w-[60px] text-center">€1,00</span>
                        <button onclick="adjustBet(0.5)" class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-lg text-gray-600 font-bold">+</button>
                    </div>
                    <button onclick="saveRij()" class="ml-auto px-4 py-2 bg-emerald-500 text-white text-sm font-semibold rounded-lg hover:bg-emerald-600 transition">
                        Rij opslaan
                    </button>
                </div>
            </div>
        </div>

        <?php if ($rijen && count($rijen) > 0): ?>
        <div class="card p-4">
            <h2 class="text-sm font-semibold text-gray-800 mb-3">Rijen (<?= count($rijen) ?>)</h2>
            <div class="space-y-3">
                <?php foreach ($rijen as $rij): 
                    $rijNumbers = array_map('intval', explode(',', $rij['numbers']));
                    $rijSaldo = floatval($rij['winnings']) - floatval($rij['bet']);
                ?>
                <div class="p-3 bg-gray-50 rounded-xl">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1">
                            <div class="flex flex-wrap gap-1.5 mb-2">
                                <?php foreach ($rijNumbers as $num): 
                                    $isWinner = in_array($num, $winningNumbers);
                                ?>
                                    <span class="number-chip <?= $isWinner ? 'match' : 'no-match' ?>"><?= $num ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-gray-500">
                                <span><?= $rij['game_type'] ?></span>
                                <span>•</span>
                                <span>Inzet: €<?= number_format($rij['bet'], 2, ',', '.') ?></span>
                                <?php if ($rij['matches'] > 0): ?>
                                <span>•</span>
                                <span class="text-emerald-600 font-medium"><?= $rij['matches'] ?> goed (<?= $rij['multiplier'] ?>x)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="text-right">
                                <div class="font-semibold <?= $rijSaldo >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                                    <?= $rijSaldo >= 0 ? '+' : '' ?>€<?= number_format($rijSaldo, 2, ',', '.') ?>
                                </div>
                            </div>
                            <button onclick="deleteRij(<?= $rij['id'] ?>)" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
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
        </div>
        <?php endif; ?>
    </main>

    <div class="keyboard-container">
        <div class="max-w-md mx-auto p-3">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span id="inputDisplay" class="input-display">_</span>
                    <span id="numberCount" class="text-sm text-gray-500 font-medium">0/10</span>
                </div>
                <div id="keyboardWarning" class="hidden warning-msg"></div>
            </div>
            
            <div class="grid grid-cols-4 gap-2 mb-2">
                <button onclick="pressKey(1)" class="key-btn h-12">1</button>
                <button onclick="pressKey(2)" class="key-btn h-12">2</button>
                <button onclick="pressKey(3)" class="key-btn h-12">3</button>
                <button onclick="pressBackspace()" class="key-btn h-12 danger">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z"/></svg>
                </button>
            </div>
            <div class="grid grid-cols-4 gap-2 mb-2">
                <button onclick="pressKey(4)" class="key-btn h-12">4</button>
                <button onclick="pressKey(5)" class="key-btn h-12">5</button>
                <button onclick="pressKey(6)" class="key-btn h-12">6</button>
                <button onclick="addNumber()" class="key-btn h-12 action" id="addBtn">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </button>
            </div>
            <div class="grid grid-cols-4 gap-2 mb-2">
                <button onclick="pressKey(7)" class="key-btn h-12">7</button>
                <button onclick="pressKey(8)" class="key-btn h-12">8</button>
                <button onclick="pressKey(9)" class="key-btn h-12">9</button>
                <button onclick="finishNumbers()" class="key-btn h-12 action" id="finishBtn">OK</button>
            </div>
            <div class="grid grid-cols-4 gap-2">
                <button onclick="clearAll()" class="key-btn h-12 text-sm text-gray-500">C</button>
                <button onclick="pressKey(0)" class="key-btn h-12">0</button>
                <button onclick="removeLastNumber()" class="key-btn h-12 text-sm text-red-500">←</button>
                <div></div>
            </div>
        </div>
    </div>

    <script>
        const bonId = <?= $bonId ?>;
        const winningNumbers = <?= json_encode(array_map('intval', $winningNumbers)) ?>;
        let selectedNumbers = [];
        let currentInput = '';
        let currentBet = 1.00;
        let inBetMode = false;
        
        const inputDisplay = document.getElementById('inputDisplay');
        const selectedNumbersEl = document.getElementById('selectedNumbers');
        const numberCountEl = document.getElementById('numberCount');
        const gameTypeEl = document.getElementById('gameType');
        const betSection = document.getElementById('betSection');
        const betDisplay = document.getElementById('betDisplay');
        const warningMessage = document.getElementById('warningMessage');
        const keyboardWarning = document.getElementById('keyboardWarning');
        
        function pressKey(num) {
            if (inBetMode) return;
            
            const newInput = currentInput + num.toString();
            const numValue = parseInt(newInput);
            
            if (numValue > 80) {
                showKeyboardWarning('Max 80');
                shakeInput();
                return;
            }
            
            currentInput = newInput;
            updateInputDisplay();
            
            if (numValue >= 10 || (numValue >= 1 && currentInput.length >= 2)) {
                setTimeout(() => {
                    if (currentInput === newInput) {
                        addNumber();
                    }
                }, 400);
            }
        }
        
        function pressBackspace() {
            if (inBetMode) return;
            
            if (currentInput.length > 0) {
                currentInput = currentInput.slice(0, -1);
                updateInputDisplay();
            } else if (selectedNumbers.length > 0) {
                selectedNumbers.pop();
                renderNumbers();
            }
            hideKeyboardWarning();
        }
        
        function addNumber() {
            if (inBetMode) return;
            if (currentInput === '') return;
            
            const num = parseInt(currentInput);
            
            if (num < 1 || num > 80) {
                showKeyboardWarning('1-80');
                shakeInput();
                currentInput = '';
                updateInputDisplay();
                return;
            }
            
            if (selectedNumbers.includes(num)) {
                showKeyboardWarning('Al gekozen');
                shakeInput();
                currentInput = '';
                updateInputDisplay();
                return;
            }
            
            if (selectedNumbers.length >= 10) {
                showKeyboardWarning('Max 10');
                shakeInput();
                currentInput = '';
                updateInputDisplay();
                return;
            }
            
            selectedNumbers.push(num);
            currentInput = '';
            updateInputDisplay();
            renderNumbers();
            hideKeyboardWarning();
        }
        
        function removeLastNumber() {
            if (inBetMode) return;
            if (selectedNumbers.length > 0) {
                selectedNumbers.pop();
                renderNumbers();
            }
        }
        
        function clearAll() {
            if (inBetMode) {
                exitBetMode();
            }
            selectedNumbers = [];
            currentInput = '';
            updateInputDisplay();
            renderNumbers();
            hideKeyboardWarning();
        }
        
        function finishNumbers() {
            if (selectedNumbers.length === 0) {
                showKeyboardWarning('Voer nummers in');
                return;
            }
            
            if (selectedNumbers.length > 10) {
                showKeyboardWarning('Max 10 nummers');
                return;
            }
            
            enterBetMode();
        }
        
        function updateInputDisplay() {
            inputDisplay.textContent = currentInput || '_';
            inputDisplay.classList.remove('error');
        }
        
        function shakeInput() {
            inputDisplay.classList.add('error', 'shake');
            setTimeout(() => {
                inputDisplay.classList.remove('shake');
            }, 300);
        }
        
        function showKeyboardWarning(msg) {
            keyboardWarning.textContent = msg;
            keyboardWarning.classList.remove('hidden');
        }
        
        function hideKeyboardWarning() {
            keyboardWarning.classList.add('hidden');
        }
        
        function renderNumbers() {
            numberCountEl.textContent = selectedNumbers.length + '/10';
            
            const gameTypes = ['', '1-getallen', '2-getallen', '3-getallen', '4-getallen', '5-getallen', '6-getallen', '7-getallen', '8-getallen', '9-getallen', '10-getallen'];
            gameTypeEl.textContent = selectedNumbers.length > 0 ? gameTypes[selectedNumbers.length] : '';
            
            if (selectedNumbers.length === 0) {
                selectedNumbersEl.innerHTML = '<span class="text-sm text-gray-400 italic">Gebruik toetsenbord hieronder</span>';
                return;
            }
            
            let html = '';
            selectedNumbers.forEach(num => {
                const isWinner = winningNumbers.includes(num);
                const chipClass = winningNumbers.length > 0 
                    ? (isWinner ? 'match' : 'no-match')
                    : 'pending';
                html += `<span class="number-chip pop-in ${chipClass}">${num}</span>`;
            });
            selectedNumbersEl.innerHTML = html;
        }
        
        function enterBetMode() {
            inBetMode = true;
            betSection.classList.remove('hidden');
            document.getElementById('finishBtn').textContent = '✓';
        }
        
        function exitBetMode() {
            inBetMode = false;
            betSection.classList.add('hidden');
            document.getElementById('finishBtn').textContent = 'OK';
        }
        
        function adjustBet(amount) {
            currentBet = Math.max(0.50, currentBet + amount);
            betDisplay.textContent = '€' + currentBet.toFixed(2).replace('.', ',');
        }
        
        async function saveRij() {
            if (selectedNumbers.length === 0) return;
            
            try {
                const formData = new FormData();
                formData.append('bon_id', bonId);
                formData.append('numbers', selectedNumbers.join(','));
                formData.append('bet', currentBet);
                
                const response = await fetch('api/add_rij.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    showWarning(data.error || 'Fout bij opslaan');
                }
            } catch (e) {
                showWarning('Fout bij opslaan');
            }
        }
        
        function showWarning(msg) {
            warningMessage.innerHTML = `<div class="warning-msg">${msg}</div>`;
            warningMessage.classList.remove('hidden');
            setTimeout(() => warningMessage.classList.add('hidden'), 3000);
        }
        
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
                    showWarning(data.error || 'Fout bij verwijderen');
                }
            } catch (e) {
                showWarning('Fout bij verwijderen');
            }
        }
        
        async function deleteBon() {
            if (!confirm('Hele bon verwijderen?')) return;
            
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
                    showWarning(data.error || 'Fout bij verwijderen');
                }
            } catch (e) {
                showWarning('Fout bij verwijderen');
            }
        }
        
        document.addEventListener('keydown', function(e) {
            if (inBetMode) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveRij();
                }
                return;
            }
            
            if (e.key >= '0' && e.key <= '9') {
                pressKey(parseInt(e.key));
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (currentInput) {
                    addNumber();
                } else if (selectedNumbers.length > 0) {
                    finishNumbers();
                }
            } else if (e.key === 'Backspace') {
                pressBackspace();
            } else if (e.key === 'Escape') {
                clearAll();
            }
        });
    </script>
</body>
</html>
