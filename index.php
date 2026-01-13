<?php
// Start session
session_start();

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    if ($_SESSION['role'] == 'student') {
        header("Location: student/dashboard.php");
    } else {
        header("Location: staff/dashboard.php");
    }
    exit();
} else {
    // Redirect to login
    header("Location: login.php");
    exit();
}
?>
