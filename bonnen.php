<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'components/winkel_selector.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

$selectedWinkel = $_SESSION['selected_winkel'] ?? null; // null = "Alles"
$winkels = getAllWinkels($conn);
$activeWinkelTheme = resolveActiveWinkelTheme($winkels, $selectedWinkel);
$startDate = date('Y-m-d', strtotime('-7 days'));
$endDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonnen - Lucky Day</title>
    <!-- volgorde gelijk aan andere pagina's: tailwind -> design-system -> fonts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; overflow-x: hidden; overflow-y: scroll; min-height: 100vh; background-color: #F8F9FA; }
        .sort-arrow { display: inline-flex; width: 10px; height: 10px; align-items: center; justify-content: center; }
        .sort-arrow::before { content: '▲'; font-size: 10px; color: #cbd5e1; transition: transform 0.15s, color 0.15s; }
        .sort-arrow.asc::before { color: #6b7280; transform: rotate(0deg); }
        .sort-arrow.desc::before { color: #6b7280; transform: rotate(180deg); }
        .toggle-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; border-radius: 12px; border: 1px solid #e5e7eb; font-weight: 600; font-size: 13px; }
        .toggle-btn.on { background: #ecfdf3; color: #166534; border-color: #bbf7d0; }
        .toggle-btn.off { background: #f3f4f6; color: #6b7280; }
        .ghost-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; border-radius: 12px; border: 1px solid #e5e7eb; background: #f8fafc; font-weight: 600; font-size: 13px; color: #1f2937; }
        .ghost-btn:hover { background: #e5e7eb; }
        .badge-neutral { display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 10px; background: #f1f5f9; color: #0f172a; font-weight: 600; font-size: 12px; }
        .log-empty { color: #94a3b8; font-size: 14px; text-align: center; padding: 16px; }
    </style>
</head>
<body class="bg-gray-50">
<?php include 'components/main_nav.php'; ?>
<?php include 'components/old_data_warning.php'; ?>
<?php include 'components/winkel_bar.php'; ?>

<main class="container-fixed py-4 sm:py-6 space-y-4 sm:space-y-6">
    <div class="card p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">Bonnen</h2>
            <p class="text-sm text-gray-500">Filter op winkel en datum, vink winnende bonnen af als gecontroleerd.</p>
        </div>
        <div class="flex flex-wrap gap-2 items-center">
            <input type="date" id="filter-start" value="<?= $startDate ?>" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
            <input type="date" id="filter-end" value="<?= $endDate ?>" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
            <label class="flex items-center gap-2 text-sm text-gray-700 border border-gray-200 px-3 py-2 rounded-lg bg-gray-50 cursor-pointer">
                <input type="checkbox" id="filter-winning" checked class="h-4 w-4 text-emerald-600 border-gray-300 rounded">
                <span>Alleen winnende bonnen</span>
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700 border border-gray-200 px-3 py-2 rounded-lg bg-gray-50 cursor-pointer">
                <input type="checkbox" id="filter-unchecked" checked class="h-4 w-4 text-amber-600 border-gray-300 rounded">
                <span>Alleen nog niet gecontroleerd</span>
            </label>
            <button id="filter-btn" class="btn-primary">Toon</button>
        </div>
    </div>

    <div class="card p-4">
        <div id="bonnen-error" class="hidden mb-3 text-sm text-red-600"></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="bonnen-table">
                <thead>
                    <tr class="text-left text-xs text-gray-500 border-b border-gray-100 uppercase tracking-wide">
                        <th class="pb-3 font-medium cursor-pointer" data-sort="date">Datum <span class="sort-arrow" data-arrow="date"></span></th>
                        <th class="pb-3 font-medium cursor-pointer" data-sort="player_name">Speler <span class="sort-arrow" data-arrow="player_name"></span></th>
                        <th class="pb-3 font-medium cursor-pointer" data-sort="bonnummer">Bonnummer <span class="sort-arrow" data-arrow="bonnummer"></span></th>
                        <th class="pb-3 font-medium text-right cursor-pointer" data-sort="rijen_count">Rijen <span class="sort-arrow" data-arrow="rijen_count"></span></th>
                        <th class="pb-3 font-medium text-right cursor-pointer" data-sort="bet">Inzet <span class="sort-arrow" data-arrow="bet"></span></th>
                        <th class="pb-3 font-medium text-right cursor-pointer" data-sort="winnings">Uitbetaald <span class="sort-arrow" data-arrow="winnings"></span></th>
                        <th class="pb-3 font-medium text-right cursor-pointer" data-sort="huis">Huisresultaat <span class="sort-arrow" data-arrow="huis"></span></th>
                        <th class="pb-3 font-medium text-center">Gecontroleerd</th>
                        <th class="pb-3 font-medium text-center">Log</th>
                        <th class="pb-3 font-medium text-center">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="bonnen-body">
                    <tr><td colspan="10" class="text-center text-gray-400 py-8">Laden...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Log modal -->
<div id="bon-log-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <p class="text-xs uppercase text-gray-500 tracking-wide">Logboek</p>
                <h3 class="text-lg font-semibold text-gray-900" id="log-modal-title"></h3>
                <p class="text-sm text-gray-500" id="log-modal-subtitle"></p>
            </div>
            <button onclick="closeBonLogModal()" class="ghost-btn">Sluiten</button>
        </div>
        <div id="bon-log-modal-body" class="max-h-[70vh] overflow-y-auto p-6 space-y-3 text-sm text-gray-700">
            <div class="log-empty">Laden…</div>
        </div>
    </div>
</div>

<!-- Move modal -->
<div id="bon-move-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <p class="text-xs uppercase text-gray-500 tracking-wide">Bon verplaatsen</p>
                <h3 class="text-lg font-semibold text-gray-900" id="move-modal-title"></h3>
            </div>
            <button onclick="closeMoveModal()" class="ghost-btn">Sluiten</button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Nieuwe datum</label>
                <input type="date" id="move-date" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Nieuwe winkel</label>
                <select id="move-winkel" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <?php foreach ($winkels as $w): ?>
                        <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['naam']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" id="move-series" class="h-4 w-4 text-emerald-600 border-gray-300 rounded">
                Reeks/vervolgbonnen ook verplaatsen
            </label>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button onclick="closeMoveModal()" class="px-4 py-2 border border-gray-200 rounded-lg text-sm">Annuleren</button>
                <button onclick="confirmMove()" class="px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition">Verplaats</button>
            </div>
            <div id="move-error" class="text-sm text-red-600 hidden"></div>
        </div>
    </div>
</div>

<script>
    const selectedWinkelId = <?= $selectedWinkel === null ? 'null' : (int)$selectedWinkel ?>;
    const tableBody = document.getElementById('bonnen-body');
    let currentBonnen = [];
    let sortCol = 'date';
    let sortAsc = false; // nieuw naar oud

    function escapeHtml(str = '') {
        return (str ?? '').toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function loadBonnen() {
        const start = document.getElementById('filter-start').value;
        const end = document.getElementById('filter-end').value;
        let url = `api/get_bonnen_overzicht.php?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
        if (selectedWinkelId !== null) {
            url += `&winkel=${encodeURIComponent(selectedWinkelId)}`;
        } else {
            url += `&winkel=all`;
        }
        tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-gray-400 py-8">Laden...</td></tr>';
        const errBox = document.getElementById('bonnen-error');
        if (errBox) { errBox.classList.add('hidden'); errBox.textContent = ''; }
        try {
            const res = await fetch(url);
            const data = await res.json();
            if (data.success) {
                currentBonnen = data.bonnen || [];
                renderBonnen();
            } else {
                tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-red-500 py-8">Fout bij laden</td></tr>';
                if (errBox) { errBox.textContent = data.error || 'Fout bij laden'; errBox.classList.remove('hidden'); }
            }
        } catch (e) {
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-red-500 py-8">Fout bij laden</td></tr>';
            if (errBox) { errBox.textContent = 'Kon data niet ophalen'; errBox.classList.remove('hidden'); }
            console.error(e);
        }
    }

    function sortBonnen(list) {
        return [...list].sort((a,b) => {
            let av = a[sortCol];
            let bv = b[sortCol];
            if (sortCol === 'date') {
                av = new Date(av);
                bv = new Date(bv);
            } else if (['bet','winnings','huis','rijen_count'].includes(sortCol)) {
                av = parseFloat(av) || 0;
                bv = parseFloat(bv) || 0;
            } else {
                av = (av || '').toString().toLowerCase();
                bv = (bv || '').toString().toLowerCase();
            }
            if (av < bv) return sortAsc ? -1 : 1;
            if (av > bv) return sortAsc ? 1 : -1;
            return 0;
        });
    }

    function updateArrows() {
        document.querySelectorAll('[data-arrow]').forEach(el => {
            el.classList.remove('asc','desc');
            if (el.dataset.arrow === sortCol) {
                el.classList.add(sortAsc ? 'asc' : 'desc');
            }
        });
    }

    function renderBonnen() {
        const winOnly = document.getElementById('filter-winning').checked;
        const uncheckedOnly = document.getElementById('filter-unchecked').checked;
        const filtered = currentBonnen.filter(b => {
            if (winOnly && b.winnings <= 0) return false;
            if (uncheckedOnly && b.checked_at) return false;
            return true;
        });
        const bonnen = sortBonnen(filtered);
        if (!bonnen.length) {
            tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-gray-400 py-8">Geen bonnen</td></tr>';
            return;
        }
        tableBody.innerHTML = bonnen.map(bon => {
            const huis = bon.bet - bon.winnings;
            const uitb = bon.winnings;
            const uitbHtml = uitb > 0 ? `<span class=\"text-red-500\">€${uitb.toFixed(2).replace('.', ',')}</span>` : '—';
            const checked = bon.checked_at ? true : false;
            return `
                <tr class="hover:bg-gray-50">
                    <td class="py-3 text-gray-800">${escapeHtml(bon.date)}</td>
                    <td class="py-3 text-gray-800">${escapeHtml(bon.player_name)}</td>
                    <td class="py-3 text-gray-800">${escapeHtml(bon.bonnummer || '—')}</td>
                    <td class="py-3 text-right text-gray-600">${bon.rijen_count}</td>
                    <td class="py-3 text-right text-gray-900">€${bon.bet.toFixed(2).replace('.', ',')}</td>
                    <td class="py-3 text-right">${uitbHtml}</td>
                    <td class="py-3 text-right font-semibold ${huis > 0 ? 'text-emerald-600' : (huis < 0 ? 'text-red-500' : 'text-gray-600')}">
                        ${huis > 0 ? '↑' : (huis < 0 ? '↓' : '→')} €${Math.abs(huis).toFixed(2).replace('.', ',')}
                    </td>
                    <td class="py-3 text-center">
                        <button class="toggle-btn ${checked ? 'on' : 'off'}" onclick="toggleChecked(${bon.id}, ${checked ? 'false' : 'true'}, this)">${checked ? 'Gecontroleerd' : 'Markeer'}</button>
                    </td>
                    <td class="py-3 text-center">
                        <button class="ghost-btn" data-log-bon="${bon.id}" data-player="${escapeHtml(bon.player_name)}" data-bonnummer="${escapeHtml(bon.bonnummer || '—')}" data-date="${escapeHtml(bon.date)}">
                            📄 Log
                        </button>
                    </td>
                    <td class="py-3 text-center">
                        <button class="ghost-btn" data-move-bon="${bon.id}" data-date="${escapeHtml(bon.date)}" data-winkel="${bon.winkel_id || ''}">
                            ↔ Verplaats
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
        updateArrows();
        bindLogButtons();
        bindMoveButtons();
    }

    async function toggleChecked(bonId, checked, btn) {
        try {
            await fetch('api/set_bon_checked.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ bon_id: bonId, checked })
            });
            if (btn) {
                if (checked) {
                    btn.classList.remove('off'); btn.classList.add('on'); btn.textContent = 'Gecontroleerd';
                } else {
                    btn.classList.remove('on'); btn.classList.add('off'); btn.textContent = 'Markeer';
                }
            }
        } catch (e) {
            console.error(e);
        }
    }

        document.getElementById('filter-btn').addEventListener('click', loadBonnen);
        document.getElementById('filter-winning').addEventListener('change', renderBonnen);
        document.getElementById('filter-unchecked').addEventListener('change', renderBonnen);
    document.addEventListener('DOMContentLoaded', () => {
        bindWinkelButtons();
        loadBonnen();
        document.querySelectorAll('th[data-sort]').forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.sort;
                if (sortCol === col) {
                    sortAsc = !sortAsc;
                } else {
                    sortCol = col;
                    sortAsc = col === 'date' ? false : true;
                }
                renderBonnen();
            });
        });
    });

    function bindLogButtons() {
        document.querySelectorAll('[data-log-bon]').forEach(btn => {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', () => {
                openBonLog({
                    id: parseInt(btn.dataset.logBon, 10),
                    player: btn.dataset.player || '',
                    bonnummer: btn.dataset.bonnummer || '—',
                    date: btn.dataset.date || ''
                });
            });
        });
    }

    async function openBonLog({ id, player, bonnummer, date }) {
        const modal = document.getElementById('bon-log-modal');
        const body = document.getElementById('bon-log-modal-body');
        document.getElementById('log-modal-title').textContent = `Bon #${id} · ${bonnummer}`;
        document.getElementById('log-modal-subtitle').textContent = `${player || 'Onbekende speler'} · ${date || ''}`;
        body.innerHTML = '<div class="log-empty">Laden…</div>';
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        try {
            const res = await fetch(`api/get_bon_logs.php?bon_id=${id}`);
            const data = await res.json();
            if (!data.success || !data.logs || !data.logs.length) {
                body.innerHTML = '<div class="log-empty">Geen logregels gevonden.</div>';
                return;
            }
            body.innerHTML = data.logs.map(log => {
                const details = log.details_parsed;
                let detailsHtml = '<span class="text-gray-400">—</span>';
                if (details && typeof details === 'object') {
                    detailsHtml = Object.keys(details).map(k => {
                        const v = details[k];
                        return `<div class="text-xs text-gray-700"><span class="font-semibold text-gray-800">${escapeHtml(k)}:</span> ${escapeHtml(typeof v === 'object' ? JSON.stringify(v) : v)}</div>`;
                    }).join('');
                } else if (details) {
                    detailsHtml = `<span class="text-xs text-gray-700">${escapeHtml(details)}</span>`;
                }
                return `
                    <div class="border border-gray-100 rounded-xl p-3 flex items-start gap-3">
                        <div class="badge-neutral">${escapeHtml(log.action)}</div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                                <span>${escapeHtml(log.user_name || 'admin')}</span>
                                <span>${escapeHtml(log.created_at)}</span>
                            </div>
                            ${detailsHtml}
                        </div>
                    </div>
                `;
            }).join('');
        } catch (e) {
            console.error(e);
            body.innerHTML = '<div class="log-empty text-red-500">Fout bij ophalen van logboek.</div>';
        }
    }

    function closeBonLogModal() {
        const modal = document.getElementById('bon-log-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }


    async function selectWinkel(winkelId) {
        try {
            await fetch('api/set_winkel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ winkel_id: winkelId })
            });
            location.reload();
        } catch (e) {
            console.error('Winkel selectie fout:', e);
            location.reload();
        }
    }

    function bindWinkelButtons() {
        document.querySelectorAll('[data-role=\"winkel-button\"]').forEach(button => {
            if (button.dataset.winkelBound === 'true') return;
            button.dataset.winkelBound = 'true';
            button.addEventListener('click', () => {
                const target = button.dataset.winkelTarget;
                if (target === 'all' || target === 'null') {
                    selectWinkel(null);
                    return;
                }
                const winkelId = parseInt(target, 10);
                selectWinkel(Number.isNaN(winkelId) ? null : winkelId);
            });
        });
    }
</script>
</body>
</html>
