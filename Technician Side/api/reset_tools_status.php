<?php
/**
 * Reset tools and equipment status from "in-use" to "in-stock"
 * This API is called when a technician clicks the reset button in the report submitted modal
 */
session_start();
if ($_SESSION['role'] !== 'technician' || !isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once '../../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get job_order_id from POST data
$job_order_id = isset($_POST['job_order_id']) ? intval($_POST['job_order_id']) : 0;

if ($job_order_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid job order ID']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get the technician ID from the session
    $technician_id = $_SESSION['user_id'];

    // Log session data for debugging
    error_log("Reset tools request - User ID: " . $technician_id . ", Job Order ID: " . $job_order_id);

    // Get the checklist for this job order and technician
    $query = "SELECT id, checked_items FROM job_order_checklists
              WHERE job_order_id = ? AND technician_id = ?
              ORDER BY id DESC LIMIT 1";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $job_order_id, $technician_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // No checklist found for this job order and technician
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'No tools were checked for this job order', 'tools_reset' => 0]);
        exit;
    }

    $checklist = $result->fetch_assoc();
    $checked_items = json_decode($checklist['checked_items'], true);

    if (empty($checked_items)) {
        // No tools were checked for this job order
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'No tools were checked for this job order', 'tools_reset' => 0]);
        exit;
    }

    // Update tool status to "in stock" for checked tools
    $id_list = implode(',', array_map('intval', $checked_items));

    // First, check if technician_id column exists in the tools_equipment table
    $check_column_sql = "SHOW COLUMNS FROM tools_equipment LIKE 'technician_id'";
    $column_result = $conn->query($check_column_sql);
    $has_technician_id = ($column_result && $column_result->num_rows > 0);

    // Build the SQL query based on the columns that exist
    if ($has_technician_id) {
        $update_status_sql = "UPDATE tools_equipment SET status = 'in stock', technician_id = NULL WHERE id IN ($id_list)";
    } else {
        $update_status_sql = "UPDATE tools_equipment SET status = 'in stock' WHERE id IN ($id_list)";
    }

    // Execute the update query
    if (!$conn->query($update_status_sql)) {
        throw new Exception("Failed to update tool status: " . $conn->error);
    }

    // Get the number of tools that were reset
    $tools_reset = $conn->affected_rows;

    // Also update the job_order_checklists table to remove these tools
    // This is important so they don't show as "in use" on the Admin Side
    $checklist_id = $checklist['id'];

    // Set checked_items to an empty array
    $empty_checked_items = json_encode([]);
    $update_checklist_sql = "UPDATE job_order_checklists SET checked_items = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_checklist_sql);
    $update_stmt->bind_param("si", $empty_checked_items, $checklist_id);

    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update checklist: " . $conn->error);
    }

    error_log("Updated checklist ID $checklist_id to remove tools");

    // Try to log the reset action, but don't fail if the table doesn't exist
    try {
        // First check if the activity_log table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'activity_log'");

        if ($table_check->num_rows > 0) {
            // Table exists, proceed with logging
            $log_sql = "INSERT INTO activity_log (user_id, user_type, action, details, ip_address)
                        VALUES (?, 'technician', 'reset_tools', ?, ?)";
            $details = json_encode([
                'job_order_id' => $job_order_id,
                'tools_reset' => $tools_reset,
                'tool_ids' => $checked_items
            ]);
            $ip = $_SERVER['REMOTE_ADDR'];

            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("iss", $technician_id, $details, $ip);
            $log_stmt->execute();

            error_log("Successfully logged tool reset action to activity_log");
        } else {
            // Table doesn't exist, just log to error_log instead
            error_log("Activity log table doesn't exist. Tool reset action: User ID: $technician_id, Job Order ID: $job_order_id, Tools reset: $tools_reset");
        }
    } catch (Exception $log_error) {
        // If logging fails, just continue - don't let it prevent the tool reset
        error_log("Failed to log tool reset action: " . $log_error->getMessage());
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Tools and equipment status reset successfully. Checklist has been updated.',
        'tools_reset' => $tools_reset,
        'checklist_updated' => true
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();

    // Log the error for debugging
    error_log("Reset tools error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());

    // Check if the error is related to the activity_log table
    $error_message = $e->getMessage();
    $user_friendly_message = 'An error occurred while resetting tools status. Please try again or contact support if the problem persists.';

    if (strpos($error_message, "Table 'macj_pest_control.activity_log' doesn't exist") !== false) {
        // This is the specific error we're handling
        $user_friendly_message = "The system couldn't log this action, but we can still try to reset the tools. Please contact the administrator to set up the activity log table.";

        // Try again without the activity logging
        try {
            // Start a new transaction
            $conn->begin_transaction();

            // Update tool status to "in stock" for checked tools
            // Use the same SQL query that was determined earlier
            if ($has_technician_id) {
                $update_status_sql = "UPDATE tools_equipment SET status = 'in stock', technician_id = NULL WHERE id IN ($id_list)";
            } else {
                $update_status_sql = "UPDATE tools_equipment SET status = 'in stock' WHERE id IN ($id_list)";
            }

            if (!$conn->query($update_status_sql)) {
                throw new Exception("Failed to update tool status: " . $conn->error);
            }

            // Get the number of tools that were reset
            $tools_reset = $conn->affected_rows;

            // Also update the job_order_checklists table to remove these tools
            $checklist_id = $checklist['id'];

            // Set checked_items to an empty array
            $empty_checked_items = json_encode([]);
            $update_checklist_sql = "UPDATE job_order_checklists SET checked_items = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_checklist_sql);
            $update_stmt->bind_param("si", $empty_checked_items, $checklist_id);

            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update checklist: " . $conn->error);
            }

            error_log("Retry: Updated checklist ID $checklist_id to remove tools");

            // Commit transaction
            $conn->commit();

            // Return success
            echo json_encode([
                'success' => true,
                'message' => 'Tools and equipment status reset successfully (without activity logging). Checklist has been updated.',
                'tools_reset' => $tools_reset,
                'checklist_updated' => true,
                'warning' => 'Activity logging is not available. Please contact the administrator.'
            ]);
            exit;
        } catch (Exception $retry_error) {
            // If the retry also fails, log and return the error
            $conn->rollback();
            error_log("Retry failed: " . $retry_error->getMessage());
            $error_message = $retry_error->getMessage();
            $user_friendly_message = 'Failed to reset tools status even after bypassing activity logging. Please contact support.';
        }
    }

    // Return a more detailed error message
    echo json_encode([
        'success' => false,
        'error' => $error_message,
        'details' => $user_friendly_message
    ]);
}
?>
