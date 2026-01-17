<?php
require_once '../db_connect.php';

// Get assessment reports with chemical recommendations
$query = "SELECT ar.report_id, ar.pest_types, ar.area, ar.chemical_recommendations, 
                 a.client_name, a.preferred_date
          FROM assessment_report ar
          JOIN appointments a ON ar.appointment_id = a.appointment_id
          WHERE ar.chemical_recommendations IS NOT NULL
          ORDER BY ar.report_id DESC";

$result = $conn->query($query);

echo "<h2>Assessment Reports with Chemical Recommendations</h2>";

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th style='padding: 8px;'>Report ID</th>";
    echo "<th style='padding: 8px;'>Client</th>";
    echo "<th style='padding: 8px;'>Date</th>";
    echo "<th style='padding: 8px;'>Pest Types</th>";
    echo "<th style='padding: 8px;'>Area</th>";
    echo "<th style='padding: 8px;'>Chemical Recommendations</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . $row['report_id'] . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['client_name']) . "</td>";
        echo "<td style='padding: 8px;'>" . date('M j, Y', strtotime($row['preferred_date'])) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['pest_types']) . "</td>";
        echo "<td style='padding: 8px;'>" . $row['area'] . " m²</td>";
        echo "<td style='padding: 8px;'>";
        
        // Display the chemical recommendations
        $recommendations = $row['chemical_recommendations'];
        if (!empty($recommendations)) {
            // Check if it's JSON
            $decoded = json_decode($recommendations, true);
            if ($decoded && json_last_error() === JSON_ERROR_NONE) {
                // It's JSON, display it nicely
                echo "<pre style='white-space: pre-wrap;'>" . json_encode($decoded, JSON_PRETTY_PRINT) . "</pre>";
            } else {
                // It's not JSON, display as is
                echo htmlspecialchars($recommendations);
            }
        } else {
            echo "<em>No recommendations</em>";
        }
        
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No assessment reports with chemical recommendations found.</p>";
}

$conn->close();
?>
