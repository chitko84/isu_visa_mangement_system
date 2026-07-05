<?php
// staff/exit_management.php
// Exit Management (Staff/Admin) — with Create / Edit / Delete + Delete Modal
// IMPORTANT: Handle POST + redirects BEFORE including staff/header.php (which outputs HTML).

if (session_status() === PHP_SESSION_NONE) session_start();

// ------------------------------
// Auth (same logic as staff/header.php)
// ------------------------------
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['staff', 'admin'], true)) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../includes/db.php';

$staff_id = (int)($_SESSION['user_id'] ?? 0);
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
        if ($res = $conn->store_result()) $res->free();
    }
}

function callProc(mysqli $conn, string $sql, string $types = "", array $params = []): mysqli_stmt {
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
    if ($types !== "") $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) throw new RuntimeException("Execute failed: " . $stmt->error);
    return $stmt;
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

// CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

$success = "";
$error   = "";

// ------------------------------------------------------------
// POST actions (run BEFORE output)
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim($_POST["action"] ?? "");
    $success = "";
    $error = "";
    $selected_exit = (int)($_POST["exit_id"] ?? 0);

    try {
        $postedToken = $_POST['csrf_token'] ?? '';
        if (!$postedToken || !hash_equals($_SESSION['csrf_token'] ?? '', $postedToken)) {
            throw new RuntimeException("Invalid request (CSRF). Please try again.");
        }

        // ============================================================
        // EXIT CASE: CREATE / UPDATE / DELETE
        // ============================================================
        if ($action === "create_exit_case") {
            $student_id = (int)($_POST["student_id"] ?? 0);
            $exit_type  = trim($_POST["exit_type"] ?? "");
            $req_date   = trim($_POST["request_date"] ?? date('Y-m-d'));
            $exit_stat  = trim($_POST["exit_status"] ?? "Pending");

            $exitTypeOptions    = ["Completion","Withdrawal","Termination"];
            $exitStatusOptions  = ["Pending","In Progress","Approved","Completed","Rejected","Cancelled"];

            if ($student_id <= 0) throw new RuntimeException("Student is required.");
            if (!in_array($exit_type, $exitTypeOptions, true)) throw new RuntimeException("Invalid exit type.");
            if (!in_array($exit_stat, $exitStatusOptions, true)) $exit_stat = "Pending";
            if ($req_date === "") $req_date = date('Y-m-d');

            // Direct insert (simple & reliable)
            $stmt = $conn->prepare("
                INSERT INTO exit_case (student_id, exit_type, request_date, exit_status)
                VALUES (?, ?, ?, ?)
            ");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("isss", $student_id, $exit_type, $req_date, $exit_stat);
            if (!$stmt->execute()) throw new RuntimeException("Insert failed: " . $stmt->error);
            $newExitId = (int)$conn->insert_id;
            $stmt->close();

            $success = "Exit case created.";
            $selected_exit = $newExitId;
        }

        if ($action === "update_exit_case") {
            $exit_id    = (int)($_POST["exit_id"] ?? 0);
            $student_id = (int)($_POST["student_id"] ?? 0);
            $exit_type  = trim($_POST["exit_type"] ?? "");
            $req_date   = trim($_POST["request_date"] ?? "");
            $exit_stat  = trim($_POST["exit_status"] ?? "");

            $exitTypeOptions    = ["Completion","Withdrawal","Termination"];
            $exitStatusOptions  = ["Pending","In Progress","Approved","Completed","Rejected","Cancelled"];

            if ($exit_id <= 0) throw new RuntimeException("Invalid exit id.");
            if ($student_id <= 0) throw new RuntimeException("Student is required.");
            if (!in_array($exit_type, $exitTypeOptions, true)) throw new RuntimeException("Invalid exit type.");
            if (!in_array($exit_stat, $exitStatusOptions, true)) throw new RuntimeException("Invalid exit status.");
            if ($req_date === "") throw new RuntimeException("Request date is required.");

            $stmt = $conn->prepare("
                UPDATE exit_case
                SET student_id = ?, exit_type = ?, request_date = ?, exit_status = ?
                WHERE exit_id = ?
                LIMIT 1
            ");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("isssi", $student_id, $exit_type, $req_date, $exit_stat, $exit_id);
            if (!$stmt->execute()) throw new RuntimeException("Update failed: " . $stmt->error);
            $stmt->close();

            $success = "Exit case updated.";
            $selected_exit = $exit_id;
        }

        if ($action === "delete_exit_case") {
            $exit_id = (int)($_POST["exit_id"] ?? 0);
            if ($exit_id <= 0) throw new RuntimeException("Invalid exit id.");

            // If you have FK constraints, you may need to delete children first or use CASCADE.
            // We'll attempt delete; any FK error will be shown.
            $stmt = $conn->prepare("DELETE FROM exit_case WHERE exit_id = ? LIMIT 1");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("i", $exit_id);
            if (!$stmt->execute()) throw new RuntimeException("Delete failed: " . $stmt->error);
            $stmt->close();

            $success = "Exit case deleted.";
            $selected_exit = 0;
        }

        // ============================================================
        // EXISTING: Update exit status (procedure)
        // ============================================================
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

        // ============================================================
        // EXISTING: Create clearance record
        // ============================================================
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

        // ============================================================
        // UNIT CLEARANCE: UPSERT / UPDATE / DELETE (add Edit via modal)
        // ============================================================
        if ($action === "upsert_unit_clearance") {
            $exit_id      = (int)($_POST["exit_id"] ?? 0);
            $clearance_id = (int)($_POST["clearance_id"] ?? 0);
            $unit_name    = trim($_POST["unit_name"] ?? "");
            $clr_date     = trim($_POST["clearance_date"] ?? "");

            if ($exit_id <= 0) throw new RuntimeException("Invalid exit id.");
            if ($clearance_id <= 0) throw new RuntimeException("Invalid clearance id.");
            if ($unit_name === "") throw new RuntimeException("Unit name is required.");

            $dateParam = ($clr_date !== "") ? $clr_date : null;

            clearStoredResults($conn);
            // NOTE: types must match NULL handling: use "iss" and pass null works with mysqlnd
            callProc($conn, "CALL sp_staff_upsert_unit_clearance(?, ?, ?, @o_uc_id)", "iss",
                [$clearance_id, $unit_name, $dateParam]
            )->close();
            clearStoredResults($conn);

            $success = "Unit clearance saved.";
            $selected_exit = $exit_id;
        }

        if ($action === "update_unit_clearance") {
            $exit_id           = (int)($_POST["exit_id"] ?? 0);
            $unit_clearance_id = (int)($_POST["unit_clearance_id"] ?? 0);
            $unit_name         = trim($_POST["unit_name"] ?? "");
            $clr_date          = trim($_POST["clearance_date"] ?? "");

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

        // ============================================================
        // EXIT VISA ACTION: ADD / UPDATE / DELETE (add Edit via modal)
        // ============================================================
        if ($action === "add_exit_visa_action") {
            $exit_id     = (int)($_POST["exit_id"] ?? 0);
            $action_type = trim($_POST["action_type"] ?? "");
            $action_date = trim($_POST["action_date"] ?? "");
            $remarks     = trim($_POST["remarks"] ?? "");

            if ($exit_id <= 0) throw new RuntimeException("Invalid exit id.");
            if (!in_array($action_type, ["Cancellation","Lapse","Transfer"], true)) throw new RuntimeException("Invalid action type.");
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

        if ($action === "update_exit_visa_action") {
            $exit_id      = (int)($_POST["exit_id"] ?? 0);
            $exit_visa_id = (int)($_POST["exit_visa_id"] ?? 0);
            $action_type  = trim($_POST["action_type"] ?? "");
            $action_date  = trim($_POST["action_date"] ?? "");
            $remarks      = trim($_POST["remarks"] ?? "");

            if ($exit_id <= 0) throw new RuntimeException("Invalid exit id.");
            if ($exit_visa_id <= 0) throw new RuntimeException("Invalid exit visa id.");
            if (!in_array($action_type, ["Cancellation","Lapse","Transfer"], true)) throw new RuntimeException("Invalid action type.");
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
        $selected_exit = (int)($_POST["exit_id"] ?? 0);
    }

    // redirect back to page, keep filters
    $url = "exit_management.php";
    $qs = [];

    // preserve filters
    foreach (['q','status','type','page'] as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') $qs[$k] = $_GET[$k];
    }

    if (!empty($selected_exit)) $qs['exit_id'] = (string)$selected_exit;
    if ($success) $qs['msg'] = $success;
    if ($error)   $qs['error'] = $error;

    if ($qs) $url .= "?" . http_build_query($qs);
    redirectTo($url);
}

// ------------------------------------------------------------
// Now include header (HTML output starts here)
// ------------------------------------------------------------
$page_title = "Exit Management - ISU Staff Portal";
require_once __DIR__ . "/header.php";

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

// Options
$exitStatusOptions  = ["Pending","In Progress","Approved","Completed","Rejected","Cancelled"];
$exitTypeOptions    = ["Completion","Withdrawal","Termination"];
$clearStatusOptions = ["In Progress","Completed"];
$visaActionOptions  = ["Cancellation","Lapse","Transfer"];

// ------------------------------------------------------------
// Load students for Create/Edit exit case modal
// ------------------------------------------------------------
$students = [];
try {
    $res = $conn->query("SELECT student_id, first_name, last_name, email FROM student ORDER BY student_id DESC LIMIT 500");
    if ($res) $students = $res->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    $error = $error ?: ("Failed to load students: " . $e->getMessage());
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
?>

<div class="container-fluid">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Exit Management</h3>
      <div class="text-muted">Manage exit requests, clearance progress, and exit visa actions</div>
    </div>

    <div>
      <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exitCaseModal" id="btnCreateExit">
        <i class="bi bi-plus-circle"></i> Create Exit Case
      </button>
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
                    <th style="width:210px;" class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($exitRows as $r): ?>
                    <?php
                      $isSelected = ((int)$r["exit_id"] === $selected_exit_id);
                      $name = trim(($r["first_name"] ?? "") . " " . ($r["last_name"] ?? ""));
                      $clr = $r["clearance_id"] ? ("#".$r["clearance_id"]." • ".($r["clearance_status"] ?? "-")) : "Not created";
                      $exitId = (int)$r["exit_id"];
                    ?>
                    <tr class="<?php echo $isSelected ? "table-warning" : ""; ?>">
                      <td class="text-muted"><?php echo h($exitId); ?></td>
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
                        <div class="d-flex justify-content-end gap-2 flex-wrap">
                          <a class="btn btn-sm btn-primary" href="<?php echo h(buildUrl(["exit_id" => $exitId, "page" => $page])); ?>">
                            Manage
                          </a>

                          <button
                            type="button"
                            class="btn btn-sm btn-outline-primary open-edit-exit"
                            data-bs-toggle="modal"
                            data-bs-target="#exitCaseModal"
                            data-exit_id="<?php echo h($exitId); ?>"
                            data-student_id="<?php echo h($r["student_id"]); ?>"
                            data-exit_type="<?php echo h($r["exit_type"]); ?>"
                            data-request_date="<?php echo h($r["request_date"]); ?>"
                            data-exit_status="<?php echo h($r["exit_status"]); ?>"
                          >
                            Edit
                          </button>

                          <button
                            type="button"
                            class="btn btn-sm btn-outline-danger open-delete"
                            data-bs-toggle="modal"
                            data-bs-target="#deleteModal"
                            data-title="Delete Exit Case #<?php echo h($exitId); ?>?"
                            data-body="This will permanently delete this exit case. If the case has clearance/actions, deletion may fail due to database constraints."
                            data-action="delete_exit_case"
                            data-exit_id="<?php echo h($exitId); ?>"
                          >
                            Delete
                          </button>
                        </div>
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
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
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
                  <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
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
                  <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
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
                          <th style="width:170px;" class="text-end">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($unitRows as $u): ?>
                          <tr>
                            <td><?php echo h($u["unit_name"]); ?></td>
                            <td><?php echo h($u["clearance_date"] ?: "-"); ?></td>
                            <td class="text-end">
                              <div class="d-flex justify-content-end gap-2">
                                <button
                                  type="button"
                                  class="btn btn-sm btn-outline-primary open-edit-unit"
                                  data-bs-toggle="modal"
                                  data-bs-target="#unitModal"
                                  data-exit_id="<?php echo h($exit_id); ?>"
                                  data-unit_clearance_id="<?php echo h($u["unit_clearance_id"]); ?>"
                                  data-unit_name="<?php echo h($u["unit_name"]); ?>"
                                  data-clearance_date="<?php echo h($u["clearance_date"]); ?>"
                                >Edit</button>

                                <button
                                  type="button"
                                  class="btn btn-sm btn-outline-danger open-delete"
                                  data-bs-toggle="modal"
                                  data-bs-target="#deleteModal"
                                  data-title="Delete Unit Clearance?"
                                  data-body="Delete clearance for unit: <?php echo h($u["unit_name"]); ?>."
                                  data-action="delete_unit_clearance"
                                  data-exit_id="<?php echo h($exit_id); ?>"
                                  data-unit_clearance_id="<?php echo h($u["unit_clearance_id"]); ?>"
                                >Delete</button>
                              </div>
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
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
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
                        <th style="width:200px;" class="text-end">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($visaRows as $v): ?>
                        <tr>
                          <td class="fw-semibold"><?php echo h($v["action_type"]); ?></td>
                          <td><?php echo h($v["action_date"]); ?></td>
                          <td><?php echo h($v["remarks"] ?? "-"); ?></td>
                          <td class="text-end">
                            <div class="d-flex justify-content-end gap-2 flex-wrap">
                              <button
                                type="button"
                                class="btn btn-sm btn-outline-primary open-edit-visa"
                                data-bs-toggle="modal"
                                data-bs-target="#visaModal"
                                data-exit_id="<?php echo h($exit_id); ?>"
                                data-exit_visa_id="<?php echo h($v["exit_visa_id"]); ?>"
                                data-action_type="<?php echo h($v["action_type"]); ?>"
                                data-action_date="<?php echo h($v["action_date"]); ?>"
                                data-remarks="<?php echo h($v["remarks"]); ?>"
                              >Edit</button>

                              <button
                                type="button"
                                class="btn btn-sm btn-outline-danger open-delete"
                                data-bs-toggle="modal"
                                data-bs-target="#deleteModal"
                                data-title="Delete Exit Visa Action?"
                                data-body="Delete this exit visa action (<?php echo h($v["action_type"]); ?> on <?php echo h($v["action_date"]); ?>)."
                                data-action="delete_exit_visa_action"
                                data-exit_id="<?php echo h($exit_id); ?>"
                                data-exit_visa_id="<?php echo h($v["exit_visa_id"]); ?>"
                              >Delete</button>
                            </div>
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

<!-- =========================
     Exit Case Modal (Create/Edit)
========================= -->
<div class="modal fade" id="exitCaseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post" id="exitCaseForm">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="action" id="exit_action" value="create_exit_case">
        <input type="hidden" name="exit_id" id="exit_id" value="0">

        <div class="modal-header">
          <h5 class="modal-title" id="exitModalTitle">Create Exit Case</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Student</label>
              <select class="form-select" name="student_id" id="exit_student_id" required>
                <option value="">-- Select student --</option>
                <?php foreach ($students as $s): ?>
                  <?php
                    $sid = (int)$s['student_id'];
                    $nm = trim(($s['first_name'] ?? '').' '.($s['last_name'] ?? ''));
                    $label = $nm ? ($nm." (ID: $sid)") : ("Student $sid");
                  ?>
                  <option value="<?php echo h($sid); ?>"><?php echo h($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label">Exit Type</label>
              <select class="form-select" name="exit_type" id="exit_type" required>
                <?php foreach ($exitTypeOptions as $opt): ?>
                  <option value="<?php echo h($opt); ?>"><?php echo h($opt); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label">Request Date</label>
              <input type="date" class="form-control" name="request_date" id="request_date" required value="<?php echo h(date('Y-m-d')); ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label">Exit Status</label>
              <select class="form-select" name="exit_status" id="exit_status" required>
                <?php foreach ($exitStatusOptions as $opt): ?>
                  <option value="<?php echo h($opt); ?>"><?php echo h($opt); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <div class="text-muted small">
                Tip: Use <b>Pending</b> for new cases; staff can update status later.
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="exitSubmitBtn">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================
     Unit Clearance Modal (Edit)
========================= -->
<div class="modal fade" id="unitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" id="unitForm">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="action" value="update_unit_clearance">
        <input type="hidden" name="exit_id" id="unit_exit_id" value="0">
        <input type="hidden" name="unit_clearance_id" id="unit_clearance_id" value="0">

        <div class="modal-header">
          <h5 class="modal-title">Edit Unit Clearance</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <div class="col-7">
              <label class="form-label">Unit Name</label>
              <input class="form-control" name="unit_name" id="unit_name" required>
            </div>
            <div class="col-5">
              <label class="form-label">Clearance Date</label>
              <input type="date" class="form-control" name="clearance_date" id="unit_clearance_date">
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================
     Exit Visa Modal (Edit)
========================= -->
<div class="modal fade" id="visaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post" id="visaForm">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="action" value="update_exit_visa_action">
        <input type="hidden" name="exit_id" id="visa_exit_id" value="0">
        <input type="hidden" name="exit_visa_id" id="exit_visa_id" value="0">

        <div class="modal-header">
          <h5 class="modal-title">Edit Exit Visa Action</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Type</label>
              <select class="form-select" name="action_type" id="visa_action_type" required>
                <?php foreach ($visaActionOptions as $opt): ?>
                  <option value="<?php echo h($opt); ?>"><?php echo h($opt); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Date</label>
              <input type="date" class="form-control" name="action_date" id="visa_action_date" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Remarks</label>
              <input class="form-control" name="remarks" id="visa_remarks" placeholder="optional">
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================
     Delete Modal (Reusable)
========================= -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" id="deleteForm">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="action" id="del_action" value="">
        <input type="hidden" name="exit_id" id="del_exit_id" value="0">
        <input type="hidden" name="unit_clearance_id" id="del_unit_clearance_id" value="0">
        <input type="hidden" name="exit_visa_id" id="del_exit_visa_id" value="0">

        <div class="modal-header">
          <h5 class="modal-title" id="del_title">Confirm Delete</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div id="del_body">Are you sure?</div>
          <div class="text-danger small mt-2">This action cannot be undone.</div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Yes, Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  // Create Exit Case
  const btnCreateExit = document.getElementById('btnCreateExit');
  if (btnCreateExit) {
    btnCreateExit.addEventListener('click', () => {
      document.getElementById('exitModalTitle').textContent = 'Create Exit Case';
      document.getElementById('exit_action').value = 'create_exit_case';
      document.getElementById('exit_id').value = '0';
      document.getElementById('exit_student_id').value = '';
      document.getElementById('exit_type').value = 'Completion';
      document.getElementById('request_date').value = new Date().toISOString().slice(0,10);
      document.getElementById('exit_status').value = 'Pending';
      document.getElementById('exitSubmitBtn').textContent = 'Create';
    });
  }

  // Edit Exit Case
  document.querySelectorAll('.open-edit-exit').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('exitModalTitle').textContent = 'Edit Exit Case';
      document.getElementById('exit_action').value = 'update_exit_case';
      document.getElementById('exit_id').value = btn.dataset.exit_id || '0';
      document.getElementById('exit_student_id').value = btn.dataset.student_id || '';
      document.getElementById('exit_type').value = btn.dataset.exit_type || 'Completion';
      document.getElementById('request_date').value = btn.dataset.request_date || '';
      document.getElementById('exit_status').value = btn.dataset.exit_status || 'Pending';
      document.getElementById('exitSubmitBtn').textContent = 'Save Changes';
    });
  });

  // Edit Unit Clearance
  document.querySelectorAll('.open-edit-unit').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('unit_exit_id').value = btn.dataset.exit_id || '0';
      document.getElementById('unit_clearance_id').value = btn.dataset.unit_clearance_id || '0';
      document.getElementById('unit_name').value = btn.dataset.unit_name || '';
      document.getElementById('unit_clearance_date').value = btn.dataset.clearance_date || '';
    });
  });

  // Edit Visa Action
  document.querySelectorAll('.open-edit-visa').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('visa_exit_id').value = btn.dataset.exit_id || '0';
      document.getElementById('exit_visa_id').value = btn.dataset.exit_visa_id || '0';
      document.getElementById('visa_action_type').value = btn.dataset.action_type || 'Cancellation';
      document.getElementById('visa_action_date').value = btn.dataset.action_date || '';
      document.getElementById('visa_remarks').value = btn.dataset.remarks || '';
    });
  });

  // Delete Modal
  const delTitle = document.getElementById('del_title');
  const delBody  = document.getElementById('del_body');
  const delAction = document.getElementById('del_action');
  const delExitId = document.getElementById('del_exit_id');
  const delUcId   = document.getElementById('del_unit_clearance_id');
  const delVisaId = document.getElementById('del_exit_visa_id');

  document.querySelectorAll('.open-delete').forEach(btn => {
    btn.addEventListener('click', () => {
      delTitle.textContent = btn.dataset.title || 'Confirm Delete';
      delBody.textContent  = btn.dataset.body  || 'Are you sure?';

      delAction.value = btn.dataset.action || '';

      // reset
      delExitId.value = '0';
      delUcId.value   = '0';
      delVisaId.value = '0';

      // set known ids based on action
      if (btn.dataset.exit_id) delExitId.value = btn.dataset.exit_id;

      if (btn.dataset.action === 'delete_unit_clearance' && btn.dataset.unit_clearance_id) {
        delUcId.value = btn.dataset.unit_clearance_id;
      } else if (btn.dataset.action === 'delete_exit_visa_action' && btn.dataset.exit_visa_id) {
        delVisaId.value = btn.dataset.exit_visa_id;
      } else if (btn.dataset.action === 'delete_exit_case') {
        // exit_id already set
      }
    });
  });
})();
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
