/**
 * Tools and Equipment Checklist JavaScript
 * Improved version with better error handling and callback management
 */

class ToolsChecklist {
    constructor(onConfirmCallback = null, jobOrderId = null) {
        this.modal = null;
        this.tools = {};
        this.totalTools = 0;
        this.checkedTools = 0;
        this.categoryIcons = {
            'General Pest Control': 'fa-spray-can',
            'Termite': 'fa-bug',
            'Termite Treatment': 'fa-house-damage',
            'Weed Control': 'fa-seedling',
            'Bed Bugs': 'fa-bed'
        };
        this.isInitialized = false;
        this.onConfirmCallback = onConfirmCallback; // Callback function to execute after confirmation
        this.jobOrderId = jobOrderId; // Job order ID for tracking tools
        this.isModalShown = false;
    }

    /**
     * Initialize the checklist
     * @returns {Promise} A promise that resolves when initialization is complete
     */
    init() {
        console.log('Initializing tools checklist...');

        // Return a promise to allow better handling of async operations
        return new Promise(async (resolve, reject) => {
            try {
                if (this.isInitialized) {
                    console.log('Checklist already initialized, skipping');
                    return resolve();
                }

                // Fetch tools and equipment data
                const url = '../get_tools_equipment.php';
                console.log('Fetching tools and equipment from:', url);

                const response = await fetch(url);
                console.log('Response status:', response.status);

                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Tools and equipment data received:', data);

                if (!data.success) {
                    console.error('Error fetching tools and equipment:', data.error);
                    // Execute callback and reject the promise
                    if (typeof this.onConfirmCallback === 'function') {
                        console.log('Executing callback due to data fetch error');
                        this.onConfirmCallback();
                    }
                    return reject(new Error(data.error || 'Failed to fetch tools data'));
                }

                // Check if we have any tools
                if (!data.data || Object.keys(data.data).length === 0) {
                    console.warn('No tools found in the response');
                    // If no tools, just execute the callback and resolve
                    if (typeof this.onConfirmCallback === 'function') {
                        console.log('Executing callback due to no tools found');
                        this.onConfirmCallback();
                    }
                    return resolve();
                }

                this.tools = data.data;
                console.log('Tools categories:', Object.keys(this.tools));

                // Count total tools
                this.totalTools = Object.values(this.tools).reduce((total, tools) => total + tools.length, 0);
                console.log('Total tools count:', this.totalTools);

                // If no tools, just execute the callback and resolve
                if (this.totalTools === 0) {
                    console.warn('Total tools count is 0, skipping checklist');
                    if (typeof this.onConfirmCallback === 'function') {
                        console.log('Executing callback due to zero tools');
                        this.onConfirmCallback();
                    }
                    return resolve();
                }

                // Create modal
                this.createModal();
                console.log('Modal created');

                // Show modal
                this.showModal();
                console.log('Modal shown');

                this.isInitialized = true;
                console.log('Checklist initialization complete');

                // Resolve the promise
                resolve();
            } catch (error) {
                console.error('Error initializing tools checklist:', error);

                // Execute the callback function even if there's an error
                if (typeof this.onConfirmCallback === 'function') {
                    console.log('Executing callback despite initialization error');
                    this.onConfirmCallback();
                }

                // Show error message if SweetAlert2 is available
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to load tools and equipment checklist. Proceeding to job order details.',
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert('Failed to load tools and equipment checklist. Proceeding to job order details.');
                }

                // Reject the promise
                reject(error);
            }
        });
    }

    /**
     * Create the checklist modal
     */
    createModal() {
        // Check if modal already exists in the DOM
        const existingModal = document.querySelector('.tools-checklist-modal');
        if (existingModal) {
            console.log('Modal already exists, removing it first');
            existingModal.remove();
        }

        // Create modal container
        this.modal = document.createElement('div');
        this.modal.className = 'tools-checklist-modal';
        this.modal.style.zIndex = '9999'; // Ensure high z-index
        this.modal.innerHTML = `
            <div class="tools-checklist-container">
                <div class="tools-checklist-header">
                    <h2><i class="fas fa-tools"></i> Tools & Equipment Checklist</h2>
                </div>
                <div class="tools-checklist-body">
                    <div class="checklist-progress">
                        <div class="checklist-progress-bar" style="width: 0%"></div>
                    </div>
                    <div class="checklist-progress-text">
                        <span class="checked-count">0</span>/<span class="total-count">${this.totalTools}</span> items checked
                    </div>
                    <div class="tools-categories">
                        ${this.renderCategories()}
                    </div>
                </div>
                <div class="tools-checklist-footer">
                    <button type="button" class="btn-confirm" disabled>Confirm & Continue</button>
                </div>
            </div>
        `;

        // Add event listeners
        const confirmButton = this.modal.querySelector('.btn-confirm');

        if (confirmButton) {
            confirmButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.confirmChecklist();
            });
        } else {
            console.error('Confirm button not found in modal');
        }

        // Add category toggle event listeners
        this.modal.querySelectorAll('.tools-category-header').forEach(header => {
            // Exclude the check-all button from triggering the collapse
            header.addEventListener('click', (e) => {
                if (!e.target.closest('.check-all-btn')) {
                    header.classList.toggle('collapsed');
                }
            });
        });

        // Add checkbox event listeners
        this.modal.querySelectorAll('.tool-checkbox input').forEach(checkbox => {
            checkbox.addEventListener('change', () => this.updateProgress());
        });

        // Add "Check All" button event listeners
        this.modal.querySelectorAll('.check-all-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent category from collapsing
                const category = button.getAttribute('data-category');
                this.toggleCategoryCheckboxes(category);
            });
        });

        // Add to document
        document.body.appendChild(this.modal);
    }

    /**
     * Render categories and tools
     */
    renderCategories() {
        let html = '';

        for (const [category, tools] of Object.entries(this.tools)) {
            const icon = this.categoryIcons[category] || 'fa-tools';
            const categoryClass = category.toLowerCase().replace(/\s+/g, '-');
            const categoryId = `category-${categoryClass}`;

            html += `
                <div class="tools-category" data-category="${categoryClass}">
                    <div class="tools-category-header">
                        <div class="category-name">
                            <i class="fas ${icon}"></i>
                            ${category}
                            <span class="category-badge category-${categoryClass}">${tools.length}</span>
                            <button type="button" class="check-all-btn" data-category="${categoryClass}">
                                <i class="fas fa-check-square"></i> Check All
                            </button>
                        </div>
                        <i class="fas fa-chevron-down category-toggle"></i>
                    </div>
                    <div class="tools-category-body">
                        <ul class="tools-list" id="${categoryId}-list">
                            ${tools.map(tool => this.renderTool(tool, categoryClass)).join('')}
                        </ul>
                    </div>
                </div>
            `;
        }

        return html;
    }

    /**
     * Render a single tool
     * @param {Object} tool - The tool object
     * @param {string} category - The category class name
     */
    renderTool(tool, category) {
        return `
            <li class="tool-item">
                <div class="tool-checkbox">
                    <input type="checkbox" id="tool-${tool.id}" data-tool-id="${tool.id}" data-category="${category}">
                </div>
                <div class="tool-info">
                    <div class="tool-name">${tool.name}</div>
                    ${tool.description ? `<div class="tool-description">${tool.description}</div>` : ''}
                </div>
            </li>
        `;
    }

    /**
     * Update progress bar and count
     */
    updateProgress() {
        const checkboxes = this.modal.querySelectorAll('.tool-checkbox input');
        this.checkedTools = Array.from(checkboxes).filter(checkbox => checkbox.checked).length;

        const progressBar = this.modal.querySelector('.checklist-progress-bar');
        const progressText = this.modal.querySelector('.checked-count');
        const confirmButton = this.modal.querySelector('.btn-confirm');

        const progress = (this.checkedTools / this.totalTools) * 100;

        progressBar.style.width = `${progress}%`;
        progressText.textContent = this.checkedTools;

        // Enable confirm button if at least one tool is checked
        confirmButton.disabled = this.checkedTools === 0;
    }

    /**
     * Show the modal
     */
    showModal() {
        console.log('Showing modal...');

        // Make sure the modal is in the DOM
        if (!document.body.contains(this.modal)) {
            console.log('Modal not in DOM, appending it first');
            document.body.appendChild(this.modal);
        }

        // Make sure the modal is visible with proper styling
        this.modal.style.display = 'flex';
        this.modal.style.opacity = '1';
        this.modal.style.visibility = 'visible';
        this.modal.style.zIndex = '9999';

        // Add show class with a slight delay for animation
        setTimeout(() => {
            this.modal.classList.add('show');
            console.log('Modal show class added');
            this.isModalShown = true;
        }, 100);
    }

    /**
     * Hide the modal
     */
    hideModal() {
        if (!this.modal) {
            console.error('Modal not created yet');
            return;
        }

        // Remove show class to hide modal
        this.modal.classList.remove('show');
        this.isModalShown = false;

        // Restore body scrolling
        document.body.style.overflow = '';

        // Remove modal after animation
        setTimeout(() => {
            if (this.modal && this.modal.parentNode) {
                this.modal.parentNode.removeChild(this.modal);
            }

            // Set session variable to indicate checklist has been shown
            this.setChecklistShown();
        }, 300);
    }

    /**
     * Confirm checklist and hide modal
     */
    async confirmChecklist() {
        console.log('Confirm checklist button clicked');

        try {
            // Disable the confirm button to prevent multiple clicks
            const confirmButton = this.modal.querySelector('.btn-confirm');
            if (confirmButton) {
                confirmButton.disabled = true;
                confirmButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            }

            // Get checked tools
            const checkedTools = Array.from(this.modal.querySelectorAll('.tool-checkbox input:checked')).map(checkbox => {
                const toolId = checkbox.dataset.toolId;
                const toolName = this.modal.querySelector(`#tool-${toolId}`).closest('.tool-item').querySelector('.tool-name').textContent;

                return { id: toolId, name: toolName };
            });

            console.log('Checked tools:', checkedTools);

            // Save checklist confirmation to database (await the result)
            try {
                await this.saveChecklistConfirmation(checkedTools);
                console.log('Checklist confirmation saved successfully');
            } catch (saveError) {
                console.error('Error saving checklist confirmation:', saveError);
                // Continue with the flow even if saving fails
            }

            // Check if SweetAlert2 is available
            if (typeof Swal === 'undefined') {
                console.error('SweetAlert2 is not defined. Using regular alert instead.');
                alert(`Checklist Confirmed! You've checked ${this.checkedTools} out of ${this.totalTools} tools and equipment items.`);
            } else {
                // Show success message with SweetAlert2
                Swal.fire({
                    title: 'Checklist Confirmed!',
                    text: `You've checked ${this.checkedTools} out of ${this.totalTools} tools and equipment items.`,
                    icon: 'success',
                    confirmButtonText: 'Continue'
                });
            }

            // Hide modal
            this.hideModal();

            // Set session variable to indicate checklist has been shown
            this.setChecklistShown();

            // Execute the callback function if provided
            if (typeof this.onConfirmCallback === 'function') {
                console.log('Executing onConfirmCallback function');
                this.onConfirmCallback();
            }
        } catch (error) {
            console.error('Error in confirmChecklist:', error);

            // Re-enable the confirm button if there's an error
            const confirmButton = this.modal.querySelector('.btn-confirm');
            if (confirmButton) {
                confirmButton.disabled = false;
                confirmButton.innerHTML = 'Confirm & Continue';
            }

            // Show error message
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred while confirming the checklist. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('An error occurred while confirming the checklist. Please try again.');
            }
        }
    }

    /**
     * Save checklist confirmation to database
     */
    saveChecklistConfirmation(checkedTools) {
        console.log('Saving checklist confirmation...');

        const data = {
            checked_items: checkedTools,
            total_items: this.totalTools,
            checked_count: this.checkedTools
        };

        // Add job order ID if available
        if (this.jobOrderId) {
            data.job_order_id = this.jobOrderId;
            console.log('Including job order ID:', this.jobOrderId);
        }

        console.log('Checklist data:', data);

        // Use the correct path to the PHP file
        const url = '../save_checklist_confirmation.php';
        console.log('Sending request to:', url);

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(result => {
            console.log('Checklist confirmation result:', result);
            if (!result.success) {
                console.error('Error saving checklist confirmation:', result.error);
                throw new Error(result.error || 'Failed to save checklist confirmation');
            }
            return result;
        })
        .catch(error => {
            console.error('Error saving checklist confirmation:', error);
            // Don't throw the error here to prevent the confirmation flow from breaking
            // Just log it and continue
        });
    }

    /**
     * Toggle all checkboxes in a specific category
     * @param {string} category - The category class name
     */
    toggleCategoryCheckboxes(category) {
        console.log('Toggling checkboxes for category:', category);

        // Get all checkboxes in this category
        const checkboxes = this.modal.querySelectorAll(`.tool-checkbox input[data-category="${category}"]`);

        // Determine if we should check or uncheck based on current state
        // If all are checked, we'll uncheck them. Otherwise, check all.
        const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);

        // Update the button text based on the action
        const button = this.modal.querySelector(`.check-all-btn[data-category="${category}"]`);

        // Toggle all checkboxes
        checkboxes.forEach(checkbox => {
            checkbox.checked = !allChecked;
        });

        // Update the button text and icon
        if (button) {
            if (allChecked) {
                button.innerHTML = '<i class="fas fa-check-square"></i> Check All';
            } else {
                button.innerHTML = '<i class="fas fa-times-circle"></i> Uncheck All';
            }
        }

        // Update the progress bar
        this.updateProgress();
    }

    /**
     * Set session variable to indicate checklist has been shown
     */
    async setChecklistShown() {
        console.log('Setting checklist as shown...');

        try {
            const url = '../set_checklist_shown.php';
            console.log('Sending request to:', url);

            const response = await fetch(url, {
                method: 'POST'
            });

            console.log('Response status:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const result = await response.json();
            console.log('Set checklist shown result:', result);

            if (!result.success) {
                console.error('Error setting checklist shown:', result.error);
            }
        } catch (error) {
            console.error('Error setting checklist shown:', error);
            // Don't throw the error here to prevent the confirmation flow from breaking
        }
    }
}

// Create a global instance of the checklist that can be accessed from other scripts
let globalChecklist = null;

/**
 * Function to show the checklist on demand (when a job order is clicked)
 * This function must be defined in the global scope to be accessible from other scripts
 *
 * @param {Function} callback - Function to call after checklist is confirmed
 * @param {string|number} jobOrderId - ID of the job order
 */
window.showToolsChecklist = function(callback = null, jobOrderId = null) {
    console.log('Showing tools checklist on demand...');
    console.log('Job Order ID:', jobOrderId);
    console.log('Callback provided:', !!callback);

    try {
        // Always create a new checklist instance to avoid issues with stale data
        console.log('Creating new checklist instance');

        // Remove any existing checklist from the DOM
        const existingModal = document.querySelector('.tools-checklist-modal');
        if (existingModal) {
            console.log('Removing existing checklist modal from DOM');
            existingModal.remove();
        }

        // Create a new checklist instance with the callback and job order ID
        globalChecklist = new ToolsChecklist(callback, jobOrderId);

        // Initialize and show the checklist
        globalChecklist.init()
            .then(() => {
                console.log('Checklist initialized successfully');
            })
            .catch(error => {
                console.error('Error initializing checklist:', error);

                // If initialization fails, still call the callback to show job details
                if (typeof callback === 'function') {
                    console.log('Executing callback after initialization error');
                    callback();
                }

                // Show error message to user
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Warning',
                        text: 'Could not load the tools checklist. Proceeding to job order details.',
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                }
            });
    } catch (error) {
        console.error('Error showing tools checklist:', error);

        // Execute the callback function even if there's an error with the checklist
        // This ensures the job order details will still be shown
        if (typeof callback === 'function') {
            console.log('Executing callback despite checklist error');
            callback();
        }

        // Show error message
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Warning',
                text: 'Could not load the tools checklist. Proceeding to job order details.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
        }
    }
};

// Make sure the function is also available as a regular function
function showToolsChecklist(callback = null, jobOrderId = null) {
    return window.showToolsChecklist(callback, jobOrderId);
}

// Initialize when DOM is loaded, but don't show the checklist automatically
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, ensuring showToolsChecklist is available...');

    // Ensure the showToolsChecklist function is available in the global scope
    if (typeof window.showToolsChecklist !== 'function') {
        console.error('showToolsChecklist not defined in global scope, defining it now');
        window.showToolsChecklist = showToolsChecklist;
    }
});
