<?php
session_start();
require_once '../db_config.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Get user information
$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;

// Map office_staff to admin for notifications
$user_type = ($role === 'office_staff') ? 'admin' : $role;

// Prepare response
$response = [
    'success' => true,
    'session' => [
        'user_id' => $user_id,
        'role' => $role,
        'notification_user_type' => $user_type
    ],
    'notifications' => [],
    'unread_count' => 0,
    'database' => [
        'connection' => isset($pdo) ? 'established' : 'not established'
    ]
];

// Get notifications if user is logged in
if ($user_id && $user_type) {
    try {
        // Get unread count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND user_type = ? AND is_read = 0");
        $stmt->execute([$user_id, $user_type]);
        $response['unread_count'] = (int)$stmt->fetchColumn();
        
        // Get recent notifications
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND user_type = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$user_id, $user_type]);
        $response['notifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get notification table structure
        $stmt = $pdo->query("DESCRIBE notifications");
        $response['database']['table_structure'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $response['success'] = false;
        $response['error'] = $e->getMessage();
    }
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
