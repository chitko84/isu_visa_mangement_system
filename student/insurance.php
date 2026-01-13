<?php
// Set page title
$page_title = "Insurance & Claims - ISSU";

// Include header
require_once 'header.php';

// Note: $conn and $student_id are already available from header.php

// Initialize messages
$success_message = '';
$error_message = '';
$renewal_success = false;

// Handle insurance renewal form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_renewal'])) {
    $policy_id = $_POST['policy_id'] ?? '';
    $new_end_date = $_POST['new_end_date'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    // Validate inputs
    if (empty($policy_id) || empty($new_end_date)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Validate policy belongs to student
        $check_policy = $conn->prepare("SELECT policy_id, end_date FROM insurance_policy WHERE policy_id = ? AND student_id = ?");
        $check_policy->bind_param("ii", $policy_id, $student_id);
        $check_policy->execute();
        $policy_result = $check_policy->get_result();
        
        if ($policy_result->num_rows == 0) {
            $error_message = "Invalid policy or you don't have permission to renew this policy.";
        } else {
            $policy_data = $policy_result->fetch_assoc();
            $current_end_date = $policy_data['end_date'];
            
            // Check if new date is after current end date
            if (strtotime($new_end_date) <= strtotime($current_end_date)) {
                $error_message = "New end date must be after current expiry date (" . date('M d, Y', strtotime($current_end_date)) . ").";
            } else {
                try {
                    // Use stored procedure to submit renewal
                    $renewal_query = "CALL sp_student_submit_insurance_renewal_form(?, ?, ?, ?, @renewal_id)";
                    $stmt = $conn->prepare($renewal_query);
                    $stmt->bind_param("iiss", $student_id, $policy_id, $new_end_date, $remarks);
                    
                    if ($stmt->execute()) {
                        $success_message = "Insurance renewal submitted successfully!";
                        $renewal_success = true;
                        // Get the generated renewal ID
                        $result = $conn->query("SELECT @renewal_id as renewal_id");
                        $renewal_result = $result->fetch_assoc();
                        $new_renewal_id = $renewal_result['renewal_id'] ?? 0;
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    $error_message = "Error: " . $e->getMessage();
                }
            }
        }
        $check_policy->close();
    }
}

// Handle claim deletion
if (isset($_GET['delete_claim']) && is_numeric($_GET['delete_claim'])) {
    $claim_id = $_GET['delete_claim'];
    
    // Verify claim belongs to student
    $verify_claim = $conn->prepare("
        SELECT c.claim_id 
        FROM insurance_claim c
        JOIN insurance_policy p ON c.policy_id = p.policy_id
        WHERE c.claim_id = ? AND p.student_id = ?
    ");
    $verify_claim->bind_param("ii", $claim_id, $student_id);
    $verify_claim->execute();
    $verify_claim->store_result();
    
    if ($verify_claim->num_rows > 0) {
        $delete_query = "DELETE FROM insurance_claim WHERE claim_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $claim_id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Claim deleted successfully.";
        } else {
            $error_message = "Failed to delete claim.";
        }
        $delete_stmt->close();
    } else {
        $error_message = "Claim not found or you don't have permission to delete it.";
    }
    $verify_claim->close();
}

// Fetch active insurance policy
$policy_query = "
    SELECT p.*, pr.provider_name, pr.contact_info,
           s.first_name, s.last_name, s.email
    FROM insurance_policy p
    JOIN insurance_provider pr ON p.provider_id = pr.provider_id
    JOIN student s ON p.student_id = s.student_id
    WHERE p.student_id = ? AND p.status = 'Active'
    ORDER BY p.end_date DESC
    LIMIT 1
";
$policy_stmt = $conn->prepare($policy_query);
$policy_stmt->bind_param("i", $student_id);
$policy_stmt->execute();
$active_policy = $policy_stmt->get_result()->fetch_assoc();

// If renewal was just successful, refresh the policy data
if ($renewal_success && $active_policy) {
    // Re-fetch the policy to get updated end date
    $policy_stmt->close();
    $policy_stmt = $conn->prepare($policy_query);
    $policy_stmt->bind_param("i", $student_id);
    $policy_stmt->execute();
    $active_policy = $policy_stmt->get_result()->fetch_assoc();
}

// Fetch all insurance policies (active and expired)
$all_policies_query = "
    SELECT p.*, pr.provider_name
    FROM insurance_policy p
    JOIN insurance_provider pr ON p.provider_id = pr.provider_id
    WHERE p.student_id = ?
    ORDER BY p.end_date DESC
";
$all_policies_stmt = $conn->prepare($all_policies_query);
$all_policies_stmt->bind_param("i", $student_id);
$all_policies_stmt->execute();
$all_policies = $all_policies_stmt->get_result();

// Fetch insurance claims
$claims_query = "
    SELECT c.*, p.policy_number, pr.provider_name,
           p.end_date as policy_expiry
    FROM insurance_claim c
    JOIN insurance_policy p ON c.policy_id = p.policy_id
    JOIN insurance_provider pr ON p.provider_id = pr.provider_id
    WHERE p.student_id = ?
    ORDER BY c.claim_date DESC
";
$claims_stmt = $conn->prepare($claims_query);
$claims_stmt->bind_param("i", $student_id);
$claims_stmt->execute();
$claims = $claims_stmt->get_result();

// Store claims in array for modal
$claims_data = [];
while ($claim = $claims->fetch_assoc()) {
    $claims_data[] = $claim;
}

// Reset pointer to beginning
$claims->data_seek(0);

// Fetch insurance renewal history
$renewals_query = "
    SELECT r.*, p.policy_number, pr.provider_name
    FROM insurance_renewal_record r
    JOIN insurance_policy p ON r.policy_id = p.policy_id
    JOIN insurance_provider pr ON p.provider_id = pr.provider_id
    WHERE p.student_id = ?
    ORDER BY r.renewal_date DESC
";
$renewals_stmt = $conn->prepare($renewals_query);
$renewals_stmt->bind_param("i", $student_id);
$renewals_stmt->execute();
$renewals = $renewals_stmt->get_result();

// Calculate days until insurance expiry
$days_until_expiry = null;
if ($active_policy && $active_policy['end_date']) {
    $expiry_date = new DateTime($active_policy['end_date']);
    $today = new DateTime();
    $interval = $today->diff($expiry_date);
    $days_until_expiry = $interval->days;
    if ($interval->invert) {
        $days_until_expiry = -$days_until_expiry; // Negative if expired
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="../bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="student_style.css">
    <style>
        /* Adjust main content to move it closer to sidebar */
        .student-content {
            margin-left: 50px !important;
            transition: all 0.3s ease;
        }
        
        @media (min-width: 992px) {
            .student-content {
                margin-left: 250px !important;
                padding-left: 15px !important;
            }
        }
        
        @media (max-width: 991.98px) {
            .student-content {
                margin-left: 0 !important;
                padding-top: 60px;
            }
        }
        
        /* Custom modal styles */
        .modal-danger .modal-header {
            background-color: #dc3545;
            color: white;
        }
        
        .modal-danger .modal-body {
            padding: 1.5rem;
        }
        
        .delete-confirmation-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        
        /* Improved table alignment */
        .table-responsive {
            margin: 0;
            padding: 0;
        }
        
        /* Adjust content container */
        .container-fluid {
            padding-left: 10px !important;
            padding-right: 10px !important;
            max-width: 100% !important;
        }
        
        @media (min-width: 1400px) {
            .container-fluid {
                padding-left: 15px !important;
                padding-right: 15px !important;
                max-width: 1400px !important;
            }
        }
        
        /* Card improvements */
        .card {
            margin-bottom: 1.5rem;
        }
        
        /* Better button spacing - make buttons more clickable */
        .btn-group-sm > .btn {
            padding: 0.4rem 0.6rem !important;
            font-size: 0.875rem;
            min-width: 40px;
        }
        
        /* Make action buttons more prominent and clickable */
        .action-buttons .btn {
            margin: 2px;
            padding: 0.4rem 0.6rem !important;
            min-width: 38px;
            height: 38px;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            cursor: pointer !important;
            border: 1px solid transparent !important;
        }
        
        .action-buttons .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .action-buttons .btn-outline-info:hover {
            background-color: #0dcaf0;
            border-color: #0dcaf0 !important;
            color: white !important;
        }
        
        .action-buttons .btn-outline-danger:hover {
            background-color: #dc3545;
            border-color: #dc3545 !important;
            color: white !important;
        }
        
        /* Status badges */
        .badge {
            font-size: 0.8em;
            padding: 0.4em 0.8em;
        }
        
        /* Info cards */
        .info-card {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 0.5rem;
            background: #f8f9fa;
            margin-bottom: 1rem;
        }
        
        .info-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
        }
        
        .info-content h6 {
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        
        /* Fix modal z-index issues */
        .modal-backdrop {
            z-index: 1050 !important;
        }
        
        .modal {
            z-index: 1060 !important;
        }
        
        /* Ensure table cells have proper spacing */
        .table td, .table th {
            vertical-align: middle !important;
        }
        
        /* Make delete button more prominent */
        .delete-claim-btn {
            position: relative !important;
            z-index: 1 !important;
        }
        
        /* Fix button group issues */
        .btn-group {
            display: flex !important;
            gap: 5px;
        }
        
        .btn-group .btn {
            position: relative !important;
            z-index: 1 !important;
        }
        
        /* Ensure buttons are clickable */
        button[data-bs-toggle="modal"],
        button.delete-claim-btn {
            cursor: pointer !important;
            pointer-events: auto !important;
        }
        
        /* Fix for button z-index stacking */
        .action-buttons {
            position: relative;
            z-index: 10;
        }
    </style>
</head>
<body>
    <!-- Mobile Top Bar -->
    <header class="student-topbar">
        <div class="topbar-content">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Menu">
                <i class="bi bi-list" id="menuToggleIcon"></i>
            </button>
            <div class="user-info">
                <div class="user-avatar">
                    <?php 
                    $initials = substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1);
                    echo strtoupper($initials); 
                    ?>
                </div>
                <div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                    <small class="text-muted">Student</small>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <aside class="student-sidebar" id="sidebar">
        <div class="sidebar-header">
            <!-- Close Button -->
            <button class="sidebar-close" id="sidebarClose" aria-label="Close Menu">
                <i class="bi bi-x"></i>
            </button>
            <div class="logo">
                <i class="bi bi-passport-fill"></i>
            </div>
            <h3>ISU Portal</h3>
            <p>Student Dashboard</p>
            <div class="mt-2">
                <small class="opacity-75">ID: <?php echo $student_id; ?></small>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="bi bi-person-circle"></i>
                        <span>My Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="visa.php" class="nav-link">
                        <i class="bi bi-passport"></i>
                        <span>My Student Pass</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="renewal.php" class="nav-link">
                        <i class="bi bi-arrow-clockwise"></i>
                        <span>Pass Renewal</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="insurance.php" class="nav-link active">
                        <i class="bi bi-shield-check"></i>
                        <span>Insurance & Claims</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="documents.php" class="nav-link">
                        <i class="bi bi-folder"></i>
                        <span>My Documents</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="exit.php" class="nav-link">
                        <i class="bi bi-door-open"></i>
                        <span>Exit & Clearance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="notifications.php" class="nav-link">
                        <i class="bi bi-bell"></i>
                        <span>Notifications</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link">
                        <i class="bi bi-gear"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a href="../logout.php" class="nav-link">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>
    
    <!-- Main Content Area -->
    <main class="student-content" id="mainContent">
        <div class="container-fluid py-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1"><i class="bi bi-shield-check me-2"></i>Insurance & Claims</h1>
                    <p class="text-muted mb-0">Manage your health insurance policies and claims</p>
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
            
            <!-- Alert for insurance expiry -->
            <?php if ($days_until_expiry !== null && $days_until_expiry <= 30): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Insurance Alert!</strong> Your insurance <?php echo $days_until_expiry < 0 ? 'has expired' : 'expires in ' . $days_until_expiry . ' days'; ?>.
                <?php if ($active_policy): ?>
                <button type="button" class="btn btn-sm btn-warning ms-2" data-bs-toggle="modal" data-bs-target="#renewalModal">
                    Renew Now
                </button>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Active Insurance Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-shield-check me-2"></i> Current Insurance Policy</h5>
                    <?php if ($active_policy): ?>
                    <span class="badge bg-<?php echo $active_policy['status'] == 'Active' ? 'success' : 'danger'; ?>">
                        <?php echo $active_policy['status']; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($active_policy): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Policy Number:</th>
                                    <td>
                                        <strong><?php echo htmlspecialchars($active_policy['policy_number']); ?></strong>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Provider:</th>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($active_policy['provider_name']); ?></div>
                                        <?php if ($active_policy['contact_info']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($active_policy['contact_info']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Coverage Type:</th>
                                    <td>
                                        <?php echo htmlspecialchars($active_policy['coverage_type'] ?? 'Comprehensive'); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Student Name:</th>
                                    <td><?php echo htmlspecialchars($active_policy['first_name'] . ' ' . $active_policy['last_name']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Start Date:</th>
                                    <td><?php echo date('M d, Y', strtotime($active_policy['start_date'])); ?></td>
                                </tr>
                                <tr>
                                    <th>End Date:</th>
                                    <td>
                                        <span class="<?php echo ($days_until_expiry !== null && $days_until_expiry <= 30) ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo date('M d, Y', strtotime($active_policy['end_date'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge bg-<?php echo $active_policy['status'] == 'Active' ? 'success' : 'danger'; ?>">
                                            <?php echo $active_policy['status']; ?>
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
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="process_claim.php" class="btn btn-primary me-2">
                                    <i class="bi bi-plus-circle me-2"></i>Submit New Claim
                                </a>
                                <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#renewalModal">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Renew Insurance
                                </button>
                                <button class="btn btn-outline-secondary" onclick="printInsuranceDetails()">
                                    <i class="bi bi-printer me-2"></i>Print Details
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-shield-slash fs-1 text-muted mb-3"></i>
                        <h5>No Active Insurance Policy</h5>
                        <p class="text-muted mb-3">You don't have an active health insurance policy.</p>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Health insurance is mandatory for all international students. Please contact ISSU office.
                        </div>
                        <a href="../contact.php" class="btn btn-primary">
                            <i class="bi bi-telephone me-2"></i>Contact ISSU Office
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Insurance Claims -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-medical me-2"></i> Insurance Claims</h5>
                    <a href="process_claim.php" class="btn btn-sm btn-light">
                        <i class="bi bi-plus-circle me-1"></i>New Claim
                    </a>
                </div>
                <div class="card-body">
                    <?php if (count($claims_data) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Claim ID</th>
                                    <th>Policy</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($claims_data as $claim): ?>
                                <?php
                                $status_color = '';
                                switch($claim['claim_status']) {
                                    case 'Pending': $status_color = 'warning'; break;
                                    case 'Approved': $status_color = 'success'; break;
                                    case 'Rejected': $status_color = 'danger'; break;
                                    default: $status_color = 'secondary';
                                }
                                ?>
                                <tr id="claim-row-<?php echo $claim['claim_id']; ?>">
                                    <td>#<?php echo $claim['claim_id']; ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($claim['policy_number']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($claim['provider_name']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($claim['claim_date'])); ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold">RM <?php echo number_format($claim['claim_amount'], 2); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_color; ?>">
                                            <?php echo htmlspecialchars($claim['claim_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-outline-info view-claim-details" 
                                                    data-claim-id="<?php echo $claim['claim_id']; ?>"
                                                    data-policy="<?php echo htmlspecialchars($claim['policy_number']); ?>"
                                                    data-provider="<?php echo htmlspecialchars($claim['provider_name']); ?>"
                                                    data-date="<?php echo date('M d, Y', strtotime($claim['claim_date'])); ?>"
                                                    data-amount="<?php echo number_format($claim['claim_amount'], 2); ?>"
                                                    data-status="<?php echo htmlspecialchars($claim['claim_status']); ?>"
                                                    data-expiry="<?php echo date('M d, Y', strtotime($claim['policy_expiry'])); ?>"
                                                    title="View Claim Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php if ($claim['claim_status'] == 'Pending'): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-danger delete-claim-btn"
                                                    data-claim-id="<?php echo $claim['claim_id']; ?>"
                                                    data-claim-number="#<?php echo $claim['claim_id']; ?>"
                                                    data-policy="<?php echo htmlspecialchars($claim['policy_number']); ?>"
                                                    title="Delete Claim">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                        <h5>No Insurance Claims</h5>
                        <p class="text-muted mb-3">You haven't submitted any insurance claims yet.</p>
                        <a href="process_claim.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Submit Your First Claim
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Insurance History -->
            <div class="row">
                <div class="col-lg-6">
                    <!-- Policy History -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Policy History</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($all_policies->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($policy = $all_policies->fetch_assoc()): ?>
                                <?php
                                $policy_expiry = new DateTime($policy['end_date']);
                                $today = new DateTime();
                                $is_expired = $policy_expiry < $today;
                                ?>
                                <div class="list-group-item border-0 px-0 py-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($policy['policy_number']); ?></div>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($policy['provider_name']); ?> | 
                                                <?php echo date('M d, Y', strtotime($policy['start_date'])); ?> - 
                                                <?php echo date('M d, Y', strtotime($policy['end_date'])); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $policy['status'] == 'Active' ? 'success' : 'danger'; ?>">
                                                <?php echo $policy['status']; ?>
                                            </span>
                                            <?php if ($policy['coverage_type']): ?>
                                            <div class="text-muted small"><?php echo $policy['coverage_type']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                                <p class="text-muted">No insurance policies found</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <!-- Renewal History -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-arrow-clockwise me-2"></i> Renewal History</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($renewals->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($renewal = $renewals->fetch_assoc()): ?>
                                <div class="list-group-item border-0 px-0 py-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-semibold">Policy: <?php echo htmlspecialchars($renewal['policy_number']); ?></div>
                                            <small class="text-muted">
                                                Renewed: <?php echo date('M d, Y', strtotime($renewal['renewal_date'])); ?> | 
                                                New expiry: <?php echo date('M d, Y', strtotime($renewal['new_end_date'])); ?>
                                            </small>
                                            <?php if ($renewal['remarks']): ?>
                                            <div class="mt-1">
                                                <small class="text-muted"><?php echo htmlspecialchars($renewal['remarks']); ?></small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span class="badge bg-info">Renewal #<?php echo $renewal['renewal_id']; ?></span>
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
                </div>
            </div>
            
            <!-- Insurance Information -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> Insurance Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-card mb-3">
                                <div class="info-icon bg-primary text-white">
                                    <i class="bi bi-heart-pulse"></i>
                                </div>
                                <div class="info-content">
                                    <h6>Medical Coverage</h6>
                                    <p class="text-muted mb-0">Hospitalization, outpatient, emergency</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-card mb-3">
                                <div class="info-icon bg-success text-white">
                                    <i class="bi bi-cash-coin"></i>
                                </div>
                                <div class="info-content">
                                    <h6>Claim Process</h6>
                                    <p class="text-muted mb-0">Submit within 30 days of treatment</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-card mb-3">
                                <div class="info-icon bg-warning text-dark">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <div class="info-content">
                                    <h6>Renewal Period</h6>
                                    <p class="text-muted mb-0">Renew 30 days before expiry</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <div class="alert alert-light">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> Health insurance is mandatory for all international students. 
                            Claims must be submitted with original receipts and medical reports.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Renewal Modal -->
<div class="modal fade" id="renewalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="renewalForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-arrow-clockwise me-2"></i> Renew Insurance Policy</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($active_policy): ?>
                    <input type="hidden" name="policy_id" value="<?php echo $active_policy['policy_id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Current Policy</label>
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($active_policy['policy_number'] . ' - ' . $active_policy['provider_name']); ?>" 
                               readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Current Expiry Date</label>
                            <input type="text" class="form-control" id="currentExpiryDisplay"
                                   value="<?php echo date('M d, Y', strtotime($active_policy['end_date'])); ?>" 
                                   readonly>
                            <input type="hidden" id="currentExpiry" value="<?php echo $active_policy['end_date']; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">New End Date *</label>
                            <input type="date" class="form-control" name="new_end_date" id="newEndDate" required
                                   min="<?php echo date('Y-m-d', strtotime($active_policy['end_date'] . ' +1 day')); ?>">
                            <div class="form-text">Must be after <?php echo date('M d, Y', strtotime($active_policy['end_date'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Renewal Duration</label>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-primary" onclick="setRenewalDuration(6)">6 Months</button>
                            <button type="button" class="btn btn-outline-primary" onclick="setRenewalDuration(12)">12 Months</button>
                            <button type="button" class="btn btn-outline-primary" onclick="setRenewalDuration(24)">24 Months</button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Remarks (Optional)</label>
                        <textarea class="form-control" name="remarks" rows="3" placeholder="Add any remarks about this renewal..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Standard renewal period is 12 months. Premium will be calculated based on the new duration.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        You don't have an active policy to renew.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_renewal" class="btn btn-primary" 
                            <?php echo !$active_policy ? 'disabled' : ''; ?>>
                        <i class="bi bi-check-circle me-2"></i>Submit Renewal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Claim Details Modal (Single modal for all claims) -->
<div class="modal fade" id="claimDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-file-earmark-medical me-2"></i> Claim Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm" id="claimDetailsTable">
                    <!-- Dynamic content will be inserted here by JavaScript -->
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" class="btn btn-primary" id="editClaimBtn" style="display: none;">
                    <i class="bi bi-pencil me-2"></i>Edit Claim
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteClaimModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i> Delete Claim</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div class="delete-confirmation-icon">
                    <i class="bi bi-trash-fill"></i>
                </div>
                <h4 class="mb-3">Are you sure?</h4>
                <p class="text-muted">You are about to delete the following claim:</p>
                
                <div class="alert alert-warning text-start">
                    <div class="row">
                        <div class="col-6"><strong>Claim ID:</strong></div>
                        <div class="col-6" id="deleteClaimId"></div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6"><strong>Policy:</strong></div>
                        <div class="col-6" id="deletePolicyNumber"></div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6"><strong>Status:</strong></div>
                        <div class="col-6"><span class="badge bg-warning">Pending</span></div>
                    </div>
                </div>
                
                <p class="text-danger">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    This action cannot be undone. The claim will be permanently deleted.
                </p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </button>
                <a href="#" class="btn btn-danger" id="confirmDeleteClaimBtn">
                    <i class="bi bi-trash me-2"></i>Delete Claim
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Main Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Enhanced Sidebar Management
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const menuToggleIcon = document.getElementById('menuToggleIcon');
    const body = document.body;
    const mainContent = document.getElementById('mainContent');

    // Function to open sidebar
    function openSidebar() {
        sidebar.classList.add('active');
        sidebarOverlay.classList.add('active');
        body.style.overflow = 'hidden';
        
        // Update toggle icon
        if (menuToggleIcon) {
            menuToggleIcon.className = 'bi bi-x';
        }
        
        // Save state
        localStorage.setItem('sidebarState', 'open');
        document.addEventListener('keydown', handleEscapeKey);
    }

    // Function to close sidebar
    function closeSidebar() {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        body.style.overflow = '';
        
        // Update toggle icon
        if (menuToggleIcon) {
            menuToggleIcon.className = 'bi bi-list';
        }
        
        // Save state
        localStorage.setItem('sidebarState', 'closed');
        document.removeEventListener('keydown', handleEscapeKey);
    }

    // Function to toggle sidebar
    function toggleSidebar() {
        if (sidebar.classList.contains('active')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    // Function to handle escape key
    function handleEscapeKey(event) {
        if (event.key === 'Escape' && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    }

    // Event Listeners
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }

    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    // Close sidebar when clicking a link on mobile
    document.querySelectorAll('.sidebar-menu .nav-link').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 992 && sidebar.classList.contains('active')) {
                closeSidebar();
            }
        });
    });

    // Restore sidebar state from localStorage
    document.addEventListener('DOMContentLoaded', function() {
        const savedState = localStorage.getItem('sidebarState');
        
        // Only restore open state on desktop
        if (window.innerWidth > 992 && savedState === 'open') {
            sidebar.classList.add('active');
        }
        
        // Highlight active page in sidebar
        const currentPage = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.sidebar-menu .nav-link');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage || (currentPage === '' && href === 'dashboard.php')) {
                link.classList.add('active');
            }
        });
    });

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 992) {
                // On desktop, remove overlay and restore body scroll
                sidebarOverlay.classList.remove('active');
                body.style.overflow = '';
                
                // Update toggle icon
                if (menuToggleIcon) {
                    menuToggleIcon.className = 'bi bi-list';
                }
            } else {
                // On mobile, close sidebar if open
                if (sidebar.classList.contains('active')) {
                    closeSidebar();
                }
            }
        }, 250);
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Insurance page specific functions
    function printInsuranceDetails() {
        const printContent = `
            <html>
            <head>
                <title>Insurance Details - Student ID: <?php echo $student_id; ?></title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    h1 { color: #0e2a47; border-bottom: 2px solid #0e2a47; padding-bottom: 10px; }
                    .section { margin-bottom: 30px; }
                    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .badge { border: 1px solid #000; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
                    .header { display: flex; justify-content: space-between; margin-bottom: 20px; }
                    .print-date { text-align: right; font-size: 0.9em; color: #666; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Insurance Details</h1>
                    <div class="print-date">Printed: ${new Date().toLocaleDateString()}</div>
                </div>
                
                <div class="section">
                    <h3>Student Information</h3>
                    <p><strong>Student ID:</strong> <?php echo $student_id; ?></p>
                    <p><strong>Name:</strong> <?php echo $active_policy ? htmlspecialchars($active_policy['first_name'] . ' ' . $active_policy['last_name']) : 'N/A'; ?></p>
                </div>
                
                <?php if ($active_policy): ?>
                <div class="section">
                    <h3>Current Insurance Policy</h3>
                    <table>
                        <tr><th>Policy Number</th><td><?php echo htmlspecialchars($active_policy['policy_number']); ?></td></tr>
                        <tr><th>Provider</th><td><?php echo htmlspecialchars($active_policy['provider_name']); ?></td></tr>
                        <tr><th>Coverage Type</th><td><?php echo htmlspecialchars($active_policy['coverage_type'] ?? 'Comprehensive'); ?></td></tr>
                        <tr><th>Start Date</th><td><?php echo date('M d, Y', strtotime($active_policy['start_date'])); ?></td></tr>
                        <tr><th>End Date</th><td><?php echo date('M d, Y', strtotime($active_policy['end_date'])); ?></td></tr>
                        <tr><th>Status</th>
                            <td>
                                <span class="badge"><?php echo $active_policy['status']; ?></span>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>
                
                <?php if (count($claims_data) > 0): ?>
                <div class="section">
                    <h3>Insurance Claims</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Claim ID</th>
                                <th>Policy</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($claims_data as $claim): ?>
                            <tr>
                                <td>#<?php echo $claim['claim_id']; ?></td>
                                <td><?php echo htmlspecialchars($claim['policy_number']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($claim['claim_date'])); ?></td>
                                <td>RM <?php echo number_format($claim['claim_amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($claim['claim_status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </body>
            </html>
        `;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(printContent);
        printWindow.document.close();
        printWindow.print();
    }

    // Set renewal duration buttons
    function setRenewalDuration(months) {
        const currentExpiry = document.getElementById('currentExpiry').value;
        if (currentExpiry) {
            const expiryDate = new Date(currentExpiry);
            expiryDate.setMonth(expiryDate.getMonth() + months);
            const nextDate = expiryDate.toISOString().split('T')[0];
            document.getElementById('newEndDate').value = nextDate;
        }
    }

    // Handle claim details modal
    document.addEventListener('DOMContentLoaded', function() {
        // Claim details click handler
        document.querySelectorAll('.view-claim-details').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                const claimId = this.getAttribute('data-claim-id');
                const policy = this.getAttribute('data-policy');
                const provider = this.getAttribute('data-provider');
                const date = this.getAttribute('data-date');
                const amount = this.getAttribute('data-amount');
                const status = this.getAttribute('data-status');
                const expiry = this.getAttribute('data-expiry');
                
                // Set status color
                let statusColor = 'secondary';
                if (status === 'Pending') statusColor = 'warning';
                if (status === 'Approved') statusColor = 'success';
                if (status === 'Rejected') statusColor = 'danger';
                
                // Set modal content
                const table = document.getElementById('claimDetailsTable');
                table.innerHTML = `
                    <tr><th width="40%">Claim ID:</th><td>#${claimId}</td></tr>
                    <tr><th>Policy:</th><td>${policy}</td></tr>
                    <tr><th>Provider:</th><td>${provider}</td></tr>
                    <tr><th>Date:</th><td>${date}</td></tr>
                    <tr><th>Amount:</th><td>RM ${amount}</td></tr>
                    <tr><th>Status:</th>
                        <td>
                            <span class="badge bg-${statusColor}">
                                ${status}
                            </span>
                        </td>
                    </tr>
                    <tr><th>Policy Expiry:</th><td>${expiry}</td></tr>
                `;
                
                // Show/hide edit button
                const editBtn = document.getElementById('editClaimBtn');
                if (status === 'Pending') {
                    editBtn.style.display = 'inline-block';
                    editBtn.href = `process_claim.php?edit=${claimId}`;
                } else {
                    editBtn.style.display = 'none';
                }
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('claimDetailsModal'));
                modal.show();
            });
        });
        
        // Delete claim button handler
        document.querySelectorAll('.delete-claim-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                
                const claimId = this.getAttribute('data-claim-id');
                const claimNumber = this.getAttribute('data-claim-number');
                const policyNumber = this.getAttribute('data-policy');
                
                // Set modal content
                document.getElementById('deleteClaimId').textContent = claimNumber;
                document.getElementById('deletePolicyNumber').textContent = policyNumber;
                
                // Set delete URL
                const deleteUrl = `insurance.php?delete_claim=${claimId}`;
                document.getElementById('confirmDeleteClaimBtn').href = deleteUrl;
                
                // Add click handler to delete button
                document.getElementById('confirmDeleteClaimBtn').onclick = function(e) {
                    e.preventDefault();
                    window.location.href = deleteUrl;
                };
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('deleteClaimModal'));
                modal.show();
            });
        });
        
        // Auto-open renewal modal if insurance is expiring soon
        <?php if ($days_until_expiry !== null && $days_until_expiry <= 30 && $active_policy && !isset($_POST['submit_renewal'])): ?>
        setTimeout(() => {
            const renewalModal = new bootstrap.Modal(document.getElementById('renewalModal'));
            renewalModal.show();
        }, 1000);
        <?php endif; ?>
        
        // Set default renewal date to 12 months from now
        const newEndDateInput = document.getElementById('newEndDate');
        if (newEndDateInput) {
            const currentExpiry = document.getElementById('currentExpiry').value;
            if (currentExpiry) {
                const expiryDate = new Date(currentExpiry);
                expiryDate.setFullYear(expiryDate.getFullYear() + 1);
                const nextYear = expiryDate.toISOString().split('T')[0];
                newEndDateInput.value = nextYear;
            }
        }
        
        // Debug: Log all delete buttons to console
        console.log('Delete buttons found:', document.querySelectorAll('.delete-claim-btn').length);
        document.querySelectorAll('.delete-claim-btn').forEach(btn => {
            console.log('Delete button:', btn);
            btn.style.pointerEvents = 'auto';
            btn.style.zIndex = '1000';
        });
    });
    
    // Add global click handler for debugging
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-claim-btn')) {
            console.log('Delete button clicked!');
        }
    });
</script>
</body>
</html>

<?php
// Close database connections
if (isset($policy_stmt)) $policy_stmt->close();
if (isset($all_policies_stmt)) $all_policies_stmt->close();
if (isset($claims_stmt)) $claims_stmt->close();
if (isset($renewals_stmt)) $renewals_stmt->close();
?>
