<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'components/winkel_selector.php';
require_once 'audit_log.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Check and setup role column if needed
$checkColumn = pg_query($conn, "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name='admins' AND column_name='role'
");

if (pg_num_rows($checkColumn) == 0) {
    pg_query($conn, "ALTER TABLE admins ADD COLUMN role VARCHAR(20) DEFAULT 'admin'");
    pg_query($conn, "UPDATE admins SET role = 'admin' WHERE role IS NULL OR role = ''");
}

// Get current user role
$currentUserRole = $_SESSION['role'] ?? 'user';
$currentUserId = $_SESSION['user_id'] ?? 0;

// Beheer pagina heeft GEEN winkel filter - altijd alles tonen
$winkels = getAllWinkels($conn);
$selectedWinkel = null; // Altijd "Alles" voor beheer
$activeWinkelTheme = resolveActiveWinkelTheme($winkels, $selectedWinkel);
$winkelPalette = getWinkelPalette();

// Get all users
$usersQuery = "SELECT id, username, role FROM admins ORDER BY username ASC";
$usersResult = db_query($usersQuery, []);
$users = db_fetch_all($usersResult) ?: [];

// Get all players with their lifetime stats
$playersQuery = "
    SELECT
        p.id, p.name, p.color, p.created_at,
        COUNT(DISTINCT b.id) as total_bons,
        COUNT(r.id) as total_rijen,
        COALESCE(SUM(r.bet), 0) as total_bet,
        COALESCE(SUM(r.winnings), 0) as total_winnings,
        COALESCE(SUM(r.winnings), 0) - COALESCE(SUM(r.bet), 0) as saldo
    FROM players p
    LEFT JOIN bons b ON p.id = b.player_id
    LEFT JOIN rijen r ON r.bon_id = b.id
    GROUP BY p.id, p.name, p.color, p.created_at
    ORDER BY p.name ASC
";

$playersResult = db_query($playersQuery, []);
$players = db_fetch_all($playersResult) ?: [];

// Prepare bon logboek data (admins/beheerders)
$bonLogError = null;
$bonLogTableExists = false;
if (in_array($currentUserRole, ['admin', 'beheerder'])) {
    $tableCheck = pg_query($conn, "SELECT to_regclass('public.audit_log') as tbl");
    $tableRow = $tableCheck ? pg_fetch_assoc($tableCheck) : null;
    $bonLogTableExists = $tableRow && !empty($tableRow['tbl']);

    if (!$bonLogTableExists) {
        $bonLogError = 'Audit log tabel ontbreekt (draai migratie 005_create_audit_log.sql).';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_player':
            $name = trim($_POST['name'] ?? '');
            $color = $_POST['color'] ?? '#3B82F6';
            $winkelId = intval($_POST['winkel_id'] ?? 0);

            if (empty($name)) {
                echo json_encode(['success' => false, 'error' => 'Naam is verplicht']);
                exit();
            }

            if ($winkelId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Winkel is verplicht']);
                exit();
            }

            $result = addPlayer($conn, $name, $color, $winkelId);
            if ($result['success']) {
                add_audit_log($conn, 'player_create', 'player', $result['id'], [
                    'name' => $name,
                    'color' => $color,
                    'winkel_id' => $winkelId
                ]);
            }
            echo json_encode($result);
            exit();

        case 'update_player':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $color = $_POST['color'] ?? '#3B82F6';

            if ($id <= 0 || empty($name)) {
                echo json_encode(['success' => false, 'error' => 'Ongeldige gegevens']);
                exit();
            }

            $oldPlayer = pg_fetch_assoc(pg_query_params($conn, "SELECT name, color, winkel_id FROM players WHERE id = $1", [$id]));
            $result = updatePlayer($conn, $id, $name, $color);
            if ($result['success']) {
                add_audit_log($conn, 'player_update', 'player', $id, [
                    'old' => $oldPlayer,
                    'new' => ['name' => $name, 'color' => $color, 'winkel_id' => $oldPlayer['winkel_id'] ?? null]
                ]);
            }
            echo json_encode($result);
            exit();

        case 'delete_player':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Ongeldige speler']);
                exit();
            }

            $oldPlayer = pg_fetch_assoc(pg_query_params($conn, "SELECT name, color, winkel_id FROM players WHERE id = $1", [$id]));

            if (deletePlayer($conn, $id)) {
                add_audit_log($conn, 'player_delete', 'player', $id, [
                    'old' => $oldPlayer
                ]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Kon speler niet verwijderen']);
            }
            exit();

        case 'change_password':
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                echo json_encode(['success' => false, 'error' => 'Alle velden zijn verplicht']);
                exit();
            }

            // Check current user password
            $result = pg_query_params($conn, "SELECT password FROM admins WHERE id = $1", [$currentUserId]);
            $user = pg_fetch_assoc($result);

            if (!$user || !password_verify($currentPassword, $user['password'])) {
                echo json_encode(['success' => false, 'error' => 'Huidig wachtwoord is onjuist']);
                exit();
            }

            if ($newPassword !== $confirmPassword) {
                echo json_encode(['success' => false, 'error' => 'Nieuwe wachtwoorden komen niet overeen']);
                exit();
            }

            if (strlen($newPassword) < 6) {
                echo json_encode(['success' => false, 'error' => 'Wachtwoord moet minimaal 6 tekens zijn']);
                exit();
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateResult = pg_query_params($conn, "UPDATE admins SET password = $1 WHERE id = $2", [$hashedPassword, $currentUserId]);

            if ($updateResult) {
                add_audit_log($conn, 'user_password_change', 'admin', $currentUserId, [
                    'username' => $_SESSION['username'] ?? null
                ]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Kon wachtwoord niet bijwerken']);
            }
            exit();
            
        case 'add_user':
            if ($currentUserRole !== 'admin') {
                echo json_encode(['success' => false, 'error' => 'Geen toegang']);
                exit();
            }
            
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';
            
            if (empty($username) || empty($password)) {
                echo json_encode(['success' => false, 'error' => 'Gebruikersnaam en wachtwoord zijn verplicht']);
                exit();
            }
            
            if (strlen($password) < 6) {
                echo json_encode(['success' => false, 'error' => 'Wachtwoord moet minimaal 6 tekens zijn']);
                exit();
            }
            
            // Check if username exists
            $checkResult = pg_query_params($conn, "SELECT id FROM admins WHERE username = $1", [$username]);
            if (pg_num_rows($checkResult) > 0) {
                echo json_encode(['success' => false, 'error' => 'Gebruikersnaam bestaat al']);
                exit();
            }
            
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertResult = pg_query_params($conn, 
                "INSERT INTO admins (username, password, role) VALUES ($1, $2, $3) RETURNING id",
                [$username, $hashedPassword, $role]
            );
            
            if ($insertResult) {
                $newId = pg_fetch_result($insertResult, 0, 'id');
                add_audit_log($conn, 'user_create', 'admin', $newId, [
                    'username' => $username,
                    'role' => $role
                ]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Kon gebruiker niet aanmaken']);
            }
            exit();
            
        case 'update_user':
            if ($currentUserRole !== 'admin') {
                echo json_encode(['success' => false, 'error' => 'Geen toegang']);
                exit();
            }
            
            $userId = intval($_POST['user_id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $role = $_POST['role'] ?? 'user';
            $password = $_POST['password'] ?? '';
            
            if ($userId <= 0 || empty($username)) {
                echo json_encode(['success' => false, 'error' => 'Ongeldige gegevens']);
                exit();
            }

            $oldUser = pg_fetch_assoc(pg_query_params($conn, "SELECT username, role FROM admins WHERE id = $1", [$userId]));
            
            // Check if username exists for another user
            $checkResult = pg_query_params($conn, "SELECT id FROM admins WHERE username = $1 AND id != $2", [$username, $userId]);
            if (pg_num_rows($checkResult) > 0) {
                echo json_encode(['success' => false, 'error' => 'Gebruikersnaam bestaat al']);
                exit();
            }
            
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    echo json_encode(['success' => false, 'error' => 'Wachtwoord moet minimaal 6 tekens zijn']);
                    exit();
                }
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $updateResult = pg_query_params($conn, 
                    "UPDATE admins SET username = $1, password = $2, role = $3 WHERE id = $4",
                    [$username, $hashedPassword, $role, $userId]
                );
            } else {
                $updateResult = pg_query_params($conn, 
                    "UPDATE admins SET username = $1, role = $2 WHERE id = $3",
                    [$username, $role, $userId]
                );
            }
            
            if ($updateResult) {
                add_audit_log($conn, 'user_update', 'admin', $userId, [
                    'old' => $oldUser,
                    'new' => ['username' => $username, 'role' => $role]
                ]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Kon gebruiker niet bijwerken']);
            }
            exit();
            
        case 'delete_user':
            if ($currentUserRole !== 'admin') {
                echo json_encode(['success' => false, 'error' => 'Geen toegang']);
                exit();
            }
            
            $userId = intval($_POST['user_id'] ?? 0);
            
            if ($userId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Ongeldige gebruiker']);
                exit();
            }
            
            if ($userId === $currentUserId) {
                echo json_encode(['success' => false, 'error' => 'Je kunt jezelf niet verwijderen']);
                exit();
            }

            $oldUser = pg_fetch_assoc(pg_query_params($conn, "SELECT username, role FROM admins WHERE id = $1", [$userId]));
            
            $deleteResult = pg_query_params($conn, "DELETE FROM admins WHERE id = $1", [$userId]);
            
            if ($deleteResult) {
                add_audit_log($conn, 'user_delete', 'admin', $userId, [
                    'old' => $oldUser
                ]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Kon gebruiker niet verwijderen']);
            }
            exit();
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beheer - Lucky Day</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            overflow-y: scroll;
            min-height: 100vh;
            background-color: #F8F9FA;
        }
        .card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 12px rgba(0,0,0,0.04); }
        .container-fixed {
            max-width: 1280px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 1rem;
            padding-right: 1rem;
        }
        .winkel-btn { 
            padding: 10px 24px; 
            font-size: 14px; 
            font-weight: 500; 
            color: var(--btn-text, #6B7280); 
            background: white; 
            border: 2px solid #E5E7EB; 
            border-radius: 20px; 
            transition: all 0.2s ease; 
            cursor: pointer; 
        }
        .winkel-btn:hover { 
            background: var(--btn-hover-bg, #F9FAFB); 
            border-color: var(--btn-hover-border, #D1D5DB); 
            color: var(--btn-hover-text, #374151);
        }
        .winkel-btn.active { 
            background: var(--btn-active-bg, #2ECC710F); 
            color: var(--btn-active-text, #2ECC71); 
            border-color: var(--btn-active-border, #2ECC71); 
            font-weight: 600;
        }
        
        /* Nav link underline */
        .nav-link {
            position: relative;
        }
        .nav-link.active {
            font-weight: 600;
        }
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 8px;
            right: 8px;
            height: 3px;
            background: <?= $activeWinkelTheme['accent'] ?>;
            border-radius: 3px 3px 0 0;
        }
        
        @media (max-width: 768px) {
            .hide-on-mobile { display: none; }
            .winkel-btn { padding: 8px 16px; font-size: 13px; }
        }
    </style>
</head>
<body>
    <?php include 'components/main_nav.php'; ?>

    <?php include 'components/old_data_warning.php'; ?>

    <?php include 'components/winkel_bar.php'; ?>

    <main class="container-fixed py-6">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Beheer</h2>
            <p class="text-sm text-gray-600">Instellingen en databeheer</p>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6">
            <button onclick="showTab('instellingen')" id="tab-instellingen" 
                    class="tab-pill active"
                    style="--tab-bg: <?= $activeWinkelTheme['accent'] ?>; --tab-color: <?= $activeWinkelTheme['accent'] ?>;">
                Instellingen
            </button>
            <?php if (in_array($currentUserRole, ['admin', 'beheerder'])): ?>
            <button onclick="showTab('databeheer')" id="tab-databeheer" 
                    class="tab-pill inactive"
                    style="--tab-bg: <?= $activeWinkelTheme['accent'] ?>; --tab-color: <?= $activeWinkelTheme['accent'] ?>;">
                Databeheer
            </button>
            <button onclick="showTab('logboek')" id="tab-logboek" 
                    class="tab-pill inactive"
                    style="--tab-bg: <?= $activeWinkelTheme['accent'] ?>; --tab-color: <?= $activeWinkelTheme['accent'] ?>;">
                Logboek
            </button>
            <?php endif; ?>
            <?php if ($currentUserRole === 'admin'): ?>
            <button onclick="showTab('gebruikers')" id="tab-gebruikers" 
                    class="tab-pill inactive"
                    style="--tab-bg: <?= $activeWinkelTheme['accent'] ?>; --tab-color: <?= $activeWinkelTheme['accent'] ?>;">
                Gebruikers
            </button>
            <?php endif; ?>
        </div>

        <!-- Gebruikers Tab -->
        <?php if ($currentUserRole === 'admin'): ?>
        <div id="content-gebruikers" class="tab-content hidden">
            <div class="card p-6 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Gebruikers Beheer</h3>
                    <button onclick="openAddUserModal()" class="px-4 py-2 bg-emerald-500 text-white text-sm font-medium rounded-lg hover:bg-emerald-600 transition">
                        + Nieuwe Gebruiker
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-100 uppercase tracking-wide">
                                <th class="pb-3 font-medium">Gebruikersnaam</th>
                                <th class="pb-3 font-medium">Rol</th>
                                <th class="pb-3 font-medium text-center">Acties</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3">
                                    <span class="font-medium text-gray-800"><?= htmlspecialchars($user['username']) ?></span>
                                    <?php if ($user['id'] == $currentUserId): ?>
                                        <span class="text-xs text-emerald-600 ml-2">(jij)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3">
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full 
                                        <?php 
                                        if ($user['role'] === 'admin') echo 'bg-purple-100 text-purple-700';
                                        elseif ($user['role'] === 'beheerder') echo 'bg-blue-100 text-blue-700';
                                        else echo 'bg-gray-100 text-gray-700';
                                        ?>">
                                        <?php 
                                        if ($user['role'] === 'admin') echo 'Admin';
                                        elseif ($user['role'] === 'beheerder') echo 'Beheerder';
                                        else echo 'Gebruiker';
                                        ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <button onclick='editUser(<?= json_encode($user) ?>)' class="text-blue-600 hover:text-blue-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <?php if ($user['id'] != $currentUserId): ?>
                                        <button onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" class="text-red-600 hover:text-red-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Rol Uitleg</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex gap-3">
                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-700 h-fit">Admin</span>
                        <p class="text-gray-600">Volledige toegang: kan alles, inclusief gebruikers beheren</p>
                    </div>
                    <div class="flex gap-3">
                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700 h-fit">Beheerder</span>
                        <p class="text-gray-600">Kan alles behalve gebruikers toevoegen of verwijderen</p>
                    </div>
                    <div class="flex gap-3">
                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700 h-fit">Gebruiker</span>
                        <p class="text-gray-600">Kan alleen data inzien en bonnen invoeren</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="content-logboek" class="tab-content hidden">
            <div class="card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Activiteitenlog</h3>
                        <p class="text-sm text-gray-600">Centrale audit log met filters en export per pagina.</p>
                    </div>
                    <?php if (!$bonLogTableExists): ?>
                        <span class="text-xs px-3 py-1 rounded-full bg-amber-100 text-amber-700 border border-amber-200">Migratie nodig</span>
                    <?php endif; ?>
                </div>

                <?php if ($bonLogError): ?>
                    <div class="p-4 mb-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                        <?= htmlspecialchars($bonLogError) ?>
                    </div>
                <?php endif; ?>

                <?php if ($bonLogTableExists): ?>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
                        <div class="flex flex-col">
                            <label class="text-xs text-gray-500 mb-1">Van</label>
                            <input type="date" id="log-start" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-xs text-gray-500 mb-1">Tot</label>
                            <input type="date" id="log-end" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-xs text-gray-500 mb-1">Gebruiker</label>
                            <select id="log-user" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                                <option value="">Alle</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-xs text-gray-500 mb-1">Actie</label>
                            <select id="log-action" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                                <option value="">Alles</option>
                                <option value="login">login</option>
                                <option value="logout">logout</option>
                                <option value="login_failed">login_failed</option>
                                <option value="bon_create">bon_create</option>
                                <option value="bon_update">bon_update</option>
                                <option value="bon_delete">bon_delete</option>
                                <option value="bon_row_create">bon_row_create</option>
                                <option value="bon_row_update">bon_row_update</option>
                                <option value="bon_checked">bon_checked</option>
                                <option value="bon_unchecked">bon_unchecked</option>
                                <option value="player_create">player_create</option>
                                <option value="player_update">player_update</option>
                                <option value="player_delete">player_delete</option>
                                <option value="user_create">user_create</option>
                                <option value="user_update">user_update</option>
                                <option value="user_delete">user_delete</option>
                                <option value="user_password_change">user_password_change</option>
                            </select>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-xs text-gray-500 mb-1">Entity</label>
                            <select id="log-entity" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                                <option value="">Alles</option>
                                <option value="bon">bon</option>
                                <option value="player">player</option>
                                <option value="admin">admin</option>
                                <option value="winkel">winkel</option>
                                <option value="settings">settings</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 mb-4">
                        <input id="log-search" type="text" placeholder="Zoek in details of gebruikersnaam..." class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm">
                        <button id="log-filter-btn" class="px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition">Filter</button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" id="audit-table">
                            <thead>
                                <tr class="text-left text-xs text-gray-500 border-b border-gray-100 uppercase tracking-wide">
                                    <th class="pb-3 font-medium">Datum</th>
                                    <th class="pb-3 font-medium">Gebruiker</th>
                                    <th class="pb-3 font-medium">Actie</th>
                                    <th class="pb-3 font-medium">Object</th>
                                    <th class="pb-3 font-medium">Details</th>
                                    <th class="pb-3 font-medium">IP</th>
                                </tr>
                            </thead>
                            <tbody id="audit-body" class="divide-y divide-gray-50">
                                <tr><td colspan="6" class="text-center text-gray-400 py-6">Laden...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between mt-4 text-sm text-gray-600">
                        <div id="audit-count"></div>
                        <div class="flex items-center gap-2">
                            <button id="audit-prev" class="px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40">Vorige</button>
                            <span id="audit-page" class="text-gray-700 font-semibold"></span>
                            <button id="audit-next" class="px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40">Volgende</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Instellingen Tab -->
        <div id="content-instellingen" class="tab-content">
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-6">Wachtwoord Wijzigen</h3>
                <form id="password-form" onsubmit="changePassword(event)" class="max-w-md space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Huidig Wachtwoord</label>
                        <input type="password" name="current_password" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nieuw Wachtwoord</label>
                        <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="text-xs text-gray-500 mt-1">Minimaal 6 tekens</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bevestig Nieuw Wachtwoord</label>
                        <input type="password" name="confirm_password" required minlength="6" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <button type="submit" class="px-6 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium">
                        Wachtwoord Wijzigen
                    </button>
                </form>
            </div>

            <div class="card p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Systeem Informatie</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Database:</span>
                        <span class="font-medium">PostgreSQL</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Totaal Spelers:</span>
                        <span class="font-medium"><?= count($players) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Admin Gebruiker:</span>
                        <span class="font-medium">admin</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Databeheer Tab (alleen voor admin en beheerder) -->
        <?php if (in_array($currentUserRole, ['admin', 'beheerder'])): ?>
        <div id="content-databeheer" class="tab-content hidden">
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    ⚠️ Databeheer
                </h3>
                <p class="text-sm text-gray-600 mb-6">Verwijder oude data om de database op te schonen. Dit kan niet ongedaan worden gemaakt.</p>
                
                <form id="databeheer-form" onsubmit="cleanupData(event)" class="max-w-md space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Verwijder data ouder dan:</label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="cleanup_type" value="30" class="text-emerald-500 focus:ring-emerald-500">
                                <span class="text-sm text-gray-700">1 maand (30 dagen)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="cleanup_type" value="60" checked class="text-emerald-500 focus:ring-emerald-500">
                                <span class="text-sm text-gray-700">2 maanden (60 dagen)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="cleanup_type" value="90" class="text-emerald-500 focus:ring-emerald-500">
                                <span class="text-sm text-gray-700">3 maanden (90 dagen)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="cleanup_type" value="custom" class="text-emerald-500 focus:ring-emerald-500">
                                <span class="text-sm text-gray-700">Handmatig kiezen (datum)</span>
                            </label>
                        </div>
                    </div>

                    <div id="custom-date-input" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Verwijder data voor:</label>
                        <input type="date" name="before_date" id="before-date" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bevestig met wachtwoord:</label>
                        <input type="password" name="confirm_password" id="cleanup-password" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition font-medium">
                            ⚠️ Verwijder Data
                        </button>
                        <button type="button" onclick="checkOldData()" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium">
                            Controleer Oude Data
                        </button>
                    </div>
                </form>

                <div id="cleanup-result" class="mt-4 hidden"></div>
            </div>

            <!-- Verwijder ALLE Data -->
            <div class="card p-6 border-2 border-red-200">
                <h3 class="text-lg font-semibold text-red-600 mb-4 flex items-center gap-2">
                    🚨 Gevaarlijke Zone
                </h3>
                <p class="text-sm text-gray-600 mb-6">
                    <strong>WAARSCHUWING:</strong> Deze actie verwijdert ALLE bonnen, rijen en gerelateerde data permanent. 
                    Gebruikers en instellingen blijven behouden. Deze actie kan NIET ongedaan worden gemaakt!
                </p>
                
                <form id="delete-all-form" onsubmit="deleteAllData(event)" class="max-w-md space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bevestig met wachtwoord:</label>
                        <input type="password" name="confirm_password" id="delete-all-password" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>

                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <p class="text-sm text-red-800 mb-2">Type "VERWIJDER ALLES" om te bevestigen:</p>
                        <input type="text" id="confirm-text" required placeholder="VERWIJDER ALLES" class="w-full px-4 py-2 border border-red-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>

                    <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">
                        🚨 VERWIJDER ALLE DATA
                    </button>
                </form>

                <div id="delete-all-result" class="mt-4 hidden"></div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Player Modal -->
    <div id="player-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
            <h3 id="modal-title" class="text-lg font-semibold text-gray-800 mb-4">Nieuwe Speler</h3>
            <form id="player-form" onsubmit="savePlayer(event)">
                <input type="hidden" id="player-id" name="id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Naam</label>
                        <input type="text" id="player-name" name="name" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Winkel</label>
                        <select id="player-winkel" name="winkel_id" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            <?php foreach ($winkels as $w): ?>
                            <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['naam']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kleur</label>
                        <input type="color" id="player-color" name="color" value="#3B82F6" class="w-full h-12 rounded-lg cursor-pointer">
                    </div>
                </div>
                <div class="flex gap-2 mt-6">
                    <button type="button" onclick="closePlayerModal()" class="flex-1 px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                        Annuleren
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 text-white bg-emerald-500 rounded-lg hover:bg-emerald-600 transition">
                        Opslaan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- User Modal -->
    <div id="user-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
            <h3 id="user-modal-title" class="text-lg font-semibold text-gray-800 mb-4">Nieuwe Gebruiker</h3>
            <form id="user-form" onsubmit="saveUser(event)">
                <input type="hidden" id="user-id" name="user_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Gebruikersnaam</label>
                        <input type="text" id="user-username" name="username" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Wachtwoord <span id="password-optional" class="text-xs text-gray-500">(optioneel bij bewerken)</span></label>
                        <input type="password" id="user-password" name="password" minlength="6" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="text-xs text-gray-500 mt-1">Minimaal 6 tekens</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rol</label>
                        <select id="user-role" name="role" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="admin">Admin (volledige toegang)</option>
                            <option value="beheerder">Beheerder (alles behalve gebruikers beheren)</option>
                            <option value="user">Gebruiker (alleen data inzien en invoeren)</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-2 mt-6">
                    <button type="button" onclick="closeUserModal()" class="flex-1 px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                        Annuleren
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 text-white bg-emerald-500 rounded-lg hover:bg-emerald-600 transition">
                        Opslaan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            
            // Update all tab buttons
            document.querySelectorAll('[id^="tab-"]').forEach(el => {
                el.classList.remove('active');
                el.classList.add('inactive');
            });

            // Show selected tab
            document.getElementById('content-' + tab).classList.remove('hidden');
            const activeTab = document.getElementById('tab-' + tab);
            activeTab.classList.remove('inactive');
            activeTab.classList.add('active');

            if (tab === 'logboek') {
                fetchAuditLogs();
            }
        }

        async function selectWinkel(winkelId) {
            try {
                const response = await fetch('api/set_winkel.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ winkel_id: winkelId })
                });

                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert('Fout bij selecteren winkel');
                }
            } catch (e) {
                console.error('Winkel selectie fout:', e);
                location.reload();
            }
        }

        function hydrateWinkelButtons() {
            document.querySelectorAll('[data-role="winkel-button"]').forEach(button => {
                if (button.dataset.winkelBound === 'true') {
                    return;
                }
                button.dataset.winkelBound = 'true';
                button.addEventListener('click', () => {
                    const target = button.dataset.winkelTarget;
                    if (target === 'all') {
                        selectWinkel(null);
                        return;
                    }
                    const winkelId = parseInt(target, 10);
                    selectWinkel(Number.isNaN(winkelId) ? null : winkelId);
                });
            });
        }

        document.addEventListener('DOMContentLoaded', hydrateWinkelButtons);

        function openAddPlayerModal() {
            document.getElementById('modal-title').textContent = 'Nieuwe Speler';
            document.getElementById('player-form').reset();
            document.getElementById('player-id').value = '';
            document.getElementById('player-modal').classList.remove('hidden');
            document.getElementById('player-modal').classList.add('flex');
        }

        function editPlayer(player) {
            document.getElementById('modal-title').textContent = 'Speler Bewerken';
            document.getElementById('player-id').value = player.id;
            document.getElementById('player-name').value = player.name;
            document.getElementById('player-color').value = player.color;
            document.getElementById('player-modal').classList.remove('hidden');
            document.getElementById('player-modal').classList.add('flex');
        }

        function closePlayerModal() {
            document.getElementById('player-modal').classList.add('hidden');
            document.getElementById('player-modal').classList.remove('flex');
        }

        async function savePlayer(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const playerId = formData.get('id');
            formData.append('action', playerId ? 'update_player' : 'add_player');

            try {
                const response = await fetch('beheer.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Er is een fout opgetreden');
                }
            } catch (error) {
                alert('Er is een fout opgetreden');
                console.error(error);
            }
        }

        // Audit log UI
        let auditPage = 1;
        const auditPerPage = 50;

        function escapeAuditHtml(str = '') {
            return (str ?? '').toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        async function fetchAuditLogs(page = 1) {
            auditPage = page;
            const start = document.getElementById('log-start')?.value || '';
            const end = document.getElementById('log-end')?.value || '';
            const user = document.getElementById('log-user')?.value || '';
            const action = document.getElementById('log-action')?.value || '';
            const entity = document.getElementById('log-entity')?.value || '';
            const search = document.getElementById('log-search')?.value || '';

            const params = new URLSearchParams();
            params.append('page', auditPage);
            params.append('per_page', auditPerPage);
            if (start) params.append('start_date', start);
            if (end) params.append('end_date', end);
            if (user) params.append('user_id', user);
            if (action) params.append('action', action);
            if (entity) params.append('entity_type', entity);
            if (search) params.append('search', search);

            const bodyEl = document.getElementById('audit-body');
            const countEl = document.getElementById('audit-count');
            const pageEl = document.getElementById('audit-page');
            const prevBtn = document.getElementById('audit-prev');
            const nextBtn = document.getElementById('audit-next');

            if (bodyEl) bodyEl.innerHTML = '<tr><td colspan="6" class="text-center text-gray-400 py-6">Laden...</td></tr>';

            try {
                const res = await fetch('api/get_audit_logs.php?' + params.toString());
                const data = await res.json();
                if (!data.success) {
                    if (bodyEl) bodyEl.innerHTML = '<tr><td colspan="6" class="text-center text-red-500 py-6">Fout bij laden</td></tr>';
                    return;
                }

                const items = data.items || [];
                if (!items.length) {
                    if (bodyEl) bodyEl.innerHTML = '<tr><td colspan="6" class="text-center text-gray-400 py-6">Geen resultaten</td></tr>';
                } else if (bodyEl) {
                    bodyEl.innerHTML = items.map(log => {
                        const date = new Date(log.created_at);
                        const dateStr = `${date.toLocaleDateString('nl-NL')} ${date.toLocaleTimeString('nl-NL')}`;
                        const badgeClass = log.action && log.action.indexOf('delete') !== -1 ? 'bg-red-50 text-red-700 border-red-200' :
                            (log.action && (log.action === 'login' || log.action === 'logout') ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-800 border-gray-200');
                        const details = log.details && typeof log.details === 'object'
                            ? Object.keys(log.details).map(k => `<div><span class="font-semibold">${escapeAuditHtml(k)}:</span> ${escapeAuditHtml(typeof log.details[k] === 'object' ? JSON.stringify(log.details[k]) : log.details[k])}</div>`).join('')
                            : (log.details ? escapeAuditHtml(log.details) : '<span class="text-gray-400">—</span>');
                        const objectLabel = log.entity_type ? `${escapeAuditHtml(log.entity_type)}${log.entity_id ? ' #' + escapeAuditHtml(log.entity_id) : ''}` : '—';
                        return `
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 text-gray-800 whitespace-nowrap">${dateStr}</td>
                                <td class="py-3 text-gray-800">${log.username ? escapeAuditHtml(log.username) : '—'}${log.user_id ? ' (' + log.user_id + ')' : ''}</td>
                                <td class="py-3">
                                    <span class="inline-flex items-center px-2 py-1 text-[11px] font-semibold rounded-full border ${badgeClass}">
                                        ${escapeAuditHtml(log.action || '')}
                                    </span>
                                </td>
                                <td class="py-3 text-gray-800">${objectLabel}</td>
                                <td class="py-3 text-gray-700 text-xs leading-5">${details}</td>
                                <td class="py-3 text-gray-500 text-xs">${escapeAuditHtml(log.ip_address || '')}</td>
                            </tr>
                        `;
                    }).join('');
                }

                if (countEl) countEl.textContent = `${data.total || 0} resultaten`;
                if (pageEl) pageEl.textContent = `Pagina ${data.page} / ${Math.max(1, Math.ceil((data.total || 0) / auditPerPage))}`;
                if (prevBtn) prevBtn.disabled = data.page <= 1;
                if (nextBtn) nextBtn.disabled = data.page >= Math.ceil((data.total || 0) / auditPerPage);

                if (prevBtn) prevBtn.onclick = () => { if (auditPage > 1) fetchAuditLogs(auditPage - 1); };
                if (nextBtn) nextBtn.onclick = () => { fetchAuditLogs(auditPage + 1); };

            } catch (e) {
                console.error('Audit log load error', e);
                if (bodyEl) bodyEl.innerHTML = '<tr><td colspan="6" class="text-center text-red-500 py-6">Kon data niet laden</td></tr>';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('log-filter-btn');
            if (btn) btn.addEventListener('click', () => fetchAuditLogs(1));
        });

        async function deletePlayer(id, name) {
            if (!confirm(`Weet je zeker dat je ${name} wilt verwijderen?`)) return;

            const formData = new FormData();
            formData.append('action', 'delete_player');
            formData.append('id', id);

            try {
                const response = await fetch('beheer.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Kon speler niet verwijderen');
                }
            } catch (error) {
                alert('Er is een fout opgetreden');
                console.error(error);
            }
        }

        async function changePassword(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'change_password');

            try {
                const response = await fetch('beheer.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    alert('Wachtwoord succesvol gewijzigd!');
                    e.target.reset();
                } else {
                    alert(data.error || 'Er is een fout opgetreden');
                }
            } catch (error) {
                alert('Er is een fout opgetreden');
                console.error(error);
            }
        }

        // User management functions
        function openAddUserModal() {
            document.getElementById('user-modal-title').textContent = 'Nieuwe Gebruiker';
            document.getElementById('user-form').reset();
            document.getElementById('user-id').value = '';
            document.getElementById('user-password').required = true;
            document.getElementById('password-optional').style.display = 'none';
            document.getElementById('user-modal').classList.remove('hidden');
            document.getElementById('user-modal').classList.add('flex');
        }

        function editUser(user) {
            document.getElementById('user-modal-title').textContent = 'Gebruiker Bewerken';
            document.getElementById('user-id').value = user.id;
            document.getElementById('user-username').value = user.username;
            document.getElementById('user-role').value = user.role;
            document.getElementById('user-password').value = '';
            document.getElementById('user-password').required = false;
            document.getElementById('password-optional').style.display = 'inline';
            document.getElementById('user-modal').classList.remove('hidden');
            document.getElementById('user-modal').classList.add('flex');
        }

        function closeUserModal() {
            document.getElementById('user-modal').classList.add('hidden');
            document.getElementById('user-modal').classList.remove('flex');
        }

        async function saveUser(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const userId = formData.get('user_id');
            formData.append('action', userId ? 'update_user' : 'add_user');

            try {
                const response = await fetch('beheer.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Er is een fout opgetreden');
                }
            } catch (error) {
                alert('Er is een fout opgetreden');
                console.error(error);
            }
        }

        async function deleteUser(id, username) {
            if (!confirm(`Weet je zeker dat je gebruiker '${username}' wilt verwijderen?`)) return;

            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('user_id', id);

            try {
                const response = await fetch('beheer.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Kon gebruiker niet verwijderen');
                }
            } catch (error) {
                alert('Er is een fout opgetreden');
                console.error(error);
            }
        }

        // Databeheer functies
        document.querySelectorAll('input[name="cleanup_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const customInput = document.getElementById('custom-date-input');
                if (this.value === 'custom') {
                    customInput.classList.remove('hidden');
                    document.getElementById('before-date').required = true;
                } else {
                    customInput.classList.add('hidden');
                    document.getElementById('before-date').required = false;
                }
            });
        });

        async function checkOldData() {
            try {
                const response = await fetch('api/check_old_data.php');
                const data = await response.json();
                
                const resultDiv = document.getElementById('cleanup-result');
                if (data.success) {
                    if (data.has_old_data) {
                        resultDiv.innerHTML = `<div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                            <p class="text-sm text-amber-800"><strong>⚠️ ${data.count} bonnen</strong> gevonden ouder dan ${data.cutoff_date}</p>
                        </div>`;
                    } else {
                        resultDiv.innerHTML = `<div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                            <p class="text-sm text-emerald-800">✅ Geen oude data gevonden</p>
                        </div>`;
                    }
                    resultDiv.classList.remove('hidden');
                } else {
                    alert(data.error || 'Kon oude data niet checken');
                }
            } catch (error) {
                alert('Er is een fout opgetreden');
                console.error(error);
            }
        }

        async function cleanupData(event) {
            event.preventDefault();
            
            const form = event.target;
            const cleanupType = form.cleanup_type.value;
            const password = form.confirm_password.value;
            const beforeDate = form.before_date?.value;
            
            if (!password) {
                alert('Voer je wachtwoord in om te bevestigen');
                return;
            }
            
            const confirmation = confirm('⚠️ WAARSCHUWING: Dit verwijdert permanent alle geselecteerde data. Deze actie kan niet ongedaan worden gemaakt. Weet je het zeker?');
            if (!confirmation) return;
            
            try {
                const payload = { password };
                
                if (cleanupType === 'custom') {
                    if (!beforeDate) {
                        alert('Selecteer een datum');
                        return;
                    }
                    payload.before_date = beforeDate;
                } else {
                    payload.days = parseInt(cleanupType);
                }
                
                const response = await fetch('api/cleanup_old_data.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const data = await response.json();
                
                const resultDiv = document.getElementById('cleanup-result');
                if (data.success) {
                    resultDiv.innerHTML = `<div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                        <p class="text-sm text-emerald-800">✅ ${data.message}</p>
                    </div>`;
                    resultDiv.classList.remove('hidden');
                    form.reset();
                    setTimeout(() => location.reload(), 2000);
                } else {
                    resultDiv.innerHTML = `<div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-800">❌ ${data.error}</p>
                    </div>`;
                    resultDiv.classList.remove('hidden');
                }
            } catch (error) {
                alert('Er is een fout opgetreden');
                console.error(error);
            }
        }

        // Verwijder ALLE data
        async function deleteAllData(event) {
            event.preventDefault();
            
            const form = event.target;
            const password = form.confirm_password.value;
            const confirmText = document.getElementById('confirm-text').value;
            
            if (!password) {
                alert('Voer je wachtwoord in om te bevestigen');
                return;
            }
            
            if (confirmText !== 'VERWIJDER ALLES') {
                alert('Type exact "VERWIJDER ALLES" om te bevestigen');
                return;
            }
            
            const confirmation = confirm('🚨 LAATSTE WAARSCHUWING: Je staat op het punt om ALLE bonnen, rijen en gerelateerde data permanent te verwijderen. Dit kan NIET ongedaan worden gemaakt!\n\nWeet je het ABSOLUUT ZEKER?');
            if (!confirmation) return;
            
            const finalConfirmation = confirm('Dit is je laatste kans! Klik OK om ALLE data te verwijderen, of Annuleren om te stoppen.');
            if (!finalConfirmation) return;
            
            try {
                const response = await fetch('api/delete_all_data.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ password })
                });
                
                const data = await response.json();
                
                const resultDiv = document.getElementById('delete-all-result');
                if (data.success) {
                    resultDiv.innerHTML = `<div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                        <p class="text-sm text-emerald-800">✅ ${data.message}</p>
                    </div>`;
                    resultDiv.classList.remove('hidden');
                    form.reset();
                    setTimeout(() => location.reload(), 2000);
                } else {
                    resultDiv.innerHTML = `<div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-800">❌ ${data.error}</p>
                    </div>`;
                    resultDiv.classList.remove('hidden');
                }
            } catch (error) {
                alert('Er is een fout opgetreden');
                console.error(error);
            }
        }
    </script>
</body>
</html>
