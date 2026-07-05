<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "issu";

mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit("Database connection failed. Please check the database setup and try again.");
}

$conn->set_charset("utf8mb4");

function test_database_connection(): void
{
    echo '<div style="color: green; font-weight: bold;">Database connected successfully.</div>';
}
?>
