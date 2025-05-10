<?php
/**
 * Configuration and utility functions for Smart Parking System
 */

// Include database connection
require_once 'database/db.php';

// Site configuration
$site_name = "Smart Parking System";
$site_url = "http://localhost/parking/new_project";
$admin_email = "admin@smartparking.com";

// Function to calculate booking amount
function calculate_booking_amount($start_time, $end_time, $hourly_rate) {
    $start = strtotime($start_time);
    $end = strtotime($end_time);
    $duration = $end - $start;
    $hours = ceil($duration / 3600); // Convert seconds to hours and round up
    return round($hours * $hourly_rate, 2);
}

// Function to get user details
function get_user_details($user_id, $conn) {
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        if (!$stmt) {
            error_log("Database error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            error_log("User not found: ID = " . $user_id);
        }

        return $user;
    } catch (Exception $e) {
        error_log("Exception in get_user_details: " . $e->getMessage());
        return false;
    }
}

// Function to get booking details
function get_booking_details($booking_id, $conn) {
    $stmt = $conn->prepare("
        SELECT b.*, p.spot_number, p.floor_number, p.type, p.hourly_rate, u.name as user_name, u.email as user_email
        FROM bookings b
        JOIN parking_spots p ON b.spot_id = p.id
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ?
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();
    $stmt->close();
    return $booking;
}

// Function to get payment details
function get_payment_details($booking_id, $conn) {
    $stmt = $conn->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    $stmt->close();
    return $payment;
}

// Function to check if a parking spot is available
function is_spot_available($spot_id, $start_time, $end_time, $conn) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM bookings
        WHERE spot_id = ? AND status = 'active' AND
        ((start_time <= ? AND end_time >= ?) OR
         (start_time <= ? AND end_time >= ?) OR
         (start_time >= ? AND end_time <= ?))
    ");
    $stmt->bind_param("issssss", $spot_id, $end_time, $start_time, $start_time, $start_time, $start_time, $end_time);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['count'] == 0;
}

// Function to format date and time
function format_datetime($datetime, $format = 'M d, Y h:i A') {
    return date($format, strtotime($datetime));
}

// Function to display status badge
function status_badge($status) {
    $badge_class = '';
    switch ($status) {
        case 'active':
            $badge_class = 'bg-success';
            break;
        case 'completed':
            $badge_class = 'bg-info';
            break;
        case 'cancelled':
            $badge_class = 'bg-danger';
            break;
        case 'pending':
            $badge_class = 'bg-warning';
            break;
        case 'paid':
            $badge_class = 'bg-success';
            break;
        default:
            $badge_class = 'bg-secondary';
    }

    return '<span class="badge ' . $badge_class . '">' . ucfirst($status) . '</span>';
}

// Function to display flash messages
function display_message() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $message_type = $_SESSION['message_type'] ?? 'info';

        $alert_class = 'alert-info';
        switch ($message_type) {
            case 'success':
                $alert_class = 'alert-success';
                break;
            case 'error':
                $alert_class = 'alert-danger';
                break;
            case 'warning':
                $alert_class = 'alert-warning';
                break;
        }

        echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
        echo $message;
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';

        unset($_SESSION['message'], $_SESSION['message_type']);
    }
}
?>
