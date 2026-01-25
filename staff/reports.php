<?php
// staff/reports.php
$page_title = "Reports Center - ISU";
require_once __DIR__ . "/header.php"; // session + db ($conn) + $staff_id

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function clearStoredResults(mysqli $conn): void {
    while ($conn->more_results()) {
        $conn->next_result();
        if ($res = $conn->store_result()) {
            $res->free();
        }
    }
}

function fmtDate($d): string {
    if (!$d) return "-";
    $t = strtotime((string)$d);
    return $t ? date("d M Y", $t) : (string)$d;
}

function badgeDays(?int $days): array {
    if ($days === null) return ["secondary", "Unknown"];
    if ($days < 0) return ["danger", "Expired"];
    if ($days <= 30) return ["warning", $days . " days"];
    return ["success", $days . " days"];
}

function daysUntil($d): ?int {
    if (!$d) return null;
    $t = strtotime((string)$d);
    if (!$t) return null;
    $now = time();
    return (int)floor(($t - $now) / 86400);
}

$tab = $_GET["tab"] ?? "visa_expiry";

$success = "";
$error = "";

// ------------------------------------------------------------
// Optional: Run reminder generation procedure (button action)
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "run_visa_reminders") {
    try {
        $stmt = $conn->prepare("CALL sp_generate_visa_expiry_reminders()");
        $stmt->execute();
        $stmt->close();
        clearStoredResults($conn);
        $success = "Visa expiry reminders generated successfully.";
        $tab = "run_reminders";
    } catch (Throwable $e) {
        $error = "Failed to run reminders: " . $e->getMessage();
        clearStoredResults($conn);
        $tab = "run_reminders";
    }
}

// ------------------------------------------------------------
// Load report data based on tab
// ------------------------------------------------------------
$rows = [];
try {
    if ($tab === "visa_expiry") {
        $stmt = $conn->prepare("CALL sp_report_visas_expiring_next_3_months()");
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            $res->free();
        }
        $stmt->close();
        clearStoredResults($conn);
    }

    if ($tab === "insurance_expiry") {
        $stmt = $conn->prepare("CALL sp_report_insurance_expiring_next_3_months()");
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            $res->free();
        }
        $stmt->close();
        clearStoredResults($conn);
    }

    if ($tab === "passport_ready") {
        $stmt = $conn->prepare("CALL sp_report_passport_ready_to_collect()");
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            $res->free();
        }
        $stmt->close();
        clearStoredResults($conn);
    }
} catch (Throwable $e) {
    $error = $error ?: ("Report load error: " . $e->getMessage());
    clearStoredResults($conn);
}

// ------------------------------------------------------------
// NOTE ABOUT COLUMN NAMES
// Your stored procedures decide the column names.
// This page is built to be "tolerant":
// It tries common column keys and falls back to printing raw values.
// ------------------------------------------------------------
function col(array $r, array $keys, $default = "") {
    foreach ($keys as $k) {
        if (array_key_exists($k, $r) && $r[$k] !== null && $r[$k] !== "") return $r[$k];
    }
    return $default;
}
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">Reports Center</h3>
            <div class="text-muted">Visa & insurance expiry reports, and passport collection list</div>
        </div>
        <div class="text-muted small">
            <span class="badge bg-dark">Staff</span>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?php echo $tab==="visa_expiry" ? "active" : ""; ?>"
               href="reports.php?tab=visa_expiry">Visas expiring (next 3 months)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab==="insurance_expiry" ? "active" : ""; ?>"
               href="reports.php?tab=insurance_expiry">Insurance expiring (next 3 months)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab==="passport_ready" ? "active" : ""; ?>"
               href="reports.php?tab=passport_ready">Passport ready to collect</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab==="run_reminders" ? "active" : ""; ?>"
               href="reports.php?tab=run_reminders">Run reminders</a>
        </li>
    </ul>

    <?php if ($tab === "run_reminders"): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <strong>Run Visa Expiry Reminders</strong>
            </div>
            <div class="card-body">
                <!-- <p class="text-muted mb-3">
                    This will call <code>sp_generate_visa_expiry_reminders</code> to generate reminder entries (if your DB procedure uses a reminder table/queue).
                </p> -->

                <form method="post" onsubmit="return confirm('Run visa expiry reminders now?');">
                    <input type="hidden" name="action" value="run_visa_reminders">
                    <button class="btn btn-primary">
                        <i class="bi bi-play-fill"></i> Run Reminder Generation
                    </button>
                </form>

                <div class="text-muted mt-3 small">
                    If you don't want staff to run this manually, remove this tab and run the procedure via a scheduled task (cron).
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>
                    <?php
                    echo $tab === "visa_expiry" ? "Visas Expiring in the Next 3 Months"
                        : ($tab === "insurance_expiry" ? "Insurance Expiring in the Next 3 Months"
                        : "Passport Ready to Collect");
                    ?>
                </strong>
                <span class="text-muted small"><?php echo count($rows); ?> record(s)</span>
            </div>

            <div class="card-body p-0">
                <?php if (!$rows): ?>
                    <div class="p-4 text-muted">No records found.</div>
                <?php else: ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th style="min-width:110px;">Student ID</th>
                                <th style="min-width:220px;">Name</th>
                                <th style="min-width:240px;">Email</th>

                                <?php if ($tab === "visa_expiry"): ?>
                                    <th style="min-width:140px;">Visa Expiry</th>
                                    <th style="min-width:120px;">Days Left</th>
                                    <th style="min-width:160px;">Latest Status</th>
                                <?php elseif ($tab === "insurance_expiry"): ?>
                                    <th style="min-width:200px;">Provider</th>
                                    <th style="min-width:140px;">Policy End</th>
                                    <th style="min-width:120px;">Days Left</th>
                                <?php else: ?>
                                    <th style="min-width:200px;">Passport Status</th>
                                    <th style="min-width:160px;">Updated</th>
                                <?php endif; ?>
                            </tr>
                            </thead>

                            <tbody>
                            <?php foreach ($rows as $r): ?>
                                <?php
                                $sid   = col($r, ["student_id","sid","id"]);
                                $fname = col($r, ["first_name","fname"]);
                                $lname = col($r, ["last_name","lname"]);
                                $name  = trim((string)$fname . " " . (string)$lname);
                                $email = col($r, ["email","student_email"], "-");
                                ?>

                                <tr>
                                    <td class="fw-semibold"><?php echo h($sid); ?></td>
                                    <td><?php echo h($name ?: col($r, ["name","full_name"], "-")); ?></td>
                                    <td><?php echo h($email); ?></td>

                                    <?php if ($tab === "visa_expiry"): ?>
                                        <?php
                                        $expiry = col($r, ["visa_expiry_date","expiry_date","visa_expiry","end_date"]);
                                        $days   = daysUntil($expiry);
                                        [$cls, $txt] = badgeDays($days);
                                        $status = col($r, ["visa_status"], "-");

                                        ?>
                                        <td><?php echo h(fmtDate($expiry)); ?></td>
                                        <td><span class="badge bg-<?php echo h($cls); ?>"><?php echo h($txt); ?></span></td>
                                        <td><?php echo h($status); ?></td>

                                    <?php elseif ($tab === "insurance_expiry"): ?>
                                        <?php
                                        $provider = col($r, ["provider_name","insurance_provider","provider"], "-");
                                        $endDate  = col($r, ["policy_end_date","end_date","insurance_end_date","expiry_date"]);
                                        $days     = daysUntil($endDate);
                                        [$cls, $txt] = badgeDays($days);
                                        ?>
                                        <td><?php echo h($provider); ?></td>
                                        <td><?php echo h(fmtDate($endDate)); ?></td>
                                        <td><span class="badge bg-<?php echo h($cls); ?>"><?php echo h($txt); ?></span></td>

                                    <?php else: ?>
                                        <?php
                                        $pstatus = col($r, ["latest_stage","application_status"], "-");
                                        $updated = col($r, ["stage_updated_date","submission_date"], null);
                                        ?>
                                        <td><?php echo h($pstatus); ?></td>
                                        <td><?php echo h($updated ? fmtDate($updated) : "-"); ?></td>
                                    <?php endif; ?>
                                </tr>

                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- <div class="p-3 border-top bg-white text-muted small">
                        Tip: If some columns appear blank, it means your stored procedure returns different column names.
                        Just tell me the exact columns returned and I will match them perfectly.
                    </div> -->

                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . "/footer.php"; ?>
