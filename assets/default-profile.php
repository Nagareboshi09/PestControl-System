<?php
// This script generates a simple default profile image with the first letter of "Admin"

// Set the content type header to image/png
header('Content-Type: image/png');

// Create a 200x200 image
$image = imagecreatetruecolor(200, 200);

// Define colors
$blue = imagecolorallocate($image, 59, 130, 246); // #3b82f6
$white = imagecolorallocate($image, 255, 255, 255);

// Fill the background with blue
imagefill($image, 0, 0, $blue);

// Set the font size and calculate text position
$font_size = 100;
$letter = 'A';

// Get the bounding box of the text
$text_box = imagettfbbox($font_size, 0, 'arial.ttf', $letter);

// If the font is not available, use a simpler approach
if (!$text_box) {
    // Draw the letter 'A' in the center
    imagestring($image, 5, 85, 75, $letter, $white);
} else {
    // Calculate the x and y coordinates for centering the text
    $text_width = $text_box[2] - $text_box[0];
    $text_height = $text_box[7] - $text_box[1];
    $x = (200 - $text_width) / 2;
    $y = (200 - $text_height) / 2 + $text_height;
    
    // Add the letter to the image
    imagettftext($image, $font_size, 0, $x, $y, $white, 'arial.ttf', $letter);
}

// Output the image
imagepng($image);

// Free up memory
imagedestroy($image);
?>
