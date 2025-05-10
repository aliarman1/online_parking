<?php
/**
 * Session Test File
 * This file is used to test if sessions are working correctly
 */

// Include database connection (which already starts the session)
require 'database/db.php';

// Display session information
echo "<h1>Session Test</h1>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session Status: " . session_status() . " (1=disabled, 2=enabled but no session, 3=active)\n";
echo "Session Cookie Parameters: \n";
print_r(session_get_cookie_params());
echo "\n\nSession Data:\n";
print_r($_SESSION);
echo "</pre>";

// Add a test value to the session
if (!isset($_SESSION['test_value'])) {
    $_SESSION['test_value'] = time();
    echo "<p>Added test value to session: " . $_SESSION['test_value'] . "</p>";
} else {
    echo "<p>Existing test value in session: " . $_SESSION['test_value'] . "</p>";
}

// Link to refresh the page
echo "<p><a href='session_test.php'>Refresh Page</a></p>";

// Link to go to login page
echo "<p><a href='login.php'>Go to Login Page</a></p>";

// Link to go to index page
echo "<p><a href='index.php'>Go to Index Page</a></p>";
?>
