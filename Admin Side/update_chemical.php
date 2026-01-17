<?php
require_once '../db_config.php';

try {
    $chemicalId = $_POST['id'];
    $chemical_name = $_POST['chemical_name'] ?? '';
    $type = $_POST['type'] ?? '';
    $target_pest = $_POST['target_pest'] ?? null;
    $quantity = (float)$_POST['quantity'];
    $unit = $_POST['unit'] ?? '';
    $manufacturer = $_POST['manufacturer'] ?? null;
    $supplier = $_POST['supplier'] ?? null;
    $expiration_date = $_POST['expiration_date'] ?? null;
    $description = $_POST['description'] ?? null;
    $safety_info = $_POST['safety_info'] ?? null;
    $dilution_rate = isset($_POST['dilution_rate']) ? (float)$_POST['dilution_rate'] : null;
    $area_coverage = isset($_POST['area_coverage']) ? (float)$_POST['area_coverage'] : 100;

    // Calculate status based on quantity
    $status = 'In Stock';
    if ($quantity <= 0) {
        $status = 'Out of Stock';
    } else if ($quantity < 10) {
        $status = 'Low Stock';
    }

    $stmt = $pdo->prepare("UPDATE chemical_inventory
                          SET chemical_name = ?,
                              type = ?,
                              target_pest = ?,
                              quantity = ?,
                              unit = ?,
                              manufacturer = ?,
                              supplier = ?,
                              expiration_date = ?,
                              description = ?,
                              safety_info = ?,
                              dilution_rate = ?,
                              area_coverage = ?
                          WHERE id = ?");

    $stmt->execute([
        $chemical_name,
        $type,
        $target_pest,
        $quantity,
        $unit,
        $manufacturer,
        $supplier,
        $expiration_date,
        $description,
        $safety_info,
        $dilution_rate,
        $area_coverage,
        $chemicalId
    ]);

    echo json_encode(['success' => true]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}