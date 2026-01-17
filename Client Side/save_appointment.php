<?php
// Set timezone to ensure correct date calculations
date_default_timezone_set('Asia/Manila'); // Philippines timezone

session_start();
include '../db_connect.php';
include '../notification_functions.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON for all responses
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null, $status = 200) {
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    sendJsonResponse(false, 'Invalid request method', null, 405);
}

// Log the POST data for debugging
error_log('POST data received: ' . print_r($_POST, true));

// Check for required fields
$required = ['client_id', 'preferred_date', 'preferred_time', 'location_address', 'kind_of_place'];
$missing_fields = [];

foreach ($required as $field) {
    if (empty($_POST[$field])) {
        $missing_fields[] = $field;
        error_log("Missing required field: $field");
    }
}

if (!empty($missing_fields)) {
    error_log('Missing required fields: ' . implode(', ', $missing_fields));
    sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), null, 400);
}

try {
    // Validate that the preferred date is today or in the future (not past)
    $selectedDate = new DateTime($_POST['preferred_date']);
    // Get today's date with explicit timezone to ensure accuracy
    $today = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $today->setTime(0, 0, 0); // Reset time part to compare dates only

    // Get yesterday's date for comparison
    $yesterday = clone $today;
    $yesterday->modify('-1 day');

    // Uncomment for debugging if needed
    error_log('Selected date: ' . $selectedDate->format('Y-m-d'));
    error_log('Today\'s date: ' . $today->format('Y-m-d'));
    error_log('Yesterday\'s date: ' . $yesterday->format('Y-m-d'));
    error_log('Timezone: ' . date_default_timezone_get());

    if ($selectedDate <= $yesterday) {
        error_log('Invalid date: ' . $_POST['preferred_date'] . ' - must be today or a future date');
        sendJsonResponse(false, "You can only schedule appointments for today or future dates", null, 400);
    }

    // Check if the selected date is a Sunday (0 = Sunday, 1 = Monday, etc.)
    if ($selectedDate->format('w') == 0) {
        error_log('Invalid date: ' . $_POST['preferred_date'] . ' - appointments not available on Sundays');
        sendJsonResponse(false, "We are closed on Sundays. Please select a different day for your appointment.", null, 400);
    }

    // If scheduling for today, check if the selected time is in the past
    if ($selectedDate->format('Y-m-d') === $today->format('Y-m-d')) {
        $currentTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $selectedTime = new DateTime($_POST['preferred_date'] . ' ' . $_POST['preferred_time'], new DateTimeZone('Asia/Manila'));

        // Add a 30-minute buffer to allow for processing
        $currentTimePlusBuffer = clone $currentTime;
        $currentTimePlusBuffer->modify('+30 minutes');

        error_log('Current time: ' . $currentTime->format('H:i:s'));
        error_log('Current time + 30 min: ' . $currentTimePlusBuffer->format('H:i:s'));
        error_log('Selected time: ' . $selectedTime->format('H:i:s'));

        if ($selectedTime <= $currentTimePlusBuffer) {
            error_log('Invalid time: ' . $_POST['preferred_time'] . ' - must be at least 30 minutes from now');
            sendJsonResponse(false, "For same-day appointments, please select a time at least 30 minutes from now", null, 400);
        }
    }

    // Use location address directly
    $location_address = $_POST['location_address'];

    // Process pest problems if provided
    $pest_problems = null;
    if (isset($_POST['pest_problems']) && is_array($_POST['pest_problems'])) {
        $pest_problems = implode(', ', $_POST['pest_problems']);
    }

    // Check if the time slot is still available
    // Exclude both cancelled and declined appointments
    $checkStmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE preferred_date = ? AND preferred_time = ? AND status NOT IN ('cancelled', 'declined')");
    $checkStmt->bind_param("ss", $_POST['preferred_date'], $_POST['preferred_time']);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        sendJsonResponse(false, "This time slot is no longer available. Please select another time.", null, 409);
    }

    $stmt = $conn->prepare("INSERT INTO appointments (
        client_id, client_name, email, contact_number, preferred_date, preferred_time,
        kind_of_place, location_address, notes, pest_problems, created_at, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')");

    $stmt->bind_param("isssssssss",
        $_POST['client_id'],
        $_POST['client_name'],
        $_POST['email'],
        $_POST['contact_number'],
        $_POST['preferred_date'],
        $_POST['preferred_time'],
        $_POST['kind_of_place'],
        $location_address,
        $_POST['notes'],
        $pest_problems
    );

    // Log the SQL query parameters for debugging
    error_log('Executing SQL with parameters: ' .
              'client_id=' . $_POST['client_id'] . ', ' .
              'client_name=' . $_POST['client_name'] . ', ' .
              'email=' . $_POST['email'] . ', ' .
              'contact_number=' . $_POST['contact_number'] . ', ' .
              'preferred_date=' . $_POST['preferred_date'] . ', ' .
              'preferred_time=' . $_POST['preferred_time'] . ', ' .
              'kind_of_place=' . $_POST['kind_of_place'] . ', ' .
              'location_address=' . $location_address . ', ' .
              'notes=' . $_POST['notes'] . ', ' .
              'pest_problems=' . $pest_problems);

    if ($stmt->execute()) {
        $appointment_id = $conn->insert_id;
        $client_id = $_POST['client_id'];
        $client_name = $_POST['client_name'];

        error_log('Appointment inserted successfully with ID: ' . $appointment_id);

        // Store the selected technician ID (if any) but don't assign yet
        $technician_id = isset($_POST['technician_id']) && !empty($_POST['technician_id']) ? $_POST['technician_id'] : null;
        $technician_assigned = false;

        // If a technician was selected by the client, store it in the appointment record
        // but don't change the status from 'pending' and don't actually assign the technician yet
        if ($technician_id) {
            error_log('Storing selected technician ID: ' . $technician_id . ' for appointment ID: ' . $appointment_id);

            // Just update the technician_id field but keep status as 'pending'
            $updateStmt = $conn->prepare("UPDATE appointments SET technician_id = ? WHERE appointment_id = ?");
            $updateStmt->bind_param("ii", $technician_id, $appointment_id);
            $updateStmt->execute();

            error_log('Technician ID stored successfully. Appointment remains in pending status.');
        } else {
            error_log('No technician selected. Appointment will remain in pending status until admin approval.');
        }

        // Create notification for admin about new appointment
        // Get all admin IDs (office staff)
        $adminQuery = $conn->query("SELECT staff_id FROM office_staff");
        while ($admin = $adminQuery->fetch_assoc()) {
            error_log('Notifying admin ID: ' . $admin['staff_id'] . ' about new appointment');
            notifyAdminAboutNewAppointment(
                $admin['staff_id'],
                $appointment_id,
                $client_name
            );
        }

        sendJsonResponse(true, "Appointment scheduled successfully", [
            'appointment_id' => $appointment_id,
            'preferred_date' => $_POST['preferred_date'],
            'preferred_time' => $_POST['preferred_time'],
            'technician_assigned' => false, // Always false since technicians are only assigned after admin approval
            'technician_id' => $technician_id,
            'status' => 'pending' // Always pending until admin approval
        ]);
    } else {
        error_log('Database error during appointment insertion: ' . $conn->error);
        error_log('SQL statement error: ' . $stmt->error);
        sendJsonResponse(false, "Database error: " . $conn->error, null, 500);
    }
} catch (Exception $e) {
    error_log('Exception caught in save_appointment.php: ' . $e->getMessage());
    error_log('Exception trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, "Server error: " . $e->getMessage(), null, 500);
}
?>