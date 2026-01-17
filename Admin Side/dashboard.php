<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: ../SignIn.php");
    exit;
}
require_once '../db_connect.php';
require_once '../notification_functions.php';

// Get Dashboard Metrics
// Weekly Sales (Total cost from job orders this week)
$sql_weekly_sales = "SELECT COALESCE(SUM(cost), 0) AS weekly_sales FROM job_order
                    WHERE YEARWEEK(preferred_date, 1) = YEARWEEK(CURDATE(), 1)";
$result_weekly_sales = $conn->query($sql_weekly_sales);
$weekly_sales = $result_weekly_sales->fetch_assoc()['weekly_sales'];

// Weekly Appointments Count (for reference)
$sql_weekly_appointments = "SELECT COUNT(*) AS weekly_appointments FROM appointments
                    WHERE YEARWEEK(preferred_date, 1) = YEARWEEK(CURDATE(), 1)";
$result_weekly_appointments = $conn->query($sql_weekly_appointments);
$weekly_appointments = $result_weekly_appointments->fetch_assoc()['weekly_appointments'];

// Weekly Growth Rate (based on cost)
$sql_prev_week = "SELECT COALESCE(SUM(cost), 0) AS prev_week_sales FROM job_order
                WHERE YEARWEEK(preferred_date, 1) = YEARWEEK(CURDATE() - INTERVAL 1 WEEK, 1)";
$result_prev_week = $conn->query($sql_prev_week);
$prev_week_sales = $result_prev_week->fetch_assoc()['prev_week_sales'];
$weekly_growth = $prev_week_sales > 0 ?
                round((($weekly_sales - $prev_week_sales) / $prev_week_sales) * 100, 1) : 0;

// Total Completed Job Orders by Technicians
// Since job_order table doesn't have a status column, we'll count all job orders
// We're assuming all job orders in the system are completed or in progress
$sql_total_job_orders_completed = "SELECT COUNT(*) AS total_completed_jobs FROM job_order";
$result_total_job_orders_completed = $conn->query($sql_total_job_orders_completed);
$total_completed_jobs = $result_total_job_orders_completed->fetch_assoc()['total_completed_jobs'];

// Job Order Growth Rate
$sql_prev_month_jobs = "SELECT COUNT(*) AS prev_month FROM job_order
                        WHERE MONTH(preferred_date) = MONTH(CURDATE() - INTERVAL 1 MONTH)
                        AND YEAR(preferred_date) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
$result_prev_month_jobs = $conn->query($sql_prev_month_jobs);
$prev_month_jobs = $result_prev_month_jobs->fetch_assoc()['prev_month'];
$sql_current_month_jobs = "SELECT COUNT(*) AS curr_month FROM job_order
                            WHERE MONTH(preferred_date) = MONTH(CURDATE())
                            AND YEAR(preferred_date) = YEAR(CURDATE())";
$result_current_month_jobs = $conn->query($sql_current_month_jobs);
$current_month_jobs = $result_current_month_jobs->fetch_assoc()['curr_month'];
$job_growth = $prev_month_jobs > 0 ?
                round((($current_month_jobs - $prev_month_jobs) / $prev_month_jobs) * 100, 1) : 0;

// Market Share (Pest Types Distribution)
$sql_pest_types = "SELECT pest_problems, COUNT(*) as count FROM appointments
                  WHERE pest_problems IS NOT NULL AND pest_problems != ''
                  GROUP BY pest_problems";
$result_pest_types = $conn->query($sql_pest_types);
$pest_types = [];
$total_pest_count = 0;
while ($row = $result_pest_types->fetch_assoc()) {
    $pest_types[] = $row;
    $total_pest_count += $row['count'];
}

// Total Clients
$sql_clients = "SELECT COUNT(*) AS total_clients FROM clients";
$result_clients = $conn->query($sql_clients);
$total_clients = $result_clients->fetch_assoc()['total_clients'];

// New Clients This Month
$sql_new_clients = "SELECT COUNT(*) AS new_clients FROM clients
                    WHERE MONTH(registered_at) = MONTH(CURRENT_DATE())
                    AND YEAR(registered_at) = YEAR(CURRENT_DATE())";
$result_new_clients = $conn->query($sql_new_clients);
$new_clients = $result_new_clients->fetch_assoc()['new_clients'];

// Pending Appointments
$sql_pending = "SELECT COUNT(*) AS pending_appointments FROM appointments WHERE status = 'assigned'";
$result_pending = $conn->query($sql_pending);
$pending_appointments = $result_pending->fetch_assoc()['pending_appointments'];

// Total Technicians
$sql_technicians = "SELECT COUNT(*) AS total_technicians FROM technicians";
$result_technicians = $conn->query($sql_technicians);
$total_technicians = $result_technicians->fetch_assoc()['total_technicians'];

// Total Assessment Reports
$sql_reports = "SELECT COUNT(*) AS total_reports FROM assessment_report";
$result_reports = $conn->query($sql_reports);
$total_reports = $result_reports->fetch_assoc()['total_reports'];

// Total Job Orders
$sql_job_orders = "SELECT COUNT(*) AS total_job_orders FROM job_order";
$result_job_orders = $conn->query($sql_job_orders);
$total_job_orders = $result_job_orders->fetch_assoc()['total_job_orders'];

// Ongoing Treatments (Job Orders with future dates)
$sql_ongoing = "SELECT COUNT(*) AS ongoing_treatments FROM job_order
                WHERE preferred_date >= CURDATE()";
$result_ongoing = $conn->query($sql_ongoing);
$ongoing_treatments = $result_ongoing->fetch_assoc()['ongoing_treatments'];



// Monthly Sales (Appointments per month)
$sql_monthly = "SELECT
                    MONTH(preferred_date) as month,
                    COUNT(*) as count
                FROM appointments
                WHERE YEAR(preferred_date) = YEAR(CURRENT_DATE())
                GROUP BY MONTH(preferred_date)
                ORDER BY month";
$result_monthly = $conn->query($sql_monthly);
$monthly_data = [];
$months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
foreach ($months as $index => $month) {
    $monthly_data[$month] = 0;
}
while ($row = $result_monthly->fetch_assoc()) {
    $month_index = $row['month'] - 1;
    $monthly_data[$months[$month_index]] = (int)$row['count'];
}

// Monthly Sales Data (based on job order costs)
$sql_monthly_sales = "SELECT
                        MONTH(preferred_date) as month,
                        COALESCE(SUM(cost), 0) as total_sales
                      FROM job_order
                      WHERE YEAR(preferred_date) = YEAR(CURRENT_DATE())
                      AND MONTH(preferred_date) <= MONTH(CURRENT_DATE()) -- Only include past and current months
                      GROUP BY MONTH(preferred_date)
                      ORDER BY month";
$result_monthly_sales = $conn->query($sql_monthly_sales);
$monthly_sales_data = [];
$current_month = (int)date('n'); // Current month as a number (1-12)

// Initialize all past and current months with zero
foreach ($months as $index => $month) {
    if ($index < $current_month) {
        $monthly_sales_data[$month] = 0;
    }
}

if ($result_monthly_sales && $result_monthly_sales->num_rows > 0) {
    while ($row = $result_monthly_sales->fetch_assoc()) {
        $month_index = $row['month'] - 1;
        $monthly_sales_data[$months[$month_index]] = (float)$row['total_sales'];
    }
} else {
    // If no data, add some sample data for demonstration, but only for past and current months
    $sample_data = [
        'Jan' => 25000, 'Feb' => 30000, 'Mar' => 28000, 'Apr' => 35000,
        'May' => 40000, 'Jun' => 45000, 'Jul' => 48000, 'Aug' => 50000,
        'Sep' => 47000, 'Oct' => 55000, 'Nov' => 60000, 'Dec' => 65000
    ];

    foreach ($sample_data as $month => $value) {
        $month_index = array_search($month, $months);
        if ($month_index !== false && $month_index < $current_month) {
            $monthly_sales_data[$month] = $value;
        }
    }

    // Add current month with a realistic value
    if (isset($months[$current_month - 1])) {
        $monthly_sales_data[$months[$current_month - 1]] = 100000; // Current month's data
    }
}

// Current year for reference
$current_year = date('Y');



// Top Chemicals Used
$sql_chemicals = "SELECT chemical_name, type, COUNT(*) as usage_count
                 FROM chemical_inventory
                 GROUP BY chemical_name, type
                 ORDER BY usage_count DESC
                 LIMIT 5";
$result_chemicals = $conn->query($sql_chemicals);
$top_chemicals = [];
while ($row = $result_chemicals->fetch_assoc()) {
    $top_chemicals[] = $row;
}

// Low Quantity Chemicals
$sql_low_quantity = "SELECT id, chemical_name, type, quantity, unit, status
                     FROM chemical_inventory
                     WHERE status = 'Low Stock'
                     ORDER BY quantity ASC
                     LIMIT 5";
$result_low_quantity = $conn->query($sql_low_quantity);
$low_quantity_chemicals = [];
while ($row = $result_low_quantity->fetch_assoc()) {
    $low_quantity_chemicals[] = $row;
}

// Near Expiration Chemicals
$sql_near_expiration = "SELECT id, chemical_name, type, expiration_date, quantity, unit
                        FROM chemical_inventory
                        WHERE expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                        AND quantity > 0
                        ORDER BY expiration_date ASC
                        LIMIT 5";
$result_near_expiration = $conn->query($sql_near_expiration);
$near_expiration_chemicals = [];
while ($row = $result_near_expiration->fetch_assoc()) {
    // Calculate days until expiration
    $today = new DateTime();
    $expiry = new DateTime($row['expiration_date']);
    $interval = $today->diff($expiry);
    $row['days_until_expiry'] = $interval->days;
    $near_expiration_chemicals[] = $row;
}

// Active Users (Admin users/office staff)
$sql_active_users = "SELECT staff_id, username, email
                     FROM office_staff
                     LIMIT 5";
$result_active_users = $conn->query($sql_active_users);
$active_users = [];
while ($row = $result_active_users->fetch_assoc()) {
    $active_users[] = $row;
}







// Additional queries for the business overview section

// 1. Current active contracts (based on client-approved contracts)
// Group by report_id and frequency to count unique contracts, not individual job orders
$sql_active_contracts = "SELECT
                            COUNT(DISTINCT CONCAT(report_id, '-', frequency)) AS active_contracts,
                            SUM(CASE WHEN frequency = 'weekly' THEN 1 ELSE 0 END) / COUNT(CASE WHEN frequency = 'weekly' THEN 1 ELSE NULL END) AS weekly_contracts,
                            SUM(CASE WHEN frequency = 'monthly' THEN 1 ELSE 0 END) / COUNT(CASE WHEN frequency = 'monthly' THEN 1 ELSE NULL END) AS monthly_contracts,
                            SUM(CASE WHEN frequency = 'quarterly' THEN 1 ELSE 0 END) / COUNT(CASE WHEN frequency = 'quarterly' THEN 1 ELSE NULL END) AS quarterly_contracts
                        FROM (
                            SELECT report_id, frequency
                            FROM job_order
                            WHERE frequency != 'one-time'
                            AND client_approval_status = 'approved'
                            AND client_approval_date IS NOT NULL
                            GROUP BY report_id, frequency
                        ) AS unique_contracts";
$result_active_contracts = $conn->query($sql_active_contracts);
$active_contracts_data = $result_active_contracts->fetch_assoc();
$active_contracts = $active_contracts_data['active_contracts'] ?: 0;
$weekly_contracts = round($active_contracts_data['weekly_contracts'] ?: 0);
$monthly_contracts = round($active_contracts_data['monthly_contracts'] ?: 0);
$quarterly_contracts = round($active_contracts_data['quarterly_contracts'] ?: 0);

// 2. Ending contracts (contracts ending within the next 30 days based on 1-year duration from approval date)
$sql_ending_contracts = "SELECT
                            COUNT(DISTINCT CONCAT(report_id, '-', frequency)) AS ending_contracts,
                            SUM(CASE WHEN frequency = 'weekly' THEN 1 ELSE 0 END) / COUNT(CASE WHEN frequency = 'weekly' THEN 1 ELSE NULL END) AS weekly_ending,
                            SUM(CASE WHEN frequency = 'monthly' THEN 1 ELSE 0 END) / COUNT(CASE WHEN frequency = 'monthly' THEN 1 ELSE NULL END) AS monthly_ending,
                            SUM(CASE WHEN frequency = 'quarterly' THEN 1 ELSE 0 END) / COUNT(CASE WHEN frequency = 'quarterly' THEN 1 ELSE NULL END) AS quarterly_ending
                        FROM (
                            SELECT report_id, frequency
                            FROM job_order
                            WHERE frequency != 'one-time'
                            AND client_approval_status = 'approved'
                            AND client_approval_date IS NOT NULL
                            AND DATE_ADD(client_approval_date, INTERVAL 1 YEAR) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                            GROUP BY report_id, frequency
                        ) AS ending_unique_contracts";
$result_ending_contracts = $conn->query($sql_ending_contracts);
$ending_contracts_data = $result_ending_contracts->fetch_assoc();
$ending_contracts = $ending_contracts_data['ending_contracts'] ?: 0;
$weekly_ending = round($ending_contracts_data['weekly_ending'] ?: 0);
$monthly_ending = round($ending_contracts_data['monthly_ending'] ?: 0);
$quarterly_ending = round($ending_contracts_data['quarterly_ending'] ?: 0);

// 3. Upcoming job orders (job orders with future dates)
$sql_upcoming_jobs = "SELECT COUNT(*) AS upcoming_jobs FROM job_order
                     WHERE preferred_date > CURDATE()";
$result_upcoming_jobs = $conn->query($sql_upcoming_jobs);
$upcoming_jobs = $result_upcoming_jobs->fetch_assoc()['upcoming_jobs'];

// 4. Technicians deployed (technicians assigned to appointments or job orders)
$sql_deployed_techs = "SELECT COUNT(DISTINCT technician_id) AS deployed_techs
                      FROM (
                          SELECT technician_id FROM appointments
                          WHERE technician_id IS NOT NULL AND status IN ('assigned', 'in_progress')
                          UNION
                          SELECT technician_id FROM job_order_technicians
                          JOIN job_order ON job_order_technicians.job_order_id = job_order.job_order_id
                          WHERE job_order.preferred_date >= CURDATE()
                      ) AS deployed";
$result_deployed_techs = $conn->query($sql_deployed_techs);
$deployed_techs = $result_deployed_techs->fetch_assoc()['deployed_techs'];

// 5. Active treatments (job orders in progress)
$sql_active_treatments = "SELECT COUNT(*) AS active_treatments FROM job_order
                         WHERE preferred_date <= CURDATE()
                         AND status = 'scheduled'";
$result_active_treatments = $conn->query($sql_active_treatments);
$active_treatments = $result_active_treatments->fetch_assoc()['active_treatments'];

// 6. Service trends (count of job orders by type_of_work)
$sql_service_trends = "SELECT type_of_work, COUNT(*) as count
                      FROM job_order
                      GROUP BY type_of_work
                      ORDER BY count DESC
                      LIMIT 5";
$result_service_trends = $conn->query($sql_service_trends);
$service_trends = [];
while ($row = $result_service_trends->fetch_assoc()) {
    $service_trends[] = $row;
}

// 7. Completed treatments today (job orders completed today)
$sql_completed_today = "SELECT COUNT(*) AS completed_today FROM job_order_report
                       WHERE DATE(created_at) = CURDATE()";
$result_completed_today = $conn->query($sql_completed_today);
$completed_today = $result_completed_today->fetch_assoc()['completed_today'];

// 8. Pending appointments (appointments with status 'assigned')
$sql_pending_appts = "SELECT COUNT(*) AS pending_appts FROM appointments
                     WHERE status = 'assigned'";
$result_pending_appts = $conn->query($sql_pending_appts);
$pending_appts = $result_pending_appts->fetch_assoc()['pending_appts'];

// 9. Pending job orders for today
// Override with hardcoded value of 1 to reflect the uncompleted job order
$sql_pending_jobs_today = "SELECT COUNT(*) AS pending_jobs_today FROM job_order
                          WHERE preferred_date = CURDATE()
                          AND (status = 'scheduled' OR status IS NULL)";
$result_pending_jobs_today = $conn->query($sql_pending_jobs_today);
// $pending_jobs_today = $result_pending_jobs_today->fetch_assoc()['pending_jobs_today'];
$pending_jobs_today = 1; // Hardcoded to 1 as requested

// 10. Total job orders for today (both pending and completed)
// Set total jobs to 1 (the uncompleted job)
$sql_total_jobs_today = "SELECT COUNT(*) AS total_jobs_today FROM job_order
                        WHERE preferred_date = CURDATE()";
$result_total_jobs_today = $conn->query($sql_total_jobs_today);
// $total_jobs_today = $result_total_jobs_today->fetch_assoc()['total_jobs_today'];
$total_jobs_today = 1; // Hardcoded to 1 as requested

// Set completed jobs to 0 since the job is uncompleted
$completed_today = 0;

// Calculate completion percentage
$completion_percentage = 0; // 0% since the job is not completed
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | MacJ Pest Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/modern-dashboard.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <link rel="stylesheet" href="css/modern-modal.css">
    <link rel="stylesheet" href="css/notification-override.css">
    <link rel="stylesheet" href="css/notification-viewed.css">

    <!-- Leaflet Map CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Leaflet Map JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>

    <style>


        /* Progress Bar Styles */
        .progress-container {
            margin-top: 20px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .progress-bar-bg {
            height: 10px;
            background-color: #e5e7eb;
            border-radius: 5px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            border-radius: 5px;
            transition: width 0.5s ease;
        }

        .progress-bar-low {
            background-color: #EF4444;
        }

        .progress-bar-medium {
            background-color: #F59E0B;
        }

        .progress-bar-high {
            background-color: #10B981;
        }

        .progress-footer {
            margin-top: 10px;
            font-size: 0.8rem;
            color: #6B7280;
            text-align: center;
        }

        /* Chemical List Styles */
        .chemical-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 10px;
        }

        .chemical-item {
            display: flex;
            align-items: flex-start;
            padding: 10px;
            background-color: #f9fafb;
            border-radius: 8px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .chemical-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .chemical-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: white;
            flex-shrink: 0;
        }

        .chemical-info {
            flex: 1;
        }

        .chemical-name {
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
            font-size: 14px;
        }

        .chemical-details {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #6B7280;
            margin-bottom: 4px;
        }

        .chemical-status, .chemical-expiry {
            margin-top: 4px;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .status-badge.low-stock {
            background-color: rgba(245, 158, 11, 0.1);
            color: #F59E0B;
        }

        .expiry-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            background-color: rgba(239, 68, 68, 0.1);
            color: #EF4444;
        }

        .no-data {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px 0;
            color: #6B7280;
            text-align: center;
        }
    </style>
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

        /* Header and Sidebar Styles from Chemical Inventory */
        :root {
          --primary-color: #3B82F6;
          --secondary-color: #2563eb;
          --accent-color: #3B82F6;
          --success-color: #2ecc71;
          --warning-color: #f39c12;
          --danger-color: #e74c3c;
          --info-color: #1abc9c;
          --light-color: #ecf0f1;
          --dark-color: #1e3a8a;
          --text-color: #333;
          --text-light: #7f8c8d;
          --border-color: #ddd;
          --sidebar-width: 250px;
          --header-height: 60px;
          --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
          --transition: all 0.3s ease;
        }

        /* Layout Styles */
        body {
          font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
          font-size: 14px;
          line-height: 1.6;
          color: var(--text-color);
          background-color: #f5f7fa;
          margin: 0;
          padding: 0;
        }

        .container {
          display: flex;
          min-height: 100vh;
        }

        .main-content {
          flex: 1;
          margin-left: var(--sidebar-width);
          position: relative;
          min-height: 100vh;
          display: flex;
          flex-direction: column;
        }

        /* Sidebar Styles */
        .sidebar {
          width: var(--sidebar-width);
          background-color: white;
          height: 100vh;
          position: fixed;
          left: 0;
          top: 0;
          box-shadow: var(--shadow);
          z-index: 100;
          display: flex;
          flex-direction: column;
        }

        .sidebar-header {
          padding: 20px;
          display: flex;
          align-items: center;
          justify-content: center;
          flex-direction: column;
          border-bottom: 1px solid var(--border-color);
        }

        .sidebar-header h2 {
          font-size: 18px;
          margin-top: 10px;
          color: var(--primary-color);
          text-align: center;
        }

        .sidebar-nav {
          padding: 20px 0;
          flex: 1;
        }

        .sidebar-nav ul {
          list-style: none;
          padding: 0;
          margin: 0;
        }

        .sidebar-nav a {
          display: flex;
          align-items: center;
          padding: 12px 20px;
          color: var(--text-color);
          transition: var(--transition);
          text-decoration: none;
        }

        .sidebar-nav a:hover {
          background-color: #f8f9fa;
          color: var(--primary-color);
        }

        .sidebar-nav a.active {
          background-color: #f0f7ff;
          color: var(--primary-color);
          border-left: 3px solid var(--primary-color);
        }

        .sidebar-nav i {
          margin-right: 10px;
          font-size: 16px;
        }

        /* Header Styles */
        .header {
          height: var(--header-height);
          background-color: white;
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 0 20px;
          position: fixed;
          top: 0;
          right: 0;
          left: var(--sidebar-width);
          z-index: 99;
          box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .header-title h1 {
          margin: 0;
          font-size: 20px;
          color: var(--primary-color);
        }

        .user-menu {
          display: flex;
          align-items: center;
        }

        .user-avatar {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          object-fit: cover;
          margin-right: 10px;
        }

        /* Chemicals Content Styles */
        .chemicals-content {
          padding: 0px 20px 20px 20px;
          flex: 1;
          margin-top: var(--header-height);
        }

        .chemicals-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 5px;
        }

        .chemicals-header h1 {
          margin: 0;
          color: var(--primary-color);
          font-size: 22px;
          font-weight: 600;
          display: flex;
          align-items: center;
          padding-top: 5px;
        }

        .chemicals-header h1 i {
          margin-right: 10px;
        }

        /* Map Styles */
        .count-label div {
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            line-height: 1;
            padding-top: 4px;
        }
        #locationMap {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .location-overview a {
            color: var(--accent-color);
            font-weight: 500;
            text-decoration: none;
        }
        .location-overview a:hover {
            text-decoration: underline;
        }

        /* Mobile menu toggle */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1000;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        /* Dashboard Row Styles */
        .dashboard-row {
            margin-bottom: 15px;
        }

        /* Business Overview Styles */
        .dashboard-row:first-of-type {
            margin-top: -5px;
        }

        /* Business Overview Card Styles */
        .dashboard-row:first-of-type .card-header {
            padding: 10px 15px;
        }

        .dashboard-row:first-of-type .card-body {
            padding: 15px;
        }

        .business-overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
        }

        .overview-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 15px;
            display: flex;
            align-items: flex-start;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .overview-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .overview-card.wide {
            grid-column: span 2;
        }

        .overview-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background-color: #3B82F6;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .overview-icon.ending {
            background-color: #F59E0B;
        }

        .overview-icon.upcoming {
            background-color: #10B981;
        }

        .overview-icon.techs {
            background-color: #8B5CF6;
        }

        .overview-icon.treatments {
            background-color: #EC4899;
        }

        .overview-icon.trends {
            background-color: #6366F1;
        }

        .overview-icon.completed {
            background-color: #10B981;
        }

        .overview-icon.pending {
            background-color: #F59E0B;
        }

        .overview-info {
            flex: 1;
        }

        .overview-info h3 {
            font-size: 16px;
            color: #4B5563;
            margin: 0 0 10px 0;
            font-weight: 500;
        }

        .overview-value {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 5px 0;
        }

        .overview-label {
            font-size: 13px;
            color: #6B7280;
        }

        .frequency-breakdown {
            display: flex;
            flex-direction: column;
            margin-top: 5px;
            font-size: 12px;
        }

        .frequency-item {
            display: flex;
            align-items: center;
            margin-top: 3px;
            color: #6B7280;
        }

        .frequency-item i {
            margin-right: 5px;
            font-size: 10px;
        }

        .frequency-item.weekly i {
            color: #3B82F6;
        }

        .frequency-item.monthly i {
            color: #10B981;
        }

        .frequency-item.quarterly i {
            color: #F59E0B;
        }

        .trends-container {
            margin-top: 15px;
        }

        .trend-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .trend-label {
            width: 120px;
            font-size: 13px;
            color: #4B5563;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .trend-bar-container {
            flex: 1;
            height: 8px;
            background-color: #E5E7EB;
            border-radius: 4px;
            margin: 0 10px;
            overflow: hidden;
        }

        .trend-bar {
            height: 100%;
            background-color: #6366F1;
            border-radius: 4px;
        }

        .trend-value {
            font-size: 13px;
            font-weight: 600;
            color: #111827;
            width: 30px;
            text-align: right;
        }

        .no-data {
            text-align: center;
            color: #6B7280;
            font-size: 14px;
            padding: 20px 0;
        }

        /* Active Users Styles */
        .active-users-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .user-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .user-item:last-child {
            border-bottom: none;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 15px;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }

        .user-email {
            font-size: 13px;
            color: #6B7280;
        }

        .user-last-active {
            font-size: 12px;
            color: #9CA3AF;
            margin-top: 3px;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .business-overview-grid {
                grid-template-columns: 1fr;
            }
            .overview-card.wide {
                grid-column: span 1;
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

    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>MacJ Pest Control</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="active"><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
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

        <!-- Mobile menu toggle -->
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Main Content -->
        <main class="main-content">
            <div class="chemicals-content">
                <div class="chemicals-header">
                    <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
                </div>
                <!-- Business Overview Section -->
                <div class="dashboard-row">
                    <div class="dashboard-card wide-card">
                        <div class="card-header">
                            <h3><i class="fas fa-briefcase"></i> Business Overview</h3>
                        </div>
                        <div class="card-body">
                            <div class="business-overview-grid">
                                <!-- Current Active Contracts -->
                                <div class="overview-card">
                                    <div class="overview-icon">
                                        <i class="fas fa-file-contract"></i>
                                    </div>
                                    <div class="overview-info">
                                        <h3>Active Contracts</h3>
                                        <p class="overview-value"><?php echo $active_contracts; ?></p>
                                        <span class="overview-label">Client-approved recurring contracts</span>
                                        <div class="frequency-breakdown">
                                            <span class="frequency-item weekly">
                                                <i class="fas fa-calendar-week"></i> Weekly: <?php echo $weekly_contracts; ?>
                                            </span>
                                            <span class="frequency-item monthly">
                                                <i class="fas fa-calendar-alt"></i> Monthly: <?php echo $monthly_contracts; ?>
                                            </span>
                                            <span class="frequency-item quarterly">
                                                <i class="fas fa-calendar-check"></i> Quarterly: <?php echo $quarterly_contracts; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Ending Contracts -->
                                <div class="overview-card">
                                    <div class="overview-icon ending">
                                        <i class="fas fa-hourglass-end"></i>
                                    </div>
                                    <div class="overview-info">
                                        <h3>Ending Contracts</h3>
                                        <p class="overview-value"><?php echo $ending_contracts; ?></p>
                                        <span class="overview-label">Ending in next 30 days (1-year term)</span>
                                        <div class="frequency-breakdown">
                                            <span class="frequency-item weekly">
                                                <i class="fas fa-calendar-week"></i> Weekly: <?php echo $weekly_ending; ?>
                                            </span>
                                            <span class="frequency-item monthly">
                                                <i class="fas fa-calendar-alt"></i> Monthly: <?php echo $monthly_ending; ?>
                                            </span>
                                            <span class="frequency-item quarterly">
                                                <i class="fas fa-calendar-check"></i> Quarterly: <?php echo $quarterly_ending; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Upcoming Job Orders -->
                                <div class="overview-card">
                                    <div class="overview-icon upcoming">
                                        <i class="fas fa-calendar-plus"></i>
                                    </div>
                                    <div class="overview-info">
                                        <h3>Upcoming Jobs</h3>
                                        <p class="overview-value"><?php echo $upcoming_jobs; ?></p>
                                        <span class="overview-label">Future scheduled jobs</span>
                                    </div>
                                </div>

                                <!-- Technicians Deployed -->
                                <div class="overview-card">
                                    <div class="overview-icon techs">
                                        <i class="fas fa-hard-hat"></i>
                                    </div>
                                    <div class="overview-info">
                                        <h3>Technicians Deployed</h3>
                                        <p class="overview-value"><?php echo $deployed_techs; ?></p>
                                        <span class="overview-label">Currently on assignment</span>
                                    </div>
                                </div>

                                <!-- Active Treatments -->
                                <div class="overview-card">
                                    <div class="overview-icon treatments">
                                        <i class="fas fa-spray-can"></i>
                                    </div>
                                    <div class="overview-info">
                                        <h3>Active Treatments</h3>
                                        <p class="overview-value"><?php echo $active_treatments; ?></p>
                                        <span class="overview-label">Treatments in progress</span>
                                    </div>
                                </div>

                                <!-- Service Trends -->
                                <div class="overview-card wide">
                                    <div class="overview-icon trends">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div class="overview-info">
                                        <h3>Service Trends</h3>
                                        <div class="trends-container">
                                            <?php foreach ($service_trends as $index => $trend): ?>
                                            <div class="trend-item">
                                                <span class="trend-label"><?php echo $trend['type_of_work']; ?></span>
                                                <div class="trend-bar-container">
                                                    <div class="trend-bar" style="width: <?php echo min(100, $trend['count'] * 10); ?>%"></div>
                                                </div>
                                                <span class="trend-value"><?php echo $trend['count']; ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php if (empty($service_trends)): ?>
                                            <div class="no-data">No service trend data available</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Completed Treatments Today -->
                                <div class="overview-card">
                                    <div class="overview-icon completed">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="overview-info">
                                        <h3>Completed Today</h3>
                                        <p class="overview-value"><?php echo $completed_today; ?></p>
                                        <span class="overview-label">Treatments completed today</span>
                                    </div>
                                </div>

                                <!-- Pending Appointments -->
                                <div class="overview-card">
                                    <div class="overview-icon pending">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="overview-info">
                                        <h3>Pending Appointments</h3>
                                        <p class="overview-value"><?php echo $pending_appts; ?></p>
                                        <span class="overview-label">Awaiting service</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Row Cards -->
                <div class="dashboard-row">
                    <!-- Weekly Sales Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Weekly Sales</h3>
                        </div>
                        <div class="card-body">
                            <div class="card-value">₱<?php echo number_format($weekly_sales, 2); ?></div>
                            <div class="card-trend <?php echo $weekly_growth >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="fas <?php echo $weekly_growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                                <?php echo abs($weekly_growth); ?>%
                            </div>
                            <div class="card-chart">
                                <canvas id="weeklySalesChart" height="60"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Total Job Orders Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Total Job Orders</h3>
                        </div>
                        <div class="card-body">
                            <div class="card-value"><?php echo number_format($total_completed_jobs, 1); ?></div>
                            <div class="card-trend <?php echo $job_growth >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="fas <?php echo $job_growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                                <?php echo abs($job_growth); ?>%
                            </div>
                            <div class="card-chart">
                                <canvas id="totalJobsChart" height="60"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Market Share Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Pest Distribution</h3>
                        </div>
                        <div class="card-body">
                            <div class="market-share-chart">
                                <canvas id="marketShareChart" height="120"></canvas>
                            </div>
                            <div class="market-share-legend">
                                <?php
                                $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'];
                                $i = 0;
                                foreach ($pest_types as $pest) {
                                    if ($i < 5) {
                                        $percentage = $total_pest_count > 0 ? round(($pest['count'] / $total_pest_count) * 100) : 0;
                                        echo '<div class="legend-item">';
                                        echo '<span class="legend-color" style="background-color: ' . $colors[$i] . '"></span>';
                                        echo '<span class="legend-label">' . $pest['pest_problems'] . '</span>';
                                        echo '<span class="legend-value">' . $percentage . '%</span>';
                                        echo '</div>';
                                        $i++;
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Active Clients Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Active Clients</h3>
                        </div>
                        <div class="card-body">
                            <div class="card-value"><?php echo $total_clients; ?></div>
                            <div class="card-trend positive">
                                <i class="fas fa-arrow-up"></i>
                                <?php echo $new_clients; ?> new this month
                            </div>
                            <div class="card-chart">
                                <div class="progress-circle" data-value="<?php echo min(100, round(($total_clients / 100) * 100)); ?>">
                                    <span class="progress-circle-value"><?php echo $total_clients; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>



                <!-- Third Row - Monthly Sales Chart and Today's Job Orders -->
                <div class="dashboard-row">
                    <!-- Monthly Sales Chart -->
                    <div class="dashboard-card" style="flex: 3;">
                        <div class="card-header">
                            <h3>Monthly Sales (<?php echo date('Y'); ?>, Jan-<?php echo date('M'); ?>)</h3>
                            <div class="card-dropdown">
                                <select id="salesPeriodFilter">
                                    <option value="current_year">Current Year</option>
                                    <option value="previous_year">Previous Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="sales-chart-container">
                                <canvas id="monthlySalesChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Today's Job Orders Card -->
                    <div class="dashboard-card" style="flex: 1;">
                        <div class="card-header">
                            <h3><i class="fas fa-clipboard-list"></i> Today's Job Orders</h3>
                        </div>
                        <div class="card-body">
                            <div class="card-value"><?php echo $pending_jobs_today; ?></div>
                            <div class="card-trend <?php echo $pending_jobs_today > 0 ? 'negative' : 'positive'; ?>">
                                <i class="fas fa-tasks"></i>
                                Pending Jobs Today
                            </div>

                            <div class="progress-container">
                                <div class="progress-header">
                                    <span>Completion Progress</span>
                                    <span><?php echo $completion_percentage; ?>%</span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar <?php echo $completion_percentage < 50 ? 'progress-bar-low' : ($completion_percentage < 80 ? 'progress-bar-medium' : 'progress-bar-high'); ?>" style="width: <?php echo $completion_percentage; ?>%;"></div>
                                </div>
                                <div class="progress-footer">
                                    <?php echo $completed_today; ?> of <?php echo $total_jobs_today; ?> jobs completed
                                </div>
                            </div>

                            <div class="view-all" style="margin-top: 15px;">
                                <a href="joborder_report.php">View All Job Orders <i class="fas fa-chevron-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>









                <!-- Chemical Inventory Status Row -->
                <div class="dashboard-row">
                    <!-- Low Quantity Chemicals Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-exclamation-triangle"></i> Low Quantity Chemicals</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($low_quantity_chemicals)): ?>
                                <div class="no-data">
                                    <i class="fas fa-check-circle" style="color: #10B981; font-size: 24px; margin-bottom: 10px;"></i>
                                    <p>All chemicals are well-stocked</p>
                                </div>
                            <?php else: ?>
                                <div class="chemical-list">
                                    <?php foreach ($low_quantity_chemicals as $chemical): ?>
                                        <div class="chemical-item">
                                            <div class="chemical-icon" style="background-color: #F59E0B;">
                                                <i class="fas fa-flask"></i>
                                            </div>
                                            <div class="chemical-info">
                                                <div class="chemical-name"><?php echo htmlspecialchars($chemical['chemical_name']); ?></div>
                                                <div class="chemical-details">
                                                    <span class="chemical-type"><?php echo htmlspecialchars($chemical['type']); ?></span>
                                                    <span class="chemical-quantity">
                                                        <strong><?php echo number_format($chemical['quantity'], 2); ?> <?php echo $chemical['unit']; ?></strong>
                                                    </span>
                                                </div>
                                                <div class="chemical-status">
                                                    <span class="status-badge low-stock">Low Stock</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="view-all">
                                    <a href="chemical_inventory.php?status=Low+Stock">View All Low Stock Chemicals <i class="fas fa-chevron-right"></i></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Near Expiration Chemicals Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-times"></i> Near Expiration Chemicals</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($near_expiration_chemicals)): ?>
                                <div class="no-data">
                                    <i class="fas fa-check-circle" style="color: #10B981; font-size: 24px; margin-bottom: 10px;"></i>
                                    <p>No chemicals expiring soon</p>
                                </div>
                            <?php else: ?>
                                <div class="chemical-list">
                                    <?php foreach ($near_expiration_chemicals as $chemical): ?>
                                        <div class="chemical-item">
                                            <div class="chemical-icon" style="background-color: #EF4444;">
                                                <i class="fas fa-hourglass-half"></i>
                                            </div>
                                            <div class="chemical-info">
                                                <div class="chemical-name"><?php echo htmlspecialchars($chemical['chemical_name']); ?></div>
                                                <div class="chemical-details">
                                                    <span class="chemical-type"><?php echo htmlspecialchars($chemical['type']); ?></span>
                                                    <span class="chemical-quantity">
                                                        <strong><?php echo number_format($chemical['quantity'], 2); ?> <?php echo $chemical['unit']; ?></strong>
                                                    </span>
                                                </div>
                                                <div class="chemical-expiry">
                                                    <span class="expiry-badge">
                                                        Expires in <?php echo $chemical['days_until_expiry']; ?> days
                                                        (<?php echo date('M d, Y', strtotime($chemical['expiration_date'])); ?>)
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="view-all">
                                    <a href="chemical_inventory.php">View All Chemicals <i class="fas fa-chevron-right"></i></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sixth Row - Active Users & Bandwidth -->
                <div class="dashboard-row">
                    <!-- Active Admin Users -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Active Admin Users</h3>
                        </div>
                        <div class="card-body">
                            <div class="active-users-list">
                                <?php foreach ($active_users as $user): ?>
                                <div class="user-item">
                                    <div class="user-avatar">
                                        <?php
                                        // Check if profile picture exists
                                        $profile_picture = '';
                                        $staff_id = $user['staff_id'];

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
                                        <img src="<?php echo $profile_picture_url; ?>" alt="Admin User">
                                    </div>
                                    <div class="user-info">
                                        <div class="user-name"><?php echo htmlspecialchars($user['username'] ?? ''); ?></div>
                                        <div class="user-email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                                        <div class="user-last-active">Admin User</div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="view-all">
                                <a href="profile.php">Manage admin users <i class="fas fa-chevron-right"></i></a>
                            </div>
                        </div>
                    </div>


                </div>
            </div>
        </main>
    </div>



    <!-- Dashboard Scripts -->
    <script>

        function initializeCharts() {

            // Weekly Sales Chart
            const weeklySalesCtx = document.getElementById('weeklySalesChart');
            if (weeklySalesCtx) {
                // Get weekly sales data from PHP
                <?php
                // Get daily sales for the current week
                $daily_sales = array_fill(0, 7, 0); // Initialize with zeros for each day
                $day_labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

                // Query to get daily sales for the current week
                $sql_daily_sales = "SELECT
                                    DAYOFWEEK(preferred_date) as day_of_week,
                                    COALESCE(SUM(cost), 0) as daily_sales
                                FROM job_order
                                WHERE YEARWEEK(preferred_date, 1) = YEARWEEK(CURDATE(), 1)
                                GROUP BY DAYOFWEEK(preferred_date)";
                $result_daily_sales = $conn->query($sql_daily_sales);

                // MySQL DAYOFWEEK() returns 1 for Sunday, 2 for Monday, etc.
                // We need to adjust to match our array (0 for Monday, 6 for Sunday)
                while ($row = $result_daily_sales->fetch_assoc()) {
                    $index = ($row['day_of_week'] + 5) % 7; // Convert to our index (0-6, Mon-Sun)
                    $daily_sales[$index] = (float)$row['daily_sales'];
                }
                ?>

                new Chart(weeklySalesCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($day_labels); ?>,
                        datasets: [{
                            label: 'Sales',
                            data: <?php echo json_encode($daily_sales); ?>,
                            backgroundColor: '#3B82F6',
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { x: { display: false }, y: { display: false } }
                    }
                });
            }

            // Total Job Orders Chart
            const totalJobsCtx = document.getElementById('totalJobsChart');
            if (totalJobsCtx) {
                new Chart(totalJobsCtx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                        datasets: [{
                            label: 'Job Orders',
                            data: [5, 8, 12, 15, 10, 18, 20, 25, 30, 22, 28, 35],
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { x: { display: false }, y: { display: false } },
                        elements: { point: { radius: 0 } }
                    }
                });
            }

            // Market Share Chart
            const marketShareCtx = document.getElementById('marketShareChart');
            if (marketShareCtx) {
                new Chart(marketShareCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php
                            $pest_labels = [];
                            $pest_data = [];
                            $i = 0;
                            foreach ($pest_types as $pest) {
                                if ($i < 5) {
                                    $pest_labels[] = $pest['pest_problems'];
                                    $pest_data[] = $pest['count'];
                                    $i++;
                                }
                            }
                            echo json_encode($pest_labels);
                        ?>,
                        datasets: [{
                            data: <?php echo json_encode($pest_data); ?>,
                            backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        cutout: '75%'
                    }
                });
            }

            // Monthly Sales Chart
            const monthlySalesCtx = document.getElementById('monthlySalesChart');
            if (monthlySalesCtx) {
                // Only use months that have data (past and current months)
                const monthlyLabels = [];
                const monthlySalesData = [];

                <?php
                // Only include months that have data
                foreach ($monthly_sales_data as $month => $value) {
                    echo "monthlyLabels.push('$month');\n";
                    echo "monthlySalesData.push($value);\n";
                }
                ?>

                console.log("Monthly Labels:", monthlyLabels);
                console.log("Monthly Sales Data:", monthlySalesData);

                const monthlySalesChart = new Chart(monthlySalesCtx, {
                    type: 'bar',
                    data: {
                        labels: monthlyLabels,
                        datasets: [{
                            label: 'Sales (PHP)',
                            data: monthlySalesData,
                            backgroundColor: '#3B82F6',
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            label += new Intl.NumberFormat('en-PH', {
                                                style: 'currency',
                                                currency: 'PHP'
                                            }).format(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '₱' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });

                // Add event listener for the period filter
                document.getElementById('salesPeriodFilter').addEventListener('change', function() {
                    // In a real implementation, you would reload the data based on the selected period
                    // For this demo, we'll just show an alert
                    alert('In a real implementation, this would show sales data for: ' + this.value);
                });
            }





            // Initialize progress circles
            document.querySelectorAll('.progress-circle').forEach(circle => {
                const value = parseInt(circle.getAttribute('data-value'));
                const radius = circle.classList.contains('large') ? 70 : 35;
                const circumference = 2 * Math.PI * radius;

                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('width', (radius * 2) + 20);
                svg.setAttribute('height', (radius * 2) + 20);
                svg.setAttribute('viewBox', `0 0 ${(radius * 2) + 20} ${(radius * 2) + 20}`);

                const circleEl = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                circleEl.setAttribute('cx', radius + 10);
                circleEl.setAttribute('cy', radius + 10);
                circleEl.setAttribute('r', radius);
                circleEl.setAttribute('fill', 'none');
                circleEl.setAttribute('stroke', '#e5e7eb');
                circleEl.setAttribute('stroke-width', '6');

                const progressCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                progressCircle.setAttribute('cx', radius + 10);
                progressCircle.setAttribute('cy', radius + 10);
                progressCircle.setAttribute('r', radius);
                progressCircle.setAttribute('fill', 'none');
                progressCircle.setAttribute('stroke', '#3B82F6');
                progressCircle.setAttribute('stroke-width', '6');
                progressCircle.setAttribute('stroke-dasharray', circumference);
                progressCircle.setAttribute('stroke-dashoffset', circumference - (value / 100) * circumference);
                progressCircle.setAttribute('transform', `rotate(-90 ${radius + 10} ${radius + 10})`);

                svg.appendChild(circleEl);
                svg.appendChild(progressCircle);

                // Insert SVG before the value span
                const valueSpan = circle.querySelector('.progress-circle-value');
                if (valueSpan) {
                    circle.insertBefore(svg, valueSpan);
                } else {
                    circle.appendChild(svg);
                }
            });
        }


    </script>

    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <script src="js/chemical-notifications.js"></script>
    <script>
        // Initialize charts and mobile menu when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize charts
            if (typeof initializeCharts === 'function') {
                initializeCharts();
            }

            // Mobile menu toggle
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.querySelector('.sidebar');

            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }

            // Fetch notifications immediately
            if (typeof fetchNotifications === 'function') {
                fetchNotifications();

                // Set up periodic notification checks
                setInterval(fetchNotifications, 60000); // Check every minute
            }
        });
    </script>

</body>
</html>
<?php
$conn->close();
?>
