<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

require_once '../db_connect.php';

// Get recent activities
$activities = [];

// Check if there are any recent logins
try {
    $result = $conn->query("SELECT * FROM technician_login_logs ORDER BY login_time DESC LIMIT 5");
    if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Get technician name
        $tech_id = $row['technician_id'];
        $tech_name = 'Unknown';

        $tech_result = $conn->query("SELECT username FROM technicians WHERE technician_id = $tech_id");
        if ($tech_result && $tech_row = $tech_result->fetch_assoc()) {
            $tech_name = $tech_row['username'];
        }

        $activities[] = [
            'type' => 'login',
            'icon' => 'sign-in-alt',
            'title' => "Technician Login: $tech_name",
            'time' => $row['login_time'],
            'content' => "Technician $tech_name logged in to the system."
        ];
    }
    }

    // Get recent job orders
    $result = $conn->query("SELECT jo.*, a.client_name FROM job_order jo
                          JOIN assessment_report ar ON jo.report_id = ar.report_id
                          JOIN appointments a ON ar.appointment_id = a.appointment_id
                          ORDER BY jo.created_at DESC LIMIT 5");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $activities[] = [
                'type' => 'job_order',
                'icon' => 'tasks',
                'title' => "New Job Order: #{$row['job_order_id']}",
                'time' => $row['created_at'],
                'content' => "Job order created for {$row['client_name']} - {$row['type_of_work']}"
            ];
        }
    }

    // Get recent appointments
    $result = $conn->query("SELECT * FROM appointments ORDER BY created_at DESC LIMIT 5");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $activities[] = [
                'type' => 'appointment',
                'icon' => 'calendar-check',
                'title' => "New Appointment: #{$row['appointment_id']}",
                'time' => $row['created_at'],
                'content' => "Appointment scheduled for {$row['client_name']} on " . date('M d, Y', strtotime($row['preferred_date']))
            ];
        }
    }
} catch (Exception $e) {
    // Log the error
    error_log("Error in get_activities.php: " . $e->getMessage());

    // Check if it's a "table doesn't exist" error
    if (strpos($e->getMessage(), "doesn't exist") !== false ||
        strpos($e->getMessage(), "Unknown table") !== false) {
        // Return a friendly message for missing tables
        echo '<div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Activity tracking is being set up. Activities will appear here once you start using the system.
              </div>';
    } else {
        // Return a general error message
        echo '<div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error loading activities: ' . htmlspecialchars($e->getMessage()) . '
              </div>';
    }
    exit;
}

// Sort activities by time (newest first)
usort($activities, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

// Limit to 10 activities
$activities = array_slice($activities, 0, 10);

// Helper function to calculate time elapsed
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Create a weeks property
    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

    $string = array();

    if ($diff->y > 0) {
        $string['y'] = $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
    }

    if ($diff->m > 0) {
        $string['m'] = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
    }

    if ($weeks > 0) {
        $string['w'] = $weeks . ' week' . ($weeks > 1 ? 's' : '');
    }

    if ($days > 0) {
        $string['d'] = $days . ' day' . ($days > 1 ? 's' : '');
    }

    if ($diff->h > 0) {
        $string['h'] = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
    }

    if ($diff->i > 0) {
        $string['i'] = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
    }

    if ($diff->s > 0) {
        $string['s'] = $diff->s . ' second' . ($diff->s > 1 ? 's' : '');
    }

    if (!$full) {
        $string = array_slice($string, 0, 1);
    }

    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// Generate HTML for activities
$html = '';
if (empty($activities)) {
    $html = '<div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No recent activities found. Activities will appear here as you and your team use the system.
             </div>';
} else {
    foreach ($activities as $activity) {
        $time_ago = time_elapsed_string($activity['time']);
        $icon_color = '';

        switch ($activity['type']) {
            case 'login':
                $icon_color = '#3b82f6';
                break;
            case 'job_order':
                $icon_color = '#10b981';
                break;
            case 'appointment':
                $icon_color = '#f59e0b';
                break;
            default:
                $icon_color = '#6b7280';
        }

        $html .= '<div class="activity-card">';
        $html .= '<div class="activity-header">';
        $html .= '<div class="activity-icon" style="background-color: ' . $icon_color . '20; color: ' . $icon_color . ';">';
        $html .= '<i class="fas fa-' . $activity['icon'] . '"></i>';
        $html .= '</div>';
        $html .= '<div>';
        $html .= '<h5 class="activity-title">' . $activity['title'] . '</h5>';
        $html .= '<div class="activity-time">' . $time_ago . '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<p class="activity-content">' . $activity['content'] . '</p>';
        $html .= '</div>';
    }
}

// Return the HTML
echo $html;
?>
