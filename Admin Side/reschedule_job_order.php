<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../db_connect.php';
require_once '../notification_functions.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Get the request data
$requestData = json_decode(file_get_contents('php://input'), true);

// Check if the request data is valid
if (!isset($requestData['job_order_id']) || !isset($requestData['new_date']) || !isset($requestData['new_time'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Start a transaction
$conn->begin_transaction();

try {
    // Extract the data
    $jobOrderId = intval($requestData['job_order_id']);
    $newDate = $requestData['new_date'];
    $newTime = $requestData['new_time'];

    // Validate the date and time
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }

    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $newTime)) {
        echo json_encode(['success' => false, 'message' => 'Invalid time format']);
        exit;
    }

    // Get the current job order details
    $stmt = $conn->prepare("
        SELECT
            j.preferred_date,
            j.preferred_time,
            j.type_of_work,
            j.status AS job_status,
            a.client_id,
            a.client_name,
            a.location_address
        FROM job_order j
        JOIN assessment_report ar ON j.report_id = ar.report_id
        JOIN appointments a ON ar.appointment_id = a.appointment_id
        WHERE j.job_order_id = ?
    ");
    $stmt->bind_param("i", $jobOrderId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Job order not found']);
        exit;
    }

    $jobOrder = $result->fetch_assoc();
    $clientId = $jobOrder['client_id'];
    $clientName = $jobOrder['client_name'];
    $oldDate = $jobOrder['preferred_date'];
    $oldTime = $jobOrder['preferred_time'];
    $typeOfWork = $jobOrder['type_of_work'];
    $location = $jobOrder['location_address'];
    $jobStatus = $jobOrder['job_status'];

    // Check if the job order is already completed
    if ($jobStatus === 'completed') {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Completed job orders cannot be rescheduled']);
        exit;
    }

    // Update the job order with the new date and time
    $stmt = $conn->prepare("UPDATE job_order SET preferred_date = ?, preferred_time = ?, status = 'rescheduled' WHERE job_order_id = ?");
    $stmt->bind_param("ssi", $newDate, $newTime, $jobOrderId);
    $result = $stmt->execute();

    if (!$result) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to update job order: ' . $conn->error]);
        exit;
    }

    // Format dates for notifications
    $oldDateFormatted = date('F j, Y', strtotime($oldDate));
    $oldTimeFormatted = date('g:i A', strtotime($oldTime));
    $newDateFormatted = date('F j, Y', strtotime($newDate));
    $newTimeFormatted = date('g:i A', strtotime($newTime));

    // Create notification for the client
    $clientTitle = "Job Order Rescheduled";
    $clientMessage = "Your {$typeOfWork} job order at {$location} on {$oldDateFormatted} at {$oldTimeFormatted} has been rescheduled to {$newDateFormatted} at {$newTimeFormatted}.";
    $clientNotificationSent = createNotification(
        $clientId,
        'client',
        $clientTitle,
        $clientMessage,
        $jobOrderId,
        'job_order_rescheduled'
    );

    // Get all technicians assigned to this job order
    $technicianNotificationsSent = [];
    $stmt = $conn->prepare("
        SELECT jot.technician_id, t.username
        FROM job_order_technicians jot
        JOIN technicians t ON jot.technician_id = t.technician_id
        WHERE jot.job_order_id = ?
    ");
    $stmt->bind_param("i", $jobOrderId);
    $stmt->execute();
    $techResult = $stmt->get_result();

    // Create notification for each assigned technician
    while ($tech = $techResult->fetch_assoc()) {
        $techId = $tech['technician_id'];
        $techName = $tech['username'];

        $techTitle = "Job Order Rescheduled";
        $techMessage = "The {$typeOfWork} job order for {$clientName} at {$location} on {$oldDateFormatted} at {$oldTimeFormatted} has been rescheduled to {$newDateFormatted} at {$newTimeFormatted}.";
        $notificationSent = createNotification(
            $techId,
            'technician',
            $techTitle,
            $techMessage,
            $jobOrderId,
            'job_order_rescheduled'
        );

        $technicianNotificationsSent[] = [
            'technician_id' => $techId,
            'technician_name' => $techName,
            'notification_sent' => $notificationSent
        ];
    }

    // Commit the transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Job order rescheduled successfully',
        'job_order_id' => $jobOrderId,
        'new_date' => $newDate,
        'new_time' => $newTime,
        'client_notification_sent' => $clientNotificationSent,
        'technician_notifications_sent' => $technicianNotificationsSent
    ]);

} catch (Exception $e) {
    // Rollback the transaction in case of error
    $conn->rollback();

    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
