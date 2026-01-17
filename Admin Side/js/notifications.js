/**
 * Admin Side Notifications JavaScript
 * This file contains all the JavaScript functionality for notifications
 */

/**
 * Fetch notifications from the server
 */
function fetchNotifications() {
    console.log('fetchNotifications called');
    // Use relative path for better portability
    fetch('../get_notifications.php')
        .then(response => {
            console.log('Notification response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Notification data received:', data);
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

        notificationItem.innerHTML = `
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
                // Navigate to appointment details
                window.location.href = `calendar.php?appointment_id=${notification.related_id}`;
            } else if (notification.related_type === 'report') {
                // Navigate to report details
                window.location.href = `assessment_report.php?report_id=${notification.related_id}`;
            } else if (notification.related_type === 'job_order_report') {
                // Navigate to job order report details
                window.location.href = `joborder_report.php?job_order_id=${notification.related_id}`;
            } else if (notification.related_type === 'job_order_feedback' || notification.related_type === 'job_order') {
                // Navigate to job order report details for feedback
                window.location.href = `joborder_report.php?job_order_id=${notification.related_id}`;
            }
        });

        notificationList.appendChild(notificationItem);
    });
}

/**
 * Mark a notification as read
 */
function markNotificationAsRead(notificationId) {
    console.log('markNotificationAsRead called for ID:', notificationId);
    const formData = new FormData();
    formData.append('notification_id', notificationId);

    // Use relative path for better portability
    fetch('../mark_notification_read.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            console.log('Mark as read response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Mark as read response data:', data);
            if (data.success) {
                // Update UI
                const notification = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                console.log('Found notification element:', notification !== null);
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
    console.log('markAllNotificationsAsRead function called');
    const unreadNotifications = document.querySelectorAll('.notification-item.unread');
    console.log('Unread notifications found:', unreadNotifications.length);

    // Always make the request, even if there are no unread notifications in the UI
    // This ensures any notifications in the database are marked as read
    const formData = new FormData();
    formData.append('mark_all', 'true');

    console.log('Sending request to mark all notifications as read');
    // Use relative path for better portability
    fetch('../mark_notification_read.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response received:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            // Update UI
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

            // Refresh notification count
            fetchNotifications();
        } else if (data.error) {
            console.error('Server error:', data.error);
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
    });
}

/**
 * Format time ago from timestamp
 */
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

// Initialize notifications when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Notification functionality
    const notificationIcon = document.querySelector('.notification-icon');
    const notificationDropdown = document.querySelector('.notification-dropdown');
    const markAllRead = document.querySelector('.mark-all-read');

    if (notificationIcon && notificationDropdown) {
        // Toggle notification dropdown
        notificationIcon.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationDropdown.contains(e.target) && e.target !== notificationIcon) {
                notificationDropdown.classList.remove('show');
            }
        });
    }

    // Add direct click handler to mark-all-read button
    document.addEventListener('click', function(e) {
        if (e.target.id === 'markAllReadBtn' || e.target.classList.contains('mark-all-read') || e.target.closest('.mark-all-read')) {
            e.stopPropagation();
            e.preventDefault();
            console.log('Mark all as read clicked');
            markAllNotificationsAsRead();
        }
    });

    // Also add a specific event listener for the button by ID
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            console.log('Mark all as read button clicked by ID');
            markAllNotificationsAsRead();
        });
    }

    // Initial fetch of notifications
    fetchNotifications();

    // Fetch notifications every 60 seconds
    setInterval(fetchNotifications, 60000);
});
