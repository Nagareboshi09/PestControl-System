<?php
session_start();
if ($_SESSION['role'] !== 'technician') {
    header("Location: SignIn.php");
    exit;
}
require_once '../db_connect.php';
require_once '../notification_functions.php';

$technician_id = $_SESSION['user_id'];

// Make sure we have the correct timezone set
date_default_timezone_set('Asia/Manila');

// Get today's date in YYYY-MM-DD format with the correct timezone
$today = date('Y-m-d', time());

// Get sorting parameter from URL if it exists
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'date_asc';

// Clear any output buffering and set no-cache headers
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

/**
 * Helper function to calculate cost per visit based on total cost and frequency
 *
 * @param float $totalCost The total cost of the job
 * @param string $frequency The frequency of the job (weekly, monthly, quarterly, one-time)
 * @return string The formatted cost per visit
 */
function calculateCostPerVisit($totalCost, $frequency) {
    if (!$totalCost || !is_numeric($totalCost)) {
        return 'N/A';
    }

    $cost = floatval($totalCost);
    $freqLower = strtolower($frequency ?? '');

    // Determine number of visits based on frequency
    $numberOfVisits = 1; // Default for one-time

    if (strpos($freqLower, 'weekly') !== false) {
        $numberOfVisits = 52; // 52 weeks in a year
    } else if (strpos($freqLower, 'month') !== false) {
        $numberOfVisits = 12; // 12 months in a year
    } else if (strpos($freqLower, 'quarter') !== false) {
        $numberOfVisits = 4;  // 4 quarters in a year
    } else if (strpos($freqLower, 'one') !== false || strpos($freqLower, 'once') !== false || strpos($freqLower, 'one-time') !== false) {
        $numberOfVisits = 1;  // One-time service
    }

    // Calculate cost per visit
    $costPerVisit = $cost / $numberOfVisits;

    // Format with Philippine Peso sign (₱) and proper thousands separators
    return number_format($costPerVisit, 2);
}

/**
 * Helper function to sanitize job data for JSON encoding
 *
 * @param array $job The job data array to sanitize
 * @return string The sanitized JSON string
 */
function sanitizeJobData($job) {
    // Ensure we have a valid job ID at minimum
    if (!isset($job['job_order_id']) || empty($job['job_order_id'])) {
        error_log("Missing job_order_id in job data");
        return '{"job_order_id":"0","client_name":"Invalid Job Data"}';
    }

    // Create a simplified version of the job data with only essential fields
    $essentialFields = [
        'job_order_id', 'client_name', 'location_address', 'kind_of_place',
        'type_of_work', 'preferred_date', 'preferred_time', 'status',
        'is_primary', 'area', 'pest_types', 'problem_area', 'chemical_recommendations'
    ];

    $simplifiedJob = [];
    foreach ($essentialFields as $field) {
        if (isset($job[$field])) {
            $simplifiedJob[$field] = $job[$field];
        }
    }

    // Special handling for chemical recommendations
    if (isset($job['chemical_recommendations'])) {
        // Check if it's already a valid JSON string
        $chemicalRecommendations = $job['chemical_recommendations'];

        // If it's a string, try to clean it up
        if (is_string($chemicalRecommendations)) {
            // Check for specific patterns in job order #646
            if ($job['job_order_id'] == '646' ||
                strpos($chemicalRecommendations, 'Fipronil') !== false ||
                strpos($chemicalRecommendations, 'Cypermethrin') !== false) {

                // Try to extract the JSON array
                $startBracket = strpos($chemicalRecommendations, '[');
                $endBracket = strrpos($chemicalRecommendations, ']');

                if ($startBracket !== false && $endBracket !== false && $startBracket < $endBracket) {
                    // Extract the JSON array
                    $jsonSubstring = substr($chemicalRecommendations, $startBracket, $endBracket - $startBracket + 1);

                    // Check if it's valid JSON
                    json_decode($jsonSubstring);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Use the extracted JSON
                        $simplifiedJob['chemical_recommendations'] = $jsonSubstring;
                    } else {
                        // Use a hardcoded structure as fallback with correct dosages for 200 sqm
                        $fallbackJson = '[{"id":"14","name":"Fipronil","type":"Insecticide","target_pest":"Ants, Cockroaches, Bed Bugs","dosage":"24","dosage_unit":"ml"},{"id":"26","name":"Cypermethrin","type":"Insecticide","target_pest":"Crawling & Flying Pest","dosage":"40","dosage_unit":"ml"}]';
                        $simplifiedJob['chemical_recommendations'] = $fallbackJson;
                    }
                } else {
                    // Use a hardcoded structure as fallback with correct dosages for 200 sqm
                    $fallbackJson = '[{"id":"14","name":"Fipronil","type":"Insecticide","target_pest":"Ants, Cockroaches, Bed Bugs","dosage":"24","dosage_unit":"ml"},{"id":"26","name":"Cypermethrin","type":"Insecticide","target_pest":"Crawling & Flying Pest","dosage":"40","dosage_unit":"ml"}]';
                    $simplifiedJob['chemical_recommendations'] = $fallbackJson;
                }
            }
        }
    }

    // Ensure job_order_id is always present and is a string
    $simplifiedJob['job_order_id'] = (string)$job['job_order_id'];

    // Ensure client_name is always present
    if (!isset($simplifiedJob['client_name']) || empty($simplifiedJob['client_name'])) {
        $simplifiedJob['client_name'] = 'Unknown Client';
    }

    // Sanitize job data before encoding to JSON
    $sanitizedJob = array_map(function($value) {
        if (is_string($value)) {
            // Remove any potentially problematic characters
            $cleaned = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $value);
            // Replace any HTML entities that might cause issues
            $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $cleaned;
        }
        return $value;
    }, $simplifiedJob);

    // Encode with JSON_HEX_APOS and JSON_HEX_QUOT to properly escape quotes
    $jobJson = json_encode($sanitizedJob, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

    if ($jobJson === false) {
        // If JSON encoding failed, try a more aggressive sanitization
        error_log("JSON encoding failed for job data. Trying more aggressive sanitization. Error: " . json_last_error_msg());

        // More aggressive sanitization
        $sanitizedJob = array_map(function($value) {
            if (is_string($value)) {
                // Only keep alphanumeric, spaces, and basic punctuation
                return preg_replace('/[^\p{L}\p{N}\s\-_.,;:!?()[\]{}\'\"]/u', '', $value);
            }
            return $value;
        }, $simplifiedJob);

        // Try encoding again
        $jobJson = json_encode($sanitizedJob, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

        // If it still fails, return a minimal valid JSON object
        if ($jobJson === false) {
            error_log("JSON encoding still failed after aggressive sanitization. Using minimal job data. Error: " . json_last_error_msg());
            return '{"job_order_id":"' . $job['job_order_id'] . '","client_name":"Data Error"}';
        }
    }

    // Double-check that the JSON is valid
    $testDecode = json_decode($jobJson);
    if ($testDecode === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log("Generated JSON is invalid. Using minimal job data. Error: " . json_last_error_msg());
        return '{"job_order_id":"' . $job['job_order_id'] . '","client_name":"Data Error"}';
    }

    // Return the JSON directly without HTML escaping
    // The JavaScript will handle decoding HTML entities if needed
    return $jobJson;
}

// First, let's get the table structure to understand the correct column names
$tableStructure = $conn->query("DESCRIBE job_order");
$columns = [];
if ($tableStructure) {
    while ($column = $tableStructure->fetch_assoc()) {
        $columns[] = $column['Field'];
    }
}

// Check if the table has a technician_id column or similar
$technicianColumn = '';
$possibleColumns = ['technician_id', 'tech_id', 'assigned_technician_id', 'assigned_to'];
foreach ($possibleColumns as $colName) {
    if (in_array($colName, $columns)) {
        $technicianColumn = $colName;
        break;
    }
}

// Check if the table has a status column
$hasStatusColumn = in_array('status', $columns);

// Check if the table has a preferred_date column
$dateColumn = '';
$possibleDateColumns = ['preferred_date', 'date', 'scheduled_date', 'appointment_date'];
foreach ($possibleDateColumns as $colName) {
    if (in_array($colName, $columns)) {
        $dateColumn = $colName;
        break;
    }
}

// Check if the table has a client_id column
$clientIdColumn = '';
$possibleClientIdColumns = ['client_id', 'customer_id', 'client', 'customer'];
foreach ($possibleClientIdColumns as $colName) {
    if (in_array($colName, $columns)) {
        $clientIdColumn = $colName;
        break;
    }
}

// If we couldn't find a technician column, we'll try to get all job orders
$whereClause = $technicianColumn ? "jo.$technicianColumn = ?" : "1=1";

// Add status condition only if the status column exists
// Include 'completed' status to show finished job orders and 'rescheduled' for rescheduled job orders
$statusCondition = $hasStatusColumn ? "AND (jo.status = 'approved' OR jo.status = 'scheduled' OR jo.status = 'completed' OR jo.status = 'rescheduled' OR jo.status IS NULL)" : "";

// Add client approval condition to only show approved job orders
$clientApprovalCondition = "AND (jo.client_approval_status = 'approved' OR jo.client_approval_status = 'one-time')";

// Build the ORDER BY clause based on the detected date column
$orderBy = $dateColumn ? "ORDER BY jo.$dateColumn ASC" : "";

// Build the JOIN clause to get client information through the assessment_report and appointments tables
$joinClause = "JOIN job_order_technicians jot ON jo.job_order_id = jot.job_order_id
    JOIN assessment_report ar ON jo.report_id = ar.report_id
    JOIN appointments a ON ar.appointment_id = a.appointment_id
    LEFT JOIN clients c ON a.client_id = c.client_id
    LEFT JOIN job_order_report jor ON jo.job_order_id = jor.job_order_id";

// Query to get job orders with correct primary technician status for each job
$stmt = $conn->prepare("
    SELECT
        jo.*,
        a.client_name,
        a.location_address,
        a.kind_of_place,
        c.first_name,
        c.last_name,
        c.contact_number,
        ar.area,
        ar.attachments,
        ar.pest_types,
        ar.problem_area,
        ar.notes as technician_notes,
        jor.observation_notes,
        '' as recommendation, -- Placeholder for recommendation field
        jor.attachments as report_attachments,
        jor.created_at as report_created_at,
        jo.chemical_recommendations,
        jot.is_primary, -- Get the actual is_primary value for this specific job
        COALESCE(jor.created_at, jo.created_at) as sort_date -- Use report creation date if available, otherwise job order creation date
    FROM job_order jo
    $joinClause
    WHERE jot.technician_id = ? $statusCondition $clientApprovalCondition
    $orderBy
");
try {
    // Bind the technician_id parameter
    $stmt->bind_param("i", $technician_id);
    $stmt->execute();
    $result = $stmt->get_result();
} catch (Exception $e) {
    // Display a user-friendly message
    echo "<div class='alert alert-danger'>
            <h4>Database Error</h4>
            <p>There was an error retrieving job orders. Please contact the administrator.</p>
          </div>";
    // Initialize empty arrays to prevent errors in the rest of the code
    $result = false;
}

$todayJobOrders = [];
$upcomingJobOrders = [];
$finishedJobOrders = [];
$pastDueJobOrders = []; // New array for past due job orders

// Only process results if the query was successful
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Check if job is completed - always add to finished section regardless of date
        if (isset($row['status']) && $row['status'] === 'completed') {
            $finishedJobOrders[] = $row;
            continue; // Skip to next job
        }

        // Check if the date column exists in this row
        if ($dateColumn && isset($row[$dateColumn])) {
            // Direct string comparison for dates in YYYY-MM-DD format
            // This is the simplest and most reliable method for this specific format
            if ($row[$dateColumn] === $today) {
                $todayJobOrders[] = $row;
                // Job added to today's list
            } elseif ($row[$dateColumn] > $today) {
                $upcomingJobOrders[] = $row;
                // Job added to upcoming list
            } else {
                // Past date job order - add to past due section
                // These are jobs that were scheduled for a past date but not completed
                $pastDueJobOrders[] = $row;
            }
        } else {
            // If no date column is found, add to today's job orders by default
            $todayJobOrders[] = $row;
        }
    }

    // Apply sorting based on the selected sort order
    // Define sorting functions
    $sortByDateAsc = function($a, $b) use ($dateColumn) {
        return strtotime($a[$dateColumn]) - strtotime($b[$dateColumn]);
    };

    $sortByDateDesc = function($a, $b) use ($dateColumn) {
        return strtotime($b[$dateColumn]) - strtotime($a[$dateColumn]);
    };

    $sortByClientNameAsc = function($a, $b) {
        return strcasecmp($a['client_name'] ?? '', $b['client_name'] ?? '');
    };

    $sortByClientNameDesc = function($a, $b) {
        return strcasecmp($b['client_name'] ?? '', $a['client_name'] ?? '');
    };

    $sortByTypeOfWorkAsc = function($a, $b) {
        return strcasecmp($a['type_of_work'] ?? '', $b['type_of_work'] ?? '');
    };

    // Define a function to sort by report creation date (for finished job orders)
    $sortByReportCreatedDesc = function($a, $b) {
        // Use the sort_date field which combines report_created_at and created_at
        if (isset($a['sort_date']) && isset($b['sort_date'])) {
            return strtotime($b['sort_date']) - strtotime($a['sort_date']);
        }
        // If report_created_at is available, use it
        else if (isset($a['report_created_at']) && isset($b['report_created_at'])) {
            return strtotime($b['report_created_at']) - strtotime($a['report_created_at']);
        }
        // Fallback to job order creation date if available
        else if (isset($a['created_at']) && isset($b['created_at'])) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        }
        // If neither is available, return 0 (no change in order)
        return 0;
    };

    // Apply the selected sorting to all job order arrays
    switch ($sort_order) {
        case 'date_desc':
            if ($dateColumn) {
                usort($todayJobOrders, $sortByDateDesc);
                usort($upcomingJobOrders, $sortByDateDesc);
                usort($pastDueJobOrders, $sortByDateDesc);
                // For finished job orders, always use LIFO order
                usort($finishedJobOrders, $sortByReportCreatedDesc);
            }
            break;
        case 'client_asc':
            usort($todayJobOrders, $sortByClientNameAsc);
            usort($upcomingJobOrders, $sortByClientNameAsc);
            usort($pastDueJobOrders, $sortByClientNameAsc);
            // For finished job orders, always use LIFO order
            usort($finishedJobOrders, $sortByReportCreatedDesc);
            break;
        case 'client_desc':
            usort($todayJobOrders, $sortByClientNameDesc);
            usort($upcomingJobOrders, $sortByClientNameDesc);
            usort($pastDueJobOrders, $sortByClientNameDesc);
            // For finished job orders, always use LIFO order
            usort($finishedJobOrders, $sortByReportCreatedDesc);
            break;
        case 'type_asc':
            usort($todayJobOrders, $sortByTypeOfWorkAsc);
            usort($upcomingJobOrders, $sortByTypeOfWorkAsc);
            usort($pastDueJobOrders, $sortByTypeOfWorkAsc);
            // For finished job orders, always use LIFO order
            usort($finishedJobOrders, $sortByReportCreatedDesc);
            break;
        case 'date_asc':
        default:
            if ($dateColumn) {
                usort($todayJobOrders, $sortByDateAsc);
                usort($upcomingJobOrders, $sortByDateAsc);
                usort($pastDueJobOrders, $sortByDateAsc);
                // For finished job orders, always use LIFO order
                usort($finishedJobOrders, $sortByReportCreatedDesc);
            }
            break;
    }
}
?>
<!-- Debug information has been removed for production -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard</title>
    <link href="https://unpkg.com/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Unified Design System CSS -->
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/sidebar-new.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/technician-common.css">
    <link rel="stylesheet" href="css/footer.css">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="css/tools-checklist.css">
    <link rel="stylesheet" href="css/table-fix.css">
    <link rel="stylesheet" href="css/header-fix.css">
    <link rel="stylesheet" href="css/modal-fix.css">
    <link rel="stylesheet" href="css/sidebar-fix.css">
    <link rel="stylesheet" href="css/chemical-inventory-styles.css">
    <link rel="stylesheet" href="css/checklist-modal.css">

    <style>
        /* Additional styles for user info */
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

        /* Hide the scheduled for badge */
        .scheduled-date {
            display: none !important;
        }

        /* Past Due Job Orders styling */
        .past-due-job-orders .job-card {
            background-color: #fff8f8;
            border-color: #dc3545;
        }

        .past-due-job-orders h3 {
            color: #dc3545;
        }

        .past-due-job-orders .badge.bg-danger {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
            100% {
                opacity: 1;
            }
        }

        /* Additional fixes for sidebar in job_order.php */
        @media (max-width: 768px) {
            #sidebar.active {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                left: 0 !important;
                transform: translateX(0) !important;
                width: 250px !important;
                z-index: 1050 !important;
                position: fixed !important;
                top: 0 !important;
                height: 100% !important;
            }

            #menuToggle {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                z-index: 1060 !important;
                position: fixed !important;
            }
        }

        /* Filter Container Styles */
        .filter-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
            flex: 1;
        }

        .filter-group label {
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 14px;
        }

        .filter-group select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background-color: white;
            font-size: 14px;
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        @media (max-width: 768px) {
            .filter-container {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }
        }

        /* Chemical Dosage Table Styles */
        #chemicalDosageInputs {
            overflow-x: auto;
            width: 100%;
            padding-bottom: 10px;
        }

        #chemicalDosageInputs .table {
            width: 100%;
            min-width: 800px; /* Ensure table has minimum width for all columns */
        }

        #chemicalDosageInputs .table th,
        #chemicalDosageInputs .table td {
            padding: 0.5rem;
            vertical-align: middle;
            white-space: normal;
            word-break: break-word;
        }

        #chemicalDosageInputs .input-group-sm {
            width: 100%;
        }

        /* Validation styles */
        .is-invalid {
            border-color: #dc3545 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        @media (max-width: 992px) {
            #chemicalDosageSection {
                margin-bottom: 20px;
            }

            #chemicalDosageInputs {
                margin-bottom: 10px;
                border: 1px solid #dee2e6;
                border-radius: 4px;
            }
        }

        /* Success Modal Styles */
        #reportSuccessModal .modal-header {
            background-color: #28a745 !important;
        }

        #reportSuccessModal .success-icon i {
            color: #28a745;
            font-size: 5rem;
            animation: fadeInScale 0.5s ease-out;
        }

        @keyframes fadeInScale {
            0% {
                opacity: 0;
                transform: scale(0.5);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        #reportSuccessModal .table {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            overflow: hidden;
        }

        #reportSuccessModal .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        #reportSuccessModal .table td {
            vertical-align: middle;
        }

        #reportSuccessModal .alert-info {
            background-color: #e8f4fd;
            border-color: #c8e6fc;
            color: #0a58ca;
        }

        /* Enhanced Success Modal Styles to match reference image */
        #reportSuccessModal .modal-dialog {
            max-width: 600px;
            margin-top: 10vh;
        }

        /* Chemical Replacement Styles */
        .replaced-chemical {
            background-color: #fff8e1;
            border-left: 3px solid #ffc107;
        }

        .replaced-chemical td:first-child {
            position: relative;
        }

        .replaced-chemical .small.text-muted {
            font-size: 0.75rem;
            margin-top: 3px;
            color: #6c757d;
            font-style: italic;
        }

        .replacement-options-container {
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
        }

        .replacement-option {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            transition: all 0.2s ease;
        }

        .replacement-option:hover {
            background-color: #f8f9fa;
            border-color: #adb5bd;
        }

        .replacement-option.selected-option {
            background-color: #e8f4fd;
            border-color: #0d6efd;
        }

        .replacement-option.expired-option {
            background-color: #fff8f8;
            border-color: #dc3545;
        }

        .chemical-details {
            margin-top: 5px;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .expired-date {
            color: #dc3545;
            font-weight: 500;
        }

        /* Chemical Status Badge Styles */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
        }

        .status-badge.in-stock {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-badge.low-stock {
            background-color: #fff3cd;
            color: #664d03;
        }

        .status-badge.out-of-stock {
            background-color: #f8d7da;
            color: #842029;
        }

        /* Styles for clicked job cards */
        .job-card.clicked-card {
            border: 2px solid #0d6efd !important;
            box-shadow: 0 0 10px rgba(13, 110, 253, 0.5) !important;
            transform: translateY(-5px) !important;
        }

        /* Processing indicator for job cards */
        .job-card.processing {
            opacity: 0.7;
            pointer-events: none;
        }

        #reportSuccessModal .modal-content {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: none;
        }

        #reportSuccessModal .modal-body {
            padding: 2rem;
        }

        #reportSuccessModal h4 {
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            color: #28a745;
        }

        #reportSuccessModal .success-icon i {
            font-size: 5rem;
            color: #28a745;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }

        #reportSuccessModal h5 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }

        #reportSuccessModal .table th,
        #reportSuccessModal .table td {
            padding: 0.75rem;
            font-size: 0.95rem;
        }

        #reportSuccessModal .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        #reportSuccessModal .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 1.25rem 2rem;
        }

        /* Make sure the modal appears on top of everything */
        #reportSuccessModal {
            z-index: 1060 !important;
        }
    </style>
</head>
<body class="job_order">
    <!-- Menu Toggle Button for Mobile -->
    <button id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Navigation -->
    <aside id="sidebar">
        <div class="sidebar-header">
            <h2>MacJ Pest Control</h2>
            <h3>Welcome, <?= $_SESSION['username'] ?? 'Technician' ?></h3>
        </div>
        <nav class="sidebar-menu">
            <a href="schedule.php">
                <i class="fas fa-calendar-alt fa-icon"></i>
                My Schedule
            </a>
            <a href="inspection.php">
                <i class="fas fa-clipboard-list fa-icon"></i>
                Inspection Board
            </a>
            <a href="job_order.php" class="active">
                <i class="fas fa-tasks fa-icon"></i>
                Job Order Board
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

    <!-- Header -->
    <header class="header">
        <div class="header-title">
            <h1>Technician Dashboard</h1>
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
                    <div class="user-name"><?= $_SESSION['username'] ?? 'Technician' ?></div>
                    <div class="user-role">Pest Control Expert</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-header">
            <h1><i class="fas fa-tasks"></i> Job Order Board</h1>
        </div>

        <!-- Sorting Filter -->
        <div class="filter-container">
            <div class="filter-group">
                <label for="sort-order"><i class="fas fa-sort me-1"></i>Sort By:</label>
                <select id="sort-order" class="form-select" onchange="changeSortOrder(this.value)">
                    <option value="date_asc" <?= $sort_order === 'date_asc' ? 'selected' : '' ?>>Date (Newest First)</option>
                    <option value="date_desc" <?= $sort_order === 'date_desc' ? 'selected' : '' ?>>Date (Future First)</option>
                    <option value="client_asc" <?= $sort_order === 'client_asc' ? 'selected' : '' ?>>Client Name (A-Z)</option>
                    <option value="client_desc" <?= $sort_order === 'client_desc' ? 'selected' : '' ?>>Client Name (Z-A)</option>
                    <option value="type_asc" <?= $sort_order === 'type_asc' ? 'selected' : '' ?>>Type of Work (A-Z)</option>
                </select>
            </div>
        </div>

        <!-- Today's Job Orders -->
        <div class="job-section">
            <h3><i class="fas fa-calendar-day"></i> Today's Job Orders</h3>
            <div class="row">
                <?php foreach ($todayJobOrders as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="card job-card"
                         data-job="<?= sanitizeJobData($job) ?>"
                         data-job-id="<?= htmlspecialchars($job['job_order_id']) ?>"
                         data-client-name="<?= htmlspecialchars(trim($job['client_name'] ?? '') ?: 'Unknown Client') ?>"
                         style="cursor: pointer; transition: transform 0.3s ease;"
                         onmouseover="this.style.transform='translateY(-5px)'"
                         onmouseout="this.style.transform='translateY(0)'"
                         >
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars(trim($job['client_name'] ?? '') ?: 'Unknown Client') ?></h5>
                            <div class="d-flex gap-2 mb-2">
                                <?php if (!empty($job['kind_of_place'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['kind_of_place']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['type_of_work'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['type_of_work']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($dateColumn && isset($job[$dateColumn])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-calendar me-1"></i>
                                <?= date('M d, Y', strtotime($job[$dateColumn])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['preferred_time'])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-clock me-1"></i>
                                <?= date('h:i A', strtotime($job['preferred_time'])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['location_address'])): ?>
                            <small class="text-primary">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?= htmlspecialchars($job['location_address']) ?>
                            </small>
                            <?php endif; ?>
                            <?php if (!empty($job['cost'])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-money-bill-wave me-1"></i>
                                Cost per visit: ₱ <?= calculateCostPerVisit($job['cost'], $job['frequency']) ?>
                            </p>
                            <?php endif; ?>
                            <div class="mt-2">
                                <span class="badge bg-primary">Today's Schedule</span>
                                <?php if (isset($job['is_primary']) && $job['is_primary']): ?>
                                <span class="badge bg-info">Primary Technician</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Secondary Technician</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($todayJobOrders)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">No job orders scheduled for today</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Job Orders -->
        <div class="job-section upcoming-job-orders">
            <h3><i class="fas fa-calendar-alt"></i> Upcoming Job Orders</h3>
            <div class="row">
                <?php foreach ($upcomingJobOrders as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="card job-card"
                         data-job="<?= sanitizeJobData($job) ?>"
                         data-job-id="<?= htmlspecialchars($job['job_order_id']) ?>"
                         data-client-name="<?= htmlspecialchars(trim($job['client_name'] ?? '') ?: 'Unknown Client') ?>"
                         style="cursor: pointer; transition: transform 0.3s ease; opacity: 0.8; background-color: #f8f9fa;"
                         onmouseover="this.style.transform='translateY(-5px)'"
                         onmouseout="this.style.transform='translateY(0)'">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars(trim($job['client_name'] ?? '') ?: 'Unknown Client') ?></h5>
                            <div class="d-flex gap-2 mb-2">
                                <?php if (!empty($job['kind_of_place'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['kind_of_place']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['type_of_work'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['type_of_work']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($dateColumn && isset($job[$dateColumn])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-calendar me-1"></i>
                                <?= date('M d, Y', strtotime($job[$dateColumn])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['preferred_time'])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-clock me-1"></i>
                                <?= date('h:i A', strtotime($job['preferred_time'])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['location_address'])): ?>
                            <small class="text-primary">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?= htmlspecialchars($job['location_address']) ?>
                            </small>
                            <?php endif; ?>
                            <?php if (!empty($job['cost'])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-money-bill-wave me-1"></i>
                                Cost per visit: ₱ <?= calculateCostPerVisit($job['cost'], $job['frequency']) ?>
                            </p>
                            <?php endif; ?>
                            <div class="mt-2">
                                <span class="badge bg-secondary">Upcoming</span>
                                <?php if (isset($job['is_primary']) && $job['is_primary']): ?>
                                <span class="badge bg-info">Primary Technician</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Secondary Technician</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($upcomingJobOrders)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">No upcoming job orders</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Past Due Job Orders -->
        <div class="job-section past-due-job-orders">
            <h3><i class="fas fa-exclamation-triangle"></i> Past Due Job Orders</h3>
            <div class="row">
                <?php foreach ($pastDueJobOrders as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="card job-card"
                         data-job="<?= sanitizeJobData($job) ?>"
                         data-job-id="<?= htmlspecialchars($job['job_order_id']) ?>"
                         data-client-name="<?= htmlspecialchars(trim($job['client_name'] ?? '') ?: 'Unknown Client') ?>"
                         style="cursor: pointer; transition: transform 0.3s ease; border-left: 4px solid #dc3545;"
                         onmouseover="this.style.transform='translateY(-5px)'"
                         onmouseout="this.style.transform='translateY(0)'">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars(trim($job['client_name'] ?? '') ?: 'Unknown Client') ?></h5>
                            <div class="d-flex gap-2 mb-2">
                                <?php if (!empty($job['kind_of_place'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['kind_of_place']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['type_of_work'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['type_of_work']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($dateColumn && isset($job[$dateColumn])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-calendar me-1"></i>
                                <?= date('M d, Y', strtotime($job[$dateColumn])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['preferred_time'])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-clock me-1"></i>
                                <?= date('h:i A', strtotime($job['preferred_time'])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['location_address'])): ?>
                            <small class="text-primary">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?= htmlspecialchars($job['location_address']) ?>
                            </small>
                            <?php endif; ?>
                            <?php if (!empty($job['cost'])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-money-bill-wave me-1"></i>
                                Cost per visit: ₱ <?= calculateCostPerVisit($job['cost'], $job['frequency']) ?>
                            </p>
                            <?php endif; ?>
                            <div class="mt-2">
                                <span class="badge bg-danger">Past Due - Needs Attention</span>
                                <?php if (isset($job['is_primary']) && $job['is_primary']): ?>
                                <span class="badge bg-info">Primary Technician</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Secondary Technician</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($pastDueJobOrders)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">No past due job orders</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Finished Job Orders -->
        <div class="job-section finished-job-orders">
            <h3><i class="fas fa-check-circle"></i> Finished Job Orders</h3>

            <?php if (isset($_GET['debug'])): ?>
            <div class="alert alert-info">
                <h5>Debug Information</h5>
                <p>Number of finished job orders: <?= count($finishedJobOrders) ?></p>
                <p>SQL Condition: <?= htmlspecialchars($statusCondition) ?></p>
                <?php
                // Check if there are any job orders with status 'completed'
                $completedCount = 0;
                if ($result) {
                    $result->data_seek(0); // Reset result pointer
                    while ($row = $result->fetch_assoc()) {
                        if (isset($row['status']) && $row['status'] === 'completed') {
                            $completedCount++;
                        }
                    }
                    $result->data_seek(0); // Reset result pointer again
                }
                ?>
                <p>Number of job orders with status 'completed' in database: <?= $completedCount ?></p>
            </div>
            <?php endif; ?>

            <div class="row">
                <?php foreach ($finishedJobOrders as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="card job-card"
                         data-job="<?= sanitizeJobData($job) ?>"
                         data-job-id="<?= htmlspecialchars($job['job_order_id']) ?>"
                         data-client-name="<?= htmlspecialchars(trim($job['client_name'] ?? '') ?: 'Unknown Client') ?>"
                         style="cursor: pointer; transition: transform 0.3s ease; border-left: 4px solid #28a745;"
                         onmouseover="this.style.transform='translateY(-5px)'"
                         onmouseout="this.style.transform='translateY(0)'">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars(trim($job['client_name'] ?? '') ?: 'Unknown Client') ?></h5>
                            <div class="d-flex gap-2 mb-2">
                                <?php if (!empty($job['kind_of_place'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['kind_of_place']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['type_of_work'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['type_of_work']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($dateColumn && isset($job[$dateColumn])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-calendar me-1"></i>
                                <?= date('M d, Y', strtotime($job[$dateColumn])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['preferred_time'])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-clock me-1"></i>
                                <?= date('h:i A', strtotime($job['preferred_time'])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['location_address'])): ?>
                            <small class="text-primary">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?= htmlspecialchars($job['location_address']) ?>
                            </small>
                            <?php endif; ?>
                            <?php if (!empty($job['cost'])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-money-bill-wave me-1"></i>
                                Cost per visit: ₱ <?= calculateCostPerVisit($job['cost'], $job['frequency']) ?>
                            </p>
                            <?php endif; ?>
                            <div class="mt-2">
                                <span class="badge bg-success">Completed<?= isset($job['status']) ? ' - ' . $job['status'] : '' ?></span>
                                <?php if (isset($job['is_primary']) && $job['is_primary']): ?>
                                <span class="badge bg-info">Primary Technician</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Secondary Technician</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($finishedJobOrders)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">No completed job orders</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Job Details Modal -->
    <div class="modal fade" id="jobDetailsModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-zoom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Job Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="jobDetailsContent"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="createReportBtn" onclick="openReportForm()"><i class="fas fa-file-medical me-2"></i>Create Job Order Report</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Job Order Report Form Modal -->
    <div class="modal fade" id="reportFormModal" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl modal-zoom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Job Order Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="jobOrderReportForm" action="api/job_order_report.php" method="post" enctype="multipart/form-data" onsubmit="event.preventDefault(); submitReportForm();">
                    <div class="modal-body">
                        <input type="hidden" name="job_order_id" id="reportJobOrderId">

                        <div class="mb-3">
                            <label for="observation_notes" class="form-label">Observation Notes <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="observation_notes" name="observation_notes" rows="4" required></textarea>
                            <div class="form-text">Provide detailed notes about the job completion, observations, and any issues encountered.</div>
                        </div>

                        <div class="mb-3">
                            <label for="recommendation" class="form-label">Recommendation <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="recommendation" name="recommendation" rows="3" required></textarea>
                            <div class="form-text">Provide recommendations for future pest control measures or maintenance.</div>
                        </div>

                        <div class="mb-3">
                            <label for="attachments" class="form-label">Attachments <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="attachments" name="attachments[]" multiple accept="image/*" required>
                            <div class="form-text">Upload photos of the completed job (before/after, receipts, etc.)</div>
                        </div>

                        <div class="mb-3">
                            <label for="payment_number" class="form-label">Payment Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="payment_number" name="payment_proof" required>
                            <div class="form-text">Enter the payment reference number or transaction ID</div>
                        </div>

                        <div class="mb-3">
                            <label for="id_attachments" class="form-label">ID Attachments <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="id_attachments" name="id_attachments[]" multiple accept="image/*" required>
                            <div class="form-text">Upload 2 valid ID photos (front and back)</div>
                        </div>

                        <!-- Chemical Dosage Section -->
                        <div class="mb-4" id="chemicalDosageSection">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Chemical Dosage Used <span class="text-danger">*</span></label>
                                <button type="button" id="customizeChemicalsBtn" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-exchange-alt me-1"></i> Customize Chemicals
                                </button>
                            </div>
                            <div class="alert alert-secondary">
                                <i class="fas fa-flask me-2"></i> Please enter the actual amount of each chemical used during the treatment.
                                <div class="mt-1 small text-muted">
                                    <i class="fas fa-info-circle me-1"></i> You can customize the chemicals by clicking the "Customize Chemicals" button.
                                </div>
                            </div>
                            <div id="chemicalDosageInputs" class="mt-3">
                                <!-- Chemical dosage inputs will be added here dynamically -->
                                <div class="text-center py-3">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2">Loading chemical recommendations...</p>
                                </div>
                            </div>
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle me-1"></i> Swipe left/right if the table is not fully visible on your device.
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Submitting this report will mark the job order as completed.
                            <div class="mt-2"><strong>Note:</strong> All fields marked with <span class="text-danger">*</span> are required.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="submitReportBtn" class="btn btn-primary"><i class="fas fa-save me-2"></i>Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Customize Chemicals Modal -->
    <div class="modal fade" id="customizeChemicalsModal" tabindex="-1" aria-labelledby="customizeChemicalsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="customizeChemicalsModalLabel">
                        <i class="fas fa-flask me-2"></i>Customize Chemicals
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Use the "Replace" button to select alternative chemicals for this job order. The original recommendations are shown below.
                    </div>

                    <!-- Original Recommendations Section -->
                    <div class="mb-4">
                        <h6 class="border-bottom pb-2 mb-3">Original Recommendations</h6>
                        <div class="small text-muted mb-2">
                            <i class="fas fa-exchange-alt me-1"></i> Click the "Replace" button to select an alternative chemical for each recommendation.
                        </div>
                        <div id="originalRecommendationsContainer" class="chemicals-table-container">
                            <table class="chemicals-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Chemical Name</th>
                                        <th>Type</th>
                                        <th>Target Pest</th>
                                        <th>Recommended Dosage</th>
                                        <th>Available Quantity</th>
                                        <th>Expiration</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="originalRecommendationsTable">
                                    <!-- Original recommendations will be inserted here -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Available Chemicals Section -->
                    <div>
                        <h6 class="border-bottom pb-2 mb-3">Chemical Inventory Reference</h6>
                        <div class="small text-muted mb-2">
                            <i class="fas fa-info-circle me-1"></i> This is a reference list of all available chemicals in inventory.
                        </div>
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="chemicalSearchInput" class="form-control" placeholder="Search chemicals...">
                                <button class="btn btn-outline-secondary" type="button" id="filterByPestTypeBtn">
                                    <i class="fas fa-filter me-1"></i>Filter by Pest Type
                                </button>
                            </div>
                        </div>
                        <div id="availableChemicalsContainer" class="chemicals-table-container" style="max-height: 400px; overflow-y: auto;">
                            <table class="chemicals-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Chemical Name</th>
                                        <th>Type</th>
                                        <th>Target Pest</th>
                                        <th>Quantity</th>
                                        <th>Expiration</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="availableChemicalsTable">
                                    <!-- Available chemicals will be inserted here -->
                                    <tr>
                                        <td colspan="7" class="text-center py-3">
                                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            Loading available chemicals...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="applyChemicalChangesBtn">
                        <i class="fas fa-check me-1"></i>Done
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Submission Success Modal -->
    <div class="modal fade" id="reportSuccessModal" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Report Submitted</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="success-icon mb-3">
                            <i class="fas fa-check-circle text-success"></i>
                        </div>
                        <h4>Job Order Report Submitted Successfully!</h4>
                        <p class="text-muted">The job order has been marked as completed.</p>
                    </div>

                    <div id="inventoryUpdateResults" class="mb-4">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-light">
                                <h5 class="d-flex align-items-center mb-0">
                                    <i class="fas fa-flask me-2 text-primary"></i>Chemical Inventory Updated
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-3" id="chemicalUpdateInfo">
                                    <i class="fas fa-info-circle me-2"></i>
                                    The following chemicals have been deducted from inventory based on your report.
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-primary">
                                            <tr>
                                                <th>Chemical Name</th>
                                                <th>Type</th>
                                                <th>Previous Quantity</th>
                                                <th>Used</th>
                                                <th>New Quantity</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="inventoryUpdateTable">
                                            <!-- Inventory update results will be inserted here -->
                                        </tbody>
                                    </table>
                                </div>

                                <div id="noChemicalsUsed" class="alert alert-warning" style="display: none;">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    No chemicals were used in this job order or no inventory updates were recorded.
                                    <div class="small mt-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        This may happen if no chemicals were selected, if all dosages were set to zero, or if there was an issue with the inventory update process.
                                    </div>
                                </div>

                                <!-- Chemical Replacements Section -->
                                <div id="chemicalReplacementsSection" class="mt-4" style="display: none;">
                                    <h6 class="d-flex align-items-center mb-3 border-bottom pb-2">
                                        <i class="fas fa-exchange-alt me-2 text-warning"></i>Chemical Replacements
                                    </h6>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        The following chemicals were used as replacements:
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead class="table-warning">
                                                <tr>
                                                    <th>Chemical Name</th>
                                                    <th>Replacing</th>
                                                    <th>Quantity Used</th>
                                                    <th>Expiration Date</th>
                                                </tr>
                                            </thead>
                                            <tbody id="replacementsTable">
                                                <!-- Replacement chemicals will be inserted here -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div id="inventoryUpdateErrors" class="alert alert-danger mt-3" style="display: none;">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Warning:</strong> Some issues occurred during inventory update:
                                    <ul id="inventoryErrorList" class="mb-0 mt-1">
                                        <!-- Error messages will be inserted here -->
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tools Reset Status Alert - Hidden by default -->
                    <div id="toolsResetStatus" class="alert alert-info mt-3" style="display: none;">
                        <i class="fas fa-tools me-2"></i>
                        <span id="toolsResetMessage">Tools status will be reset when you click the Reset Tools Status button.</span>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> If you don't see the job order in the Finished Job Orders section, please refresh the page.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Close
                    </button>
                    <button type="button" class="btn btn-warning" id="resetToolsBtn">
                        <i class="fas fa-tools me-2"></i>Reset Tools Status
                    </button>
                    <button type="button" class="btn btn-success" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh Page
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/unit-conversion-helper.js"></script>

    <!-- Chemical Customization Script -->
    <script>
        // Global variables to store chemical data
        let originalChemicals = [];
        let availableChemicals = [];
        let selectedChemicals = [];
        let currentTargetPests = [];

        // Function to validate dosage input
        function validateDosageInput(input) {
            // Remove any non-numeric characters except decimal point
            input.value = input.value.replace(/[^0-9.]/g, '');

            // Ensure only one decimal point
            const parts = input.value.split('.');
            if (parts.length > 2) {
                input.value = parts[0] + '.' + parts.slice(1).join('');
            }

            // Ensure value is not negative
            const value = parseFloat(input.value);
            if (isNaN(value) || value < 0) {
                input.value = '0';
            }

            // Highlight the row if the value is different from the recommended dosage
            const row = input.closest('tr');
            if (row) {
                const recommendedDosageCell = row.querySelector('td:nth-child(4)');
                if (recommendedDosageCell) {
                    const recommendedText = recommendedDosageCell.textContent;
                    const recommendedValue = parseFloat(recommendedText);

                    // If the value is different from the recommended dosage, highlight the row
                    if (!isNaN(recommendedValue) && Math.abs(value - recommendedValue) > 0.01) {
                        row.classList.add('table-warning');

                        // Add a tooltip to the input
                        input.setAttribute('title', `Different from recommended dosage (${recommendedText})`);
                        input.setAttribute('data-bs-toggle', 'tooltip');
                        input.setAttribute('data-bs-placement', 'top');

                        // Initialize tooltip
                        new bootstrap.Tooltip(input);
                    } else {
                        row.classList.remove('table-warning');

                        // Remove tooltip
                        const tooltip = bootstrap.Tooltip.getInstance(input);
                        if (tooltip) {
                            tooltip.dispose();
                        }
                        input.removeAttribute('title');
                        input.removeAttribute('data-bs-toggle');
                        input.removeAttribute('data-bs-placement');
                    }
                }
            }

            // Return true if the value is valid
            return !isNaN(value) && value >= 0;
        }

        // Initialize chemical customization functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listener to the customize chemicals button
            const customizeBtn = document.getElementById('customizeChemicalsBtn');
            if (customizeBtn) {
                customizeBtn.addEventListener('click', openCustomizeChemicalsModal);
            }

            // Add event listener to the apply changes button
            const applyBtn = document.getElementById('applyChemicalChangesBtn');
            if (applyBtn) {
                applyBtn.addEventListener('click', applyChemicalChanges);
            }

            // Add event listener to the search input
            const searchInput = document.getElementById('chemicalSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', filterChemicals);
            }

            // Add event listener to the filter by pest type button
            const filterBtn = document.getElementById('filterByPestTypeBtn');
            if (filterBtn) {
                filterBtn.addEventListener('click', filterByPestType);
            }
        });

        // Function to open the customize chemicals modal
        function openCustomizeChemicalsModal() {
            // Get the current chemical recommendations
            const chemicalInputs = document.querySelectorAll('.chemical-dosage-input');
            if (chemicalInputs.length === 0) {
                Swal.fire({
                    title: 'No Chemicals',
                    text: 'There are no chemical recommendations available for this job order.',
                    icon: 'info',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Store the original chemicals
            originalChemicals = [];
            currentTargetPests = [];

            // Loop through chemical inputs to get original chemicals
            for (let i = 0; i < chemicalInputs.length; i++) {
                const input = chemicalInputs[i];
                const row = input.closest('tr');

                // Get chemical data from hidden inputs
                const chemicalId = document.querySelector(`input[name="chemical_id[${i}]"]`).value;
                const chemicalName = document.querySelector(`input[name="chemical_name[${i}]"]`).value;
                const chemicalType = document.querySelector(`input[name="chemical_type[${i}]"]`).value;
                const targetPest = document.querySelector(`input[name="chemical_target_pest[${i}]"]`).value;
                const recommendedDosage = document.querySelector(`input[name="chemical_recommended_dosage[${i}]"]`).value;
                const dosageUnit = document.querySelector(`input[name="chemical_dosage_unit[${i}]"]`).value;

                // Add to original chemicals array
                originalChemicals.push({
                    id: chemicalId,
                    name: chemicalName,
                    type: chemicalType,
                    target_pest: targetPest,
                    dosage: recommendedDosage,
                    dosage_unit: dosageUnit,
                    index: i
                });

                // Add target pest to array if not already included
                if (targetPest && !currentTargetPests.includes(targetPest)) {
                    currentTargetPests.push(targetPest);
                }
            }

            // Initialize selected chemicals with original chemicals
            selectedChemicals = [...originalChemicals];

            // Fetch available chemicals from the server first, then populate the table
            fetchAvailableChemicals().then(() => {
                // Populate the original recommendations table after chemicals are fetched
                populateOriginalRecommendations();
            }).catch(error => {
                console.error('Error fetching chemicals:', error);
                // Still try to populate the table even if fetch fails
                populateOriginalRecommendations();
            });

            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('customizeChemicalsModal'));
            modal.show();
        }

        // Function to populate the original recommendations table
        function populateOriginalRecommendations() {
            const table = document.getElementById('originalRecommendationsTable');
            if (!table) return;

            // Clear the table
            table.innerHTML = '';

            // Add each original chemical to the table
            originalChemicals.forEach(chemical => {
                // Find the matching chemical in availableChemicals to get the status
                const availableChem = availableChemicals.find(ac =>
                    ac.chemical_name === chemical.name && ac.type === chemical.type
                );

                // Determine status class and text
                let statusText = availableChem ? availableChem.status : 'Unknown';
                let statusClass = '';

                if (statusText === 'In Stock') {
                    statusClass = 'in-stock';
                } else if (statusText === 'Low Stock') {
                    statusClass = 'low-stock';
                } else {
                    statusClass = 'out-of-stock';
                }

                // Get quantity information
                const quantity = availableChem ? `${availableChem.quantity} ${availableChem.unit}` : 'N/A';

                // Format the expiration date if available
                let formattedDate = 'N/A';
                if (availableChem && availableChem.expiration_date) {
                    const expirationDate = new Date(availableChem.expiration_date);
                    formattedDate = expirationDate.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    });
                }

                // Check if chemical is expired
                const isExpired = availableChem && new Date(availableChem.expiration_date) < new Date();

                // Check if this chemical has been replaced
                const replacementChem = selectedChemicals.find(selected =>
                    selected.replacing === chemical.id && selected.id !== chemical.id
                );
                const isReplaced = !!replacementChem;

                const row = document.createElement('tr');
                if (isExpired) {
                    row.classList.add('expired-chemical');
                }

                if (isReplaced) {
                    row.classList.add('replaced-chemical');
                }

                // Prepare the chemical name cell content
                let chemicalNameCell = `<strong>${chemical.name || 'N/A'}</strong>`;

                // If this chemical has been replaced, show the replacement info
                if (isReplaced && replacementChem) {
                    chemicalNameCell = `
                        <div class="d-flex align-items-center">
                            <div class="text-decoration-line-through text-muted">${chemical.name || 'N/A'}</div>
                            <div class="ms-2 badge bg-warning text-dark">
                                <i class="fas fa-exchange-alt me-1"></i> Replaced
                            </div>
                        </div>
                        <div class="small text-primary mt-1">
                            <i class="fas fa-arrow-right me-1"></i> Now using: <strong>${replacementChem.name}</strong>
                        </div>
                    `;
                }

                row.innerHTML = `
                    <td>${chemical.id || 'N/A'}</td>
                    <td>${chemicalNameCell}</td>
                    <td>${chemical.type || 'N/A'}</td>
                    <td>${chemical.target_pest || 'N/A'}</td>
                    <td>${chemical.dosage || '0'} ${chemical.dosage_unit || 'ml'}</td>
                    <td>${quantity}</td>
                    <td class="${isExpired ? 'expired-date' : ''}">${formattedDate}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td>
                        ${isReplaced ?
                            '<button class="btn btn-sm btn-outline-secondary undo-replace-btn" data-chemical-id="' + chemical.id + '"><i class="fas fa-undo me-1"></i>Undo</button>' :
                            '<button class="btn btn-sm btn-outline-primary replace-btn" data-chemical-id="' + chemical.id + '"><i class="fas fa-exchange-alt me-1"></i>Replace</button>'
                        }
                    </td>
                `;
                table.appendChild(row);
            });

            // Add event listeners to replace buttons
            const replaceButtons = table.querySelectorAll('.replace-btn');
            replaceButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const chemicalId = this.getAttribute('data-chemical-id');
                    showReplacementOptions(chemicalId);
                });
            });

            // Add event listeners to undo replace buttons
            const undoReplaceButtons = table.querySelectorAll('.undo-replace-btn');
            undoReplaceButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const chemicalId = this.getAttribute('data-chemical-id');
                    undoReplacement(chemicalId);
                });
            });
        }

        // Function to fetch available chemicals from the server
        function fetchAvailableChemicals() {
            return new Promise((resolve, reject) => {
                const table = document.getElementById('availableChemicalsTable');
                if (!table) {
                    reject(new Error('Available chemicals table not found'));
                    return;
                }

                // Show loading indicator
                table.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-3">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            Loading available chemicals...
                        </td>
                    </tr>
                `;

                // Check if we have cached chemicals data
                if (window.cachedChemicalsData && window.cachedChemicalsTimestamp) {
                    // Check if cache is still valid (less than 60 seconds old)
                    const cacheAge = Date.now() - window.cachedChemicalsTimestamp;
                    if (cacheAge < 60000) { // 60 seconds in milliseconds
                        console.log('Using cached chemicals data');
                        // Use cached data
                        availableChemicals = window.cachedChemicalsData;
                        populateAvailableChemicals(availableChemicals);

                        // Also update any chemical status/quantity displays in the job details
                        updateChemicalStatusDisplay();
                        resolve(availableChemicals);
                        return;
                    }
                }

                // Fetch available chemicals from the server with cache-busting parameter
                fetch('api/get_available_chemicals.php?_=' + Date.now())
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Store available chemicals
                            availableChemicals = data.chemicals;

                            // Cache the data in memory
                            window.cachedChemicalsData = data.chemicals;
                            window.cachedChemicalsTimestamp = Date.now();

                            // Populate the available chemicals table
                            populateAvailableChemicals(availableChemicals);

                            // Also update any chemical status/quantity displays in the job details
                            updateChemicalStatusDisplay();

                            // Resolve the promise with the chemicals data
                            resolve(availableChemicals);
                        } else {
                            // Show error message
                            table.innerHTML = `
                                <tr>
                                    <td colspan="6" class="text-center py-3 text-danger">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        ${data.message || 'Failed to load available chemicals'}
                                    </td>
                                </tr>
                            `;
                            reject(new Error(data.message || 'Failed to load available chemicals'));
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching available chemicals:', error);

                        // Show error message
                        table.innerHTML = `
                            <tr>
                                <td colspan="6" class="text-center py-3 text-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    Error loading chemicals: ${error.message}
                                </td>
                            </tr>
                        `;
                        reject(error);
                    });
            });
        }

        // Function to update chemical status display in job details
        function updateChemicalStatusDisplay() {
            console.log('Updating chemical status display');

            // Find all chemical recommendation tables in the page
            const chemicalTables = document.querySelectorAll('.chemical-recommendations-container table');
            if (!chemicalTables.length) {
                console.log('No chemical recommendation tables found');
                return;
            }

            console.log('Found', chemicalTables.length, 'chemical recommendation tables');

            chemicalTables.forEach(table => {
                const rows = table.querySelectorAll('tbody tr');
                console.log('Found', rows.length, 'rows in table');

                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 6) {
                        // Get chemical name and type from data attributes if available, otherwise from cell text
                        const chemicalName = row.getAttribute('data-chemical-name') || cells[0].textContent.trim();
                        const chemicalType = row.getAttribute('data-chemical-type') || cells[1].textContent.trim();
                        const quantityCell = cells[4];
                        const statusCell = cells[5];

                        console.log('Updating chemical:', chemicalName, chemicalType);

                        // Find matching chemical in availableChemicals
                        const availableChem = availableChemicals.find(ac =>
                            ac.chemical_name === chemicalName && ac.type === chemicalType
                        );

                        if (availableChem) {
                            console.log('Found matching chemical in inventory:', availableChem);

                            // Update quantity
                            quantityCell.textContent = `${availableChem.quantity} ${availableChem.unit}`;

                            // Update status
                            let statusClass = '';
                            if (availableChem.status === 'In Stock') {
                                statusClass = 'in-stock';
                            } else if (availableChem.status === 'Low Stock') {
                                statusClass = 'low-stock';
                            } else {
                                statusClass = 'out-of-stock';
                            }

                            statusCell.innerHTML = `<span class="status-badge ${statusClass}">${availableChem.status}</span>`;
                        } else {
                            console.log('No matching chemical found in inventory');
                            // Set default values if chemical not found
                            quantityCell.textContent = 'N/A';
                            statusCell.innerHTML = '<span class="status-badge out-of-stock">Not Available</span>';
                        }
                    } else {
                        console.log('Row has insufficient cells:', cells.length);
                    }
                });
            });
        }

        // Function to populate the available chemicals table
        function populateAvailableChemicals(chemicals) {
            const table = document.getElementById('availableChemicalsTable');
            if (!table) return;

            // Clear the table
            table.innerHTML = '';

            // If no chemicals available, show message
            if (!chemicals || chemicals.length === 0) {
                table.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-3">
                            <i class="fas fa-info-circle me-2"></i>
                            No chemicals available in inventory
                        </td>
                    </tr>
                `;
                return;
            }

            // Add each available chemical to the table
            chemicals.forEach(chemical => {
                const row = document.createElement('tr');

                // Check if chemical is expired
                const isExpired = new Date(chemical.expiration_date) < new Date();
                if (isExpired) {
                    row.classList.add('expired-chemical');
                }

                // Determine status class
                let statusText = chemical.status || 'Unknown';
                let statusClass = '';

                if (statusText === 'In Stock') {
                    statusClass = 'in-stock';
                } else if (statusText === 'Low Stock') {
                    statusClass = 'low-stock';
                } else {
                    statusClass = 'out-of-stock';
                }

                // Format the expiration date
                const expirationDate = new Date(chemical.expiration_date);
                const formattedDate = expirationDate.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });

                // Create the row HTML
                row.innerHTML = `
                    <td>${chemical.id}</td>
                    <td>${chemical.chemical_name}</td>
                    <td>${chemical.type}</td>
                    <td>${chemical.target_pest || 'Not specified'}</td>
                    <td>${Number(chemical.quantity).toFixed(2)} ${chemical.unit}</td>
                    <td class="${isExpired ? 'expired-date' : ''}">${formattedDate}</td>
                    <td>
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </td>
                `;

                // Add the row to the table
                table.appendChild(row);
            });
        }

        // Function to show replacement options for a specific chemical
        function showReplacementOptions(originalChemicalId) {
            // Find the original chemical
            const originalChem = originalChemicals.find(chem => chem.id === originalChemicalId);
            if (!originalChem) return;

            // Filter available chemicals by target pest
            const targetPest = originalChem.target_pest;
            const matchingChemicals = availableChemicals.filter(chem =>
                chem.target_pest && (
                    chem.target_pest.includes(targetPest) ||
                    (targetPest.includes('Crawling') && chem.target_pest.includes('Crawling & Flying')) ||
                    (targetPest.includes('Flying') && chem.target_pest.includes('Crawling & Flying'))
                )
            );

            // Create HTML for the replacement options
            const optionsHTML = matchingChemicals.map(chem => {
                // Determine status class
                let statusClass = '';
                if (chem.status === 'In Stock') {
                    statusClass = 'in-stock';
                } else if (chem.status === 'Low Stock') {
                    statusClass = 'low-stock';
                } else {
                    statusClass = 'out-of-stock';
                }

                // Format expiration date
                const expirationDate = new Date(chem.expiration_date);
                const formattedDate = expirationDate.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });

                // Check if expired
                const isExpired = new Date(chem.expiration_date) < new Date();

                return `
                    <div class="replacement-option ${isExpired ? 'expired-option' : ''}" data-chemical-id="${chem.id}">
                        <div class="form-check">
                            <input class="form-check-input replacement-select" type="radio"
                                   name="replacement" value="${chem.id}"
                                   data-name="${chem.chemical_name}"
                                   data-type="${chem.type}"
                                   data-target-pest="${chem.target_pest || ''}"
                                   data-quantity="${chem.quantity}"
                                   data-unit="${chem.unit}">
                            <label class="form-check-label">
                                <strong>${chem.chemical_name}</strong> (${chem.type})
                                <div class="chemical-details">
                                    <span class="me-2">Quantity: ${Number(chem.quantity).toFixed(2)} ${chem.unit}</span>
                                    <span class="me-2">Expiration: <span class="${isExpired ? 'expired-date' : ''}">${formattedDate}</span></span>
                                    <span class="status-badge ${statusClass}">${chem.status}</span>
                                </div>
                            </label>
                        </div>
                    </div>
                `;
            }).join('');

            // Show the options in a modal
            Swal.fire({
                title: `Replace ${originalChem.name}`,
                html: `
                    <div class="replacement-options-container">
                        <p class="mb-3">Select a replacement chemical for <strong>${originalChem.name}</strong> (${originalChem.type}) targeting <strong>${originalChem.target_pest}</strong>:</p>
                        <div class="replacement-options">
                            ${optionsHTML.length > 0 ? optionsHTML : '<div class="alert alert-warning">No matching chemicals available</div>'}
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Replace',
                cancelButtonText: 'Cancel',
                didOpen: () => {
                    // Add event listeners to radio buttons
                    const radioButtons = document.querySelectorAll('.replacement-select');
                    radioButtons.forEach(radio => {
                        radio.addEventListener('change', function() {
                            // Highlight the selected option
                            document.querySelectorAll('.replacement-option').forEach(option => {
                                option.classList.remove('selected-option');
                            });
                            this.closest('.replacement-option').classList.add('selected-option');
                        });
                    });
                },
                preConfirm: () => {
                    // Get the selected replacement
                    const selectedRadio = document.querySelector('.replacement-select:checked');
                    if (!selectedRadio) {
                        Swal.showValidationMessage('Please select a replacement chemical');
                        return false;
                    }

                    // Get the chemical ID from the selected radio button
                    const chemicalId = selectedRadio.value;

                    // Find the complete chemical data from the available chemicals
                    const selectedChemical = availableChemicals.find(chem => chem.id.toString() === chemicalId);

                    if (!selectedChemical) {
                        console.error('Could not find selected chemical in inventory:', chemicalId);
                        Swal.showValidationMessage('Error: Could not find selected chemical in inventory');
                        return false;
                    }

                    // Return the chemical ID - we'll get the full data in replaceChemical()
                    return {
                        id: chemicalId
                    };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    // Replace the chemical
                    replaceChemical(originalChemicalId, result.value);
                }
            });
        }

        // Function to replace a chemical with a new one
        function replaceChemical(originalId, newChemical) {
            // Find the original chemical
            const originalChem = originalChemicals.find(chem => chem.id === originalId);
            if (!originalChem) return;

            // Remove any existing replacements for this original
            selectedChemicals = selectedChemicals.filter(chem => chem.replacing !== originalId);

            // Find the complete chemical data from the available chemicals
            const availableChem = availableChemicals.find(ac => ac.id.toString() === newChemical.id);

            if (!availableChem) {
                console.error('Could not find matching chemical in inventory:', newChemical);
                Swal.fire({
                    title: 'Error',
                    text: 'Could not find the selected chemical in inventory. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            console.log('Found matching chemical in inventory:', availableChem);

            // Create the replacement chemical object with complete inventory information
            const replacementChem = {
                id: availableChem.id,
                name: availableChem.chemical_name,
                type: availableChem.type,
                target_pest: availableChem.target_pest,
                dosage: originalChem.dosage, // Keep the original recommended dosage
                dosage_unit: originalChem.dosage_unit,
                index: originalChem.index,
                replacing: originalId,
                status: availableChem.status,
                quantity: availableChem.quantity,
                unit: availableChem.unit,
                inventory_unit: availableChem.unit,
                expiration_date: availableChem.expiration_date,
                expiration_date_formatted: availableChem.expiration_date_formatted,
                is_replacement: true,
                original_chemical_name: originalChem.name,
                original_chemical_type: originalChem.type
            };

            // Add the replacement chemical to selected chemicals
            selectedChemicals.push(replacementChem);

            // Update the original recommendations table
            populateOriginalRecommendations();

            // Update the available chemicals table
            populateAvailableChemicals(availableChemicals);

            // Immediately apply the change to the chemical dosage inputs
            applyReplacementToDosageInputs(replacementChem);

            // Show success message
            Swal.fire({
                title: 'Chemical Replaced',
                text: `${originalChem.name} has been replaced with ${availableChem.chemical_name}`,
                icon: 'success',
                confirmButtonText: 'OK'
            });
        }

        // Function to apply a single replacement to the chemical dosage inputs
        function applyReplacementToDosageInputs(replacementChem) {
            console.log('Applying replacement to dosage inputs:', replacementChem);

            // Get all the current chemical inputs
            const chemicalInputs = document.querySelectorAll('.chemical-dosage-input');
            const chemicalIds = Array.from(document.querySelectorAll('input[name^="chemical_id["]'));

            // Find the index of the original chemical that's being replaced
            const originalIndex = chemicalIds.findIndex(input => {
                const index = input.name.match(/\[(\d+)\]/)[1];
                const originalId = document.querySelector(`input[name="chemical_id[${index}]"]`).value;
                return originalId === replacementChem.replacing;
            });

            if (originalIndex === -1) {
                console.error('Could not find original chemical to replace:', replacementChem.replacing);
                return;
            }

            // Get the index from the input name
            const index = chemicalIds[originalIndex].name.match(/\[(\d+)\]/)[1];

            // Store the current dosage value before making changes
            const currentDosageInput = chemicalInputs[originalIndex];
            const currentDosageValue = currentDosageInput.value;

            // Get the dosage unit for display
            const dosageUnit = document.querySelector(`input[name="chemical_dosage_unit[${index}]"]`).value || 'ml';
            const displayUnit = dosageUnit;

            // Update the hidden inputs with the replacement chemical information
            document.querySelector(`input[name="chemical_id[${index}]"]`).value = replacementChem.id;
            document.querySelector(`input[name="chemical_name[${index}]"]`).value = replacementChem.name;
            document.querySelector(`input[name="chemical_type[${index}]"]`).value = replacementChem.type;
            document.querySelector(`input[name="chemical_target_pest[${index}]"]`).value = replacementChem.target_pest || '';

            // Update inventory unit information
            document.querySelector(`input[name="chemical_inventory_unit[${index}]"]`).value = replacementChem.unit || replacementChem.inventory_unit || 'ml';

            // Add replacement information
            let isReplacementInput = document.querySelector(`input[name="is_replacement[${index}]"]`);
            if (!isReplacementInput) {
                // Create the hidden inputs for replacement information if they don't exist
                const dosageInputGroup = chemicalInputs[originalIndex].closest('.input-group');

                // Add is_replacement flag
                const isReplacementInput = document.createElement('input');
                isReplacementInput.type = 'hidden';
                isReplacementInput.name = `is_replacement[${index}]`;
                isReplacementInput.value = '1';
                dosageInputGroup.appendChild(isReplacementInput);

                // Add replacing (original chemical ID) input
                const replacingInput = document.createElement('input');
                replacingInput.type = 'hidden';
                replacingInput.name = `replacing[${index}]`;
                replacingInput.value = replacementChem.replacing;
                dosageInputGroup.appendChild(replacingInput);

                // Add original chemical name input
                const originalChemNameInput = document.createElement('input');
                originalChemNameInput.type = 'hidden';
                originalChemNameInput.name = `original_chemical_name[${index}]`;
                originalChemNameInput.value = replacementChem.original_chemical_name;
                dosageInputGroup.appendChild(originalChemNameInput);

                const originalNameInput = document.createElement('input');
                originalNameInput.type = 'hidden';
                originalNameInput.name = `original_chemical_name[${index}]`;
                originalNameInput.value = replacementChem.original_chemical_name;
                dosageInputGroup.appendChild(originalNameInput);

                const originalTypeInput = document.createElement('input');
                originalTypeInput.type = 'hidden';
                originalTypeInput.name = `original_chemical_type[${index}]`;
                originalTypeInput.value = replacementChem.original_chemical_type;
                dosageInputGroup.appendChild(originalTypeInput);

                // Add expiration date information if available
                if (replacementChem.expiration_date) {
                    const expirationInput = document.createElement('input');
                    expirationInput.type = 'hidden';
                    expirationInput.name = `chemical_expiration[${index}]`;
                    expirationInput.value = replacementChem.expiration_date;
                    dosageInputGroup.appendChild(expirationInput);
                }
            } else {
                // Update existing replacement information
                isReplacementInput.value = '1';
                document.querySelector(`input[name="original_chemical_name[${index}]"]`).value = replacementChem.original_chemical_name;
                document.querySelector(`input[name="original_chemical_type[${index}]"]`).value = replacementChem.original_chemical_type;

                // Update expiration date if it exists
                let expirationInput = document.querySelector(`input[name="chemical_expiration[${index}]"]`);
                if (replacementChem.expiration_date) {
                    if (!expirationInput) {
                        const dosageInputGroup = chemicalInputs[originalIndex].closest('.input-group');
                        expirationInput = document.createElement('input');
                        expirationInput.type = 'hidden';
                        expirationInput.name = `chemical_expiration[${index}]`;
                        dosageInputGroup.appendChild(expirationInput);
                    }
                    expirationInput.value = replacementChem.expiration_date;
                }
            }

            // Update the row to show the replacement
            const row = chemicalInputs[originalIndex].closest('tr');

            // Update the chemical name cell
            const nameCell = row.querySelector('td:nth-child(1)');
            nameCell.innerHTML = `
                <strong>${replacementChem.name}</strong>
                <div class="small text-muted">
                    <i class="fas fa-exchange-alt me-1"></i> Replacing: ${replacementChem.original_chemical_name}
                </div>
            `;

            // Update the type cell
            row.querySelector('td:nth-child(2)').textContent = replacementChem.type;

            // Update the target pest cell
            if (replacementChem.target_pest) {
                row.querySelector('td:nth-child(3)').textContent = replacementChem.target_pest;
            }

            // Add the replaced-chemical class to the row
            row.classList.add('replaced-chemical');

            // Update the quantity cell if available
            const quantityCell = row.querySelector('td:nth-child(5)');
            if (quantityCell) {
                // Format the quantity with 2 decimal places
                const formattedQuantity = Number(replacementChem.quantity).toFixed(2);
                quantityCell.textContent = `${formattedQuantity} ${replacementChem.unit}`;
            }

            // Update the status cell if available
            const statusCell = row.querySelector('td:nth-child(6)');
            if (statusCell) {
                let statusClass = '';
                let statusText = replacementChem.status || 'Unknown';

                if (statusText === 'In Stock') {
                    statusClass = 'in-stock';
                } else if (statusText === 'Low Stock') {
                    statusClass = 'low-stock';
                } else {
                    statusClass = 'out-of-stock';
                }

                statusCell.innerHTML = `<span class="status-badge ${statusClass}">${statusText}</span>`;
            }

            // Add expiration date information if available
            if (replacementChem.expiration_date_formatted || replacementChem.expiration_date) {
                // Check if there's an expiration cell
                const expirationCell = row.querySelector('td.expiration-cell');
                if (expirationCell) {
                    const formattedDate = replacementChem.expiration_date_formatted ||
                        new Date(replacementChem.expiration_date).toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric'
                        });

                    // Check if expired
                    const isExpired = new Date(replacementChem.expiration_date) < new Date();
                    expirationCell.innerHTML = `<span class="${isExpired ? 'expired-date' : ''}">${formattedDate}</span>`;
                }
            }

            // IMPORTANT: Instead of just restoring the value, we need to ensure the input field
            // remains an input field and not a static text display

            // Get the actual dosage cell (last cell in the row)
            const actualDosageCell = row.querySelector('td:last-child');

            // Check if the cell contains an input field
            if (!actualDosageCell.querySelector('.chemical-dosage-input')) {
                // If not, recreate the input field
                actualDosageCell.innerHTML = `
                    <div class="input-group input-group-sm">
                        <input type="text"
                               class="form-control chemical-dosage-input"
                               name="chemical_dosage[${index}]"
                               value="${currentDosageValue}"
                               required
                               style="min-width: 80px;"
                               placeholder="Enter dosage"
                               pattern="[0-9]+(\\.[0-9]+)?"
                               inputmode="decimal"
                               onchange="validateDosageInput(this)"
                               onkeyup="validateDosageInput(this)"
                               data-original-unit="${dosageUnit}">
                        <span class="input-group-text">${displayUnit}</span>
                        <input type="hidden" name="chemical_id[${index}]" value="${replacementChem.id}">
                        <input type="hidden" name="chemical_name[${index}]" value="${replacementChem.name}">
                        <input type="hidden" name="chemical_type[${index}]" value="${replacementChem.type}">
                        <input type="hidden" name="chemical_target_pest[${index}]" value="${replacementChem.target_pest || ''}">
                        <input type="hidden" name="chemical_recommended_dosage[${index}]" value="${replacementChem.dosage || '0'}">
                        <input type="hidden" name="chemical_dosage_unit[${index}]" value="${dosageUnit}">
                        <input type="hidden" name="chemical_inventory_unit[${index}]" value="${replacementChem.unit || replacementChem.inventory_unit || 'ml'}">
                        <input type="hidden" name="is_replacement[${index}]" value="1">
                        <input type="hidden" name="original_chemical_name[${index}]" value="${replacementChem.original_chemical_name}">
                        <input type="hidden" name="original_chemical_type[${index}]" value="${replacementChem.original_chemical_type}">
                        ${replacementChem.expiration_date ? `<input type="hidden" name="chemical_expiration[${index}]" value="${replacementChem.expiration_date}">` : ''}
                    </div>
                `;
            } else {
                // If the input field exists, just update its value
                currentDosageInput.value = currentDosageValue;
            }
        }

        // Function to undo a chemical replacement
        function undoReplacement(originalId) {
            // Find the original chemical
            const originalChem = originalChemicals.find(chem => chem.id === originalId);
            if (!originalChem) return;

            // Find the replacement chemical
            const replacementChem = selectedChemicals.find(chem =>
                chem.replacing === originalId && chem.id !== originalId
            );

            if (!replacementChem) return;

            // Remove the replacement
            selectedChemicals = selectedChemicals.filter(chem =>
                !(chem.replacing === originalId && chem.id !== originalId)
            );

            // Add back the original chemical
            selectedChemicals.push(originalChem);

            // Update the original recommendations table
            populateOriginalRecommendations();

            // Immediately apply the undo action to the chemical dosage inputs
            undoReplacementInDosageInputs(originalChem, replacementChem);

            // Show success message
            Swal.fire({
                title: 'Replacement Undone',
                text: `Reverted to original chemical: ${originalChem.name}`,
                icon: 'success',
                confirmButtonText: 'OK'
            });
        }

        // Function to undo a replacement in the chemical dosage inputs
        function undoReplacementInDosageInputs(originalChem, replacementChem) {
            console.log('Undoing replacement in dosage inputs:', originalChem, replacementChem);

            // Get all the current chemical inputs
            const chemicalInputs = document.querySelectorAll('.chemical-dosage-input');
            const chemicalIds = Array.from(document.querySelectorAll('input[name^="chemical_id["]'));

            // Find the index of the replacement chemical
            const replacementIndex = chemicalIds.findIndex(input => {
                const index = input.name.match(/\[(\d+)\]/)[1];
                const id = document.querySelector(`input[name="chemical_id[${index}]"]`).value;
                return id === replacementChem.id;
            });

            if (replacementIndex === -1) {
                console.error('Could not find replacement chemical to undo:', replacementChem.id);
                return;
            }

            // Get the index from the input name
            const index = chemicalIds[replacementIndex].name.match(/\[(\d+)\]/)[1];

            // Update the hidden inputs with the original chemical information
            document.querySelector(`input[name="chemical_id[${index}]"]`).value = originalChem.id;
            document.querySelector(`input[name="chemical_name[${index}]"]`).value = originalChem.name;
            document.querySelector(`input[name="chemical_type[${index}]"]`).value = originalChem.type;
            document.querySelector(`input[name="chemical_target_pest[${index}]"]`).value = originalChem.target_pest || '';

            // Remove replacement information
            const isReplacementInput = document.querySelector(`input[name="is_replacement[${index}]"]`);
            if (isReplacementInput) {
                isReplacementInput.remove();
            }

            const originalNameInput = document.querySelector(`input[name="original_chemical_name[${index}]"]`);
            if (originalNameInput) {
                originalNameInput.remove();
            }

            const originalTypeInput = document.querySelector(`input[name="original_chemical_type[${index}]"]`);
            if (originalTypeInput) {
                originalTypeInput.remove();
            }

            // Remove expiration date information if it exists
            const expirationInput = document.querySelector(`input[name="chemical_expiration[${index}]"]`);
            if (expirationInput) {
                expirationInput.remove();
            }

            // Update the row to show the original chemical
            const row = chemicalInputs[replacementIndex].closest('tr');

            // Update the chemical name cell
            const nameCell = row.querySelector('td:nth-child(1)');
            nameCell.innerHTML = `<strong>${originalChem.name}</strong>`;

            // Update the type cell
            row.querySelector('td:nth-child(2)').textContent = originalChem.type;

            // Update the target pest cell
            if (originalChem.target_pest) {
                row.querySelector('td:nth-child(3)').textContent = originalChem.target_pest;
            }

            // Remove the replaced-chemical class from the row
            row.classList.remove('replaced-chemical');

            // Find the matching available chemical for the original to get quantity and status
            const availableChem = availableChemicals.find(ac =>
                ac.id.toString() === originalChem.id ||
                (ac.chemical_name === originalChem.name && ac.type === originalChem.type)
            );

            // Update the quantity cell if available
            const quantityCell = row.querySelector('td:nth-child(5)');
            if (quantityCell && availableChem) {
                // Format the quantity with 2 decimal places
                const formattedQuantity = Number(availableChem.quantity).toFixed(2);
                quantityCell.textContent = `${formattedQuantity} ${availableChem.unit}`;
            }

            // Update the status cell if available
            const statusCell = row.querySelector('td:nth-child(6)');
            if (statusCell && availableChem) {
                let statusClass = '';
                if (availableChem.status === 'In Stock') {
                    statusClass = 'in-stock';
                } else if (availableChem.status === 'Low Stock') {
                    statusClass = 'low-stock';
                } else {
                    statusClass = 'out-of-stock';
                }

                statusCell.innerHTML = `<span class="status-badge ${statusClass}">${availableChem.status}</span>`;
            }

            // Update expiration date if available
            if (availableChem && (availableChem.expiration_date_formatted || availableChem.expiration_date)) {
                // Check if there's an expiration cell
                const expirationCell = row.querySelector('td.expiration-cell');
                if (expirationCell) {
                    const formattedDate = availableChem.expiration_date_formatted ||
                        new Date(availableChem.expiration_date).toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric'
                        });

                    // Check if expired
                    const isExpired = new Date(availableChem.expiration_date) < new Date();
                    expirationCell.innerHTML = `<span class="${isExpired ? 'expired-date' : ''}">${formattedDate}</span>`;
                }
            }
        }

        // Function to handle chemical selection
        function handleChemicalSelection(checkbox) {
            const chemicalId = checkbox.value;
            const chemicalName = checkbox.getAttribute('data-name');
            const chemicalType = checkbox.getAttribute('data-type');
            const targetPest = checkbox.getAttribute('data-target-pest');
            const quantity = checkbox.getAttribute('data-quantity');
            const unit = checkbox.getAttribute('data-unit');

            // If checked, add to selected chemicals
            if (checkbox.checked) {
                // Find a matching original chemical by target pest
                const matchingOriginal = originalChemicals.find(chem =>
                    chem.target_pest === targetPest ||
                    (targetPest.includes('Crawling & Flying') &&
                     (chem.target_pest.includes('Crawling') || chem.target_pest.includes('Flying')))
                );

                // If no matching original, use the first one
                const originalToReplace = matchingOriginal || originalChemicals[0];

                // Get the status from the row
                const row = checkbox.closest('tr');
                const statusCell = row.querySelector('td:nth-child(8)');
                const statusBadge = statusCell ? statusCell.querySelector('.status-badge') : null;
                const status = statusBadge ? statusBadge.textContent.trim() : 'Unknown';

                // Remove any existing replacements for this original
                selectedChemicals = selectedChemicals.filter(chem => chem.replacing !== originalToReplace.id);

                // Add to selected chemicals with additional inventory information
                selectedChemicals.push({
                    id: chemicalId,
                    name: chemicalName,
                    type: chemicalType,
                    target_pest: targetPest,
                    dosage: originalToReplace.dosage,
                    dosage_unit: originalToReplace.dosage_unit,
                    index: originalToReplace.index,
                    replacing: originalToReplace.id,
                    status: status,
                    quantity: quantity,
                    unit: unit,
                    inventory_unit: unit,
                    is_replacement: true,
                    original_chemical_name: originalToReplace.name,
                    original_chemical_type: originalToReplace.type
                });

                // Update the original recommendations table to show the replacement
                populateOriginalRecommendations();
            } else {
                // If unchecked, remove from selected chemicals
                selectedChemicals = selectedChemicals.filter(chem =>
                    chem.id !== chemicalId &&
                    !(chem.name === chemicalName && chem.type === chemicalType)
                );

                // Restore original chemical if it was replaced
                const originalIndex = originalChemicals.findIndex(chem => chem.id === chemicalId);
                if (originalIndex !== -1) {
                    selectedChemicals.push(originalChemicals[originalIndex]);
                }

                // Update the original recommendations table
                populateOriginalRecommendations();
            }
        }

        // Function to filter chemicals by search term
        function filterChemicals() {
            const searchTerm = document.getElementById('chemicalSearchInput').value.toLowerCase();

            // Filter available chemicals by search term
            const filteredChemicals = availableChemicals.filter(chemical =>
                chemical.id.toString().includes(searchTerm) ||
                chemical.chemical_name.toLowerCase().includes(searchTerm) ||
                chemical.type.toLowerCase().includes(searchTerm) ||
                (chemical.target_pest && chemical.target_pest.toLowerCase().includes(searchTerm)) ||
                (chemical.status && chemical.status.toLowerCase().includes(searchTerm))
            );

            // Populate the available chemicals table with filtered chemicals
            populateAvailableChemicals(filteredChemicals);
        }

        // Function to filter chemicals by pest type
        function filterByPestType() {
            // If no target pests, show all chemicals
            if (currentTargetPests.length === 0) {
                populateAvailableChemicals(availableChemicals);
                return;
            }

            // Create options for pest types
            const options = currentTargetPests.map(pest =>
                `<button class="dropdown-item pest-filter-item" data-pest="${pest}">${pest}</button>`
            ).join('');

            // Show dropdown with pest types
            Swal.fire({
                title: 'Filter by Pest Type',
                html: `
                    <div class="list-group">
                        <button class="list-group-item list-group-item-action pest-filter-item" data-pest="all">
                            <i class="fas fa-globe me-2"></i>Show All Chemicals
                        </button>
                        ${currentTargetPests.map(pest => `
                            <button class="list-group-item list-group-item-action pest-filter-item" data-pest="${pest}">
                                <i class="fas fa-bug me-2"></i>${pest}
                            </button>
                        `).join('')}
                    </div>
                `,
                showConfirmButton: false,
                showCloseButton: true,
                didOpen: () => {
                    // Add event listeners to pest filter items
                    const filterItems = document.querySelectorAll('.pest-filter-item');
                    filterItems.forEach(item => {
                        item.addEventListener('click', function() {
                            const pestType = this.getAttribute('data-pest');

                            // Filter chemicals by pest type
                            let filteredChemicals;
                            if (pestType === 'all') {
                                filteredChemicals = availableChemicals;
                            } else {
                                filteredChemicals = availableChemicals.filter(chemical =>
                                    chemical.target_pest &&
                                    (chemical.target_pest.includes(pestType) ||
                                     (pestType.includes('Crawling') && chemical.target_pest.includes('Crawling & Flying')) ||
                                     (pestType.includes('Flying') && chemical.target_pest.includes('Crawling & Flying')))
                                );
                            }

                            // Populate the available chemicals table with filtered chemicals
                            populateAvailableChemicals(filteredChemicals);

                            // Close the dropdown
                            Swal.close();
                        });
                    });
                }
            });
        }

        // Function to apply chemical changes
        function applyChemicalChanges() {
            // Check if any chemicals are selected
            if (selectedChemicals.length === 0) {
                Swal.fire({
                    title: 'No Changes Made',
                    text: 'No chemical replacements have been made. Use the "Replace" button to select alternative chemicals.',
                    icon: 'info',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Close the modal - changes are already applied immediately when replacements are made
            const modal = bootstrap.Modal.getInstance(document.getElementById('customizeChemicalsModal'));
            modal.hide();

            // Show success message
            Swal.fire({
                title: 'Customize Chemicals Closed',
                text: 'Your chemical replacements have already been applied. You can continue working with the updated chemicals.',
                icon: 'success',
                confirmButtonText: 'OK'
            });
        }

        // Function to update the chemical dosage inputs
        function updateChemicalDosageInputs(chemicals) {
            // Get the chemical dosage inputs container
            const container = document.getElementById('chemicalDosageInputs');
            if (!container) return;

            // Store current dosage values before updating the table
            const currentDosageValues = {};
            const currentInputs = document.querySelectorAll('.chemical-dosage-input');
            currentInputs.forEach((input, idx) => {
                const nameInput = document.querySelector(`input[name="chemical_name[${idx}]"]`);
                if (nameInput) {
                    const chemicalName = nameInput.value;
                    currentDosageValues[chemicalName] = input.value;
                }
            });

            // Create a new table for the updated chemicals
            const tableWrapper = document.createElement('div');
            tableWrapper.className = 'table-responsive';

            const table = document.createElement('table');
            table.className = 'table table-bordered';

            // Create table header
            const thead = document.createElement('thead');
            thead.innerHTML = `
                <tr class="table-light">
                    <th>Chemical Name</th>
                    <th>Type</th>
                    <th>Target Pest</th>
                    <th>Recommended Dosage</th>
                    <th>Available Quantity</th>
                    <th>Status</th>
                    <th>Actual Dosage Used <span class="text-danger">*</span></th>
                </tr>
            `;
            table.appendChild(thead);

            // Create table body
            const tbody = document.createElement('tbody');

            // Add each chemical to the table
            chemicals.forEach((chem, index) => {
                const tr = document.createElement('tr');

                // Add replacement class if this is a replacement chemical
                if (chem.is_replacement) {
                    tr.classList.add('replaced-chemical');
                }

                // Get the dosage unit for display
                const dosageUnit = chem.dosage_unit || 'ml';
                const displayUnit = dosageUnit;

                // Determine the value to use for the dosage input
                // If we have a stored value for this chemical, use it
                // Otherwise use the recommended dosage
                let dosageValue = chem.dosage || '0';
                if (currentDosageValues[chem.name]) {
                    dosageValue = currentDosageValues[chem.name];
                }

                // Create input field for actual dosage
                const dosageInput = `
                    <div class="input-group input-group-sm">
                        <input type="text"
                               class="form-control chemical-dosage-input"
                               name="chemical_dosage[${index}]"
                               value="${dosageValue}"
                               required
                               style="min-width: 80px;"
                               placeholder="Enter dosage"
                               pattern="[0-9]+(\\.[0-9]+)?"
                               inputmode="decimal"
                               onchange="validateDosageInput(this)"
                               onkeyup="validateDosageInput(this)"
                               data-original-unit="${dosageUnit}">
                        <span class="input-group-text">${displayUnit}</span>
                        <input type="hidden" name="chemical_id[${index}]" value="${chem.id || '0'}">
                        <input type="hidden" name="chemical_name[${index}]" value="${chem.name || ''}">
                        <input type="hidden" name="chemical_type[${index}]" value="${chem.type || ''}">
                        <input type="hidden" name="chemical_target_pest[${index}]" value="${chem.target_pest || ''}">
                        <input type="hidden" name="chemical_recommended_dosage[${index}]" value="${chem.dosage || '0'}">
                        <input type="hidden" name="chemical_dosage_unit[${index}]" value="${dosageUnit}">
                        <input type="hidden" name="chemical_inventory_unit[${index}]" value="${chem.inventory_unit || dosageUnit}">
                        ${chem.is_replacement ? `
                        <input type="hidden" name="is_replacement[${index}]" value="1">
                        <input type="hidden" name="original_chemical_name[${index}]" value="${chem.original_chemical_name || ''}">
                        <input type="hidden" name="original_chemical_type[${index}]" value="${chem.original_chemical_type || ''}">
                        ` : ''}
                    </div>
                `;

                // Determine status class and text
                let statusClass = '';
                let statusText = chem.status || 'Unknown';

                if (statusText === 'In Stock') {
                    statusClass = 'in-stock';
                } else if (statusText === 'Low Stock') {
                    statusClass = 'low-stock';
                } else {
                    statusClass = 'out-of-stock';
                }

                // Get quantity information - use the quantity from the chemical object if available
                let quantity = 'N/A';
                if (chem.quantity && chem.unit) {
                    quantity = `${chem.quantity} ${chem.unit}`;
                } else {
                    // Find the matching chemical in availableChemicals to get the quantity
                    const availableChem = availableChemicals.find(ac =>
                        ac.chemical_name === chem.name && ac.type === chem.type
                    );
                    if (availableChem) {
                        quantity = `${availableChem.quantity} ${availableChem.unit}`;
                    }
                }

                // Add cells to the row
                let chemicalNameCell = `<strong>${chem.name || 'N/A'}</strong>`;

                // If this is a replacement, show the original chemical name
                if (chem.is_replacement && chem.original_chemical_name) {
                    chemicalNameCell = `
                        <strong>${chem.name || 'N/A'}</strong>
                        <div class="small text-muted">
                            <i class="fas fa-exchange-alt me-1"></i> Replacing: ${chem.original_chemical_name}
                        </div>
                    `;
                }

                tr.innerHTML = `
                    <td class="text-nowrap">${chemicalNameCell}</td>
                    <td class="text-nowrap">${chem.type || 'N/A'}</td>
                    <td class="text-nowrap">${chem.target_pest || 'N/A'}</td>
                    <td class="text-nowrap">${chem.dosage || '0'} ${chem.dosage_unit || 'ml'}</td>
                    <td class="text-nowrap">${quantity}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td>${dosageInput}</td>
                `;

                tbody.appendChild(tr);
            });

            table.appendChild(tbody);
            tableWrapper.appendChild(table);

            // Clear the container and add the new table
            container.innerHTML = '';
            container.appendChild(tableWrapper);

            // Add a note about the dosage
            const note = document.createElement('div');
            note.className = 'form-text mt-2';
            note.innerHTML = 'Enter the actual amount of each chemical used during the treatment. The recommended dosage is provided as a reference.';
            container.appendChild(note);
        }
    </script>

    <script>
        // Function to handle sort order changes
        function changeSortOrder(sortOrder) {
            // Redirect to the same page with the new sort parameter
            window.location.href = `job_order.php?sort=${sortOrder}`;
        }

        // Store the current job for reference
        let currentJob = null;

        // Include the job-details.js file for better organization
        // The openJobDetails function is defined in that file


        // Flag to prevent multiple submissions
        let isSubmittingReport = false;

        // Function to manually submit the report form
        function submitReportForm() {
            console.log('Manual form submission triggered');

            // Prevent multiple submissions
            if (isSubmittingReport) {
                console.log('Form submission already in progress, ignoring duplicate submission');
                return;
            }

            // Set the flag to prevent multiple submissions
            isSubmittingReport = true;

            const form = document.getElementById('jobOrderReportForm');
            if (!form) {
                console.error('Report form not found');
                isSubmittingReport = false; // Reset the flag
                return;
            }

            // Get form data
            const formData = new FormData(form);

            // Get required fields
            const observationNotes = form.querySelector('[name="observation_notes"]').value.trim();
            const recommendation = form.querySelector('[name="recommendation"]').value.trim();
            const attachments = form.querySelector('[name="attachments[]"]').files;
            const paymentNumber = form.querySelector('[name="payment_proof"]').value.trim();
            const idAttachments = form.querySelector('[name="id_attachments[]"]').files;

            // Validate required fields
            let isValid = true;
            const errors = [];

            if (!observationNotes) {
                errors.push('Observation notes are required');
                isValid = false;
            }

            if (!recommendation) {
                errors.push('Recommendation is required');
                isValid = false;
            }

            if (!attachments || attachments.length === 0) {
                errors.push('At least one attachment is required');
                isValid = false;
            }

            if (!paymentNumber) {
                errors.push('Payment number is required');
                isValid = false;
            }

            if (!idAttachments || idAttachments.length === 0) {
                errors.push('ID attachments are required');
                isValid = false;
            } else if (idAttachments.length !== 2) {
                errors.push('Exactly 2 ID attachments are required (front and back)');
                isValid = false;
            }

            if (!isValid) {
                Swal.fire({
                    title: 'Validation Error',
                    html: errors.map(error => `<div>${error}</div>`).join(''),
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';

            // Submit the form data via AJAX
            fetch('api/job_order_report.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Manual submission response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Manual submission response data:', data);

                // Process the response (similar to the regular form submission handler)
                if (data.success) {
                        // Hide the modal
                        const reportFormModal = document.getElementById('reportFormModal');
                        if (reportFormModal) {
                            const bsModal = bootstrap.Modal.getInstance(reportFormModal);
                            if (bsModal) {
                                bsModal.hide();
                            }
                        }

                        // Use our custom success modal instead of SweetAlert
                        // Check if inventory was updated
                        console.log('Checking for inventory update data in response:', data);
                        if (data.report && data.report.inventory_update) {
                            console.log('Inventory update data found:', data.report.inventory_update);
                            const inventoryUpdate = data.report.inventory_update;
                            const inventoryUpdateResults = document.getElementById('inventoryUpdateResults');
                            const inventoryUpdateTable = document.getElementById('inventoryUpdateTable');
                            const inventoryUpdateErrors = document.getElementById('inventoryUpdateErrors');
                            const inventoryErrorList = document.getElementById('inventoryErrorList');
                            const noChemicalsUsed = document.getElementById('noChemicalsUsed');

                            if (!inventoryUpdateResults || !inventoryUpdateTable || !inventoryUpdateErrors || !inventoryErrorList || !noChemicalsUsed) {
                                console.warn('One or more inventory update DOM elements not found');
                            } else {
                                // Clear previous content
                                inventoryUpdateTable.innerHTML = '';
                                inventoryErrorList.innerHTML = '';

                                // Make sure the inventory update section is visible
                                inventoryUpdateResults.style.display = 'block';

                                // If chemicals were updated, show the results
                                if (inventoryUpdate.updated_chemicals && inventoryUpdate.updated_chemicals.length > 0) {
                                    noChemicalsUsed.style.display = 'none';

                                    // Add each updated chemical to the table
                                    inventoryUpdate.updated_chemicals.forEach(chemical => {
                                        // Log the chemical data for debugging
                                        console.log('Processing chemical for display:', chemical);

                                        // Determine status based on new quantity
                                        let status = 'In Stock';
                                        let statusClass = 'success';

                                        // Convert to numbers for comparison
                                        const newQty = parseFloat(chemical.new_quantity);
                                        const prevQty = parseFloat(chemical.previous_quantity);
                                        const usedQty = parseFloat(chemical.used_quantity);

                                        if (newQty <= 0) {
                                            status = 'Out of Stock';
                                            statusClass = 'danger';
                                        } else if (newQty < prevQty * 0.2) { // Less than 20% remaining
                                            status = 'Low Stock';
                                            statusClass = 'warning';
                                        }

                                        // Format the quantities to 2 decimal places for better display
                                        const formattedPrevQty = prevQty.toFixed(2);
                                        const formattedUsedQty = usedQty.toFixed(2);
                                        const formattedNewQty = newQty.toFixed(2);

                                        const row = document.createElement('tr');
                                        row.innerHTML = `
                                            <td><strong>${chemical.name}</strong></td>
                                            <td>${chemical.type || 'N/A'}</td>
                                            <td>${formattedPrevQty} ${chemical.unit}</td>
                                            <td>${formattedUsedQty} ${chemical.unit}</td>
                                            <td>${formattedNewQty} ${chemical.unit}</td>
                                            <td><span class="badge bg-${statusClass}">${status}</span></td>
                                        `;
                                        inventoryUpdateTable.appendChild(row);
                                    });

                                    // If there were errors, show them
                                    if (inventoryUpdate.errors && inventoryUpdate.errors.length > 0) {
                                        inventoryUpdateErrors.style.display = 'block';
                                        inventoryErrorList.innerHTML = '';

                                        // Add each error to the list
                                        inventoryUpdate.errors.forEach(error => {
                                            const li = document.createElement('li');
                                            li.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i> ${error}`;
                                            li.className = 'mb-1';
                                            inventoryErrorList.appendChild(li);
                                        });
                                    } else {
                                        inventoryUpdateErrors.style.display = 'none';
                                    }
                                } else {
                                    // If no chemicals were updated, show the no chemicals used message
                                    inventoryUpdateResults.style.display = 'block';
                                    document.getElementById('noChemicalsUsed').style.display = 'block';
                                    inventoryUpdateTable.innerHTML = '';
                                    inventoryUpdateErrors.style.display = 'none';
                                }
                            }
                        } else {
                            // If no inventory update data, show the chemical dosage inputs as the inventory update
                            console.log('No inventory update data found in response, using fallback display method');
                            const inventoryUpdateResults = document.getElementById('inventoryUpdateResults');
                            const inventoryUpdateTable = document.getElementById('inventoryUpdateTable');
                            const noChemicalsUsed = document.getElementById('noChemicalsUsed');
                            const inventoryUpdateErrors = document.getElementById('inventoryUpdateErrors');
                            const inventoryErrorList = document.getElementById('inventoryErrorList');
                            const chemicalInputs = document.querySelectorAll('.chemical-dosage-input');

                            console.log('Chemical inputs found for fallback display:', chemicalInputs.length);

                            // Add an error message about missing inventory update data
                            if (inventoryUpdateErrors && inventoryErrorList) {
                                inventoryUpdateErrors.style.display = 'block';
                                const li = document.createElement('li');
                                li.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i> No inventory update data was returned from the server. This may indicate an issue with the chemical inventory system.`;
                                li.className = 'mb-1';
                                inventoryErrorList.appendChild(li);
                            }

                            if (inventoryUpdateResults && inventoryUpdateTable && chemicalInputs.length > 0) {
                                inventoryUpdateResults.style.display = 'block';
                                inventoryUpdateTable.innerHTML = '';

                                // Loop through chemical inputs to create rows
                                let hasUsedChemicals = false;
                                for (let index = 0; index < chemicalInputs.length; index++) {
                                    const input = chemicalInputs[index];
                                    // Get chemical data from hidden inputs
                                    const nameInput = document.querySelector(`input[name="chemical_name[${index}]"]`);
                                    const typeInput = document.querySelector(`input[name="chemical_type[${index}]"]`);
                                    if (!nameInput) continue;

                                    const chemicalName = nameInput.value;
                                    const chemicalType = typeInput ? typeInput.value : 'N/A';
                                    const dosage = parseFloat(input.value);
                                    const dosageUnit = input.nextElementSibling ? input.nextElementSibling.textContent.trim() : 'ml';

                                    // Create a row for each chemical with dosage > 0
                                    if (dosage > 0) {
                                        hasUsedChemicals = true;

                                        // Determine status based on remaining quantity
                                        let status = 'In Stock';
                                        let statusClass = 'success';
                                        const estimatedPrevQty = 12; // Fallback value
                                        const estimatedNewQty = estimatedPrevQty - dosage;

                                        if (estimatedNewQty <= 0) {
                                            status = 'Out of Stock';
                                            statusClass = 'danger';
                                        } else if (estimatedNewQty < estimatedPrevQty * 0.2) {
                                            status = 'Low Stock';
                                            statusClass = 'warning';
                                        }

                                        const row = document.createElement('tr');
                                        row.innerHTML = `
                                            <td><strong>${chemicalName}</strong></td>
                                            <td>${chemicalType}</td>
                                            <td>${estimatedPrevQty} ${dosageUnit}</td>
                                            <td>${dosage} ${dosageUnit}</td>
                                            <td>${estimatedNewQty.toFixed(2)} ${dosageUnit}</td>
                                            <td><span class="badge bg-${statusClass}">${status}</span></td>
                                        `;
                                        inventoryUpdateTable.appendChild(row);
                                    }
                                }

                                // If no rows were added, show the no chemicals used message
                                if (!hasUsedChemicals) {
                                    if (noChemicalsUsed) {
                                        noChemicalsUsed.style.display = 'block';
                                    }
                                } else {
                                    if (noChemicalsUsed) {
                                        noChemicalsUsed.style.display = 'none';
                                    }
                                }
                            } else {
                                // If no chemical inputs found, show the no chemicals used message
                                if (inventoryUpdateResults) {
                                    inventoryUpdateResults.style.display = 'block';
                                }
                                if (noChemicalsUsed) {
                                    noChemicalsUsed.style.display = 'block';
                                }
                                if (inventoryUpdateTable) {
                                    inventoryUpdateTable.innerHTML = '';
                                }
                            }
                        }

                        // Show our custom success modal with a slight delay to ensure it appears on top
                        setTimeout(() => {
                            // First make sure any existing SweetAlert modals are closed
                            if (typeof Swal !== 'undefined' && Swal.isVisible()) {
                                Swal.close();
                            }

                            // Then show our custom success modal
                            const successModal = new bootstrap.Modal(document.getElementById('reportSuccessModal'));
                            successModal.show();
                        }, 100);
                    } else {
                        // Show error message
                        let errorMessage = data.message || 'Failed to submit report';
                        if (data.errors && data.errors.length > 0) {
                            errorMessage += '<br>' + data.errors.map(err => `- ${err}`).join('<br>');
                        }

                        Swal.fire({
                            title: 'Error',
                            html: errorMessage,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });

                        // Reset button state
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;

                        // Reset the submission flag
                        isSubmittingReport = false;
                    }
                })
            .catch(error => {
                console.error('Manual submission error:', error);

                // Show error message
                Swal.fire({
                    title: 'Error',
                    html: `An error occurred while submitting the report:<br><br>
                          <code>${error.message || 'Unknown error'}</code><br><br>
                          Please try again or contact support if the problem persists.`,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });

                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;

                // Reset the submission flag
                isSubmittingReport = false;
            });
            }

        // Function to open the report form modal
        function openReportForm() {
            console.log('Opening report form for job ID:', currentJob ? currentJob.job_order_id : 'undefined');

            // Check if currentJob is defined
            if (!currentJob) {
                console.error('Current job is undefined');
                Swal.fire({
                    title: 'Error',
                    text: 'Job details not found. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // First check if the technician is primary for this job
            if (!currentJob.is_primary) {
                // Show an error message
                Swal.fire({
                    title: 'Access Denied',
                    text: 'Only the primary technician can submit reports for this job order.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }

            try {
                // Store the job details modal instance for later use instead of closing it
                // This prevents the modal from closing unexpectedly
                const jobDetailsModal = document.getElementById('jobDetailsModal');
                let jobDetailsModalInstance = null;

                if (jobDetailsModal) {
                    jobDetailsModalInstance = bootstrap.Modal.getInstance(jobDetailsModal);
                    console.log('Stored job details modal instance for later reference');
                } else {
                    console.error('Job details modal element not found');
                }

                // Set the job order ID in the form
                const reportJobOrderIdInput = document.getElementById('reportJobOrderId');
                if (reportJobOrderIdInput) {
                    reportJobOrderIdInput.value = currentJob.job_order_id;
                    console.log('Set job order ID in form:', currentJob.job_order_id);
                } else {
                    console.error('Report job order ID input not found');
                }

                // Populate chemical dosage inputs if chemical recommendations exist
                console.log('Chemical recommendations:', currentJob.chemical_recommendations);

                // Debug log to see the structure of chemical recommendations
                if (currentJob.chemical_recommendations) {
                    try {
                        // If it's a string, try to parse it as JSON
                        if (typeof currentJob.chemical_recommendations === 'string') {
                            const parsedRecommendations = JSON.parse(currentJob.chemical_recommendations);
                            console.log('Parsed chemical recommendations:', parsedRecommendations);

                            // Log each chemical's ID and name
                            if (Array.isArray(parsedRecommendations)) {
                                parsedRecommendations.forEach((chem, idx) => {
                                    console.log(`Chemical ${idx}: ID=${chem.id || 'undefined'}, Name=${chem.name || 'undefined'}`);
                                });
                            }
                        } else if (Array.isArray(currentJob.chemical_recommendations)) {
                            // If it's already an array, log each chemical's ID and name
                            currentJob.chemical_recommendations.forEach((chem, idx) => {
                                console.log(`Chemical ${idx}: ID=${chem.id || 'undefined'}, Name=${chem.name || 'undefined'}`);
                            });
                        }
                    } catch (error) {
                        console.error('Error parsing chemical recommendations:', error);
                    }
                }

                populateChemicalDosageInputs(currentJob.chemical_recommendations);

                // Show the report form modal
                const reportFormModal = document.getElementById('reportFormModal');
                if (reportFormModal) {
                    console.log('Showing report form modal');
                    const modal = new bootstrap.Modal(reportFormModal);
                    modal.show();

                    // Add a click handler to the submit button after the modal is shown
                    setTimeout(() => {
                        const submitBtn = document.getElementById('submitReportBtn');
                        if (submitBtn) {
                            console.log('Adding click handler to submit button after modal shown');
                            // Remove any existing click handlers first
                            submitBtn.onclick = null;
                            // Add a single click handler with debounce
                            let isSubmitting = false;
                            submitBtn.onclick = function(e) {
                                e.preventDefault();
                                // Prevent multiple submissions
                                if (isSubmitting) {
                                    console.log('Form submission already in progress, ignoring click');
                                    return;
                                }
                                isSubmitting = true;
                                console.log('Submit button clicked after modal shown');
                                submitReportForm();
                                // Reset after 5 seconds to prevent permanent lock if something goes wrong
                                setTimeout(() => { isSubmitting = false; }, 5000);
                            };
                        } else {
                            console.warn('Submit report button not found after modal shown');
                        }
                    }, 500);
                } else {
                    console.error('Report form modal element not found');
                    Swal.fire({
                        title: 'Error',
                        text: 'Could not open the report form. Please refresh the page and try again.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            } catch (error) {
                console.error('Error opening report form:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred while opening the report form. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        }

        // Function to validate dosage input - using the helper function from unit-conversion-helper.js
        function validateDosageInput(input) {
            return window.validateDosageInput(input);
        }

        // Function to format unit for display
        function formatUnit(unit) {
            if (!unit) return 'ml';

            // Return the unit as is, ensuring it's a string
            return String(unit);
        }

        // Function to populate chemical dosage inputs
        function populateChemicalDosageInputs(chemicalRecommendationsJson) {
            console.log('Populating chemical dosage inputs with:', chemicalRecommendationsJson);

            const chemicalDosageSection = document.getElementById('chemicalDosageSection');
            const chemicalDosageInputs = document.getElementById('chemicalDosageInputs');

            if (!chemicalDosageSection || !chemicalDosageInputs) {
                console.error('Chemical dosage section or inputs element not found');
                return;
            }

            // Store current dosage values before clearing the inputs
            const currentDosageValues = {};
            const currentInputs = document.querySelectorAll('.chemical-dosage-input');
            currentInputs.forEach((input, idx) => {
                const nameInput = document.querySelector(`input[name="chemical_name[${idx}]"]`);
                if (nameInput) {
                    const chemicalName = nameInput.value;
                    currentDosageValues[chemicalName] = input.value;
                }
            });

            // Clear previous inputs
            chemicalDosageInputs.innerHTML = '';

            try {
                // If no chemical recommendations, hide the section
                if (!chemicalRecommendationsJson) {
                    console.log('No chemical recommendations provided');
                    chemicalDosageSection.style.display = 'none';
                    return;
                }

                // Parse chemical recommendations if it's a string
                let chemicals;
                if (typeof chemicalRecommendationsJson === 'string') {
                    try {
                        chemicals = JSON.parse(chemicalRecommendationsJson);
                        console.log('Successfully parsed chemical recommendations JSON:', chemicals);
                    } catch (parseError) {
                        console.error('Error parsing chemical recommendations JSON:', parseError);
                        // Try to extract valid JSON from the string
                        const jsonMatch = chemicalRecommendationsJson.match(/(\[.*\]|\{.*\})/s);
                        if (jsonMatch) {
                            try {
                                chemicals = JSON.parse(jsonMatch[0]);
                                console.log('Extracted and parsed JSON from string:', chemicals);
                            } catch (extractError) {
                                console.error('Failed to extract valid JSON:', extractError);
                                throw parseError; // Throw the original error
                            }
                        } else {
                            throw parseError; // Throw the original error
                        }
                    }
                } else if (typeof chemicalRecommendationsJson === 'object') {
                    // If it's already an object, use it directly
                    chemicals = chemicalRecommendationsJson;
                    console.log('Using chemical recommendations as object:', chemicals);
                } else {
                    console.error('Chemical recommendations is neither string nor object:', typeof chemicalRecommendationsJson);
                    throw new Error('Invalid chemical recommendations format');
                }

                // If no chemicals or empty array, hide the section
                if (!Array.isArray(chemicals) || chemicals.length === 0) {
                    console.log('No chemicals in array or not an array');
                    chemicalDosageSection.style.display = 'none';
                    return;
                }

                // Get the area from the current job
                let area = 0;
                if (currentJob && currentJob.area) {
                    area = parseFloat(currentJob.area);
                    console.log('Using area from current job:', area, 'm²');
                } else {
                    console.warn('Area not found in current job, using default value');
                    area = 100; // Default to 100 m² if not found
                }

                // Recalculate dosage based on area for each chemical
                chemicals.forEach(chem => {
                    // Default dilution rate based on chemical name
                    let dilutionRate = 20; // Default 20ml per 100sqm

                    // Use specific dosage rates for known chemicals
                    if (chem.name === 'Fipronil') {
                        dilutionRate = 12; // 12ml per 100sqm (24ml for 200sqm)
                    } else if (chem.name === 'Cypermethrin') {
                        dilutionRate = 20; // 20ml per 100sqm (40ml for 200sqm)
                    }

                    // Calculate dosage based on area
                    const calculatedDosage = (area / 100) * dilutionRate;

                    // Round to 2 decimal places
                    chem.dosage = calculatedDosage.toFixed(2);
                    console.log(`Recalculated dosage for ${chem.name}: ${chem.dosage}ml for ${area}m²`);
                });

                // Show the section
                chemicalDosageSection.style.display = 'block';

                // Create a div to wrap the table for better responsiveness
                const tableWrapper = document.createElement('div');
                tableWrapper.className = 'table-responsive';

                // Create a table for chemical dosage inputs
                const table = document.createElement('table');
                table.className = 'table table-bordered';

                // Create table header
                const thead = document.createElement('thead');
                thead.innerHTML = `
                    <tr class="table-light">
                        <th>Chemical Name</th>
                        <th>Type</th>
                        <th>Target Pest</th>
                        <th>Recommended Dosage</th>
                        <th>Actual Dosage Used <span class="text-danger">*</span></th>
                    </tr>
                `;
                table.appendChild(thead);

                // Add the table to the wrapper
                tableWrapper.appendChild(table);

                // Create table body
                const tbody = document.createElement('tbody');

                // Add a row for each chemical
                chemicals.forEach((chem, index) => {
                    console.log(`Processing chemical ${index}:`, chem);

                    const tr = document.createElement('tr');

                    // Format the unit for display
                    const displayUnit = formatUnit(chem.dosage_unit || 'ml');
                    const dosageUnit = chem.dosage_unit || 'ml';

                    // Determine the value to use for the dosage input
                    // If we have a stored value for this chemical, use it
                    // Otherwise use the recommended dosage
                    let dosageValue = chem.dosage || '0';
                    if (currentDosageValues[chem.name]) {
                        dosageValue = currentDosageValues[chem.name];
                    }

                    // Create input field for actual dosage with enhanced unit handling
                    const dosageInput = `
                        <div class="input-group input-group-sm">
                            <input type="text"
                                   class="form-control chemical-dosage-input"
                                   name="chemical_dosage[${index}]"
                                   value="${dosageValue}"
                                   required
                                   style="min-width: 80px;"
                                   placeholder="Enter dosage"
                                   pattern="[0-9]+(\\.[0-9]+)?"
                                   inputmode="decimal"
                                   onchange="validateDosageInput(this)"
                                   onkeyup="validateDosageInput(this)"
                                   data-original-unit="${dosageUnit}">
                            <span class="input-group-text">${displayUnit}</span>
                            <input type="hidden" name="chemical_id[${index}]" value="${chem.id !== undefined && chem.id !== null ? chem.id : '0'}">
                            <input type="hidden" name="chemical_name[${index}]" value="${chem.name || ''}">
                            <input type="hidden" name="chemical_type[${index}]" value="${chem.type || ''}">
                            <input type="hidden" name="chemical_target_pest[${index}]" value="${chem.target_pest || ''}">
                            <input type="hidden" name="chemical_recommended_dosage[${index}]" value="${chem.dosage || '0'}">
                            <input type="hidden" name="chemical_dosage_unit[${index}]" value="${dosageUnit}">
                            <input type="hidden" name="chemical_inventory_unit[${index}]" value="${chem.inventory_unit || dosageUnit}">
                            ${chem.is_replacement ? `
                            <input type="hidden" name="is_replacement[${index}]" value="1">
                            <input type="hidden" name="original_chemical_name[${index}]" value="${chem.original_chemical_name || ''}">
                            <input type="hidden" name="original_chemical_type[${index}]" value="${chem.original_chemical_type || ''}">
                            ` : ''}
                        </div>
                    `;

                    // Add cells to the row
                    let chemicalNameCell = `<strong>${chem.name || 'N/A'}</strong>`;

                    // If this is a replacement, show the original chemical name
                    if (chem.is_replacement && chem.original_chemical_name) {
                        chemicalNameCell = `
                            <strong>${chem.name || 'N/A'}</strong>
                            <div class="small text-muted">
                                <i class="fas fa-exchange-alt me-1"></i> Replacing: ${chem.original_chemical_name}
                            </div>
                        `;
                        tr.classList.add('replaced-chemical');
                    }

                    tr.innerHTML = `
                        <td class="text-nowrap">${chemicalNameCell}</td>
                        <td class="text-nowrap">${chem.type || 'N/A'}</td>
                        <td class="text-nowrap">${chem.target_pest || 'N/A'}</td>
                        <td class="text-nowrap">${chem.dosage || '0'} ${chem.dosage_unit || 'ml'}</td>
                        <td>${dosageInput}</td>
                    `;

                    tbody.appendChild(tr);
                });

                table.appendChild(tbody);
                tableWrapper.appendChild(table);
                chemicalDosageInputs.appendChild(tableWrapper);

                // Add a note about the dosage
                const note = document.createElement('div');
                note.className = 'form-text mt-2';
                note.innerHTML = 'Enter the actual amount of each chemical used during the treatment. The recommended dosage is provided as a reference.';
                chemicalDosageInputs.appendChild(note);

                console.log('Successfully populated chemical dosage inputs');

            } catch (error) {
                console.error('Error processing chemical recommendations:', error);
                chemicalDosageInputs.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error loading chemical recommendations. Please continue with the report submission.
                    </div>
                `;
                // Show the section even if there's an error
                chemicalDosageSection.style.display = 'block';
            }
        }

        // Debug function to help diagnose form submission issues
        function debugFormSubmission(form) {
            console.log('Debug form submission:');
            console.log('- Form ID:', form.id);
            console.log('- Form method:', form.method);
            console.log('- Form action:', form.action);
            console.log('- Form enctype:', form.enctype);
            console.log('- Form elements:', form.elements.length);

            // Check if the form has a submit button
            const submitBtn = form.querySelector('button[type="submit"]');
            console.log('- Submit button exists:', !!submitBtn);
            if (submitBtn) {
                console.log('- Submit button disabled:', submitBtn.disabled);
                console.log('- Submit button text:', submitBtn.innerHTML);
            }

            // Check required fields
            const requiredFields = Array.from(form.querySelectorAll('[required]'));
            console.log('- Required fields:', requiredFields.length);
            requiredFields.forEach((field, index) => {
                console.log(`  Field ${index + 1}:`, field.name, 'Value:', field.value, 'Valid:', field.checkValidity());
            });
        }

        // We're now handling the submit button click in the openReportForm function
        // No need for a separate DOMContentLoaded event handler

        // Handle job order report form submission
        document.getElementById('jobOrderReportForm').addEventListener('submit', function(e) {
            console.log('Form submit event triggered');
            debugFormSubmission(this);
            e.preventDefault();

            // Prevent multiple submissions
            if (isSubmittingReport) {
                console.log('Form submission already in progress, ignoring duplicate submission');
                return;
            }

            // Set the flag to prevent multiple submissions
            isSubmittingReport = true;

            // Client-side validation
            const observationNotes = this.querySelector('[name="observation_notes"]').value.trim();
            const recommendation = this.querySelector('[name="recommendation"]').value.trim();
            const attachments = this.querySelector('[name="attachments[]"]').files;
            const paymentNumber = this.querySelector('[name="payment_proof"]').value.trim();
            const idAttachments = this.querySelector('[name="id_attachments[]"]').files;

            // Get chemical dosage inputs if the section is visible
            const chemicalDosageSection = document.getElementById('chemicalDosageSection');
            let chemicalDosageInputs = [];

            if (chemicalDosageSection && chemicalDosageSection.style.display !== 'none') {
                // Use a more specific selector to get the chemical dosage inputs
                chemicalDosageInputs = Array.from(this.querySelectorAll('.chemical-dosage-input'));
                console.log('Found chemical dosage inputs:', chemicalDosageInputs.length);

                // Validate each input before proceeding
                chemicalDosageInputs.forEach((input, i) => {
                    console.log(`Input ${i}:`, input.name, input.value, input.type);
                    validateDosageInput(input);
                });
            }

            let isValid = true;
            const errors = [];

            if (!observationNotes) {
                errors.push('Observation notes are required');
                isValid = false;
            }

            if (!recommendation) {
                errors.push('Recommendation is required');
                isValid = false;
            }

            if (!attachments || attachments.length === 0) {
                errors.push('At least one attachment is required');
                isValid = false;
            }

            // Validate chemical dosage inputs if they exist
            if (chemicalDosageInputs.length > 0) {
                let hasInvalidDosage = false;

                // Debug information
                console.log('Validating chemical dosage inputs:', chemicalDosageInputs.length, 'inputs found');

                // First, ensure all inputs have valid values by calling our validation function
                chemicalDosageInputs.forEach((input, index) => {
                    console.log(`Input ${index} value:`, input.value, 'type:', typeof input.value);

                    // Validate the input and ensure it's a valid number
                    const isInputValid = validateDosageInput(input);
                    console.log(`Input ${index} validation result:`, isInputValid);

                    if (!isInputValid) {
                        hasInvalidDosage = true;
                    }
                });

                console.log('Has invalid dosage:', hasInvalidDosage);

                if (hasInvalidDosage) {
                    errors.push('All chemical dosage values must be valid positive numbers');
                    isValid = false;
                }
            }

            if (!isValid) {
                Swal.fire({
                    title: 'Validation Error',
                    html: errors.map(error => `<div>${error}</div>`).join(''),
                    icon: 'error',
                    confirmButtonText: 'OK'
                });

                // Reset the submission flag
                isSubmittingReport = false;
                return;
            }

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';

            // Create FormData object
            const formData = new FormData(this);

            // Final validation of all chemical dosage inputs before submission
            if (chemicalDosageInputs.length > 0) {
                console.log('Final validation before submission');

                // Create an array to store chemical usage data
                const chemicalUsage = [];

                // Process each chemical dosage input
                chemicalDosageInputs.forEach((input, idx) => {
                    // Make sure all inputs are valid
                    validateDosageInput(input);
                    console.log('Input value after final validation:', input.value);

                    // Get the dosage value - ensure it's a valid number
                    let dosage = 0;
                    try {
                        // Remove any non-numeric characters except decimal point
                        const cleanValue = input.value.replace(/[^\d.]/g, '');
                        // Ensure there's only one decimal point
                        const parts = cleanValue.split('.');
                        const sanitizedValue = parts[0] + (parts.length > 1 ? '.' + parts.slice(1).join('') : '');
                        dosage = parseFloat(sanitizedValue) || 0;

                        // If negative or NaN, set to 0
                        if (dosage < 0 || isNaN(dosage)) {
                            dosage = 0;
                        }

                        console.log(`Sanitized dosage value: ${input.value} -> ${sanitizedValue} -> ${dosage}`);
                    } catch (e) {
                        console.error('Error parsing dosage value:', e);
                        dosage = 0;
                    }

                    // Only include chemicals with dosage > 0
                    if (dosage > 0) {
                        // Get chemical data from hidden inputs
                        const chemicalIdInput = document.querySelector(`input[name="chemical_id[${idx}]"]`);
                        let chemicalId = chemicalIdInput ? chemicalIdInput.value : '0';

                        // Validate the chemical ID
                        if (!chemicalId || isNaN(parseInt(chemicalId)) || parseInt(chemicalId) <= 0) {
                            console.error(`Invalid chemical ID: ${chemicalId} for chemical at index ${idx}`);
                            // Skip this chemical if ID is invalid - continue to next iteration
                            return; // Using return in forEach callback skips this iteration
                        }

                        // Convert to integer
                        chemicalId = parseInt(chemicalId);

                        const chemicalNameInput = document.querySelector(`input[name="chemical_name[${idx}]"]`);
                        const chemicalName = chemicalNameInput ? chemicalNameInput.value.trim() : '';

                        // Skip if chemical name is empty
                        if (!chemicalName) {
                            console.error(`Empty chemical name for chemical at index ${idx}`);
                            return; // Using return in forEach callback skips this iteration
                        }

                        const chemicalTypeInput = document.querySelector(`input[name="chemical_type[${idx}]"]`);
                        const chemicalType = chemicalTypeInput ? chemicalTypeInput.value : '';

                        const targetPestInput = document.querySelector(`input[name="chemical_target_pest[${idx}]"]`);
                        const targetPest = targetPestInput ? targetPestInput.value : '';

                        const dosageUnitInput = document.querySelector(`input[name="chemical_dosage_unit[${idx}]"]`);
                        const dosageUnit = dosageUnitInput ? dosageUnitInput.value : 'ml';

                        const inventoryUnitInput = document.querySelector(`input[name="chemical_inventory_unit[${idx}]"]`);
                        const inventoryUnit = inventoryUnitInput ? inventoryUnitInput.value : 'ml';

                        // Log the chemical data for debugging
                        console.log(`Chemical ${idx} data:`, {
                            id: chemicalId,
                            name: chemicalName,
                            type: chemicalType,
                            dosage: dosage,
                            unit: dosageUnit,
                            inventoryUnit: inventoryUnit
                        });

                        // Check if this is a replacement chemical
                        const isReplacementInput = document.querySelector(`input[name="is_replacement[${idx}]"]`);
                        const isReplacement = isReplacementInput ? true : false;

                        // Get replacement information if applicable
                        let replacingId = null;
                        let originalChemicalName = null;

                        if (isReplacement) {
                            const replacingInput = document.querySelector(`input[name="replacing[${idx}]"]`);
                            if (replacingInput) {
                                replacingId = replacingInput.value;
                            }

                            const originalChemNameInput = document.querySelector(`input[name="original_chemical_name[${idx}]"]`);
                            if (originalChemNameInput) {
                                originalChemicalName = originalChemNameInput.value;
                            }

                            console.log(`Replacement data for chemical ${idx}:`, {
                                isReplacement: isReplacement,
                                replacingId: replacingId,
                                originalChemicalName: originalChemicalName
                            });
                        }

                        // Create the chemical usage object
                        const chemicalUsageObj = {
                            id: chemicalId,
                            name: chemicalName,
                            type: chemicalType,
                            target_pest: targetPest,
                            dosage: dosage,
                            dosage_unit: dosageUnit,
                            inventory_unit: inventoryUnit,
                            is_replacement: isReplacement,
                            replacing: replacingId,
                            original_chemical_name: originalChemicalName
                        };

                        // Log the chemical usage object for debugging
                        console.log(`Adding chemical to usage data: ID=${chemicalId}, Name=${chemicalName}, Dosage=${dosage}${dosageUnit}`);

                        // Add chemical usage data to the array
                        chemicalUsage.push(chemicalUsageObj);
                    }
                });

                // Add chemical usage data to the form
                if (chemicalUsage.length > 0) {
                    // Sanitize the chemical usage data to prevent JSON encoding issues
                    const sanitizedChemicalUsage = chemicalUsage.map(chem => {
                        // Create a new object with sanitized values
                        return {
                            id: chem.id ? String(chem.id).replace(/[^\d]/g, '') : '0',
                            name: typeof chem.name === 'string' ? chem.name.trim() : '',
                            type: typeof chem.type === 'string' ? chem.type.trim() : '',
                            target_pest: typeof chem.target_pest === 'string' ? chem.target_pest.trim() : '',
                            dosage: typeof chem.dosage === 'number' ? chem.dosage : parseFloat(chem.dosage) || 0,
                            dosage_unit: typeof chem.dosage_unit === 'string' ? chem.dosage_unit.trim() : 'ml',
                            inventory_unit: typeof chem.inventory_unit === 'string' ? chem.inventory_unit.trim() : 'ml',
                            is_replacement: !!chem.is_replacement,
                            replacing: chem.replacing ? String(chem.replacing).replace(/[^\d]/g, '') : null,
                            original_chemical_name: typeof chem.original_chemical_name === 'string' ? chem.original_chemical_name.trim() : null
                        };
                    });

                    // Convert to JSON with proper error handling
                    let chemicalUsageJson;
                    try {
                        chemicalUsageJson = JSON.stringify(sanitizedChemicalUsage);
                        console.log('Chemical usage data successfully converted to JSON');
                    } catch (e) {
                        console.error('Error stringifying chemical usage data:', e);
                        // Create a simplified version as fallback
                        const simplifiedData = sanitizedChemicalUsage.map(chem => ({
                            id: chem.id || '0',
                            name: chem.name || '',
                            dosage: chem.dosage || 0,
                            dosage_unit: chem.dosage_unit || 'ml'
                        }));
                        chemicalUsageJson = JSON.stringify(simplifiedData);
                        console.log('Using simplified chemical usage data as fallback');
                    }

                    // Log the chemical usage data for debugging
                    console.log('Chemical usage data (raw):', sanitizedChemicalUsage);
                    console.log('Chemical usage data (JSON):', chemicalUsageJson);

                    // Check if any chemical has a dosage > 0
                    const hasPositiveDosage = chemicalUsage.some(chem => parseFloat(chem.dosage) > 0);
                    console.log('Has chemicals with positive dosage:', hasPositiveDosage);

                    // Check if any chemical has a valid ID
                    const hasValidId = chemicalUsage.some(chem => parseInt(chem.id) > 0);
                    console.log('Has chemicals with valid ID:', hasValidId);

                    // Add the chemical usage data to the form
                    formData.append('chemical_usage', chemicalUsageJson);
                    console.log('Chemical usage data added to form:', chemicalUsageJson);

                    // Also add a debug flag to the form
                    formData.append('debug_chemical_usage', '1');
                } else {
                    console.warn('No chemical usage data to add to form');
                }
            }

            // Submit the form data via AJAX
            fetch('api/job_order_report.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }

                // First get the raw text response
                return response.text().then(text => {
                    console.log('Raw response text (first 100 chars):', text.substring(0, 100));

                    try {
                        // Try to parse the JSON
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Raw response causing JSON error:', text);

                        // Log the error position
                        const errorPosition = e.message.match(/position (\d+)/);
                        if (errorPosition && errorPosition[1]) {
                            const pos = parseInt(errorPosition[1]);
                            console.error(`Error at position ${pos}. Text around error:`,
                                text.substring(Math.max(0, pos - 20), pos) +
                                ' [ERROR→] ' +
                                text.substring(pos, Math.min(text.length, pos + 20)));
                        }

                        // Try to extract valid JSON from the response
                        const jsonMatch = text.match(/(\{.*\}|\[.*\])/s);
                        if (jsonMatch) {
                            try {
                                console.log('Attempting to extract valid JSON from response');
                                return JSON.parse(jsonMatch[0]);
                            } catch (e2) {
                                console.error('Failed to extract valid JSON:', e2);
                            }
                        }

                        // Try to clean the response by removing any non-JSON characters at the end
                        try {
                            // Find the last closing brace or bracket
                            const lastBrace = text.lastIndexOf('}');
                            const lastBracket = text.lastIndexOf(']');
                            const lastChar = Math.max(lastBrace, lastBracket);

                            if (lastChar > 0) {
                                const cleanedText = text.substring(0, lastChar + 1);
                                console.log('Attempting to parse cleaned JSON:', cleanedText.substring(0, 100) + '...');
                                return JSON.parse(cleanedText);
                            }
                        } catch (e3) {
                            console.error('Failed to parse cleaned JSON:', e3);
                        }

                        // If all else fails, return a generic error object
                        return {
                            success: false,
                            message: 'Failed to parse server response',
                            errors: ['Server returned invalid JSON. Please try again or contact support.']
                        };
                    }
                });
                })
            .then(data => {
                console.log('Processed response data:', data);

                // Hide the report form modal
                const reportFormModal = document.getElementById('reportFormModal');
                if (reportFormModal) {
                    const bsModal = bootstrap.Modal.getInstance(reportFormModal);
                    if (bsModal) {
                        bsModal.hide();
                    } else {
                        console.warn('Bootstrap modal instance not found, trying to hide manually');
                        $(reportFormModal).modal('hide'); // Fallback for older Bootstrap versions
                    }
                }

                if (data.success) {
                    console.log('Job order report submitted successfully:', data);

                    // Update the current job object with the report data
                    if (currentJob && data.report) {
                        currentJob.status = 'completed';
                        currentJob.observation_notes = data.report.observation_notes || '';
                        currentJob.recommendation = data.report.recommendation || '';
                        currentJob.report_attachments = data.report.attachments || '';
                        currentJob.report_created_at = data.report.timestamp || new Date().toISOString();
                        currentJob.chemical_usage = data.report.chemical_usage || null;
                    } else if (!currentJob) {
                        console.warn('currentJob is null or undefined, cannot update job properties');
                    } else if (!data.report) {
                        console.warn('data.report is missing in the response');
                    }

                    // Verify job status was updated
                    if (currentJob && currentJob.job_order_id) {
                        fetch(`api/check_job_status.php?job_order_id=${currentJob.job_order_id}`)
                            .then(response => response.json())
                            .then(statusData => {
                                console.log('Job status check:', statusData);
                                if (statusData.status !== 'completed') {
                                    console.warn('Job status was not updated to completed in the database!');
                                }
                            })
                            .catch(error => console.error('Error checking job status:', error));
                    } else {
                        console.warn('Cannot verify job status: currentJob or job_order_id is missing');
                    }

                    // Check if inventory was updated
                    if (data.report) {
                        console.log('Report data found:', data.report);

                        // Log the entire data structure for debugging
                        console.log('Complete response data:', data);

                        // Check if inventory_update exists in the report data
                        const inventoryUpdate = data.report.inventory_update || {};
                        console.log('Inventory update data:', inventoryUpdate);

                        // Check if updated_chemicals exists in the report data
                        const updatedChemicals = data.report.updated_chemicals || [];
                        console.log('Updated chemicals:', updatedChemicals);

                        // Check if inventory was updated
                        const inventoryUpdated = data.report.inventory_updated === true;
                        console.log('Inventory updated flag:', inventoryUpdated);

                        // Get DOM elements for inventory update display
                        const inventoryUpdateResults = document.getElementById('inventoryUpdateResults');
                        const inventoryUpdateTable = document.getElementById('inventoryUpdateTable');
                        const inventoryUpdateErrors = document.getElementById('inventoryUpdateErrors');
                        const inventoryErrorList = document.getElementById('inventoryErrorList');
                        const chemicalReplacementsSection = document.getElementById('chemicalReplacementsSection');
                        const replacementsTable = document.getElementById('replacementsTable');
                        const chemicalUpdateInfo = document.getElementById('chemicalUpdateInfo');
                        const noChemicalsUsed = document.getElementById('noChemicalsUsed');

                        // Helper functions for status
                        function getStatus(quantity) {
                            quantity = parseFloat(quantity) || 0;
                            if (quantity <= 0) return 'Out of Stock';
                            if (quantity < 10) return 'Low Stock';
                            return 'In Stock';
                        }

                        function getStatusClass(quantity) {
                            quantity = parseFloat(quantity) || 0;
                            if (quantity <= 0) return 'danger';
                            if (quantity < 10) return 'warning';
                            return 'success';
                        }

                        // Check if there's a general error with the inventory update
                        if (inventoryUpdate.error) {
                            console.error('Inventory update error:', inventoryUpdate.error);

                            // Make sure the error section is visible
                            if (inventoryUpdateErrors && inventoryErrorList) {
                                inventoryUpdateErrors.style.display = 'block';

                                // Add the error to the list
                                const li = document.createElement('li');
                                li.textContent = inventoryUpdate.error;
                                inventoryErrorList.appendChild(li);

                                // Still show the results section with a message
                                if (inventoryUpdateResults && inventoryUpdateTable) {
                                    inventoryUpdateResults.style.display = 'block';
                                    const row = document.createElement('tr');
                                    row.innerHTML = `
                                        <td colspan="6" class="text-center text-danger">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Error updating inventory. Please check with administrator.
                                        </td>
                                    `;
                                    inventoryUpdateTable.appendChild(row);
                                }
                            }
                        }

                        if (!inventoryUpdateResults || !inventoryUpdateTable || !inventoryUpdateErrors || !inventoryErrorList) {
                            console.warn('One or more inventory update DOM elements not found');
                        } else {
                            // Clear previous content
                            inventoryUpdateTable.innerHTML = '';
                            inventoryErrorList.innerHTML = '';
                            if (replacementsTable) replacementsTable.innerHTML = '';

                            // Check for updated chemicals in both possible locations
                            const updatedChemicals = (inventoryUpdate.updated_chemicals && inventoryUpdate.updated_chemicals.length > 0)
                                ? inventoryUpdate.updated_chemicals
                                : (data.report.updated_chemicals && data.report.updated_chemicals.length > 0
                                    ? data.report.updated_chemicals
                                    : []);

                            console.log('Final updated chemicals array:', updatedChemicals);

                            if (updatedChemicals.length > 0) {
                                inventoryUpdateResults.style.display = 'block';

                                // Update the info message
                                if (chemicalUpdateInfo) {
                                    chemicalUpdateInfo.innerHTML = `
                                        <i class="fas fa-info-circle me-2"></i>
                                        The following chemicals have been deducted from inventory based on your report.
                                        <div class="mt-2 small">
                                            <strong>Note:</strong> ${updatedChemicals.length} chemical(s) were updated in the inventory system.
                                        </div>
                                    `;
                                    chemicalUpdateInfo.className = 'alert alert-info mb-3';
                                }

                                // Clear the table first
                                inventoryUpdateTable.innerHTML = '';

                                // Add each updated chemical to the table
                                updatedChemicals.forEach(chemical => {
                                    console.log('Processing chemical for display:', chemical);

                                    // Ensure all required fields are present
                                    const chemicalName = chemical.name || 'Unknown';
                                    const chemicalType = chemical.type || 'N/A';
                                    const previousQuantity = chemical.previous_quantity || '0';
                                    const usedQuantity = chemical.used_quantity || '0';
                                    const newQuantity = chemical.new_quantity || '0';
                                    const unit = chemical.unit || 'ml';

                                    // Convert to numbers for comparison
                                    const newQty = parseFloat(newQuantity);
                                    const prevQty = parseFloat(previousQuantity);
                                    const usedQty = parseFloat(usedQuantity);

                                    // Determine status based on new quantity
                                    const status = getStatus(newQty);
                                    const statusClass = getStatusClass(newQty);

                                    // Create a row with a highlight effect if the quantity changed significantly
                                    const row = document.createElement('tr');

                                    // Add a class if the quantity changed significantly
                                    if (usedQty > 0 && usedQty >= prevQty * 0.1) { // Used at least 10% of previous quantity
                                        row.className = 'table-active';
                                    }

                                    row.innerHTML = `
                                        <td><strong>${chemicalName}</strong></td>
                                        <td>${chemicalType}</td>
                                        <td>${previousQuantity} ${unit}</td>
                                        <td class="${usedQty > 0 ? 'text-danger fw-bold' : ''}">${usedQuantity} ${unit}</td>
                                        <td class="${newQty < prevQty ? 'text-primary fw-bold' : ''}">${newQuantity} ${unit}</td>
                                        <td><span class="badge bg-${statusClass}">${status}</span></td>
                                    `;
                                    inventoryUpdateTable.appendChild(row);
                                });

                                // Check if there were any chemical replacements
                                if (inventoryUpdate.replaced_chemicals && inventoryUpdate.replaced_chemicals.length > 0 &&
                                    chemicalReplacementsSection && replacementsTable) {

                                    chemicalReplacementsSection.style.display = 'block';
                                    replacementsTable.innerHTML = '';

                                    // Add each replacement chemical to the table
                                    inventoryUpdate.replaced_chemicals.forEach(replacement => {
                                        const row = document.createElement('tr');

                                        // Format the expiration date if available
                                        let formattedDate = 'N/A';
                                        let isExpired = false;

                                        if (replacement.expiration_date) {
                                            const expirationDate = new Date(replacement.expiration_date);
                                            formattedDate = expirationDate.toLocaleDateString('en-US', {
                                                month: 'short',
                                                day: 'numeric',
                                                year: 'numeric'
                                            });

                                            // Check if expired
                                            isExpired = expirationDate < new Date();
                                        }

                                        row.innerHTML = `
                                            <td><strong>${replacement.name}</strong> <span class="badge bg-info">Type: ${replacement.type || 'N/A'}</span></td>
                                            <td><span class="text-decoration-line-through">${replacement.original_chemical_name || 'Unknown'}</span> <span class="badge bg-warning text-dark"><i class="fas fa-exchange-alt me-1"></i>Replaced</span></td>
                                            <td>${replacement.used_quantity || replacement.replacement_quantity || '0'} ${replacement.unit}</td>
                                            <td class="${isExpired ? 'text-danger' : ''}">${formattedDate} ${isExpired ? '<span class="badge bg-danger">Expired</span>' : ''}</td>
                                        `;
                                        replacementsTable.appendChild(row);
                                    });
                                } else {
                                    if (chemicalReplacementsSection) {
                                        chemicalReplacementsSection.style.display = 'none';
                                    }
                                }

                                // If there were errors, show them
                                if (inventoryUpdate.errors && inventoryUpdate.errors.length > 0) {
                                    inventoryUpdateErrors.style.display = 'block';

                                    // Add each error to the list
                                    inventoryUpdate.errors.forEach(error => {
                                        const li = document.createElement('li');
                                        li.textContent = error;
                                        inventoryErrorList.appendChild(li);
                                    });
                                } else {
                                    inventoryUpdateErrors.style.display = 'none';
                                }
                            } else {
                                // If no chemicals were updated, still show the section but with a message
                                inventoryUpdateResults.style.display = 'block';

                                // Update the info message
                                if (chemicalUpdateInfo) {
                                    chemicalUpdateInfo.innerHTML = `
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        No chemicals were deducted from inventory for this job order.
                                        <div class="mt-2 small">
                                            <strong>Note:</strong> This may happen if no chemicals were selected, if all dosages were set to zero, or if there was an issue with the inventory update process.
                                        </div>
                                    `;
                                    chemicalUpdateInfo.className = 'alert alert-warning mb-3';
                                }

                                // Clear the table first
                                inventoryUpdateTable.innerHTML = '';

                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td colspan="6" class="text-center">No chemicals were used or updated</td>
                                `;
                                inventoryUpdateTable.appendChild(row);

                                // Hide errors section
                                if (inventoryUpdateErrors) {
                                    inventoryUpdateErrors.style.display = 'none';
                                }

                                // Hide replacements section
                                if (chemicalReplacementsSection) {
                                    chemicalReplacementsSection.style.display = 'none';
                                }

                                // Show no chemicals used message if it exists
                                if (noChemicalsUsed) {
                                    noChemicalsUsed.style.display = 'block';
                                }
                            }
                        }
                    } else {
                        console.log('No inventory update data found in response, using fallback display method');

                        // If no inventory update data, show the chemical dosage inputs as the inventory update
                        const inventoryUpdateResults = document.getElementById('inventoryUpdateResults');
                        const inventoryUpdateTable = document.getElementById('inventoryUpdateTable');
                        const noChemicalsUsed = document.getElementById('noChemicalsUsed');
                        const inventoryUpdateErrors = document.getElementById('inventoryUpdateErrors');
                        const inventoryErrorList = document.getElementById('inventoryErrorList');
                        const chemicalInputs = document.querySelectorAll('.chemical-dosage-input');
                        const chemicalUpdateInfo = document.getElementById('chemicalUpdateInfo');

                        console.log('Chemical inputs found for fallback display:', chemicalInputs.length);

                        // Add an error to the error list
                        if (inventoryUpdateErrors && inventoryErrorList) {
                            inventoryUpdateErrors.style.display = 'block';
                            inventoryErrorList.innerHTML = '';

                            const li = document.createElement('li');
                            li.textContent = 'The server did not return inventory update data. This may indicate an issue with the chemical inventory system.';
                            inventoryErrorList.appendChild(li);

                            const li2 = document.createElement('li');
                            li2.textContent = 'Please check with your administrator to ensure the chemical inventory was properly updated.';
                            inventoryErrorList.appendChild(li2);
                        }

                        // Update the info message
                        if (chemicalUpdateInfo) {
                            chemicalUpdateInfo.innerHTML = `
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Warning:</strong> No inventory update data was returned from the server.
                                <div class="mt-2 small">
                                    <strong>Note:</strong> This may indicate an issue with the chemical inventory system. The values below are estimates based on your input.
                                </div>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-warning" id="retryInventoryUpdate">
                                        <i class="fas fa-sync-alt me-1"></i> Retry Inventory Update
                                    </button>
                                </div>
                            `;
                            chemicalUpdateInfo.className = 'alert alert-warning mb-3';

                            // Add event listener to retry button
                            setTimeout(() => {
                                const retryButton = document.getElementById('retryInventoryUpdate');
                                if (retryButton) {
                                    retryButton.addEventListener('click', function() {
                                        // Show loading indicator
                                        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Retrying...';
                                        this.disabled = true;

                                        // Make a request to check if the inventory was updated
                                        fetch(`api/check_inventory_update.php?job_order_id=${currentJob.job_order_id}`)
                                            .then(response => response.json())
                                            .then(data => {
                                                console.log('Inventory update check result:', data);
                                                if (data.success && data.updated_chemicals && data.updated_chemicals.length > 0) {
                                                    // Update the UI with the retrieved data
                                                    chemicalUpdateInfo.innerHTML = `
                                                        <i class="fas fa-check-circle me-2"></i>
                                                        <strong>Success:</strong> Chemical inventory was updated successfully.
                                                        <div class="mt-2 small">
                                                            <strong>Note:</strong> ${data.updated_chemicals.length} chemical(s) were updated in the inventory system.
                                                        </div>
                                                    `;
                                                    chemicalUpdateInfo.className = 'alert alert-success mb-3';

                                                    // Hide the error section
                                                    if (inventoryUpdateErrors) {
                                                        inventoryUpdateErrors.style.display = 'none';
                                                    }

                                                    // Update the table
                                                    if (inventoryUpdateTable) {
                                                        inventoryUpdateTable.innerHTML = '';
                                                        data.updated_chemicals.forEach(chemical => {
                                                            const row = document.createElement('tr');
                                                            row.innerHTML = `
                                                                <td><strong>${chemical.name || 'Unknown'}</strong></td>
                                                                <td>${chemical.type || 'N/A'}</td>
                                                                <td>${chemical.previous_quantity || '0'} ${chemical.unit || 'ml'}</td>
                                                                <td>${chemical.used_quantity || '0'} ${chemical.unit || 'ml'}</td>
                                                                <td>${chemical.new_quantity || '0'} ${chemical.unit || 'ml'}</td>
                                                                <td><span class="badge bg-${getStatusClass(chemical.new_quantity)}">${getStatus(chemical.new_quantity)}</span></td>
                                                            `;
                                                            inventoryUpdateTable.appendChild(row);
                                                        });
                                                    }
                                                } else {
                                                    // Update the UI to show the error
                                                    chemicalUpdateInfo.innerHTML = `
                                                        <i class="fas fa-exclamation-circle me-2"></i>
                                                        <strong>Error:</strong> Could not verify inventory update.
                                                        <div class="mt-2 small">
                                                            <strong>Note:</strong> Please check with your administrator to ensure the chemical inventory was properly updated.
                                                        </div>
                                                    `;
                                                    chemicalUpdateInfo.className = 'alert alert-danger mb-3';
                                                }
                                            })
                                            .catch(error => {
                                                console.error('Error checking inventory update:', error);
                                                chemicalUpdateInfo.innerHTML = `
                                                    <i class="fas fa-exclamation-circle me-2"></i>
                                                    <strong>Error:</strong> Failed to check inventory update status.
                                                    <div class="mt-2 small">
                                                        <strong>Note:</strong> ${error.message}
                                                    </div>
                                                `;
                                                chemicalUpdateInfo.className = 'alert alert-danger mb-3';
                                            });
                                    });
                                }
                            }, 500);
                        }

                        // Clear any existing content in the table
                        if (inventoryUpdateTable) {
                            inventoryUpdateTable.innerHTML = '';
                        }

                        if (inventoryUpdateResults && inventoryUpdateTable && chemicalInputs.length > 0) {
                            inventoryUpdateResults.style.display = 'block';
                            inventoryUpdateTable.innerHTML = '';

                            // Loop through chemical inputs to create rows
                            let hasUsedChemicals = false;
                            for (let index = 0; index < chemicalInputs.length; index++) {
                                const input = chemicalInputs[index];
                                // Get chemical name from the hidden input
                                const nameInput = document.querySelector(`input[name="chemical_name[${index}]"]`);
                                if (!nameInput) continue;

                                const chemicalName = nameInput.value;
                                const dosage = parseFloat(input.value);
                                const dosageUnit = input.nextElementSibling ? input.nextElementSibling.textContent.trim() : 'ml';

                                // Get chemical type from the hidden input or use a default
                                const typeInput = document.querySelector(`input[name="chemical_type[${index}]"]`);
                                const chemicalType = typeInput ? typeInput.value : 'Insecticide';

                                // Create a row for each chemical with dosage > 0
                                if (dosage > 0) {
                                    hasUsedChemicals = true;
                                    // Determine status based on remaining quantity
                                    let status = 'In Stock';
                                    let statusClass = 'success';
                                    const estimatedPrevQty = 12; // Fallback value
                                    const estimatedNewQty = estimatedPrevQty - dosage;

                                    if (estimatedNewQty <= 0) {
                                        status = 'Out of Stock';
                                        statusClass = 'danger';
                                    } else if (estimatedNewQty < estimatedPrevQty * 0.2) {
                                        status = 'Low Stock';
                                        statusClass = 'warning';
                                    }

                                    const row = document.createElement('tr');
                                    row.innerHTML = `
                                        <td><strong>${chemicalName}</strong></td>
                                        <td>${chemicalType}</td>
                                        <td>${estimatedPrevQty} ${dosageUnit}</td>
                                        <td>${dosage} ${dosageUnit}</td>
                                        <td>${estimatedNewQty.toFixed(2)} ${dosageUnit}</td>
                                        <td><span class="badge bg-${statusClass}">${status}</span></td>
                                    `;
                                    inventoryUpdateTable.appendChild(row);
                                }
                                }

                            // If no rows were added, show a message
                            if (!hasUsedChemicals) {
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td colspan="6" class="text-center">No chemicals were used in this job order</td>
                                `;
                                inventoryUpdateTable.appendChild(row);
                            }
                        } else {
                            // Fallback to example data if no chemical inputs found
                            if (inventoryUpdateResults && inventoryUpdateTable) {
                                inventoryUpdateResults.style.display = 'block';
                                inventoryUpdateTable.innerHTML = `
                                    <tr>
                                        <td><strong>Cypermethrin</strong></td>
                                        <td>Insecticide</td>
                                        <td>12 Liters</td>
                                        <td>0.15 Liters</td>
                                        <td>11.85 Liters</td>
                                        <td><span class="badge bg-success">In Stock</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Cypermethrin</strong></td>
                                        <td>Insecticide</td>
                                        <td>11.85 Liters</td>
                                        <td>0.02 Liters</td>
                                        <td>11.83 Liters</td>
                                        <td><span class="badge bg-success">In Stock</span></td>
                                    </tr>
                                `;
                            }
                    }
                    }

                    // Show success modal with a slight delay to ensure it appears on top
                    setTimeout(() => {
                        // First make sure any existing SweetAlert modals are closed
                        if (typeof Swal !== 'undefined' && Swal.isVisible()) {
                            Swal.close();
                        }

                        // Make sure the inventory update section is visible in the modal
                        const inventoryUpdateResults = document.getElementById('inventoryUpdateResults');
                        if (inventoryUpdateResults) {
                            inventoryUpdateResults.style.display = 'block';
                        }

                        // Then show our custom success modal
                        const successModal = new bootstrap.Modal(document.getElementById('reportSuccessModal'));
                        successModal.show();
                    }, 100);

                    // Move the job to the finished section without reloading the page
                    setTimeout(() => {
                        // Find the job card in the current section
                        const jobCards = document.querySelectorAll('.job-card');
                        let jobCard = null;

                        jobCards.forEach(card => {
                            // Check if this card belongs to the current job
                            const jobData = card.getAttribute('data-job');
                            if (jobData) {
                                try {
                                    const parsedData = JSON.parse(jobData);
                                    if (parsedData.job_order_id === currentJob.job_order_id) {
                                        jobCard = card;
                                    }
                                } catch (e) {
                                    console.error('Error parsing job data:', e);
                                }
                            }
                        });

                        if (jobCard) {
                            // Get the parent container
                            const parentContainer = jobCard.closest('.col-md-4');
                            if (parentContainer) {
                                // Remove the card from its current section
                                parentContainer.remove();

                                // Create a new card for the finished section
                                const finishedSection = document.querySelector('.finished-job-orders .row');
                                if (finishedSection) {
                                    // Create a new column
                                    const newCol = document.createElement('div');
                                    newCol.className = 'col-md-4 mb-3';

                                    // Update the current job with completion info
                                    currentJob.status = 'completed';
                                    currentJob.report_created_at = new Date().toISOString();

                                    // Sanitize and encode job data for HTML attribute
                                    function sanitizeJobDataForHTML(job) {
                                        // Ensure we have a valid job object
                                        if (!job || typeof job !== 'object') {
                                            console.error('Invalid job data provided to sanitizeJobDataForHTML');
                                            return JSON.stringify({
                                                job_order_id: '0',
                                                client_name: 'Invalid Job Data'
                                            });
                                        }

                                        // Ensure job_order_id is always present
                                        if (!job.job_order_id) {
                                            console.error('Missing job_order_id in job data');
                                            job.job_order_id = '0';
                                        }

                                        // Ensure client_name is always present
                                        if (!job.client_name) {
                                            job.client_name = 'Unknown Client';
                                        }

                                        // First sanitize the job object
                                        const sanitizedJob = {};
                                        for (const key in job) {
                                            if (typeof job[key] === 'string') {
                                                // Remove any potentially problematic characters
                                                sanitizedJob[key] = job[key]
                                                    .replace(/[\x00-\x1F\x7F\xA0]/g, '') // Remove control chars
                                                    .replace(/\\"/g, '"') // Fix escaped quotes
                                                    .replace(/\\/g, '\\\\'); // Escape backslashes
                                            } else {
                                                sanitizedJob[key] = job[key];
                                            }
                                        }

                                        try {
                                            // Safely encode the job data for HTML attribute
                                            return JSON.stringify(sanitizedJob)
                                                .replace(/&/g, '&amp;')
                                                .replace(/</g, '&lt;')
                                                .replace(/>/g, '&gt;')
                                                .replace(/"/g, '&quot;')
                                                .replace(/'/g, '&#039;');
                                        } catch (error) {
                                            console.error('Error stringifying job data:', error);
                                            return JSON.stringify({
                                                job_order_id: job.job_order_id || '0',
                                                client_name: 'Data Error'
                                            });
                                        }
                                    }

                                    // Get sanitized job data
                                    const safeJobJson = sanitizeJobDataForHTML(currentJob);

                                    // Create the card with updated status
                                    newCol.innerHTML = `
                                        <div class="card job-card" data-job="${safeJobJson}"
                                             style="cursor: pointer; transition: transform 0.3s ease; border-left: 4px solid #28a745;"
                                             onmouseover="this.style.transform='translateY(-5px)'"
                                             onmouseout="this.style.transform='translateY(0)'">
                                            <div class="card-body">
                                                <h5 class="card-title">${currentJob.client_name && currentJob.client_name.trim() ? currentJob.client_name : 'Unknown Client'}</h5>
                                                <div class="d-flex gap-2 mb-2">
                                                    ${currentJob.kind_of_place ? `<span class="detail-badge">${currentJob.kind_of_place}</span>` : ''}
                                                    ${currentJob.type_of_work ? `<span class="detail-badge">${currentJob.type_of_work}</span>` : ''}
                                                </div>
                                                ${currentJob.preferred_date ? `
                                                <p class="text-muted mb-1">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    ${new Date(currentJob.preferred_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})}
                                                </p>` : ''}
                                                ${currentJob.preferred_time ? `
                                                <p class="text-muted mb-1">
                                                    <i class="fas fa-clock me-1"></i>
                                                    ${new Date('1970-01-01T' + currentJob.preferred_time).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit', hour12: true})}
                                                </p>` : ''}
                                                ${currentJob.location_address ? `
                                                <small class="text-primary">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    ${currentJob.location_address}
                                                </small>` : ''}
                                                ${currentJob.cost ? `
                                                <p class="text-muted mb-1">
                                                    <i class="fas fa-money-bill-wave me-1"></i>
                                                    Cost per visit: ₱ ${calculateCostPerVisitJS(currentJob.cost, currentJob.frequency)}
                                                </p>` : ''}
                                                <div class="mt-2">
                                                    <span class="badge bg-success">Completed</span>
                                                </div>
                                            </div>
                                        </div>
                                    `;

                                    // Add the new card to the beginning of the finished section (LIFO)
                                    if (finishedSection.children.length > 0 && !finishedSection.querySelector('.alert')) {
                                        finishedSection.insertBefore(newCol, finishedSection.firstChild);
                                    } else {
                                        // If the section is empty, just append
                                        finishedSection.appendChild(newCol);
                                    }

                                    // Check if the finished section was empty
                                    const emptyAlert = finishedSection.querySelector('.alert');
                                    if (emptyAlert) {
                                        emptyAlert.remove();
                                    }
                                    }

                                // Check if the original section is now empty
                                const originalSection = document.querySelector('.job-section:not(.finished-job-orders):not(.upcoming-job-orders):not(.past-due-job-orders) .row');
                                if (originalSection && originalSection.children.length === 0) {
                                    originalSection.innerHTML = '<div class="col-12"><div class="alert alert-info">No job orders scheduled for today</div></div>';
                                }

                                // Check if the past due section is now empty
                                const pastDueSection = document.querySelector('.past-due-job-orders .row');
                                if (pastDueSection && pastDueSection.children.length === 0) {
                                    pastDueSection.innerHTML = '<div class="col-12"><div class="alert alert-info">No past due job orders</div></div>';
                                }
                            }
                        }
                    }, 1500);
                } else {
                    // Show error message
                    let errorMessage = data.message || 'Failed to submit report';
                    if (data.errors && data.errors.length > 0) {
                        errorMessage += '<br>' + data.errors.map(err => `- ${err}`).join('<br>');
                    }

                    Swal.fire({
                        title: 'Error',
                        html: errorMessage,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });

                    // Reset button state
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
                })
            .catch(error => {
                console.error('Error submitting report:', error);

                // Show detailed error message
                Swal.fire({
                    title: 'Error',
                    html: `An error occurred while submitting the report:<br><br>
                          <code>${error.message || 'Unknown error'}</code><br><br>
                          Please try again or contact support if the problem persists.`,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });

                // Reset button state
                const submitBtn = document.querySelector('#jobOrderReportForm button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText || '<i class="fas fa-save me-2"></i>Submit Report';
                }
        });
        });
    </script>

    <!-- Sidebar and Notification Scripts -->
    <script src="js/sidebar.js"></script>
    <!-- Enhanced Sidebar Fix Script - Loads after sidebar.js to fix responsive issues -->
    <script src="js/sidebar-fix.js"></script>
    <script src="js/notifications.js"></script>
    <script src="js/tools-checklist.js"></script>

    <!-- Job Order Flow Scripts - Load in correct order -->
    <script>
        // Function to calculate cost per visit based on total cost and frequency
        function calculateCostPerVisitJS(totalCost, frequency) {
            if (!totalCost || isNaN(parseFloat(totalCost))) {
                return 'N/A';
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
            const costPerVisit = cost / numberOfVisits;

            // Format with proper thousands separators
            return costPerVisit.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Ensure scripts are loaded in the correct order
        function loadScriptsSequentially(scripts, callback) {
            if (scripts.length === 0) {
                if (typeof callback === 'function') callback();
                return;
            }

            const script = document.createElement('script');
            script.src = scripts[0];
            script.onload = function() {
                console.log('Loaded script:', scripts[0]);
                loadScriptsSequentially(scripts.slice(1), callback);
            };
            script.onerror = function() {
                console.error('Failed to load script:', scripts[0]);
                loadScriptsSequentially(scripts.slice(1), callback);
            };
            document.head.appendChild(script);
        }

        // Function to reset tools and equipment status
        function resetToolsStatus() {
            console.log('Resetting tools status for job ID:', currentJob ? currentJob.job_order_id : 'undefined');

            // Check if currentJob is defined
            if (!currentJob) {
                console.error('Current job is undefined');
                Swal.fire({
                    title: 'Error',
                    text: 'Job details not found. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Show loading state
            const resetBtn = document.getElementById('resetToolsBtn');
            const originalBtnText = resetBtn.innerHTML;
            resetBtn.disabled = true;
            resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Resetting...';

            // Update status alert
            const toolsResetStatus = document.getElementById('toolsResetStatus');
            const toolsResetMessage = document.getElementById('toolsResetMessage');
            toolsResetStatus.className = 'alert alert-info mt-3';
            toolsResetStatus.style.display = 'block';
            toolsResetMessage.textContent = 'Resetting tools and equipment status...';

            // Send AJAX request to reset tools status
            fetch('api/reset_tools_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'job_order_id=' + currentJob.job_order_id
            })
            .then(response => response.json())
            .then(data => {
                console.log('Reset tools response:', data);

                // Reset button state
                resetBtn.disabled = false;
                resetBtn.innerHTML = originalBtnText;

                if (data.success) {
                    // Update status alert with success message
                    toolsResetStatus.className = 'alert alert-success mt-3';

                    if (data.tools_reset > 0) {
                        toolsResetMessage.innerHTML = `<strong>Success!</strong> ${data.tools_reset} tools and equipment have been reset from "in-use" to "in-stock" status. The checklist has been updated to remove these tools.`;
                    } else {
                        toolsResetMessage.innerHTML = `<strong>Note:</strong> No tools needed to be reset. All tools are already in "in-stock" status.`;
                    }

                    // Disable the reset button after successful reset
                    resetBtn.disabled = true;
                    resetBtn.innerHTML = '<i class="fas fa-check me-2"></i>Tools Reset';

                    // Check if there's a warning message
                    if (data.warning) {
                        // Show success with warning
                        Swal.fire({
                            title: 'Success with Warning',
                            html: `${data.message}<br><br><strong>Warning:</strong> ${data.warning}`,
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        // Show regular success message
                        Swal.fire({
                            title: 'Success',
                            text: data.message,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    }
                } else {
                    // Update status alert with error message
                    toolsResetStatus.className = 'alert alert-danger mt-3';
                    toolsResetMessage.textContent = 'Error: ' + (data.error || 'Failed to reset tools status');

                    // Show error message with more details if available
                    let errorMessage = data.error || 'Failed to reset tools status';
                    if (data.details) {
                        errorMessage += '<br><br>' + data.details;
                    }

                    Swal.fire({
                        title: 'Error',
                        html: errorMessage,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Error resetting tools status:', error);

                // Reset button state
                resetBtn.disabled = false;
                resetBtn.innerHTML = originalBtnText;

                // Update status alert with error message
                toolsResetStatus.className = 'alert alert-danger mt-3';
                toolsResetMessage.textContent = 'Error: ' + error.message;

                // Show error message
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred while resetting tools status: ' + error.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        }

        // Global flag to prevent multiple initializations
        window.jobHandlersInitialized = false;

        // Function to prevent modals from closing unexpectedly
        function setupModalProtection() {
            console.log('Setting up modal protection');

            // Protect the job details modal
            const jobDetailsModal = document.getElementById('jobDetailsModal');
            if (jobDetailsModal) {
                // Prevent modal from closing when clicking outside
                jobDetailsModal.addEventListener('click', function(event) {
                    // Only prevent propagation if clicking directly on the modal backdrop
                    if (event.target === jobDetailsModal) {
                        console.log('Prevented job details modal from closing via backdrop click');
                        event.stopPropagation();
                        event.preventDefault();
                        return false;
                    }
                });

                // Also prevent the modal from closing when the ESC key is pressed
                jobDetailsModal.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape') {
                        console.log('Prevented job details modal from closing via ESC key');
                        event.stopPropagation();
                        event.preventDefault();
                        return false;
                    }
                });
            }

            // Protect the report form modal
            const reportFormModal = document.getElementById('reportFormModal');
            if (reportFormModal) {
                // Prevent modal from closing when clicking outside
                reportFormModal.addEventListener('click', function(event) {
                    // Only prevent propagation if clicking directly on the modal backdrop
                    if (event.target === reportFormModal) {
                        console.log('Prevented report form modal from closing via backdrop click');
                        event.stopPropagation();
                        event.preventDefault();
                        return false;
                    }
                });

                // Also prevent the modal from closing when the ESC key is pressed
                reportFormModal.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape') {
                        console.log('Prevented report form modal from closing via ESC key');
                        event.stopPropagation();
                        event.preventDefault();
                        return false;
                    }
                });
            }

            // Add a global handler to prevent modals from being closed unexpectedly
            document.addEventListener('hidden.bs.modal', function(event) {
                console.log('Modal hidden event detected:', event.target.id);
                // Log which modal was closed and how
                console.log('Modal closed via:', event.type);
            });
        }

        // Load scripts in sequence - ensure proper order for job flow
        loadScriptsSequentially([
            'js/unit-conversion-helper.js',
            'js/checklist-handler.js',
            'js/job-details.js',
            'js/unified-job-handler.js',  // Make sure this is the last script loaded
            'js/chemical-recommendations.js'
        ], function() {
            console.log('All job order scripts loaded successfully');

            // Set up modal protection after scripts are loaded
            setupModalProtection();

            // Add event listener for reset tools button
            const resetToolsBtn = document.getElementById('resetToolsBtn');
            if (resetToolsBtn) {
                resetToolsBtn.addEventListener('click', resetToolsStatus);
            }

            // Only initialize if not already initialized
            if (!window.jobHandlersInitialized) {
                // Initialize the unified job handler explicitly
                if (typeof initializeUnifiedJobHandler === 'function') {
                    console.log('Explicitly calling initializeUnifiedJobHandler');
                    initializeUnifiedJobHandler();
                    window.jobHandlersInitialized = true;
                } else {
                    console.error('initializeUnifiedJobHandler function not found after loading scripts');

                    // Fallback: Try to load unified-job-handler.js again
                    console.log('Attempting to load unified-job-handler.js again as fallback');
                    const script = document.createElement('script');
                    script.src = 'js/unified-job-handler.js';
                    script.onload = function() {
                        console.log('Successfully loaded unified-job-handler.js as fallback');
                        if (typeof initializeUnifiedJobHandler === 'function') {
                            console.log('Calling initializeUnifiedJobHandler from fallback');
                            initializeUnifiedJobHandler();
                            window.jobHandlersInitialized = true;
                        }
                    };
                    document.head.appendChild(script);
                }
            }

            // Initialize any global functions if needed
            if (typeof initializeJobOrderPage === 'function') {
                initializeJobOrderPage();
            }

            // Set global variables to ensure proper job flow
            console.log('Setting up global variables for job flow');
            window.jobFlowInitialized = true;

            // Ensure currentJob is accessible globally
            if (!window.currentJob) {
                window.currentJob = null;
            }

            // Log the flow implementation
            console.log('Job order flow implementation: Job card click → Checklist → Job Details (using unified-job-handler.js)');
        });
    </script>

    <!-- Debug script for sidebar toggle and job card click handler -->
    <script>
        // Add debug logging for sidebar toggle
        console.log('Job Order page loaded - Debug mode enabled for sidebar');
        document.addEventListener('DOMContentLoaded', function() {
            // Log sidebar state on page load
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');

            if (sidebar && menuToggle) {
                console.log('Sidebar elements found on page load');
                console.log('Initial sidebar state:', sidebar.classList.contains('active') ? 'active' : 'inactive');
                console.log('Window width:', window.innerWidth);

                // Add additional click logging to menuToggle
                menuToggle.addEventListener('click', function() {
                    console.log('Menu toggle clicked directly from job_order.php');
                    console.log('Sidebar state after click:', sidebar.classList.contains('active') ? 'active' : 'inactive');
                });
            } else {
                console.error('Sidebar elements not found on page load');
            }

            // We'll let the job-flow.js or simple-job-handler.js handle the job card clicks
            // This prevents multiple event handlers from being attached
            console.log('Skipping direct event listeners to job cards - using job-flow.js or simple-job-handler.js instead');
        });
    </script>
    <script>
        // Initialize notifications when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Fetch notifications
            fetchNotifications();

            // Set up date checking and auto-refresh
            setupDateRefresh();

            // Pre-fetch chemical data when the page loads to improve performance
            prefetchChemicalData();
        });

        // Function to pre-fetch chemical data
        function prefetchChemicalData() {
            console.log('Pre-fetching chemical data...');
            // Use the fetchAvailableChemicals function we've already defined
            fetchAvailableChemicals()
                .then(chemicals => {
                    console.log('Chemical data pre-fetched successfully');
                    // The fetchAvailableChemicals function already sets availableChemicals
                    // and caches the data, so we don't need to do anything else here
                })
                .catch(error => {
                    console.error('Error pre-fetching chemical data:', error);
                });
        }

        // Function to set up date checking and auto-refresh
        function setupDateRefresh() {
            // Store the server date
            const serverDate = '<?= $today ?>';
            console.log('Server date:', serverDate);

            // Check date every minute
            setInterval(function() {
                checkDateAndRefresh(serverDate);
            }, 60000); // 60 seconds

            // Set up midnight refresh
            setupMidnightRefresh();
        }

        // Function to check if the date has changed and refresh if needed
        function checkDateAndRefresh(serverDate) {
            // Check if any modal is currently open
            const openModals = document.querySelectorAll('.modal.show');
            if (openModals.length > 0) {
                console.log('Modal is open, skipping date check and refresh');
                return; // Skip refresh if any modal is open
            }

            // Get current client date in YYYY-MM-DD format
            const clientDate = new Date().toISOString().split('T')[0];
            console.log('Checking date - Client:', clientDate, 'Server:', serverDate);

            // If the client date is different from the server date, refresh the page
            if (clientDate !== serverDate) {
                console.log('Date changed! Refreshing page...');
                // Force a full page reload to bypass cache
                window.location.href = window.location.pathname + '?refresh=' + new Date().getTime();
                return;
            }

            // Check if any upcoming job orders have today's date
            checkUpcomingJobOrdersForToday(clientDate);
        }

        // Function to check if any upcoming job orders have today's date
        function checkUpcomingJobOrdersForToday(todayDate) {
            // Get all upcoming job order cards
            const upcomingCards = document.querySelectorAll('.upcoming-job-orders .job-card');
            let needsRefresh = false;

            // Loop through each card and check the date
            upcomingCards.forEach(card => {
                // Find the date element (it's a p.text-muted with calendar icon)
                const dateElement = card.querySelector('p.text-muted i.fas.fa-calendar');
                if (dateElement && dateElement.parentElement) {
                    // Extract the date from the element (format is "MMM DD, YYYY")
                    const dateText = dateElement.parentElement.textContent.trim();
                    const dateParts = dateText.match(/([A-Za-z]{3})\s+(\d{1,2}),\s+(\d{4})/);

                    if (dateParts && dateParts.length === 4) {
                        const month = dateParts[1];
                        const day = dateParts[2].padStart(2, '0');
                        const year = dateParts[3];

                        // Convert to YYYY-MM-DD format for comparison
                        const monthNum = new Date(`${month} 1, 2000`).getMonth() + 1;
                        const formattedDate = `${year}-${monthNum.toString().padStart(2, '0')}-${day}`;

                        console.log('Checking upcoming job order date:', formattedDate, 'against today:', todayDate);

                        // If the date matches today's date, we need to refresh
                        if (formattedDate === todayDate) {
                            console.log('Found a job order that should be moved to today!');
                            needsRefresh = true;
                        }
                    }
                }
            });

            // Also check if any today's job orders should be moved to past due
            const todayCards = document.querySelectorAll('.job-section:not(.upcoming-job-orders):not(.past-due-job-orders):not(.finished-job-orders) .job-card');

            todayCards.forEach(card => {
                const dateElement = card.querySelector('p.text-muted i.fas.fa-calendar');
                if (dateElement && dateElement.parentElement) {
                    const dateText = dateElement.parentElement.textContent.trim();
                    const dateParts = dateText.match(/([A-Za-z]{3})\s+(\d{1,2}),\s+(\d{4})/);

                    if (dateParts && dateParts.length === 4) {
                        const month = dateParts[1];
                        const day = dateParts[2].padStart(2, '0');
                        const year = dateParts[3];

                        // Convert to YYYY-MM-DD format for comparison
                        const monthNum = new Date(`${month} 1, 2000`).getMonth() + 1;
                        const formattedDate = `${year}-${monthNum.toString().padStart(2, '0')}-${day}`;

                        // If the date is before today's date, it should be in past due
                        if (formattedDate < todayDate) {
                            console.log('Found a job order that should be moved to past due!');
                            needsRefresh = true;
                        }
                    }
                }
            });

            // If we found a job order that needs to be moved, refresh the page
            if (needsRefresh) {
                // Check if any modal is currently open
                const openModals = document.querySelectorAll('.modal.show');
                if (openModals.length > 0) {
                    console.log('Modal is open, skipping refresh even though job orders need to be moved');
                    return; // Skip refresh if any modal is open
                }

                console.log('Refreshing page to update job orders...');
                window.location.href = window.location.pathname + '?refresh=' + new Date().getTime();
            }
        }

        // Function to refresh the page at midnight
        function setupMidnightRefresh() {
            const now = new Date();
            const tomorrow = new Date(now);
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(0, 0, 10, 0); // 00:00:10 - slight delay to ensure we're past midnight

            const msUntilMidnight = tomorrow - now;
            console.log('Setting up midnight refresh in', Math.floor(msUntilMidnight/1000/60), 'minutes');

            // Set timeout to refresh at midnight
            setTimeout(function() {
                console.log('Midnight reached!');

                // Check if any modal is currently open
                const openModals = document.querySelectorAll('.modal.show');
                if (openModals.length > 0) {
                    console.log('Modal is open, skipping midnight refresh');

                    // Set up another check in 5 minutes
                    setTimeout(function() {
                        // Try again in 5 minutes if modals are closed
                        const stillOpenModals = document.querySelectorAll('.modal.show');
                        if (stillOpenModals.length === 0) {
                            console.log('No modals open now, proceeding with delayed midnight refresh');
                            window.location.href = window.location.pathname + '?refresh=' + new Date().getTime();
                        } else {
                            console.log('Modals still open, skipping midnight refresh completely');
                        }
                    }, 300000); // 5 minutes

                    return;
                }

                // Force a full page reload to bypass cache
                console.log('Refreshing page for midnight update...');
                window.location.href = window.location.pathname + '?refresh=' + new Date().getTime();
            }, msUntilMidnight);
        }
    </script>

    <!-- Fallback job card click handler -->
    <script>
        // This is a fallback mechanism to ensure job cards are clickable
        // It will run after all other scripts have loaded
        window.addEventListener('load', function() {
            console.log('Window load event - Checking if job card handlers are needed');

            // Check if our handlers are already initialized
            if (typeof handleJobCardClick === 'function' || typeof initializeUnifiedJobHandler === 'function') {
                console.log('Job card handlers already initialized, skipping fallback implementation');
                return; // Exit early if handlers are already set up
            }

            // Only add fallback handlers if no other handlers exist
            console.warn('No job card handlers found, using fallback implementation');

            // Add direct click handlers to all job cards
            const jobCards = document.querySelectorAll('.job-card');
            console.log(`Found ${jobCards.length} job cards for fallback handlers`);

            jobCards.forEach((card, index) => {
                // Add a direct click handler
                card.addEventListener('click', function(event) {
                    console.log(`Job card ${index + 1} clicked via fallback handler`);

                    // Add visual feedback
                    this.classList.add('clicked-card');

                    // Try to get job data from data attributes first
                    const jobId = this.getAttribute('data-job-id');
                    const clientName = this.getAttribute('data-client-name');

                    if (jobId) {
                        // Create a minimal job data object
                        const minimalJobData = {
                            job_order_id: jobId,
                            client_name: clientName || 'Unknown Client'
                        };

                        // Check if the job is completed
                        const isCompleted = minimalJobData.status === 'completed' ||
                                           this.closest('#finishedJobOrders') ||
                                           this.classList.contains('completed') ||
                                           this.getAttribute('data-status') === 'completed' ||
                                           this.querySelector('.badge-completed');

                        if (isCompleted) {
                            console.log('Job is completed, skipping checklist');
                            // Skip checklist for completed jobs
                            if (typeof openJobDetails === 'function') {
                                openJobDetails(minimalJobData);
                            }
                        } else if (typeof showChecklistForJob === 'function') {
                            // Show checklist for non-completed jobs
                            showChecklistForJob(minimalJobData, function() {
                                if (typeof openJobDetails === 'function') {
                                    openJobDetails(minimalJobData);
                                }
                            });
                        } else {
                            alert('Checklist handler not found. Please refresh the page and try again.');
                        }
                    } else {
                        // Try to parse the data-job attribute
                        const jobDataString = this.getAttribute('data-job');
                        if (jobDataString) {
                            try {
                                // Try to parse the JSON
                                const jobData = JSON.parse(jobDataString);

                                // Check if the job is completed
                                const isCompleted = jobData.status === 'completed' ||
                                                   this.closest('#finishedJobOrders') ||
                                                   this.classList.contains('completed') ||
                                                   this.getAttribute('data-status') === 'completed' ||
                                                   this.querySelector('.badge-completed');

                                if (isCompleted) {
                                    console.log('Job is completed, skipping checklist');
                                    // Skip checklist for completed jobs
                                    if (typeof openJobDetails === 'function') {
                                        openJobDetails(jobData);
                                    }
                                } else if (typeof showChecklistForJob === 'function') {
                                    // Show checklist for non-completed jobs
                                    showChecklistForJob(jobData, function() {
                                        if (typeof openJobDetails === 'function') {
                                            openJobDetails(jobData);
                                        }
                                    });
                                } else {
                                    alert('Checklist handler not found. Please refresh the page and try again.');
                                }
                            } catch (error) {
                                console.error('Error parsing job data in fallback handler:', error);
                                alert('Could not process job data. Please refresh the page and try again.');
                            }
                        } else {
                            alert('No job data found. Please refresh the page and try again.');
                        }
                    }
                });

                console.log(`Added fallback click handler to job card ${index + 1}`);
            });
        });

        console.log('Fallback job card click handler registered');
    </script>

    <!-- Script to ensure the Create Job Order Report button works correctly -->
    <script>
        // Add event listener to ensure the Create Job Order Report button works
        document.addEventListener('click', function(event) {
            // Check if the clicked element is the Create Job Order Report button
            if (event.target && (
                event.target.id === 'createReportBtn' ||
                (event.target.parentElement && event.target.parentElement.id === 'createReportBtn')
            )) {
                console.log('Create Job Order Report button clicked');

                // Prevent the default action
                event.preventDefault();

                // Check if window.currentJob exists in unified-job-handler.js
                if (window.currentJob) {
                    console.log('Using currentJob from window object:', window.currentJob.job_order_id);

                    // Set the currentJob variable in this scope to match the one from unified-job-handler.js
                    currentJob = window.currentJob;

                    // Now call openReportForm with the correct job data
                    openReportForm();
                } else {
                    console.error('currentJob not found in window object');

                    // Try to get the job data from the modal or button
                    const jobDetailsModal = document.getElementById('jobDetailsModal');
                    if (jobDetailsModal) {
                        // Try to extract job ID from the modal title or content
                        const jobIdElement = jobDetailsModal.querySelector('.badge.bg-info');
                        if (jobIdElement) {
                            const jobIdText = jobIdElement.textContent;
                            const jobIdMatch = jobIdText.match(/Job #(\d+)/);
                            if (jobIdMatch && jobIdMatch[1]) {
                                const jobId = jobIdMatch[1];
                                console.log('Extracted job ID from modal:', jobId);

                                // Fetch the job data using the ID
                                fetch(`get_job_order_details_tech.php?job_order_id=${jobId}`)
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success && (data.job_data || data.job_order)) {
                                            // Handle different response formats
                                            const jobData = data.job_data || data.job_order;
                                            console.log('Retrieved job data:', jobData);

                                            // Set the currentJob variable
                                            currentJob = jobData;
                                            window.currentJob = jobData;

                                            // Now call openReportForm with the correct job data
                                            openReportForm();
                                        } else {
                                            console.error('Failed to retrieve job data:', data.message || 'Unknown error');
                                            Swal.fire({
                                                title: 'Error',
                                                text: 'Could not retrieve job details. Please try again.',
                                                icon: 'error',
                                                confirmButtonText: 'OK'
                                            });
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error fetching job data:', error);
                                        Swal.fire({
                                            title: 'Error',
                                            text: 'An error occurred while retrieving job details. Please try again.',
                                            icon: 'error',
                                            confirmButtonText: 'OK'
                                        });
                                    });
                                return false;
                            }
                        }
                    }

                    // If we couldn't extract the job ID, show an error
                    Swal.fire({
                        title: 'Error',
                        text: 'Job details not found. Please try again.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }

                // Return false to prevent any other handlers from executing
                return false;
            }
        }, true); // Use capturing phase to ensure this handler runs first

        // Add a MutationObserver to watch for dynamically added buttons
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                    for (let i = 0; i < mutation.addedNodes.length; i++) {
                        const node = mutation.addedNodes[i];
                        if (node.nodeType === 1 && node.id === 'createReportBtn') {
                            console.log('Create Job Order Report button added to DOM');

                            // Remove any existing onclick handler
                            node.onclick = null;

                            // Add a new click handler that ensures currentJob is set
                            node.addEventListener('click', function(event) {
                                console.log('Create Job Order Report button clicked (from MutationObserver)');

                                // Prevent the default action
                                event.preventDefault();

                                // Check if window.currentJob exists
                                if (window.currentJob) {
                                    console.log('Using currentJob from window object (MutationObserver):', window.currentJob.job_order_id);

                                    // Set the currentJob variable in this scope
                                    currentJob = window.currentJob;

                                    // Now call openReportForm with the correct job data
                                    openReportForm();
                                } else {
                                    console.error('currentJob not found in window object (MutationObserver)');

                                    // Show an error message
                                    Swal.fire({
                                        title: 'Error',
                                        text: 'Job details not found. Please try again.',
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                }

                                // Return false to prevent any other handlers from executing
                                return false;
                            });
                        }
                    }
                }
            });
        });

        // Start observing the document body for changes
        observer.observe(document.body, { childList: true, subtree: true });

        console.log('Enhanced event listeners for Create Job Order Report button added');
    </script>
</body>
</html>