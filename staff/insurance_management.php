<?php
// staff/insurance.php  (or rename to insurance_management.php if you prefer)
// Insurance Records & Claims (Staff/Admin)
//
// Tables used:
// - insurance_policy (policy_id, student_id, provider_id, policy_number, coverage_type, start_date, end_date, status, ...)
// - insurance_provider (provider_id, provider_name, ...)
// - insurance_renewal_record (renewal_id, policy_id, renewal_date, new_end_date, status, ...)
// - insurance_claim (claim_id, policy_id, claim_date, claim_amount, claim_status, ...)
// - student (student_id, first_name, last_name, email, phone, ...)
//
// Stored procedures expected:
// - sp_staff_update_claim_status(IN p_claim_id INT, IN p_status VARCHAR(20))
// - sp_staff_update_renewal_status(IN p_renewal_id INT, IN p_status VARCHAR(20))
//   (Recommended behavior: updates renewal status; if Approved => updates insurance_policy.end_date = renewal.new_end_date)

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

function clearStoredResults(mysqli $conn): void {
    while ($conn->more_results()) {
        $conn->next_result();
        if ($res = $conn->store_result()) $res->free();
    }
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
    return basename($_SERVER['PHP_SELF']) . "?" . http_build_query($q);
}

function tabLink(string $tab, string $label, string $activeTab): string {
    $cls = ($tab === $activeTab) ? "btn btn-primary btn-sm" : "btn btn-outline-primary btn-sm";
    return '<a class="'.h($cls).'" href="'.h(buildUrl(['tab'=>$tab,'page'=>1])).'">'.h($label).'</a>';
}

// ------------------------------------------------------------
// POST actions BEFORE output
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $msg = "";
    $error = "";

    try {
        if ($action === "update_claim_status") {
            $claim_id = (int)($_POST['claim_id'] ?? 0);
            $new_status = trim($_POST['claim_status'] ?? "");

            if ($claim_id <= 0) throw new RuntimeException("Invalid claim id.");
            if (!in_array($new_status, ['Pending','Approved','Rejected'], true)) {
                throw new RuntimeException("Invalid claim status.");
            }

            clearStoredResults($conn);
            $stmt = $conn->prepare("CALL sp_staff_update_claim_status(?, ?)");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("is", $claim_id, $new_status);
            if (!$stmt->execute()) throw new RuntimeException("Execute failed: " . $stmt->error);
            $stmt->close();
            clearStoredResults($conn);

            $msg = "Claim status updated.";

        } elseif ($action === "update_renewal_status") {
            $renewal_id = (int)($_POST['renewal_id'] ?? 0);
            $new_status = trim($_POST['renewal_status'] ?? "");

            if ($renewal_id <= 0) throw new RuntimeException("Invalid renewal id.");
            if (!in_array($new_status, ['Pending','Approved','Rejected'], true)) {
                throw new RuntimeException("Invalid renewal status.");
            }

            clearStoredResults($conn);
            $stmt = $conn->prepare("CALL sp_staff_update_renewal_status(?, ?)");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("is", $renewal_id, $new_status);
            if (!$stmt->execute()) throw new RuntimeException("Execute failed: " . $stmt->error);
            $stmt->close();
            clearStoredResults($conn);

            $msg = "Renewal status updated.";

        } else {
            throw new RuntimeException("Unknown action.");
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    $url = basename($_SERVER['PHP_SELF']);
    if ($msg)   $url .= "?msg=" . urlencode($msg);
    if ($error) $url .= ($msg ? "&" : "?") . "error=" . urlencode($error);
    redirectTo($url);
}

// ------------------------------------------------------------
// Now include header (HTML starts)
// ------------------------------------------------------------
$page_title = "Insurance Management - ISU Staff Portal";
require_once __DIR__ . "/header.php";

// Messages
$success = trim($_GET['msg'] ?? '');
$error   = trim($_GET['error'] ?? '');

// ------------------------------------------------------------
// Filters
// ------------------------------------------------------------
$tab = trim($_GET['tab'] ?? 'policies'); // policies | claims | renewals
if (!in_array($tab, ['policies','claims','renewals'], true)) $tab = 'policies';

$q        = trim($_GET['q'] ?? '');
$student  = trim($_GET['student'] ?? '');     // student_id
$provider = trim($_GET['provider'] ?? '');    // provider_id
$status   = trim($_GET['status'] ?? '');      // policy.status OR claim_status OR renewal.status
$dateFrom = trim($_GET['from'] ?? '');        // YYYY-MM-DD
$dateTo   = trim($_GET['to'] ?? '');          // YYYY-MM-DD

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

// Options
$policyStatusOptions  = ["Active","Expired"];
$claimStatusOptions   = ["Pending","Approved","Rejected"];
$renewalStatusOptions = ["Pending","Approved","Rejected"];

// Data
$providers = [];
$rows = [];
$total = 0;

// Load providers for dropdown
try {
    $res = $conn->query("SELECT provider_id, provider_name FROM insurance_provider ORDER BY provider_name ASC");
    if ($res) {
        $providers = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
    }
} catch (Throwable $e) {
    $error = $error ?: ("Failed to load providers: " . $e->getMessage());
}

// ------------------------------------------------------------
// Build list queries
// ------------------------------------------------------------
try {
    $where = [];
    $types = "";
    $params = [];

    // search text
    if ($q !== "") {
        // For all tabs we join student + policy anyway
        $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR ip.policy_number LIKE ?)";
        $like = "%" . $q . "%";
        $types .= "ssss";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }

    // student filter
    if ($student !== "" && ctype_digit($student)) {
        $where[] = "s.student_id = ?";
        $types .= "i";
        $params[] = (int)$student;
    }

    // provider filter
    if ($provider !== "" && ctype_digit($provider)) {
        $where[] = "ip.provider_id = ?";
        $types .= "i";
        $params[] = (int)$provider;
    }

    // status filter
    if ($status !== "") {
        if ($tab === "claims") {
            $where[] = "ic.claim_status = ?";
            $types .= "s";
            $params[] = $status;
        } elseif ($tab === "renewals") {
            $where[] = "rr.status = ?";
            $types .= "s";
            $params[] = $status;
        } else {
            $where[] = "ip.status = ?";
            $types .= "s";
            $params[] = $status;
        }
    }

    // date filters (meaning changes by tab)
    if ($dateFrom !== "") {
        if ($tab === "claims")      $where[] = "ic.claim_date >= ?";
        elseif ($tab === "renewals") $where[] = "rr.renewal_date >= ?";
        else                         $where[] = "ip.end_date >= ?";
        $types .= "s";
        $params[] = $dateFrom;
    }

    if ($dateTo !== "") {
        if ($tab === "claims")      $where[] = "ic.claim_date <= ?";
        elseif ($tab === "renewals") $where[] = "rr.renewal_date <= ?";
        else                         $where[] = "ip.end_date <= ?";
        $types .= "s";
        $params[] = $dateTo;
    }

    $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

    // --------------------------------------------------------
    // Claims tab
    // --------------------------------------------------------
    if ($tab === "claims") {
        // Count
        $sqlCount = "
            SELECT COUNT(*) AS c
            FROM insurance_claim ic
            JOIN insurance_policy ip   ON ip.policy_id = ic.policy_id
            JOIN insurance_provider pr ON pr.provider_id = ip.provider_id
            JOIN student s             ON s.student_id = ip.student_id
            $whereSql
        ";
        $stmt = $conn->prepare($sqlCount);
        if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
        if ($types !== "") $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);
        $stmt->close();

        // Page
        $sql = "
            SELECT
              ic.claim_id, ic.claim_date, ic.claim_amount, ic.claim_status,
              ip.policy_id, ip.policy_number, ip.start_date, ip.end_date, ip.coverage_type, ip.status AS policy_status,
              pr.provider_id, pr.provider_name,
              s.student_id, s.first_name, s.last_name, s.email, s.phone
            FROM insurance_claim ic
            JOIN insurance_policy ip   ON ip.policy_id = ic.policy_id
            JOIN insurance_provider pr ON pr.provider_id = ip.provider_id
            JOIN student s             ON s.student_id = ip.student_id
            $whereSql
            ORDER BY ic.claim_date DESC, ic.claim_id DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);

        $types2 = $types . "ii";
        $params2 = $params;
        $params2[] = $limit;
        $params2[] = $offset;

        $stmt->bind_param($types2, ...$params2);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

    // --------------------------------------------------------
    // Renewals tab
    // --------------------------------------------------------
    } elseif ($tab === "renewals") {

        $sqlCount = "
            SELECT COUNT(*) AS c
            FROM insurance_renewal_record rr
            JOIN insurance_policy ip   ON ip.policy_id = rr.policy_id
            JOIN insurance_provider pr ON pr.provider_id = ip.provider_id
            JOIN student s             ON s.student_id = ip.student_id
            $whereSql
        ";
        $stmt = $conn->prepare($sqlCount);
        if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
        if ($types !== "") $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);
        $stmt->close();

        $sql = "
            SELECT
              rr.renewal_id, rr.renewal_date, rr.new_end_date, rr.status AS renewal_status,
              ip.policy_id, ip.policy_number, ip.start_date, ip.end_date, ip.coverage_type, ip.status AS policy_status,
              pr.provider_id, pr.provider_name,
              s.student_id, s.first_name, s.last_name, s.email, s.phone
            FROM insurance_renewal_record rr
            JOIN insurance_policy ip   ON ip.policy_id = rr.policy_id
            JOIN insurance_provider pr ON pr.provider_id = ip.provider_id
            JOIN student s             ON s.student_id = ip.student_id
            $whereSql
            ORDER BY rr.renewal_date DESC, rr.renewal_id DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);

        $types2 = $types . "ii";
        $params2 = $params;
        $params2[] = $limit;
        $params2[] = $offset;

        $stmt->bind_param($types2, ...$params2);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

    // --------------------------------------------------------
    // Policies tab (default)
    // --------------------------------------------------------
    } else {
        $sqlCount = "
            SELECT COUNT(*) AS c
            FROM insurance_policy ip
            JOIN insurance_provider pr ON pr.provider_id = ip.provider_id
            JOIN student s             ON s.student_id = ip.student_id
            $whereSql
        ";
        $stmt = $conn->prepare($sqlCount);
        if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
        if ($types !== "") $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);
        $stmt->close();

        $sql = "
            SELECT
              ip.policy_id, ip.policy_number, ip.coverage_type, ip.start_date, ip.end_date, ip.status,
              pr.provider_id, pr.provider_name,
              s.student_id, s.first_name, s.last_name, s.email, s.phone
            FROM insurance_policy ip
            JOIN insurance_provider pr ON pr.provider_id = ip.provider_id
            JOIN student s             ON s.student_id = ip.student_id
            $whereSql
            ORDER BY ip.end_date ASC, ip.policy_id DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);

        $types2 = $types . "ii";
        $params2 = $params;
        $params2[] = $limit;
        $params2[] = $offset;

        $stmt->bind_param($types2, ...$params2);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

} catch (Throwable $e) {
    $error = $error ?: ("Failed to load records: " . $e->getMessage());
}

// Pagination
$totalPages = max(1, (int)ceil(($total ?: 0) / $limit));
if ($page > $totalPages) $page = $totalPages;

// ------------------------------------------------------------
// UI
// ------------------------------------------------------------
?>
<div class="container py-4">

  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h3 class="mb-0">Insurance Management</h3>
      <div class="text-muted small">View policies, claims, and renewals. Update claim/renewal statuses.</div>
    </div>
    <div class="d-flex gap-2">
      <?= tabLink('policies', 'Policies', $tab) ?>
      <?= tabLink('claims', 'Claims', $tab) ?>
      <?= tabLink('renewals', 'Renewals', $tab) ?>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
  <?php endif; ?>

  <!-- Filters -->
  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="tab" value="<?= h($tab) ?>"/>

        <div class="col-12 col-md-3">
          <label class="form-label">Search</label>
          <input type="text" name="q" value="<?= h($q) ?>" class="form-control" placeholder="Name, email, policy no...">
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label">Student ID</label>
          <input type="text" name="student" value="<?= h($student) ?>" class="form-control" placeholder="e.g. 123">
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label">Provider</label>
          <select name="provider" class="form-select">
            <option value="">All</option>
            <?php foreach ($providers as $p): ?>
              <option value="<?= h($p['provider_id']) ?>" <?= ((string)$provider === (string)$p['provider_id']) ? 'selected' : '' ?>>
                <?= h($p['provider_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="">All</option>
            <?php
              $opts = ($tab === 'claims') ? $claimStatusOptions : (($tab === 'renewals') ? $renewalStatusOptions : $policyStatusOptions);
              foreach ($opts as $opt):
            ?>
              <option value="<?= h($opt) ?>" <?= ($status === $opt) ? 'selected' : '' ?>><?= h($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6 col-md-1">
          <label class="form-label"><?= ($tab === 'claims') ? 'From (Claim)' : (($tab === 'renewals') ? 'From (Renew)' : 'From (End)') ?></label>
          <input type="date" name="from" value="<?= h($dateFrom) ?>" class="form-control">
        </div>

        <div class="col-6 col-md-1">
          <label class="form-label"><?= ($tab === 'claims') ? 'To (Claim)' : (($tab === 'renewals') ? 'To (Renew)' : 'To (End)') ?></label>
          <input type="date" name="to" value="<?= h($dateTo) ?>" class="form-control">
        </div>

        <div class="col-12 col-md-1 d-grid">
          <button class="btn btn-primary" type="submit">Filter</button>
        </div>

        <div class="col-12 col-md-1 d-grid">
          <a class="btn btn-outline-secondary" href="<?= h(buildUrl(['q'=>null,'student'=>null,'provider'=>null,'status'=>null,'from'=>null,'to'=>null,'page'=>1])) ?>">
            Reset
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- List -->
  <div class="card">
    <div class="card-body">

      <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <div class="text-muted small">
          Showing <?= h(min($total, $offset + 1)) ?>–<?= h(min($total, $offset + $limit)) ?> of <?= h($total) ?>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <?php if ($tab === 'claims'): ?>
              <tr>
                <th>Claim</th>
                <th>Student</th>
                <th>Provider</th>
                <th>Policy</th>
                <th>Claim Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            <?php elseif ($tab === 'renewals'): ?>
              <tr>
                <th>Renewal</th>
                <th>Student</th>
                <th>Provider</th>
                <th>Policy</th>
                <th>Renewal Date</th>
                <th>New End Date</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            <?php else: ?>
              <tr>
                <th>Policy</th>
                <th>Student</th>
                <th>Provider</th>
                <th>Coverage</th>
                <th>Start</th>
                <th>End</th>
                <th>Status</th>
              </tr>
            <?php endif; ?>
          </thead>

          <tbody>
            <?php if (!$rows): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-4">No records found.</td>
              </tr>
            <?php else: ?>

              <?php if ($tab === 'claims'): ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold">#<?= h($r['claim_id']) ?></div>
                      <div class="small text-muted">Policy ID: <?= h($r['policy_id']) ?></div>
                    </td>
                    <td>
                      <div class="fw-semibold"><?= h($r['first_name'] . ' ' . $r['last_name']) ?></div>
                      <div class="small text-muted">
                        ID: <?= h($r['student_id']) ?> · <?= h($r['email']) ?>
                        <?= $r['phone'] ? " · " . h($r['phone']) : "" ?>
                      </div>
                    </td>
                    <td><?= h($r['provider_name']) ?></td>
                    <td>
                      <div class="fw-semibold"><?= h($r['policy_number']) ?></div>
                      <div class="small text-muted"><?= h($r['coverage_type']) ?> · <?= h($r['policy_status']) ?></div>
                    </td>
                    <td><?= h(fmtDate($r['claim_date'])) ?></td>
                    <td><?= h(number_format((float)$r['claim_amount'], 2)) ?></td>
                    <td>
                      <span class="badge <?= ($r['claim_status']==='Approved')?'bg-success':(($r['claim_status']==='Rejected')?'bg-danger':'bg-warning text-dark') ?>">
                        <?= h($r['claim_status']) ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <form method="post" class="d-inline-flex gap-2 align-items-center">
                        <input type="hidden" name="action" value="update_claim_status">
                        <input type="hidden" name="claim_id" value="<?= h($r['claim_id']) ?>">
                        <select name="claim_status" class="form-select form-select-sm" style="min-width: 140px;">
                          <?php foreach ($claimStatusOptions as $opt): ?>
                            <option value="<?= h($opt) ?>" <?= ($r['claim_status']===$opt)?'selected':'' ?>><?= h($opt) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">Update</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>

              <?php elseif ($tab === 'renewals'): ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold">#<?= h($r['renewal_id']) ?></div>
                      <div class="small text-muted">Policy ID: <?= h($r['policy_id']) ?></div>
                    </td>
                    <td>
                      <div class="fw-semibold"><?= h($r['first_name'] . ' ' . $r['last_name']) ?></div>
                      <div class="small text-muted">
                        ID: <?= h($r['student_id']) ?> · <?= h($r['email']) ?>
                        <?= $r['phone'] ? " · " . h($r['phone']) : "" ?>
                      </div>
                    </td>
                    <td><?= h($r['provider_name']) ?></td>
                    <td>
                      <div class="fw-semibold"><?= h($r['policy_number']) ?></div>
                      <div class="small text-muted">
                        Current End: <?= h(fmtDate($r['end_date'])) ?> · <?= h($r['coverage_type']) ?>
                      </div>
                    </td>
                    <td><?= h(fmtDate($r['renewal_date'])) ?></td>
                    <td><?= h(fmtDate($r['new_end_date'])) ?></td>
                    <td>
                      <span class="badge <?= ($r['renewal_status']==='Approved')?'bg-success':(($r['renewal_status']==='Rejected')?'bg-danger':'bg-warning text-dark') ?>">
                        <?= h($r['renewal_status']) ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <form method="post" class="d-inline-flex gap-2 align-items-center">
                        <input type="hidden" name="action" value="update_renewal_status">
                        <input type="hidden" name="renewal_id" value="<?= h($r['renewal_id']) ?>">
                        <select name="renewal_status" class="form-select form-select-sm" style="min-width: 140px;">
                          <?php foreach ($renewalStatusOptions as $opt): ?>
                            <option value="<?= h($opt) ?>" <?= ($r['renewal_status']===$opt)?'selected':'' ?>><?= h($opt) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">Update</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>

              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h($r['policy_number']) ?></div>
                      <div class="small text-muted">Policy ID: <?= h($r['policy_id']) ?></div>
                    </td>
                    <td>
                      <div class="fw-semibold"><?= h($r['first_name'] . ' ' . $r['last_name']) ?></div>
                      <div class="small text-muted">
                        ID: <?= h($r['student_id']) ?> · <?= h($r['email']) ?>
                        <?= $r['phone'] ? " · " . h($r['phone']) : "" ?>
                      </div>
                    </td>
                    <td><?= h($r['provider_name']) ?></td>
                    <td><?= h($r['coverage_type']) ?></td>
                    <td><?= h(fmtDate($r['start_date'])) ?></td>
                    <td><?= h(fmtDate($r['end_date'])) ?></td>
                    <td>
                      <span class="badge <?= ($r['status']==='Active')?'bg-success':'bg-secondary' ?>">
                        <?= h($r['status']) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>

            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <nav class="d-flex justify-content-between align-items-center mt-3">
          <div class="small text-muted">
            Page <?= h($page) ?> of <?= h($totalPages) ?>
          </div>
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= ($page<=1)?'disabled':'' ?>">
              <a class="page-link" href="<?= h(buildUrl(['page'=>max(1,$page-1)])) ?>">Prev</a>
            </li>

            <?php
              // Show compact page numbers
              $start = max(1, $page - 2);
              $end   = min($totalPages, $page + 2);
              if ($start > 1) {
                echo '<li class="page-item"><a class="page-link" href="'.h(buildUrl(['page'=>1])).'">1</a></li>';
                if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
              }
              for ($p=$start; $p<=$end; $p++) {
                $active = ($p===$page) ? 'active' : '';
                echo '<li class="page-item '.$active.'"><a class="page-link" href="'.h(buildUrl(['page'=>$p])).'">'.h($p).'</a></li>';
              }
              if ($end < $totalPages) {
                if ($end < $totalPages-1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                echo '<li class="page-item"><a class="page-link" href="'.h(buildUrl(['page'=>$totalPages])).'">'.h($totalPages).'</a></li>';
              }
            ?>

            <li class="page-item <?= ($page>=$totalPages)?'disabled':'' ?>">
              <a class="page-link" href="<?= h(buildUrl(['page'=>min($totalPages,$page+1)])) ?>">Next</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>

    </div>
  </div>

</div>

<?php require_once __DIR__ . "/footer.php"; ?>
