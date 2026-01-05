<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: budgets.php?error=Invalid budget ID');
    exit;
}

$stmt = $conn->prepare("DELETE FROM budgets WHERE id=?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    header('Location: budgets.php?success=Budget deleted successfully');
} else {
    header('Location: budgets.php?error=Failed to delete budget');
}

$stmt->close();
exit;
?>
