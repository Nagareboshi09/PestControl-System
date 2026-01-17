/**
 * Simple Job Handler
 * A simplified approach to handling job card clicks and the checklist flow
 */

// Global variables
let currentJobData = null;
let checklistModalInstance = null;

/**
 * Initialize the simple job handler
 * This should be called when the document is ready
 */
function initializeSimpleJobHandler() {
    // Check if already initialized using the global flag
    if (window.jobHandlersInitialized) {
        console.log('Job handlers already initialized, skipping simple-job-handler initialization');
        return;
    }

    console.log('Initializing simple job handler');

    // Add click handlers to all job cards
    addJobCardClickHandlers();

    // Set the global flag to prevent multiple initializations
    window.jobHandlersInitialized = true;

    // Log initialization
    console.log('Simple job handler initialized');
}

/**
 * Add click handlers to all job cards
 */
function addJobCardClickHandlers() {
    // Get all job cards
    const jobCards = document.querySelectorAll('.job-card');
    console.log(`Found ${jobCards.length} job cards`);

    // Add click handler to each card - DO NOT CLONE to avoid losing other event handlers
    jobCards.forEach((card, index) => {
        // Add our click handler directly without cloning
        card.addEventListener('click', function(event) {
            console.log(`Job card ${index + 1} clicked via simple handler`);
            handleJobCardClick.call(this, event);
        });

        console.log(`Added click handler to job card ${index + 1}`);
    });

    // Also add a global click handler as a fallback
    document.addEventListener('click', function(event) {
        // Check if the clicked element or any of its parents is a job card
        let target = event.target;
        while (target && target !== document) {
            if (target.classList && target.classList.contains('job-card')) {
                // Only handle if it has the data-job attribute
                if (target.hasAttribute('data-job')) {
                    console.log('Job card clicked via global handler');
                    handleJobCardClick.call(target, event);
                    break;
                }
            }
            target = target.parentNode;
        }
    });
}

/**
 * Handle job card click
 * @param {Event} event - The click event
 */
function handleJobCardClick(event) {
    // Prevent default behavior
    event.preventDefault();

    console.log('Job card clicked via handleJobCardClick');

    // Add a visual indicator that the card was clicked
    this.classList.add('clicked-card');

    // First try to get job ID and client name from data attributes as fallback
    const jobId = this.getAttribute('data-job-id');
    const clientName = this.getAttribute('data-client-name');

    // Get the job data
    let jobDataString = this.getAttribute('data-job');
    console.log('Job data string length:', jobDataString ? jobDataString.length : 0);
    console.log('Job data string preview:', jobDataString ? jobDataString.substring(0, 50) + '...' : 'null');

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
        alert('No job data found. Please refresh the page and try again.');
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

            alert('Invalid job data. Please refresh the page and try again.');
            return;
        }

        console.log('Job data parsed successfully:', jobData.job_order_id);

        // Store the job data globally
        currentJobData = jobData;

        // Show the checklist
        showChecklist(jobData);
    } catch (error) {
        console.error('Error handling job card click:', error);
        console.error('Job data string:', jobDataString);

        // Try to display the first 100 characters and last 100 characters of the data
        if (jobDataString && jobDataString.length > 200) {
            console.error('Job data start:', jobDataString.substring(0, 100));
            console.error('Job data end:', jobDataString.substring(jobDataString.length - 100));
        }

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

        alert('Error processing job data. Please refresh the page and try again.');
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

    // Log the first and last 50 characters of the string for debugging
    console.log('Parsing JSON string length:', jsonString.length);
    console.log('JSON string start:', jsonString.substring(0, 50));
    console.log('JSON string end:', jsonString.substring(jsonString.length - 50));

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

                // Last resort: try to extract a valid JSON object using regex
                try {
                    const extracted = extractJsonObject(jsonString);
                    if (extracted) {
                        console.log('Extracted JSON object using regex, trying to parse');
                        return JSON.parse(extracted);
                    }
                } catch (extractionError) {
                    console.error('Error extracting and parsing JSON:', extractionError);
                }

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
 * Extract a valid JSON object from a string using regex
 * @param {string} str - The string to extract from
 * @returns {string|null} The extracted JSON object string or null if not found
 */
function extractJsonObject(str) {
    // Try to find a JSON object in the string
    const matches = str.match(/(\{.*\})/s);
    if (matches && matches[0]) {
        return matches[0];
    }
    return null;
}

/**
 * Show the checklist for a job
 * @param {Object} jobData - The job data
 */
function showChecklist(jobData) {
    console.log('Showing checklist for job:', jobData.job_order_id);

    // Check if the showChecklistForJob function is available
    if (typeof showChecklistForJob === 'function') {
        console.log('Using existing showChecklistForJob function');
        // Use the existing function with our callback
        showChecklistForJob(jobData, function() {
            console.log('Checklist completed, showing job details');
            showJobDetails(jobData);
        });
    } else {
        console.log('showChecklistForJob function not found, trying to load checklist-handler.js');

        // Try to load the checklist handler script
        loadScript('js/checklist-handler.js')
            .then(() => {
                if (typeof showChecklistForJob === 'function') {
                    console.log('Successfully loaded checklist-handler.js, using showChecklistForJob');
                    // Use the loaded function with our callback
                    showChecklistForJob(jobData, function() {
                        console.log('Checklist completed, showing job details');
                        showJobDetails(jobData);
                    });
                } else {
                    console.warn('showChecklistForJob function still not available after loading script');
                    // Create a simple checklist modal
                    createSimpleChecklistModal(jobData);
                }
            })
            .catch(error => {
                console.error('Failed to load checklist-handler.js:', error);
                // Create a simple checklist modal
                createSimpleChecklistModal(jobData);
            });
    }
}

/**
 * Load a script dynamically
 * @param {string} src - The script source URL
 * @returns {Promise} A promise that resolves when the script is loaded
 */
function loadScript(src) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = () => resolve();
        script.onerror = (error) => reject(error);
        document.head.appendChild(script);
    });
}

/**
 * Create a simple checklist modal
 * @param {Object} jobData - The job data
 */
function createSimpleChecklistModal(jobData) {
    console.log('Creating simple checklist modal');

    // Check if any checklist modal already exists and remove it
    const existingModal = document.querySelector('#toolsChecklistModal, #simpleChecklistModal');
    if (existingModal) {
        console.log('Removing existing modal before creating a simple checklist modal');

        // If it's a Bootstrap modal instance, hide it first
        const bsModal = bootstrap.Modal.getInstance(existingModal);
        if (bsModal) {
            bsModal.hide();

            // Remove the modal after it's hidden
            existingModal.addEventListener('hidden.bs.modal', function() {
                existingModal.remove();
                continueCreatingSimpleModal();
            }, { once: true });
            return;
        } else {
            // If no Bootstrap modal instance, just remove it
            existingModal.remove();
        }
    }

    continueCreatingSimpleModal();

    function continueCreatingSimpleModal() {
        // Create modal HTML
        const modalHTML = `
        <div class="modal fade" id="simpleChecklistModal" tabindex="-1" aria-labelledby="checklistModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="checklistModalLabel">
                            <i class="fas fa-tools me-2"></i>Tools & Equipment Checklist
                        </h5>
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

                // Show job details
                showJobDetails(jobData);
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

    // Check if the openJobDetails function is available
    if (typeof openJobDetails === 'function') {
        // Use the existing function
        openJobDetails(jobData);
    } else {
        // Show a simple alert
        alert(`Job details for job #${jobData.job_order_id} would be shown here.`);
    }
}

// Initialize when the document is ready, but only if not already initialized
document.addEventListener('DOMContentLoaded', function() {
    // Check if already initialized
    if (!window.jobHandlersInitialized) {
        console.log('Initializing simple job handler from DOMContentLoaded event');
        initializeSimpleJobHandler();
    } else {
        console.log('Job handlers already initialized, skipping initialization from DOMContentLoaded event');
    }
});
