<?php
session_start();
$isLoggedIn = isset($_SESSION['role']) && $_SESSION['role'] === 'client';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>MacJ Pest Control Services</title>
  <meta name="description" content="Professional pest control services for homes and businesses">
  <meta name="keywords" content="pest control, termite control, rodent control, pest management, MacJ">

  <!-- Favicons -->
  <link rel="icon" href="assets/img/favicon.png">
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">

  <?php if ($isLoggedIn): ?>
  <!-- Client-side CSS for logged-in users -->
  <link href="../Client Side/css/variables.css" rel="stylesheet">
  <link href="../Client Side/css/main.css" rel="stylesheet">
  <link href="../Client Side/css/header.css" rel="stylesheet">
  <link href="../Client Side/css/sidebar.css" rel="stylesheet">
  <link href="../Client Side/css/client-common.css" rel="stylesheet">
  <link href="../Client Side/css/footer.css" rel="stylesheet">
  <link href="../Client Side/css/landing-integration.css" rel="stylesheet">
  <link href="../Client Side/css/form-validation-fix.css" rel="stylesheet">
  <link href="../Client Side/css/content-spacing-fix.css" rel="stylesheet">
  <?php endif; ?>
</head>

<body class="index-page">

  <!-- Preloader removed for testing -->
  <!-- <div id="preloader"></div> -->

  <!-- Header -->
  <header id="header" class="header d-flex align-items-center fixed-top">
    <div class="container-fluid container-xl d-flex align-items-center justify-content-between">

      <a href="index.html" class="logo d-flex align-items-center">
        <img src="assets/img/MACJLOGO.png" alt="MACJ Pest Control" class="img-fluid">
      </a>

      <nav id="navmenu" class="navmenu">
        <ul>
          <li><a href="#hero" class="active">Home</a></li>
          <li><a href="#about">About</a></li>
          <li><a href="#services">Services</a></li>
        </ul>
        <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
      </nav>

      <div class="header-buttons d-flex align-items-center">
        <a class="btn-getstarted" href="../SignIn.php">Sign In</a>
      </div>

    </div>
  </header>

  <main class="main">

    <!-- Hero Section -->
    <section id="hero" class="hero section">
      <div class="container">
        <div class="row gy-5 align-items-center">
          <div class="col-lg-7 order-2 order-lg-1 d-flex flex-column justify-content-center">
            <h1>Professional Pest Control Solutions</h1>
            <p class="hero-text">We understand the importance of a pest-free environment for your home, business, and health. Our experienced team of licensed professionals is dedicated to providing top-notch pest control solutions tailored to meet your unique needs.</p>
            <div class="d-flex gap-4 mt-4">
              <a href="../SignIn.php" class="btn-get-started">Schedule Service</a>
            </div>
          </div>
          <div class="col-lg-5 order-1 order-lg-2 hero-img">
            <img src="assets/img/teammacj.jpg" class="img-fluid rounded-4 shadow-lg animated" alt="MACJ Pest Control Team">
          </div>
        </div>
      </div>
    </section><!-- End Hero Section -->



    <!-- About Section -->
    <section id="about" class="about section light-background">

      <!-- Section Title -->
      <div class="container section-title">
        <h2>About Us</h2>
        <p>Learn more about our company and our commitment to excellence</p>
      </div><!-- End Section Title -->

      <div class="container">
        <div class="row gy-5 align-items-center">

          <div class="content col-lg-5">
            <div class="about-img position-relative mb-4">
              <img src="assets/img/teammacj.jpg" class="img-fluid rounded-4 shadow" alt="MACJ Pest Control Team">
              <div class="experience-badge">
                <span class="years">21+</span>
                <span class="text">Years of Experience</span>
              </div>
            </div>
            <h3 class="mb-3">MACJ PEST CONTROL</h3>
            <p class="mb-4">
              Was founded by a licensed pest control professional with over twenty-one years of experience who is committed to developing and applying innovative solutions for various pest issues.
            </p>
            <div class="d-flex align-items-center mb-3">
              <i class="bi bi-check-circle-fill me-2 text-success"></i>
              <p class="mb-0">Licensed and certified professionals</p>
            </div>
            <div class="d-flex align-items-center mb-3">
              <i class="bi bi-check-circle-fill me-2 text-success"></i>
              <p class="mb-0">Eco-friendly pest control solutions</p>
            </div>
            <div class="d-flex align-items-center mb-4">
              <i class="bi bi-check-circle-fill me-2 text-success"></i>
              <p class="mb-0">Customized treatment plans</p>
            </div>

          </div>

          <div class="col-lg-7">
            <div class="row gy-4">

              <div class="col-md-6">
                <div class="icon-box">
                  <i class="bi bi-bullseye text-primary"></i>
                  <h4>MISSION</h4>
                  <p>To build and establish a successful relationship with our clients as well as our suppliers. To provide our clients high quality and high standard service. To provide more jobs in order to contribute to our economy as well as providing our people an employee program that will enhance their personal growth.</p>
                </div>
              </div><!-- Icon-Box -->

              <div class="col-md-6">
                <div class="icon-box">
                  <i class="bi bi-eye text-primary"></i>
                  <h4>VISION</h4>
                  <p>To evolve as the "most excellent service provider" in the market, providing quality and honest service that every customer deserves.</p>
                </div>
              </div><!-- Icon-Box -->

              <div class="col-md-6">
                <div class="icon-box">
                  <i class="bi bi-award text-primary"></i>
                  <h4>CERTIFICATIONS</h4>
                  <p>DUNS accredited, FPA License Fumigator and Exterminator, FDA License to Operate and member of KAPESTCOPI INC. (Kapisanan ng mga Pest Control Operators ng Pilipinas).</p>
                </div>
              </div><!-- Icon-Box -->

              <div class="col-md-6">
                <div class="icon-box">
                  <i class="bi bi-shield-check text-primary"></i>
                  <h4>OUR VALUES</h4>
                  <p>We are committed to integrity, excellence, innovation, and customer satisfaction in every service we provide. Your safety and satisfaction are our top priorities.</p>
                </div>
              </div><!-- Icon-Box -->

            </div>
          </div>

        </div>
      </div>

    </section><!-- End About Section -->

    <!-- Services Section -->
    <section id="services" class="services section">
      <div class="container">
        <!-- Section Title -->
        <div class="section-title">
          <h2>Our Services</h2>
          <p>Professional pest control solutions for your needs</p>
        </div>

        <?php
        // Connect to the database
        require_once '../db_config.php';

        // Query to get active services
        $services_query = "SELECT * FROM services WHERE status = 'active' ORDER BY name";
        $services_result = $pdo->query($services_query);
        $services = $services_result->fetchAll(PDO::FETCH_ASSOC);

        // Debug: Count services
        $service_count = count($services);
        echo "<!-- Debug: Found {$service_count} active services -->";

        // If no services found, show default message
        if (empty($services)) {
            echo '<div class="row mb-5">
                    <div class="col-12 text-center">
                      <div class="alert alert-info">
                        <h4>No Services Available</h4>
                        <p>Please check back later for our service offerings.</p>
                      </div>
                    </div>
                  </div>';
        } else {
            // Start the grid row
            echo '<div class="row">';

            // Display services in a grid
            foreach ($services as $index => $service) {
                // Set image path
                if (!empty($service['image']) && file_exists('../uploads/services/' . $service['image'])) {
                    $image_path = '../uploads/services/' . $service['image'];
                } else {
                    // Use a default image if the service image doesn't exist
                    $image_path = 'assets/img/default-service.jpg';
                }

                // Output the service card
                echo "
                <div class='col-md-6 col-lg-3 mb-4'>
                  <div class='service-card'>
                    <div class='service-img-container'>
                      <img src='{$image_path}' class='img-fluid service-img' alt='{$service['name']}'>
                    </div>
                    <div class='service-card-content'>
                      <h4>{$service['name']}</h4>
                      <p class='service-description'>" . substr($service['description'], 0, 100) . (strlen($service['description']) > 100 ? '...' : '') . "</p>
                      <a href='../SignIn.php' class='btn-service'>Schedule</a>
                    </div>
                  </div>
                </div>";
            }

            // Close the grid row
            echo '</div>';
        }
        ?>
      </div>
    </section><!-- End Services Section -->


    <!-- Testimonials Section -->
    <section id="testimonials" class="testimonials section light-background">
      <div class="container">
        <!-- Section Title -->
        <div class="section-title">
          <h2>Testimonials</h2>
          <p>What our clients say about our services</p>
        </div>

        <div class="row">
          <div class="col-12">
            <div id="testimonialCarousel" class="carousel slide" data-bs-ride="carousel">
              <div class="carousel-inner">
                <?php
                // Connect to the database
                require_once '../db_config.php';

                // Query to get feedback with 4 or 5 stars from both tables
                $feedback_query = "
                    (SELECT
                        jf.comments,
                        jf.rating,
                        jf.created_at,
                        c.first_name,
                        c.last_name,
                        c.type_of_place,
                        'job_order' AS feedback_type
                    FROM joborder_feedback jf
                    JOIN clients c ON jf.client_id = c.client_id
                    WHERE jf.rating >= 4)

                    UNION

                    (SELECT
                        tf.comments,
                        tf.rating,
                        tf.created_at,
                        c.first_name,
                        c.last_name,
                        c.type_of_place,
                        'technician' AS feedback_type
                    FROM technician_feedback tf
                    JOIN clients c ON tf.client_id = c.client_id
                    WHERE tf.rating >= 4)

                    ORDER BY created_at DESC
                    LIMIT 10";

                $feedback_result = $pdo->query($feedback_query);
                $feedbacks = $feedback_result->fetchAll(PDO::FETCH_ASSOC);

                // If no feedback found, show default testimonials
                if (empty($feedbacks)) {
                    // Default testimonials
                    $default_testimonials = [
                        [
                            'comments' => 'MacJ Pest Control provided exceptional service for our termite problem. Their team was professional, thorough, and explained every step of the treatment process. We\'ve been pest-free for over a year now!',
                            'first_name' => 'Maria',
                            'last_name' => 'Santos',
                            'type_of_place' => 'House',
                            'rating' => 5
                        ],
                        [
                            'comments' => 'As a restaurant owner, pest control is critical to our business. MacJ has been our trusted partner for over 5 years. Their preventive treatments and quick response times have kept our establishment pest-free and our customers happy.',
                            'first_name' => 'John',
                            'last_name' => 'Reyes',
                            'type_of_place' => 'Restaurant',
                            'rating' => 5
                        ],
                        [
                            'comments' => 'We hired MacJ for our office building\'s quarterly pest management. Their team is always punctual, professional, and thorough. The eco-friendly solutions they use are perfect for our workplace environment.',
                            'first_name' => 'Anna',
                            'last_name' => 'Cruz',
                            'type_of_place' => 'Office',
                            'rating' => 4
                        ]
                    ];
                    $feedbacks = $default_testimonials;
                }

                // Display testimonials
                foreach ($feedbacks as $index => $feedback) {
                    $active_class = ($index === 0) ? 'active' : '';
                    $occupation = !empty($feedback['type_of_place']) ? $feedback['type_of_place'] . ' Owner' : 'Client';

                    // Default image based on index
                    $image_index = ($index % 3) + 1;
                    $image_path = "assets/img/testimonials/testimonial-{$image_index}.jpg";

                    // Generate stars based on rating
                    $stars = '';
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $feedback['rating']) {
                            $stars .= '<i class="bi bi-star-fill"></i>';
                        } elseif ($i - 0.5 <= $feedback['rating']) {
                            $stars .= '<i class="bi bi-star-half"></i>';
                        } else {
                            $stars .= '<i class="bi bi-star"></i>';
                        }
                    }

                    echo "
                    <div class='carousel-item {$active_class}'>
                      <div class='testimonial-item'>
                        <div class='row justify-content-center'>
                          <div class='col-lg-8'>
                            <div class='testimonial-content text-center'>
                              <p>
                                <i class='bi bi-quote quote-icon-left'></i>
                                {$feedback['comments']}
                                <i class='bi bi-quote quote-icon-right'></i>
                              </p>
                              <div class='testimonial-img'>
                                <img src='{$image_path}' class='img-fluid rounded-circle' alt=''>
                              </div>
                              <h3>{$feedback['first_name']} {$feedback['last_name']}</h3>
                              <h4>{$occupation}</h4>
                              <div class='stars'>
                                {$stars}
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>";
                }
                ?>
              </div>

              <!-- Carousel Controls -->
              <button class="carousel-control-prev" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
              </button>
              <button class="carousel-control-next" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </section><!-- End Testimonials Section -->

    <!-- Call to Action Section -->
    <section id="cta" class="cta section">
      <div class="container">
        <div class="row g-5">
          <div class="col-lg-8 col-md-6 content d-flex flex-column justify-content-center order-last order-md-first">
            <h3>Ready for a Pest-Free Environment?</h3>
            <p>Schedule a consultation with our pest control experts today. We'll create a customized treatment plan tailored to your specific needs.</p>
            <div class="cta-btn-container">
              <a class="cta-btn align-self-start" href="../SignIn.php">Get Started</a>
            </div>
          </div>
          <div class="col-lg-4 col-md-6 order-first order-md-last d-flex align-items-center">
            <div class="img">
              <img src="assets/img/cta-image.jpg" alt="" class="img-fluid">
            </div>
          </div>
        </div>
      </div>
    </section><!-- End Call to Action Section -->
  </main>

  <footer id="footer" class="footer">

    <div class="container">
      <div class="row gy-4">
        <div class="col-lg-5 col-md-12 footer-info">
          <a href="index.html" class="logo d-flex align-items-center mb-3">
            <img src="assets/img/MACJLOGO.png" alt="MACJ Pest Control" class="img-fluid" style="max-height: 60px;">
          </a>
          <p>MacJ Pest Control Services provides professional pest management solutions for residential and commercial properties. With over 21 years of experience, we deliver effective and eco-friendly pest control services.</p>
          <h4 class="mt-4">Connect With Us</h4>
          <div class="social-links d-flex mt-3">
            <a href="#" class="twitter"><i class="bi bi-twitter-x"></i></a>
            <a href="#" class="facebook"><i class="bi bi-facebook"></i></a>
            <a href="#" class="instagram"><i class="bi bi-instagram"></i></a>
            <a href="#" class="linkedin"><i class="bi bi-linkedin"></i></a>
          </div>
        </div>

        <div class="col-lg-2 col-6 footer-links">
          <h4>Useful Links</h4>
          <ul>
            <li><a href="#hero">Home</a></li>
            <li><a href="#about">About Us</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="../SignIn.php">Sign In</a></li>
            <li><a href="#footer">Contact</a></li>
          </ul>
        </div>



        <div class="col-lg-3 col-md-12 footer-contact text-center text-md-start">
          <h4>Contact Us</h4>
          <p>
            30 Sto. Tomas St. <br>
            Brgy Don Manuel<br>
            Quezon City <br><br>
            <strong>Phone:</strong> (02)7369-3904/880-554040<br>
            <strong>Mobile:</strong> 09171457306 / 09055158398<br>
            <strong>Email:</strong> info@macjpestcontrol.com<br>
          </p>
        </div>

      </div>
    </div>

    <div class="container mt-4">
      <div class="copyright text-center">
        <p>© <span>Copyright</span> <strong class="px-1 sitename">MacJ Pest Control Services</strong> <span>All Rights Reserved</span></p>
      </div>
    </div>

  </footer>

  <!-- Scroll Top -->
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Preloader removed for testing -->
  <!-- <div id="preloader"></div> -->

  <!-- Vendor JS Files -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Main JS File (Simplified) -->
  <script src="assets/js/main-simple.js"></script>

  <?php if ($isLoggedIn): ?>
  <!-- Client-side JS for logged-in users -->
  <script src="../Client Side/js/main.js"></script>
  <script src="../Client Side/js/sidebar.js"></script>
  <script src="../Client Side/js/form-validation-fix.js"></script>
  <?php endif; ?>

  <!-- Additional script for services section animations -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Add animation to service items when they come into view
      const serviceItems = document.querySelectorAll('.service-item');

      if (serviceItems.length > 0) {
        console.log(`Found ${serviceItems.length} service items`);

        // Simple animation when scrolling to service items
        const observer = new IntersectionObserver((entries) => {
          entries.forEach(entry => {
            if (entry.isIntersecting) {
              entry.target.style.opacity = '1';
              entry.target.style.transform = 'translateY(0)';
              observer.unobserve(entry.target);
            }
          });
        }, { threshold: 0.1 });

        // Set initial styles and observe each service item
        serviceItems.forEach(item => {
          item.style.opacity = '0';
          item.style.transform = 'translateY(20px)';
          item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
          observer.observe(item);
        });
      }
    });
  </script>

</body>

</html>
