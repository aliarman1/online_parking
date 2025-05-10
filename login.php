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
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #00416A, #E4E5E6);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: flex;
        }
        .login-image {
            flex: 1;
            background-image: url('image/login-back.jpg');
            background-size: cover;
            background-position: center;
            min-height: 500px;
            display: none;
        }
        .login-form {
            flex: 1;
            padding: 40px;
        }
        .form-title {
            color: #00416A;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .form-subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .input-group {
            margin-bottom: 20px;
            position: relative;
        }
        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
        }
        .input-wrapper {
            position: relative;
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
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        .input-wrapper input:focus {
            border-color: #00416A;
            box-shadow: 0 0 0 3px rgba(0, 65, 106, 0.2);
            outline: none;
        }
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            cursor: pointer;
        }
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .remember-me {
            display: flex;
            align-items: center;
        }
        .remember-me input {
            margin-right: 8px;
        }
        .forgot-password a {
            color: #00416A;
            text-decoration: none;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: #00416A;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #002D4A;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 65, 106, 0.3);
        }
        .switch-form {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .switch-form a {
            color: #00416A;
            text-decoration: none;
            font-weight: 600;
        }
        .login-footer {
            text-align: center;
            margin-top: 30px;
            color: #999;
            font-size: 14px;
        }
        @media (min-width: 768px) {
            .login-image {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-image"></div>
        <div class="login-form">
            <h2 class="form-title">Welcome Back</h2>
            <p class="form-subtitle">Please login to your account</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
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
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('password')"></i>
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
                &copy; <?php echo date('Y'); ?> Smart Parking System. All rights reserved.
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;

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
