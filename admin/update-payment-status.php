<?php
/**
 * Update Payment Status for Online Parking Admin Panel
 */
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Handle mark as paid action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_paid'])) {
    $payment_id = $_POST['payment_id'];
    
    // Validate payment ID
    if (empty($payment_id)) {
        $_SESSION['message'] = "Invalid payment ID.";
        $_SESSION['message_type'] = "error";
        header("Location: payments.php");
        exit();
    }
    
    // Get payment details
    $payment_sql = "SELECT p.*, b.id as booking_id FROM payments p JOIN bookings b ON p.booking_id = b.id WHERE p.id = ?";
    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->bind_param("i", $payment_id);
    $payment_stmt->execute();
    $result = $payment_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = "Payment not found.";
        $_SESSION['message_type'] = "error";
        header("Location: payments.php");
        exit();
    }
    
    $payment = $result->fetch_assoc();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update payment status
        $update_payment_sql = "UPDATE payments SET payment_status = 'paid', updated_at = NOW() WHERE id = ?";
        $update_payment_stmt = $conn->prepare($update_payment_sql);
        $update_payment_stmt->bind_param("i", $payment_id);
        $update_payment_stmt->execute();
        
        // Update booking payment status
        $update_booking_sql = "UPDATE bookings SET payment_status = 'paid', updated_at = NOW() WHERE id = ?";
        $update_booking_stmt = $conn->prepare($update_booking_sql);
        $update_booking_stmt->bind_param("i", $payment['booking_id']);
        $update_booking_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['message'] = "Payment has been marked as paid successfully.";
        $_SESSION['message_type'] = "success";
        header("Location: view-payment.php?id=" . $payment_id);
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $_SESSION['message'] = "Failed to update payment status: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: view-payment.php?id=" . $payment_id);
        exit();
    }
} else {
    // Invalid request
    header("Location: payments.php");
    exit();
}
?>
