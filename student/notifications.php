<?php
// student/notifications.php

$page_title = "Notifications & Reminders - ISU Student Portal";
require_once __DIR__ . "/header.php"; // session + db ($conn) + $student_id

// ------------------------------------------------------------
// Helpers (important for stored procedures)
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
// Fetch latest visa (for visa expiry info)
// ------------------------------------------------------------
$visa = null;
$stmt = $conn->prepare("
    SELECT visa_id, visa_type, passport_no, issue_date, expiry_date, status
    FROM student_visa
    WHERE student_id = ?
    ORDER BY expiry_date DESC, visa_id DESC
    LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$visa = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ------------------------------------------------------------
// Fetch latest insurance policy (for insurance expiry info)
// ------------------------------------------------------------
$policy = null;
$stmt = $conn->prepare("
    SELECT policy_id, provider_id, policy_number, start_date, end_date, coverage_type, status
    FROM insurance_policy
    WHERE student_id = ?
    ORDER BY end_date DESC, policy_id DESC
    LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$policy = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ------------------------------------------------------------
// Fetch latest visa renewal application (pending actions)
// ------------------------------------------------------------
$latestApp = null;
$stmt = $conn->prepare("
    SELECT application_id, submission_date, requested_months, status
    FROM visa_renewal_application
    WHERE student_id = ?
    ORDER BY submission_date DESC, application_id DESC
    LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$latestApp = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ------------------------------------------------------------
// Reports (from stored procedures)
// - These are global lists (for staff/admin), but we will filter
//   to the current student for student view.
// ------------------------------------------------------------
$reportVisaExpiring = [];
$reportInsuranceExpiring = [];
$reportPassportReady = [];

// ------------------------------------------------------------
// Handle actions
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {

        // --------------------------------------------------------
        // Generate reminders (sp_generate_visa_expiry_reminders)
        // IMPORTANT: This procedure inserts into reminder_queue table.
        // If you don't have reminder_queue in your DB right now,
        // this will fail. We'll show the error clearly.
        // --------------------------------------------------------
        if ($action === 'generate_visa_expiry_reminders') {
            if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
                throw new Exception("Unauthorized. Only staff/admin can run reminder generation.");
            }

            $stmt = $conn->prepare("CALL sp_generate_visa_expiry_reminders()");
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $success = "Visa expiry reminders generation executed.";
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
        clearStoredResults($conn);
    }
}

// ------------------------------------------------------------
// Load reports via procedures
// NOTE: These procedures return lists for ALL students.
// We'll filter to current student when displaying (student view).
// Staff/Admin can see full list.
// ------------------------------------------------------------
try {
    // Visas expiring next 3 months
    $stmt = $conn->prepare("CALL sp_report_visas_expiring_next_3_months()");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $reportVisaExpiring = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
    }
    $stmt->close();
    clearStoredResults($conn);

    // Insurance expiring next 3 months
    $stmt = $conn->prepare("CALL sp_report_insurance_expiring_next_3_months()");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $reportInsuranceExpiring = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
    }
    $stmt->close();
    clearStoredResults($conn);

    // Passport ready to collect
    $stmt = $conn->prepare("CALL sp_report_passport_ready_to_collect()");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $reportPassportReady = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
    }
    $stmt->close();
    clearStoredResults($conn);

} catch (Throwable $e) {
    // If these fail, it's okay; show at top.
    $error = $error ?: $e->getMessage();
    clearStoredResults($conn);
}

// ------------------------------------------------------------
// Filter report rows for student view
// ------------------------------------------------------------
$isStaff = in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true);

$myVisaExpiring = array_values(array_filter($reportVisaExpiring, function ($row) use ($student_id) {
    return (int)($row['student_id'] ?? 0) === (int)$student_id;
}));

$myInsuranceExpiring = array_values(array_filter($reportInsuranceExpiring, function ($row) use ($student_id) {
    return (int)($row['student_id'] ?? 0) === (int)$student_id;
}));

$myPassportReady = array_values(array_filter($reportPassportReady, function ($row) use ($student_id) {
    return (int)($row['student_id'] ?? 0) === (int)$student_id;
}));

// Simple local notifications list (based on the student's data)
$notifications = [];

// Visa expiry notification (local)
if ($visa && !empty($visa['expiry_date'])) {
    $today = new DateTime(date('Y-m-d'));
    $exp   = new DateTime($visa['expiry_date']);
    $diff  = (int)$today->diff($exp)->format('%r%a'); // negative if expired

    if ($diff < 0) {
        $notifications[] = [
            'type' => 'Visa',
            'level' => 'danger',
            'title' => 'Visa expired',
            'message' => "Your visa expired on {$visa['expiry_date']}. Please contact ISSU immediately."
        ];
    } elseif ($diff <= 90) {
        $notifications[] = [
            'type' => 'Visa',
            'level' => 'warning',
            'title' => 'Visa expiring soon',
            'message' => "Your visa will expire on {$visa['expiry_date']} (within 3 months). Please prepare renewal."
        ];
    } else {
        $notifications[] = [
            'type' => 'Visa',
            'level' => 'success',
            'title' => 'Visa status OK',
            'message' => "Your visa expiry date is {$visa['expiry_date']}."
        ];
    }
}

// Insurance expiry notification (local)
if ($policy && !empty($policy['end_date'])) {
    $today = new DateTime(date('Y-m-d'));
    $end   = new DateTime($policy['end_date']);
    $diff  = (int)$today->diff($end)->format('%r%a');

    if ($diff < 0) {
        $notifications[] = [
            'type' => 'Insurance',
            'level' => 'danger',
            'title' => 'Insurance expired',
            'message' => "Your insurance expired on {$policy['end_date']}. Please renew as soon as possible."
        ];
    } elseif ($diff <= 90) {
        $notifications[] = [
            'type' => 'Insurance',
            'level' => 'warning',
            'title' => 'Insurance expiring soon',
            'message' => "Your insurance will expire on {$policy['end_date']} (within 3 months). Please submit renewal."
        ];
    } else {
        $notifications[] = [
            'type' => 'Insurance',
            'level' => 'success',
            'title' => 'Insurance status OK',
            'message' => "Your insurance end date is {$policy['end_date']}."
        ];
    }
}

// Visa renewal application notification (local)
if ($latestApp) {
    $appStatus = $latestApp['status'] ?? '';
    if ($appStatus !== 'Passport collected') {
        $notifications[] = [
            'type' => 'Visa Renewal',
            'level' => 'info',
            'title' => 'Visa renewal application in progress',
            'message' => "Your latest renewal application is currently: {$appStatus} (Application ID: {$latestApp['application_id']})."
        ];
    } else {
        $notifications[] = [
            'type' => 'Visa Renewal',
            'level' => 'success',
            'title' => 'Passport collected',
            'message' => "Your latest renewal application is completed (Passport collected)."
        ];
    }
}
?>

<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="mb-0">Notifications & Reminders</h2>
            <div class="text-muted">Visa expiry, insurance expiry, and visa renewal actions.</div>
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

    <!-- My Notifications -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">My Notifications</div>
        <div class="card-body">
            <?php if (!$notifications): ?>
                <div class="text-muted">No notifications right now.</div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($notifications as $n): ?>
                        <div class="col-md-6">
                            <div class="alert alert-<?php echo h($n['level']); ?> mb-0">
                                <div class="fw-semibold"><?php echo h($n['title']); ?></div>
                                <div><?php echo h($n['message']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Visa Expiry (from report, filtered) -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Visa Expiring (Next 3 Months)</div>
        <div class="card-body">
            <?php $rows = $isStaff ? $reportVisaExpiring : $myVisaExpiring; ?>
            <?php if (!$rows): ?>
                <div class="text-muted">No records found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Visa Type</th>
                            <th>Passport No</th>
                            <th>Expiry Date</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo h($r['student_id']); ?></td>
                                <td><?php echo h(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?></td>
                                <td><?php echo h($r['email'] ?? ''); ?></td>
                                <td><?php echo h($r['phone'] ?? ''); ?></td>
                                <td><?php echo h($r['visa_type'] ?? ''); ?></td>
                                <td><?php echo h($r['passport_no'] ?? ''); ?></td>
                                <td><?php echo h($r['expiry_date'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!$isStaff): ?>
                    <div class="form-text mt-2">
                        This list is filtered to show only your records.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Insurance Expiry (from report, filtered) -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Insurance Expiring (Next 3 Months)</div>
        <div class="card-body">
            <?php $rows = $isStaff ? $reportInsuranceExpiring : $myInsuranceExpiring; ?>
            <?php if (!$rows): ?>
                <div class="text-muted">No records found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Policy No</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Provider</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo h($r['student_id']); ?></td>
                                <td><?php echo h(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?></td>
                                <td><?php echo h($r['email'] ?? ''); ?></td>
                                <td><?php echo h($r['phone'] ?? ''); ?></td>
                                <td><?php echo h($r['policy_number'] ?? ''); ?></td>
                                <td><?php echo h($r['end_date'] ?? ''); ?></td>
                                <td><span class="badge bg-dark"><?php echo h($r['status'] ?? ''); ?></span></td>
                                <td><?php echo h($r['provider_name'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!$isStaff): ?>
                    <div class="form-text mt-2">
                        This list is filtered to show only your records.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Passport Ready to Collect (from report, filtered) -->
    <div class="card mb-5">
        <div class="card-header fw-semibold">Passport Ready to Collect</div>
        <div class="card-body">
            <?php $rows = $isStaff ? $reportPassportReady : $myPassportReady; ?>
            <?php if (!$rows): ?>
                <div class="text-muted">No records found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Application ID</th>
                            <th>Submission Date</th>
                            <th>Application Status</th>
                            <th>Latest Stage</th>
                            <th>Stage Updated</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo h($r['student_id']); ?></td>
                                <td><?php echo h(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?></td>
                                <td><?php echo h($r['email'] ?? ''); ?></td>
                                <td><?php echo h($r['phone'] ?? ''); ?></td>
                                <td><?php echo h($r['application_id'] ?? ''); ?></td>
                                <td><?php echo h($r['submission_date'] ?? ''); ?></td>
                                <td><?php echo h($r['application_status'] ?? ''); ?></td>
                                <td><?php echo h($r['latest_stage'] ?? ''); ?></td>
                                <td><?php echo h($r['stage_updated_date'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!$isStaff): ?>
                    <div class="form-text mt-2">
                        This list is filtered to show only your records.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Staff: Generate reminders -->
    <?php if ($isStaff): ?>
        <div class="card mb-5">
            <div class="card-header fw-semibold">Staff Tools</div>
            <div class="card-body">
                <form method="post" class="row g-2">
                    <input type="hidden" name="action" value="generate_visa_expiry_reminders">
                    <div class="col-md-6">
                        <div class="text-muted">
                            This runs <strong>sp_generate_visa_expiry_reminders()</strong> which inserts into <strong>reminder_queue</strong>.
                            Make sure the reminder_queue table exists in your database.
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary w-100">Run Reminder Generation</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . "/footer.php"; ?>
