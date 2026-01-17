<?php
// Set proper content type for JSON response
header('Content-Type: application/json');

// Check PHP version
$phpVersion = phpversion();

// Check if required extensions are loaded
$extensions = [
    'mysqli' => extension_loaded('mysqli'),
    'json' => extension_loaded('json'),
    'fileinfo' => extension_loaded('fileinfo')
];

// Check PHP configuration
$config = [
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'post_max_size' => ini_get('post_max_size'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'display_errors' => ini_get('display_errors'),
    'error_reporting' => ini_get('error_reporting')
];

// Check if we can connect to the database
$dbConnection = false;
$dbError = '';
try {
    require_once '../db_connect.php';
    $dbConnection = isset($conn) && $conn;
    if ($dbConnection) {
        $dbInfo = $conn->server_info;
    } else {
        $dbError = 'Database connection failed';
    }
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

// Return the results
echo json_encode([
    'success' => true,
    'php_version' => $phpVersion,
    'extensions' => $extensions,
    'config' => $config,
    'database' => [
        'connected' => $dbConnection,
        'error' => $dbError,
        'info' => $dbInfo ?? 'N/A'
    ],
    'server' => [
        'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'request_time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time())
    ]
]);
?>
