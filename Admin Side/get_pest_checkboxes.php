<?php
session_start();
require_once '../db_connect.php';

header('Content-Type: application/json');

try {
    // Check if pest_checkboxes table exists
    $table_exists = false;
    $tables_result = $conn->query("SHOW TABLES LIKE 'pest_checkboxes'");
    if ($tables_result && $tables_result->num_rows > 0) {
        $table_exists = true;
    }

    // If table doesn't exist, create it and add default pest checkboxes
    if (!$table_exists) {
        $sql = file_get_contents('../create_pest_checkboxes_table.sql');
        if ($conn->multi_query($sql)) {
            do {
                // Process each result set
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
        }

        // Reconnect to avoid "Commands out of sync" error
        $conn->close();
        require_once '../db_connect.php';
    }

    // Get all pest checkboxes
    $query = "SELECT * FROM pest_checkboxes ORDER BY name";
    $result = $conn->query($query);

    if ($result) {
        $pest_checkboxes = [];
        while ($row = $result->fetch_assoc()) {
            $pest_checkboxes[] = $row;
        }

        echo json_encode([
            'success' => true,
            'data' => $pest_checkboxes
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch pest checkboxes: ' . $conn->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
