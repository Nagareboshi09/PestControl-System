/**
 * Technician Side Notifications JavaScript
 * This file contains all the JavaScript functionality for notifications
 */

/**
 * Fetch notifications from the server
 */
function fetchNotifications() {
    // Use relative path for better portability
    fetch('../get_notifications.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                console.error('Error fetching notifications:', data.error);
                return;
            }

            console.log('Notifications data received:', data);
            // Make sure we're not showing notifications if there are none
            if (!data.notifications || !Array.isArray(data.notifications)) {
                data.notifications = [];
                data.unread_count = 0;
            }
            updateNotificationBadge(data.unread_count);
            updateNotificationDropdown(data.notifications);
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
            // In case of error, clear notifications
            updateNotificationBadge(0);
            updateNotificationDropdown([]);
        });
}

/**
 * Update notification badge with unread count
 */
function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');

    if (badge) {
        // Ensure count is a valid number
        count = parseInt(count) || 0;

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.textContent = '0';
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

    // Ensure notifications is an array
    if (!notifications || !Array.isArray(notifications) || notifications.length === 0) {
        notificationList.innerHTML = '<div class="notification-empty">No notifications</div>';
        return;
    }

    // Add notifications to the list
    notifications.forEach(notification => {
        const notificationItem = document.createElement('li');
        notificationItem.className = `notification-item ${notification.is_read === '0' ? 'unread' : ''}`;
        notificationItem.setAttribute('data-id', notification.notification_id);

        // Determine icon based on related_type
        // For the image shown, we're using calendar-alt for all notifications to match the design
        let icon = 'calendar-alt';

        // Keep this logic for other notification types if needed in the future
        if (notification.related_type === 'report') {
            icon = 'clipboard-check';
        } else if (notification.related_type === 'job_order' || notification.related_type === 'job_order_24h' || notification.related_type === 'job_order_1h') {
            icon = 'calendar-alt'; // Changed from 'tasks' to 'calendar-alt' to match the image
        } else if (notification.related_type === 'appointment_rescheduled' || notification.related_type === 'job_order_rescheduled') {
            icon = 'calendar-plus'; // Special icon for rescheduled appointments and job orders
        }

        // Add urgency indicator for 1-hour notifications or rescheduled items
        let urgencyClass = '';
        if (notification.related_type === 'appointment_1h' || notification.related_type === 'job_order_1h') {
            urgencyClass = 'urgent';
        } else if (notification.related_type === 'appointment_rescheduled' || notification.related_type === 'job_order_rescheduled') {
            urgencyClass = 'rescheduled';
        }

        notificationItem.innerHTML = `
            <div class="notification-content">
                <div class="notification-icon-wrapper ${urgencyClass}">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="notification-text">
                    <div class="notification-title ${urgencyClass}">${notification.title}</div>
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-time">${formatTimeAgo(notification.created_at)}</div>
                </div>
            </div>
        `;

        // Add click event to mark as read
        notificationItem.addEventListener('click', function() {
            markNotificationAsRead(notification.notification_id);

            // Handle navigation based on notification type
            if (notification.related_type === 'appointment' || notification.related_type === 'appointment_24h' ||
                notification.related_type === 'appointment_1h' || notification.related_type === 'appointment_rescheduled') {
                // Navigate to appointment details
                window.location.href = `schedule.php?appointment_id=${notification.related_id}`;
            } else if (notification.related_type === 'job_order' || notification.related_type === 'job_order_24h' ||
                       notification.related_type === 'job_order_1h' || notification.related_type === 'job_order_rescheduled') {
                // Navigate to job order details
                window.location.href = `job_order.php?job_id=${notification.related_id}`;
            } else if (notification.related_type === 'inspection') {
                // Navigate to inspection details
                window.location.href = `inspection.php?inspection_id=${notification.related_id}`;
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

    console.log('Marking notification as read:', notificationId);

    // Use relative path for better portability
    fetch('../mark_notification_read.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            console.log('Mark as read response:', data);
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
    const unreadNotifications = document.querySelectorAll('.notification-item.unread');

    console.log('Marking all notifications as read');

    // Always make the request, even if there are no unread notifications in the UI
    // This ensures any notifications in the database are marked as read
    const formData = new FormData();
    formData.append('mark_all', 'true');

    // Use relative path for better portability
    fetch('../mark_notification_read.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        console.log('Mark all as read response:', data);
        if (data.success) {
            // Update UI
            unreadNotifications.forEach(notification => {
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
 * Format time ago from timestamp
 * For recent timestamps (< 1 hour), shows "time ago" format
 * For older timestamps, shows the actual time in 12-hour format
 */
function formatTimeAgo(timestamp) {
    const now = new Date();
    const date = new Date(timestamp);
    const diffInSeconds = Math.floor((now - date) / 1000);

    // For very recent timestamps (less than 1 hour), show "time ago" format
    if (diffInSeconds < 60) {
        return 'Just now';
    }

    const diffInMinutes = Math.floor(diffInSeconds / 60);
    if (diffInMinutes < 60) {
        return `${diffInMinutes} minute${diffInMinutes > 1 ? 's' : ''} ago`;
    }

    // For older timestamps, show the actual date and time in 12-hour format
    // Format the date based on how old it is
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const notificationDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const diffInDays = Math.floor((today - notificationDate) / (1000 * 60 * 60 * 24));

    // Format the time in 12-hour format (h:mm AM/PM)
    const timeOptions = { hour: 'numeric', minute: '2-digit', hour12: true };
    const timeStr = date.toLocaleTimeString('en-US', timeOptions);

    if (diffInDays === 0) {
        // Today: show only time
        return `Today at ${timeStr}`;
    } else if (diffInDays === 1) {
        // Yesterday: show "Yesterday at [time]"
        return `Yesterday at ${timeStr}`;
    } else if (diffInDays < 7) {
        // Within the last week: show day of week and time
        const dayOfWeek = date.toLocaleDateString('en-US', { weekday: 'long' });
        return `${dayOfWeek} at ${timeStr}`;
    } else {
        // Older than a week: show month, day, and time
        const dateOptions = { month: 'short', day: 'numeric' };
        const dateStr = date.toLocaleDateString('en-US', dateOptions);
        return `${dateStr} at ${timeStr}`;
    }
}

// Initialize notifications when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Notification functionality
    const notificationIcon = document.querySelector('.notification-icon');
    const notificationDropdown = document.querySelector('.notification-dropdown');
    const markAllRead = document.querySelector('.mark-all-read');
    const notificationHeader = document.querySelector('.notification-header h3');

    // Check if we're on a mobile device
    const isMobile = window.innerWidth <= 768;

    // Add close button for mobile
    if (isMobile && notificationDropdown) {
        // Check if the close button already exists
        if (!document.querySelector('.notification-mobile-close')) {
            const closeButton = document.createElement('div');
            closeButton.className = 'notification-mobile-close';
            closeButton.textContent = 'Close';
            closeButton.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent event from bubbling up
                notificationDropdown.classList.remove('show');
                document.body.style.overflow = '';
            });
            notificationDropdown.appendChild(closeButton);
        }
    }

    if (notificationIcon && notificationDropdown) {
        // Toggle notification dropdown
        notificationIcon.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent event from bubbling up
            notificationDropdown.classList.toggle('show');

            // If on mobile, prevent body scrolling when dropdown is open
            if (isMobile && notificationDropdown.classList.contains('show')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationDropdown.contains(e.target) && !notificationIcon.contains(e.target)) {
                notificationDropdown.classList.remove('show');
                document.body.style.overflow = '';
            }
        });

        // Add back button functionality for mobile
        if (notificationHeader) {
            notificationHeader.addEventListener('click', function(e) {
                // Check if the click was on the back arrow (::before pseudo-element)
                // We approximate this by checking if the click was near the left side of the header
                if (e.offsetX < 30 && isMobile) {
                    notificationDropdown.classList.remove('show');
                    document.body.style.overflow = '';
                    e.stopPropagation();
                }
            });
        }
    }

    if (markAllRead) {
        // Mark all as read
        markAllRead.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent event from bubbling up
            markAllNotificationsAsRead();
        });
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        const newIsMobile = window.innerWidth <= 768;

        // If transitioning between mobile and desktop, reset styles
        if (newIsMobile !== isMobile && !notificationDropdown.classList.contains('show')) {
            document.body.style.overflow = '';
        }

        // Add or remove mobile close button based on screen size
        if (newIsMobile && !document.querySelector('.notification-mobile-close') && notificationDropdown) {
            const closeButton = document.createElement('div');
            closeButton.className = 'notification-mobile-close';
            closeButton.textContent = 'Close';
            closeButton.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent event from bubbling up
                notificationDropdown.classList.remove('show');
                document.body.style.overflow = '';
            });
            notificationDropdown.appendChild(closeButton);
        } else if (!newIsMobile) {
            const closeButton = document.querySelector('.notification-mobile-close');
            if (closeButton) {
                closeButton.remove();
            }
        }
    });

    // Fetch notifications on page load
    fetchNotifications();

    // Fetch notifications every 60 seconds
    setInterval(fetchNotifications, 60000);
});
