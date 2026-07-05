<?php
require_once __DIR__ . '/includes/functions.php';
secure_session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();

        $email = trim((string)($_POST['email'] ?? ''));
        $role = trim((string)($_POST['role'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['student', 'staff'], true)) {
            throw new RuntimeException('Enter a valid email and account type.');
        }

        $table = $role === 'student' ? 'student' : 'staff';
        $idCol = $role === 'student' ? 'student_id' : 'staff_id';

        $stmt = $conn->prepare("SELECT {$idCol} AS id, first_name, last_name, email FROM {$table} WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $success = 'If an active account exists for that email, password reset instructions have been prepared.';

        if ($user) {
            $plainToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $plainToken);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);
            $userId = (int)$user['id'];

            $stmt = $conn->prepare("
                INSERT INTO password_resets (user_type, user_id, email, token_hash, expires_at, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("sisss", $role, $userId, $email, $tokenHash, $expiresAt);
            $stmt->execute();
            $stmt->close();

            $resetUrl = base_url() . '/reset-password.php?token=' . urlencode($plainToken) . '&role=' . urlencode($role);
            $message = '<p>Hello ' . issu_h(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) . ',</p>'
                . '<p>Use this link to reset your ISU Visa Management System password. The link expires in 1 hour.</p>'
                . '<p><a href="' . issu_h($resetUrl) . '">' . issu_h($resetUrl) . '</a></p>';
            send_email_notification($email, 'ISU password reset request', $message);
            error_log("Password reset link for {$email}: {$resetUrl}");
            log_audit($conn, 'password_reset_requested', $role, $userId, 'Password reset token created.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ISU Visa Management System</title>
    <link href="bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 560px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h3 mb-2">Forgot Password</h1>
            <p class="text-muted">Enter your account email. If email sending is disabled locally, the reset link is written to the PHP error log for development.</p>

            <?php if ($error): ?><div class="alert alert-danger"><?php echo issu_h($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo issu_h($success); ?></div><?php endif; ?>

            <form method="post">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="role">Account Type</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="student">Student</option>
                        <option value="staff">Staff/Admin</option>
                    </select>
                </div>
                <button class="btn btn-primary w-100" type="submit">Send Reset Instructions</button>
                <a class="btn btn-link w-100 mt-2" href="login.php">Back to login</a>
            </form>
        </div>
    </div>
</main>
</body>
</html>
