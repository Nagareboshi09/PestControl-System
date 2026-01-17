<?php
/**
 * Create job_order_checklists table
 */
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Check if table already exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'job_order_checklists'")->num_rows > 0;
    
    if (!$tableExists) {
        // Create the table
        $createTableSQL = "CREATE TABLE job_order_checklists (
            id INT(11) NOT NULL AUTO_INCREMENT,
            job_order_id INT(11) NOT NULL,
            technician_id INT(11) NOT NULL,
            checked_tools TEXT NOT NULL,
            checked_items TEXT NOT NULL,
            total_items INT(11) NOT NULL,
            checked_count INT(11) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_order_id (job_order_id),
            KEY technician_id (technician_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        
        if ($conn->query($createTableSQL)) {
            echo json_encode(['success' => true, 'message' => 'job_order_checklists table created successfully']);
        } else {
            throw new Exception("Failed to create job_order_checklists table: " . $conn->error);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'job_order_checklists table already exists']);
    }
    
    // Check if tools_equipment table has status column
    $statusColumnExists = $conn->query("SHOW COLUMNS FROM tools_equipment LIKE 'status'")->num_rows > 0;
    
    if (!$statusColumnExists) {
        // Add status column to tools_equipment table
        $addStatusColumnSQL = "ALTER TABLE tools_equipment ADD COLUMN status VARCHAR(20) DEFAULT 'in stock'";
        
        if ($conn->query($addStatusColumnSQL)) {
            echo json_encode(['success' => true, 'message' => 'Status column added to tools_equipment table']);
        } else {
            throw new Exception("Failed to add status column to tools_equipment table: " . $conn->error);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'Status column already exists in tools_equipment table']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
