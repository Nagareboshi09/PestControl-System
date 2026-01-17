<?php
session_start();
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../SignIn.php");
    exit;
}
require_once '../db_connect.php';

// Set default values for filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$chemical_id = isset($_GET['chemical_id']) ? intval($_GET['chemical_id']) : 0;
$technician_id = isset($_GET['technician_id']) ? intval($_GET['technician_id']) : 0;

// Get all chemicals for the filter dropdown
try {
    $stmt = $pdo->query("SELECT id, chemical_name, type FROM chemical_inventory ORDER BY chemical_name ASC");
    $chemicals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $chemicals = [];
}

// Get all technicians for the filter dropdown
try {
    $stmt = $pdo->query("SELECT technician_id, CONCAT(first_name, ' ', last_name) AS technician_name FROM technicians ORDER BY first_name ASC");
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $technicians = [];
}

// Build the query with filters
$query = "SELECT cl.*, 
          c.chemical_name, c.type, c.unit,
          CONCAT(t.first_name, ' ', t.last_name) AS technician_name,
          jo.job_order_id, jo.type_of_work,
          a.client_name
          FROM chemical_usage_log cl
          LEFT JOIN chemical_inventory c ON cl.chemical_id = c.id
          LEFT JOIN technicians t ON cl.technician_id = t.technician_id
          LEFT JOIN job_order jo ON cl.job_order_id = jo.job_order_id
          LEFT JOIN assessment_report ar ON jo.report_id = ar.report_id
          LEFT JOIN appointments a ON ar.appointment_id = a.appointment_id
          WHERE cl.usage_date BETWEEN :start_date AND :end_date";

$params = [
    ':start_date' => $start_date,
    ':end_date' => $end_date
];

if ($chemical_id > 0) {
    $query .= " AND cl.chemical_id = :chemical_id";
    $params[':chemical_id'] = $chemical_id;
}

if ($technician_id > 0) {
    $query .= " AND cl.technician_id = :technician_id";
    $params[':technician_id'] = $technician_id;
}

$query .= " ORDER BY cl.usage_date DESC, cl.log_id DESC";

// Execute the query
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $logs = [];
}

// Calculate totals
$total_usage = [];
foreach ($logs as $log) {
    $chemical_key = $log['chemical_id'];
    if (!isset($total_usage[$chemical_key])) {
        $total_usage[$chemical_key] = [
            'chemical_name' => $log['chemical_name'],
            'type' => $log['type'],
            'unit' => $log['unit'],
            'quantity' => 0
        ];
    }
    $total_usage[$chemical_key]['quantity'] += $log['quantity_used'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chemical Usage Log</title>
    <link href="https://unpkg.com/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <style>
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .summary-card {
            transition: transform 0.3s;
        }
        .summary-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1><i class="fas fa-flask me-2"></i>Chemical Usage Log</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="chemical_inventory.php" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left me-1"></i> Back to Inventory
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportToExcel()">
                            <i class="fas fa-file-excel me-1"></i> Export to Excel
                        </button>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="get" class="row g-3">
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="chemical_id" class="form-label">Chemical</label>
                            <select class="form-select" id="chemical_id" name="chemical_id">
                                <option value="0">All Chemicals</option>
                                <?php foreach ($chemicals as $chemical): ?>
                                <option value="<?= $chemical['id'] ?>" <?= $chemical_id == $chemical['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($chemical['chemical_name']) ?> (<?= htmlspecialchars($chemical['type']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="technician_id" class="form-label">Technician</label>
                            <select class="form-select" id="technician_id" name="technician_id">
                                <option value="0">All Technicians</option>
                                <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['technician_id'] ?>" <?= $technician_id == $tech['technician_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tech['technician_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i> Apply Filters
                            </button>
                            <a href="chemical_usage_log.php" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i> Reset Filters
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Usage Summary -->
                <div class="mb-4">
                    <h4 class="mb-3">Usage Summary</h4>
                    <div class="row">
                        <?php foreach ($total_usage as $chemical): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card summary-card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($chemical['chemical_name']) ?></h5>
                                    <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($chemical['type']) ?></h6>
                                    <p class="card-text fs-4 fw-bold text-primary">
                                        <?= number_format($chemical['quantity'], 2) ?> <?= htmlspecialchars($chemical['unit']) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($total_usage)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> No chemical usage data found for the selected filters.
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Usage Log Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="usageLogTable">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Chemical</th>
                                <th>Type</th>
                                <th>Quantity Used</th>
                                <th>Technician</th>
                                <th>Job Order</th>
                                <th>Client</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-3">No usage logs found for the selected filters.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($log['usage_date'])) ?></td>
                                <td><?= htmlspecialchars($log['chemical_name']) ?></td>
                                <td><?= htmlspecialchars($log['type']) ?></td>
                                <td><?= number_format($log['quantity_used'], 2) ?> <?= htmlspecialchars($log['unit']) ?></td>
                                <td><?= htmlspecialchars($log['technician_name']) ?></td>
                                <td>
                                    <?php if ($log['job_order_id']): ?>
                                    <a href="job_order_details.php?id=<?= $log['job_order_id'] ?>" class="text-decoration-none">
                                        Job #<?= $log['job_order_id'] ?>
                                    </a>
                                    <?php else: ?>
                                    N/A
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($log['client_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($log['notes'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <script src="https://unpkg.com/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script>
        // Function to export table to Excel
        function exportToExcel() {
            const table = document.getElementById('usageLogTable');
            const wb = XLSX.utils.table_to_book(table, {sheet: "Chemical Usage Log"});
            const dateStr = new Date().toISOString().slice(0, 10);
            XLSX.writeFile(wb, `Chemical_Usage_Log_${dateStr}.xlsx`);
        }
    </script>
</body>
</html>
