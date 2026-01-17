<?php
session_start();
require_once 'db_connect.php';
require_once 'notification_functions.php';

header('Content-Type: application/json');

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
} elseif ($user_type === 'office_staff') {
    $user_type = 'admin'; // Map office_staff to admin for notifications
    $user_id = $_SESSION['user_id'];
} else {
    echo json_encode(['error' => 'Invalid user type']);
    exit;
}

// Mark all notifications as read
$success = markAllNotificationsAsRead($user_id, $user_type);

echo json_encode([
    'success' => $success,
    'message' => $success ? 'All notifications marked as read' : 'Failed to mark notifications as read'
]);
?>
