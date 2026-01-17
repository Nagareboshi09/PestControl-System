<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}

// Start output buffering to capture setup messages
ob_start();

require_once '../db_connect.php';

// Check if the status column exists in technicians table
$statusColumnQuery = "SHOW COLUMNS FROM technicians LIKE 'status'";
$statusColumnResult = $conn->query($statusColumnQuery);
$statusColumnExists = $statusColumnResult->num_rows > 0;

if (!$statusColumnExists) {
    // Add status column to technicians table
    $addStatusColumnQuery = "ALTER TABLE technicians ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'";
    if ($conn->query($addStatusColumnQuery)) {
        echo "Added 'status' column to technicians table.<br>";
    } else {
        echo "Error adding status column: " . $conn->error . "<br>";
    }
}

// Check if the technician_availability table already exists
$tableExistsQuery = "SHOW TABLES LIKE 'technician_availability'";
$tableExistsResult = $conn->query($tableExistsQuery);

if ($tableExistsResult->num_rows == 0) {
    // Table doesn't exist, create it
    $createTableQuery = "
    CREATE TABLE `technician_availability` (
        `availability_id` INT(11) NOT NULL AUTO_INCREMENT,
        `technician_id` INT(11) NOT NULL,
        `day_of_week` TINYINT(1) NULL DEFAULT NULL COMMENT '0=Sunday, 1=Monday, etc.',
        `specific_date` DATE NULL DEFAULT NULL,
        `start_time` TIME NOT NULL,
        `end_time` TIME NOT NULL,
        `is_available` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`availability_id`),
        INDEX `fk_technician_availability_technician_idx` (`technician_id`),
        CONSTRAINT `fk_technician_availability_technician` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";

    if ($conn->query($createTableQuery) === TRUE) {
        echo "Table 'technician_availability' created successfully.<br>";

        // Add default availability for all technicians (Monday-Friday, 8am-5pm)
        $technicianQuery = $statusColumnExists
            ? "SELECT technician_id FROM technicians WHERE status = 'active'"
            : "SELECT technician_id FROM technicians";
        $technicianResult = $conn->query($technicianQuery);

        if ($technicianResult->num_rows > 0) {
            $insertCount = 0;

            while ($row = $technicianResult->fetch_assoc()) {
                $technicianId = $row['technician_id'];

                // Add Monday-Friday availability (8am-5pm)
                for ($day = 1; $day <= 5; $day++) {
                    $insertQuery = "INSERT INTO technician_availability (technician_id, day_of_week, start_time, end_time, is_available)
                                    VALUES (?, ?, '08:00:00', '17:00:00', 1)";
                    $stmt = $conn->prepare($insertQuery);
                    $stmt->bind_param("ii", $technicianId, $day);

                    if ($stmt->execute()) {
                        $insertCount++;
                    }
                }
            }

            echo "Added default availability (Mon-Fri, 8am-5pm) for $insertCount technician days.<br>";
        }
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
} else {
    echo "Table 'technician_availability' already exists.<br>";
}

// Check if the appointment_technicians table already exists
$tableExistsQuery = "SHOW TABLES LIKE 'appointment_technicians'";
$tableExistsResult = $conn->query($tableExistsQuery);

if ($tableExistsResult->num_rows == 0) {
    // Table doesn't exist, create it
    $createTableQuery = "
    CREATE TABLE `appointment_technicians` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `appointment_id` INT(11) NOT NULL,
        `technician_id` INT(11) NOT NULL,
        `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `fk_appointment_technicians_appointment_idx` (`appointment_id`),
        INDEX `fk_appointment_technicians_technician_idx` (`technician_id`),
        CONSTRAINT `fk_appointment_technicians_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_appointment_technicians_technician` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";

    if ($conn->query($createTableQuery) === TRUE) {
        echo "Table 'appointment_technicians' created successfully.<br>";

        // Migrate existing technician assignments
        $migrateQuery = "
        INSERT INTO appointment_technicians (appointment_id, technician_id, is_primary)
        SELECT appointment_id, technician_id, 1
        FROM appointments
        WHERE technician_id IS NOT NULL
        ";

        if ($conn->query($migrateQuery) === TRUE) {
            $affectedRows = $conn->affected_rows;
            echo "Migrated $affectedRows existing technician assignments.<br>";
        } else {
            echo "Error migrating technician assignments: " . $conn->error . "<br>";
        }
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
} else {
    echo "Table 'appointment_technicians' already exists.<br>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Technician Availability</title>
    <link rel="stylesheet" href="css/tools-equipment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .setup-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .setup-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .setup-header h2 {
            margin: 0;
            color: #2d3748;
            font-size: 1.5rem;
        }

        .setup-content {
            background-color: #f8fafc;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            white-space: pre-line;
            font-family: monospace;
            max-height: 400px;
            overflow-y: auto;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #4299e1;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
        }

        .back-link:hover {
            background-color: #3182ce;
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
            <div class="chemicals-content">
                <h2><i class="fas fa-database"></i> Database Setup for Technician Availability</h2>

                <div class="setup-container">
                    <div class="setup-header">
                        <h2><i class="fas fa-cogs"></i> Setup Results</h2>
                    </div>

                    <div class="setup-content">
                        <?php
                        // Get the output buffer content
                        $output = ob_get_contents();
                        ob_clean();
                        echo $output;
                        ?>
                    </div>

                    <a href="technicians.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Return to Technicians Page
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
