// Chemical Recommendations functionality for job order quotation

document.addEventListener('DOMContentLoaded', function() {
    // Get the job order modal
    const jobOrderModal = document.getElementById('jobOrderModal');
    
    if (!jobOrderModal) return;
    
    // Get the chemical recommendations elements
    const generateBtn = document.getElementById('generateQuotationRecommendationsBtn');
    const loadingIndicator = document.getElementById('quotationRecommendationsLoading');
    const resultContainer = document.getElementById('quotationRecommendationsResult');
    const selectedChemicalsInput = document.getElementById('selectedChemicals');
    
    if (!generateBtn || !loadingIndicator || !resultContainer || !selectedChemicalsInput) return;
    
    // Add event listener to the generate recommendations button
    generateBtn.addEventListener('click', function() {
        // Get the report ID, pest types, and area
        const reportId = document.getElementById('modalReportId').value;
        
        // Get pest types from the details modal or from a data attribute
        let pestTypes = '';
        if (document.getElementById('detailPestTypes')) {
            pestTypes = document.getElementById('detailPestTypes').textContent;
        } else if (createJobFromDetailsBtn && createJobFromDetailsBtn.getAttribute('data-pest-types')) {
            pestTypes = createJobFromDetailsBtn.getAttribute('data-pest-types');
        }
        
        // Get area from the details modal or from a data attribute
        let area = 0;
        if (document.getElementById('detailArea')) {
            const areaText = document.getElementById('detailArea').textContent;
            area = parseFloat(areaText.replace(/[^0-9.]/g, ''));
        } else if (createJobFromDetailsBtn && createJobFromDetailsBtn.getAttribute('data-area')) {
            area = parseFloat(createJobFromDetailsBtn.getAttribute('data-area'));
        }
        
        const applicationMethod = document.getElementById('applicationMethod').value;
        
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
                displayQuotationRecommendations(data, resultContainer);
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
    
    // Function to display recommendations with checkboxes
    function displayQuotationRecommendations(data, container) {
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
                                <th>Select</th>
                                <th>Chemical</th>
                                <th>Type</th>
                                <th>Recommended Dosage</th>
                                <th>Available Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            // Add each chemical with a checkbox
            chemicals.forEach(chemical => {
                html += `
                    <tr>
                        <td>
                            <input type="checkbox" class="chemical-checkbox" 
                                data-id="${chemical.id}" 
                                data-name="${chemical.chemical_name}" 
                                data-type="${chemical.type}" 
                                data-dosage="${chemical.recommended_dosage}" 
                                data-dosage-unit="${chemical.dosage_unit}" 
                                data-target-pest="${targetPest}">
                        </td>
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
        
        html += `
            </div>
            <div class="alert alert-info" style="margin-top: 15px;">
                <i class="fas fa-info-circle"></i>
                <span>Please select the chemicals you want to include in the quotation. You can select multiple chemicals if there are multiple pest types.</span>
            </div>
        `;
        
        // Set the HTML
        container.innerHTML = html;
        
        // Add event listeners to checkboxes
        const checkboxes = container.querySelectorAll('.chemical-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedChemicals);
        });
        
        // Initialize selected chemicals
        updateSelectedChemicals();
    }
    
    // Function to update the selected chemicals hidden input
    function updateSelectedChemicals() {
        const checkboxes = document.querySelectorAll('.chemical-checkbox:checked');
        const selectedChemicals = [];
        
        checkboxes.forEach(checkbox => {
            selectedChemicals.push({
                id: checkbox.getAttribute('data-id'),
                name: checkbox.getAttribute('data-name'),
                type: checkbox.getAttribute('data-type'),
                dosage: checkbox.getAttribute('data-dosage'),
                dosage_unit: checkbox.getAttribute('data-dosage-unit'),
                target_pest: checkbox.getAttribute('data-target-pest')
            });
        });
        
        // Update the hidden input with the selected chemicals
        selectedChemicalsInput.value = JSON.stringify(selectedChemicals);
    }
    
    // Add event listener to the create job from details button
    const createJobFromDetailsBtn = document.getElementById('createJobFromDetailsBtn');
    if (createJobFromDetailsBtn) {
        const originalClickHandler = createJobFromDetailsBtn.onclick;
        
        createJobFromDetailsBtn.onclick = function(event) {
            // Store pest types and area as data attributes
            if (document.getElementById('detailPestTypes')) {
                this.setAttribute('data-pest-types', document.getElementById('detailPestTypes').textContent);
            }
            
            if (document.getElementById('detailArea')) {
                const areaText = document.getElementById('detailArea').textContent;
                this.setAttribute('data-area', parseFloat(areaText.replace(/[^0-9.]/g, '')));
            }
            
            // Call the original click handler
            if (originalClickHandler) {
                originalClickHandler.call(this, event);
            }
        };
    }
});
