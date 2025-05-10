<?php
/**
 * Registration page for Online Parking System
 */

// Include database connection (which already starts the session)
require 'database/db.php';

$error = "";
$username = "";
$email = "";
$password_error = "";

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = "Security validation failed. Please try again.";
    } else {
        // Sanitize and validate inputs
        $username = sanitize_input($_POST['username']);
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password']; // Don't sanitize password before hashing
        $confirm_password = $_POST['confirm_password'];
        $role = 'User'; // Default role is User for security

        // Validate email
        if (!validate_email($email)) {
            $error = "Invalid email format.";
        }
        // Validate password match
        else if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        }
        // Validate password strength
        else if (!validate_password_strength($password)) {
            $password_error = "Password must be at least 8 characters long and include uppercase, lowercase, and numbers.";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error = "Email already exists!";
            } else {
                // Hash password securely
                $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                // Insert user into database
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);

                if ($stmt->execute()) {
                    // Redirect to login page (without passing credentials in URL)
                    header("Location: login.php");
                    exit();
                } else {
                    $error = "Error: " . $stmt->error;
                }
            }

            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ParkEase</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">


</head>

<body class="bg-gray-100 flex items-center justify-center h-screen" style="background-image: url('image/registration-back.jpg'); background-size: cover; background-position: center;">
    <div class="backdrop-blur-sm bg-orange-900/50 p-8 rounded-lg shadow-lg shadow-orange-300 w-96 border border-white/80 border-2">
        <h2 class="text-2xl font-bold mb-6 text-center text-white">Register</h2>
        <?php if ($error): ?>
            <div class="mb-4 p-2 bg-red-100/70 text-red-700 rounded backdrop-blur-sm">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="" onsubmit="return validateForm()">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

            <div class="mb-4">
                <label class="block text-white">Username</label>
                <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>" required
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none bg-black/20 text-white focus:scale-105 transition-all ease-in-out duration-1000"
                    autocomplete="username">
            </div>
            <div class="mb-4">
                <label class="block text-white">Email</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" required
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none bg-black/20 text-white focus:scale-105 transition-all ease-in-out duration-1000"
                    autocomplete="email">
                <span id="email-error" class="text-red-500 text-sm hidden">Please enter a valid email address (e.g.,
                    example@example.com).</span>
            </div>
            <div class="mb-4 relative">
                <label class="block text-white">Password</label>
                <input type="password" name="password" id="password" required
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none bg-black/20 text-white focus:scale-105 transition-all ease-in-out duration-1000"
                    autocomplete="new-password">
                <!-- Eye icon to toggle password visibility -->
                <i class="fas fa-eye absolute right-3 top-10 cursor-pointer text-white" onclick="togglePassword('password')"></i>
                <?php if ($password_error): ?>
                    <span class="text-red-500 text-sm block mt-1"><?php echo $password_error; ?></span>
                <?php endif; ?>
            </div>

            <div class="mb-4 relative">
                <label class="block text-white">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none bg-black/20 text-white focus:scale-105 transition-all ease-in-out duration-1000"
                    autocomplete="new-password">
                <i class="fas fa-eye absolute right-3 top-10 cursor-pointer text-white" onclick="togglePassword('confirm_password')"></i>
            </div>

            <!-- Hidden role field - for security, we set this on the server side -->
            <input type="hidden" name="role" value="User">
            <button type="submit"
                class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Register
            </button>
        </form>

        <p class="mt-4 text-center text-white">Already have an account? <a href="login.php" class="text-blue-300 hover:text-blue-500">Login</a></p>
    </div>
    <script>
        // Function to validate email format
        function validateEmail(email) {
            // Regex to check for a valid email format and ensure the domain ends with a fully written TLD
            const regex = /^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/;
            return regex.test(email);
        }

        // Function to validate password strength
        function validatePasswordStrength(password) {
            // At least 8 characters
            if (password.length < 8) {
                return false;
            }

            // At least one uppercase letter
            if (!/[A-Z]/.test(password)) {
                return false;
            }

            // At least one lowercase letter
            if (!/[a-z]/.test(password)) {
                return false;
            }

            // At least one number
            if (!/[0-9]/.test(password)) {
                return false;
            }

            return true;
        }

        // Function to validate the form
        function validateForm() {
            const emailInput = document.getElementById('email');
            const emailError = document.getElementById('email-error');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const email = emailInput.value.trim();
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            let isValid = true;

            // Validate email
            if (!validateEmail(email)) {
                emailError.textContent = 'Please enter a valid email address (e.g., example@example.com).';
                emailError.classList.remove('hidden');
                emailInput.focus();
                isValid = false;
            } else {
                emailError.classList.add('hidden');
            }

            // Validate password strength
            if (!validatePasswordStrength(password)) {
                alert('Password must be at least 8 characters long and include uppercase, lowercase, and numbers.');
                passwordInput.focus();
                isValid = false;
            }

            // Validate password match
            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                confirmPasswordInput.focus();
                isValid = false;
            }

            return isValid;
        }

        // Function to toggle password visibility
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const eyeIcon = passwordField.nextElementSibling;

            if (passwordField.type === "password") {
                passwordField.type = "text";
                eyeIcon.classList.remove("fa-eye");
                eyeIcon.classList.add("fa-eye-slash");
            } else {
                passwordField.type = "password";
                eyeIcon.classList.remove("fa-eye-slash");
                eyeIcon.classList.add("fa-eye");
            }
        }
    </script>
</body>

</html>