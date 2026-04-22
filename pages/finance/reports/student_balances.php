<?php 
include '../../../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';
include '../../../includes/student_balance_functions.php';

// Get semester and year
$current_term = getCurrentSemester($conn);
$default_academic_year = getAcademicYear($conn);

$selected_term = $_GET['semester'] ?? $current_term;
$selected_academic_year = $_GET['academic_year'] ?? $default_academic_year;
$display_academic_year = formatAcademicYearDisplay($conn, $selected_academic_year);

// Filters
$class_filter   = $_GET['class']   ?? 'all';
$status_filter  = $_GET['status']  ?? 'active';
$owing_filter   = $_GET['owing']   ?? 'all'; 
$percent_filter = $_GET['percent'] ?? 'all';
$sort_by        = $_GET['sort_by'] ?? 'name';
$order          = $_GET['order']   ?? 'asc';

// Pre-compute arrears
{
    $where = []; $params = []; $types = '';
    if ($status_filter && $status_filter !== 'all') { $where[] = "status = ?"; $params[] = $status_filter; $types .= 's'; }
    if ($class_filter  && $class_filter  !== 'all') { $where[] = "class = ?";  $params[] = $class_filter;  $types .= 's'; }
    $sql = "SELECT id FROM students" . (empty($where) ? '' : (' WHERE ' . implode(' AND ', $where)));
    $stmt = $conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            ensureArrearsAssignment($conn, intval($row['id']), $selected_term, $selected_academic_year);
        }
    }
    $stmt->close();
}

// Data Fetching
$student_balances = getAllStudentBalances($conn, $class_filter, $status_filter, $selected_term, $selected_academic_year);

// Post-fetch: owing filter
if ($owing_filter === 'owing') {
    $student_balances = array_filter($student_balances, fn($s) => $s['net_balance'] > 0);
} elseif ($owing_filter === 'paid_up') {
    $student_balances = array_filter($student_balances, fn($s) => $s['net_balance'] == 0);
}

// Compute paid_percent
foreach ($student_balances as &$s) {
    $tf = (float)($s['total_fees'] ?? 0);
    $tp = (float)($s['total_payments'] ?? 0);
    $s['paid_percent'] = ($tf > 0) ? min(100, ($tp / $tf) * 100) : (($tp > 0) ? 100 : 0);
}
unset($s);

// Percent filter
if ($percent_filter !== 'all') {
    $student_balances = array_filter($student_balances, function($st) use ($percent_filter) {
        $p = $st['paid_percent'];
        if ($percent_filter === 'below50')  return $p < 50;
        if ($percent_filter === 'below75')  return $p < 75;
        if ($percent_filter === 'below100') return $p < 100;
        return true;
    });
}

// Sort
usort($student_balances, function($a, $b) use ($sort_by, $order) {
    if ($sort_by === 'percent') { $vA = $a['paid_percent']; $vB = $b['paid_percent']; }
    elseif ($sort_by === 'class') { $vA = $a['class']; $vB = $b['class']; }
    else { $vA = $a['student_name']; $vB = $b['student_name']; }
    if ($vA == $vB) return 0;
    return ($order === 'asc') ? ($vA < $vB ? -1 : 1) : ($vA > $vB ? -1 : 1);
});

// Stats
$total_students = count($student_balances);
$sum_fees    = array_sum(array_column($student_balances, 'total_fees'));
$sum_paid    = array_sum(array_column($student_balances, 'total_payments'));
$sum_due     = array_sum(array_column($student_balances, 'net_balance'));

$classes_rs = $conn->query("SELECT DISTINCT class FROM students ORDER BY class");
$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$school_address = getSystemSetting($conn, 'school_address', '');

// Build filter query string (for download links)
$filter_qs = http_build_query([
    'semester'      => $selected_term,
    'academic_year' => $selected_academic_year,
    'class'         => $class_filter,
    'status'        => $status_filter,
    'owing'         => $owing_filter,
    'percent'       => $percent_filter,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Balances | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .stat-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .stat-card:hover { transform: translateY(-0.25rem); }
        .dropdown-menu { display: none; position: absolute; right: 0; top: 100%; z-index: 50; min-width: 11.25rem; }
        .dropdown-trigger:focus + .dropdown-menu,
        .dropdown-trigger:focus-within + .dropdown-menu,
        .dropdown-wrapper:hover .dropdown-menu,
        .dropdown-wrapper:focus-within .dropdown-menu { display: block; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .admin-main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="text-slate-900 leading-relaxed">
    <div class="no-print"><?php include '../../../includes/sidebar.php'; ?></div>

    <!-- Print Header (hidden on screen) -->
    <div class="hidden print:block p-8 border-b border-slate-200 mb-6">
        <h2 class="text-xl font-black text-center uppercase tracking-widest"><?= htmlspecialchars($school_name) ?></h2>
        <?php if($school_address): ?><p class="text-center text-sm text-slate-500"><?= htmlspecialchars($school_address) ?></p><?php endif; ?>
        <p class="text-center font-black text-sm mt-2 uppercase tracking-widest">Student Balances Report</p>
        <div class="text-center text-xs text-slate-500 mt-1">
            Semester: <strong><?= htmlspecialchars($selected_term) ?></strong> &middot;
            Year: <strong><?= htmlspecialchars($display_academic_year) ?></strong> &middot;
            Class: <strong><?= $class_filter !== 'all' ? htmlspecialchars($class_filter) : 'All Classes' ?></strong> &middot;
            Status: <strong><?= ucfirst($status_filter) ?></strong> &middot;
            Printed: <strong><?= date('M j, Y') ?></strong>
        </div>
    </div>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">

        <!-- Header -->
        <header class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-emerald-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[0.125rem] bg-emerald-600"></span>
                    Financial Intelligence
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Student <span class="text-emerald-600">Balances</span></h1>
                <p class="text-slate-500 mt-2 font-medium text-sm">Institutional debt exposure and revenue maturity overview.</p>
            </div>
            <div class="no-print flex flex-wrap gap-3">
                <a href="download_student_balances.php?<?= $filter_qs ?>" class="bg-emerald-50 text-emerald-700 font-black text-[0.625rem] uppercase tracking-widest px-5 py-3 rounded-xl hover:bg-emerald-600 hover:text-white transition-all flex items-center gap-2 border border-emerald-100">
                    <i class="fas fa-file-csv"></i> Download CSV
                </a>
                <a href="download_student_balances_pdf.php?<?= $filter_qs ?>" class="bg-rose-50 text-rose-700 font-black text-[0.625rem] uppercase tracking-widest px-5 py-3 rounded-xl hover:bg-rose-600 hover:text-white transition-all flex items-center gap-2 border border-rose-100">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </a>
                <button onclick="window.print()" class="bg-white border border-slate-200 text-slate-600 font-black text-[0.625rem] uppercase tracking-widest px-5 py-3 rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="../payments/record_payment_form.php" class="bg-slate-900 text-white font-black text-[0.625rem] uppercase tracking-widest px-5 py-3 rounded-xl hover:bg-slate-800 transition-all flex items-center gap-2">
                    <i class="fas fa-plus"></i> Record Payment
                </a>
            </div>
        </header>

        <!-- Stats Grid -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-10">
            <div class="stat-card bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100">
                <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600 mb-5"><i class="fas fa-users-viewfinder"></i></div>
                <p class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest mb-1">Students</p>
                <h3 class="text-3xl font-black text-slate-900"><?= number_format($total_students) ?></h3>
            </div>
            <div class="stat-card bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100">
                <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600 mb-5"><i class="fas fa-file-invoice-dollar"></i></div>
                <p class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest mb-1">Total Fees</p>
                <h3 class="text-2xl font-black text-slate-900">₵<?= number_format($sum_fees, 2) ?></h3>
            </div>
            <div class="stat-card bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100">
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 mb-5"><i class="fas fa-hand-holding-dollar"></i></div>
                <p class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest mb-1">Total Paid</p>
                <h3 class="text-2xl font-black text-slate-900">₵<?= number_format($sum_paid, 2) ?></h3>
            </div>
            <div class="stat-card bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100">
                <div class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center text-rose-600 mb-5"><i class="fas fa-triangle-exclamation"></i></div>
                <p class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest mb-1">Outstanding</p>
                <h3 class="text-2xl font-black text-rose-600">₵<?= number_format($sum_due, 2) ?></h3>
            </div>
        </section>

        <!-- Filters Console -->
        <section class="no-print bg-white rounded-[2rem] p-8 border border-slate-100 shadow-sm mb-10">
            <h3 class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-[0.3em] mb-6 flex items-center gap-3">
                Parameters &amp; Refinement <span class="flex-1 h-[0.0625rem] bg-slate-100"></span>
            </h3>
            <form method="GET" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-4 items-end">
                <div>
                    <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1 block">Semester</label>
                    <select name="semester" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                        <?php foreach (getAvailableSemesters($conn) as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>" <?= $selected_term === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1 block">Academic Year</label>
                    <select name="academic_year" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                        <?php
                        $yr_opts = [];
                        $yrs_rs = $conn->query("SELECT DISTINCT academic_year FROM student_fees ORDER BY academic_year DESC");
                        while($y = $yrs_rs->fetch_assoc()) if($y['academic_year']) $yr_opts[] = $y['academic_year'];
                        if(!in_array($default_academic_year, $yr_opts)) array_unshift($yr_opts, $default_academic_year);
                        foreach($yr_opts as $y): ?>
                            <option value="<?= htmlspecialchars($y) ?>" <?= $selected_academic_year === $y ? 'selected' : '' ?>><?= formatAcademicYearDisplay($conn, $y) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1 block">Class</label>
                    <select name="class" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="all">All Classes</option>
                        <?php while($c = $classes_rs->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($c['class']) ?>" <?= $class_filter === $c['class'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1 block">Status</label>
                    <select name="status" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="active"   <?= $status_filter === 'active'   ? 'selected' : '' ?>>Active Only</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                        <option value="all"      <?= $status_filter === 'all'      ? 'selected' : '' ?>>All Students</option>
                    </select>
                </div>
                <div>
                    <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1 block">Balance</label>
                    <select name="owing" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="all"     <?= $owing_filter === 'all'     ? 'selected' : '' ?>>All</option>
                        <option value="owing"   <?= $owing_filter === 'owing'   ? 'selected' : '' ?>>Owing Money</option>
                        <option value="paid_up" <?= $owing_filter === 'paid_up' ? 'selected' : '' ?>>Paid Up</option>
                    </select>
                </div>
                <div>
                    <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1 block">% Paid</label>
                    <select name="percent" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="all"      <?= $percent_filter === 'all'      ? 'selected' : '' ?>>All</option>
                        <option value="below50"  <?= $percent_filter === 'below50'  ? 'selected' : '' ?>>Below 50%</option>
                        <option value="below75"  <?= $percent_filter === 'below75'  ? 'selected' : '' ?>>Below 75%</option>
                        <option value="below100" <?= $percent_filter === 'below100' ? 'selected' : '' ?>>Below 100%</option>
                    </select>
                </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-black text-[0.5625rem] uppercase tracking-widest px-3 py-3 rounded-xl shadow-lg shadow-emerald-600/20 transition-all">
                    <i class="fas fa-filter mr-1"></i> Apply
                </button>
            </form>
        </section>

        <!-- Ledger Table -->
        <section class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden mb-12">
            <div class="px-8 py-6 border-b border-slate-50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-slate-50/50">
                <div>
                    <h3 class="text-[0.625rem] font-black text-slate-400 uppercase tracking-[0.3em]">Master Balance Ledger</h3>
                    <p class="text-[0.5625rem] text-slate-400 font-bold mt-1">Showing <strong class="text-slate-600"><?= $total_students ?></strong> students</p>
                </div>
                <!-- Sort Controls -->
                <form method="GET" class="flex items-center gap-2 no-print">
                    <input type="hidden" name="semester"      value="<?= htmlspecialchars($selected_term) ?>">
                    <input type="hidden" name="academic_year" value="<?= htmlspecialchars($selected_academic_year) ?>">
                    <input type="hidden" name="class"         value="<?= htmlspecialchars($class_filter) ?>">
                    <input type="hidden" name="status"        value="<?= htmlspecialchars($status_filter) ?>">
                    <input type="hidden" name="owing"         value="<?= htmlspecialchars($owing_filter) ?>">
                    <input type="hidden" name="percent"       value="<?= htmlspecialchars($percent_filter) ?>">
                    <select name="sort_by" onchange="this.form.submit()" class="bg-white border border-slate-200 text-slate-600 text-[0.5625rem] font-black uppercase tracking-widest px-3 py-2 rounded-lg outline-none">
                        <option value="name"    <?= $sort_by === 'name'    ? 'selected' : '' ?>>Sort: Name</option>
                        <option value="class"   <?= $sort_by === 'class'   ? 'selected' : '' ?>>Sort: Class</option>
                        <option value="percent" <?= $sort_by === 'percent' ? 'selected' : '' ?>>Sort: % Paid</option>
                    </select>
                    <select name="order" onchange="this.form.submit()" class="bg-white border border-slate-200 text-slate-600 text-[0.5625rem] font-black uppercase tracking-widest px-3 py-2 rounded-lg outline-none">
                        <option value="asc"  <?= $order === 'asc'  ? 'selected' : '' ?>>Asc</option>
                        <option value="desc" <?= $order === 'desc' ? 'selected' : '' ?>>Desc</option>
                    </select>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest bg-slate-50/30 border-b border-slate-100">
                            <th class="px-8 py-5">Student</th>
                            <th class="px-5 py-5">Class</th>
                            <th class="px-5 py-5 text-center">% Paid</th>
                            <th class="px-5 py-5 text-right">Total Fees</th>
                            <th class="px-5 py-5 text-right">Total Paid</th>
                            <th class="px-5 py-5 text-right font-black text-rose-500">Balance</th>
                            <th class="px-5 py-5 text-center">Pending</th>
                            <th class="px-5 py-5 text-center">Paid</th>
                            <th class="px-8 py-5 text-right no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (empty($student_balances)): ?>
                            <tr>
                                <td colspan="9" class="px-8 py-20 text-center">
                                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-slate-200 text-3xl mx-auto mb-6"><i class="fas fa-ghost"></i></div>
                                    <h3 class="text-lg font-black text-slate-900 mb-2">No Records Found</h3>
                                    <p class="text-slate-500 font-medium">No students match your current filters.
                                        <a href="student_balances.php" class="text-emerald-600 hover:underline ml-1">Clear filters</a>
                                    </p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($student_balances as $s):
                                $outstanding  = max(0, (float)($s['net_balance'] ?? 0));
                                $total_fees   = (float)($s['total_fees'] ?? 0);
                                $total_paid   = (float)($s['total_payments'] ?? 0);
                                $percent      = round($s['paid_percent']);
                                $pending_cnt  = intval($s['pending_assignments'] ?? 0);
                                $paid_cnt     = intval($s['paid_assignments'] ?? 0);
                                $is_inactive  = ($s['student_status'] ?? '') === 'inactive';
                                $bar_color    = $percent >= 100 ? 'bg-emerald-500' : ($percent >= 50 ? 'bg-indigo-500' : 'bg-rose-500');
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 font-black text-xs group-hover:bg-white group-hover:shadow-sm transition-all border border-transparent group-hover:border-slate-100 flex-shrink-0">
                                            <?= strtoupper(substr($s['student_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <a href="student_balance_details.php?id=<?= $s['student_id'] ?>&semester=<?= urlencode($selected_term) ?>&academic_year=<?= urlencode($selected_academic_year) ?>" class="text-[0.75rem] font-black text-slate-800 hover:text-emerald-600 transition-colors block leading-tight">
                                                <?= htmlspecialchars($s['student_name']) ?>
                                            </a>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <p class="text-[0.5rem] font-bold text-slate-400 uppercase tracking-tight">SMS-<?= str_pad($s['student_id'], 3, '0', STR_PAD_LEFT) ?></p>
                                                <?php if($is_inactive): ?>
                                                    <span class="text-[0.4375rem] font-black uppercase tracking-widest bg-slate-100 text-slate-400 px-2 py-0.5 rounded-full">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-5">
                                    <span class="text-[0.625rem] font-bold text-slate-600 uppercase tracking-tight bg-slate-100 px-2.5 py-1 rounded-lg"><?= htmlspecialchars($s['class']) ?></span>
                                </td>
                                <td class="px-5 py-5">
                                    <div class="flex items-center gap-2 min-w-[5.625rem]">
                                        <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-full <?= $bar_color ?> transition-all" style="width: <?= $percent ?>%"></div>
                                        </div>
                                        <span class="text-[0.5625rem] font-black text-slate-500 w-7 text-right"><?= $percent ?>%</span>
                                    </div>
                                </td>
                                <td class="px-5 py-5 text-right text-sm font-black text-slate-700">₵<?= number_format($total_fees, 2) ?></td>
                                <td class="px-5 py-5 text-right text-sm font-black text-emerald-600">₵<?= number_format($total_paid, 2) ?></td>
                                <td class="px-5 py-5 text-right text-sm font-black <?= $outstanding > 0 ? 'text-rose-600' : 'text-emerald-600' ?>">
                                    <?= $outstanding > 0 ? '₵'.number_format($outstanding, 2) : 'Paid Up' ?>
                                </td>
                                <td class="px-5 py-5 text-center">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-[0.625rem] font-black <?= $pending_cnt > 0 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-400' ?>">
                                        <?= $pending_cnt ?>
                                    </span>
                                </td>
                                <td class="px-5 py-5 text-center">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-[0.625rem] font-black <?= $paid_cnt > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' ?>">
                                        <?= $paid_cnt ?>
                                    </span>
                                </td>
                                <td class="px-8 py-5 text-right no-print">
                                    <div class="dropdown-wrapper inline-block relative">
                                        <button class="dropdown-trigger w-8 h-8 rounded-lg bg-slate-50 border border-slate-200 text-slate-500 hover:bg-slate-100 flex items-center justify-center transition-all" tabindex="0">
                                            <i class="fas fa-ellipsis-v text-[0.6875rem]"></i>
                                        </button>
                                        <div class="dropdown-menu bg-white border border-slate-100 rounded-2xl shadow-xl py-2 mt-1">
                                            <a href="student_balance_details.php?id=<?= $s['student_id'] ?>&semester=<?= urlencode($selected_term) ?>&academic_year=<?= urlencode($selected_academic_year) ?>" class="flex items-center gap-3 px-4 py-2.5 text-[0.625rem] font-black text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition-colors uppercase tracking-widest">
                                                <i class="fas fa-eye w-4 text-indigo-500"></i> View Details
                                            </a>
                                            <a href="../payments/record_payment_form.php?student_id=<?= $s['student_id'] ?>" class="flex items-center gap-3 px-4 py-2.5 text-[0.625rem] font-black text-slate-600 hover:bg-slate-50 hover:text-emerald-600 transition-colors uppercase tracking-widest">
                                                <i class="fas fa-credit-card w-4 text-emerald-500"></i> Record Payment
                                            </a>
                                            <a href="../fees/assign_fee_form.php?student_id=<?= $s['student_id'] ?>" class="flex items-center gap-3 px-4 py-2.5 text-[0.625rem] font-black text-slate-600 hover:bg-slate-50 hover:text-sky-600 transition-colors uppercase tracking-widest">
                                                <i class="fas fa-plus w-4 text-sky-500"></i> Assign Fee
                                            </a>
                                            <a href="student_percentage.php?id=<?= $s['student_id'] ?>" class="flex items-center gap-3 px-4 py-2.5 text-[0.625rem] font-black text-slate-600 hover:bg-slate-50 hover:text-purple-600 transition-colors uppercase tracking-widest">
                                                <i class="fas fa-chart-pie w-4 text-purple-500"></i> Percentage
                                            </a>
                                            <div class="border-t border-slate-100 my-1"></div>
                                            <a href="download_invoice.php?student_id=<?= $s['student_id'] ?>&semester=<?= urlencode($selected_term) ?>&academic_year=<?= urlencode($selected_academic_year) ?>" class="flex items-center gap-3 px-4 py-2.5 text-[0.625rem] font-black text-slate-600 hover:bg-slate-50 hover:text-slate-800 transition-colors uppercase tracking-widest">
                                                <i class="fas fa-download w-4 text-slate-400"></i> Download Invoice
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <footer class="mt-4 py-8 border-t border-slate-200 text-[0.5625rem] font-black text-slate-300 uppercase tracking-[0.5em] text-center">
            Institutional Registry Ledger &middot; <?= htmlspecialchars($school_name) ?> &middot; <?= date('Y') ?>
        </footer>
    </main>

    <script>
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-wrapper')) {
                document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = '');
            }
        });
        document.querySelectorAll('.dropdown-trigger').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const menu = this.nextElementSibling;
                const isOpen = menu.style.display === 'block';
                document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = '');
                menu.style.display = isOpen ? '' : 'block';
            });
        });
    </script>
</body>
</html>
