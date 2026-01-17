<?php
session_start();
require_once '../../db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: max-age=60'); // Cache for 60 seconds

// Check if user is logged in as technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get technician ID
$technician_id = $_SESSION['user_id'];

// Create a log file for debugging
$log_file = __DIR__ . '/../../logs/chemical_dilution_rates.log';
if (!file_exists(__DIR__ . '/../../logs/')) {
    mkdir(__DIR__ . '/../../logs/', 0777, true);
}

// Log function for easier debugging
function log_debug($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

log_debug("API call started");

try {
    // Query to get dilution rates and area coverage for all chemicals
    $query = "SELECT id, chemical_name, type, dilution_rate, area_coverage 
              FROM chemical_inventory 
              WHERE quantity > 0";

    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch all chemicals with their dilution rates
    $chemicals = [];
    while ($row = $result->fetch_assoc()) {
        // Set default values if dilution_rate or area_coverage is NULL
        if ($row['dilution_rate'] === null) {
            // Default dilution rates based on chemical name
            if (stripos($row['chemical_name'], 'fipronil') !== false) {
                $row['dilution_rate'] = 12; // 12ml per 100sqm
            } else if (stripos($row['chemical_name'], 'cypermethrin') !== false) {
                $row['dilution_rate'] = 20; // 20ml per 100sqm
            } else {
                $row['dilution_rate'] = 20; // Default 20ml per 100sqm
            }
        }

        if ($row['area_coverage'] === null) {
            $row['area_coverage'] = 100; // Default 100 m² per liter
        }

        // Add to chemicals array
        $chemicals[] = $row;
        
        // Log the chemical data
        log_debug("Chemical: {$row['chemical_name']}, Dilution Rate: {$row['dilution_rate']}, Area Coverage: {$row['area_coverage']}");
    }

    // Create the response
    $response = [
        'success' => true,
        'message' => 'Chemical dilution rates retrieved successfully',
        'chemicals' => $chemicals,
        'timestamp' => time()
    ];

    // Cache the response in session
    $_SESSION['chemical_dilution_rates'] = json_encode($response);
    $_SESSION['chemical_dilution_rates_timestamp'] = time();

    // Return success response with chemicals
    echo json_encode($response);
    log_debug("API call completed successfully with " . count($chemicals) . " chemicals");

} catch (Exception $e) {
    // Log the error
    log_debug("Error: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving chemical dilution rates: ' . $e->getMessage(),
        'error_code' => $conn->errno ?? 0,
        'error_details' => $conn->error ?? ''
    ]);
}

// Close database connection
$conn->close();
?>
