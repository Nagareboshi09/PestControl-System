<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}
require_once '../db_connect.php';
require_once '../notification_functions.php';

// Get technician ID from URL parameter
$technician_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate technician ID
if ($technician_id <= 0) {
    header("Location: technicians.php");
    exit;
}

// Get technician details
$techStmt = $conn->prepare("SELECT * FROM technicians WHERE technician_id = ?");
$techStmt->bind_param("i", $technician_id);
$techStmt->execute();
$techResult = $techStmt->get_result();

if ($techResult->num_rows === 0) {
    header("Location: technicians.php");
    exit;
}

$technician = $techResult->fetch_assoc();

// Check if the table has 'availability_id' or 'id' column
$checkColumnQuery = "SHOW COLUMNS FROM technician_availability LIKE 'availability_id'";
$columnResult = $conn->query($checkColumnQuery);
$hasAvailabilityIdColumn = $columnResult->num_rows > 0;

// Get technician's weekly availability
$weeklyAvailabilityStmt = $conn->prepare("
    SELECT * FROM technician_availability
    WHERE technician_id = ? AND specific_date IS NULL
    ORDER BY day_of_week
");
$weeklyAvailabilityStmt->bind_param("i", $technician_id);
$weeklyAvailabilityStmt->execute();
$weeklyAvailabilityResult = $weeklyAvailabilityStmt->get_result();

$weeklyAvailability = [];
while ($row = $weeklyAvailabilityResult->fetch_assoc()) {
    // Ensure both id and availability_id are available
    if ($hasAvailabilityIdColumn && !isset($row['id'])) {
        $row['id'] = $row['availability_id'];
    } elseif (!$hasAvailabilityIdColumn && !isset($row['availability_id'])) {
        $row['availability_id'] = $row['id'];
    }
    $weeklyAvailability[] = $row;
}

// Get technician's specific date availability
$specificAvailabilityStmt = $conn->prepare("
    SELECT * FROM technician_availability
    WHERE technician_id = ? AND specific_date IS NOT NULL
    ORDER BY specific_date
");
$specificAvailabilityStmt->bind_param("i", $technician_id);
$specificAvailabilityStmt->execute();
$specificAvailabilityResult = $specificAvailabilityStmt->get_result();

$specificAvailability = [];
while ($row = $specificAvailabilityResult->fetch_assoc()) {
    // Ensure both id and availability_id are available
    if ($hasAvailabilityIdColumn && !isset($row['id'])) {
        $row['id'] = $row['availability_id'];
    } elseif (!$hasAvailabilityIdColumn && !isset($row['availability_id'])) {
        $row['availability_id'] = $row['id'];
    }
    $specificAvailability[] = $row;
}

// Day of week names
$dayNames = [
    0 => 'Sunday',
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Availability - MacJ Pest Control</title>
    <link rel="stylesheet" href="css/tools-equipment-page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <style>
        /* Additional notification styles for Admin Side */
        .notification-container {
            position: relative;
            margin-right: 20px;
            cursor: pointer;
        }

        .notification-icon {
            font-size: 1.5rem;
            color: var(--primary-color);
            transition: color 0.3s ease;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #e74c3c;
            color: white;
            font-size: 0.75rem;
            font-weight: bold;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 350px;
            max-height: 400px;
            overflow-y: auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            display: none;
        }

        .notification-dropdown.show {
            display: block;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-color);
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-light);
        }

        /* Notification list styles */
        .notification-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .notification-item {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .notification-item:hover {
            background-color: #f8f9fa;
        }

        .notification-item.unread {
            background-color: #f0f7ff;
        }

        .notification-item.unread:hover {
            background-color: #e6f0ff;
        }

        .notification-info {
            display: flex;
            flex-direction: column;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }

        .notification-desc {
            font-size: 0.875rem;
            color: #555;
            margin-bottom: 5px;
        }

        .notification-time {
            font-size: 0.75rem;
            color: #777;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .viewed-indicator {
            font-size: 0.75rem;
            color: #28a745;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .no-notifications {
            padding: 15px;
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }

        .notification-header h3 {
            margin: 0;
            font-size: 16px;
            color: #333;
        }

        .mark-all-read {
            font-size: 12px;
            color: #007bff;
            cursor: pointer;
            text-decoration: underline;
        }

        /* Availability specific styles */
        .availability-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .availability-section h3 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .availability-section p {
            color: #555;
            margin-bottom: 15px;
        }

        .availability-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .availability-table th {
            background-color: #f5f5f5;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ddd;
        }

        .availability-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        .availability-table tr:hover {
            background-color: #f9f9f9;
        }

        .availability-actions {
            display: flex;
            gap: 5px;
        }

        .btn-edit, .btn-delete {
            padding: 5px 10px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-edit {
            background-color: #007bff;
            color: white;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
        }

        .btn-edit i, .btn-delete i {
            margin-right: 5px;
        }

        .btn-add {
            margin-top: 15px;
            padding: 8px 16px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add:hover {
            background-color: var(--secondary-color);
        }

        .availability-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-available {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .status-unavailable {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: var(--text-color);
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            color: var(--primary-color);
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1050;
            overflow-y: auto;
        }

        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            position: relative;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: -20px -20px 20px -20px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--text-light);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            transition: var(--transition);
        }

        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-cancel {
            background-color: #f8f9fa;
            color: var(--text-color);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-save {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-save:hover {
            background-color: var(--secondary-color);
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
                <?php
                // Check if profile picture exists
                $staff_id = $_SESSION['user_id'];
                $profile_picture = '';

                // Check if the office_staff table has profile_picture column
                $result = $conn->query("SHOW COLUMNS FROM office_staff LIKE 'profile_picture'");
                if ($result->num_rows > 0) {
                    $stmt = $conn->prepare("SELECT profile_picture FROM office_staff WHERE staff_id = ?");
                    $stmt->bind_param("i", $staff_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $profile_picture = $row['profile_picture'];
                    }
                }

                $profile_picture_url = !empty($profile_picture)
                    ? "../uploads/admin/" . $profile_picture
                    : "../assets/default-profile.jpg";
                ?>
                <img src="<?php echo $profile_picture_url; ?>" alt="Profile" class="user-avatar" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
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

        <!-- Main Content -->
        <main class="main-content">
            <div class="tools-content">
                <div class="tools-header">
                    <h1><i class="fas fa-calendar-check"></i> Technician Availability</h1>
                    <div class="tools-header-buttons">
                        <a href="technicians.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Technicians
                        </a>
                    </div>
                </div>

                <h2>Availability for <?= htmlspecialchars($technician['tech_fname'] . ' ' . $technician['tech_lname']) ?></h2>

                <!-- Weekly Availability Section -->
                <div class="availability-section">
                    <h3><i class="fas fa-calendar-week"></i> Weekly Schedule</h3>
                    <p>Set the technician's regular weekly availability schedule.</p>

                    <table class="availability-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($weeklyAvailability)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No weekly availability set</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($weeklyAvailability as $availability): ?>
                                    <tr>
                                        <td><?= $dayNames[$availability['day_of_week']] ?></td>
                                        <td><?= date('h:i A', strtotime($availability['start_time'])) ?></td>
                                        <td><?= date('h:i A', strtotime($availability['end_time'])) ?></td>
                                        <td>
                                            <?php if ($availability['is_available']): ?>
                                                <span class="availability-status status-available">Available</span>
                                            <?php else: ?>
                                                <span class="availability-status status-unavailable">Unavailable</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="availability-actions">
                                            <button class="btn-edit" onclick="editWeeklyAvailability(<?= isset($availability['availability_id']) ? $availability['availability_id'] : $availability['id'] ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn-delete" onclick="deleteAvailability(<?= isset($availability['availability_id']) ? $availability['availability_id'] : $availability['id'] ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <button class="btn-add" onclick="showAddWeeklyModal()">
                        <i class="fas fa-plus"></i> Add Weekly Availability
                    </button>
                </div>

                <!-- Specific Date Availability Section -->
                <div class="availability-section">
                    <h3><i class="fas fa-calendar-day"></i> Specific Dates</h3>
                    <p>Set availability for specific dates that override the weekly schedule.</p>

                    <table class="availability-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($specificAvailability)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No specific date availability set</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($specificAvailability as $availability): ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($availability['specific_date'])) ?></td>
                                        <td><?= date('h:i A', strtotime($availability['start_time'])) ?></td>
                                        <td><?= date('h:i A', strtotime($availability['end_time'])) ?></td>
                                        <td>
                                            <?php if ($availability['is_available']): ?>
                                                <span class="availability-status status-available">Available</span>
                                            <?php else: ?>
                                                <span class="availability-status status-unavailable">Unavailable</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="availability-actions">
                                            <button class="btn-edit" onclick="editSpecificAvailability(<?= isset($availability['availability_id']) ? $availability['availability_id'] : $availability['id'] ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn-delete" onclick="deleteAvailability(<?= isset($availability['availability_id']) ? $availability['availability_id'] : $availability['id'] ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <button class="btn-add" onclick="showAddSpecificModal()">
                        <i class="fas fa-plus"></i> Add Specific Date
                    </button>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Weekly Availability Modal -->
    <div id="weeklyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="weeklyModalTitle">Add Weekly Availability</h3>
                <button class="close-modal" onclick="closeModal('weeklyModal')">&times;</button>
            </div>

            <form id="weeklyAvailabilityForm" method="post" action="save_technician_availability.php">
                <input type="hidden" name="availability_id" id="weeklyAvailabilityId" value="">
                <input type="hidden" name="technician_id" value="<?= $technician_id ?>">
                <input type="hidden" name="availability_type" value="weekly">

                <div class="form-group">
                    <label for="day_of_week">Day of Week</label>
                    <select name="day_of_week" id="day_of_week" required>
                        <?php foreach ($dayNames as $value => $name): ?>
                            <option value="<?= $value ?>"><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="weekly_start_time">Start Time</label>
                    <input type="time" name="start_time" id="weekly_start_time" required>
                </div>

                <div class="form-group">
                    <label for="weekly_end_time">End Time</label>
                    <input type="time" name="end_time" id="weekly_end_time" required>
                </div>

                <div class="form-group">
                    <label for="weekly_is_available">Status</label>
                    <select name="is_available" id="weekly_is_available">
                        <option value="1">Available</option>
                        <option value="0">Unavailable</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal('weeklyModal')">Cancel</button>
                    <button type="submit" class="btn-save">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Specific Date Availability Modal -->
    <div id="specificModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="specificModalTitle">Add Specific Date Availability</h3>
                <button class="close-modal" onclick="closeModal('specificModal')">&times;</button>
            </div>

            <form id="specificAvailabilityForm" method="post" action="save_technician_availability.php">
                <input type="hidden" name="availability_id" id="specificAvailabilityId" value="">
                <input type="hidden" name="technician_id" value="<?= $technician_id ?>">
                <input type="hidden" name="availability_type" value="specific">

                <div class="form-group">
                    <label for="specific_date">Date</label>
                    <input type="date" name="specific_date" id="specific_date" required>
                </div>

                <div class="form-group">
                    <label for="specific_start_time">Start Time</label>
                    <input type="time" name="start_time" id="specific_start_time" required>
                </div>

                <div class="form-group">
                    <label for="specific_end_time">End Time</label>
                    <input type="time" name="end_time" id="specific_end_time" required>
                </div>

                <div class="form-group">
                    <label for="specific_is_available">Status</label>
                    <select name="is_available" id="specific_is_available">
                        <option value="1">Available</option>
                        <option value="0">Unavailable</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal('specificModal')">Cancel</button>
                    <button type="submit" class="btn-save">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Show Add Weekly Availability Modal
        function showAddWeeklyModal() {
            document.getElementById('weeklyModalTitle').textContent = 'Add Weekly Availability';
            document.getElementById('weeklyAvailabilityId').value = '';
            document.getElementById('weeklyAvailabilityForm').reset();
            document.getElementById('weeklyModal').style.display = 'block';
        }

        // Show Add Specific Date Availability Modal
        function showAddSpecificModal() {
            document.getElementById('specificModalTitle').textContent = 'Add Specific Date Availability';
            document.getElementById('specificAvailabilityId').value = '';
            document.getElementById('specificAvailabilityForm').reset();
            document.getElementById('specificModal').style.display = 'block';
        }

        // Edit Weekly Availability
        function editWeeklyAvailability(availabilityId) {
            // Fetch availability data via AJAX
            fetch('get_availability.php?id=' + availabilityId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('weeklyModalTitle').textContent = 'Edit Weekly Availability';
                        // Use availability_id if it exists, otherwise use id
                        const availabilityId = data.availability.availability_id || data.availability.id;
                        document.getElementById('weeklyAvailabilityId').value = availabilityId;
                        document.getElementById('day_of_week').value = data.availability.day_of_week;
                        document.getElementById('weekly_start_time').value = data.availability.start_time;
                        document.getElementById('weekly_end_time').value = data.availability.end_time;
                        document.getElementById('weekly_is_available').value = data.availability.is_available;
                        document.getElementById('weeklyModal').style.display = 'block';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while fetching availability data.');
                });
        }

        // Edit Specific Date Availability
        function editSpecificAvailability(availabilityId) {
            // Fetch availability data via AJAX
            fetch('get_availability.php?id=' + availabilityId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('specificModalTitle').textContent = 'Edit Specific Date Availability';
                        // Use availability_id if it exists, otherwise use id
                        const availabilityId = data.availability.availability_id || data.availability.id;
                        document.getElementById('specificAvailabilityId').value = availabilityId;
                        document.getElementById('specific_date').value = data.availability.specific_date;
                        document.getElementById('specific_start_time').value = data.availability.start_time;
                        document.getElementById('specific_end_time').value = data.availability.end_time;
                        document.getElementById('specific_is_available').value = data.availability.is_available;
                        document.getElementById('specificModal').style.display = 'block';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while fetching availability data.');
                });
        }

        // Delete Availability
        function deleteAvailability(availabilityId) {
            if (confirm('Are you sure you want to delete this availability?')) {
                // Send delete request via AJAX
                fetch('delete_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ availability_id: availabilityId }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Availability deleted successfully.');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the availability.');
                });
            }
        }

        // Close Modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        };

        // Initialize notification functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle notification dropdown
            const notificationIcon = document.querySelector('.notification-icon');
            const notificationDropdown = document.querySelector('.notification-dropdown');

            if (notificationIcon && notificationDropdown) {
                notificationIcon.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('show');

                    // If opening the dropdown, fetch notifications
                    if (notificationDropdown.classList.contains('show')) {
                        fetchNotifications();
                    }
                });
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (notificationDropdown && notificationDropdown.classList.contains('show') && !notificationDropdown.contains(e.target) && !notificationIcon.contains(e.target)) {
                    notificationDropdown.classList.remove('show');
                }
            });

            // Prevent dropdown from closing when clicking inside it
            if (notificationDropdown) {
                notificationDropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }

            // Mark all as read button
            const markAllReadBtn = document.querySelector('.mark-all-read');
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function() {
                    markAllNotificationsAsRead();
                });
            }

            // Initial fetch of notifications
            fetchNotifications();

            // Set up periodic refresh of notifications (every 60 seconds)
            setInterval(fetchNotifications, 60000);
        });

        // Fetch notifications
        function fetchNotifications() {
            fetch('../get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error fetching notifications:', data.error);
                        return;
                    }

                    updateNotificationBadge(data.unread_count);
                    updateNotificationDropdown(data.notifications);
                })
                .catch(error => {
                    console.error('Error fetching notifications:', error);
                });
        }

        // Update notification badge with unread count
        function updateNotificationBadge(count) {
            const badge = document.querySelector('.notification-badge');

            if (badge) {
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            }
        }

        // Update notification dropdown with notifications
        function updateNotificationDropdown(notifications) {
            const notificationList = document.querySelector('.notification-list');

            if (!notificationList) return;

            if (!notifications || notifications.length === 0) {
                notificationList.innerHTML = '<li class="no-notifications">No new notifications</li>';
                return;
            }

            // Clear existing notifications
            notificationList.innerHTML = '';

            // Add notifications to the list
            notifications.forEach(notification => {
                const notificationItem = document.createElement('li');
                notificationItem.className = `notification-item ${notification.is_read === '0' ? 'unread' : ''}`;
                notificationItem.setAttribute('data-id', notification.notification_id);

                notificationItem.innerHTML = `
                    <div class="notification-info">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-desc">${notification.message}</div>
                        <div class="notification-time">
                            ${formatTimeAgo(notification.created_at)}
                            ${notification.is_read === '1' ? '<span class="viewed-indicator"><i class="fas fa-check"></i> Viewed</span>' : ''}
                        </div>
                    </div>
                `;

                notificationList.appendChild(notificationItem);

                // Add click event to mark as read
                notificationItem.addEventListener('click', function() {
                    markNotificationAsRead(this.dataset.id);
                });
            });
        }

        // Format time ago for notifications
        function formatTimeAgo(timestamp) {
            const now = new Date();
            const date = new Date(timestamp);
            const seconds = Math.floor((now - date) / 1000);

            let interval = Math.floor(seconds / 31536000);
            if (interval >= 1) {
                return interval + " year" + (interval === 1 ? "" : "s") + " ago";
            }

            interval = Math.floor(seconds / 2592000);
            if (interval >= 1) {
                return interval + " month" + (interval === 1 ? "" : "s") + " ago";
            }

            interval = Math.floor(seconds / 86400);
            if (interval >= 1) {
                return interval + " day" + (interval === 1 ? "" : "s") + " ago";
            }

            interval = Math.floor(seconds / 3600);
            if (interval >= 1) {
                return interval + " hour" + (interval === 1 ? "" : "s") + " ago";
            }

            interval = Math.floor(seconds / 60);
            if (interval >= 1) {
                return interval + " minute" + (interval === 1 ? "" : "s") + " ago";
            }

            return "Just now";
        }

        // Mark notification as read
        function markNotificationAsRead(notificationId) {
            fetch('../mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId }),
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI
                        const notification = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                        if (notification) {
                            notification.classList.remove('unread');
                        }

                        // Refresh notification count
                        fetchNotifications();
                    }
                })
                .catch(error => console.error('Error marking notification as read:', error));
        }

        // Mark all notifications as read
        function markAllNotificationsAsRead() {
            fetch('../mark_all_notifications_read.php', {
                method: 'POST',
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update notification count and list
                        fetchNotifications();
                    }
                })
                .catch(error => console.error('Error marking all notifications as read:', error));
        }
    </script>
</body>
</html>
