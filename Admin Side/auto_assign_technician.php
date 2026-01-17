<?php
require_once '../db_connect.php';
require_once '../notification_functions.php';

header('Content-Type: application/json');

// Parse the JSON input data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if JSON was parsed successfully
if ($data === null) {
    // Log the raw input for debugging
    error_log("Failed to parse JSON input: " . $input);
    error_log("JSON error: " . json_last_error_msg());

    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON input: ' . json_last_error_msg()
    ]);
    exit;
}

// Check for required parameters
if ((!isset($data['appointment_id']) && !isset($data['job_order_id'])) ||
    (!isset($data['type']) || !in_array($data['type'], ['appointment', 'job_order']))) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters: appointment_id/job_order_id and type are required'
    ]);
    exit;
}

$type = $data['type'];
$id = $type === 'appointment' ? $data['appointment_id'] : $data['job_order_id'];
$response = ['success' => false];

try {
    // Log the request for debugging
    error_log("Auto-assign request: " . json_encode($data));

    // Get the date and time of the appointment/job order
    if ($type === 'appointment') {
        $stmt = $conn->prepare("SELECT preferred_date, preferred_time FROM appointments WHERE appointment_id = ?");
    } else {
        $stmt = $conn->prepare("SELECT preferred_date, preferred_time FROM job_order WHERE job_order_id = ?");
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => ($type === 'appointment' ? 'Appointment' : 'Job order') . ' not found'
        ]);
        exit;
    }

    $row = $result->fetch_assoc();
    $date = $row['preferred_date'];
    $time = $row['preferred_time'];
    $stmt->close();

    // Get the day of week (0 = Sunday, 1 = Monday, etc.)
    $dayOfWeek = intval(date('w', strtotime($date)));

    // Format time for SQL comparison (HH:MM:SS)
    $selectedTimeStr = date('H:i:s', strtotime($time));

    // Calculate end time (2 hours after start time for appointments)
    $endTime = date('H:i:s', strtotime($time . ' + 2 hours'));

    // Query to find available technicians based on their availability schedule
    // and current workload
    $query = "
    SELECT
        t.technician_id,
        t.username,
        COUNT(DISTINCT CASE WHEN at.appointment_id IS NOT NULL THEN at.appointment_id END) as appointment_count,
        COUNT(DISTINCT CASE WHEN jot.job_order_id IS NOT NULL THEN jot.job_order_id END) as job_order_count,
        (COUNT(DISTINCT CASE WHEN at.appointment_id IS NOT NULL THEN at.appointment_id END) +
         COUNT(DISTINCT CASE WHEN jot.job_order_id IS NOT NULL THEN jot.job_order_id END)) as total_workload
    FROM technicians t
    LEFT JOIN appointment_technicians at ON t.technician_id = at.technician_id
    LEFT JOIN job_order_technicians jot ON t.technician_id = jot.technician_id
    WHERE t.technician_id IN (
        -- Subquery to find technicians with availability for this time slot
        SELECT DISTINCT ta.technician_id
        FROM technician_availability ta
        WHERE ta.is_available = 1
        AND (
            -- Check weekly availability
            (ta.day_of_week = ? AND ta.specific_date IS NULL
             AND TIME(ta.start_time) <= TIME(?) AND TIME(ta.end_time) >= TIME(?))
            OR
            -- Check specific date availability
            (ta.specific_date = ? AND TIME(ta.start_time) <= TIME(?) AND TIME(ta.end_time) >= TIME(?))
        )
    )
    AND t.technician_id NOT IN (
        -- Exclude technicians already assigned to another appointment at this time
        SELECT at.technician_id
        FROM appointments a
        JOIN appointment_technicians at ON a.appointment_id = at.appointment_id
        WHERE a.preferred_date = ?
        AND a.status NOT IN ('cancelled', 'declined')
        AND (
            -- Check if there's an overlap with existing appointment
            (a.preferred_time <= ? AND DATE_ADD(a.preferred_time, INTERVAL 2 HOUR) > ?)
            OR
            (a.preferred_time < ? AND DATE_ADD(a.preferred_time, INTERVAL 2 HOUR) >= ?)
        )
        " . ($type === 'appointment' ? "AND a.appointment_id != ?" : "") . "
    )
    AND t.technician_id NOT IN (
        -- Exclude technicians already assigned to a job order at this time
        SELECT jt.technician_id
        FROM job_order j
        JOIN job_order_technicians jt ON j.job_order_id = jt.job_order_id
        WHERE j.preferred_date = ?
        AND j.status NOT IN ('cancelled', 'declined')
        AND (
            -- Check if there's an overlap with existing job order
            (j.preferred_time <= ? AND DATE_ADD(j.preferred_time, INTERVAL 2 HOUR) > ?)
            OR
            (j.preferred_time < ? AND DATE_ADD(j.preferred_time, INTERVAL 2 HOUR) >= ?)
        )
        " . ($type === 'job_order' ? "AND j.job_order_id != ?" : "") . "
    )
    GROUP BY t.technician_id
    ORDER BY total_workload ASC, appointment_count ASC, job_order_count ASC
    LIMIT 1
    ";

    // Prepare the statement with the appropriate number of parameters
    if ($type === 'appointment') {
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "issssssssssissssi",
            $dayOfWeek,
            $selectedTimeStr,
            $selectedTimeStr,
            $date,
            $selectedTimeStr,
            $selectedTimeStr,
            $date,
            $selectedTimeStr,
            $selectedTimeStr,
            $endTime,
            $endTime,
            $id, // Exclude the current appointment
            $date,
            $selectedTimeStr,
            $selectedTimeStr,
            $endTime,
            $endTime
        );
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "issssssssssissssi",
            $dayOfWeek,
            $selectedTimeStr,
            $selectedTimeStr,
            $date,
            $selectedTimeStr,
            $selectedTimeStr,
            $date,
            $selectedTimeStr,
            $selectedTimeStr,
            $endTime,
            $endTime,
            $date,
            $selectedTimeStr,
            $selectedTimeStr,
            $endTime,
            $endTime,
            $id // Exclude the current job order
        );
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No available technicians found for this time slot'
        ]);
        exit;
    }

    $technician = $result->fetch_assoc();
    $technicianId = $technician['technician_id'];
    $technicianName = $technician['username'];
    $stmt->close();

    // Assign the technician
    if ($type === 'appointment') {
        // Use the existing assign_technician.php logic
        $assignData = [
            'appointment_id' => $id,
            'technician_id' => $technicianId,
            'is_primary' => true
        ];

        // Use direct database operations instead of including the file

        // Check if this technician is already assigned to this appointment
        $checkStmt = $conn->prepare("SELECT * FROM appointment_technicians WHERE appointment_id = ? AND technician_id = ?");
        $checkStmt->bind_param("ii", $id, $technicianId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            // Technician is already assigned, just update the primary status if needed
            if ($assignData['is_primary']) {
                $updateStmt = $conn->prepare("UPDATE appointment_technicians SET is_primary = 1 WHERE appointment_id = ? AND technician_id = ?");
                $updateStmt->bind_param("ii", $id, $technicianId);
                $updateStmt->execute();
            }

            $assignResponse = [
                'success' => true,
                'appointment_id' => $id,
                'technician_id' => $technicianId,
                'technician_name' => $technicianName,
                'is_primary' => $assignData['is_primary'],
                'message' => 'Technician is already assigned to this appointment'
            ];
            $assignResult = json_encode($assignResponse);
        } else {
            // If this is the primary technician, unset any existing primary technicians
            if ($assignData['is_primary']) {
                $unsetPrimaryStmt = $conn->prepare("UPDATE appointment_technicians SET is_primary = 0 WHERE appointment_id = ?");
                $unsetPrimaryStmt->bind_param("i", $id);
                $unsetPrimaryStmt->execute();
            }

            // Insert the technician assignment into the appointment_technicians table
            $insertStmt = $conn->prepare("INSERT INTO appointment_technicians (appointment_id, technician_id, is_primary) VALUES (?, ?, ?)");
            $insertStmt->bind_param("iii", $id, $technicianId, $assignData['is_primary']);

            if ($insertStmt->execute()) {
                // Update the appointment status to assigned
                $updateStmt = $conn->prepare("UPDATE appointments SET status = 'assigned' WHERE appointment_id = ?");
                $updateStmt->bind_param("i", $id);
                $updateStmt->execute();

                // For backward compatibility, also update the technician_id in the appointments table if this is the primary technician
                if ($assignData['is_primary']) {
                    $updateTechStmt = $conn->prepare("UPDATE appointments SET technician_id = ? WHERE appointment_id = ?");
                    $updateTechStmt->bind_param("ii", $technicianId, $id);
                    $updateTechStmt->execute();
                }

                $assignResponse = [
                    'success' => true,
                    'appointment_id' => $id,
                    'technician_id' => $technicianId,
                    'technician_name' => $technicianName,
                    'is_primary' => $assignData['is_primary']
                ];
                $assignResult = json_encode($assignResponse);
            } else {
                $assignResponse = [
                    'success' => false,
                    'message' => $conn->error
                ];
                $assignResult = json_encode($assignResponse);
            }
        }

        // Check if the assignment was successful
        if (!$assignResponse['success']) {
            throw new Exception('Failed to assign technician: ' . ($assignResponse['message'] ?? 'Unknown error'));
        }

        $response = $assignResponse;
        $response['auto_assigned'] = true;
    } else {
        // Use the existing assign_job_technician.php logic
        $assignData = [
            'job_order_id' => $id,
            'technician_id' => $technicianId,
            'is_primary' => true
        ];

        // Use direct database operations instead of including the file

        // Check if technician is already assigned to this job order
        $checkStmt = $conn->prepare("SELECT * FROM job_order_technicians WHERE job_order_id = ? AND technician_id = ?");
        $checkStmt->bind_param("ii", $id, $technicianId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            // Technician is already assigned, just update the primary status if needed
            if ($assignData['is_primary']) {
                $updateStmt = $conn->prepare("UPDATE job_order_technicians SET is_primary = 1 WHERE job_order_id = ? AND technician_id = ?");
                $updateStmt->bind_param("ii", $id, $technicianId);
                $updateStmt->execute();
            }

            $assignResponse = [
                'success' => true,
                'job_order_id' => $id,
                'technician_id' => $technicianId,
                'technician_name' => $technicianName,
                'is_primary' => $assignData['is_primary'],
                'message' => 'Technician is already assigned to this job order'
            ];
            $assignResult = json_encode($assignResponse);
        } else {
            // If this is the primary technician, unset any existing primary technicians
            if ($assignData['is_primary']) {
                $unsetPrimaryStmt = $conn->prepare("UPDATE job_order_technicians SET is_primary = 0 WHERE job_order_id = ?");
                $unsetPrimaryStmt->bind_param("i", $id);
                $unsetPrimaryStmt->execute();
            }

            // Insert the technician assignment
            $insertStmt = $conn->prepare("INSERT INTO job_order_technicians (job_order_id, technician_id, is_primary) VALUES (?, ?, ?)");
            $insertStmt->bind_param("iii", $id, $technicianId, $assignData['is_primary']);

            if ($insertStmt->execute()) {
                $assignResponse = [
                    'success' => true,
                    'job_order_id' => $id,
                    'technician_id' => $technicianId,
                    'technician_name' => $technicianName,
                    'is_primary' => $assignData['is_primary']
                ];
                $assignResult = json_encode($assignResponse);
            } else {
                $assignResponse = [
                    'success' => false,
                    'message' => $conn->error
                ];
                $assignResult = json_encode($assignResponse);
            }
        }

        // Check if the assignment was successful
        if (!$assignResponse['success']) {
            throw new Exception('Failed to assign technician: ' . ($assignResponse['message'] ?? 'Unknown error'));
        }

        $response = $assignResponse;
        $response['auto_assigned'] = true;
    }

} catch (Exception $e) {
    // Log the detailed error
    error_log("Auto-assign error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());

    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ];
}

// Ensure we're sending a valid JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
