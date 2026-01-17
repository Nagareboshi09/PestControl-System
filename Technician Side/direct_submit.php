<?php
session_start();
require_once '../db_connect.php';
require_once '../notification_functions.php';

// Set proper content type for JSON response
header('Content-Type: application/json');

// Create a log file for debugging
$log_file = __DIR__ . '/../logs/direct_submit.log';
if (!file_exists(__DIR__ . '/../logs/')) {
    mkdir(__DIR__ . '/../logs/', 0777, true);
}

// Log function for easier debugging
function log_debug($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

// Log the start of the script
log_debug("Direct submit script started");
log_debug("PHP Version: " . phpversion());
log_debug("Session ID: " . session_id());
log_debug("Session data: " . print_r($_SESSION, true));

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    log_debug("Received POST request");

    // Log all POST data for debugging
    log_debug("POST data: " . print_r($_POST, true));

    try {
        // Get required fields with fallbacks to prevent errors
        $appointment_id = isset($_POST['appointment_id']) ? $_POST['appointment_id'] : '';
        $area = isset($_POST['area']) ? $_POST['area'] : '';
        $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
        $recommendation = isset($_POST['recommendation']) ? $_POST['recommendation'] : '';
        $problem_area = isset($_POST['problem_area']) ? $_POST['problem_area'] : '';
        $pest_types = isset($_POST['pest_types']) ? (is_array($_POST['pest_types']) ? implode(', ', $_POST['pest_types']) : $_POST['pest_types']) : '';
        $type_of_work = isset($_POST['type_of_work']) ? (is_array($_POST['type_of_work']) ? implode(', ', $_POST['type_of_work']) : $_POST['type_of_work']) : '';
        $preferred_date = isset($_POST['preferred_date']) ? $_POST['preferred_date'] : date('Y-m-d');
        $preferred_time = isset($_POST['preferred_time']) ? $_POST['preferred_time'] : date('H:i:s');
        $frequency = isset($_POST['frequency']) ? $_POST['frequency'] : 'one-time';
        $chemical_recommendations = isset($_POST['selected_chemicals']) ? $_POST['selected_chemicals'] : '';

        // Validate appointment_id
        if (empty($appointment_id)) {
            throw new Exception("Appointment ID is required");
        }

        // Validate area
        if (empty($area)) {
            throw new Exception("Area is required");
        }

        // Set end time to current time
        $end_time = date('H:i:s');

        // Log the values
        log_debug("Validated data:");
        log_debug("appointment_id: $appointment_id");
        log_debug("area: $area");
        log_debug("end_time: $end_time");

        // Check database connection
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        log_debug("Database connection successful");

        // Check if the appointment exists
        $check_stmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ?");
        if (!$check_stmt) {
            throw new Exception("Prepare statement error: " . $conn->error);
        }

        $check_stmt->bind_param("i", $appointment_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {
            throw new Exception("Appointment not found with ID: $appointment_id");
        }
        log_debug("Appointment found with ID: $appointment_id");

        // Begin transaction
        $conn->begin_transaction();

        try {
            // Insert into assessment_report table
            $stmt = $conn->prepare("
                INSERT INTO assessment_report
                (appointment_id, end_time, area, notes, recommendation, pest_types, problem_area,
                preferred_date, preferred_time, frequency, chemical_recommendations, type_of_work)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception("Prepare statement error: " . $conn->error);
            }

            $stmt->bind_param("isssssssssss",
                $appointment_id, $end_time, $area, $notes, $recommendation, $pest_types, $problem_area,
                $preferred_date, $preferred_time, $frequency, $chemical_recommendations, $type_of_work
            );

            if (!$stmt->execute()) {
                throw new Exception("Execute error: " . $stmt->error);
            }

            $report_id = $conn->insert_id;
            log_debug("Report inserted successfully with ID: $report_id");

            // Update appointment status
            $update_stmt = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE appointment_id = ?");
            if (!$update_stmt) {
                throw new Exception("Prepare update statement error: " . $conn->error);
            }

            $update_stmt->bind_param("i", $appointment_id);
            if (!$update_stmt->execute()) {
                throw new Exception("Execute update error: " . $update_stmt->error);
            }

            log_debug("Appointment status updated successfully");

            // Commit transaction
            $conn->commit();

            // Send notifications to client and admin
            log_debug("Sending notifications for report ID: $report_id");

            try {
                // Get technician information
                $technician_id = $_SESSION['user_id'];
                $technician_name = $_SESSION['username'] ?? 'Technician';
                log_debug("Technician ID: $technician_id, Name: $technician_name");

                // Get client information from the appointment
                $client_query = $conn->prepare("
                    SELECT a.client_id, a.client_name
                    FROM appointments a
                    WHERE a.appointment_id = ?
                ");
                $client_query->bind_param("i", $appointment_id);
                $client_query->execute();
                $client_result = $client_query->get_result();
                $client_data = $client_result->fetch_assoc();

                if ($client_data) {
                    $client_id = $client_data['client_id'];
                    log_debug("Client ID: $client_id, Name: {$client_data['client_name']}");

                    // Notify client about the report
                    $client_notification = notifyClientAboutReport($client_id, $appointment_id, $report_id);
                    log_debug("Client notification result: " . ($client_notification ? "success" : "failed"));
                } else {
                    log_debug("Client information not found for appointment ID: $appointment_id");
                }

                // Get all admin IDs and send notifications
                $admin_query = $conn->query("SELECT staff_id FROM office_staff");
                $admin_notifications_sent = 0;

                if ($admin_query && $admin_query->num_rows > 0) {
                    while ($admin = $admin_query->fetch_assoc()) {
                        $admin_id = $admin['staff_id'];
                        log_debug("Sending notification to admin ID: $admin_id");

                        // Notify admin about the new report
                        log_debug("Calling notifyAdminAboutNewReport with admin_id=$admin_id, report_id=$report_id, technician_name=$technician_name");
                        $admin_notification = notifyAdminAboutNewReport($admin_id, $report_id, $technician_name);
                        log_debug("notifyAdminAboutNewReport returned: " . var_export($admin_notification, true));

                        if ($admin_notification) {
                            $admin_notifications_sent++;
                            log_debug("Admin notification sent successfully to admin ID: $admin_id");
                        } else {
                            log_debug("Failed to send notification to admin ID: $admin_id");
                        }
                    }
                    log_debug("Total admin notifications sent: $admin_notifications_sent");
                } else {
                    log_debug("No admin users found in the database");
                }
            } catch (Exception $notification_error) {
                log_debug("Error sending notifications: " . $notification_error->getMessage());
                // Continue even if notifications fail - don't affect the main transaction
            }

            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Report submitted successfully',
                'report_id' => $report_id
            ]);

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            throw $e;
        }

    } catch (Exception $e) {
        log_debug("Exception: " . $e->getMessage());
        log_debug("Stack trace: " . $e->getTraceAsString());

        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    log_debug("Invalid request method: " . $_SERVER['REQUEST_METHOD']);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Only POST requests are accepted.'
    ]);
}
?>
