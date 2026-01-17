/**
 * Reliable Chemical Recommendations Display
 * This script provides a more reliable way to display chemical recommendations
 * in the assessment report page.
 */

// Function to load chemical recommendations for a report
function loadReliableChemicalRecommendations(reportId, containerId = 'chemicalRecommendationsContainer') {
    const container = document.getElementById(containerId);
    const hiddenInput = document.getElementById('selectedChemicals');

    if (!container) {
        console.error(`Chemical recommendations container with ID "${containerId}" not found`);
        return;
    }

    console.log('Loading reliable chemical recommendations for report ID:', reportId);

    // Show loading message
    container.innerHTML = `
        <div class="alert alert-info">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Loading chemical recommendations...</span>
        </div>
    `;

    // Fetch chemical recommendations from the reliable endpoint
    fetch(`get_reliable_chemical_recommendations.php?report_id=${reportId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server returned status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Received chemical recommendations data:', data);

            if (data.success) {
                // Process and display the recommendations
                displayReliableChemicalRecommendations(data.recommendations, container, hiddenInput);
            } else {
                // Display error message
                displayErrorMessage(data.message || 'Failed to load chemical recommendations', container);
            }
        })
        .catch(error => {
            console.error('Error fetching chemical recommendations:', error);
            displayErrorMessage(`Error loading chemical recommendations: ${error.message}`, container);
        });
}

// Function to display chemical recommendations
function displayReliableChemicalRecommendations(recommendations, container, hiddenInput) {
    if (!container) return;

    console.log('Displaying chemical recommendations:', recommendations);

    // Check if recommendations is an object with pest types as keys
    const isPestTypeFormat = typeof recommendations === 'object' &&
                            !Array.isArray(recommendations) &&
                            Object.keys(recommendations).length > 0;

    // Process recommendations based on format
    let processedChemicals = [];

    if (isPestTypeFormat) {
        // Format: { pest_type: [chemicals] }
        console.log('Processing recommendations by pest type');

        for (const [pestType, chemicals] of Object.entries(recommendations)) {
            if (Array.isArray(chemicals)) {
                chemicals.forEach(chemical => {
                    // Add pest type to each chemical if not already present
                    if (!chemical.target_pest) {
                        chemical.target_pest = pestType;
                    }
                    processedChemicals.push(chemical);
                });
            }
        }
    } else if (Array.isArray(recommendations)) {
        // Format: [chemicals]
        console.log('Processing recommendations as array');
        processedChemicals = recommendations;
    } else {
        console.error('Invalid recommendations format:', recommendations);
        displayErrorMessage('Invalid chemical recommendations format', container);
        return;
    }

    console.log('Processed chemicals:', processedChemicals);

    // Filter out any invalid entries
    const validChemicals = processedChemicals.filter(chem => {
        return chem && typeof chem === 'object';
    });

    console.log('Valid chemicals count:', validChemicals.length);

    if (validChemicals.length === 0) {
        displayNoChemicalRecommendations(container);
        return;
    }

    // Sort chemicals by expiration date (soonest first)
    const sortedChemicals = [...validChemicals].sort((a, b) => {
        const dateA = a.expiration_date ? new Date(a.expiration_date) : new Date('9999-12-31');
        const dateB = b.expiration_date ? new Date(b.expiration_date) : new Date('9999-12-31');
        return dateA - dateB;
    });

    // Generate HTML for the table
    let html = `
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span>${validChemicals.length} chemical(s) recommended for this assessment.</span>
        </div>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <span>Chemicals are prioritized based on expiration date to minimize waste.</span>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Chemical</th>
                        <th>Type</th>
                        <th>Recommended Dosage</th>
                        <th>Target Pest</th>
                        <th>Expiration Date</th>
                    </tr>
                </thead>
                <tbody>
    `;

    // Add each chemical to the table
    sortedChemicals.forEach(chemical => {
        try {
            // Make sure all properties exist
            const name = chemical.name || chemical.chemical_name || 'Unknown';
            const type = chemical.type || 'Unknown';

            // Handle different dosage property names and formats
            let dosage = '';
            let dosageUnit = '';

            if (chemical.dosage !== undefined) {
                dosage = chemical.dosage;
                dosageUnit = chemical.dosage_unit || '';
            } else if (chemical.recommended_dosage !== undefined) {
                dosage = chemical.recommended_dosage;
                dosageUnit = chemical.dosage_unit || '';
            } else {
                dosage = 'As recommended';
            }

            // Handle target pest property
            let targetPest = 'General';
            if (chemical.target_pest) {
                targetPest = chemical.target_pest;
            } else if (chemical.targetPest) {
                targetPest = chemical.targetPest;
            }

            // Convert "Crawling & Flying Pest" to "General Pest" for display
            if (targetPest === 'Crawling & Flying Pest') {
                targetPest = 'General Pest';
            }

            // Format expiration date if available
            let expirationDisplay = 'N/A';
            let expirationClass = '';
            let expirationBadge = '';

            // Debug expiration date information
            console.log('Chemical expiration data:', {
                name: name,
                expiration_date: chemical.expiration_date,
                expiration_date_formatted: chemical.expiration_date_formatted,
                days_until_expiry: chemical.days_until_expiry
            });

            // First check for expiration_date_formatted which is the most reliable
            if (chemical.expiration_date_formatted) {
                expirationDisplay = chemical.expiration_date_formatted;

                // If we also have days_until_expiry, use it for coloring and badges
                if (chemical.days_until_expiry !== undefined) {
                    const daysUntilExpiry = parseInt(chemical.days_until_expiry);

                    if (daysUntilExpiry < 0) {
                        expirationClass = 'text-danger fw-bold';  // Expired
                        expirationBadge = '<span class="text-danger">Expired</span>';
                    } else if (daysUntilExpiry < 30) {
                        expirationClass = 'text-warning fw-bold';  // Expiring soon - #ffc107 is the Bootstrap warning color
                        expirationDisplay = `<span style="color: #ffc107;">${expirationDisplay}</span>`;
                        expirationBadge = `<span style="color: #ffc107;">Expires in ${daysUntilExpiry} days</span>`;
                    }
                }
            }
            // Next try using expiration_date directly
            else if (chemical.expiration_date) {
                const expiryDate = new Date(chemical.expiration_date);

                // Check if the date is valid
                if (!isNaN(expiryDate.getTime())) {
                    const today = new Date();
                    const daysUntilExpiry = Math.floor((expiryDate - today) / (1000 * 60 * 60 * 24));

                    // Format date as YYYY-MM-DD
                    const year = expiryDate.getFullYear();
                    const month = String(expiryDate.getMonth() + 1).padStart(2, '0');
                    const day = String(expiryDate.getDate()).padStart(2, '0');
                    expirationDisplay = `${year}-${month}-${day}`;

                    // Add warning colors based on expiration date
                    if (daysUntilExpiry < 0) {
                        expirationClass = 'text-danger fw-bold';  // Expired
                        expirationBadge = '<span class="text-danger">Expired</span>';
                    } else if (daysUntilExpiry < 30) {
                        expirationClass = 'text-warning fw-bold';  // Expiring soon - #ffc107 is the Bootstrap warning color
                        expirationDisplay = `<span style="color: #ffc107;">${expirationDisplay}</span>`;
                        expirationBadge = `<span style="color: #ffc107;">Expires in ${daysUntilExpiry} days</span>`;
                    }
                }
            }
            // Finally, check if we have days_until_expiry without a formatted date
            else if (chemical.days_until_expiry !== undefined) {
                // If we have days_until_expiry directly but no formatted date
                const daysUntilExpiry = parseInt(chemical.days_until_expiry);

                // Create a generic expiration display
                const today = new Date();
                const expiryDate = new Date(today);
                expiryDate.setDate(today.getDate() + daysUntilExpiry);
                // Format date as YYYY-MM-DD
                const year = expiryDate.getFullYear();
                const month = String(expiryDate.getMonth() + 1).padStart(2, '0');
                const day = String(expiryDate.getDate()).padStart(2, '0');
                expirationDisplay = `${year}-${month}-${day}`;

                if (daysUntilExpiry < 0) {
                    expirationClass = 'text-danger fw-bold';  // Expired
                    expirationBadge = '<span class="text-danger">Expired</span>';
                } else if (daysUntilExpiry < 30) {
                    expirationClass = 'text-warning fw-bold';  // Expiring soon - #ffc107 is the Bootstrap warning color
                    expirationDisplay = `<span style="color: #ffc107;">${expirationDisplay}</span>`;
                    expirationBadge = `<span style="color: #ffc107;">Expires in ${daysUntilExpiry} days</span>`;
                }
            }

            html += `
                <tr>
                    <td>${name}</td>
                    <td>${type}</td>
                    <td>${dosage} ${dosageUnit}</td>
                    <td>${targetPest}</td>
                    <td class="${expirationClass}">
                        <div>${expirationDisplay}</div>
                        <div>${expirationBadge}</div>
                    </td>
                </tr>
            `;
        } catch (error) {
            console.error('Error processing chemical:', error, chemical);
        }
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    // Update the container with the HTML
    container.innerHTML = html;

    // Store the chemical recommendations in the hidden input
    if (hiddenInput) {
        hiddenInput.value = JSON.stringify(sortedChemicals);
        console.log('Stored chemicals in hidden input');
    }
}

// Function to display a message when no chemical recommendations are found
function displayNoChemicalRecommendations(container) {
    if (!container) return;

    container.innerHTML = `
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>No chemical recommendations found for this assessment report.</span>
        </div>
        <p class="form-help mt-2">
            <i class="fas fa-info-circle"></i>
            Chemical recommendations are based on the pest types and area in this report.
        </p>
    `;
}

// Function to display an error message
function displayErrorMessage(message, container) {
    if (!container) return;

    container.innerHTML = `
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <span>${message}</span>
        </div>
    `;
}
