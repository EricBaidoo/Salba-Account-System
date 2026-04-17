<?php
include '../../../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../../../includes/login.php');
    exit;
}
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

// School branding for print header
$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');

// Get filter parameters
$class_filter = $_GET['class'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$year_filter = $_GET['year'] ?? '';

// Academic year options
$current_academic_year = getAcademicYear($conn);
$year_options = [];
$yrs = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs) {
    while ($yr = $yrs->fetch_assoc()) {
        if (!empty($yr['academic_year'])) { $year_options[] = $yr['academic_year']; }
    }
    $yrs->close();
}
if (!in_array($current_academic_year, $year_options, true)) { array_unshift($year_options, $current_academic_year); }

// Build query with filters
$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($class_filter)) {
    $where_clauses[] = "v.student_class = ?";
    $params[] = $class_filter;
    $param_types .= 's';
}

if (!empty($status_filter)) {
    if ($status_filter === 'overdue') {
        $where_clauses[] = "v.payment_status = 'Overdue'";
    } else {
        $where_clauses[] = "v.status = ?";
        $params[] = $status_filter;
        $param_types .= 's';
    }
}

if (!empty($search)) {
    $where_clauses[] = "(v.student_name LIKE ? OR v.fee_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= 'ss';
}

if (!empty($year_filter)) {
    $where_clauses[] = "sf.academic_year = ?";
    $params[] = $year_filter;
    $param_types .= 's';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(' AND ', $where_clauses);
}

$sql = "SELECT v.*, sf.academic_year FROM v_fee_assignments v JOIN student_fees sf ON sf.id = v.assignment_id" . $where_sql . " ORDER BY v.due_date DESC, v.student_name";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if (!empty($param_types)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Get classes for filter dropdown
$classes_query = $conn->query("SELECT DISTINCT class FROM students ORDER BY class");

// Summary statistics
$stats_sql_base = "
    SELECT 
        COUNT(*) as total_assignments,
        SUM(CASE WHEN v.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN v.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN v.payment_status = 'Overdue' THEN 1 ELSE 0 END) as overdue_count,
        COALESCE(SUM(v.amount), 0) as total_amount,
        COALESCE(SUM(CASE WHEN v.status = 'paid' THEN v.amount ELSE 0 END), 0) as paid_amount
    FROM v_fee_assignments v
    JOIN student_fees sf ON sf.id = v.assignment_id";

if (!empty($year_filter)) {
    $st = $conn->prepare($stats_sql_base . " WHERE sf.academic_year = ?");
    $st->bind_param('s', $year_filter);
    $st->execute();
    $stats = $st->get_result()->fetch_assoc();
    $st->close();
} else {
    $stats = $conn->query($stats_sql_base)->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Assignments | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .table-container { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); }
        @media print {
            .no-print { display: none !important; }
            .ml-72 { margin-left: 0 !important; }
            .p-10 { padding: 1rem !important; }
        }
    </style>
</head>
<body class="bg-[#F8FAFC] text-slate-900">
    <div class="no-print"><?php include '../../../includes/sidebar_admin.php'; ?></div>

    <main class="ml-72 p-10 min-h-screen">
        <!-- Header Section -->
        <header class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6 no-print">
            <div>
                <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-indigo-600"></span>
                    Audit & Ledger
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Fee <span class="text-indigo-600">Assignments</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Historical trace of all financial obligations assigned to students.</p>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="window.print()" class="bg-white text-slate-600 border border-slate-200 font-black text-[10px] uppercase tracking-widest px-6 py-4 rounded-2xl hover:bg-slate-50 transition-all leading-none">
                    <i class="fas fa-print mr-2"></i> Print Report
                </button>
                <a href="assign_fee_form.php" class="bg-indigo-600 text-white font-black text-[10px] uppercase tracking-widest px-6 py-4 rounded-2xl shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 transition-all leading-none">
                    <i class="fas fa-plus mr-2"></i> New Assignment
                </a>
            </div>
        </header>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 mb-10 no-print">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Assigned</p>
                <h4 class="text-2xl font-black text-slate-900 leading-none mb-2"><?= $stats['total_assignments'] ?></h4>
                <div class="w-full h-1 bg-slate-50 rounded-full overflow-hidden">
                    <div class="h-full bg-slate-200" style="width: 100%"></div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Collected</p>
                <h4 class="text-2xl font-black text-emerald-600 leading-none mb-2"><?= $stats['paid_count'] ?></h4>
                <div class="w-full h-1 bg-emerald-50 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500" style="width: <?= ($stats['paid_count'] / ($stats['total_assignments'] ?: 1)) * 100 ?>%"></div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Overdue</p>
                <h4 class="text-2xl font-black text-rose-600 leading-none mb-2"><?= $stats['overdue_count'] ?></h4>
                <div class="w-full h-1 bg-rose-50 rounded-full overflow-hidden">
                    <div class="h-full bg-rose-500" style="width: <?= ($stats['overdue_count'] / ($stats['total_assignments'] ?: 1)) * 100 ?>%"></div>
                </div>
            </div>
             <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Pending</p>
                <h4 class="text-2xl font-black text-amber-600 leading-none mb-2"><?= $stats['pending_count'] ?></h4>
                <div class="w-full h-1 bg-amber-50 rounded-full overflow-hidden">
                    <div class="h-full bg-amber-500" style="width: <?= ($stats['pending_count'] / ($stats['total_assignments'] ?: 1)) * 100 ?>%"></div>
                </div>
            </div>
            <!-- Financial Sums -->
            <div class="bg-slate-900 p-6 rounded-3xl shadow-lg border border-slate-800 lg:col-span-1 xl:col-span-2 flex flex-col justify-center">
                <div class="flex justify-between items-end mb-4">
                    <div>
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Revenue Performance</p>
                        <h4 class="text-xl font-black text-white">GHS <?= number_format($stats['paid_amount'], 2) ?> <span class="text-xs font-bold text-slate-500 tracking-tight">/ GHS <?= number_format($stats['total_amount'], 2) ?></span></h4>
                    </div>
                    <div class="text-[10px] font-black text-emerald-400 uppercase tracking-widest">
                        <?= number_format(($stats['paid_amount'] / ($stats['total_amount'] ?: 1)) * 100, 1) ?>%
                    </div>
                </div>
                <div class="w-full h-2 bg-slate-800 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 transition-all duration-1000" style="width: <?= ($stats['paid_amount'] / ($stats['total_amount'] ?: 1)) * 100 ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-sm mb-10 no-print">
            <form method="GET" class="flex flex-wrap items-end gap-6">
                <div class="flex-1 min-w-[200px]">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i class="fas fa-search text-indigo-500"></i> Search Ledger
                    </label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Student name or fee..." 
                           class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-bold text-slate-700 transition-all">
                </div>
                <div class="w-48">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i class="fas fa-school text-indigo-500"></i> Class
                    </label>
                    <select name="class" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-bold text-slate-700 appearance-none transition-all">
                        <option value="">All Tiers</option>
                        <?php while($class = $classes_query->fetch_assoc()): ?>
                            <option value="<?= $class['class'] ?>" <?= ($class_filter === $class['class']) ? 'selected' : '' ?>><?= htmlspecialchars($class['class']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="w-48">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                         <i class="fas fa-graduation-cap text-indigo-500"></i> Academic Year
                    </label>
                    <select name="year" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-bold text-slate-700 appearance-none transition-all">
                        <option value="">All Periods</option>
                        <?php foreach ($year_options as $yr): $label = formatAcademicYearDisplay($conn, $yr); ?>
                            <option value="<?= htmlspecialchars($yr) ?>" <?= ($year_filter === $yr) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="w-48">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i class="fas fa-filter text-indigo-500"></i> Status
                    </label>
                    <select name="status" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-bold text-slate-700 appearance-none transition-all">
                        <option value="">All Status</option>
                         <option value="pending" <?= ($status_filter === 'pending') ? 'selected' : '' ?>>Pending</option>
                        <option value="paid" <?= ($status_filter === 'paid') ? 'selected' : '' ?>>Settled</option>
                        <option value="overdue" <?= ($status_filter === 'overdue') ? 'selected' : '' ?>>Overdue</option>
                    </select>
                </div>
                <button type="submit" class="bg-indigo-600 text-white w-14 h-14 rounded-2xl flex items-center justify-center hover:bg-indigo-700 hover:scale-105 transition-all shadow-lg shadow-indigo-600/20 active:scale-95">
                    <i class="fas fa-sliders"></i>
                </button>
            </form>
        </div>

        <!-- Ledger Table -->
        <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden table-container">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100">
                        <th class="px-8 py-6 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Student & Class</th>
                        <th class="px-8 py-6 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Fee Description</th>
                        <th class="px-8 py-6 text-left text-[10px) font-black text-slate-400 uppercase tracking-widest">Value (GHS)</th>
                        <th class="px-8 py-6 text-left text-[10px) font-black text-slate-400 uppercase tracking-widest">Maturity / Period</th>
                        <th class="px-8 py-6 text-center text-[10px) font-black text-slate-400 uppercase tracking-widest">Audit Status</th>
                        <th class="px-8 py-6 text-right text-[10px) font-black text-slate-400 uppercase tracking-widest no-print">Ops</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50/80 transition-colors group">
                                <td class="px-8 py-6">
                                    <div class="flex flex-col">
                                        <span class="font-black text-slate-900 text-sm tracking-tight"><?= htmlspecialchars($row['student_name']) ?></span>
                                        <span class="text-[10px] font-bold text-indigo-600 uppercase tracking-wider"><?= htmlspecialchars($row['student_class']) ?></span>
                                    </div>
                                </td>
                                <td class="px-8 py-6 text-sm font-medium text-slate-600">
                                    <?= htmlspecialchars($row['fee_name']) ?>
                                    <?php if (!empty($row['notes'])): ?>
                                        <div class="mt-1 text-[10px] text-slate-400 italic font-medium flex items-center gap-1">
                                            <i class="fas fa-sticky-note"></i> Notes attached
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-8 py-6">
                                    <span class="font-black text-slate-900 text-sm tracking-tighter"><?= number_format($row['amount'], 2) ?></span>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-slate-700 leading-none mb-1"><?= date('M j, Y', strtotime($row['due_date'])) ?></span>
                                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest"><?= htmlspecialchars($row['semester']) ?> | <?= htmlspecialchars(formatAcademicYearDisplay($conn, $row['academic_year'])) ?></span>
                                    </div>
                                </td>
                                <td class="px-8 py-6 text-center">
                                    <?php
                                        $label = ''; $color = ''; $icon = '';
                                        switch($row['payment_status']) {
                                            case 'Overdue': $label = 'Critical'; $color = 'bg-rose-50 text-rose-600 border-rose-100'; $icon = 'fa-clock'; break;
                                            case 'Due Soon': $label = 'Expiring'; $color = 'bg-amber-50 text-amber-600 border-amber-100'; $icon = 'fa-hourglass-start'; break;
                                            case 'Pending': $label = 'Scheduled'; $color = 'bg-slate-50 text-slate-500 border-slate-100'; $icon = 'fa-calendar'; break;
                                            case 'Paid': $label = 'Cleared'; $color = 'bg-emerald-50 text-emerald-600 border-emerald-100'; $icon = 'fa-check-double'; break;
                                            default: $label = 'Unknown'; $color = 'bg-slate-100 text-slate-400'; $icon = 'fa-question';
                                        }
                                    ?>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-[9px] font-black uppercase tracking-widest <?= $color ?>">
                                        <i class="fas <?= $icon ?>"></i> <?= $label ?>
                                    </span>
                                </td>
                                <td class="px-8 py-6 text-right no-print">
                                    <div class="flex items-center justify-end gap-2">
                                        <?php if($row['status'] !== 'paid'): ?>
                                            <a href="../payments/record_payment_form.php?assignment_id=<?= $row['assignment_id'] ?>&semester=<?= urlencode($row['semester']) ?>&academic_year=<?= urlencode($row['academic_year'] ?? '') ?>" 
                                               class="w-9 h-9 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center hover:bg-emerald-600 hover:text-white transition-all shadow-sm" title="Record Settlement">
                                                <i class="fas fa-credit-card text-xs"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button onclick="viewDetails(<?= $row['assignment_id'] ?>)" class="w-9 h-9 bg-white text-slate-400 border border-slate-100 rounded-xl flex items-center justify-center hover:bg-slate-900 hover:text-white transition-all shadow-sm">
                                            <i class="fas fa-eye text-xs"></i>
                                        </button>
                                        <button onclick="cancelAssignment(<?= $row['assignment_id'] ?>)" class="w-9 h-9 bg-white text-slate-300 border border-slate-50 rounded-xl flex items-center justify-center hover:bg-rose-600 hover:text-white transition-all shadow-sm">
                                            <i class="fas fa-times text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-8 py-20 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-slate-200 text-3xl mb-4">
                                        <i class="fas fa-inbox"></i>
                                    </div>
                                    <p class="text-slate-400 font-bold uppercase tracking-[0.2em] text-[10px]">No active assignments detected for this scope</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer Audit -->
        <footer class="mt-20 py-10 border-t border-slate-200 flex justify-between items-center text-[10px] font-black text-slate-300 uppercase tracking-[0.5em] no-print">
            <span>Institutional Ledger &middot; Status Report &middot; <?= date('Y-m-d H:i:s') ?></span>
            <div class="flex gap-6">
                <a href="../dashboard.php">Finance Home</a>
                <a href="../bills/view_semester_bills.php">Invoicing Center</a>
            </div>
        </footer>
    </main>

    <script>
        function viewDetails(assignmentId) {
            alert('Opening Audit Trail for Assignment ID: ' + assignmentId);
        }

        function cancelAssignment(assignmentId) {
            if (confirm('CAUTION: Are you sure you want to VOID this fee assignment? This action will be logged and may impact historical student balance reports.')) {
                window.location.href = 'cancel_assignment.php?id=' + assignmentId;
            }
        }
    </script>
</body>
</html>
