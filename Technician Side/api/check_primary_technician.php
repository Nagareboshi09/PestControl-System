<?php
session_start();
require_once '../../db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in as a technician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'technician') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$technician_id = $_SESSION['user_id'];

// Check if job_order_id is provided
if (!isset($_GET['job_order_id']) || !is_numeric($_GET['job_order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid job order ID']);
    exit;
}

$job_order_id = (int)$_GET['job_order_id'];

// Check if the job_order_technicians table has the is_primary column
$columnCheckQuery = "SHOW COLUMNS FROM job_order_technicians LIKE 'is_primary'";
$columnCheckResult = $conn->query($columnCheckQuery);

if ($columnCheckResult->num_rows === 0) {
    // Add the is_primary column if it doesn't exist
    $alterTableSQL = "ALTER TABLE job_order_technicians ADD COLUMN is_primary BOOLEAN NOT NULL DEFAULT 0";
    
    if (!$conn->query($alterTableSQL)) {
        echo json_encode(['success' => false, 'message' => 'Failed to add is_primary column: ' . $conn->error]);
        exit;
    }
    
    // Set the first technician for each job order as primary
    $updateSQL = "UPDATE job_order_technicians jot1
                 JOIN (
                     SELECT job_order_id, MIN(technician_id) as first_tech
                     FROM job_order_technicians
                     GROUP BY job_order_id
                 ) jot2 ON jot1.job_order_id = jot2.job_order_id AND jot1.technician_id = jot2.first_tech
                 SET jot1.is_primary = 1";
    
    if (!$conn->query($updateSQL)) {
        echo json_encode(['success' => false, 'message' => 'Failed to update primary technicians: ' . $conn->error]);
        exit;
    }
}

// Check if the technician is assigned to this job order and if they are the primary technician
$query = "SELECT is_primary FROM job_order_technicians 
          WHERE job_order_id = ? AND technician_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $job_order_id, $technician_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Technician is not assigned to this job order']);
    exit;
}

$row = $result->fetch_assoc();
$isPrimary = (bool)$row['is_primary'];

echo json_encode([
    'success' => true,
    'is_primary' => $isPrimary,
    'job_order_id' => $job_order_id,
    'technician_id' => $technician_id
]);
?>
