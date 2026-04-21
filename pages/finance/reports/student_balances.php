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
$class_filter = $_GET['class'] ?? 'all';
$status_filter = $_GET['status'] ?? 'active';
$owing_filter = $_GET['owing'] ?? 'all'; 

// Pre-compute arrears
{
    $where = [];
    $params = [];
    $types = '';
    if ($status_filter && $status_filter !== 'all') { $where[] = "status = ?"; $params[] = $status_filter; $types .= 's'; }
    if ($class_filter && $class_filter !== 'all') { $where[] = "class = ?"; $params[] = $class_filter; $types .= 's'; }
    $sql = "SELECT id FROM students" . (empty($where) ? '' : (' WHERE ' . implode(' AND ', $where)));
    $stmt = $conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            ensureArrearsAssignment($conn, intval($row['id']), $selected_term, $selected_academic_year);
        }
    }
}

// Data Fetching
$student_balances = getAllStudentBalances($conn, $class_filter, $status_filter, $selected_term, $selected_academic_year);

// Post-fetch filtering
if ($owing_filter === 'owing') {
    $student_balances = array_filter($student_balances, function($s) { return $s['net_balance'] > 0; });
} elseif ($owing_filter === 'paid_up') {
    $student_balances = array_filter($student_balances, function($s) { return $s['net_balance'] == 0; });
}

foreach ($student_balances as &$s) {
    $tf = (float)($s['total_fees'] ?? 0);
    $tp = (float)($s['total_payments'] ?? 0);
    $s['paid_percent'] = ($tf > 0) ? min(100, ($tp / $tf) * 100) : (($tp > 0) ? 100 : 0);
}
unset($s);

$percent_filter = $_GET['percent'] ?? 'all';
if ($percent_filter !== 'all') {
    $student_balances = array_filter($student_balances, function($st) use ($percent_filter) {
        $p = $st['paid_percent'];
        if($percent_filter === 'below50') return $p < 50;
        if($percent_filter === 'below75') return $p < 75;
        if($percent_filter === 'below100') return $p < 100;
        return true;
    });
}

// Sort
$sort_by = $_GET['sort_by'] ?? 'name';
$order = $_GET['order'] ?? 'asc';
usort($student_balances, function($a, $b) use ($sort_by, $order) {
    if ($sort_by === 'percent') $valA = $a['paid_percent'];
    elseif ($sort_by === 'class') $valA = $a['class'];
    else $valA = $a['student_name'];

    if ($sort_by === 'percent') $valB = $b['paid_percent'];
    elseif ($sort_by === 'class') $valB = $b['class'];
    else $valB = $b['student_name'];

    if ($valA == $valB) return 0;
    if ($order === 'asc') return ($valA < $valB) ? -1 : 1;
    return ($valA > $valB) ? -1 : 1;
});

// Stats
$total_students = count($student_balances);
$sum_fees = array_sum(array_column($student_balances, 'total_fees'));
$sum_paid = array_sum(array_column($student_balances, 'total_payments'));
$sum_due = array_sum(array_column($student_balances, 'net_balance'));

$classes_rs = $conn->query("SELECT DISTINCT class FROM students ORDER BY class");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Ledger | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .glass-header { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(241, 245, 249, 1); }
        .stat-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .stat-card:hover { transform: translateY(-5px); }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .ml-72 { margin-left: 0 !important; }
            .p-10 { padding: 20px !important; }
        }
    </style>
</head>
<body class="text-slate-900 leading-relaxed">
    <div class="no-print"><?php include '../../../includes/sidebar_admin_modern.php'; ?></div>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">
        <!-- Header -->
        <header class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-emerald-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-emerald-600"></span>
                    Financial Intelligence
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Student <span class="text-emerald-600">Balances</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Institutional debt exposure and revenue maturity overview.</p>
            </div>
            <div class="no-print flex flex-col md:flex-row gap-4">
                 <a href="download_student_balances.php?class=<?= urlencode($class_filter) ?>&status=<?= urlencode($status_filter) ?>&owing=<?= urlencode($owing_filter) ?>&semester=<?= urlencode($selected_term) ?>&academic_year=<?= urlencode($selected_academic_year) ?>" class="bg-indigo-50 text-indigo-600 font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:bg-indigo-600 hover:text-white transition-all leading-none border border-indigo-100 text-center">
                    <i class="fas fa-file-csv mr-2"></i> Extract CSV
                </a>
                 <a href="../payments/record_payment_form.php" class="bg-slate-900 text-white font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:bg-slate-800 transition-all leading-none text-center">
                    <i class="fas fa-plus mr-2"></i> Record Remittance
                </a>
            </div>
        </header>

        <!-- Stats Grid -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <div class="stat-card bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <div class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 mb-6">
                    <i class="fas fa-users-viewfinder text-xl"></i>
                </div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Impact Population</p>
                <h3 class="text-3xl font-black text-slate-900"><?= number_format($total_students) ?> <span class="text-xs text-slate-300">Students</span></h3>
            </div>
            <div class="stat-card bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 mb-6">
                    <i class="fas fa-file-invoice-dollar text-xl"></i>
                </div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Maturity Goal</p>
                <h3 class="text-3xl font-black text-slate-900">₵<?= number_format($sum_fees, 2) ?></h3>
            </div>
            <div class="stat-card bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 mb-6">
                    <i class="fas fa-hand-holding-dollar text-xl"></i>
                </div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Realized Intake</p>
                <h3 class="text-3xl font-black text-slate-900">₵<?= number_format($sum_paid, 2) ?></h3>
            </div>
            <div class="stat-card bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <div class="w-12 h-12 bg-rose-50 rounded-2xl flex items-center justify-center text-rose-600 mb-6">
                    <i class="fas fa-triangle-exclamation text-xl"></i>
                </div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Current Exposure</p>
                <h3 class="text-3xl font-black text-slate-900 text-rose-600">₵<?= number_format($sum_due, 2) ?></h3>
            </div>
        </section>

        <!-- Filters Console -->
        <section class="no-print bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm mb-12">
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-3">
                Parameters & Refinement <span class="flex-1 h-[1px] bg-slate-50"></span>
            </h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-6 items-end">
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Semester</label>
                    <select name="semester" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                        <?php foreach (getAvailableSemesters($conn) as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>" <?= $selected_term === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Academic Year</label>
                    <select name="academic_year" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
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
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Level</label>
                    <select name="class" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="all">All Classes</option>
                        <?php while($c = $classes_rs->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($c['class']) ?>" <?= $class_filter === $c['class'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Maturity Status</label>
                    <select name="owing" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="all" <?= $owing_filter==='all'?'selected':'' ?>>All Entries</option>
                        <option value="owing" <?= $owing_filter==='owing'?'selected':'' ?>>Residual Debt</option>
                        <option value="paid_up" <?= $owing_filter==='paid_up'?'selected':'' ?>>Fully Settled</option>
                    </select>
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Percentile Range</label>
                    <select name="percent" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="all" <?= $percent_filter==='all'?'selected':'' ?>>All Ranges</option>
                        <option value="below50" <?= $percent_filter==='below50'?'selected':'' ?>>Below 50%</option>
                        <option value="below75" <?= $percent_filter==='below75'?'selected':'' ?>>Below 75%</option>
                        <option value="below100" <?= $percent_filter==='below100'?'selected':'' ?>>Below 100%</option>
                    </select>
                </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-black text-[10px] uppercase tracking-widest px-4 py-4 rounded-xl shadow-lg shadow-emerald-600/20 transition-all leading-none">
                    Execute Query
                </button>
            </form>
        </section>

        <!-- Ledger Table -->
        <section class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden mb-12">
            <div class="px-10 py-8 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
                 <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]">Master Balance Ledger</h3>
                 <div class="flex gap-2">
                    <button onclick="window.print()" class="no-print bg-white border border-slate-200 text-slate-600 font-bold text-[9px] uppercase tracking-widest px-4 py-2 rounded-xl hover:bg-slate-50 transition-all">
                        <i class="fas fa-print mr-2"></i> Release Print Job
                    </button>
                 </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50/20">
                            <th class="px-10 py-6">Student Identifying Name</th>
                            <th class="px-6 py-6">Grade Level</th>
                            <th class="px-6 py-6 font-black text-slate-900">Settled (₵)</th>
                            <th class="px-6 py-6 font-black text-rose-600">Arrears (₵)</th>
                            <th class="px-6 py-6 min-w-[150px]">Fulfillment Status</th>
                            <th class="px-10 py-6 text-right no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach($student_balances as $s): 
                            $outstanding = $s['net_balance'];
                            $percent = round($s['paid_percent']);
                        ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group">
                            <td class="px-10 py-6">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-300 font-black text-xs group-hover:bg-white group-hover:shadow-sm transition-all border border-transparent group-hover:border-slate-100">
                                        <?= substr($s['student_name'], 0, 1) ?>
                                    </div>
                                    <div>
                                        <a href="student_balance_details.php?id=<?= $s['student_id'] ?>&semester=<?= urlencode($selected_term) ?>&academic_year=<?= urlencode($selected_academic_year) ?>" class="text-[13px] font-black text-slate-800 hover:text-emerald-600 transition-colors block leading-tight mb-1">
                                            <?= htmlspecialchars($s['student_name']) ?>
                                        </a>
                                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">UID: SMS-<?= str_pad($s['student_id'], 3, '0', STR_PAD_LEFT) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-6">
                                <span class="text-[11px] font-bold text-slate-600 uppercase tracking-tight bg-slate-100 px-3 py-1 rounded-lg">
                                    <?= htmlspecialchars($s['class']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-6 text-sm font-black text-slate-900 italic">₵<?= number_format($s['total_payments'], 2) ?></td>
                            <td class="px-6 py-6 text-sm font-black <?= $outstanding > 0 ? 'text-rose-600' : 'text-emerald-600' ?>">
                                <?= $outstanding > 0 ? '₵'.number_format($outstanding, 2) : 'Fully Settled' ?>
                            </td>
                            <td class="px-6 py-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full <?= $percent >= 100 ? 'bg-emerald-500' : ($percent >= 50 ? 'bg-indigo-500' : 'bg-rose-500') ?>" style="width: <?= $percent ?>%"></div>
                                    </div>
                                    <span class="text-[10px] font-black text-slate-400 w-8"><?= $percent ?>%</span>
                                </div>
                            </td>
                            <td class="px-10 py-6 text-right no-print">
                                <div class="flex justify-end gap-2">
                                    <a href="../payments/record_payment_form.php?student_id=<?= $s['student_id'] ?>" title="Record Payment" class="w-8 h-8 rounded-lg flex items-center justify-center text-emerald-600 bg-emerald-50 hover:bg-emerald-600 hover:text-white transition-all">
                                        <i class="fas fa-credit-card text-[10px]"></i>
                                    </a>
                                    <a href="../bills/semester_bill.php?student_id=<?= $s['student_id'] ?>&semester=<?= urlencode($selected_term) ?>&academic_year=<?= urlencode($selected_academic_year) ?>" title="View Bill" class="w-8 h-8 rounded-lg flex items-center justify-center text-indigo-600 bg-indigo-50 hover:bg-indigo-600 hover:text-white transition-all">
                                        <i class="fas fa-file-invoice text-[10px]"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (empty($student_balances)): ?>
                <div class="p-20 text-center">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-slate-200 text-3xl mx-auto mb-6">
                        <i class="fas fa-ghost"></i>
                    </div>
                    <h3 class="text-xl font-black text-slate-900 mb-2">Null Sector Encountered</h3>
                    <p class="text-slate-500 font-medium max-w-sm mx-auto">No student records match the active criteria. Adjust filters to broaden your audit range.</p>
                </div>
            <?php endif; ?>
        </section>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">
            Institutional Registry Ledger &middot; Salba Montessori &middot; v9.5.0
        </footer>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
