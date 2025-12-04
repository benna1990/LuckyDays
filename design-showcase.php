<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Mock data for demos
$mockPlayer = [
    'id' => 1,
    'name' => 'benna',
    'color' => '#5B8DEE',
    'winkel_naam' => 'Dapper'
];

$mockWinningNumbers = [3, 9, 12, 18, 19, 20, 21, 23, 24, 29, 46, 47, 54, 57, 58, 63, 66, 70, 73, 74];

$mockBon = [
    'id' => 50,
    'bonnummer' => '50',
    'date' => '2024-11-29',
    'player' => 'benna',
    'winkel' => 'Dapper',
    'rijen' => [
        [
            'id' => 1,
            'numbers' => [3, 9, 2, 4, 23, 54],
            'game_type' => '6-getallen',
            'bet' => 5.00,
            'winnings' => 25.00,
            'matches' => 4,
            'multiplier' => 5
        ],
        [
            'id' => 2,
            'numbers' => [12, 18, 20, 29, 47],
            'game_type' => '5-getallen',
            'bet' => 3.00,
            'winnings' => 0.00,
            'matches' => 3,
            'multiplier' => 0
        ],
        [
            'id' => 3,
            'numbers' => [9, 21, 24, 54, 58, 63, 70],
            'game_type' => '7-getallen',
            'bet' => 7.00,
            'winnings' => 5.00,
            'matches' => 5,
            'multiplier' => 0.71
        ]
    ],
    'total_bet' => 15.00,
    'total_winnings' => 30.00,
    'house_result' => -15.00
];

$mockBonnen = [
    ['bon_id' => 50, 'date' => '2024-11-29', 'bonnummer' => '50', 'rijen_count' => 1, 'total_bet' => 5.00, 'total_winnings' => 25.00, 'saldo' => -25.00],
    ['bon_id' => 49, 'date' => '2024-11-28', 'bonnummer' => '49', 'rijen_count' => 3, 'total_bet' => 15.00, 'total_winnings' => 45.00, 'saldo' => -30.00],
    ['bon_id' => 48, 'date' => '2024-11-27', 'bonnummer' => '48', 'rijen_count' => 5, 'total_bet' => 25.00, 'total_winnings' => 0.00, 'saldo' => 25.00],
    ['bon_id' => 47, 'date' => '2024-11-26', 'bonnummer' => '47', 'rijen_count' => 2, 'total_bet' => 10.00, 'total_winnings' => 50.00, 'saldo' => -40.00],
];

// Get winkel (simple query)
$winkelResult = pg_query($conn, "SELECT * FROM winkels LIMIT 1");
$activeWinkel = $winkelResult ? pg_fetch_assoc($winkelResult) : ['naam' => 'Dapper', 'accent' => '#2ECC71'];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LuckyDays Design Showcase</title>
    <link rel="stylesheet" href="assets/css/design-system.css?v=4.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .showcase-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .showcase-header {
            text-align: center;
            color: white;
            margin-bottom: 60px;
        }

        .showcase-header h1 {
            font-size: 48px;
            font-weight: 300;
            margin-bottom: 16px;
            letter-spacing: -1px;
        }

        .showcase-header p {
            font-size: 20px;
            opacity: 0.9;
        }

        .section {
            background: white;
            border-radius: 24px;
            padding: 48px;
            margin-bottom: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }

        .section-header {
            margin-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
        }

        .section-header h2 {
            font-size: 32px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .section-header p {
            font-size: 16px;
            color: #666;
        }

        .variants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }

        .variant-card {
            border: 2px solid #e0e0e0;
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #fafafa;
        }

        .variant-card:hover {
            border-color: #667eea;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
            transform: translateY(-4px);
        }

        .variant-card h3 {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .variant-card .tag {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 16px;
        }

        .variant-card p {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .variant-card ul {
            list-style: none;
            margin-bottom: 20px;
        }

        .variant-card li {
            font-size: 13px;
            color: #555;
            padding: 4px 0;
            padding-left: 20px;
            position: relative;
        }

        .variant-card li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #2ECC71;
            font-weight: bold;
        }

        .btn-preview {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-preview:hover {
            background: #5568d3;
            transform: scale(1.02);
        }

        /* Modal Base */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.2s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Variant 1.1: Premium Glass Card */
        .modal-v1-1 {
            background: white;
            border-radius: 20px;
            max-width: 560px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modal-header-v1-1 {
            padding: 24px 28px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-v1-1 .player-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-header-v1-1 .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #5B8DEE;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: 600;
        }

        .modal-header-v1-1 .meta {
            flex: 1;
        }

        .modal-header-v1-1 .meta h2 {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .modal-header-v1-1 .meta p {
            font-size: 13px;
            color: #999;
        }

        .modal-header-v1-1 .close-btn {
            background: #f5f5f5;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-size: 18px;
            color: #666;
        }

        .modal-header-v1-1 .close-btn:hover {
            background: #e0e0e0;
        }

        .modal-body-v1-1 {
            padding: 24px 28px;
        }

        .winning-numbers-section {
            margin-bottom: 24px;
        }

        .winning-numbers-section h3 {
            font-size: 11px;
            font-weight: 700;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }

        .winning-numbers-box {
            background: linear-gradient(135deg, #FFF5E6 0%, #FFE6CC 100%);
            border: 2px solid #FFD699;
            border-radius: 12px;
            padding: 16px;
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 6px;
        }

        .number-badge {
            background: white;
            border: 1px solid #FFD699;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
            color: #D97706;
        }

        .rijen-section h3 {
            font-size: 11px;
            font-weight: 700;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }

        .rij-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.15s;
        }

        .rij-row:last-child {
            border-bottom: none;
        }

        .rij-row:hover {
            background: #f9f9f9;
        }

        .rij-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }

        .rij-number {
            background: #667eea;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .rij-type {
            background: #e8eaf6;
            color: #667eea;
            padding: 4px 9px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            flex-shrink: 0;
        }

        .rij-numbers {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            flex: 1;
        }

        .rij-number-badge {
            background: white;
            border: 1.5px solid #e0e0e0;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            color: #666;
            transition: all 0.15s;
        }

        .rij-number-badge.match {
            border-color: #2ECC71;
            background: #E8F8F0;
            color: #2ECC71;
        }

        .rij-amounts {
            display: flex;
            gap: 20px;
            font-size: 13px;
            min-width: 160px;
            justify-content: flex-end;
        }

        .rij-amount {
            min-width: 60px;
            text-align: right;
        }

        .rij-amount.bet {
            color: #999;
        }

        .rij-amount.result {
            font-weight: 700;
            min-width: 70px;
        }

        .rij-amount.result.negative {
            color: #EF4444;
        }

        .rij-amount.result.positive {
            color: #2ECC71;
        }

        .summary-section {
            background: #FAFAFA;
            border: 1px solid #e8e8e8;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }

        .summary-section h3 {
            font-size: 11px;
            font-weight: 700;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
            color: #666;
        }

        .summary-row.total {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 2px solid #e0e0e0;
            font-size: 16px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .summary-row .value {
            font-weight: 600;
        }

        .summary-row .value.negative {
            color: #EF4444;
        }

        .summary-row .value.positive {
            color: #2ECC71;
        }

        .summary-note {
            text-align: center;
            font-size: 11px;
            color: #999;
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-footer {
            padding: 20px 28px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 12px;
        }

        .btn-delete {
            flex: 1;
            padding: 12px;
            background: white;
            border: 2px solid #EF4444;
            color: #EF4444;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-delete:hover {
            background: #FEF2F2;
        }

        .btn-done {
            flex: 2;
            padding: 12px;
            background: <?= $activeWinkel['accent'] ?>;
            border: none;
            color: white;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-done:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Variant 1.2: Numbers Grid Focused */
        .modal-v1-2 {
            background: white;
            border-radius: 20px;
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .full-grid-container {
            padding: 24px 28px;
        }

        .full-grid-title {
            font-size: 11px;
            font-weight: 700;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
            text-align: center;
        }

        .numbers-grid-80 {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 6px;
            background: #fafafa;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .grid-cell {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            border-radius: 6px;
            background: white;
            border: 1px solid #e8e8e8;
            color: #ccc;
            transition: all 0.2s;
        }

        .grid-cell.winning {
            background: linear-gradient(135deg, #FFF5E6 0%, #FFE6CC 100%);
            border-color: #FFD699;
            color: #D97706;
            font-weight: 700;
        }

        .grid-cell.player {
            background: #E8F8F0;
            border-color: #2ECC71;
            color: #2ECC71;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(46, 204, 113, 0.2);
        }

        .grid-cell.player.winning {
            background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
            border-color: #10B981;
            color: #059669;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transform: scale(1.1);
        }

        .grid-rijen-simple {
            margin-top: 24px;
        }

        .grid-rijen-simple h4 {
            font-size: 11px;
            font-weight: 700;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }

        .grid-rij-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #fafafa;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .grid-rij-row:hover {
            background: #f0f0f0;
        }

        .grid-rij-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .grid-rij-num {
            background: #667eea;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
        }

        .grid-rij-matches {
            font-size: 12px;
            color: #666;
        }

        .grid-rij-matches .match-count {
            font-weight: 700;
            color: #2ECC71;
        }

        .grid-rij-result {
            display: flex;
            gap: 16px;
            font-size: 13px;
        }

        .grid-summary-box {
            background: #fafafa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 24px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            text-align: center;
        }

        .grid-summary-item .label {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 8px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .grid-summary-item .value {
            font-size: 20px;
            font-weight: 700;
        }

        .grid-summary-item .value.positive {
            color: #2ECC71;
        }

        .grid-summary-item .value.negative {
            color: #EF4444;
        }

        /* Variant 1.3: Focus Grid */
        .modal-v1-3 {
            background: white;
            border-radius: 20px;
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .grid-winning-numbers {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 8px;
            padding: 24px;
        }

        .grid-number {
            aspect-ratio: 1;
            background: #FFF5E6;
            border: 2px solid #FFD699;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            color: #D97706;
        }

        .grid-player-numbers {
            display: flex;
            gap: 10px;
            margin: 16px 0;
        }

        .grid-player-number {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            border: 2px solid #e0e0e0;
            background: #f5f5f5;
            color: #999;
        }

        .grid-player-number.hit {
            border-color: #2ECC71;
            background: #E8F8F0;
            color: #2ECC71;
        }

        .match-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #E8F8F0;
            color: #2ECC71;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            margin-left: 12px;
        }

        .result-card {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
        }

        .result-card-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 15px;
        }

        .house-result-card {
            background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%);
            border: 2px solid #FCA5A5;
            border-radius: 12px;
            padding: 20px;
            margin-top: 16px;
            text-align: center;
        }

        .house-result-card .label {
            font-size: 13px;
            font-weight: 600;
            color: #991B1B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .house-result-card .amount {
            font-size: 32px;
            font-weight: 800;
            color: #DC2626;
        }

        .house-result-card .note {
            font-size: 12px;
            color: #991B1B;
            margin-top: 4px;
        }

        /* Variant 1.4: Minimalist Apple Card */
        .modal-v1-4 {
            background: white;
            border-radius: 24px;
            max-width: 560px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Variant 2.1: Classic Popup (Keyboard-driven) */
        .modal-v2-1 {
            background: white;
            border-radius: 20px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }

        .quick-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .quick-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .quick-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
        }

        .winning-preview {
            background: linear-gradient(135deg, #FFF5E6 0%, #FFE6CC 100%);
            border: 2px solid #FFD699;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 20px;
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
            margin-bottom: 20px;
        }

        .input-section.hidden {
            display: none;
        }

        .input-label {
            font-size: 11px;
            font-weight: 700;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .quick-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .quick-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .quick-input.large {
            font-size: 20px;
            padding: 16px 20px;
            text-align: center;
            font-weight: 600;
        }

        .chips-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            min-height: 40px;
            padding: 12px;
            background: #f9f9f9;
            border-radius: 10px;
            margin-top: 12px;
        }

        .number-chip {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.15s;
        }

        .number-chip:hover {
            background: #5568d3;
        }

        .number-chip .remove-chip {
            opacity: 0.7;
            transition: opacity 0.15s;
        }

        .number-chip .remove-chip:hover {
            opacity: 1;
        }

        .hint-text {
            font-size: 12px;
            color: #999;
            margin-top: 8px;
            font-style: italic;
        }

        .saved-rows {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #f0f0f0;
        }

        .saved-rows-title {
            font-size: 11px;
            font-weight: 700;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .saved-row {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .saved-row-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .row-badge {
            background: #667eea;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
        }

        .row-numbers {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }

        .row-bet {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a1a;
        }

        /* Variant 2.3: Split Screen Pro */
        .modal-v2-3 {
            background: white;
            border-radius: 20px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
        }

        .split-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #f0f0f0;
        }

        .split-right {
            width: 320px;
            display: flex;
            flex-direction: column;
            background: #fafafa;
        }

        .split-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f0f0f0;
        }

        .split-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .split-subtitle {
            font-size: 13px;
            color: #999;
        }

        .split-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
        }

        .preview-panel {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .preview-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
        }

        .preview-stats h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 16px;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-label {
            opacity: 0.8;
            font-size: 13px;
        }

        .stat-value {
            font-weight: 700;
            font-size: 14px;
        }

        .preview-rijen {
            margin-top: 16px;
        }

        .preview-rijen-title {
            font-size: 11px;
            font-weight: 700;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .preview-rij {
            background: white;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            border: 1px solid #e8e8e8;
        }

        .preview-rij-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .preview-rij-nums {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        .preview-num {
            background: #f5f5f5;
            border: 1px solid #e0e0e0;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            color: #666;
        }

        .quick-footer {
            padding: 16px 24px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 12px;
        }

        .btn-quick {
            flex: 1;
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .btn-cancel {
            background: white;
            border: 2px solid #e8e8e8 !important;
            color: #666;
        }

        .btn-cancel:hover {
            background: #f9f9f9;
        }

        .btn-done {
            background: #2ECC71;
            color: white;
        }

        .btn-done:hover {
            background: #27AE60;
        }
        .modal-v2-1 {
            background: white;
            border-radius: 20px;
            max-width: 640px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }

        .wizard-header {
            padding: 24px 28px;
            border-bottom: 1px solid #f0f0f0;
        }

        .wizard-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 16px;
        }

        .wizard-steps {
            display: flex;
            gap: 8px;
        }

        .wizard-step {
            flex: 1;
            height: 4px;
            background: #f0f0f0;
            border-radius: 2px;
            transition: all 0.3s;
        }

        .wizard-step.active {
            background: #667eea;
        }

        .wizard-step.completed {
            background: #2ECC71;
        }

        .wizard-body {
            flex: 1;
            overflow-y: auto;
            padding: 32px 28px;
        }

        .wizard-step-content {
            display: none;
        }

        .wizard-step-content.active {
            display: block;
            animation: fadeInStep 0.3s ease;
        }

        @keyframes fadeInStep {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .step-title {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .step-subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #666;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .player-suggestions {
            background: white;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
            margin-top: 8px;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }

        .player-suggestions.active {
            display: block;
        }

        .player-suggestion {
            padding: 12px 16px;
            cursor: pointer;
            transition: background 0.15s;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .player-suggestion:hover {
            background: #f9f9f9;
        }

        .player-suggestion .avatar-small {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            font-weight: 600;
        }

        .number-grid {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 8px;
            margin-bottom: 24px;
        }

        .number-cell {
            aspect-ratio: 1;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            background: white;
            color: #666;
        }

        .number-cell:hover {
            border-color: #667eea;
            background: #f5f7ff;
        }

        .number-cell.selected {
            background: #667eea;
            border-color: #667eea;
            color: white;
            transform: scale(0.95);
        }

        .selected-numbers {
            background: #f9f9f9;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .selected-numbers-title {
            font-size: 12px;
            font-weight: 600;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .selected-numbers-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            min-height: 40px;
        }

        .selected-number-badge {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .selected-number-badge .remove {
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.15s;
        }

        .selected-number-badge .remove:hover {
            opacity: 1;
        }

        .rij-preview {
            background: #f9f9f9;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
        }

        .rij-preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .rij-preview-numbers {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .preview-number {
            background: white;
            border: 1px solid #ddd;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            color: #666;
        }

        .btn-add-rij {
            width: 100%;
            padding: 12px;
            background: white;
            border: 2px dashed #ddd;
            border-radius: 10px;
            color: #666;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-add-rij:hover {
            border-color: #667eea;
            color: #667eea;
            background: #f5f7ff;
        }

        .wizard-footer {
            padding: 20px 28px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 12px;
        }

        .btn-wizard {
            flex: 1;
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-wizard-back {
            background: white;
            border: 2px solid #e8e8e8;
            color: #666;
        }

        .btn-wizard-back:hover {
            background: #f9f9f9;
        }

        .btn-wizard-next {
            background: #667eea;
            border: none;
            color: white;
        }

        .btn-wizard-next:hover {
            background: #5568d3;
        }

        .btn-wizard-next:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .summary-card h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
            opacity: 0.9;
        }

        .summary-stat {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .summary-stat:last-child {
            border-bottom: none;
        }

        .summary-stat .label {
            opacity: 0.8;
        }

        .summary-stat .value {
            font-weight: 700;
        }

        .apple-header {
            padding: 60px 48px 40px;
            text-align: center;
            position: relative;
        }

        .apple-header .close-btn {
            position: absolute;
            top: 24px;
            right: 24px;
        }

        .apple-header h2 {
            font-size: 28px;
            font-weight: 300;
            color: #1a1a1a;
            margin-bottom: 12px;
        }

        .apple-header p {
            font-size: 15px;
            color: #999;
        }

        .apple-section {
            padding: 48px;
            text-align: center;
            border-top: 1px solid #f5f5f5;
        }

        .apple-section-title {
            font-size: 13px;
            font-weight: 600;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 24px;
        }

        .apple-numbers {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .apple-number {
            font-size: 16px;
            font-weight: 600;
            color: #D97706;
        }

        .apple-divider {
            width: 60%;
            height: 1px;
            background: #f0f0f0;
            margin: 32px auto;
        }

        .apple-rij-info {
            font-size: 15px;
            color: #666;
            line-height: 2;
        }

        .apple-rij-numbers {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
            margin: 16px 0;
        }

        .apple-summary {
            margin-top: 32px;
        }

        .apple-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            font-size: 16px;
        }

        .apple-summary-row .label {
            color: #999;
        }

        .apple-summary-row .value {
            font-weight: 600;
            color: #1a1a1a;
        }

        .apple-summary-row .value.negative {
            color: #DC2626;
            font-size: 20px;
            font-weight: 700;
        }

        .apple-note {
            font-size: 13px;
            color: #ccc;
            margin-top: 8px;
        }

        .apple-footer {
            padding: 32px 48px;
            display: flex;
            gap: 24px;
            border-top: 1px solid #f5f5f5;
        }

        .apple-footer button {
            flex: 1;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .apple-btn-delete {
            background: white;
            color: #DC2626;
        }

        .apple-btn-delete:hover {
            background: #FEF2F2;
        }

        .apple-btn-done {
            background: #1a1a1a;
            color: white;
        }

        .apple-btn-done:hover {
            background: #333;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .showcase-container {
                padding: 20px;
            }

            .section {
                padding: 24px;
            }

            .variants-grid {
                grid-template-columns: 1fr;
            }

            .grid-winning-numbers {
                grid-template-columns: repeat(5, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="showcase-container">
        <div class="showcase-header">
            <h1>🎨 LuckyDays Design Showcase</h1>
            <p>Ontdek en vergelijk alle popup varianten</p>
        </div>

        <!-- Section 1: Dashboard Bon-Popup -->
        <div class="section">
            <div class="section-header">
                <h2>1️⃣ Dashboard Bon-Popup (View Mode)</h2>
                <p>Voor het bekijken en bewerken van bestaande bonnen op het dashboard</p>
            </div>

            <div class="variants-grid">
                <!-- Variant 1.1 -->
                <div class="variant-card" onclick="openModal('v1-1')">
                    <h3>Compact List (✓ Jouw voorkeur)</h3>
                    <span class="tag">Clean · Efficient</span>
                    <p>Rijen als eenvoudige lijst onder elkaar, vaste kolommen voor bedragen.</p>
                    <ul>
                        <li>10-grid winnende nummers</li>
                        <li>Game-type badges zichtbaar</li>
                        <li>Vaste kolommen (60px + 70px)</li>
                        <li>Maximale ruimte-efficiëntie</li>
                        <li>Perfect voor meerdere rijen</li>
                    </ul>
                    <button class="btn-preview">Bekijk Preview</button>
                </div>

                <!-- Variant 1.2 -->
                <div class="variant-card" onclick="openModal('v1-2')">
                    <h3>Numbers Grid 1-80</h3>
                    <span class="tag">Visual · Interactive</span>
                    <p>Volledige 10×8 grid met alle nummers 1-80, winning + player numbers highlighted.</p>
                    <ul>
                        <li>Complete nummergrid visueel</li>
                        <li>Matches met glow effect</li>
                        <li>Lotto-kaart nostalgisch</li>
                        <li>Educatief & engaging</li>
                    </ul>
                    <button class="btn-preview">Bekijk Preview</button>
                </div>

                <!-- Variant 1.3 -->
                <div class="variant-card" onclick="openModal('v1-3')">
                    <h3>Split Dashboard</h3>
                    <span class="tag">Professional · 2-Column</span>
                    <p>Links content, rechts sticky summary panel met grote cijfers.</p>
                    <ul>
                        <li>2-koloms layout (60/40)</li>
                        <li>Sticky summary rechts</li>
                        <li>Dashboard gevoel</li>
                        <li>Desktop-optimized</li>
                    </ul>
                    <button class="btn-preview">Bekijk Preview</button>
                </div>

                <!-- Variant 1.4 -->
                <div class="variant-card" onclick="openModal('v1-4')">
                    <h3>Minimal Zen</h3>
                    <span class="tag">Apple · Serene</span>
                    <p>Ultra minimalistisch, gecentreerd, enorme witruimte.</p>
                    <ul>
                        <li>Maximale witruimte</li>
                        <li>Centered compositie</li>
                        <li>Apple Card inspiratie</li>
                        <li>Rustige ervaring</li>
                    </ul>
                    <button class="btn-preview">Bekijk Preview</button>
                </div>
            </div>
        </div>

        <!-- Section 2: Bon Toevoegen Form -->
        <div class="section">
            <div class="section-header">
                <h2>2️⃣ Bon Toevoegen (Create Mode)</h2>
                <p>Voor het invoeren van nieuwe bonnen met speler, nummers en inzet</p>
            </div>

            <div class="variants-grid">
                <!-- Variant 2.1 -->
                <div class="variant-card" onclick="openModal('v2-1')">
                    <h3>Classic Popup</h3>
                    <span class="tag">Keyboard · Fast</span>
                    <p>Verbeterde versie van huidige popup. Type nummers → 0 → inzet → repeat.</p>
                    <ul>
                        <li>Speler + bonnummer eerste</li>
                        <li>Winnende nummers zichtbaar</li>
                        <li>Type 0 = volgende stap</li>
                        <li>Recent sets hergebruiken</li>
                        <li>Keyboard-only workflow</li>
                    </ul>
                    <button class="btn-preview">Bekijk Preview</button>
                </div>

                <!-- Variant 2.2 -->
                <div class="variant-card" onclick="openModal('v2-2')">
                    <h3>Inline Compact</h3>
                    <span class="tag">Fast · Minimal</span>
                    <p>Compacte inline form, perfect voor snelle batch invoer.</p>
                    <ul>
                        <li>Alles in 1 scherm</li>
                        <li>Multi-rij preview live</li>
                        <li>Zelfde 0-trigger logica</li>
                        <li>Compact design</li>
                    </ul>
                    <button class="btn-preview">Bekijk Preview</button>
                </div>

                <!-- Variant 2.3 -->
                <div class="variant-card" onclick="openModal('v2-3')">
                    <h3>Split Screen Pro</h3>
                    <span class="tag">Professional · Desktop</span>
                    <p>Links invoer, rechts live preview met alle rijen en totalen.</p>
                    <ul>
                        <li>2-koloms layout</li>
                        <li>Sticky preview rechts</li>
                        <li>Keyboard shortcuts</li>
                        <li>Pro user workflow</li>
                    </ul>
                    <button class="btn-preview">Bekijk Preview</button>
                </div>

                <!-- Variant 2.4 -->
                <div class="variant-card" onclick="openModal('v2-4')">
                    <h3>Focus Mode</h3>
                    <span class="tag">Zen · Minimal</span>
                    <p>Ultra minimalistisch, alleen wat nodig is, maximum focus.</p>
                    <ul>
                        <li>Distraction-free</li>
                        <li>Grote input field</li>
                        <li>Subtiele hints</li>
                        <li>Flow state optimized</li>
                    </ul>
                    <button class="btn-preview">Bekijk Preview</button>
                </div>
            </div>
        </div>

        <!-- Section 3: Week Overzicht -->
        <div class="section">
            <div class="section-header">
                <h2>3️⃣ Week Bonnen Overzicht</h2>
                <p>Voor het bekijken van alle bonnen in een geselecteerde week</p>
            </div>

            <div class="variants-grid">
                <!-- Variant 3.1 -->
                <div class="variant-card" onclick="openModal('v3-1')">
                    <h3>List View</h3>
                    <span class="tag">Clean · Scannable</span>
                    <p>Lijst met bonnen, click voor details, mini number preview.</p>
                    <ul>
                        <li>Week header met datum</li>
                        <li>Compacte lijst per bon</li>
                        <li>Mini nummer preview</li>
                        <li>Click → detail popup (1.1)</li>
                    </ul>
                    <button class="btn-preview">Bekijk Preview</button>
                </div>

                <!-- Variant 3.2 -->
                <div class="variant-card" onclick="openModal('v3-2')">
                    <h3>Card Grid</h3>
                    <span class="tag">Visual · Cards</span>
                    <p>Grid van bon cards met speler avatar en resultaat.</p>
                    <ul>
                        <li>2-3 koloms grid</li>
                        <li>Card per bon</li>
                        <li>Visual player avatars</li>
                        <li>Hover preview</li>
                    </ul>
                    <button class="btn-preview">Bekijk Preview</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Variant 1.1: Premium Glass Card -->
    <div id="modal-v1-1" class="modal-overlay" onclick="if(event.target === this) closeModal('v1-1')">
        <div class="modal-v1-1">
            <div class="modal-header-v1-1">
                <div class="player-info">
                    <div class="avatar">B</div>
                    <div class="meta">
                        <h2><?= $mockPlayer['name'] ?></h2>
                        <p><?= $mockPlayer['winkel_naam'] ?> · Bon #<?= $mockBon['bonnummer'] ?> · <?= date('d M Y', strtotime($mockBon['date'])) ?></p>
                    </div>
                </div>
                <button class="close-btn" onclick="closeModal('v1-1')">✕</button>
            </div>

            <div class="modal-body-v1-1">
                <div class="winning-numbers-section">
                    <h3>Winnende nummers</h3>
                    <div class="winning-numbers-box">
                        <?php foreach ($mockWinningNumbers as $num): ?>
                            <div class="number-badge"><?= $num ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="rijen-section">
                    <h3>Rijen (<?= count($mockBon['rijen']) ?>/10)</h3>
                    <?php foreach ($mockBon['rijen'] as $index => $rij): ?>
                        <div class="rij-row">
                            <div class="rij-left">
                                <div class="rij-number"><?= $index + 1 ?></div>
                                <div class="rij-type"><?= $rij['game_type'] ?></div>
                                <div class="rij-numbers">
                                    <?php foreach ($rij['numbers'] as $num): 
                                        $isMatch = in_array($num, $mockWinningNumbers);
                                    ?>
                                        <div class="rij-number-badge <?= $isMatch ? 'match' : '' ?>">
                                            <?= $num ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="rij-amounts">
                                <span class="rij-amount bet">€<?= number_format($rij['bet'], 2, ',', '.') ?></span>
                                <span class="rij-amount result <?= ($rij['winnings'] - $rij['bet']) >= 0 ? 'positive' : 'negative' ?>">
                                    <?= ($rij['winnings'] - $rij['bet']) >= 0 ? '+' : '' ?>€<?= number_format($rij['winnings'] - $rij['bet'], 2, ',', '.') ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-section">
                    <h3>Samenvatting</h3>
                    <div class="summary-row">
                        <span>Inzet ontvangen</span>
                        <span class="value positive">+€<?= number_format($mockBon['total_bet'], 2, ',', '.') ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Uitbetaald</span>
                        <span class="value negative">−€<?= number_format($mockBon['total_winnings'], 2, ',', '.') ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Resultaat (Het Huis)</span>
                        <span class="value <?= $mockBon['house_result'] >= 0 ? 'positive' : 'negative' ?>">
                            <?= $mockBon['house_result'] >= 0 ? '+' : '' ?>€<?= number_format($mockBon['house_result'], 2, ',', '.') ?>
                        </span>
                    </div>
                    <div class="summary-note">
                        <?= $mockBon['house_result'] < 0 ? 'Te betalen' : 'Ontvangen' ?>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn-delete" onclick="alert('Verwijderen functionaliteit')">
                    🗑 Verwijderen
                </button>
                <button class="btn-done" onclick="closeModal('v1-1')">
                    Gereed
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Variant 1.2: Numbers Grid Focused -->
    <div id="modal-v1-2" class="modal-overlay" onclick="if(event.target === this) closeModal('v1-2')">
        <div class="modal-v1-2">
            <div class="modal-header-v1-1">
                <div class="player-info">
                    <div class="avatar">B</div>
                    <div class="meta">
                        <h2><?= $mockPlayer['name'] ?></h2>
                        <p><?= $mockPlayer['winkel_naam'] ?> · Bon #<?= $mockBon['bonnummer'] ?> · <?= date('d M Y', strtotime($mockBon['date'])) ?></p>
                    </div>
                </div>
                <button class="close-btn" onclick="closeModal('v1-2')">✕</button>
            </div>

            <div class="full-grid-container">
                <div class="full-grid-title">Volledige Nummer Grid (1-80)</div>
                
                <div class="numbers-grid-80">
                    <?php 
                    // Get all player numbers
                    $allPlayerNumbers = [];
                    foreach ($mockBon['rijen'] as $rij) {
                        $allPlayerNumbers = array_merge($allPlayerNumbers, $rij['numbers']);
                    }
                    $allPlayerNumbers = array_unique($allPlayerNumbers);
                    
                    for ($i = 1; $i <= 80; $i++): 
                        $isWinning = in_array($i, $mockWinningNumbers);
                        $isPlayer = in_array($i, $allPlayerNumbers);
                        $class = '';
                        if ($isPlayer && $isWinning) $class = 'player winning';
                        elseif ($isPlayer) $class = 'player';
                        elseif ($isWinning) $class = 'winning';
                    ?>
                        <div class="grid-cell <?= $class ?>"><?= $i ?></div>
                    <?php endfor; ?>
                </div>

                <div class="grid-rijen-simple">
                    <h4>Jouw Rijen (<?= count($mockBon['rijen']) ?>)</h4>
                    <?php foreach ($mockBon['rijen'] as $index => $rij): 
                        $result = $rij['winnings'] - $rij['bet'];
                    ?>
                        <div class="grid-rij-row">
                            <div class="grid-rij-info">
                                <div class="grid-rij-num"><?= $index + 1 ?></div>
                                <div class="grid-rij-matches">
                                    <span class="match-count"><?= $rij['matches'] ?></span> matches · <?= $rij['game_type'] ?>
                                </div>
                            </div>
                            <div class="grid-rij-result">
                                <span style="color: #999;">€<?= number_format($rij['bet'], 2, ',', '.') ?></span>
                                <span style="font-weight: 700; color: <?= $result >= 0 ? '#2ECC71' : '#EF4444' ?>">
                                    <?= $result >= 0 ? '+' : '' ?>€<?= number_format($result, 2, ',', '.') ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="grid-summary-box">
                    <div class="grid-summary-item">
                        <div class="label">Inzet</div>
                        <div class="value">€<?= number_format($mockBon['total_bet'], 0, ',', '.') ?></div>
                    </div>
                    <div class="grid-summary-item">
                        <div class="label">Winst</div>
                        <div class="value">€<?= number_format($mockBon['total_winnings'], 0, ',', '.') ?></div>
                    </div>
                    <div class="grid-summary-item">
                        <div class="label">Huis</div>
                        <div class="value <?= $mockBon['house_result'] >= 0 ? 'positive' : 'negative' ?>">
                            <?= $mockBon['house_result'] >= 0 ? '+' : '' ?>€<?= number_format($mockBon['house_result'], 0, ',', '.') ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn-delete" onclick="alert('Verwijderen')">🗑 Verwijderen</button>
                <button class="btn-done" onclick="closeModal('v1-2')">Gereed</button>
            </div>
        </div>
    </div>

    <!-- Modal Variant 1.3: Focus Grid -->
    <div id="modal-v1-3" class="modal-overlay" onclick="if(event.target === this) closeModal('v1-3')">
        <div class="modal-v1-3">
            <div class="modal-header-v1-1">
                <div class="player-info">
                    <div class="avatar">B</div>
                    <div class="meta">
                        <h2><?= $mockPlayer['name'] ?></h2>
                        <p><?= $mockPlayer['winkel_naam'] ?> · Bon #<?= $mockBon['bonnummer'] ?> · <?= date('d M Y', strtotime($mockBon['date'])) ?></p>
                    </div>
                </div>
                <button class="close-btn" onclick="closeModal('v1-3')">✕</button>
            </div>

            <div class="modal-body-v1-1">
                <div class="winning-numbers-section">
                    <h3>Winnende nummers (<?= count($mockWinningNumbers) ?>)</h3>
                    <div class="grid-winning-numbers">
                        <?php foreach ($mockWinningNumbers as $num): ?>
                            <div class="grid-number"><?= $num ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="rijen-section">
                    <h3>Rij #1 · <?= $mockBon['rijen'][0]['game_type'] ?></h3>
                    
                    <p style="margin-bottom: 12px; color: #666; font-size: 14px;">Jouw nummers</p>
                    
                    <div style="display: flex; align-items: center;">
                        <div class="grid-player-numbers">
                            <?php foreach ($mockBon['rijen'][0]['numbers'] as $num): 
                                $isMatch = in_array($num, $mockWinningNumbers);
                            ?>
                                <div class="grid-player-number <?= $isMatch ? 'hit' : '' ?>">
                                    <?= $num ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <span class="match-indicator">
                            ✓ <?= $mockBon['rijen'][0]['matches'] ?> matches
                        </span>
                    </div>

                    <p style="margin-top: 12px; font-size: 13px; color: #999;">
                        🟢 groen = hit · grijs = mis
                    </p>

                    <div class="result-card">
                        <div class="result-card-row">
                            <span>Inzet</span>
                            <strong>€<?= number_format($mockBon['rijen'][0]['bet'], 2, ',', '.') ?></strong>
                        </div>
                        <div class="result-card-row">
                            <span>Winst</span>
                            <strong>€<?= number_format($mockBon['rijen'][0]['winnings'], 2, ',', '.') ?></strong>
                        </div>
                        <div class="result-card-row" style="padding-top: 12px; border-top: 1px solid #e0e0e0; margin-top: 8px;">
                            <span style="font-weight: 600;">Resultaat</span>
                            <strong style="color: <?= ($mockBon['rijen'][0]['winnings'] - $mockBon['rijen'][0]['bet']) >= 0 ? '#2ECC71' : '#EF4444' ?>">
                                <?= ($mockBon['rijen'][0]['winnings'] - $mockBon['rijen'][0]['bet']) >= 0 ? '+' : '' ?>€<?= number_format($mockBon['rijen'][0]['winnings'] - $mockBon['rijen'][0]['bet'], 2, ',', '.') ?>
                            </strong>
                        </div>
                    </div>

                    <div class="house-result-card">
                        <div class="label">Huis Resultaat</div>
                        <div class="amount"><?= $mockBon['house_result'] >= 0 ? '+' : '' ?>€<?= number_format(abs($mockBon['house_result']), 2, ',', '.') ?></div>
                        <div class="note"><?= $mockBon['house_result'] < 0 ? 'te betalen' : 'ontvangen' ?></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn-delete" onclick="alert('Verwijderen functionaliteit')">
                    🗑 Verwijderen
                </button>
                <button class="btn-done" onclick="closeModal('v1-3')">
                    Gereed
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Variant 1.4: Minimalist Apple Card -->
    <div id="modal-v1-4" class="modal-overlay" onclick="if(event.target === this) closeModal('v1-4')">
        <div class="modal-v1-4">
            <div class="apple-header">
                <button class="close-btn" onclick="closeModal('v1-4')" style="background: #f5f5f5; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer;">✕</button>
                <h2>Bon #<?= $mockBon['bonnummer'] ?></h2>
                <p><?= $mockPlayer['name'] ?> · <?= $mockPlayer['winkel_naam'] ?></p>
                <p><?= date('d F Y', strtotime($mockBon['date'])) ?></p>
            </div>

            <div class="apple-section">
                <div class="apple-section-title">Winnende nummers</div>
                <div class="apple-numbers">
                    <?php 
                    $firstRow = array_slice($mockWinningNumbers, 0, 10);
                    $secondRow = array_slice($mockWinningNumbers, 10);
                    ?>
                    <div style="width: 100%; text-align: center;">
                        <?php foreach ($firstRow as $num): ?>
                            <span class="apple-number"><?= $num ?></span>
                            <?php if ($num !== end($firstRow)): ?>&nbsp;&nbsp;<?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <div style="width: 100%; text-align: center;">
                        <?php foreach ($secondRow as $num): ?>
                            <span class="apple-number"><?= $num ?></span>
                            <?php if ($num !== end($secondRow)): ?>&nbsp;&nbsp;<?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="apple-section">
                <div class="apple-section-title">Rij 1 van <?= count($mockBon['rijen']) ?></div>
                <div class="apple-rij-numbers">
                    <?= implode(' · ', $mockBon['rijen'][0]['numbers']) ?>
                </div>
                <div class="apple-rij-info">
                    <?= $mockBon['rijen'][0]['game_type'] ?><br>
                    <?= $mockBon['rijen'][0]['matches'] ?> matches
                </div>
            </div>

            <div class="apple-section">
                <div class="apple-summary">
                    <div class="apple-summary-row">
                        <span class="label">Inzet</span>
                        <span class="value">€<?= number_format($mockBon['total_bet'], 2, ',', '.') ?></span>
                    </div>
                    <div class="apple-summary-row">
                        <span class="label">Winst</span>
                        <span class="value">€<?= number_format($mockBon['total_winnings'], 2, ',', '.') ?></span>
                    </div>
                    <div class="apple-divider"></div>
                    <div class="apple-summary-row">
                        <span class="label">Resultaat</span>
                        <span class="value negative">
                            <?= $mockBon['house_result'] >= 0 ? '+' : '' ?>€<?= number_format($mockBon['house_result'], 2, ',', '.') ?>
                        </span>
                    </div>
                    <div class="apple-note"><?= $mockBon['house_result'] < 0 ? 'te betalen' : 'ontvangen' ?></div>
                </div>
            </div>

            <div class="apple-footer">
                <button class="apple-btn-delete" onclick="alert('Verwijderen')">
                    Verwijderen
                </button>
                <button class="apple-btn-done" onclick="closeModal('v1-4')">
                    Gereed
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Variant 2.1: Classic Popup (Keyboard-driven) -->
    <div id="modal-v2-1" class="modal-overlay" onclick="if(event.target === this) closeModal('v2-1')">
        <div class="modal-v2-1">
            <div class="quick-header">
                <h2>Nieuwe Bon Toevoegen</h2>
                <button class="close-btn" onclick="closeModal('v2-1')" style="background: #f5f5f5; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer;">✕</button>
            </div>

            <div class="quick-body">
                <!-- Winning Numbers Preview -->
                <div class="winning-preview">
                    <div class="winning-preview-title">Winnende Nummers (<?= date('d-m-Y', strtotime($mockBon['date'])) ?>)</div>
                    <div class="winning-preview-grid">
                        <?php foreach ($mockWinningNumbers as $num): ?>
                            <div class="winning-num"><?= $num ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Player Name -->
                <div class="input-section" id="quick-player-section">
                    <div class="input-label">Speler Naam</div>
                    <input type="text" 
                           id="quick-player" 
                           class="quick-input" 
                           placeholder="Typ spelersnaam..."
                           autocomplete="off">
                    <div class="hint-text">Druk Enter om door te gaan</div>
                </div>

                <!-- Bon Number -->
                <div class="input-section hidden" id="quick-bonnr-section">
                    <div class="input-label">Bonnummer</div>
                    <input type="text" 
                           id="quick-bonnr" 
                           class="quick-input" 
                           placeholder="Typ bonnummer..."
                           autocomplete="off">
                    <div class="hint-text">Druk Enter om door te gaan</div>
                </div>

                <!-- Number Input -->
                <div class="input-section hidden" id="quick-numbers-section">
                    <div class="input-label">Rij <span id="quick-row-num">1</span> - Type Nummers</div>
                    <input type="number" 
                           id="quick-number-input" 
                           class="quick-input large" 
                           placeholder="Type nummer..."
                           min="0"
                           max="80"
                           autocomplete="off">
                    <div class="hint-text">Type 0 om naar inzet te gaan • Max 7 nummers</div>
                    
                    <div class="chips-container" id="quick-chips">
                        <span style="color: #999; font-size: 13px;">Nummers verschijnen hier...</span>
                    </div>
                </div>

                <!-- Bet Input -->
                <div class="input-section hidden" id="quick-bet-section">
                    <div class="input-label">Inzet (Euro)</div>
                    <input type="number" 
                           id="quick-bet-input" 
                           class="quick-input large" 
                           placeholder="5.00"
                           step="0.50"
                           min="0.50"
                           value="5.00"
                           autocomplete="off">
                    <div class="hint-text">Druk Enter om rij op te slaan</div>
                </div>

                <!-- Saved Rows -->
                <div class="saved-rows" id="quick-saved-rows" style="display: none;">
                    <div class="saved-rows-title">Opgeslagen Rijen (<span id="quick-saved-count">0</span>)</div>
                    <div id="quick-saved-list"></div>
                </div>
            </div>

            <div class="quick-footer">
                <button class="btn-quick btn-cancel" onclick="closeModal('v2-1')">
                    Annuleren
                </button>
                <button class="btn-quick btn-done" onclick="quickFinish()" id="quick-finish-btn" style="display: none;">
                    ✓ Klaar (<span id="quick-total-count">0</span> rijen)
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Variant 2.3: Split Screen Pro -->
    <div id="modal-v2-3" class="modal-overlay" onclick="if(event.target === this) closeModal('v2-3')">
        <div class="modal-v2-3">
            <!-- Left Side: Input -->
            <div class="split-left">
                <div class="split-header">
                    <h2>Nieuwe Bon</h2>
                    <div class="split-subtitle">Winkel: Dapper</div>
                </div>

                <div class="split-body">
                    <!-- Winning Numbers -->
                    <div class="winning-preview">
                        <div class="winning-preview-title">Winnende Nummers</div>
                        <div class="winning-preview-grid">
                            <?php foreach ($mockWinningNumbers as $num): ?>
                                <div class="winning-num"><?= $num ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Player Name -->
                    <div class="input-section" id="split-player-section">
                        <div class="input-label">Speler</div>
                        <input type="text" id="split-player" class="quick-input" placeholder="Naam...">
                    </div>

                    <!-- Bon Number -->
                    <div class="input-section hidden" id="split-bonnr-section">
                        <div class="input-label">Bonnummer</div>
                        <input type="text" id="split-bonnr" class="quick-input" placeholder="Nummer...">
                    </div>

                    <!-- Number Input -->
                    <div class="input-section hidden" id="split-numbers-section">
                        <div class="input-label">Rij <span id="split-row-num">1</span></div>
                        <input type="number" id="split-number-input" class="quick-input large" placeholder="Type nummer..." min="0" max="80">
                        <div class="hint-text">Type 0 → inzet • Max 7 nummers</div>
                        <div class="chips-container" id="split-chips">
                            <span style="color: #999; font-size: 13px;">Nummers...</span>
                        </div>
                    </div>

                    <!-- Bet Input -->
                    <div class="input-section hidden" id="split-bet-section">
                        <div class="input-label">Inzet</div>
                        <input type="number" id="split-bet-input" class="quick-input large" value="5.00" step="0.50" min="0.50">
                        <div class="hint-text">Enter = opslaan</div>
                    </div>
                </div>

                <div class="quick-footer">
                    <button class="btn-quick btn-cancel" onclick="closeModal('v2-3')">Annuleren</button>
                    <button class="btn-quick btn-done" onclick="splitFinish()" id="split-finish-btn" style="display: none;">
                        ✓ Klaar
                    </button>
                </div>
            </div>

            <!-- Right Side: Preview -->
            <div class="split-right">
                <div class="split-header">
                    <h2>Preview</h2>
                </div>

                <div class="preview-panel">
                    <div class="preview-stats">
                        <h3>📊 Statistieken</h3>
                        <div class="stat-row">
                            <span class="stat-label">Speler</span>
                            <span class="stat-value" id="split-preview-player">-</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Bonnummer</span>
                            <span class="stat-value" id="split-preview-bonnr">-</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Rijen</span>
                            <span class="stat-value" id="split-preview-count">0</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Totaal</span>
                            <span class="stat-value" id="split-preview-total">€0,00</span>
                        </div>
                    </div>

                    <div class="preview-rijen">
                        <div class="preview-rijen-title">Rijen</div>
                        <div id="split-preview-list">
                            <div style="text-align: center; color: #999; font-size: 13px; padding: 20px;">
                                Nog geen rijen toegevoegd
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Wizard State
        let wizardCurrentStep = 1;
        let wizardData = {
            player: '',
            winkel: '',
            rijen: [],
            selectedNumbers: []
        };

        function openModal(variant) {
            document.getElementById('modal-' + variant).classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Initialize wizard if opening v2-1
            if (variant === 'v2-1') {
                initializeWizard();
            }
        }

        function closeModal(variant) {
            document.getElementById('modal-' + variant).classList.remove('active');
            document.body.style.overflow = '';
        }

        function initializeWizard() {
            wizardCurrentStep = 1;
            wizardData = {
                player: '',
                winkel: '',
                rijen: [],
                selectedNumbers: []
            };
            
            // Reset UI
            updateWizardStep(1);
            document.getElementById('wizard-player-input').value = '';
            document.getElementById('wizard-winkel').value = '';
            document.getElementById('wizard-bet').value = '5.00';
            
            // Generate number grid
            const grid = document.getElementById('wizard-number-grid');
            grid.innerHTML = '';
            for (let i = 1; i <= 80; i++) {
                const cell = document.createElement('div');
                cell.className = 'number-cell';
                cell.textContent = i;
                cell.onclick = () => toggleNumber(i);
                grid.appendChild(cell);
            }
            
            updateSelectedNumbersDisplay();
            updateRijenList();
        }

        function updateWizardStep(step) {
            wizardCurrentStep = step;
            
            // Update step indicators
            document.querySelectorAll('.wizard-step').forEach((el, i) => {
                el.classList.remove('active', 'completed');
                if (i + 1 < step) el.classList.add('completed');
                if (i + 1 === step) el.classList.add('active');
            });
            
            // Update content visibility
            document.querySelectorAll('.wizard-step-content').forEach(el => {
                el.classList.remove('active');
            });
            document.querySelector(`.wizard-step-content[data-step="${step}"]`).classList.add('active');
            
            // Update buttons
            const btnBack = document.getElementById('wizard-btn-back');
            const btnNext = document.getElementById('wizard-btn-next');
            
            if (step === 1) {
                btnBack.style.display = 'none';
                btnNext.textContent = 'Volgende →';
            } else if (step === 2) {
                btnBack.style.display = 'block';
                btnNext.textContent = 'Volgende →';
            } else if (step === 3) {
                btnBack.style.display = 'block';
                btnNext.textContent = '✓ Opslaan';
                updateSummary();
            }
        }

        function wizardNextStep() {
            if (wizardCurrentStep === 1) {
                // Validate step 1
                const player = document.getElementById('wizard-player-input').value.trim();
                const winkel = document.getElementById('wizard-winkel').value;
                
                if (!player) {
                    alert('Vul een spelersnaam in');
                    return;
                }
                if (!winkel) {
                    alert('Selecteer een winkel');
                    return;
                }
                
                wizardData.player = player;
                wizardData.winkel = winkel;
                updateWizardStep(2);
                
            } else if (wizardCurrentStep === 2) {
                // Validate step 2
                if (wizardData.rijen.length === 0) {
                    alert('Voeg minimaal 1 rij toe');
                    return;
                }
                
                updateWizardStep(3);
                
            } else if (wizardCurrentStep === 3) {
                // Save
                alert('Bon opgeslagen! (In productie wordt dit naar de database gestuurd)');
                closeModal('v2-1');
            }
        }

        function wizardPrevStep() {
            if (wizardCurrentStep > 1) {
                updateWizardStep(wizardCurrentStep - 1);
            }
        }

        function toggleNumber(num) {
            const index = wizardData.selectedNumbers.indexOf(num);
            if (index > -1) {
                wizardData.selectedNumbers.splice(index, 1);
            } else {
                if (wizardData.selectedNumbers.length >= 7) {
                    alert('Maximaal 7 nummers per rij');
                    return;
                }
                wizardData.selectedNumbers.push(num);
            }
            
            wizardData.selectedNumbers.sort((a, b) => a - b);
            updateSelectedNumbersDisplay();
            updateNumberGrid();
        }

        function updateNumberGrid() {
            const cells = document.querySelectorAll('.number-cell');
            cells.forEach((cell, i) => {
                const num = i + 1;
                if (wizardData.selectedNumbers.includes(num)) {
                    cell.classList.add('selected');
                } else {
                    cell.classList.remove('selected');
                }
            });
        }

        function updateSelectedNumbersDisplay() {
            const count = document.getElementById('wizard-selected-count');
            const list = document.getElementById('wizard-selected-list');
            
            count.textContent = wizardData.selectedNumbers.length;
            
            if (wizardData.selectedNumbers.length === 0) {
                list.innerHTML = '<span style="color: #999; font-size: 13px;">Klik nummers hieronder om te selecteren</span>';
            } else {
                list.innerHTML = wizardData.selectedNumbers.map(num => `
                    <div class="selected-number-badge">
                        ${num}
                        <span class="remove" onclick="event.stopPropagation(); toggleNumber(${num})">✕</span>
                    </div>
                `).join('');
            }
        }

        function wizardAddRij() {
            if (wizardData.selectedNumbers.length === 0) {
                alert('Selecteer minimaal 1 nummer');
                return;
            }
            
            if (wizardData.rijen.length >= 10) {
                alert('Maximaal 10 rijen per bon');
                return;
            }
            
            const bet = parseFloat(document.getElementById('wizard-bet').value);
            if (isNaN(bet) || bet < 0.50) {
                alert('Minimale inzet is €0.50');
                return;
            }
            
            const rij = {
                numbers: [...wizardData.selectedNumbers],
                bet: bet,
                gameType: `${wizardData.selectedNumbers.length}-getallen`
            };
            
            wizardData.rijen.push(rij);
            wizardData.selectedNumbers = [];
            
            updateSelectedNumbersDisplay();
            updateNumberGrid();
            updateRijenList();
        }

        function updateRijenList() {
            const container = document.getElementById('wizard-rijen-list');
            
            if (wizardData.rijen.length === 0) {
                container.innerHTML = '';
                return;
            }
            
            container.innerHTML = `
                <div style="font-size: 12px; font-weight: 600; color: #999; text-transform: uppercase; margin-bottom: 12px;">
                    Toegevoegde Rijen (${wizardData.rijen.length}/10)
                </div>
                ${wizardData.rijen.map((rij, index) => `
                    <div class="rij-preview">
                        <div class="rij-preview-header">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="background: #667eea; color: white; width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700;">
                                    ${index + 1}
                                </div>
                                <div style="background: #e8eaf6; color: #667eea; padding: 4px 9px; border-radius: 6px; font-size: 11px; font-weight: 600;">
                                    ${rij.gameType}
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="font-size: 14px; font-weight: 600;">€${rij.bet.toFixed(2).replace('.', ',')}</span>
                                <button onclick="wizardRemoveRij(${index})" style="background: none; border: none; color: #EF4444; cursor: pointer; font-size: 18px;">✕</button>
                            </div>
                        </div>
                        <div class="rij-preview-numbers">
                            ${rij.numbers.map(num => `<div class="preview-number">${num}</div>`).join('')}
                        </div>
                    </div>
                `).join('')}
            `;
        }

        function wizardRemoveRij(index) {
            wizardData.rijen.splice(index, 1);
            updateRijenList();
        }

        function updateSummary() {
            document.getElementById('summary-player').textContent = wizardData.player;
            document.getElementById('summary-winkel').textContent = wizardData.winkel === '1' ? 'Dapper' : 'Plein';
            document.getElementById('summary-rijen-count').textContent = wizardData.rijen.length;
            
            const totalBet = wizardData.rijen.reduce((sum, rij) => sum + rij.bet, 0);
            document.getElementById('summary-total-bet').textContent = '€' + totalBet.toFixed(2).replace('.', ',');
            
            const detailContainer = document.getElementById('summary-rijen-detail');
            detailContainer.innerHTML = `
                <div style="font-size: 13px; font-weight: 600; color: #666; margin-bottom: 12px;">Rijen Detail:</div>
                ${wizardData.rijen.map((rij, index) => `
                    <div style="background: #f9f9f9; border-radius: 8px; padding: 12px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-weight: 600; color: #667eea;">#${index + 1}</span>
                            <span style="font-size: 13px; color: #666;">${rij.gameType}</span>
                            <span style="font-size: 12px; color: #999;">${rij.numbers.join(', ')}</span>
                        </div>
                        <span style="font-weight: 600;">€${rij.bet.toFixed(2).replace('.', ',')}</span>
                    </div>
                `).join('')}
            `;
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                });
                document.body.style.overflow = '';
            }
        });
    </script>
</body>
</html>

