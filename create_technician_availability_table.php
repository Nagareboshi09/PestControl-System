<?php
// Include database connection
require_once 'db_connect.php';

// Create technician_availability table
$sql = "
CREATE TABLE IF NOT EXISTS `technician_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `technician_id` int(11) NOT NULL,
  `day_of_week` int(1) DEFAULT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
  `specific_date` date DEFAULT NULL COMMENT 'For date-specific availability, NULL for weekly pattern',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=available, 0=unavailable',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `technician_id` (`technician_id`),
  KEY `day_of_week` (`day_of_week`),
  KEY `specific_date` (`specific_date`),
  CONSTRAINT `technician_availability_ibfk_1` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

if ($conn->query($sql) === TRUE) {
    echo "Table 'technician_availability' created successfully or already exists.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Close connection
$conn->close();

echo "<a href='technicians.php'>Return to Technicians Page</a>";
?>
