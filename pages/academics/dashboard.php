<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../login');
    exit;
}

$school_name          = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$current_term         = getCurrentSemester($conn);
$academic_year        = getAcademicYear($conn);
$display_academic_year = formatAcademicYearDisplay($conn, $academic_year);

// Real DB stats
$total_classes   = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM classes");
if ($r) $total_classes = $r->fetch_assoc()['c'] ?? 0;

$total_students  = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='active'");
if ($r) $total_students = $r->fetch_assoc()['c'] ?? 0;

$total_subjects  = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM subjects");
if ($r) $total_subjects = $r->fetch_assoc()['c'] ?? 0;

$total_teachers  = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM users WHERE role IN ('facilitator','staff')");
if ($r) $total_teachers = $r->fetch_assoc()['c'] ?? 0;

// Grades entered this semester
$grades_count = 0;
$g = $conn->prepare("SELECT COUNT(*) as c FROM grades WHERE semester=? AND year=?");
if ($g) {
    $g->bind_param('ss', $current_term, $academic_year);
    $g->execute();
    $grades_count = $g->get_result()->fetch_assoc()['c'] ?? 0;
    $g->close();
}

// Attendance records
$attendance_count = 0;
$at = $conn->prepare("SELECT COUNT(*) as c FROM attendance WHERE semester=? AND academic_year=?");
if ($at) {
    $at->bind_param('ss', $current_term, $academic_year);
    $at->execute();
    $attendance_count = $at->get_result()->fetch_assoc()['c'] ?? 0;
    $at->close();
}

// Lesson plan distribution
$lp_stats = ['draft' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$lp_res = $conn->prepare("SELECT status, COUNT(*) as c FROM lesson_plans WHERE academic_year = ? GROUP BY status");
if ($lp_res) {
    $lp_res->bind_param('s', $academic_year);
    $lp_res->execute();
    $res = $lp_res->get_result();
    while ($row = $res->fetch_assoc()) {
        $st = strtolower($row['status']);
        if (isset($lp_stats[$st])) $lp_stats[$st] = (int)$row['c'];
    }
    $lp_res->close();
}

// Weekly report distribution
$wr_stats = ['draft' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$wr_res = $conn->prepare("SELECT status, COUNT(*) as c FROM weekly_reports WHERE academic_year = ? GROUP BY status");
if ($wr_res) {
    $wr_res->bind_param('s', $academic_year);
    $wr_res->execute();
    $res = $wr_res->get_result();
    while ($row = $res->fetch_assoc()) {
        $st = strtolower($row['status']);
        if (isset($wr_stats[$st])) $wr_stats[$st] = (int)$row['c'];
    }
    $wr_res->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Command Hub — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="icon" href="<?= BASE_URL . getSystemLogo($conn) ?>">
    
    <!-- Modern Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        primary: { 50: '#f0f9ff', 100: '#e0f2fe', 500: '#0ea5e9', 600: '#0284c7', 900: '#0c4a6e' },
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    
    <style>
        body { background-color: #f8fafc; }
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        .stat-card-hover:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08); }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="text-slate-800 antialiased selection:bg-primary-500 selection:text-white">

    <?php 
    if ($_SESSION['role'] === 'admin') {
        include '../../includes/sidebar.php';
    } else {
        include '../../includes/top_nav.php';
    }
    ?>

    <main class="<?= $_SESSION['role'] === 'admin' ? 'lg:ml-72' : 'w-full' ?> min-h-screen pb-12 transition-all duration-300">

        <!-- Animated Background Header -->
        <div class="relative bg-gradient-to-br from-indigo-900 via-purple-800 to-slate-900 pt-16 md:pt-20 pb-24 overflow-hidden">
            <div class="absolute inset-0 bg-[url('../../assets/images/pattern-light.svg')] opacity-10"></div>
            <div class="absolute -right-20 -top-20 w-96 h-96 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse"></div>
            <div class="absolute -left-20 top-20 w-72 h-72 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse" style="animation-delay: 2s;"></div>
            
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <span class="bg-white/20 backdrop-blur text-white text-[0.65rem] font-bold uppercase tracking-widest px-3 py-1 rounded-full border border-white/20">
                                <i class="fas fa-graduation-cap mr-1"></i> Academic Command Hub
                            </span>
                            <span class="text-white/80 text-sm font-medium">
                                <?php echo htmlspecialchars($current_term); ?> &middot; <?php echo htmlspecialchars($display_academic_year); ?>
                            </span>
                        </div>
                        <h1 class="text-3xl md:text-4xl font-extrabold text-white font-display tracking-tight drop-shadow-sm">Academic Operations</h1>
                        <p class="text-indigo-100 mt-2 max-w-2xl text-sm md:text-base">Real-time stats, weekly reviews, syllabus management, and grading tracking for <?php echo htmlspecialchars($school_name); ?></p>
                    </div>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div>
                        <a href="../administration/dashboard.php" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white text-xs font-bold px-4 py-2.5 rounded-xl border border-white/20 transition-all shadow-md">
                            <i class="fas fa-arrow-left"></i> Administration Hub
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 relative z-20 space-y-6">

            <!-- AT A GLANCE METRICS ROW -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                <!-- Students -->
                <div class="glass-card rounded-2xl p-5 stat-card-hover transition-all duration-300 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-50 rounded-full group-hover:scale-110 transition-transform"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Active Students</p>
                            <h3 class="text-2xl font-extrabold font-display text-slate-800"><?= number_format($total_students) ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-lg shadow-inner border border-blue-200">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                </div>

                <!-- Classes -->
                <div class="glass-card rounded-2xl p-5 stat-card-hover transition-all duration-300 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-purple-50 rounded-full group-hover:scale-110 transition-transform"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Classes</p>
                            <h3 class="text-2xl font-extrabold font-display text-slate-800"><?= number_format($total_classes) ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center text-lg shadow-inner border border-purple-200">
                            <i class="fas fa-school"></i>
                        </div>
                    </div>
                </div>

                <!-- Subjects -->
                <div class="glass-card rounded-2xl p-5 stat-card-hover transition-all duration-300 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-50 rounded-full group-hover:scale-110 transition-transform"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Subjects</p>
                            <h3 class="text-2xl font-extrabold font-display text-slate-800"><?= number_format($total_subjects) ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg shadow-inner border border-emerald-200">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                </div>

                <!-- Teachers -->
                <div class="glass-card rounded-2xl p-5 stat-card-hover transition-all duration-300 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-orange-50 rounded-full group-hover:scale-110 transition-transform"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Teachers</p>
                            <h3 class="text-2xl font-extrabold font-display text-slate-800"><?= number_format($total_teachers) ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center text-lg shadow-inner border border-orange-200">
                            <i class="fas fa-user-tie"></i>
                        </div>
                    </div>
                </div>

                <!-- Grades Entered -->
                <div class="glass-card rounded-2xl p-5 stat-card-hover transition-all duration-300 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-indigo-50 rounded-full group-hover:scale-110 transition-transform"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Grades Entered</p>
                            <h3 class="text-2xl font-extrabold font-display text-slate-800"><?= number_format($grades_count) ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-lg shadow-inner border border-indigo-200">
                            <i class="fas fa-award"></i>
                        </div>
                    </div>
                </div>

                <!-- Attendance Logs -->
                <div class="glass-card rounded-2xl p-5 stat-card-hover transition-all duration-300 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-teal-50 rounded-full group-hover:scale-110 transition-transform"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Attendance Logs</p>
                            <h3 class="text-2xl font-extrabold font-display text-slate-800"><?= number_format($attendance_count) ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-teal-100 text-teal-600 flex items-center justify-center text-lg shadow-inner border border-teal-200">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INTERACTIVE CHARTS ROW -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Lesson Plan Distribution Chart -->
                <div class="glass-card rounded-3xl p-6 shadow-sm border border-slate-200/60 flex flex-col">
                    <div class="mb-4">
                        <h2 class="text-lg font-extrabold font-display text-slate-800 flex items-center gap-2">
                            <i class="fas fa-file-signature text-indigo-500"></i> Lesson Plans
                        </h2>
                        <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Academic Year Status Distribution</p>
                    </div>
                    <div class="flex-1 flex flex-col sm:flex-row items-center justify-center gap-6 min-h-[220px]">
                        <?php if (array_sum($lp_stats) === 0): ?>
                            <div class="text-center text-slate-400 py-6">
                                <i class="fas fa-chart-pie text-5xl opacity-20 mb-3 block"></i>
                                <p class="text-xs font-semibold">No lesson plans submitted yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="h-40 w-40 relative flex-shrink-0">
                                <canvas id="lpChart"></canvas>
                                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                    <span class="text-3xl font-extrabold font-display text-slate-800 leading-none"><?= array_sum($lp_stats) ?></span>
                                    <span class="text-[0.55rem] font-black uppercase tracking-widest text-slate-400 mt-1">Total Plans</span>
                                </div>
                            </div>
                            
                            <!-- Custom HTML Legend -->
                            <div class="grid grid-cols-2 gap-x-6 gap-y-3 w-full max-w-[240px] px-2">
                                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <span class="w-3 h-3 rounded-full bg-emerald-500"></span> Approved (<?= $lp_stats['approved'] ?>)
                                </div>
                                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <span class="w-3 h-3 rounded-full bg-amber-500"></span> Pending (<?= $lp_stats['pending'] ?>)
                                </div>
                                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <span class="w-3 h-3 rounded-full bg-slate-300"></span> Draft (<?= $lp_stats['draft'] ?>)
                                </div>
                                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <span class="w-3 h-3 rounded-full bg-rose-500"></span> Rejected (<?= $lp_stats['rejected'] ?>)
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Weekly Report Distribution Chart -->
                <div class="glass-card rounded-3xl p-6 shadow-sm border border-slate-200/60 flex flex-col">
                    <div class="mb-4">
                        <h2 class="text-lg font-extrabold font-display text-slate-800 flex items-center gap-2">
                            <i class="fas fa-clipboard-check text-teal-500"></i> Weekly Reports
                        </h2>
                        <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Academic Year Status Distribution</p>
                    </div>
                    <div class="flex-1 flex flex-col sm:flex-row items-center justify-center gap-6 min-h-[220px]">
                        <?php if (array_sum($wr_stats) === 0): ?>
                            <div class="text-center text-slate-400 py-6">
                                <i class="fas fa-chart-pie text-5xl opacity-20 mb-3 block"></i>
                                <p class="text-xs font-semibold">No weekly reports submitted yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="h-40 w-40 relative flex-shrink-0">
                                <canvas id="wrChart"></canvas>
                                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                    <span class="text-3xl font-extrabold font-display text-slate-800 leading-none"><?= array_sum($wr_stats) ?></span>
                                    <span class="text-[0.55rem] font-black uppercase tracking-widest text-slate-400 mt-1">Total Reports</span>
                                </div>
                            </div>
                            
                            <!-- Custom HTML Legend -->
                            <div class="grid grid-cols-2 gap-x-6 gap-y-3 w-full max-w-[240px] px-2">
                                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <span class="w-3 h-3 rounded-full bg-emerald-500"></span> Approved (<?= $wr_stats['approved'] ?>)
                                </div>
                                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <span class="w-3 h-3 rounded-full bg-amber-500"></span> Pending (<?= $wr_stats['pending'] ?>)
                                </div>
                                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <span class="w-3 h-3 rounded-full bg-slate-300"></span> Draft (<?= $wr_stats['draft'] ?>)
                                </div>
                                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <span class="w-3 h-3 rounded-full bg-rose-500"></span> Rejected (<?= $wr_stats['rejected'] ?>)
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Feature Cards Section -->
            <div class="space-y-4">
                <div>
                    <h2 class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                        <i class="fas fa-bolt text-indigo-500"></i> Academic Management
                    </h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php
                    $features = [
                        [
                            'icon' => 'fa-star', 'color' => 'yellow',
                            'title' => 'Grades & Marks', 'desc' => 'Enter and view student grades across subjects and classes',
                            'links' => [
                                ['label' => 'View Grades', 'href' => 'grades.php'],
                                ['label' => 'Enter Marks', 'href' => 'grades.php?action=entry'],
                            ]
                        ],
                        [
                            'icon' => 'fa-calendar-check', 'color' => 'green',
                            'title' => 'Attendance', 'desc' => 'Track and monitor daily student attendance records',
                            'links' => [
                                ['label' => 'View Records', 'href' => 'attendance.php'],
                                ['label' => 'Mark Attendance', 'href' => 'attendance.php?action=entry'],
                            ]
                        ],
                        [
                            'icon' => 'fa-chalkboard', 'color' => 'blue',
                            'title' => 'Classes', 'desc' => 'Manage class structure and student assignments',
                            'links' => [
                                ['label' => 'View Classes', 'href' => 'classes.php'],
                                ['label' => 'Class Rosters', 'href' => 'classes.php?view=roster'],
                            ]
                        ],
                        [
                            'icon' => 'fa-book-open', 'color' => 'indigo',
                            'title' => 'Subjects', 'desc' => 'Register and manage school subjects by class level',
                            'links' => [
                                ['label' => 'Manage Subjects', 'href' => 'subjects.php'],
                            ]
                        ],
                        [
                            'icon' => 'fa-user-tie', 'color' => 'purple',
                            'title' => 'Teacher Allocation', 'desc' => 'Assign class and subject teachers across 14 levels',
                            'links' => [
                                ['label' => 'Manage Allocations', 'href' => 'teacher_allocation.php'],
                            ]
                        ],
                        [
                            'icon' => 'fa-scroll', 'color' => 'orange',
                            'title' => 'Transcripts', 'desc' => 'Generate and view student academic transcripts',
                            'links' => [
                                ['label' => 'View Transcripts', 'href' => 'transcripts.php'],
                                ['label' => 'Class Breakdown', 'href' => 'transcript_breakdown.php'],
                                ['label' => 'Generate PDF', 'href' => 'transcripts.php?action=generate'],
                            ]
                        ],
                        [
                            'icon' => 'fa-table-list', 'color' => 'emerald',
                            'title' => 'Class Broadsheet', 'desc' => 'Generate and view comprehensive class performance broadsheets',
                            'links' => [
                                ['label' => 'View Broadsheet', 'href' => 'transcript_breakdown.php'],
                            ]
                        ],
                        [
                            'icon' => 'fa-paste', 'color' => 'teal',
                            'title' => 'Teacher Reports', 'desc' => 'Review teacher weekly reports and lesson plans (Admin only)',
                            'links' => [
                                ['label' => 'Review Reports', 'href' => 'teacher_reports.php'],
                            ]
                        ],
                        [
                            'icon' => 'fa-chart-bar', 'color' => 'rose',
                            'title' => 'Academic Reports', 'desc' => 'Performance analytics, grade distributions, and trends',
                            'links' => [
                                ['label' => 'View Reports', 'href' => 'report.php'],
                                ['label' => 'Download', 'href' => 'report.php?action=download'],
                            ]
                        ],
                        [
                            'icon' => 'fa-sliders', 'color' => 'indigo',
                            'title' => 'Academic Rules', 'desc' => 'Configure assessment weights, semester report rules, and pass marks',
                            'links' => [
                                ['label' => 'Configure Rules', 'href' => 'settings.php'],
                            ]
                        ],
                    ];

                    // Filter features based on role
                    if ($_SESSION['role'] === 'supervisor') {
                        $allowed_supervisor_features = ['Academic Reports', 'Class Broadsheet', 'Transcripts'];
                        $features = array_filter($features, function($f) use ($allowed_supervisor_features) {
                            return in_array($f['title'], $allowed_supervisor_features);
                        });
                    }

                    if ($_SESSION['role'] !== 'admin') {
                        $features = array_filter($features, function($f) {
                            return $f['title'] !== 'Teacher Reports';
                        });
                    }

                    $palettes = [
                        'yellow'  => ['bg-amber-50',   'text-amber-600',   'border-amber-200/50'],
                        'green'   => ['bg-emerald-50', 'text-emerald-600', 'border-emerald-200/50'],
                        'blue'    => ['bg-blue-50',    'text-blue-600',    'border-blue-200/50'],
                        'indigo'  => ['bg-indigo-50',  'text-indigo-600',  'border-indigo-200/50'],
                        'purple'  => ['bg-purple-50',  'text-purple-600',  'border-purple-200/50'],
                        'orange'  => ['bg-orange-50',  'text-orange-600',  'border-orange-200/50'],
                        'rose'    => ['bg-rose-50',    'text-rose-600',    'border-rose-200/50'],
                        'emerald' => ['bg-emerald-50', 'text-emerald-600', 'border-emerald-200/50'],
                        'teal'    => ['bg-teal-50',    'text-teal-600',    'border-teal-200/50'],
                    ];

                    foreach ($features as $f):
                        $color = $f['color'];
                        [$iconBg, $iconColor, $borderColor] = $palettes[$color] ?? $palettes['indigo'];
                    ?>
                    <div class="glass-card rounded-2xl border border-slate-200/60 p-5 stat-card-hover hover:border-slate-300 transition-all duration-300 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 <?= $iconBg ?> <?= $iconColor ?> rounded-xl flex items-center justify-center flex-shrink-0 shadow-inner border <?= $borderColor ?>">
                                    <i class="fas <?= $f['icon'] ?> text-base"></i>
                                </div>
                                <h3 class="font-extrabold font-display text-slate-800 text-base"><?= htmlspecialchars($f['title']) ?></h3>
                            </div>
                            <p class="text-xs font-semibold text-slate-400 leading-relaxed mb-4"><?= htmlspecialchars($f['desc']) ?></p>
                        </div>
                        <div class="flex flex-wrap gap-2 pt-2">
                            <?php foreach ($f['links'] as $i => $link): ?>
                            <a href="<?= htmlspecialchars($link['href']) ?>"
                               class="text-[0.7rem] font-bold px-3 py-2 rounded-xl transition-all duration-200 flex items-center gap-1.5 shadow-sm
                                      <?= $i === 0
                                          ? "{$iconBg} {$iconColor} hover:opacity-90 border {$borderColor}"
                                          : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-200/50'; ?>">
                                <?= htmlspecialchars($link['label']) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    </main>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Lesson Plan Chart
            <?php if (array_sum($lp_stats) > 0): ?>
            const lpCtx = document.getElementById('lpChart').getContext('2d');
            new Chart(lpCtx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [
                            <?= $lp_stats['approved'] ?>,
                            <?= $lp_stats['pending'] ?>,
                            <?= $lp_stats['draft'] ?>,
                            <?= $lp_stats['rejected'] ?>
                        ],
                        backgroundColor: ['#10b981', '#f59e0b', '#cbd5e1', '#ef4444'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    cutout: '70%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) { label += ': '; }
                                    label += context.raw;
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>

            // Weekly Report Chart
            <?php if (array_sum($wr_stats) > 0): ?>
            const wrCtx = document.getElementById('wrChart').getContext('2d');
            new Chart(wrCtx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [
                            <?= $wr_stats['approved'] ?>,
                            <?= $wr_stats['pending'] ?>,
                            <?= $wr_stats['draft'] ?>,
                            <?= $wr_stats['rejected'] ?>
                        ],
                        backgroundColor: ['#10b981', '#f59e0b', '#cbd5e1', '#ef4444'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    cutout: '70%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) { label += ': '; }
                                    label += context.raw;
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>

