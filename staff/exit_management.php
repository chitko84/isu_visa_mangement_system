<?php
// staff/exit_management.php
// IMPORTANT: Handle POST + redirects BEFORE including staff/header.php (which outputs HTML).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ------------------------------
// Auth (same logic as staff/header.php)
// ------------------------------
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['staff', 'admin'], true)) {
    header("Location: ../login.php");
    exit();
}

// DB
require_once __DIR__ . '/../includes/db.php';

$staff_id = (int)$_SESSION['user_id'];
$role     = $_SESSION['role'] ?? 'staff';

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function fmtDate($d): string {
    if (!$d) return "-";
    $t = strtotime((string)$d);
    return $t ? date("d M Y", $t) : (string)$d;
}

function clearStoredResults(mysqli $conn): void {
    while ($conn->more_results()) {
        $conn->next_result();
        if ($res = $conn->store_result()) {
            $res->free();
        }
    }
}

function callProc(mysqli $conn, string $sql, string $types = "", array $params = []): mysqli_stmt {
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
    if ($types !== "") $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) throw new RuntimeException("Execute failed: " . $stmt->error);
    return $stmt;
}

// safe redirect that still works even if headers somehow sent
function redirectTo(string $url): void {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    }
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . h($url) . '"></noscript>';
    exit();
}

$success = "";
$error   = "";

// ------------------------------------------------------------
// POST actions (run BEFORE output)
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim($_POST["action"] ?? "");
    $success = "";
    $error = "";

    try {
        // Update exit status
        if ($action === "update_exit_status") {
            $exit_id    = (int)($_POST["exit_id"] ?? 0);
            $new_status = trim($_POST["exit_status"] ?? "");

            if ($exit_id <= 0 || $new_status === "") throw new RuntimeException("Invalid exit id / status.");

            clearStoredResults($conn);
            callProc($conn, "CALL sp_staff_update_exit_status(?, ?)", "is", [$exit_id, $new_status])->close();
            clearStoredResults($conn);

            $success = "Exit status updated.";
            $selected_exit = $exit_id;
        }

        // Create clearance record
        if ($action === "create_clearance") {
            $exit_id = (int)($_POST["exit_id"] ?? 0);
            $status  = trim($_POST["clearance_status"] ?? "In Progress");

            if ($exit_id <= 0) throw new RuntimeException("Invalid exit id.");
            if (!in_array($status, ["In Progress", "Completed"], true)) $status = "In Progress";

            clearStoredResults($conn);
            callProc($conn, "CALL sp_staff_create_clearance_record(?, ?, @o_clearance_id)", "is", [$exit_id, $status])->close();
            clearStoredResults($conn);

            $success = "Clearance record created.";
            $selected_exit = $exit_id;
        }

        // Upsert unit clearance (by clearance_id + unit_name)
        if ($action === "upsert_unit_clearance") {
            $exit_id       = (int)($_POST["exit_id"] ?? 0);
            $clearance_id  = (int)($_POST["clearance_id"] ?? 0);
            $unit_name     = trim($_POST["unit_name"] ?? "");
            $clr_date      = trim($_POST["clearance_date"] ?? "");

            if ($exit_id <= 0) throw new RuntimeException("Invalid exit id.");
            if ($clearance_id <= 0) throw new RuntimeException("Invalid clearance id.");
            if ($unit_name === "") throw new RuntimeException("Unit name is required.");

            // allow NULL date
            $dateParam = ($clr_date !== "") ? $clr_date : null;

            clearStoredResults($conn);
            callProc($conn, "CALL sp_staff_upsert_unit_clearance(?, ?, ?, @o_uc_id)", "iss",
                [$clearance_id, $unit_name, $dateParam]
            )->close();
            clearStoredResults($conn);

            $success = "Unit clearance saved.";
            $selected_exit = $exit_id;
        }

        // Update unit clearance (by unit_clearance_id)
        if ($action === "update_unit_clearance") {
            $exit_id            = (int)($_POST["exit_id"] ?? 0);
            $unit_clearance_id  = (int)($_POST["unit_clearance_id"] ?? 0);
            $unit_name          = trim($_POST["unit_name"] ?? "");
            $clr_date           = trim($_POST["clearance_date"] ?? "");

            if ($exit_id <= 0) throw new RuntimeException("Invalid exit id.");
            if ($unit_clearance_id <= 0) throw new RuntimeException("Invalid unit clearance id.");
            if ($unit_name === "") throw new RuntimeException("Unit name is required.");

            $dateParam = ($clr_date !== "") ? $clr_date : null;

            clearStoredResults($conn);
            callProc($conn, "CALL sp_staff_update_unit_clearance(?, ?, ?)", "iss",
                [$unit_clearance_id, $unit_name, $dateParam]
            )->close();
            clearStoredResults($conn);

            $success = "Unit clearance updated.";
            $selected_exit = $exit_id;
        }

        // Delete unit clearance
        if ($action === "delete_unit_clearance") {
            $exit_id           = (int)($_POST["exit_id"] ?? 0);
            $unit_clearance_id = (int)($_POST["unit_clearance_id"] ?? 0);

            if ($exit_id <= 0) throw new RuntimeException("Invalid exit id.");
            if ($unit_clearance_id <= 0) throw new RuntimeException("Invalid unit clearance id.");

            clearStoredResults($conn);
            callProc($conn, "CALL sp_staff_delete_unit_clearance(?)", "i", [$unit_clearance_id])->close();
            clearStoredResults($conn);

            $success = "Unit clearance deleted.";
            $selected_exit = $exit_id;
        }

        // Add exit visa action
        if ($action === "add_exit_visa_action") {
            $exit_id     = (int)($_POST["exit_id"] ?? 0);
            $action_type = trim($_POST["action_type"] ?? "");
            $action_date = trim($_POST["action_date"] ?? "");
            $remarks     = trim($_POST["remarks"] ?? "");

            if ($exit_id <= 0) throw new RuntimeException("Invalid exit id.");
            if (!in_array($action_type, ["Cancellation","Lapse","Transfer"], true)) {
                throw new RuntimeException("Invalid action type.");
            }
            if ($action_date === "") throw new RuntimeException("Action date is required.");

            $remarksParam = ($remarks !== "") ? $remarks : null;

            clearStoredResults($conn);
            callProc($conn, "CALL sp_staff_add_exit_visa_action(?, ?, ?, ?, @o_exit_visa_id)", "isss",
                [$exit_id, $action_type, $action_date, $remarksParam]
            )->close();
            clearStoredResults($conn);

            $success = "Exit visa action added.";
            $selected_exit = $exit_id;
        }

        // Update exit visa action
        if ($action === "update_exit_visa_action") {
            $exit_id      = (int)($_POST["exit_id"] ?? 0);
            $exit_visa_id = (int)($_POST["exit_visa_id"] ?? 0);
            $action_type  = trim($_POST["action_type"] ?? "");
            $action_date  = trim($_POST["action_date"] ?? "");
            $remarks      = trim($_POST["remarks"] ?? "");

            if ($exit_id <= 0) throw new RuntimeException("Invalid exit id.");
            if ($exit_visa_id <= 0) throw new RuntimeException("Invalid exit visa id.");
            if (!in_array($action_type, ["Cancellation","Lapse","Transfer"], true)) {
                throw new RuntimeException("Invalid action type.");
            }
            if ($action_date === "") throw new RuntimeException("Action date is required.");

            $remarksParam = ($remarks !== "") ? $remarks : null;

            clearStoredResults($conn);
            callProc($conn, "CALL sp_staff_update_exit_visa_action(?, ?, ?, ?)", "isss",
                [$exit_visa_id, $action_type, $action_date, $remarksParam]
            )->close();
            clearStoredResults($conn);

            $success = "Exit visa action updated.";
            $selected_exit = $exit_id;
        }

        // Delete exit visa action
        if ($action === "delete_exit_visa_action") {
            $exit_id      = (int)($_POST["exit_id"] ?? 0);
            $exit_visa_id = (int)($_POST["exit_visa_id"] ?? 0);

            if ($exit_id <= 0) throw new RuntimeException("Invalid exit id.");
            if ($exit_visa_id <= 0) throw new RuntimeException("Invalid exit visa id.");

            clearStoredResults($conn);
            callProc($conn, "CALL sp_staff_delete_exit_visa_action(?)", "i", [$exit_visa_id])->close();
            clearStoredResults($conn);

            $success = "Exit visa action deleted.";
            $selected_exit = $exit_id;
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
        // keep selected exit if possible
        $selected_exit = (int)($_POST["exit_id"] ?? 0);
    }

    // redirect back to page (safe)
    $url = "exit_management.php";
    if (!empty($selected_exit)) {
        $url .= "?exit_id=" . urlencode((string)$selected_exit);
        if ($success) $url .= "&msg=" . urlencode($success);
        if ($error)   $url .= "&error=" . urlencode($error);
    } else {
        $url .= "?";
        if ($success) $url .= "msg=" . urlencode($success) . "&";
        if ($error)   $url .= "error=" . urlencode($error);
        $url = rtrim($url, "&?");
    }
    redirectTo($url);
}

// ------------------------------------------------------------
// Now include header (HTML output starts here)
// ------------------------------------------------------------
$page_title = "Exit Management - ISU Staff Portal";
require_once __DIR__ . "/header.php"; // provides nav + layout (HTML starts)

// ------------------------------------------------------------
// Messages from GET
// ------------------------------------------------------------
$success = trim($_GET["msg"] ?? "");
$error   = trim($_GET["error"] ?? "");

// ------------------------------------------------------------
// Filters (GET)
// ------------------------------------------------------------
$q       = trim($_GET["q"] ?? "");
$status  = trim($_GET["status"] ?? "");
$type    = trim($_GET["type"] ?? "");

$page  = max(1, (int)($_GET["page"] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$selected_exit_id = (int)($_GET["exit_id"] ?? 0);

$exitRows = [];
$total = 0;

function buildUrl(array $overrides = []): string {
    $q = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($q[$k]);
        else $q[$k] = $v;
    }
    return "exit_management.php?" . http_build_query($q);
}

// ------------------------------------------------------------
// List Exit Cases
// ------------------------------------------------------------
try {
    $where = [];
    $types = "";
    $params = [];

    if ($q !== "") {
        $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR e.student_id = ?)";
        $like = "%" . $q . "%";
        $types .= "sssi";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = (ctype_digit($q) ? (int)$q : -1);
    }

    if ($status !== "") {
        $where[] = "e.exit_status = ?";
        $types .= "s";
        $params[] = $status;
    }

    if ($type !== "") {
        $where[] = "e.exit_type = ?";
        $types .= "s";
        $params[] = $type;
    }

    $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

    $sqlCount = "
      SELECT COUNT(*) AS c
      FROM exit_case e
      JOIN student s ON s.student_id = e.student_id
      $whereSql
    ";
    $stmt = $conn->prepare($sqlCount);
    if ($types !== "") $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);
    $stmt->close();

    $sql = "
      SELECT
        e.exit_id, e.student_id, e.exit_type, e.request_date, e.exit_status,
        s.first_name, s.last_name, s.email,
        cr.clearance_id, cr.status AS clearance_status, cr.submission_date
      FROM exit_case e
      JOIN student s ON s.student_id = e.student_id
      LEFT JOIN clearance_record cr ON cr.exit_id = e.exit_id
      $whereSql
      ORDER BY e.request_date DESC, e.exit_id DESC
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
    $exitRows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

} catch (Throwable $e) {
    $error = $error ?: ("Failed to load exit cases: " . $e->getMessage());
}

$totalPages = max(1, (int)ceil($total / $limit));

// ------------------------------------------------------------
// Detail panel data
// ------------------------------------------------------------
$selected = null;
$unitRows = [];
$visaRows = [];

if ($selected_exit_id > 0) {
    try {
        $stmt = $conn->prepare("
          SELECT
            e.exit_id, e.student_id, e.exit_type, e.request_date, e.exit_status,
            s.first_name, s.last_name, s.email, s.phone,
            cr.clearance_id, cr.submission_date, cr.status AS clearance_status
          FROM exit_case e
          JOIN student s ON s.student_id = e.student_id
          LEFT JOIN clearance_record cr ON cr.exit_id = e.exit_id
          WHERE e.exit_id = ?
          LIMIT 1
        ");
        $stmt->bind_param("i", $selected_exit_id);
        $stmt->execute();
        $selected = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($selected && !empty($selected["clearance_id"])) {
            $cid = (int)$selected["clearance_id"];

            $stmt = $conn->prepare("
              SELECT unit_clearance_id, clearance_id, unit_name, clearance_date
              FROM unit_clearance
              WHERE clearance_id = ?
              ORDER BY unit_name ASC, unit_clearance_id ASC
            ");
            $stmt->bind_param("i", $cid);
            $stmt->execute();
            $unitRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        $stmt = $conn->prepare("
          SELECT exit_visa_id, exit_id, action_type, action_date, remarks
          FROM exit_visa_action
          WHERE exit_id = ?
          ORDER BY action_date DESC, exit_visa_id DESC
        ");
        $stmt->bind_param("i", $selected_exit_id);
        $stmt->execute();
        $visaRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

    } catch (Throwable $e) {
        $error = $error ?: ("Failed to load selected exit details: " . $e->getMessage());
    }
}

$exitStatusOptions  = ["Pending","In Progress","Approved","Completed","Rejected","Cancelled"];
$exitTypeOptions    = ["Completion","Withdrawal","Termination"];
$clearStatusOptions = ["In Progress","Completed"];
$visaActionOptions  = ["Cancellation","Lapse","Transfer"];
?>

<div class="container-fluid">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Exit Management</h3>
      <div class="text-muted">Manage exit requests, clearance progress, and exit visa actions</div>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo h($success); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
  <?php endif; ?>

  <!-- Filters -->
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Filters</strong></div>
    <div class="card-body">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-md-5">
          <label class="form-label">Search (name, email, student ID)</label>
          <input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="e.g. 768967, Ahmad, student@aiu.edu.my">
        </div>

        <div class="col-md-3">
          <label class="form-label">Exit Status</label>
          <select class="form-select" name="status">
            <option value="">All</option>
            <?php foreach ($exitStatusOptions as $opt): ?>
              <option value="<?php echo h($opt); ?>" <?php echo $status === $opt ? "selected" : ""; ?>>
                <?php echo h($opt); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Exit Type</label>
          <select class="form-select" name="type">
            <option value="">All</option>
            <?php foreach ($exitTypeOptions as $opt): ?>
              <option value="<?php echo h($opt); ?>" <?php echo $type === $opt ? "selected" : ""; ?>>
                <?php echo h($opt); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-2 d-flex gap-2">
          <button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Apply</button>
          <a class="btn btn-outline-secondary w-100" href="exit_management.php"><i class="bi bi-x-circle"></i> Clear</a>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-3">
    <!-- List -->
    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <strong>Exit Requests</strong>
          <span class="text-muted small"><?php echo (int)$total; ?> total</span>
        </div>

        <div class="card-body p-0">
          <?php if (!$exitRows): ?>
            <div class="p-4 text-muted">No exit cases found.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:90px;">Exit ID</th>
                    <th>Student</th>
                    <th style="min-width:160px;">Type</th>
                    <th style="min-width:140px;">Request Date</th>
                    <th style="min-width:140px;">Exit Status</th>
                    <th style="min-width:170px;">Clearance</th>
                    <th style="width:110px;"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($exitRows as $r): ?>
                    <?php
                      $isSelected = ((int)$r["exit_id"] === $selected_exit_id);
                      $name = trim(($r["first_name"] ?? "") . " " . ($r["last_name"] ?? ""));
                      $clr = $r["clearance_id"] ? ("#".$r["clearance_id"]." • ".($r["clearance_status"] ?? "-")) : "Not created";
                    ?>
                    <tr class="<?php echo $isSelected ? "table-warning" : ""; ?>">
                      <td class="text-muted"><?php echo h($r["exit_id"]); ?></td>
                      <td>
                        <div class="fw-semibold"><?php echo h($name ?: ("Student ".$r["student_id"])); ?></div>
                        <div class="text-muted small">ID: <?php echo h($r["student_id"]); ?> • <?php echo h($r["email"] ?? "-"); ?></div>
                      </td>
                      <td><?php echo h($r["exit_type"]); ?></td>
                      <td><?php echo h(fmtDate($r["request_date"])); ?></td>
                      <td>
                        <span class="badge bg-<?php echo in_array($r["exit_status"], ["Approved","Completed"], true) ? "success" : (in_array($r["exit_status"], ["Rejected","Cancelled"], true) ? "danger" : "secondary"); ?>">
                          <?php echo h($r["exit_status"]); ?>
                        </span>
                      </td>
                      <td><?php echo h($clr); ?></td>
                      <td class="text-end">
                        <a class="btn btn-sm btn-primary" href="<?php echo h(buildUrl(["exit_id" => (int)$r["exit_id"], "page" => $page])); ?>">
                          Manage
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

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
    </div>

    <!-- Manage -->
    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Manage Exit Case</strong></div>
        <div class="card-body">

          <?php if (!$selected): ?>
            <div class="text-muted">Select an exit request from the left table to manage.</div>
          <?php else: ?>
            <?php
              $name = trim(($selected["first_name"] ?? "") . " " . ($selected["last_name"] ?? ""));
              $exit_id = (int)$selected["exit_id"];
              $clearance_id = (int)($selected["clearance_id"] ?? 0);
            ?>

            <div class="mb-3">
              <div class="fw-semibold"><?php echo h($name ?: ("Student ".$selected["student_id"])); ?></div>
              <div class="text-muted small">
                Student ID: <?php echo h($selected["student_id"]); ?> • <?php echo h($selected["email"]); ?> • <?php echo h($selected["phone"] ?? "-"); ?>
              </div>
              <div class="text-muted small">
                Exit ID: <?php echo h($exit_id); ?> • Type: <?php echo h($selected["exit_type"]); ?> • Requested: <?php echo h(fmtDate($selected["request_date"])); ?>
              </div>
            </div>

            <!-- Update Exit Status -->
            <div class="border rounded p-3 mb-3">
              <strong>Exit Status</strong>
              <form method="post" class="row g-2 align-items-end mt-1">
                <input type="hidden" name="action" value="update_exit_status">
                <input type="hidden" name="exit_id" value="<?php echo (int)$exit_id; ?>">

                <div class="col-8">
                  <label class="form-label">Status</label>
                  <select class="form-select" name="exit_status" required>
                    <?php foreach ($exitStatusOptions as $opt): ?>
                      <option value="<?php echo h($opt); ?>" <?php echo ($selected["exit_status"] === $opt ? "selected" : ""); ?>>
                        <?php echo h($opt); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text">
                    If you set <b>Approved/Completed</b>, DB trigger may block if student has pending insurance claims.
                  </div>
                </div>

                <div class="col-4">
                  <button class="btn btn-primary w-100"><i class="bi bi-save"></i> Save</button>
                </div>
              </form>
            </div>

            <!-- Clearance Record -->
            <div class="border rounded p-3 mb-3">
              <div class="d-flex justify-content-between align-items-center">
                <strong>Clearance Record</strong>
                <span class="text-muted small"><?php echo $clearance_id ? ("#".$clearance_id) : "Not created"; ?></span>
              </div>

              <?php if (!$clearance_id): ?>
                <form method="post" class="row g-2 align-items-end mt-2">
                  <input type="hidden" name="action" value="create_clearance">
                  <input type="hidden" name="exit_id" value="<?php echo (int)$exit_id; ?>">

                  <div class="col-8">
                    <label class="form-label">Initial Status</label>
                    <select class="form-select" name="clearance_status">
                      <?php foreach ($clearStatusOptions as $opt): ?>
                        <option value="<?php echo h($opt); ?>"><?php echo h($opt); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-4">
                    <button class="btn btn-outline-primary w-100"><i class="bi bi-plus-circle"></i> Create</button>
                  </div>
                </form>
              <?php else: ?>
                <div class="text-muted small mt-2">
                  Submission date: <?php echo h(fmtDate($selected["submission_date"] ?? null)); ?> • Status: <?php echo h($selected["clearance_status"] ?? "-"); ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Unit Clearances -->
            <div class="border rounded p-3 mb-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Unit Clearances</strong>
                <span class="text-muted small"><?php echo (int)count($unitRows); ?> items</span>
              </div>

              <?php if (!$clearance_id): ?>
                <div class="text-muted">Create a clearance record first.</div>
              <?php else: ?>
                <form method="post" class="row g-2 align-items-end mb-3">
                  <input type="hidden" name="action" value="upsert_unit_clearance">
                  <input type="hidden" name="exit_id" value="<?php echo (int)$exit_id; ?>">
                  <input type="hidden" name="clearance_id" value="<?php echo (int)$clearance_id; ?>">

                  <div class="col-6">
                    <label class="form-label">Unit Name</label>
                    <input class="form-control" name="unit_name" required placeholder="e.g. Library, Finance, Hostel">
                  </div>
                  <div class="col-4">
                    <label class="form-label">Clearance Date</label>
                    <input type="date" class="form-control" name="clearance_date">
                  </div>
                  <div class="col-2">
                    <button class="btn btn-outline-primary w-100">Save</button>
                  </div>
                </form>

                <?php if (!$unitRows): ?>
                  <div class="text-muted">No unit clearances yet.</div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                      <thead class="table-light">
                        <tr>
                          <th>Unit</th>
                          <th style="width:150px;">Date</th>
                          <th style="width:110px;"></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($unitRows as $u): ?>
                          <tr>
                            <td><?php echo h($u["unit_name"]); ?></td>
                            <td><?php echo h($u["clearance_date"] ?: "-"); ?></td>
                            <td class="text-end">
                              <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="delete_unit_clearance">
                                <input type="hidden" name="exit_id" value="<?php echo (int)$exit_id; ?>">
                                <input type="hidden" name="unit_clearance_id" value="<?php echo (int)$u["unit_clearance_id"]; ?>">
                                <button class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete this unit clearance?');">Delete</button>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  <div class="form-text mt-2">
                    Adding the same unit name again will update its date (procedure upsert).
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </div>

            <!-- Exit Visa Actions -->
            <div class="border rounded p-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Exit Visa Actions</strong>
                <span class="text-muted small"><?php echo (int)count($visaRows); ?> items</span>
              </div>

              <form method="post" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="action" value="add_exit_visa_action">
                <input type="hidden" name="exit_id" value="<?php echo (int)$exit_id; ?>">

                <div class="col-5">
                  <label class="form-label">Type</label>
                  <select class="form-select" name="action_type" required>
                    <?php foreach ($visaActionOptions as $opt): ?>
                      <option value="<?php echo h($opt); ?>"><?php echo h($opt); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-4">
                  <label class="form-label">Date</label>
                  <input type="date" class="form-control" name="action_date" required>
                </div>
                <div class="col-3">
                  <label class="form-label">Remarks</label>
                  <input class="form-control" name="remarks" placeholder="optional">
                </div>

                <div class="col-12">
                  <button class="btn btn-outline-primary w-100">Add Action</button>
                </div>
              </form>

              <?php if (!$visaRows): ?>
                <div class="text-muted">No exit visa actions yet.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Type</th>
                        <th style="width:140px;">Date</th>
                        <th>Remarks</th>
                        <th style="width:110px;"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($visaRows as $v): ?>
                        <tr>
                          <td class="fw-semibold"><?php echo h($v["action_type"]); ?></td>
                          <td><?php echo h($v["action_date"]); ?></td>
                          <td><?php echo h($v["remarks"] ?? "-"); ?></td>
                          <td class="text-end">
                            <form method="post" class="d-inline">
                              <input type="hidden" name="action" value="delete_exit_visa_action">
                              <input type="hidden" name="exit_id" value="<?php echo (int)$exit_id; ?>">
                              <input type="hidden" name="exit_visa_id" value="<?php echo (int)$v["exit_visa_id"]; ?>">
                              <button class="btn btn-sm btn-outline-danger"
                                      onclick="return confirm('Delete this visa action?');">Delete</button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>

            </div>

          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . "/footer.php"; ?>
