<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}
require_once '../db_connect.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Get technician ID from URL parameter
$technician_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Initialize messages array
$messages = [];
$errors = [];

// Function to get day name
function getDayName($dayNum) {
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    return $days[$dayNum];
}

// Get all technicians
$techQuery = "SELECT technician_id, tech_fname, tech_lname FROM technicians ORDER BY tech_fname, tech_lname";
$techResult = $conn->query($techQuery);
$technicians = [];
while ($row = $techResult->fetch_assoc()) {
    $technicians[] = $row;
}

// Get today's date and day of week
$today = date('Y-m-d');
$dayOfWeek = date('w'); // 0 (Sunday) to 6 (Saturday)
$dayName = getDayName($dayOfWeek);

// If technician ID is provided, get their availability
$availability = [];
if ($technician_id > 0) {
    // Get technician details
    $techStmt = $conn->prepare("SELECT * FROM technicians WHERE technician_id = ?");
    $techStmt->bind_param("i", $technician_id);
    $techStmt->execute();
    $techResult = $techStmt->get_result();

    if ($techResult->num_rows === 0) {
        $errors[] = "Technician not found.";
    } else {
        $technician = $techResult->fetch_assoc();

        // Get all availability records for this technician
        $availQuery = "SELECT * FROM technician_availability WHERE technician_id = ? ORDER BY day_of_week, specific_date, start_time";
        $availStmt = $conn->prepare($availQuery);
        $availStmt->bind_param("i", $technician_id);
        $availStmt->execute();
        $availResult = $availStmt->get_result();

        while ($row = $availResult->fetch_assoc()) {
            $availability[] = $row;
        }
    }
}

// If form was submitted to fix availability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'fix_availability' && $technician_id > 0) {
        try {
            // Get the day of week to fix
            $dayToFix = isset($_POST['day_of_week']) ? intval($_POST['day_of_week']) : -1;

            if ($dayToFix < 0 || $dayToFix > 6) {
                throw new Exception("Invalid day of week.");
            }

            // Get the start and end times
            $startTime = isset($_POST['start_time']) ? $_POST['start_time'] : '';
            $endTime = isset($_POST['end_time']) ? $_POST['end_time'] : '';

            if (empty($startTime) || empty($endTime)) {
                throw new Exception("Start time and end time are required.");
            }

            // Ensure time format is HH:MM:SS
            if (substr_count($startTime, ':') === 1) {
                $startTime .= ':00';
            }

            if (substr_count($endTime, ':') === 1) {
                $endTime .= ':00';
            }

            // Check if there's an existing record for this day
            $checkQuery = "SELECT id FROM technician_availability
                          WHERE technician_id = ? AND day_of_week = ? AND specific_date IS NULL";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("ii", $technician_id, $dayToFix);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                // Update existing record
                $row = $checkResult->fetch_assoc();
                $id = $row['id'];

                $updateQuery = "UPDATE technician_availability
                               SET start_time = ?, end_time = ?, is_available = 1
                               WHERE id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("ssi", $startTime, $endTime, $id);
                $updateStmt->execute();

                $messages[] = "Updated availability for " . getDayName($dayToFix) . ".";
            } else {
                // Insert new record
                $insertQuery = "INSERT INTO technician_availability
                               (technician_id, day_of_week, start_time, end_time, is_available)
                               VALUES (?, ?, ?, ?, 1)";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("iiss", $technician_id, $dayToFix, $startTime, $endTime);
                $insertStmt->execute();

                $messages[] = "Added availability for " . getDayName($dayToFix) . ".";
            }

            // Reload availability
            $availQuery = "SELECT * FROM technician_availability WHERE technician_id = ? ORDER BY day_of_week, specific_date, start_time";
            $availStmt = $conn->prepare($availQuery);
            $availStmt->bind_param("i", $technician_id);
            $availStmt->execute();
            $availResult = $availStmt->get_result();

            $availability = [];
            while ($row = $availResult->fetch_assoc()) {
                $availability[] = $row;
            }
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// Check for database column issues
$columnIssues = [];
$checkColumnQuery = "SHOW COLUMNS FROM technician_availability";
$columnResult = $conn->query($checkColumnQuery);
$columns = [];
while ($row = $columnResult->fetch_assoc()) {
    $columns[$row['Field']] = $row;
}

// Check for day_of_week column type
if (isset($columns['day_of_week'])) {
    $dayOfWeekType = $columns['day_of_week']['Type'];
    if (strpos($dayOfWeekType, 'int') === false) {
        $columnIssues[] = "day_of_week column is not an integer type. Current type: $dayOfWeekType";
    }
}

// Check for time column types
foreach (['start_time', 'end_time'] as $timeCol) {
    if (isset($columns[$timeCol])) {
        $timeType = $columns[$timeCol]['Type'];
        if (strpos($timeType, 'time') === false) {
            $columnIssues[] = "$timeCol column is not a time type. Current type: $timeType";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Technician Availability - MacJ Pest Control</title>
    <link rel="stylesheet" href="css/tools-equipment-page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .message-container {
            margin-bottom: 20px;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .warning-message {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
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
        .code-block {
            background-color: #f8f9fa;
            border: 1px solid #eaecef;
            border-radius: 3px;
            font-family: monospace;
            padding: 10px;
            margin: 10px 0;
            overflow-x: auto;
        }
    </style>
</head>
<body>
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
                    <li><a href="services.php"><i class="fas fa-pest-control"></i> Services</a></li>
                    <li><a href="clients.php"><i class="fas fa-users"></i> Clients</a></li>
                    <li class="active"><a href="technicians.php"><i class="fas fa-user-hard-hat"></i> Technicians</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="SignOut.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a></li>
                </ul>
            </nav>
        </aside>

    <div class="main-content">
        <div class="tools-content">
            <div class="tools-header">
                <h1><i class="fas fa-bug"></i> Debug Technician Availability</h1>
                <div class="tools-header-buttons">
                    <a href="technicians.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Technicians
                    </a>
                </div>
            </div>

            <div class="message-container">
                <?php foreach ($messages as $message): ?>
                    <div class="success-message"><?= $message ?></div>
                <?php endforeach; ?>

                <?php foreach ($errors as $error): ?>
                    <div class="error-message"><?= $error ?></div>
                <?php endforeach; ?>

                <?php if (!empty($columnIssues)): ?>
                    <div class="warning-message">
                        <strong>Database Column Issues:</strong>
                        <ul>
                            <?php foreach ($columnIssues as $issue): ?>
                                <li><?= $issue ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Select Technician</h3>
                </div>
                <div class="card-body">
                    <form method="get" action="">
                        <div class="form-group">
                            <label for="technician_id">Technician:</label>
                            <select name="id" id="technician_id" class="form-control" onchange="this.form.submit()">
                                <option value="">-- Select Technician --</option>
                                <?php foreach ($technicians as $tech): ?>
                                    <option value="<?= $tech['technician_id'] ?>" <?= $technician_id == $tech['technician_id'] ? 'selected' : '' ?>>
                                        <?= $tech['tech_fname'] . ' ' . $tech['tech_lname'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($technician_id > 0): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h3>Current Availability</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($availability)): ?>
                            <p>No availability records found for this technician.</p>
                        <?php else: ?>
                            <table class="availability-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Day/Date</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Available</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($availability as $avail): ?>
                                        <tr>
                                            <td><?= $avail['id'] ?></td>
                                            <td><?= $avail['specific_date'] ? 'Specific Date' : 'Weekly' ?></td>
                                            <td>
                                                <?php if ($avail['specific_date']): ?>
                                                    <?= $avail['specific_date'] ?>
                                                <?php else: ?>
                                                    <?= getDayName($avail['day_of_week']) ?> (<?= $avail['day_of_week'] ?>)
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $avail['start_time'] ?></td>
                                            <td><?= $avail['end_time'] ?></td>
                                            <td><?= $avail['is_available'] ? 'Yes' : 'No' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h3>Fix Availability</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="fix_availability">

                            <div class="form-group">
                                <label for="day_of_week">Day of Week:</label>
                                <select name="day_of_week" id="day_of_week" class="form-control" required>
                                    <?php for ($i = 0; $i < 7; $i++): ?>
                                        <option value="<?= $i ?>" <?= $i == $dayOfWeek ? 'selected' : '' ?>>
                                            <?= getDayName($i) ?> (<?= $i ?>)
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="start_time">Start Time:</label>
                                <input type="time" name="start_time" id="start_time" class="form-control" value="08:00" required>
                            </div>

                            <div class="form-group">
                                <label for="end_time">End Time:</label>
                                <input type="time" name="end_time" id="end_time" class="form-control" value="17:00" required>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Availability
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h3>Debug Information</h3>
                    </div>
                    <div class="card-body">
                        <h4>Today's Date: <?= $today ?> (<?= $dayName ?>)</h4>
                        <h4>Day of Week: <?= $dayOfWeek ?></h4>

                        <h4>Database Column Information:</h4>
                        <div class="code-block">
                            <pre><?php print_r($columns); ?></pre>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/sidebar.js"></script>
</body>
</html>
