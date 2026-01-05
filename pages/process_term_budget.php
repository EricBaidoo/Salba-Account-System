<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$term = $_POST['term'] ?? '';
$academic_year = $_POST['academic_year'] ?? '';
$income_categories = $_POST['income_category'] ?? [];
$income_amounts = $_POST['income_amount'] ?? [];
$categories = $_POST['category'] ?? [];
$amounts = $_POST['amount'] ?? [];

if (!$term || !$academic_year) {
    header('Location: edit_term_budget.php?error=Please fill in all required fields');
    exit;
}

// Calculate total expected income from all fee categories
$expected_income = 0;
foreach ($income_amounts as $amt) {
    $expected_income += (float)$amt;
}

// Check if budget exists
$existing = $conn->query("SELECT id FROM term_budgets WHERE term = '$term' AND academic_year = '$academic_year'")->fetch_assoc();

if ($existing) {
    // Update existing
    $budget_id = $existing['id'];
    $stmt = $conn->prepare("UPDATE term_budgets SET expected_income = ? WHERE id = ?");
    $stmt->bind_param('di', $expected_income, $budget_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete old items
    $conn->query("DELETE FROM term_budget_items WHERE term_budget_id = $budget_id");
} else {
    // Create new
    $stmt = $conn->prepare("INSERT INTO term_budgets (term, academic_year, expected_income, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param('ssd', $term, $academic_year, $expected_income);
    $stmt->execute();
    $budget_id = $conn->insert_id;
    $stmt->close();
}

// Add income budget items
for ($i = 0; $i < count($income_categories); $i++) {
    if (!empty($income_categories[$i]) && !empty($income_amounts[$i]) && (float)$income_amounts[$i] > 0) {
        $amount = (float)$income_amounts[$i];
        $category = $income_categories[$i];
        $type = 'income';
        $stmt = $conn->prepare("INSERT INTO term_budget_items (term_budget_id, category, amount, type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isds', $budget_id, $category, $amount, $type);
        $stmt->execute();
        $stmt->close();
    }
}

// Add expense budget items
for ($i = 0; $i < count($categories); $i++) {
    if (!empty($categories[$i]) && !empty($amounts[$i])) {
        $amount = (float)$amounts[$i];
        $category = $categories[$i];
        $type = 'expense'; // All budget items from this form are expenses
        $stmt = $conn->prepare("INSERT INTO term_budget_items (term_budget_id, category, amount, type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isds', $budget_id, $category, $amount, $type);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: term_budget.php?term=' . urlencode($term) . '&academic_year=' . urlencode($academic_year) . '&success=Budget saved successfully');
exit;
?>
