<?php
// student/visa_renewal.php

$page_title = "Visa Renewal - ISU Student Portal";
require_once __DIR__ . "/header.php"; // includes session + db ($conn) + $student_id

// ------------------------------------------------------------
// Helpers (IMPORTANT for stored procedures: avoid "commands out of sync")
// ------------------------------------------------------------
function clearStoredResults(mysqli $conn): void {
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

/**
 * Validate uploaded file by extension + MIME (stronger than extension-only).
 */
function validateUpload(array $file, int $maxBytes = 5242880): void {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Please choose a file to upload.");
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new Exception("File too large. Max 5MB.");
    }

    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new Exception("Invalid file extension. Allowed: PDF, JPG, JPEG, PNG.");
    }

    // MIME verification
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowedMime = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
    ];

    if (!isset($allowedMime[$ext]) || !in_array($mime, $allowedMime[$ext], true)) {
        throw new Exception("Invalid file content type (MIME). Please upload a real PDF/JPG/PNG file.");
    }
}

function relPathToFs(string $relPath): string {
    return realpath(__DIR__ . "/" . $relPath) ?: (__DIR__ . "/" . $relPath);
}

function ensureUploadDir(string $dir): void {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new Exception("Failed to create upload directory.");
        }
    }
}

$success = "";
$warning = "";
$error = "";

// ------------------------------------------------------------
// Document Type Options (controlled list)
// ------------------------------------------------------------
$DOCUMENT_TYPES = [
    'Passport Copy',
    'Visa Page',
    'Student Pass Sticker',
    'Offer Letter',
    'Enrollment Letter',
    'Insurance Document',
    'Academic Transcript',
    'Other Supporting Document',
];

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
        require_csrf();
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
            create_notification($conn, [
                'student_id' => $student_id,
                'title' => 'Visa renewal submitted',
                'message' => "Your visa renewal application #{$newAppId} was submitted.",
                'type' => 'visa_renewal_submitted',
            ]);
            notify_staff($conn, 'Visa renewal submitted', "Student {$student_id} submitted visa renewal application #{$newAppId}.", 'visa_renewal_submitted');
            log_audit($conn, 'student_submitted_visa_renewal', 'visa_renewal_application', $newAppId, 'Student submitted visa renewal.');

            // refresh current app
            $stmt = $conn->prepare($appSqlActive);
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $app = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $currentAppId = $app['application_id'] ?? null;
        }

        // 2) Add document (student) - single upload (kept for backward compatibility)
        if ($action === 'add_document') {
            if (!$currentAppId) {
                throw new Exception("No active application found. Submit a renewal first.");
            }

            $document_type = trim($_POST['document_type'] ?? '');
            if ($document_type === '' || !in_array($document_type, $DOCUMENT_TYPES, true)) {
                throw new Exception("Invalid document type selected.");
            }

            // Duplicate type check (warning only, still allows)
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS c
                FROM visa_document
                WHERE application_id = ?
                  AND document_type = ?
                LIMIT 1
            ");
            $stmt->bind_param("is", $currentAppId, $document_type);
            $stmt->execute();
            $cntRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ((int)($cntRow['c'] ?? 0) > 0) {
                $warning = "⚠️ You already uploaded a <strong>" . h($document_type) . "</strong> for this application. "
                         . "If this is a newer version, consider using <strong>Edit</strong> on the existing one.";
            }

            if (!isset($_FILES['document_file'])) {
                throw new Exception("Please choose a file to upload.");
            }

            $file = $_FILES['document_file'];
            validateUpload($file);

            $uploadDir = __DIR__ . "/../uploads/visa_documents/";
            ensureUploadDir($uploadDir);

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $safeName = "doc_{$student_id}_" . time() . "_" . bin2hex(random_bytes(6)) . "." . $ext;
            $fullPath = $uploadDir . $safeName;

            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                throw new Exception("Failed to upload file.");
            }

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
            notify_staff($conn, 'Student uploaded document', "Student {$student_id} uploaded {$document_type} for visa renewal application {$currentAppId}.", 'document_upload');
        }

        // 2B) Add MULTIPLE documents in one submit (student)
        if ($action === 'add_documents_batch') {
            if (!$currentAppId) {
                throw new Exception("No active application found. Submit a renewal first.");
            }

            $types = $_POST['document_type'] ?? [];
            if (!is_array($types)) $types = [];

            if (!isset($_FILES['document_file'])) {
                throw new Exception("Please choose files to upload.");
            }

            $files = $_FILES['document_file'];
            $count = is_array($files['name'] ?? null) ? count($files['name']) : 0;

            if ($count === 0) {
                throw new Exception("Please add at least one document row.");
            }

            $uploadDir = __DIR__ . "/../uploads/visa_documents/";
            ensureUploadDir($uploadDir);

            $uploaded = 0;
            $skipped = 0;
            $errors = [];
            $dupWarns = 0;

            for ($i = 0; $i < $count; $i++) {
                $document_type = trim($types[$i] ?? '');

                $rowFileName = (string)($files['name'][$i] ?? '');
                $rowTmp      = (string)($files['tmp_name'][$i] ?? '');

                // If the row is completely empty, skip it
                if ($document_type === '' && $rowFileName === '') {
                    $skipped++;
                    continue;
                }

                if ($document_type === '' || !in_array($document_type, $DOCUMENT_TYPES, true)) {
                    $errors[] = "Row " . ($i + 1) . ": Invalid document type.";
                    continue;
                }

                $file = [
                    'name'     => $files['name'][$i] ?? '',
                    'type'     => $files['type'][$i] ?? '',
                    'tmp_name' => $files['tmp_name'][$i] ?? '',
                    'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $files['size'][$i] ?? 0,
                ];

                // Duplicate type check (warning only)
                $stmt = $conn->prepare("
                    SELECT COUNT(*) AS c
                    FROM visa_document
                    WHERE application_id = ?
                      AND document_type = ?
                    LIMIT 1
                ");
                $stmt->bind_param("is", $currentAppId, $document_type);
                $stmt->execute();
                $cntRow = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ((int)($cntRow['c'] ?? 0) > 0) {
                    $dupWarns++;
                }

                $fullPath = ''; // for cleanup if DB fails
                try {
                    validateUpload($file);

                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $safeName = "doc_{$student_id}_" . time() . "_" . bin2hex(random_bytes(6)) . "." . $ext;
                    $fullPath = $uploadDir . $safeName;

                    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                        throw new Exception("Row " . ($i + 1) . ": Failed to upload file.");
                    }

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
                        throw new Exception("Row " . ($i + 1) . ": Uploaded, but DB did not return document id.");
                    }

                    $uploaded++;
                    notify_staff($conn, 'Student uploaded document', "Student {$student_id} uploaded {$document_type} for visa renewal application {$currentAppId}.", 'document_upload');

                } catch (Throwable $ex) {
                    // If file moved but DB failed, delete file (best effort)
                    if ($fullPath !== '' && is_file($fullPath)) {
                        @unlink($fullPath);
                    }
                    $errors[] = $ex->getMessage();
                    clearStoredResults($conn);
                }
            }

            if ($uploaded > 0) {
                $success = "Uploaded {$uploaded} document(s) successfully.";
            }

            if ($skipped > 0) {
                $warning .= ($warning ? "<br>" : "") . "Skipped {$skipped} empty row(s).";
            }

            if ($dupWarns > 0) {
                $warning .= ($warning ? "<br>" : "") .
                    "⚠️ {$dupWarns} row(s) have document type(s) that were already uploaded before (allowed). If you meant to replace, use Edit on existing document.";
            }

            if (!empty($errors)) {
                $error = "Some rows failed:<br>- " . implode("<br>- ", array_map('h', $errors));
            }
        }

        // 3) Update document (student)
        if ($action === 'update_document') {
            $document_id = (int)($_POST['document_id'] ?? 0);
            $document_type = trim($_POST['document_type'] ?? '');

            if ($document_id <= 0) {
                throw new Exception("Invalid document ID.");
            }
            if ($document_type === '' || !in_array($document_type, $DOCUMENT_TYPES, true)) {
                throw new Exception("Invalid document type selected.");
            }

            // Find application_id of this document (ownership check + also used for duplicate warning)
            $q = "
                SELECT d.document_id, d.document_path, d.application_id
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

            $docAppId = (int)($existing['application_id'] ?? 0);

            // Duplicate type check (warning only)
            if ($docAppId > 0) {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) AS c
                    FROM visa_document
                    WHERE application_id = ?
                      AND document_type = ?
                      AND document_id <> ?
                    LIMIT 1
                ");
                $stmt->bind_param("isi", $docAppId, $document_type, $document_id);
                $stmt->execute();
                $cntRow = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ((int)($cntRow['c'] ?? 0) > 0) {
                    $warning = "⚠️ Another document with type <strong>" . h($document_type) . "</strong> already exists for this application.";
                }
            }

            $oldDbPath = (string)$existing['document_path'];
            $dbPath = $oldDbPath;
            $replaced = false;

            // If a new file is uploaded, replace path
            if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['document_file'];
                validateUpload($file);

                $uploadDir = __DIR__ . "/../uploads/visa_documents/";
                ensureUploadDir($uploadDir);

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $safeName = "doc_{$student_id}_" . time() . "_" . bin2hex(random_bytes(6)) . "." . $ext;
                $fullPath = $uploadDir . $safeName;

                if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                    throw new Exception("Failed to upload replacement file.");
                }

                $dbPath = "../uploads/visa_documents/" . $safeName;
                $replaced = true;
            }

            $stmt = $conn->prepare("CALL sp_student_update_visa_document(?, ?, ?, ?)");
            $stmt->bind_param("iiss", $student_id, $document_id, $document_type, $dbPath);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            // Delete old file AFTER DB update succeeds
            if ($replaced && $oldDbPath !== '' && $oldDbPath !== $dbPath) {
                $oldFs = relPathToFs($oldDbPath);
                if (is_file($oldFs)) {
                    @unlink($oldFs);
                }
            }

            $success = "Document updated successfully.";
            log_audit($conn, 'student_updated_document', 'visa_document', $document_id, 'Student updated visa renewal document.');
        }

        // 4) Delete document (student)
        if ($action === 'delete_document') {
            $document_id = (int)($_POST['document_id'] ?? 0);

            if ($document_id <= 0) {
                throw new Exception("Invalid document ID.");
            }

            // Get existing path for ownership + to delete file from disk
            $q = "
                SELECT d.document_path
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

            $oldDbPath = (string)$existing['document_path'];

            $stmt = $conn->prepare("CALL sp_student_delete_visa_document(?, ?)");
            $stmt->bind_param("ii", $student_id, $document_id);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            // Delete file from disk (best effort)
            if ($oldDbPath !== '') {
                $oldFs = relPathToFs($oldDbPath);
                if (is_file($oldFs)) {
                    @unlink($oldFs);
                }
            }

            $success = "Document deleted successfully.";
            log_audit($conn, 'student_deleted_document', 'visa_document', $document_id, 'Student deleted visa renewal document.');
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

    <?php if ($warning): ?>
        <div class="alert alert-warning"><?php echo $warning; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
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
                    <?php echo csrf_field(); ?>
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
                            <?php echo csrf_field(); ?>
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
                            <?php echo csrf_field(); ?>
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

                <!-- Add Multiple Documents (Table) -->
                <div class="border rounded p-3 mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="mb-0">Upload Supporting Documents (Multiple)</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addRowBtn">
                            <i class="bi bi-plus-circle"></i> Add Row
                        </button>
                    </div>

                    <form method="post" enctype="multipart/form-data" id="multiUploadForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="add_documents_batch">

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-2" id="docsTable">
                                <thead class="table-light">
                                <tr>
                                    <th style="width: 45%;">Document Type</th>
                                    <th style="width: 45%;">File</th>
                                    <th style="width: 10%;">Remove</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td>
                                        <select name="document_type[]" class="form-select" required>
                                            <option value="">-- Select Document Type --</option>
                                            <?php foreach ($DOCUMENT_TYPES as $type): ?>
                                                <option value="<?php echo h($type); ?>"><?php echo h($type); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="file" name="document_file[]" class="form-control" required>
                                        <div class="form-text">Allowed: PDF/JPG/PNG, max 5MB each.</div>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger removeRowBtn" disabled>
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-success">
                                <i class="bi bi-upload"></i> Upload All
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="clearRowsBtn">
                                Clear
                            </button>
                        </div>
                    </form>
                </div>

                <script>
                (function () {
                    const tableBody = document.querySelector("#docsTable tbody");
                    const addRowBtn = document.getElementById("addRowBtn");
                    const clearRowsBtn = document.getElementById("clearRowsBtn");

                    function updateRemoveButtons() {
                        const removeBtns = tableBody.querySelectorAll(".removeRowBtn");
                        removeBtns.forEach((btn) => {
                            btn.disabled = (tableBody.rows.length === 1); // keep at least 1 row
                        });
                    }

                    addRowBtn.addEventListener("click", () => {
                        const firstRow = tableBody.rows[0];
                        const newRow = firstRow.cloneNode(true);

                        // reset values
                        newRow.querySelector('select[name="document_type[]"]').value = "";
                        newRow.querySelector('input[name="document_file[]"]').value = "";

                        // enable remove button
                        const removeBtn = newRow.querySelector(".removeRowBtn");
                        removeBtn.disabled = false;

                        tableBody.appendChild(newRow);
                        updateRemoveButtons();
                    });

                    tableBody.addEventListener("click", (e) => {
                        const btn = e.target.closest(".removeRowBtn");
                        if (!btn) return;

                        if (tableBody.rows.length > 1) {
                            btn.closest("tr").remove();
                            updateRemoveButtons();
                        }
                    });

                    clearRowsBtn.addEventListener("click", () => {
                        // keep only one row
                        while (tableBody.rows.length > 1) tableBody.deleteRow(1);

                        // reset first row
                        tableBody.rows[0].querySelector('select[name="document_type[]"]').value = "";
                        tableBody.rows[0].querySelector('input[name="document_file[]"]').value = "";
                        updateRemoveButtons();
                    });

                    updateRemoveButtons();
                })();
                </script>

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
                            <?php foreach ($documents as $d): $docId = (int)$d['document_id']; ?>
                                <tr>
                                    <td><?php echo h($d['document_id']); ?></td>
                                    <td class="fw-semibold"><?php echo h($d['document_type']); ?></td>
                                    <td>
                                        <?php if (!empty($d['document_path'])): ?>
                                            <a href="../download.php?id=<?php echo (int)$d['document_id']; ?>" target="_blank" rel="noopener">View</a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo h($d['upload_date']); ?></td>
                                    <td>
                                        <!-- Update -->
                                        <button class="btn btn-sm btn-outline-primary" type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#editDoc<?php echo $docId; ?>">
                                            Edit
                                        </button>

                                        <!-- Delete (Modal trigger) -->
                                        <button class="btn btn-sm btn-outline-danger" type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteModal<?php echo $docId; ?>">
                                            Delete
                                        </button>

                                        <!-- Edit Collapse -->
                                        <div class="collapse mt-2" id="editDoc<?php echo $docId; ?>">
                                            <div class="border rounded p-2">
                                                <form method="post" enctype="multipart/form-data" class="row g-2">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="update_document">
                                                    <input type="hidden" name="document_id" value="<?php echo $docId; ?>">

                                                    <div class="col-12">
                                                        <label class="form-label small mb-1">Document Type</label>
                                                        <select name="document_type" class="form-select" required>
                                                            <option value="">-- Select Document Type --</option>
                                                            <?php foreach ($DOCUMENT_TYPES as $type): ?>
                                                                <option value="<?php echo h($type); ?>"
                                                                    <?php echo (($d['document_type'] ?? '') === $type) ? 'selected' : ''; ?>>
                                                                    <?php echo h($type); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
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

                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $docId; ?>" tabindex="-1"
                                             aria-labelledby="deleteModalLabel<?php echo $docId; ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?php echo $docId; ?>">Confirm Deletion</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Are you sure you want to delete this document?
                                                        <div class="small text-muted mt-1">
                                                            Document ID: <strong><?php echo h($d['document_id']); ?></strong><br>
                                                            Type: <strong><?php echo h($d['document_type']); ?></strong>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="post" class="m-0">
                                                            <?php echo csrf_field(); ?>
                                                            <input type="hidden" name="action" value="delete_document">
                                                            <input type="hidden" name="document_id" value="<?php echo $docId; ?>">
                                                            <button type="submit" class="btn btn-danger">Yes, Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
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
