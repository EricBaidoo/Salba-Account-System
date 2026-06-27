<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$semester      = $_POST['semester'] ?? '';
$academic_year = $_POST['academic_year'] ?? '';
$income_categories = $_POST['income_category'] ?? [];
$income_amounts    = $_POST['income_amount'] ?? [];

if (!$semester || !$academic_year) {
    header('Location: edit_semester_budget.php?error=Please fill in all required fields');
    exit;
}

$expected_income = 0;
foreach ($income_amounts as $amt) $expected_income += (float)$amt;

// Upsert semester_budgets header
$existing = $conn->query("SELECT id FROM semester_budgets WHERE semester = '" . $conn->real_escape_string($semester) . "' AND academic_year = '" . $conn->real_escape_string($academic_year) . "'")->fetch_assoc();

if ($existing) {
    $budget_id = $existing['id'];
    $stmt = $conn->prepare("UPDATE semester_budgets SET expected_income = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('di', $expected_income, $budget_id);
    $stmt->execute();
    $stmt->close();
    // Remove only income items — leave expense items (managed via drill-down CRUD) untouched
    $conn->query("DELETE FROM semester_budget_items WHERE semester_budget_id = $budget_id AND type = 'income'");
} else {
    $stmt = $conn->prepare("INSERT INTO semester_budgets (semester, academic_year, expected_income, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param('ssd', $semester, $academic_year, $expected_income);
    $stmt->execute();
    $budget_id = $conn->insert_id;
    $stmt->close();
}

// Re-insert income items
for ($i = 0; $i < count($income_categories); $i++) {
    if (!empty($income_categories[$i]) && isset($income_amounts[$i]) && (float)$income_amounts[$i] > 0) {
        $amount   = (float)$income_amounts[$i];
        $category = $income_categories[$i];
        $type     = 'income';
        $stmt = $conn->prepare("INSERT INTO semester_budget_items (semester_budget_id, category, amount, type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isds', $budget_id, $category, $amount, $type);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: semester_budget.php?semester=' . urlencode($semester) . '&academic_year=' . urlencode($academic_year) . '&success=Income+budget+saved');
exit;
