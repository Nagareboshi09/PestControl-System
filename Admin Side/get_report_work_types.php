<?php
session_start();
require_once '../db_connect.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Check if report_id is provided
if (!isset($_GET['report_id']) || empty($_GET['report_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Report ID is required'
    ]);
    exit;
}

$report_id = intval($_GET['report_id']);

try {
    // Get the assessment report details
    $report_query = $conn->prepare("
        SELECT ar.*, a.appointment_id
        FROM assessment_report ar
        JOIN appointments a ON ar.appointment_id = a.appointment_id
        WHERE ar.report_id = ?
    ");
    $report_query->bind_param("i", $report_id);
    $report_query->execute();
    $report_result = $report_query->get_result();

    if ($report_result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Assessment report not found'
        ]);
        exit;
    }

    $report_data = $report_result->fetch_assoc();
    
    // Check if we have work types in the assessment report
    // First, try to get work types directly from the assessment_report table
    $work_types = [];
    
    // Check if there's a work_types column in the assessment_report table
    $columns_query = $conn->query("SHOW COLUMNS FROM assessment_report LIKE 'work_types'");
    if ($columns_query && $columns_query->num_rows > 0) {
        // If the column exists, get the work types from it
        if (!empty($report_data['work_types'])) {
            // Check if it's a JSON string
            $decoded = json_decode($report_data['work_types'], true);
            if (is_array($decoded)) {
                $work_types = $decoded;
            } else {
                // If not JSON, assume it's a comma-separated string
                $work_types = array_map('trim', explode(',', $report_data['work_types']));
            }
        }
    }
    
    // If no work types found in the assessment_report table, try to get them from the appointment
    if (empty($work_types)) {
        // Check if there's a type_of_work column in the appointments table
        $columns_query = $conn->query("SHOW COLUMNS FROM appointments LIKE 'type_of_work'");
        if ($columns_query && $columns_query->num_rows > 0) {
            // Get the appointment details
            $appointment_query = $conn->prepare("
                SELECT type_of_work FROM appointments WHERE appointment_id = ?
            ");
            $appointment_query->bind_param("i", $report_data['appointment_id']);
            $appointment_query->execute();
            $appointment_result = $appointment_query->get_result();
            
            if ($appointment_result->num_rows > 0) {
                $appointment_data = $appointment_result->fetch_assoc();
                if (!empty($appointment_data['type_of_work'])) {
                    // Check if it's a JSON string
                    $decoded = json_decode($appointment_data['type_of_work'], true);
                    if (is_array($decoded)) {
                        $work_types = $decoded;
                    } else {
                        // If not JSON, assume it's a comma-separated string
                        $work_types = array_map('trim', explode(',', $appointment_data['type_of_work']));
                    }
                }
            }
        }
    }
    
    // If still no work types found, try to infer them from pest types
    if (empty($work_types) && !empty($report_data['pest_types'])) {
        // Define mapping of pest types to work types
        $pest_to_work_mapping = [
            'Flies' => 'General Pest Control',
            'Ants' => 'General Pest Control',
            'Cockroaches' => 'General Pest Control',
            'Bed Bugs' => 'General Pest Control',
            'Mice/Rats' => 'Rodent Control',
            'Termites' => 'Termite Control',
            'Mosquitoes' => 'General Pest Control',
            'Disinfect Area' => 'Disinfection',
            'Grass' => 'Weed Control'
        ];
        
        // Parse pest types
        $pest_types = [];
        $decoded = json_decode($report_data['pest_types'], true);
        if (is_array($decoded)) {
            $pest_types = $decoded;
        } else {
            // If not JSON, assume it's a comma-separated string
            $pest_types = array_map('trim', explode(',', $report_data['pest_types']));
        }
        
        // Map pest types to work types
        foreach ($pest_types as $pest_type) {
            if (isset($pest_to_work_mapping[$pest_type])) {
                $work_types[] = $pest_to_work_mapping[$pest_type];
            }
        }
        
        // Remove duplicates
        $work_types = array_unique($work_types);
    }
    
    // Return the work types
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'work_types' => array_values($work_types) // Reset array keys
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
