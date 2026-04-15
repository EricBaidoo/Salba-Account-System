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
$current_term = getCurrentTerm($conn);
$current_year = getAcademicYear($conn);

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

// Process Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
    $class_to_mark = $_POST['class_name'];
    $date_to_mark = $_POST['attendance_date'];
    
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
                $conn->query("UPDATE attendance SET status = '$stat', remarks = '$rem' WHERE student_id = $sid AND attendance_date = '$date_to_mark'");
            } else {
                // Insert
                $conn->query("INSERT INTO attendance (student_id, attendance_date, status, remarks, term, academic_year) VALUES ($sid, '$date_to_mark', '$stat', '$rem', '$current_term', '$current_year')");
            }
            $count++;
        }
        $success = "Successfully saved attendance for $count students.";
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
        <div class="glass-header px-10 py-8 sticky top-0 z-40 bg-white/80">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                <div>
                    <div class="flex items-center gap-2 text-indigo-500 font-bold text-xs uppercase tracking-widest mb-2">
                        <i class="fas fa-calendar-check text-[10px]"></i> Academic Operations
                    </div>
                    <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">
                        Class <span class="text-indigo-600">Attendance</span>
                    </h1>
                </div>
                <div class="flex gap-3">
                     <button type="button" onclick="markAll('present')" class="bg-emerald-50 text-emerald-700 px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-emerald-100 transition flex items-center gap-2 border border-emerald-100">
                        <i class="fas fa-check-double text-xs"></i> Mark All Present
                    </button>
                    <a href="dashboard.php" class="bg-gray-50 text-gray-500 px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-gray-100 transition flex items-center gap-2 border border-gray-100">
                        <i class="fas fa-arrow-left text-xs"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <div class="max-w-6xl mx-auto p-8">
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
                    <p class="text-gray-500 max-w-md mx-auto">You have not been assigned to any classes for the <?= $current_term ?> <?= $current_year ?> term. Please contact the Academic Supervisor.</p>
                </div>
            <?php else: ?>
                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8 flex items-center gap-4">
                    <form method="GET" class="flex items-center gap-4 w-full">
                        <div class="flex-1">
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Target Class</label>
                            <select name="class" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 font-medium" onchange="this.form.submit()">
                                <?php foreach($allocated_classes as $cl): ?>
                                    <option value="<?= htmlspecialchars($cl) ?>" <?= $selected_class === $cl ? 'selected' : '' ?>><?= htmlspecialchars($cl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Date</label>
                            <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 font-medium" onchange="this.form.submit()">
                        </div>
                        <div class="pt-5">
                            <button type="submit" class="bg-gray-800 text-white px-6 py-2 rounded-lg font-bold hover:bg-gray-900 transition">Filter</button>
                        </div>
                    </form>
                </div>

                <!-- Roll Call Form -->
                <?php if($selected_class): ?>
                    <form method="POST">
                        <input type="hidden" name="class_name" value="<?= htmlspecialchars($selected_class) ?>">
                        <input type="hidden" name="attendance_date" value="<?= htmlspecialchars($selected_date) ?>">
                        
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50/50 text-gray-400 border-b border-gray-100 text-[10px] uppercase font-black tracking-widest leading-none">
                                    <tr>
                                        <th class="px-8 py-5">Student Identity & Status</th>
                                        <th class="px-8 py-5 text-center">Attendance Verification</th>
                                        <th class="px-8 py-5">Remarks & Observations</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50/50">
                                    <?php foreach($students as $idx => $s): 
                                        $stat = $s['status'] ?? 'present';
                                    ?>
                                        <tr class="hover:bg-gray-50/30 transition-colors group">
                                            <td class="px-8 py-6">
                                                <div class="flex items-center gap-4">
                                                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm shadow-sm border border-indigo-100/50">
                                                        <?= substr(htmlspecialchars($s['first_name']), 0, 1) . substr(htmlspecialchars($s['last_name']), 0, 1) ?>
                                                    </div>
                                                    <div>
                                                        <div class="font-bold text-gray-900 flex items-center gap-2">
                                                            <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>
                                                            <?php if($s['status']): ?>
                                                                <i class="fas fa-check-circle text-[10px] text-emerald-500"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">Student ID: #<?= str_pad($s['id'], 4, '0', STR_PAD_LEFT) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6">
                                                <div class="flex justify-center items-center gap-3">
                                                    <input type="radio" class="radio-btn t-radio" name="attendance[<?= $s['id'] ?>]" value="present" id="p_<?= $s['id'] ?>" <?= $stat==='present'?'checked':'' ?>>
                                                    <label class="status-pill present" for="p_<?= $s['id'] ?>">Present</label>
                                                    
                                                    <input type="radio" class="radio-btn t-radio" name="attendance[<?= $s['id'] ?>]" value="late" id="l_<?= $s['id'] ?>" <?= $stat==='late'?'checked':'' ?>>
                                                    <label class="status-pill late" for="l_<?= $s['id'] ?>">Late</label>
                                                    
                                                    <input type="radio" class="radio-btn t-radio" name="attendance[<?= $s['id'] ?>]" value="absent" id="a_<?= $s['id'] ?>" <?= $stat==='absent'?'checked':'' ?>>
                                                    <label class="status-pill absent" for="a_<?= $s['id'] ?>">Absent</label>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6">
                                                <div class="relative">
                                                    <i class="far fa-comment-dots absolute left-0 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                                                    <input type="text" name="remarks[<?= $s['id'] ?>]" value="<?= htmlspecialchars($s['remarks'] ?? '') ?>" placeholder="Add note..." class="w-full pl-6 bg-transparent border-b border-gray-100 focus:border-indigo-400 focus:outline-none text-sm py-1.5 text-gray-500 transition-colors">
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="flex justify-between items-center bg-gray-50 p-8 rounded-2xl border border-gray-100">
                            <div class="flex items-center gap-3 text-gray-500 font-medium">
                                <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-sm border border-gray-100">
                                    <i class="fas fa-lock text-indigo-400"></i>
                                </div>
                                <span class="text-xs max-w-[200px]">Register strictly bounded to <strong class="text-gray-900"><?= htmlspecialchars($selected_class) ?></strong> for <strong class="text-gray-900"><?= $selected_date ?></strong>.</span>
                            </div>
                            <button type="submit" class="bg-gray-900 text-white font-extrabold py-4 px-10 rounded-xl shadow-lg hover:bg-black hover:scale-[1.02] active:scale-95 transition-all flex items-center gap-3 text-sm">
                                <i class="fas fa-check-double"></i> Submit Class Register
                            </button>
                        </div>
                    </form>
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
    </script>
</body>
</html>
