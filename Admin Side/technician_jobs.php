<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}
include '../db_connect.php';
include '../notification_functions.php';

// Check if technician_id is provided
if (!isset($_GET['technician_id']) || !is_numeric($_GET['technician_id'])) {
    header("Location: technicians.php");
    exit;
}

$technicianId = (int)$_GET['technician_id'];
$technicianName = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : 'Technician';

// Get technician details
$techQuery = "SELECT * FROM technicians WHERE technician_id = ?";
$stmt = $conn->prepare($techQuery);
$stmt->bind_param("i", $technicianId);
$stmt->execute();
$techResult = $stmt->get_result();

if ($techResult->num_rows === 0) {
    header("Location: technicians.php");
    exit;
}

$technician = $techResult->fetch_assoc();
$technicianName = $technician['username'];
$technicianFullName = $technician['tech_fname'] . ' ' . $technician['tech_lname'];

// Get technician's checked tools
function getTechnicianCheckedTools($technicianId) {
    global $conn;
    $checkedTools = [];
    $checklist = null;
    $toolIds = [];

    // First try to get tools from job_order_checklists
    $jobOrderChecklistQuery = "
        SELECT
            id,
            job_order_id,
            checked_tools,
            checked_items,
            total_items,
            checked_count,
            updated_at
        FROM job_order_checklists
        WHERE technician_id = ?
        ORDER BY updated_at DESC
        LIMIT 1
    ";

    $stmt = $conn->prepare($jobOrderChecklistQuery);
    $stmt->bind_param("i", $technicianId);
    $stmt->execute();
    $jobOrderResult = $stmt->get_result();

    if ($jobOrderResult->num_rows > 0) {
        $jobOrderChecklist = $jobOrderResult->fetch_assoc();
        $checklist = [
            'checklist_date' => date('Y-m-d', strtotime($jobOrderChecklist['updated_at'])),
            'total_items' => $jobOrderChecklist['total_items'],
            'checked_count' => $jobOrderChecklist['checked_count'],
            'created_at' => $jobOrderChecklist['updated_at'],
            'job_order_id' => $jobOrderChecklist['job_order_id']
        ];

        // Parse the checked_tools JSON
        if (!empty($jobOrderChecklist['checked_tools'])) {
            try {
                $decodedTools = json_decode($jobOrderChecklist['checked_tools'], true);

                // Extract tool IDs
                if (is_array($decodedTools)) {
                    foreach ($decodedTools as $tool) {
                        if (is_array($tool) && isset($tool['id']) && isset($tool['type']) && $tool['type'] === 'tool') {
                            $toolIds[] = $tool['id'];
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Error parsing job order checklist tools: " . $e->getMessage());
            }
        }
    }

    // If no tools found in job_order_checklists, try technician_checklist_logs as fallback
    if (empty($toolIds)) {
        $checklistQuery = "
            SELECT
                checklist_date,
                checked_items,
                total_items,
                checked_count,
                created_at
            FROM technician_checklist_logs
            WHERE technician_id = ?
            ORDER BY checklist_date DESC
            LIMIT 1
        ";

        $stmt = $conn->prepare($checklistQuery);
        $stmt->bind_param("i", $technicianId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $checklist = $result->fetch_assoc();

            // Parse the checked_items JSON
            if (!empty($checklist['checked_items'])) {
                try {
                    $decodedItems = json_decode($checklist['checked_items'], true);

                    // Handle both formats: array of IDs or array of objects with 'id' property
                    if (is_array($decodedItems)) {
                        foreach ($decodedItems as $item) {
                            if (is_array($item) && isset($item['id'])) {
                                // Format: array of objects with 'id' property
                                $toolIds[] = $item['id'];
                            } elseif (is_numeric($item)) {
                                // Format: array of IDs
                                $toolIds[] = $item;
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error parsing technician checklist items: " . $e->getMessage());
                }
            }
        }
    }

    // If there are tool IDs, get their details
    if (!empty($toolIds)) {
        // Convert array to comma-separated string for SQL IN clause
        $idList = implode(',', array_map('intval', $toolIds));

        // Get tool details for checked items
        $toolsQuery = "
            SELECT id, name, category, description
            FROM tools_equipment
            WHERE id IN ($idList)
            ORDER BY category, name
        ";

        $toolsResult = $conn->query($toolsQuery);

        if ($toolsResult) {
            // Group tools by category
            while ($tool = $toolsResult->fetch_assoc()) {
                $category = $tool['category'];
                if (!isset($checkedTools[$category])) {
                    $checkedTools[$category] = [];
                }
                $checkedTools[$category][] = $tool;
            }
        }
    }

    return [
        'checklist' => $checklist,
        'tools' => $checkedTools
    ];
}

// Helper function to get category icon
function getCategoryIcon($category) {
    $icons = [
        'General Pest Control' => 'fa-spray-can',
        'Termite' => 'fa-bug',
        'Termite Treatment' => 'fa-house-damage',
        'Weed Control' => 'fa-seedling',
        'Bed Bugs' => 'fa-bed'
    ];

    return isset($icons[$category]) ? $icons[$category] : 'fa-tools';
}

// Get the technician's checked tools
$technicianToolsData = getTechnicianCheckedTools($technicianId);

// Get current month and year
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = (int)date('m');
}
if ($year < 2020 || $year > 2100) {
    $year = (int)date('Y');
}

// Get first day of the month
$firstDay = mktime(0, 0, 0, $month, 1, $year);

// Get month name and number of days
$monthName = date('F', $firstDay);
$daysInMonth = date('t', $firstDay);

// Get the day of the week the month starts on (0 = Sunday, 6 = Saturday)
$dayOfWeek = date('w', $firstDay);

// Get previous and next month links
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $technicianFullName ?>'s Jobs - MacJ Pest Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/admin-base.css">
    <link rel="stylesheet" href="css/technicians-page.css">
    <link rel="stylesheet" href="css/technician-jobs.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <style>
        /* Calendar styles */
        .calendar-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 30px;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .calendar-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1F2937;
        }

        .calendar-nav {
            display: flex;
            gap: 10px;
        }

        .calendar-nav-btn {
            background-color: #F3F4F6;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .calendar-nav-btn:hover {
            background-color: #E5E7EB;
        }

        .calendar-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3px;
        }

        .calendar-table th {
            background-color: #3B82F6;
            color: white;
            text-align: center;
            padding: 10px;
            font-weight: 500;
            border-radius: 4px;
        }

        .calendar-table td {
            height: 100px;
            vertical-align: top;
            padding: 8px;
            border-radius: 4px;
            background-color: #F9FAFB;
            transition: all 0.2s ease;
        }

        .calendar-table td:hover {
            background-color: #F3F4F6;
        }

        .calendar-day {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 5px;
            color: #1F2937;
        }

        .calendar-day-empty {
            background-color: #F3F4F6 !important;
        }

        .calendar-day-today {
            background-color: rgba(59, 130, 246, 0.1);
            border: 2px solid #3B82F6;
        }

        .calendar-day-has-events {
            position: relative;
            cursor: pointer;
        }

        .calendar-day-has-events:after {
            content: '';
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background-color: #3B82F6;
            border-radius: 50%;
        }

        .calendar-day-has-checklist:after {
            background-color: #10B981;
        }

        .event-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .event-dot-appointment {
            background-color: #3B82F6;
        }

        .event-dot-job-order {
            background-color: #F59E0B;
        }

        .event-dot-checklist {
            background-color: #10B981;
        }

        .event-count {
            font-size: 0.8rem;
            color: #6B7280;
            margin-top: 5px;
        }

        /* Modal styles */
        .day-details-modal .modal-header {
            background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
            color: white;
            border-bottom: none;
        }

        .day-details-modal .modal-title {
            font-weight: 600;
        }

        .day-details-modal .close {
            color: white;
            opacity: 0.8;
        }

        .day-details-modal .close:hover {
            opacity: 1;
        }

        .day-details-modal .modal-body {
            padding: 20px;
        }

        .day-details-tabs {
            margin-bottom: 20px;
        }

        .day-details-tab {
            padding: 10px 15px;
            background-color: #F3F4F6;
            border: none;
            border-radius: 4px;
            margin-right: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .day-details-tab.active {
            background-color: #3B82F6;
            color: white;
        }

        .day-details-content {
            margin-top: 20px;
        }

        .day-details-section {
            display: none;
        }

        .day-details-section.active {
            display: block;
        }

        .checklist-summary {
            background-color: #F9FAFB;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .checklist-progress {
            height: 10px;
            background-color: #E5E7EB;
            border-radius: 5px;
            margin: 10px 0;
            overflow: hidden;
        }

        .checklist-progress-bar {
            height: 100%;
            background-color: #10B981;
            border-radius: 5px;
            transition: width 0.3s ease;
        }

        .checklist-progress-text {
            font-size: 1rem;
            color: #4B5563;
            font-weight: 500;
        }

        .checklist-category {
            background-color: #F3F4F6;
            padding: 12px 15px;
            margin-top: 20px;
            margin-bottom: 10px;
            font-weight: 600;
            color: #1F2937;
            display: flex;
            align-items: center;
            border-radius: 8px;
            font-size: 1.1rem;
        }

        .checklist-category i {
            margin-right: 10px;
            color: #3B82F6;
            font-size: 1.2rem;
        }

        .checklist-items-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .checklist-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #E5E7EB;
            transition: background-color 0.2s ease;
        }

        .checklist-item:hover {
            background-color: #F9FAFB;
        }

        .checklist-item:last-child {
            border-bottom: none;
        }

        .checklist-item-checked {
            color: #10B981;
            font-size: 1.2rem;
        }

        .checklist-item-unchecked {
            color: #EF4444;
            font-size: 1.2rem;
        }

        .checklist-item-details {
            flex: 1;
        }

        .checklist-item-name {
            font-weight: 500;
            color: #1F2937;
            margin-bottom: 3px;
        }

        .checklist-item-description {
            color: #6B7280;
            font-size: 0.85rem;
        }

        .schedule-item {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            background-color: #F9FAFB;
            border-left: 4px solid #3B82F6;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            position: relative;
            transition: all 0.3s ease;
        }

        .schedule-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .schedule-item.job-order {
            border-left-color: #F59E0B;
        }

        .schedule-item.completed {
            border-left-color: #10B981;
        }

        .schedule-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .schedule-item-type {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .schedule-item-type.appointment {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3B82F6;
        }

        .schedule-item-type.job-order {
            background-color: rgba(245, 158, 11, 0.1);
            color: #F59E0B;
        }

        .schedule-item-time {
            font-weight: 600;
            color: #1F2937;
            font-size: 1.1rem;
        }

        .schedule-item-client {
            font-weight: 600;
            margin-bottom: 8px;
            color: #1F2937;
            font-size: 1.1rem;
        }

        .schedule-item-details {
            color: #4B5563;
            font-size: 0.95rem;
            margin-bottom: 12px;
        }

        .schedule-item-location {
            margin-top: 12px;
            display: flex;
            align-items: center;
            color: #4B5563;
            font-size: 0.95rem;
        }

        .schedule-item-location i {
            margin-right: 8px;
            color: #3B82F6;
        }

        .schedule-status {
            position: absolute;
            top: 20px;
            right: 20px;
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .schedule-status.completed {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10B981;
        }

        .schedule-status.scheduled {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3B82F6;
        }

        .schedule-status i {
            margin-right: 5px;
        }

        .no-data-message {
            text-align: center;
            padding: 30px;
            color: #6B7280;
        }

        /* Tools & Equipment Styles */
        .content-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid #E5E7EB;
        }

        .section-header {
            font-size: 20px;
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .section-header i {
            margin-right: 10px;
            color: #3B82F6;
        }

        .inventory-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
            min-width: 200px;
            transition: all 0.2s ease;
        }

        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .summary-info {
            flex: 1;
        }

        .summary-info h3 {
            margin: 0 0 5px;
            font-size: 0.9rem;
            color: #6B7280;
            font-weight: 500;
        }

        .summary-info p {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: #1F2937;
        }

        .assessment-table-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .assessment-table {
            width: 100%;
            border-collapse: collapse;
        }

        .assessment-table th {
            background-color: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #1F2937;
            border-bottom: 1px solid #E5E7EB;
        }

        .assessment-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #E5E7EB;
        }

        .checklist-category-header {
            background-color: #f8f9fa !important;
            color: #3B82F6;
            font-weight: 600;
            padding: 15px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e9ecef;
        }

        .checklist-category-header i {
            margin-right: 8px;
            color: #3B82F6;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-title">
            <h1>Admin Dashboard</h1>
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
                    <div class="user-name"><?= $_SESSION['username'] ?? 'Admin' ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>MacJ Pest Control</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                    <li><a href="calendar.php"><i class="fas fa-calendar-alt"></i> Calendar</a></li>
                    <li><a href="assessment_report.php"><i class="fas fa-clipboard-check"></i> Assessment Report</a></li>
                    <li><a href="joborder_report.php"><i class="fas fa-tasks"></i> Job Order Report</a></li>
                    <li><a href="chemical_inventory.php"><i class="fas fa-flask"></i> Chemical Inventory</a></li>
                    <li><a href="tools_equipment.php"><i class="fas fa-tools"></i> Tools and Equipment</a></li>
                    <li><a href="services.php"><i class="fas fa-concierge-bell"></i> Services</a></li>
                    <li class="active"><a href="technicians.php"><i class="fas fa-user-md"></i> Technicians</a></li>
                    <li><a href="clients.php"><i class="fas fa-users"></i> Clients</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="../SignOut.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Mobile menu toggle -->
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Main Content -->
        <main class="main-content">
            <div class="technician-jobs-content">
                <!-- Back button and technician info -->
                <div class="page-header">
                    <a href="technicians.php" class="back-button">
                        <i class="fas fa-arrow-left"></i> Back to Technicians
                    </a>
                    <div class="technician-info">
                        <?php if (!empty($technician['technician_picture'])): ?>
                            <img src="<?= $technician['technician_picture'] ?>" alt="<?= htmlspecialchars($technicianFullName) ?>" class="technician-avatar">
                        <?php else: ?>
                            <div class="technician-avatar-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                        <div class="technician-details">
                            <h1><?= htmlspecialchars($technicianFullName) ?></h1>
                            <div class="technician-meta">
                                <span><i class="fas fa-id-badge"></i> <?= htmlspecialchars($technicianName) ?></span>
                                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($technician['tech_contact_number']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Job Summary Cards -->
                <div class="job-summary-section">
                    <h2><i class="fas fa-chart-pie"></i> Job Summary</h2>
                    <div class="job-summary-cards" id="jobsSummary">
                        <div class="job-summary-card">
                            <div class="job-summary-icon appointments">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="job-summary-data">
                                <div class="job-summary-value" id="appointmentsCount">-</div>
                                <div class="job-summary-label">Appointments</div>
                            </div>
                        </div>
                        <div class="job-summary-card">
                            <div class="job-summary-icon job-orders">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="job-summary-data">
                                <div class="job-summary-value" id="jobOrdersCount">-</div>
                                <div class="job-summary-label">Job Orders</div>
                            </div>
                        </div>
                        <div class="job-summary-card">
                            <div class="job-summary-icon completed">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="job-summary-data">
                                <div class="job-summary-value" id="completedCount">-</div>
                                <div class="job-summary-label">Completed</div>
                            </div>
                        </div>
                        <div class="job-summary-card">
                            <div class="job-summary-icon completion-rate">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="job-summary-data">
                                <div class="job-summary-value" id="completionRate">-</div>
                                <div class="job-summary-label">Completion Rate</div>
                            </div>
                        </div>
                        <div class="job-summary-card" id="checklistCard">
                            <div class="job-summary-icon tools">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div class="job-summary-data">
                                <div class="job-summary-value" id="checklistPercentage">-</div>
                                <div class="job-summary-label">Tools Checklist</div>
                                <div class="checklist-details" id="checklistDetails">No checklist completed this month</div>
                                <div class="checklist-progress">
                                    <div class="checklist-progress-bar" id="checklistProgressBar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tools & Equipment Section -->
                <div class="content-card">
                    <h2 class="section-header">
                        <i class="fas fa-tools"></i> Tools & Equipment Checklist
                    </h2>

                    <?php if (!empty($technicianToolsData['checklist'])):
                        $checklist = $technicianToolsData['checklist'];
                        $tools = $technicianToolsData['tools'];
                        $checklistPercentage = 0;
                        if ($checklist['total_items'] > 0) {
                            $checklistPercentage = round(($checklist['checked_count'] / $checklist['total_items']) * 100);
                        }
                    ?>
                    <!-- Checklist Summary -->
                    <div class="inventory-summary" style="margin-bottom: 20px;">
                        <div class="summary-card">
                            <div class="summary-icon" style="background-color: #3B82F6;">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div class="summary-info">
                                <h3>Completion Rate</h3>
                                <p><?= $checklistPercentage ?>%</p>
                            </div>
                        </div>

                        <div class="summary-card">
                            <div class="summary-icon" style="background-color: #10B981;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="summary-info">
                                <h3>Items Checked</h3>
                                <p><?= $checklist['checked_count'] ?></p>
                            </div>
                        </div>

                        <div class="summary-card">
                            <div class="summary-icon" style="background-color: #F59E0B;">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="summary-info">
                                <h3>Checklist Date</h3>
                                <p><?= date('M j, Y', strtotime($checklist['checklist_date'])) ?></p>
                            </div>
                        </div>

                        <div class="summary-card">
                            <div class="summary-icon" style="background-color: #6366F1;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="summary-info">
                                <h3>Completed At</h3>
                                <p><?= date('h:i A', strtotime($checklist['created_at'])) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Tools List -->
                    <div class="assessment-table-container">
                        <?php if (empty($tools)): ?>
                            <div class="alert alert-info" style="text-align: center; padding: 30px;">
                                <i class="fas fa-clipboard" style="font-size: 2rem; margin-bottom: 15px; display: block;"></i>
                                <h3>No Tools Checked</h3>
                                <p>No tools or equipment have been checked by this technician.</p>
                            </div>
                        <?php else: ?>
                            <table class="assessment-table">
                                <thead>
                                    <tr>
                                        <th class="col-id" width="8%">ID</th>
                                        <th class="col-status" width="10%">Status</th>
                                        <th class="col-client" width="32%">Tool/Equipment</th>
                                        <th class="col-location" width="50%">Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tools as $category => $categoryTools): ?>
                                        <tr>
                                            <td colspan="4" class="checklist-category-header">
                                                <i class="fas <?= getCategoryIcon($category) ?>"></i> <?= $category ?>
                                            </td>
                                        </tr>
                                        <?php foreach ($categoryTools as $tool): ?>
                                        <tr>
                                            <td><?= $tool['id'] ?></td>
                                            <td>
                                                <span class="status-badge completed">
                                                    <i class="fas fa-check-circle"></i> Checked
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($tool['name']) ?></td>
                                            <td class="truncate" title="<?= htmlspecialchars($tool['description'] ?? '') ?>">
                                                <?= htmlspecialchars($tool['description'] ?? '') ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                        <div class="alert alert-info" style="text-align: center; padding: 30px;">
                            <i class="fas fa-clipboard" style="font-size: 2rem; margin-bottom: 15px; display: block;"></i>
                            <h3>No Checklist Found</h3>
                            <p>No checklist has been completed by this technician yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Calendar Section -->
                <div class="calendar-container">
                    <div class="calendar-header">
                        <div class="calendar-title">
                            <i class="fas fa-calendar-alt"></i> <?= $monthName ?> <?= $year ?> Schedule
                        </div>
                        <div class="calendar-nav">
                            <a href="?technician_id=<?= $technicianId ?>&month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="calendar-nav-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                            <a href="?technician_id=<?= $technicianId ?>" class="calendar-nav-btn">
                                <i class="fas fa-calendar-day"></i> Today
                            </a>
                            <a href="?technician_id=<?= $technicianId ?>&month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="calendar-nav-btn">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>

                    <table class="calendar-table">
                        <thead>
                            <tr>
                                <th>Sunday</th>
                                <th>Monday</th>
                                <th>Tuesday</th>
                                <th>Wednesday</th>
                                <th>Thursday</th>
                                <th>Friday</th>
                                <th>Saturday</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php
                                // Fill in empty cells for days of the week before the first day of the month
                                for ($i = 0; $i < $dayOfWeek; $i++) {
                                    echo '<td class="calendar-day-empty"></td>';
                                }

                                // Counter for the current day of the week
                                $currentDayOfWeek = $dayOfWeek;

                                // Current date for highlighting today
                                $today = date('Y-m-d');

                                // Loop through days of the month
                                for ($day = 1; $day <= $daysInMonth; $day++) {
                                    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                    $isToday = ($date === $today);

                                    // Start a new row if it's Sunday (0) except for the first row
                                    if ($currentDayOfWeek == 7) {
                                        echo '</tr><tr>';
                                        $currentDayOfWeek = 0;
                                    }

                                    // CSS classes for the day cell
                                    $dayClasses = [];
                                    if ($isToday) {
                                        $dayClasses[] = 'calendar-day-today';
                                    }

                                    // Add data attributes for the date
                                    $dataAttr = 'data-date="' . $date . '" data-day="' . $day . '" data-month="' . $month . '" data-year="' . $year . '"';

                                    // We'll add the has-events class via JavaScript after loading the data
                                    echo '<td class="' . implode(' ', $dayClasses) . '" ' . $dataAttr . '>';
                                    echo '<div class="calendar-day">' . $day . '</div>';
                                    echo '<div class="calendar-day-events" id="events-' . $date . '"></div>';
                                    echo '</td>';

                                    $currentDayOfWeek++;
                                }

                                // Fill in empty cells for the days of the week after the last day of the month
                                for ($i = $currentDayOfWeek; $i < 7; $i++) {
                                    echo '<td class="calendar-day-empty"></td>';
                                }
                                ?>
                            </tr>
                        </tbody>
                    </table>

                    <div class="calendar-legend" style="margin-top: 15px; display: flex; gap: 20px;">
                        <div style="display: flex; align-items: center;">
                            <span class="event-dot event-dot-appointment"></span> Appointments
                        </div>
                        <div style="display: flex; align-items: center;">
                            <span class="event-dot event-dot-job-order"></span> Job Orders
                        </div>
                        <div style="display: flex; align-items: center;">
                            <span class="event-dot event-dot-checklist"></span> Tools Checklist
                        </div>
                    </div>
                </div>
            </div>
        </main>


    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script>
    $(document).ready(function() {
        // Mobile menu toggle
        $('#menuToggle').on('click', function() {
            $('.sidebar').toggleClass('active');
        });

        // Store data globally
        window.technicianData = {
            jobs: [],
            checklists: {},
            monthEvents: {}
        };

        // Load technician data for the current month
        loadTechnicianMonthData();



        // Calendar day click handler
        $(document).on('click', '.calendar-day-has-events', function() {
            const date = $(this).data('date');
            const technicianId = <?= $technicianId ?>;

            // Redirect to the day details page
            window.location.href = `technician_day_details.php?technician_id=${technicianId}&date=${date}`;
        });

        // Function to load technician data for the current month
        function loadTechnicianMonthData() {
            const month = <?= $month ?>;
            const year = <?= $year ?>;

            $.ajax({
                url: 'get_technician_month_data.php',
                method: 'GET',
                data: {
                    technician_id: <?= $technicianId ?>,
                    month: month,
                    year: year
                },
                dataType: 'json',
                success: function(data) {
                    console.log('Received month data:', data);

                    if (data.success) {
                        // Store the data
                        window.technicianData.jobs = data.jobs || [];
                        window.technicianData.checklists = data.checklists || {};
                        window.technicianData.monthEvents = data.events_by_date || {};

                        // Update the calendar
                        updateCalendar();

                        // Update summary statistics
                        updateJobSummary();
                    } else {
                        console.error('Error loading month data:', data.error);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error loading month data:', error);
                }
            });
        }

        // Function to update the calendar with events
        function updateCalendar() {
            const events = window.technicianData.monthEvents;

            // Clear existing event indicators
            $('.calendar-day-events').empty();
            $('.calendar-table td').removeClass('calendar-day-has-events calendar-day-has-checklist');

            // Add events to the calendar
            for (const date in events) {
                const eventData = events[date];
                const cell = $(`.calendar-table td[data-date="${date}"]`);
                const eventsContainer = $(`#events-${date}`);

                if (cell.length && eventData) {
                    // Add the has-events class to make the cell clickable
                    cell.addClass('calendar-day-has-events');

                    // If there's a checklist for this date, add the has-checklist class
                    if (eventData.has_checklist) {
                        cell.addClass('calendar-day-has-checklist');
                    }

                    // Add event counts
                    let eventHtml = '';

                    if (eventData.appointments > 0) {
                        eventHtml += `<div><span class="event-dot event-dot-appointment"></span>${eventData.appointments} Appointment${eventData.appointments > 1 ? 's' : ''}</div>`;
                    }

                    if (eventData.job_orders > 0) {
                        eventHtml += `<div><span class="event-dot event-dot-job-order"></span>${eventData.job_orders} Job Order${eventData.job_orders > 1 ? 's' : ''}</div>`;
                    }

                    if (eventData.has_checklist) {
                        eventHtml += `<div><span class="event-dot event-dot-checklist"></span>Checklist</div>`;
                    }

                    eventsContainer.html(eventHtml);
                }
            }
        }

        // Function to update job summary
        function updateJobSummary() {
            const jobs = window.technicianData.jobs;
            const checklists = window.technicianData.checklists;

            // Calculate statistics
            const appointmentsCount = jobs.filter(job => job.job_type === 'appointment').length;
            const completedCount = jobs.filter(job => job.status === 'completed').length;
            const jobOrdersCount = jobs.filter(job => job.job_type === 'job_order').length;
            const completionRate = completedCount > 0 && (appointmentsCount + jobOrdersCount) > 0
                ? Math.round((completedCount / (appointmentsCount + jobOrdersCount)) * 100)
                : 0;

            // Update summary cards
            $('#appointmentsCount').text(appointmentsCount);
            $('#jobOrdersCount').text(jobOrdersCount);
            $('#completedCount').text(completedCount);
            $('#completionRate').text(completionRate + '%');

            // Calculate checklist statistics
            let totalChecked = 0;
            let totalItems = 0;
            let hasChecklists = false;

            for (const date in checklists) {
                const checklist = checklists[date];
                if (checklist) {
                    totalChecked += parseInt(checklist.checked_count) || 0;
                    totalItems += parseInt(checklist.total_items) || 0;
                    hasChecklists = true;
                }
            }

            // Update checklist card
            if (hasChecklists && totalItems > 0) {
                const percentage = Math.round((totalChecked / totalItems) * 100);
                const checklistClass = percentage >= 80 ? 'success' :
                                    (percentage >= 50 ? 'warning' : 'danger');

                $('#checklistPercentage').text(percentage + '%');
                $('#checklistDetails').text(`${totalChecked}/${totalItems} items checked this month`);
                $('#checklistProgressBar').css('width', percentage + '%').removeClass('success warning danger').addClass(checklistClass);
                $('#checklistCard').removeClass('success warning danger').addClass(checklistClass);
            } else {
                $('#checklistPercentage').text('0%');
                $('#checklistDetails').text('No checklists completed this month');
                $('#checklistProgressBar').css('width', '0%').removeClass('success warning danger');
                $('#checklistCard').removeClass('success warning danger');
            }
        }



        // Helper function to get category icon
        function getCategoryIcon(category) {
            const icons = {
                'General Pest Control': 'fa-spray-can',
                'Termite': 'fa-bug',
                'Termite Treatment': 'fa-house-damage',
                'Weed Control': 'fa-seedling',
                'Bed Bugs': 'fa-bed'
            };

            return icons[category] || 'fa-tools';
        }

        // Helper function to format date for display
        function formatDateForDisplay(dateStr) {
            const date = new Date(dateStr);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }

        // Helper function to format time
        function formatTime(timeStr) {
            // Convert 24-hour time to 12-hour format
            const timeParts = timeStr.split(':');
            let hours = parseInt(timeParts[0]);
            const minutes = timeParts[1];
            const ampm = hours >= 12 ? 'PM' : 'AM';

            hours = hours % 12;
            hours = hours ? hours : 12; // Convert 0 to 12

            return `${hours}:${minutes} ${ampm}`;
        }
    });
    </script>

    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>

    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <script src="js/chemical-notifications.js"></script>
    <script>
        // Initialize notifications when the page loads
        $(document).ready(function() {
            // Fetch notifications immediately
            if (typeof fetchNotifications === 'function') {
                fetchNotifications();

                // Set up periodic notification checks
                setInterval(fetchNotifications, 60000); // Check every minute
            } else {
                console.error("fetchNotifications function not found");
            }
        });
    </script>
</body>
</html>
