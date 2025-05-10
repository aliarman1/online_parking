<?php
/**
 * Parking Spot Management for Online Parking Admin Panel
 */
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Handle parking spot actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new parking spot
    if (isset($_POST['add_spot'])) {
        $spot_number = trim($_POST['spot_number']);
        $floor_number = (int)$_POST['floor_number'];
        $type = $_POST['type'];
        $hourly_rate = (float)$_POST['hourly_rate'];

        // Check if spot number already exists
        $check_sql = "SELECT COUNT(*) as count FROM parking_spots WHERE spot_number = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $spot_number);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();

        if ($result['count'] > 0) {
            $_SESSION['message'] = "Spot number already exists.";
            $_SESSION['message_type'] = "danger";
        } else {
            $sql = "INSERT INTO parking_spots (spot_number, floor_number, type, hourly_rate) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisd", $spot_number, $floor_number, $type, $hourly_rate);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Parking spot added successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error adding parking spot: " . $conn->error;
                $_SESSION['message_type'] = "danger";
            }
        }

        header("Location: parking-spots.php");
        exit();
    }

    // Edit parking spot
    if (isset($_POST['edit_spot'])) {
        $spot_id = $_POST['spot_id'];
        $spot_number = trim($_POST['spot_number']);
        $floor_number = (int)$_POST['floor_number'];
        $type = $_POST['type'];
        $hourly_rate = (float)$_POST['hourly_rate'];

        // Check if spot number already exists (excluding the current spot)
        $check_sql = "SELECT COUNT(*) as count FROM parking_spots WHERE spot_number = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $spot_number, $spot_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();

        if ($result['count'] > 0) {
            $_SESSION['message'] = "Spot number already exists.";
            $_SESSION['message_type'] = "danger";
        } else {
            $sql = "UPDATE parking_spots SET spot_number = ?, floor_number = ?, type = ?, hourly_rate = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisdi", $spot_number, $floor_number, $type, $hourly_rate, $spot_id);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Parking spot updated successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error updating parking spot: " . $conn->error;
                $_SESSION['message_type'] = "danger";
            }
        }

        header("Location: parking-spots.php");
        exit();
    }

    // Delete parking spot
    if (isset($_POST['delete_spot'])) {
        $spot_id = $_POST['spot_id'];

        // Check if spot has any bookings
        $check_sql = "SELECT COUNT(*) as count FROM bookings WHERE spot_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $spot_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();

        if ($result['count'] > 0) {
            $_SESSION['message'] = "Cannot delete spot with existing bookings.";
            $_SESSION['message_type'] = "danger";
        } else {
            $sql = "DELETE FROM parking_spots WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $spot_id);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Parking spot deleted successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error deleting parking spot: " . $conn->error;
                $_SESSION['message_type'] = "danger";
            }
        }

        header("Location: parking-spots.php");
        exit();
    }
}

// Handle search and filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_floor = isset($_GET['floor']) ? $_GET['floor'] : 'all';
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Build the WHERE clause
$where_clause = "";
$params = [];
$types = "";

if (!empty($search)) {
    $where_clause = " WHERE spot_number LIKE ?";
    $search_param = "%$search%";
    $params[] = $search_param;
    $types .= "s";
}

if ($filter_floor !== 'all') {
    if (empty($where_clause)) {
        $where_clause = " WHERE floor_number = ?";
    } else {
        $where_clause .= " AND floor_number = ?";
    }
    $params[] = $filter_floor;
    $types .= "i";
}

if ($filter_type !== 'all') {
    if (empty($where_clause)) {
        $where_clause = " WHERE type = ?";
    } else {
        $where_clause .= " AND type = ?";
    }
    $params[] = $filter_type;
    $types .= "s";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get total number of parking spots
$total_sql = "SELECT COUNT(*) as total FROM parking_spots" . $where_clause;
$total_stmt = $conn->prepare($total_sql);

if (!empty($params)) {
    $total_stmt->bind_param($types, ...$params);
}

$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_spots = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_spots / $records_per_page);

// Get parking spots with pagination
$spots_sql = "SELECT * FROM parking_spots" . $where_clause . " ORDER BY floor_number, spot_number LIMIT ?, ?";
$spots_stmt = $conn->prepare($spots_sql);

// Add pagination parameters
$params[] = $offset;
$params[] = $records_per_page;
$types .= "ii";

if (!empty($params)) {
    $spots_stmt->bind_param($types, ...$params);
}

$spots_stmt->execute();
$spots = $spots_stmt->get_result();

// Get unique floor numbers for filter
$floors_sql = "SELECT DISTINCT floor_number FROM parking_spots ORDER BY floor_number";
$floors_result = $conn->query($floors_sql);
$floors = [];
while ($floor = $floors_result->fetch_assoc()) {
    $floors[] = $floor['floor_number'];
}

// Get spot types for filter
$types_sql = "SELECT DISTINCT type FROM parking_spots ORDER BY type";
$types_result = $conn->query($types_sql);
$spot_types = [];
while ($type = $types_result->fetch_assoc()) {
    $spot_types[] = $type['type'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Spots Management - Online Parking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Parking Spots Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSpotModal">
                    <i class="fas fa-plus"></i> Add New Spot
                </button>
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
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <h5 class="mb-0">Parking Spots</h5>
                        <div class="d-flex gap-2">
                            <form action="" method="GET" class="d-flex">
                                <input type="text" name="search" class="form-control" placeholder="Search spot number" value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary ms-2">
                                    <i class="fas fa-search"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex flex-wrap gap-2">
                            <div class="btn-group" role="group">
                                <a href="?floor=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_type !== 'all' ? '&type=' . urlencode($filter_type) : ''; ?>" class="btn btn-<?php echo $filter_floor === 'all' ? 'primary' : 'outline-primary'; ?>">All Floors</a>
                                <?php foreach ($floors as $floor): ?>
                                    <a href="?floor=<?php echo $floor; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_type !== 'all' ? '&type=' . urlencode($filter_type) : ''; ?>" class="btn btn-<?php echo $filter_floor == $floor ? 'primary' : 'outline-primary'; ?>">Floor <?php echo $floor; ?></a>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="btn-group ms-auto" role="group">
                                <a href="?type=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_floor !== 'all' ? '&floor=' . urlencode($filter_floor) : ''; ?>" class="btn btn-<?php echo $filter_type === 'all' ? 'primary' : 'outline-primary'; ?>">All Types</a>
                                <?php foreach ($spot_types as $type): ?>
                                    <a href="?type=<?php echo urlencode($type); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_floor !== 'all' ? '&floor=' . urlencode($filter_floor) : ''; ?>" class="btn btn-<?php echo $filter_type === $type ? 'primary' : 'outline-primary'; ?>"><?php echo ucfirst($type); ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Spot Number</th>
                                    <th>Floor</th>
                                    <th>Type</th>
                                    <th>Hourly Rate</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($spots->num_rows > 0): ?>
                                    <?php while ($spot = $spots->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $spot['id']; ?></td>
                                            <td><?php echo htmlspecialchars($spot['spot_number']); ?></td>
                                            <td><?php echo $spot['floor_number']; ?></td>
                                            <td><?php echo ucfirst(htmlspecialchars($spot['type'])); ?></td>
                                            <td>$<?php echo number_format($spot['hourly_rate'], 2); ?></td>
                                            <td>
                                                <?php if ($spot['is_occupied']): ?>
                                                    <span class="badge bg-danger">Occupied</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Available</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-warning text-white" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editSpotModal" 
                                                            data-id="<?php echo $spot['id']; ?>"
                                                            data-spot-number="<?php echo htmlspecialchars($spot['spot_number']); ?>"
                                                            data-floor-number="<?php echo $spot['floor_number']; ?>"
                                                            data-type="<?php echo htmlspecialchars($spot['type']); ?>"
                                                            data-hourly-rate="<?php echo $spot['hourly_rate']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="spot_id" value="<?php echo $spot['id']; ?>">
                                                        <button type="submit" name="delete_spot" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this parking spot?');">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">No parking spots found</td>
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
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_floor !== 'all' ? '&floor=' . urlencode($filter_floor) : ''; ?><?php echo $filter_type !== 'all' ? '&type=' . urlencode($filter_type) : ''; ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_floor !== 'all' ? '&floor=' . urlencode($filter_floor) : ''; ?><?php echo $filter_type !== 'all' ? '&type=' . urlencode($filter_type) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_floor !== 'all' ? '&floor=' . urlencode($filter_floor) : ''; ?><?php echo $filter_type !== 'all' ? '&type=' . urlencode($filter_type) : ''; ?>">
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
    
    <!-- Add Spot Modal -->
    <div class="modal fade" id="addSpotModal" tabindex="-1" aria-labelledby="addSpotModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSpotModalLabel">Add New Parking Spot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="addSpotForm">
                        <div class="mb-3">
                            <label for="spot_number" class="form-label">Spot Number</label>
                            <input type="text" class="form-control" id="spot_number" name="spot_number" required>
                            <div class="form-text">Enter a unique identifier for the spot (e.g., A1, B2)</div>
                        </div>
                        <div class="mb-3">
                            <label for="floor_number" class="form-label">Floor Number</label>
                            <input type="number" class="form-control" id="floor_number" name="floor_number" min="0" required>
                            <div class="form-text">Enter the floor number (0 for ground floor)</div>
                        </div>
                        <div class="mb-3">
                            <label for="type" class="form-label">Spot Type</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="Car">Car</option>
                                <option value="Bike">Bike</option>
                                <option value="VIP">VIP</option>
                                <option value="handicap">Handicap</option>
                                <option value="electric">Electric</option>
                                <option value="standard">Standard</option>
                            </select>
                            <div class="form-text">Select the type of parking spot</div>
                        </div>
                        <div class="mb-3">
                            <label for="hourly_rate" class="form-label">Hourly Rate ($)</label>
                            <input type="number" class="form-control" id="hourly_rate" name="hourly_rate" min="0" step="0.01" required>
                            <div class="form-text">Enter the hourly rate for this spot</div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_spot" class="btn btn-primary">Add Spot</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Spot Modal -->
    <div class="modal fade" id="editSpotModal" tabindex="-1" aria-labelledby="editSpotModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSpotModalLabel">Edit Parking Spot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="editSpotForm">
                        <input type="hidden" id="edit_spot_id" name="spot_id">
                        <div class="mb-3">
                            <label for="edit_spot_number" class="form-label">Spot Number</label>
                            <input type="text" class="form-control" id="edit_spot_number" name="spot_number" required>
                            <div class="form-text">Enter a unique identifier for the spot (e.g., A1, B2)</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_floor_number" class="form-label">Floor Number</label>
                            <input type="number" class="form-control" id="edit_floor_number" name="floor_number" min="0" required>
                            <div class="form-text">Enter the floor number (0 for ground floor)</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_type" class="form-label">Spot Type</label>
                            <select class="form-select" id="edit_type" name="type" required>
                                <option value="Car">Car</option>
                                <option value="Bike">Bike</option>
                                <option value="VIP">VIP</option>
                                <option value="handicap">Handicap</option>
                                <option value="electric">Electric</option>
                                <option value="standard">Standard</option>
                            </select>
                            <div class="form-text">Select the type of parking spot</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_hourly_rate" class="form-label">Hourly Rate ($)</label>
                            <input type="number" class="form-control" id="edit_hourly_rate" name="hourly_rate" min="0" step="0.01" required>
                            <div class="form-text">Enter the hourly rate for this spot</div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit_spot" class="btn btn-primary">Update Spot</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit spot modal data population
        const editSpotModal = document.getElementById('editSpotModal');
        if (editSpotModal) {
            editSpotModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const spotNumber = button.getAttribute('data-spot-number');
                const floorNumber = button.getAttribute('data-floor-number');
                const type = button.getAttribute('data-type');
                const hourlyRate = button.getAttribute('data-hourly-rate');
                
                document.getElementById('edit_spot_id').value = id;
                document.getElementById('edit_spot_number').value = spotNumber;
                document.getElementById('edit_floor_number').value = floorNumber;
                document.getElementById('edit_type').value = type;
                document.getElementById('edit_hourly_rate').value = hourlyRate;
            });
        }
    </script>
</body>
</html>
