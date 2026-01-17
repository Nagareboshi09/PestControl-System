<?php
session_start();
require_once '../db_connect.php';
require_once '../notification_functions.php';
require_once '../chemical_inventory_functions.php';

// Check if user is logged in as technician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'technician') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Set up logging for debugging
$log_file = 'job_report_log.txt';
file_put_contents($log_file, "Job Report Submission: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get the technician ID from the session
$technician_id = $_SESSION['user_id'];

// Get form data
$job_order_id = isset($_POST['job_order_id']) ? intval($_POST['job_order_id']) : 0;
$observation_notes = isset($_POST['observation_notes']) ? $_POST['observation_notes'] : '';
$recommendation = isset($_POST['recommendation']) ? $_POST['recommendation'] : '';

// Log received data
file_put_contents($log_file, "Job Order ID: $job_order_id\n", FILE_APPEND);
file_put_contents($log_file, "Observation Notes: $observation_notes\n", FILE_APPEND);
file_put_contents($log_file, "Recommendation: $recommendation\n", FILE_APPEND);

// Validate required fields
if (!$job_order_id || !$observation_notes || !$recommendation) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Check if the technician is assigned to this job order and is the primary technician
$check_query = "SELECT jo.job_order_id, jo.report_id, jo.chemical_recommendations, jot.is_primary
                FROM job_order jo
                JOIN job_order_technicians jot ON jo.job_order_id = jot.job_order_id
                WHERE jo.job_order_id = ? AND jot.technician_id = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("ii", $job_order_id, $technician_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not authorized to submit a report for this job order']);
    exit;
}

$job_data = $check_result->fetch_assoc();
$is_primary = $job_data['is_primary'];
$report_id = $job_data['report_id'];
$chemical_recommendations = $job_data['chemical_recommendations'];

// Only primary technician can submit reports
if (!$is_primary) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only the primary technician can submit reports for this job order']);
    exit;
}

// Process chemical usage data
$chemical_usage = [];
if (isset($_POST['chemical_name']) && is_array($_POST['chemical_name'])) {
    $chemical_count = count($_POST['chemical_name']);

    for ($i = 0; $i < $chemical_count; $i++) {
        if (isset($_POST['chemical_name'][$i]) && isset($_POST['chemical_dosage'][$i])) {
            $chemical_usage[] = [
                'name' => $_POST['chemical_name'][$i],
                'type' => $_POST['chemical_type'][$i] ?? '',
                'target_pest' => $_POST['chemical_target_pest'][$i] ?? '',
                'dosage' => floatval($_POST['chemical_dosage'][$i]),
                'dosage_unit' => $_POST['chemical_dosage_unit'][$i] ?? 'ml'
            ];
        }
    }
}

// Log chemical usage data
file_put_contents($log_file, "Chemical Usage: " . json_encode($chemical_usage) . "\n", FILE_APPEND);

// Handle file uploads
$attachments = [];
if (!empty($_FILES['attachments'])) {
    $uploadDir = '../uploads/';
    foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {
        if ($_FILES['attachments']['error'][$key] === 0) {
            $fileName = uniqid() . '_' . basename($_FILES['attachments']['name'][$key]);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($tmpName, $targetPath)) {
                $attachments[] = $fileName;
            } else {
                file_put_contents($log_file, "Failed to move uploaded file: $tmpName to $targetPath\n", FILE_APPEND);
            }
        } else {
            file_put_contents($log_file, "File upload error: " . $_FILES['attachments']['error'][$key] . "\n", FILE_APPEND);
        }
    }
}

// Convert attachments array to comma-separated string
$attachments_str = implode(',', $attachments);

// Start transaction
$conn->begin_transaction();

try {
    // Insert job order report
    $insert_query = "INSERT INTO job_order_report (job_order_id, technician_id, observation_notes, recommendation, attachments, chemical_usage)
                    VALUES (?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $chemical_usage_json = json_encode($chemical_usage);
    $insert_stmt->bind_param("iissss", $job_order_id, $technician_id, $observation_notes, $recommendation, $attachments_str, $chemical_usage_json);
    $insert_stmt->execute();

    // Update job order status to completed
    $update_query = "UPDATE job_order SET status = 'completed' WHERE job_order_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $job_order_id);
    $update_stmt->execute();

    // Process chemical inventory deduction
    if (!empty($chemical_usage)) {
        foreach ($chemical_usage as $chemical) {
            // Find the chemical in the inventory by name and type
            $find_chemical_query = "SELECT id, quantity, unit FROM chemical_inventory
                                   WHERE chemical_name = ? AND type = ?
                                   ORDER BY expiration_date ASC";
            $find_stmt = $conn->prepare($find_chemical_query);
            $find_stmt->bind_param("ss", $chemical['name'], $chemical['type']);
            $find_stmt->execute();
            $find_result = $find_stmt->get_result();

            if ($find_result->num_rows > 0) {
                $inventory_item = $find_result->fetch_assoc();
                $chemical_id = $inventory_item['id'];
                $current_quantity = $inventory_item['quantity'];
                $unit = $inventory_item['unit'];

                // Convert dosage to the same unit as inventory if needed
                $deduction_amount = $chemical['dosage'];

                // Handle unit conversion if necessary
                if ($chemical['dosage_unit'] !== $unit) {
                    // Convert between ml and L
                    if ($chemical['dosage_unit'] === 'ml' && $unit === 'L') {
                        $deduction_amount = $deduction_amount / 1000;
                    } else if ($chemical['dosage_unit'] === 'L' && $unit === 'ml') {
                        $deduction_amount = $deduction_amount * 1000;
                    }
                    // Convert between g and kg
                    else if ($chemical['dosage_unit'] === 'g' && $unit === 'kg') {
                        $deduction_amount = $deduction_amount / 1000;
                    } else if ($chemical['dosage_unit'] === 'kg' && $unit === 'g') {
                        $deduction_amount = $deduction_amount * 1000;
                    }
                }

                // Calculate new quantity
                $new_quantity = max(0, $current_quantity - $deduction_amount);

                // Update the inventory using the unified function
                $update_result = update_chemical_inventory_quantity($conn, $chemical_id, $new_quantity, "mysqli");

                // Log the chemical usage
                $usage_notes = "Used for job order #$job_order_id";
                $log_result = log_chemical_usage($conn, $chemical_id, $technician_id, $job_order_id, $deduction_amount, $usage_notes, "mysqli");

                // Log the inventory update
                file_put_contents($log_file, "Updated chemical inventory: ID $chemical_id, Name: {$chemical['name']}, Old quantity: $current_quantity, Deduction: $deduction_amount, New quantity: $new_quantity\n", FILE_APPEND);

                // Check if the chemical is now low in stock or out of stock
                if ($new_quantity <= 0) {
                    // Create notification for out of stock
                    $notification_message = "Chemical {$chemical['name']} ({$chemical['type']}) is now out of stock.";
                    createChemicalNotification($notification_message, 'out_of_stock', $chemical_id);
                } else if ($new_quantity <= 5) { // Threshold for low stock
                    // Create notification for low stock
                    $notification_message = "Chemical {$chemical['name']} ({$chemical['type']}) is running low. Only $new_quantity $unit left.";
                    createChemicalNotification($notification_message, 'low_stock', $chemical_id);
                }
            } else {
                // Log that the chemical was not found in inventory
                file_put_contents($log_file, "Chemical not found in inventory: {$chemical['name']} ({$chemical['type']})\n", FILE_APPEND);
            }
        }
    }

    // Get client information for notification
    $client_query = "SELECT c.client_id, c.first_name, c.last_name, a.client_name
                    FROM job_order jo
                    JOIN assessment_report ar ON jo.report_id = ar.report_id
                    JOIN appointments a ON ar.appointment_id = a.appointment_id
                    LEFT JOIN clients c ON a.client_id = c.client_id
                    WHERE jo.job_order_id = ?";
    $client_stmt = $conn->prepare($client_query);
    $client_stmt->bind_param("i", $job_order_id);
    $client_stmt->execute();
    $client_result = $client_stmt->get_result();

    if ($client_result->num_rows > 0) {
        $client_data = $client_result->fetch_assoc();
        $client_id = $client_data['client_id'];
        $client_name = $client_data['client_name'] ?? $client_data['first_name'] . ' ' . $client_data['last_name'];

        // Create notification for client
        if ($client_id) {
            notifyClientAboutCompletedJob($client_id, $job_order_id, $technician_id);
        }
    }

    // Create notification for admin
    $admin_query = "SELECT staff_id FROM office_staff";
    $admin_result = $conn->query($admin_query);
    while ($admin = $admin_result->fetch_assoc()) {
        notifyAdminAboutCompletedJob($admin['staff_id'], $job_order_id, $technician_id);
    }

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Job order report submitted successfully',
        'job_order_id' => $job_order_id
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();

    // Log the error
    file_put_contents($log_file, "Error: " . $e->getMessage() . "\n", FILE_APPEND);

    // Return error response
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while submitting the report: ' . $e->getMessage()]);
}

/**
 * Create a notification about chemical inventory status
 */
function createChemicalNotification($message, $type, $chemical_id) {
    global $conn;

    // Get all admin IDs (office staff)
    $admin_query = $conn->query("SELECT staff_id FROM office_staff");

    while ($admin = $admin_query->fetch_assoc()) {
        $staff_id = $admin['staff_id'];

        // Insert notification
        $notification_query = "INSERT INTO notifications (user_id, user_role, message, type, reference_id, is_read)
                              VALUES (?, 'admin', ?, ?, ?, 0)";
        $notification_stmt = $conn->prepare($notification_query);
        $notification_stmt->bind_param("issi", $staff_id, $message, $type, $chemical_id);
        $notification_stmt->execute();
    }
}
?>
