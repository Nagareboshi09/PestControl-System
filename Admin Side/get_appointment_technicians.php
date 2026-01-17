<?php
require_once '../db_connect.php';

header('Content-Type: application/json');

// Check if appointment_id is provided
if (!isset($_GET['appointment_id']) || !is_numeric($_GET['appointment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit;
}

$appointmentId = (int)$_GET['appointment_id'];

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

// Get all technicians assigned to this appointment
$query = "SELECT at.technician_id, t.username, at.is_primary
          FROM appointment_technicians at
          JOIN technicians t ON at.technician_id = t.technician_id
          WHERE at.appointment_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$result = $stmt->get_result();

$technicians = [];
while ($row = $result->fetch_assoc()) {
    $technicians[] = [
        'id' => (int)$row['technician_id'],
        'name' => $row['username'],
        'isPrimary' => (bool)$row['is_primary']
    ];
}

// If no technicians found in the new table but there's a technician_id in the appointments table
if (empty($technicians)) {
    $legacyQuery = "SELECT a.technician_id, t.username
                   FROM appointments a
                   JOIN technicians t ON a.technician_id = t.technician_id
                   WHERE a.appointment_id = ?";
    
    $legacyStmt = $conn->prepare($legacyQuery);
    $legacyStmt->bind_param("i", $appointmentId);
    $legacyStmt->execute();
    $legacyResult = $legacyStmt->get_result();
    
    if ($legacyRow = $legacyResult->fetch_assoc()) {
        $technicians[] = [
            'id' => (int)$legacyRow['technician_id'],
            'name' => $legacyRow['username'],
            'isPrimary' => true
        ];
        
        // Insert this technician into the new table
        $insertStmt = $conn->prepare("INSERT INTO appointment_technicians (appointment_id, technician_id, is_primary) VALUES (?, ?, 1)");
        $insertStmt->bind_param("ii", $appointmentId, $legacyRow['technician_id']);
        $insertStmt->execute();
    }
}

echo json_encode(['success' => true, 'technicians' => $technicians]);
?>
