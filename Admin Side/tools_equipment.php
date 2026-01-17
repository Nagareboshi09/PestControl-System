<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: ../SignIn.php");
    exit;
}
require_once '../db_connect.php';
require_once '../notification_functions.php';

// Define default categories
$default_categories = [
    'General Pest Control',
    'Termite',
    'Termite Treatment',
    'Weed Control',
    'Bed Bugs',
    'Disinfection',
    'Rodent Control',
    'Termite Control'
];

// Get Dashboard Metrics
try {
    // Check if the tools_equipment table exists
    $result = $conn->query("SHOW TABLES LIKE 'tools_equipment'");
    if ($result->num_rows == 0) {
        // Table doesn't exist, show a message with a link to create it
        echo '<div style="padding: 20px; background-color: #f8d7da; color: #721c24; margin: 20px; border-radius: 5px;">
                <h3>Table Not Found</h3>
                <p>The tools_equipment table does not exist in the database.</p>
                <p><a href="create_tools_table.php" class="btn btn-primary">Create Table and Add Sample Data</a></p>
              </div>';
        exit;
    }

    // Total Tools and Equipment
    $result = $conn->query("SELECT COUNT(*) AS total FROM tools_equipment");
    $row = $result->fetch_assoc();
    $total_tools = $row['total'];

    // Check if status column exists
    $result = $conn->query("SHOW COLUMNS FROM tools_equipment LIKE 'status'");
    $statusColumnExists = $result->num_rows > 0;

    // Count by Status (if column exists)
    $status_counts = [
        'in stock' => 0,
        'in use' => 0
    ];

    if ($statusColumnExists) {
        $result = $conn->query("SELECT status, COUNT(*) as count FROM tools_equipment GROUP BY status");
        while ($status = $result->fetch_assoc()) {
            $status_counts[$status['status'] ?? 'in stock'] = $status['count'];
        }
    } else {
        // If status column doesn't exist, consider all tools as "in stock"
        $status_counts['in stock'] = $total_tools;
    }

    // Count by Category
    $result = $conn->query("SELECT category, COUNT(*) as count FROM tools_equipment GROUP BY category");
    $category_counts = [];
    while ($cat = $result->fetch_assoc()) {
        $category_counts[$cat['category']] = $cat['count'];
    }

} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submission for NEW tool
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $name = $_POST['name'];
        $description = $_POST['description'] ?? null;
        $status = $_POST['status'] ?? 'in stock';

        // Handle multiple categories
        if (isset($_POST['categories']) && is_array($_POST['categories'])) {
            $category = implode(', ', $_POST['categories']);
        } else {
            $category = $_POST['category'] ?? '';
        }

        // Check if status column exists
        $result = $conn->query("SHOW COLUMNS FROM tools_equipment LIKE 'status'");
        $statusColumnExists = $result->num_rows > 0;

        if ($statusColumnExists) {
            $stmt = $conn->prepare("INSERT INTO tools_equipment
                    (name, category, description, status)
                    VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $category, $description, $status);
        } else {
            $stmt = $conn->prepare("INSERT INTO tools_equipment
                    (name, category, description)
                    VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $category, $description);
        }

        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Tool added successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to add tool']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Get all tools and equipment
try {
    // Check if a reset has been performed recently (within the last 5 minutes)
    $resetPerformed = isset($_SESSION['tools_reset_performed']) && $_SESSION['tools_reset_performed'] === true;
    $resetTime = isset($_SESSION['tools_reset_time']) ? $_SESSION['tools_reset_time'] : 0;
    $resetExpired = (time() - $resetTime) > 300; // 5 minutes

    // If reset has expired, clear the session flag
    if ($resetPerformed && $resetExpired) {
        unset($_SESSION['tools_reset_performed']);
        unset($_SESSION['tools_reset_time']);
        $resetPerformed = false;
    }

    // Build the query based on whether a reset has been performed
    if ($resetPerformed) {
        // After reset, ignore checklist relationship and show all tools based on their status column
        $baseQuery = "SELECT t.*,
                     COALESCE(t.status, 'in stock') AS current_status,
                     NULL AS technician_name,
                     NULL AS job_order_id
                     FROM tools_equipment t";
    } else {
        // Normal query that considers checklist relationship
        $baseQuery = "SELECT t.*,
                     CASE
                         WHEN joc.id IS NOT NULL THEN 'in use'
                         ELSE COALESCE(t.status, 'in stock')
                     END AS current_status,
                     CASE
                         WHEN joc.id IS NOT NULL THEN CONCAT(tech.tech_fname, ' ', tech.tech_lname)
                         ELSE NULL
                     END AS technician_name,
                     CASE
                         WHEN joc.id IS NOT NULL THEN jo.job_order_id
                         ELSE NULL
                     END AS job_order_id
                     FROM tools_equipment t
                     LEFT JOIN job_order_checklists joc ON FIND_IN_SET(t.id, joc.checked_items) > 0
                        AND joc.id = (
                            SELECT MAX(id) FROM job_order_checklists
                            WHERE FIND_IN_SET(t.id, checked_items) > 0
                        )
                     LEFT JOIN job_order jo ON joc.job_order_id = jo.job_order_id
                     LEFT JOIN technicians tech ON joc.technician_id = tech.technician_id";
    }

    $whereClause = [];

    // Category filter
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $category = $_GET['category'];
        $whereClause[] = "t.category LIKE '%" . $conn->real_escape_string($category) . "%'";
    }

    // Search filter
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = $_GET['search'];
        $whereClause[] = "(t.name LIKE '%" . $conn->real_escape_string($search) . "%' OR
                          t.category LIKE '%" . $conn->real_escape_string($search) . "%' OR
                          t.description LIKE '%" . $conn->real_escape_string($search) . "%')";
    }

    // Status filter
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        if ($_GET['status'] === 'in use') {
            $whereClause[] = "(joc.id IS NOT NULL OR t.status = 'in use')";
        } else {
            $whereClause[] = "(joc.id IS NULL AND (t.status IS NULL OR t.status = 'in stock'))";
        }
    }

    // Add WHERE clause if any filters are applied
    if (!empty($whereClause)) {
        $baseQuery .= " WHERE " . implode(" AND ", $whereClause);
    }

    // Sorting
    $baseQuery .= " ORDER BY t.category, t.name";

    // Execute query
    $result = $conn->query($baseQuery);
    $tools = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Use the current_status field for display
            $row['status'] = $row['current_status'];
            $tools[] = $row;
        }
    }
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tools and Equipment - MacJ Pest Control</title>
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

        /* Status badge styles */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
            text-align: center;
            min-width: 80px;
        }

        .status-in-stock {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-in-use {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        /* Category badge styles */
        .category-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
            text-align: center;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .category-general {
            background-color: #cfe2ff;
            color: #084298;
            border: 1px solid #b6d4fe;
        }

        .category-termite {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        .category-termite-treatment, .category-termite-control {
            background-color: #e2e3e5;
            color: #41464b;
            border: 1px solid #d3d6d8;
        }

        .category-weed {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }

        .category-bed-bugs {
            background-color: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
        }

        /* Custom checkbox styles */
        .custom-control {
            position: relative;
            display: block;
            min-height: 1.5rem;
            padding-left: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .custom-control-input {
            position: absolute;
            z-index: -1;
            opacity: 0;
        }

        .custom-control-label {
            position: relative;
            margin-bottom: 0;
            vertical-align: top;
            cursor: pointer;
        }

        .custom-control-label::before {
            position: absolute;
            top: 0.25rem;
            left: -1.5rem;
            display: block;
            width: 1rem;
            height: 1rem;
            pointer-events: none;
            content: "";
            background-color: #fff;
            border: 1px solid #adb5bd;
            border-radius: 0.25rem;
        }

        .custom-control-input:checked ~ .custom-control-label::before {
            color: #fff;
            border-color: #007bff;
            background-color: #007bff;
        }

        .custom-control-label::after {
            position: absolute;
            top: 0.25rem;
            left: -1.5rem;
            display: block;
            width: 1rem;
            height: 1rem;
            content: "";
            background: no-repeat 50% / 50% 50%;
        }

        .custom-control-input:checked ~ .custom-control-label::after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23fff' d='M6.564.75l-3.59 3.612-1.538-1.55L0 4.26 2.974 7.25 8 2.193z'/%3e%3c/svg%3e");
        }

        /* Filter container styles */
        .filter-container {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .filter-group {
            margin-bottom: 10px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }

        .search-input-container {
            display: flex;
            align-items: center;
        }

        #search-input {
            flex: 1;
            border-radius: 4px;
            border: 1px solid #ced4da;
            padding: 8px 12px;
            font-size: 14px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        #search-input:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        /* Results info styles */
        .results-info {
            margin-bottom: 15px;
            font-size: 14px;
            color: #6c757d;
        }

        .highlight {
            background-color: #fff3cd;
            padding: 2px;
            border-radius: 2px;
        }

        /* Tools header buttons container */
        .tools-header-buttons {
            display: flex;
            align-items: center;
        }

        /* Reset button styles */
        #resetAllToolsBtn {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
            transition: all 0.3s ease;
        }

        #resetAllToolsBtn:hover {
            background-color: #e0a800;
            border-color: #d39e00;
            color: #212529;
        }

        /* Reset confirmation modal */
        .reset-tools-modal .modal-header {
            background-color: #ffc107;
            color: #212529;
        }

        .reset-tools-modal .modal-footer {
            justify-content: space-between;
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
                    <li class="active"><a href="tools_equipment.php"><i class="fas fa-tools"></i> Tools and Equipment</a></li>
                    <li><a href="services.php"><i class="fas fa-concierge-bell"></i> Services</a></li>
                    <li><a href="technicians.php"><i class="fas fa-user-md"></i> Technicians</a></li>
                    <li><a href="clients.php"><i class="fas fa-users"></i> Clients</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="../SignOut.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="tools-content">
                <?php if (!$statusColumnExists): ?>
                <div class="alert alert-warning mb-4">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Status column not found!</strong> To enable tracking of tools as "in stock" or "in use", please
                    <a href="update_tools_table.php" class="alert-link">add the status column</a> to the database.
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['tools_reset_performed']) && $_SESSION['tools_reset_performed'] === true): ?>
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Reset Mode Active:</strong> All tools are currently showing as "in-stock" regardless of checklist status.
                    This view will reset after 5 minutes or page refresh.
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>

                <div class="tools-header">
                    <h1>Tools and Equipment</h1>
                    <div class="tools-header-buttons">
                        <button type="button" class="btn btn-warning mr-2" id="resetAllToolsBtn">
                            <i class="fas fa-sync-alt"></i> Reset All Tools Status
                        </button>
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#toolModal">
                            <i class="fas fa-plus"></i> Add New Tool/Equipment
                        </button>
                    </div>
                </div>

                <!-- Inventory Summary -->
                <div class="inventory-summary">
                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--primary-color);">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Total Items</h3>
                            <p><?= $total_tools ?></p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: #28a745;">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <div class="summary-info">
                            <h3>In Stock</h3>
                            <p><?= $status_counts['in stock'] ?? 0 ?></p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: #ffc107;">
                            <i class="fas fa-people-carry"></i>
                        </div>
                        <div class="summary-info">
                            <h3>In Use</h3>
                            <p><?= $status_counts['in use'] ?? 0 ?></p>
                        </div>
                    </div>


                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--info-color);">
                            <i class="fas fa-spray-can"></i>
                        </div>
                        <div class="summary-info">
                            <h3>General Pest Control</h3>
                            <p><?= $category_counts['General Pest Control'] ?? 0 ?></p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--warning-color);">
                            <i class="fas fa-bug"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Termite Equipment</h3>
                            <p><?= ($category_counts['Termite'] ?? 0) + ($category_counts['Termite Treatment'] ?? 0) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Filter Controls -->
                <div class="filter-container">
                    <form id="filterForm" method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; width: 100%;">
                        <div class="filter-group" style="flex: 1;">
                            <label for="search-input">Search:</label>
                            <div class="search-input-container" style="display: flex;">
                                <input type="text" id="search-input" name="search" class="form-control" placeholder="Search by name, category, or description" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                <button type="submit" class="btn btn-primary" style="margin-left: 5px;">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                <a href="tools_equipment.php<?= isset($_GET['category']) && !empty($_GET['category']) ? '?category=' . urlencode($_GET['category']) : '' ?>" class="btn btn-secondary" style="margin-left: 5px;">
                                    <i class="fas fa-times"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="filter-group">
                            <label for="category-filter">Category:</label>
                            <select id="category-filter" name="category">
                                <option value="">All Categories</option>
                                <?php
                                // Add default categories
                                foreach ($default_categories as $category) {
                                    $selected = isset($_GET['category']) && $_GET['category'] === $category ? 'selected' : '';
                                    echo "<option value=\"" . htmlspecialchars($category) . "\" {$selected}>" . htmlspecialchars($category) . "</option>";
                                }

                                // Check if services table exists
                                $services_result = $conn->query("SHOW TABLES LIKE 'services'");
                                if ($services_result && $services_result->num_rows > 0) {
                                    // Get active services
                                    $services_query = "SELECT name FROM services WHERE status = 'active' ORDER BY name";
                                    $services_result = $conn->query($services_query);

                                    if ($services_result && $services_result->num_rows > 0) {
                                        while ($service = $services_result->fetch_assoc()) {
                                            // Skip if the service name is already in default categories
                                            if (in_array($service['name'], $default_categories)) {
                                                continue;
                                            }
                                            $selected = isset($_GET['category']) && $_GET['category'] === $service['name'] ? 'selected' : '';
                                            echo "<option value=\"" . htmlspecialchars($service['name']) . "\" {$selected}>" . htmlspecialchars($service['name']) . "</option>";
                                        }
                                    }
                                }
                                ?>
                            </select>
                            <button type="submit" class="btn btn-primary" style="margin-left: 5px;">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>

                <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                <div class="results-info">
                    <p>
                        <i class="fas fa-info-circle"></i>
                        Found <strong><?= count($tools) ?></strong> result<?= count($tools) != 1 ? 's' : '' ?> for search:
                        <span class="highlight"><?= htmlspecialchars($_GET['search']) ?></span>
                        <?php if (isset($_GET['category']) && !empty($_GET['category'])): ?>
                        in category: <span class="highlight"><?= htmlspecialchars($_GET['category']) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>

                <div class="tools-table-container">
                    <table class="tools-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($tools) === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center" style="padding: 30px;">
                                    <div style="color: #6c757d;">
                                        <i class="fas fa-search fa-3x mb-3"></i>
                                        <h4>No tools or equipment found</h4>
                                        <p>Try adjusting your search criteria or clear the filters</p>
                                        <a href="tools_equipment.php" class="btn btn-outline-primary mt-3">
                                            <i class="fas fa-sync-alt"></i> Clear all filters
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($tools as $tool): ?>
                            <tr>
                                <td><?= $tool['id'] ?></td>
                                <td><?= htmlspecialchars($tool['name']) ?></td>
                                <td>
                                    <?php
                                    $categories = explode(', ', $tool['category']);
                                    foreach ($categories as $category) {
                                        $categoryClass = strtolower(str_replace(' ', '-', $category));
                                        $badgeClass = 'category-general';

                                        if ($categoryClass === 'termite') {
                                            $badgeClass = 'category-termite';
                                        } elseif ($categoryClass === 'termite-treatment' || $categoryClass === 'termite-control') {
                                            $badgeClass = 'category-termite-treatment';
                                        } elseif ($categoryClass === 'weed-control') {
                                            $badgeClass = 'category-weed';
                                        } elseif ($categoryClass === 'bed-bugs') {
                                            $badgeClass = 'category-bed-bugs';
                                        }

                                        echo '<span class="category-badge ' . $badgeClass . '" style="margin-right: 5px; margin-bottom: 5px; display: inline-block;">' .
                                            htmlspecialchars($category) .
                                        '</span>';
                                    }
                                    ?>
                                </td>

                                <td>
                                    <?php if ($statusColumnExists): ?>
                                    <span class="status-badge <?= $tool['status'] === 'in stock' ? 'status-in-stock' : 'status-in-use' ?>">
                                        <?= ucfirst($tool['status'] ?? 'in stock') ?>
                                    </span>
                                    <?php if ($tool['status'] === 'in use' && !empty($tool['technician_name'])): ?>
                                        <div class="small text-muted mt-1">
                                            <i class="fas fa-user me-1"></i> <?= htmlspecialchars($tool['technician_name']) ?>
                                            <?php if (!empty($tool['job_order_id'])): ?>
                                                <br><i class="fas fa-clipboard-list me-1"></i> Job #<?= htmlspecialchars($tool['job_order_id']) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <a href="update_tools_table.php" class="btn btn-sm btn-warning">Add Status Column</a>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($tool['updated_at'])) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-sm btn-info view-btn" data-id="<?= $tool['id'] ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-sm btn-primary edit-btn" data-id="<?= $tool['id'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-sm btn-danger delete-btn" data-id="<?= $tool['id'] ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Create Modal -->
                <div class="modal fade" id="toolModal">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form id="toolForm">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title"><i class="fas fa-tools mr-2"></i>Add New Tool/Equipment</h5>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="required">Name</label>
                                                <input type="text" class="form-control" name="name" required>
                                            </div>
                                            <div class="form-group">
                                                <label class="required">Categories (Select Multiple)</label>
                                                <div class="categories-container" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 10px;">
                                                    <?php
                                                    // Add default categories
                                                    foreach ($default_categories as $category) {
                                                        echo '<div class="custom-control custom-checkbox">
                                                            <input type="checkbox" class="custom-control-input category-checkbox" id="cat_' . md5($category) . '" name="categories[]" value="' . htmlspecialchars($category) . '">
                                                            <label class="custom-control-label" for="cat_' . md5($category) . '">' . htmlspecialchars($category) . '</label>
                                                        </div>';
                                                    }

                                                    // Check if services table exists
                                                    $services_modal_result = $conn->query("SHOW TABLES LIKE 'services'");
                                                    if ($services_modal_result && $services_modal_result->num_rows > 0) {
                                                        // Get active services
                                                        $services_modal_query = "SELECT name FROM services WHERE status = 'active' ORDER BY name";
                                                        $services_modal_result = $conn->query($services_modal_query);

                                                        if ($services_modal_result && $services_modal_result->num_rows > 0) {
                                                            while ($service = $services_modal_result->fetch_assoc()) {
                                                                // Skip if the service name is already in default categories
                                                                if (in_array($service['name'], $default_categories)) {
                                                                    continue;
                                                                }
                                                                echo '<div class="custom-control custom-checkbox">
                                                                    <input type="checkbox" class="custom-control-input category-checkbox" id="cat_' . md5($service['name']) . '" name="categories[]" value="' . htmlspecialchars($service['name']) . '">
                                                                    <label class="custom-control-label" for="cat_' . md5($service['name']) . '">' . htmlspecialchars($service['name']) . '</label>
                                                                </div>';
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllCategories">Select All</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllCategories">Deselect All</button>
                                                </div>
                                            </div>
                                            <?php if ($statusColumnExists): ?>
                                            <div class="form-group">
                                                <label class="required">Status</label>
                                                <select class="form-control" name="status" required>
                                                    <option value="in stock">In Stock</option>
                                                    <option value="in use">In Use</option>
                                                </select>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Description</label>
                                                <textarea class="form-control" name="description" rows="3" placeholder="Brief description of the tool/equipment"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Tool</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- View Tool Modal -->
                <div class="modal fade" id="viewToolModal">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title"><i class="fas fa-eye mr-2"></i>Tool/Equipment Details</h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="detail-item">
                                            <dt>Name</dt>
                                            <dd id="viewName"></dd>
                                        </div>

                                        <div class="detail-item">
                                            <dt>Category</dt>
                                            <dd id="viewCategory"></dd>
                                        </div>

                                        <div class="detail-item">
                                            <dt>Status</dt>
                                            <dd id="viewStatus"></dd>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-item">
                                            <dt>Description</dt>
                                            <dd id="viewDescription"></dd>
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

                <!-- Reset All Tools Modal -->
                <div class="modal fade reset-tools-modal" id="resetToolsModal">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-sync-alt mr-2"></i>Reset All Tools Status</h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    <strong>Warning:</strong> This action will reset the status of all tools and equipment from "in-use" to "in-stock".
                                </div>
                                <p>This will affect tools that are currently marked as being used by technicians. Are you sure you want to proceed?</p>

                                <div id="resetToolsStatus" class="alert alert-info mt-3" style="display: none;">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <span id="resetToolsMessage">Processing...</span>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-warning" id="confirmResetBtn">
                                    <i class="fas fa-sync-alt mr-1"></i> Reset All Tools
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div class="modal fade" id="editToolModal">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form id="editToolForm">
                                <div class="modal-header bg-warning text-white">
                                    <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Tool/Equipment</h5>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="id" id="editToolId">

                                    <div class="alert alert-info mb-4">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        <strong>Note:</strong> Name, category, and description fields are displayed for reference only. You can update the status of the tool.
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <input type="text" class="form-control" id="editName" readonly>
                                            </div>
                                            <div class="form-group">
                                                <label>Categories</label>
                                                <div class="form-control" id="editCategory" style="min-height: 38px; height: auto; overflow: hidden;"></div>
                                            </div>
                                            <div class="form-group status-field-container">
                                                <label class="required">Status</label>
                                                <select class="form-control" name="status" id="editStatus" required>
                                                    <option value="in stock">In Stock</option>
                                                    <option value="in use">In Use</option>
                                                </select>
                                                <small id="statusHelperText" class="form-text text-muted" style="display: none;">
                                                    <i class="fas fa-info-circle"></i> Click "Update Status" to save this change.
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Description</label>
                                                <textarea class="form-control" id="editDescription" rows="3" readonly></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Update Status</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        // Handle Reset All Tools button click
        $('#resetAllToolsBtn').on('click', function() {
            // Show the reset confirmation modal
            $('#resetToolsModal').modal('show');
        });

        // Handle Reset All Tools confirmation
        $('#confirmResetBtn').on('click', function() {
            // Show loading state
            const resetBtn = $(this);
            const originalBtnText = resetBtn.html();
            resetBtn.prop('disabled', true);
            resetBtn.html('<i class="fas fa-spinner fa-spin mr-1"></i> Resetting...');

            // Show status alert
            $('#resetToolsStatus').removeClass().addClass('alert alert-info mt-3').show();
            $('#resetToolsMessage').text('Resetting all tools status from "in-use" to "in-stock"...');

            // Send AJAX request to reset all tools
            $.ajax({
                url: 'reset_all_tools.php',
                method: 'POST',
                dataType: 'json',
                success: function(response) {
                    console.log('Reset tools response:', response);

                    // Reset button state
                    resetBtn.prop('disabled', false);
                    resetBtn.html(originalBtnText);

                    if (response.success) {
                        // Update status alert with success message
                        $('#resetToolsStatus').removeClass().addClass('alert alert-success mt-3');

                        if (response.tools_reset > 0 || response.checklists_affected > 0) {
                            let message = `<strong>Success!</strong> `;
                            if (response.tools_reset > 0) {
                                message += `${response.tools_reset} tools and equipment have been reset from "in-use" to "in-stock" status. `;
                            }
                            if (response.checklists_affected > 0) {
                                message += `${response.checklists_affected} technician checklists were affected. `;
                            }
                            message += `<br><br><strong>Note:</strong> All tools will now show as "in-stock" regardless of checklist status. This view will reset after 5 minutes or page refresh.`;
                            $('#resetToolsMessage').html(message);
                        } else {
                            $('#resetToolsMessage').html(`<strong>Note:</strong> No tools needed to be reset. All tools are already in "in-stock" status.`);
                        }

                        // Show success message with SweetAlert
                        let bypassMessage = "All tools will now show as 'in-stock' regardless of checklist status. This view will reset after 5 minutes or page refresh.";

                        if (response.warning) {
                            // Success with warning
                            Swal.fire({
                                title: 'Success with Warning',
                                html: `${response.message}<br><br><strong>Warning:</strong><br>${response.warning.replace(/\n/g, '<br>')}<br><br>${bypassMessage}`,
                                icon: 'warning',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                // Close the modal and reload without actually reloading the page
                                $('#resetToolsModal').modal('hide');
                            });
                        } else {
                            // Regular success
                            Swal.fire({
                                title: 'Success',
                                html: `${response.message}<br><br>${bypassMessage}`,
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                // Close the modal and reload without actually reloading the page
                                $('#resetToolsModal').modal('hide');
                            });
                        }
                    } else {
                        // Update status alert with error message
                        $('#resetToolsStatus').removeClass().addClass('alert alert-danger mt-3');
                        $('#resetToolsMessage').text('Error: ' + (response.error || 'Failed to reset tools status'));

                        // Show error message
                        Swal.fire({
                            title: 'Error',
                            text: response.error || 'Failed to reset tools status',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error resetting tools status:', error);

                    // Reset button state
                    resetBtn.prop('disabled', false);
                    resetBtn.html(originalBtnText);

                    // Update status alert with error message
                    $('#resetToolsStatus').removeClass().addClass('alert alert-danger mt-3');
                    $('#resetToolsMessage').text('Error: ' + error);

                    // Show error message
                    Swal.fire({
                        title: 'Error',
                        text: 'An error occurred while resetting tools status: ' + error,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        });

        // Handle search form submission
        $('#search-input').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                $('#filterForm').submit();
            }
        });

        // Reset form when modal is closed
        $('#toolModal').on('hidden.bs.modal', function() {
            $('#toolForm')[0].reset();
            $('.category-checkbox').prop('checked', false);
        });

        // Handle Select All Categories button
        $('#selectAllCategories').click(function() {
            $('.category-checkbox').prop('checked', true);
        });

        // Handle Deselect All Categories button
        $('#deselectAllCategories').click(function() {
            $('.category-checkbox').prop('checked', false);
        });

        // Create new tool/equipment
        $('#toolForm').submit(function(e) {
            e.preventDefault();

            // Validate that at least one category is selected
            if ($('.category-checkbox:checked').length === 0) {
                alert('Please select at least one category');
                return false;
            }

            const formData = $(this).serialize();

            $.ajax({
                type: 'POST',
                url: 'tools_equipment.php',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Reset form and close modal
                        $('#toolForm')[0].reset();
                        $('.category-checkbox').prop('checked', false);
                        $('#toolModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.error || 'Failed to save tool/equipment'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                }
            });
        });

        // View tool details in edit modal
        $(document).on('click', '.edit-btn', function() {
            const toolId = $(this).data('id');

            $.ajax({
                url: 'get_tool.php',
                method: 'GET',
                data: { id: toolId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        $('#editToolId').val(response.data.id);
                        $('#editName').val(response.data.name);

                        // Format categories with badges if there are multiple categories
                        const categories = response.data.category.split(', ');
                        let categoryHtml = '';

                        if (categories.length > 0) {
                            categories.forEach(function(category) {
                                const categoryClass = category.toLowerCase().replace(/\s+/g, '-');
                                categoryHtml += `<span class="category-badge category-${categoryClass}" style="margin-right: 5px; margin-bottom: 5px; display: inline-block;">${category}</span>`;
                            });
                        } else {
                            categoryHtml = response.data.category;
                        }

                        $('#editCategory').html(categoryHtml);
                        $('#editDescription').val(response.data.description);

                        // Check if status column exists
                        if ('status' in response.data) {
                            // Show status field and set the dropdown value
                            $('.status-field-container').show();
                            const status = response.data.status || 'in stock';
                            $('#editStatus').val(status);

                            // Reset helper text and button styling
                            $('#statusHelperText').hide();
                            const submitButton = $('#editToolForm button[type="submit"]');
                            submitButton.html('<i class="fas fa-save mr-1"></i> Update Status');
                            submitButton.removeClass('btn-success').addClass('btn-warning');

                            // If status is already "in stock", update the button text
                            if (status === 'in stock') {
                                submitButton.html('<i class="fas fa-save mr-1"></i> Update Status');
                            }

                            // Enable the submit button
                            submitButton.show();
                        } else {
                            // Hide status field and show a message
                            $('.status-field-container').hide();

                            // Add a message about adding the status column
                            if (!$('.status-column-message').length) {
                                $('#editToolForm .modal-body').append(`
                                    <div class="alert alert-warning status-column-message">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        <strong>Status column not found!</strong> Please <a href="update_tools_table.php">add the status column</a> first.
                                    </div>
                                `);
                            }

                            // Disable the submit button
                            $('#editToolForm button[type="submit"]').hide();
                        }

                        $('#editToolModal').modal('show');
                    }
                }
            });
        });

        // Delete tool/equipment
        $(document).on('click', '.delete-btn', function() {
            const toolId = $(this).data('id');
            if(confirm('WARNING: This will permanently delete the record!\n\nProceed?')) {
                $.ajax({
                    url: 'delete_tool.php',
                    method: 'POST',
                    data: { id: toolId },
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) location.reload();
                    }
                });
            }
        });

        // View tool/equipment
        $(document).on('click', '.view-btn', function() {
            const toolId = $(this).data('id');

            $.ajax({
                url: 'get_tool.php',
                method: 'GET',
                data: { id: toolId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        // Populate view modal
                        $('#viewName').text(response.data.name);

                        // Format categories with badges if there are multiple categories
                        const categories = response.data.category.split(', ');
                        let categoryHtml = '';

                        if (categories.length > 0) {
                            categories.forEach(function(category) {
                                const categoryClass = category.toLowerCase().replace(/\s+/g, '-');
                                categoryHtml += `<span class="category-badge category-${categoryClass}" style="margin-right: 5px; margin-bottom: 5px; display: inline-block;">${category}</span>`;
                            });
                        } else {
                            categoryHtml = response.data.category;
                        }

                        $('#viewCategory').html(categoryHtml);
                        $('#viewDescription').text(response.data.description || 'No description');

                        // Check if status column exists
                        if ('status' in response.data) {
                            // Format status with badge
                            const status = response.data.status || 'in stock';
                            const statusClass = status === 'in stock' ? 'status-in-stock' : 'status-in-use';
                            let statusHtml = `<span class="status-badge ${statusClass}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;

                            // Add technician name if tool is in use
                            if (status === 'in use' && response.data.technician_name) {
                                statusHtml += `<div class="small text-muted mt-1">
                                    <i class="fas fa-user me-1"></i> ${response.data.technician_name}`;

                                if (response.data.job_order_id) {
                                    statusHtml += `<br><i class="fas fa-clipboard-list me-1"></i> Job #${response.data.job_order_id}`;
                                }

                                statusHtml += `</div>`;
                            }

                            $('#viewStatus').html(statusHtml);
                        } else {
                            // Status column doesn't exist
                            $('#viewStatus').html(`<a href="update_tools_table.php" class="btn btn-sm btn-warning">Add Status Column</a>`);
                        }

                        $('#viewToolModal').modal('show');
                    }
                }
            });
        });

        // Handle status dropdown change in edit modal
        $('#editStatus').on('change', function() {
            const selectedStatus = $(this).val();
            const submitButton = $('#editToolForm button[type="submit"]');

            // Show helper text when "in stock" is selected
            if (selectedStatus === 'in stock') {
                $('#statusHelperText').fadeIn();
                submitButton.html('<i class="fas fa-save mr-1"></i> Set to In-Stock');
                submitButton.removeClass('btn-warning').addClass('btn-success');
            } else {
                $('#statusHelperText').fadeOut();
                submitButton.html('<i class="fas fa-save mr-1"></i> Update Status');
                submitButton.removeClass('btn-success').addClass('btn-warning');
            }
        });

        // Edit tool status
        $('#editToolForm').submit(function(e) {
            e.preventDefault();
            const toolId = $('#editToolId').val();
            const status = $('#editStatus').val();
            const submitButton = $('#editToolForm button[type="submit"]');

            // Show loading state
            const originalBtnText = submitButton.html();
            submitButton.prop('disabled', true);
            submitButton.html('<i class="fas fa-spinner fa-spin mr-1"></i> Updating...');

            $.ajax({
                url: 'update_tool_status.php',
                method: 'POST',
                data: {
                    id: toolId,
                    status: status
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        // Check if there's a warning message
                        if (response.warning) {
                            // Show warning message with SweetAlert
                            Swal.fire({
                                title: 'Status Updated with Warning',
                                text: response.warning,
                                icon: 'warning',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                $('#editToolModal').modal('hide');
                                location.reload();
                            });
                        } else {
                            // Show success message
                            Swal.fire({
                                title: 'Success',
                                text: status === 'in stock' ? 'Tool status has been set to In-Stock' : 'Tool status has been updated',
                                icon: 'success',
                                confirmButtonText: 'OK',
                                timer: 1500,
                                timerProgressBar: true
                            }).then(() => {
                                $('#editToolModal').modal('hide');
                                location.reload();
                            });
                        }
                    } else {
                        // Reset button state
                        submitButton.prop('disabled', false);
                        submitButton.html(originalBtnText);

                        // Show error message
                        Swal.fire({
                            title: 'Error',
                            text: response.error || 'Failed to update tool status',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    // Reset button state
                    submitButton.prop('disabled', false);
                    submitButton.html(originalBtnText);

                    // Show error message
                    Swal.fire({
                        title: 'Error',
                        text: 'An error occurred: ' + error,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        });
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
<?php
$conn->close();
?>
