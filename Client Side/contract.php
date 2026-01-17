<?php
include '../db_connect.php';
include '../chemical_display_functions.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract | MacJ Pest Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/sidebar-fix.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/client-common.css">
    <link rel="stylesheet" href="css/calendar.css">
    <!-- Removed unnecessary CSS files -->
    <link rel="stylesheet" href="css/notifications.css">
    <!-- Leaflet.js for OpenStreetMap -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
    <!-- jsPDF library for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-spinner {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
        }

        .loading-spinner i {
            color: #1e3a8a;
            margin-bottom: 15px;
        }

        .loading-spinner p {
            margin: 0;
            font-weight: bold;
            color: #333;
        }

        .loading-spinner .small-text {
            font-size: 0.8rem;
            font-weight: normal;
            margin-top: 10px;
            color: #666;
        }

        /* Ensure validation messages are italicized */
        .invalid-feedback, .text-danger, .error-message {
            font-style: italic !important;
            color: var(--error-color) !important;
        }

        /* Notification Dropdown Override */
        .notification-dropdown.show {
            display: block !important;
            z-index: 9999;
        }

        /* Ensure notification container is properly positioned */
        .notification-container {
            position: relative;
            display: inline-block;
        }

        /* Download button styling */
        .btn-download {
            background-color: #3B82F6;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-download:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-download i {
            font-size: 1rem;
        }

        /* Progress Bar Styles */
        .progress-container {
            margin: 20px 0;
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e0e0e0;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .progress-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #4285f4;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .progress-title i {
            background-color: #4285f4;
            color: white;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .progress-stats {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .progress-stat {
            background-color: #e8f4ff;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #4285f4;
            border: 1px solid rgba(66, 133, 244, 0.2);
        }

        .progress-bar-container {
            height: 12px;
            background-color: #e9ecef;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 10px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #4285f4 0%, #34a853 100%);
            border-radius: 6px;
            transition: width 0.6s ease;
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #6c757d;
        }

        .progress-percentage {
            font-weight: 600;
            color: #4285f4;
        }
    </style>
</head>
<body class="contract">
    <!-- PDF Loading Overlay -->
    <div id="pdfLoadingOverlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin fa-3x"></i>
            <p>Generating PDF...</p>
            <p class="small-text">This may take a few moments</p>
        </div>
    </div>

    <!-- Header -->
    <header class="header">
        <div class="header-title">
            <h1>Client Portal</h1>
        </div>
        <div class="user-menu">
            <!-- Notification Icon -->
            <div class="notification-container">
                <i class="fas fa-bell notification-icon"></i>
                <span class="notification-badge" style="display: none;">0</span>

                <!-- Notification Dropdown -->
                <div class="notification-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <span class="mark-all-read">Mark all as read</span>
                    </div>
                    <ul class="notification-list">
                        <!-- Notifications will be loaded here -->
                    </ul>
                </div>
            </div>

            <div class="user-info">
                <div>
                    <div class="user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Client') ?></div>
                    <div class="user-role">Client</div>
                </div>
            </div>
        </div>
    </header>
    <button id="menuToggle"><i class="fas fa-bars"></i></button>

        <aside id="sidebar">
            <div class="sidebar-header">
                <h2>MacJ Pest Control</h2>
                <h3>Welcome, <?= htmlspecialchars($_SESSION['fullname'] ?? '') ?></h3>
            </div>
            <nav class="sidebar-menu">
                <a href="schedule.php">
                    <i class="fas fa-calendar-alt fa-icon"></i>
                    Schedule Appointment
                </a>
                <a href="profile.php">
                    <i class="fas fa-user fa-icon"></i>
                    My Profile
                </a>
                <a href="inspection_report.php">
                    <i class="fas fa-clipboard-check fa-icon"></i>
                    Inspection Report
                </a>
                <a href="contract.php" class="active">
                    <i class="fas fa-clipboard-check fa-icon"></i>
                    Contract
                </a>
                <a href="job_order_report.php">
                    <i class="fas fa-file-alt fa-icon"></i>
                    Job Order Report
                </a>
                <a href="SignOut.php">
                    <i class="fas fa-sign-out-alt fa-icon"></i>
                    Logout
                </a>
            </nav>
            <div class="sidebar-footer">
                <p>&copy; <?= date('Y') ?> MacJ Pest Control</p>
                <a href="https://www.facebook.com/MACJPEST" target="_blank"><i class="fab fa-facebook"></i> Facebook</a>
            </div>
        </aside>

        <main class="main-content" id="mainContent">
            <div class="page-header">
                <div>
                    <h1>Contract</h1>
                    <p>View and manage your recurring treatment contracts</p>
                </div>
                <div>
                    <p class="text-light"><?= date('l, F j, Y') ?></p>
                </div>
            </div>

                <div class="contract-container">

                <?php
                // Debug information
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    echo "<div style='display:none;'>";
                    echo "POST data: ";
                    print_r($_POST);
                    echo "<br>SESSION data: ";
                    print_r($_SESSION);
                    echo "</div>";
                }

                // Process form submission for treatment approval
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_order_id']) && isset($_POST['approval_action'])) {
                    $job_order_id = $conn->real_escape_string($_POST['job_order_id']);
                    $approval_action = $conn->real_escape_string($_POST['approval_action']);
                    $client_id = $_SESSION['client_id'] ?? 0;
                    $approval_date = date('Y-m-d H:i:s');

                    // Validate that this job order belongs to the current client
                    $validate_query = "SELECT jo.job_order_id, a.client_id, jo.client_approval_status
                                      FROM job_order jo
                                      JOIN assessment_report ar ON jo.report_id = ar.report_id
                                      JOIN appointments a ON ar.appointment_id = a.appointment_id
                                      WHERE jo.job_order_id = ?";
                    $validate_stmt = $conn->prepare($validate_query);
                    $validate_stmt->bind_param("i", $job_order_id);
                    $validate_stmt->execute();
                    $validate_result = $validate_stmt->get_result();
                    $validation_row = $validate_result->fetch_assoc();

                    // Debug validation
                    echo "<div style='display:none;'>";
                    echo "Validation query: " . $validate_query . "<br>";
                    echo "Job order ID: " . $job_order_id . "<br>";
                    echo "Client ID from session: " . $client_id . "<br>";
                    echo "Validation result: ";
                    print_r($validation_row);
                    echo "</div>";

                    if ($validate_result->num_rows > 0 && $validation_row['client_id'] == $client_id) {
                        // Check if the job order is already approved or declined
                        if ($validation_row['client_approval_status'] !== 'pending') {
                            $error_message = "This job order has already been " . $validation_row['client_approval_status'] . ".";
                        } else {
                            // Valid job order for this client

                        if ($approval_action === 'approve') {
                            // Debug information
                            error_log("Starting approval process for job_order_id: $job_order_id");

                            try {
                                // Set default values for payment
                                $payment_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0;
                                $payment_proof = '';

                                // Approve the recurring schedule with payment information
                                $update_query = "UPDATE job_order SET client_approval_status = 'approved', client_approval_date = ?,
                                                payment_amount = ?, payment_proof = ?, payment_date = ? WHERE job_order_id = ?";
                                $update_stmt = $conn->prepare($update_query);
                                if (!$update_stmt) {
                                    throw new Exception("Prepare statement failed: " . $conn->error);
                                }

                                $update_stmt->bind_param("sdssi", $approval_date, $payment_amount, $payment_proof, $approval_date, $job_order_id);
                                $result = $update_stmt->execute();
                                if (!$result) {
                                    throw new Exception("Execute statement failed: " . $update_stmt->error);
                                }

                                error_log("Successfully updated job_order_id: $job_order_id to approved");

                                // Get report_id, frequency, and other details for related job orders
                                $get_related_query = "SELECT jo.report_id, jo.frequency, jo.type_of_work, jo.preferred_date, jo.preferred_time,
                                                     jo.chemical_recommendations, jo.cost, ar.area, ar.pest_types, ar.problem_area
                                                     FROM job_order jo
                                                     JOIN assessment_report ar ON jo.report_id = ar.report_id
                                                     WHERE jo.job_order_id = ?";
                                $get_related_stmt = $conn->prepare($get_related_query);
                                if (!$get_related_stmt) {
                                    throw new Exception("Prepare statement failed for related query: " . $conn->error);
                                }

                                $get_related_stmt->bind_param("i", $job_order_id);
                                $get_related_stmt->execute();
                                $related_result = $get_related_stmt->get_result();
                                $related_row = $related_result->fetch_assoc();

                                if ($related_row) {
                                    $report_id = $related_row['report_id'];
                                    $frequency = $related_row['frequency'];
                                    $type_of_work = $related_row['type_of_work'];
                                    $preferred_date = $related_row['preferred_date'];
                                    $preferred_time = $related_row['preferred_time'];
                                    $chemical_recommendations = $related_row['chemical_recommendations'];
                                    $cost = $related_row['cost'];
                                    $area = $related_row['area'];
                                    $pest_types = $related_row['pest_types'];
                                    $problem_area = $related_row['problem_area'];

                                    error_log("Found related job orders with report_id: $report_id and frequency: $frequency");

                                    // Update all related job orders in a single query
                                    $update_all_query = "UPDATE job_order SET client_approval_status = 'approved', client_approval_date = ?
                                                        WHERE report_id = ? AND frequency = ? AND job_order_id != ?";
                                    $update_all_stmt = $conn->prepare($update_all_query);
                                    if (!$update_all_stmt) {
                                        throw new Exception("Prepare statement failed for update all: " . $conn->error);
                                    }

                                    $update_all_stmt->bind_param("sisi", $approval_date, $report_id, $frequency, $job_order_id);
                                    $update_all_result = $update_all_stmt->execute();
                                    if (!$update_all_result) {
                                        throw new Exception("Execute statement failed for update all: " . $update_all_stmt->error);
                                    }

                                    error_log("Successfully updated all related job orders");

                                    // Check if we need to create additional recurring job orders based on frequency
                                    if ($frequency !== 'one-time') {
                                        // Get the latest scheduled job order date
                                        $latest_date_query = "SELECT MAX(preferred_date) as latest_date FROM job_order
                                                             WHERE report_id = ? AND frequency = ?";
                                        $latest_date_stmt = $conn->prepare($latest_date_query);
                                        $latest_date_stmt->bind_param("is", $report_id, $frequency);
                                        $latest_date_stmt->execute();
                                        $latest_date_result = $latest_date_stmt->get_result();
                                        $latest_date_row = $latest_date_result->fetch_assoc();
                                        $latest_date = $latest_date_row['latest_date'];

                                        // Calculate end date (1 year from now)
                                        $end_date = date('Y-m-d', strtotime('+1 year'));

                                        // If the latest date is less than the end date, create more job orders
                                        if (strtotime($latest_date) < strtotime($end_date)) {
                                            $current_date = $latest_date;
                                            $interval = '';

                                            // Set the appropriate interval based on frequency
                                            switch ($frequency) {
                                                case 'weekly':
                                                    $interval = '1 week';
                                                    break;
                                                case 'monthly':
                                                    $interval = '1 month';
                                                    break;
                                                case 'quarterly':
                                                    $interval = '3 months';
                                                    break;
                                            }

                                            // Create recurring job orders
                                            $insert_stmt = $conn->prepare("INSERT INTO job_order (report_id, type_of_work, preferred_date, preferred_time,
                                                                         frequency, client_approval_status, client_approval_date, chemical_recommendations, cost)
                                                                         VALUES (?, ?, ?, ?, ?, 'approved', ?, ?, ?)");

                                            // Start from the next occurrence
                                            $current_date = date('Y-m-d', strtotime($current_date . ' + ' . $interval));

                                            $recurring_job_ids = [];
                                            while (strtotime($current_date) <= strtotime($end_date)) {
                                                $insert_stmt->bind_param("issssssd", $report_id, $type_of_work, $current_date, $preferred_time,
                                                                      $frequency, $approval_date, $chemical_recommendations, $cost);
                                                $insert_stmt->execute();
                                                $recurring_job_id = $conn->insert_id;
                                                $recurring_job_ids[] = $recurring_job_id;

                                                // Move to the next date
                                                $current_date = date('Y-m-d', strtotime($current_date . ' + ' . $interval));
                                            }

                                            error_log("Created " . count($recurring_job_ids) . " additional recurring job orders");
                                        }
                                    }
                                }

                                // Get client name for notification
                                $client_name_query = "SELECT client_name FROM clients WHERE client_id = ?";
                                $client_name_stmt = $conn->prepare($client_name_query);
                                $client_name_stmt->bind_param("i", $client_id);
                                $client_name_stmt->execute();
                                $client_name_result = $client_name_stmt->get_result();
                                $client_name_row = $client_name_result->fetch_assoc();
                                $client_name = $client_name_row ? $client_name_row['client_name'] : "Client #$client_id";

                                // Create a single notification for the first admin
                                $admin_query = "SELECT staff_id FROM office_staff LIMIT 1";
                                $admin_result = $conn->query($admin_query);
                                if ($admin_result && $admin_row = $admin_result->fetch_assoc()) {
                                    $admin_id = $admin_row['staff_id'];

                                    // Use the new notification function
                                    include_once '../notification_functions.php';
                                    notifyAdminAboutQuotationResponse(
                                        $admin_id,
                                        $job_order_id,
                                        $client_id,
                                        $client_name,
                                        $type_of_work,
                                        $frequency,
                                        'approved'
                                    );


                                }

                                $success_message = "You have approved the recurring treatment schedule.";
                                error_log("Approval process completed successfully");

                            } catch (Exception $e) {
                                error_log("Error in approval process: " . $e->getMessage());
                                $error_message = "An error occurred while processing your request: " . $e->getMessage();
                            }
                        } elseif ($approval_action === 'decline') {
                            // Debug information
                            error_log("Starting decline process for job_order_id: $job_order_id");

                            try {
                                // Decline the recurring schedule - SIMPLIFIED VERSION
                                $update_query = "UPDATE job_order SET client_approval_status = 'declined', client_approval_date = ? WHERE job_order_id = ?";
                                $update_stmt = $conn->prepare($update_query);
                                if (!$update_stmt) {
                                    throw new Exception("Prepare statement failed: " . $conn->error);
                                }

                                $update_stmt->bind_param("si", $approval_date, $job_order_id);
                                $result = $update_stmt->execute();
                                if (!$result) {
                                    throw new Exception("Execute statement failed: " . $update_stmt->error);
                                }

                                error_log("Successfully updated job_order_id: $job_order_id to declined");

                                // Get report_id and frequency for related job orders
                                $get_related_query = "SELECT report_id, frequency FROM job_order WHERE job_order_id = ?";
                                $get_related_stmt = $conn->prepare($get_related_query);
                                if (!$get_related_stmt) {
                                    throw new Exception("Prepare statement failed for related query: " . $conn->error);
                                }

                                $get_related_stmt->bind_param("i", $job_order_id);
                                $get_related_stmt->execute();
                                $related_result = $get_related_stmt->get_result();
                                $related_row = $related_result->fetch_assoc();

                                if ($related_row) {
                                    $report_id = $related_row['report_id'];
                                    $frequency = $related_row['frequency'];

                                    error_log("Found related job orders with report_id: $report_id and frequency: $frequency");

                                    // Update all related job orders in a single query
                                    $update_all_query = "UPDATE job_order SET client_approval_status = 'declined', client_approval_date = ?
                                                        WHERE report_id = ? AND frequency = ? AND job_order_id != ?";
                                    $update_all_stmt = $conn->prepare($update_all_query);
                                    if (!$update_all_stmt) {
                                        throw new Exception("Prepare statement failed for update all: " . $conn->error);
                                    }

                                    $update_all_stmt->bind_param("sisi", $approval_date, $report_id, $frequency, $job_order_id);
                                    $update_all_result = $update_all_stmt->execute();
                                    if (!$update_all_result) {
                                        throw new Exception("Execute statement failed for update all: " . $update_all_stmt->error);
                                    }

                                    error_log("Successfully updated all related job orders");
                                }

                                // Get client name and job details for notification
                                $client_details_query = "SELECT c.client_name, jo.type_of_work, jo.frequency
                                                      FROM clients c
                                                      JOIN job_order jo ON jo.job_order_id = ?
                                                      WHERE c.client_id = ?";
                                $client_details_stmt = $conn->prepare($client_details_query);
                                $client_details_stmt->bind_param("ii", $job_order_id, $client_id);
                                $client_details_stmt->execute();
                                $client_details_result = $client_details_stmt->get_result();
                                $client_details = $client_details_result->fetch_assoc();

                                $client_name = $client_details ? $client_details['client_name'] : "Client #$client_id";
                                $type_of_work = $client_details ? $client_details['type_of_work'] : "";
                                $frequency = $client_details ? $client_details['frequency'] : "";

                                // Create a single notification for the first admin
                                $admin_query = "SELECT staff_id FROM office_staff LIMIT 1";
                                $admin_result = $conn->query($admin_query);
                                if ($admin_result && $admin_row = $admin_result->fetch_assoc()) {
                                    $admin_id = $admin_row['staff_id'];

                                    // Use the new notification function
                                    include_once '../notification_functions.php';
                                    notifyAdminAboutQuotationResponse(
                                        $admin_id,
                                        $job_order_id,
                                        $client_id,
                                        $client_name,
                                        $type_of_work,
                                        $frequency,
                                        'declined'
                                    );

                                    error_log("Notification created for admin ID: $admin_id about declined quotation");
                                }

                                $success_message = "You have declined the recurring treatment schedule. An administrator will contact you to discuss alternatives.";
                                error_log("Decline process completed successfully");

                            } catch (Exception $e) {
                                error_log("Error in decline process: " . $e->getMessage());
                                $error_message = "An error occurred while processing your request: " . $e->getMessage();
                            }
                        } elseif ($approval_action === 'one-time') {
                            // Debug information
                            error_log("Starting one-time process for job_order_id: $job_order_id");

                            try {
                                // Set default values for payment
                                $payment_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0;
                                $payment_proof = '';

                                // Convert to one-time treatment with payment information
                                $update_query = "UPDATE job_order SET client_approval_status = 'one-time', frequency = 'one-time',
                                                client_approval_date = ?, payment_amount = ?, payment_proof = ?, payment_date = ?
                                                WHERE job_order_id = ?";
                                $update_stmt = $conn->prepare($update_query);
                                if (!$update_stmt) {
                                    throw new Exception("Prepare statement failed: " . $conn->error);
                                }

                                $update_stmt->bind_param("sdssi", $approval_date, $payment_amount, $payment_proof, $approval_date, $job_order_id);
                                $result = $update_stmt->execute();
                                if (!$result) {
                                    throw new Exception("Execute statement failed: " . $update_stmt->error);
                                }

                                error_log("Successfully updated job_order_id: $job_order_id to one-time");

                                // Get report_id and frequency for related job orders
                                $get_related_query = "SELECT jo.report_id, jo.frequency, jo.type_of_work, jo.preferred_date, jo.preferred_time,
                                                     jo.chemical_recommendations, jo.cost
                                                     FROM job_order jo
                                                     WHERE jo.job_order_id = ?";
                                $get_related_stmt = $conn->prepare($get_related_query);
                                if (!$get_related_stmt) {
                                    throw new Exception("Prepare statement failed for related query: " . $conn->error);
                                }

                                $get_related_stmt->bind_param("i", $job_order_id);
                                $get_related_stmt->execute();
                                $related_result = $get_related_stmt->get_result();
                                $related_row = $related_result->fetch_assoc();

                                if ($related_row) {
                                    $report_id = $related_row['report_id'];
                                    $original_frequency = $related_row['frequency'];
                                    $type_of_work = $related_row['type_of_work'];
                                    $preferred_date = $related_row['preferred_date'];
                                    $preferred_time = $related_row['preferred_time'];
                                    $chemical_recommendations = $related_row['chemical_recommendations'];
                                    $cost = $related_row['cost'];

                                    error_log("Found related job orders with report_id: $report_id and frequency: $original_frequency");

                                    // Delete technician assignments for related job orders
                                    $delete_techs_query = "DELETE jot FROM job_order_technicians jot
                                                         JOIN job_order jo ON jot.job_order_id = jo.job_order_id
                                                         WHERE jo.report_id = ? AND jo.frequency = ? AND jo.job_order_id != ?";
                                    $delete_techs_stmt = $conn->prepare($delete_techs_query);
                                    if ($delete_techs_stmt) {
                                        $delete_techs_stmt->bind_param("isi", $report_id, $original_frequency, $job_order_id);
                                        $delete_techs_stmt->execute();
                                        error_log("Deleted technician assignments for related job orders");
                                    }

                                    // Delete related job orders
                                    $delete_all_query = "DELETE FROM job_order WHERE report_id = ? AND frequency = ? AND job_order_id != ?";
                                    $delete_all_stmt = $conn->prepare($delete_all_query);
                                    if (!$delete_all_stmt) {
                                        throw new Exception("Prepare statement failed for delete all: " . $conn->error);
                                    }

                                    $delete_all_stmt->bind_param("isi", $report_id, $original_frequency, $job_order_id);
                                    $delete_all_result = $delete_all_stmt->execute();
                                    if (!$delete_all_result) {
                                        throw new Exception("Execute statement failed for delete all: " . $delete_all_stmt->error);
                                    }

                                    error_log("Successfully deleted all related job orders");

                                    // Schedule this one-time job order
                                    error_log("Scheduling one-time job order for date: $preferred_date, time: $preferred_time");
                                }

                                // Get client name for notification
                                $client_name_query = "SELECT client_name FROM clients WHERE client_id = ?";
                                $client_name_stmt = $conn->prepare($client_name_query);
                                $client_name_stmt->bind_param("i", $client_id);
                                $client_name_stmt->execute();
                                $client_name_result = $client_name_stmt->get_result();
                                $client_name_row = $client_name_result->fetch_assoc();
                                $client_name = $client_name_row ? $client_name_row['client_name'] : "Client #$client_id";

                                // Create a single notification for the first admin
                                $admin_query = "SELECT staff_id FROM office_staff LIMIT 1";
                                $admin_result = $conn->query($admin_query);
                                if ($admin_result && $admin_row = $admin_result->fetch_assoc()) {
                                    $admin_id = $admin_row['staff_id'];

                                    // Use the new notification function
                                    include_once '../notification_functions.php';
                                    notifyAdminAboutQuotationResponse(
                                        $admin_id,
                                        $job_order_id,
                                        $client_id,
                                        $client_name,
                                        $type_of_work,
                                        $original_frequency,
                                        'one-time'
                                    );


                                }

                                $success_message = "You have chosen a one-time treatment only. All recurring appointments have been cancelled.";
                                error_log("One-time process completed successfully");

                            } catch (Exception $e) {
                                error_log("Error in one-time process: " . $e->getMessage());
                                $error_message = "An error occurred while processing your request: " . $e->getMessage();
                            }
                        }
                        }
                    } else {
                        $error_message = "Invalid job order or you don't have permission to approve this treatment.";
                    }
                }

                // Display success or error messages
                if (isset($success_message)) {
                    echo "<div class='alert alert-success'><i class='fas fa-check-circle'></i> <strong>Success!</strong> $success_message</div>";
                }

                if (isset($error_message)) {
                    echo "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> <strong>Error!</strong> $error_message</div>";
                }

                // Get client ID from session
                $client_id = $_SESSION['client_id'] ?? 0;

                // Check if client_id is set
                if ($client_id == 0) {
                    echo "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> <strong>Error!</strong> You are not logged in or your session has expired. Please <a href='login.php'>log in</a> to continue.</div>";
                }

                // Check if there are any job orders for this client
                $check_job_orders_query = "SELECT COUNT(*) as count
                                          FROM job_order jo
                                          JOIN assessment_report ar ON jo.report_id = ar.report_id
                                          JOIN appointments a ON ar.appointment_id = a.appointment_id
                                          WHERE a.client_id = ?";
                $check_job_orders_stmt = $conn->prepare($check_job_orders_query);
                $check_job_orders_stmt->bind_param("i", $client_id);
                $check_job_orders_stmt->execute();
                $check_job_orders_result = $check_job_orders_stmt->get_result();
                $check_job_orders_row = $check_job_orders_result->fetch_assoc();
                $has_job_orders = $check_job_orders_row['count'] > 0;

                // Get contract progress information - count total and completed job orders
                $progress_query = "SELECT
                    COUNT(jo.job_order_id) as total_job_orders,
                    SUM(CASE
                        WHEN jo.status = 'completed' OR jor.report_id IS NOT NULL THEN 1
                        ELSE 0
                    END) as completed_job_orders
                FROM job_order jo
                JOIN assessment_report ar ON jo.report_id = ar.report_id
                JOIN appointments a ON ar.appointment_id = a.appointment_id
                LEFT JOIN job_order_report jor ON jo.job_order_id = jor.job_order_id
                WHERE a.client_id = ?
                AND (jo.client_approval_status = 'approved' OR jo.client_approval_status = 'one-time')";

                $progress_stmt = $conn->prepare($progress_query);
                $progress_stmt->bind_param("i", $client_id);
                $progress_stmt->execute();
                $progress_result = $progress_stmt->get_result();
                $progress_row = $progress_result->fetch_assoc();

                $total_job_orders = $progress_row['total_job_orders'] ?? 0;
                $total_completed_job_orders = $progress_row['completed_job_orders'] ?? 0;

                // Calculate overall progress percentage
                $overall_progress_percentage = ($total_job_orders > 0) ? round(($total_completed_job_orders / $total_job_orders) * 100) : 0;

                if (!$has_job_orders) {
                    echo "<div class='alert alert-info'><i class='fas fa-info-circle'></i> <strong>No Contracts Available:</strong> You don't have any treatment plans yet. Once a technician creates a treatment plan for you, it will appear here for your approval.</div>";
                } else if ($total_job_orders > 0) {
                    // Display progress bar for contracts with job orders
                    echo "<div class='progress-container'>
                        <div class='progress-header'>
                            <div class='progress-title'>
                                <i class='fas fa-tasks'></i>
                                <span>Contract Progress</span>
                            </div>
                            <div class='progress-stats'>
                                <div class='progress-stat'>
                                    <span>Completed: {$total_completed_job_orders} / {$total_job_orders}</span>
                                </div>
                            </div>
                        </div>
                        <div class='progress-bar-container'>
                            <div class='progress-bar' style='width: {$overall_progress_percentage}%;'></div>
                        </div>
                        <div class='progress-text'>
                            <span>Overall completion</span>
                            <span class='progress-percentage'>{$overall_progress_percentage}%</span>
                        </div>
                    </div>";
                }

                // Fetch pending job orders that require client approval
                $pending_query = "SELECT jo.job_order_id, jo.type_of_work, jo.preferred_date, jo.preferred_time, jo.frequency,
                                  jo.chemical_recommendations, jo.cost,
                                  ar.report_id, ar.created_at, ar.area, ar.pest_types, ar.problem_area,
                                  a.location_address as property_address, a.client_id
                                  FROM job_order jo
                                  JOIN assessment_report ar ON jo.report_id = ar.report_id
                                  JOIN appointments a ON ar.appointment_id = a.appointment_id
                                  WHERE a.client_id = ? AND jo.client_approval_status = 'pending'
                                  GROUP BY ar.report_id, jo.frequency
                                  ORDER BY jo.preferred_date ASC";
                $pending_stmt = $conn->prepare($pending_query);
                $pending_stmt->bind_param("i", $client_id);
                $pending_stmt->execute();
                $pending_result = $pending_stmt->get_result();
                $pending_count = $pending_result->num_rows;

                // Fetch approved job orders
                $approved_query = "SELECT jo.job_order_id, jo.type_of_work, jo.preferred_date, jo.preferred_time, jo.frequency,
                                  jo.client_approval_status, jo.client_approval_date, jo.chemical_recommendations, jo.cost,
                                  jo.payment_amount, jo.payment_proof, jo.payment_date,
                                  ar.report_id, ar.area, ar.pest_types, ar.problem_area,
                                  a.location_address as property_address
                                  FROM job_order jo
                                  JOIN assessment_report ar ON jo.report_id = ar.report_id
                                  JOIN appointments a ON ar.appointment_id = a.appointment_id
                                  WHERE a.client_id = ? AND jo.client_approval_status IN ('approved', 'one-time')
                                  GROUP BY ar.report_id, jo.frequency
                                  ORDER BY jo.preferred_date ASC";
                $approved_stmt = $conn->prepare($approved_query);
                $approved_stmt->bind_param("i", $client_id);
                $approved_stmt->execute();
                $approved_result = $approved_stmt->get_result();
                $approved_count = $approved_result->num_rows;

                // Fetch declined job orders
                $declined_query = "SELECT jo.job_order_id, jo.type_of_work, jo.preferred_date, jo.preferred_time, jo.frequency,
                                  jo.client_approval_date, jo.chemical_recommendations,
                                  ar.report_id, ar.area, ar.pest_types, ar.problem_area,
                                  a.location_address as property_address
                                  FROM job_order jo
                                  JOIN assessment_report ar ON jo.report_id = ar.report_id
                                  JOIN appointments a ON ar.appointment_id = a.appointment_id
                                  WHERE a.client_id = ? AND jo.client_approval_status = 'declined'
                                  GROUP BY ar.report_id, jo.frequency
                                  ORDER BY jo.preferred_date ASC";
                $declined_stmt = $conn->prepare($declined_query);
                $declined_stmt->bind_param("i", $client_id);
                $declined_stmt->execute();
                $declined_result = $declined_stmt->get_result();
                $declined_count = $declined_result->num_rows;

                // Tab Navigation
                echo "<div class='contract-tabs'>";
                echo "<button class='contract-tab active' data-tab='pending'><i class='fas fa-clock'></i> Awaiting Approval";
                if ($pending_count > 0) echo "<span class='badge'>$pending_count</span>";
                echo "</button>";
                echo "<button class='contract-tab' data-tab='approved'><i class='fas fa-check-circle'></i> Approved Plans";
                if ($approved_count > 0) echo "<span class='badge'>$approved_count</span>";
                echo "</button>";
                echo "<button class='contract-tab' data-tab='declined'><i class='fas fa-times-circle'></i> Declined Plans";
                if ($declined_count > 0) echo "<span class='badge'>$declined_count</span>";
                echo "</button>";
                echo "</div>";

                // Pending Treatments Tab
                echo "<div class='tab-content active' id='pending-tab'>";
                if ($pending_count > 0) {
                    echo "<div class='alert alert-info'><i class='fas fa-info-circle'></i> <strong>Action Required:</strong> Review and approve treatment plans to schedule your pest control services.</div>";

                    echo "<div class='treatment-grid'>";

                    while ($row = $pending_result->fetch_assoc()) {
                        $job_order_id = $row['job_order_id'];
                        $type_of_work = htmlspecialchars($row['type_of_work']);
                        $preferred_date = !empty($row['preferred_date']) ? date('F j, Y', strtotime($row['preferred_date'])) : 'Not specified';
                        $preferred_time = !empty($row['preferred_time']) ? date('g:i A', strtotime($row['preferred_time'])) : 'Not specified';
                        $frequency = ucfirst(htmlspecialchars($row['frequency']));
                        $property_address = htmlspecialchars($row['property_address']);
                        $area = !empty($row['area']) ? number_format($row['area'], 2) : 'Not specified';
                        $pest_types = !empty($row['pest_types']) ? htmlspecialchars($row['pest_types']) : 'Not specified';
                        $problem_area = !empty($row['problem_area']) ? htmlspecialchars($row['problem_area']) : 'Not specified';
                        $cost = !empty($row['cost']) ? number_format($row['cost'], 2) : 'Not specified';

                        // Process chemical recommendations using the shared function
                        $chemicals_text = getChemicalRecommendationsText($row['chemical_recommendations']);

                        // Determine visit text based on frequency
                        $visit_text = '';
                        if ($row['frequency'] === 'weekly') {
                            $visit_text = "Weekly treatments for one year (52 visits)";
                        } elseif ($row['frequency'] === 'monthly') {
                            $visit_text = "Monthly treatments for one year (12 visits)";
                        } elseif ($row['frequency'] === 'quarterly') {
                            $visit_text = "Quarterly treatments for one year (4 visits)";
                        }

                        echo "<div class='treatment-card status-pending'>";

                        echo "<div class='card-header'>";
                        echo "<h3 class='card-title'>$type_of_work</h3>";
                        echo "<span class='status-badge'>Action Required</span>";
                        echo "</div>";

                        echo "<div class='card-body'>";
                        echo "<div class='treatment-details'>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-map-marker-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Property Address:</span>";
                        echo "<div class='detail-value'>$property_address</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-calendar-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Treatment Date:</span>";
                        echo "<div class='detail-value'>$preferred_date</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-clock'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Treatment Time:</span>";
                        echo "<div class='detail-value'>$preferred_time</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-sync-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Treatment Frequency:</span>";
                        echo "<div class='detail-value'>$frequency</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "</div>";

                        echo "<div class='treatment-schedule'>";
                        echo "<div class='schedule-title'><i class='fas fa-info-circle'></i> Treatment Plan Details</div>";
                        echo "<div class='schedule-text'>$visit_text</div>";
                        echo "</div>";

                        echo "<div class='job-order-details'>";
                        echo "<div class='details-title'><i class='fas fa-clipboard-list'></i> Job Order Information</div>";
                        echo "<div class='details-grid'>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Area:</span>";
                        echo "<span class='detail-value'>$area m²</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Pest Observed:</span>";
                        echo "<span class='detail-value'>$pest_types</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Problem Area:</span>";
                        echo "<span class='detail-value'>$problem_area</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Chemical Recommendation:</span>";
                        echo "<span class='detail-value'>$chemicals_text</span>";
                        echo "</div>";

                        // Service cost has been removed as per client request

                        echo "</div>"; // End details-grid
                        echo "</div>"; // End job-order-details

                        echo "</div>"; // End card-body

                        echo "<div class='card-footer'>";
                        echo "<div style='display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;'>";
                        echo "<button type='button' class='btn btn-download' onclick='saveContractAsPDF({
                            \"job_order_id\": " . $job_order_id . ",
                            \"type_of_work\": \"" . addslashes($type_of_work) . "\",
                            \"property_address\": \"" . addslashes($property_address) . "\",
                            \"preferred_date\": \"" . addslashes($preferred_date) . "\",
                            \"preferred_time\": \"" . addslashes($preferred_time) . "\",
                            \"frequency\": \"" . addslashes($frequency) . "\",
                            \"approval_date\": \"" . date('F j, Y') . "\",
                            \"area\": \"" . addslashes($area) . "\",
                            \"pest_types\": \"" . addslashes($pest_types) . "\",
                            \"problem_area\": \"" . addslashes($problem_area) . "\",
                            \"chemicals_text\": \"" . addslashes($chemicals_text) . "\",
                            \"visit_text\": \"" . addslashes($visit_text) . "\",
                            \"cost\": \"" . addslashes($cost) . "\"
                        })'><i class='fas fa-file-pdf'></i> Download Quotation</button>";
                        echo "</div>";
                        echo "<form method='POST' class='approval-form' id='approval-form-$job_order_id' enctype='multipart/form-data'>";
                        echo "<input type='hidden' name='job_order_id' value='$job_order_id'>";
                        echo "<input type='hidden' name='payment_amount' value='0'>";

                        echo "<div class='action-buttons'>";
                        echo "<button type='button' name='approval_action' value='approve' class='btn btn-approve' onclick='confirmAction(\"approve\", $job_order_id)'><i class='fas fa-check'></i> Approve Plan</button>";
                        echo "<button type='button' name='approval_action' value='decline' class='btn btn-decline' onclick='confirmAction(\"decline\", $job_order_id)'><i class='fas fa-times'></i> Decline Plan</button>";
                        echo "</div>";
                        echo "</form>";
                        echo "</div>";

                        echo "</div>";
                    }

                    echo "</div>";
                } else {
                    echo "<div class='empty-state'>";
                    echo "<div class='empty-icon'><i class='fas fa-check-circle'></i></div>";
                    echo "<h3 class='empty-title'>No Pending Approvals</h3>";
                    echo "<p class='empty-text'>No treatment plans require your approval at this time. New plans will appear here when created.</p>";
                    echo "</div>";
                }
                echo "</div>";

                // Fetch approved job orders
                $approved_query = "SELECT jo.job_order_id, jo.type_of_work, jo.preferred_date, jo.preferred_time, jo.frequency,
                                  jo.client_approval_status, jo.client_approval_date, jo.chemical_recommendations, jo.cost,
                                  jo.payment_amount, jo.payment_proof, jo.payment_date,
                                  ar.report_id, ar.area, ar.pest_types, ar.problem_area,
                                  a.location_address as property_address
                                  FROM job_order jo
                                  JOIN assessment_report ar ON jo.report_id = ar.report_id
                                  JOIN appointments a ON ar.appointment_id = a.appointment_id
                                  WHERE a.client_id = ? AND jo.client_approval_status IN ('approved', 'one-time')
                                  GROUP BY ar.report_id, jo.frequency
                                  ORDER BY jo.preferred_date ASC";
                $approved_stmt = $conn->prepare($approved_query);
                $approved_stmt->bind_param("i", $client_id);
                $approved_stmt->execute();
                $approved_result = $approved_stmt->get_result();
                $approved_count = $approved_result->num_rows;

                // Approved Treatments Tab
                echo "<div class='tab-content' id='approved-tab'>";
                if ($approved_count > 0) {
                    echo "<div class='treatment-grid'>";

                    while ($row = $approved_result->fetch_assoc()) {
                        $type_of_work = htmlspecialchars($row['type_of_work']);
                        $preferred_date = !empty($row['preferred_date']) ? date('F j, Y', strtotime($row['preferred_date'])) : 'Not specified';
                        $preferred_time = !empty($row['preferred_time']) ? date('g:i A', strtotime($row['preferred_time'])) : 'Not specified';
                        $is_one_time = $row['client_approval_status'] === 'one-time';
                        $frequency = $is_one_time ? 'One-time' : ucfirst(htmlspecialchars($row['frequency']));
                        $property_address = htmlspecialchars($row['property_address']);
                        $approval_date = !empty($row['client_approval_date']) ? date('F j, Y', strtotime($row['client_approval_date'])) : 'Not specified';
                        $area = !empty($row['area']) ? number_format($row['area'], 2) : 'Not specified';
                        $pest_types = !empty($row['pest_types']) ? htmlspecialchars($row['pest_types']) : 'Not specified';
                        $problem_area = !empty($row['problem_area']) ? htmlspecialchars($row['problem_area']) : 'Not specified';
                        $cost = !empty($row['cost']) ? number_format($row['cost'], 2) : 'Not specified';
                        $payment_amount = !empty($row['payment_amount']) ? number_format($row['payment_amount'], 2) : 'Not specified';
                        $payment_proof = !empty($row['payment_proof']) ? $row['payment_proof'] : '';
                        $payment_date = !empty($row['payment_date']) ? date('F j, Y', strtotime($row['payment_date'])) : 'Not specified';

                        // Process chemical recommendations using the shared function
                        $chemicals_text = getChemicalRecommendationsText($row['chemical_recommendations']);

                        // Determine visit text based on frequency
                        $visit_text = '';
                        if (!$is_one_time) {
                            if ($row['frequency'] === 'weekly') {
                                $visit_text = "Weekly treatments for one year (52 visits)";
                            } elseif ($row['frequency'] === 'monthly') {
                                $visit_text = "Monthly treatments for one year (12 visits)";
                            } elseif ($row['frequency'] === 'quarterly') {
                                $visit_text = "Quarterly treatments for one year (4 visits)";
                            }
                        } else {
                            $visit_text = "One-time treatment only (no recurring visits)";
                        }

                        $card_class = $is_one_time ? 'treatment-card status-one-time' : 'treatment-card status-approved';
                        $status_text = $is_one_time ? 'One-Time Only' : 'Approved';

                        echo "<div class='$card_class'>";

                        echo "<div class='card-header'>";
                        echo "<h3 class='card-title'>$type_of_work</h3>";
                        echo "<span class='status-badge'>$status_text</span>";
                        echo "</div>";

                        echo "<div class='card-body'>";
                        echo "<div class='treatment-details'>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-map-marker-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Location</span>";
                        echo "<div class='detail-value'>$property_address</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-calendar-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Date</span>";
                        echo "<div class='detail-value'>$preferred_date</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-clock'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Time</span>";
                        echo "<div class='detail-value'>$preferred_time</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-sync-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Frequency</span>";
                        echo "<div class='detail-value'>$frequency</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-check-circle'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Approved On</span>";
                        echo "<div class='detail-value'>$approval_date</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "</div>";

                        echo "<div class='treatment-schedule'>";
                        echo "<div class='schedule-title'><i class='fas fa-info-circle'></i> Treatment Schedule</div>";
                        echo "<div class='schedule-text'>$visit_text</div>";
                        echo "</div>";

                        echo "<div class='job-order-details'>";
                        echo "<div class='details-title'><i class='fas fa-clipboard-list'></i> Job Order Information</div>";
                        echo "<div class='details-grid'>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Area:</span>";
                        echo "<span class='detail-value'>$area m²</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Pest Observed:</span>";
                        echo "<span class='detail-value'>$pest_types</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Problem Area:</span>";
                        echo "<span class='detail-value'>$problem_area</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Chemical Recommendation:</span>";
                        echo "<span class='detail-value'>$chemicals_text</span>";
                        echo "</div>";

                        // Service cost has been removed as per client request



                        echo "</div>"; // End details-grid



                        echo "</div>"; // End job-order-details

                        echo "</div>"; // End card-body

                        // Add "Save as PDF" button in the card footer
                        echo "<div class='card-footer'>";
                        echo "<button type='button' class='btn btn-download' onclick='saveContractAsPDF({
                            \"job_order_id\": " . $row['job_order_id'] . ",
                            \"type_of_work\": \"" . addslashes($type_of_work) . "\",
                            \"property_address\": \"" . addslashes($property_address) . "\",
                            \"preferred_date\": \"" . addslashes($preferred_date) . "\",
                            \"preferred_time\": \"" . addslashes($preferred_time) . "\",
                            \"frequency\": \"" . addslashes($frequency) . "\",
                            \"approval_date\": \"" . addslashes($approval_date) . "\",
                            \"area\": \"" . addslashes($area) . "\",
                            \"pest_types\": \"" . addslashes($pest_types) . "\",
                            \"problem_area\": \"" . addslashes($problem_area) . "\",
                            \"chemicals_text\": \"" . addslashes($chemicals_text) . "\",
                            \"visit_text\": \"" . addslashes($visit_text) . "\",
                            \"cost\": \"" . addslashes($cost) . "\"
                        })'><i class='fas fa-file-pdf'></i> Download Quotation</button>";
                        echo "</div>";

                        echo "</div>"; // End treatment-card
                    }

                    echo "</div>";
                } else {
                    echo "<div class='empty-state'>";
                    echo "<div class='empty-icon'><i class='fas fa-clipboard-list'></i></div>";
                    echo "<h3 class='empty-title'>No Approved Treatments</h3>";
                    echo "<p class='empty-text'>No approved treatment plans yet. Approved plans will appear here.</p>";
                    echo "</div>";
                }
                echo "</div>";

                // Fetch declined job orders
                $declined_query = "SELECT jo.job_order_id, jo.type_of_work, jo.preferred_date, jo.preferred_time, jo.frequency,
                                  jo.client_approval_date, jo.chemical_recommendations, jo.cost,
                                  ar.report_id, ar.area, ar.pest_types, ar.problem_area,
                                  a.location_address as property_address
                                  FROM job_order jo
                                  JOIN assessment_report ar ON jo.report_id = ar.report_id
                                  JOIN appointments a ON ar.appointment_id = a.appointment_id
                                  WHERE a.client_id = ? AND jo.client_approval_status = 'declined'
                                  GROUP BY ar.report_id, jo.frequency
                                  ORDER BY jo.preferred_date ASC";
                $declined_stmt = $conn->prepare($declined_query);
                $declined_stmt->bind_param("i", $client_id);
                $declined_stmt->execute();
                $declined_result = $declined_stmt->get_result();

                // Declined Treatments Tab
                echo "<div class='tab-content' id='declined-tab'>";
                if ($declined_count > 0) {
                    echo "<div class='treatment-grid'>";

                    while ($row = $declined_result->fetch_assoc()) {
                        $type_of_work = htmlspecialchars($row['type_of_work']);
                        $preferred_date = !empty($row['preferred_date']) ? date('F j, Y', strtotime($row['preferred_date'])) : 'Not specified';
                        $preferred_time = !empty($row['preferred_time']) ? date('g:i A', strtotime($row['preferred_time'])) : 'Not specified';
                        $frequency = ucfirst(htmlspecialchars($row['frequency']));
                        $property_address = htmlspecialchars($row['property_address']);
                        $declined_date = !empty($row['client_approval_date']) ? date('F j, Y', strtotime($row['client_approval_date'])) : 'Not specified';
                        $area = !empty($row['area']) ? number_format($row['area'], 2) : 'Not specified';
                        $pest_types = !empty($row['pest_types']) ? htmlspecialchars($row['pest_types']) : 'Not specified';
                        $problem_area = !empty($row['problem_area']) ? htmlspecialchars($row['problem_area']) : 'Not specified';
                        $cost = !empty($row['cost']) ? number_format($row['cost'], 2) : 'Not specified';

                        // Process chemical recommendations using the shared function
                        $chemicals_text = getChemicalRecommendationsText($row['chemical_recommendations']);

                        // Determine visit text based on frequency
                        $visit_text = '';
                        if ($row['frequency'] === 'weekly') {
                            $visit_text = "Weekly treatments for one year (52 visits)";
                        } elseif ($row['frequency'] === 'monthly') {
                            $visit_text = "Monthly treatments for one year (12 visits)";
                        } elseif ($row['frequency'] === 'quarterly') {
                            $visit_text = "Quarterly treatments for one year (4 visits)";
                        }

                        echo "<div class='treatment-card status-declined'>";

                        echo "<div class='card-header'>";
                        echo "<h3 class='card-title'>$type_of_work</h3>";
                        echo "<span class='status-badge'>Declined</span>";
                        echo "</div>";

                        echo "<div class='card-body'>";
                        echo "<div class='treatment-details'>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-map-marker-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Location</span>";
                        echo "<div class='detail-value'>$property_address</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-calendar-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Date</span>";
                        echo "<div class='detail-value'>$preferred_date</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-clock'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Time</span>";
                        echo "<div class='detail-value'>$preferred_time</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-sync-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Frequency</span>";
                        echo "<div class='detail-value'>$frequency</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-times-circle'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Declined On</span>";
                        echo "<div class='detail-value'>$declined_date</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "</div>";

                        echo "<div class='treatment-schedule'>";
                        echo "<div class='schedule-title'><i class='fas fa-info-circle'></i> Next Steps</div>";
                        echo "<div class='schedule-text'>An administrator will contact you to discuss alternatives or make adjustments to better meet your needs.</div>";
                        echo "</div>";

                        echo "<div class='job-order-details'>";
                        echo "<div class='details-title'><i class='fas fa-clipboard-list'></i> Job Order Information</div>";
                        echo "<div class='details-grid'>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Area:</span>";
                        echo "<span class='detail-value'>$area m²</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Pest Observed:</span>";
                        echo "<span class='detail-value'>$pest_types</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Problem Area:</span>";
                        echo "<span class='detail-value'>$problem_area</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Chemical Recommendation:</span>";
                        echo "<span class='detail-value'>$chemicals_text</span>";
                        echo "</div>";

                        // Service cost has been removed as per client request

                        echo "</div>"; // End details-grid
                        echo "</div>"; // End job-order-details

                        echo "</div>"; // End card-body
                        echo "</div>"; // End treatment-card
                    }

                    echo "</div>";
                } else {
                    echo "<div class='empty-state'>";
                    echo "<div class='empty-icon'><i class='fas fa-clipboard-check'></i></div>";
                    echo "<h3 class='empty-title'>No Declined Treatments</h3>";
                    echo "<p class='empty-text'>You haven't declined any treatment plans.</p>";
                    echo "</div>";
                }
                echo "</div>";
                ?>
                </div>
            </div>

            <?php
            // Include the contract guide
            include 'includes/contract_guide.php';

            // Include the necessary CSS and JS files if not already included
            if (!isset($GLOBALS['fab_css_included'])) {
                echo '<link rel="stylesheet" href="css/floating-action-button.css">';
                $GLOBALS['fab_css_included'] = true;
            }

            if (!isset($GLOBALS['fab_js_included'])) {
                echo '<script src="js/floating-action-button.js"></script>';
                $GLOBALS['fab_js_included'] = true;
            }
            ?>
        </main>

        <script src="js/notifications.js"></script>

        <script>
            // Tab functionality
            document.addEventListener('DOMContentLoaded', function() {
                const tabs = document.querySelectorAll('.contract-tab');
                const tabContents = document.querySelectorAll('.tab-content');

                tabs.forEach(tab => {
                    tab.addEventListener('click', function() {
                        // Remove active class from all tabs
                        tabs.forEach(t => t.classList.remove('active'));

                        // Add active class to clicked tab
                        this.classList.add('active');

                        // Hide all tab contents
                        tabContents.forEach(content => content.classList.remove('active'));

                        // Show the corresponding tab content
                        const tabId = this.getAttribute('data-tab');
                        document.getElementById(tabId + '-tab').classList.add('active');
                    });
                });
            });
        </script>

        <style>
            /* Override main content padding to reduce empty space */
            .main-content {
                padding: 1rem;
                padding-top: calc(var(--header-height) + 1rem);
            }

            /* Header and Container Styles */
            .contract-header {
                margin-bottom: 1rem;
                border-bottom: 1px solid #eee;
                padding-bottom: 0.75rem;
            }

            .contract-header h1 {
                font-size: 1.5rem;
                color: var(--primary-color);
                margin-bottom: 0.25rem;
            }

            .contract-header p {
                color: #6c757d;
                font-size: 0.9rem;
                margin-bottom: 0;
            }

            .contract-container {
                width: 100%;
                margin: 0 auto;
                padding: 0;
            }

            /* Tab Navigation */
            .contract-tabs {
                display: flex;
                margin-bottom: 1rem;
                border-bottom: 1px solid #dee2e6;
                background-color: transparent;
            }

            .contract-tab {
                flex: 1;
                text-align: center;
                padding: 0.75rem 1rem;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.2s ease;
                position: relative;
                border: none;
                background: transparent;
                color: #6c757d;
                border-bottom: 3px solid transparent;
                margin-bottom: -1px;
                font-size: 0.9rem;
            }

            .contract-tab.active {
                color: var(--primary-color);
                border-bottom: 3px solid var(--primary-color);
            }

            .contract-tab:hover:not(.active) {
                color: #495057;
                border-bottom: 3px solid #dee2e6;
            }

            .contract-tab i {
                margin-right: 0.5rem;
                font-size: 1rem;
            }

            .contract-tab .badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin-left: 0.5rem;
                background-color: var(--primary-color);
                color: white;
                border-radius: 50%;
                width: 22px;
                height: 22px;
                font-size: 0.75rem;
                font-weight: 700;
            }

            /* Tab Content */
            .tab-content {
                display: none;
            }

            .tab-content.active {
                display: block;
                animation: fadeIn 0.5s ease;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Treatment Cards */
            .treatment-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
                gap: 1rem;
                margin-top: 1rem;
            }

            .treatment-card {
                background-color: #fff;
                border-radius: 6px;
                overflow: hidden;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
                transition: all 0.2s ease;
                position: relative;
                display: flex;
                flex-direction: column;
                height: 100%;
                border: 1px solid #e9ecef;
            }

            .treatment-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            }

            /* Status indicators */
            .status-pending {
                border-left: 4px solid var(--warning-color);
            }

            .status-approved {
                border-left: 4px solid var(--success-color);
            }

            .status-declined {
                border-left: 4px solid var(--error-color);
            }

            .status-one-time {
                border-left: 4px solid var(--primary-color);
            }

            .card-header {
                padding: 0.75rem 1rem 0.5rem;
                position: relative;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #f1f1f1;
            }

            .card-title {
                font-size: 1.1rem;
                font-weight: 600;
                margin-bottom: 0;
                color: #333;
            }

            .status-badge {
                display: inline-block;
                padding: 0.25rem 0.75rem;
                border-radius: 4px;
                font-size: 0.7rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .status-pending .status-badge {
                background-color: var(--warning-color);
                color: white;
            }

            .status-approved .status-badge {
                background-color: var(--success-color);
                color: white;
            }

            .status-declined .status-badge {
                background-color: var(--error-color);
                color: white;
            }

            .status-one-time .status-badge {
                background-color: var(--primary-color);
                color: white;
            }

            .card-body {
                padding: 0.75rem 1rem;
                flex: 1;
            }

            .treatment-details {
                margin-bottom: 1rem;
            }

            .detail-item {
                display: flex;
                margin-bottom: 0.75rem;
                align-items: center;
                border-bottom: 1px dashed #f1f1f1;
                padding-bottom: 0.75rem;
            }

            .detail-item:last-child {
                margin-bottom: 0;
                border-bottom: none;
                padding-bottom: 0;
            }

            .detail-icon {
                flex-shrink: 0;
                width: 28px;
                height: 28px;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 0.75rem;
                font-size: 0.9rem;
                background-color: #f8f9fa;
                color: #6c757d;
            }

            .status-pending .detail-icon {
                color: var(--warning-color);
            }

            .status-approved .detail-icon {
                color: var(--success-color);
            }

            .status-declined .detail-icon {
                color: var(--error-color);
            }

            .status-one-time .detail-icon {
                color: var(--primary-color);
            }

            .detail-content {
                flex: 1;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .detail-label {
                font-size: 0.85rem;
                color: #6c757d;
                margin-bottom: 0;
                font-weight: 500;
            }

            .detail-value {
                font-weight: 600;
                color: #333;
                font-size: 0.9rem;
                text-align: right;
            }

            .treatment-schedule {
                background-color: #f8f9fa;
                border-radius: 4px;
                padding: 0.5rem 0.75rem;
                margin-bottom: 0.75rem;
                border-left: 3px solid var(--primary-color);
            }

            .status-pending .treatment-schedule {
                border-left-color: var(--warning-color);
            }

            .status-approved .treatment-schedule {
                border-left-color: var(--success-color);
            }

            .status-declined .treatment-schedule {
                border-left-color: var(--error-color);
            }

            .status-one-time .treatment-schedule {
                border-left-color: var(--primary-color);
            }

            .schedule-title {
                display: flex;
                align-items: center;
                margin-bottom: 0.5rem;
                font-weight: 600;
                color: #495057;
                font-size: 0.85rem;
            }

            .schedule-title i {
                margin-right: 0.5rem;
                color: inherit;
            }

            .schedule-text {
                font-size: 0.8rem;
                color: #6c757d;
                line-height: 1.5;
            }

            /* Job Order Details Styles */
            .job-order-details {
                background-color: #f8f9fa;
                border-radius: 4px;
                padding: 0.5rem 0.75rem;
                margin-top: 0.75rem;
                border-left: 3px solid var(--primary-color);
            }

            .status-pending .job-order-details {
                border-left-color: var(--warning-color);
            }

            .status-approved .job-order-details {
                border-left-color: var(--success-color);
            }

            .status-declined .job-order-details {
                border-left-color: var(--error-color);
            }

            .status-one-time .job-order-details {
                border-left-color: var(--primary-color);
            }

            .details-title {
                display: flex;
                align-items: center;
                margin-bottom: 0.5rem;
                font-weight: 600;
                color: #495057;
                font-size: 0.85rem;
            }

            .details-title i {
                margin-right: 0.5rem;
                color: inherit;
            }

            .details-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 0.25rem;
            }

            .detail-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                font-size: 0.8rem;
                padding: 0.25rem 0;
                border-bottom: 1px dashed rgba(0, 0, 0, 0.05);
            }

            .detail-row:last-child {
                border-bottom: none;
            }

            .detail-row .detail-label {
                font-weight: 600;
                color: #495057;
                flex: 0 0 40%;
                font-size: 0.8rem;
            }

            .detail-row .detail-value {
                color: #6c757d;
                flex: 0 0 60%;
                text-align: right;
                word-break: break-word;
                font-size: 0.8rem;
                font-weight: normal;
            }

            .card-footer {
                padding: 0.75rem 1rem;
                background-color: #f8f9fa;
                border-top: 1px solid #eee;
            }

            /* Action Buttons */
            .action-buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                justify-content: space-between;
            }

            .btn {
                flex: 1;
                padding: 0.6rem 0.75rem;
                border-radius: 4px;
                font-weight: 500;
                font-size: 0.85rem;
                text-align: center;
                cursor: pointer;
                transition: all 0.2s ease;
                border: 1px solid transparent;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }

            .btn i {
                font-size: 0.8rem;
            }

            .btn-approve {
                background-color: white;
                color: var(--success-color);
                border-color: var(--success-color);
            }

            .btn-approve:hover {
                background-color: var(--success-color);
                color: white;
            }

            .btn-one-time {
                background-color: white;
                color: var(--primary-color);
                border-color: var(--primary-color);
            }

            .btn-one-time:hover {
                background-color: var(--primary-color);
                color: white;
            }

            .btn-decline {
                background-color: white;
                color: var(--error-color);
                border-color: var(--error-color);
            }

            .btn-decline:hover {
                background-color: var(--error-color);
                color: white;
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 1.5rem 1rem;
                background-color: #fff;
                border-radius: 8px;
                border: 1px dashed #dee2e6;
                margin: 1rem 0;
                max-width: 600px;
                margin-left: auto;
                margin-right: auto;
            }

            .empty-icon {
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background-color: #f8f9fa;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 0.75rem;
                font-size: 1.25rem;
                color: #adb5bd;
            }

            .status-pending .empty-icon {
                color: var(--warning-color);
            }

            .status-approved .empty-icon {
                color: var(--success-color);
            }

            .status-declined .empty-icon {
                color: var(--error-color);
            }

            .empty-title {
                font-size: 1rem;
                font-weight: 600;
                margin-bottom: 0.25rem;
                color: #495057;
            }

            .empty-text {
                color: #6c757d;
                max-width: 400px;
                margin: 0 auto;
                font-size: 0.85rem;
                line-height: 1.4;
            }

            /* Alerts */
            .alert {
                padding: 0.5rem 0.75rem;
                border-radius: 4px;
                margin-bottom: 0.75rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.85rem;
            }

            .alert i {
                font-size: 1rem;
            }

            .alert-success {
                background-color: #d4edda;
                border-left: 3px solid var(--success-color);
                color: #155724;
            }

            .alert-danger {
                background-color: #f8d7da;
                border-left: 3px solid var(--error-color);
                color: #721c24;
            }

            .alert-info {
                background-color: #d1ecf1;
                border-left: 3px solid var(--primary-color);
                color: #0c5460;
            }

            /* Responsive Adjustments */
            @media (max-width: 1200px) {
                .treatment-grid {
                    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                }
            }

            @media (max-width: 991px) {
                .treatment-grid {
                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                }

                .contract-header h1 {
                    font-size: 1.4rem;
                }
            }

            @media (max-width: 767px) {
                .main-content {
                    padding: 0.75rem;
                    padding-top: calc(var(--header-height) + 0.75rem);
                }

                .contract-header {
                    margin-bottom: 0.75rem;
                }

                .contract-tabs {
                    flex-wrap: wrap;
                    border-bottom: none;
                    margin-bottom: 0.75rem;
                }

                .contract-tab {
                    flex: 1 0 33.333%;
                    padding: 0.5rem 0.25rem;
                    border-bottom: 1px solid #dee2e6;
                    margin-bottom: 0;
                    font-size: 0.8rem;
                }

                .contract-tab.active {
                    border-bottom: 3px solid var(--primary-color);
                    margin-bottom: -1px;
                }

                .treatment-grid {
                    grid-template-columns: 1fr;
                    gap: 0.75rem;
                    margin-top: 0.75rem;
                }

                .action-buttons {
                    flex-direction: column;
                    gap: 0.5rem;
                }

                .btn {
                    width: 100%;
                }

                .card-header, .card-body, .card-footer {
                    padding: 0.5rem 0.75rem;
                }

                .detail-content {
                    flex-direction: row;
                    align-items: center;
                    justify-content: space-between;
                }

                .detail-item {
                    margin-bottom: 0.5rem;
                    padding-bottom: 0.5rem;
                }
            }

            @media (max-width: 575px) {
                .main-content {
                    padding: 0.5rem;
                    padding-top: calc(var(--header-height) + 0.5rem);
                }

                .contract-header {
                    margin-bottom: 0.5rem;
                    padding-bottom: 0.5rem;
                }

                .contract-header h1 {
                    font-size: 1.2rem;
                    margin-bottom: 0.15rem;
                }

                .contract-header p {
                    font-size: 0.8rem;
                }

                .contract-tab {
                    font-size: 0.75rem;
                    padding: 0.4rem 0.2rem;
                }

                .contract-tab i {
                    margin-right: 0.15rem;
                    font-size: 0.9rem;
                }

                .contract-tab .badge {
                    width: 18px;
                    height: 18px;
                    font-size: 0.7rem;
                    margin-left: 0.25rem;
                }

                .card-title {
                    font-size: 0.95rem;
                }

                .detail-icon {
                    width: 22px;
                    height: 22px;
                    font-size: 0.75rem;
                    margin-right: 0.5rem;
                }

                .detail-label {
                    font-size: 0.75rem;
                }

                .detail-value {
                    font-size: 0.8rem;
                }

                .treatment-schedule {
                    padding: 0.4rem 0.6rem;
                    margin-bottom: 0.5rem;
                }

                .schedule-title {
                    font-size: 0.75rem;
                    margin-bottom: 0.25rem;
                }

                .schedule-text {
                    font-size: 0.7rem;
                }

                .btn {
                    padding: 0.4rem 0.5rem;
                    font-size: 0.75rem;
                }

                .btn i {
                    font-size: 0.7rem;
                }

                .empty-state {
                    padding: 1rem 0.75rem;
                }

                .empty-icon {
                    width: 40px;
                    height: 40px;
                    font-size: 1rem;
                    margin-bottom: 0.5rem;
                }

                .empty-title {
                    font-size: 0.9rem;
                }

                .empty-text {
                    font-size: 0.75rem;
                }
            }
        </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/sidebar.js"></script>
    <!-- Fixed sidebar script -->
    <script src="js/sidebar-fix.js"></script>
    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>

    <!-- Debug script for notifications -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Manual initialization for notification dropdown
            const notificationIcon = document.querySelector('.notification-icon');
            const notificationDropdown = document.querySelector('.notification-dropdown');

            if (notificationIcon && notificationDropdown) {
                notificationIcon.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('show');
                    console.log('Notification icon clicked, dropdown toggled');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!notificationDropdown.contains(e.target) && !notificationIcon.contains(e.target)) {
                        notificationDropdown.classList.remove('show');
                    }
                });
            } else {
                console.error('Notification elements not found:', {
                    icon: notificationIcon,
                    dropdown: notificationDropdown
                });
            }
        });
    </script>

    <script>
        // Function to confirm action before form submission
        function confirmAction(action, jobOrderId) {
            let message = '';

            if (action === 'approve') {
                message = 'Are you sure you want to approve this recurring treatment plan?';
            } else if (action === 'decline') {
                message = 'Are you sure you want to decline this treatment plan?';
            }

            if (confirm(message)) {
                try {
                    // Show loading overlay
                    const loadingOverlay = document.createElement('div');
                    loadingOverlay.className = 'loading-overlay';
                    loadingOverlay.id = 'loadingOverlay';
                    loadingOverlay.innerHTML = `
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin fa-3x"></i>
                            <p>Processing your request...</p>
                            <p class="small-text">This may take a few moments. Please do not refresh the page.</p>
                        </div>
                    `;
                    document.body.appendChild(loadingOverlay);

                    // Disable the buttons to prevent double submission
                    const form = document.getElementById('approval-form-' + jobOrderId);
                    const buttons = form.querySelectorAll('button');

                    buttons.forEach(button => {
                        button.disabled = true;
                    });

                    // Use AJAX to submit the form instead of normal form submission
                    const formData = new FormData(form);

                    // Add the action value to the form data
                    formData.append('approval_action', action);

                    // Log the form data for debugging
                    console.log('Submitting form data for job order ID: ' + jobOrderId);
                    for (let pair of formData.entries()) {
                        console.log(pair[0] + ': ' + pair[1]);
                    }

                    // Submit the form using fetch API
                    fetch('contract.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Response received');
                        // Reload the page to show the updated status
                        window.location.reload();
                    })
                    .catch(error => {
                        console.error('Error in form submission:', error);
                        alert('An error occurred while processing your request. Please try again.');

                        // Re-enable buttons and remove loading overlay
                        buttons.forEach(button => {
                            button.disabled = false;
                        });

                        const overlay = document.getElementById('loadingOverlay');
                        if (overlay) {
                            document.body.removeChild(overlay);
                        }
                    });

                    // Prevent the default form submission
                    return false;
                } catch (error) {
                    console.error('Error in form submission:', error);
                    alert('An error occurred while processing your request. Please try again.');
                    return false;
                }
            }

            return false;
        }

        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Contract page loaded');

            const tabs = document.querySelectorAll('.contract-tab');
            const tabContents = document.querySelectorAll('.tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');

                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));

                    // Add active class to clicked tab and corresponding content
                    this.classList.add('active');
                    document.getElementById(tabId + '-tab').classList.add('active');
                });
            });

            // Debug logging for sidebar
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');

            if (!sidebar) {
                console.error('Sidebar element not found in contract.php');
            } else {
                console.log('Sidebar element found in contract.php');
            }

            if (!menuToggle) {
                console.error('Menu toggle element not found in contract.php');
            } else {
                console.log('Menu toggle element found in contract.php');
            }

            // Add event listeners to all approval forms
            const approvalForms = document.querySelectorAll('.approval-form');
            approvalForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    console.log('Form submitted:', this.id);
                });
            });
        });
    </script>

    <!-- PDF Generation Function -->
    <script>
        // Function to save contract as PDF
        function saveContractAsPDF(contractData) {
            const loadingOverlay = document.getElementById('pdfLoadingOverlay');
            loadingOverlay.style.display = 'flex';
            try {
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');
                const pageWidth = 210;
                const margin = 15;
                const contentWidth = pageWidth - margin * 2;
                const primaryColor = [37, 99, 235];
                const borderColor = [37, 99, 235];
                const grayText = [60, 60, 60];
                const safeGet = (obj, key, def = 'Not specified') => obj && obj[key] ? obj[key] : def;
                const clientName = '<?= htmlspecialchars($_SESSION["fullname"] ?? "Client") ?>';
                const today = new Date();
                const jobOrderId = safeGet(contractData, 'job_order_id', '000000');
                const quotationNumber = `Q-${today.getFullYear()}${(today.getMonth() + 1).toString().padStart(2, '0')}${today.getDate().toString().padStart(2, '0')}-${jobOrderId.toString().padStart(3, '0')}`;
                const currentDate = today.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                const propertyAddress = safeGet(contractData, 'property_address');
                const typeOfWork = safeGet(contractData, 'type_of_work');
                const preferredDate = safeGet(contractData, 'preferred_date');
                const preferredTime = safeGet(contractData, 'preferred_time');
                const frequency = safeGet(contractData, 'frequency');
                const area = safeGet(contractData, 'area');
                const pestTypes = safeGet(contractData, 'pest_types');
                const problemArea = safeGet(contractData, 'problem_area');
                const chemicalsText = safeGet(contractData, 'chemicals_text', 'To be determined');
                const visitText = safeGet(contractData, 'visit_text', 'According to agreed schedule');
                // Parse the cost value to ensure it's a number for formatting
                let totalAmount = safeGet(contractData, 'cost', 'Not specified');
                // If it's a string with commas, remove them before parsing
                if (typeof totalAmount === 'string') {
                    totalAmount = parseFloat(totalAmount.replace(/,/g, ''));
                }
                // If parsing failed or the value is not a number, set a default
                if (isNaN(totalAmount)) {
                    totalAmount = 0;
                }
                // HEADER
                pdf.setFillColor(240, 247, 255);
                pdf.rect(0, 0, pageWidth, 22, 'F');
                pdf.setFont('helvetica', 'bold');
                pdf.setFontSize(20);
                pdf.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]);
                pdf.text('Quotation', margin, 15);
                pdf.setFontSize(13);
                pdf.setTextColor(0, 0, 0);
                pdf.text('MacJ Pest Control', pageWidth - margin, 11, { align: 'right' });
                pdf.setFont('helvetica', 'normal');
                pdf.setFontSize(9);
                pdf.text('Professional Pest Control Services', pageWidth - margin, 17, { align: 'right' });
                // INFO BOXES
                pdf.setDrawColor(borderColor[0], borderColor[1], borderColor[2]);
                pdf.setLineWidth(0.5);
                pdf.rect(margin, 26, 85, 30, 'S');
                pdf.rect(pageWidth - margin - 85, 26, 85, 30, 'S');
                pdf.setFont('helvetica', 'bold');
                pdf.setFontSize(10);
                pdf.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]);
                pdf.text('Quotation by', margin + 3, 32);
                pdf.text('Quotation to', pageWidth - margin - 82, 32);
                pdf.setFont('helvetica', 'normal');
                pdf.setFontSize(9);
                pdf.setTextColor(0, 0, 0);
                pdf.text('MacJ Pest Control', margin + 3, 38);
                pdf.text(clientName, pageWidth - margin - 82, 38);
                pdf.setFontSize(8);
                pdf.setTextColor(grayText[0], grayText[1], grayText[2]);
                pdf.text('#29 Sto. Tomas St. Brgy. Don Manuel', margin + 3, 43);
                pdf.text('Quezon City', margin + 3, 47);
                pdf.text('Phone: (02) 7 369 3904 / 09171457316', margin + 3, 51);
                pdf.text('Email: macpest@yahoo.com', margin + 3, 55);
                const addressLines = pdf.splitTextToSize(propertyAddress, 80);
                addressLines.forEach((line, i) => {
                    if (i < 3) pdf.text(line, pageWidth - margin - 82, 43 + i * 4);
                });
                // QUOTATION TABLE
                pdf.setDrawColor(primaryColor[0], primaryColor[1], primaryColor[2]);
                pdf.setFillColor(255, 255, 255);
                pdf.rect(margin, 60, contentWidth, 10, 'FD');
                pdf.setFont('helvetica', 'bold');
                pdf.setFontSize(8);
                pdf.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]);
                pdf.text('Quotation #', margin + 3, 66);
                pdf.text('Date', margin + 45, 66);
                pdf.text('Property Type', margin + 85, 66);
                pdf.text('Area', margin + 140, 66);
                pdf.setFont('helvetica', 'normal');
                pdf.setTextColor(0, 0, 0);
                pdf.text(quotationNumber, margin + 23, 66);
                pdf.text(currentDate, margin + 60, 66);
                pdf.text('House', margin + 110, 66);
                pdf.text(area, margin + 155, 66);
                // CHEMICALS SECTION
                pdf.setFillColor(primaryColor[0], primaryColor[1], primaryColor[2]);
                pdf.rect(margin, 75, contentWidth, 8, 'F');
                pdf.setFont('helvetica', 'bold');
                pdf.setFontSize(9);
                pdf.setTextColor(255, 255, 255);
                pdf.text('Chemicals to be Used', margin + 3, 81);
                pdf.setFont('helvetica', 'normal');
                pdf.setFontSize(8);
                pdf.setTextColor(0, 0, 0);
                const chemLines = pdf.splitTextToSize(chemicalsText, contentWidth - 6);
                chemLines.forEach((line, i) => pdf.text(line, margin + 3, 88 + i * 5));
                pdf.setFont('helvetica', 'italic');
                pdf.setFontSize(7);
                pdf.setTextColor(60, 60, 60);
                pdf.text('Note: Specific chemicals and dosages may be adjusted based on on-site assessment.', margin + 3, 88 + chemLines.length * 5 + 4);
                // HORIZONTAL LINE
                let yPos = 88 + chemLines.length * 5 + 10;
                pdf.setDrawColor(180, 180, 180);
                pdf.line(margin, yPos, pageWidth - margin, yPos);
                // TOTAL AMOUNT
                pdf.setFont('helvetica', 'bold');
                    pdf.setFontSize(10); // Reduced from 12
                    pdf.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]); // Blue color for total
                    pdf.text('Total Amount', pageWidth - margin - 70, yPos);

                    // Format the total with commas for thousands and ensure it displays the full amount
                    // Use the Philippine Peso sign (₱)
                    const formattedTotal = `₱ ${totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    pdf.text(formattedTotal, pageWidth - margin - 10, yPos, { align: 'right' });

                // TERMS & JOB INFO COLUMNS
                yPos += 22;
                const colWidth = (contentWidth - 10) / 2;
                pdf.setFont('helvetica', 'bold');
                pdf.setFontSize(9);
                pdf.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]);
                pdf.text('Terms and Conditions', margin + 3, yPos);
                pdf.text('Job Order Information', margin + colWidth + 13, yPos);
                pdf.setFont('helvetica', 'normal');
                pdf.setFontSize(8);
                pdf.setTextColor(0, 0, 0);
                const terms = [
                    '• Service Visit Charges: You are required to pay for each visit conducted by our technicians, regardless of the number of visits made.',
                    '• Contract Duration: This agreement shall remain in effect for its full term, even if pest activity is no longer observed during the contract period.',
                    '• Additional visits may be required for severe infestations.'
                ];
                const jobInfo = [
                    `• Treatment Frequency: ${frequency}`,
                    `• Type of Work: ${typeOfWork}`,
                    `• Treatment Time: ${preferredTime}`,
                    '• Treatment will be performed according to the assessment findings.'
                ];
                let tY = yPos + 5;
                terms.forEach((t, i) => {
                    const lines = pdf.splitTextToSize(t, colWidth - 2);
                    pdf.text(lines, margin + 3, tY);
                    tY += lines.length * 5;
                });
                let jY = yPos + 5;
                jobInfo.forEach((j, i) => {
                    const lines = pdf.splitTextToSize(j, colWidth - 2);
                    pdf.text(lines, margin + colWidth + 13, jY);
                    jY += lines.length * 5;
                });
                // SIGNATURE LINE
                const sigY = Math.max(tY, jY) + 10;
                pdf.setDrawColor(primaryColor[0], primaryColor[1], primaryColor[2]);
                pdf.line(pageWidth - margin - 60, sigY, pageWidth - margin, sigY);
                pdf.setFont('helvetica', 'normal');
                pdf.setFontSize(8);
                pdf.setTextColor(0, 0, 0);
                pdf.text('Authorized Signature', pageWidth - margin - 30, sigY + 5, { align: 'center' });
                // FOOTER
                const footerY = sigY + 15;
                pdf.setFillColor(245, 247, 250);
                pdf.rect(0, footerY, pageWidth, 18, 'F');
                pdf.setDrawColor(200, 200, 200);
                pdf.line(margin, footerY, pageWidth - margin, footerY);
                pdf.setFont('helvetica', 'normal');
                pdf.setFontSize(8);
                pdf.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]);
                pdf.text('Thank you for choosing MacJ Pest Control Services', pageWidth / 2, footerY + 6, { align: 'center' });
                pdf.setTextColor(80, 80, 80);
                pdf.text('For inquiries, please contact us at (02) 7 369 3904 / 09171457316 or macpest@yahoo.com', pageWidth / 2, footerY + 11, { align: 'center' });
                pdf.text(`Quotation #${quotationNumber} | Generated on ${currentDate}`, pageWidth / 2, footerY + 16, { align: 'center' });
                pdf.save(`MacJ_Pest_Control_Quotation_${quotationNumber}.pdf`);
                loadingOverlay.style.display = 'none';
            } catch (error) {
                loadingOverlay.style.display = 'none';
                alert('An error occurred while generating the PDF. Please try again.');
            }
        }
    </script>

    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <script>
        // Ensure notification dropdown works and initialize notifications
        $(document).ready(function() {
            // Initialize notifications
            if (typeof initNotifications === 'function') {
                initNotifications();
            } else {
                console.error("initNotifications function not found");

                // Fallback notification handling if initNotifications is not available
                $('.notification-container').on('click', function(e) {
                    e.stopPropagation();
                    $('.notification-dropdown').toggleClass('show');
                    console.log('Notification icon clicked');
                });

                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.notification-container').length) {
                        $('.notification-dropdown').removeClass('show');
                    }
                });

                // Fetch notifications immediately
                if (typeof fetchNotifications === 'function') {
                    fetchNotifications();

                    // Set up periodic notification checks
                    setInterval(fetchNotifications, 60000); // Check every minute
                }
            }
        });
    </script>
</body>
</html>