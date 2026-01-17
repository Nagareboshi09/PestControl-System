<?php
// Turn off all error reporting and disable display of errors
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to capture any unexpected output
ob_start();

// Try to include required files
try {
    require_once '../db_connect.php';
    require_once '../notification_functions.php';
} catch (Exception $e) {
    // Clean buffer and return error
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to load required files: ' . $e->getMessage()]);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

// Parse the JSON input data
$data = json_decode(file_get_contents('php://input'), true);

// Check if JSON was parsed successfully
if ($data === null) {
    // Clean any output buffer
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON input: ' . json_last_error_msg()
    ]);
    exit;
}

// Check for required parameters
if (!isset($data['appointment_id']) || !isset($data['technician_id'])) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters: appointment_id and technician_id are required'
    ]);
    exit;
}

$appointmentId = $data['appointment_id'];
$technicianId = $data['technician_id'];
$isPrimary = isset($data['is_primary']) ? (bool)$data['is_primary'] : false;

// Get appointment details and technician name
$clientStmt = $conn->prepare("SELECT a.preferred_date, a.preferred_time, a.client_name, a.email, t.username
                            FROM appointments a
                            JOIN technicians t ON t.technician_id = ?
                            WHERE a.appointment_id = ?");

if (!$clientStmt) {
    $response['message'] = 'Error preparing statement: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$clientStmt->bind_param("ii", $technicianId, $appointmentId);
$clientStmt->execute();
$clientResult = $clientStmt->get_result();
$appointmentData = $clientResult->fetch_assoc();

// Check if we got data back
if (!$appointmentData) {
    $response['message'] = 'Could not find appointment or technician data';
    echo json_encode($response);
    exit;
}

$clientName = $appointmentData['client_name'] ?? 'Client';
$clientEmail = $appointmentData['email'] ?? null;
$technicianName = $appointmentData['username'] ?? 'Technician';
$appointmentDate = $appointmentData['preferred_date'] ?? date('Y-m-d');
$appointmentTime = $appointmentData['preferred_time'] ?? '00:00:00';
$clientStmt->close();

// Check if the appointment_technicians table exists
$tableCheckQuery = "SHOW TABLES LIKE 'appointment_technicians'";
$tableCheckResult = $conn->query($tableCheckQuery);

if ($tableCheckResult->num_rows === 0) {
    // Create the table if it doesn't exist
    $createTableSQL = "CREATE TABLE IF NOT EXISTS appointment_technicians (
        appointment_id INT(11) NOT NULL,
        technician_id INT(11) NOT NULL,
        is_primary BOOLEAN NOT NULL DEFAULT 0,
        PRIMARY KEY (appointment_id, technician_id),
        FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id),
        FOREIGN KEY (technician_id) REFERENCES technicians(technician_id)
    )";

    if (!$conn->query($createTableSQL)) {
        $response['message'] = 'Failed to create appointment_technicians table: ' . $conn->error;
        echo json_encode($response);
        exit;
    }

    // Migrate existing appointments with technician_id to the new table
    $migrateSQL = "INSERT INTO appointment_technicians (appointment_id, technician_id, is_primary)
                  SELECT appointment_id, technician_id, 1
                  FROM appointments
                  WHERE technician_id IS NOT NULL";

    $conn->query($migrateSQL);
}

// If this is the primary technician, unset any existing primary technicians
if ($isPrimary) {
    $unsetPrimaryStmt = $conn->prepare("UPDATE appointment_technicians SET is_primary = 0 WHERE appointment_id = ?");
    $unsetPrimaryStmt->bind_param("i", $appointmentId);
    $unsetPrimaryStmt->execute();
}

// Check if this technician is already assigned to this appointment
$checkStmt = $conn->prepare("SELECT * FROM appointment_technicians WHERE appointment_id = ? AND technician_id = ?");
$checkStmt->bind_param("ii", $appointmentId, $technicianId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    // Technician is already assigned, just update the primary status if needed
    if ($isPrimary) {
        $updateStmt = $conn->prepare("UPDATE appointment_technicians SET is_primary = 1 WHERE appointment_id = ? AND technician_id = ?");
        $updateStmt->bind_param("ii", $appointmentId, $technicianId);
        $updateStmt->execute();
    }

    $response = [
        'success' => true,
        'appointment_id' => $appointmentId,
        'technician_id' => $technicianId,
        'technician_name' => $technicianName,
        'is_primary' => $isPrimary,
        'message' => 'Technician is already assigned to this appointment'
    ];

    echo json_encode($response);
    exit;
}

// Insert the technician assignment into the appointment_technicians table
$insertStmt = $conn->prepare("INSERT INTO appointment_technicians (appointment_id, technician_id, is_primary) VALUES (?, ?, ?)");
$insertStmt->bind_param("iii", $appointmentId, $technicianId, $isPrimary);

$response = ['success' => false];

if ($insertStmt->execute()) {
    // Update the appointment status to assigned
    $updateStmt = $conn->prepare("UPDATE appointments SET status = 'assigned' WHERE appointment_id = ?");
    $updateStmt->bind_param("i", $appointmentId);
    $updateStmt->execute();

    // For backward compatibility, also update the technician_id in the appointments table if this is the primary technician
    if ($isPrimary) {
        $updateTechStmt = $conn->prepare("UPDATE appointments SET technician_id = ? WHERE appointment_id = ?");
        $updateTechStmt->bind_param("ii", $technicianId, $appointmentId);
        $updateTechStmt->execute();
    }

    $response['success'] = true;
    $response['appointment_id'] = $appointmentId;
    $response['technician_id'] = $technicianId;
    $response['technician_name'] = $technicianName;
    $response['is_primary'] = $isPrimary;

    // Check if notifications table exists before trying to create a notification
    $tableCheckQuery = "SHOW TABLES LIKE 'notifications'";
    $tableCheckResult = $conn->query($tableCheckQuery);

    if ($tableCheckResult && $tableCheckResult->num_rows > 0) {
        // Try to create notification for technician
        try {
            // Get the location address for the inspection
            $locationStmt = $conn->prepare("SELECT location_address FROM appointments WHERE appointment_id = ?");
            $locationStmt->bind_param("i", $appointmentId);
            $locationStmt->execute();
            $locationResult = $locationStmt->get_result();
            $locationData = $locationResult->fetch_assoc();
            $location = $locationData['location_address'] ?? 'Not specified';
            $locationStmt->close();

            // Use the new notification function for inspection assignments
            $notificationResult = notifyTechnicianAboutInspectionAssignment(
                $technicianId,
                $appointmentId,
                $clientName,
                $appointmentDate,
                $appointmentTime,
                $location
            );

            // Add notification info to response
            $response['notification_sent'] = $notificationResult;

            // Get all admin users to notify them about the technician assignment
            $adminQuery = "SELECT staff_id FROM office_staff";
            $adminResult = $conn->query($adminQuery);

            if ($adminResult && $adminResult->num_rows > 0) {
                while ($adminRow = $adminResult->fetch_assoc()) {
                    $adminId = $adminRow['staff_id'];

                    // Notify each admin about the technician assignment
                    notifyAdminAboutTechnicianAssignment(
                        $adminId,
                        $technicianId,
                        $technicianName,
                        $appointmentId,
                        $clientName
                    );
                }

                $response['admin_notifications_sent'] = true;
            }
        } catch (Exception $e) {
            // Log the error but don't fail the assignment
            $response['notification_error'] = $e->getMessage();
        }
    } else {
        $response['notification_status'] = 'Notifications table not found';
    }
} else {
    $response['message'] = $conn->error;
}

$conn->close();

// Clean any unexpected output
ob_end_clean();

// Send the JSON response
echo json_encode($response);
exit;
?>