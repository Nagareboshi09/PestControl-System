<?php
session_start();
require_once '../../db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if user is logged in as technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get technician ID
$technician_id = $_SESSION['user_id'];

// Get job order ID from query parameter
$job_order_id = isset($_GET['job_order_id']) ? intval($_GET['job_order_id']) : 0;

if ($job_order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid job order ID']);
    exit;
}

try {
    // Check if there's a job order report for this job order
    $report_stmt = $conn->prepare("SELECT id, chemical_usage FROM job_order_report WHERE job_order_id = ?");
    $report_stmt->bind_param("i", $job_order_id);
    $report_stmt->execute();
    $report_result = $report_stmt->get_result();
    
    if ($report_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No job order report found for this job order']);
        exit;
    }
    
    $report = $report_result->fetch_assoc();
    $report_id = $report['id'];
    $chemical_usage = $report['chemical_usage'];
    
    // Check if there are any chemical usage log entries for this job order
    $log_stmt = $conn->prepare("SELECT 
                                    cul.chemical_id, 
                                    cul.quantity_used, 
                                    cul.usage_date,
                                    ci.chemical_name,
                                    ci.type,
                                    ci.quantity as current_quantity,
                                    ci.unit
                                FROM chemical_usage_log cul
                                JOIN chemical_inventory ci ON cul.chemical_id = ci.id
                                WHERE cul.job_order_id = ?");
    $log_stmt->bind_param("i", $job_order_id);
    $log_stmt->execute();
    $log_result = $log_stmt->get_result();
    
    $updated_chemicals = [];
    
    if ($log_result->num_rows > 0) {
        // Chemical usage log entries found, use them to build the response
        while ($log = $log_result->fetch_assoc()) {
            // Get the previous quantity by adding the used quantity to the current quantity
            $previous_quantity = $log['current_quantity'] + $log['quantity_used'];
            
            $updated_chemicals[] = [
                'id' => $log['chemical_id'],
                'name' => $log['chemical_name'],
                'type' => $log['type'],
                'previous_quantity' => number_format($previous_quantity, 2),
                'used_quantity' => number_format($log['quantity_used'], 2),
                'new_quantity' => number_format($log['current_quantity'], 2),
                'unit' => $log['unit'],
                'usage_date' => $log['usage_date']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Chemical inventory update verified from usage log',
            'updated_chemicals' => $updated_chemicals,
            'source' => 'usage_log'
        ]);
        exit;
    }
    
    // If no usage log entries found, try to parse the chemical_usage JSON from the report
    if ($chemical_usage) {
        $chemical_usage_data = json_decode($chemical_usage, true);
        
        if (is_array($chemical_usage_data) && !empty($chemical_usage_data)) {
            foreach ($chemical_usage_data as $chem) {
                // Skip chemicals with zero or invalid dosage
                if (!isset($chem['dosage']) || floatval($chem['dosage']) <= 0) {
                    continue;
                }
                
                // Get the current quantity from the database
                $chem_stmt = $conn->prepare("SELECT chemical_name, type, quantity, unit FROM chemical_inventory WHERE id = ?");
                $chem_id = isset($chem['id']) ? intval($chem['id']) : 0;
                
                if ($chem_id <= 0) {
                    continue;
                }
                
                $chem_stmt->bind_param("i", $chem_id);
                $chem_stmt->execute();
                $chem_result = $chem_stmt->get_result();
                
                if ($chem_result->num_rows > 0) {
                    $chem_data = $chem_result->fetch_assoc();
                    
                    // Calculate the previous quantity (current + used)
                    $dosage = floatval($chem['dosage']);
                    $current_quantity = floatval($chem_data['quantity']);
                    $previous_quantity = $current_quantity + $dosage;
                    
                    $updated_chemicals[] = [
                        'id' => $chem_id,
                        'name' => $chem_data['chemical_name'],
                        'type' => $chem_data['type'],
                        'previous_quantity' => number_format($previous_quantity, 2),
                        'used_quantity' => number_format($dosage, 2),
                        'new_quantity' => number_format($current_quantity, 2),
                        'unit' => $chem_data['unit'],
                        'estimated' => true
                    ];
                }
            }
            
            if (!empty($updated_chemicals)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Chemical inventory update estimated from report data',
                    'updated_chemicals' => $updated_chemicals,
                    'source' => 'report_data',
                    'estimated' => true
                ]);
                exit;
            }
        }
    }
    
    // If we get here, no chemical usage data was found
    echo json_encode([
        'success' => false,
        'message' => 'No chemical usage data found for this job order',
        'updated_chemicals' => []
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error checking inventory update: ' . $e->getMessage(),
        'error_details' => $e->getMessage()
    ]);
}

// Close database connection
$conn->close();
?>
