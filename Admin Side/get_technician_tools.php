<?php
/**
 * Get technician tools and equipment for display
 */
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}
include '../db_connect.php';

// Check if technician_id is provided
if (!isset($_GET['technician_id']) || !is_numeric($_GET['technician_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid technician ID']);
    exit;
}

$technicianId = (int)$_GET['technician_id'];
$checkedTools = [];
$checkedItemIds = [];

// First try to get tools from job_order_checklists
$jobOrderChecklistQuery = "
    SELECT
        id,
        job_order_id,
        checked_tools,
        checked_items,
        total_items,
        checked_count,
        updated_at
    FROM job_order_checklists
    WHERE technician_id = ?
    ORDER BY updated_at DESC
    LIMIT 1
";

$stmt = $conn->prepare($jobOrderChecklistQuery);
$stmt->bind_param("i", $technicianId);
$stmt->execute();
$jobOrderResult = $stmt->get_result();

$checklist = null;

if ($jobOrderResult->num_rows > 0) {
    $jobOrderChecklist = $jobOrderResult->fetch_assoc();
    $checklist = [
        'checklist_date' => date('Y-m-d', strtotime($jobOrderChecklist['updated_at'])),
        'total_items' => $jobOrderChecklist['total_items'],
        'checked_count' => $jobOrderChecklist['checked_count'],
        'created_at' => $jobOrderChecklist['updated_at'],
        'job_order_id' => $jobOrderChecklist['job_order_id']
    ];

    // Parse the checked_tools JSON
    if (!empty($jobOrderChecklist['checked_tools'])) {
        try {
            $decodedTools = json_decode($jobOrderChecklist['checked_tools'], true);

            // Extract tool IDs
            if (is_array($decodedTools)) {
                foreach ($decodedTools as $tool) {
                    if (is_array($tool) && isset($tool['id']) && isset($tool['type']) && $tool['type'] === 'tool') {
                        $checkedItemIds[] = $tool['id'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error parsing job order checklist tools: " . $e->getMessage());
        }
    }
}

// If no tools found in job_order_checklists, try technician_checklist_logs as fallback
if (empty($checkedItemIds)) {
    $checklistQuery = "
        SELECT
            checklist_date,
            checked_items,
            total_items,
            checked_count,
            created_at
        FROM technician_checklist_logs
        WHERE technician_id = ?
        ORDER BY checklist_date DESC
        LIMIT 1
    ";

    $stmt = $conn->prepare($checklistQuery);
    $stmt->bind_param("i", $technicianId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $checklist = $result->fetch_assoc();

        // Parse the checked_items JSON
        if (!empty($checklist['checked_items'])) {
            try {
                $decodedItems = json_decode($checklist['checked_items'], true);

                // Handle both formats: array of IDs or array of objects with 'id' property
                if (is_array($decodedItems)) {
                    foreach ($decodedItems as $item) {
                        if (is_array($item) && isset($item['id'])) {
                            // Format: array of objects with 'id' property
                            $checkedItemIds[] = $item['id'];
                        } elseif (is_numeric($item)) {
                            // Format: array of IDs
                            $checkedItemIds[] = $item;
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Error parsing technician checklist items: " . $e->getMessage());
            }
        }
    }
}

// Helper function to get category icon
function getCategoryIcon($category) {
    $icons = [
        'General Pest Control' => 'fa-spray-can',
        'Termite' => 'fa-bug',
        'Termite Treatment' => 'fa-house-damage',
        'Weed Control' => 'fa-seedling',
        'Bed Bugs' => 'fa-bed'
    ];

    return isset($icons[$category]) ? $icons[$category] : 'fa-tools';
}

// If there are checked items, get their details
if (!empty($checkedItemIds)) {
    // Convert array to comma-separated string for SQL IN clause
    $idList = implode(',', array_map('intval', $checkedItemIds));

    // Get tool details for checked items
    $toolsQuery = "
        SELECT id, name, category, description
        FROM tools_equipment
        WHERE id IN ($idList)
        ORDER BY category, name
    ";

    $toolsResult = $conn->query($toolsQuery);

    if ($toolsResult) {
        // Group tools by category
        $toolsByCategory = [];
        while ($tool = $toolsResult->fetch_assoc()) {
            $category = $tool['category'];
            if (!isset($toolsByCategory[$category])) {
                $toolsByCategory[$category] = [];
            }
            $toolsByCategory[$category][] = $tool;
        }

        // Build HTML output
        $html = '';

        if (empty($toolsByCategory)) {
            $html .= '<div class="alert alert-info">No tools or equipment have been checked by this technician.</div>';
        } else {
            // Summary section
            $html .= '<div class="tools-summary">';
            $html .= '<div class="row">';

            // Checklist date
            $html .= '<div class="col-md-4">';
            $html .= '<div class="summary-box">';
            $html .= '<i class="fas fa-calendar-check"></i>';
            $html .= '<div class="summary-content">';
            $html .= '<h4>Checklist Date</h4>';

            $dateDisplay = 'Unknown';
            if (isset($checklist['checklist_date'])) {
                $dateDisplay = date('F j, Y', strtotime($checklist['checklist_date']));
            } else if (isset($checklist['created_at'])) {
                $dateDisplay = date('F j, Y', strtotime($checklist['created_at']));
            }

            $html .= '<p>' . $dateDisplay . '</p>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';

            // Completion rate
            $completionRate = 0;
            if (isset($checklist['total_items']) && $checklist['total_items'] > 0) {
                $completionRate = round(($checklist['checked_count'] / $checklist['total_items']) * 100);
            }

            $html .= '<div class="col-md-4">';
            $html .= '<div class="summary-box">';
            $html .= '<i class="fas fa-clipboard-check"></i>';
            $html .= '<div class="summary-content">';
            $html .= '<h4>Completion Rate</h4>';

            $completionDisplay = '0%';
            if (isset($checklist['checked_count']) && isset($checklist['total_items'])) {
                $completionDisplay = $completionRate . '% (' . $checklist['checked_count'] . '/' . $checklist['total_items'] . ')';
            }

            $html .= '<p>' . $completionDisplay . '</p>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';

            // Total checked tools
            $html .= '<div class="col-md-4">';
            $html .= '<div class="summary-box">';
            $html .= '<i class="fas fa-tools"></i>';
            $html .= '<div class="summary-content">';
            $html .= '<h4>Checked Tools</h4>';
            $html .= '<p>' . count($checkedItemIds) . '</p>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '</div>'; // End of row
            $html .= '</div>'; // End of tools-summary

            // Tools list by category
            $html .= '<div class="tools-list">';

            foreach ($toolsByCategory as $category => $tools) {
                $html .= '<div class="tools-category">';
                $html .= '<h4><i class="fas ' . getCategoryIcon($category) . '"></i> ' . htmlspecialchars($category) . '</h4>';

                $html .= '<div class="row">';
                foreach ($tools as $tool) {
                    $html .= '<div class="col-md-6">';
                    $html .= '<div class="tool-item">';
                    $html .= '<div class="tool-name"><i class="fas fa-check-circle text-success"></i> ' . htmlspecialchars($tool['name']) . '</div>';
                    if (!empty($tool['description'])) {
                        $html .= '<div class="tool-description">' . htmlspecialchars($tool['description']) . '</div>';
                    }
                    $html .= '</div>';
                    $html .= '</div>';
                }
                $html .= '</div>'; // End of row

                $html .= '</div>'; // End of tools-category
            }

            $html .= '</div>'; // End of tools-list
        }

        // Calculate percentage for response
        $percentage = 0;
        if (isset($checklist['total_items']) && $checklist['total_items'] > 0) {
            $percentage = round(($checklist['checked_count'] / $checklist['total_items']) * 100);
        }

        echo json_encode([
            'success' => true,
            'html' => $html,
            'percentage' => $percentage,
            'tools_count' => count($checkedItemIds)
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch tool details: ' . $conn->error]);
    }
} else {
    // No checked items
    $html = '<div class="alert alert-info">No tools or equipment have been checked by this technician.</div>';
    echo json_encode([
        'success' => true,
        'html' => $html,
        'percentage' => 0,
        'tools_count' => 0
    ]);
}
?>
