/**
 * Chemical Recommendations Module
 *
 * This module handles the generation and management of chemical recommendations
 * for pest control treatments based on pest types and area size.
 */

// Global variables to store chemical data
let selectedChemicals = [];
let availableChemicals = [];

// Initialize the chemical recommendations functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing chemical recommendations module');

    // Add event listener to the generate recommendations button
    const generateBtn = document.getElementById('generateRecommendationsBtn');
    if (generateBtn) {
        console.log('Found generate recommendations button, adding event listener');
        generateBtn.addEventListener('click', generateChemicalRecommendations);
    } else {
        console.error('Generate recommendations button not found');
    }

    // Add event listener to the modal open event to ensure area is passed correctly
    const chemicalModal = document.getElementById('chemicalRecommendationsModal');
    if (chemicalModal) {
        console.log('Found chemical recommendations modal');
        chemicalModal.addEventListener('show.bs.modal', function() {
            console.log('Chemical recommendations modal is being shown');
            checkAreaBeforeModalOpen();
        });
    } else {
        console.error('Chemical recommendations modal not found');
    }

    // Check if we have stored chemicals in session storage
    const storedChemicals = sessionStorage.getItem('selectedChemicals');
    if (storedChemicals) {
        try {
            selectedChemicals = JSON.parse(storedChemicals);
            displaySelectedChemicals();
        } catch (e) {
            console.error('Error parsing stored chemicals:', e);
            selectedChemicals = [];
        }
    }
});

/**
 * Check if area is available before opening the modal
 * If not, prompt the user to enter it
 */
function checkAreaBeforeModalOpen() {
    console.log('Checking area before modal open');

    // Try to get the area from the form
    const reportForm = document.getElementById('reportForm');
    if (reportForm) {
        const areaInput = reportForm.querySelector('input[name="area"]');
        if (areaInput && areaInput.value && areaInput.value > 0) {
            console.log('Area found in form:', areaInput.value);
            // Store the area in a data attribute on the modal for later use
            const modal = document.getElementById('chemicalRecommendationsModal');
            if (modal) {
                modal.dataset.area = areaInput.value;
                console.log('Stored area in modal data attribute:', modal.dataset.area);
            }
        } else {
            console.log('Area not found in form or invalid');
        }
    } else {
        console.log('Report form not found');
    }
}

/**
 * Generate chemical recommendations based on pest types and area
 */
function generateChemicalRecommendations() {
    console.log('Generating chemical recommendations');

    // Get the pest types and area from the form
    const pestTypesCheckboxes = document.querySelectorAll('input[name="pest_types[]"]:checked');

    if (pestTypesCheckboxes.length === 0) {
        Swal.fire({
            title: 'No Pest Types Selected',
            text: 'Please select at least one pest type before generating recommendations.',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
        return;
    }

    let pestTypes = Array.from(pestTypesCheckboxes).map(cb => cb.value).join(', ');
    console.log('Selected pest types:', pestTypes);

    // Handle "Others" case
    if (pestTypes.includes('Others') && document.getElementById('otherPestType') && document.getElementById('otherPestType').value.trim() !== '') {
        const otherValue = document.getElementById('otherPestType').value.trim();
        pestTypes = pestTypes.replace('Others', 'Others: ' + otherValue);
        console.log('Added "Others" pest type:', otherValue);
    }

    // Get the area size - try multiple possible locations
    let areaInput = document.getElementById('area');
    let area = 0;

    // First, check if the area is stored in the modal's data attribute
    const modal = document.getElementById('chemicalRecommendationsModal');
    if (modal && modal.dataset.area) {
        area = modal.dataset.area;
        console.log('Found area in modal data attribute:', area);
    }
    // If not found in modal data, try to get it from the input field
    else if (areaInput && areaInput.value) {
        area = areaInput.value;
        console.log('Found area in input field:', area);
    }
    // If still not found, try to get it from the form
    else {
        console.log('Area input not found by ID, trying to find it in the form');

        // Try to get it from the report form
        const reportForm = document.getElementById('reportForm');
        if (reportForm) {
            const areaInputs = reportForm.querySelectorAll('input[name="area"]');
            if (areaInputs.length > 0) {
                areaInput = areaInputs[0];
                if (areaInput.value) {
                    area = areaInput.value;
                    console.log('Found area input in report form:', area);
                }
            }
        }

        // If we still don't have a value, check if it's in the URL parameters
        if (!area) {
            const urlParams = new URLSearchParams(window.location.search);
            const areaParam = urlParams.get('area');
            if (areaParam) {
                area = areaParam;
                console.log('Found area in URL parameters:', area);
            }
        }
    }

    // If we still don't have a valid area, prompt the user
    if (!area || area <= 0) {
        console.log('No valid area found, prompting user');
        Swal.fire({
            title: 'Area Not Specified',
            text: 'Please enter the area size before generating recommendations.',
            icon: 'warning',
            input: 'number',
            inputLabel: 'Area (m²)',
            inputPlaceholder: 'Enter the area in square meters',
            inputAttributes: {
                min: 1,
                step: 0.01
            },
            confirmButtonText: 'Continue',
            showCancelButton: true,
            cancelButtonText: 'Cancel',
            inputValidator: (value) => {
                if (!value || isNaN(value) || value <= 0) {
                    return 'Please enter a valid area size';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // If the user entered a value, continue with that value
                area = result.value;
                console.log('User entered area:', area);

                // Continue with the recommendation generation
                continueGeneratingRecommendations(pestTypes, area, applicationMethod);
            }
        });
        return; // Exit the function here, it will continue in the callback if the user enters a value
    }

    console.log('Using area size:', area);

    // Get the application method
    const applicationMethodSelect = document.getElementById('applicationMethod');
    if (!applicationMethodSelect) {
        console.error('Application method select element not found');
        return;
    }

    const applicationMethod = applicationMethodSelect.value;
    console.log('Application method:', applicationMethod);

    // Continue with the recommendation generation
    continueGeneratingRecommendations(pestTypes, area, applicationMethod);
}

/**
 * Continue generating chemical recommendations with the provided parameters
 * This function is separated to allow for the area prompt flow
 */
function continueGeneratingRecommendations(pestTypes, area, applicationMethod) {
    console.log('Continuing with recommendation generation...');
    console.log('Pest types:', pestTypes);
    console.log('Area:', area);
    console.log('Application method:', applicationMethod);

    // Show loading indicator
    const loadingIndicator = document.getElementById('recommendationsLoading');
    const resultContainer = document.getElementById('recommendationsResult');

    if (!loadingIndicator || !resultContainer) {
        console.error('Loading indicator or result container not found');
        return;
    }

    loadingIndicator.style.display = 'block';
    resultContainer.style.display = 'none';

    // Create form data
    const formData = new FormData();
    formData.append('pest_types', pestTypes);
    formData.append('area', area);
    formData.append('application_method', applicationMethod);

    console.log('Sending request to get chemical recommendations');

    // Send AJAX request to get chemical recommendations
    fetch('../Admin Side/get_chemical_recommendations.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Received response:', response.status, response.statusText);
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        console.log('Received data:', data);

        // Hide loading indicator
        loadingIndicator.style.display = 'none';
        resultContainer.style.display = 'block';

        if (data.success) {
            console.log('Successfully received recommendations');

            // Store the available chemicals
            availableChemicals = [];
            for (const category in data.recommendations) {
                availableChemicals = availableChemicals.concat(data.recommendations[category]);
            }

            console.log('Available chemicals count:', availableChemicals.length);

            // Check if expiration dates are included in the response
            let hasExpirationDates = false;
            let expirationDateSample = null;

            // Check a sample of chemicals to see if they have expiration dates
            for (const category in data.recommendations) {
                if (data.recommendations[category].length > 0) {
                    const sampleChemical = data.recommendations[category][0];
                    console.log('Sample chemical:', sampleChemical.chemical_name);

                    if (sampleChemical.hasOwnProperty('expiration_date')) {
                        hasExpirationDates = true;
                        expirationDateSample = sampleChemical.expiration_date;
                        console.log('Expiration date found in response:', expirationDateSample);
                        break;
                    }
                }
            }

            if (!hasExpirationDates) {
                console.warn('No expiration dates found in the response. This may cause display issues.');
            }

            // Display the recommendations
            displayRecommendations(data);
        } else {
            console.error('Error in response:', data.message);
            resultContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>${data.message || 'Failed to generate recommendations'}</span>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error generating recommendations:', error);
        loadingIndicator.style.display = 'none';
        resultContainer.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span>An error occurred while generating recommendations: ${error.message}. Please try again.</span>
            </div>
        `;
    });
}

/**
 * Display chemical recommendations in the UI
 * @param {Object} data - The recommendation data from the server
 */
function displayRecommendations(data) {
    console.log('Displaying recommendations for data:', data);
    const container = document.getElementById('recommendationsResult');

    if (!container) {
        console.error('Recommendations result container not found');
        return;
    }

    // Create HTML for recommendations
    let html = `
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span>Chemical recommendations generated successfully for ${data.pest_types.join(', ')} in an area of ${data.area} m².</span>
        </div>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <span><strong>Only showing chemicals that are expiring within the next 10 days</strong> to minimize waste. For each chemical type, the one expiring soonest is recommended first.</span>
        </div>
        <div class="recommendations-container">
    `;

    // Combine Flying Pest and Crawling Pest into a single General Pest category
    let combinedRecommendations = {};

    // Create a copy of the recommendations to avoid modifying the original data
    for (const [targetPest, chemicals] of Object.entries(data.recommendations)) {
        if (targetPest === 'Flying Pest' || targetPest === 'Crawling Pest') {
            // Add to General Pest category
            if (!combinedRecommendations['General Pest']) {
                combinedRecommendations['General Pest'] = [];
            }
            combinedRecommendations['General Pest'] = [...combinedRecommendations['General Pest'], ...chemicals];
        } else {
            // Keep other categories as they are
            combinedRecommendations[targetPest] = chemicals;
        }
    }

    // Remove duplicates from the General Pest category based on chemical ID
    if (combinedRecommendations['General Pest']) {
        const uniqueChemicals = {};
        combinedRecommendations['General Pest'] = combinedRecommendations['General Pest'].filter(chemical => {
            const key = chemical.id;
            if (!uniqueChemicals[key]) {
                uniqueChemicals[key] = true;
                return true;
            }
            return false;
        });
    }

    // Add each target pest category
    for (const [targetPest, chemicals] of Object.entries(combinedRecommendations)) {
        html += `
            <div class="recommendation-category">
                <h4><i class="fas fa-bug"></i> ${targetPest}</h4>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="recommendations-table table table-bordered">
                        <thead>
                            <tr>
                                <th style="min-width: 70px;">Select</th>
                                <th style="min-width: 150px;">Chemical</th>
                                <th style="min-width: 120px;">Type</th>
                                <th style="min-width: 180px;">Recommended Dosage</th>
                                <th style="min-width: 150px;">Available Quantity</th>
                                <th style="min-width: 180px;">Expiration Date</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        chemicals.forEach(chemical => {
            // Check if this chemical is already selected
            const isSelected = selectedChemicals.some(c =>
                c.id === chemical.id ||
                (c.name === chemical.chemical_name && c.type === chemical.type)
            );

            // Use the formatted expiration date from the backend if available
            let expirationDateDisplay = 'N/A';
            let isExpiringSoon = false;
            let isExpired = false;
            let daysUntilExpiry = null;

            // First, check if the backend already provided formatted date and expiration info
            if (chemical.expiration_date_formatted) {
                expirationDateDisplay = chemical.expiration_date_formatted;
                daysUntilExpiry = chemical.days_until_expiry;
                isExpiringSoon = daysUntilExpiry > 0 && daysUntilExpiry <= 30;
                isExpired = daysUntilExpiry <= 0;
                console.log('Using backend expiration info:', expirationDateDisplay, 'Days until expiry:', daysUntilExpiry);
            }
            // If not, format it ourselves
            else if (chemical.expiration_date && chemical.expiration_date !== '0000-00-00') {
                try {
                    // Format the date properly
                    const dateParts = chemical.expiration_date.split('-');
                    if (dateParts.length === 3) {
                        // Create a date object with the parts (year, month-1, day)
                        const dateObj = new Date(
                            parseInt(dateParts[0]),
                            parseInt(dateParts[1]) - 1,
                            parseInt(dateParts[2])
                        );

                        // Check if the date is valid
                        if (!isNaN(dateObj.getTime())) {
                            // Format the date as a local date string
                            expirationDateDisplay = dateObj.toLocaleDateString();

                            // Calculate days until expiration
                            const today = new Date();
                            today.setHours(0, 0, 0, 0); // Reset time part for accurate day calculation
                            const diffTime = dateObj.getTime() - today.getTime();
                            daysUntilExpiry = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                            // Determine if expiring soon or expired
                            isExpiringSoon = daysUntilExpiry > 0 && daysUntilExpiry <= 30;
                            isExpired = daysUntilExpiry <= 0;

                            console.log('Calculated expiration info:', expirationDateDisplay, 'Days until expiry:', daysUntilExpiry);
                        } else {
                            console.error('Invalid date object created from:', chemical.expiration_date);
                        }
                    } else {
                        console.error('Invalid date format:', chemical.expiration_date);
                    }
                } catch (e) {
                    console.error('Error formatting expiration date:', e, 'Raw value:', chemical.expiration_date);
                }
            }

            // Log the expiration date information for debugging
            console.log('Chemical:', chemical.chemical_name, 'Expiration date raw:', chemical.expiration_date, 'Formatted:', expirationDateDisplay);

            // Determine if this is the first chemical of its type (to auto-select it)
            // We can use the index in the array to determine this since they're sorted by expiration date
            const isFirstOfType = chemicals.findIndex(c => c.chemical_name === chemical.chemical_name) === chemicals.indexOf(chemical);

            // Auto-select the first chemical of each type (the one expiring soonest)
            const autoSelect = isFirstOfType && !isSelected && !isExpired;

            // If we're auto-selecting, add this chemical to the selected chemicals array
            if (autoSelect) {
                selectedChemicals.push({
                    id: chemical.id,
                    name: chemical.chemical_name,
                    type: chemical.type,
                    dosage: chemical.recommended_dosage,
                    dosage_unit: chemical.dosage_unit,
                    target_pest: targetPest === 'General Pest' ? 'Crawling & Flying Pest' : targetPest
                });
                console.log('Auto-selected chemical:', chemical.chemical_name, 'as it is the first of its type and expiring soonest');
            }

            html += `
                <tr class="${isExpired ? 'table-danger' : isExpiringSoon ? 'table-warning' : ''}">
                    <td class="text-center">
                        <div class="form-check">
                            <input class="form-check-input chemical-checkbox" type="checkbox"
                                value="${chemical.id}"
                                data-name="${chemical.chemical_name}"
                                data-type="${chemical.type}"
                                data-dosage="${chemical.recommended_dosage}"
                                data-dosage-unit="${chemical.dosage_unit}"
                                data-target-pest="${targetPest}"
                                ${isSelected || autoSelect ? 'checked' : ''}
                                onchange="toggleChemicalSelection(this)">
                        </div>
                    </td>
                    <td>${chemical.chemical_name}</td>
                    <td>${chemical.type}</td>
                    <td>${chemical.recommended_dosage} ${chemical.dosage_unit}</td>
                    <td>${chemical.quantity} ${chemical.unit || ''}</td>
                    <td class="${isExpired ? 'text-danger fw-bold' : isExpiringSoon ? 'text-warning fw-bold' : ''}">
                        ${expirationDateDisplay}
                        ${isExpired ? ' <span class="badge bg-danger">Expired</span>' :
                          isExpiringSoon ? ` <span class="badge bg-warning text-dark">Expires in ${daysUntilExpiry} days</span>` : ''}
                        ${isFirstOfType && !isExpired ? ' <span class="badge bg-success">Recommended</span>' : ''}
                    </td>
                </tr>
            `;
        });

        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    html += `
        </div>
        <div class="mt-3">
            <button type="button" class="btn btn-primary" id="saveChemicalsBtn">
                <i class="fas fa-check"></i> Select the Chemical
            </button>
        </div>
    `;

    container.innerHTML = html;

    // Add event listener to the save button
    const saveBtn = document.getElementById('saveChemicalsBtn');
    if (saveBtn) {
        console.log('Adding event listener to save chemicals button');
        saveBtn.addEventListener('click', saveSelectedChemicals);
    } else {
        console.error('Save chemicals button not found');
    }
}

/**
 * Toggle the selection of a chemical
 * @param {HTMLElement} checkbox - The checkbox element
 */
function toggleChemicalSelection(checkbox) {
    console.log('Toggling chemical selection:', checkbox.value);
    const chemicalId = checkbox.value;
    const chemicalName = checkbox.dataset.name;
    const chemicalType = checkbox.dataset.type;
    const dosage = checkbox.dataset.dosage;
    const dosageUnit = checkbox.dataset.dosageUnit;
    let targetPest = checkbox.dataset.targetPest;

    // If the target pest is "General Pest", use "Crawling & Flying Pest" for database compatibility
    if (targetPest === 'General Pest') {
        targetPest = 'Crawling & Flying Pest';
        console.log('Converting General Pest to Crawling & Flying Pest for database compatibility');
    }

    if (checkbox.checked) {
        // Add to selected chemicals
        selectedChemicals.push({
            id: chemicalId,
            name: chemicalName,
            type: chemicalType,
            dosage: dosage,
            dosage_unit: dosageUnit,
            target_pest: targetPest
        });
        console.log('Added chemical to selection:', chemicalName);
    } else {
        // Remove from selected chemicals
        selectedChemicals = selectedChemicals.filter(c =>
            c.id !== chemicalId &&
            !(c.name === chemicalName && c.type === chemicalType)
        );
        console.log('Removed chemical from selection:', chemicalName);
    }

    // Update the display of selected chemicals
    displaySelectedChemicals();
}

// Make toggleChemicalSelection available globally for inline event handlers
window.toggleChemicalSelection = toggleChemicalSelection;

/**
 * Save the selected chemicals to session storage and update the hidden input
 */
function saveSelectedChemicals() {
    console.log('Saving selected chemicals, count:', selectedChemicals.length);

    if (selectedChemicals.length === 0) {
        Swal.fire({
            title: 'No Chemicals Selected',
            text: 'Please select at least one chemical before saving.',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
        return;
    }

    // Store in session storage
    sessionStorage.setItem('selectedChemicals', JSON.stringify(selectedChemicals));
    console.log('Saved chemicals to session storage');

    // Update hidden input
    const hiddenInput = document.getElementById('selectedChemicals');
    if (hiddenInput) {
        hiddenInput.value = JSON.stringify(selectedChemicals);
        console.log('Updated hidden input with selected chemicals');

        // Update debug info
        const debugInfo = document.getElementById('chemicalDebugInfo');
        if (debugInfo) {
            debugInfo.style.display = 'block';
            debugInfo.textContent = `${selectedChemicals.length} chemical(s) selected. Data size: ${hiddenInput.value.length} characters.`;
        }

        // Trigger a change event on the hidden input to ensure form data is updated
        const event = new Event('change', { bubbles: true });
        hiddenInput.dispatchEvent(event);
        console.log('Triggered change event on hidden input');
    } else {
        console.error('Hidden input element not found');
    }

    // Close the modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('chemicalRecommendationsModal'));
    if (modal) {
        modal.hide();
        console.log('Closed chemical recommendations modal');
    } else {
        console.error('Modal instance not found');
    }

    // Show success message
    Swal.fire({
        title: 'Chemicals Saved',
        text: `${selectedChemicals.length} chemical(s) have been added to your report.`,
        icon: 'success',
        confirmButtonText: 'OK'
    });

    // Update the display of selected chemicals
    displaySelectedChemicals();
}

// Make saveSelectedChemicals available globally for inline event handlers
window.saveSelectedChemicals = saveSelectedChemicals;

/**
 * Remove a specific chemical from the selected chemicals list
 * @param {string} chemicalId - The ID of the chemical to remove
 * @param {string} chemicalName - The name of the chemical to remove
 * @param {string} chemicalType - The type of the chemical to remove
 */
function removeSelectedChemical(chemicalId, chemicalName, chemicalType) {
    console.log('Removing chemical from selection:', chemicalName, chemicalType);

    // Remove from selected chemicals
    selectedChemicals = selectedChemicals.filter(c =>
        c.id !== chemicalId &&
        !(c.name === chemicalName && c.type === chemicalType)
    );

    // Update the display of selected chemicals
    displaySelectedChemicals();

    // Update hidden input
    const hiddenInput = document.getElementById('selectedChemicals');
    if (hiddenInput) {
        hiddenInput.value = JSON.stringify(selectedChemicals);
        console.log('Updated hidden input with selected chemicals after removal');
    } else {
        console.error('Hidden input element not found');
    }

    // Store in session storage
    sessionStorage.setItem('selectedChemicals', JSON.stringify(selectedChemicals));
    console.log('Updated session storage with selected chemicals after removal');

    // Show success message
    Swal.fire({
        title: 'Chemical Removed',
        text: `${chemicalName} has been removed from your selection.`,
        icon: 'success',
        confirmButtonText: 'OK',
        timer: 2000,
        timerProgressBar: true
    });
}

// Make removeSelectedChemical available globally for inline event handlers
window.removeSelectedChemical = removeSelectedChemical;

/**
 * Reset all selected chemicals
 * This function is exposed globally so it can be called from outside this file
 */
function resetAllSelectedChemicals() {
    console.log('Resetting all selected chemicals');

    // Clear the selected chemicals array
    selectedChemicals = [];

    // Clear session storage
    sessionStorage.removeItem('selectedChemicals');

    // Update hidden input
    const hiddenInput = document.getElementById('selectedChemicals');
    if (hiddenInput) {
        hiddenInput.value = '';
    }

    // Update the display
    displaySelectedChemicals();

    // Clear debug info
    const debugInfo = document.getElementById('chemicalDebugInfo');
    if (debugInfo) {
        debugInfo.style.display = 'none';
        debugInfo.textContent = '';
    }

    return true;
}

// Make resetAllSelectedChemicals available globally
window.resetAllSelectedChemicals = resetAllSelectedChemicals;

/**
 * Display the selected chemicals in the UI
 */
function displaySelectedChemicals() {
    const container = document.getElementById('selectedChemicalsContainer');

    if (!container) return;

    if (selectedChemicals.length === 0) {
        container.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <span>No chemicals have been selected yet. Click "Generate Recommendations" to get started.</span>
            </div>
        `;
        return;
    }

    let html = `
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span>${selectedChemicals.length} chemical(s) selected for this inspection report.</span>
        </div>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <span>Chemicals expiring within the next 10 days are prioritized to minimize waste.</span>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th style="min-width: 150px;">Chemical</th>
                        <th style="min-width: 120px;">Type</th>
                        <th style="min-width: 180px;">Recommended Dosage</th>
                        <th style="min-width: 150px;">Target Pest</th>
                        <th style="min-width: 150px;">Status</th>
                        <th style="min-width: 100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
    `;

    // Sort selected chemicals by name for better organization
    const sortedChemicals = [...selectedChemicals].sort((a, b) => a.name.localeCompare(b.name));

    sortedChemicals.forEach(chemical => {
        // Convert "Crawling & Flying Pest" to "General Pest" for display
        let displayTargetPest = chemical.target_pest;
        if (displayTargetPest === 'Crawling & Flying Pest') {
            displayTargetPest = 'General Pest';
        }

        // Determine if this is the first chemical of its type (recommended)
        const isRecommended = sortedChemicals.findIndex(c => c.name === chemical.name) === sortedChemicals.indexOf(chemical);

        html += `
            <tr>
                <td>${chemical.name}</td>
                <td>${chemical.type}</td>
                <td>${chemical.dosage} ${chemical.dosage_unit}</td>
                <td>${displayTargetPest}</td>
                <td>${isRecommended ? '<span class="badge bg-success">Recommended</span>' : ''}</td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeSelectedChemical('${chemical.id}', '${chemical.name}', '${chemical.type}')">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    container.innerHTML = html;
}

/**
 * Display chemical recommendations in a read-only format
 * @param {string} chemicalRecommendations - JSON string of chemical recommendations
 * @returns {string} HTML representation of the recommendations
 */
function displayChemicalRecommendations(chemicalRecommendations) {
    if (!chemicalRecommendations || chemicalRecommendations === '') {
        return 'No chemical recommendations provided';
    }

    try {
        const chemicals = JSON.parse(chemicalRecommendations);

        if (!Array.isArray(chemicals) || chemicals.length === 0) {
            return 'No chemical recommendations provided';
        }

        let html = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <span>Chemicals expiring within the next 10 days are prioritized to minimize waste.</span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="min-width: 150px;">Chemical</th>
                            <th style="min-width: 120px;">Type</th>
                            <th style="min-width: 180px;">Recommended Dosage</th>
                            <th style="min-width: 150px;">Target Pest</th>
                            <th style="min-width: 150px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        // Group chemicals by name to identify unique ones
        const chemicalGroups = {};
        chemicals.forEach(chemical => {
            if (!chemicalGroups[chemical.name]) {
                chemicalGroups[chemical.name] = [];
            }
            chemicalGroups[chemical.name].push(chemical);
        });

        // Process each chemical
        chemicals.forEach(chemical => {
            // Convert "Crawling & Flying Pest" to "General Pest" for display
            let displayTargetPest = chemical.target_pest;
            if (displayTargetPest === 'Crawling & Flying Pest') {
                displayTargetPest = 'General Pest';
            }

            // Determine if this is the first occurrence of this chemical name
            const isRecommended = chemicalGroups[chemical.name][0] === chemical;

            html += `
                <tr>
                    <td>${chemical.name}</td>
                    <td>${chemical.type}</td>
                    <td>${chemical.dosage} ${chemical.dosage_unit}</td>
                    <td>${displayTargetPest}</td>
                    <td>${isRecommended ? '<span class="badge bg-success">Recommended</span>' : ''}</td>
                </tr>
            `;
        });

        html += `
                </tbody>
            </table>
            </div>
        `;

        return html;
    } catch (e) {
        console.error('Error parsing chemical recommendations:', e);
        return 'Error displaying chemical recommendations';
    }
}
