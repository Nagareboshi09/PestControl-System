<?php
// Script to run the SQL file to add the new columns to the database

// Database connection parameters
$host = 'localhost';
$dbname = 'macj_pest_control';
$username = 'root';
$password = '';

try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to the database successfully.<br>";
    
    // Read the SQL file
    $sql = file_get_contents('add_dilution_fields.sql');
    
    // Execute the SQL
    $pdo->exec($sql);
    
    echo "Database schema updated successfully.<br>";
    
    // Check if the columns were added
    $stmt = $pdo->query("SHOW COLUMNS FROM chemical_inventory LIKE 'dilution_rate'");
    if ($stmt->rowCount() > 0) {
        echo "dilution_rate column added successfully.<br>";
    } else {
        echo "Failed to add dilution_rate column.<br>";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM chemical_inventory LIKE 'area_coverage'");
    if ($stmt->rowCount() > 0) {
        echo "area_coverage column added successfully.<br>";
    } else {
        echo "Failed to add area_coverage column.<br>";
    }
    
    echo "<br>You can now use the dilution calculator in the Add New Chemical form.<br>";
    echo "<a href='Admin Side/chemical_inventory.php'>Go to Chemical Inventory</a>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
