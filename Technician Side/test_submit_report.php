<?php
// This is a test file to check if the submit_report_simple.php is working correctly

// Create a log file for debugging
$log_file = __DIR__ . '/../logs/test_submit_report.log';
if (!file_exists(__DIR__ . '/../logs/')) {
    mkdir(__DIR__ . '/../logs/', 0777, true);
}

// Log function for easier debugging
function log_debug($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

// Log the start of the test
log_debug("Starting test of submit_report_simple.php");

// Check if the submit_report_simple.php file exists
if (file_exists(__DIR__ . '/submit_report_simple.php')) {
    log_debug("submit_report_simple.php exists");
} else {
    log_debug("submit_report_simple.php does not exist");
    die("submit_report_simple.php does not exist");
}

// Create a test form submission
$_POST = array(
    'appointment_id' => '101',
    'area' => '100',
    'notes' => 'Test notes',
    'recommendation' => 'Test recommendation',
    'problem_area' => 'Test problem area',
    'pest_types' => array('Flies', 'Ants'),
    'type_of_work' => array('General Pest Control'),
    'preferred_date' => '2025-05-14',
    'preferred_time' => '10:00',
    'frequency' => 'one-time',
    'selected_chemicals' => '[{"id":"31","name":"Imidaclopred","type":"Insecticide","dosage":"20","dosage_unit":"ml","target_pest":"Crawling & Flying Pest"}]'
);

// Log the test form data
log_debug("Test form data: " . print_r($_POST, true));

// Set the request method to POST
$_SERVER['REQUEST_METHOD'] = 'POST';

// Include the submit_report_simple.php file
log_debug("Including submit_report_simple.php");
ob_start();
include __DIR__ . '/submit_report_simple.php';
$output = ob_get_clean();

// Log the output
log_debug("Output from submit_report_simple.php: " . $output);

// Parse the JSON output
$result = json_decode($output, true);

// Log the parsed result
log_debug("Parsed result: " . print_r($result, true));

// Check if the submission was successful
if ($result && isset($result['success']) && $result['success']) {
    log_debug("Test successful: Report submitted successfully");
    echo "Test successful: Report submitted successfully";
} else {
    log_debug("Test failed: " . ($result['message'] ?? 'Unknown error'));
    echo "Test failed: " . ($result['message'] ?? 'Unknown error');
}
