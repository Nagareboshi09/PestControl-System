<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Technician ID is required'
    ]);
    exit;
}

$technicianId = intval($_GET['id']);

// Get technician name
$stmt = $conn->prepare("SELECT username FROM technicians WHERE technician_id = ?");
$stmt->bind_param("i", $technicianId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Technician not found'
    ]);
    exit;
}

$row = $result->fetch_assoc();
$technicianName = $row['username'];
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'id' => $technicianId,
    'name' => $technicianName
]);
?>
