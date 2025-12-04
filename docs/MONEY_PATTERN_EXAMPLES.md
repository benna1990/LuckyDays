# Money Pattern - Praktische Voorbeelden

## 🎯 Snelle Referentie voor Developers

Dit document bevat concrete code voorbeelden voor het vervangen van oude float-based berekeningen naar The Money Pattern.

---

## 1. Basis Conversies

### Van euro naar centen (en terug)

```php
// Database geeft: "125.50" (string) of 125.50 (float)
$dbValue = $row['total_bet'];  // "125.50"

// ✅ Converteer naar centen voor berekeningen
$cents = MoneyCalculator::toCents($dbValue);  // 12550 (int)

// Reken met centen (geen float errors!)
$newCents = MoneyCalculator::add($cents, 5000);  // 17550

// ✅ Terug naar euro's voor display
$euros = MoneyCalculator::toEuros($newCents);  // 175.50 (float)

// ✅ Of direct formatteren
$formatted = MoneyCalculator::formatEuro($newCents);  // "€175,50"
```

---

## 2. Commissie Berekeningen

### Voorbeeld: Vervang hardcoded percentages

**❌ OUD:**
```php
$total_bet = floatval($week_totals['total_bet']);
$total_winnings = floatval($week_totals['total_winnings']);
$commission = $total_bet * 0.30;
$house_pot = $total_bet * 0.70;
$net_house = $house_pot - $total_winnings;
```

**✅ NIEUW:**
```php
// Stap 1: Converteer naar centen
$betCents = MoneyCalculator::toCents($week_totals['total_bet']);
$winCents = MoneyCalculator::toCents($week_totals['total_winnings']);

// Stap 2: Gebruik FinancialService
$breakdown = FinancialService::calculateFinancialBreakdown($betCents, $winCents);

// Stap 3: Extract wat je nodig hebt
$commission = $breakdown['commission_euros'];     // 300.0
$house_pot = $breakdown['house_pot_euros'];       // 700.0
$net_house = $breakdown['net_house_euros'];       // 500.0
```

---

## 3. Sommaties in Loops

### Voorbeeld: Tel bedragen op

**❌ OUD (float errors stapelen op):**
```php
$total = 0.0;
foreach ($bonnen as $bon) {
    $total += floatval($bon['bet']);  // Float errors!
}
```

**✅ NIEUW (exacte cent-precisie):**
```php
$totalCents = 0;
foreach ($bonnen as $bon) {
    $totalCents = MoneyCalculator::add(
        $totalCents,
        MoneyCalculator::toCents($bon['bet'])
    );
}

// Voor display:
$total = MoneyCalculator::toEuros($totalCents);
```

---

## 4. Percentage Berekeningen

### Voorbeeld: Bereken 30% commissie

**❌ OUD:**
```php
$commission = $amount * 0.30;
```

**✅ NIEUW (optie 1 - via multiply):**
```php
$amountCents = MoneyCalculator::toCents($amount);
$commissionCents = MoneyCalculator::multiply($amountCents, 0.30);
$commission = MoneyCalculator::toEuros($commissionCents);
```

**✅ NIEUW (optie 2 - via percentage helper):**
```php
$amountCents = MoneyCalculator::toCents($amount);
$commissionCents = MoneyCalculator::percentage($amountCents, 30.0);
$commission = MoneyCalculator::toEuros($commissionCents);
```

---

## 5. Conditionele Logica

### Voorbeeld: Check of bedrag positief/negatief is

**❌ OUD (float vergelijkingen onbetrouwbaar):**
```php
$saldo = $winnings - $bet;
if ($saldo > 0) {
    // Speler wint
} elseif ($saldo < 0) {
    // Speler verliest
}
```

**✅ NIEUW:**
```php
$betCents = MoneyCalculator::toCents($bet);
$winCents = MoneyCalculator::toCents($winnings);
$saldoCents = MoneyCalculator::subtract($winCents, $betCents);

if (MoneyCalculator::isPositive($saldoCents)) {
    // Speler wint
} elseif (MoneyCalculator::isNegative($saldoCents)) {
    // Speler verliest
} elseif (MoneyCalculator::isZero($saldoCents)) {
    // Gelijk
}
```

**✅ NIEUW (nog beter - gebruik FinancialService):**
```php
$result = FinancialService::calculatePlayerVsHouse($betCents, $winCents);

if ($result['speler_wint']) {
    echo "Speler wint €" . abs($result['speler_saldo_euros']);
} elseif ($result['huis_wint']) {
    echo "Huis wint €" . abs($result['huis_saldo_euros']);
} else {
    echo "Gelijk spel";
}
```

---

## 6. CSV/Excel Export

### Voorbeeld: Export met correcte formatting

**❌ OUD:**
```php
$commission = $total_bet * 0.30;
fputcsv($output, ['Commissie', number_format($commission, 2, ',', '.')], ';');
```

**✅ NIEUW:**
```php
$betCents = MoneyCalculator::toCents($total_bet);
$breakdown = FinancialService::calculateFinancialBreakdown($betCents, $winCents);

fputcsv($output, [
    'Commissie',
    number_format($breakdown['commission_euros'], 2, ',', '.')
], ';');
```

---

## 7. Database Queries

### Voorbeeld: SUM in SQL omgaan met

**Database query blijft hetzelfde:**
```php
$query = "SELECT COALESCE(SUM(bet), 0) as total_bet FROM rijen WHERE bon_id = $1";
$result = pg_query_params($conn, $query, [$bonId]);
$row = pg_fetch_assoc($result);
```

**Maar converteer resultaat naar centen:**
```php
// Database geeft: string "1250.50"
$totalBetCents = MoneyCalculator::toCents($row['total_bet']);

// Verder rekenen met centen
$commission = FinancialService::calculateCommission($totalBetCents, $winstCents);
```

---

## 8. Template Display

### Voorbeeld: Toon bedragen in view

**Voor een enkele waarde:**
```php
$netHouseCents = 50000;  // €500.00

// Optie 1: Direct formatteren
echo MoneyCalculator::formatEuro($netHouseCents);  // "€500,00"

// Optie 2: Voor gebruik in HTML attributes
$euroValue = MoneyCalculator::toEuros($netHouseCents);  // 500.0
?>
<div data-amount="<?= $euroValue ?>">
    <?= MoneyCalculator::formatEuro($netHouseCents) ?>
</div>
```

**Voor een array van bedragen:**
```php
<?php foreach ($bonnen as $bon):
    $betCents = MoneyCalculator::toCents($bon['bet']);
?>
    <td><?= MoneyCalculator::formatEuro($betCents) ?></td>
<?php endforeach; ?>
```

---

## 9. API Responses

### Voorbeeld: JSON response met money values

**✅ NIEUW:**
```php
$breakdown = FinancialService::calculateFinancialBreakdown($inzetCents, $winstCents);

// JSON response met beide formats (centen EN euros)
echo json_encode([
    'success' => true,
    'data' => [
        // Voor JavaScript berekeningen: gebruik centen
        'inzet_cents' => $breakdown['inzet'],
        'commission_cents' => $breakdown['commission'],

        // Voor display: gebruik euros
        'inzet' => $breakdown['inzet_euros'],
        'commission' => $breakdown['commission_euros'],
        'formatted_commission' => MoneyCalculator::formatEuro($breakdown['commission'])
    ]
]);
```

---

## 10. JavaScript Interoperabiliteit

### Voorbeeld: Stuur centen naar JavaScript

**PHP:**
```php
$netHouseCents = 50000;  // €500.00
?>
<script>
const netHouseCents = <?= $netHouseCents ?>;  // 50000 (integer)
const netHouseEuros = netHouseCents / 100;     // 500.0

// Display
console.log(`€${netHouseEuros.toFixed(2)}`);   // "€500.00"
</script>
```

**Of met JSON:**
```php
<script>
const financial = <?= json_encode([
    'net_house_cents' => $breakdown['net_house'],
    'net_house_euros' => $breakdown['net_house_euros'],
    'formatted' => MoneyCalculator::formatEuro($breakdown['net_house'])
]) ?>;

console.log(financial.formatted);  // "€500,00"
</script>
```

---

## 11. Legacy Compatibiliteit

### Voorbeeld: Geleidelijke migratie

**Als je een oude functie hebt die floats verwacht:**
```php
// Oude functie (deprecated, maar nog in gebruik)
function oldCommissionCalc($bet, $winnings) {
    return $bet * 0.30;
}

// Moderniseer in twee stappen:
// Stap 1: Wrapper met nieuwe logica
function oldCommissionCalc($bet, $winnings) {
    // Gebruik nieuwe service
    $betCents = MoneyCalculator::toCents($bet);
    $winCents = MoneyCalculator::toCents($winnings);
    $breakdown = FinancialService::calculateCommission($betCents, $winCents);

    // Return float voor backwards compatibility
    return $breakdown['commission_euros'];
}

// Stap 2: Uiteindelijk vervangen door directe calls
```

---

## 12. Edge Cases

### Negatieve bedragen
```php
// Bijvoorbeeld: verlies voor speler
$saldoCents = -5000;  // -€50.00

// Absolute waarde
$absValue = MoneyCalculator::abs($saldoCents);  // 5000

// Display met minteken
$formatted = MoneyCalculator::formatEuro($saldoCents);  // "€-50,00"

// Of custom formatting
$sign = MoneyCalculator::isNegative($saldoCents) ? '–' : '+';
$abs = MoneyCalculator::abs($saldoCents);
$display = $sign . MoneyCalculator::formatEuro($abs);  // "–€50,00"
```

### Nul-waarden
```php
$amount = 0;
MoneyCalculator::isZero($amount);     // true
MoneyCalculator::formatEuro($amount);  // "€0,00"
```

### Hele grote bedragen
```php
// €1.000.000,00
$million = 100000000;  // centen

MoneyCalculator::formatEuro($million);  // "€1.000.000,00"
```

---

## 13. Testen

### Unit test voorbeeld

```php
function testMoneyPattern() {
    // Test 1: Float precision probleem oplossen
    $a = MoneyCalculator::toCents(0.1);
    $b = MoneyCalculator::toCents(0.2);
    $sum = MoneyCalculator::add($a, $b);
    assert($sum === 30);  // ✅ Exact 30 centen

    // Test 2: Commissie berekening
    $breakdown = FinancialService::calculateFinancialBreakdown(100000, 20000);
    assert($breakdown['commission'] === 30000);    // €300
    assert($breakdown['house_pot'] === 70000);     // €700
    assert($breakdown['net_house'] === 50000);     // €500

    // Test 3: Formatting
    $formatted = MoneyCalculator::formatEuro(125050);
    assert($formatted === "€1.250,50");

    echo "✅ All tests passed!";
}
```

---

## 14. Veelgemaakte Fouten

### ❌ FOUT: Vergeten te converteren
```php
// Database float direct gebruiken in berekening
$commission = $row['total_bet'] * 0.30;  // ❌ Float error!
```

### ✅ CORRECT:
```php
$betCents = MoneyCalculator::toCents($row['total_bet']);
$commissionCents = MoneyCalculator::percentage($betCents, 30.0);
```

---

### ❌ FOUT: Centen en euros mixen
```php
$betCents = MoneyCalculator::toCents(100);      // 10000 centen
$commission = $betCents * 0.30;                 // ❌ 3000 centen? Nee: 3000.0 float!
```

### ✅ CORRECT:
```php
$betCents = MoneyCalculator::toCents(100);
$commissionCents = MoneyCalculator::multiply($betCents, 0.30);  // ✅ 3000 centen
```

---

### ❌ FOUT: Floats vergelijken
```php
if (($winnings - $bet) > 0) { }  // ❌ Float comparison!
```

### ✅ CORRECT:
```php
$saldoCents = MoneyCalculator::subtract($winCents, $betCents);
if (MoneyCalculator::isPositive($saldoCents)) { }
```

---

## 📋 Checklist voor Refactoring

- [ ] Importeer `MoneyCalculator` en `FinancialService`
- [ ] Converteer database values naar centen met `toCents()`
- [ ] Vervang alle `* 0.30` / `* 0.70` door `FinancialService::calculateCommission()`
- [ ] Vervang `$a + $b` door `MoneyCalculator::add($a, $b)`
- [ ] Vervang `$a - $b` door `MoneyCalculator::subtract($a, $b)`
- [ ] Vervang `>= 0` checks door `isPositive()` / `isNegative()` / `isZero()`
- [ ] Gebruik `formatEuro()` voor display
- [ ] Test met bekende input/output waardes

---

**Tip**: Begin met één scherm (bijv. weekoverzicht), test grondig, en breid dan uit naar andere delen van de applicatie.

**Zie ook**: `FINANCIAL_REFACTOR_GUIDE.md` voor volledige architectuur en migratiestrategie.
