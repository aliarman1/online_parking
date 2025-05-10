<?php
//access all the bookings history from the database table name bookings
include 'database/db.php';

// Initialize variables for search
$search_date = isset($_GET['search_date']) ? $_GET['search_date'] : '';
$search_start_time = isset($_GET['search_start_time']) ? $_GET['search_start_time'] : '';
$search_end_time = isset($_GET['search_end_time']) ? $_GET['search_end_time'] : '';

// Build the base query
$query = "SELECT a_bookings.*, users.username, parking_slots.slot_number FROM a_bookings
          JOIN users ON a_bookings.user_id = users.id
          JOIN parking_slots ON a_bookings.slot_id = parking_slots.id";

// Add search conditions if provided
$conditions = [];
if (!empty($search_date)) {
    $conditions[] = "a_bookings.booking_date = '$search_date'";
}
if (!empty($search_start_time)) {
    $conditions[] = "a_bookings.booking_time >= '$search_start_time'";
}
if (!empty($search_end_time)) {
    $conditions[] = "a_bookings.end_time <= '$search_end_time'";
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

// Process multiple delete if form submitted
if (isset($_POST['delete_selected'])) {
    if (!empty($_POST['selected_bookings'])) {
        $selected_ids = implode(",", $_POST['selected_bookings']);
        $delete_query = "DELETE FROM a_bookings WHERE id IN ($selected_ids)";
        if ($conn->query($delete_query) === TRUE) {
            echo "<script>alert('Selected bookings deleted successfully');</script>";
            // Refresh the page to show updated list
            echo "<script>window.location.href = window.location.href;</script>";
        } else {
            echo "<script>alert('Error deleting bookings: " . $conn->error . "');</script>";
        }
    } else {
        echo "<script>alert('No bookings selected for deletion');</script>";
    }
}

// Fetch bookings
$bookings = [];
$result = $conn->query($query);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>

    <!-- tailwind css -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- font awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- daisy UI -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body class="bg-gray-100">


    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-6">Bookings Management</h1>

        <!-- Search Form -->
        <div class="bg-white p-4 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold mb-4">Search Bookings</h2>
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Booking Date</label>
                    <input type="date" name="search_date" value="<?php echo $search_date; ?>"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Start Time</label>
                    <input type="time" name="search_start_time" value="<?php echo $search_start_time; ?>"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">End Time</label>
                    <input type="time" name="search_end_time" value="<?php echo $search_end_time; ?>"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Search
                    </button>
                    <a href="?" class="ml-2 bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Bookings Table -->
        <form method="POST" action=""
            onsubmit="return confirm('Are you sure you want to delete the selected bookings?');">
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <div class="flex justify-between items-center p-4 bg-gray-100">
                    <h2 class="text-xl font-semibold">Bookings List</h2>
                    <button type="submit" name="delete_selected"
                        class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                        Delete Selected
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Booking ID</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    User</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Slot</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Vehicle Number</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Time</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Duration</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    End Time</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Cost/hr</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total Cost</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    BKash Number</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    BKash Pin</th>
                                <!-- <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions</th> -->
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="selected_bookings[]"
                                            value="<?php echo $booking['id']; ?>" class="booking-checkbox">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['username']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['slot_number']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['vehicle_number']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['booking_date']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['booking_time']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['duration']; ?> hours</td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['end_time']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['cost_per_hour']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['total_cost']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['bkash_number']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['bkash_pin']; ?></td>
                                    <!-- <td class="px-6 py-4 whitespace-nowrap">
                                        <a class="text-red-600 hover:text-red-900"
                                            onclick="return confirm('Are you sure you want to delete this booking?')"
                                            href="delete_booking.php?id=<?php echo $booking['id']; ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td> -->
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>

    <script>
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.booking-checkbox');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        function toggleMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            mobileMenu.classList.toggle('hidden');
        }
    </script>
</body>

</html>