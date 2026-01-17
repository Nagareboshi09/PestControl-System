<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}
require_once '../db_connect.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: technicians.php");
    exit;
}

// Get form data
$availability_id = isset($_POST['availability_id']) ? intval($_POST['availability_id']) : 0;
$technician_id = isset($_POST['technician_id']) ? intval($_POST['technician_id']) : 0;
$availability_type = isset($_POST['availability_type']) ? $_POST['availability_type'] : '';
$is_available = isset($_POST['is_available']) ? intval($_POST['is_available']) : 1;
// Get and format time values
$start_time_raw = isset($_POST['start_time']) ? $_POST['start_time'] : '';
$end_time_raw = isset($_POST['end_time']) ? $_POST['end_time'] : '';

// Ensure time format is HH:MM:SS
if (!empty($start_time_raw)) {
    // If time doesn't have seconds, add them
    if (substr_count($start_time_raw, ':') === 1) {
        $start_time = $start_time_raw . ':00';
    } else {
        $start_time = $start_time_raw;
    }
} else {
    $start_time = '';
}

if (!empty($end_time_raw)) {
    // If time doesn't have seconds, add them
    if (substr_count($end_time_raw, ':') === 1) {
        $end_time = $end_time_raw . ':00';
    } else {
        $end_time = $end_time_raw;
    }
} else {
    $end_time = '';
}

// Debug logging
error_log("Saving technician availability: " . json_encode([
    'availability_id' => $availability_id,
    'technician_id' => $technician_id,
    'availability_type' => $availability_type,
    'is_available' => $is_available,
    'start_time' => $start_time,
    'end_time' => $end_time,
    'POST' => $_POST
]));

// Validate required fields
if ($technician_id <= 0 || empty($availability_type) || empty($start_time) || empty($end_time)) {
    $_SESSION['error'] = "All fields are required.";
    header("Location: technician_availability.php?id=$technician_id");
    exit;
}

// Validate time format
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end_time)) {
    $_SESSION['error'] = "Invalid time format.";
    header("Location: technician_availability.php?id=$technician_id");
    exit;
}

// Validate start time is before end time
if (strtotime($start_time) >= strtotime($end_time)) {
    $_SESSION['error'] = "Start time must be before end time.";
    header("Location: technician_availability.php?id=$technician_id");
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if the table has 'availability_id' or 'id' column
    $checkColumnQuery = "SHOW COLUMNS FROM technician_availability LIKE 'availability_id'";
    $columnResult = $conn->query($checkColumnQuery);
    $hasAvailabilityIdColumn = $columnResult->num_rows > 0;

    // Set the ID column name based on the table structure
    $idColumnName = $hasAvailabilityIdColumn ? 'availability_id' : 'id';

    if ($availability_type === 'weekly') {
        // Weekly availability
        $day_of_week = isset($_POST['day_of_week']) ? intval($_POST['day_of_week']) : -1;

        // Validate day of week
        if ($day_of_week < 0 || $day_of_week > 6) {
            throw new Exception("Invalid day of week: $day_of_week");
        }

        // Log the day of week for debugging
        error_log("Processing weekly availability for day: $day_of_week (" . date('l', strtotime("Sunday +{$day_of_week} days")) . ")");

        if ($availability_id > 0) {
            // Update existing weekly availability
            $stmt = $conn->prepare("
                UPDATE technician_availability
                SET day_of_week = ?, start_time = ?, end_time = ?, is_available = ?
                WHERE $idColumnName = ? AND technician_id = ?
            ");
            $stmt->bind_param("issiii", $day_of_week, $start_time, $end_time, $is_available, $availability_id, $technician_id);
        } else {
            // Check if availability already exists for this day
            $checkStmt = $conn->prepare("
                SELECT $idColumnName FROM technician_availability
                WHERE technician_id = ? AND day_of_week = ? AND specific_date IS NULL
            ");
            $checkStmt->bind_param("ii", $technician_id, $day_of_week);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                throw new Exception("Availability for this day already exists. Please edit the existing entry.");
            }

            // Insert new weekly availability
            $stmt = $conn->prepare("
                INSERT INTO technician_availability (technician_id, day_of_week, start_time, end_time, is_available)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iissi", $technician_id, $day_of_week, $start_time, $end_time, $is_available);
        }
    } else if ($availability_type === 'specific') {
        // Specific date availability
        $specific_date = isset($_POST['specific_date']) ? $_POST['specific_date'] : '';

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $specific_date)) {
            throw new Exception("Invalid date format.");
        }

        if ($availability_id > 0) {
            // Update existing specific date availability
            $stmt = $conn->prepare("
                UPDATE technician_availability
                SET specific_date = ?, start_time = ?, end_time = ?, is_available = ?
                WHERE $idColumnName = ? AND technician_id = ?
            ");
            $stmt->bind_param("sssiii", $specific_date, $start_time, $end_time, $is_available, $availability_id, $technician_id);
        } else {
            // Check if availability already exists for this date
            $checkStmt = $conn->prepare("
                SELECT $idColumnName FROM technician_availability
                WHERE technician_id = ? AND specific_date = ?
            ");
            $checkStmt->bind_param("is", $technician_id, $specific_date);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                throw new Exception("Availability for this date already exists. Please edit the existing entry.");
            }

            // Insert new specific date availability
            $stmt = $conn->prepare("
                INSERT INTO technician_availability (technician_id, specific_date, start_time, end_time, is_available)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssi", $technician_id, $specific_date, $start_time, $end_time, $is_available);
        }
    } else {
        throw new Exception("Invalid availability type.");
    }

    // Execute the statement
    if (!$stmt->execute()) {
        throw new Exception("Failed to save availability: " . $stmt->error);
    }

    // Commit transaction
    $conn->commit();

    // Set success message
    $_SESSION['success'] = "Availability saved successfully.";

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();

    // Set error message
    $_SESSION['error'] = $e->getMessage();
}

// Redirect back to technician availability page
header("Location: technician_availability.php?id=$technician_id");
exit;
?>
