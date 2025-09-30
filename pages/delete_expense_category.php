<?php
include '../includes/auth_check.php';
include '../includes/db_connect.php';
$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM expense_categories WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}
header('Location: add_expense_category_form.php');
exit;
