<?php
// student/documents.php

$page_title = "My Visa Documents - ISU Student Portal";
require_once __DIR__ . "/header.php"; // session + db ($conn) + $student_id

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

$success = "";
$error   = "";

// ------------------------------------------------------------
// Find current application to attach documents to
// Priority: active (not "Passport collected"), else latest
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
        // ---------------------------
        // Add document
        // ---------------------------
        if ($action === 'add_document') {
            if (!$currentAppId) {
                throw new Exception("No application found yet. Please submit a renewal application first.");
            }

            $document_type = trim($_POST['document_type'] ?? '');
            if ($document_type === '') {
                throw new Exception("Document type is required.");
            }

            if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Please choose a file to upload.");
            }

            $file = $_FILES['document_file'];

            // Validate file
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

            // Save file
            $safeName = "doc_{$student_id}_" . time() . "_" . bin2hex(random_bytes(6)) . "." . $ext;
            $fullPath = $uploadDir . $safeName;

            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                throw new Exception("Failed to upload file.");
            }

            // Store relative path for web access
            $dbPath = "../uploads/visa_documents/" . $safeName;

            // CALL stored procedure
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
                throw new Exception("Document uploaded but no document ID was returned.");
            }

            $success = "Document uploaded successfully (ID: {$newDocId}).";
        }

        // ---------------------------
        // Update document (type + optional replace file)
        // ---------------------------
        if ($action === 'update_document') {
            $document_id   = (int)($_POST['document_id'] ?? 0);
            $document_type = trim($_POST['document_type'] ?? '');

            if ($document_id <= 0) {
                throw new Exception("Invalid document ID.");
            }
            if ($document_type === '') {
                throw new Exception("Document type is required.");
            }

            // Verify ownership + get existing path
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

            // If a new file is uploaded, replace it
            if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['document_file'];

                $maxBytes = 5 * 1024 * 1024; // 5MB
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

            // CALL stored procedure
            $stmt = $conn->prepare("CALL sp_student_update_visa_document(?, ?, ?, ?)");
            $stmt->bind_param("iiss", $student_id, $document_id, $document_type, $dbPath);
            $stmt->execute();
            $stmt->close();
            clearStoredResults($conn);

            $success = "Document updated successfully.";
        }

        // ---------------------------
        // Delete document
        // ---------------------------
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

    } catch (Throwable $e) {
        $error = $e->getMessage();
        clearStoredResults($conn);
    }

    // Refresh application after actions (just in case)
    $stmt = $conn->prepare($appSqlActive);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $activeApp = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($activeApp) {
        $app = $activeApp;
        $currentAppId = $app['application_id'];
    }
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
?>

<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="mb-0">My Documents</h2>
            <div class="text-muted">Upload, view, edit, and delete your documents for your latest application.</div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- Application Info -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Application Info</div>
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
                        <div class="small text-muted">Status</div>
                        <span class="badge bg-dark"><?php echo h($app['status']); ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-muted">No application found yet. Please submit a renewal application first.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upload New Document -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Upload New Document</div>
        <div class="card-body">
            <?php if (!$currentAppId): ?>
                <div class="text-muted">You need an application first before you can upload documents.</div>
            <?php else: ?>
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
            <?php endif; ?>
        </div>
    </div>

    <!-- Documents List -->
    <div class="card mb-5">
        <div class="card-header fw-semibold">Your Uploaded Documents</div>
        <div class="card-body">
            <?php if (!$currentAppId): ?>
                <div class="text-muted">No documents to show.</div>
            <?php else: ?>
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
                                        <!-- Edit -->
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
