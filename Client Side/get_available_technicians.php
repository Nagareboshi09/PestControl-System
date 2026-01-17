<?php
require_once '../db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Get date and time from request
$date = isset($_POST['date']) ? $_POST['date'] : null;
$time = isset($_POST['time']) ? $_POST['time'] : null;

// Validate input
if (!$date || !$time) {
    echo json_encode([
        'success' => false,
        'message' => 'Date and time are required'
    ]);
    exit;
}

try {
    // Parse the selected time to determine the time range (assuming 2-hour appointments)
    $selectedTime = new DateTime($time);
    $endTime = clone $selectedTime;
    $endTime->add(new DateInterval('PT2H')); // Add 2 hours

    $selectedTimeStr = $selectedTime->format('H:i:s');
    $endTimeStr = $endTime->format('H:i:s');

    // Get the day of week (0 = Sunday, 1 = Monday, etc.)
    $dayOfWeek = intval(date('w', strtotime($date)));

    // Debug log
    error_log("Client Side - Date: $date, Day of Week: $dayOfWeek (" . date('l', strtotime($date)) . ")");

    // Convert selected time to proper format for comparison
    $timeComponents = explode(':', $time);
    $hour = intval($timeComponents[0]);
    $minute = isset($timeComponents[1]) ? intval($timeComponents[1]) : 0;

    // Format time for SQL comparison (HH:MM:SS)
    $selectedTimeStr = sprintf("%02d:%02d:00", $hour, $minute);

    // Calculate end time (1 hour after start time)
    $endHour = $hour + 1;
    $endTimeStr = sprintf("%02d:%02d:00", $endHour, $minute);

    // Debug information
    error_log("DEBUG: Date selected: $date, Day of week: $dayOfWeek, Time: $selectedTimeStr");

    // Check if there are any technicians with availability for this day
    $checkAvailQuery = "SELECT COUNT(*) as count FROM technician_availability
                        WHERE day_of_week = ? AND is_available = 1";
    $checkStmt = $conn->prepare($checkAvailQuery);
    $checkStmt->bind_param("i", $dayOfWeek);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $availCount = $checkResult->fetch_assoc()['count'];
    error_log("DEBUG: Number of technicians with availability for day $dayOfWeek: $availCount");

    // Check if there are any specific date entries for today
    $checkSpecificQuery = "SELECT COUNT(*) as count FROM technician_availability
                          WHERE specific_date = ? AND is_available = 1";
    $checkSpecificStmt = $conn->prepare($checkSpecificQuery);
    $checkSpecificStmt->bind_param("s", $date);
    $checkSpecificStmt->execute();
    $checkSpecificResult = $checkSpecificStmt->get_result();
    $specificCount = $checkSpecificResult->fetch_assoc()['count'];
    error_log("DEBUG: Number of technicians with specific date availability for $date: $specificCount");

    // Get detailed availability information for debugging
    $detailQuery = "SELECT ta.technician_id, t.tech_fname, t.tech_lname,
                   ta.day_of_week, ta.specific_date, ta.start_time, ta.end_time, ta.is_available
                   FROM technician_availability ta
                   JOIN technicians t ON ta.technician_id = t.technician_id
                   WHERE (ta.day_of_week = ? OR ta.specific_date = ?)
                   ORDER BY ta.technician_id, ta.specific_date DESC, ta.day_of_week";
    $detailStmt = $conn->prepare($detailQuery);
    $detailStmt->bind_param("is", $dayOfWeek, $date);
    $detailStmt->execute();
    $detailResult = $detailStmt->get_result();

    $availabilityDetails = [];
    while ($row = $detailResult->fetch_assoc()) {
        $availabilityDetails[] = $row;
        error_log("DEBUG: Technician ID: {$row['technician_id']}, Name: {$row['tech_fname']} {$row['tech_lname']}, " .
                 "Day: {$row['day_of_week']}, Date: {$row['specific_date']}, " .
                 "Time: {$row['start_time']} - {$row['end_time']}, Available: {$row['is_available']}");
    }

    // Query to find available technicians based on their availability schedule
    $query = "
    SELECT DISTINCT t.technician_id, t.tech_fname, t.tech_lname, t.username
    FROM technicians t
    WHERE 1=1
    AND EXISTS (
        -- Check if technician has availability for this time slot
        SELECT 1 FROM technician_availability ta
        WHERE ta.technician_id = t.technician_id
        AND ta.is_available = 1
        AND (
            -- Check weekly availability
            (ta.day_of_week = ? AND ta.specific_date IS NULL
             AND TIME(ta.start_time) <= TIME(?) AND TIME(ta.end_time) >= TIME(?))
            OR
            -- Check specific date availability
            (ta.specific_date = ? AND TIME(ta.start_time) <= TIME(?) AND TIME(ta.end_time) >= TIME(?))
        )
    )
    AND NOT EXISTS (
        -- Check if technician is already assigned to another appointment at this time
        SELECT 1 FROM appointments a
        JOIN appointment_technicians at ON a.appointment_id = at.appointment_id
        WHERE at.technician_id = t.technician_id
        AND a.preferred_date = ?
        AND a.status NOT IN ('cancelled', 'declined')
        AND (
            -- Check if there's an overlap with existing appointment
            (a.preferred_time <= ? AND DATE_ADD(a.preferred_time, INTERVAL 2 HOUR) > ?)
            OR
            (a.preferred_time < ? AND DATE_ADD(a.preferred_time, INTERVAL 2 HOUR) >= ?)
        )
    )
    AND NOT EXISTS (
        -- Check if technician is already assigned to a job order at this time
        SELECT 1 FROM job_order j
        JOIN job_order_technicians jt ON j.job_order_id = jt.job_order_id
        WHERE jt.technician_id = t.technician_id
        AND j.preferred_date = ?
        AND j.status NOT IN ('cancelled', 'declined')
        AND (
            -- Check if there's an overlap with existing job order
            (j.preferred_time <= ? AND DATE_ADD(j.preferred_time, INTERVAL 2 HOUR) > ?)
            OR
            (j.preferred_time < ? AND DATE_ADD(j.preferred_time, INTERVAL 2 HOUR) >= ?)
        )
    )
    ORDER BY t.tech_fname, t.tech_lname
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "isssssssssssssss",
        $dayOfWeek,
        $selectedTimeStr,
        $selectedTimeStr,
        $date,
        $selectedTimeStr,
        $selectedTimeStr,
        $date,
        $selectedTimeStr,
        $selectedTimeStr,
        $endTimeStr,
        $endTimeStr,
        $date,
        $selectedTimeStr,
        $selectedTimeStr,
        $endTimeStr,
        $endTimeStr
    );

    // Log the query with parameters for debugging
    error_log("SQL Query for technician availability with parameters: " .
              "Day of Week: $dayOfWeek, " .
              "Selected Time: $selectedTimeStr, " .
              "Date: $date");

    $stmt->execute();
    $result = $stmt->get_result();

    $technicians = [];
    while ($row = $result->fetch_assoc()) {
        $technicians[] = [
            'technician_id' => $row['technician_id'],
            'name' => $row['tech_fname'] . ' ' . $row['tech_lname'],
            'username' => $row['username']
        ];
    }

    // Return the available technicians with debug info
    echo json_encode([
        'success' => true,
        'date' => $date,
        'time' => $time,
        'day_of_week' => $dayOfWeek,
        'technicians' => $technicians,
        'count' => count($technicians),
        'debug' => [
            'weekly_availability_count' => $availCount,
            'specific_date_availability_count' => $specificCount,
            'selected_time' => $selectedTimeStr,
            'end_time' => $endTimeStr,
            'availability_details' => $availabilityDetails
        ]
    ]);

} catch (Exception $e) {
    // Log the error
    error_log('Error in get_available_technicians.php: ' . $e->getMessage());

    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving available technicians: ' . $e->getMessage()
    ]);
}
?>
