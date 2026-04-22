<?php
include '../../../includes/auth_functions.php';
require_finance_access();
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';
$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');

// Filters: semester + academic year
$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);

$selected_term = isset($_GET['semester']) ? trim($_GET['semester']) : $current_term;
$selected_year = isset($_GET['year']) ? trim($_GET['year']) : $current_year;
$available_terms = getAvailableSemesters($conn);

// Academic year options from payments + ensure current
$year_options = [];
$yrs = $conn->query("SELECT DISTINCT academic_year FROM payments WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs) {
    while ($yr = $yrs->fetch_assoc()) {
        if (!empty($yr['academic_year'])) { $year_options[] = $yr['academic_year']; }
    }
    $yrs->close();
}
if (!in_array($current_year, $year_options, true)) { array_unshift($year_options, $current_year); }

// Build query
$where = [];
$params = [];
$types = '';
if ($selected_term !== '') { $where[] = 'p.semester = ?'; $params[] = $selected_term; $types .= 's'; }
if ($selected_year !== '') { $where[] = 'p.academic_year = ?'; $params[] = $selected_year; $types .= 's'; }
$where_sql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

$sql = "SELECT p.id, s.first_name, s.last_name, s.class, p.amount, p.payment_date, p.receipt_no, p.description, p.semester, p.academic_year, p.payment_type, f.name as fee_name
        FROM payments p
        LEFT JOIN students s ON p.student_id = s.id
        LEFT JOIN fees f ON p.fee_id = f.id" .
        $where_sql .
        " ORDER BY p.payment_date DESC, p.id DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($types) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Summary stats
$total_payments_count = 0;
$total_amount_collected = 0;
$payments_list = [];
while($row = $result->fetch_assoc()) {
    $payments_list[] = $row;
    $total_payments_count++;
    $total_amount_collected += $row['amount'];
}

// Fetch summary by fee category
$category_summary = [];
$add_where = empty($where) ? ' WHERE ' : ($where_sql . ' AND ');

// Student payment categories
$where_payments = str_replace('p.', 'payments.', $where_sql);
$student_summary_sql = "SELECT 
                    f.name AS category,
                    'student' as type,
                    SUM(pa.amount) as total
                FROM payment_allocations pa
                JOIN student_fees sf ON pa.student_fee_id = sf.id
                JOIN fees f ON sf.fee_id = f.id
                JOIN payments ON pa.payment_id = payments.id " .
                (empty($where_payments) ? ' WHERE ' : $where_payments . ' AND ') . "payments.payment_type = 'student'
                GROUP BY f.id, f.name";

// General payments
$general_with_fee_sql = "SELECT 
                    f.name AS category,
                    'general_with_fee' as type,
                    SUM(p.amount) as total
                FROM payments p
                JOIN fees f ON p.fee_id = f.id " .
                $add_where . "p.payment_type = 'general'
                GROUP BY f.id, f.name";

$general_no_fee_sql = "SELECT 
                    'Unallocated General' AS category,
                    'general_no_fee' as type,
                    SUM(p.amount) as total
                FROM payments p " .
                $add_where . "p.payment_type = 'general' AND p.fee_id IS NULL";

$summary_sql = "($student_summary_sql) UNION ALL ($general_with_fee_sql) UNION ALL ($general_no_fee_sql) ORDER BY total DESC";

if (!empty($params)) {
    $union_params = array_merge($params, $params, $params);
    $union_types = $types . $types . $types;
    $sum_stmt = $conn->prepare($summary_sql);
    if ($union_types) { $sum_stmt->bind_param($union_types, ...$union_params); }
    $sum_stmt->execute();
    $sum_result = $sum_stmt->get_result();
} else {
    $sum_result = $conn->query($summary_sql);
}

while ($row = $sum_result->fetch_assoc()) {
    if ($row['total'] > 0) $category_summary[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Ledger | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .ledger-row:hover { background-color: rgba(248, 250, 252, 0.8); }
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
                <div class="flex items-center gap-2 text-emerald-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[0.125rem] bg-emerald-600"></span>
                    Revenue Stream
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Payment <span class="text-emerald-600">Ledger</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Comprehensive historical record of all institutional revenue inflows.</p>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="window.print()" class="bg-white text-slate-600 border border-slate-200 font-black text-[0.625rem] uppercase tracking-widest px-6 py-4 rounded-2xl hover:bg-slate-50 transition-all leading-none">
                    <i class="fas fa-print mr-2"></i> Print Ledger
                </button>
                <a href="record_payment_form.php" class="bg-emerald-600 text-white font-black text-[0.625rem] uppercase tracking-widest px-6 py-4 rounded-2xl shadow-lg shadow-emerald-600/20 hover:bg-emerald-700 transition-all leading-none">
                    <i class="fas fa-plus mr-2"></i> Record Payment
                </a>
            </div>
        </header>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10 no-print">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-5">
                    <i class="fas fa-receipt text-5xl"></i>
                </div>
                <p class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-1">Transaction Count</p>
                <h4 class="text-3xl font-black text-slate-900 leading-none mb-1"><?= $total_payments_count ?></h4>
                <p class="text-[0.5625rem] font-bold text-slate-400 tracking-tight">Across selected scope</p>
            </div>
            <div class="bg-emerald-600 p-6 rounded-3xl shadow-lg shadow-emerald-500/20 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-10">
                    <i class="fas fa-money-bill-transfer text-5xl"></i>
                </div>
                <p class="text-[0.625rem] font-black text-emerald-100 uppercase tracking-widest mb-1 text-opacity-80">Aggregate Inflow</p>
                <h4 class="text-3xl font-black leading-none mb-1">GHS <?= number_format($total_amount_collected, 2) ?></h4>
                <p class="text-[0.5625rem] font-bold text-emerald-100 text-opacity-60 tracking-tight">Gross Liquid Revenue</p>
            </div>
            <div class="bg-slate-900 p-6 rounded-3xl shadow-xl shadow-slate-900/10 text-white relative overflow-hidden md:col-span-2">
                 <div class="absolute top-0 right-0 p-6 opacity-10">
                    <i class="fas fa-chart-pie text-6xl text-slate-400"></i>
                </div>
                <p class="text-[0.625rem] font-black text-slate-500 uppercase tracking-widest mb-4">Revenue Distribution (Top 3)</p>
                <div class="flex gap-8">
                    <?php 
                    $ct_limit = 0;
                    foreach($category_summary as $cat): 
                        if ($ct_limit++ >= 3) break;
                        $p_rate = ($cat['total'] / ($total_amount_collected ?: 1)) * 100;
                    ?>
                    <div>
                        <h5 class="text-xs font-black text-slate-300 uppercase tracking-tighter mb-1 truncate w-24" title="<?= htmlspecialchars($cat['category']) ?>"><?= htmlspecialchars($cat['category']) ?></h5>
                        <p class="text-lg font-black text-emerald-400 leading-none mb-1">GHS <?= number_format($cat['total'], 0) ?></p>
                        <div class="w-16 h-1 bg-slate-800 rounded-full">
                            <div class="h-full bg-emerald-500" style="width: <?= $p_rate ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-sm mb-10 no-print">
            <form method="GET" class="flex flex-wrap items-end gap-6" id="filterForm">
                <div class="w-64">
                    <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i class="fas fa-calendar-alt text-emerald-500"></i> Semester Context
                    </label>
                    <select name="semester" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-bold text-slate-700 appearance-none transition-all" onchange="this.form.submit()">
                        <option value="">All Semesters</option>
                        <?php foreach ($available_terms as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= ($selected_term === $t) ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="w-64">
                    <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                         <i class="fas fa-graduation-cap text-emerald-500"></i> Academic Period
                    </label>
                    <select name="year" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-bold text-slate-700 appearance-none transition-all" onchange="this.form.submit()">
                        <option value="">Global History</option>
                        <?php foreach ($year_options as $yr): ?>
                            <option value="<?= htmlspecialchars($yr) ?>" <?= ($selected_year === $yr) ? 'selected' : '' ?>><?= htmlspecialchars(formatAcademicYearDisplay($conn, $yr)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i class="fas fa-search text-emerald-500"></i> Instant Search
                    </label>
                    <div class="relative">
                        <input type="text" id="searchInput" placeholder="Search by student, receipt, or channel..." 
                               class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-bold text-slate-700 transition-all pl-12">
                        <i class="fas fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    </div>
                </div>
            </form>
        </div>

        <!-- Print Exclusive Header -->
        <div class="hidden print:block text-center mb-10">
            <h2 class="text-2xl font-black text-slate-900 uppercase tracking-tighter"><?= htmlspecialchars($school_name) ?></h2>
            <p class="text-sm font-bold text-slate-500 uppercase tracking-widest">Revenue Audit Report</p>
            <div class="flex justify-center gap-6 mt-4 text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">
                <span>Semester: <?= htmlspecialchars($selected_term ?: 'All') ?></span>
                <span>Year: <?= htmlspecialchars($selected_year ?: 'All') ?></span>
                <span>Audit Date: <?= date('M j, Y H:i') ?></span>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden overflow-x-auto">
            <table class="w-full min-w-[62.5rem] border-collapse" id="paymentLedger">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100">
                        <th class="px-8 py-6 text-left text-[0.625rem] font-black text-slate-400 uppercase tracking-widest w-16">Ref</th>
                        <th class="px-8 py-6 text-left text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Classification</th>
                        <th class="px-8 py-6 text-left text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Entity / Channel</th>
                        <th class="px-8 py-6 text-left text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Value (GHS)</th>
                        <th class="px-8 py-6 text-left text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Transaction Date</th>
                        <th class="px-8 py-6 text-left text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Receipt #</th>
                        <th class="px-8 py-6 text-right text-[0.625rem] font-black text-slate-400 uppercase tracking-widest no-print">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-sm">
                    <?php if (!empty($payments_list)): ?>
                        <?php foreach($payments_list as $row): ?>
                            <tr class="ledger-row transition-colors group">
                                <td class="px-8 py-6">
                                    <span class="text-[0.625rem] font-black text-slate-400 bg-slate-50 px-2 py-1 rounded-lg border border-slate-100">#<?= $row['id'] ?></span>
                                </td>
                                <td class="px-8 py-6">
                                    <?php if ($row['payment_type'] === 'general'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-amber-100 bg-amber-50 text-amber-600 text-[0.5625rem] font-black uppercase tracking-widest">
                                            General
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-600 text-[0.5625rem] font-black uppercase tracking-widest">
                                            Student Fee
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="flex flex-col">
                                        <?php if ($row['payment_type'] === 'general'): ?>
                                            <span class="font-black text-slate-700 italic"><?= !empty($row['fee_name']) ? htmlspecialchars($row['fee_name']) : 'Standalone Payment' ?></span>
                                            <span class="text-[0.625rem] text-slate-400 font-bold uppercase tracking-tight">Direct Deposit</span>
                                        <?php else: ?>
                                            <span class="font-black text-slate-900 tracking-tight"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></span>
                                            <span class="text-[0.625rem] text-indigo-500 font-bold uppercase tracking-wider"><?= htmlspecialchars($row['class']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-8 py-6">
                                    <span class="text-base font-black text-slate-900 tracking-tighter"><?= number_format($row['amount'], 2) ?></span>
                                </td>
                                <td class="px-8 py-6 font-bold text-slate-600">
                                    <div class="flex flex-col">
                                        <span><?= date('M j, Y', strtotime($row['payment_date'])) ?></span>
                                        <span class="text-[0.5625rem] text-slate-400 font-black tracking-widest uppercase">TS: <?= date('H:i', strtotime($row['payment_date'])) ?></span>
                                    </div>
                                </td>
                                <td class="px-8 py-6 font-black text-indigo-600 tracking-widest uppercase text-xs">
                                     <?= htmlspecialchars($row['receipt_no']) ?>
                                </td>
                                <td class="px-8 py-6 text-right no-print">
                                    <a href="receipt.php?payment_id=<?= $row['id'] ?>" target="_blank" class="inline-flex items-center justify-center w-10 h-10 rounded-xl border border-slate-100 bg-white text-slate-400 hover:bg-slate-900 hover:text-white transition-all shadow-sm">
                                        <i class="fas fa-receipt text-xs"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-8 py-20 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-slate-200 text-3xl mb-4">
                                        <i class="fas fa-money-bill-transfer"></i>
                                    </div>
                                    <p class="text-slate-400 font-black uppercase tracking-[0.2em] text-[0.625rem]">No collection data found for this period</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

         <!-- Footer Audit -->
        <footer class="mt-20 py-10 border-t border-slate-200 flex justify-between items-center text-[0.625rem] font-black text-slate-300 uppercase tracking-[0.5em] no-print">
            <span>Fiscal Management System &middot; Consolidated Ledger &middot; v9.5.0</span>
            <div class="flex gap-6">
                <a href="../dashboard.php" class="hover:text-emerald-600">Overview</a>
                <a href="../expenses/view_expenses.php" class="hover:text-emerald-600">Expenditure</a>
            </div>
        </footer>
    </main>

    <script>
        // Efficient search filter
        const searchInput = document.getElementById('searchInput');
        const ledgerRows = document.querySelectorAll('#paymentLedger tbody tr');
        
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase().trim();
            ledgerRows.forEach(row => {
                if (row.cells.length < 2) return; // Skip empty state row
                const student = row.cells[2].textContent.toLowerCase();
                const receipt = row.cells[5].textContent.toLowerCase();
                const amount = row.cells[3].textContent.toLowerCase();
                
                if (student.includes(term) || receipt.includes(term) || amount.includes(term)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
