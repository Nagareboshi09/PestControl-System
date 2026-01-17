/**
 * JavaScript for the Inspection Report page
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the report modal
    initReportModal();

    // Initialize sorting functionality
    initSorting();
});

/**
 * Initialize the report modal functionality
 */
function initReportModal() {
    const reportModal = document.getElementById('reportModal');

    if (reportModal) {
        // When the modal is about to be shown
        reportModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const appointmentId = button.getAttribute('data-appointment-id');

            // Fetch appointment details
            fetchAppointmentDetails(appointmentId);
        });

        // When the modal is fully shown
        reportModal.addEventListener('shown.bs.modal', function(event) {
            console.log('Modal is now fully visible');
            // The map initialization is now handled in the displayAppointmentDetails function
        });
    }
}

/**
 * Fetch appointment details via AJAX
 * @param {number} appointmentId - The ID of the appointment
 */
function fetchAppointmentDetails(appointmentId) {
    const modalContent = document.getElementById('reportModalContent');

    // Show loading spinner
    modalContent.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading appointment details...</p>
        </div>
    `;

    // Get the current sort parameter to preserve it
    const sortParam = getCurrentSortParam();

    console.log(`Fetching appointment details for ID: ${appointmentId}`);

    // Fetch appointment details
    fetch(`get_appointment_details.php?appointment_id=${appointmentId}&sort=${sortParam}`)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Received data:', data);
            if (data.success) {
                displayAppointmentDetails(data.appointment);
            } else {
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Error: ${data.message || 'Failed to load appointment details'}
                    </div>
                    <div class="text-center mt-3">
                        <button class="btn btn-primary" onclick="fetchAppointmentDetails(${appointmentId})">
                            <i class="fas fa-sync-alt"></i> Try Again
                        </button>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error fetching appointment details:', error);
            modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Error: Failed to load appointment details
                </div>
                <div class="text-center mt-3">
                    <p class="text-muted small">Technical details: ${error.message}</p>
                    <button class="btn btn-primary" onclick="fetchAppointmentDetails(${appointmentId})">
                        <i class="fas fa-sync-alt"></i> Try Again
                    </button>
                </div>
            `;
        });
}

/**
 * Display appointment details in the modal
 * @param {Object} appointment - The appointment data
 */
function displayAppointmentDetails(appointment) {
    console.log('Displaying appointment details:', appointment);

    const modalContent = document.getElementById('reportModalContent');

    // Check if we have valid appointment data
    if (!appointment || !appointment.appointment_id) {
        modalContent.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> Error: Invalid appointment data received
            </div>
        `;
        return;
    }

    try {
        // Set appointment ID as data attribute on modal content
        modalContent.setAttribute('data-appointment-id', appointment.appointment_id);

        // Format date and time
        let formattedDate = 'Unknown Date';
        let formattedTime = 'Unknown Time';

        try {
            if (appointment.preferred_date) {
                formattedDate = new Date(appointment.preferred_date).toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            }

            if (appointment.preferred_time) {
                formattedTime = new Date('2000-01-01T' + appointment.preferred_time).toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
        } catch (e) {
            console.error('Error formatting date/time:', e);
        }

        // Determine status
        let statusBadge = '';
        if (appointment.report_id) {
            statusBadge = '<span class="status-badge status-completed">Completed</span>';
        } else if (appointment.technician_id) {
            statusBadge = '<span class="status-badge status-assigned">Scheduled</span>';
        } else {
            statusBadge = '<span class="status-badge status-pending">Pending</span>';
        }

        // Safely get location address
        const locationAddress = appointment.location_address ?
            appointment.location_address.replace(/\s*\[[-\d.,]+\]$/, '') :
            'Address not available';

        // Build HTML content
        let html = `
            <div class="row">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>${formattedDate} at ${formattedTime}</h4>
                        ${statusBadge}
                    </div>

                    <h5 class="card-title">Appointment Details</h5>
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Location:</strong> ${locationAddress}</p>
                                    <p><strong>Type of Place:</strong> ${appointment.kind_of_place || 'Not specified'}</p>
                                    <p><strong>Pest Problems you reported:</strong> ${appointment.pest_problems || 'None specified'}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Prefered Date:</strong> ${formattedDate}</p>
                                    <p><strong>Prefered Time:</strong> ${formattedTime}</p>
                                </div>
                            </div>
                            <p><strong>Your Notes:</strong> ${appointment.notes || 'No additional notes'}</p>

                            <!-- Map container -->
                            <div class="mt-3">
                                <h6>Location Map:</h6>
                                <div class="location-map-container">
                                    <div id="modal-map-${appointment.appointment_id}" class="map" style="width: 100%; height: 200px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
        `;

    // Add technician information if assigned
    if (appointment.technician_id) {
        const technicianPicture = appointment.technician_picture
            ? '../Admin Side/' + appointment.technician_picture
            : '../Admin Side/uploads/technicians/default.png';

        const techName = appointment.technician_fname && appointment.technician_lname
            ? `${appointment.technician_fname} ${appointment.technician_lname}`
            : (appointment.technician_name || 'Unknown Technician');

        html += `
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Scheduled Technician</h5>
                    <div class="technician-modal-header">
                        <img src="${technicianPicture}" alt="Technician" class="technician-modal-avatar clickable-avatar"
                             onclick="openImageViewer('${technicianPicture}')" title="Click to view larger image">
                        <div>
                            <h5>${techName}</h5>
                            <p class="mb-0"><i class="fas fa-phone"></i> ${appointment.technician_contact || 'No contact information'}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Add report information if completed
    if (appointment.report_id) {
        let reportDate = 'Unknown Date';
        try {
            if (appointment.report_date) {
                reportDate = new Date(appointment.report_date).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            }
        } catch (e) {
            console.error('Error formatting report date:', e);
        }

        html += `
        <h5 class="card-title">Inspection Report</h5>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <p><strong>Completion Time:</strong> ${formatTo12Hour(appointment.end_time) || 'Not specified'}</p>
                            <p><strong>Actual Pest Found:</strong> ${appointment.pest_types || 'Not specified'}</p>
                            <p><strong>Infested Area:</strong> ${appointment.problem_area || 'Not specified'}</p>
                            <p><strong>Infested Area size:</strong> ${appointment.area ? appointment.area + ' m²' : 'Not specified'}</p>
                        </div>
                    </div>
                    <p><strong>Technician Notes:</strong></p>
                    <div class="border p-3 rounded mb-3">
                        ${appointment.report_notes || 'No additional notes from technician'}
                    </div>
                    <p><strong>Recommendation:</strong></p>
                    <div class="border p-3 rounded mb-3">
                        ${appointment.recommendation || 'No recommendations provided'}
                    </div>
        `;

        // Add attachments if any
        if (appointment.attachments) {
            try {
                const attachments = appointment.attachments.split(',');

                html += `
                    <h6>Attachments:</h6>
                    <div class="report-attachments">
                `;

                attachments.forEach(attachment => {
                    if (attachment && attachment.trim()) {
                        html += `
                            <div class="report-attachment-container">
                                <img src="../uploads/${attachment.trim()}" alt="Attachment" class="report-attachment"
                                     onclick="openImageViewer('../uploads/${attachment.trim()}')">
                                <div class="attachment-overlay">
                                    <i class="fas fa-search-plus"></i>
                                </div>
                            </div>
                        `;
                    }
                });

                html += `</div>`;
            } catch (e) {
                console.error('Error processing attachments:', e);
                html += `<p class="text-danger">Error loading attachments</p>`;
            }
        }

        html += `</div></div>`;
    }

    html += `</div></div>`;

    // Update modal content
    modalContent.innerHTML = html;

    // Initialize the map after the modal content is updated
    // Use a longer delay to ensure the modal is fully visible
    setTimeout(function() {
        try {
            const mapElement = document.getElementById(`modal-map-${appointment.appointment_id}`);
            if (mapElement) {
                console.log('Initializing map in modal:', `modal-map-${appointment.appointment_id}`);
                const address = appointment.location_address ?
                    appointment.location_address.replace(/\s*\[[-\d.,]+\]$/, '') :
                    'Philippines';
                initAppointmentMap(`modal-map-${appointment.appointment_id}`, address);
            }
        } catch (e) {
            console.error('Error initializing map:', e);
        }
    }, 1000);
    } catch (error) {
        console.error('Error displaying appointment details:', error);
        modalContent.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> Error: Failed to display appointment details
            </div>
            <div class="text-center mt-3">
                <p class="text-muted small">Technical details: ${error.message}</p>
                <button class="btn btn-primary" onclick="fetchAppointmentDetails(${appointment.appointment_id})">
                    <i class="fas fa-sync-alt"></i> Try Again
                </button>
            </div>
        `;
    }
}

/**
 * Display the technician scheduled modal
 * @param {Object} appointment - The appointment data
 */
function showTechnicianAssignedModal(appointment) {
    console.log('Showing technician assigned modal:', appointment);

    if (!appointment || !appointment.technician_id) {
        console.error('Invalid appointment data or missing technician_id');
        return;
    }

    try {
        const technicianInfoContainer = document.getElementById('assignedTechnicianInfo');
        if (!technicianInfoContainer) {
            console.error('Technician info container not found');
            return;
        }

        const technicianPicture = appointment.technician_picture
            ? '../Admin Side/' + appointment.technician_picture
            : '../Admin Side/uploads/technicians/default.png';

        // Format date and time
        let formattedDate = 'Unknown Date';
        let formattedTime = 'Unknown Time';

        try {
            if (appointment.preferred_date) {
                formattedDate = new Date(appointment.preferred_date).toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            }

            if (appointment.preferred_time) {
                formattedTime = new Date('2000-01-01T' + appointment.preferred_time).toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
        } catch (e) {
            console.error('Error formatting date/time:', e);
        }

        // Safely get location address
        const locationAddress = appointment.location_address ?
            appointment.location_address.replace(/\s*\[[-\d.,]+\]$/, '') :
            'Address not available';

        const techName = appointment.technician_fname && appointment.technician_lname
            ? `${appointment.technician_fname} ${appointment.technician_lname}`
            : (appointment.technician_name || 'Unknown Technician');

        // Build HTML content
        const html = `
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Appointment Information</h5>
                    <p><strong>Date:</strong> ${formattedDate}</p>
                    <p><strong>Time:</strong> ${formattedTime}</p>
                    <p><strong>Location:</strong> ${locationAddress}</p>
                    <p><strong>Type of Place:</strong> ${appointment.kind_of_place || 'Not specified'}</p>
                    <p><strong>Pest Problems:</strong> ${appointment.pest_problems || 'None specified'}</p>

                    <!-- Map container -->
                    <div class="location-map-container mt-2">
                        <div id="assigned-map-${appointment.appointment_id}" class="map" style="width: 100%; height: 200px;"></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Technician Information</h5>
                    <div class="technician-modal-header">
                        <img src="${technicianPicture}" alt="Technician" class="technician-modal-avatar clickable-avatar"
                             onclick="openImageViewer('${technicianPicture}')" title="Click to view larger image">
                        <div>
                            <h5>${techName}</h5>
                            <p class="mb-0"><i class="fas fa-phone"></i> ${appointment.technician_contact || 'No contact information'}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Update modal content
        technicianInfoContainer.innerHTML = html;

        // Initialize the map after the modal content is updated
        // Use a longer delay to ensure the modal is fully visible
        setTimeout(function() {
            try {
                const mapElement = document.getElementById(`assigned-map-${appointment.appointment_id}`);
                if (mapElement) {
                    console.log('Initializing map in technician modal:', `assigned-map-${appointment.appointment_id}`);
                    const address = appointment.location_address ?
                        appointment.location_address.replace(/\s*\[[-\d.,]+\]$/, '') :
                        'Philippines';
                    initAppointmentMap(`assigned-map-${appointment.appointment_id}`, address);
                }
            } catch (e) {
                console.error('Error initializing map in technician modal:', e);
            }
        }, 1000);

        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('technicianAssignedModal'));
        modal.show();
    } catch (error) {
        console.error('Error showing technician assigned modal:', error);
    }
}

// Feedback submission function removed as it's no longer needed

/**
 * Initialize sorting functionality
 */
function initSorting() {
    // Get all sort dropdown items
    const sortItems = document.querySelectorAll('.sort-filter .dropdown-item');

    // Add click event listener to each item
    sortItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Prevent default link behavior
            e.preventDefault();

            // Get the href attribute which contains the sort parameter
            const href = this.getAttribute('href');

            // Extract the sort parameter
            const sortParam = new URLSearchParams(href.substring(href.indexOf('?'))).get('sort');

            // Get the current URL and update the sort parameter
            const url = new URL(window.location.href);

            // Clear any existing sort parameter
            url.searchParams.delete('sort');

            // Add the new sort parameter
            url.searchParams.set('sort', sortParam);

            // Preserve other parameters like appointment_id or newly_assigned
            if (url.searchParams.has('appointment_id')) {
                const appointmentId = url.searchParams.get('appointment_id');
                url.searchParams.set('appointment_id', appointmentId);
            }

            if (url.searchParams.has('newly_assigned')) {
                const newlyAssigned = url.searchParams.get('newly_assigned');
                url.searchParams.set('newly_assigned', newlyAssigned);
            }

            // Log the URL for debugging
            console.log('Navigating to URL with sort parameter:', url.toString());

            // Navigate to the new URL
            window.location.href = url.toString();
        });
    });

    // Update the dropdown button text to show current sort
    updateSortDropdownText();
}

/**
 * Update the sort dropdown button text to reflect the current sort
 */
function updateSortDropdownText() {
    const sortParam = getCurrentSortParam();
    const sortDropdown = document.getElementById('sortDropdown');

    if (!sortDropdown) return;

    let sortText = 'Sort By';

    // Set the appropriate text based on the current sort
    switch (sortParam) {
        case 'date_desc':
            sortText = 'Newest First';
            break;
        case 'date_asc':
            sortText = 'Oldest First';
            break;
        case 'status_desc':
            sortText = 'Completed First';
            break;
        case 'status_asc':
            sortText = 'Pending First';
            break;
        case 'tech_asc':
            sortText = 'Technician (A-Z)';
            break;
        case 'tech_desc':
            sortText = 'Technician (Z-A)';
            break;
    }

    // Update the dropdown button text
    sortDropdown.innerHTML = `<i class="fas fa-sort"></i> ${sortText}`;
}

/**
 * Helper function to get the current sort parameter from URL
 * @returns {string} The current sort parameter or 'date_desc' as default
 */
function getCurrentSortParam() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('sort') || 'date_desc';
}

/**
 * Helper function to preserve sort parameter in URLs
 * @param {string} url - The URL to add the sort parameter to
 * @returns {string} The URL with the sort parameter added
 */
function addSortParamToUrl(url) {
    const sortParam = getCurrentSortParam();

    // Check if the URL already has parameters
    if (url.includes('?')) {
        return `${url}&sort=${sortParam}`;
    } else {
        return `${url}?sort=${sortParam}`;
    }
}

/**
 * Format time string to 12-hour format
 * @param {string} timeStr - The time string to format (e.g., "15:55:33")
 * @returns {string} The formatted time string in 12-hour format (e.g., "03:55 PM")
 */
function formatTo12Hour(timeStr) {
    if (!timeStr) return 'Not specified';

    try {
        // Create a date object using a dummy date with the provided time
        const date = new Date(`2000-01-01T${timeStr}`);

        // Format to 12-hour time with AM/PM
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    } catch (e) {
        console.error('Error formatting time to 12-hour format:', e);
        return timeStr; // Return original string if formatting fails
    }
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
            modalTitle.innerHTML = '<i class="fas fa-image me-2"></i>Inspection Image';
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