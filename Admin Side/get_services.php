<?php
session_start();
require_once '../db_connect.php';

header('Content-Type: application/json');

try {
    // Check if services table exists
    $table_exists = false;
    $tables_result = $conn->query("SHOW TABLES LIKE 'services'");
    if ($tables_result && $tables_result->num_rows > 0) {
        $table_exists = true;
    }

    // If table doesn't exist, create it and add default services
    if (!$table_exists) {
        $sql = file_get_contents('../create_services_table.sql');
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
    } else {
        // Table exists, check if the image column exists
        $result = $conn->query("SHOW COLUMNS FROM services LIKE 'image'");
        if ($result->num_rows == 0) {
            // Column doesn't exist, add it
            $sql = "ALTER TABLE services ADD COLUMN image varchar(255) DEFAULT NULL AFTER icon";
            if ($conn->query($sql)) {
                // Update default services to include image paths
                $sql = "UPDATE services SET
                        image = CASE
                            WHEN name = 'General Pest Control' THEN 'GenPest.jpg'
                            WHEN name = 'Termite Control' THEN 'termite.jpg'
                            WHEN name = 'Rodent Control' THEN 'rodent.jpg'
                            WHEN name = 'Disinfection' THEN 'disinfect.jpg'
                            WHEN name = 'Weed Control' THEN 'weed.jpg'
                            ELSE NULL
                        END
                        WHERE image IS NULL";
                $conn->query($sql);
            }
        }
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/services/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Get active services
    $query = "SELECT * FROM services WHERE status = 'active' ORDER BY name";
    $result = $conn->query($query);

    $services = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
    }

    echo json_encode(['success' => true, 'data' => $services]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
