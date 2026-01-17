<?php
// Turn off all error reporting to prevent PHP errors from appearing in the Excel file
error_reporting(0);
ini_set('display_errors', 0);

session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}
require_once '../db_config.php';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="MacJ_Reports_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: max-age=0');

// Get current year and month
$current_year = date('Y');
$current_month = date('m');

// Initialize data arrays with default values
$assessment_data = [];
$job_order_data = [];
$user_data = ['client_count' => 0, 'admin_count' => 0, 'technician_count' => 0];
$contract_data = [];
$chemical_data = [];
$revenue_data = [];

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
    // Silently handle the error - we'll use the default empty array
}

// Format assessment data for Excel
$all_assessment_counts = array_fill(0, 12, 0);
foreach ($assessment_data as $data) {
    $all_assessment_counts[$data['month'] - 1] = $data['count'];
}

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

// Format job order data for Excel
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
$all_job_order_counts = array_fill(0, $days_in_month, 0);
foreach ($job_order_data as $data) {
    $all_job_order_counts[$data['day'] - 1] = $data['count'];
}

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

// Format contract data for Excel
$all_contract_counts = array_fill(0, 12, 0);
foreach ($contract_data as $data) {
    $all_contract_counts[$data['month'] - 1] = $data['count'];
}

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

// Format revenue data for Excel
$all_revenue_data = array_fill(0, 12, 0);
foreach ($revenue_data as $data) {
    $all_revenue_data[$data['month'] - 1] = $data['total'];
}

// Get month names
$all_months = [];
for ($i = 1; $i <= 12; $i++) {
    $all_months[] = date('F', mktime(0, 0, 0, $i, 1));
}

// Start Excel content
ob_start(); // Start output buffering to catch any errors
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000000;
            padding: 5px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        h1, h2 {
            font-family: Arial, sans-serif;
        }
        h1 {
            color: #3B82F6;
            font-size: 18pt;
        }
        h2 {
            color: #1F2937;
            font-size: 14pt;
            margin-top: 20px;
        }
        .date {
            font-style: italic;
            color: #6B7280;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <h1>MacJ Pest Control - Reports</h1>
    <p class="date">Generated on: <?php echo date('F j, Y, g:i a'); ?></p>

    <!-- Assessment Reports -->
    <h2>Assessment Reports in <?php echo $current_year; ?></h2>
    <table>
        <tr>
            <th>Month</th>
            <th>Number of Reports</th>
        </tr>
        <?php for ($i = 0; $i < 12; $i++): ?>
        <tr>
            <td><?php echo $all_months[$i]; ?></td>
            <td><?php echo $all_assessment_counts[$i]; ?></td>
        </tr>
        <?php endfor; ?>
    </table>

    <!-- Job Orders in Current Month -->
    <h2>Job Orders in <?php echo date('F Y'); ?></h2>
    <table>
        <tr>
            <th>Day</th>
            <th>Number of Job Orders</th>
        </tr>
        <?php for ($i = 0; $i < $days_in_month; $i++): ?>
        <tr>
            <td><?php echo ($i + 1); ?></td>
            <td><?php echo $all_job_order_counts[$i]; ?></td>
        </tr>
        <?php endfor; ?>
    </table>

    <!-- Number of Clients Registered -->
    <h2>Number of Clients Registered in <?php echo $current_year; ?></h2>
    <p>Total Registered Clients: <?php echo $user_data['client_count']; ?></p>
    <table>
        <tr>
            <th>Month</th>
            <th>New Clients</th>
        </tr>
        <?php for ($i = 0; $i < 12; $i++): ?>
        <tr>
            <td><?php echo $all_months[$i]; ?></td>
            <td><?php echo $all_client_registration_counts[$i]; ?></td>
        </tr>
        <?php endfor; ?>
    </table>

    <!-- Contracts Accepted -->
    <h2>Contracts Accepted in <?php echo $current_year; ?></h2>
    <table>
        <tr>
            <th>Month</th>
            <th>Number of Contracts</th>
        </tr>
        <?php for ($i = 0; $i < 12; $i++): ?>
        <tr>
            <td><?php echo $all_months[$i]; ?></td>
            <td><?php echo $all_contract_counts[$i]; ?></td>
        </tr>
        <?php endfor; ?>
    </table>

    <!-- Chemical Inventory -->
    <h2>Chemical Inventory Status</h2>
    <table>
        <tr>
            <th>Chemical Name</th>
            <th>Quantity</th>
            <th>Unit</th>
        </tr>
        <?php foreach ($chemical_data as $chemical): ?>
        <tr>
            <td><?php echo $chemical['chemical_name']; ?></td>
            <td><?php echo $chemical['quantity']; ?></td>
            <td><?php echo $chemical['unit']; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <!-- Monthly Revenue -->
    <h2>Monthly Revenue in <?php echo $current_year; ?></h2>
    <table>
        <tr>
            <th>Month</th>
            <th>Revenue (PHP)</th>
        </tr>
        <?php for ($i = 0; $i < 12; $i++): ?>
        <tr>
            <td><?php echo $all_months[$i]; ?></td>
            <td><?php echo number_format($all_revenue_data[$i], 2); ?></td>
        </tr>
        <?php endfor; ?>
    </table>
</body>
</html>
<?php
$content = ob_get_clean(); // Get the buffered content and clear the buffer
echo $content; // Output the content
?>
