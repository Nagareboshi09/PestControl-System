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

// Get service ID
$service_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (empty($service_id)) {
    echo json_encode(['success' => false, 'error' => 'Service ID is required']);
    exit;
}

try {
    // Check if this is a default service (first 5 services)
    $check_stmt = $conn->prepare("SELECT service_id FROM services ORDER BY service_id LIMIT 5");
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $default_ids = [];
    
    while ($row = $result->fetch_assoc()) {
        $default_ids[] = $row['service_id'];
    }
    
    if (in_array($service_id, $default_ids)) {
        echo json_encode(['success' => false, 'error' => 'Default services cannot be deleted']);
        exit;
    }
    
    // Delete the service
    $stmt = $conn->prepare("DELETE FROM services WHERE service_id = ?");
    $stmt->bind_param("i", $service_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Service deleted successfully']);
    } else {
        throw new Exception($stmt->error);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
