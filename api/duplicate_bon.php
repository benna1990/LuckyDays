<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../config.php';
require_once '../audit_log.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit();
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$bonId = $input['bon_id'] ?? null;
$aantalTrekkingen = $input['aantal_trekkingen'] ?? 1;

if (!$bonId || !is_numeric($bonId)) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige bon_id']);
    exit();
}

if (!is_numeric($aantalTrekkingen) || $aantalTrekkingen < 1 || $aantalTrekkingen > 7) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige aantal trekkingen (1-7)']);
    exit();
}

try {
    // Get original bon details
    $bonQuery = "SELECT * FROM bons WHERE id = $1";
    $bonResult = db_query($bonQuery, [$bonId]);
    $originalBon = db_fetch_assoc($bonResult);

    if (!$originalBon) {
        echo json_encode(['success' => false, 'error' => 'Bon niet gevonden']);
        exit();
    }

    // Get all rijen from original bon
    $rijenQuery = "SELECT numbers, bet FROM rijen WHERE bon_id = $1 ORDER BY id ASC";
    $rijenResult = db_query($rijenQuery, [$bonId]);
    $rijen = db_fetch_all($rijenResult);

    if (empty($rijen)) {
        echo json_encode(['success' => false, 'error' => 'Geen rijen gevonden in bon']);
        exit();
    }

    // Use the original bon's ID as the group ID (or create a new one if it doesn't have one)
    $groepId = $originalBon['trekking_groep_id'] ?? $bonId;

    // Update original bon with groep_id if it doesn't have one
    if ($originalBon['trekking_groep_id'] === null) {
        $updateGroepQuery = "UPDATE bons SET trekking_groep_id = $1 WHERE id = $2";
        db_query($updateGroepQuery, [$groepId, $bonId]);
    }

    // Calculate winning numbers for each date
    $originalDate = new DateTime($originalBon['date']);
    $createdBonIds = [$bonId]; // Include original bon

    // Create additional bonnen for days 2 to aantalTrekkingen
    for ($i = 1; $i < $aantalTrekkingen; $i++) {
        $newDate = clone $originalDate;
        $newDate->modify("+{$i} days");
        $newDateStr = $newDate->format('Y-m-d');

        // Create new bon with trekking_groep_id
        $insertBonQuery = "
            INSERT INTO bons (player_id, name, date, created_at, winkel_id, trekking_groep_id)
            VALUES ($1, $2, $3, NOW(), $4, $5)
            RETURNING id
        ";
        $newBonResult = db_query($insertBonQuery, [
            $originalBon['player_id'],
            $originalBon['name'],
            $newDateStr,
            $originalBon['winkel_id'],
            $groepId
        ]);
        $newBon = db_fetch_assoc($newBonResult);
        $newBonId = $newBon['id'];
        $createdBonIds[] = $newBonId;
        add_audit_log($conn, 'bon_create', 'bon', $newBonId, [
            'source_bon_id' => $bonId,
            'date' => $newDateStr,
            'trekking_groep_id' => $groepId
        ]);

        // Copy all rijen to new bon
        foreach ($rijen as $rij) {
            $insertRijQuery = "
                INSERT INTO rijen (bon_id, numbers, bet, winnings, created_at)
                VALUES ($1, $2, $3, 0, NOW())
            ";
            db_query($insertRijQuery, [
                $newBonId,
                $rij['numbers'],
                $rij['bet']
            ]);
        }
    }

    echo json_encode([
        'success' => true,
        'created_bon_ids' => $createdBonIds,
        'aantal_trekkingen' => $aantalTrekkingen
    ]);

} catch (Exception $e) {
    error_log("Error duplicating bon: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database fout: ' . $e->getMessage()]);
}
