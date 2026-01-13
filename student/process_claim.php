<?php
// Set page title
$page_title = "Submit Insurance Claim - ISSU";

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

// Include database
require_once '../includes/db.php';

// Get student ID from session
$student_id = $_SESSION['user_id'];

// Initialize variables
$success_message = '';
$error_message = '';
$is_edit_mode = false;
$claim_to_edit = null;

// Check if editing existing claim
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $claim_id = $_GET['edit'];
    
    // Verify the claim belongs to the student
    $verify_query = "
        SELECT c.*, p.policy_number, pr.provider_name
        FROM insurance_claim c
        JOIN insurance_policy p ON c.policy_id = p.policy_id
        JOIN insurance_provider pr ON p.provider_id = pr.provider_id
        WHERE c.claim_id = ? AND p.student_id = ? AND c.claim_status = 'Pending'
    ";
    
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("ii", $claim_id, $student_id);
    $verify_stmt->execute();
    $claim_to_edit = $verify_stmt->get_result()->fetch_assoc();
    
    if ($claim_to_edit) {
        $is_edit_mode = true;
    } else {
        $error_message = "Claim not found or cannot be edited.";
        header("Location: insurance.php");
        exit();
    }
    $verify_stmt->close();
}

// Fetch student's active insurance policies
$policies_query = "
    SELECT p.*, pr.provider_name, pr.contact_info
    FROM insurance_policy p
    JOIN insurance_provider pr ON p.provider_id = pr.provider_id
    WHERE p.student_id = ? AND p.status = 'Active'
    ORDER BY p.end_date DESC
";
$policies_stmt = $conn->prepare($policies_query);
$policies_stmt->bind_param("i", $student_id);
$policies_stmt->execute();
$policies = $policies_stmt->get_result();

// Fetch student details
$student_query = "SELECT first_name, last_name, email, phone FROM student WHERE student_id = ?";
$student_stmt = $conn->prepare($student_query);
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

// Handle claim submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $policy_id = $_POST['policy_id'] ?? '';
    $claim_amount = $_POST['claim_amount'] ?? '';
    $treatment_date = $_POST['treatment_date'] ?? '';
    $hospital_name = $_POST['hospital_name'] ?? '';
    $diagnosis = $_POST['diagnosis'] ?? '';
    $treatment_details = $_POST['treatment_details'] ?? '';
    $receipt_amount = $_POST['receipt_amount'] ?? '';
    
    // Validate inputs
    if (empty($policy_id) || empty($claim_amount) || empty($treatment_date) || empty($hospital_name)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!is_numeric($claim_amount) || $claim_amount <= 0) {
        $error_message = "Please enter a valid claim amount.";
    } elseif (!is_numeric($receipt_amount) || $receipt_amount <= 0) {
        $error_message = "Please enter a valid receipt amount.";
    } else {
        // Validate policy belongs to student
        $check_policy = $conn->prepare("SELECT policy_id FROM insurance_policy WHERE policy_id = ? AND student_id = ? AND status = 'Active'");
        $check_policy->bind_param("ii", $policy_id, $student_id);
        $check_policy->execute();
        $check_policy->store_result();
        
        if ($check_policy->num_rows == 0) {
            $error_message = "Invalid policy or policy is not active.";
        } else {
            try {
                if ($is_edit_mode && isset($_POST['update_claim'])) {
                    // Update existing claim
                    $update_query = "
                        UPDATE insurance_claim 
                        SET policy_id = ?, claim_amount = ?, claim_date = CURDATE()
                        WHERE claim_id = ? AND claim_status = 'Pending'
                    ";
                    $stmt = $conn->prepare($update_query);
                    $stmt->bind_param("idi", $policy_id, $claim_amount, $claim_to_edit['claim_id']);
                    
                    if ($stmt->execute()) {
                        $success_message = "Claim updated successfully!";
                        // Store additional claim details in session for success page
                        $_SESSION['claim_details'] = [
                            'claim_id' => $claim_to_edit['claim_id'],
                            'policy_number' => $_POST['policy_number'] ?? '',
                            'claim_amount' => $claim_amount,
                            'treatment_date' => $treatment_date,
                            'hospital_name' => $hospital_name
                        ];
                        header("Location: claim_success.php");
                        exit();
                    }
                    $stmt->close();
                } else {
                    // Submit new claim using stored procedure
                    $submit_query = "CALL sp_student_submit_insurance_claim(?, ?, ?, @claim_id)";
                    $stmt = $conn->prepare($submit_query);
                    $stmt->bind_param("iid", $student_id, $policy_id, $claim_amount);
                    
                    if ($stmt->execute()) {
                        // Get the generated claim ID
                        $result = $conn->query("SELECT @claim_id as claim_id");
                        $claim_result = $result->fetch_assoc();
                        $new_claim_id = $claim_result['claim_id'] ?? 0;
                        
                        // Store additional claim details in session for success page
                        $_SESSION['claim_details'] = [
                            'claim_id' => $new_claim_id,
                            'policy_number' => $_POST['policy_number'] ?? '',
                            'claim_amount' => $claim_amount,
                            'treatment_date' => $treatment_date,
                            'hospital_name' => $hospital_name
                        ];
                        
                        $success_message = "Claim submitted successfully! Claim ID: #" . $new_claim_id;
                        header("Location: claim_success.php");
                        exit();
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
        $check_policy->close();
    }
}

// If no active policies, show error
if ($policies->num_rows == 0 && !$is_edit_mode) {
    $error_message = "You don't have any active insurance policies. Please contact ISSU office.";
}
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
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .claim-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .claim-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .claim-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .claim-card-header {
            background: var(--primary-blue);
            color: white;
            padding: 1.5rem;
            border-bottom: none;
        }
        
        .claim-card-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .required::after {
            content: " *";
            color: #dc3545;
        }
        
        .info-box {
            background: #e8f4fd;
            border-left: 4px solid var(--primary-blue);
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }
        
        .policy-card {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .policy-card:hover,
        .policy-card.selected {
            border-color: var(--primary-blue);
            background: #f0f9ff;
        }
        
        .policy-card.selected {
            border-width: 3px;
        }
        
        .amount-input {
            position: relative;
        }
        
        .amount-input .input-group-text {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .steps-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .steps-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        
        .step {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 2px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin: 0 auto 0.5rem;
            transition: all 0.3s ease;
        }
        
        .step.active .step-number {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
            color: white;
        }
        
        .step.completed .step-number {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .step-label {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .step.active .step-label {
            color: var(--primary-blue);
            font-weight: 600;
        }
        
        .back-link {
            color: var(--primary-blue);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .receipt-upload {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .receipt-upload:hover {
            border-color: var(--primary-blue);
            background: #f8f9fa;
        }
        
        .receipt-upload i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="claim-header">
        <div class="claim-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">
                        <i class="bi bi-file-earmark-medical me-2"></i>
                        <?php echo $is_edit_mode ? 'Edit Insurance Claim' : 'Submit Insurance Claim'; ?>
                    </h1>
                    <p class="mb-0 opacity-75">ISSU Student Portal</p>
                </div>
                <div class="text-end">
                    <div class="fw-semibold">ID: <?php echo $student_id; ?></div>
                    <small class="opacity-75">Student ID</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="claim-container flex-grow-1">
        <!-- Back Link -->
        <a href="insurance.php" class="back-link no-print">
            <i class="bi bi-arrow-left me-2"></i> Back to Insurance
        </a>
        
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
        
        <?php if ($policies->num_rows == 0 && !$is_edit_mode): ?>
        <div class="claim-card">
            <div class="claim-card-body text-center py-5">
                <i class="bi bi-shield-slash fs-1 text-muted mb-3"></i>
                <h4>No Active Insurance Policies</h4>
                <p class="text-muted mb-4">You need an active insurance policy to submit a claim.</p>
                <a href="../contact.php" class="btn btn-primary">
                    <i class="bi bi-telephone me-2"></i> Contact ISSU Office
                </a>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Steps Indicator -->
        <div class="steps-indicator no-print">
            <div class="step active">
                <div class="step-number">1</div>
                <div class="step-label">Policy Selection</div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-label">Claim Details</div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-label">Review & Submit</div>
            </div>
        </div>
        
        <!-- Claim Form -->
        <div class="claim-card">
            <div class="claim-card-header">
                <h3 class="h5 mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    <?php echo $is_edit_mode ? 'Edit Claim Details' : 'Claim Submission Form'; ?>
                </h3>
            </div>
            
            <div class="claim-card-body">
                <form method="POST" action="" id="claimForm">
                    
                    <!-- Step 1: Policy Selection -->
                    <div class="step-content" id="step1">
                        <h4 class="mb-4">Select Insurance Policy</h4>
                        
                        <div class="info-box">
                            <i class="bi bi-info-circle me-2"></i>
                            Select the active insurance policy you want to claim under. Only active policies are shown.
                        </div>
                        
                        <div class="policy-options">
                            <?php while ($policy = $policies->fetch_assoc()): ?>
                            <?php
                            $expiry_date = new DateTime($policy['end_date']);
                            $today = new DateTime();
                            $is_expired = $expiry_date < $today;
                            ?>
                            <div class="policy-card" 
                                 data-policy-id="<?php echo $policy['policy_id']; ?>"
                                 data-policy-number="<?php echo htmlspecialchars($policy['policy_number']); ?>"
                                 data-provider="<?php echo htmlspecialchars($policy['provider_name']); ?>"
                                 data-expiry="<?php echo date('M d, Y', strtotime($policy['end_date'])); ?>">
                                 
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($policy['policy_number']); ?></h5>
                                        <p class="mb-1">
                                            <i class="bi bi-building me-1"></i>
                                            <?php echo htmlspecialchars($policy['provider_name']); ?>
                                        </p>
                                        <p class="mb-1 text-muted small">
                                            <i class="bi bi-calendar me-1"></i>
                                            Valid until: <?php echo date('M d, Y', strtotime($policy['end_date'])); ?>
                                        </p>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $policy['status'] == 'Active' ? 'success' : 'danger'; ?>">
                                            <?php echo $policy['status']; ?>
                                        </span>
                                        <?php if ($policy['coverage_type']): ?>
                                        <div class="text-muted small mt-1"><?php echo $policy['coverage_type']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="policy-details" style="display: none;">
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <small class="text-muted">Start Date:</small>
                                            <div><?php echo date('M d, Y', strtotime($policy['start_date'])); ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted">Days Left:</small>
                                            <div>
                                                <?php if (!$is_expired): ?>
                                                <?php
                                                $days_left = $today->diff($expiry_date)->days;
                                                ?>
                                                <span class="badge bg-<?php echo $days_left <= 30 ? 'warning' : 'success'; ?>">
                                                    <?php echo $days_left; ?> days
                                                </span>
                                                <?php else: ?>
                                                <span class="badge bg-danger">Expired</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <input type="hidden" name="policy_id" id="selectedPolicyId" 
                               value="<?php echo $is_edit_mode ? $claim_to_edit['policy_id'] : ''; ?>">
                        <input type="hidden" name="policy_number" id="selectedPolicyNumber">
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='insurance.php'">
                                Cancel
                            </button>
                            <button type="button" class="btn btn-primary" onclick="nextStep()">
                                Next: Claim Details <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Claim Details -->
                    <div class="step-content" id="step2" style="display: none;">
                        <h4 class="mb-4">Claim Details</h4>
                        
                        <div class="info-box">
                            <i class="bi bi-info-circle me-2"></i>
                            Please provide details about the medical treatment and the amount you're claiming.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="treatment_date" class="form-label required">Treatment Date</label>
                                <input type="date" class="form-control" id="treatment_date" name="treatment_date" 
                                       max="<?php echo date('Y-m-d'); ?>" 
                                       value="<?php echo isset($_POST['treatment_date']) ? $_POST['treatment_date'] : date('Y-m-d'); ?>" 
                                       required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="hospital_name" class="form-label required">Hospital/Clinic Name</label>
                                <input type="text" class="form-control" id="hospital_name" name="hospital_name" 
                                       value="<?php echo isset($_POST['hospital_name']) ? htmlspecialchars($_POST['hospital_name']) : ''; ?>" 
                                       placeholder="Enter hospital or clinic name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="diagnosis" class="form-label required">Diagnosis/Medical Condition</label>
                                <input type="text" class="form-control" id="diagnosis" name="diagnosis" 
                                       value="<?php echo isset($_POST['diagnosis']) ? htmlspecialchars($_POST['diagnosis']) : ''; ?>" 
                                       placeholder="e.g., Fever, Injury, Surgery, etc." required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="treatment_details" class="form-label">Treatment Details</label>
                                <textarea class="form-control" id="treatment_details" name="treatment_details" 
                                          rows="3" placeholder="Describe the treatment received..."><?php echo isset($_POST['treatment_details']) ? htmlspecialchars($_POST['treatment_details']) : ''; ?></textarea>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="receipt_amount" class="form-label required">Total Receipt Amount (RM)</label>
                                <div class="input-group amount-input">
                                    <span class="input-group-text">RM</span>
                                    <input type="number" class="form-control" id="receipt_amount" name="receipt_amount" 
                                           step="0.01" min="0.01" 
                                           value="<?php echo isset($_POST['receipt_amount']) ? $_POST['receipt_amount'] : ''; ?>" 
                                           placeholder="0.00" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="claim_amount" class="form-label required">Claim Amount (RM)</label>
                                <div class="input-group amount-input">
                                    <span class="input-group-text">RM</span>
                                    <input type="number" class="form-control" id="claim_amount" name="claim_amount" 
                                           step="0.01" min="0.01" 
                                           value="<?php echo $is_edit_mode ? $claim_to_edit['claim_amount'] : (isset($_POST['claim_amount']) ? $_POST['claim_amount'] : ''); ?>" 
                                           placeholder="0.00" required>
                                </div>
                                <div class="form-text">Amount you are claiming (cannot exceed receipt amount)</div>
                            </div>
                        </div>
                        
                        <!-- Receipt Upload Section -->
                        <div class="mb-4">
                            <label class="form-label">Upload Receipts (Optional)</label>
                            <div class="receipt-upload" onclick="document.getElementById('receiptFile').click()">
                                <i class="bi bi-cloud-arrow-up"></i>
                                <h5>Click to upload medical receipts</h5>
                                <p class="text-muted mb-2">Supported formats: PDF, JPG, PNG (Max: 10MB)</p>
                                <small class="text-muted">You can upload receipts later from the documents page</small>
                                <input type="file" id="receiptFile" name="receipt_file" accept=".pdf,.jpg,.jpeg,.png" style="display: none;">
                            </div>
                            <div id="filePreview" class="mt-2"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-secondary" onclick="prevStep()">
                                <i class="bi bi-arrow-left me-2"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary" onclick="nextStep()">
                                Next: Review <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Review & Submit -->
                    <div class="step-content" id="step3" style="display: none;">
                        <h4 class="mb-4">Review & Submit</h4>
                        
                        <div class="info-box">
                            <i class="bi bi-check-circle me-2"></i>
                            Please review your claim details before submission. Once submitted, claims will be processed within 7-14 working days.
                        </div>
                        
                        <div class="review-section mb-4">
                            <h5 class="mb-3">Claim Summary</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <th width="30%">Selected Policy</th>
                                            <td id="reviewPolicy">-</td>
                                        </tr>
                                        <tr>
                                            <th>Treatment Date</th>
                                            <td id="reviewTreatmentDate">-</td>
                                        </tr>
                                        <tr>
                                            <th>Hospital/Clinic</th>
                                            <td id="reviewHospital">-</td>
                                        </tr>
                                        <tr>
                                            <th>Diagnosis</th>
                                            <td id="reviewDiagnosis">-</td>
                                        </tr>
                                        <tr>
                                            <th>Total Receipt Amount</th>
                                            <td id="reviewReceiptAmount">-</td>
                                        </tr>
                                        <tr>
                                            <th>Claim Amount</th>
                                            <td id="reviewClaimAmount">-</td>
                                        </tr>
                                        <tr>
                                            <th>Student Information</th>
                                            <td>
                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?><br>
                                                <small class="text-muted">ID: <?php echo $student_id; ?></small>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Important:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Claims are processed within 7-14 working days</li>
                                <li>You may be asked to submit original receipts</li>
                                <li>False claims may result in policy cancellation</li>
                                <li>Keep all original documents for verification</li>
                            </ul>
                        </div>
                        
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="confirmTerms" required>
                            <label class="form-check-label" for="confirmTerms">
                                I confirm that all information provided is accurate and I have read the terms and conditions.
                            </label>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-secondary" onclick="prevStep()">
                                <i class="bi bi-arrow-left me-2"></i> Back
                            </button>
                            <?php if ($is_edit_mode): ?>
                            <button type="submit" name="update_claim" class="btn btn-success">
                                <i class="bi bi-check-circle me-2"></i> Update Claim
                            </button>
                            <?php else: ?>
                            <button type="submit" name="submit_claim" class="btn btn-success">
                                <i class="bi bi-send-check me-2"></i> Submit Claim
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </form>
            </div>
        </div>
        
        <!-- Claim Process Information -->
        <div class="claim-card no-print">
            <div class="claim-card-body">
                <h5 class="mb-3"><i class="bi bi-clock-history me-2"></i> Claim Process Timeline</h5>
                <div class="row">
                    <div class="col-md-3 text-center mb-3">
                        <div class="process-step">
                            <div class="process-icon bg-primary text-white">
                                <i class="bi bi-send-check"></i>
                            </div>
                            <h6 class="mt-2 mb-1">Submission</h6>
                            <small class="text-muted">Claim submitted</small>
                        </div>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <div class="process-step">
                            <div class="process-icon bg-info text-white">
                                <i class="bi bi-file-earmark-check"></i>
                            </div>
                            <h6 class="mt-2 mb-1">Document Review</h6>
                            <small class="text-muted">1-3 working days</small>
                        </div>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <div class="process-step">
                            <div class="process-icon bg-warning text-dark">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                            <h6 class="mt-2 mb-1">Processing</h6>
                            <small class="text-muted">3-7 working days</small>
                        </div>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <div class="process-step">
                            <div class="process-icon bg-success text-white">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <h6 class="mt-2 mb-1">Completion</h6>
                            <small class="text-muted">Payment issued</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-4 no-print">
        <div class="claim-container text-center">
            <p class="mb-0">ISSU Student Portal &copy; <?php echo date('Y'); ?> | Need help? Contact: support@issu.edu</p>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
    let currentStep = 1;
    const totalSteps = 3;
    
    // Policy selection
    document.querySelectorAll('.policy-card').forEach(card => {
        card.addEventListener('click', function() {
            // Remove selected class from all cards
            document.querySelectorAll('.policy-card').forEach(c => {
                c.classList.remove('selected');
                c.querySelector('.policy-details').style.display = 'none';
            });
            
            // Add selected class to clicked card
            this.classList.add('selected');
            this.querySelector('.policy-details').style.display = 'block';
            
            // Set hidden inputs
            document.getElementById('selectedPolicyId').value = this.dataset.policyId;
            document.getElementById('selectedPolicyNumber').value = this.dataset.policyNumber;
            
            // Update review section
            document.getElementById('reviewPolicy').textContent = 
                `${this.dataset.policyNumber} (${this.dataset.provider}) - Valid until ${this.dataset.expiry}`;
        });
    });
    
    // Auto-select policy if in edit mode
    <?php if ($is_edit_mode && $claim_to_edit): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const policyCard = document.querySelector(`.policy-card[data-policy-id="<?php echo $claim_to_edit['policy_id']; ?>"]`);
        if (policyCard) {
            policyCard.click();
            // Pre-fill other fields
            document.getElementById('claim_amount').value = "<?php echo $claim_to_edit['claim_amount']; ?>";
            document.getElementById('reviewClaimAmount').textContent = "RM " + <?php echo $claim_to_edit['claim_amount']; ?>;
            document.getElementById('reviewPolicy').textContent = 
                "<?php echo htmlspecialchars($claim_to_edit['policy_number'] . ' (' . $claim_to_edit['provider_name'] . ')'); ?>";
        }
    });
    <?php endif; ?>
    
    // File upload preview
    document.getElementById('receiptFile').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('filePreview');
        
        if (file) {
            if (file.size > 10 * 1024 * 1024) {
                alert('File size exceeds 10MB limit.');
                this.value = '';
                preview.innerHTML = '';
                return;
            }
            
            const validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            if (!validTypes.includes(file.type)) {
                alert('Only PDF, JPG, and PNG files are allowed.');
                this.value = '';
                preview.innerHTML = '';
                return;
            }
            
            preview.innerHTML = `
                <div class="alert alert-info d-flex align-items-center">
                    <i class="bi bi-file-earmark-text me-3 fs-4"></i>
                    <div>
                        <div class="fw-semibold">${file.name}</div>
                        <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                    </div>
                    <button type="button" class="btn-close ms-auto" onclick="clearFile()"></button>
                </div>
            `;
        }
    });
    
    function clearFile() {
        document.getElementById('receiptFile').value = '';
        document.getElementById('filePreview').innerHTML = '';
    }
    
    // Step navigation
    function nextStep() {
        if (!validateStep(currentStep)) {
            return;
        }
        
        // Update step indicator
        document.querySelector(`.step:nth-child(${currentStep})`).classList.add('completed');
        document.querySelector(`.step:nth-child(${currentStep})`).classList.remove('active');
        
        // Hide current step
        document.getElementById(`step${currentStep}`).style.display = 'none';
        
        // Move to next step
        currentStep++;
        document.querySelector(`.step:nth-child(${currentStep})`).classList.add('active');
        document.getElementById(`step${currentStep}`).style.display = 'block';
        
        // Update review section if on last step
        if (currentStep === 3) {
            updateReviewSection();
        }
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    function prevStep() {
        // Update step indicator
        document.querySelector(`.step:nth-child(${currentStep})`).classList.remove('active');
        
        // Hide current step
        document.getElementById(`step${currentStep}`).style.display = 'none';
        
        // Move to previous step
        currentStep--;
        document.querySelector(`.step:nth-child(${currentStep})`).classList.add('active');
        document.querySelector(`.step:nth-child(${currentStep})`).classList.remove('completed');
        document.getElementById(`step${currentStep}`).style.display = 'block';
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    function validateStep(step) {
        switch(step) {
            case 1:
                const selectedPolicy = document.getElementById('selectedPolicyId').value;
                if (!selectedPolicy) {
                    alert('Please select an insurance policy.');
                    return false;
                }
                return true;
                
            case 2:
                const requiredFields = ['treatment_date', 'hospital_name', 'diagnosis', 'receipt_amount', 'claim_amount'];
                for (const fieldId of requiredFields) {
                    const field = document.getElementById(fieldId);
                    if (!field.value.trim()) {
                        alert(`Please fill in ${field.placeholder || field.name}.`);
                        field.focus();
                        return false;
                    }
                }
                
                // Validate amounts
                const receiptAmount = parseFloat(document.getElementById('receipt_amount').value);
                const claimAmount = parseFloat(document.getElementById('claim_amount').value);
                
                if (claimAmount > receiptAmount) {
                    alert('Claim amount cannot exceed receipt amount.');
                    document.getElementById('claim_amount').focus();
                    return false;
                }
                
                if (claimAmount <= 0 || receiptAmount <= 0) {
                    alert('Amounts must be greater than zero.');
                    return false;
                }
                
                return true;
                
            default:
                return true;
        }
    }
    
    function updateReviewSection() {
        // Get values from form
        const treatmentDate = document.getElementById('treatment_date').value;
        const hospitalName = document.getElementById('hospital_name').value;
        const diagnosis = document.getElementById('diagnosis').value;
        const receiptAmount = document.getElementById('receipt_amount').value;
        const claimAmount = document.getElementById('claim_amount').value;
        
        // Format date
        const dateObj = new Date(treatmentDate);
        const formattedDate = dateObj.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        // Update review table
        document.getElementById('reviewTreatmentDate').textContent = formattedDate;
        document.getElementById('reviewHospital').textContent = hospitalName;
        document.getElementById('reviewDiagnosis').textContent = diagnosis;
        document.getElementById('reviewReceiptAmount').textContent = 'RM ' + parseFloat(receiptAmount).toFixed(2);
        document.getElementById('reviewClaimAmount').textContent = 'RM ' + parseFloat(claimAmount).toFixed(2);
    }
    
    // Auto-calculate claim amount from receipt amount
    document.getElementById('receipt_amount').addEventListener('input', function() {
        const receiptAmount = parseFloat(this.value) || 0;
        const claimAmountInput = document.getElementById('claim_amount');
        
        // Only auto-fill if claim amount is empty or less than receipt amount
        if (!claimAmountInput.value || parseFloat(claimAmountInput.value) > receiptAmount) {
            claimAmountInput.value = receiptAmount.toFixed(2);
        }
    });
    
    // Form submission validation
    document.getElementById('claimForm').addEventListener('submit', function(e) {
        const confirmTerms = document.getElementById('confirmTerms');
        if (!confirmTerms.checked) {
            e.preventDefault();
            alert('Please confirm that you have read and agree to the terms and conditions.');
            confirmTerms.focus();
            return false;
        }
        
        // Final validation
        if (!validateStep(1) || !validateStep(2)) {
            e.preventDefault();
            // Go back to first invalid step
            if (!validateStep(1)) {
                currentStep = 1;
                document.querySelectorAll('.step').forEach(step => {
                    step.classList.remove('active', 'completed');
                });
                document.querySelector('.step:nth-child(1)').classList.add('active');
                document.getElementById('step2').style.display = 'none';
                document.getElementById('step3').style.display = 'none';
                document.getElementById('step1').style.display = 'block';
            } else if (!validateStep(2)) {
                currentStep = 2;
                document.querySelectorAll('.step').forEach(step => {
                    step.classList.remove('active', 'completed');
                });
                document.querySelector('.step:nth-child(1)').classList.add('completed');
                document.querySelector('.step:nth-child(2)').classList.add('active');
                document.getElementById('step1').style.display = 'none';
                document.getElementById('step3').style.display = 'none';
                document.getElementById('step2').style.display = 'block';
            }
            return false;
        }
        
        // Show loading state
        const submitBtn = document.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            submitBtn.disabled = true;
        }
        
        return true;
    });
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Set max date to today
        document.getElementById('treatment_date').max = new Date().toISOString().split('T')[0];
        
        // Auto-select first policy if not in edit mode
        <?php if (!$is_edit_mode): ?>
        const firstPolicyCard = document.querySelector('.policy-card');
        if (firstPolicyCard) {
            firstPolicyCard.click();
        }
        <?php endif; ?>
    });
    </script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
    .process-step {
        padding: 1rem;
    }
    
    .process-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        font-size: 1.5rem;
    }
    
    @media (max-width: 768px) {
        .claim-container {
            padding: 0 10px;
        }
        
        .claim-card-body {
            padding: 1.5rem;
        }
        
        .steps-indicator {
            font-size: 0.875rem;
        }
        
        .step-number {
            width: 32px;
            height: 32px;
            font-size: 0.875rem;
        }
    }
    </style>
</body>
</html>

<?php
// Close database connections
if (isset($policies_stmt)) $policies_stmt->close();
if (isset($student_stmt)) $student_stmt->close();
?>
