<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['id']) || empty($_POST['id']) || !isset($_POST['quantity'])) {
    echo json_encode(['success' => false, 'error' => 'Tool ID and quantity are required']);
    exit;
}

$id = (int)$_POST['id'];
$quantity = (int)$_POST['quantity'];

if ($quantity < 0) {
    echo json_encode(['success' => false, 'error' => 'Quantity cannot be negative']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE tools_equipment SET quantity = ? WHERE id = ?");
    $stmt->bind_param("ii", $quantity, $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Tool quantity updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'No changes made or tool not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
