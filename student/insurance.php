<?php
// student/insurance.php

$page_title = "Insurance - ISU Student Portal";
require_once __DIR__ . "/header.php"; // session + db ($conn) + $student_id

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fmtDate($d): string {
    if (!$d) return '-';
    $t = strtotime((string)$d);
    return $t ? date('d M Y', $t) : (string)$d;
}

// IMPORTANT: date-only days left (avoid "expired" on same day)
function daysLeft($d): ?int {
    if (!$d) return null;
    $t = strtotime((string)$d);
    if (!$t) return null;

    $today = strtotime(date('Y-m-d'));     // today 00:00
    $end   = strtotime(date('Y-m-d', $t)); // end date 00:00

    return (int)(($end - $today) / 86400);
}

function badgeForDays(?int $days): array {
    if ($days === null) return ['secondary', 'Unknown'];
    if ($days < 0)      return ['danger', 'Expired'];
    if ($days <= 30)    return ['warning', $days . " days left"];
    return ['success', $days . " days left"];
}

// Safe redirect (header if possible; JS/meta fallback if output started)
function redirectTo(string $url): void {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    }
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . h($url) . '"></noscript>';
    exit();
}

// Clear any extra result sets after CALL to avoid "Commands out of sync"
function clearStoredResults(mysqli $conn): void {
    while ($conn->more_results() && $conn->next_result()) {
        $r = $conn->use_result();
        if ($r instanceof mysqli_result) $r->free();
    }
}

// Validate that policy belongs to this student (anti POST tampering)
function assertPolicyOwnership(mysqli $conn, int $student_id, int $policy_id): void {
    $stmt = $conn->prepare("SELECT 1 FROM insurance_policy WHERE policy_id=? AND student_id=? LIMIT 1");
    if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
    $stmt->bind_param("ii", $policy_id, $student_id);
    $stmt->execute();
    $ok = $stmt->get_result()->fetch_row();
    $stmt->close();
    if (!$ok) throw new RuntimeException("Invalid policy selected.");
}

// ------------------------------------------------------------
// Messages + data holders
// ------------------------------------------------------------
$success = "";
$error   = "";

$policyLatest = null;
$policies     = [];
$renewals     = [];
$claims       = [];
$providers    = [];

// ------------------------------------------------------------
// Handle student actions (POST) - PRG pattern
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    try {
        require_csrf();
        if ($action === 'submit_claim') {
            $policy_id = (int)($_POST['policy_id'] ?? 0);
            $amount    = (float)($_POST['claim_amount'] ?? 0);

            if ($policy_id <= 0) throw new RuntimeException("Invalid policy selected.");
            if ($amount <= 0)    throw new RuntimeException("Claim amount must be greater than 0.");

            assertPolicyOwnership($conn, (int)$student_id, $policy_id);

            // CALL sp_student_submit_insurance_claim(IN p_student_id, IN p_policy_id, IN p_claim_amount, OUT o_claim_id)
            $stmt = $conn->prepare("CALL sp_student_submit_insurance_claim(?, ?, ?, @o_claim_id)");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("iid", $student_id, $policy_id, $amount);
            if (!$stmt->execute()) throw new RuntimeException("Execute failed: " . $stmt->error);
            $stmt->close();

            clearStoredResults($conn);

            $res = $conn->query("SELECT @o_claim_id AS claim_id");
            $row = $res ? $res->fetch_assoc() : null;
            $newId = $row['claim_id'] ?? null;
            if ($newId === null || strtoupper((string)$newId) === 'NULL' || (string)$newId === '') $newId = null;

            $msg = "Claim submitted successfully" . ($newId ? " (Claim ID: " . (string)$newId . ")." : ".");
            notify_staff($conn, 'Insurance claim submitted', "Student {$student_id} submitted an insurance claim.", 'insurance_claim_submitted');
            log_audit($conn, 'student_submitted_insurance_claim', 'insurance_claim', $newId ? (int)$newId : null, 'Student submitted insurance claim.');
            redirectTo("insurance.php?msg=" . urlencode($msg));
        }

        if ($action === 'submit_renewal') {
            $policy_id    = (int)($_POST['policy_id'] ?? 0);
            $new_end_date = trim((string)($_POST['new_end_date'] ?? ''));
            $remarks      = trim((string)($_POST['remarks'] ?? ''));

            if ($policy_id <= 0) throw new RuntimeException("Invalid policy selected.");
            if ($new_end_date === '') throw new RuntimeException("Please select a new end date.");

            assertPolicyOwnership($conn, (int)$student_id, $policy_id);

            $ts = strtotime($new_end_date);
            if (!$ts) throw new RuntimeException("Invalid date format.");
            $new_end_date_sql = date('Y-m-d', $ts);

            // Extra validation: new end date must be later than current end date
            $stmtChk = $conn->prepare("SELECT end_date FROM insurance_policy WHERE policy_id=? AND student_id=? LIMIT 1");
            if (!$stmtChk) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmtChk->bind_param("ii", $policy_id, $student_id);
            $stmtChk->execute();
            $curRow = $stmtChk->get_result()->fetch_assoc();
            $stmtChk->close();
            $currentEnd = $curRow['end_date'] ?? null;
            if ($currentEnd && strtotime($new_end_date_sql) <= strtotime($currentEnd)) {
                throw new RuntimeException("New end date must be later than current end date.");
            }

            // CALL sp_student_submit_insurance_renewal_form(IN p_student_id, IN p_policy_id, IN p_new_end_date, IN p_remarks, OUT o_renewal_id)
            $stmt = $conn->prepare("CALL sp_student_submit_insurance_renewal_form(?, ?, ?, ?, @o_renewal_id)");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("iiss", $student_id, $policy_id, $new_end_date_sql, $remarks);
            if (!$stmt->execute()) throw new RuntimeException("Execute failed: " . $stmt->error);
            $stmt->close();

            clearStoredResults($conn);

            $res = $conn->query("SELECT @o_renewal_id AS renewal_id");
            $row = $res ? $res->fetch_assoc() : null;
            $newId = $row['renewal_id'] ?? null;
            if ($newId === null || strtoupper((string)$newId) === 'NULL' || (string)$newId === '') $newId = null;

            $msg = "Renewal request submitted successfully" . ($newId ? " (Renewal ID: " . (string)$newId . ")." : ".");
            $msg .= " Please wait for staff/admin review (Pending).";
            notify_staff($conn, 'Insurance renewal submitted', "Student {$student_id} submitted an insurance renewal request.", 'insurance_renewal_submitted');
            log_audit($conn, 'student_submitted_insurance_renewal', 'insurance_renewal_record', $newId ? (int)$newId : null, 'Student submitted insurance renewal.');
            redirectTo("insurance.php?msg=" . urlencode($msg));
        }

        throw new RuntimeException("Unknown action.");

    } catch (Throwable $e) {
        clearStoredResults($conn);
        redirectTo("insurance.php?error=" . urlencode($e->getMessage()));
    }
}

// ------------------------------------------------------------
// Messages from GET (after PRG redirect)
// ------------------------------------------------------------
$success = trim($_GET['msg'] ?? $success);
$error   = trim($_GET['error'] ?? $error);

// ------------------------------------------------------------
// Load providers (optional dropdown / display)
// ------------------------------------------------------------
try {
    $res = $conn->query("SELECT provider_id, provider_name, contact_info FROM insurance_provider ORDER BY provider_name ASC");
    if ($res) $providers = $res->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    // ignore
}

// ------------------------------------------------------------
// 1) Latest policy (insurance_policy + provider)
// ------------------------------------------------------------
try {
    $stmt = $conn->prepare("
        SELECT
            ip.policy_id, ip.student_id, ip.provider_id, ip.policy_number,
            ip.start_date, ip.end_date, ip.coverage_type, ip.status,
            p.provider_name, p.contact_info
        FROM insurance_policy ip
        LEFT JOIN insurance_provider p ON p.provider_id = ip.provider_id
        WHERE ip.student_id = ?
        ORDER BY ip.end_date DESC, ip.policy_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $policyLatest = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Throwable $e) {
    if (!$error) $error = "Insurance load error: " . $e->getMessage();
}

// 2) All policies for this student
try {
    $stmt = $conn->prepare("
        SELECT
            ip.policy_id, ip.provider_id, ip.policy_number, ip.start_date, ip.end_date, ip.coverage_type, ip.status,
            p.provider_name
        FROM insurance_policy ip
        LEFT JOIN insurance_provider p ON p.provider_id = ip.provider_id
        WHERE ip.student_id = ?
        ORDER BY ip.end_date DESC, ip.policy_id DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $policies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {
    // ignore
}

// 3) Renewal records (for this student via policy)
// If you have rr.status column, it will display. If not, it will show '-'.
try {
    $stmt = $conn->prepare("
        SELECT
            rr.renewal_id, rr.policy_id, rr.renewal_date, rr.new_end_date, rr.remarks,
            rr.status AS renewal_status,
            ip.policy_number,
            p.provider_name
        FROM insurance_renewal_record rr
        JOIN insurance_policy ip ON ip.policy_id = rr.policy_id
        LEFT JOIN insurance_provider p ON p.provider_id = ip.provider_id
        WHERE ip.student_id = ?
        ORDER BY rr.renewal_date DESC, rr.renewal_id DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $renewals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {
    // ignore
}

// 4) Claims (for this student via policy)
try {
    $stmt = $conn->prepare("
        SELECT
            c.claim_id, c.policy_id, c.claim_date, c.claim_amount, c.claim_status,
            ip.policy_number,
            p.provider_name
        FROM insurance_claim c
        JOIN insurance_policy ip ON ip.policy_id = c.policy_id
        LEFT JOIN insurance_provider p ON p.provider_id = ip.provider_id
        WHERE ip.student_id = ?
        ORDER BY c.claim_date DESC, c.claim_id DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {
    // ignore
}

// ------------------------------------------------------------
// Derived
// ------------------------------------------------------------
$endDate = $policyLatest['end_date'] ?? null;
$days    = daysLeft($endDate);
[$badge, $badgeText] = badgeForDays($days);

$hasPolicy = !empty($policyLatest);
?>

<style>
.page-title { font-weight: 800; }
.kv { display:flex; flex-direction:column; gap:4px; }
.kv .k { font-size:.85rem; color:#6b7280; }
.kv .v { font-weight:700; }
.table td, .table th { vertical-align: middle; }
</style>

<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="mb-0 page-title">Insurance</h2>
            <div class="text-muted">View your insurance policy, renewals, and claims.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- Latest Policy Summary -->
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold d-flex align-items-center justify-content-between">
                    <span>Current Policy</span>
                    <i class="bi bi-shield-check text-success"></i>
                </div>
                <div class="card-body">
                    <?php if (!$hasPolicy): ?>
                        <div class="text-muted">No insurance policy found.</div>
                    <?php else: ?>
                        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                            <div>
                                <div class="fw-bold"><?php echo h($policyLatest['provider_name'] ?? '-'); ?></div>
                                <div class="text-muted small"><?php echo h($policyLatest['contact_info'] ?? ''); ?></div>
                            </div>
                            <span class="badge bg-<?php echo h($badge); ?>"><?php echo h($badgeText); ?></span>
                        </div>

                        <hr>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="kv">
                                    <div class="k">Policy Number</div>
                                    <div class="v"><?php echo h($policyLatest['policy_number'] ?? '-'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="kv">
                                    <div class="k">Coverage Type</div>
                                    <div class="v"><?php echo h($policyLatest['coverage_type'] ?? '-'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="kv">
                                    <div class="k">Start Date</div>
                                    <div class="v"><?php echo h(fmtDate($policyLatest['start_date'] ?? null)); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="kv">
                                    <div class="k">End Date</div>
                                    <div class="v"><?php echo h(fmtDate($policyLatest['end_date'] ?? null)); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="kv">
                                    <div class="k">Status</div>
                                    <div class="v"><span class="badge bg-dark"><?php echo h($policyLatest['status'] ?? '-'); ?></span></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="kv">
                                    <div class="k">Policy ID</div>
                                    <div class="v"><?php echo (int)($policyLatest['policy_id'] ?? 0); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Actions</div>
                <div class="card-body">

                    <?php if (!$policies): ?>
                        <div class="text-muted">No policy available. Please contact ISSU staff.</div>
                    <?php else: ?>

                        <!-- Submit Renewal -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-2"><i class="bi bi-arrow-repeat me-1"></i> Submit Renewal Form</h6>
                            <form method="post" class="row g-2">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="submit_renewal">

                                <div class="col-md-5">
                                    <label class="form-label small">Policy</label>
                                    <select name="policy_id" class="form-select" required>
                                        <?php foreach ($policies as $p): ?>
                                            <option value="<?php echo (int)$p['policy_id']; ?>">
                                                <?php echo h(($p['policy_number'] ?? 'POL') . " • " . ($p['provider_name'] ?? '-')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label small">New End Date</label>
                                    <input type="date" name="new_end_date" class="form-control" required>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label small">Remarks (optional)</label>
                                    <input type="text" name="remarks" class="form-control" maxlength="255" placeholder="e.g., Request +12 months">
                                </div>

                                <div class="col-md-12">
                                    <button class="btn btn-success w-100">
                                        <i class="bi bi-send me-1"></i> Submit Renewal
                                    </button>
                                    <div class="text-muted small mt-2">
                                        Note: Your renewal request will be reviewed by staff/admin (Pending).
                                    </div>
                                </div>
                            </form>
                        </div>

                        <hr>

                        <!-- Submit Claim -->
                        <div>
                            <h6 class="fw-bold mb-2"><i class="bi bi-receipt me-1"></i> Submit Insurance Claim</h6>
                            <form method="post" class="row g-2">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="submit_claim">

                                <div class="col-md-5">
                                    <label class="form-label small">Policy</label>
                                    <select name="policy_id" class="form-select" required>
                                        <?php foreach ($policies as $p): ?>
                                            <option value="<?php echo (int)$p['policy_id']; ?>">
                                                <?php echo h(($p['policy_number'] ?? 'POL') . " • " . ($p['provider_name'] ?? '-')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label small">Claim Amount (RM)</label>
                                    <input type="number" step="0.01" min="0.01" name="claim_amount" class="form-control" required placeholder="e.g., 120.00">
                                </div>

                                <div class="col-md-12">
                                    <button class="btn btn-primary w-100">
                                        <i class="bi bi-plus-circle me-1"></i> Submit Claim
                                    </button>
                                    <div class="text-muted small mt-2">
                                        Note: pending claims may block exit approval (business rule).
                                    </div>
                                </div>
                            </form>
                        </div>

                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- Policies table -->
    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold">My Policies</div>
                <div class="card-body">
                    <?php if (!$policies): ?>
                        <div class="text-muted">No policies found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Policy ID</th>
                                    <th>Provider</th>
                                    <th>Policy No.</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($policies as $p): ?>
                                    <?php
                                        $d = daysLeft($p['end_date'] ?? null);
                                        [$b, $bt] = badgeForDays($d);
                                    ?>
                                    <tr>
                                        <td><?php echo (int)$p['policy_id']; ?></td>
                                        <td><?php echo h($p['provider_name'] ?? '-'); ?></td>
                                        <td class="fw-semibold"><?php echo h($p['policy_number'] ?? '-'); ?></td>
                                        <td><?php echo h(fmtDate($p['start_date'] ?? null)); ?></td>
                                        <td><?php echo h(fmtDate($p['end_date'] ?? null)); ?></td>
                                        <td><span class="badge bg-<?php echo h($b); ?>"><?php echo h($bt); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Renewals + Claims -->
    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Renewal Records</div>
                <div class="card-body">
                    <?php if (!$renewals): ?>
                        <div class="text-muted">No renewal records found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Renewal ID</th>
                                    <th>Policy</th>
                                    <th>Provider</th>
                                    <th>Renewal Date</th>
                                    <th>New End Date</th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($renewals as $r): ?>
                                    <?php
                                      $rs = $r['renewal_status'] ?? '-';
                                      $rsL = strtolower((string)$rs);
                                      $rb = 'secondary';
                                      if ($rsL === 'pending')  $rb = 'warning';
                                      if ($rsL === 'approved') $rb = 'success';
                                      if ($rsL === 'rejected') $rb = 'danger';
                                    ?>
                                    <tr>
                                        <td><?php echo (int)$r['renewal_id']; ?></td>
                                        <td class="fw-semibold"><?php echo h($r['policy_number'] ?? '-'); ?></td>
                                        <td><?php echo h($r['provider_name'] ?? '-'); ?></td>
                                        <td><?php echo h(fmtDate($r['renewal_date'] ?? null)); ?></td>
                                        <td><?php echo h(fmtDate($r['new_end_date'] ?? null)); ?></td>
                                        <td><span class="badge bg-<?php echo h($rb); ?>"><?php echo h($rs); ?></span></td>
                                    </tr>
                                    <?php if (!empty($r['remarks'])): ?>
                                        <tr>
                                            <td colspan="6" class="text-muted small">
                                                <strong>Remarks:</strong> <?php echo h($r['remarks']); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- <div class="text-muted small mt-2">
                            Note: If you don't see a status, add <code>insurance_renewal_record.status</code> with default <b>Pending</b>.
                        </div> -->
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Claims</div>
                <div class="card-body">
                    <?php if (!$claims): ?>
                        <div class="text-muted">No claims found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Claim ID</th>
                                    <th>Policy</th>
                                    <th>Provider</th>
                                    <th>Claim Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($claims as $c): ?>
                                    <?php
                                        $cs = strtolower((string)($c['claim_status'] ?? ''));
                                        $cb = 'secondary';
                                        if ($cs === 'pending')  $cb = 'warning';
                                        if ($cs === 'approved') $cb = 'success';
                                        if ($cs === 'rejected') $cb = 'danger';
                                    ?>
                                    <tr>
                                        <td><?php echo (int)$c['claim_id']; ?></td>
                                        <td class="fw-semibold"><?php echo h($c['policy_number'] ?? '-'); ?></td>
                                        <td><?php echo h($c['provider_name'] ?? '-'); ?></td>
                                        <td><?php echo h(fmtDate($c['claim_date'] ?? null)); ?></td>
                                        <td>RM <?php echo h(number_format((float)($c['claim_amount'] ?? 0), 2)); ?></td>
                                        <td><span class="badge bg-<?php echo h($cb); ?>"><?php echo h($c['claim_status'] ?? '-'); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . "/footer.php"; ?>
