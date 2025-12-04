<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

$winkels = getAllWinkels($conn);
$winkelPalette = getWinkelPalette();

// Definieer de 3 varianten met verschillende winkelkleuren
$variants = [
    [
        'name' => 'Variant A: Minimalistisch',
        'subtitle' => 'Zeer subtiel, bijna niet merkbaar',
        'winkel' => 'Dapper',
        'color' => '#2ECC71',
        'class' => 'variant-a'
    ],
    [
        'name' => 'Variant B: Balanced Modern',
        'subtitle' => 'Duidelijk zichtbaar maar niet opdringerig',
        'winkel' => 'Jumbo',
        'color' => '#F59E0B',
        'class' => 'variant-b'
    ],
    [
        'name' => 'Variant C: Energiek',
        'subtitle' => 'Levendig en opvallend',
        'winkel' => 'Plus',
        'color' => '#EF4444',
        'class' => 'variant-c'
    ]
];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gradient Achtergronden Demo - Lucky Day</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            min-height: 100vh;
            padding: 40px 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .page-header h1 {
            font-size: 48px;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
        }

        .page-header p {
            color: #94a3b8;
            font-size: 18px;
        }

        .demo-container {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            gap: 40px;
        }

        .variant-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }

        .variant-info {
            padding: 32px;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        .variant-info h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1e293b;
        }

        .variant-info p {
            color: #64748b;
            font-size: 16px;
            margin-bottom: 16px;
        }

        .winkel-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
        }

        /* Shared winkel selector styles */
        .winkel-selector-demo {
            position: relative;
            padding: 80px 32px;
            overflow: hidden;
        }

        .winkel-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
            position: relative;
            z-index: 100;
        }

        .winkel-btn {
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 500;
            background: white;
            border: 2px solid #E5E7EB;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .winkel-btn:hover {
            background: #F9FAFB;
            border-color: #D1D5DB;
        }

        .winkel-btn.active {
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* ================================================
           VARIANT A: MINIMALISTISCH
           Zeer subtiele gradient, bijna niet merkbaar
           ================================================ */
        .variant-a .winkel-selector-demo {
            background-color: rgba(46, 204, 113, 0.05);
            position: relative;
        }

        .variant-a .winkel-selector-demo::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to right,
                rgba(46, 204, 113, 0.05),
                rgba(46, 204, 113, 0.15),
                rgba(46, 204, 113, 0.20),
                rgba(46, 204, 113, 0.15),
                rgba(46, 204, 113, 0.05)
            );
            background-size: 400%;
            background-position: 0% 50%;
            animation: minimalFlow 30s ease-in-out infinite;
            pointer-events: none;
        }

        .variant-a .winkel-btn {
            color: #6B7280;
        }

        .variant-a .winkel-btn.active {
            background: rgba(46, 204, 113, 0.10);
            border-color: #2ECC71;
            color: #2ECC71;
        }

        @keyframes minimalFlow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* ================================================
           VARIANT B: BALANCED MODERN (AANBEVOLEN)
           Duidelijk zichtbaar, mooie beweging
           ================================================ */
        .variant-b .winkel-selector-demo {
            background-color: rgba(245, 158, 11, 0.08);
            position: relative;
        }

        /* Eerste laag: bewegende gradient */
        .variant-b .winkel-selector-demo::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to right,
                rgba(245, 158, 11, 0.05),
                rgba(245, 158, 11, 0.20),
                rgba(245, 158, 11, 0.12),
                rgba(245, 158, 11, 0.25),
                rgba(245, 158, 11, 0.05)
            );
            background-size: 400%;
            background-position: 0% 50%;
            animation: balancedWave 25s ease-in-out infinite;
            pointer-events: none;
            z-index: 1;
        }

        /* Tweede laag: glans overlay */
        .variant-b .winkel-selector-demo::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(
                ellipse at 30% 50%,
                rgba(245, 158, 11, 0.20) 0%,
                transparent 50%
            );
            animation: balancedShine 20s ease-in-out infinite;
            pointer-events: none;
            z-index: 2;
        }

        .variant-b .winkel-btn {
            color: #6B7280;
        }

        .variant-b .winkel-btn.active {
            background: rgba(245, 158, 11, 0.10);
            border-color: #F59E0B;
            color: #F59E0B;
        }

        @keyframes balancedWave {
            0%, 100% { background-position: 0% 50%; }
            25% { background-position: 50% 50%; }
            50% { background-position: 100% 50%; }
            75% { background-position: 50% 50%; }
        }

        @keyframes balancedShine {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }

        /* ================================================
           VARIANT C: ENERGIEK
           Levendig met meerdere lagen en beweging
           ================================================ */
        .variant-c .winkel-selector-demo {
            background-color: rgba(239, 68, 68, 0.10);
            position: relative;
        }

        /* Eerste laag: bewegende gradient */
        .variant-c .winkel-selector-demo::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to right,
                rgba(239, 68, 68, 0.08),
                rgba(239, 68, 68, 0.25),
                rgba(239, 68, 68, 0.15),
                rgba(239, 68, 68, 0.30),
                rgba(239, 68, 68, 0.08)
            );
            background-size: 400%;
            background-position: 0% 50%;
            animation: energyFlow 20s ease-in-out infinite;
            pointer-events: none;
            z-index: 1;
        }

        /* Tweede laag: bewegende gloed */
        .variant-c .winkel-selector-demo::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(
                circle at 40% 50%,
                rgba(239, 68, 68, 0.25) 0%,
                transparent 60%
            );
            animation: energyGlow 15s ease-in-out infinite;
            pointer-events: none;
            z-index: 2;
        }

        .variant-c .winkel-btn {
            color: #6B7280;
        }

        .variant-c .winkel-btn.active {
            background: rgba(239, 68, 68, 0.10);
            border-color: #EF4444;
            color: #EF4444;
        }

        @keyframes energyFlow {
            0%, 100% { background-position: 0% 50%; }
            33% { background-position: 100% 50%; }
            66% { background-position: 50% 50%; }
        }

        @keyframes energyGlow {
            0%, 100% {
                transform: translate(0, 0) scale(1);
                opacity: 0.6;
            }
            50% {
                transform: translate(30%, 0) scale(1.2);
                opacity: 1;
            }
        }

        @keyframes meshMove {
            0% { transform: translateX(0); }
            100% { transform: translateX(40px); }
        }

        /* Stats table */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            padding: 32px;
            background: #f8fafc;
        }

        .stat-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #e2e8f0;
        }

        .stat-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: white;
            color: #1e293b;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            transition: all 0.2s;
            z-index: 1000;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.4);
        }

        .recommendation-badge {
            display: inline-block;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 12px;
        }

        /* Code preview */
        .code-preview {
            padding: 32px;
            background: #1e293b;
            color: #e2e8f0;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            overflow-x: auto;
        }

        .code-preview .comment {
            color: #64748b;
        }

        .code-preview .property {
            color: #38bdf8;
        }

        .code-preview .value {
            color: #fb923c;
        }

        .code-preview .animation {
            color: #a78bfa;
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-btn">← Terug naar Dashboard</a>

    <div class="page-header">
        <h1>🎨 Geanimeerde Gradient Achtergronden</h1>
        <p>3 Subtiele varianten voor de Winkel Selector Bar</p>
    </div>

    <div class="demo-container">
        <?php foreach ($variants as $index => $variant): ?>
        <div class="variant-card">
            <!-- Info Header -->
            <div class="variant-info">
                <h2>
                    <?= $variant['name'] ?>
                    <?php if ($index === 1): ?>
                    <span class="recommendation-badge">⭐ Aanbevolen</span>
                    <?php endif; ?>
                </h2>
                <p><?= $variant['subtitle'] ?></p>
                <div class="winkel-badge" style="background: <?= $variant['color'] ?>15; color: <?= $variant['color'] ?>; border: 2px solid <?= $variant['color'] ?>30;">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                    </svg>
                    <?= $variant['winkel'] ?>
                </div>
            </div>

            <!-- Live Demo -->
            <div class="<?= $variant['class'] ?> winkel-selector-demo">
                <div class="winkel-buttons">
                    <button class="winkel-btn">Alles</button>
                    <button class="winkel-btn active"><?= $variant['winkel'] ?></button>
                    <button class="winkel-btn">Andere Winkel 1</button>
                    <button class="winkel-btn">Andere Winkel 2</button>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <?php if ($index === 0): ?>
                    <div class="stat-item" style="border-color: <?= $variant['color'] ?>">
                        <div class="stat-label">Zichtbaarheid</div>
                        <div class="stat-value">⭐⭐☆☆☆</div>
                    </div>
                    <div class="stat-item" style="border-color: <?= $variant['color'] ?>">
                        <div class="stat-label">Animatie Snelheid</div>
                        <div class="stat-value">30s (zeer traag)</div>
                    </div>
                    <div class="stat-item" style="border-color: <?= $variant['color'] ?>">
                        <div class="stat-label">Lagen</div>
                        <div class="stat-value">1 gradient</div>
                    </div>
                    <div class="stat-item" style="border-color: <?= $variant['color'] ?>">
                        <div class="stat-label">Performance</div>
                        <div class="stat-value">Uitstekend</div>
                    </div>
                <?php elseif ($index === 1): ?>
                    <div class="stat-item" style="border-color: <?= $variant['color'] ?>">
                        <div class="stat-label">Zichtbaarheid</div>
                        <div class="stat-value">⭐⭐⭐⭐☆</div>
                    </div>
                    <div class="stat-item" style="border-color: <?= $variant['color'] ?>">
                        <div class="stat-label">Animatie Snelheid</div>
                        <div class="stat-value">25s gradient + 20s shine</div>
                    </div>
                    <div class="stat-item" style="border-color: <?= $variant['color'] ?>">
                        <div class="stat-label">Lagen</div>
                        <div class="stat-value">2 (gradient + radial)</div>
                    </div>
                    <div class="stat-item" style="border-color: <?= $variant['color'] ?>">
                        <div class="stat-label">Performance</div>
                        <div class="stat-value">Goed</div>
                    </div>
                <?php else: ?>
                    <div class="stat-item" style="border-color: <?= $variant['color'] ?>">
                        <div class="stat-label">Zichtbaarheid</div>
                        <div class="stat-value">⭐⭐⭐⭐⭐</div>
                    </div>
                    <div class="stat-item" style="border-color: <?= $variant['color'] ?>">
                        <div class="stat-label">Animatie Snelheid</div>
                        <div class="stat-value">20s + 15s + 40s</div>
                    </div>
                    <div class="stat-item" style="border-color: <?= $variant['color'] ?>">
                        <div class="stat-label">Lagen</div>
                        <div class="stat-value">3 (gradient + glow + mesh)</div>
                    </div>
                    <div class="stat-item" style="border-color: <?= $variant['color'] ?>">
                        <div class="stat-label">Performance</div>
                        <div class="stat-value">Acceptabel</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Code Preview -->
            <div class="code-preview">
                <div class="comment">/* CSS voor <?= $variant['name'] ?> */</div>
                <?php if ($index === 0): ?>
.winkel-selector-bar {
    <span class="property">background</span>: <span class="value">linear-gradient(to right,
        #2ECC7103, #2ECC7106, #2ECC7108, #2ECC7106, #2ECC7103)</span>;
    <span class="property">background-size</span>: <span class="value">400%</span>;
    <span class="property">animation</span>: <span class="animation">minimalFlow 30s ease-in-out infinite</span>;
}

@keyframes <span class="animation">minimalFlow</span> {
    0%, 100% { <span class="property">background-position</span>: <span class="value">0% 50%</span>; }
    50% { <span class="property">background-position</span>: <span class="value">100% 50%</span>; }
}
                <?php elseif ($index === 1): ?>
.winkel-selector-bar {
    <span class="property">background</span>: <span class="value">linear-gradient(to right,
        #F59E0B08, #F59E0B15, #F59E0B10, #F59E0B18, #F59E0B08)</span>;
    <span class="property">background-size</span>: <span class="value">400%</span>;
    <span class="property">animation</span>: <span class="animation">balancedWave 25s ease-in-out infinite</span>;
}

.winkel-selector-bar::before {
    <span class="property">background</span>: <span class="value">radial-gradient(ellipse at 30% 50%,
        #F59E0B12 0%, transparent 50%)</span>;
    <span class="property">animation</span>: <span class="animation">balancedShine 20s ease-in-out infinite</span>;
}
                <?php else: ?>
.winkel-selector-bar {
    <span class="property">background</span>: <span class="value">linear-gradient(to right,
        #EF444410, #EF444420, #EF444415, #EF444425, #EF444410)</span>;
    <span class="property">background-size</span>: <span class="value">400%</span>;
    <span class="property">animation</span>: <span class="animation">energyFlow 20s ease-in-out infinite</span>;
}

<span class="comment">/* 3 gelaagde animaties voor maximale dynamiek */</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Final Recommendation -->
        <div class="variant-card" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
            <div style="padding: 48px; text-align: center; color: white;">
                <h2 style="font-size: 36px; font-weight: 800; margin-bottom: 16px;">
                    🎯 Mijn Aanbeveling
                </h2>
                <p style="font-size: 20px; line-height: 1.6; margin-bottom: 24px; opacity: 0.95;">
                    <strong>Variant B: Balanced Modern</strong> is de perfecte keuze
                </p>
                <div style="max-width: 700px; margin: 0 auto; font-size: 16px; line-height: 1.8; opacity: 0.9;">
                    <p style="margin-bottom: 16px;">
                        ✓ Gradient is <strong>WÉL duidelijk zichtbaar</strong> (zoals je wilt)<br>
                        ✓ Toch <strong>subtiel en rustig</strong> genoeg voor professioneel gebruik<br>
                        ✓ Moderne uitstraling met <strong>2-laags animatie</strong> voor diepte<br>
                        ✓ Goede <strong>performance</strong> zonder zware effecten
                    </p>
                    <p style="font-size: 14px; opacity: 0.8;">
                        Variant A is te subtiel (je ziet bijna niks),<br>
                        Variant C is te druk voor een rustig design.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Maak de demo knoppen interactief
        document.querySelectorAll('.winkel-buttons').forEach(container => {
            container.querySelectorAll('.winkel-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    container.querySelectorAll('.winkel-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                });
            });
        });
    </script>
</body>
</html>
