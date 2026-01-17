/**
 * Reports Charts JavaScript
 * This file contains functions to initialize and manage charts for the reports page
 */

// Initialize all charts when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeAssessmentChart();
    initializeJobOrderChart();
    initializeUserChart();
    initializeContractChart();
    initializeRevenueChart();
});

/**
 * Initialize Assessment Reports Chart
 */
function initializeAssessmentChart() {
    const assessmentCtx = document.getElementById('assessmentChart');
    if (!assessmentCtx) return;

    const assessmentChart = new Chart(assessmentCtx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: assessmentMonths,
            datasets: [{
                label: 'Assessment Reports',
                data: assessmentCounts,
                backgroundColor: '#3B82F6',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

/**
 * Initialize Job Order Chart
 */
function initializeJobOrderChart() {
    const jobOrderCtx = document.getElementById('jobOrderChart');
    if (!jobOrderCtx) return;

    const jobOrderChart = new Chart(jobOrderCtx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: jobOrderDays,
            datasets: [{
                label: 'Job Orders',
                data: jobOrderCounts,
                backgroundColor: '#10B981',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

/**
 * Initialize User Distribution Chart
 */
function initializeUserChart() {
    const userCtx = document.getElementById('userChart');
    if (!userCtx) return;

    const userChart = new Chart(userCtx.getContext('2d'), {
        type: 'pie',
        data: {
            labels: ['Clients', 'Admin', 'Technicians'],
            datasets: [{
                data: userCounts,
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56'],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
}

/**
 * Initialize Contracts Accepted Chart
 */
function initializeContractChart() {
    const contractCtx = document.getElementById('contractChart');
    if (!contractCtx) return;

    const contractChart = new Chart(contractCtx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: contractMonths,
            datasets: [{
                label: 'Contracts Accepted',
                data: contractCounts,
                backgroundColor: '#F59E0B',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

/**
 * Initialize Revenue Chart
 */
function initializeRevenueChart() {
    const revenueCtx = document.getElementById('revenueChart');
    if (!revenueCtx) return;

    const revenueChart = new Chart(revenueCtx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: revenueMonths,
            datasets: [{
                label: 'Revenue',
                data: revenueTotals,
                backgroundColor: '#8B5CF6',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += '₱' + context.parsed.y.toLocaleString();
                            return label;
                        }
                    }
                }
            }
        }
    });
}
