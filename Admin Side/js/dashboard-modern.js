/**
 * Modern Dashboard JavaScript
 * This file contains all the JavaScript functionality for the dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all charts
    initializeCharts();
    
    // Initialize the map
    initializeMap();
    
    // Initialize progress circles
    initializeProgressCircles();
    
    // Add event listeners for dropdowns
    initializeDropdowns();
});

/**
 * Initialize all charts on the dashboard
 */
function initializeCharts() {
    // Weekly Sales Chart
    if (document.getElementById('weeklySalesChart')) {
        const weeklySalesCtx = document.getElementById('weeklySalesChart').getContext('2d');
        new Chart(weeklySalesCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Sales',
                    data: [12, 19, 15, 17, 22, 24, 20],
                    borderColor: '#2563EB',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#2563EB',
                    pointRadius: 0,
                    pointHoverRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: '#FFF',
                        titleColor: '#111827',
                        bodyColor: '#4B5563',
                        borderColor: '#E5E7EB',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return `$${context.raw}K`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        display: false
                    },
                    y: {
                        display: false,
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Total Jobs Chart
    if (document.getElementById('totalJobsChart')) {
        const totalJobsCtx = document.getElementById('totalJobsChart').getContext('2d');
        new Chart(totalJobsCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Jobs',
                    data: [65, 59, 80, 81, 56, 55],
                    backgroundColor: '#10B981',
                    borderRadius: 4,
                    barThickness: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: '#FFF',
                        titleColor: '#111827',
                        bodyColor: '#4B5563',
                        borderColor: '#E5E7EB',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false
                    }
                },
                scales: {
                    x: {
                        display: false
                    },
                    y: {
                        display: false,
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Market Share Chart
    if (document.getElementById('marketShareChart')) {
        const marketShareCtx = document.getElementById('marketShareChart').getContext('2d');
        new Chart(marketShareCtx, {
            type: 'doughnut',
            data: {
                labels: ['Ants', 'Flies', 'Mice', 'Termites', 'Other'],
                datasets: [{
                    data: [30, 25, 20, 15, 10],
                    backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'],
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#FFF',
                        titleColor: '#111827',
                        bodyColor: '#4B5563',
                        borderColor: '#E5E7EB',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.raw}%`;
                            }
                        }
                    }
                }
            }
        });
    }

    // Monthly Sales Chart
    if (document.getElementById('monthlySalesChart')) {
        const monthlySalesCtx = document.getElementById('monthlySalesChart').getContext('2d');
        new Chart(monthlySalesCtx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'This Month',
                    data: [42, 49, 53, 58],
                    borderColor: '#2563EB',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#2563EB',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }, {
                    label: 'Last Month',
                    data: [35, 41, 45, 50],
                    borderColor: '#9CA3AF',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.4,
                    fill: false,
                    pointBackgroundColor: '#9CA3AF',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            boxWidth: 10,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: '#FFF',
                        titleColor: '#111827',
                        bodyColor: '#4B5563',
                        borderColor: '#E5E7EB',
                        borderWidth: 1,
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: $${context.raw}K`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [2, 2]
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value + 'K';
                            }
                        }
                    }
                }
            }
        });
    }

    // Top Products Chart
    if (document.getElementById('topProductsChart')) {
        const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
        new Chart(topProductsCtx, {
            type: 'bar',
            data: {
                labels: ['Pest Control', 'Termite Treatment', 'Rodent Control', 'Mosquito Control', 'Bed Bug Treatment'],
                datasets: [{
                    label: 'Revenue',
                    data: [65, 59, 80, 81, 56],
                    backgroundColor: [
                        '#3B82F6',
                        '#10B981',
                        '#F59E0B',
                        '#8B5CF6',
                        '#EC4899'
                    ],
                    borderRadius: 4,
                    barThickness: 12
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#FFF',
                        titleColor: '#111827',
                        bodyColor: '#4B5563',
                        borderColor: '#E5E7EB',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return `$${context.raw}K`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            display: false
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value + 'K';
                            }
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
}

/**
 * Initialize the map on the dashboard
 */
function initializeMap() {
    if (document.getElementById('locationMap')) {
        // Create a map centered on a default location
        const map = L.map('locationMap').setView([14.5995, 120.9842], 11); // Manila, Philippines

        // Add the OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Add some sample markers
        const locations = [
            { lat: 14.5995, lng: 120.9842, count: 12, name: 'Manila' },
            { lat: 14.6091, lng: 121.0223, count: 8, name: 'Quezon City' },
            { lat: 14.5176, lng: 121.0509, count: 5, name: 'Makati' },
            { lat: 14.5378, lng: 121.0014, count: 3, name: 'Pasay' },
            { lat: 14.6760, lng: 121.0437, count: 7, name: 'Caloocan' }
        ];

        // Add markers to the map
        locations.forEach(location => {
            // Create a custom icon with the count
            const customIcon = L.divIcon({
                className: 'custom-marker',
                html: `<div style="background-color: #2563EB; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">${location.count}</div>`,
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });

            // Add the marker to the map
            L.marker([location.lat, location.lng], { icon: customIcon })
                .addTo(map)
                .bindPopup(`<b>${location.name}</b><br>${location.count} projects`);
        });
    }
}

/**
 * Initialize progress circles on the dashboard
 */
function initializeProgressCircles() {
    document.querySelectorAll('.progress-circle').forEach(circle => {
        const value = circle.getAttribute('data-value');
        const radius = circle.classList.contains('large') ? 54 : 36;
        const circumference = 2 * Math.PI * radius;
        
        // Create SVG element
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('width', radius * 2 + 8);
        svg.setAttribute('height', radius * 2 + 8);
        svg.setAttribute('viewBox', `0 0 ${radius * 2 + 8} ${radius * 2 + 8}`);
        
        // Create background circle
        const bgCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        bgCircle.setAttribute('cx', radius + 4);
        bgCircle.setAttribute('cy', radius + 4);
        bgCircle.setAttribute('r', radius);
        bgCircle.setAttribute('fill', 'none');
        bgCircle.setAttribute('stroke', '#E5E7EB');
        bgCircle.setAttribute('stroke-width', '4');
        
        // Create progress circle
        const progressCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        progressCircle.setAttribute('cx', radius + 4);
        progressCircle.setAttribute('cy', radius + 4);
        progressCircle.setAttribute('r', radius);
        progressCircle.setAttribute('fill', 'none');
        progressCircle.setAttribute('stroke', '#2563EB');
        progressCircle.setAttribute('stroke-width', '4');
        progressCircle.setAttribute('stroke-dasharray', circumference);
        progressCircle.setAttribute('stroke-dashoffset', circumference - (value / 100) * circumference);
        progressCircle.setAttribute('transform', `rotate(-90 ${radius + 4} ${radius + 4})`);
        
        // Append circles to SVG
        svg.appendChild(bgCircle);
        svg.appendChild(progressCircle);
        
        // Insert SVG before the value span
        circle.insertBefore(svg, circle.firstChild);
    });
}

/**
 * Initialize dropdown event listeners
 */
function initializeDropdowns() {
    // Working Time Filter
    const workingTimeFilter = document.getElementById('workingTimeFilter');
    if (workingTimeFilter) {
        workingTimeFilter.addEventListener('change', function() {
            // Handle filter change
            console.log('Working time filter changed to:', this.value);
        });
    }
    
    // Sales Period Filter
    const salesPeriodFilter = document.getElementById('salesPeriodFilter');
    if (salesPeriodFilter) {
        salesPeriodFilter.addEventListener('change', function() {
            // Handle filter change
            console.log('Sales period filter changed to:', this.value);
        });
    }
    
    // Location Period Filter
    const locationPeriodFilter = document.getElementById('locationPeriodFilter');
    if (locationPeriodFilter) {
        locationPeriodFilter.addEventListener('change', function() {
            // Handle filter change
            console.log('Location period filter changed to:', this.value);
        });
    }
    
    // Product Period Filter
    const productPeriodFilter = document.getElementById('productPeriodFilter');
    if (productPeriodFilter) {
        productPeriodFilter.addEventListener('change', function() {
            // Handle filter change
            console.log('Product period filter changed to:', this.value);
        });
    }
    
    // Bandwidth Period Filter
    const bandwidthPeriodFilter = document.getElementById('bandwidthPeriodFilter');
    if (bandwidthPeriodFilter) {
        bandwidthPeriodFilter.addEventListener('change', function() {
            // Handle filter change
            console.log('Bandwidth period filter changed to:', this.value);
        });
    }
}
