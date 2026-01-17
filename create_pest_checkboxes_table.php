<?php
require_once 'db_connect.php';

// Check if the pest_checkboxes table already exists
$result = $conn->query("SHOW TABLES LIKE 'pest_checkboxes'");
if ($result->num_rows == 0) {
    // Table doesn't exist, create it
    $sql = file_get_contents('create_pest_checkboxes_table.sql');
    
    if ($conn->multi_query($sql)) {
        do {
            // Process each result set
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        
        echo "Success: The 'pest_checkboxes' table has been created and populated with default values.";
    } else {
        echo "Error creating pest_checkboxes table: " . $conn->error;
    }
} else {
    echo "The 'pest_checkboxes' table already exists.";
}

$conn->close();
?>
