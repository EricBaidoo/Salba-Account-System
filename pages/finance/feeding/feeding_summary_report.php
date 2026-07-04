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
$has_rates_table = feed_table_exists($conn, 'feeding_class_rates');
$has_closeouts_table = feed_table_exists($conn, 'feeding_day_closeouts');
$schema_ready = $has_plan_table && $has_payments_table;
$has_payment_method = $schema_ready && feed_has_column($conn, 'feeding_payments', 'payment_method');

function feeding_bind_params($stmt, string $types, array $values) {
    $refs = [];
    $refs[] = $types;
    foreach ($values as $index => $value) {
        $refs[] = &$values[$index];
    }
    return $stmt->bind_param(...$refs);
}

function feeding_fee_amount_for_class($conn, $student_class) {
    $fee_stmt = $conn->prepare("SELECT id, amount, fee_type FROM fees WHERE name = 'Feeding Fee' LIMIT 1");
    if (!$fee_stmt) {
        return null;
    }
    $fee_stmt->execute();
    $fee_row = $fee_stmt->get_result()->fetch_assoc();
    $fee_stmt->close();

    if (!$fee_row) {
        return null;
    }

    if (($fee_row['fee_type'] ?? '') === 'fixed') {
        return (float)$fee_row['amount'];
    }

    $amount_stmt = $conn->prepare("SELECT amount FROM fee_amounts WHERE fee_id = ? AND class_name = ? LIMIT 1");
    if (!$amount_stmt) {
        return (float)$fee_row['amount'];
    }
    $fee_id = (int)$fee_row['id'];
    $amount_stmt->bind_param('is', $fee_id, $student_class);
    $amount_stmt->execute();
    $amount_row = $amount_stmt->get_result()->fetch_assoc();
    $amount_stmt->close();

    return $amount_row ? (float)$amount_row['amount'] : (float)$fee_row['amount'];
}

function feeding_has_active_plan($conn, $student_id, $academic_year, $semester) {
    $stmt = $conn->prepare("SELECT id FROM student_daily_weekly_feeding WHERE student_id = ? AND academic_year = ? AND semester = ? AND status = 'active' LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iss', $student_id, $academic_year, $semester);
    $stmt->execute();
    $has_row = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $has_row;
}

$success_message = '';
$error_message = '';

if ($schema_ready && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_to_bill_based'])) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $student_class = trim($_POST['student_class'] ?? '');
    $confirm_switch_back = isset($_POST['confirm_switch_back']);

    if ($student_id <= 0 || $student_class === '') {
        $error_message = 'Select a learner before switching them back to bill-based feeding.';
    } elseif (!$confirm_switch_back) {
        $error_message = 'Confirm that you want to restore the Feeding Fee and deactivate separate feeding.';
    } else {
        $plan_stmt = $conn->prepare("SELECT id FROM student_daily_weekly_feeding WHERE student_id = ? AND academic_year = ? AND semester = ? AND status = 'active' LIMIT 1");
        if ($plan_stmt) {
            $plan_stmt->bind_param('iss', $student_id, $acad_year, $current_semester);
            $plan_stmt->execute();
            $plan_row = $plan_stmt->get_result()->fetch_assoc();
            $plan_stmt->close();

            if (!$plan_row) {
                $error_message = 'This learner is not currently on separate feeding.';
            } else {
                $conn->begin_transaction();
                try {
                    $plan_id = (int)$plan_row['id'];
                    $deactivate_stmt = $conn->prepare("UPDATE student_daily_weekly_feeding SET status = 'completed', ended_date = CURDATE() WHERE id = ?");
                    if (!$deactivate_stmt) {
                        throw new Exception('Could not prepare feeding deactivation.');
                    }
                    $deactivate_stmt->bind_param('i', $plan_id);
                    if (!$deactivate_stmt->execute()) {
                        throw new Exception('Could not deactivate feeding enrollment.');
                    }
                    $deactivate_stmt->close();

                    $feeding_fee_amount = feeding_fee_amount_for_class($conn, $student_class);
                    if ($feeding_fee_amount === null) {
                        throw new Exception('Feeding Fee amount could not be resolved for the learner class.');
                    }

                    $feeding_fee_stmt = $conn->prepare("SELECT id FROM fees WHERE name = 'Feeding Fee' LIMIT 1");
                    if (!$feeding_fee_stmt) {
                        throw new Exception('Feeding Fee record not found.');
                    }
                    $feeding_fee_stmt->execute();
                    $feeding_fee_row = $feeding_fee_stmt->get_result()->fetch_assoc();
                    $feeding_fee_stmt->close();
                    if (!$feeding_fee_row) {
                        throw new Exception('Feeding Fee record not found.');
                    }

                    $feeding_fee_id = (int)$feeding_fee_row['id'];
                    $delete_bill_stmt = $conn->prepare("DELETE FROM student_fees WHERE student_id = ? AND fee_id = ? AND semester = ? AND academic_year = ? AND status != 'paid'");
                    if (!$delete_bill_stmt) {
                        throw new Exception('Could not prepare existing Feeding Fee cleanup.');
                    }
                    $delete_bill_stmt->bind_param('iiss', $student_id, $feeding_fee_id, $current_semester, $acad_year);
                    if (!$delete_bill_stmt->execute()) {
                        throw new Exception('Could not clear the old Feeding Fee row.');
                    }
                    $delete_bill_stmt->close();

                    $restore_stmt = $conn->prepare("INSERT INTO student_fees (student_id, fee_id, due_date, amount, semester, academic_year, assigned_date, status) VALUES (?, ?, CURDATE(), ?, ?, ?, NOW(), 'pending')");
                    if (!$restore_stmt) {
                        throw new Exception('Could not prepare Feeding Fee restoration.');
                    }
                    feeding_bind_params($restore_stmt, 'iidss', [$student_id, $feeding_fee_id, $feeding_fee_amount, $current_semester, $acad_year]);
                    if (!$restore_stmt->execute()) {
                        throw new Exception('Could not restore the Feeding Fee to the bill.');
                    }
                    $restore_stmt->close();

                    $conn->commit();
                    $success_message = 'Learner switched back to bill-based feeding successfully.';
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = $e->getMessage();
                }
            }
        }
    }
}

$stats = [
    'total_students' => 0,
    'weekly_count' => 0,
    'monthly_count' => 0,
    'total_collected' => 0,
];
$recent_payments = [];
$balances = [];
$recent_closeouts = [];
$feeding_conflicts = [];
$switch_back_candidates = [];

if ($schema_ready) {
    // Summary statistics for active learners in the selected academic context.
    $stats_sql = "SELECT
                    COUNT(DISTINCT dwf.student_id) as total_students,
                    SUM(CASE WHEN dwf.plan_type = 'weekly' THEN 1 ELSE 0 END) as weekly_count,
                    SUM(CASE WHEN dwf.plan_type = 'monthly' THEN 1 ELSE 0 END) as monthly_count,
                    COALESCE(SUM(fp.amount), 0) as total_collected
                FROM student_daily_weekly_feeding dwf
                LEFT JOIN feeding_payments fp ON dwf.id = fp.student_feeding_plan_id
                WHERE dwf.academic_year = ?
                    AND dwf.semester = ?
                    AND dwf.status = 'active'
                    AND dwf.plan_type IN ('weekly', 'monthly')";
    $stats_stmt = $conn->prepare($stats_sql);
    if ($stats_stmt) {
        $stats_stmt->bind_param('ss', $acad_year, $current_semester);
        $stats_stmt->execute();
        $stats_row = $stats_stmt->get_result()->fetch_assoc();
        if ($stats_row) {
            $stats = $stats_row;
        }
        $stats_stmt->close();
    }

    $recent_method_select = $has_payment_method ? 'fp.payment_method' : "'cash' AS payment_method";
    $recent_sql = "SELECT
                    s.first_name,
                    s.last_name,
                    CONCAT('SMS-', LPAD(s.id, 3, '0')) AS student_code,
                    fp.payment_date,
                    fp.payment_type,
                    fp.amount,
                    $recent_method_select
                FROM feeding_payments fp
                JOIN student_daily_weekly_feeding dwf ON dwf.id = fp.student_feeding_plan_id
                JOIN students s ON fp.student_id = s.id
                WHERE dwf.academic_year = ?
                    AND dwf.semester = ?
                    AND dwf.plan_type IN ('weekly', 'monthly')
                ORDER BY fp.payment_date DESC, fp.id DESC
                LIMIT 20";
    $recent_stmt = $conn->prepare($recent_sql);
    if ($recent_stmt) {
        $recent_stmt->bind_param('ss', $acad_year, $current_semester);
        $recent_stmt->execute();
        $recent_payments = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $recent_stmt->close();
    }

    $expected_amount_select = 'dwf.amount_per_unit';
    if ($has_rates_table) {
        $expected_amount_select = "COALESCE((
                SELECT fcr.amount
                FROM feeding_class_rates fcr
                WHERE CONVERT(fcr.class_name USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(s.class USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    AND fcr.plan_type = dwf.plan_type
                    AND fcr.is_active = 1
                    AND fcr.effective_from <= CURDATE()
                ORDER BY fcr.effective_from DESC, fcr.id DESC
                LIMIT 1
        ), dwf.amount_per_unit)";
    }

    $balance_sql = "SELECT
                        s.id,
                        s.first_name,
                        s.last_name,
                        CONCAT('SMS-', LPAD(s.id, 3, '0')) AS student_code,
                        dwf.plan_type,
                        $expected_amount_select AS expected_amount,
                        dwf.amount_per_unit,
                        dwf.started_date,
                        COUNT(fp.id) as payment_count,
                        COALESCE(SUM(fp.amount), 0) as total_paid
                    FROM student_daily_weekly_feeding dwf
                    JOIN students s ON dwf.student_id = s.id
                    LEFT JOIN feeding_payments fp ON dwf.id = fp.student_feeding_plan_id
                    WHERE dwf.academic_year = ?
                        AND dwf.semester = ?
                        AND dwf.status = 'active'
                        AND dwf.plan_type IN ('weekly', 'monthly')
                    GROUP BY dwf.id, s.id, s.first_name, s.last_name, s.class, dwf.plan_type, dwf.amount_per_unit, dwf.started_date
                    ORDER BY s.first_name, s.last_name";
    $balance_stmt = $conn->prepare($balance_sql);
    if ($balance_stmt) {
        $balance_stmt->bind_param('ss', $acad_year, $current_semester);
        $balance_stmt->execute();
        $balances = $balance_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $balance_stmt->close();
    }

    if ($has_closeouts_table) {
        $closeout_sql = "SELECT class_name, close_date, expected_total, collected_total, variance, is_locked, closed_at, reopened_at
                         FROM feeding_day_closeouts
                         WHERE academic_year = ?
                           AND semester = ?
                         ORDER BY close_date DESC, class_name ASC
                         LIMIT 10";
        $closeout_stmt = $conn->prepare($closeout_sql);
        if ($closeout_stmt) {
            $closeout_stmt->bind_param('ss', $acad_year, $current_semester);
            $closeout_stmt->execute();
            $recent_closeouts = $closeout_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $closeout_stmt->close();
        }
    }

    $audit_sql = "SELECT
                    s.id,
                    s.first_name,
                    s.last_name,
                    CONCAT('SMS-', LPAD(s.id, 3, '0')) AS student_code,
                    s.class,
                    dwf.id AS plan_id,
                    dwf.plan_type,
                    dwf.amount_per_unit,
                    sf.id AS bill_fee_id,
                    sf.amount AS billed_amount,
                    sf.status AS bill_status
                FROM student_daily_weekly_feeding dwf
                JOIN students s ON s.id = dwf.student_id
                                LEFT JOIN fees f ON CONVERT(f.name USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT('Feeding Fee' USING utf8mb4) COLLATE utf8mb4_unicode_ci
                                LEFT JOIN student_fees sf ON sf.student_id = s.id
                                        AND sf.fee_id = f.id
                                        AND CONVERT(sf.semester USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(dwf.semester USING utf8mb4) COLLATE utf8mb4_unicode_ci
                                        AND CONVERT(sf.academic_year USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(dwf.academic_year USING utf8mb4) COLLATE utf8mb4_unicode_ci
                                        AND CONVERT(sf.status USING utf8mb4) COLLATE utf8mb4_unicode_ci != CONVERT('cancelled' USING utf8mb4) COLLATE utf8mb4_unicode_ci
                                WHERE CONVERT(dwf.academic_year USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
                                    AND CONVERT(dwf.semester USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
                                    AND CONVERT(dwf.status USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT('active' USING utf8mb4) COLLATE utf8mb4_unicode_ci
                                    AND CONVERT(dwf.plan_type USING utf8mb4) COLLATE utf8mb4_unicode_ci IN (
                                        CONVERT('weekly' USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                                        CONVERT('monthly' USING utf8mb4) COLLATE utf8mb4_unicode_ci
                                    )
                ORDER BY s.class, s.first_name, s.last_name";
    $audit_stmt = $conn->prepare($audit_sql);
    if ($audit_stmt) {
        $audit_stmt->bind_param('ss', $acad_year, $current_semester);
        $audit_stmt->execute();
        $audit_rows = $audit_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $audit_stmt->close();

        foreach ($audit_rows as $row) {
            if (!empty($row['bill_fee_id'])) {
                $feeding_conflicts[] = $row;
            }
            $switch_back_candidates[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feeding Summary Report | Salba</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#F8FAFC] text-slate-900 min-h-screen">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <main class="admin-main-content lg:ml-72 min-h-screen pb-12">
        <!-- Header -->
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-emerald-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <a href="dashboard.php" class="hover:text-emerald-600 transition-colors">Feeding Dashboard</a>
                <span>/</span>
                <span class="text-emerald-600">Summary Report</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-chart-bar text-indigo-600"></i> Feeding Summary Report
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm"><?= $current_semester ?> (<?= $acad_year ?>)</p>
                </div>
                <a href="daily_weekly_tracker.php" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i> Back to Tracker
                </a>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="mx-6 mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg text-sm font-medium">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="mx-6 mb-4 bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-lg text-sm font-medium">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Content -->
        <div class="px-6 space-y-6">
            <?php if (!$schema_ready): ?>
                <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg text-sm font-medium">
                    Feeding tables are not ready yet. Run the DB patch, then refresh this page.
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Total Students -->
                <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-slate-600 text-sm font-medium">Total Students</p>
                            <p class="text-3xl font-bold text-slate-900 mt-1"><?= $stats['total_students'] ?? 0 ?></p>
                        </div>
                        <i class="fas fa-users text-4xl text-blue-200"></i>
                    </div>
                </div>

                <!-- Weekly -->
                <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-slate-600 text-sm font-medium">Weekly Plan</p>
                            <p class="text-3xl font-bold text-purple-600 mt-1"><?= $stats['weekly_count'] ?? 0 ?></p>
                        </div>
                        <i class="fas fa-calendar-week text-4xl text-purple-200"></i>
                    </div>
                </div>

                <!-- Monthly -->
                <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-slate-600 text-sm font-medium">Monthly Plan</p>
                            <p class="text-3xl font-bold text-green-600 mt-1"><?= $stats['monthly_count'] ?? 0 ?></p>
                        </div>
                        <i class="fas fa-calendar text-4xl text-green-200"></i>
                    </div>
                </div>

                <!-- Total Collected -->
                <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-slate-600 text-sm font-medium">Total Collected</p>
                            <p class="text-3xl font-bold text-emerald-600 mt-1">GHS <?= number_format($stats['total_collected'] ?? 0, 2) ?></p>
                        </div>
                        <i class="fas fa-money-bill-wave text-4xl text-emerald-200"></i>
                    </div>
                </div>

                <!-- Separate Feeding -->
                <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-slate-600 text-sm font-medium">Separate Feeding</p>
                            <p class="text-3xl font-bold text-orange-600 mt-1"><?= (int)($separate_feeding_students ?? 0) ?></p>
                        </div>
                        <i class="fas fa-utensils text-4xl text-orange-200"></i>
                    </div>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="bg-white rounded-lg border border-slate-200 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <i class="fas fa-clock text-blue-600"></i> Recent Payments
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Student</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Student Code</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Payment Date</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Method</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if (count($recent_payments) > 0): ?>
                                <?php foreach ($recent_payments as $payment): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4 text-sm text-slate-900 font-medium"><?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-700"><?= htmlspecialchars($payment['student_code']) ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-700"><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                        <td class="px-6 py-4">
                                            <span class="inline-block px-3 py-1 text-xs font-medium rounded-full <?= $payment['payment_type'] === 'weekly' ? 'bg-purple-100 text-purple-700' : ($payment['payment_type'] === 'monthly' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-700') ?>">
                                                <?= ucfirst($payment['payment_type']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-emerald-600">GHS <?= number_format($payment['amount'], 2) ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-700"><?= ucfirst($payment['payment_method']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                        No payments recorded yet
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Student Balances -->
            <div class="bg-white rounded-lg border border-slate-200 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <i class="fas fa-list text-blue-600"></i> Student Payment Status
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Student</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Student Code</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Plan</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Amount/Unit</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Payments</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Total Paid</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Started</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if (count($balances) > 0): ?>
                                <?php foreach ($balances as $balance): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <p class="text-sm font-medium text-slate-900"><?= htmlspecialchars($balance['first_name'] . ' ' . $balance['last_name']) ?></p>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-700"><?= htmlspecialchars($balance['student_code']) ?></td>
                                        <td class="px-6 py-4">
                                            <span class="inline-block px-3 py-1 text-xs font-medium rounded-full <?= $balance['plan_type'] === 'weekly' ? 'bg-purple-100 text-purple-700' : ($balance['plan_type'] === 'monthly' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-700') ?>">
                                                <?= ucfirst($balance['plan_type']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-slate-900">GHS <?= number_format((float)($balance['expected_amount'] ?? $balance['amount_per_unit']), 2) ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-700"><?= $balance['payment_count'] ?></td>
                                        <td class="px-6 py-4 text-sm font-medium text-emerald-600">GHS <?= number_format($balance['total_paid'], 2) ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-700"><?= date('M d, Y', strtotime($balance['started_date'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                        No students enrolled in feeding plans yet
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Switch Back To Bill-Based Feeding -->
            <div class="bg-white rounded-lg border border-slate-200 shadow-sm" id="switch-back">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <i class="fas fa-right-left text-emerald-600"></i> Switch Back to Bill-Based Feeding
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Student</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Class</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Current Plan</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Restore To Bill</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if (count($switch_back_candidates) > 0): ?>
                                <?php foreach ($switch_back_candidates as $row): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4 text-sm text-slate-900 font-medium"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-700"><?= htmlspecialchars($row['class']) ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-700"><?= htmlspecialchars(ucfirst($row['plan_type'])) ?></td>
                                        <td class="px-6 py-4">
                                            <form method="POST" class="flex items-center gap-2">
                                                <input type="hidden" name="switch_to_bill_based" value="1">
                                                <input type="hidden" name="student_id" value="<?= (int)$row['id'] ?>">
                                                <input type="hidden" name="student_class" value="<?= htmlspecialchars($row['class']) ?>">
                                                <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                                                    <input type="checkbox" name="confirm_switch_back" required class="w-4 h-4 rounded border-slate-300 text-emerald-600">
                                                    Confirm
                                                </label>
                                                <button type="submit" class="px-3 py-2 rounded-lg text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700">
                                                    Restore Feeding Fee
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-slate-500">No learners are currently on separate feeding.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sync Audit -->
            <div class="bg-white rounded-lg border border-slate-200 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <i class="fas fa-triangle-exclamation text-rose-600"></i> Feeding Sync Audit
                    </h2>
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold <?= count($feeding_conflicts) > 0 ? 'bg-rose-100 text-rose-700 border border-rose-200' : 'bg-emerald-100 text-emerald-700 border border-emerald-200' ?>">
                        <span class="w-2 h-2 rounded-full <?= count($feeding_conflicts) > 0 ? 'bg-rose-500' : 'bg-emerald-500' ?>"></span>
                        <?= count($feeding_conflicts) ?> conflict<?= count($feeding_conflicts) === 1 ? '' : 's' ?>
                    </span>
                </div>
                <?php if (count($feeding_conflicts) > 0): ?>
                    <div class="px-6 py-3 bg-rose-50 border-b border-rose-100 text-rose-700 text-sm flex items-start gap-2">
                        <i class="fas fa-circle-exclamation mt-0.5"></i>
                        <span>These learners are still listed in both systems. Restore them to bill-based feeding or re-run the bill sync to clear the conflict.</span>
                    </div>
                <?php endif; ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Student</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Class</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Separate Feeding</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Bill State</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if (count($feeding_conflicts) > 0): ?>
                                <?php foreach ($feeding_conflicts as $row): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4 text-sm text-slate-900 font-medium"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-700"><?= htmlspecialchars($row['class']) ?></td>
                                        <td class="px-6 py-4 text-sm text-emerald-700">Active <?= htmlspecialchars(ucfirst($row['plan_type'])) ?></td>
                                        <td class="px-6 py-4 text-sm <?= $row['bill_fee_id'] ? 'text-rose-700' : 'text-emerald-700' ?>">
                                            <?= $row['bill_fee_id'] ? 'Feeding Fee still on bill' : 'No bill conflict' ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-500">Use switch-back or re-run bill sync</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-slate-500">No sync conflicts detected.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Closeouts -->
            <div class="bg-white rounded-lg border border-slate-200 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <i class="fas fa-clock-rotate-left text-rose-600"></i> Recent Closeouts
                    </h2>
                    <a href="closeout_history.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">View all</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Class</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Expected</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Collected</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Variance</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Slip</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if (count($recent_closeouts) > 0): ?>
                                <?php foreach ($recent_closeouts as $closeout): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4 text-sm text-slate-900 font-medium"><?= htmlspecialchars($closeout['class_name']) ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-700"><?= date('M d, Y', strtotime($closeout['close_date'])) ?></td>
                                        <td class="px-6 py-4">
                                            <?php if ((int)$closeout['is_locked'] === 1): ?>
                                                <span class="inline-block px-3 py-1 text-xs font-medium rounded-full bg-rose-100 text-rose-700">Locked</span>
                                            <?php else: ?>
                                                <span class="inline-block px-3 py-1 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700">Open</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-slate-900">GHS <?= number_format((float)$closeout['expected_total'], 2) ?></td>
                                        <td class="px-6 py-4 text-sm font-medium text-emerald-600">GHS <?= number_format((float)$closeout['collected_total'], 2) ?></td>
                                        <td class="px-6 py-4 text-sm font-medium <?= (float)$closeout['variance'] >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>">GHS <?= number_format((float)$closeout['variance'], 2) ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-700">
                                            <a href="daily_closeout_slip.php?class=<?= urlencode($closeout['class_name']) ?>&date=<?= urlencode($closeout['close_date']) ?>" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-medium">
                                                <i class="fas fa-print"></i> Print
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                        No closeouts recorded yet
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
