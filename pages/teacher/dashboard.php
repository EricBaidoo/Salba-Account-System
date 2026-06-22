<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || $_SESSION['role'] !== 'facilitator') {
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
$job_title = $profile['job_title'] ?? 'Facilitator';
$photo = $profile['photo_path'] ?? '';

// Fetch Stats
$class_count = $conn->query("SELECT COUNT(DISTINCT class_name) FROM teacher_allocations WHERE teacher_id = $uid AND year = '$current_year'")->fetch_row()[0];
$student_count = $conn->query("SELECT COUNT(id) FROM students WHERE class COLLATE utf8mb4_unicode_ci IN (SELECT DISTINCT class_name COLLATE utf8mb4_unicode_ci FROM teacher_allocations WHERE teacher_id = $uid AND year = '$current_year') AND status='active'")->fetch_row()[0];
$lesson_plans = $conn->query("SELECT COUNT(*) FROM lesson_plans WHERE teacher_id = $uid AND academic_year = '$current_year' AND status IN ('pending', 'approved')")->fetch_row()[0] ?? 0;
$approved_reports = $conn->query("SELECT COUNT(*) FROM weekly_reports WHERE teacher_id = $uid AND academic_year = '$current_year' AND status IN ('pending', 'approved')")->fetch_row()[0] ?? 0;

// Fetch Notifications for Needs Revision and Approved with Comments
$notifications = [];

// Lesson Plans
$lp_notifs = $conn->query("SELECT id, week_number, class_name, status, supervisor_comments, admin_comments, 'lesson_plan' as type FROM lesson_plans WHERE teacher_id = $uid AND ((status = 'rejected') OR (status = 'approved' AND (supervisor_comments IS NOT NULL AND TRIM(supervisor_comments) != '' OR admin_comments IS NOT NULL AND TRIM(admin_comments) != ''))) ORDER BY updated_at DESC LIMIT 5");
if ($lp_notifs) {
    while($r = $lp_notifs->fetch_assoc()) $notifications[] = $r;
}

// Weekly Reports
$wr_notifs = $conn->query("SELECT id, week_number, class_name, status, supervisor_comments, admin_comments, 'weekly_report' as type FROM weekly_reports WHERE teacher_id = $uid AND ((status = 'rejected') OR (status = 'approved' AND (supervisor_comments IS NOT NULL AND TRIM(supervisor_comments) != '' OR admin_comments IS NOT NULL AND TRIM(admin_comments) != ''))) ORDER BY updated_at DESC LIMIT 5");
if ($wr_notifs) {
    while($r = $wr_notifs->fetch_assoc()) $notifications[] = $r;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | SALBA Montessori</title>
    <link rel="icon" href="<?= BASE_URL . getSystemLogo($conn) ?>">
    <!-- Clean, Professional Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased">

    <?php include '../../includes/top_nav.php'; ?>

    <div class="pt-16 md:pt-20">
        <!-- Colorful yet Professional Header -->
        <header class="bg-gradient-to-r from-blue-700 via-indigo-600 to-purple-600 shadow-md relative overflow-hidden">
            <div class="absolute inset-0 bg-black/5"></div>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative z-10">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div class="flex items-center gap-5">
                        <?php if($photo): ?>
                            <img src="../../<?= htmlspecialchars($photo) ?>" class="w-16 h-16 rounded-full object-cover border-2 border-white/50 shadow-md hidden sm:block">
                        <?php else: ?>
                            <?php 
                                $name_parts = explode(' ', trim($display_name));
                                $initials = strtoupper(substr($name_parts[0], 0, 1));
                                if (count($name_parts) > 1) {
                                    $initials .= strtoupper(substr(end($name_parts), 0, 1));
                                }
                            ?>
                            <div class="w-16 h-16 rounded-full bg-white/20 text-white flex items-center justify-center text-xl font-bold shadow-md border border-white/40 hidden sm:flex backdrop-blur-sm">
                                <?= htmlspecialchars($initials) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div class="flex items-center gap-3 mb-1.5">
                                <span class="text-[0.65rem] font-bold text-indigo-700 bg-white px-2.5 py-0.5 rounded shadow-sm uppercase tracking-wider"><?= htmlspecialchars($job_title) ?></span>
                                <span class="text-white/80 text-xs font-semibold"><i class="far fa-calendar-alt mr-1"></i> <?= $current_term ?> · <?= $current_year ?></span>
                            </div>
                            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-white drop-shadow-sm">Welcome back, <?= htmlspecialchars($display_name) ?></h1>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full min-h-screen">
            
            <!-- Key Metrics Row -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8 -mt-6 relative z-20">
                <!-- Metric Card 1 -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-md shadow-slate-200/50 flex items-center gap-4 group hover:-translate-y-1 transition-transform duration-300">
                    <div class="w-12 h-12 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center text-xl shrink-0 group-hover:bg-blue-500 group-hover:text-white transition-colors duration-300 shadow-sm border border-blue-100">
                        <i class="fas fa-user-group"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Students</div>
                        <div class="text-2xl font-bold text-slate-800 mt-0.5"><?= $student_count ?></div>
                    </div>
                </div>
                
                <!-- Metric Card 2 -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-md shadow-slate-200/50 flex items-center gap-4 group hover:-translate-y-1 transition-transform duration-300">
                    <div class="w-12 h-12 rounded-lg bg-orange-50 text-orange-500 flex items-center justify-center text-xl shrink-0 group-hover:bg-orange-500 group-hover:text-white transition-colors duration-300 shadow-sm border border-orange-100">
                        <i class="fas fa-school"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Classes</div>
                        <div class="text-2xl font-bold text-slate-800 mt-0.5"><?= $class_count ?></div>
                    </div>
                </div>
                
                <!-- Metric Card 3 -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-md shadow-slate-200/50 flex items-center gap-4 group hover:-translate-y-1 transition-transform duration-300">
                    <div class="w-12 h-12 rounded-lg bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl shrink-0 group-hover:bg-emerald-500 group-hover:text-white transition-colors duration-300 shadow-sm border border-emerald-100">
                        <i class="fas fa-file-signature"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Lesson Plans</div>
                        <div class="text-2xl font-bold text-slate-800 mt-0.5"><?= $lesson_plans ?></div>
                    </div>
                </div>
                
                <!-- Metric Card 4 -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-md shadow-slate-200/50 flex items-center gap-4 group hover:-translate-y-1 transition-transform duration-300">
                    <div class="w-12 h-12 rounded-lg bg-indigo-50 text-indigo-500 flex items-center justify-center text-xl shrink-0 group-hover:bg-indigo-500 group-hover:text-white transition-colors duration-300 shadow-sm border border-indigo-100">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Reports</div>
                        <div class="text-2xl font-bold text-slate-800 mt-0.5"><?= $approved_reports ?></div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content Grid -->
            <!-- Use CSS Grid to re-order columns: Sidebar is Top on mobile, Right on desktop -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Right Column (Sidebar) - Comes FIRST in DOM for Mobile Stacking -->
                <div class="lg:col-start-3 lg:row-start-1 space-y-8">
                    
                    <!-- Supervisor Feedback Widget -->
                    <?php if(!empty($notifications)): ?>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                            <h2 class="text-sm font-bold text-slate-900"><i class="fas fa-bell text-rose-500 mr-1.5"></i> Feedback Alerts</h2>
                            <span class="bg-rose-100 text-rose-600 text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm"><?= count($notifications) ?> New</span>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <?php foreach($notifications as $n): ?>
                                <?php 
                                    $is_rejected = ($n['status'] === 'rejected');
                                    $icon = $is_rejected ? 'fa-circle-xmark text-rose-500' : 'fa-circle-check text-emerald-500';
                                    $tag_text = $is_rejected ? 'Needs Revision' : 'Approved';
                                    $tag_class = $is_rejected ? 'text-rose-600 bg-rose-50' : 'text-emerald-600 bg-emerald-50';
                                    
                                    $title = $n['type'] === 'lesson_plan' ? 'Lesson Plan' : 'Weekly Report';
                                    $link = $n['type'] === 'lesson_plan' ? "lesson_plans?edit={$n['id']}" : "weekly_reports?edit={$n['id']}";
                                    if (!$is_rejected) {
                                        $link = $n['type'] === 'lesson_plan' ? "print_lesson_plan?id={$n['id']}&view=html" : "print_weekly_report?id={$n['id']}&view=html";
                                    }
                                ?>
                                <div class="p-5 hover:bg-slate-50 transition-colors block">
                                    <div class="flex items-start gap-3 mb-2">
                                        <i class="fas <?= $icon ?> mt-0.5"></i>
                                        <div>
                                            <div class="text-sm font-bold text-slate-800 leading-tight"><?= htmlspecialchars($n['class_name']) ?></div>
                                            <div class="text-[11px] font-semibold text-slate-400 mt-0.5 uppercase tracking-wide">Wk <?= $n['week_number'] ?> <?= $title ?></div>
                                        </div>
                                    </div>
                                    <div class="ml-7">
                                         <?php if (!empty($n['supervisor_comments'])): ?>
                                             <div class="bg-slate-50 rounded-lg p-2.5 text-xs text-slate-600 border border-slate-100 mb-2">
                                                 <span class="font-bold text-[9px] text-slate-400 uppercase block mb-1">Supervisor Remarks</span>
                                                 "<?= htmlspecialchars($n['supervisor_comments']) ?>"
                                             </div>
                                         <?php endif; ?>
                                         <?php if (!empty($n['admin_comments'])): ?>
                                             <div class="bg-indigo-50/50 rounded-lg p-2.5 text-xs text-slate-700 border border-indigo-100 mb-2">
                                                 <span class="font-bold text-[9px] text-indigo-500 uppercase block mb-1">Admin Remarks</span>
                                                 "<?= htmlspecialchars($n['admin_comments']) ?>"
                                             </div>
                                         <?php endif; ?>
                                        <a href="<?= $link ?>" class="text-xs font-bold text-primary-600 hover:text-primary-800">
                                            <?= $is_rejected ? 'Resolve Issue &rarr;' : 'View Details &rarr;' ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Staff Attendance Widget -->
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50/50">
                            <h2 class="text-sm font-bold text-slate-900"><i class="fas fa-clock text-indigo-500 mr-1.5"></i> Time & Attendance</h2>
                        </div>
                        <div class="p-2">
                            <a href="<?= BASE_URL ?>pages/teacher/check_in" class="flex items-center gap-4 p-3 rounded-lg hover:bg-rose-50 transition-colors group">
                                <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-500 border border-rose-100 flex items-center justify-center group-hover:bg-rose-500 group-hover:text-white transition-colors">
                                    <i class="fas fa-location-dot text-sm"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-slate-800">Daily Check-in</div>
                                    <div class="text-xs font-medium text-slate-500">Log arrival with GPS</div>
                                </div>
                            </a>
                            <a href="<?= BASE_URL ?>pages/teacher/my_attendance" class="flex items-center gap-4 p-3 rounded-lg hover:bg-slate-50 transition-colors group">
                                <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-500 border border-slate-200 flex items-center justify-center group-hover:bg-slate-600 group-hover:text-white transition-colors">
                                    <i class="fas fa-clock-rotate-left text-sm"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-slate-800">Attendance History</div>
                                    <div class="text-xs font-medium text-slate-500">Review punctuality record</div>
                                </div>
                            </a>
                        </div>
                    </div>

                </div>

                <!-- Left Column (Main Tools) - Comes SECOND in DOM, displays Left on desktop -->
                <div class="lg:col-span-2 lg:col-start-1 lg:row-start-1 space-y-8">
                    
                    <!-- Section: Academic Tools -->
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 mb-4">Classroom Management</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <a href="<?= BASE_URL ?>pages/academics/grades" class="group bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-amber-300 hover:shadow-md transition-all flex items-start gap-4">
                                <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center border border-amber-100 shrink-0 group-hover:bg-amber-500 group-hover:text-white transition-colors shadow-sm">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800 mb-1">Gradebook</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed font-medium">Manage student scores and calculate term grades automatically.</p>
                                </div>
                            </a>

                            <a href="<?= BASE_URL ?>pages/academics/attendance" class="group bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-cyan-300 hover:shadow-md transition-all flex items-start gap-4">
                                <div class="w-10 h-10 rounded-lg bg-cyan-50 text-cyan-600 flex items-center justify-center border border-cyan-100 shrink-0 group-hover:bg-cyan-500 group-hover:text-white transition-colors shadow-sm">
                                    <i class="fas fa-clipboard-user"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800 mb-1">Student Attendance</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed font-medium">Track daily presence and monitor class attendance rates.</p>
                                </div>
                            </a>

                            <a href="<?= BASE_URL ?>pages/academics/transcripts" class="group bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-purple-300 hover:shadow-md transition-all flex items-start gap-4 sm:col-span-2">
                                <div class="w-10 h-10 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center border border-purple-100 shrink-0 group-hover:bg-purple-500 group-hover:text-white transition-colors shadow-sm">
                                    <i class="fas fa-scroll"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800 mb-1">Transcripts</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed font-medium">View generated official semester reports and academic history for your assigned classes.</p>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Section: Submissions -->
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 mb-4">Documents & Submissions</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <a href="<?= BASE_URL ?>pages/teacher/lesson_portfolio" class="group relative bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:border-emerald-400 hover:ring-1 hover:ring-emerald-400 hover:shadow-md transition-all overflow-hidden">
                                <div class="absolute right-0 top-0 w-24 h-24 bg-emerald-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                                <div class="relative z-10">
                                    <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl mb-4 border border-emerald-200 shadow-sm group-hover:bg-emerald-500 group-hover:text-white transition-colors">
                                        <i class="fas fa-chalkboard-teacher"></i>
                                    </div>
                                    <h3 class="text-base font-bold text-slate-800 mb-1">Lesson Notes</h3>
                                    <p class="text-xs font-medium text-slate-500 mb-4">Draft, manage, and track weekly lesson plans.</p>
                                    <span class="text-[11px] font-bold uppercase tracking-widest text-emerald-600 flex items-center gap-1 group-hover:text-emerald-700">Open Portfolio <i class="fas fa-arrow-right text-[10px]"></i></span>
                                </div>
                            </a>

                            <a href="<?= BASE_URL ?>pages/teacher/report_portfolio" class="group relative bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:border-teal-400 hover:ring-1 hover:ring-teal-400 hover:shadow-md transition-all overflow-hidden">
                                <div class="absolute right-0 top-0 w-24 h-24 bg-teal-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                                <div class="relative z-10">
                                    <div class="w-10 h-10 rounded-lg bg-teal-100 text-teal-600 flex items-center justify-center text-xl mb-4 border border-teal-200 shadow-sm group-hover:bg-teal-500 group-hover:text-white transition-colors">
                                        <i class="fas fa-chart-pie"></i>
                                    </div>
                                    <h3 class="text-base font-bold text-slate-800 mb-1">Weekly Reports</h3>
                                    <p class="text-xs font-medium text-slate-500 mb-4">Upload and submit performance reports.</p>
                                    <span class="text-[11px] font-bold uppercase tracking-widest text-teal-600 flex items-center gap-1 group-hover:text-teal-700">Open Portfolio <i class="fas fa-arrow-right text-[10px]"></i></span>
                                </div>
                            </a>
                        </div>
                        
                        <div class="mt-4">
                            <a href="<?= BASE_URL ?>pages/teacher/appraisal_portfolio.php" class="group relative bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:border-blue-400 hover:ring-1 hover:ring-blue-400 hover:shadow-md transition-all overflow-hidden flex items-center gap-4">
                                <div class="absolute right-0 top-0 w-24 h-24 bg-blue-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                                <div class="relative z-10 w-10 h-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center text-xl shrink-0 border border-blue-200 shadow-sm group-hover:bg-blue-500 group-hover:text-white transition-colors">
                                    <i class="fas fa-clipboard-user"></i>
                                </div>
                                <div class="relative z-10 flex-1">
                                    <h3 class="text-base font-bold text-slate-800 mb-1">Monthly Appraisals</h3>
                                    <p class="text-xs font-medium text-slate-500 mb-1">Complete your self-evaluation and view ratings.</p>
                                    <span class="text-[11px] font-bold uppercase tracking-widest text-blue-600 flex items-center gap-1 group-hover:text-blue-700">Open Portfolio <i class="fas fa-arrow-right text-[10px]"></i></span>
                                </div>
                            </a>
                        </div>
                    </div>

                </div>

            </div>

        </main>
    </div>

</body>
</html>
