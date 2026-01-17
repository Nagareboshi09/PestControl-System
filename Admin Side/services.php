<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: ../SignIn.php");
    exit;
}
require_once '../db_connect.php';
require_once '../notification_functions.php';

// Check if the services table exists
try {
    $result = $conn->query("SHOW TABLES LIKE 'services'");
    if ($result->num_rows == 0) {
        // Table doesn't exist, create it
        $sql = file_get_contents('../create_services_table.sql');
        if ($conn->multi_query($sql)) {
            do {
                // Process each result set
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
        } else {
            echo '<div class="alert alert-danger">Error creating services table: ' . $conn->error . '</div>';
        }
    } else {
        // Table exists, check if the image column exists
        $result = $conn->query("SHOW COLUMNS FROM services LIKE 'image'");
        if ($result->num_rows == 0) {
            // Column doesn't exist, add it
            $sql = "ALTER TABLE services ADD COLUMN image varchar(255) DEFAULT NULL AFTER icon";
            if (!$conn->query($sql)) {
                echo '<div class="alert alert-danger">Error adding image column: ' . $conn->error . '</div>';
            }

            // Update default services to include image paths
            $sql = "UPDATE services SET
                    image = CASE
                        WHEN name = 'General Pest Control' THEN 'GenPest.jpg'
                        WHEN name = 'Termite Control' THEN 'termite.jpg'
                        WHEN name = 'Rodent Control' THEN 'rodent.jpg'
                        WHEN name = 'Disinfection' THEN 'disinfect.jpg'
                        WHEN name = 'Weed Control' THEN 'weed.jpg'
                        ELSE NULL
                    END
                    WHERE image IS NULL";
            $conn->query($sql);
        }
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/services/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Get services count
    $result = $conn->query("SELECT COUNT(*) AS total FROM services");
    $row = $result->fetch_assoc();
    $total_services = $row['total'];

    // Count by status
    $result = $conn->query("SELECT status, COUNT(*) as count FROM services GROUP BY status");
    $status_counts = [];
    while ($status = $result->fetch_assoc()) {
        $status_counts[$status['status']] = $status['count'];
    }

} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Get all services
try {
    $baseQuery = "SELECT * FROM services";

    // Status filter
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $status = $_GET['status'];
        $baseQuery .= " WHERE status = '" . $conn->real_escape_string($status) . "'";
    }

    // Sorting
    $baseQuery .= " ORDER BY name";

    // Execute query
    $result = $conn->query($baseQuery);
    $services = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
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
    <title>Services Management - MacJ Pest Control</title>
    <link rel="stylesheet" href="css/admin-base.css">
    <link rel="stylesheet" href="css/tools-equipment-page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <link rel="stylesheet" href="css/notification-viewed.css">
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

        /* Services header style for consistency */
        .services-content {
            width: 100%;
            flex: 1;
            padding: 25px;
            box-sizing: border-box;
            background-color: #f5f7fb;
        }

        .services-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .services-header h1 {
            margin: 0;
            color: var(--primary-color);
            font-size: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .services-header h1 i {
            margin-right: 10px;
        }

        /* Additional styles for services page */
        .service-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .service-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .service-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        .service-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .service-description {
            color: #666;
            margin-bottom: 15px;
            min-height: 60px;
        }

        .service-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 15px;
        }

        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .status-inactive {
            background-color: #ffebee;
            color: #c62828;
        }

        .service-actions {
            display: flex;
            gap: 10px;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-edit {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .btn-edit:hover {
            background-color: #1976d2;
            color: white;
        }

        .btn-delete {
            background-color: #ffebee;
            color: #c62828;
        }

        .btn-delete:hover {
            background-color: #c62828;
            color: white;
        }

        .btn-view {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .btn-view:hover {
            background-color: #2e7d32;
            color: white;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .services-grid {
                grid-template-columns: 1fr;
            }
        }

        .icon-preview {
            font-size: 2rem;
            margin: 10px 0;
            color: var(--primary-color);
        }

        .icon-options {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .icon-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .icon-option:hover, .icon-option.selected {
            background-color: #e3f2fd;
            border-color: #1976d2;
        }

        .icon-option i {
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: #555;
        }

        .icon-option.selected i {
            color: #1976d2;
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
                    <li class="active"><a href="services.php"><i class="fas fa-concierge-bell"></i> Services</a></li>
                    <li><a href="technicians.php"><i class="fas fa-user-md"></i> Technicians</a></li>
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
            <div class="services-content">
                <div class="services-header">
                    <h1><i class="fas fa-concierge-bell"></i> Services Management</h1>
                    <div class="d-flex">
                        <button class="btn btn-info mr-2" data-toggle="modal" data-target="#pestCheckboxModal">
                            <i class="fas fa-check-square"></i> Pest CheckBox
                        </button>
                        <button class="btn btn-primary" data-toggle="modal" data-target="#serviceModal">
                            <i class="fas fa-plus"></i> Add New Service
                        </button>
                    </div>
                </div>

            <!-- Services Content -->
            <div class="content-body">
                <!-- Services Summary -->
                <div class="inventory-summary">
                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--primary-color);">
                            <i class="fas fa-concierge-bell"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Total Services</h3>
                            <p><?= $total_services ?></p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--success-color);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Active Services</h3>
                            <p><?= $status_counts['active'] ?? 0 ?></p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--danger-color);">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Inactive Services</h3>
                            <p><?= $status_counts['inactive'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>

                <!-- Filter Controls -->
                <div class="filter-container">
                    <form id="filterForm" method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; width: 100%;">
                        <div class="filter-group">
                            <label for="status-filter">Status:</label>
                            <select id="status-filter" name="status" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="active" <?= isset($_GET['status']) && $_GET['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="filter-group ml-auto">
                            <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='services.php'">
                                <i class="fas fa-sync-alt"></i> Reset Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Services Grid -->
                <div class="services-grid">
                    <?php foreach ($services as $service): ?>
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas <?= htmlspecialchars($service['icon']) ?>"></i>
                        </div>
                        <h3 class="service-title"><?= htmlspecialchars($service['name']) ?></h3>
                        <div class="service-description">
                            <?= htmlspecialchars($service['description'] ?? 'No description available') ?>
                        </div>
                        <div class="service-status status-<?= $service['status'] ?>">
                            <?= ucfirst($service['status']) ?>
                        </div>
                        <div class="service-actions">
                            <button class="btn-icon btn-view view-btn" data-id="<?= $service['service_id'] ?>" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn-icon btn-edit edit-btn" data-id="<?= $service['service_id'] ?>" title="Edit Service">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon btn-delete delete-btn" data-id="<?= $service['service_id'] ?>" data-name="<?= htmlspecialchars($service['name']) ?>" title="Delete Service">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create/Edit Service Modal -->
    <div class="modal fade" id="serviceModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="serviceForm" enctype="multipart/form-data">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-concierge-bell mr-2"></i><span id="modalTitle">Add New Service</span></h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="service_id" id="serviceId">
                        <input type="hidden" name="image" id="serviceImage">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="required">Service Name</label>
                                    <input type="text" class="form-control" name="name" id="serviceName" required>
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea class="form-control" name="description" id="serviceDescription" rows="3" placeholder="Brief description of the service"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Status</label>
                                    <select class="form-control" name="status" id="serviceStatus">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Service Image</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="serviceImageUpload" accept="image/*">
                                        <label class="custom-file-label" for="serviceImageUpload">Choose image...</label>
                                    </div>
                                    <small class="form-text text-muted">Recommended size: 800x600px. Max file size: 5MB.</small>
                                    <div class="mt-2" id="imagePreviewContainer" style="display: none;">
                                        <img id="imagePreview" src="" alt="Service Image Preview" class="img-fluid img-thumbnail" style="max-height: 150px;">
                                        <button type="button" class="btn btn-sm btn-danger mt-1" id="removeImage"><i class="fas fa-times"></i> Remove</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Icon</label>
                                    <div class="icon-preview">
                                        <i class="fas fa-spray-can" id="iconPreview"></i>
                                    </div>
                                    <input type="hidden" name="icon" id="serviceIcon" value="fa-spray-can">
                                    <div class="icon-options">
                                        <div class="icon-option selected" data-icon="fa-spray-can">
                                            <i class="fas fa-spray-can"></i>
                                            <span>Spray</span>
                                        </div>
                                        <div class="icon-option" data-icon="fa-bug">
                                            <i class="fas fa-bug"></i>
                                            <span>Bug</span>
                                        </div>
                                        <div class="icon-option" data-icon="fa-mouse">
                                            <i class="fas fa-mouse"></i>
                                            <span>Mouse</span>
                                        </div>
                                        <div class="icon-option" data-icon="fa-seedling">
                                            <i class="fas fa-seedling"></i>
                                            <span>Plant</span>
                                        </div>
                                        <div class="icon-option" data-icon="fa-pump-medical">
                                            <i class="fas fa-pump-medical"></i>
                                            <span>Pump</span>
                                        </div>
                                        <div class="icon-option" data-icon="fa-house-damage">
                                            <i class="fas fa-house-damage"></i>
                                            <span>House</span>
                                        </div>
                                        <div class="icon-option" data-icon="fa-spider">
                                            <i class="fas fa-spider"></i>
                                            <span>Spider</span>
                                        </div>
                                        <div class="icon-option" data-icon="fa-broom">
                                            <i class="fas fa-broom"></i>
                                            <span>Broom</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Service Modal -->
    <div class="modal fade" id="viewServiceModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-info-circle mr-2"></i>Service Details</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div id="viewImageContainer" class="mb-3" style="display: none;">
                            <img id="viewImage" src="" alt="Service Image" class="img-fluid rounded" style="max-height: 200px;">
                        </div>
                        <div class="icon-preview">
                            <i class="fas" id="viewIcon"></i>
                        </div>
                        <h3 id="viewName"></h3>
                        <div class="service-status" id="viewStatus"></div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <h5>Description</h5>
                            <p id="viewDescription"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i>Delete Service</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the service: <strong id="deleteServiceName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Pest CheckBox Modal -->
    <div class="modal fade" id="pestCheckboxModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-check-square mr-2"></i>Manage Pest CheckBoxes</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <button class="btn btn-primary" id="addPestCheckboxBtn">
                            <i class="fas fa-plus"></i> Add New Pest CheckBox
                        </button>
                    </div>

                    <div class="pest-checkbox-form mb-4" style="display: none;">
                        <form id="pestCheckboxForm">
                            <input type="hidden" id="pestCheckboxId" name="id">
                            <div class="form-group">
                                <label for="pestCheckboxName" class="required">Pest CheckBox Name</label>
                                <input type="text" class="form-control" id="pestCheckboxName" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="pestCheckboxStatus">Status</label>
                                <select class="form-control" id="pestCheckboxStatus" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Save</button>
                                <button type="button" class="btn btn-secondary" id="cancelPestCheckboxBtn">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="pestCheckboxList">
                                <!-- Pest checkboxes will be loaded here -->
                                <tr>
                                    <td colspan="3" class="text-center">Loading pest checkboxes...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Pest CheckBox Confirmation Modal -->
    <div class="modal fade" id="deletePestCheckboxModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i>Delete Pest CheckBox</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the pest checkbox: <strong id="deletePestCheckboxName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone and may affect existing forms.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeletePestCheckbox">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Required JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Icon selection
        $('.icon-option').click(function() {
            $('.icon-option').removeClass('selected');
            $(this).addClass('selected');
            const icon = $(this).data('icon');
            $('#serviceIcon').val(icon);
            $('#iconPreview').attr('class', 'fas ' + icon);
        });

        // Image upload preview
        $('#serviceImageUpload').change(function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#imagePreview').attr('src', e.target.result);
                    $('#imagePreviewContainer').show();
                    $('.custom-file-label').text(file.name);
                }
                reader.readAsDataURL(file);
            }
        });

        // Remove image
        $('#removeImage').click(function() {
            $('#serviceImageUpload').val('');
            $('#serviceImage').val('');
            $('#imagePreviewContainer').hide();
            $('.custom-file-label').text('Choose image...');
        });

        // Create/Edit service
        $('#serviceForm').submit(function(e) {
            e.preventDefault();
            const serviceId = $('#serviceId').val();
            const url = serviceId ? 'update_service.php' : 'save_service.php';

            // Check if there's a file to upload
            const fileInput = $('#serviceImageUpload')[0];
            if (fileInput.files.length > 0) {
                // First upload the image
                const imageFormData = new FormData();
                imageFormData.append('service_image', fileInput.files[0]);

                $.ajax({
                    type: 'POST',
                    url: 'upload_service_image.php',
                    data: imageFormData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Image uploaded successfully, now save the service with the image filename
                            $('#serviceImage').val(response.file_name);
                            saveService(url);
                        } else {
                            alert('Error uploading image: ' + (response.error || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('Error uploading image. Please try again.');
                    }
                });
            } else {
                // No new image, just save the service
                saveService(url);
            }
        });

        // Function to save service data
        function saveService(url) {
            const formData = $('#serviceForm').serialize();

            $.ajax({
                type: 'POST',
                url: url,
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#serviceModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.error || 'Failed to save service'));
                    }
                },
                error: function() {
                    alert('Error saving service. Please try again.');
                }
            });
        }

        // View service
        $(document).on('click', '.view-btn', function() {
            const serviceId = $(this).data('id');

            $.ajax({
                url: 'get_service.php',
                method: 'GET',
                data: { id: serviceId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        // Populate view modal
                        $('#viewName').text(response.data.name);
                        $('#viewDescription').text(response.data.description || 'No description available');
                        $('#viewIcon').attr('class', 'fas ' + response.data.icon);
                        $('#viewStatus').attr('class', 'service-status status-' + response.data.status)
                            .text(response.data.status.charAt(0).toUpperCase() + response.data.status.slice(1));

                        // Handle image
                        if (response.data.image) {
                            $('#viewImage').attr('src', '../uploads/services/' + response.data.image);
                            $('#viewImageContainer').show();
                        } else {
                            $('#viewImageContainer').hide();
                        }

                        $('#viewServiceModal').modal('show');
                    }
                }
            });
        });

        // Edit service
        $(document).on('click', '.edit-btn', function() {
            const serviceId = $(this).data('id');

            $.ajax({
                url: 'get_service.php',
                method: 'GET',
                data: { id: serviceId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        // Populate edit modal
                        $('#modalTitle').text('Edit Service');
                        $('#serviceId').val(response.data.service_id);
                        $('#serviceName').val(response.data.name);
                        $('#serviceDescription').val(response.data.description);
                        $('#serviceStatus').val(response.data.status);
                        $('#serviceIcon').val(response.data.icon);
                        $('#iconPreview').attr('class', 'fas ' + response.data.icon);
                        $('#serviceImage').val(response.data.image || '');

                        // Select the correct icon
                        $('.icon-option').removeClass('selected');
                        $('.icon-option[data-icon="' + response.data.icon + '"]').addClass('selected');

                        // Handle image preview
                        if (response.data.image) {
                            $('#imagePreview').attr('src', '../uploads/services/' + response.data.image);
                            $('#imagePreviewContainer').show();
                            $('.custom-file-label').text('Current: ' + response.data.image);
                        } else {
                            $('#imagePreviewContainer').hide();
                            $('.custom-file-label').text('Choose image...');
                        }

                        $('#serviceModal').modal('show');
                    }
                }
            });
        });

        // Delete service
        $(document).on('click', '.delete-btn', function() {
            const serviceId = $(this).data('id');
            const serviceName = $(this).data('name');

            $('#deleteServiceName').text(serviceName);
            $('#confirmDelete').data('id', serviceId);
            $('#deleteModal').modal('show');
        });

        // Confirm delete
        $('#confirmDelete').click(function() {
            const serviceId = $(this).data('id');

            $.ajax({
                url: 'delete_service.php',
                method: 'POST',
                data: { id: serviceId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        $('#deleteModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.error || 'Failed to delete service'));
                    }
                }
            });
        });

        // Reset form when modal is closed
        $('#serviceModal').on('hidden.bs.modal', function() {
            $('#serviceForm')[0].reset();
            $('#serviceId').val('');
            $('#serviceImage').val('');
            $('#modalTitle').text('Add New Service');
            $('.icon-option').removeClass('selected');
            $('.icon-option[data-icon="fa-spray-can"]').addClass('selected');
            $('#serviceIcon').val('fa-spray-can');
            $('#iconPreview').attr('class', 'fas fa-spray-can');
            $('#imagePreviewContainer').hide();
            $('.custom-file-label').text('Choose image...');
        });
    });
    </script>

    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <script>
        // Initialize mobile menu and notifications when the page loads
        $(document).ready(function() {
            // Mobile menu toggle
            $('#menuToggle').on('click', function() {
                $('.sidebar').toggleClass('active');
            });

            // Notification functionality
            $('.notification-icon').on('click', function() {
                $('.notification-dropdown').toggleClass('show');
                fetchNotifications();
            });

            // Close notification dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.notification-container').length) {
                    $('.notification-dropdown').removeClass('show');
                }
            });

            // Mark all notifications as read
            $('.mark-all-read').on('click', function() {
                markAllNotificationsAsRead();
            });

            // Fetch notifications immediately
            if (typeof fetchNotifications === 'function') {
                fetchNotifications();

                // Set up periodic notification checks
                setInterval(fetchNotifications, 60000); // Check every minute
            } else {
                console.error("fetchNotifications function not found");
            }

            // Pest CheckBox functionality
            $('#pestCheckboxModal').on('show.bs.modal', function() {
                loadPestCheckboxes();
            });

            // Add new pest checkbox button
            $('#addPestCheckboxBtn').click(function() {
                resetPestCheckboxForm();
                $('.pest-checkbox-form').slideDown();
            });

            // Cancel pest checkbox form
            $('#cancelPestCheckboxBtn').click(function() {
                $('.pest-checkbox-form').slideUp();
                resetPestCheckboxForm();
            });

            // Submit pest checkbox form
            $('#pestCheckboxForm').submit(function(e) {
                e.preventDefault();
                const formData = $(this).serialize();

                $.ajax({
                    url: 'save_pest_checkbox.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('.pest-checkbox-form').slideUp();
                            resetPestCheckboxForm();
                            loadPestCheckboxes();
                        } else {
                            alert('Error: ' + (response.error || 'Failed to save pest checkbox'));
                        }
                    },
                    error: function() {
                        alert('Error saving pest checkbox. Please try again.');
                    }
                });
            });

            // Edit pest checkbox
            $(document).on('click', '.edit-pest-checkbox-btn', function() {
                const id = $(this).data('id');

                $.ajax({
                    url: 'get_pest_checkbox.php',
                    method: 'GET',
                    data: { id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#pestCheckboxId').val(response.data.id);
                            $('#pestCheckboxName').val(response.data.name);
                            $('#pestCheckboxStatus').val(response.data.status);
                            $('.pest-checkbox-form').slideDown();
                        } else {
                            alert('Error: ' + (response.error || 'Failed to get pest checkbox details'));
                        }
                    },
                    error: function() {
                        alert('Error getting pest checkbox details. Please try again.');
                    }
                });
            });

            // Delete pest checkbox
            $(document).on('click', '.delete-pest-checkbox-btn', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');

                $('#deletePestCheckboxName').text(name);
                $('#confirmDeletePestCheckbox').data('id', id);
                $('#deletePestCheckboxModal').modal('show');
            });

            // Confirm delete pest checkbox
            $('#confirmDeletePestCheckbox').click(function() {
                const id = $(this).data('id');

                $.ajax({
                    url: 'delete_pest_checkbox.php',
                    method: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#deletePestCheckboxModal').modal('hide');
                            loadPestCheckboxes();
                        } else {
                            alert('Error: ' + (response.error || 'Failed to delete pest checkbox'));
                        }
                    },
                    error: function() {
                        alert('Error deleting pest checkbox. Please try again.');
                    }
                });
            });
        });

        // Load pest checkboxes
        function loadPestCheckboxes() {
            $.ajax({
                url: 'get_pest_checkboxes.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        renderPestCheckboxes(response.data);
                    } else {
                        $('#pestCheckboxList').html('<tr><td colspan="3" class="text-center text-danger">Error loading pest checkboxes: ' + (response.error || 'Unknown error') + '</td></tr>');
                    }
                },
                error: function() {
                    $('#pestCheckboxList').html('<tr><td colspan="3" class="text-center text-danger">Error loading pest checkboxes. Please try again.</td></tr>');
                }
            });
        }

        // Render pest checkboxes
        function renderPestCheckboxes(checkboxes) {
            if (!checkboxes || checkboxes.length === 0) {
                $('#pestCheckboxList').html('<tr><td colspan="3" class="text-center">No pest checkboxes found</td></tr>');
                return;
            }

            let html = '';
            checkboxes.forEach(function(checkbox) {
                html += `
                    <tr>
                        <td>${checkbox.name}</td>
                        <td>
                            <span class="badge badge-${checkbox.status === 'active' ? 'success' : 'danger'}">
                                ${checkbox.status === 'active' ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary edit-pest-checkbox-btn" data-id="${checkbox.id}" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-pest-checkbox-btn" data-id="${checkbox.id}" data-name="${checkbox.name}" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            $('#pestCheckboxList').html(html);
        }

        // Reset pest checkbox form
        function resetPestCheckboxForm() {
            $('#pestCheckboxId').val('');
            $('#pestCheckboxName').val('');
            $('#pestCheckboxStatus').val('active');
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>
