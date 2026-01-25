<?php
// hash.php
// Simple password hash generator for testing

$password = "Staff@123";

echo "<h2>Password Hash Generator</h2>";
echo "<p><strong>Plain password:</strong> {$password}</p>";

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<p><strong>Generated hash:</strong></p>";
echo "<textarea cols='100' rows='3' readonly>{$hash}</textarea>";
