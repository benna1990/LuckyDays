<?php
// Script om migraties uit te voeren
require_once 'config.php';

echo "=== Lucky Days Database Migraties ===\n\n";

$migrations = [
    '001_add_bonnummer.sql',
    '002_add_winkels.sql'
];

foreach ($migrations as $migration) {
    $file = __DIR__ . '/migrations/' . $migration;
    
    if (!file_exists($file)) {
        echo "❌ Migratie niet gevonden: $migration\n";
        continue;
    }
    
    echo "⏳ Uitvoeren: $migration...\n";
    
    $sql = file_get_contents($file);
    
    try {
        $result = pg_query($conn, $sql);
        if ($result) {
            echo "✅ Voltooid: $migration\n\n";
        } else {
            echo "❌ Fout: " . pg_last_error($conn) . "\n\n";
        }
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n\n";
    }
}

echo "=== Migraties voltooid ===\n";
?>



