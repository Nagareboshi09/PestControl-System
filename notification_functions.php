<?php
/**
 * Notification Functions
 *
 * This file contains functions for creating, retrieving, and managing notifications
 * across all sides of the application (Client, Technician, Admin)
 */

/**
 * Create a new notification
 *
 * @param int $user_id - The ID of the user receiving the notification
 * @param string $user_type - The type of user (client, technician, admin)
 * @param string $title - The notification title
 * @param string $message - The notification message
 * @param int|null $related_id - Optional ID of related item (appointment, report, etc.)
 * @param string|null $related_type - Optional type of related item
 * @param object|null $db_connection - Optional database connection (mysqli or PDO)
 * @return bool - True if notification was created successfully
 */
function createNotification($user_id, $user_type, $title, $message, $related_id = null, $related_type = null, $db_connection = null) {
    // Use provided connection or fall back to global
    if ($db_connection === null) {
        global $conn;
        $db_connection = $conn;
    }

    // If still null, return false
    if ($db_connection === null) {
        error_log("No database connection available for creating notification");
        return false;
    }

    // Ensure user_id is an integer
    $user_id = intval($user_id);

    // Ensure user_type is one of the valid types
    if (!in_array($user_type, ['client', 'technician', 'admin'])) {
        error_log("Invalid user_type: $user_type. Must be 'client', 'technician', or 'admin'");
        return false;
    }

    error_log("Creating notification: user_id=$user_id, user_type=$user_type, title=$title, related_id=$related_id, related_type=$related_type");

    // Debug database connection
    if ($db_connection instanceof PDO) {
        error_log("Using PDO connection");
    } else {
        error_log("Using mysqli connection: " . ($db_connection->ping() ? "Connected" : "Not connected"));
    }

    // Check if we're using PDO or mysqli
    if ($db_connection instanceof PDO) {
        try {
            $stmt = $db_connection->prepare("INSERT INTO notifications
                (user_id, user_type, title, message, related_id, related_type)
                VALUES (:user_id, :user_type, :title, :message, :related_id, :related_type)");

            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_type', $user_type, PDO::PARAM_STR);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':message', $message, PDO::PARAM_STR);
            $stmt->bindParam(':related_id', $related_id, PDO::PARAM_INT);
            $stmt->bindParam(':related_type', $related_type, PDO::PARAM_STR);

            $result = $stmt->execute();

            if ($result) {
                error_log("Notification created successfully with ID: " . $db_connection->lastInsertId());
            } else {
                error_log("Failed to create notification with PDO");
            }

            return $result;
        } catch (PDOException $e) {
            error_log("PDO error creating notification: " . $e->getMessage());
            return false;
        }
    } else {
        // Assume mysqli
        error_log("Using mysqli for notification creation");

        // Check if the notifications table exists
        $table_check = $db_connection->query("SHOW TABLES LIKE 'notifications'");
        if (!$table_check || $table_check->num_rows === 0) {
            error_log("Notifications table does not exist!");
            return false;
        }

        $stmt = $db_connection->prepare("INSERT INTO notifications
            (user_id, user_type, title, message, related_id, related_type)
            VALUES (?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            error_log("Prepare statement failed: " . $db_connection->error);
            return false;
        }

        // Convert related_id to integer if it's not null
        $related_id_int = $related_id !== null ? intval($related_id) : null;

        error_log("Binding parameters: user_id=$user_id, user_type=$user_type, title=$title, message=$message, related_id_int=$related_id_int, related_type=$related_type");

        // Check if related_id_int is null and handle it properly
        if ($related_id_int === null) {
            error_log("related_id is null, using special binding");
            $stmt->bind_param("isssis", $user_id, $user_type, $title, $message, $related_id_int, $related_type);
        } else {
            error_log("related_id is not null, using standard binding");
            $stmt->bind_param("isssss", $user_id, $user_type, $title, $message, $related_id_int, $related_type);
        }

        $result = $stmt->execute();

        if (!$result) {
            error_log("Execute statement failed: " . $stmt->error);
        } else {
            error_log("Notification created successfully with ID: " . $db_connection->insert_id);
        }

        return $result;
    }
}

/**
 * Get unread notifications count for a user
 *
 * @param int $user_id - The ID of the user
 * @param string $user_type - The type of user (client, technician, admin)
 * @param object|null $db_connection - Optional database connection
 * @return int - Number of unread notifications
 */
function getUnreadNotificationsCount($user_id, $user_type, $db_connection = null) {
    // Use provided connection or fall back to global
    if ($db_connection === null) {
        global $conn;
        $db_connection = $conn;
    }

    // If still null, return 0
    if ($db_connection === null) {
        error_log("No database connection available for getting unread notifications count");
        return 0;
    }

    // Check if we're using PDO or mysqli
    if ($db_connection instanceof PDO) {
        try {
            $stmt = $db_connection->prepare("SELECT COUNT(*) as count FROM notifications
                WHERE user_id = :user_id AND user_type = :user_type AND is_read = 0");

            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_type', $user_type, PDO::PARAM_STR);

            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("PDO error getting unread notifications count: " . $e->getMessage());
            return 0;
        }
    } else {
        // Assume mysqli
        $stmt = $db_connection->prepare("SELECT COUNT(*) as count FROM notifications
            WHERE user_id = ? AND user_type = ? AND is_read = 0");

        $stmt->bind_param("is", $user_id, $user_type);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return (int)$row['count'];
    }
}

/**
 * Get notifications for a user
 *
 * @param int $user_id - The ID of the user
 * @param string $user_type - The type of user (client, technician, admin)
 * @param int $limit - Maximum number of notifications to return
 * @param bool $unread_only - Whether to return only unread notifications
 * @param object|null $db_connection - Optional database connection
 * @return array - Array of notification objects
 */
function getNotifications($user_id, $user_type, $limit = 10, $unread_only = false, $db_connection = null) {
    // Use provided connection or fall back to global
    if ($db_connection === null) {
        global $conn;
        $db_connection = $conn;
    }

    // If still null, return empty array
    if ($db_connection === null) {
        error_log("No database connection available for getting notifications");
        return [];
    }

    // Check if we're using PDO or mysqli
    if ($db_connection instanceof PDO) {
        try {
            $query = "SELECT * FROM notifications
                WHERE user_id = :user_id AND user_type = :user_type";

            if ($unread_only) {
                $query .= " AND is_read = 0";
            }

            $query .= " ORDER BY created_at DESC LIMIT :limit";

            $stmt = $db_connection->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_type', $user_type, PDO::PARAM_STR);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("PDO error getting notifications: " . $e->getMessage());
            return [];
        }
    } else {
        // Assume mysqli
        $query = "SELECT * FROM notifications
            WHERE user_id = ? AND user_type = ?";

        if ($unread_only) {
            $query .= " AND is_read = 0";
        }

        $query .= " ORDER BY created_at DESC LIMIT ?";

        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("isi", $user_id, $user_type, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }

        return $notifications;
    }
}

/**
 * Mark a notification as read
 *
 * @param int $notification_id - The ID of the notification
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was marked as read successfully
 */
function markNotificationAsRead($notification_id, $db_connection = null) {
    // Use provided connection or fall back to global
    if ($db_connection === null) {
        global $conn;
        $db_connection = $conn;
    }

    // If still null, return false
    if ($db_connection === null) {
        error_log("No database connection available for marking notification as read");
        return false;
    }

    // Check if we're using PDO or mysqli
    if ($db_connection instanceof PDO) {
        try {
            $stmt = $db_connection->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = :notification_id");
            $stmt->bindParam(':notification_id', $notification_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("PDO error marking notification as read: " . $e->getMessage());
            return false;
        }
    } else {
        // Assume mysqli
        $stmt = $db_connection->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
        $stmt->bind_param("i", $notification_id);
        return $stmt->execute();
    }
}

/**
 * Mark all notifications as read for a user
 *
 * @param int $user_id - The ID of the user
 * @param string $user_type - The type of user (client, technician, admin)
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notifications were marked as read successfully
 */
function markAllNotificationsAsRead($user_id, $user_type, $db_connection = null) {
    // Use provided connection or fall back to global
    if ($db_connection === null) {
        global $conn;
        $db_connection = $conn;
    }

    // If still null, return false
    if ($db_connection === null) {
        error_log("No database connection available for marking all notifications as read");
        return false;
    }

    // Log the function call for debugging
    error_log("markAllNotificationsAsRead called with user_id: $user_id, user_type: $user_type");

    // Check if we're using PDO or mysqli
    if ($db_connection instanceof PDO) {
        try {
            // First, check if there are any unread notifications for this user
            $check_stmt = $db_connection->prepare("SELECT COUNT(*) as count FROM notifications
                WHERE user_id = :user_id AND user_type = :user_type AND is_read = 0");
            $check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $check_stmt->bindParam(':user_type', $user_type, PDO::PARAM_STR);
            $check_stmt->execute();
            $unread_count = (int)$check_stmt->fetchColumn();

            error_log("Found $unread_count unread notifications for user_id: $user_id, user_type: $user_type");

            // If there are unread notifications, mark them as read
            if ($unread_count > 0) {
                $stmt = $db_connection->prepare("UPDATE notifications SET is_read = 1
                    WHERE user_id = :user_id AND user_type = :user_type AND is_read = 0");
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':user_type', $user_type, PDO::PARAM_STR);
                $result = $stmt->execute();

                error_log("Marked all notifications as read for user_id: $user_id, user_type: $user_type, result: " . ($result ? 'true' : 'false'));

                return $result;
            }
        } catch (PDOException $e) {
            error_log("PDO error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    } else {
        // Assume mysqli
        // First, check if there are any unread notifications for this user
        $check_stmt = $db_connection->prepare("SELECT COUNT(*) as count FROM notifications
            WHERE user_id = ? AND user_type = ? AND is_read = 0");

        $check_stmt->bind_param("is", $user_id, $user_type);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();
        $unread_count = $row['count'];

        error_log("Found $unread_count unread notifications for user_id: $user_id, user_type: $user_type");

        // If there are unread notifications, mark them as read
        if ($unread_count > 0) {
            $stmt = $db_connection->prepare("UPDATE notifications SET is_read = 1
                WHERE user_id = ? AND user_type = ? AND is_read = 0");

            $stmt->bind_param("is", $user_id, $user_type);
            $result = $stmt->execute();

            error_log("Marked all notifications as read for user_id: $user_id, user_type: $user_type, result: " . ($result ? 'true' : 'false'));

            return $result;
        }
    }

    // If there are no unread notifications, return true (success)
    return true;
}

/**
 * Create notification for client about new report
 *
 * @param int $client_id - The client ID
 * @param int $appointment_id - The appointment ID (used for reference)
 * @param int $report_id - The report ID
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyClientAboutReport($client_id, $appointment_id, $report_id, $db_connection = null) {
    $title = "New Inspection Report Available";
    $message = "A new inspection report has been created for your appointment. Please check your inspection reports.";

    // Use appointment_id in the message to avoid unused parameter warning
    error_log("Creating notification for client about report. Appointment ID: $appointment_id");

    return createNotification(
        $client_id,
        'client',
        $title,
        $message,
        $report_id,
        'report',
        $db_connection
    );
}

/**
 * Create notification for client about appointment status
 *
 * @param int $client_id - The client ID
 * @param int $appointment_id - The appointment ID
 * @param string $status - The new status
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyClientAboutAppointment($client_id, $appointment_id, $status, $db_connection = null) {
    $title = "Appointment Update";
    $message = "Your appointment status has been updated to: " . ucfirst($status);

    return createNotification(
        $client_id,
        'client',
        $title,
        $message,
        $appointment_id,
        'appointment',
        $db_connection
    );
}

/**
 * Create notification for technician about new assignment
 *
 * @param int $technician_id - The technician ID
 * @param int $appointment_id - The appointment ID
 * @param string $client_name - The client name
 * @param string $date - The appointment date
 * @param string $time - The appointment time
 * @param string $location - The appointment location
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyTechnicianAboutAssignment($technician_id, $appointment_id, $client_name, $date, $time, $location = '', $db_connection = null) {
    // Format the time in 12-hour format (g:i A)
    $formattedTime = date('g:i A', strtotime($time));

    $title = "New Appointment Assigned";
    $message = "You have been assigned to a new appointment for $client_name on $date at $formattedTime";

    // Add location if provided
    if (!empty($location)) {
        $message .= " at $location";
    }
    $message .= ".";

    error_log("Notifying technician ID: $technician_id about new assignment (appointment ID: $appointment_id, client: $client_name, date: $date, time: $formattedTime, location: $location)");

    $result = createNotification(
        $technician_id,
        'technician',
        $title,
        $message,
        $appointment_id,
        'appointment',
        $db_connection
    );

    error_log("Technician notification creation result: " . ($result ? 'success' : 'failed'));

    return $result;
}

/**
 * Create notification for admin about new appointment
 *
 * @param int $admin_id - The admin ID
 * @param int $appointment_id - The appointment ID
 * @param string $client_name - The client name
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyAdminAboutNewAppointment($admin_id, $appointment_id, $client_name, $db_connection = null) {
    $title = "New Appointment Request";
    $message = "A new appointment has been requested by $client_name.";

    error_log("Notifying admin ID: $admin_id about new appointment ID: $appointment_id for client: $client_name");

    $result = createNotification(
        $admin_id,
        'admin',
        $title,
        $message,
        $appointment_id,
        'appointment',
        $db_connection
    );

    error_log("Notification creation result: " . ($result ? 'success' : 'failed'));

    return $result;
}

/**
 * Create notification for admin about new report
 *
 * @param int $admin_id - The admin ID
 * @param int $report_id - The report ID
 * @param string $technician_name - The technician name
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyAdminAboutNewReport($admin_id, $report_id, $technician_name, $db_connection = null) {
    $title = "New Inspection Report";
    $message = "A new inspection report has been submitted by $technician_name.";

    // Log the function call for debugging
    error_log("notifyAdminAboutNewReport called with admin_id=$admin_id, report_id=$report_id, technician_name=$technician_name");

    // Use provided connection or fall back to global
    if ($db_connection === null) {
        global $conn;
        $db_connection = $conn;
        error_log("Using global database connection");
    }

    // If still null, log error and return false
    if ($db_connection === null) {
        error_log("No database connection available for notifyAdminAboutNewReport");
        return false;
    }

    $result = createNotification(
        $admin_id,
        'admin',
        $title,
        $message,
        $report_id,
        'report',
        $db_connection
    );

    error_log("notifyAdminAboutNewReport result: " . ($result ? "success" : "failed"));

    return $result;
}

/**
 * Create notification for technician about upcoming appointment (24 hours notice)
 *
 * @param int $technician_id - The technician ID
 * @param int $appointment_id - The appointment ID
 * @param string $client_name - The client name
 * @param string $time - The appointment time
 * @param string $location - The appointment location
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyTechnicianAboutUpcomingAppointment24h($technician_id, $appointment_id, $client_name, $time, $location, $db_connection = null) {
    // Format the time in 12-hour format (g:i A)
    $formattedTime = date('g:i A', strtotime($time));

    $title = "Upcoming Inspection Tomorrow";
    $message = "You have an inspection scheduled tomorrow at $formattedTime for $client_name at $location.";

    return createNotification(
        $technician_id,
        'technician',
        $title,
        $message,
        $appointment_id,
        'appointment_24h',
        $db_connection
    );
}

/**
 * Create notification for technician about upcoming appointment (1 hour notice)
 *
 * @param int $technician_id - The technician ID
 * @param int $appointment_id - The appointment ID
 * @param string $client_name - The client name
 * @param string $time - The appointment time
 * @param string $location - The appointment location
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyTechnicianAboutUpcomingAppointment1h($technician_id, $appointment_id, $client_name, $time, $location, $db_connection = null) {
    // Format the time in 12-hour format (g:i A)
    $formattedTime = date('g:i A', strtotime($time));

    $title = "Inspection in 1 Hour";
    $message = "You have an inspection scheduled in about 1 hour at $formattedTime for $client_name at $location.";

    return createNotification(
        $technician_id,
        'technician',
        $title,
        $message,
        $appointment_id,
        'appointment_1h',
        $db_connection
    );
}

/**
 * Create notification for technician about upcoming job order (24 hours notice)
 *
 * @param int $technician_id - The technician ID
 * @param int $job_order_id - The job order ID
 * @param string $client_name - The client name
 * @param string $time - The job order time
 * @param string $location - The job order location
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyTechnicianAboutUpcomingJobOrder24h($technician_id, $job_order_id, $client_name, $time, $location, $db_connection = null) {
    // Format the time in 12-hour format (g:i A)
    $formattedTime = date('g:i A', strtotime($time));

    $title = "Upcoming Job Order Tomorrow";
    $message = "You have a job order scheduled tomorrow at $formattedTime for $client_name at $location.";

    return createNotification(
        $technician_id,
        'technician',
        $title,
        $message,
        $job_order_id,
        'job_order_24h',
        $db_connection
    );
}

/**
 * Create notification for technician about upcoming job order (1 hour notice)
 *
 * @param int $technician_id - The technician ID
 * @param int $job_order_id - The job order ID
 * @param string $client_name - The client name
 * @param string $time - The job order time
 * @param string $location - The job order location
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyTechnicianAboutUpcomingJobOrder1h($technician_id, $job_order_id, $client_name, $time, $location, $db_connection = null) {
    // Format the time in 12-hour format (g:i A)
    $formattedTime = date('g:i A', strtotime($time));

    $title = "Job Order in 1 Hour";
    $message = "You have a job order scheduled in about 1 hour at $formattedTime for $client_name at $location.";

    return createNotification(
        $technician_id,
        'technician',
        $title,
        $message,
        $job_order_id,
        'job_order_1h',
        $db_connection
    );
}

/**
 * Create notification for client about new quotation
 *
 * @param int $client_id - The client ID
 * @param int $job_order_id - The job order ID
 * @param int $report_id - The assessment report ID
 * @param string $type_of_work - The type of work
 * @param string $frequency - The frequency of the job order
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyClientAboutQuotation($client_id, $job_order_id, $report_id, $type_of_work, $frequency, $db_connection = null) {
    $title = "New Quotation Available";
    $message = "A new quotation has been sent for your assessment. Type of work: $type_of_work, Frequency: " . ucfirst($frequency) . ". Please check your contracts.";

    error_log("Notifying client ID: $client_id about new quotation (job order ID: $job_order_id, report ID: $report_id)");

    $result = createNotification(
        $client_id,
        'client',
        $title,
        $message,
        $job_order_id,
        'quotation',
        $db_connection
    );

    error_log("Notification creation result: " . ($result ? 'success' : 'failed'));

    return $result;
}

/**
 * Create notification for admin about quotation approval
 *
 * @param int $admin_id - The admin ID
 * @param int $job_order_id - The job order ID
 * @param int $client_id - The client ID
 * @param string $client_name - The client name
 * @param string $type_of_work - The type of work
 * @param string $frequency - The frequency of the job order
 * @param string $approval_type - The type of approval ('approved', 'one-time', 'declined')
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyAdminAboutQuotationResponse($admin_id, $job_order_id, $client_id, $client_name, $type_of_work, $frequency, $approval_type, $db_connection = null) {
    $title = "";
    $message = "";

    switch ($approval_type) {
        case 'approved':
            $title = "Quotation Approved";
            $message = "Client $client_name has approved the $type_of_work quotation with $frequency frequency. Job orders have been automatically scheduled.";
            break;
        case 'one-time':
            $title = "One-Time Treatment Selected";
            $message = "Client $client_name has selected a one-time treatment for the $type_of_work quotation instead of the $frequency plan.";
            break;
        case 'declined':
            $title = "Quotation Declined";
            $message = "Client $client_name has declined the $type_of_work quotation with $frequency frequency.";
            break;
        default:
            $title = "Quotation Response";
            $message = "Client $client_name has responded to the $type_of_work quotation.";
    }

    error_log("Notifying admin ID: $admin_id about quotation response from client ID: $client_id (job order ID: $job_order_id)");

    $result = createNotification(
        $admin_id,
        'admin',
        $title,
        $message,
        $job_order_id,
        'quotation_response',
        $db_connection
    );

    error_log("Admin notification creation result: " . ($result ? 'success' : 'failed'));

    return $result;
}

/**
 * Create notification for admin about technician assignment
 *
 * @param int $admin_id - The admin ID
 * @param int $technician_id - The technician ID
 * @param string $technician_name - The technician name
 * @param int $appointment_id - The appointment ID
 * @param string $client_name - The client name
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyAdminAboutTechnicianAssignment($admin_id, $technician_id, $technician_name, $appointment_id, $client_name, $db_connection = null) {
    $title = "Technician Assigned to Inspection";
    $message = "Technician $technician_name has been assigned to an inspection for client $client_name.";

    error_log("Notifying admin ID: $admin_id about technician assignment (technician ID: $technician_id, appointment ID: $appointment_id)");

    $result = createNotification(
        $admin_id,
        'admin',
        $title,
        $message,
        $appointment_id,
        'technician_assignment',
        $db_connection
    );

    error_log("Notification creation result: " . ($result ? 'success' : 'failed'));

    return $result;
}

/**
 * Create notification for client about job order report
 *
 * @param int $client_id - The client ID
 * @param int $job_order_id - The job order ID
 * @param string $technician_name - The technician name
 * @param string $type_of_work - The type of work
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyClientAboutJobOrderReport($client_id, $job_order_id, $technician_name, $type_of_work, $db_connection = null) {
    $title = "Job Order Report Submitted";
    $message = "Technician $technician_name has submitted a report for your $type_of_work job order. You can view the details in your job order reports.";

    error_log("Notifying client ID: $client_id about job order report (job order ID: $job_order_id, technician: $technician_name)");

    $result = createNotification(
        $client_id,
        'client',
        $title,
        $message,
        $job_order_id,
        'job_order_report',
        $db_connection
    );

    error_log("Client notification creation result: " . ($result ? 'success' : 'failed'));

    return $result;
}

/**
 * Create notification for admin about job order report
 *
 * @param int $admin_id - The admin ID
 * @param int $job_order_id - The job order ID
 * @param string $technician_name - The technician name
 * @param string $client_name - The client name
 * @param string $type_of_work - The type of work
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyAdminAboutJobOrderReport($admin_id, $job_order_id, $technician_name, $client_name, $type_of_work, $db_connection = null) {
    $title = "New Job Order Report";
    $message = "Technician $technician_name has submitted a report for the $type_of_work job order for client $client_name.";

    error_log("Notifying admin ID: $admin_id about job order report (job order ID: $job_order_id, technician: $technician_name, client: $client_name)");

    $result = createNotification(
        $admin_id,
        'admin',
        $title,
        $message,
        $job_order_id,
        'job_order_report',
        $db_connection
    );

    error_log("Admin notification creation result: " . ($result ? 'success' : 'failed'));

    return $result;
}

/**
 * Create notification for technician about job order assignment
 *
 * @param int $technician_id - The technician ID
 * @param int $job_order_id - The job order ID
 * @param string $client_name - The client name
 * @param string $date - The job order date
 * @param string $time - The job order time
 * @param string $type_of_work - The type of work
 * @param string $location - The job location
 * @param bool $is_primary - Whether the technician is the primary technician
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyTechnicianAboutJobOrderAssignment($technician_id, $job_order_id, $client_name, $date, $time, $type_of_work, $location, $is_primary = false, $db_connection = null) {
    $title = "New Job Order Assigned";
    $message = "You have been assigned to a job order for $client_name on $date at $time. Type: $type_of_work. Location: $location";

    if ($is_primary) {
        $message .= ". You are the primary technician responsible for submitting reports.";
    }

    error_log("Notifying technician ID: $technician_id about job order assignment (job order ID: $job_order_id, client: $client_name, date: $date, time: $time)");

    $result = createNotification(
        $technician_id,
        'technician',
        $title,
        $message,
        $job_order_id,
        'job_order',
        $db_connection
    );

    error_log("Technician job order notification creation result: " . ($result ? 'success' : 'failed'));

    return $result;
}

/**
 * Create notification for technician about inspection assignment
 *
 * @param int $technician_id - The technician ID
 * @param int $inspection_id - The inspection ID
 * @param string $client_name - The client name
 * @param string $date - The inspection date
 * @param string $time - The inspection time
 * @param string $location - The inspection location
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyTechnicianAboutInspectionAssignment($technician_id, $inspection_id, $client_name, $date, $time, $location, $db_connection = null) {
    // Format the time in 12-hour format (g:i A)
    $formattedTime = date('g:i A', strtotime($time));

    $title = "New Inspection Assigned";
    $message = "You have been assigned to an inspection for $client_name on $date at $formattedTime. Location: $location";

    error_log("Notifying technician ID: $technician_id about inspection assignment (inspection ID: $inspection_id, client: $client_name, date: $date, time: $formattedTime)");

    $result = createNotification(
        $technician_id,
        'technician',
        $title,
        $message,
        $inspection_id,
        'inspection',
        $db_connection
    );

    error_log("Technician inspection notification creation result: " . ($result ? 'success' : 'failed'));

    return $result;
}
/**
 * Create notification for client about job order rescheduling
 *
 * @param int $client_id - The client ID
 * @param int $job_order_id - The job order ID
 * @param string $type_of_work - The type of work
 * @param string $location - The job location
 * @param string $old_date - The old date (formatted)
 * @param string $old_time - The old time (formatted)
 * @param string $new_date - The new date (formatted)
 * @param string $new_time - The new time (formatted)
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyClientAboutJobOrderReschedule($client_id, $job_order_id, $type_of_work, $location, $old_date, $old_time, $new_date, $new_time, $db_connection = null) {
    $title = "Job Order Rescheduled";
    $message = "Your {$type_of_work} job order at {$location} on {$old_date} at {$old_time} has been rescheduled to {$new_date} at {$new_time}.";

    error_log("Notifying client ID: $client_id about job order reschedule (job order ID: $job_order_id)");

    $result = createNotification(
        $client_id,
        'client',
        $title,
        $message,
        $job_order_id,
        'job_order_rescheduled',
        $db_connection
    );

    error_log("Client job order reschedule notification creation result: " . ($result ? 'success' : 'failed'));

    return $result;
}

/**
 * Create notification for technician about job order rescheduling
 *
 * @param int $technician_id - The technician ID
 * @param int $job_order_id - The job order ID
 * @param string $client_name - The client name
 * @param string $type_of_work - The type of work
 * @param string $location - The job location
 * @param string $old_date - The old date (formatted)
 * @param string $old_time - The old time (formatted)
 * @param string $new_date - The new date (formatted)
 * @param string $new_time - The new time (formatted)
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyTechnicianAboutJobOrderReschedule($technician_id, $job_order_id, $client_name, $type_of_work, $location, $old_date, $old_time, $new_date, $new_time, $db_connection = null) {
    $title = "Job Order Rescheduled";
    $message = "The {$type_of_work} job order for {$client_name} at {$location} on {$old_date} at {$old_time} has been rescheduled to {$new_date} at {$new_time}.";

    error_log("Notifying technician ID: $technician_id about job order reschedule (job order ID: $job_order_id, client: $client_name)");

    $result = createNotification(
        $technician_id,
        'technician',
        $title,
        $message,
        $job_order_id,
        'job_order_rescheduled',
        $db_connection
    );

    error_log("Technician job order reschedule notification creation result: " . ($result ? 'success' : 'failed'));

    return $result;
}

/**
 * Notify client about completed job
 *
 * @param int $client_id - The client ID
 * @param int $job_order_id - The job order ID
 * @param int $technician_id - The technician ID
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyClientAboutCompletedJob($client_id, $job_order_id, $technician_id, $db_connection = null) {
    // Use provided connection or fall back to global
    if ($db_connection === null) {
        global $conn;
        $db_connection = $conn;
    }

    // Get technician name
    $tech_query = $db_connection->prepare("SELECT username FROM technicians WHERE technician_id = ?");
    $tech_query->bind_param("i", $technician_id);
    $tech_query->execute();
    $tech_result = $tech_query->get_result();
    $tech_data = $tech_result->fetch_assoc();
    $technician_name = $tech_data['username'] ?? 'Your technician';

    // Get job order details for more informative notification
    $job_query = $db_connection->prepare("
        SELECT jo.type_of_work, a.location_address
        FROM job_order jo
        JOIN assessment_report ar ON jo.report_id = ar.report_id
        JOIN appointments a ON ar.appointment_id = a.appointment_id
        WHERE jo.job_order_id = ?
    ");
    $job_query->bind_param("i", $job_order_id);
    $job_query->execute();
    $job_result = $job_query->get_result();
    $job_data = $job_result->fetch_assoc();

    $type_of_work = $job_data['type_of_work'] ?? 'pest control';
    $location = $job_data['location_address'] ?? 'your location';

    $title = "Service Completed";
    $message = "$technician_name has completed your $type_of_work service at $location. Please check your job order reports and provide feedback on your experience.";

    error_log("Sending job completion notification to client ID: $client_id for job order ID: $job_order_id");

    return createNotification(
        $client_id,
        'client',
        $title,
        $message,
        $job_order_id,
        'job_completed',
        $db_connection
    );
}

/**
 * Notify admin about completed job
 *
 * @param int $staff_id - The admin/staff ID
 * @param int $job_order_id - The job order ID
 * @param int $technician_id - The technician ID
 * @param object|null $db_connection - Optional database connection
 * @return bool - True if notification was created successfully
 */
function notifyAdminAboutCompletedJob($staff_id, $job_order_id, $technician_id, $db_connection = null) {
    // Use provided connection or fall back to global
    if ($db_connection === null) {
        global $conn;
        $db_connection = $conn;
    }

    // Get technician name
    $tech_query = $db_connection->prepare("SELECT username FROM technicians WHERE technician_id = ?");
    $tech_query->bind_param("i", $technician_id);
    $tech_query->execute();
    $tech_result = $tech_query->get_result();
    $tech_data = $tech_result->fetch_assoc();
    $technician_name = $tech_data['username'] ?? 'A technician';

    // Get job order details including client name and type of work
    $job_query = $db_connection->prepare("
        SELECT a.client_name, jo.type_of_work, a.location_address
        FROM job_order jo
        JOIN assessment_report ar ON jo.report_id = ar.report_id
        JOIN appointments a ON ar.appointment_id = a.appointment_id
        WHERE jo.job_order_id = ?
    ");
    $job_query->bind_param("i", $job_order_id);
    $job_query->execute();
    $job_result = $job_query->get_result();
    $job_data = $job_result->fetch_assoc();

    $client_name = $job_data['client_name'] ?? 'a client';
    $type_of_work = $job_data['type_of_work'] ?? 'pest control';
    $location = $job_data['location_address'] ?? '';

    $title = "Job Order Completed";
    $message = "$technician_name has completed a $type_of_work job order for $client_name";

    if (!empty($location)) {
        $message .= " at $location";
    }

    $message .= ". Please check the job order report.";

    error_log("Sending job completion notification to admin ID: $staff_id for job order ID: $job_order_id");

    return createNotification(
        $staff_id,
        'admin',
        $title,
        $message,
        $job_order_id,
        'job_completed',
        $db_connection
    );
}
?>
