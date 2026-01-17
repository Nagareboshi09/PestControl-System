<?php
session_start();
if ($_SESSION['role'] !== 'technician') {
    header("Location: SignIn.php");
    exit;
}
require_once '../db_connect.php';
require_once '../notification_functions.php';

$technician_id = $_SESSION['user_id'];

// Make sure we have the correct timezone set
date_default_timezone_set('Asia/Manila');

// Get today's date in YYYY-MM-DD format with the correct timezone
$today = date('Y-m-d', time());

// Clear any output buffering and set no-cache headers
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Fetch appointments using the old method - always use this for now
// We'll add the is_primary flag with a default value of 1 (true)
$stmt = $conn->prepare("
    SELECT a.*, c.first_name, c.last_name, c.contact_number, c.email, 1 as is_primary
    FROM appointments a
    JOIN clients c ON a.client_id = c.client_id
    WHERE a.technician_id = ? AND a.status != 'completed'
    ORDER BY a.preferred_date ASC
");
$stmt->bind_param("i", $technician_id);

$stmt->execute();
$result = $stmt->get_result();

$todayJobs = [];
$upcomingJobs = [];
$finishedJobs = [];

while ($row = $result->fetch_assoc()) {
    $row['client_name'] = $row['first_name'] . ' ' . $row['last_name'];

    // Direct string comparison for dates in YYYY-MM-DD format
    // This is the simplest and most reliable method for this specific format
    if ($row['preferred_date'] === $today) {
        $todayJobs[] = $row;
        // Debug output
        error_log("Added to TODAY: Client {$row['client_name']}, Date: {$row['preferred_date']}, Today: {$today}");
    } elseif ($row['preferred_date'] > $today) {
        $upcomingJobs[] = $row;
        // Debug output
        error_log("Added to UPCOMING: Client {$row['client_name']}, Date: {$row['preferred_date']}, Today: {$today}");
    } else {
        // Debug output for past dates
        error_log("PAST DATE: Client {$row['client_name']}, Date: {$row['preferred_date']}, Today: {$today}");
    }
}

// Fetch completed jobs
$stmt = $conn->prepare("
    SELECT a.*, c.first_name, c.last_name, c.contact_number, c.email,
           r.end_time, r.area, r.notes, r.recommendation, r.attachments, r.pest_types, r.problem_area, r.created_at as report_date,
           1 as is_primary
    FROM appointments a
    JOIN clients c ON a.client_id = c.client_id
    LEFT JOIN assessment_report r ON a.appointment_id = r.appointment_id
    WHERE a.technician_id = ? AND a.status = 'completed'
    ORDER BY a.preferred_date DESC
");
$stmt->bind_param("i", $technician_id);
$stmt->execute();
$finishedJobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>


<!-- Debug information has been removed for production -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard</title>
    <link href="https://unpkg.com/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <!-- Unified Design System CSS -->
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/sidebar-new.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/technician-common.css">
    <link rel="stylesheet" href="css/footer.css">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="css/tools-checklist.css">
    <link rel="stylesheet" href="css/table-fix.css">
    <link rel="stylesheet" href="css/header-fix.css">
    <link rel="stylesheet" href="css/sidebar-fix.css">
    <style>
        /* Hide the scheduled for badge */
        .scheduled-date {
            display: none !important;
        }

        .badge-custom {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-bottom: 10px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <!-- Menu Toggle Button for Mobile -->
    <button id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Navigation -->
    <aside id="sidebar">
        <div class="sidebar-header">
            <h2>MacJ Pest Control</h2>
            <h3>Welcome, <?= $_SESSION['username'] ?? 'Technician' ?></h3>
        </div>
        <nav class="sidebar-menu">
            <a href="schedule.php">
                <i class="fas fa-calendar-alt fa-icon"></i>
                My Schedule
            </a>
            <a href="inspection.php" class="active">
                <i class="fas fa-clipboard-list fa-icon"></i>
                Inspection Board
            </a>
            <a href="job_order.php">
                <i class="fas fa-tasks fa-icon"></i>
                Job Order Board
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

    <!-- Header -->
    <header class="header">
        <div class="header-title">
            <h1></h1>
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
                    <div class="user-name"><?= $_SESSION['username'] ?? 'Technician' ?></div>
                    <div class="user-role"></div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-header">
            <h1><i class="fas fa-clipboard-list"></i> Inspection Board</h1>
        </div>
        <!-- Today's Jobs -->
        <div class="job-section">
            <h3><i class="fas fa-calendar-day"></i> Today's Inspection</h3>
            <div class="row">
                <?php foreach ($todayJobs as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="card job-card" style="cursor: pointer; transition: transform 0.3s ease;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($job['client_name']) ?></h5>
                            <p class="text-muted"><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($job['preferred_time'])) ?></p>
                            <span class="badge bg-primary mb-2">Today's Schedule</span>
                            <?php if (isset($job['is_primary']) && $job['is_primary']): ?>
                            <span class="badge bg-info mb-2">Primary Technician</span>
                            <?php else: ?>
                            <span class="badge bg-secondary mb-2">Secondary Technician</span>
                            <?php endif; ?>
                            <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); openDetails(<?= htmlspecialchars(json_encode($job)) ?>)">
                                View Details
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($todayJobs)): ?>
                <div class="col-12">
                    <div class="alert alert-info">No inspections scheduled for today</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Jobs -->
        <div class="job-section upcoming-inspections">
            <h3><i class="fas fa-calendar-alt"></i> Upcoming Inspection</h3>
            <div class="row">
                <?php foreach ($upcomingJobs as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="card job-card" style="opacity: 0.8; background-color: #f8f9fa;">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($job['client_name']) ?></h5>
                            <p class="text-muted"><?= $job['preferred_date'] ?></p>
                            <p class="text-muted"><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($job['preferred_time'])) ?></p>
                            <span class="badge bg-secondary">Upcoming - Not Yet Available</span>
                            <?php if (isset($job['is_primary']) && $job['is_primary']): ?>
                            <span class="badge bg-info">Primary Technician</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Secondary Technician</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($upcomingJobs)): ?>
                <div class="col-12">
                    <div class="alert alert-info">No upcoming inspections scheduled</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Finished Jobs -->
        <div class="job-section">
            <h3><i class="fas fa-check-circle"></i> Finished Inspection</h3>
            <div class="row">
                <?php foreach ($finishedJobs as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="card job-card" style="cursor: pointer;" onclick="openFinishedDetails(<?= htmlspecialchars(json_encode($job)) ?>)">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($job['client_name']) ?></h5>
                            <span class="badge badge-custom">Completed</span>
                            <?php if($job['end_time']): ?>
                                <p class="text-muted mb-0">Report submitted: <?= date('M j, Y', strtotime($job['report_date'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal">
        <div class="modal-dialog modal-lg modal-zoom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Inspection Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalDetailsContent">
                    <!-- Dynamic content loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="openReportForm()" id="sendReportBtn">
                        <i class="fas fa-paper-plane"></i> Send Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Form Modal -->
    <div class="modal fade" id="reportModal" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-zoom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="reportForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="appointment_id" id="reportAppointmentId">
                        <!-- End time is now automatically recorded upon submission -->
                        <div class="mb-3">
                            <label>Area (m²)</label>
                            <input type="number" step="0.01" name="area" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Pest Types</label>
                            <div class="row" id="pestCheckboxContainer">
                                <!-- Pest checkboxes will be loaded dynamically -->
                                <div class="col-12 text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                    <p>Loading pest options...</p>
                                </div>
                            </div>
                            <div id="otherPestTypeContainer" style="display: none; margin-top: 8px;" class="row">
                                <div class="col-12">
                                    <input type="text" class="form-control" name="other_pest_type" id="otherPestType" placeholder="Please specify other pest types">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Problem Area</label>
                            <input type="text" name="problem_area" class="form-control" placeholder="e.g. Kitchen, Living Room, Bedroom, etc.">
                        </div>
                        <div class="mb-3">
                            <label>Additional Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Recommendation</label>
                            <textarea name="recommendation" class="form-control" rows="3" placeholder="Enter your recommendations for pest control treatment"></textarea>
                        </div>

                        <hr>
                        <h5 class="mb-3">Scope of work</h5>

                        <div class="mb-3">
                            <label><i class="fas fa-briefcase"></i> Type of Work</label>
                            <div class="row" id="workTypesContainer">
                                <div class="col-md-6">
                                <?php
                                // Check if services table exists
                                $services_result = $conn->query("SHOW TABLES LIKE 'services'");
                                if ($services_result && $services_result->num_rows > 0) {
                                    // Get active services
                                    $services_query = "SELECT name FROM services WHERE status = 'active' ORDER BY name";
                                    $services_result = $conn->query($services_query);

                                    if ($services_result && $services_result->num_rows > 0) {
                                        $count = 0;
                                        $total = $services_result->num_rows;
                                        $halfway = ceil($total / 2);

                                        while ($service = $services_result->fetch_assoc()) {
                                            $count++;
                                            $service_id = 'work' . str_replace(' ', '', $service['name']);

                                            // Close first column and open second column at halfway point
                                            if ($count == $halfway) {
                                                echo '</div><div class="col-md-6">';
                                            }

                                            echo '<div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="type_of_work[]" value="' . htmlspecialchars($service['name']) . '" id="' . $service_id . '">
                                                <label class="form-check-label" for="' . $service_id . '">' . htmlspecialchars($service['name']) . '</label>
                                            </div>';
                                        }
                                    } else {
                                        // Fallback if no services found in the database
                                        echo '<div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="type_of_work[]" value="General Pest Control" id="workGeneral">
                                            <label class="form-check-label" for="workGeneral">General Pest Control</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="type_of_work[]" value="Termite Control" id="workTermite">
                                            <label class="form-check-label" for="workTermite">Termite Control</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="type_of_work[]" value="Rodent Control" id="workRodent">
                                            <label class="form-check-label" for="workRodent">Rodent Control</label>
                                        </div>
                                        </div><div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="type_of_work[]" value="Disinfection" id="workDisinfection">
                                            <label class="form-check-label" for="workDisinfection">Disinfection</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="type_of_work[]" value="Weed Control" id="workWeed">
                                            <label class="form-check-label" for="workWeed">Weed Control</label>
                                        </div>';
                                    }
                                } else {
                                    // Fallback if services table doesn't exist
                                    echo '<div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="type_of_work[]" value="General Pest Control" id="workGeneral">
                                        <label class="form-check-label" for="workGeneral">General Pest Control</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="type_of_work[]" value="Termite Control" id="workTermite">
                                        <label class="form-check-label" for="workTermite">Termite Control</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="type_of_work[]" value="Rodent Control" id="workRodent">
                                        <label class="form-check-label" for="workRodent">Rodent Control</label>
                                    </div>
                                    </div><div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="type_of_work[]" value="Disinfection" id="workDisinfection">
                                        <label class="form-check-label" for="workDisinfection">Disinfection</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="type_of_work[]" value="Weed Control" id="workWeed">
                                        <label class="form-check-label" for="workWeed">Weed Control</label>
                                    </div>';
                                }
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="type_of_work[]" value="Other" id="workOther" onchange="toggleOtherWorkField()">
                                        <label class="form-check-label" for="workOther">Other</label>
                                    </div>
                                </div>
                            </div>
                            <div id="otherWorkTypeContainer" style="display: none; margin-top: 8px;" class="row">
                                <div class="col-12">
                                    <input type="text" class="form-control" name="other_work_type" id="otherWorkType" placeholder="Please specify other type of work">
                                </div>
                            </div>
                        </div>

                        <div class="detail-grid">
                            <div class="mb-3">
                                <label><i class="fas fa-calendar-day"></i> Preferred Date</label>
                                <input type="date" name="preferred_date" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label><i class="fas fa-clock"></i> Preferred Time</label>
                                <input type="time" name="preferred_time" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label><i class="fas fa-sync-alt"></i> Treatment Frequency</label>
                            <select name="frequency" id="frequency" class="form-control" required>
                                <option value="one-time">One-time Treatment</option>
                                <option value="weekly">Weekly (Recurring for 1 year)</option>
                                <option value="monthly">Monthly (Recurring for 1 year)</option>
                                <option value="quarterly">Quarterly (Recurring for 1 year)</option>
                            </select>
                            <p class="form-help"><i class="fas fa-info-circle"></i> For recurring treatments, appointments will be automatically scheduled for one year from the initial date.</p>
                        </div>
                        <hr>

                        <div class="mb-3">
                            <label>Chemical Recommendations</label>
                            <div id="selectedChemicalsContainer" class="mt-2">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <span>No chemicals have been selected yet. Click "Generate Recommendations" to get started.</span>
                                </div>
                            </div>
                            <input type="hidden" id="selectedChemicals" name="selected_chemicals" value="">
                            <!-- Add a debug message to verify the hidden input is included in the form -->
                            <div id="chemicalDebugInfo" class="small text-muted mt-1">
                                Chemical data will appear here when selected
                            </div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#chemicalRecommendationsModal">
                                <i class="fas fa-flask"></i> Generate Recommendations
                            </button>
                            <!-- Add a manual input option for testing -->
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleManualChemicalInput()">
                                    <i class="fas fa-edit"></i> Manual Input (For Testing)
                                </button>
                                <div id="manualChemicalInputContainer" style="display: none;" class="mt-2">
                                    <textarea class="form-control" id="manualChemicalInput" rows="3" placeholder='[{"id":"1","name":"Imidaclopred","type":"Insecticide","dosage":"40","dosage_unit":"ml","target_pest":"Crawling & Flying Pest"}]'></textarea>
                                    <small class="form-text text-muted">Example shows dosage for 200m² area (20ml per 100m²)</small>
                                    <button type="button" class="btn btn-sm btn-primary mt-1" onclick="applyManualChemicalInput()">Apply</button>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label>Upload Images</label>
                            <input type="file" name="attachments[]" class="form-control" multiple accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="cancelReportBtn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Finished Insepction Details Modal -->
    <div class="modal fade" id="finishedDetailsModal">
        <div class="modal-dialog modal-lg modal-zoom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Completed Inspection Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="finishedModalContent">
                    <!-- Dynamic content loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Confirmation Modal -->
    <div class="modal fade" id="reportConfirmationModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Report Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-bold">Please verify that your report is complete and accurate before final submission.</p>
                    <p>Once submitted, this report cannot be edited and will be sent to the client and admin.</p>

                    <div class="alert alert-info">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Please confirm you have:</h6>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="checkAll">
                                <label class="form-check-label" for="checkAll"><strong>Check All</strong></label>
                            </div>
                        </div>
                        <!-- End time is now automatically recorded upon submission -->
                        <div class="form-check mb-2">
                            <input class="form-check-input confirmation-checkbox" type="checkbox" id="checkArea">
                            <label class="form-check-label" for="checkArea">Measured and entered the correct area</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input confirmation-checkbox" type="checkbox" id="checkPestTypes">
                            <label class="form-check-label" for="checkPestTypes">Selected all relevant pest types</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input confirmation-checkbox" type="checkbox" id="checkProblemArea">
                            <label class="form-check-label" for="checkProblemArea">Specified the problem area</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input confirmation-checkbox" type="checkbox" id="checkNotes">
                            <label class="form-check-label" for="checkNotes">Added detailed notes about the inspection</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input confirmation-checkbox" type="checkbox" id="checkRecommendation">
                            <label class="form-check-label" for="checkRecommendation">Provided treatment recommendations</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input confirmation-checkbox" type="checkbox" id="checkWorkTypes">
                            <label class="form-check-label" for="checkWorkTypes">Selected appropriate type(s) of work</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input confirmation-checkbox" type="checkbox" id="checkSchedule">
                            <label class="form-check-label" for="checkSchedule">Set preferred date and time</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input confirmation-checkbox" type="checkbox" id="checkFrequency">
                            <label class="form-check-label" for="checkFrequency">Selected treatment frequency</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input confirmation-checkbox" type="checkbox" id="checkAttachments">
                            <label class="form-check-label" for="checkAttachments">Attached all relevant photos/documents</label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Go Back & Edit</button>
                        <button type="button" class="btn btn-success" id="finalSubmitBtn" disabled>
                            <i class="fas fa-paper-plane me-1"></i> Submit Final Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <!-- Sidebar Fix Script -->
    <script src="js/sidebar-fix.js"></script>
    <script>
        // Load pest checkboxes when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadPestCheckboxes();
        });

        // Function to load pest checkboxes
        function loadPestCheckboxes() {
            fetch('get_pest_checkboxes.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data && data.data.length > 0) {
                        renderPestCheckboxes(data.data);
                    } else {
                        // Fallback to default pest checkboxes if API fails
                        const defaultPests = [
                            { id: 1, name: 'Flies' },
                            { id: 2, name: 'Ants' },
                            { id: 3, name: 'Cockroaches' },
                            { id: 4, name: 'Bed Bugs' },
                            { id: 5, name: 'Mice/Rats' },
                            { id: 6, name: 'Termites' },
                            { id: 7, name: 'Mosquitoes' },
                            { id: 8, name: 'Grass' },
                            { id: 9, name: 'Disinfect Area' },
                            { id: 10, name: 'Others' }
                        ];
                        renderPestCheckboxes(defaultPests);
                    }
                })
                .catch(error => {
                    console.error('Error loading pest checkboxes:', error);
                    // Fallback to default pest checkboxes if API fails
                    const defaultPests = [
                        { id: 1, name: 'Flies' },
                        { id: 2, name: 'Ants' },
                        { id: 3, name: 'Cockroaches' },
                        { id: 4, name: 'Bed Bugs' },
                        { id: 5, name: 'Mice/Rats' },
                        { id: 6, name: 'Termites' },
                        { id: 7, name: 'Mosquitoes' },
                        { id: 8, name: 'Grass' },
                        { id: 9, name: 'Disinfect Area' },
                        { id: 10, name: 'Others' }
                    ];
                    renderPestCheckboxes(defaultPests);
                });
        }

        // Function to render pest checkboxes
        function renderPestCheckboxes(pests) {
            const container = document.getElementById('pestCheckboxContainer');
            if (!container) return;

            // Create two columns for the checkboxes
            const leftColumn = document.createElement('div');
            leftColumn.className = 'col-md-6';

            const rightColumn = document.createElement('div');
            rightColumn.className = 'col-md-6';

            // Determine the midpoint to split the checkboxes into two columns
            const midpoint = Math.ceil(pests.length / 2);

            // Add checkboxes to the columns
            pests.forEach((pest, index) => {
                const id = 'pest' + pest.name.replace(/[^a-zA-Z0-9]/g, '');

                const checkboxDiv = document.createElement('div');
                checkboxDiv.className = 'form-check';

                checkboxDiv.innerHTML = `
                    <input class="form-check-input" type="checkbox" name="pest_types[]" value="${pest.name}" id="${id}" ${pest.name === 'Others' ? 'onchange="toggleOtherPestField()"' : ''}>
                    <label class="form-check-label" for="${id}">${pest.name}</label>
                `;

                // Add to left or right column based on index
                if (index < midpoint) {
                    leftColumn.appendChild(checkboxDiv);
                } else {
                    rightColumn.appendChild(checkboxDiv);
                }
            });

            // Clear the container and add the columns
            container.innerHTML = '';
            container.appendChild(leftColumn);
            container.appendChild(rightColumn);
        }

        let currentJob = null;

        // Function to reset selected chemicals
        function resetSelectedChemicals() {
            console.log('Resetting selected chemicals');

            // Try to use the global function from chemical-recommendations.js if available
            if (typeof resetAllSelectedChemicals === 'function') {
                resetAllSelectedChemicals();
                console.log('Used resetAllSelectedChemicals function from chemical-recommendations.js');
                return;
            }

            // Fallback implementation if the global function is not available
            console.log('resetAllSelectedChemicals function not found, using fallback implementation');

            // Clear the global selectedChemicals array if it exists
            if (typeof selectedChemicals !== 'undefined') {
                selectedChemicals = [];
                console.log('Cleared selectedChemicals array');
            }

            // Clear session storage
            sessionStorage.removeItem('selectedChemicals');
            console.log('Removed selectedChemicals from session storage');

            // Reset the hidden input
            const hiddenInput = document.getElementById('selectedChemicals');
            if (hiddenInput) {
                hiddenInput.value = '';
                console.log('Reset selectedChemicals hidden input');
            }

            // Reset the display container
            const container = document.getElementById('selectedChemicalsContainer');
            if (container) {
                container.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span>No chemicals have been selected yet. Click "Generate Recommendations" to get started.</span>
                    </div>
                `;
                console.log('Reset selectedChemicalsContainer display');
            }
        }

        // Function to toggle the visibility of the "Other" pest type field
        function toggleOtherPestField() {
            const otherCheckbox = document.getElementById('pestOthers');
            const otherFieldContainer = document.getElementById('otherPestTypeContainer');

            if (otherCheckbox.checked) {
                otherFieldContainer.style.display = 'block';
            } else {
                otherFieldContainer.style.display = 'none';
                document.getElementById('otherPestType').value = '';
            }
        }

        // Function to toggle the visibility of the "Other" work type field
        function toggleOtherWorkField() {
            const otherCheckbox = document.getElementById('workOther');
            const otherFieldContainer = document.getElementById('otherWorkTypeContainer');

            if (otherCheckbox.checked) {
                otherFieldContainer.style.display = 'block';
            } else {
                otherFieldContainer.style.display = 'none';
                document.getElementById('otherWorkType').value = '';
            }
        }

        // Reset the form when the modal is closed
        document.getElementById('reportModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('reportForm').reset();
            document.getElementById('otherPestTypeContainer').style.display = 'none';
            document.getElementById('otherWorkTypeContainer').style.display = 'none';

            // Reset selected chemicals
            resetSelectedChemicals();
        });

        function openDetails(job) {
            try {
                console.log('Opening details for job:', job);

                // If job is a string (which can happen with JSON encoding/decoding issues), try to parse it
                if (typeof job === 'string') {
                    try {
                        job = JSON.parse(job);
                        console.log('Successfully parsed job string to object:', job);
                    } catch (parseError) {
                        console.error('Error parsing job string:', parseError);
                        Swal.fire({
                            title: 'Error',
                            text: 'There was an error loading the job details. Please try again.',
                            icon: 'error'
                        });
                        return;
                    }
                }

                // Store the current job for later use
                currentJob = job;

                // Create the content for the modal
                const content = `
                    <h5>INFORMATION OF THE CLIENT</h5>
                    <p><strong>Client Name:</strong> ${job.client_name || 'N/A'}</p>
                    <p><strong>Date:</strong> ${job.preferred_date || 'N/A'}</p>
                    <p><strong>Time:</strong> ${job.preferred_time || 'N/A'}</p>
                    <p><strong>Location:</strong> ${job.location_address || 'N/A'}</p>
                    <p><strong>Type of Place:</strong> ${job.kind_of_place || 'N/A'}</p>
                    <p><strong>Contact:</strong> ${job.contact_number || 'N/A'}</p>
                    <p><strong>Pest Problems:</strong> ${job.pest_problems || 'None specified'}</p>
                    <p><strong>Notes:</strong> ${job.notes || 'N/A'}</p>
                `;

                // Update the modal content
                const modalContent = document.getElementById('modalDetailsContent');
                if (modalContent) {
                    modalContent.innerHTML = content;
                } else {
                    console.error('Modal content element not found');
                    return;
                }

                // Always show the Send Report button for now
                const sendReportBtn = document.getElementById('sendReportBtn');
                if (sendReportBtn) {
                    sendReportBtn.style.display = 'inline-block';
                } else {
                    console.error('Send report button not found');
                }

                // Show the modal
                const detailsModal = document.getElementById('detailsModal');
                if (detailsModal) {
                    const modal = new bootstrap.Modal(detailsModal);
                    modal.show();
                } else {
                    console.error('Details modal element not found');
                }
            } catch (error) {
                console.error('Error in openDetails function:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'There was an error loading the job details. Please try again.',
                    icon: 'error'
                });
            }
        }

        function openReportForm() {
            new bootstrap.Modal('#detailsModal').hide();
            document.getElementById('reportAppointmentId').value = currentJob.appointment_id;
            new bootstrap.Modal('#reportModal').show();
        }

        // Store the form data globally for later submission
        let reportFormData = null;

        // Handle report form submission
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();

            console.log('Report form submitted');

            // Check if the selectedChemicals hidden input has a value
            const selectedChemicalsInput = document.getElementById('selectedChemicals');
            if (selectedChemicalsInput) {
                console.log('Selected chemicals input value:', selectedChemicalsInput.value);
                console.log('Selected chemicals input length:', selectedChemicalsInput.value.length);

                // If the input is empty, try to get the value from session storage
                if (!selectedChemicalsInput.value) {
                    const storedChemicals = sessionStorage.getItem('selectedChemicals');
                    if (storedChemicals) {
                        selectedChemicalsInput.value = storedChemicals;
                        console.log('Retrieved chemicals from session storage:', storedChemicals);
                    }
                }

                // If still empty, try to get from the global variable
                if (!selectedChemicalsInput.value && typeof selectedChemicals !== 'undefined' && selectedChemicals.length > 0) {
                    selectedChemicalsInput.value = JSON.stringify(selectedChemicals);
                    console.log('Retrieved chemicals from global variable:', selectedChemicalsInput.value);
                }

                // If still empty, set a default value for testing
                if (!selectedChemicalsInput.value) {
                    // Get the area value to calculate the correct dosage
                    const areaInput = this.querySelector('[name="area"]');
                    const area = areaInput ? parseFloat(areaInput.value) : 200; // Default to 200 if not found

                    // Calculate dosage based on area (20ml per 100sqm for Imidaclopred)
                    const dilutionRate = 20; // ml per liter
                    const areaCoverage = 100; // sqm per liter
                    const solutionAmount = (area / areaCoverage).toFixed(2); // liters
                    const dosage = (dilutionRate * solutionAmount).toFixed(2); // ml

                    const defaultChemical = [{"id":"1","name":"Imidaclopred","type":"Insecticide","dosage":dosage,"dosage_unit":"ml","target_pest":"Crawling & Flying Pest"}];
                    selectedChemicalsInput.value = JSON.stringify(defaultChemical);
                    console.log(`Using default chemical for testing with calculated dosage: ${dosage}ml for ${area}sqm`);

                    // Show a warning to the user
                    Swal.fire({
                        title: 'No Chemicals Selected',
                        text: 'Using a default chemical for testing purposes. In a real scenario, you should select chemicals using the "Generate Recommendations" button.',
                        icon: 'warning',
                        confirmButtonText: 'Continue'
                    });
                }
            }

            // Check if "Others" is selected but the other field is empty for pest types
            const othersChecked = document.getElementById('pestOthers') && document.getElementById('pestOthers').checked;
            const otherFieldValue = document.getElementById('otherPestType') ? document.getElementById('otherPestType').value.trim() : '';

            if (othersChecked && otherFieldValue === '') {
                // Show a warning and focus on the field
                Swal.fire({
                    title: 'Missing Information',
                    text: 'You selected "Others" for pest types. Please specify what other pest types were found.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                document.getElementById('otherPestType').focus();
                return;
            }

            // Check if "Other" is selected but the other field is empty for work types
            const otherWorkChecked = document.getElementById('workOther').checked;
            const otherWorkValue = document.getElementById('otherWorkType').value.trim();

            if (otherWorkChecked && otherWorkValue === '') {
                // Show a warning and focus on the field
                Swal.fire({
                    title: 'Missing Information',
                    text: 'You selected "Other" for type of work. Please specify what other type of work is needed.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                document.getElementById('otherWorkType').focus();
                return;
            }

            // Check if at least one work type is selected
            const workTypesChecked = this.querySelectorAll('[name="type_of_work[]"]:checked');
            if (workTypesChecked.length === 0) {
                // Show a warning
                Swal.fire({
                    title: 'Missing Information',
                    text: 'Please select at least one type of work.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // We already have the selectedChemicalsInput from earlier, no need to get it again
            // Just update it if needed
            if (!selectedChemicalsInput.value) {
                const storedChemicals = sessionStorage.getItem('selectedChemicals');
                if (storedChemicals) {
                    selectedChemicalsInput.value = storedChemicals;
                    console.log('Updated hidden input with chemicals from session storage');
                } else if (typeof selectedChemicals !== 'undefined' && selectedChemicals.length > 0) {
                    selectedChemicalsInput.value = JSON.stringify(selectedChemicals);
                    console.log('Updated hidden input with chemicals from global variable');
                }
            }

            // Store the form data for later use
            reportFormData = new FormData(this);

            // Log the form data for debugging
            console.log('Form data created with ' + reportFormData.get('selected_chemicals') + ' for selected_chemicals');

            // Get form values for validation
            const area = this.querySelector('[name="area"]').value;
            const notes = this.querySelector('[name="notes"]').value;
            const recommendation = this.querySelector('[name="recommendation"]').value;
            const problemArea = this.querySelector('[name="problem_area"]').value;
            const pestTypesChecked = this.querySelectorAll('[name="pest_types[]"]:checked');
            const attachments = this.querySelector('[name="attachments[]"]').files;

            // Show the confirmation modal
            const confirmationModal = new bootstrap.Modal(document.getElementById('reportConfirmationModal'));
            confirmationModal.show();

            // Reset checkboxes
            document.querySelectorAll('#reportConfirmationModal .form-check-input').forEach(checkbox => {
                checkbox.checked = false;
            });

            // Update the confirmation dialog with field status
            const areaCheck = document.getElementById('checkArea');
            const pestTypesCheck = document.getElementById('checkPestTypes');
            const problemAreaCheck = document.getElementById('checkProblemArea');
            const notesCheck = document.getElementById('checkNotes');
            const recommendationCheck = document.getElementById('checkRecommendation');
            const attachmentsCheck = document.getElementById('checkAttachments');

            // Add visual indicators for filled fields

            if (area) {
                areaCheck.parentElement.classList.add('text-success');
                areaCheck.parentElement.querySelector('label').innerHTML =
                    `Measured and entered the correct area <strong>(${area} m²)</strong>`;
            }

            if (pestTypesChecked && pestTypesChecked.length > 0) {
                pestTypesCheck.parentElement.classList.add('text-success');

                // Create a list of selected pest types, including the "Other" value if specified
                let selectedPests = Array.from(pestTypesChecked).map(cb => cb.value);

                // If "Others" is selected and the other field has a value, include it in the display
                if (selectedPests.includes('Others') && document.getElementById('otherPestType').value) {
                    const otherIndex = selectedPests.indexOf('Others');
                    selectedPests[otherIndex] = 'Others: ' + document.getElementById('otherPestType').value;
                }

                pestTypesCheck.parentElement.querySelector('label').innerHTML =
                    `Selected all relevant pest types <strong>(${selectedPests.join(', ')})</strong>`;
            }

            if (problemArea) {
                problemAreaCheck.parentElement.classList.add('text-success');
                problemAreaCheck.parentElement.querySelector('label').innerHTML =
                    `Specified the problem area <strong>(${problemArea})</strong>`;
            }

            if (notes && notes.length > 10) {
                notesCheck.parentElement.classList.add('text-success');
                notesCheck.parentElement.querySelector('label').innerHTML =
                    `Added detailed notes about the inspection <strong>(${notes.length} characters)</strong>`;
            }

            if (recommendation && recommendation.length > 10) {
                recommendationCheck.parentElement.classList.add('text-success');
                recommendationCheck.parentElement.querySelector('label').innerHTML =
                    `Provided treatment recommendations <strong>(${recommendation.length} characters)</strong>`;
            }

            // Check work types
            const workTypesCheck = document.getElementById('checkWorkTypes');
            if (workTypesChecked && workTypesChecked.length > 0) {
                workTypesCheck.parentElement.classList.add('text-success');

                // Create a list of selected work types, including the "Other" value if specified
                let selectedWorks = Array.from(workTypesChecked).map(cb => cb.value);

                // If "Other" is selected and the other field has a value, include it in the display
                if (selectedWorks.includes('Other') && document.getElementById('otherWorkType').value) {
                    const otherIndex = selectedWorks.indexOf('Other');
                    selectedWorks[otherIndex] = 'Other: ' + document.getElementById('otherWorkType').value;
                }

                workTypesCheck.parentElement.querySelector('label').innerHTML =
                    `Selected appropriate type(s) of work <strong>(${selectedWorks.join(', ')})</strong>`;
            }

            // Check preferred date and time
            const scheduleCheck = document.getElementById('checkSchedule');
            const preferredDate = this.querySelector('[name="preferred_date"]').value;
            const preferredTime = this.querySelector('[name="preferred_time"]').value;

            if (preferredDate && preferredTime) {
                scheduleCheck.parentElement.classList.add('text-success');
                // Format the date for display
                const formattedDate = new Date(preferredDate).toLocaleDateString();
                // Format the time for display (convert from 24h to 12h format)
                const timeArr = preferredTime.split(':');
                const hours = parseInt(timeArr[0]);
                const minutes = timeArr[1];
                const ampm = hours >= 12 ? 'PM' : 'AM';
                const hours12 = hours % 12 || 12;
                const formattedTime = `${hours12}:${minutes} ${ampm}`;

                scheduleCheck.parentElement.querySelector('label').innerHTML =
                    `Set preferred date and time <strong>(${formattedDate} at ${formattedTime})</strong>`;
            }

            // Check frequency
            const frequencyCheck = document.getElementById('checkFrequency');
            const frequency = this.querySelector('[name="frequency"]').value;

            if (frequency) {
                frequencyCheck.parentElement.classList.add('text-success');

                // Get the display text for the frequency
                let frequencyText = '';
                switch (frequency) {
                    case 'one-time':
                        frequencyText = 'One-time Treatment';
                        break;
                    case 'weekly':
                        frequencyText = 'Weekly (Recurring for 1 year)';
                        break;
                    case 'monthly':
                        frequencyText = 'Monthly (Recurring for 1 year)';
                        break;
                    case 'quarterly':
                        frequencyText = 'Quarterly (Recurring for 1 year)';
                        break;
                }

                frequencyCheck.parentElement.querySelector('label').innerHTML =
                    `Selected treatment frequency <strong>(${frequencyText})</strong>`;
            }

            if (attachments && attachments.length > 0) {
                attachmentsCheck.parentElement.classList.add('text-success');
                attachmentsCheck.parentElement.querySelector('label').innerHTML =
                    `Attached all relevant photos/documents <strong>(${attachments.length} files)</strong>`;
            }

            // Disable the final submit button until all checkboxes are checked
            document.getElementById('finalSubmitBtn').disabled = true;
        });

        // Handle "Check All" checkbox
        document.getElementById('checkAll').addEventListener('change', function() {
            // Get all confirmation checkboxes
            const confirmationCheckboxes = document.querySelectorAll('.confirmation-checkbox');

            // Set all checkboxes to the same state as the "Check All" checkbox
            confirmationCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });

            // Enable or disable the final submit button
            document.getElementById('finalSubmitBtn').disabled = !this.checked;
        });

        // Handle individual checkbox changes in the confirmation modal
        document.querySelectorAll('.confirmation-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                // Check if all confirmation checkboxes are checked
                const allConfirmationChecked = Array.from(
                    document.querySelectorAll('.confirmation-checkbox')
                ).every(cb => cb.checked);

                // Update the "Check All" checkbox
                document.getElementById('checkAll').checked = allConfirmationChecked;

                // Enable or disable the final submit button
                document.getElementById('finalSubmitBtn').disabled = !allConfirmationChecked;
            });
        });

        // Reset confirmation modal when it's closed
        document.getElementById('reportConfirmationModal').addEventListener('hidden.bs.modal', function() {
            // Reset all checkboxes including "Check All"
            document.querySelectorAll('#reportConfirmationModal .form-check-input').forEach(checkbox => {
                checkbox.checked = false;
            });

            // Reset visual indicators
            document.querySelectorAll('#reportConfirmationModal .form-check').forEach(checkItem => {
                checkItem.classList.remove('text-success');
            });

            // Reset labels
            document.getElementById('checkArea').parentElement.querySelector('label').textContent = 'Measured and entered the correct area';
            document.getElementById('checkPestTypes').parentElement.querySelector('label').textContent = 'Selected all relevant pest types';
            document.getElementById('checkProblemArea').parentElement.querySelector('label').textContent = 'Specified the problem area';
            document.getElementById('checkNotes').parentElement.querySelector('label').textContent = 'Added detailed notes about the inspection';
            document.getElementById('checkRecommendation').parentElement.querySelector('label').textContent = 'Provided treatment recommendations';
            document.getElementById('checkWorkTypes').parentElement.querySelector('label').textContent = 'Selected appropriate type(s) of work';
            document.getElementById('checkSchedule').parentElement.querySelector('label').textContent = 'Set preferred date and time';
            document.getElementById('checkFrequency').parentElement.querySelector('label').textContent = 'Selected treatment frequency';
            document.getElementById('checkAttachments').parentElement.querySelector('label').textContent = 'Attached all relevant photos/documents';

            // Disable the final submit button
            document.getElementById('finalSubmitBtn').disabled = true;
            document.getElementById('finalSubmitBtn').innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit Final Report';
        });

        // Handle final submission
        document.getElementById('finalSubmitBtn').addEventListener('click', function() {
            // Show loading state
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Submitting...';

            // Get the form element
            const reportForm = document.getElementById('reportForm');

            // Create a new FormData object directly from the form
            const directFormData = new FormData(reportForm);

            // Make sure the selected chemicals are included
            const selectedChemicalsInput = document.getElementById('selectedChemicals');
            if (selectedChemicalsInput && selectedChemicalsInput.value) {
                directFormData.set('selected_chemicals', selectedChemicalsInput.value);
            }

            // Log what we're submitting
            console.log('Submitting form data to direct_submit.php');

            // Simple direct submission with minimal code
            fetch('direct_submit.php', {
                method: 'POST',
                body: directFormData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);

                // Try to parse the JSON response
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                    throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                }

                // Hide the confirmation modal
                const confirmationModal = document.getElementById('reportConfirmationModal');
                if (confirmationModal) {
                    const bsModal = bootstrap.Modal.getInstance(confirmationModal);
                    if (bsModal) {
                        bsModal.hide();
                    }
                }

                if (data.success) {
                    // Show success message
                    Swal.fire({
                        title: 'Success!',
                        text: 'Your report has been submitted successfully.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        // Reload the page to refresh the data
                        window.location.reload();
                    });
                } else {
                    // Re-enable the submit button
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit Final Report';

                    // Show error message
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Failed to submit report',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Error during submission:', error);

                // Hide the confirmation modal
                const confirmationModal = document.getElementById('reportConfirmationModal');
                if (confirmationModal) {
                    const bsModal = bootstrap.Modal.getInstance(confirmationModal);
                    if (bsModal) {
                        bsModal.hide();
                    }
                }

                // Re-enable the submit button
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit Final Report';

                // Show error message
                Swal.fire({
                    title: 'Error',
                    text: 'There was an error submitting the report: ' + error.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        });
        function openFinishedDetails(job) {
            try {
                console.log('Opening finished details for job:', job);

                // If job is a string (which can happen with JSON encoding/decoding issues), try to parse it
                if (typeof job === 'string') {
                    try {
                        job = JSON.parse(job);
                        console.log('Successfully parsed job string to object:', job);
                    } catch (parseError) {
                        console.error('Error parsing job string:', parseError);
                        Swal.fire({
                            title: 'Error',
                            text: 'There was an error loading the job details. Please try again.',
                            icon: 'error'
                        });
                        return;
                    }
                }

                // Handle attachments
                const attachments = job.attachments ? job.attachments.split(',') : [];
                const attachmentList = attachments.map(file =>
                    `<a href="../uploads/${file}" target="_blank" class="list-group-item list-group-item-action">
                        <i class="fas fa-paperclip me-2"></i>${file}
                    </a>`
                ).join('');

                // Create the content for the modal with null checks for all properties
                const content = `
                    <div class="row">
                        <div class="col-md-12">
                            <h4>INFORMATION OF THE CLIENT</h4>
                            <p><strong>Client Name:</strong> ${job.client_name || 'N/A'}</p>
                            <p><strong>Date:</strong> ${job.preferred_date || 'N/A'}</p>
                            <p><strong>Time:</strong> ${job.preferred_time || 'N/A'}</p>
                            <p><strong>Location:</strong> ${job.location_address || 'N/A'}</p>
                            <p><strong>Type of Place:</strong> ${job.kind_of_place || 'N/A'}</p>
                            <p><strong>Contact:</strong> ${job.contact_number || 'N/A'}</p>
                            <p><strong>Pest Problems:</strong> ${job.pest_problems || 'None specified'}</p>
                            <hr>
                            <h4>Assessment Report</h4>
                            <p><strong>Completion Time:</strong> ${job.end_time || 'N/A'}</p>
                            <p><strong>Area Treated:</strong> ${job.area ? job.area + ' m²' : 'N/A'}</p>
                            <p><strong>Pest Types Found:</strong> ${job.pest_types || 'None specified'}</p>
                            <p><strong>Problem Area:</strong> ${job.problem_area || 'None specified'}</p>
                            <p><strong>Report Date:</strong> ${job.report_date ? new Date(job.report_date).toLocaleDateString() : 'N/A'}</p>
                            <p><strong>Technician Notes:</strong></p>
                            <div class="border p-2 mb-3">${job.notes || 'No additional notes'}</div>
                            <p><strong>Recommendation:</strong></p>
                            <div class="border p-2 mb-3">${job.recommendation || 'No recommendations provided'}</div>

                            <p><strong>Chemical Recommendations:</strong></p>
                            <div class="border p-2 mb-3" id="chemicalRecommendationsDisplay">
                                ${typeof displayChemicalRecommendations === 'function' ?
                                  displayChemicalRecommendations(job.chemical_recommendations) :
                                  (job.chemical_recommendations || 'No chemical recommendations provided')}
                            </div>

                            <h5>Attachments:</h5>
                            <div class="list-group">${attachmentList.length > 0 ? attachmentList : 'No attachments'}</div>
                        </div>
                    </div>
                `;

                // Update the modal content
                const modalContent = document.getElementById('finishedModalContent');
                if (modalContent) {
                    modalContent.innerHTML = content;
                } else {
                    console.error('Finished modal content element not found');
                    return;
                }

                // Show the modal
                const finishedModal = document.getElementById('finishedDetailsModal');
                if (finishedModal) {
                    const modal = new bootstrap.Modal(finishedModal);
                    modal.show();
                } else {
                    console.error('Finished details modal element not found');
                }
            } catch (error) {
                console.error('Error in openFinishedDetails function:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'There was an error loading the completed inspection details. Please try again.',
                    icon: 'error'
                });
            }
        }
    </script>

    <!-- Chemical Recommendations Modal -->
    <div class="modal fade" id="chemicalRecommendationsModal" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl modal-zoom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-flask"></i> Chemical Recommendations</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span>Generate chemical recommendations based on the pest types you've identified. <strong>Only chemicals expiring within the next 10 days</strong> will be shown to minimize waste.</span>
                    </div>
                    <div class="form-group mb-3">
                        <label><i class="fas fa-spray-can"></i> Application Method:</label>
                        <select id="applicationMethod" class="form-control">
                            <option value="spray">Spray Application</option>
                            <option value="fogging">Fogging Application</option>
                            <option value="soil drench">Soil Drench (for Termites)</option>
                            <option value="bait">Bait Application</option>
                        </select>
                    </div>
                    <button id="generateRecommendationsBtn" class="btn btn-primary">
                        <i class="fas fa-sync-alt"></i> Generate Recommendations
                    </button>
                    <div id="recommendationsLoading" style="display: none; margin-top: 15px; text-align: center;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem;"></i>
                        <p>Generating recommendations...</p>
                    </div>
                    <div id="recommendationsResult" style="display: none; margin-top: 15px;">
                        <!-- Recommendations will be displayed here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add direct event listener to the generate recommendations button -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const generateBtn = document.getElementById('generateRecommendationsBtn');
            if (generateBtn) {
                console.log('Adding direct event listener to generate recommendations button');
                generateBtn.addEventListener('click', function() {
                    console.log('Generate recommendations button clicked directly');
                    if (typeof generateChemicalRecommendations === 'function') {
                        generateChemicalRecommendations();
                    } else {
                        console.error('generateChemicalRecommendations function not found');
                        alert('Error: Could not find the function to generate recommendations. Please refresh the page and try again.');
                    }
                });
            }
        });
    </script>

    <!-- Sidebar and Notification Scripts -->
    <script src="js/sidebar.js"></script>
    <script src="js/notifications.js"></script>
    <script src="js/tools-checklist.js"></script>
    <script src="js/chemical-recommendations.js"></script>
    <script>
        // Function to toggle the manual chemical input container
        function toggleManualChemicalInput() {
            const container = document.getElementById('manualChemicalInputContainer');
            if (container) {
                container.style.display = container.style.display === 'none' ? 'block' : 'none';
            }
        }

        // Function to apply the manual chemical input
        function applyManualChemicalInput() {
            const input = document.getElementById('manualChemicalInput');
            const hiddenInput = document.getElementById('selectedChemicals');
            const debugInfo = document.getElementById('chemicalDebugInfo');

            if (!input || !hiddenInput) {
                console.error('Manual chemical input or hidden input not found');
                return;
            }

            try {
                // Validate the JSON
                const chemicals = JSON.parse(input.value);

                if (!Array.isArray(chemicals)) {
                    throw new Error('Input must be an array of chemicals');
                }

                // Get the area value to calculate the correct dosage if needed
                const areaInput = document.querySelector('input[name="area"]');
                const area = areaInput ? parseFloat(areaInput.value) : 200; // Default to 200 if not found

                // Process each chemical to ensure dosage is calculated correctly
                chemicals.forEach(chemical => {
                    // If dosage is missing or set to a default value like "20", calculate it based on area
                    if (!chemical.dosage || chemical.dosage === "20") {
                        // Calculate dosage based on chemical type and area
                        let dilutionRate = 20; // Default dilution rate (ml per liter)

                        // Adjust dilution rate based on chemical name
                        if (chemical.name.toLowerCase().includes('fipronil')) {
                            dilutionRate = 12; // 12ml per liter for Fipronil
                        } else if (chemical.name.toLowerCase().includes('cypermethrin')) {
                            dilutionRate = 20; // 20ml per liter for Cypermethrin
                        }

                        const areaCoverage = 100; // sqm per liter (default)
                        const solutionAmount = (area / areaCoverage).toFixed(2); // liters
                        chemical.dosage = (dilutionRate * solutionAmount).toFixed(2); // ml
                        console.log(`Calculated dosage for ${chemical.name}: ${chemical.dosage}ml for ${area}sqm`);
                    }
                });

                // Update the input value with the processed chemicals
                input.value = JSON.stringify(chemicals);

                // Update the hidden input
                hiddenInput.value = input.value;

                // Update the global variable if it exists
                if (typeof selectedChemicals !== 'undefined') {
                    selectedChemicals = chemicals;
                }

                // Update session storage
                sessionStorage.setItem('selectedChemicals', input.value);

                // Update debug info
                if (debugInfo) {
                    debugInfo.style.display = 'block';
                    debugInfo.textContent = `${chemicals.length} chemical(s) manually added. Data size: ${input.value.length} characters.`;
                }

                // Update the display
                if (typeof displaySelectedChemicals === 'function') {
                    displaySelectedChemicals();
                } else {
                    // Fallback if the function is not available
                    const container = document.getElementById('selectedChemicalsContainer');
                    if (container) {
                        container.innerHTML = `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <span>${chemicals.length} chemical(s) manually added.</span>
                            </div>
                        `;
                    }
                }

                // Show success message
                Swal.fire({
                    title: 'Chemicals Added',
                    text: `${chemicals.length} chemical(s) have been manually added to your report.`,
                    icon: 'success',
                    confirmButtonText: 'OK',
                    timer: 2000,
                    timerProgressBar: true
                });

                // Hide the manual input container
                toggleManualChemicalInput();

            } catch (e) {
                console.error('Error parsing manual chemical input:', e);

                // Show error message
                Swal.fire({
                    title: 'Invalid Input',
                    text: `Error: ${e.message}. Please check your JSON format.`,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        }

        // Initialize notifications when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Fetch notifications
            fetchNotifications();

            // Set up date checking and auto-refresh
            setupDateRefresh();

            // Add event listener to the cancel button
            const cancelBtn = document.getElementById('cancelReportBtn');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function() {
                    console.log('Cancel button clicked, resetting selected chemicals');
                    resetSelectedChemicals();
                });
            }

            // Make the manual chemical input functions available globally
            window.toggleManualChemicalInput = toggleManualChemicalInput;
            window.applyManualChemicalInput = applyManualChemicalInput;
        });

        // Function to set up date checking and auto-refresh
        function setupDateRefresh() {
            // Store the server date
            const serverDate = '<?= $today ?>';
            console.log('Server date:', serverDate);

            // Check date every minute
            setInterval(function() {
                checkDateAndRefresh(serverDate);
            }, 60000); // 60 seconds

            // Set up midnight refresh
            setupMidnightRefresh();
        }

        // Function to check if the date has changed and refresh if needed
        function checkDateAndRefresh(serverDate) {
            // Get current client date in YYYY-MM-DD format
            const clientDate = new Date().toISOString().split('T')[0];
            console.log('Checking date - Client:', clientDate, 'Server:', serverDate);

            // If the client date is different from the server date, refresh the page
            if (clientDate !== serverDate) {
                console.log('Date changed! Refreshing page...');
                // Force a full page reload to bypass cache
                window.location.href = window.location.pathname + '?refresh=' + new Date().getTime();
                return;
            }

            // Check if any upcoming inspections have today's date
            checkUpcomingInspectionsForToday(clientDate);
        }

        // Function to check if any upcoming inspections have today's date
        function checkUpcomingInspectionsForToday(todayDate) {
            // Get all upcoming inspection cards
            const upcomingCards = document.querySelectorAll('.upcoming-inspections .job-card');
            let needsRefresh = false;

            // Loop through each card and check the date
            upcomingCards.forEach(card => {
                // Find the date element (it's a p.text-muted that contains the date)
                const dateElement = card.querySelector('p.text-muted:first-of-type');
                if (dateElement) {
                    // Extract the date from the element (format is YYYY-MM-DD)
                    const dateText = dateElement.textContent.trim();

                    console.log('Checking upcoming inspection date:', dateText, 'against today:', todayDate);

                    // If the date matches today's date, we need to refresh
                    if (dateText === todayDate) {
                        console.log('Found an inspection that should be moved to today!');
                        needsRefresh = true;
                    }
                }
            });

            // If we found an inspection that needs to be moved, refresh the page
            if (needsRefresh) {
                console.log('Refreshing page to update inspections...');
                window.location.href = window.location.pathname + '?refresh=' + new Date().getTime();
            }
        }

        // Function to refresh the page at midnight
        function setupMidnightRefresh() {
            const now = new Date();
            const tomorrow = new Date(now);
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(0, 0, 10, 0); // 00:00:10 - slight delay to ensure we're past midnight

            const msUntilMidnight = tomorrow - now;
            console.log('Setting up midnight refresh in', Math.floor(msUntilMidnight/1000/60), 'minutes');

            // Set timeout to refresh at midnight
            setTimeout(function() {
                console.log('Midnight reached! Refreshing page...');
                // Force a full page reload to bypass cache
                window.location.href = window.location.pathname + '?refresh=' + new Date().getTime();
            }, msUntilMidnight);
        }
    </script>
</body>
</html>