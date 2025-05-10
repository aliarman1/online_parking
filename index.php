<?php
/**
 * Index page for Smart Parking System
 * Redirects to login or dashboard based on authentication status
 */
require_once 'database/db.php';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to dashboard
    header("Location: dashboard.php");
    exit();
} else {
    // Redirect to login page
    header("Location: login.php");
    exit();
}
?>
