/**
 * Admin Side Chemical Notifications JavaScript
 * This file extends the standard notifications.js with chemical-specific functionality
 */

// Extend the original updateNotificationDropdown function from notifications.js
// Store the original function
const originalUpdateNotificationDropdown = window.updateNotificationDropdown || function() {};

// Override the function with our extended version
window.updateNotificationDropdown = function(notifications) {
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
        let iconClass = '';

        if (notification.related_type === 'appointment') {
            icon = 'calendar-alt';
        } else if (notification.related_type === 'report') {
            // Check if this is a technician verification notification
            if (notification.title === 'Technician Work Verified') {
                icon = 'user-check';
                iconClass = 'verification-completed';
            } else {
                icon = 'clipboard-check';
            }
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
                // Navigate to appointment details
                window.location.href = `calendar.php?appointment_id=${notification.related_id}`;
            } else if (notification.related_type === 'report') {
                // Navigate to report details
                window.location.href = `assessment_report.php?report_id=${notification.related_id}`;
            } else if (notification.related_type === 'expiring_chemical') {
                // Navigate to chemical inventory with expiration sorting
                window.location.href = `chemical_inventory.php?sort=expiration`;
            } else if (notification.related_type === 'job_order_report') {
                // Navigate to job order report details
                window.location.href = `joborder_report.php?job_order_id=${notification.related_id}`;
            }
        });

        notificationList.appendChild(notificationItem);
    });
};

// Override the markNotificationAsRead function to add viewed indicator
const originalMarkNotificationAsRead = window.markNotificationAsRead || function() {};

// Override the markAllNotificationsAsRead function to add viewed indicator
const originalMarkAllNotificationsAsRead = window.markAllNotificationsAsRead || function() {};

window.markAllNotificationsAsRead = function() {
    const unreadNotifications = document.querySelectorAll('.notification-item.unread');

    // Always make the request, even if there are no unread notifications in the UI
    // This ensures any notifications in the database are marked as read
    const formData = new FormData();
    formData.append('mark_all', 'true');

    // Use relative path for better portability
    fetch('../mark_notification_read.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
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
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
    });
};

window.markNotificationAsRead = function(notificationId) {
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
};

// Add custom styles for chemical notifications
document.addEventListener('DOMContentLoaded', function() {
    // Add custom styles for chemical notifications
    const style = document.createElement('style');
    style.textContent = `
        .notification-icon-wrapper.chemical-expiring {
            background-color: #ffe6e6;
        }
        .notification-icon-wrapper.chemical-expiring i {
            color: #cc0000;
        }
        .notification-icon-wrapper.job-completed {
            background-color: #e6ffe6;
        }
        .notification-icon-wrapper.job-completed i {
            color: #28a745;
        }
        /* Style for technician verification notifications */
        .notification-icon-wrapper.verification-completed {
            background-color: #e6f7ff;
        }
        .notification-icon-wrapper.verification-completed i {
            color: #0066cc;
        }

        /* Override notification item styles */
        .notification-item.unread {
            background-color: white !important;
            border-left: none !important;
        }

        .notification-item.unread:hover {
            background-color: #f9f9f9 !important;
        }

        .notification-item:not(.unread) {
            background-color: #EBF5FF !important;
            border-left: 3px solid #3B82F6;
        }
    `;
    document.head.appendChild(style);
});
