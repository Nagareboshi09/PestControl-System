<?php
session_start();
require_once '../db_connect.php';
require_once '../notification_functions.php';

// Check if user is logged in as technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Create job_order_report table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS job_order_report (
    report_id INT(11) NOT NULL AUTO_INCREMENT,
    job_order_id INT(11) NOT NULL,
    technician_id INT(11) NOT NULL,
    observation_notes TEXT NOT NULL,
    payment_amount DECIMAL(10,2) NOT NULL,
    attachments VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (report_id),
    FOREIGN KEY (job_order_id) REFERENCES job_order(job_order_id),
    FOREIGN KEY (technician_id) REFERENCES technicians(technician_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$conn->query($createTableSQL);

// Add status column to job_order table if it doesn't exist
$checkStatusColumn = $conn->query("SHOW COLUMNS FROM job_order LIKE 'status'");
if ($checkStatusColumn->num_rows == 0) {
    $conn->query("ALTER TABLE job_order ADD COLUMN status VARCHAR(20) DEFAULT 'scheduled'");
}

// Check if it's a GET request to fetch report data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['job_order_id'])) {
    $job_order_id = $_GET['job_order_id'];

    // Get the report data
    $stmt = $conn->prepare("SELECT * FROM job_order_report WHERE job_order_id = ?");
    $stmt->bind_param("i", $job_order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $report = $result->fetch_assoc();
        echo json_encode(['success' => true, 'report' => $report]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No report found']);
    }
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $job_order_id = $_POST['job_order_id'];
    $technician_id = $_SESSION['user_id'];
    $observation_notes = $_POST['observation_notes'];
    $payment_amount = $_POST['payment_amount'];

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
                }
            }
        }
    }

    $attachmentsStr = implode(',', $attachments);

    // Insert job order report
    $stmt = $conn->prepare("
        INSERT INTO job_order_report
        (job_order_id, technician_id, observation_notes, payment_amount, attachments)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisds", $job_order_id, $technician_id, $observation_notes, $payment_amount, $attachmentsStr);

    if ($stmt->execute()) {
        // Update job order status to completed
        $updateStmt = $conn->prepare("UPDATE job_order SET status = 'completed' WHERE job_order_id = ?");
        $updateStmt->bind_param("i", $job_order_id);
        $updateStmt->execute();

        // Get technician name
        $technicianName = $_SESSION['username'];

        // Get job order details to get client information and type of work
        $jobOrderQuery = $conn->prepare("
            SELECT
                jo.type_of_work,
                a.client_id,
                a.client_name,
                c.first_name,
                c.last_name
            FROM job_order jo
            JOIN assessment_report ar ON jo.report_id = ar.report_id
            JOIN appointments a ON ar.appointment_id = a.appointment_id
            LEFT JOIN clients c ON a.client_id = c.client_id
            WHERE jo.job_order_id = ?
        ");
        $jobOrderQuery->bind_param("i", $job_order_id);
        $jobOrderQuery->execute();
        $jobOrderResult = $jobOrderQuery->get_result();

        if ($jobOrderResult && $jobOrderResult->num_rows > 0) {
            $jobOrderData = $jobOrderResult->fetch_assoc();
            $clientId = $jobOrderData['client_id'];
            $clientName = $jobOrderData['client_name'];
            $typeOfWork = $jobOrderData['type_of_work'];

            // Try to include get_notifications.php to use the enhanced notification function
            if (!function_exists('sendJobOrderCompletionNotifications')) {
                @include_once '../get_notifications.php';
            }

            // Check if the enhanced notification function exists
            if (function_exists('sendJobOrderCompletionNotifications')) {
                // Use the enhanced notification function
                error_log("Using enhanced notification function for job completion");
                $notification_result = sendJobOrderCompletionNotifications($job_order_id, $technician_id, $conn);
                error_log("Job completion notification result: " . json_encode($notification_result));
            } else {
                // Fall back to the old notification functions
                error_log("Enhanced notification function not available, using legacy functions");

                // Send notification to client
                $client_result = notifyClientAboutCompletedJob($clientId, $job_order_id, $technician_id, $conn);
                error_log("Client notification result: " . ($client_result ? "success" : "failed"));

                // If the completed job notification function is not available, use the report notification
                if (!$client_result) {
                    error_log("Falling back to job order report notification for client");
                    notifyClientAboutJobOrderReport($clientId, $job_order_id, $technicianName, $typeOfWork);
                }

                // Send notification to all admins
                $adminQuery = $conn->query("SELECT staff_id FROM office_staff");
                if ($adminQuery && $adminQuery->num_rows > 0) {
                    $admin_notifications_sent = 0;
                    while ($adminRow = $adminQuery->fetch_assoc()) {
                        $adminId = $adminRow['staff_id'];

                        // Try the completed job notification first
                        $admin_result = notifyAdminAboutCompletedJob($adminId, $job_order_id, $technician_id, $conn);

                        // If that fails, fall back to the report notification
                        if (!$admin_result) {
                            error_log("Falling back to job order report notification for admin ID: $adminId");
                            $admin_result = notifyAdminAboutJobOrderReport($adminId, $job_order_id, $technicianName, $clientName, $typeOfWork);
                        }

                        if ($admin_result) {
                            $admin_notifications_sent++;
                        }
                    }
                    error_log("Admin notifications sent: $admin_notifications_sent");
                } else {
                    error_log("No admin users found in the database");
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Job order report submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit report: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
