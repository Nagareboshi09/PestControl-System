<?php
session_start();
require_once '../db_connect.php';

// Create a log file for debugging
$log_file = __DIR__ . '/../logs/test_report_submission.log';
if (!file_exists(__DIR__ . '/../logs/')) {
    mkdir(__DIR__ . '/../logs/', 0777, true);
}

// Log function for easier debugging
function log_debug($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

// Set proper content type for JSON response
header('Content-Type: application/json');

// Log the start of the script
log_debug("Test report submission script started");
log_debug("PHP Version: " . phpversion());
log_debug("Session ID: " . session_id());
log_debug("Session data: " . print_r($_SESSION, true));

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    log_debug("Received POST request");
    
    // Log all POST data for debugging
    log_debug("POST data: " . print_r($_POST, true));
    
    // Log all FILES data for debugging
    if (!empty($_FILES)) {
        log_debug("FILES data: " . print_r($_FILES, true));
    } else {
        log_debug("No files uploaded");
    }
    
    try {
        // Check database connection
        log_debug("Checking database connection");
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        log_debug("Database connection successful");
        
        // Check if the assessment_report table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'assessment_report'");
        if ($table_check->num_rows === 0) {
            throw new Exception("assessment_report table does not exist");
        }
        log_debug("assessment_report table exists");
        
        // Check the structure of the assessment_report table
        $columns_check = $conn->query("DESCRIBE assessment_report");
        $columns = [];
        while ($row = $columns_check->fetch_assoc()) {
            $columns[] = $row['Field'] . ' - ' . $row['Type'];
        }
        log_debug("Columns in assessment_report table: " . implode(", ", $columns));
        
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
        
        // Log the values
        log_debug("appointment_id: $appointment_id");
        log_debug("area: $area");
        log_debug("notes: $notes");
        log_debug("recommendation: $recommendation");
        log_debug("problem_area: $problem_area");
        log_debug("pest_types: $pest_types");
        log_debug("type_of_work: $type_of_work");
        log_debug("preferred_date: $preferred_date");
        log_debug("preferred_time: $preferred_time");
        log_debug("frequency: $frequency");
        log_debug("chemical_recommendations length: " . strlen($chemical_recommendations));
        
        // Prepare a test insert statement
        log_debug("Preparing test insert statement");
        $end_time = date('H:i:s');
        
        // Try to insert a test record
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
        
        log_debug("Statement prepared successfully");
        
        // Bind parameters
        log_debug("Binding parameters");
        $stmt->bind_param("isssssssssss",
            $appointment_id, $end_time, $area, $notes, $recommendation, $pest_types, $problem_area,
            $preferred_date, $preferred_time, $frequency, $chemical_recommendations, $type_of_work
        );
        
        if ($stmt->error) {
            log_debug("Bind param error: " . $stmt->error);
            throw new Exception("Bind param error: " . $stmt->error);
        }
        
        log_debug("Parameters bound successfully");
        
        // Execute the statement
        log_debug("Executing statement");
        $result = $stmt->execute();
        
        if ($result) {
            $report_id = $conn->insert_id;
            log_debug("Test insert successful with ID: $report_id");
            
            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Test insert successful',
                'report_id' => $report_id
            ]);
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
