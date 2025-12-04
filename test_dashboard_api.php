<?php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['selected_winkel'] = null;

require_once 'config.php';
require_once 'functions.php';

$date = '2025-12-02';
echo "Testing dashboard data for date: {$date}\n\n";

$bonnen = getBonnenByDate($conn, $date, null);
echo "Number of bonnen: " . count($bonnen) . "\n\n";

foreach ($bonnen as $bon) {
    echo "Bon ID: " . $bon['id'] . " - " . $bon['name'] . " - Player: " . $bon['player_name'] . "\n";
    echo "  trekking_groep_id: " . ($bon['trekking_groep_id'] ?? 'null') . "\n";

    if (!empty($bon['trekking_groep_id'])) {
        $groepQuery = "
            SELECT
                COUNT(*) as aantal_trekkingen,
                MIN(date) as start_datum,
                MAX(date) as eind_datum
            FROM bons
            WHERE trekking_groep_id = $1
        ";
        $groepResult = db_query($groepQuery, [$bon['trekking_groep_id']]);
        $groepData = db_fetch_assoc($groepResult);

        if ($groepData && $groepData['aantal_trekkingen'] > 1) {
            $positionQuery = "
                SELECT COUNT(*) + 1 as positie
                FROM bons
                WHERE trekking_groep_id = $1 AND date < $2
            ";
            $positionResult = db_query($positionQuery, [$bon['trekking_groep_id'], $bon['date']]);
            $positionData = db_fetch_assoc($positionResult);

            echo "  → Trekking Info: {$positionData['positie']}/{$groepData['aantal_trekkingen']} trekkingen\n";
            echo "  → Dates: {$groepData['start_datum']} to {$groepData['eind_datum']}\n";
        } else {
            echo "  → Only 1 trekking (no banner)\n";
        }
    }
    echo "\n";
}
?>
