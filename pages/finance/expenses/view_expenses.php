<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}

// Get current semester and academic year
$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);
$available_terms = getAvailableSemesters($conn);

// Filters: semester, academic year, and category
$selected_term = isset($_GET['semester']) ? trim($_GET['semester']) : $current_term;
$selected_year = isset($_GET['year']) ? trim($_GET['year']) : $current_year;
$selected_category = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Academic year options from expenses + ensure current
$year_options = [];
$yrs = $conn->query("SELECT DISTINCT academic_year FROM expenses WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs) {
    while ($yr = $yrs->fetch_assoc()) {
        if (!empty($yr['academic_year'])) { $year_options[] = $yr['academic_year']; }
    }
    $yrs->close();
}
if (!in_array($current_year, $year_options, true)) { array_unshift($year_options, $current_year); }

// Get all expense categories for filter
$categories_result = $conn->query("SELECT id, name FROM expense_categories ORDER BY name");
$categories_list = [];
while ($cat = $categories_result->fetch_assoc()) {
    $categories_list[] = $cat;
}

// Build query with filters
$where = [];
$params = [];
$types = '';
if ($selected_term !== '') { 
    $where[] = 'e.semester = ?'; 
    $params[] = $selected_term; 
    $types .= 's'; 
}
if ($selected_year !== '') { 
    $where[] = 'e.academic_year = ?'; 
    $params[] = $selected_year; 
    $types .= 's'; 
}
if ($selected_category > 0) { 
    $where[] = 'e.category_id = ?'; 
    $params[] = $selected_category; 
    $types .= 'i'; 
}
$where_sql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

// Fetch filtered expenses
$sql = "SELECT e.*, ec.name AS category_name 
        FROM expenses e 
        LEFT JOIN expense_categories ec ON e.category_id = ec.id" .
        $where_sql .
        " ORDER BY e.expense_date DESC, e.id DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($types) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Fetch summary by category
$summary_sql = "SELECT ec.name AS category, SUM(e.amount) as total 
                FROM expenses e 
                LEFT JOIN expense_categories ec ON e.category_id = ec.id" .
                $where_sql .
                " GROUP BY ec.id, ec.name 
                ORDER BY ec.name";

if (!empty($params)) {
    $sum_stmt = $conn->prepare($summary_sql);
    if ($types) { $sum_stmt->bind_param($types, ...$params); }
    $sum_stmt->execute();
    $sum_result = $sum_stmt->get_result();
} else {
    $sum_result = $conn->query($summary_sql);
}

$summary_data = [];
$total_expenses_amt = 0;
while ($row = $sum_result->fetch_assoc()) {
    if ($row['total'] > 0) {
        $summary_data[] = $row;
        $total_expenses_amt += $row['total'];
    }
}
$total_count = $result->num_rows;
$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenditure Ledger | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .expense-row:hover { background-color: rgba(248, 250, 252, 0.8); }
        @media print {
            .no-print { display: none !important; }
            .ml-72 { margin-left: 0 !important; }
            .p-10 { padding: 1.5rem !important; }
        }
    </style>
</head>
<body class="bg-[#F8FAFC] text-slate-900">
    <div class="no-print"><?php include '../../../includes/sidebar.php'; ?></div>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 p-10 min-h-screen">
        <!-- Header Section -->
        <header class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6 no-print">
            <div>
                <div class="flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[0.125rem] bg-rose-600"></span>
                    Expenditure Oversight
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Institutional <span class="text-rose-600">Expenses</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Detailed tracking and categorization of school spending protocols.</p>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="window.print()" class="bg-white text-slate-600 border border-slate-200 font-black text-[0.625rem] uppercase tracking-widest px-6 py-4 rounded-2xl hover:bg-slate-50 transition-all leading-none">
                    <i class="fas fa-print mr-2"></i> Print Audit
                </button>
                <a href="add_expense_form.php" class="bg-rose-600 text-white font-black text-[0.625rem] uppercase tracking-widest px-6 py-4 rounded-2xl shadow-lg shadow-rose-600/20 hover:bg-rose-700 transition-all leading-none">
                    <i class="fas fa-plus mr-2"></i> Record Expense
                </a>
            </div>
        </header>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 mb-10 no-print">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <p class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-1">Transaction Pool</p>
                <h4 class="text-2xl font-black text-slate-900 leading-none mb-2"><?= $total_count ?></h4>
                <p class="text-[0.5625rem] font-bold text-slate-400">Total Entries</p>
            </div>
            <div class="bg-rose-600 p-6 rounded-3xl shadow-lg shadow-rose-500/20 text-white lg:col-span-1 xl:col-span-2">
                <p class="text-[0.625rem] font-black text-rose-100 uppercase tracking-widest mb-1 text-opacity-80">Aggregate Spending</p>
                <h4 class="text-3xl font-black leading-none mb-2">GHS <?= number_format($total_expenses_amt, 2) ?></h4>
                <p class="text-[0.5625rem] font-bold text-rose-100 text-opacity-60">Total Cash Outflow</p>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-center xl:col-span-3">
                 <p class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-3">Spending Breakdown by Category</p>
                 <div class="flex gap-6 overflow-x-auto pb-2 custom-scrollbar">
                    <?php foreach(array_slice($summary_data, 0, 4) as $cat): 
                        $rate = ($cat['total'] / ($total_expenses_amt ?: 1)) * 100;
                    ?>
                    <div class="flex-shrink-0">
                        <div class="text-[0.5rem] font-black text-slate-400 uppercase tracking-tighter mb-1 truncate w-20"><?= htmlspecialchars($cat['category']) ?></div>
                        <div class="text-sm font-black text-slate-900 mb-1 leading-none"><?= number_format($rate, 1) ?>%</div>
                        <div class="w-16 h-1 bg-slate-50 rounded-full overflow-hidden">
                            <div class="h-full bg-rose-500" style="width: <?= $rate ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                 </div>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-sm mb-10 no-print">
            <form method="GET" class="flex flex-wrap items-end gap-6" id="filterForm">
                <div class="w-56">
                    <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i class="fas fa-calendar-alt text-rose-500"></i> Semester
                    </label>
                    <select name="semester" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-bold text-slate-700 appearance-none transition-all" onchange="this.form.submit()">
                        <option value="">All Semesters</option>
                         <?php foreach ($available_terms as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= ($selected_term === $t) ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="w-56">
                    <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i class="fas fa-graduation-cap text-rose-500"></i> Academic Period
                    </label>
                    <select name="year" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-bold text-slate-700 appearance-none transition-all" onchange="this.form.submit()">
                        <option value="">All Years</option>
                         <?php foreach ($year_options as $yr): ?>
                            <option value="<?= htmlspecialchars($yr) ?>" <?= ($selected_year === $yr) ? 'selected' : '' ?>><?= htmlspecialchars(formatAcademicYearDisplay($conn, $yr)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="w-56">
                    <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i class="fas fa-folder text-rose-500"></i> Category
                    </label>
                    <select name="category" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-bold text-slate-700 appearance-none transition-all" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories_list as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($selected_category == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1 min-w-[12.5rem]">
                    <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i class="fas fa-search text-rose-500"></i> Search Entries
                    </label>
                    <div class="relative">
                        <input type="text" id="searchInput" placeholder="Search description or amount..." 
                               class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-bold text-slate-700 transition-all pl-12">
                        <i class="fas fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    </div>
                </div>
            </form>
        </div>

        <!-- Print Exclusive Header -->
        <div class="hidden print:block text-center mb-10">
            <h2 class="text-2xl font-black text-slate-900 uppercase tracking-tighter"><?= htmlspecialchars($school_name) ?></h2>
            <p class="text-sm font-bold text-slate-500 uppercase tracking-widest">Expenditure Audit Report</p>
            <div class="flex justify-center gap-6 mt-4 text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">
                <span>Semester: <?= htmlspecialchars($selected_term ?: 'All') ?></span>
                <span>Year: <?= htmlspecialchars($selected_year ?: 'All') ?></span>
                <span>Audit Date: <?= date('M j, Y H:i') ?></span>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden overflow-x-auto">
            <table class="w-full min-w-[62.5rem] border-collapse" id="expenseLedger">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100">
                        <th class="px-8 py-6 text-left text-[0.625rem] font-black text-slate-400 uppercase tracking-widest w-20">ID</th>
                        <th class="px-8 py-6 text-left text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Category</th>
                        <th class="px-8 py-6 text-left text-[0.625rem] font-black text-slate-400 uppercase tracking-widest text-rose-600">Value (GHS)</th>
                        <th class="px-8 py-6 text-left text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Date / Period</th>
                        <th class="px-8 py-6 text-left text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Description</th>
                        <th class="px-8 py-6 text-right text-[0.625rem] font-black text-slate-400 uppercase tracking-widest no-print">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-sm">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr class="expense-row transition-colors group">
                                <td class="px-8 py-6">
                                    <span class="text-[0.625rem] font-black text-slate-400 bg-slate-50 px-2 py-1 rounded-lg border border-slate-100">#<?= $row['id'] ?></span>
                                </td>
                                <td class="px-8 py-6">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-100 bg-slate-50 text-slate-600 text-[0.625rem] font-black uppercase tracking-widest">
                                        <i class="fas fa-folder-open text-rose-400"></i> <?= htmlspecialchars($row['category_name'] ?? 'General') ?>
                                    </span>
                                </td>
                                <td class="px-8 py-6">
                                    <span class="text-base font-black text-rose-600 tracking-tighter"><?= number_format($row['amount'], 2) ?></span>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-700"><?= date('M j, Y', strtotime($row['expense_date'])) ?></span>
                                        <span class="text-[0.5625rem] text-slate-400 font-black tracking-widest uppercase"><?= htmlspecialchars($row['semester'] ?? '') ?> | <?= htmlspecialchars(formatAcademicYearDisplay($conn, $row['academic_year'])) ?></span>
                                    </div>
                                </td>
                                <td class="px-8 py-6 text-slate-600 font-medium max-w-md truncate" title="<?= htmlspecialchars($row['description']) ?>">
                                    <?= htmlspecialchars($row['description']) ?>
                                </td>
                                <td class="px-8 py-6 text-right no-print">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="edit_expense.php?id=<?= $row['id'] ?>" class="w-9 h-9 bg-slate-50 text-slate-400 border border-slate-100 rounded-xl flex items-center justify-center hover:bg-slate-900 hover:text-white transition-all shadow-sm">
                                            <i class="fas fa-pen text-xs"></i>
                                        </a>
                                        <a href="delete_expense.php?id=<?= $row['id'] ?>" onclick="return confirm('DANGER: Permanently excise this expense entry from history?');" class="w-9 h-9 bg-slate-50 text-slate-300 border border-slate-50 rounded-xl flex items-center justify-center hover:bg-rose-600 hover:text-white transition-all shadow-sm">
                                            <i class="fas fa-trash text-xs"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-8 py-20 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-slate-200 text-3xl mb-4">
                                        <i class="fas fa-receipt"></i>
                                    </div>
                                    <p class="text-slate-400 font-black uppercase tracking-[0.2em] text-[0.625rem]">No recorded expenditure found for this scope</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer Audit -->
        <footer class="mt-20 py-10 border-t border-slate-200 flex justify-between items-center text-[0.625rem] font-black text-slate-300 uppercase tracking-[0.5em] no-print">
            <span>Expenditure Management &middot; Fiscal Transparency &middot; v9.5.0</span>
            <div class="flex gap-6">
                <a href="../dashboard.php" class="hover:text-rose-600">Finance Hub</a>
                <a href="../payments/view_payments.php" class="hover:text-rose-600">Revenue Ledger</a>
            </div>
        </footer>
    </main>

    <script>
        // Search filter
        const searchInput = document.getElementById('searchInput');
        const expenseRows = document.querySelectorAll('#expenseLedger tbody tr');
        
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase().trim();
            expenseRows.forEach(row => {
                if (row.cells.length < 2) return;
                const category = row.cells[1].textContent.toLowerCase();
                const amount = row.cells[2].textContent.toLowerCase();
                const desc = row.cells[4].textContent.toLowerCase();
                
                if (category.includes(term) || amount.includes(term) || desc.includes(term)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
