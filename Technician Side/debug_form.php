<?php
// Include database connection
require_once '../db_connect.php';

// Create a log file for debugging
$log_file = __DIR__ . '/../logs/debug_form.log';
if (!file_exists(__DIR__ . '/../logs/')) {
    mkdir(__DIR__ . '/../logs/', 0777, true);
}

// Log function for easier debugging
function log_debug($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

log_debug("Debug form script started");

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    log_debug("POST request received");
    log_debug("POST data: " . print_r($_POST, true));
    
    if (!empty($_FILES)) {
        log_debug("FILES data: " . print_r($_FILES, true));
    } else {
        log_debug("No files uploaded");
    }
    
    // Check for required fields
    $required_fields = ['appointment_id', 'area', 'preferred_date', 'preferred_time', 'frequency'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    // Check for array fields
    $array_fields = ['pest_types', 'type_of_work'];
    foreach ($array_fields as $field) {
        if (!isset($_POST[$field]) || !is_array($_POST[$field]) || empty($_POST[$field])) {
            $missing_fields[] = $field . '[]';
        }
    }
    
    if (!empty($missing_fields)) {
        log_debug("Missing required fields: " . implode(', ', $missing_fields));
        echo json_encode(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing_fields)]);
        exit;
    }
    
    // Check chemical recommendations
    if (isset($_POST['selected_chemicals']) && !empty($_POST['selected_chemicals'])) {
        log_debug("Chemical recommendations received: " . substr($_POST['selected_chemicals'], 0, 100) . (strlen($_POST['selected_chemicals']) > 100 ? '...' : ''));
        
        try {
            $chemicals = json_decode($_POST['selected_chemicals'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_debug("Error decoding chemical recommendations: " . json_last_error_msg());
            } else {
                log_debug("Successfully decoded chemical recommendations. Count: " . count($chemicals));
            }
        } catch (Exception $e) {
            log_debug("Exception decoding chemical recommendations: " . $e->getMessage());
        }
    } else {
        log_debug("No chemical recommendations received");
    }
    
    // Check database connection
    log_debug("Database connection status: " . ($conn ? "Connected" : "Not connected"));
    
    // Check assessment_report table structure
    log_debug("Checking assessment_report table structure");
    $tableCheck = $conn->query("DESCRIBE assessment_report");
    if (!$tableCheck) {
        log_debug("Error checking table structure: " . $conn->error);
    } else {
        $columns = [];
        while ($row = $tableCheck->fetch_assoc()) {
            $columns[] = $row['Field'];
            log_debug("Found column: " . $row['Field'] . " - " . $row['Type']);
        }
        
        // Check for required columns
        $requiredColumns = ['appointment_id', 'end_time', 'area', 'notes', 'recommendation', 
                            'attachments', 'pest_types', 'problem_area', 'preferred_date', 
                            'preferred_time', 'frequency', 'chemical_recommendations', 'type_of_work'];
        
        $missingColumns = [];
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $columns)) {
                $missingColumns[] = $column;
            }
        }
        
        if (!empty($missingColumns)) {
            log_debug("Missing columns in assessment_report table: " . implode(', ', $missingColumns));
        } else {
            log_debug("All required columns exist in assessment_report table");
        }
    }
    
    // Return success response for testing
    echo json_encode(['success' => true, 'message' => 'Debug form submission successful']);
} else {
    // Display a simple form for testing
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Debug Form</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <h1>Debug Form</h1>
            <p>Use this form to test the submission process.</p>
            
            <form id="debugForm" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="appointment_id" class="form-label">Appointment ID</label>
                    <input type="text" class="form-control" id="appointment_id" name="appointment_id" value="123" required>
                </div>
                
                <div class="mb-3">
                    <label for="area" class="form-label">Area (m²)</label>
                    <input type="number" class="form-control" id="area" name="area" value="100" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Pest Types</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="pest_types[]" value="Ants" id="pestAnts" checked>
                        <label class="form-check-label" for="pestAnts">Ants</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="pest_types[]" value="Cockroaches" id="pestCockroaches">
                        <label class="form-check-label" for="pestCockroaches">Cockroaches</label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="problem_area" class="form-label">Problem Area</label>
                    <input type="text" class="form-control" id="problem_area" name="problem_area" value="Kitchen">
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes">Test notes</textarea>
                </div>
                
                <div class="mb-3">
                    <label for="recommendation" class="form-label">Recommendation</label>
                    <textarea class="form-control" id="recommendation" name="recommendation">Test recommendation</textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Type of Work</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="type_of_work[]" value="General Pest Control" id="workGeneral" checked>
                        <label class="form-check-label" for="workGeneral">General Pest Control</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="type_of_work[]" value="Termite Control" id="workTermite">
                        <label class="form-check-label" for="workTermite">Termite Control</label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="preferred_date" class="form-label">Preferred Date</label>
                    <input type="date" class="form-control" id="preferred_date" name="preferred_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="preferred_time" class="form-label">Preferred Time</label>
                    <input type="time" class="form-control" id="preferred_time" name="preferred_time" value="14:00" required>
                </div>
                
                <div class="mb-3">
                    <label for="frequency" class="form-label">Treatment Frequency</label>
                    <select class="form-control" id="frequency" name="frequency" required>
                        <option value="one-time">One-time Treatment</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="selected_chemicals" class="form-label">Chemical Recommendations</label>
                    <textarea class="form-control" id="selected_chemicals" name="selected_chemicals">[{"id":"1","name":"Imidaclopred","type":"Insecticide","dosage":"20","dosage_unit":"ml","target_pest":"Crawling & Flying Pest"}]</textarea>
                </div>
                
                <div class="mb-3">
                    <label for="attachments" class="form-label">Attachments</label>
                    <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                </div>
                
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
            
            <div class="mt-4" id="result"></div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.getElementById('debugForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('debug_form.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('result').innerHTML = `
                        <div class="alert alert-${data.success ? 'success' : 'danger'}">
                            ${data.message}
                        </div>
                    `;
                })
                .catch(error => {
                    document.getElementById('result').innerHTML = `
                        <div class="alert alert-danger">
                            Error: ${error.message}
                        </div>
                    `;
                });
            });
        </script>
    </body>
    </html>
    <?php
}
?>
