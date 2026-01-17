/**
 * Notifications JavaScript
 * 
 * This file contains functions for handling notifications UI and interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize notifications
    initNotifications();
    
    // Fetch notifications every 60 seconds
    setInterval(fetchNotifications, 60000);
});

/**
 * Initialize notifications
 */
function initNotifications() {
    // Fetch notifications on page load
    fetchNotifications();
    
    // Add click event to notification icon
    const notificationIcon = document.querySelector('.notification-container');
    if (notificationIcon) {
        notificationIcon.addEventListener('click', toggleNotificationDropdown);
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.querySelector('.notification-dropdown');
        const container = document.querySelector('.notification-container');
        
        if (dropdown && container && !container.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });
    
    // Add event listener for mark all as read button
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('mark-all-read')) {
            markAllNotificationsAsRead();
        }
    });
}

/**
 * Toggle notification dropdown
 */
function toggleNotificationDropdown(event) {
    event.stopPropagation();
    const dropdown = document.querySelector('.notification-dropdown');
    
    if (dropdown) {
        dropdown.classList.toggle('show');
        
        // If dropdown is shown, mark visible notifications as read
        if (dropdown.classList.contains('show')) {
            const visibleNotifications = dropdown.querySelectorAll('.notification-item.unread');
            visibleNotifications.forEach(notification => {
                const notificationId = notification.getAttribute('data-id');
                if (notificationId) {
                    markNotificationAsRead(notificationId);
                }
            });
        }
    }
}

/**
 * Fetch notifications from the server
 */
function fetchNotifications() {
    fetch('get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Error fetching notifications:', data.error);
                return;
            }
            
            updateNotificationBadge(data.unread_count);
            updateNotificationDropdown(data.notifications);
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
        });
}

/**
 * Update notification badge with unread count
 */
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

/**
 * Update notification dropdown with notifications
 */
function updateNotificationDropdown(notifications) {
    const dropdown = document.querySelector('.notification-dropdown');
    
    if (!dropdown) return;
    
    const notificationList = dropdown.querySelector('.notification-list');
    
    if (!notificationList) return;
    
    // Clear existing notifications
    notificationList.innerHTML = '';
    
    if (notifications.length === 0) {
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
        if (notification.related_type === 'appointment') {
            icon = 'calendar-alt';
        } else if (notification.related_type === 'report') {
            icon = 'clipboard-check';
        }
        
        // Format time
        const createdAt = new Date(notification.created_at);
        const timeAgo = formatTimeAgo(createdAt);
        
        notificationItem.innerHTML = `
            <div class="notification-content">
                <div class="notification-icon-wrapper">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="notification-text">
                    <div class="notification-title">${notification.title}</div>
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-time">${timeAgo}</div>
                </div>
            </div>
        `;
        
        // Add click event to mark as read
        notificationItem.addEventListener('click', function() {
            markNotificationAsRead(notification.notification_id);
            
            // Handle navigation based on notification type
            if (notification.related_type === 'appointment') {
                // Navigate to appointment details
                window.location.href = getAppointmentUrl(notification.related_id);
            } else if (notification.related_type === 'report') {
                // Navigate to report details
                window.location.href = getReportUrl(notification.related_id);
            }
        });
        
        notificationList.appendChild(notificationItem);
    });
}

/**
 * Mark a notification as read
 */
function markNotificationAsRead(notificationId) {
    const formData = new FormData();
    formData.append('notification_id', notificationId);
    
    fetch('mark_notification_read.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI
                const notification = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (notification) {
                    notification.classList.remove('unread');
                }
                
                // Refresh notification count
                fetchNotifications();
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsAsRead() {
    const formData = new FormData();
    formData.append('mark_all', 'true');
    
    fetch('mark_notification_read.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI
                const notifications = document.querySelectorAll('.notification-item.unread');
                notifications.forEach(notification => {
                    notification.classList.remove('unread');
                });
                
                // Refresh notification count
                fetchNotifications();
            }
        })
        .catch(error => {
            console.error('Error marking all notifications as read:', error);
        });
}

/**
 * Format time ago
 */
function formatTimeAgo(date) {
    const now = new Date();
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

/**
 * Get URL for appointment details
 */
function getAppointmentUrl(appointmentId) {
    // Determine the correct URL based on user role
    const userRole = getUserRole();
    
    if (userRole === 'client') {
        return `inspection_report.php?appointment_id=${appointmentId}`;
    } else if (userRole === 'technician') {
        return `inspection.php?appointment_id=${appointmentId}`;
    } else if (userRole === 'admin') {
        return `calendar.php?appointment_id=${appointmentId}`;
    }
    
    return '#';
}

/**
 * Get URL for report details
 */
function getReportUrl(reportId) {
    // Determine the correct URL based on user role
    const userRole = getUserRole();
    
    if (userRole === 'client') {
        return `inspection_report.php?report_id=${reportId}`;
    } else if (userRole === 'technician') {
        return `job_order.php?report_id=${reportId}`;
    } else if (userRole === 'admin') {
        return `assessment_report.php?report_id=${reportId}`;
    }
    
    return '#';
}

/**
 * Get user role
 */
function getUserRole() {
    // This function should return the current user's role
    // You may need to adapt this based on how roles are stored in your application
    
    // Check if there's a client-side element
    if (document.querySelector('#sidebar .sidebar-menu a[href="schedule.php"]')) {
        return 'client';
    }
    
    // Check if there's a technician-side element
    if (document.querySelector('.sidebar .sidebar-nav a[href="inspection.php"]')) {
        return 'technician';
    }
    
    // Default to admin
    return 'admin';
}
