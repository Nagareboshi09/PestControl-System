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

    // Build HTML content
    let html = `
        <div class="row">
            <div class="col-md-12">
                <h4 class="mb-3">Inspection Report</h4>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Report Details</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Report Date:</strong> ${reportDate}</p>
                                <p><strong>Completion Time:</strong> ${report.end_time || 'Not specified'}</p>
                                <p><strong>Area Treated:</strong> ${report.area || 'Not specified'} m²</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Pest Types:</strong> ${report.pest_types || 'Not specified'}</p>
                                <p><strong>Problem Area:</strong> ${report.problem_area || 'Not specified'}</p>
                            </div>
                        </div>
                        <p><strong>Technician Notes:</strong></p>
                        <div class="border p-3 rounded mb-3">
                            ${report.report_notes || 'No additional notes from technician'}
                        </div>
    `;

    // Add attachments if any
    if (report.attachments) {
        const attachments = report.attachments.split(',');

        html += `
            <h6>Attachments:</h6>
            <div class="report-attachments">
        `;

        attachments.forEach(attachment => {
            html += `
                <a href="../uploads/${attachment}" target="_blank">
                    <img src="../uploads/${attachment}" alt="Attachment" class="report-attachment">
                </a>
            `;
        });

        html += `</div>`;
    }

    html += `</div></div>`;

    // Add technician information if assigned
    if (report.technician_id) {
        const technicianPicture = report.technician_picture
            ? report.technician_picture
            : '../uploads/default-avatar.png';

        html += `
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Assigned Technician</h5>
                    <div class="technician-modal-header">
                        <img src="${technicianPicture}" alt="Technician" class="technician-modal-avatar">
                        <div>
                            <h5>${report.technician_fname && report.technician_lname ? `${report.technician_fname} ${report.technician_lname}` : report.technician_name}</h5>
                            <p class="mb-0"><i class="fas fa-phone"></i> ${report.technician_contact || 'No contact information'}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }



    html += `</div></div>`;

    // Update modal content
    modalContent.innerHTML = html;
}
