<?php
/**
 * Bookings Management for Online Parking Admin Panel
 */
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Update expired bookings
updateExpiredBookings();

// Handle booking actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cancel booking
    if (isset($_POST['cancel_booking'])) {
        $booking_id = $_POST['booking_id'];

        // Get booking details
        $booking_sql = "SELECT b.*, p.id as spot_id FROM bookings b JOIN parking_spots p ON b.spot_id = p.id WHERE b.id = ?";
        $booking_stmt = $conn->prepare($booking_sql);
        $booking_stmt->bind_param("i", $booking_id);
        $booking_stmt->execute();
        $booking = $booking_stmt->get_result()->fetch_assoc();

        if ($booking) {
            // Start transaction
            $conn->begin_transaction();

            try {
                // Update booking status to cancelled
                $update_booking_sql = "UPDATE bookings SET status = 'cancelled' WHERE id = ?";
                $update_booking_stmt = $conn->prepare($update_booking_sql);
                $update_booking_stmt->bind_param("i", $booking_id);
                $update_booking_stmt->execute();

                // Free up the parking spot
                $update_spot_sql = "UPDATE parking_spots SET is_occupied = 0, vehicle_number = NULL WHERE id = ?";
                $update_spot_stmt = $conn->prepare($update_spot_sql);
                $update_spot_stmt->bind_param("i", $booking['spot_id']);
                $update_spot_stmt->execute();

                // Commit transaction
                $conn->commit();

                $_SESSION['message'] = "Booking #" . $booking_id . " has been cancelled.";
                $_SESSION['message_type'] = "success";
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();

                $_SESSION['message'] = "Error cancelling booking: " . $e->getMessage();
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Booking not found.";
            $_SESSION['message_type'] = "danger";
        }

        header("Location: bookings.php");
        exit();
    }

    // Complete booking
    if (isset($_POST['complete_booking'])) {
        $booking_id = $_POST['booking_id'];
        $end_time = $_POST['end_time'];
        $paid_amount = $_POST['paid_amount'];
        $payment_method = $_POST['payment_method'];
        $transaction_id = !empty($_POST['transaction_id']) ? $_POST['transaction_id'] : null;

        // Get booking details
        $booking_sql = "SELECT b.*, p.id as spot_id, p.hourly_rate FROM bookings b JOIN parking_spots p ON b.spot_id = p.id WHERE b.id = ?";
        $booking_stmt = $conn->prepare($booking_sql);
        $booking_stmt->bind_param("i", $booking_id);
        $booking_stmt->execute();
        $booking = $booking_stmt->get_result()->fetch_assoc();

        if ($booking) {
            // Start transaction
            $conn->begin_transaction();

            try {
                // Calculate final amount based on the end time
                $final_amount = calculate_booking_amount(
                    $booking['start_time'],
                    $end_time,
                    $booking['hourly_rate']
                );

                // Validate transaction ID for non-cash payments
                if ($payment_method !== 'cash' && empty($transaction_id)) {
                    throw new Exception("Transaction ID is required for " . ucfirst($payment_method) . " payments.");
                }

                // Generate transaction ID if not provided (for cash payments)
                if (empty($transaction_id)) {
                    $transaction_id = 'TXN' . time() . rand(1000, 9999);
                }

                // Use the same amount for both the payment record and booking update
                // This ensures consistency and prevents negative values

                // Insert payment record
                $payment_sql = "INSERT INTO payments (booking_id, amount, payment_date, payment_method, transaction_id) VALUES (?, ?, NOW(), ?, ?)";
                $payment_stmt = $conn->prepare($payment_sql);
                $payment_stmt->bind_param("idss", $booking_id, $paid_amount, $payment_method, $transaction_id);
                $payment_stmt->execute();

                // Update booking status and amount - use the same amount as the payment
                $update_booking_sql = "UPDATE bookings SET status = 'completed', end_time = ?, amount = ?, payment_status = 'paid' WHERE id = ?";
                $update_booking_stmt = $conn->prepare($update_booking_sql);
                $update_booking_stmt->bind_param("sdi", $end_time, $paid_amount, $booking_id);
                $update_booking_stmt->execute();

                // Free up the parking spot
                $update_spot_sql = "UPDATE parking_spots SET is_occupied = 0, vehicle_number = NULL WHERE id = ?";
                $update_spot_stmt = $conn->prepare($update_spot_sql);
                $update_spot_stmt->bind_param("i", $booking['spot_id']);
                $update_spot_stmt->execute();

                // Commit transaction
                $conn->commit();

                $_SESSION['message'] = "Booking #" . $booking_id . " has been completed. Payment of $" . number_format($paid_amount, 2) . " recorded.";
                $_SESSION['message_type'] = "success";
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();

                $_SESSION['message'] = "Error completing booking: " . $e->getMessage();
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Booking not found.";
            $_SESSION['message_type'] = "danger";
        }

        header("Location: bookings.php");
        exit();
    }
}

// Filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$id_filter = isset($_GET['id_filter']) ? $_GET['id_filter'] : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Build query based on filters
$where_clauses = [];
$params = [];
$types = "";

if ($id_filter) {
    // Exact match for ID
    $where_clauses[] = "b.id = ?";
    $params[] = $id_filter;
    $types .= "i";
}

if ($status_filter) {
    $where_clauses[] = "b.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($date_from) {
    $where_clauses[] = "b.start_time >= ?";
    $params[] = $date_from . " 00:00:00";
    $types .= "s";
}

if ($date_to) {
    $where_clauses[] = "b.end_time <= ?";
    $params[] = $date_to . " 23:59:59";
    $types .= "s";
}

if ($search) {
    $search_term = "%$search%";
    $where_clauses[] = "(u.name LIKE ? OR u.email LIKE ? OR p.spot_number LIKE ? OR b.vehicle_number LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Get total number of bookings
$total_sql = "SELECT COUNT(*) as total
              FROM bookings b
              JOIN users u ON b.user_id = u.id
              JOIN parking_spots p ON b.spot_id = p.id
              $where_sql";
$total_stmt = $conn->prepare($total_sql);

if (!empty($params)) {
    $total_stmt->bind_param($types, ...$params);
}

$total_stmt->execute();
$total_bookings = $total_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_bookings / $records_per_page);

// Get bookings with pagination and filters
$bookings_sql = "SELECT b.*, u.name as user_name, u.email as user_email, p.spot_number, p.floor_number, p.type, p.hourly_rate
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN parking_spots p ON b.spot_id = p.id
                $where_sql
                ORDER BY b.id DESC
                LIMIT ?, ?";

$bookings_stmt = $conn->prepare($bookings_sql);
$params[] = $offset;
$params[] = $records_per_page;
$types .= "ii";
$bookings_stmt->bind_param($types, ...$params);
$bookings_stmt->execute();
$bookings = $bookings_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings Management - Online Parking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="header">
                <h1>Bookings Management</h1>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Filter Bookings</h5>
                </div>
                <div class="card-body">
                    <form action="" method="GET" id="filterForm" class="row g-3">
                        <div class="col-md-2">
                            <label for="id_filter" class="form-label">Booking ID</label>
                            <input type="number" class="form-control" id="id_filter" name="id_filter" value="<?php echo htmlspecialchars($id_filter); ?>" placeholder="ID">
                        </div>
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="User, Email, Spot, Vehicle">
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>All</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Bookings List</h5>
                    <span class="badge bg-primary"><?php echo $total_bookings; ?> bookings found</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="bookingsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Spot</th>
                                    <th>Vehicle</th>
                                    <th>Duration</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="bookingsTableBody">
                                <?php if ($bookings->num_rows > 0): ?>
                                    <?php while ($booking = $bookings->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $booking['id']; ?></td>
                                            <td>
                                                <div><?php echo htmlspecialchars($booking['user_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($booking['user_email']); ?></small>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($booking['spot_number']); ?></div>
                                                <small class="text-muted">Floor <?php echo $booking['floor_number']; ?>, <?php echo ucfirst($booking['type']); ?></small>
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
                                                <div>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, H:i', strtotime($booking['start_time'])); ?> -
                                                        <?php echo date('M d, H:i', strtotime($booking['end_time'])); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>$<?php echo number_format($booking['amount'], 2); ?></td>
                                            <td><?php echo status_badge($booking['status']); ?></td>
                                            <td><?php echo status_badge($booking['payment_status']); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <?php if ($booking['status'] === 'active'): ?>
                                                        <button type="button" class="btn btn-sm btn-success"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#completeBookingModal"
                                                                data-id="<?php echo $booking['id']; ?>"
                                                                data-end-time="<?php echo $booking['end_time']; ?>"
                                                                data-amount="<?php echo $booking['amount']; ?>"
                                                                data-hourly-rate="<?php echo $booking['hourly_rate']; ?>">
                                                            <i class="fas fa-check-circle"></i>
                                                        </button>
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <button type="submit" name="cancel_booking" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this booking?');">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">No bookings found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center" id="paginationContainer">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($id_filter) ? '&id_filter=' . urlencode($id_filter) : ''; ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($id_filter) ? '&id_filter=' . urlencode($id_filter) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($id_filter) ? '&id_filter=' . urlencode($id_filter) : ''; ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Complete Booking Modal -->
    <div class="modal fade" id="completeBookingModal" tabindex="-1" aria-labelledby="completeBookingModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="completeBookingModalLabel">Complete Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="completeBookingForm">
                        <input type="hidden" id="booking_id" name="booking_id">
                        <input type="hidden" id="hourly_rate" name="hourly_rate">

                        <div class="mb-3">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="datetime-local" class="form-control" id="end_time" name="end_time" required>
                            <div class="form-text">Adjust the end time if needed</div>
                        </div>

                        <div class="mb-3">
                            <label for="calculated_amount" class="form-label">Calculated Amount ($)</label>
                            <input type="number" class="form-control" id="calculated_amount" readonly>
                            <div class="form-text">Amount based on the duration and hourly rate (for reference only)</div>
                        </div>

                        <div class="mb-3">
                            <label for="paid_amount" class="form-label">Paid Amount ($)</label>
                            <input type="number" class="form-control" id="paid_amount" name="paid_amount" step="0.01" required>
                            <div class="form-text">Enter the actual amount paid by the customer - this will be used as the final booking amount</div>
                        </div>

                        <div class="alert alert-info">
                            <small><i class="fas fa-info-circle"></i> Note: The paid amount will be used as the final booking amount to ensure consistency. If you need to adjust the amount, modify the "Paid Amount" field.</small>
                        </div>

                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Payment Method</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="paypal">PayPal</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="transaction_id" class="form-label">Transaction ID</label>
                            <input type="text" class="form-control" id="transaction_id" name="transaction_id" placeholder="Enter transaction ID (optional)">
                            <div class="form-text">Enter the transaction ID if available (required for card, PayPal, bank transfer)</div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="complete_booking" class="btn btn-success">Complete Booking</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Complete booking modal
        const completeBookingModal = document.getElementById('completeBookingModal');
        if (completeBookingModal) {
            completeBookingModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const bookingId = button.getAttribute('data-id');
                const endTime = button.getAttribute('data-end-time');
                const amount = button.getAttribute('data-amount');
                const hourlyRate = button.getAttribute('data-hourly-rate');

                document.getElementById('booking_id').value = bookingId;
                document.getElementById('hourly_rate').value = hourlyRate;

                // Format the end time for the datetime-local input
                const endTimeDate = new Date(endTime);
                const formattedEndTime = endTimeDate.toISOString().slice(0, 16);
                document.getElementById('end_time').value = formattedEndTime;

                // Set both calculated and paid amount to the same value initially
                const amountValue = parseFloat(amount).toFixed(2);
                document.getElementById('calculated_amount').value = amountValue;
                document.getElementById('paid_amount').value = amountValue;

                // Add event listener to recalculate amount when end time changes
                document.getElementById('end_time').addEventListener('change', recalculateAmount);

                // Add event listener to toggle transaction ID field based on payment method
                document.getElementById('payment_method').addEventListener('change', toggleTransactionIdField);
                toggleTransactionIdField(); // Call once to set initial state
            });
        }

        // Function to toggle transaction ID field based on payment method
        function toggleTransactionIdField() {
            const paymentMethod = document.getElementById('payment_method').value;
            const transactionIdField = document.getElementById('transaction_id');
            const transactionIdContainer = transactionIdField.closest('.mb-3');

            if (paymentMethod === 'cash') {
                transactionIdField.removeAttribute('required');
                transactionIdContainer.querySelector('.form-text').textContent = 'Enter the transaction ID if available (optional for cash payments)';
            } else {
                transactionIdField.setAttribute('required', 'required');
                transactionIdContainer.querySelector('.form-text').textContent = 'Transaction ID is required for ' + paymentMethod.charAt(0).toUpperCase() + paymentMethod.slice(1) + ' payments';
            }
        }

        // Function to recalculate amount based on end time
        function recalculateAmount() {
            const bookingId = document.getElementById('booking_id').value;
            const endTime = document.getElementById('end_time').value;
            const hourlyRate = document.getElementById('hourly_rate').value;

            // Make AJAX call to calculate the new amount
            fetch('calculate_booking_amount.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `booking_id=${bookingId}&end_time=${endTime}&hourly_rate=${hourlyRate}&action=calculate_amount`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the calculated amount
                    document.getElementById('calculated_amount').value = data.amount.toFixed(2);
                    // Also update the paid amount to match by default
                    document.getElementById('paid_amount').value = data.amount.toFixed(2);

                    // Add event listener to ensure calculated_amount is updated when paid_amount changes
                    document.getElementById('paid_amount').addEventListener('change', function() {
                        // This ensures the admin knows what they're changing from
                        document.getElementById('calculated_amount').value = data.amount.toFixed(2);
                    });
                } else {
                    console.error('Error calculating amount:', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>
