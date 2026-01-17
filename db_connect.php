<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connect to local database
try {
    // Connect to localhost
    error_log("Attempting to connect to local database");
    $conn = new mysqli("localhost", "root", "", "macj_pest_control");
    error_log("Connected to local database");
} catch (Exception $e) {
    error_log("Failed to connect to production database: " . $e->getMessage());
    die("Could not connect to the database: " . $e->getMessage());
}

// Check connection
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
} else {
    error_log("Database connection successful. Server info: " . $conn->server_info);

    // Test query to verify database functionality
    try {
        $test_result = $conn->query("SELECT 1");
        if ($test_result) {
            error_log("Test query successful");
            $test_result->free();
        } else {
            error_log("Test query failed: " . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Exception during test query: " . $e->getMessage());
    }
}
?>