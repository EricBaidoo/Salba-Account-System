<?php
include_once '../../includes/auth_functions.php';
include_once '../../includes/db_connect.php';
include_once '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../login');
    exit;
}
require_finance_access();

// Get current session context
$current_semester = getCurrentSemester($conn);
$acad_year = getAcademicYear($conn);

// Get finance statistics for CURRENT TERM ONLY
$total_fees = $conn->query("SELECT SUM(amount) as total FROM student_fees WHERE semester = '$current_semester' AND academic_year = '$acad_year' AND status != 'cancelled'")->fetch_assoc()['total'] ?? 0;
$total_payments = $conn->query("SELECT SUM(amount) as total FROM payments WHERE semester = '$current_semester' AND academic_year = '$acad_year'")->fetch_assoc()['total'] ?? 0;
$total_expenses = $conn->query("SELECT SUM(amount) as total FROM expenses WHERE semester = '$current_semester' AND academic_year = '$acad_year'")->fetch_assoc()['total'] ?? 0;
$outstanding = $total_fees - $total_payments;

// Count students with outstanding fees in CURRENT TERM
$pending_payments_result = $conn->query("
    SELECT COUNT(DISTINCT s.id) as cnt 
    FROM students s 
    INNER JOIN student_fees sf ON s.id = sf.student_id
    WHERE s.status = 'active' 
    AND sf.semester = '$current_semester'
    AND sf.academic_year = '$acad_year'
    AND sf.status != 'cancelled'
    AND (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.student_id = s.id AND p.semester = '$current_semester' AND p.academic_year = '$acad_year') < 
        sf.amount
");
$pending_students = $pending_payments_result ? ($pending_payments_result->fetch_assoc()['cnt'] ?? 0) : 0;

$net_position = $total_payments - $total_expenses;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Hub | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .metric-gradient-1 { background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%); }
        .metric-gradient-2 { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .metric-gradient-3 { background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%); }
        .metric-gradient-4 { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
    </style>
</head>
<body class="bg-[#F8FAFC] text-slate-900">
    <?php include '../../includes/sidebar.php'; ?>
        
    <main class="admin-main-content lg:ml-72 p-4 md:p-8 p-10 min-h-screen">
        <!-- Header Section -->
        <header class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-emerald-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-emerald-600"></span>
                    Institutional Oversight
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Finance & <span class="text-emerald-600">Billing Hub</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Global financial console for revenue tracking, expenditure, and student billing.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="bg-white px-5 py-3 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Current Session</p>
                        <p class="text-sm font-bold text-slate-700"><?= htmlspecialchars(getCurrentSemester($conn)) ?> | <?= htmlspecialchars(getAcademicYear($conn)) ?></p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dynamic Metrics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <!-- Revenue Card -->
            <div class="metric-gradient-1 p-6 rounded-[2rem] shadow-xl shadow-indigo-500/20 text-white relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-6 opacity-20 group-hover:scale-110 transition-transform duration-500">
                    <i class="fas fa-coins text-6xl"></i>
                </div>
                <p class="text-indigo-100 text-xs font-black uppercase tracking-widest mb-1">Total Receivables</p>
                <h2 class="text-3xl font-black mb-4">GHS <?= number_format($total_fees, 2) ?></h2>
                <div class="flex items-center gap-2 text-[10px] font-bold bg-white/10 w-fit px-3 py-1 rounded-full backdrop-blur-sm">
                    <i class="fas fa-info-circle"></i> Current Semester Context
                </div>
            </div>

            <!-- Payments Card -->
            <div class="metric-gradient-2 p-6 rounded-[2rem] shadow-xl shadow-emerald-500/20 text-white relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-6 opacity-20 group-hover:scale-110 transition-transform duration-500">
                    <i class="fas fa-hand-holding-dollar text-6xl"></i>
                </div>
                <p class="text-emerald-100 text-xs font-black uppercase tracking-widest mb-1">Revenue Collected</p>
                <h2 class="text-3xl font-black mb-4">GHS <?= number_format($total_payments, 2) ?></h2>
                <div class="flex items-center gap-2 text-[10px] font-bold bg-white/10 w-fit px-3 py-1 rounded-full backdrop-blur-sm">
                    <i class="fas fa-check-circle"></i> <?= number_format(($total_payments / ($total_fees ?: 1)) * 100, 1) ?>% Collections
                </div>
            </div>

            <!-- Outstanding Card -->
            <div class="metric-gradient-4 p-6 rounded-[2rem] shadow-xl shadow-amber-500/20 text-white relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-6 opacity-20 group-hover:scale-110 transition-transform duration-500">
                    <i class="fas fa-exclamation-circle text-6xl"></i>
                </div>
                <p class="text-amber-100 text-xs font-black uppercase tracking-widest mb-1">Semester Exposure</p>
                <h2 class="text-3xl font-black mb-4">GHS <?= number_format($outstanding, 2) ?></h2>
                <div class="flex items-center gap-2 text-[10px] font-bold bg-white/10 w-fit px-3 py-1 rounded-full backdrop-blur-sm">
                    <i class="fas fa-users"></i> <?= $pending_students ?> Active Arrears
                </div>
            </div>

            <!-- Net Position -->
            <div class="bg-slate-900 p-6 rounded-[2rem] shadow-xl shadow-slate-900/20 text-white relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-6 opacity-20 group-hover:scale-110 transition-transform duration-500 text-slate-500">
                    <i class="fas fa-chart-line text-6xl"></i>
                </div>
                <p class="text-slate-500 text-xs font-black uppercase tracking-widest mb-1">Net Cashflow</p>
                <h2 class="text-3xl font-black mb-4 <?= $net_position >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">GHS <?= number_format($net_position, 2) ?></h2>
                <div class="flex items-center gap-2 text-[10px] font-bold bg-white/5 w-fit px-3 py-1 rounded-full backdrop-blur-sm">
                    <i class="fas fa-shield-halved"></i> Liquidity Status
                </div>
            </div>
        </div>

        <!-- Navigation Grid -->
        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-6 flex items-center gap-3">
            Available Modules <span class="flex-1 h-[1px] bg-slate-100"></span>
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Billing Card -->
            <a href="bills/view_semester_bills.php" class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 group relative">
                <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-3xl flex items-center justify-center text-2xl mb-8 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-500">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <h4 class="text-xl font-black text-slate-900 mb-2">Billing Center</h4>
                <p class="text-slate-500 text-sm font-medium leading-relaxed mb-8">Generate, manage, and batch-print semester bills for all students.</p>
                <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-widest">
                    Manage Bills <i class="fas fa-arrow-right transition-transform group-hover:translate-x-2"></i>
                </div>
            </a>

            <!-- Payments Card -->
            <a href="payments/view_payments.php" class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 group">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-3xl flex items-center justify-center text-2xl mb-8 group-hover:bg-emerald-600 group-hover:text-white transition-all duration-500">
                    <i class="fas fa-credit-card"></i>
                </div>
                <h4 class="text-xl font-black text-slate-900 mb-2">Revenue Input</h4>
                <p class="text-slate-500 text-sm font-medium leading-relaxed mb-8">Record fee payments, issue receipts, and track student balances.</p>
                <div class="flex items-center gap-2 text-emerald-600 font-bold text-xs uppercase tracking-widest">
                    Record Payment <i class="fas fa-arrow-right transition-transform group-hover:translate-x-2"></i>
                </div>
            </a>

            <!-- Expenses Card -->
            <a href="expenses/view_expenses.php" class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 group">
                <div class="w-16 h-16 bg-rose-50 text-rose-600 rounded-3xl flex items-center justify-center text-2xl mb-8 group-hover:bg-rose-600 group-hover:text-white transition-all duration-500">
                    <i class="fas fa-receipt"></i>
                </div>
                <h4 class="text-xl font-black text-slate-900 mb-2">Expenditure</h4>
                <p class="text-slate-500 text-sm font-medium leading-relaxed mb-8">Monitor institutional spending, categorize expenses, and track outflows.</p>
                <div class="flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-widest">
                    View Expenses <i class="fas fa-arrow-right transition-transform group-hover:translate-x-2"></i>
                </div>
            </a>

            <!-- Fees Card -->
            <a href="fees/view_fees.php" class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 group">
                <div class="w-16 h-16 bg-amber-50 text-amber-600 rounded-3xl flex items-center justify-center text-2xl mb-8 group-hover:bg-amber-600 group-hover:text-white transition-all duration-500">
                    <i class="fas fa-tags"></i>
                </div>
                <h4 class="text-xl font-black text-slate-900 mb-2">Fee Structure</h4>
                <p class="text-slate-500 text-sm font-medium leading-relaxed mb-8">Define fee categories, assign tiers to classes, and manage pricing.</p>
                <div class="flex items-center gap-2 text-amber-600 font-bold text-xs uppercase tracking-widest">
                    Configure Fees <i class="fas fa-arrow-right transition-transform group-hover:translate-x-2"></i>
                </div>
            </a>

            <!-- Budgets Card -->
            <a href="budgets/semester_budget.php" class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 group">
                <div class="w-16 h-16 bg-purple-50 text-purple-600 rounded-3xl flex items-center justify-center text-2xl mb-8 group-hover:bg-purple-600 group-hover:text-white transition-all duration-500">
                    <i class="fas fa-sack-dollar"></i>
                </div>
                <h4 class="text-xl font-black text-slate-900 mb-2">Planning</h4>
                <p class="text-slate-500 text-sm font-medium leading-relaxed mb-8">Set up semester budgets and monitor actual vs. planned performance.</p>
                <div class="flex items-center gap-2 text-purple-600 font-bold text-xs uppercase tracking-widest">
                    Open Budgets <i class="fas fa-arrow-right transition-transform group-hover:translate-x-2"></i>
                </div>
            </a>

            <!-- Settings Card -->
            <a href="settings.php" class="bg-slate-900 p-8 rounded-[2.5rem] shadow-xl shadow-slate-900/10 hover:-translate-y-2 transition-all duration-500 group relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/10 to-transparent"></div>
                <div class="w-16 h-16 bg-white/10 text-white rounded-3xl flex items-center justify-center text-2xl mb-8 group-hover:bg-white group-hover:text-slate-900 transition-all duration-500 relative z-10">
                    <i class="fas fa-sliders"></i>
                </div>
                <h4 class="text-xl font-black text-white mb-2 relative z-10">Protocols</h4>
                <p class="text-slate-400 text-sm font-medium leading-relaxed mb-8 relative z-10">Configure currency, late fee rules, and global billing footer details.</p>
                <div class="flex items-center gap-2 text-white font-bold text-xs uppercase tracking-[0.2em] relative z-10">
                    Edit Protocol <i class="fas fa-arrow-right transition-transform group-hover:translate-x-2"></i>
                </div>
            </a>
        </div>

        <footer class="mt-20 py-10 border-t border-slate-200 flex justify-between items-center">
            <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">Salba Montessori Financial ERP &middot; v9.5.0</p>
            <div class="flex gap-4">
                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-xs hover:bg-emerald-600 hover:text-white cursor-help transition-all">
                    <i class="fas fa-question"></i>
                </div>
            </div>
        </footer>
    </main>
</body>
</html>
