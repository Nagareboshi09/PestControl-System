<?php
session_start();
require_once '../../db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: max-age=60'); // Cache for 60 seconds

// Check if user is logged in as technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get technician ID
$technician_id = $_SESSION['user_id'];

// Get target pest type from query parameter (optional)
$target_pest = isset($_GET['target_pest']) ? $_GET['target_pest'] : null;

// Check if we have a cached version of the data
$cache_key = 'chemical_inventory_' . md5($target_pest ?? 'all');
if (isset($_SESSION[$cache_key]) && isset($_SESSION[$cache_key . '_timestamp'])) {
    // Check if cache is still valid (less than 60 seconds old)
    $cache_age = time() - $_SESSION[$cache_key . '_timestamp'];
    if ($cache_age < 60) {
        // Return cached data
        echo $_SESSION[$cache_key];
        exit;
    }
}

try {
    // Optimize the query - no need to check for status column every time
    // Use a more efficient query with indexing in mind
    $query = "SELECT id, chemical_name, type, target_pest, quantity, unit, expiration_date,
              CASE
                  WHEN quantity <= 0 THEN 'Out of Stock'
                  WHEN quantity < 10 THEN 'Low Stock'
                  ELSE 'In Stock'
              END as status
              FROM chemical_inventory
              WHERE quantity > 0";

    // Add target pest filter if provided
    if ($target_pest) {
        $query .= " AND target_pest LIKE ?";
    }

    // Order by expiration date (chemicals expiring sooner first)
    $query .= " ORDER BY expiration_date ASC";

    // Prepare and execute the query
    $stmt = $conn->prepare($query);

    if ($target_pest) {
        $search_term = "%$target_pest%";
        $stmt->bind_param("s", $search_term);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch all chemicals
    $chemicals = [];
    while ($row = $result->fetch_assoc()) {
        // Format expiration date
        $row['expiration_date_formatted'] = date('M d, Y', strtotime($row['expiration_date']));

        // Calculate days until expiration
        $today = new DateTime();
        $expiry = new DateTime($row['expiration_date']);
        $interval = $today->diff($expiry);
        $row['days_until_expiry'] = $interval->days * ($interval->invert ? -1 : 1); // Negative if expired

        // Add to chemicals array
        $chemicals[] = $row;
    }

    // Create the response
    $response = json_encode([
        'success' => true,
        'message' => 'Available chemicals retrieved successfully',
        'chemicals' => $chemicals,
        'timestamp' => time()
    ]);

    // Cache the response
    $_SESSION[$cache_key] = $response;
    $_SESSION[$cache_key . '_timestamp'] = time();

    // Return success response with chemicals
    echo $response;

} catch (Exception $e) {
    // Return error response with more detailed information for debugging
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving chemicals: ' . $e->getMessage(),
        'error_code' => $conn->errno,
        'error_details' => $conn->error
    ]);
}

// Close database connection
$conn->close();
?>
