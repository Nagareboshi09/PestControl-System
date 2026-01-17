<?php
// Include the database connection
require_once 'db_connect.php';

// Check if the assessment_report table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'assessment_report'");
if ($tableExists->num_rows == 0) {
    echo "The assessment_report table does not exist.\n";
    exit;
}

// Get the columns of the assessment_report table
$result = $conn->query("DESCRIBE assessment_report");
if (!$result) {
    echo "Error: " . $conn->error . "\n";
    exit;
}

echo "Columns in assessment_report table:\n";
echo "--------------------------------\n";
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

// Check if the frequency column exists
$frequencyExists = $conn->query("SHOW COLUMNS FROM assessment_report LIKE 'frequency'");
if ($frequencyExists->num_rows == 0) {
    echo "\nThe frequency column does not exist in the assessment_report table.\n";
    echo "Adding the frequency column...\n";

    // Add the frequency column
    $addColumn = $conn->query("ALTER TABLE assessment_report ADD COLUMN frequency ENUM('one-time','weekly','monthly','quarterly') DEFAULT 'one-time'");
    if ($addColumn) {
        echo "Successfully added the frequency column.\n";
    } else {
        echo "Failed to add the frequency column: " . $conn->error . "\n";
    }
} else {
    echo "\nThe frequency column exists in the assessment_report table.\n";
}

// Check if the type_of_work column exists
$typeOfWorkExists = $conn->query("SHOW COLUMNS FROM assessment_report LIKE 'type_of_work'");
if ($typeOfWorkExists->num_rows == 0) {
    echo "\nThe type_of_work column does not exist in the assessment_report table.\n";
    echo "Adding the type_of_work column...\n";

    // Add the type_of_work column
    $addColumn = $conn->query("ALTER TABLE assessment_report ADD COLUMN type_of_work VARCHAR(255) DEFAULT NULL");
    if ($addColumn) {
        echo "Successfully added the type_of_work column.\n";
    } else {
        echo "Failed to add the type_of_work column: " . $conn->error . "\n";
    }
} else {
    echo "\nThe type_of_work column exists in the assessment_report table.\n";
}

// Close the connection
$conn->close();
?>
