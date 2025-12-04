<?php
/**
 * Test Suite voor LoggerService
 *
 * Verifieert dat de nieuwe LoggerService correct werkt:
 * - logChange() met diff calculation
 * - logError() file writing
 * - Duplicate preventie
 * - Export functionaliteit
 *
 * Usage: php test_logger_service.php
 *        Of open in browser: http://localhost/LuckyDays/test_logger_service.php
 */

require_once 'config.php';
require_once 'php/services/LoggerService.php';

// HTML header (als je in browser draait)
$isCLI = php_sapi_name() === 'cli';
if (!$isCLI) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>LoggerService Tests</title>";
    echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;}";
    echo ".test{background:white;padding:15px;margin:10px 0;border-radius:5px;border-left:4px solid #2ECC71;}";
    echo ".fail{border-left-color:#EF4444;}.pass{color:#2ECC71;}.fail-msg{color:#EF4444;}";
    echo "pre{background:#f9f9f9;padding:10px;border-radius:4px;overflow-x:auto;}</style></head><body>";
    echo "<h1>📋 LoggerService Test Suite</h1>";
}

// Test counter
$tests_passed = 0;
$tests_failed = 0;
$tests_total = 0;

function test($name, $callback) {
    global $tests_passed, $tests_failed, $tests_total, $isCLI;
    $tests_total++;

    try {
        $result = $callback();
        $tests_passed++;

        if ($isCLI) {
            echo "✅ PASS: $name\n";
            if ($result !== null) {
                echo "   Result: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        } else {
            echo "<div class='test pass'>✅ <strong>PASS:</strong> $name";
            if ($result !== null) {
                echo "<pre>" . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
            }
            echo "</div>";
        }
    } catch (Exception $e) {
        $tests_failed++;

        if ($isCLI) {
            echo "❌ FAIL: $name\n";
            echo "   Error: " . $e->getMessage() . "\n";
        } else {
            echo "<div class='test fail'>❌ <strong>FAIL:</strong> $name<br><span class='fail-msg'>Error: " . htmlspecialchars($e->getMessage()) . "</span></div>";
        }
    }
}

function assertNotNull($value, $message = "Value should not be null") {
    if ($value === null) {
        throw new Exception($message);
    }
}

function assertEquals($expected, $actual, $message = "") {
    if ($expected !== $actual) {
        throw new Exception("Expected: " . json_encode($expected) . ", Got: " . json_encode($actual) . ". $message");
    }
}

function assertTrue($value, $message = "Value should be true") {
    if ($value !== true) {
        throw new Exception($message);
    }
}

function assertGreaterThan($expected, $actual, $message = "") {
    if ($actual <= $expected) {
        throw new Exception("Expected $actual to be greater than $expected. $message");
    }
}

function assertContains($needle, $haystack, $message = "") {
    if (strpos($haystack, $needle) === false) {
        throw new Exception("Expected string to contain '$needle'. $message");
    }
}

// Maak test log directory
$testLogDir = __DIR__ . '/logs';
if (!is_dir($testLogDir)) {
    mkdir($testLogDir, 0755, true);
}

// === TESTS ===

echo $isCLI ? "\n=== LoggerService Setup Tests ===\n\n" : "<h2>LoggerService Setup Tests</h2>";

$logger = null;

test("LoggerService can be instantiated", function() use ($conn, $testLogDir, &$logger) {
    $logger = new LoggerService($conn, $testLogDir);
    assertNotNull($logger);
    return null;
});

test("LoggerService health check passes", function() use ($logger) {
    $healthy = $logger->healthCheck();
    assertEquals(true, $healthy, "LoggerService should be healthy");
    return ['healthy' => $healthy];
});

echo $isCLI ? "\n=== logChange() Tests ===\n\n" : "<h2>logChange() Tests</h2>";

$testUserId = 999;
$testLogId = null;

test("logChange() writes a log entry", function() use ($logger, $testUserId, &$testLogId) {
    $oldValues = ['name' => 'John', 'age' => 25];
    $newValues = ['name' => 'John', 'age' => 26];

    $logId = $logger->logChange(
        $testUserId,
        'test_user_update',
        'user',
        123,
        $oldValues,
        $newValues
    );

    assertGreaterThan(0, $logId, "logChange should return a valid log ID");
    $testLogId = $logId;

    return ['log_id' => $logId];
});

test("logChange() calculates diff correctly", function() use ($conn, $testLogId) {
    // Haal de log entry op
    $result = pg_query_params($conn, "SELECT details FROM audit_log WHERE id = $1", [$testLogId]);
    $row = pg_fetch_assoc($result);

    assertNotNull($row, "Log entry should exist");

    $details = json_decode($row['details'], true);

    // Check dat diff correct is
    assertTrue(isset($details['diff']), "Details should have diff");
    assertTrue(isset($details['diff']['age']), "Diff should contain 'age'");
    assertEquals(25, $details['diff']['age']['old']);
    assertEquals(26, $details['diff']['age']['new']);

    // 'name' mag NIET in de diff (want ongewijzigd)
    assertTrue(!isset($details['diff']['name']), "'name' should not be in diff (unchanged)");

    // Check changed_fields
    assertEquals(['age'], $details['changed_fields']);
    assertEquals(1, $details['change_count']);

    return $details;
});

test("logChange() prevents duplicate logs", function() use ($logger, $testUserId, $conn) {
    $oldValues = ['status' => 'pending'];
    $newValues = ['status' => 'completed'];

    // Verwijder oude test logs eerst
    pg_query_params($conn, "DELETE FROM audit_log WHERE action = 'test_duplicate_prevention'", []);

    // Eerste log
    $logId1 = $logger->logChange(
        $testUserId,
        'test_duplicate_prevention',
        'order',
        999,
        $oldValues,
        $newValues
    );

    assertGreaterThan(0, $logId1, "First log should be created");

    // Wacht even (maar binnen 5 seconden)
    usleep(100000); // 0.1 seconde

    // Tweede log (binnen 5 seconden, zelfde data) → moet 0 returnen
    $logId2 = $logger->logChange(
        $testUserId,
        'test_duplicate_prevention',
        'order',
        999,
        $oldValues,
        $newValues
    );

    assertEquals(0, $logId2, "Duplicate log should return 0");

    return ['first_log_id' => $logId1, 'duplicate_returned' => $logId2];
});

test("logChange() with context", function() use ($logger, $testUserId, $conn) {
    $oldValues = ['price' => 100.0];
    $newValues = ['price' => 120.0];
    $context = ['reason' => 'price correction', 'ticket' => '#ABC-123'];

    $logId = $logger->logChange(
        $testUserId,
        'test_with_context',
        'product',
        555,
        $oldValues,
        $newValues,
        $context
    );

    assertGreaterThan(0, $logId);

    // Check context in details
    $result = pg_query_params($conn, "SELECT details FROM audit_log WHERE id = $1", [$logId]);
    $row = pg_fetch_assoc($result);
    $details = json_decode($row['details'], true);

    assertTrue(isset($details['context']), "Details should have context");
    assertEquals('price correction', $details['context']['reason']);
    assertEquals('#ABC-123', $details['context']['ticket']);

    return $details['context'];
});

test("logChange() with multiple field changes", function() use ($logger, $testUserId, $conn) {
    $oldValues = ['name' => 'Product A', 'price' => 50.0, 'stock' => 10];
    $newValues = ['name' => 'Product B', 'price' => 60.0, 'stock' => 10];

    $logId = $logger->logChange(
        $testUserId,
        'test_multi_field',
        'product',
        777,
        $oldValues,
        $newValues
    );

    assertGreaterThan(0, $logId);

    $result = pg_query_params($conn, "SELECT details FROM audit_log WHERE id = $1", [$logId]);
    $row = pg_fetch_assoc($result);
    $details = json_decode($row['details'], true);

    // Check dat alleen 'name' en 'price' in diff zitten (niet 'stock')
    assertEquals(2, $details['change_count'], "Should have 2 changes");
    assertTrue(isset($details['diff']['name']));
    assertTrue(isset($details['diff']['price']));
    assertTrue(!isset($details['diff']['stock']), "'stock' should not be in diff");

    return $details['changed_fields'];
});

test("logChange() throws exception on invalid params", function() use ($logger, $testUserId) {
    $exceptionThrown = false;

    try {
        $logger->logChange($testUserId, '', 'user', 123, [], []);
    } catch (InvalidArgumentException $e) {
        $exceptionThrown = true;
    }

    assertTrue($exceptionThrown, "Should throw exception for empty action");

    return ['exception_thrown' => $exceptionThrown];
});

echo $isCLI ? "\n=== logError() Tests ===\n\n" : "<h2>logError() Tests</h2>";

test("logError() writes to error.log", function() use ($logger, $testLogDir) {
    $message = "Test error message at " . date('Y-m-d H:i:s');
    $trace = "Stack trace line 1\nStack trace line 2";

    $logger->logError($message, $trace);

    $logFile = $testLogDir . '/error.log';
    assertTrue(file_exists($logFile), "error.log should exist");

    $contents = file_get_contents($logFile);
    assertContains($message, $contents, "Log should contain error message");
    assertContains("Stack trace line 1", $contents, "Log should contain trace");

    return ['log_file_size' => filesize($logFile)];
});

test("logError() with context", function() use ($logger, $testLogDir) {
    $message = "Test error with context";
    $trace = "Trace here";
    $context = ['user_id' => 123, 'operation' => 'test'];

    $logger->logError($message, $trace, $context);

    $logFile = $testLogDir . '/error.log';
    $contents = file_get_contents($logFile);

    assertContains('Context:', $contents);
    assertContains('"user_id": 123', $contents);

    return ['context_logged' => true];
});

echo $isCLI ? "\n=== Export Tests ===\n\n" : "<h2>Export Tests</h2>";

test("exportAuditLogCsv() returns CSV string", function() use ($logger) {
    $csv = $logger->exportAuditLogCsv(
        startDate: date('Y-m-d', strtotime('-1 day')),
        endDate: date('Y-m-d'),
        action: null,
        entityType: null,
        userId: null
    );

    assertTrue(is_string($csv), "Export should return string");
    assertTrue(strlen($csv) > 0, "CSV should not be empty");

    // Check CSV header
    assertContains('ID,Timestamp,Action', $csv, "CSV should have header row");

    return ['csv_length' => strlen($csv), 'lines' => substr_count($csv, "\n")];
});

test("exportAuditLogCsv() with filters", function() use ($logger) {
    $csv = $logger->exportAuditLogCsv(
        startDate: date('Y-m-d'),
        endDate: date('Y-m-d'),
        action: 'test_user_update',
        entityType: 'user',
        userId: 999
    );

    assertTrue(is_string($csv));
    assertContains('test_user_update', $csv, "CSV should contain filtered action");

    return ['filtered' => true];
});

echo $isCLI ? "\n=== getRecentLogs() Tests ===\n\n" : "<h2>getRecentLogs() Tests</h2>";

test("getRecentLogs() returns array", function() use ($logger) {
    $logs = $logger->getRecentLogs(10, 0, ['action' => 'test_user_update']);

    assertTrue(is_array($logs), "Should return array");
    assertTrue(count($logs) > 0, "Should have at least one log");

    // Check dat de eerste log de test log is
    assertTrue(isset($logs[0]['details_parsed']), "Log should have parsed details");

    return ['log_count' => count($logs), 'first_action' => $logs[0]['action']];
});

test("getRecentLogs() with pagination", function() use ($logger) {
    $page1 = $logger->getRecentLogs(5, 0);
    $page2 = $logger->getRecentLogs(5, 5);

    assertTrue(is_array($page1));
    assertTrue(is_array($page2));

    // Als er genoeg logs zijn, zouden de IDs anders moeten zijn
    if (count($page1) > 0 && count($page2) > 0) {
        assertTrue($page1[0]['id'] !== $page2[0]['id'], "Pagination should return different results");
    }

    return ['page1_count' => count($page1), 'page2_count' => count($page2)];
});

// === CLEANUP ===

// Verwijder test logs
pg_query_params($conn, "DELETE FROM audit_log WHERE action LIKE 'test_%'", []);

// === SUMMARY ===

echo $isCLI ? "\n" . str_repeat("=", 50) . "\n" : "<hr>";
echo $isCLI ? "TEST SUMMARY\n" : "<h2>Test Summary</h2>";
echo $isCLI ? str_repeat("=", 50) . "\n\n" : "";

$pass_rate = $tests_total > 0 ? round(($tests_passed / $tests_total) * 100, 1) : 0;

if ($isCLI) {
    echo "Total Tests:  $tests_total\n";
    echo "✅ Passed:    $tests_passed\n";
    echo "❌ Failed:    $tests_failed\n";
    echo "Pass Rate:    $pass_rate%\n\n";

    if ($tests_failed === 0) {
        echo "🎉 ALL TESTS PASSED! LoggerService is working correctly.\n";
        echo "\n";
        echo "✅ logChange() berekent diff correct\n";
        echo "✅ Duplicate preventie werkt (binnen 5 seconden)\n";
        echo "✅ logError() schrijft naar /logs/error.log\n";
        echo "✅ Export functionaliteit werkt\n";
        echo "✅ Geen error suppression - alle failures gooien exceptions\n";
        exit(0);
    } else {
        echo "⚠️  SOME TESTS FAILED. Please review the errors above.\n";
        exit(1);
    }
} else {
    echo "<div style='background:" . ($tests_failed === 0 ? "#d1fae5" : "#fee2e2") . ";padding:20px;border-radius:8px;margin:20px 0;'>";
    echo "<h3>Results:</h3>";
    echo "<p><strong>Total Tests:</strong> $tests_total</p>";
    echo "<p><strong>✅ Passed:</strong> $tests_passed</p>";
    echo "<p><strong>❌ Failed:</strong> $tests_failed</p>";
    echo "<p><strong>Pass Rate:</strong> $pass_rate%</p>";

    if ($tests_failed === 0) {
        echo "<h3>🎉 ALL TESTS PASSED!</h3>";
        echo "<p>LoggerService is working correctly.</p>";
        echo "<ul>";
        echo "<li>✅ logChange() berekent diff correct</li>";
        echo "<li>✅ Duplicate preventie werkt (binnen 5 seconden)</li>";
        echo "<li>✅ logError() schrijft naar /logs/error.log</li>";
        echo "<li>✅ Export functionaliteit werkt</li>";
        echo "<li>✅ Geen error suppression - alle failures gooien exceptions</li>";
        echo "</ul>";
    } else {
        echo "<h3>⚠️ SOME TESTS FAILED</h3>";
        echo "<p>Please review the errors above and fix the issues.</p>";
    }
    echo "</div>";
    echo "</body></html>";
}
