<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Add Bon - Keyboard Driven</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f7;
            padding: 40px 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .demo-header {
            background: white;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 32px;
            text-align: center;
        }

        .demo-header h1 {
            font-size: 28px;
            color: #1a1a1a;
            margin-bottom: 12px;
        }

        .demo-header p {
            color: #666;
            font-size: 15px;
        }

        .btn {
            background: #007AFF;
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn:hover {
            background: #0051D5;
            transform: translateY(-1px);
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: white;
            border-radius: 20px;
            max-width: 580px;
            width: 100%;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .close-btn {
            background: #f5f5f5;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            color: #666;
        }

        .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
        }

        .winning-preview {
            background: linear-gradient(135deg, #FFF5E6 0%, #FFE6CC 100%);
            border: 2px solid #FFD699;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 24px;
        }

        .winning-preview-title {
            font-size: 10px;
            font-weight: 700;
            color: #D97706;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .winning-preview-grid {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 4px;
        }

        .winning-num {
            aspect-ratio: 1;
            background: white;
            border: 1px solid #FFD699;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            color: #D97706;
        }

        .input-section {
            margin-bottom: 24px;
        }

        .input-section.hidden {
            display: none;
        }

        .input-label {
            font-size: 13px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-field {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.2s;
            font-family: inherit;
        }

        .input-field:focus {
            outline: none;
            border-color: #007AFF;
            background: #F9FAFB;
        }

        .input-field.large {
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            padding: 12px;
        }

        .hint-text {
            font-size: 12px;
            color: #999;
            margin-top: 6px;
        }

        .chips-container {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 12px;
            min-height: 32px;
        }

        .chip {
            background: #007AFF;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
        }

        .chip-mini {
            background: #f0f0f0;
            color: #333;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .saved-rows {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #f0f0f0;
        }

        .saved-rows-title {
            font-size: 13px;
            font-weight: 700;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .saved-row-item {
            background: #f9f9f9;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 8px;
        }

        .saved-row-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .saved-row-num {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
        }

        .saved-row-bet {
            font-size: 14px;
            font-weight: 700;
            color: #007AFF;
        }

        .saved-row-numbers {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 12px;
            justify-content: space-between;
        }

        .modal-footer .btn {
            flex: 1;
        }

        .btn-cancel {
            background: #f0f0f0;
            color: #333;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .btn-done {
            background: #34C759;
        }

        .btn-done:hover {
            background: #2AA149;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="demo-header">
            <h1>⚡️ Quick Add Bon</h1>
            <p>Keyboard-driven bon invoer zoals je gewend bent</p>
            <button class="btn" onclick="openModal()">+ Nieuwe Bon</button>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2>Nieuwe Bon Toevoegen</h2>
                <button class="close-btn" onclick="closeModal()">✕</button>
            </div>

            <div class="modal-body">
                <!-- Winning Numbers -->
                <div class="winning-preview">
                    <div class="winning-preview-title">Winnende Nummers (29-11-2024)</div>
                    <div class="winning-preview-grid">
                        <div class="winning-num">3</div>
                        <div class="winning-num">9</div>
                        <div class="winning-num">12</div>
                        <div class="winning-num">18</div>
                        <div class="winning-num">19</div>
                        <div class="winning-num">20</div>
                        <div class="winning-num">21</div>
                        <div class="winning-num">23</div>
                        <div class="winning-num">24</div>
                        <div class="winning-num">29</div>
                        <div class="winning-num">46</div>
                        <div class="winning-num">47</div>
                        <div class="winning-num">54</div>
                        <div class="winning-num">57</div>
                        <div class="winning-num">58</div>
                        <div class="winning-num">63</div>
                        <div class="winning-num">66</div>
                        <div class="winning-num">70</div>
                        <div class="winning-num">73</div>
                        <div class="winning-num">74</div>
                    </div>
                </div>

                <!-- Step 1: Player Name -->
                <div class="input-section" id="step-player">
                    <div class="input-label">Speler Naam</div>
                    <input type="text" 
                           id="input-player" 
                           class="input-field" 
                           placeholder="Typ spelersnaam...">
                    <div class="hint-text">Druk Enter om door te gaan</div>
                </div>

                <!-- Step 2: Bonnummer -->
                <div class="input-section hidden" id="step-bonnr">
                    <div class="input-label">Bonnummer</div>
                    <input type="text" 
                           id="input-bonnr" 
                           class="input-field" 
                           placeholder="Typ bonnummer...">
                    <div class="hint-text">Druk Enter om door te gaan</div>
                </div>

                <!-- Step 3: Numbers -->
                <div class="input-section hidden" id="step-numbers">
                    <div class="input-label">Rij <span id="row-num">1</span> - Type Nummers</div>
                    <input type="number" 
                           id="input-number" 
                           class="input-field large" 
                           min="0"
                           max="80">
                    <div class="hint-text">Type 0 om rij op te slaan • Max 7 nummers • Inzet: €<span id="current-bet">5.00</span></div>
                    <div class="chips-container" id="chips">
                        <span style="color: #999; font-size: 13px;">Nummers verschijnen hier...</span>
                    </div>
                </div>

                <!-- Saved Rows -->
                <div class="saved-rows" id="saved-rows" style="display: none;">
                    <div class="saved-rows-title">Opgeslagen Rijen (<span id="saved-count">0</span>)</div>
                    <div id="saved-list"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-cancel" onclick="closeModal()">Annuleren</button>
                <button class="btn btn-done" id="finish-btn" style="display: none;" onclick="finishBon()">
                    ✓ Klaar (<span id="total-count">0</span> rijen)
                </button>
            </div>
        </div>
    </div>

    <script>
        let state = {
            step: 'player',
            currentRow: 1,
            playerName: '',
            bonNr: '',
            currentNumbers: [],
            savedRows: [],
            defaultBet: 5.00
        };

        function openModal() {
            document.getElementById('modal').classList.add('active');
            resetState();
            setTimeout(() => {
                document.getElementById('input-player').focus();
            }, 100);
        }

        function closeModal() {
            document.getElementById('modal').classList.remove('active');
        }

        function resetState() {
            state = {
                step: 'player',
                currentRow: 1,
                playerName: '',
                bonNr: '',
                currentNumbers: [],
                savedRows: [],
                defaultBet: 5.00
            };

            document.getElementById('step-player').classList.remove('hidden');
            document.getElementById('step-bonnr').classList.add('hidden');
            document.getElementById('step-numbers').classList.add('hidden');
            document.getElementById('saved-rows').style.display = 'none';
            document.getElementById('finish-btn').style.display = 'none';

            document.getElementById('input-player').value = '';
            document.getElementById('input-bonnr').value = '';
            document.getElementById('input-number').value = '';
        }

        // Event Listeners
        document.getElementById('input-player').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const val = e.target.value.trim();
                if (val) {
                    state.playerName = val;
                    document.getElementById('step-player').classList.add('hidden');
                    document.getElementById('step-bonnr').classList.remove('hidden');
                    document.getElementById('input-bonnr').focus();
                }
            }
        });

        document.getElementById('input-bonnr').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const val = e.target.value.trim();
                if (val) {
                    state.bonNr = val;
                    document.getElementById('step-bonnr').classList.add('hidden');
                    document.getElementById('step-numbers').classList.remove('hidden');
                    document.getElementById('input-number').focus();
                }
            }
        });

        document.getElementById('input-number').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const num = parseInt(e.target.value);

                if (num === 0) {
                    // Save row immediately with default bet
                    if (state.currentNumbers.length > 0) {
                        state.savedRows.push({
                            row: state.currentRow,
                            numbers: [...state.currentNumbers],
                            bet: state.defaultBet
                        });

                        renderSavedRows();

                        // Reset for next row
                        state.currentRow++;
                        state.currentNumbers = [];
                        document.getElementById('row-num').textContent = state.currentRow;
                        document.getElementById('chips').innerHTML = '<span style="color: #999; font-size: 13px;">Nummers verschijnen hier...</span>';

                        // Show finish button
                        document.getElementById('finish-btn').style.display = 'inline-flex';
                        document.getElementById('total-count').textContent = state.savedRows.length;
                    }
                } else if (num >= 1 && num <= 80) {
                    // Add number
                    if (!state.currentNumbers.includes(num)) {
                        if (state.currentNumbers.length < 7) {
                            state.currentNumbers.push(num);
                            renderChips();
                        } else {
                            alert('Maximum 7 nummers per rij');
                        }
                    } else {
                        alert('Nummer is al toegevoegd');
                    }
                }

                e.target.value = '';
            }
        });

        function renderChips() {
            const container = document.getElementById('chips');
            if (state.currentNumbers.length === 0) {
                container.innerHTML = '<span style="color: #999; font-size: 13px;">Nummers verschijnen hier...</span>';
            } else {
                container.innerHTML = state.currentNumbers
                    .sort((a, b) => a - b)
                    .map(num => `<div class="chip">${num}</div>`)
                    .join('');
            }
        }

        function renderSavedRows() {
            const container = document.getElementById('saved-rows');
            const list = document.getElementById('saved-list');

            if (state.savedRows.length === 0) {
                container.style.display = 'none';
                return;
            }

            container.style.display = 'block';
            document.getElementById('saved-count').textContent = state.savedRows.length;

            list.innerHTML = state.savedRows
                .map(row => `
                    <div class="saved-row-item">
                        <div class="saved-row-header">
                            <span class="saved-row-num">Rij ${row.row}</span>
                            <span class="saved-row-bet">€${row.bet.toFixed(2)}</span>
                        </div>
                        <div class="saved-row-numbers">
                            ${row.numbers.sort((a, b) => a - b).map(n => `<span class="chip-mini">${n}</span>`).join('')}
                        </div>
                    </div>
                `)
                .join('');
        }

        function finishBon() {
            if (state.savedRows.length === 0) {
                alert('Voeg minimaal 1 rij toe');
                return;
            }

            const total = state.savedRows.reduce((sum, row) => sum + row.bet, 0);

            alert(`✅ Bon opgeslagen!\n\nSpeler: ${state.playerName}\nBonnummer: ${state.bonNr}\nRijen: ${state.savedRows.length}\nTotaal: €${total.toFixed(2)}`);

            closeModal();
        }
    </script>
</body>
</html>

