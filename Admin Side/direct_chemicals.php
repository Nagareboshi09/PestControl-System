<?php
session_start();
require_once '../db_connect.php';
require_once '../chemical_display_functions.php';

// Set proper content type for JSON response
header('Content-Type: application/json');

// Check if user is logged in as admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'office_staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if report_id is provided
if (!isset($_GET['report_id']) || empty($_GET['report_id'])) {
    echo json_encode(['success' => false, 'message' => 'Report ID is required']);
    exit;
}

$report_id = intval($_GET['report_id']);

// Get chemical recommendations from assessment_report
$query = "SELECT chemical_recommendations, pest_types, problem_area, area FROM assessment_report WHERE report_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Report not found']);
    exit;
}

$row = $result->fetch_assoc();
$chemical_recommendations = $row['chemical_recommendations'];
$pest_types = $row['pest_types'];
$problem_area = $row['problem_area'];
$area = $row['area'];

// For debugging
error_log("Report ID: $report_id, Chemical Recommendations: " . substr($chemical_recommendations, 0, 100) . "...");

// Check if chemical recommendations exist
if (empty($chemical_recommendations)) {
    echo json_encode(['success' => false, 'message' => 'No chemical recommendations found for this report']);
    exit;
}

// Try to parse the chemical recommendations directly
try {
    $decoded = json_decode($chemical_recommendations, true);

    // If it's a valid JSON array, process it
    if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
        $chemicals_array = [];

        foreach ($decoded as $chemical) {
            // Handle different formats of chemical data
            $name = isset($chemical['name']) ? $chemical['name'] :
                   (isset($chemical['chemical_name']) ? $chemical['chemical_name'] : 'Unknown');

            $type = isset($chemical['type']) ? $chemical['type'] : 'Unknown';

            $dosage = isset($chemical['dosage']) ? $chemical['dosage'] :
                     (isset($chemical['recommended_dosage']) ? $chemical['recommended_dosage'] : 'As recommended');

            $dosage_unit = isset($chemical['dosage_unit']) ? $chemical['dosage_unit'] : 'ml';

            $target_pest = isset($chemical['target_pest']) ? $chemical['target_pest'] : 'General';

            $chemicals_array[] = [
                'name' => $name,
                'type' => $type,
                'dosage' => $dosage,
                'dosage_unit' => $dosage_unit,
                'target_pest' => $target_pest
            ];
        }

        if (!empty($chemicals_array)) {
            echo json_encode([
                'success' => true,
                'chemicals_array' => $chemicals_array,
                'count' => count($chemicals_array),
                'pest_types' => $pest_types,
                'problem_area' => $problem_area,
                'area' => $area
            ]);
            exit;
        }
    }
} catch (Exception $e) {
    error_log("Error parsing chemical recommendations: " . $e->getMessage());
}

// If direct parsing failed, try using the shared function
$chemicals_array = parseChemicalRecommendations($chemical_recommendations);

// Convert simple array of names to full chemical objects
if (!empty($chemicals_array) && is_array($chemicals_array) && isset($chemicals_array[0]) && is_string($chemicals_array[0])) {
    $formatted_chemicals = [];
    foreach ($chemicals_array as $name) {
        $formatted_chemicals[] = [
            'name' => $name,
            'type' => 'Unknown',
            'dosage' => 'As recommended',
            'dosage_unit' => 'ml',
            'target_pest' => 'General'
        ];
    }
    $chemicals_array = $formatted_chemicals;
}

// If no chemical recommendations found, try to get them from the database based on pest types
if (empty($chemicals_array) && !empty($pest_types)) {
    // Get chemicals recommended for these pest types
    $pest_types_array = explode(',', $pest_types);
    $recommended_chemicals = [];

    foreach ($pest_types_array as $pest_type) {
        $pest_type = trim($pest_type);

        // Skip empty pest types
        if (empty($pest_type)) {
            continue;
        }

        // Query the chemical_inventory table for recommendations
        $query = "SELECT id as chemical_id, chemical_name, type, target_pest, quantity, unit, expiration_date
                  FROM chemical_inventory
                  WHERE target_pest LIKE ? OR target_pest = 'General'
                  AND quantity > 0
                  ORDER BY expiration_date ASC, chemical_name ASC";
        $stmt = $conn->prepare($query);
        $pest_param = "%$pest_type%";
        $stmt->bind_param("s", $pest_param);
        $stmt->execute();
        $chemicals_result = $stmt->get_result();

        while ($chemical = $chemicals_result->fetch_assoc()) {
            // Check if this chemical is already in the recommendations
            $exists = false;
            foreach ($recommended_chemicals as $existing) {
                if (isset($existing['chemical_id']) && $existing['chemical_id'] == $chemical['chemical_id']) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                // Calculate a default dosage based on area and chemical type
                $dosage = 20; // Default 20ml per 100sqm

                // Use specific dosage rates for known chemicals
                if ($chemical['chemical_name'] === 'Fipronil') {
                    $dosage = 12; // 12ml per 100sqm (24ml for 200sqm)
                } else if ($chemical['chemical_name'] === 'Cypermethrin') {
                    $dosage = 20; // 20ml per 100sqm (40ml for 200sqm)
                }

                if ($area > 0) {
                    $dosage = round(($area / 100) * $dosage, 2); // Apply area calculation
                }

                $recommended_chemicals[] = [
                    'chemical_id' => $chemical['chemical_id'],
                    'name' => $chemical['chemical_name'],
                    'type' => $chemical['type'],
                    'dosage' => $dosage,
                    'dosage_unit' => 'ml',
                    'target_pest' => $chemical['target_pest']
                ];
            }
        }
    }

    if (!empty($recommended_chemicals)) {
        $chemicals_array = $recommended_chemicals;
    }
}

if (!empty($chemicals_array)) {
    // Return the chemicals array
    echo json_encode([
        'success' => true,
        'chemicals_array' => $chemicals_array,
        'count' => count($chemicals_array),
        'pest_types' => $pest_types,
        'problem_area' => $problem_area,
        'area' => $area
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No valid chemical recommendations found',
        'raw_data' => $chemical_recommendations,
        'pest_types' => $pest_types,
        'problem_area' => $problem_area,
        'area' => $area
    ]);
}
?>
