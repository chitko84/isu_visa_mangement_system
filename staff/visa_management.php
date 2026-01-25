<?php
// staff/visa_management.php
// Visa Records Management (Staff)
//
// Tables used:
// - student_visa (create/update visa records)
// - student (student reference)
// - program (optional display)
// - school (optional display)
//
// Procedures:
// - Recommended: sp_staff_upsert_student_visa (AVAILABLE in your DB)
//   This procedure automatically sets visa status Active/Expired based on expiry_date.

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
    return "visa_management.php?" . http_build_query($q);
}

function clearStoredResults(mysqli $conn): void {
    while ($conn->more_results()) {
        $conn->next_result();
        if ($res = $conn->store_result()) $res->free();
    }
}

// ------------------------------------------------------------
// POST actions BEFORE output
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    try {
        if ($action === 'upsert_visa') {
            $visa_id     = (int)($_POST['visa_id'] ?? 0);     // update only
            $student_id  = (int)($_POST['student_id'] ?? 0);
            $visa_type   = trim($_POST['visa_type'] ?? '');
            $issue_date  = trim($_POST['issue_date'] ?? '');
            $expiry_date = trim($_POST['expiry_date'] ?? '');
            $passport_no = trim($_POST['passport_no'] ?? '');

            if ($student_id <= 0) throw new RuntimeException("Student ID is required.");
            if ($visa_type === '') throw new RuntimeException("Visa type is required.");
            if ($passport_no === '') throw new RuntimeException("Passport number is required.");
            if ($issue_date === '' || $expiry_date === '') throw new RuntimeException("Issue date and expiry date are required.");

            if ($visa_id > 0) {
                // Update via procedure
                clearStoredResults($conn);
                $stmt = $conn->prepare("CALL sp_staff_upsert_student_visa(?, ?, ?, ?, ?, ?)");
                if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
                $stmt->bind_param("iissss", $visa_id, $student_id, $visa_type, $issue_date, $expiry_date, $passport_no);
                if (!$stmt->execute()) throw new RuntimeException("Execute failed: " . $stmt->error);
                $stmt->close();
                clearStoredResults($conn);

                redirectTo("visa_management.php?msg=" . urlencode("Visa record updated.") . "&edit=" . $visa_id);
            } else {
                // Create via INSERT (AUTO_INCREMENT visa_id)
                $today = strtotime(date('Y-m-d 00:00:00'));
                $exp   = strtotime($expiry_date . ' 00:00:00');
                $status = ($exp < $today) ? 'Expired' : 'Active';

                $stmt = $conn->prepare("
                    INSERT INTO student_visa(student_id, visa_type, issue_date, expiry_date, status, passport_no)
                    VALUES(?, ?, ?, ?, ?, ?)
                ");
                if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
                $stmt->bind_param("isssss", $student_id, $visa_type, $issue_date, $expiry_date, $status, $passport_no);
                if (!$stmt->execute()) throw new RuntimeException("Insert failed: " . $stmt->error);

                $newVisaId = (int)$conn->insert_id;
                $stmt->close();

                redirectTo("visa_management.php?msg=" . urlencode("Visa record created.") . "&edit=" . $newVisaId);
            }

        } elseif ($action === 'bulk_delete') {

            $ids = $_POST['delete_ids'] ?? [];
            if (!is_array($ids) || count($ids) === 0) {
                throw new RuntimeException("Please select at least one record to delete.");
            }

            // Keep only positive ints
            $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
            if (!$ids) throw new RuntimeException("Invalid selection.");

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));

            $sql = "DELETE FROM student_visa WHERE visa_id IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);

            $stmt->bind_param($types, ...$ids);
            if (!$stmt->execute()) throw new RuntimeException("Delete failed: " . $stmt->error);

            $deleted = (int)$stmt->affected_rows;
            $stmt->close();

            redirectTo("visa_management.php?msg=" . urlencode("Deleted $deleted visa record(s)."));

        } else {
            throw new RuntimeException("Unknown action.");
        }

    } catch (Throwable $e) {
        redirectTo("visa_management.php?error=" . urlencode($e->getMessage()));
    }
}

// ------------------------------------------------------------
// Page header + messages
// ------------------------------------------------------------
$page_title = "Visa Records Management - ISU Staff Portal";
require_once __DIR__ . "/header.php";

$success = trim($_GET['msg'] ?? '');
$error   = trim($_GET['error'] ?? '');

// ------------------------------------------------------------
// Load students for dropdown (light list)
// ------------------------------------------------------------
$students = [];
try {
    $res = $conn->query("SELECT student_id, first_name, last_name, email FROM student ORDER BY student_id DESC LIMIT 500");
    if ($res) $students = $res->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    $error = $error ?: ("Failed to load students: " . $e->getMessage());
}

// ------------------------------------------------------------
// Edit mode
// ------------------------------------------------------------
$edit = (int)($_GET['edit'] ?? 0);
$editRow = null;

if ($edit > 0) {
    try {
        $stmt = $conn->prepare("
            SELECT v.*, s.first_name, s.last_name, s.email,
                   p.program_name, sc.school_name
            FROM student_visa v
            JOIN student s ON s.student_id = v.student_id
            LEFT JOIN program p ON p.program_id = s.program_id
            LEFT JOIN school sc ON sc.school_id = p.school_id
            WHERE v.visa_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $edit);
        $stmt->execute();
        $editRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (Throwable $e) {
        $error = $error ?: ("Failed to load visa record: " . $e->getMessage());
    }
}

// ------------------------------------------------------------
// List filters
// ------------------------------------------------------------
$q = trim($_GET['q'] ?? '');
$student = trim($_GET['student'] ?? '');
$status = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['from'] ?? '');
$dateTo = trim($_GET['to'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = [];
$types = "";
$params = [];

if ($q !== "") {
    $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR v.passport_no LIKE ? OR v.visa_type LIKE ? OR CAST(v.visa_id AS CHAR) LIKE ?)";
    $like = "%" . $q . "%";
    $types .= "ssssss";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}

if ($student !== "" && ctype_digit($student)) {
    $where[] = "v.student_id = ?";
    $types .= "i";
    $params[] = (int)$student;
}

if ($status !== "" && in_array($status, ['Active','Expired'], true)) {
    $where[] = "v.status = ?";
    $types .= "s";
    $params[] = $status;
}

if ($dateFrom !== "") {
    $where[] = "v.expiry_date >= ?";
    $types .= "s";
    $params[] = $dateFrom;
}
if ($dateTo !== "") {
    $where[] = "v.expiry_date <= ?";
    $types .= "s";
    $params[] = $dateTo;
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// Count + list
$rows = [];
$total = 0;
$totalPages = 1;

try {
    // Count
    $sqlCount = "
      SELECT COUNT(*) AS c
      FROM student_visa v
      JOIN student s ON s.student_id = v.student_id
      $whereSql
    ";
    $stmt = $conn->prepare($sqlCount);
    if ($types !== "") $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);
    $stmt->close();

    $totalPages = max(1, (int)ceil($total / $limit));

    // List
    $sql = "
      SELECT
        v.visa_id, v.student_id, v.visa_type, v.issue_date, v.expiry_date, v.status, v.passport_no,
        s.first_name, s.last_name, s.email,
        p.program_name, sc.school_name
      FROM student_visa v
      JOIN student s ON s.student_id = v.student_id
      LEFT JOIN program p ON p.program_id = s.program_id
      LEFT JOIN school sc ON sc.school_id = p.school_id
      $whereSql
      ORDER BY v.expiry_date DESC, v.visa_id DESC
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
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

} catch (Throwable $e) {
    $error = $error ?: ("Failed to load visa records: " . $e->getMessage());
}
?>

<div class="container-fluid">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Visa Records Management</h3>
      <div class="text-muted">Create and update student visa records</div>
    </div>

    <div>
        <a href="visa_renewal.php" class="btn btn-outline-primary">
            <i class="bi bi-arrow-repeat"></i> Visa Renewal Applications
        </a>
    </div>

  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo h($success); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <!-- Form -->
    <div class="col-lg-4">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-white">
          <strong><?php echo $editRow ? "Edit Visa Record" : "Create Visa Record"; ?></strong>
        </div>
        <div class="card-body">

          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="upsert_visa">
            <input type="hidden" name="visa_id" value="<?php echo (int)($editRow['visa_id'] ?? 0); ?>">

            <div class="col-12">
              <label class="form-label">Student</label>
              <select class="form-select" name="student_id" required>
                <option value="">-- Select student --</option>
                <?php foreach ($students as $s): ?>
                  <?php
                    $sid = (int)$s['student_id'];
                    $name = trim(($s['first_name'] ?? '').' '.($s['last_name'] ?? ''));
                    $label = $name ? ($name . " (ID: $sid)") : ("Student $sid");
                    $selected = ((int)($editRow['student_id'] ?? 0) === $sid) ? "selected" : "";
                  ?>
                  <option value="<?php echo $sid; ?>" <?php echo $selected; ?>>
                    <?php echo h($label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Choose the student for this visa record</div>
            </div>

            <div class="col-12">
              <label class="form-label">Visa Type</label>
              <input class="form-control" name="visa_type" required value="<?php echo h($editRow['visa_type'] ?? 'Student Pass'); ?>">
            </div>

            <div class="col-12">
              <label class="form-label">Passport Number</label>
              <input class="form-control" name="passport_no" required value="<?php echo h($editRow['passport_no'] ?? ''); ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label">Issue Date</label>
              <input type="date" class="form-control" name="issue_date" required value="<?php echo h($editRow['issue_date'] ?? ''); ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label">Expiry Date</label>
              <input type="date" class="form-control" name="expiry_date" required value="<?php echo h($editRow['expiry_date'] ?? ''); ?>">
            </div>

            <div class="col-12 d-grid mt-2">
              <button class="btn btn-primary">
                <i class="bi bi-save"></i> <?php echo $editRow ? "Update Visa" : "Create Visa"; ?>
              </button>
            </div>

            <?php if ($editRow): ?>
              <div class="col-12 d-grid">
                <a class="btn btn-outline-secondary" href="visa_management.php">
                  <i class="bi bi-x-circle"></i> Cancel Edit
                </a>
              </div>
            <?php endif; ?>

            <div class="col-12">
              <div class="form-text">
                Notes:
                <ul class="mb-0">
                  <li>When you edit a record, the system updates the existing visa.</li>
                  <li>When you create a new record, the system adds a new visa automatically.</li>
                  <li>The visa status is set based on the expiry date (Active or Expired).</li>
                </ul>
              </div>
            </div>
          </form>

        </div>
      </div>

      <?php if ($editRow): ?>
        <div class="card shadow-sm">
          <div class="card-header bg-white"><strong>Selected Record Info</strong></div>
          <div class="card-body">
            <div class="text-muted small">Student</div>
            <div class="fw-semibold">
              <?php echo h(trim(($editRow['first_name'] ?? '').' '.($editRow['last_name'] ?? ''))); ?>
            </div>
            <div class="text-muted small">Email: <?php echo h($editRow['email'] ?? '-'); ?></div>
            <div class="text-muted small">
              <?php if (!empty($editRow['program_name'])): ?>
                Program: <?php echo h($editRow['program_name']); ?>
              <?php endif; ?>
              <?php if (!empty($editRow['school_name'])): ?>
                | School: <?php echo h($editRow['school_name']); ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

    </div>

    <!-- List -->
    <div class="col-lg-8">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Filters</strong></div>
        <div class="card-body">
          <form method="get" class="row g-3 align-items-end">
            <div class="col-md-5">
              <label class="form-label">Search</label>
              <input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="name, email, visa id, passport, type">
            </div>
            <div class="col-md-2">
              <label class="form-label">Student ID</label>
              <input class="form-control" name="student" value="<?php echo h($student); ?>" placeholder="e.g. 768967">
            </div>
            <div class="col-md-2">
              <label class="form-label">Status</label>
              <select class="form-select" name="status">
                <option value="" <?php echo $status==="" ? "selected" : ""; ?>>All</option>
                <option value="Active" <?php echo $status==="Active" ? "selected" : ""; ?>>Active</option>
                <option value="Expired" <?php echo $status==="Expired" ? "selected" : ""; ?>>Expired</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Expiry From</label>
              <input type="date" class="form-control" name="from" value="<?php echo h($dateFrom); ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Expiry To</label>
              <input type="date" class="form-control" name="to" value="<?php echo h($dateTo); ?>">
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-outline-primary"><i class="bi bi-search"></i> Apply</button>
              <a class="btn btn-outline-secondary" href="visa_management.php"><i class="bi bi-x-circle"></i> Clear</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <strong>Visa Records</strong>
          <span class="text-muted small"><?php echo (int)$total; ?> total</span>
        </div>

        <div class="card-body p-0">
          <?php if (!$rows): ?>
            <div class="p-4 text-muted">No visa records found.</div>
          <?php else: ?>

            <form method="post" onsubmit="return confirm('Are you sure you want to delete the selected visa record(s)?');">
              <input type="hidden" name="action" value="bulk_delete">

              <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-white">
                <div class="text-muted small">Select records and click delete.</div>
                <button type="submit" class="btn btn-danger btn-sm" id="bulkDeleteBtn" disabled>
                  Delete Selected
                </button>
              </div>

              <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="width:40px;" class="text-center">
                        <input type="checkbox" id="selectAll">
                      </th>
                      <th style="width:90px;">Visa ID</th>
                      <th style="min-width:240px;">Student</th>
                      <th style="min-width:240px;">Email</th>
                      <th style="min-width:160px;">Visa Type</th>
                      <th style="min-width:140px;">Issue</th>
                      <th style="min-width:140px;">Expiry</th>
                      <th style="min-width:120px;">Status</th>
                      <th style="min-width:140px;">Passport</th>
                      <th style="width:110px;">Edit</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($rows as $r): ?>
                      <?php
                        $name = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
                        $badge = ($r['status'] === 'Active') ? 'success' : 'secondary';
                      ?>
                      <tr>
                        <td class="text-center">
                          <input type="checkbox" class="row-check" name="delete_ids[]" value="<?php echo (int)$r['visa_id']; ?>">
                        </td>
                        <td class="text-muted"><?php echo (int)$r['visa_id']; ?></td>
                        <td>
                          <div class="fw-semibold"><?php echo h($name ?: ("Student ".$r['student_id'])); ?></div>
                          <div class="text-muted small">ID: <?php echo h($r['student_id']); ?></div>
                          <?php if (!empty($r['program_name'])): ?>
                            <div class="text-muted small"><?php echo h($r['program_name']); ?><?php echo !empty($r['school_name']) ? " • ".h($r['school_name']) : ""; ?></div>
                          <?php endif; ?>
                        </td>
                        <td><?php echo h($r['email'] ?? '-'); ?></td>
                        <td><?php echo h($r['visa_type']); ?></td>
                        <td><?php echo h(fmtDate($r['issue_date'])); ?></td>
                        <td><?php echo h(fmtDate($r['expiry_date'])); ?></td>
                        <td><span class="badge bg-<?php echo h($badge); ?>"><?php echo h($r['status']); ?></span></td>
                        <td><?php echo h($r['passport_no']); ?></td>
                        <td>
                          <a class="btn btn-sm btn-primary" href="<?php echo h(buildUrl(['edit' => (int)$r['visa_id']])); ?>">
                            Edit
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </form>

            <!-- Pagination -->
            <div class="p-3 border-top bg-white d-flex justify-content-between align-items-center">
              <div class="text-muted small">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></div>
              <nav>
                <ul class="pagination mb-0">
                  <li class="page-item <?php echo $page <= 1 ? "disabled" : ""; ?>">
                    <a class="page-link" href="<?php echo h(buildUrl(['page' => max(1, $page - 1)])); ?>">Prev</a>
                  </li>
                  <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    for ($p = $start; $p <= $end; $p++):
                  ?>
                    <li class="page-item <?php echo $p === $page ? "active" : ""; ?>">
                      <a class="page-link" href="<?php echo h(buildUrl(['page' => $p])); ?>"><?php echo (int)$p; ?></a>
                    </li>
                  <?php endfor; ?>
                  <li class="page-item <?php echo $page >= $totalPages ? "disabled" : ""; ?>">
                    <a class="page-link" href="<?php echo h(buildUrl(['page' => min($totalPages, $page + 1)])); ?>">Next</a>
                  </li>
                </ul>
              </nav>
            </div>

          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
  (function () {
    const selectAll = document.getElementById('selectAll');
    const btn = document.getElementById('bulkDeleteBtn');

    function allChecks() {
      return Array.from(document.querySelectorAll('.row-check'));
    }

    function updateButton() {
      const anyChecked = allChecks().some(c => c.checked);
      if (btn) btn.disabled = !anyChecked;
    }

    if (selectAll) {
      selectAll.addEventListener('change', function () {
        allChecks().forEach(c => c.checked = selectAll.checked);
        updateButton();
      });
    }

    document.addEventListener('change', function (e) {
      if (e.target && e.target.classList.contains('row-check')) {
        const list = allChecks();
        const allChecked = list.length > 0 && list.every(c => c.checked);
        const anyChecked = list.some(c => c.checked);
        if (selectAll) selectAll.checked = allChecked;
        if (btn) btn.disabled = !anyChecked;
      }
    });

    updateButton();
  })();
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
