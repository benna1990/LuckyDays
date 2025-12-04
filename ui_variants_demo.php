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

// Gebruik 3 verschillende winkels voor de 3 varianten
$variant_winkels = [
    'A' => ['naam' => 'Dapper', 'color' => '#2ECC71'],
    'B' => ['naam' => 'Jumbo', 'color' => '#F59E0B'],
    'C' => ['naam' => 'Plus', 'color' => '#EF4444']
];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UI Varianten Demo - Lucky Day</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .variant-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            margin-bottom: 40px;
        }

        .variant-header {
            padding: 24px 32px;
            border-bottom: 3px solid #E5E7EB;
        }

        .variant-body {
            padding: 32px;
        }

        /* ========================================
           VARIANT A: MINIMALISTISCH (Dapper/Groen)
           ======================================== */
        .variant-a {
            --accent: #2ECC71;
        }

        .variant-a .winkel-pill {
            background: linear-gradient(135deg,
                var(--accent)06 0%,
                var(--accent)0A 25%,
                var(--accent)0E 50%,
                var(--accent)0A 75%,
                var(--accent)06 100%
            );
            background-size: 200% 200%;
            animation: minimalistMesh 25s ease-in-out infinite;
            border: 2px solid var(--accent)20;
            padding: 12px 28px;
            border-radius: 24px;
            font-weight: 600;
            color: var(--accent);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px var(--accent)10;
        }

        @keyframes minimalistMesh {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .variant-a .action-btn {
            background: var(--accent);
            color: white;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 8px var(--accent)30;
        }

        .variant-a .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px var(--accent)40;
        }

        .variant-a .date-btn {
            width: 60px;
            height: 40px;
            border: 1.5px solid #E5E7EB;
            border-radius: 6px;
            background: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 500;
            color: #6B7280;
            transition: all 0.2s;
            position: relative;
        }

        .variant-a .date-btn:hover {
            border-color: var(--accent)40;
            background: var(--accent)03;
        }

        .variant-a .date-btn.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .variant-a .date-btn.has-data::after {
            content: '';
            position: absolute;
            bottom: 3px;
            left: 8px;
            right: 8px;
            height: 2px;
            background: var(--accent);
            border-radius: 2px;
            opacity: 0.5;
        }

        /* ========================================
           VARIANT B: BALANCED MODERN (Jumbo/Oranje)
           ======================================== */
        .variant-b {
            --accent: #F59E0B;
        }

        .variant-b .winkel-pill {
            background: linear-gradient(
                -45deg,
                var(--accent)08,
                var(--accent)16,
                var(--accent)10,
                var(--accent)0C
            );
            background-size: 400% 400%;
            animation: balancedWave 30s ease infinite;
            border: 2px solid var(--accent)30;
            padding: 12px 28px;
            border-radius: 24px;
            font-weight: 600;
            color: var(--accent);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px var(--accent)15;
            position: relative;
            overflow: hidden;
        }

        .variant-b .winkel-pill::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, var(--accent)15 0%, transparent 70%);
            animation: shimmer 8s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes balancedWave {
            0%, 100% { background-position: 0% 50%; }
            25% { background-position: 100% 50%; }
            50% { background-position: 0% 100%; }
            75% { background-position: 100% 0%; }
        }

        @keyframes shimmer {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(20%, 20%); }
        }

        .variant-b .action-btn {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent)DD 100%);
            color: white;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px var(--accent)35;
            position: relative;
            overflow: hidden;
        }

        .variant-b .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .variant-b .action-btn:hover::before {
            left: 100%;
        }

        .variant-b .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px var(--accent)45;
        }

        .variant-b .date-btn {
            width: 62px;
            height: 42px;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            background: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 500;
            color: #6B7280;
            transition: all 0.2s;
            position: relative;
        }

        .variant-b .date-btn:hover {
            border-color: var(--accent);
            background: var(--accent)08;
            transform: translateY(-1px);
        }

        .variant-b .date-btn.active {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent)E6 100%);
            color: white;
            border-color: var(--accent);
            box-shadow: 0 2px 8px var(--accent)30;
        }

        .variant-b .date-btn.has-data::after {
            content: '';
            position: absolute;
            bottom: 4px;
            left: 10px;
            right: 10px;
            height: 3px;
            background: var(--accent);
            border-radius: 2px;
            opacity: 0.6;
        }

        /* ========================================
           VARIANT C: ENERGIEK (Plus/Rood)
           ======================================== */
        .variant-c {
            --accent: #EF4444;
        }

        .variant-c .winkel-pill {
            background: var(--accent)0F;
            border: 2.5px solid var(--accent)40;
            padding: 12px 28px;
            border-radius: 28px;
            font-weight: 700;
            color: var(--accent);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow:
                0 0 20px var(--accent)18,
                inset 0 0 30px var(--accent)08;
            animation: energyGlow 20s ease-in-out infinite;
            position: relative;
        }

        .variant-c .winkel-pill::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 28px;
            padding: 2px;
            background: linear-gradient(45deg, var(--accent)40, transparent, var(--accent)40);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            animation: rotateBorder 8s linear infinite;
        }

        @keyframes energyGlow {
            0%, 100% {
                box-shadow: 0 0 20px var(--accent)18, inset 0 0 30px var(--accent)08;
            }
            50% {
                box-shadow: 0 0 35px var(--accent)28, inset 0 0 40px var(--accent)12;
            }
        }

        @keyframes rotateBorder {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .variant-c .action-btn {
            background: var(--accent);
            color: white;
            padding: 11px 26px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 14px var(--accent)40;
            position: relative;
            overflow: hidden;
        }

        .variant-c .action-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .variant-c .action-btn:hover::after {
            width: 300px;
            height: 300px;
        }

        .variant-c .action-btn:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 6px 20px var(--accent)50;
        }

        .variant-c .date-btn {
            width: 64px;
            height: 44px;
            border: 2px solid #E5E7EB;
            border-radius: 10px;
            background: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            color: #6B7280;
            transition: all 0.3s;
            position: relative;
        }

        .variant-c .date-btn:hover {
            border-color: var(--accent);
            background: var(--accent)0A;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px var(--accent)15;
        }

        .variant-c .date-btn.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
            box-shadow: 0 0 20px var(--accent)30;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 20px var(--accent)30; }
            50% { box-shadow: 0 0 30px var(--accent)40; }
        }

        .variant-c .date-btn.has-data::after {
            content: '';
            position: absolute;
            bottom: 4px;
            left: 10px;
            right: 10px;
            height: 3px;
            background: var(--accent);
            border-radius: 2px;
            box-shadow: 0 0 8px var(--accent);
        }

        /* Shared styles */
        .demo-section {
            margin-bottom: 32px;
        }

        .demo-section h4 {
            font-size: 14px;
            font-weight: 600;
            color: #6B7280;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .demo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .date-selector-scroll {
            display: flex;
            gap: 4px;
            overflow-x: auto;
            padding: 12px 0;
            scrollbar-width: thin;
        }

        .date-selector-scroll::-webkit-scrollbar {
            height: 6px;
        }

        .date-selector-scroll::-webkit-scrollbar-track {
            background: #F3F4F6;
            border-radius: 3px;
        }

        .date-selector-scroll::-webkit-scrollbar-thumb {
            background: #D1D5DB;
            border-radius: 3px;
        }

        .week-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .week-label {
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.6;
            padding: 2px 6px;
            border-radius: 4px;
            align-self: flex-start;
        }

        .date-buttons {
            display: flex;
            gap: 4px;
        }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: white;
            color: #374151;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            transition: all 0.2s;
            z-index: 1000;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.25);
        }

        .comparison-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        .comparison-table th,
        .comparison-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #E5E7EB;
        }

        .comparison-table th {
            background: #F9FAFB;
            font-weight: 600;
            color: #374151;
            font-size: 13px;
        }

        .comparison-table td {
            font-size: 13px;
            color: #6B7280;
        }

        .check {
            color: #10B981;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-btn">← Terug naar Dashboard</a>

    <div style="max-width: 1400px; margin: 0 auto;">
        <div style="text-align: center; margin-bottom: 40px;">
            <h1 style="font-size: 36px; font-weight: 800; color: white; margin-bottom: 12px;">
                🎨 UI Variant Showcase
            </h1>
            <p style="font-size: 18px; color: rgba(255,255,255,0.9);">
                3 Complete UI Designs voor LuckyDays App
            </p>
        </div>

        <!-- VARIANT A: MINIMALISTISCH -->
        <div class="variant-container variant-a">
            <div class="variant-header" style="background: linear-gradient(135deg, #2ECC7108 0%, #2ECC7103 100%);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="font-size: 28px; font-weight: 700; color: #2ECC71; margin-bottom: 4px;">
                            Variant A: Minimalistisch
                        </h2>
                        <p style="font-size: 14px; color: #6B7280;">
                            Voorbeeld: Dapper (Groen) • Subtiel, Clean, Professioneel
                        </p>
                    </div>
                    <div class="winkel-pill">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                        </svg>
                        Dapper
                    </div>
                </div>
            </div>

            <div class="variant-body">
                <!-- Winkel Pills -->
                <div class="demo-section">
                    <h4>Winkelpills met Subtiele Mesh Gradient</h4>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <div class="winkel-pill">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            Alles
                        </div>
                        <div class="winkel-pill" style="--accent: #2ECC71;">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            Dapper
                        </div>
                        <div class="winkel-pill" style="--accent: #F59E0B;">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            Jumbo
                        </div>
                        <div class="winkel-pill" style="--accent: #EF4444;">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            Plus
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="demo-section">
                    <h4>Actieknoppen</h4>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <button class="action-btn">Opslaan</button>
                        <button class="action-btn">Exporteer CSV</button>
                        <button class="action-btn">Nieuwe Bon</button>
                        <button class="action-btn">Wachtwoord Wijzigen</button>
                    </div>
                </div>

                <!-- Date Selector -->
                <div class="demo-section">
                    <h4>Scrollbare Dagselector (Compact Design)</h4>
                    <div class="card">
                        <div class="date-selector-scroll">
                            <?php
                            $days = ['Ma 18', 'Di 19', 'Wo 20', 'Do 21', 'Vr 22', 'Za 23', 'Zo 24', 'Ma 25', 'Di 26', 'Wo 27', 'Do 28', 'Vr 29'];
                            foreach ($days as $i => $day) {
                                $classes = 'date-btn';
                                if ($i === 5) $classes .= ' active';
                                if ($i >= 3 && $i <= 7) $classes .= ' has-data';
                                echo "<button class='$classes'>$day</button>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Characteristics -->
                <div class="demo-section">
                    <h4>Kenmerken</h4>
                    <table class="comparison-table">
                        <tr>
                            <td><strong>Gradient Animatie</strong></td>
                            <td>Zeer subtiele mesh (25s cycle)</td>
                        </tr>
                        <tr>
                            <td><strong>Zichtbaarheid</strong></td>
                            <td>⭐⭐⚪⚪⚪ - Subtiel, bijna niet zichtbaar</td>
                        </tr>
                        <tr>
                            <td><strong>Knoppen</strong></td>
                            <td>Solid kleuren, zachte shadows</td>
                        </tr>
                        <tr>
                            <td><strong>Spacing</strong></td>
                            <td>Ruim, veel witruimte</td>
                        </tr>
                        <tr>
                            <td><strong>Best voor</strong></td>
                            <td>Professionele, zakelijke uitstraling</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- VARIANT B: BALANCED MODERN -->
        <div class="variant-container variant-b">
            <div class="variant-header" style="background: linear-gradient(135deg, #F59E0B10 0%, #F59E0B05 100%);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="font-size: 28px; font-weight: 700; color: #F59E0B; margin-bottom: 4px;">
                            Variant B: Balanced Modern
                        </h2>
                        <p style="font-size: 14px; color: #6B7280;">
                            Voorbeeld: Jumbo (Oranje) • Duidelijk Zichtbaar, Moderne Balans
                        </p>
                    </div>
                    <div class="winkel-pill">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                        </svg>
                        Jumbo
                    </div>
                </div>
            </div>

            <div class="variant-body">
                <!-- Winkel Pills -->
                <div class="demo-section">
                    <h4>Winkelpills met Diagonal Wave Pattern + Shimmer</h4>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <div class="winkel-pill">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            Alles
                        </div>
                        <div class="winkel-pill" style="--accent: #2ECC71;">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            Dapper
                        </div>
                        <div class="winkel-pill" style="--accent: #F59E0B;">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            Jumbo
                        </div>
                        <div class="winkel-pill" style="--accent: #EF4444;">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            Plus
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="demo-section">
                    <h4>Actieknoppen met Gradient & Shine Effect</h4>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <button class="action-btn">Opslaan</button>
                        <button class="action-btn">Exporteer CSV</button>
                        <button class="action-btn">Nieuwe Bon</button>
                        <button class="action-btn">Wachtwoord Wijzigen</button>
                    </div>
                </div>

                <!-- Date Selector -->
                <div class="demo-section">
                    <h4>Scrollbare Dagselector met Hover Lift Effect</h4>
                    <div class="card">
                        <div class="date-selector-scroll">
                            <?php
                            foreach ($days as $i => $day) {
                                $classes = 'date-btn';
                                if ($i === 5) $classes .= ' active';
                                if ($i >= 3 && $i <= 7) $classes .= ' has-data';
                                echo "<button class='$classes'>$day</button>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Characteristics -->
                <div class="demo-section">
                    <h4>Kenmerken</h4>
                    <table class="comparison-table">
                        <tr>
                            <td><strong>Gradient Animatie</strong></td>
                            <td>Diagonal wave + radial shimmer (30s + 8s cycle)</td>
                        </tr>
                        <tr>
                            <td><strong>Zichtbaarheid</strong></td>
                            <td>⭐⭐⭐⭐⚪ - Duidelijk zichtbaar, niet opdringerig</td>
                        </tr>
                        <tr>
                            <td><strong>Knoppen</strong></td>
                            <td>Gradient achtergrond, shine on hover</td>
                        </tr>
                        <tr>
                            <td><strong>Spacing</strong></td>
                            <td>Balanced, moderne verhoudingen</td>
                        </tr>
                        <tr>
                            <td><strong>Best voor</strong></td>
                            <td>Moderne, levendige app met goede balans</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- VARIANT C: ENERGIEK -->
        <div class="variant-container variant-c">
            <div class="variant-header" style="background: linear-gradient(135deg, #EF444415 0%, #EF444408 100%);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="font-size: 28px; font-weight: 700; color: #EF4444; margin-bottom: 4px;">
                            Variant C: Energiek
                        </h2>
                        <p style="font-size: 14px; color: #6B7280;">
                            Voorbeeld: Plus (Rood) • Dynamisch, Levendig, Opvallend
                        </p>
                    </div>
                    <div class="winkel-pill">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                        </svg>
                        Plus
                    </div>
                </div>
            </div>

            <div class="variant-body">
                <!-- Winkel Pills -->
                <div class="demo-section">
                    <h4>Winkelpills met Pulsing Glow + Rotating Border</h4>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <div class="winkel-pill">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            Alles
                        </div>
                        <div class="winkel-pill" style="--accent: #2ECC71;">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            Dapper
                        </div>
                        <div class="winkel-pill" style="--accent: #F59E0B;">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 000-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            Jumbo
                        </div>
                        <div class="winkel-pill" style="--accent: #EF4444;">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            Plus
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="demo-section">
                    <h4>Actieknoppen met Ripple Effect</h4>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <button class="action-btn">Opslaan</button>
                        <button class="action-btn">Exporteer CSV</button>
                        <button class="action-btn">Nieuwe Bon</button>
                        <button class="action-btn">Wachtwoord Wijzigen</button>
                    </div>
                </div>

                <!-- Date Selector -->
                <div class="demo-section">
                    <h4>Scrollbare Dagselector met Pulsing Active State</h4>
                    <div class="card">
                        <div class="date-selector-scroll">
                            <?php
                            foreach ($days as $i => $day) {
                                $classes = 'date-btn';
                                if ($i === 5) $classes .= ' active';
                                if ($i >= 3 && $i <= 7) $classes .= ' has-data';
                                echo "<button class='$classes'>$day</button>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Characteristics -->
                <div class="demo-section">
                    <h4>Kenmerken</h4>
                    <table class="comparison-table">
                        <tr>
                            <td><strong>Gradient Animatie</strong></td>
                            <td>Pulsing glow + rotating gradient border (20s + 8s cycle)</td>
                        </tr>
                        <tr>
                            <td><strong>Zichtbaarheid</strong></td>
                            <td>⭐⭐⭐⭐⭐ - Zeer opvallend en dynamisch</td>
                        </tr>
                        <tr>
                            <td><strong>Knoppen</strong></td>
                            <td>Ripple effect, scale transform, sterke shadows</td>
                        </tr>
                        <tr>
                            <td><strong>Spacing</strong></td>
                            <td>Ruimer, meer ademruimte voor effecten</td>
                        </tr>
                        <tr>
                            <td><strong>Best voor</strong></td>
                            <td>Energieke, speelse app die opvalt</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- COMPARISON TABLE -->
        <div class="variant-container" style="background: white;">
            <div class="variant-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                <h2 style="font-size: 28px; font-weight: 700; color: white; margin-bottom: 4px;">
                    📊 Vergelijkingstabel
                </h2>
                <p style="font-size: 14px; color: rgba(255,255,255,0.9);">
                    Directe vergelijking van alle 3 de varianten
                </p>
            </div>

            <div class="variant-body">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Kenmerk</th>
                            <th>A: Minimalistisch</th>
                            <th>B: Balanced Modern</th>
                            <th>C: Energiek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Gradient Zichtbaarheid</strong></td>
                            <td>⭐⭐⚪⚪⚪ Zeer subtiel</td>
                            <td>⭐⭐⭐⭐⚪ Duidelijk</td>
                            <td>⭐⭐⭐⭐⭐ Zeer opvallend</td>
                        </tr>
                        <tr>
                            <td><strong>Animatie Snelheid</strong></td>
                            <td>25s (traag)</td>
                            <td>30s + 8s (gebalanceerd)</td>
                            <td>20s + 8s (energiek)</td>
                        </tr>
                        <tr>
                            <td><strong>Hover Effecten</strong></td>
                            <td>Minimaal</td>
                            <td>Lift + shine</td>
                            <td>Scale + ripple</td>
                        </tr>
                        <tr>
                            <td><strong>Shadows</strong></td>
                            <td>Soft, subtiel</td>
                            <td>Medium, gebalanceerd</td>
                            <td>Strong, glowing</td>
                        </tr>
                        <tr>
                            <td><strong>Performance Impact</strong></td>
                            <td class="check">✓ Laag</td>
                            <td class="check">✓ Medium</td>
                            <td style="color: #F59E0B;">⚠ Medium-hoog</td>
                        </tr>
                        <tr>
                            <td><strong>Professioneel</strong></td>
                            <td class="check">✓✓✓</td>
                            <td class="check">✓✓</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td><strong>Modern</strong></td>
                            <td class="check">✓✓</td>
                            <td class="check">✓✓✓</td>
                            <td class="check">✓✓✓</td>
                        </tr>
                        <tr>
                            <td><strong>Speels</strong></td>
                            <td>✓</td>
                            <td class="check">✓✓</td>
                            <td class="check">✓✓✓</td>
                        </tr>
                        <tr>
                            <td><strong>Best voor</strong></td>
                            <td>Zakelijk gebruik</td>
                            <td>Algemeen/Alles</td>
                            <td>Retail/Consumer</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RECOMMENDATION -->
        <div class="variant-container" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white;">
            <div class="variant-body" style="text-align: center;">
                <h2 style="font-size: 32px; font-weight: 800; margin-bottom: 16px;">
                    🎯 Aanbeveling
                </h2>
                <p style="font-size: 18px; line-height: 1.6; opacity: 0.95; max-width: 800px; margin: 0 auto 24px;">
                    Voor een <strong>rustig en modern</strong> ontwerp dat goed werkt voor een professionele omgeving
                    maar tóch subtiele animaties heeft, adviseer ik <strong>Variant B: Balanced Modern</strong>.
                </p>
                <p style="font-size: 16px; opacity: 0.9; max-width: 700px; margin: 0 auto;">
                    Deze variant biedt de perfecte balans tussen zichtbare gradients (zoals je wilt) en een
                    rustige, moderne uitstraling. De diagonal wave pattern is WÉL zichtbaar, maar niet te druk.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
