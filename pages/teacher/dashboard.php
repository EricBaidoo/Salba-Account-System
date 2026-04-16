<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || $_SESSION['role'] !== 'facilitator') {
    header('Location: ../../includes/login.php');
    exit;
}

$uid = $_SESSION['user_id'];
$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);

// Fetch Profile Data
$prof_res = $conn->query("SELECT full_name, photo_path, job_title FROM staff_profiles WHERE user_id = $uid LIMIT 1");
$profile = $prof_res->fetch_assoc();
$display_name = $profile['full_name'] ?? $_SESSION['username'];
$job_title = $profile['job_title'] ?? 'Facilitator';
$photo = $profile['photo_path'] ?? '';

// Fetch Stats
$class_count = $conn->query("SELECT COUNT(DISTINCT class_name) FROM teacher_allocations WHERE teacher_id = $uid AND year = '$current_year'")->fetch_row()[0];
$student_count = $conn->query("SELECT COUNT(id) FROM students WHERE class COLLATE utf8mb4_unicode_ci IN (SELECT DISTINCT class_name COLLATE utf8mb4_unicode_ci FROM teacher_allocations WHERE teacher_id = $uid AND year = '$current_year') AND status='active'")->fetch_row()[0];
$lesson_plans = $conn->query("SELECT COUNT(*) FROM lesson_plans WHERE teacher_id = $uid AND status='approved'")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Hub | SALBA Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/top_nav.php'; ?>

    <main class="w-full">
        <!-- Profile Banner -->
        <div class="bg-indigo-600 px-8 py-16 text-white relative overflow-hidden">
            <div class="absolute right-0 top-0 opacity-10 pointer-events-none p-4">
                <i class="fas fa-graduation-cap text-[15rem]"></i>
            </div>
            <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center gap-8 relative z-10 text-center md:text-left">
                <div class="w-32 h-32 rounded-[2rem] bg-white/20 backdrop-blur-md flex items-center justify-center border border-white/30 overflow-hidden shadow-2xl">
                    <?php if($photo): ?>
                        <img src="../../<?= htmlspecialchars($photo) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fas fa-user-tie text-5xl"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="text-5xl font-black tracking-tight leading-tight">Welcome Back, <br class="md:hidden"><?= htmlspecialchars($display_name) ?>!</h1>
                    <div class="flex flex-wrap items-center justify-center md:justify-start gap-3 mt-4">
                        <span class="bg-white/20 px-4 py-1.5 rounded-full text-sm font-bold uppercase tracking-wider backdrop-blur-sm border border-white/20 text-indigo-50"><?= htmlspecialchars($job_title) ?></span>
                        <div class="h-6 w-px bg-white/20 hidden md:block"></div>
                        <span class="text-indigo-100 font-medium flex items-center gap-2"><i class="fas fa-calendar-check opacity-60"></i> <?= $current_term ?> · <?= $current_year ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-6xl mx-auto p-8 -mt-8">
            <!-- Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-5">
                    <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-2xl shadow-inner">
                        <i class="fas fa-user-group"></i>
                    </div>
                    <div>
                        <div class="text-3xl font-black text-gray-900"><?= $student_count ?></div>
                        <div class="text-xs font-bold text-gray-400 uppercase tracking-widest">Active Students</div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-5">
                    <div class="w-14 h-14 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center text-2xl shadow-inner">
                        <i class="fas fa-school"></i>
                    </div>
                    <div>
                        <div class="text-3xl font-black text-gray-900"><?= $class_count ?></div>
                        <div class="text-xs font-bold text-gray-400 uppercase tracking-widest">Assigned Classes</div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-5">
                    <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-2xl shadow-inner">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div>
                        <div class="text-3xl font-black text-gray-900"><?= $lesson_plans ?></div>
                        <div class="text-xs font-bold text-gray-400 uppercase tracking-widest">Approved Plans</div>
                    </div>
                </div>
            </div>

            <!-- Navigation Grid -->
            <h2 class="text-2xl font-black text-gray-900 mb-8 flex items-center gap-3 lowercase tracking-tighter">
                <span class="w-2 h-8 bg-indigo-600 rounded-full"></span>
                academic command center
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Card: Gradebook -->
                <a href="grades.php" class="group bg-white rounded-3xl p-8 border border-gray-100 shadow-sm hover:shadow-2xl hover:border-yellow-300 transition-all duration-300 relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <i class="fas fa-star text-8xl text-yellow-500"></i>
                    </div>
                    <div class="w-16 h-16 bg-yellow-50 text-yellow-500 rounded-2xl flex items-center justify-center text-3xl mb-6 shadow-inner group-hover:scale-110 transition-transform">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-2">My Gradebook</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Input raw scores and auto-scale student grades for transcripts.</p>
                </a>

                <!-- Card: Attendance -->
                <a href="../academics/attendance.php" class="group bg-white rounded-3xl p-8 border border-gray-100 shadow-sm hover:shadow-2xl hover:border-blue-300 transition-all duration-300 relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <i class="fas fa-clipboard-user text-8xl text-blue-500"></i>
                    </div>
                    <div class="w-16 h-16 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center text-3xl mb-6 shadow-inner group-hover:scale-110 transition-transform">
                        <i class="fas fa-clipboard-user"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-2">Daily Attendance</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Track daily student presence and generate attendance reports.</p>
                </a>

                <!-- Card: Lesson Plans -->
                <a href="lesson_plans.php" class="group bg-white rounded-3xl p-8 border border-gray-100 shadow-sm hover:shadow-2xl hover:border-emerald-300 transition-all duration-300 relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <i class="fas fa-file-contract text-8xl text-emerald-500"></i>
                    </div>
                    <div class="w-16 h-16 bg-emerald-50 text-emerald-500 rounded-2xl flex items-center justify-center text-3xl mb-6 shadow-inner group-hover:scale-110 transition-transform">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-2">Lesson Planning</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Draft and submit weekly schemes of work for supervisor review.</p>
                </a>

                <!-- Card: Transcripts -->
                <a href="../academics/transcripts.php" class="group bg-white rounded-3xl p-8 border border-gray-100 shadow-sm hover:shadow-2xl hover:border-purple-300 transition-all duration-300 relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <i class="fas fa-scroll text-8xl text-purple-500"></i>
                    </div>
                    <div class="w-16 h-16 bg-purple-50 text-purple-500 rounded-2xl flex items-center justify-center text-3xl mb-6 shadow-inner group-hover:scale-110 transition-transform">
                        <i class="fas fa-scroll"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-2">Student Transcripts</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">View generated official semester reports for your allocated classes.</p>
                </a>

                <!-- Card: Check-in -->
                <a href="check_in.php" class="group bg-white rounded-3xl p-8 border border-gray-100 shadow-sm hover:shadow-2xl hover:border-red-300 transition-all duration-300 relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <i class="fas fa-location-dot text-8xl text-red-500"></i>
                    </div>
                    <div class="w-16 h-16 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center text-3xl mb-6 shadow-inner group-hover:scale-110 transition-transform">
                        <i class="fas fa-location-dot"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-2">Daily Check-In</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Log your daily school arrival and departure status with GPS verification.</p>
                </a>
            </div>
        </div>
    </main>

    <footer class="text-center py-10 text-gray-400 text-xs font-bold uppercase tracking-widest bg-gray-50">
        &copy; <?= date('Y') ?> SALBA Montessori · Management Information System
    </footer>

</body>
</html>
