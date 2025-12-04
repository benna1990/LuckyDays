<?php
/**
 * Test Script voor Money Pattern
 *
 * Run dit script om te verifiëren dat de nieuwe services correct werken.
 *
 * Usage: php test_money_pattern.php
 *        Of open in browser: http://localhost/LuckyDays/test_money_pattern.php
 */

require_once 'php/services/MoneyCalculator.php';
require_once 'php/services/FinancialService.php';

// HTML header (als je in browser draait)
$isCLI = php_sapi_name() === 'cli';
if (!$isCLI) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Money Pattern Tests</title>";
    echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;}";
    echo ".test{background:white;padding:15px;margin:10px 0;border-radius:5px;border-left:4px solid #2ECC71;}";
    echo ".fail{border-left-color:#EF4444;}.pass{color:#2ECC71;}.fail-msg{color:#EF4444;}</style></head><body>";
    echo "<h1>💰 Money Pattern Test Suite</h1>";
}

// Test counter
$tests_passed = 0;
$tests_failed = 0;
$tests_total = 0;

function test($name, $callback) {
    global $tests_passed, $tests_failed, $tests_total, $isCLI;
    $tests_total++;

    try {
        $callback();
        $tests_passed++;

        if ($isCLI) {
            echo "✅ PASS: $name\n";
        } else {
            echo "<div class='test pass'>✅ <strong>PASS:</strong> $name</div>";
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

function assertEquals($expected, $actual, $message = "") {
    if ($expected !== $actual) {
        throw new Exception("Expected: $expected, Got: $actual. $message");
    }
}

// === TESTS ===

echo $isCLI ? "\n=== MoneyCalculator Tests ===\n\n" : "<h2>MoneyCalculator Tests</h2>";

test("toCents converts euro to cents", function() {
    assertEquals(1250, MoneyCalculator::toCents(12.50));
    assertEquals(10, MoneyCalculator::toCents(0.10));
    assertEquals(100000, MoneyCalculator::toCents(1000));
    assertEquals(0, MoneyCalculator::toCents(0));
});

test("toCents handles strings", function() {
    assertEquals(1250, MoneyCalculator::toCents("12.50"));
    assertEquals(1250, MoneyCalculator::toCents("12,50")); // Nederlandse komma
});

test("toEuros converts cents to euro", function() {
    assertEquals(12.50, MoneyCalculator::toEuros(1250));
    assertEquals(0.10, MoneyCalculator::toEuros(10));
    assertEquals(1000.0, MoneyCalculator::toEuros(100000));
});

test("add performs exact addition", function() {
    assertEquals(2000, MoneyCalculator::add(1250, 750));
    assertEquals(30, MoneyCalculator::add(10, 20)); // Famous 0.1 + 0.2 = 0.3 test!
});

test("subtract performs exact subtraction", function() {
    assertEquals(1250, MoneyCalculator::subtract(2000, 750));
    assertEquals(-500, MoneyCalculator::subtract(500, 1000)); // Negatief
});

test("multiply calculates percentage correctly", function() {
    assertEquals(300, MoneyCalculator::multiply(1000, 0.30)); // 30%
    assertEquals(2500, MoneyCalculator::multiply(1000, 2.5));
});

test("percentage helper works", function() {
    assertEquals(300, MoneyCalculator::percentage(1000, 30.0));
    assertEquals(700, MoneyCalculator::percentage(1000, 70.0));
});

test("formatEuro produces correct Dutch format", function() {
    assertEquals("€12,50", MoneyCalculator::formatEuro(1250));
    assertEquals("€1.250,00", MoneyCalculator::formatEuro(125000));
    assertEquals("€0,10", MoneyCalculator::formatEuro(10));
});

test("formatEuro without symbol", function() {
    assertEquals("12,50", MoneyCalculator::formatEuro(1250, false));
});

test("compare returns correct comparison", function() {
    assertEquals(1, MoneyCalculator::compare(1000, 500));   // 1000 > 500
    assertEquals(-1, MoneyCalculator::compare(500, 1000));  // 500 < 1000
    assertEquals(0, MoneyCalculator::compare(1000, 1000));  // 1000 == 1000
});

test("isPositive detects positive amounts", function() {
    assertEquals(true, MoneyCalculator::isPositive(100));
    assertEquals(false, MoneyCalculator::isPositive(0));
    assertEquals(false, MoneyCalculator::isPositive(-100));
});

test("isNegative detects negative amounts", function() {
    assertEquals(true, MoneyCalculator::isNegative(-100));
    assertEquals(false, MoneyCalculator::isNegative(0));
    assertEquals(false, MoneyCalculator::isNegative(100));
});

test("isZero detects zero", function() {
    assertEquals(true, MoneyCalculator::isZero(0));
    assertEquals(false, MoneyCalculator::isZero(1));
    assertEquals(false, MoneyCalculator::isZero(-1));
});

test("abs returns absolute value", function() {
    assertEquals(1250, MoneyCalculator::abs(-1250));
    assertEquals(1250, MoneyCalculator::abs(1250));
    assertEquals(0, MoneyCalculator::abs(0));
});

echo $isCLI ? "\n=== FinancialService Tests ===\n\n" : "<h2>FinancialService Tests</h2>";

test("calculateCommission with COMMISSION_ON_BET", function() {
    // €1000 inzet, €200 winst
    $result = FinancialService::calculateCommission(100000, 20000);

    assertEquals(30000, $result['commission']);      // €300 (30% van inzet)
    assertEquals(70000, $result['house_pot']);       // €700 (70% van inzet)
    assertEquals(50000, $result['net_house']);       // €500 (€700 - €200)
    assertEquals('bet', $result['basis']);
});

test("calculateFinancialBreakdown returns complete data", function() {
    $breakdown = FinancialService::calculateFinancialBreakdown(100000, 20000);

    assertEquals(100000, $breakdown['inzet']);
    assertEquals(1000.0, $breakdown['inzet_euros']);
    assertEquals(20000, $breakdown['winst']);
    assertEquals(200.0, $breakdown['winst_euros']);
    assertEquals(30000, $breakdown['commission']);
    assertEquals(300.0, $breakdown['commission_euros']);
    assertEquals(70000, $breakdown['house_pot']);
    assertEquals(700.0, $breakdown['house_pot_euros']);
    assertEquals(50000, $breakdown['net_house']);
    assertEquals(500.0, $breakdown['net_house_euros']);
    assertEquals('bet', $breakdown['commission_basis']);
    assertEquals(30.0, $breakdown['commission_percentage']);
    assertEquals(70.0, $breakdown['house_percentage']);
});

test("calculatePlayerVsHouse when player wins", function() {
    // Speler: €100 inzet, €150 winst → +€50
    $result = FinancialService::calculatePlayerVsHouse(10000, 15000);

    assertEquals(5000, $result['speler_saldo']);      // +€50
    assertEquals(50.0, $result['speler_saldo_euros']);
    assertEquals(-5000, $result['huis_saldo']);       // -€50 (huis verliest)
    assertEquals(-50.0, $result['huis_saldo_euros']);
    assertEquals(false, $result['huis_wint']);
    assertEquals(true, $result['speler_wint']);
    assertEquals(false, $result['gelijk']);
});

test("calculatePlayerVsHouse when house wins", function() {
    // Speler: €100 inzet, €50 winst → -€50
    $result = FinancialService::calculatePlayerVsHouse(10000, 5000);

    assertEquals(-5000, $result['speler_saldo']);     // -€50 (speler verliest)
    assertEquals(5000, $result['huis_saldo']);        // +€50 (huis wint)
    assertEquals(true, $result['huis_wint']);
    assertEquals(false, $result['speler_wint']);
});

test("calculatePlayerVsHouse when break-even", function() {
    // Speler: €100 inzet, €100 winst → €0
    $result = FinancialService::calculatePlayerVsHouse(10000, 10000);

    assertEquals(0, $result['speler_saldo']);
    assertEquals(0, $result['huis_saldo']);
    assertEquals(false, $result['huis_wint']);
    assertEquals(false, $result['speler_wint']);
    assertEquals(true, $result['gelijk']);
});

test("calculateHouseShare returns correct 70%", function() {
    $result = FinancialService::calculateHouseShare(100000);

    assertEquals(70000, $result['house_share']);
    assertEquals(700.0, $result['house_share_euros']);
    assertEquals(70.0, $result['percentage']);
});

test("calculateNetHouse calculates net result", function() {
    $result = FinancialService::calculateNetHouse(100000, 20000);

    assertEquals(50000, $result['net_house']);        // €700 - €200 = €500
    assertEquals(500.0, $result['net_house_euros']);
    assertEquals(true, $result['is_positive']);
    assertEquals(false, $result['is_negative']);
    assertEquals(false, $result['is_zero']);
});

test("calculateCommissionLegacy for backwards compatibility", function() {
    $result = FinancialService::calculateCommissionLegacy(1000.0, 200.0);

    assertEquals(300.0, $result['commission']);       // Float voor legacy
    assertEquals(700.0, $result['house_pot']);
    assertEquals(500.0, $result['net_house']);
});

echo $isCLI ? "\n=== Real World Scenario Tests ===\n\n" : "<h2>Real World Scenario Tests</h2>";

test("Weekoverzicht scenario: €25,000 inzet, €3,500 uitbetaald", function() {
    // Realistisch weekoverzicht scenario
    $inzetCents = MoneyCalculator::toCents(25000);      // €25.000
    $winstCents = MoneyCalculator::toCents(3500);       // €3.500

    $breakdown = FinancialService::calculateFinancialBreakdown($inzetCents, $winstCents);

    // Verwachte resultaten met COMMISSION_ON_BET:
    assertEquals(750000, $breakdown['commission']);     // €7.500 (30% van €25.000)
    assertEquals(1750000, $breakdown['house_pot']);     // €17.500 (70% van €25.000)
    assertEquals(1400000, $breakdown['net_house']);     // €14.000 (€17.500 - €3.500)

    assertEquals("€7.500,00", MoneyCalculator::formatEuro($breakdown['commission']));
    assertEquals("€17.500,00", MoneyCalculator::formatEuro($breakdown['house_pot']));
    assertEquals("€14.000,00", MoneyCalculator::formatEuro($breakdown['net_house']));
});

test("Edge case: Exactly zero", function() {
    $breakdown = FinancialService::calculateFinancialBreakdown(0, 0);

    assertEquals(0, $breakdown['commission']);
    assertEquals(0, $breakdown['house_pot']);
    assertEquals(0, $breakdown['net_house']);
});

test("Edge case: Very small amount (1 cent)", function() {
    $breakdown = FinancialService::calculateFinancialBreakdown(1, 0);

    assertEquals(0, $breakdown['commission']);          // 30% van €0.01 = 0 (afgerond)
    assertEquals(1, $breakdown['house_pot']);           // 70% van €0.01 = 1 cent
});

test("Edge case: House loses (high winnings)", function() {
    // €1000 inzet, €2000 uitbetaald (huis verliest €300)
    $breakdown = FinancialService::calculateFinancialBreakdown(100000, 200000);

    assertEquals(30000, $breakdown['commission']);      // €300
    assertEquals(70000, $breakdown['house_pot']);       // €700
    assertEquals(-130000, $breakdown['net_house']);     // -€1.300 (verlies!)
    assertEquals(-1300.0, $breakdown['net_house_euros']);
});

test("Float precision issue demonstration", function() {
    // Het probleem dat we oplossen:
    // In PHP: 0.1 + 0.2 !== 0.3 (floating point error)

    // ❌ FOUT met floats:
    $float_a = 0.1;
    $float_b = 0.2;
    $float_sum = $float_a + $float_b;
    // $float_sum is 0.30000000000000004, NIET 0.3!

    // ✅ CORRECT met centen:
    $cents_a = MoneyCalculator::toCents(0.1);   // 10 centen
    $cents_b = MoneyCalculator::toCents(0.2);   // 20 centen
    $cents_sum = MoneyCalculator::add($cents_a, $cents_b);  // 30 centen

    assertEquals(30, $cents_sum);
    assertEquals(0.3, MoneyCalculator::toEuros($cents_sum));

    // Bewijs dat float niet exact is:
    $float_not_exact = ($float_sum !== 0.3);
    assertEquals(true, $float_not_exact, "Float 0.1 + 0.2 should NOT equal exactly 0.3");
});

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
        echo "🎉 ALL TESTS PASSED! Money Pattern is working correctly.\n";
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
        echo "<p>Money Pattern is working correctly. The financial services are ready for production use.</p>";
    } else {
        echo "<h3>⚠️ SOME TESTS FAILED</h3>";
        echo "<p>Please review the errors above and fix the issues.</p>";
    }
    echo "</div>";
    echo "</body></html>";
}
