<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once '../db_connect.php';

header('Content-Type: application/json');

try {
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

    // Get custom work types from the job_order table
    $query1 = "SELECT DISTINCT type_of_work FROM job_order
              WHERE type_of_work NOT IN ('" . implode("','", $default_work_types) . "')
              ORDER BY type_of_work";

    $result1 = $conn->query($query1);
    $custom_work_types = [];

    if ($result1 && $result1->num_rows > 0) {
        while ($row = $result1->fetch_assoc()) {
            $custom_work_types[] = $row['type_of_work'];
        }
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

    // Get custom work types from the work_types table if it exists
    if ($table_exists) {
        $query2 = "SELECT type_name FROM work_types
                  WHERE type_name NOT IN ('" . implode("','", $default_work_types) . "')
                  ORDER BY type_name";

        $result2 = $conn->query($query2);

        if ($result2 && $result2->num_rows > 0) {
            while ($row = $result2->fetch_assoc()) {
                if (!in_array($row['type_name'], $custom_work_types)) {
                    $custom_work_types[] = $row['type_name'];
                }
            }
        }
    }

    // Sort the custom work types alphabetically
    sort($custom_work_types);

    // Combine default and custom work types
    $all_work_types = [
        'default' => $default_work_types,
        'custom' => $custom_work_types
    ];

    echo json_encode(['success' => true, 'data' => $all_work_types]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
