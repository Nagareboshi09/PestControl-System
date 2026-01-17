<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// Set proper content type for JSON response
header('Content-Type: application/json');

// Create a log file for debugging
$log_file = __DIR__ . '/logs/debug_submit_report.log';
if (!file_exists(__DIR__ . '/logs/')) {
    mkdir(__DIR__ . '/logs/', 0777, true);
}

// Log function for easier debugging
function log_debug($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

// Log database connection status
log_debug("Database connection status: " . ($conn ? "Connected" : "Not connected"));
if ($conn) {
    log_debug("Database info: " . $conn->server_info);
}

// Check if the assessment_report table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'assessment_report'");
log_debug("Table assessment_report exists: " . ($tableExists->num_rows > 0 ? "Yes" : "No"));

if ($tableExists->num_rows > 0) {
    // Get the columns of the assessment_report table
    $result = $conn->query("DESCRIBE assessment_report");
    if (!$result) {
        log_debug("Error describing table: " . $conn->error);
    } else {
        log_debug("Columns in assessment_report table:");
        while ($row = $result->fetch_assoc()) {
            log_debug($row['Field'] . " - " . $row['Type']);
        }
    }
}

// Check if the submit_report.php file exists
$submit_report_path = __DIR__ . '/Technician Side/submit_report.php';
log_debug("submit_report.php exists: " . (file_exists($submit_report_path) ? "Yes" : "No"));

// Check if the logs directory exists and is writable
$logs_dir = __DIR__ . '/logs';
log_debug("Logs directory exists: " . (file_exists($logs_dir) ? "Yes" : "No"));
log_debug("Logs directory is writable: " . (is_writable($logs_dir) ? "Yes" : "No"));

// Check if the submit_report_debug.log file exists
$submit_report_log = __DIR__ . '/logs/submit_report_debug.log';
log_debug("submit_report_debug.log exists: " . (file_exists($submit_report_log) ? "Yes" : "No"));
if (file_exists($submit_report_log)) {
    log_debug("submit_report_debug.log size: " . filesize($submit_report_log) . " bytes");
    log_debug("Last few lines of submit_report_debug.log:");
    $log_content = file_get_contents($submit_report_log);
    $lines = explode("\n", $log_content);
    $last_lines = array_slice($lines, -10);
    foreach ($last_lines as $line) {
        log_debug("LOG: " . $line);
    }
}

// Check if the uploads directory exists and is writable
$uploads_dir = __DIR__ . '/uploads';
log_debug("Uploads directory exists: " . (file_exists($uploads_dir) ? "Yes" : "No"));
log_debug("Uploads directory is writable: " . (is_writable($uploads_dir) ? "Yes" : "No"));

// Return the debug information
echo json_encode([
    'success' => true,
    'message' => 'Debug information has been logged to ' . $log_file
]);
?>
