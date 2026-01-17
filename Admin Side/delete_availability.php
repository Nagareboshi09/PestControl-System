<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}
require_once '../db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get JSON data from request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validate availability ID
$availability_id = isset($data['availability_id']) ? intval($data['availability_id']) : 0;
if ($availability_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid availability ID']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if the table has 'availability_id' or 'id' column
    $checkColumnQuery = "SHOW COLUMNS FROM technician_availability LIKE 'availability_id'";
    $columnResult = $conn->query($checkColumnQuery);
    $hasAvailabilityIdColumn = $columnResult->num_rows > 0;

    // Set the ID column name based on the table structure
    $idColumnName = $hasAvailabilityIdColumn ? 'availability_id' : 'id';

    // Delete availability
    $stmt = $conn->prepare("DELETE FROM technician_availability WHERE $idColumnName = ?");
    $stmt->bind_param("i", $availability_id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to delete availability: " . $stmt->error);
    }

    // Check if any rows were affected
    if ($stmt->affected_rows === 0) {
        throw new Exception("Availability not found or already deleted");
    }

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Availability deleted successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();

    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
