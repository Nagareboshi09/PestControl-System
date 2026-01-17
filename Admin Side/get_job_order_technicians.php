<?php
require_once '../db_connect.php';

header('Content-Type: application/json');

// Check if job_order_id is provided
if (!isset($_GET['job_order_id']) || !is_numeric($_GET['job_order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid job order ID']);
    exit;
}

$jobOrderId = (int)$_GET['job_order_id'];

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

// Get all technicians assigned to this job order
$query = "SELECT jot.technician_id, t.username, jot.is_primary
          FROM job_order_technicians jot
          JOIN technicians t ON jot.technician_id = t.technician_id
          WHERE jot.job_order_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jobOrderId);
$stmt->execute();
$result = $stmt->get_result();

$technicians = [];
while ($row = $result->fetch_assoc()) {
    $technicians[] = [
        'id' => (int)$row['technician_id'],
        'name' => $row['username'],
        'isPrimary' => (bool)$row['is_primary']
    ];
}

// If no primary technician is set but there are technicians, set the first one as primary
if (!empty($technicians) && !array_filter($technicians, function($tech) { return $tech['isPrimary']; })) {
    $technicians[0]['isPrimary'] = true;
    
    // Update the database
    $updateStmt = $conn->prepare("UPDATE job_order_technicians SET is_primary = 1 
                                 WHERE job_order_id = ? AND technician_id = ?");
    $updateStmt->bind_param("ii", $jobOrderId, $technicians[0]['id']);
    $updateStmt->execute();
}

echo json_encode(['success' => true, 'technicians' => $technicians]);
?>
