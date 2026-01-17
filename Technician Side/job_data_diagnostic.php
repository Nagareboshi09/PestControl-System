<?php
/**
 * Job Data Diagnostic Tool
 *
 * This script provides a detailed diagnostic view of all data related to a job order
 * to help identify issues with data retrieval and display
 */

session_start();
// For diagnostic purposes, we'll allow access even if not logged in
// Comment this out when done debugging
/*
if ($_SESSION['role'] !== 'technician') {
    header("Location: SignIn.php");
    exit;
}
*/

require_once '../db_connect.php';

// Get job order ID from request
$job_order_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;

// If no job ID provided, show a form to enter one
if ($job_order_id <= 0) {
    $show_form = true;
} else {
    $show_form = false;

    // Initialize arrays to store data
    $job_data = [];
    $assessment_data = [];
    $appointment_data = [];
    $client_data = [];
    $report_data = [];
    $technician_data = [];

    // Get job order data
    $job_query = "SELECT * FROM job_order WHERE job_order_id = ?";
    $job_stmt = $conn->prepare($job_query);
    $job_stmt->bind_param("i", $job_order_id);
    $job_stmt->execute();
    $job_result = $job_stmt->get_result();

    if ($job_result->num_rows > 0) {
        $job_data = $job_result->fetch_assoc();
        $report_id = $job_data['report_id'];

        // Get assessment report data
        if ($report_id) {
            $assessment_query = "SELECT * FROM assessment_report WHERE report_id = ?";
            $assessment_stmt = $conn->prepare($assessment_query);
            $assessment_stmt->bind_param("i", $report_id);
            $assessment_stmt->execute();
            $assessment_result = $assessment_stmt->get_result();

            if ($assessment_result->num_rows > 0) {
                $assessment_data = $assessment_result->fetch_assoc();
                $appointment_id = $assessment_data['appointment_id'];

                // Get appointment data
                if ($appointment_id) {
                    $appointment_query = "SELECT * FROM appointments WHERE appointment_id = ?";
                    $appointment_stmt = $conn->prepare($appointment_query);
                    $appointment_stmt->bind_param("i", $appointment_id);
                    $appointment_stmt->execute();
                    $appointment_result = $appointment_stmt->get_result();

                    if ($appointment_result->num_rows > 0) {
                        $appointment_data = $appointment_result->fetch_assoc();
                        $client_id = $appointment_data['client_id'];

                        // Get client data
                        if ($client_id) {
                            $client_query = "SELECT * FROM clients WHERE client_id = ?";
                            $client_stmt = $conn->prepare($client_query);
                            $client_stmt->bind_param("i", $client_id);
                            $client_stmt->execute();
                            $client_result = $client_stmt->get_result();

                            if ($client_result->num_rows > 0) {
                                $client_data = $client_result->fetch_assoc();
                            }
                        }
                    }
                }
            }
        }

        // Get job order report data
        $report_query = "SELECT * FROM job_order_report WHERE job_order_id = ?";
        $report_stmt = $conn->prepare($report_query);
        $report_stmt->bind_param("i", $job_order_id);
        $report_stmt->execute();
        $report_result = $report_stmt->get_result();

        if ($report_result->num_rows > 0) {
            $report_data = $report_result->fetch_assoc();
        }

        // Get technician assignment data
        $tech_query = "SELECT * FROM job_order_technicians WHERE job_order_id = ?";
        $tech_stmt = $conn->prepare($tech_query);
        $tech_stmt->bind_param("i", $job_order_id);
        $tech_stmt->execute();
        $tech_result = $tech_stmt->get_result();

        if ($tech_result->num_rows > 0) {
            while ($tech_row = $tech_result->fetch_assoc()) {
                $technician_data[] = $tech_row;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Data Diagnostic Tool</title>
    <link href="https://unpkg.com/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .data-table {
            font-size: 0.9rem;
        }
        .data-table th {
            width: 30%;
        }
        .data-section {
            margin-bottom: 2rem;
        }
        .key-field {
            font-weight: bold;
            color: #0d6efd;
        }
        .empty-value {
            color: #dc3545;
            font-style: italic;
        }
        .json-value {
            font-family: monospace;
            white-space: pre-wrap;
            background-color: #f8f9fa;
            padding: 0.5rem;
            border-radius: 0.25rem;
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4">Job Data Diagnostic Tool</h1>

        <?php if ($show_form): ?>
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Enter Job Order ID</h5>
                <form method="get" action="">
                    <div class="mb-3">
                        <label for="job_id" class="form-label">Job Order ID</label>
                        <input type="number" class="form-control" id="job_id" name="job_id" required>
                    </div>
                    <button type="submit" class="btn btn-primary">View Job Data</button>
                </form>
            </div>
        </div>
        <?php else: ?>
            <?php if (empty($job_data)): ?>
                <div class="alert alert-danger">
                    <h4 class="alert-heading">Job Order Not Found</h4>
                    <p>No job order found with ID: <?= htmlspecialchars($job_order_id) ?></p>
                    <hr>
                    <p class="mb-0"><a href="job_data_diagnostic.php" class="alert-link">Try another job order ID</a></p>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-4">
                    <h4 class="alert-heading">Job Order #<?= htmlspecialchars($job_order_id) ?></h4>
                    <p>This page shows all data related to this job order from various tables in the database.</p>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <!-- Job Order Data -->
                        <div class="data-section">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">Job Order Data</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-bordered data-table">
                                        <tbody>
                                            <?php foreach ($job_data as $key => $value): ?>
                                                <tr>
                                                    <th scope="row" class="<?= in_array($key, ['job_order_id', 'report_id']) ? 'key-field' : '' ?>">
                                                        <?= htmlspecialchars($key) ?>
                                                    </th>
                                                    <td>
                                                        <?php if ($value === null || $value === ''): ?>
                                                            <span class="empty-value">Empty</span>
                                                        <?php elseif (in_array($key, ['chemical_recommendations']) && strlen($value) > 50): ?>
                                                            <div class="json-value"><?= htmlspecialchars($value) ?></div>
                                                        <?php else: ?>
                                                            <?= htmlspecialchars($value) ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Assessment Report Data -->
                        <div class="data-section">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">Assessment Report Data</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($assessment_data)): ?>
                                        <div class="alert alert-warning">No assessment report data found</div>
                                    <?php else: ?>
                                        <table class="table table-bordered data-table">
                                            <tbody>
                                                <?php foreach ($assessment_data as $key => $value): ?>
                                                    <tr>
                                                        <th scope="row" class="<?= in_array($key, ['report_id', 'appointment_id', 'area', 'pest_types', 'problem_area']) ? 'key-field' : '' ?>">
                                                            <?= htmlspecialchars($key) ?>
                                                        </th>
                                                        <td>
                                                            <?php if ($value === null || $value === ''): ?>
                                                                <span class="empty-value">Empty</span>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($value) ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <!-- Appointment Data -->
                        <div class="data-section">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">Appointment Data</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($appointment_data)): ?>
                                        <div class="alert alert-warning">No appointment data found</div>
                                    <?php else: ?>
                                        <table class="table table-bordered data-table">
                                            <tbody>
                                                <?php foreach ($appointment_data as $key => $value): ?>
                                                    <tr>
                                                        <th scope="row" class="<?= in_array($key, ['appointment_id', 'client_id', 'client_name', 'pest_problems']) ? 'key-field' : '' ?>">
                                                            <?= htmlspecialchars($key) ?>
                                                        </th>
                                                        <td>
                                                            <?php if ($value === null || $value === ''): ?>
                                                                <span class="empty-value">Empty</span>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($value) ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Client Data -->
                        <div class="data-section">
                            <div class="card">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0">Client Data</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($client_data)): ?>
                                        <div class="alert alert-warning">No client data found</div>
                                    <?php else: ?>
                                        <table class="table table-bordered data-table">
                                            <tbody>
                                                <?php foreach ($client_data as $key => $value): ?>
                                                    <tr>
                                                        <th scope="row" class="<?= in_array($key, ['client_id', 'first_name', 'last_name', 'contact_number']) ? 'key-field' : '' ?>">
                                                            <?= htmlspecialchars($key) ?>
                                                        </th>
                                                        <td>
                                                            <?php if ($value === null || $value === ''): ?>
                                                                <span class="empty-value">Empty</span>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($value) ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <!-- Job Order Report Data -->
                        <div class="data-section">
                            <div class="card">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0">Job Order Report Data</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($report_data)): ?>
                                        <div class="alert alert-warning">No job order report data found</div>
                                    <?php else: ?>
                                        <table class="table table-bordered data-table">
                                            <tbody>
                                                <?php foreach ($report_data as $key => $value): ?>
                                                    <tr>
                                                        <th scope="row">
                                                            <?= htmlspecialchars($key) ?>
                                                        </th>
                                                        <td>
                                                            <?php if ($value === null || $value === ''): ?>
                                                                <span class="empty-value">Empty</span>
                                                            <?php elseif (in_array($key, ['chemical_usage']) && strlen($value) > 50): ?>
                                                                <div class="json-value"><?= htmlspecialchars($value) ?></div>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($value) ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <!-- Technician Assignment Data -->
                        <div class="data-section">
                            <div class="card">
                                <div class="card-header bg-dark text-white">
                                    <h5 class="mb-0">Technician Assignment Data</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($technician_data)): ?>
                                        <div class="alert alert-warning">No technician assignment data found</div>
                                    <?php else: ?>
                                        <?php foreach ($technician_data as $index => $tech): ?>
                                            <h6>Technician #<?= $index + 1 ?></h6>
                                            <table class="table table-bordered data-table mb-4">
                                                <tbody>
                                                    <?php foreach ($tech as $key => $value): ?>
                                                        <tr>
                                                            <th scope="row" class="<?= in_array($key, ['technician_id', 'is_primary']) ? 'key-field' : '' ?>">
                                                                <?= htmlspecialchars($key) ?>
                                                            </th>
                                                            <td>
                                                                <?php if ($value === null || $value === ''): ?>
                                                                    <span class="empty-value">Empty</span>
                                                                <?php else: ?>
                                                                    <?= htmlspecialchars($value) ?>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="job_data_diagnostic.php" class="btn btn-primary">Check Another Job Order</a>
                    <a href="job_order.php" class="btn btn-secondary ms-2">Return to Job Orders</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://unpkg.com/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
