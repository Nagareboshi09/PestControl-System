<?php
/**
 * Add Floating Action Button to Client Side Pages
 * 
 * This script adds the necessary CSS and JavaScript files for the floating action button
 * and includes the floating action button HTML.
 * 
 * Usage:
 * 1. Include this file at the end of the main content section of each client-side page
 * 2. Make sure the CSS and JavaScript files are properly linked
 */

// Function to check if a string contains a substring
function contains($haystack, $needle) {
    return strpos($haystack, $needle) !== false;
}

// Get the current page URL
$current_page = $_SERVER['PHP_SELF'];

// Add the floating action button HTML
include 'includes/floating-action-button.php';

// Check if the floating action button CSS is already included
$css_included = false;
foreach (get_included_files() as $file) {
    if (contains($file, 'floating-action-button.css')) {
        $css_included = true;
        break;
    }
}

// If the CSS is not included, add it
if (!$css_included) {
    echo '<link rel="stylesheet" href="css/floating-action-button.css">';
}

// Check if the floating action button JavaScript is already included
$js_included = false;
foreach (get_included_files() as $file) {
    if (contains($file, 'floating-action-button.js')) {
        $js_included = true;
        break;
    }
}

// If the JavaScript is not included, add it
if (!$js_included) {
    echo '<script src="js/floating-action-button.js"></script>';
}
?>
