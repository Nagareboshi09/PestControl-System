<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: ../SignIn.php");
    exit;
}
require_once '../db_connect.php';

// Check if the status column already exists
$result = $conn->query("SHOW COLUMNS FROM tools_equipment LIKE 'status'");
$statusColumnExists = $result->num_rows > 0;

$message = '';
$messageType = '';

if (!$statusColumnExists) {
    // Add the status column
    $alterTableSQL = "ALTER TABLE tools_equipment ADD COLUMN status ENUM('in stock', 'in use') NOT NULL DEFAULT 'in stock'";

    if ($conn->query($alterTableSQL)) {
        $message = '<div style="padding: 20px; background-color: #d4edda; color: #155724; margin: 20px; border-radius: 5px;">
                <h3>Success!</h3>
                <p>The status column has been added to the tools_equipment table.</p>
                <p><a href="tools_equipment.php" class="btn btn-primary">Return to Tools and Equipment</a></p>
              </div>';
        $messageType = 'success';
    } else {
        $message = '<div style="padding: 20px; background-color: #f8d7da; color: #721c24; margin: 20px; border-radius: 5px;">
                <h3>Error</h3>
                <p>Failed to add status column: ' . $conn->error . '</p>
                <p><a href="tools_equipment.php" class="btn btn-primary">Return to Tools and Equipment</a></p>
              </div>';
        $messageType = 'error';
    }
} else {
    $message = '<div style="padding: 20px; background-color: #fff3cd; color: #856404; margin: 20px; border-radius: 5px;">
            <h3>Notice</h3>
            <p>The status column already exists in the tools_equipment table.</p>
            <p><a href="tools_equipment.php" class="btn btn-primary">Return to Tools and Equipment</a></p>
          </div>';
    $messageType = 'notice';
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Tools Table - MacJ Pest Control</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-database mr-3"></i>Update Tools Table</h1>
        <?php echo $message; ?>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
