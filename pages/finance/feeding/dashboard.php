<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/semester_helpers.php';

if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
require_finance_access();

$current_semester = getCurrentSemester($conn);
$acad_year = getAcademicYear($conn);

function table_exists($conn, $table_name) {
    $escaped = $conn->real_escape_string($table_name);
    $sql = "SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$escaped'";
    $res = $conn->query($sql);
    if (!$res) {
        return false;
    }
    return ((int)($res->fetch_assoc()['c'] ?? 0)) > 0;
}

function scalar($conn, $sql, $default = 0) {
    $res = $conn->query($sql);
    if (!$res) {
        return $default;
    }
    $row = $res->fetch_row();
    return $row ? $row[0] : $default;
}

$has_plan_table = table_exists($conn, 'student_daily_weekly_feeding');
$has_payments_table = table_exists($conn, 'feeding_payments');
$has_rates_table = table_exists($conn, 'feeding_class_rates');
$schema_ready = $has_plan_table && $has_payments_table;

$active_learners = 0;
$weekly_count = 0;
$monthly_count = 0;
$active_plans = 0;
$today_collected = 0;
$month_collected = 0;

if ($schema_ready) {
    $active_learners = (int)scalar($conn, "
        SELECT COUNT(*)
        FROM student_daily_weekly_feeding
        WHERE academic_year = '$acad_year'
          AND semester = '$current_semester'
          AND status = 'active'
    ");

    $weekly_count = (int)scalar($conn, "
        SELECT COUNT(*)
        FROM student_daily_weekly_feeding
        WHERE academic_year = '$acad_year'
          AND semester = '$current_semester'
          AND status = 'active'
          AND plan_type = 'weekly'
    ");

    $monthly_count = (int)scalar($conn, "
        SELECT COUNT(*)
        FROM student_daily_weekly_feeding
        WHERE academic_year = '$acad_year'
          AND semester = '$current_semester'
          AND status = 'active'
          AND plan_type = 'monthly'
    ");

    $active_plans = $weekly_count + $monthly_count;

    $today_collected = (float)scalar($conn, "
        SELECT COALESCE(SUM(amount), 0)
        FROM feeding_payments
        WHERE payment_date = CURDATE()
    ", 0);

    $month_collected = (float)scalar($conn, "
        SELECT COALESCE(SUM(amount), 0)
        FROM feeding_payments
        WHERE YEAR(payment_date) = YEAR(CURDATE())
          AND MONTH(payment_date) = MONTH(CURDATE())
    ", 0);
}

$active_rates = 0;
if ($has_rates_table) {
    $active_rates = (int)scalar($conn, "SELECT COUNT(*) FROM feeding_class_rates WHERE is_active = 1");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feeding Dashboard | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-[#F8FAFC] text-slate-900 min-h-screen">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-emerald-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <span class="text-emerald-600">Feeding Dashboard</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-utensils text-orange-600"></i> Feeding Dashboard
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Operational hub for class rates, weekly/monthly marking, and feeding collections.</p>
                </div>
                <div class="bg-white px-4 py-2 rounded-lg shadow-sm border border-slate-200">
                    <p class="text-[0.65rem] font-semibold text-slate-400 uppercase tracking-wider leading-none mb-1">Current Session</p>
                    <p class="text-sm font-medium text-slate-700"><?= htmlspecialchars($current_semester) ?> | <?= htmlspecialchars($acad_year) ?></p>
                </div>
            </div>
        </div>

        <?php if (!$schema_ready): ?>
            <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl">
                <p class="text-sm font-semibold">Feeding tables are not fully ready yet.</p>
                <p class="text-xs mt-1">Run the DB patch script to create missing feeding tables before using this module fully.</p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <p class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Active Learners</p>
                <p class="text-2xl font-black text-slate-900 mt-2"><?= $active_learners ?></p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <p class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Weekly/Monthly Plans</p>
                <p class="text-2xl font-black text-blue-600 mt-2"><?= $active_plans ?></p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <p class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Today Collected</p>
                <p class="text-2xl font-black text-orange-600 mt-2">GHS <?= number_format($today_collected, 2) ?></p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <p class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Active Class Rates</p>
                <p class="text-2xl font-black text-slate-900 mt-2"><?= $active_rates ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <a href="enroll_daily_feeding.php" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 mb-1">Register for Feeding</h3>
                <p class="text-xs text-slate-500">Add a student into the feeding tables for the current semester.</p>
            </a>

            <a href="feeding_settings.php" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:border-emerald-300 transition-all">
                <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-sliders"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 mb-1">Feeding Settings</h3>
                <p class="text-xs text-slate-500">Configure class-based feeding amounts for weekly and monthly plans.</p>
            </a>

            <a href="daily_weekly_tracker.php" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:border-orange-300 transition-all">
                <div class="w-10 h-10 bg-orange-50 text-orange-600 rounded-lg flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-list-check"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 mb-1">Marking Tracker</h3>
                <p class="text-xs text-slate-500">View active feeding learners and monitor collection activity by plan type.</p>
            </a>

            <a href="registered_learners.php" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all">
                <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 mb-1">Registered Learners</h3>
                <p class="text-xs text-slate-500">See all students currently registered for weekly/monthly feeding mode.</p>
            </a>

            <a href="record_feeding_payment.php" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-receipt"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 mb-1">Record Payment</h3>
                <p class="text-xs text-slate-500">Capture lump-sum or routine feeding payments with method and notes.</p>
            </a>

            <a href="feeding_summary_report.php" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all">
                <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-chart-column"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 mb-1">Summary Report</h3>
                <p class="text-xs text-slate-500">Review totals, plan distribution, and recent feeding transactions.</p>
            </a>

            <a href="closeout_history.php" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:border-rose-300 transition-all">
                <div class="w-10 h-10 bg-rose-50 text-rose-600 rounded-lg flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-clock-rotate-left"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 mb-1">Closeout History</h3>
                <p class="text-xs text-slate-500">Audit locked registers, reopen events, and feeding reconciliation results.</p>
            </a>

            <a href="daily_closeout_slip.php" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:border-slate-400 transition-all">
                <div class="w-10 h-10 bg-slate-100 text-slate-700 rounded-lg flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-receipt"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 mb-1">Closeout Slip</h3>
                <p class="text-xs text-slate-500">Open a printable reconciliation slip by class and date.</p>
            </a>
        </div>

        <div class="mt-8 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider mb-2">This Month</h2>
            <p class="text-3xl font-black text-slate-900">GHS <?= number_format($month_collected, 2) ?></p>
            <p class="text-xs text-slate-500 mt-1">Total feeding collections recorded in the current calendar month.</p>
        </div>
    </main>
</body>
</html>
