<?php
// Set up a simple form to test the API
echo '<form method="post" action="Admin Side/get_chemical_recommendations.php">';
echo '<input type="text" name="pest_types" value="Termites" />';
echo '<input type="text" name="area" value="100" />';
echo '<input type="text" name="application_method" value="spray" />';
echo '<input type="submit" value="Test API" />';
echo '</form>';

// If we want to test the API directly
echo '<h2>Direct API Test</h2>';
echo '<pre>';

// Create a cURL handle
$ch = curl_init();

// Set the URL
curl_setopt($ch, CURLOPT_URL, 'http://localhost/macj1/Admin%20Side/get_chemical_recommendations.php');

// Set the HTTP method to POST
curl_setopt($ch, CURLOPT_POST, 1);

// Set the POST data
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'pest_types' => 'Termites',
    'area' => '100',
    'application_method' => 'spray'
]);

// Return the response instead of outputting it
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Execute the request
$response = curl_exec($ch);

// Check for errors
if (curl_errno($ch)) {
    echo 'Error: ' . curl_error($ch);
} else {
    // Print the raw response
    echo "Raw response:\n";
    echo $response . "\n\n";
    
    // Try to decode the JSON response
    $data = json_decode($response, true);
    if ($data) {
        echo "Decoded response:\n";
        
        // Check if the response was successful
        if (isset($data['success']) && $data['success']) {
            echo "Success: " . $data['message'] . "\n";
            
            // Check if recommendations are included
            if (isset($data['recommendations']) && !empty($data['recommendations'])) {
                echo "Recommendations found for target pests: " . implode(', ', array_keys($data['recommendations'])) . "\n\n";
                
                // Check the first recommendation for each target pest
                foreach ($data['recommendations'] as $targetPest => $chemicals) {
                    echo "Target Pest: $targetPest\n";
                    echo "Number of chemicals: " . count($chemicals) . "\n";
                    
                    if (!empty($chemicals)) {
                        $firstChemical = $chemicals[0];
                        echo "First chemical details:\n";
                        echo "- Name: " . $firstChemical['chemical_name'] . "\n";
                        echo "- Type: " . $firstChemical['type'] . "\n";
                        echo "- Quantity: " . $firstChemical['quantity'] . " " . $firstChemical['unit'] . "\n";
                        echo "- Recommended Dosage: " . $firstChemical['recommended_dosage'] . " " . $firstChemical['dosage_unit'] . "\n";
                        
                        // Check if expiration_date is included
                        if (isset($firstChemical['expiration_date'])) {
                            echo "- Expiration Date: " . $firstChemical['expiration_date'] . "\n";
                        } else {
                            echo "- Expiration Date: NOT INCLUDED in response\n";
                        }
                        
                        echo "\n";
                    }
                }
            } else {
                echo "No recommendations found in the response\n";
            }
        } else {
            echo "Error: " . ($data['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "Failed to decode JSON response\n";
    }
}

// Close the cURL handle
curl_close($ch);

echo '</pre>';
?>
