<?php
/**
 * Profile page for Online Parking System
 * Allows users to update their password
 */

// Include database connection (which already starts the session)
require 'database/db.php';

// Ensure user is logged in
require_login();

$email = $_SESSION['email']; // Pre-fill email field
$success = "";
$error = "";
$password_error = "";

// Handle password update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = "Security validation failed. Please try again.";
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Verify current password first
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();

        if (!password_verify($current_password, $hashed_password)) {
            $error = "Current password is incorrect.";
        }
        // Check if passwords match
        else if ($new_password !== $confirm_password) {
            $error = "New passwords do not match!";
        }
        // Validate password strength
        else if (!validate_password_strength($new_password)) {
            $password_error = "Password must be at least 8 characters long and include uppercase, lowercase, and numbers.";
        } else {
            // Hash new password
            $new_hashed_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);

            // Update password in database
            $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $new_hashed_password, $_SESSION['user_id']);

            if ($stmt->execute()) {
                $success = "Password updated successfully!";
                // Regenerate session ID for security
                session_regenerate_id(true);
                header("Location: index.php");
                exit();
            } else {
                $error = "Error updating password.";
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
    <title>Profile - ParkEase</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- DaisyUI -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>




</head>

<body class="bg-gray-100 flex flex-col items-center h-screen"
    style="background-image: url('image/registration-back.jpg'); background-size: cover; background-position: center; ">




    <!-- navbar -->
    <div class="navbar bg-base-100 shadow-sm sticky top-0">
        <div class="navbar-start">
            <div class="dropdown">
                <div tabindex="0" role="button" class="btn btn-ghost lg:hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h8m-8 6h16" />
                    </svg>
                </div>
                <ul tabindex="0" class="menu menu-sm dropdown-content bg-base-100 rounded-box z-1 mt-3 w-52 p-2 shadow">
                    <li><a href="index.php"
                            class="bg-blue-600 text-white px-4 py-2 mb-2 rounded-lg hover:bg-blue-700">Home</a></li>
                    <li><a href="booking_slot.php"
                            class="bg-green-600 text-white px-4 py-2 mb-2 rounded-lg hover:bg-green-700">Booking
                            History</a>
                    </li>
                    <li><a href="profile.php"
                            class="bg-purple-600 text-white px-4 py-2 mb-2 rounded-lg hover:bg-purple-700">Profile</a>
                    </li>
                </ul>
            </div>
            <a class="btn btn-ghost text-xl">ParkEase</a>
            <div class="dropdown dropdown-end">
                <div tabindex="0" class="btn btn-ghost btn-circle avatar">
                    <div class="w-10 rounded-full relative group">
                        <!-- Profile Image with Tooltip -->
                        <img alt="Tailwind CSS Navbar component"
                            src="https://img.daisyui.com/images/stock/photo-1534528741775-53994a69daeb.webp"
                            title="<?php echo $_SESSION['email']; ?>" class="w-full h-full rounded-full" />
                        <!-- Custom Tooltip -->
                        <div
                            class="absolute bottom-full mb-2 hidden group-hover:block bg-black text-white text-sm px-2 py-1 rounded">
                            <?php echo $_SESSION['email']; ?>
                        </div>
                    </div>
                </div>
                <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-52">
                    <li>
                        <a class="justify-between">
                            Profile
                            <span class="badge">1</span>
                        </a>
                    </li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>

        <div class="navbar-end">
            <div class="navbar-center hidden lg:flex">
                <ul class="menu menu-horizontal">
                    <li><a href="index.php"
                            class="bg-blue-600 text-white px-4 py-2 mr-2 rounded-lg hover:bg-blue-700">Home</a></li>
                    <li><a href="booking_slot.php"
                            class="bg-green-600 text-white px-4 py-2 mr-2 rounded-lg hover:bg-green-700">Booking
                            History</a>
                    </li>
                    <li><a href="profile.php"
                            class="bg-purple-600 text-white px-4 py-2 mr-2 rounded-lg hover:bg-purple-700">Profile</a>
                    </li>
                </ul>
            </div>
            <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Logout</a>
        </div>
    </div>


    <div class="p-8 rounded-lg shadow-lg shadow-orange-300 w-96 my-auto backdrop-blur-sm bg-orange-900/50 border border-white/80 border-2">
        <h2 class="text-2xl font-bold mb-6 text-center text-white">Profile</h2>

        <?php if ($success): ?>
            <div class="mb-4 p-2 bg-green-100 text-green-700 rounded"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-4 p-2 bg-red-100 text-red-700 rounded"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="" onsubmit="return validatePasswordForm()">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

            <div class="mb-4">
                <label class="block text-white">Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" disabled
                    class="w-full px-4 py-2 border rounded-lg text-white cursor-not-allowed">
            </div>

            <div class="mb-4 relative">
                <label class="block text-white">Current Password</label>
                <input type="password" name="current_password" id="current_password" required
                    class="w-full px-4 py-2 border focus:outline-none focus:scale-105 transition-all ease-in-out duration-1000 rounded-lg text-white">
                <i class="fas fa-eye absolute right-3 top-10 cursor-pointer text-white"
                    onclick="togglePassword('current_password')"></i>
            </div>

            <div class="mb-4 relative">
                <label class="block text-white">New Password</label>
                <input type="password" name="new_password" id="new_password" required
                    class="w-full px-4 py-2 border focus:outline-none focus:scale-105 transition-all ease-in-out duration-1000 rounded-lg text-white">
                <i class="fas fa-eye absolute right-3 top-10 cursor-pointer text-white"
                    onclick="togglePassword('new_password')"></i>
                <?php if ($password_error): ?>
                    <span class="text-red-500 text-sm block mt-1"><?php echo $password_error; ?></span>
                <?php endif; ?>
            </div>

            <div class="mb-4 relative">
                <label class="block text-white">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required
                    class="w-full px-4 py-2 border focus:outline-none focus:scale-105 transition-all ease-in-out duration-1000 rounded-lg text-white">
                <i class="fas fa-eye absolute right-3 top-10 cursor-pointer text-white"
                    onclick="togglePassword('confirm_password')"></i>
            </div>

            <button type="submit"
                class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500">
                Update Password
            </button>
        </form>
    </div>
    <script>
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

        // Function to validate the password form
        function validatePasswordForm() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            let isValid = true;

            // Validate password strength
            if (!validatePasswordStrength(newPassword)) {
                alert('Password must be at least 8 characters long and include uppercase, lowercase, and numbers.');
                document.getElementById('new_password').focus();
                isValid = false;
            }

            // Validate password match
            if (newPassword !== confirmPassword) {
                alert('New passwords do not match.');
                document.getElementById('confirm_password').focus();
                isValid = false;
            }

            return isValid;
        }

        // Function to toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.querySelector(`i[onclick="togglePassword('${inputId}')"]`);
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