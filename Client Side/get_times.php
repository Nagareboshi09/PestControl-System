<?php
// Set timezone to ensure correct date calculations
date_default_timezone_set('Asia/Manila'); // Philippines timezone

// Enhanced version of get_times.php with better error handling and debugging

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all requests with detailed information
error_log("get_times.php received request: " . print_r($_REQUEST, true));
error_log("HTTP method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'Not set'));

// Set headers to prevent caching and allow CORS
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');

// Create a response array
$response = array(
    'booked' => array(),
    'available_slots' => array(),
    'default_slots' => true
);

// Check for date in both POST and GET
$date = null;
if (isset($_POST['date'])) {
    $date = $_POST['date'];
    error_log("Date provided in POST: {$date}");
} elseif (isset($_GET['date'])) {
    $date = $_GET['date'];
    error_log("Date provided in GET: {$date}");
} else {
    // Try to get data from php://input for JSON requests
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        error_log("Raw input: {$input}");
        $jsonData = json_decode($input, true);
        if ($jsonData && isset($jsonData['date'])) {
            $date = $jsonData['date'];
            error_log("Date provided in JSON: {$date}");
        }
    }
}

// Process the date if provided
if ($date) {
    $response['date_received'] = $date;

    // Include database connection to get real booked times
    try {
        include '../db_connect.php';

        // Get booked times for the selected date from the database
        // Exclude cancelled and declined appointments
        $stmt = $conn->prepare("SELECT preferred_time FROM appointments WHERE preferred_date = ? AND status NOT IN ('cancelled', 'declined')");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();

        // Clear any test data and use real data
        $response['booked'] = [];

        while ($row = $result->fetch_assoc()) {
            $response['booked'][] = $row['preferred_time'];
        }

        // Get the day of week for the selected date (0 = Sunday, 1 = Monday, etc.)
        $dayOfWeek = intval(date('w', strtotime($date)));
        error_log("get_times.php - Date: $date, Day of Week: $dayOfWeek (" . date('l', strtotime($date)) . ")");

        // Check for custom time slot configurations for this specific date
        $specificDateQuery = $conn->prepare("
            SELECT time_slot, is_available
            FROM time_slot_config
            WHERE specific_date = ?
            ORDER BY time_slot
        ");
        $specificDateQuery->bind_param("s", $date);
        $specificDateQuery->execute();
        $specificDateResult = $specificDateQuery->get_result();

        // Check for custom time slot configurations for this day of week
        $dayOfWeekQuery = $conn->prepare("
            SELECT time_slot, is_available
            FROM time_slot_config
            WHERE day_of_week = ?
            ORDER BY time_slot
        ");
        $dayOfWeekQuery->bind_param("i", $dayOfWeek);
        $dayOfWeekQuery->execute();
        $dayOfWeekResult = $dayOfWeekQuery->get_result();

        // Generate default time slots (7:00 AM to 9:00 PM)
        $defaultTimeSlots = [];
        $unavailableTimeSlots = [];
        $customTimeSlots = [];

        // First, collect all custom and unavailable time slots

        // Check specific date configurations first (they have priority)
        $hasSpecificDateConfig = false;
        if ($specificDateResult->num_rows > 0) {
            $hasSpecificDateConfig = true;

            while ($row = $specificDateResult->fetch_assoc()) {
                if ($row['is_available'] == 1) {
                    // This is a custom available time slot
                    $customTimeSlots[] = $row['time_slot'];
                } else {
                    // This is an unavailable time slot
                    $unavailableTimeSlots[] = $row['time_slot'];
                }
            }

            error_log("Found specific date time slot configuration for {$date}");
        }

        // If no specific date config, check day of week configurations
        if (!$hasSpecificDateConfig && $dayOfWeekResult->num_rows > 0) {
            while ($row = $dayOfWeekResult->fetch_assoc()) {
                if ($row['is_available'] == 1) {
                    // This is a custom available time slot
                    $customTimeSlots[] = $row['time_slot'];
                } else {
                    // This is an unavailable time slot
                    $unavailableTimeSlots[] = $row['time_slot'];
                }
            }

            error_log("Found day of week time slot configuration for day {$dayOfWeek}");
        }

        // Check for technician availability for this day
        $techAvailQuery = $conn->prepare("
            SELECT DISTINCT TIME(start_time) as start_time, TIME(end_time) as end_time
            FROM technician_availability
            WHERE is_available = 1
            AND (
                (day_of_week = ? AND specific_date IS NULL)
                OR
                (specific_date = ?)
            )
            ORDER BY start_time
        ");
        $techAvailQuery->bind_param("is", $dayOfWeek, $date);
        $techAvailQuery->execute();
        $techAvailResult = $techAvailQuery->get_result();

        $techAvailability = [];
        while ($row = $techAvailResult->fetch_assoc()) {
            $techAvailability[] = [
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time']
            ];
        }

        error_log("Found " . count($techAvailability) . " technician availability records for day $dayOfWeek or date $date");

        // Generate time slots based on technician availability
        $availableHours = [];

        if (!empty($techAvailability)) {
            // Use technician availability to determine available hours
            foreach ($techAvailability as $avail) {
                $startHour = intval(substr($avail['start_time'], 0, 2));
                $endHour = intval(substr($avail['end_time'], 0, 2));

                // Ensure we don't go past 9 PM (21:00)
                $endHour = min($endHour, 21);

                for ($hour = $startHour; $hour <= $endHour; $hour++) {
                    $availableHours[$hour] = true;
                }
            }

            error_log("Available hours based on technician availability: " . implode(", ", array_keys($availableHours)));
        } else {
            // Fallback to default hours if no technician availability is found
            for ($hour = 7; $hour <= 21; $hour++) {
                $availableHours[$hour] = true;
            }
            error_log("No technician availability found, using default hours (7-21)");
        }

        // Generate default time slots based on available hours
        foreach ($availableHours as $hour => $available) {
            $timeSlot = sprintf("%02d:00:00", $hour);

            // Skip if this default time slot is marked as unavailable
            if (in_array($timeSlot, $unavailableTimeSlots)) {
                continue;
            }

            $defaultTimeSlots[] = $timeSlot;
        }

        // Merge default and custom time slots
        $allTimeSlots = array_merge($defaultTimeSlots, $customTimeSlots);

        // Remove duplicates and sort
        $allTimeSlots = array_unique($allTimeSlots);
        sort($allTimeSlots);

        // Set the response
        $response['available_slots'] = $allTimeSlots;
        $response['default_slots'] = false; // Always use the available_slots array
        $response['technician_availability'] = $techAvailability;
        $response['day_of_week'] = $dayOfWeek;

        error_log("Generated " . count($allTimeSlots) . " available time slots");

        // Add unavailable time slots to booked
        foreach ($unavailableTimeSlots as $timeSlot) {
            if (!in_array($timeSlot, $response['booked'])) {
                $response['booked'][] = $timeSlot;
            }
        }

        $response['source'] = 'database';
        error_log("Retrieved " . count($response['booked']) . " booked times from database");

    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        $response['source'] = 'error';
        $response['db_error'] = $e->getMessage();
    }
} else {
    error_log("No date provided in any format");
    $response['error'] = "No date provided";
    $response['status'] = 'error';
    http_response_code(400); // Bad request
}

// Add timestamp for debugging cache issues
$response['timestamp'] = date('Y-m-d H:i:s');
$response['server_time'] = time();

// Return the response
echo json_encode($response);
?>