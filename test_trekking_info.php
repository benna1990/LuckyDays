<?php
require_once 'config.php';
require_once 'functions.php';

// Test trekking info for bon 57
$bonId = 57;

$query = "SELECT * FROM bons WHERE id = $1";
$result = db_query($query, [$bonId]);
$bon = db_fetch_assoc($result);

echo "Bon details:\n";
echo json_encode($bon, JSON_PRETTY_PRINT) . "\n\n";

if (!empty($bon['trekking_groep_id'])) {
    echo "Trekking groep ID: " . $bon['trekking_groep_id'] . "\n\n";

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

    echo "Groep data:\n";
    echo json_encode($groepData, JSON_PRETTY_PRINT) . "\n\n";

    if ($groepData && $groepData['aantal_trekkingen'] > 1) {
        // Calculate current position in series
        $positionQuery = "
            SELECT COUNT(*) + 1 as positie
            FROM bons
            WHERE trekking_groep_id = $1 AND date < $2
        ";
        $positionResult = db_query($positionQuery, [$bon['trekking_groep_id'], $bon['date']]);
        $positionData = db_fetch_assoc($positionResult);

        echo "Position data:\n";
        echo json_encode($positionData, JSON_PRETTY_PRINT) . "\n\n";

        $trekkingInfo = [
            'aantal_trekkingen' => (int)$groepData['aantal_trekkingen'],
            'huidige_trekking' => (int)$positionData['positie'],
            'start_datum' => $groepData['start_datum'],
            'eind_datum' => $groepData['eind_datum']
        ];

        echo "Trekking Info:\n";
        echo json_encode($trekkingInfo, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Only 1 trekking in group - not showing banner\n";
    }
} else {
    echo "No trekking_groep_id\n";
}
?>
