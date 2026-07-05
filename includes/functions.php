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
    if (!user_has_role($allowed_roles)) {
        redirect('../login.php?unauthorized=1');
    }
}

function normalize_role(string $role): string {
    $role = strtolower(trim($role));
    $aliases = [
        'super admin' => 'super_admin',
        'super-admin' => 'super_admin',
        'visa officer' => 'visa_officer',
        'insurance officer' => 'insurance_officer',
        'exit officer' => 'exit_officer',
    ];
    return $aliases[$role] ?? $role;
}

function user_has_role(array $allowed_roles): bool {
    $current = normalize_role(current_role());
    $allowed = array_map('normalize_role', $allowed_roles);

    if (in_array($current, $allowed, true)) {
        return true;
    }

    if (in_array($current, ['admin', 'super_admin'], true) && array_intersect($allowed, ['staff', 'admin', 'super_admin', 'visa_officer', 'insurance_officer', 'exit_officer'])) {
        return true;
    }

    if ($current === 'staff' && array_intersect($allowed, ['staff', 'visa_officer', 'insurance_officer', 'exit_officer'])) {
        return true;
    }

    return false;
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

function csrf_token(): string {
    secure_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . issu_h(csrf_token()) . '">';
}

function verify_csrf_token(?string $token = null): bool {
    secure_session_start();
    $token = $token ?? (string)($_POST['csrf_token'] ?? '');
    return $token !== '' && hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token);
}

function require_csrf(): void {
    if (!verify_csrf_token()) {
        throw new RuntimeException('Your form session expired. Please refresh the page and try again.');
    }
}

function flash_set(string $type, string $message): void {
    secure_session_start();
    $_SESSION['flash_messages'][] = [
        'type' => in_array($type, ['success', 'danger', 'warning', 'info'], true) ? $type : 'info',
        'message' => $message,
    ];
}

function flash_get(): array {
    secure_session_start();
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return is_array($messages) ? $messages : [];
}

function flash_render(): void {
    foreach (flash_get() as $flash) {
        $type = $flash['type'] ?? 'info';
        $message = $flash['message'] ?? '';
        echo '<div class="alert alert-' . issu_h($type) . ' alert-dismissible fade show" role="alert">';
        echo issu_h($message);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

function db_has_column(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $count > 0;
}

function create_notification(mysqli $conn, array $data): bool {
    $title = trim((string)($data['title'] ?? 'Notification'));
    $message = trim((string)($data['message'] ?? ''));
    $student_id = isset($data['student_id']) ? (int)$data['student_id'] : null;
    $staff_id = isset($data['staff_id']) ? (int)$data['staff_id'] : null;
    $type = trim((string)($data['type'] ?? 'general'));

    try {
        $hasStaff = db_has_column($conn, 'notifications', 'staff_id');
        $hasType = db_has_column($conn, 'notifications', 'notification_type');

        if ($hasStaff && $hasType) {
            $stmt = $conn->prepare("INSERT INTO notifications (student_id, staff_id, notification_type, title, message, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            $stmt->bind_param("iisss", $student_id, $staff_id, $type, $title, $message);
        } elseif ($hasStaff) {
            $stmt = $conn->prepare("INSERT INTO notifications (student_id, staff_id, title, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $stmt->bind_param("iiss", $student_id, $staff_id, $title, $message);
        } else {
            if ($student_id === null || $student_id <= 0) {
                return false;
            }
            $stmt = $conn->prepare("INSERT INTO notifications (student_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
            $stmt->bind_param("iss", $student_id, $title, $message);
        }

        if (!$stmt) {
            return false;
        }
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    } catch (Throwable $e) {
        error_log("Notification insert failed: " . $e->getMessage());
        return false;
    }
}

function notify_staff(mysqli $conn, string $title, string $message, string $type = 'staff_alert'): void {
    try {
        $result = $conn->query("SELECT staff_id FROM staff WHERE status = 'Active'");
        if (!$result) {
            return;
        }
        while ($row = $result->fetch_assoc()) {
            create_notification($conn, [
                'staff_id' => (int)$row['staff_id'],
                'title' => $title,
                'message' => $message,
                'type' => $type,
            ]);
        }
        $result->free();
    } catch (Throwable $e) {
        error_log("Staff notification failed: " . $e->getMessage());
    }
}

function log_audit(mysqli $conn, string $action, string $entity_type = '', ?int $entity_id = null, string $details = ''): void {
    try {
        $actor_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $actor_role = (string)($_SESSION['role'] ?? 'guest');
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

        $stmt = $conn->prepare("
            INSERT INTO audit_logs (actor_id, actor_role, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param("isssisss", $actor_id, $actor_role, $action, $entity_type, $entity_id, $details, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

function send_email_notification(string $to, string $subject, string $html_message): bool {
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: ISU Visa Management System <noreply@example.com>\r\n";

    $enabled = getenv('ISU_EMAIL_ENABLED') === '1';
    if (!$enabled) {
        error_log("EMAIL DISABLED | TO: {$to} | SUBJECT: {$subject} | MESSAGE: " . strip_tags($html_message));
        return true;
    }

    try {
        return mail($to, $subject, $html_message, $headers);
    } catch (Throwable $e) {
        error_log("Email send failed: " . $e->getMessage());
        return false;
    }
}

function base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $scheme . '://' . $host . ($scriptDir === '' ? '' : $scriptDir);
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
