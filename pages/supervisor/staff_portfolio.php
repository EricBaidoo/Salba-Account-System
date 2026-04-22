<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'supervisor' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../login');
    exit;
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$staff = null;

// Fetch Staff Profile & User Info
if ($user_id) {
    $prof_res = $conn->query("
        SELECT u.username, u.role, sp.* 
        FROM users u 
        LEFT JOIN staff_profiles sp ON u.id = sp.user_id 
        WHERE u.id = $user_id 
        LIMIT 1
    ");
    $staff = $prof_res->fetch_assoc();
}

// Fetch list of all facilitators for the dropdown selection
$facilitators = $conn->query("
    SELECT u.id, u.username, COALESCE(sp.full_name, u.username) as name, sp.job_title
    FROM users u
    LEFT JOIN staff_profiles sp ON u.id = sp.user_id
    WHERE u.role IN ('teacher', 'facilitator')
    ORDER BY name ASC
");

// If no user selected, don't show stats yet
$lesson_stats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
$lesson_history = [];
$attendance_stats = ['present' => 0, 'ontime' => 0];

if ($staff) {
    // Lesson Plan Stats
    $l_res = $conn->query("SELECT status, COUNT(*) as count FROM lesson_plans WHERE teacher_id = $user_id GROUP BY status");
    while($row = $l_res->fetch_assoc()) {
        $lesson_stats['total'] += $row['count'];
        $lesson_stats[$row['status']] = $row['count'];
    }

    // Lesson Plan History
    $lesson_history = $conn->query("
        SELECT l.*, s.name as subject_name 
        FROM lesson_plans l 
        JOIN subjects s ON l.subject_id = s.id 
        WHERE l.teacher_id = $user_id 
        ORDER BY l.created_at DESC LIMIT 30
    ");

    // Staff Attendance Stats (Simple summary)
    $a_res = $conn->query("SELECT COUNT(*) as count FROM staff_attendance WHERE user_id = $user_id");
    if($a_res) $attendance_stats['present'] = $a_res->fetch_assoc()['count'];
    
    $ontime_limit = getSystemSetting($conn, 'attendance_ontime_limit', '07:00');
    $ot_res = $conn->query("SELECT COUNT(*) as count FROM staff_attendance WHERE user_id = $user_id AND TIME(check_in_time) <= '$ontime_limit'");
    if($ot_res) $attendance_stats['ontime'] = $ot_res->fetch_assoc()['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Portfolio - Supervisor Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/top_nav.php'; ?>

    <main class="min-h-screen p-4 md:p-8 pt-20 md:pt-24 max-w-7xl mx-auto">
        
        <!-- Header & Selection -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-8">
            <div>
                <h1 class="text-3xl font-black text-gray-900 flex items-center gap-3">
                    <i class="fas fa-id-badge text-indigo-600"></i> Facilitator <span class="text-indigo-600">Portfolio</span>
                </h1>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-widest mt-1">Personnel Oversight & Professional Audit</p>
            </div>

            <form method="GET" class="w-full md:w-80">
                <label class="text-[0.625rem] font-black text-gray-400 uppercase mb-2 block">Select Facilitator to Audit</label>
                <div class="relative">
                    <select name="user_id" onchange="this.form.submit()" class="w-full bg-white border-2 border-gray-100 rounded-2xl px-5 py-3 font-bold text-sm text-gray-700 outline-none focus:border-indigo-500 transition-all shadow-sm appearance-none cursor-pointer">
                        <option value="">-- Choose Staff Member --</option>
                        <?php while($f = $facilitators->fetch_assoc()): ?>
                            <option value="<?= $f['id'] ?>" <?= $user_id == $f['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['name']) ?> (<?= htmlspecialchars($f['job_title'] ?? 'Teacher') ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <i class="fas fa-chevron-down absolute right-5 top-1/2 -translate-y-1/2 text-gray-300 pointer-events-none"></i>
                </div>
            </form>
        </div>

        <?php if (!$staff): ?>
            <div class="bg-white rounded-[2.5rem] border-2 border-dashed border-gray-100 p-20 text-center">
                <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 mx-auto mb-6">
                    <i class="fas fa-user-tie text-4xl"></i>
                </div>
                <h2 class="text-2xl font-black text-gray-400 uppercase tracking-tight">Select a staff member to view their records</h2>
            </div>
        <?php else: ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left Column: Staff Identity Card -->
                <div class="lg:col-span-4 space-y-6 lg:sticky lg:top-24">
                    <div class="bg-white rounded-[2.5rem] border border-gray-100 shadow-sm overflow-hidden group">
                        <div class="h-32 bg-indigo-600 relative overflow-hidden">
                            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full"></div>
                        </div>
                        <div class="px-8 pb-8 text-center -mt-16 relative">
                            <div class="w-32 h-32 bg-white rounded-[2.5rem] p-2 mx-auto shadow-xl mb-4 group-hover:scale-105 transition-transform duration-500">
                                <?php if(($staff['photo_path'] ?? '') && file_exists('../../'.$staff['photo_path'])): ?>
                                    <img src="../../<?= $staff['photo_path'] ?>" class="w-full h-full object-cover rounded-[2rem]">
                                <?php else: ?>
                                    <div class="w-full h-full bg-slate-100 rounded-[2rem] flex items-center justify-center text-slate-300">
                                        <i class="fas fa-user text-3xl"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h2 class="text-2xl font-black text-gray-900 leading-tight"><?= htmlspecialchars($staff['full_name'] ?: $staff['username']) ?></h2>
                            <div class="text-[0.625rem] font-black text-indigo-500 uppercase tracking-widest mt-2 bg-indigo-50 inline-block px-3 py-1 rounded-lg">
                                <?= htmlspecialchars($staff['job_title'] ?? 'Professional Instructor') ?>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mt-8 pt-8 border-t border-gray-100">
                                <div class="text-left">
                                    <div class="text-[0.5625rem] font-black text-gray-400 uppercase">Staff ID</div>
                                    <div class="font-bold text-gray-700 text-sm">#<?= htmlspecialchars($staff['staff_code'] ?? 'T-'.str_pad($staff['id'], 3, '0', STR_PAD_LEFT)) ?></div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[0.5625rem] font-black text-gray-400 uppercase">Department</div>
                                    <div class="font-bold text-gray-700 text-sm"><?= htmlspecialchars($staff['department'] ?: 'Academics') ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6 bg-gray-50/50 space-y-3">
                            <a href="../administration/staff_history.php?user_id=<?= $user_id ?>" class="flex items-center justify-between p-4 bg-white rounded-2xl border border-gray-100 hover:border-indigo-300 transition-all group/link">
                                <span class="text-xs font-bold text-gray-600 flex items-center gap-3">
                                    <i class="fas fa-clock-rotate-left text-indigo-500"></i> Attendance Ledger
                                </span>
                                <i class="fas fa-arrow-right text-[0.625rem] text-gray-300 group-hover/link:translate-x-1 transition-transform"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Performance Analysis -->
                <div class="lg:col-span-8 space-y-8">
                    
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
                            <div class="text-[0.5625rem] font-black text-gray-400 uppercase tracking-widest mb-2 font-black">Plan Volume</div>
                            <div class="text-2xl font-black text-gray-900"><?= $lesson_stats['total'] ?></div>
                            <div class="text-[0.5rem] font-bold text-gray-400 mt-1 uppercase">Total Submissions</div>
                        </div>
                        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm border-l-4 border-l-emerald-500">
                            <div class="text-[0.5625rem] font-black text-emerald-500 uppercase tracking-widest mb-2">Approved</div>
                            <div class="text-2xl font-black text-gray-900"><?= $lesson_stats['approved'] ?></div>
                            <div class="text-[0.5rem] font-bold text-emerald-600 mt-1 uppercase"><?= $lesson_stats['total'] > 0 ? round(($lesson_stats['approved']/$lesson_stats['total'])*100) : 0 ?>% Acceptance</div>
                        </div>
                        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
                            <div class="text-[0.5625rem] font-black text-gray-400 uppercase tracking-widest mb-2">Attend. Count</div>
                            <div class="text-2xl font-black text-gray-900"><?= $attendance_stats['present'] ?></div>
                            <div class="text-[0.5rem] font-bold text-gray-400 mt-1 uppercase">Days Logged</div>
                        </div>
                        <div class="bg-indigo-600 p-6 rounded-3xl shadow-lg shadow-indigo-100">
                            <div class="text-[0.5625rem] font-black text-indigo-100 uppercase tracking-widest mb-2">Punctuality</div>
                            <div class="text-2xl font-black text-white"><?= $attendance_stats['present'] > 0 ? round(($attendance_stats['ontime']/$attendance_stats['present'])*100) : 0 ?>%</div>
                            <div class="text-[0.5rem] font-bold text-indigo-200 mt-1 uppercase">On-Time Rate</div>
                        </div>
                    </div>

                    <!-- Lesson Plan Timeline -->
                    <div class="bg-white rounded-[2.5rem] border border-gray-100 shadow-sm p-8">
                        <div class="flex justify-between items-center mb-8 border-b border-gray-50 pb-4">
                            <div>
                                <h3 class="text-xl font-black text-gray-900 tracking-tight">Deployment Timeline</h3>
                                <p class="text-[0.625rem] font-bold text-gray-400 uppercase mt-1">Lesson Plan History & Review Status</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <?php if($lesson_history && $lesson_history->num_rows > 0): while($l = $lesson_history->fetch_assoc()): ?>
                                <div class="group relative pl-8 pb-6 border-l-2 border-gray-100 last:border-0 last:pb-0">
                                    <div class="absolute -left-[0.5625rem] top-0 w-4 h-4 rounded-full border-4 border-white shadow-sm
                                        <?= $l['status'] === 'approved' ? 'bg-emerald-500' : ($l['status'] === 'rejected' ? 'bg-red-500' : 'bg-yellow-400') ?>">
                                    </div>
                                    <div class="bg-gray-50/50 group-hover:bg-white rounded-2xl border border-gray-100 p-5 transition-all group-hover:shadow-md group-hover:border-indigo-100">
                                        <div class="flex justify-between items-start mb-3">
                                            <div>
                                                <div class="text-[0.625rem] font-black text-indigo-500 uppercase tracking-widest mb-1"><?= htmlspecialchars($l['subject_name']) ?> <span class="mx-1 text-gray-300">•</span> Week <?= $l['week_number'] ?></div>
                                                <h4 class="font-black text-gray-900 leading-tight"><?= htmlspecialchars($l['topic']) ?></h4>
                                            </div>
                                            <div class="flex gap-2">
                                                <a href="../teacher/print_lesson_plan.php?id=<?= $l['id'] ?>&view=html" target="_blank" class="w-8 h-8 bg-white border border-gray-200 rounded-lg flex items-center justify-center text-gray-400 hover:text-indigo-600 transition-colors">
                                                    <i class="fas fa-eye text-xs"></i>
                                                </a>
                                                <a href="../teacher/print_lesson_plan.php?id=<?= $l['id'] ?>" target="_blank" class="w-8 h-8 bg-white border border-gray-200 rounded-lg flex items-center justify-center text-gray-400 hover:text-red-500 transition-colors">
                                                    <i class="fas fa-file-pdf text-xs"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-4 text-[0.625rem] font-bold text-gray-400 uppercase tracking-tight">
                                            <span class="flex items-center gap-1.5"><i class="fas fa-calendar-day opacity-50"></i> <?= date('M j, Y', strtotime($l['created_at'])) ?></span>
                                            <span class="flex items-center gap-1.5"><i class="fas fa-layer-group opacity-50"></i> <?= htmlspecialchars($l['class_name']) ?></span>
                                        </div>
                                        
                                        <?php if($l['supervisor_comments']): ?>
                                            <div class="mt-4 p-3 bg-white rounded-xl border border-indigo-50 italic text-[0.7rem] text-slate-500 leading-relaxed shadow-sm">
                                                <span class="font-black text-indigo-300 mr-1">"</span><?= htmlspecialchars($l['supervisor_comments']) ?><span class="font-black text-indigo-300 ml-1">"</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; else: ?>
                                <div class="text-center py-10">
                                    <i class="fas fa-folder-open text-3xl text-gray-200 mb-2"></i>
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">No plans in archive</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
