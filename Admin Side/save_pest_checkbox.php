<?php
session_start();
require_once '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Check if pest_checkboxes table exists, if not create it
    $table_exists = false;
    $tables_result = $conn->query("SHOW TABLES LIKE 'pest_checkboxes'");
    if ($tables_result && $tables_result->num_rows > 0) {
        $table_exists = true;
    }

    if (!$table_exists) {
        $sql = file_get_contents('../create_pest_checkboxes_table.sql');
        if ($conn->multi_query($sql)) {
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
        }

        // Reconnect to avoid "Commands out of sync" error
        $conn->close();
        require_once '../db_connect.php';
    }

    // Get form data
    $name = trim($_POST['name']);
    $status = $_POST['status'] ?? 'active';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    // Validate input
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Pest checkbox name is required']);
        exit;
    }

    // Check if name already exists (for new entries)
    if (!$id) {
        $check_query = "SELECT id FROM pest_checkboxes WHERE name = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'A pest checkbox with this name already exists']);
            exit;
        }
    }

    if ($id) {
        // Update existing pest checkbox
        $query = "UPDATE pest_checkboxes SET name = ?, status = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $name, $status, $id);
    } else {
        // Insert new pest checkbox
        $query = "INSERT INTO pest_checkboxes (name, status) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $name, $status);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save pest checkbox: ' . $stmt->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
