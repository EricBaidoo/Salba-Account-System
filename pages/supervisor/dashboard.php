<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'supervisor' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../login');
    exit;
}

$uid = $_SESSION['user_id'];
$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);

// Fetch Profile Data
$prof_res = $conn->query("SELECT full_name, photo_path, job_title FROM staff_profiles WHERE user_id = $uid LIMIT 1");
$profile = $prof_res->fetch_assoc();
$display_name = $profile['full_name'] ?? $_SESSION['username'];
$job_title = $profile['job_title'] ?? 'Academic Supervisor';
$photo = $profile['photo_path'] ?? '';

// Fetch Stats
$pending_plans = $conn->query("SELECT COUNT(*) FROM lesson_plans WHERE status='pending'")->fetch_row()[0];
$active_classes = $conn->query("SELECT COUNT(DISTINCT class) FROM students WHERE status='active'")->fetch_row()[0];
$total_students = $conn->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetch_row()[0];

// Total Staff Today
$today = date('Y-m-d');
$staff_today = $conn->query("SELECT COUNT(DISTINCT user_id) FROM staff_attendance WHERE DATE(check_in_time) = '$today'")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Hub | SALBA Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/top_nav.php'; ?>

    <main class="w-full">
        <!-- Profile Banner -->
        <div class="bg-indigo-700 px-8 py-20 text-white relative overflow-hidden">
            <div class="absolute right-0 top-0 opacity-10 pointer-events-none p-4"><i class="fas fa-eye text-[15rem]"></i></div>
            <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center gap-10 relative z-10 text-center md:text-left">
                <div class="w-40 h-40 rounded-[2.5rem] bg-white/20 backdrop-blur-md flex items-center justify-center border border-white/30 overflow-hidden shadow-2xl">
                    <?php if($photo): ?><img src="../../<?= htmlspecialchars($photo) ?>" class="w-full h-full object-cover">
                    <?php else: ?><i class="fas fa-user-shield text-6xl"></i><?php endif; ?>
                </div>
                <div>
                    <h1 class="text-6xl font-black tracking-tight leading-tight">Welcome Back, <?= htmlspecialchars($display_name) ?>!</h1>
                    <div class="flex flex-wrap items-center justify-center md:justify-start gap-4 mt-6">
                        <span class="bg-white/20 px-5 py-2 rounded-full text-sm font-bold tracking-widest backdrop-blur-sm border border-white/20 text-indigo-50"><?= htmlspecialchars($job_title) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-6xl mx-auto p-10 -mt-10">
            <!-- Stats Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 group hover:border-indigo-500 transition-all">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Pending Approvals</div>
                        <i class="fas fa-file-signature text-emerald-500 opacity-40"></i>
                    </div>
                    <div class="text-5xl font-black text-gray-900"><?= $pending_plans ?></div>
                </div>
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 group hover:border-indigo-500 transition-all">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Active Classes</div>
                        <i class="fas fa-school text-blue-500 opacity-40"></i>
                    </div>
                    <div class="text-5xl font-black text-gray-900"><?= $active_classes ?></div>
                </div>
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 group hover:border-indigo-500 transition-all">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total Students</div>
                        <i class="fas fa-users-viewfinder text-purple-500 opacity-40"></i>
                    </div>
                    <div class="text-5xl font-black text-gray-900"><?= $total_students ?></div>
                </div>
            </div>

            <!-- Management Hub Section -->
            <h2 class="text-3xl font-black text-gray-900 mb-10 flex items-center gap-4 tracking-tight">
                <span class="w-2 h-10 bg-indigo-700 rounded-full"></span>
                Oversight & Monitoring
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-16">
                <!-- Card: GPS Check-In -->
                <a href="<?= BASE_URL ?>pages/teacher/check_in" class="relative group bg-white rounded-[2rem] p-8 border border-gray-100 shadow-sm hover:shadow-2xl hover:border-red-300 transition-all duration-500 overflow-hidden">
                    <div class="flex flex-col items-center gap-4 relative z-10 text-center">
                        <div class="w-16 h-16 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center text-3xl shadow-inner group-hover:scale-110 transition-all"><i class="fas fa-location-dot"></i></div>
                        <div>
                            <h3 class="text-lg font-black text-gray-900 leading-none tracking-tight">Daily Check-In</h3>
                            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-2">Campus Verification</p>
                        </div>
                    </div>
                </a>

                <!-- Card: Staff Presence -->
                <a href="<?= BASE_URL ?>pages/administration/staff_attendance" class="relative group bg-white rounded-[2rem] p-8 border border-indigo-100 shadow-sm hover:shadow-2xl hover:border-indigo-300 transition-all duration-500 overflow-hidden bg-indigo-50/10">
                    <div class="flex flex-col items-center gap-4 relative z-10 text-center">
                        <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-3xl shadow-inner group-hover:scale-110 transition-all"><i class="fas fa-users-rectangle"></i></div>
                        <div>
                            <h3 class="text-lg font-black text-gray-900 leading-none tracking-tight">Staff Presence</h3>
                            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-2"><?= $staff_today ?> Logged Today</p>
                        </div>
                    </div>
                </a>

                <!-- Card: Student Attendance (RESTORED) -->
                <a href="<?= BASE_URL ?>pages/academics/attendance" class="relative group bg-white rounded-[2rem] p-8 border border-blue-100 shadow-sm hover:shadow-2xl hover:border-blue-300 transition-all duration-500 overflow-hidden">
                    <div class="flex flex-col items-center gap-4 relative z-10 text-center">
                        <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-3xl shadow-inner group-hover:scale-110 transition-all"><i class="fas fa-clipboard-user"></i></div>
                        <div>
                            <h3 class="text-lg font-black text-gray-900 leading-none tracking-tight">Student Attendance</h3>
                            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-2">Global Tracking</p>
                        </div>
                    </div>
                </a>

                <!-- Card: Lesson Plan Approvals -->
                <a href="<?= BASE_URL ?>pages/supervisor/lesson_plans" class="relative group bg-white rounded-[2rem] p-8 border border-gray-100 shadow-sm hover:shadow-2xl hover:border-emerald-300 transition-all duration-500 overflow-hidden text-center">
                    <div class="flex flex-col items-center gap-4 relative z-10">
                        <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-3xl shadow-inner group-hover:scale-110 transition-all"><i class="fas fa-file-circle-check"></i></div>
                        <div>
                            <h3 class="text-lg font-black text-gray-900 leading-none tracking-tight">Review Plans</h3>
                            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-2"><?= $pending_plans ?> Drafts Pending</p>
                        </div>
                    </div>
                </a>
            </div>

            <h2 class="text-3xl font-black text-gray-900 mb-10 flex items-center gap-4 tracking-tight">
                <span class="w-2 h-10 bg-indigo-700 rounded-full"></span>
                Academic Portfolio
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <a href="<?= BASE_URL ?>pages/academics/transcripts" class="bg-white rounded-[2rem] p-8 border border-gray-100 shadow-sm hover:shadow-xl hover:border-indigo-500 transition-all duration-300 group">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center text-xl shadow-inner"><i class="fas fa-file-invoice"></i></div>
                        <h3 class="text-lg font-black text-gray-900 tracking-tight">Manage Reports</h3>
                    </div>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Global transcript oversight and remarks.</p>
                </a>
                <a href="<?= BASE_URL ?>pages/academics/report" class="bg-white rounded-[2rem] p-8 border border-gray-100 shadow-sm hover:shadow-xl hover:border-indigo-500 transition-all duration-300 group">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 bg-orange-50 text-orange-600 rounded-2xl flex items-center justify-center text-xl shadow-inner"><i class="fas fa-chart-pie"></i></div>
                        <h3 class="text-lg font-black text-gray-900 tracking-tight">Global Reports</h3>
                    </div>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Institutional performance analytics.</p>
                </a>
                <a href="<?= BASE_URL ?>pages/administration/system_settings" class="bg-white rounded-[2rem] p-8 border border-gray-100 shadow-sm hover:shadow-xl hover:border-indigo-500 transition-all duration-300 group">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 bg-gray-50 text-gray-500 rounded-2xl flex items-center justify-center text-xl shadow-inner"><i class="fas fa-gears"></i></div>
                        <h3 class="text-lg font-black text-gray-900 tracking-tight">Academic Rules</h3>
                    </div>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Grading scales and assessment logic.</p>
                </a>
            </div>
        </div>
    </main>
</body>
</html>

<?php
// ... end of file
?>
