<?php
/**
 * Dashboard page for Online Parking
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

    date_default_timezone_set('Asia/Dhaka'); // Set to match client timezone

    // Get spot IDs - handle both array and string formats
    $spot_ids = isset($_POST['spot_ids']) ? (is_array($_POST['spot_ids']) ? $_POST['spot_ids'] : explode(',', $_POST['spot_ids'])) : [];
    $vehicle_numbers = isset($_POST['vehicle_numbers']) ? $_POST['vehicle_numbers'] : [];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $total_amount = floatval($_POST['total_amount']);
    $hourly_rate = floatval($_POST['hourly_rate']);
    $user_id = $_SESSION['user_id'];

    // Validate input
    $errors = [];

    if (empty($spot_ids)) {
        $errors[] = "Please select at least one parking spot.";
    }

    if (empty($vehicle_numbers)) {
        $errors[] = "Vehicle number is required for each selected spot.";
    }

    if (empty($start_time) || empty($end_time)) {
        $errors[] = "Start time and end time are required.";
    }

    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();

        try {
            // Calculate expected amount for verification
            $start = new DateTime($start_time);
            $end = new DateTime($end_time);
            $duration_hours = ceil(($end->getTimestamp() - $start->getTimestamp()) / 3600); // Round up to nearest hour
            $expected_amount = count($spot_ids) * $hourly_rate * $duration_hours;

            // Allow for small floating-point differences
            if (abs($expected_amount - $total_amount) > 0.01) {
                throw new Exception("Amount calculation error. Expected: " . number_format($expected_amount, 2) . ", Received: " . number_format($total_amount, 2));
            }

            // Insert bookings for each spot
            $insert_stmt = $conn->prepare("INSERT INTO bookings (user_id, spot_id, vehicle_number, start_time, end_time, amount, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, 'active', 'pending')");
            $update_spot_stmt = $conn->prepare("UPDATE parking_spots SET is_occupied = 1, vehicle_number = ? WHERE id = ?");

            foreach ($spot_ids as $index => $spot_id) {
                $vehicle_number = $vehicle_numbers[$index];
                $amount_per_spot = $hourly_rate * $duration_hours;

                // Insert booking
                $insert_stmt->bind_param("iisssd", $user_id, $spot_id, $vehicle_number, $start_time, $end_time, $amount_per_spot);
                if (!$insert_stmt->execute()) {
                    throw new Exception("Failed to create booking: " . $conn->error);
                }

                // Update parking spot status to occupied
                $update_spot_stmt->bind_param("si", $vehicle_number, $spot_id);
                if (!$update_spot_stmt->execute()) {
                    throw new Exception("Failed to update parking spot status: " . $conn->error);
                }
            }

            $conn->commit();
            $_SESSION['message'] = "Booking successful! Amount: $" . number_format($expected_amount, 2);
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
    <title>Dashboard - Online Parking</title>
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
                <form method="POST" action="" class="booking-form" onsubmit="return validateBooking()">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="spot_ids" id="spot_ids" required>
                    <input type="hidden" name="total_amount" id="total_amount" required>
                    <input type="hidden" name="hourly_rate" id="hourly_rate" required>
                    <input type="hidden" name="book_spot" value="1">

                    <!-- Step 1: Time Range Selection -->
                    <div class="booking-step" id="step1">
                        <h3>Step 1: Select Time Range</h3>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Please select your desired parking time range. Spots will be shown as available if they're not booked during your selected time.
                        </div>
                        <div class="form-group">
                            <label for="start_time">Start Time:</label>
                            <input type="datetime-local" id="start_time" name="start_time" required onchange="checkTimeRange()">
                        </div>
                        <div class="form-group">
                            <label for="end_time">End Time:</label>
                            <input type="datetime-local" id="end_time" name="end_time" required onchange="checkTimeRange()">
                        </div>
                        <div id="time-range-error" class="error-message"></div>
                        <button type="button" id="check-availability-btn" class="btn btn-primary" disabled onclick="checkAvailability()">
                            <i class="fas fa-search"></i> Check Available Spots
                        </button>
                    </div>

                    <!-- Step 2: Vehicle Details -->
                    <div class="booking-step" id="step2" style="display: none;">
                        <h3>Step 2: Enter Vehicle Details</h3>
                        <div id="selected-spot-info">
                            <p>Please select a parking spot from the layout above</p>
                        </div>
                        <div id="vehicle-inputs-container">
                            <!-- Vehicle inputs will be dynamically added here -->
                        </div>
                        <div class="form-group">
                            <label>Total Amount:</label>
                            <div id="total_amount_display" class="amount-display">$0.00</div>
                        </div>
                        <button type="submit" id="book-button" class="btn btn-primary" disabled>Confirm Booking</button>
                    </div>
                </form>
            </div>

            <div class="parking-layout-container" id="parking-layout">
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
                                    <div class="spot time-not-selected"
                                         data-spot-id="<?php echo $spot['id']; ?>"
                                         data-spot-number="<?php echo htmlspecialchars($spot['spot_number']); ?>"
                                         data-floor-number="<?php echo $spot['floor_number']; ?>"
                                         data-spot-type="<?php echo htmlspecialchars($spot['type']); ?>"
                                         data-hourly-rate="<?php echo $spot['hourly_rate']; ?>"
                                         data-status="time-not-selected">
                                        <div class="spot-number"><?php echo htmlspecialchars($spot['spot_number']); ?></div>
                                        <div class="spot-type"><?php echo htmlspecialchars($spot['type']); ?></div>
                                        <div class="spot-rate">$<?php echo number_format($spot['hourly_rate'], 2); ?>/hr</div>
                                        <div class="status-badge time-not-selected">
                                            Select Time First
                                        </div>
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
        let selectedSpots = new Map(); // Store selected spots with their details
        const MAX_SELECTED_SPOTS = 4;

        document.addEventListener('DOMContentLoaded', function() {
            const layoutOptions = document.querySelectorAll('.layout-option');
            const floorTabs = document.querySelectorAll('.floor-tab');

            // Set minimum date time for booking
            const now = new Date();
            const minDateTime = now.toISOString().slice(0, 16);

            document.getElementById('start_time').min = minDateTime;
            document.getElementById('end_time').min = minDateTime;

            // Make parking spots clickable
            setupParkingSpotClickHandlers();

            // Form validation
            const bookingForm = document.querySelector('.booking-form');
            bookingForm.addEventListener('submit', function(e) {
                if (!selectedSpots.size) {
                    e.preventDefault();
                    alert('Please select at least one parking spot from the layout');
                    return false;
                }

                // Check if all vehicle numbers are filled
                let allVehicleNumbersFilled = true;
                const vehicleInputs = document.querySelectorAll('input[name^="vehicle_numbers"]');
                vehicleInputs.forEach(input => {
                    if (!input.value.trim()) {
                        allVehicleNumbersFilled = false;
                        input.setCustomValidity('Vehicle number is required');
                    } else {
                        input.setCustomValidity('');
                    }
                });

                if (!allVehicleNumbersFilled) {
                    e.preventDefault();
                    alert('Please enter vehicle numbers for all selected spots');
                    return false;
                }

                // Update spot statuses to booked
                selectedSpots.forEach((spot, spotId) => {
                    updateSpotStatus(spotId, 'booked');
                });
            });

            // Add event listeners for time inputs
            document.getElementById('start_time').addEventListener('change', calculateTotalAmount);
            document.getElementById('end_time').addEventListener('change', calculateTotalAmount);
        });

        // Function to check time range validity
        function checkTimeRange() {
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            const errorElement = document.getElementById('time-range-error');
            const checkBtn = document.getElementById('check-availability-btn');

            if (!startTime || !endTime) {
                errorElement.textContent = '';
                checkBtn.disabled = true;
                return false;
            }

            const start = new Date(startTime);
            const end = new Date(endTime);
            const now = new Date();

            // Check if start time is in the past
            if (start < now) {
                errorElement.textContent = 'Start time cannot be in the past';
                checkBtn.disabled = true;
                return false;
            }

            // Check if end time is after start time
            if (end <= start) {
                errorElement.textContent = 'End time must be after start time';
                checkBtn.disabled = true;
                return false;
            }

            // All checks passed
            errorElement.textContent = '';
            checkBtn.disabled = false;
            return true;
        }

        function checkAvailability() {
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;

            // Show loading state
            const checkBtn = document.getElementById('check-availability-btn');
            checkBtn.disabled = true;
            checkBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';

            // Make AJAX call to check availability
            fetch('check_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    start_time: startTime,
                    end_time: endTime
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update available spots
                    updateAvailableSpots(data.available_spots);

                    // Show step 2
                    document.getElementById('step2').style.display = 'block';

                    // Remove any existing time range info
                    const existingTimeInfo = document.getElementById('time-range-info');
                    if (existingTimeInfo) {
                        existingTimeInfo.remove();
                    }

                    // Add the time range info at the top of the parking layout
                    const timeRangeInfo = document.createElement('div');
                    timeRangeInfo.className = 'alert alert-success';
                    timeRangeInfo.id = 'time-range-info';
                    timeRangeInfo.innerHTML = `
                        <i class="fas fa-clock"></i>
                        Showing available spots for: <strong>${new Date(startTime).toLocaleString()}</strong> to <strong>${new Date(endTime).toLocaleString()}</strong>
                    `;

                    const parkingLayout = document.getElementById('parking-layout');
                    parkingLayout.insertBefore(timeRangeInfo, parkingLayout.firstChild);

                    // Reset selected spots when searching again
                    selectedSpots.clear();
                    updateSelectedSpotsDisplay();
                } else {
                    alert('Error checking availability: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error checking availability. Please try again.');
            })
            .finally(() => {
                // Reset button state
                checkBtn.disabled = false;
                checkBtn.innerHTML = 'Check Available Spots';
            });
        }

        function updateAvailableSpots(availableSpots) {
            // Get all spots
            const allSpots = document.querySelectorAll('.spot');

            // Reset all spots to unavailable
            allSpots.forEach(spot => {
                spot.classList.remove('available', 'occupied', 'selected', 'time-not-selected');
                spot.classList.add('occupied');
                spot.setAttribute('data-status', 'occupied');

                // Update status badge
                const statusBadge = spot.querySelector('.status-badge');
                if (statusBadge) {
                    statusBadge.textContent = 'Occupied';
                    statusBadge.className = 'status-badge occupied';
                }
            });

            // Mark available spots
            availableSpots.forEach(spotId => {
                const spot = document.querySelector(`.spot[data-spot-id="${spotId}"]`);
                if (spot) {
                    spot.classList.remove('occupied', 'time-not-selected');
                    spot.classList.add('available');
                    spot.setAttribute('data-status', 'available');

                    // Update status badge
                    const statusBadge = spot.querySelector('.status-badge');
                    if (statusBadge) {
                        statusBadge.textContent = 'Available';
                        statusBadge.className = 'status-badge available';
                    }
                }
            });

            // Setup click handlers for the updated spots
            setupParkingSpotClickHandlers();
        }

        // Setup click handlers for parking spots
        function setupParkingSpotClickHandlers() {
            // Get all spots from both grid and realistic layouts
            const allSpots = document.querySelectorAll('.spot');

            allSpots.forEach(spot => {
                // Only make available spots clickable
                if (spot.classList.contains('available')) {
                    spot.addEventListener('click', function(e) {
                        // Prevent click if the click was on the select button (to avoid double triggering)
                        if (e.target.classList.contains('select-spot-btn')) {
                            return;
                        }

                        const spotId = this.getAttribute('data-spot-id');
                        const spotNumber = this.getAttribute('data-spot-number');
                        const floorNumber = this.getAttribute('data-floor-number');
                        const spotType = this.getAttribute('data-spot-type');
                        const hourlyRate = this.getAttribute('data-hourly-rate');

                        // Call the selectSpot function
                        selectSpot(spotId, spotNumber, floorNumber, spotType, hourlyRate);
                    });
                }
            });
        }

        // Function to select a parking spot
        function selectSpot(spotId, spotNumber, floorNumber, spotType, hourlyRate) {
            // Only allow selection if spot is available
            const spot = document.querySelector(`[data-spot-id="${spotId}"]`);
            const spotStatus = spot ? spot.getAttribute('data-status') : null;

            // Prevent selection if spot is not available or time range is not selected
            if (!spot || spotStatus !== 'available' || spotStatus === 'time-not-selected') {
                if (spotStatus === 'time-not-selected') {
                    alert('Please select a time range and check availability first.');
                }
                return;
            }

            // Check if we've reached the maximum number of spots
            if (selectedSpots.size >= MAX_SELECTED_SPOTS && !selectedSpots.has(spotId)) {
                alert(`You can only select up to ${MAX_SELECTED_SPOTS} spots at a time.`);
                return;
            }

            // If spot is already selected, deselect it
            if (selectedSpots.has(spotId)) {
                selectedSpots.delete(spotId);
                updateSpotStatus(spotId, 'available');
                updateSelectedSpotsDisplay();
                return;
            }

            // Add spot to selected spots
            selectedSpots.set(spotId, {
                spotId,
                spotNumber,
                floorNumber,
                spotType,
                hourlyRate: parseFloat(hourlyRate)
            });

            // Update spot status to processing
            updateSpotStatus(spotId, 'selected');
            updateSelectedSpotsDisplay();

            // Recalculate total amount
            calculateTotalAmount();
        }

        // Function to update spot status
        function updateSpotStatus(spotId, status) {
            const spot = document.querySelector(`[data-spot-id="${spotId}"]`);
            if (!spot) return;

            // Remove all status classes
            spot.classList.remove('available', 'occupied', 'selected', 'time-not-selected');

            // Add the new status class
            spot.classList.add(status);
            spot.setAttribute('data-status', status);

            // Update status badge
            const statusBadge = spot.querySelector('.status-badge');
            if (statusBadge) {
                statusBadge.className = 'status-badge ' + status;

                switch (status) {
                    case 'available':
                        statusBadge.textContent = 'Available';
                        break;
                    case 'occupied':
                        statusBadge.textContent = 'Occupied';
                        break;
                    case 'selected':
                        statusBadge.textContent = 'Selected';
                        break;
                    case 'time-not-selected':
                        statusBadge.textContent = 'Select Time First';
                        break;
                    case 'booked':
                        statusBadge.textContent = 'Booked';
                        break;
                }
            }
        }

        // Function to update selected spots display
        function updateSelectedSpotsDisplay() {
            const selectedSpotInfo = document.getElementById('selected-spot-info');
            const vehicleInputsContainer = document.getElementById('vehicle-inputs-container');

            // Convert Map to Array for easier iteration
            const spotsList = Array.from(selectedSpots.values());

            // Update the hidden input with selected spot IDs
            document.getElementById('spot_ids').value = spotsList.map(spot => spot.spotId).join(',');

            if (spotsList.length === 0) {
                selectedSpotInfo.innerHTML = '<p>Please select parking spots from the layout above</p>';
                vehicleInputsContainer.innerHTML = '';
                document.getElementById('book-button').disabled = true;
                return;
            }

            let html = `
                <div class="selected-spots-header">
                    <h4>Selected Spots (${spotsList.length}/${MAX_SELECTED_SPOTS})</h4>
                </div>
                <div class="selected-spots-list">
            `;

            spotsList.forEach(spot => {
                html += `
                    <div class="selected-spot-item">
                        <div class="spot-details">
                            <div class="spot-number">Spot ${spot.spotNumber}</div>
                            <div class="spot-location">Floor ${spot.floorNumber}, ${spot.spotType}</div>
                            <div class="spot-rate">$${spot.hourlyRate.toFixed(2)}/hr</div>
                        </div>
                        <button type="button" class="remove-spot-btn" onclick="selectSpot('${spot.spotId}')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            });

            html += '</div>';
            selectedSpotInfo.innerHTML = html;

            // Create vehicle inputs for each selected spot
            let vehicleInputsHtml = '';
            spotsList.forEach((spot, index) => {
                vehicleInputsHtml += `
                    <div class="form-group vehicle-input">
                        <label for="vehicle_number_${index}">Vehicle Number for Spot ${spot.spotNumber}:</label>
                        <input type="text" id="vehicle_number_${index}" name="vehicle_numbers[${index}]" required
                               placeholder="Enter vehicle number" class="vehicle-number-input">
                    </div>
                `;
            });

            vehicleInputsContainer.innerHTML = vehicleInputsHtml;

            // Add event listeners to the new vehicle inputs
            const vehicleInputs = document.querySelectorAll('.vehicle-number-input');
            vehicleInputs.forEach(input => {
                input.addEventListener('input', function() {
                    validateVehicleNumbers();
                });
            });

            // Enable book button if all vehicle numbers are entered
            validateVehicleNumbers();
        }

        // Function to validate vehicle numbers
        function validateVehicleNumbers() {
            const vehicleInputs = document.querySelectorAll('.vehicle-number-input');
            let allValid = true;

            vehicleInputs.forEach(input => {
                const vehicleNumber = input.value.trim();
                const isValidFormat = /^[A-Z0-9-]+$/.test(vehicleNumber);

                if (!vehicleNumber) {
                    input.setCustomValidity('Vehicle number is required');
                    allValid = false;
                } else if (!isValidFormat) {
                    input.setCustomValidity('Please enter a valid vehicle number');
                    allValid = false;
                } else {
                    input.setCustomValidity('');
                }
            });

            document.getElementById('book-button').disabled = !allValid || vehicleInputs.length === 0;
        }

        // Function to validate booking before submission
        function validateBooking() {
            if (selectedSpots.size === 0) {
                alert('Please select at least one parking spot');
                return false;
            }

            const vehicleInputs = document.querySelectorAll('.vehicle-number-input');
            let allValid = true;

            vehicleInputs.forEach(input => {
                if (!input.value.trim()) {
                    allValid = false;
                }
            });

            if (!allValid) {
                alert('Please enter vehicle numbers for all selected spots');
                return false;
            }

            return true;
        }

        // Function to calculate total amount
        function calculateTotalAmount() {
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;

            if (!startTime || !endTime || selectedSpots.size === 0) {
                document.getElementById('total_amount_display').textContent = '$0.00';
                document.getElementById('total_amount').value = 0;
                return;
            }

            const start = new Date(startTime);
            const end = new Date(endTime);
            const durationHours = Math.ceil((end - start) / (1000 * 60 * 60)); // Round up to nearest hour

            let totalAmount = 0;
            selectedSpots.forEach(spot => {
                totalAmount += spot.hourlyRate * durationHours;
            });

            document.getElementById('total_amount_display').textContent = '$' + totalAmount.toFixed(2);
            document.getElementById('total_amount').value = totalAmount.toFixed(2);
            document.getElementById('hourly_rate').value = Array.from(selectedSpots.values())[0]?.hourlyRate || 0;

            // Highlight the amount to draw attention to the change
            const amountDisplay = document.getElementById('total_amount_display');
            amountDisplay.classList.add('amount-highlight');
            setTimeout(() => {
                amountDisplay.classList.remove('amount-highlight');
            }, 1000);
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
