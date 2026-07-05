<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact AIU ISU | Albukhary International University</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- Leaflet CSS for interactive map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        :root {
            --gold: #E6B31E;
            --dark-gold: #C9A116;
            --navy: #0A1931;
            --royal-blue: #1A365D;
            --light-blue: #2D5F91;
            --white: #FFFFFF;
            --light-gray: #F5F5F5;
            --gradient-gold: linear-gradient(135deg, #E6B31E 0%, #C9A116 100%);
            --gradient-blue: linear-gradient(135deg, #0A1931 0%, #1A365D 100%);
            --gradient-overlay: linear-gradient(90deg, rgba(10,25,49,0.9) 0%, rgba(26,54,93,0.7) 50%, rgba(10,25,49,0.9) 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            color: var(--white);
            background: #000;
            overflow-x: hidden;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--navy);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--gradient-gold);
            border-radius: 5px;
        }
        
        /* Cinematic Navigation - Refined */
        .navbar-cinematic {
            background: rgba(10, 25, 49, 0.97);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(230, 179, 30, 0.15);
            padding: 1.2rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .navbar-cinematic.scrolled {
            padding: 0.9rem 0;
            background: rgba(10, 25, 49, 0.99);
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }
        
        .brand-section {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
        }
        
        .brand-logo {
            height: 50px;
            width: auto;
            filter: drop-shadow(0 0 8px rgba(230, 179, 30, 0.2));
            transition: transform 0.3s ease;
        }
        
        .brand-logo:hover {
            transform: scale(1.05);
        }
        
        .brand-text {
            font-family: 'Cinzel', serif;
            font-size: 1.7rem;
            font-weight: 600;
            color: var(--white);
            letter-spacing: 1px;
        }
        
        .nav-cinematic {
            display: flex;
            align-items: center;
            gap: 2.2rem;
        }
        
        .nav-link-cinematic {
            color: rgba(255, 255, 255, 0.85) !important;
            font-weight: 500;
            font-size: 0.95rem;
            letter-spacing: 1px;
            padding: 0.5rem 0 !important;
            position: relative;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .nav-link-cinematic::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: var(--gradient-gold);
            transition: width 0.3s ease;
        }
        
        .nav-link-cinematic:hover {
            color: var(--gold) !important;
        }
        
        .nav-link-cinematic:hover::after {
            width: 100%;
        }
        
        /* Hero Section - Contact Page */
        .contact-hero {
            position: relative;
            min-height: 50vh;
            display: flex;
            align-items: center;
            overflow: hidden;
            background: 
                linear-gradient(90deg, rgba(10,25,49,0.9) 0%, rgba(10,25,49,0.7) 50%, rgba(10,25,49,0.9) 100%),
                url('https://www.atsa.com.my/works/educational/albukhary-5.jpg');
            background-size: cover;
            background-position: center;
            margin-top: 76px;
        }
        
        .contact-hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                45deg,
                rgba(10, 25, 49, 0.8) 0%,
                rgba(26, 54, 93, 0.6) 50%,
                rgba(10, 25, 49, 0.8) 100%
            );
        }
        
        .contact-hero-content {
            position: relative;
            z-index: 2;
            padding: 5rem 0;
        }
        
        .contact-hero-title {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            color: var(--white);
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .contact-hero-subtitle {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            max-width: 700px;
            line-height: 1.6;
        }
        
        /* Contact Section */
        .contact-section {
            padding: 6rem 0;
            background: var(--navy);
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }
        
        .section-subtitle {
            font-family: 'Cinzel', serif;
            font-size: 1.1rem;
            color: var(--gold);
            letter-spacing: 2px;
            margin-bottom: 1rem;
            text-transform: uppercase;
        }
        
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 1rem;
        }
        
        /* Contact Grid */
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            margin-top: 3rem;
        }
        
        /* Contact Form */
        .contact-form-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(230, 179, 30, 0.1);
            border-radius: 15px;
            padding: 3rem;
        }
        
        .form-label {
            color: var(--white);
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-control-custom {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--white);
            padding: 0.8rem 1rem;
            border-radius: 8px;
            width: 100%;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }
        
        .form-control-custom:focus {
            outline: none;
            border-color: var(--gold);
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 2px rgba(230, 179, 30, 0.2);
        }
        
        .form-control-custom::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        textarea.form-control-custom {
            min-height: 150px;
            resize: vertical;
        }
        
        /* FIXED: Select dropdown text color */
        .form-control-custom option {
            color: #000 !important;
            background-color: white !important;
        }
        
        .form-control-custom select {
            color: var(--white) !important;
        }
        
        select.form-control-custom {
            color: var(--white) !important;
        }
        
        select.form-control-custom option {
            color: #000 !important;
            background: white !important;
        }
        
        /* Contact Info */
        .contact-info-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(230, 179, 30, 0.1);
            border-radius: 15px;
            padding: 3rem;
        }
        
        .contact-info-item {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
            padding-bottom: 2.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .contact-info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .contact-icon {
            width: 60px;
            height: 60px;
            background: rgba(230, 179, 30, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--gold);
            flex-shrink: 0;
        }
        
        .contact-details h4 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: var(--white);
            font-family: 'Playfair Display', serif;
        }
        
        .contact-details p {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.6;
            margin-bottom: 0.5rem;
        }
        
        .contact-link {
            color: var(--gold);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .contact-link:hover {
            color: var(--white);
            text-decoration: underline;
        }
        
        /* Office Hours */
        .office-hours {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .hours-table {
            width: 100%;
            margin-top: 1rem;
        }
        
        .hours-table tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .hours-table td {
            padding: 0.8rem 0;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .hours-table td:first-child {
            font-weight: 500;
            color: var(--white);
        }
        
        /* Departments Section */
        .departments-section {
            padding: 6rem 0;
            background: var(--royal-blue);
            clip-path: polygon(0 8%, 100% 0, 100% 92%, 0 100%);
        }
        
        .departments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }
        
        .department-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(230, 179, 30, 0.1);
            border-radius: 15px;
            padding: 2.5rem;
            transition: all 0.3s ease;
        }
        
        .department-card:hover {
            transform: translateY(-8px);
            border-color: var(--gold);
            background: rgba(230, 179, 30, 0.05);
        }
        
        .department-icon {
            width: 70px;
            height: 70px;
            background: rgba(230, 179, 30, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 2rem;
            color: var(--gold);
        }
        
        .department-card h4 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: var(--white);
            font-family: 'Playfair Display', serif;
        }
        
        /* Map Section */
        .map-section {
            padding: 6rem 0;
            background: var(--navy);
        }
        
        .map-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(230, 179, 30, 0.1);
            border-radius: 15px;
            overflow: hidden;
            height: 500px;
            margin-top: 3rem;
            position: relative;
        }
        
        #map {
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        
        .map-overlay {
            position: absolute;
            bottom: 20px;
            left: 20px;
            background: rgba(10, 25, 49, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(230, 179, 30, 0.3);
            border-radius: 10px;
            padding: 1.5rem;
            max-width: 300px;
            z-index: 10;
        }
        
        /* FAQ Section - Fixed */
        .faq-section {
            padding: 6rem 0;
            background: var(--royal-blue);
        }
        
        .accordion-custom {
            margin-top: 3rem;
        }
        
        .accordion-item-custom {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(230, 179, 30, 0.1);
            border-radius: 10px;
            margin-bottom: 1rem;
            overflow: hidden;
        }
        
        .accordion-button-custom {
            background: transparent;
            color: var(--white);
            font-weight: 500;
            padding: 1.5rem;
            width: 100%;
            text-align: left;
            border: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }
        
        .accordion-button-custom:hover {
            background: rgba(230, 179, 30, 0.05);
        }
        
        .accordion-button-custom.collapsed {
            background: transparent;
        }
        
        .accordion-button-custom:not(.collapsed) {
            background: rgba(230, 179, 30, 0.1);
        }
        
        .accordion-icon {
            color: var(--gold);
            transition: transform 0.3s ease;
            flex-shrink: 0;
        }
        
        .accordion-button-custom:not(.collapsed) .accordion-icon {
            transform: rotate(180deg);
        }
        
        .accordion-content-custom {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
            padding: 0 1.5rem;
        }
        
        .accordion-content-custom.show {
            max-height: 500px;
            padding: 0 1.5rem 1.5rem;
        }
        
        .accordion-content-custom > div {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.6;
            padding-top: 1rem;
        }
        
        /* Action Buttons */
        .btn-cinematic {
            padding: 1.1rem 2.5rem;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            border-radius: 6px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
            border: none;
        }
        
        .btn-cinematic::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.7s ease;
        }
        
        .btn-cinematic:hover::before {
            left: 100%;
        }
        
        .btn-primary-cinematic {
            background: var(--gradient-gold);
            color: var(--navy);
            box-shadow: 0 4px 15px rgba(230, 179, 30, 0.25);
        }
        
        .btn-primary-cinematic:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(230, 179, 30, 0.35);
            color: var(--navy);
        }
        
        /* Footer */
        .footer-cinematic {
            background: #000;
            padding: 4rem 0 2rem;
            border-top: 1px solid rgba(230, 179, 30, 0.1);
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            margin-bottom: 3rem;
        }
        
        .footer-brand {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .footer-logo {
            height: 45px;
            width: auto;
        }
        
        .copyright {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .footer-links {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }
        
        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .footer-links a:hover {
            color: var(--gold);
            transform: translateX(5px);
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .nav-cinematic {
                display: none;
            }
            
            .mobile-menu-btn {
                display: block !important;
            }
            
            .contact-hero-title {
                font-size: 2.8rem;
            }
            
            .section-title {
                font-size: 2.3rem;
            }
            
            .contact-grid {
                grid-template-columns: 1fr;
                gap: 3rem;
            }
        }
        
        @media (max-width: 768px) {
            .contact-hero-title {
                font-size: 2.3rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .contact-form-container,
            .contact-info-container {
                padding: 2rem;
            }
            
            .map-container {
                height: 400px;
            }
            
            .map-overlay {
                max-width: 250px;
                padding: 1rem;
            }
        }
        
        .mobile-menu-btn {
            display: none;
            background: transparent;
            border: none;
            color: var(--gold);
            font-size: 1.5rem;
        }
        
        /* Mobile Menu */
        .mobile-menu {
            position: fixed;
            top: 0;
            right: -100%;
            width: 280px;
            height: 100vh;
            background: var(--navy);
            z-index: 2000;
            transition: right 0.4s ease;
            padding: 2rem;
            box-shadow: -5px 0 25px rgba(0,0,0,0.4);
        }
        
        .mobile-menu.active {
            right: 0;
        }
        
        .mobile-menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
        }
        
        .mobile-nav {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }
        
        .mobile-nav a {
            color: var(--white);
            text-decoration: none;
            font-size: 1.1rem;
            padding: 0.8rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            background: rgba(0,0,0,0.7);
            z-index: 1999;
            display: none;
        }
        
        .mobile-overlay.active {
            display: block;
        }
        
        /* Success Message */
        .success-message {
            background: rgba(46, 204, 113, 0.1);
            border: 1px solid rgba(46, 204, 113, 0.3);
            color: #2ecc71;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1.5rem;
            display: none;
        }
        
        /* Animations */
        .animate-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }
        
        .animate-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Leaflet Map Custom Styling */
        .leaflet-container {
            font-family: 'Inter', sans-serif !important;
        }
        
        .leaflet-control-zoom a {
            background-color: var(--navy) !important;
            color: var(--white) !important;
            border-color: rgba(230, 179, 30, 0.3) !important;
        }
        
        .leaflet-control-zoom a:hover {
            background-color: var(--royal-blue) !important;
        }
        
        .leaflet-popup-content-wrapper {
            background: var(--navy) !important;
            color: var(--white) !important;
            border: 1px solid rgba(230, 179, 30, 0.3) !important;
            border-radius: 8px !important;
        }
        
        .leaflet-popup-tip {
            background: var(--navy) !important;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar-cinematic">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <a href="index.php" class="brand-section">
                    <img src="https://aiu.edu.my/wp-content/uploads/2022/11/AIULogo-512x521-01.jpg" 
                         alt="AIU Logo" class="brand-logo">
                    <span class="brand-text">ISU PORTAL</span>
                </a>
                
                <div class="nav-cinematic d-none d-lg-flex">
                    <a href="index.php" class="nav-link-cinematic">Home</a>
                    <a href="about.php" class="nav-link-cinematic">About</a>
                    <a href="contact.php" class="nav-link-cinematic active">Contact</a>
                    <a href="login.php" class="nav-link-cinematic">Login</a>
                </div>
                
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="bi bi-list"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-overlay" id="mobileOverlay"></div>
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <h4>Menu</h4>
            <button id="closeMobileMenu" style="background: none; border: none; color: var(--gold); font-size: 1.5rem;">
                <i class="bi bi-x"></i>
            </button>
        </div>
        <div class="mobile-nav">
            <a href="index.php">Home</a>
            <a href="about.php">About</a>
            <a href="contact.php">Contact</a>
            <a href="login.php">Login</a>
            <a href="register.php" class="btn btn-primary-cinematic mt-3">Register Now</a>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="contact-hero">
        <div class="contact-hero-overlay"></div>
        <div class="container">
            <div class="contact-hero-content">
                <h1 class="contact-hero-title animate__animated animate__fadeInUp">
                    Get in Touch with<br>
                    <span style="color: var(--gold);">Albukhary International University</span><br>
                    Student Unit
                </h1>
                <p class="contact-hero-subtitle animate__animated animate__fadeInUp animate__delay-0-5s">
                    We're here to help! Whether you have questions about admissions, need visa assistance, 
                    or want to learn more about our services, our team in Alor Setar, Kedah is ready to assist you.
                </p>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <div class="section-header">
                <div class="section-subtitle">Reach Out to Us</div>
                <h2 class="section-title">Contact Information</h2>
                <p style="color: rgba(255,255,255,0.8); max-width: 700px; margin: 0 auto;">
                    Choose your preferred method of communication. We typically respond within 24-48 hours 
                    during business days from our campus in Alor Setar, Kedah.
                </p>
            </div>
            
            <div class="contact-grid">
                <!-- Contact Form -->
                <div class="contact-form-container animate-in">
                    <h3 style="color: var(--white); margin-bottom: 2rem; font-family: 'Playfair Display', serif;">
                        Send Us a Message
                    </h3>
                    
                    <form id="contactForm">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control-custom" placeholder="Enter your first name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control-custom" placeholder="Enter your last name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Email Address *</label>
                                <input type="email" class="form-control-custom" placeholder="Enter your email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control-custom" placeholder="Enter your phone number">
                            </div>
                        </div>
                        
                        <div>
                            <label class="form-label">Subject *</label>
                            <select class="form-control-custom" required style="color: var(--white);">
                                <option value="" disabled selected style="color: rgba(255,255,255,0.5);">Select a subject</option>
                                <option value="visa" style="color: #000;">Visa & Immigration</option>
                                <option value="admission" style="color: #000;">Admissions Inquiry</option>
                                <option value="accommodation" style="color: #000;">Accommodation</option>
                                <option value="insurance" style="color: #000;">Health Insurance</option>
                                <option value="academic" style="color: #000;">Academic Support</option>
                                <option value="other" style="color: #000;">Other Inquiry</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label">Message *</label>
                            <textarea class="form-control-custom" placeholder="Please describe your inquiry in detail..." required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary-cinematic w-100">
                            <i class="bi bi-send-fill me-2"></i> Send Message
                        </button>
                        
                        <div class="success-message" id="successMessage">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            Thank you! Your message has been sent successfully. We'll get back to you soon.
                        </div>
                    </form>
                </div>
                
                <!-- Contact Information -->
                <div class="contact-info-container animate-in">
                    <h3 style="color: var(--white); margin-bottom: 2rem; font-family: 'Playfair Display', serif;">
                        Contact Details
                    </h3>
                    
                    <div class="contact-info-item">
                        <div class="contact-icon">
                            <i class="bi bi-geo-alt-fill"></i>
                        </div>
                        <div class="contact-details">
                            <h4>Visit Our Office</h4>
                            <p>International Student Unit</p>
                            <p>Albukhary International University</p>
                            <p>Jalan Tun Abdul Razak, Bandar Darul Aman</p>
                            <p>05100 Alor Setar, Kedah, Malaysia</p>
                            <a href="#map" class="contact-link">
                                <i class="bi bi-map me-1"></i> View on Map
                            </a>
                        </div>
                    </div>
                    
                    <div class="contact-info-item">
                        <div class="contact-icon">
                            <i class="bi bi-telephone-fill"></i>
                        </div>
                        <div class="contact-details">
                            <h4>Call Us</h4>
                            <p>General Inquiries: +604-773 3333</p>
                            <p>Visa Support: +604-773 3334</p>
                            <p>Emergency: +604-773 3335</p>
                            <a href="tel:+6047733333" class="contact-link">
                                <i class="bi bi-telephone-outbound me-1"></i> Call Now
                            </a>
                        </div>
                    </div>
                    
                    <div class="contact-info-item">
                        <div class="contact-icon">
                            <i class="bi bi-envelope-fill"></i>
                        </div>
                        <div class="contact-details">
                            <h4>Email Us</h4>
                            <p>General: isu@aiu.edu.my</p>
                            <p>Visa: visa@aiu.edu.my</p>
                            <p>Admissions: admissions@aiu.edu.my</p>
                            <a href="mailto:isu@aiu.edu.my" class="contact-link">
                                <i class="bi bi-envelope-paper me-1"></i> Send Email
                            </a>
                        </div>
                    </div>
                    
                    <div class="office-hours">
                        <h4 style="color: var(--white); margin-bottom: 1rem; font-family: 'Playfair Display', serif;">
                            Office Hours
                        </h4>
                        <table class="hours-table">
                            <tr>
                                <td>Monday - Thursday</td>
                                <td>8:30 AM - 5:30 PM</td>
                            </tr>
                            <tr>
                                <td>Friday</td>
                                <td>8:30 AM - 12:15 PM, 2:45 PM - 5:30 PM</td>
                            </tr>
                            <tr>
                                <td>Saturday</td>
                                <td>9:00 AM - 1:00 PM</td>
                            </tr>
                            <tr>
                                <td>Sunday & Public Holidays</td>
                                <td>Closed</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Departments Section -->
    <section class="departments-section">
        <div class="container">
            <div class="section-header">
                <div class="section-subtitle">Specialized Support</div>
                <h2 class="section-title">Contact by Department</h2>
                <p style="color: rgba(255,255,255,0.8); max-width: 700px; margin: 0 auto;">
                    For faster assistance, contact the specific department handling your inquiry at our Alor Setar campus.
                </p>
            </div>
            
            <div class="departments-grid">
                <div class="department-card animate-in">
                    <div class="department-icon">
                        <i class="bi bi-globe-americas"></i>
                    </div>
                    <h4>Visa & Immigration</h4>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.6; font-size: 0.95rem;">
                        Visa applications, renewals, extensions, and immigration compliance.
                        <br><br>
                        <strong>Email:</strong> visa@aiu.edu.my<br>
                        <strong>Phone:</strong> +604-773 3334
                    </p>
                </div>
                
                <div class="department-card animate-in">
                    <div class="department-icon">
                        <i class="bi bi-person-badge-fill"></i>
                    </div>
                    <h4>Admissions</h4>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.6; font-size: 0.95rem;">
                        Application process, requirements, deadlines, and offer letters.
                        <br><br>
                        <strong>Email:</strong> admissions@aiu.edu.my<br>
                        <strong>Phone:</strong> +604-773 3336
                    </p>
                </div>
                
                <div class="department-card animate-in">
                    <div class="department-icon">
                        <i class="bi bi-house-heart-fill"></i>
                    </div>
                    <h4>Accommodation</h4>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.6; font-size: 0.95rem;">
                        On-campus housing, off-campus options, and housing transitions.
                        <br><br>
                        <strong>Email:</strong> housing@aiu.edu.my<br>
                        <strong>Phone:</strong> +604-773 3337
                    </p>
                </div>
                
                <div class="department-card animate-in">
                    <div class="department-icon">
                        <i class="bi bi-heart-pulse-fill"></i>
                    </div>
                    <h4>Health Services</h4>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.6; font-size: 0.95rem;">
                        Health insurance, medical referrals, and wellness support.
                        <br><br>
                        <strong>Email:</strong> health@aiu.edu.my<br>
                        <strong>Phone:</strong> +604-773 3338
                    </p>
                </div>
                
                <div class="department-card animate-in">
                    <div class="department-icon">
                        <i class="bi bi-currency-exchange"></i>
                    </div>
                    <h4>Financial Aid</h4>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.6; font-size: 0.95rem;">
                        Scholarships, tuition fees, payment plans, and financial advice.
                        <br><br>
                        <strong>Email:</strong> financial@aiu.edu.my<br>
                        <strong>Phone:</strong> +604-773 3339
                    </p>
                </div>
                
                <div class="department-card animate-in">
                    <div class="department-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h4>Student Life</h4>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.6; font-size: 0.95rem;">
                        Cultural activities, clubs, events, and community integration.
                        <br><br>
                        <strong>Email:</strong> studentlife@aiu.edu.my<br>
                        <strong>Phone:</strong> +604-773 3340
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section class="map-section">
        <div class="container">
            <div class="section-header">
                <div class="section-subtitle">Find Us</div>
                <h2 class="section-title">Our Location in Alor Setar</h2>
                <p style="color: rgba(255,255,255,0.8); max-width: 700px; margin: 0 auto;">
                    Visit our office at Albukhary International University in Alor Setar, Kedah. 
                    Our campus is strategically located in Bandar Darul Aman with excellent access to city amenities.
                </p>
            </div>
            
            <div class="map-container">
                <div id="map"></div>
                <div class="map-overlay">
                    <h4 style="color: var(--white); margin-bottom: 0.5rem; font-size: 1.1rem;">
                        Albukhary International University
                    </h4>
                    <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem; margin-bottom: 0.5rem;">
                        International Student Unit<br>
                        Jalan Tun Abdul Razak<br>
                        Bandar Darul Aman<br>
                        05100 Alor Setar, Kedah
                    </p>
                    <a href="https://maps.google.com/?q=Albukhary+International+University+Alor+Setar" 
                       target="_blank" 
                       style="color: var(--gold); font-size: 0.9rem; text-decoration: none;">
                        <i class="bi bi-arrow-up-right-square me-1"></i> Open in Google Maps
                    </a>
                </div>
            </div>
            
            <div class="row mt-5">
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div style="color: var(--gold); font-size: 1.2rem;">
                            <i class="bi bi-train-front"></i>
                        </div>
                        <div>
                            <h5 style="color: var(--white); margin: 0;">Public Transport</h5>
                            <p style="color: rgba(255,255,255,0.8); margin: 0; font-size: 0.9rem;">
                                10-min drive from Alor Setar Railway Station
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div style="color: var(--gold); font-size: 1.2rem;">
                            <i class="bi bi-car-front"></i>
                        </div>
                        <div>
                            <h5 style="color: var(--white); margin: 0;">Parking</h5>
                            <p style="color: rgba(255,255,255,0.8); margin: 0; font-size: 0.9rem;">
                                Ample visitor parking available on campus
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div style="color: var(--gold); font-size: 1.2rem;">
                            <i class="bi bi-wheelchair"></i>
                        </div>
                        <div>
                            <h5 style="color: var(--white); margin: 0;">Accessibility</h5>
                            <p style="color: rgba(255,255,255,0.8); margin: 0; font-size: 0.9rem;">
                                Fully wheelchair accessible campus facilities
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <div class="section-header">
                <div class="section-subtitle">Quick Answers</div>
                <h2 class="section-title">Frequently Asked Questions</h2>
                <p style="color: rgba(255,255,255,0.8); max-width: 700px; margin: 0 auto;">
                    Find quick answers to common questions about our services and procedures at Albukhary International University.
                </p>
            </div>
            
            <div class="accordion-custom">
                <div class="accordion-item-custom animate-in">
                    <button class="accordion-button-custom" onclick="toggleAccordion(this)">
                        <span>What documents do I need for visa application at AIU Alor Setar?</span>
                        <i class="bi bi-chevron-down accordion-icon"></i>
                    </button>
                    <div class="accordion-content-custom">
                        <div>
                            You'll need your passport (minimum 18 months validity), offer letter from AIU Alor Setar, 
                            proof of financial capability, health insurance certificate, and completed visa 
                            application form. Additional documents may be required based on your country of origin.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item-custom animate-in">
                    <button class="accordion-button-custom" onclick="toggleAccordion(this)">
                        <span>How long does visa processing take at AIU Alor Setar?</span>
                        <i class="bi bi-chevron-down accordion-icon"></i>
                    </button>
                    <div class="accordion-content-custom">
                        <div>
                            Standard visa processing takes 4-6 weeks. We recommend applying at least 8 weeks 
                            before your intended travel date. Express processing (2-3 weeks) is available for 
                            an additional fee.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item-custom animate-in">
                    <button class="accordion-button-custom" onclick="toggleAccordion(this)">
                        <span>Do I need health insurance as an international student at AIU?</span>
                        <i class="bi bi-chevron-down accordion-icon"></i>
                    </button>
                    <div class="accordion-content-custom">
                        <div>
                            Yes, all international students must have valid health insurance for the entire 
                            duration of their studies in Malaysia. AIU offers a comprehensive health insurance 
                            plan that meets all government requirements.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item-custom animate-in">
                    <button class="accordion-button-custom" onclick="toggleAccordion(this)">
                        <span>Can I work while studying at Albukhary International University?</span>
                        <i class="bi bi-chevron-down accordion-icon"></i>
                    </button>
                    <div class="accordion-content-custom">
                        <div>
                            International students are allowed to work part-time (maximum 20 hours per week) 
                            during semester and full-time during semester breaks, subject to approval from 
                            the Immigration Department. Our office in Alor Setar can assist with work permit applications.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item-custom animate-in">
                    <button class="accordion-button-custom" onclick="toggleAccordion(this)">
                        <span>How do I extend my student visa at AIU Alor Setar?</span>
                        <i class="bi bi-chevron-down accordion-icon"></i>
                    </button>
                    <div class="accordion-content-custom">
                        <div>
                            Visa extensions must be applied for at least 2 months before expiry. You'll need 
                            to provide updated academic records, proof of continued enrollment, and financial 
                            documents. Our visa team in Alor Setar will guide you through the entire process.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item-custom animate-in">
                    <button class="accordion-button-custom" onclick="toggleAccordion(this)">
                        <span>What emergency services are available at AIU Alor Setar?</span>
                        <i class="bi bi-chevron-down accordion-icon"></i>
                    </button>
                    <div class="accordion-content-custom">
                        <div>
                            We provide 24/7 emergency support for international students. Call our emergency 
                            hotline at +604-773 3335 for immediate assistance with medical emergencies, 
                            security concerns, or urgent visa issues outside office hours.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-cinematic">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <img src="https://aiu.edu.my/wp-content/uploads/2022/11/AIULogo-512x521-01.jpg" 
                         alt="AIU Logo" class="footer-logo">
                    <div>
                        <h4 style="color: var(--gold); margin-bottom: 0.5rem;">Albukhary International University</h4>
                        <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">International Student Unit</p>
                    </div>
                </div>
                
                <div>
                    <h5 style="color: var(--gold); margin-bottom: 1rem;">Quick Links</h5>
                    <div class="footer-links">
                        <a href="index.php">Home</a>
                        <a href="about.php">About</a>
                        <a href="contact.php">Contact</a>
                        <a href="register.php">Register Now</a>
                    </div>
                </div>
                
                <div>
                    <h5 style="color: var(--gold); margin-bottom: 1rem;">Contact</h5>
                    <div class="footer-links">
                        <p style="color: rgba(255,255,255,0.8); margin: 0 0 0.5rem 0;">
                            <i class="bi bi-envelope me-2"></i> isu@aiu.edu.my
                        </p>
                        <p style="color: rgba(255,255,255,0.8); margin: 0;">
                            <i class="bi bi-phone me-2"></i> +604-773 3333
                        </p>
                    </div>
                </div>
                
                <div>
                    <h5 style="color: var(--gold); margin-bottom: 1rem;">Connect</h5>
                    <div class="d-flex gap-3">
                        <a href="#" style="color: rgba(255,255,255,0.8); font-size: 1.2rem;">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" style="color: rgba(255,255,255,0.8); font-size: 1.2rem;">
                            <i class="bi bi-twitter"></i>
                        </a>
                        <a href="#" style="color: rgba(255,255,255,0.8); font-size: 1.2rem;">
                            <i class="bi bi-linkedin"></i>
                        </a>
                        <a href="#" style="color: rgba(255,255,255,0.8); font-size: 1.2rem;">
                            <i class="bi bi-instagram"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="copyright">
                &copy; 2024 Albukhary International University - International Student Unit. All Rights Reserved.<br>
                Jalan Tun Abdul Razak, Bandar Darul Aman, 05100 Alor Setar, Kedah, Malaysia<br>
                ISU Management System v2.1 • Designed with cinematic excellence
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar-cinematic');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
            
            // Animate elements on scroll
            animateOnScroll();
        });

        // Mobile menu functionality
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const closeMobileMenu = document.getElementById('closeMobileMenu');
        const mobileMenu = document.getElementById('mobileMenu');
        const mobileOverlay = document.getElementById('mobileOverlay');

        function openMobileMenu() {
            mobileMenu.classList.add('active');
            mobileOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileMenuFunc() {
            mobileMenu.classList.remove('active');
            mobileOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        mobileMenuBtn.addEventListener('click', openMobileMenu);
        closeMobileMenu.addEventListener('click', closeMobileMenuFunc);
        mobileOverlay.addEventListener('click', closeMobileMenuFunc);

        // Close mobile menu when clicking links
        document.querySelectorAll('.mobile-nav a').forEach(link => {
            link.addEventListener('click', closeMobileMenuFunc);
        });

        // Contact Form Submission
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show success message
            const successMessage = document.getElementById('successMessage');
            successMessage.style.display = 'block';
            
            // Reset form after 3 seconds
            setTimeout(() => {
                this.reset();
                successMessage.style.display = 'none';
            }, 3000);
        });

        // FAQ Accordion Function - FIXED
        function toggleAccordion(button) {
            const content = button.nextElementSibling;
            const icon = button.querySelector('.accordion-icon');
            
            // Toggle collapsed class
            button.classList.toggle('collapsed');
            
            // Toggle content visibility
            content.classList.toggle('show');
            
            // Rotate icon
            if (button.classList.contains('collapsed')) {
                icon.style.transform = 'rotate(0deg)';
            } else {
                icon.style.transform = 'rotate(180deg)';
            }
        }

        // FIXED: Interactive Map - Using Leaflet
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map
            const map = L.map('map').setView([6.1184, 100.3661], 16); // Alor Setar, Kedah coordinates
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);
            
            // Custom golden marker icon
            const goldIcon = L.divIcon({
                html: '<i class="bi bi-geo-alt-fill" style="color: #E6B31E; font-size: 2rem; background: rgba(10, 25, 49, 0.8); border-radius: 50%; padding: 5px; border: 2px solid #E6B31E;"></i>',
                iconSize: [40, 40],
                iconAnchor: [20, 40],
                popupAnchor: [0, -40],
                className: 'custom-marker'
            });
            
            // Add marker
            const marker = L.marker([6.1184, 100.3661], { icon: goldIcon }).addTo(map);
            
            // Add popup
            marker.bindPopup(`
                <div style="color: #0A1931; font-family: 'Inter', sans-serif;">
                    <h4 style="margin: 0 0 8px 0; color: #0A1931; font-weight: 600;">Albukhary International University</h4>
                    <p style="margin: 0; color: #666; font-size: 0.9rem;">
                        International Student Unit<br>
                        Jalan Tun Abdul Razak<br>
                        Bandar Darul Aman<br>
                        05100 Alor Setar, Kedah
                    </p>
                </div>
            `);
            
            // Add zoom controls
            L.control.zoom({
                position: 'topright'
            }).addTo(map);
        });

        // Animate elements on scroll
        function animateOnScroll() {
            const elements = document.querySelectorAll('.animate-in');
            
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const elementVisible = 150;
                
                if (elementTop < window.innerHeight - elementVisible) {
                    element.classList.add('visible');
                }
            });
        }

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 80,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // Initial animation check
            animateOnScroll();
        });

        // Scroll animation observer
        window.addEventListener('scroll', animateOnScroll);
    </script>
</body>
</html>