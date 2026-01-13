<?php
// Set page title
$page_title = "Submit Exit Request - ISSU";

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

// Check if student already has an exit request
$existing_exit_query = "
    SELECT exit_id, exit_status FROM exit_case 
    WHERE student_id = ? AND (exit_status = 'Pending' OR exit_status = 'Approved')
    LIMIT 1
";
$existing_exit_stmt = $conn->prepare($existing_exit_query);
$existing_exit_stmt->bind_param("i", $student_id);
$existing_exit_stmt->execute();
$existing_exit = $existing_exit_stmt->get_result()->fetch_assoc();

// Initialize variables
$success = false;
$error = '';
$form_data = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate that student has active visa
    if (!$visa || $visa['status'] !== 'Active') {
        $error = 'You must have an active visa to submit an exit request.';
    } 
    // Check for existing exit request
    elseif ($existing_exit) {
        $error = 'You already have an exit request in progress. Please check the Exit Clearance page for status.';
    }
    else {
        // Sanitize and validate form data
        $exit_type = trim($_POST['exit_type'] ?? '');
        $exit_reason = trim($_POST['exit_reason'] ?? '');
        $expected_exit_date = trim($_POST['expected_exit_date'] ?? '');
        $destination_country = trim($_POST['destination_country'] ?? '');
        $contact_address = trim($_POST['contact_address'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? $student['email']);
        $additional_notes = trim($_POST['additional_notes'] ?? '');
        
        // Validate required fields
        if (empty($exit_type) || empty($exit_reason) || empty($expected_exit_date)) {
            $error = 'Please fill in all required fields.';
        } elseif (!in_array($exit_type, ['Graduation', 'Transfer', 'Withdrawal', 'Completion', 'Other'])) {
            $error = 'Please select a valid exit type.';
        } else {
            // Prepare data for database insertion
            $form_data = [
                'student_id' => $student_id,
                'exit_type' => $exit_type,
                'exit_reason' => $exit_reason,
                'expected_exit_date' => $expected_exit_date,
                'destination_country' => $destination_country,
                'contact_address' => $contact_address,
                'contact_phone' => $contact_phone,
                'contact_email' => $contact_email,
                'additional_notes' => $additional_notes,
                'request_date' => date('Y-m-d H:i:s'),
                'exit_status' => 'Pending'
            ];
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert into exit_case table
                $exit_query = "
                    INSERT INTO exit_case (
                        student_id, exit_type, exit_reason, expected_exit_date, 
                        destination_country, contact_address, contact_phone, 
                        contact_email, additional_notes, request_date, exit_status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                
                $exit_stmt = $conn->prepare($exit_query);
                $exit_stmt->bind_param(
                    "issssssssss",
                    $form_data['student_id'],
                    $form_data['exit_type'],
                    $form_data['exit_reason'],
                    $form_data['expected_exit_date'],
                    $form_data['destination_country'],
                    $form_data['contact_address'],
                    $form_data['contact_phone'],
                    $form_data['contact_email'],
                    $form_data['additional_notes'],
                    $form_data['request_date'],
                    $form_data['exit_status']
                );
                
                if ($exit_stmt->execute()) {
                    $exit_id = $conn->insert_id;
                    
                    // Create initial clearance record
                    $clearance_query = "
                        INSERT INTO clearance_record (
                            exit_id, status, submission_date
                        ) VALUES (?, 'Not Started', NOW())
                    ";
                    
                    $clearance_stmt = $conn->prepare($clearance_query);
                    $clearance_stmt->bind_param("i", $exit_id);
                    
                    if ($clearance_stmt->execute()) {
                        // Get clearance_id
                        $clearance_id = $conn->insert_id;
                        
                        // Create unit clearance records for all required units
                        $units = [
                            'Library',
                            'Finance Department',
                            'Academic Department',
                            'Student Affairs',
                            'Hostel Office',
                            'Sports Department',
                            'IT Department'
                        ];
                        
                        $unit_stmt = $conn->prepare("
                            INSERT INTO unit_clearance (clearance_id, unit_name, status) 
                            VALUES (?, ?, 'Pending')
                        ");
                        
                        foreach ($units as $unit) {
                            $unit_stmt->bind_param("is", $clearance_id, $unit);
                            $unit_stmt->execute();
                        }
                        $unit_stmt->close();
                        
                        // Commit transaction
                        $conn->commit();
                        $success = true;
                        
                        // Log activity
                        $activity_query = "INSERT INTO student_activity (student_id, activity_type, description) VALUES (?, 'Exit Request', 'Submitted exit clearance request')";
                        $activity_stmt = $conn->prepare($activity_query);
                        $activity_stmt->bind_param("i", $student_id);
                        $activity_stmt->execute();
                        $activity_stmt->close();
                        
                        // Redirect to exit clearance page after 3 seconds
                        header("Refresh: 3; url=exit_clearance.php");
                        
                    } else {
                        throw new Exception("Failed to create clearance record.");
                    }
                    $clearance_stmt->close();
                } else {
                    throw new Exception("Failed to submit exit request.");
                }
                $exit_stmt->close();
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = 'An error occurred while submitting your request. Please try again.';
                error_log("Exit request submission error: " . $e->getMessage());
            }
        }
    }
}

// Close statements
$visa_stmt->close();
$existing_exit_stmt->close();
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 fw-bold text-primary">Submit Exit Request</h1>
            <p class="text-muted mb-0">Complete the form below to begin your exit clearance process</p>
        </div>
        <div>
            <a href="exit_clearance.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Exit Clearance
            </a>
        </div>
    </div>

    <!-- Success Message -->
    <?php if ($success): ?>
    <div class="alert alert-success" role="alert">
        <div class="d-flex align-items-center">
            <div class="flex-shrink-0">
                <i class="bi bi-check-circle-fill fs-3"></i>
            </div>
            <div class="flex-grow-1 ms-3">
                <h5 class="alert-heading mb-2">Exit Request Submitted Successfully!</h5>
                <p class="mb-0">Your exit clearance request has been submitted and is now pending approval. You will be redirected to the Exit Clearance page in a few seconds.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- Visa Status Warning -->
    <?php if (!$visa || $visa['status'] !== 'Active'): ?>
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Visa Status Alert!</strong> You cannot submit an exit request without an active visa. 
        Please ensure your visa is active before proceeding.
    </div>
    <?php endif; ?>

    <!-- Existing Request Warning -->
    <?php if ($existing_exit && !$success): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>Existing Exit Request Found!</strong> You already have an exit request with status: 
        <span class="badge <?php echo $existing_exit['exit_status'] == 'Approved' ? 'bg-success' : 'bg-warning'; ?>">
            <?php echo htmlspecialchars($existing_exit['exit_status']); ?>
        </span>
        <br>
        <a href="exit_clearance.php" class="alert-link">View your exit clearance status</a>
    </div>
    <?php endif; ?>

    <!-- Main Form (only show if no existing request and visa is active) -->
    <?php if ((!$existing_exit || $existing_exit['exit_status'] == 'Rejected') && $visa && $visa['status'] === 'Active' && !$success): ?>
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-box-arrow-right me-2"></i> Exit Request Form</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="exitRequestForm">
                        <!-- Student Information -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-person-circle me-2"></i> Student Information</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Student ID</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($student_id); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Program</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['program_name'] ?? 'Not specified'); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">School</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['school_name'] ?? 'Not specified'); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Exit Details -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-info-circle me-2"></i> Exit Details</h6>
                            
                            <!-- Exit Type -->
                            <div class="mb-3">
                                <label for="exit_type" class="form-label">Exit Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="exit_type" name="exit_type" required>
                                    <option value="" selected disabled>Select exit type</option>
                                    <option value="Graduation" <?php echo ($_POST['exit_type'] ?? '') == 'Graduation' ? 'selected' : ''; ?>>Graduation</option>
                                    <option value="Transfer" <?php echo ($_POST['exit_type'] ?? '') == 'Transfer' ? 'selected' : ''; ?>>Transfer to Another Institution</option>
                                    <option value="Withdrawal" <?php echo ($_POST['exit_type'] ?? '') == 'Withdrawal' ? 'selected' : ''; ?>>Withdrawal from Studies</option>
                                    <option value="Completion" <?php echo ($_POST['exit_type'] ?? '') == 'Completion' ? 'selected' : ''; ?>>Program Completion</option>
                                    <option value="Other" <?php echo ($_POST['exit_type'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <div class="form-text">Please select the primary reason for your exit</div>
                            </div>

                            <!-- Exit Reason -->
                            <div class="mb-3">
                                <label for="exit_reason" class="form-label">Exit Reason <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="exit_reason" name="exit_reason" rows="3" required maxlength="500"><?php echo htmlspecialchars($_POST['exit_reason'] ?? ''); ?></textarea>
                                <div class="form-text">Please provide a detailed reason for your exit (max 500 characters)</div>
                            </div>

                            <!-- Expected Exit Date -->
                            <div class="mb-3">
                                <label for="expected_exit_date" class="form-label">Expected Exit Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="expected_exit_date" name="expected_exit_date" 
                                       value="<?php echo htmlspecialchars($_POST['expected_exit_date'] ?? ''); ?>" 
                                       min="<?php echo date('Y-m-d'); ?>" 
                                       max="<?php echo date('Y-m-d', strtotime('+6 months')); ?>" required>
                                <div class="form-text">Select the date you plan to leave the university (within next 6 months)</div>
                            </div>

                            <!-- Destination Country -->
                            <div class="mb-3">
                                <label for="destination_country" class="form-label">Destination Country</label>
                                <input type="text" class="form-control" id="destination_country" name="destination_country" 
                                       value="<?php echo htmlspecialchars($_POST['destination_country'] ?? ''); ?>" 
                                       maxlength="100">
                                <div class="form-text">Country you plan to go to after exiting</div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-telephone me-2"></i> Contact Information</h6>
                            
                            <div class="mb-3">
                                <label for="contact_address" class="form-label">Contact Address</label>
                                <textarea class="form-control" id="contact_address" name="contact_address" rows="2" maxlength="255"><?php echo htmlspecialchars($_POST['contact_address'] ?? ''); ?></textarea>
                                <div class="form-text">Your permanent address for correspondence</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="contact_phone" class="form-label">Contact Phone</label>
                                    <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                           value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? $student['phone'] ?? ''); ?>" 
                                           maxlength="20">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="contact_email" class="form-label">Contact Email</label>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                           value="<?php echo htmlspecialchars($_POST['contact_email'] ?? $student['email']); ?>" 
                                           maxlength="100" required>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-chat-text me-2"></i> Additional Information</h6>
                            
                            <div class="mb-3">
                                <label for="additional_notes" class="form-label">Additional Notes (Optional)</label>
                                <textarea class="form-control" id="additional_notes" name="additional_notes" rows="4" maxlength="1000"><?php echo htmlspecialchars($_POST['additional_notes'] ?? ''); ?></textarea>
                                <div class="form-text">Any additional information or special requests (max 1000 characters)</div>
                            </div>
                        </div>

                        <!-- Terms and Conditions -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms_agreement" name="terms_agreement" required>
                                <label class="form-check-label" for="terms_agreement">
                                    I hereby declare that the information provided above is true and correct to the best of my knowledge. 
                                    I understand that false information may result in cancellation of my exit clearance.
                                </label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-outline-secondary me-md-2">
                                <i class="bi bi-x-circle me-1"></i> Clear Form
                            </button>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="bi bi-send me-1"></i> Submit Exit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column - Information & Requirements -->
        <div class="col-lg-4">
            <!-- Important Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i> Important Information</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning mb-3">
                        <h6 class="alert-heading">Before You Submit</h6>
                        <ul class="mb-0 small">
                            <li>Ensure all university fees are paid</li>
                            <li>Return all borrowed library books</li>
                            <li>Clear any outstanding dues</li>
                            <li>Update your contact information</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <h6 class="alert-heading">Processing Time</h6>
                        <p class="mb-0 small">Exit clearance typically takes 7-14 working days to process. Ensure all information is accurate to avoid delays.</p>
                    </div>
                </div>
            </div>

            <!-- Required Documents -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i> Required Documents</h5>
                </div>
                <div class="card-body">
                    <p class="small mb-3">You may need to submit the following documents after your request is approved:</p>
                    <ul class="small">
                        <li>Clearance form (will be provided)</li>
                        <li>Copy of valid passport</li>
                        <li>Copy of student ID card</li>
                        <li>No objection certificate (if applicable)</li>
                        <li>Academic transcript request</li>
                    </ul>
                </div>
            </div>

            <!-- Visa Information -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-passport me-2"></i> Visa Information</h5>
                </div>
                <div class="card-body">
                    <?php if ($visa): ?>
                    <div class="list-group list-group-flush small">
                        <div class="list-group-item border-0 px-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Visa Status</span>
                                <span class="badge bg-success">Active</span>
                            </div>
                        </div>
                        <div class="list-group-item border-0 px-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Expiry Date</span>
                                <span><?php echo date('M d, Y', strtotime($visa['expiry_date'])); ?></span>
                            </div>
                        </div>
                        <div class="list-group-item border-0 px-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Passport No.</span>
                                <span class="fw-semibold"><?php echo htmlspecialchars($visa['passport_no']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="text-danger small mb-0">No active visa found. You must have an active visa to submit exit request.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript for form validation -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('exitRequestForm');
        const submitBtn = document.getElementById('submitBtn');
        
        // Date validation
        const exitDateInput = document.getElementById('expected_exit_date');
        const today = new Date().toISOString().split('T')[0];
        const maxDate = new Date();
        maxDate.setMonth(maxDate.getMonth() + 6);
        const maxDateStr = maxDate.toISOString().split('T')[0];
        
        exitDateInput.min = today;
        exitDateInput.max = maxDateStr;
        
        // Form submission handler
        form.addEventListener('submit', function(e) {
            // Validate exit date
            const selectedDate = new Date(exitDateInput.value);
            const minDate = new Date(today);
            const maxDate = new Date(maxDateStr);
            
            if (selectedDate < minDate || selectedDate > maxDate) {
                e.preventDefault();
                alert('Please select an exit date within the next 6 months.');
                exitDateInput.focus();
                return false;
            }
            
            // Validate reason length
            const reasonText = document.getElementById('exit_reason').value;
            if (reasonText.length < 10) {
                e.preventDefault();
                alert('Please provide a more detailed exit reason (at least 10 characters).');
                document.getElementById('exit_reason').focus();
                return false;
            }
            
            // Disable submit button to prevent double submission
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Processing...';
            
            return true;
        });
        
        // Character counters
        const reasonTextarea = document.getElementById('exit_reason');
        const notesTextarea = document.getElementById('additional_notes');
        
        function createCounter(textarea, maxLength) {
            const counter = document.createElement('small');
            counter.className = 'text-muted float-end';
            counter.style.fontSize = '0.875em';
            counter.textContent = `0/${maxLength}`;
            
            textarea.parentNode.appendChild(counter);
            
            textarea.addEventListener('input', function() {
                const currentLength = this.value.length;
                counter.textContent = `${currentLength}/${maxLength}`;
                
                if (currentLength > maxLength) {
                    counter.className = 'text-danger float-end';
                } else if (currentLength > maxLength * 0.9) {
                    counter.className = 'text-warning float-end';
                } else {
                    counter.className = 'text-muted float-end';
                }
            });
        }
        
        createCounter(reasonTextarea, 500);
        if (notesTextarea) {
            createCounter(notesTextarea, 1000);
        }
    });
    </script>
    
    <?php endif; // End form display condition ?>
</div>

<?php
// Close database connections
if (isset($stmt)) $stmt->close();

// Include footer
require_once 'footer.php';
?>
