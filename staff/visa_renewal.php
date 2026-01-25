<?php
// staff/visa_renewal.php
// Visa Renewal Processing (Staff)

if (session_status() === PHP_SESSION_NONE) session_start();

// Auth
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['staff','admin'], true)) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../includes/db.php';

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function fmtDate($d): string {
    if (!$d) return "-";
    $t = strtotime((string)$d);
    return $t ? date("d M Y", $t) : (string)$d;
}

// IMPORTANT for stored procedures
function clearStoredResults(mysqli $conn): void {
    while ($conn->more_results()) {
        $conn->next_result();
        if ($res = $conn->store_result()) $res->free();
    }
}

// Option A: safe redirect (header if possible; JS/meta fallback if output started)
function redirectTo(string $url): void {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    }
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . h($url) . '"></noscript>';
    exit();
}

function buildUrl(array $overrides = []): string {
    $q = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($q[$k]);
        else $q[$k] = $v;
    }
    $qs = http_build_query($q);
    return $qs ? ("visa_renewal.php?" . $qs) : "visa_renewal.php";
}

function normalizeStage(string $s): string {
    return trim(preg_replace('/\s+/', ' ', $s));
}

function stageBadge(string $stage): array {
    $s = strtolower($stage);
    if ($s === 'pending') return ['secondary', 'Pending'];
    if (str_contains($s, 'submitted passport')) return ['warning', $stage];
    if (str_contains($s, 'approved')) return ['success', $stage];
    if (str_contains($s, 'rejected')) return ['danger', $stage];
    if (str_contains($s, 'passport collected')) return ['primary', $stage];
    return ['info', $stage ?: '—'];
}

function safeDocUrl(string $path): string {
    $p = trim($path);
    $p = str_replace("\\", "/", $p);
    if (str_starts_with($p, "../")) $p = substr($p, 3);
    $p = ltrim($p, "/");
    return "../" . $p;
}

// ------------------------------------------------------------
// Status rules (must match your CHECK constraint)
// visa_renewal_application.status: ('Pending','Submitted passport to ISSU','Passport collected')
// ------------------------------------------------------------
$appStatusOptions = [
    'Pending',
    'Submitted passport to ISSU',
    'Passport collected'
];

// Timeline stage options (now dropdown, not manual text)
$stageOptions = [
    'Pending',
    'Submitted passport to ISSU',
    'In Progress',
    'Approved',
    'Rejected',
    'Passport collected'
];

// ------------------------------------------------------------
// POST actions BEFORE output (avoid headers already sent)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    try {
        if ($action === 'update_app_status') {
            $application_id = (int)($_POST['application_id'] ?? 0);
            $new_status = trim($_POST['new_status'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');

            if ($application_id <= 0) throw new RuntimeException("Invalid application id.");
            if (!in_array($new_status, $appStatusOptions, true)) {
                throw new RuntimeException("Invalid application status (must match system rules).");
            }

            clearStoredResults($conn);

            $stmt = $conn->prepare("CALL sp_staff_update_visa_application_status(?, ?, ?)");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("iss", $application_id, $new_status, $remarks);
            if (!$stmt->execute()) throw new RuntimeException("Execute failed: " . $stmt->error);
            $stmt->close();
            clearStoredResults($conn);

            redirectTo("visa_renewal.php?msg=" . urlencode("Application status updated.") . "&view=" . $application_id);

        } elseif ($action === 'add_stage') {
            $application_id = (int)($_POST['application_id'] ?? 0);
            $stage_name = normalizeStage((string)($_POST['stage_name'] ?? ''));
            $remarks = trim($_POST['remarks'] ?? '');

            if ($application_id <= 0) throw new RuntimeException("Invalid application id.");
            if ($stage_name === "") throw new RuntimeException("Stage name is required.");
            if (!in_array($stage_name, $stageOptions, true)) {
                throw new RuntimeException("Invalid stage name.");
            }

            clearStoredResults($conn);

            $stmt = $conn->prepare("CALL sp_staff_add_visa_renewal_status(?, ?, ?, @o_status_id)");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("iss", $application_id, $stage_name, $remarks);
            if (!$stmt->execute()) throw new RuntimeException("Execute failed: " . $stmt->error);
            $stmt->close();
            clearStoredResults($conn);

            redirectTo("visa_renewal.php?msg=" . urlencode("Timeline stage added.") . "&view=" . $application_id);

        } elseif ($action === 'approve_and_update_visa') {
            $application_id = (int)($_POST['application_id'] ?? 0);
            $visa_id = (int)($_POST['visa_id'] ?? 0);
            $student_id = (int)($_POST['student_id'] ?? 0);
            $visa_type = trim($_POST['visa_type'] ?? 'Student Pass');
            $passport_no = trim($_POST['passport_no'] ?? '');
            $issue_date = trim($_POST['issue_date'] ?? '');
            $expiry_date = trim($_POST['expiry_date'] ?? '');
            $set_app_status = trim($_POST['set_app_status'] ?? 'Passport collected');
            $remarks = trim($_POST['remarks'] ?? 'Approved & visa updated by staff');

            if ($application_id <= 0) throw new RuntimeException("Invalid application id.");
            if ($visa_id <= 0) throw new RuntimeException("Invalid visa_id.");
            if ($student_id <= 0) throw new RuntimeException("Invalid student_id.");
            if ($passport_no === "") throw new RuntimeException("Passport no is required.");
            if ($issue_date === "" || $expiry_date === "") throw new RuntimeException("Issue date and expiry date are required.");
            if (!in_array($set_app_status, $appStatusOptions, true)) throw new RuntimeException("Invalid application status.");

            clearStoredResults($conn);

            // 1) Add "Approved" stage (timeline)
            $stmt = $conn->prepare("CALL sp_staff_add_visa_renewal_status(?, 'Approved', ?, @o_status_id)");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("is", $application_id, $remarks);
            if (!$stmt->execute()) throw new RuntimeException("Execute failed: " . $stmt->error);
            $stmt->close();
            clearStoredResults($conn);

            // 2) Update application status (constraint)
            $stmt = $conn->prepare("CALL sp_staff_update_visa_application_status(?, ?, ?)");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("iss", $application_id, $set_app_status, $remarks);
            if (!$stmt->execute()) throw new RuntimeException("Execute failed: " . $stmt->error);
            $stmt->close();
            clearStoredResults($conn);

            // 3) Update student_visa
            $stmt = $conn->prepare("CALL sp_staff_upsert_student_visa(?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("iissss", $visa_id, $student_id, $visa_type, $issue_date, $expiry_date, $passport_no);
            if (!$stmt->execute()) throw new RuntimeException("Execute failed: " . $stmt->error);
            $stmt->close();
            clearStoredResults($conn);

            redirectTo("visa_renewal.php?msg=" . urlencode("Approved: application + visa record updated.") . "&view=" . $application_id);

        } else {
            throw new RuntimeException("Unknown action.");
        }

    } catch (Throwable $e) {
        redirectTo("visa_renewal.php?error=" . urlencode($e->getMessage()) . (isset($_POST['application_id']) ? "&view=".(int)$_POST['application_id'] : ""));
    }
}

// ------------------------------------------------------------
// Now include header (HTML starts)
// ------------------------------------------------------------
$page_title = "Visa Renewal Processing - ISU Staff Portal";
require_once __DIR__ . "/header.php";

// Messages
$success = trim($_GET['msg'] ?? '');
$error   = trim($_GET['error'] ?? '');

// ------------------------------------------------------------
// View / list logic
// ------------------------------------------------------------
$view = (int)($_GET['view'] ?? 0);

// Filters (list)
$q        = trim($_GET['q'] ?? '');
$student  = trim($_GET['student'] ?? '');
$status   = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['from'] ?? '');
$dateTo   = trim($_GET['to'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$listRows = [];
$total = 0;
$totalPages = 1;

$detail = null;
$documents = [];
$timeline = [];
$visa = null;

// ------------------------------------------------------------
// If viewing a single application, load detail + docs + timeline + visa
// ------------------------------------------------------------
if ($view > 0) {
    try {
        $sql = "
            SELECT
              a.application_id, a.student_id, a.submission_date, a.requested_months, a.status,
              s.first_name, s.last_name, s.email, s.phone,
              (SELECT rs.stage_name
                 FROM visa_renewal_status rs
                WHERE rs.application_id = a.application_id
                ORDER BY rs.updated_date DESC, rs.status_id DESC
                LIMIT 1
              ) AS latest_stage,
              (SELECT rs.updated_date
                 FROM visa_renewal_status rs
                WHERE rs.application_id = a.application_id
                ORDER BY rs.updated_date DESC, rs.status_id DESC
                LIMIT 1
              ) AS latest_stage_date
            FROM visa_renewal_application a
            JOIN student s ON s.student_id = a.student_id
            WHERE a.application_id = ?
            LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $view);
        $stmt->execute();
        $detail = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$detail) throw new RuntimeException("Application not found.");

        // Documents
        $stmt = $conn->prepare("
            SELECT document_id, application_id, document_type, document_path, upload_date
            FROM visa_document
            WHERE application_id = ?
            ORDER BY upload_date DESC, document_id DESC
        ");
        $stmt->bind_param("i", $view);
        $stmt->execute();
        $documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Timeline
        $stmt = $conn->prepare("
            SELECT status_id, application_id, stage_name, updated_date, remarks
            FROM visa_renewal_status
            WHERE application_id = ?
            ORDER BY updated_date DESC, status_id DESC
        ");
        $stmt->bind_param("i", $view);
        $stmt->execute();
        $timeline = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Student visa (latest by expiry_date)
        $sid = (int)$detail['student_id'];
        $stmt = $conn->prepare("
            SELECT visa_id, student_id, visa_type, issue_date, expiry_date, status, passport_no
            FROM student_visa
            WHERE student_id = ?
            ORDER BY expiry_date DESC, visa_id DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $sid);
        $stmt->execute();
        $visa = $stmt->get_result()->fetch_assoc();
        $stmt->close();

    } catch (Throwable $e) {
        $error = $error ?: ("Failed to load application: " . $e->getMessage());
    }
}

// ------------------------------------------------------------
// Otherwise load list
// ------------------------------------------------------------
if ($view <= 0) {
    try {
        $where = [];
        $types = "";
        $params = [];

        if ($q !== "") {
            $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR a.application_id LIKE ?)";
            $like = "%" . $q . "%";
            $types .= "ssss";
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }

        if ($student !== "" && ctype_digit($student)) {
            $where[] = "a.student_id = ?";
            $types .= "i";
            $params[] = (int)$student;
        }

        if ($status !== "") {
            if (!in_array($status, $appStatusOptions, true)) throw new RuntimeException("Invalid status filter.");
            $where[] = "a.status = ?";
            $types .= "s";
            $params[] = $status;
        }

        if ($dateFrom !== "") {
            $where[] = "a.submission_date >= ?";
            $types .= "s";
            $params[] = $dateFrom;
        }

        if ($dateTo !== "") {
            $where[] = "a.submission_date <= ?";
            $types .= "s";
            $params[] = $dateTo;
        }

        $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

        // Count
        $sqlCount = "
            SELECT COUNT(*) AS c
            FROM visa_renewal_application a
            JOIN student s ON s.student_id = a.student_id
            $whereSql
        ";
        $stmt = $conn->prepare($sqlCount);
        if ($types !== "") $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);
        $stmt->close();

        $totalPages = max(1, (int)ceil($total / $limit));

        // Page rows
        $sql = "
            SELECT
              a.application_id, a.student_id, a.submission_date, a.requested_months, a.status,
              s.first_name, s.last_name, s.email,
              (SELECT rs.stage_name
                 FROM visa_renewal_status rs
                WHERE rs.application_id = a.application_id
                ORDER BY rs.updated_date DESC, rs.status_id DESC
                LIMIT 1
              ) AS latest_stage,
              (SELECT rs.updated_date
                 FROM visa_renewal_status rs
                WHERE rs.application_id = a.application_id
                ORDER BY rs.updated_date DESC, rs.status_id DESC
                LIMIT 1
              ) AS latest_stage_date,
              (SELECT COUNT(*) FROM visa_document d WHERE d.application_id = a.application_id) AS doc_count
            FROM visa_renewal_application a
            JOIN student s ON s.student_id = a.student_id
            $whereSql
            ORDER BY a.submission_date DESC, a.application_id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $conn->prepare($sql);
        if ($types === "") {
            $stmt->bind_param("ii", $limit, $offset);
        } else {
            $types2 = $types . "ii";
            $params2 = array_merge($params, [$limit, $offset]);
            $stmt->bind_param($types2, ...$params2);
        }

        $stmt->execute();
        $listRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

    } catch (Throwable $e) {
        $error = $error ?: ("Failed to load applications: " . $e->getMessage());
    }
}
?>

<div class="container-fluid">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Visa Renewal Processing</h3>
      <div class="text-muted">Review student applications, view documents, update status timeline, and update visa records</div>
    </div>
    <?php if ($view > 0): ?>
      <a class="btn btn-outline-secondary" href="visa_renewal.php">
        <i class="bi bi-arrow-left"></i> Back to List
      </a>
    <?php endif; ?>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo h($success); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
  <?php endif; ?>

  <?php if ($view <= 0): ?>

    <!-- Filters -->
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white"><strong>Filters</strong></div>
      <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Search (name/email/application id)</label>
            <input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="e.g. Fatima, student@aiu.edu.my, 1">
          </div>

          <div class="col-md-2">
            <label class="form-label">Student ID</label>
            <input class="form-control" name="student" value="<?php echo h($student); ?>" placeholder="e.g. 768967">
          </div>

          <div class="col-md-3">
            <label class="form-label">Application Status</label>
            <select class="form-select" name="status">
              <option value="" <?php echo $status==="" ? "selected" : ""; ?>>All</option>
              <?php foreach ($appStatusOptions as $opt): ?>
                <option value="<?php echo h($opt); ?>" <?php echo $status===$opt ? "selected" : ""; ?>>
                  <?php echo h($opt); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Must match system rules</div>
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
            <a class="btn btn-outline-secondary" href="visa_renewal.php">
              <i class="bi bi-x-circle"></i> Clear
            </a>
          </div>
        </form>
      </div>
    </div>

    <!-- List -->
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>Visa Renewal Applications</strong>
        <span class="text-muted small"><?php echo (int)$total; ?> total</span>
      </div>

      <div class="card-body p-0">
        <?php if (!$listRows): ?>
          <div class="p-4 text-muted">No applications found.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:90px;">App ID</th>
                  <th style="min-width:240px;">Student</th>
                  <th style="min-width:240px;">Email</th>
                  <th style="min-width:140px;">Submitted</th>
                  <th style="min-width:140px;">Requested</th>
                  <th style="min-width:210px;">Application Status</th>
                  <th style="min-width:220px;">Latest Stage</th>
                  <th style="min-width:120px;">Docs</th>
                  <th style="width:110px;">View</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($listRows as $r): ?>
                  <?php
                    $name = trim(($r["first_name"] ?? "")." ".($r["last_name"] ?? ""));
                    [$b, $lbl] = stageBadge((string)($r["latest_stage"] ?? "—"));
                  ?>
                  <tr>
                    <td class="text-muted"><?php echo (int)$r["application_id"]; ?></td>
                    <td>
                      <div class="fw-semibold"><?php echo h($name ?: ("Student ".$r["student_id"])); ?></div>
                      <div class="text-muted small">ID: <?php echo h($r["student_id"]); ?></div>
                    </td>
                    <td><?php echo h($r["email"] ?? "-"); ?></td>
                    <td><?php echo h(fmtDate($r["submission_date"])); ?></td>
                    <td><?php echo h((int)$r["requested_months"] . " months"); ?></td>
                    <td><span class="badge bg-dark"><?php echo h($r["status"]); ?></span></td>
                    <td>
                      <span class="badge bg-<?php echo h($b); ?>"><?php echo h($lbl); ?></span>
                      <div class="text-muted small"><?php echo h(fmtDate($r["latest_stage_date"] ?? null)); ?></div>
                    </td>
                    <td><span class="badge bg-info"><?php echo (int)($r["doc_count"] ?? 0); ?></span></td>
                    <td>
                      <a class="btn btn-sm btn-primary" href="<?php echo h(buildUrl(["view" => (int)$r["application_id"], "page" => null])); ?>">
                        Open
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="p-3 border-top bg-white d-flex justify-content-between align-items-center">
            <div class="text-muted small">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></div>
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

  <?php else: ?>

    <?php if ($detail): ?>
      <?php
        $studentName = trim(($detail["first_name"] ?? "") . " " . ($detail["last_name"] ?? ""));
        [$bb, $bl] = stageBadge((string)($detail["latest_stage"] ?? "—"));
      ?>

      <div class="row g-3">
        <div class="col-lg-7">

          <!-- Application Summary -->
          <div class="card shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
              <strong>Application #<?php echo (int)$detail["application_id"]; ?></strong>
              <span class="badge bg-dark"><?php echo h($detail["status"]); ?></span>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="text-muted small">Student</div>
                  <div class="fw-semibold"><?php echo h($studentName ?: ("Student ".$detail["student_id"])); ?></div>
                  <div class="text-muted small">ID: <?php echo h($detail["student_id"]); ?></div>
                </div>

                <div class="col-md-6">
                  <div class="text-muted small">Email</div>
                  <div><?php echo h($detail["email"] ?? "-"); ?></div>
                  <div class="text-muted small">Phone: <?php echo h($detail["phone"] ?? "-"); ?></div>
                </div>

                <div class="col-md-4">
                  <div class="text-muted small">Submission Date</div>
                  <div><?php echo h(fmtDate($detail["submission_date"])); ?></div>
                </div>

                <div class="col-md-4">
                  <div class="text-muted small">Requested Months</div>
                  <div><?php echo (int)$detail["requested_months"]; ?> months</div>
                </div>

                <div class="col-md-4">
                  <div class="text-muted small">Latest Stage</div>
                  <div>
                    <span class="badge bg-<?php echo h($bb); ?>"><?php echo h($bl); ?></span>
                    <div class="text-muted small"><?php echo h(fmtDate($detail["latest_stage_date"] ?? null)); ?></div>
                  </div>
                </div>
              </div>

              <hr>
            </div>
          </div>

          <!-- Documents -->
          <div class="card shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
              <strong>Supporting Documents</strong>
              <span class="badge bg-info"><?php echo count($documents); ?></span>
            </div>

            <div class="card-body">
              <?php if (!$documents): ?>
                <div class="text-muted">No documents uploaded for this application.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th style="width:90px;">Doc ID</th>
                        <th style="min-width:220px;">Type</th>
                        <th style="min-width:140px;">Upload Date</th>
                        <th style="width:140px;">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($documents as $d): ?>
                        <?php $docUrl = safeDocUrl((string)$d["document_path"]); ?>
                        <tr>
                          <td class="text-muted"><?php echo (int)$d["document_id"]; ?></td>
                          <td class="fw-semibold"><?php echo h($d["document_type"]); ?></td>
                          <td><?php echo h(fmtDate($d["upload_date"])); ?></td>
                          <td>
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo h($docUrl); ?>" target="_blank" rel="noopener">
                              <i class="bi bi-eye"></i> View Document
                            </a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <div class="form-text mt-2">
                  (This staff page is read-only for documents; students manage uploads via student module.)
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Timeline -->
          <div class="card shadow-sm">
            <div class="card-header bg-white">
              <strong>Timeline Stages (visa_renewal_status)</strong>
            </div>
            <div class="card-body">

              <form method="post" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="action" value="add_stage">
                <input type="hidden" name="application_id" value="<?php echo (int)$detail["application_id"]; ?>">

                <div class="col-md-5">
                  <label class="form-label">Stage Name</label>
                  <select class="form-select" name="stage_name" required>
                    <option value="">-- Choose stage --</option>
                    <?php foreach ($stageOptions as $opt): ?>
                      <option value="<?php echo h($opt); ?>"><?php echo h($opt); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-5">
                  <label class="form-label">Remarks</label>
                  <input class="form-control" name="remarks" placeholder="Optional remarks">
                </div>

                <div class="col-md-2 d-grid">
                  <button class="btn btn-outline-primary">
                    <i class="bi bi-plus-circle"></i> Add Stage
                  </button>
                </div>
              </form>

              <?php if (!$timeline): ?>
                <div class="text-muted">No timeline records.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th style="width:90px;">#</th>
                        <th style="min-width:220px;">Stage</th>
                        <th style="min-width:140px;">Date</th>
                        <th>Remarks</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($timeline as $t): ?>
                        <?php [$tb, $tl] = stageBadge((string)$t["stage_name"]); ?>
                        <tr>
                          <td class="text-muted"><?php echo (int)$t["status_id"]; ?></td>
                          <td><span class="badge bg-<?php echo h($tb); ?>"><?php echo h($tl); ?></span></td>
                          <td><?php echo h(fmtDate($t["updated_date"])); ?></td>
                          <td><?php echo h($t["remarks"] ?? "-"); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>

            </div>
          </div>
        </div>

        <!-- Right column -->
        <div class="col-lg-5">

          <div class="card shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
              <strong>Current Visa (student_visa)</strong>
              <?php if ($visa): ?>
                <span class="badge bg-<?php echo ($visa["status"] === "Active" ? "success" : "secondary"); ?>">
                  <?php echo h($visa["status"]); ?>
                </span>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <?php if (!$visa): ?>
                <div class="text-muted">No visa record found for this student.</div>
              <?php else: ?>
                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="text-muted small">Visa ID</div>
                    <div class="fw-semibold"><?php echo h($visa["visa_id"]); ?></div>
                  </div>
                  <div class="col-md-6">
                    <div class="text-muted small">Passport No</div>
                    <div class="fw-semibold"><?php echo h($visa["passport_no"]); ?></div>
                  </div>
                  <div class="col-md-6">
                    <div class="text-muted small">Issue Date</div>
                    <div><?php echo h(fmtDate($visa["issue_date"])); ?></div>
                  </div>
                  <div class="col-md-6">
                    <div class="text-muted small">Expiry Date</div>
                    <div><?php echo h(fmtDate($visa["expiry_date"])); ?></div>
                  </div>
                  <div class="col-12">
                    <div class="text-muted small">Visa Type</div>
                    <div><?php echo h($visa["visa_type"]); ?></div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Approve + Update Visa (Modal confirm) -->
          <div class="card shadow-sm">
            <div class="card-header bg-white">
              <strong>Approve & Update Visa (Helper)</strong>
            </div>
            <div class="card-body">

              <div class="alert alert-info py-2">
                The visa renewal request cannot be marked as “Approved” directly because of system rules.
                <br><br>
                Instead, this action will:
                <ul class="mb-0">
                  <li>Add “Approved” as a step in the visa progress timeline</li>
                  <li>Change the request status to a valid stage such as “Passport collected”</li>
                  <li>Update the student’s visa record in the system</li>
                </ul>
              </div>

              <form method="post" id="approveVisaForm" class="row g-2">
                <input type="hidden" name="action" value="approve_and_update_visa">
                <input type="hidden" name="application_id" value="<?php echo (int)$detail["application_id"]; ?>">
                <input type="hidden" name="student_id" value="<?php echo (int)$detail["student_id"]; ?>">

                <div class="col-md-6">
                  <label class="form-label">Visa ID</label>
                  <input class="form-control" name="visa_id" value="<?php echo h($visa["visa_id"] ?? ""); ?>" placeholder="e.g. 768967001">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Visa Type</label>
                  <input class="form-control" name="visa_type" value="<?php echo h($visa["visa_type"] ?? "Student Pass"); ?>">
                </div>

                <div class="col-md-12">
                  <label class="form-label">Passport No</label>
                  <input class="form-control" name="passport_no" value="<?php echo h($visa["passport_no"] ?? ""); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Issue Date</label>
                  <input type="date" class="form-control" name="issue_date" value="<?php echo h($visa["issue_date"] ?? ""); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Expiry Date</label>
                  <input type="date" class="form-control" name="expiry_date" value="<?php echo h($visa["expiry_date"] ?? ""); ?>">
                </div>

                <div class="col-md-12">
                  <label class="form-label">Set Request Status</label>
                  <select class="form-select" name="set_app_status" required>
                    <?php foreach ($appStatusOptions as $opt): ?>
                      <option value="<?php echo h($opt); ?>" <?php echo $opt === "Passport collected" ? "selected" : ""; ?>>
                        <?php echo h($opt); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-12">
                  <label class="form-label">Remarks</label>
                  <input class="form-control" name="remarks" value="Approved & visa updated by staff">
                </div>

                <div class="col-12 d-grid mt-2">
                  <!-- Trigger modal -->
                  <button type="button"
                          class="btn btn-success"
                          data-bs-toggle="modal"
                          data-bs-target="#approveConfirmModal">
                    <i class="bi bi-check2-circle"></i> Approve & Update Visa
                  </button>

                  <!-- Real submit button -->
                  <button type="submit" id="approveRealSubmitBtn" class="d-none"></button>
                </div>
              </form>

            </div>
          </div>

        </div>
      </div>

      <!-- Modal -->
      <div class="modal fade" id="approveConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title">Confirm Approval</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
              Are you sure you want to <strong>Approve</strong> this visa renewal and <strong>update the student's visa record</strong>?
              <div class="text-muted small mt-2">
                This will also set the application status to a valid system status and add an "Approved" timeline stage.
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                Cancel
              </button>

              <button type="button" class="btn btn-success" id="approveConfirmYesBtn">
                Yes, Approve
              </button>
            </div>

          </div>
        </div>
      </div>

      <script>
        (function () {
          const yesBtn = document.getElementById('approveConfirmYesBtn');
          const realSubmit = document.getElementById('approveRealSubmitBtn');

          if (yesBtn && realSubmit) {
            yesBtn.addEventListener('click', function () {
              realSubmit.click();
            });
          }
        })();
      </script>

    <?php endif; ?>

  <?php endif; ?>

</div>

<?php require_once __DIR__ . "/footer.php"; ?>
