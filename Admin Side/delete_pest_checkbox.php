<?php
session_start();
require_once '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Invalid pest checkbox ID']);
        exit;
    }

    // Delete the pest checkbox
    $query = "DELETE FROM pest_checkboxes WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete pest checkbox: ' . $stmt->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
