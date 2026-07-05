<?php
// staff/insurance.php
// Insurance Records & Claims (Staff/Admin) — with Create / Edit / Delete
//
// Tables used:
// - insurance_policy (policy_id, student_id, provider_id, policy_number, coverage_type, start_date, end_date, status, ...)
// - insurance_provider (provider_id, provider_name, ...)
// - insurance_renewal_record (renewal_id, policy_id, renewal_date, new_end_date, status, ...)
// - insurance_claim (claim_id, policy_id, claim_date, claim_amount, claim_status, ...)
// - student (student_id, first_name, last_name, email, phone, ...)
//
// Stored procedures (optional but supported):
// - sp_staff_update_claim_status(IN p_claim_id INT, IN p_status VARCHAR(20))
// - sp_staff_update_renewal_status(IN p_renewal_id INT, IN p_status VARCHAR(20))
//   (Recommended: if Approved => updates insurance_policy.end_date = renewal.new_end_date)

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

function computePolicyStatus(string $end_date): string {
    $today = strtotime(date('Y-m-d 00:00:00'));
    $end   = strtotime($end_date . ' 00:00:00');
    return ($end < $today) ? 'Expired' : 'Active';
}

// ------------------------------------------------------------
// CSRF
// ------------------------------------------------------------
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

// ------------------------------------------------------------
// POST actions BEFORE output
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $msg = "";
    $error = "";

    try {
        $postedToken = $_POST['csrf_token'] ?? '';
        if (!$postedToken || !hash_equals($_SESSION['csrf_token'] ?? '', $postedToken)) {
            throw new RuntimeException("Invalid request (CSRF). Please try again.");
        }

        // -------------------------------
        // CLAIM STATUS UPDATE (existing)
        // -------------------------------
        if ($action === "update_claim_status") {
            $claim_id = (int)($_POST['claim_id'] ?? 0);
            $new_status = trim($_POST['claim_status'] ?? "");

            if ($claim_id <= 0) throw new RuntimeException("Invalid claim id.");
            if (!in_array($new_status, ['Pending','Approved','Rejected'], true)) {
                throw new RuntimeException("Invalid claim status.");
            }

            $studentForClaim = 0;
            $lookup = $conn->prepare("SELECT ip.student_id FROM insurance_claim ic JOIN insurance_policy ip ON ip.policy_id = ic.policy_id WHERE ic.claim_id = ? LIMIT 1");
            if ($lookup) {
                $lookup->bind_param("i", $claim_id);
                $lookup->execute();
                $studentForClaim = (int)($lookup->get_result()->fetch_assoc()['student_id'] ?? 0);
                $lookup->close();
            }

            // Try procedure, fallback to direct update
            clearStoredResults($conn);
            $stmt = $conn->prepare("CALL sp_staff_update_claim_status(?, ?)");
            if ($stmt) {
                $stmt->bind_param("is", $claim_id, $new_status);
                if (!$stmt->execute()) throw new RuntimeException("Execute failed: " . $stmt->error);
                $stmt->close();
                clearStoredResults($conn);
            } else {
                clearStoredResults($conn);
                $stmt = $conn->prepare("UPDATE insurance_claim SET claim_status = ? WHERE claim_id = ? LIMIT 1");
                if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
                $stmt->bind_param("si", $new_status, $claim_id);
                if (!$stmt->execute()) throw new RuntimeException("Update failed: " . $stmt->error);
                $stmt->close();
            }

            $msg = "Claim status updated.";
            if ($studentForClaim > 0) {
                create_notification($conn, [
                    'student_id' => $studentForClaim,
                    'title' => 'Insurance claim status updated',
                    'message' => "Your insurance claim #{$claim_id} was updated to {$new_status}.",
                    'type' => 'insurance_claim_status',
                ]);
            }
            log_audit($conn, 'updated_insurance_claim_status', 'insurance_claim', $claim_id, "Claim status changed to {$new_status}.");
        }

        // -------------------------------
        // RENEWAL STATUS UPDATE (existing)
        // -------------------------------
        elseif ($action === "update_renewal_status") {
            $renewal_id = (int)($_POST['renewal_id'] ?? 0);
            $new_status = trim($_POST['renewal_status'] ?? "");

            if ($renewal_id <= 0) throw new RuntimeException("Invalid renewal id.");
            if (!in_array($new_status, ['Pending','Approved','Rejected'], true)) {
                throw new RuntimeException("Invalid renewal status.");
            }

            $studentForRenewal = 0;
            $lookup = $conn->prepare("SELECT ip.student_id FROM insurance_renewal_record irr JOIN insurance_policy ip ON ip.policy_id = irr.policy_id WHERE irr.renewal_id = ? LIMIT 1");
            if ($lookup) {
                $lookup->bind_param("i", $renewal_id);
                $lookup->execute();
                $studentForRenewal = (int)($lookup->get_result()->fetch_assoc()['student_id'] ?? 0);
                $lookup->close();
            }

            // Try procedure, fallback to direct update + (optional) update policy end_date if Approved
            clearStoredResults($conn);
            $stmt = $conn->prepare("CALL sp_staff_update_renewal_status(?, ?)");
            if ($stmt) {
                $stmt->bind_param("is", $renewal_id, $new_status);
                if (!$stmt->execute()) throw new RuntimeException("Execute failed: " . $stmt->error);
                $stmt->close();
                clearStoredResults($conn);
            } else {
                clearStoredResults($conn);
                $stmt = $conn->prepare("UPDATE insurance_renewal_record SET status = ? WHERE renewal_id = ? LIMIT 1");
                if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
                $stmt->bind_param("si", $new_status, $renewal_id);
                if (!$stmt->execute()) throw new RuntimeException("Update failed: " . $stmt->error);
                $stmt->close();

                if ($new_status === 'Approved') {
                    // Update policy end_date to new_end_date
                    $stmt = $conn->prepare("
                        UPDATE insurance_policy ip
                        JOIN insurance_renewal_record rr ON rr.policy_id = ip.policy_id
                        SET ip.end_date = rr.new_end_date, ip.status = 'Active'
                        WHERE rr.renewal_id = ?
                        LIMIT 1
                    ");
                    if ($stmt) {
                        $stmt->bind_param("i", $renewal_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }

            $msg = "Renewal status updated.";
            if ($studentForRenewal > 0) {
                create_notification($conn, [
                    'student_id' => $studentForRenewal,
                    'title' => 'Insurance renewal status updated',
                    'message' => "Your insurance renewal #{$renewal_id} was updated to {$new_status}.",
                    'type' => 'insurance_renewal_status',
                ]);
            }
            log_audit($conn, 'updated_insurance_renewal_status', 'insurance_renewal_record', $renewal_id, "Renewal status changed to {$new_status}.");
        }

        // -------------------------------
        // POLICY CREATE
        // -------------------------------
        elseif ($action === "create_policy") {
            $student_id    = (int)($_POST['student_id'] ?? 0);
            $provider_id   = (int)($_POST['provider_id'] ?? 0);
            $policy_number = trim($_POST['policy_number'] ?? '');
            $coverage_type = trim($_POST['coverage_type'] ?? '');
            $start_date    = trim($_POST['start_date'] ?? '');
            $end_date      = trim($_POST['end_date'] ?? '');

            if ($student_id <= 0) throw new RuntimeException("Student is required.");
            if ($provider_id <= 0) throw new RuntimeException("Provider is required.");
            if ($policy_number === '') throw new RuntimeException("Policy number is required.");
            if ($coverage_type === '') throw new RuntimeException("Coverage type is required.");
            if ($start_date === '' || $end_date === '') throw new RuntimeException("Start/End date is required.");

            $status = computePolicyStatus($end_date);

            $stmt = $conn->prepare("
                INSERT INTO insurance_policy (student_id, provider_id, policy_number, coverage_type, start_date, end_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("iisssss", $student_id, $provider_id, $policy_number, $coverage_type, $start_date, $end_date, $status);
            if (!$stmt->execute()) throw new RuntimeException("Insert failed: " . $stmt->error);
            $stmt->close();

            $msg = "Policy created.";
        }

        // -------------------------------
        // POLICY UPDATE (Edit)
        // -------------------------------
        elseif ($action === "update_policy") {
            $policy_id     = (int)($_POST['policy_id'] ?? 0);
            $student_id    = (int)($_POST['student_id'] ?? 0);
            $provider_id   = (int)($_POST['provider_id'] ?? 0);
            $policy_number = trim($_POST['policy_number'] ?? '');
            $coverage_type = trim($_POST['coverage_type'] ?? '');
            $start_date    = trim($_POST['start_date'] ?? '');
            $end_date      = trim($_POST['end_date'] ?? '');

            if ($policy_id <= 0) throw new RuntimeException("Invalid policy id.");
            if ($student_id <= 0) throw new RuntimeException("Student is required.");
            if ($provider_id <= 0) throw new RuntimeException("Provider is required.");
            if ($policy_number === '') throw new RuntimeException("Policy number is required.");
            if ($coverage_type === '') throw new RuntimeException("Coverage type is required.");
            if ($start_date === '' || $end_date === '') throw new RuntimeException("Start/End date is required.");

            $status = computePolicyStatus($end_date);

            $stmt = $conn->prepare("
                UPDATE insurance_policy
                SET student_id = ?, provider_id = ?, policy_number = ?, coverage_type = ?, start_date = ?, end_date = ?, status = ?
                WHERE policy_id = ?
                LIMIT 1
            ");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("iisssssi", $student_id, $provider_id, $policy_number, $coverage_type, $start_date, $end_date, $status, $policy_id);
            if (!$stmt->execute()) throw new RuntimeException("Update failed: " . $stmt->error);
            $stmt->close();

            $msg = "Policy updated.";
        }

        // -------------------------------
        // POLICY DELETE
        // -------------------------------
        elseif ($action === "delete_policy") {
            $policy_id = (int)($_POST['policy_id'] ?? 0);
            if ($policy_id <= 0) throw new RuntimeException("Invalid policy id.");

            // Note: may fail if FK constraints exist (claims/renewals). Handle error message.
            $stmt = $conn->prepare("DELETE FROM insurance_policy WHERE policy_id = ? LIMIT 1");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("i", $policy_id);
            if (!$stmt->execute()) throw new RuntimeException("Delete failed: " . $stmt->error);
            $stmt->close();

            $msg = "Policy deleted.";
        }

        // -------------------------------
        // CLAIM CREATE
        // -------------------------------
        elseif ($action === "create_claim") {
            $policy_id    = (int)($_POST['policy_id'] ?? 0);
            $claim_date   = trim($_POST['claim_date'] ?? '');
            $claim_amount = trim($_POST['claim_amount'] ?? '');
            $claim_status = trim($_POST['claim_status'] ?? 'Pending');

            if ($policy_id <= 0) throw new RuntimeException("Policy is required.");
            if ($claim_date === '') throw new RuntimeException("Claim date is required.");
            if ($claim_amount === '' || !is_numeric($claim_amount) || (float)$claim_amount < 0) throw new RuntimeException("Invalid claim amount.");
            if (!in_array($claim_status, ['Pending','Approved','Rejected'], true)) throw new RuntimeException("Invalid claim status.");

            $amt = (float)$claim_amount;

            $stmt = $conn->prepare("
                INSERT INTO insurance_claim (policy_id, claim_date, claim_amount, claim_status)
                VALUES (?, ?, ?, ?)
            ");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("isds", $policy_id, $claim_date, $amt, $claim_status);
            if (!$stmt->execute()) throw new RuntimeException("Insert failed: " . $stmt->error);
            $stmt->close();

            $msg = "Claim created.";
        }

        // -------------------------------
        // CLAIM UPDATE (Edit amount/date/status)
        // -------------------------------
        elseif ($action === "update_claim") {
            $claim_id     = (int)($_POST['claim_id'] ?? 0);
            $policy_id    = (int)($_POST['policy_id'] ?? 0);
            $claim_date   = trim($_POST['claim_date'] ?? '');
            $claim_amount = trim($_POST['claim_amount'] ?? '');
            $claim_status = trim($_POST['claim_status'] ?? 'Pending');

            if ($claim_id <= 0) throw new RuntimeException("Invalid claim id.");
            if ($policy_id <= 0) throw new RuntimeException("Policy is required.");
            if ($claim_date === '') throw new RuntimeException("Claim date is required.");
            if ($claim_amount === '' || !is_numeric($claim_amount) || (float)$claim_amount < 0) throw new RuntimeException("Invalid claim amount.");
            if (!in_array($claim_status, ['Pending','Approved','Rejected'], true)) throw new RuntimeException("Invalid claim status.");

            $amt = (float)$claim_amount;

            $stmt = $conn->prepare("
                UPDATE insurance_claim
                SET policy_id = ?, claim_date = ?, claim_amount = ?, claim_status = ?
                WHERE claim_id = ?
                LIMIT 1
            ");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("isdsi", $policy_id, $claim_date, $amt, $claim_status, $claim_id);
            if (!$stmt->execute()) throw new RuntimeException("Update failed: " . $stmt->error);
            $stmt->close();

            $msg = "Claim updated.";
        }

        // -------------------------------
        // CLAIM DELETE
        // -------------------------------
        elseif ($action === "delete_claim") {
            $claim_id = (int)($_POST['claim_id'] ?? 0);
            if ($claim_id <= 0) throw new RuntimeException("Invalid claim id.");

            $stmt = $conn->prepare("DELETE FROM insurance_claim WHERE claim_id = ? LIMIT 1");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("i", $claim_id);
            if (!$stmt->execute()) throw new RuntimeException("Delete failed: " . $stmt->error);
            $stmt->close();

            $msg = "Claim deleted.";
        }

        // -------------------------------
        // RENEWAL CREATE
        // -------------------------------
        elseif ($action === "create_renewal") {
            $policy_id    = (int)($_POST['policy_id'] ?? 0);
            $renewal_date = trim($_POST['renewal_date'] ?? '');
            $new_end_date = trim($_POST['new_end_date'] ?? '');
            $r_status     = trim($_POST['renewal_status'] ?? 'Pending');

            if ($policy_id <= 0) throw new RuntimeException("Policy is required.");
            if ($renewal_date === '') throw new RuntimeException("Renewal date is required.");
            if ($new_end_date === '') throw new RuntimeException("New end date is required.");
            if (!in_array($r_status, ['Pending','Approved','Rejected'], true)) throw new RuntimeException("Invalid renewal status.");

            $stmt = $conn->prepare("
                INSERT INTO insurance_renewal_record (policy_id, renewal_date, new_end_date, status)
                VALUES (?, ?, ?, ?)
            ");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("isss", $policy_id, $renewal_date, $new_end_date, $r_status);
            if (!$stmt->execute()) throw new RuntimeException("Insert failed: " . $stmt->error);
            $stmt->close();

            // If approved, also update policy end_date now (nice behavior)
            if ($r_status === 'Approved') {
                $stmt = $conn->prepare("
                    UPDATE insurance_policy
                    SET end_date = ?, status = 'Active'
                    WHERE policy_id = ?
                    LIMIT 1
                ");
                if ($stmt) {
                    $stmt->bind_param("si", $new_end_date, $policy_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $msg = "Renewal created.";
        }

        // -------------------------------
        // RENEWAL UPDATE (Edit)
        // -------------------------------
        elseif ($action === "update_renewal") {
            $renewal_id   = (int)($_POST['renewal_id'] ?? 0);
            $policy_id    = (int)($_POST['policy_id'] ?? 0);
            $renewal_date = trim($_POST['renewal_date'] ?? '');
            $new_end_date = trim($_POST['new_end_date'] ?? '');
            $r_status     = trim($_POST['renewal_status'] ?? 'Pending');

            if ($renewal_id <= 0) throw new RuntimeException("Invalid renewal id.");
            if ($policy_id <= 0) throw new RuntimeException("Policy is required.");
            if ($renewal_date === '') throw new RuntimeException("Renewal date is required.");
            if ($new_end_date === '') throw new RuntimeException("New end date is required.");
            if (!in_array($r_status, ['Pending','Approved','Rejected'], true)) throw new RuntimeException("Invalid renewal status.");

            $stmt = $conn->prepare("
                UPDATE insurance_renewal_record
                SET policy_id = ?, renewal_date = ?, new_end_date = ?, status = ?
                WHERE renewal_id = ?
                LIMIT 1
            ");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("isssi", $policy_id, $renewal_date, $new_end_date, $r_status, $renewal_id);
            if (!$stmt->execute()) throw new RuntimeException("Update failed: " . $stmt->error);
            $stmt->close();

            // If approved, apply to policy
            if ($r_status === 'Approved') {
                $stmt = $conn->prepare("
                    UPDATE insurance_policy
                    SET end_date = ?, status = 'Active'
                    WHERE policy_id = ?
                    LIMIT 1
                ");
                if ($stmt) {
                    $stmt->bind_param("si", $new_end_date, $policy_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $msg = "Renewal updated.";
        }

        // -------------------------------
        // RENEWAL DELETE
        // -------------------------------
        elseif ($action === "delete_renewal") {
            $renewal_id = (int)($_POST['renewal_id'] ?? 0);
            if ($renewal_id <= 0) throw new RuntimeException("Invalid renewal id.");

            $stmt = $conn->prepare("DELETE FROM insurance_renewal_record WHERE renewal_id = ? LIMIT 1");
            if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);
            $stmt->bind_param("i", $renewal_id);
            if (!$stmt->execute()) throw new RuntimeException("Delete failed: " . $stmt->error);
            $stmt->close();

            $msg = "Renewal deleted.";
        }

        else {
            throw new RuntimeException("Unknown action.");
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    // Keep tab and filters if possible
    $url = buildUrl([]);
    $sep = (strpos($url, '?') !== false) ? "&" : "?";
    if ($msg)   $url .= $sep . "msg=" . urlencode($msg);
    if ($error) $url .= ($msg ? "&" : $sep) . "error=" . urlencode($error);
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
$status   = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['from'] ?? '');
$dateTo   = trim($_GET['to'] ?? '');

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

// Options
$policyStatusOptions  = ["Active","Expired"];
$claimStatusOptions   = ["Pending","Approved","Rejected"];
$renewalStatusOptions = ["Pending","Approved","Rejected"];

// Data
$providers = [];
$studentOptions = [];
$policyOptions = []; // for creating claims/renewals
$rows = [];
$total = 0;

// Load providers
try {
    $res = $conn->query("SELECT provider_id, provider_name FROM insurance_provider ORDER BY provider_name ASC");
    if ($res) {
        $providers = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
    }
} catch (Throwable $e) {
    $error = $error ?: ("Failed to load providers: " . $e->getMessage());
}

// Load students (light list for create/edit modals)
try {
    $res = $conn->query("SELECT student_id, first_name, last_name, email FROM student ORDER BY student_id DESC LIMIT 500");
    if ($res) {
        $studentOptions = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
    }
} catch (Throwable $e) {
    $error = $error ?: ("Failed to load students: " . $e->getMessage());
}

// Load policies list for claim/renewal dropdown (compact)
try {
    $res = $conn->query("
        SELECT ip.policy_id, ip.policy_number, s.student_id, s.first_name, s.last_name
        FROM insurance_policy ip
        JOIN student s ON s.student_id = ip.student_id
        ORDER BY ip.policy_id DESC
        LIMIT 500
    ");
    if ($res) {
        $policyOptions = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
    }
} catch (Throwable $e) {
    $error = $error ?: ("Failed to load policies: " . $e->getMessage());
}

// ------------------------------------------------------------
// Build list queries
// ------------------------------------------------------------
try {
    $where = [];
    $types = "";
    $params = [];

    if ($q !== "") {
        $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR ip.policy_number LIKE ?)";
        $like = "%" . $q . "%";
        $types .= "ssss";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }

    if ($student !== "" && ctype_digit($student)) {
        $where[] = "s.student_id = ?";
        $types .= "i";
        $params[] = (int)$student;
    }

    if ($provider !== "" && ctype_digit($provider)) {
        $where[] = "ip.provider_id = ?";
        $types .= "i";
        $params[] = (int)$provider;
    }

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

    if ($dateFrom !== "") {
        if ($tab === "claims")       $where[] = "ic.claim_date >= ?";
        elseif ($tab === "renewals") $where[] = "rr.renewal_date >= ?";
        else                         $where[] = "ip.end_date >= ?";
        $types .= "s";
        $params[] = $dateFrom;
    }

    if ($dateTo !== "") {
        if ($tab === "claims")       $where[] = "ic.claim_date <= ?";
        elseif ($tab === "renewals") $where[] = "rr.renewal_date <= ?";
        else                         $where[] = "ip.end_date <= ?";
        $types .= "s";
        $params[] = $dateTo;
    }

    $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

    if ($tab === "claims") {
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
      <div class="text-muted small">View policies, claims, and renewals. Create/Edit/Delete + update statuses.</div>
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

  <!-- Top actions -->
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="text-muted small">
      Showing <?= h(min($total, $offset + 1)) ?>–<?= h(min($total, $offset + $limit)) ?> of <?= h($total) ?>
    </div>

    <div>
      <?php if ($tab === 'policies'): ?>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#policyModal" id="openCreatePolicy">
          <i class="bi bi-plus-circle"></i> Create Policy
        </button>
      <?php elseif ($tab === 'claims'): ?>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#claimModal" id="openCreateClaim">
          <i class="bi bi-plus-circle"></i> Create Claim
        </button>
      <?php else: ?>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#renewalModal" id="openCreateRenewal">
          <i class="bi bi-plus-circle"></i> Create Renewal
        </button>
      <?php endif; ?>
    </div>
  </div>

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
                <th class="text-end">Actions</th>
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
                <th class="text-end">Actions</th>
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
                <th class="text-end">Actions</th>
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
                  <?php
                    $badge = ($r['claim_status']==='Approved')?'bg-success':(($r['claim_status']==='Rejected')?'bg-danger':'bg-warning text-dark');
                  ?>
                  <tr>
                    <td>
                      <div class="fw-semibold">#<?= h($r['claim_id']) ?></div>
                      <div class="small text-muted">Policy ID: <?= h($r['policy_id']) ?></div>
                    </td>
                    <td>
                      <div class="fw-semibold"><?= h($r['first_name'] . ' ' . $r['last_name']) ?></div>
                      <div class="small text-muted">ID: <?= h($r['student_id']) ?> · <?= h($r['email']) ?><?= $r['phone'] ? " · " . h($r['phone']) : "" ?></div>
                    </td>
                    <td><?= h($r['provider_name']) ?></td>
                    <td>
                      <div class="fw-semibold"><?= h($r['policy_number']) ?></div>
                      <div class="small text-muted"><?= h($r['coverage_type']) ?> · <?= h($r['policy_status']) ?></div>
                    </td>
                    <td><?= h(fmtDate($r['claim_date'])) ?></td>
                    <td><?= h(number_format((float)$r['claim_amount'], 2)) ?></td>
                    <td><span class="badge <?= h($badge) ?>"><?= h($r['claim_status']) ?></span></td>

                    <td class="text-end">
                      <div class="d-flex justify-content-end gap-2 flex-wrap">

                        <!-- Edit -->
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-primary open-edit-claim"
                          data-bs-toggle="modal"
                          data-bs-target="#claimModal"
                          data-claim_id="<?= h($r['claim_id']) ?>"
                          data-policy_id="<?= h($r['policy_id']) ?>"
                          data-claim_date="<?= h($r['claim_date']) ?>"
                          data-claim_amount="<?= h($r['claim_amount']) ?>"
                          data-claim_status="<?= h($r['claim_status']) ?>"
                        >
                          Edit
                        </button>

                        <!-- Status quick update (optional) -->
                        <form method="post" class="d-inline-flex gap-2 align-items-center">
                          <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                          <input type="hidden" name="action" value="update_claim_status">
                          <input type="hidden" name="claim_id" value="<?= h($r['claim_id']) ?>">
                          <select name="claim_status" class="form-select form-select-sm" style="min-width: 140px;">
                            <?php foreach ($claimStatusOptions as $opt): ?>
                              <option value="<?= h($opt) ?>" <?= ($r['claim_status']===$opt)?'selected':'' ?>><?= h($opt) ?></option>
                            <?php endforeach; ?>
                          </select>
                          <button type="submit" class="btn btn-sm btn-primary">Update</button>
                        </form>

                        <!-- Delete -->
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-danger open-delete"
                          data-bs-toggle="modal"
                          data-bs-target="#deleteModal"
                          data-delete_action="delete_claim"
                          data-id_field="claim_id"
                          data-id_value="<?= h($r['claim_id']) ?>"
                          data-title="Delete Claim #<?= h($r['claim_id']) ?>?"
                          data-body="This will permanently delete this claim. This action cannot be undone."
                        >
                          Delete
                        </button>

                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>

              <?php elseif ($tab === 'renewals'): ?>
                <?php foreach ($rows as $r): ?>
                  <?php
                    $badge = ($r['renewal_status']==='Approved')?'bg-success':(($r['renewal_status']==='Rejected')?'bg-danger':'bg-warning text-dark');
                  ?>
                  <tr>
                    <td>
                      <div class="fw-semibold">#<?= h($r['renewal_id']) ?></div>
                      <div class="small text-muted">Policy ID: <?= h($r['policy_id']) ?></div>
                    </td>
                    <td>
                      <div class="fw-semibold"><?= h($r['first_name'] . ' ' . $r['last_name']) ?></div>
                      <div class="small text-muted">ID: <?= h($r['student_id']) ?> · <?= h($r['email']) ?><?= $r['phone'] ? " · " . h($r['phone']) : "" ?></div>
                    </td>
                    <td><?= h($r['provider_name']) ?></td>
                    <td>
                      <div class="fw-semibold"><?= h($r['policy_number']) ?></div>
                      <div class="small text-muted">Current End: <?= h(fmtDate($r['end_date'])) ?> · <?= h($r['coverage_type']) ?></div>
                    </td>
                    <td><?= h(fmtDate($r['renewal_date'])) ?></td>
                    <td><?= h(fmtDate($r['new_end_date'])) ?></td>
                    <td><span class="badge <?= h($badge) ?>"><?= h($r['renewal_status']) ?></span></td>

                    <td class="text-end">
                      <div class="d-flex justify-content-end gap-2 flex-wrap">

                        <!-- Edit -->
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-primary open-edit-renewal"
                          data-bs-toggle="modal"
                          data-bs-target="#renewalModal"
                          data-renewal_id="<?= h($r['renewal_id']) ?>"
                          data-policy_id="<?= h($r['policy_id']) ?>"
                          data-renewal_date="<?= h($r['renewal_date']) ?>"
                          data-new_end_date="<?= h($r['new_end_date']) ?>"
                          data-renewal_status="<?= h($r['renewal_status']) ?>"
                        >
                          Edit
                        </button>

                        <!-- Status quick update -->
                        <form method="post" class="d-inline-flex gap-2 align-items-center">
                          <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                          <input type="hidden" name="action" value="update_renewal_status">
                          <input type="hidden" name="renewal_id" value="<?= h($r['renewal_id']) ?>">
                          <select name="renewal_status" class="form-select form-select-sm" style="min-width: 140px;">
                            <?php foreach ($renewalStatusOptions as $opt): ?>
                              <option value="<?= h($opt) ?>" <?= ($r['renewal_status']===$opt)?'selected':'' ?>><?= h($opt) ?></option>
                            <?php endforeach; ?>
                          </select>
                          <button type="submit" class="btn btn-sm btn-primary">Update</button>
                        </form>

                        <!-- Delete -->
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-danger open-delete"
                          data-bs-toggle="modal"
                          data-bs-target="#deleteModal"
                          data-delete_action="delete_renewal"
                          data-id_field="renewal_id"
                          data-id_value="<?= h($r['renewal_id']) ?>"
                          data-title="Delete Renewal #<?= h($r['renewal_id']) ?>?"
                          data-body="This will permanently delete this renewal record. This action cannot be undone."
                        >
                          Delete
                        </button>

                      </div>
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
                      <div class="small text-muted">ID: <?= h($r['student_id']) ?> · <?= h($r['email']) ?><?= $r['phone'] ? " · " . h($r['phone']) : "" ?></div>
                    </td>
                    <td><?= h($r['provider_name']) ?></td>
                    <td><?= h($r['coverage_type']) ?></td>
                    <td><?= h(fmtDate($r['start_date'])) ?></td>
                    <td><?= h(fmtDate($r['end_date'])) ?></td>
                    <td><span class="badge <?= ($r['status']==='Active')?'bg-success':'bg-secondary' ?>"><?= h($r['status']) ?></span></td>

                    <td class="text-end">
                      <div class="d-flex justify-content-end gap-2">

                        <!-- Edit -->
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-primary open-edit-policy"
                          data-bs-toggle="modal"
                          data-bs-target="#policyModal"
                          data-policy_id="<?= h($r['policy_id']) ?>"
                          data-student_id="<?= h($r['student_id']) ?>"
                          data-provider_id="<?= h($r['provider_id']) ?>"
                          data-policy_number="<?= h($r['policy_number']) ?>"
                          data-coverage_type="<?= h($r['coverage_type']) ?>"
                          data-start_date="<?= h($r['start_date']) ?>"
                          data-end_date="<?= h($r['end_date']) ?>"
                        >
                          Edit
                        </button>

                        <!-- Delete -->
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-danger open-delete"
                          data-bs-toggle="modal"
                          data-bs-target="#deleteModal"
                          data-delete_action="delete_policy"
                          data-id_field="policy_id"
                          data-id_value="<?= h($r['policy_id']) ?>"
                          data-title="Delete Policy #<?= h($r['policy_id']) ?>?"
                          data-body="If this policy has claims/renewals, deletion may fail due to database constraints. This action cannot be undone."
                        >
                          Delete
                        </button>

                      </div>
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
          <div class="small text-muted">Page <?= h($page) ?> of <?= h($totalPages) ?></div>
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= ($page<=1)?'disabled':'' ?>">
              <a class="page-link" href="<?= h(buildUrl(['page'=>max(1,$page-1)])) ?>">Prev</a>
            </li>
            <?php
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

<!-- =======================
     POLICY MODAL (Create/Edit)
======================= -->
<div class="modal fade" id="policyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post" id="policyForm">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
        <input type="hidden" name="action" id="policy_action" value="create_policy">
        <input type="hidden" name="policy_id" id="policy_id" value="0">

        <div class="modal-header">
          <h5 class="modal-title" id="policyModalTitle">Create Policy</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label">Student</label>
              <select class="form-select" name="student_id" id="policy_student_id" required>
                <option value="">-- Select --</option>
                <?php foreach ($studentOptions as $s): ?>
                  <?php
                    $sid = (int)$s['student_id'];
                    $nm = trim(($s['first_name'] ?? '').' '.($s['last_name'] ?? ''));
                    $label = $nm ? ($nm . " (ID: $sid)") : ("Student $sid");
                  ?>
                  <option value="<?= h($sid) ?>"><?= h($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Provider</label>
              <select class="form-select" name="provider_id" id="policy_provider_id" required>
                <option value="">-- Select --</option>
                <?php foreach ($providers as $p): ?>
                  <option value="<?= h($p['provider_id']) ?>"><?= h($p['provider_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Policy Number</label>
              <input class="form-control" name="policy_number" id="policy_number" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Coverage Type</label>
              <input class="form-control" name="coverage_type" id="coverage_type" required placeholder="e.g., Medical">
            </div>

            <div class="col-md-6">
              <label class="form-label">Start Date</label>
              <input type="date" class="form-control" name="start_date" id="policy_start_date" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">End Date</label>
              <input type="date" class="form-control" name="end_date" id="policy_end_date" required>
            </div>

            <div class="col-12">
              <div class="text-muted small">
                Status is auto-calculated from End Date (Active/Expired).
              </div>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="policySubmitBtn">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =======================
     CLAIM MODAL (Create/Edit)
======================= -->
<div class="modal fade" id="claimModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post" id="claimForm">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
        <input type="hidden" name="action" id="claim_action" value="create_claim">
        <input type="hidden" name="claim_id" id="claim_id" value="0">

        <div class="modal-header">
          <h5 class="modal-title" id="claimModalTitle">Create Claim</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label">Policy</label>
              <select class="form-select" name="policy_id" id="claim_policy_id" required>
                <option value="">-- Select --</option>
                <?php foreach ($policyOptions as $p): ?>
                  <?php
                    $pid = (int)$p['policy_id'];
                    $nm = trim(($p['first_name'] ?? '').' '.($p['last_name'] ?? ''));
                    $label = h($p['policy_number']) . " • " . h($nm) . " (Student " . h($p['student_id']) . ")";
                  ?>
                  <option value="<?= h($pid) ?>"><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label">Claim Date</label>
              <input type="date" class="form-control" name="claim_date" id="claim_date" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">Claim Amount (RM)</label>
              <input type="number" step="0.01" min="0" class="form-control" name="claim_amount" id="claim_amount" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select class="form-select" name="claim_status" id="claim_status" required>
                <?php foreach ($claimStatusOptions as $opt): ?>
                  <option value="<?= h($opt) ?>"><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="claimSubmitBtn">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =======================
     RENEWAL MODAL (Create/Edit)
======================= -->
<div class="modal fade" id="renewalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post" id="renewalForm">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
        <input type="hidden" name="action" id="renewal_action" value="create_renewal">
        <input type="hidden" name="renewal_id" id="renewal_id" value="0">

        <div class="modal-header">
          <h5 class="modal-title" id="renewalModalTitle">Create Renewal</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label">Policy</label>
              <select class="form-select" name="policy_id" id="renewal_policy_id" required>
                <option value="">-- Select --</option>
                <?php foreach ($policyOptions as $p): ?>
                  <?php
                    $pid = (int)$p['policy_id'];
                    $nm = trim(($p['first_name'] ?? '').' '.($p['last_name'] ?? ''));
                    $label = h($p['policy_number']) . " • " . h($nm) . " (Student " . h($p['student_id']) . ")";
                  ?>
                  <option value="<?= h($pid) ?>"><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label">Renewal Date</label>
              <input type="date" class="form-control" name="renewal_date" id="renewal_date" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">New End Date</label>
              <input type="date" class="form-control" name="new_end_date" id="new_end_date" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select class="form-select" name="renewal_status" id="renewal_status" required>
                <?php foreach ($renewalStatusOptions as $opt): ?>
                  <option value="<?= h($opt) ?>"><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <div class="text-muted small">
                If you set status to <b>Approved</b>, policy end date will be updated to the new end date.
              </div>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="renewalSubmitBtn">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =======================
     DELETE CONFIRM MODAL (reusable)
======================= -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" id="deleteForm">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
        <input type="hidden" name="action" id="delete_action" value="">
        <input type="hidden" name="" id="delete_id_field" value="">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteTitle">Confirm Delete</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="deleteBody">Are you sure?</div>
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
  // -------------------------
  // Create buttons reset modals
  // -------------------------
  const openCreatePolicy = document.getElementById('openCreatePolicy');
  if (openCreatePolicy) {
    openCreatePolicy.addEventListener('click', () => {
      document.getElementById('policyModalTitle').textContent = 'Create Policy';
      document.getElementById('policy_action').value = 'create_policy';
      document.getElementById('policy_id').value = '0';
      document.getElementById('policy_student_id').value = '';
      document.getElementById('policy_provider_id').value = '';
      document.getElementById('policy_number').value = '';
      document.getElementById('coverage_type').value = '';
      document.getElementById('policy_start_date').value = '';
      document.getElementById('policy_end_date').value = '';
      document.getElementById('policySubmitBtn').textContent = 'Create';
    });
  }

  const openCreateClaim = document.getElementById('openCreateClaim');
  if (openCreateClaim) {
    openCreateClaim.addEventListener('click', () => {
      document.getElementById('claimModalTitle').textContent = 'Create Claim';
      document.getElementById('claim_action').value = 'create_claim';
      document.getElementById('claim_id').value = '0';
      document.getElementById('claim_policy_id').value = '';
      document.getElementById('claim_date').value = '';
      document.getElementById('claim_amount').value = '';
      document.getElementById('claim_status').value = 'Pending';
      document.getElementById('claimSubmitBtn').textContent = 'Create';
    });
  }

  const openCreateRenewal = document.getElementById('openCreateRenewal');
  if (openCreateRenewal) {
    openCreateRenewal.addEventListener('click', () => {
      document.getElementById('renewalModalTitle').textContent = 'Create Renewal';
      document.getElementById('renewal_action').value = 'create_renewal';
      document.getElementById('renewal_id').value = '0';
      document.getElementById('renewal_policy_id').value = '';
      document.getElementById('renewal_date').value = '';
      document.getElementById('new_end_date').value = '';
      document.getElementById('renewal_status').value = 'Pending';
      document.getElementById('renewalSubmitBtn').textContent = 'Create';
    });
  }

  // -------------------------
  // Edit Policy
  // -------------------------
  document.querySelectorAll('.open-edit-policy').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('policyModalTitle').textContent = 'Edit Policy';
      document.getElementById('policy_action').value = 'update_policy';
      document.getElementById('policy_id').value = btn.dataset.policy_id || '0';
      document.getElementById('policy_student_id').value = btn.dataset.student_id || '';
      document.getElementById('policy_provider_id').value = btn.dataset.provider_id || '';
      document.getElementById('policy_number').value = btn.dataset.policy_number || '';
      document.getElementById('coverage_type').value = btn.dataset.coverage_type || '';
      document.getElementById('policy_start_date').value = btn.dataset.start_date || '';
      document.getElementById('policy_end_date').value = btn.dataset.end_date || '';
      document.getElementById('policySubmitBtn').textContent = 'Save Changes';
    });
  });

  // -------------------------
  // Edit Claim
  // -------------------------
  document.querySelectorAll('.open-edit-claim').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('claimModalTitle').textContent = 'Edit Claim';
      document.getElementById('claim_action').value = 'update_claim';
      document.getElementById('claim_id').value = btn.dataset.claim_id || '0';
      document.getElementById('claim_policy_id').value = btn.dataset.policy_id || '';
      document.getElementById('claim_date').value = btn.dataset.claim_date || '';
      document.getElementById('claim_amount').value = btn.dataset.claim_amount || '';
      document.getElementById('claim_status').value = btn.dataset.claim_status || 'Pending';
      document.getElementById('claimSubmitBtn').textContent = 'Save Changes';
    });
  });

  // -------------------------
  // Edit Renewal
  // -------------------------
  document.querySelectorAll('.open-edit-renewal').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('renewalModalTitle').textContent = 'Edit Renewal';
      document.getElementById('renewal_action').value = 'update_renewal';
      document.getElementById('renewal_id').value = btn.dataset.renewal_id || '0';
      document.getElementById('renewal_policy_id').value = btn.dataset.policy_id || '';
      document.getElementById('renewal_date').value = btn.dataset.renewal_date || '';
      document.getElementById('new_end_date').value = btn.dataset.new_end_date || '';
      document.getElementById('renewal_status').value = btn.dataset.renewal_status || 'Pending';
      document.getElementById('renewalSubmitBtn').textContent = 'Save Changes';
    });
  });

  // -------------------------
  // Delete Modal (reusable)
  // -------------------------
  const deleteForm = document.getElementById('deleteForm');
  const deleteAction = document.getElementById('delete_action');
  const deleteIdField = document.getElementById('delete_id_field');
  const deleteTitle = document.getElementById('deleteTitle');
  const deleteBody = document.getElementById('deleteBody');

  document.querySelectorAll('.open-delete').forEach(btn => {
    btn.addEventListener('click', () => {
      const action = btn.dataset.delete_action || '';
      const idField = btn.dataset.id_field || '';
      const idValue = btn.dataset.id_value || '';
      const title = btn.dataset.title || 'Confirm Delete';
      const body = btn.dataset.body || 'Are you sure?';

      deleteTitle.textContent = title;
      deleteBody.textContent = body;

      deleteAction.value = action;

      // set the hidden input name dynamically
      deleteIdField.name = idField;
      deleteIdField.value = idValue;
    });
  });
})();
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
