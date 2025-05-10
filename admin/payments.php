<?php
/**
 * Payments Management for Online Parking Admin Panel
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

// Handle search and filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : 'all';
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Build the WHERE clause
$where_sql = "WHERE 1=1"; // Always true condition to start with
$params = [];
$types = "";

if (!empty($search)) {
    $where_sql .= " AND (p.transaction_id LIKE ? OR b.vehicle_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if ($payment_method !== 'all') {
    $where_sql .= " AND p.payment_method = ?";
    $params[] = $payment_method;
    $types .= "s";
}

if ($payment_status !== 'all') {
    $where_sql .= " AND p.payment_status = ?";
    $params[] = $payment_status;
    $types .= "s";
}

if (!empty($date_from)) {
    $where_sql .= " AND DATE(p.payment_date) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $where_sql .= " AND DATE(p.payment_date) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Get total number of payments matching the filters
$total_sql = "SELECT COUNT(*) as total FROM payments p
              JOIN bookings b ON p.booking_id = b.id
              JOIN users u ON b.user_id = u.id
              $where_sql";

if (!empty($params)) {
    $total_stmt = $conn->prepare($total_sql);
    $total_stmt->bind_param($types, ...$params);
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
} else {
    $total_result = $conn->query($total_sql);
}

$total_payments = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_payments / $records_per_page);

// Get payments with pagination and filters
$payments_sql = "SELECT p.*, b.vehicle_number, b.start_time, b.end_time, b.status as booking_status,
                u.name as user_name, u.email as user_email, ps.spot_number, ps.floor_number, ps.type
                FROM payments p
                JOIN bookings b ON p.booking_id = b.id
                JOIN users u ON b.user_id = u.id
                JOIN parking_spots ps ON b.spot_id = ps.id
                $where_sql
                ORDER BY p.id DESC
                LIMIT ?, ?";

$params[] = $offset;
$params[] = $records_per_page;
$types .= "ii";

$payments_stmt = $conn->prepare($payments_sql);
$payments_stmt->bind_param($types, ...$params);
$payments_stmt->execute();
$payments = $payments_stmt->get_result();

// Get payment statistics
$stats_sql = "SELECT
                COUNT(*) as total_count,
                SUM(amount) as total_amount,
                COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_count,
                COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN payment_method = 'cash' THEN 1 END) as cash_count,
                COUNT(CASE WHEN payment_method = 'card' THEN 1 END) as card_count,
                COUNT(CASE WHEN payment_method = 'paypal' THEN 1 END) as paypal_count,
                COUNT(CASE WHEN payment_method = 'bank_transfer' THEN 1 END) as bank_transfer_count
              FROM payments";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

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
    <title>Payments Management - Online Parking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Payments Management</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random" alt="User Avatar">
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Payment Statistics -->
            <div class="stats-container">
                <div class="stat-card">
                    <i class="fas fa-money-bill-wave"></i>
                    <div class="stat-info">
                        <h3>Total Payments</h3>
                        <p><?php echo $stats['total_count']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-dollar-sign"></i>
                    <div class="stat-info">
                        <h3>Total Revenue</h3>
                        <p>$<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <div class="stat-info">
                        <h3>Paid Payments</h3>
                        <p><?php echo $stats['paid_count']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <div class="stat-info">
                        <h3>Pending Payments</h3>
                        <p><?php echo $stats['pending_count']; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Search and Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="" method="GET" class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" name="search" placeholder="Search by transaction ID, vehicle, user..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <select name="payment_method" class="form-select">
                                <option value="all" <?php echo $payment_method === 'all' ? 'selected' : ''; ?>>All Methods</option>
                                <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="card" <?php echo $payment_method === 'card' ? 'selected' : ''; ?>>Card</option>
                                <option value="paypal" <?php echo $payment_method === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                <option value="bank_transfer" <?php echo $payment_method === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <select name="payment_status" class="form-select">
                                <option value="all" <?php echo $payment_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="paid" <?php echo $payment_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="pending" <?php echo $payment_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_from" placeholder="From Date" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_to" placeholder="To Date" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        
                        <div class="col-md-12 d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="payments.php" class="btn btn-secondary">
                                <i class="fas fa-sync-alt"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Payments Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Transaction ID</th>
                                    <th>User</th>
                                    <th>Vehicle</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($payments->num_rows > 0): ?>
                                    <?php while ($payment = $payments->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $payment['id']; ?></td>
                                        <td><?php echo htmlspecialchars($payment['transaction_id']); ?></td>
                                        <td>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($payment['user_name']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($payment['user_email']); ?></div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment['vehicle_number']); ?></td>
                                        <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                switch($payment['payment_method']) {
                                                    case 'cash': echo 'success'; break;
                                                    case 'card': echo 'primary'; break;
                                                    case 'paypal': echo 'info'; break;
                                                    case 'bank_transfer': echo 'warning'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>">
                                                <?php echo ucfirst($payment['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($payment['payment_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $payment['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($payment['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="view-payment.php?id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="../view-receipt.php?booking_id=<?php echo $payment['booking_id']; ?>" target="_blank" class="btn btn-sm btn-success" title="View Receipt">
                                                    <i class="fas fa-receipt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">No payments found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&payment_method=<?php echo urlencode($payment_method); ?>&payment_status=<?php echo urlencode($payment_status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                    Previous
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&payment_method=<?php echo urlencode($payment_method); ?>&payment_status=<?php echo urlencode($payment_status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&payment_method=<?php echo urlencode($payment_method); ?>&payment_status=<?php echo urlencode($payment_status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                    Next
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
