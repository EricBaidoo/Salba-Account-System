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

// Student stats
$active_students   = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='active'")->fetch_assoc()['c'] ?? 0;
$inactive_students = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='inactive'")->fetch_assoc()['c'] ?? 0;
$unique_classes    = $conn->query("SELECT COUNT(DISTINCT class) as c FROM students WHERE status='active'")->fetch_assoc()['c'] ?? 0;

// Attendance stats (Today)
$today = date('Y-m-d');
$present_stmt = $conn->prepare("SELECT COUNT(*) as c FROM attendance WHERE attendance_date=? AND status='present'");
$present_stmt->bind_param('s', $today);
$present_stmt->execute();
$present_students = (int)$present_stmt->get_result()->fetch_assoc()['c'];
$present_stmt->close();
$attendance_rate = ($active_students > 0) ? round(($present_students / $active_students) * 100) : 0;

// Financial stats for current semester
$total_fees_assigned = $conn->query("SELECT SUM(amount) as total FROM student_fees WHERE semester = '$current_term' AND academic_year = '$academic_year' AND status != 'cancelled'")->fetch_assoc()['total'] ?? 0;
$total_payments = $conn->query("SELECT SUM(amount) as total FROM payments WHERE semester = '$current_term' AND academic_year = '$academic_year'")->fetch_assoc()['total'] ?? 0;
$outstanding_fees = $total_fees_assigned - $total_payments;

// Payments this semester
$pay_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE semester=? AND academic_year=?");
$pay_stmt->bind_param('ss', $current_term, $academic_year);
$pay_stmt->execute();
$total_payments = (float)$pay_stmt->get_result()->fetch_assoc()['total'];
$pay_stmt->close();

// Expenses this semester
$exp_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE semester=? AND academic_year=?");
$exp_stmt->bind_param('ss', $current_term, $academic_year);
$exp_stmt->execute();
$total_expenses = (float)$exp_stmt->get_result()->fetch_assoc()['total'];
$exp_stmt->close();

$net_position = $total_payments - $total_expenses;

// System users
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

// Progress Tracking (Attendance & Grades)
$today_date = date('Y-m-d');
$class_progress = [];

// Get all active classes and their total active students
$c_res = $conn->query("SELECT class, COUNT(*) as student_count FROM students WHERE status='active' GROUP BY class ORDER BY class");
if ($c_res) {
    while ($row = $c_res->fetch_assoc()) {
        $cname = $row['class'];
        $class_progress[$cname] = [
            'total_students' => (int)$row['student_count'],
            'attendance_marked' => 0,
            'expected_subjects' => 0,
            'graded_subjects' => 0
        ];
    }
}

// Attendance (Present) today
$att_res = $conn->prepare("SELECT s.class, COUNT(DISTINCT a.student_id) as marked_count FROM attendance a JOIN students s ON a.student_id = s.id WHERE a.attendance_date = ? AND s.status='active' AND LOWER(a.status) = 'present' GROUP BY s.class");
if ($att_res) {
    $att_res->bind_param('s', $today_date);
    $att_res->execute();
    $res = $att_res->get_result();
    while ($row = $res->fetch_assoc()) {
        if (isset($class_progress[$row['class']])) {
            $class_progress[$row['class']]['attendance_marked'] = (int)$row['marked_count'];
        }
    }
    $att_res->close();
}

// Expected subjects per class
$sub_res = $conn->query("SELECT class_name, COUNT(DISTINCT subject_id) as sub_count FROM class_subjects GROUP BY class_name");
if ($sub_res) {
    while ($row = $sub_res->fetch_assoc()) {
        if (isset($class_progress[$row['class_name']])) {
            $class_progress[$row['class_name']]['expected_subjects'] = (int)$row['sub_count'];
        }
    }
}
// 1. CELEBRATORY BIRTHDAYS (Current Month ONLY)
$student_birthdays = [];
$staff_birthdays = [];
$current_month_num = (int)date('n');
$today_day = (int)date('j');

// Fetch Students Birthdays (Filtered to Current Month)
$s_res = $conn->query("SELECT first_name, last_name, date_of_birth, class, status FROM students WHERE status='active' AND MONTH(date_of_birth) = $current_month_num");
if ($s_res) {
    while ($row = $s_res->fetch_assoc()) {
        $day = (int)date('j', strtotime($row['date_of_birth']));
        $days_until = $day - $today_day;
        
        $student_birthdays[] = [
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'info' => $row['class'],
            'day' => $day,
            'days_until' => $days_until,
            'is_today' => ($day == $today_day),
            'is_passed' => ($day < $today_day)
        ];
    }
    usort($student_birthdays, fn($a, $b) => $a['day'] <=> $b['day']);
}

// Fetch Staff Birthdays (Filtered to Current Month)
$st_res = $conn->query("SELECT full_name, job_title, department, date_of_birth FROM staff_profiles WHERE employment_status='active' AND MONTH(date_of_birth) = $current_month_num");
if ($st_res) {
    while ($row = $st_res->fetch_assoc()) {
        $day = (int)date('j', strtotime($row['date_of_birth']));
        $days_until = $day - $today_day;
        
        $staff_birthdays[] = [
            'name' => $row['full_name'],
            'info' => $row['job_title'] . ' (' . $row['department'] . ')',
            'day' => $day,
            'days_until' => $days_until,
            'is_today' => ($day == $today_day),
            'is_passed' => ($day < $today_day)
        ];
    }
    usort($staff_birthdays, fn($a, $b) => $a['day'] <=> $b['day']);
}

$month_names = [1=>"January", 2=>"February", 3=>"March", 4=>"April", 5=>"May", 6=>"June", 7=>"July", 8=>"August", 9=>"September", 10=>"October", 11=>"November", 12=>"December"];
$display_month = $month_names[$current_month_num];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration Dashboard — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="icon" type="image/jpeg" href="../../<?= getSystemLogo($conn) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/tailwind.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">

        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                <span class="text-xs font-bold text-blue-600 uppercase tracking-widest bg-blue-50 px-3 py-1 rounded-full">
                    <i class="fas fa-cog mr-1"></i> Administration
                </span>
                <span class="text-xs text-gray-400">
                    <?php echo htmlspecialchars($current_term); ?> &middot; <?php echo htmlspecialchars($display_academic_year); ?>
                </span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">Administration Dashboard</h1>
            <p class="text-gray-500 mt-1">Central control for system configuration, student oversight, and staff management</p>
        </div>

        <!-- Students Overview -->
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <i class="fas fa-user-graduate"></i> Students Overview
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-3xl font-bold text-blue-600"><?php echo number_format($active_students); ?></div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1">Active Students</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-3xl font-bold text-gray-300"><?php echo number_format($inactive_students); ?></div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1">Inactive Students</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-3xl font-bold text-purple-600"><?php echo $unique_classes; ?></div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1">Active Classes</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-3xl font-bold <?php echo $attendance_rate >= 80 ? 'text-emerald-500' : 'text-orange-500'; ?>"><?php echo $attendance_rate; ?>%</div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1">Today's Attendance</div>
            </div>
        </div>

        <!-- Financial Overview (current trimester) -->
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <i class="fas fa-chart-line"></i> Financial Overview — <?php echo htmlspecialchars($current_term); ?>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-3xl font-bold text-orange-500">GH₵<?php echo number_format($outstanding_fees, 0); ?></div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1">Outstanding Fees</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-3xl font-bold text-green-600">GH₵<?php echo number_format($total_payments, 0); ?></div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1">Payments Received</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-3xl font-bold text-red-500">GH₵<?php echo number_format($total_expenses, 0); ?></div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1">Total Expenses</div>
            </div>
        </div>

        <!-- Staff & Users -->
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <i class="fas fa-users"></i> Staff & System Users
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-3xl font-bold text-gray-800"><?php echo $total_users; ?></div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1">Total Users</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-3xl font-bold text-red-600"><?php echo $total_admins; ?></div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1">Admins</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-3xl font-bold text-indigo-600"><?php echo $total_supervisors; ?></div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1">Supervisors</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-3xl font-bold text-blue-500"><?php echo $total_staff_users; ?></div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1">Staff</div>
            </div>
        </div>

        <!-- Academic Progress Tracker -->
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2 mt-4">
            <i class="fas fa-list-check"></i> Class Progress Tracker
        </h2>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-8">
            <div class="overflow-x-auto max-h-[21.875rem] overflow-y-auto custom-scrollbar relative">
                <table class="w-full text-left border-collapse text-sm">
                    <thead class="sticky top-0 z-10 bg-gray-50 shadow-sm border-b border-gray-100">
                        <tr class="font-semibold text-gray-500">
                            <th class="px-6 py-4">Class</th>
                            <th class="px-6 py-4">Today's Attendance (<?php echo date('M j'); ?>)</th>
                            <th class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(empty($class_progress)): ?>
                            <tr><td colspan="3" class="px-6 py-8 text-center text-gray-400">No active classes found.</td></tr>
                        <?php else: foreach($class_progress as $cname => $data): 
                            // Attendance logic
                            $att_total = $data['total_students'];
                            $att_marked = $data['attendance_marked'];
                            $att_pct = $att_total > 0 ? round(($att_marked / $att_total) * 100) : 0;
                            
                            $att_color = 'bg-red-500';
                            if ($att_pct == 100) $att_color = 'bg-emerald-500';
                            elseif ($att_pct > 0) $att_color = 'bg-amber-400';
                        ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 font-bold text-gray-900">
                                <?php echo htmlspecialchars($cname); ?>
                                <span class="block text-[0.625rem] uppercase text-gray-400 font-normal"><?php echo $att_total; ?> Active Students</span>
                            </td>
                            
                            <td class="px-6 py-4">
                                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 text-xs mb-1">
                                    <span class="font-medium text-gray-700"><?php echo $att_marked; ?> of <?php echo $att_total; ?> Present</span>
                                    <span class="font-bold <?php echo str_replace('bg-','text-',$att_color); ?>"><?php echo $att_pct; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2">
                                    <div class="<?php echo $att_color; ?> h-2 rounded-full transition-all duration-500" style="width: <?php echo $att_pct; ?>%"></div>
                                </div>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="../academics/attendance.php?class=<?php echo urlencode($cname); ?>&action=entry" class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-green-100 hover:text-green-600 transition" title="Mark Attendance">
                                        <i class="fas fa-calendar-check text-sm"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Birthdays & Celebrations -->
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2 mt-4">
            <i class="fas fa-cake-candles text-pink-500"></i> Birthdays in <?= $display_month ?>
        </h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            
            <!-- STUDENT BIRTHDAYS -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col h-[31.25rem]">
                <div class="p-6 border-b border-gray-50 bg-gradient-to-r from-blue-50/50 to-transparent flex justify-between items-center">
                    <div>
                        <h3 class="font-black text-gray-900 flex items-center gap-2 text-lg italic uppercase tracking-tighter">
                            <i class="fas fa-graduation-cap text-blue-500"></i> Student <span class="text-blue-500">Sparklers</span>
                        </h3>
                        <p class="text-[0.625rem] text-gray-400 font-bold uppercase tracking-widest mt-0.5">Enrolled Celebrants — <?= $display_month ?></p>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar p-6">
                    <?php if (empty($student_birthdays)): ?>
                        <div class="h-full flex flex-col items-center justify-center text-gray-300">
                            <i class="fas fa-calendar-times text-5xl mb-4 opacity-10"></i>
                            <p class="text-sm font-medium">No student birthdays this month.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 gap-3">
                            <?php foreach ($student_birthdays as $b): ?>
                                <div class="flex items-center justify-between p-3.5 rounded-xl border border-transparent hover:border-blue-100 hover:bg-blue-50/30 transition-all group <?= $b['is_today'] ? 'bg-blue-50 border-blue-200' : ($b['is_passed'] ? 'opacity-40 grayscale-[0.5]' : '') ?>">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg flex flex-col items-center justify-center text-[0.625rem] font-black <?= $b['is_today'] ? 'bg-blue-600 text-white shadow-lg shadow-blue-200 animate-bounce' : 'bg-gray-50 text-gray-400 border border-gray-100' ?>">
                                            <span class="text-[0.5rem] uppercase opacity-60"><?= substr($display_month, 0, 3) ?></span>
                                            <span class="text-xs -mt-1"><?= $b['day'] ?></span>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                                                <?= htmlspecialchars($b['name']) ?>
                                                <?php if ($b['is_today']): ?>
                                                    <i class="fas fa-bahai text-blue-500 animate-spin-slow"></i>
                                                <?php endif; ?>
                                            </h4>
                                            <p class="text-[0.625rem] text-gray-400 font-bold uppercase tracking-widest"><?= htmlspecialchars($b['info']) ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <?php if ($b['is_today']): ?>
                                            <div class="text-[0.625rem] font-black text-blue-600 uppercase tracking-widest bg-white px-2.5 py-1 rounded-full shadow-sm border border-blue-100">Today</div>
                                        <?php elseif ($b['is_passed']): ?>
                                            <div class="text-[0.5rem] font-black text-gray-400 uppercase tracking-widest bg-gray-100 px-2 py-0.5 rounded">Passed</div>
                                        <?php else: ?>
                                            <div class="text-[0.625rem] font-black text-gray-600 uppercase tracking-widest">In <?= $b['days_until'] ?> days</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- STAFF BIRTHDAYS -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col h-[31.25rem]">
                <div class="p-6 border-b border-gray-50 bg-gradient-to-r from-indigo-50/50 to-transparent flex justify-between items-center">
                    <div>
                        <h3 class="font-black text-gray-900 flex items-center gap-2 text-lg italic uppercase tracking-tighter">
                            <i class="fas fa-user-tie text-indigo-500"></i> Staff <span class="text-indigo-500">Stars</span>
                        </h3>
                        <p class="text-[0.625rem] text-gray-400 font-bold uppercase tracking-widest mt-0.5">Faculty Celebrants — <?= $display_month ?></p>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar p-6">
                    <?php if (empty($staff_birthdays)): ?>
                        <div class="h-full flex flex-col items-center justify-center text-gray-300">
                            <i class="fas fa-calendar-times text-5xl mb-4 opacity-10"></i>
                            <p class="text-sm font-medium">No staff birthdays this month.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 gap-3">
                            <?php foreach ($staff_birthdays as $b): ?>
                                <div class="flex items-center justify-between p-3.5 rounded-xl border border-transparent hover:border-indigo-100 hover:bg-indigo-50/30 transition-all group <?= $b['is_today'] ? 'bg-indigo-50 border-indigo-200' : ($b['is_passed'] ? 'opacity-40 grayscale-[0.5]' : '') ?>">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg flex flex-col items-center justify-center text-[0.625rem] font-black <?= $b['is_today'] ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-200 animate-bounce' : 'bg-gray-50 text-gray-400 border border-gray-100' ?>">
                                            <span class="text-[0.5rem] uppercase opacity-60"><?= substr($display_month, 0, 3) ?></span>
                                            <span class="text-xs -mt-1"><?= $b['day'] ?></span>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                                                <?= htmlspecialchars($b['name']) ?>
                                                <?php if ($b['is_today']): ?>
                                                    <i class="fas fa-bahai text-indigo-500 animate-spin-slow"></i>
                                                <?php endif; ?>
                                            </h4>
                                            <p class="text-[0.625rem] text-gray-400 font-bold uppercase tracking-widest"><?= htmlspecialchars($b['info']) ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <?php if ($b['is_today']): ?>
                                            <div class="text-[0.625rem] font-black text-indigo-600 uppercase tracking-widest bg-white px-2.5 py-1 rounded-full shadow-sm border border-indigo-100">Today</div>
                                        <?php elseif ($b['is_passed']): ?>
                                            <div class="text-[0.5rem] font-black text-gray-400 uppercase tracking-widest bg-gray-100 px-2 py-0.5 rounded">Passed</div>
                                        <?php else: ?>
                                            <div class="text-[0.625rem] font-black text-gray-600 uppercase tracking-widest">In <?= $b['days_until'] ?> days</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2 mt-4">
            <i class="fas fa-bolt text-amber-500"></i> Quick Actions Center
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php
            $actions = [
                ['href'=>'students/view_students.php',   'icon'=>'fa-users-viewfinder', 'color'=>'blue',   'title'=>'Student Directory',  'desc'=>'View and manage all enrolled students'],
                ['href'=>'students/add_student_form.php','icon'=>'fa-user-plus',         'color'=>'green',  'title'=>'New Enrollment',     'desc'=>'Enroll a new student into the system'],
                ['href'=>'staff/view_staff.php',         'icon'=>'fa-id-card',           'color'=>'purple', 'title'=>'Staff Directory',    'desc'=>'View and manage teaching and support staff'],
                ['href'=>'staff/add_staff.php',          'icon'=>'fa-user-tie',          'color'=>'indigo', 'title'=>'Add Staff',          'desc'=>'Create a new staff profile'],
                ['href'=>'staff_attendance.php',         'icon'=>'fa-map-location-dot',  'color'=>'emerald','title'=>'Staff Attendance',   'desc'=>'Track faculty geofence & punctuality'],
                ['href'=>'system_settings.php',          'icon'=>'fa-sliders-h',         'color'=>'orange', 'title'=>'System Settings',   'desc'=>'Configure semester and academic year'],
                ['href'=>'register.php',                 'icon'=>'fa-user-shield',       'color'=>'gray',   'title'=>'Register User',      'desc'=>'Add a new system user account'],
                ['href'=>'students/bulk_upload_students.php','icon'=>'fa-file-arrow-up', 'color'=>'teal',   'title'=>'Bulk Upload',        'desc'=>'Import students via CSV file'],
                ['href'=>'../../pages/academics/dashboard.php','icon'=>'fa-graduation-cap','color'=>'violet','title'=>'Go to Academics',  'desc'=>'View academics module'],
            ];
            $colors = [
                'blue'   => 'hover:border-blue-200   group-hover:text-blue-600   bg-blue-50   text-blue-600',
                'green'  => 'hover:border-green-200  group-hover:text-green-600  bg-green-50  text-green-600',
                'purple' => 'hover:border-purple-200 group-hover:text-purple-600 bg-purple-50 text-purple-600',
                'indigo' => 'hover:border-indigo-200 group-hover:text-indigo-600 bg-indigo-50 text-indigo-600',
                'orange' => 'hover:border-orange-200 group-hover:text-orange-600 bg-orange-50 text-orange-600',
                'gray'   => 'hover:border-gray-300   group-hover:text-gray-700   bg-gray-50   text-gray-600',
                'teal'   => 'hover:border-teal-200   group-hover:text-teal-600   bg-teal-50   text-teal-600',
                'emerald'=> 'hover:border-emerald-200 group-hover:text-emerald-600 bg-emerald-50 text-emerald-600',
                'violet' => 'hover:border-violet-200 group-hover:text-violet-600 bg-violet-50 text-violet-600',
            ];
            foreach ($actions as $a):
                [$borderHover, $textHover, $iconBg, $iconColor] = explode(' ', $colors[$a['color']]);
            ?>
            <a href="<?php echo $a['href']; ?>"
               class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 hover:shadow-md <?php echo $borderHover; ?> transition-all group flex flex-col gap-2">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 <?php echo $iconBg; ?> rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas <?php echo $a['icon']; ?> <?php echo $iconColor; ?>"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 <?php echo $textHover; ?> transition-colors"><?php echo $a['title']; ?></h3>
                </div>
                <p class="text-sm text-gray-400 pl-1"><?php echo $a['desc']; ?></p>
            </a>
            <?php endforeach; ?>
        </div>

    </main>
</body>
</html>
