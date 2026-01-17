<?php
/**
 * Functions for displaying chemical recommendations consistently across the application
 */

/**
 * Parse chemical recommendations from JSON string
 *
 * @param string $chemical_recommendations JSON string of chemical recommendations
 * @return array Array of chemical data or chemical names
 */
function parseChemicalRecommendations($chemical_recommendations) {
    $chemical_data = [];

    if (empty($chemical_recommendations)) {
        return $chemical_data;
    }

    // Log the input for debugging
    error_log("Parsing chemical recommendations: " . substr($chemical_recommendations, 0, 100) . "...");

    try {
        $decoded = json_decode($chemical_recommendations, true);

        if ($decoded && json_last_error() === JSON_ERROR_NONE) {
            // Format 1: Array of objects with chemical data
            if (is_array($decoded) && !isset($decoded['success'])) {
                foreach ($decoded as $chemical) {
                    // Check if this is a complete chemical object
                    if ((isset($chemical['name']) || isset($chemical['chemical_name'])) &&
                        (isset($chemical['type']) || isset($chemical['dosage']) || isset($chemical['target_pest']))) {

                        // This is a complete chemical object, return the full array
                        return $decoded;
                    }

                    // Otherwise, just collect the names
                    if (isset($chemical['name'])) {
                        $chemical_data[] = $chemical['name'];
                    } else if (isset($chemical['chemical_name'])) {
                        $chemical_data[] = $chemical['chemical_name'];
                    }
                }
            }
            // Format 2: Complete response object from get_chemical_recommendations.php
            else if (isset($decoded['success']) && isset($decoded['recommendations'])) {
                // Extract full chemical objects if available
                $full_chemicals = [];

                foreach ($decoded['recommendations'] as $category => $chemicals) {
                    foreach ($chemicals as $chemical) {
                        if (isset($chemical['chemical_name']) || isset($chemical['name'])) {
                            $name = isset($chemical['name']) ? $chemical['name'] : $chemical['chemical_name'];
                            $type = isset($chemical['type']) ? $chemical['type'] : 'Unknown';
                            $dosage = isset($chemical['recommended_dosage']) ? $chemical['recommended_dosage'] :
                                     (isset($chemical['dosage']) ? $chemical['dosage'] : 'As recommended');
                            $dosage_unit = isset($chemical['dosage_unit']) ? $chemical['dosage_unit'] : 'ml';
                            $target_pest = isset($chemical['target_pest']) ? $chemical['target_pest'] : 'General';

                            $full_chemicals[] = [
                                'name' => $name,
                                'type' => $type,
                                'dosage' => $dosage,
                                'dosage_unit' => $dosage_unit,
                                'target_pest' => $target_pest
                            ];
                        }
                    }
                }

                if (!empty($full_chemicals)) {
                    return $full_chemicals;
                }

                // If we couldn't extract full objects, just get the names
                foreach ($decoded['recommendations'] as $category => $chemicals) {
                    foreach ($chemicals as $chemical) {
                        if (isset($chemical['chemical_name'])) {
                            $chemical_data[] = $chemical['chemical_name'];
                        } else if (isset($chemical['name'])) {
                            $chemical_data[] = $chemical['name'];
                        }
                    }
                }
            }
            // Format 3: String that might be a chemical name
            else if (is_string($decoded)) {
                $chemical_data[] = $decoded;
            }
        } else {
            // Try to handle it as a plain string if JSON decode failed
            if (is_string($chemical_recommendations)) {
                // Check if it might be a serialized PHP array
                if (strpos($chemical_recommendations, 'a:') === 0 || strpos($chemical_recommendations, 's:') === 0) {
                    $unserialized = @unserialize($chemical_recommendations);
                    if ($unserialized !== false) {
                        if (is_array($unserialized)) {
                            foreach ($unserialized as $item) {
                                if (is_string($item)) {
                                    $chemical_data[] = $item;
                                } else if (is_array($item) && isset($item['name'])) {
                                    $chemical_data[] = $item['name'];
                                }
                            }
                        } else if (is_string($unserialized)) {
                            $chemical_data[] = $unserialized;
                        }
                    } else {
                        $chemical_data[] = $chemical_recommendations;
                    }
                } else {
                    // Try to extract chemical names using regex if it looks like JSON
                    if (strpos($chemical_recommendations, '"name"') !== false ||
                        strpos($chemical_recommendations, '"chemical_name"') !== false) {

                        // Extract name values
                        preg_match_all('/"name":"([^"]+)"/', $chemical_recommendations, $name_matches);
                        preg_match_all('/"chemical_name":"([^"]+)"/', $chemical_recommendations, $chem_name_matches);

                        if (!empty($name_matches[1])) {
                            $chemical_data = array_merge($chemical_data, $name_matches[1]);
                        }

                        if (!empty($chem_name_matches[1])) {
                            $chemical_data = array_merge($chemical_data, $chem_name_matches[1]);
                        }
                    } else {
                        // Just use the string as is
                        $chemical_data[] = $chemical_recommendations;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Log error but continue
        error_log("Error parsing chemical recommendations: " . $e->getMessage());

        // Try regex as a last resort
        preg_match_all('/"name":"([^"]+)"/', $chemical_recommendations, $name_matches);
        preg_match_all('/"chemical_name":"([^"]+)"/', $chemical_recommendations, $chem_name_matches);

        if (!empty($name_matches[1])) {
            $chemical_data = array_merge($chemical_data, $name_matches[1]);
        }

        if (!empty($chem_name_matches[1])) {
            $chemical_data = array_merge($chemical_data, $chem_name_matches[1]);
        }
    }

    // Remove duplicates
    $chemical_data = array_unique($chemical_data);

    // Log the result
    error_log("Parsed " . count($chemical_data) . " chemicals");

    return $chemical_data;
}

/**
 * Get chemical recommendations as a formatted HTML string
 *
 * @param string $chemical_recommendations JSON string of chemical recommendations
 * @return string HTML representation of the chemical recommendations
 */
function getChemicalRecommendationsHtml($chemical_recommendations) {
    $chemicals = parseChemicalRecommendations($chemical_recommendations);

    if (empty($chemicals)) {
        return '<span class="text-muted">No chemical recommendations provided</span>';
    }

    $html = '<ul class="chemical-list">';

    // Check if we have an array of objects or just an array of strings
    if (is_array($chemicals) && isset($chemicals[0]) && is_array($chemicals[0])) {
        // Array of chemical objects
        foreach ($chemicals as $chemical) {
            $name = isset($chemical['name']) ? $chemical['name'] :
                  (isset($chemical['chemical_name']) ? $chemical['chemical_name'] : 'Unknown');

            $type = isset($chemical['type']) ? $chemical['type'] : '';
            $dosage = isset($chemical['dosage']) ? $chemical['dosage'] :
                    (isset($chemical['recommended_dosage']) ? $chemical['recommended_dosage'] : '');
            $dosage_unit = isset($chemical['dosage_unit']) ? $chemical['dosage_unit'] : '';
            $target_pest = isset($chemical['target_pest']) ? $chemical['target_pest'] : '';

            $html .= '<li>';
            $html .= '<strong>' . htmlspecialchars($name) . '</strong>';

            $details = [];
            if (!empty($type)) $details[] = 'Type: ' . htmlspecialchars($type);
            if (!empty($dosage)) {
                $dosage_text = 'Dosage: ' . htmlspecialchars($dosage);
                if (!empty($dosage_unit)) $dosage_text .= ' ' . htmlspecialchars($dosage_unit);
                $details[] = $dosage_text;
            }
            if (!empty($target_pest)) $details[] = 'Target: ' . htmlspecialchars($target_pest);

            if (!empty($details)) {
                $html .= ' <span class="text-muted">(' . implode(', ', $details) . ')</span>';
            }

            $html .= '</li>';
        }
    } else {
        // Array of chemical names
        foreach ($chemicals as $name) {
            $html .= '<li>' . htmlspecialchars($name) . '</li>';
        }
    }

    $html .= '</ul>';

    return $html;
}

/**
 * Get chemical recommendations as a comma-separated string
 *
 * @param string $chemical_recommendations JSON string of chemical recommendations
 * @return string Comma-separated list of chemical names
 */
function getChemicalRecommendationsText($chemical_recommendations) {
    $chemicals = parseChemicalRecommendations($chemical_recommendations);

    if (empty($chemicals)) {
        return 'Not specified';
    }

    $names = [];

    // Check if we have an array of objects or just an array of strings
    if (is_array($chemicals) && isset($chemicals[0]) && is_array($chemicals[0])) {
        // Array of chemical objects
        foreach ($chemicals as $chemical) {
            if (isset($chemical['name'])) {
                $names[] = $chemical['name'];
            } else if (isset($chemical['chemical_name'])) {
                $names[] = $chemical['chemical_name'];
            }
        }
    } else {
        // Array of chemical names
        $names = $chemicals;
    }

    return implode(', ', $names);
}
?>
