<?php
/**
 * View Receipt page for Smart Parking System
 */
require_once 'config.php';

// Check if user is logged in
require_login();

// Get user details
$user_id = $_SESSION['user_id'];
$user = get_user_details($user_id, $conn);

// Check if booking ID is provided
if (!isset($_GET['booking_id'])) {
    $_SESSION['message'] = "Booking ID is required.";
    $_SESSION['message_type'] = "error";
    header("Location: my-bookings.php");
    exit();
}

$booking_id = $_GET['booking_id'];

// Get booking details
$booking_sql = "
    SELECT b.*, p.spot_number, p.floor_number, p.type, p.hourly_rate, u.name as user_name, u.email as user_email
    FROM bookings b
    JOIN parking_spots p ON b.spot_id = p.id
    JOIN users u ON b.user_id = u.id
    WHERE b.id = ? AND b.user_id = ? AND b.payment_status = 'paid'
";
$booking_stmt = $conn->prepare($booking_sql);
$booking_stmt->bind_param("ii", $booking_id, $user_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();

if ($booking_result->num_rows === 0) {
    $_SESSION['message'] = "Invalid booking or payment not processed.";
    $_SESSION['message_type'] = "error";
    header("Location: my-bookings.php");
    exit();
}

$booking = $booking_result->fetch_assoc();

// Get payment details
$payment_sql = "SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1";
$payment_stmt = $conn->prepare($payment_sql);
$payment_stmt->bind_param("i", $booking_id);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();
$payment = $payment_result->fetch_assoc();

// Calculate duration in hours
$start_time = strtotime($booking['start_time']);
$end_time = strtotime($booking['end_time']);
$duration_hours = ceil(($end_time - $start_time) / 3600);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Smart Parking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .receipt-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .receipt-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .receipt-logo img {
            width: 50px;
            height: 50px;
        }
        
        .receipt-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #00416A;
        }
        
        .receipt-subtitle {
            color: #666;
            font-size: 1rem;
        }
        
        .receipt-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .receipt-section {
            margin-bottom: 1.5rem;
        }
        
        .receipt-section h3 {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: #00416A;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
        }
        
        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .receipt-item-label {
            font-weight: 500;
            color: #555;
        }
        
        .receipt-total {
            font-size: 1.2rem;
            font-weight: 600;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            color: #666;
        }
        
        .receipt-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        @media print {
            .sidebar, .page-header, .receipt-actions, .btn {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .receipt-container {
                box-shadow: none !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Receipt</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random" alt="User Avatar">
                </div>
            </div>
            
            <div class="receipt-container">
                <div class="receipt-header">
                    <div class="receipt-logo">
                        <img src="https://img.icons8.com/color/96/000000/parking.png" alt="Smart Parking Logo">
                        <h2>Smart Parking</h2>
                    </div>
                    <div class="receipt-title">Payment Receipt</div>
                    <div class="receipt-subtitle">Thank you for using Smart Parking System</div>
                </div>
                
                <div class="receipt-info">
                    <div class="receipt-section">
                        <h3>Booking Information</h3>
                        <div class="receipt-item">
                            <span class="receipt-item-label">Booking ID:</span>
                            <span>#<?php echo $booking['id']; ?></span>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-item-label">Booking Date:</span>
                            <span><?php echo format_datetime($booking['created_at'], 'M d, Y'); ?></span>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-item-label">Status:</span>
                            <span><?php echo ucfirst($booking['status']); ?></span>
                        </div>
                    </div>
                    
                    <div class="receipt-section">
                        <h3>Customer Information</h3>
                        <div class="receipt-item">
                            <span class="receipt-item-label">Name:</span>
                            <span><?php echo htmlspecialchars($booking['user_name']); ?></span>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-item-label">Email:</span>
                            <span><?php echo htmlspecialchars($booking['user_email']); ?></span>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-item-label">Vehicle Number:</span>
                            <span><?php echo htmlspecialchars($booking['vehicle_number']); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="receipt-section">
                    <h3>Parking Details</h3>
                    <div class="receipt-item">
                        <span class="receipt-item-label">Parking Spot:</span>
                        <span><?php echo htmlspecialchars($booking['spot_number']); ?> (Floor <?php echo $booking['floor_number']; ?>)</span>
                    </div>
                    <div class="receipt-item">
                        <span class="receipt-item-label">Spot Type:</span>
                        <span><?php echo htmlspecialchars($booking['type']); ?></span>
                    </div>
                    <div class="receipt-item">
                        <span class="receipt-item-label">Start Time:</span>
                        <span><?php echo format_datetime($booking['start_time']); ?></span>
                    </div>
                    <div class="receipt-item">
                        <span class="receipt-item-label">End Time:</span>
                        <span><?php echo format_datetime($booking['end_time']); ?></span>
                    </div>
                    <div class="receipt-item">
                        <span class="receipt-item-label">Duration:</span>
                        <span><?php echo $duration_hours; ?> hour<?php echo $duration_hours > 1 ? 's' : ''; ?></span>
                    </div>
                </div>
                
                <div class="receipt-section">
                    <h3>Payment Information</h3>
                    <div class="receipt-item">
                        <span class="receipt-item-label">Payment Date:</span>
                        <span><?php echo format_datetime($payment['payment_date']); ?></span>
                    </div>
                    <div class="receipt-item">
                        <span class="receipt-item-label">Payment Method:</span>
                        <span><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></span>
                    </div>
                    <div class="receipt-item">
                        <span class="receipt-item-label">Transaction ID:</span>
                        <span><?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                    </div>
                    <div class="receipt-item">
                        <span class="receipt-item-label">Hourly Rate:</span>
                        <span>$<?php echo number_format($booking['hourly_rate'], 2); ?></span>
                    </div>
                    <div class="receipt-item receipt-total">
                        <span class="receipt-item-label">Total Amount:</span>
                        <span>$<?php echo number_format($booking['amount'], 2); ?></span>
                    </div>
                </div>
                
                <div class="receipt-footer">
                    <p>Thank you for choosing Smart Parking System.</p>
                    <p>For any inquiries, please contact us at support@smartparking.com</p>
                    <p>&copy; <?php echo date('Y'); ?> Smart Parking System. All rights reserved.</p>
                </div>
                
                <div class="receipt-actions">
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                    <a href="my-bookings.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Bookings
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
