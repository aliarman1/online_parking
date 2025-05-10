<?php
/**
 * Settings page for Smart Parking System
 */
require_once 'config.php';

// Check if user is logged in
require_login();

// Get user details
$user_id = $_SESSION['user_id'];
$user = get_user_details($user_id, $conn);

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['message'] = "Invalid form submission. Please try again.";
        $_SESSION['message_type'] = "error";
        header("Location: settings.php");
        exit();
    }
    
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    
    // Validate input
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!validate_email($email)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    // Check if email already exists (if changed)
    if ($email !== $user['email']) {
        $check_sql = "SELECT * FROM users WHERE email = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Email already exists. Please use a different email.";
        }
    }
    
    if (empty($errors)) {
        // Update profile
        $update_sql = "UPDATE users SET name = ?, email = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssi", $name, $email, $user_id);
        
        if ($update_stmt->execute()) {
            // Update session variables
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            
            $_SESSION['message'] = "Profile updated successfully.";
            $_SESSION['message_type'] = "success";
            header("Location: settings.php");
            exit();
        } else {
            $_SESSION['message'] = "Error updating profile. Please try again.";
            $_SESSION['message_type'] = "error";
            header("Location: settings.php");
            exit();
        }
    } else {
        $_SESSION['message'] = implode("<br>", $errors);
        $_SESSION['message_type'] = "error";
    }
}

// Process password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['message'] = "Invalid form submission. Please try again.";
        $_SESSION['message_type'] = "error";
        header("Location: settings.php");
        exit();
    }
    
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    $errors = [];
    
    if (empty($current_password)) {
        $errors[] = "Current password is required.";
    }
    
    if (empty($new_password)) {
        $errors[] = "New password is required.";
    } elseif (!validate_password_strength($new_password)) {
        $errors[] = "New password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    }
    
    if (empty($errors)) {
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['message'] = "Password changed successfully.";
                $_SESSION['message_type'] = "success";
                header("Location: settings.php");
                exit();
            } else {
                $_SESSION['message'] = "Error changing password. Please try again.";
                $_SESSION['message_type'] = "error";
                header("Location: settings.php");
                exit();
            }
        } else {
            $_SESSION['message'] = "Current password is incorrect.";
            $_SESSION['message_type'] = "error";
            header("Location: settings.php");
            exit();
        }
    } else {
        $_SESSION['message'] = implode("<br>", $errors);
        $_SESSION['message_type'] = "error";
    }
}

// Get any messages from the session
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$message_type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Generate CSRF token
$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Smart Parking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Settings</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random" alt="User Avatar">
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'error' ? 'danger' : $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Profile Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="change_password" value="1">
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password" required onkeyup="checkPasswordStrength()">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength mt-2" id="passwordStrength"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required onkeyup="checkPasswordMatch()">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-match mt-2" id="passwordMatch"></div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
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
        
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            // Reset strength indicator
            strengthDiv.innerHTML = '';
            
            if (password.length === 0) return;
            
            let strength = 0;
            let feedback = [];
            
            // Check length
            if (password.length < 8) {
                feedback.push('Password should be at least 8 characters');
            } else {
                strength += 1;
            }
            
            // Check for uppercase letters
            if (!/[A-Z]/.test(password)) {
                feedback.push('Add uppercase letter');
            } else {
                strength += 1;
            }
            
            // Check for lowercase letters
            if (!/[a-z]/.test(password)) {
                feedback.push('Add lowercase letter');
            } else {
                strength += 1;
            }
            
            // Check for numbers
            if (!/[0-9]/.test(password)) {
                feedback.push('Add number');
            } else {
                strength += 1;
            }
            
            // Display strength
            let strengthText = '';
            let strengthColor = '';
            
            switch (strength) {
                case 0:
                case 1:
                    strengthText = 'Weak';
                    strengthColor = '#dc3545';
                    break;
                case 2:
                case 3:
                    strengthText = 'Medium';
                    strengthColor = '#ffc107';
                    break;
                case 4:
                    strengthText = 'Strong';
                    strengthColor = '#28a745';
                    break;
            }
            
            strengthDiv.innerHTML = `<span style="color: ${strengthColor};">${strengthText}</span>`;
            if (feedback.length > 0) {
                strengthDiv.innerHTML += ': ' + feedback.join(', ');
            }
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchDiv.innerHTML = '<span style="color: #28a745;">Passwords match</span>';
            } else {
                matchDiv.innerHTML = '<span style="color: #dc3545;">Passwords do not match</span>';
            }
        }
    </script>
</body>
</html>
