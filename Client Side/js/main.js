/**
 * Main JavaScript file for client-side functionality
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize toast notifications
    initToasts();

    // Add animation to cards
    animateCards();

    // Initialize form validation
    initFormValidation();

    // Prevent duplicate form submissions
    preventDuplicateSubmissions();
});

/**
 * Toast notification system
 */
function initToasts() {
    // Create toast container if it doesn't exist
    if (!document.querySelector('.toast-container')) {
        const toastContainer = document.createElement('div');
        toastContainer.classList.add('toast-container');
        document.body.appendChild(toastContainer);
    }

    // Toast creation function
    window.showToast = function(message, type = 'info', duration = 3000) {
        const toastContainer = document.querySelector('.toast-container');
        const toast = document.createElement('div');
        toast.classList.add('toast', `toast-${type}`);

        toast.innerHTML = `
            <div class="toast-content">${message}</div>
            <button class="toast-close">&times;</button>
        `;

        toastContainer.appendChild(toast);

        // Close button functionality
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => {
            toast.style.opacity = '0';
            setTimeout(() => {
                toastContainer.removeChild(toast);
            }, 300);
        });

        // Auto-close after duration
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                if (toast.parentNode === toastContainer) {
                    toastContainer.removeChild(toast);
                }
            }, 300);
        }, duration);
    };
}

/**
 * Animate cards on page load
 */
function animateCards() {
    const cards = document.querySelectorAll('.card, .enhanced-card');

    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';

        setTimeout(() => {
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 * index);
    });
}

/**
 * Form validation
 */
function initFormValidation() {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, select, textarea');

        inputs.forEach(input => {
            if (input.hasAttribute('required')) {
                // Add validation styling
                input.addEventListener('blur', () => {
                    if (input.value.trim() === '') {
                        input.classList.add('is-invalid');
                    } else {
                        input.classList.remove('is-invalid');
                        input.classList.add('is-valid');
                    }
                });

                // Add validation message
                const feedbackDiv = document.createElement('div');
                feedbackDiv.classList.add('invalid-feedback');
                feedbackDiv.textContent = 'This field is required';
                input.parentNode.appendChild(feedbackDiv);
            }

            // Email validation
            if (input.type === 'email') {
                input.addEventListener('blur', () => {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (input.value.trim() !== '' && !emailRegex.test(input.value)) {
                        input.classList.add('is-invalid');
                        const feedbackDiv = input.parentNode.querySelector('.invalid-feedback');
                        if (feedbackDiv) {
                            feedbackDiv.textContent = 'Please enter a valid email address';
                        }
                    }
                });
            }
        });
    });
}

/**
 * Prevent duplicate form submissions
 */
function preventDuplicateSubmissions() {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        // Skip forms with ajax-form class as they're handled separately
        if (form.classList.contains('ajax-form')) {
            return;
        }

        form.addEventListener('submit', function(e) {
            // If the form is already submitting, prevent another submission
            if (this.classList.contains('is-submitting')) {
                e.preventDefault();
                return false;
            }

            // Mark the form as submitting
            this.classList.add('is-submitting');

            // Disable submit button
            const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';

                // If the form submission is successful, keep the button disabled
                // Otherwise, re-enable it after a timeout (in case of error)
                setTimeout(() => {
                    if (this.classList.contains('is-submitting')) {
                        this.classList.remove('is-submitting');
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                }, 5000);
            }
        });
    });
}

/**
 * Reset form submission state
 * @param {HTMLFormElement} form - The form to reset
 */
window.resetFormSubmitState = function(form) {
    if (!form) return;

    // Remove submitting class
    form.classList.remove('is-submitting');

    // Re-enable submit button
    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
    if (submitBtn && submitBtn.dataset.originalHtml) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = submitBtn.dataset.originalHtml;
        delete submitBtn.dataset.originalHtml;
    }
}

/**
 * Date formatting utility
 */
function formatDate(dateString, format = 'long') {
    const date = new Date(dateString);

    if (format === 'long') {
        return date.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    } else if (format === 'short') {
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } else if (format === 'time') {
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        });
    } else if (format === 'datetime') {
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    return date.toLocaleDateString();
}
