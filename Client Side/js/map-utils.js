/**
 * Map Utilities for MacJ Pest Control
 *
 * This file contains utility functions for working with maps and location data
 */

// Function to extract coordinates from a location string
function extractCoordinates(locationString) {
    // Check if the location string contains coordinates in the format [lat,lng]
    const coordsMatch = locationString.match(/\[([-\d.]+),([-\d.]+)\]$/);

    if (coordsMatch && coordsMatch.length === 3) {
        // Return the coordinates as an object
        return {
            lat: parseFloat(coordsMatch[1]),
            lng: parseFloat(coordsMatch[2])
        };
    }

    // Return null if no coordinates found
    return null;
}

// Function to initialize a static map for displaying a location
function initStaticMap(mapElementId, locationString) {
    console.log('Initializing static map for', mapElementId, 'with location:', locationString);

    // Check if Google Maps API is loaded
    if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
        console.error('Google Maps API not loaded when trying to initialize map:', mapElementId);

        // Add a message to the map container
        const mapElement = document.getElementById(mapElementId);
        if (mapElement) {
            mapElement.innerHTML = '<div style="padding: 20px; text-align: center; background-color: #f8f9fa;">Map loading... Please wait.</div>';

            // Try again after a delay
            setTimeout(function() {
                initStaticMap(mapElementId, locationString);
            }, 2000);
        }
        return;
    }

    // Try to extract coordinates from the location string
    const coords = extractCoordinates(locationString);
    console.log('Extracted coordinates:', coords);

    // Get the map container element
    const mapElement = document.getElementById(mapElementId);

    // If no map element found, return
    if (!mapElement) {
        console.error('Map element not found:', mapElementId);
        return;
    }

    // If coordinates were found, initialize the map
    if (coords) {
        console.log('Using extracted coordinates for map');
        try {
            // Create a map centered at the coordinates
            const map = new google.maps.Map(mapElement, {
                center: coords,
                zoom: 15,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true
            });

            // Add a marker at the coordinates
            new google.maps.Marker({
                position: coords,
                map: map
            });

            // Show the map container
            mapElement.style.display = 'block';
            console.log('Map initialized successfully with coordinates');
        } catch (error) {
            console.error('Error initializing map with coordinates:', error);
        }
    } else {
        console.log('No coordinates found, trying to geocode address');
        // If no coordinates found, try to geocode the address
        const geocoder = new google.maps.Geocoder();

        // Clean the location string (remove any coordinate notation)
        const cleanAddress = locationString.replace(/\s*\[[-\d.,]+\]$/, '');
        console.log('Clean address for geocoding:', cleanAddress);

        // Geocode the address
        geocoder.geocode({ 'address': cleanAddress }, function(results, status) {
            console.log('Geocode results:', status, results);
            if (status === 'OK' && results[0]) {
                try {
                    // Create a map centered at the geocoded location
                    const map = new google.maps.Map(mapElement, {
                        center: results[0].geometry.location,
                        zoom: 15,
                        mapTypeControl: false,
                        streetViewControl: false,
                        fullscreenControl: true
                    });

                    // Add a marker at the geocoded location
                    new google.maps.Marker({
                        position: results[0].geometry.location,
                        map: map
                    });

                    // Show the map container
                    mapElement.style.display = 'block';
                    console.log('Map initialized successfully with geocoded address');
                } catch (error) {
                    console.error('Error initializing map with geocoded address:', error);
                }
            } else {
                // If geocoding failed, try with a default location
                console.error('Geocode was not successful for the following reason: ' + status);
                try {
                    // Use a default location (Manila, Philippines)
                    const defaultLocation = { lat: 14.5995, lng: 120.9842 };
                    const map = new google.maps.Map(mapElement, {
                        center: defaultLocation,
                        zoom: 10,
                        mapTypeControl: false,
                        streetViewControl: false,
                        fullscreenControl: true
                    });

                    // Show the map container
                    mapElement.style.display = 'block';
                    console.log('Map initialized with default location');
                } catch (error) {
                    console.error('Error initializing map with default location:', error);
                    mapElement.style.display = 'none';
                }
            }
        });
    }
}
