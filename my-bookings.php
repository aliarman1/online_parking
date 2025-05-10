<?php
/**
 * My Bookings page for Smart Parking System
 */
require_once 'config.php';

// Check if user is logged in
require_login();

// Get user details
$user_id = $_SESSION['user_id'];
$user = get_user_details($user_id, $conn);

// Process booking cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['message'] = "Invalid form submission. Please try again.";
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
    
    $booking_id = $_POST['booking_id'];
    
    // Verify that the booking belongs to the user
    $verify_sql = "SELECT b.*, p.id as spot_id FROM bookings b JOIN parking_spots p ON b.spot_id = p.id WHERE b.id = ? AND b.user_id = ? AND b.status = 'active'";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $booking_id, $user_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = "Invalid booking or booking is not active.";
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
    
    $booking = $result->fetch_assoc();
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update booking status
        $update_sql = "UPDATE bookings SET status = 'cancelled' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $booking_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to cancel booking: " . $conn->error);
        }
        
        // Free up the parking spot
        $update_spot_sql = "UPDATE parking_spots SET is_occupied = 0, vehicle_number = NULL WHERE id = ?";
        $update_spot_stmt = $conn->prepare($update_spot_sql);
        $update_spot_stmt->bind_param("i", $booking['spot_id']);
        
        if (!$update_spot_stmt->execute()) {
            throw new Exception("Failed to update parking spot status: " . $conn->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['message'] = "Booking cancelled successfully.";
        $_SESSION['message_type'] = "success";
        header("Location: my-bookings.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
}

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['message'] = "Invalid form submission. Please try again.";
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
    
    $booking_id = $_POST['booking_id'];
    $payment_method = $_POST['payment_method'];
    
    // Verify that the booking belongs to the user
    $verify_sql = "SELECT b.*, p.id as spot_id FROM bookings b JOIN parking_spots p ON b.spot_id = p.id WHERE b.id = ? AND b.user_id = ? AND b.payment_status = 'pending'";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $booking_id, $user_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = "Invalid booking or payment already processed.";
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
    
    $booking = $result->fetch_assoc();
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Generate a transaction ID
        $transaction_id = 'TXN' . time() . rand(1000, 9999);
        
        // Insert payment record
        $payment_sql = "INSERT INTO payments (booking_id, amount, payment_date, payment_method, transaction_id) VALUES (?, ?, NOW(), ?, ?)";
        $payment_stmt = $conn->prepare($payment_sql);
        $payment_stmt->bind_param("idss", $booking_id, $booking['amount'], $payment_method, $transaction_id);
        
        if (!$payment_stmt->execute()) {
            throw new Exception("Failed to process payment: " . $conn->error);
        }
        
        // Update booking payment status
        $update_sql = "UPDATE bookings SET payment_status = 'paid' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $booking_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to update booking payment status: " . $conn->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['message'] = "Payment processed successfully! Transaction ID: " . $transaction_id;
        $_SESSION['message_type'] = "success";
        header("Location: view-receipt.php?booking_id=" . $booking_id);
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
}

// Get bookings for the user
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$bookings_sql = "
    SELECT b.*, p.spot_number, p.floor_number, p.type, p.hourly_rate
    FROM bookings b
    JOIN parking_spots p ON b.spot_id = p.id
    WHERE b.user_id = ?
";

if (!empty($status_filter)) {
    $bookings_sql .= " AND b.status = ?";
}

$bookings_sql .= " ORDER BY b.created_at DESC";

$bookings_stmt = $conn->prepare($bookings_sql);

if (!empty($status_filter)) {
    $bookings_stmt->bind_param("is", $user_id, $status_filter);
} else {
    $bookings_stmt->bind_param("i", $user_id);
}

$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();
$bookings = [];

while ($booking = $bookings_result->fetch_assoc()) {
    $bookings[] = $booking;
}

// Get any messages from the session
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$message_type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Generate CSRF token
$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Smart Parking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">My Bookings</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random" alt="User Avatar">
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'error' ? 'danger' : $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="filter-section mb-4">
                <div class="btn-group" role="group" aria-label="Booking status filter">
                    <a href="my-bookings.php" class="btn <?php echo empty($status_filter) ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                    <a href="my-bookings.php?status=active" class="btn <?php echo $status_filter === 'active' ? 'btn-primary' : 'btn-outline-primary'; ?>">Active</a>
                    <a href="my-bookings.php?status=completed" class="btn <?php echo $status_filter === 'completed' ? 'btn-primary' : 'btn-outline-primary'; ?>">Completed</a>
                    <a href="my-bookings.php?status=cancelled" class="btn <?php echo $status_filter === 'cancelled' ? 'btn-primary' : 'btn-outline-primary'; ?>">Cancelled</a>
                </div>
            </div>
            
            <?php if (empty($bookings)): ?>
                <div class="alert alert-info">
                    No bookings found. <a href="dashboard.php">Book a parking spot</a> to get started.
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Spot</th>
                                <th>Vehicle</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>#<?php echo $booking['id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($booking['spot_number']); ?><br>
                                        <small>Floor <?php echo $booking['floor_number']; ?>, <?php echo $booking['type']; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['vehicle_number']); ?></td>
                                    <td><?php echo format_datetime($booking['start_time']); ?></td>
                                    <td><?php echo format_datetime($booking['end_time']); ?></td>
                                    <td>$<?php echo number_format($booking['amount'], 2); ?></td>
                                    <td><?php echo status_badge($booking['status']); ?></td>
                                    <td><?php echo status_badge($booking['payment_status']); ?></td>
                                    <td>
                                        <?php if ($booking['status'] === 'active' && $booking['payment_status'] === 'pending'): ?>
                                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal<?php echo $booking['id']; ?>">
                                                <i class="fas fa-credit-card"></i> Pay
                                            </button>
                                            
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <button type="submit" name="cancel_booking" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this booking?')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                        <?php elseif ($booking['status'] === 'active' && $booking['payment_status'] === 'paid'): ?>
                                            <a href="view-receipt.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-receipt"></i> Receipt
                                            </a>
                                            
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <button type="submit" name="cancel_booking" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this booking?')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                        <?php elseif ($booking['status'] === 'completed' || $booking['status'] === 'cancelled'): ?>
                                            <?php if ($booking['payment_status'] === 'paid'): ?>
                                                <a href="view-receipt.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-receipt"></i> Receipt
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No actions</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <!-- Payment Modal -->
                                <?php if ($booking['status'] === 'active' && $booking['payment_status'] === 'pending'): ?>
                                    <div class="modal fade" id="paymentModal<?php echo $booking['id']; ?>" tabindex="-1" aria-labelledby="paymentModalLabel<?php echo $booking['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="paymentModalLabel<?php echo $booking['id']; ?>">Process Payment</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <p><strong>Booking Details:</strong></p>
                                                            <p>Spot: <?php echo htmlspecialchars($booking['spot_number']); ?> (Floor <?php echo $booking['floor_number']; ?>)</p>
                                                            <p>Vehicle: <?php echo htmlspecialchars($booking['vehicle_number']); ?></p>
                                                            <p>Duration: <?php echo format_datetime($booking['start_time']); ?> to <?php echo format_datetime($booking['end_time']); ?></p>
                                                            <p>Amount: $<?php echo number_format($booking['amount'], 2); ?></p>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="payment_method" class="form-label">Payment Method</label>
                                                            <select class="form-select" id="payment_method" name="payment_method" required>
                                                                <option value="">Select payment method</option>
                                                                <option value="card">Credit/Debit Card</option>
                                                                <option value="paypal">PayPal</option>
                                                                <option value="bank_transfer">Bank Transfer</option>
                                                                <option value="cash">Cash</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" name="process_payment" class="btn btn-primary">Process Payment</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
