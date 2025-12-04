<?php
session_start();

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once '../config.php';
require_once '../functions.php';
require_once '../php/services/LoggerService.php';

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

$logger = new LoggerService($conn, __DIR__ . '/../logs');
$userId = $_SESSION['user_id'] ?? 0;

$input = json_decode(file_get_contents('php://input'), true);

// Support beide formaten: single update of batch updates
if (isset($input['rij_id']) && isset($input['numbers'])) {
    // Single update format
    $numbersArray = is_string($input['numbers'])
        ? explode(',', $input['numbers'])
        : $input['numbers'];
    $changes = [[
        'rij_id' => $input['rij_id'],
        'numbers' => $numbersArray
    ]];
} else {
    // Batch update format
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
        
        $validatedNumbers = [];
        foreach ($numbers as $num) {
            $intNum = intval($num);
            if ($intNum < 1 || $intNum > 80) {
                throw new Exception('Nummers moeten tussen 1 en 80 zijn');
            }
            $validatedNumbers[] = $intNum;
        }
        $numbers = $validatedNumbers;
        
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

        // ✅ Bewaar oude waarden VOOR update (voor forensische logging)
        $oldNumbers = array_filter(array_map('intval', explode(',', $rij['numbers'] ?? '')));
        $oldValues = [
            'numbers' => $oldNumbers,
            'game_type' => $rij['game_type'] ?? null,
            'matches' => intval($rij['matches']),
            'multiplier' => floatval($rij['multiplier']),
            'winnings' => floatval($rij['winnings'])
        ];

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

        $updateResult = pg_query_params($conn,
            "UPDATE rijen SET numbers = $1, game_type = $2, matches = $3, multiplier = $4, winnings = $5 WHERE id = $6",
            [$numbersStr, $gameType, $matches, $multiplier, $winnings, $rijId]
        );

        if (!$updateResult) {
            throw new Exception('Database update mislukt: ' . pg_last_error($conn));
        }

        // ✅ Log met LoggerService (automatische diff, geen duplicates!)
        try {
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
        } catch (Exception $e) {
            // Log failure, maar laat update succesvol zijn
            error_log("Failed to log rij update: " . $e->getMessage());
        }
    }
    
    $commitResult = pg_query($conn, "COMMIT");
    if (!$commitResult) {
        throw new Exception('Commit failed');
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");

    // ✅ Log error naar bestand voor debugging
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
        error_log("Failed to log error: " . $logError->getMessage());
    }

    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
