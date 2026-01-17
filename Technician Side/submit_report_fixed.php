<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../notification_functions.php';

// Set proper content type for JSON response
header('Content-Type: application/json');
// Ensure no output buffering issues
ob_clean();

// Create a log file for debugging
$log_file = __DIR__ . '/../logs/submit_report_fixed.log';
if (!file_exists(__DIR__ . '/../logs/')) {
    mkdir(__DIR__ . '/../logs/', 0777, true);
}

// Log function for easier debugging
function log_debug($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    error_log($message);
}

// Log database connection status
log_debug("Database connection status: " . ($conn ? "Connected" : "Not connected"));
if ($conn) {
    log_debug("Database info: " . $conn->server_info);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log the incoming request for debugging
    log_debug("Received POST request to submit_report_fixed.php");

    // Log all POST data for debugging
    log_debug("Full POST data: " . print_r($_POST, true));

    // Log all FILES data for debugging
    if (!empty($_FILES)) {
        log_debug("FILES data: " . print_r($_FILES, true));
    } else {
        log_debug("No files uploaded");
    }

    // Validate required fields
    if (!isset($_POST['appointment_id']) || empty($_POST['appointment_id'])) {
        log_debug("Error: Missing appointment ID");
        echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
        exit;
    }

    $appointment_id = $_POST['appointment_id'];
    log_debug("Processing appointment ID: " . $appointment_id);

    // Automatically set the current time as the end_time
    $end_time = date('H:i:s');
    $area = $_POST['area'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $recommendation = $_POST['recommendation'] ?? '';
    $problem_area = $_POST['problem_area'] ?? '';

    // Handle chemical recommendations
    $chemical_recommendations = $_POST['selected_chemicals'] ?? '';

    // Log the chemical recommendations for debugging
    if (!empty($chemical_recommendations)) {
        log_debug("Chemical recommendations received: " . substr($chemical_recommendations, 0, 100) . (strlen($chemical_recommendations) > 100 ? '...' : ''));
        log_debug("Chemical recommendations type: " . gettype($chemical_recommendations));
        log_debug("Chemical recommendations length: " . strlen($chemical_recommendations));
    } else {
        log_debug("No chemical recommendations received");
    }

    // Get job order related fields
    $preferred_date = $_POST['preferred_date'] ?? '';
    $preferred_time = $_POST['preferred_time'] ?? '';
    $frequency = $_POST['frequency'] ?? 'one-time';

    // Process pest types, including the "Other" field if specified
    $pest_types = [];
    if (isset($_POST['pest_types'])) {
        $pest_types = $_POST['pest_types'];

        // If "Others" is selected and other_pest_type is provided, replace "Others" with the specific value
        if (in_array('Others', $pest_types) && !empty($_POST['other_pest_type'])) {
            $otherIndex = array_search('Others', $pest_types);
            $pest_types[$otherIndex] = 'Others: ' . $_POST['other_pest_type'];
        }
    }
    $pest_types = implode(', ', $pest_types);

    // Process work types, including the "Other" field if specified
    $work_types = [];
    if (isset($_POST['type_of_work'])) {
        $work_types = $_POST['type_of_work'];

        // If "Other" is selected and other_work_type is provided, replace "Other" with the specific value
        if (in_array('Other', $work_types) && !empty($_POST['other_work_type'])) {
            $otherIndex = array_search('Other', $work_types);
            $work_types[$otherIndex] = 'Other: ' . $_POST['other_work_type'];
        }
    }
    $type_of_work = implode(', ', $work_types);

    $attachments = [];

    // Handle file uploads
    if (!empty($_FILES['attachments'])) {
        $uploadDir = '../uploads/';

        // Create uploads directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['attachments']['error'][$key] === 0) {
                $fileName = uniqid() . '_' . basename($_FILES['attachments']['name'][$key]);
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $attachments[] = $fileName;
                } else {
                    log_debug("Failed to move uploaded file: $tmpName to $targetPath");
                }
            } else {
                log_debug("File upload error: " . $_FILES['attachments']['error'][$key]);
            }
        }
    }

    // Insert report
    log_debug("Preparing SQL statement for report insertion");
    $stmt = $conn->prepare("
        INSERT INTO assessment_report
        (appointment_id, end_time, area, notes, recommendation, attachments, pest_types, problem_area,
         preferred_date, preferred_time, frequency, chemical_recommendations, type_of_work)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        log_debug("Error preparing statement: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $attachmentsStr = implode(',', $attachments);
    log_debug("Binding parameters for SQL statement");
    log_debug("appointment_id: $appointment_id, end_time: $end_time, area: $area");
    log_debug("pest_types: $pest_types, problem_area: $problem_area");
    log_debug("preferred_date: $preferred_date, preferred_time: $preferred_time, frequency: $frequency");
    log_debug("type_of_work: $type_of_work");

    $stmt->bind_param("issssssssssss", $appointment_id, $end_time, $area, $notes, $recommendation,
                     $attachmentsStr, $pest_types, $problem_area, $preferred_date,
                     $preferred_time, $frequency, $chemical_recommendations, $type_of_work);

    // Try to execute the statement
    try {
        log_debug("Attempting to execute SQL statement for report submission");
        if ($stmt->execute()) {
            log_debug("SQL statement executed successfully");
            // Get the report ID
            $report_id = $conn->insert_id;
            log_debug("Report inserted successfully with ID: $report_id");

            // Update appointment status to completed
            $updateResult = $conn->query("UPDATE appointments SET status = 'completed' WHERE appointment_id = $appointment_id");
            log_debug("Appointment status update result: " . ($updateResult ? "Success" : "Failed: " . $conn->error));

            // Return success response with chemical info
            $chemicals_saved = !empty($chemical_recommendations);
            $response = [
                'success' => true,
                'report_id' => $report_id,
                'chemicals_saved' => $chemicals_saved,
                'chemicals_count' => $chemicals_saved ? count(json_decode($chemical_recommendations, true)) : 0
            ];
            log_debug("Sending success response: " . json_encode($response));
            echo json_encode($response);
            exit;
        } else {
            log_debug("Error executing statement: " . $stmt->error);
            $response = ['success' => false, 'message' => 'Database error: ' . $stmt->error];
            log_debug("Sending error response: " . json_encode($response));
            echo json_encode($response);
            exit;
        }
    } catch (Exception $e) {
        log_debug("Exception during report submission: " . $e->getMessage());
        log_debug("Stack trace: " . $e->getTraceAsString());

        $response = [
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage(),
            'details' => 'Please try again or contact support if the issue persists.'
        ];
        log_debug("Sending exception response: " . json_encode($response));
        echo json_encode($response);
        exit;
    }
}
?>
