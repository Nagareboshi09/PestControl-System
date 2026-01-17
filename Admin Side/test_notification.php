<?php
session_start();
require_once '../db_config.php';
require_once '../notification_functions.php';

// Check if user is logged in as office staff
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $title = "Test Notification";
    $message_text = "This is a test notification created at " . date('Y-m-d H:i:s');
    
    // Create notification
    $result = createNotification(
        $user_id,
        'admin', // Map office_staff to admin for notifications
        $title,
        $message_text,
        null,
        'test',
        $pdo
    );
    
    if ($result) {
        $message = "Test notification created successfully!";
    } else {
        $error = "Failed to create test notification.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Notification - MacJ Pest Control</title>
    <link rel="stylesheet" href="css/reports-page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <style>
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #3B82F6;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        button {
            background-color: #3B82F6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        button:hover {
            background-color: #2563EB;
        }
        
        .success-message {
            color: #10B981;
            padding: 10px;
            background-color: #ECFDF5;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .error-message {
            color: #EF4444;
            padding: 10px;
            background-color: #FEF2F2;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .debug-info {
            margin-top: 30px;
            padding: 15px;
            background-color: #F3F4F6;
            border-radius: 4px;
        }
        
        .debug-info h2 {
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .debug-info pre {
            background-color: #E5E7EB;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-bell"></i> Test Notification System</h1>
        
        <?php if (!empty($message)): ?>
        <div class="success-message">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="error-message">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <p>Click the button below to create a test notification for yourself.</p>
                <button type="submit">Create Test Notification</button>
            </div>
        </form>
        
        <div class="debug-info">
            <h2>Debug Information</h2>
            <pre><?php
                echo "Session Information:\n";
                echo "- role: " . ($_SESSION['role'] ?? 'not set') . "\n";
                echo "- user_id: " . ($_SESSION['user_id'] ?? 'not set') . "\n";
                echo "- username: " . ($_SESSION['username'] ?? 'not set') . "\n";
                
                echo "\nDatabase Connection:\n";
                echo "- PDO connection: " . (isset($pdo) ? 'established' : 'not established') . "\n";
                
                echo "\nNotification Table Structure:\n";
                try {
                    $stmt = $pdo->query("DESCRIBE notifications");
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($columns as $column) {
                        echo "- " . $column['Field'] . ": " . $column['Type'] . 
                             ($column['Null'] === 'NO' ? ' (required)' : ' (optional)') . 
                             ($column['Key'] === 'PRI' ? ' (primary key)' : '') . "\n";
                    }
                } catch (Exception $e) {
                    echo "Error fetching table structure: " . $e->getMessage();
                }
                
                echo "\nRecent Notifications:\n";
                try {
                    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND user_type = 'admin' ORDER BY created_at DESC LIMIT 5");
                    $stmt->execute([$_SESSION['user_id']]);
                    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($notifications) > 0) {
                        foreach ($notifications as $index => $notification) {
                            echo ($index + 1) . ". " . $notification['title'] . " - " . 
                                 substr($notification['message'], 0, 30) . "... " . 
                                 "(" . ($notification['is_read'] == 1 ? 'read' : 'unread') . ")\n";
                        }
                    } else {
                        echo "No notifications found for this user.\n";
                    }
                } catch (Exception $e) {
                    echo "Error fetching notifications: " . $e->getMessage();
                }
            ?></pre>
        </div>
        
        <p><a href="reports.php">&larr; Back to Reports</a></p>
    </div>
    
    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <script src="js/chemical-notifications.js"></script>
    <script src="js/debug-notifications.js"></script>
</body>
</html>
