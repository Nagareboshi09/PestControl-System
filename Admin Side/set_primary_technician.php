<?php
require_once '../db_connect.php';
require_once '../notification_functions.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// Check if we have all required parameters
if (!isset($data['type']) || !isset($data['technician_id']) ||
    (!isset($data['appointment_id']) && !isset($data['job_order_id']))) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$type = $data['type'];
$technicianId = (int)$data['technician_id'];
$response = ['success' => false];

if ($type === 'appointment') {
    if (!isset($data['appointment_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing appointment_id parameter']);
        exit;
    }

    $appointmentId = (int)$data['appointment_id'];

    // First, unset all primary technicians for this appointment
    $unsetStmt = $conn->prepare("UPDATE appointment_technicians SET is_primary = 0 WHERE appointment_id = ?");
    $unsetStmt->bind_param("i", $appointmentId);

    if (!$unsetStmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to unset primary technicians: ' . $conn->error]);
        exit;
    }

    // Now set the selected technician as primary
    $setStmt = $conn->prepare("UPDATE appointment_technicians SET is_primary = 1
                              WHERE appointment_id = ? AND technician_id = ?");
    $setStmt->bind_param("ii", $appointmentId, $technicianId);

    if ($setStmt->execute()) {
        // For backward compatibility, also update the technician_id in the appointments table
        $updateStmt = $conn->prepare("UPDATE appointments SET technician_id = ? WHERE appointment_id = ?");
        $updateStmt->bind_param("ii", $technicianId, $appointmentId);
        $updateStmt->execute();

        // Get technician name for the response
        $techStmt = $conn->prepare("SELECT username FROM technicians WHERE technician_id = ?");
        $techStmt->bind_param("i", $technicianId);
        $techStmt->execute();
        $techResult = $techStmt->get_result();
        $techData = $techResult->fetch_assoc();

        $response = [
            'success' => true,
            'appointment_id' => $appointmentId,
            'technician_id' => $technicianId,
            'technician_name' => $techData['username'] ?? 'Unknown',
            'message' => 'Primary technician updated successfully'
        ];
    } else {
        $response['message'] = 'Failed to set primary technician: ' . $conn->error;
    }
} elseif ($type === 'job_order') {
    if (!isset($data['job_order_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing job_order_id parameter']);
        exit;
    }

    $jobOrderId = (int)$data['job_order_id'];

    // First, unset all primary technicians for this job order
    $unsetStmt = $conn->prepare("UPDATE job_order_technicians SET is_primary = 0 WHERE job_order_id = ?");
    $unsetStmt->bind_param("i", $jobOrderId);

    if (!$unsetStmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to unset primary technicians: ' . $conn->error]);
        exit;
    }

    // Now set the selected technician as primary
    $setStmt = $conn->prepare("UPDATE job_order_technicians SET is_primary = 1
                              WHERE job_order_id = ? AND technician_id = ?");
    $setStmt->bind_param("ii", $jobOrderId, $technicianId);

    if ($setStmt->execute()) {
        // Get technician name for the response
        $techStmt = $conn->prepare("SELECT username FROM technicians WHERE technician_id = ?");
        $techStmt->bind_param("i", $technicianId);
        $techStmt->execute();
        $techResult = $techStmt->get_result();
        $techData = $techResult->fetch_assoc();

        $response = [
            'success' => true,
            'job_order_id' => $jobOrderId,
            'technician_id' => $technicianId,
            'technician_name' => $techData['username'] ?? 'Unknown',
            'message' => 'Primary technician updated successfully'
        ];

        // Send notification to the technician
        try {
            // Get job order details
            $jobStmt = $conn->prepare("SELECT
                j.preferred_date,
                j.preferred_time,
                j.type_of_work,
                a.client_name,
                a.location_address
            FROM job_order j
            JOIN assessment_report ar ON j.report_id = ar.report_id
            JOIN appointments a ON ar.appointment_id = a.appointment_id
            WHERE j.job_order_id = ?");

            $jobStmt->bind_param("i", $jobOrderId);
            $jobStmt->execute();
            $jobResult = $jobStmt->get_result();
            $jobData = $jobResult->fetch_assoc();

            if ($jobData) {
                // Use the new notification function
                $notificationResult = notifyTechnicianAboutJobOrderAssignment(
                    $technicianId,
                    $jobOrderId,
                    $jobData['client_name'],
                    date('F j, Y', strtotime($jobData['preferred_date'])),
                    date('g:i A', strtotime($jobData['preferred_time'])),
                    $jobData['type_of_work'],
                    $jobData['location_address'] ?? 'Not specified',
                    true, // is_primary is true since this is specifically for setting primary technician
                    $conn
                );

                $response['notification_sent'] = $notificationResult;
            }
        } catch (Exception $e) {
            // Log the error but don't fail the assignment
            $response['notification_error'] = $e->getMessage();
        }
    } else {
        $response['message'] = 'Failed to set primary technician: ' . $conn->error;
    }
} else {
    $response['message'] = 'Invalid type parameter. Must be "appointment" or "job_order".';
}

echo json_encode($response);
?>
