<?php
// Set page title
$page_title = "Renewal Application Status - ISSU";

// Include header
require_once 'header.php';

// Note: $conn and $student_id are already available from header.php

// Check if application ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: visa.php");
    exit();
}

$application_id = $_GET['id'];

// Verify the application belongs to the current student
$verify_query = "
    SELECT a.*, 
           s.first_name, s.last_name,
           sv.visa_type, sv.expiry_date as current_expiry,
           sv.passport_no
    FROM visa_renewal_application a
    JOIN student s ON a.student_id = s.student_id
    LEFT JOIN student_visa sv ON s.student_id = sv.student_id AND sv.status = 'Active'
    WHERE a.application_id = ? AND a.student_id = ?
    LIMIT 1
";
$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("ii", $application_id, $student_id);
$verify_stmt->execute();
$application = $verify_stmt->get_result()->fetch_assoc();

// If application doesn't exist or doesn't belong to student
if (!$application) {
    echo '<div class="alert alert-danger">Application not found or access denied.</div>';
    require_once 'footer.php';
    exit();
}

// Fetch status history for this application
$status_query = "
    SELECT * FROM visa_renewal_status 
    WHERE application_id = ? 
    ORDER BY updated_date DESC, status_id DESC
";
$status_stmt = $conn->prepare($status_query);
$status_stmt->bind_param("i", $application_id);
$status_stmt->execute();
$status_history = $status_stmt->get_result();

// Fetch documents for this application
$documents_query = "
    SELECT * FROM visa_document 
    WHERE application_id = ? 
    ORDER BY upload_date DESC
";
$docs_stmt = $conn->prepare($documents_query);
$docs_stmt->bind_param("i", $application_id);
$docs_stmt->execute();
$documents = $docs_stmt->get_result();

// Calculate estimated expiry date
$estimated_expiry = null;
if ($application['current_expiry']) {
    $current_expiry = new DateTime($application['current_expiry']);
    $current_expiry->modify('+' . $application['requested_months'] . ' months');
    $estimated_expiry = $current_expiry->format('M d, Y');
}

// Calculate processing timeline
$submission_date = new DateTime($application['submission_date']);
$today = new DateTime();
$processing_days = $submission_date->diff($today)->days;

// Determine status color
$status_color = '';
switch($application['status']) {
    case 'Pending': $status_color = 'warning'; break;
    case 'Submitted passport to ISSU': $status_color = 'info'; break;
    case 'Passport collected': $status_color = 'success'; break;
    default: $status_color = 'secondary';
}
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-clock-history me-2"></i>Renewal Application Status</h1>
            <p class="text-muted mb-0">Track your student pass renewal application progress</p>
        </div>
        <div class="bg-primary text-white rounded p-3">
            <div class="h5 mb-0">Application #<?php echo $application_id; ?></div>
            <small class="opacity-75">ID: <?php echo $student_id; ?></small>
        </div>
    </div>
    
    <!-- Application Overview Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i> Application Overview</h5>
            <span class="badge bg-<?php echo $status_color; ?>">
                <?php echo htmlspecialchars($application['status']); ?>
            </span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="info-box mb-3">
                        <div class="info-label">Student Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box mb-3">
                        <div class="info-label">Submission Date</div>
                        <div class="info-value"><?php echo date('M d, Y', strtotime($application['submission_date'])); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box mb-3">
                        <div class="info-label">Requested Duration</div>
                        <div class="info-value"><?php echo $application['requested_months']; ?> months</div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="info-box mb-3">
                        <div class="info-label">Current Passport</div>
                        <div class="info-value">
                            <?php if ($application['passport_no']): ?>
                            <code><?php echo htmlspecialchars($application['passport_no']); ?></code>
                            <?php else: ?>
                            <span class="text-muted">Not available</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box mb-3">
                        <div class="info-label">Current Visa Type</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($application['visa_type'] ?? 'Student Pass'); ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box mb-3">
                        <div class="info-label">Estimated New Expiry</div>
                        <div class="info-value">
                            <?php echo $estimated_expiry ?? 'Calculating...'; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Processing Time:</strong> 
                        Your application has been processing for <?php echo $processing_days; ?> days. 
                        Standard processing time is 14-21 working days.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Timeline -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-activity me-2"></i> Status Timeline</h5>
                </div>
                <div class="card-body">
                    <?php if ($status_history->num_rows > 0): ?>
                    <div class="timeline">
                        <?php while ($status = $status_history->fetch_assoc()): ?>
                        <?php
                        $stage_color = 'primary';
                        if (strpos(strtolower($status['stage_name']), 'approved') !== false) $stage_color = 'success';
                        if (strpos(strtolower($status['stage_name']), 'rejected') !== false) $stage_color = 'danger';
                        if (strpos(strtolower($status['stage_name']), 'pending') !== false) $stage_color = 'warning';
                        if (strpos(strtolower($status['stage_name']), 'processing') !== false) $stage_color = 'info';
                        ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-<?php echo $stage_color; ?>"></div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($status['stage_name']); ?></h6>
                                    <span class="timeline-date"><?php echo date('M d, Y', strtotime($status['updated_date'])); ?></span>
                                </div>
                                <?php if ($status['remarks']): ?>
                                <div class="timeline-body">
                                    <p class="mb-0"><?php echo htmlspecialchars($status['remarks']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-activity fs-1 text-muted mb-3"></i>
                        <h5>No Status Updates</h5>
                        <p class="text-muted">Status updates will appear here once processing begins.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Uploaded Documents -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-files me-2"></i> Uploaded Documents</h5>
                </div>
                <div class="card-body">
                    <?php if ($documents->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Document Type</th>
                                    <th>Upload Date</th>
                                    <th>File Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($doc = $documents->fetch_assoc()): ?>
                                <?php
                                $file_ext = pathinfo($doc['document_path'], PATHINFO_EXTENSION);
                                $icon_class = 'text-secondary';
                                $file_type = 'Document';
                                if (in_array(strtolower($file_ext), ['pdf'])) {
                                    $icon_class = 'text-danger';
                                    $file_type = 'PDF';
                                } elseif (in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif'])) {
                                    $icon_class = 'text-success';
                                    $file_type = 'Image';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-file-earmark <?php echo $icon_class; ?> me-3 fs-5"></i>
                                            <div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($doc['document_type']); ?></div>
                                                <?php if ($doc['upload_date']): ?>
                                                <small class="text-muted">Uploaded: <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark"><?php echo strtoupper($file_ext); ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../download.php?id=<?php echo (int)$doc['document_id']; ?>" 
                                               target="_blank" 
                                               rel="noopener"
                                               class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="../download.php?id=<?php echo (int)$doc['document_id']; ?>" 
                                               target="_blank"
                                               rel="noopener"
                                               class="btn btn-outline-success">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                        <h5>No Documents Uploaded</h5>
                        <p class="text-muted mb-3">You haven't uploaded any documents for this application.</p>
                        <a href="documents.php" class="btn btn-primary">
                            <i class="bi bi-upload me-2"></i>Upload Documents
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Processing Stages -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-list-check me-2"></i> Processing Stages</h5>
                </div>
                <div class="card-body">
                    <div class="stages-list">
                        <?php
                        $stages = [
                            'Application Submitted' => 'Your application has been received',
                            'Document Review' => 'Documents are being verified',
                            'Payment Processing' => 'Fee payment is being processed',
                            'ISSU Processing' => 'Application is with immigration',
                            'Approval' => 'Application has been approved',
                            'Passport Collection' => 'Ready for collection'
                        ];
                        
                        $current_stage = $application['status'];
                        $completed = false;
                        
                        foreach ($stages as $stage => $description):
                            $stage_completed = false;
                            $stage_active = false;
                            
                            // Check if stage is completed
                            if ($stage === 'Application Submitted') {
                                $stage_completed = true;
                            } elseif ($stage === 'Document Review' && in_array($current_stage, ['Submitted passport to ISSU', 'Passport collected'])) {
                                $stage_completed = true;
                            } elseif ($stage === 'ISSU Processing' && $current_stage === 'Submitted passport to ISSU') {
                                $stage_active = true;
                            } elseif ($stage === 'Passport Collection' && $current_stage === 'Passport collected') {
                                $stage_completed = true;
                            }
                        ?>
                        <div class="stage-item">
                            <div class="stage-icon">
                                <?php if ($stage_completed): ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <?php elseif ($stage_active): ?>
                                <i class="bi bi-arrow-right-circle-fill text-primary"></i>
                                <?php else: ?>
                                <i class="bi bi-circle text-muted"></i>
                                <?php endif; ?>
                            </div>
                            <div class="stage-content">
                                <div class="stage-title <?php echo $stage_active ? 'fw-bold text-primary' : ''; ?>">
                                    <?php echo $stage; ?>
                                </div>
                                <div class="stage-desc"><?php echo $description; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Estimated Timeline -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i> Estimated Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="timeline-estimate">
                        <?php
                        $timeline_dates = [];
                        $submission = new DateTime($application['submission_date']);
                        
                        $timeline_dates['Submission'] = $submission->format('M d');
                        $timeline_dates['Document Review'] = $submission->modify('+3 days')->format('M d');
                        $timeline_dates['ISSU Processing'] = $submission->modify('+5 days')->format('M d');
                        $timeline_dates['Approval'] = $submission->modify('+7 days')->format('M d');
                        $timeline_dates['Collection'] = $submission->modify('+3 days')->format('M d');
                        ?>
                        
                        <?php foreach ($timeline_dates as $step => $date): ?>
                        <div class="timeline-estimate-item">
                            <div class="timeline-estimate-date"><?php echo $date; ?></div>
                            <div class="timeline-estimate-step"><?php echo $step; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="alert alert-light mt-3">
                        <i class="bi bi-clock-history me-2"></i>
                        <strong>Total Estimated Time:</strong> 18 days
                        <small class="d-block text-muted mt-1">Based on average processing times</small>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="documents.php" class="btn btn-outline-primary text-start">
                            <i class="bi bi-upload me-2"></i>Upload More Documents
                        </a>
                        <a href="renewal.php" class="btn btn-outline-success text-start">
                            <i class="bi bi-plus-circle me-2"></i>Submit New Renewal
                        </a>
                        <a href="visa.php" class="btn btn-outline-info text-start">
                            <i class="bi bi-passport me-2"></i>View All Visas
                        </a>
                        <button class="btn btn-outline-secondary text-start" onclick="window.print()">
                            <i class="bi bi-printer me-2"></i>Print Status Report
                        </button>
                    </div>
                    
                    <div class="mt-3">
                        <div class="alert alert-light">
                            <i class="bi bi-question-circle me-2"></i>
                            <strong>Need Help?</strong>
                            <div class="mt-1">
                                <a href="../contact.php" class="text-decoration-none">Contact ISSU Support</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Close database connections
if (isset($verify_stmt)) $verify_stmt->close();
if (isset($status_stmt)) $status_stmt->close();
if (isset($docs_stmt)) $docs_stmt->close();

// Include footer
require_once 'footer.php';
?>

<style>
.info-box {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    height: 100%;
}
.info-label {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 5px;
}
.info-value {
    font-size: 1.125rem;
    font-weight: 600;
    color: #212529;
}

/* Timeline Styles */
.timeline {
    position: relative;
    padding-left: 40px;
}
.timeline:before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #e9ecef 0%, #dee2e6 100%);
}
.timeline-item {
    position: relative;
    margin-bottom: 25px;
}
.timeline-marker {
    position: absolute;
    left: -40px;
    top: 5px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 4px solid white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
}
.timeline-content {
    background: white;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}
.timeline-header h6 {
    margin: 0;
    flex: 1;
}
.timeline-date {
    font-size: 0.875rem;
    color: #6c757d;
    white-space: nowrap;
}
.timeline-body {
    font-size: 0.95rem;
    color: #495057;
    line-height: 1.5;
}

/* Stages List */
.stages-list {
    padding-left: 0;
    list-style: none;
}
.stage-item {
    display: flex;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e9ecef;
}
.stage-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}
.stage-icon {
    flex-shrink: 0;
    width: 30px;
    font-size: 1.25rem;
    margin-right: 15px;
}
.stage-content {
    flex: 1;
}
.stage-title {
    font-size: 0.95rem;
    margin-bottom: 4px;
}
.stage-desc {
    font-size: 0.85rem;
    color: #6c757d;
}

/* Estimate Timeline */
.timeline-estimate {
    border-left: 3px solid #0d6efd;
    padding-left: 20px;
}
.timeline-estimate-item {
    margin-bottom: 15px;
    position: relative;
}
.timeline-estimate-item:before {
    content: '';
    position: absolute;
    left: -24px;
    top: 7px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #0d6efd;
}
.timeline-estimate-date {
    font-weight: 600;
    color: #0d6efd;
    font-size: 0.95rem;
}
.timeline-estimate-step {
    font-size: 0.875rem;
    color: #495057;
}

@media print {
    .card {
        border: 1px solid #dee2e6;
        box-shadow: none;
    }
    .btn, .alert {
        display: none !important;
    }
}
</style>
