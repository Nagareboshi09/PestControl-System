<?php
session_start();
require_once '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if report ID is provided
if (!isset($_GET['report_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Report ID is required']);
    exit;
}

$reportId = $_GET['report_id'];
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
        t.technician_picture,
        tf.feedback_id,
        tf.rating,
        tf.comments as feedback_comments,
        tf.created_at as feedback_date,
        tf.technician_arrived,
        tf.job_completed,
        tf.verification_notes
    FROM assessment_report ar
    JOIN appointments a ON ar.appointment_id = a.appointment_id
    LEFT JOIN technicians t ON a.technician_id = t.technician_id
    LEFT JOIN technician_feedback tf ON ar.report_id = tf.report_id
    WHERE ar.report_id = ? AND a.client_id = ?
");

$stmt->bind_param("ii", $reportId, $clientId);
$stmt->execute();
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
