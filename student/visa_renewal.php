<?php
// student/visa_renewal.php

$page_title = "Visa Renewal - ISU Student Portal";
require_once __DIR__ . "/header.php"; // includes session + db ($conn) + $student_id

// ------------------------------------------------------------
// Helpers (IMPORTANT for stored procedures: avoid "commands out of sync")
// ------------------------------------------------------------
function clearStoredResults(mysqli $conn): void {
    // flush all pending result sets from CALL statements
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
$error = "";

// ------------------------------------------------------------
// Fetch current visa (latest by expiry_date)
// ------------------------------------------------------------
$currentVisa = null;
$visaSql = "
    SELECT visa_id, visa_type, passport_no, issue_date, expiry_date, status
    FROM student_visa
    WHERE student_id = ?
    ORDER BY expiry_date DESC, visa_id DESC
    LIMIT 1
";
$stmt = $conn->prepare($visaSql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$currentVisa = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ------------------------------------------------------------
// Fetch current (active) application: status <> 'Passport collected'
// (If none, show last application)
// ------------------------------------------------------------
$app = null;
$appSqlActive = "
    SELECT application_id, submission_date, requested_months, status
    FROM visa_renewal_application
    WHERE student_id = ?
      AND status <> 'Passport collected'
    ORDER BY submission_date DESC, application_id DESC
    LIMIT 1
";
$stmt = $conn->prepare($appSqlActive);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) {
    $appSqlLast = "
        SELECT application_id, submission_date, requested_months, status
        FROM visa_renewal_application
        WHERE student_id = ?
        ORDER BY submission_date DESC, application_id DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($appSqlLast);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$currentAppId = $app['application_id'] ?? null;

// ------------------------------------------------------------
// Handle actions (POST)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1) Submit visa renewal (student)
        if ($action === 'submit_renewal') {
            $requested_months = (int)($_POST['requested_months'] ?? 0);

            if ($requested_months <= 0) {
                throw new Exception("Requested months must be greater than 0.");
            }

            // CALL sp_student_submit_visa_renewal_form(IN student_id, IN requested_months, OUT application_id)
            $stmt = $conn->prepare("CALL sp_student_submit_visa_renewal_form(?, ?, @o_app_id)");
            $stmt->bind_param("ii", $student_id, $requested_months);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $res = $conn->query("SELECT @o_app_id AS application_id");
            $row = $res ? $res->fetch_assoc() : null;
            $newAppId = (int)($row['application_id'] ?? 0);
            if ($res) $res->free();

            if ($newAppId <= 0) {
                throw new Exception("Failed to create visa renewal application.");
            }

            $success = "Visa renewal application submitted successfully. Application ID: {$newAppId}";

            // refresh current app
            $stmt = $conn->prepare($appSqlActive);
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $app = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $currentAppId = $app['application_id'] ?? null;
        }

        // 2) Add document (student) - uses sp_student_add_visa_document
        if ($action === 'add_document') {
            if (!$currentAppId) {
                throw new Exception("No active application found. Submit a renewal first.");
            }

            $document_type = trim($_POST['document_type'] ?? '');
            if ($document_type === '') {
                throw new Exception("Document type is required.");
            }

            if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Please choose a file to upload.");
            }

            $file = $_FILES['document_file'];

            // Basic validation
            $maxBytes = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $maxBytes) {
                throw new Exception("File too large. Max 5MB.");
            }

            $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                throw new Exception("Invalid file type. Allowed: PDF, JPG, JPEG, PNG.");
            }

            // Ensure upload folder exists
            $uploadDir = __DIR__ . "/../uploads/visa_documents/";
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $safeName = "doc_{$student_id}_" . time() . "_" . bin2hex(random_bytes(6)) . "." . $ext;
            $fullPath = $uploadDir . $safeName;

            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                throw new Exception("Failed to upload file.");
            }

            // Store relative path
            $dbPath = "../uploads/visa_documents/" . $safeName;

            $stmt = $conn->prepare("CALL sp_student_add_visa_document(?, ?, ?, ?, @o_doc_id)");
            $stmt->bind_param("iiss", $student_id, $currentAppId, $document_type, $dbPath);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $res = $conn->query("SELECT @o_doc_id AS document_id");
            $row = $res ? $res->fetch_assoc() : null;
            $newDocId = (int)($row['document_id'] ?? 0);
            if ($res) $res->free();

            if ($newDocId <= 0) {
                throw new Exception("Document saved but document_id was not returned. Please check DB.");
            }

            $success = "Document uploaded successfully. Document ID: {$newDocId}";
        }

        // 3) Update document (student)
        if ($action === 'update_document') {
            $document_id = (int)($_POST['document_id'] ?? 0);
            $document_type = trim($_POST['document_type'] ?? '');

            if ($document_id <= 0) {
                throw new Exception("Invalid document ID.");
            }
            if ($document_type === '') {
                throw new Exception("Document type is required.");
            }

            // Get existing doc path (for ownership + to reuse if no file uploaded)
            $q = "
                SELECT d.document_id, d.document_path
                FROM visa_document d
                JOIN visa_renewal_application a ON a.application_id = d.application_id
                WHERE d.document_id = ?
                  AND a.student_id = ?
                LIMIT 1
            ";
            $stmt = $conn->prepare($q);
            $stmt->bind_param("ii", $document_id, $student_id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$existing) {
                throw new Exception("Document not found or not yours.");
            }

            $dbPath = $existing['document_path'];

            // If a new file is uploaded, replace path
            if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['document_file'];

                $maxBytes = 5 * 1024 * 1024;
                if ($file['size'] > $maxBytes) {
                    throw new Exception("File too large. Max 5MB.");
                }

                $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) {
                    throw new Exception("Invalid file type. Allowed: PDF, JPG, JPEG, PNG.");
                }

                $uploadDir = __DIR__ . "/../uploads/visa_documents/";
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }

                $safeName = "doc_{$student_id}_" . time() . "_" . bin2hex(random_bytes(6)) . "." . $ext;
                $fullPath = $uploadDir . $safeName;

                if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                    throw new Exception("Failed to upload replacement file.");
                }

                $dbPath = "../uploads/visa_documents/" . $safeName;
            }

            $stmt = $conn->prepare("CALL sp_student_update_visa_document(?, ?, ?, ?)");
            $stmt->bind_param("iiss", $student_id, $document_id, $document_type, $dbPath);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $success = "Document updated successfully.";
        }

        // 4) Delete document (student)
        if ($action === 'delete_document') {
            $document_id = (int)($_POST['document_id'] ?? 0);

            if ($document_id <= 0) {
                throw new Exception("Invalid document ID.");
            }

            $stmt = $conn->prepare("CALL sp_student_delete_visa_document(?, ?)");
            $stmt->bind_param("ii", $student_id, $document_id);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $success = "Document deleted successfully.";
        }

        // 5) Staff-only actions
        if ($action === 'staff_add_status') {
            if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
                throw new Exception("Unauthorized (staff only).");
            }

            $application_id = (int)($_POST['application_id'] ?? 0);
            $stage_name = trim($_POST['stage_name'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');

            if ($application_id <= 0 || $stage_name === '') {
                throw new Exception("Application ID and stage name are required.");
            }

            $stmt = $conn->prepare("CALL sp_staff_add_visa_renewal_status(?, ?, ?, @o_status_id)");
            $stmt->bind_param("iss", $application_id, $stage_name, $remarks);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $success = "Status added (staff).";
        }

        if ($action === 'staff_update_application_status') {
            if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
                throw new Exception("Unauthorized (staff only).");
            }

            $application_id = (int)($_POST['application_id'] ?? 0);
            $new_status = trim($_POST['new_status'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');

            if ($application_id <= 0 || $new_status === '') {
                throw new Exception("Application ID and new status are required.");
            }

            $stmt = $conn->prepare("CALL sp_staff_update_visa_application_status(?, ?, ?)");
            $stmt->bind_param("iss", $application_id, $new_status, $remarks);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $success = "Application status updated (staff).";
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
        clearStoredResults($conn);
    }

    // Re-fetch current app after any action
    $stmt = $conn->prepare($appSqlActive);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $activeApp = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($activeApp) {
        $app = $activeApp;
        $currentAppId = $app['application_id'];
    } else {
        $stmt = $conn->prepare("
            SELECT application_id, submission_date, requested_months, status
            FROM visa_renewal_application
            WHERE student_id = ?
            ORDER BY submission_date DESC, application_id DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $app = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $currentAppId = $app['application_id'] ?? null;
    }
}

// ------------------------------------------------------------
// Fetch timeline for current app
// ------------------------------------------------------------
$timeline = [];
if ($currentAppId) {
    $stmt = $conn->prepare("
        SELECT status_id, stage_name, updated_date, remarks
        FROM visa_renewal_status
        WHERE application_id = ?
        ORDER BY updated_date DESC, status_id DESC
    ");
    $stmt->bind_param("i", $currentAppId);
    $stmt->execute();
    $timeline = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ------------------------------------------------------------
// Fetch documents for current app
// ------------------------------------------------------------
$documents = [];
if ($currentAppId) {
    $stmt = $conn->prepare("
        SELECT document_id, document_type, document_path, upload_date
        FROM visa_document
        WHERE application_id = ?
        ORDER BY upload_date DESC, document_id DESC
    ");
    $stmt->bind_param("i", $currentAppId);
    $stmt->execute();
    $documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ------------------------------------------------------------
// Utility: whether student has active application
// ------------------------------------------------------------
$hasActiveApp = false;
if ($app && ($app['status'] ?? '') !== 'Passport collected') {
    $hasActiveApp = true;
}
?>

<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="mb-0">Visa Renewal</h2>
            <div class="text-muted">Submit your visa renewal, track status, and manage supporting documents.</div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- Current Visa -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">
            Current Student Pass
        </div>
        <div class="card-body">
            <?php if ($currentVisa): ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="small text-muted">Visa Type</div>
                        <div class="fw-semibold"><?php echo h($currentVisa['visa_type']); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Passport No</div>
                        <div class="fw-semibold"><?php echo h($currentVisa['passport_no']); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Status</div>
                        <span class="badge <?php echo ($currentVisa['status'] === 'Active') ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo h($currentVisa['status']); ?>
                        </span>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Issue Date</div>
                        <div class="fw-semibold"><?php echo h($currentVisa['issue_date']); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Expiry Date</div>
                        <div class="fw-semibold"><?php echo h($currentVisa['expiry_date']); ?></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-muted">No visa record found.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Submit Renewal -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">
            Submit Visa Renewal
        </div>
        <div class="card-body">
            <?php if ($hasActiveApp): ?>
                <div class="alert alert-info mb-0">
                    You already have an active renewal application (ID: <strong><?php echo h($app['application_id']); ?></strong>).
                    You cannot submit another one until it is marked as <strong>Passport collected</strong>.
                </div>
            <?php else: ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="submit_renewal">

                    <div class="col-md-6">
                        <label class="form-label">Requested Months</label>
                        <input type="number" name="requested_months" class="form-control" min="1" max="60" required
                               placeholder="e.g., 12">
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <button class="btn btn-primary">
                            <i class="bi bi-send"></i> Submit Renewal
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Application Details -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">
            Application Details
        </div>
        <div class="card-body">
            <?php if ($app): ?>
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="small text-muted">Application ID</div>
                        <div class="fw-semibold"><?php echo h($app['application_id']); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Submission Date</div>
                        <div class="fw-semibold"><?php echo h($app['submission_date']); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Requested Months</div>
                        <div class="fw-semibold"><?php echo h($app['requested_months']); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Application Status</div>
                        <span class="badge bg-dark"><?php echo h($app['status']); ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-muted">No renewal application found yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Timeline -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">
            Status Timeline
        </div>
        <div class="card-body">
            <?php if (!$currentAppId): ?>
                <div class="text-muted">No application to show timeline.</div>
            <?php else: ?>
                <?php if (!$timeline): ?>
                    <div class="text-muted">No status updates found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                            <tr>
                                <th>Status ID</th>
                                <th>Stage</th>
                                <th>Updated Date</th>
                                <th>Remarks</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($timeline as $t): ?>
                                <tr>
                                    <td><?php echo h($t['status_id']); ?></td>
                                    <td class="fw-semibold"><?php echo h($t['stage_name']); ?></td>
                                    <td><?php echo h($t['updated_date']); ?></td>
                                    <td><?php echo h($t['remarks']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true) && $currentAppId): ?>
                <hr>
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="mb-2">Staff: Add Status</h6>
                        <form method="post" class="row g-2">
                            <input type="hidden" name="action" value="staff_add_status">
                            <input type="hidden" name="application_id" value="<?php echo (int)$currentAppId; ?>">
                            <div class="col-12">
                                <input type="text" name="stage_name" class="form-control" placeholder="Stage name (e.g. Approved)" required>
                            </div>
                            <div class="col-12">
                                <input type="text" name="remarks" class="form-control" placeholder="Remarks (optional)">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-outline-primary">Add Status</button>
                            </div>
                        </form>
                    </div>

                    <div class="col-md-6">
                        <h6 class="mb-2">Staff: Update Application Status</h6>
                        <form method="post" class="row g-2">
                            <input type="hidden" name="action" value="staff_update_application_status">
                            <input type="hidden" name="application_id" value="<?php echo (int)$currentAppId; ?>">
                            <div class="col-12">
                                <select name="new_status" class="form-select" required>
                                    <option value="">-- Choose status --</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Submitted passport to ISSU">Submitted passport to ISSU</option>
                                    <option value="Passport collected">Passport collected</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <input type="text" name="remarks" class="form-control" placeholder="Remarks (optional)">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-outline-dark">Update Status</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Supporting Documents -->
    <div class="card mb-5">
        <div class="card-header fw-semibold">
            Supporting Documents
        </div>
        <div class="card-body">

            <?php if (!$currentAppId): ?>
                <div class="text-muted">Submit a visa renewal application first to upload documents.</div>
            <?php else: ?>

                <!-- Add Document -->
                <div class="border rounded p-3 mb-4">
                    <h6 class="mb-3">Upload New Document</h6>
                    <form method="post" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="action" value="add_document">

                        <div class="col-md-5">
                            <label class="form-label">Document Type</label>
                            <input type="text" name="document_type" class="form-control" required placeholder="e.g., Passport Copy">
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">Choose File</label>
                            <input type="file" name="document_file" class="form-control" required>
                            <div class="form-text">Allowed: PDF/JPG/PNG, max 5MB.</div>
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-success w-100">
                                <i class="bi bi-upload"></i> Upload
                            </button>
                        </div>
                    </form>
                </div>

                <!-- List Documents -->
                <?php if (!$documents): ?>
                    <div class="text-muted">No documents uploaded yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>Document ID</th>
                                <th>Type</th>
                                <th>File</th>
                                <th>Upload Date</th>
                                <th style="width: 260px;">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($documents as $d): ?>
                                <tr>
                                    <td><?php echo h($d['document_id']); ?></td>
                                    <td class="fw-semibold"><?php echo h($d['document_type']); ?></td>
                                    <td>
                                        <?php if (!empty($d['document_path'])): ?>
                                            <a href="<?php echo h($d['document_path']); ?>" target="_blank">View</a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo h($d['upload_date']); ?></td>
                                    <td>
                                        <!-- Update -->
                                        <button class="btn btn-sm btn-outline-primary" type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#editDoc<?php echo (int)$d['document_id']; ?>">
                                            Edit
                                        </button>

                                        <!-- Delete -->
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this document?');">
                                            <input type="hidden" name="action" value="delete_document">
                                            <input type="hidden" name="document_id" value="<?php echo (int)$d['document_id']; ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">
                                                Delete
                                            </button>
                                        </form>

                                        <!-- Edit Collapse -->
                                        <div class="collapse mt-2" id="editDoc<?php echo (int)$d['document_id']; ?>">
                                            <div class="border rounded p-2">
                                                <form method="post" enctype="multipart/form-data" class="row g-2">
                                                    <input type="hidden" name="action" value="update_document">
                                                    <input type="hidden" name="document_id" value="<?php echo (int)$d['document_id']; ?>">

                                                    <div class="col-12">
                                                        <label class="form-label small mb-1">Document Type</label>
                                                        <input type="text" name="document_type" class="form-control"
                                                               value="<?php echo h($d['document_type']); ?>" required>
                                                    </div>

                                                    <div class="col-12">
                                                        <label class="form-label small mb-1">Replace File (optional)</label>
                                                        <input type="file" name="document_file" class="form-control">
                                                        <div class="form-text">If empty, it keeps the current file.</div>
                                                    </div>

                                                    <div class="col-12">
                                                        <button class="btn btn-sm btn-primary" type="submit">
                                                            Save Changes
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . "/footer.php"; ?>
