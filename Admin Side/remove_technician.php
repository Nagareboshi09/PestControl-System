<?php
require_once '../db_connect.php';
require_once '../notification_functions.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// Check if we have all required parameters
if (!isset($data['type']) || !isset($data['technician_id']) || 
    (!isset($data['appointment_id']) && !isset($data['job_order_id']))) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$type = $data['type'];
$technicianId = (int)$data['technician_id'];
$response = ['success' => false];

if ($type === 'appointment') {
    if (!isset($data['appointment_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing appointment_id parameter']);
        exit;
    }
    
    $appointmentId = (int)$data['appointment_id'];
    
    // Check if this is the primary technician
    $checkStmt = $conn->prepare("SELECT is_primary FROM appointment_technicians 
                               WHERE appointment_id = ? AND technician_id = ?");
    $checkStmt->bind_param("ii", $appointmentId, $technicianId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $isPrimary = false;
    
    if ($row = $checkResult->fetch_assoc()) {
        $isPrimary = (bool)$row['is_primary'];
    }
    
    // Delete the technician assignment
    $deleteStmt = $conn->prepare("DELETE FROM appointment_technicians 
                                WHERE appointment_id = ? AND technician_id = ?");
    $deleteStmt->bind_param("ii", $appointmentId, $technicianId);
    
    if ($deleteStmt->execute()) {
        // If this was the primary technician, update the appointments table
        if ($isPrimary) {
            // Find another technician to set as primary
            $findStmt = $conn->prepare("SELECT technician_id FROM appointment_technicians 
                                      WHERE appointment_id = ? LIMIT 1");
            $findStmt->bind_param("i", $appointmentId);
            $findStmt->execute();
            $findResult = $findStmt->get_result();
            
            if ($newTech = $findResult->fetch_assoc()) {
                // Set this technician as primary
                $updatePrimaryStmt = $conn->prepare("UPDATE appointment_technicians 
                                                  SET is_primary = 1 
                                                  WHERE appointment_id = ? AND technician_id = ?");
                $updatePrimaryStmt->bind_param("ii", $appointmentId, $newTech['technician_id']);
                $updatePrimaryStmt->execute();
                
                // Update the appointments table
                $updateAppointmentStmt = $conn->prepare("UPDATE appointments 
                                                      SET technician_id = ? 
                                                      WHERE appointment_id = ?");
                $updateAppointmentStmt->bind_param("ii", $newTech['technician_id'], $appointmentId);
                $updateAppointmentStmt->execute();
            } else {
                // No more technicians, set technician_id to NULL
                $nullStmt = $conn->prepare("UPDATE appointments 
                                         SET technician_id = NULL 
                                         WHERE appointment_id = ?");
                $nullStmt->bind_param("i", $appointmentId);
                $nullStmt->execute();
            }
        }
        
        $response = [
            'success' => true,
            'appointment_id' => $appointmentId,
            'technician_id' => $technicianId,
            'was_primary' => $isPrimary,
            'message' => 'Technician removed successfully'
        ];
    } else {
        $response['message'] = 'Failed to remove technician: ' . $conn->error;
    }
} elseif ($type === 'job_order') {
    if (!isset($data['job_order_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing job_order_id parameter']);
        exit;
    }
    
    $jobOrderId = (int)$data['job_order_id'];
    
    // Check if this is the primary technician
    $checkStmt = $conn->prepare("SELECT is_primary FROM job_order_technicians 
                               WHERE job_order_id = ? AND technician_id = ?");
    $checkStmt->bind_param("ii", $jobOrderId, $technicianId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $isPrimary = false;
    
    if ($row = $checkResult->fetch_assoc()) {
        $isPrimary = (bool)$row['is_primary'];
    }
    
    // Delete the technician assignment
    $deleteStmt = $conn->prepare("DELETE FROM job_order_technicians 
                                WHERE job_order_id = ? AND technician_id = ?");
    $deleteStmt->bind_param("ii", $jobOrderId, $technicianId);
    
    if ($deleteStmt->execute()) {
        // If this was the primary technician, find another technician to set as primary
        if ($isPrimary) {
            $findStmt = $conn->prepare("SELECT technician_id FROM job_order_technicians 
                                      WHERE job_order_id = ? LIMIT 1");
            $findStmt->bind_param("i", $jobOrderId);
            $findStmt->execute();
            $findResult = $findStmt->get_result();
            
            if ($newTech = $findResult->fetch_assoc()) {
                // Set this technician as primary
                $updatePrimaryStmt = $conn->prepare("UPDATE job_order_technicians 
                                                  SET is_primary = 1 
                                                  WHERE job_order_id = ? AND technician_id = ?");
                $updatePrimaryStmt->bind_param("ii", $jobOrderId, $newTech['technician_id']);
                $updatePrimaryStmt->execute();
                
                // Send notification to the new primary technician
                try {
                    // Get job order details
                    $jobStmt = $conn->prepare("SELECT 
                        j.preferred_date, 
                        j.preferred_time, 
                        j.type_of_work,
                        a.client_name
                    FROM job_order j
                    JOIN assessment_report ar ON j.report_id = ar.report_id
                    JOIN appointments a ON ar.appointment_id = a.appointment_id
                    WHERE j.job_order_id = ?");
                    
                    $jobStmt->bind_param("i", $jobOrderId);
                    $jobStmt->execute();
                    $jobResult = $jobStmt->get_result();
                    $jobData = $jobResult->fetch_assoc();
                    
                    if ($jobData) {
                        $notificationText = "You have been set as the primary technician for the job order for {$jobData['client_name']} on " . 
                                           date('F j, Y', strtotime($jobData['preferred_date'])) . ". You are responsible for submitting reports.";
                        
                        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, related_id, related_type, is_read, created_at) 
                                                   VALUES (?, 'technician', 'Primary Technician Assignment', ?, ?, 'job_order', 0, NOW())");
                        $notifStmt->bind_param("isi", $newTech['technician_id'], $notificationText, $jobOrderId);
                        $notifStmt->execute();
                    }
                } catch (Exception $e) {
                    // Log the error but don't fail the removal
                    $response['notification_error'] = $e->getMessage();
                }
            }
        }
        
        $response = [
            'success' => true,
            'job_order_id' => $jobOrderId,
            'technician_id' => $technicianId,
            'was_primary' => $isPrimary,
            'message' => 'Technician removed successfully'
        ];
    } else {
        $response['message'] = 'Failed to remove technician: ' . $conn->error;
    }
} else {
    $response['message'] = 'Invalid type parameter. Must be "appointment" or "job_order".';
}

echo json_encode($response);
?>
