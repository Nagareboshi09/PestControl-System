<?php
session_start();
require_once '../../db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if user is logged in as technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if job_order_id is provided
if (!isset($_GET['job_order_id']) || empty($_GET['job_order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Job order ID is required']);
    exit;
}

$job_order_id = intval($_GET['job_order_id']);

// Check if the job_order table has a status column
$checkStatusColumn = $conn->query("SHOW COLUMNS FROM job_order LIKE 'status'");
if ($checkStatusColumn->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Status column does not exist in job_order table']);
    exit;
}

// Get the job status
$stmt = $conn->prepare("SELECT status FROM job_order WHERE job_order_id = ?");
$stmt->bind_param("i", $job_order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'success' => true, 
        'job_order_id' => $job_order_id,
        'status' => $row['status']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Job order not found']);
}

// Close database connection
$conn->close();
?>
