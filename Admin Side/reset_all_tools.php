<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get all tools that are currently marked as "in use"
    $query = "SELECT id, name FROM tools_equipment WHERE status = 'in use'";
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Failed to query tools: " . $conn->error);
    }

    $inUseTools = [];
    while ($row = $result->fetch_assoc()) {
        $inUseTools[] = $row;
    }

    $totalTools = count($inUseTools);

    if ($totalTools === 0) {
        // No tools are currently in use
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'No tools are currently marked as in-use.',
            'tools_reset' => 0
        ]);
        exit;
    }

    // Update all tools with "in use" status to "in stock"
    // First, check if technician_id column exists in the tools_equipment table
    $check_column_sql = "SHOW COLUMNS FROM tools_equipment LIKE 'technician_id'";
    $column_result = $conn->query($check_column_sql);
    $has_technician_id = ($column_result && $column_result->num_rows > 0);

    // Build the SQL query based on the columns that exist
    if ($has_technician_id) {
        $updateQuery = "UPDATE tools_equipment SET status = 'in stock', technician_id = NULL, updated_at = NOW() WHERE status = 'in use'";
    } else {
        $updateQuery = "UPDATE tools_equipment SET status = 'in stock', updated_at = NOW() WHERE status = 'in use'";
    }

    if (!$conn->query($updateQuery)) {
        throw new Exception("Failed to update tool status: " . $conn->error);
    }

    $toolsReset = $conn->affected_rows;

    // Get the list of tools that were in use by technicians
    $checkQuery = "SELECT DISTINCT t.id, t.name, CONCAT(tech.tech_fname, ' ', tech.tech_lname) AS technician_name, jo.job_order_id, joc.id as checklist_id, joc.checked_items
                  FROM tools_equipment t
                  JOIN job_order_checklists joc ON FIND_IN_SET(t.id, joc.checked_items) > 0
                  LEFT JOIN technicians tech ON joc.technician_id = tech.technician_id
                  LEFT JOIN job_order jo ON joc.job_order_id = jo.job_order_id
                  ORDER BY technician_name";

    $checkResult = $conn->query($checkQuery);
    $techniciansAffected = [];
    $checklistsToUpdate = [];

    if ($checkResult && $checkResult->num_rows > 0) {
        while ($row = $checkResult->fetch_assoc()) {
            $key = $row['technician_name'] . ' (Job #' . $row['job_order_id'] . ')';
            if (!isset($techniciansAffected[$key])) {
                $techniciansAffected[$key] = [];
            }
            $techniciansAffected[$key][] = $row['name'];

            // Store checklist info for updating
            if (!isset($checklistsToUpdate[$row['checklist_id']])) {
                $checklistsToUpdate[$row['checklist_id']] = [
                    'checked_items' => $row['checked_items'],
                    'tools_to_remove' => []
                ];
            }
            $checklistsToUpdate[$row['checklist_id']]['tools_to_remove'][] = $row['id'];
        }
    }

    // Update the job_order_checklists table to clear checked_items
    if (!empty($checklistsToUpdate)) {
        foreach ($checklistsToUpdate as $checklist_id => $checklist_data) {
            // Set checked_items to an empty array
            $empty_checked_items = json_encode([]);
            $update_checklist_sql = "UPDATE job_order_checklists SET checked_items = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_checklist_sql);
            $update_stmt->bind_param("si", $empty_checked_items, $checklist_id);

            if (!$update_stmt->execute()) {
                error_log("Failed to update checklist ID $checklist_id: " . $conn->error);
                // Continue with other checklists even if one fails
            } else {
                error_log("Updated checklist ID $checklist_id to remove tools");
            }
        }
    }

    // Log the affected checklists
    error_log("Reset tools: " . count($checklistsToUpdate) . " checklists updated");

    // Note: The tools will still be in the checklists, but they will show as "in-stock"
    // in the tools_equipment.php page due to the session flag

    // Commit transaction
    $conn->commit();

    // Prepare warning message if tools were in use by technicians
    $warningMessage = null;
    if (!empty($techniciansAffected)) {
        $warningMessage = "The following technicians had tools marked as in-use that have been reset:";
        foreach ($techniciansAffected as $technician => $tools) {
            $warningMessage .= "\n- " . $technician . ": " . implode(", ", $tools);
        }
    }

    // Count the number of checklists affected
    $checklistsAffected = count($checklistsToUpdate);

    // Set a session flag to indicate that a reset has been performed
    $_SESSION['tools_reset_performed'] = true;
    $_SESSION['tools_reset_time'] = time();

    echo json_encode([
        'success' => true,
        'message' => 'Successfully reset ' . $toolsReset . ' tools from "in-use" to "in-stock" status. ' . $checklistsAffected . ' checklists were updated to remove the tools.',
        'tools_reset' => $toolsReset,
        'checklists_affected' => $checklistsAffected,
        'warning' => $warningMessage
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
