<?php
// Set page title
$page_title = "Claim Submitted Successfully - ISSU";

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

// Check if claim details exist in session
if (!isset($_SESSION['claim_details'])) {
    header("Location: insurance.php");
    exit();
}

// Get claim details from session
$claim_details = $_SESSION['claim_details'];
$claim_id = $claim_details['claim_id'] ?? 0;
$policy_number = $claim_details['policy_number'] ?? '';
$claim_amount = $claim_details['claim_amount'] ?? 0;
$treatment_date = $claim_details['treatment_date'] ?? '';
$hospital_name = $claim_details['hospital_name'] ?? '';

// Clear session data after displaying
unset($_SESSION['claim_details']);

// Include database
require_once '../includes/db.php';

// Get student ID from session
$student_id = $_SESSION['user_id'];

// Fetch student details
$student_query = "SELECT first_name, last_name, email FROM student WHERE student_id = ?";
$student_stmt = $conn->prepare($student_query);
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="../bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-blue: #0e2a47;
            --secondary-blue: #1a5276;
            --dark-blue: #0b1f33;
            --success-green: #28a745;
            --light-green: #d4edda;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .success-header {
            background: linear-gradient(135deg, var(--success-green) 0%, #1e7e34 100%);
            color: white;
            padding: 3rem 0;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .success-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .success-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
            border-top: 5px solid var(--success-green);
        }
        
        .success-card-header {
            background: white;
            padding: 2rem 2rem 0;
            text-align: center;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--light-green);
            color: var(--success-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
            border: 3px solid var(--success-green);
        }
        
        .success-card-body {
            padding: 2rem;
        }
        
        .claim-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .summary-label {
            font-weight: 600;
            color: #495057;
        }
        
        .summary-value {
            color: #212529;
            font-weight: 500;
        }
        
        .next-steps {
            background: #e8f4fd;
            border-left: 4px solid var(--primary-blue);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 4px;
        }
        
        .step-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .step-item:last-child {
            margin-bottom: 0;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--primary-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        
        .btn-success-custom {
            background: var(--success-green);
            border-color: var(--success-green);
            color: white;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        
        .btn-success-custom:hover {
            background: #218838;
            border-color: #1e7e34;
            color: white;
        }
        
        .contact-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        
        .contact-item:last-child {
            margin-bottom: 0;
        }
        
        .contact-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: var(--primary-blue);
        }
        
        .timeline-estimate {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 2rem 0;
        }
        
        .timeline-estimate::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        
        .timeline-point {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .timeline-dot {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 3px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin: 0 auto 0.5rem;
            color: #6c757d;
        }
        
        .timeline-point.active .timeline-dot {
            background: var(--success-green);
            border-color: var(--success-green);
            color: white;
        }
        
        .timeline-label {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .timeline-point.active .timeline-label {
            color: var(--success-green);
            font-weight: 600;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .success-card {
                box-shadow: none;
                border: 1px solid #dee2e6;
            }
            
            .action-buttons {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Success Header -->
    <div class="success-header">
        <div class="success-container">
            <h1 class="h2 mb-3">Claim Submitted Successfully!</h1>
            <p class="lead mb-0">Your insurance claim has been received and is being processed.</p>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="success-container flex-grow-1">
        <!-- Success Card -->
        <div class="success-card">
            <div class="success-card-header">
                <div class="success-icon">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h2 class="h4 mb-2">Thank You for Your Submission</h2>
                <p class="text-muted">Claim ID: <strong>#<?php echo $claim_id; ?></strong></p>
            </div>
            
            <div class="success-card-body">
                <!-- Claim Summary -->
                <div class="claim-summary">
                    <h4 class="h5 mb-3">Claim Summary</h4>
                    
                    <div class="summary-item">
                        <span class="summary-label">Claim ID</span>
                        <span class="summary-value">#<?php echo $claim_id; ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Submission Date</span>
                        <span class="summary-value"><?php echo date('F d, Y'); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Policy Number</span>
                        <span class="summary-value"><?php echo htmlspecialchars($policy_number); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Claim Amount</span>
                        <span class="summary-value">RM <?php echo number_format($claim_amount, 2); ?></span>
                    </div>
                    
                    <?php if ($treatment_date): ?>
                    <div class="summary-item">
                        <span class="summary-label">Treatment Date</span>
                        <span class="summary-value"><?php echo date('F d, Y', strtotime($treatment_date)); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($hospital_name): ?>
                    <div class="summary-item">
                        <span class="summary-label">Hospital/Clinic</span>
                        <span class="summary-value"><?php echo htmlspecialchars($hospital_name); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="summary-item">
                        <span class="summary-label">Student Name</span>
                        <span class="summary-value"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Student ID</span>
                        <span class="summary-value"><?php echo $student_id; ?></span>
                    </div>
                </div>
                
                <!-- Processing Timeline -->
                <div class="mb-4">
                    <h5 class="h5 mb-3">Estimated Processing Timeline</h5>
                    <div class="timeline-estimate">
                        <div class="timeline-point active">
                            <div class="timeline-dot">1</div>
                            <div class="timeline-label">Submitted</div>
                        </div>
                        <div class="timeline-point">
                            <div class="timeline-dot">2</div>
                            <div class="timeline-label">Review (1-3 days)</div>
                        </div>
                        <div class="timeline-point">
                            <div class="timeline-dot">3</div>
                            <div class="timeline-label">Processing (3-7 days)</div>
                        </div>
                        <div class="timeline-point">
                            <div class="timeline-dot">4</div>
                            <div class="timeline-label">Completed (7-14 days)</div>
                        </div>
                    </div>
                </div>
                
                <!-- Next Steps -->
                <div class="next-steps">
                    <h5 class="h5 mb-3">What Happens Next?</h5>
                    
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div>
                            <strong>Document Verification</strong>
                            <p class="mb-0 text-muted">Our team will review your claim and verify the submitted documents.</p>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div>
                            <strong>Processing & Approval</strong>
                            <p class="mb-0 text-muted">Your claim will be processed and approved based on policy terms.</p>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div>
                            <strong>Payment Issuance</strong>
                            <p class="mb-0 text-muted">Once approved, payment will be issued to your registered bank account.</p>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number">4</div>
                        <div>
                            <strong>Notification</strong>
                            <p class="mb-0 text-muted">You will receive email notifications at each stage of the process.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Important Notes -->
                <div class="contact-info">
                    <h6 class="h6 mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Important Information</h6>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div>
                            <strong>Processing Time:</strong> 7-14 working days
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div>
                            <strong>Original Documents:</strong> Keep all original receipts for verification
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div>
                            <strong>Email Updates:</strong> Check <?php echo htmlspecialchars($student['email']); ?> for updates
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="bi bi-telephone"></i>
                        </div>
                        <div>
                            <strong>Need Help?</strong> Contact ISSU Support: support@issu.edu
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons no-print">
                    <a href="insurance.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i> Back to Insurance
                    </a>
                    
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-house me-2"></i> Go to Dashboard
                    </a>
                    
                    <button onclick="window.print()" class="btn btn-outline-success">
                        <i class="bi bi-printer me-2"></i> Print Confirmation
                    </button>
                    
                    <a href="process_claim.php" class="btn btn-success-custom">
                        <i class="bi bi-plus-circle me-2"></i> Submit Another Claim
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Additional Information -->
        <div class="success-card no-print">
            <div class="success-card-body">
                <h5 class="h5 mb-3">Track Your Claim Status</h5>
                <p class="text-muted mb-3">You can track the status of your claim at any time through the following methods:</p>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="text-center p-3 border rounded">
                            <i class="bi bi-laptop fs-1 text-primary mb-3"></i>
                            <h6 class="mb-2">Online Portal</h6>
                            <p class="text-muted small mb-0">Check status in your Insurance & Claims section</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="text-center p-3 border rounded">
                            <i class="bi bi-envelope fs-1 text-success mb-3"></i>
                            <h6 class="mb-2">Email Notifications</h6>
                            <p class="text-muted small mb-0">Receive updates at <?php echo htmlspecialchars($student['email']); ?></p>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="text-center p-3 border rounded">
                            <i class="bi bi-telephone fs-1 text-info mb-3"></i>
                            <h6 class="mb-2">Phone Support</h6>
                            <p class="text-muted small mb-0">Call ISSU Support: +603-1234 5678</p>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-light mt-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> For urgent inquiries, please quote your Claim ID: <strong>#<?php echo $claim_id; ?></strong>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-4 no-print">
        <div class="success-container text-center">
            <p class="mb-0">ISSU Student Portal &copy; <?php echo date('Y'); ?> | Need help? Contact: support@issu.edu</p>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
    // Auto-print option (optional)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('print')) {
        window.print();
    }
    
    // Countdown timer for automatic redirect
    let countdown = 30;
    const countdownElement = document.createElement('div');
    countdownElement.className = 'alert alert-info text-center no-print';
    countdownElement.innerHTML = `
        <i class="bi bi-clock me-2"></i>
        You will be redirected to the insurance page in <span id="countdown">${countdown}</span> seconds.
        <a href="insurance.php" class="alert-link ms-2">Go now</a>
    `;
    
    // Insert countdown after action buttons
    const actionButtons = document.querySelector('.action-buttons');
    if (actionButtons) {
        actionButtons.parentNode.insertBefore(countdownElement, actionButtons.nextSibling);
        
        const countdownInterval = setInterval(() => {
            countdown--;
            document.getElementById('countdown').textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                window.location.href = 'insurance.php';
            }
        }, 1000);
    }
    
    // Print-specific styling
    document.addEventListener('DOMContentLoaded', function() {
        // Add print button event
        document.querySelectorAll('button[onclick*="print"]').forEach(btn => {
            btn.addEventListener('click', function() {
                // Add print-specific class to body
                document.body.classList.add('printing');
                
                // Wait a bit before printing to ensure styles are applied
                setTimeout(() => {
                    window.print();
                    // Remove class after printing
                    setTimeout(() => {
                        document.body.classList.remove('printing');
                    }, 1000);
                }, 500);
            });
        });
        
        // Save claim ID to local storage for reference
        localStorage.setItem('lastClaimId', '<?php echo $claim_id; ?>');
        localStorage.setItem('lastClaimDate', '<?php echo date('Y-m-d'); ?>');
    });
    
    // Before print event
    window.addEventListener('beforeprint', function() {
        // Add print header
        const printHeader = document.createElement('div');
        printHeader.className = 'print-header';
        printHeader.innerHTML = `
            <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px;">
                <h2 style="color: #0e2a47; margin: 0;">ISSU Insurance Claim Confirmation</h2>
                <p style="color: #666; margin: 5px 0 0;">Generated: ${new Date().toLocaleDateString()}</p>
            </div>
        `;
        document.body.insertBefore(printHeader, document.body.firstChild);
    });
    
    // After print event
    window.addEventListener('afterprint', function() {
        const printHeader = document.querySelector('.print-header');
        if (printHeader) {
            printHeader.remove();
        }
    });
    </script>
    
    <!-- Print Styles -->
    <style>
    @media print {
        body {
            background: white !important;
            font-size: 12pt;
        }
        
        .success-header {
            background: #f8f9fa !important;
            color: #000 !important;
            padding: 1rem 0 !important;
            margin-bottom: 1rem !important;
        }
        
        .success-icon {
            background: #f8f9fa !important;
            color: #000 !important;
            border-color: #000 !important;
        }
        
        .success-card {
            box-shadow: none !important;
            border: 1px solid #000 !important;
            margin-bottom: 1rem !important;
        }
        
        .claim-summary, .next-steps, .contact-info {
            background: #f8f9fa !important;
            border: 1px solid #dee2e6 !important;
        }
        
        .summary-item {
            border-bottom: 1px solid #dee2e6 !important;
        }
        
        .timeline-estimate::before {
            background: #000 !important;
        }
        
        .timeline-dot {
            background: white !important;
            border-color: #000 !important;
            color: #000 !important;
        }
        
        .timeline-point.active .timeline-dot {
            background: #000 !important;
            color: white !important;
        }
        
        .no-print {
            display: none !important;
        }
        
        .print-header {
            display: block !important;
        }
        
        a {
            text-decoration: none !important;
            color: #000 !important;
        }
        
        .btn, .action-buttons, footer {
            display: none !important;
        }
        
        h1, h2, h3, h4, h5, h6 {
            color: #000 !important;
        }
    }
    
    .print-header {
        display: none;
    }
    </style>
</body>
</html>

<?php
// Close database connections
if (isset($student_stmt)) $student_stmt->close();
?>
