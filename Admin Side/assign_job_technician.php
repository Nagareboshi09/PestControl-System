<?php
require_once '../db_connect.php';
require_once '../notification_functions.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$job_order_id = $data['job_order_id'];
$technician_id = $data['technician_id'];
$isPrimary = isset($data['is_primary']) ? (bool)$data['is_primary'] : false;

// Validate input
if (!$job_order_id || !$technician_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Get job order details and technician name
$jobStmt = $conn->prepare("SELECT
    j.preferred_date,
    j.preferred_time,
    j.type_of_work,
    a.client_name,
    a.location_address,
    t.username as technician_name
FROM job_order j
JOIN assessment_report ar ON j.report_id = ar.report_id
JOIN appointments a ON ar.appointment_id = a.appointment_id
JOIN technicians t ON t.technician_id = ?
WHERE j.job_order_id = ?");

if (!$jobStmt) {
    echo json_encode(['success' => false, 'message' => 'Error preparing statement: ' . $conn->error]);
    exit;
}

$jobStmt->bind_param("ii", $technician_id, $job_order_id);
$jobStmt->execute();
$jobResult = $jobStmt->get_result();

if ($jobResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Job order or technician not found']);
    exit;
}

$jobData = $jobResult->fetch_assoc();
$jobStmt->close();

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

    $conn->query($updateSQL);
}

// If this is the primary technician, unset any existing primary technicians
if ($isPrimary) {
    $unsetPrimaryStmt = $conn->prepare("UPDATE job_order_technicians SET is_primary = 0 WHERE job_order_id = ?");
    $unsetPrimaryStmt->bind_param("i", $job_order_id);
    $unsetPrimaryStmt->execute();
}

// Check if technician is already assigned to this job order
$checkStmt = $conn->prepare("SELECT * FROM job_order_technicians WHERE job_order_id = ? AND technician_id = ?");
$checkStmt->bind_param("ii", $job_order_id, $technician_id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    // Technician is already assigned, just update the primary status if needed
    if ($isPrimary) {
        $updateStmt = $conn->prepare("UPDATE job_order_technicians SET is_primary = 1 WHERE job_order_id = ? AND technician_id = ?");
        $updateStmt->bind_param("ii", $job_order_id, $technician_id);
        $updateStmt->execute();
    }

    $response = [
        'success' => true,
        'job_order_id' => $job_order_id,
        'technician_id' => $technician_id,
        'technician_name' => $jobData['technician_name'],
        'is_primary' => $isPrimary,
        'message' => 'Technician is already assigned to this job order'
    ];

    echo json_encode($response);
    exit;
}
$checkStmt->close();

// Insert the technician assignment
$stmt = $conn->prepare("INSERT INTO job_order_technicians (job_order_id, technician_id, is_primary) VALUES (?, ?, ?)");
$stmt->bind_param("iii", $job_order_id, $technician_id, $isPrimary);

$response = ['success' => false];

if ($stmt->execute()) {
    $response['success'] = true;
    $response['job_order_id'] = $job_order_id;
    $response['technician_id'] = $technician_id;
    $response['technician_name'] = $jobData['technician_name'];
    $response['is_primary'] = $isPrimary;

    // Try to create notification for technician
    try {
        $jobDate = $jobData['preferred_date'];
        $jobTime = $jobData['preferred_time'];
        $clientName = $jobData['client_name'];
        $jobType = $jobData['type_of_work'];
        $location = $jobData['location_address'];

        // Use the new notification function
        $notificationResult = notifyTechnicianAboutJobOrderAssignment(
            $technician_id,
            $job_order_id,
            $clientName,
            date('F j, Y', strtotime($jobDate)),
            date('g:i A', strtotime($jobTime)),
            $jobType,
            $location,
            $isPrimary,
            $conn
        );

        $response['notification_sent'] = $notificationResult;
    } catch (Exception $e) {
        // Log the error but don't fail the assignment
        $response['notification_error'] = $e->getMessage();
    }
} else {
    $response['message'] = $conn->error;
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>
