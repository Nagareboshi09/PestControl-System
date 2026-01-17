<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}
require_once '../db_connect.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Initialize messages array
$messages = [];
$errors = [];
$fixed = [];

// Function to get day name
function getDayName($dayNum) {
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    return $days[$dayNum];
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'fix_all') {
        try {
            // Get all weekly availability entries
            $query = "SELECT * FROM technician_availability WHERE day_of_week IS NOT NULL";
            $result = $conn->query($query);

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $id = $row['id'];
                    $technicianId = $row['technician_id'];
                    $dayOfWeek = $row['day_of_week'];
                    $startTime = $row['start_time'];
                    $endTime = $row['end_time'];
                    $isAvailable = $row['is_available'];

                    // Ensure day_of_week is a valid integer between 0-6
                    $newDayOfWeek = intval($dayOfWeek);
                    if ($newDayOfWeek < 0 || $newDayOfWeek > 6) {
                        $errors[] = "Invalid day of week found: $dayOfWeek for ID $id";
                        continue;
                    }

                    // Ensure time format is correct (HH:MM:SS)
                    $newStartTime = $startTime;
                    if (substr_count($startTime, ':') === 1) {
                        $newStartTime = $startTime . ':00';
                    }

                    $newEndTime = $endTime;
                    if (substr_count($endTime, ':') === 1) {
                        $newEndTime = $endTime . ':00';
                    }

                    // Update the record if needed
                    if ($newDayOfWeek != $dayOfWeek || $newStartTime != $startTime || $newEndTime != $endTime) {
                        $updateQuery = "UPDATE technician_availability
                                       SET day_of_week = ?, start_time = ?, end_time = ?
                                       WHERE id = ?";
                        $stmt = $conn->prepare($updateQuery);
                        $stmt->bind_param("issi", $newDayOfWeek, $newStartTime, $newEndTime, $id);
                        $stmt->execute();

                        $fixed[] = "Fixed record ID $id: Day $dayOfWeek → $newDayOfWeek, Start $startTime → $newStartTime, End $endTime → $newEndTime";
                    }
                }

                $messages[] = "Checked and fixed " . count($fixed) . " weekly availability entries.";
            } else {
                $messages[] = "No weekly availability entries found.";
            }
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }

    if ($action === 'add_today') {
        try {
            $technicianId = isset($_POST['technician_id']) ? intval($_POST['technician_id']) : 0;
            $startTime = isset($_POST['start_time']) ? $_POST['start_time'] : '';
            $endTime = isset($_POST['end_time']) ? $_POST['end_time'] : '';

            if ($technicianId <= 0) {
                throw new Exception("Please select a technician.");
            }

            if (empty($startTime) || empty($endTime)) {
                throw new Exception("Start time and end time are required.");
            }

            // Ensure time format is correct
            if (substr_count($startTime, ':') === 1) {
                $startTime .= ':00';
            }

            if (substr_count($endTime, ':') === 1) {
                $endTime .= ':00';
            }

            // Get today's day of week
            $today = date('w'); // 0 (Sunday) to 6 (Saturday)

            // Check if there's already a weekly entry for this day
            $checkQuery = "SELECT id FROM technician_availability
                          WHERE technician_id = ? AND day_of_week = ? AND specific_date IS NULL";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("ii", $technicianId, $today);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Update existing entry
                $row = $result->fetch_assoc();
                $id = $row['id'];

                $updateQuery = "UPDATE technician_availability
                               SET start_time = ?, end_time = ?, is_available = 1
                               WHERE id = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("ssi", $startTime, $endTime, $id);
                $stmt->execute();

                $messages[] = "Updated weekly availability for " . getDayName($today) . " (day $today) for technician ID $technicianId.";
            } else {
                // Insert new entry
                $insertQuery = "INSERT INTO technician_availability
                               (technician_id, day_of_week, start_time, end_time, is_available)
                               VALUES (?, ?, ?, ?, 1)";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("iiss", $technicianId, $today, $startTime, $endTime);
                $stmt->execute();

                $messages[] = "Added weekly availability for " . getDayName($today) . " (day $today) for technician ID $technicianId.";
            }
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// Get all technicians
$techQuery = "SELECT technician_id, tech_fname, tech_lname FROM technicians ORDER BY tech_fname, tech_lname";
$techResult = $conn->query($techQuery);
$technicians = [];
while ($row = $techResult->fetch_assoc()) {
    $technicians[] = $row;
}

// Get today's day of week
$today = date('w'); // 0 (Sunday) to 6 (Saturday)
$dayName = getDayName($today);

// Get weekly availability for today
$todayQuery = "SELECT ta.*, t.tech_fname, t.tech_lname
              FROM technician_availability ta
              JOIN technicians t ON ta.technician_id = t.technician_id
              WHERE ta.day_of_week = ? AND ta.specific_date IS NULL
              ORDER BY t.tech_fname, t.tech_lname";
$todayStmt = $conn->prepare($todayQuery);
$todayStmt->bind_param("i", $today);
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
    <title>Fix Weekly Availability - MacJ Pest Control</title>
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
        .fixed-message {
            background-color: #fff3cd;
            color: #856404;
            padding: 5px;
            border-radius: 4px;
            margin-bottom: 5px;
            font-family: monospace;
            font-size: 12px;
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
                <h1><i class="fas fa-calendar-check"></i> Fix Weekly Availability</h1>
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

                <?php if (!empty($fixed)): ?>
                    <div class="success-message">
                        <strong>Fixed Records:</strong>
                        <?php foreach ($fixed as $fix): ?>
                            <div class="fixed-message"><?= $fix ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Today's Weekly Availability (<?= $dayName ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($todayAvailability)): ?>
                        <p>No weekly availability found for today (<?= $dayName ?>).</p>
                    <?php else: ?>
                        <?php foreach ($todayAvailability as $avail): ?>
                            <div class="availability-card">
                                <h4><?= $avail['tech_fname'] . ' ' . $avail['tech_lname'] ?></h4>
                                <div class="availability-details">
                                    <div class="availability-item">Day: <?= getDayName($avail['day_of_week']) ?> (<?= $avail['day_of_week'] ?>)</div>
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
                    <h3>Fix All Weekly Availability</h3>
                </div>
                <div class="card-body">
                    <p>This will check and fix all weekly availability entries in the database, ensuring correct day of week values and time formats.</p>
                    <form method="post" action="">
                        <button type="submit" name="action" value="fix_all" class="btn btn-primary">
                            <i class="fas fa-wrench"></i> Fix All Weekly Availability
                        </button>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h3>Add Weekly Availability for Today (<?= $dayName ?>)</h3>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="technician_id">Select Technician:</label>
                            <select name="technician_id" id="technician_id" class="form-control" required>
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
                                <i class="fas fa-plus"></i> Add Weekly Availability for Today
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
