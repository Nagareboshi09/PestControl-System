/**
 * Floating Action Button JavaScript
 * This file contains the functionality for the floating action button
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the floating action button
    initFloatingActionButton();
});

/**
 * Initialize the floating action button functionality
 */
function initFloatingActionButton() {
    console.log('Initializing floating action button');

    // Get the floating action button and procedure steps elements
    const fabButton = document.querySelector('#fab-main-button') || document.querySelector('.fab-button');
    const procedureSteps = document.querySelector('.procedure-steps');
    const closeButton = document.querySelector('.procedure-steps-close');

    // If elements don't exist, return
    if (!fabButton) {
        console.error('Floating action button not found');
        return;
    }

    if (!procedureSteps) {
        console.error('Procedure steps not found');
        // Even if procedure steps is missing, we can still animate the button
    }

    if (!closeButton && procedureSteps) {
        console.error('Close button not found');
        // Even if close button is missing, we can still animate the button
    }

    // Add click event listener to the floating action button
    fabButton.addEventListener('click', function() {
        // Toggle the active class on the procedure steps if it exists
        if (procedureSteps) {
            procedureSteps.classList.toggle('active');
        }

        // Stop bouncing animation when clicked
        stopBounceAnimation();
    });

    // Add click event listener to the close button if it exists
    if (closeButton) {
        closeButton.addEventListener('click', function() {
            // Remove the active class from the procedure steps
            procedureSteps.classList.remove('active');
        });
    }

    // Close the procedure steps when clicking outside if it exists
    if (procedureSteps) {
        document.addEventListener('click', function(event) {
            // If the click is outside the fab button and procedure steps
            if (!fabButton.contains(event.target) &&
                !procedureSteps.contains(event.target)) {
                // Remove the active class from the procedure steps
                procedureSteps.classList.remove('active');
            }
        });
    }

    // Test the animation immediately
    fabButton.classList.add('fab-bounce');
    console.log('Added initial bounce class');

    // Remove the test animation after 3 seconds
    setTimeout(function() {
        fabButton.classList.remove('fab-bounce');
        console.log('Removed initial bounce class');

        // Start the regular bounce animation cycle
        startBounceCycle();
    }, 3000);
}

/**
 * Start the bounce animation cycle
 * This will make the FAB bounce every 30 seconds
 */
function startBounceCycle() {
    console.log('Starting bounce cycle');

    // Initial bounce after 5 seconds
    const initialBounceTimeout = setTimeout(function() {
        console.log('Executing initial bounce');
        startBounceAnimation();
    }, 5000);

    // Set up recurring bounce every 30 seconds
    const bounceInterval = setInterval(function() {
        console.log('Executing scheduled bounce');
        startBounceAnimation();
    }, 30000);

    // Store the timeout and interval IDs on the window object so they can be cleared if needed
    window.fabBounceTimeout = initialBounceTimeout;
    window.fabBounceInterval = bounceInterval;
}

/**
 * Start the bounce animation
 */
function startBounceAnimation() {
    const fabButton = document.querySelector('#fab-main-button') || document.querySelector('.fab-button');
    const procedureSteps = document.querySelector('.procedure-steps');

    // Only bounce if the button exists
    if (fabButton) {
        console.log('Starting bounce animation');

        // Add the bounce class
        fabButton.classList.add('fab-bounce');

        // Remove the bounce class after 3 seconds
        setTimeout(stopBounceAnimation, 3000);
    } else {
        console.error('FAB button not found for animation');
    }
}

/**
 * Stop the bounce animation
 */
function stopBounceAnimation() {
    const fabButton = document.querySelector('#fab-main-button') || document.querySelector('.fab-button');

    if (fabButton) {
        console.log('Stopping bounce animation');

        // Remove the bounce class
        fabButton.classList.remove('fab-bounce');

        // Force a reflow to ensure the animation stops immediately
        void fabButton.offsetWidth;
    } else {
        console.error('FAB button not found when trying to stop animation');
    }
}
