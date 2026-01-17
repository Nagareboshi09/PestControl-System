<?php
require_once '../db_connect.php';

// Set content type to plain text for easier reading
header('Content-Type: text/plain');

echo "Assessment Report Table Fix Script\n";
echo "=================================\n\n";

// Check if the assessment_report table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'assessment_report'");
if ($tableExists->num_rows == 0) {
    echo "The assessment_report table does not exist. Creating it...\n";
    
    // Create the table
    $createTable = $conn->query("
        CREATE TABLE assessment_report (
            report_id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NOT NULL,
            end_time TIME NOT NULL,
            area DECIMAL(10,2) NOT NULL,
            notes TEXT DEFAULT NULL,
            recommendation TEXT DEFAULT NULL,
            attachments VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            pest_types VARCHAR(255) DEFAULT NULL,
            problem_area VARCHAR(255) DEFAULT NULL,
            chemical_recommendations TEXT DEFAULT NULL,
            preferred_date DATE DEFAULT NULL,
            preferred_time TIME DEFAULT NULL,
            frequency ENUM('one-time','weekly','monthly','quarterly') DEFAULT 'one-time',
            type_of_work VARCHAR(255) DEFAULT NULL
        )
    ");
    
    if ($createTable) {
        echo "Table created successfully.\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
        exit;
    }
} else {
    echo "The assessment_report table exists.\n";
}

// Get the current columns in the table
$result = $conn->query("DESCRIBE assessment_report");
$existingColumns = [];
while ($row = $result->fetch_assoc()) {
    $existingColumns[$row['Field']] = $row['Type'];
    echo "Found column: " . $row['Field'] . " - " . $row['Type'] . "\n";
}

// Define the required columns and their types
$requiredColumns = [
    'report_id' => 'INT AUTO_INCREMENT PRIMARY KEY',
    'appointment_id' => 'INT NOT NULL',
    'end_time' => 'TIME NOT NULL',
    'area' => 'DECIMAL(10,2) NOT NULL',
    'notes' => 'TEXT DEFAULT NULL',
    'recommendation' => 'TEXT DEFAULT NULL',
    'attachments' => 'VARCHAR(255) DEFAULT NULL',
    'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'pest_types' => 'VARCHAR(255) DEFAULT NULL',
    'problem_area' => 'VARCHAR(255) DEFAULT NULL',
    'chemical_recommendations' => 'TEXT DEFAULT NULL',
    'preferred_date' => 'DATE DEFAULT NULL',
    'preferred_time' => 'TIME DEFAULT NULL',
    'frequency' => "ENUM('one-time','weekly','monthly','quarterly') DEFAULT 'one-time'",
    'type_of_work' => 'VARCHAR(255) DEFAULT NULL'
];

// Check for missing columns
$missingColumns = [];
foreach ($requiredColumns as $column => $type) {
    if (!array_key_exists($column, $existingColumns)) {
        $missingColumns[$column] = $type;
    }
}

// Add missing columns
if (!empty($missingColumns)) {
    echo "\nMissing columns found: " . implode(', ', array_keys($missingColumns)) . "\n";
    
    foreach ($missingColumns as $column => $type) {
        $alterQuery = "ALTER TABLE assessment_report ADD COLUMN $column $type";
        echo "Adding column with query: " . $alterQuery . "\n";
        
        $result = $conn->query($alterQuery);
        if ($result) {
            echo "Successfully added column: " . $column . "\n";
        } else {
            echo "Failed to add column: " . $column . " - Error: " . $conn->error . "\n";
        }
    }
} else {
    echo "\nAll required columns exist in the assessment_report table.\n";
}

// Test inserting a dummy record
echo "\nTesting insertion of a dummy record...\n";

$testInsert = $conn->query("
    INSERT INTO assessment_report 
    (appointment_id, end_time, area, notes, recommendation, pest_types, problem_area, 
     preferred_date, preferred_time, frequency, chemical_recommendations, type_of_work)
    VALUES 
    (1, '12:00:00', 100.00, 'Test notes', 'Test recommendation', 'Ants', 'Kitchen', 
     '2025-05-20', '10:00:00', 'one-time', '[{\"id\":\"1\",\"name\":\"Test\"}]', 'General Pest Control')
");

if ($testInsert) {
    $insertId = $conn->insert_id;
    echo "Test record inserted successfully with ID: " . $insertId . "\n";
    
    // Delete the test record
    $conn->query("DELETE FROM assessment_report WHERE report_id = $insertId");
    echo "Test record deleted.\n";
} else {
    echo "Error inserting test record: " . $conn->error . "\n";
}

echo "\nFix script completed.\n";
?>
