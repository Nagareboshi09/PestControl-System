<?php
session_start();
require_once '../db_connect.php';
require_once '../notification_functions.php';
require_once 'log_chemical_dosage.php'; // Include the chemical dosage logging utility

// Get Dashboard Metrics
try {
    // Total Job Order Reports
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM job_order_report");
    $total_reports = $stmt->fetch_assoc()['total'];

    // Set payment metrics to 0 since payment field has been removed
    $total_payment = 0;
    $avg_payment = 0;

    // Reports with Attachments
    $stmt = $conn->query("SELECT COUNT(*) AS with_attachments FROM job_order_report WHERE attachments IS NOT NULL AND attachments != ''");
    $with_attachments = $stmt->fetch_assoc()['with_attachments'];

    // Reports with Client Feedback
    $stmt = $conn->query("SELECT COUNT(*) AS with_feedback FROM job_order_report jor
                         JOIN job_order jo ON jor.job_order_id = jo.job_order_id
                         JOIN joborder_feedback jf ON jo.job_order_id = jf.job_order_id");
    $with_feedback = $stmt->fetch_assoc()['with_feedback'];

    // Average Rating
    $stmt = $conn->query("SELECT AVG(rating) AS avg_rating FROM joborder_feedback");
    $avg_rating_result = $stmt->fetch_assoc();
    $avg_rating = $avg_rating_result['avg_rating'] ? round($avg_rating_result['avg_rating'], 1) : 0;

} catch (Exception $e) {
    // Handle any errors
    $total_reports = 0;
    $total_payment = 0;
    $avg_payment = 0;
    $with_attachments = 0;
    $with_feedback = 0;
    $avg_rating = 0;
}



// Check if recommendation column exists in job_order_report table
$checkRecommendationColumn = $conn->query("SHOW COLUMNS FROM job_order_report LIKE 'recommendation'");
$recommendationColumnExists = $checkRecommendationColumn->num_rows > 0;

// Check if chemical_usage column exists in job_order_report table
$checkChemicalUsageColumn = $conn->query("SHOW COLUMNS FROM job_order_report LIKE 'chemical_usage'");
$chemicalUsageColumnExists = $checkChemicalUsageColumn->num_rows > 0;

// Check if payment_proof column exists in job_order_report table
$checkPaymentProofColumn = $conn->query("SHOW COLUMNS FROM job_order_report LIKE 'payment_proof'");
$paymentProofColumnExists = $checkPaymentProofColumn->num_rows > 0;

// Check if id_attachments column exists in job_order_report table
$checkIdAttachmentsColumn = $conn->query("SHOW COLUMNS FROM job_order_report LIKE 'id_attachments'");
$idAttachmentsColumnExists = $checkIdAttachmentsColumn->num_rows > 0;

// Build the SELECT part of the query based on column existence
$select_fields = "
    jor.report_id,
    jor.job_order_id,
    jor.technician_id,
    jor.observation_notes,";

// Add recommendation field if it exists
if ($recommendationColumnExists) {
    $select_fields .= "
    jor.recommendation,";
} else {
    $select_fields .= "
    '' AS recommendation,";
}

$select_fields .= "
    jor.attachments,";

// Add chemical_usage field if it exists
if ($chemicalUsageColumnExists) {
    $select_fields .= "
    jor.chemical_usage,";
} else {
    $select_fields .= "
    NULL AS chemical_usage,";
}

// Add payment_proof field if it exists
if ($paymentProofColumnExists) {
    $select_fields .= "
    jor.payment_proof,";
} else {
    $select_fields .= "
    NULL AS payment_proof,";
}

// Add id_attachments field if it exists
if ($idAttachmentsColumnExists) {
    $select_fields .= "
    jor.id_attachments,";
} else {
    $select_fields .= "
    NULL AS id_attachments,";
}

$select_fields .= "
    jor.created_at,
    t.username AS technician_name,
    jo.type_of_work,
    jo.preferred_date,
    jo.preferred_time,
    jo.chemical_recommendations,
    a.client_name,
    a.location_address,
    a.kind_of_place,
    jf.feedback_id,
    jf.rating,
    jf.comments AS feedback_comments,
    jf.created_at AS feedback_date,
    jf.technician_arrived,
    jf.job_completed,
    jf.verification_notes,
    a.client_name AS feedback_client_name";

// Fetch job order reports with filters
$report_query = "SELECT $select_fields,
    ar.chemical_recommendations as assessment_chemical_recommendations
    FROM job_order_report jor
    JOIN technicians t ON jor.technician_id = t.technician_id
    JOIN job_order jo ON jor.job_order_id = jo.job_order_id
    JOIN assessment_report ar ON jo.report_id = ar.report_id
    JOIN appointments a ON ar.appointment_id = a.appointment_id
    LEFT JOIN joborder_feedback jf ON jo.job_order_id = jf.job_order_id
    WHERE 1=1";



$report_query .= " ORDER BY jor.created_at DESC";
$report_result = $conn->query($report_query);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Order Report - MacJ Pest Control</title>
    <link rel="stylesheet" href="css/joborder-report-page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        /* Chemical Usage Table Styles */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }

        .table-bordered {
            border: 1px solid #dee2e6;
        }

        .table th, .table td {
            padding: 0.75rem;
            vertical-align: top;
            border: 1px solid #dee2e6;
        }

        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
            background-color: #f8f9fa;
        }

        .table-responsive {
            display: block;
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .text-success {
            color: #28a745;
        }

        .text-warning {
            color: #ffc107;
        }

        .text-danger {
            color: #dc3545;
        }

        .mt-3 {
            margin-top: 1rem;
        }

        .badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }

        .bg-success {
            background-color: #28a745;
            color: white;
        }

        .text-warning {
            color: #ffc107;
        }

        .text-muted {
            color: #6c757d;
        }

        /* Client Feedback Styles */
        .feedback-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e9ecef;
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .feedback-client {
            display: flex;
            flex-direction: column;
        }

        .feedback-date {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .feedback-rating {
            display: flex;
            align-items: center;
        }

        .star-filled {
            color: #ffc107;
        }

        .star-empty {
            color: #e9ecef;
        }

        .rating-text {
            margin-left: 10px;
            font-weight: bold;
        }

        .feedback-verification {
            background-color: #fff;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
        }

        .feedback-verification h4 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.1rem;
            color: #495057;
        }

        .verification-items {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .verification-item {
            flex: 1;
            min-width: 200px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }

        .verification-label {
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
        }

        .verification-notes {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #e9ecef;
        }

        .verification-notes h5 {
            margin-top: 0;
            font-size: 0.95rem;
            color: #495057;
        }

        .feedback-comments {
            background-color: #fff;
            border-radius: 6px;
            padding: 15px;
            border: 1px solid #e9ecef;
        }

        .feedback-comments h4 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.1rem;
            color: #495057;
        }

        .comment-box {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 10px;
            border-left: 3px solid #6c757d;
        }

        .comment-box p {
            margin: 0;
        }

        /* Payment Proof Styles */
        .payment-proof-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e9ecef;
        }

        .payment-proof-container .detail-label {
            color: #495057;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .payment-proof-container .detail-value {
            background-color: #fff;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e9ecef;
            color: #212529;
        }

        /* Modal highlight styles */
        .report-card {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .report-card.modal-open {
            background-color: #f0f7ff; /* Light blue background */
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3); /* Blue glow */
            border-left: 4px solid #3B82F6; /* Blue left border */
        }

        .report-card.modal-open .report-header {
            background-color: #e6f0ff; /* Slightly darker blue for the header */
        }

        /* Animation for modal opening */
        @keyframes highlightPulse {
            0% { background-color: white; }
            50% { background-color: #e6f0ff; }
            100% { background-color: #f0f7ff; }
        }

        .report-card.modal-open {
            animation: highlightPulse 0.5s ease-in-out;
        }

        /* Add a visual indicator for open modals */
        .report-card.modal-open::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 0 30px 30px 0;
            border-color: transparent #3B82F6 transparent transparent;
            z-index: 1;
        }

        .report-card.modal-open::after {
            content: '\f06e'; /* Eye icon from Font Awesome */
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            top: 3px;
            right: 3px;
            color: white;
            font-size: 12px;
            z-index: 2;
        }

        /* Client Job Orders Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
            padding: 0;
            margin: 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.active {
            opacity: 1;
        }

        .modal-content {
            background-color: #fff;
            margin: 50px auto;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 1000px;
            position: relative;
            animation: modalFadeIn 0.3s;
        }

        .modal-lg {
            max-width: 1200px;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            background-color: var(--primary-color);
            color: white;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .modal-title {
            margin: 0;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }

        .close:hover {
            color: #f8f9fa;
        }

        /* Job Orders Summary Styles */
        .job-orders-summary .row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
        }

        .job-orders-summary .col-md-3 {
            flex: 0 0 25%;
            max-width: 25%;
            padding: 10px;
        }

        .job-orders-summary .col-sm-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 10px;
        }

        @media (max-width: 768px) {
            .job-orders-summary .col-md-3 {
                flex: 0 0 50%;
                max-width: 50%;
            }
        }

        @media (max-width: 576px) {
            .job-orders-summary .col-sm-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        .summary-box {
            display: flex;
            align-items: center;
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 1.5rem;
        }

        .bg-primary {
            background-color: #4e73df;
        }

        .bg-success {
            background-color: #1cc88a;
        }

        .bg-warning {
            background-color: #f6c23e;
        }

        .bg-info {
            background-color: #36b9cc;
        }

        .summary-info h4 {
            margin: 0 0 5px 0;
            font-size: 0.9rem;
            color: #5a5c69;
        }

        .summary-info p {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
        }

        /* Progress Section Styles */
        .job-orders-progress {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .section-title {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.1rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .progress-container {
            margin-top: 15px;
        }

        .progress {
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-bar {
            height: 100%;
            background-color: #4e73df;
            color: white;
            text-align: center;
            line-height: 20px;
            font-size: 0.8rem;
            transition: width 0.6s ease;
        }

        .progress-text {
            text-align: right;
            font-size: 0.9rem;
            color: #666;
        }

        /* Job Orders Section Styles */
        .job-orders-section {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .job-orders-list {
            margin-top: 15px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .job-order-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
            overflow: hidden;
            position: relative;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .job-order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .view-report-btn {
            background-color: #4e73df;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            text-align: center;
            margin-top: 10px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background-color 0.3s ease;
        }

        .view-report-btn:hover {
            background-color: #375bca;
        }

        .completed-job-order {
            border-left: 4px solid #1cc88a;
        }

        .current-job-order {
            border-left: 4px solid #4e73df;
        }

        .upcoming-job-order {
            border-left: 4px solid #f6c23e;
        }

        .job-order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .job-order-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .job-order-title h4 {
            margin: 0;
            font-size: 1rem;
            color: #333;
        }

        .job-order-id {
            font-size: 0.9rem;
            color: #666;
            font-weight: 600;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-badge.completed {
            background-color: rgba(28, 200, 138, 0.1);
            color: #1cc88a;
        }

        .status-badge.current {
            background-color: rgba(78, 115, 223, 0.1);
            color: #4e73df;
        }

        .status-badge.upcoming {
            background-color: rgba(246, 194, 62, 0.1);
            color: #f6c23e;
        }

        .job-order-body {
            padding: 15px;
        }

        .job-order-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .job-order-date, .job-order-time, .job-order-technician {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #666;
        }

        .job-order-technician.not-assigned {
            color: #dc3545;
        }

        .job-order-location {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 0.9rem;
            color: #666;
        }

        .job-order-location i {
            margin-top: 3px;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px;
            background-color: #f8f9fa;
            border-radius: 8px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        .empty-state p {
            margin: 0;
            font-size: 1rem;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .text-center {
            text-align: center;
        }

        .p-4 {
            padding: 1.5rem;
        }

        /* Save as PDF Button Styles */
        .save-as-pdf-btn {
            width: 100%;
            margin-top: 15px;
            padding: 10px 15px;
            background-color: var(--primary-color);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        .save-as-pdf-btn:hover {
            background-color: #0056b3;
        }

        .mt-2 {
            margin-top: 0.5rem;
        }

        .mb-4 {
            margin-bottom: 1.5rem;
        }

        .btn {
            display: inline-block;
            font-weight: 400;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            user-select: none;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            cursor: pointer;
        }

        .btn-primary {
            color: #fff;
            background-color: #4e73df;
            border-color: #4e73df;
        }

        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }

        .btn-secondary {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }

        .view-all-job-orders {
            margin-top: 10px;
            width: 100%;
            background-color: #4e73df;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .view-all-job-orders:hover {
            background-color: #375bca;
        }

        /* Individual Job Order Modal Styles */
        .view-job-order {
            margin-top: 10px;
            width: 100%;
            background-color: #4e73df;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .view-job-order:hover {
            background-color: #375bca;
        }

        .job-order-header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .job-order-title-section {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .job-order-title-section h3 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .job-order-id-badge {
            background-color: #f8f9fa;
            color: #6c757d;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .job-order-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .job-order-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            color: #495057;
        }

        .job-order-meta-item i {
            color: var(--accent-color);
            width: 16px;
        }

        .job-order-status-section {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .job-order-details-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .job-order-details-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .job-order-details-row:last-child {
            margin-bottom: 0;
        }

        .job-order-detail-item {
            flex: 1;
            min-width: 200px;
        }

        .assessment-details-section,
        .chemical-recommendations-section,
        .attachments-section,
        .job-report-section,
        .feedback-section {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .assessment-details-content {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }

        .assessment-detail-item {
            flex: 1;
            min-width: 200px;
        }

        .assessment-detail-item.full-width {
            flex-basis: 100%;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .detail-value {
            color: #212529;
            line-height: 1.5;
        }

        .attachments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .job-report-content {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 15px;
        }

        .job-report-detail {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
        }

        .feedback-content {
            margin-top: 15px;
        }

        .feedback-rating {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .rating-stars {
            display: flex;
            gap: 5px;
        }

        .rating-value {
            font-weight: 600;
            font-size: 1.1rem;
            color: #212529;
        }

        .feedback-verification {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .verification-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            font-weight: 500;
        }

        .verification-item.verified i {
            color: #28a745;
        }

        .verification-item.not-verified i {
            color: #dc3545;
        }

        .feedback-comments {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
        }

        /* Assessment Reports Tabs Styles */
        .assessment-reports-container {
            margin-top: 20px;
        }

        .assessment-report-tabs {
            margin-top: 20px;
        }

        .nav-tabs {
            display: flex;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
            overflow-x: auto;
            flex-wrap: nowrap;
            padding-bottom: 1px;
        }

        .nav-tabs .nav-item {
            margin-bottom: -1px;
            white-space: nowrap;
        }

        .nav-tabs .nav-link {
            border: 1px solid transparent;
            border-top-left-radius: 0.25rem;
            border-top-right-radius: 0.25rem;
            color: #495057;
            background-color: transparent;
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .nav-tabs .nav-link:hover {
            border-color: #e9ecef #e9ecef #dee2e6;
            background-color: #f8f9fa;
        }

        .nav-tabs .nav-link.active {
            color: #495057;
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
        }

        .tab-content > .tab-pane {
            display: none;
        }

        .tab-content > .active {
            display: block;
        }

        .fade {
            transition: opacity 0.15s linear;
        }

        .fade:not(.show) {
            opacity: 0;
        }

        .tab-title {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .tab-title-main {
            font-weight: 600;
            font-size: 0.9rem;
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tab-title-sub {
            font-size: 0.75rem;
            color: #6c757d;
        }

        .report-summary {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .report-summary-header {
            margin-bottom: 15px;
        }

        .report-summary-header h4 {
            margin: 0 0 10px 0;
            font-size: 1.2rem;
            color: var(--primary-color);
        }

        .report-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .report-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #495057;
        }

        .report-meta-item i {
            color: var(--accent-color);
            width: 16px;
        }

        .report-summary-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
        }

        .report-stat {
            text-align: center;
            flex: 1;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- PDF Loading Overlay -->
    <div id="pdfLoadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center; flex-direction: column;">
        <div style="background-color: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color); margin-bottom: 15px;"></i>
            <p style="margin: 0; font-weight: 500; color: #333;">Generating PDF...</p>
            <p style="margin: 5px 0 0; font-size: 0.9rem; color: #666;">This may take a few moments</p>
        </div>
    </div>

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
                    <li class="active"><a href="joborder_report.php"><i class="fas fa-tasks"></i> Job Order Report</a></li>
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

            <div class="chemicals-content">
                <div class="chemicals-header">
                    <h1>Job Order Reports</h1>
                </div>

                <!-- Job Order Report Summary -->
                <div class="inventory-summary">
                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--primary-color);">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Total Reports</h3>
                            <p><?= $total_reports ?></p>
                        </div>
                    </div>



                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--info-color);">
                            <i class="fas fa-paperclip"></i>
                        </div>
                        <div class="summary-info">
                            <h3>With Attachments</h3>
                            <p><?= $with_attachments ?></p>
                        </div>
                    </div>

                    <?php if ($chemicalUsageColumnExists): ?>
                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--success-color);">
                            <i class="fas fa-flask"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Chemical Usage</h3>
                            <p>
                                <?php
                                // Count reports with chemical usage
                                $stmt = $conn->query("SELECT COUNT(*) AS with_chemicals FROM job_order_report WHERE chemical_usage IS NOT NULL AND chemical_usage != ''");
                                $with_chemicals = $stmt->fetch_assoc()['with_chemicals'];
                                echo $with_chemicals;
                                ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--warning-color);">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Client Feedback</h3>
                            <p><?= $with_feedback ?> <small class="text-muted">(<?= $avg_rating ?>/5 <i class="fas fa-star" style="color: #ffc107;"></i>)</small></p>
                        </div>
                    </div>
                </div>



                <!-- Job Order Reports List -->
                <?php if ($report_result && $report_result->num_rows > 0): ?>
                <div class="reports-container">
                    <?php while ($report = $report_result->fetch_assoc()):
                        // Get client ID for this report
                        $client_id_query = "SELECT a.client_id
                                           FROM appointments a
                                           JOIN assessment_report ar ON a.appointment_id = ar.appointment_id
                                           JOIN job_order jo ON ar.report_id = jo.report_id
                                           WHERE jo.job_order_id = ?";
                        $stmt = $conn->prepare($client_id_query);
                        $stmt->bind_param("i", $report['job_order_id']);
                        $stmt->execute();
                        $client_id_result = $stmt->get_result();
                        $client_id = ($client_id_result && $client_id_result->num_rows > 0) ? $client_id_result->fetch_assoc()['client_id'] : 0;
                    ?>
                    <div class="report-card" data-client-id="<?= $client_id ?>">
                        <div class="report-header" onclick="toggleReportDetails(this)">
                            <div class="report-info">
                                <h3><?= htmlspecialchars($report['client_name']) ?></h3>
                                <div class="report-meta">
                                    <div class="report-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?= htmlspecialchars($report['location_address']) ?>
                                    </div>
                                    <div class="detail-label"><i class="fas fa-building"></i> <?= htmlspecialchars($report['kind_of_place']) ?></div>
                                    <div class="report-time">
                                        <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($report['preferred_date'])) ?></span>
                                        <span><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($report['preferred_time'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="job-order-container">
                                <div class="job-order-status">
                                </div>
                            </div>
                        </div>
                        <div class="report-details">
                            <!-- Observation and Recommendation Section -->
                            <div class="detail-section">
                                <h3><i class="fas fa-clipboard-check"></i> Inspection Details</h3>
                                <div class="inspection-details-container">
                                    <div class="inspection-section">
                                        <div class="inspection-header">
                                            <i class="fas fa-search"></i>
                                            <h4>Observation Notes</h4>
                                        </div>
                                        <div class="inspection-content">
                                            <p><?= nl2br(htmlspecialchars($report['observation_notes'])) ?></p>
                                        </div>
                                    </div>

                                    <div class="inspection-section">
                                        <div class="inspection-header">
                                            <i class="fas fa-lightbulb"></i>
                                            <h4>Recommendation</h4>
                                        </div>
                                        <div class="inspection-content">
                                            <?php if ($recommendationColumnExists && !empty($report['recommendation'])): ?>
                                                <p><?= nl2br(htmlspecialchars($report['recommendation'])) ?></p>
                                            <?php else: ?>
                                                <p class="text-muted"><em>No recommendation available. This may be from a report created before recommendations were required.</em></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($chemicalUsageColumnExists && !empty($report['chemical_usage'])):
                                // Log chemical usage data for debugging
                                log_chemical_dosage($report['chemical_usage'], 'job_order_report_chemical_usage', $report['job_order_id']);

                                // Also log chemical recommendations from job order and assessment report
                                if (!empty($report['chemical_recommendations'])) {
                                    log_chemical_dosage($report['chemical_recommendations'], 'job_order_chemical_recommendations', $report['job_order_id']);
                                }

                                if (!empty($report['assessment_chemical_recommendations'])) {
                                    log_chemical_dosage($report['assessment_chemical_recommendations'], 'assessment_report_chemical_recommendations', $report['job_order_id']);
                                }

                                $chemicals = json_decode($report['chemical_usage'], true);
                                if ($chemicals && is_array($chemicals)):
                                    // Initialize counters for chemical usage statistics
                                    // We'll calculate these after processing each chemical with fallback mechanisms
                                    $totalChemicals = count($chemicals);
                                    $optimalCount = 0;
                                    $underDosedCount = 0;
                                    $overDosedCount = 0;

                                    // Process each chemical to ensure recommended dosage is available
                                    // We'll recalculate the counts after processing all chemicals
                                    foreach ($chemicals as &$chemical) {
                                        // Get chemical name for lookup
                                        $chemicalName = $chemical['name'] ?? '';

                                        // Get recommended dosage, first try from chemical data
                                        $recommended = isset($chemical['recommended_dosage']) ? floatval($chemical['recommended_dosage']) : 0;

                                        // Apply fallback mechanisms to find recommended dosage
                                        if ($recommended == 0 && !empty($chemicalName)) {
                                            // First try job order chemical recommendations
                                            if (!empty($report['chemical_recommendations'])) {
                                                $jobOrderChemicals = json_decode($report['chemical_recommendations'], true);
                                                if (is_array($jobOrderChemicals)) {
                                                    foreach ($jobOrderChemicals as $jobOrderChem) {
                                                        if (isset($jobOrderChem['name']) && $jobOrderChem['name'] == $chemicalName &&
                                                            isset($jobOrderChem['dosage']) && floatval($jobOrderChem['dosage']) > 0) {
                                                            $recommended = floatval($jobOrderChem['dosage']);
                                                            // Add the recommended dosage to the chemical data for future reference
                                                            $chemical['recommended_dosage'] = $recommended;
                                                            break;
                                                        }
                                                    }
                                                }
                                            }

                                            // If still 0, try assessment report chemical recommendations
                                            if ($recommended == 0 && !empty($report['assessment_chemical_recommendations'])) {
                                                $assessmentChemicals = json_decode($report['assessment_chemical_recommendations'], true);
                                                if (is_array($assessmentChemicals)) {
                                                    // Assessment report might have nested structure by pest type
                                                    foreach ($assessmentChemicals as $pestType => $pestChemicals) {
                                                        if (is_array($pestChemicals)) {
                                                            foreach ($pestChemicals as $assessmentChem) {
                                                                if (isset($assessmentChem['chemical_name']) && $assessmentChem['chemical_name'] == $chemicalName &&
                                                                    isset($assessmentChem['recommended_dosage']) && floatval($assessmentChem['recommended_dosage']) > 0) {
                                                                    $recommended = floatval($assessmentChem['recommended_dosage']);
                                                                    // Add the recommended dosage to the chemical data for future reference
                                                                    $chemical['recommended_dosage'] = $recommended;
                                                                    break 2; // Break out of both loops
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }

                                            // If still 0, use default dosage based on chemical name
                                            if ($recommended == 0) {
                                                // Use specific dosage rates for known chemicals (for 100 sqm)
                                                if (stripos($chemicalName, 'Fipronil') !== false) {
                                                    $recommended = 12; // 12ml per 100sqm
                                                } else if (stripos($chemicalName, 'Cypermethrin') !== false) {
                                                    $recommended = 20; // 20ml per 100sqm
                                                } else if (stripos($chemicalName, 'Imidacloprid') !== false) {
                                                    $recommended = 10; // 10ml per 100sqm
                                                }

                                                // If we found a default dosage, add it to the chemical data
                                                if ($recommended > 0) {
                                                    $chemical['recommended_dosage'] = $recommended;
                                                }
                                            }
                                        }

                                        // Store the recommended dosage in the chemical data
                                        $chemical['recommended_dosage'] = $recommended;
                                    }
                                    unset($chemical); // Unset reference to last element

                                    // Now recalculate the counts with the updated recommended dosage values
                                    foreach ($chemicals as $chemical) {
                                        $recommended = isset($chemical['recommended_dosage']) ? floatval($chemical['recommended_dosage']) : 0;
                                        $actual = isset($chemical['dosage']) ? floatval($chemical['dosage']) : 0;
                                        $minAcceptable = $recommended * 0.8;
                                        $maxAcceptable = $recommended * 1.2;

                                        if ($actual < $minAcceptable) {
                                            $underDosedCount++;
                                        } elseif ($actual > $maxAcceptable) {
                                            $overDosedCount++;
                                        } else {
                                            $optimalCount++;
                                        }
                                    }
                            ?>
                            <div class="detail-section">
                                <h3><i class="fas fa-flask"></i> Chemical Usage</h3>
                                <div class="chemical-usage-container">
                                    <!-- Chemical Usage Summary -->
                                    <div class="chemical-usage-summary">
                                        <div class="chemical-usage-stat">
                                            <div class="stat-icon optimal">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div class="stat-info">
                                                <span class="stat-label">Optimal</span>
                                                <span class="stat-value"><?= $optimalCount ?>/<?= $totalChemicals ?></span>
                                            </div>
                                        </div>

                                        <div class="chemical-usage-stat">
                                            <div class="stat-icon under-dosed">
                                                <i class="fas fa-arrow-down"></i>
                                            </div>
                                            <div class="stat-info">
                                                <span class="stat-label">Under-dosed</span>
                                                <span class="stat-value"><?= $underDosedCount ?>/<?= $totalChemicals ?></span>
                                            </div>
                                        </div>

                                        <div class="chemical-usage-stat">
                                            <div class="stat-icon over-dosed">
                                                <i class="fas fa-arrow-up"></i>
                                            </div>
                                            <div class="stat-info">
                                                <span class="stat-label">Over-dosed</span>
                                                <span class="stat-value"><?= $overDosedCount ?>/<?= $totalChemicals ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Chemical Usage Table -->
                                    <div class="table-responsive">
                                        <table class="table table-bordered chemical-table">
                                            <thead>
                                                <tr>
                                                    <th>Chemical Name</th>
                                                    <th>Type</th>
                                                    <th>Target Pest</th>
                                                    <th>Recommended</th>
                                                    <th>Actual Used</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                foreach ($chemicals as $chemical):
                                                    // The recommended dosage has already been processed and stored in the chemical data
                                                    // in the summary calculation section above
                                                    $recommended = isset($chemical['recommended_dosage']) ? floatval($chemical['recommended_dosage']) : 0;
                                                    $chemicalName = $chemical['name'] ?? '';

                                                    $actual = isset($chemical['dosage']) ? floatval($chemical['dosage']) : 0;
                                                    $minAcceptable = $recommended * 0.8;
                                                    $maxAcceptable = $recommended * 1.2;
                                                    $unit = htmlspecialchars($chemical['dosage_unit'] ?? 'ml');

                                                    $status = '';
                                                    $statusClass = '';
                                                    $statusIcon = '';

                                                    if ($actual < $minAcceptable) {
                                                        $status = 'Under-dosed';
                                                        $statusClass = 'text-warning';
                                                        $statusIcon = 'fa-arrow-down';
                                                    } elseif ($actual > $maxAcceptable) {
                                                        $status = 'Over-dosed';
                                                        $statusClass = 'text-danger';
                                                        $statusIcon = 'fa-arrow-up';
                                                    } else {
                                                        $status = 'Optimal';
                                                        $statusClass = 'text-success';
                                                        $statusIcon = 'fa-check-circle';
                                                    }
                                                ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($chemical['name'] ?? 'N/A') ?></strong></td>
                                                    <td><?= htmlspecialchars($chemical['type'] ?? 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($chemical['target_pest'] ?? 'N/A') ?></td>
                                                    <td><?= $recommended ?> <?= $unit ?></td>
                                                    <td><?= $actual ?> <?= $unit ?></td>
                                                    <td class="<?= $statusClass ?>">
                                                        <i class="fas <?= $statusIcon ?>"></i>
                                                        <strong><?= $status ?></strong>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="chemical-usage-note">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Chemical usage is considered optimal when within ±20% of the recommended dosage.</span>
                                    </div>
                                </div>
                            </div>
                            <?php endif; endif; ?>

                            <?php if (!empty($report['attachments'])):
                                $attachments = explode(',', $report['attachments']);
                                $validAttachments = array_filter($attachments, function($att) { return trim($att) !== ''; });
                                $attachmentCount = count($validAttachments);
                            ?>
                            <div class="detail-section">
                                <h3><i class="fas fa-images"></i> Attachments <span class="attachment-count">(<?= $attachmentCount ?>)</span></h3>
                                <div class="attachments-container">
                                    <div class="attachments-grid">
                                        <?php
                                        foreach ($validAttachments as $attachment):
                                            $attachmentPath = "../uploads/" . trim($attachment);
                                        ?>
                                        <div class="attachment-item">
                                            <div class="attachment-preview">
                                                <a href="<?= $attachmentPath ?>" target="_blank" class="attachment-link">
                                                    <img src="<?= $attachmentPath ?>" alt="Attachment" class="attachment-img">
                                                </a>
                                                <div class="attachment-overlay">
                                                    <a href="<?= $attachmentPath ?>" target="_blank" class="attachment-action">
                                                        <i class="fas fa-search-plus"></i>
                                                    </a>
                                                    <a href="<?= $attachmentPath ?>" download class="attachment-action">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="attachment-info">
                                                <span class="attachment-name"><?= basename(trim($attachment)) ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Payment Proof Section -->
                            <?php if ($paymentProofColumnExists && !empty($report['payment_proof'])): ?>
                            <div class="detail-section">
                                <h3><i class="fas fa-money-check-alt"></i> Payment Proof</h3>
                                <div class="payment-proof-container">
                                    <div class="job-report-detail">
                                        <div class="detail-label">
                                            <i class="fas fa-receipt"></i> Payment Information
                                        </div>
                                        <div class="detail-value">
                                            <?= nl2br(htmlspecialchars($report['payment_proof'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- ID Attachments Section -->
                            <?php if ($idAttachmentsColumnExists && !empty($report['id_attachments'])):
                                $idAttachments = explode(',', $report['id_attachments']);
                                $validIdAttachments = array_filter($idAttachments, function($att) { return trim($att) !== ''; });
                                $idAttachmentCount = count($validIdAttachments);
                            ?>
                            <div class="detail-section">
                                <h3><i class="fas fa-id-card"></i> Client ID Attachments <span class="attachment-count">(<?= $idAttachmentCount ?>)</span></h3>
                                <div class="attachments-container">
                                    <div class="attachments-grid">
                                        <?php
                                        foreach ($validIdAttachments as $attachment):
                                            $attachmentPath = "../uploads/ids/" . trim($attachment);
                                        ?>
                                        <div class="attachment-item">
                                            <div class="attachment-preview">
                                                <a href="<?= $attachmentPath ?>" target="_blank" class="attachment-link">
                                                    <img src="<?= $attachmentPath ?>" alt="ID Attachment" class="attachment-img">
                                                </a>
                                                <div class="attachment-overlay">
                                                    <a href="<?= $attachmentPath ?>" target="_blank" class="attachment-action">
                                                        <i class="fas fa-search-plus"></i>
                                                    </a>
                                                    <a href="<?= $attachmentPath ?>" download class="attachment-action">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="attachment-info">
                                                <span class="attachment-name"><?= basename(trim($attachment)) ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($report['feedback_id'])): ?>
                            <div class="detail-section">
                                <h3><i class="fas fa-star"></i> Client Feedback</h3>
                                <div class="feedback-container">
                                    <!-- Feedback Rating Summary -->
                                    <div class="feedback-summary">
                                        <div class="feedback-rating-card">
                                            <div class="rating-header">
                                                <span class="rating-label">Client Rating</span>
                                                <span class="rating-value"><?= $report['rating'] ?>/5</span>
                                            </div>
                                            <div class="rating-stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?= ($i <= $report['rating']) ? 'star-filled' : 'star-empty' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="rating-date">
                                                <i class="far fa-calendar-alt"></i>
                                                <span><?= date('M d, Y', strtotime($report['feedback_date'])) ?></span>
                                            </div>
                                        </div>

                                        <!-- Verification Checks -->
                                        <div class="verification-checks">
                                            <div class="verification-check <?= $report['technician_arrived'] ? 'verified' : 'not-verified' ?>">
                                                <div class="check-icon">
                                                    <i class="fas <?= $report['technician_arrived'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                                </div>
                                                <div class="check-info">
                                                    <span class="check-label">Technician Arrived</span>
                                                    <span class="check-value"><?= $report['technician_arrived'] ? 'Confirmed' : 'Not Confirmed' ?></span>
                                                </div>
                                            </div>

                                            <div class="verification-check <?= $report['job_completed'] ? 'verified' : 'not-verified' ?>">
                                                <div class="check-icon">
                                                    <i class="fas <?= $report['job_completed'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                                </div>
                                                <div class="check-info">
                                                    <span class="check-label">Job Completed</span>
                                                    <span class="check-value"><?= $report['job_completed'] ? 'Confirmed' : 'Not Confirmed' ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Feedback Content -->
                                    <div class="feedback-content">
                                        <?php if (!empty($report['verification_notes'])): ?>
                                        <div class="feedback-section">
                                            <div class="feedback-section-header">
                                                <i class="fas fa-clipboard-check"></i>
                                                <h4>Verification Notes</h4>
                                            </div>
                                            <div class="feedback-section-content verification-notes-content">
                                                <p><?= nl2br(htmlspecialchars($report['verification_notes'])) ?></p>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($report['feedback_comments'])): ?>
                                        <div class="feedback-section">
                                            <div class="feedback-section-header">
                                                <i class="fas fa-comment-alt"></i>
                                                <h4>Client Comments</h4>
                                            </div>
                                            <div class="feedback-section-content client-comments-content">
                                                <div class="comment-box">
                                                    <i class="fas fa-quote-left quote-icon"></i>
                                                    <p><?= nl2br(htmlspecialchars($report['feedback_comments'])) ?></p>
                                                    <i class="fas fa-quote-right quote-icon"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Save as PDF Button -->
                            <div class="detail-section">
                                <button class="btn btn-primary save-as-pdf-btn" onclick="saveAsPDF(<?= $report['job_order_id'] ?>)">
                                    <i class="fas fa-file-pdf"></i> Save as PDF
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="no-reports">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No job order reports found.</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <!-- Notification Scripts -->


    <!-- Client Job Orders Modal -->
    <div class="modal" id="clientJobOrdersModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-calendar-alt"></i> <span id="clientNameTitle">Client</span> - All Job Orders</h2>
                <button class="close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="clientJobOrdersLoading" class="text-center p-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading job orders...</p>
                </div>
                <div id="clientJobOrdersContent" style="display: none;">
                    <!-- Summary Section -->
                    <div class="job-orders-summary mb-4">
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="summary-box">
                                    <div class="summary-icon bg-primary">
                                        <i class="fas fa-clipboard-list"></i>
                                    </div>
                                    <div class="summary-info">
                                        <h4>Total Job Orders</h4>
                                        <p id="totalJobOrders">0</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="summary-box">
                                    <div class="summary-icon bg-success">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="summary-info">
                                        <h4>Completed</h4>
                                        <p id="completedJobOrders">0</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="summary-box">
                                    <div class="summary-icon bg-warning">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="summary-info">
                                        <h4>Upcoming</h4>
                                        <p id="upcomingJobOrders">0</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="summary-box">
                                    <div class="summary-icon bg-info">
                                        <i class="fas fa-sync-alt"></i>
                                    </div>
                                    <div class="summary-info">
                                        <h4>Frequency</h4>
                                        <p id="jobFrequency">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Section -->
                    <div class="job-orders-progress mb-4">
                        <h3 class="section-title"><i class="fas fa-tasks"></i> Project Progress</h3>
                        <div class="progress-container">
                            <div class="progress">
                                <div id="jobOrderProgressBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                            </div>
                            <div class="progress-text">
                                <span id="jobOrderProgressText">0/0 Job orders completed</span>
                            </div>
                        </div>
                    </div>

                    <!-- Completed Job Orders Section -->
                    <div class="job-orders-section mb-4">
                        <h3 class="section-title"><i class="fas fa-check-circle"></i> Completed Job Orders</h3>
                        <div id="completedJobOrdersList" class="job-orders-list">
                            <!-- Completed job orders will be loaded here -->
                            <div class="empty-state">
                                <i class="fas fa-clipboard-check"></i>
                                <p>No completed job orders found.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Current Job Order Section -->
                    <div class="job-orders-section mb-4">
                        <h3 class="section-title"><i class="fas fa-calendar-day"></i> Current Job Order</h3>
                        <div id="currentJobOrderContainer" class="job-orders-list">
                            <!-- Current job order will be loaded here -->
                            <div class="empty-state">
                                <i class="fas fa-calendar-day"></i>
                                <p>No current job order found.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Job Orders Section -->
                    <div class="job-orders-section">
                        <h3 class="section-title"><i class="fas fa-calendar-alt"></i> Upcoming Job Orders</h3>
                        <div id="upcomingJobOrdersList" class="job-orders-list">
                            <!-- Upcoming job orders will be loaded here -->
                            <div class="empty-state">
                                <i class="fas fa-calendar-alt"></i>
                                <p>No upcoming job orders found.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="clientJobOrdersError" class="alert alert-danger" style="display: none;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="errorMessage">Failed to load job orders.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-btn">Close</button>
            </div>
        </div>
    </div>

    <script src="js/notifications.js"></script>
    <script src="js/chemical-notifications.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jsPDF library for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        // Initialize mobile menu and notifications when the page loads
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

            // Initialize the client job orders modal
            initClientJobOrdersModal();




        });

        // Function to toggle report details
        function toggleReportDetails(header) {
            const reportCard = header.closest('.report-card');
            const details = reportCard.querySelector('.report-details');

            if (details.classList.contains('active')) {
                // Close the modal
                details.classList.remove('active');
                reportCard.classList.remove('modal-open');
            } else {
                // Close any other open details first
                document.querySelectorAll('.report-details.active').forEach(function(el) {
                    const openCard = el.closest('.report-card');
                    el.classList.remove('active');
                    openCard.classList.remove('modal-open');
                });

                // Open this modal
                details.classList.add('active');
                reportCard.classList.add('modal-open');
            }
        }

        // Function to initialize the client job orders modal
        function initClientJobOrdersModal() {
            const modal = document.getElementById('clientJobOrdersModal');
            const closeButtons = modal.querySelectorAll('.close, .close-btn');

            // Add click event to close buttons
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    closeModal(modal);
                });
            });

            // Close modal when clicking outside of it
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal(modal);
                }
            });

            // Function to properly close the modal with animation
            function closeModal(modalElement) {
                // Remove active class first to trigger fade out
                modalElement.classList.remove('active');

                // Wait for animation to complete before hiding
                setTimeout(() => {
                    modalElement.style.display = 'none';
                }, 300);
            }

            // Add click event to report cards to open the modal
            document.querySelectorAll('.report-card').forEach(card => {
                const clientName = card.querySelector('.report-info h3').textContent;
                const clientId = getClientIdFromReportCard(card);

                // Add a button to view all job orders
                const jobOrderContainer = card.querySelector('.job-order-container');
                if (jobOrderContainer) {
                    // Check if button already exists
                    if (!jobOrderContainer.querySelector('.view-all-job-orders')) {
                        const jobOrderStatus = jobOrderContainer.querySelector('.job-order-status');

                        const viewAllButton = document.createElement('button');
                        viewAllButton.className = 'btn btn-primary view-all-job-orders';
                        viewAllButton.innerHTML = '<i class="fas fa-calendar-alt"></i> View All Job Orders';
                        viewAllButton.setAttribute('data-client-id', clientId);
                        viewAllButton.setAttribute('data-client-name', clientName);

                        viewAllButton.addEventListener('click', function(e) {
                            e.stopPropagation(); // Prevent the report card from toggling
                            openClientJobOrdersModal(clientId, clientName);
                        });

                        // Append after job-order-status
                        if (jobOrderStatus) {
                            jobOrderStatus.insertAdjacentElement('afterend', viewAllButton);
                        } else {
                            jobOrderContainer.appendChild(viewAllButton);
                        }
                    }
                }
            });
        }

        // Function to get client ID from report card
        function getClientIdFromReportCard(card) {
            // Get client ID from the data attribute
            const clientId = card.getAttribute('data-client-id');
            console.log('Getting client ID from card:', clientId);
            return clientId || '0';
        }

        // Function to open the client job orders modal
        function openClientJobOrdersModal(clientId, clientName) {
            const modal = document.getElementById('clientJobOrdersModal');
            const clientNameTitle = document.getElementById('clientNameTitle');
            const loadingElement = document.getElementById('clientJobOrdersLoading');
            const contentElement = document.getElementById('clientJobOrdersContent');
            const errorElement = document.getElementById('clientJobOrdersError');

            // Reset modal state
            clientNameTitle.textContent = clientName;
            loadingElement.style.display = 'block';
            contentElement.style.display = 'none';
            errorElement.style.display = 'none';

            // Show the modal
            modal.style.display = 'block';

            // Force browser to recognize the change and render properly
            setTimeout(() => {
                // Add a class to trigger animation if needed
                modal.classList.add('active');
            }, 10);

            // Fetch client job orders
            fetchClientJobOrders(clientId);

            // Log for debugging
            console.log('Opening modal for client:', clientName, 'ID:', clientId);
        }

        // Function to fetch client job orders
        function fetchClientJobOrders(clientId) {
            console.log('Fetching job orders for client ID:', clientId);

            // Show the URL being fetched for debugging
            const url = `get_client_job_orders.php?client_id=${clientId}`;
            console.log('Fetching from URL:', url);

            // Fetch data from the server
            fetch(url)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.text();
                })
                .then(text => {
                    console.log('Raw response:', text);
                    try {
                        const data = JSON.parse(text);
                        console.log('Parsed data:', data);
                        if (data.success) {
                            displayClientJobOrders(data);
                        } else {
                            showError(data.message || 'Failed to load job orders.');
                        }
                    } catch (e) {
                        console.error('Error parsing JSON:', e);
                        showError('Invalid response from server. Please check the console for details.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching client job orders:', error);
                    showError('An error occurred while fetching job orders.');
                });
        }

        // Function to display client job orders
        function displayClientJobOrders(data) {
            const loadingElement = document.getElementById('clientJobOrdersLoading');
            const contentElement = document.getElementById('clientJobOrdersContent');
            const errorElement = document.getElementById('clientJobOrdersError');

            // Check if we have job orders grouped by report
            if (data.job_orders_by_report && data.job_orders_by_report.length > 0) {
                // Create a container for the assessment reports
                const reportsContainer = document.createElement('div');
                reportsContainer.className = 'assessment-reports-container';

                // Add a header for the assessment reports section
                const reportsHeader = document.createElement('h3');
                reportsHeader.className = 'section-title mt-4';
                reportsHeader.innerHTML = '<i class="fas fa-clipboard-list"></i> Assessment Reports';
                reportsContainer.appendChild(reportsHeader);

                // Create tabs for each assessment report
                const tabsContainer = document.createElement('div');
                tabsContainer.className = 'assessment-report-tabs';

                const tabsNav = document.createElement('ul');
                tabsNav.className = 'nav nav-tabs';
                tabsNav.id = 'assessmentReportTabs';
                tabsNav.setAttribute('role', 'tablist');

                const tabsContent = document.createElement('div');
                tabsContent.className = 'tab-content';
                tabsContent.id = 'assessmentReportTabContent';

                // Create a tab for each assessment report
                data.job_orders_by_report.forEach((report, index) => {
                    // Create tab nav item
                    const tabNavItem = document.createElement('li');
                    tabNavItem.className = 'nav-item';
                    tabNavItem.setAttribute('role', 'presentation');

                    const tabNavLink = document.createElement('button');
                    tabNavLink.className = `nav-link ${index === 0 ? 'active' : ''}`;
                    tabNavLink.id = `report-${report.report_id}-tab`;
                    tabNavLink.setAttribute('data-bs-toggle', 'tab');
                    tabNavLink.setAttribute('data-bs-target', `#report-${report.report_id}`);
                    tabNavLink.setAttribute('type', 'button');
                    tabNavLink.setAttribute('role', 'tab');
                    tabNavLink.setAttribute('aria-controls', `report-${report.report_id}`);
                    tabNavLink.setAttribute('aria-selected', index === 0 ? 'true' : 'false');

                    // Create a short title for the tab
                    const tabTitle = document.createElement('div');
                    tabTitle.className = 'tab-title';
                    tabTitle.innerHTML = `
                        <div class="tab-title-main">${report.type_of_work}</div>
                        <div class="tab-title-sub">${report.frequency.charAt(0).toUpperCase() + report.frequency.slice(1)}</div>
                    `;
                    tabNavLink.appendChild(tabTitle);

                    tabNavItem.appendChild(tabNavLink);
                    tabsNav.appendChild(tabNavItem);

                    // Create tab content
                    const tabContent = document.createElement('div');
                    tabContent.className = `tab-pane fade ${index === 0 ? 'show active' : ''}`;
                    tabContent.id = `report-${report.report_id}`;
                    tabContent.setAttribute('role', 'tabpanel');
                    tabContent.setAttribute('aria-labelledby', `report-${report.report_id}-tab`);

                    // Create report summary
                    const reportSummary = document.createElement('div');
                    reportSummary.className = 'report-summary mb-4';
                    reportSummary.innerHTML = `
                        <div class="report-summary-header">
                            <h4>${report.type_of_work}</h4>
                            <div class="report-meta">
                                <div class="report-meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>${report.location_address}</span>
                                </div>
                                <div class="report-meta-item">
                                    <i class="fas fa-building"></i>
                                    <span>${report.kind_of_place}</span>
                                </div>
                                <div class="report-meta-item">
                                    <i class="fas fa-sync-alt"></i>
                                    <span>${report.frequency.charAt(0).toUpperCase() + report.frequency.slice(1)}</span>
                                </div>
                            </div>
                        </div>
                        <div class="report-summary-stats">
                            <div class="report-stat">
                                <div class="stat-label">Total Job Orders</div>
                                <div class="stat-value">${report.total_job_orders}</div>
                            </div>
                            <div class="report-stat">
                                <div class="stat-label">Completed</div>
                                <div class="stat-value">${report.completed_job_orders}</div>
                            </div>
                            <div class="report-stat">
                                <div class="stat-label">Upcoming</div>
                                <div class="stat-value">${report.upcoming_job_orders}</div>
                            </div>
                        </div>
                    `;
                    tabContent.appendChild(reportSummary);

                    // Create progress bar
                    const progressPercentage = report.total_job_orders > 0 ? Math.round((report.completed_job_orders / report.total_job_orders) * 100) : 0;
                    const progressSection = document.createElement('div');
                    progressSection.className = 'job-orders-progress mb-4';
                    progressSection.innerHTML = `
                        <h3 class="section-title"><i class="fas fa-tasks"></i> Project Progress</h3>
                        <div class="progress-container">
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: ${progressPercentage}%;" aria-valuenow="${progressPercentage}" aria-valuemin="0" aria-valuemax="100">${progressPercentage}%</div>
                            </div>
                            <div class="progress-text">
                                <span>${report.completed_job_orders}/${report.total_job_orders} Job orders completed</span>
                            </div>
                        </div>
                    `;
                    tabContent.appendChild(progressSection);

                    // Create completed jobs section
                    const completedSection = document.createElement('div');
                    completedSection.className = 'job-orders-section mb-4';
                    completedSection.innerHTML = `
                        <h3 class="section-title"><i class="fas fa-check-circle"></i> Completed Job Orders</h3>
                        <div class="job-orders-list" id="completedJobsList-${report.report_id}">
                            ${report.completed_jobs.length > 0 ? '' : `
                                <div class="empty-state">
                                    <i class="fas fa-clipboard-check"></i>
                                    <p>No completed job orders found.</p>
                                </div>
                            `}
                        </div>
                    `;
                    tabContent.appendChild(completedSection);

                    // Create current job section
                    const currentSection = document.createElement('div');
                    currentSection.className = 'job-orders-section mb-4';
                    currentSection.innerHTML = `
                        <h3 class="section-title"><i class="fas fa-calendar-day"></i> Current Job Order</h3>
                        <div class="job-orders-list" id="currentJobContainer-${report.report_id}">
                            ${report.current_job ? '' : `
                                <div class="empty-state">
                                    <i class="fas fa-calendar-day"></i>
                                    <p>No current job order found.</p>
                                </div>
                            `}
                        </div>
                    `;
                    tabContent.appendChild(currentSection);

                    // Create upcoming jobs section
                    const upcomingSection = document.createElement('div');
                    upcomingSection.className = 'job-orders-section';
                    upcomingSection.innerHTML = `
                        <h3 class="section-title"><i class="fas fa-calendar-alt"></i> Upcoming Job Orders</h3>
                        <div class="job-orders-list" id="upcomingJobsList-${report.report_id}">
                            ${report.upcoming_jobs.length > 0 ? '' : `
                                <div class="empty-state">
                                    <i class="fas fa-calendar-alt"></i>
                                    <p>No upcoming job orders found.</p>
                                </div>
                            `}
                        </div>
                    `;
                    tabContent.appendChild(upcomingSection);

                    tabsContent.appendChild(tabContent);

                    // Add job order cards after the tab content is added to the DOM
                    setTimeout(() => {
                        // Add completed job cards
                        const completedList = document.getElementById(`completedJobsList-${report.report_id}`);
                        if (report.completed_jobs.length > 0 && completedList) {
                            completedList.innerHTML = '';
                            report.completed_jobs.forEach(job => {
                                completedList.appendChild(createJobOrderCard(job, 'completed'));
                            });
                        }

                        // Add current job card
                        const currentContainer = document.getElementById(`currentJobContainer-${report.report_id}`);
                        if (report.current_job && currentContainer) {
                            currentContainer.innerHTML = '';
                            currentContainer.appendChild(createJobOrderCard(report.current_job, 'current'));
                        }

                        // Add upcoming job cards
                        const upcomingList = document.getElementById(`upcomingJobsList-${report.report_id}`);
                        if (report.upcoming_jobs.length > 0 && upcomingList) {
                            upcomingList.innerHTML = '';
                            report.upcoming_jobs.forEach(job => {
                                upcomingList.appendChild(createJobOrderCard(job, 'upcoming'));
                            });
                        }
                    }, 0);
                });

                tabsContainer.appendChild(tabsNav);
                tabsContainer.appendChild(tabsContent);
                reportsContainer.appendChild(tabsContainer);

                // Add the reports container to the content element
                contentElement.innerHTML = '';
                contentElement.appendChild(reportsContainer);

                // Initialize Bootstrap tabs
                if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                    const triggerTabList = [].slice.call(document.querySelectorAll('#assessmentReportTabs button'));
                    triggerTabList.forEach(function (triggerEl) {
                        new bootstrap.Tab(triggerEl);
                    });
                }
            } else {
                // Fallback to the old display method if job_orders_by_report is not available

                // Update summary information
                document.getElementById('totalJobOrders').textContent = data.total_job_orders || 0;
                document.getElementById('completedJobOrders').textContent = data.completed_job_orders || 0;
                document.getElementById('upcomingJobOrders').textContent = data.upcoming_job_orders || 0;
                document.getElementById('jobFrequency').textContent = data.frequency ? data.frequency.charAt(0).toUpperCase() + data.frequency.slice(1) : 'One-time';

                // Update progress bar
                const progressPercentage = data.total_job_orders > 0 ? Math.round((data.completed_job_orders / data.total_job_orders) * 100) : 0;
                const progressBar = document.getElementById('jobOrderProgressBar');
                progressBar.style.width = `${progressPercentage}%`;
                progressBar.setAttribute('aria-valuenow', progressPercentage);
                progressBar.textContent = `${progressPercentage}%`;

                document.getElementById('jobOrderProgressText').textContent = `${data.completed_job_orders || 0}/${data.total_job_orders || 0} Job orders completed`;

                // Populate completed job orders
                const completedList = document.getElementById('completedJobOrdersList');
                completedList.innerHTML = '';

                if (data.completed_job_orders > 0 && data.completed_jobs && data.completed_jobs.length > 0) {
                    data.completed_jobs.forEach(job => {
                        completedList.appendChild(createJobOrderCard(job, 'completed'));
                    });
                } else {
                    completedList.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-clipboard-check"></i>
                            <p>No completed job orders found.</p>
                        </div>
                    `;
                }

                // Populate current job order
                const currentContainer = document.getElementById('currentJobOrderContainer');
                currentContainer.innerHTML = '';

                if (data.current_job && Object.keys(data.current_job).length > 0) {
                    currentContainer.appendChild(createJobOrderCard(data.current_job, 'current'));
                } else {
                    currentContainer.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-calendar-day"></i>
                            <p>No current job order found.</p>
                        </div>
                    `;
                }

                // Populate upcoming job orders
                const upcomingList = document.getElementById('upcomingJobOrdersList');
                upcomingList.innerHTML = '';

                if (data.upcoming_job_orders > 0 && data.upcoming_jobs && data.upcoming_jobs.length > 0) {
                    data.upcoming_jobs.forEach(job => {
                        upcomingList.appendChild(createJobOrderCard(job, 'upcoming'));
                    });
                } else {
                    upcomingList.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-calendar-alt"></i>
                            <p>No upcoming job orders found.</p>
                        </div>
                    `;
                }
            }

            // Show content
            loadingElement.style.display = 'none';
            contentElement.style.display = 'block';
            errorElement.style.display = 'none';
        }

        // Function to create a job order card
        function createJobOrderCard(job, type) {
            const card = document.createElement('div');
            card.className = `job-order-card ${type}-job-order`;

            // Add job order ID as data attribute
            card.setAttribute('data-job-order-id', job.job_order_id);

            // Format date and time
            const jobDate = new Date(job.preferred_date);
            const formattedDate = jobDate.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            const formattedTime = job.preferred_time ? new Date(`2000-01-01T${job.preferred_time}`).toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            }) : 'N/A';

            // Create status badge
            let statusBadge = '';
            if (type === 'completed') {
                statusBadge = '<span class="status-badge completed"><i class="fas fa-check-circle"></i> Completed</span>';
            } else if (type === 'current') {
                statusBadge = '<span class="status-badge current"><i class="fas fa-clock"></i> Current</span>';
            } else {
                statusBadge = '<span class="status-badge upcoming"><i class="fas fa-calendar-alt"></i> Upcoming</span>';
            }

            // Create technician info
            let technicianInfo = '';
            if (job.technician_name) {
                technicianInfo = `
                    <div class="job-order-technician">
                        <i class="fas fa-user-md"></i>
                        <span>${job.technician_name}</span>
                    </div>
                `;
            } else {
                technicianInfo = `
                    <div class="job-order-technician not-assigned">
                        <i class="fas fa-user-md"></i>
                        <span>Not Assigned</span>
                    </div>
                `;
            }

            // Build card content
            card.innerHTML = `
                <div class="job-order-header">
                    <div class="job-order-title">
                        <h4>${job.type_of_work || 'Job Order'}</h4>
                        ${statusBadge}
                    </div>
                    <div class="job-order-id">#${job.job_order_id}</div>
                </div>
                <div class="job-order-body">
                    <div class="job-order-info">
                        <div class="job-order-date">
                            <i class="fas fa-calendar"></i>
                            <span>${formattedDate}</span>
                        </div>
                        <div class="job-order-time">
                            <i class="fas fa-clock"></i>
                            <span>${formattedTime}</span>
                        </div>
                        ${technicianInfo}
                    </div>
                    <div class="job-order-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>${job.location_address || 'No address provided'}</span>
                    </div>
                </div>

            `;

            return card;
        }

        // Function to show error message
        function showError(message) {
            const loadingElement = document.getElementById('clientJobOrdersLoading');
            const contentElement = document.getElementById('clientJobOrdersContent');
            const errorElement = document.getElementById('clientJobOrdersError');
            const errorMessage = document.getElementById('errorMessage');

            errorMessage.textContent = message;
            loadingElement.style.display = 'none';
            contentElement.style.display = 'none';
            errorElement.style.display = 'block';
        }











        // Helper function to get basename from path
        function basename(path) {
            return path.split('/').pop();
        }

        // Function to save job order report as PDF
        function saveAsPDF(jobOrderId) {
            // Show loading overlay
            const loadingOverlay = document.getElementById('pdfLoadingOverlay');
            loadingOverlay.style.display = 'flex';

            // Fetch job order details
            fetch(`get_job_order_details_admin.php?job_order_id=${jobOrderId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to fetch job order details');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Failed to fetch job order details');
                    }
                    generatePDF(data.job_order, data.technicians);
                })
                .catch(error => {
                    console.error('Error fetching job order details:', error);
                    alert('Error generating PDF: ' + error.message);
                    loadingOverlay.style.display = 'none';
                });
        }

        // Function to generate PDF
        function generatePDF(jobOrder, technicians) {
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

                // Client/Worksite Details Section
                pdf.setFontSize(12);
                pdf.setTextColor(blueColor[0], blueColor[1], blueColor[2]);
                pdf.text('Client/Worksite Details', margin, yPos);

                // Draw a line under the section title
                pdf.setDrawColor(blueColor[0], blueColor[1], blueColor[2]);
                pdf.line(margin, yPos + 1, pageWidth - margin, yPos + 1);

                yPos += 10;

                // Create client details table with proper spacing
                pdf.setTextColor(0, 0, 0);
                pdf.setFontSize(10);
                pdf.setFont('helvetica', 'normal');

                // Improved column layout with better spacing
                const labelWidth = 30; // Width for labels
                const valueWidth = 65; // Width for values
                const leftColStart = margin;
                const rightColStart = margin + contentWidth/2;

                // Client Name
                pdf.setFont('helvetica', 'bold');
                pdf.text('Client Name:', leftColStart, yPos);
                pdf.setFont('helvetica', 'normal');

                // Handle client name with proper wrapping
                const clientNameText = jobOrder.client_name || 'N/A';
                const clientNameLines = pdf.splitTextToSize(clientNameText, valueWidth);
                pdf.text(clientNameLines, leftColStart + labelWidth, yPos);

                // Client Phone
                pdf.setFont('helvetica', 'bold');
                pdf.text('Client Phone Numb:', rightColStart, yPos);
                pdf.setFont('helvetica', 'normal');

                const clientPhoneText = jobOrder.contact_number || 'N/A';
                const clientPhoneLines = pdf.splitTextToSize(clientPhoneText, valueWidth);
                pdf.text(clientPhoneLines, rightColStart + labelWidth + 10, yPos);

                // Calculate max lines for first row to determine vertical offset
                const firstRowMaxLines = Math.max(clientNameLines.length, clientPhoneLines.length);
                yPos += firstRowMaxLines * 5 + 5;

                // Client Address
                pdf.setFont('helvetica', 'bold');
                pdf.text('Client Address:', leftColStart, yPos);
                pdf.setFont('helvetica', 'normal');

                // Handle long addresses with proper wrapping
                const addressText = jobOrder.location_address || 'N/A';
                const addressLines = pdf.splitTextToSize(addressText, valueWidth);
                pdf.text(addressLines, leftColStart + labelWidth, yPos);

                // Calculate the position for Client Email based on address length
                // We'll position the email at the same height as the address but in the right column
                const addressYPos = yPos;

                // Client Email - positioned in the right column
                pdf.setFont('helvetica', 'bold');
                pdf.text('Client Email:', rightColStart, addressYPos);
                pdf.setFont('helvetica', 'normal');

                const clientEmailText = jobOrder.email || 'N/A';
                const clientEmailLines = pdf.splitTextToSize(clientEmailText, valueWidth);
                pdf.text(clientEmailLines, rightColStart + labelWidth + 10, addressYPos);

                // Calculate max lines for second row to determine vertical offset
                // Only use address lines for vertical spacing since email is positioned horizontally
                yPos += addressLines.length * 5 + 5;

                yPos += 15;

                // Order Details Section
                pdf.setFontSize(12);
                pdf.setTextColor(blueColor[0], blueColor[1], blueColor[2]);
                pdf.text('Order Details', margin, yPos);

                // Draw a line under the section title
                pdf.setDrawColor(blueColor[0], blueColor[1], blueColor[2]);
                pdf.line(margin, yPos + 1, pageWidth - margin, yPos + 1);

                yPos += 10;

                // Create order details table using the same improved layout
                pdf.setTextColor(0, 0, 0);
                pdf.setFontSize(10);

                // Date Issued
                pdf.setFont('helvetica', 'bold');
                pdf.text('Date Issued:', leftColStart, yPos);
                pdf.setFont('helvetica', 'normal');
                const issuedDate = jobOrder.preferred_date ? new Date(jobOrder.preferred_date).toLocaleDateString() : 'N/A';
                pdf.text(issuedDate, leftColStart + labelWidth, yPos);

                // Work Order Number
                pdf.setFont('helvetica', 'bold');
                pdf.text('Work Order Number:', rightColStart, yPos);
                pdf.setFont('helvetica', 'normal');
                pdf.text('#' + jobOrder.job_order_id, rightColStart + labelWidth + 10, yPos);

                yPos += 10;

                // Issued By
                pdf.setFont('helvetica', 'bold');
                pdf.text('Issued By:', leftColStart, yPos);
                pdf.setFont('helvetica', 'normal');
                pdf.text('Admin', leftColStart + labelWidth, yPos);

                // Work Performed By
                pdf.setFont('helvetica', 'bold');
                pdf.text('Work Performed By:', rightColStart, yPos);
                pdf.setFont('helvetica', 'normal');

                // Get primary technician name
                let technicianName = 'Not Assigned';
                if (technicians && technicians.length > 0) {
                    const primaryTech = technicians.find(tech => tech.is_primary) || technicians[0];
                    technicianName = primaryTech.tech_fname + ' ' + primaryTech.tech_lname;
                }

                // Handle technician name with proper wrapping
                const techNameLines = pdf.splitTextToSize(technicianName, valueWidth);
                pdf.text(techNameLines, rightColStart + labelWidth + 10, yPos);

                // Adjust vertical position based on technician name length
                const techNameOffset = techNameLines.length > 1 ? (techNameLines.length - 1) * 5 : 0;
                yPos += Math.max(10, techNameOffset + 5);

                yPos += 15;

                // Observation and Recommendation Section
                pdf.setFontSize(12);
                pdf.setTextColor(blueColor[0], blueColor[1], blueColor[2]);
                pdf.text('Observation and Recommendation:', margin, yPos);

                // Draw a line under the section title
                pdf.setDrawColor(blueColor[0], blueColor[1], blueColor[2]);
                pdf.line(margin, yPos + 1, pageWidth - margin, yPos + 1);

                yPos += 10;

                // Observation
                pdf.setTextColor(0, 0, 0);
                pdf.setFontSize(10);
                pdf.setFont('helvetica', 'bold');
                pdf.text('Observation:', margin, yPos);
                yPos += 5;

                pdf.setFont('helvetica', 'normal');
                const observationText = jobOrder.observation_notes || 'No observation notes provided.';
                const observationLines = pdf.splitTextToSize(observationText, contentWidth - 10);
                pdf.text(observationLines, margin + 5, yPos);

                yPos += observationLines.length * 5 + 5;

                // Recommendation
                pdf.setFont('helvetica', 'bold');
                pdf.text('Recommendation:', margin, yPos);
                yPos += 5;

                pdf.setFont('helvetica', 'normal');
                const recommendationText = jobOrder.recommendation || 'No recommendation provided.';
                const recommendationLines = pdf.splitTextToSize(recommendationText, contentWidth - 10);
                pdf.text(recommendationLines, margin + 5, yPos);

                yPos += recommendationLines.length * 5 + 10;

                // Completion Information Section
                pdf.setFontSize(12);
                pdf.setTextColor(blueColor[0], blueColor[1], blueColor[2]);
                pdf.text('Completion Information', margin, yPos);

                // Draw a line under the section title
                pdf.setDrawColor(blueColor[0], blueColor[1], blueColor[2]);
                pdf.line(margin, yPos + 1, pageWidth - margin, yPos + 1);

                yPos += 10;

                // Create completion information table using the same improved layout
                pdf.setTextColor(0, 0, 0);
                pdf.setFontSize(10);

                // Date Completed
                pdf.setFont('helvetica', 'bold');
                pdf.text('Date Completed:', leftColStart, yPos);
                pdf.setFont('helvetica', 'normal');
                const completedDate = jobOrder.completed_date ? new Date(jobOrder.completed_date).toLocaleDateString() : issuedDate;
                pdf.text(completedDate, leftColStart + labelWidth, yPos);

                // Next Job Order Date
                pdf.setFont('helvetica', 'bold');
                pdf.text('Next Job Order Date:', rightColStart, yPos);
                pdf.setFont('helvetica', 'normal');

                // Calculate next job order date based on frequency
                let nextJobDate = 'N/A';
                if (jobOrder.frequency && jobOrder.frequency !== 'one-time' && completedDate) {
                    const date = new Date(completedDate);
                    switch(jobOrder.frequency) {
                        case 'weekly':
                            date.setDate(date.getDate() + 7);
                            break;
                        case 'bi-weekly':
                            date.setDate(date.getDate() + 14);
                            break;
                        case 'monthly':
                            date.setMonth(date.getMonth() + 1);
                            break;
                        case 'quarterly':
                            date.setMonth(date.getMonth() + 3);
                            break;
                        case 'semi-annually':
                            date.setMonth(date.getMonth() + 6);
                            break;
                        case 'annually':
                            date.setFullYear(date.getFullYear() + 1);
                            break;
                    }
                    nextJobDate = date.toLocaleDateString();
                }

                pdf.text(nextJobDate, rightColStart + labelWidth + 10, yPos);

                yPos += 15;

                // Chemical Used Section
                pdf.setFontSize(12);
                pdf.setTextColor(blueColor[0], blueColor[1], blueColor[2]);
                pdf.text('Chemical Used:', margin, yPos);

                // Draw a line under the section title
                pdf.setDrawColor(blueColor[0], blueColor[1], blueColor[2]);
                pdf.line(margin, yPos + 1, pageWidth - margin, yPos + 1);

                yPos += 10;

                // Check if chemical usage data exists
                if (jobOrder.chemical_usage) {
                    try {
                        let chemicals = JSON.parse(jobOrder.chemical_usage);
                        if (chemicals && chemicals.length > 0) {
                            // Process chemicals to ensure recommended dosage is available
                            for (let i = 0; i < chemicals.length; i++) {
                                const chemical = chemicals[i];
                                const chemicalName = chemical.name || '';

                                // Get recommended dosage, first try from chemical data
                                let recommended = chemical.recommended_dosage ? parseFloat(chemical.recommended_dosage) : 0;

                                // Apply fallback mechanisms to find recommended dosage
                                if (recommended === 0 && chemicalName) {
                                    // First try job order chemical recommendations
                                    if (jobOrder.chemical_recommendations) {
                                        try {
                                            const jobOrderChemicals = JSON.parse(jobOrder.chemical_recommendations);
                                            if (Array.isArray(jobOrderChemicals)) {
                                                for (const jobOrderChem of jobOrderChemicals) {
                                                    if (jobOrderChem.name === chemicalName &&
                                                        jobOrderChem.dosage && parseFloat(jobOrderChem.dosage) > 0) {
                                                        recommended = parseFloat(jobOrderChem.dosage);
                                                        // Add to chemical object for future reference
                                                        chemical.recommended_dosage = recommended;
                                                        break;
                                                    }
                                                }
                                            }
                                        } catch (e) {
                                            console.error('Error parsing job order chemical recommendations:', e);
                                        }
                                    }

                                    // If still 0, try assessment report chemical recommendations
                                    if (recommended === 0 && jobOrder.assessment_chemical_recommendations) {
                                        try {
                                            const assessmentChemicals = JSON.parse(jobOrder.assessment_chemical_recommendations);
                                            if (assessmentChemicals) {
                                                // Assessment report might have nested structure by pest type
                                                for (const pestType in assessmentChemicals) {
                                                    const pestChemicals = assessmentChemicals[pestType];
                                                    if (Array.isArray(pestChemicals)) {
                                                        for (const assessmentChem of pestChemicals) {
                                                            if (assessmentChem.chemical_name === chemicalName &&
                                                                assessmentChem.recommended_dosage &&
                                                                parseFloat(assessmentChem.recommended_dosage) > 0) {
                                                                recommended = parseFloat(assessmentChem.recommended_dosage);
                                                                // Add to chemical object for future reference
                                                                chemical.recommended_dosage = recommended;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        } catch (e) {
                                            console.error('Error parsing assessment chemical recommendations:', e);
                                        }
                                    }

                                    // If still 0, use default dosage based on chemical name
                                    if (recommended === 0) {
                                        // Use specific dosage rates for known chemicals (for 100 sqm)
                                        if (chemicalName.toLowerCase().includes('fipronil')) {
                                            recommended = 12; // 12ml per 100sqm
                                        } else if (chemicalName.toLowerCase().includes('cypermethrin')) {
                                            recommended = 20; // 20ml per 100sqm
                                        } else if (chemicalName.toLowerCase().includes('imidacloprid')) {
                                            recommended = 10; // 10ml per 100sqm
                                        }

                                        // If we found a default dosage, add it to the chemical data
                                        if (recommended > 0) {
                                            chemical.recommended_dosage = recommended;
                                        }
                                    }
                                }
                            }

                            // Extract chemical names
                            const chemicalNames = chemicals.map(chem => chem.name || 'Unknown Chemical');

                            // Display chemical names
                            pdf.setFont('helvetica', 'normal');
                            pdf.setFontSize(10);
                            pdf.setTextColor(0, 0, 0);

                            // Format the chemical names with proper spacing
                            const chemicalText = chemicalNames.join(', ');
                            const chemicalLines = pdf.splitTextToSize(chemicalText, contentWidth - 10);

                            if (chemicalLines.length > 0) {
                                pdf.text(chemicalLines, margin + 5, yPos);
                                yPos += chemicalLines.length * 5 + 5;
                            }

                            // Add chemical usage table if there's enough space
                            if (yPos < pageHeight - 60) {
                                // Chemical Usage Table with improved layout
                                const tableTop = yPos;
                                // Adjust column widths to better fit content
                                const colWidths = [35, 25, 40, 30, 30, 30];
                                const rowHeight = 12; // Increased row height for better readability

                                // Table headers
                                pdf.setFont('helvetica', 'bold');
                                pdf.setFillColor(240, 240, 240);
                                pdf.rect(margin, tableTop, contentWidth, rowHeight, 'F');

                                let colX = margin;
                                pdf.text('Chemical Name', colX + 2, tableTop + 7);
                                colX += colWidths[0];

                                pdf.text('Type', colX + 2, tableTop + 7);
                                colX += colWidths[1];

                                pdf.text('Target Pest', colX + 2, tableTop + 7);
                                colX += colWidths[2];

                                pdf.text('Recommended', colX + 2, tableTop + 7);
                                colX += colWidths[3];

                                pdf.text('Actual Used', colX + 2, tableTop + 7);
                                colX += colWidths[4];

                                pdf.text('Status', colX + 2, tableTop + 7);

                                // Draw table grid for header
                                pdf.setDrawColor(200, 200, 200);
                                pdf.rect(margin, tableTop, contentWidth, rowHeight);

                                colX = margin;
                                for (let i = 0; i < colWidths.length - 1; i++) {
                                    colX += colWidths[i];
                                    pdf.line(colX, tableTop, colX, tableTop + rowHeight);
                                }

                                // Table rows
                                let rowY = tableTop + rowHeight;

                                pdf.setFont('helvetica', 'normal');

                                for (let i = 0; i < chemicals.length; i++) {
                                    const chemical = chemicals[i];

                                    // Check if we need a new page
                                    if (rowY + rowHeight > pageHeight - 20) {
                                        pdf.addPage();
                                        rowY = margin;

                                        // Add table headers to new page
                                        pdf.setFont('helvetica', 'bold');
                                        pdf.setFillColor(240, 240, 240);
                                        pdf.rect(margin, rowY, contentWidth, rowHeight, 'F');

                                        colX = margin;
                                        pdf.text('Chemical Name', colX + 2, rowY + 7);
                                        colX += colWidths[0];

                                        pdf.text('Type', colX + 2, rowY + 7);
                                        colX += colWidths[1];

                                        pdf.text('Target Pest', colX + 2, rowY + 7);
                                        colX += colWidths[2];

                                        pdf.text('Recommended', colX + 2, rowY + 7);
                                        colX += colWidths[3];

                                        pdf.text('Actual Used', colX + 2, rowY + 7);
                                        colX += colWidths[4];

                                        pdf.text('Status', colX + 2, rowY + 7);

                                        // Draw table grid for header
                                        pdf.setDrawColor(200, 200, 200);
                                        pdf.rect(margin, rowY, contentWidth, rowHeight);

                                        colX = margin;
                                        for (let j = 0; j < colWidths.length - 1; j++) {
                                            colX += colWidths[j];
                                            pdf.line(colX, rowY, colX, rowY + rowHeight);
                                        }

                                        rowY += rowHeight;
                                        pdf.setFont('helvetica', 'normal');
                                    }

                                    // Get recommended dosage from chemical data
                                    // This should already be processed and available from the server-side processing
                                    const recommended = chemical.recommended_dosage ? parseFloat(chemical.recommended_dosage) : 0;

                                    const actual = chemical.dosage ? parseFloat(chemical.dosage) : 0;
                                    const minAcceptable = recommended * 0.8;
                                    const maxAcceptable = recommended * 1.2;

                                    let status = '';
                                    if (actual < minAcceptable) {
                                        status = 'Under-dosed';
                                        pdf.setTextColor(255, 150, 0); // Orange for under-dosed
                                    } else if (actual > maxAcceptable) {
                                        status = 'Over-dosed';
                                        pdf.setTextColor(255, 0, 0); // Red for over-dosed
                                    } else {
                                        status = 'Optimal';
                                        pdf.setTextColor(0, 150, 0); // Green for optimal
                                    }

                                    // Draw row background
                                    pdf.setFillColor(i % 2 === 0 ? 255 : 245, i % 2 === 0 ? 255 : 245, i % 2 === 0 ? 255 : 245);
                                    pdf.rect(margin, rowY, contentWidth, rowHeight, 'F');

                                    // Function to handle cell text that might be too long
                                    function drawCellText(text, x, y, maxWidth) {
                                        if (!text) text = 'N/A';

                                        // Check if text needs to be wrapped
                                        if (pdf.getStringUnitWidth(text) * 10 > maxWidth - 4) {
                                            // Split text into multiple lines if needed
                                            const lines = pdf.splitTextToSize(text, maxWidth - 4);
                                            // Only use the first line and add ellipsis if needed
                                            if (lines.length > 1) {
                                                text = lines[0].substring(0, lines[0].length - 3) + '...';
                                            }
                                        }

                                        pdf.text(text, x, y);
                                    }

                                    // Draw cell content with improved text handling
                                    pdf.setTextColor(0, 0, 0);

                                    colX = margin;
                                    drawCellText(chemical.name || 'N/A', colX + 2, rowY + 7, colWidths[0]);
                                    colX += colWidths[0];

                                    drawCellText(chemical.type || 'N/A', colX + 2, rowY + 7, colWidths[1]);
                                    colX += colWidths[1];

                                    drawCellText(chemical.target_pest || 'N/A', colX + 2, rowY + 7, colWidths[2]);
                                    colX += colWidths[2];

                                    const unit = chemical.dosage_unit || 'ml';
                                    pdf.text(`${recommended} ${unit}`, colX + 2, rowY + 7);
                                    colX += colWidths[3];

                                    pdf.text(`${actual} ${unit}`, colX + 2, rowY + 7);
                                    colX += colWidths[4];

                                    // Status with color
                                    if (actual < minAcceptable) {
                                        pdf.setTextColor(255, 150, 0); // Orange for under-dosed
                                    } else if (actual > maxAcceptable) {
                                        pdf.setTextColor(255, 0, 0); // Red for over-dosed
                                    } else {
                                        pdf.setTextColor(0, 150, 0); // Green for optimal
                                    }
                                    pdf.text(status, colX + 2, rowY + 7);
                                    pdf.setTextColor(0, 0, 0);

                                    // Draw cell borders
                                    pdf.setDrawColor(200, 200, 200);
                                    pdf.rect(margin, rowY, contentWidth, rowHeight);

                                    colX = margin;
                                    for (let j = 0; j < colWidths.length - 1; j++) {
                                        colX += colWidths[j];
                                        pdf.line(colX, rowY, colX, rowY + rowHeight);
                                    }

                                    rowY += rowHeight;
                                }

                                // Add note about optimal usage
                                rowY += 5;
                                pdf.setFontSize(8);
                                pdf.setTextColor(100, 100, 100);
                                pdf.text('* Chemical usage is considered optimal when within ±20% of the recommended dosage.', margin, rowY);

                                yPos = rowY + 10;
                            }
                        } else {
                            pdf.setFont('helvetica', 'italic');
                            pdf.setFontSize(10);
                            pdf.setTextColor(100, 100, 100);
                            pdf.text('No chemicals recorded', margin + 5, yPos);
                            yPos += 10;
                        }
                    } catch (error) {
                        console.error('Error parsing chemical usage data:', error);
                        pdf.setFont('helvetica', 'italic');
                        pdf.setFontSize(10);
                        pdf.setTextColor(100, 100, 100);
                        pdf.text('Error displaying chemical usage data', margin + 5, yPos);
                        yPos += 10;
                    }
                } else {
                    pdf.setFont('helvetica', 'italic');
                    pdf.setFontSize(10);
                    pdf.setTextColor(100, 100, 100);
                    pdf.text('No chemicals recorded', margin + 5, yPos);
                    yPos += 10;
                }

                // Add footer with company information
                pdf.setFontSize(8);
                pdf.setTextColor(100, 100, 100);
                pdf.text('MacJ Pest Control Services', pageWidth / 2, pageHeight - 10, { align: 'center' });

                // Set PDF filename
                const filename = `Maintenance_Work_Order_${jobOrder.job_order_id}.pdf`;

                // Save the PDF
                pdf.save(filename);

                // Hide loading overlay
                document.getElementById('pdfLoadingOverlay').style.display = 'none';

                // Show success message
                alert('PDF has been generated successfully!');
            } catch (error) {
                console.error('Error generating PDF:', error);
                document.getElementById('pdfLoadingOverlay').style.display = 'none';
                alert('Error generating PDF: ' + error.message);
            }
        }
    </script>
</body>
</html>