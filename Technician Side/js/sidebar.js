document.addEventListener('DOMContentLoaded', function() {
    // Get sidebar elements with error handling
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    const mainContent = document.querySelector('.main-content');

    // Check if required elements exist
    if (!sidebar || !menuToggle) {
        console.error('Sidebar elements not found');
        return; // Exit if elements don't exist
    }

    console.log('Sidebar script initialized');

    // Toggle sidebar on mobile
    menuToggle.addEventListener('click', function(e) {
        e.preventDefault(); // Prevent default action
        e.stopPropagation(); // Stop event from bubbling up
        console.log('Menu toggle clicked');

        sidebar.classList.toggle('active');

        // Toggle a class on the body to control header position
        document.body.classList.toggle('sidebar-active');

        // Add overlay when sidebar is active
        if (sidebar.classList.contains('active')) {
            // Remove existing overlay if any
            const existingOverlay = document.querySelector('.sidebar-overlay');
            if (existingOverlay) {
                document.body.removeChild(existingOverlay);
            }

            const overlay = document.createElement('div');
            overlay.classList.add('sidebar-overlay');
            document.body.appendChild(overlay);

            // Close sidebar when clicking on overlay
            overlay.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                sidebar.classList.remove('active');
                document.body.classList.remove('sidebar-active');
                document.body.removeChild(overlay);
            });
        } else {
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) {
                document.body.removeChild(overlay);
            }
        }
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        // Only proceed if sidebar is active and we're on mobile
        if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
            // Check if click is outside sidebar and menu toggle
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target) &&
                !e.target.classList.contains('sidebar-overlay')) {
                console.log('Clicked outside sidebar');
                sidebar.classList.remove('active');
                document.body.classList.remove('sidebar-active');

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

    // Handle window resize with debounce for better performance
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            console.log('Window resized');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                document.body.classList.remove('sidebar-active');
                const overlay = document.querySelector('.sidebar-overlay');
                if (overlay) {
                    try {
                        document.body.removeChild(overlay);
                    } catch (err) {
                        console.error('Error removing overlay on resize:', err);
                    }
                }
            }
        }, 250); // 250ms debounce
    });

    // Set active menu item based on current page
    const currentPage = window.location.pathname.split('/').pop();
    console.log('Current page:', currentPage);

    const menuLinks = document.querySelectorAll('.sidebar-menu a');
    console.log('Found', menuLinks.length, 'menu links');

    // First remove active class from all links
    menuLinks.forEach(link => {
        link.classList.remove('active');
    });

    // Then add active class to matching link
    let activeFound = false;
    menuLinks.forEach(link => {
        const linkPage = link.getAttribute('href');
        console.log('Checking link:', linkPage);

        if (linkPage === currentPage) {
            link.classList.add('active');
            activeFound = true;
            console.log('Active link found:', linkPage);
        }

        // Add click event listener to each link for better navigation
        link.addEventListener('click', function(e) {
            // Remove active class from all links
            menuLinks.forEach(l => l.classList.remove('active'));
            // Add active class to clicked link
            this.classList.add('active');
        });
    });

    // If no active link was found, try to match partial paths
    if (!activeFound && currentPage) {
        menuLinks.forEach(link => {
            const linkPage = link.getAttribute('href');
            if (currentPage.includes(linkPage) && linkPage !== '') {
                link.classList.add('active');
                console.log('Partial match found:', linkPage);
            }
        });
    }

    // Add CSS for overlay with higher z-index
    const style = document.createElement('style');
    style.textContent = `
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1045; /* Higher z-index to ensure it's above other elements but below the sidebar */
            display: block;
            opacity: 1;
            transition: opacity 0.3s ease;
            cursor: pointer;
        }
    `;
    document.head.appendChild(style);
});
