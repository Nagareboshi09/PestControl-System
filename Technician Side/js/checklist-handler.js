/**
 * Checklist Handler JavaScript
 * Handles the tools checklist flow before showing job details
 */

// Global variables
let currentJobForChecklist = null;
let onChecklistComplete = null;
let callbackExecuted = false; // Flag to track if callback has been executed

/**
 * Check if a job is completed
 * @param {Object} job - The job order object
 * @returns {boolean} True if the job is completed, false otherwise
 */
function isJobCompleted(job) {
    // Check if the job has a status property and it's set to 'completed'
    if (job && job.status && job.status.toLowerCase() === 'completed') {
        console.log('Job is marked as completed:', job.job_order_id);
        return true;
    }

    // Check if the job is in the finished job orders section
    const jobCard = document.querySelector(`.job-card[data-job-id="${job.job_order_id}"]`);
    if (jobCard && jobCard.closest('#finishedJobOrders')) {
        console.log('Job is in the finished job orders section:', job.job_order_id);
        return true;
    }

    // Check if the job card has a 'completed' class or data attribute
    if (jobCard && (
        jobCard.classList.contains('completed') ||
        jobCard.getAttribute('data-status') === 'completed' ||
        jobCard.querySelector('.badge-completed')
    )) {
        console.log('Job card has completed indicators:', job.job_order_id);
        return true;
    }

    return false;
}

/**
 * Show the tools checklist for a job order
 * @param {Object} job - The job order object
 * @param {Function} callback - Function to call after checklist is confirmed
 */
function showChecklistForJob(job, callback) {
    console.log('Showing checklist for job ID:', job.job_order_id);
    console.log('Callback provided:', typeof callback === 'function' ? 'Yes (function)' : 'No (type: ' + typeof callback + ')');

    // Check if the job is completed
    if (isJobCompleted(job)) {
        console.log('Job is completed, skipping checklist and executing callback directly');
        if (typeof callback === 'function') {
            callback();
        } else {
            console.error('No valid callback provided for completed job');
            // Try to show job details directly
            if (typeof openJobDetails === 'function') {
                openJobDetails(job);
            } else if (typeof showJobDetailsAfterChecklist === 'function') {
                showJobDetailsAfterChecklist();
            } else if (typeof showJobDetails === 'function') {
                showJobDetails(job);
            }
        }
        return;
    }

    // Reset callback execution flag
    callbackExecuted = false;
    console.log('Reset callback execution flag to false');

    // Store the job and callback for later use
    currentJobForChecklist = job;

    // Ensure the callback is a function
    if (typeof callback === 'function') {
        onChecklistComplete = callback;
        console.log('Stored valid callback function');
    } else {
        // Create a default callback that will show job details
        onChecklistComplete = function() {
            console.log('Using default callback to show job details');
            if (typeof openJobDetails === 'function') {
                openJobDetails(job);
            } else if (typeof showJobDetailsAfterChecklist === 'function') {
                showJobDetailsAfterChecklist();
            } else {
                console.error('No job details functions available for default callback');
                alert('Error showing job details. Please refresh the page and try again.');
            }
        };
        console.log('Created default callback function');
    }

    console.log('Stored callback:', typeof onChecklistComplete === 'function' ? 'Function' : 'Not a function');

    // For debugging, add a global reference to the callback
    window.storedChecklistCallback = onChecklistComplete;
    console.log('Added global reference to callback as window.storedChecklistCallback');

    // Check if any checklist modal already exists
    const existingModal = document.querySelector('#toolsChecklistModal, #simpleChecklistModal');
    if (existingModal) {
        console.log('Removing existing checklist modal before creating a new one');

        // If it's a Bootstrap modal instance, hide it first
        const bsModal = bootstrap.Modal.getInstance(existingModal);
        if (bsModal) {
            bsModal.hide();

            // Remove the modal after it's hidden
            existingModal.addEventListener('hidden.bs.modal', function() {
                existingModal.remove();
                // Now create a new modal
                createChecklistModal(job);
            }, { once: true });
        } else {
            // If no Bootstrap modal instance, just remove it
            existingModal.remove();
            createChecklistModal(job);
        }
    } else {
        // No existing modal, create a new one
        createChecklistModal(job);
    }
}

/**
 * Create the checklist modal
 * @param {Object} job - The job order object
 */
function createChecklistModal(job) {
    console.log('Creating checklist modal for job ID:', job.job_order_id);

    // Check if any checklist modal already exists and remove it
    const existingModal = document.querySelector('#toolsChecklistModal, #simpleChecklistModal');
    if (existingModal) {
        console.log('Removing existing modal before creating a new one');
        existingModal.remove();
    }

    // Create the modal HTML with client information
    const modalHTML = `
        <div class="modal fade" id="toolsChecklistModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="toolsChecklistModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="toolsChecklistModalLabel">
                            <i class="fas fa-tools me-2"></i>Tools & Equipment Checklist
                        </h5>
                        <button type="button" class="btn-close btn-close-white" id="closeChecklistBtn" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Job Information Banner -->
                        <div class="alert alert-secondary mb-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle fa-2x me-3 text-primary"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">Job Order #${job.job_order_id}: ${job.client_name || 'Unknown Client'}</h6>
                                    <div class="small text-muted">
                                        <i class="fas fa-map-marker-alt me-1"></i> ${job.location_address || 'No address provided'}
                                    </div>
                                    <div class="small text-muted">
                                        <i class="fas fa-calendar me-1"></i> ${new Date(job.preferred_date).toLocaleDateString()}
                                        ${job.preferred_time ? `<i class="fas fa-clock ms-2 me-1"></i> ${job.preferred_time.substr(0,5)}` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Important:</strong> You must complete this checklist before viewing job details. Please check all the tools and equipment you'll need for this job.
                        </div>

                        <div class="checklist-progress mb-3">
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="text-center mt-1">
                                <span class="checked-count">0</span>/<span class="total-count">0</span> items checked
                            </div>
                        </div>

                        <div id="checklistContent">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Loading tools and equipment checklist...</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="confirmChecklistBtn" disabled>
                            <i class="fas fa-check me-2"></i>Confirm & Continue to Job Details
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Add the modal to the document
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Get the modal element
    const modal = document.getElementById('toolsChecklistModal');

    // Add event listener to the confirm button
    const confirmBtn = document.getElementById('confirmChecklistBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            confirmChecklist(job);
        });
    }

    // Add event listener to the close button
    const closeBtn = document.getElementById('closeChecklistBtn');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            handleCloseChecklist(job);
        });
    }

    // Prevent modal from being closed by clicking outside or pressing ESC
    modal.addEventListener('hide.bs.modal', function(event) {
        // Only allow modal to close if checklist is confirmed or closed with the close button
        if (!event.target.getAttribute('data-checklist-confirmed') && !event.target.getAttribute('data-checklist-closed')) {
            event.preventDefault();
            // Show a message explaining why the modal can't be closed
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Checklist Required',
                    text: 'You must complete the checklist before proceeding to job details.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
            }
        }
    });

    // Show the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();

    // Load the checklist content
    loadChecklistContent(job);
}

/**
 * Load the checklist content
 * @param {Object} job - The job order object
 */
function loadChecklistContent(job) {
    console.log('Loading checklist content for job ID:', job.job_order_id);

    // Fetch tools and equipment data
    fetch('../get_tools_equipment.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Failed to load tools and equipment');
            }

            // Render the checklist
            renderChecklist(data.data);
        })
        .catch(error => {
            console.error('Error loading checklist content:', error);

            // Show error message
            const checklistContent = document.getElementById('checklistContent');
            if (checklistContent) {
                checklistContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Failed to load tools and equipment checklist. Please try again.
                    </div>
                    <div class="text-center">
                        <button type="button" class="btn btn-outline-primary" onclick="loadChecklistContent(currentJobForChecklist)">
                            <i class="fas fa-sync-alt me-2"></i>Retry
                        </button>
                    </div>
                `;
            }

            // Enable the confirm button anyway to allow proceeding
            const confirmBtn = document.getElementById('confirmChecklistBtn');
            if (confirmBtn) {
                confirmBtn.disabled = false;
            }
        });
}

/**
 * Render the checklist with the provided tools data
 * @param {Object} toolsData - The tools and equipment data
 */
function renderChecklist(toolsData) {
    console.log('Rendering checklist with tools data:', Object.keys(toolsData));

    const checklistContent = document.getElementById('checklistContent');
    if (!checklistContent) return;

    // Count total tools
    const totalTools = Object.values(toolsData).reduce((total, tools) => total + tools.length, 0);

    // Update total count
    const totalCountElement = document.querySelector('.total-count');
    if (totalCountElement) {
        totalCountElement.textContent = totalTools;
    }

    // If no tools, show a message
    if (totalTools === 0) {
        checklistContent.innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>No tools or equipment found in the system.</strong>
            </div>
            <div class="alert alert-info">
                <p>Please select at least one item from the list below to proceed:</p>
                <div class="form-check mb-2">
                    <input class="form-check-input tool-checkbox" type="checkbox" id="tool-default-1" data-tool-id="default-1" data-category="default">
                    <label class="form-check-label" for="tool-default-1">
                        <strong>Basic Pest Control Kit</strong>
                        <small class="text-muted d-block">Includes sprayer, gloves, and basic tools</small>
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input tool-checkbox" type="checkbox" id="tool-default-2" data-tool-id="default-2" data-category="default">
                    <label class="form-check-label" for="tool-default-2">
                        <strong>Safety Equipment</strong>
                        <small class="text-muted d-block">Includes mask, goggles, and protective clothing</small>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input tool-checkbox" type="checkbox" id="tool-default-3" data-tool-id="default-3" data-category="default">
                    <label class="form-check-label" for="tool-default-3">
                        <strong>Inspection Tools</strong>
                        <small class="text-muted d-block">Includes flashlight, mirror, and measuring tools</small>
                    </label>
                </div>
            </div>
        `;

        // Add event listeners to these default checkboxes
        const defaultCheckboxes = document.querySelectorAll('.tool-checkbox');
        defaultCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateChecklistProgress);
        });

        // Update progress
        updateChecklistProgress();

        return;
    }

    // Create HTML for the checklist
    let html = '';

    // Define category icons
    const categoryIcons = {
        'General Pest Control': 'fa-spray-can',
        'Termite': 'fa-bug',
        'Termite Treatment': 'fa-house-damage',
        'Weed Control': 'fa-seedling',
        'Bed Bugs': 'fa-bed',
        'default': 'fa-tools'
    };

    // Add each category
    for (const [category, tools] of Object.entries(toolsData)) {
        const icon = categoryIcons[category] || categoryIcons['default'];
        const categoryId = category.toLowerCase().replace(/\s+/g, '-');

        html += `
            <div class="card mb-3 checklist-category" data-category="${categoryId}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas ${icon} me-2"></i>${category}
                        <span class="badge bg-secondary ms-2">${tools.length}</span>
                    </h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary check-all-btn" data-category="${categoryId}">
                            <i class="fas fa-check-square me-1"></i>Check All
                        </button>
                        <button class="btn btn-sm btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#category-${categoryId}">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>
                <div class="collapse show" id="category-${categoryId}">
                    <div class="card-body">
                        <div class="row">
                            ${tools.map(tool => `
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input tool-checkbox" type="checkbox" id="tool-${tool.id}" data-tool-id="${tool.id}" data-category="${categoryId}">
                                        <label class="form-check-label" for="tool-${tool.id}">
                                            ${tool.name}
                                            ${tool.description ? `<small class="text-muted d-block">${tool.description}</small>` : ''}
                                        </label>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Set the HTML
    checklistContent.innerHTML = html;

    // Add event listeners to checkboxes
    const checkboxes = document.querySelectorAll('.tool-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateChecklistProgress);
    });

    // Add event listeners to "Check All" buttons
    const checkAllButtons = document.querySelectorAll('.check-all-btn');
    checkAllButtons.forEach(button => {
        button.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            toggleCategoryCheckboxes(category);
        });
    });

    // Initial progress update
    updateChecklistProgress();
}

/**
 * Update the checklist progress
 */
function updateChecklistProgress() {
    const checkboxes = document.querySelectorAll('.tool-checkbox');
    const checkedCount = Array.from(checkboxes).filter(checkbox => checkbox.checked).length;
    const totalCount = checkboxes.length;

    // Update progress bar
    const progressBar = document.querySelector('.progress-bar');
    if (progressBar) {
        const progress = totalCount > 0 ? (checkedCount / totalCount) * 100 : 0;
        progressBar.style.width = `${progress}%`;
        progressBar.setAttribute('aria-valuenow', progress);
    }

    // Update count text
    const countElement = document.querySelector('.checked-count');
    if (countElement) {
        countElement.textContent = checkedCount;
    }

    // Enable/disable confirm button
    const confirmBtn = document.getElementById('confirmChecklistBtn');
    if (confirmBtn) {
        confirmBtn.disabled = checkedCount === 0;
    }
}

/**
 * Toggle all checkboxes in a category
 * @param {string} category - The category ID
 */
function toggleCategoryCheckboxes(category) {
    const checkboxes = document.querySelectorAll(`.tool-checkbox[data-category="${category}"]`);
    const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);

    // Toggle checkboxes
    checkboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
    });

    // Update button text
    const button = document.querySelector(`.check-all-btn[data-category="${category}"]`);
    if (button) {
        if (allChecked) {
            button.innerHTML = '<i class="fas fa-check-square me-1"></i>Check All';
        } else {
            button.innerHTML = '<i class="fas fa-times-circle me-1"></i>Uncheck All';
        }
    }

    // Update progress
    updateChecklistProgress();
}

/**
 * Handle the close button click on the checklist
 * @param {Object} job - The job order object
 */
function handleCloseChecklist(job) {
    console.log('Close button clicked for job ID:', job.job_order_id);

    // Show confirmation dialog
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Skip Checklist?',
            text: 'Are you sure you want to skip the tools checklist? No tools will be marked as in-use for this job.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Skip Checklist',
            cancelButtonText: 'No, Continue Checklist',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#0d6efd'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mark the modal as closed so it can be dismissed
                const modalElement = document.getElementById('toolsChecklistModal');
                if (modalElement) {
                    modalElement.setAttribute('data-checklist-closed', 'true');
                }

                // Hide the checklist modal
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }

                // Execute the callback function if provided
                if (typeof onChecklistComplete === 'function' && !callbackExecuted) {
                    console.log('Executing callback after skipping checklist');

                    // Set flag to prevent multiple executions
                    callbackExecuted = true;

                    try {
                        // Execute the callback
                        onChecklistComplete();
                    } catch (error) {
                        console.error('Error executing callback after skipping checklist:', error);
                        // Fallback to direct job details modal display
                        if (typeof openJobDetails === 'function' && job) {
                            console.log('Using fallback to openJobDetails');
                            openJobDetails(job);
                        }
                    }
                }
            }
        });
    } else {
        // If SweetAlert is not available, use a standard confirm dialog
        if (confirm('Are you sure you want to skip the tools checklist? No tools will be marked as in-use for this job.')) {
            // Mark the modal as closed so it can be dismissed
            const modalElement = document.getElementById('toolsChecklistModal');
            if (modalElement) {
                modalElement.setAttribute('data-checklist-closed', 'true');
            }

            // Hide the checklist modal
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }

            // Execute the callback function if provided
            if (typeof onChecklistComplete === 'function' && !callbackExecuted) {
                console.log('Executing callback after skipping checklist');

                // Set flag to prevent multiple executions
                callbackExecuted = true;

                try {
                    // Execute the callback
                    onChecklistComplete();
                } catch (error) {
                    console.error('Error executing callback after skipping checklist:', error);
                    // Fallback to direct job details modal display
                    if (typeof openJobDetails === 'function' && job) {
                        console.log('Using fallback to openJobDetails');
                        openJobDetails(job);
                    }
                }
            }
        }
    }
}

/**
 * Confirm the checklist and proceed to job details
 * @param {Object} job - The job order object
 */
function confirmChecklist(job) {
    console.log('Confirming checklist for job ID:', job.job_order_id);

    // Get checked tools
    const checkedTools = Array.from(document.querySelectorAll('.tool-checkbox:checked')).map(checkbox => {
        return {
            id: checkbox.getAttribute('data-tool-id'),
            name: checkbox.nextElementSibling.textContent.trim()
        };
    });

    console.log('Checked tools:', checkedTools);

    // Disable the confirm button
    const confirmBtn = document.getElementById('confirmChecklistBtn');
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    }

    // Save the checklist confirmation
    saveChecklistConfirmation(job, checkedTools)
        .then(() => {
            // Mark the modal as confirmed so it can be closed
            const modalElement = document.getElementById('toolsChecklistModal');
            if (modalElement) {
                modalElement.setAttribute('data-checklist-confirmed', 'true');
            }

            // Hide the checklist modal
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }

            // Show success message
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Checklist Completed',
                    text: 'Tools checklist confirmed. Loading job details...',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    console.log('SweetAlert closed, now executing callback');
                    executeCallbackAfterDelay();
                });
            } else {
                // If SweetAlert is not available, just execute the callback
                console.log('SweetAlert not available, executing callback directly');
                executeCallbackAfterDelay();
            }

            // Helper function to execute the callback after a short delay
            function executeCallbackAfterDelay() {
                // Add a small delay to ensure the modal is fully closed
                setTimeout(() => {
                    // Execute the callback function if provided and not already executed
                    if (typeof onChecklistComplete === 'function' && !callbackExecuted) {
                        console.log('Executing checklist completion callback');
                        console.log('Callback type:', typeof onChecklistComplete);
                        console.log('Callback value:', onChecklistComplete);

                        // Set flag to prevent multiple executions
                        callbackExecuted = true;
                        console.log('Setting callbackExecuted flag to true');

                        try {
                            // Execute the callback
                            onChecklistComplete();
                            console.log('Callback executed successfully');
                        } catch (error) {
                            console.error('Error executing callback:', error);
                            // Fallback to direct job details modal display
                            if (typeof openJobDetails === 'function' && job) {
                                console.log('Using fallback to openJobDetails');
                                openJobDetails(job);
                            }
                        }
                    } else if (callbackExecuted) {
                        console.warn('Callback already executed, skipping duplicate execution');
                    } else {
                        console.warn('No checklist completion callback provided');
                        console.warn('Callback type:', typeof onChecklistComplete);
                        console.warn('Callback value:', onChecklistComplete);

                        // Fallback to direct job details modal display
                        if (typeof openJobDetails === 'function' && job) {
                            console.log('No callback available, using fallback to openJobDetails');
                            openJobDetails(job);
                        } else if (typeof showJobDetailsAfterChecklist === 'function') {
                            console.log('No callback available, using fallback to showJobDetailsAfterChecklist');
                            showJobDetailsAfterChecklist();
                        } else if (typeof showJobDetails === 'function') {
                            console.log('No callback available, using fallback to showJobDetails');
                            showJobDetails(job);
                        } else {
                            console.error('No job details functions available for fallback');
                            alert('Error showing job details. Please refresh the page and try again.');
                        }
                    }
                }, 300); // 300ms delay to ensure modal is fully closed
            }
        })
        .catch(error => {
            console.error('Error saving checklist confirmation:', error);

            // Re-enable the confirm button
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fas fa-check me-2"></i>Confirm & Continue to Job Details';
            }

            // Show error message
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to save checklist confirmation. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
}

/**
 * Save the checklist confirmation to the server
 * @param {Object} job - The job order object
 * @param {Array} checkedTools - The checked tools
 * @returns {Promise} A promise that resolves when the confirmation is saved
 */
function saveChecklistConfirmation(job, checkedTools) {
    return new Promise((resolve, reject) => {
        // Prepare data
        const data = {
            checked_items: checkedTools,
            total_items: document.querySelectorAll('.tool-checkbox').length,
            checked_count: checkedTools.length,
            job_order_id: job.job_order_id
        };

        // Send request
        fetch('../save_checklist_confirmation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(result => {
            if (!result.success) {
                throw new Error(result.error || 'Failed to save checklist confirmation');
            }

            // Set session variable to indicate checklist has been shown
            return fetch('../set_checklist_shown.php', { method: 'POST' });
        })
        .then(response => {
            if (!response.ok) {
                console.warn('Failed to set checklist shown status, but continuing anyway');
            }
            resolve();
        })
        .catch(error => {
            console.error('Error in saveChecklistConfirmation:', error);
            reject(error);
        });
    });
}
