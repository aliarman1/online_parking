<?php
/**
 * Booking History page for Smart Parking System
 */
require_once 'config.php';

// Check if user is logged in
require_login();

// Get user details
$user_id = $_SESSION['user_id'];
$user = get_user_details($user_id, $conn);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get total number of completed or cancelled bookings
$total_sql = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ? AND (status = 'completed' OR status = 'cancelled')";
$total_stmt = $conn->prepare($total_sql);
$total_stmt->bind_param("i", $user_id);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_bookings = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_bookings / $records_per_page);

// Get bookings with pagination
$bookings_sql = "
    SELECT b.*, p.spot_number, p.floor_number, p.type, p.hourly_rate
    FROM bookings b
    JOIN parking_spots p ON b.spot_id = p.id
    WHERE b.user_id = ? AND (b.status = 'completed' OR b.status = 'cancelled')
    ORDER BY b.created_at DESC
    LIMIT ?, ?
";
$bookings_stmt = $conn->prepare($bookings_sql);
$bookings_stmt->bind_param("iii", $user_id, $offset, $records_per_page);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking History - Smart Parking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Booking History</h1>
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
            
            <?php if (empty($bookings)): ?>
                <div class="alert alert-info">
                    No booking history found. <a href="dashboard.php">Book a parking spot</a> to get started.
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
                                        <?php if ($booking['payment_status'] === 'paid'): ?>
                                            <a href="view-receipt.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-receipt"></i> Receipt
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No receipt</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-center mt-4">
                            <nav aria-label="Booking history pagination">
                                <ul class="pagination">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
