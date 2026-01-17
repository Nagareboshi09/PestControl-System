<?php
/**
 * Get technician data for a specific month
 * This file fetches all appointments, job orders, and checklists for a technician in a given month
 */
include '../db_connect.php';

// Check if required parameters are provided
if (!isset($_GET['technician_id']) || !is_numeric($_GET['technician_id']) ||
    !isset($_GET['month']) || !is_numeric($_GET['month']) ||
    !isset($_GET['year']) || !is_numeric($_GET['year'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// Get parameters
$technicianId = (int)$_GET['technician_id'];
$month = (int)$_GET['month'];
$year = (int)$_GET['year'];

// Validate month and year
if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
    echo json_encode(['success' => false, 'error' => 'Invalid month or year']);
    exit;
}

// Format month for SQL queries
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = date('Y-m-t', strtotime($monthStart));

// Initialize response data
$response = [
    'success' => true,
    'jobs' => [],
    'checklists' => [],
    'events_by_date' => []
];

try {
    // Get appointments for this technician in the specified month
    $appointmentsQuery = "
        SELECT
            'appointment' as job_type,
            a.appointment_id as id,
            a.client_name,
            a.location_address,
            a.kind_of_place,
            a.preferred_date,
            TIME_FORMAT(a.preferred_time, '%H:%i') as preferred_time,
            CASE
                WHEN ar.report_id IS NOT NULL THEN 'completed'
                ELSE a.status
            END as status
        FROM appointments a
        LEFT JOIN assessment_report ar ON a.appointment_id = ar.appointment_id
        WHERE a.technician_id = ?
        AND a.preferred_date BETWEEN ? AND ?
        ORDER BY a.preferred_date, a.preferred_time
    ";

    $stmt = $conn->prepare($appointmentsQuery);
    $stmt->bind_param("iss", $technicianId, $monthStart, $monthEnd);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $response['jobs'][] = $row;

        // Add to events by date
        $date = $row['preferred_date'];
        if (!isset($response['events_by_date'][$date])) {
            $response['events_by_date'][$date] = [
                'appointments' => 0,
                'job_orders' => 0,
                'has_checklist' => false
            ];
        }

        $response['events_by_date'][$date]['appointments']++;
    }

    // Get job orders for this technician in the specified month
    $jobOrdersQuery = "
        SELECT
            'job_order' as job_type,
            j.job_order_id as id,
            j.type_of_work,
            j.preferred_date,
            TIME_FORMAT(j.preferred_time, '%H:%i') as preferred_time,
            a.client_name,
            a.location_address,
            a.kind_of_place,
            CASE
                WHEN CURDATE() > j.preferred_date THEN 'completed'
                ELSE 'scheduled'
            END as status
        FROM job_order j
        JOIN job_order_technicians jot ON j.job_order_id = jot.job_order_id
        JOIN assessment_report ar ON j.report_id = ar.report_id
        JOIN appointments a ON ar.appointment_id = a.appointment_id
        WHERE jot.technician_id = ?
        AND j.preferred_date BETWEEN ? AND ?
        AND j.client_approval_status IN ('approved', 'one-time')
        ORDER BY j.preferred_date, j.preferred_time
    ";

    $stmt = $conn->prepare($jobOrdersQuery);
    $stmt->bind_param("iss", $technicianId, $monthStart, $monthEnd);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $response['jobs'][] = $row;

        // Add to events by date
        $date = $row['preferred_date'];
        if (!isset($response['events_by_date'][$date])) {
            $response['events_by_date'][$date] = [
                'appointments' => 0,
                'job_orders' => 0,
                'has_checklist' => false
            ];
        }

        $response['events_by_date'][$date]['job_orders']++;
    }

    // Get checklists for this technician in the specified month
    $checklistQuery = "
        SELECT
            checklist_date,
            checked_items,
            total_items,
            checked_count,
            created_at
        FROM technician_checklist_logs
        WHERE technician_id = ?
        AND checklist_date BETWEEN ? AND ?
        ORDER BY checklist_date
    ";

    $stmt = $conn->prepare($checklistQuery);
    $stmt->bind_param("iss", $technicianId, $monthStart, $monthEnd);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $date = $row['checklist_date'];

        // Parse the checked_items JSON
        if (!empty($row['checked_items'])) {
            try {
                $decodedItems = json_decode($row['checked_items'], true);
                $checkedItemIds = [];

                // Handle both formats: array of IDs or array of objects with 'id' property
                if (is_array($decodedItems)) {
                    foreach ($decodedItems as $item) {
                        if (is_array($item) && isset($item['id'])) {
                            // Format: array of objects with 'id' property
                            $checkedItemIds[] = $item['id'];
                        } elseif (is_numeric($item)) {
                            // Format: array of IDs
                            $checkedItemIds[] = $item;
                        }
                    }
                }

                $row['checked_items'] = $checkedItemIds;
            } catch (Exception $e) {
                $row['checked_items'] = [];
            }
        }

        $response['checklists'][$date] = $row;

        // Add to events by date
        if (!isset($response['events_by_date'][$date])) {
            $response['events_by_date'][$date] = [
                'appointments' => 0,
                'job_orders' => 0,
                'has_checklist' => true
            ];
        } else {
            $response['events_by_date'][$date]['has_checklist'] = true;
        }
    }

    // Return the response as JSON
    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
