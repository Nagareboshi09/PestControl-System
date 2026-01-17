<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../db_connect.php';

header('Content-Type: application/json');

// Check if client_id is provided
if (!isset($_GET['client_id']) || empty($_GET['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Client ID is required']);
    exit;
}

$client_id = intval($_GET['client_id']);

try {
    // Get client details
    $stmt = $conn->prepare("SELECT * FROM clients WHERE client_id = ?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Client not found']);
        exit;
    }

    $client = $result->fetch_assoc();

    // Clean location address (remove coordinates)
    if (!empty($client['location_address'])) {
        $client['location_address'] = preg_replace('/\[[-\d\.]+,[-\d\.]+\]$/', '', $client['location_address']);
    }

    // Get client's appointments
    $stmt = $conn->prepare("SELECT
                            appointment_id,
                            preferred_date,
                            preferred_time,
                            location_address,
                            status,
                            created_at
                        FROM appointments
                        WHERE client_id = ?
                        ORDER BY preferred_date DESC");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $appointmentsResult = $stmt->get_result();

    $appointments = [];
    while ($row = $appointmentsResult->fetch_assoc()) {
        // Clean location address (remove coordinates)
        if (!empty($row['location_address'])) {
            $row['location_address'] = preg_replace('/\[[-\d\.]+,[-\d\.]+\]$/', '', $row['location_address']);
        }
        $appointments[] = $row;
    }

    // Get client's contracts (approved job orders)
    $stmt = $conn->prepare("SELECT
                            jo.job_order_id,
                            jo.report_id,
                            jo.type_of_work,
                            jo.preferred_date,
                            jo.preferred_time,
                            jo.frequency,
                            jo.client_approval_status,
                            jo.client_approval_date,
                            jo.cost,
                            jo.payment_amount,
                            jo.payment_date,
                            jo.status,
                            ar.area,
                            ar.pest_types,
                            a.location_address
                        FROM job_order jo
                        JOIN assessment_report ar ON jo.report_id = ar.report_id
                        JOIN appointments a ON ar.appointment_id = a.appointment_id
                        WHERE a.client_id = ?
                        AND (jo.client_approval_status = 'approved' OR jo.client_approval_status = 'one-time')
                        ORDER BY jo.client_approval_date DESC");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $contractsResult = $stmt->get_result();

    $contracts = [];
    while ($row = $contractsResult->fetch_assoc()) {
        // Clean location address (remove coordinates)
        if (!empty($row['location_address'])) {
            $row['location_address'] = preg_replace('/\[[-\d\.]+,[-\d\.]+\]$/', '', $row['location_address']);
        }
        $contracts[] = $row;
    }

    echo json_encode([
        'success' => true,
        'client' => $client,
        'appointments' => $appointments,
        'contracts' => $contracts
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
