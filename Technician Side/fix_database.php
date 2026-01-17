<?php
// Include database connection
require_once '../db_connect.php';

// Create a log file for debugging
$log_file = __DIR__ . '/../logs/fix_database.log';
if (!file_exists(__DIR__ . '/../logs/')) {
    mkdir(__DIR__ . '/../logs/', 0777, true);
}

// Log function for easier debugging
function log_debug($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    echo date('Y-m-d H:i:s') . " - " . $message . "<br>";
}

log_debug("Database fix script started");

// Check database connection
log_debug("Database connection status: " . ($conn ? "Connected" : "Not connected"));
if (!$conn) {
    log_debug("Database connection failed. Please check your connection settings.");
    exit;
}

// Check if the assessment_report table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'assessment_report'");
if ($tableExists->num_rows == 0) {
    log_debug("The assessment_report table does not exist. Creating it...");
    
    // Create the assessment_report table
    $createTable = $conn->query("
        CREATE TABLE assessment_report (
            report_id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NOT NULL,
            end_time TIME NOT NULL,
            area VARCHAR(50) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            recommendation TEXT DEFAULT NULL,
            attachments TEXT DEFAULT NULL,
            pest_types VARCHAR(255) DEFAULT NULL,
            problem_area VARCHAR(255) DEFAULT NULL,
            preferred_date DATE DEFAULT NULL,
            preferred_time TIME DEFAULT NULL,
            frequency ENUM('one-time','weekly','monthly','quarterly') DEFAULT 'one-time',
            chemical_recommendations TEXT DEFAULT NULL,
            type_of_work VARCHAR(255) DEFAULT NULL,
            report_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    if ($createTable) {
        log_debug("Successfully created the assessment_report table.");
    } else {
        log_debug("Failed to create the assessment_report table: " . $conn->error);
    }
} else {
    log_debug("The assessment_report table exists.");
    
    // Check if all required columns exist
    $requiredColumns = [
        'report_id' => 'INT AUTO_INCREMENT PRIMARY KEY',
        'appointment_id' => 'INT NOT NULL',
        'end_time' => 'TIME NOT NULL',
        'area' => 'VARCHAR(50) DEFAULT NULL',
        'notes' => 'TEXT DEFAULT NULL',
        'recommendation' => 'TEXT DEFAULT NULL',
        'attachments' => 'TEXT DEFAULT NULL',
        'pest_types' => 'VARCHAR(255) DEFAULT NULL',
        'problem_area' => 'VARCHAR(255) DEFAULT NULL',
        'preferred_date' => 'DATE DEFAULT NULL',
        'preferred_time' => 'TIME DEFAULT NULL',
        'frequency' => "ENUM('one-time','weekly','monthly','quarterly') DEFAULT 'one-time'",
        'chemical_recommendations' => 'TEXT DEFAULT NULL',
        'type_of_work' => 'VARCHAR(255) DEFAULT NULL',
        'report_date' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ];
    
    // Get existing columns
    $result = $conn->query("DESCRIBE assessment_report");
    $existingColumns = [];
    while ($row = $result->fetch_assoc()) {
        $existingColumns[$row['Field']] = $row['Type'];
        log_debug("Found column: " . $row['Field'] . " - " . $row['Type']);
    }
    
    // Check for missing columns
    $missingColumns = [];
    foreach ($requiredColumns as $column => $type) {
        if (!array_key_exists($column, $existingColumns)) {
            $missingColumns[$column] = $type;
        }
    }
    
    // Add missing columns
    if (!empty($missingColumns)) {
        log_debug("Missing columns found: " . implode(', ', array_keys($missingColumns)));
        
        foreach ($missingColumns as $column => $type) {
            $alterQuery = "ALTER TABLE assessment_report ADD COLUMN $column $type";
            log_debug("Adding column with query: " . $alterQuery);
            
            $result = $conn->query($alterQuery);
            if ($result) {
                log_debug("Successfully added column: " . $column);
            } else {
                log_debug("Failed to add column: " . $column . " - Error: " . $conn->error);
            }
        }
    } else {
        log_debug("All required columns exist in the assessment_report table.");
    }
}

// Check if the logs directory exists
if (!file_exists(__DIR__ . '/../logs/')) {
    mkdir(__DIR__ . '/../logs/', 0777, true);
    log_debug("Created logs directory.");
} else {
    log_debug("Logs directory exists.");
}

// Check if the uploads directory exists
if (!file_exists(__DIR__ . '/../uploads/')) {
    mkdir(__DIR__ . '/../uploads/', 0777, true);
    log_debug("Created uploads directory.");
} else {
    log_debug("Uploads directory exists.");
}

// Check if the chemical_dosage.log file exists
$chemicalLogFile = __DIR__ . '/../logs/chemical_dosage.log';
if (!file_exists($chemicalLogFile)) {
    file_put_contents($chemicalLogFile, date('Y-m-d H:i:s') . " - Chemical dosage log file created\n");
    log_debug("Created chemical_dosage.log file.");
} else {
    log_debug("Chemical_dosage.log file exists.");
}

log_debug("Database fix script completed.");
?>
