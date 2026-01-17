<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../db_connect.php';
require_once '../notification_functions.php';

// Set the content type to JSON
header('Content-Type: application/json');

// Get the request data
$requestData = json_decode(file_get_contents('php://input'), true);

// Check if all required fields are present
if (!isset($requestData['appointment_id']) || !isset($requestData['new_date']) || !isset($requestData['new_time'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Extract the data
$appointmentId = intval($requestData['appointment_id']);
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

try {
    // Start a transaction
    $conn->begin_transaction();

    // Get the current appointment details for notification purposes
    $stmt = $conn->prepare("SELECT client_id, client_name, preferred_date, preferred_time, technician_id, status FROM appointments WHERE appointment_id = ?");
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }

    $appointment = $result->fetch_assoc();
    $clientId = $appointment['client_id'];
    $clientName = $appointment['client_name'];
    $oldDate = $appointment['preferred_date'];
    $oldTime = $appointment['preferred_time'];
    $technicianId = $appointment['technician_id'];
    $currentStatus = $appointment['status'];

    // Check if the appointment is already completed or declined
    if ($currentStatus === 'completed') {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Completed appointments cannot be rescheduled']);
        exit;
    }

    // Check if the appointment is declined
    if ($currentStatus === 'declined') {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Declined appointments cannot be rescheduled']);
        exit;
    }

    // Update the appointment with the new date and time
    $newStatus = 'rescheduled';
    $stmt = $conn->prepare("UPDATE appointments SET preferred_date = ?, preferred_time = ?, status = ? WHERE appointment_id = ?");
    $stmt->bind_param("sssi", $newDate, $newTime, $newStatus, $appointmentId);
    $result = $stmt->execute();

    if (!$result) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to update appointment: ' . $conn->error]);
        exit;
    }

    // Format dates for notifications
    $oldDateFormatted = date('F j, Y', strtotime($oldDate));
    $oldTimeFormatted = date('g:i A', strtotime($oldTime));
    $newDateFormatted = date('F j, Y', strtotime($newDate));
    $newTimeFormatted = date('g:i A', strtotime($newTime));

    // Create notification for the client
    $clientTitle = ($currentStatus === 'declined') ? "Appointment Reactivated" : "Appointment Rescheduled";

    if ($currentStatus === 'declined') {
        $clientMessage = "Your previously declined appointment has been reactivated and rescheduled to {$newDateFormatted} at {$newTimeFormatted}.";
    } else {
        $clientMessage = "Your appointment on {$oldDateFormatted} at {$oldTimeFormatted} has been rescheduled to {$newDateFormatted} at {$newTimeFormatted}.";
    }
    $clientNotificationSent = createNotification(
        $clientId,
        'client',
        $clientTitle,
        $clientMessage,
        $appointmentId,
        'appointment_rescheduled'
    );

    // Create notification for the technician if assigned
    $technicianNotificationSent = false;
    if ($technicianId) {
        $techTitle = ($currentStatus === 'declined') ? "Appointment Reactivated" : "Appointment Rescheduled";

        if ($currentStatus === 'declined') {
            $techMessage = "A previously declined appointment for {$clientName} has been reactivated and rescheduled to {$newDateFormatted} at {$newTimeFormatted}.";
        } else {
            $techMessage = "The appointment for {$clientName} on {$oldDateFormatted} at {$oldTimeFormatted} has been rescheduled to {$newDateFormatted} at {$newTimeFormatted}.";
        }
        $technicianNotificationSent = createNotification(
            $technicianId,
            'technician',
            $techTitle,
            $techMessage,
            $appointmentId,
            'appointment_rescheduled'
        );
    }

    // Commit the transaction
    $conn->commit();

    // Return success response
    $successMessage = ($currentStatus === 'declined') ? 'Declined appointment reactivated and rescheduled successfully' : 'Appointment rescheduled successfully';

    echo json_encode([
        'success' => true,
        'message' => $successMessage,
        'appointment_id' => $appointmentId,
        'new_date' => $newDate,
        'new_time' => $newTime,
        'new_status' => $newStatus,
        'previous_status' => $currentStatus,
        'client_notification_sent' => $clientNotificationSent,
        'technician_notification_sent' => $technicianNotificationSent
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
