<?php
/**
 * Functions for chemical inventory management
 * These functions work with both MySQLi and PDO connections
 */

/**
 * Update chemical inventory quantity
 * 
 * @param mixed $conn MySQLi or PDO connection
 * @param int $chemical_id Chemical ID
 * @param float $new_quantity New quantity
 * @param string $connection_type Type of connection ("mysqli" or "pdo")
 * @return bool Success status
 */
function update_chemical_inventory_quantity($conn, $chemical_id, $new_quantity, $connection_type = "mysqli") {
    // Ensure new quantity is not negative
    $new_quantity = max(0, $new_quantity);
    
    // Determine status based on quantity
    $status = "In Stock";
    if ($new_quantity <= 0) {
        $status = "Out of Stock";
    } else if ($new_quantity < 10) {
        $status = "Low Stock";
    }
    
    // Update based on connection type
    if ($connection_type === "mysqli") {
        // MySQLi connection
        $stmt = $conn->prepare("UPDATE chemical_inventory SET quantity = ?, status = ? WHERE id = ?");
        $stmt->bind_param("dsi", $new_quantity, $status, $chemical_id);
        return $stmt->execute();
    } else {
        // PDO connection
        $stmt = $conn->prepare("UPDATE chemical_inventory SET quantity = ?, status = ? WHERE id = ?");
        return $stmt->execute([$new_quantity, $status, $chemical_id]);
    }
}

/**
 * Log chemical usage
 * 
 * @param mixed $conn MySQLi or PDO connection
 * @param int $chemical_id Chemical ID
 * @param int $technician_id Technician ID
 * @param int $job_order_id Job order ID
 * @param float $quantity_used Quantity used
 * @param string $notes Usage notes
 * @param string $connection_type Type of connection ("mysqli" or "pdo")
 * @return bool Success status
 */
function log_chemical_usage($conn, $chemical_id, $technician_id, $job_order_id, $quantity_used, $notes = "", $connection_type = "mysqli") {
    $today = date("Y-m-d");
    
    // Check if chemical_usage_log table exists
    if ($connection_type === "mysqli") {
        $result = $conn->query("SHOW TABLES LIKE 'chemical_usage_log'");
        if ($result->num_rows == 0) {
            // Create the table if it doesn't exist
            $create_table_sql = "CREATE TABLE chemical_usage_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                chemical_id INT NOT NULL,
                technician_id INT NOT NULL,
                job_order_id INT NOT NULL,
                quantity_used DECIMAL(10,2) NOT NULL,
                usage_date DATE NOT NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (chemical_id),
                INDEX (job_order_id)
            )";
            $conn->query($create_table_sql);
        }
    } else {
        $result = $conn->query("SHOW TABLES LIKE 'chemical_usage_log'");
        if ($result->rowCount() == 0) {
            // Create the table if it doesn't exist
            $create_table_sql = "CREATE TABLE chemical_usage_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                chemical_id INT NOT NULL,
                technician_id INT NOT NULL,
                job_order_id INT NOT NULL,
                quantity_used DECIMAL(10,2) NOT NULL,
                usage_date DATE NOT NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (chemical_id),
                INDEX (job_order_id)
            )";
            $conn->exec($create_table_sql);
        }
    }
    
    if ($connection_type === "mysqli") {
        // MySQLi connection
        $stmt = $conn->prepare("INSERT INTO chemical_usage_log 
                              (chemical_id, technician_id, job_order_id, quantity_used, usage_date, notes)
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiidss", $chemical_id, $technician_id, $job_order_id, $quantity_used, $today, $notes);
        return $stmt->execute();
    } else {
        // PDO connection
        $stmt = $conn->prepare("INSERT INTO chemical_usage_log 
                              (chemical_id, technician_id, job_order_id, quantity_used, usage_date, notes)
                              VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$chemical_id, $technician_id, $job_order_id, $quantity_used, $today, $notes]);
    }
}

/**
 * Get chemical information by ID
 * 
 * @param mixed $conn MySQLi or PDO connection
 * @param int $chemical_id Chemical ID
 * @param string $connection_type Type of connection ("mysqli" or "pdo")
 * @return array|null Chemical information or null if not found
 */
function get_chemical_by_id($conn, $chemical_id, $connection_type = "mysqli") {
    if ($connection_type === "mysqli") {
        // MySQLi connection
        $stmt = $conn->prepare("SELECT id, chemical_name, type, quantity, unit FROM chemical_inventory WHERE id = ?");
        $stmt->bind_param("i", $chemical_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    } else {
        // PDO connection
        $stmt = $conn->prepare("SELECT id, chemical_name, type, quantity, unit FROM chemical_inventory WHERE id = ?");
        $stmt->execute([$chemical_id]);
        return $stmt->fetch();
    }
}

/**
 * Get chemical usage for a job order
 * 
 * @param mixed $conn MySQLi or PDO connection
 * @param int $job_order_id Job order ID
 * @param string $connection_type Type of connection ("mysqli" or "pdo")
 * @return array Chemical usage information
 */
function get_chemical_usage_for_job($conn, $job_order_id, $connection_type = "mysqli") {
    $chemicals = [];
    
    if ($connection_type === "mysqli") {
        // MySQLi connection
        $stmt = $conn->prepare("
            SELECT cul.chemical_id, cul.quantity_used, cul.usage_date, 
                   ci.chemical_name, ci.type, ci.unit
            FROM chemical_usage_log cul
            JOIN chemical_inventory ci ON cul.chemical_id = ci.id
            WHERE cul.job_order_id = ?
        ");
        $stmt->bind_param("i", $job_order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $chemicals[] = $row;
        }
    } else {
        // PDO connection
        $stmt = $conn->prepare("
            SELECT cul.chemical_id, cul.quantity_used, cul.usage_date, 
                   ci.chemical_name, ci.type, ci.unit
            FROM chemical_usage_log cul
            JOIN chemical_inventory ci ON cul.chemical_id = ci.id
            WHERE cul.job_order_id = ?
        ");
        $stmt->execute([$job_order_id]);
        $chemicals = $stmt->fetchAll();
    }
    
    return $chemicals;
}
?>
