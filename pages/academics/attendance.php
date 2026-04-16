<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'facilitator' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../includes/login.php');
    exit;
}

$success = '';
$error = '';
$uid = $_SESSION['user_id'];
$current_semester = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);
$weeks_limit = intval(getSystemSetting($conn, 'weeks_per_semester', 12));
$total_instructional_days = getInstructionalDaysCount($conn, $current_semester, $current_year);
$semester_start = getSystemSetting($conn, 'semester_start_date');
$semester_end = getSystemSetting($conn, 'semester_end_date');

// Fetch Holidays/Closed Dates
$holidays = [];
$h_res = $conn->query("SELECT event_date, description, event_type FROM academic_calendar");
if ($h_res) {
    while($hr = $h_res->fetch_assoc()) $holidays[$hr['event_date']] = $hr;
}

// Find what classes this teacher is allocated to
$allocated_classes = [];
if ($_SESSION['role'] === 'admin') {
    $res = $conn->query("SELECT DISTINCT class FROM students WHERE status='active' ORDER BY class");
    while($r = $res->fetch_assoc()) $allocated_classes[] = $r['class'];
} else {
    $res = $conn->query("SELECT DISTINCT class_name FROM teacher_allocations WHERE teacher_id = $uid AND year = '$current_year' AND is_class_teacher = 1");
    if ($res) {
        while($r = $res->fetch_assoc()) $allocated_classes[] = $r['class_name'];
    }
    if (empty($allocated_classes)) {
        $res = $conn->query("SELECT DISTINCT class_name FROM teacher_allocations WHERE teacher_id = $uid AND year = '$current_year'");
        while($r = $res->fetch_assoc()) $allocated_classes[] = $r['class_name'];
    }
}

$selected_class = $_GET['class'] ?? ($allocated_classes[0] ?? '');
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_week = intval($_GET['week'] ?? getSystemSetting($conn, "active_week_{$selected_class}", 1));

// Handle Week Number Update (Initialization)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_active_week') {
    $new_week = intval($_POST['week_number']);
    setSystemSetting($conn, "active_week_{$selected_class}", $new_week, $_SESSION['username']);
    header("Location: attendance.php?class=$selected_class&date=$selected_date&week=$new_week&success=Week+Initialized");
    exit;
}

// Process Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
    $class_to_mark = $_POST['class_name'];
    $date_to_mark = $_POST['attendance_date'];
    $week_to_mark = intval($_POST['week_val']);
    
    if ($_SESSION['role'] === 'admin' || in_array($class_to_mark, $allocated_classes)) {
        $count = 0;
        foreach ($_POST['attendance'] as $student_id => $status) {
            $sid = intval($student_id);
            $stat = $conn->real_escape_string($status);
            $rem = $conn->real_escape_string($_POST['remarks'][$sid] ?? '');
            
            $check = $conn->query("SELECT id FROM attendance WHERE student_id = $sid AND attendance_date = '$date_to_mark'");
            if ($check->num_rows > 0) {
                $conn->query("UPDATE attendance SET status = '$stat', remarks = '$rem', week_number = $week_to_mark WHERE student_id = $sid AND attendance_date = '$date_to_mark'");
            } else {
                $conn->query("INSERT INTO attendance (student_id, attendance_date, status, remarks, semester, academic_year, week_number) VALUES ($sid, '$date_to_mark', '$stat', '$rem', '$current_semester', '$current_year', $week_to_mark)");
            }
            $count++;
        }
        setSystemSetting($conn, "active_week_{$class_to_mark}", $week_to_mark, $_SESSION['username']);
        $success = "Successfully saved attendance for $count students.";
    }
}

// Fetch students
$students = [];
if ($selected_class && in_array($selected_class, $allocated_classes)) {
    $stmt = $conn->prepare("
        SELECT s.id, s.first_name, s.last_name, a.status, a.remarks 
        FROM students s 
        LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date = ? 
        WHERE s.class = ? AND s.status = 'active'
        ORDER BY s.first_name ASC
    ");
    $stmt->bind_param("ss", $selected_date, $selected_class);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $students[] = $row;
}

$view_mode = $_GET['mode'] ?? 'daily';
$tracker_data = [];
$tracker_dates = [];
if ($view_mode === 'history' && $selected_class) {
    // Determine target week slice
    $target_week = intval($_GET['h_week'] ?? $selected_week);
    
    // Fetch all attendance for this specific week across the whole semester
    $t_res = $conn->query("
        SELECT a.student_id, a.attendance_date, a.status, a.week_number
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE s.class = '$selected_class' AND a.week_number = $target_week AND a.semester = '$current_semester'
        ORDER BY a.attendance_date ASC
    ");
    
    while($row = $t_res->fetch_assoc()) {
        $tracker_data[$row['student_id']][$row['attendance_date']] = $row['status'];
        if (!in_array($row['attendance_date'], $tracker_dates)) $tracker_dates[] = $row['attendance_date'];
    }
    sort($tracker_dates);
    
    // Fallback: If no records for this week yet, show placeholders for Mon-Fri
    if (empty($tracker_dates)) {
        // Find the Monday of 'some' week. Since we don't have a calendar start date, we use current date as anchor
        // This part is tricky without a true academic calendar start date. 
        // We'll show the last 5 school days instead.
        for($i=0; $i<5; $i++) {
            $d = date('Y-m-d', strtotime("-$i days"));
            if (date('N', strtotime($d)) < 6) $tracker_dates[] = $d;
        }
        sort($tracker_dates);
    }
}

// Calculate Stats for header
$stats = ['present' => 0, 'absent' => 0, 'total' => count($students)];
foreach ($students as $s) {
    if ($s['status'] === 'present') $stats['present']++;
    elseif ($s['status'] === 'absent') $stats['absent']++;
}
$participation_rate = $stats['total'] > 0 ? round(($stats['present'] / $stats['total']) * 100) : 0;
$is_holiday = isset($holidays[$selected_date]);
$holiday_info = $is_holiday ? $holidays[$selected_date] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Hub - <?= htmlspecialchars($selected_class) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .glass-nav { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(226, 232, 240, 0.8); }
        .stat-card { background: white; border: 1px solid #f1f5f9; border-radius: 1.25rem; padding: 1.5rem; transition: all 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        .attendance-row:hover { background: #f8fafc; }
        .radio-btn { display: none; }
        .status-pill {
            cursor: pointer; padding: 0.5rem 1rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; border: 1.5px solid #e2e8f0; transition: all 0.2s; color: #94a3b8;
        }
        .radio-btn:checked + .present { background: #f0fdf4; color: #15803d; border-color: #15803d; }
        .radio-btn:checked + .absent { background: #fef2f2; color: #b91c1c; border-color: #b91c1c; }
        
        .sticky-col { position: sticky; left: 0; background: white; z-index: 10; border-right: 1px solid #f1f5f9; }
        .tracker-grid::-webkit-scrollbar { height: 8px; }
        .tracker-grid::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body class="bg-[#fcfdfe] text-slate-800">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="ml-72 min-h-screen pb-20">
        <!-- Professional Header -->
        <header class="glass-nav sticky top-0 z-50 px-10 py-5">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                <div>
                    <div class="flex items-center gap-2 text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-1">
                        <i class="fas fa-fingerprint"></i> Institutional Biometrics
                    </div>
                    <div class="flex items-center gap-4">
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Attendance <span class="text-indigo-600">Hub</span></h1>
                        <div class="h-6 w-px bg-slate-200"></div>
                        <nav class="flex bg-slate-100 p-1 rounded-xl">
                            <a href="?class=<?= $selected_class ?>&mode=daily" class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all <?= $view_mode==='daily'?'bg-white shadow-sm text-indigo-600':'text-slate-500 hover:text-slate-800' ?>">Daily Roll Call</a>
                            <a href="?class=<?= $selected_class ?>&mode=history" class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all <?= $view_mode==='history'?'bg-white shadow-sm text-indigo-600':'text-slate-500 hover:text-slate-800' ?>">Semester Ledger</a>
                        </nav>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                <div class="flex items-center gap-6">
                    <div class="text-right border-r border-slate-100 pr-6 mr-2 hidden md:block">
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 flex items-center justify-end gap-1">
                            <i class="fas fa-clock text-[8px]"></i> Session Timeline
                        </div>
                        <div class="text-[11px] font-bold text-slate-600 flex items-center gap-2">
                            <?php if($semester_start && $semester_end): ?>
                                <span><?= date('M d', strtotime($semester_start)) ?></span>
                                <i class="fas fa-arrow-right text-[8px] opacity-30"></i>
                                <span><?= date('M d, Y', strtotime($semester_end)) ?></span>
                            <?php else: ?>
                                <span class="text-slate-400 italic">Dates not set</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-black text-indigo-500 uppercase tracking-widest leading-none mb-1"><?= $current_semester ?></div>
                        <div class="text-xs font-bold text-slate-700"><?= $current_year ?> Academic Session</div>
                    </div>
                    <button onclick="window.print()" class="w-10 h-10 bg-white border border-slate-200 rounded-xl flex items-center justify-center text-slate-400 hover:text-indigo-600 transition-colors shadow-sm">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-10 py-8">
            
            <?php if ($success): ?>
                <div class="bg-emerald-50 border border-emerald-100 text-emerald-700 px-6 py-4 rounded-2xl mb-8 flex items-center gap-3 animate-in fade-in slide-in-from-top-4 duration-300">
                    <div class="w-8 h-8 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-600"><i class="fas fa-check"></i></div>
                    <span class="font-bold text-sm"><?= $success ?></span>
                </div>
            <?php endif; ?>

            <!-- Global Stats Ribbon -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                <div class="stat-card">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Institutional Days</div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black text-slate-900"><?= $total_instructional_days ?></span>
                        <span class="text-xs font-bold text-slate-400">School Sessions</span>
                    </div>
                    <div class="mt-4 h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-indigo-500 rounded-full" style="width: 100%"></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-3">Live Presence</div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black text-emerald-600"><?= $stats['present'] ?></span>
                        <span class="text-xs font-bold text-slate-400">/ <?= $stats['total'] ?> Present</span>
                    </div>
                    <div class="mt-4 h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full" style="width: <?= $participation_rate ?>%"></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="text-[10px] font-black text-red-500 uppercase tracking-widest mb-3">Live Absence</div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black text-red-600"><?= $stats['absent'] ?></span>
                        <span class="text-xs font-bold text-slate-400">/ <?= $stats['total'] ?> Missing</span>
                    </div>
                    <div class="mt-4 h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-red-400 rounded-full" style="width: <?= $stats['total']>0 ? ($stats['absent']/$stats['total'])*100 : 0 ?>%"></div>
                    </div>
                </div>
                <div class="stat-card bg-slate-900 border-slate-800">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">Class Performance</div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black text-white"><?= $participation_rate ?>%</span>
                        <span class="text-xs font-bold text-slate-500">Participation</span>
                    </div>
                    <div class="mt-4 flex gap-1">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <div class="h-1 flex-1 rounded-full <?= $participation_rate >= ($i*20) ? 'bg-indigo-500':'bg-slate-800' ?>"></div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-3xl p-8 border border-slate-100 shadow-sm mb-10">
                <form method="GET" class="flex flex-wrap items-end gap-6">
                    <input type="hidden" name="mode" value="<?= $view_mode ?>">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">Selected Class / Level</label>
                        <select name="class" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500 transition-all cursor-pointer appearance-none">
                            <?php foreach($allocated_classes as $cl): ?>
                                <option value="<?= htmlspecialchars($cl) ?>" <?= $selected_class === $cl ? 'selected' : '' ?>><?= htmlspecialchars($cl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if($view_mode === 'daily'): ?>
                        <div class="w-48">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">Session Date</label>
                            <input type="date" name="date" value="<?= $selected_date ?>" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                        </div>
                    <?php else: ?>
                        <div class="w-48">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">Analysis Week</label>
                            <select name="h_week" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500 transition-all cursor-pointer">
                                <?php for($w=1; $w<=$weeks_limit; $w++): ?>
                                    <option value="<?= $w ?>" <?= ($target_week ?? 1) == $w ? 'selected' : '' ?>>Week <?= $w ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex-1 min-w-[280px]">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">Real-time Search</label>
                        <div class="relative">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                            <input type="text" id="liveSearch" placeholder="Filter by student name..." class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-10 pr-4 py-3 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-100 transition-all">
                        </div>
                    </div>
                </form>
            </div>

            <?php if($view_mode === 'daily'): ?>
                <!-- Daily View Overhaul -->
                <?php if($is_holiday): ?>
                    <div class="bg-orange-50/50 border border-orange-100 rounded-[2.5rem] p-20 text-center animate-in zoom-in duration-500">
                        <div class="w-24 h-24 bg-orange-100/50 rounded-full flex items-center justify-center text-orange-500 mx-auto mb-6 shadow-inner">
                            <i class="fas fa-umbrella-beach text-4xl"></i>
                        </div>
                        <h2 class="text-3xl font-black text-orange-900 mb-2 uppercase tracking-tight">Institutional Break</h2>
                        <p class="text-orange-700/70 font-bold text-lg mb-8"><?= htmlspecialchars($holiday_info['description']) ?></p>
                        <div class="flex justify-center gap-4">
                            <a href="attendance.php?class=<?= $selected_class ?>&date=<?= date('Y-m-d', strtotime($selected_date.' +1 day')) ?>" class="bg-white text-orange-700 px-6 py-2.5 rounded-xl font-bold text-sm border border-orange-200 shadow-sm hover:bg-orange-100 transition-all">Check Next Day</a>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="class_name" value="<?= htmlspecialchars($selected_class) ?>">
                        <input type="hidden" name="attendance_date" value="<?= $selected_date ?>">
                        <input type="hidden" name="week_val" value="<?= $selected_week ?>">
                        
                        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden mb-8">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                        <th class="px-10 py-6">Identity & Records</th>
                                        <th class="px-10 py-6 text-center">Engagement Status</th>
                                        <th class="px-10 py-6">Incident Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50" id="attendanceBody">
                                    <?php foreach($students as $s): 
                                        $stat = $s['status'] ?? 'present';
                                    ?>
                                        <tr class="attendance-row transition-all duration-200" data-name="<?= strtolower(htmlspecialchars($s['first_name'].' '.$s['last_name'])) ?>">
                                            <td class="px-10 py-5">
                                                <div class="flex items-center gap-4">
                                                    <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center font-black text-slate-500 text-xs shadow-inner">
                                                        <?= substr($s['first_name'], 0, 1) . substr($s['last_name'], 0, 1) ?>
                                                    </div>
                                                    <div>
                                                        <div class="font-bold text-slate-900 leading-tight"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
                                                        <div class="text-[9px] font-black text-indigo-400 uppercase tracking-widest mt-0.5">SID: #<?= str_pad($s['id'], 5, '0', STR_PAD_LEFT) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-10 py-5">
                                                    <input type="radio" class="radio-btn" name="attendance[<?= $s['id'] ?>]" value="present" id="p_<?= $s['id'] ?>" <?= $stat==='present'?'checked':'' ?>>
                                                    <label class="status-pill present" for="p_<?= $s['id'] ?>">Present</label>
                                                    
                                                    <input type="radio" class="radio-btn" name="attendance[<?= $s['id'] ?>]" value="absent" id="a_<?= $s['id'] ?>" <?= $stat==='absent'?'checked':'' ?>>
                                                    <label class="status-pill absent" for="a_<?= $s['id'] ?>">Absent</label>
                                            </td>
                                            <td class="px-10 py-5">
                                                <input type="text" name="remarks[<?= $s['id'] ?>]" value="<?= htmlspecialchars($s['remarks'] ?? '') ?>" placeholder="Observation..." class="w-full bg-slate-50 border-0 border-b border-transparent focus:border-indigo-500 focus:bg-white rounded-lg px-3 py-1.5 text-xs font-medium outline-none transition-all">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="flex justify-between items-center p-8 bg-slate-900 rounded-[2rem] shadow-xl border border-slate-800">
                           <div class="flex gap-4">
                               <button type="button" onclick="markAll('present')" class="px-5 py-2.5 bg-slate-800 text-slate-300 rounded-xl font-bold text-xs hover:bg-slate-700 transition-all border border-slate-700">Mark All Present</button>
                               <button type="button" onclick="markAll('absent')" class="px-5 py-2.5 bg-slate-800 text-slate-300 rounded-xl font-bold text-xs hover:bg-slate-700 transition-all border border-slate-700">Mark All Absent</button>
                           </div>
                           <button type="submit" class="bg-indigo-600 text-white px-10 py-3 rounded-xl font-black text-sm uppercase tracking-widest hover:bg-indigo-500 hover:scale-[1.02] active:scale-95 transition-all shadow-lg shadow-indigo-900/40 flex items-center gap-3">
                               <i class="fas fa-lock text-indigo-400"></i> Save Register
                           </button>
                        </div>
                    </form>
                <?php endif; ?>

            <?php else: ?>
                <!-- Semester Ledger Overhaul -->
                <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden tracker-grid overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[1000px]">
                        <thead>
                            <tr class="bg-slate-900 border-b border-slate-800">
                                <th class="px-8 py-8 sticky-col bg-slate-900 text-[10px] font-black uppercase tracking-widest text-slate-400 w-64 border-r border-slate-800">Operational Log</th>
                                <?php foreach($tracker_dates as $date): 
                                    $is_h = isset($holidays[$date]);
                                ?>
                                    <th class="px-4 py-8 text-center border-r border-slate-800 min-w-[90px] <?= $is_h ? 'bg-orange-900/20':'' ?>">
                                        <div class="text-[10px] font-black uppercase tracking-wider <?= $is_h ? 'text-orange-400':'text-slate-500' ?>"><?= date('D', strtotime($date)) ?></div>
                                        <div class="text-lg font-black <?= $is_h ? 'text-orange-200':'text-white' ?>"><?= date('d', strtotime($date)) ?></div>
                                        <div class="text-[9px] font-bold text-slate-600 mt-1 uppercase"><?= date('M', strtotime($date)) ?></div>
                                    </th>
                                <?php endforeach; ?>
                                <th class="px-6 py-8 text-center bg-indigo-950 text-indigo-400 border-l border-indigo-900 border-r border-indigo-900">
                                    <div class="text-[10px] font-black uppercase mb-1">Weekly Sum</div>
                                    <i class="fas fa-plus-circle"></i>
                                </th>
                                <th class="px-8 py-8 text-center bg-slate-950 text-white">
                                    <div class="text-[10px] font-black uppercase mb-1 text-slate-500">Institution Total</div>
                                    <div class="text-xs font-black uppercase opacity-60">Semester</div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php 
                            // Fetch raw totals for each student in current semester
                            $totals = [];
                            $t_res = $conn->query("SELECT student_id, COUNT(*) as p FROM attendance WHERE semester = '$current_semester' AND academic_year = '$current_year' AND status='present' GROUP BY student_id");
                            while($tr = $t_res->fetch_assoc()) $totals[$tr['student_id']] = $tr['p'];

                            foreach($students as $s): 
                                $id = $s['id'];
                                $w_sum = 0;
                            ?>
                                <tr class="attendance-row group transition-all" data-name="<?= strtolower(htmlspecialchars($s['first_name'].' '.$s['last_name'])) ?>">
                                    <td class="px-8 py-4 sticky-col group-hover:bg-slate-50">
                                        <div class="font-bold text-slate-900 leading-tight"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
                                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-0.5">ID: #<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></div>
                                    </td>
                                    <?php foreach($tracker_dates as $date): 
                                        $st = $tracker_data[$id][$date] ?? null;
                                        if($st === 'present') $w_sum++;
                                        $is_h = isset($holidays[$date]);
                                    ?>
                                        <td class="px-4 py-4 text-center border-r border-slate-50/50 <?= $is_h?'bg-orange-50/20':'' ?>">
                                            <?php if($is_h): ?>
                                                <div class="w-6 h-1 bg-orange-200 rounded-full mx-auto opacity-30"></div>
                                            <?php elseif($st === 'present'): ?>
                                                <div class="w-2.5 h-2.5 bg-emerald-500 rounded-full mx-auto shadow-[0_0_10px_rgba(16,185,129,0.3)]"></div>
                                            <?php elseif($st === 'absent'): ?>
                                                <div class="w-2 h-2 border-2 border-red-400 rounded-full mx-auto opacity-80"></div>
                                            <?php else: ?>
                                                <div class="w-1.5 h-1.5 bg-slate-200 rounded-full mx-auto opacity-20"></div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="px-6 py-4 text-center bg-indigo-50/30 border-l border-indigo-100">
                                        <span class="font-black text-indigo-600 text-sm"><?= $w_sum ?></span>
                                        <div class="text-[8px] font-bold text-slate-400 uppercase tracking-tighter mt-1">Present</div>
                                    </td>
                                    <td class="px-8 py-4 text-center bg-slate-50 border-l border-slate-100">
                                        <div class="flex flex-col items-center">
                                            <span class="font-black text-slate-900 text-sm"><?= $totals[$id] ?? 0 ?> <span class="text-[10px] text-slate-300">/</span> <?= $total_instructional_days ?></span>
                                            <div class="text-[8px] font-bold text-slate-400 uppercase tracking-widest mt-1">Sessions Marked</div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-8 flex justify-end">
                    <div class="flex items-center gap-6 bg-white px-8 py-4 rounded-3xl border border-slate-100 shadow-sm">
                        <div class="flex items-center gap-2"><div class="w-3 h-3 bg-emerald-500 rounded-full"></div> <span class="text-[10px] font-black text-slate-500 uppercase">Present</span></div>
                        <div class="flex items-center gap-2"><div class="w-3 h-3 border-2 border-red-400 rounded-full"></div> <span class="text-[10px] font-black text-slate-500 uppercase">Absent</span></div>
                        <div class="flex items-center gap-2"><div class="w-3 h-1 bg-orange-200 rounded-full"></div> <span class="text-[10px] font-black text-slate-500 uppercase">Institutional Break</span></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
    function markAll(status) {
        document.querySelectorAll(`.radio-btn[value="${status}"]`).forEach(radio => {
            radio.checked = true;
        });
    }

    const liveSearch = document.getElementById('liveSearch');
    if (liveSearch) {
        liveSearch.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.attendance-row').forEach(row => {
                const name = row.getAttribute('data-name');
                row.style.display = name.includes(term) ? 'table-row' : 'none';
            });
        });
    }
    </script>
</body>
</html>
