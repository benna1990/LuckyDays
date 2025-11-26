<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $result = pg_query_params($conn, 
            "SELECT * FROM admins WHERE username = $1", 
            [$username]
        );
        
        if ($result && pg_num_rows($result) > 0) {
            $user = pg_fetch_assoc($result);
            
            if ($password === $user['password'] || password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'] ?? 'user';
                $_SESSION['user_id'] = $user['id'];
                
                header('Location: /dashboard.php');
                exit();
            } else {
                $error = 'Onjuist wachtwoord';
            }
        } else {
            $error = 'Gebruiker niet gevonden';
        }
    } else {
        $error = 'Vul alle velden in';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Lucky Day</title>
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
        
        .login-container {
            width: 100%;
            max-width: 420px;
        }
        
        .login-card {
            background: #FFFFFF;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
            padding: 3rem;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .login-logo {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1A1A1A;
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
            color: #6B7280;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: #374151;
        }
        
        .form-input {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.2s;
            background: #FAFAFA;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #10B981;
            background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }
        
        .form-input::placeholder {
            color: #9CA3AF;
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
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: #FEE2E2;
            color: #DC2626;
            padding: 1rem;
            border-radius: 12px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #F3F4F6;
        }
        
        .credentials-hint {
            background: #F0FDF4;
            color: #065F46;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.8rem;
        }
        
        .credentials-hint strong {
            color: #047857;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">🍀</div>
                <h1 class="login-title">Lucky Day</h1>
                <p class="login-subtitle">Log in om verder te gaan</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Gebruikersnaam</label>
                    <input type="text" name="username" class="form-input" placeholder="Voer gebruikersnaam in" required autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Wachtwoord</label>
                    <input type="password" name="password" class="form-input" placeholder="Voer wachtwoord in" required autocomplete="current-password">
                </div>
                
                <button type="submit" class="btn-login">Inloggen</button>
            </form>
            
            <div class="login-footer">
                <div class="credentials-hint">
                    Standaard login: <strong>admin</strong> / <strong>admin</strong>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
