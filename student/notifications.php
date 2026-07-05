<?php
// student/notifications.php

$page_title = "Notifications - ISU Student Portal";
require_once __DIR__ . "/header.php"; // session + db ($conn) + $student_id

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function clearStoredResults(mysqli $conn): void {
    while ($conn->more_results()) {
        $conn->next_result();
        if ($res = $conn->store_result()) {
            $res->free();
        }
    }
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$success = "";
$error   = "";

// ------------------------------------------------------------
// Fetch student basic info (for display)
// ------------------------------------------------------------
$student = null;
$stmt = $conn->prepare("SELECT student_id, first_name, last_name, email, phone, status FROM student WHERE student_id = ? LIMIT 1");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ------------------------------------------------------------
// Handle actions (Mark as read)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        require_csrf();

        // Mark ONE notification as read
        if ($action === 'mark_one_read') {
            $notification_id = (int)($_POST['notification_id'] ?? 0);
            if ($notification_id <= 0) {
                throw new Exception("Invalid notification.");
            }

            // Ensure student can only update their own notifications
            $stmt = $conn->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE notification_id = ?
                  AND student_id = ?
            ");
            $stmt->bind_param("ii", $notification_id, $student_id);
            $stmt->execute();
            $stmt->close();

            $success = "Notification marked as read.";
        }

        // Mark ALL as read for this student
        if ($action === 'mark_all_read') {
            $stmt = $conn->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE student_id = ?
                  AND COALESCE(is_read,0) = 0
            ");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $stmt->close();

            $success = "All notifications marked as read.";
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
        clearStoredResults($conn);
    }
}

// ------------------------------------------------------------
// Fetch notifications from NEW table
// columns: notification_id, student_id, title, message, is_read, created_at
// ------------------------------------------------------------
$notifications = [];
$unreadCount = 0;

try {
    // unread count
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM notifications
        WHERE student_id = ?
          AND COALESCE(is_read,0) = 0
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $unreadCount = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    // list (latest first)
    $stmt = $conn->prepare("
        SELECT notification_id, student_id, title, message, COALESCE(is_read,0) AS is_read, created_at
        FROM notifications
        WHERE student_id = ?
        ORDER BY created_at DESC, notification_id DESC
        LIMIT 200
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Throwable $e) {
    $error = $error ?: $e->getMessage();
}
?>

<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="mb-0">Notifications</h2>
            <div class="text-muted">View your notifications and mark them as read.</div>
        </div>

        <div class="d-flex gap-2">
            <?php if ($unreadCount > 0): ?>
                <form method="post" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="mark_all_read">
                    <button class="btn btn-outline-primary btn-sm">
                        Mark all as read (<?php echo (int)$unreadCount; ?>)
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- Student Summary -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">My Profile Summary</div>
        <div class="card-body">
            <?php if ($student): ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="small text-muted">Student</div>
                        <div class="fw-semibold"><?php echo h($student['first_name'] . ' ' . $student['last_name']); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Email</div>
                        <div class="fw-semibold"><?php echo h($student['email']); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Phone</div>
                        <div class="fw-semibold"><?php echo h($student['phone'] ?? '-'); ?></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-muted">Student record not found.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="card mb-5">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <span>My Notifications</span>
            <span class="badge bg-<?php echo ($unreadCount > 0) ? 'danger' : 'secondary'; ?>">
                <?php echo (int)$unreadCount; ?> unread
            </span>
        </div>

        <div class="card-body">
            <?php if (!$notifications): ?>
                <div class="text-muted">No notifications yet.</div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($notifications as $n): ?>
                        <?php
                            $isRead = ((int)($n['is_read'] ?? 0) === 1);
                            $title = $n['title'] ?: 'Notification';
                        ?>
                        <div class="list-group-item <?php echo $isRead ? '' : 'list-group-item-warning'; ?>">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="fw-semibold"><?php echo h($title); ?></div>
                                        <?php if (!$isRead): ?>
                                            <span class="badge bg-danger">New</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Read</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($n['message'])): ?>
                                        <div class="mt-1"><?php echo nl2br(h($n['message'])); ?></div>
                                    <?php endif; ?>

                                    <div class="small text-muted mt-2">
                                        <?php echo h($n['created_at']); ?>
                                    </div>
                                </div>

                                <div class="text-nowrap">
                                    <?php if (!$isRead): ?>
                                        <form method="post" class="d-inline">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="mark_one_read">
                                            <input type="hidden" name="notification_id" value="<?php echo (int)$n['notification_id']; ?>">
                                            <button class="btn btn-sm btn-outline-dark">
                                                Mark as read
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-text mt-3">
                    Tip: Marking notifications as read will remove the red count badge in the header.
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . "/footer.php"; ?>
