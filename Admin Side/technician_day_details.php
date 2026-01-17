<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}
include '../db_connect.php';
include '../notification_functions.php';

// Check if technician_id and date are provided
if (!isset($_GET['technician_id']) || !is_numeric($_GET['technician_id']) || !isset($_GET['date'])) {
    header("Location: technicians.php");
    exit;
}

$technicianId = (int)$_GET['technician_id'];
$date = $_GET['date'];

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header("Location: technicians.php");
    exit;
}

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

// Format date for display
$formattedDate = date('l, F j, Y', strtotime($date));

// Get appointments for this date
$appointmentsQuery = "
    SELECT
        'appointment' as job_type,
        a.appointment_id as id,
        a.client_name,
        a.location_address,
        a.kind_of_place,
        a.preferred_date,
        TIME_FORMAT(a.preferred_time, '%H:%i') as preferred_time,
        CASE
            WHEN ar.report_id IS NOT NULL THEN 'completed'
            ELSE a.status
        END as status,
        a.notes
    FROM appointments a
    LEFT JOIN assessment_report ar ON a.appointment_id = ar.appointment_id
    WHERE a.technician_id = ?
    AND a.preferred_date = ?
    ORDER BY a.preferred_time
";

$stmt = $conn->prepare($appointmentsQuery);
$stmt->bind_param("is", $technicianId, $date);
$stmt->execute();
$appointmentsResult = $stmt->get_result();
$appointments = [];

while ($row = $appointmentsResult->fetch_assoc()) {
    $appointments[] = $row;
}

// Get job orders for this date
$jobOrdersQuery = "
    SELECT
        'job_order' as job_type,
        j.job_order_id as id,
        j.type_of_work,
        j.preferred_date,
        TIME_FORMAT(j.preferred_time, '%H:%i') as preferred_time,
        a.client_name,
        a.location_address,
        a.kind_of_place,
        CASE
            WHEN CURDATE() > j.preferred_date THEN 'completed'
            ELSE 'scheduled'
        END as status,
        a.notes
    FROM job_order j
    JOIN job_order_technicians jot ON j.job_order_id = jot.job_order_id
    JOIN assessment_report ar ON j.report_id = ar.report_id
    JOIN appointments a ON ar.appointment_id = a.appointment_id
    WHERE jot.technician_id = ?
    AND j.preferred_date = ?
    AND j.client_approval_status IN ('approved', 'one-time')
    ORDER BY j.preferred_time
";

$stmt = $conn->prepare($jobOrdersQuery);
$stmt->bind_param("is", $technicianId, $date);
$stmt->execute();
$jobOrdersResult = $stmt->get_result();
$jobOrders = [];

while ($row = $jobOrdersResult->fetch_assoc()) {
    $jobOrders[] = $row;
}

// Combine appointments and job orders
$schedules = array_merge($appointments, $jobOrders);

// Sort by time
usort($schedules, function($a, $b) {
    return $a['preferred_time'] <=> $b['preferred_time'];
});

// Get checklist for this date
$checklistQuery = "
    SELECT
        checklist_date,
        checked_items,
        total_items,
        checked_count,
        created_at
    FROM technician_checklist_logs
    WHERE technician_id = ?
    AND checklist_date = ?
";

$stmt = $conn->prepare($checklistQuery);
$stmt->bind_param("is", $technicianId, $date);
$stmt->execute();
$checklistResult = $stmt->get_result();
$checklist = $checklistResult->num_rows > 0 ? $checklistResult->fetch_assoc() : null;

// Get tools and equipment list
$toolsQuery = "SELECT id, name, category, description FROM tools_equipment ORDER BY category, name";
$toolsResult = $conn->query($toolsQuery);
$tools = [];

while ($row = $toolsResult->fetch_assoc()) {
    $category = $row['category'];
    if (!isset($tools[$category])) {
        $tools[$category] = [];
    }
    $tools[$category][] = $row;
}

// Parse checked items
$checkedItems = [];
if ($checklist && !empty($checklist['checked_items'])) {
    try {
        $decodedItems = json_decode($checklist['checked_items'], true);

        // Handle both formats: array of IDs or array of objects with 'id' property
        if (is_array($decodedItems)) {
            foreach ($decodedItems as $item) {
                if (is_array($item) && isset($item['id'])) {
                    // Format: array of objects with 'id' property
                    $checkedItems[] = $item['id'];
                } elseif (is_numeric($item)) {
                    // Format: array of IDs
                    $checkedItems[] = $item;
                }
            }
        }
    } catch (Exception $e) {
        $checkedItems = [];
    }
}

// Calculate checklist percentage
$checklistPercentage = 0;
if ($checklist && $checklist['total_items'] > 0) {
    $checklistPercentage = round(($checklist['checked_count'] / $checklist['total_items']) * 100);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($technicianFullName) ?>'s Schedule - <?= $formattedDate ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/admin-base.css">
    <link rel="stylesheet" href="css/technicians-page.css">
    <link rel="stylesheet" href="css/technician-jobs.css">
    <link rel="stylesheet" href="css/assessment-table.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <style>
        .page-content {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            padding: 10px 15px;
            background-color: #F3F4F6;
            color: #1F2937;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background-color: #E5E7EB;
            color: #2563EB;
            text-decoration: none;
        }

        .back-button i {
            margin-right: 8px;
        }

        .date-header {
            font-size: 24px;
            font-weight: 600;
            color: #1F2937;
            margin: 0;
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

        .content-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid #E5E7EB;
        }

        /* Common Table Styles */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 0;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            overflow: hidden;
        }

        .data-table th {
            background-color: #F3F4F6;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #1F2937;
            border-bottom: 1px solid #E5E7EB;
            white-space: nowrap;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #E5E7EB;
            vertical-align: middle;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover td {
            background-color: #F9FAFB;
        }

        .job-type-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-align: center;
            white-space: nowrap;
        }

        .job-type-badge.appointment {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3B82F6;
        }

        .job-type-badge.job-order {
            background-color: rgba(245, 158, 11, 0.1);
            color: #F59E0B;
        }

        .job-type-badge i {
            margin-right: 5px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-align: center;
            white-space: nowrap;
        }

        .status-badge.completed {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-badge.scheduled {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }

        .status-badge i {
            margin-right: 5px;
        }

        /* Inventory Summary Cards */
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

        /* Truncate text */
        .truncate {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 250px;
        }

        /* Assessment Table Container */
        .assessment-table-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }

        /* Alert Styles */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-info {
            background-color: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3B82F6;
            color: #3B82F6;
        }

        .alert h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }

        .alert p {
            margin: 0;
        }

        /* Filter Styles */
        .filter-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
            overflow: hidden;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background-color: #f1f3f5;
            border-bottom: 1px solid #e9ecef;
        }

        .filter-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #495057;
        }

        .filter-header h3 i {
            margin-right: 8px;
            color: #3B82F6;
        }

        .filter-body {
            padding: 15px 20px;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
            font-size: 14px;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            background-color: white;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            border-color: #3B82F6;
            outline: none;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
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

        .checklist-item-checked {
            color: #10B981;
            font-size: 1.2rem;
        }

        .checklist-item-unchecked {
            color: #EF4444;
            font-size: 1.2rem;
        }

        .no-data-message {
            text-align: center;
            padding: 30px;
            color: #6B7280;
            font-size: 16px;
        }

        .no-data-message i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        .tab-buttons {
            display: flex;
            margin-bottom: 25px;
            border-bottom: 1px solid #E5E7EB;
            padding-bottom: 0;
        }

        .tab-button {
            padding: 15px 25px;
            background: none;
            border: none;
            font-size: 16px;
            font-weight: 600;
            color: #6B7280;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            margin-right: 20px;
            position: relative;
            bottom: -1px;
        }

        .tab-button.active {
            color: #2563EB;
            border-bottom-color: #2563EB;
        }

        .tab-button:hover {
            color: #2563EB;
        }

        .tab-button i {
            margin-right: 10px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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
                    <li><a href="calendar.php"><i class="fas fa-calendar-alt"></i> Calendar</a></li>
                    <li><a href="assessment_report.php"><i class="fas fa-clipboard-check"></i> Assessment Report</a></li>
                    <li><a href="joborder_report.php"><i class="fas fa-tasks"></i> Job Order Report</a></li>
                    <li><a href="chemical_inventory.php"><i class="fas fa-flask"></i> Chemical Inventory</a></li>
                    <li><a href="tools_equipment.php"><i class="fas fa-tools"></i> Tools and Equipment</a></li>
                    <li class="active"><a href="technicians.php"><i class="fas fa-user-md"></i> Technicians</a></li>
                    <li><a href="clients.php"><i class="fas fa-users"></i> Clients</a></li>
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
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <a href="technician_jobs.php?technician_id=<?= $technicianId ?>" class="back-button">
                            <i class="fas fa-arrow-left"></i> Back to Calendar
                        </a>
                        <h1 class="date-header"><?= $formattedDate ?></h1>
                        <p>Technician: <?= htmlspecialchars($technicianFullName) ?> (<?= htmlspecialchars($technicianName) ?>)</p>
                    </div>
                </div>

                <!-- Tab Buttons -->
                <div class="tab-buttons">
                    <button class="tab-button active" data-tab="schedules">
                        <i class="fas fa-calendar-check"></i> Schedules
                    </button>
                    <button class="tab-button" data-tab="checklist">
                        <i class="fas fa-tools"></i> Tools & Equipment Checklist
                    </button>
                </div>

                <!-- Schedules Tab -->
                <div class="tab-content active" id="schedules-tab">
                    <div class="content-card">
                        <h2 class="section-header">
                            <i class="fas fa-calendar-day"></i> Schedule for <?= $formattedDate ?>
                        </h2>

                        <!-- Schedule Summary -->
                        <?php if (!empty($schedules)):
                            $appointmentCount = 0;
                            $jobOrderCount = 0;
                            $completedCount = 0;
                            $scheduledCount = 0;

                            foreach ($schedules as $schedule) {
                                if ($schedule['job_type'] === 'appointment') {
                                    $appointmentCount++;
                                } else {
                                    $jobOrderCount++;
                                }

                                if ($schedule['status'] === 'completed') {
                                    $completedCount++;
                                } else {
                                    $scheduledCount++;
                                }
                            }
                        ?>
                        <div class="inventory-summary" style="margin-bottom: 20px;">
                            <div class="summary-card">
                                <div class="summary-icon" style="background-color: #3B82F6;">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="summary-info">
                                    <h3>Appointments</h3>
                                    <p><?= $appointmentCount ?></p>
                                </div>
                            </div>

                            <div class="summary-card">
                                <div class="summary-icon" style="background-color: #F59E0B;">
                                    <i class="fas fa-tools"></i>
                                </div>
                                <div class="summary-info">
                                    <h3>Job Orders</h3>
                                    <p><?= $jobOrderCount ?></p>
                                </div>
                            </div>

                            <div class="summary-card">
                                <div class="summary-icon" style="background-color: #2ecc71;">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="summary-info">
                                    <h3>Completed</h3>
                                    <p><?= $completedCount ?></p>
                                </div>
                            </div>

                            <div class="summary-card">
                                <div class="summary-icon" style="background-color: #f39c12;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="summary-info">
                                    <h3>Scheduled</h3>
                                    <p><?= $scheduledCount ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Filter Controls -->
                        <div class="filter-container">
                            <div class="filter-header">
                                <h3><i class="fas fa-filter"></i> Filter Schedules</h3>
                                <button id="resetScheduleFilters" class="btn btn-sm btn-default">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </div>
                            <div class="filter-body">
                                <div class="filter-row">
                                    <div class="filter-group">
                                        <label for="scheduleTypeFilter">Type:</label>
                                        <select id="scheduleTypeFilter" class="form-control input-sm">
                                            <option value="all">All Types</option>
                                            <option value="appointment">Appointments</option>
                                            <option value="job_order">Job Orders</option>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label for="scheduleStatusFilter">Status:</label>
                                        <select id="scheduleStatusFilter" class="form-control input-sm">
                                            <option value="all">All Statuses</option>
                                            <option value="completed">Completed</option>
                                            <option value="scheduled">Scheduled</option>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label for="scheduleSearchFilter">Search:</label>
                                        <input type="text" id="scheduleSearchFilter" class="form-control input-sm" placeholder="Search client, location...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="assessment-table-container">
                            <?php if (empty($schedules)): ?>
                                <div class="alert alert-info" style="text-align: center; padding: 30px;">
                                    <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 15px; display: block;"></i>
                                    <h3>No Schedules Found</h3>
                                    <p>No schedules have been assigned to this technician for this date.</p>
                                </div>
                            <?php else: ?>
                                <table class="assessment-table">
                                    <thead>
                                        <tr>
                                            <th class="col-id" width="8%">Time</th>
                                            <th class="col-status" width="12%">Type</th>
                                            <th class="col-client" width="20%">Client</th>
                                            <th class="col-technician" width="20%">Details</th>
                                            <th class="col-location" width="30%">Location</th>
                                            <th class="col-status" width="10%">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($schedules as $schedule): ?>
                                            <?php
                                            $isAppointment = $schedule['job_type'] === 'appointment';
                                            $isCompleted = $schedule['status'] === 'completed';
                                            $jobTypeClass = $isAppointment ? 'appointment' : 'job-order';
                                            $jobTypeIcon = $isAppointment ? '<i class="fas fa-calendar-check"></i>' : '<i class="fas fa-tools"></i>';
                                            $jobTypeText = $isAppointment ? 'Appointment' : 'Job Order';
                                            $jobDetails = $isAppointment ? $schedule['kind_of_place'] : $schedule['type_of_work'];
                                            $statusClass = $isCompleted ? 'completed' : 'scheduled';
                                            $statusIcon = $isCompleted ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-clock"></i>';
                                            $statusText = $isCompleted ? 'Completed' : 'Scheduled';
                                            $formattedTime = date('h:i A', strtotime($schedule['preferred_time']));
                                            ?>
                                            <tr>
                                                <td><?= $formattedTime ?></td>
                                                <td>
                                                    <span class="job-type-badge <?= $jobTypeClass ?>">
                                                        <?= $jobTypeIcon ?> <?= $jobTypeText ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($schedule['client_name']) ?></td>
                                                <td><?= htmlspecialchars($jobDetails) ?></td>
                                                <td class="truncate" title="<?= htmlspecialchars($schedule['location_address']) ?>">
                                                    <?= htmlspecialchars($schedule['location_address']) ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?= $statusClass ?>">
                                                        <?= $statusIcon ?> <?= $statusText ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Checklist Tab -->
                <div class="tab-content" id="checklist-tab">
                    <div class="content-card">
                        <h2 class="section-header">
                            <i class="fas fa-clipboard-list"></i> Tools & Equipment Checklist
                        </h2>

                        <!-- Checklist Summary -->
                        <?php if ($checklist): ?>
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
                                <div class="summary-icon" style="background-color: #EF4444;">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="summary-info">
                                    <h3>Items Missing</h3>
                                    <p><?= $checklist['total_items'] - $checklist['checked_count'] ?></p>
                                </div>
                            </div>

                            <div class="summary-card">
                                <div class="summary-icon" style="background-color: #F59E0B;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="summary-info">
                                    <h3>Completed At</h3>
                                    <p><?= date('h:i A', strtotime($checklist['created_at'])) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Filter Controls -->
                        <div class="filter-container">
                            <div class="filter-header">
                                <h3><i class="fas fa-filter"></i> Filter Checklist</h3>
                                <button id="resetChecklistFilters" class="btn btn-sm btn-default">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </div>
                            <div class="filter-body">
                                <div class="filter-row">
                                    <div class="filter-group">
                                        <label for="checklistCategoryFilter">Category:</label>
                                        <select id="checklistCategoryFilter" class="form-control input-sm">
                                            <option value="all">All Categories</option>
                                            <?php foreach (array_keys($tools) as $category): ?>
                                                <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label for="checklistStatusFilter">Status:</label>
                                        <select id="checklistStatusFilter" class="form-control input-sm">
                                            <option value="all">All Statuses</option>
                                            <option value="checked">Checked</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label for="checklistSearchFilter">Search:</label>
                                        <input type="text" id="checklistSearchFilter" class="form-control input-sm" placeholder="Search tools...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="assessment-table-container">
                            <?php if (!$checklist): ?>
                                <div class="alert alert-info" style="text-align: center; padding: 30px;">
                                    <i class="fas fa-clipboard" style="font-size: 2rem; margin-bottom: 15px; display: block;"></i>
                                    <h3>No Checklist Found</h3>
                                    <p>No checklist has been completed by the technician for this date.</p>
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
                                        <?php
                                        $toolCount = 0;
                                        foreach ($tools as $category => $categoryTools):
                                        ?>
                                            <tr>
                                                <td colspan="4" class="checklist-category-header">
                                                    <i class="fas <?= getCategoryIcon($category) ?>"></i> <?= $category ?>
                                                </td>
                                            </tr>
                                            <?php foreach ($categoryTools as $tool):
                                                $toolCount++;
                                                $isChecked = in_array($tool['id'], $checkedItems);
                                                $statusClass = $isChecked ? 'completed' : 'scheduled';
                                                $statusText = $isChecked ? 'Checked' : 'Missing';
                                                $statusIcon = $isChecked ?
                                                    '<i class="fas fa-check-circle"></i>' :
                                                    '<i class="fas fa-times-circle"></i>';
                                            ?>
                                            <tr>
                                                <td><?= $tool['id'] ?></td>
                                                <td>
                                                    <span class="status-badge <?= $statusClass ?>">
                                                        <?= $statusIcon ?> <?= $statusText ?>
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

        // Tab switching
        $('.tab-button').on('click', function() {
            const tab = $(this).data('tab');
            $('.tab-button').removeClass('active');
            $(this).addClass('active');
            $('.tab-content').removeClass('active');
            $(`#${tab}-tab`).addClass('active');
        });

        // Schedule Filtering
        $('#scheduleTypeFilter, #scheduleStatusFilter').on('change', function() {
            filterSchedules();
        });

        $('#scheduleSearchFilter').on('keyup', function() {
            filterSchedules();
        });

        $('#resetScheduleFilters').on('click', function() {
            $('#scheduleTypeFilter').val('all');
            $('#scheduleStatusFilter').val('all');
            $('#scheduleSearchFilter').val('');
            filterSchedules();
        });

        function filterSchedules() {
            const typeFilter = $('#scheduleTypeFilter').val();
            const statusFilter = $('#scheduleStatusFilter').val();
            const searchFilter = $('#scheduleSearchFilter').val().toLowerCase();

            // Get all rows except the header row
            const rows = $('#schedules-tab .assessment-table tbody tr');

            let visibleCount = 0;

            rows.each(function() {
                let show = true;
                const row = $(this);

                // Skip category headers in case they exist
                if (row.find('td[colspan]').length > 0) {
                    row.show();
                    return;
                }

                // Type filtering
                if (typeFilter !== 'all') {
                    const jobTypeCell = row.find('td:nth-child(2)');
                    const jobTypeText = jobTypeCell.text().toLowerCase();

                    if (typeFilter === 'appointment' && !jobTypeText.includes('appointment')) {
                        show = false;
                    } else if (typeFilter === 'job_order' && !jobTypeText.includes('job order')) {
                        show = false;
                    }
                }

                // Status filtering
                if (show && statusFilter !== 'all') {
                    const statusCell = row.find('td:nth-child(6)');
                    const statusText = statusCell.text().toLowerCase();

                    if (statusFilter === 'completed' && !statusText.includes('completed')) {
                        show = false;
                    } else if (statusFilter === 'scheduled' && !statusText.includes('scheduled')) {
                        show = false;
                    }
                }

                // Search filtering
                if (show && searchFilter !== '') {
                    const clientCell = row.find('td:nth-child(3)');
                    const detailsCell = row.find('td:nth-child(4)');
                    const locationCell = row.find('td:nth-child(5)');

                    const clientText = clientCell.text().toLowerCase();
                    const detailsText = detailsCell.text().toLowerCase();
                    const locationText = locationCell.text().toLowerCase();

                    if (!clientText.includes(searchFilter) &&
                        !detailsText.includes(searchFilter) &&
                        !locationText.includes(searchFilter)) {
                        show = false;
                    }
                }

                if (show) {
                    row.show();
                    visibleCount++;
                } else {
                    row.hide();
                }
            });

            // Show/hide no results message
            if (visibleCount === 0 && rows.length > 0) {
                if ($('#schedules-tab .no-filter-results').length === 0) {
                    $('#schedules-tab .assessment-table').after(
                        '<div class="alert alert-info no-filter-results" style="margin-top: 15px;">' +
                        '<i class="fas fa-filter"></i> ' +
                        'No schedules match the selected filters. Try adjusting your filters or ' +
                        '<a href="#" id="clearScheduleFilters">clear all filters</a>.' +
                        '</div>'
                    );

                    $('#clearScheduleFilters').on('click', function(e) {
                        e.preventDefault();
                        $('#resetScheduleFilters').click();
                    });
                } else {
                    $('#schedules-tab .no-filter-results').show();
                }
            } else {
                $('#schedules-tab .no-filter-results').hide();
            }

            // Update summary cards with filtered counts
            updateScheduleSummary(rows.filter(':visible'));
        }

        function updateScheduleSummary(visibleRows) {
            let appointmentCount = 0;
            let jobOrderCount = 0;
            let completedCount = 0;
            let scheduledCount = 0;

            visibleRows.each(function() {
                const row = $(this);

                // Skip category headers
                if (row.find('td[colspan]').length > 0) {
                    return;
                }

                const jobTypeCell = row.find('td:nth-child(2)');
                const statusCell = row.find('td:nth-child(6)');

                const jobTypeText = jobTypeCell.text().toLowerCase();
                const statusText = statusCell.text().toLowerCase();

                if (jobTypeText.includes('appointment')) {
                    appointmentCount++;
                } else if (jobTypeText.includes('job order')) {
                    jobOrderCount++;
                }

                if (statusText.includes('completed')) {
                    completedCount++;
                } else if (statusText.includes('scheduled')) {
                    scheduledCount++;
                }
            });

            // Update the summary cards
            $('#schedules-tab .summary-card:nth-child(1) .summary-info p').text(appointmentCount);
            $('#schedules-tab .summary-card:nth-child(2) .summary-info p').text(jobOrderCount);
            $('#schedules-tab .summary-card:nth-child(3) .summary-info p').text(completedCount);
            $('#schedules-tab .summary-card:nth-child(4) .summary-info p').text(scheduledCount);
        }

        // Checklist Filtering
        $('#checklistCategoryFilter, #checklistStatusFilter').on('change', function() {
            filterChecklist();
        });

        $('#checklistSearchFilter').on('keyup', function() {
            filterChecklist();
        });

        $('#resetChecklistFilters').on('click', function() {
            $('#checklistCategoryFilter').val('all');
            $('#checklistStatusFilter').val('all');
            $('#checklistSearchFilter').val('');
            filterChecklist();
        });

        function filterChecklist() {
            const categoryFilter = $('#checklistCategoryFilter').val();
            const statusFilter = $('#checklistStatusFilter').val();
            const searchFilter = $('#checklistSearchFilter').val().toLowerCase();

            // Get all rows except the header row
            const rows = $('#checklist-tab .assessment-table tbody tr');
            let currentCategory = null;
            let visibleCount = 0;
            let visibleInCategory = {};

            rows.each(function() {
                let show = true;
                const row = $(this);

                // Handle category headers
                if (row.find('td[colspan]').length > 0) {
                    currentCategory = row.text().trim();
                    visibleInCategory[currentCategory] = 0;

                    // Don't apply filters to category headers yet
                    return;
                }

                // Category filtering
                if (categoryFilter !== 'all' && currentCategory) {
                    if (!currentCategory.includes(categoryFilter)) {
                        show = false;
                    }
                }

                // Status filtering
                if (show && statusFilter !== 'all') {
                    const statusCell = row.find('td:nth-child(2)');
                    const statusText = statusCell.text().toLowerCase();

                    if (statusFilter === 'checked' && !statusText.includes('checked')) {
                        show = false;
                    } else if (statusFilter === 'missing' && !statusText.includes('missing')) {
                        show = false;
                    }
                }

                // Search filtering
                if (show && searchFilter !== '') {
                    const nameCell = row.find('td:nth-child(3)');
                    const descCell = row.find('td:nth-child(4)');

                    const nameText = nameCell.text().toLowerCase();
                    const descText = descCell.text().toLowerCase();

                    if (!nameText.includes(searchFilter) && !descText.includes(searchFilter)) {
                        show = false;
                    }
                }

                if (show) {
                    row.show();
                    visibleCount++;
                    if (currentCategory) {
                        visibleInCategory[currentCategory]++;
                    }
                } else {
                    row.hide();
                }
            });

            // Now handle category headers visibility based on their items
            rows.each(function() {
                const row = $(this);

                if (row.find('td[colspan]').length > 0) {
                    const categoryText = row.text().trim();
                    if (visibleInCategory[categoryText] > 0) {
                        row.show();
                    } else {
                        row.hide();
                    }
                }
            });

            // Show/hide no results message
            if (visibleCount === 0 && rows.length > 0) {
                if ($('#checklist-tab .no-filter-results').length === 0) {
                    $('#checklist-tab .assessment-table').after(
                        '<div class="alert alert-info no-filter-results" style="margin-top: 15px;">' +
                        '<i class="fas fa-filter"></i> ' +
                        'No items match the selected filters. Try adjusting your filters or ' +
                        '<a href="#" id="clearChecklistFilters">clear all filters</a>.' +
                        '</div>'
                    );

                    $('#clearChecklistFilters').on('click', function(e) {
                        e.preventDefault();
                        $('#resetChecklistFilters').click();
                    });
                } else {
                    $('#checklist-tab .no-filter-results').show();
                }
            } else {
                $('#checklist-tab .no-filter-results').hide();
            }

            // Update summary cards with filtered counts
            updateChecklistSummary(rows.filter(':visible'));
        }

        function updateChecklistSummary(visibleRows) {
            let checkedCount = 0;
            let missingCount = 0;
            let totalCount = 0;

            visibleRows.each(function() {
                const row = $(this);

                // Skip category headers
                if (row.find('td[colspan]').length > 0) {
                    return;
                }

                totalCount++;

                const statusCell = row.find('td:nth-child(2)');
                const statusText = statusCell.text().toLowerCase();

                if (statusText.includes('checked')) {
                    checkedCount++;
                } else if (statusText.includes('missing')) {
                    missingCount++;
                }
            });

            // Calculate completion percentage
            const percentage = totalCount > 0 ? Math.round((checkedCount / totalCount) * 100) : 0;

            // Update the summary cards
            $('#checklist-tab .summary-card:nth-child(1) .summary-info p').text(percentage + '%');
            $('#checklist-tab .summary-card:nth-child(2) .summary-info p').text(checkedCount);
            $('#checklist-tab .summary-card:nth-child(3) .summary-info p').text(missingCount);
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
