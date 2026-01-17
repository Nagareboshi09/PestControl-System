<?php
session_start();
require_once '../../db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in as a technician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'technician') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$technician_id = $_SESSION['user_id'];

// Check if appointment_id is provided
if (!isset($_GET['appointment_id']) || !is_numeric($_GET['appointment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit;
}

$appointment_id = (int)$_GET['appointment_id'];

// Check if the appointment_technicians table exists
$tableCheckQuery = "SHOW TABLES LIKE 'appointment_technicians'";
$tableCheckResult = $conn->query($tableCheckQuery);

if ($tableCheckResult->num_rows === 0) {
    // Create the table if it doesn't exist
    $createTableSQL = "CREATE TABLE IF NOT EXISTS appointment_technicians (
        appointment_id INT(11) NOT NULL,
        technician_id INT(11) NOT NULL,
        is_primary BOOLEAN NOT NULL DEFAULT 0,
        PRIMARY KEY (appointment_id, technician_id),
        FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id),
        FOREIGN KEY (technician_id) REFERENCES technicians(technician_id)
    )";
    
    if (!$conn->query($createTableSQL)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create appointment_technicians table: ' . $conn->error]);
        exit;
    }
    
    // Migrate existing appointments with technician_id to the new table
    $migrateSQL = "INSERT INTO appointment_technicians (appointment_id, technician_id, is_primary)
                  SELECT appointment_id, technician_id, 1
                  FROM appointments
                  WHERE technician_id IS NOT NULL";
    
    if (!$conn->query($migrateSQL)) {
        echo json_encode(['success' => false, 'message' => 'Failed to migrate existing appointments: ' . $conn->error]);
        exit;
    }
}

// Check if the technician is assigned to this appointment and if they are the primary technician
$query = "SELECT is_primary FROM appointment_technicians 
          WHERE appointment_id = ? AND technician_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $appointment_id, $technician_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Check if the technician is assigned in the old way (directly in the appointments table)
    $oldQuery = "SELECT technician_id FROM appointments 
                WHERE appointment_id = ? AND technician_id = ?";
    
    $oldStmt = $conn->prepare($oldQuery);
    $oldStmt->bind_param("ii", $appointment_id, $technician_id);
    $oldStmt->execute();
    $oldResult = $oldStmt->get_result();
    
    if ($oldResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Technician is not assigned to this appointment']);
        exit;
    }
    
    // Technician is assigned in the old way, so they are the primary technician
    // Let's migrate this to the new table
    $migrateStmt = $conn->prepare("INSERT INTO appointment_technicians (appointment_id, technician_id, is_primary) VALUES (?, ?, 1)");
    $migrateStmt->bind_param("ii", $appointment_id, $technician_id);
    $migrateStmt->execute();
    
    echo json_encode([
        'success' => true,
        'is_primary' => true,
        'appointment_id' => $appointment_id,
        'technician_id' => $technician_id,
        'migrated' => true
    ]);
    exit;
}

$row = $result->fetch_assoc();
$isPrimary = (bool)$row['is_primary'];

echo json_encode([
    'success' => true,
    'is_primary' => $isPrimary,
    'appointment_id' => $appointment_id,
    'technician_id' => $technician_id
]);
?>
