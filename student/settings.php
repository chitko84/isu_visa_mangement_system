<?php
// student/settings.php

$page_title = "Account Settings - ISU Student Portal";
require_once __DIR__ . "/header.php"; // session + db ($conn) + $student_id

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$success = "";
$error   = "";

// ------------------------------------------------------------
// Fetch current student info
// ------------------------------------------------------------
$student = null;
$stmt = $conn->prepare("
    SELECT student_id, first_name, last_name, email, phone, status, student_type
    FROM student
    WHERE student_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    // If student not found, force logout (consistent behavior)
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// ------------------------------------------------------------
// Handle POST actions
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        require_csrf();

        // -----------------------------
        // Update contact info (email, phone)
        // -----------------------------
        if ($action === 'update_contact') {
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if ($email === '') {
                throw new Exception("Email is required.");
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format.");
            }

            // Ensure email is unique (student.email has UNIQUE index)
            $stmt = $conn->prepare("SELECT student_id FROM student WHERE email = ? AND student_id <> ? LIMIT 1");
            $stmt->bind_param("si", $email, $student_id);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exists) {
                throw new Exception("This email is already used by another student.");
            }

            $stmt = $conn->prepare("UPDATE student SET email = ?, phone = ? WHERE student_id = ? LIMIT 1");
            $stmt->bind_param("ssi", $email, $phone, $student_id);
            $stmt->execute();
            $stmt->close();

            $success = "Contact information updated successfully.";
            log_audit($conn, 'student_updated_settings', 'student', $student_id, 'Student updated contact information.');
        }

        // -----------------------------
        // Change password
        // student.password is varchar(255)
        // -----------------------------
        if ($action === 'change_password') {
            $current_password = (string)($_POST['current_password'] ?? '');
            $new_password     = (string)($_POST['new_password'] ?? '');
            $confirm_password = (string)($_POST['confirm_password'] ?? '');

            if ($current_password === '' || $new_password === '' || $confirm_password === '') {
                throw new Exception("Please fill in all password fields.");
            }
            if ($new_password !== $confirm_password) {
                throw new Exception("New password and confirm password do not match.");
            }
            if (strlen($new_password) < 8) {
                throw new Exception("New password must be at least 8 characters.");
            }

            // Re-fetch hashed password
            $stmt = $conn->prepare("SELECT password FROM student WHERE student_id = ? LIMIT 1");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $hashed = $row['password'] ?? null;

            // If password is NULL (your schema allows NULL), block change unless current is empty?
            // We'll handle safely: if no password stored, treat as invalid current password.
            if (!$hashed || !password_verify($current_password, $hashed)) {
                throw new Exception("Current password is incorrect.");
            }

            // Prevent setting same password
            if (password_verify($new_password, $hashed)) {
                throw new Exception("New password cannot be the same as current password.");
            }

            $new_hashed = password_hash($new_password, PASSWORD_BCRYPT);

            $stmt = $conn->prepare("UPDATE student SET password = ? WHERE student_id = ? LIMIT 1");
            $stmt->bind_param("si", $new_hashed, $student_id);
            $stmt->execute();
            $stmt->close();

            $success = "Password changed successfully.";
            log_audit($conn, 'student_changed_password', 'student', $student_id, 'Student changed password.');
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    // Refresh student info after update
    $stmt = $conn->prepare("
        SELECT student_id, first_name, last_name, email, phone, status, student_type
        FROM student
        WHERE student_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="mb-0">Account Settings</h2>
            <div class="text-muted">Update your contact info and password.</div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- Profile Info -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">My Account</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="small text-muted">Student ID</div>
                    <div class="fw-semibold"><?php echo h($student['student_id']); ?></div>
                </div>
                <div class="col-md-4">
                    <div class="small text-muted">Name</div>
                    <div class="fw-semibold"><?php echo h($student['first_name'] . ' ' . $student['last_name']); ?></div>
                </div>
                <div class="col-md-4">
                    <div class="small text-muted">Status</div>
                    <span class="badge bg-dark"><?php echo h($student['status']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Contact Info -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Update Contact Information</div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_contact">

                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        required
                        value="<?php echo h($student['email']); ?>"
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input
                        type="text"
                        name="phone"
                        class="form-control"
                        value="<?php echo h($student['phone'] ?? ''); ?>"
                        placeholder="e.g., +60123456789"
                    >
                </div>

                <div class="col-12">
                    <button class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password -->
    <div class="card mb-5">
        <div class="card-header fw-semibold">Change Password</div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="change_password">

                <div class="col-md-4">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" required minlength="8">
                    <div class="form-text">At least 8 characters.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="8">
                </div>

                <div class="col-12">
                    <button class="btn btn-success">Change Password</button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php require_once __DIR__ . "/footer.php"; ?>
