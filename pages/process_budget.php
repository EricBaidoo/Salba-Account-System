<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
include '../includes/budget_functions.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$action = $_POST['action'] ?? 'create';

if ($action === 'create') {
    $category = $_POST['category'] ?? '';
    $description = $_POST['description'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $term = $_POST['term'] ?? '';
    $academic_year = $_POST['academic_year'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $alert_threshold = (int)($_POST['alert_threshold'] ?? 80);

    if (!$category || $amount <= 0 || !$term || !$academic_year || !$start_date || !$end_date) {
        header('Location: add_budget_form.php?error=Invalid data');
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO budgets (category, description, amount, term, academic_year, start_date, end_date, alert_threshold, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('ssdsssi', $category, $description, $amount, $term, $academic_year, $start_date, $end_date, $alert_threshold);
    
    if ($stmt->execute()) {
        header('Location: budgets.php?success=Budget created successfully');
    } else {
        header('Location: add_budget_form.php?error=Failed to create budget');
    }
    $stmt->close();

} elseif ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $category = $_POST['category'] ?? '';
    $description = $_POST['description'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $alert_threshold = (int)($_POST['alert_threshold'] ?? 80);

    if (!$id || !$category || $amount <= 0 || !$start_date || !$end_date) {
        header('Location: budgets.php?error=Invalid data');
        exit;
    }

    $stmt = $conn->prepare("UPDATE budgets SET category=?, description=?, amount=?, start_date=?, end_date=?, alert_threshold=? WHERE id=?");
    $stmt->bind_param('ssdssii', $category, $description, $amount, $start_date, $end_date, $alert_threshold, $id);
    
    if ($stmt->execute()) {
        header('Location: budgets.php?success=Budget updated successfully');
    } else {
        header('Location: edit_budget_form.php?id=' . $id . '&error=Failed to update budget');
    }
    $stmt->close();

} else {
    header('Location: budgets.php');
}

exit;
?>
