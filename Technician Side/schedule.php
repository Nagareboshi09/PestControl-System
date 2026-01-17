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

// Default sort order is time ascending
$sort_order = 'time_asc';

// Clear any output buffering and set no-cache headers
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Fetch appointments assigned to this technician
$appointmentsQuery = "
    SELECT
        'appointment' as schedule_type,
        a.appointment_id as id,
        a.client_name,
        a.kind_of_place,
        a.location_address,
        a.preferred_date,
        a.preferred_time,
        a.status,
        a.pest_problems,
        a.notes,
        c.contact_number
    FROM appointments a
    LEFT JOIN clients c ON a.client_id = c.client_id
    WHERE a.technician_id = ? AND a.status != 'completed'
    ORDER BY a.preferred_date ASC, a.preferred_time ASC
";

$stmt = $conn->prepare($appointmentsQuery);
$stmt->bind_param("i", $technician_id);
$stmt->execute();
$appointmentsResult = $stmt->get_result();
$appointments = [];

while ($row = $appointmentsResult->fetch_assoc()) {
    $appointments[] = $row;
}

// Fetch job orders assigned to this technician - only show approved ones
$jobOrdersQuery = "
    SELECT
        'job_order' as schedule_type,
        jo.job_order_id as id,
        jo.type_of_work,
        jo.preferred_date,
        jo.preferred_time,
        a.client_name,
        a.location_address,
        a.kind_of_place,
        a.notes,
        c.contact_number,
        'scheduled' as status
    FROM job_order jo
    JOIN job_order_technicians jot ON jo.job_order_id = jot.job_order_id
    JOIN assessment_report ar ON jo.report_id = ar.report_id
    JOIN appointments a ON ar.appointment_id = a.appointment_id
    LEFT JOIN clients c ON a.client_id = c.client_id
    WHERE jot.technician_id = ? AND jo.client_approval_status IN ('approved', 'one-time')
    ORDER BY jo.preferred_date ASC, jo.preferred_time ASC
";

$stmt = $conn->prepare($jobOrdersQuery);
$stmt->bind_param("i", $technician_id);
$stmt->execute();
$jobOrdersResult = $stmt->get_result();
$jobOrders = [];

while ($row = $jobOrdersResult->fetch_assoc()) {
    $jobOrders[] = $row;
}

// Combine all schedules
$allSchedules = array_merge($appointments, $jobOrders);

// Define default sorting function
$sortByTimeAsc = function($a, $b) {
    $dateCompare = strtotime($a['preferred_date']) - strtotime($b['preferred_date']);
    if ($dateCompare === 0) {
        return strtotime($a['preferred_time']) - strtotime($b['preferred_time']);
    }
    return $dateCompare;
};

// Apply sorting by time (earliest first)
usort($allSchedules, $sortByTimeAsc);

// Separate into today's and upcoming schedules
$todaySchedules = [];
$upcomingSchedules = [];

foreach ($allSchedules as $schedule) {
    // Direct string comparison for dates in YYYY-MM-DD format
    // This is the simplest and most reliable method for this specific format
    if ($schedule['preferred_date'] === $today) {
        $todaySchedules[] = $schedule;
        // Debug output
        error_log("Added to TODAY SCHEDULES: Client {$schedule['client_name']}, Date: {$schedule['preferred_date']}, Today: {$today}");
    } elseif ($schedule['preferred_date'] > $today) {
        $upcomingSchedules[] = $schedule;
        // Debug output
        error_log("Added to UPCOMING SCHEDULES: Client {$schedule['client_name']}, Date: {$schedule['preferred_date']}, Today: {$today}");
    } else {
        // Debug output for past dates
        error_log("PAST DATE SCHEDULE: Client {$schedule['client_name']}, Date: {$schedule['preferred_date']}, Today: {$today}");
    }
}
?>
<!-- Debug information has been removed for production -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Schedule - MacJ Pest Control</title>
    <link href="https://unpkg.com/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
    <style>
        /* Additional styles for schedule page */
        .schedule-card {
            transition: all 0.3s ease;
            height: 100%;
            border-left: 4px solid transparent;
        }

        .schedule-card.appointment {
            border-left-color: var(--primary-color);
        }

        .schedule-card.job_order {
            border-left-color: var(--success-color);
        }

        .schedule-card.clickable {
            cursor: pointer;
        }

        .schedule-card.clickable:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .schedule-card.non-clickable {
            opacity: 0.8;
            background-color: #f8f9fa;
        }

        .schedule-type-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 0.7rem;
            padding: 3px 8px;
        }

        .schedule-time {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .schedule-date {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .schedule-location {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .schedule-details {
            margin-top: 10px;
        }

        .schedule-details span {
            display: inline-block;
            margin-right: 10px;
            font-size: 0.8rem;
            background-color: #f0f0f0;
            padding: 2px 8px;
            border-radius: 12px;
        }

        .today-indicator {
            background-color: #ffc107;
            color: #212529;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: 10px;
        }

        .no-schedules {
            padding: 30px;
            text-align: center;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .no-schedules i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 15px;
        }

        .schedule-section-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .schedule-section-header h3 {
            margin-bottom: 0;
        }

        /* Hide the scheduled for badge */
        .scheduled-date {
            display: none !important;
        }

        .schedule-count {
            margin-left: 10px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
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
            <a href="schedule.php" class="active">
                <i class="fas fa-calendar-alt fa-icon"></i>
                My Schedule
            </a>
            <a href="inspection.php">
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
            <h1><i class="fas fa-calendar-alt"></i> My Schedule</h1>
            <p>View and manage your appointments and job orders</p>
        </div>



        <!-- Today's Schedules -->
        <div class="job-section">
            <div class="schedule-section-header">
                <h3><i class="fas fa-calendar-day"></i> Today's Schedule</h3>
                <span class="schedule-count"><?= count($todaySchedules) ?></span>
                <span class="today-indicator"><i class="fas fa-star"></i> Today</span>
            </div>

            <?php if (empty($todaySchedules)): ?>
                <div class="no-schedules">
                    <i class="fas fa-calendar-times"></i>
                    <h4>No schedules for today</h4>
                    <p>You don't have any appointments or job orders scheduled for today.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($todaySchedules as $schedule): ?>
                        <div class="col-md-4 col-sm-6 mb-4">
                            <div class="card schedule-card <?= $schedule['schedule_type'] ?> clickable"
                                 onclick="openScheduleDetails(<?= htmlspecialchars(json_encode($schedule)) ?>)">
                                <div class="card-body">
                                    <span class="badge schedule-type-badge <?= $schedule['schedule_type'] === 'appointment' ? 'bg-primary' : 'bg-success' ?>">
                                        <?= $schedule['schedule_type'] === 'appointment' ? 'Inspection' : 'Job Order' ?>
                                    </span>
                                    <h5 class="card-title"><?= htmlspecialchars($schedule['client_name']) ?></h5>
                                    <div class="schedule-time">
                                        <?= date('h:i A', strtotime($schedule['preferred_time'])) ?>
                                    </div>
                                    <div class="schedule-location">
                                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($schedule['location_address']) ?>
                                    </div>
                                    <div class="schedule-details">
                                        <span><?= htmlspecialchars($schedule['kind_of_place']) ?></span>
                                        <?php if ($schedule['schedule_type'] === 'job_order'): ?>
                                            <span><?= htmlspecialchars($schedule['type_of_work']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Upcoming Schedules -->
        <div class="job-section upcoming-schedules">
            <div class="schedule-section-header">
                <h3><i class="fas fa-calendar-alt"></i> Upcoming Schedule</h3>
                <span class="schedule-count"><?= count($upcomingSchedules) ?></span>
            </div>

            <?php if (empty($upcomingSchedules)): ?>
                <div class="no-schedules">
                    <i class="fas fa-calendar-times"></i>
                    <h4>No upcoming schedules</h4>
                    <p>You don't have any upcoming appointments or job orders scheduled.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($upcomingSchedules as $schedule): ?>
                        <div class="col-md-4 col-sm-6 mb-4">
                            <div class="card schedule-card <?= $schedule['schedule_type'] ?> non-clickable">
                                <div class="card-body">
                                    <span class="badge schedule-type-badge <?= $schedule['schedule_type'] === 'appointment' ? 'bg-primary' : 'bg-success' ?>">
                                        <?= $schedule['schedule_type'] === 'appointment' ? 'Inspection' : 'Job Order' ?>
                                    </span>
                                    <h5 class="card-title"><?= htmlspecialchars($schedule['client_name']) ?></h5>
                                    <div class="schedule-date">
                                        <i class="fas fa-calendar"></i> <?= date('D, M d, Y', strtotime($schedule['preferred_date'])) ?>
                                    </div>
                                    <div class="schedule-time">
                                        <i class="fas fa-clock"></i> <?= date('h:i A', strtotime($schedule['preferred_time'])) ?>
                                    </div>
                                    <div class="schedule-location">
                                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($schedule['location_address']) ?>
                                    </div>
                                    <div class="schedule-details">
                                        <span><?= htmlspecialchars($schedule['kind_of_place']) ?></span>
                                        <?php if ($schedule['schedule_type'] === 'job_order'): ?>
                                            <span><?= htmlspecialchars($schedule['type_of_work']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Schedule Details Modal -->
    <div class="modal fade" id="scheduleDetailsModal">
        <div class="modal-dialog modal-lg modal-zoom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scheduleModalTitle">Schedule Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="scheduleModalContent">
                    <!-- Dynamic content loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="actionButton" style="display: none;">
                        <i class="fas fa-paper-plane"></i> <span id="actionButtonText">Action</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let currentSchedule = null;

        function openScheduleDetails(schedule) {
            currentSchedule = schedule;

            // Set modal title based on schedule type
            const titleElement = document.getElementById('scheduleModalTitle');
            if (schedule.schedule_type === 'appointment') {
                titleElement.innerHTML = '<i class="fas fa-clipboard-list"></i> Inspection Details';
            } else {
                titleElement.innerHTML = '<i class="fas fa-tasks"></i> Job Order Details';
            }

            // Generate content based on schedule type
            let content = '';

            if (schedule.schedule_type === 'appointment') {
                content = `
                    <h5>CLIENT INFORMATION</h5>
                    <p><strong>Client Name:</strong> ${schedule.client_name}</p>
                    <p><strong>Date:</strong> ${schedule.preferred_date}</p>
                    <p><strong>Time:</strong> ${formatTime(schedule.preferred_time)}</p>
                    <p><strong>Location:</strong> ${schedule.location_address}</p>
                    <p><strong>Type of Place:</strong> ${schedule.kind_of_place}</p>
                    <p><strong>Contact:</strong> ${schedule.contact_number || 'N/A'}</p>
                    <p><strong>Pest Problems:</strong> ${schedule.pest_problems || 'None specified'}</p>
                    <p><strong>Notes:</strong> ${schedule.notes || 'N/A'}</p>
                `;

                // Show action button for appointments
                document.getElementById('actionButton').style.display = 'block';
                document.getElementById('actionButtonText').textContent = 'Send Report';
                document.getElementById('actionButton').onclick = function() {
                    openReportForm(schedule.id);
                };
            } else {
                // Get required equipment based on type of work
                const equipmentHTML = getEquipmentHTML(schedule.type_of_work);

                content = `
                    <div class="modal-container">
                        <!-- Header Section -->
                        <div class="modal-header-section mb-4">
                            <h4 class="mb-2">${schedule.client_name}</h4>
                            <div class="d-flex gap-2">
                                <span class="badge bg-primary">${schedule.kind_of_place}</span>
                                <span class="badge bg-secondary">${schedule.type_of_work}</span>
                            </div>
                        </div>

                        <!-- Main Content -->
                        <div class="row g-4">
                            <!-- Left Column -->
                            <div class="col-md-6">
                                <div class="info-card">
                                    <h6 class="card-subtitle mb-3 text-muted">
                                        <i class="fas fa-info-circle me-2"></i>Job Details
                                    </h6>
                                    <ul class="list-unstyled">
                                        <li class="mb-3">
                                            <span class="text-muted"><i class="fas fa-map-marker-alt me-2"></i>Location:</span>
                                            <div class="fw-bold">${schedule.location_address}</div>
                                        </li>
                                        <li class="mb-3">
                                            <span class="text-muted"><i class="fas fa-calendar-day me-2"></i>Date:</span>
                                            <div class="fw-bold">${schedule.preferred_date}</div>
                                        </li>
                                        <li class="mb-3">
                                            <span class="text-muted"><i class="fas fa-clock me-2"></i>Time:</span>
                                            <div class="fw-bold">${formatTime(schedule.preferred_time)}</div>
                                        </li>
                                        <li class="mb-3">
                                            <span class="text-muted"><i class="fas fa-phone me-2"></i>Contact:</span>
                                            <div class="fw-bold">${schedule.contact_number || 'N/A'}</div>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="col-md-6">
                                <div class="info-card">
                                    <h6 class="card-subtitle mb-3 text-muted">
                                        <i class="fas fa-wrench me-2"></i>Technical Info
                                    </h6>
                                    <ul class="list-unstyled">
                                        <li class="mb-3">
                                            <span class="text-muted"><i class="fas fa-hashtag me-2"></i>Job ID:</span>
                                            <div class="fw-bold">#${schedule.id}</div>
                                        </li>
                                        <li class="mb-3">
                                            <span class="text-muted"><i class="fas fa-home me-2"></i>Type of Place:</span>
                                            <div class="fw-bold">${schedule.kind_of_place}</div>
                                        </li>
                                        <li class="mb-3">
                                            <span class="text-muted"><i class="fas fa-tools me-2"></i>Type of Work:</span>
                                            <div class="fw-bold">${schedule.type_of_work}</div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Notes Section -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="info-card">
                                    <h6 class="card-subtitle mb-3 text-muted">
                                        <i class="fas fa-sticky-note me-2"></i>Notes
                                    </h6>
                                    <div class="notes-content p-3 bg-light rounded">
                                        ${schedule.notes || '<em class="text-muted">No notes available</em>'}
                                    </div>
                                </div>
                            </div>
                        </div>

                        ${equipmentHTML}
                    </div>
                `;

                // Hide action button for job orders
                document.getElementById('actionButton').style.display = 'none';
            }

            document.getElementById('scheduleModalContent').innerHTML = content;
            new bootstrap.Modal('#scheduleDetailsModal').show();
        }

        function openReportForm(appointmentId) {
            // Close the details modal
            bootstrap.Modal.getInstance(document.getElementById('scheduleDetailsModal')).hide();

            // Redirect to the inspection page
            window.location.href = 'inspection.php';
        }

        function formatTime(timeString) {
            const time = new Date(`2000-01-01T${timeString}`);
            return time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        function getEquipmentHTML(typeOfWork) {
            const requiredItems = {
                'General Pest Control': [
                    'Chemical Sprayer', 'Protective Gloves', 'N95 Mask',
                    'Insecticide Concentrate', 'Bait Stations', 'Flashlight',
                    'Protective Goggles', 'Coverall Suit', 'Boot Covers'
                ],
                'Termite Baiting': [
                    'Termite Bait Stations', 'Drill', 'Soil Rod',
                    'Monitoring Stations', 'Gloves', 'Safety Glasses'
                ],
                'Rodent Control Only': [
                    'Rodent Bait', 'Traps', 'Gloves', 'Disinfectant',
                    'Sealing Materials', 'Inspection Camera'
                ],
                'Soil Poisoning': [
                    'Chemical Sprayer', 'Protective Suit', 'Respirator',
                    'Termiticide', 'Measuring Tools', 'Drill'
                ],
                'Wood Protection Only': [
                    'Wood Treatment Solution', 'Brushes', 'Sprayer',
                    'Protective Gear', 'Measuring Tools'
                ],
                'Weed Control': [
                    'Herbicide', 'Sprayer', 'Protective Gear',
                    'Measuring Tools', 'Gloves'
                ],
                'Disinfection': [
                    'Disinfectant Solution', 'Fogger', 'Sprayer',
                    'Full Protective Suit', 'Respirator', 'Gloves'
                ],
                'Installation of Pipes': [
                    'PVC Pipes', 'Drill', 'Saw', 'Measuring Tape',
                    'Adhesive', 'Safety Gear'
                ]
            };

            // Get equipment for the specific type of work, or use a default set
            const equipment = requiredItems[typeOfWork] || [
                'Inspection Kit', 'Safety Gear', 'Documentation Forms',
                'Digital Camera', 'Measuring Tools'
            ];

            // Add base items that are always needed
            const baseItems = ['Clipboard', 'Pen', 'Digital Camera'];
            const allItems = [...baseItems, ...equipment];

            // Generate HTML
            return `
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="info-card">
                            <h6 class="card-subtitle mb-3 text-muted">
                                <i class="fas fa-toolbox me-2"></i>Required Equipment
                            </h6>
                            <div class="d-flex flex-wrap gap-2">
                                ${allItems.map(item => `
                                    <span class="badge bg-light text-dark p-2 mb-2">
                                        <i class="fas fa-tools me-1"></i>${item}
                                    </span>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
    </script>

    <!-- Sidebar and Notification Scripts -->
    <script src="js/sidebar.js"></script>
    <script src="js/notifications.js"></script>
    <script src="js/tools-checklist.js"></script>
    <script>
        // Initialize notifications when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Fetch notifications
            fetchNotifications();

            // Set up date checking and auto-refresh
            setupDateRefresh();
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

            // Check if any upcoming schedules have today's date
            checkUpcomingSchedulesForToday(clientDate);
        }

        // Function to check if any upcoming schedules have today's date
        function checkUpcomingSchedulesForToday(todayDate) {
            // Get all upcoming schedule cards
            const upcomingCards = document.querySelectorAll('.upcoming-schedules .schedule-card');
            let needsRefresh = false;

            // Loop through each card and check the date
            upcomingCards.forEach(card => {
                // Find the schedule date element
                const dateElement = card.querySelector('.schedule-date');
                if (dateElement) {
                    // Extract the date from the element (format is "Day, Month DD, YYYY")
                    const dateText = dateElement.textContent.trim();
                    const dateParts = dateText.match(/[A-Za-z]{3}, ([A-Za-z]{3}) (\d{1,2}), (\d{4})/);

                    if (dateParts && dateParts.length === 4) {
                        const month = dateParts[1];
                        const day = dateParts[2].padStart(2, '0');
                        const year = dateParts[3];

                        // Convert to YYYY-MM-DD format for comparison
                        const monthNum = new Date(`${month} 1, 2000`).getMonth() + 1;
                        const formattedDate = `${year}-${monthNum.toString().padStart(2, '0')}-${day}`;

                        console.log('Checking upcoming schedule date:', formattedDate, 'against today:', todayDate);

                        // If the date matches today's date, we need to refresh
                        if (formattedDate === todayDate) {
                            console.log('Found a schedule that should be moved to today!');
                            needsRefresh = true;
                        }
                    }
                }
            });

            // If we found a schedule that needs to be moved, refresh the page
            if (needsRefresh) {
                console.log('Refreshing page to update schedules...');
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
