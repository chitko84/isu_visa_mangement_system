<?php
require_once __DIR__ . '/includes/functions.php';

$hash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $password = (string)($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            $error = 'Enter a password with at least 8 characters.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
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
    <title>Password Hash Helper</title>
    <link href="bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 760px;">
    <h1 class="h3 mb-3">Password Hash Helper</h1>
    <p class="text-muted">Local development helper for creating password hashes. Do not commit real passwords.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo issu_h($error); ?></div>
    <?php endif; ?>

    <form method="post" class="card card-body mb-3">
        <?php echo csrf_field(); ?>
        <label for="password" class="form-label">Password to hash</label>
        <input type="password" id="password" name="password" class="form-control" minlength="8" required>
        <button class="btn btn-primary mt-3" type="submit">Generate Hash</button>
    </form>

    <?php if ($hash): ?>
        <label for="hash" class="form-label">Generated hash</label>
        <textarea id="hash" class="form-control" rows="3" readonly><?php echo issu_h($hash); ?></textarea>
    <?php endif; ?>
</main>
</body>
</html>
