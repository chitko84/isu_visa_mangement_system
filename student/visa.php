<?php
// Set page title
$page_title = "My Student Pass - ISSU";

// Include header
require_once 'header.php';

// Note: $conn and $student_id are already available from header.php

// Fetch student visa information
$visa_query = "
    SELECT sv.*, 
           s.first_name, s.last_name, s.email, s.status as student_status,
           p.program_name, sc.school_name,
           c.country_name, n.acquired_date
    FROM student_visa sv
    JOIN student s ON sv.student_id = s.student_id
    LEFT JOIN program p ON s.program_id = p.program_id
    LEFT JOIN school sc ON p.school_id = sc.school_id
    LEFT JOIN nationality n ON s.student_id = n.student_id AND n.is_primary = 1
    LEFT JOIN country c ON n.country_id = c.country_id
    WHERE sv.student_id = ? 
    ORDER BY sv.expiry_date DESC
";
$visa_stmt = $conn->prepare($visa_query);
$visa_stmt->bind_param("i", $student_id);
$visa_stmt->execute();
$visas = $visa_stmt->get_result();

// Fetch visa renewal applications with latest status
$renewal_query = "
    SELECT v.*,
           (SELECT stage_name FROM visa_renewal_status 
            WHERE application_id = v.application_id 
            ORDER BY updated_date DESC LIMIT 1) as latest_stage,
           (SELECT updated_date FROM visa_renewal_status 
            WHERE application_id = v.application_id 
            ORDER BY updated_date DESC LIMIT 1) as stage_date
    FROM visa_renewal_application v
    WHERE v.student_id = ?
    ORDER BY v.submission_date DESC
";
$renewal_stmt = $conn->prepare($renewal_query);
$renewal_stmt->bind_param("i", $student_id);
$renewal_stmt->execute();
$renewals = $renewal_stmt->get_result();

// Fetch uploaded documents for visa applications
$documents_query = "
    SELECT d.*, v.application_id, v.submission_date
    FROM visa_document d
    JOIN visa_renewal_application v ON d.application_id = v.application_id
    WHERE v.student_id = ?
    ORDER BY d.upload_date DESC
    LIMIT 10
";
$docs_stmt = $conn->prepare($documents_query);
$docs_stmt->bind_param("i", $student_id);
$docs_stmt->execute();
$documents = $docs_stmt->get_result();

// Calculate days until visa expiry for the current visa
$current_visa = null;
$days_until_expiry = null;
if ($visas->num_rows > 0) {
    $visas->data_seek(0);
    $current_visa = $visas->fetch_assoc();
    
    if ($current_visa['expiry_date']) {
        $expiry_date = new DateTime($current_visa['expiry_date']);
        $today = new DateTime();
        $interval = $today->diff($expiry_date);
        $days_until_expiry = $interval->days;
        if ($interval->invert) {
            $days_until_expiry = -$days_until_expiry;
        }
    }
    $visas->data_seek(0); // Reset pointer
}
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-passport me-2"></i>My Student Pass</h1>
            <p class="text-muted mb-0">View and manage your student pass information</p>
        </div>
        <div class="bg-primary text-white rounded p-3">
            <div class="h5 mb-0">ID: <?php echo $student_id; ?></div>
            <small class="opacity-75">Student ID</small>
        </div>
    </div>
    
    <!-- Alert for visa expiry -->
    <?php if ($days_until_expiry !== null && $days_until_expiry <= 30): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Visa Alert!</strong> Your student pass <?php echo $days_until_expiry < 0 ? 'has expired' : 'expires in ' . $days_until_expiry . ' days'; ?>.
        <a href="renewal.php" class="alert-link">Apply for renewal now.</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Current Visa Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-passport me-2"></i> Current Student Pass</h5>
            <?php if ($current_visa): ?>
            <span class="badge bg-<?php echo $current_visa['status'] == 'Active' ? 'success' : 'danger'; ?>">
                <?php echo $current_visa['status']; ?>
            </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($current_visa): ?>
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Student Pass ID:</th>
                            <td>
                                <strong><?php echo $current_visa['visa_id']; ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <th>Type:</th>
                            <td><?php echo htmlspecialchars($current_visa['visa_type']); ?></td>
                        </tr>
                        <tr>
                            <th>Passport Number:</th>
                            <td>
                                <code><?php echo htmlspecialchars($current_visa['passport_no']); ?></code>
                            </td>
                        </tr>
                        <tr>
                            <th>Issue Date:</th>
                            <td><?php echo date('M d, Y', strtotime($current_visa['issue_date'])); ?></td>
                        </tr>
                        <tr>
                            <th>Student Status:</th>
                            <td>
                                <span class="badge bg-<?php echo $current_visa['student_status'] == 'Active' ? 'success' : 'danger'; ?>">
                                    <?php echo $current_visa['student_status']; ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Expiry Date:</th>
                            <td>
                                <span class="<?php echo ($days_until_expiry !== null && $days_until_expiry <= 30) ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo date('M d, Y', strtotime($current_visa['expiry_date'])); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <span class="badge bg-<?php echo $current_visa['status'] == 'Active' ? 'success' : 'danger'; ?>">
                                    <?php echo $current_visa['status']; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Days Left:</th>
                            <td>
                                <?php if ($days_until_expiry > 0): ?>
                                <span class="badge bg-<?php echo $days_until_expiry <= 30 ? 'warning' : 'success'; ?>">
                                    <?php echo $days_until_expiry; ?> days
                                </span>
                                <?php elseif ($days_until_expiry < 0): ?>
                                <span class="badge bg-danger">
                                    Expired <?php echo abs($days_until_expiry); ?> days ago
                                </span>
                                <?php else: ?>
                                <span class="badge bg-danger">Expires Today</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($current_visa['program_name']): ?>
                        <tr>
                            <th>Program:</th>
                            <td><?php echo htmlspecialchars($current_visa['program_name']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($current_visa['country_name']): ?>
                        <tr>
                            <th>Nationality:</th>
                            <td>
                                <?php echo htmlspecialchars($current_visa['country_name']); ?>
                                <?php if ($current_visa['acquired_date']): ?>
                                <br>
                                <small class="text-muted">Since: <?php echo date('M d, Y', strtotime($current_visa['acquired_date'])); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="renewal.php" class="btn btn-primary me-2">
                            <i class="bi bi-arrow-clockwise me-2"></i>Renew Student Pass
                        </a>
                        <button class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer me-2"></i>Print Details
                        </button>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-passport fs-1 text-muted mb-3"></i>
                <h5>No Student Pass Found</h5>
                <p class="text-muted mb-3">You don't have an active student pass.</p>
                <a href="../contact.php" class="btn btn-primary">
                    <i class="bi bi-telephone me-2"></i>Contact ISSU Office
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Visa History -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Student Pass History</h5>
        </div>
        <div class="card-body">
            <?php if ($visas->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Pass ID</th>
                            <th>Type</th>
                            <th>Passport No</th>
                            <th>Issue Date</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($visa = $visas->fetch_assoc()): ?>
                        <?php
                        // Calculate duration in days
                        $issue = new DateTime($visa['issue_date']);
                        $expiry = new DateTime($visa['expiry_date']);
                        $duration = $issue->diff($expiry);
                        $duration_days = $duration->days;
                        ?>
                        <tr>
                            <td><?php echo $visa['visa_id']; ?></td>
                            <td><?php echo htmlspecialchars($visa['visa_type']); ?></td>
                            <td><code><?php echo htmlspecialchars($visa['passport_no']); ?></code></td>
                            <td><?php echo date('M d, Y', strtotime($visa['issue_date'])); ?></td>
                            <td>
                                <?php echo date('M d, Y', strtotime($visa['expiry_date'])); ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $visa['status'] == 'Active' ? 'success' : 'danger'; ?>">
                                    <?php echo $visa['status']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo round($duration_days / 30); ?> months
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                <p class="text-muted">No student pass history found</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Renewal Applications -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Renewal Applications</h5>
                </div>
                <div class="card-body">
                    <?php if ($renewals->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>App ID</th>
                                    <th>Submitted</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Latest Stage</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($renewal = $renewals->fetch_assoc()): ?>
                                <?php
                                $status_class = '';
                                switch($renewal['status']) {
                                    case 'Pending': $status_class = 'warning'; break;
                                    case 'Submitted passport to ISSU': $status_class = 'info'; break;
                                    case 'Passport collected': $status_class = 'success'; break;
                                    default: $status_class = 'secondary';
                                }
                                ?>
                                <tr>
                                    <td>#<?php echo $renewal['application_id']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($renewal['submission_date'])); ?></td>
                                    <td><?php echo $renewal['requested_months']; ?> months</td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($renewal['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($renewal['latest_stage']): ?>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($renewal['latest_stage']); ?>
                                        </span>
                                        <?php if ($renewal['stage_date']): ?>
                                        <br>
                                        <small class="text-muted"><?php echo date('M d', strtotime($renewal['stage_date'])); ?></small>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">No updates</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="renewal_status.php?id=<?php echo $renewal['application_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i>View Details
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                        <h5>No Renewal Applications</h5>
                        <p class="text-muted mb-3">You haven't applied for student pass renewal yet.</p>
                        <a href="renewal.php" class="btn btn-primary">Apply for Renewal</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Recent Documents -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-files me-2"></i> Recent Documents</h5>
                </div>
                <div class="card-body">
                    <?php if ($documents->num_rows > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($doc = $documents->fetch_assoc()): ?>
                        <?php
                        $file_ext = pathinfo($doc['document_path'], PATHINFO_EXTENSION);
                        $icon_class = 'text-secondary';
                        if (in_array(strtolower($file_ext), ['pdf'])) $icon_class = 'text-danger';
                        elseif (in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif'])) $icon_class = 'text-success';
                        ?>
                        <div class="list-group-item border-0 px-0 py-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-file-earmark <?php echo $icon_class; ?> me-2"></i>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($doc['document_type']); ?></div>
                                            <small class="text-muted">
                                                App: #<?php echo $doc['application_id']; ?> | 
                                                <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <a href="../download.php?id=<?php echo (int)$doc['document_id']; ?>" 
                                       target="_blank" 
                                       rel="noopener"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="documents.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-folder me-1"></i>View All Documents
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-3">
                        <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                        <p class="text-muted">No documents uploaded</p>
                        <a href="documents.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-upload me-1"></i>Upload Documents
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Info -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> Student Pass Information</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item border-0 px-0 py-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <span class="fw-semibold">Validity Period</span>
                            <small class="text-muted d-block">Usually 12 months, renewable</small>
                        </div>
                        <div class="list-group-item border-0 px-0 py-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <span class="fw-semibold">Renewal Timing</span>
                            <small class="text-muted d-block">Apply 30 days before expiry</small>
                        </div>
                        <div class="list-group-item border-0 px-0 py-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <span class="fw-semibold">Required Documents</span>
                            <small class="text-muted d-block">Passport, offer letter, transcripts</small>
                        </div>
                        <div class="list-group-item border-0 px-0 py-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <span class="fw-semibold">Processing Time</span>
                            <small class="text-muted d-block">14-21 working days</small>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="renewal.php" class="btn btn-primary w-100">
                            <i class="bi bi-arrow-clockwise me-2"></i>Apply for Renewal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Close database connections
if (isset($visa_stmt)) $visa_stmt->close();
if (isset($renewal_stmt)) $renewal_stmt->close();
if (isset($docs_stmt)) $docs_stmt->close();

// Include footer
require_once 'footer.php';
?>
