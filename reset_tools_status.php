<?php
/**
 * Reset tools status when job order is completed
 */
require_once 'db_connect.php';

header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get data from POST request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

// Extract job order ID
$job_order_id = isset($data['job_order_id']) ? (int)$data['job_order_id'] : 0;

if ($job_order_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid job order ID']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if status column exists in tools_equipment table
    $status_column_exists = $conn->query("SHOW COLUMNS FROM tools_equipment LIKE 'status'")->num_rows > 0;

    if (!$status_column_exists) {
        throw new Exception("Status column does not exist in tools_equipment table");
    }

    // Get checked tools for this job order
    $stmt = $conn->prepare("SELECT checked_items FROM job_order_checklists WHERE job_order_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $job_order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // No checklist found for this job order
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'No tools to reset for this job order', 'tools_reset' => 0]);
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

    if (!$conn->query($update_status_sql)) {
        throw new Exception("Failed to update tool status: " . $conn->error);
    }

    $tools_reset = $conn->affected_rows;

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Tool status reset successfully',
        'job_order_id' => $job_order_id,
        'tools_reset' => $tools_reset
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
