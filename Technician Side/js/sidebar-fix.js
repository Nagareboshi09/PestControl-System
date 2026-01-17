/**
 * Enhanced sidebar fixes for responsive mode in Technician Side
 * This script ensures the sidebar displays correctly when toggled in responsive mode
 * and fixes issues with the sidebar not appearing in job_order.php
 */
document.addEventListener('DOMContentLoaded', function() {
    // Get sidebar elements
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');

    // Check if elements exist
    if (!sidebar || !menuToggle) {
        console.error('Sidebar elements not found in sidebar-fix.js');
        return;
    }

    console.log('Technician sidebar fix script initialized');

    // Check if we're on the job_order.php page
    const isJobOrderPage = window.location.pathname.includes('job_order.php');
    if (isJobOrderPage) {
        console.log('Job Order page detected - applying enhanced sidebar fixes');
    }

    // Remove any existing click event listeners from menuToggle
    // This prevents conflicts with sidebar.js
    const newMenuToggle = menuToggle.cloneNode(true);
    menuToggle.parentNode.replaceChild(newMenuToggle, menuToggle);

    // Force sidebar to be visible when active class is added
    newMenuToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        console.log('Menu toggle clicked in enhanced sidebar-fix.js');

        // Toggle active class
        sidebar.classList.toggle('active');

        // Toggle body class for iOS fix
        document.body.classList.toggle('sidebar-active');

        // Force sidebar to be visible when active
        if (sidebar.classList.contains('active')) {
            console.log('Sidebar is now active - applying forced styles');
            sidebar.style.display = 'block';
            sidebar.style.left = '0';
            sidebar.style.visibility = 'visible';
            sidebar.style.opacity = '1';
            sidebar.style.zIndex = '1050';
            sidebar.style.position = 'fixed';
            sidebar.style.top = '0';
            sidebar.style.height = '100%';
            sidebar.style.width = '250px';

            // Create overlay if it doesn't exist
            if (!document.querySelector('.sidebar-overlay')) {
                const overlay = document.createElement('div');
                overlay.classList.add('sidebar-overlay');
                overlay.style.position = 'fixed';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.width = '100%';
                overlay.style.height = '100%';
                overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
                overlay.style.zIndex = '1045';
                overlay.style.display = 'block';
                overlay.style.opacity = '1';
                document.body.appendChild(overlay);

                // Close sidebar when clicking on overlay
                overlay.addEventListener('click', function() {
                    console.log('Overlay clicked - closing sidebar');
                    sidebar.classList.remove('active');
                    document.body.classList.remove('sidebar-active');
                    sidebar.style.left = '-280px';
                    document.body.removeChild(overlay);
                });
            }
        } else {
            console.log('Sidebar is now inactive');
            sidebar.style.left = '-280px';

            // Remove overlay
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) {
                document.body.removeChild(overlay);
            }
        }
    });

    // Add touch event listener for mobile devices
    newMenuToggle.addEventListener('touchstart', function(e) {
        console.log('Touch event on menu toggle');
        e.preventDefault();
        e.stopPropagation();

        // Trigger the click event handler
        newMenuToggle.click();
    }, { passive: false });

    // Close sidebar when clicking outside
    document.addEventListener('click', function(e) {
        // Only proceed if sidebar is active and we're on mobile
        if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
            // Check if click is outside sidebar and menu toggle
            if (!sidebar.contains(e.target) && !newMenuToggle.contains(e.target) &&
                !e.target.classList.contains('sidebar-overlay')) {
                console.log('Clicked outside sidebar');
                sidebar.classList.remove('active');
                document.body.classList.remove('sidebar-active');

                // Reset sidebar position
                sidebar.style.left = '-280px';

                // Remove overlay
                const overlay = document.querySelector('.sidebar-overlay');
                if (overlay) {
                    try {
                        document.body.removeChild(overlay);
                    } catch (err) {
                        console.error('Error removing overlay:', err);
                    }
                }
            }
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            // Reset sidebar for desktop view
            sidebar.classList.remove('active');
            document.body.classList.remove('sidebar-active');
            sidebar.style.left = '0';
            sidebar.style.display = 'block';
            sidebar.style.visibility = 'visible';
            sidebar.style.opacity = '1';

            // Remove overlay
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) {
                try {
                    document.body.removeChild(overlay);
                } catch (err) {
                    console.error('Error removing overlay on resize:', err);
                }
            }
        } else if (window.innerWidth <= 768 && !sidebar.classList.contains('active')) {
            // Reset sidebar for mobile view when not active
            sidebar.style.left = '-280px';
        }
    });

    // Fix for iOS devices
    if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
        document.body.style.cursor = 'pointer';
    }

    // Initial check for mobile view
    if (window.innerWidth <= 768) {
        console.log('Mobile view detected - setting initial sidebar position');
        sidebar.style.left = '-280px';
    }
});
