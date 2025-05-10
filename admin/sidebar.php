<?php
/**
 * Admin sidebar component for Online Parking
 */

// Check if user is logged in and is an admin
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Get current page for highlighting active menu item
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="logo">
        <img src="https://img.icons8.com/color/96/000000/parking.png" alt="Online Parking Logo">
        <h2>Admin Panel</h2>
    </div>
    <nav>
        <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="users.php" class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> User Management
        </a>
        <a href="parking-spots.php" class="<?php echo $current_page == 'parking-spots.php' ? 'active' : ''; ?>">
            <i class="fas fa-parking"></i> Parking Spots
        </a>
        <a href="bookings.php" class="<?php echo $current_page == 'bookings.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i> Bookings
        </a>
        <a href="payments.php" class="<?php echo $current_page == 'payments.php' ? 'active' : ''; ?>">
            <i class="fas fa-money-bill-wave"></i> Payments
        </a>
        <a href="reports.php" class="<?php echo ($current_page == 'reports.php' || $current_page == 'profit-analysis.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i> Reports
        </a>
        <a href="../dashboard.php" class="<?php echo $current_page == '../dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i> User Dashboard
        </a>
        <a href="../logout.php" class="logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</div>
