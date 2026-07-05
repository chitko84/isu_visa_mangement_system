<?php
require_once __DIR__ . '/includes/functions.php';
secure_session_start();

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$role = trim((string)($_GET['role'] ?? $_POST['role'] ?? ''));
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if ($token === '' || !in_array($role, ['student', 'staff'], true)) {
            throw new RuntimeException('Invalid reset request.');
        }

        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if (strlen($password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters.');
        }
        if ($password !== $confirm) {
            throw new RuntimeException('Passwords do not match.');
        }

        $tokenHash = hash('sha256', $token);
        $stmt = $conn->prepare("
            SELECT reset_id, user_type, user_id, email
            FROM password_resets
            WHERE token_hash = ?
              AND user_type = ?
              AND used_at IS NULL
              AND expires_at > NOW()
            ORDER BY reset_id DESC
            LIMIT 1
        ");
        $stmt->bind_param("ss", $tokenHash, $role);
        $stmt->execute();
        $reset = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$reset) {
            throw new RuntimeException('This reset link is invalid or expired.');
        }

        $table = $role === 'student' ? 'student' : 'staff';
        $idCol = $role === 'student' ? 'student_id' : 'staff_id';
        $userId = (int)$reset['user_id'];
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $conn->begin_transaction();
        $stmt = $conn->prepare("UPDATE {$table} SET password = ? WHERE {$idCol} = ?");
        $stmt->bind_param("si", $hash, $userId);
        $stmt->execute();
        $stmt->close();

        $resetId = (int)$reset['reset_id'];
        $stmt = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE reset_id = ?");
        $stmt->bind_param("i", $resetId);
        $stmt->execute();
        $stmt->close();
        $conn->commit();

        log_audit($conn, 'password_reset_completed', $role, $userId, 'Password reset completed.');
        $success = 'Your password has been reset. You can now log in.';
    } catch (Throwable $e) {
        if ($conn->errno) {
            @$conn->rollback();
        }
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ISU Visa Management System</title>
    <link href="bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 560px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h3 mb-2">Reset Password</h1>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo issu_h($error); ?></div><?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo issu_h($success); ?></div>
                <a class="btn btn-primary w-100" href="login.php">Go to Login</a>
            <?php else: ?>
                <form method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="token" value="<?php echo issu_h($token); ?>">
                    <input type="hidden" name="role" value="<?php echo issu_h($role); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="password">New Password</label>
                        <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
