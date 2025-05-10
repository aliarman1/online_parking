<?php
/**
 * My Bookings page for Online Parking
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
        // Get transaction ID from form or generate one if not provided
        $transaction_id = !empty($_POST['transaction_id']) ? $_POST['transaction_id'] : 'TXN' . time() . rand(1000, 9999);

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

// Calculate booking metrics (excluding cancelled bookings)
function calculate_booking_metrics($user_id, $conn) {
    $metrics = [
        'total_bookings' => 0,
        'total_hours' => 0,
        'total_amount' => 0,
        'active_bookings' => 0,
        'completed_bookings' => 0,
        'monthly_usage' => []
    ];

    // Get all non-cancelled bookings
    $metrics_sql = "
        SELECT b.*, p.hourly_rate
        FROM bookings b
        JOIN parking_spots p ON b.spot_id = p.id
        WHERE b.user_id = ? AND b.status != 'cancelled'
        ORDER BY b.start_time ASC
    ";

    $metrics_stmt = $conn->prepare($metrics_sql);
    $metrics_stmt->bind_param("i", $user_id);
    $metrics_stmt->execute();
    $metrics_result = $metrics_stmt->get_result();

    // Monthly usage tracking
    $monthly_data = [];

    while ($booking = $metrics_result->fetch_assoc()) {
        $metrics['total_bookings']++;

        if ($booking['status'] == 'active') {
            $metrics['active_bookings']++;
        } else if ($booking['status'] == 'completed') {
            $metrics['completed_bookings']++;
        }

        // Calculate hours
        $start = strtotime($booking['start_time']);
        $end = strtotime($booking['end_time']);
        $duration_hours = ceil(($end - $start) / 3600);
        $metrics['total_hours'] += $duration_hours;

        // Add to total amount
        $metrics['total_amount'] += $booking['amount'];

        // Track monthly usage
        $month_year = date('M Y', strtotime($booking['start_time']));
        if (!isset($monthly_data[$month_year])) {
            $monthly_data[$month_year] = [
                'hours' => 0,
                'amount' => 0
            ];
        }
        $monthly_data[$month_year]['hours'] += $duration_hours;
        $monthly_data[$month_year]['amount'] += $booking['amount'];
    }

    // Convert monthly data to array for easier use in charts
    foreach ($monthly_data as $month => $data) {
        $metrics['monthly_usage'][] = [
            'month' => $month,
            'hours' => $data['hours'],
            'amount' => $data['amount']
        ];
    }

    // Get last 6 months only
    $metrics['monthly_usage'] = array_slice($metrics['monthly_usage'], -6);

    return $metrics;
}

// Get booking metrics
$booking_metrics = calculate_booking_metrics($user_id, $conn);

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
    <title>My Bookings - Online Parking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .metric-card {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            background-color: #fff;
            margin-bottom: 15px;
            transition: transform 0.3s ease;
        }
        .metric-card:hover {
            transform: translateY(-5px);
        }
        .metric-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 1.5rem;
        }
        .metric-details h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .metric-details p {
            margin: 0;
            color: #666;
        }
        .usage-chart-container {
            height: 250px;
            margin-top: 20px;
        }
    </style>
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

            <!-- Usage Metrics Section -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Parking Usage Metrics</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="metric-card">
                                <div class="metric-icon bg-primary">
                                    <i class="fas fa-car"></i>
                                </div>
                                <div class="metric-details">
                                    <h3><?php echo $booking_metrics['total_bookings']; ?></h3>
                                    <p>Total Bookings</p>
                                    <small class="text-muted">Excluding cancelled</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="metric-card">
                                <div class="metric-icon bg-success">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="metric-details">
                                    <h3><?php echo $booking_metrics['total_hours']; ?></h3>
                                    <p>Total Hours</p>
                                    <small class="text-muted">Hours parked</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="metric-card">
                                <div class="metric-icon bg-info">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div class="metric-details">
                                    <h3>$<?php echo number_format($booking_metrics['total_amount'], 2); ?></h3>
                                    <p>Total Spent</p>
                                    <small class="text-muted">On parking fees</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="metric-card">
                                <div class="metric-icon bg-warning">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="metric-details">
                                    <h3><?php echo $booking_metrics['active_bookings']; ?> / <?php echo $booking_metrics['completed_bookings']; ?></h3>
                                    <p>Active / Completed</p>
                                    <small class="text-muted">Current status</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($booking_metrics['monthly_usage'])): ?>
                    <div class="mt-4">
                        <h5>Monthly Usage Trend</h5>
                        <div class="usage-chart-container">
                            <canvas id="usageChart" width="100%" height="50"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

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

                                                        <div class="mb-3">
                                                            <label for="transaction_id" class="form-label">Transaction ID</label>
                                                            <input type="text" class="form-control" id="transaction_id" name="transaction_id" placeholder="Enter transaction ID (optional)">
                                                            <div class="form-text">If left empty, a transaction ID will be generated automatically.</div>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <?php if (!empty($booking_metrics['monthly_usage'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Prepare data for chart
            const months = <?php echo json_encode(array_column($booking_metrics['monthly_usage'], 'month')); ?>;
            const hoursData = <?php echo json_encode(array_column($booking_metrics['monthly_usage'], 'hours')); ?>;
            const amountData = <?php echo json_encode(array_column($booking_metrics['monthly_usage'], 'amount')); ?>;

            // Create chart
            const ctx = document.getElementById('usageChart').getContext('2d');
            const usageChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Hours Parked',
                            data: hoursData,
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Amount Spent ($)',
                            data: amountData,
                            backgroundColor: 'rgba(75, 192, 192, 0.5)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1,
                            type: 'line',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Hours'
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Amount ($)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Monthly Parking Usage (Excluding Cancelled Bookings)'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.datasetIndex === 1) {
                                        label += '$' + context.raw.toFixed(2);
                                    } else {
                                        label += context.raw + ' hours';
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>
