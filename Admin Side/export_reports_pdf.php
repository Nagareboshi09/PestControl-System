<?php
// Turn off all error reporting to prevent PHP errors from appearing in the response
error_reporting(0);
ini_set('display_errors', 0);

session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}
require_once '../db_config.php';

// Check if report type is specified
if (!isset($_GET['type'])) {
    header("Location: reports.php");
    exit;
}

$report_type = $_GET['type'];
$valid_types = ['assessment', 'job_order', 'user_distribution', 'contracts', 'chemical', 'revenue', 'all'];

if (!in_array($report_type, $valid_types)) {
    header("Location: reports.php");
    exit;
}

// Get current year and month
$current_year = date('Y');
$current_month = date('m');
$current_month_name = date('F');

// Initialize data arrays with default values
$assessment_data = [];
$job_order_data = [];
$user_data = ['client_count' => 0, 'admin_count' => 0, 'technician_count' => 0];
$contract_data = [];
$chemical_data = [];
$revenue_data = [];

// Get month names
$all_months = [];
for ($i = 1; $i <= 12; $i++) {
    $all_months[] = date('F', mktime(0, 0, 0, $i, 1));
}

// Fetch data based on report type
if ($report_type == 'assessment' || $report_type == 'all') {
    try {
        // Query for assessment reports in a year
        $assessment_query = "SELECT
                                MONTH(created_at) as month,
                                COUNT(*) as count
                            FROM assessment_report
                            WHERE YEAR(created_at) = ?
                            GROUP BY MONTH(created_at)
                            ORDER BY MONTH(created_at)";
        $stmt = $pdo->prepare($assessment_query);
        $stmt->execute([$current_year]);
        $assessment_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Silently handle the error
    }

    // Format assessment data
    $all_assessment_counts = array_fill(0, 12, 0);
    foreach ($assessment_data as $data) {
        $all_assessment_counts[$data['month'] - 1] = $data['count'];
    }
}

if ($report_type == 'job_order' || $report_type == 'all') {
    try {
        // Query for job orders in the current month
        $job_order_query = "SELECT
                                DAY(preferred_date) as day,
                                COUNT(*) as count
                            FROM job_order
                            WHERE MONTH(preferred_date) = ? AND YEAR(preferred_date) = ?
                            GROUP BY DAY(preferred_date)
                            ORDER BY DAY(preferred_date)";
        $stmt = $pdo->prepare($job_order_query);
        $stmt->execute([$current_month, $current_year]);
        $job_order_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Silently handle the error
    }

    // Format job order data
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
    $all_job_order_counts = array_fill(0, $days_in_month, 0);
    foreach ($job_order_data as $data) {
        $all_job_order_counts[$data['day'] - 1] = $data['count'];
    }
}

if ($report_type == 'user_distribution' || $report_type == 'all') {
    try {
        // Query for client registrations by month
        $client_registration_query = "SELECT
                                    MONTH(registered_at) as month,
                                    COUNT(*) as count
                                FROM clients
                                WHERE YEAR(registered_at) = ?
                                GROUP BY MONTH(registered_at)
                                ORDER BY MONTH(registered_at)";
        $stmt = $pdo->prepare($client_registration_query);
        $stmt->execute([$current_year]);
        $client_registration_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format client registration data
        $all_client_registration_counts = array_fill(0, 12, 0);
        foreach ($client_registration_data as $data) {
            $all_client_registration_counts[$data['month'] - 1] = $data['count'];
        }

        // Also get total client count
        $total_clients_query = "SELECT COUNT(*) as client_count FROM clients";
        $stmt = $pdo->prepare($total_clients_query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $user_data = $result;
        }
    } catch (Exception $e) {
        // Silently handle the error
    }
}

if ($report_type == 'contracts' || $report_type == 'all') {
    try {
        // Query for contracts accepted by month
        $contract_query = "SELECT
                            MONTH(client_approval_date) as month,
                            COUNT(*) as count
                        FROM job_order
                        WHERE YEAR(client_approval_date) = ?
                        AND client_approval_status = 'approved'
                        GROUP BY MONTH(client_approval_date)
                        ORDER BY MONTH(client_approval_date)";
        $stmt = $pdo->prepare($contract_query);
        $stmt->execute([$current_year]);
        $contract_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Silently handle the error
    }

    // Format contract data
    $all_contract_counts = array_fill(0, 12, 0);
    foreach ($contract_data as $data) {
        $all_contract_counts[$data['month'] - 1] = $data['count'];
    }
}

if ($report_type == 'chemical' || $report_type == 'all') {
    try {
        // Query for chemical inventory quantities
        $chemical_query = "SELECT
                            chemical_name,
                            quantity,
                            unit
                        FROM chemical_inventory
                        WHERE quantity > 0
                        ORDER BY quantity DESC
                        LIMIT 10";
        $stmt = $pdo->prepare($chemical_query);
        $stmt->execute();
        $chemical_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Silently handle the error
    }
}

if ($report_type == 'revenue' || $report_type == 'all') {
    try {
        // Query for monthly revenue
        $revenue_query = "SELECT
                            MONTH(payment_date) as month,
                            SUM(payment_amount) as total
                        FROM payments
                        WHERE YEAR(payment_date) = ?
                        GROUP BY MONTH(payment_date)
                        ORDER BY MONTH(payment_date)";
        $stmt = $pdo->prepare($revenue_query);
        $stmt->execute([$current_year]);
        $revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Silently handle the error
    }

    // Format revenue data
    $all_revenue_data = array_fill(0, 12, 0);
    foreach ($revenue_data as $data) {
        $all_revenue_data[$data['month'] - 1] = $data['total'];
    }
}

// Return JSON data for PDF generation
header('Content-Type: application/json');
echo json_encode([
    'report_type' => $report_type,
    'current_year' => $current_year,
    'current_month' => $current_month,
    'current_month_name' => $current_month_name,
    'all_months' => $all_months,
    'assessment_data' => $all_assessment_counts ?? [],
    'job_order_data' => $all_job_order_counts ?? [],
    'user_data' => $user_data,
    'client_registration_data' => $all_client_registration_counts ?? [],
    'contract_data' => $all_contract_counts ?? [],
    'chemical_data' => $chemical_data,
    'revenue_data' => $all_revenue_data ?? [],
    'days_in_month' => $days_in_month ?? 0
]);
?>
