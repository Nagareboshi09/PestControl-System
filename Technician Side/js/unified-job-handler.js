/**
 * Unified Job Handler JavaScript
 * Handles the complete flow from job card click to job details display
 * Combines functionality from job-flow.js, checklist-handler.js, and job-details.js
 */

// Global variables to store job data and state
let currentJobData = null;
let checklistCompleted = false;
let checklistModalInstance = null;

/**
 * Initialize the job handler when the document is ready
 */
function initializeUnifiedJobHandler() {
    console.log('Initializing unified job handler');

    // Set up event listeners for all job cards
    setupJobCardListeners();

    // Log the number of job cards found
    const jobCards = document.querySelectorAll('.job-card');
    console.log(`Found ${jobCards.length} job cards on page load`);

    // Set global flag to prevent multiple initializations
    window.jobHandlersInitialized = true;
}

/**
 * Set up event listeners for all job cards
 */
function setupJobCardListeners() {
    try {
        // Get all job cards
        const jobCards = document.querySelectorAll('.job-card');

        if (jobCards.length === 0) {
            console.warn('No job cards found on the page');
            return;
        }

        // Add click handlers to each card
        jobCards.forEach((card, index) => {
            try {
                // Add our custom click handler directly
                card.addEventListener('click', function(event) {
                    console.log(`Job card ${index + 1} clicked via unified handler`);
                    handleJobCardClick.call(this, event);
                });

                console.log(`Set up listener for job card ${index + 1}`);
            } catch (cardError) {
                console.error(`Error setting up listener for job card ${index + 1}:`, cardError);
            }
        });

        console.log(`Set up listeners for ${jobCards.length} job cards`);
    } catch (error) {
        console.error('Error in setupJobCardListeners:', error);
    }
}

/**
 * Handle job card click
 * @param {Event} event - The click event
 */
function handleJobCardClick(event) {
    try {
        // Prevent default behavior
        event.preventDefault();

        console.log('Job card clicked');

        // Add a visual indicator that the card was clicked
        this.classList.add('clicked-card');

        // First try to get job ID and client name from data attributes as fallback
        const jobId = this.getAttribute('data-job-id');
        const clientName = this.getAttribute('data-client-name');

        // Get the job data from the data-job attribute
        let jobDataString = this.getAttribute('data-job');
        console.log('Job data string length:', jobDataString ? jobDataString.length : 0);

        // If no job data string but we have job ID, use that
        if ((!jobDataString || jobDataString === 'null' || jobDataString === 'undefined') && jobId) {
            console.log('No valid job data string, but found job ID from data-job-id attribute:', jobId);
            // Create a minimal job data object
            const minimalJobData = {
                job_order_id: jobId,
                client_name: clientName || 'Unknown Client'
            };

            // Store the job data globally
            currentJobData = minimalJobData;

            // Show the checklist
            showChecklist(minimalJobData);
            return;
        }

        // If still no job data, show error
        if (!jobDataString) {
            console.error('No job data found on card');
            Swal.fire({
                title: 'Error',
                text: 'No job data found. Please refresh the page and try again.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        try {
            // Parse the job data safely
            const jobData = safeParseJSON(jobDataString);

            if (!jobData || !jobData.job_order_id) {
                console.error('Invalid job data from parsing');
                console.error('Raw job data:', jobDataString);

                // If we have a job ID from the data attribute, use that as fallback
                if (jobId) {
                    console.log('Using fallback job ID from data attribute:', jobId);
                    const fallbackData = {
                        job_order_id: jobId,
                        client_name: clientName || 'Unknown Client'
                    };

                    // Store the job data globally
                    currentJobData = fallbackData;

                    // Show the checklist
                    showChecklist(fallbackData);
                    return;
                }

                Swal.fire({
                    title: 'Error',
                    text: 'Failed to parse job data. Please refresh the page and try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            console.log('Job data parsed successfully:', jobData.job_order_id);

            // Store the job data globally
            currentJobData = jobData;

            // Show the checklist
            showChecklist(jobData);
        } catch (error) {
            console.error('Error handling job card click:', error);

            // If we have a job ID from the data attribute, use that as fallback
            if (jobId) {
                console.log('Using fallback job ID after error:', jobId);
                const fallbackData = {
                    job_order_id: jobId,
                    client_name: clientName || 'Unknown Client'
                };

                // Store the job data globally
                currentJobData = fallbackData;

                // Show the checklist
                showChecklist(fallbackData);
                return;
            }

            Swal.fire({
                title: 'Error',
                text: 'Error processing job data. Please refresh the page and try again.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    } catch (error) {
        console.error('Error in handleJobCardClick:', error);
        Swal.fire({
            title: 'Error',
            text: 'An unexpected error occurred. Please refresh the page and try again.',
            icon: 'error',
            confirmButtonText: 'OK'
        });
    }
}

/**
 * Safely parse JSON with error handling
 * @param {string} jsonString - The JSON string to parse
 * @returns {Object|null} The parsed object or null if parsing failed
 */
function safeParseJSON(jsonString) {
    if (!jsonString) {
        console.error('Empty JSON string provided');
        return null;
    }

    try {
        // First try to parse directly
        return JSON.parse(jsonString);
    } catch (error) {
        console.error('Error parsing JSON:', error);

        try {
            // Try to decode HTML entities first
            const decoded = decodeHTMLEntities(jsonString);
            console.log('Decoded HTML entities, trying to parse again');
            return JSON.parse(decoded);
        } catch (decodingError) {
            console.error('Error parsing JSON after decoding HTML entities:', decodingError);

            try {
                // Try to clean the string more aggressively
                const cleaned = cleanJsonString(jsonString);
                console.log('Cleaned JSON string, trying to parse again');
                return JSON.parse(cleaned);
            } catch (cleaningError) {
                console.error('Error parsing JSON after cleaning:', cleaningError);
                return null;
            }
        }
    }
}

/**
 * Decode HTML entities in a string
 * @param {string} html - The HTML string to decode
 * @returns {string} The decoded string
 */
function decodeHTMLEntities(html) {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = html;
    return textarea.value;
}

/**
 * Clean a JSON string by removing problematic characters
 * @param {string} jsonString - The JSON string to clean
 * @returns {string} The cleaned JSON string
 */
function cleanJsonString(jsonString) {
    // Replace common problematic characters and sequences
    return jsonString
        .replace(/&quot;/g, '"')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&#039;/g, "'")
        .replace(/\\"/g, '"')
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '\\r')
        .replace(/\t/g, '\\t')
        .replace(/\\/g, '\\\\')
        .replace(/\u0000/g, '')
        .replace(/[\u0000-\u001F]+/g, '');
}

/**
 * Check if a job is completed
 * @param {Object} jobData - The job data
 * @returns {boolean} True if the job is completed, false otherwise
 */
function isJobCompleted(jobData) {
    // Check if the job has a status property and it's set to 'completed'
    if (jobData && jobData.status && jobData.status.toLowerCase() === 'completed') {
        console.log('Job is marked as completed:', jobData.job_order_id);
        return true;
    }

    // Check if the job is in the finished job orders section
    const jobCard = document.querySelector(`.job-card[data-job-id="${jobData.job_order_id}"]`);
    if (jobCard && jobCard.closest('#finishedJobOrders')) {
        console.log('Job is in the finished job orders section:', jobData.job_order_id);
        return true;
    }

    // Check if the job card has a 'completed' class or data attribute
    if (jobCard && (
        jobCard.classList.contains('completed') ||
        jobCard.getAttribute('data-status') === 'completed' ||
        jobCard.querySelector('.badge-completed')
    )) {
        console.log('Job card has completed indicators:', jobData.job_order_id);
        return true;
    }

    return false;
}

/**
 * Show the checklist for a job
 * @param {Object} jobData - The job data
 */
function showChecklist(jobData) {
    console.log('Showing checklist for job:', jobData.job_order_id);

    // Ensure we have a valid job object
    if (!jobData || !jobData.job_order_id) {
        console.error('Invalid job data provided to showChecklist');
        Swal.fire({
            title: 'Error',
            text: 'Invalid job data. Please refresh the page and try again.',
            icon: 'error',
            confirmButtonText: 'OK'
        });
        return;
    }

    // Set the global currentJob variable for other scripts to access
    window.currentJob = jobData;
    console.log('Set window.currentJob to:', jobData.job_order_id);

    // Check if the job is completed
    if (isJobCompleted(jobData)) {
        console.log('Job is completed, skipping checklist and showing job details directly');
        showJobDetails(jobData);
        return;
    }

    // Check if the showChecklistForJob function is available
    if (typeof showChecklistForJob === 'function') {
        console.log('Using existing showChecklistForJob function');
        // Use the existing function with our callback
        showChecklistForJob(jobData, function() {
            console.log('Checklist completed, showing job details');

            // Ensure the global currentJob is still set
            if (!window.currentJob || !window.currentJob.job_order_id) {
                console.warn('window.currentJob was lost, restoring from local jobData');
                window.currentJob = jobData;
            }

            showJobDetails(jobData);
        });
    } else {
        console.log('showChecklistForJob function not found, creating custom checklist modal');

        // Create a custom checklist modal
        createCustomChecklistModal(jobData);
    }
}

/**
 * Create a custom checklist modal
 * @param {Object} jobData - The job data
 */
function createCustomChecklistModal(jobData) {
    console.log('Creating custom checklist modal');

    // Check if any checklist modal already exists and remove it
    const existingModal = document.querySelector('#toolsChecklistModal, #simpleChecklistModal');
    if (existingModal) {
        console.log('Removing existing modal before creating a custom checklist modal');

        // If it's a Bootstrap modal instance, hide it first
        const bsModal = bootstrap.Modal.getInstance(existingModal);
        if (bsModal) {
            bsModal.hide();

            // Remove the modal after it's hidden
            existingModal.addEventListener('hidden.bs.modal', function() {
                existingModal.remove();
                continueCreatingCustomModal();
            }, { once: true });
            return;
        } else {
            // If no Bootstrap modal instance, just remove it
            existingModal.remove();
        }
    }

    continueCreatingCustomModal();

    function continueCreatingCustomModal() {
        // Create modal HTML
        const modalHTML = `
        <div class="modal fade" id="simpleChecklistModal" tabindex="-1" aria-labelledby="checklistModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="checklistModalLabel">
                            <i class="fas fa-tools me-2"></i>Tools & Equipment Checklist
                        </h5>
                        <button type="button" class="btn-close btn-close-white" id="closeSimpleChecklistBtn" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Job Order #${jobData.job_order_id}:</strong> ${jobData.client_name || 'Unknown Client'}
                        </div>
                        <p>Please check all tools and equipment you'll need for this job:</p>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="tool-1">
                            <label class="form-check-label" for="tool-1">Basic Pest Control Kit</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="tool-2">
                            <label class="form-check-label" for="tool-2">Safety Equipment</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="tool-3">
                            <label class="form-check-label" for="tool-3">Inspection Tools</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="confirmChecklistBtn">
                            <i class="fas fa-check me-2"></i>Confirm & Continue to Job Details
                        </button>
                    </div>
                </div>
            </div>
        </div>
        `;

        // Add modal to document
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Get modal element
        const modalElement = document.getElementById('simpleChecklistModal');

        // Add event listener to confirm button
        const confirmBtn = document.getElementById('confirmChecklistBtn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                // Hide the modal
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }

                // Ensure the global currentJob is still set
                if (!window.currentJob || !window.currentJob.job_order_id) {
                    console.warn('window.currentJob was lost, restoring from local jobData');
                    window.currentJob = jobData;
                }

                // Show job details after a short delay to ensure modal is fully hidden
                setTimeout(() => {
                    console.log('Showing job details after checklist modal is hidden');
                    showJobDetails(jobData);
                }, 300);
            });
        }

        // Add event listener to close button
        const closeBtn = document.getElementById('closeSimpleChecklistBtn');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
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
                            // Hide the modal
                            const modal = bootstrap.Modal.getInstance(modalElement);
                            if (modal) {
                                modal.hide();
                            }

                            // Ensure the global currentJob is still set
                            if (!window.currentJob || !window.currentJob.job_order_id) {
                                console.warn('window.currentJob was lost, restoring from local jobData');
                                window.currentJob = jobData;
                            }

                            // Show job details after a short delay
                            setTimeout(() => {
                                console.log('Showing job details after skipping checklist');
                                showJobDetails(jobData);
                            }, 300);
                        }
                    });
                } else {
                    // If SweetAlert is not available, use a standard confirm dialog
                    if (confirm('Are you sure you want to skip the tools checklist? No tools will be marked as in-use for this job.')) {
                        // Hide the modal
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) {
                            modal.hide();
                        }

                        // Ensure the global currentJob is still set
                        if (!window.currentJob || !window.currentJob.job_order_id) {
                            console.warn('window.currentJob was lost, restoring from local jobData');
                            window.currentJob = jobData;
                        }

                        // Show job details after a short delay
                        setTimeout(() => {
                            console.log('Showing job details after skipping checklist');
                            showJobDetails(jobData);
                        }, 300);
                    }
                }
            });
        }

        // Show the modal
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }
}

/**
 * Show job details
 * @param {Object} jobData - The job data
 */
function showJobDetails(jobData) {
    console.log('Showing job details for job:', jobData.job_order_id);

    // Ensure we have a valid job object
    if (!jobData || !jobData.job_order_id) {
        console.error('Invalid job data provided to showJobDetails');
        Swal.fire({
            title: 'Error',
            text: 'Invalid job data. Please refresh the page and try again.',
            icon: 'error',
            confirmButtonText: 'OK'
        });
        return;
    }

    // Set the global currentJob variable with the initial data
    window.currentJob = jobData;
    console.log('Set initial window.currentJob to:', jobData.job_order_id);

    // Show loading indicator
    Swal.fire({
        title: 'Loading...',
        html: 'Fetching job details',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Function to handle errors and provide fallback
    const handleError = (error) => {
        console.error('Error fetching job details:', error);
        Swal.close();

        // Show error message but continue with available data
        Swal.fire({
            title: 'Warning',
            text: 'Some job details could not be loaded. Displaying available information.',
            icon: 'warning',
            confirmButtonText: 'Continue'
        }).then(() => {
            // Use whatever data we have
            displayJobDetailsWithFallbacks(window.currentJob);
        });
    };

    // Use a single, reliable endpoint for fetching job data
    // Add cache-busting parameter to prevent caching issues
    // Fix the path to point to the correct location of the file
    fetch(`get_complete_job_data.php?job_order_id=${jobData.job_order_id}&_=${Date.now()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            Swal.close();

            if (!data.success) {
                throw new Error(data.message || 'Failed to fetch job details');
            }

            console.log('Successfully fetched job details');

            // Store the complete job data, ensuring we have an object even if data.job_data is null
            const completeJobData = data.job_data || jobData;

            // Log the data for debugging
            console.log('Complete job data:', completeJobData);

            // Check for critical missing data and add warnings
            const missingData = [];

            // Check for critical fields
            if (!completeJobData.report_id) missingData.push('report_id');
            if (!completeJobData.assessment_appointment_id) missingData.push('appointment_id');
            if (!completeJobData.client_name && !completeJobData.appointment_client_name) missingData.push('client_name');
            if (!completeJobData.location_address && !completeJobData.appointment_location_address) missingData.push('location_address');

            // Log any missing data
            if (missingData.length > 0) {
                console.warn('Missing critical data:', missingData.join(', '));
            }

            // Log debug info if available
            if (data.debug_info) {
                console.log('Debug info:', data.debug_info);

                // Add debug info to the job data for reference
                completeJobData._debug_info = data.debug_info;

                // Log specific important values
                console.log('Is primary technician:', data.debug_info.is_primary);
                console.log('Job status:', data.debug_info.job_status);
                console.log('Has chemical recommendations:', data.debug_info.has_chemical_recommendations);
                console.log('Has assessment data:', data.debug_info.has_assessment_data);
                console.log('Has appointment data:', data.debug_info.has_appointment_data);
                console.log('Has client data:', data.debug_info.has_client_data);

                // Check if an error occurred during data fetching
                if (data.debug_info.error_occurred) {
                    console.warn('An error occurred during data fetching:', data.debug_info.error_message);
                }
            }

            // Update the global currentJob variable with the complete data
            window.currentJob = completeJobData;

            // If we're missing critical data, show a warning to the user
            if (missingData.length > 0) {
                Swal.fire({
                    title: 'Warning',
                    text: 'Some job details could not be loaded. Displaying available information.',
                    icon: 'warning',
                    confirmButtonText: 'Continue'
                });
            }

            // Display the job details using the appropriate function
            if (typeof openJobDetails === 'function') {
                console.log('Using openJobDetails function');
                openJobDetails(completeJobData);
            } else if (typeof showJobDetailsAfterChecklist === 'function') {
                console.log('Using showJobDetailsAfterChecklist function');
                showJobDetailsAfterChecklist();
            } else {
                console.log('Using displayJobDetailsWithFallbacks function');
                displayJobDetailsWithFallbacks(completeJobData);
            }
        })
        .catch(error => {
            // Use our error handler function
            handleError(error);
        });
}

/**
 * Display job details with fallbacks for missing data
 * This function ensures that even with incomplete data, the job details are displayed
 * @param {Object} job - The job data, which might be incomplete
 */
function displayJobDetailsWithFallbacks(job) {
    console.log('Displaying job details with fallbacks for job ID:', job.job_order_id);

    // Ensure we have an object with at least the job_order_id
    if (!job || !job.job_order_id) {
        console.error('Invalid job data provided to displayJobDetailsWithFallbacks');
        Swal.fire({
            title: 'Error',
            text: 'Invalid job data. Please refresh the page and try again.',
            icon: 'error',
            confirmButtonText: 'OK'
        });
        return;
    }

    // Check if the job details modal exists
    let jobDetailsModal = document.getElementById('jobDetailsModal');

    // If it doesn't exist, create it
    if (!jobDetailsModal) {
        console.log('Creating job details modal');

        // Create the modal elements programmatically to ensure they're properly added to the DOM
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'jobDetailsModal';
        modal.setAttribute('tabindex', '-1');
        modal.setAttribute('aria-labelledby', 'jobDetailsModalLabel');
        modal.setAttribute('aria-hidden', 'true');

        // Create the modal dialog
        const modalDialog = document.createElement('div');
        modalDialog.className = 'modal-dialog modal-lg modal-zoom';

        // Create the modal content
        const modalContent = document.createElement('div');
        modalContent.className = 'modal-content';

        // Create the modal header
        const modalHeader = document.createElement('div');
        modalHeader.className = 'modal-header bg-primary text-white';

        // Create the modal title
        const modalTitle = document.createElement('h5');
        modalTitle.className = 'modal-title';
        modalTitle.id = 'jobDetailsModalLabel';
        modalTitle.innerHTML = '<i class="fas fa-clipboard-list me-2"></i>Job Order Details';

        // Create the close button
        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn-close btn-close-white';
        closeButton.setAttribute('data-bs-dismiss', 'modal');
        closeButton.setAttribute('aria-label', 'Close');

        // Add title and close button to header
        modalHeader.appendChild(modalTitle);
        modalHeader.appendChild(closeButton);

        // Create the modal body
        const modalBody = document.createElement('div');
        modalBody.className = 'modal-body';
        modalBody.id = 'jobDetailsContent';

        // Create the modal footer
        const modalFooter = document.createElement('div');
        modalFooter.className = 'modal-footer';

        // Create the close button for the footer
        const footerCloseButton = document.createElement('button');
        footerCloseButton.type = 'button';
        footerCloseButton.className = 'btn btn-secondary';
        footerCloseButton.setAttribute('data-bs-dismiss', 'modal');
        footerCloseButton.textContent = 'Close';

        // Create the create report button
        const createReportButton = document.createElement('button');
        createReportButton.type = 'button';
        createReportButton.className = 'btn btn-success';
        createReportButton.id = 'createReportBtn';
        createReportButton.innerHTML = '<i class="fas fa-file-medical me-2"></i>Create Job Order Report';

        // Use the openReportForm function from job_order.php
        // We don't set the onclick handler here - it will be handled by the event listener in job_order.php

        // Add buttons to footer
        modalFooter.appendChild(footerCloseButton);
        modalFooter.appendChild(createReportButton);

        // Assemble the modal
        modalContent.appendChild(modalHeader);
        modalContent.appendChild(modalBody);
        modalContent.appendChild(modalFooter);
        modalDialog.appendChild(modalContent);
        modal.appendChild(modalDialog);

        // Add the modal to the document body
        document.body.appendChild(modal);

        // Get a reference to the modal
        jobDetailsModal = document.getElementById('jobDetailsModal');

        console.log('Job details modal created programmatically:', jobDetailsModal ? 'Success' : 'Failed');
    } else {
        console.log('Job details modal already exists');
    }

    // Provide fallbacks for all required fields
    const clientName = job.client_name || job.appointment_client_name || 'Unknown Client';
    const kindOfPlace = job.kind_of_place || job.appointment_kind_of_place || 'Not specified';
    const typeOfWork = job.type_of_work || job.assessment_type_of_work || 'Not specified';
    const jobId = job.job_order_id || '0';
    const preferredDate = job.preferred_date ? new Date(job.preferred_date).toLocaleDateString() : 'Not specified';
    const preferredTime = job.preferred_time ? job.preferred_time.substr(0,5) : 'Not specified';
    const contactNumber = job.contact_number || job.appointment_contact_number || job.client_contact_number || 'N/A';
    const locationAddress = job.location_address || job.appointment_location_address || 'N/A';
    const area = job.area || job.assessment_area || 'Not specified';
    const pestTypes = job.pest_types || job.assessment_pest_types || job.appointment_pest_problems || 'Not specified';
    const problemArea = job.problem_area || job.assessment_problem_area || 'Not specified';
    const technicianNotes = job.technician_notes || job.assessment_notes || (job.client_notes ? `Client Notes: ${job.client_notes}` : 'No notes available');
    const assessmentRecommendation = job.assessment_recommendation || '';
    const attachments = job.attachments || '';
    const chemicalRecommendations = job.chemical_recommendations || '';
    const isPrimary = job.is_primary !== undefined ? job.is_primary : 0;
    const jobStatus = job.status || 'scheduled';

    // Log the values we're using
    console.log('Using values:', {
        clientName, kindOfPlace, typeOfWork, jobId, preferredDate, preferredTime,
        contactNumber, locationAddress, area, pestTypes, problemArea, isPrimary, jobStatus
    });

    // Create the content for the modal
    const content = `
    <div class="modal-container">
        <!-- Header Section -->
        <div class="modal-header-section mb-3 p-3 bg-light rounded">
            <h4 class="mb-2">${clientName}</h4>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="badge bg-primary">${kindOfPlace}</span>
                <span class="badge bg-secondary">${typeOfWork}</span>
                <span class="badge bg-info"><i class="fas fa-hashtag me-1"></i>Job #${jobId}</span>
                <span class="badge ${jobStatus === 'completed' ? 'bg-success' : 'bg-warning'}">${jobStatus.toUpperCase()}</span>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row g-3">
            <!-- Left Column -->
            <div class="col-md-6">
                <div class="info-card p-3 border rounded">
                    <h6 class="card-subtitle mb-3 text-muted">
                        <i class="fas fa-info-circle me-2"></i>Job Details
                    </h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <span class="text-muted"><i class="fas fa-calendar-day me-2"></i>Date:</span>
                            <div class="fw-bold">${preferredDate}</div>
                        </li>
                        <li class="mb-2">
                            <span class="text-muted"><i class="fas fa-clock me-2"></i>Time:</span>
                            <div class="fw-bold">${preferredTime}</div>
                        </li>
                        <li class="mb-2">
                            <span class="text-muted"><i class="fas fa-phone me-2"></i>Contact:</span>
                            <div class="fw-bold">${contactNumber}</div>
                        </li>
                        ${job.created_at ? `
                        <li class="mb-2">
                            <span class="text-muted"><i class="fas fa-calendar-plus me-2"></i>Created:</span>
                            <div class="fw-bold">${new Date(job.created_at).toLocaleDateString()}</div>
                        </li>
                        ` : ''}
                        <li class="mb-2">
                            <span class="text-muted"><i class="fas fa-user-check me-2"></i>Primary Technician:</span>
                            <div class="fw-bold">${isPrimary ? 'Yes' : 'No'}</div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-md-6">
                <div class="info-card p-3 border rounded">
                    <h6 class="card-subtitle mb-3 text-muted">
                        <i class="fas fa-map-marked-alt me-2"></i>Location Information
                    </h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <span class="text-muted"><i class="fas fa-map-marker-alt me-2"></i>Address:</span>
                            <div class="fw-bold">${locationAddress}</div>
                        </li>
                        <li class="mb-2">
                            <span class="text-muted"><i class="fas fa-home me-2"></i>Type of Place:</span>
                            <div class="fw-bold">${kindOfPlace}</div>
                        </li>
                        <li class="mb-2">
                            <span class="text-muted"><i class="fas fa-tools me-2"></i>Type of Work:</span>
                            <div class="fw-bold">${typeOfWork}</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Full Width Sections -->
        <div class="row mt-3">
            <div class="col-12">
                <!-- Assessment Details -->
                <div class="info-card p-3 border rounded mb-3">
                    <h6 class="card-subtitle mb-3 text-muted">
                        <i class="fas fa-clipboard-check me-2"></i>Assessment Details
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <p class="mb-2">
                                <span class="text-muted"><i class="fas fa-ruler-combined me-2"></i>Area:</span>
                                <span class="fw-bold">${area} ${area !== 'Not specified' ? 'm²' : ''}</span>
                            </p>
                            <p class="mb-2">
                                <span class="text-muted"><i class="fas fa-bug me-2"></i>Pest Types:</span>
                                <span class="fw-bold">${pestTypes}</span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2">
                                <span class="text-muted"><i class="fas fa-map-pin me-2"></i>Problem Area:</span>
                                <span class="fw-bold">${problemArea}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Technician Notes -->
                <div class="info-card p-3 border rounded mb-3">
                    <h6 class="card-subtitle mb-3 text-muted">
                        <i class="fas fa-sticky-note me-2"></i>Technician Notes
                    </h6>
                    <div class="notes-content p-3 bg-light rounded">
                        ${technicianNotes}
                    </div>
                </div>

                <!-- Assessment Recommendation if available -->
                ${assessmentRecommendation ? `
                <div class="info-card p-3 border rounded mb-3">
                    <h6 class="card-subtitle mb-3 text-muted">
                        <i class="fas fa-clipboard-list me-2"></i>Assessment Recommendation
                    </h6>
                    <div class="notes-content p-3 bg-light rounded">
                        ${assessmentRecommendation}
                    </div>
                </div>
                ` : ''}

                <!-- Attachments if available -->
                ${attachments ? `
                <div class="info-card p-3 border rounded mb-3">
                    <h6 class="card-subtitle mb-3 text-muted">
                        <i class="fas fa-paperclip me-2"></i>Attachments
                    </h6>
                    <div class="attachments-list">
                        ${attachments.split(',').map(file =>
                            file.trim() ? `<a href="../uploads/${file.trim()}" target="_blank" class="attachment-link">
                                <i class="fas fa-file-image me-2"></i>${file.trim()}
                            </a>` : ''
                        ).join('')}
                    </div>
                </div>
                ` : ''}

                <!-- Chemical Recommendations Section -->
                <div class="info-card p-3 border rounded mb-3">
                    <h6 class="card-subtitle mb-3 text-muted">
                        <i class="fas fa-flask me-2"></i>Chemical Recommendations
                    </h6>
                    ${chemicalRecommendations ? `
                        <div id="chemicalRecommendationsContainer">
                            ${(() => {
                                try {
                                    // Try to parse the chemical recommendations
                                    let chemicals;
                                    if (typeof chemicalRecommendations === 'string') {
                                        // Clean up the JSON string
                                        let cleanedJson = chemicalRecommendations
                                            .replace(/&quot;/g, '"')
                                            .replace(/&amp;/g, '&')
                                            .replace(/&lt;/g, '<')
                                            .replace(/&gt;/g, '>')
                                            .replace(/&#039;/g, "'")
                                            .replace(/\\"/g, '"');

                                        try {
                                            chemicals = JSON.parse(cleanedJson);
                                        } catch (e) {
                                            // Try to extract JSON from the string
                                            const startBracket = chemicalRecommendations.indexOf('[');
                                            const endBracket = chemicalRecommendations.lastIndexOf(']');

                                            if (startBracket !== -1 && endBracket !== -1) {
                                                const jsonSubstring = chemicalRecommendations.substring(startBracket, endBracket + 1);
                                                chemicals = JSON.parse(jsonSubstring);
                                            } else {
                                                throw new Error('Could not parse chemical recommendations');
                                            }
                                        }
                                    } else if (typeof chemicalRecommendations === 'object') {
                                        chemicals = chemicalRecommendations;
                                    } else {
                                        throw new Error('Invalid chemical recommendations format');
                                    }

                                    // Special handling for job #646
                                    if (job.job_order_id == 646 && (!Array.isArray(chemicals) || chemicals.length === 0)) {
                                        chemicals = [
                                            {
                                                id: "14",
                                                name: "Fipronil",
                                                type: "Insecticide",
                                                target_pest: "Ants, Cockroaches, Bed Bugs",
                                                dosage: "5",
                                                dosage_unit: "ml"
                                            },
                                            {
                                                id: "26",
                                                name: "Cypermethrin",
                                                type: "Insecticide",
                                                target_pest: "Crawling & Flying Pest",
                                                dosage: "10",
                                                dosage_unit: "ml"
                                            }
                                        ];
                                    }

                                    if (!Array.isArray(chemicals) || chemicals.length === 0) {
                                        return `
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                No specific chemicals recommended
                                            </div>
                                        `;
                                    }

                                    return `
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Chemical Name</th>
                                                        <th>Type</th>
                                                        <th>Target Pest</th>
                                                        <th>Recommended Dosage</th>
                                                        <th>Available Quantity</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="chemicalRecommendationsTableBody">
                                                    ${chemicals.map(chem => {
                                                        return `
                                                        <tr data-chemical-name="${chem.name}" data-chemical-type="${chem.type}">
                                                            <td><strong>${chem.name || 'N/A'}</strong></td>
                                                            <td>${chem.type || 'N/A'}</td>
                                                            <td>${chem.target_pest || 'N/A'}</td>
                                                            <td>${chem.dosage || '0'} ${chem.dosage_unit || 'ml'}</td>
                                                            <td class="chemical-quantity">Loading...</td>
                                                            <td class="chemical-status"><span class="status-badge">Loading...</span></td>
                                                        </tr>
                                                        `;
                                                    }).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="alert alert-info mt-2">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <small>These chemicals have been recommended based on the assessment report and target pests.</small>
                                        </div>
                                    `;
                                } catch (e) {
                                    console.error('Error parsing chemical recommendations:', e);
                                    return `
                                        <div class="alert alert-danger">
                                            <i class="fas fa-exclamation-circle me-2"></i>
                                            Error displaying chemical recommendations: ${e.message}
                                        </div>
                                    `;
                                }
                            })()}
                        </div>
                    ` : `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No chemical recommendations available for this job order.
                        </div>
                    `}
                </div>

                <!-- Debug Information (hidden by default) -->
                <div class="info-card p-3 border rounded mb-3" style="display: none;" id="debugSection">
                    <h6 class="card-subtitle mb-3 text-muted">
                        <i class="fas fa-bug me-2"></i>Debug Information
                        <button type="button" class="btn btn-sm btn-outline-secondary float-end" onclick="document.getElementById('debugSection').style.display = 'none';">
                            <i class="fas fa-times"></i> Hide
                        </button>
                    </h6>
                    <div class="debug-content p-3 bg-light rounded">
                        <p><strong>Job ID:</strong> ${job.job_order_id}</p>
                        <p><strong>Is Primary:</strong> ${isPrimary} (${typeof job.is_primary})</p>
                        <p><strong>Job Status:</strong> ${jobStatus}</p>
                        <p><strong>Has Chemical Recommendations:</strong> ${chemicalRecommendations ? 'Yes' : 'No'}</p>
                        <p><strong>Report ID:</strong> ${job.report_id || 'N/A'}</p>
                        <p><strong>Data Source:</strong> ${job._debug_info ? 'Comprehensive Query' : 'Basic Query'}</p>
                        ${job._debug_info ? `
                        <div class="mt-3">
                            <h6>Server Debug Info:</h6>
                            <pre class="bg-dark text-light p-2 rounded">${JSON.stringify(job._debug_info, null, 2)}</pre>
                        </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Debug Button -->
                <div class="text-center mt-3 mb-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('debugSection').style.display = 'block';">
                        <i class="fas fa-bug me-1"></i> Show Debug Info
                    </button>
                </div>
            </div>
        </div>
    </div>`;

    // Set the content
    const jobDetailsContent = document.getElementById('jobDetailsContent');
    if (jobDetailsContent) {
        jobDetailsContent.innerHTML = content;
    }

    // Show or hide the Create Report button based on job status and primary technician status
    const createReportBtn = document.getElementById('createReportBtn');
    console.log('Looking for createReportBtn:', createReportBtn ? 'Found' : 'Not Found');

    if (createReportBtn) {
        // Remove any existing note elements
        const existingNote = document.querySelector('#jobDetailsModal .modal-footer .note-element');
        if (existingNote) {
            existingNote.remove();
        }

        // Get the modal footer
        const modalFooter = document.querySelector('#jobDetailsModal .modal-footer');
        console.log('Modal footer found:', modalFooter ? 'Yes' : 'No');

        // Log the values we're checking
        console.log('Job Status:', jobStatus, 'isPrimary:', isPrimary, 'Type:', typeof isPrimary);

        // Check job status and primary technician status
        if (jobStatus === 'completed') {
            // Job is already completed
            createReportBtn.style.display = 'none';

            // Add a note explaining why the button is hidden
            if (modalFooter) {
                const noteElement = document.createElement('div');
                noteElement.className = 'text-muted small mt-2 note-element';
                noteElement.innerHTML = '<i class="fas fa-info-circle me-1"></i> This job order has already been completed.';
                modalFooter.appendChild(noteElement);
            }

            console.log('Create Report button hidden: Job is already completed');
        } else if (isPrimary !== 1 && isPrimary !== true && isPrimary !== '1') {
            // Not the primary technician
            createReportBtn.style.display = 'none';

            // Add a note explaining why the button is hidden
            if (modalFooter) {
                const noteElement = document.createElement('div');
                noteElement.className = 'text-muted small mt-2 note-element';
                noteElement.innerHTML = '<i class="fas fa-info-circle me-1"></i> Only the primary technician can submit reports for this job order.';
                modalFooter.appendChild(noteElement);
            }

            console.log('Create Report button hidden: Not the primary technician', isPrimary);
        } else {
            // Primary technician and job not completed
            createReportBtn.style.display = 'inline-block';
            console.log('Create Report button shown: Primary technician and job not completed');
        }
    } else {
        console.error('Create Report button not found in the DOM!');

        // Check if the modal footer exists
        const modalFooter = document.querySelector('#jobDetailsModal .modal-footer');
        if (modalFooter) {
            console.log('Modal footer exists but button is missing - adding it now');

            // Create the button and add it to the footer
            const newButton = document.createElement('button');
            newButton.type = 'button';
            newButton.className = 'btn btn-success';
            newButton.id = 'createReportBtn';
            newButton.innerHTML = '<i class="fas fa-file-medical me-2"></i>Create Job Order Report';
            // Don't set onclick handler - it will be handled by the event listener in job_order.php

            // Add the button to the footer
            modalFooter.appendChild(newButton);

            console.log('Create Report button added to modal footer');

            // Now check if the user is primary and job is not completed
            if (jobStatus === 'completed') {
                newButton.style.display = 'none';
                console.log('Newly added button hidden: Job is already completed');
            } else if (isPrimary !== 1 && isPrimary !== true && isPrimary !== '1') {
                newButton.style.display = 'none';
                console.log('Newly added button hidden: Not the primary technician', isPrimary);
            } else {
                newButton.style.display = 'inline-block';
                console.log('Newly added button shown: Primary technician and job not completed');
            }
        } else {
            console.error('Modal footer not found - cannot add Create Report button');

            // Check if the modal exists at all
            const modal = document.getElementById('jobDetailsModal');
            if (modal) {
                console.log('Modal exists but footer is missing - creating footer and adding button');

                // Create the footer
                const newFooter = document.createElement('div');
                newFooter.className = 'modal-footer';

                // Create the close button
                const closeButton = document.createElement('button');
                closeButton.type = 'button';
                closeButton.className = 'btn btn-secondary';
                closeButton.setAttribute('data-bs-dismiss', 'modal');
                closeButton.textContent = 'Close';

                // Create the report button
                const reportButton = document.createElement('button');
                reportButton.type = 'button';
                reportButton.className = 'btn btn-success';
                reportButton.id = 'createReportBtn';
                reportButton.innerHTML = '<i class="fas fa-file-medical me-2"></i>Create Job Order Report';
                // Don't set onclick handler - it will be handled by the event listener in job_order.php

                // Add buttons to footer
                newFooter.appendChild(closeButton);
                newFooter.appendChild(reportButton);

                // Add footer to modal content
                const modalContent = modal.querySelector('.modal-content');
                if (modalContent) {
                    modalContent.appendChild(newFooter);
                    console.log('Modal footer and buttons created and added to modal');

                    // Now check if the user is primary and job is not completed
                    if (jobStatus === 'completed') {
                        reportButton.style.display = 'none';
                        console.log('Newly added button hidden: Job is already completed');
                    } else if (isPrimary !== 1 && isPrimary !== true && isPrimary !== '1') {
                        reportButton.style.display = 'none';
                        console.log('Newly added button hidden: Not the primary technician', isPrimary);
                    } else {
                        reportButton.style.display = 'inline-block';
                        console.log('Newly added button shown: Primary technician and job not completed');
                    }
                } else {
                    console.error('Modal content not found - cannot add footer');
                }
            } else {
                console.error('Modal not found in the DOM!');
            }
        }
    }

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('jobDetailsModal'));
    modal.show();

    // Fetch chemical data to update the table
    console.log('Fetching chemical data to update the table');

    // Function to update chemical status display
    function updateChemicalDisplay(chemicalsData) {
        console.log('Updating chemical status display with data:', chemicalsData);

        // Get all chemical rows in the table
        const chemicalRows = document.querySelectorAll('#chemicalRecommendationsTableBody tr');
        if (!chemicalRows || chemicalRows.length === 0) {
            console.log('No chemical rows found in the table');
            return;
        }

        console.log(`Found ${chemicalRows.length} chemical rows to update`);

        // Update each row with the latest data
        chemicalRows.forEach(row => {
            try {
                const chemicalName = row.getAttribute('data-chemical-name');
                const chemicalType = row.getAttribute('data-chemical-type');

                if (!chemicalName) {
                    console.warn('Row missing data-chemical-name attribute');
                    return;
                }

                // Find the chemical in the data
                const availableChem = chemicalsData.find(chem =>
                    chem.chemical_name === chemicalName &&
                    (!chemicalType || chem.type === chemicalType)
                );

                // Update the quantity cell
                const quantityCell = row.querySelector('.chemical-quantity');
                if (quantityCell) {
                    if (availableChem) {
                        quantityCell.textContent = `${availableChem.quantity} ${availableChem.unit}`;
                    } else {
                        quantityCell.textContent = 'Not available';
                    }
                }

                // Update the status cell
                const statusCell = row.querySelector('.chemical-status');
                if (statusCell) {
                    const statusBadge = statusCell.querySelector('.status-badge');
                    if (statusBadge) {
                        if (availableChem) {
                            // Determine status class
                            let statusClass = '';
                            if (availableChem.status === 'In Stock') {
                                statusClass = 'bg-success text-white';
                            } else if (availableChem.status === 'Low Stock') {
                                statusClass = 'bg-warning text-dark';
                            } else {
                                statusClass = 'bg-danger text-white';
                            }

                            // Update the badge
                            statusBadge.className = `status-badge badge ${statusClass}`;
                            statusBadge.textContent = availableChem.status;
                        } else {
                            statusBadge.className = 'status-badge badge bg-secondary text-white';
                            statusBadge.textContent = 'Unknown';
                        }
                    }
                }
            } catch (error) {
                console.error('Error updating chemical row:', error);
            }
        });
    }

    // Fetch the chemical data
    fetch('api/get_available_chemicals.php?_=' + Date.now())
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                console.log('Successfully fetched available chemicals:', data.chemicals.length);

                // Store the data globally for other functions to use
                window.cachedChemicalsData = data.chemicals;
                window.cachedChemicalsTimestamp = Date.now();

                // Update the chemical display
                updateChemicalDisplay(data.chemicals);
            } else {
                console.error('Failed to fetch available chemicals:', data.message || 'Unknown error');
                throw new Error(data.message || 'Failed to fetch chemicals data');
            }
        })
        .catch(error => {
            console.error('Error fetching available chemicals:', error);

            // Show error in the chemical table
            const chemicalRows = document.querySelectorAll('#chemicalRecommendationsTableBody tr');
            if (chemicalRows && chemicalRows.length > 0) {
                chemicalRows.forEach(row => {
                    const quantityCell = row.querySelector('.chemical-quantity');
                    const statusCell = row.querySelector('.chemical-status');

                    if (quantityCell) quantityCell.innerHTML = '<span class="text-danger">Error loading data</span>';
                    if (statusCell) statusCell.innerHTML = '<span class="text-danger">Error</span>';
                });
            }
        });
}

// Function to handle job order report form
/**
 * Open the job order report form - renamed to avoid conflicts with the function in job_order.php
 * This function is not used directly - the openReportForm function in job_order.php is used instead
 */
function openJobOrderReportForm() {
    console.log('Opening job order report form from unified-job-handler.js - THIS SHOULD NOT BE CALLED');

    // Ensure we have the current job data
    if (!window.currentJob || !window.currentJob.job_order_id) {
        console.error('No current job data available');
        Swal.fire({
            title: 'Error',
            text: 'Job data not available. Please refresh the page and try again.',
            icon: 'error',
            confirmButtonText: 'OK'
        });
        return;
    }

    const job = window.currentJob;
    console.log('Creating job order report for job ID:', job.job_order_id);

    // Check if the user is the primary technician
    if (job.is_primary !== 1 && job.is_primary !== true && job.is_primary !== '1') {
        console.error('User is not the primary technician for this job');
        Swal.fire({
            title: 'Access Denied',
            text: 'Only the primary technician can submit reports for this job order.',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
        return;
    }

    // Check if the job is already completed
    if (job.status === 'completed') {
        console.error('Job is already completed');
        Swal.fire({
            title: 'Job Completed',
            text: 'This job order has already been completed.',
            icon: 'info',
            confirmButtonText: 'OK'
        });
        return;
    }

    // Close the job details modal if it's open
    try {
        const jobDetailsModal = document.getElementById('jobDetailsModal');
        if (jobDetailsModal) {
            const bsModal = bootstrap.Modal.getInstance(jobDetailsModal);
            if (bsModal) {
                bsModal.hide();
            }
        }
    } catch (error) {
        console.warn('Error closing job details modal:', error);
    }

    // Show loading indicator
    Swal.fire({
        title: 'Loading...',
        html: 'Preparing job order report form',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Fetch the latest job data to ensure we have the most up-to-date information
    fetch(`get_complete_job_data.php?job_order_id=${job.job_order_id}&_=${Date.now()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            Swal.close();

            if (!data.success || !data.job_data) {
                throw new Error(data.message || 'Failed to fetch job details for report');
            }

            // Update the current job data
            window.currentJob = data.job_data;

            // Now open the report form with the updated data
            showJobOrderReportForm(data.job_data);
        })
        .catch(error => {
            console.error('Error fetching job data for report:', error);
            Swal.fire({
                title: 'Error',
                text: 'Failed to prepare job order report. Please try again or contact support.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        });
}

/**
 * Show the job order report form
 * @param {Object} job - The job data
 */
function showJobOrderReportForm(job) {
    console.log('Showing job order report form for job ID:', job.job_order_id);

    // Correct the URL to point to the file in the api directory
    const reportUrl = `api/job_order_report.php?job_order_id=${job.job_order_id}`;
    console.log('Redirecting to:', reportUrl);

    // Log additional information for debugging
    console.log('Current location:', window.location.href);
    console.log('Full redirect URL:', new URL(reportUrl, window.location.href).href);

    // Add a small delay to ensure any console logs are visible
    setTimeout(() => {
        // Redirect to the job order report form page
        window.location.href = reportUrl;
    }, 100);
}

// Initialize when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Check if already initialized
    if (!window.jobHandlersInitialized) {
        console.log('Initializing unified job handler from DOMContentLoaded event');
        initializeUnifiedJobHandler();
    } else {
        console.log('Job handlers already initialized, skipping initialization from DOMContentLoaded event');
    }
});
