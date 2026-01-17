<?php
session_start();
require_once '../db_connect.php';

// Check if user is logged in as admin (office_staff)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get job order ID from request
$job_order_id = isset($_GET['job_order_id']) ? intval($_GET['job_order_id']) : 0;

if ($job_order_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid job order ID']);
    exit;
}

try {
    // Get job order details
    $jobOrderQuery = "
        SELECT
            jo.job_order_id,
            jo.report_id,
            jo.type_of_work,
            jo.preferred_date,
            jo.preferred_time,
            jo.frequency,
            jo.client_approval_status,
            jo.status,
            jo.chemical_recommendations,
            jo.cost,
            ar.area,
            ar.notes as assessment_notes,
            ar.recommendation,
            ar.pest_types,
            ar.problem_area,
            ar.attachments as assessment_attachments,
            ar.chemical_recommendations as assessment_chemical_recommendations,
            a.client_name,
            a.client_id,
            a.location_address,
            a.kind_of_place,
            a.contact_number,
            a.email,
            jor.report_id AS job_report_id,
            jor.observation_notes,
            jor.attachments AS report_attachments,
            jor.created_at AS completed_date,
            jor.chemical_usage
        FROM job_order jo
        JOIN assessment_report ar ON jo.report_id = ar.report_id
        JOIN appointments a ON ar.appointment_id = a.appointment_id
        LEFT JOIN job_order_report jor ON jo.job_order_id = jor.job_order_id
        WHERE jo.job_order_id = ?
    ";

    $stmt = $conn->prepare($jobOrderQuery);
    $stmt->bind_param("i", $job_order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Job order not found']);
        exit;
    }

    $jobOrder = $result->fetch_assoc();

    // Get assigned technicians
    $techQuery = "
        SELECT
            t.technician_id,
            t.username,
            t.tech_fname,
            t.tech_lname,
            t.tech_contact_number,
            jot.is_primary
        FROM job_order_technicians jot
        JOIN technicians t ON jot.technician_id = t.technician_id
        WHERE jot.job_order_id = ?
    ";

    $stmt = $conn->prepare($techQuery);
    $stmt->bind_param("i", $job_order_id);
    $stmt->execute();
    $techResult = $stmt->get_result();

    $technicians = [];
    while ($tech = $techResult->fetch_assoc()) {
        $technicians[] = $tech;
    }

    // Get feedback if available
    $feedbackQuery = "
        SELECT
            feedback_id,
            rating,
            comments,
            created_at AS feedback_date,
            technician_arrived,
            job_completed,
            verification_notes
        FROM joborder_feedback
        WHERE job_order_id = ?
    ";

    $stmt = $conn->prepare($feedbackQuery);
    $stmt->bind_param("i", $job_order_id);
    $stmt->execute();
    $feedbackResult = $stmt->get_result();
    $feedback = $feedbackResult->num_rows > 0 ? $feedbackResult->fetch_assoc() : null;

    // Prepare response
    $response = [
        'success' => true,
        'job_order' => $jobOrder,
        'technicians' => $technicians,
        'feedback' => $feedback
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>
