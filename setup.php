<?php
/**
 * Database Setup Script for Smart Parking System
 */

// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';

// Create connection without database
$conn = new mysqli($host, $user, $pass);

// Check connection
if ($conn->connect_error) {
    die("<div style='color: red;'>Connection failed: " . $conn->connect_error . "</div>");
}

// Function to execute SQL queries
function execute_query($conn, $sql, $description) {
    try {
        if ($conn->multi_query($sql)) {
            // Process all result sets
            do {
                // Store result
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
            
            echo "<div style='color: green;'>✓ $description completed successfully.</div>";
            return true;
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        echo "<div style='color: red;'>✗ $description failed: " . $e->getMessage() . "</div>";
        return false;
    }
}

// Check if the script should execute
$execute = isset($_GET['execute']) && $_GET['execute'] === 'true';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Parking System - Database Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .setup-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .success {
            color: #28a745;
            margin-bottom: 10px;
        }
        .error {
            color: #dc3545;
            margin-bottom: 10px;
        }
        .warning {
            color: #ffc107;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .button:hover {
            background-color: #0069d9;
            color: white;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1 class="mb-4">Smart Parking System - Database Setup</h1>
        
        <?php
        if ($execute) {
            echo "<h3>Setting up database...</h3>";
            
            try {
                // Read SQL file
                $sql_file = file_get_contents('database/schema.sql');
                
                if ($sql_file === false) {
                    throw new Exception("Could not read the SQL file. Make sure 'database/schema.sql' exists.");
                }
                
                // Execute the SQL file
                if (execute_query($conn, $sql_file, "Database setup")) {
                    echo "<p class='success'>Database setup completed successfully!</p>";
                    
                    // Check if the database and tables were created
                    $conn->select_db('smart_parking');
                    $tables_result = $conn->query("SHOW TABLES");
                    
                    if ($tables_result) {
                        echo "<h3>Created Tables:</h3>";
                        echo "<ul>";
                        while ($table = $tables_result->fetch_array()) {
                            echo "<li>" . $table[0] . "</li>";
                        }
                        echo "</ul>";
                    }
                } else {
                    echo "<p class='error'>Database setup failed.</p>";
                }
                
            } catch (Exception $e) {
                echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
            }
            
            echo "<p><a href='index.php' class='button'>Go to Homepage</a></p>";
            
        } else {
            // Show warning and confirmation button
            echo "<p class='warning'>Warning: This script will set up the database structure for the Smart Parking System.</p>";
            echo "<p>The following actions will be performed:</p>";
            echo "<ul>";
            echo "<li>Create the 'smart_parking' database if it doesn't exist</li>";
            echo "<li>Drop existing tables if they exist</li>";
            echo "<li>Create new tables: users, auth_logs, parking_spots, bookings, payments</li>";
            echo "<li>Add indexes, functions, triggers, and events</li>";
            echo "<li>Insert sample data: admin user, regular user, and parking spots</li>";
            echo "</ul>";
            echo "<p>Make sure you have backed up any existing data before proceeding.</p>";
            echo "<p><a href='?execute=true' class='button' onclick='return confirm(\"Are you sure you want to set up the database?\")'>Execute Setup</a></p>";
        }
        ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
