<?php
session_start();
require_once '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Pest checkbox ID is required']);
    exit;
}

try {
    $id = (int)$_GET['id'];
    
    // Get the pest checkbox details
    $query = "SELECT * FROM pest_checkboxes WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $pest_checkbox = $result->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $pest_checkbox]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Pest checkbox not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
