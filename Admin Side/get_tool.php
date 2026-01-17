<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Tool ID is required']);
    exit;
}

$id = (int)$_GET['id'];

try {
    // Query to get tool details along with technician information if it's in use
    $query = "SELECT t.*,
              CASE
                  WHEN joc.id IS NOT NULL THEN 'in use'
                  ELSE COALESCE(t.status, 'in stock')
              END AS current_status,
              CASE
                  WHEN joc.id IS NOT NULL THEN CONCAT(tech.tech_fname, ' ', tech.tech_lname)
                  ELSE NULL
              END AS technician_name,
              CASE
                  WHEN joc.id IS NOT NULL THEN jo.job_order_id
                  ELSE NULL
              END AS job_order_id
              FROM tools_equipment t
              LEFT JOIN job_order_checklists joc ON FIND_IN_SET(t.id, joc.checked_items) > 0
                AND joc.id = (
                    SELECT MAX(id) FROM job_order_checklists
                    WHERE FIND_IN_SET(t.id, checked_items) > 0
                )
              LEFT JOIN job_order jo ON joc.job_order_id = jo.job_order_id
              LEFT JOIN technicians tech ON joc.technician_id = tech.technician_id
              WHERE t.id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $tool = $result->fetch_assoc();

    if ($tool) {
        // Use the current_status field for display
        $tool['status'] = $tool['current_status'];
        echo json_encode(['success' => true, 'data' => $tool]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Tool not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
