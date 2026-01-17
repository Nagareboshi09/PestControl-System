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

// Get the work type to save
$work_type = isset($_POST['work_type']) ? trim($_POST['work_type']) : '';

if (empty($work_type)) {
    echo json_encode(['success' => false, 'error' => 'Work type is required']);
    exit;
}

// Define default work types
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
    echo json_encode(['success' => false, 'error' => 'This work type already exists as a default type']);
    exit;
}

try {
    // Check if the work_types table exists
    $table_exists = false;
    $tables_result = $conn->query("SHOW TABLES LIKE 'work_types'");
    if ($tables_result && $tables_result->num_rows > 0) {
        $table_exists = true;
    }

    // If the table doesn't exist, create it
    if (!$table_exists) {
        $create_table_sql = "CREATE TABLE IF NOT EXISTS work_types (
            id INT(11) NOT NULL AUTO_INCREMENT,
            type_name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY (type_name)
        )";

        if (!$conn->query($create_table_sql)) {
            throw new Exception("Failed to create work_types table: " . $conn->error);
        }

        // Table should now exist
        $table_exists = true;
    }

    // Now prepare the statement to check if the work type already exists
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM work_types WHERE type_name = ?");
    if (!$check_stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $check_stmt->bind_param("s", $work_type);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'This work type already exists']);
        exit;
    }

    // Insert the new work type
    $insert_stmt = $conn->prepare("INSERT INTO work_types (type_name) VALUES (?)");
    $insert_stmt->bind_param("s", $work_type);

    if ($insert_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Work type saved successfully']);
    } else {
        throw new Exception("Failed to save work type: " . $insert_stmt->error);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
