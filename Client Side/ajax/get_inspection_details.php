<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../../db_connect.php';

// Log request data for debugging
$log_file = fopen("inspection_details_log.txt", "a");
fwrite($log_file, "Request time: " . date('Y-m-d H:i:s') . "\n");
fwrite($log_file, "POST data: " . print_r($_POST, true) . "\n");
fwrite($log_file, "SESSION data: " . print_r($_SESSION, true) . "\n");
fclose($log_file);

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if report ID is provided
if (!isset($_POST['report_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Report ID is required']);
    exit;
}

$reportId = $_POST['report_id'];
$clientId = $_SESSION['client_id'];

// Fetch inspection report details
$stmt = $conn->prepare("
    SELECT
        ar.report_id,
        ar.end_time,
        ar.area,
        ar.notes as report_notes,
        ar.attachments,
        ar.created_at as report_date,
        ar.pest_types,
        ar.problem_area,
        a.appointment_id,
        a.client_id,
        a.client_name,
        a.location_address,
        a.kind_of_place,
        a.preferred_date,
        a.preferred_time,
        a.pest_problems,
        a.notes as client_notes,
        t.technician_id,
        t.username as technician_name,
        t.tech_contact_number as technician_contact,
        t.tech_fname as technician_fname,
        t.tech_lname as technician_lname,
        t.technician_picture
    FROM assessment_report ar
    JOIN appointments a ON ar.appointment_id = a.appointment_id
    LEFT JOIN technicians t ON a.technician_id = t.technician_id
    WHERE ar.report_id = ? AND a.client_id = ?
");

$stmt->bind_param("ii", $reportId, $clientId);

// Execute query and check for errors
if (!$stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    exit;
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Inspection report not found']);
    exit;
}

$report = $result->fetch_assoc();

// Return inspection report details
header('Content-Type: application/json');
echo json_encode(['success' => true, 'report' => $report]);
?>
