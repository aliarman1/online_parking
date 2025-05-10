<?php
/**
 * Database connection for Smart Parking System
 */

// Database configuration
$host = 'localhost';
$db   = 'online_parking';
$user = 'root';
$pass = '';

// Create connection
try {
    $conn = new mysqli($host, $user, $pass, $db);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    // Log the error
    error_log("Database connection error: " . $e->getMessage());

    // Check if this is an API request or a web page
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    } else {
        // Redirect to setup page if database doesn't exist
        if (strpos($e->getMessage(), "Unknown database") !== false) {
            header("Location: ../setup.php");
            exit;
        }

        die("<div style='color: red; font-family: Arial, sans-serif; padding: 20px; margin: 20px; border: 1px solid #ddd; border-radius: 5px;'>
            <h2>Database Connection Error</h2>
            <p>Could not connect to the database. Please make sure the database server is running and the database exists.</p>
            <p>Error details: " . $e->getMessage() . "</p>
            <p><a href='../setup.php' style='color: blue;'>Run Database Setup</a></p>
        </div>");
    }
}

// Set charset to ensure proper encoding
$conn->set_charset("utf8mb4");

// Include security functions
require_once __DIR__ . '/../includes/security.php';

// Start secure session
secure_session_start();
?>
