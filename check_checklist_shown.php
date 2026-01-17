<?php
/**
 * Check if the tools checklist has been shown to the technician today
 */
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Log the request for debugging
    error_log("Checking if tools checklist has been shown");

    // Check if user is a technician
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
        error_log("User is not a technician. Role: " . ($_SESSION['role'] ?? 'not set'));
        echo json_encode(['success' => true, 'shown' => true, 'message' => 'Not a technician']); // Not a technician, don't show checklist
        exit;
    }

    // Get technician ID from session
    if (!isset($_SESSION['user_id'])) {
        error_log("User ID not found in session");
        echo json_encode(['success' => true, 'shown' => true, 'message' => 'User ID not found']);
        exit;
    }

    $technician_id = $_SESSION['user_id'];
    error_log("Checking checklist for technician ID: " . $technician_id);

    // Get current date
    $today = date('Y-m-d');
    error_log("Today's date: " . $today);

    // Check if checklist has been shown today using session
    $session_shown = isset($_SESSION['checklist_shown_date']) && $_SESSION['checklist_shown_date'] === $today;
    error_log("Session shown: " . ($session_shown ? 'Yes' : 'No'));
    if (isset($_SESSION['checklist_shown_date'])) {
        error_log("Session checklist_shown_date: " . $_SESSION['checklist_shown_date']);
    } else {
        error_log("Session checklist_shown_date not set");
    }

    // Check if checklist has been logged in database for today
    $db_shown = false;

    try {
        $check_stmt = $conn->prepare("SELECT log_id FROM technician_checklist_logs WHERE technician_id = ? AND checklist_date = ?");
        if (!$check_stmt) {
            error_log("Failed to prepare statement: " . $conn->error);
            throw new Exception("Database error: " . $conn->error);
        }

        $check_stmt->bind_param("is", $technician_id, $today);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            $db_shown = true;
            error_log("Checklist found in database for today");
        } else {
            error_log("No checklist found in database for today");
        }
    } catch (Exception $dbException) {
        error_log("Database error: " . $dbException->getMessage());
        // Continue with session check only
    }

    // Checklist is considered shown if either session or database indicates it's been shown
    $shown = $session_shown || $db_shown;
    error_log("Final shown status: " . ($shown ? 'Yes' : 'No'));

    // If shown in database but not in session, update session for consistency
    if ($db_shown && !$session_shown) {
        $_SESSION['checklist_shown_date'] = $today;
        error_log("Updated session with today's date");
    }

    // Always return that the checklist has been shown to prevent automatic popup
    // The checklist will now only be shown when a job order is clicked
    echo json_encode([
        'success' => true,
        'shown' => true, // Always return true to prevent automatic popup
        'today' => $today,
        'session_shown' => true, // Force to true
        'db_shown' => true, // Force to true
        'checklist_shown_date' => $today // Always use today's date
    ]);
} catch (Exception $e) {
    // Log the error
    error_log("Error checking if checklist has been shown: " . $e->getMessage());

    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'shown' => true // Default to shown to prevent errors
    ]);
}
?>
