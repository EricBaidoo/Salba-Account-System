<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category']);
    $amount = floatval($_POST['amount']);
    $expense_date = $_POST['expense_date'];
    $description = trim($_POST['description']);

    $stmt = $conn->prepare("INSERT INTO expenses (category, amount, expense_date, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sdss", $category, $amount, $expense_date, $description);
    if ($stmt->execute()) {
        echo "<div class='alert alert-success'>Expense added successfully!</div>";
    } else {
        echo "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Expense - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="page-header rounded shadow-sm mb-4 p-4 text-center">
            <h2 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Add Expense</h2>
            <p class="lead mb-0">Record a new expense and assign it to a category for better reporting.</p>
        </div>
        <div class="main-content p-4">
            <a href="add_expense_form.php" class="btn btn-secondary mb-3 me-2"><i class="fas fa-arrow-left me-1"></i>Back to Add Expense Form</a>
            <a href="view_expenses.php" class="btn btn-info mb-3"><i class="fas fa-list me-1"></i>View All Expenses</a>
        </div>
    </div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>