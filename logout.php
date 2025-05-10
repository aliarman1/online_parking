<?php
/**
 * Logout page for Online Parking System
 */

// Include database connection to get security functions
require 'database/db.php';

// Get the session name
$session_name = session_name();

// Unset all session variables
$_SESSION = array();

// Delete the session cookie
if (isset($_COOKIE[$session_name])) {
    // Make sure the cookie path matches the one used when setting the cookie
    $params = session_get_cookie_params();
    setcookie($session_name, '', time() - 42000, '/', $params['domain'], $params['secure'], $params['httponly']);
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
