<?php
/**
 * Chemical Dosage Logging Utility
 * 
 * This file provides functions to log chemical dosage information for debugging purposes.
 */

/**
 * Log chemical dosage information to a file
 * 
 * @param array $chemicals The chemical data to log
 * @param string $source The source of the chemical data (e.g., 'job_order_report', 'job_order', 'assessment_report')
 * @param int $id The ID of the record (job_order_id, report_id, etc.)
 * @return void
 */
function log_chemical_dosage($chemicals, $source, $id) {
    $log_dir = __DIR__ . '/../logs';
    
    // Create logs directory if it doesn't exist
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/chemical_dosage.log';
    
    // Format the log entry
    $log_entry = "[" . date('Y-m-d H:i:s') . "] Source: $source, ID: $id\n";
    
    if (is_string($chemicals)) {
        // If chemicals is a JSON string, try to decode it
        $chemicals_data = json_decode($chemicals, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $log_entry .= "Chemical data (decoded from JSON string):\n";
            $chemicals = $chemicals_data;
        } else {
            $log_entry .= "Chemical data (raw string):\n$chemicals\n";
            file_put_contents($log_file, $log_entry, FILE_APPEND);
            return;
        }
    }
    
    if (is_array($chemicals)) {
        if (count($chemicals) === 0) {
            $log_entry .= "No chemicals found (empty array)\n";
        } else {
            $log_entry .= "Found " . count($chemicals) . " chemicals:\n";
            
            // Check if chemicals are nested by pest type
            $is_nested = false;
            foreach ($chemicals as $key => $value) {
                if (is_array($value) && !isset($value['name']) && !isset($value['chemical_name'])) {
                    $is_nested = true;
                    break;
                }
            }
            
            if ($is_nested) {
                // Handle nested structure (by pest type)
                foreach ($chemicals as $pest_type => $pest_chemicals) {
                    $log_entry .= "  Pest Type: $pest_type\n";
                    if (is_array($pest_chemicals)) {
                        foreach ($pest_chemicals as $index => $chemical) {
                            $name = isset($chemical['name']) ? $chemical['name'] : (isset($chemical['chemical_name']) ? $chemical['chemical_name'] : 'Unknown');
                            $recommended = isset($chemical['recommended_dosage']) ? $chemical['recommended_dosage'] : (isset($chemical['dosage']) ? $chemical['dosage'] : 'N/A');
                            $unit = isset($chemical['dosage_unit']) ? $chemical['dosage_unit'] : 'ml';
                            $log_entry .= "    [$index] $name: Recommended: $recommended $unit\n";
                        }
                    } else {
                        $log_entry .= "    Invalid chemical data for pest type: $pest_type\n";
                    }
                }
            } else {
                // Handle flat structure
                foreach ($chemicals as $index => $chemical) {
                    $name = isset($chemical['name']) ? $chemical['name'] : (isset($chemical['chemical_name']) ? $chemical['chemical_name'] : 'Unknown');
                    $recommended = isset($chemical['recommended_dosage']) ? $chemical['recommended_dosage'] : (isset($chemical['dosage']) ? $chemical['dosage'] : 'N/A');
                    $unit = isset($chemical['dosage_unit']) ? $chemical['dosage_unit'] : 'ml';
                    $log_entry .= "  [$index] $name: Recommended: $recommended $unit\n";
                }
            }
        }
    } else {
        $log_entry .= "Invalid chemical data: " . gettype($chemicals) . "\n";
    }
    
    $log_entry .= "---------------------------------------------\n";
    
    // Write to log file
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}
