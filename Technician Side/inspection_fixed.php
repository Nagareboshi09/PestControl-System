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

        /* Add loading overlay styles */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            color: white;
        }

        .loading-spinner {
            width: 80px;
            height: 80px;
            border: 8px solid #f3f3f3;
            border-top: 8px solid #3498db;
            border-radius: 50%;
            animation: spin 2s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner"></div>
        <h3>Submitting Report...</h3>
        <p>Please wait while your report is being processed.</p>
        <p>This may take a few moments.</p>
    </div>

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
                                    <div class="form-check">
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
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="type_of_work[]" value="Disinfection" id="workDisinfection">
                                        <label class="form-check-label" for="workDisinfection">Disinfection</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="type_of_work[]" value="Weed Control" id="workWeed">
                                        <label class="form-check-label" for="workWeed">Weed Control</label>
                                    </div>
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
                                    <textarea class="form-control" id="manualChemicalInput" rows="3" placeholder='[{"id":"1","name":"Imidaclopred","type":"Insecticide","dosage":"20","dosage_unit":"ml","target_pest":"Crawling & Flying Pest"}]'></textarea>
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
                        <span>Generate chemical recommendations based on the pest types you've identified. Chemicals are prioritized based on expiration date to minimize waste.</span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <!-- Sidebar Fix Script -->
    <script src="js/sidebar-fix.js"></script>
