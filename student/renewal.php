<?php
// Set page title
$page_title = "Visa Renewal - ISSU";

// Include header
require_once 'header.php';

// Note: $conn and $student_id are already available from header.php

// Initialize variables
$success_message = '';
$error_message = '';
$has_active_application = false;
$current_visa = null;

// Fetch current visa information
$visa_query = "
    SELECT * FROM student_visa 
    WHERE student_id = ? AND status = 'Active' 
    ORDER BY expiry_date DESC 
    LIMIT 1
";
$visa_stmt = $conn->prepare($visa_query);
$visa_stmt->bind_param("i", $student_id);
$visa_stmt->execute();
$current_visa = $visa_stmt->get_result()->fetch_assoc();

// Check for existing active renewal application
$active_app_query = "
    SELECT * FROM visa_renewal_application 
    WHERE student_id = ? AND status != 'Passport collected' 
    LIMIT 1
";
$active_app_stmt = $conn->prepare($active_app_query);
$active_app_stmt->bind_param("i", $student_id);
$active_app_stmt->execute();
$active_application = $active_app_stmt->get_result()->fetch_assoc();
$has_active_application = ($active_application !== null);

// Handle renewal form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_renewal'])) {
    // Validate if student has active visa
    if (!$current_visa) {
        $error_message = "You don't have an active visa to renew.";
    } 
    // Check for existing active application
    elseif ($has_active_application) {
        $error_message = "You already have an active renewal application (ID: #" . $active_application['application_id'] . ").";
    }
    // Validate requested months
    elseif (empty($_POST['requested_months']) || !is_numeric($_POST['requested_months'])) {
        $error_message = "Please enter a valid number of months for renewal.";
    }
    elseif ($_POST['requested_months'] <= 0 || $_POST['requested_months'] > 24) {
        $error_message = "Renewal period must be between 1 and 24 months.";
    }
    else {
        $requested_months = (int)$_POST['requested_months'];
        
        // Use stored procedure to submit renewal
        $submit_query = "CALL sp_student_submit_visa_renewal_form(?, ?, @application_id)";
        $stmt = $conn->prepare($submit_query);
        $stmt->bind_param("ii", $student_id, $requested_months);
        
        if ($stmt->execute()) {
            // Get the generated application ID
            $result = $conn->query("SELECT @application_id as app_id");
            $app_result = $result->fetch_assoc();
            $new_app_id = $app_result['app_id'] ?? 0;
            
            $success_message = "Renewal application submitted successfully! Application ID: #" . $new_app_id;
            
            // Refresh active application status
            $has_active_application = true;
            $active_application = ['application_id' => $new_app_id, 'status' => 'Pending'];
            
        } else {
            $error_message = "Failed to submit renewal application. Please try again.";
        }
        $stmt->close();
    }
}

// Fetch renewal history
$history_query = "
    SELECT * FROM visa_renewal_application 
    WHERE student_id = ? 
    ORDER BY submission_date DESC 
    LIMIT 10
";
$history_stmt = $conn->prepare($history_query);
$history_stmt->bind_param("i", $student_id);
$history_stmt->execute();
$renewal_history = $history_stmt->get_result();

// Calculate days until visa expiry
$days_until_expiry = null;
if ($current_visa && $current_visa['expiry_date']) {
    $expiry_date = new DateTime($current_visa['expiry_date']);
    $today = new DateTime();
    $interval = $today->diff($expiry_date);
    $days_until_expiry = $interval->days;
    if ($interval->invert) {
        $days_until_expiry = -$days_until_expiry; // Negative if expired
    }
}
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-arrow-clockwise me-2"></i>Visa Renewal</h1>
            <p class="text-muted mb-0">Apply for student pass renewal</p>
        </div>
        <div class="bg-primary text-white rounded p-3">
            <div class="h5 mb-0">ID: <?php echo $student_id; ?></div>
            <small class="opacity-75">Student ID</small>
        </div>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <strong>Success!</strong> <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Error!</strong> <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Current Visa Status Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-passport me-2"></i> Current Visa Status</h5>
        </div>
        <div class="card-body">
            <?php if ($current_visa): ?>
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Passport Number:</th>
                            <td><?php echo htmlspecialchars($current_visa['passport_no']); ?></td>
                        </tr>
                        <tr>
                            <th>Visa Type:</th>
                            <td><?php echo htmlspecialchars($current_visa['visa_type']); ?></td>
                        </tr>
                        <tr>
                            <th>Issue Date:</th>
                            <td><?php echo date('M d, Y', strtotime($current_visa['issue_date'])); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Expiry Date:</th>
                            <td>
                                <span class="<?php echo ($days_until_expiry <= 30) ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo date('M d, Y', strtotime($current_visa['expiry_date'])); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <span class="badge <?php echo ($current_visa['status'] == 'Active') ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo htmlspecialchars($current_visa['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Days Left:</th>
                            <td>
                                <?php if ($days_until_expiry > 0): ?>
                                <span class="badge <?php echo ($days_until_expiry <= 30) ? 'bg-warning' : 'bg-success'; ?>">
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
                    </table>
                </div>
            </div>
            
            <?php if ($days_until_expiry !== null && $days_until_expiry <= 60): ?>
            <div class="alert alert-info mt-3">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Renewal Notice:</strong> 
                <?php if ($days_until_expiry > 0): ?>
                Your visa expires in <?php echo $days_until_expiry; ?> days. 
                <?php else: ?>
                Your visa has expired. 
                <?php endif; ?>
                It's recommended to renew at least 30 days before expiry.
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="text-center py-4">
                <i class="bi bi-passport fs-1 text-muted mb-3"></i>
                <h5>No Active Visa Found</h5>
                <p class="text-muted mb-3">You don't have an active student pass.</p>
                <a href="../contact.php" class="btn btn-primary">Contact ISSU Office</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($current_visa): ?>
    <!-- Renewal Application Section -->
    <div class="row">
        <!-- Left Column: Submit Renewal -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-send-check me-2"></i> Submit Renewal Application</h5>
                </div>
                <div class="card-body">
                    <?php if ($has_active_application): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-hourglass-split fs-1 text-primary mb-3"></i>
                        <h5>Application in Progress</h5>
                        <p class="text-muted mb-3">
                            You have an active renewal application (ID: #<?php echo $active_application['application_id']; ?>).
                        </p>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Current Status:</strong> 
                            <span class="badge bg-info">
                                <?php echo htmlspecialchars($active_application['status']); ?>
                            </span>
                        </div>
                        <div class="mt-3">
                            <a href="documents.php" class="btn btn-primary me-2">
                                <i class="bi bi-upload me-2"></i>Upload Documents
                            </a>
                            <a href="#" class="btn btn-outline-secondary">
                                <i class="bi bi-clock-history me-2"></i>Check Status
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="current_expiry" class="form-label">Current Expiry Date</label>
                                <input type="text" class="form-control" id="current_expiry" 
                                       value="<?php echo date('M d, Y', strtotime($current_visa['expiry_date'])); ?>" 
                                       readonly>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="requested_months" class="form-label">
                                    Renewal Duration <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="requested_months" 
                                           name="requested_months" min="1" max="24" 
                                           value="12" required>
                                    <span class="input-group-text">months</span>
                                </div>
                                <div class="form-text">
                                    Standard renewal is 12 months. Max allowed: 24 months.
                                </div>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Estimated New Expiry Date</label>
                                <div class="alert alert-secondary">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar-check fs-4 me-3"></i>
                                        <div>
                                            <div id="estimated_date" class="fw-bold">
                                                <?php 
                                                $new_expiry = date('M d, Y', strtotime($current_visa['expiry_date'] . ' + 12 months'));
                                                echo $new_expiry;
                                                ?>
                                            </div>
                                            <small class="text-muted">Based on 12 months renewal</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 mb-4">
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Important:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Submit renewal at least 30 days before expiry</li>
                                        <li>You must upload required documents after submission</li>
                                        <li>Processing time: 14-21 working days</li>
                                        <li>Your current visa must be valid at time of submission</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="reset" class="btn btn-outline-secondary me-2">Reset</button>
                                    <button type="submit" name="submit_renewal" class="btn btn-primary">
                                        <i class="bi bi-send-check me-2"></i>Submit Renewal Application
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Required Documents -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i> Required Documents</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item border-0 px-0 py-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <span class="fw-semibold">Passport Copy</span>
                                    <small class="text-muted d-block">All pages including photo page</small>
                                </div>
                                <div class="list-group-item border-0 px-0 py-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <span class="fw-semibold">Offer Letter</span>
                                    <small class="text-muted d-block">Current university offer letter</small>
                                </div>
                                <div class="list-group-item border-0 px-0 py-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <span class="fw-semibold">Academic Transcript</span>
                                    <small class="text-muted d-block">Latest semester results</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item border-0 px-0 py-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <span class="fw-semibold">Financial Proof</span>
                                    <small class="text-muted d-block">Bank statement/sponsor letter</small>
                                </div>
                                <div class="list-group-item border-0 px-0 py-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <span class="fw-semibold">Passport Photos</span>
                                    <small class="text-muted d-block">2 recent color photographs</small>
                                </div>
                                <div class="list-group-item border-0 px-0 py-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <span class="fw-semibold">Medical Report</span>
                                    <small class="text-muted d-block">If required (for certain countries)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="documents.php" class="btn btn-outline-primary">
                            <i class="bi bi-upload me-2"></i>Go to Document Upload
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Information -->
        <div class="col-lg-4">
            <!-- Renewal History -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Renewal History</h5>
                </div>
                <div class="card-body">
                    <?php if ($renewal_history->num_rows > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($history = $renewal_history->fetch_assoc()): ?>
                        <?php 
                        $status_class = '';
                        switch($history['status']) {
                            case 'Pending': $status_class = 'warning'; break;
                            case 'Submitted passport to ISSU': $status_class = 'info'; break;
                            case 'Passport collected': $status_class = 'success'; break;
                            default: $status_class = 'secondary';
                        }
                        ?>
                        <div class="list-group-item border-0 px-0 py-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold">Application #<?php echo $history['application_id']; ?></div>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y', strtotime($history['submission_date'])); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($history['status']); ?>
                                    </span>
                                    <div class="text-muted small">
                                        <?php echo $history['requested_months']; ?> months
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-3">
                        <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                        <p class="text-muted">No renewal history found</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Processing Timeline -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-clock me-2"></i> Processing Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Application Submitted</h6>
                                <small class="text-muted">Day 1-2: Initial review</small>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-info"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Document Verification</h6>
                                <small class="text-muted">Day 3-7: Document check</small>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-warning"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">ISSU Processing</h6>
                                <small class="text-muted">Day 8-14: Immigration processing</small>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Passport Ready</h6>
                                <small class="text-muted">Day 15-21: Collection notice</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-link-45deg me-2"></i> Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="documents.php" class="btn btn-outline-primary text-start">
                            <i class="bi bi-upload me-2"></i>Upload Documents
                        </a>
                        <a href="visa.php" class="btn btn-outline-success text-start">
                            <i class="bi bi-passport me-2"></i>View Visa Details
                        </a>
                        <a href="../contact.php" class="btn btn-outline-info text-start">
                            <i class="bi bi-telephone me-2"></i>Contact ISSU
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Close database connections
if (isset($visa_stmt)) $visa_stmt->close();
if (isset($active_app_stmt)) $active_app_stmt->close();
if (isset($history_stmt)) $history_stmt->close();

// Include footer
require_once 'footer.php';
?>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline:before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}
.timeline-item {
    position: relative;
    margin-bottom: 20px;
}
.timeline-marker {
    position: absolute;
    left: -30px;
    top: 5px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 3px solid white;
}
.timeline-content {
    margin-left: 10px;
}
</style>

<script>
// Update estimated date based on months input
document.getElementById('requested_months').addEventListener('input', function() {
    const currentExpiry = '<?php echo $current_visa ? $current_visa['expiry_date'] : ''; ?>';
    if (currentExpiry) {
        const months = parseInt(this.value) || 12;
        const expiryDate = new Date(currentExpiry);
        expiryDate.setMonth(expiryDate.getMonth() + months);
        
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        document.getElementById('estimated_date').textContent = expiryDate.toLocaleDateString('en-US', options);
    }
});
</script>
