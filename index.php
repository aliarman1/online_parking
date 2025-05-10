<?php
// include the database connection file (which already starts the session)
require 'database/db.php';

// Debug session information
error_log("Session data in index.php: " . print_r($_SESSION, true));

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in, redirecting to login.php");
    header("Location: login.php");
    exit();
}

// Debug successful login
error_log("User logged in successfully with ID: " . $_SESSION['user_id'] . " and role: " . $_SESSION['role']);


// Fetch available parking slots for the selected area, date, time, duration, and vehicle type
$selected_area = isset($_GET['area']) ? $_GET['area'] : 'Bashundhara Shopping Mall';
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_time = isset($_GET['time']) ? $_GET['time'] : date('H:i');
$selected_duration = isset($_GET['duration']) ? $_GET['duration'] : 1;
$selected_vehicle_type = isset($_GET['vehicle_type']) ? $_GET['vehicle_type'] : 'car';

// Calculate end time
$end_time = date('H:i', strtotime("+$selected_duration hours", strtotime($selected_time)));

// Fetch available slots
$slots = [];
$query = "SELECT * FROM parking_slots
          WHERE location = '$selected_area'
          AND vehicle_type = '$selected_vehicle_type'
          AND id NOT IN (
              SELECT slot_id FROM bookings
              WHERE booking_date = '$selected_date'
              AND (
                  ('$selected_time' < end_time AND '$end_time' > booking_time)
              )
          )";
$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . $conn->error); // Debug query error
}

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $slots[] = $row;
    }
} else {
    echo "No available slots found in $selected_area for the selected date, time, and vehicle type."; // Debug no slots found
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- daisy UI -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">


</head>

<body class="bg-gray-100 relative">
    <!-- nav section -->
    <div class="navbar bg-base-100 shadow-sm sticky top-0 z-50" >
        <div class="navbar-start">
            <div class="dropdown">
                <div tabindex="0" role="button" class="btn btn-ghost lg:hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h8m-8 6h16" />
                    </svg>
                </div>
                <ul tabindex="0" class="menu menu-sm dropdown-content bg-base-100 rounded-box z-1 mt-3 w-52 p-2 shadow">
                    <li><a href="index.php"
                            class="bg-blue-600 text-white px-4 py-2 mb-2 rounded-lg hover:bg-blue-700">Home</a></li>
                    <li><a href="booking_slot.php"
                            class="bg-green-600 text-white px-4 py-2 mb-2 rounded-lg hover:bg-green-700">Booking History</a>
                    </li>
                    <li><a href="profile.php"
                            class="bg-purple-600 text-white px-4 py-2 mb-2 rounded-lg hover:bg-purple-700">Profile</a>
                    </li>
                </ul>
            </div>
            <a class="btn btn-ghost text-xl">ParkEase</a>
            <div class="dropdown dropdown-end">
                <div tabindex="0" class="btn btn-ghost btn-circle avatar">
                    <div class="w-10 rounded-full relative group">
                        <!-- Profile Image with Tooltip -->
                        <img alt="Tailwind CSS Navbar component"
                            src="https://img.daisyui.com/images/stock/photo-1534528741775-53994a69daeb.webp"
                            title="<?php echo $_SESSION['email']; ?>" class="w-full h-full rounded-full" />
                        <!-- Custom Tooltip -->
                        <div
                            class="absolute bottom-full mb-2 hidden group-hover:block bg-black text-white text-sm px-2 py-1 rounded">
                            <?php echo $_SESSION['email']; ?>
                        </div>
                    </div>
                </div>
                <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-52">
                    <li>
                        <a class="justify-between">
                            Profile
                            <span class="badge">1</span>
                        </a>
                    </li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>

        <div class="navbar-end">
            <div class="navbar-center hidden lg:flex">
                <ul class="menu menu-horizontal">
                    <li><a href="index.php"
                            class="bg-blue-600 text-white px-4 py-2 mr-2 rounded-lg hover:bg-blue-700">Home</a></li>
                    <li><a href="booking_slot.php"
                            class="bg-green-600 text-white px-4 py-2 mr-2 rounded-lg hover:bg-green-700">Booking History</a>
                    </li>
                    <li><a href="profile.php"
                            class="bg-purple-600 text-white px-4 py-2 mr-2 rounded-lg hover:bg-purple-700">Profile</a>
                    </li>
                </ul>
            </div>
            <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Logout</a>
        </div>



    </div>


    <!-- Dashboard -->
    <div class="max-w-6xl mx-auto px-4 py-8">
        <h2 class="text-3xl font-bold mb-6">Available Parking Slots</h2>

        <!-- Area Selection Dropdown -->
        <div class="mb-6 backdrop-blur-sm bg-gray-200/50 p-8 rounded-lg shadow-lg shadow-gray-300 ">
            <label class="block text-gray-700">Select Area</label>
            <select id="area-select"
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 border-black focus:border-none">
                <option value="Bashundhara Shopping Mall">Bashundhara Shopping Mall</option>
                <option value="Jamuna Future Park">Jamuna Future Park</option>
            </select>
        </div>

        <!-- Vehicle Type Dropdown -->
        <div class="mb-6 backdrop-blur-sm bg-gray-200/50 p-8 rounded-lg shadow-lg shadow-gray-300 ">
            <label class="block text-gray-700">Select Vehicle Type</label>
            <select id="vehicle-type-select"
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 border-black focus:border-none">
                <option value="car">Car</option>
                <option value="bike">Bike</option>
            </select>
        </div>

        <!-- Date and Time Selection -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 backdrop-blur-sm bg-gray-200/50 p-8 rounded-lg shadow-lg shadow-gray-300 ">
            <div>
                <label class="block text-gray-700">Booking Date</label>
                <input type="date" id="date-select" value="<?php echo $selected_date; ?>"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 border-black focus:border-none">
            </div>
            <div>
                <label class="block text-gray-700">Booking Time</label>
                <input type="time" id="time-select" value="<?php echo $selected_time; ?>"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 border-black focus:border-none">
            </div>
            <div>
                <label class="block text-gray-700">Duration (hours)</label>
                <input type="number" id="duration-select" value="<?php echo $selected_duration; ?>"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 border-black focus:border-none">
            </div>
        </div>

        <!-- Slot List -->
        <div id="slot-list" class="grid grid-cols-1 md:grid-cols-3 gap-6 backdrop-blur-sm bg-white p-8 rounded-lg">
            <?php if (empty($slots)): ?>
                <p class="text-gray-600">No parking slots available at the moment.</p>
            <?php else: ?>
                <?php foreach ($slots as $slot): ?>
                    <div class=" p-6 rounded-lg text-center shadow-2xl backdrop-blur-md bg-white hover:scale-105 transition-all ease-in-out duration-1000 ">
                        <h3 class="text-xl font-bold mb-2">Slot <?php echo $slot['slot_number']; ?></h3>
                        <p class="text-gray-600 mb-4">Location: <?php echo $slot['location']; ?></p>
                        <p class="text-gray-600 mb-4">Type: <?php echo ucfirst($slot['vehicle_type']); ?></p>
                        <p class="text-gray-600 mb-4">Cost: $<?php echo $slot['cost_per_hour']; ?> per hour</p>
                        <button onclick="bookSlot(<?php echo $slot['id']; ?>, <?php echo $slot['cost_per_hour']; ?>)"
                            class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                            Book Now
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="booking-modal"
        class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center overflow-y-scroll">
        <div class="bg-white p-10 rounded-lg w-96 ">
            <h2 class="text-2xl font-bold mb-6">Book Parking Slot</h2>
            <form id="booking-form">
                <input type="hidden" id="slot-id" name="slot_id">
                <input type="hidden" id="cost-per-hour" name="cost_per_hour">
                <div class="mb-4">
                    <label class="block text-gray-700">Vehicle Number</label>
                    <input type="text" name="vehicle_number" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Booking Date</label>
                    <input type="date" id="popup-date" name="booking_date" readonly
                        class="w-full px-4 py-2 border rounded-lg bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Booking Time</label>
                    <input type="time" id="popup-time" name="booking_time" readonly
                        class="w-full px-4 py-2 border rounded-lg bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Duration (hours)</label>
                    <input type="number" id="popup-duration" name="duration" readonly
                        class="w-full px-4 py-2 border rounded-lg bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Total Cost</label>
                    <input type="text" id="total-cost" readonly
                        class="w-full px-4 py-2 border rounded-lg bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <!-- bKash Payment Section -->
                <div class="mb-4">
                    <label class="block text-gray-700" for="bkash-number">bKash Number</label>
                    <input type="text" id="bkash-number" name="bkash_number" placeholder="Enter bKash number"
                        autocomplete='off' required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4 relative">
                    <label class="block text-gray-700" for="bkash-pin">bKash PIN</label>
                    <input type="password" id="bkash-pin" name="bkash_pin" placeholder="Enter bKash PIN"
                        autocomplete="new-password" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <!-- Eye icon to toggle password visibility -->
                    <i class="fas fa-eye absolute right-3 top-10 cursor-pointer"
                        onclick="togglePassword('bkash-pin')"></i>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                    Confirm Booking
                </button>
            </form>
            <button onclick="closeModal()"
                class="w-full bg-gray-600 text-white py-2 px-4 rounded-lg mt-4 hover:bg-gray-700">
                Cancel
            </button>
        </div>
    </div>

    <script>
        // Open booking modal and populate fields
        function bookSlot(slotId, costPerHour) {
            const selectedDate = document.getElementById('date-select').value;
            const selectedTime = document.getElementById('time-select').value;
            const selectedDuration = document.getElementById('duration-select').value;

            document.getElementById('slot-id').value = slotId;
            document.getElementById('cost-per-hour').value = costPerHour;
            document.getElementById('popup-date').value = selectedDate;
            document.getElementById('popup-time').value = selectedTime;
            document.getElementById('popup-duration').value = selectedDuration;

            // Calculate total cost
            const totalCost = costPerHour * selectedDuration;
            document.getElementById('total-cost').value = `$${totalCost.toFixed(2)}`;

            document.getElementById('booking-modal').classList.remove('hidden');
        }

        // Close booking modal
        function closeModal() {
            document.getElementById('booking-modal').classList.add('hidden');
        }

        // Handle booking form submission
        // Handle booking form submission
        $('#booking-form').submit(function (e) {
            e.preventDefault();

            // Get bKash number and PIN values
            const bkashNumber = $('#bkash-number').val().trim();
            const bkashPin = $('#bkash-pin').val().trim();

            // Validate bKash number and PIN
            if (!bkashNumber || !bkashPin) {
                alert('Please enter your bKash number and PIN.');
                return; // Stop the form submission if fields are empty
            }

            // Simulate bKash payment
            alert('Payment successful! Confirming your booking...');

            // Serialize form data
            const formData = $(this).serialize();

            // Send AJAX request to book the slot
            $.ajax({
                url: 'book_slot.php',
                type: 'POST',
                data: formData,
                success: function (response) {
                    alert(response); // Show success message
                    closeModal(); // Close the modal
                    location.reload(); // Refresh the page to update slot availability
                },
                error: function () {
                    alert('An error occurred. Please try again.'); // Show error message
                }
            });
        });

        // Function to toggle password visibility
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const eyeIcon = passwordField.nextElementSibling; // The eye icon

            if (passwordField.type === "password") {
                passwordField.type = "text";
                eyeIcon.classList.remove("fa-eye");
                eyeIcon.classList.add("fa-eye-slash");
            } else {
                passwordField.type = "password";
                eyeIcon.classList.remove("fa-eye-slash");
                eyeIcon.classList.add("fa-eye");
            }
        }


        // Reload slots when area, vehicle type, date, time, or duration is changed
        $('#area-select, #vehicle-type-select, #date-select, #time-select, #duration-select').change(function () {
            const selectedArea = $('#area-select').val();
            const selectedVehicleType = $('#vehicle-type-select').val();
            const selectedDate = $('#date-select').val();
            const selectedTime = $('#time-select').val();
            const selectedDuration = $('#duration-select').val();
            window.location.href = `index.php?area=${selectedArea}&vehicle_type=${selectedVehicleType}&date=${selectedDate}&time=${selectedTime}&duration=${selectedDuration}`;
        });

        // Set the selected area, vehicle type, date, time, and duration in the inputs
        const urlParams = new URLSearchParams(window.location.search);
        const selectedArea = urlParams.get('area') || 'Bashundhara Shopping Mall';
        const selectedVehicleType = urlParams.get('vehicle_type') || 'car';
        const selectedDate = urlParams.get('date') || '<?php echo date('Y-m-d'); ?>';
        const selectedTime = urlParams.get('time') || '<?php echo date('H:i'); ?>';
        const selectedDuration = urlParams.get('duration') || 1;

        $('#area-select').val(selectedArea);
        $('#vehicle-type-select').val(selectedVehicleType);
        $('#date-select').val(selectedDate);
        $('#time-select').val(selectedTime);
        $('#duration-select').val(selectedDuration);
    </script>


</body>

</html>