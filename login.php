<?php
/**
 * Login page for Smart Parking System
 */
require_once 'database/db.php';

// Initialize variables
$email = '';
$error = '';
$success = '';

// Check for error messages in URL
if (isset($_GET['error']) && $_GET['error'] === 'user_not_found') {
    $error = "Your session has expired or your account was not found. Please login again.";
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid form submission. Please try again.";
    } else {
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']) ? true : false;

        // Check for too many failed login attempts
        if (check_login_attempts($email, $conn)) {
            $error = "Too many failed login attempts. Please try again later.";
        } else {
            // Validate input
            if (empty($email) || empty($password)) {
                $error = "Email and password are required.";
            } else {
                // Check if user exists
                $sql = "SELECT * FROM users WHERE email = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password'])) {
                        // Log successful login
                        log_auth_attempt($email, 1, $conn);

                        // Update last login time
                        update_last_login($user['id'], $conn);

                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['name'] = $user['name'];
                        $_SESSION['is_admin'] = $user['is_admin'];

                        // Set cookie if remember me is checked (30 days)
                        if ($remember) {
                            $token = bin2hex(random_bytes(16));
                            setcookie('remember_token', $token, time() + 30 * 24 * 60 * 60, '/');
                        }

                        // Redirect based on user role
                        if ($user['is_admin'] == 1) {
                            header("Location: admin/dashboard.php");
                        } else {
                            header("Location: dashboard.php");
                        }
                        exit();
                    } else {
                        // Log failed login
                        log_auth_attempt($email, 0, $conn);
                        $error = "Invalid password!";
                    }
                } else {
                    // Log failed login
                    log_auth_attempt($email, 0, $conn);
                    $error = "User not found!";
                }
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Parking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #00416A, #E4E5E6);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            display: flex;
            width: 100%;
            max-width: 900px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            overflow: hidden;
        }
        .login-image {
            display: none;
            flex: 1;
            background-image: url('image/login-back.jpg');
            background-size: cover;
            background-position: center;
            position: relative;
        }
        .login-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 65, 106, 0.4);
        }
        .login-image-content {
            position: absolute;
            bottom: 2rem;
            left: 2rem;
            color: white;
            z-index: 1;
        }
        .login-image-content h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }
        .login-image-content p {
            font-size: 1.1rem;
            max-width: 80%;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        .login-form {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #fff;
            padding: 2.5rem;
        }
        .container {
            width: 100%;
            max-width: 400px;
        }
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .form-header img {
            width: 60px;
            height: 60px;
            margin-bottom: 1rem;
            background-color: #5a67d8;
            padding: 10px;
            border-radius: 10px;
        }
        .form-title {
            text-align: center;
            margin-bottom: 0.5rem;
            color: #00416A;
            font-size: 1.75rem;
            font-weight: 700;
        }
        .form-subtitle {
            text-align: center;
            margin-bottom: 2rem;
            color: #666;
            font-size: 0.95rem;
        }
        .input-group {
            margin-bottom: 1.5rem;
        }
        .input-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
            font-size: 0.95rem;
        }
        .input-wrapper {
            position: relative;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.3s ease;
            background-color: #f0f4f8;
        }
        .input-wrapper:focus-within {
            border-color: #00416A;
            box-shadow: 0 0 0 3px rgba(0, 65, 106, 0.1);
        }
        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #00416A;
        }
        .input-wrapper input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            background: transparent;
        }
        .input-wrapper input:focus {
            outline: none;
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            cursor: pointer;
            z-index: 10;
            background: transparent;
            border: none;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .remember-me {
            display: flex;
            align-items: center;
        }
        .remember-me input {
            margin-right: 8px;
            cursor: pointer;
        }
        .remember-me label {
            font-size: 0.9rem;
            color: #555;
            cursor: pointer;
        }
        .forgot-password a {
            color: #00416A;
            text-decoration: none;
            transition: color 0.3s ease;
            font-size: 0.9rem;
        }
        .forgot-password a:hover {
            color: #002D4A;
            text-decoration: underline;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: #00416A;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }
        .btn:hover {
            background: #002D4A;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 65, 106, 0.3);
        }
        .switch-form {
            text-align: center;
            margin-top: 1.5rem;
            color: #666;
            font-size: 0.9rem;
        }
        .switch-form a {
            color: #00416A;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .switch-form a:hover {
            color: #002D4A;
            text-decoration: underline;
        }
        .login-footer {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.85rem;
        }
        /* Animation for form elements */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Error and success messages */
        .error-message {
            background: #fff5f5;
            color: #e53e3e;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            border: 1px solid #fed7d7;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 500;
        }
        .success-message {
            background: #f0fff4;
            color: #2ecc71;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            border: 1px solid #c3ffd9;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 500;
        }
        @media (min-width: 992px) {
            .login-container {
                display: flex;
            }
            .login-image {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left side image (visible on larger screens) -->
        <div class="login-image">
            <div class="login-image-content">
                <h2>Online Parking System</h2>
                <p>Manage your parking spots efficiently with our smart parking solution.</p>
            </div>
        </div>

        <!-- Right side form -->
        <div class="login-form">
            <div class="container fade-in">
                <div class="form-header">
                    <img src="https://img.icons8.com/color/96/000000/parking.png" alt="Online Parking Logo">
                    <h2 class="form-title">Welcome Back</h2>
                    <p class="form-subtitle">Please login to your account</p>
                </div>

                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="input-group">
                        <label for="email">Email</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Enter your password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="remember-forgot">
                        <div class="remember-me">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Remember me</label>
                        </div>
                        <div class="forgot-password">
                            <a href="forgot-password.php">Forgot Password?</a>
                        </div>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>

                <div class="switch-form">
                    Don't have an account? <a href="register.php">Sign Up</a>
                </div>

                <div class="login-footer">
                    &copy; <?php echo date('Y'); ?> Online Parking System. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            const icon = button.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
