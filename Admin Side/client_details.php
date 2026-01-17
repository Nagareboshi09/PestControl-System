<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: ../SignIn.php");
    exit;
}
include '../db_connect.php';
include '../notification_functions.php';

// Check if client_id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: clients.php");
    exit;
}

$clientId = (int)$_GET['id'];

// Get client details
$clientQuery = "SELECT * FROM clients WHERE client_id = ?";
$stmt = $conn->prepare($clientQuery);
$stmt->bind_param("i", $clientId);
$stmt->execute();
$clientResult = $stmt->get_result();

if ($clientResult->num_rows === 0) {
    header("Location: clients.php");
    exit;
}

$client = $clientResult->fetch_assoc();
$clientName = $client['first_name'] . ' ' . $client['last_name'];

// Clean location address (remove coordinates)
if (!empty($client['location_address'])) {
    $cleanAddress = preg_replace('/\[[-\d\.]+,[-\d\.]+\]$/', '', $client['location_address']);
} else {
    $cleanAddress = 'Not specified';
}

// Get client's appointments
$appointmentsQuery = "SELECT
                        a.appointment_id,
                        a.preferred_date,
                        a.preferred_time,
                        a.location_address,
                        a.status,
                        a.created_at,
                        a.pest_problems,
                        t.tech_fname,
                        t.tech_lname
                    FROM appointments a
                    LEFT JOIN technicians t ON a.technician_id = t.technician_id
                    WHERE a.client_id = ?
                    ORDER BY a.preferred_date DESC";
$stmt = $conn->prepare($appointmentsQuery);
$stmt->bind_param("i", $clientId);
$stmt->execute();
$appointmentsResult = $stmt->get_result();
$appointments = [];
while ($row = $appointmentsResult->fetch_assoc()) {
    $appointments[] = $row;
}

// Get client's job orders
$jobOrdersQuery = "SELECT
                    jo.job_order_id,
                    ar.appointment_id,
                    jo.preferred_date as job_date,
                    jo.preferred_time as job_time,
                    jo.status,
                    jo.created_at,
                    t.tech_fname,
                    t.tech_lname
                FROM job_order jo
                JOIN assessment_report ar ON jo.report_id = ar.report_id
                JOIN appointments a ON ar.appointment_id = a.appointment_id
                LEFT JOIN job_order_technicians jot ON jo.job_order_id = jot.job_order_id
                LEFT JOIN technicians t ON jot.technician_id = t.technician_id
                WHERE a.client_id = ?
                ORDER BY jo.preferred_date DESC";
$stmt = $conn->prepare($jobOrdersQuery);
$stmt->bind_param("i", $clientId);
$stmt->execute();
$jobOrdersResult = $stmt->get_result();
$jobOrders = [];
while ($row = $jobOrdersResult->fetch_assoc()) {
    $jobOrders[] = $row;
}

// Get client's inspection reports
$inspectionReportsQuery = "SELECT DISTINCT
                            ar.report_id as inspection_id,
                            jo.job_order_id,
                            ar.created_at as inspection_date,
                            ar.pest_types as pest_observation,
                            ar.problem_area as infestation_area,
                            ar.created_at,
                            t.tech_fname,
                            t.tech_lname
                        FROM assessment_report ar
                        JOIN appointments a ON ar.appointment_id = a.appointment_id
                        LEFT JOIN job_order jo ON ar.report_id = jo.report_id
                        LEFT JOIN job_order_technicians jot ON jo.job_order_id = jot.job_order_id
                        LEFT JOIN technicians t ON jot.technician_id = t.technician_id
                        WHERE a.client_id = ?
                        GROUP BY ar.report_id
                        ORDER BY ar.created_at DESC";
$stmt = $conn->prepare($inspectionReportsQuery);
$stmt->bind_param("i", $clientId);
$stmt->execute();
$inspectionReportsResult = $stmt->get_result();
$inspectionReports = [];
while ($row = $inspectionReportsResult->fetch_assoc()) {
    $inspectionReports[] = $row;
}

// Calculate statistics
$totalAppointments = count($appointments);
$completedAppointments = 0;
$pendingAppointments = 0;
$cancelledAppointments = 0;

foreach ($appointments as $appointment) {
    if ($appointment['status'] === 'completed') {
        $completedAppointments++;
    } elseif ($appointment['status'] === 'pending' || $appointment['status'] === 'scheduled') {
        $pendingAppointments++;
    } elseif ($appointment['status'] === 'cancelled') {
        $cancelledAppointments++;
    }
}

$totalJobOrders = count($jobOrders);
$completedJobOrders = 0;
$pendingJobOrders = 0;

foreach ($jobOrders as $jobOrder) {
    if ($jobOrder['status'] === 'completed') {
        $completedJobOrders++;
    } else {
        $pendingJobOrders++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($clientName) ?>'s Details - MacJ Pest Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/admin-base.css">
    <link rel="stylesheet" href="css/technicians-page.css">
    <link rel="stylesheet" href="css/technician-jobs.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <style>
        /* Client details specific styles */
        .client-details-content {
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background-color: #F3F4F6;
            border-radius: 4px;
            color: #1F2937;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background-color: #E5E7EB;
            text-decoration: none;
            color: #1F2937;
        }

        .back-button i {
            margin-right: 8px;
        }

        .client-info {
            display: flex;
            align-items: center;
        }

        .client-avatar-placeholder {
            width: 80px;
            height: 80px;
            background-color: #E5E7EB;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #9CA3AF;
            margin-right: 20px;
        }

        .client-details {
            flex: 1;
        }

        .client-details h1 {
            margin: 0 0 5px 0;
            font-size: 1.8rem;
            color: #1F2937;
        }

        .client-meta {
            display: flex;
            gap: 20px;
            color: #6B7280;
        }

        .client-meta span {
            display: flex;
            align-items: center;
        }

        .client-meta i {
            margin-right: 8px;
            color: #3B82F6;
        }

        .summary-section {
            margin-bottom: 30px;
        }

        .summary-section h2 {
            font-size: 1.5rem;
            color: #1F2937;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .summary-section h2 i {
            margin-right: 10px;
            color: #3B82F6;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .summary-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 20px;
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.5rem;
            color: white;
        }

        .appointments {
            background-color: #3B82F6;
        }

        .completed {
            background-color: #10B981;
        }

        .pending {
            background-color: #F59E0B;
        }

        .cancelled {
            background-color: #EF4444;
        }

        .job-orders {
            background-color: #8B5CF6;
        }

        .summary-data {
            flex: 1;
        }

        .summary-value {
            font-size: 1.8rem;
            font-weight: 600;
            color: #1F2937;
            line-height: 1;
            margin-bottom: 5px;
        }

        .summary-label {
            color: #6B7280;
            font-size: 0.9rem;
        }

        .content-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .section-header {
            padding: 15px 20px;
            background-color: #F9FAFB;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1F2937;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            color: #3B82F6;
        }

        .section-content {
            padding: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: 600;
            color: #4B5563;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .info-value {
            color: #1F2937;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table th {
            background-color: #F9FAFB;
            color: #4B5563;
            font-weight: 600;
            text-align: left;
            padding: 12px 15px;
            border-bottom: 1px solid #E5E7EB;
        }

        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #E5E7EB;
            color: #1F2937;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover td {
            background-color: #F9FAFB;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-completed {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10B981;
        }

        .status-pending, .status-scheduled {
            background-color: rgba(245, 158, 11, 0.1);
            color: #F59E0B;
        }

        .status-cancelled {
            background-color: rgba(239, 68, 68, 0.1);
            color: #EF4444;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #6B7280;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #E5E7EB;
        }

        .empty-state p {
            margin: 0;
        }

        /* Filter styles */
        .filter-toggle {
            background-color: #F3F4F6;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
            color: #4B5563;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-toggle:hover {
            background-color: #E5E7EB;
        }

        .filter-toggle i {
            color: #3B82F6;
        }

        .filter-container {
            background-color: #F9FAFB;
            border-radius: 0 0 8px 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-top: 1px solid #E5E7EB;
            display: none;
        }

        .filter-container.show {
            display: block;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 0.85rem;
            color: #4B5563;
        }

        .filter-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #D1D5DB;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .client-info {
                flex-direction: column;
                align-items: flex-start;
            }

            .client-avatar-placeholder {
                margin-bottom: 15px;
            }

            .client-meta {
                flex-direction: column;
                gap: 10px;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .filter-row {
                flex-direction: column;
                gap: 10px;
            }

            .filter-group {
                width: 100%;
            }
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
                    <li><a href="technicians.php"><i class="fas fa-user-md"></i> Technicians</a></li>
                    <li class="active"><a href="clients.php"><i class="fas fa-users"></i> Clients</a></li>
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
            <div class="client-details-content">
                <!-- Back button and client info -->
                <div class="page-header">
                    <a href="clients.php" class="back-button">
                        <i class="fas fa-arrow-left"></i> Back to Clients
                    </a>
                    <div class="client-info">
                        <div class="client-avatar-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="client-details">
                            <h1><?= htmlspecialchars($clientName) ?></h1>
                            <div class="client-meta">
                                <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($client['email']) ?></span>
                                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($client['contact_number']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Client Summary Cards -->
                <div class="summary-section">
                    <h2><i class="fas fa-chart-pie"></i> Client Summary</h2>
                    <div class="summary-cards">
                        <div class="summary-card">
                            <div class="summary-icon appointments">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="summary-data">
                                <div class="summary-value"><?= $totalAppointments ?></div>
                                <div class="summary-label">Total Appointments</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon completed">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="summary-data">
                                <div class="summary-value"><?= $completedAppointments ?></div>
                                <div class="summary-label">Completed</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon pending">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="summary-data">
                                <div class="summary-value"><?= $pendingAppointments ?></div>
                                <div class="summary-label">Pending/Scheduled</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon job-orders">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="summary-data">
                                <div class="summary-value"><?= $totalJobOrders ?></div>
                                <div class="summary-label">Job Orders</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Client Information Section -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title"><i class="fas fa-user"></i> Client Information</h3>
                    </div>
                    <div class="section-content">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Client ID</div>
                                <div class="info-value"><?= htmlspecialchars($client['client_id']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?= htmlspecialchars($clientName) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email Address</div>
                                <div class="info-value"><?= htmlspecialchars($client['email']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Contact Number</div>
                                <div class="info-value"><?= htmlspecialchars($client['contact_number']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Location Address</div>
                                <div class="info-value"><?= htmlspecialchars($cleanAddress) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Type of Place</div>
                                <div class="info-value"><?= htmlspecialchars($client['type_of_place'] ?? 'Not specified') ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Registered Date</div>
                                <div class="info-value"><?= date('F d, Y', strtotime($client['registered_at'])) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Account Status</div>
                                <div class="info-value">
                                    <span class="status-badge status-completed">
                                        Active
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Appointments Section -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title"><i class="fas fa-calendar-check"></i> Appointment History</h3>
                        <button class="filter-toggle" data-target="appointment-filters">
                            <i class="fas fa-filter"></i> Filters
                        </button>
                    </div>

                    <!-- Appointment Filters -->
                    <div id="appointment-filters" class="filter-container">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="appointment-date-from">Date From:</label>
                                <input type="date" id="appointment-date-from" class="filter-input">
                            </div>
                            <div class="filter-group">
                                <label for="appointment-date-to">Date To:</label>
                                <input type="date" id="appointment-date-to" class="filter-input">
                            </div>
                            <div class="filter-group">
                                <label for="appointment-status">Status:</label>
                                <select id="appointment-status" class="filter-input">
                                    <option value="">All</option>
                                    <option value="completed">Completed</option>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="pending">Pending</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="appointment-pest">Pest Problem:</label>
                                <select id="appointment-pest" class="filter-input">
                                    <option value="">All</option>
                                    <?php
                                    // Get unique pest problems
                                    $uniquePestProblems = [];
                                    foreach ($appointments as $appointment) {
                                        if (!empty($appointment['pest_problems'])) {
                                            $problems = explode(',', $appointment['pest_problems']);
                                            foreach ($problems as $problem) {
                                                $problem = trim($problem);
                                                if (!empty($problem) && !in_array($problem, $uniquePestProblems)) {
                                                    $uniquePestProblems[] = $problem;
                                                }
                                            }
                                        }
                                    }
                                    sort($uniquePestProblems);
                                    foreach ($uniquePestProblems as $problem):
                                    ?>
                                        <option value="<?= htmlspecialchars($problem) ?>"><?= htmlspecialchars($problem) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button id="apply-appointment-filters" class="btn btn-primary btn-sm">Apply Filters</button>
                            <button id="reset-appointment-filters" class="btn btn-default btn-sm">Reset</button>
                        </div>
                    </div>

                    <div class="section-content">
                        <?php if (count($appointments) > 0): ?>
                            <div class="table-responsive">
                                <table id="appointments-table" class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Location</th>
                                            <th>Pest Problems</th>
                                            <th>Technician</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $appointment): ?>
                                            <tr data-date="<?= $appointment['preferred_date'] ?>"
                                                data-status="<?= $appointment['status'] ?>"
                                                data-pest="<?= htmlspecialchars($appointment['pest_problems'] ?? '') ?>">
                                                <td><?= date('M d, Y', strtotime($appointment['preferred_date'])) ?></td>
                                                <td><?= $appointment['preferred_time'] ?></td>
                                                <td>
                                                    <?php
                                                    if (!empty($appointment['location_address'])) {
                                                        echo htmlspecialchars(preg_replace('/\[[-\d\.]+,[-\d\.]+\]$/', '', $appointment['location_address']));
                                                    } else {
                                                        echo 'Not specified';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    if (!empty($appointment['pest_problems'])) {
                                                        $pestProblems = explode(',', $appointment['pest_problems']);
                                                        foreach ($pestProblems as $index => $problem) {
                                                            echo htmlspecialchars(trim($problem));
                                                            if ($index < count($pestProblems) - 1) {
                                                                echo ', ';
                                                            }
                                                        }
                                                    } else {
                                                        echo 'Not specified';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    if (!empty($appointment['tech_fname']) && !empty($appointment['tech_lname'])) {
                                                        echo htmlspecialchars($appointment['tech_fname'] . ' ' . $appointment['tech_lname']);
                                                    } else {
                                                        echo 'Not assigned';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?= $appointment['status'] ?>">
                                                        <?= ucfirst($appointment['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No appointment history found for this client.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Job Orders Section -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title"><i class="fas fa-clipboard-list"></i> Job Orders</h3>
                        <button class="filter-toggle" data-target="joborder-filters">
                            <i class="fas fa-filter"></i> Filters
                        </button>
                    </div>

                    <!-- Job Order Filters -->
                    <div id="joborder-filters" class="filter-container">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="joborder-date-from">Date From:</label>
                                <input type="date" id="joborder-date-from" class="filter-input">
                            </div>
                            <div class="filter-group">
                                <label for="joborder-date-to">Date To:</label>
                                <input type="date" id="joborder-date-to" class="filter-input">
                            </div>
                            <div class="filter-group">
                                <label for="joborder-status">Status:</label>
                                <select id="joborder-status" class="filter-input">
                                    <option value="">All</option>
                                    <option value="completed">Completed</option>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="joborder-technician">Technician:</label>
                                <select id="joborder-technician" class="filter-input">
                                    <option value="">All</option>
                                    <?php
                                    // Get unique technicians
                                    $uniqueTechnicians = [];
                                    foreach ($jobOrders as $jobOrder) {
                                        if (!empty($jobOrder['tech_fname']) && !empty($jobOrder['tech_lname'])) {
                                            $techName = $jobOrder['tech_fname'] . ' ' . $jobOrder['tech_lname'];
                                            if (!in_array($techName, $uniqueTechnicians)) {
                                                $uniqueTechnicians[] = $techName;
                                            }
                                        }
                                    }
                                    sort($uniqueTechnicians);
                                    foreach ($uniqueTechnicians as $techName):
                                    ?>
                                        <option value="<?= htmlspecialchars($techName) ?>"><?= htmlspecialchars($techName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button id="apply-joborder-filters" class="btn btn-primary btn-sm">Apply Filters</button>
                            <button id="reset-joborder-filters" class="btn btn-default btn-sm">Reset</button>
                        </div>
                    </div>

                    <div class="section-content">
                        <?php if (count($jobOrders) > 0): ?>
                            <div class="table-responsive">
                                <table id="joborders-table" class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Job Order ID</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Technician</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($jobOrders as $jobOrder): ?>
                                            <?php
                                            $techName = '';
                                            if (!empty($jobOrder['tech_fname']) && !empty($jobOrder['tech_lname'])) {
                                                $techName = $jobOrder['tech_fname'] . ' ' . $jobOrder['tech_lname'];
                                            }
                                            ?>
                                            <tr data-date="<?= $jobOrder['job_date'] ?>"
                                                data-status="<?= $jobOrder['status'] ?>"
                                                data-technician="<?= htmlspecialchars($techName) ?>">
                                                <td><?= $jobOrder['job_order_id'] ?></td>
                                                <td><?= date('M d, Y', strtotime($jobOrder['job_date'])) ?></td>
                                                <td><?= $jobOrder['job_time'] ?></td>
                                                <td>
                                                    <?php
                                                    if (!empty($techName)) {
                                                        echo htmlspecialchars($techName);
                                                    } else {
                                                        echo 'Not assigned';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?= $jobOrder['status'] ?>">
                                                        <?= ucfirst($jobOrder['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-clipboard"></i>
                                <p>No job orders found for this client.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Assessment Reports Section -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title"><i class="fas fa-clipboard-check"></i> Assessment Reports</h3>
                        <button class="filter-toggle" data-target="assessment-filters">
                            <i class="fas fa-filter"></i> Filters
                        </button>
                    </div>

                    <!-- Assessment Report Filters -->
                    <div id="assessment-filters" class="filter-container">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="assessment-date-from">Date From:</label>
                                <input type="date" id="assessment-date-from" class="filter-input">
                            </div>
                            <div class="filter-group">
                                <label for="assessment-date-to">Date To:</label>
                                <input type="date" id="assessment-date-to" class="filter-input">
                            </div>
                            <div class="filter-group">
                                <label for="assessment-pest">Pest Type:</label>
                                <select id="assessment-pest" class="filter-input">
                                    <option value="">All</option>
                                    <?php
                                    // Get unique pest observations
                                    $uniquePestTypes = [];
                                    foreach ($inspectionReports as $report) {
                                        if (!empty($report['pest_observation'])) {
                                            $pestTypes = explode(',', $report['pest_observation']);
                                            foreach ($pestTypes as $type) {
                                                $type = trim($type);
                                                if (!empty($type) && !in_array($type, $uniquePestTypes)) {
                                                    $uniquePestTypes[] = $type;
                                                }
                                            }
                                        }
                                    }
                                    sort($uniquePestTypes);
                                    foreach ($uniquePestTypes as $type):
                                    ?>
                                        <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button id="apply-assessment-filters" class="btn btn-primary btn-sm">Apply Filters</button>
                            <button id="reset-assessment-filters" class="btn btn-default btn-sm">Reset</button>
                        </div>
                    </div>

                    <div class="section-content">
                        <?php if (count($inspectionReports) > 0): ?>
                            <div class="table-responsive">
                                <table id="assessment-table" class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Report ID</th>
                                            <th>Date</th>
                                            <th>Pest Types</th>
                                            <th>Problem Area</th>
                                            <th>Technician</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inspectionReports as $report): ?>
                                            <tr data-date="<?= $report['inspection_date'] ?>"
                                                data-pest="<?= htmlspecialchars($report['pest_observation'] ?? '') ?>">
                                                <td><?= $report['inspection_id'] ?></td>
                                                <td><?= date('M d, Y', strtotime($report['inspection_date'])) ?></td>
                                                <td>
                                                    <?php
                                                    if (!empty($report['pest_observation'])) {
                                                        $pestObservations = explode(',', $report['pest_observation']);
                                                        foreach ($pestObservations as $index => $observation) {
                                                            echo htmlspecialchars(trim($observation));
                                                            if ($index < count($pestObservations) - 1) {
                                                                echo ', ';
                                                            }
                                                        }
                                                    } else {
                                                        echo 'Not specified';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?= htmlspecialchars($report['infestation_area'] ?? 'Not specified') ?></td>
                                                <td>
                                                    <?php
                                                    if (!empty($report['tech_fname']) && !empty($report['tech_lname'])) {
                                                        echo htmlspecialchars($report['tech_fname'] . ' ' . $report['tech_lname']);
                                                    } else {
                                                        echo 'Not assigned';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-clipboard-check"></i>
                                <p>No assessment reports found for this client.</p>
                            </div>
                        <?php endif; ?>
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

        // Format dates and times
        $('.data-table td:nth-child(1), .data-table td:nth-child(2)').each(function() {
            const timeText = $(this).text();
            if (timeText && timeText.includes(':')) {
                $(this).text(formatTime(timeText));
            }
        });

        // Helper function to format time
        function formatTime(timeStr) {
            // Check if it's a time format with colon
            if (timeStr.includes(':')) {
                // Convert 24-hour time to 12-hour format
                const timeParts = timeStr.split(':');
                let hours = parseInt(timeParts[0]);
                const minutes = timeParts[1];
                const ampm = hours >= 12 ? 'PM' : 'AM';

                hours = hours % 12;
                hours = hours ? hours : 12; // Convert 0 to 12

                return `${hours}:${minutes} ${ampm}`;
            }
            return timeStr;
        }

        // Initialize filter toggles
        $('.filter-toggle').on('click', function() {
            const targetId = $(this).data('target');
            $(`#${targetId}`).toggleClass('show');
        });

        // Appointment filters
        $('#apply-appointment-filters').on('click', function() {
            filterAppointments();
        });

        $('#reset-appointment-filters').on('click', function() {
            // Reset filter inputs
            $('#appointment-date-from, #appointment-date-to').val('');
            $('#appointment-status, #appointment-pest').val('');

            // Show all rows
            $('#appointments-table tbody tr').show();
        });

        function filterAppointments() {
            const dateFrom = $('#appointment-date-from').val();
            const dateTo = $('#appointment-date-to').val();
            const status = $('#appointment-status').val();
            const pest = $('#appointment-pest').val();

            $('#appointments-table tbody tr').each(function() {
                let show = true;
                const rowDate = $(this).data('date');
                const rowStatus = $(this).data('status');
                const rowPest = $(this).data('pest');

                // Filter by date range
                if (dateFrom && rowDate < dateFrom) {
                    show = false;
                }
                if (dateTo && rowDate > dateTo) {
                    show = false;
                }

                // Filter by status
                if (status && rowStatus !== status) {
                    show = false;
                }

                // Filter by pest problem
                if (pest && !rowPest.includes(pest)) {
                    show = false;
                }

                // Show or hide the row
                if (show) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }

        // Job Order filters
        $('#apply-joborder-filters').on('click', function() {
            filterJobOrders();
        });

        $('#reset-joborder-filters').on('click', function() {
            // Reset filter inputs
            $('#joborder-date-from, #joborder-date-to').val('');
            $('#joborder-status, #joborder-technician').val('');

            // Show all rows
            $('#joborders-table tbody tr').show();
        });

        function filterJobOrders() {
            const dateFrom = $('#joborder-date-from').val();
            const dateTo = $('#joborder-date-to').val();
            const status = $('#joborder-status').val();
            const technician = $('#joborder-technician').val();

            $('#joborders-table tbody tr').each(function() {
                let show = true;
                const rowDate = $(this).data('date');
                const rowStatus = $(this).data('status');
                const rowTechnician = $(this).data('technician');

                // Filter by date range
                if (dateFrom && rowDate < dateFrom) {
                    show = false;
                }
                if (dateTo && rowDate > dateTo) {
                    show = false;
                }

                // Filter by status
                if (status && rowStatus !== status) {
                    show = false;
                }

                // Filter by technician
                if (technician && rowTechnician !== technician) {
                    show = false;
                }

                // Show or hide the row
                if (show) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }

        // Assessment Report filters
        $('#apply-assessment-filters').on('click', function() {
            filterAssessmentReports();
        });

        $('#reset-assessment-filters').on('click', function() {
            // Reset filter inputs
            $('#assessment-date-from, #assessment-date-to').val('');
            $('#assessment-pest').val('');

            // Show all rows
            $('#assessment-table tbody tr').show();
        });

        function filterAssessmentReports() {
            const dateFrom = $('#assessment-date-from').val();
            const dateTo = $('#assessment-date-to').val();
            const pest = $('#assessment-pest').val();

            $('#assessment-table tbody tr').each(function() {
                let show = true;
                const rowDate = $(this).data('date');
                const rowPest = $(this).data('pest');

                // Filter by date range
                if (dateFrom && rowDate < dateFrom) {
                    show = false;
                }
                if (dateTo && rowDate > dateTo) {
                    show = false;
                }

                // Filter by pest type
                if (pest && !rowPest.includes(pest)) {
                    show = false;
                }

                // Show or hide the row
                if (show) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
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