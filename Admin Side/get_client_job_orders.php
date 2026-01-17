<?php
session_start();
require_once '../db_connect.php';

// Check if user is logged in as admin (office_staff)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get client ID from request
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

if ($client_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
    exit;
}

try {
    // Get client information - first try from clients table
    $clientQuery = "SELECT CONCAT(first_name, ' ', last_name) AS client_name FROM clients WHERE client_id = ?";
    $stmt = $conn->prepare($clientQuery);
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $clientResult = $stmt->get_result();

    if ($clientResult->num_rows === 0) {
        // If not found in clients table, try appointments table
        $clientQuery = "SELECT client_name FROM appointments WHERE client_id = ? LIMIT 1";
        $stmt = $conn->prepare($clientQuery);
        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $clientResult = $stmt->get_result();

        if ($clientResult->num_rows === 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Client not found']);
            exit;
        }
    }

    $client = $clientResult->fetch_assoc();

    // Get all job orders for this client
    $jobOrdersQuery = "
        SELECT
            jo.job_order_id,
            jo.report_id,
            jo.type_of_work,
            jo.preferred_date,
            jo.preferred_time,
            jo.frequency,
            jo.client_approval_status,
            jo.status,
            a.client_name,
            a.location_address,
            a.kind_of_place,
            CONCAT(t.tech_fname, ' ', t.tech_lname) AS technician_name,
            jor.report_id AS job_report_id,
            jor.created_at AS completed_date,
            ar.area,
            ar.pest_types,
            ar.problem_area,
            ar.notes AS assessment_notes,
            ar.recommendation,
            ar.created_at AS assessment_date
        FROM job_order jo
        JOIN assessment_report ar ON jo.report_id = ar.report_id
        JOIN appointments a ON ar.appointment_id = a.appointment_id
        LEFT JOIN job_order_technicians jot ON jo.job_order_id = jot.job_order_id
        LEFT JOIN technicians t ON jot.technician_id = t.technician_id
        LEFT JOIN job_order_report jor ON jo.job_order_id = jor.job_order_id
        WHERE a.client_id = ?
        AND jo.client_approval_status IN ('approved', 'one-time')
        ORDER BY jo.report_id ASC, jo.preferred_date ASC, jo.preferred_time ASC
    ";

    $stmt = $conn->prepare($jobOrdersQuery);
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $jobOrdersResult = $stmt->get_result();

    $allJobOrders = [];
    $jobOrdersByReport = [];
    $today = date('Y-m-d');

    while ($row = $jobOrdersResult->fetch_assoc()) {
        $allJobOrders[] = $row;

        // Group job orders by report_id
        if (!isset($jobOrdersByReport[$row['report_id']])) {
            $jobOrdersByReport[$row['report_id']] = [
                'report_id' => $row['report_id'],
                'type_of_work' => $row['type_of_work'],
                'frequency' => $row['frequency'],
                'area' => $row['area'],
                'pest_types' => $row['pest_types'],
                'problem_area' => $row['problem_area'],
                'assessment_notes' => $row['assessment_notes'],
                'recommendation' => $row['recommendation'],
                'assessment_date' => $row['assessment_date'],
                'location_address' => $row['location_address'],
                'kind_of_place' => $row['kind_of_place'],
                'job_orders' => [],
                'completed_jobs' => [],
                'upcoming_jobs' => [],
                'current_job' => null,
                'total_job_orders' => 0,
                'completed_job_orders' => 0,
                'upcoming_job_orders' => 0
            ];
        }

        // Add job to the appropriate report group
        $jobOrdersByReport[$row['report_id']]['job_orders'][] = $row;
        $jobOrdersByReport[$row['report_id']]['total_job_orders']++;

        // Categorize job within its report group
        if ($row['status'] === 'completed' || !empty($row['job_report_id'])) {
            $jobOrdersByReport[$row['report_id']]['completed_jobs'][] = $row;
            $jobOrdersByReport[$row['report_id']]['completed_job_orders']++;
        }
        elseif ($row['preferred_date'] === $today) {
            $jobOrdersByReport[$row['report_id']]['current_job'] = $row;
        }
        elseif ($row['preferred_date'] > $today) {
            $jobOrdersByReport[$row['report_id']]['upcoming_jobs'][] = $row;
            $jobOrdersByReport[$row['report_id']]['upcoming_job_orders']++;
        }
        elseif (empty($jobOrdersByReport[$row['report_id']]['current_job'])) {
            $jobOrdersByReport[$row['report_id']]['current_job'] = $row;
        }
    }

    // Process each report group
    foreach ($jobOrdersByReport as $reportId => $reportData) {
        // Sort completed jobs by completion date (most recent first)
        usort($jobOrdersByReport[$reportId]['completed_jobs'], function($a, $b) {
            $dateA = !empty($a['completed_date']) ? strtotime($a['completed_date']) : 0;
            $dateB = !empty($b['completed_date']) ? strtotime($b['completed_date']) : 0;
            return $dateB - $dateA; // Descending order
        });

        // Sort upcoming jobs by date (nearest first)
        usort($jobOrdersByReport[$reportId]['upcoming_jobs'], function($a, $b) {
            $dateA = strtotime($a['preferred_date'] . ' ' . $a['preferred_time']);
            $dateB = strtotime($b['preferred_date'] . ' ' . $b['preferred_time']);
            return $dateA - $dateB; // Ascending order
        });
    }

    // For backward compatibility, also categorize all job orders together
    $completedJobs = [];
    $upcomingJobs = [];
    $currentJob = null;

    foreach ($allJobOrders as $job) {
        // Check if job is completed
        if ($job['status'] === 'completed' || !empty($job['job_report_id'])) {
            $completedJobs[] = $job;
        }
        // Check if job is current (today's date)
        elseif ($job['preferred_date'] === $today) {
            $currentJob = $job;
        }
        // Check if job is upcoming
        elseif ($job['preferred_date'] > $today) {
            $upcomingJobs[] = $job;
        }
        // If date is in the past but not completed, consider it as current
        elseif (empty($currentJob)) {
            $currentJob = $job;
        }
    }

    // Sort completed jobs by completion date (most recent first)
    usort($completedJobs, function($a, $b) {
        $dateA = !empty($a['completed_date']) ? strtotime($a['completed_date']) : 0;
        $dateB = !empty($b['completed_date']) ? strtotime($b['completed_date']) : 0;
        return $dateB - $dateA; // Descending order
    });

    // Sort upcoming jobs by date (nearest first)
    usort($upcomingJobs, function($a, $b) {
        $dateA = strtotime($a['preferred_date'] . ' ' . $a['preferred_time']);
        $dateB = strtotime($b['preferred_date'] . ' ' . $b['preferred_time']);
        return $dateA - $dateB; // Ascending order
    });

    // Prepare response data
    $response = [
        'success' => true,
        'client_id' => $client_id,
        'client_name' => $client['client_name'],
        'frequency' => !empty($allJobOrders) ? $allJobOrders[0]['frequency'] : 'one-time',
        'total_job_orders' => count($allJobOrders),
        'completed_job_orders' => count($completedJobs),
        'upcoming_job_orders' => count($upcomingJobs),
        'completed_jobs' => $completedJobs,
        'current_job' => $currentJob,
        'upcoming_jobs' => $upcomingJobs,
        'job_orders_by_report' => array_values($jobOrdersByReport) // Convert to indexed array for easier JSON handling
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
