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

// Get the work type to delete
$work_type = isset($_POST['work_type']) ? $_POST['work_type'] : '';

if (empty($work_type)) {
    echo json_encode(['success' => false, 'error' => 'Work type is required']);
    exit;
}

// Define default work types that cannot be deleted
$default_work_types = [
    'General Pest Control',
    'Rodent Control Only',
    'Termite Baiting',
    'Soil Poisoning',
    'Wood Protection Only',
    'Weed Control',
    'Disinfection',
    'Installation of Pipes'
];

// Check if the work type is a default type
if (in_array($work_type, $default_work_types)) {
    echo json_encode(['success' => false, 'error' => 'Default work types cannot be deleted']);
    exit;
}

try {
    // Check if the work type is used in any job orders
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_order WHERE type_of_work = ?");
    $check_stmt->bind_param("s", $work_type);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'];

    if ($count > 0) {
        echo json_encode([
            'success' => false,
            'error' => 'This work type is used in ' . $count . ' job order(s) and cannot be deleted'
        ]);
        exit;
    }

    // Check if work_types table exists
    $table_exists = false;
    $tables_result = $conn->query("SHOW TABLES LIKE 'work_types'");
    if ($tables_result && $tables_result->num_rows > 0) {
        $table_exists = true;
    }

    // Create the work_types table if it doesn't exist
    if (!$table_exists) {
        $create_table_sql = "CREATE TABLE IF NOT EXISTS work_types (
            id INT(11) NOT NULL AUTO_INCREMENT,
            type_name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY (type_name)
        )";

        if ($conn->query($create_table_sql)) {
            $table_exists = true;
        } else {
            // If table creation fails, just continue without it
            error_log("Failed to create work_types table: " . $conn->error);
        }
    }

    // If the work_types table exists, delete the work type from it
    if ($table_exists) {
        $delete_stmt = $conn->prepare("DELETE FROM work_types WHERE type_name = ?");
        if ($delete_stmt) {
            $delete_stmt->bind_param("s", $work_type);
            $delete_stmt->execute();
        } else {
            error_log("Failed to prepare delete statement: " . $conn->error);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Work type deleted successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
