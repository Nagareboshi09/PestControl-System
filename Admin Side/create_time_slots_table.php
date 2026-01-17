<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../db_connect.php';

// SQL to create the time_slot_config table
$sql = "
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

// Execute the SQL
try {
    if ($conn->query($sql) === TRUE) {
        echo "Table time_slot_config created successfully";
    } else {
        echo "Error creating table: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}

// Try to add a constraint to ensure either day_of_week or specific_date is set, but not both
// Note: This might not work on all MySQL versions, so we'll catch any exceptions
try {
    $constraintSql = "
    ALTER TABLE `time_slot_config` 
    ADD CONSTRAINT `check_date_or_day` 
    CHECK ((day_of_week IS NULL AND specific_date IS NOT NULL) OR (day_of_week IS NOT NULL AND specific_date IS NULL));
    ";
    
    if ($conn->query($constraintSql) === TRUE) {
        echo "<br>Constraint added successfully";
    } else {
        echo "<br>Error adding constraint: " . $conn->error;
    }
} catch (Exception $e) {
    echo "<br>Exception adding constraint (this is normal on some MySQL versions): " . $e->getMessage();
}

echo "<br>Done!";
?>
