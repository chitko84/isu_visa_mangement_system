<?php
// student/exit.php

$page_title = "Exit Module - ISU Student Portal";
require_once __DIR__ . "/header.php"; // session + db ($conn) + $student_id

// ------------------------------------------------------------
// Helpers (important for stored procedures: avoid commands out of sync)
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

// Exit types MUST match DB CHECK constraint:
// CHECK (exit_type in ('Completion','Withdrawal','Termination'))
$allowedExitTypes = ['Completion', 'Withdrawal', 'Termination'];

// Exit visa action types MUST match DB CHECK constraint:
// CHECK (action_type in ('Cancellation','Lapse','Transfer'))
$allowedActionTypes = ['Cancellation', 'Lapse', 'Transfer'];

// ------------------------------------------------------------
// Fetch latest visa (optional validation display)
// ------------------------------------------------------------
$currentVisa = null;
$stmt = $conn->prepare("
    SELECT visa_id, visa_type, passport_no, issue_date, expiry_date, status
    FROM student_visa
    WHERE student_id = ?
    ORDER BY expiry_date DESC, visa_id DESC
    LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$currentVisa = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ------------------------------------------------------------
// Fetch latest exit case for this student (by request_date)
// ------------------------------------------------------------
$exitCase = null;
$stmt = $conn->prepare("
    SELECT exit_id, student_id, exit_type, request_date, exit_status
    FROM exit_case
    WHERE student_id = ?
    ORDER BY request_date DESC, exit_id DESC
    LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$exitCase = $stmt->get_result()->fetch_assoc();
$stmt->close();

$currentExitId = $exitCase['exit_id'] ?? null;

// ------------------------------------------------------------
// Fetch clearance record (one-to-one with exit_id - UNIQUE exit_id)
// ------------------------------------------------------------
$clearance = null;
if ($currentExitId) {
    $stmt = $conn->prepare("
        SELECT clearance_id, exit_id, submission_date, status
        FROM clearance_record
        WHERE exit_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $currentExitId);
    $stmt->execute();
    $clearance = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$currentClearanceId = $clearance['clearance_id'] ?? null;

// ------------------------------------------------------------
// Fetch unit clearance list (by clearance_id)
// ------------------------------------------------------------
$unitClearances = [];
if ($currentClearanceId) {
    $stmt = $conn->prepare("
        SELECT unit_clearance_id, clearance_id, unit_name, clearance_date
        FROM unit_clearance
        WHERE clearance_id = ?
        ORDER BY unit_name ASC, unit_clearance_id ASC
    ");
    $stmt->bind_param("i", $currentClearanceId);
    $stmt->execute();
    $unitClearances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ------------------------------------------------------------
// Fetch exit visa actions list (by exit_id)
// ------------------------------------------------------------
$visaActions = [];
if ($currentExitId) {
    $stmt = $conn->prepare("
        SELECT exit_visa_id, exit_id, action_type, action_date, remarks
        FROM exit_visa_action
        WHERE exit_id = ?
        ORDER BY action_date DESC, exit_visa_id DESC
    ");
    $stmt->bind_param("i", $currentExitId);
    $stmt->execute();
    $visaActions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ------------------------------------------------------------
// Decide if student can submit new exit request
// (Simple rule: allow if no exit case OR latest is Completed)
// ------------------------------------------------------------
$canSubmitExit = true;
if ($exitCase && ($exitCase['exit_status'] ?? '') !== 'Completed') {
    $canSubmitExit = false;
}

// ------------------------------------------------------------
// Handle POST actions
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {

        // ---------------------------
        // Student: submit exit request
        // Uses: sp_student_submit_exit_request(IN student_id, IN exit_type, OUT exit_id)
        // ---------------------------
        if ($action === 'submit_exit') {

            if (!$canSubmitExit) {
                throw new Exception("You already have an exit request in progress. You can only submit a new one when the current case is completed.");
            }

            $exit_type = trim($_POST['exit_type'] ?? '');
            if ($exit_type === '') {
                throw new Exception("Exit type is required.");
            }
            if (!in_array($exit_type, $allowedExitTypes, true)) {
                throw new Exception("Invalid exit type selected. Allowed: Completion, Withdrawal, Termination.");
            }

            $stmt = $conn->prepare("CALL sp_student_submit_exit_request(?, ?, @o_exit_id)");
            $stmt->bind_param("is", $student_id, $exit_type);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $res = $conn->query("SELECT @o_exit_id AS exit_id");
            $row = $res ? $res->fetch_assoc() : null;
            $newExitId = (int)($row['exit_id'] ?? 0);
            if ($res) $res->free();

            if ($newExitId <= 0) {
                throw new Exception("Failed to create exit request.");
            }

            $success = "Exit request submitted successfully. Exit ID: {$newExitId}";
        }

        // ---------------------------
        // Staff/Admin: update exit status (hidden for students)
        // sp_staff_update_exit_status(IN exit_id, IN new_status)
        // ---------------------------
        if ($action === 'staff_update_exit_status') {
            if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
                throw new Exception("Unauthorized.");
            }

            $exit_id = (int)($_POST['exit_id'] ?? 0);
            $new_status = trim($_POST['new_status'] ?? '');

            if ($exit_id <= 0 || $new_status === '') {
                throw new Exception("Exit ID and new status are required.");
            }

            $stmt = $conn->prepare("CALL sp_staff_update_exit_status(?, ?)");
            $stmt->bind_param("is", $exit_id, $new_status);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $success = "Exit status updated.";
        }

        // ---------------------------
        // Staff/Admin: create clearance record (one per exit case)
        // sp_staff_create_clearance_record(IN exit_id, IN status, OUT clearance_id)
        // clearance_record.status CHECK ('In Progress','Completed')
        // ---------------------------
        if ($action === 'staff_create_clearance') {
            if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
                throw new Exception("Unauthorized.");
            }

            $exit_id = (int)($_POST['exit_id'] ?? 0);
            $status  = trim($_POST['status'] ?? '');

            $allowed = ['In Progress', 'Completed'];
            if ($exit_id <= 0) throw new Exception("Exit ID is required.");
            if (!in_array($status, $allowed, true)) throw new Exception("Invalid clearance status.");

            $stmt = $conn->prepare("CALL sp_staff_create_clearance_record(?, ?, @o_clearance_id)");
            $stmt->bind_param("is", $exit_id, $status);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $success = "Clearance record created.";
        }

        // ---------------------------
        // Staff/Admin: add unit clearance (upsert)
        // sp_staff_upsert_unit_clearance(IN clearance_id, IN unit_name, IN clearance_date, OUT unit_clearance_id)
        // ---------------------------
        if ($action === 'staff_upsert_unit_clearance') {
            if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
                throw new Exception("Unauthorized.");
            }

            $clearance_id = (int)($_POST['clearance_id'] ?? 0);
            $unit_name    = trim($_POST['unit_name'] ?? '');
            $clearance_date = trim($_POST['clearance_date'] ?? '');
            if ($clearance_id <= 0 || $unit_name === '') {
                throw new Exception("Clearance ID and unit name are required.");
            }
            if ($clearance_date === '') {
                $clearance_date = date('Y-m-d');
            }

            $stmt = $conn->prepare("CALL sp_staff_upsert_unit_clearance(?, ?, ?, @o_unit_clearance_id)");
            $stmt->bind_param("iss", $clearance_id, $unit_name, $clearance_date);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $success = "Unit clearance saved.";
        }

        // ---------------------------
        // Staff/Admin: UPDATE unit clearance
        // sp_staff_update_unit_clearance(IN unit_clearance_id, IN unit_name, IN clearance_date)
        // ---------------------------
        if ($action === 'staff_update_unit_clearance') {
            if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
                throw new Exception("Unauthorized.");
            }

            $unit_clearance_id = (int)($_POST['unit_clearance_id'] ?? 0);
            $unit_name = trim($_POST['unit_name'] ?? '');
            $clearance_date = trim($_POST['clearance_date'] ?? '');

            if ($unit_clearance_id <= 0) throw new Exception("Unit clearance ID is required.");
            if ($unit_name === '') throw new Exception("Unit name is required.");
            if ($clearance_date === '') $clearance_date = date('Y-m-d');

            $stmt = $conn->prepare("CALL sp_staff_update_unit_clearance(?, ?, ?)");
            $stmt->bind_param("iss", $unit_clearance_id, $unit_name, $clearance_date);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $success = "Unit clearance updated.";
        }

        // ---------------------------
        // Staff/Admin: DELETE unit clearance
        // sp_staff_delete_unit_clearance(IN unit_clearance_id)
        // ---------------------------
        if ($action === 'staff_delete_unit_clearance') {
            if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
                throw new Exception("Unauthorized.");
            }

            $unit_clearance_id = (int)($_POST['unit_clearance_id'] ?? 0);
            if ($unit_clearance_id <= 0) throw new Exception("Unit clearance ID is required.");

            $stmt = $conn->prepare("CALL sp_staff_delete_unit_clearance(?)");
            $stmt->bind_param("i", $unit_clearance_id);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $success = "Unit clearance deleted.";
        }

        // ---------------------------
        // Staff/Admin: add exit visa action
        // sp_staff_add_exit_visa_action(IN exit_id, IN action_type, IN action_date, IN remarks, OUT exit_visa_id)
        // ---------------------------
        if ($action === 'staff_add_exit_visa_action') {
            if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
                throw new Exception("Unauthorized.");
            }

            $exit_id = (int)($_POST['exit_id'] ?? 0);
            $action_type = trim($_POST['action_type'] ?? '');
            $action_date = trim($_POST['action_date'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');

            if ($exit_id <= 0) throw new Exception("Exit ID is required.");
            if (!in_array($action_type, $allowedActionTypes, true)) {
                throw new Exception("Invalid action type. Allowed: Cancellation, Lapse, Transfer.");
            }
            if ($action_date === '') $action_date = date('Y-m-d');

            $stmt = $conn->prepare("CALL sp_staff_add_exit_visa_action(?, ?, ?, ?, @o_exit_visa_id)");
            $stmt->bind_param("isss", $exit_id, $action_type, $action_date, $remarks);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $success = "Exit visa action added.";
        }

        // ---------------------------
        // Staff/Admin: UPDATE exit visa action
        // sp_staff_update_exit_visa_action(IN exit_visa_id, IN action_type, IN action_date, IN remarks)
        // ---------------------------
        if ($action === 'staff_update_exit_visa_action') {
            if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
                throw new Exception("Unauthorized.");
            }

            $exit_visa_id = (int)($_POST['exit_visa_id'] ?? 0);
            $action_type  = trim($_POST['action_type'] ?? '');
            $action_date  = trim($_POST['action_date'] ?? '');
            $remarks      = trim($_POST['remarks'] ?? '');

            if ($exit_visa_id <= 0) throw new Exception("Exit visa action ID is required.");
            if (!in_array($action_type, $allowedActionTypes, true)) {
                throw new Exception("Invalid action type. Allowed: Cancellation, Lapse, Transfer.");
            }
            if ($action_date === '') $action_date = date('Y-m-d');

            $stmt = $conn->prepare("CALL sp_staff_update_exit_visa_action(?, ?, ?, ?)");
            $stmt->bind_param("isss", $exit_visa_id, $action_type, $action_date, $remarks);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $success = "Exit visa action updated.";
        }

        // ---------------------------
        // Staff/Admin: DELETE exit visa action
        // sp_staff_delete_exit_visa_action(IN exit_visa_id)
        // ---------------------------
        if ($action === 'staff_delete_exit_visa_action') {
            if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
                throw new Exception("Unauthorized.");
            }

            $exit_visa_id = (int)($_POST['exit_visa_id'] ?? 0);
            if ($exit_visa_id <= 0) throw new Exception("Exit visa action ID is required.");

            $stmt = $conn->prepare("CALL sp_staff_delete_exit_visa_action(?)");
            $stmt->bind_param("i", $exit_visa_id);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $success = "Exit visa action deleted.";
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
        clearStoredResults($conn);
    }

    // --------------------------------------------------------
    // Re-fetch everything after actions
    // --------------------------------------------------------
    $stmt = $conn->prepare("
        SELECT exit_id, student_id, exit_type, request_date, exit_status
        FROM exit_case
        WHERE student_id = ?
        ORDER BY request_date DESC, exit_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $exitCase = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $currentExitId = $exitCase['exit_id'] ?? null;

    $clearance = null;
    if ($currentExitId) {
        $stmt = $conn->prepare("
            SELECT clearance_id, exit_id, submission_date, status
            FROM clearance_record
            WHERE exit_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $currentExitId);
        $stmt->execute();
        $clearance = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    $currentClearanceId = $clearance['clearance_id'] ?? null;

    $unitClearances = [];
    if ($currentClearanceId) {
        $stmt = $conn->prepare("
            SELECT unit_clearance_id, clearance_id, unit_name, clearance_date
            FROM unit_clearance
            WHERE clearance_id = ?
            ORDER BY unit_name ASC, unit_clearance_id ASC
        ");
        $stmt->bind_param("i", $currentClearanceId);
        $stmt->execute();
        $unitClearances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    $visaActions = [];
    if ($currentExitId) {
        $stmt = $conn->prepare("
            SELECT exit_visa_id, exit_id, action_type, action_date, remarks
            FROM exit_visa_action
            WHERE exit_id = ?
            ORDER BY action_date DESC, exit_visa_id DESC
        ");
        $stmt->bind_param("i", $currentExitId);
        $stmt->execute();
        $visaActions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    $canSubmitExit = true;
    if ($exitCase && ($exitCase['exit_status'] ?? '') !== 'Completed') {
        $canSubmitExit = false;
    }
}
?>

<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="mb-0">Exit Module</h2>
            <div class="text-muted">Submit your exit request and track clearance progress.</div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- Current Visa (display only) -->
    <div class="card mb-4">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <span>Current Visa</span>
            <?php if ($currentVisa && in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)): ?>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editVisaModal">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($currentVisa): ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="small text-muted">Visa Type</div>
                        <div class="fw-semibold"><?php echo h($currentVisa['visa_type']); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Passport No</div>
                        <div class="fw-semibold"><?php echo h($currentVisa['passport_no']); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Status</div>
                        <span class="badge <?php echo (($currentVisa['status'] ?? '') === 'Active') ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo h($currentVisa['status']); ?>
                        </span>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Issue Date</div>
                        <div class="fw-semibold"><?php echo h($currentVisa['issue_date']); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Expiry Date</div>
                        <div class="fw-semibold"><?php echo h($currentVisa['expiry_date']); ?></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-muted">No visa record found.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Visa Modal (Staff/Admin only) -->
    <?php if ($currentVisa && in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)): ?>
    <div class="modal fade" id="editVisaModal" tabindex="-1" aria-labelledby="editVisaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editVisaModalLabel">Edit Visa Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="update_visa.php">
                    <div class="modal-body">
                        <input type="hidden" name="visa_id" value="<?php echo h($currentVisa['visa_id']); ?>">
                        <input type="hidden" name="student_id" value="<?php echo h($student_id); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Visa Type</label>
                            <input type="text" name="visa_type" class="form-control" value="<?php echo h($currentVisa['visa_type']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Passport No</label>
                            <input type="text" name="passport_no" class="form-control" value="<?php echo h($currentVisa['passport_no']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Issue Date</label>
                            <input type="date" name="issue_date" class="form-control" value="<?php echo h($currentVisa['issue_date']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control" value="<?php echo h($currentVisa['expiry_date']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="Active" <?php echo ($currentVisa['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Expired" <?php echo ($currentVisa['status'] === 'Expired') ? 'selected' : ''; ?>>Expired</option>
                                <option value="Cancelled" <?php echo ($currentVisa['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Submit Exit Request (Student) -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Submit Exit Request</div>
        <div class="card-body">
            <?php if (!$canSubmitExit && $exitCase): ?>
                <div class="alert alert-info mb-0">
                    You already have an exit request in progress (Exit ID: <strong><?php echo h($exitCase['exit_id']); ?></strong>,
                    Status: <strong><?php echo h($exitCase['exit_status']); ?></strong>).
                    You can submit a new request only when it is <strong>Completed</strong>.
                </div>
            <?php else: ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="submit_exit">

                    <div class="col-md-6">
                        <label class="form-label">Exit Type</label>
                        <select name="exit_type" class="form-select" required>
                            <option value="">-- Choose exit type --</option>
                            <option value="Completion">Completion</option>
                            <option value="Withdrawal">Withdrawal</option>
                            <option value="Termination">Termination</option>
                        </select>
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <button class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i> Submit Exit Request
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Exit Case Details -->
    <div class="card mb-4">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <span>Exit Case Details</span>
            <?php if ($exitCase && in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)): ?>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editExitCaseModal">
                    <i class="fas fa-edit"></i> Edit
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($exitCase): ?>
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="small text-muted">Exit ID</div>
                        <div class="fw-semibold"><?php echo h($exitCase['exit_id']); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Exit Type</div>
                        <div class="fw-semibold"><?php echo h($exitCase['exit_type']); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Request Date</div>
                        <div class="fw-semibold"><?php echo h($exitCase['request_date']); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Exit Status</div>
                        <span class="badge bg-dark"><?php echo h($exitCase['exit_status']); ?></span>
                    </div>
                </div>
                
                <!-- Delete Exit Case (Admin only) -->
                <?php if ($exitCase && ($_SESSION['role'] ?? '') === 'admin'): ?>
                <div class="mt-3 pt-3 border-top">
                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this exit case? This will also delete related clearance records, unit clearances, and visa actions.');">
                        <input type="hidden" name="action" value="delete_exit_case">
                        <input type="hidden" name="exit_id" value="<?php echo (int)$exitCase['exit_id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-trash me-1"></i> Delete Exit Case
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-muted">No exit request found yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Exit Case Modal -->
    <?php if ($exitCase && in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)): ?>
    <div class="modal fade" id="editExitCaseModal" tabindex="-1" aria-labelledby="editExitCaseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editExitCaseModalLabel">Edit Exit Case</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_exit_case">
                        <input type="hidden" name="exit_id" value="<?php echo h($exitCase['exit_id']); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Exit Type</label>
                            <select name="exit_type" class="form-select" required>
                                <?php foreach ($allowedExitTypes as $type): ?>
                                    <option value="<?php echo h($type); ?>" <?php echo ($exitCase['exit_type'] === $type) ? 'selected' : ''; ?>>
                                        <?php echo h($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Request Date</label>
                            <input type="date" name="request_date" class="form-control" value="<?php echo h($exitCase['request_date']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Exit Status</label>
                            <select name="exit_status" class="form-select" required>
                                <option value="Pending" <?php echo ($exitCase['exit_status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="In Progress" <?php echo ($exitCase['exit_status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Approved" <?php echo ($exitCase['exit_status'] === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="Completed" <?php echo ($exitCase['exit_status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="Rejected" <?php echo ($exitCase['exit_status'] === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Clearance Record -->
    <div class="card mb-4">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <span>Clearance Record</span>
            <?php if ($clearance && in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)): ?>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editClearanceModal">
                    <i class="fas fa-edit"></i> Edit
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!$exitCase): ?>
                <div class="text-muted">Submit an exit request first.</div>
            <?php elseif (!$clearance): ?>
                <div class="text-muted">No clearance record created yet.</div>
            <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="small text-muted">Clearance ID</div>
                        <div class="fw-semibold"><?php echo h($clearance['clearance_id']); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Submission Date</div>
                        <div class="fw-semibold"><?php echo h($clearance['submission_date']); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Status</div>
                        <span class="badge <?php echo (($clearance['status'] ?? '') === 'Completed') ? 'bg-success' : 'bg-warning text-dark'; ?>">
                            <?php echo h($clearance['status']); ?>
                        </span>
                    </div>
                    <div class="col-md-3">
                        <?php if ($clearance && in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)): ?>
                            <form method="post" onsubmit="return confirm('Delete this clearance record? This will also delete all unit clearances.');" class="mt-3">
                                <input type="hidden" name="action" value="delete_clearance_record">
                                <input type="hidden" name="clearance_id" value="<?php echo (int)$clearance['clearance_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash me-1"></i> Delete
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true) && $exitCase): ?>
                <hr>
                <h6 class="mb-2">Staff: Create Clearance Record</h6>
                <form method="post" class="row g-2">
                    <input type="hidden" name="action" value="staff_create_clearance">
                    <input type="hidden" name="exit_id" value="<?php echo (int)$exitCase['exit_id']; ?>">

                    <div class="col-md-4">
                        <select name="status" class="form-select" required>
                            <option value="">-- Choose --</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-outline-primary">
                            <i class="fas fa-plus me-1"></i> Create
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Clearance Modal -->
    <?php if ($clearance && in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)): ?>
    <div class="modal fade" id="editClearanceModal" tabindex="-1" aria-labelledby="editClearanceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editClearanceModalLabel">Edit Clearance Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_clearance_record">
                        <input type="hidden" name="clearance_id" value="<?php echo h($clearance['clearance_id']); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Submission Date</label>
                            <input type="date" name="submission_date" class="form-control" value="<?php echo h($clearance['submission_date']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="In Progress" <?php echo ($clearance['status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Completed" <?php echo ($clearance['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Unit Clearance -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Unit Clearance</div>
        <div class="card-body">
            <?php if (!$clearance): ?>
                <div class="text-muted">No clearance record yet.</div>
            <?php elseif (!$unitClearances): ?>
                <div class="text-muted">No unit clearance entries yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Clearance Date</th>
                            <?php if (in_array($_SESSION['role'] ?? '', ['staff','admin'], true)): ?>
                                <th style="width: 150px;">Actions</th>
                            <?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($unitClearances as $u): ?>
                            <?php $formId = "uc_form_" . (int)$u['unit_clearance_id']; ?>
                            <tr>
                                <?php if (in_array($_SESSION['role'] ?? '', ['staff','admin'], true)): ?>
                                    <td>
                                        <input type="text"
                                               name="unit_name"
                                               class="form-control form-control-sm"
                                               value="<?php echo h($u['unit_name']); ?>"
                                               required
                                               form="<?php echo h($formId); ?>">
                                    </td>
                                    <td>
                                        <input type="date"
                                               name="clearance_date"
                                               class="form-control form-control-sm"
                                               value="<?php echo h($u['clearance_date'] ?? date('Y-m-d')); ?>"
                                               form="<?php echo h($formId); ?>">
                                    </td>
                                    <td class="text-nowrap">
                                        <div class="d-flex gap-1">
                                            <form id="<?php echo h($formId); ?>" method="post" class="d-inline">
                                                <input type="hidden" name="action" value="staff_update_unit_clearance">
                                                <input type="hidden" name="unit_clearance_id" value="<?php echo (int)$u['unit_clearance_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Save">
                                                    <i class="fas fa-save"></i>
                                                </button>
                                            </form>

                                            <form method="post" onsubmit="return confirm('Delete this unit clearance?');" class="d-inline">
                                                <input type="hidden" name="action" value="staff_delete_unit_clearance">
                                                <input type="hidden" name="unit_clearance_id" value="<?php echo (int)$u['unit_clearance_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                <?php else: ?>
                                    <td class="fw-semibold"><?php echo h($u['unit_name']); ?></td>
                                    <td><?php echo h($u['clearance_date'] ?? '-'); ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true) && $clearance): ?>
                <hr>
                <h6 class="mb-2">Staff: Add Unit Clearance</h6>
                <form method="post" class="row g-2">
                    <input type="hidden" name="action" value="staff_upsert_unit_clearance">
                    <input type="hidden" name="clearance_id" value="<?php echo (int)$clearance['clearance_id']; ?>">

                    <div class="col-md-4">
                        <input type="text" name="unit_name" class="form-control" placeholder="Unit name (e.g. Library)" required>
                    </div>

                    <div class="col-md-4">
                        <input type="date" name="clearance_date" class="form-control" value="<?php echo h(date('Y-m-d')); ?>">
                    </div>

                    <div class="col-md-4">
                        <button class="btn btn-outline-dark">
                            <i class="fas fa-plus me-1"></i> Add
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Exit Visa Actions -->
    <div class="card mb-5">
        <div class="card-header fw-semibold">Exit Visa Actions</div>
        <div class="card-body">
            <?php if (!$exitCase): ?>
                <div class="text-muted">Submit an exit request first.</div>
            <?php elseif (!$visaActions): ?>
                <div class="text-muted">No exit visa actions recorded yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Action Type</th>
                            <th>Action Date</th>
                            <th>Remarks</th>
                            <?php if (in_array($_SESSION['role'] ?? '', ['staff','admin'], true)): ?>
                                <th style="width: 150px;">Actions</th>
                            <?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($visaActions as $a): ?>
                            <?php $formId = "eva_form_" . (int)$a['exit_visa_id']; ?>
                            <tr>
                                <?php if (in_array($_SESSION['role'] ?? '', ['staff','admin'], true)): ?>
                                    <td>
                                        <select name="action_type"
                                                class="form-select form-select-sm"
                                                required
                                                form="<?php echo h($formId); ?>">
                                            <?php foreach ($allowedActionTypes as $opt): ?>
                                                <option value="<?php echo h($opt); ?>" <?php echo (($a['action_type'] ?? '') === $opt) ? 'selected' : ''; ?>>
                                                    <?php echo h($opt); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="date"
                                               name="action_date"
                                               class="form-control form-control-sm"
                                               value="<?php echo h($a['action_date'] ?? date('Y-m-d')); ?>"
                                               form="<?php echo h($formId); ?>">
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="remarks"
                                               class="form-control form-control-sm"
                                               value="<?php echo h($a['remarks'] ?? ''); ?>"
                                               form="<?php echo h($formId); ?>">
                                    </td>
                                    <td class="text-nowrap">
                                        <div class="d-flex gap-1">
                                            <form id="<?php echo h($formId); ?>" method="post" class="d-inline">
                                                <input type="hidden" name="action" value="staff_update_exit_visa_action">
                                                <input type="hidden" name="exit_visa_id" value="<?php echo (int)$a['exit_visa_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Save">
                                                    <i class="fas fa-save"></i>
                                                </button>
                                            </form>

                                            <form method="post" onsubmit="return confirm('Delete this exit visa action?');" class="d-inline">
                                                <input type="hidden" name="action" value="staff_delete_exit_visa_action">
                                                <input type="hidden" name="exit_visa_id" value="<?php echo (int)$a['exit_visa_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                <?php else: ?>
                                    <td class="fw-semibold"><?php echo h($a['action_type']); ?></td>
                                    <td><?php echo h($a['action_date']); ?></td>
                                    <td><?php echo h($a['remarks'] ?? ''); ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true) && $exitCase): ?>
                <hr>
                <h6 class="mb-2">Staff: Add Exit Visa Action</h6>
                <form method="post" class="row g-2">
                    <input type="hidden" name="action" value="staff_add_exit_visa_action">
                    <input type="hidden" name="exit_id" value="<?php echo (int)$exitCase['exit_id']; ?>">

                    <div class="col-md-3">
                        <select name="action_type" class="form-select" required>
                            <option value="">-- Choose --</option>
                            <?php foreach ($allowedActionTypes as $opt): ?>
                                <option value="<?php echo h($opt); ?>"><?php echo h($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <input type="date" name="action_date" class="form-control" value="<?php echo h(date('Y-m-d')); ?>">
                    </div>

                    <div class="col-md-4">
                        <input type="text" name="remarks" class="form-control" placeholder="Remarks (optional)">
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-outline-primary w-100">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if (in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true) && $exitCase): ?>
                <hr>
                <h6 class="mb-2">Staff: Update Exit Status</h6>
                <form method="post" class="row g-2">
                    <input type="hidden" name="action" value="staff_update_exit_status">
                    <input type="hidden" name="exit_id" value="<?php echo (int)$exitCase['exit_id']; ?>">

                    <div class="col-md-4">
                        <select name="new_status" class="form-select" required>
                            <option value="">-- Choose --</option>
                            <option value="Pending">Pending</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Approved">Approved</option>
                            <option value="Completed">Completed</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <button class="btn btn-outline-dark">
                            <i class="fas fa-sync-alt me-1"></i> Update
                        </button>
                    </div>
                </form>
                <div class="form-text mt-2">
                    Note: Your trigger blocks Approved/Completed if student has pending insurance claims.
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<!-- Add Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<?php require_once __DIR__ . "/footer.php"; ?>
