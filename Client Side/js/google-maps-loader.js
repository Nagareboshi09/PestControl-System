/**
 * Google Maps API Loader
 * 
 * This file handles loading the Google Maps API and provides fallback mechanisms
 */

// Array of API keys to try
const apiKeys = [
    'AIzaSyDLQEIfvCNg9bDQCJQvjNg8tJJdnZpuUlk',
    'AIzaSyB41DRUbKWJHPxaFjMAwdrzWzbVKartNGg',
    // Add more API keys here if needed
];

// Current API key index
let currentKeyIndex = 0;

// Flag to track if we're already trying to load the API
let isLoadingApi = false;

// Function to load the Google Maps API
function loadGoogleMapsApi(callback) {
    if (isLoadingApi) return;
    isLoadingApi = true;
    
    console.log('Loading Google Maps API with key index:', currentKeyIndex);
    
    // Create script element
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKeys[currentKeyIndex]}&libraries=places&callback=googleMapsCallback`;
    script.async = true;
    script.defer = true;
    
    // Handle errors
    script.onerror = function() {
        console.error('Failed to load Google Maps API with key index:', currentKeyIndex);
        currentKeyIndex++;
        
        if (currentKeyIndex < apiKeys.length) {
            console.log('Trying next API key...');
            isLoadingApi = false;
            loadGoogleMapsApi(callback);
        } else {
            console.error('All API keys failed. Could not load Google Maps API.');
            document.body.innerHTML += '<div style="position: fixed; top: 0; left: 0; right: 0; background-color: #f44336; color: white; padding: 10px; text-align: center; z-index: 9999;">Failed to load Google Maps. Please try again later.</div>';
        }
    };
    
    // Define callback
    window.googleMapsCallback = function() {
        console.log('Google Maps API loaded successfully with key index:', currentKeyIndex);
        isLoadingApi = false;
        
        if (typeof callback === 'function') {
            callback();
        }
        
        // Call the original callback if it exists
        if (typeof window.initGoogleMapsAPI === 'function') {
            window.initGoogleMapsAPI();
        }
    };
    
    // Add script to document
    document.head.appendChild(script);
}

// Check if the API is already loaded
if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
    console.log('Google Maps API not detected, loading it now...');
    
    // If initGoogleMapsAPI is already defined, store it
    if (typeof window.initGoogleMapsAPI === 'function') {
        const originalCallback = window.initGoogleMapsAPI;
        window.initGoogleMapsAPI = function() {
            console.log('Custom initGoogleMapsAPI called');
            originalCallback();
        };
    }
    
    // Load the API
    document.addEventListener('DOMContentLoaded', function() {
        loadGoogleMapsApi();
    });
} else {
    console.log('Google Maps API already loaded');
}
