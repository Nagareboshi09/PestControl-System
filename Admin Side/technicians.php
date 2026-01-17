<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}
include '../db_connect.php';
include '../notification_functions.php';

// Function to get technician jobs
function getTechnicianJobs($technicianId) {
    global $conn;
    $jobs = [];

    // Get appointments assigned to this technician
    $appointmentsQuery = "SELECT
        'appointment' as job_type,
        a.appointment_id as id,
        a.client_name,
        a.kind_of_place,
        a.location_address,
        a.preferred_date,
        TIME_FORMAT(a.preferred_time, '%H:%i') as preferred_time,
        a.status
    FROM appointments a
    WHERE a.technician_id = ?";

    $stmt = $conn->prepare($appointmentsQuery);
    $stmt->bind_param("i", $technicianId);
    $stmt->execute();
    $result = $stmt->get_result();

    while($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }

    // Get job orders assigned to this technician
    $jobOrdersQuery = "SELECT
        'job_order' as job_type,
        j.job_order_id as id,
        j.type_of_work,
        j.preferred_date,
        TIME_FORMAT(j.preferred_time, '%H:%i') as preferred_time,
        a.client_name,
        a.location_address,
        'scheduled' as status
    FROM job_order j
    JOIN job_order_technicians jot ON j.job_order_id = jot.job_order_id
    JOIN assessment_report ar ON j.report_id = ar.report_id
    JOIN appointments a ON ar.appointment_id = a.appointment_id
    WHERE jot.technician_id = ?";

    $stmt = $conn->prepare($jobOrdersQuery);
    $stmt->bind_param("i", $technicianId);
    $stmt->execute();
    $result = $stmt->get_result();

    while($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }

    return $jobs;
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

// Function to get technician's checked tools and equipment
function getTechnicianCheckedTools($technicianId) {
    global $conn;
    $checkedTools = [];

    // First try to get tools from job_order_checklists
    $jobOrderChecklistQuery = "
        SELECT
            checked_tools,
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

    $toolIds = [];

    if ($jobOrderResult->num_rows > 0) {
        $jobOrderChecklist = $jobOrderResult->fetch_assoc();

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
                checked_items
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

    return $checkedTools;
}

// Handle file upload
function handleFileUpload($id = null) {
    // If no file was uploaded, return the old picture path for updates or empty for new records
    if (!isset($_FILES["picture"]) || $_FILES["picture"]["error"] == UPLOAD_ERR_NO_FILE) {
        return $id ? (isset($_POST['old_picture']) ? $_POST['old_picture'] : '') : '';
    }

    // Check for upload errors
    if ($_FILES["picture"]["error"] != UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini",
            UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form",
            UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded",
            UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload"
        ];

        $errorMessage = isset($errorMessages[$_FILES["picture"]["error"]])
            ? $errorMessages[$_FILES["picture"]["error"]]
            : "Unknown upload error";

        throw new Exception($errorMessage);
    }

    // Create upload directory if it doesn't exist
    $targetDir = "uploads/technicians/";
    if (!file_exists($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) {
            throw new Exception("Failed to create upload directory");
        }
    }

    // Generate unique filename
    $fileName = uniqid() . '_' . basename($_FILES["picture"]["name"]);
    $targetFile = $targetDir . $fileName;

    // Validate file type
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    if (!in_array($fileType, $allowed)) {
        throw new Exception("Only JPG, JPEG, PNG, and GIF files are allowed");
    }

    // Validate file size (max 5MB)
    if ($_FILES["picture"]["size"] > 5000000) {
        throw new Exception("File is too large. Maximum size is 5MB");
    }

    // Move the uploaded file
    if (!move_uploaded_file($_FILES["picture"]["tmp_name"], $targetFile)) {
        throw new Exception("Failed to move uploaded file");
    }

    return $targetFile;
}

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle all operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // For debugging
    $error = '';

    // Check for action_type or traditional form submission methods
    $action = '';
    if (isset($_POST['action_type'])) {
        $action = $_POST['action_type'];
    } elseif (isset($_POST['add'])) {
        $action = 'add';
    } elseif (isset($_POST['update'])) {
        $action = 'update';
    }

    if ($action === 'add') {
        try {
            // Log the POST data for debugging
            error_log('Adding technician with data: ' . print_r($_POST, true));

            // Validate required fields
            if (empty($_POST['username']) || empty($_POST['contact']) || empty($_POST['fname']) || empty($_POST['lname']) || empty($_POST['password'])) {
                throw new Exception('All required fields must be filled out');
            }

            $username = $conn->real_escape_string($_POST['username']);
            $contact = $conn->real_escape_string($_POST['contact']);
            $fname = $conn->real_escape_string($_POST['fname']);
            $lname = $conn->real_escape_string($_POST['lname']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

            // Check if username already exists
            $checkUsername = $conn->query("SELECT technician_id FROM technicians WHERE username = '$username'");
            if ($checkUsername->num_rows > 0) {
                throw new Exception('Username already exists. Please choose a different username.');
            }

            // Handle file upload
            try {
                $picture = handleFileUpload();
            } catch (Exception $e) {
                throw new Exception('File upload error: ' . $e->getMessage());
            }

            $sql = "INSERT INTO technicians (username, password, tech_contact_number, tech_fname, tech_lname, technician_picture)
                   VALUES ('$username', '$password', '$contact', '$fname', '$lname', '$picture')";
            $result = $conn->query($sql);

            if (!$result) {
                throw new Exception('Database error: ' . $conn->error);
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Technician added successfully']);
                exit;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
            error_log('Error adding technician: ' . $error);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
            }
        }
    } elseif ($action === 'update') {
        try {
            // Log the POST data for debugging
            error_log('Updating technician with data: ' . print_r($_POST, true));

            // Validate required fields
            if (empty($_POST['username']) || empty($_POST['contact']) || empty($_POST['fname']) || empty($_POST['lname'])) {
                throw new Exception('All required fields must be filled out');
            }

            $id = (int)$_POST['id'];
            if ($id <= 0) {
                throw new Exception('Invalid technician ID');
            }

            $username = $conn->real_escape_string($_POST['username']);
            $contact = $conn->real_escape_string($_POST['contact']);
            $fname = $conn->real_escape_string($_POST['fname']);
            $lname = $conn->real_escape_string($_POST['lname']);

            // Check if username already exists for another technician
            $checkUsername = $conn->query("SELECT technician_id FROM technicians WHERE username = '$username' AND technician_id != $id");
            if ($checkUsername->num_rows > 0) {
                throw new Exception('Username already exists. Please choose a different username.');
            }

            // Handle password - keep old password if no new one is provided
            $password = !empty($_POST['password'])
                        ? password_hash($_POST['password'], PASSWORD_DEFAULT)
                        : (isset($_POST['old_password']) ? $_POST['old_password'] : '');

            // Handle file upload
            try {
                $picture = handleFileUpload($id);
            } catch (Exception $e) {
                throw new Exception('File upload error: ' . $e->getMessage());
            }

            $sql = "UPDATE technicians SET
                  username='$username',
                  password='$password',
                  tech_contact_number='$contact',
                  tech_fname='$fname',
                  tech_lname='$lname',
                  technician_picture='$picture'
                  WHERE technician_id=$id";
            $result = $conn->query($sql);

            if (!$result) {
                throw new Exception('Database error: ' . $conn->error);
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Technician updated successfully']);
                exit;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
            error_log('Error updating technician: ' . $error);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
            }
        }
    }
} elseif (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM technicians WHERE technician_id=$id");
}

$technicians = $conn->query("SELECT * FROM technicians ORDER BY technician_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technicians Management - MacJ Pest Control</title>
    <link rel="stylesheet" href="css/technicians-page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        /* Bootstrap 3 compatibility fixes */
        .btn-secondary {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            color: #fff;
            background-color: #5a6268;
            border-color: #545b62;
        }
        .d-flex {
            display: flex;
        }
        .align-items-center {
            align-items: center;
        }
        .mr-2 {
            margin-right: 10px;
        }
        .mt-2 {
            margin-top: 10px;
        }
        .mt-3 {
            margin-top: 15px;
        }
        .font-weight-bold {
            font-weight: bold;
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

        <!-- Main Content -->
        <main class="main-content">
            <div class="technicians-content">
                <div class="technicians-header">
                    <h1>Technicians Management</h1>
                    <button type="button" class="btn btn-primary add-tech-btn" onclick="openModal('add')">
                        <i class="fas fa-plus"></i> Add New Technician
                    </button>
                </div>

                <!-- Technicians Summary -->
                <div class="dashboard-stats">
                    <div class="stat-card primary">
                        <div class="stat-card-content">
                            <div class="stat-card-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-card-info">
                                <h3>Total Technicians</h3>
                                <p><?= $technicians->num_rows ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card success">
                        <div class="stat-card-content">
                            <div class="stat-card-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-card-info">
                                <h3>Active Appointments</h3>
                                <p><?= $conn->query("SELECT COUNT(*) as count FROM appointments WHERE technician_id IS NOT NULL AND status != 'completed'")->fetch_assoc()['count'] ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card info">
                        <div class="stat-card-content">
                            <div class="stat-card-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="stat-card-info">
                                <h3>Job Orders</h3>
                                <p><?= $conn->query("SELECT COUNT(*) as count FROM job_order_technicians")->fetch_assoc()['count'] ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card warning">
                        <div class="stat-card-content">
                            <div class="stat-card-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-card-info">
                                <h3>High Workload</h3>
                                <p><?= $conn->query("SELECT COUNT(*) as count FROM (SELECT technician_id, COUNT(*) as workload FROM (SELECT technician_id FROM appointments WHERE technician_id IS NOT NULL AND status != 'completed' UNION ALL SELECT technician_id FROM job_order_technicians) as all_jobs GROUP BY technician_id HAVING workload > 5) as high_workload")->fetch_assoc()['count'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Controls -->
                <div class="filter-section">
                    <div class="filter-header">
                        <h2><i class="fas fa-filter"></i> Filter Technicians</h2>
                    </div>
                    <div class="filter-body">
                        <form id="filterForm" method="GET">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label for="workload-filter">Workload:</label>
                                    <select id="workload-filter" name="workload_filter" onchange="this.form.submit()">
                                        <option value="">All Technicians</option>
                                        <option value="high" <?= isset($_GET['workload_filter']) && $_GET['workload_filter'] === 'high' ? 'selected' : '' ?>>High Workload (>5)</option>
                                        <option value="medium" <?= isset($_GET['workload_filter']) && $_GET['workload_filter'] === 'medium' ? 'selected' : '' ?>>Medium Workload (3-5)</option>
                                        <option value="low" <?= isset($_GET['workload_filter']) && $_GET['workload_filter'] === 'low' ? 'selected' : '' ?>>Low Workload (<3)</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="sort-by">Sort by:</label>
                                    <select id="sort-by" name="sort" onchange="this.form.submit()">
                                        <option value="id" <?= !isset($_GET['sort']) || $_GET['sort'] === 'id' ? 'selected' : '' ?>>ID</option>
                                        <option value="name" <?= isset($_GET['sort']) && $_GET['sort'] === 'name' ? 'selected' : '' ?>>Name</option>
                                        <option value="workload" <?= isset($_GET['sort']) && $_GET['sort'] === 'workload' ? 'selected' : '' ?>>Workload</option>
                                    </select>
                                </div>
                                <div class="filter-actions">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply Filters</button>
                                    <a href="technicians.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Technicians Table -->
                <div class="table-responsive">
                    <table class="table table-hover technicians-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Technician</th>
                                <th>Contact</th>
                                <th>Name</th>
                                <th>Workload</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Reset the result pointer
                            $technicians->data_seek(0);
                            while($tech = $technicians->fetch_assoc()):
                                // Get workload count
                                $techId = $tech['technician_id'];
                                $appointmentsCount = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE technician_id = $techId AND status != 'completed'")->fetch_assoc()['count'];
                                $jobOrdersCount = $conn->query("SELECT COUNT(*) as count FROM job_order_technicians WHERE technician_id = $techId")->fetch_assoc()['count'];
                                $totalWorkload = $appointmentsCount + $jobOrdersCount;

                                // Determine workload class
                                $workloadClass = $totalWorkload > 10 ? 'high' : ($totalWorkload > 5 ? 'medium' : 'low');
                                $workloadText = $totalWorkload > 10 ? 'High' : ($totalWorkload > 5 ? 'Medium' : 'Low');
                            ?>
                            <tr>
                                <td><?= $tech['technician_id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?= !empty($tech['technician_picture']) ? $tech['technician_picture'] : 'uploads/technicians/default.png' ?>" class="tech-picture mr-2" alt="<?= htmlspecialchars($tech['username']) ?>">
                                        <a href="technician_jobs.php?technician_id=<?= $tech['technician_id'] ?>&name=<?= urlencode($tech['username']) ?>" class="tech-name-link">
                                            <?= htmlspecialchars($tech['username']) ?>
                                        </a>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($tech['tech_contact_number']) ?></td>
                                <td><?= htmlspecialchars($tech['tech_fname']) . ' ' . htmlspecialchars($tech['tech_lname']) ?></td>
                                <td>
                                    <span class="badge workload-badge workload-<?= $workloadClass ?>">
                                        <?= $workloadText ?> (<?= $totalWorkload ?>)
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="technician_jobs.php?technician_id=<?= $tech['technician_id'] ?>&name=<?= urlencode($tech['username']) ?>" class="btn btn-sm btn-info" data-technician-id="<?= $tech['technician_id'] ?>" title="View Jobs">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-sm btn-success" onclick="viewTechnicianTools(<?= $tech['technician_id'] ?>, '<?= htmlspecialchars($tech['username']) ?>')" title="View Tools">
                                            <i class="fas fa-tools"></i>
                                        </button>
                                        <a href="technician_availability.php?id=<?= $tech['technician_id'] ?>" class="btn btn-sm btn-warning" title="Manage Availability">
                                            <i class="fas fa-calendar-alt"></i>
                                        </a>
                                        <button class="btn btn-sm btn-primary" onclick="openModal('edit', <?= $tech['technician_id'] ?>, '<?= htmlspecialchars($tech['username']) ?>', '<?= htmlspecialchars($tech['tech_contact_number']) ?>', '<?= htmlspecialchars($tech['tech_fname']) ?>', '<?= htmlspecialchars($tech['tech_lname']) ?>', '<?= !empty($tech['technician_picture']) ? $tech['technician_picture'] : '' ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="openDeleteModal(<?= $tech['technician_id'] ?>, '<?= htmlspecialchars($tech['username']) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add/Edit Modal (Bootstrap 3.4.1) -->
            <div id="techModal" class="modal fade">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form id="techForm" method="POST" enctype="multipart/form-data">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalTitle"><i class="fas fa-user-plus"></i> Add New Technician</h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="id" id="techId">
                                <input type="hidden" name="old_password" id="oldPassword">
                                <input type="hidden" name="old_picture" id="oldPicture">

                                <div class="detail-section">
                                    <h3><i class="fas fa-user"></i> Account Information</h3>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label><i class="fas fa-user-tag"></i> Username <span class="text-danger">*</span></label>
                                                <input type="text" name="username" id="username" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label><i class="fas fa-lock"></i> Password</label>
                                                <div class="input-group">
                                                    <input type="password" name="password" id="password" class="form-control">
                                                    <span class="input-group-addon" onclick="togglePasswordVisibility()" style="cursor: pointer;">
                                                        <i class="toggle-password fas fa-eye"></i>
                                                    </span>
                                                </div>
                                                <span class="help-block">Leave blank to keep current password</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="detail-section">
                                    <h3><i class="fas fa-address-card"></i> Personal Information</h3>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label><i class="fas fa-user-circle"></i> First Name <span class="text-danger">*</span></label>
                                                <input type="text" name="fname" id="fname" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label><i class="fas fa-user-circle"></i> Last Name <span class="text-danger">*</span></label>
                                                <input type="text" name="lname" id="lname" class="form-control" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label><i class="fas fa-phone"></i> Contact Number <span class="text-danger">*</span></label>
                                                <input type="text" name="contact" id="contact" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label><i class="fas fa-image"></i> Technician Picture</label>
                                                <input type="file" name="picture" id="techPicture" accept="image/*" class="form-control">
                                                <div id="currentPicture" class="help-block mt-2"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" name="add" id="submitBtn">
                                    <i class="fas fa-save"></i> <span id="submitBtnText">Add Technician</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Technician Jobs Modal (Landscape Orientation) - Bootstrap 3.4.1 -->
            <div id="techJobsModal" class="modal fade tech-jobs-modal">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="jobsModalTitle"><i class="fas fa-clipboard-list"></i> <span></span></h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="detail-section">
                                <h3><i class="fas fa-chart-pie"></i> Job Summary</h3>
                                <div class="jobs-summary" id="jobsSummary">
                                    <!-- Job statistics will be inserted here -->
                                </div>
                            </div>

                            <!-- Filter Section -->
                            <div class="detail-section">
                                <h3><i class="fas fa-filter"></i> Filter Jobs</h3>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="jobTypeFilter"><i class="fas fa-tasks"></i> Job Type</label>
                                            <select id="jobTypeFilter" class="form-control">
                                                <option value="all">All Types</option>
                                                <option value="appointment">Appointments</option>
                                                <option value="job_order">Job Orders</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="statusFilter"><i class="fas fa-check-circle"></i> Status</label>
                                            <select id="statusFilter" class="form-control">
                                                <option value="all">All Statuses</option>
                                                <option value="completed">Completed</option>
                                                <option value="scheduled">Scheduled</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="dateFilter"><i class="fas fa-calendar-alt"></i> Date Range</label>
                                            <select id="dateFilter" class="form-control">
                                                <option value="all">All Dates</option>
                                                <option value="today">Today</option>
                                                <option value="this_week">This Week</option>
                                                <option value="this_month">This Month</option>
                                                <option value="custom">Custom Range</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="searchFilter"><i class="fas fa-search"></i> Search</label>
                                            <input type="text" id="searchFilter" class="form-control" placeholder="Search client, location, etc.">
                                        </div>
                                    </div>
                                </div>
                                <div id="customDateRange" style="display: none;" class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="startDate"><i class="fas fa-calendar-day"></i> Start Date</label>
                                            <input type="date" id="startDate" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="endDate"><i class="fas fa-calendar-day"></i> End Date</label>
                                            <input type="date" id="endDate" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12 text-right">
                                        <button id="applyFilters" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>
                                        <button id="resetFilters" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</button>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-section">
                                <h3><i class="fas fa-list"></i> Job List</h3>
                                <div id="techJobsList">
                                    <div class="alert alert-info jobs-loading"><i class="fas fa-spinner fa-spin"></i> Loading technician jobs...</div>
                                </div>
                            </div>

                            <!-- Tools & Equipment Checklist Section -->
                            <div class="detail-section">
                                <h3><i class="fas fa-tools"></i> Tools & Equipment Checklist</h3>
                                <div id="techToolsList">
                                    <div class="alert alert-info tools-loading"><i class="fas fa-spinner fa-spin"></i> Loading technician tools...</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Availability Scheduler Modal - Bootstrap 3.4.1 -->
            <div id="availabilityModal" class="modal fade">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-calendar-alt"></i> <span id="availabilityModalTitle">Technician Availability</span></h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="schedulerTechnicianId" value="">

                            <!-- Tabs for Weekly and Specific Date Availability -->
                            <ul class="nav nav-tabs" role="tablist">
                                <li role="presentation" class="active">
                                    <a href="#weeklyTab" aria-controls="weeklyTab" role="tab" data-toggle="tab">
                                        <i class="fas fa-calendar-week"></i> Weekly Schedule
                                    </a>
                                </li>
                                <li role="presentation">
                                    <a href="#specificDateTab" aria-controls="specificDateTab" role="tab" data-toggle="tab">
                                        <i class="fas fa-calendar-day"></i> Specific Dates
                                    </a>
                                </li>
                            </ul>

                            <!-- Tab Content -->
                            <div class="tab-content" style="padding-top: 20px;">
                                <!-- Weekly Schedule Tab -->
                                <div role="tabpanel" class="tab-pane active" id="weeklyTab">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="dayOfWeek"><i class="fas fa-calendar"></i> Day of Week</label>
                                                <select id="dayOfWeek" class="form-control">
                                                    <option value="1">Monday</option>
                                                    <option value="2">Tuesday</option>
                                                    <option value="3">Wednesday</option>
                                                    <option value="4">Thursday</option>
                                                    <option value="5">Friday</option>
                                                    <option value="6">Saturday</option>
                                                    <option value="0">Sunday</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="startTime"><i class="fas fa-clock"></i> Start Time</label>
                                                <input type="time" id="startTime" class="form-control" value="08:00">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="endTime"><i class="fas fa-clock"></i> End Time</label>
                                                <input type="time" id="endTime" class="form-control" value="17:00">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="weeklyAvailability"><i class="fas fa-toggle-on"></i> Availability</label>
                                                <select id="weeklyAvailability" class="form-control">
                                                    <option value="1">Available</option>
                                                    <option value="0">Unavailable</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group" style="margin-top: 25px;">
                                                <button type="button" id="addWeeklySlot" class="btn btn-primary">
                                                    <i class="fas fa-plus"></i> Add Time Slot
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>

                                    <h4><i class="fas fa-list"></i> Weekly Schedule</h4>
                                    <div id="weeklyScheduleList" class="schedule-list">
                                        <div class="alert alert-info">
                                            <i class="fas fa-spinner fa-spin"></i> Loading weekly schedule...
                                        </div>
                                    </div>
                                </div>

                                <!-- Specific Date Tab -->
                                <div role="tabpanel" class="tab-pane" id="specificDateTab">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="specificDate"><i class="fas fa-calendar-day"></i> Specific Date</label>
                                                <input type="date" id="specificDate" class="form-control" value="<?= date('Y-m-d') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="specificStartTime"><i class="fas fa-clock"></i> Start Time</label>
                                                <input type="time" id="specificStartTime" class="form-control" value="08:00">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="specificEndTime"><i class="fas fa-clock"></i> End Time</label>
                                                <input type="time" id="specificEndTime" class="form-control" value="17:00">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="specificAvailability"><i class="fas fa-toggle-on"></i> Availability</label>
                                                <select id="specificAvailability" class="form-control">
                                                    <option value="1">Available</option>
                                                    <option value="0">Unavailable</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group" style="margin-top: 25px;">
                                                <button type="button" id="addSpecificDateSlot" class="btn btn-primary">
                                                    <i class="fas fa-plus"></i> Add Time Slot
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>

                                    <h4><i class="fas fa-list"></i> Specific Date Schedule</h4>
                                    <div id="specificDateScheduleList" class="schedule-list">
                                        <div class="alert alert-info">
                                            <i class="fas fa-spinner fa-spin"></i> Loading specific date schedule...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete Confirmation Modal - Bootstrap 3.4.1 -->
            <div id="deleteModal" class="modal fade delete-modal">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Delete Technician</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div>
                                    <strong>Warning:</strong> This action cannot be undone. All data associated with this technician will be permanently removed.
                                </div>
                            </div>

                            <div class="detail-section">
                                <h3><i class="fas fa-user-slash"></i> Delete Confirmation</h3>
                                <p class="text-center">Are you sure you want to delete this technician?</p>
                                <div id="deleteTechnicianName" class="text-center" style="font-weight: bold; font-size: 1.2rem; margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-radius: 8px;"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete Permanently
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <style>
        /* Styles for tools and equipment section */
        .tools-summary {
            margin-bottom: 20px;
        }

        .summary-box {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .summary-box i {
            font-size: 24px;
            color: #3B82F6;
            margin-right: 15px;
        }

        .summary-content h4 {
            margin: 0 0 5px;
            font-size: 14px;
            color: #6B7280;
        }

        .summary-content p {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #1F2937;
        }

        .tools-category {
            margin-bottom: 20px;
        }

        .tools-category h4 {
            font-size: 16px;
            font-weight: 600;
            color: #3B82F6;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #E5E7EB;
        }

        .tool-item {
            background-color: #fff;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .tool-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: #1F2937;
        }

        .tool-name i {
            color: #10B981;
            margin-right: 8px;
        }

        .tool-description {
            font-size: 13px;
            color: #6B7280;
        }

        /* Styles for availability scheduler */
        .schedule-list {
            margin-top: 15px;
            max-height: 400px;
            overflow-y: auto;
        }

        .schedule-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 10px;
            border-left: 4px solid #3B82F6;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .schedule-item.unavailable {
            border-left-color: #EF4444;
        }

        .schedule-info {
            flex: 1;
        }

        .schedule-day {
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 3px;
        }

        .schedule-time {
            color: #6B7280;
            font-size: 14px;
        }

        .schedule-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .status-available {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .status-unavailable {
            background-color: #FEE2E2;
            color: #B91C1C;
        }

        .schedule-actions {
            display: flex;
            align-items: center;
        }

        .remove-slot {
            cursor: pointer;
            color: #EF4444;
            margin-left: 10px;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s;
        }

        .remove-slot:hover {
            background-color: #FEE2E2;
        }

        .toggle-availability {
            cursor: pointer;
            margin-left: 10px;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s;
        }

        .toggle-availability:hover {
            background-color: #E5E7EB;
        }

        .no-schedule-message {
            text-align: center;
            padding: 20px;
            color: #6B7280;
            font-style: italic;
        }
    </style>

    <script>
    $(document).ready(function() {
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();

        // Mobile menu toggle
        $('#menuToggle').on('click', function() {
            $('.sidebar').toggleClass('active');
        });

        // Show file name when selected
        $('#techPicture').on('change', function() {
            let fileName = $(this).val().split('\\').pop();
            // Remove any existing selected-file spans
            $(this).siblings('.selected-file').remove();
            if (fileName) {
                $(this).after('<span class="selected-file">' + fileName + '</span>');
            }
        });

        // Notification functionality is handled by notifications.js

        // Handle form submission
        $('#techForm').submit(function(e) {
            e.preventDefault();

            // Validate form
            let isValid = true;
            let errorMessage = '';

            // Check required fields
            $(this).find('[required]').each(function() {
                if ($(this).val() === '') {
                    isValid = false;
                    errorMessage = 'Please fill out all required fields';
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });

            if (!isValid) {
                alert(errorMessage);
                return false;
            }

            // Use FormData to handle file uploads
            const formData = new FormData(this);

            // Show loading indicator
            const submitBtn = $('#submitBtn');
            const originalBtnText = submitBtn.html();
            submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            submitBtn.prop('disabled', true);

            $.ajax({
                type: 'POST',
                url: 'technicians.php',
                data: formData,
                dataType: 'json',
                contentType: false, // Required for FormData
                processData: false, // Required for FormData
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' // Add this header to identify AJAX requests
                },
                success: function(response) {
                    // Reset button
                    submitBtn.html(originalBtnText);
                    submitBtn.prop('disabled', false);

                    if (response.success) {
                        $('#techModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.message || 'Failed to save technician'));
                    }
                },
                error: function(xhr, status, error) {
                    // Reset button
                    submitBtn.html(originalBtnText);
                    submitBtn.prop('disabled', false);

                    console.error('AJAX Error:', xhr.responseText);
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            alert('Error: ' + response.message);
                        } else {
                            alert('Error: Unable to process your request. Please try again.');
                        }
                    } catch (e) {
                        alert('Error: Unable to process your request. Please try again.');
                    }
                }
            });
        });

        // Define openModal function inside document ready
        window.openModal = function(action, id = null, username = '', contact = '', fname = '', lname = '', picture = '') {
            const modalTitle = document.getElementById('modalTitle');
            const submitBtnText = document.getElementById('submitBtnText');
            const passwordField = document.getElementById('password');
            const passwordHint = document.querySelector('.help-block');
            const techForm = document.getElementById('techForm');

            // Reset form validation
            $('.is-invalid').removeClass('is-invalid');

            // Reset the form completely to clear any previous data
            techForm.reset();

            if(action === 'add') {
                modalTitle.innerHTML = '<i class="fas fa-user-plus"></i> Add New Technician';
                submitBtnText.textContent = 'Add Technician';

                // Set form for adding new technician
                document.getElementById('techId').value = '';
                passwordField.required = true;
                passwordField.setAttribute('required', 'required');
                if (passwordHint) passwordHint.style.display = 'none';
                document.getElementById('currentPicture').innerHTML = '';
                document.getElementById('submitBtn').name = 'add';
                document.getElementById('submitBtn').value = 'add';

                // Add a hidden field to indicate this is an add operation
                if (!document.getElementById('action_type')) {
                    const actionTypeInput = document.createElement('input');
                    actionTypeInput.type = 'hidden';
                    actionTypeInput.id = 'action_type';
                    actionTypeInput.name = 'action_type';
                    actionTypeInput.value = 'add';
                    techForm.appendChild(actionTypeInput);
                } else {
                    document.getElementById('action_type').value = 'add';
                }
            } else {
                modalTitle.innerHTML = '<i class="fas fa-user-edit"></i> Edit Technician';
                submitBtnText.textContent = 'Update Technician';

                // Fill form with technician data
                document.getElementById('techId').value = id;
                document.getElementById('username').value = username;
                document.getElementById('contact').value = contact;
                document.getElementById('fname').value = fname;
                document.getElementById('lname').value = lname;
                document.getElementById('oldPassword').value = "<?= isset($tech['password']) ? $tech['password'] : '' ?>";
                document.getElementById('oldPicture').value = picture;

                // Password is not required for updates
                passwordField.required = false;
                passwordField.removeAttribute('required');
                if (passwordHint) passwordHint.style.display = 'block';

                // Show current picture if available
                if (picture) {
                    document.getElementById('currentPicture').innerHTML =
                        `<div style="display: flex; align-items: center; gap: 10px;">
                            <img src="${picture}" class="tech-picture" alt="Current Picture">
                            <span>Current profile picture</span>
                        </div>`;
                } else {
                    document.getElementById('currentPicture').innerHTML =
                        '<div style="color: var(--text-light);">No profile picture set</div>';
                }

                document.getElementById('submitBtn').name = 'update';
                document.getElementById('submitBtn').value = 'update';

                // Add a hidden field to indicate this is an update operation
                if (!document.getElementById('action_type')) {
                    const actionTypeInput = document.createElement('input');
                    actionTypeInput.type = 'hidden';
                    actionTypeInput.id = 'action_type';
                    actionTypeInput.name = 'action_type';
                    actionTypeInput.value = 'update';
                    techForm.appendChild(actionTypeInput);
                } else {
                    document.getElementById('action_type').value = 'update';
                }
            }

            // Show modal using Bootstrap
            $('#techModal').modal('show');
        };

        // Define togglePasswordVisibility function inside document ready
        window.togglePasswordVisibility = function() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.querySelector('.toggle-password');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        };

        // Define openDeleteModal function inside document ready
        window.openDeleteModal = function(techId, techName) {
            // Set the technician name in the modal
            document.getElementById('deleteTechnicianName').textContent = techName;

            // Set the delete confirmation button href
            document.getElementById('confirmDeleteBtn').href = '?delete=' + techId;

            // Show the modal
            $('#deleteModal').modal('show');
        };

        // Define viewTechnicianJobs function inside document ready
        window.viewTechnicianJobs = function(techId, techName) {
            // Redirect to the technician jobs page
            window.location.href = 'technician_jobs.php?technician_id=' + techId + '&name=' + encodeURIComponent(techName);
        };

        // Define viewTechnicianTools function inside document ready
        window.viewTechnicianTools = function(techId, techName) {
            // Create a modal to display the technician's tools
            if (!$('#techToolsModal').length) {
                // Create the modal if it doesn't exist
                const modalHtml = `
                <div id="techToolsModal" class="modal fade">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-tools"></i> <span id="techToolsModalTitle"></span></h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div id="techToolsModalContent">
                                    <div class="alert alert-info tools-loading"><i class="fas fa-spinner fa-spin"></i> Loading technician tools...</div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
                `;
                $('body').append(modalHtml);
            }

            // Set the modal title
            $('#techToolsModalTitle').text(techName + "'s Tools & Equipment");

            // Show the modal
            $('#techToolsModal').modal('show');

            // Load the technician's tools
            $('#techToolsModalContent').html('<div class="alert alert-info tools-loading"><i class="fas fa-spinner fa-spin"></i> Loading technician tools...</div>');

            $.ajax({
                url: 'get_technician_tools.php',
                type: 'GET',
                data: { technician_id: techId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#techToolsModalContent').html(response.html);
                    } else {
                        $('#techToolsModalContent').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ' + (response.error || 'Failed to load tools') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#techToolsModalContent').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ' + error + '</div>');
                }
            });
        };

        // Define openAvailabilityScheduler function inside document ready
        window.openAvailabilityScheduler = function(techId, techName) {
            // Set the technician ID and name in the modal
            $('#schedulerTechnicianId').val(techId);
            $('#availabilityModalTitle').text(techName + "'s Availability Schedule");

            // Show the modal
            $('#availabilityModal').modal('show');

            // Load the technician's availability schedule
            loadTechnicianAvailability(techId);
        };

        // Function to load technician availability
        function loadTechnicianAvailability(technicianId) {
            // Show loading indicators
            $('#weeklyScheduleList').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Loading weekly schedule...</div>');
            $('#specificDateScheduleList').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Loading specific date schedule...</div>');

            $.ajax({
                url: 'manage_technician_availability.php',
                type: 'GET',
                data: {
                    action: 'get',
                    technician_id: technicianId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Render weekly schedule
                        renderWeeklySchedule(response.data.weekly);

                        // Render specific date schedule
                        renderSpecificDateSchedule(response.data.specific);
                    } else {
                        const errorMessage = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ' + (response.message || 'Failed to load availability') + '</div>';
                        $('#weeklyScheduleList').html(errorMessage);
                        $('#specificDateScheduleList').html(errorMessage);
                    }
                },
                error: function(xhr, status, error) {
                    const errorMessage = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ' + error + '</div>';
                    $('#weeklyScheduleList').html(errorMessage);
                    $('#specificDateScheduleList').html(errorMessage);
                }
            });
        }

        // Function to render weekly schedule
        function renderWeeklySchedule(weeklySchedule) {
            if (!weeklySchedule || weeklySchedule.length === 0) {
                $('#weeklyScheduleList').html('<div class="no-schedule-message"><i class="fas fa-info-circle"></i> No weekly schedule set up yet.</div>');
                return;
            }

            const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            let html = '';

            weeklySchedule.forEach(slot => {
                const isAvailable = parseInt(slot.is_available) === 1;
                const dayName = dayNames[slot.day_of_week];
                const startTime = formatTime(slot.start_time);
                const endTime = formatTime(slot.end_time);

                html += `
                <div class="schedule-item ${isAvailable ? 'available' : 'unavailable'}">
                    <div class="schedule-info">
                        <div class="schedule-day">${dayName}</div>
                        <div class="schedule-time">${startTime} - ${endTime}</div>
                    </div>
                    <div class="schedule-status ${isAvailable ? 'status-available' : 'status-unavailable'}">
                        ${isAvailable ? 'Available' : 'Unavailable'}
                    </div>
                    <div class="schedule-actions">
                        <div class="toggle-availability" onclick="toggleAvailability(${slot.id}, ${isAvailable ? 0 : 1})">
                            <i class="fas fa-${isAvailable ? 'ban' : 'check'}"></i>
                        </div>
                        <div class="remove-slot" onclick="removeAvailabilitySlot(${slot.id})">
                            <i class="fas fa-times"></i>
                        </div>
                    </div>
                </div>
                `;
            });

            $('#weeklyScheduleList').html(html);
        }

        // Function to render specific date schedule
        function renderSpecificDateSchedule(specificSchedule) {
            if (!specificSchedule || specificSchedule.length === 0) {
                $('#specificDateScheduleList').html('<div class="no-schedule-message"><i class="fas fa-info-circle"></i> No specific date schedule set up yet.</div>');
                return;
            }

            let html = '';

            specificSchedule.forEach(slot => {
                const isAvailable = parseInt(slot.is_available) === 1;
                const formattedDate = formatDate(slot.specific_date);
                const startTime = formatTime(slot.start_time);
                const endTime = formatTime(slot.end_time);

                html += `
                <div class="schedule-item ${isAvailable ? 'available' : 'unavailable'}">
                    <div class="schedule-info">
                        <div class="schedule-day">${formattedDate}</div>
                        <div class="schedule-time">${startTime} - ${endTime}</div>
                    </div>
                    <div class="schedule-status ${isAvailable ? 'status-available' : 'status-unavailable'}">
                        ${isAvailable ? 'Available' : 'Unavailable'}
                    </div>
                    <div class="schedule-actions">
                        <div class="toggle-availability" onclick="toggleAvailability(${slot.id}, ${isAvailable ? 0 : 1})">
                            <i class="fas fa-${isAvailable ? 'ban' : 'check'}"></i>
                        </div>
                        <div class="remove-slot" onclick="removeAvailabilitySlot(${slot.id})">
                            <i class="fas fa-times"></i>
                        </div>
                    </div>
                </div>
                `;
            });

            $('#specificDateScheduleList').html(html);
        }

        // Helper function to format time (HH:MM:SS to h:MM AM/PM)
        function formatTime(timeString) {
            if (!timeString) return '';

            const timeParts = timeString.split(':');
            let hours = parseInt(timeParts[0]);
            const minutes = timeParts[1];
            const ampm = hours >= 12 ? 'PM' : 'AM';

            hours = hours % 12;
            hours = hours ? hours : 12; // Convert 0 to 12 for 12 AM

            return hours + ':' + minutes + ' ' + ampm;
        }

        // Helper function to format date (YYYY-MM-DD to Month DD, YYYY)
        function formatDate(dateString) {
            if (!dateString) return '';

            const date = new Date(dateString);
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }

        // Add weekly time slot
        $('#addWeeklySlot').on('click', function() {
            const technicianId = $('#schedulerTechnicianId').val();
            const dayOfWeek = $('#dayOfWeek').val();
            const startTime = $('#startTime').val();
            const endTime = $('#endTime').val();
            const isAvailable = $('#weeklyAvailability').val();

            if (!technicianId || !dayOfWeek || !startTime || !endTime) {
                alert('Please fill in all required fields');
                return;
            }

            // Validate time range
            if (startTime >= endTime) {
                alert('Start time must be before end time');
                return;
            }

            $.ajax({
                url: 'manage_technician_availability.php?action=add',
                type: 'POST',
                data: JSON.stringify({
                    technician_id: technicianId,
                    day_of_week: dayOfWeek,
                    start_time: startTime,
                    end_time: endTime,
                    is_available: isAvailable
                }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Reload the technician's availability schedule
                        loadTechnicianAvailability(technicianId);
                    } else {
                        alert('Error: ' + (response.message || 'Failed to add time slot'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                }
            });
        });

        // Add specific date time slot
        $('#addSpecificDateSlot').on('click', function() {
            const technicianId = $('#schedulerTechnicianId').val();
            const specificDate = $('#specificDate').val();
            const startTime = $('#specificStartTime').val();
            const endTime = $('#specificEndTime').val();
            const isAvailable = $('#specificAvailability').val();

            if (!technicianId || !specificDate || !startTime || !endTime) {
                alert('Please fill in all required fields');
                return;
            }

            // Validate time range
            if (startTime >= endTime) {
                alert('Start time must be before end time');
                return;
            }

            $.ajax({
                url: 'manage_technician_availability.php?action=add',
                type: 'POST',
                data: JSON.stringify({
                    technician_id: technicianId,
                    specific_date: specificDate,
                    start_time: startTime,
                    end_time: endTime,
                    is_available: isAvailable
                }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Reload the technician's availability schedule
                        loadTechnicianAvailability(technicianId);
                    } else {
                        alert('Error: ' + (response.message || 'Failed to add time slot'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                }
            });
        });

        // Define toggleAvailability function in global scope
        window.toggleAvailability = function(slotId, newAvailability) {
            const technicianId = $('#schedulerTechnicianId').val();

            $.ajax({
                url: 'manage_technician_availability.php?action=add',
                type: 'POST',
                data: JSON.stringify({
                    id: slotId,
                    is_available: newAvailability
                }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Reload the technician's availability schedule
                        loadTechnicianAvailability(technicianId);
                    } else {
                        alert('Error: ' + (response.message || 'Failed to update availability'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                }
            });
        };

        // Define removeAvailabilitySlot function in global scope
        window.removeAvailabilitySlot = function(slotId) {
            if (!confirm('Are you sure you want to remove this time slot?')) {
                return;
            }

            const technicianId = $('#schedulerTechnicianId').val();

            $.ajax({
                url: 'manage_technician_availability.php',
                type: 'GET',
                data: {
                    action: 'delete',
                    id: slotId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Reload the technician's availability schedule
                        loadTechnicianAvailability(technicianId);
                    } else {
                        alert('Error: ' + (response.message || 'Failed to remove time slot'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                }
            });
        };

        // Function to load technician tools
        function loadTechnicianTools(technicianId) {
            $('#techToolsList').html('<div class="alert alert-info tools-loading"><i class="fas fa-spinner fa-spin"></i> Loading technician tools...</div>');

            $.ajax({
                url: 'get_technician_tools.php',
                type: 'GET',
                data: { technician_id: technicianId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#techToolsList').html(response.html);
                    } else {
                        $('#techToolsList').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ' + (response.error || 'Failed to load tools') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#techToolsList').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ' + error + '</div>');
                }
            });
        }

        // Load technician tools when viewing technician jobs
        $('#techJobsModal').on('show.bs.modal', function(e) {
            const techId = $(e.relatedTarget).data('technician-id');
            if (techId) {
                loadTechnicianTools(techId);
            }
        });

        // This function is no longer used as we're redirecting to a separate page
        // Keeping it commented out for reference
        /*
        function fetchTechnicianJobs(techId) {
            $.ajax({
                url: 'get_technician_jobs.php',
                method: 'GET',
                data: { technician_id: techId },
                dataType: 'json',
                success: function(data) {
                    // Extract jobs and checklist data from the response
                    const jobs = data.jobs || [];
                    const checklist = data.checklist || null;

                    // Calculate statistics
                    const appointmentsCount = jobs.filter(job => job.job_type === 'appointment').length;
                    const completedCount = jobs.filter(job => job.status === 'completed').length;
                    const jobOrdersCount = jobs.filter(job => job.job_type === 'job_order').length;

                    // Create summary cards in a horizontal layout with modern styling
                    let summaryHtml = `
                        <div class="job-stat-card">
                            <div class="job-stat-icon"><i class="fas fa-calendar-check"></i></div>
                            <div class="job-stat-number job-stat-appointments">${appointmentsCount}</div>
                            <div class="job-stat-label">Appointments</div>
                        </div>
                        <div class="job-stat-card">
                            <div class="job-stat-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="job-stat-number job-stat-completed">${completedCount}</div>
                            <div class="job-stat-label">Completed</div>
                        </div>
                        <div class="job-stat-card">
                            <div class="job-stat-icon"><i class="fas fa-clipboard-list"></i></div>
                            <div class="job-stat-number job-stat-orders">${jobOrdersCount}</div>
                            <div class="job-stat-label">Job Orders</div>
                        </div>
                        <div class="job-stat-card">
                            <div class="job-stat-icon"><i class="fas fa-percentage"></i></div>
                            <div class="job-stat-number job-stat-completion">${completedCount > 0 && (appointmentsCount + jobOrdersCount) > 0 ? Math.round((completedCount / (appointmentsCount + jobOrdersCount)) * 100) : 0}%</div>
                            <div class="job-stat-label">Completion Rate</div>
                        </div>
                    `;

                    // Add checklist card if available
                    if (checklist) {
                        const checklistClass = checklist.percentage >= 80 ? 'success' :
                                            (checklist.percentage >= 50 ? 'warning' : 'danger');

                        summaryHtml += `
                        <div class="job-stat-card checklist-card checklist-${checklistClass}">
                            <div class="job-stat-icon"><i class="fas fa-tools"></i></div>
                            <div class="job-stat-number">${checklist.percentage}%</div>
                            <div class="job-stat-label">Tools Checklist</div>
                            <div class="checklist-details">${checklist.checked_count}/${checklist.total_items} items checked</div>
                            <div class="checklist-progress">
                                <div class="checklist-progress-bar checklist-${checklistClass}" style="width: ${checklist.percentage}%"></div>
                            </div>
                        </div>
                        `;
                    } else {
                        summaryHtml += `
                        <div class="job-stat-card checklist-card checklist-none">
                            <div class="job-stat-icon"><i class="fas fa-tools"></i></div>
                            <div class="job-stat-number">0%</div>
                            <div class="job-stat-label">Tools Checklist</div>
                            <div class="checklist-details">No checklist completed today</div>
                            <div class="checklist-progress">
                                <div class="checklist-progress-bar" style="width: 0%"></div>
                            </div>
                        </div>
                        `;
                    }
                    document.getElementById('jobsSummary').innerHTML = summaryHtml;

                    // Render the jobs table using our rendering function
                    renderJobsTable(jobs);

                    // Store the original data for filtering
                    window.technicianJobsData = jobs;

                    // Initialize filter functionality
                    initializeJobFilters();
                },
                error: function(error) {
                    console.error('Error:', error);
                    document.getElementById('techJobsList').innerHTML =
                        '<div class="no-jobs-message"><i class="fas fa-exclamation-triangle"></i>Error loading jobs. Please try again.</div>';
                }
            });
        }
        */

        // Function to initialize job filters
        function initializeJobFilters() {
            // Show/hide custom date range based on selection
            $('#dateFilter').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#customDateRange').show();
                } else {
                    $('#customDateRange').hide();
                }
            });

            // Set today's date as default for date inputs
            const today = new Date().toISOString().split('T')[0];
            $('#startDate').val(today);
            $('#endDate').val(today);

            // Apply filters button click handler
            $('#applyFilters').on('click', function() {
                applyJobFilters();
            });

            // Reset filters button click handler
            $('#resetFilters').on('click', function() {
                resetJobFilters();
            });

            // Search filter keyup handler (for real-time filtering)
            $('#searchFilter').on('keyup', function() {
                if ($(this).val().length >= 3 || $(this).val().length === 0) {
                    applyJobFilters();
                }
            });
        }

        // Function to apply job filters
        function applyJobFilters() {
            if (!window.technicianJobsData) return;

            const jobType = $('#jobTypeFilter').val();
            const status = $('#statusFilter').val();
            const dateRange = $('#dateFilter').val();
            const searchTerm = $('#searchFilter').val().toLowerCase();

            // Filter the data
            let filteredData = window.technicianJobsData.filter(job => {
                // Job Type filter
                if (jobType !== 'all' && job.job_type !== jobType) return false;

                // Status filter
                if (status !== 'all' && job.status !== status) return false;

                // Date filter
                if (dateRange !== 'all') {
                    const jobDate = new Date(job.preferred_date);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    if (dateRange === 'today') {
                        if (jobDate.toDateString() !== today.toDateString()) return false;
                    } else if (dateRange === 'this_week') {
                        const startOfWeek = new Date(today);
                        startOfWeek.setDate(today.getDate() - today.getDay()); // Start of week (Sunday)
                        const endOfWeek = new Date(startOfWeek);
                        endOfWeek.setDate(startOfWeek.getDate() + 6); // End of week (Saturday)

                        if (jobDate < startOfWeek || jobDate > endOfWeek) return false;
                    } else if (dateRange === 'this_month') {
                        const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                        const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);

                        if (jobDate < startOfMonth || jobDate > endOfMonth) return false;
                    } else if (dateRange === 'custom') {
                        const startDate = new Date($('#startDate').val());
                        const endDate = new Date($('#endDate').val());
                        endDate.setHours(23, 59, 59, 999); // End of the selected day

                        if (jobDate < startDate || jobDate > endDate) return false;
                    }
                }

                // Search filter
                if (searchTerm) {
                    const searchFields = [
                        job.client_name || '',
                        job.location_address || '',
                        job.kind_of_place || '',
                        job.type_of_work || ''
                    ];

                    return searchFields.some(field => field.toLowerCase().includes(searchTerm));
                }

                return true;
            });

            // Update statistics
            const appointmentsCount = filteredData.filter(job => job.job_type === 'appointment').length;
            const completedCount = filteredData.filter(job => job.status === 'completed').length;
            const jobOrdersCount = filteredData.filter(job => job.job_type === 'job_order').length;

            const summaryHtml = `
                <div class="job-stat-card">
                    <div class="job-stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="job-stat-number job-stat-appointments">${appointmentsCount}</div>
                    <div class="job-stat-label">Appointments</div>
                </div>
                <div class="job-stat-card">
                    <div class="job-stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="job-stat-number job-stat-completed">${completedCount}</div>
                    <div class="job-stat-label">Completed</div>
                </div>
                <div class="job-stat-card">
                    <div class="job-stat-icon"><i class="fas fa-clipboard-list"></i></div>
                    <div class="job-stat-number job-stat-orders">${jobOrdersCount}</div>
                    <div class="job-stat-label">Job Orders</div>
                </div>
                <div class="job-stat-card">
                    <div class="job-stat-icon"><i class="fas fa-percentage"></i></div>
                    <div class="job-stat-number job-stat-completion">${completedCount > 0 && (appointmentsCount + jobOrdersCount) > 0 ? Math.round((completedCount / (appointmentsCount + jobOrdersCount)) * 100) : 0}%</div>
                    <div class="job-stat-label">Completion Rate</div>
                </div>
            `;
            document.getElementById('jobsSummary').innerHTML = summaryHtml;

            // Render the filtered data
            renderJobsTable(filteredData);
        }

        // Function to reset job filters
        function resetJobFilters() {
            $('#jobTypeFilter').val('all');
            $('#statusFilter').val('all');
            $('#dateFilter').val('all');
            $('#searchFilter').val('');
            $('#customDateRange').hide();

            // Apply filters (which will now show all data)
            applyJobFilters();
        }

        // Function to render the jobs table
        function renderJobsTable(data) {
            console.log('Rendering jobs table with data:', data);
            let jobsHtml = '';

            if (!data || data.length === 0) {
                jobsHtml = '<div class="no-jobs-message"><i class="fas fa-filter"></i> No jobs match the selected filters.</div>';
                console.log('No jobs to display');
            } else {
                jobsHtml = '<div class="jobs-table-container" style="padding: 20px;"><table class="jobs-table" style="border-spacing: 2px;">' +
                    '<thead><tr>' +
                    '<th>ID</th>' +
                    '<th>Type</th>' +
                    '<th>Client</th>' +
                    '<th>Details</th>' +
                    '<th>Date/Time</th>' +
                    '<th>Location</th>' +
                    '<th>Status</th>' +
                    '</tr></thead><tbody>';

                data.forEach((job, index) => {
                    console.log(`Processing job ${index}:`, job);
                    try {
                        const isAppointment = job.job_type === 'appointment';
                        const jobType = isAppointment ? 'Appointment' : 'Job Order';
                        const jobTypeClass = isAppointment ? 'job-type-appointment' : 'job-type-job-order';
                        const jobTypeIcon = isAppointment ? '<i class="fas fa-calendar-check"></i>' : '<i class="fas fa-tools"></i>';
                        const jobDetails = isAppointment ? job.kind_of_place : job.type_of_work;
                        const dateTime = job.preferred_date + ' at ' + job.preferred_time;
                        const statusClass = job.status === 'completed' ? 'status-completed' : 'status-scheduled';
                        const statusIcon = job.status === 'completed' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-clock"></i>';

                        jobsHtml += '<tr>' +
                            '<td>' + (job.id || '-') + '</td>' +
                            '<td><div class="job-type-cell"><div class="job-type-icon ' + jobTypeClass + '">' + jobTypeIcon + '</div>' + jobType + '</div></td>' +
                            '<td>' + job.client_name + '</td>' +
                            '<td>' + jobDetails + '</td>' +
                            '<td>' + dateTime + '</td>' +
                            '<td>' + job.location_address + '</td>' +
                            '<td><span class="status-badge ' + statusClass + '">' + statusIcon + ' ' + job.status + '</span></td>' +
                            '</tr>';
                    } catch (error) {
                        console.error(`Error processing job ${index}:`, error, job);
                    }
                });

                jobsHtml += '</tbody></table></div>';
            }

            console.log('Setting HTML for techJobsList');
            const techJobsList = document.getElementById('techJobsList');
            if (techJobsList) {
                techJobsList.innerHTML = jobsHtml;
            } else {
                console.error('techJobsList element not found');
            }
        }

    // Document ready end
    });
    </script>

    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <script src="js/chemical-notifications.js"></script>
    <script>
        // Initialize mobile menu and notifications when the page loads
        $(document).ready(function() {
            // Mobile menu toggle
            $('#menuToggle').on('click', function() {
                $('.sidebar').toggleClass('active');
            });

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
