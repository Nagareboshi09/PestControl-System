<?php
require_once '../db_config.php';

try {
    $chemicalId = $_GET['id'] ?? null;
    
    $stmt = $pdo->prepare("SELECT * FROM chemical_inventory WHERE id = ?");
    $stmt->execute([$chemicalId]);
    $chemical = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$chemical) throw new Exception('Chemical not found');
    
    echo json_encode(['success' => true, 'data' => $chemical]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}