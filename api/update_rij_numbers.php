<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$changes = $input['changes'] ?? [];

if (empty($changes)) {
    echo json_encode(['success' => false, 'error' => 'Geen wijzigingen']);
    exit;
}

try {
    $conn->beginTransaction();
    
    foreach ($changes as $change) {
        $rijId = intval($change['rij_id']);
        $numbers = $change['numbers'];
        
        if (!is_array($numbers) || empty($numbers)) {
            throw new Exception('Ongeldige nummers');
        }
        
        foreach ($numbers as $num) {
            if (!is_int($num) || $num < 1 || $num > 80) {
                throw new Exception('Nummers moeten tussen 1 en 80 zijn');
            }
        }
        
        $stmt = $conn->prepare("SELECT r.id, r.bon_id, r.bet, b.date FROM rijen r JOIN bons b ON r.bon_id = b.id WHERE r.id = ?");
        $stmt->execute([$rijId]);
        $rij = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$rij) {
            throw new Exception('Rij niet gevonden');
        }
        
        $gameType = count($numbers) . '-getallen';
        $numbersJson = json_encode($numbers);
        
        $winningNumbers = getWinningNumbers($conn, $rij['date']);
        $matches = 0;
        $multiplier = 0;
        $winnings = 0;
        
        if (!empty($winningNumbers)) {
            foreach ($numbers as $num) {
                if (in_array($num, $winningNumbers)) {
                    $matches++;
                }
            }
            $multiplier = getMultiplier($gameType, $matches);
            $winnings = $rij['bet'] * $multiplier;
        }
        
        $stmt = $conn->prepare("UPDATE rijen SET numbers = ?, game_type = ?, matches = ?, multiplier = ?, winnings = ? WHERE id = ?");
        $stmt->execute([$numbersJson, $gameType, $matches, $multiplier, $winnings, $rijId]);
    }
    
    $conn->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
