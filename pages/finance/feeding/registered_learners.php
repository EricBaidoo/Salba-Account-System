<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
require_finance_access();

$current_semester = getCurrentSemester($conn);
$acad_year = getAcademicYear($conn);
$selected_plan = strtolower(trim($_GET['plan'] ?? 'all'));
$selected_class = trim($_GET['class'] ?? '');
if (!in_array($selected_plan, ['all', 'weekly', 'monthly'], true)) {
    $selected_plan = 'all';
}

function reg_table_exists($conn, $table_name) {
    $escaped = $conn->real_escape_string($table_name);
    $sql = "SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$escaped'";
    $res = $conn->query($sql);
    if (!$res) {
        return false;
    }
    return ((int)($res->fetch_assoc()['c'] ?? 0)) > 0;
}

$has_plan_table = reg_table_exists($conn, 'student_daily_weekly_feeding');
$schema_ready = $has_plan_table;

$class_options = [];
$rows = [];

if ($schema_ready) {
    $class_sql = "SELECT DISTINCT s.class
                  FROM student_daily_weekly_feeding dwf
                  JOIN students s ON s.id = dwf.student_id
                  WHERE dwf.academic_year = ?
                    AND dwf.semester = ?
                    AND dwf.status = 'active'
                    AND dwf.plan_type IN ('weekly','monthly')
                    AND s.status = 'active'
                    AND s.class IS NOT NULL
                    AND s.class != ''
                  ORDER BY s.class";
    $class_stmt = $conn->prepare($class_sql);
    if ($class_stmt) {
        $class_stmt->bind_param('ss', $acad_year, $current_semester);
        $class_stmt->execute();
        $class_options = $class_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $class_stmt->close();
    }

    $sql = "SELECT
                s.id,
                s.first_name,
                s.last_name,
                CONCAT('SMS-', LPAD(s.id, 3, '0')) AS student_code,
                s.class AS class_name,
                dwf.plan_type,
                dwf.amount_per_unit,
                dwf.started_date,
                dwf.status
            FROM student_daily_weekly_feeding dwf
            JOIN students s ON s.id = dwf.student_id
            WHERE dwf.academic_year = ?
              AND dwf.semester = ?
              AND dwf.status = 'active'
              AND dwf.plan_type IN ('weekly','monthly')
              AND s.status = 'active'";
    $types = 'ss';
    $values = [$acad_year, $current_semester];

    if ($selected_plan !== 'all') {
        $sql .= " AND dwf.plan_type = ?";
        $types .= 's';
        $values[] = $selected_plan;
    }

    if ($selected_class !== '') {
        $sql .= " AND CONVERT(s.class USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci";
        $types .= 's';
        $values[] = $selected_class;
    }

    $sql .= " ORDER BY s.class, s.first_name, s.last_name";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $refs = [];
        $refs[] = $types;
        foreach ($values as $idx => $val) {
            $refs[] = &$values[$idx];
        }
        $stmt->bind_param(...$refs);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Feeding Learners | Salba</title>
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
                <span class="text-emerald-600">Registered Learners</span>
            </div>
            <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fas fa-users text-indigo-600"></i> Registered Feeding Learners
            </h1>
            <p class="text-slate-500 mt-1 text-sm">All active learners registered under weekly or monthly feeding mode for this semester.</p>
        </div>

        <?php if (!$schema_ready): ?>
            <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-sm font-semibold">
                Feeding registration table is not ready. Run the database patch script and refresh.
            </div>
        <?php endif; ?>

        <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Mode</label>
                <select name="plan" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" onchange="this.form.submit()">
                    <option value="all" <?= $selected_plan === 'all' ? 'selected' : '' ?>>All Modes</option>
                    <option value="weekly" <?= $selected_plan === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                    <option value="monthly" <?= $selected_plan === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Class</label>
                <select name="class" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" onchange="this.form.submit()">
                    <option value="">All Classes</option>
                    <?php foreach ($class_options as $option): ?>
                        <option value="<?= htmlspecialchars($option['class']) ?>" <?= $selected_class === $option['class'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($option['class']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <a href="registered_learners.php" class="w-full text-center px-3 py-2 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
            </div>
        </form>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 bg-white flex items-center justify-between">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Learner List</h2>
                <span class="text-xs font-semibold text-slate-500"><?= count($rows) ?> learners</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px]">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Learner</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Class</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Mode</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Started</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">History</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No registered learners found for the selected filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-sm">
                                        <p class="font-semibold text-slate-800"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></p>
                                        <p class="text-xs text-slate-500"><?= htmlspecialchars($row['student_code']) ?></p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700"><?= htmlspecialchars($row['class_name']) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-700 uppercase tracking-wider">
                                        <span class="inline-flex px-2 py-1 text-xs font-bold rounded-full <?= $row['plan_type'] === 'weekly' ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700' ?>">
                                            <?= htmlspecialchars($row['plan_type']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-semibold text-slate-900">GHS <?= number_format((float)($row['amount_per_unit'] ?? 0), 2) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-700"><?= htmlspecialchars($row['started_date'] ?: '-') ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <a href="student_history.php?student_id=<?= (int)$row['id'] ?>&class=<?= urlencode((string)$row['class_name']) ?>&date=<?= urlencode(date('Y-m-d')) ?>&history_type=all" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-emerald-300 text-emerald-700 text-xs font-semibold hover:bg-emerald-50">
                                            <i class="fas fa-clock-rotate-left"></i> View History
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
