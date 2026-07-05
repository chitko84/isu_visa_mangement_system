<?php
$db_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'issu',
    'port' => 3306,
];

$local_config = __DIR__ . '/db.local.php';
if (is_file($local_config)) {
    $local = require $local_config;
    if (is_array($local)) {
        $db_config = array_merge($db_config, $local);
    }
}

$servername = $db_config['host'];
$username = $db_config['username'];
$password = $db_config['password'];
$dbname = $db_config['database'];
$port = (int)($db_config['port'] ?? 3306);

mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli($servername, $username, $password, $dbname, $port);

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
