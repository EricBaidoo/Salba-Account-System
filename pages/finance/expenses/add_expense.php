<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}

include '../../../includes/system_settings.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = intval($_POST['category_id']);
    $amount = floatval($_POST['amount']);
    $expense_date = $_POST['expense_date'];
    $description = trim($_POST['description']);
    $semester = trim($_POST['semester'] ?? '');
    $academic_year = trim($_POST['academic_year'] ?? '');
    
    // Get current values if not provided
    if (empty($semester)) { $semester = getCurrentSemester($conn); }
    if (empty($academic_year)) { $academic_year = getAcademicYear($conn); }

    $stmt = $conn->prepare("INSERT INTO expenses (category_id, amount, expense_date, description, semester, academic_year) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("idssss", $category_id, $amount, $expense_date, $description, $semester, $academic_year);
    if ($stmt->execute()) {
        echo "<div class='p-4 bg-green-100 text-green-700 rounded border border-green-200'>Expense added successfully!</div>";
    } else {
        echo "<div class='p-4 bg-red-100 text-red-700 rounded border border-red-200'>Error: " . $stmt->error . "</div>";
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
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
        <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body>
    <div class="max-w-7xl mx-auto mt-5">
        <div class="page-header rounded shadow-sm mb- p-4 text-center">
            <h2 class="mb-"><i class="fas fa-money-bill-wave mr-2"></i>Add Expense</h2>
            <p class="lead mb-">Record a new expense and assign it to a category for better reporting.</p>
        </div>
        <div class="main-content p-4">
            <a href="add_expense_form.php" class="px-4 py-2 bg-gray-600 text-white rounded mb- mr-2"><i class="fas fa-arrow-left mr-1"></i>Back to Add Expense Form</a>
            <a href="view_expenses.php" class="px-3 py-2 rounded px-3 py-2 rounded-info mb-"><i class="fas fa-list mr-1"></i>View All Expenses</a>
        </div>
    </div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </body>
</html>
