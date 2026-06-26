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
<body class="bg-slate-50 text-slate-900 min-h-screen">
    <div class="no-print"><?php include '../../../includes/sidebar.php'; ?></div>

    <main class="admin-main-content lg:ml-72 min-h-screen pb-12">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6 no-print">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <span class="text-blue-600">Payment Ledger</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-file-invoice-dollar text-emerald-600"></i> Payment Ledger
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Comprehensive historical record of all institutional revenue inflows.</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="download_payments_pdf.php?semester=<?= urlencode($selected_term) ?>&year=<?= urlencode($selected_year) ?>" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all flex items-center gap-2">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                    <a href="record_payment_form.php" class="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 shadow-sm transition-all flex items-center gap-2">
                        <i class="fas fa-plus"></i> Record Payment
                    </a>
                </div>
            </div>
        </div>

        <div class="px-6">

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 no-print">
            <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Transaction Count</p>
                    <h4 class="text-2xl font-bold text-slate-900"><?= $total_payments_count ?></h4>
                    <p class="text-[10px] font-medium text-slate-400 mt-1">Across selected scope</p>
                </div>
                <div class="w-12 h-12 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shrink-0 border border-emerald-100">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
            
            <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Aggregate Inflow</p>
                    <h4 class="text-2xl font-bold text-slate-900">GHS <?= number_format($total_amount_collected, 2) ?></h4>
                    <p class="text-[10px] font-medium text-slate-400 mt-1">Gross Liquid Revenue</p>
                </div>
                <div class="w-12 h-12 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shrink-0 border border-indigo-100">
                    <i class="fas fa-money-bill-transfer"></i>
                </div>
            </div>
            
            <div class="bg-slate-900 p-5 rounded-xl shadow-lg border border-slate-800 text-white relative overflow-hidden md:col-span-2">
                 <div class="absolute top-0 right-0 p-5 opacity-10">
                    <i class="fas fa-chart-pie text-6xl text-slate-400"></i>
                </div>
                <div class="relative z-10">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Revenue Distribution (Top 3)</p>
                    <div class="grid grid-cols-3 gap-4">
                        <?php 
                        $ct_limit = 0;
                        foreach($category_summary as $cat): 
                            if ($ct_limit++ >= 3) break;
                            $p_rate = ($cat['total'] / ($total_amount_collected ?: 1)) * 100;
                        ?>
                        <div>
                            <h5 class="text-[10px] font-semibold text-slate-300 uppercase tracking-wider mb-1 truncate" title="<?= htmlspecialchars($cat['category']) ?>"><?= htmlspecialchars($cat['category']) ?></h5>
                            <p class="text-base font-bold text-emerald-400 mb-1">GHS <?= number_format($cat['total'], 0) ?></p>
                            <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                <div class="h-full bg-emerald-500" style="width: <?= $p_rate ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm mb-6 no-print">
            <form method="GET" class="flex flex-wrap items-end gap-4" id="filterForm">
                <div class="w-full md:w-56">
                    <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                        <i class="fas fa-calendar-alt text-emerald-500"></i> Semester Context
                    </label>
                    <select name="semester" class="w-full px-4 py-2 bg-white border border-slate-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm font-medium text-slate-900 appearance-none transition-all" onchange="this.form.submit()">
                        <option value="">All Semesters</option>
                        <?php foreach ($available_terms as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= ($selected_term === $t) ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="w-full md:w-56">
                    <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                         <i class="fas fa-graduation-cap text-emerald-500"></i> Academic Period
                    </label>
                    <select name="year" class="w-full px-4 py-2 bg-white border border-slate-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm font-medium text-slate-900 appearance-none transition-all" onchange="this.form.submit()">
                        <option value="">Global History</option>
                        <?php foreach ($year_options as $yr): ?>
                            <option value="<?= htmlspecialchars($yr) ?>" <?= ($selected_year === $yr) ? 'selected' : '' ?>><?= htmlspecialchars(formatAcademicYearDisplay($conn, $yr)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                        <i class="fas fa-search text-emerald-500"></i> Instant Search
                    </label>
                    <div class="relative">
                        <input type="text" id="searchInput" placeholder="Search by student, receipt, or channel..." 
                               class="w-full px-4 py-2 bg-white border border-slate-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm font-medium text-slate-900 transition-all pl-10">
                        <i class="fas fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
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
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden overflow-x-auto mb-6">
            <table class="w-full text-left text-sm text-slate-600" id="paymentLedger">
                <thead class="bg-slate-50 sticky top-0 border-b border-slate-200 text-xs uppercase font-semibold text-slate-500">
                    <tr>
                        <th class="px-6 py-3 w-16">Ref</th>
                        <th class="px-6 py-3">Classification</th>
                        <th class="px-6 py-3">Entity / Channel</th>
                        <th class="px-6 py-3">Value (GHS)</th>
                        <th class="px-6 py-3">Transaction Date</th>
                        <th class="px-6 py-3">Receipt #</th>
                        <th class="px-6 py-3 text-right no-print">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (!empty($payments_list)): ?>
                        <?php foreach($payments_list as $row): ?>
                            <tr class="ledger-row hover:bg-slate-50 transition-colors group">
                                <td class="px-6 py-4">
                                    <span class="text-[10px] font-semibold text-slate-500 bg-slate-100 px-2 py-1 rounded border border-slate-200">#<?= $row['id'] ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($row['payment_type'] === 'general'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-amber-50 border border-amber-200 text-amber-700 text-[10px] font-semibold uppercase tracking-wider">
                                            General
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-[10px] font-semibold uppercase tracking-wider">
                                            Student Fee
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <?php if ($row['payment_type'] === 'general'): ?>
                                            <span class="font-semibold text-slate-900"><?= !empty($row['fee_name']) ? htmlspecialchars($row['fee_name']) : 'Standalone Payment' ?></span>
                                            <span class="text-[10px] text-slate-500 font-medium uppercase tracking-wider">Direct Deposit</span>
                                        <?php else: ?>
                                            <span class="font-semibold text-slate-900"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></span>
                                            <span class="text-[10px] text-indigo-500 font-medium uppercase tracking-wider"><?= htmlspecialchars($row['class']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm font-bold text-slate-900"><?= number_format($row['amount'], 2) ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-medium text-slate-900"><?= date('M j, Y', strtotime($row['payment_date'])) ?></span>
                                        <span class="text-[10px] text-slate-400 font-medium tracking-wider uppercase">TS: <?= date('H:i', strtotime($row['payment_date'])) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-semibold text-indigo-600 tracking-wider text-xs">
                                     <?= htmlspecialchars($row['receipt_no']) ?>
                                </td>
                                <td class="px-6 py-4 text-right no-print">
                                    <a href="receipt.php?payment_id=<?= $row['id'] ?>" target="_blank" class="inline-flex items-center justify-center w-8 h-8 rounded bg-white border border-slate-300 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
                                        <i class="fas fa-receipt text-xs"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300 text-2xl mb-3 border border-slate-100">
                                        <i class="fas fa-money-bill-transfer"></i>
                                    </div>
                                    <p class="text-xs font-medium uppercase tracking-wider">No collection data found for this period</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
