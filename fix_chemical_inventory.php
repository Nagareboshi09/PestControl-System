<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to plain text for better readability
header('Content-Type: text/plain');

echo "Chemical Inventory Fix Script\n";
echo "===========================\n\n";

// Create a log file
$log_file = __DIR__ . '/chemical_fix.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Fix script started\n");

function log_message($message) {
    global $log_file;
    $log_entry = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    echo $message . "\n";
}

// Connect to the database using MySQLi (Technician Side)
log_message("Connecting using MySQLi (Technician Side)...");
try {
    require_once 'db_connect.php';
    log_message("Connected successfully using MySQLi");
} catch (Exception $e) {
    log_message("Error connecting using MySQLi: " . $e->getMessage());
    exit;
}

// Check if chemical_usage_log table exists
$result = $conn->query("SHOW TABLES LIKE 'chemical_usage_log'");
if ($result->num_rows == 0) {
    log_message("Creating chemical_usage_log table...");
    
    $create_table_sql = "CREATE TABLE chemical_usage_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chemical_id INT NOT NULL,
        technician_id INT NOT NULL,
        job_order_id INT NOT NULL,
        quantity_used DECIMAL(10,2) NOT NULL,
        usage_date DATE NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (chemical_id) REFERENCES chemical_inventory(id),
        INDEX (chemical_id),
        INDEX (job_order_id)
    )";
    
    if ($conn->query($create_table_sql)) {
        log_message("✓ chemical_usage_log table created successfully");
    } else {
        log_message("✗ Failed to create chemical_usage_log table: " . $conn->error);
        exit;
    }
} else {
    log_message("chemical_usage_log table already exists");
}

// Fix 1: Create a function to update chemical inventory that works with both MySQLi and PDO
log_message("\nFix 1: Creating unified chemical inventory update function");

// Create the function file
$function_file = __DIR__ . '/chemical_inventory_functions.php';
$function_content = '<?php
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
?>';

file_put_contents($function_file, $function_content);
log_message("✓ Created chemical_inventory_functions.php");

// Fix 2: Update the submit_job_report.php file to use the new functions
log_message("\nFix 2: Checking submit_job_report.php");

// Check if the file exists
if (!file_exists(__DIR__ . '/Technician Side/submit_job_report.php')) {
    log_message("✗ File not found: Technician Side/submit_job_report.php");
} else {
    log_message("✓ File found: Technician Side/submit_job_report.php");
    log_message("Please update the file manually using the provided instructions");
}

// Fix 3: Update the job_order.php file to display chemical usage in the success modal
log_message("\nFix 3: Checking job_order.php");

// Check if the file exists
if (!file_exists(__DIR__ . '/Technician Side/job_order.php')) {
    log_message("✗ File not found: Technician Side/job_order.php");
} else {
    log_message("✓ File found: Technician Side/job_order.php");
    log_message("Please update the file manually using the provided instructions");
}

// Close the connection
$conn->close();
log_message("\nFix script completed. Please follow the manual update instructions.");
?>
