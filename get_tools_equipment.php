<?php
/**
 * Get tools and equipment data for technician checklist
 */
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Check if status column exists
    $statusColumnExists = $conn->query("SHOW COLUMNS FROM tools_equipment LIKE 'status'")->num_rows > 0;

    // Get available tools and equipment (in stock or NULL status)
    if ($statusColumnExists) {
        $query = "SELECT id, name, category, description, status FROM tools_equipment
                 WHERE status IS NULL OR status = 'in stock'
                 ORDER BY category, name";
    } else {
        $query = "SELECT id, name, category, description FROM tools_equipment ORDER BY category, name";
    }

    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    $tools = [];

    // Group tools by category
    while ($row = $result->fetch_assoc()) {
        $category = $row['category'];

        if (!isset($tools[$category])) {
            $tools[$category] = [];
        }

        $toolData = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description']
        ];

        // Add status if it exists
        if ($statusColumnExists && isset($row['status'])) {
            $toolData['status'] = $row['status'];
        }

        $tools[$category][] = $toolData;
    }

    echo json_encode(['success' => true, 'data' => $tools]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
