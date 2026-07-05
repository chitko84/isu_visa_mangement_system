<?php
require_once __DIR__ . '/includes/functions.php';
secure_session_start();

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'student') {
        header("Location: student/dashboard.php");
    } else {
        header("Location: staff/dashboard.php");
    }
    exit();
}

// Handle login form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $role = isset($_POST['role']) ? trim($_POST['role']) : '';
    
    if (empty($email) || empty($password) || empty($role)) {
        $error = "Please enter email, password, and select a role.";
    } else {
        if ($role === 'student') {
            // Check student login - Now with password column
            $query = "SELECT student_id, first_name, last_name, email, program_id, status, password 
                     FROM student 
                     WHERE email = ?";
            
            if ($stmt = $conn->prepare($query)) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $student = $result->fetch_assoc();
                    
                    // Check if student has a password set
                    if (empty($student['password'])) {
                        $error = "This account has no password set. Please contact ISSU staff.";
                    } else {
                        // Verify existing password
                        if (password_verify($password, $student['password'])) {
                            // Check if student is active
                            if ($student['status'] != 'Active') {
                                $error = "Your account is not active. Please contact support.";
                            } else {
                                session_regenerate_id(true);
                                $_SESSION['user_id'] = $student['student_id'];
                                $_SESSION['email'] = $student['email'];
                                $_SESSION['full_name'] = $student['first_name'] . ' ' . $student['last_name'];
                                $_SESSION['role'] = 'student';
                                $_SESSION['program_id'] = $student['program_id'];
                                $_SESSION['login_time'] = time();
                                
                                // Redirect to student dashboard
                                header("Location: student/dashboard.php");
                                exit();
                            }
                        } else {
                            $error = "Invalid email or password for student account.";
                        }
                    }
                } else {
                    $error = "No student account found with this email.";
                }
                $stmt->close();
            } else {
                $error = "Database query error. Please try again.";
            }
            
        } elseif ($role === 'staff') {
            // Check staff login
            $staff_query = "SELECT staff_id, first_name, last_name, email, role, password, status 
                           FROM staff 
                           WHERE email = ?";
            
            if ($stmt = $conn->prepare($staff_query)) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $staff = $result->fetch_assoc();
                    
                    // Check if staff has a password set
                    if (empty($staff['password'])) {
                        $error = "This account has no password set. Please contact the administrator.";
                    } else {
                        // Verify existing password
                        if (password_verify($password, $staff['password'])) {
                            // Check if staff is active
                            if (isset($staff['status']) && $staff['status'] != 'Active') {
                                $error = "Your account is not active. Please contact administrator.";
                            } else {
                                session_regenerate_id(true);
                                $_SESSION['user_id'] = $staff['staff_id'];
                                $_SESSION['email'] = $staff['email'];
                                $_SESSION['full_name'] = $staff['first_name'] . ' ' . $staff['last_name'];
                                $_SESSION['role'] = $staff['role'];
                                $_SESSION['login_time'] = time();
                                
                                // Redirect to staff dashboard
                                header("Location: staff/dashboard.php");
                                exit();
                            }
                        } else {
                            $error = "Invalid email or password for staff account.";
                        }
                    }
                } else {
                    $error = "No staff account found with this email.";
                }
                $stmt->close();
            } else {
                $error = "Database query error. Please try again.";
            }
        } else {
            $error = "Invalid role selected.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - ISSU Visa Management System</title>
    
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
        
        /* Background image - Same as register.php */
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
        
        .login-container {
            max-width: 450px;
            margin: 0 auto;
            padding: 2.5rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255, 255, 255, 0.35);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 1.5rem;
        }

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
        
        .form-label {
            font-weight: 600;
            color: var(--dark-blue);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
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
        
        .input-group-text {
            background: white;
            border: 2px solid var(--border-gray);
            color: var(--text-gray);
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .input-group .form-control { border-left: none; }
        .input-group .input-group-text { border-right: none; }
        
        .password-toggle {
            cursor: pointer;
            user-select: none;
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--btn-navy), var(--btn-black));
            color: white;
            border: none;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.25s ease;
            width: 100%;
            margin-top: 1rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(11, 15, 20, 0.25);
            filter: brightness(1.05);
        }
        
        /* Role Selector Styles */
        .role-selector {
            display: flex;
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .role-option {
            flex: 1;
            position: relative;
        }
        
        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .role-label {
            display: block;
            padding: 1.25rem 1rem;
            background-color: var(--light-blue);
            border: 2px solid #E0E0E0;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 100%;
            color: var(--text-gray);
        }
        
        .role-option input[type="radio"]:checked + .role-label {
            border-color: var(--primary-blue);
            background-color: rgba(14, 42, 71, 0.1);
            font-weight: 500;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(14, 42, 71, 0.15);
            color: var(--primary-blue);
        }
        
        .role-option input[type="radio"]:focus + .role-label {
            box-shadow: 0 0 0 0.25rem rgba(14, 42, 71, 0.25);
        }
        
        .role-icon {
            font-size: 1.75rem;
            margin-bottom: 0.75rem;
            color: var(--primary-blue);
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
        
        .muted-link {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
        }
        
        .muted-link:hover {
            text-decoration: underline;
            color: var(--dark-blue);
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
        
        .university-watermark {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: rgba(0, 0, 0, 0.35);
            font-style: italic;
        }
        
        .register-link {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        @media (max-width: 576px) {
            body { padding: 10px; background-attachment: scroll; }
            .login-container { padding: 1.5rem; }
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
            .logo-container {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            .logo-text-container {
                text-align: center;
            }
            .logo-text {
                font-size: 1.8rem;
            }
            .role-selector {
                flex-direction: column;
                gap: 0.75rem;
            }
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
    
    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="login-container w-100">
            <div class="login-header">
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
                        <h1 class="logo-text" data-i18n="brand_title">ISSU Login</h1>
                        <p class="logo-subtitle" data-i18n="welcome_subtitle">International Student Services Unit</p>
                    </div>
                </div>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-danger alert-custom">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['success']) && $_GET['success'] == 'registered'): ?>
                <div class="alert alert-success alert-custom">
                    <i class="bi bi-check-circle me-2"></i>
                    Registration successful! You can now login with your credentials.
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['logout']) && $_GET['logout'] == 'true'): ?>
                <div class="alert alert-success alert-custom">
                    <i class="bi bi-check-circle me-2"></i>
                    You have been successfully logged out.
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['session']) && $_GET['session'] == 'expired'): ?>
                <div class="alert alert-warning alert-custom">
                    <i class="bi bi-clock-history me-2"></i>
                    Your session has expired. Please login again.
                </div>
            <?php endif; ?>
            <?php if(isset($_GET['unauthorized']) && $_GET['unauthorized'] == '1'): ?>
                <div class="alert alert-warning alert-custom">
                    <i class="bi bi-shield-exclamation me-2"></i>
                    Please login with an account that has permission to open that page.
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="mb-3">
                    <label for="email" class="form-label" data-i18n="label_email">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               placeholder="student@aiu.edu.my" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label" data-i18n="label_password">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Enter your password" required>
                        <span class="input-group-text password-toggle" onclick="togglePassword()">
                            <i class="bi bi-eye-slash"></i>
                        </span>
                    </div>
                </div>
                
                <!-- Role Selector -->
                <div class="mb-3">
                    <label class="form-label" data-i18n="label_role">Login as</label>
                    <div class="role-selector">
                        <div class="role-option">
                            <input type="radio" id="role_student" name="role" value="student" required 
                                   <?php echo (isset($_POST['role']) && $_POST['role'] == 'student') ? 'checked' : ''; ?>>
                            <label for="role_student" class="role-label">
                                <i class="bi bi-person-fill role-icon"></i>
                                <div data-i18n="role_student">Student</div>
                            </label>
                        </div>
                        <div class="role-option">
                            <input type="radio" id="role_staff" name="role" value="staff" required
                                   <?php echo (isset($_POST['role']) && $_POST['role'] == 'staff') ? 'checked' : ''; ?>>
                            <label for="role_staff" class="role-label">
                                <i class="bi bi-person-badge-fill role-icon"></i>
                                <div data-i18n="role_staff">Staff</div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login mb-3">
                    <i class="bi bi-box-arrow-in-right me-2"></i>
                    <span data-i18n="btn_login">Login</span>
                </button>
                
                <div class="register-link">
                    <span class="text-muted" data-i18n="no_account">Don't have an account?</span>
                    <a href="register.php" class="muted-link ms-2" data-i18n="register_here">Register here</a>
                </div>
                
                <div class="text-center mt-3">
                    <span class="text-muted small" data-i18n="forgot_password">Forgot your password? Please contact ISSU staff.</span>
                </div>
            </form>
            
            <div class="university-watermark" data-i18n="uni_name">
                Albukhary International University | International Student Services Unit
            </div>
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
                
                // Preload the logo image
                const img = new Image();
                img.src = logo.src;
                img.onload = function() {
                    console.log('Logo loaded successfully');
                };
                img.onerror = function() {
                    console.log('Logo failed to load, showing fallback');
                    logo.style.display = 'none';
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
                brand_title: "ISSU Login",
                welcome_subtitle: "International Student Services Unit",
                label_email: "Email Address",
                label_password: "Password",
                label_role: "Login as",
                role_student: "Student",
                role_staff: "Staff",
                btn_login: "Login",
                no_account: "Don't have an account?",
                register_here: "Register here",
                forgot_password: "Forgot your password?",
                uni_name: "Albukhary International University | International Student Services Unit"
            },
            ms: {
                brand_title: "ISSU Log Masuk",
                welcome_subtitle: "Unit Perkhidmatan Pelajar Antarabangsa",
                label_email: "Alamat E-mel",
                label_password: "Kata Laluan",
                label_role: "Log Masuk sebagai",
                role_student: "Pelajar",
                role_staff: "Kakitangan",
                btn_login: "Log Masuk",
                no_account: "Tiada akaun?",
                register_here: "Daftar di sini",
                forgot_password: "Lupa kata laluan?",
                uni_name: "Universiti Antarabangsa Albukhary | Unit Perkhidmatan Pelajar Antarabangsa"
            },
            id: {
                brand_title: "ISSU Masuk",
                welcome_subtitle: "Unit Layanan Mahasiswa Internasional",
                label_email: "Alamat Email",
                label_password: "Kata Sandi",
                label_role: "Masuk sebagai",
                role_student: "Mahasiswa",
                role_staff: "Staf",
                btn_login: "Masuk",
                no_account: "Belum punya akun?",
                register_here: "Daftar di sini",
                forgot_password: "Lupa kata sandi?",
                uni_name: "Universitas Internasional Albukhary | Unit Layanan Mahasiswa Internasional"
            },
            my: {
                brand_title: "ISSU ဝင်ရောက်ရန်",
                welcome_subtitle: "အပြည်ပြည်ဆိုင်ရာ ကျောင်းသားဝန်ဆောင်မှု ဌာန",
                label_email: "အီးမေးလ်လိပ်စာ",
                label_password: "စကားဝှက်",
                label_role: "အနေနှင့် ဝင်ရောက်ရန်",
                role_student: "ကျောင်းသား",
                role_staff: "ဝန်ထမ်း",
                btn_login: "ဝင်ရောက်ရန်",
                no_account: "အကောင့်မရှိသေးဘူးလား?",
                register_here: "ဒီမှာ စာရင်းသွင်းပါ",
                forgot_password: "စကားဝှက်မေ့နေပြီလား?",
                uni_name: "Albukhary အပြည်ပြည်ဆိုင်ရာ တက္ကသိုလ် | အပြည်ပြည်ဆိုင်ရာ ကျောင်းသားဝန်ဆောင်မှု ဌာန"
            },
            ar: {
                brand_title: "تسجيل دخول ISSU",
                welcome_subtitle: "وحدة خدمات الطلاب الدوليين",
                label_email: "عنوان البريد الإلكتروني",
                label_password: "كلمة المرور",
                label_role: "تسجيل الدخول كـ",
                role_student: "طالب",
                role_staff: "موظف",
                btn_login: "تسجيل الدخول",
                no_account: "ليس لديك حساب؟",
                register_here: "سجل هنا",
                forgot_password: "نسيت كلمة المرور؟",
                uni_name: "جامعة البخاري الدولية | وحدة خدمات الطلاب الدوليين"
            },
            si: {
                brand_title: "ISSU පිවිසෙන්න",
                welcome_subtitle: "ජාත්‍යන්තර ශිෂ්‍ය සේවා ඒකකය",
                label_email: "ඊමේල් ලිපිනය",
                label_password: "මුරපදය",
                label_role: "ලෙස පිවිසෙන්න",
                role_student: "ශිෂ්‍ය",
                role_staff: "කාර්ය මණ්ඩලය",
                btn_login: "පිවිසෙන්න",
                no_account: "ගිණුමක් නැද්ද?",
                register_here: "මෙහි ලියාපදිංචි වන්න",
                forgot_password: "මුරපදය අමතක වුණාද?",
                uni_name: "Albukhary ජාත්‍යන්තර විශ්වවිද්‍යාලය | ජාත්‍යන්තර ශිෂ්‍ය සේවා ඒකකය"
            }
        };

        function applyLanguage(lang) {
            const dict = translations[lang] || translations.en;

            // Update text content
            document.querySelectorAll("[data-i18n]").forEach(el => {
                const key = el.getAttribute("data-i18n");
                if (dict[key]) {
                    el.textContent = dict[key];
                }
            });

            // RTL for Arabic
            if (lang === "ar") {
                document.documentElement.setAttribute("dir", "rtl");
                document.documentElement.lang = "ar";
                document.querySelectorAll('.form-control, .input-group-text, select, .form-label, .logo-text, .logo-subtitle, .role-label').forEach(el => {
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
                document.querySelectorAll('.form-control, .input-group-text, select, .form-label, .logo-text, .logo-subtitle, .role-label').forEach(el => {
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

            localStorage.setItem("issu_lang", lang);
        }

        const savedLang = localStorage.getItem("issu_lang") || "en";
        document.getElementById("langSelect").value = savedLang;
        applyLanguage(savedLang);

        document.getElementById("langSelect").addEventListener("change", function() {
            applyLanguage(this.value);
        });

        function togglePassword() {
            const passwordField = document.getElementById('password');
            const icon = document.querySelector('.password-toggle i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            }
        }

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const roleStudent = document.getElementById('role_student');
            const roleStaff = document.getElementById('role_staff');
            let isValid = true;

            // Remove previous error styles
            email.classList.remove('is-invalid');
            password.classList.remove('is-invalid');
            document.querySelectorAll('.role-label').forEach(label => {
                label.classList.remove('border-danger');
            });

            if (!email.value.trim()) {
                email.classList.add('is-invalid');
                isValid = false;
            }

            if (!password.value.trim()) {
                password.classList.add('is-invalid');
                isValid = false;
            }

            if (!roleStudent.checked && !roleStaff.checked) {
                document.querySelectorAll('.role-label').forEach(label => {
                    label.classList.add('border-danger');
                });
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
            }
        });

        // Auto-focus email field
        document.getElementById('email').focus();
        
        // Show welcome message on first visit
        document.addEventListener('DOMContentLoaded', function() {
            const firstVisit = localStorage.getItem('issu_first_visit');
            if (!firstVisit) {
                const currentLang = localStorage.getItem("issu_lang") || "en";
                const dict = translations[currentLang] || translations.en;
                setTimeout(() => {
                    console.log("Welcome to " + dict.brand_title + " - " + dict.uni_name);
                }, 500);
                localStorage.setItem('issu_first_visit', 'true');
            }
            
            // Auto-select role if previously selected
            const savedRole = localStorage.getItem('issu_last_role');
            if (savedRole) {
                const roleRadio = document.getElementById(`role_${savedRole}`);
                if (roleRadio) {
                    roleRadio.checked = true;
                }
            }
            
            // Save role selection
            document.querySelectorAll('input[name="role"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    localStorage.setItem('issu_last_role', this.value);
                });
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt+S for student role
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                document.getElementById('role_student').checked = true;
                document.getElementById('role_student').focus();
            }
            
            // Alt+T for staff role
            if (e.altKey && e.key === 't') {
                e.preventDefault();
                document.getElementById('role_staff').checked = true;
                document.getElementById('role_staff').focus();
            }
            
            // Ctrl+Enter to submit form
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('loginForm').submit();
            }
        });
    </script>
</body>
</html>
