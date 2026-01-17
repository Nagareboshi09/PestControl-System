// Chemical Recommendations functionality for assessment reports

document.addEventListener('DOMContentLoaded', function() {
    // Get the details modal
    const detailsModal = document.getElementById('detailsModal');

    if (!detailsModal) return;

    // Add chemical recommendations section to the details modal
    const modalBody = detailsModal.querySelector('.modal-body');

    if (!modalBody) return;

    // Create the chemical recommendations section
    const chemicalRecommendationsSection = document.createElement('div');
    chemicalRecommendationsSection.className = 'detail-section chemical-recommendations-section';
    chemicalRecommendationsSection.innerHTML = `
        <h3><i class="fas fa-flask"></i> Chemical Recommendations</h3>
        <div id="chemicalRecommendationsContent">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <span>Click "Generate Recommendations" to get chemical recommendations based on pest types and area.</span>
            </div>
            <div class="form-group">
                <label><i class="fas fa-spray-can"></i> Application Method:</label>
                <select id="applicationMethod" class="form-control">
                    <option value="spray">Spray Application</option>
                    <option value="fogging">Fogging Application</option>
                    <option value="soil drench">Soil Drench (for Termites)</option>
                    <option value="bait">Bait Application</option>
                </select>
            </div>
            <button id="generateRecommendationsBtn" class="btn btn-primary">
                <i class="fas fa-sync-alt"></i> Generate Recommendations
            </button>
            <div id="recommendationsLoading" style="display: none; margin-top: 15px; text-align: center;">
                <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem;"></i>
                <p>Generating recommendations...</p>
            </div>
            <div id="recommendationsResult" style="display: none; margin-top: 15px;">
                <!-- Recommendations will be displayed here -->
            </div>
        </div>
    `;

    // Add the section to the modal body
    modalBody.appendChild(chemicalRecommendationsSection);

    // Add event listener to the generate recommendations button
    const generateBtn = document.getElementById('generateRecommendationsBtn');
    const loadingIndicator = document.getElementById('recommendationsLoading');
    const resultContainer = document.getElementById('recommendationsResult');

    if (generateBtn && loadingIndicator && resultContainer) {
        generateBtn.addEventListener('click', function() {
            // Get the report ID, pest types, and area from the modal
            const reportId = document.getElementById('modalReportId') ?
                             document.getElementById('modalReportId').value :
                             document.querySelector('.view-details-btn').getAttribute('data-report-id');

            const pestTypes = document.getElementById('detailPestTypes') ?
                             document.getElementById('detailPestTypes').textContent : '';

            const area = document.getElementById('detailArea') ?
                        parseFloat(document.getElementById('detailArea').textContent) : 0;

            const applicationMethod = document.getElementById('applicationMethod') ?
                                     document.getElementById('applicationMethod').value : 'spray';

            // Validate inputs
            if (!reportId || !pestTypes || !area) {
                alert('Missing required information. Please ensure pest types and area are available.');
                return;
            }

            // Show loading indicator
            loadingIndicator.style.display = 'block';
            resultContainer.style.display = 'none';

            // Create form data
            const formData = new FormData();
            formData.append('report_id', reportId);
            formData.append('pest_types', pestTypes);
            formData.append('area', area);
            formData.append('application_method', applicationMethod);

            // Send AJAX request to get chemical recommendations
            fetch('get_chemical_recommendations.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                // Hide loading indicator
                loadingIndicator.style.display = 'none';

                // Show result container
                resultContainer.style.display = 'block';

                // Handle the response
                if (data.success) {
                    // Format and display recommendations
                    displayRecommendations(data, resultContainer, reportId);
                } else {
                    // Show error message
                    resultContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>${data.message || 'Failed to generate recommendations'}</span>
                        </div>
                    `;
                }
            })
            .catch(error => {
                // Hide loading indicator
                loadingIndicator.style.display = 'none';

                // Show error message
                resultContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Error: ${error.message}</span>
                    </div>
                `;
            });
        });
    }

    // Function to display recommendations
    function displayRecommendations(data, container, reportId) {
        // Create HTML for recommendations
        let html = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>Chemical recommendations generated successfully for ${data.pest_types.join(', ')} in an area of ${data.area} m².</span>
            </div>
            <div class="recommendations-container">
        `;

        // Add each target pest category
        for (const [targetPest, chemicals] of Object.entries(data.recommendations)) {
            html += `
                <div class="recommendation-category">
                    <h4><i class="fas fa-bug"></i> ${targetPest}</h4>
                    <table class="recommendations-table">
                        <thead>
                            <tr>
                                <th>Chemical</th>
                                <th>Type</th>
                                <th>Recommended Dosage</th>
                                <th>Available Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            // Add each chemical
            chemicals.forEach(chemical => {
                html += `
                    <tr>
                        <td>${chemical.chemical_name}</td>
                        <td>${chemical.type}</td>
                        <td>${chemical.recommended_dosage} ${chemical.dosage_unit}</td>
                        <td>${chemical.quantity} ${chemical.unit}</td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
            `;
        }

        // Add save button
        html += `
            </div>
            <div class="recommendations-actions" style="margin-top: 20px; text-align: right;">
                <button id="saveRecommendationsBtn" class="btn btn-success" data-report-id="${reportId}">
                    <i class="fas fa-save"></i> Save Recommendations
                </button>
            </div>
        `;

        // Set the HTML
        container.innerHTML = html;

        // Add event listener to save button
        const saveBtn = document.getElementById('saveRecommendationsBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                saveRecommendations(data, this.getAttribute('data-report-id'));
            });
        }
    }

    // Function to save recommendations
    function saveRecommendations(data, reportId) {
        // Show confirmation dialog
        if (!confirm('Are you sure you want to save these chemical recommendations to the assessment report?')) {
            return;
        }

        // Create form data
        const formData = new FormData();
        formData.append('report_id', reportId);
        formData.append('chemical_recommendations', JSON.stringify(data));

        // Show loading state
        const saveBtn = document.getElementById('saveRecommendationsBtn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        }

        // Send AJAX request to save recommendations
        fetch('save_chemical_recommendation.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            // Reset save button
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Recommendations';
            }

            // Handle the response
            if (data.success) {
                // Show success message
                alert('Chemical recommendations saved successfully!');

                // Add saved indicator
                const resultContainer = document.getElementById('recommendationsResult');
                if (resultContainer) {
                    const savedIndicator = document.createElement('div');
                    savedIndicator.className = 'alert alert-success';
                    savedIndicator.innerHTML = `
                        <i class="fas fa-check-circle"></i>
                        <span>Recommendations saved successfully!</span>
                    `;
                    resultContainer.prepend(savedIndicator);
                }
            } else {
                // Show error message
                alert('Error: ' + (data.message || 'Failed to save recommendations'));
            }
        })
        .catch(error => {
            // Reset save button
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Recommendations';
            }

            // Show error message
            alert('Error: ' + error.message);
        });
    }
});
