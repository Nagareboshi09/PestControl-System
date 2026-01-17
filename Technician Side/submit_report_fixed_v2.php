<?php
session_start();
require_once __DIR__ . '/../db_connect.php';

// Set proper content type for JSON response
header('Content-Type: application/json');

// Create a log file for debugging
$log_file = __DIR__ . '/../logs/submit_report_fixed_v2.log';
if (!file_exists(__DIR__ . '/../logs/')) {
    mkdir(__DIR__ . '/../logs/', 0777, true);
}

// Log function for easier debugging
function log_debug($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

// Log the incoming request
log_debug("Received request to submit_report_fixed_v2.php");
log_debug("PHP Version: " . phpversion());
log_debug("Session ID: " . session_id());
log_debug("Session data: " . print_r($_SESSION, true));

// Set maximum execution time to 60 seconds
set_time_limit(60);

// Ensure no output buffering issues
if (ob_get_level()) ob_end_clean();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log all POST data for debugging
    log_debug("POST data: " . print_r($_POST, true));

    // Log all FILES data for debugging
    if (!empty($_FILES)) {
        log_debug("FILES data: " . print_r($_FILES, true));
    } else {
        log_debug("No files uploaded");
    }

    try {
        // Get required fields
        $appointment_id = $_POST['appointment_id'] ?? '';
        $area = $_POST['area'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $recommendation = $_POST['recommendation'] ?? '';
        $problem_area = $_POST['problem_area'] ?? '';
        $pest_types = isset($_POST['pest_types']) ? implode(', ', $_POST['pest_types']) : '';
        $type_of_work = isset($_POST['type_of_work']) ? implode(', ', $_POST['type_of_work']) : '';
        $preferred_date = $_POST['preferred_date'] ?? '';
        $preferred_time = $_POST['preferred_time'] ?? '';
        $frequency = $_POST['frequency'] ?? 'one-time';
        $chemical_recommendations = $_POST['selected_chemicals'] ?? '';

        // Validate required fields
        if (empty($appointment_id)) {
            log_debug("Error: Missing appointment ID");
            throw new Exception("Missing appointment ID");
        }

        if (empty($area)) {
            log_debug("Error: Missing area");
            throw new Exception("Missing area");
        }

        // Check database connection
        if (!$conn) {
            log_debug("Error: Database connection failed");
            throw new Exception("Database connection failed");
        }
        log_debug("Database connection successful");

        // Check if the appointment exists
        $check_stmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ?");
        $check_stmt->bind_param("i", $appointment_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            log_debug("Error: Appointment not found with ID: $appointment_id");
            throw new Exception("Appointment not found with ID: $appointment_id");
        }
        log_debug("Appointment found with ID: $appointment_id");

        // Check the structure of the assessment_report table
        $columns_check = $conn->query("DESCRIBE assessment_report");
        $columns = [];
        while ($row = $columns_check->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        log_debug("Columns in assessment_report table: " . implode(", ", $columns));

        // Insert into database
        $stmt = $conn->prepare("
            INSERT INTO assessment_report
            (appointment_id, end_time, area, notes, recommendation, pest_types, problem_area,
             preferred_date, preferred_time, frequency, chemical_recommendations, type_of_work)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            log_debug("Prepare statement error: " . $conn->error);
            throw new Exception("Prepare statement error: " . $conn->error);
        }

        $end_time = date('H:i:s');
        log_debug("End time: $end_time");

        // Log all values being bound to the statement
        log_debug("Values being bound to statement:");
        log_debug("appointment_id: $appointment_id");
        log_debug("end_time: $end_time");
        log_debug("area: $area");
        log_debug("notes: $notes");
        log_debug("recommendation: $recommendation");
        log_debug("pest_types: $pest_types");
        log_debug("problem_area: $problem_area");
        log_debug("preferred_date: $preferred_date");
        log_debug("preferred_time: $preferred_time");
        log_debug("frequency: $frequency");
        log_debug("chemical_recommendations length: " . strlen($chemical_recommendations));
        log_debug("type_of_work: $type_of_work");

        $stmt->bind_param("isssssssssss",
            $appointment_id, $end_time, $area, $notes, $recommendation, $pest_types, $problem_area,
            $preferred_date, $preferred_time, $frequency, $chemical_recommendations, $type_of_work
        );

        if ($stmt->error) {
            log_debug("Bind param error: " . $stmt->error);
            throw new Exception("Bind param error: " . $stmt->error);
        }

        $result = $stmt->execute();

        if ($result) {
            $report_id = $conn->insert_id;
            log_debug("Report inserted successfully with ID: $report_id");

            // Update appointment status
            $update_result = $conn->query("UPDATE appointments SET status = 'completed' WHERE appointment_id = $appointment_id");
            if (!$update_result) {
                log_debug("Warning: Failed to update appointment status: " . $conn->error);
            } else {
                log_debug("Appointment status updated successfully");
            }

            // Return success response
            $response = [
                'success' => true,
                'message' => 'Report submitted successfully',
                'report_id' => $report_id,
                'chemicals_saved' => !empty($chemical_recommendations),
                'chemicals_count' => substr_count($chemical_recommendations, 'id')
            ];
            log_debug("Sending success response: " . json_encode($response));
            echo json_encode($response);
        } else {
            log_debug("Error executing statement: " . $stmt->error);
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $stmt->error
            ]);
        }
    } catch (Exception $e) {
        log_debug("Exception: " . $e->getMessage());
        log_debug("Stack trace: " . $e->getTraceAsString());
        echo json_encode([
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage()
        ]);
    }
} else {
    log_debug("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>
