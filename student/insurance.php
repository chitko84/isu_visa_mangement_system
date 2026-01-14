<?php
// student/insurance.php

$page_title = "Insurance - ISU Student Portal";
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

function currentDb(mysqli $conn): string {
    $res = $conn->query("SELECT DATABASE() AS db");
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->free();
    return (string)($row['db'] ?? '');
}

function tableExists(mysqli $conn, string $table): bool {
    $db = currentDb($conn);
    if ($db === '') return false;

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM information_schema.tables
        WHERE table_schema = ?
          AND table_name = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $db, $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ((int)($row['cnt'] ?? 0)) > 0;
}

/**
 * Try a stored procedure call safely with different signatures.
 * Returns true if any call succeeds.
 */
function callProcTry(mysqli $conn, array $sqlOptions, array $typesOptions, array $valuesOptions): bool {
    foreach ($sqlOptions as $i => $sql) {
        try {
            $stmt = $conn->prepare($sql);

            $types = $typesOptions[$i] ?? '';
            $vals  = $valuesOptions[$i] ?? [];

            if ($types !== '') {
                $refs = [];
                foreach ($vals as $k => $v) {
                    $refs[$k] = &$vals[$k];
                }
                array_unshift($refs, $types);
                call_user_func_array([$stmt, 'bind_param'], $refs);
            }

            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);
            return true;
        } catch (Throwable $e) {
            clearStoredResults($conn);
        }
    }
    return false;
}

$success = "";
$error   = "";

// ------------------------------------------------------------
// Renewal table name handling (insurance_renewal_record vs insruance_renewal_record)
// ------------------------------------------------------------
$renewalTable = null;
if (tableExists($conn, 'insurance_renewal_record')) {
    $renewalTable = 'insurance_renewal_record';
} elseif (tableExists($conn, 'insruance_renewal_record')) {
    $renewalTable = 'insruance_renewal_record';
}

// ------------------------------------------------------------
// Fetch current policy (latest by end_date)
// ------------------------------------------------------------
$currentPolicy = null;
$stmt = $conn->prepare("
    SELECT policy_id, provider_id, policy_number, start_date, end_date, coverage_type, status
    FROM insurance_policy
    WHERE student_id = ?
    ORDER BY end_date DESC, policy_id DESC
    LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$currentPolicy = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ------------------------------------------------------------
// Fetch provider info
// ------------------------------------------------------------
$provider = null;
if ($currentPolicy && !empty($currentPolicy['provider_id'])) {
    $pid = (int)$currentPolicy['provider_id'];
    $stmt = $conn->prepare("
        SELECT provider_id, provider_name, contact_info
        FROM insurance_provider
        WHERE provider_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $provider = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ------------------------------------------------------------
// Handle POST actions
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {

        // ============================================================
        // RENEWALS (insert/edit/delete in renewal record table)
        // ============================================================

        // Student: submit renewal (uses procedure if works; fallback insert)
        if ($action === 'submit_renewal') {
            if (!$currentPolicy) throw new Exception("No policy found. Cannot submit renewal.");
            if (!$renewalTable) throw new Exception("Renewal table not found in database.");

            $requested_months = (int)($_POST['requested_months'] ?? 0);
            if ($requested_months <= 0) throw new Exception("Requested months must be greater than 0.");

            $ok = callProcTry(
                $conn,
                [
                    "CALL sp_student_submit_insurance_renewal_form(?, ?, @o_renewal_id)",
                    "CALL sp_student_submit_insurance_renewal_form(?, ?)",
                    "CALL sp_student_submit_insurance_renewal_form(?)"
                ],
                ["ii", "ii", "i"],
                [
                    [$student_id, $requested_months],
                    [$student_id, $requested_months],
                    [$student_id]
                ]
            );

            if (!$ok) {
                $renewal_date = date('Y-m-d');

                $end = new DateTime($currentPolicy['end_date']);
                $end->modify('+' . $requested_months . ' months');
                $new_end_date = $end->format('Y-m-d');

                $remarks = "Requested +{$requested_months} months";

                $stmt = $conn->prepare("
                    INSERT INTO {$renewalTable} (policy_id, renewal_date, new_end_date, remarks)
                    VALUES (?, ?, ?, ?)
                ");
                $pid = (int)$currentPolicy['policy_id'];
                $stmt->bind_param("isss", $pid, $renewal_date, $new_end_date, $remarks);
                $stmt->execute();
                $stmt->close();
            }

            $success = "Renewal request submitted successfully.";
        }

        // Student: update a renewal record (EDIT)
        if ($action === 'update_renewal') {
            if (!$currentPolicy) throw new Exception("No policy found.");
            if (!$renewalTable) throw new Exception("Renewal table not found.");

            $renewal_id  = (int)($_POST['renewal_id'] ?? 0);
            $renewal_date = trim($_POST['renewal_date'] ?? '');
            $new_end_date = trim($_POST['new_end_date'] ?? '');
            $remarks      = trim($_POST['remarks'] ?? '');

            if ($renewal_id <= 0) throw new Exception("Invalid renewal ID.");
            if ($renewal_date === '' || $new_end_date === '') throw new Exception("Renewal date and new end date are required.");

            // ownership check: renewal belongs to student's policy
            $pid = (int)$currentPolicy['policy_id'];
            $stmt = $conn->prepare("SELECT renewal_id FROM {$renewalTable} WHERE renewal_id = ? AND policy_id = ? LIMIT 1");
            $stmt->bind_param("ii", $renewal_id, $pid);
            $stmt->execute();
            $owned = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$owned) throw new Exception("Renewal record not found or not yours.");

            $stmt = $conn->prepare("
                UPDATE {$renewalTable}
                SET renewal_date = ?, new_end_date = ?, remarks = ?
                WHERE renewal_id = ? AND policy_id = ?
                LIMIT 1
            ");
            $stmt->bind_param("sssii", $renewal_date, $new_end_date, $remarks, $renewal_id, $pid);
            $stmt->execute();
            $stmt->close();

            $success = "Renewal record updated.";
        }

        // Student: delete a renewal record (DELETE)
        if ($action === 'delete_renewal') {
            if (!$currentPolicy) throw new Exception("No policy found.");
            if (!$renewalTable) throw new Exception("Renewal table not found.");

            $renewal_id = (int)($_POST['renewal_id'] ?? 0);
            if ($renewal_id <= 0) throw new Exception("Invalid renewal ID.");

            $pid = (int)$currentPolicy['policy_id'];

            $stmt = $conn->prepare("DELETE FROM {$renewalTable} WHERE renewal_id = ? AND policy_id = ? LIMIT 1");
            $stmt->bind_param("ii", $renewal_id, $pid);
            $stmt->execute();
            $stmt->close();

            $success = "Renewal record deleted.";
        }

        // ============================================================
        // CLAIMS (insert/edit/delete in insurance_claim)
        // ============================================================

        // Student: submit claim (procedure if works; fallback insert)
        if ($action === 'submit_claim') {
            if (!$currentPolicy) throw new Exception("No policy found. Cannot submit claim.");

            $claim_amount = (float)($_POST['claim_amount'] ?? 0);
            $claim_date   = trim($_POST['claim_date'] ?? '');

            if ($claim_amount <= 0) throw new Exception("Claim amount must be greater than 0.");
            if ($claim_date === '') $claim_date = date('Y-m-d');

            $policy_id = (int)$currentPolicy['policy_id'];

            $ok = callProcTry(
                $conn,
                [
                    "CALL sp_student_submit_insurance_claim(?, ?, @o_claim_id)",
                    "CALL sp_student_submit_insurance_claim(?, ?, ?)",
                    "CALL sp_student_submit_insurance_claim(?, ?, ?, @o_claim_id)"
                ],
                ["id", "isd", "isd"],
                [
                    [$student_id, $claim_amount],
                    [$policy_id, $claim_date, $claim_amount],
                    [$policy_id, $claim_date, $claim_amount]
                ]
            );

            if (!$ok) {
                $status = "Pending";
                $stmt = $conn->prepare("
                    INSERT INTO insurance_claim (policy_id, claim_date, claim_amount, claim_status)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("isds", $policy_id, $claim_date, $claim_amount, $status);
                $stmt->execute();
                $stmt->close();
            }

            $success = "Claim submitted successfully.";
        }

        // Student: update claim (EDIT) - only allowed when Pending
        if ($action === 'update_claim') {
            if (!$currentPolicy) throw new Exception("No policy found.");

            $claim_id     = (int)($_POST['claim_id'] ?? 0);
            $claim_date   = trim($_POST['claim_date'] ?? '');
            $claim_amount = (float)($_POST['claim_amount'] ?? 0);

            if ($claim_id <= 0) throw new Exception("Invalid claim ID.");
            if ($claim_date === '') throw new Exception("Claim date is required.");
            if ($claim_amount <= 0) throw new Exception("Claim amount must be greater than 0.");

            $policy_id = (int)$currentPolicy['policy_id'];

            // ownership + status check
            $stmt = $conn->prepare("
                SELECT claim_status
                FROM insurance_claim
                WHERE claim_id = ? AND policy_id = ?
                LIMIT 1
            ");
            $stmt->bind_param("ii", $claim_id, $policy_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) throw new Exception("Claim not found or not yours.");
            if (($row['claim_status'] ?? '') !== 'Pending') {
                throw new Exception("Only Pending claims can be edited.");
            }

            $stmt = $conn->prepare("
                UPDATE insurance_claim
                SET claim_date = ?, claim_amount = ?
                WHERE claim_id = ? AND policy_id = ?
                LIMIT 1
            ");
            $stmt->bind_param("sdii", $claim_date, $claim_amount, $claim_id, $policy_id);
            $stmt->execute();
            $stmt->close();

            $success = "Claim updated.";
        }

        // Student: delete claim (DELETE) - only allowed when Pending
        if ($action === 'delete_claim') {
            if (!$currentPolicy) throw new Exception("No policy found.");

            $claim_id = (int)($_POST['claim_id'] ?? 0);
            if ($claim_id <= 0) throw new Exception("Invalid claim ID.");

            $policy_id = (int)$currentPolicy['policy_id'];

            $stmt = $conn->prepare("
                SELECT claim_status
                FROM insurance_claim
                WHERE claim_id = ? AND policy_id = ?
                LIMIT 1
            ");
            $stmt->bind_param("ii", $claim_id, $policy_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) throw new Exception("Claim not found or not yours.");
            if (($row['claim_status'] ?? '') !== 'Pending') {
                throw new Exception("Only Pending claims can be deleted.");
            }

            $stmt = $conn->prepare("DELETE FROM insurance_claim WHERE claim_id = ? AND policy_id = ? LIMIT 1");
            $stmt->bind_param("ii", $claim_id, $policy_id);
            $stmt->execute();
            $stmt->close();

            $success = "Claim deleted.";
        }

        // Staff/Admin: update claim status (procedure if works; fallback update)
        if ($action === 'staff_update_claim_status') {
            if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
                throw new Exception("Unauthorized (staff only).");
            }

            $claim_id   = (int)($_POST['claim_id'] ?? 0);
            $new_status = trim($_POST['new_status'] ?? '');
            if ($claim_id <= 0 || $new_status === '') throw new Exception("Claim ID and new status are required.");

            $remarks = trim($_POST['remarks'] ?? '');

            $ok = callProcTry(
                $conn,
                [
                    "CALL sp_staff_update_claim_status(?, ?, ?)",
                    "CALL sp_staff_update_claim_status(?, ?)"
                ],
                ["iss", "is"],
                [
                    [$claim_id, $new_status, $remarks],
                    [$claim_id, $new_status]
                ]
            );

            if (!$ok) {
                $stmt = $conn->prepare("UPDATE insurance_claim SET claim_status = ? WHERE claim_id = ? LIMIT 1");
                $stmt->bind_param("si", $new_status, $claim_id);
                $stmt->execute();
                $stmt->close();
            }

            $success = "Claim status updated.";
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
        clearStoredResults($conn);
    }

    // Refresh data after actions
    $stmt = $conn->prepare("
        SELECT policy_id, provider_id, policy_number, start_date, end_date, coverage_type, status
        FROM insurance_policy
        WHERE student_id = ?
        ORDER BY end_date DESC, policy_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $currentPolicy = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $provider = null;
    if ($currentPolicy && !empty($currentPolicy['provider_id'])) {
        $pid = (int)$currentPolicy['provider_id'];
        $stmt = $conn->prepare("
            SELECT provider_id, provider_name, contact_info
            FROM insurance_provider
            WHERE provider_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $provider = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// ------------------------------------------------------------
// Fetch lists for display (after refresh)
// ------------------------------------------------------------
$renewals = [];
if ($renewalTable && $currentPolicy) {
    $pid = (int)$currentPolicy['policy_id'];
    $stmt = $conn->prepare("
        SELECT renewal_id, policy_id, renewal_date, new_end_date, remarks
        FROM {$renewalTable}
        WHERE policy_id = ?
        ORDER BY renewal_date DESC, renewal_id DESC
    ");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $renewals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$claims = [];
if ($currentPolicy) {
    $pid = (int)$currentPolicy['policy_id'];
    $stmt = $conn->prepare("
        SELECT claim_id, policy_id, claim_date, claim_amount, claim_status
        FROM insurance_claim
        WHERE policy_id = ?
        ORDER BY claim_date DESC, claim_id DESC
    ");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="mb-0">Insurance</h2>
            <div class="text-muted">View your policy, submit renewals, and manage claims.</div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- Current Policy -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Current Policy</div>
        <div class="card-body">
            <?php if ($currentPolicy): ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="small text-muted">Policy Number</div>
                        <div class="fw-semibold"><?php echo h($currentPolicy['policy_number']); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Coverage Type</div>
                        <div class="fw-semibold"><?php echo h($currentPolicy['coverage_type'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Status</div>
                        <span class="badge <?php echo (($currentPolicy['status'] ?? '') === 'Active') ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo h($currentPolicy['status']); ?>
                        </span>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Start Date</div>
                        <div class="fw-semibold"><?php echo h($currentPolicy['start_date']); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">End Date</div>
                        <div class="fw-semibold"><?php echo h($currentPolicy['end_date']); ?></div>
                    </div>
                </div>

                <?php if ($provider): ?>
                    <hr>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small text-muted">Provider Name</div>
                            <div class="fw-semibold"><?php echo h($provider['provider_name']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Contact Info</div>
                            <div class="fw-semibold"><?php echo h($provider['contact_info'] ?? '-'); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-muted">No policy found.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Submit Renewal -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Submit Renewal Request</div>
        <div class="card-body">
            <?php if (!$currentPolicy): ?>
                <div class="text-muted">You need a policy before you can submit a renewal.</div>
            <?php else: ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="submit_renewal">
                    <div class="col-md-6">
                        <label class="form-label">Requested Months</label>
                        <input type="number" name="requested_months" class="form-control" min="1" max="60" required placeholder="e.g., 12">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button class="btn btn-primary">Submit Renewal</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Renewal History (Editable + Deletable) -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Renewal History</div>
        <div class="card-body">
            <?php if (!$renewalTable): ?>
                <div class="text-muted">Renewal records table not found.</div>
            <?php elseif (!$renewals): ?>
                <div class="text-muted">No renewals found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                        <tr>
                            <th>Renewal ID</th>
                            <th>Renewal Date</th>
                            <th>New End Date</th>
                            <th>Remarks</th>
                            <th style="width: 250px;">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($renewals as $r): ?>
                            <tr>
                                <td><?php echo h($r['renewal_id']); ?></td>
                                <td><?php echo h($r['renewal_date']); ?></td>
                                <td><?php echo h($r['new_end_date']); ?></td>
                                <td><?php echo h($r['remarks']); ?></td>
                                <td>
                                    <!-- Edit -->
                                    <button class="btn btn-sm btn-outline-primary"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#editRenewal<?php echo (int)$r['renewal_id']; ?>">
                                        Edit
                                    </button>

                                    <!-- Delete -->
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this renewal record?');">
                                        <input type="hidden" name="action" value="delete_renewal">
                                        <input type="hidden" name="renewal_id" value="<?php echo (int)$r['renewal_id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>

                                    <!-- Edit form -->
                                    <div class="collapse mt-2" id="editRenewal<?php echo (int)$r['renewal_id']; ?>">
                                        <div class="border rounded p-2">
                                            <form method="post" class="row g-2">
                                                <input type="hidden" name="action" value="update_renewal">
                                                <input type="hidden" name="renewal_id" value="<?php echo (int)$r['renewal_id']; ?>">

                                                <div class="col-12">
                                                    <label class="form-label small mb-1">Renewal Date</label>
                                                    <input type="date" name="renewal_date" class="form-control"
                                                           value="<?php echo h($r['renewal_date']); ?>" required>
                                                </div>

                                                <div class="col-12">
                                                    <label class="form-label small mb-1">New End Date</label>
                                                    <input type="date" name="new_end_date" class="form-control"
                                                           value="<?php echo h($r['new_end_date']); ?>" required>
                                                </div>

                                                <div class="col-12">
                                                    <label class="form-label small mb-1">Remarks</label>
                                                    <input type="text" name="remarks" class="form-control"
                                                           value="<?php echo h($r['remarks']); ?>">
                                                </div>

                                                <div class="col-12">
                                                    <button class="btn btn-sm btn-primary" type="submit">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Submit Claim -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Submit Claim</div>
        <div class="card-body">
            <?php if (!$currentPolicy): ?>
                <div class="text-muted">You need a policy before you can submit a claim.</div>
            <?php else: ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="submit_claim">

                    <div class="col-md-6">
                        <label class="form-label">Claim Date</label>
                        <input type="date" name="claim_date" class="form-control" value="<?php echo h(date('Y-m-d')); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Claim Amount (RM)</label>
                        <input type="number" step="0.01" min="0.01" name="claim_amount" class="form-control" required placeholder="e.g., 120.50">
                    </div>

                    <div class="col-12">
                        <button class="btn btn-success">Submit Claim</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Claim History (Editable + Deletable when Pending) -->
    <div class="card mb-5">
        <div class="card-header fw-semibold">Claim History</div>
        <div class="card-body">
            <?php if (!$claims): ?>
                <div class="text-muted">No claims found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Claim ID</th>
                            <th>Claim Date</th>
                            <th>Amount (RM)</th>
                            <th>Status</th>
                            <th style="width: 280px;">Student Actions</th>
                            <?php if (in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)): ?>
                                <th style="width: 280px;">Staff Action</th>
                            <?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($claims as $c): ?>
                            <?php $isPending = (($c['claim_status'] ?? '') === 'Pending'); ?>
                            <tr>
                                <td><?php echo h($c['claim_id']); ?></td>
                                <td><?php echo h($c['claim_date']); ?></td>
                                <td><?php echo number_format((float)$c['claim_amount'], 2); ?></td>
                                <td><span class="badge bg-dark"><?php echo h($c['claim_status']); ?></span></td>

                                <td>
                                    <?php if ($isPending): ?>
                                        <button class="btn btn-sm btn-outline-primary"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#editClaim<?php echo (int)$c['claim_id']; ?>">
                                            Edit
                                        </button>

                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this claim?');">
                                            <input type="hidden" name="action" value="delete_claim">
                                            <input type="hidden" name="claim_id" value="<?php echo (int)$c['claim_id']; ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                        </form>

                                        <div class="collapse mt-2" id="editClaim<?php echo (int)$c['claim_id']; ?>">
                                            <div class="border rounded p-2">
                                                <form method="post" class="row g-2">
                                                    <input type="hidden" name="action" value="update_claim">
                                                    <input type="hidden" name="claim_id" value="<?php echo (int)$c['claim_id']; ?>">

                                                    <div class="col-12">
                                                        <label class="form-label small mb-1">Claim Date</label>
                                                        <input type="date" name="claim_date" class="form-control"
                                                               value="<?php echo h($c['claim_date']); ?>" required>
                                                    </div>

                                                    <div class="col-12">
                                                        <label class="form-label small mb-1">Claim Amount (RM)</label>
                                                        <input type="number" step="0.01" min="0.01" name="claim_amount" class="form-control"
                                                               value="<?php echo h($c['claim_amount']); ?>" required>
                                                    </div>

                                                    <div class="col-12">
                                                        <button class="btn btn-sm btn-primary" type="submit">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not editable (<?php echo h($c['claim_status']); ?>)</span>
                                    <?php endif; ?>
                                </td>

                                <?php if (in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)): ?>
                                    <td>
                                        <form method="post" class="row g-2">
                                            <input type="hidden" name="action" value="staff_update_claim_status">
                                            <input type="hidden" name="claim_id" value="<?php echo (int)$c['claim_id']; ?>">

                                            <div class="col-12">
                                                <select name="new_status" class="form-select" required>
                                                    <option value="">-- Choose status --</option>
                                                    <option value="Pending">Pending</option>
                                                    <option value="Approved">Approved</option>
                                                    <option value="Rejected">Rejected</option>
                                                    <option value="Paid">Paid</option>
                                                </select>
                                            </div>

                                            <div class="col-12">
                                                <input type="text" name="remarks" class="form-control" placeholder="Remarks (optional)">
                                            </div>

                                            <div class="col-12">
                                                <button class="btn btn-sm btn-outline-primary">Update</button>
                                            </div>
                                        </form>
                                    </td>
                                <?php endif; ?>

                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . "/footer.php"; ?>
