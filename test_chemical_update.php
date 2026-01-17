<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to plain text for better readability
header('Content-Type: text/plain');

echo "Chemical Inventory Update Test Script\n";
echo "===================================\n\n";

// Create a log file
$log_file = __DIR__ . '/chemical_update_test.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Test started\n");

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

// Connect using PDO (Admin Side)
log_message("Connecting using PDO (Admin Side)...");
try {
    require_once 'db_config.php';
    log_message("Connected successfully using PDO");
} catch (Exception $e) {
    log_message("Error connecting using PDO: " . $e->getMessage());
    exit;
}

// Test 1: Check if chemical_inventory table exists and has the same data in both connections
log_message("\nTest 1: Checking chemical_inventory table consistency");

// Check using MySQLi
$result = $conn->query("SELECT COUNT(*) as count FROM chemical_inventory");
$row = $result->fetch_assoc();
$mysqli_count = $row['count'];
log_message("Number of records in chemical_inventory (MySQLi): " . $mysqli_count);

// Check using PDO
$stmt = $pdo->query("SELECT COUNT(*) FROM chemical_inventory");
$pdo_count = $stmt->fetchColumn();
log_message("Number of records in chemical_inventory (PDO): " . $pdo_count);

if ($mysqli_count == $pdo_count) {
    log_message("✓ Both connections see the same number of records");
} else {
    log_message("✗ Record count mismatch between connections");
}

// Test 2: Check if we can update chemical inventory using MySQLi
log_message("\nTest 2: Testing chemical inventory update using MySQLi");

// Get a chemical to update
$result = $conn->query("SELECT id, chemical_name, quantity FROM chemical_inventory WHERE quantity > 0 LIMIT 1");
if ($result->num_rows == 0) {
    log_message("No chemicals with quantity > 0 found");
    exit;
}

$chemical = $result->fetch_assoc();
$chemical_id = $chemical['id'];
$chemical_name = $chemical['chemical_name'];
$original_quantity = $chemical['quantity'];

log_message("Selected chemical: ID=$chemical_id, Name=$chemical_name, Quantity=$original_quantity");

// Update the quantity (subtract 1)
$new_quantity = max(0, $original_quantity - 1);
log_message("Attempting to update quantity to: $new_quantity");

$update_query = "UPDATE chemical_inventory SET quantity = ? WHERE id = ?";
$update_stmt = $conn->prepare($update_query);
$update_stmt->bind_param("di", $new_quantity, $chemical_id);
$result = $update_stmt->execute();

if ($result) {
    log_message("✓ Update successful using MySQLi");
} else {
    log_message("✗ Update failed using MySQLi: " . $conn->error);
}

// Verify the update using MySQLi
$result = $conn->query("SELECT quantity FROM chemical_inventory WHERE id = $chemical_id");
$row = $result->fetch_assoc();
$mysqli_updated_quantity = $row['quantity'];
log_message("Updated quantity (MySQLi): $mysqli_updated_quantity");

// Verify the update using PDO
$stmt = $pdo->prepare("SELECT quantity FROM chemical_inventory WHERE id = ?");
$stmt->execute([$chemical_id]);
$pdo_updated_quantity = $stmt->fetchColumn();
log_message("Updated quantity (PDO): $pdo_updated_quantity");

if ($mysqli_updated_quantity == $pdo_updated_quantity && $mysqli_updated_quantity == $new_quantity) {
    log_message("✓ Both connections see the updated quantity correctly");
} else {
    log_message("✗ Quantity mismatch after update");
    log_message("  MySQLi: $mysqli_updated_quantity, PDO: $pdo_updated_quantity, Expected: $new_quantity");
}

// Test 3: Check if we can update chemical inventory using PDO
log_message("\nTest 3: Testing chemical inventory update using PDO");

// Restore the original quantity
log_message("Restoring original quantity: $original_quantity");

$stmt = $pdo->prepare("UPDATE chemical_inventory SET quantity = ? WHERE id = ?");
$stmt->execute([$original_quantity, $chemical_id]);

// Verify the update using PDO
$stmt = $pdo->prepare("SELECT quantity FROM chemical_inventory WHERE id = ?");
$stmt->execute([$chemical_id]);
$pdo_restored_quantity = $stmt->fetchColumn();
log_message("Restored quantity (PDO): $pdo_restored_quantity");

// Verify the update using MySQLi
$result = $conn->query("SELECT quantity FROM chemical_inventory WHERE id = $chemical_id");
$row = $result->fetch_assoc();
$mysqli_restored_quantity = $row['quantity'];
log_message("Restored quantity (MySQLi): $mysqli_restored_quantity");

if ($mysqli_restored_quantity == $pdo_restored_quantity && $mysqli_restored_quantity == $original_quantity) {
    log_message("✓ Both connections see the restored quantity correctly");
} else {
    log_message("✗ Quantity mismatch after restore");
    log_message("  MySQLi: $mysqli_restored_quantity, PDO: $pdo_restored_quantity, Expected: $original_quantity");
}

// Close the connections
$conn->close();
log_message("\nTest completed.");
?>
