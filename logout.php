<?php
session_start();
require_once 'audit_log.php';
require_once 'config.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    add_audit_log($conn, 'logout', 'admin', $_SESSION['user_id'] ?? null, [
        'username' => $_SESSION['username'] ?? null
    ]);
}

session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uitgelogd - Lucky Day</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #F0FDF4 0%, #ECFDF5 50%, #D1FAE5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .logout-container {
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        
        .logout-card {
            background: #FFFFFF;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
            padding: 3rem;
            animation: fadeIn 0.4s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logout-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            animation: bounce 0.6s ease-out;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .logout-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1A1A1A;
            margin-bottom: 0.75rem;
        }
        
        .logout-message {
            color: #6B7280;
            font-size: 0.95rem;
            margin-bottom: 2rem;
        }
        
        .btn-login {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }
        
        .redirect-message {
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: #9CA3AF;
        }
        
        .loading-dots {
            display: inline-block;
        }
        
        .loading-dots span {
            animation: blink 1.4s infinite both;
        }
        
        .loading-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .loading-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes blink {
            0%, 80%, 100% { opacity: 0; }
            40% { opacity: 1; }
        }
    </style>
    <script>
        // Auto redirect naar login pagina na 3 seconden
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 3000);
    </script>
</head>
<body>
    <div class="logout-container">
        <div class="logout-card">
            <div class="logout-icon">👋</div>
            <h1 class="logout-title">Tot ziens!</h1>
            <p class="logout-message">Je bent succesvol uitgelogd.</p>
            <a href="index.php" class="btn-login">Opnieuw Inloggen</a>
            <p class="redirect-message">
                Je wordt automatisch doorverwezen<span class="loading-dots"><span>.</span><span>.</span><span>.</span></span>
            </p>
        </div>
    </div>
</body>
</html>
