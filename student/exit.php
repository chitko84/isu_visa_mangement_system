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
// Fetch ALL exit cases for this student (repeating group demo)
// ------------------------------------------------------------
$exitCases = [];
$stmt = $conn->prepare("
    SELECT exit_id, student_id, exit_type, request_date, exit_status
    FROM exit_case
    WHERE student_id = ?
    ORDER BY request_date DESC, exit_id DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$exitCases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ------------------------------------------------------------
// Choose selected exit_id (from ?exit_id=...) or default to latest
// ------------------------------------------------------------
$selectedExitId = null;
if (isset($_GET['exit_id'])) {
    $selectedExitId = (int)$_GET['exit_id'];
}

// If user didn't pass exit_id, default to latest (first row)
if (!$selectedExitId && !empty($exitCases)) {
    $selectedExitId = (int)$exitCases[0]['exit_id'];
}

// Validate selectedExitId belongs to this student
$exitCase = null;
if ($selectedExitId) {
    $stmt = $conn->prepare("
        SELECT exit_id, student_id, exit_type, request_date, exit_status
        FROM exit_case
        WHERE exit_id = ? AND student_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $selectedExitId, $student_id);
    $stmt->execute();
    $exitCase = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$exitCase) {
        // fallback to latest
        $selectedExitId = !empty($exitCases) ? (int)$exitCases[0]['exit_id'] : null;
        $exitCase = !empty($exitCases) ? $exitCases[0] : null;
    }
}


// Decide if student can submit new exit request
// Rule: allow if no exit case OR latest is Completed
$canSubmitExit = true;
if (!empty($exitCases)) {
    $latest = $exitCases[0];
    if (($latest['exit_status'] ?? '') !== 'Completed') {
        $canSubmitExit = false;
    }
}

// ------------------------------------------------------------
// Fetch clearance record (one-to-one with exit_id)
// ------------------------------------------------------------
$clearance = null;
$currentClearanceId = null;

if ($selectedExitId) {
    $stmt = $conn->prepare("
        SELECT clearance_id, exit_id, submission_date, status
        FROM clearance_record
        WHERE exit_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $selectedExitId);
    $stmt->execute();
    $clearance = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $currentClearanceId = $clearance['clearance_id'] ?? null;
}

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
if ($selectedExitId) {
    $stmt = $conn->prepare("
        SELECT exit_visa_id, exit_id, action_type, action_date, remarks
        FROM exit_visa_action
        WHERE exit_id = ?
        ORDER BY action_date DESC, exit_visa_id DESC
    ");
    $stmt->bind_param("i", $selectedExitId);
    $stmt->execute();
    $visaActions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ------------------------------------------------------------
// Handle POST actions
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postExitId = (int)($_POST['exit_id'] ?? 0); // allow forms to pass exit_id explicitly

    try {

        // ---------------------------
        // Student: submit exit request
        // Uses: sp_student_submit_exit_request(IN student_id, IN exit_type, OUT exit_id)
        // ---------------------------
        if ($action === 'submit_exit') {

            if (!$canSubmitExit) {
                throw new Exception("You already have an exit request in progress. You can only submit a new one when the latest case is completed.");
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

            // make newly created exit the selected one
            $selectedExitId = $newExitId;

            $success = "Exit request submitted successfully. Exit ID: {$newExitId}";
        }

        // ---------------------------
        // Staff/Admin: update exit status
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

            // keep selection on the updated exit case
            $selectedExitId = $exit_id;

            $success = "Exit status updated.";
        }

        // ---------------------------
        // Staff/Admin: create clearance record (one per exit case)
        // sp_staff_create_clearance_record(IN exit_id, IN status, OUT clearance_id)
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

            $selectedExitId = $exit_id;

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

            // keep selection based on form exit_id (if provided)
            if ($postExitId > 0) $selectedExitId = $postExitId;

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

            if ($postExitId > 0) $selectedExitId = $postExitId;

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

            if ($postExitId > 0) $selectedExitId = $postExitId;

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

            $selectedExitId = $exit_id;

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

            if ($postExitId > 0) $selectedExitId = $postExitId;

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

            if ($postExitId > 0) $selectedExitId = $postExitId;

            $success = "Exit visa action deleted.";
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
        clearStoredResults($conn);
    }

    // ✅ NO REDIRECT (prevents "headers already sent" error)
    // Re-fetch all data so the page shows updated results immediately.

    // Re-fetch exit cases
    $stmt = $conn->prepare("
        SELECT exit_id, student_id, exit_type, request_date, exit_status
        FROM exit_case
        WHERE student_id = ?
        ORDER BY request_date DESC, exit_id DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $exitCases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Keep selected exit id if possible
    if (!$selectedExitId && !empty($exitCases)) {
        $selectedExitId = (int)$exitCases[0]['exit_id'];
    }

    // Re-resolve selected exit case
    $exitCase = null;
    if ($selectedExitId) {
        foreach ($exitCases as $ec) {
            if ((int)$ec['exit_id'] === (int)$selectedExitId) {
                $exitCase = $ec;
                break;
            }
        }
        if (!$exitCase && !empty($exitCases)) {
            $selectedExitId = (int)$exitCases[0]['exit_id'];
            $exitCase = $exitCases[0];
        }
    }

    // Update submit rule
    $canSubmitExit = true;
    if (!empty($exitCases)) {
        $latest = $exitCases[0];
        if (($latest['exit_status'] ?? '') !== 'Completed') {
            $canSubmitExit = false;
        }
    }

    // Re-fetch clearance
    $clearance = null;
    $currentClearanceId = null;

    if ($selectedExitId) {
        $stmt = $conn->prepare("
            SELECT clearance_id, exit_id, submission_date, status
            FROM clearance_record
            WHERE exit_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $selectedExitId);
        $stmt->execute();
        $clearance = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $currentClearanceId = $clearance['clearance_id'] ?? null;
    }

    // Re-fetch unit clearances
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

    // Re-fetch exit visa actions
    $visaActions = [];
    if ($selectedExitId) {
        $stmt = $conn->prepare("
            SELECT exit_visa_id, exit_id, action_type, action_date, remarks
            FROM exit_visa_action
            WHERE exit_id = ?
            ORDER BY action_date DESC, exit_visa_id DESC
        ");
        $stmt->bind_param("i", $selectedExitId);
        $stmt->execute();
        $visaActions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
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

    <!-- Exit Case History (Repeating Group Demo) -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Exit Case History (Repeating Group)</div>
        <div class="card-body">
            <?php if (!$exitCases): ?>
                <div class="text-muted">No exit cases yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Exit ID</th>
                            <th>Exit Type</th>
                            <th>Request Date</th>
                            <th>Status</th>
                            <th style="width: 140px;">View</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($exitCases as $ec): ?>
                            <?php $isSelected = ((int)$ec['exit_id'] === (int)$selectedExitId); ?>
                            <tr class="<?php echo $isSelected ? 'table-primary' : ''; ?>">
                                <td><?php echo h($ec['exit_id']); ?></td>
                                <td><?php echo h($ec['exit_type']); ?></td>
                                <td><?php echo h($ec['request_date']); ?></td>
                                <td><span class="badge bg-dark"><?php echo h($ec['exit_status']); ?></span></td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="exit.php?exit_id=<?php echo (int)$ec['exit_id']; ?>">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-text">
                    This table demonstrates the repeating group: a student can have many exit cases.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Submit Exit Request (Student) -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Submit Exit Request</div>
        <div class="card-body">
            <?php if (!$canSubmitExit && !empty($exitCases)): ?>
                <div class="alert alert-info mb-0">
                    You already have an exit request in progress (Latest Exit ID: <strong><?php echo h($exitCases[0]['exit_id']); ?></strong>,
                    Status: <strong><?php echo h($exitCases[0]['exit_status']); ?></strong>).
                    You can submit a new request only when the latest case is <strong>Completed</strong>.
                </div>
            <?php else: ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="submit_exit">

                    <div class="col-md-6">
                        <label class="form-label">Exit Type</label>
                        <select name="exit_type" class="form-select" required>
                            <option value="">-- Choose exit type --</option>
                            <?php foreach ($allowedExitTypes as $t): ?>
                                <option value="<?php echo h($t); ?>"><?php echo h($t); ?></option>
                            <?php endforeach; ?>
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

    <!-- Selected Exit Case Details -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">
            Selected Exit Case Details
        </div>
        <div class="card-body">
            <?php if (!$exitCase): ?>
                <div class="text-muted">Select an exit case from the history table above.</div>
            <?php else: ?>
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
            <?php endif; ?>
        </div>
    </div>

    <!-- Clearance Record -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Clearance Record (per Exit Case)</div>
        <div class="card-body">
            <?php if (!$exitCase): ?>
                <div class="text-muted">Select an exit case first.</div>
            <?php elseif (!$clearance): ?>
                <div class="text-muted">No clearance record created yet for this exit case.</div>
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

    <!-- Unit Clearance -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Unit Clearance (Repeating Group)</div>
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
                                                <input type="hidden" name="exit_id" value="<?php echo (int)$exitCase['exit_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Save">
                                                    <i class="fas fa-save"></i>
                                                </button>
                                            </form>

                                            <form method="post" onsubmit="return confirm('Delete this unit clearance?');" class="d-inline">
                                                <input type="hidden" name="action" value="staff_delete_unit_clearance">
                                                <input type="hidden" name="unit_clearance_id" value="<?php echo (int)$u['unit_clearance_id']; ?>">
                                                <input type="hidden" name="exit_id" value="<?php echo (int)$exitCase['exit_id']; ?>">
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
                <div class="form-text">
                    This table demonstrates the repeating group: multiple unit clearances under one clearance record.
                </div>
            <?php endif; ?>

            <?php if (in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true) && $clearance): ?>
                <hr>
                <h6 class="mb-2">Staff: Add Unit Clearance</h6>
                <form method="post" class="row g-2">
                    <input type="hidden" name="action" value="staff_upsert_unit_clearance">
                    <input type="hidden" name="clearance_id" value="<?php echo (int)$clearance['clearance_id']; ?>">
                    <input type="hidden" name="exit_id" value="<?php echo (int)$exitCase['exit_id']; ?>">

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
        <div class="card-header fw-semibold">Exit Visa Actions (Repeating Group)</div>
        <div class="card-body">
            <?php if (!$exitCase): ?>
                <div class="text-muted">Select an exit case first.</div>
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
                                                <input type="hidden" name="exit_id" value="<?php echo (int)$exitCase['exit_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Save">
                                                    <i class="fas fa-save"></i>
                                                </button>
                                            </form>

                                            <form method="post" onsubmit="return confirm('Delete this exit visa action?');" class="d-inline">
                                                <input type="hidden" name="action" value="staff_delete_exit_visa_action">
                                                <input type="hidden" name="exit_visa_id" value="<?php echo (int)$a['exit_visa_id']; ?>">
                                                <input type="hidden" name="exit_id" value="<?php echo (int)$exitCase['exit_id']; ?>">
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
                <div class="form-text">
                    This table demonstrates the repeating group: multiple exit visa actions under one exit case.
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
            <?php endif; ?>

        </div>
    </div>

</div>

<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<?php require_once __DIR__ . "/footer.php"; ?>
