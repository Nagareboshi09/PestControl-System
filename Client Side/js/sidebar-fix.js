/**
 * Enhanced sidebar functionality for responsive mode
 * This script ensures the sidebar displays correctly when toggled in responsive mode
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Sidebar fix script loaded');
    
    // Get sidebar elements
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    
    // Check if elements exist
    if (!sidebar || !menuToggle) {
        console.error('Sidebar elements not found in sidebar-fix.js');
        return;
    }
    
    console.log('Sidebar elements found');
    
    // Remove any existing click handlers by cloning the element
    const newMenuToggle = menuToggle.cloneNode(true);
    menuToggle.parentNode.replaceChild(newMenuToggle, menuToggle);
    
    // Get the new menu toggle element
    const updatedMenuToggle = document.getElementById('menuToggle');
    
    if (!updatedMenuToggle) {
        console.error('Updated menu toggle element not found');
        return;
    }
    
    console.log('Menu toggle element cloned successfully');
    
    // Define the click handler function
    function handleMenuToggleClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Menu toggle clicked');
        
        // Toggle active class on sidebar
        sidebar.classList.toggle('active');
        
        // Toggle body class for iOS fix
        document.body.classList.toggle('sidebar-active');
        
        // Force sidebar to be visible when active
        if (sidebar.classList.contains('active')) {
            console.log('Sidebar is now active');
            
            // Apply inline styles to ensure visibility
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
                console.log('Creating overlay');
                
                const overlay = document.createElement('div');
                overlay.classList.add('sidebar-overlay');
                
                // Apply inline styles to ensure visibility
                overlay.style.position = 'fixed';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.width = '100%';
                overlay.style.height = '100%';
                overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
                overlay.style.zIndex = '1045';
                overlay.style.display = 'block';
                overlay.style.opacity = '1';
                overlay.style.cursor = 'pointer';
                
                document.body.appendChild(overlay);
                
                // Close sidebar when clicking on overlay
                overlay.addEventListener('click', function() {
                    console.log('Overlay clicked');
                    
                    sidebar.classList.remove('active');
                    document.body.classList.remove('sidebar-active');
                    
                    // Reset sidebar position
                    sidebar.style.left = '-280px';
                    
                    // Remove overlay
                    document.body.removeChild(overlay);
                });
            }
        } else {
            console.log('Sidebar is now inactive');
            
            // Reset sidebar position
            sidebar.style.left = '-280px';
            
            // Remove overlay
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) {
                document.body.removeChild(overlay);
            }
        }
    }
    
    // Add the click event listener to the new element
    updatedMenuToggle.addEventListener('click', handleMenuToggleClick);
    console.log('Click event listener added to menu toggle');
    
    // Add touch event listener for mobile devices
    updatedMenuToggle.addEventListener('touchstart', function(e) {
        console.log('Touch event on menu toggle');
        e.preventDefault();
        handleMenuToggleClick(e);
    }, { passive: false });
    
    // Ensure sidebar is properly positioned on page load
    if (window.innerWidth <= 768) {
        sidebar.style.left = '-280px';
    } else {
        sidebar.style.left = '0';
    }
    
    // Close sidebar when clicking outside
    document.addEventListener('click', function(e) {
        // Only proceed if sidebar is active and we're on mobile
        if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
            // Check if click is outside sidebar and menu toggle
            if (!sidebar.contains(e.target) && !updatedMenuToggle.contains(e.target) &&
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
            sidebar.style.position = '';
            sidebar.style.top = '';
            sidebar.style.height = '';
            sidebar.style.width = '';
            
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
});
