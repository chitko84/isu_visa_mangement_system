<?php
// student/process_claim.php
// Insurance Claim Submission (Student)
// Tables: insurance_claim, insurance_policy, student
// Procedure: sp_student_submit_insurance_claim

$page_title = "Submit Insurance Claim - ISU Student Portal";
require_once __DIR__ . "/header.php"; // session + db ($conn) + $student_id

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function clearStoredResults(mysqli $conn): void {
    while ($conn->more_results()) {
        $conn->next_result();
        if ($res = $conn->store_result()) $res->free();
    }
}

$success = "";
$error   = "";

// ------------------------------------------------------------
// Verify student exists (extra safety)
// ------------------------------------------------------------
$stmt = $conn->prepare("SELECT student_id, first_name, last_name FROM student WHERE student_id = ? LIMIT 1");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// ------------------------------------------------------------
// Fetch current ACTIVE policy (latest by end_date)
// NOTE: your insurance_policy.status is CHECK ('Active','Expired')
// ------------------------------------------------------------
$currentPolicy = null;
$stmt = $conn->prepare("
    SELECT policy_id, provider_id, policy_number, start_date, end_date, coverage_type, status
    FROM insurance_policy
    WHERE student_id = ?
    ORDER BY end_date DESC, policy_id DESC
    LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$currentPolicy = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ------------------------------------------------------------
// Handle claim submit
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claim_amount = (float)($_POST['claim_amount'] ?? 0);

    try {
        if (!$currentPolicy) {
            throw new Exception("No insurance policy found. You cannot submit a claim.");
        }

        if ($claim_amount <= 0) {
            throw new Exception("Claim amount must be greater than 0.");
        }

        $policy_id = (int)$currentPolicy['policy_id'];

        // Use the exact SP signature from your SQL dump:
        // sp_student_submit_insurance_claim(IN p_student_id INT, IN p_policy_id INT, IN p_claim_amount DECIMAL(10,2), OUT o_claim_id INT)
        $stmt = $conn->prepare("CALL sp_student_submit_insurance_claim(?, ?, ?, @o_claim_id)");
        $stmt->bind_param("iid", $student_id, $policy_id, $claim_amount);
        $stmt->execute();
        $stmt->close();
        clearStoredResults($conn);

        // Get OUT value
        $res = $conn->query("SELECT @o_claim_id AS claim_id");
        $row = $res ? $res->fetch_assoc() : null;
        if ($res) $res->free();

        $newId = (int)($row['claim_id'] ?? 0);

        $success = $newId > 0
            ? "Claim submitted successfully. Claim ID: " . $newId
            : "Claim submitted successfully.";

    } catch (Throwable $e) {
        $error = $e->getMessage();
        clearStoredResults($conn);
    }

    // Refresh policy (optional)
    $stmt = $conn->prepare("
        SELECT policy_id, provider_id, policy_number, start_date, end_date, coverage_type, status
        FROM insurance_policy
        WHERE student_id = ?
        ORDER BY end_date DESC, policy_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $currentPolicy = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="mb-0">Submit Insurance Claim</h2>
            <div class="text-muted">Create a new insurance claim under your current policy.</div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- Policy Summary -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Current Policy</div>
        <div class="card-body">
            <?php if (!$currentPolicy): ?>
                <div class="text-muted">No policy found.</div>
            <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="small text-muted">Policy Number</div>
                        <div class="fw-semibold"><?php echo h($currentPolicy['policy_number']); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Coverage Type</div>
                        <div class="fw-semibold"><?php echo h($currentPolicy['coverage_type'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Status</div>
                        <span class="badge <?php echo (($currentPolicy['status'] ?? '') === 'Active') ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo h($currentPolicy['status']); ?>
                        </span>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Start Date</div>
                        <div class="fw-semibold"><?php echo h($currentPolicy['start_date']); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">End Date</div>
                        <div class="fw-semibold"><?php echo h($currentPolicy['end_date']); ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Claim Form -->
    <div class="card mb-5">
        <div class="card-header fw-semibold">New Claim</div>
        <div class="card-body">
            <?php if (!$currentPolicy): ?>
                <div class="text-muted">You need an insurance policy before submitting a claim.</div>
            <?php else: ?>
                <form method="post" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Claim Amount (RM)</label>
                        <input
                            type="number"
                            name="claim_amount"
                            class="form-control"
                            step="0.01"
                            min="0.01"
                            required
                            placeholder="e.g., 120.50"
                        >
                        <div class="form-text">Your claim will be created with status <b>Pending</b>.</div>
                    </div>

                    <div class="col-12">
                        <button class="btn btn-success">Submit Claim</button>
                        <a href="insurance.php" class="btn btn-outline-secondary">Back to Insurance</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . "/footer.php"; ?>
