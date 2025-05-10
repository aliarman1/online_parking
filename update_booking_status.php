<?php
require 'database/db.php';
date_default_timezone_set('Asia/Dhaka'); // Set correct timezone

$current_time = date('Y-m-d H:i:s'); // Get current timestamp

// Update bookings: "Active" → "Completed" if end time has passed
$sql = "UPDATE bookings 
        SET status = 'Completed' 
        WHERE status = 'Active' AND CONCAT(booking_date, ' ', end_time) <= ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $current_time);

if ($stmt->execute()) {
    echo "Booking statuses updated successfully.";
} else {
    echo "Error updating bookings: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
