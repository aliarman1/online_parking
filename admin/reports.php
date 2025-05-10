<?php
/**
 * Reports and Analytics for Online Parking Admin Panel
 */
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Handle date range filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'revenue';

// Get revenue statistics
$revenue_sql = "SELECT
                SUM(p.amount) as total_revenue,
                COUNT(p.id) as total_transactions,
                AVG(p.amount) as average_transaction,
                SUM(CASE WHEN p.payment_method = 'cash' THEN p.amount ELSE 0 END) as cash_revenue,
                SUM(CASE WHEN p.payment_method = 'card' THEN p.amount ELSE 0 END) as card_revenue,
                SUM(CASE WHEN p.payment_method = 'paypal' THEN p.amount ELSE 0 END) as paypal_revenue,
                SUM(CASE WHEN p.payment_method = 'bank_transfer' THEN p.amount ELSE 0 END) as bank_transfer_revenue
                FROM payments p
                WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                AND p.payment_status = 'paid'";

$revenue_stmt = $conn->prepare($revenue_sql);
$revenue_stmt->bind_param("ss", $date_from, $date_to);
$revenue_stmt->execute();
$revenue_stats = $revenue_stmt->get_result()->fetch_assoc();

// Get booking statistics
$booking_sql = "SELECT
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                AVG(TIMESTAMPDIFF(HOUR, start_time, end_time)) as avg_duration
                FROM bookings
                WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";

$booking_stmt = $conn->prepare($booking_sql);
$booking_stmt->bind_param("ss", $date_from, $date_to);
$booking_stmt->execute();
$booking_stats = $booking_stmt->get_result()->fetch_assoc();

// Get revenue by spot type
$spot_revenue_sql = "SELECT
                    ps.type,
                    COUNT(b.id) as booking_count,
                    SUM(p.amount) as total_revenue,
                    AVG(p.amount) as avg_revenue
                    FROM payments p
                    JOIN bookings b ON p.booking_id = b.id
                    JOIN parking_spots ps ON b.spot_id = ps.id
                    WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                    AND p.payment_status = 'paid'
                    GROUP BY ps.type
                    ORDER BY total_revenue DESC";

$spot_revenue_stmt = $conn->prepare($spot_revenue_sql);
$spot_revenue_stmt->bind_param("ss", $date_from, $date_to);
$spot_revenue_stmt->execute();
$spot_revenue = $spot_revenue_stmt->get_result();

// Get monthly revenue data for chart
$monthly_revenue_sql = "SELECT
                        DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                        SUM(p.amount) as total_revenue
                        FROM payments p
                        WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                        AND p.payment_status = 'paid'
                        GROUP BY month
                        ORDER BY month ASC";

$monthly_revenue_result = $conn->query($monthly_revenue_sql);
$monthly_revenue_data = [];
$monthly_revenue_labels = [];

while ($row = $monthly_revenue_result->fetch_assoc()) {
    $monthly_revenue_labels[] = date('M Y', strtotime($row['month'] . '-01'));
    $monthly_revenue_data[] = $row['total_revenue'];
}

// Get daily revenue for the selected period
$daily_revenue_sql = "SELECT
                      DATE(p.payment_date) as day,
                      SUM(p.amount) as daily_revenue
                      FROM payments p
                      WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                      AND p.payment_status = 'paid'
                      GROUP BY day
                      ORDER BY day ASC";

$daily_revenue_stmt = $conn->prepare($daily_revenue_sql);
$daily_revenue_stmt->bind_param("ss", $date_from, $date_to);
$daily_revenue_stmt->execute();
$daily_revenue_result = $daily_revenue_stmt->get_result();

$daily_revenue_data = [];
$daily_revenue_labels = [];

while ($row = $daily_revenue_result->fetch_assoc()) {
    $daily_revenue_labels[] = date('M d', strtotime($row['day']));
    $daily_revenue_data[] = $row['daily_revenue'];
}

// Get most popular parking spots
$popular_spots_sql = "SELECT
                      ps.id, ps.spot_number, ps.floor_number, ps.type, ps.hourly_rate,
                      COUNT(b.id) as booking_count,
                      SUM(p.amount) as total_revenue
                      FROM parking_spots ps
                      LEFT JOIN bookings b ON ps.id = b.spot_id
                      LEFT JOIN payments p ON b.id = p.booking_id
                      WHERE (b.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) OR b.created_at IS NULL)
                      GROUP BY ps.id
                      ORDER BY booking_count DESC, total_revenue DESC
                      LIMIT 10";

$popular_spots_stmt = $conn->prepare($popular_spots_sql);
$popular_spots_stmt->bind_param("ss", $date_from, $date_to);
$popular_spots_stmt->execute();
$popular_spots = $popular_spots_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Online Parking Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Reports & Analytics</h1>
                <div class="d-flex gap-3">
                    <a href="profit-analysis.php" class="btn btn-primary">
                        <i class="fas fa-chart-line"></i> Detailed Profit Analysis
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="" method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="report_type" class="form-label">Report Type</label>
                            <select class="form-select" id="report_type" name="report_type">
                                <option value="revenue" <?php echo $report_type === 'revenue' ? 'selected' : ''; ?>>Revenue Analysis</option>
                                <option value="bookings" <?php echo $report_type === 'bookings' ? 'selected' : ''; ?>>Booking Analysis</option>
                                <option value="spots" <?php echo $report_type === 'spots' ? 'selected' : ''; ?>>Parking Spot Analysis</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-money-bill-wave fs-1 text-success mb-2"></i>
                            <h5 class="card-title">Total Revenue</h5>
                            <h3 class="mb-0">$<?php echo number_format($revenue_stats['total_revenue'] ?? 0, 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-receipt fs-1 text-primary mb-2"></i>
                            <h5 class="card-title">Transactions</h5>
                            <h3 class="mb-0"><?php echo number_format($revenue_stats['total_transactions'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-car fs-1 text-info mb-2"></i>
                            <h5 class="card-title">Total Bookings</h5>
                            <h3 class="mb-0"><?php echo number_format($booking_stats['total_bookings'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-clock fs-1 text-warning mb-2"></i>
                            <h5 class="card-title">Avg. Duration</h5>
                            <h3 class="mb-0"><?php echo round($booking_stats['avg_duration'] ?? 0, 1); ?> hrs</h3>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($report_type === 'revenue'): ?>
            <!-- Revenue Analysis -->
            <div class="row mb-4">
                <!-- Monthly Revenue Chart -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Monthly Revenue</h5>
                        </div>
                        <div class="card-body">
                            <div style="height: 300px;">
                                <canvas id="monthlyRevenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Revenue Chart -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Daily Revenue (Selected Period)</h5>
                        </div>
                        <div class="card-body">
                            <div style="height: 300px;">
                                <canvas id="dailyRevenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <!-- Payment Method Distribution -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Payment Method Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div style="height: 300px;">
                                <canvas id="paymentMethodChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Revenue by Spot Type -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Revenue by Spot Type</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Spot Type</th>
                                            <th>Bookings</th>
                                            <th>Total Revenue</th>
                                            <th>Avg. Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $spot_revenue->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo ucfirst($row['type']); ?></td>
                                            <td><?php echo number_format($row['booking_count']); ?></td>
                                            <td>$<?php echo number_format($row['total_revenue'], 2); ?></td>
                                            <td>$<?php echo number_format($row['avg_revenue'], 2); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
