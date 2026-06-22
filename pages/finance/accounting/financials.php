<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

// Fetch balances by account type
$balances = [
    'asset' => [], 'liability' => [], 'equity' => [], 'revenue' => [], 'expense' => []
];
$totals = [
    'asset' => 0, 'liability' => 0, 'equity' => 0, 'revenue' => 0, 'expense' => 0
];

$query = $conn->query("
    SELECT a.name, a.type, SUM(l.debit) as dr, SUM(l.credit) as cr
    FROM accounts a
    JOIN journal_lines l ON a.id = l.account_id
    GROUP BY a.id
");

while ($row = $query->fetch_assoc()) {
    $dr = (float)$row['dr'];
    $cr = (float)$row['cr'];
    
    // Normal balances:
    // Assets & Expenses = DR
    // Liab, Equity, Revenue = CR
    $type = $row['type'];
    if ($type == 'asset' || $type == 'expense') {
        $bal = $dr - $cr;
    } else {
        $bal = $cr - $dr;
    }
    
    if ($bal != 0) {
        $balances[$type][] = ['name' => $row['name'], 'balance' => $bal];
        $totals[$type] += $bal;
    }
}

// Calculate Net Income (Revenue - Expenses)
$net_income = $totals['revenue'] - $totals['expense'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Statements | SALBA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-[#F8FAFC] text-slate-900">
    <?php include '../../../includes/sidebar.php'; ?>
    <main class="admin-main-content lg:ml-72 min-h-screen pb-12">
        <div class="bg-white border-b border-gray-100 px-8 py-6 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight"><i class="fas fa-file-invoice-dollar text-indigo-600"></i> Financial Statements</h1>
                    <p class="text-slate-500 mt-2 font-medium">Income Statement and Balance Sheet derived from the Double-Entry Ledger.</p>
                </div>
                <a href="index.php" class="px-5 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition-all"><i class="fas fa-arrow-left"></i> Back to Ledger</a>
            </div>
        </div>

        <div class="px-8 grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Income Statement -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                <div class="bg-indigo-600 p-6 text-white text-center">
                    <h2 class="text-2xl font-black uppercase tracking-widest">Income Statement</h2>
                    <p class="text-indigo-200 text-sm font-bold mt-1">For the period ended <?= date('F j, Y') ?></p>
                </div>
                <div class="p-8">
                    <!-- Revenue -->
                    <h3 class="font-black text-slate-900 text-lg border-b border-slate-200 pb-2 mb-4">Revenues</h3>
                    <?php foreach($balances['revenue'] as $item): ?>
                    <div class="flex justify-between items-center py-2 text-slate-700 font-medium">
                        <span><?= htmlspecialchars($item['name']) ?></span>
                        <span><?= number_format($item['balance'], 2) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="flex justify-between items-center py-2 font-black text-slate-900 mt-2">
                        <span>Total Revenues</span>
                        <span><?= number_format($totals['revenue'], 2) ?></span>
                    </div>

                    <!-- Expenses -->
                    <h3 class="font-black text-slate-900 text-lg border-b border-slate-200 pb-2 mb-4 mt-8">Expenses</h3>
                    <?php foreach($balances['expense'] as $item): ?>
                    <div class="flex justify-between items-center py-2 text-slate-700 font-medium">
                        <span><?= htmlspecialchars($item['name']) ?></span>
                        <span><?= number_format($item['balance'], 2) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="flex justify-between items-center py-2 font-black text-slate-900 mt-2 border-b border-slate-200 pb-4">
                        <span>Total Expenses</span>
                        <span><?= number_format($totals['expense'], 2) ?></span>
                    </div>

                    <!-- Net Income -->
                    <div class="flex justify-between items-center py-4 font-black text-2xl <?= $net_income >= 0 ? 'text-emerald-600' : 'text-rose-600' ?> mt-4">
                        <span>Net Income (Loss)</span>
                        <span class="underline decoration-double"><?= number_format($net_income, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Balance Sheet -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                <div class="bg-slate-800 p-6 text-white text-center">
                    <h2 class="text-2xl font-black uppercase tracking-widest">Balance Sheet</h2>
                    <p class="text-slate-400 text-sm font-bold mt-1">As of <?= date('F j, Y') ?></p>
                </div>
                <div class="p-8">
                    <!-- Assets -->
                    <h3 class="font-black text-blue-800 text-lg border-b border-slate-200 pb-2 mb-4">Assets</h3>
                    <?php foreach($balances['asset'] as $item): ?>
                    <div class="flex justify-between items-center py-2 text-slate-700 font-medium">
                        <span><?= htmlspecialchars($item['name']) ?></span>
                        <span><?= number_format($item['balance'], 2) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="flex justify-between items-center py-2 font-black text-blue-900 mt-2 mb-8">
                        <span>Total Assets</span>
                        <span class="underline decoration-double"><?= number_format($totals['asset'], 2) ?></span>
                    </div>

                    <!-- Liabilities -->
                    <h3 class="font-black text-rose-800 text-lg border-b border-slate-200 pb-2 mb-4">Liabilities</h3>
                    <?php foreach($balances['liability'] as $item): ?>
                    <div class="flex justify-between items-center py-2 text-slate-700 font-medium">
                        <span><?= htmlspecialchars($item['name']) ?></span>
                        <span><?= number_format($item['balance'], 2) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="flex justify-between items-center py-2 font-black text-rose-900 mt-2 mb-8">
                        <span>Total Liabilities</span>
                        <span><?= number_format($totals['liability'], 2) ?></span>
                    </div>

                    <!-- Equity -->
                    <h3 class="font-black text-purple-800 text-lg border-b border-slate-200 pb-2 mb-4">Equity</h3>
                    <?php foreach($balances['equity'] as $item): ?>
                    <div class="flex justify-between items-center py-2 text-slate-700 font-medium">
                        <span><?= htmlspecialchars($item['name']) ?></span>
                        <span><?= number_format($item['balance'], 2) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="flex justify-between items-center py-2 text-indigo-700 font-black italic">
                        <span>Net Income (Current Period)</span>
                        <span><?= number_format($net_income, 2) ?></span>
                    </div>
                    <div class="flex justify-between items-center py-2 font-black text-purple-900 mt-2">
                        <span>Total Equity</span>
                        <span><?= number_format($totals['equity'] + $net_income, 2) ?></span>
                    </div>

                    <!-- Total Liab + Equity -->
                    <div class="flex justify-between items-center py-4 font-black text-xl text-slate-900 mt-8 border-t border-slate-200">
                        <span>Total Liabilities & Equity</span>
                        <span class="underline decoration-double"><?= number_format($totals['liability'] + $totals['equity'] + $net_income, 2) ?></span>
                    </div>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
