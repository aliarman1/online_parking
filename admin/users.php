<?php
/**
 * User Management for Online Parking Admin Panel
 */
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toggle admin status
    if (isset($_POST['toggle_admin'])) {
        $user_id = $_POST['user_id'];
        $is_admin = $_POST['is_admin'] ? 0 : 1; // Toggle the value

        $sql = "UPDATE users SET is_admin = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $is_admin, $user_id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "User admin status updated successfully.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating user admin status.";
            $_SESSION['message_type'] = "error";
        }

        header("Location: users.php");
        exit();
    }

    // Delete user
    if (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];

        // Check if user has any bookings
        $check_sql = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();

        if ($result['count'] > 0) {
            $_SESSION['message'] = "Cannot delete user with existing bookings.";
            $_SESSION['message_type'] = "error";
        } else {
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                $_SESSION['message'] = "User deleted successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error deleting user.";
                $_SESSION['message_type'] = "error";
            }
        }

        header("Location: users.php");
        exit();
    }
}

// Handle search and filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build the WHERE clause
$where_clause = "";
$params = [];
$types = "";

if (!empty($search)) {
    $where_clause = " WHERE (name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($filter !== 'all') {
    $is_admin = ($filter === 'admin') ? 1 : 0;
    if (empty($where_clause)) {
        $where_clause = " WHERE is_admin = ?";
    } else {
        $where_clause .= " AND is_admin = ?";
    }
    $params[] = $is_admin;
    $types .= "i";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get total number of users
$total_sql = "SELECT COUNT(*) as total FROM users" . $where_clause;
$total_stmt = $conn->prepare($total_sql);

if (!empty($params)) {
    $total_stmt->bind_param($types, ...$params);
}

$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_users = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_users / $records_per_page);

// Get users with pagination
$users_sql = "SELECT * FROM users" . $where_clause . " ORDER BY created_at DESC LIMIT ?, ?";
$users_stmt = $conn->prepare($users_sql);

// Add pagination parameters
$params[] = $offset;
$params[] = $records_per_page;
$types .= "ii";

if (!empty($params)) {
    $users_stmt->bind_param($types, ...$params);
}

$users_stmt->execute();
$users = $users_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Online Parking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>User Management</h1>
                <a href="add-user.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New User
                </a>
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
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Users List</h5>
                        <div class="d-flex gap-2">
                            <form action="" method="GET" class="d-flex">
                                <input type="text" name="search" class="form-control" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary ms-2">
                                    <i class="fas fa-search"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="btn-group" role="group">
                            <a href="?filter=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-<?php echo $filter === 'all' ? 'primary' : 'outline-primary'; ?>">All Users</a>
                            <a href="?filter=admin<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-<?php echo $filter === 'admin' ? 'primary' : 'outline-primary'; ?>">Admins</a>
                            <a href="?filter=user<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-<?php echo $filter === 'user' ? 'primary' : 'outline-primary'; ?>">Regular Users</a>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users->num_rows > 0): ?>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['is_admin'] ? 'primary' : 'secondary'; ?>">
                                                    <?php echo $user['is_admin'] ? 'Admin' : 'User'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="user-details.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info text-white">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning text-white">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="is_admin" value="<?php echo $user['is_admin']; ?>">
                                                        <button type="submit" name="toggle_admin" class="btn btn-sm btn-<?php echo $user['is_admin'] ? 'secondary' : 'primary'; ?>" title="<?php echo $user['is_admin'] ? 'Remove admin rights' : 'Make admin'; ?>">
                                                            <i class="fas fa-<?php echo $user['is_admin'] ? 'user' : 'user-shield'; ?>"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" name="delete_user" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">No users found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . $filter : ''; ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . $filter : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . $filter : ''; ?>">
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
