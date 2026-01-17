/**
 * Admin Side Client Details JavaScript
 * This file contains all the JavaScript functionality for client details
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize client details modal
    initClientDetailsModal();

    // Initialize delete client functionality
    initDeleteClientModal();
});

/**
 * Initialize the client details modal functionality
 */
function initClientDetailsModal() {
    // Get modal elements
    const clientDetailsModal = document.getElementById('clientDetailsModal');
    const closeModalButtons = document.querySelectorAll('.close-modal, .close-btn');

    // Add event listeners to view client buttons
    const viewClientButtons = document.querySelectorAll('.view-client-btn');
    viewClientButtons.forEach(button => {
        button.addEventListener('click', function() {
            const clientId = this.getAttribute('data-id');
            fetchClientDetails(clientId);
            clientDetailsModal.style.display = 'block';
        });
    });

    // Add event listeners to close modal buttons
    closeModalButtons.forEach(button => {
        button.addEventListener('click', function() {
            clientDetailsModal.style.display = 'none';
        });
    });

    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
        if (event.target === clientDetailsModal) {
            clientDetailsModal.style.display = 'none';
        }
    });
}

/**
 * Initialize the delete client modal functionality
 */
function initDeleteClientModal() {
    // Get modal elements
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const closeModalButtons = document.querySelectorAll('.close-modal, .cancel-btn');
    const deleteClientButtons = document.querySelectorAll('.delete-client-btn');
    const confirmDeleteButton = document.querySelector('.confirm-delete-btn');

    // Add event listeners to delete client buttons
    deleteClientButtons.forEach(button => {
        button.addEventListener('click', function() {
            const clientId = this.getAttribute('data-id');
            const clientName = this.getAttribute('data-name');
            const appointmentCount = parseInt(this.getAttribute('data-appointments'));
            const contractCount = parseInt(this.getAttribute('data-contracts') || '0');

            // Set client name in modal
            document.getElementById('deleteClientName').textContent = clientName;

            // Show/hide appointment warning
            const appointmentWarning = document.getElementById('appointmentWarning');
            if (appointmentCount > 0 || contractCount > 0) {
                appointmentWarning.style.display = 'block';

                // Update warning message to include contracts if any
                let warningText = 'This client has ';
                if (appointmentCount > 0 && contractCount > 0) {
                    warningText += `${appointmentCount} appointment(s) and ${contractCount} contract(s)`;
                } else if (appointmentCount > 0) {
                    warningText += `${appointmentCount} appointment(s)`;
                } else {
                    warningText += `${contractCount} contract(s)`;
                }
                warningText += '. Deleting this client will also delete all associated appointments, reports, and contracts.';

                appointmentWarning.querySelector('span').textContent = warningText;
            } else {
                appointmentWarning.style.display = 'none';
            }

            // Set client ID for delete confirmation
            confirmDeleteButton.setAttribute('data-id', clientId);

            // Show modal
            deleteConfirmModal.style.display = 'block';
        });
    });

    // Add event listeners to close modal buttons
    closeModalButtons.forEach(button => {
        button.addEventListener('click', function() {
            deleteConfirmModal.style.display = 'none';
        });
    });

    // Add event listener to confirm delete button
    confirmDeleteButton.addEventListener('click', function() {
        const clientId = this.getAttribute('data-id');
        deleteClient(clientId);
    });

    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
        if (event.target === deleteConfirmModal) {
            deleteConfirmModal.style.display = 'none';
        }
    });
}

/**
 * Fetch client details from the server
 * @param {number} clientId - The ID of the client to fetch details for
 */
function fetchClientDetails(clientId) {
    // Show loading indicator
    document.getElementById('clientDetails').innerHTML = `
        <div class="text-center">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p>Loading client details...</p>
        </div>
    `;

    // Fetch client details
    fetch(`get_client_details.php?client_id=${clientId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayClientDetails(data.client, data.appointments, data.contracts);
            } else {
                document.getElementById('clientDetails').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Error: ${data.message || 'Failed to load client details'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('clientDetails').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Error: Failed to load client details
                </div>
            `;
        });
}

/**
 * Display client details in the modal
 * @param {Object} client - The client data
 * @param {Array} appointments - The client's appointments
 * @param {Array} contracts - The client's contracts
 */
function displayClientDetails(client, appointments, contracts = []) {
    // Format client details
    const clientDetailsHtml = `
        <div class="client-details-container">
            <div class="client-info-section">
                <h4>Personal Information</h4>
                <div class="client-detail-row">
                    <div class="detail-label">Name:</div>
                    <div class="detail-value">${client.first_name} ${client.last_name}</div>
                </div>
                <div class="client-detail-row">
                    <div class="detail-label">Email:</div>
                    <div class="detail-value">${client.email}</div>
                </div>
                <div class="client-detail-row">
                    <div class="detail-label">Contact:</div>
                    <div class="detail-value">${client.contact_number}</div>
                </div>
                <div class="client-detail-row">
                    <div class="detail-label">Location:</div>
                    <div class="detail-value">${client.location_address || 'Not set'}</div>
                </div>
                <div class="client-detail-row">
                    <div class="detail-label">Type of Place:</div>
                    <div class="detail-value">${client.type_of_place || 'Not set'}</div>
                </div>
                <div class="client-detail-row">
                    <div class="detail-label">Registered:</div>
                    <div class="detail-value">${new Date(client.registered_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                </div>
            </div>

            <div class="appointment-history">
                <h4>Appointment History</h4>
                ${formatAppointmentHistory(appointments)}
            </div>

            <div class="contract-history">
                <h4>Contract History</h4>
                ${formatContractHistory(contracts)}
            </div>

            <div class="client-actions">
                <a href="client_details.php?id=${client.client_id}" class="btn btn-primary">
                    <i class="fas fa-external-link-alt"></i> View Full Details
                </a>
            </div>
        </div>
    `;

    // Update modal content
    document.getElementById('clientDetails').innerHTML = clientDetailsHtml;
}

/**
 * Format appointment history for display
 * @param {Array} appointments - The client's appointments
 * @returns {string} HTML for appointment history
 */
function formatAppointmentHistory(appointments) {
    if (!appointments || appointments.length === 0) {
        return `<p class="text-muted">No appointment history found.</p>`;
    }

    // Create appointment history table
    let html = `
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;

    // Add appointment rows
    appointments.forEach(appointment => {
        const date = new Date(appointment.preferred_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

        html += `
            <tr>
                <td>${date}</td>
                <td>${appointment.preferred_time}</td>
                <td>${appointment.location_address || 'Not specified'}</td>
                <td><span class="badge badge-${getStatusBadgeClass(appointment.status)}">${appointment.status}</span></td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    return html;
}

/**
 * Format contract history for display
 * @param {Array} contracts - The client's contracts
 * @returns {string} HTML for contract history
 */
function formatContractHistory(contracts) {
    if (!contracts || contracts.length === 0) {
        return `<p class="text-muted">No contract history found.</p>`;
    }

    // Create contract history table
    let html = `
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Frequency</th>
                        <th>Approval Date</th>
                        <th>Cost</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;

    // Add contract rows
    contracts.forEach(contract => {
        const approvalDate = new Date(contract.client_approval_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        const frequency = contract.client_approval_status === 'one-time' ? 'One-time' : contract.frequency.charAt(0).toUpperCase() + contract.frequency.slice(1);

        html += `
            <tr>
                <td>${contract.type_of_work}</td>
                <td>${frequency}</td>
                <td>${approvalDate}</td>
                <td>PHP ${parseFloat(contract.payment_amount || contract.cost).toFixed(2)}</td>
                <td><span class="badge badge-${getStatusBadgeClass(contract.status)}">${contract.status}</span></td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    return html;
}

/**
 * Get the appropriate badge class for a status
 * @param {string} status - The status
 * @returns {string} The badge class
 */
function getStatusBadgeClass(status) {
    switch (status) {
        case 'completed':
            return 'success';
        case 'scheduled':
            return 'primary';
        case 'cancelled':
            return 'danger';
        case 'pending':
            return 'warning';
        default:
            return 'secondary';
    }
}

/**
 * Delete a client
 * @param {number} clientId - The ID of the client to delete
 */
function deleteClient(clientId) {
    // Show loading state
    const confirmDeleteButton = document.querySelector('.confirm-delete-btn');
    const originalButtonText = confirmDeleteButton.innerHTML;
    confirmDeleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    confirmDeleteButton.disabled = true;

    // Send delete request
    fetch('delete_client.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ client_id: clientId }),
    })
        .then(response => response.json())
        .then(data => {
            // Hide modal
            document.getElementById('deleteConfirmModal').style.display = 'none';

            if (data.success) {
                // Show success message
                showNotification('success', 'Client deleted successfully');

                // Remove client from table
                const clientRow = document.querySelector(`button[data-id="${clientId}"]`).closest('tr');
                clientRow.remove();

                // Update client count
                updateClientCount();
            } else {
                // Show error message
                showNotification('error', data.message || 'Failed to delete client');
            }

            // Reset button
            confirmDeleteButton.innerHTML = originalButtonText;
            confirmDeleteButton.disabled = false;
        })
        .catch(error => {
            console.error('Error:', error);

            // Show error message
            showNotification('error', 'Failed to delete client');

            // Hide modal
            document.getElementById('deleteConfirmModal').style.display = 'none';

            // Reset button
            confirmDeleteButton.innerHTML = originalButtonText;
            confirmDeleteButton.disabled = false;
        });
}

/**
 * Show a notification message
 * @param {string} type - The type of notification (success, error)
 * @param {string} message - The notification message
 */
function showNotification(type, message) {
    // Check if notification container exists
    let notificationContainer = document.querySelector('.notification-message-container');

    // Create notification container if it doesn't exist
    if (!notificationContainer) {
        notificationContainer = document.createElement('div');
        notificationContainer.className = 'notification-message-container';
        document.body.appendChild(notificationContainer);
    }

    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification-message notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;

    // Add notification to container
    notificationContainer.appendChild(notification);

    // Remove notification after 5 seconds
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}

/**
 * Update the client count display
 */
function updateClientCount() {
    const clientCountElement = document.getElementById('clientCount');
    if (clientCountElement) {
        const currentCount = parseInt(clientCountElement.textContent);
        clientCountElement.textContent = currentCount - 1;
    }
}
