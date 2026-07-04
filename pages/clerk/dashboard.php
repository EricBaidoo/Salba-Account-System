<?php
include '../../includes/auth_check.php';
include '../../includes/db_connect.php';
include '../../includes/system_settings.php';

if (!in_array(($_SESSION['role'] ?? ''), ['admin', 'data_entry'])) {
    header('Location: ../../index'); exit;
}

$user_name   = $_SESSION['username'] ?? 'Clerk';
$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');

// ── Quick stats ────────────────────────────────────────────────────────
// Payments recorded today
$payments_today = $conn->query("SELECT COUNT(*) as c FROM payments WHERE DATE(payment_date)=CURDATE()")->fetch_assoc()['c'] ?? 0;
// Expenses recorded today
$expenses_today = $conn->query("SELECT COUNT(*) as c FROM expenses WHERE DATE(expense_date)=CURDATE()")->fetch_assoc()['c'] ?? 0;
// Unpaid student fees count
$unpaid_fees    = $conn->query("SELECT COUNT(*) as c FROM student_fees WHERE status IN ('pending','due','overdue')")->fetch_assoc()['c'] ?? 0;
// Stationery pending (not brought, not billed)
$stat_pending   = $conn->query("SELECT COUNT(*) as c FROM stationery_submissions WHERE brought=0 AND billed=0")->fetch_assoc()['c'] ?? 0;
$stat_pending   = $stat_pending ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Entry Dashboard | <?= htmlspecialchars($school_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-900">
<?php include '../../includes/sidebar_data_entry.php'; ?>

<main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">

    <!-- Header -->
    <header class="mb-8">
        <p class="text-xs font-bold text-amber-500 uppercase tracking-widest mb-1">Data Entry Portal</p>
        <h1 class="text-3xl font-black text-slate-900">Welcome, <span class="text-amber-500"><?= htmlspecialchars($user_name) ?></span></h1>
        <p class="text-slate-500 mt-1 text-sm"><?= htmlspecialchars($school_name) ?> &mdash; <?= date('l, d F Y') ?></p>
    </header>

    <!-- Stats Row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1">Payments Today</p>
            <p class="text-3xl font-black text-emerald-600"><?= $payments_today ?></p>
            <p class="text-xs text-slate-400 mt-1">recorded today</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1">Expenses Today</p>
            <p class="text-3xl font-black text-rose-600"><?= $expenses_today ?></p>
            <p class="text-xs text-slate-400 mt-1">recorded today</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1">Unpaid Fees</p>
            <p class="text-3xl font-black text-orange-600"><?= $unpaid_fees ?></p>
            <p class="text-xs text-slate-400 mt-1">student fee rows</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1">Stationery Pending</p>
            <p class="text-3xl font-black text-violet-600"><?= $stat_pending ?></p>
            <p class="text-xs text-slate-400 mt-1">items not brought</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <h2 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4">Quick Actions</h2>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">

        <a href="../finance/payments/record_payment_form.php" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 hover:border-emerald-300 hover:shadow-md transition-all group">
            <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                <i class="fas fa-money-bill-wave text-white"></i>
            </div>
            <h3 class="font-black text-slate-900 text-sm mb-1">Record Payment</h3>
            <p class="text-xs text-slate-500 hidden sm:block">Post a student fee payment.</p>
            <span class="inline-flex items-center gap-1 text-emerald-600 text-xs font-black mt-2">Go <i class="fas fa-arrow-right text-[0.6rem]"></i></span>
        </a>

        <a href="../finance/expenses/add_expense_form.php" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 hover:border-rose-300 hover:shadow-md transition-all group">
            <div class="w-10 h-10 bg-rose-600 rounded-xl flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                <i class="fas fa-receipt text-white"></i>
            </div>
            <h3 class="font-black text-slate-900 text-sm mb-1">Record Expense</h3>
            <p class="text-xs text-slate-500 hidden sm:block">Log a school expense.</p>
            <span class="inline-flex items-center gap-1 text-rose-600 text-xs font-black mt-2">Go <i class="fas fa-arrow-right text-[0.6rem]"></i></span>
        </a>

        <a href="../administration/stationery/index.php" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 hover:border-violet-300 hover:shadow-md transition-all group">
            <div class="w-10 h-10 bg-violet-600 rounded-xl flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                <i class="fas fa-table-cells text-white"></i>
            </div>
            <h3 class="font-black text-slate-900 text-sm mb-1">Stationery Tracker</h3>
            <p class="text-xs text-slate-500 hidden sm:block">Mark brought &amp; bill missing items.</p>
            <span class="inline-flex items-center gap-1 text-violet-600 text-xs font-black mt-2">Go <i class="fas fa-arrow-right text-[0.6rem]"></i></span>
        </a>

        <a href="../finance/reports/student_balances.php" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 hover:border-amber-300 hover:shadow-md transition-all group">
            <div class="w-10 h-10 bg-amber-500 rounded-xl flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                <i class="fas fa-scale-balanced text-white"></i>
            </div>
            <h3 class="font-black text-slate-900 text-sm mb-1">Student Balances</h3>
            <p class="text-xs text-slate-500 hidden sm:block">View outstanding balances.</p>
            <span class="inline-flex items-center gap-1 text-amber-600 text-xs font-black mt-2">View <i class="fas fa-arrow-right text-[0.6rem]"></i></span>
        </a>

    </div>

    <!-- Recent Payments -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-clock-rotate-left text-slate-400"></i> Recent Payments
            </h2>
            <a href="../finance/payments/view_payments.php" class="text-xs font-black text-indigo-600 hover:underline">View all</a>
        </div>
        <?php
        $recent = $conn->query("SELECT p.id, p.amount, p.payment_date, p.payment_type,
                p.receipt_no, s.first_name, s.last_name, s.class
            FROM payments p
            LEFT JOIN students s ON p.student_id = s.id
            ORDER BY p.payment_date DESC, p.id DESC LIMIT 8");
        ?>
        <?php if ($recent && $recent->num_rows > 0): ?>
        <div class="divide-y divide-slate-50">
            <?php while ($r = $recent->fetch_assoc()): ?>
            <div class="px-6 py-3 flex items-center justify-between hover:bg-slate-50 transition-colors">
                <div>
                    <p class="text-sm font-bold text-slate-800"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></p>
                    <p class="text-xs text-slate-400"><?= htmlspecialchars($r['class'] ?? '') ?> &bull; <?= date('d M Y', strtotime($r['payment_date'])) ?></p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-black text-emerald-600">GH&#8373;<?= number_format($r['amount'], 2) ?></p>
                    <p class="text-xs text-slate-400 capitalize"><?= htmlspecialchars($r['payment_type'] ?? '') ?></p>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="px-6 py-10 text-center text-slate-400 text-sm">No payments recorded yet.</div>
        <?php endif; ?>
    </div>

</main>
</body>
</html>
