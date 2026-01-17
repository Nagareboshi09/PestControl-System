<?php
session_start();
require_once '../db_connect.php';

// Set up error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Create a log file for debugging
$log_file = '../logs/schedule_debug.log';
if (!file_exists('../logs/')) {
    mkdir('../logs/', 0777, true);
}

// Function to log messages
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    error_log("SCHEDULE_DEBUG: " . $message); // Also log to PHP error log with a prefix
}

// Log request information
log_message("Schedule request received: " . json_encode($_GET));

// Check if user is logged in and is an admin or office staff
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'admin' && $_SESSION['role'] !== 'office_staff')) {
    log_message("Unauthorized access attempt: User not logged in or not authorized");
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Check if report_id is provided
if (!isset($_GET['report_id']) || empty($_GET['report_id'])) {
    log_message("Error: Report ID is required but was not provided");
    echo json_encode([
        'success' => false,
        'message' => 'Report ID is required'
    ]);
    exit;
}

$report_id = intval($_GET['report_id']);
log_message("Fetching schedule for report ID: $report_id");

try {
    // Get the preferred date, time, and frequency from the assessment report
    $query = "SELECT preferred_date, preferred_time, frequency FROM assessment_report WHERE report_id = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Log the query and result for debugging
    log_message("Query: " . $query . " with report_id = " . $report_id);
    log_message("Result rows: " . $result->num_rows);

    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        log_message("Retrieved schedule data from assessment_report: " . json_encode($data));

        // Check if preferred_date and preferred_time are empty
        if (empty($data['preferred_date']) || empty($data['preferred_time'])) {
            log_message("Preferred date or time is empty, trying to get from appointments table");

            // Try to get preferred_date and preferred_time from appointments table
            $query = "SELECT a.preferred_date, a.preferred_time
                    FROM assessment_report ar
                    JOIN appointments a ON ar.appointment_id = a.appointment_id
                    WHERE ar.report_id = ?";
            $stmt = $conn->prepare($query);

            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }

            $stmt->bind_param("i", $report_id);
            $stmt->execute();
            $app_result = $stmt->get_result();

            if ($app_result->num_rows > 0) {
                $app_data = $app_result->fetch_assoc();
                log_message("Retrieved date/time from appointments: " . json_encode($app_data));

                // Use appointment data if assessment report data is empty
                $data['preferred_date'] = empty($data['preferred_date']) ? $app_data['preferred_date'] : $data['preferred_date'];
                $data['preferred_time'] = empty($data['preferred_time']) ? $app_data['preferred_time'] : $data['preferred_time'];
            }
        }

        // Ensure frequency is set to a valid value
        if (empty($data['frequency'])) {
            $data['frequency'] = 'one-time';
            log_message("Frequency was empty, defaulting to 'one-time'");
        }

        // Format the response
        $response = [
            'success' => true,
            'preferred_date' => $data['preferred_date'],
            'preferred_time' => $data['preferred_time'],
            'frequency' => $data['frequency'],
            'source' => 'assessment_report'
        ];

        log_message("Sending response: " . json_encode($response));
        echo json_encode($response);
    } else {
        log_message("No schedule information found in assessment_report for report ID: $report_id");

        // If no data found in assessment_report, try to get it from the appointments table
        $query = "SELECT a.preferred_date, a.preferred_time, 'one-time' as frequency
                FROM assessment_report ar
                JOIN appointments a ON ar.appointment_id = a.appointment_id
                WHERE ar.report_id = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            log_message("Retrieved schedule data from appointments: " . json_encode($data));

            // Format the response
            $response = [
                'success' => true,
                'preferred_date' => $data['preferred_date'],
                'preferred_time' => $data['preferred_time'],
                'frequency' => $data['frequency'],
                'source' => 'appointments'
            ];

            log_message("Sending response from appointments: " . json_encode($response));
            echo json_encode($response);
        } else {
            log_message("No schedule information found in appointments for report ID: $report_id");

            // Try one more fallback - check if the report exists at all
            $check_query = "SELECT report_id FROM assessment_report WHERE report_id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("i", $report_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                log_message("Report exists but has no schedule information");
                echo json_encode([
                    'success' => false,
                    'message' => 'Report exists but has no schedule information',
                    'report_exists' => true
                ]);
            } else {
                log_message("Report does not exist");
                echo json_encode([
                    'success' => false,
                    'message' => 'Report does not exist',
                    'report_exists' => false
                ]);
            }

            if (isset($check_stmt)) {
                $check_stmt->close();
            }
        }
    }
} catch (Exception $e) {
    log_message("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching schedule information: ' . $e->getMessage()
    ]);
}

// Clean up
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
log_message("Request completed");
?>
