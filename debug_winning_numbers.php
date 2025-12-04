<?php
require_once 'config.php';
require_once 'functions.php';

echo "=== WINNING NUMBERS DEBUG ===\n\n";

// Check table structure
echo "Table structure:\n";
$result = pg_query($conn, "
    SELECT column_name, data_type
    FROM information_schema.columns
    WHERE table_name = 'winning_numbers'
    ORDER BY ordinal_position
");

while ($row = pg_fetch_assoc($result)) {
    echo "  - {$row['column_name']}: {$row['data_type']}\n";
}

echo "\n";

// Check all winning numbers in database
echo "All winning numbers in database:\n";
$result = pg_query($conn, "
    SELECT date, numbers
    FROM winning_numbers
    ORDER BY date DESC
    LIMIT 10
");

while ($row = pg_fetch_assoc($result)) {
    $nums = explode(',', $row['numbers']);
    echo "  Date: {$row['date']}\n";
    echo "  Numbers: " . implode(', ', array_slice($nums, 0, 20)) . "\n";
    echo "  Count: " . count($nums) . "\n";
    echo "  First 5: " . implode(', ', array_slice($nums, 0, 5)) . "...\n";
    echo "  ---\n";
}

echo "\n";

// Check for duplicate dates
echo "Checking for duplicate dates:\n";
$result = pg_query($conn, "
    SELECT date, COUNT(*) as count
    FROM winning_numbers
    GROUP BY date
    HAVING COUNT(*) > 1
");

$duplicates = pg_num_rows($result);
if ($duplicates > 0) {
    echo "  WARNING: Found $duplicates dates with multiple entries!\n";
    while ($row = pg_fetch_assoc($result)) {
        echo "    - {$row['date']}: {$row['count']} entries\n";
    }
} else {
    echo "  No duplicate dates found.\n";
}

echo "\n";
echo "=== END DEBUG ===\n";
?>
