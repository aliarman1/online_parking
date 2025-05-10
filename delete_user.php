<?php
include 'database/db.php';

// Check if ID is provided
if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$id = $_GET['id'];

// Delete user from database
$query = "DELETE FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: a_user.php");
} else {
    header("Location: a_user.php");
}
exit();
?>