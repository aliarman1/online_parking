<?php
/**
 * Bookings AJAX Handler for Online Parking Admin Panel
 *
 * This file handles AJAX requests for filtering and pagination of bookings.
 */
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Update expired bookings
updateExpiredBookings();

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

// Start output buffering to capture HTML
ob_start();

// Generate table HTML
if ($bookings->num_rows > 0) {
    while($booking = $bookings->fetch_assoc()) {
        ?>
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
        <?php
    }
} else {
    ?>
    <tr>
        <td colspan="9" class="text-center py-4">No bookings found</td>
    </tr>
    <?php
}

$tableHtml = ob_get_clean();

// Generate pagination HTML
ob_start();

if ($total_pages > 1) {
    ?>
    <ul class="pagination justify-content-center">
        <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="javascript:void(0)" onclick="changePage(<?php echo $page - 1; ?>)">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                <a class="page-link" href="javascript:void(0)" onclick="changePage(<?php echo $i; ?>)">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="javascript:void(0)" onclick="changePage(<?php echo $page + 1; ?>)">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        <?php endif; ?>
    </ul>
    <?php
}

$paginationHtml = ob_get_clean();

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'tableHtml' => $tableHtml,
    'paginationHtml' => $paginationHtml,
    'totalRecords' => $total_bookings,
    'totalPages' => $total_pages,
    'currentPage' => $page
]);
?>
