<?php
session_start();

// Redirect to login if not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// include the database connection file
require 'database/db.php';

// Fetch all bookings
$bookings = [];
$result = $conn->query("SELECT bookings.*, users.username, parking_slots.slot_number FROM bookings
                        JOIN users ON bookings.user_id = users.id
                        JOIN parking_slots ON bookings.slot_id = parking_slots.id");
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
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <!-- Navbar -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="text-2xl font-bold text-blue-600">ParkEase Admin</div>
                <div>
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Dashboard -->
    <div class="max-w-6xl mx-auto px-4 py-8">
        <h2 class="text-3xl font-bold mb-6">All Bookings</h2>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left">User</th>
                        <th class="text-left">Slot</th>
                        <th class="text-left">Vehicle Number</th>
                        <th class="text-left">Date</th>
                        <th class="text-left">Time</th>
                        <th class="text-left">Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr class="border-t">
                            <td class="py-2"><?php echo $booking['username']; ?></td>
                            <td class="py-2"><?php echo $booking['slot_number']; ?></td>
                            <td class="py-2"><?php echo $booking['vehicle_number']; ?></td>
                            <td class="py-2"><?php echo $booking['booking_date']; ?></td>
                            <td class="py-2"><?php echo $booking['booking_time']; ?></td>
                            <td class="py-2"><?php echo $booking['duration']; ?> hours</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>