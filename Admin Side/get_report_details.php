<?php
session_start();
require_once '../db_connect.php';

// Check if the user is logged in as admin
if (!isset($_SESSION['staff_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get the report ID from the query string
$report_id = isset($_GET['report_id']) ? intval($_GET['report_id']) : 0;

if ($report_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
    exit;
}

// Fetch the report details
$query = "SELECT ar.report_id, ar.appointment_id, ar.pest_types, ar.area, ar.problem_area, ar.notes, ar.recommendation,
          ar.type_of_work, ar.preferred_date, ar.preferred_time, ar.frequency, ar.chemical_recommendations
          FROM assessment_report ar
          WHERE ar.report_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Report not found']);
    exit;
}

$report = $result->fetch_assoc();

// Get the pest types and area directly from the assessment report
$pest_types = $report['pest_types'];
$area = $report['area'];

// Process chemical recommendations
$chemical_recommendations = $report['chemical_recommendations'];

// Log the chemical recommendations for debugging
error_log("Chemical recommendations from report ID {$report['report_id']}: " . substr($chemical_recommendations, 0, 100) . (strlen($chemical_recommendations) > 100 ? '...' : ''));

// Validate and clean up chemical recommendations
$cleaned_recommendations = $chemical_recommendations;
if (!empty($chemical_recommendations)) {
    try {
        // Try to decode the JSON to make sure it's valid
        $decoded = json_decode($chemical_recommendations, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Invalid JSON in chemical_recommendations: " . json_last_error_msg());

            // Try to clean up the JSON string
            $cleaned_recommendations = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $chemical_recommendations);
            $cleaned_recommendations = str_replace("\'", "'", $cleaned_recommendations);

            // Try decoding again
            $decoded = json_decode($cleaned_recommendations, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Still invalid JSON after cleanup: " . json_last_error_msg());
                $cleaned_recommendations = '[]'; // Reset to empty array if still invalid
            } else {
                error_log("JSON valid after cleanup");
            }
        } else {
            error_log("Valid JSON in chemical_recommendations with " . (is_array($decoded) ? count($decoded) : 0) . " items");
        }
    } catch (Exception $e) {
        error_log("Exception processing chemical_recommendations: " . $e->getMessage());
        $cleaned_recommendations = '[]';
    }
}

// Return the report details as JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'report_id' => $report['report_id'],
    'appointment_id' => $report['appointment_id'],
    'pest_types' => $pest_types,
    'area' => $area,
    'problem_area' => $report['problem_area'],
    'notes' => $report['notes'],
    'recommendation' => $report['recommendation'],
    'type_of_work' => $report['type_of_work'],
    'preferred_date' => $report['preferred_date'],
    'preferred_time' => $report['preferred_time'],
    'frequency' => $report['frequency'],
    'chemical_recommendations' => $cleaned_recommendations,
    'raw_chemical_recommendations' => $chemical_recommendations, // Include the raw data for debugging
    'source' => 'assessment'
]);
?>
