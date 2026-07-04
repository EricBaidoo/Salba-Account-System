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
$selected_date = trim($_GET['date'] ?? '');
$selected_status = trim($_GET['status'] ?? 'all');

function feed_table_exists($conn, $table_name) {
    $escaped = $conn->real_escape_string($table_name);
    $sql = "SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$escaped'";
    $res = $conn->query($sql);
    if (!$res) {
        return false;
    }
    return ((int)($res->fetch_assoc()['c'] ?? 0)) > 0;
}

function feed_bind_params($stmt, string $types, array $values) {
    $refs = [];
    $refs[] = $types;
    foreach ($values as $index => $value) {
        $refs[] = &$values[$index];
    }
    return $stmt->bind_param(...$refs);
}

$has_closeouts_table = feed_table_exists($conn, 'feeding_day_closeouts');
$has_plan_table = feed_table_exists($conn, 'student_daily_weekly_feeding');
$schema_ready = $has_closeouts_table && $has_plan_table;

$class_options = [];
$closeouts = [];

if ($schema_ready) {
    $class_stmt = $conn->prepare("SELECT DISTINCT s.class
                                  FROM student_daily_weekly_feeding dwf
                                  JOIN students s ON s.id = dwf.student_id
                                  WHERE dwf.academic_year = ?
                                    AND dwf.semester = ?
                                    AND dwf.status = 'active'
                                    AND s.class IS NOT NULL
                                    AND s.class != ''
                                  ORDER BY s.class");
    if ($class_stmt) {
        $class_stmt->bind_param('ss', $acad_year, $current_semester);
        $class_stmt->execute();
        $class_options = $class_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $class_stmt->close();
    }

    $sql = "SELECT
                c.class_name,
                c.close_date,
                c.academic_year,
                c.semester,
                c.expected_total,
                c.collected_total,
                c.variance,
                c.cash_total,
                c.momo_total,
                c.transfer_total,
                c.check_total,
                c.close_notes,
                c.is_locked,
                c.closed_at,
                c.reopened_at,
                c.closed_by,
                c.reopened_by
            FROM feeding_day_closeouts c
            WHERE c.academic_year = ?
              AND c.semester = ?";
    $types = 'ss';
    $values = [$acad_year, $current_semester];

    if ($selected_class !== '') {
        $sql .= " AND c.class_name = ?";
        $types .= 's';
        $values[] = $selected_class;
    }
    if ($selected_date !== '') {
        $sql .= " AND c.close_date = ?";
        $types .= 's';
        $values[] = $selected_date;
    }
    if ($selected_status === 'locked') {
        $sql .= " AND c.is_locked = 1";
    } elseif ($selected_status === 'open') {
        $sql .= " AND c.is_locked = 0";
    }

    $sql .= " ORDER BY c.close_date DESC, c.class_name ASC LIMIT 100";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        feed_bind_params($stmt, $types, $values);
        $stmt->execute();
        $closeouts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feeding Closeout History | Salba Montessori</title>
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
                <span class="text-emerald-600">Closeout History</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-clock-rotate-left text-indigo-600"></i> Feeding Closeout History
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Audit locked and reopened class/date reconciliation records.</p>
                </div>
                <a href="daily_weekly_tracker.php" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Tracker
                </a>
            </div>
        </div>

        <?php if (!$schema_ready): ?>
            <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-sm font-semibold">
                Feeding closeout tables are not ready yet. Run the database patch first.
            </div>
        <?php endif; ?>

        <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Class</label>
                <select name="class" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">All classes</option>
                    <?php foreach ($class_options as $option): ?>
                        <option value="<?= htmlspecialchars($option['class']) ?>" <?= $selected_class === $option['class'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($option['class']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Date</label>
                <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="all" <?= $selected_status === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="locked" <?= $selected_status === 'locked' ? 'selected' : '' ?>>Locked</option>
                    <option value="open" <?= $selected_status === 'open' ? 'selected' : '' ?>>Open</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800">Filter</button>
            </div>
        </form>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-3">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Closeout Records</h2>
                <p class="text-xs text-slate-500">Academic year: <?= htmlspecialchars($acad_year) ?> | Semester: <?= htmlspecialchars($current_semester) ?></p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px]">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Class</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Expected</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Collected</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Variance</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Cash</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">MoMo</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Transfer</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Check</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Closed / Reopened</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!$closeouts): ?>
                            <tr>
                                <td colspan="12" class="px-4 py-10 text-center text-sm text-slate-500">No closeout records found for the selected filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($closeouts as $row): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-sm font-semibold text-slate-900"><?= htmlspecialchars($row['class_name']) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-700"><?= htmlspecialchars(date('M d, Y', strtotime($row['close_date']))) ?></td>
                                    <td class="px-4 py-3">
                                        <?php if ((int)$row['is_locked'] === 1): ?>
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-700">Locked</span>
                                        <?php else: ?>
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">Open</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700">GHS <?= number_format((float)$row['expected_total'], 2) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-700">GHS <?= number_format((float)$row['collected_total'], 2) ?></td>
                                    <td class="px-4 py-3 text-sm font-semibold <?= (float)$row['variance'] >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>">GHS <?= number_format((float)$row['variance'], 2) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-700">GHS <?= number_format((float)$row['cash_total'], 2) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-700">GHS <?= number_format((float)$row['momo_total'], 2) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-700">GHS <?= number_format((float)$row['transfer_total'], 2) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-700">GHS <?= number_format((float)$row['check_total'], 2) ?></td>
                                    <td class="px-4 py-3 text-xs text-slate-600">
                                        <div>Closed: <?= htmlspecialchars($row['closed_at'] ? date('M d, Y H:i', strtotime($row['closed_at'])) : '—') ?></div>
                                        <div>Reopened: <?= htmlspecialchars($row['reopened_at'] ? date('M d, Y H:i', strtotime($row['reopened_at'])) : '—') ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-600 max-w-[240px] break-words">
                                        <div><?= htmlspecialchars($row['close_notes'] ?: '—') ?></div>
                                        <a href="daily_closeout_slip.php?class=<?= urlencode($row['class_name']) ?>&date=<?= urlencode($row['close_date']) ?>" class="inline-flex mt-2 text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                            <i class="fas fa-print mr-1"></i> Print slip
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>