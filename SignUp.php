<?php
session_start();
$error = '';
$conn = new mysqli("localhost", "root", "", "macj_pest_control");
require_once("FUNCTIONS/MAIL/SEND_EMAIL.php");

// reCAPTCHA keys - Replace these with your actual keys from Google reCAPTCHA
$recaptcha_site_key = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI'; // This is a test key
$recaptcha_secret_key = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe'; // This is a test key

// Function to generate OTP
function generateOTP() {
    return rand(100000, 999999);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $firstName = $conn->real_escape_string($_POST['firstName']);
    $lastName = $conn->real_escape_string($_POST['lastName']);
    $email = $conn->real_escape_string($_POST['email']);
    $contact = $conn->real_escape_string($_POST['contact']);
    $location = $conn->real_escape_string($_POST['location']);
    $latitude = isset($_POST['latitude']) ? $conn->real_escape_string($_POST['latitude']) : '';
    $longitude = isset($_POST['longitude']) ? $conn->real_escape_string($_POST['longitude']) : '';

    // Handle place type (dropdown or custom input)
    $placeType = $conn->real_escape_string($_POST['place_type']);
    if ($placeType === 'other' && isset($_POST['custom_place_type']) && !empty($_POST['custom_place_type'])) {
        $placeType = $conn->real_escape_string($_POST['custom_place_type']);
    }

    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];

    // Verify reCAPTCHA
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    if (empty($recaptcha_response)) {
        $error = "Please complete the CAPTCHA verification.";
    } else {
        // Verify with Google reCAPTCHA API
        $verify_response = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$recaptcha_secret_key.'&response='.$recaptcha_response);
        $response_data = json_decode($verify_response);

        if (!$response_data->success) {
            $error = "CAPTCHA verification failed. Please try again.";
        } else {
            // CAPTCHA verified successfully, proceed with registration

            // Validate passwords
            if ($password !== $confirmPassword) {
                $error = "Passwords do not match!";
            } else {
                // Validate password strength
                if (strlen($password) < 10 ||
                    !preg_match('/[A-Z]/', $password) ||
                    !preg_match('/[a-z]/', $password) ||
                    !preg_match('/[0-9]/', $password)) {
                    $error = "Password must be at least 10 characters long and include uppercase, lowercase letters, and a number.";
                } else {
                    // Validate contact number format (Philippine mobile number: 11 digits starting with 09)
                    if (!preg_match('/^09\d{9}$/', $contact)) {
                        $error = "Please enter a valid Philippine mobile number (11 digits starting with 09).";
                    } else {
                        // Check if email exists
                        $check = $conn->prepare("SELECT email FROM clients WHERE email = ?");
                        $check->bind_param("s", $email);
                        $check->execute();
                        $check->store_result();

                        if ($check->num_rows > 0) {
                            $error = "Email already registered!";
                        } else {
                        // Use location as is without coordinates

                        // Generate OTP
                        $OTP = generateOTP();

                        // Store user data in session for OTP verification
                        $_SESSION["firstName"] = $firstName;
                        $_SESSION["lastName"] = $lastName;
                        $_SESSION["email"] = $email;
                        $_SESSION["contact"] = $contact;
                        $_SESSION["location"] = $location;
                        $_SESSION["latitude"] = $latitude;
                        $_SESSION["longitude"] = $longitude;
                        $_SESSION["placeType"] = $placeType;
                        $_SESSION["password"] = $password; // Store original password temporarily
                        $_SESSION["OTP"] = $OTP;

                        // Send OTP via email
                        SendMail($firstName, $email, $OTP);

                        // Redirect to OTP verification page
                        header("Location: verification_otp.php");
                        exit();
                    }
                }
            }
        }
    }
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - MacJ Pest Control</title>
    <link href="https://unpkg.com/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <!-- Google reCAPTCHA v2 -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <!-- jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <!-- Leaflet.js for OpenStreetMap -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
</head>
<body>
<div class="bg-light py-3 py-md-5">
  <div class="container">
    <div class="row justify-content-md-center">
      <div class="col-12 col-md-11 col-lg-8 col-xl-7 col-xxl-6">
        <div class="bg-white p-4 p-md-5 rounded shadow-sm">
          <div class="row">
            <div class="col-12">
              <div class="text-center mb-5">
                <a>
                  <img src="Landingpage/assets/img/MACJLOGO.png" alt="MacJ Pest Control" width="180" height="57">
                </a>
              </div>
            </div>
          </div>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
          <?php endif; ?>

          <form action="SignUp.php" method="POST">
            <div class="row gy-3 gy-md-4 overflow-hidden">
              <!-- First Name -->
              <div class="col-12">
                <label for="firstName" class="form-label">First Name <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard" viewBox="0 0 16 16">
                      <path d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5"/>
                      <path d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96c.026-.163.04-.33.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1.006 1.006 0 0 1 1 12z"/>
                    </svg>
                  </span>
                  <input type="text" class="form-control" name="firstName" id="firstName" required>
                </div>
              </div>

              <!-- Last Name -->
              <div class="col-12">
                <label for="lastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-card-checklist" viewBox="0 0 16 16">
                      <path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2z"/>
                      <path d="M7 5.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 1 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0M7 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 0 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0"/>
                    </svg>
                  </span>
                  <input type="text" class="form-control" name="lastName" id="lastName" required>
                </div>
              </div>

              <!-- Email -->
              <div class="col-12">
                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-envelope" viewBox="0 0 16 16">
                      <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4Zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2Zm13 2.383-4.708 2.825L15 11.105V5.383Zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741ZM1 11.105l4.708-2.897L1 5.383v5.722Z"/>
                    </svg>
                  </span>
                  <input type="email" class="form-control" name="email" id="email" required>
                </div>
              </div>

              <!-- Contact Number -->
              <div class="col-12">
                <label for="contact" class="form-label">Contact Number <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telephone" viewBox="0 0 16 16">
                      <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.6 17.6 0 0 0 4.168 6.608 17.6 17.6 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.678.678 0 0 0-.58-.122l-2.19.547a1.75 1.75 0 0 1-1.657-.459L5.482 8.062a1.75 1.75 0 0 1-.46-1.657l.548-2.19a.678.678 0 0 0-.122-.58zM1.884.511a1.75 1.75 0 0 1 2.612.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.75 1.75 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877z"/>
                    </svg>
                  </span>
                  <input type="tel" class="form-control" name="contact" id="contact"
                         pattern="^09\d{9}$"
                         title="Please enter a valid Philippine mobile number (11 digits starting with 09)"
                         placeholder="09XXXXXXXXX"
                         maxlength="11"
                         required>
                  <div class="invalid-feedback" id="contactFeedback">
                    Please enter a valid Philippine mobile number (11 digits starting with 09).
                  </div>
                </div>
              </div>

              <!-- Location Address -->
              <div class="col-12">
                <label for="location" class="form-label">Location Address <span class="text-danger">*</span></label>
                <div class="map-controls">
                  <div class="input-group mb-3">
                    <span class="input-group-text">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt" viewBox="0 0 16 16">
                        <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A31.493 31.493 0 0 1 8 14.58a31.481 31.481 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94zM8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10z"/>
                        <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                      </svg>
                    </span>
                    <input type="text" class="form-control" id="location-search" placeholder="Search for an address">
                    <button type="button" class="btn btn-outline-primary" id="useMyLocation">
                      <i class="bi bi-geo"></i> Use My Location
                    </button>
                  </div>
                  <div class="btn-group mb-3">
                    <button type="button" id="search-button" class="btn btn-primary btn-sm">Search</button>
                    <button type="button" id="clear-map-button" class="btn btn-outline-secondary btn-sm">Clear</button>
                  </div>
                </div>

                <!-- Map Container -->
                <div id="map-container" class="mb-3">
                  <div id="map" style="height: 300px; width: 100%; border-radius: 8px; border: 1px solid #ddd;"></div>
                </div>

                <div class="input-group mb-3">
                  <span class="input-group-text">
                    <i class="bi bi-pin-map"></i>
                  </span>
                  <input type="text" class="form-control" name="location" id="location" placeholder="Your address will appear here after selecting on the map" required>
                  <input type="hidden" name="latitude" id="latitude">
                  <input type="hidden" name="longitude" id="longitude">
                </div>

                <div class="mt-2 text-center mb-3">
                  <small class="text-muted">
                    <span id="selected-location-text">Selected Location: Drag to adjust</span>
                  </small>
                </div>
              </div>

              <!-- Type of Place -->
              <div class="col-12">
                <label for="place_type" class="form-label">Type of Place <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-building" viewBox="0 0 16 16">
                      <path d="M4 2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v10a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-10zm.5.5h7v10h-7V3z"/>
                      <path d="M2 2a2 2 0 0 0-2 2v1h16V4a2 2 0 0 0-2-2H2zm14 3H0v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V5z"/>
                    </svg>
                  </span>
                  <select class="form-control" name="place_type" id="place_type" required>
                    <option value="">Select Place Type</option>
                    <option value="Restaurant">Restaurant</option>
                    <option value="House">House</option>
                    <option value="Condominium">Condominium</option>
                    <option value="Office">Office</option>
                    <option value="Construction Site">Construction Site</option>
                    <option value="other">Other (specify)</option>
                  </select>
                </div>
                <!-- Custom place type input (hidden by default) -->
                <div id="custom_place_type_container" class="mt-2" style="display: none;">
                  <div class="input-group">
                    <span class="input-group-text">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
                        <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                        <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/>
                      </svg>
                    </span>
                    <input type="text" class="form-control" name="custom_place_type" id="custom_place_type" placeholder="Please specify the type of place">
                  </div>
                </div>
              </div>

              <!-- Password -->
              <div class="col-12">
                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-key" viewBox="0 0 16 16">
                      <path d="M0 8a4 4 0 0 1 7.465-2H14a.5.5 0 0 1 .354.146l1.5 1.5a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0L13 9.207l-.646.647a.5.5 0 0 1-.708 0L11 9.207l-.646.647a.5.5 0 0 1-.708 0L9 9.207l-.646.647A.5.5 0 0 1 8 10h-.535A4 4 0 0 1 0 8zm4-3a3 3 0 1 0 2.712 4.285A.5.5 0 0 1 7.163 9h.63l.853-.854a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .708 0l.646.647.793-.793-1-1h-6.63a.5.5 0 0 1-.451-.285A3 3 0 0 0 4 5z"/>
                      <path d="M4 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                    </svg>
                  </span>
                  <input type="password" class="form-control" name="password" id="password"
                         pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{10,}"
                         title="Must contain at least 10 characters, including uppercase, lowercase letters, and a number"
                         required>
                  <span class="input-group-text" onclick="togglePassword('password', this)">
                    <i class="bi bi-eye"></i>
                  </span>
                </div>
              </div>

              <!-- Confirm Password -->
              <div class="col-12">
                <label for="confirmPassword" class="form-label">Re-Type Password <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-key" viewBox="0 0 16 16">
                      <path d="M0 8a4 4 0 0 1 7.465-2H14a.5.5 0 0 1 .354.146l1.5 1.5a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0L13 9.207l-.646.647a.5.5 0 0 1-.708 0L11 9.207l-.646.647a.5.5 0 0 1-.708 0L9 9.207l-.646.647A.5.5 0 0 1 8 10h-.535A4 4 0 0 1 0 8zm4-3a3 3 0 1 0 2.712 4.285A.5.5 0 0 1 7.163 9h.63l.853-.854a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .708 0l.646.647.793-.793-1-1h-6.63a.5.5 0 0 1-.451-.285A3 3 0 0 0 4 5z"/>
                      <path d="M4 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                    </svg>
                  </span>
                  <input type="password" class="form-control" name="confirmPassword" id="confirmPassword"
                         pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{10,}"
                         required>
                  <span class="input-group-text" onclick="togglePassword('confirmPassword', this)">
                    <i class="bi bi-eye"></i>
                  </span>
                </div>
              </div>

              <!-- Terms Checkbox -->
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="iAgree" id="iAgree" required>
                  <label class="form-check-label text-secondary" for="iAgree">
                    I agree to the <a href="#" id="termsLink" class="link-primary text-decoration-none">terms and conditions</a>
                  </label>
                </div>
              </div>

              <!-- reCAPTCHA -->
              <div class="col-12 mt-3 mb-3">
                <label class="form-label">Verify you're human <span class="text-danger">*</span></label>
                <div class="g-recaptcha" data-sitekey="<?php echo $recaptcha_site_key; ?>"></div>
                <small class="text-muted fst-italic">Please complete the CAPTCHA verification before submitting.</small>
              </div>

              <!-- Submit Button -->
              <div class="col-12">
                <div class="d-grid">
                  <button class="btn btn-primary btn-lg" type="submit" href="landing.php">Sign Up</button>
                </div>
              </div>
            </div>
          </form>

          <!-- Sign In Link -->
          <div class="row">
            <div class="col-12">
              <hr class="mt-5 mb-4 border-secondary-subtle">
              <p class="m-0 text-secondary text-center">Already have an account? <a href="SignIn.php" class="link-primary text-decoration-none">Sign in</a></p>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>


<!-- Terms and Conditions Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="termsModalLabel"><i class="bi bi-file-text me-2"></i>Terms and Conditions</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="section">
          <h5>1. Acceptance of Terms</h5>
          <p>By accessing or using the MacJ Pest Control Services web platform ("Service"), you agree to be bound by these Terms and Conditions. If you disagree with any part, you may not access the Service.</p>
        </div>

        <div class="section">
          <h5>2. User Responsibilities</h5>
          <p>You agree to:
            <ul>
              <li>Provide accurate and complete information during registration</li>
              <li>Maintain the confidentiality of your account credentials</li>
              <li>Immediately notify us of unauthorized account use</li>
              <li>Grant safe access to your property for scheduled services</li>
            </ul>
          </p>
        </div>

        <div class="section">
          <h5>3. Service Management</h5>
          <p>
            <span class="highlight">Appointments:</span> All service requests are subject to availability and final approval by MacJ administrators. We reserve the right to reschedule due to unforeseen circumstances.<br><br>
            <span class="highlight">Cancellations:</span> Must be made at least 24 hours before scheduled service. Repeated cancellations may result in account suspension.
          </p>
        </div>

        <div class="section">
          <h5>4. Data Privacy</h5>
          <p>We collect personal information including:
            <ul>
              <li>Contact details</li>
              <li>Property information</li>
              <li>Service history</li>
            </ul>
            This data is used solely for service delivery and will not be shared with third parties without consent, except as required by law.
          </p>
        </div>

        <div class="section">
          <h5>5. Liability</h5>
          <p>MacJ Pest Control:
            <ul>
              <li>Uses FDA-approved chemicals following safety protocols</li>
              <li>Is not liable for pre-existing structural damages</li>
              <li>Requires client disclosure of pet/child safety concerns</li>
            </ul>
          </p>
        </div>

        <div class="section">
          <h5>6. Intellectual Property</h5>
          <p>All system content, including logos and treatment plans, are property of MacJ Pest Control Services. Unauthorized use is prohibited.</p>
        </div>

        <div class="section">
          <h5>7. Governing Law</h5>
          <p>These Terms shall be governed by the laws of the Republic of the Philippines. Any disputes shall be resolved in Quezon City courts.</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="agreeBtn">I Agree</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword(fieldId, element) {
    const passwordField = document.getElementById(fieldId);
    const eyeIcon = element.querySelector('i');

    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        eyeIcon.classList.remove('bi-eye');
        eyeIcon.classList.add('bi-eye-slash');
    } else {
        passwordField.type = 'password';
        eyeIcon.classList.remove('bi-eye-slash');
        eyeIcon.classList.add('bi-eye');
    }
}

// Terms and Conditions Modal
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the modal
    const termsModal = new bootstrap.Modal(document.getElementById('termsModal'));

    // Contact number validation
    const contactInput = document.getElementById('contact');
    const contactFeedback = document.getElementById('contactFeedback');

    contactInput.addEventListener('input', function(e) {
        const value = e.target.value;

        // Remove any non-numeric characters
        const numericValue = value.replace(/\D/g, '');

        // Ensure it starts with '09' and has max 11 digits
        if (numericValue.length > 0) {
            // If first digit is not 0, prepend 0
            if (numericValue.charAt(0) !== '0') {
                e.target.value = '0' + numericValue.substring(0, 10);
            }
            // If first digit is 0 but second is not 9, make it 09
            else if (numericValue.length > 1 && numericValue.charAt(1) !== '9') {
                e.target.value = '09' + numericValue.substring(2, 11);
            }
            // Otherwise just ensure max length
            else {
                e.target.value = numericValue.substring(0, 11);
            }
        }

        // Validate the pattern
        const isValid = /^09\d{9}$/.test(e.target.value);

        if (isValid) {
            contactInput.classList.remove('is-invalid');
            contactInput.classList.add('is-valid');
        } else {
            if (e.target.value.length > 0) {
                contactInput.classList.add('is-invalid');
                contactInput.classList.remove('is-valid');

                // Show specific error message based on the issue
                if (e.target.value.length < 11) {
                    contactFeedback.textContent = 'Phone number must be 11 digits (currently ' + e.target.value.length + ' digits).';
                } else if (!e.target.value.startsWith('09')) {
                    contactFeedback.textContent = 'Phone number must start with 09.';
                } else {
                    contactFeedback.textContent = 'Please enter a valid Philippine mobile number (11 digits starting with 09).';
                }
            } else {
                contactInput.classList.remove('is-invalid');
                contactInput.classList.remove('is-valid');
            }
        }
    });

    // Show modal when terms link is clicked
    document.getElementById('termsLink').addEventListener('click', function(e) {
        e.preventDefault();
        termsModal.show();
    });

    // Handle the agree button click
    document.getElementById('agreeBtn').addEventListener('click', function() {
        document.getElementById('iAgree').checked = true;
    });

    // Handle privacy button click
    if (document.getElementById('privacyBtn')) {
        document.getElementById('privacyBtn').addEventListener('click', function() {
            alert("Full privacy policy available at [Office Location] or by request through our contact form.");
        });
    }

    // Handle custom place type input
    const placeTypeSelect = document.getElementById('place_type');
    const customPlaceTypeContainer = document.getElementById('custom_place_type_container');
    const customPlaceTypeInput = document.getElementById('custom_place_type');

    placeTypeSelect.addEventListener('change', function() {
        if (this.value === 'other') {
            customPlaceTypeContainer.style.display = 'block';
            customPlaceTypeInput.setAttribute('required', 'required');
        } else {
            customPlaceTypeContainer.style.display = 'none';
            customPlaceTypeInput.removeAttribute('required');
            customPlaceTypeInput.value = '';
        }
    });

    // Leaflet Map initialization
    let map;
    let marker;
    const locationInput = document.getElementById('location');
    const locationSearchInput = document.getElementById('location-search');
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    const selectedLocationText = document.getElementById('selected-location-text');
    const useMyLocationBtn = document.getElementById('useMyLocation');
    const searchButton = document.getElementById('search-button');
    const clearMapButton = document.getElementById('clear-map-button');

    // Initialize the map with default location (Manila, Philippines)
    function initMap() {
        try {
            // Get the map element
            const mapElement = document.getElementById('map');
            if (!mapElement) {
                return;
            }

            // Make sure the map container is visible
            mapElement.style.display = 'block';
            document.getElementById('map-container').style.display = 'block';

            // Remove any existing map instance
            if (map) {
                map.remove();
            }

            // Force the map container to be visible before initialization
            $('#map').css({
                'height': '300px',
                'width': '100%',
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1'
            });

            $('#map-container').css({
                'height': '300px',
                'width': '100%',
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1'
            });

            // Default location (Manila, Philippines)
            const defaultLocation = [14.5995, 120.9842];

            // Create map centered on default location
            map = L.map('map', {
                center: defaultLocation,
                zoom: 13,
                zoomControl: true,
                scrollWheelZoom: true,
                preferCanvas: true, // Use canvas for better performance
                fadeAnimation: false, // Disable animations for better performance
                markerZoomAnimation: false // Disable marker animations
            });

            // Use OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19,
                crossOrigin: true
            }).addTo(map);

            // Add click event to map
            map.on('click', function(e) {
                setMarker(e.latlng);
                updateLocationFields(e.latlng);
            });

            // Force a resize after initialization
            map.invalidateSize({ animate: false, pan: false });

            // Add a marker in the center as a fallback
            const centerMarker = L.marker(defaultLocation, {
                title: 'Map Center'
            }).addTo(map);
            centerMarker.bindPopup('Click anywhere on the map to select your location.').openPopup();

            // Multiple resize attempts with increasing delays
            setTimeout(function() {
                map.invalidateSize({ animate: false, pan: false });
            }, 500);

            setTimeout(function() {
                map.invalidateSize({ animate: false, pan: false });
            }, 1000);

        } catch (error) {
            console.error("Error initializing map:", error);
            // Try a simpler initialization as fallback
            try {
                // Clear the map container first
                $('#map').html('');

                // Simple initialization
                map = L.map('map').setView([14.5995, 120.9842], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                // Force resize
                setTimeout(function() {
                    map.invalidateSize();
                }, 1000);
            } catch (fallbackError) {
                console.error("Fallback map initialization failed:", fallbackError);
                // Show error message in map container
                $('#map').html('<div style="padding: 20px; text-align: center;"><strong>Error loading map.</strong><br>Please refresh the page and try again.</div>');
            }
        }
    }

    // Set marker on map
    function setMarker(latlng) {
        // Remove existing marker if any
        if (marker) {
            map.removeLayer(marker);
        }

        // Create custom icon for the marker
        const customIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        // Create new marker with custom icon
        marker = L.marker(latlng, {
            icon: customIcon,
            draggable: true, // Allow marker to be dragged
            title: 'Drag to adjust location'
        }).addTo(map);

        // Add popup to the marker
        marker.bindPopup('Selected Location<br>Drag to adjust').openPopup();

        // Handle marker drag end event
        marker.on('dragend', function(event) {
            const newPosition = marker.getLatLng();
            updateLocationFields(newPosition);
        });

        // Center map on marker with animation
        map.setView(latlng, 15, { // Zoom level 15 for better detail
            animate: true,
            duration: 1
        });

        // Remove any existing circles
        map.eachLayer(function(layer) {
            if (layer instanceof L.Circle) {
                map.removeLayer(layer);
            }
        });

        // Add a circle to highlight the area
        L.circle(latlng, {
            color: '#FF4136',
            weight: 2,
            fillColor: '#FF4136',
            fillOpacity: 0.15,
            radius: 200 // 200 meters radius for better visibility
        }).addTo(map);
    }

    // Update location fields in the form
    function updateLocationFields(latlng) {
        // Format coordinates with 6 decimal places
        const lat = latlng.lat.toFixed(6);
        const lng = latlng.lng.toFixed(6);

        // Update the hidden fields with coordinates
        latitudeInput.value = lat;
        longitudeInput.value = lng;

        // Set a temporary value while we fetch the address
        locationInput.value = `Fetching address for: ${lat}, ${lng}...`;

        // Use Nominatim for reverse geocoding
        $.ajax({
            url: `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data && data.display_name) {
                    // Update the location field with the found address
                    locationInput.value = data.display_name;
                    selectedLocationText.textContent = 'Selected Location: ' + data.display_name;
                } else {
                    // Fallback to coordinates if no address found
                    locationInput.value = `Location at: ${lat}, ${lng}`;
                    selectedLocationText.textContent = 'Selected Location: No address found';
                }
            },
            error: function(xhr, status, error) {
                // Fallback to coordinates if geocoding fails
                locationInput.value = `Location at: ${lat}, ${lng}`;
                selectedLocationText.textContent = 'Selected Location: Geocoding failed';
            }
        });
    }

    // Search for a location
    function searchLocation(query) {
        // Show loading indicator
        locationSearchInput.classList.add('loading');
        searchButton.disabled = true;
        searchButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Searching...';

        // Use Nominatim for geocoding (OpenStreetMap's geocoding service)
        $.ajax({
            url: `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5`,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                // Reset loading state
                locationSearchInput.classList.remove('loading');
                searchButton.disabled = false;
                searchButton.innerHTML = 'Search';

                if (data && data.length > 0) {
                    // If multiple results, show them in a dropdown
                    if (data.length > 1) {
                        // Create a dropdown for search results
                        let resultsHtml = '<div class="search-results"><ul>';
                        data.forEach(function(result, index) {
                            resultsHtml += `<li data-lat="${result.lat}" data-lon="${result.lon}">${result.display_name}</li>`;
                        });
                        resultsHtml += '</ul></div>';

                        // Remove any existing results dropdown
                        $('.search-results').remove();

                        // Add the new dropdown after the search box
                        $('.map-controls').append(resultsHtml);

                        // Add click handler for results
                        $('.search-results li').on('click', function() {
                            const lat = $(this).data('lat');
                            const lon = $(this).data('lon');
                            const latlng = L.latLng(lat, lon);

                            // Set marker and update fields
                            setMarker(latlng);
                            updateLocationFields(latlng);

                            // Remove the results dropdown
                            $('.search-results').remove();
                        });
                    } else {
                        // If only one result, use it directly
                        const result = data[0];
                        const latlng = L.latLng(result.lat, result.lon);

                        // Set marker and update fields
                        setMarker(latlng);

                        // Update location field with the found address
                        locationInput.value = result.display_name;
                        selectedLocationText.textContent = 'Selected Location: ' + result.display_name;
                        latitudeInput.value = result.lat;
                        longitudeInput.value = result.lon;
                    }
                } else {
                    alert('Location not found. Please try a different search term.');
                }
            },
            error: function(xhr, status, error) {
                // Reset loading state
                locationSearchInput.classList.remove('loading');
                searchButton.disabled = false;
                searchButton.innerHTML = 'Search';

                alert('Error searching for location. Please try again.');
            }
        });
    }

    // Use my location button
    useMyLocationBtn.addEventListener('click', function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const latlng = L.latLng(position.coords.latitude, position.coords.longitude);

                    // Set marker and update fields
                    setMarker(latlng);
                    updateLocationFields(latlng);
                },
                function(error) {
                    alert('Error: The Geolocation service failed. ' + error.message);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 5000,
                    maximumAge: 0
                }
            );
        } else {
            alert('Error: Your browser doesn\'t support geolocation.');
        }
    });

    // Handle search button click
    searchButton.addEventListener('click', function() {
        const searchQuery = locationSearchInput.value.trim();
        if (searchQuery) {
            searchLocation(searchQuery);
        }
    });

    // Handle search box enter key press
    locationSearchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const searchQuery = locationSearchInput.value.trim();
            if (searchQuery) {
                searchLocation(searchQuery);
            }
        }
    });

    // Handle clear button click
    clearMapButton.addEventListener('click', function() {
        // Clear the search box
        locationSearchInput.value = '';

        // Clear any markers and overlays
        if (marker) {
            map.removeLayer(marker);
            marker = null;
        }

        // Remove all other layers (circles, etc.)
        map.eachLayer(function(layer) {
            if (layer instanceof L.Circle || layer instanceof L.Marker) {
                map.removeLayer(layer);
            }
        });

        // Reset the map view
        map.setView([14.5995, 120.9842], 13);

        // Clear the location fields
        locationInput.value = '';
        latitudeInput.value = '';
        longitudeInput.value = '';
        selectedLocationText.textContent = 'Selected Location: Drag to adjust';

        // Remove any search results
        $('.search-results').remove();

        // Add a marker in the center as a fallback
        const centerMarker = L.marker([14.5995, 120.9842], {
            title: 'Map Center'
        }).addTo(map);
        centerMarker.bindPopup('Click anywhere on the map to select your location.').openPopup();
    });

    // Initialize map when document is ready
    $(document).ready(function() {
        // Initialize map with multiple attempts
        setTimeout(function() {
            initMap();
        }, 500);

        // Try again after a longer delay in case the first attempt fails
        setTimeout(function() {
            if (!map || !map._loaded) {
                initMap();
            }
        }, 1500);
    });
});
</script>

<style>
/* Modal Styles */
.modal-content {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border: none;
}

.modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid rgba(0,0,0,0.1);
}

/* Terms Content Styles */
.section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 3px solid #3B82F6;
}

.section h5 {
    color: #2c5f2d;
    margin-top: 0;
    margin-bottom: 10px;
    font-weight: 600;
}

.highlight {
    color: #dc3545;
    font-weight: bold;
}

/* Make sure the modal is scrollable on smaller screens */
@media (max-width: 768px) {
    .modal-dialog {
        margin: 10px;
        max-width: calc(100% - 20px);
    }
}

/* Map Styles */
#map-container {
    height: 300px;
    width: 100%;
    margin-bottom: 1rem;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    position: relative;
    border: 1px solid #ddd;
    display: block !important; /* Force display */
}

#map {
    height: 100% !important;
    width: 100% !important;
    position: absolute;
    top: 0;
    left: 0;
    z-index: 1;
    background-color: #f8f9fa;
    display: block !important; /* Force display */
    transition: all 0.3s ease;
}

#map:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

/* Fix for Leaflet controls */
.leaflet-control-container .leaflet-top,
.leaflet-control-container .leaflet-bottom {
    z-index: 10;
}

/* Fix for Leaflet attribution */
.leaflet-control-attribution {
    z-index: 10;
    background-color: rgba(255, 255, 255, 0.8) !important;
}

/* Fix for map tiles */
.leaflet-tile-container img {
    width: 256px !important;
    height: 256px !important;
}

#useMyLocation {
    transition: all 0.2s ease;
}

#useMyLocation:hover {
    background-color: #f8f9fa;
}

#selected-location-text {
    font-style: italic;
    color: #6c757d;
}

#location-search {
    width: 100%;
    padding: 0.375rem 0.75rem;
    border-radius: 0.25rem;
}

.map-controls {
    margin-bottom: 1rem;
    position: relative;
}

/* Search results dropdown */
.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background-color: white;
    border: 1px solid #ddd;
    border-radius: 0.25rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
}

.search-results ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.search-results li {
    padding: 10px 15px;
    border-bottom: 1px solid #ddd;
    cursor: pointer;
    font-size: 14px;
}

.search-results li:last-child {
    border-bottom: none;
}

.search-results li:hover {
    background-color: #f8f9fa;
}

/* Loading indicator */
.loading {
    background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiIgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiBmaWxsPSIjMzMzIj48cGF0aCBvcGFjaXR5PSIuMjUiIGQ9Ik0xNiAwIEExNiAxNiAwIDAgMCAxNiAzMiBBMTYgMTYgMCAwIDAgMTYgMCBNMTYgNCBBMTIgMTIgMCAwIDEgMTYgMjggQTEyIDEyIDAgMCAxIDE2IDQiLz48cGF0aCBkPSJNMTYgMCBBMTYgMTYgMCAwIDEgMzIgMTYgTDI4IDE2IEExMiAxMiAwIDAgMCAxNiA0eiI+PGFuaW1hdGVUcmFuc2Zvcm0gYXR0cmlidXRlTmFtZT0idHJhbnNmb3JtIiB0eXBlPSJyb3RhdGUiIGZyb209IjAgMTYgMTYiIHRvPSIzNjAgMTYgMTYiIGR1cj0iMC44cyIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIC8+PC9wYXRoPjwvc3ZnPg==');
    background-position: right 10px center;
    background-repeat: no-repeat;
    background-size: 20px 20px;
}

/* Form validation styles */
.form-control.is-valid {
    border-color: #198754;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.form-control.is-invalid {
    border-color: #dc3545;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.invalid-feedback {
    display: none;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #dc3545;
}

.is-invalid ~ .invalid-feedback {
    display: block;
}
</style>
</body>
</html>