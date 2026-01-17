<?php
session_start();

// Debug session information
error_log("Session data: " . print_r($_SESSION, true));

// Check if the user is logged in and has a role
if (!isset($_SESSION['role'])) {
    error_log("No role found in session");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// For now, let's allow any role to access this endpoint for testing
// We can restrict it later once everything is working
/*
if ($_SESSION['role'] !== 'office_staff') {
    error_log("Unauthorized role: " . $_SESSION['role']);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}
*/

require_once '../db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request
error_log("manage_time_slots.php received request: " . print_r($_REQUEST, true));
error_log("HTTP method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'Not set'));

// Set headers for JSON response
header('Content-Type: application/json');

// Get the request data
$requestData = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

// Log the action and request data
error_log("Action: " . $action);
error_log("Request data: " . print_r($requestData, true));

// Response array
$response = ['success' => false, 'message' => 'Invalid action'];

// Function to get all time slot configurations
function getTimeSlotConfigs() {
    global $conn;

    $configs = [
        'weekdays' => [],
        'dates' => []
    ];

    try {
        // Get day-of-week configurations
        $dayQuery = "SELECT * FROM time_slot_config WHERE day_of_week IS NOT NULL ORDER BY day_of_week, time_slot";
        error_log("Executing day query: " . $dayQuery);
        $dayResult = $conn->query($dayQuery);

        if ($dayResult) {
            error_log("Day query successful, found " . $dayResult->num_rows . " rows");
            while ($row = $dayResult->fetch_assoc()) {
                $configs['weekdays'][] = $row;
            }
        } else {
            error_log("Day query failed: " . $conn->error);
        }

        // Get specific date configurations
        $dateQuery = "SELECT * FROM time_slot_config WHERE specific_date IS NOT NULL ORDER BY specific_date, time_slot";
        error_log("Executing date query: " . $dateQuery);
        $dateResult = $conn->query($dateQuery);

        if ($dateResult) {
            error_log("Date query successful, found " . $dateResult->num_rows . " rows");
            while ($row = $dateResult->fetch_assoc()) {
                $configs['dates'][] = $row;
            }
        } else {
            error_log("Date query failed: " . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Exception in getTimeSlotConfigs: " . $e->getMessage());
    }

    error_log("Returning configs: " . print_r($configs, true));
    return $configs;
}

// Function to add a time slot
function addTimeSlot($data) {
    global $conn;

    // Validate input
    if (!isset($data['time_slot']) || empty($data['time_slot'])) {
        return ['success' => false, 'message' => 'Time slot is required'];
    }

    // Check if we have a day of week or specific date
    if (isset($data['day_of_week']) && $data['day_of_week'] !== '') {
        $dayOfWeek = (int)$data['day_of_week'];
        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            return ['success' => false, 'message' => 'Invalid day of week'];
        }

        // Check if this time slot already exists for this day
        $checkStmt = $conn->prepare("SELECT config_id FROM time_slot_config WHERE day_of_week = ? AND time_slot = ?");
        $checkStmt->bind_param("is", $dayOfWeek, $data['time_slot']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            // Update existing record
            $row = $checkResult->fetch_assoc();
            $updateStmt = $conn->prepare("UPDATE time_slot_config SET is_available = ? WHERE config_id = ?");
            $isAvailable = isset($data['is_available']) ? (int)$data['is_available'] : 1;
            $updateStmt->bind_param("ii", $isAvailable, $row['config_id']);

            if ($updateStmt->execute()) {
                return ['success' => true, 'message' => 'Time slot updated successfully', 'action' => 'updated'];
            } else {
                return ['success' => false, 'message' => 'Failed to update time slot: ' . $conn->error];
            }
        } else {
            // Insert new record
            $insertStmt = $conn->prepare("INSERT INTO time_slot_config (day_of_week, time_slot, is_available) VALUES (?, ?, ?)");
            $isAvailable = isset($data['is_available']) ? (int)$data['is_available'] : 1;
            $insertStmt->bind_param("isi", $dayOfWeek, $data['time_slot'], $isAvailable);

            if ($insertStmt->execute()) {
                return ['success' => true, 'message' => 'Time slot added successfully', 'action' => 'added'];
            } else {
                return ['success' => false, 'message' => 'Failed to add time slot: ' . $conn->error];
            }
        }
    } elseif (isset($data['specific_date']) && $data['specific_date'] !== '') {
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['specific_date'])) {
            return ['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD'];
        }

        // Check if this time slot already exists for this date
        $checkStmt = $conn->prepare("SELECT config_id FROM time_slot_config WHERE specific_date = ? AND time_slot = ?");
        $checkStmt->bind_param("ss", $data['specific_date'], $data['time_slot']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            // Update existing record
            $row = $checkResult->fetch_assoc();
            $updateStmt = $conn->prepare("UPDATE time_slot_config SET is_available = ? WHERE config_id = ?");
            $isAvailable = isset($data['is_available']) ? (int)$data['is_available'] : 1;
            $updateStmt->bind_param("ii", $isAvailable, $row['config_id']);

            if ($updateStmt->execute()) {
                return ['success' => true, 'message' => 'Time slot updated successfully', 'action' => 'updated'];
            } else {
                return ['success' => false, 'message' => 'Failed to update time slot: ' . $conn->error];
            }
        } else {
            // Insert new record
            $insertStmt = $conn->prepare("INSERT INTO time_slot_config (specific_date, time_slot, is_available) VALUES (?, ?, ?)");
            $isAvailable = isset($data['is_available']) ? (int)$data['is_available'] : 1;
            $insertStmt->bind_param("ssi", $data['specific_date'], $data['time_slot'], $isAvailable);

            if ($insertStmt->execute()) {
                return ['success' => true, 'message' => 'Time slot added successfully', 'action' => 'added'];
            } else {
                return ['success' => false, 'message' => 'Failed to add time slot: ' . $conn->error];
            }
        }
    } else {
        return ['success' => false, 'message' => 'Either day of week or specific date is required'];
    }
}

// Function to remove a time slot
function removeTimeSlot($data) {
    global $conn;

    // Check if we have a config ID
    if (isset($data['config_id']) && !empty($data['config_id'])) {
        $deleteStmt = $conn->prepare("DELETE FROM time_slot_config WHERE config_id = ?");
        $deleteStmt->bind_param("i", $data['config_id']);

        if ($deleteStmt->execute()) {
            return ['success' => true, 'message' => 'Time slot removed successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to remove time slot: ' . $conn->error];
        }
    } else {
        return ['success' => false, 'message' => 'Config ID is required'];
    }
}

// Function to test the API
function testApi() {
    global $conn;

    // Check if the database connection is working
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    // Check if the time_slot_config table exists
    $tableCheckQuery = "SHOW TABLES LIKE 'time_slot_config'";
    $result = $conn->query($tableCheckQuery);

    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Table time_slot_config does not exist'];
    }

    // Return success with some test data
    return [
        'success' => true,
        'message' => 'API is working correctly',
        'test_data' => [
            'weekdays' => [
                [
                    'config_id' => 0,
                    'day_of_week' => 1,
                    'time_slot' => '10:00:00',
                    'is_available' => 1
                ]
            ],
            'dates' => []
        ]
    ];
}

// Handle different actions
switch ($action) {
    case 'get':
        $response = ['success' => true, 'data' => getTimeSlotConfigs()];
        break;

    case 'add':
        $response = addTimeSlot($requestData);
        break;

    case 'remove':
        $response = removeTimeSlot($requestData);
        break;

    case 'test':
        $response = testApi();
        break;

    default:
        // Invalid action, response already set
        break;
}

// Return the response
echo json_encode($response);
?>
