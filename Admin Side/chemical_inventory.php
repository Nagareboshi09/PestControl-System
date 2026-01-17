<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}
require_once '../db_config.php';
require_once '../notification_functions.php';
// Function to create notifications for expiring chemicals
function createExpiringChemicalNotifications($pdo) {
    try {
        // Get all admin users from office_staff table
        $adminStmt = $pdo->query("SELECT staff_id FROM office_staff");
        $admins = $adminStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($admins)) {
            // If no admins found, use the current user
            $admins = [$_SESSION['user_id']];
        }

        // Get chemicals expiring within 30 days that haven't had notifications created yet
        $stmt = $pdo->prepare("
            SELECT c.id, c.chemical_name, c.expiration_date
            FROM chemical_inventory c
            WHERE c.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            AND NOT EXISTS (
                SELECT 1 FROM notifications n
                WHERE n.related_id = c.id
                AND n.related_type = 'expiring_chemical'
                AND DATE(n.created_at) = CURDATE()
            )
        ");

        $stmt->execute();
        $expiringChemicals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Create notifications for each admin about each expiring chemical
        $notificationsCreated = 0;
        foreach ($expiringChemicals as $chemical) {
            $daysUntilExpiration = (new DateTime($chemical['expiration_date']))->diff(new DateTime())->days;
            $title = "Chemical Expiring Soon";
            $message = "{$chemical['chemical_name']} will expire in {$daysUntilExpiration} days (on " . date('M d, Y', strtotime($chemical['expiration_date'])) . ").";

            foreach ($admins as $adminId) {
                if (createNotification(
                    $adminId,
                    'admin',
                    $title,
                    $message,
                    $chemical['id'],
                    'expiring_chemical',
                    $pdo
                )) {
                    $notificationsCreated++;
                }
            }
        }

        return $notificationsCreated;
    } catch (PDOException $e) {
        // Log the error but don't stop page execution
        error_log("Error creating expiring chemical notifications: " . $e->getMessage());
        return 0;
    }
}

// Get Dashboard Metrics
try {
    // Check for expiring chemicals and create notifications
    try {
        $notificationsCreated = createExpiringChemicalNotifications($pdo);
    } catch (Exception $e) {
        // Log the error but continue with page execution
        error_log("Error in chemical notification system: " . $e->getMessage());
        $notificationsCreated = 0;
    }

    // Total Chemicals
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM chemical_inventory");
    $total_chemicals = $stmt->fetchColumn();

    // Low Stock (quantity < 10)
    $stmt = $pdo->query("SELECT COUNT(*) AS low_stock FROM chemical_inventory WHERE status = 'Low Stock'");
    $low_stock = $stmt->fetchColumn();

    // Out of Stock
    $stmt = $pdo->query("SELECT COUNT(*) AS out_of_stock FROM chemical_inventory WHERE status = 'Out of Stock'");
    $out_of_stock = $stmt->fetchColumn();

    // Expiring within 30 days
    $stmt = $pdo->query("SELECT COUNT(*) AS expiring_soon
                         FROM chemical_inventory
                         WHERE expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 MONTH)");
    $expiring_soon = $stmt->fetchColumn();

    // Expired chemicals
    $stmt = $pdo->query("SELECT COUNT(*) AS expired
                         FROM chemical_inventory
                         WHERE expiration_date < CURDATE()");
    $expired_count = $stmt->fetchColumn();

    // Get list of expired chemicals
    $stmt = $pdo->query("SELECT id, chemical_name, type, quantity, unit, expiration_date, status
                         FROM chemical_inventory
                         WHERE expiration_date < CURDATE()
                         ORDER BY expiration_date ASC");
    $expired_chemicals = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submission for NEW chemical
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $data = [
            ':name' => $_POST['chemical_name'],
            ':type' => $_POST['type'],
            ':target_pest' => $_POST['target_pest'] ?? null,
            ':qty' => (float)$_POST['quantity'],
            ':unit' => $_POST['unit'],
            ':manufacturer' => $_POST['manufacturer'] ?? null,
            ':supplier' => $_POST['supplier'] ?? null,
            ':desc' => $_POST['description'] ?? null,
            ':safety' => $_POST['safety_info'] ?? null,
            ':exp_date' => $_POST['expiration_date'],
            ':dilution_rate' => isset($_POST['dilution_rate']) ? (float)$_POST['dilution_rate'] : null,
            ':area_coverage' => isset($_POST['area_coverage']) ? (float)$_POST['area_coverage'] : 100
        ];

        $sql = "INSERT INTO chemical_inventory
                (chemical_name, type, target_pest, quantity, unit, manufacturer,
                 supplier, description, safety_info, expiration_date, dilution_rate, area_coverage)
                VALUES (:name, :type, :target_pest, :qty, :unit, :manufacturer,
                        :supplier, :desc, :safety, :exp_date, :dilution_rate, :area_coverage)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        echo json_encode(['success' => true, 'message' => 'Chemical added successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Get all chemicals
try {
    $baseQuery = "SELECT * FROM chemical_inventory";
    $whereClauses = [];
    $params = [];

    // Status filter
    if (isset($_GET['status']) && in_array($_GET['status'], ['In Stock', 'Low Stock', 'Out of Stock'])) {
        $whereClauses[] = "status = :status";
        $params[':status'] = $_GET['status'];
    }

    // Expired filter
    if (isset($_GET['filter']) && $_GET['filter'] === 'expired') {
        $whereClauses[] = "expiration_date < CURDATE()";
    }

    // Chemical name filter
    if (isset($_GET['chemical_filter']) && !empty($_GET['chemical_filter'])) {
        $whereClauses[] = "chemical_name LIKE :chemical_name";
        $params[':chemical_name'] = '%' . $_GET['chemical_filter'] . '%';
    }

    // Build WHERE clause
    if (!empty($whereClauses)) {
        $baseQuery .= " WHERE " . implode(" AND ", $whereClauses);
    }

    // Expiration sorting
    $orderBy = " ORDER BY ";
    if (isset($_GET['sort']) && $_GET['sort'] === 'expiration') {
        $orderBy .= "expiration_date ASC";
    } else {
        $orderBy .= "created_at DESC";
    }

    // Prepare final query
    $query = $baseQuery . $orderBy;
    $stmt = $pdo->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $chemicals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chemical Inventory - MacJ Pest Control</title>
    <link rel="stylesheet" href="css/chemical-inventory-page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <link rel="stylesheet" href="css/modern-modal.css">
    <link rel="stylesheet" href="css/notification-override.css">
    <link rel="stylesheet" href="css/notification-viewed.css">
    <style>
        /* Action buttons in header */
        .action-buttons {
            display: flex;
            gap: 10px;
        }

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

        /* Chemical notification styles */
        .notification-icon-wrapper {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: rgba(59, 130, 246, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .notification-icon-wrapper.chemical-expiring {
            background-color: #ffe6e6;
        }

        .notification-icon-wrapper.chemical-expiring i {
            color: #cc0000;
        }

        .notification-item {
            display: flex;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }

        .notification-item:hover {
            background-color: #f9f9f9;
        }

        .notification-item.unread {
            background-color: #f0f7ff;
        }

        .notification-item.unread:hover {
            background-color: #e6f0ff;
        }

        /* Expired Chemicals Alert Styles */
        .expired-chemicals-alert {
            background-color: #fff0f0;
            border: 1px solid #ffcccc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .expired-chemicals-alert i {
            font-size: 24px;
            color: #cc0000;
        }

        .expired-chemicals-alert strong {
            color: #cc0000;
        }

        /* Expired date styling in the main table */
        .expired-date {
            color: #cc0000 !important;
            font-weight: bold;
            position: relative;
        }

        .expired-date::before {
            content: "\f06a"; /* Font Awesome exclamation circle */
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            margin-right: 5px;
            color: #cc0000;
        }

        /* Highlight the entire row for expired chemicals */
        tr.expired-chemical {
            background-color: #fff0f0 !important;
        }

        tr.expired-chemical:hover {
            background-color: #ffe6e6 !important;
        }

        /* Dilution Calculator Styles */
        .formula-display {
            font-family: monospace;
            padding: 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            margin: 5px 0;
            display: inline-block;
        }

        .fraction {
            display: inline-block;
            vertical-align: middle;
            text-align: center;
            margin: 0 5px;
        }

        .fraction .numerator {
            border-bottom: 1px solid #000;
            padding: 0 4px;
            display: block;
        }

        .fraction .denominator {
            padding: 0 4px;
            display: block;
        }

        .dilution-preview {
            font-size: 0.95rem;
        }

        .dilution-preview ol {
            padding-left: 20px;
        }

        .dilution-preview li {
            margin-bottom: 10px;
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
                try {
                    $checkColumnStmt = $pdo->prepare("SHOW COLUMNS FROM office_staff LIKE 'profile_picture'");
                    $checkColumnStmt->execute();
                    if ($checkColumnStmt->rowCount() > 0) {
                        $stmt = $pdo->prepare("SELECT profile_picture FROM office_staff WHERE staff_id = ?");
                        $stmt->execute([$staff_id]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            $profile_picture = $row['profile_picture'];
                        }
                    }
                } catch (PDOException $e) {
                    // Log error but continue
                    error_log("Error fetching profile picture: " . $e->getMessage());
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
                    <li class="active"><a href="chemical_inventory.php"><i class="fas fa-flask"></i> Chemical Inventory</a></li>
                    <li><a href="tools_equipment.php"><i class="fas fa-tools"></i> Tools and Equipment</a></li>
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

            <div class="chemicals-content">
                <div class="chemicals-header">
                    <h1><i class="fas fa-flask"></i> Chemical Inventory</h1>
                    <div class="action-buttons">
                        <a href="chemical_usage_log.php" class="btn btn-secondary">
                            <i class="fas fa-history"></i> Usage Log
                        </a>
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#chemicalModal">
                            <i class="fas fa-plus"></i> Add New Chemical
                        </button>
                    </div>
                </div>

                <!-- Inventory Summary -->
                <div class="inventory-summary">
                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--primary-color);">
                            <i class="fas fa-flask"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Total Chemicals</h3>
                            <p><?= $total_chemicals ?></p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--warning-color);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Low Stock</h3>
                            <p><?= $low_stock ?></p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--danger-color);">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Out of Stock</h3>
                            <p><?= $out_of_stock ?></p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--info-color);">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Expiring Soon</h3>
                            <p><?= $expiring_soon ?></p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: #cc0000;">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Expired</h3>
                            <p><?= $expired_count ?></p>
                        </div>
                    </div>
                </div>

                <!-- Filter Controls -->
                <div class="filter-container">
                    <form id="filterForm" method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; width: 100%;">
                        <div class="filter-group">
                            <label for="chemical-type">Chemical:</label>
                            <div class="input-group">
                                <input type="text" id="chemical-type" name="chemical_filter" class="form-control" placeholder="Search chemical name" value="<?= isset($_GET['chemical_filter']) ? htmlspecialchars($_GET['chemical_filter']) : '' ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="filter-group">
                            <label for="chemical-status">Status:</label>
                            <select id="chemical-status" name="status" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="In Stock" <?= isset($_GET['status']) && $_GET['status'] === 'In Stock' ? 'selected' : '' ?>>In Stock</option>
                                <option value="Low Stock" <?= isset($_GET['status']) && $_GET['status'] === 'Low Stock' ? 'selected' : '' ?>>Low Stock</option>
                                <option value="Out of Stock" <?= isset($_GET['status']) && $_GET['status'] === 'Out of Stock' ? 'selected' : '' ?>>Out of Stock</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="sort-by">Sort by:</label>
                            <select id="sort-by" name="sort" onchange="this.form.submit()">
                                <option value="">Newest Ordered</option>
                                <option value="expiration" <?= isset($_GET['sort']) && $_GET['sort'] === 'expiration' ? 'selected' : '' ?>>Closest To Expiration Date</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="filter-type">Filter:</label>
                            <select id="filter-type" name="filter" onchange="this.form.submit()">
                                <option value="">All Chemicals</option>
                                <option value="expired" <?= isset($_GET['filter']) && $_GET['filter'] === 'expired' ? 'selected' : '' ?>>Expired Only</option>
                            </select>
                        </div>
                    </form>
                </div>

                <?php if ($expired_count > 0): ?>
                <!-- Expired Chemicals Alert -->
                <div class="expired-chemicals-alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Warning:</strong> There are <?= $expired_count ?> expired chemicals in your inventory that should be disposed of properly.
                    </div>
                </div>
                <?php endif; ?>

                <!-- Chemicals Section -->
                <div class="chemicals-section">

                    <div class="chemicals-table-container">
                        <table class="chemicals-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Chemical Name</th>
                                    <th>Type</th>
                                    <th>Target Pest</th>
                                    <th>Quantity</th>
                                    <th>Expiration</th>
                                    <th>Status</th>
                                    <th>Last Ordered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($chemicals as $chemical):
                                    $isExpired = strtotime($chemical['expiration_date']) < strtotime('today');
                                ?>
                                <tr class="<?= $isExpired ? 'expired-chemical' : '' ?>">
                                    <td><?= $chemical['id'] ?></td>
                                    <td><?= htmlspecialchars($chemical['chemical_name']) ?></td>
                                    <td><?= htmlspecialchars($chemical['type']) ?></td>
                                    <td><?= htmlspecialchars($chemical['target_pest'] ?? 'Not specified') ?></td>
                                    <td><?= number_format($chemical['quantity'], 2) ?> <?= $chemical['unit'] ?></td>
                                    <td class="<?= $isExpired ? 'expired-date' : '' ?>">
                                        <?= date('M d, Y', strtotime($chemical['expiration_date'])) ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= match($chemical['status']) {
                                            'In Stock' => 'in-stock',
                                            'Low Stock' => 'low-stock',
                                            default => 'out-of-stock'
                                        } ?>"><?= $chemical['status'] ?></span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($chemical['last_ordered'] ?? $chemical['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-sm btn-info view-btn" data-id="<?= $chemical['id'] ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-sm btn-primary edit-btn" data-id="<?= $chemical['id'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-sm btn-danger delete-btn" data-id="<?= $chemical['id'] ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <!-- Create Modal -->
        <div class="modal fade" id="chemicalModal">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form id="chemicalForm">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-flask"></i>Add New Chemical</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body create-chemical-container">
                            <div class="detail-section">
                                <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="required"><i class="fas fa-flask"></i> Chemical Name</label>
                                            <input type="text" class="form-control" name="chemical_name" placeholder="Enter Chemical Name" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="required"><i class="fas fa-tag"></i> Type</label>
                                            <select class="form-control" name="type" required>
                                                <option value="">Select Type</option>
                                                <option>Insecticide</option>
                                                <option>Herbicide</option>
                                                <option>Rodenticide</option>
                                                <option>Fungicide</option>
                                                <option>Disinfection</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label><i class="fas fa-bug"></i> Target Pest</label>
                                            <select class="form-control" name="target_pest" id="target_pest">
                                                <option value="">Select Target Pest</option>
                                                <option>Crawling & Flying Pest</option>
                                                <option>Flying Pest</option>
                                                <option>Crawling Pest</option>
                                                <option>Cockroaches</option>
                                                <option>Termites</option>
                                                <option>Rodents</option>
                                                <option>Mosquitoes</option>
                                                <option>Ants</option>
                                                <option>Bed Bugs</option>
                                                <option>Flies</option>
                                                <option>Grass Problems</option>
                                                <option>Weeds</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="required"><i class="fas fa-balance-scale"></i> Quantity</label>
                                            <input type="number" step="0.01" class="form-control"
                                                name="quantity" min="0" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="required"><i class="fas fa-ruler"></i> Unit</label>
                                            <select class="form-control" name="unit" required>
                                                <option>Liters</option>
                                                <option>Kilograms</option>
                                                <option>Grams</option>
                                                <option>Pieces</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-section">
                                <h3><i class="fas fa-building"></i> Supplier Information</h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><i class="fas fa-industry"></i> Manufacturer</label>
                                            <input type="text" class="form-control" name="manufacturer" placeholder="Enter manufacturer name">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><i class="fas fa-truck"></i> Supplier</label>
                                            <input type="text" class="form-control" name="supplier" placeholder="Enter supplier name">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-section">
                                <h3><i class="fas fa-calculator"></i> Dilution Calculator</h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><i class="fas fa-tint"></i> Dilution Rate (ml per liter)</label>
                                            <input type="number" step="0.1" min="0" class="form-control"
                                                name="dilution_rate" id="dilution_rate" placeholder="e.g., 20 ml per liter">
                                            <small class="form-text text-muted">Amount of chemical per liter of water</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><i class="fas fa-expand"></i> Area Coverage (m² per liter)</label>
                                            <input type="number" step="0.1" min="0" class="form-control"
                                                name="area_coverage" id="area_coverage" value="100" placeholder="e.g., 100 m² per liter">
                                            <small class="form-text text-muted">Area covered by 1 liter of diluted solution</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="card bg-light">
                                            <div class="card-header">
                                                <h5><i class="fas fa-info-circle"></i> Dilution Calculator Preview</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="dilution-preview">
                                                    <p><strong>Recommended Chemical:</strong> <span id="preview_chemical_name">Chemical Name</span></p>
                                                    <p><strong>Calculation:</strong></p>
                                                    <ol>
                                                        <li><strong>Dilution Rate:</strong> <span id="preview_dilution_rate">20</span> ml per 1 liter of water</li>
                                                        <li><strong>Coverage:</strong> 1 liter of diluted solution covers <span id="preview_area_coverage">100</span> m²</li>
                                                        <li><strong>Total Spray Solution Needed for 200 m²:</strong>
                                                            <div class="formula-display">
                                                                <span class="fraction">
                                                                    <span class="numerator">1 L</span>
                                                                    <span class="denominator"><span id="preview_area_denominator">100</span> m²</span>
                                                                </span>
                                                                × 200 m² = <span id="preview_total_solution">2</span> liters
                                                            </div>
                                                        </li>
                                                        <li><strong>Total Chemical Required:</strong>
                                                            <div class="formula-display">
                                                                <span id="preview_dilution_rate2">20</span> mL/L × <span id="preview_total_solution2">2</span> L = <span id="preview_total_chemical">40</span> mL
                                                            </div>
                                                        </li>
                                                    </ol>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-section">
                                <h3><i class="fas fa-file-alt"></i> Additional Details</h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="required"><i class="fas fa-calendar-alt"></i> Expiration Date</label>
                                            <input type="date" class="form-control"
                                                name="expiration_date" required
                                                min="<?= date('Y-m-d') ?>">
                                        </div>
                                        <div class="form-group">
                                            <label><i class="fas fa-align-left"></i> Description</label>
                                            <textarea class="form-control" name="description" rows="2" placeholder="Brief description of the chemical"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><i class="fas fa-shield-alt"></i> Safety Information</label>
                                            <textarea class="form-control" name="safety_info" rows="4" placeholder="Safety precautions and handling instructions"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Chemical</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- View Chemical Modal -->
        <div class="modal fade" id="viewChemicalModal">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-eye"></i>Chemical Details</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body view-chemical-container">
                        <div class="detail-section">
                            <h3><i class="fas fa-info-circle"></i> Chemical Information</h3>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-flask"></i> Chemical Name</div>
                                    <div class="detail-value" id="viewChemicalName"></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-tag"></i> Type</div>
                                    <div class="detail-value" id="viewType"></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-bug"></i> Target Pest</div>
                                    <div class="detail-value" id="viewTargetPest"></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-balance-scale"></i> Quantity</div>
                                    <div class="detail-value" id="viewQuantity"></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-industry"></i> Manufacturer</div>
                                    <div class="detail-value" id="viewManufacturer"></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-truck"></i> Supplier</div>
                                    <div class="detail-value" id="viewSupplier"></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-calendar-alt"></i> Expiration Date</div>
                                    <div class="detail-value" id="viewExpirationDate"></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-ruler"></i> Unit</div>
                                    <div class="detail-value" id="viewUnit"></div>
                                </div>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h3><i class="fas fa-calculator"></i> Dilution Information</h3>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-tint"></i> Dilution Rate</div>
                                    <div class="detail-value" id="viewDilutionRate">Not specified</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-expand"></i> Area Coverage</div>
                                    <div class="detail-value" id="viewAreaCoverage">Not specified</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-calculator"></i> Calculation Example</div>
                                    <div class="detail-value" id="viewDilutionExample">
                                        <div class="dilution-preview">
                                            <p><strong>For 200 m² area:</strong></p>
                                            <ol>
                                                <li>Total solution needed: <span id="viewTotalSolution">2</span> liters</li>
                                                <li>Total chemical required: <span id="viewTotalChemical">40</span> ml</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h3><i class="fas fa-file-alt"></i> Additional Details</h3>
                            <div class="detail-item">
                                <div class="detail-label"><i class="fas fa-align-left"></i> Description</div>
                                <div class="detail-value" id="viewDescription"></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label"><i class="fas fa-shield-alt"></i> Safety Information</div>
                                <div class="detail-value" id="viewSafetyInfo"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editChemicalModal">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form id="editChemicalForm">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-edit"></i>Edit Chemical</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body edit-chemical-container">
                            <input type="hidden" name="id" id="editChemicalId">

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <strong>Note:</strong> All fields can now be updated. Make sure to review your changes before submitting.
                                </div>
                            </div>

                            <div class="detail-section">
                                <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="required"><i class="fas fa-flask"></i> Chemical Name</label>
                                            <input type="text" class="form-control" id="editChemicalName" name="chemical_name" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="required"><i class="fas fa-tag"></i> Type</label>
                                            <select class="form-control" id="editType" name="type" required>
                                                <option value="">Select Type</option>
                                                <option>Insecticide</option>
                                                <option>Herbicide</option>
                                                <option>Rodenticide</option>
                                                <option>Fungicide</option>
                                                <option>Disinfection</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label><i class="fas fa-bug"></i> Target Pest</label>
                                            <select class="form-control" name="target_pest" id="editTargetPest">
                                                <option value="">Select Target Pest</option>
                                                <option>Crawling & Flying Pest</option>
                                                <option>Flying Pest</option>
                                                <option>Crawling Pest</option>
                                                <option>Cockroaches</option>
                                                <option>Termites</option>
                                                <option>Rodents</option>
                                                <option>Mosquitoes</option>
                                                <option>Ants</option>
                                                <option>Bed Bugs</option>
                                                <option>Flies</option>
                                                <option>Grass Problems</option>
                                                <option>Weeds</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="required"><i class="fas fa-balance-scale"></i> Quantity</label>
                                            <input type="number" step="0.01" class="form-control"
                                                name="quantity" id="editQuantity" min="0" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="required"><i class="fas fa-ruler"></i> Unit</label>
                                            <select class="form-control" id="editUnit" name="unit" required>
                                                <option>Liters</option>
                                                <option>Kilograms</option>
                                                <option>Grams</option>
                                                <option>Pieces</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-section">
                                <h3><i class="fas fa-building"></i> Supplier Information</h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><i class="fas fa-industry"></i> Manufacturer</label>
                                            <input type="text" class="form-control" id="editManufacturer" name="manufacturer">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><i class="fas fa-truck"></i> Supplier</label>
                                            <input type="text" class="form-control" id="editSupplier" name="supplier">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-section">
                                <h3><i class="fas fa-calculator"></i> Dilution Calculator</h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><i class="fas fa-tint"></i> Dilution Rate (ml per liter)</label>
                                            <input type="number" step="0.1" min="0" class="form-control"
                                                name="dilution_rate" id="editDilutionRate" placeholder="e.g., 20 ml per liter">
                                            <small class="form-text text-muted">Amount of chemical per liter of water</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><i class="fas fa-expand"></i> Area Coverage (m² per liter)</label>
                                            <input type="number" step="0.1" min="0" class="form-control"
                                                name="area_coverage" id="editAreaCoverage" placeholder="e.g., 100 m² per liter">
                                            <small class="form-text text-muted">Area covered by 1 liter of diluted solution</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="card bg-light">
                                            <div class="card-header">
                                                <h5><i class="fas fa-info-circle"></i> Dilution Calculator Preview</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="dilution-preview">
                                                    <p><strong>Recommended Chemical:</strong> <span id="edit_preview_chemical_name">Chemical Name</span></p>
                                                    <p><strong>Calculation:</strong></p>
                                                    <ol>
                                                        <li><strong>Dilution Rate:</strong> <span id="edit_preview_dilution_rate">20</span> ml per 1 liter of water</li>
                                                        <li><strong>Coverage:</strong> 1 liter of diluted solution covers <span id="edit_preview_area_coverage">100</span> m²</li>
                                                        <li><strong>Total Spray Solution Needed for 200 m²:</strong>
                                                            <div class="formula-display">
                                                                <span class="fraction">
                                                                    <span class="numerator">1 L</span>
                                                                    <span class="denominator"><span id="edit_preview_area_denominator">100</span> m²</span>
                                                                </span>
                                                                × 200 m² = <span id="edit_preview_total_solution">2</span> liters
                                                            </div>
                                                        </li>
                                                        <li><strong>Total Chemical Required:</strong>
                                                            <div class="formula-display">
                                                                <span id="edit_preview_dilution_rate2">20</span> mL/L × <span id="edit_preview_total_solution2">2</span> L = <span id="edit_preview_total_chemical">40</span> mL
                                                            </div>
                                                        </li>
                                                    </ol>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-section">
                                <h3><i class="fas fa-file-alt"></i> Additional Details</h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="required"><i class="fas fa-calendar-alt"></i> Expiration Date</label>
                                            <input type="date" class="form-control" id="editExpirationDate" name="expiration_date" required>
                                        </div>
                                        <div class="form-group">
                                            <label><i class="fas fa-align-left"></i> Description</label>
                                            <textarea class="form-control" id="editDescription" name="description" rows="2"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><i class="fas fa-shield-alt"></i> Safety Information</label>
                                            <textarea class="form-control" id="editSafetyInfo" name="safety_info" rows="4"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Information</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script>
    // Dilution Calculator Functions
    function updateDilutionCalculator(isEdit = false) {
        // Get values from inputs based on whether we're in edit mode or not
        const prefix = isEdit ? 'edit' : '';
        const chemicalNameSelector = isEdit ? '#editChemicalName' : 'input[name="chemical_name"]';
        const dilutionRateSelector = isEdit ? '#editDilutionRate' : '#dilution_rate';
        const areaCoverageSelector = isEdit ? '#editAreaCoverage' : '#area_coverage';

        const chemicalName = $(chemicalNameSelector).val() || 'Chemical Name';
        const dilutionRate = parseFloat($(dilutionRateSelector).val()) || 20;
        const areaCoverage = parseFloat($(areaCoverageSelector).val()) || 100;
        const targetArea = 200; // Fixed at 200 m² for the example

        // Calculate total solution needed
        const totalSolution = (targetArea / areaCoverage).toFixed(2);

        // Calculate total chemical needed
        const totalChemical = (dilutionRate * totalSolution).toFixed(2);

        // Update preview elements
        const previewPrefix = isEdit ? '#edit_preview_' : '#preview_';
        $(previewPrefix + 'chemical_name').text(chemicalName);
        $(previewPrefix + 'dilution_rate').text(dilutionRate);
        $(previewPrefix + 'dilution_rate2').text(dilutionRate);
        $(previewPrefix + 'area_coverage').text(areaCoverage);
        $(previewPrefix + 'area_denominator').text(areaCoverage);
        $(previewPrefix + 'total_solution').text(totalSolution);
        $(previewPrefix + 'total_solution2').text(totalSolution);
        $(previewPrefix + 'total_chemical').text(totalChemical);
    }

    $(document).ready(function() {
        // Initialize dilution calculator
        updateDilutionCalculator();

        // Update calculator when inputs change
        $('#dilution_rate, #area_coverage, input[name="chemical_name"]').on('input', function() {
            updateDilutionCalculator();
        });

        // Set default values for common chemicals
        $('#target_pest').on('change', function() {
            const pestType = $(this).val();
            const chemicalType = $('select[name="type"]').val();

            // Default values
            let dilutionRate = 20;
            let areaCoverage = 100;

            // Adjust based on pest type and chemical type
            if (pestType === 'Termites') {
                dilutionRate = 12;
                areaCoverage = 100;
            } else if (pestType === 'Cockroaches' || pestType === 'Ants' || pestType === 'Bed Bugs') {
                dilutionRate = 20;
                areaCoverage = 100;
            } else if (pestType === 'Mosquitoes' || pestType === 'Flies') {
                dilutionRate = 20;
                areaCoverage = 100;
            } else if (pestType === 'Grass Problems' || pestType === 'Weeds') {
                dilutionRate = 30;
                areaCoverage = 100;
            }

            // Adjust based on chemical type
            if (chemicalType === 'Herbicide') {
                dilutionRate = 30;
            } else if (chemicalType === 'Rodenticide') {
                // Rodenticides typically don't use dilution
                dilutionRate = 0;
            } else if (chemicalType === 'Disinfection') {
                dilutionRate = 50;
            }

            // Set the values
            $('#dilution_rate').val(dilutionRate);
            $('#area_coverage').val(areaCoverage);

            // Update the calculator
            updateDilutionCalculator();
        });

        // Also update when chemical type changes
        $('select[name="type"]').on('change', function() {
            // Trigger the target pest change handler to recalculate
            $('#target_pest').trigger('change');
        });
        // Create new chemical
        $('#chemicalForm').submit(function(e) {
            e.preventDefault();
            const formData = $(this).serialize();

            $.ajax({
                type: 'POST',
                url: 'chemical_inventory.php',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#chemicalModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.error || 'Failed to save chemical'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    alert('Error: Could not save chemical. Please try again.');
                }
            });
        });

        // Edit chemical
        $(document).on('click', '.edit-btn', function() {
            const chemicalId = $(this).data('id');

            $.ajax({
                url: 'get_chemical.php',
                method: 'GET',
                data: { id: chemicalId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        $('#editChemicalId').val(response.data.id);
                        $('#editChemicalName').val(response.data.chemical_name);

                        // Set type dropdown value
                        $('#editType option').each(function() {
                            if ($(this).text() === response.data.type) {
                                $(this).prop('selected', true);
                            }
                        });

                        // Set target pest dropdown value
                        if (response.data.target_pest) {
                            $('#editTargetPest option').each(function() {
                                if ($(this).text() === response.data.target_pest) {
                                    $(this).prop('selected', true);
                                }
                            });
                        } else {
                            $('#editTargetPest').val('');
                        }

                        $('#editQuantity').val(response.data.quantity);

                        // Set unit dropdown value
                        $('#editUnit option').each(function() {
                            if ($(this).text() === response.data.unit) {
                                $(this).prop('selected', true);
                            }
                        });

                        // Set dilution rate and area coverage
                        $('#editDilutionRate').val(response.data.dilution_rate || 20);
                        $('#editAreaCoverage').val(response.data.area_coverage || 100);

                        $('#editManufacturer').val(response.data.manufacturer);
                        $('#editSupplier').val(response.data.supplier);
                        $('#editExpirationDate').val(response.data.expiration_date);
                        $('#editDescription').val(response.data.description);
                        $('#editSafetyInfo').val(response.data.safety_info);

                        // Update the dilution calculator preview
                        updateDilutionCalculator(true);

                        $('#editChemicalModal').modal('show');

                        // Set up event handlers for the edit form
                        $('#editDilutionRate, #editAreaCoverage, #editChemicalName').off('input').on('input', function() {
                            updateDilutionCalculator(true);
                        });

                        // Set default values when pest type changes in edit mode
                        $('#editTargetPest').off('change').on('change', function() {
                            const pestType = $(this).val();
                            const chemicalType = $('#editType').val();

                            // Default values
                            let dilutionRate = 20;
                            let areaCoverage = 100;

                            // Adjust based on pest type and chemical type
                            if (pestType === 'Termites') {
                                dilutionRate = 12;
                                areaCoverage = 100;
                            } else if (pestType === 'Cockroaches' || pestType === 'Ants' || pestType === 'Bed Bugs') {
                                dilutionRate = 20;
                                areaCoverage = 100;
                            } else if (pestType === 'Mosquitoes' || pestType === 'Flies') {
                                dilutionRate = 20;
                                areaCoverage = 100;
                            } else if (pestType === 'Grass Problems' || pestType === 'Weeds') {
                                dilutionRate = 30;
                                areaCoverage = 100;
                            }

                            // Adjust based on chemical type
                            if (chemicalType === 'Herbicide') {
                                dilutionRate = 30;
                            } else if (chemicalType === 'Rodenticide') {
                                // Rodenticides typically don't use dilution
                                dilutionRate = 0;
                            } else if (chemicalType === 'Disinfection') {
                                dilutionRate = 50;
                            }

                            // Set the values
                            $('#editDilutionRate').val(dilutionRate);
                            $('#editAreaCoverage').val(areaCoverage);

                            // Update the calculator
                            updateDilutionCalculator(true);
                        });

                        // Also update when chemical type changes in edit mode
                        $('#editType').off('change').on('change', function() {
                            // Trigger the target pest change handler to recalculate
                            $('#editTargetPest').trigger('change');
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    alert('Error: Could not load chemical details. Please try again.');
                }
            });
        });

        // Update chemical
        $('#editChemicalForm').submit(function(e) {
            e.preventDefault();
            if(confirm('Are you sure you want to update this chemical?')) {
                const formData = {
                    id: $('#editChemicalId').val(),
                    chemical_name: $('#editChemicalName').val(),
                    type: $('#editType').val(),
                    target_pest: $('#editTargetPest').val(),
                    quantity: $('#editQuantity').val(),
                    unit: $('#editUnit').val(),
                    manufacturer: $('#editManufacturer').val(),
                    supplier: $('#editSupplier').val(),
                    expiration_date: $('#editExpirationDate').val(),
                    description: $('#editDescription').val(),
                    safety_info: $('#editSafetyInfo').val(),
                    dilution_rate: $('#editDilutionRate').val(),
                    area_coverage: $('#editAreaCoverage').val()
                };

                $.ajax({
                    url: 'update_chemical.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            $('#editChemicalModal').modal('hide');
                            location.reload();
                        } else {
                            alert('Error: ' + (response.error || 'Failed to update chemical'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        alert('Error: Could not update chemical. Please try again.');
                    }
                });
            }
        });

        // Delete chemical
        $(document).on('click', '.delete-btn', function() {
            const chemicalId = $(this).data('id');
            if(confirm('WARNING: This will permanently delete the record!\n\nProceed?')) {
                $.ajax({
                    url: 'delete_chemical.php',
                    method: 'POST',
                    data: { id: chemicalId },
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + (response.error || 'Failed to delete chemical'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        alert('Error: Could not delete chemical. Please try again.');
                    }
                });
            }
        });

        // View Chemical
        $(document).on('click', '.view-btn', function() {
            const chemicalId = $(this).data('id');

            $.ajax({
                url: 'get_chemical.php',
                method: 'GET',
                data: { id: chemicalId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        // Populate view modal
                        $('#viewChemicalName').text(response.data.chemical_name);
                        $('#viewType').text(response.data.type);
                        $('#viewTargetPest').text(response.data.target_pest || 'Not specified');
                        $('#viewQuantity').text(
                            `${response.data.quantity} ${response.data.unit}`
                        );
                        $('#viewUnit').text(response.data.unit);
                        $('#viewManufacturer').text(response.data.manufacturer || 'N/A');
                        $('#viewSupplier').text(response.data.supplier || 'N/A');
                        $('#viewExpirationDate').text(
                            new Date(response.data.expiration_date).toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric'
                            })
                        );
                        $('#viewDescription').text(response.data.description || 'No description');
                        $('#viewSafetyInfo').text(response.data.safety_info || 'No safety information');

                        // Display dilution information
                        const dilutionRate = response.data.dilution_rate || 20;
                        const areaCoverage = response.data.area_coverage || 100;

                        $('#viewDilutionRate').text(dilutionRate + ' ml per liter');
                        $('#viewAreaCoverage').text(areaCoverage + ' m² per liter');

                        // Calculate example values
                        const targetArea = 200; // Fixed at 200 m²
                        const totalSolution = (targetArea / areaCoverage).toFixed(2);
                        const totalChemical = (dilutionRate * totalSolution).toFixed(2);

                        $('#viewTotalSolution').text(totalSolution);
                        $('#viewTotalChemical').text(totalChemical);

                        $('#viewChemicalModal').modal('show');
                    } else {
                        alert('Error: ' + (response.error || 'Failed to load chemical details'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    alert('Error: Could not load chemical details. Please try again.');
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

            // Fetch notifications immediately to show any expiring chemicals
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