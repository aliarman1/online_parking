<?php
/**
 * Profit Analysis for Online Parking Admin Panel
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
$group_by = isset($_GET['group_by']) ? $_GET['group_by'] : 'daily';

// Get total profit
$profit_sql = "SELECT
                SUM(p.amount) as total_revenue,
                COUNT(DISTINCT b.id) as total_bookings,
                COUNT(DISTINCT p.id) as total_transactions,
                AVG(p.amount) as avg_transaction_value,
                SUM(p.amount) / COUNT(DISTINCT b.id) as revenue_per_booking
              FROM payments p
              JOIN bookings b ON p.booking_id = b.id
              WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
              AND p.payment_status = 'paid'";

$profit_stmt = $conn->prepare($profit_sql);
$profit_stmt->bind_param("ss", $date_from, $date_to);
$profit_stmt->execute();
$profit_stats = $profit_stmt->get_result()->fetch_assoc();

// Get profit by spot type
$spot_profit_sql = "SELECT
                    ps.type,
                    COUNT(DISTINCT b.id) as booking_count,
                    SUM(p.amount) as total_revenue,
                    AVG(p.amount) as avg_revenue,
                    SUM(p.amount) / COUNT(DISTINCT b.id) as revenue_per_booking
                  FROM payments p
                  JOIN bookings b ON p.booking_id = b.id
                  JOIN parking_spots ps ON b.spot_id = ps.id
                  WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                  AND p.payment_status = 'paid'
                  GROUP BY ps.type
                  ORDER BY total_revenue DESC";

$spot_profit_stmt = $conn->prepare($spot_profit_sql);
$spot_profit_stmt->bind_param("ss", $date_from, $date_to);
$spot_profit_stmt->execute();
$spot_profit = $spot_profit_stmt->get_result();

// Get profit by floor
$floor_profit_sql = "SELECT
                     ps.floor_number,
                     COUNT(DISTINCT b.id) as booking_count,
                     SUM(p.amount) as total_revenue,
                     AVG(p.amount) as avg_revenue,
                     SUM(p.amount) / COUNT(DISTINCT b.id) as revenue_per_booking
                   FROM payments p
                   JOIN bookings b ON p.booking_id = b.id
                   JOIN parking_spots ps ON b.spot_id = ps.id
                   WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                   AND p.payment_status = 'paid'
                   GROUP BY ps.floor_number
                   ORDER BY ps.floor_number ASC";

$floor_profit_stmt = $conn->prepare($floor_profit_sql);
$floor_profit_stmt->bind_param("ss", $date_from, $date_to);
$floor_profit_stmt->execute();
$floor_profit = $floor_profit_stmt->get_result();

// Get time-based profit data
$time_sql = "";
$time_labels = [];
$time_data = [];

if ($group_by === 'hourly') {
    $time_sql = "SELECT
                 HOUR(p.payment_date) as hour,
                 SUM(p.amount) as revenue
                 FROM payments p
                 WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                 AND p.payment_status = 'paid'
                 GROUP BY hour
                 ORDER BY hour ASC";

    $time_stmt = $conn->prepare($time_sql);
    $time_stmt->bind_param("ss", $date_from, $date_to);
    $time_stmt->execute();
    $time_result = $time_stmt->get_result();

    // Initialize all hours with zero
    for ($i = 0; $i < 24; $i++) {
        $time_labels[] = sprintf("%02d:00", $i);
        $time_data[$i] = 0;
    }

    // Fill in actual data
    while ($row = $time_result->fetch_assoc()) {
        $hour = (int)$row['hour'];
        $time_data[$hour] = (float)$row['revenue'];
    }
} elseif ($group_by === 'daily') {
    $time_sql = "SELECT
                 DATE(p.payment_date) as day,
                 SUM(p.amount) as revenue
                 FROM payments p
                 WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                 AND p.payment_status = 'paid'
                 GROUP BY day
                 ORDER BY day ASC";

    $time_stmt = $conn->prepare($time_sql);
    $time_stmt->bind_param("ss", $date_from, $date_to);
    $time_stmt->execute();
    $time_result = $time_stmt->get_result();

    while ($row = $time_result->fetch_assoc()) {
        $time_labels[] = date('M d', strtotime($row['day']));
        $time_data[] = (float)$row['revenue'];
    }
} elseif ($group_by === 'weekly') {
    $time_sql = "SELECT
                 YEARWEEK(p.payment_date, 1) as yearweek,
                 MIN(DATE(p.payment_date)) as week_start,
                 SUM(p.amount) as revenue
                 FROM payments p
                 WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
                 AND p.payment_status = 'paid'
                 GROUP BY yearweek
                 ORDER BY yearweek ASC";

    $time_result = $conn->query($time_sql);

    while ($row = $time_result->fetch_assoc()) {
        $time_labels[] = 'Week of ' . date('M d', strtotime($row['week_start']));
        $time_data[] = (float)$row['revenue'];
    }
} else { // monthly
    $time_sql = "SELECT
                 DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                 SUM(p.amount) as revenue
                 FROM payments p
                 WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                 AND p.payment_status = 'paid'
                 GROUP BY month
                 ORDER BY month ASC";

    $time_result = $conn->query($time_sql);

    while ($row = $time_result->fetch_assoc()) {
        $time_labels[] = date('M Y', strtotime($row['month'] . '-01'));
        $time_data[] = (float)$row['revenue'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit Analysis - Online Parking Admin</title>
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
                <h1 class="page-title">Profit Analysis</h1>
                <a href="reports.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Reports
                </a>
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
                            <label for="group_by" class="form-label">Group By</label>
                            <select class="form-select" id="group_by" name="group_by">
                                <option value="hourly" <?php echo $group_by === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                <option value="daily" <?php echo $group_by === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo $group_by === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo $group_by === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Profit Overview -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-money-bill-wave fs-1 text-success mb-2"></i>
                            <h5 class="card-title">Total Revenue</h5>
                            <h3 class="mb-0">$<?php echo number_format($profit_stats['total_revenue'] ?? 0, 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-receipt fs-1 text-primary mb-2"></i>
                            <h5 class="card-title">Transactions</h5>
                            <h3 class="mb-0"><?php echo number_format($profit_stats['total_transactions'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-car fs-1 text-info mb-2"></i>
                            <h5 class="card-title">Total Bookings</h5>
                            <h3 class="mb-0"><?php echo number_format($profit_stats['total_bookings'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-line fs-1 text-warning mb-2"></i>
                            <h5 class="card-title">Avg. Transaction</h5>
                            <h3 class="mb-0">$<?php echo number_format($profit_stats['avg_transaction_value'] ?? 0, 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Time-based Profit Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Revenue Over Time (<?php echo ucfirst($group_by); ?>)</h5>
                </div>
                <div class="card-body">
                    <div style="height: 400px;">
                        <canvas id="timeBasedProfitChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Profit by Category -->
            <div class="row mb-4">
                <!-- Profit by Spot Type -->
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
                                        <?php while ($row = $spot_profit->fetch_assoc()): ?>
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

                <!-- Profit by Floor -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Revenue by Floor</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Floor</th>
                                            <th>Bookings</th>
                                            <th>Total Revenue</th>
                                            <th>Avg. Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $floor_profit->fetch_assoc()): ?>
                                        <tr>
                                            <td>Floor <?php echo $row['floor_number']; ?></td>
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Time-based Profit Chart
        const timeBasedProfitCtx = document.getElementById('timeBasedProfitChart');
        if (timeBasedProfitCtx) {
            new Chart(timeBasedProfitCtx, {
                type: '<?php echo $group_by === 'hourly' ? 'bar' : 'line'; ?>',
                data: {
                    labels: <?php echo json_encode($time_labels); ?>,
                    datasets: [{
                        label: 'Revenue',
                        data: <?php echo json_encode($time_data); ?>,
                        backgroundColor: 'rgba(13, 110, 253, 0.7)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: <?php echo $group_by === 'hourly' ? 'false' : 'true'; ?>
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value;
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>