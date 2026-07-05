<?php
require_once __DIR__ . '/../includes/functions.php';
destroy_session();

// Redirect to staff login page
// Use header if possible, otherwise JS fallback
$loginPage = "../login.php?logout=true";

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
