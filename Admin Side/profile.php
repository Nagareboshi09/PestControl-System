<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: ../SignIn.php");
    exit;
}
require_once '../db_connect.php';
require_once '../notification_functions.php';

// Get admin profile information
$staff_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Check if the office_staff table has the necessary columns
$result = $conn->query("SHOW COLUMNS FROM office_staff LIKE 'full_name'");
$hasFullName = $result->num_rows > 0;

$result = $conn->query("SHOW COLUMNS FROM office_staff LIKE 'email'");
$hasEmail = $result->num_rows > 0;

$result = $conn->query("SHOW COLUMNS FROM office_staff LIKE 'contact_number'");
$hasContactNumber = $result->num_rows > 0;

$result = $conn->query("SHOW COLUMNS FROM office_staff LIKE 'profile_picture'");
$hasProfilePicture = $result->num_rows > 0;

// Add missing columns if needed
if (!$hasFullName) {
    $conn->query("ALTER TABLE office_staff ADD COLUMN full_name VARCHAR(100) DEFAULT NULL");
}

if (!$hasEmail) {
    $conn->query("ALTER TABLE office_staff ADD COLUMN email VARCHAR(100) DEFAULT NULL");
}

if (!$hasContactNumber) {
    $conn->query("ALTER TABLE office_staff ADD COLUMN contact_number VARCHAR(20) DEFAULT NULL");
}

if (!$hasProfilePicture) {
    $conn->query("ALTER TABLE office_staff ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL");
}

// Get admin profile data
$stmt = $conn->prepare("SELECT * FROM office_staff WHERE staff_id = ?");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Handle profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $contact_number = $conn->real_escape_string($_POST['contact_number']);
    $new_username = $conn->real_escape_string($_POST['username']);

    // Check if username is being changed and if it's already taken
    if ($new_username !== $username) {
        $check_username = $conn->prepare("SELECT staff_id FROM office_staff WHERE username = ? AND staff_id != ?");
        $check_username->bind_param("si", $new_username, $staff_id);
        $check_username->execute();
        $result = $check_username->get_result();

        if ($result->num_rows > 0) {
            $error_message = "Username already taken. Please choose a different one.";
        } else {
            $username = $new_username;
            $_SESSION['username'] = $new_username;
        }
    }

    // Handle password change
    $password_sql = "";
    if (!empty($_POST['new_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Check if current password is correct
        $check_password = $conn->prepare("SELECT password FROM office_staff WHERE staff_id = ?");
        $check_password->bind_param("i", $staff_id);
        $check_password->execute();
        $result = $check_password->get_result();
        $current_hash = $result->fetch_assoc()['password'];

        if (md5($current_password) !== $current_hash) {
            $error_message = "Current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New password and confirmation do not match.";
        } else {
            $hashed_password = md5($new_password);
            $password_sql = ", password = '$hashed_password'";
        }
    }

    // Handle profile picture upload
    $profile_picture_sql = "";
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../uploads/admin/";

        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
        $target_file = $upload_dir . $file_name;

        // Check if file is an image
        $check = getimagesize($_FILES['profile_picture']['tmp_name']);
        if ($check !== false) {
            // Upload file
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                $profile_picture_sql = ", profile_picture = '$file_name'";
            } else {
                $error_message = "Failed to upload profile picture.";
            }
        } else {
            $error_message = "Uploaded file is not an image.";
        }
    }

    // Update profile if no errors
    if (empty($error_message)) {
        $update_sql = "UPDATE office_staff SET
                      username = '$new_username',
                      full_name = '$full_name',
                      email = '$email',
                      contact_number = '$contact_number'
                      $password_sql
                      $profile_picture_sql
                      WHERE staff_id = $staff_id";

        if ($conn->query($update_sql)) {
            $success_message = "Profile updated successfully!";

            // Refresh admin data
            $stmt = $conn->prepare("SELECT * FROM office_staff WHERE staff_id = ?");
            $stmt->bind_param("i", $staff_id);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
        } else {
            $error_message = "Failed to update profile: " . $conn->error;
        }
    }
}

// Get profile picture URL
$profile_picture_url = !empty($admin['profile_picture'])
    ? "../uploads/admin/" . $admin['profile_picture']
    : "../assets/default-profile.jpg";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - MacJ Pest Control</title>
    <link rel="stylesheet" href="css/profile-page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <link rel="stylesheet" href="css/modern-modal.css">
    <link rel="stylesheet" href="css/notification-override.css">
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

        /* Profile specific styles */
        :root {
            --primary-color: #3b82f6;
            --primary-dark: #2563eb;
            --secondary-color: #10b981;
            --secondary-dark: #059669;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --light-color: #f3f4f6;
            --dark-color: #1f2937;
            --gray-color: #6b7280;
            --border-color: #e5e7eb;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Profile specific styles */
        .profile-content {
            width: 100%;
            flex: 1;
        }

        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .profile-header h1 {
            margin: 0;
            color: var(--primary-color);
            font-size: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .profile-header h1 i {
            margin-right: 10px;
        }

        .profile-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .profile-picture-container {
            position: relative;
            margin-right: 40px;
        }

        .profile-picture {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .profile-picture:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
        }

        .profile-picture-edit {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background-color: var(--primary-color);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s ease;
        }

        .profile-picture-edit:hover {
            background-color: var(--primary-dark);
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h1 {
            margin-bottom: 10px;
            color: var(--dark-color);
            font-size: 2.2rem;
            font-weight: 700;
        }

        .profile-info p {
            color: var(--gray-color);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            font-size: 1.1rem;
        }

        .profile-info p i {
            width: 25px;
            margin-right: 10px;
            color: var(--primary-color);
        }

        .profile-info .admin-badge {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-top: 10px;
        }

        .profile-tabs {
            margin-bottom: 30px;
        }

        .nav-tabs .nav-link {
            color: var(--gray-color);
            font-weight: 500;
            padding: 10px 20px;
            border: none;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background-color: transparent;
        }

        .nav-tabs .nav-link:hover:not(.active) {
            border-bottom-color: var(--border-color);
        }

        .profile-content {
            padding: 20px 0;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px 15px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25);
        }

        .form-text {
            color: var(--gray-color);
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .password-section {
            margin-top: 40px;
            padding: 25px;
            border-radius: 10px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .password-section h4 {
            color: var(--dark-color);
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .password-section h4 i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-update {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            padding: 12px 25px;
        }

        .btn-update:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-update:active {
            transform: translateY(0);
        }

        .btn-update i {
            margin-right: 8px;
        }

        .activity-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease;
        }

        .activity-card:hover {
            transform: translateY(-5px);
        }

        .activity-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e0f2fe;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
        }

        .activity-title {
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .activity-time {
            color: var(--gray-color);
            font-size: 0.85rem;
            margin-top: 3px;
        }

        .activity-content {
            color: var(--gray-color);
            margin-bottom: 0;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #e0f2fe;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--gray-color);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-picture-container {
                margin-right: 0;
                margin-bottom: 30px;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }
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

    <!-- Mobile menu toggle -->
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>MacJ Pest Control</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li class="active"><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                    <li><a href="calendar.php"><i class="fas fa-calendar-alt"></i> Calendar</a></li>
                    <li><a href="assessment_report.php"><i class="fas fa-clipboard-check"></i> Assessment Report</a></li>
                    <li><a href="joborder_report.php"><i class="fas fa-tasks"></i> Job Order Report</a></li>
                    <li><a href="chemical_inventory.php"><i class="fas fa-flask"></i> Chemical Inventory</a></li>
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
            <div class="profile-content">
                <div class="profile-header">
                    <h1><i class="fas fa-user-circle"></i> My Profile</h1>
                </div>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="profile-container">
                    <div class="profile-user-header">
                        <div class="profile-picture-container">
                            <img src="<?php echo $profile_picture_url; ?>" alt="Profile Picture" class="profile-picture" id="profilePicturePreview">
                            <label for="profile_picture" class="profile-picture-edit" title="Change profile picture">
                                <i class="fas fa-camera"></i>
                            </label>
                        </div>
                        <div class="profile-info">
                            <h1><?php echo htmlspecialchars($admin['full_name'] ?? $admin['username']); ?></h1>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($admin['email'] ?? 'No email set'); ?></p>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($admin['contact_number'] ?? 'No contact number set'); ?></p>
                            <p><i class="fas fa-user-shield"></i> Administrator</p>
                            <span class="admin-badge"><i class="fas fa-crown me-1"></i> Admin Access</span>
                        </div>
                    </div>

                    <!-- Stats Section -->
                    <div class="stats-container">
                        <?php
                        // Get some basic stats for the admin
                        $total_technicians = 0;
                        $total_clients = 0;
                        $total_appointments = 0;
                        $total_job_orders = 0;

                        // Count technicians
                        $result = $conn->query("SELECT COUNT(*) as count FROM technicians");
                        if ($result && $row = $result->fetch_assoc()) {
                            $total_technicians = $row['count'];
                        }

                        // Count clients
                        $result = $conn->query("SELECT COUNT(*) as count FROM clients");
                        if ($result && $row = $result->fetch_assoc()) {
                            $total_clients = $row['count'];
                        }

                        // Count appointments
                        $result = $conn->query("SELECT COUNT(*) as count FROM appointments");
                        if ($result && $row = $result->fetch_assoc()) {
                            $total_appointments = $row['count'];
                        }

                        // Count job orders
                        $result = $conn->query("SELECT COUNT(*) as count FROM job_order");
                        if ($result && $row = $result->fetch_assoc()) {
                            $total_job_orders = $row['count'];
                        }
                        ?>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <div class="stat-value"><?php echo $total_technicians; ?></div>
                            <div class="stat-label">Technicians</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon" style="background-color: #fee2e2; color: #ef4444;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-value"><?php echo $total_clients; ?></div>
                            <div class="stat-label">Clients</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon" style="background-color: #e0f2fe; color: #0ea5e9;">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-value"><?php echo $total_appointments; ?></div>
                            <div class="stat-label">Appointments</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon" style="background-color: #dcfce7; color: #10b981;">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="stat-value"><?php echo $total_job_orders; ?></div>
                            <div class="stat-label">Job Orders</div>
                        </div>
                    </div>

                    <div class="profile-tabs">
                        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit" type="button" role="tab" aria-controls="edit" aria-selected="true">
                                    <i class="fas fa-user-edit me-2"></i>Edit Profile
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab" aria-controls="activity" aria-selected="false">
                                    <i class="fas fa-history me-2"></i>Recent Activity
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="profile-content">
                        <div class="tab-content" id="profileTabsContent">
                            <div class="tab-pane fade show active" id="edit" role="tabpanel" aria-labelledby="edit-tab">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="username" class="form-label">Username</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="full_name" class="form-label">Full Name</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($admin['full_name'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="email" class="form-label">Email</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="contact_number" class="form-label">Contact Number</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                                    <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($admin['contact_number'] ?? ''); ?>" pattern="09[0-9]{9}" title="Please enter a valid 11-digit Philippine mobile number starting with '09'">
                                                </div>
                                                <small class="form-text text-muted">Format: 09XXXXXXXXX (11 digits)</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="profile_picture" class="form-label">Profile Picture</label>
                                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this)" style="display: none;">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-image"></i></span>
                                            <input type="text" class="form-control" id="file-name" readonly placeholder="No file selected">
                                            <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('profile_picture').click()">
                                                <i class="fas fa-upload me-2"></i>Choose File
                                            </button>
                                        </div>
                                        <small class="form-text text-muted">Upload a new profile picture (optional). Recommended size: 200x200 pixels or larger.</small>
                                    </div>

                                    <div class="password-section">
                                        <h4><i class="fas fa-lock"></i> Change Password</h4>
                                        <p class="text-muted">Leave these fields blank if you don't want to change your password</p>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="current_password" class="form-label">Current Password</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                                                        <input type="password" class="form-control" id="current_password" name="current_password">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="new_password" class="form-label">New Password</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                        <input type="password" class="form-control" id="new_password" name="new_password">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group text-end mt-4">
                                        <button type="submit" name="update_profile" class="btn btn-primary btn-update">
                                            <i class="fas fa-save"></i> Update Profile
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
                                <div id="activity-loading" class="text-center p-4" style="display: none;">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2">Loading recent activities...</p>
                                </div>
                                <div id="activity-content">
                                    <div class="text-center p-5">
                                        <i class="fas fa-history fa-3x mb-3"></i>
                                        <h4>Recent Activity</h4>
                                        <p class="text-muted">Your recent activities will appear here when you click the Recent Activity tab.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

    <!-- Profile specific scripts -->
    <script>
        $(document).ready(function() {
            // Preview profile picture before upload
            function previewImage(input) {
                if (input.files && input.files[0]) {
                    var reader = new FileReader();

                    reader.onload = function(e) {
                        $('#profilePicturePreview').attr('src', e.target.result);
                        $('#file-name').val(input.files[0].name);
                    }

                    reader.readAsDataURL(input.files[0]);
                }
            }

            // Assign the previewImage function to the global scope
            window.previewImage = previewImage;

            // Initialize tabs
            $('.nav-tabs .nav-link').on('click', function(e) {
                e.preventDefault();

                // Get the target tab content
                const target = $(this).attr('data-bs-target').replace('#', '');
                const isActivityTab = target === 'activity';

                // If this is the first time clicking the activity tab, show loading state
                if (isActivityTab && !activitiesLoaded) {
                    // Clear the placeholder message
                    $('#activity-content').empty();
                }

                // Remove active class from all tabs
                $('.nav-tabs .nav-link').removeClass('active').attr('aria-selected', 'false');

                // Add active class to clicked tab
                $(this).addClass('active').attr('aria-selected', 'true');

                // Hide all tab contents
                $('.tab-pane').removeClass('show active');

                // Show the target tab content
                $('#' + target).addClass('show active');

                // If the activity tab is clicked, load the activities
                if (isActivityTab) {
                    loadActivities();
                }
            });

            // Track if activities have been loaded
            let activitiesLoaded = false;

            // Function to load activities via AJAX
            function loadActivities() {
                // If activities have already been loaded, don't reload them
                if (activitiesLoaded) {
                    return;
                }

                // Show loading indicator and clear placeholder content
                $('#activity-loading').show();
                $('#activity-content').empty();

                // Fetch activities
                $.ajax({
                    url: 'get_activities.php',
                    method: 'GET',
                    success: function(response) {
                        // Hide loading indicator
                        $('#activity-loading').hide();

                        // Display activities
                        $('#activity-content').html(response);

                        // Initialize hover effects for newly loaded activity cards
                        initActivityCardHoverEffects();

                        // Mark activities as loaded
                        activitiesLoaded = true;
                    },
                    error: function(xhr, status, error) {
                        // Hide loading indicator
                        $('#activity-loading').hide();

                        // Display error message
                        $('#activity-content').html(
                            '<div class="alert alert-danger">' +
                            '<i class="fas fa-exclamation-circle me-2"></i>' +
                            'Error loading activities: ' + error +
                            '</div>'
                        );

                        // Allow retry on error
                        activitiesLoaded = false;
                    }
                });
            }

            // Function to initialize hover effects for activity cards
            function initActivityCardHoverEffects() {
                $('.activity-card').hover(
                    function() {
                        $(this).css({
                            'transform': 'translateY(-5px)',
                            'box-shadow': '0 10px 15px rgba(0, 0, 0, 0.1)'
                        });
                    },
                    function() {
                        $(this).css({
                            'transform': 'translateY(0)',
                            'box-shadow': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)'
                        });
                    }
                );
            }

            // Add animation effects for stat cards
            $('.stat-card').hover(
                function() {
                    $(this).css({
                        'transform': 'translateY(-10px)',
                        'box-shadow': '0 10px 20px rgba(0, 0, 0, 0.1)'
                    });
                },
                function() {
                    $(this).css({
                        'transform': 'translateY(0)',
                        'box-shadow': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)'
                    });
                }
            );

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

    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <script src="js/chemical-notifications.js"></script>
</body>
</html>
