<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../login');
    exit;
}

$current_term = getCurrentSemester($conn);
$academic_year = getAcademicYear($conn);

// Get academic statistics
$total_students = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM students WHERE status='active'");
if($res) $total_students = $res->fetch_assoc()['cnt'];

$total_classes = 0;
$res = $conn->query("SELECT COUNT(DISTINCT class) as cnt FROM students WHERE status='active' AND class IS NOT NULL");
if($res) $total_classes = $res->fetch_assoc()['cnt'];

// Calculate average attendance for the current semester!
$avg_attendance = 0;
$att_res = $conn->prepare("
    SELECT AVG(presence_rate) as avg_attendance 
    FROM (
        SELECT (SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) / COUNT(*)) * 100 as presence_rate
        FROM attendance
        WHERE semester = ? AND academic_year = ?
        GROUP BY student_id
    ) as attendance_rates
");
if($att_res) {
    $att_res->bind_param("ss", $current_term, $academic_year);
    $att_res->execute();
    $avg_attendance = $att_res->get_result()->fetch_assoc()['avg_attendance'] ?? 0;
    $att_res->close();
}

// Get class-wise performance for current semester
$class_performance = $conn->prepare("
    SELECT s.class, 
           COUNT(DISTINCT g.student_id) as students_graded,
           AVG(CASE WHEN g.out_of > 0 THEN (g.marks / g.out_of) * 100 ELSE 0 END) as avg_grade_pct
    FROM grades g
    JOIN students s ON g.student_id = s.id
    WHERE g.semester = ? AND g.year = ?
    GROUP BY s.class
    ORDER BY s.class
");
$performance_data = [];
if($class_performance) {
    $class_performance->bind_param("ss", $current_term, $academic_year);
    $class_performance->execute();
    $res = $class_performance->get_result();
    while($row = $res->fetch_assoc()) {
        $performance_data[] = $row;
    }
    $class_performance->close();
}

// System grades recorded count
$grades_recorded = 0;
$g_res = $conn->prepare("SELECT COUNT(*) as cnt FROM grades WHERE semester = ? AND year = ?");
if($g_res) {
    $g_res->bind_param("ss", $current_term, $academic_year);
    $g_res->execute();
    $grades_recorded = $g_res->get_result()->fetch_assoc()['cnt'] ?? 0;
    $g_res->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Analytics - Academics Module</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php 
    if ($_SESSION['role'] === 'admin') {
        include '../../includes/sidebar.php';
    } else {
        include '../../includes/top_nav.php';
    }
    ?>

    <main class="admin-main-content <?= $_SESSION['role'] === 'admin' ? 'lg:ml-72' : '' ?> p-4 md:p-8 min-h-screen relative">
        <!-- Header Section -->
        <div class="bg-white border-b border-gray-100 px-8 py-6 sticky top-0 z-30">
            <div class="flex items-center gap-3 mb-4">
                <a href="dashboard.php" class="text-gray-400 hover:text-blue-600 transition-colors flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i> Back to Academics Dashboard
                </a>
            </div>
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                        <i class="fas fa-chart-line text-blue-500"></i> Academic Reports & Analytics
                    </h1>
                    <p class="text-gray-500 mt-2 text-sm">
                        School-wide academic analytics and performance for <strong><?= htmlspecialchars($current_term . ' ' . $academic_year) ?></strong>
                    </p>
                </div>
            </div>
        </div>

        <div class="p-8 max-w-7xl">
            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col items-center justify-center text-center">
                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center text-xl mb-3">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <p class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-1">Active Students</p>
                    <p class="text-4xl font-extrabold text-gray-900"><?= $total_students ?></p>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col items-center justify-center text-center">
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center text-xl mb-3">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <p class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-1">Current Classes</p>
                    <p class="text-4xl font-extrabold text-gray-900"><?= $total_classes ?></p>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col items-center justify-center text-center">
                    <div class="w-12 h-12 bg-yellow-50 text-yellow-600 rounded-full flex items-center justify-center text-xl mb-3">
                        <i class="fas fa-clock"></i>
                    </div>
                    <p class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-1">Avg Attendance</p>
                    <p class="text-4xl font-extrabold text-gray-900"><?= round($avg_attendance, 1) ?><span class="text-xl text-gray-400 font-medium ml-1">%</span></p>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col items-center justify-center text-center">
                    <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-full flex items-center justify-center text-xl mb-3">
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-1">Grades Recorded</p>
                    <p class="text-4xl font-extrabold text-gray-900"><?= number_format($grades_recorded) ?></p>
                </div>
            </div>

            <!-- Focus Content -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Class Performance Table -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="px-6 py-5 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h2 class="font-bold text-gray-800 flex items-center gap-2">
                                <i class="fas fa-ranking-star text-yellow-500"></i> Class-wise Academic Performance
                            </h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-sm">
                                <thead>
                                    <tr class="bg-white border-b border-gray-100 text-gray-500 uppercase text-[10px] uppercase font-extrabold tracking-widest">
                                        <th class="px-6 py-4">Class Target</th>
                                        <th class="px-6 py-4 text-center">Students Scored</th>
                                        <th class="px-6 py-4 text-center border-l border-gray-50">Class Average</th>
                                        <th class="px-6 py-4">Status Indicator</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <?php if(empty($performance_data)): ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                                <div class="w-12 h-12 bg-gray-50 text-gray-300 rounded-full flex items-center justify-center mx-auto mb-3 text-xl">
                                                    <i class="fas fa-folder-open"></i>
                                                </div>
                                                <p>No performance data exists for this semester.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($performance_data as $row): 
                                            $avg = $row['avg_grade_pct'];
                                            $color = 'text-green-600 bg-green-50 border-green-200';
                                            $msg = 'Excellent';
                                            $icon = 'fa-arrow-up';
                                            
                                            if ($avg < 50) {
                                                $color = 'text-red-600 bg-red-50 border-red-200';
                                                $msg = 'Critical';
                                                $icon = 'fa-arrow-down';
                                            } elseif ($avg < 70) {
                                                $color = 'text-yellow-600 bg-yellow-50 border-yellow-200';
                                                $msg = 'Average';
                                                $icon = 'fa-minus';
                                            }
                                        ?>
                                            <tr class="hover:bg-gray-50/50 transition duration-150">
                                                <td class="px-6 py-4 font-bold text-gray-900 border-r border-gray-50">
                                                    <?= htmlspecialchars($row['class'] ?? 'Unassigned') ?>
                                                </td>
                                                <td class="px-6 py-4 text-center font-medium text-gray-500">
                                                    <?= $row['students_graded'] ?> students
                                                </td>
                                                <td class="px-6 py-4 text-center border-l border-gray-50">
                                                    <div class="text-xl font-extrabold text-gray-900">
                                                        <?= number_format($avg, 1) ?><span class="text-sm font-bold text-gray-400 ml-0.5">%</span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border <?= $color ?>">
                                                        <i class="fas <?= $icon ?>"></i> <?= $msg ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Info panel -->
                <div class="lg:col-span-1 border border-blue-100 bg-blue-50/30 rounded-xl p-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-8 opacity-5">
                        <i class="fas fa-chart-pie text-9xl text-blue-600"></i>
                    </div>
                    <h3 class="font-bold text-gray-900 text-lg mb-4 relative z-10">Export Records</h3>
                    <p class="text-sm text-gray-600 mb-6 relative z-10 leading-relaxed">
                        Need physical copies of these analytics? Generate official PDF reports or export underlying data constraints directly to Excel.
                    </p>
                    <div class="space-y-3 relative z-10">
                        <button class="w-full bg-white border border-gray-200 text-gray-700 font-medium px-4 py-3 rounded-lg hover:bg-gray-50 transition shadow-sm flex items-center justify-center gap-2">
                            <i class="fas fa-file-pdf text-red-500"></i> Download PDF Summary
                        </button>
                        <button class="w-full bg-white border border-gray-200 text-gray-700 font-medium px-4 py-3 rounded-lg hover:bg-gray-50 transition shadow-sm flex items-center justify-center gap-2">
                            <i class="fas fa-file-excel text-green-600"></i> Export Datasets (CSV)
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </main>

</body>
</html>
