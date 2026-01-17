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

// Get availability ID from request
$availability_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate availability ID
if ($availability_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid availability ID']);
    exit;
}

try {
    // Check if the table has 'availability_id' or 'id' column
    $checkColumnQuery = "SHOW COLUMNS FROM technician_availability LIKE 'availability_id'";
    $columnResult = $conn->query($checkColumnQuery);
    $hasAvailabilityIdColumn = $columnResult->num_rows > 0;

    // Use the appropriate column name based on the table structure
    if ($hasAvailabilityIdColumn) {
        $stmt = $conn->prepare("
            SELECT * FROM technician_availability
            WHERE availability_id = ?
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT * FROM technician_availability
            WHERE id = ?
        ");
    }

    $stmt->bind_param("i", $availability_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Availability not found']);
        exit;
    }

    $availability = $result->fetch_assoc();

    // Format times for HTML time input
    $availability['start_time'] = substr($availability['start_time'], 0, 5);
    $availability['end_time'] = substr($availability['end_time'], 0, 5);

    // Return success response
    echo json_encode([
        'success' => true,
        'availability' => $availability
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
