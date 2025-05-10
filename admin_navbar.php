<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <div class="navbar bg-base-100 shadow-sm sticky top-0 z-50">
        <div class="navbar-start">
            <!-- Mobile menu button -->
            <div class="dropdown lg:hidden">
                <!-- Your mobile menu code -->
            </div>
            <!-- Logo -->
            <a class="btn btn-ghost text-xl">ParkEase</a>
        </div>

        <!-- Desktop navigation -->
        <div class="navbar-center hidden lg:flex">
            <ul class="menu menu-horizontal px-1">
                <li><a href="#" data-page="a_dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="#" data-page="a_user.php" class="nav-link">User</a></li>
                <!-- <li><a href="#" data-page="booking_slot.php" class="nav-link">Booking History</a></li>
                <li><a href="#" data-page="profile.php" class="nav-link">Profile</a></li> -->
            </ul>
        </div>

        <!-- Right-side profile & logout -->
        <div class="navbar-end">
            <!-- Your profile dropdown -->
            <a href="logout.php" class="btn btn-error ml-2">Logout</a>
        </div>
    </div>
</body>

</html>