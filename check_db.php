<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to plain text for better readability
header('Content-Type: text/plain');

echo "Database Check Script\n";
echo "====================\n\n";

// Connect to the database using MySQLi (Technician Side)
echo "Connecting using MySQLi (Technician Side)...\n";
try {
    require_once 'db_connect.php';
    echo "Connected successfully using MySQLi\n\n";
} catch (Exception $e) {
    echo "Error connecting using MySQLi: " . $e->getMessage() . "\n";
    exit;
}

// Check if the chemical_inventory table exists
$result = $conn->query("SHOW TABLES LIKE 'chemical_inventory'");
if ($result->num_rows == 0) {
    echo "Table chemical_inventory does not exist\n";
    exit;
}

echo "Table chemical_inventory exists\n";

// Get the structure of the chemical_inventory table
echo "Structure of chemical_inventory table:\n";
$result = $conn->query("DESCRIBE chemical_inventory");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

// Check if there are any records in the chemical_inventory table
$result = $conn->query("SELECT COUNT(*) as count FROM chemical_inventory");
$row = $result->fetch_assoc();
echo "\nNumber of records in chemical_inventory: " . $row['count'] . "\n";

// Check if there are any records in the job_order_report table
$result = $conn->query("SHOW TABLES LIKE 'job_order_report'");
if ($result->num_rows == 0) {
    echo "Table job_order_report does not exist\n";
} else {
    echo "Table job_order_report exists\n";

    // Get the structure of the job_order_report table
    echo "Structure of job_order_report table:\n";
    $result = $conn->query("DESCRIBE job_order_report");
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }

    // Check if there are any records in the job_order_report table
    $result = $conn->query("SELECT COUNT(*) as count FROM job_order_report");
    $row = $result->fetch_assoc();
    echo "\nNumber of records in job_order_report: " . $row['count'] . "\n";
}

// Check if there are any records in the chemical_usage_log table
$result = $conn->query("SHOW TABLES LIKE 'chemical_usage_log'");
if ($result->num_rows == 0) {
    echo "Table chemical_usage_log does not exist\n";
} else {
    echo "Table chemical_usage_log exists\n";

    // Get the structure of the chemical_usage_log table
    echo "Structure of chemical_usage_log table:\n";
    $result = $conn->query("DESCRIBE chemical_usage_log");
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }

    // Check if there are any records in the chemical_usage_log table
    $result = $conn->query("SELECT COUNT(*) as count FROM chemical_usage_log");
    $row = $result->fetch_assoc();
    echo "\nNumber of records in chemical_usage_log: " . $row['count'] . "\n";
}

// Now connect using PDO (Admin Side)
echo "\n\nConnecting using PDO (Admin Side)...\n";
try {
    require_once 'db_config.php';
    echo "Connected successfully using PDO\n\n";

    // Check if we can access the chemical_inventory table using PDO
    $stmt = $pdo->query("SELECT COUNT(*) FROM chemical_inventory");
    $count = $stmt->fetchColumn();
    echo "Number of records in chemical_inventory (PDO): " . $count . "\n";
} catch (Exception $e) {
    echo "Error connecting using PDO: " . $e->getMessage() . "\n";
}

// Close the connections
$conn->close();
echo "\nDatabase check completed.\n";
?>
