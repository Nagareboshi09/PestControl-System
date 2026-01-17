<?php
/**
 * Get Job Order Details for Technician
 *
 * This script fetches complete job order details for a specific job order ID
 * It's used by the unified job handler to get complete job data when only minimal data is available
 */

session_start();
if ($_SESSION['role'] !== 'technician') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../db_connect.php';

// Get job order ID from request
$job_order_id = isset($_GET['job_order_id']) ? intval($_GET['job_order_id']) : 0;

if ($job_order_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid job order ID']);
    exit;
}

// Get technician ID from session
$technician_id = $_SESSION['user_id'];

try {
    // Query to get complete job order details with enhanced logging
    $query = "
        SELECT
            jo.*,
            a.client_name,
            a.location_address,
            a.kind_of_place,
            a.contact_number as appointment_contact,
            a.pest_problems,
            a.notes as client_notes,
            c.first_name,
            c.last_name,
            c.contact_number,
            ar.report_id,
            ar.area,
            ar.attachments,
            ar.pest_types,
            ar.problem_area,
            ar.notes as technician_notes,
            ar.recommendation as assessment_recommendation,
            ar.type_of_work as assessment_type_of_work,
            ar.preferred_date as assessment_preferred_date,
            ar.preferred_time as assessment_preferred_time,
            ar.frequency as assessment_frequency,
            jor.observation_notes,
            jor.recommendation,
            jor.attachments as report_attachments,
            jor.created_at as report_created_at,
            jo.chemical_recommendations,
            jot.is_primary,
            COALESCE(jor.created_at, jo.created_at) as sort_date
        FROM job_order jo
        JOIN job_order_technicians jot ON jo.job_order_id = jot.job_order_id
        JOIN assessment_report ar ON jo.report_id = ar.report_id
        JOIN appointments a ON ar.appointment_id = a.appointment_id
        LEFT JOIN clients c ON a.client_id = c.client_id
        LEFT JOIN job_order_report jor ON jo.job_order_id = jor.job_order_id
        WHERE jo.job_order_id = ? AND jot.technician_id = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $job_order_id, $technician_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // If no results with technician ID, try without technician restriction
        // This allows technicians to see job details even if they're not assigned
        $query_without_tech = "
            SELECT
                jo.*,
                a.client_name,
                a.location_address,
                a.kind_of_place,
                a.contact_number as appointment_contact,
                a.pest_problems,
                a.notes as client_notes,
                c.first_name,
                c.last_name,
                c.contact_number,
                ar.report_id,
                ar.area,
                ar.attachments,
                ar.pest_types,
                ar.problem_area,
                ar.notes as technician_notes,
                ar.recommendation as assessment_recommendation,
                ar.type_of_work as assessment_type_of_work,
                ar.preferred_date as assessment_preferred_date,
                ar.preferred_time as assessment_preferred_time,
                ar.frequency as assessment_frequency,
                jor.observation_notes,
                jor.recommendation,
                jor.attachments as report_attachments,
                jor.created_at as report_created_at,
                jo.chemical_recommendations,
                0 as is_primary,
                COALESCE(jor.created_at, jo.created_at) as sort_date
            FROM job_order jo
            JOIN assessment_report ar ON jo.report_id = ar.report_id
            JOIN appointments a ON ar.appointment_id = a.appointment_id
            LEFT JOIN clients c ON a.client_id = c.client_id
            LEFT JOIN job_order_report jor ON jo.job_order_id = jor.job_order_id
            WHERE jo.job_order_id = ?
        ";

        $stmt = $conn->prepare($query_without_tech);
        $stmt->bind_param("i", $job_order_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Job order not found']);
            exit;
        }
    }

    // Get job order data
    $job_order = $result->fetch_assoc();

    // Log the data for debugging
    error_log('Job order data retrieved for job ID ' . $job_order_id . ': ' . print_r($job_order, true));

    // Use our comprehensive data retrieval approach
    // Initialize arrays to store additional data
    $assessment_data = [];
    $appointment_data = [];
    $client_data = [];

    // Get the report_id from job_order
    $report_id = $job_order['report_id'];

    // Get assessment report data if available
    if ($report_id) {
        $assessment_query = "SELECT * FROM assessment_report WHERE report_id = ?";
        $assessment_stmt = $conn->prepare($assessment_query);
        $assessment_stmt->bind_param("i", $report_id);
        $assessment_stmt->execute();
        $assessment_result = $assessment_stmt->get_result();

        if ($assessment_result->num_rows > 0) {
            $assessment_data = $assessment_result->fetch_assoc();
            error_log('Retrieved assessment data: ' . print_r($assessment_data, true));

            // Add assessment data with prefixed keys
            foreach ($assessment_data as $key => $value) {
                if ($key !== 'report_id') { // Avoid duplicate keys
                    $job_order['assessment_' . $key] = $value;
                }
            }

            // Get appointment data if available
            $appointment_id = $assessment_data['appointment_id'];
            if ($appointment_id) {
                $appointment_query = "SELECT * FROM appointments WHERE appointment_id = ?";
                $appointment_stmt = $conn->prepare($appointment_query);
                $appointment_stmt->bind_param("i", $appointment_id);
                $appointment_stmt->execute();
                $appointment_result = $appointment_stmt->get_result();

                if ($appointment_result->num_rows > 0) {
                    $appointment_data = $appointment_result->fetch_assoc();
                    error_log('Retrieved appointment data: ' . print_r($appointment_data, true));

                    // Add appointment data with prefixed keys
                    foreach ($appointment_data as $key => $value) {
                        if ($key !== 'appointment_id') { // Avoid duplicate keys
                            $job_order['appointment_' . $key] = $value;
                        }
                    }

                    // Get client data if available
                    $client_id = $appointment_data['client_id'];
                    if ($client_id) {
                        $client_query = "SELECT * FROM clients WHERE client_id = ?";
                        $client_stmt = $conn->prepare($client_query);
                        $client_stmt->bind_param("i", $client_id);
                        $client_stmt->execute();
                        $client_result = $client_stmt->get_result();

                        if ($client_result->num_rows > 0) {
                            $client_data = $client_result->fetch_assoc();
                            error_log('Retrieved client data: ' . print_r($client_data, true));

                            // Add client data with prefixed keys
                            foreach ($client_data as $key => $value) {
                                if ($key !== 'client_id') { // Avoid duplicate keys
                                    $job_order['client_' . $key] = $value;
                                }
                            }
                        }
                    }
                }
            }
        } else {
            error_log('No assessment data found for report ID ' . $report_id);
        }
    }

    // Add convenience fields for the frontend
    if (empty($job_order['area'])) {
        $job_order['area'] = $assessment_data['area'] ?? null;
    }

    if (empty($job_order['pest_types'])) {
        $job_order['pest_types'] = $assessment_data['pest_types'] ?? $appointment_data['pest_problems'] ?? null;
    }

    if (empty($job_order['problem_area'])) {
        $job_order['problem_area'] = $assessment_data['problem_area'] ?? null;
    }

    // Sanitize data for JSON
    $sanitized_job_order = array_map(function($value) {
        if (is_string($value)) {
            // Remove any potentially problematic characters
            $cleaned = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $value);
            // Replace any HTML entities that might cause issues
            $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $cleaned;
        }
        return $value;
    }, $job_order);

    // Return job order data
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'job_order' => $sanitized_job_order]);

} catch (Exception $e) {
    // Log the error
    error_log('Error fetching job order details: ' . $e->getMessage());

    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error fetching job order details']);
}
