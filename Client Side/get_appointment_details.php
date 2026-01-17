<?php
session_start();
require_once '../db_connect.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../error.log');

// Function to return JSON response
function sendJsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response);
    exit;
}

try {
    // Check if user is logged in
    if (!isset($_SESSION['client_id'])) {
        sendJsonResponse(false, 'Not authenticated');
    }

    // Check if appointment ID is provided
    if (!isset($_GET['appointment_id'])) {
        sendJsonResponse(false, 'Appointment ID is required');
    }

    $appointmentId = intval($_GET['appointment_id']);
    $clientId = intval($_SESSION['client_id']);

    // Log the request parameters
    error_log("Fetching appointment details - Appointment ID: $appointmentId, Client ID: $clientId");

    // Fetch appointment details (excluding declined appointments)
    $stmt = $conn->prepare("
        SELECT
            a.*,
            t.username as technician_name,
            t.tech_contact_number as technician_contact,
            t.tech_fname as technician_fname,
            t.tech_lname as technician_lname,
            t.technician_picture,
            ar.end_time,
            ar.area,
            ar.notes as report_notes,
            ar.recommendation,
            ar.attachments,
            ar.created_at as report_date,
            ar.report_id,
            ar.pest_types,
            ar.problem_area
        FROM appointments a
        LEFT JOIN technicians t ON a.technician_id = t.technician_id
        LEFT JOIN assessment_report ar ON a.appointment_id = ar.appointment_id
        WHERE a.appointment_id = ? AND a.client_id = ? AND a.status != 'declined'
    ");

    if (!$stmt) {
        error_log("Prepare statement failed: " . $conn->error);
        sendJsonResponse(false, 'Database query preparation failed');
    }

    $stmt->bind_param("ii", $appointmentId, $clientId);

    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        sendJsonResponse(false, 'Database query execution failed');
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("No appointment found with ID: $appointmentId for client ID: $clientId");
        sendJsonResponse(false, 'Appointment not found');
    }

    $appointment = $result->fetch_assoc();

    // Log success
    error_log("Successfully fetched appointment details for appointment ID: $appointmentId");

    // Return appointment details
    sendJsonResponse(true, 'Appointment details retrieved successfully', ['appointment' => $appointment]);

} catch (Exception $e) {
    error_log("Exception in get_appointment_details.php: " . $e->getMessage());
    sendJsonResponse(false, 'An error occurred while retrieving appointment details');
}
?>
