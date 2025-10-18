<?php
// login.php - Login page for NUST Timetable Manager
session_start();
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard/index.php");
    exit();
}

$error = '';
$success = isset($_GET['registered']) ? 'Registration successful! Please login.' : '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        // Get database connection
        $db = Database::getInstance();
        
        // Query to find user
        $stmt = $db->query(
            "SELECT user_id, student_number, first_name, last_name, email, password 
             FROM users WHERE email = ? OR student_number = ?",
            [$email, $email]
        );
        
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['student_number'] = $user['student_number'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['email'] = $user['email'];
            
            // Update last login
            $db->query(
                "UPDATE users SET last_login = NOW() WHERE user_id = ?",
                [$user['user_id']]
            );
            
            // Redirect to dashboard
            header("Location: dashboard/index.php");
            exit();
        } else {
            $error = 'Invalid email/student number or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NUST Timetable Manager</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 80px auto;
            padding: 40px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo {
            width: 80px;
            height: auto;
            margin-bottom: 20px;
        }
        
        .login-title {
            color: var(--primary-blue);
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .login-subtitle {
            color: var(--text-light);
            font-size: 14px;
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
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-light);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .form-checkbox input {
            margin-right: 8px;
        }
        
        .btn-login {
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
        }
        
        .btn-login:hover {
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
                <li><a href="login.php" class="nav-link active">Login</a></li>
                <li><a href="register.php" class="nav-link btn-register">Register</a></li>
            </ul>
        </div>
    </nav>

    <!-- Login Form -->
    <div class="login-container">
        <div class="login-header">
            <img src="assets/images/logo.png" alt="NUST Logo" class="login-logo">
            <h1 class="login-title">Welcome Back!</h1>
            <p class="login-subtitle">Login to access your timetables</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="login.php" id="loginForm">
            <div class="form-group">
                <label for="email" class="form-label">Email or Student Number</label>
                <input type="text" 
                       id="email" 
                       name="email" 
                       class="form-input" 
                       placeholder="Enter your email or student number"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       required>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="form-input" 
                       placeholder="Enter your password"
                       required>
            </div>
            
            <div class="form-checkbox">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember me</label>
            </div>
            
            <button type="submit" class="btn-login">Login</button>
        </form>
        
        <div class="form-footer">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
            <p style="margin-top: 10px;"><a href="forgot-password.php">Forgot Password?</a></p>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#loginForm').on('submit', function(e) {
                var email = $('#email').val().trim();
                var password = $('#password').val();
                
                if(email === '' || password === '') {
                    e.preventDefault();
                    alert('Please fill in all fields');
                    return false;
                }
                
                // Basic email validation if email is entered
                if(email.includes('@')) {
                    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if(!emailRegex.test(email)) {
                        e.preventDefault();
                        alert('Please enter a valid email address');
                        return false;
                    }
                }
            });
        });
    </script>
</body>
</html>