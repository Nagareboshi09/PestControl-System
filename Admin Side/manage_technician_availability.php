<?php
// Include database connection
require_once '../db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if the request is AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Get the action from the request
$action = isset($_GET['action']) ? $_GET['action'] : '';

// For debugging
error_log("Action: " . $action);
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data: " . file_get_contents('php://input'));
}

// Handle different actions
try {
    switch ($action) {
        case 'get':
            // Get technician availability
            $technicianId = isset($_GET['technician_id']) ? intval($_GET['technician_id']) : 0;

            if (!$technicianId) {
                throw new Exception('Technician ID is required');
            }

            $result = getTechnicianAvailability($technicianId, $conn);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'add':
            // Add or update technician availability
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['technician_id']) || !$data['technician_id']) {
                throw new Exception('Technician ID is required');
            }

            $result = addTechnicianAvailability($data, $conn);
            echo json_encode(['success' => true, 'message' => 'Availability saved successfully', 'data' => $result]);
            break;

        case 'delete':
            // Delete technician availability
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

            if (!$id) {
                throw new Exception('Availability ID is required');
            }

            $result = deleteTechnicianAvailability($id, $conn);
            echo json_encode(['success' => true, 'message' => 'Availability deleted successfully']);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Function to get technician availability
function getTechnicianAvailability($technicianId, $conn) {
    // Get weekly availability
    $weeklyQuery = "SELECT * FROM technician_availability
                   WHERE technician_id = ? AND day_of_week IS NOT NULL AND specific_date IS NULL
                   ORDER BY day_of_week, start_time";
    $weeklyStmt = $conn->prepare($weeklyQuery);
    $weeklyStmt->bind_param("i", $technicianId);
    $weeklyStmt->execute();
    $weeklyResult = $weeklyStmt->get_result();

    // Debug log
    error_log("Fetching weekly availability for technician ID: $technicianId");

    $weekly = [];
    while ($row = $weeklyResult->fetch_assoc()) {
        // Ensure day_of_week is an integer
        $row['day_of_week'] = intval($row['day_of_week']);

        // Debug log
        $dayName = date('l', strtotime("Sunday +{$row['day_of_week']} days"));
        error_log("Found weekly availability: Day {$row['day_of_week']} ($dayName), Time: {$row['start_time']} - {$row['end_time']}, Available: {$row['is_available']}");

        $weekly[] = $row;
    }

    // Get specific date availability
    $specificQuery = "SELECT * FROM technician_availability
                     WHERE technician_id = ? AND specific_date IS NOT NULL
                     ORDER BY specific_date, start_time";
    $specificStmt = $conn->prepare($specificQuery);
    $specificStmt->bind_param("i", $technicianId);
    $specificStmt->execute();
    $specificResult = $specificStmt->get_result();

    $specific = [];
    while ($row = $specificResult->fetch_assoc()) {
        $specific[] = $row;
    }

    return [
        'weekly' => $weekly,
        'specific' => $specific
    ];
}

// Function to add or update technician availability
function addTechnicianAvailability($data, $conn) {
    // Check if we're updating an existing record by ID
    if (isset($data['id']) && $data['id'] > 0) {
        $id = intval($data['id']);
        $isAvailable = isset($data['is_available']) ? intval($data['is_available']) : 1;

        // Update the availability status
        $updateQuery = "UPDATE technician_availability
                       SET is_available = ?, updated_at = CURRENT_TIMESTAMP
                       WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("ii", $isAvailable, $id);
        $updateStmt->execute();

        return ['id' => $id, 'action' => 'updated'];
    }

    // Otherwise, we're adding a new record
    $technicianId = intval($data['technician_id']);
    $isAvailable = isset($data['is_available']) ? intval($data['is_available']) : 1;

    // Check if it's weekly or specific date
    if (isset($data['day_of_week']) && $data['day_of_week'] !== null) {
        // Weekly availability
        $dayOfWeek = intval($data['day_of_week']);
        $startTime = $data['start_time'];
        $endTime = $data['end_time'];

        // Check if this time slot already exists
        $checkQuery = "SELECT id FROM technician_availability
                      WHERE technician_id = ? AND day_of_week = ? AND start_time = ? AND end_time = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("iiss", $technicianId, $dayOfWeek, $startTime, $endTime);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            // Update existing record
            $row = $checkResult->fetch_assoc();
            $id = $row['id'];

            $updateQuery = "UPDATE technician_availability
                           SET is_available = ?, updated_at = CURRENT_TIMESTAMP
                           WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("ii", $isAvailable, $id);
            $updateStmt->execute();

            return ['id' => $id, 'action' => 'updated'];
        } else {
            // Insert new record
            $insertQuery = "INSERT INTO technician_availability
                           (technician_id, day_of_week, start_time, end_time, is_available)
                           VALUES (?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param("iissi", $technicianId, $dayOfWeek, $startTime, $endTime, $isAvailable);
            $insertStmt->execute();

            return ['id' => $conn->insert_id, 'action' => 'added'];
        }
    } elseif (isset($data['specific_date']) && $data['specific_date']) {
        // Specific date availability
        $specificDate = $data['specific_date'];
        $startTime = $data['start_time'];
        $endTime = $data['end_time'];

        // Check if this time slot already exists
        $checkQuery = "SELECT id FROM technician_availability
                      WHERE technician_id = ? AND specific_date = ? AND start_time = ? AND end_time = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("isss", $technicianId, $specificDate, $startTime, $endTime);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            // Update existing record
            $row = $checkResult->fetch_assoc();
            $id = $row['id'];

            $updateQuery = "UPDATE technician_availability
                           SET is_available = ?, updated_at = CURRENT_TIMESTAMP
                           WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("ii", $isAvailable, $id);
            $updateStmt->execute();

            return ['id' => $id, 'action' => 'updated'];
        } else {
            // Insert new record
            $insertQuery = "INSERT INTO technician_availability
                           (technician_id, specific_date, start_time, end_time, is_available)
                           VALUES (?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param("isssi", $technicianId, $specificDate, $startTime, $endTime, $isAvailable);
            $insertStmt->execute();

            return ['id' => $conn->insert_id, 'action' => 'added'];
        }
    } else {
        throw new Exception('Either day of week or specific date is required');
    }
}

// Function to delete technician availability
function deleteTechnicianAvailability($id, $conn) {
    $query = "DELETE FROM technician_availability WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('Availability not found or already deleted');
    }

    return true;
}
?>
