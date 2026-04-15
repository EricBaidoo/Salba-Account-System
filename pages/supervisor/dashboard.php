<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'supervisor' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../includes/login.php');
    exit;
}

$uid = $_SESSION['user_id'];
$current_term = getCurrentTerm($conn);
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
            <div class="absolute right-0 top-0 opacity-10 pointer-events-none p-4">
                <i class="fas fa-eye text-[15rem]"></i>
            </div>
            <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center gap-10 relative z-10 text-center md:text-left">
                <div class="w-40 h-40 rounded-[2.5rem] bg-white/20 backdrop-blur-md flex items-center justify-center border border-white/30 overflow-hidden shadow-2xl">
                    <?php if($photo): ?>
                        <img src="../../<?= htmlspecialchars($photo) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fas fa-user-shield text-6xl"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="text-6xl font-black tracking-tight leading-tight">Welcome Back, <br class="md:hidden"><?= htmlspecialchars($display_name) ?>!</h1>
                    <div class="flex flex-wrap items-center justify-center md:justify-start gap-4 mt-6">
                        <span class="bg-white/20 px-5 py-2 rounded-full text-base font-bold tracking-wider backdrop-blur-sm border border-white/20 text-indigo-50"><?= htmlspecialchars($job_title) ?></span>
                        <div class="h-8 w-px bg-white/20 hidden md:block"></div>
                        <span class="text-indigo-100 text-lg font-medium flex items-center gap-2"><i class="fas fa-shield-check opacity-60"></i> System Oversight Hub</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-6xl mx-auto p-10 -mt-10">
            <!-- Stats Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 group hover:border-indigo-500 transition-all duration-300">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-black text-gray-400 uppercase tracking-widest">Pending Approvals</div>
                        <i class="fas fa-file-signature text-emerald-500 opacity-40"></i>
                    </div>
                    <div class="text-5xl font-black text-gray-900"><?= $pending_plans ?></div>
                    <p class="text-xs font-bold text-gray-500 mt-2">Lesson plans awaiting your review</p>
                </div>
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 group hover:border-indigo-500 transition-all duration-300">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-black text-gray-400 uppercase tracking-widest">Global Classes</div>
                        <i class="fas fa-school text-blue-500 opacity-40"></i>
                    </div>
                    <div class="text-5xl font-black text-gray-900"><?= $active_classes ?></div>
                    <p class="text-xs font-bold text-gray-500 mt-2">Total active class sections</p>
                </div>
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 group hover:border-indigo-500 transition-all duration-300">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-black text-gray-400 uppercase tracking-widest">Total Students</div>
                        <i class="fas fa-users-viewfinder text-purple-500 opacity-40"></i>
                    </div>
                    <div class="text-5xl font-black text-gray-900"><?= $total_students ?></div>
                    <p class="text-xs font-bold text-gray-500 mt-2">Enrolled across all levels</p>
                </div>
            </div>

            <!-- Command Hub Navigation -->
            <h2 class="text-3xl font-black text-gray-900 mb-10 flex items-center gap-4 lowercase tracking-tighter">
                <span class="w-3 h-10 bg-indigo-700 rounded-full"></span>
                supervisor administrative hub
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Card: Lesson Plan Approvals -->
                <a href="lesson_plans.php" class="bg-white rounded-[2.5rem] p-8 border border-gray-100 shadow-sm hover:shadow-2xl hover:border-indigo-500 transition-all duration-500 flex flex-col items-center text-center group">
                    <div class="w-20 h-20 bg-emerald-50 text-emerald-600 rounded-3xl flex items-center justify-center text-4xl mb-6 shadow-inner group-hover:rotate-12 transition-transform">
                        <i class="fas fa-file-circle-check"></i>
                    </div>
                    <h3 class="text-xl font-black text-gray-900 mb-2">Review Plans</h3>
                    <p class="text-xs text-gray-400 font-bold leading-relaxed px-4">Approve or rejected weekly teacher lesson plans.</p>
                </a>

                <!-- Card: Transcripts -->
                <a href="../academics/transcripts.php" class="bg-white rounded-[2.5rem] p-8 border border-gray-100 shadow-sm hover:shadow-2xl hover:border-indigo-500 transition-all duration-500 flex flex-col items-center text-center group">
                    <div class="w-20 h-20 bg-purple-50 text-purple-600 rounded-3xl flex items-center justify-center text-4xl mb-6 shadow-inner group-hover:rotate-12 transition-transform">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h3 class="text-xl font-black text-gray-900 mb-2">Manage Reports</h3>
                    <p class="text-xs text-gray-400 font-bold leading-relaxed px-4">Review global student transcripts and add remarks.</p>
                </a>

                <!-- Card: Reports -->
                <a href="../academics/report.php" class="bg-white rounded-[2.5rem] p-8 border border-gray-100 shadow-sm hover:shadow-2xl hover:border-indigo-500 transition-all duration-500 flex flex-col items-center text-center group">
                    <div class="w-20 h-20 bg-orange-50 text-orange-600 rounded-3xl flex items-center justify-center text-4xl mb-6 shadow-inner group-hover:rotate-12 transition-transform">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h3 class="text-xl font-black text-gray-900 mb-2">Global Reports</h3>
                    <p class="text-xs text-gray-400 font-bold leading-relaxed px-4">Analyze academic performance across the institution.</p>
                </a>

                <!-- Card: Settings -->
                <a href="../academics/settings.php" class="bg-white rounded-[2.5rem] p-8 border border-gray-100 shadow-sm hover:shadow-2xl hover:border-indigo-500 transition-all duration-500 flex flex-col items-center text-center group">
                    <div class="w-20 h-20 bg-gray-50 text-gray-500 rounded-3xl flex items-center justify-center text-4xl mb-6 shadow-inner group-hover:rotate-12 transition-transform">
                        <i class="fas fa-gears"></i>
                    </div>
                    <h3 class="text-xl font-black text-gray-900 mb-2">Academic Rules</h3>
                    <p class="text-xs text-gray-400 font-bold leading-relaxed px-4">Configure grading scales, weightages and terms.</p>
                </a>
            </div>
        </div>
    </main>

    <footer class="text-center py-10 text-gray-400 text-xs font-bold uppercase tracking-widest bg-gray-50">
        &copy; <?= date('Y') ?> SALBA Montessori · Administrative Portal
    </footer>

</body>
</html>
