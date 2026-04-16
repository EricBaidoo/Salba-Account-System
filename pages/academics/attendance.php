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
$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);
$weeks_limit = intval(getSystemSetting($conn, 'weeks_per_term', 12));

// Fetch Holidays/Closed Dates
$holidays = [];
$h_res = $conn->query("SELECT event_date, description, event_type FROM academic_calendar");
while($hr = $h_res->fetch_assoc()) $holidays[$hr['event_date']] = $hr;

// Find what classes this teacher is allocated to
$allocated_classes = [];
if ($_SESSION['role'] === 'admin') {
    // Admin sees all
    $res = $conn->query("SELECT DISTINCT class FROM students WHERE status='active' ORDER BY class");
    while($r = $res->fetch_assoc()) $allocated_classes[] = $r['class'];
} else {
    // Teacher sees only assigned classes
    // Teacher sees only their Permanent (Home) classes for roll-call
    $res = $conn->query("SELECT DISTINCT class_name FROM teacher_allocations WHERE teacher_id = $uid AND year = '$current_year' AND is_class_teacher = 1");
    if ($res) {
        while($r = $res->fetch_assoc()) $allocated_classes[] = $r['class_name'];
    }
    // If no permanent class, check if they have any assigned subject classes (optional fallback)
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
    
    // Security check - can teacher mark this class?
    if ($_SESSION['role'] === 'admin' || in_array($class_to_mark, $allocated_classes)) {
        
        // Loop through submitted students
        $count = 0;
        foreach ($_POST['attendance'] as $student_id => $status) {
            $sid = intval($student_id);
            $stat = $conn->real_escape_string($status);
            $rem = $conn->real_escape_string($_POST['remarks'][$sid] ?? '');
            
            // Upsert mechanism
            $check = $conn->query("SELECT id FROM attendance WHERE student_id = $sid AND attendance_date = '$date_to_mark'");
            if ($check->num_rows > 0) {
                // Update
                $conn->query("UPDATE attendance SET status = '$stat', remarks = '$rem', week_number = $week_to_mark WHERE student_id = $sid AND attendance_date = '$date_to_mark'");
            } else {
                // Insert
                $conn->query("INSERT INTO attendance (student_id, attendance_date, status, remarks, semester, academic_year, week_number) VALUES ($sid, '$date_to_mark', '$stat', '$rem', '$current_term', '$current_year', $week_to_mark)");
            }
            $count++;
        }
        // Update global/class setting for the active week as well
        setSystemSetting($conn, "active_week_{$class_to_mark}", $week_to_mark, $_SESSION['username']);
        $success = "Successfully saved attendance for $count students in Week $week_to_mark.";
    } else {
        $error = "Unauthorized attempt to mark attendance for an unassigned class.";
    }
}

// Fetch students for the selected class
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
    while($row = $res->fetch_assoc()){
        $students[] = $row;
    }
}

// 1. Calculate Stats for the "Daily Pulse"
$stats = [
    'present' => 0,
    'late' => 0,
    'absent' => 0,
    'excused' => 0,
    'total' => count($students)
];

foreach ($students as $s) {
    if ($s['status'] && isset($stats[$s['status']])) {
        $stats[$s['status']]++;
    }
}

// Calculation for participation rate
$participation_rate = $stats['total'] > 0 ? round((($stats['present'] + $stats['late']) / $stats['total']) * 100) : 0;

$is_holiday = isset($holidays[$selected_date]);
$holiday_info = $is_holiday ? $holidays[$selected_date] : null;

// 2. Fetch Weekly Data for the "History Tracker" (optional mode)
$view_mode = $_GET['mode'] ?? 'daily';
$tracker_data = [];
$tracker_dates = [];
if ($view_mode === 'history' && $selected_class) {
    // Last 10 school days (2 weeks of instruction)
    for ($i = 0, $count = 0; $count < 10 && $i < 20; $i++) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $dayNum = date('N', strtotime($d));
        if ($dayNum < 6) { 
            $tracker_dates[] = $d;
            $count++;
        }
    }
    sort($tracker_dates);
    
    // Fetch all attendance for these dates in this class
    $date_list = "'" . implode("','", $tracker_dates) . "'";
    $t_res = $conn->query("
        SELECT a.student_id, a.attendance_date, a.status, s.first_name, s.last_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE s.class = '$selected_class' AND a.attendance_date IN ($date_list)
        ORDER BY s.first_name ASC, a.attendance_date ASC
    ");
    
    while($row = $t_res->fetch_assoc()) {
        $tracker_data[$row['student_id']][$row['attendance_date']] = $row['status'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Attendance - Teacher Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .glass-header { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid rgba(0,0,0,0.05); }
        .attendance-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid rgba(0,0,0,0.05); }
        .attendance-card:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -10px rgba(0, 0, 0, 0.1); border-color: rgba(99, 102, 241, 0.2); }
        
        .radio-btn { display: none; }
        .status-pill {
            cursor: pointer; padding: 6px 14px; border-radius: 99px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
            border: 1px solid #e5e7eb; transition: all 0.2s; color: #9ca3af; background: white;
        }
        
        .radio-btn:checked + .present { background: #ecfdf5; color: #059669; border-color: #059669; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.1); }
        .radio-btn:checked + .late { background: #fffbeb; color: #d97706; border-color: #d97706; box-shadow: 0 2px 4px rgba(217, 119, 6, 0.1); }
        .radio-btn:checked + .absent { background: #fef2f2; color: #dc2626; border-color: #dc2626; box-shadow: 0 2px 4px rgba(220, 38, 38, 0.1); }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="ml-72 min-h-screen bg-white">
        <!-- Modern Header -->
        <div class="glass-header px-10 py-6 sticky top-0 z-40 bg-white/80">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                <div>
                    <div class="flex items-center gap-2 text-indigo-500 font-bold text-[10px] uppercase tracking-widest mb-1">
                        <i class="fas fa-calendar-check"></i> Attendance Operations
                    </div>
                    <div class="flex items-center gap-4">
                        <h1 class="text-3xl font-black text-gray-900 tracking-tight">
                            Register <span class="text-indigo-600">Hub</span>
                        </h1>
                        <div class="flex bg-gray-100 p-1 rounded-xl items-center">
                            <a href="?class=<?= $selected_class ?>&date=<?= $selected_date ?>&mode=daily" class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all <?= $view_mode === 'daily' ? 'bg-white shadow-sm text-indigo-600' : 'text-gray-400 hover:text-gray-600' ?>">Daily Roll Call</a>
                            <a href="?class=<?= $selected_class ?>&date=<?= $selected_date ?>&mode=history" class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all <?= $view_mode === 'history' ? 'bg-white shadow-sm text-indigo-600' : 'text-gray-400 hover:text-gray-600' ?>">Attendance Tracker</a>
                        </div>
                        <div class="ml-4 px-4 py-1.5 bg-indigo-600 text-white rounded-xl text-xs font-black shadow-lg shadow-indigo-200">
                            TERM WEEK: <?= $selected_week ?>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3">
                     <button type="button" onclick="markAll('present')" class="bg-emerald-50 text-emerald-700 px-5 py-2.5 rounded-xl font-bold text-xs hover:bg-emerald-100 transition flex items-center gap-2 border border-emerald-100/50">
                        <i class="fas fa-check-double text-[10px]"></i> Mark All Present
                    </button>
                    <a href="dashboard.php" class="bg-gray-50 text-gray-400 p-2.5 rounded-xl hover:bg-gray-100 transition border border-gray-100">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto p-10">
            
            <!-- Weekly Initialization (Monday Check) -->
            <?php if (date('N', strtotime($selected_date)) == 1): ?>
                <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 p-8 rounded-3xl shadow-xl mb-10 text-white flex justify-between items-center relative overflow-hidden">
                    <div class="relative z-10">
                        <h3 class="text-xl font-black mb-1">New Week Initialization</h3>
                        <p class="text-xs text-indigo-100 font-medium italic">It's Monday! Please confirm the instructional week number for this session.</p>
                    </div>
                    <form method="POST" class="relative z-10 flex items-center gap-4">
                        <input type="hidden" name="action" value="set_active_week">
                        <select name="week_number" class="bg-white/10 border border-white/20 rounded-xl px-4 py-2 text-white font-black text-sm outline-none focus:bg-white/20 transition-all cursor-pointer">
                            <?php for($w=1; $w<=$weeks_limit; $w++): ?>
                                <option value="<?= $w ?>" <?= $selected_week == $w ? 'selected' : '' ?> class="bg-indigo-900">Week <?= $w ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="bg-white text-indigo-600 px-6 py-2 rounded-xl font-black text-xs hover:bg-indigo-50 transition-all shadow-lg">START WEEK</button>
                    </form>
                    <i class="fas fa-calendar-alt absolute right-4 bottom-[-20%] text-9xl opacity-10 pointer-events-none"></i>
                </div>
            <?php endif; ?>

            <!-- Holiday/Closed Day Guard -->
            <?php if ($is_holiday): ?>
                <div class="bg-amber-50 border border-amber-200 p-10 rounded-3xl shadow-xl mb-10 text-center relative overflow-hidden">
                    <div class="relative z-10">
                        <div class="w-20 h-20 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-6 text-amber-600 shadow-inner">
                            <i class="fas fa-umbrella-beach text-3xl"></i>
                        </div>
                        <h2 class="text-3xl font-black text-amber-900 mb-2 uppercase tracking-tighter">School Closed</h2>
                        <p class="text-amber-700 font-bold text-lg"><?= htmlspecialchars($holiday_info['description'] ?: 'Instruction Suspended') ?></p>
                        <div class="mt-4 px-4 py-1 bg-amber-200/50 inline-block rounded-full text-[10px] font-black text-amber-800 uppercase tracking-widest">Type: <?= strtoupper($holiday_info['event_type']) ?></div>
                    </div>
                    <i class="fas fa-sun absolute left-[-5%] bottom-[-20%] text-9xl text-amber-100 opacity-40 pointer-events-none"></i>
                </div>
            <?php endif; ?>

            <!-- Real-time Stat Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Class Roster</div>
                    <div class="flex items-end gap-2">
                        <span class="text-3xl font-black text-gray-900"><?= $stats['total'] ?></span>
                        <span class="text-xs font-bold text-gray-400 mb-1">Students</span>
                    </div>
                </div>
                <div class="bg-emerald-50 p-6 rounded-2xl border border-emerald-100/50 shadow-sm">
                    <div class="text-[10px] font-black text-emerald-600/50 uppercase tracking-widest mb-1">Currently Present</div>
                    <div class="flex items-end gap-2 text-emerald-600">
                        <span class="text-3xl font-black"><?= $stats['present'] + $stats['late'] ?></span>
                        <span class="text-xs font-bold mb-1 opacity-70">Enrolled</span>
                    </div>
                </div>
                <div class="bg-red-50 p-6 rounded-2xl border border-red-100/50 shadow-sm">
                    <div class="text-[10px] font-black text-red-600/50 uppercase tracking-widest mb-1">Absentee Alert</div>
                    <div class="flex items-end gap-2 text-red-600">
                        <span class="text-3xl font-black"><?= $stats['absent'] ?></span>
                        <span class="text-xs font-bold mb-1 opacity-70">Missing</span>
                    </div>
                </div>
                <div class="bg-indigo-600 p-6 rounded-2xl border border-indigo-700 shadow-lg text-white">
                    <div class="text-[10px] font-black text-indigo-200 uppercase tracking-widest mb-1">Daily Participation</div>
                    <div class="flex items-end gap-2">
                        <span class="text-3xl font-black"><?= $participation_rate ?>%</span>
                        <div class="flex-1 h-2 bg-indigo-500 rounded-full mb-2 overflow-hidden">
                            <div class="h-full bg-white rounded-full" style="width: <?= $participation_rate ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($success): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-3 shadow-sm">
                    <i class="fas fa-check-circle text-emerald-500"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-3 shadow-sm">
                    <i class="fas fa-exclamation-circle text-red-500"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if(empty($allocated_classes)): ?>
                <div class="bg-white p-12 text-center rounded-xl shadow-sm border border-gray-100">
                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center text-4xl text-gray-300 mx-auto mb-4">
                        <i class="fas fa-link-slash"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">No Classes Assigned</h3>
                    <p class="text-gray-500 max-w-md mx-auto">You have not been assigned to any classes for the <?= $current_term ?> <?= $current_year ?> semester. Please contact the Academic Supervisor.</p>
                </div>
            <?php else: ?>
                <!-- Filters & Search -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 mb-8 flex flex-col md:flex-row items-center gap-8">
                    <form method="GET" class="flex flex-col md:flex-row items-center gap-6 flex-1">
                        <input type="hidden" name="mode" value="<?= $view_mode ?>">
                        <div class="w-full md:w-56">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Target Level</label>
                            <select name="class" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 font-bold text-sm outline-none transition-all" onchange="this.form.submit()">
                                <?php foreach($allocated_classes as $cl): ?>
                                    <option value="<?= htmlspecialchars($cl) ?>" <?= $selected_class === $cl ? 'selected' : '' ?>><?= htmlspecialchars($cl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-full md:w-48">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Selected Date</label>
                            <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 font-bold text-sm outline-none transition-all" onchange="this.form.submit()">
                        </div>
                        <div class="w-full md:w-32">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Active Week</label>
                            <select name="week" class="w-full px-4 py-2 bg-indigo-50 border border-indigo-100 rounded-xl font-black text-xs text-indigo-600 outline-none transition-all" onchange="this.form.submit()">
                                <?php for($w=1; $w<=$weeks_limit; $w++): ?>
                                    <option value="<?= $w ?>" <?= $selected_week == $w ? 'selected' : '' ?>>Week <?= $w ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </form>
                    
                    <div class="w-full md:w-80 border-l border-gray-100 pl-8">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Live Student Filter</label>
                        <div class="relative">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 text-sm"></i>
                            <input type="text" id="studentSearch" placeholder="Identity Search..." class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 font-bold text-sm outline-none transition-all">
                        </div>
                    </div>
                </div>

                <!-- View Modes -->
                <?php if($view_mode === 'daily' && $selected_class): ?>
                    <!-- Daily Roll Call View -->
                    <form method="POST">
                        <input type="hidden" name="class_name" value="<?= htmlspecialchars($selected_class) ?>">
                        <input type="hidden" name="attendance_date" value="<?= htmlspecialchars($selected_date) ?>">
                        <input type="hidden" name="week_val" value="<?= $selected_week ?>">
                        
                        <?php if ($is_holiday): ?>
                            <div class="bg-white/30 backdrop-blur-md rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-8 p-20 text-center">
                                <i class="fas fa-lock text-gray-300 text-5xl mb-4"></i>
                                <div class="text-gray-400 font-bold">Register is locked for institutional breaks.</div>
                            </div>
                        <?php else: ?>
                            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-8">
                                <table class="w-full text-left border-collapse">
                                <thead class="bg-gray-50/50 text-gray-400 border-b border-gray-100 text-[10px] uppercase font-black tracking-widest">
                                    <tr>
                                        <th class="px-10 py-6">Student Identity</th>
                                        <th class="px-10 py-6 text-center">Daily Status</th>
                                        <th class="px-10 py-6">Incident Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50/50" id="attendanceTableBody">
                                    <?php foreach($students as $idx => $s): 
                                        $stat = $s['status'] ?? 'present';
                                    ?>
                                        <tr class="hover:bg-gray-50/30 transition-colors group attendance-row" data-name="<?= strtolower(htmlspecialchars($s['first_name'] . ' ' . $s['last_name'])) ?>">
                                            <td class="px-10 py-6">
                                                <div class="flex items-center gap-4">
                                                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-black text-sm shadow-sm border border-indigo-100/50 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                                                        <?= substr(htmlspecialchars($s['first_name']), 0, 1) . substr(htmlspecialchars($s['last_name']), 0, 1) ?>
                                                    </div>
                                                    <div>
                                                        <div class="font-black text-gray-900 flex items-center gap-2 tracking-tight">
                                                            <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>
                                                            <?php if($s['status']): ?>
                                                                <i class="fas fa-check-circle text-[10px] text-emerald-500"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">SID: #<?= str_pad($s['id'], 4, '0', STR_PAD_LEFT) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-10 py-6">
                                                <div class="flex justify-center items-center gap-3">
                                                    <input type="radio" class="radio-btn t-radio" name="attendance[<?= $s['id'] ?>]" value="present" id="p_<?= $s['id'] ?>" <?= $stat==='present'?'checked':'' ?>>
                                                    <label class="status-pill present" for="p_<?= $s['id'] ?>">Present</label>
                                                    
                                                    <input type="radio" class="radio-btn t-radio" name="attendance[<?= $s['id'] ?>]" value="late" id="l_<?= $s['id'] ?>" <?= $stat==='late'?'checked':'' ?>>
                                                    <label class="status-pill late" for="l_<?= $s['id'] ?>">Late</label>
                                                    
                                                    <input type="radio" class="radio-btn t-radio" name="attendance[<?= $s['id'] ?>]" value="absent" id="a_<?= $s['id'] ?>" <?= $stat==='absent'?'checked':'' ?>>
                                                    <label class="status-pill absent" for="a_<?= $s['id'] ?>">Absent</label>
                                                </div>
                                            </td>
                                            <td class="px-10 py-6">
                                                <div class="relative">
                                                    <i class="far fa-comment-dots absolute left-0 top-1/2 -translate-y-1/2 text-gray-300 text-xs translate-x-1"></i>
                                                    <input type="text" name="remarks[<?= $s['id'] ?>]" value="<?= htmlspecialchars($s['remarks'] ?? '') ?>" placeholder="Add incident report..." class="w-full pl-8 bg-transparent border-b border-gray-100 focus:border-indigo-400 focus:outline-none text-xs font-bold py-2 text-gray-500 transition-colors">
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="flex flex-col md:flex-row justify-between items-center bg-gray-900 p-10 rounded-[2.5rem] shadow-2xl border border-gray-800">
                            <div class="flex items-center gap-5 text-gray-400 font-medium mb-6 md:mb-0">
                                <div class="w-14 h-14 bg-gray-800 rounded-3xl flex items-center justify-center shadow-inner border border-gray-700/50 text-indigo-400 text-xl">
                                    <i class="fas fa-fingerprint"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs font-black text-gray-500 uppercase tracking-widest">Institutional Lockdown</span>
                                    <span class="text-sm">Register active for <strong class="text-white"><?= htmlspecialchars($selected_class) ?></strong> on <strong class="text-indigo-400"><?= date('M d, Y', strtotime($selected_date)) ?></strong></span>
                                </div>
                            </div>
                            <div class="flex justify-end gap-3 mb-10">
                                <button type="submit" class="bg-indigo-600 text-white font-black py-4 px-12 rounded-2xl shadow-xl shadow-indigo-100 hover:bg-indigo-700 hover:scale-[1.02] active:scale-95 transition-all text-sm uppercase tracking-widest flex items-center gap-3 border-b-4 border-indigo-800">
                                    <i class="fas fa-save text-lg"></i>
                                    Submit Class Register
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>

                <?php elseif($view_mode === 'history' && $selected_class): ?>
                    <!-- Full Register History Tracker -->
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-gray-50/50 text-[10px] uppercase font-black tracking-widest text-gray-400">
                                    <tr>
                                        <th class="px-8 py-6 sticky left-0 bg-gray-50/50 z-10 w-64 border-r border-gray-100 shadow-sm">Student Identity</th>
                                        <?php 
                                        // Week selector for history view
                                        $h_week = intval($_GET['h_week'] ?? $selected_week);
                                        ?>
                                        <th colspan="<?= count($tracker_dates) ?>" class="px-8 py-4 bg-gray-900 text-white text-[10px] font-black uppercase text-center border-b border-gray-800">
                                            Weekly Instruction Pattern
                                        </th>
                                        <th class="px-6 py-6 text-center text-indigo-600 font-extrabold text-[10px] uppercase border-l border-gray-100">Weekly Score</th>
                                        <th class="px-6 py-6 text-center text-gray-900 font-extrabold text-[10px] uppercase border-l border-gray-100">Termly Hub</th>
                                    </tr>
                                    <tr class="bg-white">
                                        <th class="sticky left-0 bg-white border-r border-gray-100"></th>
                                        <?php foreach($tracker_dates as $date): ?>
                                            <th class="px-4 py-4 text-center border-r border-gray-50 text-[9px] font-bold text-gray-400 uppercase">
                                                <?= date('D d', strtotime($date)) ?>
                                            </th>
                                        <?php endforeach; ?>
                                        <th class="border-l border-gray-100"></th>
                                        <th class="border-l border-gray-100"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50/50">
                                    <?php 
                                    // Total Semester Days Calculation (Weekdays minus Holidays)
                                    // Simplified: total_weeks * 5 minus any holiday that fell on Mon-Fri
                                    $h_count = 0;
                                    foreach($holidays as $hd => $hi) {
                                        if (date('N', strtotime($hd)) < 6) $h_count++;
                                    }
                                    $total_possible_days = ($weeks_limit * 5) - $h_count;

                                    $term_presence = [];
                                    $tp_res = $conn->query("SELECT student_id, COUNT(*) as p FROM attendance WHERE semester = '$current_term' AND academic_year = '$current_year' AND week_number <= $weeks_limit AND (status='present' OR status='late') GROUP BY student_id");
                                    while($tp = $tp_res->fetch_assoc()) $term_presence[$tp['student_id']] = $tp['p'];

                                    foreach($students as $s): 
                                        $id = $s['id'];
                                        $presence = $term_presence[$id] ?? 0;
                                        
                                        // Weekly sum for visible dates
                                        $w_presence = 0;
                                        foreach($tracker_dates as $td) {
                                            $st = $tracker_data[$id][$td] ?? '';
                                            if ($st === 'present' || $st === 'late') $w_presence++;
                                        }
                                    ?>
                                        <tr class="hover:bg-gray-50/20 transition-colors">
                                            <td class="px-8 py-5 sticky left-0 bg-white z-10 border-r border-gray-50 shadow-sm">
                                                <div class="font-bold text-gray-900 text-sm tracking-tight"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                                <div class="text-[9px] text-gray-400 font-bold uppercase">#<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?></div>
                                            </td>
                                            <?php foreach($tracker_dates as $date): 
                                                $status = $tracker_data[$id][$date] ?? null;
                                            ?>
                                                <td class="px-4 py-5 text-center border-r border-gray-50/50">
                                                    <?php if($status === 'present'): ?>
                                                        <i class="fas fa-check-circle text-emerald-500 text-sm"></i>
                                                    <?php elseif($status === 'late'): ?>
                                                        <i class="fas fa-clock text-amber-500 text-sm"></i>
                                                    <?php elseif($status === 'absent'): ?>
                                                        <i class="fas fa-times-circle text-red-500 text-sm"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-minus text-gray-200 text-xs opacity-30"></i>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td class="px-6 py-5 text-center border-l border-gray-100 bg-indigo-50/20">
                                                <span class="font-black text-xs text-indigo-600"><?= $w_presence ?></span>
                                                <span class="text-[8px] text-gray-400 font-bold block uppercase tracking-tighter">This Week</span>
                                            </td>
                                            <td class="px-6 py-5 text-center border-l border-gray-100">
                                                <div class="flex flex-col items-center">
                                                    <span class="font-black text-xs text-gray-900 leading-none"><?= $presence ?> / <?= $total_possible_days ?></span>
                                                    <span class="text-[8px] text-gray-400 font-bold uppercase mt-1">Institutional Total</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="pb-20"></div>
    </main>

    <script>
    function markAll(status) {
        document.querySelectorAll(`.status-pill.${status}`).forEach(label => {
            label.click();
        });
    }

    // Live Search Logic
    const searchInput = document.getElementById('studentSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const semester = e.target.value.toLowerCase();
            document.querySelectorAll('.attendance-row').forEach(row => {
                const name = row.getAttribute('data-name');
                if (name.includes(semester)) {
                    row.style.display = 'table-row';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    </script>
</body>
</html>
