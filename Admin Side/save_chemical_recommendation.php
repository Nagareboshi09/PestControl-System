<?php
session_start();
require_once '../db_connect.php';

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Get the report ID and chemical recommendations from the POST data
    $report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : 0;
    $chemical_recommendations = isset($_POST['chemical_recommendations']) ? $_POST['chemical_recommendations'] : '';
    
    // Validate the data
    if ($report_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
        exit;
    }
    
    try {
        // Check if the assessment report exists
        $check_stmt = $conn->prepare("SELECT report_id FROM assessment_report WHERE report_id = ?");
        $check_stmt->bind_param("i", $report_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Assessment report not found']);
            exit;
        }
        
        // Update the assessment report with the chemical recommendations
        $update_stmt = $conn->prepare("UPDATE assessment_report SET chemical_recommendations = ? WHERE report_id = ?");
        $update_stmt->bind_param("si", $chemical_recommendations, $report_id);
        $success = $update_stmt->execute();
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Chemical recommendations saved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save chemical recommendations: ' . $conn->error]);
        }
        
        $update_stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    // If not a POST request, return an error
    header('HTTP/1.1 405 Method Not Allowed');
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
