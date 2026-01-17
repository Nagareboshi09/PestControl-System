<!-- Floating Action Button Component -->
<div class="fab-container">
    <button class="fab-button" aria-label="Show scheduling procedure" id="fab-main-button">
        <i class="fas fa-question"></i>
    </button>
</div>

<!-- Add inline script for immediate animation testing -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Test animation directly
    setTimeout(function() {
        const fabButton = document.getElementById('fab-main-button');
        if (fabButton) {
            console.log('Direct animation test');
            fabButton.style.animation = 'bounce 1s ease infinite';
        }
    }, 1000);
});
</script>

<!-- Procedure Steps Container -->
<div class="procedure-steps">
    <div class="procedure-steps-header">
        <h3>Scheduling Procedure</h3>
        <button class="procedure-steps-close" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="procedure-steps-content">
        <p>Follow these steps to schedule a pest control service:</p>
        <ol>
            <li>Navigate to the <strong>Schedule Appointment</strong> page from the sidebar menu.</li>
            <li>Select a <strong>date</strong> from the calendar (we're closed on Sundays).</li>
            <li>Choose an available <strong>time slot</strong> from the options displayed.</li>
            <li>Fill in your <strong>service location details</strong> in the form that appears.</li>
            <li>Select the <strong>pest control service</strong> you need from the dropdown menu.</li>
            <li>Add any <strong>special instructions</strong> or notes for our technicians.</li>
            <li>Review your appointment details in the summary section.</li>
            <li>Click the <strong>Schedule Appointment</strong> button to confirm your booking.</li>
            <li>You'll receive a <strong>confirmation notification</strong> once your appointment is scheduled.</li>
            <li>Our team will contact you to confirm the details before your scheduled service.</li>
        </ol>
        <p>If you need to reschedule or cancel an appointment, please contact our customer service at least 24 hours in advance.</p>
    </div>
</div>
