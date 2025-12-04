<?php
require_once 'config.php';
require_once 'functions.php';

// Create a test bon with 3 trekkingen
$playerId = 33; // Use existing player
$winkelId = 1;
$baseDate = '2025-12-02';
$aantalTrekkingen = 3;

echo "Creating test bon with {$aantalTrekkingen} trekkingen...\n\n";

// Create first bon
$insertBonQuery = "
    INSERT INTO bons (player_id, name, date, created_at, winkel_id)
    VALUES ($1, $2, $3, NOW(), $4)
    RETURNING id
";
$bonResult = db_query($insertBonQuery, [$playerId, 'Test 3-Trekkingen', $baseDate, $winkelId]);
$firstBon = db_fetch_assoc($bonResult);
$firstBonId = $firstBon['id'];
$groepId = $firstBonId;

echo "Created first bon with ID: {$firstBonId}\n";

// Update first bon with groep_id
$updateGroepQuery = "UPDATE bons SET trekking_groep_id = $1 WHERE id = $2";
db_query($updateGroepQuery, [$groepId, $firstBonId]);
echo "Set trekking_groep_id to {$groepId}\n";

// Add some test rijen to first bon
$testNumbers = ['5,12,23,34,45', '8,16,24,32,40', '3,9,15,21,27'];
foreach ($testNumbers as $numbers) {
    $insertRijQuery = "
        INSERT INTO rijen (bon_id, numbers, bet, winnings, created_at)
        VALUES ($1, $2, $3, 0, NOW())
    ";
    db_query($insertRijQuery, [$firstBonId, $numbers, 2.00]);
}
echo "Added 3 test rijen to first bon\n\n";

// Create additional bonnen for days 2 and 3
$createdBonIds = [$firstBonId];
for ($i = 1; $i < $aantalTrekkingen; $i++) {
    $newDate = new DateTime($baseDate);
    $newDate->modify("+{$i} days");
    $newDateStr = $newDate->format('Y-m-d');

    $insertBonQuery = "
        INSERT INTO bons (player_id, name, date, created_at, winkel_id, trekking_groep_id)
        VALUES ($1, $2, $3, NOW(), $4, $5)
        RETURNING id
    ";
    $newBonResult = db_query($insertBonQuery, [$playerId, 'Test 3-Trekkingen', $newDateStr, $winkelId, $groepId]);
    $newBon = db_fetch_assoc($newBonResult);
    $newBonId = $newBon['id'];
    $createdBonIds[] = $newBonId;

    echo "Created bon {$newBonId} for date {$newDateStr}\n";

    // Copy rijen to new bon
    foreach ($testNumbers as $numbers) {
        $insertRijQuery = "
            INSERT INTO rijen (bon_id, numbers, bet, winnings, created_at)
            VALUES ($1, $2, $3, 0, NOW())
        ";
        db_query($insertRijQuery, [$newBonId, $numbers, 2.00]);
    }
}

echo "\n✓ Created multi-trekking bon group!\n";
echo "Groep ID: {$groepId}\n";
echo "Bon IDs: " . implode(', ', $createdBonIds) . "\n";
echo "Dates: {$baseDate} to " . (new DateTime($baseDate))->modify('+' . ($aantalTrekkingen - 1) . ' days')->format('Y-m-d') . "\n";
?>
