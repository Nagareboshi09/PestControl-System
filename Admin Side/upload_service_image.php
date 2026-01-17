<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

// Check if file was uploaded
if (!isset($_FILES['service_image']) || $_FILES['service_image']['error'] !== UPLOAD_ERR_OK) {
    $error = isset($_FILES['service_image']) ? $_FILES['service_image']['error'] : 'No file uploaded';
    echo json_encode(['success' => false, 'error' => 'File upload error: ' . $error]);
    exit;
}

// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/services/';
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
        exit;
    }
}

// Get file info
$file_name = $_FILES['service_image']['name'];
$file_tmp = $_FILES['service_image']['tmp_name'];
$file_size = $_FILES['service_image']['size'];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Set allowed file extensions
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// Check file extension
if (!in_array($file_ext, $allowed_extensions)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file extension. Allowed: ' . implode(', ', $allowed_extensions)]);
    exit;
}

// Check file size (max 5MB)
$max_size = 5 * 1024 * 1024; // 5MB in bytes
if ($file_size > $max_size) {
    echo json_encode(['success' => false, 'error' => 'File size too large. Maximum size: 5MB']);
    exit;
}

// Generate a unique filename to prevent overwriting
$new_file_name = uniqid('service_') . '.' . $file_ext;
$upload_path = $upload_dir . $new_file_name;

// Move the uploaded file
if (move_uploaded_file($file_tmp, $upload_path)) {
    echo json_encode(['success' => true, 'file_name' => $new_file_name, 'file_path' => $upload_path]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
}
?>
