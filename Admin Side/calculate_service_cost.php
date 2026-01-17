<?php
// Prevent any output before headers
ob_start();

// Enable error reporting for debugging but capture it
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start session
session_start();

// Create a log file for debugging
$log_file = '../logs/cost_calculation.log';
if (!file_exists('../logs/')) {
    mkdir('../logs/', 0777, true);
}

// Function to log messages
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// Log request information
log_message("Cost calculation request received: " . json_encode($_GET));
log_message("Session data: " . json_encode($_SESSION));

try {
    require_once '../db_connect.php';

    // Set proper content type for JSON response
    header('Content-Type: application/json');

    // For testing purposes, temporarily bypass authentication
    // Comment this out in production
    /*
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'admin' && $_SESSION['role'] !== 'office_staff')) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
    */

// Check if report_id is provided
if (!isset($_GET['report_id']) || empty($_GET['report_id'])) {
    echo json_encode(['success' => false, 'message' => 'Report ID is required']);
    exit;
}

$report_id = intval($_GET['report_id']);

// Get custom base rate if provided
$base_rate = isset($_GET['base_rate']) ? floatval($_GET['base_rate']) : 20; // Default to 20 if not provided

// Validate base rate
if ($base_rate <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid base rate']);
    exit;
}

// Get area and frequency from assessment_report
$query = "SELECT area, frequency, pest_types, problem_area, type_of_work FROM assessment_report WHERE report_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Report not found']);
    exit;
}

$row = $result->fetch_assoc();
$area = floatval($row['area']);
$frequency = $row['frequency'];
$pest_types = $row['pest_types'];
$problem_area = $row['problem_area'];
$type_of_work = $row['type_of_work'];

// For debugging
error_log("Calculating cost for Report ID: $report_id, Area: $area, Frequency: $frequency, Pest Types: $pest_types, Problem Area: $problem_area, Type of Work: $type_of_work");

// Base cost calculation - use the provided base rate
$base_cost = $area * $base_rate;

// Apply frequency multiplier based on number of services per year
$frequency_multiplier = 1; // Default for one-time treatment
$services_per_year = 1; // Default for one-time treatment

switch ($frequency) {
    case 'weekly':
        $services_per_year = 52; // 52 weeks in a year
        $frequency_multiplier = $services_per_year;
        break;
    case 'monthly':
        $services_per_year = 12; // 12 months in a year
        $frequency_multiplier = $services_per_year;
        break;
    case 'quarterly':
        $services_per_year = 4; // 4 quarters in a year
        $frequency_multiplier = $services_per_year;
        break;
}

// Apply type of work multiplier
$work_type_multiplier = 1.0; // Default multiplier
if (!empty($type_of_work)) {
    // Parse type of work - could be JSON, comma-separated, or a single value
    $work_types = [];

    // Try to parse as JSON first
    $decoded = json_decode($type_of_work, true);
    if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
        $work_types = $decoded;
    } else {
        // If not JSON, split by comma
        $work_types = array_map('trim', explode(',', $type_of_work));
    }

    // Apply multipliers based on work types
    foreach ($work_types as $work_type) {
        $work_type = strtolower(trim($work_type));

        if (strpos($work_type, 'termite') !== false) {
            $work_type_multiplier = max($work_type_multiplier, 1.3); // 30% increase for termite control
        } elseif (strpos($work_type, 'rodent') !== false) {
            $work_type_multiplier = max($work_type_multiplier, 1.2); // 20% increase for rodent control
        } elseif (strpos($work_type, 'mosquito') !== false || strpos($work_type, 'dengue') !== false) {
            $work_type_multiplier = max($work_type_multiplier, 1.15); // 15% increase for mosquito/dengue control
        } elseif (strpos($work_type, 'fumigation') !== false) {
            $work_type_multiplier = max($work_type_multiplier, 1.25); // 25% increase for fumigation
        }
    }
}

// Apply pest types multiplier
$pest_multiplier = 1.0; // Default multiplier
if (!empty($pest_types)) {
    $pest_array = array_map('trim', explode(',', $pest_types));

    // Count the number of pest types
    $pest_count = count($pest_array);

    // Apply multiplier based on number of pest types
    if ($pest_count > 3) {
        $pest_multiplier = 1.2; // 20% increase for more than 3 pest types
    } elseif ($pest_count > 1) {
        $pest_multiplier = 1.1; // 10% increase for 2-3 pest types
    }

    // Check for specific high-cost pests
    foreach ($pest_array as $pest) {
        $pest = strtolower(trim($pest));

        if (strpos($pest, 'termite') !== false) {
            $pest_multiplier = max($pest_multiplier, 1.3); // 30% increase for termites
        } elseif (strpos($pest, 'bed bug') !== false) {
            $pest_multiplier = max($pest_multiplier, 1.25); // 25% increase for bed bugs
        } elseif (strpos($pest, 'rat') !== false || strpos($pest, 'rodent') !== false) {
            $pest_multiplier = max($pest_multiplier, 1.2); // 20% increase for rats/rodents
        }
    }
}

// Calculate final cost - now we only use the frequency multiplier
// We no longer apply work_type_multiplier and pest_multiplier as per the new requirements
$cost = $base_cost * $frequency_multiplier;

// Round to nearest 100 for cleaner pricing
$cost = ceil($cost / 100) * 100;

// Return the calculated cost
echo json_encode([
    'success' => true,
    'cost' => $cost,
    'formatted_cost' => '₱ ' . number_format($cost, 2),
    'calculation' => [
        'area' => $area,
        'base_rate' => $base_rate,
        'base_cost' => $base_cost,
        'frequency' => $frequency,
        'services_per_year' => $services_per_year,
        'frequency_multiplier' => $frequency_multiplier,
        'final_cost' => $cost
    ]
]);

// Log success
log_message("Cost calculation successful: Cost = $cost");

} catch (Exception $e) {
    // Log the error
    log_message("Error: " . $e->getMessage());

    // Return error as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}

// Flush output buffer
ob_end_flush();
?>
