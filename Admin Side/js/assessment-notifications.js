/**
 * Assessment Report Notifications JavaScript
 * This file ensures notification functionality works properly on the assessment report page
 */

document.addEventListener('DOMContentLoaded', function() {
    // Explicitly handle notification icon click
    const notificationIcon = document.querySelector('.notification-icon');
    const notificationDropdown = document.querySelector('.notification-dropdown');
    
    if (notificationIcon && notificationDropdown) {
        // Remove any existing click listeners (just in case)
        const newNotificationIcon = notificationIcon.cloneNode(true);
        notificationIcon.parentNode.replaceChild(newNotificationIcon, notificationIcon);
        
        // Add fresh click event listener
        newNotificationIcon.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
            console.log('Notification icon clicked - dropdown toggled');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationDropdown.contains(e.target) && e.target !== newNotificationIcon) {
                notificationDropdown.classList.remove('show');
            }
        });
    }
    
    // Handle mark all as read
    const markAllRead = document.querySelector('.mark-all-read');
    if (markAllRead) {
        markAllRead.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            console.log('Mark all as read clicked');
            if (typeof markAllNotificationsAsRead === 'function') {
                markAllNotificationsAsRead();
            }
        });
    }
});
