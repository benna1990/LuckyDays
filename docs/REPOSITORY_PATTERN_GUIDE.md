# Repository Pattern - Data Integriteit Refactoring

## 📋 Overzicht

Dit document beschrijft de refactoring van de data access layer naar het **Repository Pattern**. Deze refactoring lost de volgende data-integriteits problemen op:

### ❌ Problemen VOOR refactoring:

1. **NULL-waarden**: `SUM()` zonder `COALESCE` gaf `NULL` terug bij geen data
2. **Dubbele tellingen**: JOIN met rijen telde bonnen meerdere keren
3. **Inconsistente filters**: `shopId` filter gebruikte soms `!== null`, soms `truthy check`
4. **SQL duplicatie**: Zelfde queries herhaald in views, API's en exports
5. **Geen type safety**: Geen strict types, geen parameter validatie

### ✅ Oplossingen NA refactoring:

1. **Altijd COALESCE**: Elke `SUM()` heeft `COALESCE(SUM(...), 0)` → nooit NULL
2. **DISTINCT counts**: `COUNT(DISTINCT b.id)` voorkomt dubbele tellingen
3. **Strikte filtering**: `$shopId !== null` check (shopId=0 is geldige winkel!)
4. **Centrale queries**: Alle SQL in `LotteryRepository` class
5. **Type safety**: `declare(strict_types=1)` en type hints overal

---

## 🏗️ Architectuur

```
┌─────────────────────────────────────────────────────────────────┐
│                    LuckyDays Application                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌──────────────┐       ┌────────────────────────────────────┐  │
│  │ Views/UI     │──────>│  LotteryRepository                 │  │
│  │ - weekoverzicht│      │  - getWeekTotals()                │  │
│  │ - dashboard  │       │  - getWeekStats()                  │  │
│  │ - API's      │       │  - getDayStats()                   │  │
│  └──────────────┘       │  - getBonnenByDate()               │  │
│         │               │  - getPlayersByDate()              │  │
│         │               │  - getDailyStatsForRange()         │  │
│         │               └────────────────────────────────────┘  │
│         │                             │                          │
│         │                             │ SQL queries              │
│         │                             ▼                          │
│         │                   ┌──────────────────┐                │
│         └──────────────────>│   PostgreSQL DB  │                │
│                             │   (via pg_query) │                │
│                             └──────────────────┘                │
└─────────────────────────────────────────────────────────────────┘
```

**Voordelen**:
- 🎯 **Single Responsibility**: Repository doet alleen data access
- 🔒 **Encapsulation**: SQL queries zijn verborgen in repository
- ♻️ **Reusability**: Herbruikbare queries in alle views/API's
- 🧪 **Testability**: Eenvoudig unit tests schrijven
- 🛡️ **Type Safety**: Strikte types en defensive programming

---

## 📦 LotteryRepository Class

**Bestand**: [`php/repositories/LotteryRepository.php`](php/repositories/LotteryRepository.php)

### Constructor

```php
$repo = new LotteryRepository($conn);
```

**Parameter**:
- `$conn`: PostgreSQL database connectie (resource)

---

### Methode: `getWeekTotals()`

Haal week totalen op voor een specifieke periode.

**Signature**:
```php
public function getWeekTotals(
    string $startDate,   // 'YYYY-MM-DD'
    string $endDate,     // 'YYYY-MM-DD'
    ?int $shopId = null  // null = alle winkels, 0+ = specifieke winkel
): array
```

**Returns**:
```php
[
    'total_bons' => 150,        // int (aantal bonnen)
    'total_rijen' => 450,       // int (aantal rijen)
    'total_bet' => '15000.00',  // string (NUMERIC uit DB)
    'total_winnings' => '3500.00',
    'saldo' => '11500.00'       // winst - inzet (speler perspectief)
]
```

**Belangrijke Features**:
- ✅ `COUNT(DISTINCT b.id)` voorkomt dubbele tellingen
- ✅ `COALESCE(SUM(...), 0)` voorkomt NULL
- ✅ `LEFT JOIN rijen` telt bonnen zonder rijen mee in total_bons
- ✅ Strikte `shopId !== null` check

**Voorbeeld**:
```php
$repo = new LotteryRepository($conn);

// Alle winkels
$totals = $repo->getWeekTotals('2024-01-01', '2024-01-07', null);

// Specifieke winkel
$totals = $repo->getWeekTotals('2024-01-01', '2024-01-07', 1);

// Gebruik met Money Pattern
$betCents = MoneyCalculator::toCents($totals['total_bet']);
$winCents = MoneyCalculator::toCents($totals['total_winnings']);
$breakdown = FinancialService::calculateFinancialBreakdown($betCents, $winCents);
```

---

### Methode: `getWeekStats()`

Haal week statistieken per speler op.

**Signature**:
```php
public function getWeekStats(
    string $startDate,
    string $endDate,
    ?int $shopId = null
): array // Array van speler statistieken
```

**Returns**:
```php
[
    [
        'id' => 1,
        'name' => 'Jan Jansen',
        'color' => '#3B82F6',
        'total_bons' => 10,
        'total_rijen' => 30,
        'total_bet' => '100.00',
        'total_winnings' => '50.00',
        'saldo' => '-50.00'  // verlies voor speler
    ],
    // ... meer spelers
]
```

**Belangrijke Features**:
- ✅ Alleen spelers met bonnen EN rijen (`HAVING COUNT(...) > 0`)
- ✅ Gesorteerd op saldo (DESC)
- ✅ COALESCE op alle sommen

**Voorbeeld**:
```php
$stats = $repo->getWeekStats('2024-01-01', '2024-01-07', 1);

foreach ($stats as $player) {
    echo "{$player['name']}: €{$player['saldo']}\n";
}
```

---

### Methode: `getDayStats()`

Haal dag statistieken op voor een specifieke datum.

**Signature**:
```php
public function getDayStats(
    string $date,        // 'YYYY-MM-DD'
    ?int $shopId = null
): array
```

**Returns**:
```php
[
    'total_bons' => 10,
    'total_players' => 5,
    'total_rijen' => 30,
    'total_bet' => '100.00',
    'total_winnings' => '50.00'
]
```

**Voorbeeld**:
```php
$dayStats = $repo->getDayStats('2024-01-01', 1);

if ($dayStats['total_bons'] > 0) {
    echo "Vandaag {$dayStats['total_bons']} bonnen!";
}
```

---

### Methode: `getBonnenByDate()`

Haal alle bonnen op voor een specifieke datum.

**Signature**:
```php
public function getBonnenByDate(
    string $date,
    ?int $shopId = null
): array // Array van bonnen
```

**Returns**:
```php
[
    [
        'id' => 1,
        'player_id' => 1,
        'player_name' => 'Jan',
        'winkel_id' => 1,
        'winkel_name' => 'Dapper',
        'player_color' => '#FF9F40',
        'date' => '2024-01-01',
        'bonnummer' => '12345',
        'rijen_count' => 5,
        'total_bet' => '50.00',
        'total_winnings' => '100.00',
        // ... andere bon velden
    ],
    // ... meer bonnen
]
```

**Belangrijke Features**:
- ✅ Subqueries voor aggregaties (geen JOIN duplicaten!)
- ✅ Alleen bonnen met minimaal 1 rij (`EXISTS` check)
- ✅ player_color wordt toegevoegd op basis van winkel

---

### Methode: `getPlayersByDate()`

Haal spelers op die actief waren op een datum.

**Signature**:
```php
public function getPlayersByDate(
    string $date,
    ?int $shopId = null
): array
```

**Returns**:
```php
[
    ['id' => 1, 'name' => 'Jan', 'color' => '#3B82F6'],
    ['id' => 2, 'name' => 'Piet', 'color' => '#EF4444'],
    // ...
]
```

---

### Methode: `getDailyStatsForRange()`

Haal statistieken per dag op voor een week range.

**Signature**:
```php
public function getDailyStatsForRange(
    string $startDate,
    string $endDate,
    ?int $shopId = null
): array // Associative array met datum als key
```

**Returns**:
```php
[
    '2024-01-01' => [
        'total_bons' => 10,
        'total_players' => 5,
        'total_bet' => '100.00',
        // ...
    ],
    '2024-01-02' => [
        'total_bons' => 15,
        // ...
    ],
    // ... elke dag
]
```

**Voorbeeld**:
```php
$dailyStats = $repo->getDailyStatsForRange('2024-01-01', '2024-01-07', 1);

foreach ($dailyStats as $date => $stats) {
    echo "$date: {$stats['total_bons']} bonnen\n";
}
```

---

## 🔄 Migratie Voorbeelden

### Voorbeeld 1: weekoverzicht.php

**VOOR** (oude code):
```php
// Direct SQL in view
$week_stats = getWeekStats($conn, $week_range['start'], $week_range['end'], $selectedWinkel);
$week_totals = getWeekTotals($conn, $week_range['start'], $week_range['end'], $selectedWinkel);

// Defensive null checks nodig
$week_stats = $week_stats ?: [];
$week_totals = $week_totals ?: ['total_bet' => 0, 'total_winnings' => 0];
```

**NA** (nieuwe code):
```php
// Repository Pattern
$lotteryRepo = new LotteryRepository($conn);

// Repository garandeert nooit NULL!
$week_stats = $lotteryRepo->getWeekStats($week_range['start'], $week_range['end'], $selectedWinkel);
$week_totals = $lotteryRepo->getWeekTotals($week_range['start'], $week_range['end'], $selectedWinkel);

// Extra defensive (optioneel, repository is al veilig)
$week_stats = $week_stats ?: [];
```

**Voordelen**:
- ✅ Geen SQL in view
- ✅ Consistent met andere schermen
- ✅ Testbaar
- ✅ Type safe

---

### Voorbeeld 2: Dag statistieken loop

**VOOR**:
```php
$current = new DateTime($week_range['start']);
$end = new DateTime($week_range['end']);
while ($current <= $end):
    $dayStr = $current->format('Y-m-d');
    $dayStats = getDayStats($conn, $dayStr);  // Oude functie
    $dayHuisSaldo = floatval($dayStats['total_bet']) - floatval($dayStats['total_winnings']);
    // ...
```

**NA**:
```php
$current = new DateTime($week_range['start']);
$end = new DateTime($week_range['end']);
while ($current <= $end):
    $dayStr = $current->format('Y-m-d');
    $dayStats = $lotteryRepo->getDayStats($dayStr, $selectedWinkel);  // Repository

    // Gebruik Money Pattern voor precisie
    $dayBetCents = MoneyCalculator::toCents($dayStats['total_bet']);
    $dayWinCents = MoneyCalculator::toCents($dayStats['total_winnings']);
    $dayHuisSaldoCents = MoneyCalculator::subtract($dayBetCents, $dayWinCents);
    $dayHuisSaldo = MoneyCalculator::toEuros($dayHuisSaldoCents);
    // ...
```

**Voordelen**:
- ✅ Repository + Money Pattern combinatie
- ✅ Geen float errors
- ✅ Consistent met rest van app

---

### Voorbeeld 3: API Endpoint

**Oude API** (`api/get_dashboard_data.php`):
```php
// Direct SQL in API
$query = "SELECT ... FROM bons b LEFT JOIN rijen r ...";
$result = pg_query_params($conn, $query, [$date]);
// ... handmatige null checks, type conversies, etc.
```

**Nieuwe API**:
```php
require_once '../php/repositories/LotteryRepository.php';

$repo = new LotteryRepository($conn);
$dayStats = $repo->getDayStats($date, $shopId);

// Clean JSON response
echo json_encode([
    'success' => true,
    'data' => $dayStats
]);
```

**Voordelen**:
- ✅ Minimale code
- ✅ Herbruikbare logic
- ✅ Consistent met andere endpoints

---

## 🎯 Belangrijke Patronen

### Pattern 1: Nooit NULL

**Probleem VOOR**:
```sql
SELECT SUM(bet) as total_bet FROM rijen;
-- Returns: NULL als geen rijen (database gedrag)
```

**Oplossing NA**:
```sql
SELECT COALESCE(SUM(bet), 0) as total_bet FROM rijen;
-- Returns: 0 als geen rijen (altijd numeriek)
```

**In PHP**:
```php
// Repository garandeert dit:
$totals = $repo->getWeekTotals($start, $end, $shopId);
// $totals['total_bet'] is altijd string "0" of hoger, NOOIT NULL
```

---

### Pattern 2: Strikte ShopId Filter

**Probleem VOOR**:
```php
if ($shopId) {  // ❌ Falsy check!
    $query .= " AND winkel_id = $1";
}
// shopId=0 wordt gezien als "geen filter" → verkeerd!
```

**Oplossing NA**:
```php
if ($shopId !== null) {  // ✅ Strikte null check
    $query .= " AND winkel_id = $1";
}
// shopId=0 is nu een geldige winkel filter
```

---

### Pattern 3: Geen Dubbele Tellingen

**Probleem VOOR**:
```sql
SELECT COUNT(b.id) as total_bons
FROM bons b
JOIN rijen r ON r.bon_id = b.id
-- Als bon 3 rijen heeft, wordt deze 3x geteld!
```

**Oplossing NA**:
```sql
SELECT COUNT(DISTINCT b.id) as total_bons
FROM bons b
LEFT JOIN rijen r ON r.bon_id = b.id
-- DISTINCT zorgt dat elke bon 1x telt
-- LEFT JOIN telt ook bonnen zonder rijen
```

---

### Pattern 4: Subqueries voor Aggregaties

**Probleem VOOR**:
```sql
SELECT b.*, SUM(r.bet) as total_bet
FROM bons b
JOIN rijen r ON r.bon_id = b.id
GROUP BY b.id
-- GROUP BY kan leiden tot missing columns errors
```

**Oplossing NA**:
```sql
SELECT b.*,
    (SELECT COALESCE(SUM(bet), 0) FROM rijen WHERE bon_id = b.id) as total_bet,
    (SELECT COUNT(*) FROM rijen WHERE bon_id = b.id) as rijen_count
FROM bons b
-- Geen GROUP BY nodig, geen duplicaten mogelijk
```

---

## 🧪 Testing

### Run de tests:

```bash
php test_repository.php
```

**Expected output**:
```
=== Repository Setup Tests ===
✅ PASS: Repository can be instantiated
✅ PASS: Repository health check passes

=== getWeekTotals Tests ===
✅ PASS: getWeekTotals returns array with required keys
✅ PASS: getWeekTotals never returns NULL for numeric fields
✅ PASS: getWeekTotals with shopId filter
✅ PASS: getWeekTotals with shopId = 0 (edge case)

... (13 tests)

🎉 ALL TESTS PASSED! LotteryRepository is working correctly.

✅ COALESCE werkt correct (geen NULL waarden)
✅ ShopId filtering is strikt (0 is geldige winkel)
✅ Geen dubbele tellingen (DISTINCT gebruikt)
✅ Defensive programming overal
```

---

## 📋 Volgende Stappen

### ✅ Klaar
- [x] LotteryRepository gebouwd
- [x] weekoverzicht.php gemigreerd
- [x] 13 passing unit tests
- [x] Documentatie compleet

### 🎯 TODO (Hoge Prioriteit)

Migreer deze bestanden naar Repository Pattern:

1. **`dashboard.php`**
   - Gebruik `getDayStats()` voor dagoverzicht
   - Gebruik `getBonnenByDate()` voor bonnen lijst

2. **`api/get_dashboard_data.php`**
   - Replace directe SQL met repository calls

3. **`api/get_bonnen_overzicht.php`**
   - Gebruik `getBonnenByDate()`

4. **`overzichten.php`**
   - Gebruik repository voor alle queries

5. **`spelers.php`**
   - Gebruik `getWeekStats()` en `getPlayersByDate()`

### 📝 Pattern voor migratie:

```php
// 1. Importeer repository
require_once 'php/repositories/LotteryRepository.php';

// 2. Instantieer
$repo = new LotteryRepository($conn);

// 3. Vervang oude calls
// VOOR: $stats = getWeekStats($conn, $start, $end, $shopId);
// NA:   $stats = $repo->getWeekStats($start, $end, $shopId);

// 4. Test!
```

---

## 🎉 Resultaat

| Aspect | Voor | Na | Impact |
|--------|------|-----|--------|
| **NULL safety** | Crashes bij geen data | Altijd 0 | 🟢 Hoog |
| **Dubbele tellingen** | Bonnen 2-3x geteld | DISTINCT gebruikt | 🟢 Hoog |
| **ShopId filtering** | Truthy check (bug) | Strikte `!== null` | 🟢 Hoog |
| **SQL duplicatie** | 5+ plekken | 1 repository | 🟢 Hoog |
| **Onderhoudbaarheid** | Wijzig 5+ bestanden | Wijzig 1 class | 🟢 Hoog |
| **Testbaarheid** | Niet testbaar | 13 unit tests | 🟢 Hoog |
| **Type safety** | Geen types | strict_types=1 | 🟢 Medium |

---

## 📚 Referenties

- [Repository Pattern (Martin Fowler)](https://martinfowler.com/eaaCatalog/repository.html)
- [Defensive Programming Best Practices](https://en.wikipedia.org/wiki/Defensive_programming)
- Audit rapport: `Audit_Bevindingen_Details.txt` (regel 82-133)

---

**Versie**: 1.0.0
**Datum**: 2024-12-04
**Auteur**: Senior Software Architect
**Status**: ✅ Production ready, 13/13 tests passing
