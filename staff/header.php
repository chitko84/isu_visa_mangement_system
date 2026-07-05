<?php
// staff/header.php

require_once __DIR__ . '/../includes/functions.php';
require_role(['staff', 'admin']);

$staff_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'staff';

$page_title = $page_title ?? 'Staff Portal - ISU';

// ------------------------------
// Fetch staff basic info
// ------------------------------
$staff = null;

// Try to load from staff table (recommended)
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'staff'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT first_name, last_name, email, profile_photo FROM staff WHERE staff_id = ? LIMIT 1");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $staff = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Staff fetch error: " . $e->getMessage());
}

if (!$staff) {
    $staff = [
        'first_name' => 'Staff',
        'last_name' => '',
        'email' => '',
        'profile_photo' => ''
    ];
}

$initials = strtoupper(substr($staff['first_name'] ?? 'S', 0, 1) . substr($staff['last_name'] ?? '', 0, 1));

// ------------------------------
// Notification count (staff)
// ------------------------------
$notification_count = 0;

try {
    // Option A: notifications_staff
    $t = $conn->query("SHOW TABLES LIKE 'notifications_staff'");
    if ($t && $t->num_rows > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications_staff WHERE staff_id = ? AND is_read = 0");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $notification_count = (int)($row['c'] ?? 0);
    } else {
        // Option B: fallback to notifications table if you use it for staff too (requires staff_id column)
        $t2 = $conn->query("SHOW TABLES LIKE 'notifications'");
        if ($t2 && $t2->num_rows > 0) {
            $colCheck = $conn->query("SHOW COLUMNS FROM notifications LIKE 'staff_id'");
            if ($colCheck && $colCheck->num_rows > 0) {
                $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE staff_id = ? AND is_read = 0");
                $stmt->bind_param("i", $staff_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $notification_count = (int)($row['c'] ?? 0);
            }
        }
    }
} catch (Exception $e) {
    error_log("Staff notification count error: " . $e->getMessage());
}

// AIU Logo URL
$aiu_logo_url = "https://aiu.edu.my/wp-content/uploads/2022/11/AIULogo-512x521-01.jpg";

// Profile photo url build (fallback to default image)
$profilePhoto = $staff['profile_photo'] ?? '';

if ($profilePhoto && preg_match('/^https?:\/\//i', $profilePhoto)) {
    $profilePhotoUrl = $profilePhoto;
} elseif ($profilePhoto) {
    $profilePhotoUrl = $profilePhoto; // e.g. uploads/profile/staff_4_20260116.png
} else {
    // fallback image inside /staff/uploads/
    $profilePhotoUrl = "uploads/default_image.png";
}

$profilePhotoUrlWithVersion = $profilePhotoUrl . '?v=' . time();

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Bootstrap 5 CSS (local) -->
    <link href="../bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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

        /* Sidebar */
        .staff-sidebar {
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

        .sidebar-header h3 { font-size: 1.1rem; font-weight: 600; margin-bottom: 0.25rem; }
        .sidebar-header p  { font-size: 0.85rem; opacity: 0.8; margin: 0; }

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

        /* Mobile Topbar */
        .staff-topbar {
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
            text-decoration: none;
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
            .staff-content {
                margin-left: 280px;
                width: calc(100% - 280px);
                padding: 2rem;
                transition: all 0.3s ease;
            }
        }

        @media (max-width: 992px) {
            .staff-topbar { display: block; }

            .staff-sidebar {
                transform: translateX(-100%);
                box-shadow: none;
            }

            .staff-sidebar.active {
                transform: translateX(0);
                box-shadow: 5px 0 15px rgba(0,0,0,0.1);
            }

            .staff-content {
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

<header class="staff-topbar">
    <div class="topbar-content">

        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Menu">
            <i class="bi bi-list" id="menuToggleIcon"></i>
        </button>

        <div class="topbar-actions">

            <a href="notifications.php" class="notif-btn" aria-label="Notifications" title="Notifications">
                <i class="bi bi-bell"></i>
                <?php if ($notification_count > 0): ?>
                    <span class="notif-badge"><?php echo (int)$notification_count; ?></span>
                <?php endif; ?>
            </a>

            <div class="user-info">
                <div class="user-avatar">
                    <img src="<?php echo htmlspecialchars($profilePhotoUrlWithVersion); ?>" alt="Profile">
                </div>
                <div>
                    <div class="fw-semibold">
                        <?php echo htmlspecialchars(trim(($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? ''))); ?>
                    </div>
                    <small class="text-muted"><?php echo htmlspecialchars(strtoupper($role)); ?></small>
                </div>
            </div>

        </div>
    </div>
</header>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="staff-sidebar" id="sidebar">
    <div class="sidebar-header">
        <button class="sidebar-close" id="sidebarClose" aria-label="Close Menu">
            <i class="bi bi-x"></i>
        </button>

        <div class="logo">
            <img src="<?php echo htmlspecialchars($aiu_logo_url); ?>" alt="AIU Logo">
        </div>

        <h3>ISU Portal</h3>
        <p>Staff Dashboard</p>

        <div class="mt-3 d-flex flex-column align-items-center">
            <div class="user-avatar" style="width: 70px; height: 70px; font-size: 1.3rem;">
                <img src="<?php echo htmlspecialchars($profilePhotoUrlWithVersion); ?>" alt="Profile">
            </div>

            <div class="mt-2 fw-semibold" style="color: rgba(255,255,255,0.92);">
                <?php echo htmlspecialchars(trim(($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? ''))); ?>
            </div>
            <small class="opacity-75">
                ID: <?php echo (int)$staff_id; ?> | <?php echo htmlspecialchars(strtoupper($role)); ?>
            </small>
        </div>
    </div>

    <div class="sidebar-menu">
        <ul class="nav flex-column">

            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="students.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'students.php' ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i>
                    <span>Students</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="visa_management.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'visa_management.php' ? 'active' : ''; ?>">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Visa Management</span>
                </a>
            </li>


            <li class="nav-item">
                <a href="insurance_management.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'insurance_management.php' ? 'active' : ''; ?>">
                    <i class="bi bi-shield-check"></i>
                    <span>Insurance</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="exit_management.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'exit_management.php' ? 'active' : ''; ?>">
                    <i class="bi bi-door-open"></i>
                    <span>Exit Management</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Reports</span>
                </a>
            </li>

            <?php if (user_has_role(['admin', 'super_admin'])): ?>
            <li class="nav-item">
                <a href="audit_logs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'audit_logs.php' ? 'active' : ''; ?>">
                    <i class="bi bi-shield-lock"></i>
                    <span>Audit Logs</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-item">
                <a href="notifications.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : ''; ?>">
                    <i class="bi bi-bell"></i>
                    <span>Notifications</span>
                    <?php if ($notification_count > 0): ?>
                        <span class="badge bg-danger ms-auto badge-notif"><?php echo (int)$notification_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
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

<main class="staff-content" id="mainContent">
