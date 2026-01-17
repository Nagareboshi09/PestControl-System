<?php
session_start();
require_once '../db_connect.php';
require_once '../notification_functions.php';

$client_id = $_SESSION['client_id'];

// Default sorting
$orderBy = "ORDER BY a.preferred_date DESC";

// Fetch all appointments for this client (excluding declined ones)
$stmt = $conn->prepare("
    SELECT
        a.*,
        t.username as technician_name,
        t.tech_contact_number as technician_contact,
        t.tech_fname as technician_fname,
        t.tech_lname as technician_lname,
        t.technician_picture,
        ar.end_time,
        ar.area,
        ar.notes as report_notes,
        ar.attachments,
        ar.created_at as report_date,
        ar.report_id
    FROM appointments a
    LEFT JOIN technicians t ON a.technician_id = t.technician_id
    LEFT JOIN assessment_report ar ON a.appointment_id = ar.appointment_id
    WHERE a.client_id = ? AND a.status != 'declined'
    $orderBy
");

$stmt->bind_param("i", $client_id);
$stmt->execute();
$allAppointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Separate appointments into pending and completed
$pendingAppointments = [];
$completedAppointments = [];

foreach ($allAppointments as $appointment) {
    if (empty($appointment['report_id'])) {
        $pendingAppointments[] = $appointment;
    } else {
        $completedAppointments[] = $appointment;
    }
}

// All appointments (used for existing functionality)
$appointments = $allAppointments;

// Check for newly scheduled technician (from URL parameter)
$newlyAssigned = isset($_GET['newly_assigned']) ? $_GET['newly_assigned'] : null;
$appointmentId = isset($_GET['appointment_id']) ? $_GET['appointment_id'] : null;

// Find the newly scheduled appointment in the list
$newlyAssignedAppointment = null;
if ($newlyAssigned && $appointmentId) {
    foreach ($appointments as $appointment) {
        if ($appointment['appointment_id'] == $appointmentId) {
            $newlyAssignedAppointment = $appointment;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspection Report | MacJ Pest Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/sidebar-fix.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/client-common.css">
    <!-- Removed unnecessary CSS files -->
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="css/floating-action-button.css">
    <!-- Leaflet.js for OpenStreetMap -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
    <style>
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

        /* Custom styles specific to inspection report page */

        .technician-modal-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .technician-modal-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .technician-modal-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1.5rem;
            border: 3px solid var(--primary-color);
        }

        .clickable-avatar {
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }

        .clickable-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }

        .clickable-avatar::after {
            content: '\f00e';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            bottom: 0;
            right: 0;
            background-color: var(--primary-color);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .clickable-avatar:hover::after {
            opacity: 1;
        }

        /* Full-size image viewer styles */
        #imageViewerModal .modal-body {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #000;
            min-height: 300px;
        }

        #fullSizeImage {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
        }

        #fullSizeImage.profile-picture-view {
            max-height: 400px;
            max-width: 400px;
            border-radius: 5px;
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.2);
        }

        .report-details {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .report-attachments {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 1rem;
        }

        .report-attachment-container {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 5px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .report-attachment {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .attachment-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .attachment-overlay i {
            color: white;
            font-size: 1.5rem;
        }

        .report-attachment-container:hover {
            transform: scale(1.05);
        }

        .report-attachment-container:hover .attachment-overlay {
            opacity: 1;
        }

        /* Map Styles */
        .location-map-container {
            height: 200px;
            width: 100%;
            margin-top: 1rem;
            margin-bottom: 1rem;
            border-radius: var(--border-radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            position: relative;
            border: 1px solid #ddd;
        }

        /* Ensure maps in modals display properly */
        .modal .map {
            height: 200px !important;
            width: 100% !important;
            z-index: 1; /* Lower than modal z-index */
        }

        /* Fix for Leaflet controls in Bootstrap modals */
        .modal .leaflet-control-container .leaflet-top,
        .modal .leaflet-control-container .leaflet-bottom {
            z-index: 1000; /* Lower than modal z-index but higher than map */
        }

        .no-reports-message {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .star-rating {
            direction: rtl;
            display: inline-block;
        }
        .star-rating input[type="radio"] {
            display: none;
        }
        .star-rating label {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        .star-rating input[type="radio"]:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #ffc107;
        }

        /* Tab Styles */
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }

        .nav-tabs .nav-link {
            margin-bottom: -2px;
            border: none;
            color: #495057;
            font-weight: 500;
            padding: 0.75rem 1.25rem;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            border: none;
            color: var(--primary-color);
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: transparent;
            border: none;
            border-bottom: 3px solid var(--primary-color);
        }

        .nav-tabs .nav-link i {
            margin-right: 5px;
        }

        /* Badge styles */
        .nav-tabs .badge {
            font-size: 0.7rem;
            font-weight: 500;
            padding: 0.25rem 0.5rem;
        }

        /* Tab content transition */
        .tab-pane {
            transition: all 0.3s ease;
        }

        /* Pending tab specific styles */
        #pending-tab .badge {
            background-color: var(--primary-color);
        }

        /* Completed tab specific styles */
        #completed-tab .badge {
            background-color: var(--success-color);
        }

        /* Responsive tab adjustments */
        @media (max-width: 576px) {
            .nav-tabs .nav-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body class="inspection_report">
    <!-- Header -->
    <header class="header">
        <div class="header-title">
            <!-- Client Portal text removed -->
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
                    <div class="user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? '') ?></div>
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
            <a href="inspection_report.php" class="active">
                <i class="fas fa-clipboard-check fa-icon"></i>
                Inspection Report
            </a>
            <a href="contract.php">
                <i class="fas fa-file-contract fa-icon"></i>
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-clipboard-check"></i> Inspection Reports</h1>
                    <h3><i class="fas fa-clipboard-check"></i> Note: You have free inspection</h3>
                    <p>View all your scheduled appointments and inspection reports</p>
                </div>

            </div>
        </div>

        <div class="container mb-5">
            <?php if (empty($appointments)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-clipboard fa-4x mb-3 text-muted"></i>
                        <h3>No Appointments Found</h3>
                        <p class="text-muted">You don't have any scheduled appointments yet.</p>
                        <a href="schedule.php" class="btn btn-primary mt-3">
                            <i class="fas fa-calendar-plus"></i> Schedule an Appointment
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-4" id="inspectionTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-inspections"
                                type="button" role="tab" aria-controls="pending-inspections" aria-selected="true">
                            <i class="fas fa-clock"></i> Pending Inspections
                            <?php if (count($pendingAppointments) > 0): ?>
                                <span class="badge bg-primary ms-2"><?= count($pendingAppointments) ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed-inspections"
                                type="button" role="tab" aria-controls="completed-inspections" aria-selected="false">
                            <i class="fas fa-check-circle"></i> Completed Inspections
                            <?php if (count($completedAppointments) > 0): ?>
                                <span class="badge bg-success ms-2"><?= count($completedAppointments) ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                </ul>

                <!-- Tabs Content -->
                <div class="tab-content" id="inspectionTabsContent">
                    <!-- Pending Inspections Tab -->
                    <div class="tab-pane fade show active" id="pending-inspections" role="tabpanel" aria-labelledby="pending-tab">
                        <?php if (empty($pendingAppointments)): ?>
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-clipboard fa-4x mb-3 text-muted"></i>
                                    <h3>No Pending Inspections</h3>
                                    <p class="text-muted">You don't have any pending inspection appointments.</p>
                                    <a href="schedule.php" class="btn btn-primary mt-3">
                                        <i class="fas fa-calendar-plus"></i> Schedule an Appointment
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($pendingAppointments as $appointment): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="enhanced-card">
                                            <div class="enhanced-card-header">
                                                <h5 class="mb-0"><?= date('M d, Y', strtotime($appointment['preferred_date'])) ?></h5>
                                                <?php if (!empty($appointment['technician_id'])): ?>
                                                    <span class="status-badge status-assigned">Scheduled</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-pending">Pending</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="enhanced-card-body">
                                                <p class="text-muted mb-3"><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($appointment['preferred_time'])) ?></p>
                                                <p><strong><i class="fas fa-map-marker-alt"></i> Location:</strong> <?= htmlspecialchars(preg_replace('/\s*\[[-\d.,]+\]$/', '', $appointment['location_address'])) ?></p>
                                                <div class="location-map-container">
                                                    <div id="map-<?= $appointment['appointment_id'] ?>" class="map" style="width: 100%; height: 200px;"
                                                         data-address="<?= htmlspecialchars(preg_replace('/\s*\[[-\d.,]+\]$/', '', $appointment['location_address'])) ?>">
                                                    </div>
                                                </div>
                                                <script>
                                                    document.addEventListener('DOMContentLoaded', function() {
                                                        initAppointmentMap('map-<?= $appointment['appointment_id'] ?>',
                                                            '<?= addslashes(preg_replace('/\s*\[[-\d.,]+\]$/', '', $appointment['location_address'])) ?>');
                                                    });
                                                </script>
                                                <p><strong><i class="fas fa-building"></i> Type:</strong> <?= htmlspecialchars($appointment['kind_of_place']) ?></p>

                                                <?php if (!empty($appointment['technician_id'])): ?>
                                                    <div class="technician-info">
                                                        <img src="<?= !empty($appointment['technician_picture']) ? '../Admin Side/' . $appointment['technician_picture'] : '../Admin Side/uploads/technicians/default.png' ?>"
                                                             alt="Technician" class="technician-avatar clickable-avatar"
                                                             onclick="openImageViewer('<?= !empty($appointment['technician_picture']) ? '../Admin Side/' . $appointment['technician_picture'] : '../Admin Side/uploads/technicians/default.png' ?>')"
                                                             title="Click to view larger image">
                                                        <div class="technician-details">
                                                            <h6 class="mb-0"><?= !empty($appointment['technician_fname']) && !empty($appointment['technician_lname']) ? htmlspecialchars($appointment['technician_fname'] . ' ' . $appointment['technician_lname']) : htmlspecialchars($appointment['technician_name']) ?></h6>
                                                            <small class="text-muted">Scheduled Technician</small>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <button class="btn btn-primary w-100 mt-3"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#reportModal"
                                                        data-appointment-id="<?= $appointment['appointment_id'] ?>">
                                                    <i class="fas fa-eye"></i> View Inspection Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Completed Inspections Tab -->
                    <div class="tab-pane fade" id="completed-inspections" role="tabpanel" aria-labelledby="completed-tab">
                        <?php if (empty($completedAppointments)): ?>
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-clipboard-check fa-4x mb-3 text-muted"></i>
                                    <h3>No Completed Inspections</h3>
                                    <p class="text-muted">You don't have any completed inspection reports yet.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($completedAppointments as $appointment): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="enhanced-card">
                                            <div class="enhanced-card-header">
                                                <h5 class="mb-0"><?= date('M d, Y', strtotime($appointment['preferred_date'])) ?></h5>
                                                <span class="status-badge status-completed">Completed</span>
                                            </div>
                                            <div class="enhanced-card-body">
                                                <p class="text-muted mb-3"><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($appointment['preferred_time'])) ?></p>
                                                <p><strong><i class="fas fa-map-marker-alt"></i> Location:</strong> <?= htmlspecialchars(preg_replace('/\s*\[[-\d.,]+\]$/', '', $appointment['location_address'])) ?></p>
                                                <div class="location-map-container">
                                                    <div id="map-completed-<?= $appointment['appointment_id'] ?>" class="map" style="width: 100%; height: 200px;"
                                                         data-address="<?= htmlspecialchars(preg_replace('/\s*\[[-\d.,]+\]$/', '', $appointment['location_address'])) ?>">
                                                    </div>
                                                </div>
                                                <script>
                                                    document.addEventListener('DOMContentLoaded', function() {
                                                        initAppointmentMap('map-completed-<?= $appointment['appointment_id'] ?>',
                                                            '<?= addslashes(preg_replace('/\s*\[[-\d.,]+\]$/', '', $appointment['location_address'])) ?>');
                                                    });
                                                </script>
                                                <p><strong><i class="fas fa-building"></i> Type:</strong> <?= htmlspecialchars($appointment['kind_of_place']) ?></p>

                                                <?php if (!empty($appointment['technician_id'])): ?>
                                                    <div class="technician-info">
                                                        <img src="<?= !empty($appointment['technician_picture']) ? '../Admin Side/' . $appointment['technician_picture'] : '../Admin Side/uploads/technicians/default.png' ?>"
                                                             alt="Technician" class="technician-avatar clickable-avatar"
                                                             onclick="openImageViewer('<?= !empty($appointment['technician_picture']) ? '../Admin Side/' . $appointment['technician_picture'] : '../Admin Side/uploads/technicians/default.png' ?>')"
                                                             title="Click to view larger image">
                                                        <div class="technician-details">
                                                            <h6 class="mb-0"><?= !empty($appointment['technician_fname']) && !empty($appointment['technician_lname']) ? htmlspecialchars($appointment['technician_fname'] . ' ' . $appointment['technician_lname']) : htmlspecialchars($appointment['technician_name']) ?></h6>
                                                            <small class="text-muted">Assigned Technician</small>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <button class="btn btn-success w-100 mt-3"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#reportModal"
                                                        data-appointment-id="<?= $appointment['appointment_id'] ?>">
                                                    <i class="fas fa-file-alt"></i> View Inspection Report
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/inspection_report_procedure.php'; ?>

    <!-- Report Details Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Inspection Report Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="reportModalContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading inspection report details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Technician Assignment Notification Modal -->
    <div class="modal fade" id="technicianAssignedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Technician Scheduled!</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="technicianAssignedContent">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-check fa-4x text-success mb-3"></i>
                        <h4>A technician has been scheduled for your appointment!</h4>
                    </div>
                    <div id="assignedTechnicianInfo"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Full-size Image Viewer Modal -->
    <div class="modal fade" id="imageViewerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Image Viewer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 text-center">
                    <img id="fullSizeImage" src="" alt="Full-size Image" style="max-width: 100%; max-height: 80vh;">
                </div>
                <div class="modal-footer">
                    <a id="downloadImageLink" href="#" download class="btn btn-primary">
                        <i class="fas fa-download"></i> Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>




    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/sidebar.js"></script>
    <!-- Fixed sidebar script -->
    <script src="js/sidebar-fix.js"></script>
    <script src="js/inspection_report.js"></script>
    <!-- Add notifications script -->
    <script src="js/notifications.js"></script>
    <!-- Floating Action Button Script -->
    <script src="js/floating-action-button.js"></script>

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
        // Function to initialize maps for appointments
        function initAppointmentMap(mapId, address) {
            console.log('Initializing map:', mapId, 'with address:', address);

            // Get the map element
            const mapElement = document.getElementById(mapId);
            if (!mapElement) {
                console.error('Map element not found:', mapId);
                return;
            }

            // Make sure the map container is visible
            mapElement.style.display = 'block';

            try {
                // Create map centered on a default location (Manila, Philippines)
                const map = L.map(mapId).setView([14.5995, 120.9842], 13);

                // Store map reference in window.L.maps object for later access
                if (!window.L.maps) {
                    window.L.maps = {};
                }
                window.L.maps[mapId] = map;

                // Add OpenStreetMap tile layer
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                // Search for the address and add a marker
                if (address) {
                    // Use Nominatim for geocoding
                    $.ajax({
                        url: `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`,
                        type: 'GET',
                        dataType: 'json',
                        success: function(data) {
                            if (data && data.length > 0) {
                                const result = data[0];
                                const latlng = L.latLng(result.lat, result.lon);

                                // Add marker and center map
                                L.marker(latlng).addTo(map);
                                map.setView(latlng, 15);

                                // Force a resize after setting the view
                                map.invalidateSize();
                            } else {
                                console.warn('No results found for address:', address);
                                // Add a default marker at Manila if no results
                                const defaultLatlng = L.latLng(14.5995, 120.9842);
                                L.marker(defaultLatlng).addTo(map);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error geocoding address:', error);
                            // Add a default marker at Manila if geocoding fails
                            const defaultLatlng = L.latLng(14.5995, 120.9842);
                            L.marker(defaultLatlng).addTo(map);
                        }
                    });
                }

                // Refresh map size after a short delay (helps with rendering in hidden elements)
                setTimeout(function() {
                    map.invalidateSize();
                    console.log('Map size refreshed for:', mapId);
                }, 1000);

                // Also refresh when the modal is shown (for maps in modals)
                $('#reportModal').on('shown.bs.modal', function() {
                    setTimeout(function() {
                        map.invalidateSize();
                        console.log('Map size refreshed after modal shown for:', mapId);
                    }, 500);
                });

                // Also refresh when the technician assigned modal is shown
                $('#technicianAssignedModal').on('shown.bs.modal', function() {
                    setTimeout(function() {
                        map.invalidateSize();
                        console.log('Map size refreshed after technician modal shown for:', mapId);
                    }, 500);
                });

                // Also refresh when tabs are switched
                $('#inspectionTabs .nav-link').on('click', function() {
                    setTimeout(function() {
                        map.invalidateSize();
                        console.log('Map size refreshed after tab switch for:', mapId);
                    }, 500);
                });

                console.log('Map initialized successfully for:', mapId);
            } catch (error) {
                console.error('Error initializing map:', error);
            }
        }
    </script>

    <?php if ($newlyAssignedAppointment): ?>
    <script>
        // Show the technician scheduled modal when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            const appointmentData = <?= json_encode($newlyAssignedAppointment) ?>;
            showTechnicianAssignedModal(appointmentData);
        });
    </script>
    <?php endif; ?>
    <script>
        // Add sidebar-active class to body when sidebar is active
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Inspection report page loaded');

            // Add debug code to check if sidebar elements exist
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');

            if (!sidebar) {
                console.error('Sidebar element not found!');
            } else {
                console.log('Sidebar element found:', sidebar);
            }

            if (!menuToggle) {
                console.error('Menu toggle element not found!');
            } else {
                console.log('Menu toggle element found:', menuToggle);

                // Add a direct click handler for debugging
                menuToggle.addEventListener('click', function() {
                    console.log('Menu toggle clicked directly in inspection_report.php');
                });
            }

            // Initialize tab functionality
            initTabFunctionality();
        });

        // Initialize tab functionality
        function initTabFunctionality() {
            // Get all tab elements
            const tabElements = document.querySelectorAll('#inspectionTabs .nav-link');

            // Add click event listeners to tabs
            tabElements.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    // Prevent default behavior
                    e.preventDefault();

                    // Get the target tab content
                    const targetId = this.getAttribute('data-bs-target');
                    const targetTab = document.querySelector(targetId);

                    // Remove active class from all tabs and tab contents
                    tabElements.forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-pane').forEach(p => {
                        p.classList.remove('show', 'active');
                    });

                    // Add active class to clicked tab and its content
                    this.classList.add('active');
                    targetTab.classList.add('show', 'active');

                    // Refresh maps in the newly activated tab
                    setTimeout(() => {
                        const maps = targetTab.querySelectorAll('.map');
                        maps.forEach(map => {
                            const mapId = map.id;
                            const address = map.getAttribute('data-address');
                            if (mapId && address) {
                                console.log(`Refreshing map ${mapId} in tab ${targetId}`);
                                // Force map to recalculate its size
                                if (window.L && window.L.maps && window.L.maps[mapId]) {
                                    window.L.maps[mapId].invalidateSize();
                                }
                            }
                        });
                    }, 100);
                });
            });
        }
    </script>
    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <script>
        // Ensure notification dropdown works
        $(document).ready(function() {
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
        });
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
