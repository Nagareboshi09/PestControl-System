/**
 * Client Side Notifications JavaScript
 * This file contains all the JavaScript functionality for notifications
 */

/**
 * Fetch notifications from the server
 */
function fetchNotifications() {
    // Use relative path for better portability
    fetch('../get_notifications.php')
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
    const notificationList = document.querySelector('.notification-list');

    if (!notificationList) {
        console.error('Notification list element not found');
        return;
    }

    // Clear existing notifications
    notificationList.innerHTML = '';

    // If no notifications, show empty message
    if (!notifications || notifications.length === 0) {
        const emptyItem = document.createElement('li');
        emptyItem.className = 'notification-empty';
        emptyItem.textContent = 'No notifications';
        notificationList.appendChild(emptyItem);
        return;
    }

    // Add notifications to the list
    notifications.forEach(notification => {
        const notificationItem = document.createElement('li');
        notificationItem.className = `notification-item ${notification.is_read === '0' ? 'unread' : ''}`;
        notificationItem.setAttribute('data-id', notification.notification_id);

        // Determine icon based on related_type
        let icon = 'bell';
        if (notification.related_type === 'appointment' || notification.related_type === 'appointment_24h' || notification.related_type === 'appointment_1h') {
            icon = 'calendar-alt';
        } else if (notification.related_type === 'appointment_rescheduled') {
            icon = 'calendar-plus';
        } else if (notification.related_type === 'report') {
            icon = 'clipboard-check';
        } else if (notification.related_type === 'quotation') {
            icon = 'file-invoice-dollar';
        } else if (notification.related_type === 'job_order_report') {
            icon = 'check-circle';
        }

        // Add urgency indicator for 1-hour notifications or rescheduled items
        let urgencyClass = '';
        if (notification.related_type === 'appointment_1h' || notification.related_type === 'job_order_1h') {
            urgencyClass = 'urgent';
        } else if (notification.related_type === 'appointment_rescheduled' || notification.related_type === 'job_order_rescheduled') {
            urgencyClass = 'rescheduled';
        }

        notificationItem.innerHTML = `
            <div class="notification-icon-wrapper ${urgencyClass}">
                <i class="fas fa-${icon}"></i>
            </div>
            <div class="notification-info">
                <div class="notification-title ${urgencyClass}">${notification.title}</div>
                <div class="notification-desc">${notification.message}</div>
                <div class="notification-time">${formatTimeAgo(notification.created_at)}</div>
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
            } else if (notification.related_type === 'report') {
                // Navigate to report details
                window.location.href = `inspection_report.php?report_id=${notification.related_id}`;
            } else if (notification.related_type === 'quotation') {
                // Navigate to contract page
                window.location.href = `contract.php?job_order_id=${notification.related_id}`;
            } else if (notification.related_type === 'job_order_report' ||
                       notification.related_type === 'job_order' ||
                       notification.related_type === 'job_order_rescheduled') {
                // Navigate to job order report page
                window.location.href = `job_order_report.php?job_order_id=${notification.related_id}`;
            }
        });

        notificationList.appendChild(notificationItem);
    });
}

/**
 * Format time ago from timestamp
 */
function formatTimeAgo(timestamp) {
    const now = new Date();
    const date = new Date(timestamp);
    const seconds = Math.floor((now - date) / 1000);

    let interval = Math.floor(seconds / 31536000);
    if (interval >= 1) {
        return interval + ' year' + (interval === 1 ? '' : 's') + ' ago';
    }

    interval = Math.floor(seconds / 2592000);
    if (interval >= 1) {
        return interval + ' month' + (interval === 1 ? '' : 's') + ' ago';
    }

    interval = Math.floor(seconds / 86400);
    if (interval >= 1) {
        return interval + ' day' + (interval === 1 ? '' : 's') + ' ago';
    }

    interval = Math.floor(seconds / 3600);
    if (interval >= 1) {
        return interval + ' hour' + (interval === 1 ? '' : 's') + ' ago';
    }

    interval = Math.floor(seconds / 60);
    if (interval >= 1) {
        return interval + ' minute' + (interval === 1 ? '' : 's') + ' ago';
    }

    return Math.floor(seconds) + ' second' + (seconds === 1 ? '' : 's') + ' ago';
}

/**
 * Mark a notification as read
 */
function markNotificationAsRead(notificationId) {
    const formData = new FormData();
    formData.append('notification_id', notificationId);

    // Use relative path for better portability
    fetch('../mark_notification_read.php', {
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

    console.log('Marking all notifications as read');

    // Use relative path for better portability
    fetch('../mark_notification_read.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            console.log('Response received:', response);
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            if (data.success) {
                // Update UI - remove unread class from all notifications
                const unreadNotifications = document.querySelectorAll('.notification-item.unread');
                unreadNotifications.forEach(notification => {
                    notification.classList.remove('unread');
                });

                // Update badge
                updateNotificationBadge(0);

                // Show success message
                if (typeof showToast === 'function') {
                    showToast('All notifications marked as read', 'success');
                }
            } else {
                console.error('Failed to mark notifications as read:', data.message);
            }
        })
        .catch(error => {
            console.error('Error marking all notifications as read:', error);
        });
}

/**
 * Initialize notification functionality
 */
function initNotifications() {
    // Fetch notifications on page load
    fetchNotifications();

    // Set up polling to check for new notifications every minute
    setInterval(fetchNotifications, 60000);

    // Toggle notification dropdown
    const notificationContainer = document.querySelector('.notification-container');
    const notificationDropdown = document.querySelector('.notification-dropdown');
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
                $(notificationDropdown).removeClass('show');
                document.body.style.overflow = '';
            });
            notificationDropdown.appendChild(closeButton);
        }
    }

    if (notificationContainer && notificationDropdown) {
        // Use jQuery for better event handling across browsers
        $(notificationContainer).on('click', function(e) {
            e.stopPropagation();
            $(notificationDropdown).toggleClass('show');
            console.log('Notification container clicked, dropdown toggled');

            // If on mobile, prevent body scrolling when dropdown is open
            if (isMobile && $(notificationDropdown).hasClass('show')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });

        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!notificationDropdown.contains(e.target) && !notificationContainer.contains(e.target)) {
                $(notificationDropdown).removeClass('show');
                document.body.style.overflow = '';
            }
        });

        // Add back button functionality for mobile
        if (notificationHeader) {
            $(notificationHeader).on('click', function(e) {
                // Check if the click was on the back arrow (::before pseudo-element)
                // We approximate this by checking if the click was near the left side of the header
                if (e.offsetX < 30 && isMobile) {
                    $(notificationDropdown).removeClass('show');
                    document.body.style.overflow = '';
                    e.stopPropagation();
                }
            });
        }
    } else {
        console.error('Notification container or dropdown not found');
    }

    // Mark all as read functionality
    const markAllReadBtn = document.querySelector('.mark-all-read');
    if (markAllReadBtn) {
        $(markAllReadBtn).on('click', function(e) {
            e.stopPropagation();
            markAllNotificationsAsRead();
        });
    }

    // Handle window resize
    $(window).on('resize', function() {
        const newIsMobile = window.innerWidth <= 768;

        // If transitioning between mobile and desktop, reset styles
        if (newIsMobile !== isMobile && !$(notificationDropdown).hasClass('show')) {
            document.body.style.overflow = '';
        }

        // Add or remove mobile close button based on screen size
        if (newIsMobile && !document.querySelector('.notification-mobile-close') && notificationDropdown) {
            const closeButton = document.createElement('div');
            closeButton.className = 'notification-mobile-close';
            closeButton.textContent = 'Close';
            closeButton.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent event from bubbling up
                $(notificationDropdown).removeClass('show');
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
}

// Initialize notifications when the DOM is loaded
document.addEventListener('DOMContentLoaded', initNotifications);
