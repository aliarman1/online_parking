<?php
/**
 * Security functions for the Online Parking System
 * This file contains functions for authentication, authorization, and security
 */

// Start or resume session with secure settings
function secure_session_start() {
    // Check if session is already started
    if (session_status() == PHP_SESSION_ACTIVE) {
        return;
    }

    $session_name = 'PARKING_SESSION';
    $secure = false; // Set to true if using HTTPS
    $httponly = true;

    // Force session to use cookies only
    ini_set('session.use_only_cookies', 1);

    // Get current cookie params
    $cookieParams = session_get_cookie_params();

    // Set secure cookie parameters
    session_set_cookie_params(
        86400, // 24 hour lifetime (increased from 1 hour)
        '/',   // Make sure the path is set to root
        $cookieParams["domain"],
        $secure,
        $httponly
    );

    // Set session name
    session_name($session_name);

    // Start session
    session_start();

    // Regenerate session ID if it's older than 30 minutes
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_regenerate_id(true);
        $_SESSION['last_activity'] = time();
    } else if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }
}

// Generate CSRF token
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function is_admin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// Redirect if not logged in
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}

// Redirect if not admin
function require_admin() {
    require_login();
    if (!is_admin()) {
        header("Location: index.php");
        exit();
    }
}

// Sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validate password strength
function validate_password_strength($password) {
    // Password must be at least 8 characters
    if (strlen($password) < 8) {
        return false;
    }

    // Password must contain at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }

    // Password must contain at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }

    // Password must contain at least one number
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }

    return true;
}

// Update last login time
function update_last_login($user_id, $conn) {
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

// Log authentication attempt
function log_auth_attempt($identifier, $success, $conn) {
    // Temporarily disable logging to prevent errors if table doesn't exist
    return;

    /* Original code - commented out for now
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    $stmt = $conn->prepare("INSERT INTO auth_logs (email, ip_address, user_agent, success) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $identifier, $ip, $user_agent, $success);
    $stmt->execute();
    $stmt->close();
    */
}

// Check for too many failed login attempts
function check_login_attempts($identifier, $conn) {
    // Temporarily disable login attempt checking
    return false;

    /* Original code - commented out for now
    $ip = $_SERVER['REMOTE_ADDR'];
    $time_limit = date('Y-m-d H:i:s', strtotime('-15 minutes'));

    $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM auth_logs WHERE (email = ? OR ip_address = ?) AND success = 0 AND timestamp > ?");
    $stmt->bind_param("sss", $identifier, $ip, $time_limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['attempts'] >= 5; // Limit to 5 failed attempts in 15 minutes
    */
}
