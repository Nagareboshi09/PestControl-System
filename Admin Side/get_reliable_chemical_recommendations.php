<?php
session_start();
require_once '../db_connect.php';

// Set up logging for debugging
$log_file = 'chemical_recommendations_log.txt';
file_put_contents($log_file, "Reliable Chemical Recommendations Request: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'recommendations' => []
];

// Check if the request is a GET request with report_id parameter
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['report_id'])) {
    $response['message'] = 'Invalid request method or missing report_id';
    file_put_contents($log_file, "Error: Invalid request method or missing report_id\n", FILE_APPEND);
    echo json_encode($response);
    exit;
}

// Get the report ID
$report_id = intval($_GET['report_id']);
file_put_contents($log_file, "Processing report ID: $report_id\n", FILE_APPEND);

// First, try to get chemical recommendations directly from the assessment_report table
$query = "SELECT ar.chemical_recommendations, ar.pest_types, ar.area
          FROM assessment_report ar
          WHERE ar.report_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $response['message'] = 'Assessment report not found';
    file_put_contents($log_file, "Error: Assessment report not found\n", FILE_APPEND);
    echo json_encode($response);
    exit;
}

$report_data = $result->fetch_assoc();
$chemical_recommendations = $report_data['chemical_recommendations'];
$pest_types = $report_data['pest_types'];
$area = $report_data['area'];

file_put_contents($log_file, "Found report with pest types: $pest_types, area: $area\n", FILE_APPEND);

// Process chemical recommendations
$chemicals = [];

// Check if we have valid chemical recommendations in the database
if (!empty($chemical_recommendations)) {
    file_put_contents($log_file, "Found chemical recommendations in database\n", FILE_APPEND);

    try {
        // Try to parse the chemical recommendations as JSON
        $decoded = json_decode($chemical_recommendations, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Successfully parsed JSON
            if (isset($decoded['recommendations'])) {
                // Format: { recommendations: { pest_type: [chemicals] } }
                $chemicals = $decoded['recommendations'];
                file_put_contents($log_file, "Parsed recommendations from JSON object\n", FILE_APPEND);
            } else if (is_array($decoded) && !empty($decoded)) {
                // Format: [chemicals]
                $chemicals = $decoded;
                file_put_contents($log_file, "Parsed recommendations from JSON array\n", FILE_APPEND);
            }
        } else {
            // Failed to parse JSON, try to clean the string
            file_put_contents($log_file, "Failed to parse JSON: " . json_last_error_msg() . "\n", FILE_APPEND);

            // Clean the string and try again
            $cleaned = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $chemical_recommendations);
            $cleaned = str_replace("\'", "'", $cleaned);

            $decoded = json_decode($cleaned, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Successfully parsed cleaned JSON
                if (isset($decoded['recommendations'])) {
                    $chemicals = $decoded['recommendations'];
                } else if (is_array($decoded) && !empty($decoded)) {
                    $chemicals = $decoded;
                }
                file_put_contents($log_file, "Parsed recommendations after cleaning\n", FILE_APPEND);
            } else {
                file_put_contents($log_file, "Failed to parse cleaned JSON: " . json_last_error_msg() . "\n", FILE_APPEND);
            }
        }
    } catch (Exception $e) {
        file_put_contents($log_file, "Exception parsing chemical recommendations: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// If we couldn't get valid chemicals from the database, generate new ones
if (empty($chemicals)) {
    file_put_contents($log_file, "No valid chemicals found in database, generating new ones\n", FILE_APPEND);

    // Parse pest types
    $pest_list = explode(',', $pest_types);
    $pest_list = array_map('trim', $pest_list);

    // Map pest types to target pests
    $target_pests = [];
    foreach ($pest_list as $pest) {
        if (stripos($pest, 'termite') !== false) {
            $target_pests[] = 'Termites';
        } else if (stripos($pest, 'ant') !== false || stripos($pest, 'cockroach') !== false ||
                  stripos($pest, 'bed bug') !== false || stripos($pest, 'tick') !== false ||
                  stripos($pest, 'spider') !== false) {
            $target_pests[] = 'Crawling Pest';
        } else if (stripos($pest, 'fly') !== false || stripos($pest, 'mosquito') !== false ||
                  stripos($pest, 'wasp') !== false || stripos($pest, 'bee') !== false) {
            $target_pests[] = 'Flying Pest';
        } else if (stripos($pest, 'rat') !== false || stripos($pest, 'mouse') !== false ||
                  stripos($pest, 'rodent') !== false) {
            $target_pests[] = 'Rodents';
        } else {
            $target_pests[] = 'General Pest';
        }
    }

    // Remove duplicates
    $target_pests = array_unique($target_pests);

    file_put_contents($log_file, "Mapped pest types to target pests: " . implode(', ', $target_pests) . "\n", FILE_APPEND);

    // Get chemicals for each target pest
    $recommendations = [];

    foreach ($target_pests as $target_pest) {
        // Build the query to find chemicals for this target pest
        $query = "SELECT id, chemical_name, type, target_pest, quantity, unit, expiration_date
                  FROM chemical_inventory
                  WHERE target_pest LIKE ?
                  AND quantity > 0
                  ORDER BY expiration_date ASC"; // Sort by expiration date (earliest first)

        // Use wildcards to match partial target pest strings
        $search_term = "%$target_pest%";

        // Also search for "Crawling & Flying Pest" for both crawling and flying pests
        if ($target_pest == 'Crawling Pest' || $target_pest == 'Flying Pest') {
            $search_term = "%Crawling & Flying Pest%";
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $search_term);
        $stmt->execute();
        $result = $stmt->get_result();

        $pest_chemicals = [];
        $unique_chemicals = []; // To track unique chemical names

        while ($row = $result->fetch_assoc()) {
            // Only add each chemical once (the one with earliest expiration date)
            if (!in_array($row['chemical_name'], $unique_chemicals)) {
                $unique_chemicals[] = $row['chemical_name'];

                // Calculate days until expiry
                $expiry_date = new DateTime($row['expiration_date']);
                $today = new DateTime();
                $days_until_expiry = $today->diff($expiry_date)->days;
                if ($today > $expiry_date) {
                    $days_until_expiry = -$days_until_expiry; // Negative if expired
                }

                // Add days until expiry to the row
                $row['days_until_expiry'] = $days_until_expiry;
                $row['expiration_date_formatted'] = $expiry_date->format('Y-m-d');

                // Log expiration date information for debugging
                file_put_contents($log_file, "Chemical: {$row['chemical_name']}, Expiration: {$row['expiration_date']}, Formatted: {$row['expiration_date_formatted']}, Days until expiry: {$row['days_until_expiry']}\n", FILE_APPEND);

                $pest_chemicals[] = $row;
            }
        }

        file_put_contents($log_file, "Found " . count($pest_chemicals) . " chemicals for target pest: $target_pest\n", FILE_APPEND);

        if (!empty($pest_chemicals)) {
            // Calculate dosage based on area
            foreach ($pest_chemicals as &$chemical) {
                // Default dosage calculation
                $dosage = 0;
                $dosage_unit = 'ml';

                // Different dosage calculations based on chemical type
                $chemical_name = strtolower($chemical['chemical_name']);

                if (strpos($chemical_name, 'cypermethrin') !== false) {
                    // Cypermethrin: 5-10ml per liter of water, 1 liter covers ~10 sq meters
                    $dosage = round(($area / 10) * 7.5); // Average 7.5ml per liter
                    $dosage_unit = 'ml';
                } else if (strpos($chemical_name, 'deltamethrin') !== false) {
                    // Deltamethrin: 2-5ml per liter of water
                    $dosage = round(($area / 10) * 3.5); // Average 3.5ml per liter
                    $dosage_unit = 'ml';
                } else if (strpos($chemical_name, 'malathion') !== false) {
                    // Malathion: 10-20ml per liter of water
                    $dosage = round(($area / 10) * 15); // Average 15ml per liter
                    $dosage_unit = 'ml';
                } else if (strpos($chemical_name, 'fipronil') !== false) {
                    // Fipronil: 3-5ml per liter of water
                    $dosage = round(($area / 10) * 4); // Average 4ml per liter
                    $dosage_unit = 'ml';
                } else {
                    // Generic calculation: assume 5ml per liter, 1 liter covers ~10 sq meters
                    $dosage = round(($area / 10) * 5);
                    $dosage_unit = 'ml';
                }

                // Add dosage information
                $chemical['recommended_dosage'] = $dosage;
                $chemical['dosage_unit'] = $dosage_unit;
                $chemical['target_pest'] = $target_pest;
            }

            $recommendations[$target_pest] = $pest_chemicals;
        }
    }

    $chemicals = $recommendations;
}

// Return success response with recommendations
$response['success'] = true;
$response['message'] = 'Chemical recommendations retrieved successfully';
$response['recommendations'] = $chemicals;
$response['pest_types'] = explode(',', $pest_types);
$response['area'] = $area;

file_put_contents($log_file, "Success: Returning chemical recommendations\n", FILE_APPEND);
echo json_encode($response);
?>
