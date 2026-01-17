<?php
session_start();
include '../db_connect.php';
include '../notification_functions.php';

// Ensure the user is logged in
if (!isset($_SESSION['client_id'])) {
    header("Location: login.php");
    exit();
}

$client_id = $_SESSION['client_id'];
$message = "";

// Fetch client data
$query = "SELECT first_name, last_name, email, contact_number, location_address, type_of_place, location_lat, location_lng FROM clients WHERE client_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();
$stmt->close();

// Extract coordinates from location_address if they exist and are not already in location_lat/location_lng
if (empty($client['location_lat']) || empty($client['location_lng'])) {
    // Check if coordinates are embedded in the address string
    if (preg_match('/\[([-\d\.]+),([-\d\.]+)\]$/', $client['location_address'], $matches)) {
        $client['location_lat'] = $matches[1];
        $client['location_lng'] = $matches[2];

        // Update the database with the extracted coordinates
        $updateCoords = $conn->prepare("UPDATE clients SET location_lat = ?, location_lng = ? WHERE client_id = ?");
        $updateCoords->bind_param("ssi", $client['location_lat'], $client['location_lng'], $client_id);
        $updateCoords->execute();
        $updateCoords->close();
    }
}

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a confirmation submission
    if (isset($_POST['confirm_update']) && $_POST['confirm_update'] === 'true') {
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $contact_number = $_POST['contact_number'];
        $location_address = $_POST['location_address'];
        $type_of_place = $_POST['type_of_place'];
        $new_password = $_POST['new_password'];
        $location_lat = isset($_POST['location_lat']) ? $_POST['location_lat'] : null;
        $location_lng = isset($_POST['location_lng']) ? $_POST['location_lng'] : null;

        // Prepare full address with coordinates if available
        if ($location_lat && $location_lng) {
            // Store coordinates with the address for future map display
            $location_address = $location_address . ' [' . $location_lat . ',' . $location_lng . ']';
        }

        // Hash new password if provided
        $password_query = "";
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $password_query = ", password = '$hashed_password'";
        }

        // Update client details
        $update_query = "UPDATE clients SET first_name = ?, last_name = ?, email = ?, contact_number = ?, location_address = ?, type_of_place = ?, location_lat = ?, location_lng = ? $password_query WHERE client_id = ?";
        $stmt = $conn->prepare($update_query);
        if (empty($new_password)) {
            $stmt->bind_param("ssssssssi", $first_name, $last_name, $email, $contact_number, $location_address, $type_of_place, $location_lat, $location_lng, $client_id);
        } else {
            $stmt->bind_param("ssssssssi", $first_name, $last_name, $email, $contact_number, $location_address, $type_of_place, $location_lat, $location_lng, $client_id);
        }
        if ($stmt->execute()) {
            $message = "Profile updated successfully!";

            // Update the client data in the session to reflect changes
            $_SESSION['fullname'] = $first_name . ' ' . $last_name;

            // Update the client variable to reflect the changes without page refresh
            $client['first_name'] = $first_name;
            $client['last_name'] = $last_name;
            $client['email'] = $email;
            $client['contact_number'] = $contact_number;
            $client['location_address'] = $location_address;
            $client['type_of_place'] = $type_of_place;
        } else {
            $message = "Error updating profile.";
        }
        $stmt->close();
    }

    // For AJAX requests, return JSON response
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => strpos($message ?? '', 'success') !== false,
            'message' => $message ?? '',
            'client' => $client ?? null
        ]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Profile | MacJ Pest Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/sidebar-fix.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/client-common.css">
    <!-- Removed unnecessary CSS files -->
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="css/floating-action-button.css">
    <!-- Leaflet.js for OpenStreetMap -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        /* Ensure validation messages are italicized */
        .invalid-feedback, .text-danger, .error-message {
            font-style: italic !important;
            color: var(--error-color) !important;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-title">
            <!-- Client Portal text removed -->
        </div>
        <div class="user-menu">
            <!-- Notification Icon -->
            <div class="notification-container">
                <i class="fas fa-bell notification-icon"></i>
                <span class="notification-badge" style="display: none;">0</span>

                <!-- Notification Dropdown -->
                <div class="notification-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <span class="mark-all-read">Mark all as read</span>
                    </div>
                    <ul class="notification-list">
                        <!-- Notifications will be loaded here -->
                    </ul>
                </div>
            </div>

            <div class="user-info">
                <div>
                    <div class="user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? '') ?></div>
                    <div class="user-role">Client</div>
                </div>
            </div>
        </div>
    </header>

    <button id="menuToggle"><i class="fas fa-bars"></i></button>

        <aside id="sidebar">
            <div class="sidebar-header">
                <h2>MacJ Pest Control</h2>
                <h3>Welcome, <?= htmlspecialchars($_SESSION['fullname'] ?? '') ?></h3>
            </div>
            <nav class="sidebar-menu">
                <a href="schedule.php">
                    <i class="fas fa-calendar-alt fa-icon"></i>
                    Schedule Appointment
                </a>
                <a href="profile.php" class="active">
                    <i class="fas fa-user fa-icon"></i>
                    My Profile
                </a>
                <a href="inspection_report.php">
                    <i class="fas fa-clipboard-check fa-icon"></i>
                    Inspection Report
                </a>
                <a href="contract.php">
                    <i class="fas fa-file-contract fa-icon"></i>
                    Contract
                </a>
                <a href="job_order_report.php">
                    <i class="fas fa-file-alt fa-icon"></i>
                    Job Order Report
                </a>
                <a href="SignOut.php">
                    <i class="fas fa-sign-out-alt fa-icon"></i>
                    Logout
                </a>
            </nav>
            <div class="sidebar-footer">
                <p>&copy; <?= date('Y') ?> MacJ Pest Control</p>
                <a href="https://www.facebook.com/MACJPEST" target="_blank"><i class="fab fa-facebook"></i> Facebook</a>
            </div>
        </aside>

        <main class="main-content" id="mainContent">
            <div class="page-header">
                <div>
                    <h1>My Profile</h1>
                    <p>Manage your personal information and account settings</p>
                </div>
                <div>
                    <p class="text-light"><?= date('l, F j, Y') ?></p>
                </div>
            </div>

            <div class="card">
                <?php if ($message): ?>
                    <div class="alert <?= strpos($message, 'success') !== false ? 'alert-success' : 'alert-danger' ?>">
                        <i class="fas <?= strpos($message, 'success') !== false ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <div class="card-body">
                    <!-- View Mode -->
                    <div id="profileViewMode">
                        <div class="row">
                            <div class="col-6">
                                <div class="profile-field">
                                    <label class="profile-label">First Name</label>
                                    <div class="profile-value"><?= htmlspecialchars($client['first_name']) ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="profile-field">
                                    <label class="profile-label">Last Name</label>
                                    <div class="profile-value"><?= htmlspecialchars($client['last_name']) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-6">
                                <div class="profile-field">
                                    <label class="profile-label">Email Address</label>
                                    <div class="profile-value"><?= htmlspecialchars($client['email']) ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="profile-field">
                                    <label class="profile-label">Contact Number</label>
                                    <div class="profile-value"><?= htmlspecialchars($client['contact_number']) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-6">
                                <div class="profile-field">
                                    <label class="profile-label">Location Address</label>
                                    <div class="profile-value"><?= htmlspecialchars(preg_replace('/\[[-\d\.]+,[-\d\.]+\]$/', '', $client['location_address'] ?? 'Not set')) ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="profile-field">
                                    <label class="profile-label">Type of Place</label>
                                    <div class="profile-value"><?= htmlspecialchars($client['type_of_place'] ?? 'Not set') ?></div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($client['location_lat']) && !empty($client['location_lng'])): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="profile-field">
                                    <label class="profile-label">Location Map</label>
                                    <div id="profile-map" style="height: 300px; border-radius: 8px; overflow: hidden; border: 1px solid #dee2e6;"></div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-group mt-4">
                            <button type="button" id="editProfileBtn" class="btn btn-primary">
                                <i class="fas fa-edit mr-2"></i> Edit Profile
                            </button>
                        </div>
                    </div>

                    <!-- Edit Mode -->
                    <form method="POST" class="profile-form" id="profileEditMode" style="display: none;">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control"
                                        value="<?= htmlspecialchars($client['first_name']) ?>" required>
                                    <div class="invalid-feedback" style="font-style: italic;">This field is required</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control"
                                        value="<?= htmlspecialchars($client['last_name']) ?>" required>
                                    <div class="invalid-feedback" style="font-style: italic;">This field is required</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" id="email" name="email" class="form-control"
                                        value="<?= htmlspecialchars($client['email']) ?>" required>
                                    <div class="invalid-feedback" style="font-style: italic;">This field is required</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="contact_number" class="form-label">Contact Number</label>
                                    <input type="text" id="contact_number" name="contact_number" class="form-control"
                                        value="<?= htmlspecialchars($client['contact_number']) ?>" required>
                                    <div class="invalid-feedback" style="font-style: italic;">This field is required</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="location_address" class="form-label">Location Address</label>
                                    <input type="text" id="location_address" name="location_address" class="form-control"
                                        value="<?= htmlspecialchars(preg_replace('/\[[-\d\.]+,[-\d\.]+\]$/', '', $client['location_address'] ?? '')) ?>">
                                    <div class="invalid-feedback" style="font-style: italic;">This field is required</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="type_of_place" class="form-label">Type of Place</label>
                                    <select id="type_of_place" name="type_of_place" class="form-control">
                                        <option value="">Select Place Type</option>
                                        <option value="Restaurant" <?= ($client['type_of_place'] == 'Restaurant') ? 'selected' : '' ?>>Restaurant</option>
                                        <option value="House" <?= ($client['type_of_place'] == 'House') ? 'selected' : '' ?>>House</option>
                                        <option value="Condominium" <?= ($client['type_of_place'] == 'Condominium') ? 'selected' : '' ?>>Condominium</option>
                                        <option value="Office" <?= ($client['type_of_place'] == 'Office') ? 'selected' : '' ?>>Office</option>
                                        <option value="Construction" <?= ($client['type_of_place'] == 'Construction') ? 'selected' : '' ?>>Construction Site</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label class="form-label">Location Map</label>
                                    <div class="map-controls mb-2">
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-search"></i>
                                            </span>
                                            <input type="text" id="location-search" class="form-control" placeholder="Search for an address">
                                            <button class="btn btn-outline-secondary" type="button" id="search-button">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end mb-2">
                                        <button type="button" id="current-location-button" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-map-marker-alt"></i> Use My Location
                                        </button>
                                    </div>
                                    <div id="edit-map-container" style="height: 300px; margin-bottom: 15px; border-radius: 8px; overflow: hidden; border: 1px solid #dee2e6;">
                                        <div id="edit-map" style="height: 100%;"></div>
                                    </div>
                                    <input type="hidden" id="location_lat" name="location_lat" value="<?= htmlspecialchars($client['location_lat'] ?? '') ?>">
                                    <input type="hidden" id="location_lng" name="location_lng" value="<?= htmlspecialchars($client['location_lng'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group password-toggle">
                            <label for="new_password" class="form-label">New Password (leave blank to keep current)</label>
                            <div class="password-input-container">
                                <input type="password" id="new_password" name="new_password" class="form-control">
                                <i class="fas fa-eye password-toggle-icon" id="togglePassword"></i>
                            </div>
                        </div>

                        <div class="form-group mt-4">
                            <button type="button" id="showConfirmBtn" class="btn btn-primary">
                                <i class="fas fa-save mr-2"></i> Update Profile
                            </button>
                            <button type="button" id="cancelEditBtn" class="btn btn-secondary ml-2">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </button>
                        </div>
                        <input type="hidden" name="confirm_update" id="confirm_update" value="false">
                    </form>

                    <!-- Confirmation Modal -->
                    <div id="confirmationModal" class="modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3>Confirm Profile Update</h3>
                                <span class="close-modal">&times;</span>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to update your profile with the following information?</p>
                                <div class="confirmation-details">
                                    <div class="confirm-row">
                                        <span class="confirm-label">First Name:</span>
                                        <span id="confirm-first-name" class="confirm-value"></span>
                                    </div>
                                    <div class="confirm-row">
                                        <span class="confirm-label">Last Name:</span>
                                        <span id="confirm-last-name" class="confirm-value"></span>
                                    </div>
                                    <div class="confirm-row">
                                        <span class="confirm-label">Email:</span>
                                        <span id="confirm-email" class="confirm-value"></span>
                                    </div>
                                    <div class="confirm-row">
                                        <span class="confirm-label">Contact Number:</span>
                                        <span id="confirm-contact" class="confirm-value"></span>
                                    </div>
                                    <div class="confirm-row">
                                        <span class="confirm-label">Location Address:</span>
                                        <span id="confirm-location" class="confirm-value"></span>
                                    </div>
                                    <div class="confirm-row">
                                        <span class="confirm-label">Type of Place:</span>
                                        <span id="confirm-place-type" class="confirm-value"></span>
                                    </div>
                                    <div class="confirm-row" id="password-changed-row" style="display: none;">
                                        <span class="confirm-label">Password:</span>
                                        <span class="confirm-value">Will be changed</span>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button id="confirmUpdateBtn" class="btn btn-primary">
                                    <i class="fas fa-check mr-2"></i> Confirm Update
                                </button>
                                <button id="cancelUpdateBtn" class="btn btn-secondary ml-2">
                                    <i class="fas fa-times mr-2"></i> Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">Account Security</h3>
                </div>
                <div class="card-body">
                    <p>Protect your account by keeping your password secure and up-to-date.</p>
                    <ul class="security-tips">
                        <li><i class="fas fa-check-circle text-success mr-2"></i> Use a strong, unique password</li>
                        <li><i class="fas fa-check-circle text-success mr-2"></i> Change your password regularly</li>
                        <li><i class="fas fa-check-circle text-success mr-2"></i> Don't share your account credentials</li>
                    </ul>
                </div>
            </div>
        </main>

    <?php include 'includes/floating-action-button.php'; ?>

    <script src="js/sidebar.js"></script>
    <script src="js/main.js"></script>
    <script>
        // Initialize maps
        let viewMap, editMap, editMarker;

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize view map if coordinates exist
            <?php if (!empty($client['location_lat']) && !empty($client['location_lng'])): ?>
            initViewMap();
            <?php endif; ?>

            // Initialize edit map when edit button is clicked
            document.getElementById('editProfileBtn').addEventListener('click', function() {
                // Small delay to ensure the map container is visible
                setTimeout(initEditMap, 100);
            });
        });

        function initViewMap() {
            const lat = <?= !empty($client['location_lat']) ? $client['location_lat'] : '14.5995' ?>;
            const lng = <?= !empty($client['location_lng']) ? $client['location_lng'] : '120.9842' ?>;

            viewMap = L.map('profile-map', {
                center: [lat, lng],
                zoom: 15,
                zoomControl: true,
                scrollWheelZoom: false
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(viewMap);

            // Add marker at the saved location
            const marker = L.marker([lat, lng]).addTo(viewMap);
            marker.bindPopup('Your Location').openPopup();

            // Add a circle to highlight the area
            L.circle([lat, lng], {
                color: '#3B82F6',
                weight: 2,
                fillColor: '#3B82F6',
                fillOpacity: 0.15,
                radius: 200 // 200 meters radius
            }).addTo(viewMap);
        }

        function initEditMap() {
            if (editMap) {
                editMap.invalidateSize();
                return;
            }

            const lat = document.getElementById('location_lat').value || 14.5995;
            const lng = document.getElementById('location_lng').value || 120.9842;

            editMap = L.map('edit-map', {
                center: [lat, lng],
                zoom: 15,
                zoomControl: true,
                scrollWheelZoom: true
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(editMap);

            // Add marker at the saved location if coordinates exist
            if (lat && lng && lat != 14.5995) {
                setEditMarker(L.latLng(lat, lng));
            }

            // Add click event to map
            editMap.on('click', function(e) {
                setEditMarker(e.latlng);
                updateLocationFields(e.latlng);
            });

            // Handle search button click
            document.getElementById('search-button').addEventListener('click', searchLocation);

            // Handle enter key in search input
            document.getElementById('location-search').addEventListener('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    searchLocation();
                }
            });

            // Handle current location button click
            document.getElementById('current-location-button').addEventListener('click', getCurrentLocation);
        }

        function setEditMarker(latlng) {
            // Remove existing marker if any
            if (editMarker) {
                editMap.removeLayer(editMarker);
            }

            // Create a new marker at the clicked position
            editMarker = L.marker(latlng, {
                draggable: true
            }).addTo(editMap);

            // Add popup to the marker
            editMarker.bindPopup('Selected Location<br>Drag to adjust').openPopup();

            // Handle marker drag end event
            editMarker.on('dragend', function() {
                const newPosition = editMarker.getLatLng();
                updateLocationFields(newPosition);
            });

            // Center map on marker
            editMap.setView(latlng, 15);

            // Remove any existing circles
            editMap.eachLayer(function(layer) {
                if (layer instanceof L.Circle) {
                    editMap.removeLayer(layer);
                }
            });

            // Add a circle to highlight the area
            L.circle(latlng, {
                color: '#3B82F6',
                weight: 2,
                fillColor: '#3B82F6',
                fillOpacity: 0.15,
                radius: 200 // 200 meters radius
            }).addTo(editMap);
        }

        function updateLocationFields(latlng) {
            // Format coordinates with 6 decimal places
            const lat = latlng.lat.toFixed(6);
            const lng = latlng.lng.toFixed(6);

            // Update the hidden fields with coordinates
            document.getElementById('location_lat').value = lat;
            document.getElementById('location_lng').value = lng;

            // Use Nominatim for reverse geocoding
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.display_name) {
                        // Update the location field with the found address
                        document.getElementById('location_address').value = data.display_name;
                    } else {
                        // Fallback to coordinates if no address found
                        document.getElementById('location_address').value = `Location at: ${lat}, ${lng}`;
                    }
                })
                .catch(error => {
                    console.error('Error reverse geocoding:', error);
                    // Fallback to coordinates if geocoding fails
                    document.getElementById('location_address').value = `Location at: ${lat}, ${lng}`;
                });
        }

        function searchLocation() {
            const searchTerm = document.getElementById('location-search').value.trim();
            if (!searchTerm) return;

            // Show loading state
            const searchButton = document.getElementById('search-button');
            searchButton.disabled = true;
            searchButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            // Use Nominatim for geocoding
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    // Reset loading state
                    searchButton.disabled = false;
                    searchButton.innerHTML = '<i class="fas fa-search"></i>';

                    if (data && data.length > 0) {
                        // Use the first result
                        const result = data[0];
                        const latlng = L.latLng(result.lat, result.lon);

                        // Set marker and update fields
                        setEditMarker(latlng);
                        document.getElementById('location_address').value = result.display_name;
                        document.getElementById('location_lat').value = result.lat;
                        document.getElementById('location_lng').value = result.lon;
                    } else {
                        alert('Location not found. Please try a different search term.');
                    }
                })
                .catch(error => {
                    console.error('Error searching for location:', error);
                    searchButton.disabled = false;
                    searchButton.innerHTML = '<i class="fas fa-search"></i>';
                    alert('Error searching for location. Please try again.');
                });
        }

        function getCurrentLocation() {
            // Show loading state
            const button = document.getElementById('current-location-button');
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting location...';

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const latlng = L.latLng(lat, lng);

                    // Set marker and update map
                    setEditMarker(latlng);
                    updateLocationFields(latlng);

                    // Reset button state
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-map-marker-alt"></i> Use My Location';
                }, function(error) {
                    // Reset button state
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-map-marker-alt"></i> Use My Location';

                    // Show appropriate error message
                    let errorMessage = 'Unable to get your location. Please enter it manually.';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage = 'Location access was denied. Please enable location services and try again.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage = 'Location information is unavailable. Please try again later.';
                            break;
                        case error.TIMEOUT:
                            errorMessage = 'Location request timed out. Please try again.';
                            break;
                    }
                    alert(errorMessage);
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            } else {
                alert('Geolocation is not supported by your browser. Please enter your location manually.');
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-map-marker-alt"></i> Use My Location';
            }
        }

        // Password visibility toggle
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#new_password');

        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });

        // Add custom styles for password toggle and profile view
        const style = document.createElement('style');
        style.textContent = `
            .password-input-container {
                position: relative;
            }
            .password-toggle-icon {
                position: absolute;
                right: 15px;
                top: 50%;
                transform: translateY(-50%);
                cursor: pointer;
                color: var(--text-secondary);
                transition: var(--transition-fast);
            }
            .password-toggle-icon:hover {
                color: var(--primary-color);
            }
            .security-tips {
                list-style: none;
                padding-left: 0;
                margin-top: var(--spacing-md);
            }
            .security-tips li {
                margin-bottom: var(--spacing-sm);
                display: flex;
                align-items: center;
            }

            /* Profile View Mode Styles */
            .profile-field {
                margin-bottom: 1.5rem;
            }
            .profile-label {
                font-weight: 600;
                color: var(--text-secondary);
                font-size: 0.9rem;
                margin-bottom: 0.5rem;
                display: block;
            }
            .profile-value {
                font-size: 1.1rem;
                padding: 0.5rem 0;
                border-bottom: 1px solid #eee;
                color: var(--text-primary);
            }
            .btn-primary {
                background-color: #43547B;
                border-color: #43547B;
            }
            .btn-primary:hover {
                background-color: #364268;
                border-color: #364268;
            }
            .btn-secondary {
                background-color: #6c757d;
                border-color: #6c757d;
            }
            .ml-2 {
                margin-left: 0.5rem;
            }

            /* Modal Styles */
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0, 0, 0, 0.5);
            }

            .modal-content {
                background-color: #fff;
                margin: 10% auto;
                width: 50%;
                border-radius: 8px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
                animation: modalFadeIn 0.3s;
            }

            @keyframes modalFadeIn {
                from {opacity: 0; transform: translateY(-50px);}
                to {opacity: 1; transform: translateY(0);}
            }

            .modal-header {
                padding: 15px 20px;
                background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
                color: white;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-top-left-radius: 8px;
                border-top-right-radius: 8px;
            }

            .modal-header h3 {
                margin: 0;
                color: white;
            }

            .close-modal {
                color: white;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }

            .modal-body {
                padding: 20px;
            }

            .modal-footer {
                padding: 15px 20px;
                display: flex;
                justify-content: flex-end;
                border-top: 1px solid #e9ecef;
            }

            .confirmation-details {
                background-color: #f8f9fa;
                border-radius: 5px;
                padding: 15px;
                margin-top: 15px;
            }

            .confirm-row {
                display: flex;
                margin-bottom: 10px;
                padding-bottom: 5px;
                border-bottom: 1px solid #e9ecef;
            }

            .confirm-row:last-child {
                margin-bottom: 0;
                border-bottom: none;
            }

            .confirm-label {
                font-weight: 600;
                width: 150px;
                color: var(--text-secondary);
            }

            .confirm-value {
                flex: 1;
                color: var(--text-primary);
            }
        `;
        document.head.appendChild(style);

        // Show success message as toast if present and handle edit mode toggle
        document.addEventListener('DOMContentLoaded', () => {
            <?php if (strpos($message ?? '', 'success') !== false): ?>
            if (typeof showToast === 'function') {
                showToast('<?= $message ?>', 'success', 5000);
            }
            <?php endif; ?>

            // Handle edit mode toggle
            const editProfileBtn = document.getElementById('editProfileBtn');
            const cancelEditBtn = document.getElementById('cancelEditBtn');
            const profileViewMode = document.getElementById('profileViewMode');
            const profileEditMode = document.getElementById('profileEditMode');
            const showConfirmBtn = document.getElementById('showConfirmBtn');
            const confirmationModal = document.getElementById('confirmationModal');
            const closeModal = document.querySelector('.close-modal');
            const confirmUpdateBtn = document.getElementById('confirmUpdateBtn');
            const cancelUpdateBtn = document.getElementById('cancelUpdateBtn');
            const confirmUpdate = document.getElementById('confirm_update');
            const profileForm = document.querySelector('.profile-form');

            if (editProfileBtn && cancelEditBtn && profileViewMode && profileEditMode) {
                // Switch to edit mode
                editProfileBtn.addEventListener('click', () => {
                    profileViewMode.style.display = 'none';
                    profileEditMode.style.display = 'block';
                });

                // Switch back to view mode
                cancelEditBtn.addEventListener('click', () => {
                    profileEditMode.style.display = 'none';
                    profileViewMode.style.display = 'block';
                });

                // If there was an error during form submission, show edit mode
                <?php if (strpos($message ?? '', 'Error') !== false): ?>
                profileViewMode.style.display = 'none';
                profileEditMode.style.display = 'block';
                <?php endif; ?>

                // Show confirmation modal
                if (showConfirmBtn) {
                    showConfirmBtn.addEventListener('click', () => {
                        // Validate form first
                        const inputs = profileForm.querySelectorAll('input[required]');
                        let isValid = true;

                        inputs.forEach(input => {
                            if (input.value.trim() === '') {
                                input.classList.add('is-invalid');
                                isValid = false;
                            } else {
                                input.classList.remove('is-invalid');
                            }
                        });

                        if (!isValid) return;

                        // Populate confirmation modal with form values
                        document.getElementById('confirm-first-name').textContent = document.getElementById('first_name').value;
                        document.getElementById('confirm-last-name').textContent = document.getElementById('last_name').value;
                        document.getElementById('confirm-email').textContent = document.getElementById('email').value;
                        document.getElementById('confirm-contact').textContent = document.getElementById('contact_number').value;
                        document.getElementById('confirm-location').textContent = document.getElementById('location_address').value || 'Not set';

                        // Get the selected place type text
                        const placeTypeSelect = document.getElementById('type_of_place');
                        const selectedOption = placeTypeSelect.options[placeTypeSelect.selectedIndex];
                        document.getElementById('confirm-place-type').textContent = selectedOption.text !== 'Select Place Type' ? selectedOption.text : 'Not set';

                        // Check if password is being changed
                        const passwordField = document.getElementById('new_password');
                        if (passwordField.value.trim() !== '') {
                            document.getElementById('password-changed-row').style.display = 'flex';
                        } else {
                            document.getElementById('password-changed-row').style.display = 'none';
                        }

                        // Show modal
                        confirmationModal.style.display = 'block';
                    });
                }

                // Close modal when clicking X
                if (closeModal) {
                    closeModal.addEventListener('click', () => {
                        confirmationModal.style.display = 'none';
                    });
                }

                // Close modal when clicking outside
                window.addEventListener('click', (event) => {
                    if (event.target === confirmationModal) {
                        confirmationModal.style.display = 'none';
                    }
                });

                // Cancel update button
                if (cancelUpdateBtn) {
                    cancelUpdateBtn.addEventListener('click', () => {
                        confirmationModal.style.display = 'none';
                    });
                }

                // Confirm update button
                if (confirmUpdateBtn) {
                    confirmUpdateBtn.addEventListener('click', () => {
                        // Set the confirmation flag
                        confirmUpdate.value = 'true';

                        // Get form data
                        const formData = new FormData(profileForm);

                        // Create AJAX request
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', 'profile.php', true);
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                        xhr.onload = function() {
                            if (this.status === 200) {
                                try {
                                    const response = JSON.parse(this.responseText);

                                    if (response.success) {
                                        // Update view mode with new values
                                        const profileValues = document.querySelectorAll('#profileViewMode .profile-value');
                                        if (profileValues.length >= 6) {
                                            profileValues[0].textContent = document.getElementById('first_name').value;
                                            profileValues[1].textContent = document.getElementById('last_name').value;
                                            profileValues[2].textContent = document.getElementById('email').value;
                                            profileValues[3].textContent = document.getElementById('contact_number').value;
                                            profileValues[4].textContent = document.getElementById('location_address').value || 'Not set';

                                            // Get the selected place type text
                                            const placeTypeSelect = document.getElementById('type_of_place');
                                            const selectedOption = placeTypeSelect.options[placeTypeSelect.selectedIndex];
                                            profileValues[5].textContent = selectedOption.text !== 'Select Place Type' ? selectedOption.text : 'Not set';
                                        }

                                        // Update header name if available
                                        const headerName = document.querySelector('.sidebar-header h3');
                                        if (headerName) {
                                            const firstName = document.getElementById('first_name').value;
                                            const lastName = document.getElementById('last_name').value;
                                            headerName.textContent = 'Welcome, ' + firstName + ' ' + lastName;
                                        }

                                        // Show success message
                                        if (typeof showToast === 'function') {
                                            showToast(response.message, 'success', 5000);
                                        }

                                        // Hide modal and return to view mode
                                        confirmationModal.style.display = 'none';
                                        profileEditMode.style.display = 'none';
                                        profileViewMode.style.display = 'block';
                                    } else {
                                        // Show error message
                                        if (typeof showToast === 'function') {
                                            showToast(response.message || 'Error updating profile', 'error', 5000);
                                        }
                                    }
                                } catch (e) {
                                    console.error('Error parsing response:', e);
                                    if (typeof showToast === 'function') {
                                        showToast('An unexpected error occurred', 'error', 5000);
                                    }
                                }
                            }
                        };

                        xhr.onerror = function() {
                            if (typeof showToast === 'function') {
                                showToast('Network error occurred', 'error', 5000);
                            }
                        };

                        // Send the form data
                        xhr.send(formData);
                    });
                }
            }
        });
    </script>
    <script src="js/main.js"></script>
    <script src="js/sidebar.js"></script>
    <script src="js/form-validation-fix.js"></script>
    <!-- Fixed sidebar script -->
    <script src="js/sidebar-fix.js"></script>
    <script>
        // Add sidebar-active class to body when sidebar is active
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');

            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    document.body.classList.toggle('sidebar-active');
                });
            }
        });
    </script>
    <script>
        // Additional script to ensure profile validation messages are italicized
        document.addEventListener('DOMContentLoaded', function() {
            // Apply italic style to all validation messages in profile form
            const profileForm = document.getElementById('profileEditMode');
            if (profileForm) {
                const inputs = profileForm.querySelectorAll('input[required]');

                inputs.forEach(input => {
                    // Create custom validation message
                    const parent = input.parentNode;
                    let feedbackDiv = parent.querySelector('.invalid-feedback');

                    if (!feedbackDiv) {
                        feedbackDiv = document.createElement('div');
                        feedbackDiv.className = 'invalid-feedback';
                        feedbackDiv.style.fontStyle = 'italic';
                        feedbackDiv.textContent = 'This field is required';
                        parent.appendChild(feedbackDiv);
                    }

                    // Show validation message on blur if field is empty
                    input.addEventListener('blur', function() {
                        if (this.value.trim() === '') {
                            this.classList.add('is-invalid');
                            feedbackDiv.style.display = 'block';
                        } else {
                            this.classList.remove('is-invalid');
                            feedbackDiv.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>
    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <!-- Floating Action Button Script -->
    <script src="js/floating-action-button.js"></script>
    <script>
        // Ensure notification dropdown works and initialize notifications
        $(document).ready(function() {
            // Initialize notifications
            if (typeof initNotifications === 'function') {
                initNotifications();
            } else {
                console.error("initNotifications function not found");

                // Fallback notification handling if initNotifications is not available
                $('.notification-container').on('click', function(e) {
                    e.stopPropagation();
                    $('.notification-dropdown').toggleClass('show');
                    console.log('Notification icon clicked');
                });

                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.notification-container').length) {
                        $('.notification-dropdown').removeClass('show');
                    }
                });

                // Fetch notifications immediately
                if (typeof fetchNotifications === 'function') {
                    fetchNotifications();

                    // Set up periodic notification checks
                    setInterval(fetchNotifications, 60000); // Check every minute
                }
            }
        });
    </script>
</body>
</html>