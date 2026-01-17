<?php
session_start();
require_once '../db_connect.php';
require_once '../notification_functions.php';

$client_id = $_SESSION['client_id'];

// Get contract progress information - count total and completed job orders
$progress_query = "SELECT
    ar.report_id,
    jo.frequency,
    COUNT(jo.job_order_id) as total_job_orders,
    SUM(CASE
        WHEN jo.status = 'completed' OR jor.report_id IS NOT NULL THEN 1
        ELSE 0
    END) as completed_job_orders
FROM job_order jo
JOIN assessment_report ar ON jo.report_id = ar.report_id
JOIN appointments a ON ar.appointment_id = a.appointment_id
LEFT JOIN job_order_report jor ON jo.job_order_id = jor.job_order_id
WHERE a.client_id = ?
AND (jo.client_approval_status = 'approved' OR jo.client_approval_status = 'one-time')
GROUP BY ar.report_id, jo.frequency";

$progress_stmt = $conn->prepare($progress_query);
$progress_stmt->bind_param("i", $client_id);
$progress_stmt->execute();
$progress_result = $progress_stmt->get_result();

$contract_progress = [];
$total_job_orders = 0;
$total_completed_job_orders = 0;

while ($row = $progress_result->fetch_assoc()) {
    $contract_progress[$row['report_id'] . '-' . $row['frequency']] = [
        'report_id' => $row['report_id'],
        'frequency' => $row['frequency'],
        'total_job_orders' => $row['total_job_orders'],
        'completed_job_orders' => $row['completed_job_orders']
    ];

    $total_job_orders += $row['total_job_orders'];
    $total_completed_job_orders += $row['completed_job_orders'];
}

// Calculate overall progress percentage
$overall_progress_percentage = ($total_job_orders > 0) ? round(($total_completed_job_orders / $total_job_orders) * 100) : 0;

// Fetch only approved job orders for this client
// Modified query to prevent duplicate job orders by using GROUP BY and selecting the latest job report
$stmt = $conn->prepare("SELECT
    jo.job_order_id,
    jo.type_of_work,
    jo.preferred_date,
    jo.preferred_time,
    jo.frequency,
    jo.client_approval_status,
    jo.client_approval_date,
    jo.status,
    jo.cost,
    jo.payment_amount,
    jo.payment_date,
    ar.report_id,
    ar.area,
    ar.notes as report_notes,
    ar.pest_types,
    ar.problem_area,
    a.location_address as property_address,
    a.status as appointment_status,
    a.client_name,
    a.contact_number,
    a.email as client_email,
    CASE
        WHEN jo.status = 'completed' THEN 'finished'
        WHEN jo.preferred_date < CURDATE() OR (jo.preferred_date = CURDATE() AND jo.preferred_time < CURTIME()) THEN
            CASE
                WHEN jor.report_id IS NOT NULL THEN 'finished'
                ELSE 'past_due'
            END
        ELSE 'scheduled'
    END as job_status,
    jor.report_id as job_report_id,
    jor.observation_notes,
    jor.recommendation, -- Get actual recommendation field
    jor.attachments as report_attachments,
    jor.created_at as report_created_at,
    jor.chemical_usage, -- Add chemical_usage field
    t.technician_id,
    t.username as technician_name,
    t.tech_contact_number as technician_contact,
    t.tech_fname as technician_fname,
    t.tech_lname as technician_lname,
    t.technician_picture,
    jf.feedback_id,
    jf.rating,
    jf.comments as feedback_comments,
    jf.created_at as feedback_date,
    jf.technician_arrived,
    jf.job_completed,
    jf.verification_notes
    FROM job_order jo
    JOIN assessment_report ar ON jo.report_id = ar.report_id
    JOIN appointments a ON ar.appointment_id = a.appointment_id
    LEFT JOIN (
        SELECT job_order_id, MAX(report_id) as latest_report_id
        FROM job_order_report
        GROUP BY job_order_id
    ) latest_jor ON jo.job_order_id = latest_jor.job_order_id
    LEFT JOIN job_order_report jor ON latest_jor.latest_report_id = jor.report_id
    LEFT JOIN job_order_technicians jot ON jo.job_order_id = jot.job_order_id
    LEFT JOIN technicians t ON jot.technician_id = t.technician_id
    LEFT JOIN joborder_feedback jf ON jo.job_order_id = jf.job_order_id
    WHERE a.client_id = ?
    AND (jo.client_approval_status = 'approved' OR jo.client_approval_status = 'one-time')
    ORDER BY
        CASE
            WHEN jo.status = 'completed' THEN 2
            WHEN jo.preferred_date < CURDATE() OR (jo.preferred_date = CURDATE() AND jo.preferred_time < CURTIME()) THEN 1
            ELSE 0
        END ASC,
        jo.preferred_date ASC,
        jo.preferred_time ASC");

// We'll sort the arrays after fetching to ensure proper ordering
$stmt->bind_param("i", $client_id);
$stmt->execute();
$job_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, shrink-to-fit=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Job Order Report | MacJ Pest Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/sidebar-fix.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/client-common.css">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="css/floating-action-button.css">
    <style>
        /* Core Variables */
        :root {
            --primary-blue: #4285f4;
            --light-blue: #e8f4ff;
            --success-green: #34c759;
            --light-green: #e3f9e5;
            --card-border: #e0e0e0;
            --light-gray: #f8f9fa;
            --text-dark: #333333;
            --text-muted: #6c757d;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        /* Progress Bar Styles */
        .progress-container {
            margin: 20px 0;
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            border: 1px solid var(--card-border);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .progress-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .progress-title i {
            background-color: var(--primary-blue);
            color: white;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .progress-stats {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .progress-stat {
            background-color: var(--light-blue);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--primary-blue);
            border: 1px solid rgba(66, 133, 244, 0.2);
        }

        .progress-bar-container {
            height: 12px;
            background-color: #e9ecef;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 10px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-blue) 0%, var(--secondary-color) 100%);
            border-radius: 6px;
            transition: width 0.6s ease;
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .progress-percentage {
            font-weight: 600;
            color: var(--primary-blue);
        }

        /* Page Header */
        .job-status-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #1a73e8 100%);
            color: white;
            border-radius: var(--border-radius);
            padding: 25px 30px;
            margin-bottom: 35px;
            box-shadow: 0 5px 15px rgba(66, 133, 244, 0.15);
            position: relative;
            overflow: hidden;
        }

        .job-status-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }

        .job-status-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(-30%, 30%);
        }

        .job-status-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 1;
        }

        .job-status-header h1 i {
            background: rgba(255, 255, 255, 0.2);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .job-status-header p {
            margin-bottom: 0;
            opacity: 0.9;
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--light-blue);
            position: relative;
        }

        .section-header::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 80px;
            height: 2px;
            background-color: var(--primary-blue);
        }

        .section-header i {
            color: white;
            font-size: 1.1rem;
            background-color: var(--primary-blue);
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .section-header h2 {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
            color: var(--primary-blue);
        }

        /* Collapse Button Styles */
        .btn-collapse {
            background: transparent;
            border: none;
            color: var(--primary-blue);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            outline: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            background-color: #f0f7ff;
        }

        .btn-collapse:hover {
            background-color: var(--primary-blue);
            color: white;
        }

        .btn-collapse i {
            transition: transform 0.3s ease;
            background-color: transparent !important;
            width: auto !important;
            height: auto !important;
            color: inherit !important;
        }

        .btn-collapse[aria-expanded="false"] i {
            transform: rotate(180deg);
        }

        /* Adjust for past due section */
        .past-due-section .btn-collapse {
            color: #dc3545;
            background-color: #fff5f5;
        }

        .past-due-section .btn-collapse:hover {
            background-color: #dc3545;
            color: white;
        }

        /* Adjust for completed section */
        .completed-section .btn-collapse {
            color: var(--success-green);
            background-color: #e3f9e5;
        }

        .completed-section .btn-collapse:hover {
            background-color: var(--success-green);
            color: white;
        }

        /* Job Cards */
        .job-card {
            background: white;
            border-radius: var(--border-radius);
            border: 1px solid var(--card-border);
            margin-bottom: 24px;
            overflow: hidden;
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .job-card:hover {
            box-shadow: var(--box-shadow);
            transform: translateY(-3px);
        }

        .job-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: white;
        }

        .job-card-header h5 {
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
            color: var(--primary-blue);
        }

        .job-card-body {
            padding: 16px 20px;
            flex: 1;
        }

        .job-card-footer {
            padding: 14px 20px;
            border-top: 1px solid var(--card-border);
            background-color: var(--light-gray);
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .status-badge::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(66, 133, 244, 0.4);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(66, 133, 244, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(66, 133, 244, 0);
            }
        }

        .status-scheduled {
            background-color: var(--light-blue);
            color: var(--primary-blue);
            border: 1px solid rgba(66, 133, 244, 0.3);
        }

        .status-scheduled::before {
            background-color: var(--primary-blue);
            animation: pulse 2s infinite;
        }

        @keyframes pulse-green {
            0% {
                box-shadow: 0 0 0 0 rgba(52, 199, 89, 0.4);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(52, 199, 89, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(52, 199, 89, 0);
            }
        }

        .status-finished {
            background-color: var(--light-green);
            color: var(--success-green);
            border: 1px solid rgba(52, 199, 89, 0.3);
        }

        .status-finished::before {
            background-color: var(--success-green);
            animation: pulse-green 2s infinite;
        }

        /* Job Details */
        .detail-item {
            margin-bottom: 12px;
            display: flex;
            align-items: baseline;
        }

        .detail-item:last-child {
            margin-bottom: 0;
        }

        .detail-label {
            font-weight: 600;
            width: 100px;
            color: var(--text-muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            flex: 1;
            font-size: 0.95rem;
            color: var(--text-dark);
            font-weight: 500;
        }

        .detail-item i {
            margin-right: 8px;
            color: var(--primary-blue);
            width: 16px;
            text-align: center;
        }

        /* Compact Job Card for Completed Jobs */
        .compact-job-card {
            background: white;
            border-radius: var(--border-radius);
            border: 1px solid var(--card-border);
            margin-bottom: 20px;
            overflow: hidden;
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .compact-job-card:hover {
            box-shadow: var(--box-shadow);
            transform: translateY(-3px);
        }

        .compact-job-header {
            padding: 14px 16px;
            background-color: white;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .compact-job-header h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-blue);
        }

        .compact-job-body {
            padding: 14px 16px;
            flex: 1;
        }

        .compact-detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.85rem;
            align-items: baseline;
        }

        .compact-detail-label {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .compact-detail-label i {
            color: var(--primary-blue);
            width: 16px;
            text-align: center;
        }

        .compact-detail-value {
            font-weight: 600;
            color: var(--text-dark);
            text-align: right;
        }

        .compact-detail-item:last-child {
            margin-bottom: 0;
        }

        /* Buttons */
        .btn-view-details {
            background-color: white;
            color: var(--primary-blue);
            border: 1px solid var(--primary-blue);
            border-radius: var(--border-radius);
            padding: 10px 16px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(66, 133, 244, 0.1);
        }

        .btn-view-details:hover {
            background-color: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(66, 133, 244, 0.2);
        }

        .btn-view-details i {
            font-size: 0.85rem;
        }

        .scheduled-section .btn-view-details {
            color: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        .scheduled-section .btn-view-details:hover {
            background-color: var(--primary-blue);
            color: white;
        }

        .completed-section .btn-view-details {
            color: var(--success-green);
            border-color: var(--success-green);
            box-shadow: 0 2px 4px rgba(52, 199, 89, 0.1);
        }

        .completed-section .btn-view-details:hover {
            background-color: var(--success-green);
            color: white;
            box-shadow: 0 4px 8px rgba(52, 199, 89, 0.2);
        }

        /* Feedback button styles */
        .btn-feedback-submitted {
            color: var(--success-green) !important;
            border-color: var(--success-green) !important;
            background-color: rgba(52, 199, 89, 0.1) !important;
        }

        .btn-feedback-submitted:hover {
            background-color: var(--success-green) !important;
            color: white !important;
        }

        .btn-feedback-needed {
            color: var(--primary-blue) !important;
            border-color: var(--primary-blue) !important;
            background-color: rgba(66, 133, 244, 0.1) !important;
        }

        .btn-feedback-needed:hover {
            background-color: var(--primary-blue) !important;
            color: white !important;
        }

        /* Scheduled Date */
        .scheduled-date {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 12px;
            background-color: var(--light-blue);
            padding: 8px 12px;
            border-radius: var(--border-radius);
            border: 1px solid rgba(66, 133, 244, 0.15);
        }

        .scheduled-date i {
            color: var(--primary-blue);
        }

        .completed-section .scheduled-date {
            background-color: var(--light-green);
            border: 1px solid rgba(52, 199, 89, 0.15);
        }

        .completed-section .scheduled-date i {
            color: var(--success-green);
        }

        /* Section Structure */
        .job-order-container {
            display: flex;
            flex-direction: column;
            gap: 40px;
        }

        .job-section {
            background-color: #f9fbff;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }

        /* Search Container */
        .search-container {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border: 1px solid var(--card-border);
            transition: var(--transition);
        }

        .search-container:focus-within,
        .search-container.active {
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.15);
            border-color: var(--primary-blue);
        }

        .search-info {
            font-style: italic;
            color: var(--text-muted);
        }

        .search-highlight {
            background-color: rgba(255, 230, 0, 0.3);
            padding: 2px;
            border-radius: 2px;
        }

        .no-results-message {
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            margin-top: 15px;
            border: 1px dashed #dee2e6;
        }

        .no-results-message i {
            font-size: 2rem;
            color: #dee2e6;
            margin-bottom: 10px;
        }

        /* Filter Container */
        .filter-container {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .scheduled-section .filter-container {
            background-color: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(66, 133, 244, 0.1);
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            max-width: 300px;
        }

        .filter-group label {
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--primary-blue);
            font-size: 14px;
        }

        .filter-group select {
            padding: 10px 15px;
            border: 1px solid var(--card-border);
            border-radius: var(--border-radius);
            background-color: white;
            font-size: 14px;
            color: var(--text-color);
            transition: var(--transition);
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.1);
        }

        @media (max-width: 576px) {
            .search-container,
            .filter-container {
                padding: 12px;
            }

            .filter-group {
                max-width: 100%;
            }

            .search-container .input-group {
                flex-direction: column;
            }

            .search-container .input-group .form-control {
                border-radius: var(--border-radius) var(--border-radius) 0 0;
                margin-bottom: -1px;
            }

            .search-container .input-group .btn:first-of-type {
                border-radius: 0;
                margin-bottom: -1px;
            }

            .search-container .input-group .btn:last-of-type {
                border-radius: 0 0 var(--border-radius) var(--border-radius);
            }
        }

        .job-section .section-header {
            margin-top: 0;
        }

        .scheduled-section {
            border-top: 4px solid var(--primary-blue);
        }

        .completed-section {
            border-top: 4px solid var(--success-green);
        }

        /* Past Due Section Styles */
        .past-due-section {
            border-top: 4px solid #dc3545;
            background-color: #fff8f8;
        }

        .past-due-section .section-header i {
            background-color: #dc3545;
        }

        .past-due-section .section-header h2 {
            color: #dc3545;
        }

        .past-due-section .section-header::after {
            background-color: #dc3545;
        }

        .past-due-section .job-card {
            border-left: 4px solid #dc3545;
        }

        .status-past-due {
            background-color: #fff5f5;
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .status-past-due::before {
            background-color: #dc3545;
            animation: pulse-red 2s infinite;
        }

        @keyframes pulse-red {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(220, 53, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
        }

        .past-due-section .scheduled-date {
            background-color: #fff5f5;
            border: 1px solid rgba(220, 53, 69, 0.15);
        }

        .past-due-section .scheduled-date i {
            color: #dc3545;
        }

        .past-due-section .btn-view-details {
            color: #dc3545;
            border-color: #dc3545;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.1);
        }

        .past-due-section .btn-view-details:hover {
            background-color: #dc3545;
            color: white;
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.2);
        }

        .section-content {
            margin-top: 20px;
        }

        /* Empty Section */
        .empty-section {
            text-align: center;
            padding: 40px 20px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin: 10px 0 20px;
        }

        .empty-icon {
            font-size: 60px;
            color: var(--primary-blue);
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .scheduled-section .empty-icon {
            color: var(--primary-blue);
        }

        .completed-section .empty-icon {
            color: var(--success-green);
        }

        .empty-section h4 {
            color: var(--text-dark);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .empty-section p {
            color: var(--text-muted);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Enhanced Responsive Styles */
        @media (max-width: 1199px) {
            .job-section {
                padding: 25px;
            }

            .modal-xl {
                max-width: 95%;
            }

            /* Adjust column layout for large screens */
            .col-xl-4 {
                flex: 0 0 auto;
                width: 50%;
            }

            .col-xl-3 {
                flex: 0 0 auto;
                width: 33.33%;
            }
        }

        @media (max-width: 991px) {
            .job-status-header h1 {
                font-size: 1.3rem;
            }

            .job-status-header p {
                font-size: 0.9rem;
            }

            .section-header h2 {
                font-size: 1.25rem;
            }

            .btn-collapse {
                width: 28px;
                height: 28px;
            }

            .btn-collapse i {
                font-size: 0.9rem;
            }

            .job-section {
                padding: 20px;
            }

            .job-order-container {
                gap: 30px;
            }

            /* Modal responsiveness */
            .modal-lg, .modal-xl {
                max-width: 90%;
            }

            .report-field-value {
                word-break: break-word;
            }

            /* Adjust column layout for medium screens */
            .col-lg-4 {
                flex: 0 0 auto;
                width: 50%;
            }

            /* Improve technician card layout */
            .technician-card {
                padding: 12px;
            }

            .technician-profile {
                gap: 12px;
            }

            .technician-avatar {
                width: 60px;
                height: 60px;
            }

            /* Improve job card layout */
            .job-card, .compact-job-card {
                height: auto;
            }
        }

        @media (max-width: 767px) {
            /* Improve header layout */
            .header {
                padding: 10px 15px;
            }

            .header-title {
                padding-left: 40px; /* Add padding to make space for the menu toggle button */
            }

            .header-title h1 {
                font-size: 1.2rem;
                white-space: nowrap; /* Prevent text wrapping */
            }

            /* Adjust job card header */
            .job-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .compact-job-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            /* Improve detail items layout */
            .detail-item {
                flex-direction: column;
                margin-bottom: 16px;
                padding-bottom: 10px;
                border-bottom: 1px dashed rgba(0,0,0,0.05);
            }

            .detail-item:last-child {
                border-bottom: none;
                padding-bottom: 0;
            }

            .detail-label {
                width: 100%;
                margin-bottom: 4px;
            }

            /* Improve compact detail items */
            .compact-detail-item {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 12px;
                padding-bottom: 8px;
                border-bottom: 1px dashed rgba(0,0,0,0.05);
            }

            .compact-detail-item:last-child {
                border-bottom: none;
                padding-bottom: 0;
            }

            .compact-detail-value {
                width: 100%;
                text-align: left;
                margin-top: 4px;
            }

            /* Make buttons full width */
            .btn-view-details {
                width: 100%;
                justify-content: center;
            }

            .compact-job-card {
                margin-bottom: 16px;
            }

            .job-section {
                padding: 20px 15px;
            }

            /* Sidebar fix for mobile */
            #sidebar {
                z-index: 1050 !important;
            }

            #menuToggle {
                z-index: 1060 !important;
            }

            /* Modal responsiveness */
            .modal-dialog {
                margin: 0.5rem;
                max-width: 100%;
            }

            .modal-content {
                border-radius: 0.5rem;
            }

            /* Improve attachment display */
            .report-attachments {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }

            .attachment-img {
                height: 100px;
            }

            /* Improve technician info card */
            .technician-info-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .tech-avatar {
                margin-bottom: 8px;
            }

            /* Adjust column layout for small screens */
            .col-md-6 {
                flex: 0 0 auto;
                width: 100%;
            }

            /* Improve row spacing */
            .row {
                row-gap: 15px;
            }

            /* Improve filter container */
            .filter-container {
                padding: 12px;
            }

            /* Improve job status header */
            .job-status-header {
                padding: 20px;
                margin-bottom: 20px;
            }

            /* Improve section spacing */
            .section-header {
                margin-bottom: 15px;
            }

            .section-content {
                margin-top: 15px;
            }
        }

        @media (max-width: 576px) {
            /* Further optimize for extra small screens */
            .job-status-header {
                padding: 15px;
                margin-bottom: 20px;
            }

            .job-status-header h1 {
                font-size: 1.1rem;
            }

            .job-status-header p {
                font-size: 0.85rem;
            }

            .section-header h2 {
                font-size: 1.1rem;
            }

            .section-header i {
                width: 28px;
                height: 28px;
                font-size: 0.9rem;
            }

            .btn-collapse {
                width: 24px;
                height: 24px;
            }

            .btn-collapse i {
                font-size: 0.8rem;
            }

            /* Optimize card padding */
            .job-card-body {
                padding: 12px 15px;
            }

            .job-card-footer {
                padding: 12px 15px;
            }

            .compact-job-body {
                padding: 12px 15px;
            }

            /* Optimize scheduled date display */
            .scheduled-date {
                font-size: 0.8rem;
                padding: 6px 10px;
            }

            /* Optimize compact details */
            .compact-detail-item {
                margin-bottom: 8px;
            }

            .compact-detail-label,
            .compact-detail-value {
                font-size: 0.8rem;
            }

            /* Optimize container spacing */
            .job-order-container {
                gap: 20px;
            }

            .job-section {
                padding: 15px 12px;
            }

            /* Optimize empty section */
            .empty-section {
                padding: 25px 15px;
            }

            .empty-icon {
                font-size: 45px;
                margin-bottom: 15px;
            }

            /* Optimize empty state */
            .empty-state {
                padding: 25px 15px;
            }

            .empty-state .empty-icon {
                font-size: 50px;
            }

            .empty-state h3 {
                font-size: 1.2rem;
            }

            .empty-state p {
                font-size: 0.9rem;
            }

            /* Optimize modal display */
            .modal-header {
                padding: 0.75rem 1rem;
            }

            .modal-body {
                padding: 1rem;
            }

            .modal-footer {
                padding: 0.75rem 1rem;
                flex-direction: column;
            }

            .modal-footer .btn {
                width: 100%;
                margin: 0.25rem 0;
            }

            /* Optimize report display */
            .report-section-title {
                font-size: 1rem;
                padding: 10px;
            }

            .report-field-label {
                font-size: 0.8rem;
            }

            .report-field-value {
                font-size: 0.9rem;
            }

            .report-notes {
                font-size: 0.85rem;
                padding: 10px;
            }

            .payment-amount {
                font-size: 1.1rem;
            }

            /* Optimize attachments display */
            .report-attachments {
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
                gap: 10px;
            }

            .attachment-img {
                height: 80px;
            }

            /* Optimize technician card */
            .technician-avatar {
                width: 50px;
                height: 50px;
            }

            .technician-name {
                font-size: 1rem;
            }

            .technician-username {
                font-size: 0.8rem;
            }

            .technician-contact {
                font-size: 0.85rem;
            }

            /* Optimize buttons */
            .btn-view-details {
                padding: 8px 12px;
                font-size: 0.85rem;
            }

            /* Optimize status badges */
            .status-badge {
                padding: 4px 10px;
                font-size: 0.65rem;
            }
        }

        /* Extra small devices (portrait phones) */
        @media (max-width: 375px) {
            /* Improve header for very small screens */
            .header-title {
                padding-left: 45px; /* Increase padding for extra small screens */
            }

            .header-title h1 {
                font-size: 1.1rem;
            }

            /* Adjust menu toggle position */
            #menuToggle {
                top: 15px !important;
                left: 10px !important;
            }

            .job-status-header h1 {
                font-size: 1rem;
            }

            .section-header h2 {
                font-size: 1rem;
            }

            .btn-collapse {
                width: 22px;
                height: 22px;
            }

            .btn-collapse i {
                font-size: 0.75rem;
            }

            .job-card-header h5,
            .compact-job-header h5 {
                font-size: 0.95rem;
            }

            .detail-label,
            .compact-detail-label {
                font-size: 0.75rem;
            }

            .detail-value,
            .compact-detail-value {
                font-size: 0.85rem;
            }

            .btn-view-details {
                font-size: 0.8rem;
                padding: 8px 10px;
            }

            .scheduled-date {
                font-size: 0.75rem;
                padding: 5px 8px;
            }

            .empty-section h4 {
                font-size: 1rem;
            }

            .empty-section p {
                font-size: 0.8rem;
            }

            .report-section {
                margin-bottom: 15px;
            }

            .report-field {
                margin-bottom: 10px;
            }
        }

        /* Enhanced fix for sidebar in responsive mode */
        @media (max-width: 768px) {
            /* Improved sidebar display */
            #sidebar {
                position: fixed !important;
                top: 0 !important;
                left: -280px !important; /* Start off-screen */
                width: 280px !important;
                height: 100% !important;
                z-index: 1050 !important;
                transition: left 0.3s ease !important;
                overflow-y: auto !important;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.2) !important;
            }

            #sidebar.active {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                left: 0 !important;
                transform: translateX(0) !important;
            }

            /* Improved overlay for sidebar */
            .sidebar-overlay {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
                background-color: rgba(0, 0, 0, 0.5) !important;
                z-index: 1045 !important;
                display: block !important;
                opacity: 1 !important;
                cursor: pointer !important;
                transition: opacity 0.3s ease !important;
                backdrop-filter: blur(2px) !important;
            }

            /* Prevent scrolling when sidebar is active */
            body.sidebar-active {
                overflow: hidden !important;
            }

            /* Improved menu toggle button */
            #menuToggle {
                position: fixed !important;
                top: 15px !important;
                left: 10px !important; /* Move slightly to the left */
                z-index: 1060 !important;
                width: 36px !important; /* Slightly smaller */
                height: 36px !important; /* Slightly smaller */
                border-radius: 50% !important;
                background-color: var(--primary-blue) !important;
                color: white !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                cursor: pointer !important;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2) !important;
                border: none !important;
                transition: background-color 0.2s ease !important;
                font-size: 0.9rem !important; /* Slightly smaller icon */
            }

            #menuToggle:hover {
                background-color: var(--primary-dark) !important;
            }

            /* Ensure main content is properly displayed */
            .main-content {
                padding-top: 70px !important;
                padding-bottom: 30px !important;
                min-height: 100vh !important;
                width: 100% !important;
                margin-left: 0 !important;
            }

            /* Ensure container has proper padding */
            .container {
                padding-left: 15px !important;
                padding-right: 15px !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            /* Fix button display on small screens */
            .btn {
                white-space: normal !important;
                text-align: center !important;
                padding: 8px 16px !important;
                font-size: 0.9rem !important;
            }

            /* Ensure text doesn't overflow */
            p, h1, h2, h3, h4, h5, h6 {
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
                max-width: 100% !important;
            }

            /* Improve sidebar menu items */
            .sidebar-menu a {
                padding: 12px 20px !important;
                font-size: 1rem !important;
            }

            /* Improve sidebar header */
            .sidebar-header {
                padding: 20px !important;
            }

            .sidebar-header h2 {
                font-size: 1.3rem !important;
            }

            .sidebar-header h3 {
                font-size: 1rem !important;
            }

            /* Improve sidebar footer */
            .sidebar-footer {
                padding: 20px !important;
                font-size: 0.9rem !important;
            }
        }

        /* Modal Styles */
        @media (min-width: 1200px) {
            .modal-xl {
                max-width: 1140px;
            }
        }

        /* Job Report Modal Styles */
        .report-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .report-section {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            padding: 15px;
            transition: all 0.3s ease;
        }

        .report-section:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .report-field {
            margin-bottom: 15px;
        }

        .report-field-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 5px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .report-field-value {
            font-size: 1rem;
            color: var(--text-dark);
            font-weight: 500;
            padding: 5px 0;
            word-break: break-word;
        }

        .report-notes {
            white-space: pre-line;
            font-size: 0.95rem;
            line-height: 1.6;
            padding: 15px;
            border-radius: 8px;
            background-color: #f8f9fa;
            border: 1px solid #eee;
        }

        .payment-amount {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--success-green);
            padding: 5px 10px;
            background-color: rgba(52, 199, 89, 0.1);
            border-radius: 5px;
            display: inline-block;
        }

        .report-attachments {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .attachment-item {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
            background-color: #fff;
            border: 1px solid #eee;
        }

        .attachment-item:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }

        .attachment-img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            display: block;
        }

        /* Modal responsive fixes */
        .modal-dialog {
            max-width: 95%;
            margin: 1.75rem auto;
        }

        @media (min-width: 576px) {
            .modal-dialog {
                max-width: 500px;
            }

            .modal-dialog.modal-lg {
                max-width: 800px;
            }

            .modal-dialog.modal-xl {
                max-width: 1140px;
            }
        }

        @media (max-width: 767px) {
            .modal-dialog {
                margin: 1rem auto;
            }

            .modal-content {
                border-radius: 0.5rem;
            }

            .modal-header {
                padding: 0.75rem 1rem;
            }

            .modal-body {
                padding: 1rem;
            }

            .modal-footer {
                padding: 0.75rem 1rem;
            }
        }

        /* Technician Card Styles */
        .technician-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .technician-profile {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .technician-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 2rem;
        }

        .technician-info {
            flex: 1;
        }

        .technician-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 4px;
        }

        .technician-username {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .technician-contact {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            color: var(--text-dark);
        }

        .technician-contact i {
            color: var(--primary-blue);
            font-size: 0.9rem;
        }

        /* Attachment styles for inspection report modal */
        .attachments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            padding: 10px 0;
        }

        .attachment-item {
            border: 1px solid #eaeaea;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            height: 150px;
            position: relative;
        }

        .attachment-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
            border-color: var(--primary-blue);
        }

        .attachment-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .attachment-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 5px;
            font-size: 0.8rem;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .no-attachments-message {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px;
            background-color: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }

        .no-attachments-message i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }

        .no-attachments-message p {
            color: #6c757d;
            margin: 0;
        }
    </style>
</head>
<body class="job_order_report">
    <!-- Header - Improved for mobile -->
    <header class="header">
        <div class="header-title">
            <!-- Client Portal text removed -->
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
                <div>
                    <div class="user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? '') ?></div>
                    <div class="user-role">Client</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Menu toggle button - Positioned to avoid overlapping with header title -->
    <button id="menuToggle"><i class="fas fa-bars"></i></button>

    <aside id="sidebar">
        <div class="sidebar-header">
            <h2>MacJ Pest Control</h2>
            <h3>Welcome, <?= htmlspecialchars($_SESSION['fullname'] ?? '') ?></h3>
        </div>
        <nav class="sidebar-menu">
            <a href="schedule.php">
                <i class="fas fa-calendar-alt fa-icon"></i>
                Schedule Appointment
            </a>
            <a href="profile.php">
                <i class="fas fa-user fa-icon"></i>
                My Profile
            </a>
            <a href="inspection_report.php">
                <i class="fas fa-clipboard-check fa-icon"></i>
                Inspection Report
            </a>
            <a href="contract.php">
                <i class="fas fa-file-contract fa-icon"></i>
                Contract
            </a>
            <a href="job_order_report.php" class="active">
                <i class="fas fa-file-alt fa-icon"></i>
                Job Order Report
            </a>
            <a href="SignOut.php">
                <i class="fas fa-sign-out-alt fa-icon"></i>
                Logout
            </a>
        </nav>
        <div class="sidebar-footer">
            <p>&copy; <?= date('Y') ?> MacJ Pest Control</p>
            <a href="https://www.facebook.com/MACJPEST" target="_blank"><i class="fab fa-facebook"></i> Facebook</a>
        </div>
    </aside>

    <main class="main-content" id="mainContent">
        <div class="container-fluid container-lg mb-5 px-3 px-md-4">
            <div class="job-status-header">
                <h1><i class="fas fa-file-alt"></i> Job Order Reports</h1>
                <p>View and track all your pest control treatment reports</p>
            </div>

            <?php if (!empty($job_orders)): ?>
            <!-- Progress Bar Section -->
            <div class="progress-container">
                <div class="progress-header">
                    <div class="progress-title">
                        <i class="fas fa-tasks"></i>
                        <span>Treatment Progress</span>
                    </div>
                    <div class="progress-stats">
                        <div class="progress-stat">
                            <span>Completed: <?= $total_completed_job_orders ?> / <?= $total_job_orders ?></span>
                        </div>
                    </div>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar" style="width: <?= $overall_progress_percentage ?>%;"></div>
                </div>
                <div class="progress-text">
                    <span>Overall completion</span>
                    <span class="progress-percentage"><?= $overall_progress_percentage ?>%</span>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($job_orders)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>No Job Orders Found</h3>
                    <p class="text-muted">You don't have any approved job orders yet. Once a technician creates a treatment plan for you, it will appear here.</p>
                    <div class="mt-3 d-flex flex-wrap gap-3 justify-content-center">
                        <a href="inspection_report.php" class="btn btn-outline-secondary">
                            <i class="fas fa-clipboard-check"></i> View Inspection Reports
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php
                // Separate job orders into scheduled, past due, and finished
                $scheduled_jobs = [];
                $past_due_jobs = [];
                $finished_jobs = [];

                foreach ($job_orders as $job_order) {
                    if ($job_order['job_status'] == 'finished') {
                        $finished_jobs[] = $job_order;
                    } else if ($job_order['job_status'] == 'past_due') {
                        $past_due_jobs[] = $job_order;
                    } else {
                        $scheduled_jobs[] = $job_order;
                    }
                }

                // Default sorting for job orders
                // For scheduled jobs: nearest date first (upcoming to future)
                usort($scheduled_jobs, function($a, $b) {
                    $date_a = strtotime($a['preferred_date'] . ' ' . $a['preferred_time']);
                    $date_b = strtotime($b['preferred_date'] . ' ' . $b['preferred_time']);
                    return $date_a - $date_b; // Ascending order (nearest first)
                });

                // For past due jobs: oldest first (most overdue first)
                usort($past_due_jobs, function($a, $b) {
                    $date_a = strtotime($a['preferred_date'] . ' ' . $a['preferred_time']);
                    $date_b = strtotime($b['preferred_date'] . ' ' . $b['preferred_time']);
                    return $date_a - $date_b; // Ascending order (nearest first)
                });

                // For finished jobs: most recent first
                usort($finished_jobs, function($a, $b) {
                    $date_a = strtotime($a['preferred_date'] . ' ' . $a['preferred_time']);
                    $date_b = strtotime($b['preferred_date'] . ' ' . $b['preferred_time']);
                    return $date_b - $date_a; // Descending order (most recent first)
                });
                ?>

                <!-- Search Bar -->
                <div class="search-container mb-4">
                    <div class="input-group">
                        <input type="text" id="jobOrderSearch" class="form-control" placeholder="Search job orders by ID, type, location, technician..." aria-label="Search job orders">
                        <button class="btn btn-primary" type="button" id="searchButton">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <button class="btn btn-outline-secondary" type="button" id="clearSearchButton">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                    <div class="search-info mt-2 text-muted small" id="searchInfo"></div>
                </div>

                <!-- Main Content Wrapper -->
                <div class="job-order-container">
                    <!-- UPPER SECTION: Upcoming Treatments Section -->
                    <div class="job-section scheduled-section mb-5">
                        <div class="section-header">
                            <div class="d-flex align-items-center justify-content-between w-100">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-calendar-alt"></i>
                                    <h2>Upcoming Treatments</h2>
                                </div>
                                <button class="btn-collapse" type="button" data-bs-toggle="collapse" data-bs-target="#upcomingTreatmentsContent" aria-expanded="true" aria-controls="upcomingTreatmentsContent">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                            </div>
                        </div>

                        <div class="section-content collapse show" id="upcomingTreatmentsContent">
                            <?php if (empty($scheduled_jobs)): ?>
                                <div class="empty-section">
                                    <div class="empty-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <h4>No Upcoming Treatments</h4>
                                    <p class="text-muted">You don't have any upcoming scheduled treatments at this time.</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($scheduled_jobs as $job_order): ?>
                                        <div class="col-12 col-sm-12 col-md-6 col-xl-4 mb-2">
                                            <div class="job-card">
                                                <div class="job-card-header">
                                                    <h5><?= htmlspecialchars($job_order['type_of_work']) ?></h5>
                                                    <span class="status-badge status-scheduled">SCHEDULED</span>
                                                </div>
                                                <div class="job-card-body">
                                                    <div class="detail-item">
                                                        <div class="detail-label"><i class="fas fa-hashtag"></i> JOB ID</div>
                                                        <div class="detail-value">#<?= $job_order['job_order_id'] ?></div>
                                                    </div>
                                                    <div class="detail-item">
                                                        <div class="detail-label"><i class="fas fa-calendar"></i> DATE</div>
                                                        <div class="detail-value"><?= date('F j, Y', strtotime($job_order['preferred_date'])) ?></div>
                                                    </div>
                                                    <div class="detail-item">
                                                        <div class="detail-label"><i class="fas fa-clock"></i> TIME</div>
                                                        <div class="detail-value"><?= date('g:i A', strtotime($job_order['preferred_time'])) ?></div>
                                                    </div>
                                                    <div class="detail-item">
                                                        <div class="detail-label"><i class="fas fa-map-marker-alt"></i> LOCATION</div>
                                                        <div class="detail-value"><?= htmlspecialchars($job_order['property_address']) ?></div>
                                                    </div>
                                                    <div class="detail-item">
                                                        <div class="detail-label"><i class="fas fa-sync-alt"></i> FREQUENCY</div>
                                                        <div class="detail-value"><?= ucfirst(htmlspecialchars($job_order['frequency'])) ?></div>
                                                    </div>
                                                    <?php if (!empty($job_order['technician_name'])): ?>
                                                    <div class="detail-item technician-detail">
                                                        <div class="detail-label"><i class="fas fa-user-shield"></i> TECHNICIAN</div>
                                                        <div class="detail-value technician-info-card">
                                                            <div class="tech-avatar">
                                                                <?php if (!empty($job_order['technician_picture'])): ?>
                                                                <img src="../Admin Side/<?= htmlspecialchars($job_order['technician_picture']) ?>"
                                                                     alt="<?= htmlspecialchars($job_order['technician_name']) ?>"
                                                                     class="clickable-avatar"
                                                                     onclick="openImageViewer('../Admin Side/<?= htmlspecialchars($job_order['technician_picture']) ?>')"
                                                                     title="Click to view larger image">
                                                                <?php else: ?>
                                                                <i class="fas fa-user"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="tech-info">
                                                                <div class="tech-name">
                                                                    <?php if (!empty($job_order['technician_fname']) && !empty($job_order['technician_lname'])): ?>
                                                                    <?= htmlspecialchars($job_order['technician_fname'] . ' ' . $job_order['technician_lname']) ?>
                                                                    <?php else: ?>
                                                                    <?= htmlspecialchars($job_order['technician_name']) ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <?php if (!empty($job_order['technician_contact'])): ?>
                                                                <div class="tech-contact">
                                                                    <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($job_order['technician_contact']) ?>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="job-card-footer">
                                                    <div class="scheduled-date mb-2">
                                                        <i class="fas fa-calendar-check"></i> Scheduled for: <?= date('F j, Y', strtotime($job_order['preferred_date'])) ?> at <?= date('g:i A', strtotime($job_order['preferred_time'])) ?>
                                                    </div>
                                                    <button class="btn-view-details w-100"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#inspectionModal"
                                                            data-report-id="<?= $job_order['report_id'] ?>">
                                                        <i class="fas fa-eye"></i> View Inspection Details
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- MIDDLE SECTION: Past Due Job Orders Section -->
                    <div class="job-section past-due-section mb-5">
                        <div class="section-header">
                            <div class="d-flex align-items-center justify-content-between w-100">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <h2>Past Due Job Orders</h2>
                                </div>
                                <button class="btn-collapse" type="button" data-bs-toggle="collapse" data-bs-target="#pastDueContent" aria-expanded="true" aria-controls="pastDueContent">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                            </div>
                        </div>

                        <div class="section-content collapse show" id="pastDueContent">
                            <?php if (empty($past_due_jobs)): ?>
                                <div class="empty-section">
                                    <div class="empty-icon">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <h4>No Past Due Job Orders</h4>
                                    <p class="text-muted">All your job orders are either scheduled for the future or have been completed with reports.</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($past_due_jobs as $job_order): ?>
                                        <div class="col-12 col-sm-12 col-md-6 col-xl-4 mb-2">
                                            <div class="job-card">
                                                <div class="job-card-header">
                                                    <h5><?= htmlspecialchars($job_order['type_of_work']) ?></h5>
                                                    <span class="status-badge status-past-due">PAST DUE</span>
                                                </div>
                                                <div class="job-card-body">
                                                    <div class="detail-item">
                                                        <div class="detail-label"><i class="fas fa-hashtag"></i> JOB ID</div>
                                                        <div class="detail-value">#<?= $job_order['job_order_id'] ?></div>
                                                    </div>
                                                    <div class="detail-item">
                                                        <div class="detail-label"><i class="fas fa-calendar"></i> DATE</div>
                                                        <div class="detail-value"><?= date('F j, Y', strtotime($job_order['preferred_date'])) ?></div>
                                                    </div>
                                                    <div class="detail-item">
                                                        <div class="detail-label"><i class="fas fa-clock"></i> TIME</div>
                                                        <div class="detail-value"><?= date('g:i A', strtotime($job_order['preferred_time'])) ?></div>
                                                    </div>
                                                    <div class="detail-item">
                                                        <div class="detail-label"><i class="fas fa-map-marker-alt"></i> LOCATION</div>
                                                        <div class="detail-value"><?= htmlspecialchars($job_order['property_address']) ?></div>
                                                    </div>
                                                    <div class="detail-item">
                                                        <div class="detail-label"><i class="fas fa-sync-alt"></i> FREQUENCY</div>
                                                        <div class="detail-value"><?= ucfirst(htmlspecialchars($job_order['frequency'])) ?></div>
                                                    </div>
                                                    <?php if (!empty($job_order['technician_name'])): ?>
                                                    <div class="detail-item technician-detail">
                                                        <div class="detail-label"><i class="fas fa-user-shield"></i> TECHNICIAN</div>
                                                        <div class="detail-value technician-info-card">
                                                            <div class="tech-avatar">
                                                                <?php if (!empty($job_order['technician_picture'])): ?>
                                                                <img src="../Admin Side/<?= htmlspecialchars($job_order['technician_picture']) ?>"
                                                                     alt="<?= htmlspecialchars($job_order['technician_name']) ?>"
                                                                     class="clickable-avatar"
                                                                     onclick="openImageViewer('../Admin Side/<?= htmlspecialchars($job_order['technician_picture']) ?>')"
                                                                     title="Click to view larger image">
                                                                <?php else: ?>
                                                                <i class="fas fa-user"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="tech-info">
                                                                <div class="tech-name">
                                                                    <?php if (!empty($job_order['technician_fname']) && !empty($job_order['technician_lname'])): ?>
                                                                    <?= htmlspecialchars($job_order['technician_fname'] . ' ' . $job_order['technician_lname']) ?>
                                                                    <?php else: ?>
                                                                    <?= htmlspecialchars($job_order['technician_name']) ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <?php if (!empty($job_order['technician_contact'])): ?>
                                                                <div class="tech-contact">
                                                                    <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($job_order['technician_contact']) ?>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="job-card-footer">
                                                    <div class="scheduled-date mb-2">
                                                        <i class="fas fa-exclamation-circle"></i> Was scheduled for: <?= date('F j, Y', strtotime($job_order['preferred_date'])) ?> at <?= date('g:i A', strtotime($job_order['preferred_time'])) ?>
                                                    </div>
                                                    <button class="btn-view-details w-100"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#inspectionModal"
                                                            data-report-id="<?= $job_order['report_id'] ?>">
                                                        <i class="fas fa-eye"></i> View Inspection Details
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- LOWER SECTION: Completed Treatments Section -->
                    <div class="job-section completed-section">
                        <div class="section-header">
                            <div class="d-flex align-items-center justify-content-between w-100">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle"></i>
                                    <h2>Completed Treatments</h2>
                                </div>
                                <button class="btn-collapse" type="button" data-bs-toggle="collapse" data-bs-target="#completedTreatmentsContent" aria-expanded="true" aria-controls="completedTreatmentsContent">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                            </div>
                        </div>

                        <div class="section-content collapse show" id="completedTreatmentsContent">
                            <?php if (empty($finished_jobs)): ?>
                                <div class="empty-section">
                                    <div class="empty-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <h4>No Completed Treatments</h4>
                                    <p class="text-muted">You don't have any completed treatments in your history yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($finished_jobs as $job_order): ?>
                                        <div class="col-12 col-sm-12 col-md-6 col-lg-4 col-xl-3 mb-2">
                                            <div class="compact-job-card">
                                                <div class="compact-job-header">
                                                    <h5><?= htmlspecialchars($job_order['type_of_work']) ?></h5>
                                                    <span class="status-badge status-finished">FINISHED</span>
                                                </div>
                                                <div class="compact-job-body">
                                                    <div class="compact-detail-item">
                                                        <div class="compact-detail-label"><i class="fas fa-hashtag"></i> JOB ID</div>
                                                        <div class="compact-detail-value">#<?= $job_order['job_order_id'] ?></div>
                                                    </div>
                                                    <div class="compact-detail-item">
                                                        <div class="compact-detail-label"><i class="fas fa-calendar"></i> DATE</div>
                                                        <div class="compact-detail-value"><?= date('F j, Y', strtotime($job_order['preferred_date'])) ?></div>
                                                    </div>
                                                    <div class="compact-detail-item">
                                                        <div class="compact-detail-label"><i class="fas fa-clock"></i> TIME</div>
                                                        <div class="compact-detail-value"><?= date('g:i A', strtotime($job_order['preferred_time'])) ?></div>
                                                    </div>
                                                    <div class="compact-detail-item">
                                                        <div class="compact-detail-label"><i class="fas fa-map-marker-alt"></i> LOCATION</div>
                                                        <div class="compact-detail-value text-truncate"><?= htmlspecialchars($job_order['property_address']) ?></div>
                                                    </div>
                                                    <?php if (!empty($job_order['technician_name'])): ?>
                                                    <div class="compact-detail-item technician-detail">
                                                        <div class="compact-detail-label"><i class="fas fa-user-shield"></i> TECHNICIAN</div>
                                                        <div class="compact-detail-value technician-info-card">
                                                            <div class="tech-avatar">
                                                                <?php if (!empty($job_order['technician_picture'])): ?>
                                                                <img src="../Admin Side/<?= htmlspecialchars($job_order['technician_picture']) ?>"
                                                                     alt="<?= htmlspecialchars($job_order['technician_name']) ?>"
                                                                     class="clickable-avatar"
                                                                     onclick="openImageViewer('../Admin Side/<?= htmlspecialchars($job_order['technician_picture']) ?>')"
                                                                     title="Click to view larger image">
                                                                <?php else: ?>
                                                                <i class="fas fa-user"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="tech-info">
                                                                <div class="tech-name">
                                                                    <?php if (!empty($job_order['technician_fname']) && !empty($job_order['technician_lname'])): ?>
                                                                    <?= htmlspecialchars($job_order['technician_fname'] . ' ' . $job_order['technician_lname']) ?>
                                                                    <?php else: ?>
                                                                    <?= htmlspecialchars($job_order['technician_name']) ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <?php if (!empty($job_order['technician_contact'])): ?>
                                                                <div class="tech-contact">
                                                                    <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($job_order['technician_contact']) ?>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <div class="scheduled-date mb-2">
                                                        <i class="fas fa-check-circle"></i>
                                                        <?php if (!empty($job_order['report_created_at'])): ?>
                                                            Completed on: <?= date('F j, Y', strtotime($job_order['report_created_at'])) ?>
                                                        <?php else: ?>
                                                            Completed on: <?= date('F j, Y', strtotime($job_order['preferred_date'])) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="d-flex flex-column gap-2">
                                                        <!-- Always show the View Job Report button for completed jobs -->
                                                        <button class="btn-view-details w-100 mb-2 <?= !empty($job_order['feedback_id']) ? 'btn-feedback-submitted' : 'btn-feedback-needed' ?>"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#jobOrderModal"
                                                                data-job-order-id="<?= $job_order['job_order_id'] ?>">
                                                            <i class="<?= !empty($job_order['feedback_id']) ? 'fas fa-star' : 'fas fa-comment-dots' ?>"></i>
                                                            <?= !empty($job_order['feedback_id']) ? 'View Details & Feedback' : 'Send Job Order Feedback' ?>
                                                        </button>
                                                        <button class="btn-view-details w-100 mb-2"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#jobReportModal"
                                                                data-job-order="<?= htmlspecialchars(json_encode($job_order)) ?>">
                                                            <i class="fas fa-file-alt"></i> View Job Report
                                                        </button>
                                                        <button class="btn-view-details w-100"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#inspectionModal"
                                                                data-report-id="<?= $job_order['report_id'] ?>">
                                                            <i class="fas fa-eye"></i> View Inspection Details
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
    </main>

    <?php include 'includes/job_order_procedure.php'; ?>

    <!-- Inspection Report Modal - Enhanced for mobile -->
    <div class="modal fade" id="inspectionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-clipboard-check me-2"></i>Inspection Report Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="inspectionModalContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3">Loading inspection report details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary w-100 w-md-auto" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Inspection Report Modal Styles */
        .technician-card {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .technician-header {
            color: #4285f4;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .technician-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .technician-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .technician-info h3 {
            font-size: 1.2rem;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .technician-contact {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #6c757d;
        }

        .inspection-section {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .inspection-header {
            color: #4285f4;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .inspection-field {
            margin-bottom: 15px;
        }

        .inspection-label {
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .inspection-value {
            font-size: 1rem;
        }

        .inspection-notes {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            white-space: pre-line;
        }
    </style>

    <!-- Job Order Report Modal - Enhanced for mobile -->
    <div class="modal fade" id="jobReportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-file-alt me-2"></i>Job Order Report</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="jobReportContent">
                        <div class="report-section mb-4">
                            <h4 class="report-section-title"><i class="fas fa-info-circle me-2"></i>Job Information</h4>
                            <div class="row g-3">
                                <div class="col-md-6 col-sm-12">
                                    <div class="report-field">
                                        <div class="report-field-label">Job Type</div>
                                        <div class="report-field-value" id="reportJobType"></div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-12">
                                    <div class="report-field">
                                        <div class="report-field-label">Job ID</div>
                                        <div class="report-field-value" id="reportJobId"></div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-12">
                                    <div class="report-field">
                                        <div class="report-field-label">Service Date</div>
                                        <div class="report-field-value" id="reportServiceDate"></div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-12">
                                    <div class="report-field">
                                        <div class="report-field-label">Location</div>
                                        <div class="report-field-value" id="reportLocation"></div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-12">
                                    <div class="report-field">
                                        <div class="report-field-label">Completion Date</div>
                                        <div class="report-field-value" id="reportCompletionDate"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Information Section -->
                        <div class="report-section mb-4" id="paymentSection">
                            <h4 class="report-section-title"><i class="fas fa-money-bill-wave me-2"></i>Payment Information</h4>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="report-field">
                                        <div class="report-field-label">Total Service Cost</div>
                                        <div class="report-field-value" id="reportServiceCost"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="report-field">
                                        <div class="report-field-label">Cost Per Visit</div>
                                        <div class="report-field-value" id="reportCostPerVisit"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="report-field">
                                        <div class="report-field-label">Payment Amount</div>
                                        <div class="report-field-value payment-amount" id="reportPaymentAmount"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="report-field">
                                        <div class="report-field-label">Payment Date</div>
                                        <div class="report-field-value" id="reportPaymentDate"></div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Technician Information Section -->
                        <div class="report-section mb-4" id="technicianSection">
                            <h4 class="report-section-title"><i class="fas fa-user-shield me-2"></i>Technician Information</h4>
                            <div class="technician-card" id="technicianCard">
                                <!-- Technician details will be populated by JavaScript -->
                            </div>
                        </div>

                        <div class="report-section mb-4">
                            <h4 class="report-section-title"><i class="fas fa-clipboard me-2"></i>Observation Notes</h4>
                            <div class="report-notes p-3 bg-light rounded" id="reportObservationNotes">
                                <!-- Observation notes will be inserted here -->
                            </div>
                        </div>

                        <div class="report-section mb-4">
                            <h4 class="report-section-title"><i class="fas fa-lightbulb me-2"></i>Recommendation</h4>
                            <div class="report-notes p-3 bg-light rounded" id="reportRecommendation">
                                <!-- Recommendation will be inserted here -->
                            </div>
                        </div>

                        <div class="report-section" id="reportAttachmentsSection">
                            <h4 class="report-section-title"><i class="fas fa-images me-2"></i>Attachments</h4>
                            <div class="report-attachments" id="reportAttachments">
                                <!-- Attachments will be inserted here -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-column flex-md-row">
                    <button type="button" class="btn btn-primary w-100 w-md-auto mb-2 mb-md-0" onclick="printJobReport()">
                        <i class="fas fa-print me-2"></i>Print Report
                    </button>
                    <button type="button" class="btn btn-success w-100 w-md-auto mb-2 mb-md-0" onclick="saveAsPDF()">
                        <i class="fas fa-file-pdf me-2"></i>Save as PDF
                    </button>
                    <button type="button" class="btn btn-secondary w-100 w-md-auto" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PDF Loading Overlay -->
    <div id="pdfLoadingOverlay" class="pdf-loading" style="display: none;">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="pdf-loading-text mt-3">Generating PDF...</div>
    </div>

    <style>
        /* Additional header fixes for all screen sizes */
        @media screen and (max-width: 768px) {
            /* Ensure header title is properly displayed */
            .header {
                padding-left: 55px !important; /* Make space for the menu toggle */
            }

            /* Ensure header title doesn't wrap */
            .header-title h1 {
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }
        }

        .payment-amount {
            font-weight: bold;
            color: #28a745;
        }

        /* Star Rating Styles */
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            cursor: pointer;
            font-size: 1.5rem;
            color: #ddd;
            transition: color 0.2s;
        }

        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #ffb700;
        }

        .rating-stars {
            font-size: 1.5rem;
        }

        /* Feedback Display Styles */
        .feedback-display {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e9ecef;
        }

        /* Technician Modal Header */
        .technician-modal-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .technician-modal-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e9ecef;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        /* Report Attachments */
        .report-attachments {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .report-attachment-container {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            cursor: pointer;
        }

        .report-attachment {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.2s;
        }

        .attachment-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
            color: white;
            font-size: 1.5rem;
        }

        .report-attachment-container:hover .report-attachment {
            transform: scale(1.05);
        }

        .report-attachment-container:hover .attachment-overlay {
            opacity: 1;
        }

        /* Empty state styling */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin: 20px auto;
            max-width: 800px;
            min-height: 60vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .empty-state .empty-icon {
            font-size: 70px;
            color: var(--primary-blue);
            margin-bottom: 25px;
            opacity: 0.3;
        }

        .empty-state h3 {
            color: var(--text-dark);
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .empty-state p {
            color: var(--text-muted);
            max-width: 600px;
            margin: 0 auto 20px;
            font-size: 1rem;
            line-height: 1.6;
        }

        /* Responsive styles for empty state */
        @media (max-width: 767px) {
            .empty-state {
                padding: 30px 15px;
                min-height: 50vh;
                margin: 10px auto;
            }

            .empty-state .empty-icon {
                font-size: 60px;
                margin-bottom: 20px;
            }

            .empty-state h3 {
                font-size: 1.3rem;
                margin-bottom: 12px;
            }

            .empty-state p {
                font-size: 0.95rem;
                margin-bottom: 15px;
            }

            .empty-state .btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }

        @media (max-width: 576px) {
            .empty-state {
                padding: 25px 12px;
                min-height: 40vh;
            }

            .empty-state .empty-icon {
                font-size: 50px;
                margin-bottom: 15px;
            }

            .empty-state h3 {
                font-size: 1.2rem;
                margin-bottom: 10px;
            }

            .empty-state p {
                font-size: 0.9rem;
                margin-bottom: 12px;
            }
        }

        /* Make modals wider */
        @media (min-width: 1200px) {
            .modal-xl {
                max-width: 1140px;
            }
        }

        /* Remove blue vertical element */
        body::before,
        body::after,
        .main-content::before,
        .main-content::after,
        .container::before,
        .container::after,
        .job-order-container::before,
        .job-order-container::after {
            display: none !important;
        }

        /* Remove any blue vertical elements but keep the sidebar */
        .job-order-sidebar,
        .blue-sidebar,
        .vertical-nav,
        .side-nav,
        .left-nav,
        .job-sidebar,
        div[class*="blue"]:not(#sidebar):not(.sidebar-header):not(.sidebar-menu):not(.sidebar-footer),
        div[id*="blue"]:not(#sidebar):not(.sidebar-header):not(.sidebar-menu):not(.sidebar-footer) {
            display: none !important;
        }

        /* Target specific blue element but not the sidebar */
        .job-order-container > div:first-child,
        .main-content > div:first-child:not(#sidebar),
        .container > div:first-child:not(#sidebar) {
            background-color: transparent !important;
            border-left: none !important;
        }

        /* Target the specific blue element in the image but not the sidebar */
        .job-order-container::before,
        .main-content::before,
        .container::before,
        body > div:first-child:not(#sidebar),
        body > div.blue:not(#sidebar),
        body > div[style*="background-color: #4285f4"]:not(#sidebar),
        body > div[style*="background-color: #3B82F6"]:not(#sidebar),
        body > div[style*="background-color: blue"]:not(#sidebar),
        body > div[style*="background: #4285f4"]:not(#sidebar),
        body > div[style*="background: #3B82F6"]:not(#sidebar),
        body > div[style*="background: blue"]:not(#sidebar) {
            display: none !important;
        }

        /* Override any dynamically added blue elements but not the sidebar */
        div[style*="background-color: #4285f4"]:not(#sidebar):not(.sidebar-header):not(.sidebar-menu):not(.sidebar-footer),
        div[style*="background-color: #3B82F6"]:not(#sidebar):not(.sidebar-header):not(.sidebar-menu):not(.sidebar-footer),
        div[style*="background-color: blue"]:not(#sidebar):not(.sidebar-header):not(.sidebar-menu):not(.sidebar-footer),
        div[style*="background: #4285f4"]:not(#sidebar):not(.sidebar-header):not(.sidebar-menu):not(.sidebar-footer),
        div[style*="background: #3B82F6"]:not(#sidebar):not(.sidebar-header):not(.sidebar-menu):not(.sidebar-footer),
        div[style*="background: blue"]:not(#sidebar):not(.sidebar-header):not(.sidebar-menu):not(.sidebar-footer) {
            display: none !important;
        }

        /* Target the specific blue vertical element */
        body > div.blue-vertical-element,
        .blue-vertical-element,
        .vertical-blue-bar,
        .blue-bar {
            display: none !important;
        }

        /* Target the blue corner element */
        .job-order-container::before,
        .job-order-container > div::before,
        .job-order-container > div > div::before,
        .job-order-container > div > div > div::before,
        .job-order-container > div > div > div > div::before,
        .job-order-container > div > div > div > div > div::before,
        .job-order-container > div > div > div > div > div > div::before,
        .job-order-container > div > div > div > div > div > div > div::before,
        .job-order-container > div > div > div > div > div > div > div > div::before,
        .job-order-container > div > div > div > div > div > div > div > div > div::before,
        .job-order-container > div > div > div > div > div > div > div > div > div > div::before {
            display: none !important;
        }

        /* Technician info card styles */
        .technician-info-card {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tech-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 0.8rem;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.1);
        }

        .tech-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .tech-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .tech-name {
            font-weight: 500;
            color: var(--primary-blue);
            line-height: 1.2;
        }

        .tech-contact {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .tech-contact i {
            font-size: 0.75rem;
            color: var(--primary-blue);
        }

        .technician-detail {
            border-top: 1px dashed rgba(0,0,0,0.1);
            padding-top: 8px;
            margin-top: 8px;
        }

        /* Clickable avatar styles */
        .clickable-avatar {
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }

        .clickable-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }

        /* Full-size image viewer styles */
        #imageViewerModal .modal-body {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #000;
            min-height: 300px;
        }

        #fullSizeImage {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
        }

        #fullSizeImage.profile-picture-view {
            max-height: 400px;
            max-width: 400px;
            border-radius: 5px;
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.2);
        }

        /* Job Report Styles */
        .report-section {
            margin-bottom: 2rem;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            background-color: white;
        }

        .report-section-title {
            padding: 1rem;
            margin: 0;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            font-size: 1.1rem;
            font-weight: 600;
            color: #495057;
            display: flex;
            align-items: center;
        }

        .report-section-title i {
            margin-right: 0.5rem;
            color: var(--primary-blue);
        }

        .report-field {
            margin-bottom: 1rem;
        }

        .report-field-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #6c757d;
            margin-bottom: 0.25rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .report-field-value {
            font-size: 1rem;
            color: #212529;
            font-weight: 500;
        }

        .report-notes {
            white-space: pre-line;
            line-height: 1.6;
            color: #212529;
        }

        .report-attachments {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }

        .attachment-item {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }

        .attachment-item:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .attachment-img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            display: block;
        }

        .attachment-caption {
            padding: 8px;
            font-size: 0.8rem;
            text-align: center;
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .no-attachments-message {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px dashed #dee2e6;
            width: 100%;
        }

        .no-attachments-message i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
            opacity: 0.5;
        }

        .no-attachments-message p {
            margin: 0;
            font-size: 1rem;
        }

        /* Print styles for job report */
        @media print {
            body * {
                visibility: hidden;
            }

            #jobReportModal,
            #jobReportModal * {
                visibility: visible;
            }

            #jobReportModal {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: visible;
            }

            #jobReportModal .modal-dialog {
                width: 100%;
                max-width: 100%;
                margin: 0;
                padding: 0;
            }

            #jobReportModal .modal-content {
                border: none;
                box-shadow: none;
            }

            #jobReportModal .modal-header,
            #jobReportModal .modal-footer {
                display: none;
            }

            .report-section {
                break-inside: avoid;
                page-break-inside: avoid;
                margin-bottom: 1.5rem;
                box-shadow: none;
                border: 1px solid #dee2e6;
            }
        }

        /* PDF generation styles */
        .pdf-loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .pdf-loading-text {
            margin-top: 1rem;
            font-size: 1.2rem;
            color: var(--primary-blue);
        }

        /* Ensure images in PDF are properly sized */
        #jobReportContent .attachment-img {
            max-width: 100%;
            height: auto;
        }

        /* Style for the PDF button */
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }

        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        /* No attachments message styling */
        .no-attachments-message {
            text-align: center;
            padding: 2rem;
            background-color: #f8f9fa;
            border-radius: 8px;
            color: #6c757d;
        }

        .no-attachments-message i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Hide any blue elements in the corner */
        .job-order-container > div:first-child > div:first-child,
        .job-order-container > div:first-child > div:first-child > div:first-child,
        .job-order-container > div:first-child > div:first-child > div:first-child > div:first-child,
        .job-order-container > div:first-child > div:first-child > div:first-child > div:first-child > div:first-child,
        .job-order-container > div:first-child > div:first-child > div:first-child > div:first-child > div:first-child > div:first-child,
        .job-order-container > div:first-child > div:first-child > div:first-child > div:first-child > div:first-child > div:first-child > div:first-child {
            background-color: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        /* Target the specific blue corner element shown in the image */
        .job-status-header::before,
        .job-status-header::after,
        .job-section::before,
        .job-section::after,
        .section-header::before,
        .section-header::after,
        .section-content::before,
        .section-content::after,
        .job-card::before,
        .job-card::after,
        .job-card-header::before,
        .job-card-header::after,
        .job-card-body::before,
        .job-card-body::after,
        .job-card-footer::before,
        .job-card-footer::after {
            display: none !important;
        }

        /* Hide any blue corner elements */
        [class*="corner"],
        [class*="blue-corner"],
        [class*="blue-element"],
        [id*="corner"],
        [id*="blue-corner"],
        [id*="blue-element"] {
            display: none !important;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jsPDF library for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/sidebar.js"></script>
    <!-- Fixed sidebar script -->
    <script src="js/sidebar-fix.js"></script>
    <script src="js/inspection_details_new.js"></script>
    <script src="js/job_order_feedback.js"></script>
    <script>
        // Helper function to calculate next job order date based on frequency
        function calculateNextJobDate(currentDate, frequency) {
            try {
                // Extract date part if it contains time
                let dateStr = currentDate;
                if (currentDate.includes(' at ')) {
                    dateStr = currentDate.split(' at ')[0];
                }

                const date = new Date(dateStr);

                // Check if date is valid
                if (isNaN(date.getTime())) {
                    return "N/A";
                }

                const freqLower = frequency.toLowerCase();

                if (freqLower.includes('weekly') || freqLower.includes('week')) {
                    if (freqLower.includes('bi') || freqLower.includes('bi-')) {
                        date.setDate(date.getDate() + 14);
                    } else {
                        date.setDate(date.getDate() + 7);
                    }
                } else if (freqLower.includes('month')) {
                    date.setMonth(date.getMonth() + 1);
                } else if (freqLower.includes('quarter')) {
                    date.setMonth(date.getMonth() + 3);
                } else if (freqLower.includes('semi') || freqLower.includes('6 month')) {
                    date.setMonth(date.getMonth() + 6);
                } else if (freqLower.includes('annual') || freqLower.includes('year')) {
                    date.setFullYear(date.getFullYear() + 1);
                } else if (freqLower.includes('one') || freqLower.includes('once') || freqLower.includes('one-time')) {
                    return "N/A (One-time service)";
                } else {
                    return "Based on contract";
                }

                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            } catch (e) {
                console.error("Error calculating next job date:", e);
                return "N/A";
            }
        }

        // Helper function to calculate cost per visit based on frequency
        function calculateCostPerVisit(totalCost, frequency) {
            try {
                if (!totalCost || isNaN(parseFloat(totalCost))) {
                    return null;
                }

                const cost = parseFloat(totalCost);
                const freqLower = (frequency || '').toLowerCase();

                // Determine number of visits based on frequency
                let numberOfVisits = 1; // Default for one-time

                if (freqLower.includes('weekly')) {
                    numberOfVisits = 52; // 52 weeks in a year
                } else if (freqLower.includes('month')) {
                    numberOfVisits = 12; // 12 months in a year
                } else if (freqLower.includes('quarter')) {
                    numberOfVisits = 4;  // 4 quarters in a year
                } else if (freqLower.includes('one') || freqLower.includes('once') || freqLower.includes('one-time')) {
                    numberOfVisits = 1;  // One-time service
                }

                // Calculate cost per visit
                return cost / numberOfVisits;
            } catch (e) {
                console.error("Error calculating cost per visit:", e);
                return null;
            }
        }

        // Function to add touch-friendly interactions
        function addTouchInteractions() {
            // Make cards more touch-friendly
            const jobCards = document.querySelectorAll('.job-card, .compact-job-card');
            jobCards.forEach(card => {
                // Add hover effect on touch for mobile
                card.addEventListener('touchstart', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 8px 16px rgba(0,0,0,0.1)';
                }, { passive: true });

                card.addEventListener('touchend', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                }, { passive: true });
            });

            // Make buttons more touch-friendly
            const buttons = document.querySelectorAll('.btn, .btn-view-details');
            buttons.forEach(button => {
                button.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.98)';
                }, { passive: true });

                button.addEventListener('touchend', function() {
                    this.style.transform = '';
                }, { passive: true });
            });

            // Improve modal scrolling on mobile
            const modalBodies = document.querySelectorAll('.modal-body');
            modalBodies.forEach(body => {
                body.style.webkitOverflowScrolling = 'touch';
            });
        }

        // Add debug logging for sidebar
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Job Order Report page loaded');

            // Add touch-friendly interactions for mobile
            addTouchInteractions();

            // Debug logging for sidebar
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');

            if (!sidebar) {
                console.error('Sidebar element not found in job_order_report.php');
            } else {
                console.log('Sidebar element found in job_order_report.php');
            }

            if (!menuToggle) {
                console.error('Menu toggle element not found in job_order_report.php');
            } else {
                console.log('Menu toggle element found in job_order_report.php');
            }

            // Job Report Modal
            const jobReportModal = document.getElementById('jobReportModal');
            if (jobReportModal) {
                jobReportModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const jobOrderData = JSON.parse(button.getAttribute('data-job-order'));

                    console.log('Job Order Data:', jobOrderData); // Debug log

                    // Fill in the job report details
                    document.getElementById('reportJobType').textContent = jobOrderData.type_of_work || 'N/A';
                    document.getElementById('reportJobId').textContent = '#' + jobOrderData.job_order_id;

                    // Format service date with error handling
                    try {
                        document.getElementById('reportServiceDate').textContent = new Date(jobOrderData.preferred_date + ' ' + jobOrderData.preferred_time).toLocaleString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: 'numeric',
                            minute: 'numeric',
                            hour12: true
                        });
                    } catch (e) {
                        document.getElementById('reportServiceDate').textContent = 'Date information unavailable';
                        console.error('Error formatting date:', e);
                    }

                    document.getElementById('reportLocation').textContent = jobOrderData.property_address || 'N/A';

                    // Payment information
                    const paymentSection = document.getElementById('paymentSection');

                    // Format currency
                    const formatCurrency = (amount) => {
                        return 'PHP ' + parseFloat(amount).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    };

                    // Display total service cost
                    document.getElementById('reportServiceCost').textContent = jobOrderData.cost ? formatCurrency(jobOrderData.cost) : 'N/A';

                    // Calculate and display cost per visit based on frequency
                    const costPerVisit = calculateCostPerVisit(jobOrderData.cost, jobOrderData.frequency);
                    document.getElementById('reportCostPerVisit').textContent = costPerVisit ? formatCurrency(costPerVisit) : 'N/A';

                    // Display payment amount
                    document.getElementById('reportPaymentAmount').textContent = "The Amount is Paid";

                    // Format payment date
                    if (jobOrderData.payment_date) {
                        try {
                            document.getElementById('reportPaymentDate').textContent = new Date(jobOrderData.payment_date).toLocaleString('en-US', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            });
                        } catch (e) {
                            document.getElementById('reportPaymentDate').textContent = 'Date information unavailable';
                            console.error('Error formatting payment date:', e);
                        }
                    } else {
                        document.getElementById('reportPaymentDate').textContent = 'N/A';
                    }

                    // Handle technician information section
                    const technicianSection = document.getElementById('technicianSection');
                    const technicianCard = document.getElementById('technicianCard');

                    if (jobOrderData.technician_id) {
                        // Show technician section
                        technicianSection.style.display = 'block';

                        // Determine technician picture
                        const technicianPicture = jobOrderData.technician_picture
                            ? '../Admin Side/' + jobOrderData.technician_picture
                            : '../Admin Side/uploads/technicians/default.png';

                        // Determine technician name
                        const technicianFullName = jobOrderData.technician_fname && jobOrderData.technician_lname
                            ? `${jobOrderData.technician_fname} ${jobOrderData.technician_lname}`
                            : jobOrderData.technician_name;

                        // Create technician card HTML
                        technicianCard.innerHTML = `
                            <div class="technician-profile">
                                <div class="technician-avatar">
                                    ${jobOrderData.technician_picture
                                        ? `<img src="${technicianPicture}" alt="${technicianFullName}" class="technician-avatar">`
                                        : `<i class="fas fa-user"></i>`}
                                </div>
                                <div class="technician-info">
                                    <div class="technician-name">${technicianFullName}</div>
                                    <div class="technician-username">@${jobOrderData.technician_name}</div>
                                    <div class="technician-contact">
                                        <i class="fas fa-phone"></i>
                                        ${jobOrderData.technician_contact || 'No contact information available'}
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        // Hide technician section if no technician assigned
                        technicianSection.style.display = 'none';
                    }

                    // Format completion date
                    const completionDate = jobOrderData.report_created_at
                        ? new Date(jobOrderData.report_created_at).toLocaleString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: 'numeric',
                            minute: 'numeric',
                            hour12: true
                          })
                        : 'N/A';
                    document.getElementById('reportCompletionDate').textContent = completionDate;

                    // Set observation notes with fallback message
                    const observationNotesElement = document.getElementById('reportObservationNotes');
                    if (jobOrderData.observation_notes && jobOrderData.observation_notes.trim() !== '') {
                        observationNotesElement.textContent = jobOrderData.observation_notes;
                    } else if (jobOrderData.status === 'completed') {
                        observationNotesElement.textContent = 'This job has been completed, but no observation notes were provided.';
                    } else {
                        observationNotesElement.textContent = 'No observation notes available.';
                    }

                    // Set recommendation with fallback message
                    const recommendationElement = document.getElementById('reportRecommendation');
                    if (jobOrderData.recommendation && jobOrderData.recommendation.trim() !== '') {
                        recommendationElement.textContent = jobOrderData.recommendation;
                    } else if (jobOrderData.status === 'completed') {
                        recommendationElement.textContent = 'This job has been completed, but no recommendation was provided.';
                    } else {
                        recommendationElement.textContent = 'No recommendation available.';
                    }

                    // Store chemical usage data for PDF generation
                    if (jobOrderData.chemical_usage) {
                        console.log('Chemical usage data found:', jobOrderData.chemical_usage);
                    } else {
                        console.log('No chemical usage data found for this job order');
                    }

                    // Handle attachments
                    const attachmentsSection = document.getElementById('reportAttachmentsSection');
                    const attachmentsContainer = document.getElementById('reportAttachments');

                    if (jobOrderData.report_attachments && jobOrderData.report_attachments.trim() !== '') {
                        attachmentsSection.style.display = 'block';
                        attachmentsContainer.innerHTML = '';

                        try {
                            const attachments = jobOrderData.report_attachments.split(',');
                            let hasValidAttachments = false;

                            attachments.forEach(attachment => {
                                if (attachment.trim()) {
                                    hasValidAttachments = true;
                                    const attachmentItem = document.createElement('div');
                                    attachmentItem.className = 'attachment-item';

                                    const attachmentPath = '../uploads/' + attachment.trim();

                                    // Create clickable container instead of a link
                                    const link = document.createElement('div');
                                    link.style.cursor = 'pointer';
                                    link.onclick = function() {
                                        openImageViewer(attachmentPath);
                                    };
                                    link.title = 'Click to view larger image';

                                    const img = document.createElement('img');
                                    img.src = attachmentPath;
                                    img.alt = 'Attachment';
                                    img.className = 'attachment-img';

                                    // Add loading and error handling
                                    img.onerror = function() {
                                        this.src = '../assets/img/image-not-found.png';
                                        this.alt = 'Image not found';
                                    };

                                    // Add caption with filename
                                    const caption = document.createElement('div');
                                    caption.className = 'attachment-caption';
                                    caption.textContent = attachment.trim().split('/').pop();

                                    link.appendChild(img);
                                    link.appendChild(caption);
                                    attachmentItem.appendChild(link);
                                    attachmentsContainer.appendChild(attachmentItem);
                                }
                            });

                            // If no valid attachments were found, display a message
                            if (!hasValidAttachments) {
                                displayNoAttachmentsMessage(attachmentsContainer);
                            }
                        } catch (e) {
                            console.error('Error processing attachments:', e);
                            displayNoAttachmentsMessage(attachmentsContainer);
                        }
                    } else {
                        attachmentsSection.style.display = 'block'; // Still show the section
                        displayNoAttachmentsMessage(attachmentsContainer);
                    }

                    // Helper function to display no attachments message
                    function displayNoAttachmentsMessage(container) {
                        container.innerHTML = '';
                        const noAttachmentsMsg = document.createElement('div');
                        noAttachmentsMsg.className = 'no-attachments-message';
                        noAttachmentsMsg.innerHTML = '<i class="fas fa-image text-muted"></i><p>No attachments available for this job report.</p>';
                        container.appendChild(noAttachmentsMsg);
                    }
                });
            }

            // Function to print the job order report - now uses the same PDF content as saveAsPDF
            window.printJobReport = function() {
                // Get the job order data from the modal
                const button = document.querySelector('[data-bs-target="#jobReportModal"]');
                const jobOrderData = button ? JSON.parse(button.getAttribute('data-job-order')) : null;

                // Get report content and data
                const jobId = document.getElementById('reportJobId').textContent;
                const jobType = document.getElementById('reportJobType').textContent;
                const serviceDate = document.getElementById('reportServiceDate').textContent;
                const location = document.getElementById('reportLocation').textContent;
                const completionDate = document.getElementById('reportCompletionDate').textContent;
                const observationNotes = document.getElementById('reportObservationNotes').textContent;
                const recommendation = document.getElementById('reportRecommendation').textContent;
                const serviceCost = document.getElementById('reportServiceCost').textContent;

                // Get chemical usage data from the job order data
                let chemicalUsageData = null;
                if (jobOrderData && jobOrderData.chemical_usage) {
                    try {
                        chemicalUsageData = JSON.parse(jobOrderData.chemical_usage);
                        console.log('Chemical usage data parsed:', chemicalUsageData);
                    } catch (e) {
                        console.error('Error parsing chemical usage data:', e);
                    }
                }

                // Try to find frequency from the job order details
                let frequency = "One-time";
                const detailItems = document.querySelectorAll('.detail-item, .compact-detail-item');
                detailItems.forEach(item => {
                    const label = item.querySelector('.detail-label, .compact-detail-label');
                    const value = item.querySelector('.detail-value, .compact-detail-value');
                    if (label && value && label.textContent.includes('FREQUENCY')) {
                        frequency = value.textContent.trim();
                    }
                });

                // Get client info from the job order data if available, otherwise from session
                let clientName = jobOrderData && jobOrderData.client_name ? jobOrderData.client_name : "<?= htmlspecialchars($_SESSION['fullname'] ?? '') ?>";
                let clientPhone = jobOrderData && jobOrderData.contact_number ? jobOrderData.contact_number : "<?= htmlspecialchars($_SESSION['contact_number'] ?? '') ?>";
                let clientEmail = jobOrderData && jobOrderData.client_email ? jobOrderData.client_email : "<?= htmlspecialchars($_SESSION['email'] ?? '') ?>";
                let clientAddress = location || "N/A";

                // Get technician info if available
                let technicianName = "N/A";
                let technicianContact = "N/A";
                const technicianCard = document.getElementById('technicianCard');
                if (technicianCard) {
                    const techNameEl = technicianCard.querySelector('.technician-name');
                    const techContactEl = technicianCard.querySelector('.technician-contact');
                    if (techNameEl) technicianName = techNameEl.textContent.trim();
                    if (techContactEl) technicianContact = techContactEl.textContent.trim();
                }

                // Calculate next job order date based on frequency
                const nextJobDate = calculateNextJobDate(completionDate, frequency);

                // Show loading overlay
                const loadingOverlay = document.getElementById('pdfLoadingOverlay');
                loadingOverlay.style.display = 'flex';

                // Show loading indicator on button
                const printBtn = document.querySelector('.btn-primary');
                const originalBtnText = printBtn.innerHTML;
                printBtn.disabled = true;
                printBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating PDF...';

                // Set PDF filename
                const filename = `Maintenance_Work_Order_${jobId.replace('#', '')}.pdf`;

                try {
                    // Initialize jsPDF
                    const { jsPDF } = window.jspdf;
                    const pdf = new jsPDF('p', 'mm', 'a4');

                    // Define blue color for headers and lines
                    const blueColor = [0, 83, 156]; // RGB for blue

                    // Set up page dimensions
                    const pageWidth = 210;
                    const pageHeight = 297;
                    const margin = 15;
                    const contentWidth = pageWidth - (margin * 2);

                    // Add title
                    pdf.setFontSize(16);
                    pdf.setFont('helvetica', 'bold');
                    pdf.setTextColor(blueColor[0], blueColor[1], blueColor[2]); // Set blue color for title
                    pdf.text('Maintenance Work Order Form', pageWidth / 2, margin + 5, { align: 'center' });

                    // Current Y position tracker
                    let yPos = margin + 15;

                    // Helper function to create a table cell
                    function createTableCell(x, y, width, height, text, isHeader = false) {
                        // Draw cell border with blue color
                        pdf.setDrawColor(blueColor[0], blueColor[1], blueColor[2]);
                        pdf.setLineWidth(0.3);
                        pdf.rect(x, y, width, height);

                        // Add text
                        pdf.setFontSize(isHeader ? 11 : 10);
                        pdf.setFont('helvetica', isHeader ? 'bold' : 'normal');

                        // Set text color - blue for headers, black for regular text
                        if (isHeader) {
                            pdf.setTextColor(blueColor[0], blueColor[1], blueColor[2]);
                        } else {
                            pdf.setTextColor(0, 0, 0);
                        }

                        // Calculate text position (with padding)
                        const textX = x + 2;
                        const textY = y + (isHeader ? 5 : 5);

                        pdf.text(text || '', textX, textY);
                    }

                    // Helper function to create a section header
                    function createSectionHeader(x, y, width, text) {
                        pdf.setFillColor(230, 240, 250); // Light blue background
                        pdf.rect(x, y, width, 7, 'F');

                        pdf.setFontSize(11);
                        pdf.setFont('helvetica', 'bold');
                        pdf.setTextColor(blueColor[0], blueColor[1], blueColor[2]); // Blue text color
                        pdf.text(text, x + 2, y + 5);

                        return y + 7; // Return the new Y position
                    }

                    // 1. Client/Worksite Details Section
                    yPos = createSectionHeader(margin, yPos, contentWidth, 'Client/Worksite Details');

                    // Client Name and Phone cells
                    createTableCell(margin, yPos, contentWidth/2, 10, 'Client Name:', true);
                    createTableCell(margin + contentWidth/2, yPos, contentWidth/2, 10, 'Client Phone Numb:', true);
                    yPos += 10;

                    // Client Address and Email cells
                    createTableCell(margin, yPos, contentWidth/2, 10, 'Client Address:', true);
                    createTableCell(margin + contentWidth/2, yPos, contentWidth/2, 10, 'Client Email:', true);
                    yPos += 10;

                    // Fill in client details with better positioning to avoid collisions
                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(10);
                    pdf.setTextColor(0, 0, 0);

                    // Client Name - position after the label text
                    pdf.text(clientName, margin + 30, yPos - 13);

                    // Client Phone - position after the label text with fallback
                    if (clientPhone && clientPhone.trim() !== '') {
                        // Format phone number if needed
                        const formattedPhone = clientPhone.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
                        pdf.text(formattedPhone, margin + contentWidth/2 + 40, yPos - 13);
                    } else {
                        pdf.text("N/A", margin + contentWidth/2 + 40, yPos - 13);
                    }

                    // Client Address - use text wrapping to avoid overflow
                    const addressLines = pdf.splitTextToSize(clientAddress, contentWidth/2 - 40);
                    if (addressLines.length > 0) {
                        pdf.text(addressLines[0], margin + 35, yPos - 3);
                    }

                    // Client Email - position after the label text
                    const emailLines = pdf.splitTextToSize(clientEmail, contentWidth/2 - 35);
                    if (emailLines.length > 0) {
                        pdf.text(emailLines[0], margin + contentWidth/2 + 30, yPos - 3);
                    }

                    // Add some spacing
                    yPos += 5;

                    // 2. Order Details Section
                    yPos = createSectionHeader(margin, yPos, contentWidth, 'Order Details');

                    // Date Issued and Work Order Number cells
                    createTableCell(margin, yPos, contentWidth/2, 10, 'Date Issued:', true);
                    createTableCell(margin + contentWidth/2, yPos, contentWidth/2, 10, 'Work Order Number:', true);
                    yPos += 10;

                    // Issued By and Work Performed By cells
                    createTableCell(margin, yPos, contentWidth/2, 10, 'Issued By:', true);
                    createTableCell(margin + contentWidth/2, yPos, contentWidth/2, 10, 'Work Performed By:', true);
                    yPos += 10;

                    // Fill in order details
                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(10);
                    pdf.setTextColor(0, 0, 0);
                    pdf.text(serviceDate.split(' at ')[0], margin + 25, yPos - 13);
                    pdf.text(jobId, margin + contentWidth/2 + 45, yPos - 13);
                    pdf.text("Admin", margin + 25, yPos - 3);
                    pdf.text(technicianName, margin + contentWidth/2 + 45, yPos - 3);

                    // 3. Description of Work Required (replaced with Observation and Recommendation)
                    yPos += 5;
                    yPos = createSectionHeader(margin, yPos, contentWidth, 'Observation and Recommendation:');

                    // Create a larger cell for the description
                    createTableCell(margin, yPos, contentWidth, 40, '', false);

                    // Add the observation and recommendation text
                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(9);

                    // Format and add the text with word wrapping
                    const textOptions = {
                        align: 'left',
                        maxWidth: contentWidth - 4
                    };

                    // Add observation notes
                    pdf.text('Observation:', margin + 2, yPos + 5);
                    const observationLines = pdf.splitTextToSize(observationNotes, contentWidth - 8);
                    if (observationLines.length > 0) {
                        pdf.text(observationLines, margin + 2, yPos + 10, textOptions);
                    }

                    // Add recommendation
                    pdf.text('Recommendation:', margin + 2, yPos + 25);
                    const recommendationLines = pdf.splitTextToSize(recommendation, contentWidth - 8);
                    if (recommendationLines.length > 0) {
                        pdf.text(recommendationLines, margin + 2, yPos + 30, textOptions);
                    }

                    yPos += 40;

                    // 4. Completion Information (removed Material Required section)
                    yPos += 5;
                    yPos = createSectionHeader(margin, yPos, contentWidth, 'Completion Information');

                    // Date Completed and Next Job Order Date cells
                    createTableCell(margin, yPos, contentWidth/2, 10, 'Date Completed:', true);
                    createTableCell(margin + contentWidth/2, yPos, contentWidth/2, 10, 'Next Job Order Date:', true);
                    yPos += 10;

                    // Fill in completion date and next job date
                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(10);
                    pdf.setTextColor(0, 0, 0);
                    pdf.text(completionDate.split(' at ')[0], margin + 35, yPos - 3);
                    pdf.text(nextJobDate, margin + contentWidth/2 + 45, yPos - 3);

                    // Chemical Used cell
                    createTableCell(margin, yPos, contentWidth, 25, 'Chemical Used:', true);

                    // Display chemical names from the parsed chemical usage data
                    try {
                        let chemicalNames = [];

                        // Use the previously parsed chemicalUsageData
                        if (chemicalUsageData && Array.isArray(chemicalUsageData)) {
                            // Extract just the chemical names
                            chemicalNames = chemicalUsageData.map(chem => chem.name || 'Unknown Chemical');
                        }

                        // Display chemical names if available
                        if (chemicalNames.length > 0) {
                            pdf.setFont('helvetica', 'normal');
                            pdf.setFontSize(10);
                            pdf.setTextColor(0, 0, 0);

                            // Format the chemical names with proper spacing
                            const chemicalText = chemicalNames.join(', ');
                            const chemicalLines = pdf.splitTextToSize(chemicalText, contentWidth - 10);

                            if (chemicalLines.length > 0) {
                                pdf.text(chemicalLines, margin + 5, yPos + 10);
                            }
                        } else {
                            pdf.setFont('helvetica', 'italic');
                            pdf.setFontSize(10);
                            pdf.setTextColor(100, 100, 100);
                            pdf.text('No chemicals recorded', margin + 5, yPos + 10);
                        }
                    } catch (e) {
                        console.error('Error processing chemical data:', e);
                        pdf.setFont('helvetica', 'italic');
                        pdf.setFontSize(10);
                        pdf.setTextColor(100, 100, 100);
                        pdf.text('No chemicals recorded', margin + 5, yPos + 10);
                    }

                    yPos += 25;

                    // Add footer with company information
                    pdf.setFontSize(8);
                    pdf.setTextColor(100, 100, 100);
                    pdf.text('MacJ Pest Control Services', pageWidth / 2, pageHeight - 10, { align: 'center' });

                    // Open the PDF in a new window/tab for printing
                    const pdfOutput = pdf.output('bloburl');
                    window.open(pdfOutput, '_blank');

                    // Hide loading overlay
                    loadingOverlay.style.display = 'none';

                    // Restore button state
                    printBtn.disabled = false;
                    printBtn.innerHTML = originalBtnText;
                } catch (error) {
                    console.error('Error generating PDF for print:', error);

                    // Hide loading overlay
                    loadingOverlay.style.display = 'none';

                    // Restore button state
                    printBtn.disabled = false;
                    printBtn.innerHTML = originalBtnText;

                    // Show error message
                    alert('Error generating PDF for print. Please try again.');
                }
            };

            // Function to save the job order report as PDF
            window.saveAsPDF = function() {
                // Get the job order data from the modal
                const button = document.querySelector('[data-bs-target="#jobReportModal"]');
                const jobOrderData = button ? JSON.parse(button.getAttribute('data-job-order')) : null;

                // Get report content and data
                const jobId = document.getElementById('reportJobId').textContent;
                const jobType = document.getElementById('reportJobType').textContent;
                const serviceDate = document.getElementById('reportServiceDate').textContent;
                const location = document.getElementById('reportLocation').textContent;
                const completionDate = document.getElementById('reportCompletionDate').textContent;
                const observationNotes = document.getElementById('reportObservationNotes').textContent;
                const recommendation = document.getElementById('reportRecommendation').textContent;
                const serviceCost = document.getElementById('reportServiceCost').textContent;

                // Get chemical usage data from the job order data
                let chemicalUsageData = null;
                if (jobOrderData && jobOrderData.chemical_usage) {
                    try {
                        chemicalUsageData = JSON.parse(jobOrderData.chemical_usage);
                        console.log('Chemical usage data parsed:', chemicalUsageData);
                    } catch (e) {
                        console.error('Error parsing chemical usage data:', e);
                    }
                }

                // Try to find frequency from the job order details
                let frequency = "One-time";
                const detailItems = document.querySelectorAll('.detail-item, .compact-detail-item');
                detailItems.forEach(item => {
                    const label = item.querySelector('.detail-label, .compact-detail-label');
                    const value = item.querySelector('.detail-value, .compact-detail-value');
                    if (label && value && label.textContent.includes('FREQUENCY')) {
                        frequency = value.textContent.trim();
                    }
                });

                // Get client info from the job order data if available, otherwise from session
                let clientName = jobOrderData && jobOrderData.client_name ? jobOrderData.client_name : "<?= htmlspecialchars($_SESSION['fullname'] ?? '') ?>";
                let clientPhone = jobOrderData && jobOrderData.contact_number ? jobOrderData.contact_number : "<?= htmlspecialchars($_SESSION['contact_number'] ?? '') ?>";
                let clientEmail = jobOrderData && jobOrderData.client_email ? jobOrderData.client_email : "<?= htmlspecialchars($_SESSION['email'] ?? '') ?>";
                let clientAddress = location || "N/A";

                // Debug client info
                console.log('Client Info:', {
                    name: clientName,
                    phone: clientPhone,
                    email: clientEmail,
                    address: clientAddress
                });

                // Get technician info if available
                let technicianName = "N/A";
                let technicianContact = "N/A";
                const technicianCard = document.getElementById('technicianCard');
                if (technicianCard) {
                    const techNameEl = technicianCard.querySelector('.technician-name');
                    const techContactEl = technicianCard.querySelector('.technician-contact');
                    if (techNameEl) technicianName = techNameEl.textContent.trim();
                    if (techContactEl) technicianContact = techContactEl.textContent.trim();
                }

                // Get the next job date based on completion date and frequency
                const nextJobDate = calculateNextJobDate(completionDate, frequency);

                // Show loading overlay
                const loadingOverlay = document.getElementById('pdfLoadingOverlay');
                loadingOverlay.style.display = 'flex';

                // Show loading indicator on button
                const saveBtn = document.querySelector('.btn-success');
                const originalBtnText = saveBtn.innerHTML;
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating PDF...';

                // Set PDF filename
                const filename = `Maintenance_Work_Order_${jobId.replace('#', '')}.pdf`;

                try {
                    // Initialize jsPDF
                    const { jsPDF } = window.jspdf;
                    const pdf = new jsPDF('p', 'mm', 'a4');

                    // Define blue color for headers and lines
                    const blueColor = [0, 83, 156]; // RGB for blue

                    // Set up page dimensions
                    const pageWidth = 210;
                    const pageHeight = 297;
                    const margin = 15;
                    const contentWidth = pageWidth - (margin * 2);

                    // Add title
                    pdf.setFontSize(16);
                    pdf.setFont('helvetica', 'bold');
                    pdf.setTextColor(blueColor[0], blueColor[1], blueColor[2]); // Set blue color for title
                    pdf.text('Maintenance Work Order Form', pageWidth / 2, margin + 5, { align: 'center' });

                    // Current Y position tracker
                    let yPos = margin + 15;

                    // Helper function to create a table cell
                    function createTableCell(x, y, width, height, text, isHeader = false) {
                        // Draw cell border with blue color
                        pdf.setDrawColor(blueColor[0], blueColor[1], blueColor[2]);
                        pdf.setLineWidth(0.3);
                        pdf.rect(x, y, width, height);

                        // Add text
                        pdf.setFontSize(isHeader ? 11 : 10);
                        pdf.setFont('helvetica', isHeader ? 'bold' : 'normal');

                        // Set text color - blue for headers, black for regular text
                        if (isHeader) {
                            pdf.setTextColor(blueColor[0], blueColor[1], blueColor[2]);
                        } else {
                            pdf.setTextColor(0, 0, 0);
                        }

                        // Calculate text position (with padding)
                        const textX = x + 2;
                        const textY = y + (isHeader ? 5 : 5);

                        pdf.text(text || '', textX, textY);
                    }

                    // Helper function to create a section header
                    function createSectionHeader(x, y, width, text) {
                        pdf.setFillColor(230, 240, 250); // Light blue background
                        pdf.rect(x, y, width, 7, 'F');

                        pdf.setFontSize(11);
                        pdf.setFont('helvetica', 'bold');
                        pdf.setTextColor(blueColor[0], blueColor[1], blueColor[2]); // Blue text color
                        pdf.text(text, x + 2, y + 5);

                        return y + 7; // Return the new Y position
                    }

                    // 1. Client/Worksite Details Section
                    yPos = createSectionHeader(margin, yPos, contentWidth, 'Client/Worksite Details');

                    // Client Name and Phone cells
                    createTableCell(margin, yPos, contentWidth/2, 10, 'Client Name:', true);
                    createTableCell(margin + contentWidth/2, yPos, contentWidth/2, 10, 'Client Phone Numb:', true);
                    yPos += 10;

                    // Client Address and Email cells
                    createTableCell(margin, yPos, contentWidth/2, 10, 'Client Address:', true);
                    createTableCell(margin + contentWidth/2, yPos, contentWidth/2, 10, 'Client Email:', true);
                    yPos += 10;

                    // Fill in client details with better positioning to avoid collisions
                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(10);
                    pdf.setTextColor(0, 0, 0);

                    // Client Name - position after the label text
                    pdf.text(clientName, margin + 30, yPos - 13);

                    // Client Phone - position after the label text with fallback
                    if (clientPhone && clientPhone.trim() !== '') {
                        // Format phone number if needed
                        const formattedPhone = clientPhone.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
                        pdf.text(formattedPhone, margin + contentWidth/2 + 40, yPos - 13);
                    } else {
                        pdf.text("N/A", margin + contentWidth/2 + 40, yPos - 13);
                    }

                    // Client Address - use text wrapping to avoid overflow
                    const addressLines = pdf.splitTextToSize(clientAddress, contentWidth/2 - 40);
                    if (addressLines.length > 0) {
                        pdf.text(addressLines[0], margin + 35, yPos - 3);
                    }

                    // Client Email - position after the label text
                    const emailLines = pdf.splitTextToSize(clientEmail, contentWidth/2 - 35);
                    if (emailLines.length > 0) {
                        pdf.text(emailLines[0], margin + contentWidth/2 + 30, yPos - 3);
                    }

                    // Add some spacing
                    yPos += 5;

                    // 2. Order Details Section
                    yPos = createSectionHeader(margin, yPos, contentWidth, 'Order Details');

                    // Date Issued and Work Order Number cells
                    createTableCell(margin, yPos, contentWidth/2, 10, 'Date Issued:', true);
                    createTableCell(margin + contentWidth/2, yPos, contentWidth/2, 10, 'Work Order Number:', true);
                    yPos += 10;

                    // Issued By and Work Performed By cells
                    createTableCell(margin, yPos, contentWidth/2, 10, 'Issued By:', true);
                    createTableCell(margin + contentWidth/2, yPos, contentWidth/2, 10, 'Work Performed By:', true);
                    yPos += 10;

                    // Fill in order details
                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(10);
                    pdf.setTextColor(0, 0, 0);
                    pdf.text(serviceDate.split(' at ')[0], margin + 25, yPos - 13);
                    pdf.text(jobId, margin + contentWidth/2 + 45, yPos - 13);
                    pdf.text("Admin", margin + 25, yPos - 3);
                    pdf.text(technicianName, margin + contentWidth/2 + 45, yPos - 3);

                    // 3. Description of Work Required (replaced with Observation and Recommendation)
                    yPos += 5;
                    yPos = createSectionHeader(margin, yPos, contentWidth, 'Observation and Recommendation:');

                    // Create a larger cell for the description
                    createTableCell(margin, yPos, contentWidth, 40, '', false);

                    // Add the observation and recommendation text
                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(9);

                    // Format and add the text with word wrapping
                    const textOptions = {
                        align: 'left',
                        maxWidth: contentWidth - 4
                    };

                    // Add observation notes
                    pdf.text('Observation:', margin + 2, yPos + 5);
                    const observationLines = pdf.splitTextToSize(observationNotes, contentWidth - 8);
                    if (observationLines.length > 0) {
                        pdf.text(observationLines, margin + 2, yPos + 10, textOptions);
                    }

                    // Add recommendation
                    pdf.text('Recommendation:', margin + 2, yPos + 25);
                    const recommendationLines = pdf.splitTextToSize(recommendation, contentWidth - 8);
                    if (recommendationLines.length > 0) {
                        pdf.text(recommendationLines, margin + 2, yPos + 30, textOptions);
                    }

                    yPos += 40;

                    // 4. Completion Information (removed Material Required section)
                    yPos += 5;
                    yPos = createSectionHeader(margin, yPos, contentWidth, 'Completion Information');

                    // Date Completed and Next Job Order Date cells
                    createTableCell(margin, yPos, contentWidth/2, 10, 'Date Completed:', true);
                    createTableCell(margin + contentWidth/2, yPos, contentWidth/2, 10, 'Next Job Order Date:', true);
                    yPos += 10;

                    // Fill in completion date and next job date
                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(10);
                    pdf.setTextColor(0, 0, 0);
                    pdf.text(completionDate.split(' at ')[0], margin + 35, yPos - 3);
                    pdf.text(nextJobDate, margin + contentWidth/2 + 45, yPos - 3);

                    // Chemical Used cell
                    createTableCell(margin, yPos, contentWidth, 25, 'Chemical Used:', true);

                    // Display chemical names from the parsed chemical usage data
                    try {
                        let chemicalNames = [];

                        // Use the previously parsed chemicalUsageData
                        if (chemicalUsageData && Array.isArray(chemicalUsageData)) {
                            // Extract just the chemical names
                            chemicalNames = chemicalUsageData.map(chem => chem.name || 'Unknown Chemical');
                        }

                        // Display chemical names if available
                        if (chemicalNames.length > 0) {
                            pdf.setFont('helvetica', 'normal');
                            pdf.setFontSize(10);
                            pdf.setTextColor(0, 0, 0);

                            // Format the chemical names with proper spacing
                            const chemicalText = chemicalNames.join(', ');
                            const chemicalLines = pdf.splitTextToSize(chemicalText, contentWidth - 10);

                            if (chemicalLines.length > 0) {
                                pdf.text(chemicalLines, margin + 5, yPos + 10);
                            }
                        } else {
                            pdf.setFont('helvetica', 'italic');
                            pdf.setFontSize(10);
                            pdf.setTextColor(100, 100, 100);
                            pdf.text('No chemicals recorded', margin + 5, yPos + 10);
                        }
                    } catch (e) {
                        console.error('Error processing chemical data:', e);
                        pdf.setFont('helvetica', 'italic');
                        pdf.setFontSize(10);
                        pdf.setTextColor(100, 100, 100);
                        pdf.text('No chemicals recorded', margin + 5, yPos + 10);
                    }

                    yPos += 25;

                    // Add footer with company information
                    pdf.setFontSize(8);
                    pdf.setTextColor(100, 100, 100);
                    pdf.text('MacJ Pest Control Services', pageWidth / 2, pageHeight - 10, { align: 'center' });

                    // Save the PDF
                    pdf.save(filename);

                    // Hide loading overlay
                    loadingOverlay.style.display = 'none';

                    // Restore button state
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalBtnText;

                    // Show success message
                    alert('PDF has been generated successfully!');
                } catch (error) {
                    console.error('Error generating PDF:', error);

                    // Hide loading overlay
                    loadingOverlay.style.display = 'none';

                    // Restore button state
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalBtnText;

                    // Show error message
                    alert('Error generating PDF. Please try again.');
                }
            };
        });
    </script>
    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>

    <!-- Full-size Image Viewer Modal -->
    <div class="modal fade" id="imageViewerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Image Viewer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 text-center">
                    <img id="fullSizeImage" src="" alt="Full-size Image" style="max-width: 100%; max-height: 80vh;">
                </div>
                <div class="modal-footer">
                    <a id="downloadImageLink" href="#" download class="btn btn-primary">
                        <i class="fas fa-download"></i> Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PDF Generation Loading Overlay -->
    <div id="pdfLoadingOverlay" class="pdf-loading" style="display: none;">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="pdf-loading-text">Generating PDF, please wait...</div>
    </div>

    <script>
        /**
         * Open the image viewer modal with the specified image
         * @param {string} imageSrc - The source URL of the image to display
         */
        function openImageViewer(imageSrc) {
            const fullSizeImage = document.getElementById('fullSizeImage');
            const downloadLink = document.getElementById('downloadImageLink');
            const modalTitle = document.querySelector('#imageViewerModal .modal-title');

            if (fullSizeImage && downloadLink) {
                // Set the image source
                fullSizeImage.src = imageSrc;

                // Set the download link
                downloadLink.href = imageSrc;

                // Extract filename from path for the download attribute
                const filename = imageSrc.substring(imageSrc.lastIndexOf('/') + 1);
                downloadLink.setAttribute('download', filename);

                // Determine if this is a profile picture or an attachment
                const isProfilePic = imageSrc.includes('technicians');

                // Update modal title based on image type
                if (isProfilePic) {
                    modalTitle.innerHTML = '<i class="fas fa-user-circle me-2"></i>Technician Profile Picture';

                    // Add profile picture specific styling
                    fullSizeImage.classList.add('profile-picture-view');
                } else {
                    modalTitle.innerHTML = '<i class="fas fa-image me-2"></i>Inspection Image';
                    fullSizeImage.classList.remove('profile-picture-view');
                }

                // Show the modal
                const imageViewerModal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
                imageViewerModal.show();

                // Handle image load event to adjust modal size
                fullSizeImage.onload = function() {
                    // Force modal to recalculate its position
                    setTimeout(() => {
                        window.dispatchEvent(new Event('resize'));
                    }, 200);
                };
            }
        }
    </script>

    <!-- Job Order Modal -->
    <div class="modal fade" id="jobOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Job Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="jobOrderModalContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading job order details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <!-- Floating Action Button Script -->
    <script src="js/floating-action-button.js"></script>
    <script>
        // Initialize collapse functionality and search
        document.addEventListener('DOMContentLoaded', function() {
            // Define the section IDs
            const sectionIds = [
                'upcomingTreatmentsContent',
                'pastDueContent',
                'completedTreatmentsContent'
            ];

            // Load saved collapse states from localStorage
            sectionIds.forEach(sectionId => {
                const savedState = localStorage.getItem(`collapse_${sectionId}`);
                if (savedState === 'collapsed') {
                    // Collapse the section
                    const section = document.getElementById(sectionId);
                    if (section) {
                        section.classList.remove('show');

                        // Update the button aria-expanded attribute
                        const button = document.querySelector(`[data-bs-target="#${sectionId}"]`);
                        if (button) {
                            button.setAttribute('aria-expanded', 'false');
                        }
                    }
                }
            });

            // Add event listeners to save collapse state
            sectionIds.forEach(sectionId => {
                const section = document.getElementById(sectionId);
                if (section) {
                    section.addEventListener('hidden.bs.collapse', function() {
                        localStorage.setItem(`collapse_${sectionId}`, 'collapsed');
                    });

                    section.addEventListener('shown.bs.collapse', function() {
                        localStorage.setItem(`collapse_${sectionId}`, 'expanded');
                    });
                }
            });

            // Initialize search functionality
            initJobOrderSearch();
        });

        /**
         * Initialize the job order search functionality
         */
        function initJobOrderSearch() {
            const searchInput = document.getElementById('jobOrderSearch');
            const searchButton = document.getElementById('searchButton');
            const clearSearchButton = document.getElementById('clearSearchButton');
            const searchInfo = document.getElementById('searchInfo');

            if (!searchInput || !searchButton || !clearSearchButton) {
                console.error('Search elements not found');
                return;
            }

            // Function to perform the search
            function performSearch() {
                const searchTerm = searchInput.value.trim().toLowerCase();

                if (searchTerm === '') {
                    clearSearch();
                    return;
                }

                // Get all job cards
                const jobCards = document.querySelectorAll('.job-card, .compact-job-card');
                let matchCount = 0;
                let totalCards = jobCards.length;

                // Expand all sections to show search results
                document.querySelectorAll('.section-content.collapse').forEach(section => {
                    const bsCollapse = new bootstrap.Collapse(section, { toggle: false });
                    bsCollapse.show();

                    // Update the button aria-expanded attribute
                    const button = document.querySelector(`[data-bs-target="#${section.id}"]`);
                    if (button) {
                        button.setAttribute('aria-expanded', 'true');
                    }
                });

                // Loop through each card and check if it matches the search term
                jobCards.forEach(card => {
                    const cardText = card.textContent.toLowerCase();
                    const isMatch = cardText.includes(searchTerm);

                    // Show or hide the card based on the search result
                    if (isMatch) {
                        card.closest('.col-12').style.display = '';
                        matchCount++;

                        // Highlight the matching text
                        highlightMatches(card, searchTerm);
                    } else {
                        card.closest('.col-12').style.display = 'none';
                    }
                });

                // Update search info
                if (matchCount === 0) {
                    searchInfo.innerHTML = `<i class="fas fa-info-circle"></i> No job orders found matching "${searchTerm}".`;

                    // Add no results message to each section
                    document.querySelectorAll('.section-content .row').forEach(row => {
                        // Check if the row already has a no-results message
                        if (!row.querySelector('.no-results-message')) {
                            // Check if all cards in this row are hidden
                            const visibleCards = Array.from(row.querySelectorAll('.col-12')).filter(col => col.style.display !== 'none');

                            if (visibleCards.length === 0) {
                                const noResultsDiv = document.createElement('div');
                                noResultsDiv.className = 'col-12 no-results-message';
                                noResultsDiv.innerHTML = `
                                    <i class="fas fa-search"></i>
                                    <p>No job orders matching "${searchTerm}" in this section.</p>
                                `;
                                row.appendChild(noResultsDiv);
                            }
                        }
                    });
                } else {
                    searchInfo.innerHTML = `<i class="fas fa-check-circle"></i> Found ${matchCount} job order(s) matching "${searchTerm}".`;

                    // Remove any existing no-results messages
                    document.querySelectorAll('.no-results-message').forEach(msg => msg.remove());
                }

                // Add active class to search container
                document.querySelector('.search-container').classList.add('active');
            }

            // Function to highlight matching text
            function highlightMatches(card, searchTerm) {
                // Get all text nodes in the card
                const textNodes = [];
                const walker = document.createTreeWalker(card, NodeFilter.SHOW_TEXT, null, false);

                let node;
                while (node = walker.nextNode()) {
                    // Skip nodes in buttons and other interactive elements
                    if (!isInInteractiveElement(node)) {
                        textNodes.push(node);
                    }
                }

                // Highlight matching text in each text node
                textNodes.forEach(textNode => {
                    const text = textNode.nodeValue;
                    const lowerText = text.toLowerCase();
                    const index = lowerText.indexOf(searchTerm);

                    if (index >= 0) {
                        // Create a span with the highlight class
                        const highlightSpan = document.createElement('span');
                        highlightSpan.className = 'search-highlight';
                        highlightSpan.textContent = text.substring(index, index + searchTerm.length);

                        // Create text nodes for the parts before and after the match
                        const beforeNode = document.createTextNode(text.substring(0, index));
                        const afterNode = document.createTextNode(text.substring(index + searchTerm.length));

                        // Replace the original text node with the new nodes
                        const parent = textNode.parentNode;
                        parent.insertBefore(beforeNode, textNode);
                        parent.insertBefore(highlightSpan, textNode);
                        parent.insertBefore(afterNode, textNode);
                        parent.removeChild(textNode);
                    }
                });
            }

            // Helper function to check if a node is inside an interactive element
            function isInInteractiveElement(node) {
                let parent = node.parentNode;
                while (parent) {
                    if (parent.tagName === 'BUTTON' || parent.tagName === 'A' ||
                        parent.classList && (parent.classList.contains('btn') || parent.classList.contains('status-badge'))) {
                        return true;
                    }
                    parent = parent.parentNode;
                }
                return false;
            }

            // Function to clear the search
            function clearSearch() {
                // Clear the search input
                searchInput.value = '';

                // Show all job cards
                document.querySelectorAll('.job-card, .compact-job-card').forEach(card => {
                    card.closest('.col-12').style.display = '';
                });

                // Remove highlights
                document.querySelectorAll('.search-highlight').forEach(highlight => {
                    const parent = highlight.parentNode;
                    const text = highlight.textContent;
                    const textNode = document.createTextNode(text);
                    parent.insertBefore(textNode, highlight);
                    parent.removeChild(highlight);
                });

                // Clear search info
                searchInfo.innerHTML = '';

                // Remove no-results messages
                document.querySelectorAll('.no-results-message').forEach(msg => msg.remove());

                // Remove active class from search container
                document.querySelector('.search-container').classList.remove('active');

                // Restore saved collapse states
                const sectionIds = [
                    'upcomingTreatmentsContent',
                    'pastDueContent',
                    'completedTreatmentsContent'
                ];

                sectionIds.forEach(sectionId => {
                    const savedState = localStorage.getItem(`collapse_${sectionId}`);
                    const section = document.getElementById(sectionId);

                    if (section) {
                        const bsCollapse = new bootstrap.Collapse(section, { toggle: false });

                        if (savedState === 'collapsed') {
                            bsCollapse.hide();
                        } else {
                            bsCollapse.show();
                        }
                    }
                });
            }

            // Add event listeners
            searchButton.addEventListener('click', performSearch);
            clearSearchButton.addEventListener('click', clearSearch);

            // Add event listener for Enter key
            searchInput.addEventListener('keyup', function(event) {
                if (event.key === 'Enter') {
                    performSearch();
                }
            });
        }

        // Ensure notification dropdown works and initialize notifications
        $(document).ready(function() {
            // Initialize notifications
            if (typeof initNotifications === 'function') {
                initNotifications();
            } else {
                console.error("initNotifications function not found");

                // Fallback notification handling if initNotifications is not available
                $('.notification-container').on('click', function(e) {
                    e.stopPropagation();
                    $('.notification-dropdown').toggleClass('show');
                    console.log('Notification icon clicked');
                });

                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.notification-container').length) {
                        $('.notification-dropdown').removeClass('show');
                    }
                });

                // Fetch notifications immediately
                if (typeof fetchNotifications === 'function') {
                    fetchNotifications();

                    // Set up periodic notification checks
                    setInterval(fetchNotifications, 60000); // Check every minute
                }
            }
        });
    </script>
</body>
</html>