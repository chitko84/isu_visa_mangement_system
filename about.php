<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About AIU ISU | International Student Unit</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
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
        
        /* Hero Section - About Page */
        .about-hero {
            position: relative;
            min-height: 60vh;
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
        
        .about-hero-overlay {
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
        
        .about-hero-content {
            position: relative;
            z-index: 2;
            padding: 6rem 0;
        }
        
        .about-hero-title {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            color: var(--white);
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .about-hero-subtitle {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            max-width: 700px;
            line-height: 1.6;
        }
        
        /* Mission Section */
        .mission-section {
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
        
        .mission-content {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(230, 179, 30, 0.1);
            border-radius: 15px;
            padding: 3rem;
            margin-top: 3rem;
        }
        
        .mission-statement {
            font-size: 1.2rem;
            line-height: 1.8;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
        }
        
        .mission-values {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }
        
        .value-card {
            background: rgba(230, 179, 30, 0.05);
            border: 1px solid rgba(230, 179, 30, 0.1);
            border-radius: 12px;
            padding: 2rem;
            transition: all 0.3s ease;
        }
        
        .value-card:hover {
            transform: translateY(-5px);
            border-color: var(--gold);
            background: rgba(230, 179, 30, 0.1);
        }
        
        .value-icon {
            width: 60px;
            height: 60px;
            background: rgba(230, 179, 30, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            color: var(--gold);
        }
        
        .value-card h4 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: var(--white);
            font-family: 'Playfair Display', serif;
        }
        
        /* Team Section */
        .team-section {
            padding: 6rem 0;
            background: var(--royal-blue);
            clip-path: polygon(0 8%, 100% 0, 100% 92%, 0 100%);
        }
        
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2.5rem;
            margin-top: 3rem;
        }
        
        .team-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(230, 179, 30, 0.1);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.4s ease;
        }
        
        .team-card:hover {
            transform: translateY(-10px);
            border-color: var(--gold);
            box-shadow: 0 15px 30px rgba(230, 179, 30, 0.1);
        }
        
        .team-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        
        .team-info {
            padding: 2rem;
        }
        
        .team-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            margin-bottom: 0.5rem;
            color: var(--white);
        }
        
        .team-role {
            color: var(--gold);
            font-size: 0.95rem;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* History Section */
        .history-section {
            padding: 6rem 0;
            background: var(--navy);
        }
        
        .history-timeline {
            position: relative;
            max-width: 800px;
            margin: 4rem auto 0;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 3rem;
            margin-bottom: 3rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 2px;
            height: 100%;
            background: var(--gradient-gold);
        }
        
        .timeline-year {
            font-family: 'Cinzel', serif;
            font-size: 1.5rem;
            color: var(--gold);
            margin-bottom: 0.5rem;
        }
        
        .timeline-content {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(230, 179, 30, 0.1);
            border-radius: 10px;
            padding: 1.5rem;
        }
        
        /* Services Section */
        .services-section {
            padding: 6rem 0;
            background: var(--royal-blue);
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }
        
        .service-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(230, 179, 30, 0.1);
            border-radius: 15px;
            padding: 2.5rem;
            transition: all 0.3s ease;
        }
        
        .service-card:hover {
            transform: translateY(-8px);
            border-color: var(--gold);
            background: rgba(230, 179, 30, 0.05);
        }
        
        .service-icon {
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
        
        .service-card h4 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: var(--white);
            font-family: 'Playfair Display', serif;
        }
        
        /* CTA Section */
        .cta-section {
            padding: 6rem 0;
            background: var(--navy);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 30% 50%, rgba(230,179,30,0.08) 0%, transparent 50%),
                radial-gradient(circle at 70% 20%, rgba(26,54,93,0.2) 0%, transparent 50%);
        }
        
        .cta-title {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            margin-bottom: 1.5rem;
            color: var(--white);
        }
        
        .cta-highlight {
            color: var(--gold);
        }
        
        .cta-subtitle {
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto 3rem;
            color: rgba(255, 255, 255, 0.9);
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
        
        .btn-secondary-cinematic {
            background: transparent;
            color: var(--white);
            border: 1px solid var(--gold);
        }
        
        .btn-secondary-cinematic:hover {
            background: rgba(230, 179, 30, 0.1);
            transform: translateY(-3px);
            color: var(--white);
            border-color: var(--gold);
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
            
            .about-hero-title {
                font-size: 2.8rem;
            }
            
            .section-title {
                font-size: 2.3rem;
            }
        }
        
        @media (max-width: 768px) {
            .about-hero-title {
                font-size: 2.3rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .cta-title {
                font-size: 2.3rem;
            }
            
            .mission-content {
                padding: 2rem;
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
                    <a href="about.php" class="nav-link-cinematic active">About</a>
                    <a href="contact.php" class="nav-link-cinematic">Contact</a>
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
    <section class="about-hero">
        <div class="about-hero-overlay"></div>
        <div class="container">
            <div class="about-hero-content">
                <h1 class="about-hero-title animate__animated animate__fadeInUp">
                    About AIU International<br>
                    <span style="color: var(--gold);">Student Unit</span>
                </h1>
                <p class="about-hero-subtitle animate__animated animate__fadeInUp animate__delay-0-5s">
                    Dedicated to providing exceptional support and services to international students 
                    from admission to graduation and beyond. Empowering global learners since 2010.
                </p>
            </div>
        </div>
    </section>

    <!-- Mission Section -->
    <section class="mission-section">
        <div class="container">
            <div class="section-header">
                <div class="section-subtitle">Our Purpose</div>
                <h2 class="section-title">Mission & Vision</h2>
            </div>
            
            <div class="mission-content animate-in">
                <p class="mission-statement">
                    The AIU International Student Unit (ISU) is committed to creating an inclusive, 
                    supportive, and enriching environment for students from around the world. We strive 
                    to facilitate academic success, cultural integration, and personal growth through 
                    comprehensive services and dedicated support.
                </p>
                
                <div class="row">
                    <div class="col-lg-6">
                        <h3 style="color: var(--gold); margin-bottom: 1rem; font-family: 'Playfair Display', serif;">
                            Our Mission
                        </h3>
                        <p style="color: rgba(255,255,255,0.9); line-height: 1.7;">
                            To provide exceptional support services that enable international students 
                            to achieve academic excellence, cultural integration, and personal growth 
                            while fostering a globally-minded campus community.
                        </p>
                    </div>
                    <div class="col-lg-6">
                        <h3 style="color: var(--gold); margin-bottom: 1rem; font-family: 'Playfair Display', serif;">
                            Our Vision
                        </h3>
                        <p style="color: rgba(255,255,255,0.9); line-height: 1.7;">
                            To be the leading international student support unit in the region, 
                            recognized for excellence in student services, cultural programming, 
                            and global engagement initiatives.
                        </p>
                    </div>
                </div>
                
                <div class="mission-values">
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <h4>Student-Centered</h4>
                        <p style="color: rgba(255,255,255,0.8); font-size: 0.95rem;">
                            Every decision and service is designed with student success as our top priority.
                        </p>
                    </div>
                    
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="bi bi-globe2"></i>
                        </div>
                        <h4>Global Perspective</h4>
                        <p style="color: rgba(255,255,255,0.8); font-size: 0.95rem;">
                            Fostering cross-cultural understanding and global citizenship among all students.
                        </p>
                    </div>
                    
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h4>Integrity</h4>
                        <p style="color: rgba(255,255,255,0.8); font-size: 0.95rem;">
                            Maintaining the highest standards of ethical conduct and professional excellence.
                        </p>
                    </div>
                    
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="bi bi-lightbulb-fill"></i>
                        </div>
                        <h4>Innovation</h4>
                        <p style="color: rgba(255,255,255,0.8); font-size: 0.95rem;">
                            Continuously improving our services through technology and creative solutions.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services-section">
        <div class="container">
            <div class="section-header">
                <div class="section-subtitle">What We Offer</div>
                <h2 class="section-title">Our Core Services</h2>
            </div>
            
            <div class="services-grid">
                <div class="service-card animate-in">
                    <div class="service-icon">
                        <i class="bi bi-globe-americas"></i>
                    </div>
                    <h4>Visa & Immigration Support</h4>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.6; font-size: 0.95rem;">
                        Comprehensive visa application assistance, renewal services, and immigration 
                        compliance guidance to ensure students maintain legal status throughout their studies.
                    </p>
                </div>
                
                <div class="service-card animate-in">
                    <div class="service-icon">
                        <i class="bi bi-house-door-fill"></i>
                    </div>
                    <h4>Accommodation Assistance</h4>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.6; font-size: 0.95rem;">
                        Support in finding suitable on-campus and off-campus housing options, 
                        including temporary accommodation for new arrivals and housing transitions.
                    </p>
                </div>
                
                <div class="service-card animate-in">
                    <div class="service-icon">
                        <i class="bi bi-heart-pulse-fill"></i>
                    </div>
                    <h4>Health & Insurance</h4>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.6; font-size: 0.95rem;">
                        Guidance on mandatory health insurance requirements, medical facility 
                        referrals, and support during medical emergencies or health concerns.
                    </p>
                </div>
                
                <div class="service-card animate-in">
                    <div class="service-icon">
                        <i class="bi bi-book-fill"></i>
                    </div>
                    <h4>Academic Support</h4>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.6; font-size: 0.95rem;">
                        Academic advising, study skills workshops, tutoring referrals, and 
                        assistance with academic procedures and course registration.
                    </p>
                </div>
                
                <div class="service-card animate-in">
                    <div class="service-icon">
                        <i class="bi bi-currency-exchange"></i>
                    </div>
                    <h4>Financial Guidance</h4>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.6; font-size: 0.95rem;">
                        Assistance with banking procedures, scholarship information, 
                        financial planning, and understanding tuition fee structures.
                    </p>
                </div>
                
                <div class="service-card animate-in">
                    <div class="service-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h4>Cultural Integration</h4>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.6; font-size: 0.95rem;">
                        Orientation programs, cultural events, language support, and 
                        community-building activities to help students adapt to life in Malaysia.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- History Section -->
    <section class="history-section">
        <div class="container">
            <div class="section-header">
                <div class="section-subtitle">Our Journey</div>
                <h2 class="section-title">Milestones & Achievements</h2>
            </div>
            
            <div class="history-timeline animate-in">
                <div class="timeline-item">
                    <div class="timeline-year">2010</div>
                    <div class="timeline-content">
                        <h4 style="color: var(--white); margin-bottom: 0.5rem;">Establishment</h4>
                        <p style="color: rgba(255,255,255,0.8); line-height: 1.6;">
                            AIU International Student Unit was founded with a team of 5 dedicated staff 
                            members to serve 150 international students from 25 countries.
                        </p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-year">2014</div>
                    <div class="timeline-content">
                        <h4 style="color: var(--white); margin-bottom: 0.5rem;">Digital Transformation</h4>
                        <p style="color: rgba(255,255,255,0.8); line-height: 1.6;">
                            Launched the first online portal for visa applications and student services, 
                            reducing processing time by 60%.
                        </p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-year">2018</div>
                    <div class="timeline-content">
                        <h4 style="color: var(--white); margin-bottom: 0.5rem;">Regional Recognition</h4>
                        <p style="color: rgba(255,255,255,0.8); line-height: 1.6;">
                            Received "Best International Student Support Unit" award at the ASEAN 
                            Education Excellence Awards.
                        </p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-year">2022</div>
                    <div class="timeline-content">
                        <h4 style="color: var(--white); margin-bottom: 0.5rem;">System Upgrade</h4>
                        <p style="color: rgba(255,255,255,0.8); line-height: 1.6;">
                            Implemented the current ISU Management System with advanced analytics, 
                            automated workflows, and mobile accessibility.
                        </p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-year">2024</div>
                    <div class="timeline-content">
                        <h4 style="color: var(--white); margin-bottom: 0.5rem;">Current Status</h4>
                        <p style="color: rgba(255,255,255,0.8); line-height: 1.6;">
                            Serving over 1,500 international students from 50+ countries with a 
                            dedicated team of 100+ staff members and maintaining a 98% visa success rate.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="team-section">
        <div class="container">
            <div class="section-header">
                <div class="section-subtitle">Leadership</div>
                <h2 class="section-title">Meet Our Team</h2>
                <p style="color: rgba(255,255,255,0.8); max-width: 700px; margin: 0 auto;">
                    Our dedicated team of professionals brings decades of experience in international 
                    education, student services, and cross-cultural support.
                </p>
            </div>
            
            <div class="team-grid">
                <div class="team-card animate-in">
                    <img src="https://aiu.edu.my/wp-content/uploads/2022/06/20_NUR_SYAFIQAH_BINTI_NAZARY-200x200.jpg" 
                         alt="Director" class="team-image">
                    <div class="team-info">
                        <h3 class="team-name">Nur Syafiqah Binti Nazary</h3>
                        <div class="team-role">Senior Executive</div>
                        <!-- <p style="color: rgba(255,255,255,0.8); font-size: 0.95rem; line-height: 1.6;">
                            15+ years experience in international education. PhD in Cross-Cultural Studies.
                        </p> -->
                    </div>
                </div>
                
                <div class="team-card animate-in">
                    <img src="https://aiu.edu.my/wp-content/uploads/2022/05/22_AHMAD_MUAZ_BIN_AZIZAN-200x200.jpg" 
                         alt="Visa Manager" class="team-image">
                    <div class="team-info">
                        <h3 class="team-name">Ahmad Muaz Bin Azizan</h3>
                        <div class="team-role">Senior Executive</div>
                        <!-- <p style="color: rgba(255,255,255,0.8); font-size: 0.95rem; line-height: 1.6;">
                            Specialist in immigration law with 12 years of experience handling student visas.
                        </p> -->
                    </div>
                </div>
                
                <div class="team-card animate-in">
                    <img src="https://aiu.edu.my/wp-content/uploads/2022/05/21_SITI_NUR_AISYAH_BINTI_NOR_AZMAN-200x200.jpg" 
                         alt="Student Services" class="team-image">
                    <div class="team-info">
                        <h3 class="team-name">Siti Nur Aisyah Binti Nor Azman</h3>
                        <div class="team-role">Executive</div>
                        <!-- <p style="color: rgba(255,255,255,0.8); font-size: 0.95rem; line-height: 1.6;">
                            Masters in Counseling Psychology. Focuses on student wellness and cultural adjustment.
                        </p> -->
                    </div>
                </div>

                <div class="team-card animate-in">
                    <img src="https://aiu.edu.my/wp-content/uploads/2022/05/shazwani-ISU-200x200.jpg" 
                         alt="Student Services" class="team-image">
                    <div class="team-info">
                        <h3 class="team-name">Nurul Shazwani Binti Mohd Fahmi</h3>
                        <div class="team-role">Executive</div>
                        <!-- <p style="color: rgba(255,255,255,0.8); font-size: 0.95rem; line-height: 1.6;">
                            Masters in Counseling Psychology. Focuses on student wellness and cultural adjustment.
                        </p> -->
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2 class="cta-title">Join Our International<br><span class="cta-highlight">Community</span></h2>
            <p class="cta-subtitle">
                Experience world-class support and become part of our diverse global family at AIU.
            </p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="register.php" class="btn btn-primary-cinematic btn-lg">
                    <i class="bi bi-person-plus-fill"></i> Apply Now
                </a>
                <a href="contact.php" class="btn btn-secondary-cinematic btn-lg">
                    <i class="bi bi-envelope-fill"></i> Contact Us
                </a>
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
                        <h4 style="color: var(--gold); margin-bottom: 0.5rem;">AIU International</h4>
                        <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">Student Unit Portal</p>
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
                            <i class="bi bi-phone me-2"></i> +603-XXXX XXXX
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
                &copy; 2024 Asia International University - International Student Unit. All Rights Reserved.<br>
                ISU Management System v2.1 • Designed with cinematic excellence
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
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