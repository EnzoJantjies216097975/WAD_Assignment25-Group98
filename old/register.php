<?php
// register.php - Registration page for NUST Timetable Manager
session_start();
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard/index.php");
    exit();
}

$errors = [];
$success = false;

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect and sanitize form data
    $student_number = trim($_POST['student_number']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $year_of_study = $_POST['year_of_study'];
    $program = trim($_POST['program']);
    
    // Validation
    if (empty($student_number)) {
        $errors[] = 'Student number is required.';
    }
    
    if (empty($first_name) || empty($last_name)) {
        $errors[] = 'First and last name are required.';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    // Check if student number or email already exists
    if (empty($errors)) {
        $db = Database::getInstance();
        
        // Check for existing student number
        $stmt = $db->query(
            "SELECT user_id FROM users WHERE student_number = ?",
            [$student_number]
        );
        if ($stmt->rowCount() > 0) {
            $errors[] = 'Student number already registered.';
        }
        
        // Check for existing email
        $stmt = $db->query(
            "SELECT user_id FROM users WHERE email = ?",
            [$email]
        );
        if ($stmt->rowCount() > 0) {
            $errors[] = 'Email address already registered.';
        }
    }
    
    // If no errors, create the account
    if (empty($errors)) {
        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $db = Database::getInstance();
        $stmt = $db->query(
            "INSERT INTO users (student_number, first_name, last_name, email, password, year_of_study, program) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$student_number, $first_name, $last_name, $email, $password_hash, $year_of_study, $program]
        );
        
        if ($stmt) {
            $success = true;
            // Redirect to login page with success message
            header("Location: login.php?registered=1");
            exit();
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - NUST Timetable Manager</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .register-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 40px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-logo {
            width: 80px;
            height: auto;
            margin-bottom: 20px;
        }
        
        .register-title {
            color: var(--primary-blue);
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .register-subtitle {
            color: var(--text-light);
            font-size: 14px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: 500;
        }
        
        .form-label span {
            color: var(--danger);
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-light);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background-color: white;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }
        
        .strength-weak { color: var(--danger); }
        .strength-medium { color: var(--warning); }
        .strength-strong { color: var(--success); }
        
        .btn-register-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary-orange), var(--dark-orange));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-register-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 53, 0.3);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background-color: #FEE;
            color: var(--danger);
            border: 1px solid #FDD;
        }
        
        .alert-success {
            background-color: #EFE;
            color: var(--success);
            border: 1px solid #DFD;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-light);
        }
        
        .form-footer a {
            color: var(--primary-orange);
            font-weight: 500;
        }
        
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script src="assets/js/jquery-3.6.0.min.js"></script>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <img src="assets/images/logo.png" alt="NUST Logo" class="nav-logo">
                <span class="nav-title">NUST Timetable Manager</span>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="about.php" class="nav-link">About</a></li>
                <li><a href="features.php" class="nav-link">Features</a></li>
                <li><a href="login.php" class="nav-link">Login</a></li>
                <li><a href="register.php" class="nav-link btn-register active">Register</a></li>
            </ul>
        </div>
    </nav>

    <!-- Registration Form -->
    <div class="register-container">
        <div class="register-header">
            <img src="assets/images/logo.png" alt="NUST Logo" class="register-logo">
            <h1 class="register-title">Create Your Account</h1>
            <p class="register-subtitle">Join NUST Timetable Manager today</p>
        </div>
        
        <?php if(!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach($errors as $error): ?>
                    <div><?php echo $error; ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success">Registration successful! Redirecting to login...</div>
        <?php endif; ?>
        
        <form method="POST" action="register.php" id="registerForm">
            <div class="form-group">
                <label for="student_number" class="form-label">Student Number <span>*</span></label>
                <input type="text" 
                       id="student_number" 
                       name="student_number" 
                       class="form-input" 
                       placeholder="e.g., 217090427"
                       value="<?php echo isset($_POST['student_number']) ? htmlspecialchars($_POST['student_number']) : ''; ?>"
                       required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name" class="form-label">First Name <span>*</span></label>
                    <input type="text" 
                           id="first_name" 
                           name="first_name" 
                           class="form-input" 
                           placeholder="Enter first name"
                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="last_name" class="form-label">Last Name <span>*</span></label>
                    <input type="text" 
                           id="last_name" 
                           name="last_name" 
                           class="form-input" 
                           placeholder="Enter last name"
                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                           required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">Email Address <span>*</span></label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       class="form-input" 
                       placeholder="your.email@nust.na"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="year_of_study" class="form-label">Year of Study <span>*</span></label>
                    <select id="year_of_study" name="year_of_study" class="form-select" required>
                        <option value="">Select Year</option>
                        <option value="1" <?php echo (isset($_POST['year_of_study']) && $_POST['year_of_study'] == '1') ? 'selected' : ''; ?>>1st Year</option>
                        <option value="2" <?php echo (isset($_POST['year_of_study']) && $_POST['year_of_study'] == '2') ? 'selected' : ''; ?>>2nd Year</option>
                        <option value="3" <?php echo (isset($_POST['year_of_study']) && $_POST['year_of_study'] == '3') ? 'selected' : ''; ?>>3rd Year</option>
                        <option value="4" <?php echo (isset($_POST['year_of_study']) && $_POST['year_of_study'] == '4') ? 'selected' : ''; ?>>4th Year</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="program" class="form-label">Program <span>*</span></label>
                    <select id="program" name="program" class="form-select" required>
                        <option value="">Select Program</option>
                        <option value="Software Development">Software Development</option>
                        <option value="Computer Science">Computer Science</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Cyber Security">Cyber Security</option>
                        <option value="Data Science">Data Science</option>
                        <option value="Computer Systems and Networks">Computer Systems and Networks</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Password <span>*</span></label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="form-input" 
                       placeholder="Minimum 8 characters"
                       required>
                <div id="passwordStrength" class="password-strength"></div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password <span>*</span></label>
                <input type="password" 
                       id="confirm_password" 
                       name="confirm_password" 
                       class="form-input" 
                       placeholder="Re-enter password"
                       required>
            </div>
            
            <button type="submit" class="btn-register-submit">Create Account</button>
        </form>
        
        <div class="form-footer">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Password strength checker
            $('#password').on('keyup', function() {
                var password = $(this).val();
                var strength = 0;
                var feedback = '';
                
                if (password.length >= 8) strength++;
                if (password.match(/[a-z]/)) strength++;
                if (password.match(/[A-Z]/)) strength++;
                if (password.match(/[0-9]/)) strength++;
                if (password.match(/[^a-zA-Z0-9]/)) strength++;
                
                if (password.length < 8) {
                    feedback = '<span class="strength-weak">Too short</span>';
                } else if (strength < 3) {
                    feedback = '<span class="strength-weak">Weak password</span>';
                } else if (strength < 4) {
                    feedback = '<span class="strength-medium">Medium strength</span>';
                } else {
                    feedback = '<span class="strength-strong">Strong password</span>';
                }
                
                $('#passwordStrength').html(feedback);
            });
            
            // Form validation
            $('#registerForm').on('submit', function(e) {
                var password = $('#password').val();
                var confirmPassword = $('#confirm_password').val();
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
                
                if (password.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long!');
                    return false;
                }
            });
        });
    </script>
</body>
</html>