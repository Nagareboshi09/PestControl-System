<?php
/**
 * Common Footer for Client Side Pages
 * 
 * This file includes common elements that should appear at the bottom of all client-side pages,
 * such as the floating action button.
 */

// Include the floating action button
include 'includes/floating-action-button.php';

// Add the floating action button CSS if not already included
if (!defined('FAB_CSS_INCLUDED')) {
    echo '<link rel="stylesheet" href="css/floating-action-button.css">';
    define('FAB_CSS_INCLUDED', true);
}

// Add the floating action button JavaScript if not already included
if (!defined('FAB_JS_INCLUDED')) {
    echo '<script src="js/floating-action-button.js"></script>';
    define('FAB_JS_INCLUDED', true);
}
?>
