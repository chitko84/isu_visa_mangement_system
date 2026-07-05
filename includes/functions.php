<?php
// Common helpers for ISSU pages.

require_once __DIR__ . '/db.php';

function secure_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function issu_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sanitize($value): string {
    return trim((string)$value);
}

function redirect(string $url): void {
    header("Location: " . $url);
    exit();
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

function current_role(): string {
    return (string)($_SESSION['role'] ?? '');
}

function require_login(): void {
    secure_session_start();
    if (!is_logged_in()) {
        redirect('../login.php?session=expired');
    }
    check_session_timeout();
}

function require_role(array $allowed_roles): void {
    require_login();
    if (!in_array(current_role(), $allowed_roles, true)) {
        redirect('../login.php?unauthorized=1');
    }
}

function destroy_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        secure_session_start();
    }

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function clear_stored_results(mysqli $conn): void {
    while ($conn->more_results()) {
        $conn->next_result();
        if ($res = $conn->store_result()) {
            $res->free();
        }
    }
}

function validate_uploaded_file(array $file, array $allowed_extensions, int $max_bytes): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Please choose a valid file to upload.'];
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > $max_bytes) {
        return ['success' => false, 'message' => 'File size is not allowed.'];
    }

    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_extensions, true)) {
        return ['success' => false, 'message' => 'Invalid file type.'];
    }

    return ['success' => true, 'extension' => $extension];
}

/**
 * Display success message
 */
function show_success($message) {
    echo '<div class="alert alert-success">' . issu_h($message) . '</div>';
}

/**
 * Display error message
 */
function show_error($message) {
    echo '<div class="alert alert-danger">' . issu_h($message) . '</div>';
}

/**
 * Display info message
 */
function show_info($message) {
    echo '<div class="alert alert-info">' . issu_h($message) . '</div>';
}

/**
 * Display warning message
 */
function show_warning($message) {
    echo '<div class="alert alert-warning">' . issu_h($message) . '</div>';
}

/**
 * Check if form is submitted via POST
 */
function is_post_request() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Check if form is submitted via GET
 */
function is_get_request() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Get form value with sanitization
 */
function get_form_value($field_name, $default = '') {
    if (isset($_POST[$field_name])) {
        return sanitize($_POST[$field_name]);
    }
    return $default;
}

/**
 * Upload profile picture
 */
function upload_profile_picture($file, $user_id, $user_type = 'student') {
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large. Max 2MB'];
    }
    
    // Get file extension
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check file type
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF'];
    }
    
    // Create upload directory if not exists
    $base_dir = in_array($user_type, ['staff', 'student'], true) ? $user_type : 'student';
    $upload_dir = "../{$base_dir}/uploads/profile/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $new_filename = $user_id . '_' . time() . '.' . $file_ext;
    $upload_path = $upload_dir . $new_filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return [
            'success' => true,
            'message' => 'File uploaded successfully',
            'file_path' => $upload_path,
            'filename' => $new_filename
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to upload file'];
}

/**
 * Get profile picture path
 */
function get_profile_picture($user_id, $user_type = 'student') {
    $upload_dir = "uploads/profile_pics/$user_type/";
    
    // Check for common image extensions
    $extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    foreach ($extensions as $ext) {
        $filename = $user_id . '.' . $ext;
        $filepath = $upload_dir . $filename;
        
        if (file_exists($filepath)) {
            return $filepath;
        }
        
        // Also check for timestamped versions
        $pattern = $upload_dir . $user_id . '_*.' . $ext;
        $files = glob($pattern);
        if (!empty($files)) {
            return $files[0];
        }
    }
    
    // Return default avatar
    return "https://ui-avatars.com/api/?name=" . urlencode(get_current_user_name()) . "&background=random&color=fff&size=200";
}

/**
 * Generate random password
 */
function generate_random_password($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Send email notification (simplified)
 */
function send_email($to, $subject, $message) {
    $headers = "From: ISSU System <noreply@issu.edu>" . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // For demo - just log the email
    error_log("EMAIL TO: $to | SUBJECT: $subject | MESSAGE: $message");
    
    // In production, use: return mail($to, $subject, $message, $headers);
    return true;
}

/**
 * Calculate days between two dates
 */
function days_between($date1, $date2) {
    $datetime1 = new DateTime($date1);
    $datetime2 = new DateTime($date2);
    $interval = $datetime1->diff($datetime2);
    return $interval->days;
}

/**
 * Get visa expiry status
 */
function get_visa_expiry_status($expiry_date) {
    $days_left = days_between(date('Y-m-d'), $expiry_date);
    
    if ($days_left <= 0) {
        return ['status' => 'Expired', 'class' => 'danger', 'days' => $days_left];
    } elseif ($days_left <= 30) {
        return ['status' => 'Expiring Soon', 'class' => 'warning', 'days' => $days_left];
    } elseif ($days_left <= 90) {
        return ['status' => 'Expiring in 3 months', 'class' => 'info', 'days' => $days_left];
    } else {
        return ['status' => 'Valid', 'class' => 'success', 'days' => $days_left];
    }
}

/**
 * Check session timeout (30 minutes)
 */
function check_session_timeout() {
    $timeout = 1800; // 30 minutes in seconds
    
    if (isset($_SESSION['login_time'])) {
        $session_life = time() - $_SESSION['login_time'];
        if ($session_life > $timeout) {
            destroy_session();
            redirect('../login.php?session=expired');
        }
    }
}

?>
