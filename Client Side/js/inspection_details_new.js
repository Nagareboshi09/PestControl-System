/**
 * JavaScript for handling inspection report details
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the inspection report modal
    initInspectionModal();
});

/**
 * Initialize the inspection report modal functionality
 */
function initInspectionModal() {
    const inspectionModal = document.getElementById('inspectionModal');

    if (inspectionModal) {
        // When the modal is about to be shown
        inspectionModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const reportId = button.getAttribute('data-report-id');

            // Fetch inspection report details
            fetchInspectionDetails(reportId);
        });

        // When the modal is fully shown
        inspectionModal.addEventListener('shown.bs.modal', function() {
            console.log('Inspection modal is now fully visible');
        });
    }
}

/**
 * Fetch inspection report details via AJAX
 * @param {number} reportId - The ID of the inspection report
 */
function fetchInspectionDetails(reportId) {
    const modalContent = document.getElementById('inspectionModalContent');

    console.log('Fetching inspection details for report ID:', reportId);

    // Show loading spinner
    modalContent.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading inspection report details...</p>
        </div>
    `;

    // Fetch inspection report details using jQuery AJAX
    console.log('Making AJAX request to:', 'ajax/get_inspection_details.php');

    // Show the full URL for debugging
    const fullUrl = new URL('ajax/get_inspection_details.php', window.location.href).href;
    console.log('Full URL:', fullUrl);

    $.ajax({
        url: window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) + '/ajax/get_inspection_details.php',
        type: 'POST',
        data: { report_id: reportId },
        dataType: 'json',
        timeout: 10000, // 10 second timeout
        success: function(data) {
            if (data.success) {
                displayInspectionDetails(data.report);
            } else {
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Error: ${data.message || 'Failed to load inspection report details'}
                    </div>
                `;
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);

            modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Error: Failed to load inspection report details
                </div>
                <div class="alert alert-info">
                    <p>Error details: ${error}</p>
                    <p>Status: ${status}</p>
                    <p>Please try again or contact support if the issue persists.</p>
                </div>
            `;
        }
    });
}

/**
 * Display inspection report details in the modal
 * @param {Object} report - The inspection report data
 */
function displayInspectionDetails(report) {
    const modalContent = document.getElementById('inspectionModalContent');

    // Format date and time
    const reportDate = new Date(report.report_date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    // Build HTML content for the technician section
    let technicianHtml = '';
    if (report.technician_id) {
        const technicianName = report.technician_fname && report.technician_lname
            ? `${report.technician_fname} ${report.technician_lname}`
            : report.technician_name;

        const technicianPicture = report.technician_picture
            ? `../Admin Side/${report.technician_picture}`
            : 'https://via.placeholder.com/80';

        technicianHtml = `
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="text-primary mb-3">Scheduled Technician</h5>
                    <div class="d-flex align-items-center">
                        <img src="${technicianPicture}" alt="Technician" class="rounded-circle me-3" style="width: 80px; height: 80px; object-fit: cover;">
                        <div>
                            <h4 class="mb-1">${technicianName}</h4>
                            <p class="text-muted mb-0">
                                <i class="fas fa-phone me-2"></i>${report.technician_contact || 'No contact number'}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Build HTML content for attachments section
    let attachmentsHtml = '';
    if (report.attachments && report.attachments.trim() !== '') {
        const attachments = report.attachments.split(',');
        let attachmentItems = '';

        attachments.forEach(attachment => {
            if (attachment.trim() !== '') {
                const attachmentPath = '../uploads/' + attachment.trim();
                attachmentItems += `
                    <div class="attachment-item">
                        <div style="cursor: pointer;" onclick="openImageViewer('${attachmentPath}')" title="Click to view larger image">
                            <img src="${attachmentPath}" alt="Attachment" class="attachment-img" onerror="this.src='../assets/img/image-not-found.png'; this.alt='Image not found';">
                            <div class="attachment-caption">${attachment.trim().split('/').pop()}</div>
                        </div>
                    </div>
                `;
            }
        });

        if (attachmentItems) {
            attachmentsHtml = `
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="text-primary mb-3">Attachments</h5>
                        <div class="attachments-grid">
                            ${attachmentItems}
                        </div>
                    </div>
                </div>
            `;
        }
    }

    // Build HTML content for the inspection report section
    const inspectionHtml = `
        <div class="card">
            <div class="card-body">
                <h5 class="text-primary mb-4">Inspection Report</h5>

                <div class="mb-3">
                    <p class="text-muted mb-1">Completion Time:</p>
                    <p class="fw-bold">${report.end_time || 'Not specified'}</p>
                </div>

                <div class="mb-3">
                    <p class="text-muted mb-1">Actual Pest Found:</p>
                    <p class="fw-bold">${report.pest_types || 'Not specified'}</p>
                </div>

                <div class="mb-3">
                    <p class="text-muted mb-1">Infested Area:</p>
                    <p class="fw-bold">${report.problem_area || 'Not specified'}</p>
                </div>

                <div class="mb-3">
                    <p class="text-muted mb-1">Infested Area size:</p>
                    <p class="fw-bold">${report.area ? report.area + ' m²' : 'Not specified'}</p>
                </div>

                <div class="mb-3">
                    <p class="text-muted mb-1">Technician Notes:</p>
                    <div class="p-3 bg-light rounded">
                        ${report.report_notes || 'No notes provided'}
                    </div>
                </div>

                <div class="mb-3">
                    <p class="text-muted mb-1">Recommendation:</p>
                    <div class="p-3 bg-light rounded">
                        ${report.report_notes || 'No recommendation provided'}
                    </div>
                </div>
            </div>
        </div>
    `;

    // Combine all sections
    const html = `
        <div class="container-fluid p-0">
            ${technicianHtml}
            ${inspectionHtml}
            ${attachmentsHtml}
        </div>
    `;

    // Update modal content
    modalContent.innerHTML = html;
}
