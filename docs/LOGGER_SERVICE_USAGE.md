# LoggerService Usage Guide

**Versie**: 1.0.0
**Datum**: 2024-12-04

---

## Overzicht

De nieuwe `LoggerService` class lost de volgende problemen op:

1. ✅ **Forensische voor/na tracking** - Automatische diff berekening in JSON
2. ✅ **File-based error logging** - Kritieke fouten naar `/logs/error.log`
3. ✅ **Geen error suppression** - Alle failures gooien exceptions
4. ✅ **Duplicate preventie** - Automatische detectie van duplicate logs binnen 5 seconden
5. ✅ **Export functionaliteit** - CSV export voor rapportage

---

## 🔴 Probleem: Duplicate Logs

### Root Cause

In **`api/set_bon_checked.php`** (regel 40-43):

```php
if ($res) {
    logBonAction($conn, $bonId, $checked ? 'checked' : 'unchecked', ['user' => $user]);  // ❌ Schrijft naar bon_logs
    add_audit_log($conn, $checked ? 'bon_checked' : 'bon_unchecked', 'bon', $bonId, [    // ❌ Schrijft naar audit_log
        'checked_by' => $user
    ]);
    echo json_encode(['success' => true]);
}
```

Dit creëert **2 log entries** per actie:
- `logBonAction()` → `bon_logs` tabel
- `add_audit_log()` → `audit_log` tabel

### Waarom 11 duplicates?

Als je UI meerdere AJAX calls maakt (door bugs, double-clicks, of page refreshes), krijg je:
- 1 user actie → 2 logs per call → 11 duplicates betekent 5-6 AJAX calls tegelijk

### Oplossing: Gebruik ALLEEN LoggerService

```php
// ✅ NIEUW - Eén log call
$logger = new LoggerService($conn);
$logger->logChange(
    $userId,
    'bon_checked',
    'bon',
    $bonId,
    ['checked_at' => null, 'checked_by' => null],  // old
    ['checked_at' => date('Y-m-d H:i:s'), 'checked_by' => $user],  // new
);
```

LoggerService heeft **ingebouwde duplicate detectie** die checkt of dezelfde log al binnen 5 seconden geschreven is.

---

## ✅ Gebruik in `update_rij_numbers.php`

### VOOR (met oude systeem):

```php
// ❌ PROBLEEM: Dubbele logging + error suppression
foreach ($changes as $change) {
    $rijId = intval($change['rij_id']);
    $numbers = $change['numbers'];

    // ... validatie ...

    // Haal oude data op
    $result = pg_query_params($conn,
        "SELECT r.id, r.bon_id, r.bet, r.numbers, r.matches, r.multiplier, r.winnings, b.date
         FROM rijen r JOIN bons b ON r.bon_id = b.id WHERE r.id = $1",
        [$rijId]
    );
    $rij = pg_fetch_assoc($result);

    // ... bereken matches, multiplier, winnings ...

    // Update query
    $updateResult = pg_query_params($conn,
        "UPDATE rijen SET numbers = $1, game_type = $2, matches = $3, multiplier = $4, winnings = $5 WHERE id = $6",
        [$numbersStr, $gameType, $matches, $multiplier, $winnings, $rijId]
    );

    // ❌ Dubbele logs
    logBonAction($conn, $rij['bon_id'], 'update_row', [...]);  // bon_logs
    add_audit_log($conn, 'bon_row_update', 'bon', $rij['bon_id'], [...]);  // audit_log
}
```

### NA (met LoggerService):

```php
<?php
session_start();

header('Content-Type: application/json');

require_once '../config.php';
require_once '../functions.php';
require_once '../php/services/LoggerService.php';  // ✅ NIEUW

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

// ✅ Instantieer LoggerService
$logger = new LoggerService($conn, __DIR__ . '/../logs');
$userId = $_SESSION['user_id'] ?? 0;

$input = json_decode(file_get_contents('php://input'), true);

// Support beide formaten: single update of batch updates
if (isset($input['rij_id']) && isset($input['numbers'])) {
    $numbersArray = is_string($input['numbers'])
        ? explode(',', $input['numbers'])
        : $input['numbers'];
    $changes = [[
        'rij_id' => $input['rij_id'],
        'numbers' => $numbersArray
    ]];
} else {
    $changes = $input['changes'] ?? [];
}

if (empty($changes)) {
    echo json_encode(['success' => false, 'error' => 'Geen wijzigingen']);
    exit;
}

try {
    $beginResult = pg_query($conn, "BEGIN");
    if (!$beginResult) {
        throw new Exception('Database transaction failed');
    }

    foreach ($changes as $change) {
        $rijId = intval($change['rij_id']);
        $numbers = $change['numbers'];

        if (!is_array($numbers) || empty($numbers)) {
            throw new Exception('Ongeldige nummers');
        }

        // Validatie
        $validatedNumbers = [];
        foreach ($numbers as $num) {
            $intNum = intval($num);
            if ($intNum < 1 || $intNum > 80) {
                throw new Exception('Nummers moeten tussen 1 en 80 zijn');
            }
            $validatedNumbers[] = $intNum;
        }
        $numbers = $validatedNumbers;

        // ✅ STAP 1: Haal OUDE waarden op VOOR de update
        $result = pg_query_params($conn,
            "SELECT r.id, r.bon_id, r.bet, r.numbers, r.matches, r.multiplier, r.winnings, b.date
             FROM rijen r JOIN bons b ON r.bon_id = b.id WHERE r.id = $1",
            [$rijId]
        );

        if (!$result) {
            throw new Exception('Database query failed');
        }

        $rij = pg_fetch_assoc($result);

        if (!$rij) {
            throw new Exception('Rij niet gevonden');
        }

        // ✅ Bewaar oude waarden
        $oldNumbers = array_filter(array_map('intval', explode(',', $rij['numbers'] ?? '')));
        $oldValues = [
            'numbers' => $oldNumbers,
            'game_type' => $rij['game_type'] ?? null,
            'matches' => intval($rij['matches']),
            'multiplier' => floatval($rij['multiplier']),
            'winnings' => floatval($rij['winnings'])
        ];

        // Bereken nieuwe waarden
        $gameType = count($numbers) . '-getallen';
        $numbersStr = implode(',', $numbers);

        $winningNumbers = getWinningNumbersFromDatabase($rij['date'], $conn);
        $matches = 0;
        $multiplier = 0;
        $winnings = 0;

        if ($winningNumbers && !empty($winningNumbers)) {
            foreach ($numbers as $num) {
                if (in_array($num, $winningNumbers)) {
                    $matches++;
                }
            }
            $multiplier = getMultiplier($gameType, $matches);
            $winnings = floatval($rij['bet']) * $multiplier;
        }

        // ✅ Bewaar nieuwe waarden
        $newValues = [
            'numbers' => $numbers,
            'game_type' => $gameType,
            'matches' => $matches,
            'multiplier' => $multiplier,
            'winnings' => $winnings
        ];

        // ✅ STAP 2: Voer de update uit
        $updateResult = pg_query_params($conn,
            "UPDATE rijen SET numbers = $1, game_type = $2, matches = $3, multiplier = $4, winnings = $5 WHERE id = $6",
            [$numbersStr, $gameType, $matches, $multiplier, $winnings, $rijId]
        );

        if (!$updateResult) {
            throw new Exception('Database update mislukt: ' . pg_last_error($conn));
        }

        // ✅ STAP 3: Log de wijziging met voor/na diff
        // LoggerService berekent automatisch de diff!
        $logger->logChange(
            $userId,
            'update_rij_numbers',
            'rij',
            $rijId,
            $oldValues,
            $newValues,
            [
                'bon_id' => $rij['bon_id'],
                'bet' => floatval($rij['bet']),
                'date' => $rij['date']
            ]
        );

        // ✅ GEEN logBonAction(), GEEN add_audit_log() meer!
        // LoggerService doet alles in één keer, met duplicate preventie
    }

    $commitResult = pg_query($conn, "COMMIT");
    if (!$commitResult) {
        throw new Exception('Commit failed');
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");

    // ✅ Log errors naar bestand
    try {
        $logger->logError(
            "Update rij numbers failed: " . $e->getMessage(),
            $e->getTraceAsString(),
            [
                'input' => $input,
                'user_id' => $userId,
                'session_id' => session_id()
            ]
        );
    } catch (Exception $logError) {
        // Als logging faalt, log naar PHP error log
        error_log("Failed to log error: " . $logError->getMessage());
    }

    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
```

---

## Wat doet LoggerService anders?

### 1. Automatische Diff Berekening

```php
$oldValues = ['numbers' => [1, 2, 3], 'bet' => '5.00', 'matches' => 0];
$newValues = ['numbers' => [4, 5, 6], 'bet' => '5.00', 'matches' => 3];

$logger->logChange($userId, 'update_rij', 'rij', $rijId, $oldValues, $newValues);
```

**Result in `audit_log.details`**:

```json
{
  "old_values": {
    "numbers": [1, 2, 3],
    "bet": "5.00",
    "matches": 0
  },
  "new_values": {
    "numbers": [4, 5, 6],
    "bet": "5.00",
    "matches": 3
  },
  "diff": {
    "numbers": {
      "old": [1, 2, 3],
      "new": [4, 5, 6]
    },
    "matches": {
      "old": 0,
      "new": 3
    }
  },
  "changed_fields": ["numbers", "matches"],
  "change_count": 2
}
```

**Voordelen**:
- Je ziet **exact** wat er gewijzigd is
- `changed_fields` = quick overview
- `bet` is NIET in de diff (want ongewijzigd)

### 2. Duplicate Preventie

LoggerService checkt automatisch:
- Zelfde `action`, `entity_type`, `entity_id`
- Binnen 5 seconden
- Met identieke `diff`

Als duplicate: **return 0** (geen nieuwe log)

### 3. Error Logging naar Bestand

```php
try {
    $result = riskyDatabaseOperation();
} catch (Exception $e) {
    $logger->logError(
        'Database operation failed',
        $e->getTraceAsString(),
        ['operation' => 'update_rij', 'rij_id' => $rijId]
    );
    throw $e;  // Re-throw voor error handling
}
```

**Result in `/logs/error.log`**:

```
[2024-12-04 14:32:15] [ERROR] [Session: abc123] [User: admin] [IP: 192.168.1.100]
Database operation failed

Stack Trace:
#0 /path/to/file.php(123): function()
#1 /path/to/file.php(456): anotherFunction()
...

Context:
{
  "operation": "update_rij",
  "rij_id": 42
}
--------------------------------------------------------------------------------
```

### 4. Geen Error Suppression

```php
// ❌ OUD (audit_log.php regel 28)
@pg_query_params($conn, ...);  // Faalt stil

// ✅ NIEUW
$result = pg_query_params($conn, ...);
if ($result === false) {
    throw new \RuntimeException("Failed to write audit log: " . pg_last_error($conn));
}
```

**Als logging faalt → je ziet het direct!**

---

## Export Functionaliteit

### CSV Export

```php
<?php
require_once 'config.php';
require_once 'php/services/LoggerService.php';

$logger = new LoggerService($conn);

// Export laatste week van 'update_rij_numbers' acties
$csv = $logger->exportAuditLogCsv(
    startDate: date('Y-m-d', strtotime('-7 days')),
    endDate: date('Y-m-d'),
    action: 'update_rij_numbers',
    entityType: 'rij',
    userId: null  // Alle users
);

// Download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d') . '.csv"');
echo $csv;
```

**CSV Output**:

```csv
ID,Timestamp,Action,Entity Type,Entity ID,User ID,Username,IP Address,Changed Fields,Details
123,2024-12-04 14:30:00,update_rij_numbers,rij,42,5,admin,192.168.1.100,"numbers; matches","numbers: 1,2,3 → 4,5,6; matches: 0 → 3"
124,2024-12-04 14:35:00,update_rij_numbers,rij,43,5,admin,192.168.1.100,"numbers","numbers: 10,20 → 30,40"
```

### UI Export Button

```php
<!-- In audit_log.php of beheer.php -->
<form action="api/export_audit_log.php" method="POST">
    <input type="date" name="start_date" value="<?= date('Y-m-d', strtotime('-7 days')) ?>">
    <input type="date" name="end_date" value="<?= date('Y-m-d') ?>">
    <select name="action">
        <option value="">Alle acties</option>
        <option value="update_rij_numbers">Update rij numbers</option>
        <option value="bon_checked">Bon checked</option>
        <option value="delete_bon">Delete bon</option>
    </select>
    <button type="submit">Export CSV</button>
</form>
```

**api/export_audit_log.php**:

```php
<?php
session_start();
require_once '../config.php';
require_once '../php/services/LoggerService.php';

if (!isset($_SESSION['admin_logged_in'])) {
    die('Niet ingelogd');
}

$logger = new LoggerService($conn);

$csv = $logger->exportAuditLogCsv(
    $_POST['start_date'] ?? null,
    $_POST['end_date'] ?? null,
    $_POST['action'] ?: null,
    $_POST['entity_type'] ?: null,
    null
);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d_His') . '.csv"');
echo $csv;
```

---

## Migration Checklist

### 1. Verwijder dubbele logging calls

**Bestanden om te updaten**:

- ✅ `api/set_bon_checked.php` - Verwijder `logBonAction()` call (regel 40)
- ✅ `api/update_rij_numbers.php` - Vervang door LoggerService (zie voorbeeld boven)
- ✅ `api/delete_bon.php` - Check of die ook dubbel logt
- ✅ `api/create_bon.php` - Check of die ook dubbel logt
- ✅ `api/update_bon_winkel.php` - Check of die ook dubbel logt

### 2. Replace pattern

```php
// ❌ VERWIJDER DEZE CALLS:
logBonAction($conn, $bonId, 'action', [...]);
add_audit_log($conn, 'action', 'entity', $entityId, [...]);

// ✅ VERVANG DOOR:
$logger = new LoggerService($conn);
$logger->logChange(
    $userId,
    'action',
    'entity',
    $entityId,
    $oldValues,  // Haal op VOOR update
    $newValues,  // Haal op NA update
    $context     // Optional extra info
);
```

### 3. Error handling

```php
// ❌ OUD:
try {
    // code
} catch (Exception $e) {
    // Nothing, of alleen echo
}

// ✅ NIEUW:
try {
    // code
} catch (Exception $e) {
    $logger->logError($e->getMessage(), $e->getTraceAsString());
    throw $e;  // Of return error response
}
```

---

## Testing

### 1. Test LoggerService

```bash
php test_logger_service.php
```

### 2. Test in browser

1. Open `dashboard.php`
2. Update een rij nummer
3. Check `audit_log` tabel:
   ```sql
   SELECT * FROM audit_log
   WHERE action = 'update_rij_numbers'
   ORDER BY created_at DESC
   LIMIT 5;
   ```
4. Check details JSON:
   ```json
   {
     "diff": {
       "numbers": {"old": [1,2,3], "new": [4,5,6]}
     },
     "changed_fields": ["numbers"],
     "change_count": 1
   }
   ```

### 3. Test error logging

```bash
tail -f /Applications/MAMP/htdocs/LuckyDays/logs/error.log
```

Trigger een error en check of die in het logbestand komt.

### 4. Test duplicate preventie

1. Update een rij
2. Refresh de page snel 5x
3. Check `audit_log`:
   ```sql
   SELECT COUNT(*) FROM audit_log
   WHERE action = 'update_rij_numbers'
   AND created_at > NOW() - INTERVAL '10 seconds';
   ```
4. Verwacht: **1 record** (niet 5!)

---

## FAQ

### Q: Wat gebeurt er met oude logs?

**A**: Niets. Oude `audit_log` entries blijven bestaan. LoggerService voegt alleen betere nieuwe logs toe.

### Q: Moet ik bon_logs tabel nog gebruiken?

**A**: Nee. Je kunt `logBonAction()` verwijderen. Alle logs gaan naar `audit_log` met betere details.

### Q: Wat als logChange() faalt?

**A**: Het gooit een `RuntimeException`. Vang dit op en rollback de transactie:

```php
try {
    pg_query($conn, "BEGIN");
    // ... updates ...
    $logger->logChange(...);
    pg_query($conn, "COMMIT");
} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    throw $e;
}
```

### Q: Hoe voorkom ik dat een fout in logging de hele transactie rolled back?

**A**: Gebruik een aparte database connectie voor logging, of log NA de commit:

```php
pg_query($conn, "COMMIT");

// Log AFTER commit (buiten transactie)
try {
    $logger->logChange(...);
} catch (Exception $e) {
    // Log failure, maar transactie is al committed
    error_log("Logging failed: " . $e->getMessage());
}
```

**Trade-off**: Als de app crasht tussen commit en log, heb je geen log.

---

## Conclusie

LoggerService geeft je:

1. ✅ **Forensische diff tracking** - Zie exact wat er veranderd is
2. ✅ **Geen duplicates** - Automatische preventie binnen 5 seconden
3. ✅ **Error visibility** - Geen stille failures
4. ✅ **CSV export** - Rapportage voor accountants
5. ✅ **File logging** - Kritieke errors naar `/logs/error.log`

**Next steps**:
1. Run test suite
2. Migreer `api/update_rij_numbers.php` (zie voorbeeld boven)
3. Migreer `api/set_bon_checked.php`
4. Bouw export UI in `audit_log.php`

---

**Versie**: 1.0.0 🎉
