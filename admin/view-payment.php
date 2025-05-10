<?php
/**
 * Payment Details View for Online Parking Admin Panel
 */
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Check if payment ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['message'] = "No payment ID provided.";
    $_SESSION['message_type'] = "error";
    header("Location: payments.php");
    exit();
}

$payment_id = $_GET['id'];

// Get payment details with related information
$payment_sql = "SELECT p.*, b.vehicle_number, b.start_time, b.end_time, b.status as booking_status, 
                u.id as user_id, u.name as user_name, u.email as user_email, 
                ps.id as spot_id, ps.spot_number, ps.floor_number, ps.type, ps.hourly_rate
                FROM payments p
                JOIN bookings b ON p.booking_id = b.id
                JOIN users u ON b.user_id = u.id
                JOIN parking_spots ps ON b.spot_id = ps.id
                WHERE p.id = ?";
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

// Calculate booking duration
$start_time = new DateTime($payment['start_time']);
$end_time = new DateTime($payment['end_time']);
$duration = $start_time->diff($end_time);

// Format duration
$duration_formatted = '';
if ($duration->d > 0) {
    $duration_formatted .= $duration->d . ' day' . ($duration->d > 1 ? 's' : '') . ' ';
}
if ($duration->h > 0) {
    $duration_formatted .= $duration->h . ' hour' . ($duration->h > 1 ? 's' : '') . ' ';
}
if ($duration->i > 0) {
    $duration_formatted .= $duration->i . ' minute' . ($duration->i > 1 ? 's' : '');
}

// Get user details
$user_id = $_SESSION['user_id'];
$user = get_user_details($user_id, $conn);

// Get any messages from the session
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$message_type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : '';
unset($_SESSION['message'], $_SESSION['message_type']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details - Online Parking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Payment Details</h1>
                <div class="d-flex gap-2">
                    <a href="payments.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Payments
                    </a>
                    <a href="../view-receipt.php?booking_id=<?php echo $payment['booking_id']; ?>" target="_blank" class="btn btn-success">
                        <i class="fas fa-receipt"></i> View Receipt
                    </a>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Payment Information -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Payment Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Payment ID</h6>
                                    <p class="fs-5"><?php echo $payment['id']; ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Transaction ID</h6>
                                    <p class="fs-5"><?php echo htmlspecialchars($payment['transaction_id']); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Amount</h6>
                                    <p class="fs-5 fw-bold text-success">$<?php echo number_format($payment['amount'], 2); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Payment Date</h6>
                                    <p class="fs-5"><?php echo date('F d, Y h:i A', strtotime($payment['payment_date'])); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Payment Method</h6>
                                    <span class="badge bg-<?php 
                                        switch($payment['payment_method']) {
                                            case 'cash': echo 'success'; break;
                                            case 'card': echo 'primary'; break;
                                            case 'paypal': echo 'info'; break;
                                            case 'bank_transfer': echo 'warning'; break;
                                            default: echo 'secondary';
                                        }
                                    ?> fs-6 px-3 py-2">
                                        <?php echo ucfirst($payment['payment_method']); ?>
                                    </span>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Payment Status</h6>
                                    <span class="badge bg-<?php echo $payment['payment_status'] === 'paid' ? 'success' : 'warning'; ?> fs-6 px-3 py-2">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h5 class="mb-3">Booking Details</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Booking ID</h6>
                                    <p><?php echo $payment['booking_id']; ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Vehicle Number</h6>
                                    <p><?php echo htmlspecialchars($payment['vehicle_number']); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Start Time</h6>
                                    <p><?php echo date('F d, Y h:i A', strtotime($payment['start_time'])); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">End Time</h6>
                                    <p><?php echo date('F d, Y h:i A', strtotime($payment['end_time'])); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Duration</h6>
                                    <p><?php echo $duration_formatted; ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Booking Status</h6>
                                    <span class="badge bg-<?php 
                                        switch($payment['booking_status']) {
                                            case 'active': echo 'primary'; break;
                                            case 'completed': echo 'success'; break;
                                            case 'cancelled': echo 'danger'; break;
                                            default: echo 'secondary';
                                        }
                                    ?>">
                                        <?php echo ucfirst($payment['booking_status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h5 class="mb-3">Parking Spot Details</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <h6 class="text-muted mb-1">Spot Number</h6>
                                    <p><?php echo htmlspecialchars($payment['spot_number']); ?></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <h6 class="text-muted mb-1">Floor Number</h6>
                                    <p><?php echo $payment['floor_number']; ?></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <h6 class="text-muted mb-1">Spot Type</h6>
                                    <p><?php echo ucfirst($payment['type']); ?></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <h6 class="text-muted mb-1">Hourly Rate</h6>
                                    <p>$<?php echo number_format($payment['hourly_rate'], 2); ?></p>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <a href="bookings.php?search=<?php echo urlencode($payment['vehicle_number']); ?>" class="btn btn-primary">
                                    <i class="fas fa-calendar-check"></i> View Booking Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- User Information -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">User Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($payment['user_name']); ?>&background=random&size=100" 
                                     alt="User Avatar" 
                                     class="rounded-circle img-thumbnail" 
                                     style="width: 100px; height: 100px;">
                                <h5 class="mt-3 mb-0"><?php echo htmlspecialchars($payment['user_name']); ?></h5>
                                <p class="text-muted"><?php echo htmlspecialchars($payment['user_email']); ?></p>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="users.php?search=<?php echo urlencode($payment['user_email']); ?>" class="btn btn-primary">
                                    <i class="fas fa-user"></i> View User Profile
                                </a>
                                <a href="bookings.php?search=<?php echo urlencode($payment['user_email']); ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-calendar-check"></i> View User Bookings
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="../view-receipt.php?booking_id=<?php echo $payment['booking_id']; ?>" target="_blank" class="btn btn-success">
                                    <i class="fas fa-receipt"></i> View Receipt
                                </a>
                                <?php if ($payment['payment_status'] === 'pending'): ?>
                                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#markAsPaidModal">
                                    <i class="fas fa-check-circle"></i> Mark as Paid
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($payment['payment_status'] === 'pending'): ?>
    <!-- Mark as Paid Modal -->
    <div class="modal fade" id="markAsPaidModal" tabindex="-1" aria-labelledby="markAsPaidModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="markAsPaidModalLabel">Mark Payment as Paid</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="update-payment-status.php" method="POST">
                    <div class="modal-body">
                        <p>Are you sure you want to mark this payment as paid?</p>
                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="mark_as_paid" class="btn btn-warning">Mark as Paid</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
