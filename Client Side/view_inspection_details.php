<?php
session_start();
require_once '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    echo "You must be logged in to view this page.";
    exit;
}

// Check if report ID is provided
if (!isset($_GET['report_id'])) {
    echo "Report ID is required.";
    exit;
}

$reportId = $_GET['report_id'];
$clientId = $_SESSION['client_id'];

// Fetch inspection report details
$stmt = $conn->prepare("
    SELECT
        ar.report_id,
        ar.end_time,
        ar.area,
        ar.notes as report_notes,
        ar.attachments,
        ar.created_at as report_date,
        ar.pest_types,
        ar.problem_area,
        a.appointment_id,
        a.client_id,
        a.client_name,
        a.location_address,
        a.kind_of_place,
        a.preferred_date,
        a.preferred_time,
        a.pest_problems,
        a.notes as client_notes,
        t.technician_id,
        t.username as technician_name,
        t.tech_contact_number as technician_contact,
        t.tech_fname as technician_fname,
        t.tech_lname as technician_lname,
        t.technician_picture
    FROM assessment_report ar
    JOIN appointments a ON ar.appointment_id = a.appointment_id
    LEFT JOIN technicians t ON a.technician_id = t.technician_id
    WHERE ar.report_id = ? AND a.client_id = ?
");

$stmt->bind_param("ii", $reportId, $clientId);

// Execute query and check for errors
try {
    if (!$stmt->execute()) {
        echo "Database error: " . $stmt->error;
        exit;
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Try a simpler query to see if the report exists at all
        $checkStmt = $conn->prepare("SELECT report_id FROM assessment_report WHERE report_id = ?");
        $checkStmt->bind_param("i", $reportId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            echo "<div class='alert alert-danger'>You don't have permission to view this report.</div>";
            echo "<a href='job_order_report.php' class='btn btn-primary'>Back to Job Orders</a>";
        } else {
            echo "<div class='alert alert-danger'>Inspection report not found.</div>";
            echo "<a href='job_order_report.php' class='btn btn-primary'>Back to Job Orders</a>";
        }
        exit;
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    echo "<a href='job_order_report.php' class='btn btn-primary'>Back to Job Orders</a>";
    exit;
}

$report = $result->fetch_assoc();

// Format date
$reportDate = date('F j, Y', strtotime($report['report_date']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspection Report Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }
        .report-container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .report-header {
            margin-bottom: 30px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 20px;
        }
        .report-section {
            margin-bottom: 30px;
        }
        .report-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #4285f4;
        }
        .report-field {
            margin-bottom: 15px;
        }
        .report-field-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .report-field-value {
            font-size: 1rem;
        }
        .report-notes {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            white-space: pre-line;
        }
        .technician-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .technician-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }
        .attachments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        .attachment-item {
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .attachment-img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }
        .btn-back {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="job_order_report.php" class="btn btn-primary btn-back">
            <i class="fas fa-arrow-left"></i> Back to Job Orders
        </a>

        <div class="report-container">
            <div class="report-header">
                <h1>Inspection Report #<?= $report['report_id'] ?></h1>
                <p class="text-muted">Generated on <?= $reportDate ?></p>
            </div>

            <div class="report-section">
                <h2 class="report-section-title"><i class="fas fa-info-circle me-2"></i>Report Details</h2>
                <div class="row">
                    <div class="col-md-6">
                        <div class="report-field">
                            <div class="report-field-label">Report Date</div>
                            <div class="report-field-value"><?= $reportDate ?></div>
                        </div>
                        <div class="report-field">
                            <div class="report-field-label">Completion Time</div>
                            <div class="report-field-value"><?= $report['end_time'] ?: 'Not specified' ?></div>
                        </div>
                        <div class="report-field">
                            <div class="report-field-label">Area Treated</div>
                            <div class="report-field-value"><?= $report['area'] ? $report['area'] . ' m²' : 'Not specified' ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="report-field">
                            <div class="report-field-label">Pest Types</div>
                            <div class="report-field-value"><?= $report['pest_types'] ?: 'Not specified' ?></div>
                        </div>
                        <div class="report-field">
                            <div class="report-field-label">Problem Area</div>
                            <div class="report-field-value"><?= $report['problem_area'] ?: 'Not specified' ?></div>
                        </div>
                        <div class="report-field">
                            <div class="report-field-label">Location</div>
                            <div class="report-field-value"><?= $report['location_address'] ?: 'Not specified' ?></div>
                        </div>
                    </div>
                </div>

                <div class="report-field mt-4">
                    <div class="report-field-label">Technician Notes</div>
                    <div class="report-notes mt-2">
                        <?= $report['report_notes'] ?: 'No additional notes from technician' ?>
                    </div>
                </div>
            </div>

            <?php if ($report['technician_id']): ?>
            <div class="report-section">
                <h2 class="report-section-title"><i class="fas fa-user-shield me-2"></i>Assigned Technician</h2>
                <div class="technician-info">
                    <?php if ($report['technician_picture']): ?>
                    <img src="../Admin Side/<?= $report['technician_picture'] ?>" alt="Technician" class="technician-avatar">
                    <?php else: ?>
                    <div class="technician-avatar d-flex align-items-center justify-content-center bg-light">
                        <i class="fas fa-user fa-2x text-secondary"></i>
                    </div>
                    <?php endif; ?>

                    <div>
                        <h5>
                            <?php if ($report['tech_fname'] && $report['tech_lname']): ?>
                                <?= $report['tech_fname'] . ' ' . $report['tech_lname'] ?>
                            <?php else: ?>
                                <?= $report['technician_name'] ?>
                            <?php endif; ?>
                        </h5>
                        <p class="mb-0"><i class="fas fa-phone me-2"></i><?= $report['technician_contact'] ?: 'No contact information' ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($report['attachments']): ?>
            <div class="report-section">
                <h2 class="report-section-title"><i class="fas fa-images me-2"></i>Attachments</h2>
                <div class="attachments-grid">
                    <?php
                    $attachments = explode(',', $report['attachments']);
                    foreach ($attachments as $attachment):
                        if (trim($attachment)):
                    ?>
                    <div class="attachment-item">
                        <a href="../uploads/<?= trim($attachment) ?>" target="_blank">
                            <img src="../uploads/<?= trim($attachment) ?>" alt="Attachment" class="attachment-img">
                        </a>
                    </div>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>
            </div>
            <?php endif; ?>


        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
