<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db.php';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'student') {
        header("Location: student/dashboard.php");
    } else {
        header("Location: staff/dashboard.php");
    }
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Get programs for dropdown
$programs_query = "SELECT program_id, program_name FROM program ORDER BY program_name";
$programs_result = $conn->query($programs_query);

// Get schools for dropdown
$schools_query = "SELECT school_id, school_name FROM school ORDER BY school_name";
$schools_result = $conn->query($schools_query);

// Get countries for dropdown
$countries_query = "SELECT country_id, country_name FROM country ORDER BY country_name";
$countries_result = $conn->query($countries_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get form data
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $student_id = intval($_POST['student_id']);
    $program_id = intval($_POST['program_id']);
    $school_id = intval($_POST['school_id']);
    $nationality_id = intval($_POST['nationality_id']);
    $gender = $_POST['gender'];
    $date_of_birth = $_POST['date_of_birth'];
    $passport_no = $_POST['passport_no'] ?? '';
    $emergency_contact = $_POST['emergency_contact'] ?? '';
    $address = $_POST['address'] ?? '';
    $student_type = $_POST['student_type'] ?? 'UG';
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || 
        empty($password) || empty($confirm_password) || empty($student_id) || 
        empty($program_id) || empty($school_id) || empty($nationality_id) || 
        empty($gender) || empty($date_of_birth)) {
        $error = "All required fields must be filled!";
    }
    // Validate email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }
    // Validate password strength
    elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    }
    // Check password match
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    }
    // Check if email already exists
    elseif ($conn->query("SELECT student_id FROM student WHERE email = '$email'")->num_rows > 0) {
        $error = "Email already registered! Please use a different email.";
    }
    // Check if student ID already exists
    elseif ($conn->query("SELECT student_id FROM student WHERE student_id = $student_id")->num_rows > 0) {
        $error = "Student ID already exists! Please contact administrator.";
    }
    
    // If no errors, proceed with registration
    if (!$error) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // For demo - use simple password (in production, use password_hash)
            $hashed_password = $password; // Simple for demo
            
            // Insert into student table
            $student_query = "INSERT INTO student (student_id, program_id, first_name, last_name, phone, email, status, student_type) 
                             VALUES (?, ?, ?, ?, ?, ?, 'Active', ?)";
            
            $stmt = $conn->prepare($student_query);
            $stmt->bind_param("iisssss", $student_id, $program_id, $first_name, $last_name, $phone, $email, $student_type);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create student record: " . $stmt->error);
            }
            $stmt->close();
            
            // Insert into nationality table
            $nationality_query = "INSERT INTO nationality (student_id, country_id, acquired_date, is_primary) 
                                 VALUES (?, ?, CURDATE(), 1)";
            
            $stmt = $conn->prepare($nationality_query);
            $stmt->bind_param("ii", $student_id, $nationality_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to add nationality: " . $stmt->error);
            }
            $stmt->close();
            
            // Insert visa record
            if (!empty($passport_no)) {
                $visa_query = "INSERT INTO student_visa (visa_id, student_id, visa_type, issue_date, expiry_date, status, passport_no) 
                              VALUES (?, ?, 'Student Pass', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'Active', ?)";
                
                // Generate visa ID
                $visa_id = $student_id * 1000 + 1;
                
                $stmt = $conn->prepare($visa_query);
                $stmt->bind_param("iis", $visa_id, $student_id, $passport_no);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to create visa record: " . $stmt->error);
                }
                $stmt->close();
            }
            
            // Insert into student subtype table based on student_type
            switch ($student_type) {
                case 'PC': // Pre-College
                    $subtype_query = "INSERT INTO pre_college (student_id, guardian_name, guardian_contact, placement_test_score) 
                                     VALUES (?, ?, ?, NULL)";
                    $stmt = $conn->prepare($subtype_query);
                    $stmt->bind_param("iss", $student_id, $emergency_contact, $emergency_contact);
                    break;
                    
                case 'UG': // Undergraduate
                    $subtype_query = "INSERT INTO undergraduate (student_id, high_school_name, admission_score, scholarship_flag) 
                                     VALUES (?, ?, NULL, 0)";
                    $stmt = $conn->prepare($subtype_query);
                    $stmt->bind_param("is", $student_id, $emergency_contact);
                    break;
                    
                case 'PG': // Post Graduate
                    $subtype_query = "INSERT INTO post_graduate (student_id, previous_degree, supervisor_name, thesis_required) 
                                     VALUES (?, ?, NULL, 0)";
                    $stmt = $conn->prepare($subtype_query);
                    $stmt->bind_param("is", $student_id, $emergency_contact);
                    break;
            }
            
            if (isset($stmt)) {
                if (!$stmt->execute()) {
                    throw new Exception("Failed to add student subtype: " . $stmt->error);
                }
                $stmt->close();
            }
            
            // Commit transaction
            $conn->commit();
            
            // Set success message
            $success = "Registration successful! Your Student ID: $student_id. You can now login.";
            
            // Clear form data
            $_POST = array();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title data-i18n="page_title">Register - ISSU Visa Management System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-blue: #0e2a47;
            --secondary-blue: #1a5276;
            --dark-blue: #0b1f33;
            --light-blue: #e8f4fd;
            --accent-green: #2ecc71;
            --accent-red: #e74c3c;
            --text-gray: #3E3E3E;
            --border-gray: #E0E0E0;
            --btn-black: #0b0f14;
            --btn-navy: #0e2a47;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-gray);
            min-height: 100vh;
            padding: 20px;
            position: relative;
        }
        
        /* Background image */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url('https://ace-sedi.aiu.edu.my/cfgs%20pic.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            z-index: -2;
        }
        
        /* Overlay */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.001);
            z-index: -1;
        }
        
        .register-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 2.5rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255, 255, 255, 0.35);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 1.5rem;
        }
        
        .logo-img {
            width: 64px;
            height: 64px;
            object-fit: contain;
            border-radius: 12px;
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(0,0,0,0.06);
            box-shadow: 0 8px 18px rgba(0,0,0,0.08);
            padding: 8px;
        }
        
        .logo-text {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: var(--dark-blue);
            font-size: 2.2rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 0;
        }
        
        .logo-subtitle {
            color: var(--text-gray);
            opacity: 0.85;
            font-size: 1.1rem;
            margin-top: 0.5rem;
            margin-bottom: 0;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2.5rem;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border-gray);
            z-index: 1;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
            cursor: pointer;
            user-select: none;
        }
        
        .step-number {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 3px solid var(--border-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.2rem;
            color: var(--text-gray);
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .step.active .step-number {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
            color: white;
            transform: scale(1.1);
        }
        
        .step.completed .step-number {
            background: var(--accent-green);
            border-color: var(--accent-green);
            color: white;
        }
        
        .step-label {
            font-size: 0.9rem;
            color: var(--text-gray);
            font-weight: 500;
            text-align: center;
            max-width: 120px;
        }
        
        .form-section {
            background: #f8fafc;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary-blue);
            transition: transform 0.3s ease;
        }
        
        .form-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .section-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-blue);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
            font-size: 1.2rem;
        }
        
        .section-title {
            color: var(--dark-blue);
            font-weight: 600;
            font-size: 1.5rem;
            margin: 0;
        }
        
        .form-control {
            padding: 0.85rem 1.25rem;
            border-radius: 12px;
            border: 2px solid var(--border-gray);
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.25rem rgba(14, 42, 71, 0.14);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark-blue);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .required::after {
            content: " *";
            color: var(--accent-red);
        }
        
        .input-group-text {
            background: white;
            border: 2px solid var(--border-gray);
            color: var(--text-gray);
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .input-group .form-control { border-left: none; }
        .input-group .input-group-text { border-right: none; }
        
        .step-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid var(--border-gray);
        }
        
        .btn-step {
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-width: 150px;
        }
        
        /* Dark blue + black buttons */
        .btn-next {
            background: linear-gradient(135deg, var(--btn-navy), var(--btn-black));
            color: white;
            border: none;
        }
        
        .btn-next:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(11, 15, 20, 0.25);
            filter: brightness(1.05);
        }
        
        .btn-prev {
            background: linear-gradient(135deg, #ffffff, #f2f4f7);
            color: var(--dark-blue);
            border: 2px solid rgba(11, 15, 20, 0.15);
        }
        
        .btn-prev:hover {
            background: linear-gradient(135deg, #ffffff, #eef2f6);
            border-color: rgba(14, 42, 71, 0.35);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--btn-navy), #000000);
            color: white;
            border: none;
            width: 100%;
            padding: 1rem;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.25s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(11, 15, 20, 0.25);
            filter: brightness(1.05);
        }
        
        .file-upload-container {
            border: 2px dashed var(--border-gray);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload-container:hover {
            border-color: var(--primary-blue);
            background: var(--light-blue);
        }
        
        .file-upload-container.drag-over {
            border-color: var(--accent-green);
            background: rgba(46, 204, 113, 0.1);
        }
        
        .file-preview {
            width: 120px;
            height: 120px;
            border-radius: 10px;
            object-fit: cover;
            margin-bottom: 1rem;
            border: 3px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .terms-checkbox {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border: 2px solid var(--border-gray);
        }
        
        .terms-content {
            max-height: 200px;
            overflow-y: auto;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 1rem 0;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        
        .step-content { display: none; }
        .step-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #51cf66, #40c057);
            color: white;
        }
        
        .is-invalid { border-color: var(--accent-red) !important; }
        .is-invalid:focus {
            box-shadow: 0 0 0 0.25rem rgba(231, 76, 60, 0.25) !important;
        }
        
        .university-watermark {
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 0.8rem;
            color: rgba(0, 0, 0, 0.22);
            font-style: italic;
            z-index: 1;
        }
        
        /* Password toggle */
        .password-toggle {
            cursor: pointer;
            user-select: none;
        }
        
        .language-switcher {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 5;
        }
        
        .language-switcher select {
            border: 2px solid var(--border-gray);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            background: white;
            color: var(--text-gray);
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            body { padding: 10px; background-attachment: scroll; }
            .register-container { padding: 1.5rem; margin: 1rem auto; }
            .progress-steps { flex-direction: column; gap: 20px; }
            .progress-steps::before { display: none; }
            .step { flex-direction: row; gap: 15px; justify-content: flex-start; }
            .step-label { text-align: left; max-width: none; }
            .step-buttons { flex-direction: column; gap: 15px; }
            .btn-step { width: 100%; }
            .university-watermark { display: none; }
            .language-switcher {
                position: relative;
                top: 0;
                right: 0;
                margin-bottom: 1rem;
                text-align: center;
            }
            .language-switcher select {
                width: 100%;
            }
        }

        /* Logo Container */
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 1.5rem;
        }

        /* Logo Image Container */
        .logo-image-container {
            flex-shrink: 0;
        }

        /* University Logo */
        .university-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(0,0,0,0.06);
            box-shadow: 0 8px 18px rgba(0,0,0,0.08);
            padding: 8px;
        }

        /* Fallback logo */
        .logo-fallback {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-blue), #1a5276);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            box-shadow: 0 8px 18px rgba(0,0,0,0.08);
        }

        /* Logo Text Container */
        .logo-text-container {
            text-align: left;
            flex-grow: 1;
        }

        .logo-text {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: var(--dark-blue);
            font-size: 2.2rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 0;
            line-height: 1.2;
        }

        .logo-subtitle {
            color: var(--text-gray);
            opacity: 0.85;
            font-size: 1.1rem;
            margin-top: 0.5rem;
            margin-bottom: 0;
        }
    </style>
</head>

<body>
    <!-- Language Switcher -->
    <div class="language-switcher">
        <select id="langSelect" class="form-select form-select-sm" style="width:auto;">
            <option value="en">English</option>
            <option value="ms">Bahasa Melayu</option>
            <option value="id">Bahasa Indonesia</option>
            <option value="my">မြန်မာ</option>
            <option value="ar">العربية</option>
            <option value="si">සිංහල</option>
        </select>
    </div>
    
    <div class="container">
        <div class="register-container">
            <div class="register-header">
                <div class="logo-container">
                    <!-- University Logo -->
                    <div class="logo-image-container">
                        <img
                            src="https://aiu.edu.my/wp-content/uploads/2023/11/AIU-Official-Logo-01.png"
                            alt="Albukhary International University Logo"
                            class="university-logo"
                            onerror="this.style.display='none'; document.getElementById('logoFallback').style.display='flex';"
                        />
                        <div id="logoFallback" class="logo-fallback d-none">
                            <i class="bi bi-building"></i>
                        </div>
                    </div>
                    
                    <div class="logo-text-container">
                        <h1 class="logo-text" data-i18n="brand_title">ISSU Student Registration</h1>
                        <p class="logo-subtitle" data-i18n="welcome_subtitle">International Student Services Unit - New Student Registration</p>
                    </div>
                </div>
                
                <div class="progress-steps">
                    <div class="step active" data-step="1">
                        <div class="step-number">1</div>
                        <div class="step-label" data-i18n="step1_label">Personal Info</div>
                    </div>
                    <div class="step" data-step="2">
                        <div class="step-number">2</div>
                        <div class="step-label" data-i18n="step2_label">Academic Info</div>
                    </div>
                    <div class="step" data-step="3">
                        <div class="step-number">3</div>
                        <div class="step-label" data-i18n="step3_label">Account Setup</div>
                    </div>
                </div>
            </div>
            
            <!-- Alert Messages -->
            <?php if($error): ?>
                <div class="alert alert-danger alert-custom">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success alert-custom">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <br><br>
                    <a href="login.php" class="btn btn-light" data-i18n="go_to_login">Go to Login</a>
                </div>
            <?php endif; ?>
            
            <!-- Registration Form -->
            <?php if(!$success): ?>
            <form method="POST" action="" id="registrationForm">
                
                <!-- Step 1: Personal Information -->
                <div class="step-content active" id="step1">
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon"><i class="bi bi-person-circle"></i></div>
                            <h2 class="section-title" data-i18n="personal_info_title">Personal Information</h2>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="student_id" class="form-label required" data-i18n="label_student_id">Student ID</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                    <input type="number" class="form-control" id="student_id" name="student_id" 
                                           value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>" 
                                           placeholder="e.g., 1001" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label required" data-i18n="label_email">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                           placeholder="student@aiu.edu.my" required>
                                </div>
                                <small class="text-muted" data-i18n="email_hint">Use your official AIU email</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="first_name" class="form-label required" data-i18n="label_first_name">First Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" 
                                           placeholder="First Name" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="last_name" class="form-label required" data-i18n="label_last_name">Last Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" 
                                           placeholder="Last Name" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="phone" class="form-label required" data-i18n="label_phone">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                    <input type="text" class="form-control" id="phone" name="phone" 
                                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                                           placeholder="+60123456789" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="date_of_birth" class="form-label required" data-i18n="label_dob">Date of Birth</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="gender" class="form-label required" data-i18n="label_gender">Gender</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-gender-ambiguous"></i></span>
                                    <select class="form-control" id="gender" name="gender" required>
                                        <option value="" data-i18n="select_gender">Select Gender</option>
                                        <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?> data-i18n="gender_male">Male</option>
                                        <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?> data-i18n="gender_female">Female</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="nationality_id" class="form-label required" data-i18n="label_nationality">Nationality</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-globe"></i></span>
                                    <select class="form-control" id="nationality_id" name="nationality_id" required>
                                        <option value="" data-i18n="select_country">Select Country</option>
                                        <?php while($country = $countries_result->fetch_assoc()): ?>
                                            <option value="<?php echo $country['country_id']; ?>" 
                                                <?php echo (isset($_POST['nationality_id']) && $_POST['nationality_id'] == $country['country_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($country['country_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="passport_no" class="form-label" data-i18n="label_passport">Passport Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-passport"></i></span>
                                    <input type="text" class="form-control" id="passport_no" name="passport_no" 
                                           value="<?php echo isset($_POST['passport_no']) ? htmlspecialchars($_POST['passport_no']) : ''; ?>" 
                                           placeholder="A1234567">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="emergency_contact" class="form-label" data-i18n="label_emergency">Emergency Contact</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                    <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                           value="<?php echo isset($_POST['emergency_contact']) ? htmlspecialchars($_POST['emergency_contact']) : ''; ?>" 
                                           data-i18n-ph="ph_emergency" placeholder="Emergency phone number">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="step-buttons">
                        <div></div>
                        <button type="button" class="btn btn-step btn-next" onclick="nextStep()">
                            <span data-i18n="btn_next">Next</span> <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Step 2: Academic Information -->
                <div class="step-content" id="step2">
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon"><i class="bi bi-mortarboard"></i></div>
                            <h2 class="section-title" data-i18n="academic_info_title">Academic Information</h2>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="school_id" class="form-label required" data-i18n="label_school">School</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-building"></i></span>
                                    <select class="form-control" id="school_id" name="school_id" required>
                                        <option value="" data-i18n="select_school">Select School</option>
                                        <?php while($school = $schools_result->fetch_assoc()): ?>
                                            <option value="<?php echo $school['school_id']; ?>" 
                                                <?php echo (isset($_POST['school_id']) && $_POST['school_id'] == $school['school_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($school['school_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="program_id" class="form-label required" data-i18n="label_program">Program</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-journal"></i></span>
                                    <select class="form-control" id="program_id" name="program_id" required>
                                        <option value="" data-i18n="select_program">Select Program</option>
                                        <?php while($program = $programs_result->fetch_assoc()): ?>
                                            <option value="<?php echo $program['program_id']; ?>" 
                                                <?php echo (isset($_POST['program_id']) && $_POST['program_id'] == $program['program_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($program['program_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="student_type" class="form-label required" data-i18n="label_student_type">Student Type</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                    <select class="form-control" id="student_type" name="student_type" required>
                                        <option value="" data-i18n="select_type">Select Type</option>
                                        <option value="PC" <?php echo (isset($_POST['student_type']) && $_POST['student_type'] == 'PC') ? 'selected' : ''; ?> data-i18n="type_pc">Pre-College</option>
                                        <option value="UG" <?php echo (isset($_POST['student_type']) && $_POST['student_type'] == 'UG') ? 'selected' : ''; ?> data-i18n="type_ug">Undergraduate</option>
                                        <option value="PG" <?php echo (isset($_POST['student_type']) && $_POST['student_type'] == 'PG') ? 'selected' : ''; ?> data-i18n="type_pg">Post-Graduate</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="address" class="form-label" data-i18n="label_address">Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                    <textarea class="form-control" id="address" name="address" rows="1" 
                                              data-i18n-ph="ph_address" placeholder="Current address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="step-buttons">
                        <button type="button" class="btn btn-step btn-prev" onclick="prevStep()">
                            <i class="bi bi-arrow-left"></i> <span data-i18n="btn_previous">Previous</span>
                        </button>
                        <button type="button" class="btn btn-step btn-next" onclick="nextStep()">
                            <span data-i18n="btn_next">Next</span> <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Step 3: Account Setup -->
                <div class="step-content" id="step3">
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon"><i class="bi bi-shield-lock"></i></div>
                            <h2 class="section-title" data-i18n="account_security_title">Account Security</h2>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label required" data-i18n="label_password">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           data-i18n-ph="ph_password" placeholder="Create a strong password" required>
                                    <span class="input-group-text password-toggle" onclick="togglePassword('password')">
                                        <i class="bi bi-eye-slash"></i>
                                    </span>
                                </div>
                                <small class="text-muted" data-i18n="password_hint">Minimum 6 characters</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label required" data-i18n="label_confirm_password">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           data-i18n-ph="ph_confirm_password" placeholder="Confirm your password" required>
                                    <span class="input-group-text password-toggle" onclick="togglePassword('confirm_password')">
                                        <i class="bi bi-eye-slash"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="terms-checkbox">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                        <label class="form-check-label" for="terms">
                                            <strong data-i18n="terms_label">I agree to the Terms and Conditions</strong>
                                        </label>
                                    </div>
                                    <div class="terms-content">
                                        <p data-i18n="terms_intro">By registering, you agree to:</p>
                                        <ul>
                                            <li data-i18n="terms_point1">Provide accurate and complete information</li>
                                            <li data-i18n="terms_point2">Maintain the confidentiality of your account credentials</li>
                                            <li data-i18n="terms_point3">Comply with AIU's policies and regulations</li>
                                            <li data-i18n="terms_point4">Keep your visa and passport information updated</li>
                                            <li data-i18n="terms_point5">Notify the International Student Services Unit of any changes to your status</li>
                                        </ul>
                                        <p data-i18n="terms_conclusion">You also consent to the collection and processing of your personal data for academic and administrative purposes.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="step-buttons">
                        <button type="button" class="btn btn-step btn-prev" onclick="prevStep()">
                            <i class="bi bi-arrow-left"></i> <span data-i18n="btn_previous">Previous</span>
                        </button>
                        <button type="submit" class="btn btn-step btn-submit">
                            <i class="bi bi-check-circle"></i> <span data-i18n="btn_register">Complete Registration</span>
                        </button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <p>
                    <span data-i18n="have_account">Already have an account?</span>
                    <a href="login.php" class="text-decoration-none fw-bold" style="color: var(--primary-blue);" data-i18n="login_here">Login here</a>
                </p>
            </div>
        
        <div class="university-watermark" data-i18n="uni_name">
            Albukhary International University | International Student Services Unit
        </div>
    </div>

        <!-- University Logo Script -->
    <script>
        // Handle logo fallback if image fails to load
        document.addEventListener('DOMContentLoaded', function() {
            const logo = document.querySelector('.university-logo');
            const fallback = document.getElementById('logoFallback');
            
            if (logo) {
                logo.onerror = function() {
                    this.style.display = 'none';
                    if (fallback) {
                        fallback.style.display = 'flex';
                        fallback.classList.remove('d-none');
                    }
                };
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ---------- i18n translations ----------
        const translations = {
            en: {
                page_title: "Register - ISSU Visa Management System",
                brand_title: "ISSU Student Registration",
                welcome_subtitle: "International Student Services Unit - New Student Registration",
                step1_label: "Personal Info",
                step2_label: "Academic Info",
                step3_label: "Account Setup",
                personal_info_title: "Personal Information",
                academic_info_title: "Academic Information",
                account_security_title: "Account Security",
                label_student_id: "Student ID",
                label_email: "Email Address",
                label_first_name: "First Name",
                label_last_name: "Last Name",
                label_phone: "Phone Number",
                label_dob: "Date of Birth",
                label_gender: "Gender",
                label_nationality: "Nationality",
                label_passport: "Passport Number",
                label_emergency: "Emergency Contact",
                label_school: "School",
                label_program: "Program",
                label_student_type: "Student Type",
                label_address: "Address",
                label_password: "Password",
                label_confirm_password: "Confirm Password",
                label_terms: "Terms and Conditions",
                email_hint: "Use your official AIU email",
                password_hint: "Minimum 6 characters",
                select_gender: "Select Gender",
                gender_male: "Male",
                gender_female: "Female",
                select_country: "Select Country",
                select_school: "Select School",
                select_program: "Select Program",
                select_type: "Select Type",
                type_pc: "Pre-College",
                type_ug: "Undergraduate",
                type_pg: "Post-Graduate",
                ph_emergency: "Emergency phone number",
                ph_address: "Current address",
                ph_password: "Create a strong password",
                ph_confirm_password: "Confirm your password",
                terms_label: "I agree to the Terms and Conditions",
                terms_intro: "By registering, you agree to:",
                terms_point1: "Provide accurate and complete information",
                terms_point2: "Maintain the confidentiality of your account credentials",
                terms_point3: "Comply with AIU's policies and regulations",
                terms_point4: "Keep your visa and passport information updated",
                terms_point5: "Notify the International Student Services Unit of any changes to your status",
                terms_conclusion: "You also consent to the collection and processing of your personal data for academic and administrative purposes.",
                btn_next: "Next",
                btn_previous: "Previous",
                btn_register: "Complete Registration",
                btn_submit: "Submit Registration",
                have_account: "Already have an account?",
                login_here: "Login here",
                go_to_login: "Go to Login",
                uni_name: "Albukhary International University | International Student Services Unit"
            },
            ms: {
                page_title: "Daftar - Sistem Pengurusan Visa ISSU",
                brand_title: "Pendaftaran Pelajar ISSU",
                welcome_subtitle: "Unit Perkhidmatan Pelajar Antarabangsa - Pendaftaran Pelajar Baru",
                step1_label: "Maklumat Peribadi",
                step2_label: "Maklumat Akademik",
                step3_label: "Penyediaan Akaun",
                personal_info_title: "Maklumat Peribadi",
                academic_info_title: "Maklumat Akademik",
                account_security_title: "Keselamatan Akaun",
                label_student_id: "ID Pelajar",
                label_email: "Alamat E-mel",
                label_first_name: "Nama Pertama",
                label_last_name: "Nama Akhir",
                label_phone: "Nombor Telefon",
                label_dob: "Tarikh Lahir",
                label_gender: "Jantina",
                label_nationality: "Kewarganegaraan",
                label_passport: "Nombor Pasport",
                label_emergency: "Hubungan Kecemasan",
                label_school: "Sekolah",
                label_program: "Program",
                label_student_type: "Jenis Pelajar",
                label_address: "Alamat",
                label_password: "Kata Laluan",
                label_confirm_password: "Sahkan Kata Laluan",
                label_terms: "Terma dan Syarat",
                email_hint: "Gunakan e-mel rasmi AIU anda",
                password_hint: "Minimum 6 aksara",
                select_gender: "Pilih Jantina",
                gender_male: "Lelaki",
                gender_female: "Perempuan",
                select_country: "Pilih Negara",
                select_school: "Pilih Sekolah",
                select_program: "Pilih Program",
                select_type: "Pilih Jenis",
                type_pc: "Pra-Kolej",
                type_ug: "Ijazah Sarjana Muda",
                type_pg: "Pascasiswazah",
                ph_emergency: "Nombor telefon kecemasan",
                ph_address: "Alamat semasa",
                ph_password: "Cipta kata laluan yang kuat",
                ph_confirm_password: "Sahkan kata laluan anda",
                terms_label: "Saya bersetuju dengan Terma dan Syarat",
                terms_intro: "Dengan mendaftar, anda bersetuju untuk:",
                terms_point1: "Memberikan maklumat yang tepat dan lengkap",
                terms_point2: "Menjaga kerahsiaan kelayakan akaun anda",
                terms_point3: "Mematuhi polisi dan peraturan AIU",
                terms_point4: "Mengemas kini maklumat visa dan pasport anda",
                terms_point5: "Memberitahu Unit Perkhidmatan Pelajar Antarabangsa tentang sebarang perubahan status anda",
                terms_conclusion: "Anda juga bersetuju dengan pengumpulan dan pemprosesan data peribadi anda untuk tujuan akademik dan pentadbiran.",
                btn_next: "Seterusnya",
                btn_previous: "Sebelumnya",
                btn_register: "Selesai Pendaftaran",
                btn_submit: "Hantar Pendaftaran",
                have_account: "Sudah mempunyai akaun?",
                login_here: "Log masuk di sini",
                go_to_login: "Pergi ke Log Masuk",
                uni_name: "Universiti Antarabangsa Albukhary | Unit Perkhidmatan Pelajar Antarabangsa"
            },
            id: {
                page_title: "Daftar - Sistem Manajemen Visa ISSU",
                brand_title: "Pendaftaran Mahasiswa ISSU",
                welcome_subtitle: "Unit Layanan Mahasiswa Internasional - Pendaftaran Mahasiswa Baru",
                step1_label: "Info Pribadi",
                step2_label: "Info Akademik",
                step3_label: "Pengaturan Akun",
                personal_info_title: "Informasi Pribadi",
                academic_info_title: "Informasi Akademik",
                account_security_title: "Keamanan Akun",
                label_student_id: "ID Mahasiswa",
                label_email: "Alamat Email",
                label_first_name: "Nama Depan",
                label_last_name: "Nama Belakang",
                label_phone: "Nomor Telepon",
                label_dob: "Tanggal Lahir",
                label_gender: "Jenis Kelamin",
                label_nationality: "Kewarganegaraan",
                label_passport: "Nomor Paspor",
                label_emergency: "Kontak Darurat",
                label_school: "Sekolah",
                label_program: "Program",
                label_student_type: "Jenis Mahasiswa",
                label_address: "Alamat",
                label_password: "Kata Sandi",
                label_confirm_password: "Konfirmasi Kata Sandi",
                label_terms: "Syarat dan Ketentuan",
                email_hint: "Gunakan email resmi AIU Anda",
                password_hint: "Minimal 6 karakter",
                select_gender: "Pilih Jenis Kelamin",
                gender_male: "Laki-laki",
                gender_female: "Perempuan",
                select_country: "Pilih Negara",
                select_school: "Pilih Sekolah",
                select_program: "Pilih Program",
                select_type: "Pilih Jenis",
                type_pc: "Pra-Kuliah",
                type_ug: "Sarjana",
                type_pg: "Pascasarjana",
                ph_emergency: "Nomor telepon darurat",
                ph_address: "Alamat saat ini",
                ph_password: "Buat kata sandi yang kuat",
                ph_confirm_password: "Konfirmasi kata sandi Anda",
                terms_label: "Saya setuju dengan Syarat dan Ketentuan",
                terms_intro: "Dengan mendaftar, Anda setuju untuk:",
                terms_point1: "Memberikan informasi yang akurat dan lengkap",
                terms_point2: "Menjaga kerahasiaan kredensial akun Anda",
                terms_point3: "Mematuhi kebijakan dan peraturan AIU",
                terms_point4: "Memperbarui informasi visa dan paspor Anda",
                terms_point5: "Memberitahu Unit Layanan Mahasiswa Internasional tentang perubahan status Anda",
                terms_conclusion: "Anda juga menyetujui pengumpulan dan pemrosesan data pribadi Anda untuk tujuan akademik dan administratif.",
                btn_next: "Selanjutnya",
                btn_previous: "Sebelumnya",
                btn_register: "Selesaikan Pendaftaran",
                btn_submit: "Kirim Pendaftaran",
                have_account: "Sudah punya akun?",
                login_here: "Masuk di sini",
                go_to_login: "Pergi ke Masuk",
                uni_name: "Universitas Internasional Albukhary | Unit Layanan Mahasiswa Internasional"
            },
            my: {
                page_title: "မှတ်ပုံတင်ရန် - ISSU ဗီဇာစီမံခန့်ခွဲမှုစနစ်",
                brand_title: "ISSU ကျောင်းသားမှတ်ပုံတင်ခြင်း",
                welcome_subtitle: "နိုင်ငံတကာကျောင်းသားဝန်ဆောင်မှုဌာန - ကျောင်းသားအသစ်မှတ်ပုံတင်ခြင်း",
                step1_label: "ကိုယ်ရေးအချက်အလက်",
                step2_label: "ပညာရေးအချက်အလက်",
                step3_label: "အကောင့်တည်ဆောက်ခြင်း",
                personal_info_title: "ကိုယ်ရေးအချက်အလက်",
                academic_info_title: "ပညာရေးအချက်အလက်",
                account_security_title: "အကောင့်လုံခြုံရေး",
                label_student_id: "ကျောင်းသားနံပါတ်",
                label_email: "အီးမေးလ်လိပ်စာ",
                label_first_name: "အမည်ရှေ့",
                label_last_name: "အမည်နောက်",
                label_phone: "ဖုန်းနံပါတ်",
                label_dob: "မွေးသက္ကရာဇ်",
                label_gender: "လိင်",
                label_nationality: "နိုင်ငံသား",
                label_passport: "နိုင်ငံကူးလက်မှတ်နံပါတ်",
                label_emergency: "အရေးပေါ်ဆက်သွယ်ရန်",
                label_school: "ကျောင်း",
                label_program: "ပရိုဂရမ်",
                label_student_type: "ကျောင်းသားအမျိုးအစား",
                label_address: "လိပ်စာ",
                label_password: "စကားဝှက်",
                label_confirm_password: "စကားဝှက်အတည်ပြုရန်",
                label_terms: "စည်းကမ်းချက်များနှင့်သတ်မှတ်ချက်များ",
                email_hint: "သင်၏တရားဝင် AIU အီးမေးလ်ကိုအသုံးပြုပါ",
                password_hint: "အနည်းဆုံး စာလုံး ၆ လုံး",
                select_gender: "လိင် ရွေးချယ်ပါ",
                gender_male: "ကျား",
                gender_female: "မ",
                select_country: "နိုင်ငံ ရွေးချယ်ပါ",
                select_school: "ကျောင်း ရွေးချယ်ပါ",
                select_program: "ပရိုဂရမ် ရွေးချယ်ပါ",
                select_type: "အမျိုးအစား ရွေးချယ်ပါ",
                type_pc: "ကောလိပ်မတက်မီ",
                type_ug: "ဘွဲ့လွန်",
                type_pg: "ဘွဲ့လွန်ကျောင်းသား",
                ph_emergency: "အရေးပေါ်ဖုန်းနံပါတ်",
                ph_address: "လက်ရှိလိပ်စာ",
                ph_password: "ခိုင်မာသောစကားဝှက်ဖန်တီးပါ",
                ph_confirm_password: "သင်၏စကားဝှက်ကိုအတည်ပြုပါ",
                terms_label: "ကျွန်ုပ်သည် စည်းကမ်းချက်များနှင့် သတ်မှတ်ချက်များကို သဘောတူပါသည်",
                terms_intro: "မှတ်ပုံတင်ခြင်းဖြင့် သင်သည် အောက်ပါတို့ကို သဘောတူပါသည်-",
                terms_point1: "တိကျမှန်ကန်ပြီး ပြည့်စုံသော အချက်အလက်များကို ပေးပါ",
                terms_point2: "သင်၏အကောင့်အထောက်အထားများ၏ လျှို့ဝှက်မှုကို ထိန်းသိမ်းပါ",
                terms_point3: "AIU ၏မူဝါဒများနှင့် စည်းမျဉ်းများကို လိုက်နာပါ",
                terms_point4: "သင်၏ဗီဇာနှင့် နိုင်ငံကူးလက်မှတ်အချက်အလက်များကို မွမ်းမံပါ",
                terms_point5: "သင်၏အခြေအနေပြောင်းလဲမှုများကို နိုင်ငံတကာကျောင်းသားဝန်ဆောင်မှုဌာနသို့ အသိပေးပါ",
                terms_conclusion: "သင်၏ကိုယ်ရေးကိုယ်တာအချက်အလက်များကို ပညာရေးနှင့် အုပ်ချုပ်ရေးဆိုင်ရာ ရည်ရွယ်ချက်များအတွက် စုဆောင်းခြင်းနှင့် ကိုင်တွယ်ခြင်းကိုလည်း သဘောတူပါသည်။",
                btn_next: "နောက်တစ်ခု",
                btn_previous: "ရှေ့တစ်ခု",
                btn_register: "မှတ်ပုံတင်ခြင်းပြီးစီးရန်",
                btn_submit: "မှတ်ပုံတင်ခြင်းတင်ပြရန်",
                have_account: "အကောင့်ရှိပြီးသားလား?",
                login_here: "ဤနေရာတွင် ဝင်ရောက်ပါ",
                go_to_login: "ဝင်ရောက်ရန်သွားရန်",
                uni_name: "Albukhary အပြည်ပြည်ဆိုင်ရာတက္ကသိုလ် | နိုင်ငံတကာကျောင်းသားဝန်ဆောင်မှုဌာန"
            },
            ar: {
                page_title: "تسجيل - نظام إدارة تأشيرات ISSU",
                brand_title: "تسجيل طالب ISSU",
                welcome_subtitle: "وحدة خدمات الطلاب الدوليين - تسجيل طالب جديد",
                step1_label: "المعلومات الشخصية",
                step2_label: "المعلومات الأكاديمية",
                step3_label: "إعداد الحساب",
                personal_info_title: "المعلومات الشخصية",
                academic_info_title: "المعلومات الأكاديمية",
                account_security_title: "أمان الحساب",
                label_student_id: "رقم الطالب",
                label_email: "عنوان البريد الإلكتروني",
                label_first_name: "الاسم الأول",
                label_last_name: "اسم العائلة",
                label_phone: "رقم الهاتف",
                label_dob: "تاريخ الميلاد",
                label_gender: "الجنس",
                label_nationality: "الجنسية",
                label_passport: "رقم جواز السفر",
                label_emergency: "جهة اتصال الطوارئ",
                label_school: "المدرسة",
                label_program: "البرنامج",
                label_student_type: "نوع الطالب",
                label_address: "العنوان",
                label_password: "كلمة المرور",
                label_confirm_password: "تأكيد كلمة المرور",
                label_terms: "الشروط والأحكام",
                email_hint: "استخدم بريد AIU الرسمي الخاص بك",
                password_hint: "6 أحرف على الأقل",
                select_gender: "اختر الجنس",
                gender_male: "ذكر",
                gender_female: "أنثى",
                select_country: "اختر الدولة",
                select_school: "اختر المدرسة",
                select_program: "اختر البرنامج",
                select_type: "اختر النوع",
                type_pc: "ما قبل الكلية",
                type_ug: "المرحلة الجامعية",
                type_pg: "الدراسات العليا",
                ph_emergency: "رقم هاتف الطوارئ",
                ph_address: "العنوان الحالي",
                ph_password: "أنشئ كلمة مرور قوية",
                ph_confirm_password: "قم بتأكيد كلمة المرور الخاصة بك",
                terms_label: "أوافق على الشروط والأحكام",
                terms_intro: "بالتسجيل، فإنك توافق على:",
                terms_point1: "تقديم معلومات دقيقة وكاملة",
                terms_point2: "الحفاظ على سرية بيانات اعتماد حسابك",
                terms_point3: "الامتثال لسياسات وأنظمة AIU",
                terms_point4: "تحديث معلومات التأشيرة وجواز السفر الخاصة بك",
                terms_point5: "إخطار وحدة خدمات الطلاب الدوليين بأي تغييرات في حالتك",
                terms_conclusion: "أنت توافق أيضًا على جمع ومعالجة بياناتك الشخصية للأغراض الأكاديمية والإدارية.",
                btn_next: "التالي",
                btn_previous: "السابق",
                btn_register: "إكمال التسجيل",
                btn_submit: "إرسال التسجيل",
                have_account: "هل لديك حساب بالفعل؟",
                login_here: "تسجيل الدخول هنا",
                go_to_login: "اذهب لتسجيل الدخول",
                uni_name: "جامعة البخاري الدولية | وحدة خدمات الطلاب الدوليين"
            },
            si: {
                page_title: "ලියාපදිංචි වන්න - ISSU වීසා කළමනාකරණ පද්ධතිය",
                brand_title: "ISSU ශිෂ්‍ය ලියාපදිංචි කිරීම",
                welcome_subtitle: "ජාත්‍යන්තර ශිෂ්‍ය සේවා ඒකකය - නව ශිෂ්‍ය ලියාපදිංචි කිරීම",
                step1_label: "පෞද්ගලික තොරතුරු",
                step2_label: "ශාස්ත්‍රීය තොරතුරු",
                step3_label: "ගිණුම් සැකසුම",
                personal_info_title: "පෞද්ගලික තොරතුරු",
                academic_info_title: "ශාස්ත්‍රීය තොරතුරු",
                account_security_title: "ගිණුම් ආරක්ෂාව",
                label_student_id: "ශිෂ්‍ය අංකය",
                label_email: "විද්‍යුත් තැපැල් ලිපිනය",
                label_first_name: "මුල් නම",
                label_last_name: "අවසාන නම",
                label_phone: "දුරකථන අංකය",
                label_dob: "උපන් දිනය",
                label_gender: "ලිංගය",
                label_nationality: "ජාතිකත්වය",
                label_passport: "පාස්පෝර්ට් අංකය",
                label_emergency: "හදිසි සම්බන්ධතාව",
                label_school: "විද්‍යාලය",
                label_program: "වැඩසටහන",
                label_student_type: "ශිෂ්‍ය වර්ගය",
                label_address: "ලිපිනය",
                label_password: "මුරපදය",
                label_confirm_password: "මුරපදය තහවුරු කරන්න",
                label_terms: "නියමයන් සහ කොන්දේසි",
                email_hint: "ඔබගේ නිල AIU විද්‍යුත් තැපෑල භාවිතා කරන්න",
                password_hint: "අවම අක්ෂර 6 ක්",
                select_gender: "ලිංගය තෝරන්න",
                gender_male: "පිරිමි",
                gender_female: "ගැහැණු",
                select_country: "රට තෝරන්න",
                select_school: "විද්‍යාලය තෝරන්න",
                select_program: "වැඩසටහන තෝරන්න",
                select_type: "වර්ගය තෝරන්න",
                type_pc: "පූර්ව විද්‍යාල",
                type_ug: "උපාධි පෙර සූදානම්",
                type_pg: "පශ්චාත් උපාධි",
                ph_emergency: "හදිසි දුරකථන අංකය",
                ph_address: "වර්තමාන ලිපිනය",
                ph_password: "ශක්තිමත් මුරපදයක් සාදන්න",
                ph_confirm_password: "ඔබගේ මුරපදය තහවුරු කරන්න",
                terms_label: "මම නියමයන් සහ කොන්දේසි වලට එකඟ වෙමි",
                terms_intro: "ලියාපදිංචි වීමෙන්, ඔබ පහත සඳහන් දේ සඳහා එකඟ වේ:",
                terms_point1: "නිවැරදි සහ සම්පූර්ණ තොරතුරු සපයන්න",
                terms_point2: "ඔබේ ගිණුම් අක්තපත්‍රවල රහස්‍යතාව රැකගන්න",
                terms_point3: "AIU හි ප්‍රතිපත්ති සහ රෙගුලාසි වලට අනුගත වන්න",
                terms_point4: "ඔබේ වීසා සහ පාස්පෝර්ට් තොරතුරු යාවත්කාලීන කරගන්න",
                terms_point5: "ඔබේ තත්ත්වයේ ඕනෑම වෙනසක් ජාත්‍යන්තර ශිෂ්‍ය සේවා ඒකකයට දන්වන්න",
                terms_conclusion: "ශාස්ත්‍රීය සහ පරිපාලන අරමුණු සඳහා ඔබේ පෞද්ගලික දත්ත එකතු කිරීම සහ සැකසීමට ද ඔබ එකඟ වේ.",
                btn_next: "ඊළඟ",
                btn_previous: "කලින්",
                btn_register: "ලියාපදිංචි කිරීම සම්පූර්ණ කරන්න",
                btn_submit: "ලියාපදිංචි කිරීම ඉදිරිපත් කරන්න",
                have_account: "දැනටමත් ගිණුමක් තිබේද?",
                login_here: "මෙහි පිවිසෙන්න",
                go_to_login: "පිවිසීමට යන්න",
                uni_name: "Albukhary ජාත්‍යන්තර විශ්වවිද්‍යාලය | ජාත්‍යන්තර ශිෂ්‍ය සේවා ඒකකය"
            }
        };

        function applyLanguage(lang) {
            const dict = translations[lang] || translations.en;

            // Update page title
            document.title = dict.page_title || translations.en.page_title;

            // Update text content
            document.querySelectorAll("[data-i18n]").forEach(el => {
                const key = el.getAttribute("data-i18n");
                if (dict[key]) {
                    // For RTL languages, add special handling if needed
                    if (lang === "ar" || lang === "my") {
                        el.style.textAlign = 'right';
                        el.style.direction = (lang === "ar") ? 'rtl' : 'ltr';
                    }
                    el.textContent = dict[key];
                }
            });

            // Update placeholders
            document.querySelectorAll("[data-i18n-ph]").forEach(el => {
                const key = el.getAttribute("data-i18n-ph");
                if (dict[key]) el.setAttribute("placeholder", dict[key]);
            });

            // Update option values for dropdowns
            document.querySelectorAll("option[data-i18n]").forEach(option => {
                const key = option.getAttribute("data-i18n");
                if (dict[key]) option.textContent = dict[key];
            });

            // RTL for Arabic
            if (lang === "ar") {
                document.documentElement.setAttribute("dir", "rtl");
                document.documentElement.lang = "ar";
                document.querySelectorAll('.form-control, .input-group-text, select, textarea, .form-label, .section-title, .step-label, .logo-text, .logo-subtitle').forEach(el => {
                    el.style.textAlign = 'right';
                    el.style.direction = 'rtl';
                });
                // Adjust padding for RTL
                document.querySelectorAll('.input-group').forEach(group => {
                    const text = group.querySelector('.input-group-text');
                    if (text && text.parentElement === group) {
                        text.style.borderRadius = '0 12px 12px 0';
                        const input = group.querySelector('.form-control');
                        if (input) {
                            input.style.borderRadius = '12px 0 0 12px';
                        }
                    }
                });
            } else {
                document.documentElement.setAttribute("dir", "ltr");
                document.documentElement.lang = lang;
                document.querySelectorAll('.form-control, .input-group-text, select, textarea, .form-label, .section-title, .step-label, .logo-text, .logo-subtitle').forEach(el => {
                    el.style.textAlign = 'left';
                    el.style.direction = 'ltr';
                });
                // Reset padding for LTR
                document.querySelectorAll('.input-group').forEach(group => {
                    const text = group.querySelector('.input-group-text');
                    if (text && text.parentElement === group) {
                        text.style.borderRadius = '12px 0 0 12px';
                        const input = group.querySelector('.form-control');
                        if (input) {
                            input.style.borderRadius = '0 12px 12px 0';
                        }
                    }
                });
            }

            // For Myanmar (Burmese)
            if (lang === "my") {
                document.querySelectorAll('.form-control, .input-group-text, select, textarea, .form-label, .section-title, .step-label, .logo-text, .logo-subtitle').forEach(el => {
                    el.style.fontFamily = "'Padauk', 'Myanmar3', 'Poppins', sans-serif";
                });
            } else {
                document.querySelectorAll('.form-control, .input-group-text, select, textarea, .form-label, .section-title, .step-label, .logo-text, .logo-subtitle').forEach(el => {
                    el.style.fontFamily = "'Poppins', sans-serif";
                });
            }

            localStorage.setItem("issu_lang", lang);
        }

        const savedLang = localStorage.getItem("issu_lang") || "en";
        document.getElementById("langSelect").value = savedLang;
        applyLanguage(savedLang);

        document.getElementById("langSelect").addEventListener("change", function() {
            applyLanguage(this.value);
        });

        let currentStep = 1;
        const totalSteps = 3;
        
        function updateProgress() {
            // Update step indicators
            document.querySelectorAll('.step').forEach((step, index) => {
                step.classList.remove('active', 'completed');
                const stepNum = parseInt(step.getAttribute('data-step'));
                
                if (stepNum < currentStep) {
                    step.classList.add('completed');
                } else if (stepNum === currentStep) {
                    step.classList.add('active');
                }
            });
            
            // Show current step content
            document.querySelectorAll('.step-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(`step${currentStep}`).classList.add('active');
        }
        
        function nextStep() {
            // Validate current step before proceeding
            if (validateStep(currentStep)) {
                if (currentStep < totalSteps) {
                    currentStep++;
                    updateProgress();
                }
            }
        }
        
        function prevStep() {
            if (currentStep > 1) {
                currentStep--;
                updateProgress();
            }
        }
        
        function validateStep(step) {
            let isValid = true;
            const stepElement = document.getElementById(`step${step}`);
            
            // Check all required fields in current step
            const requiredFields = stepElement.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Special validations for step 1
            if (step === 1) {
                const email = document.getElementById('email');
                if (email.value && !validateEmail(email.value)) {
                    email.classList.add('is-invalid');
                    isValid = false;
                }
                
                const phone = document.getElementById('phone');
                if (phone.value && !validatePhone(phone.value)) {
                    phone.classList.add('is-invalid');
                    isValid = false;
                }
            }
            
            // Special validations for step 3
            if (step === 3) {
                const password = document.getElementById('password');
                const confirmPassword = document.getElementById('confirm_password');
                
                if (password.value.length < 6) {
                    password.classList.add('is-invalid');
                    isValid = false;
                }
                
                if (password.value !== confirmPassword.value) {
                    confirmPassword.classList.add('is-invalid');
                    isValid = false;
                }
                
                const terms = document.getElementById('terms');
                if (!terms.checked) {
                    terms.classList.add('is-invalid');
                    isValid = false;
                } else {
                    terms.classList.remove('is-invalid');
                }
            }
            
            return isValid;
        }
        
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        function validatePhone(phone) {
            // Basic phone validation - accepts +, numbers, spaces, dashes
            const re = /^[\d\s\-\+\(\)]{10,}$/;
            return re.test(phone);
        }
        
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            } else {
                field.type = 'password';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            }
        }
        
        // Initialize form validation on submit
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            // Validate all steps before submission
            for (let step = 1; step <= totalSteps; step++) {
                if (!validateStep(step)) {
                    e.preventDefault();
                    // Go to first invalid step
                    currentStep = step;
                    updateProgress();
                    return;
                }
            }
            
            // If all validation passes, show confirmation
            const currentLang = localStorage.getItem("issu_lang") || "en";
            const dict = translations[currentLang] || translations.en;
            const confirmMsg = "Are you sure you want to submit your registration? Please verify all information is correct.";
            
            if (!confirm(confirmMsg)) {
                e.preventDefault();
            }
        });
        
        // Real-time validation for fields
        document.querySelectorAll('input, select').forEach(field => {
            field.addEventListener('blur', function() {
                if (this.hasAttribute('required') && !this.value.trim()) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
                
                // Special validation for email
                if (this.id === 'email' && this.value) {
                    if (!validateEmail(this.value)) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                }
                
                // Special validation for password match
                if ((this.id === 'password' || this.id === 'confirm_password') && 
                    document.getElementById('password').value && 
                    document.getElementById('confirm_password').value) {
                    
                    const password = document.getElementById('password');
                    const confirmPassword = document.getElementById('confirm_password');
                    
                    if (password.value !== confirmPassword.value) {
                        confirmPassword.classList.add('is-invalid');
                    } else {
                        confirmPassword.classList.remove('is-invalid');
                    }
                }
            });
            
            field.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });
        
        // Initialize date picker max date (must be at least 16 years old)
        const today = new Date();
        const minDate = new Date(today.getFullYear() - 60, today.getMonth(), today.getDate());
        const maxDate = new Date(today.getFullYear() - 16, today.getMonth(), today.getDate());
        
        document.getElementById('date_of_birth').setAttribute('max', maxDate.toISOString().split('T')[0]);
        document.getElementById('date_of_birth').setAttribute('min', minDate.toISOString().split('T')[0]);
        
        // Add click handler for step indicators
        document.querySelectorAll('.step').forEach(step => {
            step.addEventListener('click', function() {
                const stepNum = parseInt(this.getAttribute('data-step'));
                // Only allow navigation to previous steps
                if (stepNum <= currentStep) {
                    currentStep = stepNum;
                    updateProgress();
                }
            });
        });
        
        // Auto-focus first field
        document.getElementById('student_id').focus();
    </script>
</body>
</html>
