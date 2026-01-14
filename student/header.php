<?php
// student/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../includes/db.php';

$student_id = (int)$_SESSION['user_id'];

/**
 * Fetch student basic info + profile photo
 * NOTE: make sure your student table has `profile_photo` column (VARCHAR) as used in profile.php
 */
$student_query = "SELECT first_name, last_name, email, profile_photo FROM student WHERE student_id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$page_title = $page_title ?? 'Student Portal - ISU';

$initials = strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1));

/**
 * Notification count placeholder
 * Later you can replace this with real query from notification table
 */
$notification_count = 0;

// AIU Logo URL
$aiu_logo_url = "https://aiu.edu.my/wp-content/uploads/2022/11/AIULogo-512x521-01.jpg";

/**
 * Build profile photo URL
 * We store profile_photo like: "uploads/profile/xxx.png" (relative to /student/)
 */
$profilePhoto = $student['profile_photo'] ?? '';
if ($profilePhoto && preg_match('/^https?:\/\//i', $profilePhoto)) {
    $profilePhotoUrl = $profilePhoto;
} elseif ($profilePhoto) {
    // relative to /student/ folder
    $profilePhotoUrl = $profilePhoto;
} else {
    $profilePhotoUrl = ""; // empty => show initials fallback
}

// cache-bust (so new upload shows immediately)
$profilePhotoUrlWithVersion = $profilePhotoUrl ? ($profilePhotoUrl . '?v=' . time()) : '';
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

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            color: var(--text-gray);
            overflow-x: hidden;
        }

        .student-container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Sidebar */
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
            text-align: center;
        }

        .sidebar-header .logo {
            width: 72px;
            height: 72px;
            border-radius: 14px;
            background: rgba(255,255,255,0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            overflow: hidden;
        }

        .sidebar-header .logo img {
            width: 58px;
            height: 58px;
            object-fit: contain;
            border-radius: 10px;
            background: white;
            padding: 4px;
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

        .sidebar-menu { padding: 1.5rem 0; }

        .sidebar-menu .nav-item { margin-bottom: 0.5rem; }

        .sidebar-menu .nav-link {
            color: rgba(255,255,255,0.82);
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

        .sidebar-menu .nav-link i { width: 20px; font-size: 1.1rem; }

        /* Mobile Top Header */
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

        .sidebar-toggle:hover { transform: scale(1.1); }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* ✅ Avatar supports photo + fallback initials */
        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--light-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-blue);
            font-weight: 700;
            font-size: 1.05rem;
            overflow: hidden;
            flex-shrink: 0;
            border: 1px solid rgba(0,0,0,0.06);
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* ✅ Notification icon in topbar */
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .notif-btn {
            position: relative;
            border: none;
            background: transparent;
            color: var(--dark-blue);
            font-size: 1.35rem;
            padding: 0.35rem 0.5rem;
            cursor: pointer;
            line-height: 1;
        }

        .notif-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            background: #dc3545;
            color: #fff;
            border-radius: 999px;
            font-size: 0.7rem;
            padding: 0.1rem 0.35rem;
            min-width: 18px;
            text-align: center;
        }

        @media (min-width: 993px) {
            .student-content {
                margin-left: 280px;
                width: calc(100% - 280px);
                padding: 2rem;
                transition: all 0.3s ease;
            }
        }
        @media (max-width: 992px) {
            .student-topbar { display: block; }

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

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            transition: all 0.3s ease;
        }
        .sidebar-overlay.active { display: block; }

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
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
        }
        .sidebar-close:hover { background: rgba(255,255,255,0.2); transform: rotate(90deg); }
        @media (max-width: 992px) { .sidebar-close { display: flex; } }

        .badge-notif {
            font-size: 0.75rem;
            border-radius: 999px;
            padding: 0.25rem 0.45rem;
        }
    </style>
</head>
<body>

<header class="student-topbar">
    <div class="topbar-content">

        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Menu">
            <i class="bi bi-list" id="menuToggleIcon"></i>
        </button>

        <div class="topbar-actions">

            <!-- ✅ Notifications icon -->
            <a href="notifications.php" class="notif-btn" aria-label="Notifications" title="Notifications">
                <i class="bi bi-bell"></i>
                <?php if ($notification_count > 0): ?>
                    <span class="notif-badge"><?php echo (int)$notification_count; ?></span>
                <?php endif; ?>
            </a>

            <!-- ✅ User info (photo or initials) -->
            <div class="user-info">
                <div class="user-avatar">
                    <?php if ($profilePhotoUrlWithVersion): ?>
                        <img src="<?php echo htmlspecialchars($profilePhotoUrlWithVersion); ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                    <small class="text-muted">Student</small>
                </div>
            </div>

        </div>
    </div>
</header>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="student-sidebar" id="sidebar">
    <div class="sidebar-header">
        <button class="sidebar-close" id="sidebarClose" aria-label="Close Menu">
            <i class="bi bi-x"></i>
        </button>

        <!-- ✅ AIU LOGO IMAGE -->
        <div class="logo">
            <img src="<?php echo htmlspecialchars($aiu_logo_url); ?>" alt="AIU Logo">
        </div>

        <h3>ISU Portal</h3>
        <p>Student Dashboard</p>

        <!-- ✅ Show profile photo in sidebar too -->
        <div class="mt-3 d-flex flex-column align-items-center">
            <div class="user-avatar" style="width: 70px; height: 70px; font-size: 1.3rem;">
                <?php if ($profilePhotoUrlWithVersion): ?>
                    <img src="<?php echo htmlspecialchars($profilePhotoUrlWithVersion); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div class="mt-2 fw-semibold" style="color: rgba(255,255,255,0.92);">
                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
            </div>
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
                <a href="student.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'student.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-vcard"></i>
                    <span>Student</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="visa_renewal.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'visa_renewal.php' ? 'active' : ''; ?>">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Visa Renewal</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="insurance.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'insurance.php' ? 'active' : ''; ?>">
                    <i class="bi bi-shield-check"></i>
                    <span>Insurance</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="exit.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'exit.php' ? 'active' : ''; ?>">
                    <i class="bi bi-door-open"></i>
                    <span>Exit</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="notifications.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
                    <i class="bi bi-bell"></i>
                    <span>Notifications</span>

                    <?php if ($notification_count > 0): ?>
                        <span class="badge bg-danger ms-auto badge-notif"><?php echo (int)$notification_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-circle"></i>
                    <span>My Profile</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="documents.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'documents.php' ? 'active' : ''; ?>">
                    <i class="bi bi-folder"></i>
                    <span>My Documents</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </li>

            <li class="nav-item mt-4">
                <a href="logout.php" class="nav-link">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </li>

        </ul>
    </div>
</aside>

<main class="student-content" id="mainContent">
