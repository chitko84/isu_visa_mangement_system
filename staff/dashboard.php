<?php
// staff/dashboard.php
$page_title = "Staff Dashboard - ISU";
require_once __DIR__ . "/header.php"; // session + db ($conn) + $staff_id + $role

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function clearStoredResults(mysqli $conn): void {
    while ($conn->more_results()) {
        $conn->next_result();
        if ($res = $conn->store_result()) { $res->free(); }
    }
}

function fmtDate($d): string {
    if (!$d) return "-";
    $t = strtotime((string)$d);
    return $t ? date("d M Y", $t) : (string)$d;
}

function daysUntil($d): ?int {
    if (!$d) return null;
    $t = strtotime((string)$d);
    if (!$t) return null;
    return (int)floor(($t - time()) / 86400);
}

function badgeDays(?int $days): array {
    if ($days === null) return ["secondary", "Unknown"];
    if ($days < 0)      return ["danger", "Expired"];
    if ($days <= 30)    return ["warning", $days . " days"];
    return ["success", $days . " days"];
}

$success = trim($_GET['msg'] ?? '');
$error   = trim($_GET['error'] ?? '');

// ------------------------------------------------------------
// Optional: Run visa reminder generation (admin only)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_visa_reminders') {
    try {
        require_csrf();
        if (($role ?? 'staff') !== 'admin') {
            throw new RuntimeException("Only admin can run reminders.");
        }

        $stmt = $conn->prepare("CALL sp_generate_visa_expiry_reminders()");
        if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
        if (!$stmt->execute()) throw new RuntimeException("Execute failed: " . $stmt->error);
        $stmt->close();
        clearStoredResults($conn);

        $success = "Visa expiry reminders generated successfully.";
        log_audit($conn, 'ran_visa_expiry_reminders', 'reminder_queue', null, 'Generated visa expiry reminders.');
    } catch (Throwable $e) {
        $error = "Failed to run reminders: " . $e->getMessage();
        clearStoredResults($conn);
    }
}

// ------------------------------------------------------------
// Summary counts
// ------------------------------------------------------------
$counts = [
    'students_total' => 0,
    'visa_renewal_pending' => 0,
    'claims_pending' => 0,
    'exit_pending' => 0,
    'notifications_total' => 0,
];

// Small lists (top items)
$visaExpRows   = [];
$insExpRows    = [];
$passportRows  = [];
$exitRows      = [];

try {
    // Total students
    $res = $conn->query("SELECT COUNT(*) AS c FROM student");
    if ($res) $counts['students_total'] = (int)($res->fetch_assoc()['c'] ?? 0);

    // Pending visa renewals (active cases)
    $res = $conn->query("SELECT COUNT(*) AS c FROM visa_renewal_application WHERE status <> 'Passport collected'");
    if ($res) $counts['visa_renewal_pending'] = (int)($res->fetch_assoc()['c'] ?? 0);

    // Pending insurance claims
    $res = $conn->query("SELECT COUNT(*) AS c FROM insurance_claim WHERE claim_status = 'Pending'");
    if ($res) $counts['claims_pending'] = (int)($res->fetch_assoc()['c'] ?? 0);

    // Pending exit cases
    $res = $conn->query("SELECT COUNT(*) AS c FROM exit_case WHERE exit_status IN ('Pending','In Progress')");
    if ($res) $counts['exit_pending'] = (int)($res->fetch_assoc()['c'] ?? 0);

    // Notifications count (student notifications table)
    $res = $conn->query("SELECT COUNT(*) AS c FROM notifications");
    if ($res) $counts['notifications_total'] = (int)($res->fetch_assoc()['c'] ?? 0);

} catch (Throwable $e) {
    $error = $error ?: ("Dashboard count error: " . $e->getMessage());
}

// ------------------------------------------------------------
// Reports (use procedures you listed)
// ------------------------------------------------------------
try {
    // Visas expiring next 3 months
    $stmt = $conn->prepare("CALL sp_report_visas_expiring_next_3_months()");
    if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) { $visaExpRows = $res->fetch_all(MYSQLI_ASSOC); $res->free(); }
    $stmt->close();
    clearStoredResults($conn);

    // Insurance expiring next 3 months
    $stmt = $conn->prepare("CALL sp_report_insurance_expiring_next_3_months()");
    if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) { $insExpRows = $res->fetch_all(MYSQLI_ASSOC); $res->free(); }
    $stmt->close();
    clearStoredResults($conn);

    // Passport ready to collect
    $stmt = $conn->prepare("CALL sp_report_passport_ready_to_collect()");
    if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) { $passportRows = $res->fetch_all(MYSQLI_ASSOC); $res->free(); }
    $stmt->close();
    clearStoredResults($conn);

    // Pending exit cases (procedure exists in your DB)
    $stmt = $conn->prepare("CALL sp_report_pending_exit_cases()");
    if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) { $exitRows = $res->fetch_all(MYSQLI_ASSOC); $res->free(); }
    $stmt->close();
    clearStoredResults($conn);

} catch (Throwable $e) {
    $error = $error ?: ("Dashboard report error: " . $e->getMessage());
    clearStoredResults($conn);
}

// Limit lists on dashboard (top 5)
$visaExpTop    = array_slice($visaExpRows, 0, 5);
$insExpTop     = array_slice($insExpRows, 0, 5);
$passportTop   = array_slice($passportRows, 0, 5);
$exitTop       = array_slice($exitRows, 0, 5);

// Counts from procedures
$visaExpCount   = count($visaExpRows);
$insExpCount    = count($insExpRows);
$passportCount  = count($passportRows);
$exitCount      = count($exitRows);

// ------------------------------------------------------------
// Chart data
// ------------------------------------------------------------
$chart = [
    'student_status_labels' => [],
    'student_status_values' => [],
    'visa_bucket_labels' => ['Expired', '0-30 days', '31-60 days', '61-90 days'],
    'visa_bucket_values' => [0, 0, 0, 0],
    'ins_bucket_labels'  => ['Expired', '0-30 days', '31-60 days', '61-90 days'],
    'ins_bucket_values'  => [0, 0, 0, 0],
];

try {
    // Students by status
    $res = $conn->query("
        SELECT status, COUNT(*) AS c
        FROM student
        GROUP BY status
        ORDER BY c DESC
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $chart['student_status_labels'][] = (string)$row['status'];
            $chart['student_status_values'][] = (int)$row['c'];
        }
    }

    // Visa expiry buckets (all visa records)
    $res = $conn->query("SELECT expiry_date FROM student_visa");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $d = $row['expiry_date'] ?? null;
            if (!$d) continue;

            $days = daysUntil($d);
            if ($days === null) continue;

            if ($days < 0) $chart['visa_bucket_values'][0]++;
            elseif ($days <= 30) $chart['visa_bucket_values'][1]++;
            elseif ($days <= 60) $chart['visa_bucket_values'][2]++;
            elseif ($days <= 90) $chart['visa_bucket_values'][3]++;
        }
    }

    // Insurance expiry buckets (all policies)
    $res = $conn->query("SELECT end_date FROM insurance_policy");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $d = $row['end_date'] ?? null;
            if (!$d) continue;

            $days = daysUntil($d);
            if ($days === null) continue;

            if ($days < 0) $chart['ins_bucket_values'][0]++;
            elseif ($days <= 30) $chart['ins_bucket_values'][1]++;
            elseif ($days <= 60) $chart['ins_bucket_values'][2]++;
            elseif ($days <= 90) $chart['ins_bucket_values'][3]++;
        }
    }

} catch (Throwable $e) {
    $error = $error ?: ("Chart data error: " . $e->getMessage());
}
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">Staff Dashboard</h3>
            <div class="text-muted">Overview of students, visas, insurance, renewals, and exit requests</div>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <?php if (($role ?? 'staff') === 'admin'): ?>
                <form method="post" class="m-0" onsubmit="return confirm('Run visa expiry reminders now?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="run_visa_reminders">
                    <button class="btn btn-outline-primary">
                        <i class="bi bi-bell"></i> Run Visa Reminders
                    </button>
                </form>
            <?php endif; ?>

            <a href="reports.php" class="btn btn-primary">
                <i class="bi bi-graph-up"></i> Open Reports
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- Summary cards -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Total Students</div>
                    <div class="fs-3 fw-bold"><?php echo (int)$counts['students_total']; ?></div>
                    <a class="small" href="students.php">View students</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Visa renewals (active)</div>
                    <div class="fs-3 fw-bold"><?php echo (int)$counts['visa_renewal_pending']; ?></div>
                    <a class="small" href="visa_renewal.php">Open visa renewals</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Pending insurance claims</div>
                    <div class="fs-3 fw-bold"><?php echo (int)$counts['claims_pending']; ?></div>
                    <a class="small" href="insurance_management.php">Open insurance</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Exit requests (Pending/In Progress)</div>
                    <div class="fs-3 fw-bold"><?php echo (int)$counts['exit_pending']; ?></div>
                    <a class="small" href="exit_management.php">Open exit management</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Students by Status</strong></div>
                <div class="card-body">
                    <canvas id="chartStudentsStatus" height="180"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Visa Expiry Buckets</strong></div>
                <div class="card-body">
                    <canvas id="chartVisaBuckets" height="180"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Insurance Expiry Buckets</strong></div>
                <div class="card-body">
                    <canvas id="chartInsBuckets" height="180"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Report widgets -->
    <div class="row g-3">
        <!-- Visa expiry -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Visas expiring (next 3 months)</strong>
                    <span class="badge bg-dark"><?php echo (int)$visaExpCount; ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (!$visaExpTop): ?>
                        <div class="p-3 text-muted">No records found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Email</th>
                                    <th>Expiry</th>
                                    <th>Days</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($visaExpTop as $r): ?>
                                    <?php
                                    $name = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
                                    $exp  = $r['expiry_date'] ?? null;
                                    $days = daysUntil($exp);
                                    [$cls,$txt] = badgeDays($days);
                                    ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo h($name ?: ('Student '.$r['student_id'])); ?></td>
                                        <td><?php echo h($r['email'] ?? '-'); ?></td>
                                        <td><?php echo h(fmtDate($exp)); ?></td>
                                        <td><span class="badge bg-<?php echo h($cls); ?>"><?php echo h($txt); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-2 border-top bg-white text-end">
                            <a class="btn btn-sm btn-outline-primary" href="reports.php?tab=visa_expiry">View full report</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Insurance expiry -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Insurance expiring (next 3 months)</strong>
                    <span class="badge bg-dark"><?php echo (int)$insExpCount; ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (!$insExpTop): ?>
                        <div class="p-3 text-muted">No records found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Provider</th>
                                    <th>End</th>
                                    <th>Days</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($insExpTop as $r): ?>
                                    <?php
                                    $name = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
                                    $end  = $r['end_date'] ?? null;
                                    $days = daysUntil($end);
                                    [$cls,$txt] = badgeDays($days);
                                    ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo h($name ?: ('Student '.$r['student_id'])); ?></td>
                                        <td><?php echo h($r['provider_name'] ?? '-'); ?></td>
                                        <td><?php echo h(fmtDate($end)); ?></td>
                                        <td><span class="badge bg-<?php echo h($cls); ?>"><?php echo h($txt); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-2 border-top bg-white text-end">
                            <a class="btn btn-sm btn-outline-primary" href="reports.php?tab=insurance_expiry">View full report</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Passport ready -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Passport ready to collect</strong>
                    <span class="badge bg-dark"><?php echo (int)$passportCount; ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (!$passportTop): ?>
                        <div class="p-3 text-muted">No records found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Email</th>
                                    <th>Latest Stage</th>
                                    <th>Updated</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($passportTop as $r): ?>
                                    <?php
                                    $name  = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
                                    $stage = $r['latest_stage'] ?? '-';
                                    $upd   = $r['stage_updated_date'] ?? null;
                                    ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo h($name ?: ('Student '.$r['student_id'])); ?></td>
                                        <td><?php echo h($r['email'] ?? '-'); ?></td>
                                        <td><?php echo h($stage); ?></td>
                                        <td><?php echo h($upd ? fmtDate($upd) : "-"); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-2 border-top bg-white text-end">
                            <a class="btn btn-sm btn-outline-primary" href="reports.php?tab=passport_ready">View full report</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Exit cases -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Pending exit cases</strong>
                    <span class="badge bg-dark"><?php echo (int)$exitCount; ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (!$exitTop): ?>
                        <div class="p-3 text-muted">No records found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Request</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($exitTop as $r): ?>
                                    <?php $name = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')); ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo h($name ?: ('Student '.$r['student_id'])); ?></td>
                                        <td><?php echo h($r['email'] ?? '-'); ?></td>
                                        <td><?php echo h($r['exit_type'] ?? '-'); ?></td>
                                        <td><?php echo h(fmtDate($r['request_date'] ?? null)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-2 border-top bg-white text-end">
                            <a class="btn btn-sm btn-outline-primary" href="exit_management.php">Open exit management</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const studentStatusLabels = <?php echo json_encode($chart['student_status_labels']); ?>;
  const studentStatusValues = <?php echo json_encode($chart['student_status_values']); ?>;

  const visaBucketLabels = <?php echo json_encode($chart['visa_bucket_labels']); ?>;
  const visaBucketValues = <?php echo json_encode($chart['visa_bucket_values']); ?>;

  const insBucketLabels = <?php echo json_encode($chart['ins_bucket_labels']); ?>;
  const insBucketValues = <?php echo json_encode($chart['ins_bucket_values']); ?>;

  // Students by status (bar)
  const ctx1 = document.getElementById('chartStudentsStatus');
  if (ctx1) {
    new Chart(ctx1, {
      type: 'bar',
      data: {
        labels: studentStatusLabels,
        datasets: [{
          label: 'Students',
          data: studentStatusValues
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  // Visa expiry buckets (doughnut)
  const ctx2 = document.getElementById('chartVisaBuckets');
  if (ctx2) {
    new Chart(ctx2, {
      type: 'doughnut',
      data: {
        labels: visaBucketLabels,
        datasets: [{
          label: 'Visas',
          data: visaBucketValues
        }]
      },
      options: { responsive: true }
    });
  }

  // Insurance expiry buckets (doughnut)
  const ctx3 = document.getElementById('chartInsBuckets');
  if (ctx3) {
    new Chart(ctx3, {
      type: 'doughnut',
      data: {
        labels: insBucketLabels,
        datasets: [{
          label: 'Policies',
          data: insBucketValues
        }]
      },
      options: { responsive: true }
    });
  }
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
