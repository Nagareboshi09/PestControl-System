<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../db_connect.php';
require_once '../notification_functions.php';

header('Content-Type: application/json');

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Check if client_id is provided
if (!isset($data['client_id']) || empty($data['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Client ID is required']);
    exit;
}

$client_id = intval($data['client_id']);

// Start transaction
$conn->begin_transaction();

try {
    // Check if client exists
    $stmt = $conn->prepare("SELECT first_name, last_name FROM clients WHERE client_id = ?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Client not found');
    }
    
    $client = $result->fetch_assoc();
    $client_name = $client['first_name'] . ' ' . $client['last_name'];
    
    // Get all appointments for this client
    $stmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE client_id = ?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $appointmentsResult = $stmt->get_result();
    
    // Delete related records for each appointment
    while ($appointment = $appointmentsResult->fetch_assoc()) {
        $appointment_id = $appointment['appointment_id'];
        
        // Get assessment reports for this appointment
        $stmt = $conn->prepare("SELECT report_id FROM assessment_report WHERE appointment_id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $reportsResult = $stmt->get_result();
        
        // Delete related records for each report
        while ($report = $reportsResult->fetch_assoc()) {
            $report_id = $report['report_id'];
            
            // Delete technician feedback
            $stmt = $conn->prepare("DELETE FROM technician_feedback WHERE report_id = ?");
            $stmt->bind_param("i", $report_id);
            $stmt->execute();
            
            // Delete job orders and job order technicians
            $stmt = $conn->prepare("SELECT job_order_id FROM job_order WHERE report_id = ?");
            $stmt->bind_param("i", $report_id);
            $stmt->execute();
            $jobOrdersResult = $stmt->get_result();
            
            while ($jobOrder = $jobOrdersResult->fetch_assoc()) {
                $job_order_id = $jobOrder['job_order_id'];
                
                // Delete job order technicians
                $stmt = $conn->prepare("DELETE FROM job_order_technicians WHERE job_order_id = ?");
                $stmt->bind_param("i", $job_order_id);
                $stmt->execute();
            }
            
            // Delete job orders
            $stmt = $conn->prepare("DELETE FROM job_order WHERE report_id = ?");
            $stmt->bind_param("i", $report_id);
            $stmt->execute();
        }
        
        // Delete assessment reports
        $stmt = $conn->prepare("DELETE FROM assessment_report WHERE appointment_id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
    }
    
    // Delete notifications related to the client
    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND user_type = 'client'");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    
    // Delete appointments
    $stmt = $conn->prepare("DELETE FROM appointments WHERE client_id = ?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    
    // Finally, delete the client
    $stmt = $conn->prepare("DELETE FROM clients WHERE client_id = ?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Create notification for admin
    createNotification(
        1, // Admin ID (assuming admin_id = 1)
        'admin',
        'Client Deleted',
        "Client $client_name has been deleted from the system.",
        null,
        null
    );
    
    echo json_encode(['success' => true, 'message' => 'Client deleted successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
