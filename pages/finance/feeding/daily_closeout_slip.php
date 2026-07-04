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

function feed_table_exists($conn, $table_name) {
    $escaped = $conn->real_escape_string($table_name);
    $sql = "SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$escaped'";
    $res = $conn->query($sql);
    if (!$res) {
        return false;
    }
    return ((int)($res->fetch_assoc()['c'] ?? 0)) > 0;
}

$has_closeouts_table = feed_table_exists($conn, 'feeding_day_closeouts');
$has_plan_table = feed_table_exists($conn, 'student_daily_weekly_feeding');
$schema_ready = $has_closeouts_table && $has_plan_table;

$closeout = null;
$class_options = [];

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

    if ($selected_class !== '' && $selected_date !== '') {
        $stmt = $conn->prepare("SELECT * FROM feeding_day_closeouts WHERE class_name = ? AND close_date = ? AND academic_year = ? AND semester = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ssss', $selected_class, $selected_date, $acad_year, $current_semester);
            $stmt->execute();
            $closeout = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
}

$selected_class_label = $selected_class !== '' ? $selected_class : 'All Classes';
$status_label = $closeout ? ((int)($closeout['is_locked'] ?? 0) === 1 ? 'Locked' : 'Open') : 'No closeout record';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feeding Closeout Slip | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            main { margin: 0 !important; padding: 0 !important; }
            .slip-page { box-shadow: none !important; border: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen">
    <main class="max-w-5xl mx-auto p-4 md:p-8">
        <div class="no-print mb-4 flex items-center justify-between gap-3">
            <a href="closeout_history.php" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 hover:text-slate-900">
                <i class="fas fa-arrow-left"></i> Back to closeout history
            </a>
            <button type="button" onclick="window.print()" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800">
                <i class="fas fa-print mr-2"></i> Print
            </button>
        </div>

        <?php if (!$schema_ready): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-sm font-semibold no-print mb-4">
                Feeding closeout tables are not ready yet. Run the database patch first.
            </div>
        <?php endif; ?>

        <section class="slip-page bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="bg-slate-900 text-white px-6 py-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-slate-300">Feeding Closeout Slip</p>
                        <h1 class="text-2xl font-bold mt-2">Feeding Reconciliation Closeout</h1>
                        <p class="text-sm text-slate-300 mt-1">Class: <?= htmlspecialchars($selected_class_label) ?> | Date: <?= htmlspecialchars($selected_date ?: '—') ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Academic Year</p>
                        <p class="text-sm font-semibold mt-1"><?= htmlspecialchars($acad_year) ?> / <?= htmlspecialchars($current_semester) ?></p>
                        <p class="text-xs text-slate-400 mt-3">Status: <?= htmlspecialchars($status_label) ?></p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-5 space-y-5">
                <?php if (!$closeout): ?>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm font-semibold">
                        No closeout record was found for the selected class/date.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                        <div class="rounded-xl border border-slate-200 p-3">
                            <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest">Class</p>
                            <p class="mt-2 text-slate-900 font-semibold"><?= htmlspecialchars($closeout['class_name']) ?></p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3">
                            <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest">Closed At</p>
                            <p class="mt-2 text-slate-900 font-semibold"><?= htmlspecialchars($closeout['closed_at'] ? date('M d, Y H:i', strtotime($closeout['closed_at'])) : '—') ?></p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3">
                            <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest">Reopened At</p>
                            <p class="mt-2 text-slate-900 font-semibold"><?= htmlspecialchars($closeout['reopened_at'] ? date('M d, Y H:i', strtotime($closeout['reopened_at'])) : '—') ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 text-sm">
                        <div class="rounded-xl border border-slate-200 p-3">
                            <p class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Expected</p>
                            <p class="text-lg font-black text-slate-900 mt-1">GHS <?= number_format((float)$closeout['expected_total'], 2) ?></p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3">
                            <p class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Collected</p>
                            <p class="text-lg font-black text-emerald-600 mt-1">GHS <?= number_format((float)$closeout['collected_total'], 2) ?></p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3">
                            <p class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Variance</p>
                            <p class="text-lg font-black mt-1 <?= (float)$closeout['variance'] >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>">GHS <?= number_format((float)$closeout['variance'], 2) ?></p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3">
                            <p class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Cash</p>
                            <p class="text-lg font-black text-slate-900 mt-1">GHS <?= number_format((float)$closeout['cash_total'], 2) ?></p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3">
                            <p class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">MoMo</p>
                            <p class="text-lg font-black text-slate-900 mt-1">GHS <?= number_format((float)$closeout['momo_total'], 2) ?></p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3">
                            <p class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest">Transfer / Check</p>
                            <p class="text-lg font-black text-slate-900 mt-1">GHS <?= number_format((float)$closeout['transfer_total'] + (float)$closeout['check_total'], 2) ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div class="rounded-xl border border-slate-200 p-4 min-h-[110px]">
                            <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest">Close Notes</p>
                            <p class="mt-2 text-slate-700 leading-relaxed"><?= htmlspecialchars($closeout['close_notes'] ?: '—') ?></p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-4 min-h-[110px]">
                            <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest">Audit Trail</p>
                            <div class="mt-2 text-slate-700 space-y-1">
                                <p>Closed by: <?= htmlspecialchars((string)($closeout['closed_by'] ?? '—')) ?></p>
                                <p>Reopened by: <?= htmlspecialchars((string)($closeout['reopened_by'] ?? '—')) ?></p>
                                <p>Lock state: <?= ((int)($closeout['is_locked'] ?? 0) === 1) ? 'Locked' : 'Open' ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                        <div class="rounded-xl border border-slate-200 p-3 min-h-[90px]">
                            <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest">Collected By</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3 min-h-[90px]">
                            <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest">Teacher/Assistant</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3 min-h-[90px]">
                            <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest">Supervisor</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>