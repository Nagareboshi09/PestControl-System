/**
 * Debug Notifications JavaScript
 * This file helps debug notification issues
 */

// Add this script to any page where notifications aren't working
document.addEventListener('DOMContentLoaded', function() {
    console.log('Debug Notifications Script Loaded');
    
    // Check if notification elements exist
    const notificationIcon = document.querySelector('.notification-icon');
    const notificationDropdown = document.querySelector('.notification-dropdown');
    const notificationBadge = document.querySelector('.notification-badge');
    
    console.log('Notification Elements:');
    console.log('- notification-icon exists:', notificationIcon !== null);
    console.log('- notification-dropdown exists:', notificationDropdown !== null);
    console.log('- notification-badge exists:', notificationBadge !== null);
    
    // Check if notification functions exist
    console.log('Notification Functions:');
    console.log('- fetchNotifications exists:', typeof fetchNotifications === 'function');
    console.log('- updateNotificationBadge exists:', typeof updateNotificationBadge === 'function');
    console.log('- updateNotificationDropdown exists:', typeof updateNotificationDropdown === 'function');
    console.log('- markNotificationAsRead exists:', typeof markNotificationAsRead === 'function');
    console.log('- markAllNotificationsAsRead exists:', typeof markAllNotificationsAsRead === 'function');
    
    // Check session data
    console.log('Checking session data via fetch...');
    fetch('../check_session.php')
        .then(response => response.json())
        .then(data => {
            console.log('Session data:', data);
        })
        .catch(error => {
            console.error('Error fetching session data:', error);
        });
    
    // Try to fetch notifications
    if (typeof fetchNotifications === 'function') {
        console.log('Attempting to fetch notifications...');
        try {
            fetchNotifications();
            console.log('fetchNotifications called successfully');
        } catch (error) {
            console.error('Error calling fetchNotifications:', error);
        }
    }
    
    // Add manual click handler for notification icon
    if (notificationIcon) {
        console.log('Adding manual click handler to notification icon');
        notificationIcon.addEventListener('click', function(e) {
            console.log('Notification icon clicked (debug handler)');
            e.stopPropagation();
            if (notificationDropdown) {
                notificationDropdown.classList.toggle('show');
                console.log('Notification dropdown toggled:', notificationDropdown.classList.contains('show'));
            }
        });
    }
});
