<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIU International Student Portal | Global Education Excellence</title>
    
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
            color: var(--white); /* Changed from gold to white */
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
        
        /* Hero Section - Enhanced */
        .hero-section {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            overflow: hidden;
            background: 
                linear-gradient(90deg, rgba(10,25,49,0.85) 0%, rgba(10,25,49,0.6) 50%, rgba(10,25,49,0.85) 100%),
                url('https://ace-sedi.aiu.edu.my/Chancellery%20daytime.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            clip-path: polygon(0 0, 100% 0, 100% 90%, 0 100%);
        }
        
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                45deg,
                rgba(10, 25, 49, 0.7) 0%,
                rgba(26, 54, 93, 0.5) 50%,
                rgba(10, 25, 49, 0.7) 100%
            );
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            padding: 8rem 0;
        }
        
        .hero-tagline {
            font-family: 'Cinzel', serif;
            font-size: 1.2rem;
            color: var(--gold);
            letter-spacing: 3px;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            opacity: 0;
            animation: fadeInUp 1s 0.5s forwards;
        }
        
        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: 4rem;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 2rem;
            color: var(--white);
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            opacity: 0;
            animation: fadeInUp 1s 0.7s forwards;
        }
        
        .hero-highlight {
            color: var(--gold);
            position: relative;
            display: inline-block;
        }
        
        .hero-highlight::after {
            content: '';
            position: absolute;
            bottom: 5px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--gradient-gold);
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            max-width: 600px;
            margin-bottom: 3rem;
            line-height: 1.6;
            opacity: 0;
            animation: fadeInUp 1s 0.9s forwards;
        }
        
        /* Action Buttons - Refined */
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
        
        /* Campus Showcase Section - Enhanced */
        .campus-section {
            padding: 8rem 0;
            background: var(--navy);
            position: relative;
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
        
        .section-description {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .campus-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 3rem;
        }
        
        .campus-card {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            height: 300px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .campus-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        }
        
        .campus-card.large {
            grid-column: span 2;
            height: 350px;
        }
        
        .campus-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.8s ease;
        }
        
        .campus-card:hover .campus-image {
            transform: scale(1.08);
        }
        
        .campus-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 2rem;
            background: linear-gradient(transparent, rgba(10, 25, 49, 0.95));
            color: white;
        }
        
        .campus-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        /* Features Section - Enhanced */
        .features-section {
            padding: 8rem 0;
            background: var(--royal-blue);
            position: relative;
            clip-path: polygon(0 8%, 100% 0, 100% 92%, 0 100%);
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(230, 179, 30, 0.1);
            border-radius: 12px;
            padding: 2.2rem;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .feature-card:hover {
            transform: translateY(-8px);
            border-color: var(--gold);
            box-shadow: 0 15px 30px rgba(230, 179, 30, 0.1);
        }
        
        .feature-icon {
            width: 65px;
            height: 65px;
            background: rgba(230, 179, 30, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            color: var(--gold);
            transition: all 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            background: var(--gradient-gold);
            color: var(--navy);
            transform: scale(1.05);
        }
        
        .feature-card h4 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: var(--white);
            font-family: 'Playfair Display', serif;
        }
        
        .feature-card p {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        /* Stats Section - Enhanced */
        .stats-section {
            padding: 6rem 0;
            background: 
                linear-gradient(rgba(10, 25, 49, 0.92), rgba(10, 25, 49, 0.92)),
                url('https://www.atsa.com.my/works/educational/albukhary-5.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 2.5rem;
        }
        
        .stat-item {
            padding: 1.5rem;
        }
        
        .stat-number {
            font-family: 'Cinzel', serif;
            font-size: 3rem;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.9);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* CTA Section - Enhanced */
        .cta-section {
            padding: 7rem 0;
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
        
        /* Footer - Enhanced */
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
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.03); }
            100% { transform: scale(1); }
        }
        
        .animate-pulse {
            animation: pulse 2s infinite;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .hero-title {
                font-size: 3.5rem;
            }
            
            .nav-cinematic {
                gap: 1.8rem;
            }
            
            .campus-card.large {
                grid-column: span 1;
            }
        }
        
        @media (max-width: 992px) {
            .hero-title {
                font-size: 3rem;
            }
            
            .section-title {
                font-size: 2.5rem;
            }
            
            .nav-cinematic {
                display: none;
            }
            
            .mobile-menu-btn {
                display: block !important;
            }
            
            .campus-gallery {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
            
            .section-title {
                font-size: 2.2rem;
            }
            
            .cta-title {
                font-size: 2.5rem;
            }
            
            .campus-card {
                height: 280px;
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
        
        /* Loader Animation */
        .loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--navy);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }
        
        .loader.hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(230, 179, 30, 0.3);
            border-top-color: var(--gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loader -->
    <div class="loader" id="loader">
        <div class="spinner"></div>
    </div>

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
    <section id="home" class="hero-section">
        <div class="hero-overlay"></div>
        <div class="container">
            <div class="hero-content">
                <div class="row align-items-center">
                    <div class="col-lg-7">
                        <div class="hero-tagline animate__animated animate__fadeInUp">
                            <i class="bi bi-star-fill me-2"></i> Excellence in Global Education
                        </div>
                        <h1 class="hero-title animate__animated animate__fadeInUp">
                            Welcome to<br>
                            <span class="hero-highlight">AIU International</span><br>
                            Student Portal
                        </h1>
                        <p class="hero-subtitle animate__animated animate__fadeInUp">
                            Experience the future of student management with our cinematic-grade portal. 
                            Seamlessly manage visas, insurance, academic records, and international 
                            student services in one breathtaking interface.
                        </p>
                        <div class="d-flex flex-wrap gap-3 mt-4 animate__animated animate__fadeInUp animate__delay-1s">
                            <a href="login.php" class="btn btn-primary-cinematic animate-pulse">
                                <i class="bi bi-box-arrow-in-right"></i> Access Staff Portal
                            </a>
                            <a href="#campus" class="btn btn-secondary-cinematic">
                                <i class="bi bi-play-circle"></i> Explore Our Campus
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Campus Showcase -->
    <section id="campus" class="campus-section">
        <div class="container">
            <div class="section-header">
                <div class="section-subtitle">Discover Our World-Class Campus</div>
                <h2 class="section-title">Where Excellence Meets Innovation</h2>
                <p class="section-description">
                    Experience the architectural marvels that inspire academic greatness
                </p>
            </div>
            
            <div class="campus-gallery">
                <div class="campus-card large">
                    <img src="https://ace-sedi.aiu.edu.my/Chancellery%20daytime.jpg" 
                         alt="AIU Chancellery" class="campus-image">
                    <div class="campus-overlay">
                        <h3 class="campus-name">AIU Chancellery</h3>
                        <p>The heart of administration and academic excellence</p>
                    </div>
                </div>
                
                <div class="campus-card">
                    <img src="https://www.atsa.com.my/works/educational/albukhary-5.jpg" 
                         alt="Modern Campus" class="campus-image">
                    <div class="campus-overlay">
                        <h3 class="campus-name">Modern Campus Facilities</h3>
                        <p>State-of-the-art infrastructure</p>
                    </div>
                </div>
                
                <div class="campus-card">
                    <img src="https://aiu.edu.my/wp-content/uploads/2022/05/AIU-Department-08.jpg" 
                         alt="Academic Departments" class="campus-image">
                    <div class="campus-overlay">
                        <h3 class="campus-name">Academic Departments</h3>
                        <p>Specialized learning environments</p>
                    </div>
                </div>
                
                <div class="campus-card">
                    <img src="https://aiu.edu.my/wp-content/uploads/2022/05/AIU-Department-05.jpg" 
                         alt="University Facilities" class="campus-image">
                    <div class="campus-overlay">
                        <h3 class="campus-name">University Facilities</h3>
                        <p>Modern learning spaces</p>
                    </div>
                </div>
                
                <div class="campus-card">
                    <img src="https://sudaneseresearchers.org/content/images/2024/05/AiU-Pond.jpg" 
                         alt="Campus Landscape" class="campus-image">
                    <div class="campus-overlay">
                        <h3 class="campus-name">Campus Landscape</h3>
                        <p>Beautiful green spaces</p>
                    </div>
                </div>
                
                <div class="campus-card">
                    <img src="https://beritappm.wordpress.com/wp-content/uploads/2024/07/aiu-2.jpg" 
                         alt="University Grounds" class="campus-image">
                    <div class="campus-overlay">
                        <h3 class="campus-name">University Grounds</h3>
                        <p>Inspiring academic environment</p>
                    </div>
                </div>

                <div class="campus-card">
                    <img src="https://aiu.edu.my/wp-content/uploads/2022/05/Football.jpg" 
                         alt="University Grounds" class="campus-image">
                    <div class="campus-overlay">
                        <h3 class="campus-name">University Field</h3>
                        <p>Inspiring athletic growth</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <div class="section-header">
                <div class="section-subtitle">Premium Experience</div>
                <h2 class="section-title">Advanced Management Features</h2>
                <p class="section-description">Designed with elegance and precision for modern student management</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card animate__animated" data-animate="fadeInUp">
                    <div class="feature-icon">
                        <i class="bi bi-globe-americas"></i>
                    </div>
                    <h4>Visa Management Suite</h4>
                    <p>Comprehensive visa tracking with automated expiry alerts, document management, and renewal workflows.</p>
                </div>
                
                <div class="feature-card animate__animated" data-animate="fadeInUp">
                    <div class="feature-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h4>Insurance Portal</h4>
                    <p>Complete insurance policy management with claims processing, coverage tracking, and provider integration.</p>
                </div>
                
                <div class="feature-card animate__animated" data-animate="fadeInUp">
                    <div class="feature-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h4>Advanced Analytics</h4>
                    <p>Real-time dashboards and comprehensive reporting for data-driven decision making.</p>
                </div>
                
                <div class="feature-card animate__animated" data-animate="fadeInUp">
                    <div class="feature-icon">
                        <i class="bi bi-chat-dots-fill"></i>
                    </div>
                    <h4>Student Communication</h4>
                    <p>Integrated messaging system with automated notifications and announcement management.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section id="stats" class="stats-section">
        <div class="container">
            <div class="section-header">
                <div class="section-subtitle">By The Numbers</div>
                <h2 class="section-title">Our Global Impact</h2>
            </div>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number" data-count="0">1200+</div>
                    <div class="stat-label">International Students</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-number" data-count="0">98%</div>
                    <div class="stat-label">Visa Success Rate</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-number" data-count="0">25</div>
                    <div class="stat-label">Partner Countries</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-number" data-count="0">150</div>
                    <div class="stat-label">Staff Members</div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section id="cta" class="cta-section">
        <div class="container">
            <h2 class="cta-title">Ready to Transform<br><span class="cta-highlight">Student Management?</span></h2>
            <p class="cta-subtitle">
                Join our community of staff members using the world's most advanced 
                international student management system.
            </p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="login.php" class="btn btn-primary-cinematic btn-lg">
                    <i class="bi bi-star-fill"></i> Get Started Today
                </a>
                <a href="#features" class="btn btn-secondary-cinematic btn-lg">
                    <i class="bi bi-play-btn"></i> Watch System Tour
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
        // Hide loader when page loads
        window.addEventListener('load', function() {
            setTimeout(() => {
                document.getElementById('loader').classList.add('hidden');
            }, 500);
        });

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

        // Animated counter - Random numbers
        function animateCounter() {
            const counters = document.querySelectorAll('.stat-number');
            
            // Generate random numbers for each counter
            const randomValues = [
                Math.floor(Math.random() * 2000) + 1500, // 1500-3500 students
                Math.floor(Math.random() * 10) + 90,     // 90-99% success rate
                Math.floor(Math.random() * 30) + 20,     // 20-50 countries
                Math.floor(Math.random() * 50) + 50      // 50-100 staff
            ];
            
            counters.forEach((counter, index) => {
                if (counter.getAttribute('data-animated') === 'true') return;
                
                const target = randomValues[index];
                counter.setAttribute('data-count', target);
                
                const duration = 2000;
                const increment = target / (duration / 16);
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                        counter.setAttribute('data-animated', 'true');
                    }
                    counter.textContent = Math.floor(current);
                }, 16);
            });
        }

        // Animate elements on scroll
        function animateOnScroll() {
            const elements = document.querySelectorAll('.animate__animated');
            
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const elementVisible = 150;
                
                if (elementTop < window.innerHeight - elementVisible) {
                    const animation = element.getAttribute('data-animate');
                    if (animation && !element.classList.contains('animate__' + animation)) {
                        element.classList.add('animate__' + animation);
                        
                        // Trigger counter animation for stats
                        if (element.closest('.stats-section')) {
                            animateCounter();
                        }
                    }
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

            // Parallax effect for hero section
            window.addEventListener('scroll', function() {
                const scrolled = window.pageYOffset;
                const hero = document.querySelector('.hero-section');
                if (hero) {
                    hero.style.backgroundPosition = `center ${scrolled * 0.5}px`;
                }
            });
        });

        // Intersection Observer for animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        // Observe feature cards
        document.querySelectorAll('.feature-card').forEach(card => {
            observer.observe(card);
        });

        // Add smooth hover effects for campus cards
        document.querySelectorAll('.campus-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.zIndex = '10';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.zIndex = '1';
            });
        });
    </script>
</body>
</html>