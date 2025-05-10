<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    echo "Please log in to book a slot.";
    exit();
}

// include the database connection file
require 'database/db.php';

// Validate and sanitize form data
$required_fields = ['slot_id', 'vehicle_number', 'booking_date', 'booking_time', 'duration', 'cost_per_hour', 'bkash_number', 'bkash_pin'];
$errors = [];

foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $errors[] = "The field '$field' is required.";
    }
}

if (!empty($errors)) {
    echo "Errors:<br>";
    foreach ($errors as $error) {
        echo "- $error<br>";
    }
    exit();
}

// Assign POST data to variables
$slot_id = $_POST['slot_id'];
$user_id = $_SESSION['user_id'];
$vehicle_number = $_POST['vehicle_number'];
$booking_date = $_POST['booking_date'];
$booking_time = $_POST['booking_time'];
$duration = $_POST['duration'];
$cost_per_hour = $_POST['cost_per_hour'];
$bkash_number = $_POST['bkash_number'];
$bkash_pin = $_POST['bkash_pin'];

// Calculate end time and total cost
$end_time = date('H:i', strtotime("+$duration hours", strtotime($booking_time)));
$total_cost = $cost_per_hour * $duration;

// Set the initial status of the booking
$status = "Pending"; // You can change this to any default status you prefer


// Insert booking into database
$stmt = $conn->prepare("INSERT INTO bookings (user_id, slot_id, vehicle_number, booking_date, booking_time, duration, end_time, cost_per_hour, total_cost, bkash_number, bkash_pin, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iisssisddsss", $user_id, $slot_id, $vehicle_number, $booking_date, $booking_time, $duration, $end_time, $cost_per_hour, $total_cost, $bkash_number, $bkash_pin, $status);
if ($stmt->execute()) {
    echo "Booking successful! Total cost: $" . number_format($total_cost, 2);
} else {
    echo "Error: " . $stmt->error;
}

$stmt2 = $conn->prepare("INSERT INTO a_bookings (user_id, slot_id, vehicle_number, booking_date, booking_time, duration, end_time, cost_per_hour, total_cost, bkash_number, bkash_pin, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt2->bind_param("iisssisddsss", $user_id, $slot_id, $vehicle_number, $booking_date, $booking_time, $duration, $end_time, $cost_per_hour, $total_cost, $bkash_number, $bkash_pin, $status);
if ($stmt2->execute()) {
    echo "Booking successful! Total cost: $" . number_format($total_cost, 2);
} else {
    echo "Error: " . $stmt2->error;
}

$stmt->close();
$stmt2->close();
$conn->close();
?>