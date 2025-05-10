<?php
/**
 * Dashboard page for Smart Parking System
 */
require_once 'config.php';

// Check if user is logged in
require_login();

// Get user details
$user_id = $_SESSION['user_id'];
$user = get_user_details($user_id, $conn);

// Check if user exists
if (!$user) {
    // User not found in database, redirect to login
    session_destroy();
    header("Location: login.php?error=user_not_found");
    exit();
}

// Process booking form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_spot'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['message'] = "Invalid form submission. Please try again.";
        $_SESSION['message_type'] = "error";
        header("Location: dashboard.php");
        exit();
    }

    $spot_id = $_POST['spot_id'];
    $vehicle_number = sanitize_input($_POST['vehicle_number']);
    $start_time = $_POST['start_time'];
    $duration = (int)$_POST['duration'];

    // Validate input
    $errors = [];

    if (empty($spot_id)) {
        $errors[] = "Please select a parking spot.";
    }

    if (empty($vehicle_number)) {
        $errors[] = "Vehicle number is required.";
    }

    if (empty($start_time)) {
        $errors[] = "Start time is required.";
    }

    if ($duration <= 0) {
        $errors[] = "Duration must be greater than 0.";
    }

    if (empty($errors)) {
        // Calculate end time
        $end_time = date('Y-m-d H:i:s', strtotime($start_time . " + {$duration} hours"));

        // Get spot details
        $spot_sql = "SELECT * FROM parking_spots WHERE id = ?";
        $spot_stmt = $conn->prepare($spot_sql);
        $spot_stmt->bind_param("i", $spot_id);
        $spot_stmt->execute();
        $spot_result = $spot_stmt->get_result();

        if ($spot_result->num_rows === 0) {
            $_SESSION['message'] = "Invalid parking spot selected.";
            $_SESSION['message_type'] = "error";
            header("Location: dashboard.php");
            exit();
        }

        $spot = $spot_result->fetch_assoc();

        // Check if spot is available for the selected time
        if (!is_spot_available($spot_id, $start_time, $end_time, $conn)) {
            $_SESSION['message'] = "The selected parking spot is not available for the chosen time period.";
            $_SESSION['message_type'] = "error";
            header("Location: dashboard.php");
            exit();
        }

        // Calculate amount
        $amount = calculate_booking_amount($start_time, $end_time, $spot['hourly_rate']);

        // Begin transaction
        $conn->begin_transaction();

        try {
            // Insert booking
            $booking_sql = "INSERT INTO bookings (user_id, spot_id, vehicle_number, start_time, end_time, amount, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, 'active', 'pending')";
            $booking_stmt = $conn->prepare($booking_sql);
            $booking_stmt->bind_param("iisssd", $user_id, $spot_id, $vehicle_number, $start_time, $end_time, $amount);

            if (!$booking_stmt->execute()) {
                throw new Exception("Failed to create booking: " . $conn->error);
            }

            $booking_id = $conn->insert_id;

            // Update parking spot status
            $update_spot_sql = "UPDATE parking_spots SET is_occupied = 1, vehicle_number = ? WHERE id = ?";
            $update_spot_stmt = $conn->prepare($update_spot_sql);
            $update_spot_stmt->bind_param("si", $vehicle_number, $spot_id);

            if (!$update_spot_stmt->execute()) {
                throw new Exception("Failed to update parking spot status: " . $conn->error);
            }

            // Commit transaction
            $conn->commit();

            $_SESSION['message'] = "Booking successful! Amount: $" . number_format($amount, 2);
            $_SESSION['message_type'] = "success";
            header("Location: my-bookings.php");
            exit();

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();

            $_SESSION['message'] = "Error: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
            header("Location: dashboard.php");
            exit();
        }
    } else {
        $_SESSION['message'] = implode("<br>", $errors);
        $_SESSION['message_type'] = "error";
    }
}

// Get available parking spots
$spots_sql = "SELECT * FROM parking_spots ORDER BY floor_number, spot_number";
$spots_result = $conn->query($spots_sql);
$parking_spots = [];

while ($spot = $spots_result->fetch_assoc()) {
    $parking_spots[] = $spot;
}

// Group spots by floor
$floors = [];
foreach ($parking_spots as $spot) {
    $floor = $spot['floor_number'];
    if (!isset($floors[$floor])) {
        $floors[$floor] = [];
    }
    $floors[$floor][] = $spot;
}

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
    <title>Dashboard - Smart Parking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Dashboard</h1>
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

            <div class="booking-section">
                <h2>Book a Parking Spot</h2>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="book_spot" value="1">
                    <input type="hidden" name="spot_id" id="selected_spot_id">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="vehicle_number">Vehicle Number</label>
                            <input type="text" class="form-control" id="vehicle_number" name="vehicle_number" placeholder="Enter vehicle number" required>
                        </div>

                        <div class="form-group">
                            <label for="start_time">Start Time</label>
                            <input type="datetime-local" class="form-control" id="start_time" name="start_time" min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="duration">Duration (hours)</label>
                            <input type="number" class="form-control" id="duration" name="duration" min="1" max="24" value="1" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Select a parking spot from the layout below</label>
                    </div>

                    <button type="submit" class="btn btn-primary" id="book_button" disabled>
                        <i class="fas fa-check-circle"></i> Book Now
                    </button>
                </form>
            </div>

            <div class="parking-layout-container">
                <div class="floor-tabs">
                    <?php foreach (array_keys($floors) as $floor_number): ?>
                        <button class="floor-tab <?php echo $floor_number === 0 ? 'active' : ''; ?>" data-floor="<?php echo $floor_number; ?>">
                            Floor <?php echo $floor_number; ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="parking-layout">
                    <?php foreach ($floors as $floor_number => $floor_spots): ?>
                        <div class="floor-layout" id="floor-<?php echo $floor_number; ?>" style="display: <?php echo $floor_number === 0 ? 'block' : 'none'; ?>">
                            <h3>Floor <?php echo $floor_number; ?> Parking Spots</h3>

                            <div class="spots-grid">
                                <?php foreach ($floor_spots as $spot): ?>
                                    <div class="spot <?php echo $spot['is_occupied'] ? 'occupied' : 'available'; ?>" data-spot-id="<?php echo $spot['id']; ?>">
                                        <div class="spot-number"><?php echo htmlspecialchars($spot['spot_number']); ?></div>
                                        <div class="spot-type"><?php echo htmlspecialchars($spot['type']); ?></div>
                                        <div class="spot-rate">$<?php echo number_format($spot['hourly_rate'], 2); ?>/hr</div>
                                        <div class="status-badge <?php echo $spot['is_occupied'] ? 'occupied' : 'available'; ?>">
                                            <?php echo $spot['is_occupied'] ? 'Occupied' : 'Available'; ?>
                                        </div>
                                        <?php if (!$spot['is_occupied']): ?>
                                            <button class="select-spot-btn" onclick="selectSpot(<?php echo $spot['id']; ?>, '<?php echo htmlspecialchars($spot['spot_number']); ?>')">
                                                Select
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to select a parking spot
        function selectSpot(spotId, spotNumber) {
            // Set the selected spot ID in the hidden input
            document.getElementById('selected_spot_id').value = spotId;

            // Enable the book button
            document.getElementById('book_button').disabled = false;

            // Highlight the selected spot
            const spots = document.querySelectorAll('.spot');
            spots.forEach(spot => {
                spot.classList.remove('selected');
            });

            const selectedSpot = document.querySelector(`.spot[data-spot-id="${spotId}"]`);
            if (selectedSpot) {
                selectedSpot.classList.add('selected');
            }

            // Show a message
            alert(`Spot ${spotNumber} selected. Please fill in the booking details and click "Book Now".`);
        }

        // Function to switch between floor tabs
        document.querySelectorAll('.floor-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                document.querySelectorAll('.floor-tab').forEach(t => {
                    t.classList.remove('active');
                });

                // Add active class to clicked tab
                this.classList.add('active');

                // Hide all floor layouts
                document.querySelectorAll('.floor-layout').forEach(layout => {
                    layout.style.display = 'none';
                });

                // Show the selected floor layout
                const floorNumber = this.getAttribute('data-floor');
                document.getElementById(`floor-${floorNumber}`).style.display = 'block';
            });
        });
    </script>
</body>
</html>
