<?php
require_once '../db_config.php';

try {
    $chemicalId = $_POST['id'];
    
    $stmt = $pdo->prepare("DELETE FROM chemical_inventory WHERE id = ?");
    $stmt->execute([$chemicalId]);
    
    echo json_encode(['success' => true]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}