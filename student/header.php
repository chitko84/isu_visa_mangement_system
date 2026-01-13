<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

// Include database
require_once '../includes/db.php';

// Get student ID from session
$student_id = $_SESSION['user_id'];

// Fetch student basic info for header
$student_query = "SELECT first_name, last_name, email FROM student WHERE student_id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Check if student was found
if (!$student) {
    // Student not found in database, log out
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Get current page title
$page_title = isset($page_title) ? $page_title : 'Student Dashboard - ISU';

// Get initials for avatar
$initials = substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1);
$initials = strtoupper($initials);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="../bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="student_style.css">
    
    <style>
        :root {
            --primary-blue: #0e2a47;
            --secondary-blue: #1a5276;
            --dark-blue: #0b1f33;
            --light-blue: #e8f4fd;
            --accent-green: #2ecc71;
            --accent-red: #e74c3c;
            --text-gray: #3E3E3E;
            --border-gray: #E0E0E0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            color: var(--text-gray);
            overflow-x: hidden;
        }
        
        /* Student Dashboard Layout */
        .student-container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }
        
        /* Sidebar Styles */
        .student-sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 5px 0 15px rgba(0,0,0,0.1);
            left: 0;
            top: 0;
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            position: relative;
        }
        
        .sidebar-header .logo {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 1rem;
        }
        
        .sidebar-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .sidebar-header p {
            font-size: 0.85rem;
            opacity: 0.8;
            margin: 0;
        }
        
        .sidebar-menu {
            padding: 1.5rem 0;
        }
        
        .sidebar-menu .nav-item {
            margin-bottom: 0.5rem;
        }
        
        .sidebar-menu .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.85rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            text-decoration: none;
        }
        
        .sidebar-menu .nav-link:hover,
        .sidebar-menu .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--accent-green);
        }
        
        .sidebar-menu .nav-link i {
            width: 20px;
            font-size: 1.1rem;
        }
        
        /* Top Header */
        .student-topbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 999;
            display: none;
            padding: 1rem;
        }
        
        .topbar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sidebar-toggle {
            background: none;
            border: none;
            color: var(--dark-blue);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .sidebar-toggle:hover {
            transform: scale(1.1);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-blue);
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        /* Mobile Styles */
        @media (max-width: 992px) {
            .student-topbar {
                display: block;
            }
            
            .student-sidebar {
                transform: translateX(-100%);
                box-shadow: none;
            }
            
            .student-sidebar.active {
                transform: translateX(0);
                box-shadow: 5px 0 15px rgba(0,0,0,0.1);
            }
            
            .student-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }
        }
        
        /* Desktop Styles */
        @media (min-width: 993px) {
            .student-content {
                margin-left: 280px;
                width: calc(100% - 280px);
                padding: 2rem;
                transition: all 0.3s ease;
            }
            
            .student-sidebar.active ~ .student-content {
                margin-left: 0;
                width: 100%;
            }
        }
        
        /* Overlay for mobile menu */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            transition: all 0.3s ease;
        }
        
        .sidebar-overlay.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Sidebar Close Button */
        .sidebar-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
            display: none;
        }
        
        .sidebar-close:hover {
            background: rgba(255,255,255,0.2);
            transform: rotate(90deg);
        }
        
        .sidebar-close i {
            font-size: 1.2rem;
        }
        
        /* Mobile specific styles */
        @media (max-width: 992px) {
            .sidebar-close {
                display: flex;
            }
        }
        
        /* Scroll to top button */
        .btn-scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-scroll-top:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <!-- Mobile Top Bar -->
    <header class="student-topbar">
        <div class="topbar-content">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Menu">
                <i class="bi bi-list" id="menuToggleIcon"></i>
            </button>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo $initials; ?>
                </div>
                <div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                    <small class="text-muted">Student</small>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <aside class="student-sidebar" id="sidebar">
        <div class="sidebar-header">
            <!-- Close Button -->
            <button class="sidebar-close" id="sidebarClose" aria-label="Close Menu">
                <i class="bi bi-x"></i>
            </button>
            <div class="logo">
                <i class="bi bi-passport-fill"></i>
            </div>
            <h3>ISU Portal</h3>
            <p>Student Dashboard</p>
            <div class="mt-2">
                <small class="opacity-75">ID: <?php echo $student_id; ?></small>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                        <i class="bi bi-person-circle"></i>
                        <span>My Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="visa.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'visa.php' ? 'active' : ''; ?>">
                        <i class="bi bi-passport"></i>
                        <span>My Student Pass</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="renewal.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'renewal.php' ? 'active' : ''; ?>">
                        <i class="bi bi-arrow-clockwise"></i>
                        <span>Pass Renewal</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="insurance.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'insurance.php' ? 'active' : ''; ?>">
                        <i class="bi bi-shield-check"></i>
                        <span>Insurance & Claims</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="documents.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'documents.php' ? 'active' : ''; ?>">
                        <i class="bi bi-folder"></i>
                        <span>My Documents</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="exit.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'exit.php' ? 'active' : ''; ?>">
                        <i class="bi bi-door-open"></i>
                        <span>Exit & Clearance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="notifications.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
                        <i class="bi bi-bell"></i>
                        <span>Notifications</span>
                        <?php
                        // You can add a notification count here if needed
                        // Example: <span class="badge bg-danger ms-auto">3</span>
                        ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                        <i class="bi bi-gear"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a href="../logout.php" class="nav-link">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>
    
    <!-- Main Content Area -->
    <main class="student-content" id="mainContent">
        