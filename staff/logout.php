<?php
// staff/logout.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Remove session cookie (important for full logout)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Redirect to staff login page
// Use header if possible, otherwise JS fallback
$loginPage = "../login.php";

if (!headers_sent()) {
    header("Location: " . $loginPage);
    exit();
}
?>
<script>
    window.location.href = "<?php echo $loginPage; ?>";
</script>
<noscript>
    <meta http-equiv="refresh" content="0;url=<?php echo $loginPage; ?>">
</noscript>
