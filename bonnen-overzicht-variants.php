<?php
require_once 'config.php';
require_once 'session_config.php';

// Mock data
$mockPlayer = [
    'id' => 1,
    'name' => 'John Doe',
    'color' => '#3B82F6'
];

$mockBonnen = [
    ['id' => 1, 'date' => '2024-11-28', 'bonnummer' => '1234', 'rijen' => 5, 'bet' => 25.00, 'win' => 18.00],
    ['id' => 2, 'date' => '2024-11-27', 'bonnummer' => '1230', 'rijen' => 3, 'bet' => 15.00, 'win' => 45.00],
    ['id' => 3, 'date' => '2024-11-26', 'bonnummer' => '1228', 'rijen' => 7, 'bet' => 35.00, 'win' => 0.00],
    ['id' => 4, 'date' => '2024-11-25', 'bonnummer' => '1220', 'rijen' => 4, 'bet' => 20.00, 'win' => 12.00],
];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonnen Overzicht - Design Varianten</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 32px;
            text-align: center;
        }

        .page-header h1 {
            font-size: 32px;
            color: #1a1a1a;
            margin-bottom: 12px;
        }

        .page-header p {
            color: #666;
            font-size: 15px;
        }

        .variants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 32px;
            margin-bottom: 60px;
        }

        .variant-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .variant-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 24px;
        }

        .variant-header h3 {
            font-size: 18px;
            margin-bottom: 4px;
        }

        .variant-header p {
            font-size: 13px;
            opacity: 0.9;
        }

        .variant-body {
            padding: 24px;
        }

        .btn {
            background: #007AFF;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }

        .btn:hover {
            background: #0051D5;
            transform: translateY(-1px);
        }

        /* ========================================
           VARIANT 1: COMPACT LIST
           ======================================== */
        .bonnen-list-compact {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .bon-item-compact {
            background: #f9f9f9;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
            cursor: pointer;
        }

        .bon-item-compact:hover {
            background: #f0f0f0;
            transform: translateX(4px);
        }

        .bon-item-left {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .bon-date {
            font-size: 11px;
            font-weight: 600;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-width: 80px;
        }

        .bon-bonnr {
            font-size: 13px;
            font-weight: 600;
            color: #333;
        }

        .bon-rijen {
            font-size: 12px;
            color: #666;
            background: white;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .bon-item-right {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .bon-bet {
            font-size: 13px;
            color: #666;
            min-width: 60px;
            text-align: right;
        }

        .bon-win {
            font-size: 14px;
            font-weight: 700;
            min-width: 70px;
            text-align: right;
        }

        .bon-win.positive {
            color: #34C759;
        }

        .bon-win.negative {
            color: #FF3B30;
        }

        .bon-saldo {
            font-size: 14px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 6px;
            min-width: 80px;
            text-align: center;
        }

        .bon-saldo.positive {
            background: #D1FAE5;
            color: #059669;
        }

        .bon-saldo.negative {
            background: #FEE2E2;
            color: #DC2626;
        }

        /* ========================================
           VARIANT 2: CARD GRID
           ======================================== */
        .bonnen-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .bon-card {
            background: white;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            padding: 16px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .bon-card:hover {
            border-color: #007AFF;
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.1);
            transform: translateY(-2px);
        }

        .bon-card-date {
            font-size: 12px;
            font-weight: 600;
            color: #007AFF;
            margin-bottom: 4px;
        }

        .bon-card-bonnr {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 12px;
        }

        .bon-card-numbers {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-bottom: 12px;
        }

        .number-mini {
            background: #f0f0f0;
            color: #666;
            font-size: 10px;
            font-weight: 600;
            padding: 4px 6px;
            border-radius: 4px;
        }

        .bon-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
        }

        .bon-card-bet {
            font-size: 12px;
            color: #666;
        }

        .bon-card-result {
            font-size: 14px;
            font-weight: 700;
        }

        .bon-card-result.positive {
            color: #34C759;
        }

        .bon-card-result.negative {
            color: #FF3B30;
        }

        /* ========================================
           VARIANT 3: TIMELINE
           ======================================== */
        .bonnen-timeline {
            position: relative;
            padding-left: 40px;
        }

        .bonnen-timeline::before {
            content: '';
            position: absolute;
            left: 16px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #007AFF, transparent);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }

        .timeline-dot {
            position: absolute;
            left: -32px;
            top: 6px;
            width: 12px;
            height: 12px;
            background: #007AFF;
            border: 3px solid white;
            border-radius: 50%;
            box-shadow: 0 0 0 2px #007AFF;
        }

        .timeline-content {
            background: #f9f9f9;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            padding: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .timeline-content:hover {
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .timeline-date {
            font-size: 12px;
            font-weight: 600;
            color: #007AFF;
        }

        .timeline-bonnr {
            font-size: 13px;
            font-weight: 600;
            color: #333;
        }

        .timeline-body {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .timeline-info {
            font-size: 12px;
            color: #666;
        }

        .timeline-result {
            font-size: 14px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 6px;
        }

        .timeline-result.positive {
            background: #D1FAE5;
            color: #059669;
        }

        .timeline-result.negative {
            background: #FEE2E2;
            color: #DC2626;
        }

        /* ========================================
           VARIANT 4: TABLE VIEW
           ======================================== */
        .bonnen-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bonnen-table thead {
            background: #f9f9f9;
        }

        .bonnen-table th {
            padding: 12px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e8e8e8;
        }

        .bonnen-table th:last-child {
            text-align: right;
        }

        .bonnen-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s;
            cursor: pointer;
        }

        .bonnen-table tbody tr:hover {
            background: #f9f9f9;
        }

        .bonnen-table td {
            padding: 14px 12px;
            font-size: 13px;
        }

        .table-date {
            font-weight: 600;
            color: #333;
        }

        .table-bonnr {
            color: #007AFF;
            font-weight: 600;
        }

        .table-rijen {
            color: #666;
        }

        .table-amount {
            font-weight: 600;
            text-align: right;
        }

        .table-saldo {
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-block;
            text-align: right;
        }

        .table-saldo.positive {
            background: #D1FAE5;
            color: #059669;
        }

        .table-saldo.negative {
            background: #FEE2E2;
            color: #DC2626;
        }

        /* Section divider */
        .section-divider {
            margin: 60px 0;
            text-align: center;
        }

        .section-divider h2 {
            font-size: 24px;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .section-divider p {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1>📋 Bonnen Overzicht - Design Varianten</h1>
            <p>Kies je favoriete stijl voor het tonen van bonnen (Spelers & Weekoverzicht)</p>
        </div>

        <h2 style="font-size: 24px; color: #1a1a1a; margin-bottom: 24px;">🎯 SPELERS PAGINA - Alle Bonnen van <?= $mockPlayer['name'] ?></h2>

        <div class="variants-grid">
            <!-- Variant 1: Compact List -->
            <div class="variant-card">
                <div class="variant-header">
                    <h3>Variant 1: Compact List</h3>
                    <p>Snel scanbaar • Mobiel-vriendelijk • Veel info op 1 regel</p>
                </div>
                <div class="variant-body">
                    <div class="bonnen-list-compact">
                        <?php foreach ($mockBonnen as $bon): 
                            $saldo = $bon['win'] - $bon['bet'];
                            $saldoClass = $saldo >= 0 ? 'positive' : 'negative';
                        ?>
                            <div class="bon-item-compact">
                                <div class="bon-item-left">
                                    <div class="bon-date"><?= date('d-m-Y', strtotime($bon['date'])) ?></div>
                                    <div class="bon-bonnr">Bon #<?= $bon['bonnummer'] ?></div>
                                    <div class="bon-rijen"><?= $bon['rijen'] ?> rijen</div>
                                </div>
                                <div class="bon-item-right">
                                    <div class="bon-bet">€<?= number_format($bon['bet'], 2) ?></div>
                                    <div class="bon-win <?= $saldoClass ?>">€<?= number_format($bon['win'], 2) ?></div>
                                    <div class="bon-saldo <?= $saldoClass ?>">
                                        <?= $saldo >= 0 ? '+' : '' ?><?= number_format($saldo, 2) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn" style="margin-top: 16px;" onclick="alert('Dit wordt je overzicht!')">Kies deze stijl</button>
                </div>
            </div>

            <!-- Variant 2: Card Grid -->
            <div class="variant-card">
                <div class="variant-header">
                    <h3>Variant 2: Card Grid</h3>
                    <p>Visueel aantrekkelijk • Touch-friendly • Nummer preview</p>
                </div>
                <div class="variant-body">
                    <div class="bonnen-grid">
                        <?php foreach (array_slice($mockBonnen, 0, 4) as $bon): 
                            $saldo = $bon['win'] - $bon['bet'];
                            $saldoClass = $saldo >= 0 ? 'positive' : 'negative';
                        ?>
                            <div class="bon-card">
                                <div class="bon-card-date"><?= date('d M Y', strtotime($bon['date'])) ?></div>
                                <div class="bon-card-bonnr">#<?= $bon['bonnummer'] ?></div>
                                <div class="bon-card-numbers">
                                    <?php for ($i = 0; $i < $bon['rijen']; $i++): ?>
                                        <div class="number-mini">Rij <?= $i + 1 ?></div>
                                    <?php endfor; ?>
                                </div>
                                <div class="bon-card-footer">
                                    <div class="bon-card-bet">€<?= number_format($bon['bet'], 2) ?> inzet</div>
                                    <div class="bon-card-result <?= $saldoClass ?>">
                                        <?= $saldo >= 0 ? '+' : '' ?><?= number_format($saldo, 2) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn" style="margin-top: 16px;" onclick="alert('Dit wordt je overzicht!')">Kies deze stijl</button>
                </div>
            </div>

            <!-- Variant 3: Timeline -->
            <div class="variant-card">
                <div class="variant-header">
                    <h3>Variant 3: Timeline View</h3>
                    <p>Chronologisch • Rustig • Overzichtelijk</p>
                </div>
                <div class="variant-body">
                    <div class="bonnen-timeline">
                        <?php foreach ($mockBonnen as $bon): 
                            $saldo = $bon['win'] - $bon['bet'];
                            $saldoClass = $saldo >= 0 ? 'positive' : 'negative';
                        ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <div class="timeline-date"><?= date('d-m-Y', strtotime($bon['date'])) ?></div>
                                        <div class="timeline-bonnr">Bon #<?= $bon['bonnummer'] ?></div>
                                    </div>
                                    <div class="timeline-body">
                                        <div class="timeline-info">
                                            <?= $bon['rijen'] ?> rijen • €<?= number_format($bon['bet'], 2) ?> → €<?= number_format($bon['win'], 2) ?>
                                        </div>
                                        <div class="timeline-result <?= $saldoClass ?>">
                                            <?= $saldo >= 0 ? '+' : '' ?><?= number_format($saldo, 2) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn" style="margin-top: 16px;" onclick="alert('Dit wordt je overzicht!')">Kies deze stijl</button>
                </div>
            </div>

            <!-- Variant 4: Table View -->
            <div class="variant-card">
                <div class="variant-header">
                    <h3>Variant 4: Table View</h3>
                    <p>Data analyse • Sorteerbaar • Professioneel</p>
                </div>
                <div class="variant-body">
                    <table class="bonnen-table">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Bonnr</th>
                                <th>Rijen</th>
                                <th>Inzet</th>
                                <th>Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mockBonnen as $bon): 
                                $saldo = $bon['win'] - $bon['bet'];
                                $saldoClass = $saldo >= 0 ? 'positive' : 'negative';
                            ?>
                                <tr>
                                    <td class="table-date"><?= date('d-m-Y', strtotime($bon['date'])) ?></td>
                                    <td class="table-bonnr">#<?= $bon['bonnummer'] ?></td>
                                    <td class="table-rijen"><?= $bon['rijen'] ?></td>
                                    <td class="table-amount">€<?= number_format($bon['bet'], 2) ?></td>
                                    <td class="table-amount">
                                        <span class="table-saldo <?= $saldoClass ?>">
                                            <?= $saldo >= 0 ? '+' : '' ?><?= number_format($saldo, 2) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button class="btn" style="margin-top: 16px;" onclick="alert('Dit wordt je overzicht!')">Kies deze stijl</button>
                </div>
            </div>
        </div>

        <div class="section-divider">
            <h2>📅 WEEKOVERZICHT - Week 48 (25-29 nov)</h2>
            <p>Dezelfde varianten werken ook voor weekoverzicht</p>
        </div>

        <p style="text-align: center; color: #666; font-size: 14px; margin-top: 40px;">
            💡 Tip: Alle varianten hebben dezelfde functionaliteit, alleen verschillend design.<br>
            Klik op een bon om de detail popup te openen (stijl van dashboard).
        </p>
    </div>
</body>
</html>


