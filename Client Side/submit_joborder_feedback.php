<?php
session_start();
require_once '../db_connect.php';
require_once '../notification_functions.php';

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check required fields
if (empty($_POST['job_order_id']) || empty($_POST['rating']) || !isset($_POST['technician_arrived']) || !isset($_POST['job_completed'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$client_id = $_SESSION['client_id'];
$job_order_id = intval($_POST['job_order_id']);
$rating = intval($_POST['rating']);
$comments = isset($_POST['comments']) ? $_POST['comments'] : '';
$technician_arrived = isset($_POST['technician_arrived']) ? 1 : 0;
$job_completed = isset($_POST['job_completed']) ? 1 : 0;
$verification_notes = isset($_POST['verification_notes']) ? $_POST['verification_notes'] : '';

// Validate rating (0-5)
if ($rating < 0 || $rating > 5) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid rating value']);
    exit;
}

// Check if feedback already exists for this job order
$check_stmt = $conn->prepare("SELECT feedback_id FROM joborder_feedback WHERE job_order_id = ? AND client_id = ?");
$check_stmt->bind_param("ii", $job_order_id, $client_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Feedback already submitted for this job order']);
    exit;
}

// Get technician_id from the job order
$tech_stmt = $conn->prepare("
    SELECT jot.technician_id
    FROM job_order jo
    JOIN job_order_technicians jot ON jo.job_order_id = jot.job_order_id
    WHERE jo.job_order_id = ?
    LIMIT 1
");
$tech_stmt->bind_param("i", $job_order_id);
$tech_stmt->execute();
$tech_result = $tech_stmt->get_result();

if ($tech_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Job order not found or no technician assigned']);
    exit;
}

$tech_data = $tech_result->fetch_assoc();
$technician_id = $tech_data['technician_id'];

try {
    // Insert feedback with verification fields
    $stmt = $conn->prepare("INSERT INTO joborder_feedback (job_order_id, client_id, technician_id, rating, comments, technician_arrived, job_completed, verification_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiisiss", $job_order_id, $client_id, $technician_id, $rating, $comments, $technician_arrived, $job_completed, $verification_notes);
    $result = $stmt->execute();

    if ($result) {
        // Update the job order status to 'completed' if it's not already
        $update_stmt = $conn->prepare("UPDATE job_order SET status = 'completed' WHERE job_order_id = ? AND status != 'completed'");
        $update_stmt->bind_param("i", $job_order_id);
        $update_stmt->execute();

        // Get job order and client details for notification
        $job_stmt = $conn->prepare("
            SELECT
                jo.job_order_id,
                a.client_name,
                jo.type_of_work,
                t.tech_fname,
                t.tech_lname
            FROM job_order jo
            JOIN assessment_report ar ON jo.report_id = ar.report_id
            JOIN appointments a ON ar.appointment_id = a.appointment_id
            LEFT JOIN job_order_technicians jot ON jo.job_order_id = jot.job_order_id
            LEFT JOIN technicians t ON jot.technician_id = t.technician_id
            WHERE jo.job_order_id = ?
            LIMIT 1
        ");
        $job_stmt->bind_param("i", $job_order_id);
        $job_stmt->execute();
        $job_result = $job_stmt->get_result();
        $job_data = $job_result->fetch_assoc();

        // Create notification for admin users when client submits feedback
        if ($job_data) {
            // Get client name and job type from the job order data
            $client_name = $job_data['client_name'];
            $job_type = $job_data['type_of_work'];
            $tech_name = $job_data['tech_fname'] . ' ' . $job_data['tech_lname'];

            // Create notification title and message
            $title = "Job Order Feedback Received";

            // Customize message based on feedback content
            if ($technician_arrived == 1 && $job_completed == 1) {
                $message = "Client {$client_name} has verified technician {$tech_name}'s work for the {$job_type} job order with a rating of {$rating}/5.";
            } else {
                $message = "Client {$client_name} has submitted feedback for the {$job_type} job order with a rating of {$rating}/5.";
            }

            // Get all admin users
            $admin_query = $conn->query("SELECT staff_id FROM office_staff");
            if ($admin_query && $admin_query->num_rows > 0) {
                while ($admin_row = $admin_query->fetch_assoc()) {
                    $admin_id = $admin_row['staff_id'];
                    // Create notification for each admin
                    createNotification(
                        $admin_id,
                        'admin',
                        $title,
                        $message,
                        $job_order_id,
                        'job_order_feedback',
                        $conn
                    );
                }
            }
        }

        // Get sort parameter if provided
        $sort = isset($_POST['sort']) ? $_POST['sort'] : 'date_asc';

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Feedback submitted successfully',
            'job_order_id' => $job_order_id,
            'sort' => $sort
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to submit feedback']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
