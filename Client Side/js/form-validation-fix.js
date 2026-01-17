/**
 * Form validation fixes
 * This script fixes common form validation issues
 */
document.addEventListener('DOMContentLoaded', function() {
    // Fix duplicate validation messages
    fixDuplicateValidationMessages();
});

/**
 * Fix duplicate validation messages
 * This function removes duplicate validation messages that might be added by both
 * browser native validation and custom validation scripts
 */
function fixDuplicateValidationMessages() {
    // Get all form elements
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        // Get all required inputs in the form
        const requiredInputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        
        requiredInputs.forEach(input => {
            // Get the parent element
            const parent = input.parentNode;
            
            // Get all validation message elements
            const feedbackDivs = parent.querySelectorAll('.invalid-feedback');
            
            // If there are multiple feedback divs, remove all but the first one
            if (feedbackDivs.length > 1) {
                for (let i = 1; i < feedbackDivs.length; i++) {
                    parent.removeChild(feedbackDivs[i]);
                }
            }
            
            // Ensure only one validation message is shown
            input.addEventListener('invalid', function(event) {
                // Prevent the browser's default validation bubble
                event.preventDefault();
                
                // Get or create a validation message element
                let feedbackDiv = parent.querySelector('.invalid-feedback');
                if (!feedbackDiv) {
                    feedbackDiv = document.createElement('div');
                    feedbackDiv.className = 'invalid-feedback';
                    parent.appendChild(feedbackDiv);
                }
                
                // Set the validation message
                feedbackDiv.textContent = this.validationMessage || 'This field is required';
                
                // Add invalid class to show the message
                this.classList.add('is-invalid');
            });
            
            // Clear validation message when input is valid
            input.addEventListener('input', function() {
                if (this.checkValidity()) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        });
    });
    
    // Log that the fix has been applied
    console.log('Form validation fix applied: Removed duplicate validation messages');
}
