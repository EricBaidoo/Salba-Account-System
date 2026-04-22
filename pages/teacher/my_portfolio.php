<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || !in_array($_SESSION['role'], ['teacher', 'facilitator'])) {
    header('Location: ../../login');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch Profile Data
$prof_res = $conn->query("SELECT u.username, u.role, sp.* FROM users u LEFT JOIN staff_profiles sp ON u.id = sp.user_id WHERE u.id = $user_id LIMIT 1");
$staff = $prof_res->fetch_assoc();

// Lesson Plan Stats
$lesson_stats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
$l_res = $conn->query("SELECT status, COUNT(*) as count FROM lesson_plans WHERE teacher_id = $user_id GROUP BY status");
while($row = $l_res->fetch_assoc()) {
    $lesson_stats['total'] += $row['count'];
    $lesson_stats[$row['status']] = $row['count'];
}

// Attendance Stats
$ontime_limit = getSystemSetting($conn, 'attendance_ontime_limit', '07:00');
$attendance_stats = [
    'present' => $conn->query("SELECT COUNT(*) FROM staff_attendance WHERE user_id = $user_id")->fetch_row()[0],
    'ontime' => $conn->query("SELECT COUNT(*) FROM staff_attendance WHERE user_id = $user_id AND TIME(check_in_time) <= '$ontime_limit'")->fetch_row()[0]
];

// Full Lesson Plan Archive
$lesson_history = $conn->query("
    SELECT l.*, s.name as subject_name 
    FROM lesson_plans l 
    JOIN subjects s ON l.subject_id = s.id 
    WHERE l.teacher_id = $user_id 
    ORDER BY l.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Professional Portfolio | Teacher Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/top_nav.php'; ?>

    <main class="min-h-screen p-4 md:p-8 pt-20 md:pt-24 max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-6 mb-12">
            <div>
                <h1 class="text-4xl font-black text-gray-900 tracking-tight">My <span class="text-indigo-600">Professional</span> Portfolio</h1>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-widest mt-2">Comprehensive teaching records & performance metrics</p>
            </div>
            <div class="flex gap-4">
                <a href="lesson_plans.php" class="bg-indigo-600 text-white px-6 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-indigo-700 transition shadow-lg shadow-indigo-100 flex items-center gap-2">
                    <i class="fas fa-plus"></i> New Lesson Note
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Sidebar: Profile & Quick Stats -->
            <div class="lg:col-span-4 space-y-6 lg:sticky lg:top-24">
                <div class="bg-white rounded-[2.5rem] border border-gray-100 shadow-sm p-8 text-center">
                    <div class="w-32 h-32 bg-slate-100 rounded-[2.5rem] p-1.5 mx-auto mb-6 shadow-xl border-4 border-white overflow-hidden">
                        <?php if(($staff['photo_path'] ?? '') && file_exists('../../'.$staff['photo_path'])): ?>
                            <img src="../../<?= $staff['photo_path'] ?>" class="w-full h-full object-cover rounded-[2.1rem]">
                        <?php else: ?>
                            <div class="w-full h-full bg-indigo-50 rounded-[2.1rem] flex items-center justify-center text-indigo-200 text-4xl">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 leading-tight"><?= htmlspecialchars($staff['full_name'] ?: $staff['username']) ?></h3>
                    <div class="text-[0.625rem] font-black text-indigo-500 uppercase tracking-widest mt-2">
                        <?= htmlspecialchars($staff['job_title'] ?? 'Professional Instructor') ?>
                    </div>
                    
                    <div class="p-6 bg-gray-50/50 space-y-3 mt-6 rounded-2xl">
                        <a href="my_attendance.php" class="flex items-center justify-between p-4 bg-white rounded-2xl border border-gray-100 hover:border-indigo-300 transition-all group/link shadow-sm">
                            <span class="text-xs font-bold text-gray-600 flex items-center gap-3">
                                <i class="fas fa-clock-rotate-left text-indigo-500"></i> My Attendance
                            </span>
                            <i class="fas fa-arrow-right text-[0.625rem] text-gray-300 group-hover/link:translate-x-1 transition-transform"></i>
                        </a>
                        <a href="../common/profile.php" class="flex items-center justify-between p-4 bg-white rounded-2xl border border-gray-100 hover:border-indigo-300 transition-all group/link shadow-sm">
                            <span class="text-xs font-bold text-gray-600 flex items-center gap-3">
                                <i class="fas fa-user-gear text-indigo-500"></i> My Profile
                            </span>
                            <i class="fas fa-arrow-right text-[0.625rem] text-gray-300 group-hover/link:translate-x-1 transition-transform"></i>
                        </a>
                    </div>

                    
                    <div class="grid grid-cols-2 gap-4 mt-8 pt-8 border-t border-gray-100">
                        <div class="text-left">
                            <div class="text-[0.5625rem] font-black text-gray-400 uppercase">Employment ID</div>
                            <div class="font-bold text-gray-700">#<?= htmlspecialchars($staff['staff_code'] ?? 'T-'.str_pad($user_id, 3, '0', STR_PAD_LEFT)) ?></div>
                        </div>
                        <div class="text-right">
                            <div class="text-[0.5625rem] font-black text-gray-400 uppercase">Punctuality</div>
                            <div class="font-bold text-emerald-600"><?= $attendance_stats['present'] > 0 ? round(($attendance_stats['ontime']/$attendance_stats['present'])*100) : 0 ?>%</div>
                        </div>
                    </div>
                </div>

                <div class="bg-indigo-600 rounded-[2.5rem] p-8 text-white shadow-xl shadow-indigo-100">
                    <h4 class="text-xs font-black uppercase tracking-widest mb-6 opacity-70">At a Glance</h4>
                    <div class="space-y-6">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-bold opacity-80">Total Lesson Notes</span>
                            <span class="text-xl font-black"><?= $lesson_stats['total'] ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-bold opacity-80">Approved</span>
                            <span class="text-xl font-black"><?= $lesson_stats['approved'] ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-bold opacity-80">Attendance Rate</span>
                            <span class="text-xl font-black"><?= $attendance_stats['present'] ?> Days</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content: Archive -->
            <div class="lg:col-span-8">
                <div class="bg-white rounded-[2.5rem] border border-gray-100 shadow-sm p-8">
                    <h3 class="text-xl font-black text-gray-900 tracking-tight mb-8">Lesson Note Archive</h3>
                    
                    <div class="space-y-4">
                        <?php if($lesson_history && $lesson_history->num_rows > 0): while($l = $lesson_history->fetch_assoc()): ?>
                            <div class="group flex flex-col md:flex-row items-start md:items-center gap-6 p-6 rounded-3xl border border-gray-50 hover:border-indigo-100 hover:bg-indigo-50/10 transition-all">
                                <div class="w-14 h-14 bg-white rounded-2xl border border-gray-100 flex items-center justify-center text-indigo-500 shadow-sm group-hover:rotate-6 transition-transform">
                                    <i class="fas fa-file-lines text-2xl"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <span class="text-[0.625rem] font-black text-indigo-500 uppercase tracking-widest"><?= htmlspecialchars($l['subject_name']) ?></span>
                                        <span class="text-[0.625rem] font-bold text-gray-300">|</span>
                                        <span class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest">Week <?= $l['week_number'] ?></span>
                                        <?php if($l['status'] === 'approved'): ?>
                                            <span class="text-[0.5625rem] font-black text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded uppercase ml-auto">Approved</span>
                                        <?php elseif($l['status'] === 'rejected'): ?>
                                            <span class="text-[0.5625rem] font-black text-red-600 bg-red-50 px-2 py-0.5 rounded uppercase ml-auto">Rejected</span>
                                        <?php else: ?>
                                            <span class="text-[0.5625rem] font-black text-yellow-600 bg-yellow-50 px-2 py-0.5 rounded uppercase ml-auto">Pending</span>
                                        <?php endif; ?>
                                    </div>
                                    <h4 class="text-lg font-black text-gray-900 leading-tight mb-2"><?= htmlspecialchars($l['topic']) ?></h4>
                                    <div class="flex items-center gap-4 text-[0.6875rem] font-bold text-gray-400">
                                        <span><i class="fas fa-layer-group text-[0.625rem] mr-1"></i> <?= htmlspecialchars($l['class_name']) ?></span>
                                        <span><i class="fas fa-clock text-[0.625rem] mr-1"></i> <?= date('M j, Y', strtotime($l['created_at'])) ?></span>
                                    </div>
                                </div>
                                <div class="flex gap-2 w-full md:w-auto mt-4 md:mt-0">
                                    <a href="print_lesson_plan.php?id=<?= $l['id'] ?>&view=html" target="_blank" class="flex-1 md:flex-none h-11 px-4 bg-white border border-gray-200 rounded-xl flex items-center justify-center text-gray-400 hover:text-indigo-600 transition shadow-sm">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>
                                    <a href="print_lesson_plan.php?id=<?= $l['id'] ?>" target="_blank" class="flex-1 md:flex-none h-11 px-4 bg-white border border-gray-200 rounded-xl flex items-center justify-center text-gray-400 hover:text-red-500 transition shadow-sm">
                                        <i class="fas fa-file-pdf text-sm"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; else: ?>
                            <div class="text-center py-20 text-gray-300">
                                <i class="fas fa-box-open text-4xl mb-4"></i>
                                <div class="text-xs font-black uppercase tracking-widest">No submission history found</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
