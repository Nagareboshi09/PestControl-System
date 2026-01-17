<?php
// Turn off all error reporting and disable display of errors
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to capture any unexpected output
ob_start();

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

// Get the parameters
$appointmentId = $data['appointment_id'];
$technicianId = $data['technician_id'];
$isPrimary = isset($data['is_primary']) ? (bool)$data['is_primary'] : false;

try {
    // Connect to the database
    $conn = new mysqli("localhost", "root", "", "macj_pest_control");

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get technician name
    $techStmt = $conn->prepare("SELECT username FROM technicians WHERE technician_id = ?");
    if (!$techStmt) {
        throw new Exception("Error preparing technician statement: " . $conn->error);
    }

    $techStmt->bind_param("i", $technicianId);
    $techStmt->execute();
    $techResult = $techStmt->get_result();

    if ($techResult->num_rows === 0) {
        throw new Exception("Technician not found");
    }

    $techData = $techResult->fetch_assoc();
    $technicianName = $techData['username'];
    $techStmt->close();

    // Check if the appointment exists
    $apptStmt = $conn->prepare("SELECT * FROM appointments WHERE appointment_id = ?");
    if (!$apptStmt) {
        throw new Exception("Error preparing appointment statement: " . $conn->error);
    }

    $apptStmt->bind_param("i", $appointmentId);
    $apptStmt->execute();
    $apptResult = $apptStmt->get_result();

    if ($apptResult->num_rows === 0) {
        throw new Exception("Appointment not found");
    }

    $apptStmt->close();

    // Check if the appointment_technicians table exists
    $tableCheckQuery = "SHOW TABLES LIKE 'appointment_technicians'";
    $tableCheckResult = $conn->query($tableCheckQuery);

    if ($tableCheckResult->num_rows === 0) {
        // Create the table if it doesn't exist
        $createTableSQL = "CREATE TABLE IF NOT EXISTS appointment_technicians (
            appointment_id INT(11) NOT NULL,
            technician_id INT(11) NOT NULL,
            is_primary BOOLEAN NOT NULL DEFAULT 0,
            PRIMARY KEY (appointment_id, technician_id)
        )";

        if (!$conn->query($createTableSQL)) {
            throw new Exception("Failed to create appointment_technicians table: " . $conn->error);
        }
    } else {
        // Check if the is_primary column exists
        $columnCheckQuery = "SHOW COLUMNS FROM appointment_technicians LIKE 'is_primary'";
        $columnCheckResult = $conn->query($columnCheckQuery);

        if ($columnCheckResult->num_rows === 0) {
            // Add the is_primary column if it doesn't exist
            $alterTableSQL = "ALTER TABLE appointment_technicians ADD COLUMN is_primary BOOLEAN NOT NULL DEFAULT 0";

            if (!$conn->query($alterTableSQL)) {
                throw new Exception("Failed to add is_primary column: " . $conn->error);
            }
        }
    }

    // If this is the primary technician, try to unset any existing primary technicians
    if ($isPrimary) {
        try {
            $unsetPrimaryStmt = $conn->prepare("UPDATE appointment_technicians SET is_primary = 0 WHERE appointment_id = ?");
            $unsetPrimaryStmt->bind_param("i", $appointmentId);
            $unsetPrimaryStmt->execute();
            $unsetPrimaryStmt->close();
        } catch (Exception $e) {
            // Ignore errors here, as the column might not exist yet
        }
    }

    // Check if this technician is already assigned to this appointment
    $checkStmt = $conn->prepare("SELECT * FROM appointment_technicians WHERE appointment_id = ? AND technician_id = ?");
    $checkStmt->bind_param("ii", $appointmentId, $technicianId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        // Technician is already assigned, just update the primary status if needed
        if ($isPrimary) {
            try {
                $updateStmt = $conn->prepare("UPDATE appointment_technicians SET is_primary = 1 WHERE appointment_id = ? AND technician_id = ?");
                $updateStmt->bind_param("ii", $appointmentId, $technicianId);
                $updateStmt->execute();
                $updateStmt->close();
            } catch (Exception $e) {
                // Ignore errors here, as the column might not exist yet
            }
        }

        $checkStmt->close();

        // Clean any output buffer
        ob_end_clean();

        echo json_encode([
            'success' => true,
            'appointment_id' => $appointmentId,
            'technician_id' => $technicianId,
            'technician_name' => $technicianName,
            'is_primary' => $isPrimary,
            'message' => 'Technician is already assigned to this appointment'
        ]);
        exit;
    }

    $checkStmt->close();

    // Insert the technician assignment into the appointment_technicians table
    try {
        // First try with is_primary column
        $insertStmt = $conn->prepare("INSERT INTO appointment_technicians (appointment_id, technician_id, is_primary) VALUES (?, ?, ?)");
        $insertStmt->bind_param("iii", $appointmentId, $technicianId, $isPrimary);

        if (!$insertStmt->execute()) {
            // If it fails, try without is_primary column
            if (strpos($insertStmt->error, "Unknown column 'is_primary'") !== false) {
                $insertStmt->close();

                $insertStmt = $conn->prepare("INSERT INTO appointment_technicians (appointment_id, technician_id) VALUES (?, ?)");
                $insertStmt->bind_param("ii", $appointmentId, $technicianId);

                if (!$insertStmt->execute()) {
                    throw new Exception("Failed to assign technician: " . $insertStmt->error);
                }
            } else {
                throw new Exception("Failed to assign technician: " . $insertStmt->error);
            }
        }

        $insertStmt->close();
    } catch (Exception $e) {
        // If all else fails, try a direct query
        $insertSQL = "INSERT INTO appointment_technicians (appointment_id, technician_id) VALUES ($appointmentId, $technicianId)";
        if (!$conn->query($insertSQL)) {
            throw new Exception("Failed to assign technician: " . $conn->error);
        }
    }

    // Update the appointment status to assigned
    $updateStmt = $conn->prepare("UPDATE appointments SET status = 'assigned' WHERE appointment_id = ?");
    $updateStmt->bind_param("i", $appointmentId);
    $updateStmt->execute();
    $updateStmt->close();

    // For backward compatibility, also update the technician_id in the appointments table if this is the primary technician
    if ($isPrimary) {
        $updateTechStmt = $conn->prepare("UPDATE appointments SET technician_id = ? WHERE appointment_id = ?");
        $updateTechStmt->bind_param("ii", $technicianId, $appointmentId);
        $updateTechStmt->execute();
        $updateTechStmt->close();
    }

    // Try to include notification functions if not already included
    if (!function_exists('notifyTechnicianAboutAssignment')) {
        try {
            require_once '../notification_functions.php';
        } catch (Exception $e) {
            // Ignore if can't load
        }
    }

    // Get appointment details for notifications
    $notificationData = [];
    try {
        $apptDataStmt = $conn->prepare("SELECT client_name, preferred_date, preferred_time FROM appointments WHERE appointment_id = ?");
        $apptDataStmt->bind_param("i", $appointmentId);
        $apptDataStmt->execute();
        $apptDataResult = $apptDataStmt->get_result();
        if ($apptDataResult->num_rows > 0) {
            $notificationData = $apptDataResult->fetch_assoc();
        }
        $apptDataStmt->close();
    } catch (Exception $e) {
        // Ignore errors
    }

    $response = [
        'success' => true,
        'appointment_id' => $appointmentId,
        'technician_id' => $technicianId,
        'technician_name' => $technicianName,
        'is_primary' => $isPrimary,
        'message' => 'Technician assigned successfully'
    ];

    // Check if notifications table exists before trying to create a notification
    if (function_exists('notifyTechnicianAboutAssignment') && !empty($notificationData)) {
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
                    $notificationData['client_name'],
                    $notificationData['preferred_date'],
                    $notificationData['preferred_time'],
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
                            $notificationData['client_name']
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
    }

    // Close the database connection
    $conn->close();

    // Clean any output buffer
    ob_end_clean();

    // Send the success response
    echo json_encode($response);

} catch (Exception $e) {
    // Clean any output buffer
    ob_end_clean();

    // Send the error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
