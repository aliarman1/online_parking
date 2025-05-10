
<?php
/**
 * Database connection for Online Parking System
 */

// Database configuration
$host = 'localhost';
$db   = 'online_parking';
$user = 'root';
$pass = '';

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to ensure proper encoding
$conn->set_charset("utf8mb4");

// Include security functions
require_once __DIR__ . '/../includes/security.php';

// Start secure session
secure_session_start();
?>