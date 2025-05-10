<?php
/**
 * User Details Page for Online Parking Admin Panel
 */
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Check if user ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['message'] = "User ID is required.";
    $_SESSION['message_type'] = "error";
    header("Location: users.php");
    exit();
}

$user_id = $_GET['id'];

// Get user information
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    $_SESSION['message'] = "User not found.";
    $_SESSION['message_type'] = "error";
    header("Location: users.php");
    exit();
}

$user = $user_result->fetch_assoc();

// Get user's bookings
$bookings_sql = "SELECT b.*, p.spot_number, p.floor_number, p.type 
                FROM bookings b 
                JOIN parking_spots p ON b.spot_id = p.id 
                WHERE b.user_id = ? 
                ORDER BY b.created_at DESC";
$bookings_stmt = $conn->prepare($bookings_sql);
$bookings_stmt->bind_param("i", $user_id);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();

// Get booking statistics
$stats_sql = "SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_paid
              FROM bookings 
              WHERE user_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - Online Parking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>User Details</h1>
                <div class="d-flex gap-2">
                    <a href="users.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                    <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit User
                    </a>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center mb-4 mb-md-0">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random&size=128"
                                 alt="User Avatar"
                                 class="rounded-circle img-thumbnail mb-3"
                                 style="width: 128px; height: 128px;">
                            <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                            <span class="badge bg-<?php echo $user['is_admin'] ? 'primary' : 'secondary'; ?> mb-2">
                                <?php echo $user['is_admin'] ? 'Administrator' : 'Regular User'; ?>
                            </span>
                            <p class="text-muted">
                                <small>Member since <?php echo date('M d, Y', strtotime($user['created_at'])); ?></small>
                            </p>
                        </div>
                        <div class="col-md-9">
                            <h5 class="border-bottom pb-2 mb-3">User Information</h5>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Email:</strong></p>
                                    <p class="text-muted">
                                        <i class="fas fa-envelope me-2"></i>
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>User ID:</strong></p>
                                    <p class="text-muted">
                                        <i class="fas fa-id-card me-2"></i>
                                        <?php echo $user['id']; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Last Login:</strong></p>
                                    <p class="text-muted">
                                        <i class="fas fa-clock me-2"></i>
                                        <?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Account Status:</strong></p>
                                    <p class="text-success">
                                        <i class="fas fa-check-circle me-2"></i>
                                        Active
                                    </p>
                                </div>
                            </div>
                            
                            <h5 class="border-bottom pb-2 mb-3 mt-4">Booking Statistics</h5>
                            <div class="row">
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center py-3">
                                            <h3 class="mb-0"><?php echo $stats['total_bookings'] ?? 0; ?></h3>
                                            <p class="text-muted mb-0">Total Bookings</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center py-3">
                                            <h3 class="mb-0"><?php echo $stats['active_bookings'] ?? 0; ?></h3>
                                            <p class="text-muted mb-0">Active</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center py-3">
                                            <h3 class="mb-0"><?php echo $stats['completed_bookings'] ?? 0; ?></h3>
                                            <p class="text-muted mb-0">Completed</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center py-3">
                                            <h3 class="mb-0">$<?php echo number_format($stats['total_paid'] ?? 0, 2); ?></h3>
                                            <p class="text-muted mb-0">Total Paid</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Booking History</h5>
                </div>
                <div class="card-body">
                    <?php if ($bookings_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Spot</th>
                                        <th>Vehicle</th>
                                        <th>Duration</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $bookings_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $booking['id']; ?></td>
                                            <td>
                                                <span class="badge bg-info text-white">
                                                    <?php echo htmlspecialchars($booking['spot_number']); ?>
                                                </span>
                                                <small class="d-block text-muted">
                                                    Floor <?php echo $booking['floor_number']; ?>, 
                                                    <?php echo ucfirst($booking['type']); ?>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($booking['vehicle_number']); ?></td>
                                            <td>
                                                <?php 
                                                    $start = new DateTime($booking['start_time']);
                                                    $end = new DateTime($booking['end_time']);
                                                    $duration = $start->diff($end);
                                                    
                                                    if ($duration->d > 0) {
                                                        echo $duration->format('%d days, %h hrs');
                                                    } else {
                                                        echo $duration->format('%h hrs, %i mins');
                                                    }
                                                ?>
                                                <small class="d-block text-muted">
                                                    <?php echo date('M d, H:i', strtotime($booking['start_time'])); ?> - 
                                                    <?php echo date('M d, H:i', strtotime($booking['end_time'])); ?>
                                                </small>
                                            </td>
                                            <td>$<?php echo number_format($booking['amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $booking['status'] === 'active' ? 'success' : 
                                                        ($booking['status'] === 'completed' ? 'primary' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $booking['payment_status'] === 'paid' ? 'success' : 'warning'; 
                                                ?>">
                                                    <?php echo ucfirst($booking['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($booking['created_at'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p>This user has no booking history.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
