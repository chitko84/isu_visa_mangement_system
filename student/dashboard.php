<?php
// Set page title
$page_title = "Student Dashboard - ISSU";

// Include header
require_once 'header.php';

// Note: $conn and $student_id are already available from header.php

// Fetch student details with program info
$student_query = "
    SELECT s.*, p.program_name, sc.school_name, 
           sv.visa_id, sv.visa_type, sv.expiry_date as visa_expiry, sv.status as visa_status,
           sv.issue_date as visa_issue_date
    FROM student s
    LEFT JOIN program p ON s.program_id = p.program_id
    LEFT JOIN school sc ON p.school_id = sc.school_id
    LEFT JOIN student_visa sv ON s.student_id = sv.student_id 
        AND sv.status = 'Active'
    WHERE s.student_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student_details = $result->fetch_assoc();

// Check if student details were found
if (!$student_details) {
    echo '<div class="alert alert-danger">Student details not found. Please contact support.</div>';
    require_once 'footer.php';
    exit();
}

// Fetch student nationality
$nationality_query = "
    SELECT c.country_name, c.region, n.acquired_date, n.is_primary
    FROM nationality n
    LEFT JOIN country c ON n.country_id = c.country_id
    WHERE n.student_id = ? AND n.is_primary = 1
    LIMIT 1
";
$nationality_stmt = $conn->prepare($nationality_query);
$nationality_stmt->bind_param("i", $student_id);
$nationality_stmt->execute();
$nationality = $nationality_stmt->get_result()->fetch_assoc();

// Fetch visa renewal applications
$renewals_query = "
    SELECT * FROM visa_renewal_application 
    WHERE student_id = ? 
    ORDER BY submission_date DESC 
    LIMIT 5
";
$renewals_stmt = $conn->prepare($renewals_query);
$renewals_stmt->bind_param("i", $student_id);
$renewals_stmt->execute();
$renewals = $renewals_stmt->get_result();

// Fetch insurance policy
$insurance_query = "
    SELECT ip.*, ips.provider_name 
    FROM insurance_policy ip
    LEFT JOIN insurance_provider ips ON ip.provider_id = ips.provider_id
    WHERE ip.student_id = ? AND ip.status = 'Active'
    LIMIT 1
";
$insurance_stmt = $conn->prepare($insurance_query);
$insurance_stmt->bind_param("i", $student_id);
$insurance_stmt->execute();
$insurance = $insurance_stmt->get_result()->fetch_assoc();

// Calculate days until visa expiry
$days_until_expiry = null;
if ($student_details['visa_expiry']) {
    $expiry_date = new DateTime($student_details['visa_expiry']);
    $today = new DateTime();
    $interval = $today->diff($expiry_date);
    $days_until_expiry = $interval->days;
    if ($interval->invert) {
        $days_until_expiry = -$days_until_expiry; // Negative if expired
    }
}

// Fetch recent activities from database - SIMPLIFIED VERSION
$activities_query = "
    SELECT 'Visa Renewal Submitted' as activity, submission_date as date 
    FROM visa_renewal_application 
    WHERE student_id = ?
    ORDER BY date DESC 
    LIMIT 5
";

$activities_stmt = $conn->prepare($activities_query);
$activities_stmt->bind_param("i", $student_id);
$activities_stmt->execute();
$db_activities = $activities_stmt->get_result();
?>

<div class="container-fluid py-4">
    <!-- Welcome Section -->
    <div class="welcome-banner bg-primary text-white rounded-3 p-4 mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h3 fw-bold mb-2">Welcome back, <?php echo htmlspecialchars($student_details['first_name']); ?>!</h1>
                <p class="mb-0">Here's your visa and academic status overview.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="bg-white text-dark rounded p-3 d-inline-block">
                    <div class="h4 mb-1">ID: <?php echo $student_id; ?></div>
                    <small class="text-muted">Student ID</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alert for visa expiry -->
    <?php if ($days_until_expiry !== null && $days_until_expiry <= 30): ?>
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Visa Alert!</strong> Your visa <?php echo $days_until_expiry < 0 ? 'has expired' : 'expires in ' . $days_until_expiry . ' days'; ?>.
        <a href="renewal.php" class="alert-link">Apply for renewal now.</a>
    </div>
    <?php endif; ?>
    
    <!-- Stats Grid -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card bg-white rounded-3 p-4 shadow-sm border">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-primary bg-opacity-10 text-primary rounded-circle p-3 me-3">
                        <i class="bi bi-person-badge fs-4"></i>
                    </div>
                    <div>
                        <h3 class="h5 mb-1 fw-bold">
                            <?php 
                            if ($student_details['visa_status'] == 'Active') {
                                echo 'Visa Active';
                            } elseif ($student_details['visa_status'] == 'Expired') {
                                echo 'Visa Expired';
                            } else {
                                echo 'No Visa';
                            }
                            ?>
                        </h3>
                        <span class="badge <?php 
                            echo ($student_details['visa_status'] == 'Active') ? 'bg-success' : 
                                 (($student_details['visa_status'] == 'Expired') ? 'bg-danger' : 'bg-secondary'); 
                        ?>">
                            <?php echo $student_details['visa_status'] ?? 'Not Found'; ?>
                        </span>
                    </div>
                </div>
                <p class="text-muted mb-0">
                    <?php 
                    if ($student_details['visa_expiry']) {
                        echo 'Expires: ' . date('M d, Y', strtotime($student_details['visa_expiry']));
                    } else {
                        echo 'No active visa';
                    }
                    ?>
                </p>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="stat-card bg-white rounded-3 p-4 shadow-sm border">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-success bg-opacity-10 text-success rounded-circle p-3 me-3">
                        <i class="bi bi-mortarboard fs-4"></i>
                    </div>
                    <div>
                        <h3 class="h5 mb-1 fw-bold"><?php echo htmlspecialchars($student_details['program_name'] ?? 'Not Assigned'); ?></h3>
                        <span class="badge bg-info"><?php echo htmlspecialchars($student_details['student_type']); ?></span>
                    </div>
                </div>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($student_details['school_name'] ?? 'No School'); ?></p>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="stat-card bg-white rounded-3 p-4 shadow-sm border">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-warning bg-opacity-10 text-warning rounded-circle p-3 me-3">
                        <i class="bi bi-shield-check fs-4"></i>
                    </div>
                    <div>
                        <h3 class="h5 mb-1 fw-bold">
                            <?php echo $insurance ? 'Insured' : 'No Insurance'; ?>
                        </h3>
                        <span class="badge <?php echo $insurance ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo $insurance ? 'Active' : 'Not Active'; ?>
                        </span>
                    </div>
                </div>
                <p class="text-muted mb-0">
                    <?php 
                    if ($insurance) {
                        echo $insurance['provider_name'] . ' (Expires: ' . date('M d, Y', strtotime($insurance['end_date'])) . ')';
                    } else {
                        echo 'Insurance not active';
                    }
                    ?>
                </p>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="stat-card bg-white rounded-3 p-4 shadow-sm border">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-info bg-opacity-10 text-info rounded-circle p-3 me-3">
                        <i class="bi bi-check-circle fs-4"></i>
                    </div>
                    <div>
                        <h3 class="h5 mb-1 fw-bold">Status</h3>
                        <span class="badge <?php echo ($student_details['status'] == 'Active') ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo htmlspecialchars($student_details['status']); ?>
                        </span>
                    </div>
                </div>
                <p class="text-muted mb-0">Student account is <?php echo strtolower($student_details['status']); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="row g-4">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Visa Details Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-card-text me-2"></i> Visa Details</h5>
                </div>
                <div class="card-body">
                    <?php if ($student_details['visa_id']): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Visa Type:</th>
                                    <td><?php echo htmlspecialchars($student_details['visa_type']); ?></td>
                                </tr>
                                <tr>
                                    <th>Visa ID:</th>
                                    <td><?php echo htmlspecialchars($student_details['visa_id']); ?></td>
                                </tr>
                                <tr>
                                    <th>Issue Date:</th>
                                    <td><?php echo date('M d, Y', strtotime($student_details['visa_issue_date'])); ?></td>
                                </tr>
                                <?php if ($nationality): ?>
                                <tr>
                                    <th>Nationality:</th>
                                    <td>
                                        <?php echo htmlspecialchars($nationality['country_name']); ?>
                                        <?php if ($nationality['region']): ?>
                                        <small class="text-muted">(<?php echo htmlspecialchars($nationality['region']); ?>)</small>
                                        <?php endif; ?>
                                        <?php if ($nationality['acquired_date']): ?>
                                        <br>
                                        <small class="text-muted">Since: <?php echo date('M d, Y', strtotime($nationality['acquired_date'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Expiry Date:</th>
                                    <td>
                                        <span class="<?php echo ($days_until_expiry <= 30) ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo date('M d, Y', strtotime($student_details['visa_expiry'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge <?php 
                                            echo ($student_details['visa_status'] == 'Active') ? 'bg-success' : 'bg-danger'; 
                                        ?>">
                                            <?php echo htmlspecialchars($student_details['visa_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Days Left:</th>
                                    <td>
                                        <?php if ($days_until_expiry > 0): ?>
                                        <span class="badge <?php 
                                            echo ($days_until_expiry <= 30) ? 'bg-warning' : 'bg-success'; 
                                        ?>">
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
                                <tr>
                                    <th>Passport No:</th>
                                    <td>
                                        <code>
                                            <?php 
                                            // Get passport number from visa if available
                                            $passport_query = "SELECT passport_no FROM student_visa WHERE student_id = ? AND status = 'Active' LIMIT 1";
                                            $passport_stmt = $conn->prepare($passport_query);
                                            $passport_stmt->bind_param("i", $student_id);
                                            $passport_stmt->execute();
                                            $passport_result = $passport_stmt->get_result();
                                            if ($passport_row = $passport_result->fetch_assoc()) {
                                                echo htmlspecialchars($passport_row['passport_no']);
                                            } else {
                                                echo 'Not available';
                                            }
                                            $passport_stmt->close();
                                            ?>
                                        </code>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-card-text fs-1 text-muted mb-3"></i>
                        <h5>No Active Visa Found</h5>
                        <p class="text-muted mb-3">Please contact ISSU office for visa registration.</p>
                        <a href="../contact.php" class="btn btn-primary">Contact ISSU</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Renewal Applications -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Recent Visa Renewals</h5>
                </div>
                <div class="card-body">
                    <?php if ($renewals->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Application ID</th>
                                    <th>Submission Date</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($renewal = $renewals->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $renewal['application_id']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($renewal['submission_date'])); ?></td>
                                    <td><?php echo $renewal['requested_months']; ?> months</td>
                                    <td>
                                        <?php 
                                        $status_class = '';
                                        switch($renewal['status']) {
                                            case 'Pending': $status_class = 'warning'; break;
                                            case 'Submitted passport to ISSU': $status_class = 'info'; break;
                                            case 'Passport collected': $status_class = 'success'; break;
                                            default: $status_class = 'secondary';
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($renewal['status']); ?>
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
                        <h5>No Renewal Applications</h5>
                        <p class="text-muted mb-3">You haven't applied for visa renewal yet.</p>
                        <a href="renewal.php" class="btn btn-primary">Apply for Renewal</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
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
                        <div class="col-6">
                            <a href="renewal.php" class="quick-action-btn d-block text-center p-3 bg-light rounded-3 text-decoration-none">
                                <i class="bi bi-arrow-clockwise fs-2 text-primary mb-2"></i>
                                <div class="fw-semibold">Visa Renewal</div>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="documents.php" class="quick-action-btn d-block text-center p-3 bg-light rounded-3 text-decoration-none">
                                <i class="bi bi-upload fs-2 text-primary mb-2"></i>
                                <div class="fw-semibold">Upload Documents</div>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="profile.php" class="quick-action-btn d-block text-center p-3 bg-light rounded-3 text-decoration-none">
                                <i class="bi bi-person-badge fs-2 text-primary mb-2"></i>
                                <div class="fw-semibold">Update Profile</div>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="insurance.php" class="quick-action-btn d-block text-center p-3 bg-light rounded-3 text-decoration-none">
                                <i class="bi bi-shield-plus fs-2 text-primary mb-2"></i>
                                <div class="fw-semibold">Insurance</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-activity me-2"></i> Recent Activity</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php if ($db_activities->num_rows > 0): ?>
                            <?php while ($activity = $db_activities->fetch_assoc()): ?>
                            <div class="list-group-item border-0 px-0 py-2">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="fw-semibold"><?php echo htmlspecialchars($activity['activity']); ?></div>
                                        <small class="text-muted"><?php echo date('M d, H:i', strtotime($activity['date'])); ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-activity fs-1 text-muted mb-3"></i>
                                <p class="text-muted">No recent activity</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Important Dates -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i> Important Dates</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php if ($student_details['visa_expiry']): ?>
                        <div class="list-group-item border-0 px-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Visa Expiry</span>
                                <span class="badge <?php echo ($days_until_expiry <= 30) ? 'bg-danger' : 'bg-warning'; ?>">
                                    <?php echo date('M d, Y', strtotime($student_details['visa_expiry'])); ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($insurance && $insurance['end_date']): ?>
                        <div class="list-group-item border-0 px-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Insurance Expiry</span>
                                <span class="badge bg-info">
                                    <?php echo date('M d, Y', strtotime($insurance['end_date'])); ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($nationality && $nationality['acquired_date']): ?>
                        <div class="list-group-item border-0 px-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Nationality Since</span>
                                <span class="badge bg-secondary">
                                    <?php echo date('M d, Y', strtotime($nationality['acquired_date'])); ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Close database connections
if (isset($stmt)) $stmt->close();
if (isset($renewals_stmt)) $renewals_stmt->close();
if (isset($insurance_stmt)) $insurance_stmt->close();
if (isset($activities_stmt)) $activities_stmt->close();
if (isset($nationality_stmt)) $nationality_stmt->close();

// Include footer
require_once 'footer.php';
?>
