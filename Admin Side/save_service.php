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
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$icon = isset($_POST['icon']) ? trim($_POST['icon']) : 'fa-spray-can';
$image = isset($_POST['image']) ? trim($_POST['image']) : null;
$status = isset($_POST['status']) ? trim($_POST['status']) : 'active';

// Validate required fields
if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Service name is required']);
    exit;
}

try {
    // Check if service with the same name already exists
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM services WHERE name = ?");
    $check_stmt->bind_param("s", $name);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'A service with this name already exists']);
        exit;
    }

    // Insert new service
    $stmt = $conn->prepare("INSERT INTO services (name, description, icon, image, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $description, $icon, $image, $status);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Service added successfully']);
    } else {
        throw new Exception($stmt->error);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
