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

// Get form data
$service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$icon = isset($_POST['icon']) ? trim($_POST['icon']) : 'fa-spray-can';
$image = isset($_POST['image']) ? trim($_POST['image']) : null;
$status = isset($_POST['status']) ? trim($_POST['status']) : 'active';

// Validate required fields
if (empty($service_id)) {
    echo json_encode(['success' => false, 'error' => 'Service ID is required']);
    exit;
}

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Service name is required']);
    exit;
}

try {
    // Check if service with the same name already exists (excluding the current service)
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM services WHERE name = ? AND service_id != ?");
    $check_stmt->bind_param("si", $name, $service_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'A service with this name already exists']);
        exit;
    }

    // Update service
    $stmt = $conn->prepare("UPDATE services SET name = ?, description = ?, icon = ?, image = ?, status = ? WHERE service_id = ?");
    $stmt->bind_param("sssssi", $name, $description, $icon, $image, $status, $service_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Service updated successfully']);
    } else {
        throw new Exception($stmt->error);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
