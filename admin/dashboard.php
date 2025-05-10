<?php
/**
 * Admin Dashboard for Smart Parking System
 */
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Get user details
$user_id = $_SESSION['user_id'];
$user = get_user_details($user_id, $conn);

// Get statistics
// Total users
$users_sql = "SELECT COUNT(*) as total FROM users";
$users_result = $conn->query($users_sql);
$total_users = $users_result->fetch_assoc()['total'];

// Total parking spots
$spots_sql = "SELECT COUNT(*) as total FROM parking_spots";
$spots_result = $conn->query($spots_sql);
$total_spots = $spots_result->fetch_assoc()['total'];

// Active bookings
$active_bookings_sql = "SELECT COUNT(*) as total FROM bookings WHERE status = 'active'";
$active_bookings_result = $conn->query($active_bookings_sql);
$active_bookings = $active_bookings_result->fetch_assoc()['total'];

// Total revenue
$revenue_sql = "SELECT SUM(amount) as total FROM payments WHERE payment_status = 'paid'";
$revenue_result = $conn->query($revenue_sql);
$total_revenue = $revenue_result->fetch_assoc()['total'] ?? 0;

// Recent bookings
$recent_bookings_sql = "
    SELECT b.*, p.spot_number, p.floor_number, p.type, u.name as user_name, u.email as user_email
    FROM bookings b
    JOIN parking_spots p ON b.spot_id = p.id
    JOIN users u ON b.user_id = u.id
    ORDER BY b.created_at DESC
    LIMIT 5
";
$recent_bookings_result = $conn->query($recent_bookings_sql);
$recent_bookings = [];

while ($booking = $recent_bookings_result->fetch_assoc()) {
    $recent_bookings[] = $booking;
}

// Recent payments
$recent_payments_sql = "
    SELECT p.*, b.vehicle_number, u.name as user_name, u.email as user_email
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN users u ON b.user_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 5
";
$recent_payments_result = $conn->query($recent_payments_sql);
$recent_payments = [];

while ($payment = $recent_payments_result->fetch_assoc()) {
    $recent_payments[] = $payment;
}

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
    <title>Admin Dashboard - Smart Parking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Admin Dashboard</h1>
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
            
            <div class="stats-container">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <div class="stat-info">
                        <h3>Total Users</h3>
                        <p><?php echo $total_users; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-parking"></i>
                    <div class="stat-info">
                        <h3>Parking Spots</h3>
                        <p><?php echo $total_spots; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-calendar-check"></i>
                    <div class="stat-info">
                        <h3>Active Bookings</h3>
                        <p><?php echo $active_bookings; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-money-bill-wave"></i>
                    <div class="stat-info">
                        <h3>Total Revenue</h3>
                        <p>$<?php echo number_format($total_revenue, 2); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="table-container">
                        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                            <h3 class="m-0">Recent Bookings</h3>
                            <a href="bookings.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <?php if (empty($recent_bookings)): ?>
                            <div class="p-3">No recent bookings found.</div>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Spot</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bookings as $booking): ?>
                                        <tr>
                                            <td>#<?php echo $booking['id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($booking['user_name']); ?><br>
                                                <small><?php echo htmlspecialchars($booking['user_email']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($booking['spot_number']); ?><br>
                                                <small>Floor <?php echo $booking['floor_number']; ?>, <?php echo $booking['type']; ?></small>
                                            </td>
                                            <td><?php echo status_badge($booking['status']); ?></td>
                                            <td>$<?php echo number_format($booking['amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="table-container">
                        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                            <h3 class="m-0">Recent Payments</h3>
                            <a href="payments.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <?php if (empty($recent_payments)): ?>
                            <div class="p-3">No recent payments found.</div>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_payments as $payment): ?>
                                        <tr>
                                            <td>#<?php echo $payment['id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($payment['user_name']); ?><br>
                                                <small><?php echo htmlspecialchars($payment['user_email']); ?></small>
                                            </td>
                                            <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                            <td><?php echo format_datetime($payment['payment_date']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12">
                    <div class="table-container">
                        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                            <h3 class="m-0">Parking Spot Occupancy</h3>
                            <a href="parking-spots.php" class="btn btn-sm btn-primary">Manage Spots</a>
                        </div>
                        <div class="p-3">
                            <div class="row">
                                <?php
                                // Get occupancy by floor
                                $floors_sql = "SELECT floor_number, COUNT(*) as total, SUM(is_occupied) as occupied FROM parking_spots GROUP BY floor_number ORDER BY floor_number";
                                $floors_result = $conn->query($floors_sql);
                                
                                while ($floor = $floors_result->fetch_assoc()):
                                    $occupancy_rate = ($floor['total'] > 0) ? round(($floor['occupied'] / $floor['total']) * 100) : 0;
                                    $progress_class = 'bg-success';
                                    
                                    if ($occupancy_rate > 70) {
                                        $progress_class = 'bg-warning';
                                    }
                                    
                                    if ($occupancy_rate > 90) {
                                        $progress_class = 'bg-danger';
                                    }
                                ?>
                                    <div class="col-md-4 mb-3">
                                        <h5>Floor <?php echo $floor['floor_number']; ?></h5>
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar <?php echo $progress_class; ?>" role="progressbar" style="width: <?php echo $occupancy_rate; ?>%;" aria-valuenow="<?php echo $occupancy_rate; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $occupancy_rate; ?>% Occupied
                                            </div>
                                        </div>
                                        <small class="text-muted"><?php echo $floor['occupied']; ?> of <?php echo $floor['total']; ?> spots occupied</small>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
