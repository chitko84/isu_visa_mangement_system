<?php
// Set page title
$page_title = "My Documents - ISSU";

// Include header
require_once 'header.php';

// Note: $conn and $student_id are already available from header.php

// Handle file upload
$upload_success = false;
$upload_error = '';
$document_types = [
    'Passport Copy' => 'Passport page with photo and details',
    'Offer Letter' => 'University offer/acceptance letter',
    'Academic Transcript' => 'Latest academic transcript',
    'Financial Proof' => 'Bank statement or sponsorship letter',
    'Medical Report' => 'Medical examination report',
    'Visa Application Form' => 'Completed visa application form',
    'Passport Photo' => 'Recent passport-sized photographs',
    'Police Clearance' => 'Police clearance certificate',
    'Other' => 'Other supporting documents'
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_document'])) {
    // Get form data
    $application_id = $_POST['application_id'] ?? '';
    $document_type = $_POST['document_type'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Validate application belongs to student
    $check_app = $conn->prepare("SELECT application_id FROM visa_renewal_application WHERE application_id = ? AND student_id = ?");
    $check_app->bind_param("ii", $application_id, $student_id);
    $check_app->execute();
    $check_app->store_result();
    
    if ($check_app->num_rows == 0) {
        $upload_error = "Invalid application ID or you don't have permission to upload documents for this application.";
    } elseif (empty($document_type)) {
        $upload_error = "Please select a document type.";
    } elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] == UPLOAD_ERR_NO_FILE) {
        $upload_error = "Please select a file to upload.";
    } else {
        $file = $_FILES['document_file'];
        
        // File validation
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if ($file['size'] > $max_size) {
            $upload_error = "File size exceeds 10MB limit.";
        } elseif (!in_array($file['type'], $allowed_types)) {
            $upload_error = "Only PDF, JPEG, PNG, and GIF files are allowed.";
        } else {
            // Generate unique filename
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'doc_' . $student_id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
            $upload_path = '../uploads/documents/' . $filename;
            
            // Create uploads directory if it doesn't exist
            if (!is_dir('../uploads/documents')) {
                mkdir('../uploads/documents', 0777, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Use the stored procedure to insert document
                $insert_query = "CALL sp_student_add_visa_document(?, ?, ?, ?, @document_id)";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param("iiss", $student_id, $application_id, $document_type, $upload_path);
                
                if ($stmt->execute()) {
                    $upload_success = true;
                    // Get the generated document ID
                    $result = $conn->query("SELECT @document_id as doc_id");
                    $doc_result = $result->fetch_assoc();
                    $new_doc_id = $doc_result['doc_id'] ?? 0;
                } else {
                    $upload_error = "Failed to save document information to database.";
                    // Delete uploaded file if DB insert failed
                    unlink($upload_path);
                }
                $stmt->close();
            } else {
                $upload_error = "Failed to upload file. Please try again.";
            }
        }
    }
    $check_app->close();
}

// Handle document deletion FIRST before rendering anything
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $doc_id = $_GET['delete'];
    
    try {
        // First, get the file path to delete the physical file
        $get_file_query = "
            SELECT d.document_path 
            FROM visa_document d
            JOIN visa_renewal_application a ON d.application_id = a.application_id
            WHERE d.document_id = ? AND a.student_id = ?
        ";
        $get_stmt = $conn->prepare($get_file_query);
        $get_stmt->bind_param("ii", $doc_id, $student_id);
        $get_stmt->execute();
        $get_stmt->bind_result($file_path);
        $get_stmt->fetch();
        $get_stmt->close();
        
        // Use stored procedure to delete document
        $delete_query = "CALL sp_student_delete_visa_document(?, ?)";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("ii", $student_id, $doc_id);
        
        if ($stmt->execute()) {
            // Delete the physical file if it exists
            if (!empty($file_path) && file_exists($file_path)) {
                unlink($file_path);
            }
            
            $_SESSION['success_message'] = "Document deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete document from database.";
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error deleting document: " . $e->getMessage();
    }
    
    // Redirect to avoid resubmission
    header("Location: documents.php");
    exit();
}

// Fetch student's active renewal applications
$applications_query = "
    SELECT application_id, submission_date, requested_months, status 
    FROM visa_renewal_application 
    WHERE student_id = ? 
    AND status != 'Passport collected'
    ORDER BY submission_date DESC
";
$apps_stmt = $conn->prepare($applications_query);
$apps_stmt->bind_param("i", $student_id);
$apps_stmt->execute();
$applications = $apps_stmt->get_result();

// Fetch all documents for the student
$documents_query = "
    SELECT 
        d.document_id,
        d.document_type,
        d.document_path,
        d.upload_date,
        a.application_id,
        a.submission_date,
        a.status as app_status
    FROM visa_document d
    JOIN visa_renewal_application a ON d.application_id = a.application_id
    WHERE a.student_id = ?
    ORDER BY d.upload_date DESC
";
$docs_stmt = $conn->prepare($documents_query);
$docs_stmt->bind_param("i", $student_id);
$docs_stmt->execute();
$documents_result = $docs_stmt->get_result();

// Store documents in array for later use
$documents = [];
while ($doc = $documents_result->fetch_assoc()) {
    $documents[] = $doc;
}
$documents_result->close();

// Check for success/error messages from session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .document-actions .btn {
            padding: 0.25rem 0.5rem;
        }
        .file-icon {
            font-size: 1.25rem;
            width: 30px;
        }
        .modal-backdrop {
            z-index: 1040;
        }
        .modal {
            z-index: 1050;
        }
        .cursor-pointer {
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-folder me-2"></i>My Documents</h1>
            <p class="text-muted mb-0">Upload and manage your visa renewal documents</p>
        </div>
        <div class="bg-primary text-white rounded p-3">
            <div class="h5 mb-0">ID: <?php echo $student_id; ?></div>
            <small class="opacity-75">Student ID</small>
        </div>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if ($upload_success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <strong>Success!</strong> Document uploaded successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php elseif (!empty($upload_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Error!</strong> <?php echo htmlspecialchars($upload_error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Upload Document Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-upload me-2"></i> Upload New Document</h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="application_id" class="form-label">Select Application <span class="text-danger">*</span></label>
                        <select class="form-select" id="application_id" name="application_id" required>
                            <option value="">Choose an application...</option>
                            <?php if ($applications->num_rows > 0): ?>
                                <?php while ($app = $applications->fetch_assoc()): ?>
                                <option value="<?php echo $app['application_id']; ?>">
                                    Application #<?php echo $app['application_id']; ?> 
                                    (Submitted: <?php echo date('M d, Y', strtotime($app['submission_date'])); ?>)
                                </option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option value="" disabled>No active applications found</option>
                            <?php endif; ?>
                        </select>
                        <?php if ($applications->num_rows == 0): ?>
                        <div class="form-text text-warning">
                            You need to submit a visa renewal application first.
                            <a href="renewal.php" class="text-decoration-none">Apply for renewal</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="document_type" class="form-label">Document Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="document_type" name="document_type" required>
                            <option value="">Select document type...</option>
                            <?php foreach ($document_types as $type => $desc): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>">
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-12 mb-3">
                        <label for="document_file" class="form-label">Select File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="document_file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,.gif" required>
                        <div class="form-text">
                            Accepted formats: PDF, JPG, PNG, GIF (Max: 10MB)
                        </div>
                    </div>
                    
                    <div class="col-12 mb-3">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="2" placeholder="Add any notes about this document..."></textarea>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" name="upload_document" class="btn btn-primary">
                            <i class="bi bi-upload me-2"></i> Upload Document
                        </button>
                        <button type="reset" class="btn btn-outline-secondary">Clear Form</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Document List Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-files me-2"></i> My Documents</h5>
            <span class="badge bg-light text-dark">
                <?php echo count($documents); ?> document(s)
            </span>
        </div>
        <div class="card-body">
            <?php if (count($documents) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Document Type</th>
                            <th>Application ID</th>
                            <th>Upload Date</th>
                            <th>File</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                        <?php
                        // Get file icon based on extension
                        $file_ext = pathinfo($doc['document_path'], PATHINFO_EXTENSION);
                        $file_icon = 'bi-file-earmark';
                        $file_color = 'text-secondary';
                        
                        switch(strtolower($file_ext)) {
                            case 'pdf': $file_icon = 'bi-file-earmark-pdf'; $file_color = 'text-danger'; break;
                            case 'jpg':
                            case 'jpeg':
                            case 'png':
                            case 'gif': $file_icon = 'bi-file-earmark-image'; $file_color = 'text-success'; break;
                            default: $file_icon = 'bi-file-earmark'; $file_color = 'text-secondary';
                        }
                        
                        // Get file size
                        $file_size = file_exists($doc['document_path']) ? filesize($doc['document_path']) : 0;
                        $file_size_formatted = $file_size > 0 ? round($file_size / 1024 / 1024, 2) . ' MB' : 'N/A';
                        ?>
                        <tr id="row-<?php echo $doc['document_id']; ?>">
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($doc['document_type']); ?></div>
                                <?php if (isset($document_types[$doc['document_type']])): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($document_types[$doc['document_type']]); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info">#<?php echo $doc['application_id']; ?></span><br>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($doc['submission_date'])); ?></small>
                            </td>
                            <td>
                                <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?><br>
                                <small class="text-muted"><?php echo date('H:i', strtotime($doc['upload_date'])); ?></small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="bi <?php echo $file_icon; ?> file-icon me-2 <?php echo $file_color; ?>"></i>
                                    <div>
                                        <div><?php echo strtoupper($file_ext); ?> File</div>
                                        <small class="text-muted"><?php echo $file_size_formatted; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="document-actions">
                                    <a href="<?php echo htmlspecialchars($doc['document_path']); ?>" 
                                       target="_blank" 
                                       class="btn btn-sm btn-outline-primary me-1"
                                       title="View Document">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?php echo htmlspecialchars($doc['document_path']); ?>" 
                                       download
                                       class="btn btn-sm btn-outline-success me-1"
                                       title="Download">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger delete-btn"
                                            data-doc-id="<?php echo $doc['document_id']; ?>"
                                            data-doc-type="<?php echo htmlspecialchars($doc['document_type']); ?>"
                                            data-app-id="<?php echo $doc['application_id']; ?>"
                                            title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                <h5>No Documents Found</h5>
                <p class="text-muted mb-3">You haven't uploaded any documents yet.</p>
                <p class="text-muted">Upload documents using the form above to get started.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Help Information -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i> Document Requirements</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>All documents must be clear and legible</li>
                        <li>Passport copies should show all 4 corners</li>
                        <li>Files should not exceed 10MB</li>
                        <li>Use PDF format for multi-page documents</li>
                        <li>Keep original documents for verification</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i> Processing Time</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Documents are reviewed within 3-5 working days</li>
                        <li>Incomplete submissions will be rejected</li>
                        <li>You'll receive email notifications</li>
                        <li>Check application status regularly</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal (Single Modal for all deletions) -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this document?</p>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    This action cannot be undone. The file will be permanently deleted.
                </div>
                <p><strong>Document:</strong> <span id="deleteDocType"></span></p>
                <p><strong>Application:</strong> #<span id="deleteAppId"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" class="btn btn-danger" id="confirmDeleteBtn">Delete Document</a>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Document ready function
document.addEventListener('DOMContentLoaded', function() {
    // Handle delete button clicks
    const deleteButtons = document.querySelectorAll('.delete-btn');
    const deleteConfirmModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deleteDocType = document.getElementById('deleteDocType');
    const deleteAppId = document.getElementById('deleteAppId');
    
    let currentDocId = null;
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            currentDocId = this.getAttribute('data-doc-id');
            const docType = this.getAttribute('data-doc-type');
            const appId = this.getAttribute('data-app-id');
            
            // Update modal content
            deleteDocType.textContent = docType;
            deleteAppId.textContent = appId;
            
            // Set delete URL
            confirmDeleteBtn.href = `documents.php?delete=${currentDocId}`;
            
            // Show modal
            deleteConfirmModal.show();
        });
    });
    
    // Clear currentDocId when modal is hidden
    document.getElementById('deleteConfirmModal').addEventListener('hidden.bs.modal', function() {
        currentDocId = null;
    });
    
    // Handle confirm delete button click
    confirmDeleteBtn.addEventListener('click', function(e) {
        if (currentDocId) {
            // Add a small delay to allow modal to close
            setTimeout(() => {
                // You could also add AJAX here if you want to avoid page reload
                window.location.href = this.href;
            }, 100);
        }
    });
});
</script>

<?php
// Close database connections
if (isset($apps_stmt)) $apps_stmt->close();
if (isset($docs_stmt)) $docs_stmt->close();
?>
</body>
</html>
