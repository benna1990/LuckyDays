<?php
// Check for old data (only if logged in)
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // Check if warning was dismissed this session
    $warningDismissed = $_SESSION['old_data_warning_dismissed'] ?? false;
    
    if (!$warningDismissed) {
        require_once __DIR__ . '/../config.php';
        $cutoff_date = date('Y-m-d', strtotime('-2 months'));
        
        try {
            $query = "SELECT COUNT(*) as count FROM bons WHERE date < $1";
            $result = db_query($query, [$cutoff_date]);
            $row = db_fetch_assoc($result);
            $old_data_count = intval($row['count'] ?? 0);
            
            if ($old_data_count > 0):
?>
<div id="old-data-banner" class="bg-amber-50 border-b border-amber-200 relative" style="z-index: 30;">
    <div class="container-fixed py-3">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 flex-1">
                <span class="text-amber-600 text-lg">⚠️</span>
                <p class="text-sm text-amber-800">
                    <strong>Let op:</strong> Er staat verouderde data in het systeem (<?= $old_data_count ?> bonnen ouder dan <?= date('d-m-Y', strtotime($cutoff_date)) ?>).
                    <a href="beheer.php" class="underline hover:text-amber-900 font-medium ml-1">Verwijderen via Instellingen → Databeheer</a>
                </p>
            </div>
            <button onclick="dismissWarning()" class="px-3 py-1 text-xs text-amber-700 hover:text-amber-900 hover:bg-amber-100 rounded transition">
                Verbergen
            </button>
        </div>
    </div>
</div>

<script>
async function dismissWarning() {
    document.getElementById('old-data-banner').style.display = 'none';
    
    // Mark as dismissed in session
    try {
        await fetch('api/dismiss_old_data_warning.php', { method: 'POST' });
    } catch (e) {
        console.error('Could not dismiss warning:', e);
    }
}
</script>
<?php
            endif;
        } catch (Exception $e) {
            // Silently fail - don't show warning if there's an error
        }
    }
}
?>

