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

// Safe Migration: Ensure attendance table has remarks column
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'remarks'")->fetch_row()[0];
if (!$exists) {
    $conn->query("ALTER TABLE attendance ADD COLUMN remarks TEXT NULL AFTER status");
}
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
    $res = $conn->query("SELECT DISTINCT class_name FROM teacher_allocations WHERE teacher_id = $uid AND year = '$current_year'");
    while($r = $res->fetch_assoc()) $allocated_classes[] = $r['class_name'];
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
        .radio-btn { display: none; }
        .radio-label {
            cursor: pointer; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; text-align: center; border: 1px solid #e5e7eb; transition: all 0.2s;
        }
        .radio-btn:checked + .radio-label.present { background-color: #ecfdf5; color: #059669; border-color: #34d399; }
        .radio-btn:checked + .radio-label.late { background-color: #fffbeb; color: #d97706; border-color: #fbbf24; }
        .radio-btn:checked + .radio-label.absent { background-color: #fef2f2; color: #dc2626; border-color: #f87171; }
        .radio-btn:checked + .radio-label.excused { background-color: #f3f4f6; color: #4b5563; border-color: #9ca3af; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/top_nav.php'; ?>

    <main class=" min-h-screen relative">
        <div class="bg-white border-b border-gray-100 px-8 py-6 sticky top-0 z-30 shadow-sm flex justify-between items-center bg-pattern">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-clipboard-user text-blue-500"></i> My Class Register
                </h1>
                <p class="text-gray-500 mt-2 text-sm">
                    Mark daily attendance strictly for your assigned classes.
                </p>
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
                        
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 text-gray-500 border-b border-gray-100 text-xs uppercase font-bold tracking-wider">
                                    <tr>
                                        <th class="px-6 py-4">Student Profile</th>
                                        <th class="px-6 py-4 text-center">Status Selection</th>
                                        <th class="px-6 py-4">Contextual Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <?php foreach($students as $idx => $s): ?>
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs">
                                                        <?= substr(htmlspecialchars($s['first_name']), 0, 1) . substr(htmlspecialchars($s['last_name']), 0, 1) ?>
                                                    </div>
                                                    <span class="font-bold text-gray-900"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex justify-center items-center gap-2">
                                                    <?php $stat = $s['status'] ?? 'present'; // Default present ?>
                                                    
                                                    <input type="radio" class="radio-btn" name="attendance[<?= $s['id'] ?>]" value="present" id="p_<?= $s['id'] ?>" <?= $stat==='present'?'checked':'' ?>>
                                                    <label class="radio-label present w-20" for="p_<?= $s['id'] ?>">Present</label>
                                                    
                                                    <input type="radio" class="radio-btn" name="attendance[<?= $s['id'] ?>]" value="late" id="l_<?= $s['id'] ?>" <?= $stat==='late'?'checked':'' ?>>
                                                    <label class="radio-label late w-20" for="l_<?= $s['id'] ?>">Late</label>
                                                    
                                                    <input type="radio" class="radio-btn" name="attendance[<?= $s['id'] ?>]" value="absent" id="a_<?= $s['id'] ?>" <?= $stat==='absent'?'checked':'' ?>>
                                                    <label class="radio-label absent w-20" for="a_<?= $s['id'] ?>">Absent</label>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <input type="text" name="remarks[<?= $s['id'] ?>]" value="<?= htmlspecialchars($s['remarks'] ?? '') ?>" placeholder="Optional note..." class="w-full bg-transparent border-b border-gray-200 focus:border-blue-500 focus:outline-none text-sm py-1 text-gray-600">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="flex justify-between items-center bg-blue-50 p-6 rounded-xl border border-blue-100">
                            <div class="text-blue-800 text-sm">
                                <i class="fas fa-info-circle mr-2"></i> All students strictly bounded to <strong><?= htmlspecialchars($selected_class) ?></strong>.
                            </div>
                            <button type="submit" class="bg-blue-600 text-white font-bold py-3 px-8 rounded-lg shadow-sm hover:bg-blue-700 transition flex items-center gap-2">
                                <i class="fas fa-save"></i> Submit Class Register
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>

