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

// Get finance statistics for CURRENT TRIMESTER ONLY
// Total fees assigned to active students
$total_fees = $conn->query("
    SELECT SUM(sf.amount) as total 
    FROM student_fees sf 
    INNER JOIN students s ON sf.student_id = s.id 
    WHERE s.status = 'active' 
      AND sf.semester = '$current_semester' 
      AND sf.academic_year = '$acad_year' 
      AND sf.status != 'cancelled'
")->fetch_assoc()['total'] ?? 0;

// Total payments collected (payment_type = 'student') — all students including inactive
$total_student_payments = $conn->query("
    SELECT SUM(amount) as total 
    FROM payments 
    WHERE semester = '$current_semester' 
      AND academic_year = '$acad_year' 
      AND payment_type = 'student'
")->fetch_assoc()['total'] ?? 0;

// Total general payments collected (not student fee payments)
$total_general_payments = $conn->query("
    SELECT SUM(amount) as total 
    FROM payments 
    WHERE semester = '$current_semester' 
      AND academic_year = '$acad_year' 
      AND payment_type = 'general'
")->fetch_assoc()['total'] ?? 0;

// Aggregate payments (fees + general) received this semester for Net Surplus calculations
$total_revenue = $conn->query("
    SELECT SUM(amount) as total 
    FROM payments 
    WHERE semester = '$current_semester' 
      AND academic_year = '$acad_year'
")->fetch_assoc()['total'] ?? 0;

$total_expenses = $conn->query("
    SELECT SUM(amount) as total 
    FROM expenses 
    WHERE semester = '$current_semester' 
      AND academic_year = '$acad_year'
")->fetch_assoc()['total'] ?? 0;

$total_payroll_expense = $conn->query("
    SELECT SUM(total_gross + total_employer_ssnit) as total 
    FROM payroll_runs 
    WHERE status IN ('approved', 'paid')
")->fetch_assoc()['total'] ?? 0;

// Outstanding balance = sum of positive outstanding balances for active students (amounts owed by learners)
$outstanding_query = $conn->query("
    SELECT SUM(GREATEST(0, sf_sum.fees - COALESCE(p_sum.paid, 0))) as outstanding
    FROM (
        SELECT sf.student_id, SUM(sf.amount) as fees 
        FROM student_fees sf
        INNER JOIN students s ON sf.student_id = s.id
        WHERE s.status = 'active' 
          AND sf.semester = '$current_semester' 
          AND sf.academic_year = '$acad_year' 
          AND sf.status != 'cancelled'
        GROUP BY sf.student_id
    ) sf_sum
    LEFT JOIN (
        SELECT p.student_id, SUM(p.amount) as paid 
        FROM payments p
        INNER JOIN students s ON p.student_id = s.id
        WHERE s.status = 'active' 
          AND p.semester = '$current_semester' 
          AND p.academic_year = '$acad_year' 
          AND p.payment_type = 'student'
        GROUP BY p.student_id
    ) p_sum ON sf_sum.student_id = p_sum.student_id
");
$outstanding = $outstanding_query ? ($outstanding_query->fetch_assoc()['outstanding'] ?? 0) : 0;

// Count students with outstanding fees in CURRENT TRIMESTER
$pending_students_query = $conn->query("
    SELECT COUNT(*) as cnt FROM (
        SELECT sf.student_id, SUM(sf.amount) as fees 
        FROM student_fees sf
        INNER JOIN students s ON sf.student_id = s.id
        WHERE s.status = 'active' 
          AND sf.semester = '$current_semester' 
          AND sf.academic_year = '$acad_year' 
          AND sf.status != 'cancelled'
        GROUP BY sf.student_id
    ) sf_sum
    LEFT JOIN (
        SELECT p.student_id, SUM(p.amount) as paid 
        FROM payments p
        INNER JOIN students s ON p.student_id = s.id
        WHERE s.status = 'active' 
          AND p.semester = '$current_semester' 
          AND p.academic_year = '$acad_year' 
          AND p.payment_type = 'student'
        GROUP BY p.student_id
    ) p_sum ON sf_sum.student_id = p_sum.student_id
    WHERE sf_sum.fees > COALESCE(p_sum.paid, 0)
");
$pending_students = $pending_students_query ? ($pending_students_query->fetch_assoc()['cnt'] ?? 0) : 0;

// Net Surplus = total revenues received (fees + general) minus expenses and payroll
$net_position = $total_revenue - $total_expenses - $total_payroll_expense;
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
            backdrop-filter: blur(0.75rem);
            border: 0.0625rem solid rgba(255, 255, 255, 0.3);
        }
        .metric-gradient-1 { background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%); }
        .metric-gradient-2 { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .metric-gradient-3 { background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%); }
        .metric-gradient-4 { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <?php include '../../includes/sidebar.php'; ?>
        
    <main class="admin-main-content lg:ml-72 p-4 md:p-8 p-10 min-h-screen">
        <!-- Header Section -->
        <header class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-6 border-b border-slate-200 pb-6">
            <div>
                <div class="flex items-center gap-2 text-blue-600 font-semibold text-xs uppercase tracking-wider mb-2">
                    Institutional Oversight
                </div>
                <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">Finance & Billing</h1>
                <p class="text-slate-500 mt-1 text-sm">Global financial console for revenue tracking, expenditure, and student billing.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="bg-white px-4 py-2 rounded-lg shadow-sm border border-slate-200 flex items-center gap-3">
                    <div class="text-blue-600 text-lg">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div>
                        <p class="text-[0.65rem] font-semibold text-slate-400 uppercase tracking-wider leading-none mb-1">Current Session</p>
                        <p class="text-sm font-medium text-slate-700"><?= htmlspecialchars(getCurrentSemester($conn)) ?> | <?= htmlspecialchars(getAcademicYear($conn)) ?></p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dynamic Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
            <!-- Revenue Card -->
            <div class="bg-indigo-50/50 p-4 rounded-xl border border-indigo-100 shadow-sm relative overflow-hidden group">
                <p class="text-indigo-600 text-[10px] font-bold uppercase tracking-wider mb-1">Total Fees</p>
                <h2 class="text-xl font-bold text-slate-900 mb-2">GHS <?= number_format($total_fees, 2) ?></h2>
                <div class="flex items-center gap-1.5 text-[10px] font-semibold text-slate-500">
                    <i class="fas fa-info-circle text-indigo-500"></i> What students owe this term
                </div>
                <div class="absolute top-3 right-3 text-indigo-200 group-hover:text-indigo-300 transition-colors">
                    <i class="fas fa-coins text-2xl"></i>
                </div>
            </div>

            <!-- Payments Card -->
            <div class="bg-emerald-50/50 p-4 rounded-xl border border-emerald-100 shadow-sm relative overflow-hidden group">
                <p class="text-emerald-600 text-[10px] font-bold uppercase tracking-wider mb-1">Amount Collected</p>
                <h2 class="text-xl font-bold text-slate-900 mb-2">GHS <?= number_format($total_revenue, 2) ?></h2>
                <div class="space-y-1 text-[10px] font-semibold text-slate-500">
                    <div class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Fees: GHS <?= number_format($total_student_payments, 2) ?> (<?= number_format(($total_student_payments / ($total_fees ?: 1)) * 100, 1) ?>%)
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                        General: GHS <?= number_format($total_general_payments, 2) ?>
                    </div>
                </div>
                <div class="absolute top-3 right-3 text-emerald-200 group-hover:text-emerald-300 transition-colors">
                    <i class="fas fa-hand-holding-dollar text-2xl"></i>
                </div>
            </div>

            <!-- Outstanding Card -->
            <div class="bg-amber-50/50 p-4 rounded-xl border border-amber-100 shadow-sm relative overflow-hidden group">
                <p class="text-amber-600 text-[10px] font-bold uppercase tracking-wider mb-1">Outstanding Balance</p>
                <h2 class="text-xl font-bold text-slate-900 mb-2">GHS <?= number_format($outstanding, 2) ?></h2>
                <div class="flex items-center gap-1.5 text-[10px] font-semibold text-slate-500">
                    <i class="fas fa-users text-amber-500"></i> <?= $pending_students ?> students still owing
                </div>
                <div class="absolute top-3 right-3 text-amber-200 group-hover:text-amber-300 transition-colors">
                    <i class="fas fa-exclamation-circle text-2xl"></i>
                </div>
            </div>

            <!-- Net Position -->
            <div class="bg-slate-900 p-4 rounded-xl border border-slate-800 shadow-sm relative overflow-hidden group">
                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-1">Net Surplus</p>
                <h2 class="text-xl font-bold mb-2 <?= $net_position >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">GHS <?= number_format($net_position, 2) ?></h2>
                <div class="flex items-center gap-1.5 text-[10px] font-semibold text-slate-400">
                    <i class="fas fa-shield-halved text-slate-500"></i> Income minus Expenses
                </div>
                <div class="absolute top-3 right-3 text-slate-800 group-hover:text-slate-700 transition-colors">
                    <i class="fas fa-chart-line text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Navigation Grid -->
        <h3 class="text-[0.65rem] font-semibold text-slate-500 uppercase tracking-wider mb-4 border-b border-slate-200 pb-2">
            Available Modules
        </h3>

        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
            <!-- Billing Card -->
            <a href="bills/view_semester_bills.php" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:shadow-indigo-500/10 hover:border-indigo-300 hover:-translate-y-1 transition-all duration-300 group flex flex-col justify-between h-full">
                <div>
                    <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center text-lg mb-3 group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1">Billing Center</h4>
                    <p class="text-slate-500 text-xs mb-3 line-clamp-2">Generate, manage, and batch-print semester bills for all students.</p>
                </div>
                <div class="text-indigo-600 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 group-hover:gap-2 transition-all">
                    Manage Bills <i class="fas fa-arrow-right"></i>
                </div>
            </a>
            
            <!-- Payments/Receipts Card -->
            <a href="payments/view_payments.php" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:shadow-emerald-500/10 hover:border-emerald-300 hover:-translate-y-1 transition-all duration-300 group flex flex-col justify-between h-full">
                <div>
                    <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center text-lg mb-3 group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1">Payments & Receipts</h4>
                    <p class="text-slate-500 text-xs mb-3 line-clamp-2">Record fee payments, issue receipts, and track student balances.</p>
                </div>
                <div class="text-emerald-600 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 group-hover:gap-2 transition-all">
                    Record Payment <i class="fas fa-arrow-right"></i>
                </div>
            </a>

            <!-- Expenses Card -->
            <a href="expenses/view_expenses.php" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:shadow-rose-500/10 hover:border-rose-300 hover:-translate-y-1 transition-all duration-300 group flex flex-col justify-between h-full">
                <div>
                    <div class="w-10 h-10 bg-rose-50 text-rose-600 rounded-lg flex items-center justify-center text-lg mb-3 group-hover:bg-rose-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1">Expenditure</h4>
                    <p class="text-slate-500 text-xs mb-3 line-clamp-2">Monitor institutional spending, categorize expenses, and track outflows.</p>
                </div>
                <div class="text-rose-600 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 group-hover:gap-2 transition-all">
                    View Expenses <i class="fas fa-arrow-right"></i>
                </div>
            </a>

            <!-- Invoicing/Billing Options -->
            <a href="fees/view_fees.php" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:shadow-amber-500/10 hover:border-amber-300 hover:-translate-y-1 transition-all duration-300 group flex flex-col justify-between h-full">
                <div>
                    <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center text-lg mb-3 group-hover:bg-amber-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-tags"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1">Invoicing & Pricing</h4>
                    <p class="text-slate-500 text-xs mb-3 line-clamp-2">Define invoice categories, assign tiers to classes, and manage pricing.</p>
                </div>
                <div class="text-amber-600 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 group-hover:gap-2 transition-all">
                    Configure Invoicing <i class="fas fa-arrow-right"></i>
                </div>
            </a>

            <!-- Budgets Card -->
            <a href="budgets/semester_budget.php" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:shadow-purple-500/10 hover:border-purple-300 hover:-translate-y-1 transition-all duration-300 group flex flex-col justify-between h-full">
                <div>
                    <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center text-lg mb-3 group-hover:bg-purple-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-sack-dollar"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1">Semester Budget</h4>
                    <p class="text-slate-500 text-xs mb-3 line-clamp-2">Set up semester budgets and monitor actual vs. planned performance.</p>
                </div>
                <div class="text-purple-600 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 group-hover:gap-2 transition-all">
                    Open Budgets <i class="fas fa-arrow-right"></i>
                </div>
            </a>

            <!-- Settings Card -->
            <a href="settings.php" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:shadow-slate-500/10 hover:border-slate-400 hover:-translate-y-1 transition-all duration-300 group flex flex-col justify-between h-full">
                <div>
                    <div class="w-10 h-10 bg-slate-100 text-slate-600 rounded-lg flex items-center justify-center text-lg mb-3 group-hover:bg-slate-700 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-sliders"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1">Settings</h4>
                    <p class="text-slate-500 text-xs mb-3 line-clamp-2">Configure currency, late fee rules, and global billing footer details.</p>
                </div>
                <div class="text-slate-600 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 group-hover:gap-2 transition-all">
                    Edit Settings <i class="fas fa-arrow-right"></i>
                </div>
            </a>

            <!-- Payroll Card -->
            <a href="payroll/index.php" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:shadow-sky-500/10 hover:border-sky-300 hover:-translate-y-1 transition-all duration-300 group flex flex-col justify-between h-full">
                <div>
                    <div class="w-10 h-10 bg-sky-50 text-sky-600 rounded-lg flex items-center justify-center text-lg mb-3 group-hover:bg-sky-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-money-check-dollar"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1">Payroll & Salaries</h4>
                    <p class="text-slate-500 text-xs mb-3 line-clamp-2">Manage staff salary structures, SSNIT deductions, and generate payslips.</p>
                </div>
                <div class="text-sky-600 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 group-hover:gap-2 transition-all">
                    Open Payroll <i class="fas fa-arrow-right"></i>
                </div>
            </a>

            <!-- Financial Reporting Card -->
            <a href="reports/report.php" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:shadow-fuchsia-500/10 hover:border-fuchsia-300 hover:-translate-y-1 transition-all duration-300 group flex flex-col justify-between h-full">
                <div>
                    <div class="w-10 h-10 bg-fuchsia-50 text-fuchsia-600 rounded-lg flex items-center justify-center text-lg mb-3 group-hover:bg-fuchsia-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1">Financial Reporting</h4>
                    <p class="text-slate-500 text-xs mb-3 line-clamp-2">Generate custom data extracts, payment histories, and overall financial summaries.</p>
                </div>
                <div class="text-fuchsia-600 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 group-hover:gap-2 transition-all">
                    Open Intelligence <i class="fas fa-arrow-right"></i>
                </div>
            </a>

            <!-- Student Balances Card -->
            <a href="reports/student_balances.php" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:shadow-emerald-500/10 hover:border-emerald-300 hover:-translate-y-1 transition-all duration-300 group flex flex-col justify-between h-full">
                <div>
                    <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center text-lg mb-3 group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-users-between-lines"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1">Student Balances</h4>
                    <p class="text-slate-500 text-xs mb-3 line-clamp-2">View per-student fee balances, outstanding debts, and payment progress across all classes.</p>
                </div>
                <div class="text-emerald-600 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 group-hover:gap-2 transition-all">
                    View Balances <i class="fas fa-arrow-right"></i>
                </div>
            </a>

            <!-- Waivers Card -->
            <a href="waivers/index.php" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:shadow-pink-500/10 hover:border-pink-300 hover:-translate-y-1 transition-all duration-300 group flex flex-col justify-between h-full">
                <div>
                    <div class="w-10 h-10 bg-pink-50 text-pink-600 rounded-lg flex items-center justify-center text-lg mb-3 group-hover:bg-pink-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-hand-holding-dollar"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1">Waivers & Scholarships</h4>
                    <p class="text-slate-500 text-xs mb-3 line-clamp-2">Manage staff ward discounts, academic scholarships, and apply fee waivers.</p>
                </div>
                <div class="text-pink-600 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 group-hover:gap-2 transition-all">
                    Manage Waivers <i class="fas fa-arrow-right"></i>
                </div>
            </a>

            <!-- Petty Cash Card -->
            <a href="petty_cash/index.php" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:shadow-orange-500/10 hover:border-orange-300 hover:-translate-y-1 transition-all duration-300 group flex flex-col justify-between h-full">
                <div>
                    <div class="w-10 h-10 bg-orange-50 text-orange-600 rounded-lg flex items-center justify-center text-lg mb-3 group-hover:bg-orange-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1">Petty Cash</h4>
                    <p class="text-slate-500 text-xs mb-3 line-clamp-2">Log daily minor disbursements securely and upload receipts instantly.</p>
                </div>
                <div class="text-orange-600 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 group-hover:gap-2 transition-all">
                    Manage Petty Cash <i class="fas fa-arrow-right"></i>
                </div>
            </a>

            <!-- Accounting Ledger Card -->
            <a href="accounting/index.php" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:shadow-teal-500/10 hover:border-teal-300 hover:-translate-y-1 transition-all duration-300 group flex flex-col justify-between h-full">
                <div>
                    <div class="w-10 h-10 bg-teal-50 text-teal-600 rounded-lg flex items-center justify-center text-lg mb-3 group-hover:bg-teal-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-book-journal-whills"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1">Accounting Ledger</h4>
                    <p class="text-slate-500 text-xs mb-3 line-clamp-2">Access the Double-Entry General Journal, Trial Balance, and Financial Statements.</p>
                </div>
                <div class="text-teal-600 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 group-hover:gap-2 transition-all">
                    Open Ledger <i class="fas fa-arrow-right"></i>
                </div>
            </a>
        </div>

        <footer class="mt-20 py-10 border-t border-slate-200 flex justify-between items-center">
            <p class="text-[0.625rem] font-black text-slate-300 uppercase tracking-[0.5em]">Salba Montessori Financial ERP &middot; v9.5.0</p>
            <div class="flex gap-4">
                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-xs hover:bg-emerald-600 hover:text-white cursor-help transition-all">
                    <i class="fas fa-question"></i>
                </div>
            </div>
        </footer>
    </main>
</body>
</html>
