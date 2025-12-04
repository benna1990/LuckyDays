<?php
// Simpele migratie-runner: kies migratie (checked of logs) en voer uit.
// Beveiliging: alleen ingelogde admin.
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo 'Niet ingelogd';
    exit;
}
require_once 'config.php';

$migrationOptions = [
    '003_add_bon_checked.sql' => 'Bon checked kolommen',
    '004_add_bon_logs.sql'    => 'Bon logtabel',
    '005_create_audit_log.sql'=> 'Audit log tabel'
];
$selectedMigration = $_POST['migration'] ?? '003_add_bon_checked.sql';
$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($migrationOptions[$selectedMigration])) {
        $message = 'Ongeldige migratie';
        $status = 'error';
    } elseif (!file_exists('migrations/' . $selectedMigration)) {
        $message = 'Migratiebestand niet gevonden: ' . $selectedMigration;
        $status = 'error';
    } else {
        $sql = file_get_contents('migrations/' . $selectedMigration);
        $result = @pg_query($conn, $sql);
        if ($result) {
            $message = 'Migratie uitgevoerd: ' . $selectedMigration;
            $status = 'success';
        } else {
            $message = 'Migratie mislukt: ' . pg_last_error($conn);
            $status = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Run migratie</title>
    <link rel="stylesheet" href="assets/css/design-system.css">
    <style>
        body { font-family: Inter, sans-serif; background: #f9fafb; }
        .container { max-width: 520px; margin: 60px auto; background: white; border: 1px solid #e5e7eb; border-radius: 16px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 10px; border: 1px solid #e5e7eb; background: #111827; color: white; font-weight: 600; cursor: pointer; }
        .message { margin-top: 16px; padding: 12px; border-radius: 10px; font-size: 14px; }
        .success { background: #ecfdf3; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecdd3; }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="font-size:20px; font-weight:700; margin-bottom:8px;">Migratie uitvoeren</h2>
        <p style="color:#6b7280; margin-bottom:16px;">Kies een migratie en voer deze uit.</p>
        <form method="POST" style="display:flex; gap:8px; align-items:center;">
            <select name="migration" style="flex:1; padding:10px; border:1px solid #e5e7eb; border-radius:10px;">
                <?php foreach ($migrationOptions as $file => $label): ?>
                    <option value="<?= htmlspecialchars($file) ?>" <?= $file === $selectedMigration ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?> (<?= htmlspecialchars($file) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">Run migratie</button>
        </form>
        <?php if ($message): ?>
            <div class="message <?= $status === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
