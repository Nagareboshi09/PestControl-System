<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'office_staff') {
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

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'fix_day_of_week') {
            // Fix day of week for a specific technician or all technicians
            $tech_id = isset($_POST['technician_id']) ? intval($_POST['technician_id']) : 0;

            // Get all weekly availability entries
            if ($tech_id > 0) {
                $query = "SELECT * FROM technician_availability WHERE technician_id = ? AND day_of_week IS NOT NULL";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $tech_id);
            } else {
                $query = "SELECT * FROM technician_availability WHERE day_of_week IS NOT NULL";
                $stmt = $conn->prepare($query);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $count = 0;

            while ($row = $result->fetch_assoc()) {
                // Verify day of week is between 0-6
                $day = intval($row['day_of_week']);
                if ($day < 0 || $day > 6) {
                    $errors[] = "Invalid day of week found: {$row['day_of_week']} for ID {$row['id']}";
                    continue;
                }

                // Update the record to ensure it's stored as an integer
                $updateQuery = "UPDATE technician_availability SET day_of_week = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("ii", $day, $row['id']);
                $updateStmt->execute();
                $count++;
            }

            $messages[] = "Verified $count weekly availability entries.";
        }
        elseif ($action === 'fix_specific_date') {
            // Fix specific date entries
            $tech_id = isset($_POST['technician_id']) ? intval($_POST['technician_id']) : 0;

            // Get all specific date availability entries
            if ($tech_id > 0) {
                $query = "SELECT * FROM technician_availability WHERE technician_id = ? AND specific_date IS NOT NULL";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $tech_id);
            } else {
                $query = "SELECT * FROM technician_availability WHERE specific_date IS NOT NULL";
                $stmt = $conn->prepare($query);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $count = 0;

            while ($row = $result->fetch_assoc()) {
                // Verify specific date is valid
                $date = $row['specific_date'];
                if (!$date || $date === '0000-00-00') {
                    $errors[] = "Invalid date found: {$row['specific_date']} for ID {$row['id']}";
                    continue;
                }

                $count++;
            }

            $messages[] = "Verified $count specific date availability entries.";
        }
        elseif ($action === 'add_today') {
            // Add availability for today for a specific technician
            $tech_id = isset($_POST['technician_id']) ? intval($_POST['technician_id']) : 0;
            $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '08:00:00';
            $end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '21:00:00';

            if ($tech_id <= 0) {
                throw new Exception("Invalid technician ID");
            }

            // Get today's date and day of week
            $today = date('Y-m-d');
            $dayOfWeek = date('w'); // 0 (Sunday) to 6 (Saturday)

            // Check if there's already a specific date entry for today
            $checkQuery = "SELECT * FROM technician_availability WHERE technician_id = ? AND specific_date = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("is", $tech_id, $today);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                // Update existing entry
                $updateQuery = "UPDATE technician_availability
                               SET start_time = ?, end_time = ?, is_available = 1
                               WHERE technician_id = ? AND specific_date = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("ssis", $start_time, $end_time, $tech_id, $today);
                $updateStmt->execute();
                $messages[] = "Updated availability for today ({$today}) for technician ID {$tech_id}.";
            } else {
                // Insert new entry
                $insertQuery = "INSERT INTO technician_availability
                               (technician_id, specific_date, start_time, end_time, is_available)
                               VALUES (?, ?, ?, ?, 1)";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("isss", $tech_id, $today, $start_time, $end_time);
                $insertStmt->execute();
                $messages[] = "Added availability for today ({$today}) for technician ID {$tech_id}.";
            }
        }
    } catch (Exception $e) {
        $errors[] = "Error: " . $e->getMessage();
    }
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

// Get availability for today
$todayQuery = "SELECT ta.*, t.tech_fname, t.tech_lname
              FROM technician_availability ta
              JOIN technicians t ON ta.technician_id = t.technician_id
              WHERE (ta.day_of_week = ? OR ta.specific_date = ?)
              ORDER BY t.tech_fname, t.tech_lname";
$todayStmt = $conn->prepare($todayQuery);
$todayStmt->bind_param("is", $dayOfWeek, $today);
$todayStmt->execute();
$todayResult = $todayStmt->get_result();

$todayAvailability = [];
while ($row = $todayResult->fetch_assoc()) {
    $todayAvailability[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Technician Availability - MacJ Pest Control</title>
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
        .availability-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .availability-card h4 {
            margin-top: 0;
            color: #4361ee;
        }
        .availability-details {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .availability-item {
            background-color: #e9ecef;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
        }
        .availability-item.available {
            background-color: #d4edda;
            color: #155724;
        }
        .availability-item.unavailable {
            background-color: #f8d7da;
            color: #721c24;
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
                <h1><i class="fas fa-calendar-check"></i> Fix Technician Availability</h1>
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
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Today's Availability (<?= $today ?> - <?= $dayName ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($todayAvailability)): ?>
                        <p>No availability found for today.</p>
                    <?php else: ?>
                        <?php foreach ($todayAvailability as $avail): ?>
                            <div class="availability-card">
                                <h4><?= $avail['tech_fname'] . ' ' . $avail['tech_lname'] ?></h4>
                                <div class="availability-details">
                                    <?php if ($avail['specific_date']): ?>
                                        <div class="availability-item">Specific Date: <?= $avail['specific_date'] ?></div>
                                    <?php else: ?>
                                        <div class="availability-item">Weekly: <?= getDayName($avail['day_of_week']) ?></div>
                                    <?php endif; ?>

                                    <div class="availability-item">Time: <?= date('h:i A', strtotime($avail['start_time'])) ?> - <?= date('h:i A', strtotime($avail['end_time'])) ?></div>

                                    <?php if ($avail['is_available']): ?>
                                        <div class="availability-item available">Available</div>
                                    <?php else: ?>
                                        <div class="availability-item unavailable">Unavailable</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h3>Fix Availability Issues</h3>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="technician_id">Select Technician:</label>
                            <select name="technician_id" id="technician_id" class="form-control">
                                <option value="0">All Technicians</option>
                                <?php foreach ($technicians as $tech): ?>
                                    <option value="<?= $tech['technician_id'] ?>" <?= $technician_id == $tech['technician_id'] ? 'selected' : '' ?>>
                                        <?= $tech['tech_fname'] . ' ' . $tech['tech_lname'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <button type="submit" name="action" value="fix_day_of_week" class="btn btn-primary">
                                <i class="fas fa-check"></i> Fix Weekly Availability
                            </button>
                            <button type="submit" name="action" value="fix_specific_date" class="btn btn-primary">
                                <i class="fas fa-check"></i> Fix Specific Date Availability
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h3>Add Availability for Today</h3>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="add_technician_id">Select Technician:</label>
                            <select name="technician_id" id="add_technician_id" class="form-control" required>
                                <option value="">-- Select Technician --</option>
                                <?php foreach ($technicians as $tech): ?>
                                    <option value="<?= $tech['technician_id'] ?>">
                                        <?= $tech['tech_fname'] . ' ' . $tech['tech_lname'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="start_time">Start Time:</label>
                            <input type="time" name="start_time" id="start_time" class="form-control" value="08:00" required>
                        </div>

                        <div class="form-group">
                            <label for="end_time">End Time:</label>
                            <input type="time" name="end_time" id="end_time" class="form-control" value="21:00" required>
                        </div>

                        <div class="form-group">
                            <button type="submit" name="action" value="add_today" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Availability for Today
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/sidebar.js"></script>
</body>
</html>
