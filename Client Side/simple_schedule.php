<?php
// Simple scheduling page with minimal code
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
    header("Location: SignIn.php");
    exit;
}

// Get client info
$client_id = $_SESSION['client_id'];
$stmt = $conn->prepare("SELECT * FROM clients WHERE client_id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();

// Clean the location address for display (remove coordinates)
$client['display_address'] = preg_replace('/\[[-\d\.]+,[-\d\.]+\]$/', '', $client['location_address']);

// Get date from query parameter or use today
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get booked times for the selected date
$stmt = $conn->prepare("SELECT preferred_time FROM appointments WHERE preferred_date = ?");
$stmt->bind_param("s", $selectedDate);
$stmt->execute();
$result = $stmt->get_result();

$bookedTimes = [];
while ($row = $result->fetch_assoc()) {
    $bookedTimes[] = $row['preferred_time'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Schedule | MacJ Pest Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2, h3 {
            color: #4361ee;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .date-selector {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f0f4ff;
            border-radius: 8px;
        }
        .date-selector label {
            font-weight: bold;
            margin-right: 10px;
        }
        .date-selector input[type="date"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 10px;
        }
        .date-selector button {
            padding: 8px 15px;
            background-color: #4361ee;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .date-selector button:hover {
            background-color: #3a56d4;
        }
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }
        .time-slot {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .time-slot:hover:not(.booked) {
            border-color: #4361ee;
            background-color: rgba(67, 97, 238, 0.1);
        }
        .time-slot.selected {
            background-color: #4361ee;
            color: white;
            border-color: #4361ee;
        }
        .time-slot.booked {
            background-color: rgba(255, 51, 51, 0.1);
            border-color: #ff3333;
            color: #ff3333;
            cursor: not-allowed;
            opacity: 0.7;
        }
        .time-slot.past-time {
            background-color: rgba(150, 150, 150, 0.1);
            border-color: #999;
            color: #999;
            cursor: not-allowed;
            opacity: 0.7;
            text-decoration: line-through;
        }
        .appointment-form {
            margin-top: 30px;
            padding: 20px;
            background-color: #f0f4ff;
            border-radius: 8px;
            display: none;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn-primary {
            padding: 10px 15px;
            background-color: #4361ee;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-primary:hover {
            background-color: #3a56d4;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-info {
            background-color: #e8f4ff;
            border-left: 4px solid #4361ee;
        }
        .nav-links {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        .nav-links a {
            color: #4361ee;
            text-decoration: none;
            margin-right: 15px;
        }
        .nav-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Schedule an Appointment</h1>
            <div>
                <p>Welcome, <?= htmlspecialchars($_SESSION['fullname'] ?? 'Client') ?></p>
            </div>
        </div>

        <div class="alert alert-info">
            <h3 style="margin-top: 0;">Simple Scheduling Page</h3>
            <p>This is a simplified version of the scheduling page to ensure functionality. Follow these steps:</p>
            <ol>
                <li>Select a date using the date picker below</li>
                <li>Click "Show Available Times" to see time slots for that date</li>
                <li>Click on an available time slot (shown in blue when you hover)</li>
                <li>Fill in the appointment details and submit the form</li>
            </ol>
        </div>

        <div class="date-selector">
            <form method="get" action="simple_schedule.php">
                <label for="date">Select Date:</label>
                <input type="date" id="date" name="date" value="<?= $selectedDate ?>" min="<?= date('Y-m-d') ?>">
                <button type="submit">Show Available Times</button>
            </form>
        </div>

        <div class="time-slots-container">
            <h2>Available Time Slots for <?= date('l, F j, Y', strtotime($selectedDate)) ?></h2>
            <div class="time-slots">
                <?php
                // Check if selected date is today
                $isToday = $selectedDate === date('Y-m-d');

                // If it's today, show a message about the 30-minute buffer
                if ($isToday) {
                    echo '<div class="alert alert-info" style="margin-bottom: 15px;">';
                    echo '<i class="fas fa-info-circle"></i> For same-day appointments, you can only select times at least 30 minutes from now.';
                    echo '</div>';

                    // Get current time plus 30 minutes buffer
                    $currentTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
                    $bufferTime = clone $currentTime;
                    $bufferTime->modify('+30 minutes');
                }

                // Generate time slots from 7:00 AM to 9:00 PM
                for ($hour = 7; $hour <= 21; $hour++) {
                    for ($minute = 0; $minute < 60; $minute += 60) {
                        // No need to skip any times with 1-hour intervals

                        $time = sprintf("%02d:%02d:00", $hour, $minute);
                        $displayTime = date("g:i A", strtotime($time));
                        $isBooked = in_array($time, $bookedTimes);

                        // Check if time is in the past (for today only)
                        $isPastTime = false;
                        if ($isToday) {
                            $slotTime = new DateTime($selectedDate . ' ' . $time, new DateTimeZone('Asia/Manila'));
                            if ($slotTime <= $bufferTime) {
                                $isPastTime = true;
                            }
                        }

                        // Set appropriate class
                        if ($isBooked) {
                            $class = 'time-slot booked';
                            $title = 'This time slot is already booked';
                        } elseif ($isPastTime) {
                            $class = 'time-slot past-time';
                            $title = 'This time has already passed or is too soon to book';
                        } else {
                            $class = 'time-slot';
                            $title = '';
                        }

                        // Output the time slot with appropriate class and title
                        echo "<div class=\"$class\" data-time=\"$time\"" . ($title ? " title=\"$title\"" : "") .
                             ($isBooked || $isPastTime ? " disabled" : "") . ">$displayTime</div>";
                    }
                }
                ?>
            </div>
        </div>

        <div class="appointment-form" id="appointmentForm">
            <h2>Appointment Details</h2>

            <div class="form-group">
                <p><strong>Date:</strong> <span id="summaryDate"><?= date('l, F j, Y', strtotime($selectedDate)) ?></span></p>
                <p><strong>Time:</strong> <span id="summaryTime">Select a time</span></p>
                <p><strong>Type of Place:</strong> <?= htmlspecialchars($client['type_of_place'] ?? 'Not set') ?></p>
            </div>

            <form id="bookingForm" method="post" action="save_appointment.php">
                <input type="hidden" id="preferred_date" name="preferred_date" value="<?= $selectedDate ?>">
                <input type="hidden" id="preferred_time" name="preferred_time" value="">
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
                <input type="hidden" name="client_name" value="<?= htmlspecialchars($_SESSION['fullname'] ?? '') ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($client['email'] ?? '') ?>">
                <input type="hidden" name="contact_number" value="<?= htmlspecialchars($client['contact_number'] ?? '') ?>">

                <div class="form-group">
                    <label for="location" class="form-label">Location Address</label>
                    <input type="text" id="location" name="location_address" class="form-control" value="<?= htmlspecialchars($client['display_address'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Type of Place</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($client['type_of_place'] ?? 'Not set') ?>" readonly>
                    <input type="hidden" name="kind_of_place" value="<?= htmlspecialchars($client['type_of_place'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">What kind of pest problem are you encountering?</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
                        <div>
                            <input type="checkbox" id="pest_flies" name="pest_problems[]" value="Flies">
                            <label for="pest_flies">Flies</label>
                        </div>
                        <div>
                            <input type="checkbox" id="pest_mice" name="pest_problems[]" value="Mice/Rats">
                            <label for="pest_mice">Mice/Rats</label>
                        </div>
                        <div>
                            <input type="checkbox" id="pest_ants" name="pest_problems[]" value="Ants">
                            <label for="pest_ants">Ants</label>
                        </div>
                        <div>
                            <input type="checkbox" id="pest_termites" name="pest_problems[]" value="Termites (White Ants)">
                            <label for="pest_termites">Termites (White Ants)</label>
                        </div>
                        <div>
                            <input type="checkbox" id="pest_cockroaches" name="pest_problems[]" value="Cockroaches">
                            <label for="pest_cockroaches">Cockroaches</label>
                        </div>
                        <div>
                            <input type="checkbox" id="pest_mosquitoes" name="pest_problems[]" value="Mosquitoes">
                            <label for="pest_mosquitoes">Mosquitoes</label>
                        </div>
                        <div>
                            <input type="checkbox" id="pest_bedbugs" name="pest_problems[]" value="Bed Bugs">
                            <label for="pest_bedbugs">Bed Bugs</label>
                        </div>
                        <div>
                            <input type="checkbox" id="pest_grass" name="pest_problems[]" value="Grass Problems">
                            <label for="pest_grass">Grass Problems</label>
                        </div>
                        <div>
                            <input type="checkbox" id="pest_disinfect" name="pest_problems[]" value="Disinfect Area">
                            <label for="pest_disinfect">Disinfect Area</label>
                        </div>
                        <div>
                            <input type="checkbox" id="pest_other" name="pest_problems[]" value="Other">
                            <label for="pest_other">Other (please specify below)</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes" class="form-label">Additional Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Provide any additional details about your pest problem or special instructions"></textarea>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> Confirm Appointment
                    </button>
                </div>
            </form>
        </div>

        <div class="nav-links">
            <a href="schedule.php"><i class="fas fa-arrow-left"></i> Back to Regular Schedule Page</a>
            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
            <a href="SignOut.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Simple, direct JavaScript with no AJAX
        $(document).ready(function() {
            console.log('Document ready in simple_schedule.php');

            // Handle time slot selection with event delegation
            $(document).on('click', '.time-slot:not(.booked):not(.past-time)', function() {
                const selectedTime = $(this).data('time');
                const displayTime = $(this).text();

                console.log('Selected time:', selectedTime, 'Display time:', displayTime);

                // Update UI
                $('.time-slot').removeClass('selected');
                $(this).addClass('selected');

                // Update form values
                $('#preferred_time').val(selectedTime);
                $('#summaryTime').text(displayTime);

                // Show appointment form
                $('#appointmentForm').show();

                // Scroll to appointment form
                $('html, body').animate({
                    scrollTop: $('#appointmentForm').offset().top - 20
                }, 500);
            });

            // Form validation
            $('#bookingForm').on('submit', function(e) {
                if (!$('#preferred_time').val()) {
                    alert('Please select a time slot first.');
                    e.preventDefault();
                    return false;
                }
                return true;
            });

            // Log the number of time slots for debugging
            console.log('Total time slots:', $('.time-slot').length);
            console.log('Available time slots:', $('.time-slot:not(.booked)').length);
            console.log('Booked time slots:', $('.time-slot.booked').length);
        });
    </script>

    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
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
