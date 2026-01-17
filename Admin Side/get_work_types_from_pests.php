<?php
// This file takes pest types as input and returns the appropriate work types
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log request for debugging
$log_file = 'debug_log.txt';
file_put_contents($log_file, "Request received at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
file_put_contents($log_file, "POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);

// Get pest types from request
$pest_types = isset($_POST['pest_types']) ? $_POST['pest_types'] : '';
file_put_contents($log_file, "Pest types: " . $pest_types . "\n", FILE_APPEND);

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'work_types' => []
];

// If no pest types provided, return error
if (empty($pest_types)) {
    $response['message'] = 'No pest types provided';
    file_put_contents($log_file, "Error: No pest types provided\n", FILE_APPEND);
    echo json_encode($response);
    exit;
}

// Parse pest types string into array
$pest_list = [];
if (strpos($pest_types, ',') !== false) {
    // Format: "Flies, Ants, Cockroaches"
    $pest_list = array_map('trim', explode(',', $pest_types));
} else if (strpos($pest_types, ' ') !== false && $pest_types !== 'Disinfect Area') {
    // Format: "Flies Ants Cockroaches" but not "Disinfect Area"
    $pest_list = array_filter(explode(' ', $pest_types), function($item) {
        return trim($item) !== '';
    });
} else {
    // Single pest type or "Disinfect Area"
    $pest_list = [trim($pest_types)];
}

// Define our categories
$general_pest_list = ['Flies', 'Ants', 'Cockroaches', 'Bed Bugs', 'Mosquitoes', 'Mice/Rats', 'Termites'];
$rodent_list = ['Mice/Rats'];
$termite_list = ['Termites'];
$grass_list = ['Grass', 'Grass Problems'];
$disinfect_list = ['Disinfect Area', 'Disinfect'];
$other_option = ['Others'];

// Log the pest list for debugging
file_put_contents($log_file, "Parsed pest list: " . print_r($pest_list, true) . "\n", FILE_APPEND);

// Check if "Other" is selected
$has_other = false;
foreach ($pest_list as $pest) {
    $lower_pest = strtolower(trim($pest));
    foreach ($other_option as $other) {
        if ($lower_pest === strtolower(trim($other))) {
            $has_other = true;
            file_put_contents($log_file, "Found 'Other' in pest types\n", FILE_APPEND);
            break 2;
        }
    }
}

// If "Other" is selected, no automation occurs
if ($has_other) {
    $response['success'] = true;
    $response['message'] = 'Manual selection required due to "Other" pest type';
    $response['manual_selection'] = true;
    echo json_encode($response);
    exit;
}

// Initialize flags for each pest category
$has_general_pests = false;
$has_rodent = false;
$has_termite = false;
$has_grass = false;
$has_disinfect = false;
$total_pest_types = 0;

// Check each pest type against our categories
foreach ($pest_list as $pest) {
    $lower_pest = strtolower($pest);
    $pest_found = false;

    // Check for general pests (flies, ants, cockroaches, bed bugs, mosquitoes, mice/rats, termites)
    foreach ($general_pest_list as $general) {
        if (strpos($lower_pest, strtolower($general)) !== false) {
            $has_general_pests = true;
            $pest_found = true;
            file_put_contents($log_file, "Found general pest: $pest\n", FILE_APPEND);

            // Also check if it's specifically a rodent or termite
            if (strpos($lower_pest, 'mice') !== false || strpos($lower_pest, 'rat') !== false) {
                $has_rodent = true;
                file_put_contents($log_file, "This is also a rodent: $pest\n", FILE_APPEND);
            }
            if (strpos($lower_pest, 'termite') !== false) {
                $has_termite = true;
                file_put_contents($log_file, "This is also a termite: $pest\n", FILE_APPEND);
            }

            break;
        }
    }

    // Check for grass problems
    if (!$pest_found) {
        foreach ($grass_list as $grass) {
            if (strpos($lower_pest, strtolower($grass)) !== false) {
                $has_grass = true;
                $pest_found = true;
                file_put_contents($log_file, "Found grass problem: $pest\n", FILE_APPEND);
                break;
            }
        }
    }

    // Check for disinfect area
    if (!$pest_found) {
        // Check for "Disinfect Area" in various formats
        if (strtolower(trim($pest)) === "disinfect area" ||
            strtolower(trim($pest)) === "disinfect" ||
            strpos(strtolower(trim($pest)), "disinfect") !== false) {
            $has_disinfect = true;
            $pest_found = true;
            file_put_contents($log_file, "Found disinfect area: $pest\n", FILE_APPEND);
        } else {
            foreach ($disinfect_list as $disinfect) {
                if (strtolower(trim($pest)) === strtolower(trim($disinfect))) {
                    $has_disinfect = true;
                    $pest_found = true;
                    file_put_contents($log_file, "Found disinfect area: $pest\n", FILE_APPEND);
                    break;
                }
            }
        }
    }

    if ($pest_found) {
        $total_pest_types++;
    }
}

// Log the detection results
file_put_contents($log_file, "Detection results:\n", FILE_APPEND);
file_put_contents($log_file, "- Has general pests: " . ($has_general_pests ? "Yes" : "No") . "\n", FILE_APPEND);
file_put_contents($log_file, "- Has rodent: " . ($has_rodent ? "Yes" : "No") . "\n", FILE_APPEND);
file_put_contents($log_file, "- Has termite: " . ($has_termite ? "Yes" : "No") . "\n", FILE_APPEND);
file_put_contents($log_file, "- Has grass: " . ($has_grass ? "Yes" : "No") . "\n", FILE_APPEND);
file_put_contents($log_file, "- Has disinfect: " . ($has_disinfect ? "Yes" : "No") . "\n", FILE_APPEND);
file_put_contents($log_file, "- Total pest types: " . $total_pest_types . "\n", FILE_APPEND);

// Count how many general pests were selected
$general_pest_count = 0;
if (strpos(strtolower(implode(' ', $pest_list)), 'flies') !== false) $general_pest_count++;
if (strpos(strtolower(implode(' ', $pest_list)), 'ants') !== false) $general_pest_count++;
if (strpos(strtolower(implode(' ', $pest_list)), 'cockroach') !== false) $general_pest_count++;
if (strpos(strtolower(implode(' ', $pest_list)), 'bed bug') !== false) $general_pest_count++;
if (strpos(strtolower(implode(' ', $pest_list)), 'mosquito') !== false) $general_pest_count++;
if ($has_rodent) $general_pest_count++;
if ($has_termite) $general_pest_count++;

file_put_contents($log_file, "General pest count: $general_pest_count\n", FILE_APPEND);

// Apply the logic rules - assign work types based on pest types
$work_types = [];

// Special case handling for combinations with Grass and Disinfect will be done later
$skip_general_pest_rules = false;

// Check if we have the special case of Grass + Disinfect + General Pests
if ($has_grass && $has_disinfect && $general_pest_count > 0) {
    $skip_general_pest_rules = true;
}

// Only apply these rules if we're not in a special case
if (!$skip_general_pest_rules) {
    // Case 1: If at least TWO general pests are selected, only add General Pest Control
    if ($general_pest_count >= 2) {
        $work_types[] = 'General Pest Control';
        file_put_contents($log_file, "Case: At least TWO general pests - Adding ONLY General Pest Control\n", FILE_APPEND);
    }
    // Case 2: If ONLY Mice/Rats is selected
    else if ($has_rodent && $general_pest_count == 1 && !$has_termite && !$has_grass && !$has_disinfect) {
        $work_types[] = 'Rodent Control Only';
        file_put_contents($log_file, "Case: ONLY Mice/Rats - Adding ONLY Rodent Control Only\n", FILE_APPEND);
    }
    // Case 3: If ONLY Termites is selected
    else if ($has_termite && $general_pest_count == 1 && !$has_rodent && !$has_grass && !$has_disinfect) {
        $work_types[] = 'Termite Baiting';
        file_put_contents($log_file, "Case: ONLY Termites - Adding ONLY Termite Baiting\n", FILE_APPEND);
    }
    // Case 4: If ONLY one general pest (not mice/rats or termites) is selected
    else if ($general_pest_count == 1 && !$has_rodent && !$has_termite && !$has_grass && !$has_disinfect) {
        $work_types[] = 'General Pest Control';
        file_put_contents($log_file, "Case: ONLY one general pest - Adding ONLY General Pest Control\n", FILE_APPEND);
    }
}

// Special case 1: If Grass, Disinfect Area, AND general pests are selected
if ($has_grass && $has_disinfect && $general_pest_count > 0) {
    $work_types = ['Weed Control', 'Disinfection', 'General Pest Control'];
    file_put_contents($log_file, "Case: Grass, Disinfect Area, AND general pests - Adding Weed Control, Disinfection, and General Pest Control\n", FILE_APPEND);
}
// Special case 2: If ONLY Grass and Disinfect Area are selected (no general pests)
else if ($has_grass && $has_disinfect && $general_pest_count == 0 && $total_pest_types == 2) {
    $work_types = ['Weed Control', 'Disinfection'];
    file_put_contents($log_file, "Case: ONLY Grass and Disinfect Area - Adding Weed Control and Disinfection\n", FILE_APPEND);
}
// Always add Weed Control if Grass is selected and no general pests (and not already handled by special cases)
else if ($has_grass && $general_pest_count == 0) {
    $work_types[] = 'Weed Control';
    file_put_contents($log_file, "Case: ONLY Grass - Adding ONLY Weed Control\n", FILE_APPEND);
}
// Add Weed Control if Grass is selected with other pests (and not already handled by special cases)
else if ($has_grass && !($has_grass && $has_disinfect)) {
    $work_types[] = 'Weed Control';
    file_put_contents($log_file, "Adding work type: Weed Control\n", FILE_APPEND);
}

// Always add Disinfection if Disinfect is selected and no general pests (and not already handled by special cases)
if ($has_disinfect && $general_pest_count == 0 && !($has_grass && $has_disinfect)) {
    $work_types[] = 'Disinfection';
    file_put_contents($log_file, "Case: ONLY Disinfect - Adding ONLY Disinfection\n", FILE_APPEND);
}
// Add Disinfection if Disinfect is selected with other pests (and not already handled by special cases)
else if ($has_disinfect && !($has_grass && $has_disinfect)) {
    $work_types[] = 'Disinfection';
    file_put_contents($log_file, "Adding work type: Disinfection\n", FILE_APPEND);
}

// Set response
$response['success'] = true;
$response['message'] = 'Work types determined successfully';
$response['work_types'] = $work_types;
$response['debug'] = [
    'pest_list' => $pest_list,
    'has_general_pests' => $has_general_pests,
    'has_rodent' => $has_rodent,
    'has_termite' => $has_termite,
    'has_grass' => $has_grass,
    'has_disinfect' => $has_disinfect,
    'general_pest_count' => $general_pest_count,
    'total_pest_types' => $total_pest_types
];

// Log the response
file_put_contents($log_file, "Response: " . print_r($response, true) . "\n", FILE_APPEND);
file_put_contents($log_file, "JSON Response: " . json_encode($response) . "\n\n", FILE_APPEND);

echo json_encode($response);
?>
