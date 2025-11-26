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

$player_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');

if (!$player_id) {
    header('Location: spelers.php');
    exit;
}

$player = getPlayerById($conn, $player_id);
if (!$player) {
    header('Location: spelers.php');
    exit;
}

$history = getPlayerHistory($conn, $player_id, $start_date, $end_date);
$totals = getPlayerTotals($conn, $player_id, $start_date, $end_date);
$total_saldo = floatval($totals['saldo']);

$winning_numbers_cache = [];
if ($history) {
    $unique_dates = array_unique(array_column($history, 'date'));
    if (!empty($unique_dates)) {
        $wn_result = pg_query_params($conn, 
            "SELECT date, numbers FROM winning_numbers WHERE date = ANY($1)",
            ['{' . implode(',', $unique_dates) . '}']
        );
        if ($wn_result) {
            while ($row = pg_fetch_assoc($wn_result)) {
                $winning_numbers_cache[$row['date']] = array_map('intval', explode(',', $row['numbers']));
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="speler_' . $player_id . '_' . $start_date . '_' . $end_date . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Speler: ' . $player['name'] . ' (#' . $player_id . ')'], ';');
    fputcsv($output, ['Periode: ' . $start_date . ' t/m ' . $end_date], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Datum', 'Nummers', 'Speltype', 'Inzet', 'Treffers', 'Winst'], ';');
    
    foreach ($history as $row) {
        fputcsv($output, [
            $row['date'],
            $row['numbers'],
            $row['game_type'],
            number_format($row['bet'], 2, ',', '.'),
            $row['matches'] ?? 0,
            number_format($row['winnings'] ?? 0, 2, ',', '.')
        ], ';');
    }
    
    fputcsv($output, [], ';');
    fputcsv($output, ['TOTALEN', '', '', 
                      number_format($totals['total_bet'], 2, ',', '.'), '',
                      number_format($totals['total_winnings'], 2, ',', '.')], ';');
    fputcsv($output, ['SALDO', '', '', '', '', number_format($total_saldo, 2, ',', '.')], ';');
    
    fclose($output);
    exit;
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;
$total_pages = ceil(count($history) / $per_page);
$history_page = array_slice($history, ($page - 1) * $per_page, $per_page);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($player['name']) ?> - LuckyDays Casino</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <span class="text-xl font-semibold text-gray-900">LuckyDays <span class="text-xs text-gray-400">Casino</span></span>
                    <div class="hidden md:flex space-x-6">
                        <a href="dashboard.php" class="text-gray-500 hover:text-gray-900">Dashboard</a>
                        <a href="weekoverzicht.php" class="text-gray-500 hover:text-gray-900">Week</a>
                        <a href="spelers.php" class="text-gray-900 font-medium">Spelers</a>
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
        <!-- Back Button & Player Header -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-4">
                <a href="spelers.php" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold" style="background: <?= htmlspecialchars($player['color'] ?? '#3B82F6') ?>">
                        <?= strtoupper(substr($player['name'], 0, 1)) ?>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($player['name']) ?></h1>
                        <p class="text-sm text-gray-500">Speler #<?= $player_id ?></p>
                    </div>
                </div>
            </div>
            
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="export_csv">
                <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export CSV
                </button>
            </form>
        </div>

        <!-- Date Range Selection -->
        <div class="bg-white rounded-xl p-4 border border-gray-100 mb-6">
            <form method="GET" class="flex items-center gap-4 flex-wrap">
                <input type="hidden" name="id" value="<?= $player_id ?>">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600">Van:</label>
                    <input type="date" name="start" value="<?= $start_date ?>" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600">Tot:</label>
                    <input type="date" name="end" value="<?= $end_date ?>" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-100 rounded-lg text-sm font-medium hover:bg-gray-200">Toepassen</button>
                <div class="flex gap-2 ml-auto">
                    <a href="?id=<?= $player_id ?>&start=<?= date('Y-m-d', strtotime('-7 days')) ?>&end=<?= date('Y-m-d') ?>" 
                       class="px-3 py-2 text-xs text-gray-600 hover:text-gray-900 border border-gray-200 rounded-lg">7 dagen</a>
                    <a href="?id=<?= $player_id ?>&start=<?= date('Y-m-d', strtotime('-30 days')) ?>&end=<?= date('Y-m-d') ?>" 
                       class="px-3 py-2 text-xs text-gray-600 hover:text-gray-900 border border-gray-200 rounded-lg">30 dagen</a>
                    <a href="?id=<?= $player_id ?>&start=<?= date('Y-m-01') ?>&end=<?= date('Y-m-d') ?>" 
                       class="px-3 py-2 text-xs text-gray-600 hover:text-gray-900 border border-gray-200 rounded-lg">Deze maand</a>
                </div>
            </form>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl p-5 border border-gray-100">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Rijen</p>
                <p class="text-2xl font-semibold text-gray-900"><?= $totals['total_rows'] ?></p>
            </div>
            <div class="bg-white rounded-xl p-5 border border-gray-100">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Inzet</p>
                <p class="text-2xl font-semibold text-gray-900">&euro;<?= number_format($totals['total_bet'], 2, ',', '.') ?></p>
            </div>
            <div class="bg-white rounded-xl p-5 border border-gray-100">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Winst</p>
                <p class="text-2xl font-semibold text-green-600">&euro;<?= number_format($totals['total_winnings'], 2, ',', '.') ?></p>
            </div>
            <div class="bg-white rounded-xl p-5 border border-gray-100 <?= $total_saldo > 0 ? 'bg-green-50 border-green-200' : ($total_saldo < 0 ? 'bg-red-50 border-red-200' : '') ?>">
                <p class="text-xs <?= $total_saldo > 0 ? 'text-green-600' : ($total_saldo < 0 ? 'text-red-600' : 'text-gray-500') ?> uppercase tracking-wide mb-1">
                    <?= $total_saldo > 0 ? 'Krijgt' : ($total_saldo < 0 ? 'Betaalt' : 'Saldo') ?>
                </p>
                <p class="text-2xl font-semibold <?= $total_saldo > 0 ? 'text-green-700' : ($total_saldo < 0 ? 'text-red-700' : 'text-gray-900') ?>">
                    &euro;<?= number_format(abs($total_saldo), 2, ',', '.') ?>
                </p>
            </div>
        </div>

        <!-- History Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Rijen</h2>
                <span class="text-sm text-gray-500"><?= count($history) ?> totaal</span>
            </div>
            
            <?php if (empty($history)): ?>
                <p class="text-gray-500 text-center py-8">Geen rijen in deze periode</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-100 uppercase tracking-wide">
                                <th class="pb-3 font-medium">Datum</th>
                                <th class="pb-3 font-medium">Nummers</th>
                                <th class="pb-3 font-medium text-right">Inzet</th>
                                <th class="pb-3 font-medium text-center">Match</th>
                                <th class="pb-3 font-medium text-right">Winst</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($history_page as $row): 
                                $rowNumbers = explode(',', $row['numbers']);
                                $winningNums = $winning_numbers_cache[$row['date']] ?? [];
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2">
                                    <a href="dashboard.php?date=<?= $row['date'] ?>" class="text-blue-600 hover:text-blue-700">
                                        <?= date('d-m-Y', strtotime($row['date'])) ?>
                                    </a>
                                </td>
                                <td class="py-2">
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($rowNumbers as $num): 
                                            $isMatch = in_array(intval($num), $winningNums);
                                        ?>
                                            <span class="w-6 h-6 flex items-center justify-center rounded-full text-xs font-medium
                                                         <?= $isMatch ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-600' ?>">
                                                <?= $num ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="py-2 text-right text-gray-900">&euro;<?= number_format($row['bet'], 2, ',', '.') ?></td>
                                <td class="py-2 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                                 <?= ($row['matches'] ?? 0) > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                                        <?= $row['matches'] ?? 0 ?>
                                    </span>
                                </td>
                                <td class="py-2 text-right font-medium <?= ($row['winnings'] ?? 0) > 0 ? 'text-green-600' : 'text-gray-400' ?>">
                                    &euro;<?= number_format($row['winnings'] ?? 0, 2, ',', '.') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="flex items-center justify-center gap-2 mt-6">
                    <?php if ($page > 1): ?>
                        <a href="?id=<?= $player_id ?>&start=<?= $start_date ?>&end=<?= $end_date ?>&page=<?= $page - 1 ?>" 
                           class="px-3 py-1 border border-gray-200 rounded text-sm hover:bg-gray-50">Vorige</a>
                    <?php endif; ?>
                    
                    <span class="text-sm text-gray-500">Pagina <?= $page ?> van <?= $total_pages ?></span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?id=<?= $player_id ?>&start=<?= $start_date ?>&end=<?= $end_date ?>&page=<?= $page + 1 ?>" 
                           class="px-3 py-1 border border-gray-200 rounded text-sm hover:bg-gray-50">Volgende</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
