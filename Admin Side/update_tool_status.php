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

if (!isset($_POST['id']) || empty($_POST['id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'error' => 'Tool ID and status are required']);
    exit;
}

$id = (int)$_POST['id'];
$status = $_POST['status'];

// Validate status
if ($status !== 'in stock' && $status !== 'in use') {
    echo json_encode(['success' => false, 'error' => 'Invalid status value']);
    exit;
}

try {
    // Check if the tool is currently in use by a technician
    $checkQuery = "SELECT joc.id, joc.technician_id, CONCAT(tech.tech_fname, ' ', tech.tech_lname) AS technician_name, jo.job_order_id
                  FROM job_order_checklists joc
                  LEFT JOIN technicians tech ON joc.technician_id = tech.technician_id
                  LEFT JOIN job_order jo ON joc.job_order_id = jo.job_order_id
                  WHERE FIND_IN_SET(?, joc.checked_items) > 0
                  ORDER BY joc.id DESC
                  LIMIT 1";

    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    // If the tool is in use by a technician and we're trying to set it to "in stock"
    if ($checkResult->num_rows > 0 && $status === 'in stock') {
        $checklist = $checkResult->fetch_assoc();

        // Return a warning but still update the status
        $warningMessage = "This tool is currently in use by " . $checklist['technician_name'] .
                         " (Job #" . $checklist['job_order_id'] . "). The status has been set to In-Stock, but the technician may still need this tool.";

        // Update the status
        $stmt = $conn->prepare("UPDATE tools_equipment SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Tool status has been set to In-Stock successfully',
                'warning' => $warningMessage
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update tool status']);
        }
    } else {
        // Normal update without warning
        $stmt = $conn->prepare("UPDATE tools_equipment SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $successMessage = $status === 'in stock'
                ? 'Tool status has been set to In-Stock successfully'
                : 'Tool status updated successfully';
            echo json_encode(['success' => true, 'message' => $successMessage]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No changes made or tool not found']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
