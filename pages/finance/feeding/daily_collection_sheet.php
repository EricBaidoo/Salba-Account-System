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
$selected_class = trim($_GET['class'] ?? '');
$selected_date = trim($_GET['date'] ?? date('Y-m-d'));

function feed_table_exists($conn, $table_name) {
    $escaped = $conn->real_escape_string($table_name);
    $sql = "SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$escaped'";
    $res = $conn->query($sql);
    if (!$res) {
        return false;
    }
    return ((int)($res->fetch_assoc()['c'] ?? 0)) > 0;
}

function feed_has_column($conn, $table_name, $column_name) {
    $table_name = $conn->real_escape_string($table_name);
    $column_name = $conn->real_escape_string($column_name);
    $sql = "SELECT COUNT(*) as c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_name' AND COLUMN_NAME = '$column_name'";
    $res = $conn->query($sql);
    if (!$res) {
        return false;
    }
    return ((int)($res->fetch_assoc()['c'] ?? 0)) > 0;
}

$has_plan_table = feed_table_exists($conn, 'student_daily_weekly_feeding');
$has_payments_table = feed_table_exists($conn, 'feeding_payments');
$schema_ready = $has_plan_table && $has_payments_table;
$has_rates_table = feed_table_exists($conn, 'feeding_class_rates');
$has_payment_method = $schema_ready && feed_has_column($conn, 'feeding_payments', 'payment_method');
$has_month_no = $schema_ready && feed_has_column($conn, 'feeding_payments', 'month_no');
$has_months_count = $schema_ready && feed_has_column($conn, 'feeding_payments', 'months_count');

$class_options = [];
$learners = [];

if ($schema_ready) {
    $stmt = $conn->prepare("SELECT DISTINCT s.class
                            FROM student_daily_weekly_feeding dwf
                            JOIN students s ON s.id = dwf.student_id
                            WHERE dwf.academic_year = ? AND dwf.semester = ? AND dwf.status = 'active' AND s.status = 'active' AND s.class IS NOT NULL AND s.class != ''
                            ORDER BY s.class");
    $stmt->bind_param('ss', $acad_year, $current_semester);
    $stmt->execute();
    $class_options = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($selected_class === '' && !empty($class_options)) {
        $selected_class = $class_options[0]['class'];
    }

    if ($selected_class !== '') {
        $expected_amount_select = 'dwf.amount_per_unit';
        if ($has_rates_table) {
            $expected_amount_select = "COALESCE((
                    SELECT fcr.amount
                    FROM feeding_class_rates fcr
                    WHERE fcr.class_name = s.class
                        AND fcr.plan_type = dwf.plan_type
                        AND fcr.is_active = 1
                        AND fcr.effective_from <= ?
                    ORDER BY fcr.effective_from DESC, fcr.id DESC
                    LIMIT 1
            ), dwf.amount_per_unit)";
        }

        $payment_method_select = $has_payment_method ? 'fp.payment_method' : "'cash' AS payment_method";
        $month_no_select = $has_month_no ? 'fp.month_no' : 'NULL AS month_no';
        $months_count_select = $has_months_count ? 'fp.months_count' : '1 AS months_count';

        $sql = "SELECT
                    s.first_name,
                    s.last_name,
                    CONCAT('SMS-', LPAD(s.id, 3, '0')) AS student_code,
                    s.class as class_name,
                    dwf.plan_type,
                    $expected_amount_select AS expected_amount,
                    dwf.amount_per_unit,
                    dwf.notes,
                    fp.amount as paid_amount,
                    $payment_method_select,
                    fp.notes as payment_notes,
                    $month_no_select,
                    $months_count_select
                FROM student_daily_weekly_feeding dwf
                JOIN students s ON s.id = dwf.student_id
                LEFT JOIN feeding_payments fp ON fp.student_id = s.id
                    AND fp.student_feeding_plan_id = dwf.id
                    AND fp.payment_date = ?
                    AND fp.payment_type = dwf.plan_type
                WHERE dwf.academic_year = ?
                    AND dwf.semester = ?
                    AND dwf.status = 'active'
                    AND s.status = 'active'
                    AND s.class = ?
                ORDER BY s.first_name, s.last_name";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($has_rates_table) {
                $stmt->bind_param('sssss', $selected_date, $selected_date, $acad_year, $current_semester, $selected_class);
            } else {
                $stmt->bind_param('ssss', $selected_date, $acad_year, $current_semester, $selected_class);
            }
            $stmt->execute();
            $learners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}

$class_label = $selected_class !== '' ? $selected_class : 'All Classes';
$print_total = count($learners);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feeding Collection Sheet | Salba</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            main { margin: 0 !important; padding: 0 !important; }
            .sheet-page { box-shadow: none !important; border: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen">
    <main class="max-w-6xl mx-auto p-4 md:p-8">
        <div class="no-print mb-4 flex items-center justify-between gap-3">
            <div>
                <a href="daily_weekly_tracker.php?class=<?= urlencode($selected_class) ?>&date=<?= urlencode($selected_date) ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 hover:text-slate-900">
                    <i class="fas fa-arrow-left"></i> Back to register
                </a>
            </div>
            <button type="button" onclick="window.print()" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800">
                <i class="fas fa-print mr-2"></i> Print
            </button>
        </div>

        <?php if (!$schema_ready): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-sm font-semibold no-print">
                Feeding tables are not ready yet. Run the DB patch, then refresh this page.
            </div>
        <?php endif; ?>

        <section class="sheet-page bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="bg-slate-900 text-white px-6 py-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-slate-300">Feeding Collection Sheet</p>
                        <h1 class="text-2xl font-bold mt-2">Feeding Collection Sheet</h1>
                        <p class="text-sm text-slate-300 mt-1">Class: <?= htmlspecialchars($class_label) ?> | Date: <?= htmlspecialchars(date('D, M d, Y', strtotime($selected_date))) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Academic Year</p>
                        <p class="text-sm font-semibold mt-1"><?= htmlspecialchars($acad_year) ?> / <?= htmlspecialchars($current_semester) ?></p>
                        <p class="text-xs text-slate-400 mt-3">Total learners: <?= (int)$print_total ?></p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5 text-sm">
                    <div class="rounded-xl border border-slate-200 p-3">
                        <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest">Collection Notes</p>
                        <p class="mt-2 text-slate-700">Use this sheet for manual roll call and on-the-spot payment capture.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-3">
                        <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest">Payment Rule</p>
                        <p class="mt-2 text-slate-700">Weekly learners can pay in installments or in lumps; monthly learners must specify month and months count.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-3">
                        <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest">Legend</p>
                        <p class="mt-2 text-slate-700">Tick the box, write amount, method, and notes for each learner.</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <table class="w-full border-collapse">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="border border-slate-300 px-3 py-2 text-left text-xs font-black uppercase tracking-wider w-12">#</th>
                                <th class="border border-slate-300 px-3 py-2 text-left text-xs font-black uppercase tracking-wider">Learner</th>
                                <th class="border border-slate-300 px-3 py-2 text-left text-xs font-black uppercase tracking-wider w-24">Code</th>
                                <th class="border border-slate-300 px-3 py-2 text-left text-xs font-black uppercase tracking-wider w-20">Plan</th>
                                <th class="border border-slate-300 px-3 py-2 text-left text-xs font-black uppercase tracking-wider w-24">Expected</th>
                                <th class="border border-slate-300 px-3 py-2 text-left text-xs font-black uppercase tracking-wider w-28">Paid</th>
                                <th class="border border-slate-300 px-3 py-2 text-left text-xs font-black uppercase tracking-wider w-24">Method</th>
                                <th class="border border-slate-300 px-3 py-2 text-left text-xs font-black uppercase tracking-wider w-20">Month</th>
                                <th class="border border-slate-300 px-3 py-2 text-left text-xs font-black uppercase tracking-wider w-20">Months</th>
                                <th class="border border-slate-300 px-3 py-2 text-left text-xs font-black uppercase tracking-wider">Remarks</th>
                                <th class="border border-slate-300 px-3 py-2 text-center text-xs font-black uppercase tracking-wider w-20">Tick</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($learners)): ?>
                                <tr>
                                    <td colspan="11" class="border border-slate-200 px-4 py-10 text-center text-slate-500">No learners found for this class/date context.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($learners as $index => $row): ?>
                                    <?php
                                        $paid_amount = isset($row['paid_amount']) ? (float)$row['paid_amount'] : null;
                                        $expected_amount = (float)($row['expected_amount'] ?? 0);
                                        $payment_method = $row['payment_method'] ?? '';
                                        $month_no = (int)($row['month_no'] ?? 0);
                                        $months_count = (int)($row['months_count'] ?? 1);
                                    ?>
                                    <tr class="align-top">
                                        <td class="border border-slate-200 px-3 py-3 text-sm"><?= $index + 1 ?></td>
                                        <td class="border border-slate-200 px-3 py-3">
                                            <div class="font-semibold text-slate-900"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                            <div class="text-xs text-slate-500"><?= htmlspecialchars($row['class_name'] ?? '') ?></div>
                                        </td>
                                        <td class="border border-slate-200 px-3 py-3 text-sm font-medium"><?= htmlspecialchars($row['student_code']) ?></td>
                                        <td class="border border-slate-200 px-3 py-3 text-sm"><?= htmlspecialchars(ucfirst($row['plan_type'])) ?></td>
                                        <td class="border border-slate-200 px-3 py-3 text-sm font-semibold">GHS <?= number_format($expected_amount, 2) ?></td>
                                        <td class="border border-slate-200 px-3 py-3 text-sm">&nbsp;</td>
                                        <td class="border border-slate-200 px-3 py-3 text-sm">&nbsp;</td>
                                        <td class="border border-slate-200 px-3 py-3 text-sm">&nbsp;</td>
                                        <td class="border border-slate-200 px-3 py-3 text-sm">&nbsp;</td>
                                        <td class="border border-slate-200 px-3 py-3 text-sm">&nbsp;</td>
                                        <td class="border border-slate-200 px-3 py-3 text-center">
                                            <span class="inline-flex h-5 w-5 items-center justify-center border border-slate-400"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="11" class="border border-slate-200 px-3 py-2 text-xs text-slate-500">
                                            Paid amount: <?= $paid_amount !== null ? 'GHS ' . number_format($paid_amount, 2) : '________' ?>
                                            | Method: <?= htmlspecialchars($payment_method ?: '________') ?>
                                            | Month: <?= $month_no > 0 ? date('F', mktime(0, 0, 0, $month_no, 1)) : '________' ?>
                                            | Months count: <?= $months_count > 0 ? (string)$months_count : '________' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-5 text-sm">
                    <div class="rounded-xl border border-slate-200 p-3 min-h-[96px]">
                        <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest">Collected By</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-3 min-h-[96px]">
                        <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest">Teacher/Assistant</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-3 min-h-[96px]">
                        <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest">End-of-day Check</p>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>