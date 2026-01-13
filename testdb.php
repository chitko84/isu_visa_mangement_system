<?php
require_once 'includes/db.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Database</title>
</head>
<body>
    <h1>Testing Database Connection</h1>
    <?php test_database_connection(); ?>
    <p>If you see "Database connected successfully", it's working!</p>
    <a href="login.php">Go to Login</a>
</body>
</html>
