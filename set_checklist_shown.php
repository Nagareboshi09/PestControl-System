<?php
/**
 * Set the tools checklist as shown for the technician today
 */
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Check if user is a technician
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
        echo json_encode(['success' => false, 'error' => 'Not a technician']);
        exit;
    }

    // Get technician ID from session
    $technician_id = $_SESSION['user_id'];

    // Get current date
    $today = date('Y-m-d');

    // Set checklist as shown for today in session
    $_SESSION['checklist_shown_date'] = $today;

    // Check if a record already exists in the database for today
    $check_stmt = $conn->prepare("SELECT log_id FROM technician_checklist_logs WHERE technician_id = ? AND checklist_date = ?");
    $check_stmt->bind_param("is", $technician_id, $today);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    // If no record exists, create a placeholder record to mark that the checklist has been shown
    if ($result->num_rows == 0) {
        // Create a placeholder record with empty checked items
        $insert_stmt = $conn->prepare("INSERT INTO technician_checklist_logs (technician_id, checklist_date, checked_items, total_items, checked_count) VALUES (?, ?, '[]', 0, 0)");
        $insert_stmt->bind_param("is", $technician_id, $today);
        $insert_stmt->execute();
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Checklist marked as shown',
        'date' => $today
    ]);
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
