<?php
session_start();
require_once 'db_connect.php';
require_once 'db_config.php'; // Add PDO connection
require_once 'notification_functions.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Log the request for debugging
$request_data = json_encode([
    'POST' => $_POST,
    'SESSION' => $_SESSION
]);
error_log("mark_notification_read.php request: $request_data");

// Check if user is logged in
if (!isset($_SESSION['role'])) {
    $error = ['error' => 'Not authenticated'];
    error_log("mark_notification_read.php error: " . json_encode($error));
    echo json_encode($error);
    exit;
}

// Try to use PDO connection first, fall back to mysqli if needed
$db_connection = isset($pdo) ? $pdo : $conn;

// Check if notification_id is provided
if (isset($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    $success = markNotificationAsRead($notification_id, $db_connection);

    $response = ['success' => $success];
    error_log("mark_notification_read.php response (single): " . json_encode($response));
    echo json_encode($response);
}
// Check if mark_all is provided
elseif (isset($_POST['mark_all']) && $_POST['mark_all'] === 'true') {
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
        $error = ['error' => 'Invalid user type'];
        error_log("mark_notification_read.php error: " . json_encode($error));
        echo json_encode($error);
        exit;
    }

    error_log("mark_notification_read.php: Marking all notifications as read for user_type=$user_type, user_id=$user_id");

    error_log("Marking all notifications as read for user_id: $user_id, user_type: $user_type");
    $success = markAllNotificationsAsRead($user_id, $user_type, $db_connection);
    $response = ['success' => $success];
    error_log("mark_notification_read.php response (all): " . json_encode($response));
    echo json_encode($response);
}
else {
    $error = ['error' => 'Missing parameters'];
    error_log("mark_notification_read.php error: " . json_encode($error));
    echo json_encode($error);
}
?>
