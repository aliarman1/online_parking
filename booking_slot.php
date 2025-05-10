<?php
// Include database connection file (which already starts the session)
require 'database/db.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    echo "Please log in to view booking details.";
    exit();
}
if (isset($_POST["username"])) {
    $_SESSION['username'] = $_POST['username'];
}
if (isset($_POST["email"])) {
    $_SESSION['email'] = $_POST['email'];
}

// Handle bulk delete operation
if (isset($_POST['delete_selected'])) {
    if (!empty($_POST['selected_bookings'])) {
        $selected_bookings = $_POST['selected_bookings'];
        $placeholders = implode(',', array_fill(0, count($selected_bookings), '?')); // Create placeholders for prepared statement
        $delete_query = "DELETE FROM bookings WHERE id IN ($placeholders) AND user_id = ?";
        $stmt = $conn->prepare($delete_query);

        // Bind parameters dynamically
        $types = str_repeat('i', count($selected_bookings)) . 'i'; // e.g., "iii" for 3 selected items
        $params = array_merge($selected_bookings, [$_SESSION['user_id']]); // Combine selected IDs and user ID
        $stmt->bind_param($types, ...$params); // Bind parameters

        if ($stmt->execute()) {
            echo "<script>alert('Selected bookings deleted successfully.');</script>";
        } else {
            echo "<script>alert('Error deleting selected bookings.');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('No bookings selected.');</script>";
    }
}


// Handle delete operation
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $delete_query = "DELETE FROM bookings WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("ii", $delete_id, $_SESSION['user_id']);
    if ($stmt->execute()) {
        echo "<script>alert('Booking deleted successfully.');</script>";
    } else {
        echo "<script>alert('Error deleting booking.');</script>";
    }
    $stmt->close();
}

// Handle CSV download
if (isset($_GET['download_csv'])) {
    $status_filter = $_GET['status_filter'] ?? '';
    $query = "
        SELECT
            id AS booking_id,
            slot_id,
            vehicle_number,
            booking_date,
            booking_time,
            duration,
            end_time,
            cost_per_hour,
            total_cost,
            bkash_number,
            bkash_pin,
            CASE
                WHEN NOW() < CONCAT(booking_date, ' ', booking_time) THEN 'Pending'
                WHEN NOW() BETWEEN CONCAT(booking_date, ' ', booking_time) AND CONCAT(booking_date, ' ', end_time) THEN 'Active'
                ELSE 'Completed'
            END AS status
        FROM
            bookings
        WHERE
            user_id = ?
    ";
    if (!empty($status_filter)) {
        $query .= " HAVING status = ?";
    }
    $stmt = $conn->prepare($query);
    if (!empty($status_filter)) {
        $stmt->bind_param("is", $_SESSION['user_id'], $status_filter);
    } else {
        $stmt->bind_param("i", $_SESSION['user_id']);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Output CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="bookings.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, array('Booking ID', 'Slot ID', 'Vehicle Number', 'Booking Date', 'Booking Time', 'Duration', 'End Time', 'Cost Per Hour', 'Total Cost', 'Bkash Number', 'Bkash PIN', 'Status'));

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

// Fetch booking details for the logged-in user with dynamic status calculation
$status_filter = $_GET['status_filter'] ?? '';
$query = "
    SELECT
        id AS booking_id,
        slot_id,
        vehicle_number,
        booking_date,
        booking_time,
        duration,
        end_time,
        cost_per_hour,
        total_cost,
        bkash_number,
        bkash_pin,
        CASE
            WHEN NOW() < CONCAT(booking_date, ' ', booking_time) THEN 'Pending'
            WHEN NOW() BETWEEN CONCAT(booking_date, ' ', booking_time) AND CONCAT(booking_date, ' ', end_time) THEN 'Active'
            ELSE 'Completed'
        END AS status
    FROM
        bookings
    WHERE
        user_id = ?
";
if (!empty($status_filter)) {
    $query .= " HAVING status = ?";
}
$stmt = $conn->prepare($query);
if (!empty($status_filter)) {
    $stmt->bind_param("is", $_SESSION['user_id'], $status_filter);
} else {
    $stmt->bind_param("i", $_SESSION['user_id']);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-100 dark:bg-gray-900">

    <!-- navbar -->
    <div class="navbar bg-base-100 shadow-sm sticky top-0">
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
                            class="bg-green-600 text-white px-4 py-2 mb-2 rounded-lg hover:bg-green-700">Booking
                            History</a>
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
                            class="bg-green-600 text-white px-4 py-2 mr-2 rounded-lg hover:bg-green-700">Booking
                            History</a>
                    </li>
                    <li><a href="profile.php"
                            class="bg-purple-600 text-white px-4 py-2 mr-2 rounded-lg hover:bg-purple-700">Profile</a>
                    </li>
                </ul>
            </div>
            <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Logout</a>
        </div>
    </div>

    <div class="container mx-auto p-4">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md mb-6 ">
            <h1 class="text-2xl font-bold mb-4 dark:text-white">Your Booking Details</h1>

            <div class="bg-white dark:bg-gray-800 p-2 rounded-lg shadow-md">
                <h1 class="text-center mb-4 dark:text-white">User_Name:
                    <?php echo $_SESSION['username']; ?>
                </h1>
                <h1 class="text-center mb-4 dark:text-white">Email: <?php echo $_SESSION['email']; ?></h1>
            </div>

            <!-- Filter Form -->
            <form method="GET" action="" class="mb-4">
                <label for="status_filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Filter by
                    Status:</label>
                <div class="mt-1 flex flex-col md:flex-row gap-2">
                    <select name="status_filter" id="status_filter"
                        class="w-full md:w-auto pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-700 dark:text-white">
                        <option value="">All</option>
                        <option value="Pending" <?php echo ($status_filter == 'Pending') ? 'selected' : ''; ?>>Pending
                        </option>
                        <option value="Active" <?php echo ($status_filter == 'Active') ? 'selected' : ''; ?>>Active
                        </option>
                        <option value="Completed" <?php echo ($status_filter == 'Completed') ? 'selected' : ''; ?>>
                            Completed</option>
                    </select>
                    <button type="submit"
                        class="w-full md:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Filter</button>
                </div>
            </form>

            <!-- Download CSV Button -->
            <form method="GET" action="">
                <input type="hidden" name="status_filter" value="<?php echo $status_filter; ?>">
                <button type="submit" name="download_csv"
                    class="w-full md:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">Download
                    CSV</button>


            </form>


        </div>


        <!-- Bulk Delete Form -->
        <form method="POST" action="" class="mb-4">


            <div class="overflow-x-auto relative">
                <table class="min-w-full bg-white dark:bg-gray-800 rounded-lg shadow-md mt-6">
                    <caption class="text-lg font-medium text-gray-900 dark:text-white mb-2">Booking Details</caption>
                    <button type="submit" name="delete_selected"
                        class="absolute top-0 left-0 w-full md:w-auto text-center inline-flex items-center justify-center px-4 py-2 my-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">Delete
                        Selected</button>
                    <thead>

                        <tr>
                            <th
                                class="px-4 py-2 border-b-2 border-gray-300 dark:border-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Select</th>
                            <th
                                class="px-4 py-2 border-b-2 border-gray-300 dark:border-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Booking ID</th>
                            <th
                                class="px-4 py-2 border-b-2 border-gray-300 dark:border-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Slot ID</th>
                            <th
                                class="px-4 py-2 border-b-2 border-gray-300 dark:border-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Vehicle Number</th>
                            <th
                                class="px-4 py-2 border-b-2 border-gray-300 dark:border-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Booking Date</th>
                            <th
                                class="px-4 py-2 border-b-2 border-gray-300 dark:border-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Booking Time</th>
                            <th
                                class="px-4 py-2 border-b-2 border-gray-300 dark:border-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Duration (hours)</th>
                            <th
                                class="px-4 py-2 border-b-2 border-gray-300 dark:border-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                End Time</th>
                            <th
                                class="px-4 py-2 border-b-2 border-gray-300 dark:border-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Cost Per Hour</th>
                            <th
                                class="px-4 py-2 border-b-2 border-gray-300 dark:border-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Total Cost</th>
                            <th
                                class="px-4 py-2 border-b-2 border-gray-300 dark:border-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Bkash Number</th>
                            <th
                                class="px-4 py-2 border-b-2 border-gray-300 dark:border-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Bkash PIN</th>
                            <th
                                class="px-4 py-2 border-b-2 border-gray-300 dark:border-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $status_class = '';
                                switch ($row['status']) {
                                    case 'Pending':
                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                        break;
                                    case 'Active':
                                        $status_class = 'bg-green-100 text-green-800';
                                        break;
                                    case 'Completed':
                                        $status_class = 'bg-red-100 text-red-800';
                                        break;
                                }

                                echo "<tr>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100'><input type='checkbox' name='selected_bookings[]' value='" . $row['booking_id'] . "'></td>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100'>" . htmlspecialchars($row['booking_id']) . "</td>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100'>" . htmlspecialchars($row['slot_id']) . "</td>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100'>" . htmlspecialchars($row['vehicle_number']) . "</td>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100'>" . htmlspecialchars($row['booking_date']) . "</td>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100'>" . htmlspecialchars($row['booking_time']) . "</td>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100'>" . htmlspecialchars($row['duration']) . "</td>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100'>" . htmlspecialchars($row['end_time']) . "</td>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100'>$" . htmlspecialchars(number_format($row['cost_per_hour'], 2)) . "</td>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100'>$" . htmlspecialchars(number_format($row['total_cost'], 2)) . "</td>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100'>" . htmlspecialchars($row['bkash_number']) . "</td>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100'>" . htmlspecialchars($row['bkash_pin']) . "</td>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm $status_class'>" . htmlspecialchars($row['status']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='13' class='px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100'>No bookings found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>