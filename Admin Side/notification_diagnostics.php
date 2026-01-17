<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}
require_once '../db_config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Diagnostics - MacJ Pest Control</title>
    <link rel="stylesheet" href="css/reports-page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #3B82F6;
            margin-bottom: 20px;
        }
        
        h2 {
            color: #4B5563;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 1px solid #E5E7EB;
            padding-bottom: 10px;
        }
        
        .test-section {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #F9FAFB;
            border-radius: 8px;
        }
        
        .test-button {
            background-color: #3B82F6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .test-button:hover {
            background-color: #2563EB;
        }
        
        .result-box {
            background-color: #F3F4F6;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            overflow-x: auto;
            white-space: pre-wrap;
            font-family: monospace;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .success {
            color: #10B981;
        }
        
        .error {
            color: #EF4444;
        }
        
        .notification-demo {
            position: relative;
            margin-top: 30px;
            padding: 20px;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
        }
        
        .notification-container {
            position: relative;
            display: inline-block;
            margin-right: 20px;
        }
        
        .notification-icon {
            font-size: 1.5rem;
            color: #3B82F6;
            cursor: pointer;
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #EF4444;
            color: white;
            font-size: 0.75rem;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 350px;
            max-height: 400px;
            overflow-y: auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            display: none;
        }
        
        .notification-dropdown.show {
            display: block;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #E5E7EB;
        }
        
        .notification-header h3 {
            margin: 0;
            font-size: 16px;
        }
        
        .mark-all-read {
            color: #3B82F6;
            font-size: 14px;
            cursor: pointer;
        }
        
        .notification-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .notification-item {
            display: flex;
            padding: 12px 15px;
            border-bottom: 1px solid #E5E7EB;
        }
        
        .notification-item.unread {
            background-color: #F0F7FF;
        }
        
        .notification-info {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .notification-desc {
            font-size: 14px;
            color: #6B7280;
            margin-bottom: 5px;
        }
        
        .notification-time {
            font-size: 12px;
            color: #9CA3AF;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-bell"></i> Notification Diagnostics</h1>
        
        <div class="notification-demo">
            <h2>Notification Demo</h2>
            <p>This is a standalone notification system for testing. Click the bell icon to see notifications.</p>
            
            <div class="notification-container">
                <i class="fas fa-bell notification-icon" id="demoNotificationIcon"></i>
                <span class="notification-badge" id="demoNotificationBadge">3</span>
                
                <div class="notification-dropdown" id="demoNotificationDropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <span class="mark-all-read" id="demoMarkAllRead">Mark all as read</span>
                    </div>
                    <ul class="notification-list" id="demoNotificationList">
                        <li class="notification-item unread">
                            <div class="notification-info">
                                <div class="notification-title">Test Notification 1</div>
                                <div class="notification-desc">This is a test notification message.</div>
                                <div class="notification-time">Just now</div>
                            </div>
                        </li>
                        <li class="notification-item unread">
                            <div class="notification-info">
                                <div class="notification-title">Test Notification 2</div>
                                <div class="notification-desc">This is another test notification message.</div>
                                <div class="notification-time">5 minutes ago</div>
                            </div>
                        </li>
                        <li class="notification-item">
                            <div class="notification-info">
                                <div class="notification-title">Test Notification 3</div>
                                <div class="notification-desc">This is a read notification message.</div>
                                <div class="notification-time">1 hour ago</div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <h2>Session Information</h2>
        <div class="test-section">
            <div class="result-box">
                <?php
                echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "\n";
                echo "Role: " . ($_SESSION['role'] ?? 'Not set') . "\n";
                echo "Username: " . ($_SESSION['username'] ?? 'Not set') . "\n";
                echo "Session ID: " . session_id() . "\n";
                ?>
            </div>
        </div>
        
        <h2>Database Connection Test</h2>
        <div class="test-section">
            <button class="test-button" id="testDbConnection">Test Database Connection</button>
            <div class="result-box" id="dbConnectionResult"></div>
        </div>
        
        <h2>Notification API Test</h2>
        <div class="test-section">
            <button class="test-button" id="testNotificationApi">Test Notification API</button>
            <div class="result-box" id="notificationApiResult"></div>
        </div>
        
        <h2>Create Test Notification</h2>
        <div class="test-section">
            <button class="test-button" id="createTestNotification">Create Test Notification</button>
            <div class="result-box" id="createNotificationResult"></div>
        </div>
        
        <h2>Mark All Notifications as Read</h2>
        <div class="test-section">
            <button class="test-button" id="markAllNotificationsRead">Mark All as Read</button>
            <div class="result-box" id="markAllReadResult"></div>
        </div>
        
        <p><a href="reports.php">&larr; Back to Reports</a></p>
    </div>
    
    <script>
        // Demo notification functionality
        document.addEventListener('DOMContentLoaded', function() {
            const demoNotificationIcon = document.getElementById('demoNotificationIcon');
            const demoNotificationDropdown = document.getElementById('demoNotificationDropdown');
            const demoMarkAllRead = document.getElementById('demoMarkAllRead');
            
            // Toggle dropdown when notification icon is clicked
            demoNotificationIcon.addEventListener('click', function(e) {
                e.stopPropagation();
                demoNotificationDropdown.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!demoNotificationDropdown.contains(e.target) && e.target !== demoNotificationIcon) {
                    demoNotificationDropdown.classList.remove('show');
                }
            });
            
            // Mark all as read
            demoMarkAllRead.addEventListener('click', function(e) {
                e.stopPropagation();
                const unreadItems = document.querySelectorAll('#demoNotificationList .notification-item.unread');
                unreadItems.forEach(item => {
                    item.classList.remove('unread');
                });
                document.getElementById('demoNotificationBadge').style.display = 'none';
            });
            
            // Test database connection
            document.getElementById('testDbConnection').addEventListener('click', function() {
                const resultBox = document.getElementById('dbConnectionResult');
                resultBox.innerHTML = 'Testing database connection...';
                
                fetch('api_check_notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            resultBox.innerHTML = '<span class="success">Database connection successful!</span>\n\n';
                            resultBox.innerHTML += 'Connection: ' + data.database.connection + '\n';
                            if (data.database.table_structure) {
                                resultBox.innerHTML += '\nNotification Table Structure:\n';
                                data.database.table_structure.forEach(column => {
                                    resultBox.innerHTML += `- ${column.Field}: ${column.Type} ${column.Null === 'NO' ? '(required)' : '(optional)'}\n`;
                                });
                            }
                        } else {
                            resultBox.innerHTML = '<span class="error">Database connection failed!</span>\n\n';
                            resultBox.innerHTML += 'Error: ' + (data.error || 'Unknown error');
                        }
                    })
                    .catch(error => {
                        resultBox.innerHTML = '<span class="error">Error testing database connection!</span>\n\n';
                        resultBox.innerHTML += 'Error: ' + error.message;
                    });
            });
            
            // Test notification API
            document.getElementById('testNotificationApi').addEventListener('click', function() {
                const resultBox = document.getElementById('notificationApiResult');
                resultBox.innerHTML = 'Testing notification API...';
                
                fetch('api_check_notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        resultBox.innerHTML = 'API Response:\n\n';
                        resultBox.innerHTML += JSON.stringify(data, null, 2);
                    })
                    .catch(error => {
                        resultBox.innerHTML = '<span class="error">Error testing notification API!</span>\n\n';
                        resultBox.innerHTML += 'Error: ' + error.message;
                    });
            });
            
            // Create test notification
            document.getElementById('createTestNotification').addEventListener('click', function() {
                const resultBox = document.getElementById('createNotificationResult');
                resultBox.innerHTML = 'Creating test notification...';
                
                const formData = new FormData();
                formData.append('action', 'create');
                
                fetch('direct_notification_test.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.statusText);
                        }
                        resultBox.innerHTML = '<span class="success">Test notification created successfully!</span>\n\n';
                        resultBox.innerHTML += 'Refresh the page to see the new notification.';
                    })
                    .catch(error => {
                        resultBox.innerHTML = '<span class="error">Error creating test notification!</span>\n\n';
                        resultBox.innerHTML += 'Error: ' + error.message;
                    });
            });
            
            // Mark all notifications as read
            document.getElementById('markAllNotificationsRead').addEventListener('click', function() {
                const resultBox = document.getElementById('markAllReadResult');
                resultBox.innerHTML = 'Marking all notifications as read...';
                
                const formData = new FormData();
                formData.append('action', 'mark_all_read');
                
                fetch('direct_notification_test.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.statusText);
                        }
                        resultBox.innerHTML = '<span class="success">All notifications marked as read successfully!</span>';
                    })
                    .catch(error => {
                        resultBox.innerHTML = '<span class="error">Error marking all notifications as read!</span>\n\n';
                        resultBox.innerHTML += 'Error: ' + error.message;
                    });
            });
        });
    </script>
</body>
</html>
