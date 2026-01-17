<?php
require_once 'db_connect.php';

// Check if job_order_checklists table exists
$result = $conn->query("SHOW TABLES LIKE 'job_order_checklists'");
$job_order_checklists_exists = $result->num_rows > 0;

echo "job_order_checklists table exists: " . ($job_order_checklists_exists ? 'Yes' : 'No') . "\n";

// If the table exists, show its structure
if ($job_order_checklists_exists) {
    $result = $conn->query("DESCRIBE job_order_checklists");
    echo "\nStructure of job_order_checklists table:\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . " - " . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . " - " . $row['Default'] . "\n";
    }
}

// Check if tools_equipment table has status column
$result = $conn->query("SHOW COLUMNS FROM tools_equipment LIKE 'status'");
$status_column_exists = $result->num_rows > 0;

echo "\ntools_equipment table has status column: " . ($status_column_exists ? 'Yes' : 'No') . "\n";

// Show sample data from tools_equipment table
$result = $conn->query("SELECT * FROM tools_equipment LIMIT 5");
echo "\nSample data from tools_equipment table:\n";
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . ", Name: " . $row['name'] . ", Category: " . $row['category'];
    if ($status_column_exists) {
        echo ", Status: " . ($row['status'] ?? 'NULL');
    }
    echo "\n";
}

// Check if technician_checklist_logs table exists
$result = $conn->query("SHOW TABLES LIKE 'technician_checklist_logs'");
$technician_checklist_logs_exists = $result->num_rows > 0;

echo "\ntechnician_checklist_logs table exists: " . ($technician_checklist_logs_exists ? 'Yes' : 'No') . "\n";

// If the table exists, show its structure
if ($technician_checklist_logs_exists) {
    $result = $conn->query("DESCRIBE technician_checklist_logs");
    echo "\nStructure of technician_checklist_logs table:\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . " - " . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . " - " . $row['Default'] . "\n";
    }
}

$conn->close();
?>
