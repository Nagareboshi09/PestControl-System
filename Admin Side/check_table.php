<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../db_connect.php';

// Check if the time_slot_config table exists
$tableCheckQuery = "SHOW TABLES LIKE 'time_slot_config'";
$result = $conn->query($tableCheckQuery);

if ($result->num_rows > 0) {
    echo "Table time_slot_config exists.<br>";
    
    // Check the structure of the table
    $describeQuery = "DESCRIBE time_slot_config";
    $describeResult = $conn->query($describeQuery);
    
    if ($describeResult) {
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = $describeResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "Error describing table: " . $conn->error;
    }
    
    // Check if there are any records in the table
    $countQuery = "SELECT COUNT(*) as count FROM time_slot_config";
    $countResult = $conn->query($countQuery);
    
    if ($countResult) {
        $row = $countResult->fetch_assoc();
        echo "<br>Number of records in the table: " . $row['count'];
    } else {
        echo "<br>Error counting records: " . $conn->error;
    }
} else {
    echo "Table time_slot_config does not exist.<br>";
    
    // Try to create the table
    echo "<br>Attempting to create the table...<br>";
    
    $createTableSql = "
    CREATE TABLE IF NOT EXISTS `time_slot_config` (
      `config_id` int(11) NOT NULL AUTO_INCREMENT,
      `day_of_week` int(1) DEFAULT NULL COMMENT 'Day of week (0-6, where 0 is Sunday, 1 is Monday, etc.)',
      `specific_date` date DEFAULT NULL COMMENT 'Specific date (if this is set, day_of_week is ignored)',
      `time_slot` time NOT NULL COMMENT 'Time slot (HH:MM:SS format)',
      `is_available` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether this time slot is available (1) or unavailable (0)',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`config_id`),
      KEY `day_of_week_idx` (`day_of_week`),
      KEY `specific_date_idx` (`specific_date`),
      KEY `time_slot_idx` (`time_slot`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    if ($conn->query($createTableSql) === TRUE) {
        echo "Table created successfully.<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
}

// Add a test record
echo "<br><h3>Adding a test record:</h3>";

$insertSql = "INSERT INTO time_slot_config (day_of_week, time_slot, is_available) VALUES (1, '10:00:00', 1)";

if ($conn->query($insertSql) === TRUE) {
    echo "Test record added successfully.<br>";
} else {
    echo "Error adding test record: " . $conn->error . "<br>";
}

echo "<br><a href='calendar.php'>Go back to calendar</a>";
?>
