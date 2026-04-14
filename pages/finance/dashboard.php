<?php
include_once '../../includes/auth_functions.php';
include_once '../../includes/db_connect.php';
include_once '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../includes/login.php');
    exit;
}
require_finance_access();

// Get finance statistics
$total_fees = $conn->query("SELECT SUM(amount) as total FROM fees")->fetch_assoc()['total'] ?? 0;
$total_payments = $conn->query("SELECT SUM(amount) as total FROM payments")->fetch_assoc()['total'] ?? 0;
$total_expenses = $conn->query("SELECT SUM(amount) as total FROM expenses")->fetch_assoc()['total'] ?? 0;
$total_budgets = $conn->query("SELECT COUNT(*) as cnt FROM term_budgets")->fetch_assoc()['cnt'] ?? 0;

// Count students with outstanding fees (those with payments less than fees assigned)
$pending_payments_result = $conn->query("
    SELECT COUNT(DISTINCT s.id) as cnt 
    FROM students s 
    WHERE s.status = 'active' 
    AND s.id NOT IN (SELECT DISTINCT student_id FROM payments WHERE amount > 0)
");
$pending_payments = $pending_payments_result ? ($pending_payments_result->fetch_assoc()['cnt'] ?? 0) : 0;

// Calculate balance
$balance = $total_payments - $total_expenses;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance & Billing - School Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    </head>
<body class="bg-gray-50">
    <?php include '../../includes/sidebar.php'; ?>
        
    <main class="ml-72 p-8 min-h-screen">
            <!-- Header -->
            <div class="mb-6">
                <nav class="text-sm text-gray-600 mb-2">
                    <a href="../../index.php" class="text-blue-600 hover:underline">Home</a> / 
                    <span>Finance & Billing</span>
                </nav>
                <h1 class="text-3xl font-bold text-gray-900">Finance & Billing Module</h1>
                <p class="text-gray-600 mt-1">Manage fees, payments, invoices, expenses, and budgets</p>
            </div>

            <!-- Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Total Fees</p>
                            <p class="text-2xl font-bold text-gray-900">GH₵ <?= number_format($total_fees, 2) ?></p>
                        </div>
                        <i class="fas fa-file-invoice-dollar text-blue-500 text-2xl opacity-50"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Payments Received</p>
                            <p class="text-2xl font-bold text-gray-900">GH₵ <?= number_format($total_payments, 2) ?></p>
                        </div>
                        <i class="fas fa-credit-card text-green-500 text-2xl opacity-50"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Total Expenses</p>
                            <p class="text-2xl font-bold text-gray-900">GH₵ <?= number_format($total_expenses, 2) ?></p>
                        </div>
                        <i class="fas fa-receipt text-red-500 text-2xl opacity-50"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Balance</p>
                            <p class="text-2xl font-bold text-gray-900">GH₵ <?= number_format($balance, 2) ?></p>
                        </div>
                        <i class="fas fa-wallet text-purple-500 text-2xl opacity-50"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6 border-l-4 border-orange-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Pending Payments</p>
                            <p class="text-2xl font-bold text-gray-900"><?= $pending_payments ?></p>
                        </div>
                        <i class="fas fa-hourglass-half text-orange-500 text-2xl opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- Module Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-tags text-blue-500 text-2xl mr-3"></i>
                        <h3 class="text-xl font-bold">Fees Management</h3>
                    </div>
                    <p class="text-gray-600 mb-4">Configure and manage student fees by category and class</p>
                    <a href="fees/view_fees.php" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Go to Fees</a>
                </div>

                <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-credit-card text-green-500 text-2xl mr-3"></i>
                        <h3 class="text-xl font-bold">Payments</h3>
                    </div>
                    <p class="text-gray-600 mb-4">Record and track student payment transactions</p>
                    <a href="payments/view_payments.php" class="inline-block bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Go to Payments</a>
                </div>

                <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-receipt text-red-500 text-2xl mr-3"></i>
                        <h3 class="text-xl font-bold">Expenses</h3>
                    </div>
                    <p class="text-gray-600 mb-4">Track school expenses and expenditure categories</p>
                    <a href="expenses/view_expenses.php" class="inline-block bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Go to Expenses</a>
                </div>

                <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-sack-dollar text-purple-500 text-2xl mr-3"></i>
                        <h3 class="text-xl font-bold">Budgets</h3>
                    </div>
                    <p class="text-gray-600 mb-4">Create and manage term and annual budgets</p>
                    <a href="budgets/budgets.php" class="inline-block bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">Go to Budgets</a>
                </div>

                <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-file-invoice text-indigo-500 text-2xl mr-3"></i>
                        <h3 class="text-xl font-bold">Invoices</h3>
                    </div>
                    <p class="text-gray-600 mb-4">Generate and manage term bills and invoices</p>
                    <a href="invoices/term_invoice.php" class="inline-block bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Go to Invoices</a>
                </div>

                <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-chart-line text-pink-500 text-2xl mr-3"></i>
                        <h3 class="text-xl font-bold">Reports</h3>
                    </div>
                    <p class="text-gray-600 mb-4">View financial reports and analytics</p>
                    <a href="reports/report.php" class="inline-block bg-pink-600 text-white px-4 py-2 rounded hover:bg-pink-700">Go to Reports</a>
                </div>
            </div>
    </main>
</body>
</html>
