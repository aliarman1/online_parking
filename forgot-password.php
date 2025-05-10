<?php
/**
 * Forgot Password page for Online Parking System
 */
require_once 'database/db.php';

// Initialize variables
$email = '';
$error = '';
$success = '';

// Process forgot password form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid form submission. Please try again.";
    } else {
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate input
        if (empty($email) || empty($password) || empty($confirm_password)) {
            $error = "All fields are required.";
        } elseif (!validate_email($email)) {
            $error = "Please enter a valid email address.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (!validate_password_strength($password)) {
            $error = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.";
        } else {
            // Check if email exists
            $sql = "SELECT * FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Hash the new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Update password directly
                $update_sql = "UPDATE users SET password = ? WHERE email = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ss", $hashed_password, $email);

                if ($update_stmt->execute()) {
                    $success = "Password updated successfully! Redirecting to login...";
                    header("refresh:2;url=login.php");
                } else {
                    $error = "Error updating password. Please try again.";
                }
            } else {
                $error = "No account found with this email address.";
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
    <title>Forgot Password - Online Parking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
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
            background-image: url('image/forgot-password-back.jpg');
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
        .form-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.5rem;
            display: block;
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
        /* Loading animation */
        .loading {
            display: none;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .loading-spinner {
            display: inline-block;
            width: 30px;
            height: 30px;
            border: 3px solid rgba(0, 65, 106, 0.2);
            border-radius: 50%;
            border-top-color: #00416A;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
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
                <h2>Password Recovery</h2>
                <p>We'll help you get back into your account in no time.</p>
            </div>
        </div>

        <!-- Right side form -->
        <div class="login-form">
            <div class="container fade-in">
                <div class="form-header">
                    <img src="https://img.icons8.com/color/96/000000/parking.png" alt="Online Parking Logo">
                    <h2 class="form-title">Reset Password</h2>
                    <p class="form-subtitle">Enter your email and new password</p>
                </div>

                <?php if($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <!-- Loading indicator -->
                <div id="loading" class="loading">
                    <div class="loading-spinner"></div>
                    <p>Resetting password...</p>
                </div>

                <form method="POST" action="" id="resetForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="input-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" placeholder="Enter your registered email" required>
                        </div>
                        <small class="form-text">Enter the email associated with your account</small>
                    </div>

                    <div class="input-group">
                        <label for="password">New Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Enter new password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn" id="submitBtn">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </form>

                <div class="switch-form">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                </div>

                <div class="login-footer">
                    &copy; <?php echo date('Y'); ?> Online Parking System. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <script>
        // Form submission with loading indicator
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            // Show loading indicator
            document.getElementById('loading').style.display = 'block';

            // Disable submit button to prevent multiple submissions
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';

            // Form will submit normally
            // The loading indicator will be hidden when the page reloads
        });

        // Add visual feedback when focusing on inputs
        document.getElementById('email').addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });

        document.getElementById('email').addEventListener('blur', function() {
            if (this.value === '') {
                this.parentElement.classList.remove('focused');
            }
        });

        // Toggle password visibility
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
