<?php
$page_title = "Audit Logs - ISU Staff Portal";
require_once __DIR__ . "/header.php";

if (!user_has_role(['admin', 'super_admin'])) {
    echo '<div class="alert alert-danger">Only admin users can view audit logs.</div>';
    require_once __DIR__ . "/footer.php";
    exit;
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;
$rows = [];
$total = 0;
$error = '';

try {
    $res = $conn->query("SELECT COUNT(*) AS c FROM audit_logs");
    if ($res) {
        $total = (int)($res->fetch_assoc()['c'] ?? 0);
    }

    $stmt = $conn->prepare("SELECT * FROM audit_logs ORDER BY created_at DESC, log_id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$totalPages = max(1, (int)ceil($total / $limit));
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">Audit Logs</h3>
            <div class="text-muted">Review important staff/admin and security events.</div>
        </div>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <?php if (!$rows): ?>
                <div class="p-4 text-muted">No audit logs found. Import database/security_updates.sql if the table is missing.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Actor</th>
                                <th>Action</th>
                                <th>Entity</th>
                                <th>Details</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?php echo h($r['created_at'] ?? '-'); ?></td>
                                    <td><?php echo h(($r['actor_role'] ?? '-') . ' #' . ($r['actor_id'] ?? '-')); ?></td>
                                    <td class="fw-semibold"><?php echo h($r['action'] ?? '-'); ?></td>
                                    <td><?php echo h(($r['entity_type'] ?? '-') . ' #' . ($r['entity_id'] ?? '-')); ?></td>
                                    <td><?php echo h($r['details'] ?? ''); ?></td>
                                    <td><?php echo h($r['ip_address'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-top d-flex justify-content-between">
                    <span class="text-muted small">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
                    <div>
                        <a class="btn btn-sm btn-outline-secondary <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="?page=<?php echo max(1, $page - 1); ?>">Prev</a>
                        <a class="btn btn-sm btn-outline-secondary <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="?page=<?php echo min($totalPages, $page + 1); ?>">Next</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . "/footer.php"; ?>
