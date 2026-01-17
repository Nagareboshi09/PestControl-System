<?php
session_start();
require_once '../../db_connect.php';
require_once '../../notification_functions.php';
require_once '../../chemical_inventory_functions.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Custom error handler to catch any PHP errors and return them as JSON
function json_error_handler($errno, $errstr, $errfile, $errline) {
    // Log the error
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");

    // Return JSON error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred',
        'errors' => ["PHP Error: $errstr"]
    ]);
    exit;
}

// Set the custom error handler
set_error_handler('json_error_handler', E_ALL & ~E_NOTICE & ~E_WARNING);

// Ensure all output is valid JSON
ob_start(function($buffer) {
    // If the buffer doesn't look like valid JSON, wrap it in a JSON error response
    if (!empty($buffer) && $buffer[0] !== '{' && $buffer[0] !== '[') {
        error_log("Invalid JSON output detected: " . substr($buffer, 0, 200));
        return json_encode([
            'success' => false,
            'message' => 'Invalid server response',
            'errors' => ['Server returned invalid data']
        ]);
    }
    return $buffer;
});

// Check if user is logged in as technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

/**
 * Update chemical inventory quantities based on actual dosage used
 *
 * @param array $chemical_usage Array of chemicals used with dosage information
 * @param object $conn Database connection
 * @param int $job_order_id The job order ID
 * @param int $technician_id The technician ID
 * @param bool $debug_mode Enable additional debug logging
 * @return array Result with success status and messages
 */
function update_chemical_inventory($chemical_usage, $conn, $job_order_id = 0, $technician_id = 0, $debug_mode = false) {
    // Initialize result array
    $result = [
        'success' => true,
        'message' => 'Chemical inventory updated successfully',
        'updated_chemicals' => [],
        'replaced_chemicals' => [], // Track chemicals that were automatically replaced
        'errors' => []
    ];

    // Log the chemical usage data for debugging
    error_log("Chemical usage data received: " . json_encode($chemical_usage));

    // Additional debug logging if debug mode is enabled
    if ($debug_mode) {
        error_log("DEBUG MODE: Chemical inventory update function called with debug mode enabled");
        error_log("Job Order ID: $job_order_id, Technician ID: $technician_id");
        error_log("Chemical usage data count: " . count($chemical_usage));

        // Log each chemical in detail
        foreach ($chemical_usage as $idx => $chem) {
            $id = isset($chem['id']) ? $chem['id'] : 'N/A';
            $name = isset($chem['name']) ? $chem['name'] : 'N/A';
            $type = isset($chem['type']) ? $chem['type'] : 'N/A';
            $dosage = isset($chem['dosage']) ? $chem['dosage'] : '0';
            $unit = isset($chem['dosage_unit']) ? $chem['dosage_unit'] : 'ml';
            $inventory_unit = isset($chem['inventory_unit']) ? $chem['inventory_unit'] : 'ml';

            error_log("Chemical $idx details: ID=$id, Name=$name, Type=$type, Dosage=$dosage $unit, Inventory Unit=$inventory_unit");
        }
    }

    // If no chemical usage data, return early
    if (empty($chemical_usage)) {
        $result['success'] = false;
        $result['message'] = 'No chemical usage data provided';
        return $result;
    }

    // Process each chemical
    foreach ($chemical_usage as $chemical) {
        // Extract chemical data
        $chemical_name = $chemical['name'];

        // Ensure chemical_id is properly handled
        $chemical_id = 0;
        if (isset($chemical['id'])) {
            // Try to convert to integer
            $chemical_id = filter_var($chemical['id'], FILTER_VALIDATE_INT);
            if ($chemical_id === false) {
                // If not a valid integer, try to extract numeric part
                preg_match('/\d+/', $chemical['id'], $matches);
                $chemical_id = !empty($matches) ? intval($matches[0]) : 0;
            }
        }

        // Log the chemical ID for debugging
        error_log("Processing chemical: {$chemical_name}, ID from request: " .
                 (isset($chemical['id']) ? "'{$chemical['id']}'" : "not set") .
                 ", Parsed ID: {$chemical_id}");

        $dosage = floatval($chemical['dosage']);
        $dosage_unit = $chemical['dosage_unit'] ?? 'ml';
        $is_replacement = isset($chemical['is_replacement']) && $chemical['is_replacement'] === true;
        $original_chemical_id = isset($chemical['replacing']) ? intval($chemical['replacing']) : 0;
        $original_chemical_name = isset($chemical['original_chemical_name']) ? $chemical['original_chemical_name'] : '';

        // Skip if dosage is zero or negative
        if ($dosage <= 0) {
            continue;
        }

        // Log replacement information if applicable
        if ($is_replacement) {
            error_log("Processing replacement chemical: $chemical_name (ID: $chemical_id) replacing $original_chemical_name (ID: $original_chemical_id)");
        }

        // Find matching chemical in inventory by ID if provided, otherwise by name
        if ($chemical_id > 0) {
            // Use chemical ID for precise inventory update
            $stmt = $conn->prepare("SELECT id, chemical_name, quantity, unit, expiration_date, type, status FROM chemical_inventory
                                  WHERE id = ?");
            $stmt->bind_param("i", $chemical_id);
            error_log("Looking up chemical by ID: {$chemical_id}");

            $stmt->execute();
            $inventory_result = $stmt->get_result();

            // If no results found, try to find by name as a fallback
            if ($inventory_result->num_rows === 0 && !empty($chemical_name)) {
                error_log("Chemical not found by ID {$chemical_id}, trying by exact name: {$chemical_name}");

                // Try exact name match first
                $stmt = $conn->prepare("SELECT id, chemical_name, quantity, unit, expiration_date, type, status FROM chemical_inventory
                                      WHERE chemical_name = ? AND quantity > 0
                                      ORDER BY expiration_date ASC LIMIT 1");
                $stmt->bind_param("s", $chemical_name);
                $stmt->execute();
                $inventory_result = $stmt->get_result();

                if ($inventory_result->num_rows > 0) {
                    error_log("Found chemical by exact name match: {$chemical_name}");
                    $found_item = $inventory_result->fetch_assoc();
                    error_log("Found chemical: ID={$found_item['id']}, Name={$found_item['chemical_name']}, Quantity={$found_item['quantity']} {$found_item['unit']}");
                    $inventory_result->data_seek(0); // Reset the result pointer
                }
            }

            // Debug logging
            if ($debug_mode) {
                error_log("Chemical lookup by ID $chemical_id result: " . ($inventory_result->num_rows > 0 ? "Found" : "Not found"));
                if ($inventory_result->num_rows > 0) {
                    $debug_item = $inventory_result->fetch_assoc();
                    error_log("Found chemical: ID={$debug_item['id']}, Name={$debug_item['chemical_name']}, Quantity={$debug_item['quantity']} {$debug_item['unit']}, Type={$debug_item['type']}, Status={$debug_item['status']}");
                    // Reset the result pointer
                    $inventory_result->data_seek(0);
                }
            }

            // If not found by ID, try by name as a fallback
            if ($inventory_result->num_rows === 0) {
                error_log("Chemical not found by ID {$chemical_id}, trying by name as fallback");
                // Continue to name-based search below
            } else {
                // Check if the found chemical has quantity > 0
                $inventory_item = $inventory_result->fetch_assoc();
                if ($inventory_item['quantity'] > 0) {
                    error_log("Found chemical by ID with positive quantity: {$inventory_item['chemical_name']} (ID: {$inventory_item['id']})");
                    // Reset the result pointer
                    $inventory_result->data_seek(0);
                    goto process_inventory_result;
                } else {
                    error_log("Chemical found by ID {$chemical_id} but has zero quantity, trying by name as fallback");
                    // Continue to name-based search below
                }
            }
        }

        // Get chemical type if available
        $chemical_type = isset($chemical['type']) ? $chemical['type'] : '';
        $found_by_name_and_type = false;

        // Try first with name and type for precise matching
        if (!empty($chemical_type)) {
            // First try exact match
            $stmt = $conn->prepare("SELECT id, chemical_name, quantity, unit, expiration_date FROM chemical_inventory
                                  WHERE chemical_name = ? AND type = ? AND quantity > 0
                                  ORDER BY expiration_date ASC LIMIT 1");
            $stmt->bind_param("ss", $chemical_name, $chemical_type);
            error_log("Looking up chemical by exact name and type: {$chemical_name}, Type: {$chemical_type}");

            $stmt->execute();
            $inventory_result = $stmt->get_result();

            // If exact match found, process it
            if ($inventory_result->num_rows > 0) {
                error_log("Found chemical by exact name and type match");
                $found_by_name_and_type = true;
                goto process_inventory_result;
            }

            // If exact match not found, try with LIKE for name
            $stmt = $conn->prepare("SELECT id, chemical_name, quantity, unit, expiration_date FROM chemical_inventory
                                  WHERE LOWER(chemical_name) LIKE LOWER(?) AND type = ? AND quantity > 0
                                  ORDER BY expiration_date ASC LIMIT 1");
            $search_term = "%{$chemical_name}%";
            $stmt->bind_param("ss", $search_term, $chemical_type);
            error_log("Looking up chemical by similar name and type: {$chemical_name}, Type: {$chemical_type}");

            $stmt->execute();
            $inventory_result = $stmt->get_result();

            // If found by name LIKE and type, process it
            if ($inventory_result->num_rows > 0) {
                error_log("Found chemical by similar name and type match");
                $found_by_name_and_type = true;
                goto process_inventory_result;
            }

            // Try with SOUNDEX for phonetic matching (helps with spelling variations)
            $stmt = $conn->prepare("SELECT id, chemical_name, quantity, unit, expiration_date FROM chemical_inventory
                                  WHERE SOUNDEX(chemical_name) = SOUNDEX(?) AND type = ? AND quantity > 0
                                  ORDER BY expiration_date ASC LIMIT 1");
            $stmt->bind_param("ss", $chemical_name, $chemical_type);
            error_log("Looking up chemical by SOUNDEX name and type: {$chemical_name}, Type: {$chemical_type}");

            $stmt->execute();
            $inventory_result = $stmt->get_result();

            // If SOUNDEX match found, process it
            if ($inventory_result->num_rows > 0) {
                error_log("Found chemical by SOUNDEX name and type match");
                $found_by_name_and_type = true;
                goto process_inventory_result;
            }
        }

        // If not found by name and type, or if type is not available, try by name only
        if (!$found_by_name_and_type) {
            // Special handling for known problematic chemicals
            $special_case = false;

            // Check for Cypermethrin with different spellings
            if (strtolower($chemical_name) == 'cypermetrin' ||
                strtolower($chemical_name) == 'cypermethrin' ||
                strtolower($chemical_name) == 'cypermethrine') {

                $stmt = $conn->prepare("SELECT id, chemical_name, quantity, unit, expiration_date FROM chemical_inventory
                                      WHERE LOWER(chemical_name) LIKE '%cyper%' AND quantity > 0
                                      ORDER BY expiration_date ASC LIMIT 1");
                $stmt->execute();
                $inventory_result = $stmt->get_result();

                if ($inventory_result->num_rows > 0) {
                    error_log("Found Cypermethrin using special case handling");
                    $special_case = true;
                    goto process_inventory_result;
                }
            }

            // Check for Imidacloprid with different spellings
            if (strtolower($chemical_name) == 'imidacloprid' ||
                strtolower($chemical_name) == 'imidachloprid' ||
                strtolower($chemical_name) == 'imidaclopryd') {

                $stmt = $conn->prepare("SELECT id, chemical_name, quantity, unit, expiration_date FROM chemical_inventory
                                      WHERE LOWER(chemical_name) LIKE '%imida%' AND quantity > 0
                                      ORDER BY expiration_date ASC LIMIT 1");
                $stmt->execute();
                $inventory_result = $stmt->get_result();

                if ($inventory_result->num_rows > 0) {
                    error_log("Found Imidacloprid using special case handling");
                    $special_case = true;
                    goto process_inventory_result;
                }
            }

            // If no special case or special case didn't find a match, proceed with normal search
            if (!$special_case) {
                // First try exact name match
                $stmt = $conn->prepare("SELECT id, chemical_name, quantity, unit, expiration_date FROM chemical_inventory
                                      WHERE chemical_name = ? AND quantity > 0
                                      ORDER BY expiration_date ASC LIMIT 1");
                $stmt->bind_param("s", $chemical_name);
                error_log("Looking up chemical by exact name only: {$chemical_name}");

                $stmt->execute();
                $inventory_result = $stmt->get_result();

                // If exact name match not found, try with LIKE
                if ($inventory_result->num_rows === 0) {
                    $stmt = $conn->prepare("SELECT id, chemical_name, quantity, unit, expiration_date FROM chemical_inventory
                                          WHERE LOWER(chemical_name) LIKE LOWER(?) AND quantity > 0
                                          ORDER BY expiration_date ASC LIMIT 1");
                    $search_term = "%{$chemical_name}%";
                    $stmt->bind_param("s", $search_term);
                    error_log("Looking up chemical by similar name only: {$chemical_name}");

                    $stmt->execute();
                    $inventory_result = $stmt->get_result();

                    // If LIKE search didn't find anything, try SOUNDEX
                    if ($inventory_result->num_rows === 0) {
                        $stmt = $conn->prepare("SELECT id, chemical_name, quantity, unit, expiration_date FROM chemical_inventory
                                              WHERE SOUNDEX(chemical_name) = SOUNDEX(?) AND quantity > 0
                                              ORDER BY expiration_date ASC LIMIT 1");
                        $stmt->bind_param("s", $chemical_name);
                        error_log("Looking up chemical by SOUNDEX name only: {$chemical_name}");

                        $stmt->execute();
                        $inventory_result = $stmt->get_result();

                        if ($inventory_result->num_rows > 0) {
                            error_log("Found chemical by SOUNDEX name match");
                        }
                    }
                }
            }
        }

        // Label for processing the inventory result
        process_inventory_result:

        if ($inventory_result->num_rows === 0) {
            // Get chemical type for error message
            $chemical_type = isset($chemical['type']) ? $chemical['type'] : 'N/A';
            $error_msg = "Chemical not found in inventory: $chemical_name (Type: $chemical_type)";
            $result['errors'][] = $error_msg;
            error_log($error_msg);

            // Log available chemicals for debugging
            error_log("Searching for similar chemicals in inventory...");
            $debug_stmt = $conn->prepare("SELECT id, chemical_name, type, quantity, unit FROM chemical_inventory WHERE quantity > 0");
            $debug_stmt->execute();
            $debug_result = $debug_stmt->get_result();

            $found_similar = false;
            while ($row = $debug_result->fetch_assoc()) {
                // Check for similar chemical names (case-insensitive)
                if (stripos($row['chemical_name'], $chemical_name) !== false ||
                    stripos($chemical_name, $row['chemical_name']) !== false) {
                    error_log("Possible match found: ID={$row['id']}, Name={$row['chemical_name']}, Type={$row['type']}, Quantity={$row['quantity']} {$row['unit']}");
                    $found_similar = true;
                }
            }

            if (!$found_similar) {
                error_log("No similar chemicals found in inventory");
            }

            continue;
        }

        $inventory_item = $inventory_result->fetch_assoc();
        $chemical_name = $inventory_item['chemical_name']; // Use the actual name from the database

        // Get current quantity and inventory unit
        $current_quantity = floatval($inventory_item['quantity']);
        $inventory_unit = $inventory_item['unit'];

        // Use the provided inventory unit if available, otherwise use the one from the database
        $expected_inventory_unit = isset($chemical['inventory_unit']) ? $chemical['inventory_unit'] : $inventory_unit;

        // Log the units for debugging
        error_log("Chemical: $chemical_name, Dosage: $dosage $dosage_unit, Inventory Unit: $inventory_unit, Expected Unit: $expected_inventory_unit");

        // Convert dosage to inventory unit if needed
        $converted_dosage = $dosage;

        // Handle unit conversion
        if ($dosage_unit !== $inventory_unit) {
            // Standardize unit names for comparison
            $dosage_unit_normalized = strtolower($dosage_unit);
            $inventory_unit_normalized = strtolower($inventory_unit);

            error_log("Normalized units for comparison: dosage_unit=$dosage_unit_normalized, inventory_unit=$inventory_unit_normalized");

            // Convert ml to L or L to ml
            if (($dosage_unit_normalized === 'ml' || $dosage_unit_normalized === 'milliliters') &&
                ($inventory_unit_normalized === 'l' || $inventory_unit_normalized === 'liters' || $inventory_unit_normalized === 'liter')) {
                $converted_dosage = $dosage / 1000;
                error_log("Converting $dosage ml to $converted_dosage L");
            } else if (($dosage_unit_normalized === 'l' || $dosage_unit_normalized === 'liters' || $dosage_unit_normalized === 'liter') &&
                       ($inventory_unit_normalized === 'ml' || $inventory_unit_normalized === 'milliliters')) {
                $converted_dosage = $dosage * 1000;
                error_log("Converting $dosage L to $converted_dosage ml");
            }
            // Convert g to kg or kg to g
            else if (($dosage_unit_normalized === 'g' || $dosage_unit_normalized === 'grams') &&
                     ($inventory_unit_normalized === 'kg' || $inventory_unit_normalized === 'kilograms')) {
                $converted_dosage = $dosage / 1000;
                error_log("Converting $dosage g to $converted_dosage kg");
            } else if (($dosage_unit_normalized === 'kg' || $dosage_unit_normalized === 'kilograms') &&
                       ($inventory_unit_normalized === 'g' || $dosage_unit_normalized === 'grams')) {
                $converted_dosage = $dosage * 1000;
                error_log("Converting $dosage kg to $converted_dosage g");
            }
            // If units are the same after normalization, no conversion needed
            else if ($dosage_unit_normalized === $inventory_unit_normalized) {
                error_log("No conversion needed, units are the same after normalization: $dosage_unit_normalized");
            }
            else {
                $warning_msg = "Warning: Unit conversion not supported from $dosage_unit ($dosage_unit_normalized) to $inventory_unit ($inventory_unit_normalized) for $chemical_name";
                $result['errors'][] = $warning_msg;
                error_log($warning_msg);
            }
        }

        // Skip update if dosage is zero or negative
        if ($converted_dosage <= 0) {
            $warning_msg = "Warning: Skipping inventory update for $chemical_name because dosage is zero or negative";
            $result['errors'][] = $warning_msg;
            error_log($warning_msg);
            continue;
        }

        // Calculate new quantity
        $new_quantity = $current_quantity - $converted_dosage;
        error_log("Calculated new quantity: Current: $current_quantity, Used: $converted_dosage, New: $new_quantity $inventory_unit");

        // Ensure quantity doesn't go below zero
        if ($new_quantity < 0) {
            $new_quantity = 0;
            $warning_msg = "Warning: $chemical_name quantity reduced to 0 (tried to subtract more than available)";
            $result['errors'][] = $warning_msg;
            error_log($warning_msg);
        }

        // Start a transaction to ensure both inventory update and logging happen together
        $conn->begin_transaction();

        try {
            // Debug logging before update
            if ($debug_mode) {
                error_log("DEBUG: About to update chemical inventory with the following data:");
                error_log("Chemical ID: {$inventory_item['id']}, Name: {$inventory_item['chemical_name']}");
                error_log("Current quantity: {$current_quantity} {$inventory_unit}, New quantity: {$new_quantity} {$inventory_unit}");
                error_log("Dosage used: {$converted_dosage} {$inventory_unit}");

                // Check if the chemical exists in the database
                $check_stmt = $conn->prepare("SELECT id, chemical_name, quantity FROM chemical_inventory WHERE id = ?");
                $check_stmt->bind_param("i", $inventory_item['id']);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows > 0) {
                    $check_item = $check_result->fetch_assoc();
                    error_log("Chemical found in database: ID={$check_item['id']}, Name={$check_item['chemical_name']}, Current Quantity={$check_item['quantity']}");
                } else {
                    error_log("WARNING: Chemical ID {$inventory_item['id']} not found in database before update!");
                }
            }

            // Log the query parameters
            error_log("Updating chemical inventory: ID={$inventory_item['id']}, New Quantity=$new_quantity");

            // Use the unified function to update the inventory
            $update_result = update_chemical_inventory_quantity($conn, $inventory_item['id'], $new_quantity, "mysqli");

            // Log the result
            error_log("Update result: " . ($update_result ? "Success" : "Failed"));

            // Always verify the update was successful
            $verify_stmt = $conn->prepare("SELECT id, chemical_name, quantity, unit FROM chemical_inventory WHERE id = ?");
            $verify_stmt->bind_param("i", $inventory_item['id']);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();

            if ($verify_result->num_rows > 0) {
                $updated_item = $verify_result->fetch_assoc();
                error_log("Verified updated chemical: ID={$updated_item['id']}, Name={$updated_item['chemical_name']}, New Quantity={$updated_item['quantity']} {$updated_item['unit']}");

                // Check if the update was successful
                if (abs($updated_item['quantity'] - $new_quantity) > 0.001) {
                    error_log("WARNING: Updated quantity ({$updated_item['quantity']}) does not match expected quantity ($new_quantity)");
                    // Add a warning to the result
                    $result['errors'][] = "Warning: Updated quantity ({$updated_item['quantity']}) does not match expected quantity ($new_quantity) for $chemical_name";
                }
            } else {
                error_log("ERROR: Could not verify update for chemical ID {$inventory_item['id']}");
                throw new Exception("Failed to verify chemical inventory update for $chemical_name (ID: {$inventory_item['id']}). Chemical not found after update.");
            }

            // Debug logging after update
            if ($debug_mode) {
                // Additional detailed logging for debugging
                error_log("DEBUG: Chemical inventory update details:");
                error_log("Chemical ID: {$inventory_item['id']}, Name: {$chemical_name}");
                error_log("Previous quantity: $current_quantity, Used: $converted_dosage, New: $new_quantity");
                error_log("Database quantity after update: {$updated_item['quantity']}");
            }

            if (!$update_result) {
                throw new Exception("Failed to execute update query for chemical inventory for $chemical_name (ID: {$inventory_item['id']}). Error: " . $conn->error);
            }

            // Check if the quantity was updated correctly
            $check_stmt = $conn->prepare("SELECT quantity FROM chemical_inventory WHERE id = ?");
            $check_stmt->bind_param("i", $inventory_item['id']);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows === 0) {
                throw new Exception("Chemical with ID {$inventory_item['id']} not found in database");
            }

            $current_db_quantity = $check_result->fetch_assoc()['quantity'];

            if (abs($current_db_quantity - $new_quantity) < 0.001) {
                // Quantity is correct, no error
                error_log("Quantity is now $current_db_quantity for chemical ID {$inventory_item['id']}");
            } else {
                throw new Exception("Failed to update chemical inventory for $chemical_name (ID: {$inventory_item['id']}). Current DB quantity: $current_db_quantity, New quantity: $new_quantity");
            }

            // Log the chemical usage using the unified function
            $notes = "Used for job order #$job_order_id";
            if ($is_replacement) {
                $notes .= " (Replacement for {$original_chemical_name})";
            }

            $log_result = log_chemical_usage(
                $conn,
                $inventory_item['id'],
                $technician_id,
                $job_order_id,
                $converted_dosage,
                $notes,
                "mysqli"
            );

            if (!$log_result) {
                throw new Exception("Failed to log chemical usage for $chemical_name (ID: {$inventory_item['id']}). Error: " . $conn->error);
            }

            // Commit the transaction
            $conn->commit();

        } catch (Exception $e) {
            // Rollback the transaction on error
            $conn->rollback();
            $error_msg = $e->getMessage();
            $result['errors'][] = $error_msg;
            error_log($error_msg);
            continue;
        }

        // Add to updated chemicals list
        $result['updated_chemicals'][] = [
            'id' => $inventory_item['id'],
            'name' => $chemical_name,
            'type' => isset($chemical['type']) ? $chemical['type'] : '',
            'previous_quantity' => $current_quantity,
            'used_quantity' => $converted_dosage,
            'new_quantity' => $new_quantity,
            'unit' => $inventory_unit,
            'is_replacement' => $is_replacement,
            'original_chemical_id' => $original_chemical_id,
            'original_chemical_name' => $original_chemical_name,
            'status' => $new_quantity <= 0 ? 'Out of Stock' : ($new_quantity < 10 ? 'Low Stock' : 'In Stock')
        ];

        // If this is a replacement chemical, add it to the replaced_chemicals list
        if ($is_replacement) {
            $result['replaced_chemicals'][] = [
                'id' => $inventory_item['id'],
                'name' => $chemical_name,
                'replacement_quantity' => $converted_dosage,
                'unit' => $inventory_unit,
                'expiration_date' => $inventory_item['expiration_date'],
                'original_chemical_id' => $original_chemical_id,
                'original_chemical_name' => $original_chemical_name
            ];
        }

        // Update status based on new quantity
        $status_update_needed = false;
        $new_status = '';

        if ($new_quantity <= 0) {
            $new_status = 'Out of Stock';
            $status_update_needed = true;
        } else if ($new_quantity <= 5) { // Threshold for low stock
            $new_status = 'Low Stock';
            $status_update_needed = true;
        }

        if ($status_update_needed) {
            $status_stmt = $conn->prepare("UPDATE chemical_inventory SET status = ? WHERE id = ?");
            $status_stmt->bind_param("si", $new_status, $inventory_item['id']);
            $status_stmt->execute();
            error_log("Updated status for chemical ID {$inventory_item['id']} to $new_status");
        }
    }

    // If there were any errors, but we still updated some chemicals, partial success
    if (!empty($result['errors']) && !empty($result['updated_chemicals'])) {
        $result['success'] = true;
        $result['message'] = 'Chemical inventory partially updated with some errors';
    } else if (empty($result['updated_chemicals'])) {
        $result['success'] = false;
        $result['message'] = 'No chemicals were updated';
    }

    return $result;
}

// Create job_order_report table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS job_order_report (
    id INT(11) NOT NULL AUTO_INCREMENT,
    job_order_id INT(11) NOT NULL,
    technician_id INT(11) NOT NULL,
    observation_notes TEXT NOT NULL,
    recommendation TEXT NOT NULL,
    attachments VARCHAR(255) DEFAULT NULL,
    chemical_usage TEXT DEFAULT NULL,
    payment_proof TEXT DEFAULT NULL,
    id_attachments VARCHAR(255) DEFAULT NULL,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (job_order_id) REFERENCES job_order(job_order_id),
    FOREIGN KEY (technician_id) REFERENCES technicians(technician_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$conn->query($createTableSQL);

// Add status column to job_order table if it doesn't exist
$checkStatusColumn = $conn->query("SHOW COLUMNS FROM job_order LIKE 'status'");
if ($checkStatusColumn->num_rows == 0) {
    $conn->query("ALTER TABLE job_order ADD COLUMN status VARCHAR(20) DEFAULT 'scheduled'");
}

// Add payment_proof column to job_order_report table if it doesn't exist
$checkPaymentProofColumn = $conn->query("SHOW COLUMNS FROM job_order_report LIKE 'payment_proof'");
if ($checkPaymentProofColumn->num_rows == 0) {
    $conn->query("ALTER TABLE job_order_report ADD COLUMN payment_proof TEXT DEFAULT NULL");
}

// Add id_attachments column to job_order_report table if it doesn't exist
$checkIdAttachmentsColumn = $conn->query("SHOW COLUMNS FROM job_order_report LIKE 'id_attachments'");
if ($checkIdAttachmentsColumn->num_rows == 0) {
    $conn->query("ALTER TABLE job_order_report ADD COLUMN id_attachments VARCHAR(255) DEFAULT NULL");
}

// Handle POST request for creating a job order report
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Extract and validate required fields
    $job_order_id = isset($_POST['job_order_id']) ? intval($_POST['job_order_id']) : 0;
    $technician_id = $_SESSION['user_id'];
    $observation_notes = isset($_POST['observation_notes']) ? trim($_POST['observation_notes']) : '';
    $recommendation = isset($_POST['recommendation']) ? trim($_POST['recommendation']) : '';
    $payment_proof = isset($_POST['payment_proof']) ? trim($_POST['payment_proof']) : '';

    // Check if job order is older than 30 days
    $jobOrderQuery = "SELECT preferred_date FROM job_order WHERE job_order_id = ?";
    $stmt = $conn->prepare($jobOrderQuery);
    $stmt->bind_param("i", $job_order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $isOlderThan30Days = false;

    if ($result->num_rows > 0) {
        $jobOrder = $result->fetch_assoc();
        $jobDate = new DateTime($jobOrder['preferred_date']);
        $today = new DateTime();
        $daysDifference = $today->diff($jobDate)->days;
        $isOlderThan30Days = $daysDifference > 30;
    }

    // Process chemical usage data if available
    $chemical_usage = null;
    $chemical_usage_array = null;

    // Check if debug flag is set
    $debug_chemical_usage = isset($_POST['debug_chemical_usage']) && $_POST['debug_chemical_usage'] == '1';
    if ($debug_chemical_usage) {
        error_log("DEBUG MODE: Chemical usage debugging enabled");
        // Log all POST data for debugging
        error_log("POST data keys: " . implode(", ", array_keys($_POST)));
    }

    if (isset($_POST['chemical_usage']) && !empty($_POST['chemical_usage'])) {
        // Use the chemical_usage JSON data directly from the form
        $chemical_usage = $_POST['chemical_usage'];
        error_log("Using chemical_usage JSON data directly from form: " . substr($chemical_usage, 0, 200) . "...");

        // Validate JSON format
        $test_decode = json_decode($chemical_usage, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ERROR: Invalid JSON format in chemical_usage: " . json_last_error_msg());
            error_log("Raw chemical_usage data: " . $chemical_usage);

            // Try to sanitize the JSON string
            $sanitized_json = preg_replace('/[\x00-\x1F\x7F]/', '', $chemical_usage);
            $sanitized_json = str_replace("'", '"', $sanitized_json);

            // Try to decode the sanitized JSON
            $test_decode = json_decode($sanitized_json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                error_log("Successfully sanitized JSON. Found " . count($test_decode) . " chemicals");
                $chemical_usage = $sanitized_json;
            } else {
                error_log("Failed to sanitize JSON: " . json_last_error_msg());
            }

            // Try to fix common JSON issues
            $fixed_json = preg_replace('/[\x00-\x1F\x7F]/', '', $chemical_usage);
            $fixed_json = str_replace("'", '"', $fixed_json);

            // Try to extract valid JSON
            if (preg_match('/(\[.*\])/', $fixed_json, $matches)) {
                $extracted_json = $matches[0];
                $test_decode = json_decode($extracted_json, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    error_log("Successfully fixed and extracted JSON. Found " . count($test_decode) . " chemicals");
                    $chemical_usage = $extracted_json;
                } else {
                    error_log("Failed to fix JSON: " . json_last_error_msg());
                }
            }
        } else {
            error_log("Valid JSON format in chemical_usage. Found " . count($test_decode) . " chemicals");

            // Log each chemical for debugging
            foreach ($test_decode as $idx => $chem) {
                $id = isset($chem['id']) ? $chem['id'] : 'N/A';
                $name = isset($chem['name']) ? $chem['name'] : 'N/A';
                $dosage = isset($chem['dosage']) ? $chem['dosage'] : '0';
                error_log("Chemical $idx: ID=$id, Name=$name, Dosage=$dosage");

                // Ensure ID is properly formatted
                if (isset($chem['id']) && !is_numeric($chem['id'])) {
                    error_log("Chemical $idx has non-numeric ID: {$chem['id']}. Attempting to fix.");

                    // Try to extract numeric part
                    preg_match('/\d+/', $chem['id'], $matches);
                    if (!empty($matches)) {
                        $test_decode[$idx]['id'] = intval($matches[0]);
                        error_log("Fixed ID to: {$test_decode[$idx]['id']}");
                    }
                }
            }

            // Update chemical_usage with fixed data
            $chemical_usage = json_encode($test_decode);
        }
    } else if (isset($_POST['chemical_name']) && is_array($_POST['chemical_name']) &&
        isset($_POST['chemical_dosage']) && is_array($_POST['chemical_dosage'])) {
        // Fallback to processing individual form fields
        error_log("Falling back to processing individual chemical form fields");

        // Log the chemical names and dosages for debugging
        if ($debug_chemical_usage) {
            foreach ($_POST['chemical_name'] as $idx => $name) {
                $dosage = isset($_POST['chemical_dosage'][$idx]) ? $_POST['chemical_dosage'][$idx] : 'N/A';
                $id = isset($_POST['chemical_id'][$idx]) ? $_POST['chemical_id'][$idx] : 'N/A';
                error_log("Individual field - Chemical $idx: ID=$id, Name=$name, Dosage=$dosage");
            }
        }

        $chemicals = [];
        $count = count($_POST['chemical_name']);

        for ($i = 0; $i < $count; $i++) {
            if (isset($_POST['chemical_name'][$i]) && isset($_POST['chemical_dosage'][$i])) {
                // Ensure dosage is a valid positive number
                $dosage = $_POST['chemical_dosage'][$i];

                // Remove any non-numeric characters except decimal point
                $dosage = preg_replace('/[^\d.]/', '', $dosage);

                // Ensure there's only one decimal point
                $parts = explode('.', $dosage);
                if (count($parts) > 2) {
                    $dosage = $parts[0] . '.' . implode('', array_slice($parts, 1));
                }

                // Convert to float
                $dosage = floatval($dosage);

                // If negative or NaN, set to 0
                if ($dosage < 0 || is_nan($dosage)) {
                    $dosage = 0;
                }

                // Get recommended dosage
                $recommended_dosage = isset($_POST['chemical_recommended_dosage'][$i]) ?
                    floatval($_POST['chemical_recommended_dosage'][$i]) : 0;

                // Check if this is a replacement chemical
                $is_replacement = isset($_POST['is_replacement'][$i]) && $_POST['is_replacement'][$i] == '1';
                $replacing_id = isset($_POST['replacing'][$i]) ? $_POST['replacing'][$i] : null;
                $original_chemical_name = isset($_POST['original_chemical_name'][$i]) ? $_POST['original_chemical_name'][$i] : null;

                // Build the chemical data
                $chemical_data = [
                    'id' => isset($_POST['chemical_id'][$i]) ? $_POST['chemical_id'][$i] : '',
                    'name' => $_POST['chemical_name'][$i],
                    'type' => isset($_POST['chemical_type'][$i]) ? $_POST['chemical_type'][$i] : '',
                    'target_pest' => isset($_POST['chemical_target_pest'][$i]) ? $_POST['chemical_target_pest'][$i] : '',
                    'dosage' => $dosage,
                    'recommended_dosage' => $recommended_dosage,
                    'dosage_unit' => isset($_POST['chemical_dosage_unit'][$i]) ? $_POST['chemical_dosage_unit'][$i] : 'ml',
                    'inventory_unit' => isset($_POST['chemical_inventory_unit'][$i]) ? $_POST['chemical_inventory_unit'][$i] : 'ml'
                ];

                // Add replacement information if applicable
                if ($is_replacement) {
                    $chemical_data['is_replacement'] = true;
                    $chemical_data['replacing'] = $replacing_id;
                    $chemical_data['original_chemical_name'] = $original_chemical_name;
                    error_log("Processing replacement chemical: {$_POST['chemical_name'][$i]} replacing $original_chemical_name (ID: $replacing_id)");
                }

                // Add to chemicals array
                $chemicals[] = $chemical_data;
            }
        }

        // Convert to JSON
        if (!empty($chemicals)) {
            // Sanitize data before encoding to prevent JSON errors
            foreach ($chemicals as &$chem) {
                foreach ($chem as $key => $value) {
                    if (is_string($value)) {
                        // Remove any non-printable characters that might cause JSON issues
                        $chem[$key] = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $value);
                    }
                }
            }

            // Ensure proper JSON encoding with options to handle special characters
            $chemical_usage = json_encode($chemicals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);

            // Check if encoding was successful
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON encoding error during initial encoding: " . json_last_error_msg());

                // Try a more aggressive sanitization approach
                foreach ($chemicals as &$chem) {
                    foreach ($chem as $key => $value) {
                        if (is_string($value)) {
                            // More aggressive sanitization - only keep alphanumeric, spaces and basic punctuation
                            $chem[$key] = preg_replace('/[^\p{L}\p{N}\s\-_.,]/u', '', $value);
                        }
                    }
                }

                // Try encoding again
                $chemical_usage = json_encode($chemicals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
            }
        }
    }

    // Check for JSON encoding errors if we have chemical usage data
    if ($chemical_usage) {
        $test_decode = json_decode($chemical_usage, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON encoding error: " . json_last_error_msg() . " when encoding chemicals data");

            // Try to sanitize the data if we have the original chemicals array
            if (isset($chemicals) && is_array($chemicals)) {
                // Try to sanitize the data before encoding
                foreach ($chemicals as &$chem) {
                    foreach ($chem as $key => $value) {
                        if (is_string($value)) {
                            // Remove any non-printable characters
                            $chem[$key] = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
                        }
                    }
                }

                // Try encoding again after sanitization
                $chemical_usage = json_encode($chemicals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);

                // If still failing, use a more aggressive approach
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("JSON encoding still failing after sanitization: " . json_last_error_msg());

                    // Create a simplified version of the chemicals array with only essential data
                    $simplified_chemicals = [];
                    foreach ($chemicals as $chem) {
                        $simplified_chemicals[] = [
                            'name' => isset($chem['name']) ? substr(preg_replace('/[^\p{L}\p{N}\s\-_.,]/u', '', $chem['name']), 0, 255) : '',
                            'type' => isset($chem['type']) ? substr(preg_replace('/[^\p{L}\p{N}\s\-_.,]/u', '', $chem['type']), 0, 100) : '',
                            'target_pest' => isset($chem['target_pest']) ? substr(preg_replace('/[^\p{L}\p{N}\s\-_.,]/u', '', $chem['target_pest']), 0, 100) : '',
                            'dosage' => isset($chem['dosage']) ? (float)$chem['dosage'] : 0,
                            'recommended_dosage' => isset($chem['recommended_dosage']) ? (float)$chem['recommended_dosage'] : 0,
                            'dosage_unit' => isset($chem['dosage_unit']) ? substr(preg_replace('/[^\p{L}\p{N}\s\-_.,]/u', '', $chem['dosage_unit']), 0, 10) : 'ml'
                        ];
                    }

                    $chemical_usage = json_encode($simplified_chemicals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
                }
            }
        }
    }
    // Validate required fields
    $errors = [];
    if ($job_order_id <= 0) {
        $errors[] = 'Invalid job order ID';
    }
    if (empty($observation_notes)) {
        $errors[] = 'Observation notes are required';
    }
    if (empty($recommendation)) {
        $errors[] = 'Recommendation is required';
    }

    // Only validate payment proof if job is less than 30 days old
    if (!$isOlderThan30Days && empty($payment_proof)) {
        $errors[] = 'Payment proof is required for jobs less than 30 days old';
    }

    // Check if the technician is the primary technician for this job order
    $checkPrimaryStmt = $conn->prepare("SELECT is_primary FROM job_order_technicians
                                      WHERE job_order_id = ? AND technician_id = ?");
    $checkPrimaryStmt->bind_param("ii", $job_order_id, $technician_id);
    $checkPrimaryStmt->execute();
    $checkPrimaryResult = $checkPrimaryStmt->get_result();

    if ($checkPrimaryResult->num_rows === 0) {
        $errors[] = 'You are not assigned to this job order';
    } else {
        $primaryRow = $checkPrimaryResult->fetch_assoc();
        if (!(bool)$primaryRow['is_primary']) {
            $errors[] = 'Only the primary technician can submit reports for this job order';
        }
    }

    // If there are validation errors, return them
    if (!empty($errors)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
        exit;
    }

    // Handle file uploads
    $attachments = [];
    if (empty($_FILES['attachments']) || empty($_FILES['attachments']['name'][0])) {
        $errors[] = 'At least one attachment is required';
    } else {
        $uploadDir = '../../uploads/';

        // Create uploads directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['attachments']['error'][$key] === 0) {
                $fileName = uniqid() . '_' . basename($_FILES['attachments']['name'][$key]);
                $targetPath = $uploadDir . $fileName;

                // Check file type (allow only images)
                $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
                if (!in_array($fileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $errors[] = 'Only JPG, JPEG, PNG & GIF files are allowed';
                    continue;
                }

                // Check file size (max 5MB)
                if ($_FILES['attachments']['size'][$key] > 5000000) {
                    $errors[] = 'File size should not exceed 5MB';
                    continue;
                }

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $attachments[] = $fileName;
                } else {
                    $errors[] = 'Failed to upload file: ' . $_FILES['attachments']['name'][$key];
                }
            }
        }

        // Check if at least one attachment was successfully uploaded
        if (empty($attachments)) {
            $errors[] = 'Failed to upload any attachments. Please try again.';
        }
    }

    // Handle ID attachments
    $idAttachments = [];
    if (!$isOlderThan30Days && (empty($_FILES['id_attachments']) || empty($_FILES['id_attachments']['name'][0]))) {
        $errors[] = 'ID attachments are required for jobs less than 30 days old';
    } else if (!empty($_FILES['id_attachments']) && !empty($_FILES['id_attachments']['name'][0])) {
        $uploadDir = '../../uploads/ids/';

        // Create uploads/ids directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Check if exactly 2 ID attachments are provided for jobs less than 30 days old
        if (!$isOlderThan30Days && count($_FILES['id_attachments']['name']) !== 2) {
            $errors[] = 'Exactly 2 ID attachments are required for jobs less than 30 days old';
        } else {
            foreach ($_FILES['id_attachments']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['id_attachments']['error'][$key] === 0) {
                    $fileName = 'id_' . uniqid() . '_' . basename($_FILES['id_attachments']['name'][$key]);
                    $targetPath = $uploadDir . $fileName;

                    // Check file type (allow only images)
                    $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
                    if (!in_array($fileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $errors[] = 'Only JPG, JPEG, PNG & GIF files are allowed for ID attachments';
                        continue;
                    }

                    // Check file size (max 5MB)
                    if ($_FILES['id_attachments']['size'][$key] > 5000000) {
                        $errors[] = 'ID attachment file size should not exceed 5MB';
                        continue;
                    }

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $idAttachments[] = $fileName;
                    } else {
                        $errors[] = 'Failed to upload ID file: ' . $_FILES['id_attachments']['name'][$key];
                    }
                }
            }

            // Check if required ID attachments were successfully uploaded
            if (!$isOlderThan30Days && count($idAttachments) !== 2) {
                $errors[] = 'Failed to upload both ID attachments. Please try again.';
            }
        }
    }

    // If there are file upload errors, return them
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => 'File upload failed', 'errors' => $errors]);
        exit;
    }

    $attachmentsStr = implode(',', $attachments);
    $idAttachmentsStr = implode(',', $idAttachments);

    // Start transaction to ensure data integrity
    $conn->begin_transaction();

    try {
        // Check if a report already exists for this job order
        $checkExistingReport = $conn->prepare("SELECT report_id FROM job_order_report WHERE job_order_id = ?");
        $checkExistingReport->bind_param("i", $job_order_id);
        $checkExistingReport->execute();
        $existingResult = $checkExistingReport->get_result();

        if ($existingResult->num_rows > 0) {
            // A report already exists for this job order
            throw new Exception('A report has already been submitted for this job order. Refresh the page to see the updated status.');
        }

        // Check if columns exist
        $result = $conn->query("SHOW COLUMNS FROM job_order_report LIKE 'recommendation'");
        $recommendationExists = $result->num_rows > 0;

        $result = $conn->query("SHOW COLUMNS FROM job_order_report LIKE 'chemical_usage'");
        $chemicalUsageExists = $result->num_rows > 0;

        $result = $conn->query("SHOW COLUMNS FROM job_order_report LIKE 'payment_proof'");
        $paymentProofExists = $result->num_rows > 0;

        $result = $conn->query("SHOW COLUMNS FROM job_order_report LIKE 'id_attachments'");
        $idAttachmentsExists = $result->num_rows > 0;

        // Insert job order report based on column existence
        if ($recommendationExists && $chemicalUsageExists && $paymentProofExists && $idAttachmentsExists) {
            $stmt = $conn->prepare("
                INSERT INTO job_order_report
                (job_order_id, technician_id, observation_notes, recommendation, attachments, chemical_usage, payment_proof, id_attachments)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iissssss", $job_order_id, $technician_id, $observation_notes, $recommendation, $attachmentsStr, $chemical_usage, $payment_proof, $idAttachmentsStr);
        } elseif ($recommendationExists && $chemicalUsageExists) {
            $stmt = $conn->prepare("
                INSERT INTO job_order_report
                (job_order_id, technician_id, observation_notes, recommendation, attachments, chemical_usage)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iissss", $job_order_id, $technician_id, $observation_notes, $recommendation, $attachmentsStr, $chemical_usage);
        } elseif ($recommendationExists) {
            $stmt = $conn->prepare("
                INSERT INTO job_order_report
                (job_order_id, technician_id, observation_notes, recommendation, attachments)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iisss", $job_order_id, $technician_id, $observation_notes, $recommendation, $attachmentsStr);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO job_order_report
                (job_order_id, technician_id, observation_notes, attachments)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iiss", $job_order_id, $technician_id, $observation_notes, $attachmentsStr);
        }

        if (!$stmt->execute()) {
            throw new Exception('Failed to insert job order report: ' . $conn->error);
        }

        $report_id = $conn->insert_id;

        // Update job order status to completed
        $updateStmt = $conn->prepare("UPDATE job_order SET status = 'completed' WHERE job_order_id = ?");
        $updateStmt->bind_param("i", $job_order_id);

        if (!$updateStmt->execute()) {
            throw new Exception('Failed to update job order status: ' . $conn->error);
        }

        // Log the update for debugging
        error_log("Updated job_order status to 'completed' for job_order_id: $job_order_id. Affected rows: " . $updateStmt->affected_rows);

        // Also update the status in the job_order_technicians table if it exists
        $checkJOTTable = $conn->query("SHOW TABLES LIKE 'job_order_technicians'");
        if ($checkJOTTable->num_rows > 0) {
            // Check if the table has a status column
            $checkStatusColumn = $conn->query("SHOW COLUMNS FROM job_order_technicians LIKE 'status'");
            if ($checkStatusColumn->num_rows > 0) {
                $updateJOTStmt = $conn->prepare("UPDATE job_order_technicians SET status = 'completed' WHERE job_order_id = ?");
                $updateJOTStmt->bind_param("i", $job_order_id);
                $updateJOTStmt->execute();
                error_log("Updated job_order_technicians status to 'completed' for job_order_id: $job_order_id. Affected rows: " . $updateJOTStmt->affected_rows);
            }
        }

        // Reset tools status to "in stock" for this job order
        try {
            // Check if reset_tools_status.php exists
            if (file_exists('../../reset_tools_status.php')) {
                // Make a request to reset_tools_status.php
                $reset_tools_url = '../../reset_tools_status.php';
                $reset_tools_data = json_encode(['job_order_id' => $job_order_id]);

                // Use cURL to make the request
                $ch = curl_init($reset_tools_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $reset_tools_data);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                $reset_tools_response = curl_exec($ch);
                $curl_error = curl_error($ch);
                curl_close($ch);

                if ($reset_tools_response) {
                    $reset_tools_result = json_decode($reset_tools_response, true);
                    error_log("Tools status reset result: " . json_encode($reset_tools_result));
                } else {
                    error_log("Failed to reset tools status: " . $curl_error);
                }
            } else {
                error_log("reset_tools_status.php file not found");
            }
        } catch (Exception $reset_ex) {
            error_log("Exception during tools status reset: " . $reset_ex->getMessage());
            // Don't fail the whole operation if tools reset fails
        }

        // Commit transaction
        $conn->commit();

        // Prepare report data based on column existence
        $reportData = [
            'id' => $report_id,
            'job_order_id' => $job_order_id,
            'technician_id' => $technician_id,
            'observation_notes' => $observation_notes,
            'attachments' => $attachmentsStr,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Add recommendation field if it exists
        if ($recommendationExists) {
            $reportData['recommendation'] = $recommendation;
        }

        // Add chemical usage field if it exists
        if ($chemicalUsageExists && $chemical_usage) {
            $reportData['chemical_usage'] = $chemical_usage;

            // Process chemical inventory deduction
            try {
                // Parse chemical usage data with error handling
                try {
                    // First, ensure the chemical_usage string is valid
                    if (!is_string($chemical_usage) || trim($chemical_usage) === '') {
                        throw new Exception("Chemical usage data is empty or not a string");
                    }

                    // Check for any BOM or other invisible characters at the beginning
                    $chemical_usage = preg_replace('/^[\x00-\x1F\xEF\xBB\xBF]+/', '', $chemical_usage);

                    // Check for any trailing characters after valid JSON
                    // Use a more robust pattern to extract valid JSON
                    if (preg_match('/^(\[.*\]|\{.*\})/', $chemical_usage, $matches)) {
                        $chemical_usage = $matches[0];
                    } else {
                        // Try a more aggressive approach to find and extract valid JSON
                        if (preg_match('/(\[.*\]|\{.*\})/', $chemical_usage, $matches)) {
                            $chemical_usage = $matches[0];
                        }
                    }

                    // Remove any non-printable characters that might be causing issues
                    $chemical_usage = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $chemical_usage);

                    // Attempt to decode the JSON
                    $chemical_usage_data = json_decode($chemical_usage, true);

                    // Check for JSON decoding errors
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("JSON decode error: " . json_last_error_msg());
                    }

                    if (!is_array($chemical_usage_data) || empty($chemical_usage_data)) {
                        throw new Exception("Chemical usage data is not a valid array or is empty");
                    }

                    // Log the chemical usage data before updating inventory
                    error_log("Chemical usage data before inventory update: " . json_encode($chemical_usage_data));

                    // Filter out chemicals with zero dosage and sanitize the data
                    $filtered_chemical_usage = [];
                    foreach ($chemical_usage_data as $chem) {
                        // Skip chemicals with zero or negative dosage
                        if (!isset($chem['dosage']) || floatval($chem['dosage']) <= 0) {
                            continue;
                        }

                        // Sanitize the chemical data
                        $sanitized_chem = [
                            'id' => isset($chem['id']) ? filter_var($chem['id'], FILTER_VALIDATE_INT) : 0,
                            'name' => isset($chem['name']) ? trim($chem['name']) : '',
                            'type' => isset($chem['type']) ? trim($chem['type']) : '',
                            'target_pest' => isset($chem['target_pest']) ? trim($chem['target_pest']) : '',
                            'dosage' => floatval($chem['dosage']),
                            'dosage_unit' => isset($chem['dosage_unit']) ? trim($chem['dosage_unit']) : 'ml',
                            'inventory_unit' => isset($chem['inventory_unit']) ? trim($chem['inventory_unit']) : 'ml'
                        ];

                        // Add replacement information if available
                        if (isset($chem['is_replacement']) && $chem['is_replacement']) {
                            $sanitized_chem['is_replacement'] = true;
                            $sanitized_chem['replacing'] = isset($chem['replacing']) ? filter_var($chem['replacing'], FILTER_VALIDATE_INT) : 0;
                            $sanitized_chem['original_chemical_name'] = isset($chem['original_chemical_name']) ? trim($chem['original_chemical_name']) : '';
                        }

                        $filtered_chemical_usage[] = $sanitized_chem;
                    }

                    if (empty($filtered_chemical_usage)) {
                        error_log("No chemicals with positive dosage found, skipping inventory update");
                        $reportData['inventory_update'] = [
                            'success' => false,
                            'message' => 'No chemicals with positive dosage found',
                            'updated_chemicals' => [],
                            'errors' => ['No chemicals with positive dosage to deduct from inventory']
                        ];
                    } else {
                        error_log("Filtered and sanitized chemical usage data (positive dosage only): " . json_encode($filtered_chemical_usage));

                        // Update chemical inventory based on actual dosage used
                        error_log("Calling update_chemical_inventory with " . count($filtered_chemical_usage) . " chemicals");

                        // Add debug flag
                        $debug_mode = isset($_POST['debug_chemical_usage']) && $_POST['debug_chemical_usage'] == '1';

                        // Force debug mode for troubleshooting
                        $debug_mode = true;

                        // Log the filtered chemical usage data
                        error_log("Filtered chemical usage data: " . json_encode($filtered_chemical_usage));

                        // Call the function with debug mode
                        $inventory_update_result = update_chemical_inventory($filtered_chemical_usage, $conn, $job_order_id, $technician_id, $debug_mode);

                        // Add inventory update result to the response
                        $reportData['inventory_update'] = $inventory_update_result;

                        // Log the result
                        error_log("Inventory update result: success=" . ($inventory_update_result['success'] ? 'true' : 'false') .
                                 ", updated_chemicals=" . count($inventory_update_result['updated_chemicals']) .
                                 ", errors=" . count($inventory_update_result['errors']));

                        // Add the updated chemicals to the response for display
                        $reportData['updated_chemicals'] = $inventory_update_result['updated_chemicals'];

                        // Ensure the updated_chemicals array is properly formatted for display
                        foreach ($reportData['updated_chemicals'] as &$chem) {
                            // Ensure all required fields are present
                            if (!isset($chem['name'])) $chem['name'] = 'Unknown';
                            if (!isset($chem['type'])) $chem['type'] = 'N/A';
                            if (!isset($chem['previous_quantity'])) $chem['previous_quantity'] = '0';
                            if (!isset($chem['used_quantity'])) $chem['used_quantity'] = '0';
                            if (!isset($chem['new_quantity'])) $chem['new_quantity'] = '0';
                            if (!isset($chem['unit'])) $chem['unit'] = 'ml';

                            // Format numeric values to 2 decimal places
                            $chem['previous_quantity'] = number_format((float)$chem['previous_quantity'], 2);
                            $chem['used_quantity'] = number_format((float)$chem['used_quantity'], 2);
                            $chem['new_quantity'] = number_format((float)$chem['new_quantity'], 2);
                        }

                        // Add a flag to indicate that the inventory was updated
                        $reportData['inventory_updated'] = true;

                        // Invalidate the chemical inventory cache
                        if (isset($_SESSION)) {
                            foreach ($_SESSION as $key => $value) {
                                if (strpos($key, 'chemical_inventory_') === 0) {
                                    unset($_SESSION[$key]);
                                    unset($_SESSION[$key . '_timestamp']);
                                    error_log("Invalidated cache key: $key");
                                }
                            }
                            error_log("Chemical inventory cache invalidated");
                        } else {
                            error_log("WARNING: Session not available, could not invalidate chemical inventory cache");
                        }
                    }

                    // Log the result with more details
                    error_log("Inventory update completed with result: " . json_encode($inventory_update_result));

                    // Log the updated chemicals for debugging
                    if (!empty($inventory_update_result['updated_chemicals'])) {
                        error_log("Updated chemicals count: " . count($inventory_update_result['updated_chemicals']));
                        foreach ($inventory_update_result['updated_chemicals'] as $idx => $chem) {
                            error_log("Chemical $idx: Name={$chem['name']}, Previous={$chem['previous_quantity']}, Used={$chem['used_quantity']}, New={$chem['new_quantity']}, Unit={$chem['unit']}");
                        }
                    } else {
                        error_log("No chemicals were updated in inventory");
                    }
                } catch (Exception $json_ex) {
                    error_log("ERROR: Failed to process chemical usage data: " . $json_ex->getMessage());
                    error_log("Chemical usage data: " . substr($chemical_usage, 0, 1000) . (strlen($chemical_usage) > 1000 ? '...' : ''));

                    $reportData['inventory_update'] = [
                        'success' => false,
                        'message' => 'Failed to process chemical usage data: ' . $json_ex->getMessage(),
                        'updated_chemicals' => [],
                        'replaced_chemicals' => [],
                        'errors' => [$json_ex->getMessage()]
                    ];
                }
            } catch (Exception $e) {
                error_log("ERROR: Exception during chemical inventory update: " . $e->getMessage());
                $reportData['inventory_update'] = [
                    'success' => false,
                    'message' => 'Exception during inventory update: ' . $e->getMessage(),
                    'updated_chemicals' => [],
                    'replaced_chemicals' => [],
                    'errors' => [$e->getMessage()]
                ];
            }
        }

        // Add payment proof field if it exists
        if ($paymentProofExists && $payment_proof) {
            $reportData['payment_proof'] = $payment_proof;
        }

        // Add ID attachments field if it exists
        if ($idAttachmentsExists && !empty($idAttachmentsStr)) {
            $reportData['id_attachments'] = $idAttachmentsStr;
        }

        // Send notifications to admin and client about job completion
        $notification_result = null;
        try {
            error_log("Starting job completion notification process for job_order_id: $job_order_id, technician_id: $technician_id");

            // First, make sure we have the notification_functions.php included
            if (!function_exists('createNotification')) {
                error_log("createNotification function not found, attempting to include notification_functions.php");
                @include_once '../../notification_functions.php';

                if (!function_exists('createNotification')) {
                    error_log("Failed to include notification_functions.php");
                }
            }

            // Include the get_notifications.php file to access the sendJobOrderCompletionNotifications function
            if (!function_exists('sendJobOrderCompletionNotifications')) {
                error_log("sendJobOrderCompletionNotifications function not found, attempting to include get_notifications.php");
                // Try to include the file
                @include_once '../../get_notifications.php';
            }

            // Check if the notification function exists
            if (function_exists('sendJobOrderCompletionNotifications')) {
                error_log("sendJobOrderCompletionNotifications function found, calling directly");
                // Call the function directly
                $notification_result = sendJobOrderCompletionNotifications($job_order_id, $technician_id, $conn);
                error_log("Notification result: " . json_encode($notification_result));
            } else {
                error_log("sendJobOrderCompletionNotifications function not available, trying alternative approach");

                // Try to use the individual notification functions if they exist
                if (function_exists('notifyClientAboutCompletedJob') && function_exists('notifyAdminAboutCompletedJob')) {
                    error_log("Using individual notification functions");

                    // Get job order details
                    $job_query = $conn->prepare("
                        SELECT jo.*, ar.report_id, a.client_id, a.client_name, jo.type_of_work
                        FROM job_order jo
                        JOIN assessment_report ar ON jo.report_id = ar.report_id
                        JOIN appointments a ON ar.appointment_id = a.appointment_id
                        WHERE jo.job_order_id = ?
                    ");
                    $job_query->bind_param("i", $job_order_id);
                    $job_query->execute();
                    $job_result = $job_query->get_result();

                    if ($job_result->num_rows > 0) {
                        $job_data = $job_result->fetch_assoc();
                        $client_id = $job_data['client_id'];

                        // Notify client
                        $client_notification = notifyClientAboutCompletedJob($client_id, $job_order_id, $technician_id, $conn);
                        error_log("Client notification result: " . ($client_notification ? "success" : "failed"));

                        // Get admin IDs
                        $admin_ids = [];
                        $admin_query = $conn->query("SELECT staff_id FROM office_staff");
                        if ($admin_query && $admin_query->num_rows > 0) {
                            while ($admin = $admin_query->fetch_assoc()) {
                                $admin_ids[] = $admin['staff_id'];
                            }
                        }

                        // Notify admins
                        $admin_notifications_sent = 0;
                        foreach ($admin_ids as $admin_id) {
                            $admin_notification = notifyAdminAboutCompletedJob($admin_id, $job_order_id, $technician_id, $conn);
                            if ($admin_notification) {
                                $admin_notifications_sent++;
                            }
                        }

                        error_log("Admin notifications sent: $admin_notifications_sent");

                        // Create result array
                        $notification_result = [
                            'success' => ($client_notification || $admin_notifications_sent > 0),
                            'message' => 'Notifications sent using individual functions',
                            'client_notification' => $client_notification,
                            'admin_notification' => $admin_notifications_sent > 0
                        ];
                    } else {
                        error_log("Job order not found for notifications");
                        $notification_result = [
                            'success' => false,
                            'message' => 'Job order not found for notifications'
                        ];
                    }
                } else {
                    error_log("Individual notification functions not available, making HTTP request");
                    // Make an HTTP request to the notification endpoint as a last resort
                    $notification_url = "../../get_notifications.php?action=job_completed";
                    $notification_data = [
                        'job_order_id' => $job_order_id,
                        'technician_id' => $technician_id
                    ];

                    error_log("Sending HTTP request to: $notification_url with data: " . json_encode($notification_data));

                    // Use cURL to make the request
                    $ch = curl_init($notification_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $notification_data);
                    $notification_response = curl_exec($ch);
                    $curl_error = curl_error($ch);
                    curl_close($ch);

                    if ($notification_response) {
                        $notification_result = json_decode($notification_response, true);
                        error_log("Notification response: " . $notification_response);
                    } else {
                        error_log("Failed to send notification request: " . $curl_error);
                    }
                }
            }

            // Add notification result to the report data
            if ($notification_result) {
                $reportData['notification_result'] = $notification_result;
                error_log("Added notification result to report data: " . json_encode($notification_result));
            } else {
                error_log("No notification result to add to report data");
            }
        } catch (Exception $notification_ex) {
            error_log("Exception during notification sending: " . $notification_ex->getMessage());
            // Don't fail the whole operation if notifications fail
            $reportData['notification_error'] = $notification_ex->getMessage();
        }

        error_log("Job order report submission completed with notifications");

        // Check if inventory update data is present in the final response
        if (isset($reportData['inventory_update'])) {
            error_log("Final response includes inventory update data with " .
                      (isset($reportData['inventory_update']['updated_chemicals']) ?
                       count($reportData['inventory_update']['updated_chemicals']) : 0) .
                      " updated chemicals");
        } else {
            error_log("WARNING: Final response does not include inventory update data");
        }

        // Return success response with report data
        // Make sure there's no whitespace or other characters before or after the JSON
        ob_clean(); // Clear any previous output
        $response = [
            'success' => true,
            'message' => 'Job order report submitted successfully',
            'report' => $reportData
        ];

        // Log the final response size
        $json_response = json_encode($response);
        error_log("Final JSON response size: " . strlen($json_response) . " bytes");

        echo $json_response;
        exit; // Ensure no additional output

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();

        // Clean output buffer and send error response
        ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }

} else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['job_order_id'])) {
    // Handle GET request to fetch report data
    $job_order_id = intval($_GET['job_order_id']);

    $stmt = $conn->prepare("SELECT * FROM job_order_report WHERE job_order_id = ?");
    $stmt->bind_param("i", $job_order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Clean output buffer before sending response
    ob_clean();

    if ($result->num_rows > 0) {
        $report = $result->fetch_assoc();
        echo json_encode(['success' => true, 'report' => $report]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No report found for this job order']);
    }
    exit;

} else {
    // Invalid request method
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Close database connection
$conn->close();
?>
