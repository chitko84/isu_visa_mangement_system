<?php
require_once __DIR__ . '/includes/functions.php';
destroy_session();
header("Location: login.php?logout=true");
exit();
?>
