<?php
// staff/notifications.php
// Updated to match your notifications table:
// notifications(notification_id PK, student_id, title, message, is_read, created_at)

$page_title = "Notifications Log - ISU Staff Portal";
require_once __DIR__ . "/header.php"; // provides $conn, $staff_id

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function fmtDateTime($d): string {
    if (!$d) return "-";
    $t = strtotime((string)$d);
    return $t ? date("d M Y, h:i A", $t) : (string)$d;
}

// ------------------------------------------------------------
// Filters
// ------------------------------------------------------------
$q        = trim($_GET["q"] ?? "");
$student  = trim($_GET["student"] ?? "");     // student_id
$dateFrom = trim($_GET["from"] ?? "");        // YYYY-MM-DD
$dateTo   = trim($_GET["to"] ?? "");          // YYYY-MM-DD
$read     = trim($_GET["read"] ?? "");        // "" | "0" | "1"

$page  = max(1, (int)($_GET["page"] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$rows = [];
$total = 0;
$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $action = trim($_POST['action'] ?? '');
        if ($action === 'mark_staff_all_read' && db_has_column($conn, 'notifications', 'staff_id')) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE staff_id = ? AND COALESCE(is_read,0) = 0");
            $stmt->bind_param("i", $staff_id);
            $stmt->execute();
            $stmt->close();
            $success = "Your notifications were marked as read.";
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// ------------------------------------------------------------
// Build query (NO procedures - simple select)
// Tables used: notifications, student
//
// Your notifications columns:
// - notification_id (PK)
// - student_id (FK)
// - title
// - message
// - is_read (tinyint, default 0, nullable)
// - created_at (timestamp, default current_timestamp())
// ------------------------------------------------------------
try {
    $where  = [];
    $types  = "";
    $params = [];

    if ($q !== "") {
        $where[] = "(n.title LIKE ? OR n.message LIKE ?)";
        $types  .= "ss";
        $like = "%" . $q . "%";
        $params[] = $like;
        $params[] = $like;
    }

    if ($student !== "" && ctype_digit($student)) {
        $where[] = "n.student_id = ?";
        $types  .= "i";
        $params[] = (int)$student;
    }

    if ($dateFrom !== "") {
        $where[] = "DATE(n.created_at) >= ?";
        $types  .= "s";
        $params[] = $dateFrom;
    }

    if ($dateTo !== "") {
        $where[] = "DATE(n.created_at) <= ?";
        $types  .= "s";
        $params[] = $dateTo;
    }

    // is_read filter
    if ($read === "0" || $read === "1") {
        // handle NULL as unread (0)
        if ($read === "0") {
            $where[] = "(n.is_read = 0 OR n.is_read IS NULL)";
        } else {
            $where[] = "n.is_read = 1";
        }
    }

    $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

    // Count for pagination
    $sqlCount = "
        SELECT COUNT(*) AS c
        FROM notifications n
        LEFT JOIN student s ON s.student_id = n.student_id
        $whereSql
    ";
    $stmt = $conn->prepare($sqlCount);
    if ($types !== "") {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $totalRow = $stmt->get_result()->fetch_assoc();
    $total = (int)($totalRow["c"] ?? 0);
    $stmt->close();

    // Data page (REMOVED created_by_staff_id since your table doesn't have it)
    $sql = "
        SELECT
            n.notification_id,
            n.student_id,
            n.title,
            n.message,
            n.is_read,
            n.created_at,
            s.first_name,
            s.last_name,
            s.email
        FROM notifications n
        LEFT JOIN student s ON s.student_id = n.student_id
        $whereSql
        ORDER BY n.created_at DESC, n.notification_id DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);

    // Bind pagination too
    if ($types === "") {
        $stmt->bind_param("ii", $limit, $offset);
    } else {
        $types2  = $types . "ii";
        $params2 = array_merge($params, [$limit, $offset]);
        $stmt->bind_param($types2, ...$params2);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
    }
    $stmt->close();

} catch (Throwable $e) {
    $error = "Failed to load notifications: " . $e->getMessage();
}

$totalPages = max(1, (int)ceil($total / $limit));

function buildUrl(array $overrides = []): string {
    $q = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($q[$k]);
        else $q[$k] = $v;
    }
    return "notifications.php?" . http_build_query($q);
}
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">Notifications Log</h3>
            <div class="text-muted">View and search notifications sent to students</div>
        </div>
        <a href="send_notification.php" class="btn btn-primary">
            <i class="bi bi-send"></i> Send New Notification
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>

    <?php if (db_has_column($conn, 'notifications', 'staff_id')): ?>
        <form method="post" class="mb-3">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="mark_staff_all_read">
            <button class="btn btn-outline-primary btn-sm">Mark my staff notifications as read</button>
        </form>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white">
            <strong>Filters</strong>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search (title/message)</label>
                    <input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="e.g. visa, renewal, reminder...">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Student ID</label>
                    <input class="form-control" name="student" value="<?php echo h($student); ?>" placeholder="e.g. 1001">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Read Status</label>
                    <select class="form-select" name="read">
                        <option value=""  <?php echo $read === ""  ? "selected" : ""; ?>>All</option>
                        <option value="0" <?php echo $read === "0" ? "selected" : ""; ?>>Unread</option>
                        <option value="1" <?php echo $read === "1" ? "selected" : ""; ?>>Read</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" class="form-control" name="from" value="<?php echo h($dateFrom); ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" class="form-control" name="to" value="<?php echo h($dateTo); ?>">
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-outline-primary">
                        <i class="bi bi-search"></i> Apply
                    </button>
                    <a class="btn btn-outline-secondary" href="notifications.php">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Sent Notifications</strong>
            <span class="text-muted small"><?php echo (int)$total; ?> total</span>
        </div>

        <div class="card-body p-0">
            <?php if (!$rows): ?>
                <div class="p-4 text-muted">No notifications found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width:90px;">#</th>
                            <th style="min-width:220px;">Student</th>
                            <th style="min-width:240px;">Email</th>
                            <th style="min-width:220px;">Title</th>
                            <th>Message</th>
                            <th style="min-width:120px;">Read</th>
                            <th style="min-width:170px;">Sent At</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $sid   = $r["student_id"] ?? "-";
                            $name  = trim(($r["first_name"] ?? "") . " " . ($r["last_name"] ?? ""));
                            $email = $r["email"] ?? "-";
                            $title = $r["title"] ?? "";
                            $msg   = $r["message"] ?? "";
                            $sentAt = $r["created_at"] ?? null;

                            $isReadVal = $r["is_read"];
                            $isRead = ((string)$isReadVal === "1");
                            ?>
                            <tr>
                                <td class="text-muted"><?php echo h($r["notification_id"] ?? ""); ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo h($name ?: ("Student " . $sid)); ?></div>
                                    <div class="text-muted small">ID: <?php echo h($sid); ?></div>
                                </td>
                                <td><?php echo h($email); ?></td>
                                <td class="fw-semibold"><?php echo h($title); ?></td>
                                <td>
                                    <div style="max-width:520px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                        <?php echo h($msg); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($isRead): ?>
                                        <span class="badge bg-success">Read</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unread</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h(fmtDateTime($sentAt)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-3 border-top bg-white d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?>
                    </div>

                    <nav>
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $page <= 1 ? "disabled" : ""; ?>">
                                <a class="page-link" href="<?php echo h(buildUrl(["page" => max(1, $page - 1)])); ?>">Prev</a>
                            </li>

                            <?php
                            $start = max(1, $page - 2);
                            $end   = min($totalPages, $page + 2);
                            for ($p = $start; $p <= $end; $p++):
                            ?>
                                <li class="page-item <?php echo $p === $page ? "active" : ""; ?>">
                                    <a class="page-link" href="<?php echo h(buildUrl(["page" => $p])); ?>"><?php echo (int)$p; ?></a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?php echo $page >= $totalPages ? "disabled" : ""; ?>">
                                <a class="page-link" href="<?php echo h(buildUrl(["page" => min($totalPages, $page + 1)])); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . "/footer.php"; ?>
