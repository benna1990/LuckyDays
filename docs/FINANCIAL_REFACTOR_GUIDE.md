# LuckyDays Financial Refactor - The Money Pattern

## 📋 Overzicht

Dit document beschrijft de refactoring van de financiële kern van LuckyDays naar **The Money Pattern**. Deze refactoring lost de volgende kritieke problemen op:

### ❌ Problemen VOOR refactoring:
1. **Float rekeningenfouten**: `0.1 + 0.2 ≠ 0.3` in binary floating point
2. **Inconsistente commissielogica**: UI rekent over inzet (30% van €1000 = €300), terwijl exports over winst rekenen
3. **Verspreid gedrag**: Financiële logica in views, API's, exports (elk net iets anders)
4. **Cumulatieve fouten**: Bij sommaties over veel rijen/dagen/weken stapelen kleine fouten op

### ✅ Oplossingen NA refactoring:
1. **Cent-based berekeningen**: Alle bedragen intern als integers (geen float errors!)
2. **Centrale configuratie**: `COMMISSION_BASE` constante bepaalt logica app-wide
3. **Eén waarheid**: `FinancialService` als single source of truth
4. **Robuuste math**: `MoneyCalculator` voor alle geldberekeningen

---

## 🏗️ Architectuur

```
┌─────────────────────────────────────────────────────────────┐
│                    LuckyDays Application                     │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌───────────────┐         ┌─────────────────────────────┐  │
│  │ Views/UI      │────────>│  FinancialService           │  │
│  │ - weekoverzicht│         │  - calculateCommission()    │  │
│  │ - dashboard   │         │  - calculateNetHouse()      │  │
│  │ - exports     │         │  - calculatePlayerVsHouse() │  │
│  └───────────────┘         └─────────────────────────────┘  │
│         │                             │                      │
│         │                             │ gebruikt             │
│         │                             ▼                      │
│         │                   ┌──────────────────────┐        │
│         └──────────────────>│  MoneyCalculator     │        │
│                             │  - toCents()          │        │
│                             │  - toEuros()          │        │
│                             │  - add() / subtract() │        │
│                             │  - multiply()         │        │
│                             │  - formatEuro()       │        │
│                             └──────────────────────┘        │
└─────────────────────────────────────────────────────────────┘
```

---

## 📦 Nieuwe Services

### 1. MoneyCalculator (`php/services/MoneyCalculator.php`)

**Doel**: Alle geldbedragen als integers (centen) behandelen om floating-point errors te vermijden.

**Kernfuncties**:
```php
// Conversie
MoneyCalculator::toCents(12.50)    → 1250  // Euro naar centen
MoneyCalculator::toEuros(1250)     → 12.50 // Centen naar euro

// Rekenkundig
MoneyCalculator::add(1250, 750)    → 2000  // €12.50 + €7.50 = €20.00
MoneyCalculator::subtract(2000, 750) → 1250  // €20.00 - €7.50 = €12.50
MoneyCalculator::multiply(1000, 0.30) → 300   // €10.00 * 30% = €3.00
MoneyCalculator::percentage(1000, 30) → 300   // 30% van €10.00 = €3.00

// Formatting
MoneyCalculator::formatEuro(1250)  → "€12,50"

// Vergelijken
MoneyCalculator::isPositive(1250)  → true
MoneyCalculator::isNegative(-500)  → true
MoneyCalculator::isZero(0)         → true
```

**Waarom integers?**
```php
// ❌ FOUT: Float precisie probleem
$a = 0.1 + 0.2;              // 0.30000000000000004
$b = 0.3;
var_dump($a === $b);         // false!!!

// ✅ CORRECT: Integer berekening
$a = 10 + 20;                // 30 centen
$b = 30;
var_dump($a === $b);         // true
```

---

### 2. FinancialService (`php/services/FinancialService.php`)

**Doel**: Centrale financiële bedrijfslogica met configureerbare commissieberekening.

#### 🎛️ Configuratie

```php
class FinancialService
{
    // BELANGRIJKE CONFIGURATIE:
    const COMMISSION_BASE = self::COMMISSION_ON_BET; // Of COMMISSION_ON_PROFIT
    const COMMISSION_PERCENTAGE = 30.0;
    const HOUSE_PERCENTAGE = 70.0;
}
```

**Commissie Basis Opties**:

1. **`COMMISSION_ON_BET`** (standaard):
   ```
   Inzet:      €1000
   Commissie:  30% van inzet    = €300
   Huispot:    70% van inzet    = €700
   Uitbetaald: €200
   Netto huis: €700 - €200      = €500
   ```

2. **`COMMISSION_ON_PROFIT`** (alternatief):
   ```
   Inzet:         €1000
   Uitbetaald:    €200
   Huis bruto:    €1000 - €200  = €800
   Commissie:     30% van €800  = €240
   Netto huis:    €800 - €240   = €560
   ```

#### 📊 Kernfuncties

```php
// Complete financiële breakdown
$breakdown = FinancialService::calculateFinancialBreakdown(
    $inzetCents,    // int: inzet in centen
    $winstCents     // int: winst in centen
);

// Returns:
[
    'inzet' => 100000,               // €1000.00 in centen
    'inzet_euros' => 1000.0,
    'winst' => 20000,                // €200.00 in centen
    'winst_euros' => 200.0,
    'commission' => 30000,           // €300.00
    'commission_euros' => 300.0,
    'house_pot' => 70000,            // €700.00
    'house_pot_euros' => 700.0,
    'net_house' => 50000,            // €500.00
    'net_house_euros' => 500.0,
    'commission_basis' => 'bet',
    'commission_percentage' => 30.0,
    'house_percentage' => 70.0
]

// Speler vs Huis perspectief
$pvh = FinancialService::calculatePlayerVsHouse($inzetCents, $winstCents);

// Returns:
[
    'speler_saldo' => 5000,          // Speler wint €50
    'speler_saldo_euros' => 50.0,
    'huis_saldo' => -5000,           // Huis verliest €50
    'huis_saldo_euros' => -50.0,
    'huis_wint' => false,
    'speler_wint' => true,
    'gelijk' => false
]
```

---

## 🔄 Migratie Stappenplan

### Stap 1: Begrijp je huidige code

**VOOR** (oude code):
```php
// ❌ Float berekeningen met inconsistenties
$total_bet = floatval($week_totals['total_bet']);
$total_winnings = floatval($week_totals['total_winnings']);
$commission = $total_bet * 0.30;           // In UI
$house_pot = $total_bet * 0.70;
$net_house = $house_pot - $total_winnings;
```

**Problemen**:
- Float precisie errors bij grote sommen
- Hardcoded percentages (`0.30`, `0.70`)
- Geen centrale logica (duplicatie in exports)

### Stap 2: Importeer de services

```php
// Bovenaan je bestand:
require_once 'php/services/MoneyCalculator.php';
require_once 'php/services/FinancialService.php';
```

### Stap 3: Vervang float-based berekeningen

**NA** (nieuwe code):
```php
// ✅ Integer-based met Money Pattern
// 1. Converteer database floats naar centen
$total_bet_cents = MoneyCalculator::toCents($week_totals['total_bet'] ?? 0);
$total_winnings_cents = MoneyCalculator::toCents($week_totals['total_winnings'] ?? 0);

// 2. Bereken met FinancialService (centrale logica)
$breakdown = FinancialService::calculateFinancialBreakdown(
    $total_bet_cents,
    $total_winnings_cents
);

// 3. Voor backwards compatibility met templates
$total_bet = $breakdown['inzet_euros'];
$total_winnings = $breakdown['winst_euros'];
$commission = $breakdown['commission_euros'];
$house_pot = $breakdown['house_pot_euros'];
$net_house = $breakdown['net_house_euros'];
```

**Voordelen**:
- ✅ Geen float errors
- ✅ Centrale configuratie (pas `COMMISSION_BASE` één keer aan)
- ✅ Consistente berekeningen overal
- ✅ Backwards compatible met bestaande templates

---

## 📝 Voorbeelden

### Voorbeeld 1: Weekoverzicht commissie

**VOOR** (`weekoverzicht.php:34-39`):
```php
$total_bet = floatval($week_totals['total_bet'] ?? 0);
$total_winnings = floatval($week_totals['total_winnings'] ?? 0);
$commission = $total_bet * 0.30;
$house_pot = $total_bet * 0.70;
$net_house = $house_pot - $total_winnings;
```

**NA** (`weekoverzicht.php:36-49`):
```php
$total_bet_cents = MoneyCalculator::toCents($week_totals['total_bet'] ?? 0);
$total_winnings_cents = MoneyCalculator::toCents($week_totals['total_winnings'] ?? 0);

$financialBreakdown = FinancialService::calculateFinancialBreakdown(
    $total_bet_cents,
    $total_winnings_cents
);

$total_bet = $financialBreakdown['inzet_euros'];
$total_winnings = $financialBreakdown['winst_euros'];
$commission = $financialBreakdown['commission_euros'];
$house_pot = $financialBreakdown['house_pot_euros'];
$net_house = $financialBreakdown['net_house_euros'];
```

### Voorbeeld 2: CSV Export (Multi-winkel)

**VOOR** (inconsistent met UI):
```php
$winkel_total_bet = 0;
$winkel_total_winnings = 0;
foreach ($winkel_stats as $stat) {
    $winkel_total_bet += floatval($stat['total_bet']);
    $winkel_total_winnings += floatval($stat['total_winnings']);
}
$winkel_huis_saldo = $winkel_total_bet - $winkel_total_winnings;
$winkel_commissie = calculateCommission($winkel_huis_saldo); // ❌ Andere logica dan UI!
```

**NA** (consistent met UI):
```php
$winkel_total_bet_cents = 0;
$winkel_total_winnings_cents = 0;
foreach ($winkel_stats as $stat) {
    $winkel_total_bet_cents = MoneyCalculator::add(
        $winkel_total_bet_cents,
        MoneyCalculator::toCents($stat['total_bet'])
    );
    $winkel_total_winnings_cents = MoneyCalculator::add(
        $winkel_total_winnings_cents,
        MoneyCalculator::toCents($stat['total_winnings'])
    );
}

$winkel_breakdown = FinancialService::calculateFinancialBreakdown(
    $winkel_total_bet_cents,
    $winkel_total_winnings_cents
);

$winkel_total_bet = $winkel_breakdown['inzet_euros'];
$winkel_total_winnings = $winkel_breakdown['winst_euros'];
$winkel_commissie = [
    'bruto' => MoneyCalculator::toEuros(
        MoneyCalculator::subtract($winkel_total_bet_cents, $winkel_total_winnings_cents)
    ),
    'commissie' => $winkel_breakdown['commission_euros'],
    'netto' => $winkel_breakdown['net_house_euros']
];
```

### Voorbeeld 3: Oude `functions.php` commissie vervangen

**VOOR** (`functions.php:159-171`):
```php
function calculateCommissionSplit($bet, $winnings) {
    $bet = floatval($bet);
    $winnings = floatval($winnings);
    $commission = $bet * 0.30;
    $house_pot = $bet * 0.70;
    $net_house = $house_pot - $winnings;
    return [
        'commission' => $commission,
        'house_pot' => $house_pot,
        'net_house' => $net_house
    ];
}
```

**NA** (gebruik FinancialService):
```php
function calculateCommissionSplit($bet, $winnings) {
    // Gebruik de nieuwe FinancialService voor consistentie
    return FinancialService::calculateCommissionLegacy($bet, $winnings);

    // Of moderniseer volledig:
    // $betCents = MoneyCalculator::toCents($bet);
    // $winCents = MoneyCalculator::toCents($winnings);
    // $breakdown = FinancialService::calculateCommission($betCents, $winCents);
    // return [
    //     'commission' => $breakdown['commission_euros'],
    //     'house_pot' => $breakdown['house_pot_euros'],
    //     'net_house' => $breakdown['net_house_euros']
    // ];
}
```

---

## 🔧 Commissie Basis Wijzigen

### Scenario: Je wilt commissie over WINST i.p.v. INZET

**Stap 1**: Open `php/services/FinancialService.php`

**Stap 2**: Wijzig de constante:
```php
// VOOR:
const COMMISSION_BASE = self::COMMISSION_ON_BET;

// NA:
const COMMISSION_BASE = self::COMMISSION_ON_PROFIT;
```

**Stap 3**: Save en klaar! Alle schermen, exports en rapportages gebruiken nu de nieuwe logica.

**Test het verschil**:
```php
// Test scenario: €1000 inzet, €200 uitbetaling

// Met COMMISSION_ON_BET:
$breakdown = FinancialService::calculateFinancialBreakdown(100000, 20000);
// commission_euros = 300.0 (30% van €1000)
// net_house_euros = 500.0

// Met COMMISSION_ON_PROFIT:
$breakdown = FinancialService::calculateFinancialBreakdown(100000, 20000);
// commission_euros = 240.0 (30% van €800 huissaldo)
// net_house_euros = 560.0
```

---

## ✅ Voordelen van deze Refactoring

| Aspect | VOOR | NA |
|--------|------|-----|
| **Precisie** | Float errors bij grote sommen | Exacte cent-precisie |
| **Consistentie** | Verschillende logica per scherm | Één centrale truth |
| **Onderhoudbaarheid** | Wijzig 10+ plekken | Wijzig 1 constante |
| **Testbaarheid** | Moeilijk te testen | Gemakkelijk unit tests |
| **Audit compliance** | Inconsistente exports | Identieke berekeningen |
| **Configuratie** | Hardcoded `0.30` overal | Centrale `COMMISSION_PERCENTAGE` |
| **Type safety** | Geen strict types | `declare(strict_types=1)` |

---

## 🚀 Volgende Stappen

### Prioriteit 1: Kritieke financiële berekeningen
- [x] `weekoverzicht.php` (GEDAAN)
- [ ] `api/export_week_csv.php`
- [ ] `api/export_week_excel.php`
- [ ] `overzichten.php`
- [ ] `functions.php` → `calculateCommissionSplit()` vervangen

### Prioriteit 2: Andere financiële schermen
- [ ] `dashboard.php`
- [ ] `spelers.php`
- [ ] `api/get_dashboard_data.php`

### Prioriteit 3: Unit tests schrijven
```php
// Voorbeeld test
function testCommissionCalculation() {
    $result = FinancialService::calculateCommission(100000, 20000);
    assert($result['commission'] === 30000); // €300
    assert($result['net_house'] === 50000);  // €500
}
```

---

## 📞 Vragen & Troubleshooting

### Vraag: "Mijn oude code verwacht floats, maar de service geeft integers?"

**Antwoord**: Gebruik de `_euros` suffix in de return array:
```php
$breakdown = FinancialService::calculateFinancialBreakdown($inzetCents, $winstCents);
$commission_float = $breakdown['commission_euros'];  // Float voor display
$commission_cents = $breakdown['commission'];        // Int voor berekeningen
```

### Vraag: "Hoe zit het met afronden?"

**Antwoord**: `MoneyCalculator::multiply()` gebruikt banker's rounding (half naar even):
```php
MoneyCalculator::multiply(1001, 0.30); // 300 (rounds to 300)
MoneyCalculator::multiply(1002, 0.30); // 301 (rounds to 301)
```

### Vraag: "Moet ik de database schema aanpassen?"

**Antwoord**: **NEE!** De database blijft NUMERIC/DECIMAL gebruiken. De conversie naar centen gebeurt alleen in PHP:
```php
// Database: NUMERIC(10,2) → "1250.50"
$dbValue = floatval($row['total_bet']);  // 1250.50
$cents = MoneyCalculator::toCents($dbValue); // 125050 (int)
```

---

## 📚 Referenties

- [Martin Fowler - Money Pattern](https://martinfowler.com/eaaCatalog/money.html)
- [IEEE 754 Floating Point Problems](https://0.30000000000000004.com/)
- Audit rapport: `Audit_Bevindingen_Details.txt` (regel 11-13, 51-53)

---

**Versie**: 1.0.0
**Datum**: 2024-12-04
**Auteur**: Senior Software Architect
**Status**: ✅ Weekoverzicht.php gemigreerd, ready for production
