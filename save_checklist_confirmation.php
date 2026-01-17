<?php
/**
 * Save technician checklist confirmation
 */
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// Check if user is a technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get technician ID from session
$technician_id = $_SESSION['user_id'];

// Get data from POST request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

// Extract data
$checked_items_raw = isset($data['checked_items']) ? $data['checked_items'] : [];
// Extract only the IDs from the checked items
$checked_item_ids = array_map(function($item) {
    return (int)$item['id'];
}, $checked_items_raw);
$checked_items = json_encode($checked_item_ids);
$total_items = isset($data['total_items']) ? (int)$data['total_items'] : 0;
$checked_count = isset($data['checked_count']) ? (int)$data['checked_count'] : 0;
$checklist_date = date('Y-m-d'); // Today's date
$job_order_id = isset($data['job_order_id']) ? (int)$data['job_order_id'] : 0;

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if job_order_id is provided
    if ($job_order_id > 0) {
        // Check if a job order checklist already exists
        $check_jo_stmt = $conn->prepare("SELECT id FROM job_order_checklists WHERE job_order_id = ? AND technician_id = ?");
        $check_jo_stmt->bind_param("ii", $job_order_id, $technician_id);
        $check_jo_stmt->execute();
        $jo_result = $check_jo_stmt->get_result();

        if ($jo_result->num_rows > 0) {
            // Update existing job order checklist
            $jo_checklist = $jo_result->fetch_assoc();
            $checklist_id = $jo_checklist['id'];

            $update_jo_stmt = $conn->prepare("UPDATE job_order_checklists SET checked_tools = ?, checked_items = ?, total_items = ?, checked_count = ? WHERE id = ?");
            $update_jo_stmt->bind_param("ssiii", $checked_items_raw_json, $checked_items, $total_items, $checked_count, $checklist_id);
            $checked_items_raw_json = json_encode($checked_items_raw);

            if (!$update_jo_stmt->execute()) {
                throw new Exception("Failed to update job order checklist: " . $conn->error);
            }
        } else {
            // Insert new job order checklist
            $insert_jo_stmt = $conn->prepare("INSERT INTO job_order_checklists (job_order_id, technician_id, checked_tools, checked_items, total_items, checked_count) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_jo_stmt->bind_param("iissii", $job_order_id, $technician_id, $checked_items_raw_json, $checked_items, $total_items, $checked_count);
            $checked_items_raw_json = json_encode($checked_items_raw);

            if (!$insert_jo_stmt->execute()) {
                throw new Exception("Failed to save job order checklist: " . $conn->error);
            }
        }
    }

    // Check if a daily log already exists for today
    $check_stmt = $conn->prepare("SELECT log_id FROM technician_checklist_logs WHERE technician_id = ? AND checklist_date = ?");
    $check_stmt->bind_param("is", $technician_id, $checklist_date);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        // Update existing log
        $log = $result->fetch_assoc();
        $log_id = $log['log_id'];

        $update_stmt = $conn->prepare("UPDATE technician_checklist_logs SET checked_items = ?, total_items = ?, checked_count = ? WHERE log_id = ?");
        $update_stmt->bind_param("siii", $checked_items, $total_items, $checked_count, $log_id);

        if (!$update_stmt->execute()) {
            throw new Exception("Failed to update checklist confirmation: " . $conn->error);
        }
    } else {
        // Insert new log
        $insert_stmt = $conn->prepare("INSERT INTO technician_checklist_logs (technician_id, checklist_date, checked_items, total_items, checked_count) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("issii", $technician_id, $checklist_date, $checked_items, $total_items, $checked_count);

        if (!$insert_stmt->execute()) {
            throw new Exception("Failed to save checklist confirmation: " . $conn->error);
        }
    }

    // Check if status column exists in tools_equipment table
    $status_column_exists = $conn->query("SHOW COLUMNS FROM tools_equipment LIKE 'status'")->num_rows > 0;

    if ($status_column_exists && !empty($checked_item_ids)) {
        // Update tool status to "in use" for checked tools
        $id_list = implode(',', array_map('intval', $checked_item_ids));
        $update_status_sql = "UPDATE tools_equipment SET status = 'in use' WHERE id IN ($id_list)";

        if (!$conn->query($update_status_sql)) {
            throw new Exception("Failed to update tool status: " . $conn->error);
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Checklist confirmation saved',
        'job_order_id' => $job_order_id,
        'tools_updated' => $status_column_exists ? count($checked_item_ids) : 0
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
