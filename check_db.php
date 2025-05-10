<?php
// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';

// Create connection without database
$conn = new mysqli($host, $user, $pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if database exists
$result = $conn->query("SHOW DATABASES LIKE 'online_parking'");
if ($result->num_rows > 0) {
    echo "Database 'online_parking' exists.<br>";

    // Connect to the database
    $conn->select_db('online_parking');

    // Check for tables
    $tables = $conn->query("SHOW TABLES");
    echo "Tables in online_parking database:<br>";

    if ($tables->num_rows > 0) {
        $tableList = array();
        while($row = $tables->fetch_row()) {
            $tableList[] = $row[0];
            echo "- " . $row[0] . "<br>";
        }
    } else {
        echo "No tables found in the database.<br>";
    }

    // Check if a_bookings table exists
    if (!in_array('a_bookings', $tableList)) {
        echo "<br>The 'a_bookings' table does not exist.<br>";
        echo "Creating a_bookings table...<br>";

        // Create the a_bookings table
        $create_table_sql = "CREATE TABLE a_bookings (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            slot_id INT(11) NOT NULL,
            vehicle_number VARCHAR(20) NOT NULL,
            booking_date DATE NOT NULL,
            booking_time TIME NOT NULL,
            duration INT(11) NOT NULL,
            end_time TIME NOT NULL,
            cost_per_hour DECIMAL(10,2) NOT NULL,
            total_cost DECIMAL(10,2) NOT NULL,
            bkash_number VARCHAR(15) NOT NULL,
            bkash_pin VARCHAR(10) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (slot_id) REFERENCES parking_slots(id)
        )";

        if ($conn->query($create_table_sql) === TRUE) {
            echo "Table a_bookings created successfully<br>";
        } else {
            echo "Error creating table: " . $conn->error . "<br>";
        }
    }
} else {
    echo "Database 'online_parking' does not exist.<br>";
}

$conn->close();
?>
