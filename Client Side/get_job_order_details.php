<?php
session_start();
require_once '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if job_order_id is provided
if (!isset($_GET['job_order_id']) || empty($_GET['job_order_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Job order ID is required']);
    exit;
}

$client_id = $_SESSION['client_id'];
$job_order_id = intval($_GET['job_order_id']);

// Fetch job order details
$stmt = $conn->prepare("
    SELECT
        jo.*,
        ar.report_id,
        ar.area,
        ar.notes as report_notes,
        ar.pest_types,
        ar.problem_area,
        a.location_address as property_address,
        a.status as appointment_status,
        jor.report_id as job_report_id,
        jor.observation_notes,
        jor.attachments as report_attachments,
        jor.created_at as report_created_at,
        t.technician_id,
        t.username as technician_name,
        t.tech_contact_number as technician_contact,
        t.tech_fname as technician_fname,
        t.tech_lname as technician_lname,
        t.technician_picture,
        jf.feedback_id,
        jf.rating,
        jf.comments as feedback_comments,
        jf.created_at as feedback_date,
        jf.technician_arrived,
        jf.job_completed,
        jf.verification_notes
    FROM job_order jo
    JOIN assessment_report ar ON jo.report_id = ar.report_id
    JOIN appointments a ON ar.appointment_id = a.appointment_id
    LEFT JOIN job_order_report jor ON jo.job_order_id = jor.job_order_id
    LEFT JOIN job_order_technicians jot ON jo.job_order_id = jot.job_order_id
    LEFT JOIN technicians t ON jot.technician_id = t.technician_id
    LEFT JOIN joborder_feedback jf ON jo.job_order_id = jf.job_order_id
    WHERE jo.job_order_id = ? AND a.client_id = ?
");

$stmt->bind_param("ii", $job_order_id, $client_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Job order not found or not authorized']);
    exit;
}

$job_order = $result->fetch_assoc();

// Return the job order details
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'job_order' => $job_order
]);
?>
