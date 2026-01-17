<?php
session_start();
require_once 'db_connect.php';
require_once 'db_config.php'; // Add PDO connection
require_once 'notification_functions.php';

header('Content-Type: application/json');

/**
 * Send job order completion notifications to admin and client
 *
 * @param int $job_order_id - The job order ID
 * @param int $technician_id - The technician ID who completed the job
 * @param object|null $db_connection - Optional database connection
 * @return array - Result with success status and messages
 */
function sendJobOrderCompletionNotifications($job_order_id, $technician_id, $db_connection = null) {
    // Use provided connection or fall back to global
    if ($db_connection === null) {
        global $conn;
        $db_connection = $conn;
    }

    // Initialize result array
    $result = [
        'success' => true,
        'message' => 'Notifications sent successfully',
        'admin_notification' => false,
        'client_notification' => false,
        'errors' => []
    ];

    try {
        error_log("Starting job order completion notification process for job_order_id: $job_order_id, technician_id: $technician_id");

        // Get job order details
        $job_query = $db_connection->prepare("
            SELECT jo.*, ar.report_id, a.client_id, a.client_name, jo.type_of_work, a.location_address
            FROM job_order jo
            JOIN assessment_report ar ON jo.report_id = ar.report_id
            JOIN appointments a ON ar.appointment_id = a.appointment_id
            WHERE jo.job_order_id = ?
        ");
        $job_query->bind_param("i", $job_order_id);
        $job_query->execute();
        $job_result = $job_query->get_result();

        if ($job_result->num_rows === 0) {
            $result['success'] = false;
            $result['message'] = "Job order not found";
            $result['errors'][] = "Job order ID $job_order_id not found";
            error_log("Error: Job order ID $job_order_id not found");
            return $result;
        }

        $job_data = $job_result->fetch_assoc();
        $client_id = $job_data['client_id'];
        $client_name = $job_data['client_name'];
        $type_of_work = $job_data['type_of_work'] ?? 'pest control';
        $location = $job_data['location_address'] ?? '';

        error_log("Found job order: client_id=$client_id, client_name=$client_name, type_of_work=$type_of_work, location=$location");

        // Get technician name
        $tech_query = $db_connection->prepare("SELECT username FROM technicians WHERE technician_id = ?");
        $tech_query->bind_param("i", $technician_id);
        $tech_query->execute();
        $tech_result = $tech_query->get_result();

        if ($tech_result->num_rows === 0) {
            $result['success'] = false;
            $result['message'] = "Technician not found";
            $result['errors'][] = "Technician ID $technician_id not found";
            error_log("Error: Technician ID $technician_id not found");
            return $result;
        }

        $tech_data = $tech_result->fetch_assoc();
        $technician_name = $tech_data['username'] ?? 'Technician';
        error_log("Found technician: $technician_name (ID: $technician_id)");

        // Get all admin IDs - first try from office_staff table
        $admin_ids = [];

        // Try to get admin IDs from office_staff table first
        $admin_query = $db_connection->query("SELECT staff_id FROM office_staff");
        if ($admin_query && $admin_query->num_rows > 0) {
            while ($admin = $admin_query->fetch_assoc()) {
                $admin_ids[] = $admin['staff_id'];
            }
            error_log("Found " . count($admin_ids) . " admins from office_staff table");
        } else {
            // Fallback to users table if no admins found in office_staff
            $admin_query = $db_connection->query("SELECT user_id FROM users WHERE role = 'office_staff' OR role = 'admin'");
            if ($admin_query) {
                while ($admin = $admin_query->fetch_assoc()) {
                    $admin_ids[] = $admin['user_id'];
                }
                error_log("Found " . count($admin_ids) . " admins from users table");
            }
        }

        // If still no admins found, log an error but continue
        if (empty($admin_ids)) {
            error_log("Warning: No admin users found in the database");
            $result['errors'][] = "No admin users found in the database";
        }

        // Notify client
        if ($client_id) {
            error_log("Sending notification to client ID: $client_id");
            $client_notification = notifyClientAboutCompletedJob($client_id, $job_order_id, $technician_id, $db_connection);
            $result['client_notification'] = $client_notification;

            if (!$client_notification) {
                $result['errors'][] = "Failed to send notification to client ID $client_id";
                error_log("Failed to send notification to client ID $client_id");
            } else {
                error_log("Successfully sent notification to client ID $client_id");
            }
        } else {
            $result['errors'][] = "Client ID not found for job order $job_order_id";
            error_log("Error: Client ID not found for job order $job_order_id");
        }

        // Notify all admins
        $admin_notifications_sent = 0;
        foreach ($admin_ids as $admin_id) {
            error_log("Sending notification to admin ID: $admin_id");
            $admin_notification = notifyAdminAboutCompletedJob($admin_id, $job_order_id, $technician_id, $db_connection);

            if ($admin_notification) {
                $admin_notifications_sent++;
                error_log("Successfully sent notification to admin ID $admin_id");
            } else {
                $result['errors'][] = "Failed to send notification to admin ID $admin_id";
                error_log("Failed to send notification to admin ID $admin_id");
            }
        }

        $result['admin_notification'] = $admin_notifications_sent > 0;
        error_log("Admin notifications sent: $admin_notifications_sent");

        // If there were any errors but we still sent some notifications, partial success
        if (!empty($result['errors']) && ($result['client_notification'] || $result['admin_notification'])) {
            $result['success'] = true;
            $result['message'] = 'Notifications partially sent with some errors';
            error_log("Notifications partially sent with " . count($result['errors']) . " errors");
        } else if (!$result['client_notification'] && !$result['admin_notification']) {
            $result['success'] = false;
            $result['message'] = 'Failed to send any notifications';
            error_log("Failed to send any notifications");
        } else {
            error_log("All notifications sent successfully");
        }

    } catch (Exception $e) {
        $result['success'] = false;
        $result['message'] = 'Exception during notification sending: ' . $e->getMessage();
        $result['errors'][] = $e->getMessage();
        error_log("Exception during notification sending: " . $e->getMessage());
    }

    return $result;
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    $action = $_GET['action'];

    // Handle job order completion notification
    if ($action === 'job_completed' && isset($_POST['job_order_id']) && isset($_POST['technician_id'])) {
        $job_order_id = intval($_POST['job_order_id']);
        $technician_id = intval($_POST['technician_id']);

        $result = sendJobOrderCompletionNotifications($job_order_id, $technician_id);
        echo json_encode($result);
        exit;
    }
}

// Check if user is logged in
if (!isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Determine user type and ID
$user_type = $_SESSION['role'];
$user_id = null;

if ($user_type === 'client') {
    $user_id = $_SESSION['client_id'];
} elseif ($user_type === 'technician') {
    $user_id = $_SESSION['user_id'];
    $user_type = 'technician'; // Explicitly set user_type to technician
} elseif ($user_type === 'office_staff') {
    $user_type = 'admin'; // Map office_staff to admin for notifications
    $user_id = $_SESSION['user_id'];
} else {
    echo json_encode(['error' => 'Invalid user type']);
    exit;
}

// Log the user type and ID for debugging
error_log("get_notifications.php: user_type=$user_type, user_id=$user_id");

// Get notifications
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

// Try to use PDO connection first, fall back to mysqli if needed
$db_connection = isset($pdo) ? $pdo : $conn;

$notifications = getNotifications($user_id, $user_type, $limit, $unread_only, $db_connection);
$unread_count = getUnreadNotificationsCount($user_id, $user_type, $db_connection);

echo json_encode([
    'notifications' => $notifications,
    'unread_count' => $unread_count
]);
?>
