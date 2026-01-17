<?php
include '../db_connect.php';

// Check if technician_id is provided
if (!isset($_GET['technician_id']) || !is_numeric($_GET['technician_id'])) {
    echo json_encode(['error' => 'Invalid technician ID']);
    exit;
}

$technicianId = (int)$_GET['technician_id'];
$jobs = [];
$checklist_info = null;

// Get technician's checklist information for today
$today = date('Y-m-d');
$checklistQuery = "SELECT
    checklist_date,
    checked_items,
    total_items,
    checked_count,
    created_at
FROM technician_checklist_logs
WHERE technician_id = ? AND checklist_date = ?";

$stmt = $conn->prepare($checklistQuery);
$stmt->bind_param("is", $technicianId, $today);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $checklist_info = $result->fetch_assoc();

    // Parse the checked_items JSON
    $decodedItems = json_decode($checklist_info['checked_items'], true);
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

    $checklist_info['checked_items'] = $checkedItemIds;
    $checklist_info['percentage'] = $checklist_info['total_items'] > 0 ?
        round(($checklist_info['checked_count'] / $checklist_info['total_items']) * 100) : 0;
}

// Get appointments assigned to this technician
$appointmentsQuery = "SELECT
    'appointment' as job_type,
    a.appointment_id as id,
    a.client_name,
    a.kind_of_place,
    a.location_address,
    a.preferred_date,
    TIME_FORMAT(a.preferred_time, '%H:%i') as preferred_time,
    a.status
FROM appointments a
WHERE a.technician_id = ?";

$stmt = $conn->prepare($appointmentsQuery);
$stmt->bind_param("i", $technicianId);
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {
    $jobs[] = $row;
}

// Get job orders assigned to this technician
$jobOrdersQuery = "SELECT
    'job_order' as job_type,
    j.job_order_id as id,
    j.type_of_work,
    j.preferred_date,
    TIME_FORMAT(j.preferred_time, '%H:%i') as preferred_time,
    a.client_name,
    a.location_address,
    'scheduled' as status
FROM job_order j
JOIN job_order_technicians jot ON j.job_order_id = jot.job_order_id
JOIN assessment_report ar ON j.report_id = ar.report_id
JOIN appointments a ON ar.appointment_id = a.appointment_id
WHERE jot.technician_id = ?
AND j.client_approval_status IN ('approved', 'one-time')";

$stmt = $conn->prepare($jobOrdersQuery);
$stmt->bind_param("i", $technicianId);
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {
    $jobs[] = $row;
}

// Prepare response data
$response = [
    'jobs' => $jobs,
    'checklist' => $checklist_info
];

// Set content type to JSON
header('Content-Type: application/json');
echo json_encode($response);
