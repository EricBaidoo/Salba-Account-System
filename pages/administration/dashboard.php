<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../login');
    exit;
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../index.php');
    exit;
}

$school_name          = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$current_term         = getCurrentSemester($conn);
$academic_year        = getAcademicYear($conn);
$display_academic_year = formatAcademicYearDisplay($conn, $academic_year);

// --- 1. AT A GLANCE METRICS ---
$active_students   = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='active'")->fetch_assoc()['c'] ?? 0;
$unique_classes    = $conn->query("SELECT COUNT(DISTINCT class) as c FROM students WHERE status='active'")->fetch_assoc()['c'] ?? 0;

$today = date('Y-m-d');
$present_stmt = $conn->prepare("SELECT COUNT(*) as c FROM attendance WHERE attendance_date=? AND status='present'");
$present_stmt->bind_param('s', $today);
$present_stmt->execute();
$present_students = (int)$present_stmt->get_result()->fetch_assoc()['c'];
$present_stmt->close();
$attendance_rate = ($active_students > 0) ? round(($present_students / $active_students) * 100) : 0;

$total_users = $total_admins = $total_supervisors = $total_staff_users = 0;
$users_res = $conn->query("SELECT COALESCE(role,'staff') AS r, COUNT(*) AS c FROM users GROUP BY r");
if ($users_res) {
    while ($row = $users_res->fetch_assoc()) {
        $total_users += $row['c'];
        $r = strtolower($row['r']);
        if ($r === 'admin')         $total_admins      += $row['c'];
        elseif ($r === 'supervisor') $total_supervisors += $row['c'];
        else                         $total_staff_users += $row['c'];
    }
}

$total_fees_assigned = $conn->query("SELECT SUM(amount) as total FROM student_fees WHERE semester = '$current_term' AND academic_year = '$academic_year' AND status != 'cancelled'")->fetch_assoc()['total'] ?? 0;

$pay_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE semester=? AND academic_year=?");
$pay_stmt->bind_param('ss', $current_term, $academic_year);
$pay_stmt->execute();
$total_payments = (float)$pay_stmt->get_result()->fetch_assoc()['total'];
$pay_stmt->close();

$exp_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE semester=? AND academic_year=?");
$exp_stmt->bind_param('ss', $current_term, $academic_year);
$exp_stmt->execute();
$total_expenses = (float)$exp_stmt->get_result()->fetch_assoc()['total'];
$exp_stmt->close();

$outstanding_fees = max(0, $total_fees_assigned - $total_payments);
$collection_rate = ($total_fees_assigned > 0) ? round(($total_payments / $total_fees_assigned) * 100) : 0;

// --- 2. FINANCIAL TRENDS (CHART DATA) ---
$months = [];
$income_data = [];
$expense_data = [];

$inc_trend = $conn->prepare("SELECT MONTH(payment_date) as m, SUM(amount) as total FROM payments WHERE academic_year = ? GROUP BY MONTH(payment_date) ORDER BY MONTH(payment_date)");
$inc_trend->bind_param('s', $academic_year);
$inc_trend->execute();
$inc_res = $inc_trend->get_result();
while ($row = $inc_res->fetch_assoc()) {
    $m = (int)$row['m'];
    $months[$m] = date('M', mktime(0, 0, 0, $m, 1));
    $income_data[$m] = (float)$row['total'];
}
$inc_trend->close();

$exp_trend = $conn->prepare("SELECT MONTH(expense_date) as m, SUM(amount) as total FROM expenses WHERE academic_year = ? GROUP BY MONTH(expense_date) ORDER BY MONTH(expense_date)");
$exp_trend->bind_param('s', $academic_year);
$exp_trend->execute();
$exp_res = $exp_trend->get_result();
while ($row = $exp_res->fetch_assoc()) {
    $m = (int)$row['m'];
    $months[$m] = date('M', mktime(0, 0, 0, $m, 1));
    $expense_data[$m] = (float)$row['total'];
}
$exp_trend->close();

if(empty($months)) {
    // Fallback if no data
    $m = (int)date('n');
    $months[$m] = date('b');
}

ksort($months);
$chart_labels = array_values($months);
$chart_income = [];
$chart_expense = [];
foreach ($months as $m => $name) {
    $chart_income[] = $income_data[$m] ?? 0;
    $chart_expense[] = $expense_data[$m] ?? 0;
}

// --- 3. ACADEMIC SUBMISSIONS (CHART DATA) ---
$lp_stats = ['draft' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$lp_res = $conn->prepare("SELECT status, COUNT(*) as c FROM lesson_plans WHERE academic_year = ? GROUP BY status");
$lp_res->bind_param('s', $academic_year);
$lp_res->execute();
$res = $lp_res->get_result();
while ($row = $res->fetch_assoc()) {
    $st = strtolower($row['status']);
    if (isset($lp_stats[$st])) $lp_stats[$st] = (int)$row['c'];
}
$lp_res->close();

// --- 4. PROGRESS TRACKING ---
$class_progress = [];
$c_res = $conn->query("SELECT class, COUNT(*) as student_count FROM students WHERE status='active' GROUP BY class ORDER BY class");
if ($c_res) {
    while ($row = $c_res->fetch_assoc()) {
        $class_progress[$row['class']] = [
            'total_students' => (int)$row['student_count'],
            'attendance_marked' => 0,
        ];
    }
}
$att_res = $conn->prepare("SELECT s.class, COUNT(DISTINCT a.student_id) as marked_count FROM attendance a JOIN students s ON a.student_id = s.id WHERE a.attendance_date = ? AND s.status='active' AND LOWER(a.status) = 'present' GROUP BY s.class");
if ($att_res) {
    $att_res->bind_param('s', $today);
    $att_res->execute();
    $res = $att_res->get_result();
    while ($row = $res->fetch_assoc()) {
        if (isset($class_progress[$row['class']])) {
            $class_progress[$row['class']]['attendance_marked'] = (int)$row['marked_count'];
        }
    }
    $att_res->close();
}

// --- 5. CELEBRATORY BIRTHDAYS ---
$student_birthdays = [];
$staff_birthdays = [];
$current_month_num = (int)date('n');
$today_day = (int)date('j');

$s_res = $conn->query("SELECT first_name, last_name, date_of_birth, class, status FROM students WHERE status='active' AND MONTH(date_of_birth) = $current_month_num");
if ($s_res) {
    while ($row = $s_res->fetch_assoc()) {
        $day = (int)date('j', strtotime($row['date_of_birth']));
        $student_birthdays[] = [
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'info' => $row['class'],
            'day' => $day,
            'days_until' => $day - $today_day,
            'is_today' => ($day == $today_day),
            'is_passed' => ($day < $today_day)
        ];
    }
    usort($student_birthdays, fn($a, $b) => $a['day'] <=> $b['day']);
}

$st_res = $conn->query("SELECT full_name, job_title, department, date_of_birth FROM staff_profiles WHERE employment_status='active' AND MONTH(date_of_birth) = $current_month_num");
if ($st_res) {
    while ($row = $st_res->fetch_assoc()) {
        $day = (int)date('j', strtotime($row['date_of_birth']));
        $staff_birthdays[] = [
            'name' => $row['full_name'],
            'info' => $row['job_title'] . ' (' . $row['department'] . ')',
            'day' => $day,
            'days_until' => $day - $today_day,
            'is_today' => ($day == $today_day),
            'is_passed' => ($day < $today_day)
        ];
    }
    usort($staff_birthdays, fn($a, $b) => $a['day'] <=> $b['day']);
}

$month_names = [1=>"January", 2=>"February", 3=>"March", 4=>"April", 5=>"May", 6=>"June", 7=>"July", 8=>"August", 9=>"September", 10=>"October", 11=>"November", 12=>"December"];
$display_month = $month_names[$current_month_num];

// Quick Actions Configuration
$actions = [
    ['href'=>'students/view_students.php',   'icon'=>'fa-users-viewfinder', 'color'=>'blue',   'title'=>'Students',  'desc'=>'Manage enrollments'],
    ['href'=>'staff/view_staff.php',         'icon'=>'fa-id-card',           'color'=>'purple', 'title'=>'Staff',    'desc'=>'View & manage faculty'],
    ['href'=>'staff_attendance.php',         'icon'=>'fa-map-location-dot',  'color'=>'emerald','title'=>'Attendance',   'desc'=>'Monitor daily check-ins'],
    ['href'=>'system_settings.php',          'icon'=>'fa-sliders-h',         'color'=>'orange', 'title'=>'Settings',   'desc'=>'Configure the system'],
    ['href'=>'../../pages/academics/teacher_reports.php','icon'=>'fa-paste','color'=>'indigo','title'=>'Teacher Reports',  'desc'=>'Review lessons & weekly reports'],
    ['href'=>'../../pages/academics/transcript_breakdown.php','icon'=>'fa-table-list','color'=>'emerald','title'=>'Class Broadsheet',  'desc'=>'View student performance broadsheets'],
    ['href'=>'../../pages/communication/dashboard.php',  'icon'=>'fa-tower-broadcast',  'color'=>'rose',  'title'=>'Communication',    'desc'=>'Announcements & internal messages'],
    ['href'=>'../../pages/finance/dashboard.php',  'icon'=>'fa-wallet',          'color'=>'teal',  'title'=>'Finance',    'desc'=>'Go to financial portal'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Command Center | <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="icon" type="image/jpeg" href="../../<?= getSystemLogo($conn) ?>">
    
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

    <?php include '../../includes/sidebar.php'; ?>

    <main class="lg:ml-72 min-h-screen pb-12 transition-all duration-300">
        
        <!-- Animated Background Header -->
        <div class="relative bg-gradient-to-br from-primary-900 via-blue-800 to-indigo-900 pt-16 md:pt-20 pb-24 overflow-hidden">
            <div class="absolute inset-0 bg-[url('../../assets/images/pattern-light.svg')] opacity-10"></div>
            <div class="absolute -right-20 -top-20 w-96 h-96 bg-primary-500 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse"></div>
            <div class="absolute -left-20 top-20 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse" style="animation-delay: 2s;"></div>
            
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <span class="bg-white/20 backdrop-blur text-white text-[0.65rem] font-bold uppercase tracking-widest px-3 py-1 rounded-full border border-white/20">
                                <i class="fas fa-satellite-dish mr-1"></i> Global Command Center
                            </span>
                            <span class="text-white/80 text-sm font-medium">
                                <?= htmlspecialchars($current_term) ?> &middot; <?= htmlspecialchars($display_academic_year) ?>
                            </span>
                        </div>
                        <h1 class="text-3xl md:text-4xl font-extrabold text-white font-display tracking-tight drop-shadow-sm">System Overview</h1>
                        <p class="text-blue-100 mt-2 max-w-2xl text-sm md:text-base">Real-time insights across academics, finance, and human resources.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 relative z-20 space-y-6">
            
            <!-- AT A GLANCE STATS ROW -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                <!-- Students -->
                <div class="glass-card rounded-2xl p-5 stat-card-hover transition-all duration-300 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-50 rounded-full group-hover:scale-110 transition-transform"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Active Students</p>
                            <h3 class="text-3xl font-extrabold font-display text-slate-800"><?= number_format($active_students) ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-xl shadow-inner border border-blue-200">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-xs font-semibold">
                        <span class="text-blue-600 bg-blue-50 px-2 py-0.5 rounded"><?= $unique_classes ?> Classes</span>
                        <span class="text-slate-400">Enrolled globally</span>
                    </div>
                </div>

                <!-- Attendance -->
                <div class="glass-card rounded-2xl p-5 stat-card-hover transition-all duration-300 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-50 rounded-full group-hover:scale-110 transition-transform"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Today's Attendance</p>
                            <h3 class="text-3xl font-extrabold font-display text-slate-800"><?= $attendance_rate ?>%</h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl shadow-inner border border-emerald-200">
                            <i class="fas fa-clipboard-user"></i>
                        </div>
                    </div>
                    <div class="mt-4 w-full bg-slate-100 rounded-full h-1.5">
                        <div class="bg-emerald-500 h-1.5 rounded-full" style="width: <?= $attendance_rate ?>%"></div>
                    </div>
                </div>

                <!-- Finance -->
                <div class="glass-card rounded-2xl p-5 stat-card-hover transition-all duration-300 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-purple-50 rounded-full group-hover:scale-110 transition-transform"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Term Collection</p>
                            <h3 class="text-3xl font-extrabold font-display text-slate-800"><?= $collection_rate ?>%</h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center text-xl shadow-inner border border-purple-200">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-between text-xs font-semibold">
                        <span class="text-slate-500">Collected: <span class="text-purple-600">GH₵<?= number_format($total_payments) ?></span></span>
                    </div>
                </div>

                <!-- Staff -->
                <div class="glass-card rounded-2xl p-5 stat-card-hover transition-all duration-300 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-orange-50 rounded-full group-hover:scale-110 transition-transform"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Faculty</p>
                            <h3 class="text-3xl font-extrabold font-display text-slate-800"><?= $total_users ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center text-xl shadow-inner border border-orange-200">
                            <i class="fas fa-users-gear"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-[0.65rem] font-bold text-slate-400 uppercase">
                        <span class="bg-slate-100 px-2 py-0.5 rounded text-slate-600"><?= $total_admins ?> Admins</span>
                        <span class="bg-slate-100 px-2 py-0.5 rounded text-slate-600"><?= $total_staff_users ?> Staff</span>
                    </div>
                </div>
            </div>

            <!-- ANALYTICS SECTION -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Financial Overview Cards -->
                <div class="glass-card rounded-3xl p-6 lg:col-span-2 shadow-sm border border-slate-200/60 flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-lg font-extrabold font-display text-slate-800 flex items-center gap-2">
                                <i class="fas fa-wallet text-purple-500"></i> Financial Overview
                            </h2>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Current Term (<?= htmlspecialchars($current_term) ?>)</p>
                        </div>
                        <a href="../../pages/finance/dashboard.php" class="text-xs font-bold text-purple-600 bg-purple-50 px-3 py-1.5 rounded-lg hover:bg-purple-100 transition-colors border border-purple-100 shadow-sm">Finance Hub</a>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 flex-1">
                        <!-- Total Expected -->
                        <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 flex flex-col justify-center">
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Expected Fees</p>
                            <h3 class="text-2xl font-extrabold font-display text-blue-600">GH₵<?= number_format($total_fees_assigned, 2) ?></h3>
                        </div>
                        <!-- Collected -->
                        <div class="bg-emerald-50 rounded-2xl p-5 border border-emerald-100 flex flex-col justify-center">
                            <p class="text-[0.65rem] font-bold text-emerald-600/70 uppercase tracking-widest mb-1">Payments Collected</p>
                            <h3 class="text-2xl font-extrabold font-display text-emerald-600">GH₵<?= number_format($total_payments, 2) ?></h3>
                        </div>
                        <!-- Outstanding -->
                        <div class="bg-amber-50 rounded-2xl p-5 border border-amber-100 flex flex-col justify-center">
                            <p class="text-[0.65rem] font-bold text-amber-600/70 uppercase tracking-widest mb-1">Outstanding Fees</p>
                            <h3 class="text-2xl font-extrabold font-display text-amber-600">GH₵<?= number_format($outstanding_fees, 2) ?></h3>
                        </div>
                        <!-- Expenses -->
                        <div class="bg-rose-50 rounded-2xl p-5 border border-rose-100 flex flex-col justify-center">
                            <p class="text-[0.65rem] font-bold text-rose-600/70 uppercase tracking-widest mb-1">Total Expenses</p>
                            <h3 class="text-2xl font-extrabold font-display text-rose-600">GH₵<?= number_format($total_expenses, 2) ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Academic Submissions Chart -->
                <div class="glass-card rounded-3xl p-6 shadow-sm border border-slate-200/60 flex flex-col">
                    <div class="mb-4">
                        <h2 class="text-lg font-extrabold font-display text-slate-800 flex items-center gap-2">
                            <i class="fas fa-file-signature text-emerald-500"></i> Academic Documents
                        </h2>
                        <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Lesson Plan Status Distribution</p>
                    </div>
                    <div class="flex-1 flex flex-col items-center justify-center relative min-h-[220px]">
                        <?php if (array_sum($lp_stats) === 0): ?>
                            <div class="text-center text-slate-400">
                                <i class="fas fa-chart-pie text-5xl opacity-20 mb-3 block"></i>
                                <p class="text-xs font-semibold">No lesson plans submitted yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="h-40 w-40 relative mb-4">
                                <canvas id="academicChart"></canvas>
                                <!-- Center Text -->
                                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                    <span class="text-3xl font-extrabold font-display text-slate-800 leading-none"><?= array_sum($lp_stats) ?></span>
                                    <span class="text-[0.55rem] font-black uppercase tracking-widest text-slate-400 mt-1">Total Plans</span>
                                </div>
                            </div>
                            
                            <!-- Custom HTML Legend -->
                            <div class="grid grid-cols-2 gap-x-6 gap-y-3 w-full max-w-[240px] px-2">
                                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <span class="w-3 h-3 rounded-full bg-emerald-500"></span> Approved
                                </div>
                                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <span class="w-3 h-3 rounded-full bg-amber-500"></span> Pending
                                </div>
                                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <span class="w-3 h-3 rounded-full bg-slate-300"></span> Draft
                                </div>
                                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <span class="w-3 h-3 rounded-full bg-rose-500"></span> Rejected
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- LOWER GRID -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Class Progress Tracker -->
                <div class="glass-card rounded-3xl shadow-sm border border-slate-200/60 lg:col-span-2 flex flex-col overflow-hidden">
                    <div class="p-6 border-b border-slate-100/50 bg-slate-50/50 flex justify-between items-center">
                        <div>
                            <h2 class="text-lg font-extrabold font-display text-slate-800 flex items-center gap-2">
                                <i class="fas fa-list-check text-indigo-500"></i> Class Progress Tracker
                            </h2>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Today's Attendance Status</p>
                        </div>
                    </div>
                    <div class="flex-1 overflow-x-auto max-h-[350px] overflow-y-auto custom-scrollbar">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead class="sticky top-0 z-10 bg-white/95 backdrop-blur shadow-sm border-b border-slate-100 text-[0.65rem] uppercase tracking-widest text-slate-400 font-black">
                                <tr>
                                    <th class="px-6 py-3">Class</th>
                                    <th class="px-6 py-3">Attendance Progress</th>
                                    <th class="px-6 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php if(empty($class_progress)): ?>
                                    <tr><td colspan="3" class="px-6 py-8 text-center text-slate-400 text-xs font-semibold">No active classes found.</td></tr>
                                <?php else: foreach($class_progress as $cname => $data): 
                                    $att_total = $data['total_students'];
                                    $att_marked = $data['attendance_marked'];
                                    $att_pct = $att_total > 0 ? round(($att_marked / $att_total) * 100) : 0;
                                    
                                    $color = 'orange';
                                    if ($att_pct == 100) $color = 'emerald';
                                    elseif ($att_pct == 0) $color = 'rose';
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-800"><?= htmlspecialchars($cname) ?></div>
                                        <div class="text-[0.65rem] text-slate-400 font-semibold"><?= $att_total ?> Enrolled</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-between text-[0.65rem] font-bold uppercase tracking-widest text-slate-500 mb-1.5">
                                            <span><?= $att_marked ?> / <?= $att_total ?> Present</span>
                                            <span class="text-<?= $color ?>-600"><?= $att_pct ?>%</span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-1.5">
                                            <div class="bg-<?= $color ?>-500 h-1.5 rounded-full transition-all duration-500" style="width: <?= $att_pct ?>%"></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="../academics/attendance.php?class=<?= urlencode($cname) ?>&action=entry" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-50 text-slate-400 border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 transition-all shadow-sm" title="Mark Attendance">
                                            <i class="fas fa-clipboard-check text-xs"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Combined Quick Actions & Celebrations -->
                <div class="flex flex-col gap-6">
                    <!-- Quick Actions Grid -->
                    <div class="glass-card rounded-3xl p-6 shadow-sm border border-slate-200/60">
                        <h2 class="text-sm font-extrabold font-display text-slate-800 mb-4 flex items-center gap-2">
                            <i class="fas fa-bolt text-amber-500"></i> Quick Actions
                        </h2>
                        <div class="grid grid-cols-2 gap-3">
                            <?php foreach($actions as $a): 
                                $colorClass = "bg-{$a['color']}-50 text-{$a['color']}-600 border-{$a['color']}-100 group-hover:bg-{$a['color']}-500 group-hover:text-white";
                            ?>
                            <a href="<?= $a['href'] ?>" class="p-3 rounded-2xl border border-slate-100 bg-white shadow-sm hover:-translate-y-1 hover:shadow-md transition-all duration-300 group flex flex-col gap-2 relative overflow-hidden">
                                <div class="w-8 h-8 rounded-lg <?= $colorClass ?> flex items-center justify-center transition-colors shadow-inner">
                                    <i class="fas <?= $a['icon'] ?> text-sm"></i>
                                </div>
                                <h3 class="font-bold text-slate-800 text-xs leading-tight group-hover:text-<?= $a['color'] ?>-600 transition-colors"><?= $a['title'] ?></h3>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Celebrations Mini -->
                    <div class="glass-card rounded-3xl p-6 shadow-sm border border-slate-200/60 flex-1 flex flex-col bg-gradient-to-b from-white to-pink-50/30">
                        <h2 class="text-sm font-extrabold font-display text-slate-800 mb-4 flex items-center gap-2">
                            <i class="fas fa-cake-candles text-pink-500"></i> Birthdays in <?= substr($display_month, 0, 3) ?>
                        </h2>
                        <div class="flex-1 overflow-y-auto max-h-[150px] custom-scrollbar pr-2 space-y-2">
                            <?php 
                            $all_b = array_merge($student_birthdays, $staff_birthdays);
                            usort($all_b, fn($a, $b) => $a['day'] <=> $b['day']);
                            if(empty($all_b)): 
                            ?>
                                <p class="text-xs text-slate-400 font-medium text-center italic mt-4">No birthdays this month.</p>
                            <?php else: foreach($all_b as $b): ?>
                                <div class="flex items-center gap-3 p-2 rounded-xl <?= $b['is_today'] ? 'bg-pink-50 border border-pink-100 shadow-sm' : 'hover:bg-slate-50' ?> transition-colors">
                                    <div class="w-9 h-9 rounded-lg flex flex-col items-center justify-center text-[0.55rem] font-black <?= $b['is_today'] ? 'bg-pink-500 text-white animate-pulse shadow-md shadow-pink-200' : 'bg-slate-100 text-slate-500 border border-slate-200' ?>">
                                        <span class="uppercase opacity-70"><?= substr($display_month, 0, 3) ?></span>
                                        <span class="text-sm leading-none"><?= $b['day'] ?></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-xs font-bold text-slate-800 truncate <?= $b['is_today'] ? 'text-pink-600' : '' ?>">
                                            <?= htmlspecialchars($b['name']) ?>
                                            <?php if($b['is_today']) echo '<i class="fas fa-star text-amber-400 ml-1"></i>'; ?>
                                        </h4>
                                        <p class="text-[0.6rem] text-slate-400 font-semibold truncate"><?= htmlspecialchars($b['info']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Chart Initializations -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            
            // Finance Chart removed in favor of stat cards.

            // Academic Chart
            const academicCtx = document.getElementById('academicChart');
            if (academicCtx) {
                new Chart(academicCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Approved', 'Pending', 'Draft', 'Rejected'],
                        datasets: [{
                            data: [
                                <?= $lp_stats['approved'] ?>, 
                                <?= $lp_stats['pending'] ?>, 
                                <?= $lp_stats['draft'] ?>, 
                                <?= $lp_stats['rejected'] ?>
                            ],
                            backgroundColor: [
                                '#10b981', // emerald-500
                                '#f59e0b', // amber-500
                                '#cbd5e1', // slate-300
                                '#f43f5e'  // rose-500
                            ],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '75%',
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(15, 23, 42, 0.9)',
                                titleFont: { family: "'Inter', sans-serif", size: 12 },
                                bodyFont: { family: "'Inter', sans-serif", size: 12, weight: 'bold' },
                                padding: 10,
                                cornerRadius: 8,
                                displayColors: true
                            }
                        }
                    }
                });
            }

        });
    </script>

</body>
</html>
