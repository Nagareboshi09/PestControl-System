<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: ../SignIn.php");
    exit;
}
require_once '../db_connect.php';
require_once '../notification_functions.php';

// Get total client count
$countSql = "SELECT COUNT(*) as total_clients FROM clients";
$countResult = $conn->query($countSql);
$totalClients = $countResult->fetch_assoc()['total_clients'];

// Get clients with appointments count
$appointmentsSql = "SELECT COUNT(DISTINCT client_id) as clients_with_appointments FROM appointments";
$appointmentsResult = $conn->query($appointmentsSql);
$clientsWithAppointments = $appointmentsResult->fetch_assoc()['clients_with_appointments'];

// Get new clients in the last 30 days
$newClientsSql = "SELECT COUNT(*) as new_clients FROM clients WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$newClientsResult = $conn->query($newClientsSql);
$newClients = $newClientsResult->fetch_assoc()['new_clients'];

// Fetch all clients with contract information
$sql = "SELECT
            c.client_id,
            c.first_name,
            c.last_name,
            c.email,
            c.contact_number,
            c.location_address,
            c.type_of_place,
            c.registered_at,
            COUNT(DISTINCT a.appointment_id) as appointment_count,
            COUNT(DISTINCT CASE WHEN jo.client_approval_status = 'approved' OR jo.client_approval_status = 'one-time' THEN jo.job_order_id END) as contract_count
        FROM clients c
        LEFT JOIN appointments a ON c.client_id = a.client_id
        LEFT JOIN assessment_report ar ON a.appointment_id = ar.appointment_id
        LEFT JOIN job_order jo ON ar.report_id = jo.report_id
        GROUP BY c.client_id
        ORDER BY c.registered_at DESC";
$clients = $conn->query($sql);

// Check for errors
if (!$clients) {
    die("Error fetching clients: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Management | MacJ Pest Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/clients-page.css">
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
            <div class="clients-content">
                <div class="clients-header">
                    <h1><i class="fas fa-users"></i> Client Management</h1>
                    <div class="header-actions">
                        <div class="search-container">
                            <input type="text" id="clientSearch" placeholder="Search clients...">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                    </div>
                </div>

                <!-- Client Summary -->
                <div class="client-summary">
                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--primary-color);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Total Clients</h3>
                            <p><?= $totalClients ?></p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--success-color);">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="summary-info">
                            <h3>With Appointments</h3>
                            <p><?= $clientsWithAppointments ?></p>
                        </div>
                    </div>

                    <?php
                    // Count clients with contracts
                    $contractsSql = "SELECT COUNT(DISTINCT c.client_id) as clients_with_contracts
                                    FROM clients c
                                    JOIN appointments a ON c.client_id = a.client_id
                                    JOIN assessment_report ar ON a.appointment_id = ar.appointment_id
                                    JOIN job_order jo ON ar.report_id = jo.report_id
                                    WHERE jo.client_approval_status = 'approved' OR jo.client_approval_status = 'one-time'";
                    $contractsResult = $conn->query($contractsSql);
                    $clientsWithContracts = $contractsResult->fetch_assoc()['clients_with_contracts'];
                    ?>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: #4c51bf;">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <div class="summary-info">
                            <h3>With Contracts</h3>
                            <p><?= $clientsWithContracts ?></p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--info-color);">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="summary-info">
                            <h3>New (Last 30 Days)</h3>
                            <p><?= $newClients ?></p>
                        </div>
                    </div>
                </div>

                <!-- Clients Table -->
                <div class="table-responsive">
                    <table class="table table-hover clients-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Location</th>
                                <th>Type of Place</th>
                                <th>Registered</th>
                                <th>Appointments</th>
                                <th>Contracts</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($clients->num_rows > 0): ?>
                                <?php while($client = $clients->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($client['client_id']) ?></td>
                                        <td><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></td>
                                        <td><?= htmlspecialchars($client['email']) ?></td>
                                        <td><?= htmlspecialchars($client['contact_number']) ?></td>
                                        <td><?= htmlspecialchars(preg_replace('/\[[-\d\.]+,[-\d\.]+\]$/', '', $client['location_address'] ?? 'Not set')) ?></td>
                                        <td><?= htmlspecialchars($client['type_of_place'] ?? 'Not set') ?></td>
                                        <td><?= date('M d, Y', strtotime($client['registered_at'])) ?></td>
                                        <td>
                                            <span class="badge <?= $client['appointment_count'] > 0 ? 'badge-primary' : 'badge-secondary' ?>">
                                                <?= $client['appointment_count'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $client['contract_count'] > 0 ? 'badge-success' : 'badge-secondary' ?>">
                                                <?= $client['contract_count'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-info view-client-btn" data-id="<?= $client['client_id'] ?>" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger delete-client-btn"
                                                        data-id="<?= $client['client_id'] ?>"
                                                        data-name="<?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?>"
                                                        data-appointments="<?= $client['appointment_count'] ?>"
                                                        data-contracts="<?= $client['contract_count'] ?>"
                                                        title="Delete Client">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center">No clients found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Client Details Modal -->
    <div class="modal" id="clientDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Client Details</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div id="clientDetails">
                    <!-- Client details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary close-btn">Close</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteConfirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Deletion</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the client <strong id="deleteClientName"></strong>?</p>
                <div id="appointmentWarning" class="warning-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>This client has appointments. Deleting this client will also delete all associated appointments and reports.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary cancel-btn">Cancel</button>
                <button class="btn btn-danger confirm-delete-btn">Delete</button>
            </div>
        </div>
    </div>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <script src="js/chemical-notifications.js"></script>

    <!-- Client Details Scripts -->
    <script src="js/client_details.js"></script>

    <script>
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

            // Initialize client search functionality
            $('#clientSearch').on('keyup', function() {
                const searchValue = $(this).val().toLowerCase();
                $('.clients-table tbody tr').each(function() {
                    const clientName = $(this).find('td:nth-child(2)').text().toLowerCase();
                    const clientEmail = $(this).find('td:nth-child(3)').text().toLowerCase();
                    const clientContact = $(this).find('td:nth-child(4)').text().toLowerCase();
                    const clientLocation = $(this).find('td:nth-child(5)').text().toLowerCase();

                    if (clientName.includes(searchValue) ||
                        clientEmail.includes(searchValue) ||
                        clientContact.includes(searchValue) ||
                        clientLocation.includes(searchValue)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
        });
    </script>
</body>
</html>
