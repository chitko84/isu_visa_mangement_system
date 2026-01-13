<?php
// Set page title
$page_title = "Exit Clearance - ISSU";

// Include header
require_once 'header.php';

// Note: $conn and $student_id are already available from header.php

// Fetch student basic details
$student_query = "
    SELECT s.*, p.program_name, sc.school_name
    FROM student s
    LEFT JOIN program p ON s.program_id = p.program_id
    LEFT JOIN school sc ON p.school_id = sc.school_id
    WHERE s.student_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Check if student details were found
if (!$student) {
    echo '<div class="alert alert-danger">Student details not found. Please contact support.</div>';
    require_once 'footer.php';
    exit();
}

// Fetch active visa details
$visa_query = "
    SELECT * FROM student_visa 
    WHERE student_id = ? AND status = 'Active'
    LIMIT 1
";
$visa_stmt = $conn->prepare($visa_query);
$visa_stmt->bind_param("i", $student_id);
$visa_stmt->execute();
$visa = $visa_stmt->get_result()->fetch_assoc();

// Fetch existing exit case (if any)
$exit_case_query = "
    SELECT ec.*, cr.status as clearance_status, cr.submission_date as clearance_submitted
    FROM exit_case ec
    LEFT JOIN clearance_record cr ON ec.exit_id = cr.exit_id
    WHERE ec.student_id = ?
    ORDER BY ec.request_date DESC
    LIMIT 1
";
$exit_stmt = $conn->prepare($exit_case_query);
$exit_stmt->bind_param("i", $student_id);
$exit_stmt->execute();
$exit_case = $exit_stmt->get_result()->fetch_assoc();

// Fetch clearance details if exit case exists
$clearance_details = null;
$unit_clearances = null;
$exit_visa_actions = null;

if ($exit_case) {
    // Fetch clearance record
    $clearance_query = "
        SELECT * FROM clearance_record 
        WHERE exit_id = ?
        ORDER BY submission_date DESC
        LIMIT 1
    ";
    $clearance_stmt = $conn->prepare($clearance_query);
    $clearance_stmt->bind_param("i", $exit_case['exit_id']);
    $clearance_stmt->execute();
    $clearance_details = $clearance_stmt->get_result()->fetch_assoc();
    
    // Fetch unit clearances
    if ($clearance_details) {
        $unit_query = "
            SELECT * FROM unit_clearance 
            WHERE clearance_id = ?
            ORDER BY unit_name
        ";
        $unit_stmt = $conn->prepare($unit_query);
        $unit_stmt->bind_param("i", $clearance_details['clearance_id']);
        $unit_stmt->execute();
        $unit_clearances = $unit_stmt->get_result();
    }
    
    // Fetch visa actions
    $visa_actions_query = "
        SELECT * FROM exit_visa_action 
        WHERE exit_id = ?
        ORDER BY action_date DESC
    ";
    $visa_actions_stmt = $conn->prepare($visa_actions_query);
    $visa_actions_stmt->bind_param("i", $exit_case['exit_id']);
    $visa_actions_stmt->execute();
    $exit_visa_actions = $visa_actions_stmt->get_result();
}

// Calculate visa expiry days
$visa_expiry_days = null;
if ($visa && $visa['expiry_date']) {
    $expiry_date = new DateTime($visa['expiry_date']);
    $today = new DateTime();
    $interval = $today->diff($expiry_date);
    $visa_expiry_days = $interval->days;
    if ($interval->invert) {
        $visa_expiry_days = -$visa_expiry_days;
    }
}
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 fw-bold text-primary">Exit Clearance</h1>
            <p class="text-muted mb-0">Manage your exit process and clearance status</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- IMPORTANT INFORMATION SECTION - MOVED TO TOP & PERMANENT -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> Important Information</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning mb-3 alert-permanent"> <!-- Added alert-permanent class -->
                        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Before You Exit</h6>
                        <ul class="mb-0 small">
                            <li>Ensure all fees are cleared</li>
                            <li>Return all university property</li>
                            <li>Complete all academic requirements</li>
                            <li>Clear any outstanding library books</li>
                            <li>Update your contact information</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-info mb-0 alert-permanent"> <!-- Added alert-permanent class -->
                        <h6 class="alert-heading"><i class="bi bi-clock-history me-2"></i>Processing Time</h6>
                        <p class="mb-0 small">Exit clearance typically takes 7-14 working days to process after all documents are submitted.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts - Removed data-bs-dismiss to prevent auto-hiding -->
    <?php if ($visa_expiry_days !== null && $visa_expiry_days < 0): ?>
    <div class="alert alert-danger fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Visa Expired!</strong> Your visa has expired. Please resolve this before proceeding with exit clearance.
    </div>
    <?php endif; ?>
    
    <?php if ($exit_case && $exit_case['exit_status'] == 'Pending'): ?>
    <div class="alert alert-info fade show" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>Exit Request Pending</strong> Your exit request is being processed. Check the status below.
    </div>
    <?php endif; ?>
    
    <?php if ($exit_case && $exit_case['exit_status'] == 'Approved'): ?>
    <div class="alert alert-success fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <strong>Exit Approved!</strong> Your exit request has been approved. Please complete the remaining steps.
    </div>
    <?php endif; ?>

    <!-- Stats Overview -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card bg-white rounded-3 p-4 shadow-sm border">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-primary bg-opacity-10 text-primary rounded-circle p-3 me-3">
                        <i class="bi bi-box-arrow-right fs-4"></i>
                    </div>
                    <div>
                        <h3 class="h5 mb-1 fw-bold">Exit Status</h3>
                        <?php if ($exit_case): ?>
                            <span class="badge <?php 
                                echo ($exit_case['exit_status'] == 'Approved') ? 'bg-success' : 
                                     (($exit_case['exit_status'] == 'Pending') ? 'bg-warning' : 'bg-danger'); 
                            ?>">
                                <?php echo htmlspecialchars($exit_case['exit_status']); ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Not Started</span>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="text-muted mb-0">
                    <?php if ($exit_case): ?>
                        <?php echo date('M d, Y', strtotime($exit_case['request_date'])); ?>
                    <?php else: ?>
                        No exit request submitted
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="stat-card bg-white rounded-3 p-4 shadow-sm border">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-success bg-opacity-10 text-success rounded-circle p-3 me-3">
                        <i class="bi bi-shield-check fs-4"></i>
                    </div>
                    <div>
                        <h3 class="h5 mb-1 fw-bold">Clearance</h3>
                        <?php if ($clearance_details): ?>
                            <span class="badge <?php 
                                echo ($clearance_details['status'] == 'Completed') ? 'bg-success' : 
                                     (($clearance_details['status'] == 'In Progress') ? 'bg-warning' : 'bg-danger'); 
                            ?>">
                                <?php echo htmlspecialchars($clearance_details['status']); ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Not Started</span>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="text-muted mb-0">
                    <?php if ($unit_clearances && $unit_clearances->num_rows > 0): ?>
                        <?php echo $unit_clearances->num_rows; ?> units
                    <?php else: ?>
                        No units cleared
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="stat-card bg-white rounded-3 p-4 shadow-sm border">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-warning bg-opacity-10 text-warning rounded-circle p-3 me-3">
                        <i class="bi bi-person-badge fs-4"></i>
                    </div>
                    <div>
                        <h3 class="h5 mb-1 fw-bold">Visa Status</h3>
                        <?php if ($visa): ?>
                            <span class="badge <?php 
                                echo ($visa['status'] == 'Active') ? 'bg-success' : 'bg-danger'; 
                            ?>">
                                <i class="bi bi-check-circle me-1"></i><?php echo htmlspecialchars($visa['status']); ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>No Active Visa</span>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="text-muted mb-0">
                    <?php if ($visa && $visa['expiry_date']): ?>
                        Expires: <?php echo date('M d, Y', strtotime($visa['expiry_date'])); ?>
                    <?php else: ?>
                        No expiry date
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="stat-card bg-white rounded-3 p-4 shadow-sm border">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-info bg-opacity-10 text-info rounded-circle p-3 me-3">
                        <i class="bi bi-person-check fs-4"></i>
                    </div>
                    <div>
                        <h3 class="h5 mb-1 fw-bold">Student Status</h3>
                        <span class="badge <?php echo ($student['status'] == 'Active') ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo htmlspecialchars($student['status']); ?>
                        </span>
                    </div>
                </div>
                <p class="text-muted mb-0">
                    <?php echo htmlspecialchars($student['program_name'] ?? 'No Program'); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row g-4">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Exit Request Section -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-box-arrow-right me-2"></i> Exit Request</h5>
                </div>
                <div class="card-body">
                    <?php if ($exit_case): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Exit Type:</th>
                                        <td><?php echo htmlspecialchars($exit_case['exit_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Request Date:</th>
                                        <td><?php echo date('M d, Y', strtotime($exit_case['request_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Exit Status:</th>
                                        <td>
                                            <span class="badge <?php 
                                                echo ($exit_case['exit_status'] == 'Approved') ? 'bg-success' : 
                                                     (($exit_case['exit_status'] == 'Pending') ? 'bg-warning' : 'bg-danger'); 
                                            ?>">
                                                <?php echo htmlspecialchars($exit_case['exit_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Clearance Status:</th>
                                        <td>
                                            <?php if ($clearance_details): ?>
                                                <span class="badge <?php 
                                                    echo ($clearance_details['status'] == 'Completed') ? 'bg-success' : 
                                                         (($clearance_details['status'] == 'In Progress') ? 'bg-warning' : 'bg-danger'); 
                                                ?>">
                                                    <?php echo htmlspecialchars($clearance_details['status']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not Submitted</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Submission Date:</th>
                                        <td>
                                            <?php if ($clearance_details && $clearance_details['submission_date']): ?>
                                                <?php echo date('M d, Y', strtotime($clearance_details['submission_date'])); ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Actions:</th>
                                        <td>
                                            <?php if ($exit_case['exit_status'] == 'Approved'): ?>
                                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#downloadCertificateModal">
                                                    <i class="bi bi-download me-1"></i> Download Certificate
                                                </button>
                                            <?php elseif ($exit_case['exit_status'] == 'Pending'): ?>
                                                <button class="btn btn-sm btn-warning" disabled>
                                                    <i class="bi bi-clock me-1"></i> Processing
                                                </button>
                                            <?php else: ?>
                                                <a href="submit_exit.php" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil me-1"></i> Update Request
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Clearance Progress -->
                        <?php 
                        // Reset unit_clearances pointer if needed
                        if ($unit_clearances && $unit_clearances->num_rows > 0) {
                            $unit_clearances->data_seek(0);
                        ?>
                        <hr>
                        <h6 class="fw-bold mb-3">Unit Clearance Progress</h6>
                        <div class="row">
                            <?php while ($unit = $unit_clearances->fetch_assoc()): ?>
                            <div class="col-md-4 mb-2">
                                <div class="d-flex align-items-center p-2 border rounded">
                                    <div class="me-3">
                                        <?php if ($unit['clearance_date']): ?>
                                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                        <?php else: ?>
                                            <i class="bi bi-clock text-warning fs-5"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($unit['unit_name']); ?></div>
                                        <small class="text-muted">
                                            <?php if ($unit['clearance_date']): ?>
                                                Cleared: <?php echo date('M d, Y', strtotime($unit['clearance_date'])); ?>
                                            <?php else: ?>
                                                Pending
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php } ?>
                        
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-box-arrow-right fs-1 text-muted mb-3"></i>
                            <h5>No Exit Request Found</h5>
                            <p class="text-muted mb-3">You haven't submitted an exit request yet. Start the process to begin your clearance.</p>
                            <?php if ($visa && $visa['status'] == 'Active'): ?>
                                <a href="submit_exit.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i> Submit Exit Request
                                </a>
                            <?php else: ?>
                                <button class="btn btn-primary" disabled title="You need an active visa to submit exit request">
                                    <i class="bi bi-plus-circle me-1"></i> Submit Exit Request
                                </button>
                                <p class="text-danger small mt-2">You need an active visa to submit an exit request</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Visa Actions History -->
            <?php 
            if ($exit_visa_actions && $exit_visa_actions->num_rows > 0) {
                $exit_visa_actions->data_seek(0);
            ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-passport me-2"></i> Visa Actions</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Action Type</th>
                                    <th>Date</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($action = $exit_visa_actions->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="badge 
                                            <?php echo ($action['action_type'] == 'Visa Cancelled') ? 'bg-danger' : 
                                                   (($action['action_type'] == 'Exit Endorsement') ? 'bg-success' : 'bg-info'); ?>">
                                            <?php echo htmlspecialchars($action['action_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($action['action_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($action['remarks'] ?? 'No remarks'); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
        
        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php if (!$exit_case): ?>
                        <div class="col-12">
                            <a href="submit_exit.php" class="quick-action-btn d-block text-center p-3 bg-light rounded-3 text-decoration-none <?php echo (!$visa || $visa['status'] != 'Active') ? 'disabled' : ''; ?>">
                                <i class="bi bi-box-arrow-right fs-2 text-primary mb-2"></i>
                                <div class="fw-semibold">Submit Exit Request</div>
                                <?php if (!$visa || $visa['status'] != 'Active'): ?>
                                <small class="text-danger">(Requires active visa)</small>
                                <?php endif; ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($exit_case && $clearance_details && $clearance_details['status'] == 'In Progress'): ?>
                        <div class="col-12">
                            <a href="upload_documents.php?type=clearance" class="quick-action-btn d-block text-center p-3 bg-light rounded-3 text-decoration-none">
                                <i class="bi bi-upload fs-2 text-primary mb-2"></i>
                                <div class="fw-semibold">Upload Clearance Docs</div>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($exit_case && $exit_case['exit_status'] == 'Approved'): ?>
                        <div class="col-12">
                            <a href="#" class="quick-action-btn d-block text-center p-3 bg-light rounded-3 text-decoration-none" data-bs-toggle="modal" data-bs-target="#downloadCertificateModal">
                                <i class="bi bi-download fs-2 text-primary mb-2"></i>
                                <div class="fw-semibold">Download Certificate</div>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-12">
                            <a href="documents.php" class="quick-action-btn d-block text-center p-3 bg-light rounded-3 text-decoration-none">
                                <i class="bi bi-folder fs-2 text-primary mb-2"></i>
                                <div class="fw-semibold">View Documents</div>
                            </a>
                        </div>
                        
                        <div class="col-12">
                            <a href="contact.php" class="quick-action-btn d-block text-center p-3 bg-light rounded-3 text-decoration-none">
                                <i class="bi bi-question-circle fs-2 text-primary mb-2"></i>
                                <div class="fw-semibold">Get Help</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Certificate Download Modal -->
<div class="modal fade" id="downloadCertificateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-download me-2"></i> Download Certificate</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Select the certificate type you want to download:</p>
                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-file-text text-primary me-2"></i>
                                Exit Clearance Certificate
                            </div>
                            <i class="bi bi-download"></i>
                        </div>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-file-text text-success me-2"></i>
                                No Objection Certificate (NOC)
                            </div>
                            <i class="bi bi-download"></i>
                        </div>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-file-text text-info me-2"></i>
                                Completion Letter
                            </div>
                            <i class="bi bi-download"></i>
                        </div>
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
// Close database connections
if (isset($stmt)) $stmt->close();
if (isset($visa_stmt)) $visa_stmt->close();
if (isset($exit_stmt)) $exit_stmt->close();
if (isset($clearance_stmt)) $clearance_stmt->close();
if (isset($unit_stmt)) $unit_stmt->close();
if (isset($visa_actions_stmt)) $visa_actions_stmt->close();

// Include footer
require_once 'footer.php';
?>
