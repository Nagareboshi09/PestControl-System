/**
 * Reports Page Notification System
 * This is a standalone notification system specifically for the reports.php page
 */

// Immediately execute when the script loads
(function() {
    console.log('Reports Notification System Initializing...');
    
    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        initReportsNotifications();
    });
    
    // Initialize notifications
    function initReportsNotifications() {
        console.log('Initializing Reports Notifications');
        
        // Get notification elements
        const notificationIcon = document.querySelector('.notification-icon');
        const notificationBadge = document.querySelector('.notification-badge');
        const notificationDropdown = document.querySelector('.notification-dropdown');
        const notificationList = document.querySelector('.notification-list');
        const markAllReadBtn = document.querySelector('.mark-all-read');
        
        console.log('Notification Elements Found:', {
            icon: notificationIcon !== null,
            badge: notificationBadge !== null,
            dropdown: notificationDropdown !== null,
            list: notificationList !== null,
            markAllBtn: markAllReadBtn !== null
        });
        
        // Set up notification icon click handler
        if (notificationIcon && notificationDropdown) {
            notificationIcon.addEventListener('click', function(e) {
                e.stopPropagation();
                console.log('Notification icon clicked');
                notificationDropdown.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!notificationDropdown.contains(e.target) && e.target !== notificationIcon) {
                    notificationDropdown.classList.remove('show');
                }
            });
        }
        
        // Set up mark all as read button
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Mark all as read clicked');
                markAllNotificationsAsRead();
            });
        }
        
        // Fetch notifications immediately
        fetchReportsNotifications();
        
        // Set up periodic notification checks
        setInterval(fetchReportsNotifications, 60000); // Check every minute
    }
    
    // Fetch notifications from the server
    function fetchReportsNotifications() {
        console.log('Fetching notifications for reports page');
        
        fetch('../get_notifications.php')
            .then(response => {
                console.log('Notification response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log('Notification data received:', data);
                if (data.error) {
                    console.error('Error in notification data:', data.error);
                    return;
                }
                
                updateNotificationBadge(data.unread_count);
                updateNotificationList(data.notifications);
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
            });
    }
    
    // Update notification badge with unread count
    function updateNotificationBadge(count) {
        const badge = document.querySelector('.notification-badge');
        
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    }
    
    // Update notification list with notifications
    function updateNotificationList(notifications) {
        const notificationList = document.querySelector('.notification-list');
        
        if (!notificationList) {
            console.error('Notification list element not found');
            return;
        }
        
        // Clear existing notifications
        notificationList.innerHTML = '';
        
        if (!notifications || notifications.length === 0) {
            notificationList.innerHTML = '<div class="notification-empty">No notifications</div>';
            return;
        }
        
        // Add notifications to the list
        notifications.forEach(notification => {
            const notificationItem = document.createElement('li');
            notificationItem.className = `notification-item ${notification.is_read === '0' ? 'unread' : ''}`;
            notificationItem.setAttribute('data-id', notification.notification_id);
            
            // Determine icon based on related_type
            let icon = 'bell';
            let iconClass = '';
            
            if (notification.related_type === 'appointment') {
                icon = 'calendar-alt';
            } else if (notification.related_type === 'report') {
                icon = 'clipboard-check';
            } else if (notification.related_type === 'expiring_chemical') {
                icon = 'flask';
                iconClass = 'chemical-expiring';
            } else if (notification.related_type === 'job_order_report') {
                icon = 'check-circle';
                iconClass = 'job-completed';
            }
            
            notificationItem.innerHTML = `
                <div class="notification-icon-wrapper ${iconClass}">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="notification-info">
                    <div class="notification-title">${notification.title}</div>
                    <div class="notification-desc">${notification.message}</div>
                    <div class="notification-time">
                        ${formatTimeAgo(notification.created_at)}
                        ${notification.is_read === '1' ? '<span class="viewed-indicator"><i class="fas fa-check"></i> Viewed</span>' : ''}
                    </div>
                </div>
            `;
            
            // Add click event to mark as read
            notificationItem.addEventListener('click', function() {
                markNotificationAsRead(notification.notification_id);
                
                // Handle navigation based on notification type
                if (notification.related_type === 'appointment') {
                    window.location.href = `calendar.php?appointment_id=${notification.related_id}`;
                } else if (notification.related_type === 'report') {
                    window.location.href = `assessment_report.php?report_id=${notification.related_id}`;
                } else if (notification.related_type === 'expiring_chemical') {
                    window.location.href = `chemical_inventory.php?sort=expiration`;
                } else if (notification.related_type === 'job_order_report') {
                    window.location.href = `joborder_report.php?job_order_id=${notification.related_id}`;
                }
            });
            
            notificationList.appendChild(notificationItem);
        });
    }
    
    // Mark a notification as read
    function markNotificationAsRead(notificationId) {
        console.log('Marking notification as read:', notificationId);
        
        const formData = new FormData();
        formData.append('notification_id', notificationId);
        
        fetch('../mark_notification_read.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Mark as read response status:', response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            console.log('Mark as read response data:', data);
            if (data.success) {
                // Update UI
                const notification = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (notification) {
                    notification.classList.remove('unread');
                    
                    // Add viewed indicator
                    const timeElement = notification.querySelector('.notification-time');
                    if (timeElement && !timeElement.querySelector('.viewed-indicator')) {
                        const viewedIndicator = document.createElement('span');
                        viewedIndicator.className = 'viewed-indicator';
                        viewedIndicator.innerHTML = '<i class="fas fa-check"></i> Viewed';
                        timeElement.appendChild(viewedIndicator);
                    }
                }
                
                // Refresh notifications
                fetchReportsNotifications();
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
    }
    
    // Mark all notifications as read
    function markAllNotificationsAsRead() {
        console.log('Marking all notifications as read');
        
        const formData = new FormData();
        formData.append('mark_all', 'true');
        
        fetch('../mark_notification_read.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Mark all as read response status:', response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            console.log('Mark all as read response data:', data);
            if (data.success) {
                // Update UI
                const unreadNotifications = document.querySelectorAll('.notification-item.unread');
                unreadNotifications.forEach(notification => {
                    notification.classList.remove('unread');
                    
                    // Add viewed indicator
                    const timeElement = notification.querySelector('.notification-time');
                    if (timeElement && !timeElement.querySelector('.viewed-indicator')) {
                        const viewedIndicator = document.createElement('span');
                        viewedIndicator.className = 'viewed-indicator';
                        viewedIndicator.innerHTML = '<i class="fas fa-check"></i> Viewed';
                        timeElement.appendChild(viewedIndicator);
                    }
                });
                
                // Refresh notifications
                fetchReportsNotifications();
            }
        })
        .catch(error => {
            console.error('Error marking all notifications as read:', error);
        });
    }
    
    // Format time ago from timestamp
    function formatTimeAgo(timestamp) {
        const now = new Date();
        const date = new Date(timestamp);
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) {
            return 'Just now';
        }
        
        const diffInMinutes = Math.floor(diffInSeconds / 60);
        if (diffInMinutes < 60) {
            return `${diffInMinutes} minute${diffInMinutes > 1 ? 's' : ''} ago`;
        }
        
        const diffInHours = Math.floor(diffInMinutes / 60);
        if (diffInHours < 24) {
            return `${diffInHours} hour${diffInHours > 1 ? 's' : ''} ago`;
        }
        
        const diffInDays = Math.floor(diffInHours / 24);
        if (diffInDays < 30) {
            return `${diffInDays} day${diffInDays > 1 ? 's' : ''} ago`;
        }
        
        const diffInMonths = Math.floor(diffInDays / 30);
        if (diffInMonths < 12) {
            return `${diffInMonths} month${diffInMonths > 1 ? 's' : ''} ago`;
        }
        
        const diffInYears = Math.floor(diffInMonths / 12);
        return `${diffInYears} year${diffInYears > 1 ? 's' : ''} ago`;
    }
})();
