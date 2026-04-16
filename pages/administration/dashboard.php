<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../includes/login.php');
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
require_once '../../includes/student_balance_functions.php';
$student_balances    = getAllStudentBalances($conn, null, 'active', $current_term, $academic_year);
$total_fees_assigned = 0;
$outstanding_fees    = 0;
foreach ($student_balances as $s) {
    $total_fees_assigned += (float)($s['total_fees'] ?? 0);
    $outstanding_fees    += (float)($s['net_balance'] ?? 0);
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration Dashboard — <?php echo htmlspecialchars($school_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="ml-72 p-8 min-h-screen">

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
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
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

        <!-- Financial Overview (current semester) -->
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
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
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

        <!-- Quick Actions -->
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <i class="fas fa-bolt"></i> Quick Actions
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php
            $actions = [
                ['href'=>'students/view_students.php',   'icon'=>'fa-users-viewfinder', 'color'=>'blue',   'title'=>'Student Directory',  'desc'=>'View and manage all enrolled students'],
                ['href'=>'students/add_student_form.php','icon'=>'fa-user-plus',         'color'=>'green',  'title'=>'New Enrollment',     'desc'=>'Enroll a new student into the system'],
                ['href'=>'staff/view_staff.php',         'icon'=>'fa-id-card',           'color'=>'purple', 'title'=>'Staff Directory',    'desc'=>'View and manage teaching and support staff'],
                ['href'=>'staff/add_staff.php',          'icon'=>'fa-user-tie',          'color'=>'indigo', 'title'=>'Add Staff',          'desc'=>'Create a new staff profile'],
                ['href'=>'system_settings.php',          'icon'=>'fa-sliders-h',         'color'=>'orange', 'title'=>'System Settings',   'desc'=>'Configure semester, academic year, and school info'],
                ['href'=>'register.php',                 'icon'=>'fa-user-shield',       'color'=>'gray',   'title'=>'Register User',      'desc'=>'Add a new system user account'],
                ['href'=>'students/bulk_upload_students.php','icon'=>'fa-file-arrow-up', 'color'=>'teal',   'title'=>'Bulk Upload',        'desc'=>'Import students via CSV file'],
                ['href'=>'../../pages/finance/dashboard.php','icon'=>'fa-wallet',        'color'=>'emerald','title'=>'Go to Finance',      'desc'=>'View finance module'],
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
