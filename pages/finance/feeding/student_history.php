<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/feeding_helpers.php';

if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
require_finance_access();

$current_semester = getCurrentSemester($conn);
$acad_year = getAcademicYear($conn);
$student_id = (int)($_GET['student_id'] ?? 0);
$selected_class = trim($_GET['class'] ?? '');
$selected_date = trim($_GET['date'] ?? date('Y-m-d'));
$history_type = strtolower(trim($_GET['history_type'] ?? 'all'));
if (!in_array($history_type, ['all', 'weekly', 'monthly'], true)) {
    $history_type = 'all';
}
$week_filter = trim((string)($_GET['week_no'] ?? 'all'));

function history_table_exists($conn, $table_name) {
    $escaped = $conn->real_escape_string($table_name);
    $sql = "SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$escaped'";
    $res = $conn->query($sql);
    if (!$res) {
        return false;
    }
    return ((int)($res->fetch_assoc()['c'] ?? 0)) > 0;
}

function history_has_column($conn, $table_name, $column_name) {
    $table_name = $conn->real_escape_string($table_name);
    $column_name = $conn->real_escape_string($column_name);
    $sql = "SELECT COUNT(*) as c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_name' AND COLUMN_NAME = '$column_name'";
    $res = $conn->query($sql);
    if (!$res) {
        return false;
    }
    return ((int)($res->fetch_assoc()['c'] ?? 0)) > 0;
}

$has_plan_table = history_table_exists($conn, 'student_daily_weekly_feeding');
$has_payments_table = history_table_exists($conn, 'feeding_payments');
$schema_ready = $has_plan_table && $has_payments_table;

$has_payment_method = $schema_ready && history_has_column($conn, 'feeding_payments', 'payment_method');
$has_month_no = $schema_ready && history_has_column($conn, 'feeding_payments', 'month_no');
$has_months_count = $schema_ready && history_has_column($conn, 'feeding_payments', 'months_count');

$weeks_total = max(1, (int)getSystemSetting($conn, 'weeks_per_semester', 12));
$semester_start_setting = getSystemSetting($conn, 'semester_start_date', '');
$semester_end_setting = getSystemSetting($conn, 'semester_end_date', '');
$semester_start_monday = null;
$semester_end_dt = null;
$week_options = [];

if ($semester_start_setting) {
    try {
        $semester_start_monday = new DateTime($semester_start_setting);
        $semester_start_monday->modify('monday this week');
        if ($semester_end_setting) {
            $semester_end_dt = new DateTime($semester_end_setting);
        }

        for ($w = 1; $w <= $weeks_total; $w++) {
            $ws = clone $semester_start_monday;
            $ws->modify('+' . ($w - 1) . ' week');
            $we = clone $ws;
            $we->modify('+4 days');
            if ($semester_end_dt && $we > $semester_end_dt) {
                $we = clone $semester_end_dt;
            }

            $week_options[$w] = [
                'week_no' => $w,
                'week_start' => $ws->format('Y-m-d'),
                'week_end' => $we->format('Y-m-d'),
                'label' => sprintf('Week %d (%s - %s)', $w, $ws->format('D j M'), $we->format('D j M')),
            ];
        }
    } catch (Exception $e) {
        $semester_start_monday = null;
        $week_options = [];
    }
}

$selected_week_no = 0;
if ($week_filter !== 'all') {
    $candidate_week_no = (int)$week_filter;
    if ($candidate_week_no > 0 && isset($week_options[$candidate_week_no])) {
        $selected_week_no = $candidate_week_no;
    }
}

$selected_week_start = null;
$selected_week_end = null;
$selected_week_label = 'All Weeks';
if ($selected_week_no > 0) {
    $selected_week_start = $week_options[$selected_week_no]['week_start'];
    $selected_week_end = $week_options[$selected_week_no]['week_end'];
    $selected_week_label = $week_options[$selected_week_no]['label'];
}

$history_student = null;
$student_history = [];
$history_notice = '';
$history_totals = [
    'weekly' => 0,
    'monthly' => 0,
    'all' => 0,
];
$weekly_by_week = [];
$selected_week_total = 0;

if ($schema_ready && $student_id > 0) {
    $profile_stmt = $conn->prepare("SELECT id, first_name, last_name, class, CONCAT('SMS-', LPAD(id, 3, '0')) AS student_code FROM students WHERE id = ? LIMIT 1");
    if ($profile_stmt) {
        $profile_stmt->bind_param('i', $student_id);
        $profile_stmt->execute();
        $history_student = $profile_stmt->get_result()->fetch_assoc();
        $profile_stmt->close();
    }

    if ($history_student) {
        $history_method_select = $has_payment_method ? 'fp.payment_method' : "'cash' AS payment_method";
        $history_month_select = $has_month_no ? 'fp.month_no' : 'NULL AS month_no';
        $history_months_count_select = $has_months_count ? 'fp.months_count' : '1 AS months_count';

        if ($history_type === 'all') {
            $history_sql = "SELECT fp.payment_date, fp.payment_type, fp.amount, $history_method_select, $history_month_select, $history_months_count_select, fp.notes
                            FROM feeding_payments fp
                            JOIN student_daily_weekly_feeding dwf ON dwf.id = fp.student_feeding_plan_id
                            WHERE fp.student_id = ?
                              AND dwf.academic_year = ?
                              AND dwf.semester = ?
                            ORDER BY fp.payment_date DESC, fp.id DESC
                            LIMIT 120";
            $history_stmt = $conn->prepare($history_sql);
            if ($history_stmt) {
                $history_stmt->bind_param('iss', $student_id, $acad_year, $current_semester);
                $history_stmt->execute();
                $student_history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $history_stmt->close();
            }
        } elseif ($history_type === 'weekly' && $selected_week_no > 0 && $selected_week_start && $selected_week_end) {
            $history_sql = "SELECT fp.payment_date, fp.payment_type, fp.amount, $history_method_select, $history_month_select, $history_months_count_select, fp.notes
                            FROM feeding_payments fp
                            JOIN student_daily_weekly_feeding dwf ON dwf.id = fp.student_feeding_plan_id
                            WHERE fp.student_id = ?
                              AND dwf.academic_year = ?
                              AND dwf.semester = ?
                              AND fp.payment_type = 'weekly'
                              AND fp.payment_date BETWEEN ? AND ?
                            ORDER BY fp.payment_date DESC, fp.id DESC
                            LIMIT 120";
            $history_stmt = $conn->prepare($history_sql);
            if ($history_stmt) {
                $history_stmt->bind_param('issss', $student_id, $acad_year, $current_semester, $selected_week_start, $selected_week_end);
                $history_stmt->execute();
                $student_history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $history_stmt->close();
            }
        } else {
            $history_sql = "SELECT fp.payment_date, fp.payment_type, fp.amount, $history_method_select, $history_month_select, $history_months_count_select, fp.notes
                            FROM feeding_payments fp
                            JOIN student_daily_weekly_feeding dwf ON dwf.id = fp.student_feeding_plan_id
                            WHERE fp.student_id = ?
                              AND dwf.academic_year = ?
                              AND dwf.semester = ?
                              AND fp.payment_type = ?
                            ORDER BY fp.payment_date DESC, fp.id DESC
                            LIMIT 120";
            $history_stmt = $conn->prepare($history_sql);
            if ($history_stmt) {
                $history_stmt->bind_param('isss', $student_id, $acad_year, $current_semester, $history_type);
                $history_stmt->execute();
                $student_history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $history_stmt->close();
            }
        }

        // Weekly per-week summary for this semester (used for quick week comparison).
        if (!empty($week_options)) {
            $weekly_summary_stmt = $conn->prepare("SELECT fp.payment_date, fp.amount
                                                  FROM feeding_payments fp
                                                  JOIN student_daily_weekly_feeding dwf ON dwf.id = fp.student_feeding_plan_id
                                                  WHERE fp.student_id = ?
                                                    AND dwf.academic_year = ?
                                                    AND dwf.semester = ?
                                                    AND fp.payment_type = 'weekly'
                                                  ORDER BY fp.payment_date ASC, fp.id ASC");
            if ($weekly_summary_stmt) {
                $weekly_summary_stmt->bind_param('iss', $student_id, $acad_year, $current_semester);
                $weekly_summary_stmt->execute();
                $weekly_rows = $weekly_summary_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $weekly_summary_stmt->close();

                foreach ($week_options as $wk_no => $wk_meta) {
                    $weekly_by_week[$wk_no] = [
                        'label' => $wk_meta['label'],
                        'amount' => 0,
                        'payments_count' => 0,
                    ];
                }

                foreach ($weekly_rows as $wrow) {
                    $payment_date = trim((string)($wrow['payment_date'] ?? ''));
                    $amount = (float)($wrow['amount'] ?? 0);
                    if ($payment_date === '' || !$semester_start_monday) {
                        continue;
                    }
                    try {
                        $payment_dt = new DateTime($payment_date);
                        $payment_monday = clone $payment_dt;
                        $payment_monday->modify('monday this week');

                        $interval = $semester_start_monday->diff($payment_monday);
                        $week_no = $interval->invert ? 1 : ((int)floor($interval->days / 7) + 1);
                        $week_no = max(1, min($week_no, $weeks_total));

                        if (!isset($weekly_by_week[$week_no])) {
                            $weekly_by_week[$week_no] = [
                                'label' => 'Week ' . $week_no,
                                'amount' => 0,
                                'payments_count' => 0,
                            ];
                        }
                        $weekly_by_week[$week_no]['amount'] += $amount;
                        $weekly_by_week[$week_no]['payments_count']++;
                    } catch (Exception $e) {
                        continue;
                    }
                }

                if ($selected_week_no > 0 && isset($weekly_by_week[$selected_week_no])) {
                    $selected_week_total = (float)$weekly_by_week[$selected_week_no]['amount'];
                }
            }
        }

        foreach ($student_history as $entry) {
            $payment_type_key = strtolower((string)($entry['payment_type'] ?? ''));
            $amount = (float)($entry['amount'] ?? 0);
            if (isset($history_totals[$payment_type_key])) {
                $history_totals[$payment_type_key] += $amount;
            }
            $history_totals['all'] += $amount;
        }
    } else {
        $history_notice = 'Learner history could not be opened. The selected learner may no longer be active in this context.';
    }
} elseif ($student_id <= 0) {
    $history_notice = 'No learner was selected for history.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Feeding History | Salba</title>
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
                <a href="dashboard.php" class="hover:text-emerald-600 transition-colors">Feeding Dashboard</a>
                <span>/</span>
                <a href="daily_weekly_tracker.php?class=<?= urlencode($selected_class) ?>&date=<?= urlencode($selected_date) ?>" class="hover:text-emerald-600 transition-colors">Marking Register</a>
                <span>/</span>
                <span class="text-emerald-600">Student History</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-clock-rotate-left text-indigo-600"></i> Student Payment History
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Dedicated page for learner feeding payment history.</p>
                </div>
                <a href="daily_weekly_tracker.php?class=<?= urlencode($selected_class) ?>&date=<?= urlencode($selected_date) ?>" class="px-3 py-2 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50">Back to Register</a>
            </div>
        </div>

        <?php if (!$schema_ready): ?>
            <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-sm font-semibold">
                Feeding tables are not ready. Run the patch script, then refresh this page.
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl border border-slate-200 p-4 mb-6">
            <?php if ($history_student): ?>
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Learner</h2>
                <p class="text-sm text-slate-600 mt-1">
                    <?= htmlspecialchars($history_student['first_name'] . ' ' . $history_student['last_name']) ?>
                    <span class="text-slate-400">(<?= htmlspecialchars($history_student['student_code']) ?> · <?= htmlspecialchars($history_student['class'] ?? 'No Class') ?>)</span>
                </p>
            <?php else: ?>
                <p class="text-sm text-rose-700"><?= htmlspecialchars($history_notice ?: 'No learner profile found for this selection.') ?></p>
            <?php endif; ?>
        </div>

        <?php if ($history_student): ?>
            <div class="flex flex-wrap gap-2 mb-4">
                <?php foreach (['all' => 'All', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $type_key => $type_label): ?>
                    <a href="student_history.php?student_id=<?= (int)$history_student['id'] ?>&class=<?= urlencode($selected_class) ?>&date=<?= urlencode($selected_date) ?>&history_type=<?= urlencode($type_key) ?>&week_no=<?= urlencode($selected_week_no > 0 ? (string)$selected_week_no : 'all') ?>" class="px-3 py-1.5 rounded-full text-xs font-semibold <?= $history_type === $type_key ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
                        <?= htmlspecialchars($type_label) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($history_type === 'weekly'): ?>
                <form method="GET" class="bg-slate-50 border border-slate-200 rounded-lg p-3 mb-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                    <input type="hidden" name="student_id" value="<?= (int)$history_student['id'] ?>">
                    <input type="hidden" name="class" value="<?= htmlspecialchars($selected_class) ?>">
                    <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                    <input type="hidden" name="history_type" value="weekly">
                    <div class="md:col-span-3">
                        <label class="block text-[0.65rem] font-black text-slate-500 uppercase tracking-widest mb-2">Weekly Filter</label>
                        <select name="week_no" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" onchange="this.form.submit()">
                            <option value="all" <?= $selected_week_no === 0 ? 'selected' : '' ?>>All Weeks</option>
                            <?php foreach ($week_options as $wk_meta): ?>
                                <option value="<?= (int)$wk_meta['week_no'] ?>" <?= $selected_week_no === (int)$wk_meta['week_no'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($wk_meta['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-[0.65rem] font-black text-slate-500 uppercase tracking-widest">Selected Week</p>
                        <p class="text-sm font-semibold text-slate-800 mt-2"><?= htmlspecialchars($selected_week_label) ?></p>
                    </div>
                </form>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                <div class="rounded-lg border border-slate-200 p-3">
                    <p class="text-[0.65rem] font-black text-slate-500 uppercase tracking-widest">Total</p>
                    <p class="text-lg font-black text-slate-900 mt-1">GHS <?= number_format($history_totals['all'], 2) ?></p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <p class="text-[0.65rem] font-black text-amber-700 uppercase tracking-widest">Weekly</p>
                    <p class="text-lg font-black text-amber-700 mt-1">GHS <?= number_format($history_totals['weekly'], 2) ?></p>
                </div>
                <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-3">
                    <p class="text-[0.65rem] font-black text-indigo-700 uppercase tracking-widest">Monthly</p>
                    <p class="text-lg font-black text-indigo-700 mt-1">GHS <?= number_format($history_totals['monthly'], 2) ?></p>
                </div>
            </div>

            <?php if ($history_type === 'weekly' && $selected_week_no > 0): ?>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 mb-4">
                    <p class="text-[0.65rem] font-black text-emerald-700 uppercase tracking-widest">Paid In <?= htmlspecialchars($selected_week_label) ?></p>
                    <p class="text-lg font-black text-emerald-700 mt-1">GHS <?= number_format($selected_week_total, 2) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($history_type === 'weekly' && !empty($weekly_by_week)): ?>
                <div class="overflow-x-auto border border-slate-200 rounded-lg bg-white mb-4">
                    <table class="w-full min-w-[560px]">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Week</th>
                                <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Payments</th>
                                <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Amount Paid</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($weekly_by_week as $wk_no => $wk): ?>
                                <tr class="<?= $selected_week_no === (int)$wk_no ? 'bg-emerald-50' : '' ?>">
                                    <td class="px-4 py-3 text-sm text-slate-700"><?= htmlspecialchars($wk['label']) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-700"><?= (int)($wk['payments_count'] ?? 0) ?></td>
                                    <td class="px-4 py-3 text-sm font-semibold text-slate-900">GHS <?= number_format((float)($wk['amount'] ?? 0), 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="overflow-x-auto border border-slate-200 rounded-lg bg-white">
                <table class="w-full min-w-[760px]">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Method</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Month</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Months Count</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!$student_history): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">No payments found for this learner in the current semester context.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($student_history as $entry): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm text-slate-700"><?= htmlspecialchars($entry['payment_date']) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-700"><?= htmlspecialchars(ucfirst($entry['payment_type'])) ?></td>
                                    <td class="px-4 py-3 text-sm font-semibold text-slate-900">GHS <?= number_format((float)($entry['amount'] ?? 0), 2) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-700"><?= htmlspecialchars(ucfirst((string)($entry['payment_method'] ?? 'cash'))) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        <?php if (!empty($entry['month_no'])): ?>
                                            <?= htmlspecialchars(date('M', mktime(0, 0, 0, (int)$entry['month_no'], 1))) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700"><?= (int)($entry['months_count'] ?? 1) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-600"><?= htmlspecialchars((string)($entry['notes'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
