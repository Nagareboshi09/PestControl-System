/**
 * JavaScript for handling job order feedback
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the job order modal
    initJobOrderModal();
});

/**
 * Initialize the job order modal functionality
 */
function initJobOrderModal() {
    const jobOrderModal = document.getElementById('jobOrderModal');

    if (jobOrderModal) {
        // When the modal is about to be shown
        jobOrderModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const jobOrderId = button.getAttribute('data-job-order-id');

            // Fetch job order details
            fetchJobOrderDetails(jobOrderId);
        });
    }
}

/**
 * Fetch job order details via AJAX
 * @param {number} jobOrderId - The ID of the job order
 */
function fetchJobOrderDetails(jobOrderId) {
    const modalContent = document.getElementById('jobOrderModalContent');

    // Show loading spinner
    modalContent.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading job order details...</p>
        </div>
    `;

    // Get the current sort parameter to preserve it
    const sortParam = getCurrentSortParam();

    // Fetch job order details
    fetch(`get_job_order_details.php?job_order_id=${jobOrderId}&sort=${sortParam}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayJobOrderDetails(data.job_order);
            } else {
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Error: ${data.message || 'Failed to load job order details'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Error: Failed to load job order details
                </div>
            `;
        });
}

/**
 * Display job order details in the modal
 * @param {Object} jobOrder - The job order data
 */
function displayJobOrderDetails(jobOrder) {
    const modalContent = document.getElementById('jobOrderModalContent');

    // Set job order ID as data attribute on modal content
    modalContent.setAttribute('data-job-order-id', jobOrder.job_order_id);

    // Format date and time
    const formattedDate = new Date(jobOrder.preferred_date).toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    const formattedTime = new Date('2000-01-01T' + jobOrder.preferred_time).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });

    // Determine status
    let statusBadge = '';
    if (jobOrder.status === 'completed') {
        statusBadge = '<span class="status-badge status-finished">Completed</span>';
    } else {
        statusBadge = '<span class="status-badge status-scheduled">Scheduled</span>';
    }

    // Build HTML content
    let html = `
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>${formattedDate} at ${formattedTime}</h4>
                    ${statusBadge}
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Job Order Details</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Job Type:</strong> ${jobOrder.type_of_work}</p>
                                <p><strong>Location:</strong> ${jobOrder.property_address}</p>
                                <p><strong>Frequency:</strong> ${jobOrder.frequency}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Date:</strong> ${formattedDate}</p>
                                <p><strong>Time:</strong> ${formattedTime}</p>
                                <p><strong>Status:</strong> ${jobOrder.status}</p>
                            </div>
                        </div>
                    </div>
                </div>
    `;

    // Add technician information if assigned
    if (jobOrder.technician_id) {
        const technicianPicture = jobOrder.technician_picture
            ? '../Admin Side/' + jobOrder.technician_picture
            : '../Admin Side/uploads/technicians/default.png';

        html += `
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Assigned Technician</h5>
                    <div class="technician-modal-header">
                        <img src="${technicianPicture}" alt="Technician" class="technician-modal-avatar clickable-avatar"
                             onclick="openImageViewer('${technicianPicture}')" title="Click to view larger image">
                        <div>
                            <h5>${jobOrder.technician_fname && jobOrder.technician_lname ? `${jobOrder.technician_fname} ${jobOrder.technician_lname}` : jobOrder.technician_name}</h5>
                            <p class="mb-0"><i class="fas fa-phone"></i> ${jobOrder.technician_contact || 'No contact information'}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Add job order report information if completed
    if (jobOrder.job_report_id) {
        const reportDate = jobOrder.report_created_at ? new Date(jobOrder.report_created_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }) : 'Not specified';

        html += `
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Job Order Report</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Report Date:</strong> ${reportDate}</p>
                        </div>
                    </div>
                    <p><strong>Technician Notes:</strong></p>
                    <div class="border p-3 rounded mb-3">
                        ${jobOrder.observation_notes || 'No additional notes from technician'}
                    </div>
        `;

        // Add attachments if any
        if (jobOrder.report_attachments) {
            const attachments = jobOrder.report_attachments.split(',');

            html += `
                <h6>Attachments:</h6>
                <div class="report-attachments">
            `;

            attachments.forEach(attachment => {
                html += `
                    <div class="report-attachment-container">
                        <img src="../uploads/${attachment}" alt="Attachment" class="report-attachment"
                             onclick="openImageViewer('../uploads/${attachment}')">
                        <div class="attachment-overlay">
                            <i class="fas fa-search-plus"></i>
                        </div>
                    </div>
                `;
            });

            html += `</div>`;
        }

        html += `</div></div>`;
    }

    // Add feedback section
    html += `
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Technician Feedback</h5>
    `;

    if (jobOrder.feedback_id) {
        // Show existing feedback with verification details
        const feedbackDate = jobOrder.feedback_date ? new Date(jobOrder.feedback_date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }) : 'Unknown';

        html += `
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Thank you for your feedback and verification!
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Verification Details</h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Technician Arrived
                            <span class="badge ${jobOrder.technician_arrived == 1 ? 'bg-success' : 'bg-danger'} rounded-pill">
                                ${jobOrder.technician_arrived == 1 ? 'Yes' : 'No'}
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Job Completed
                            <span class="badge ${jobOrder.job_completed == 1 ? 'bg-success' : 'bg-danger'} rounded-pill">
                                ${jobOrder.job_completed == 1 ? 'Yes' : 'No'}
                            </span>
                        </li>
                    </ul>
                    ${jobOrder.verification_notes ? `
                    <div class="mt-3">
                        <strong>Verification Notes:</strong>
                        <p class="mb-0">${jobOrder.verification_notes}</p>
                    </div>
                    ` : ''}
                </div>
            </div>

            <div class="feedback-display">
                <div class="rating-stars mb-2">
        `;

        // Display stars
        for (let i = 1; i <= 5; i++) {
            html += `<i class="fas fa-star ${i <= jobOrder.rating ? 'text-warning' : 'text-secondary'}"></i> `;
        }

        html += `
                </div>
                <div class="border p-3 rounded mb-2">
                    ${jobOrder.feedback_comments || 'No additional comments provided.'}
                </div>
                <small class="text-muted">Submitted on ${feedbackDate}</small>
            </div>
        `;
    } else if (jobOrder.status === 'completed') {
        // Show feedback form with verification questions
        html += `
            <form method="POST" id="jobOrderFeedbackForm">
                <input type="hidden" name="job_order_id" value="${jobOrder.job_order_id}">

                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Technician Verification Required</strong>
                        <p class="mb-0">Please verify the following details about the completed job order.</p>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Verification Questions</h5>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="technicianArrived" name="technician_arrived" value="1" required>
                            <label class="form-check-label" for="technicianArrived">Did the technician arrive for the job?</label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="jobCompleted" name="job_completed" value="1" required>
                            <label class="form-check-label" for="jobCompleted">Did the technician complete the job successfully?</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Additional Verification Notes (optional):</label>
                            <textarea name="verification_notes" class="form-control" rows="2" placeholder="Any additional notes about the technician's work..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Rating:</label>
                    <div class="star-rating">
        `;

        // Add star rating inputs
        for (let i = 5; i >= 1; i--) {
            html += `
                <input type="radio" id="star${i}" name="rating" value="${i}" required>
                <label for="star${i}" class="fas fa-star"></label>
            `;
        }

        html += `
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Comments:</label>
                    <textarea name="comments" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" name="submit_feedback" class="btn btn-primary">Submit Feedback & Verification</button>
            </form>
        `;
    } else {
        // Job not completed yet
        html += `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Feedback can be provided once the job has been completed.
            </div>
        `;
    }

    html += `</div></div>`;
    html += `</div></div>`;

    // Update modal content
    modalContent.innerHTML = html;

    // Add event listener for feedback form submission
    const feedbackForm = document.getElementById('jobOrderFeedbackForm');
    if (feedbackForm) {
        feedbackForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitJobOrderFeedback(this);
        });
    }
}

/**
 * Submit job order feedback form via AJAX
 * @param {HTMLFormElement} form - The feedback form element
 */
function submitJobOrderFeedback(form) {
    const formData = new FormData(form);
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.innerHTML;

    // Get the current sort parameter to preserve it
    const sortParam = getCurrentSortParam();
    formData.append('sort', sortParam);

    // Disable button and show loading state
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

    fetch('submit_joborder_feedback.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message and refresh the modal
            let jobOrderId;

            // Try to get job order ID from response first
            if (data.job_order_id) {
                jobOrderId = data.job_order_id;
            } else {
                // Fallback to getting it from the modal content
                const modalContent = document.getElementById('jobOrderModalContent');
                jobOrderId = modalContent.getAttribute('data-job-order-id');
            }

            if (jobOrderId) {
                fetchJobOrderDetails(jobOrderId);
            } else {
                // If we can't get the job order ID, just show a success message
                alert('Feedback submitted successfully!');
                // Reload the page to refresh the data with the current sort parameter
                const url = new URL(window.location.href);
                url.searchParams.set('sort', sortParam);
                window.location.href = url.toString();
            }
        } else {
            // Show error message
            alert('Error: ' + (data.message || 'Failed to submit feedback'));
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: Failed to submit feedback');
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonText;
    });
}

/**
 * Helper function to get the current sort parameter from URL
 * @returns {string} The current sort parameter or 'date_asc' as default
 */
function getCurrentSortParam() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('sort') || 'date_asc';
}

/**
 * Open the image viewer modal with the specified image
 * @param {string} imageSrc - The source URL of the image to display
 */
function openImageViewer(imageSrc) {
    const fullSizeImage = document.getElementById('fullSizeImage');
    const downloadLink = document.getElementById('downloadImageLink');
    const modalTitle = document.querySelector('#imageViewerModal .modal-title');

    if (fullSizeImage && downloadLink) {
        // Set the image source
        fullSizeImage.src = imageSrc;

        // Set the download link
        downloadLink.href = imageSrc;

        // Extract filename from path for the download attribute
        const filename = imageSrc.substring(imageSrc.lastIndexOf('/') + 1);
        downloadLink.setAttribute('download', filename);

        // Determine if this is a profile picture or an attachment
        const isProfilePic = imageSrc.includes('technicians');

        // Update modal title based on image type
        if (isProfilePic) {
            modalTitle.innerHTML = '<i class="fas fa-user-circle me-2"></i>Technician Profile Picture';

            // Add profile picture specific styling
            fullSizeImage.classList.add('profile-picture-view');
        } else {
            modalTitle.innerHTML = '<i class="fas fa-image me-2"></i>Job Order Image';
            fullSizeImage.classList.remove('profile-picture-view');
        }

        // Show the modal
        const imageViewerModal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
        imageViewerModal.show();

        // Handle image load event to adjust modal size
        fullSizeImage.onload = function() {
            // Force modal to recalculate its position
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 200);
        };
    }
}
