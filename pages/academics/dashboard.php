<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../includes/login.php');
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
    $at->execute();
    $attendance_count = $at->get_result()->fetch_assoc()['c'] ?? 0;
    $at->close();
}



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academics Dashboard — <?php echo htmlspecialchars($school_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50">

    <?php include '../../includes/sidebar_admin.php'; ?>

    <main class="ml-72 p-8 min-h-screen">

        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                <span class="text-xs font-bold text-purple-600 uppercase tracking-widest bg-purple-50 px-3 py-1 rounded-full">
                    <i class="fas fa-book mr-1"></i> Academics
                </span>
                <span class="text-xs text-gray-400">
                    <?php echo htmlspecialchars($current_term); ?> &middot; <?php echo htmlspecialchars($display_academic_year); ?>
                </span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">Academic Dashboard</h1>
            <p class="text-gray-500 mt-1">Manage grades, attendance, subjects, and academic records for <?php echo htmlspecialchars($school_name); ?></p>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            <?php
            $stats = [
                ['value' => $total_students,    'label' => 'Active Students', 'color' => 'text-blue-600'],
                ['value' => $total_classes,     'label' => 'Classes',         'color' => 'text-purple-600'],
                ['value' => $total_subjects,    'label' => 'Subjects',        'color' => 'text-green-600'],
                ['value' => $total_teachers,    'label' => 'Teachers',        'color' => 'text-orange-600'],
                ['value' => $grades_count,      'label' => 'Grades Entered',  'color' => 'text-indigo-600'],
                ['value' => $attendance_count,  'label' => 'Attendance Logs', 'color' => 'text-teal-600'],
            ];
            foreach ($stats as $s): ?>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
                <div class="text-2xl font-bold <?php echo $s['color']; ?>"><?php echo number_format($s['value']); ?></div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1"><?php echo $s['label']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Feature Cards -->
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <i class="fas fa-bolt"></i> Academic Management
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
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
                        ['label' => 'Generate PDF', 'href' => 'transcripts.php?action=generate'],
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
                    'icon' => 'fa-file-signature', 'color' => 'emerald',
                    'title' => 'Lesson Plans', 'desc' => 'Manage the end-to-end weekly teaching outlines workflow',
                    'links' => [
                        ['label' => 'Submit Plan (Teacher View)', 'href' => '../teacher/lesson_plans.php'],
                        ['label' => 'Review Queue (Supervisor)', 'href' => '../supervisor/lesson_plans.php'],
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

            $palettes = [
                'yellow'  => ['bg-yellow-50',  'text-yellow-600'],
                'green'   => ['bg-green-50',   'text-green-600'],
                'blue'    => ['bg-blue-50',    'text-blue-600'],
                'indigo'  => ['bg-indigo-50',  'text-indigo-600'],
                'purple'  => ['bg-purple-50',  'text-purple-600'],
                'orange'  => ['bg-orange-50',  'text-orange-600'],
                'rose'    => ['bg-rose-50',    'text-rose-600'],
                'emerald' => ['bg-emerald-50', 'text-emerald-600'],
            ];

            foreach ($features as $f):
                [$iconBg, $iconColor] = $palettes[$f['color']];
            ?>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 <?php echo $iconBg; ?> rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas <?php echo $f['icon']; ?> <?php echo $iconColor; ?>"></i>
                    </div>
                    <h3 class="font-bold text-gray-800"><?php echo $f['title']; ?></h3>
                </div>
                <p class="text-sm text-gray-400 mb-4"><?php echo $f['desc']; ?></p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($f['links'] as $i => $link): ?>
                    <a href="<?php echo $link['href']; ?>"
                       class="text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors
                              <?php echo $i === 0
                                  ? "{$iconBg} {$iconColor} hover:opacity-80"
                                  : 'bg-gray-50 text-gray-500 hover:bg-gray-100'; ?>">
                        <?php echo $link['label']; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>



    </main>
</body>
</html>
