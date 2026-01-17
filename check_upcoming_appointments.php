<?php
/**
 * Script to check for upcoming appointments and send notifications to technicians
 * This script is designed to be run via a cron job or scheduled task
 */

// Include necessary files
require_once 'db_connect.php';
require_once 'notification_functions.php';

// Set error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log function
function logMessage($message) {
    $logFile = 'logs/appointment_notifications.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    
    // Create logs directory if it doesn't exist
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }
    
    // Append to log file
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Also output to console if running from command line
    if (php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}

logMessage("Starting upcoming appointment check");

// Get current date and time
$currentDate = date('Y-m-d');
$currentTime = date('H:i:s');

// Calculate time thresholds for notifications
// 24 hours before appointment
$oneDayAhead = date('Y-m-d', strtotime('+1 day'));
// 1 hour before appointment
$oneHourAheadDate = $currentDate;
$oneHourAheadTime = date('H:i:s', strtotime('+1 hour'));

try {
    // Query to find appointments that are coming up
    // 1. Appointments tomorrow (24-hour notice)
    // 2. Appointments today that are 1 hour away (1-hour notice)
    $query = "
        SELECT 
            a.appointment_id,
            a.technician_id,
            a.client_name,
            a.preferred_date,
            a.preferred_time,
            a.location_address,
            a.kind_of_place,
            t.username AS technician_name,
            'appointment' AS type
        FROM 
            appointments a
        JOIN 
            technicians t ON a.technician_id = t.technician_id
        WHERE 
            (
                -- Tomorrow's appointments (for 24-hour notice)
                (a.preferred_date = ? AND NOT EXISTS (
                    SELECT 1 FROM notifications n 
                    WHERE n.related_id = a.appointment_id 
                    AND n.related_type = 'appointment_24h'
                    AND DATE(n.created_at) = CURRENT_DATE()
                ))
                OR
                -- Today's appointments 1 hour before (for 1-hour notice)
                (a.preferred_date = ? AND TIME(a.preferred_time) BETWEEN ? AND DATE_ADD(?, INTERVAL 15 MINUTE)
                AND NOT EXISTS (
                    SELECT 1 FROM notifications n 
                    WHERE n.related_id = a.appointment_id 
                    AND n.related_type = 'appointment_1h'
                    AND DATE(n.created_at) = CURRENT_DATE()
                ))
            )
            AND a.status != 'completed'
            AND a.technician_id IS NOT NULL
        
        UNION
        
        SELECT 
            jo.job_order_id AS appointment_id,
            jot.technician_id,
            a.client_name,
            jo.preferred_date,
            jo.preferred_time,
            a.location_address,
            a.kind_of_place,
            t.username AS technician_name,
            'job_order' AS type
        FROM 
            job_order jo
        JOIN 
            job_order_technicians jot ON jo.job_order_id = jot.job_order_id
        JOIN 
            assessment_report ar ON jo.report_id = ar.report_id
        JOIN 
            appointments a ON ar.appointment_id = a.appointment_id
        JOIN 
            technicians t ON jot.technician_id = t.technician_id
        WHERE 
            (
                -- Tomorrow's job orders (for 24-hour notice)
                (jo.preferred_date = ? AND NOT EXISTS (
                    SELECT 1 FROM notifications n 
                    WHERE n.related_id = jo.job_order_id 
                    AND n.related_type = 'job_order_24h'
                    AND DATE(n.created_at) = CURRENT_DATE()
                ))
                OR
                -- Today's job orders 1 hour before (for 1-hour notice)
                (jo.preferred_date = ? AND TIME(jo.preferred_time) BETWEEN ? AND DATE_ADD(?, INTERVAL 15 MINUTE)
                AND NOT EXISTS (
                    SELECT 1 FROM notifications n 
                    WHERE n.related_id = jo.job_order_id 
                    AND n.related_type = 'job_order_1h'
                    AND DATE(n.created_at) = CURRENT_DATE()
                ))
            )
            AND jo.status != 'completed'
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssssss", 
        $oneDayAhead,                // 24-hour appointment notice date
        $currentDate,                // 1-hour appointment notice date
        $oneHourAheadTime,           // 1-hour appointment notice time start
        $oneHourAheadTime,           // 1-hour appointment notice time end
        $oneDayAhead,                // 24-hour job order notice date
        $currentDate,                // 1-hour job order notice date
        $oneHourAheadTime,           // 1-hour job order notice time start
        $oneHourAheadTime            // 1-hour job order notice time end
    );
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notificationCount = 0;
    
    while ($row = $result->fetch_assoc()) {
        $technician_id = $row['technician_id'];
        $appointment_id = $row['appointment_id'];
        $client_name = $row['client_name'];
        $preferred_date = $row['preferred_date'];
        $preferred_time = date('h:i A', strtotime($row['preferred_time']));
        $location = $row['location_address'];
        $type = $row['type'];
        $technician_name = $row['technician_name'];
        
        // Determine if this is a 24-hour or 1-hour notification
        $is24HourNotice = ($preferred_date == $oneDayAhead);
        
        if ($is24HourNotice) {
            // 24-hour notification
            if ($type == 'appointment') {
                $title = "Upcoming Inspection Tomorrow";
                $message = "You have an inspection scheduled tomorrow at $preferred_time for $client_name at $location.";
                $related_type = "appointment_24h";
            } else {
                $title = "Upcoming Job Order Tomorrow";
                $message = "You have a job order scheduled tomorrow at $preferred_time for $client_name at $location.";
                $related_type = "job_order_24h";
            }
        } else {
            // 1-hour notification
            if ($type == 'appointment') {
                $title = "Inspection in 1 Hour";
                $message = "You have an inspection scheduled in about 1 hour at $preferred_time for $client_name at $location.";
                $related_type = "appointment_1h";
            } else {
                $title = "Job Order in 1 Hour";
                $message = "You have a job order scheduled in about 1 hour at $preferred_time for $client_name at $location.";
                $related_type = "job_order_1h";
            }
        }
        
        // Create notification
        $success = createNotification(
            $technician_id,
            'technician',
            $title,
            $message,
            $appointment_id,
            $related_type
        );
        
        if ($success) {
            $notificationCount++;
            $noticeType = $is24HourNotice ? "24-hour" : "1-hour";
            logMessage("Created $noticeType notification for technician $technician_name (ID: $technician_id) for " . 
                       ($type == 'appointment' ? "inspection" : "job order") . 
                       " #$appointment_id on $preferred_date at $preferred_time");
        } else {
            logMessage("Failed to create notification for technician $technician_id for appointment #$appointment_id");
        }
    }
    
    logMessage("Completed upcoming appointment check. Created $notificationCount notifications.");
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
}

// Close database connection
$conn->close();
?>
