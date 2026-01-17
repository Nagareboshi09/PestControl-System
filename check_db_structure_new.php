<?php
require_once 'db_connect.php';

// Check job_order_checklists table structure
echo "<h2>job_order_checklists Table Structure</h2>";
$result = $conn->query("SHOW COLUMNS FROM job_order_checklists");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

// Check technicians table structure
echo "<h2>technicians Table Structure</h2>";
$result = $conn->query("SHOW COLUMNS FROM technicians");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

// Check the problematic query
echo "<h2>Problematic Query</h2>";
echo "<pre>
SELECT t.*,
CASE
    WHEN joc.id IS NOT NULL THEN 'in use'
    ELSE COALESCE(t.status, 'in stock')
END AS current_status,
CASE
    WHEN joc.id IS NOT NULL THEN tech.name
    ELSE NULL
END AS technician_name,
CASE
    WHEN joc.id IS NOT NULL THEN jo.job_order_id
    ELSE NULL
END AS job_order_id
FROM tools_equipment t
LEFT JOIN job_order_checklists joc ON FIND_IN_SET(t.id, joc.checked_items) > 0
   AND joc.id = (
       SELECT MAX(id) FROM job_order_checklists
       WHERE FIND_IN_SET(t.id, checked_items) > 0
   )
LEFT JOIN job_order jo ON joc.job_order_id = jo.job_order_id
LEFT JOIN technicians tech ON joc.technician_id = tech.technician_id
</pre>";

// Try to execute the query with a modified version that doesn't use tech.name
echo "<h2>Testing Modified Query</h2>";
try {
    $result = $conn->query("
        SELECT t.*,
        CASE
            WHEN joc.id IS NOT NULL THEN 'in use'
            ELSE COALESCE(t.status, 'in stock')
        END AS current_status,
        joc.technician_id,
        CASE
            WHEN joc.id IS NOT NULL THEN jo.job_order_id
            ELSE NULL
        END AS job_order_id
        FROM tools_equipment t
        LEFT JOIN job_order_checklists joc ON FIND_IN_SET(t.id, joc.checked_items) > 0
           AND joc.id = (
               SELECT MAX(id) FROM job_order_checklists
               WHERE FIND_IN_SET(t.id, checked_items) > 0
           )
        LEFT JOIN job_order jo ON joc.job_order_id = jo.job_order_id
        LIMIT 5
    ");
    
    if ($result) {
        echo "Modified query executed successfully.<br>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Status</th><th>Technician ID</th><th>Job Order ID</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . ($row['id'] ?? 'N/A') . "</td>";
            echo "<td>" . ($row['name'] ?? 'N/A') . "</td>";
            echo "<td>" . ($row['current_status'] ?? 'N/A') . "</td>";
            echo "<td>" . ($row['technician_id'] ?? 'N/A') . "</td>";
            echo "<td>" . ($row['job_order_id'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "Error: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
?>
