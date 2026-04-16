<?php
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

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated_by = $_SESSION['username'] ?? 'Admin';
    
    // Update current semester
    if (isset($_POST['current_semester'])) {
        if (setSystemSetting($conn, 'current_semester', $_POST['current_semester'], $updated_by)) {
            $success_message .= 'Current semester updated. ';
        } else {
            $error_message .= 'Failed to update semester. ';
        }
    }
    
    // Semester Dictionary Operations
    if (isset($_POST['semester_action'])) {
        if ($_POST['semester_action'] === 'add_semester') {
            $s_name = trim($_POST['new_semester_name']);
            if ($s_name) {
                $stmt = $conn->prepare("INSERT IGNORE INTO academic_semester_dictionary (semester_name) VALUES (?)");
                $stmt->bind_param("s", $s_name);
                $stmt->execute();
                $success_message .= "New semester '$s_name' added to dictionary. ";
            }
        }
        if ($_POST['semester_action'] === 'delete_semester') {
            $del_id = intval($_POST['delete_id']);
            $conn->query("DELETE FROM academic_semester_dictionary WHERE id = $del_id");
            $success_message .= "Semester removed from system. ";
        }
        if ($_POST['semester_action'] === 'rename_semester') {
            $s_id = intval($_POST['semester_id']);
            $old_name = trim($_POST['old_name']);
            $new_name = trim($_POST['new_name']);
            
            if ($s_id && $old_name && $new_name && $old_name !== $new_name) {
                $conn->begin_transaction();
                try {
                    // 1. Update Dictionary
                    $stmt = $conn->prepare("UPDATE academic_semester_dictionary SET semester_name = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_name, $s_id);
                    $stmt->execute();
                    
                    // 2. Cascade to all tables using semester name
                    $tables = [
                        'assessment_configurations', 'attendance', 'budgets', 'expenses', 
                        'grades', 'lesson_plans', 'payments', 'student_fees', 
                        'student_semester_remarks', 'teacher_allocations', 
                        'semester_budgets', 'semester_invoices'
                    ];
                    
                    foreach($tables as $t) {
                        $stmt = $conn->prepare("UPDATE `$t` SET semester = ? WHERE semester = ?");
                        $stmt->bind_param("ss", $new_name, $old_name);
                        $stmt->execute();
                    }
                    
                    // 3. Update Global Setting if active
                    if (getCurrentSemester($conn) === $old_name) {
                        setSystemSetting($conn, 'current_semester', $new_name, $updated_by);
                    }
                    
                    $conn->commit();
                    $success_message .= "Semester '$old_name' renamed to '$new_name' across all records.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message .= "Renaming failed: " . $e->getMessage();
                }
            }
        }
    }
    
    // Update academic year
    if (isset($_POST['academic_year'])) {
        $year_value = trim($_POST['academic_year']);
        if (preg_match('/^(\d{4})\/(\d{2,4})$/', $year_value, $m)) {
            $startY = (int)$m[1];
            $endPart = $m[2];
            if (strlen($endPart) === 2) {
                $century = substr((string)$startY, 0, 2);
                $endY = (int)($century . $endPart);
            } else {
                $endY = (int)$endPart;
            }
            $year_value = $startY . '/' . $endY;
        }
        if (setSystemSetting($conn, 'academic_year', $year_value, $updated_by)) {
            $success_message .= 'Academic year updated. ';
        } else {
            $error_message .= 'Failed to update year. ';
        }
    }

    // Preferences
    $prefs = [
        'academic_year_format', 'academic_year_start_month', 'academic_year_start_day',
        'attendance_early_limit', 'attendance_ontime_limit'
    ];
    foreach ($prefs as $p) {
        if (isset($_POST[$p])) {
            setSystemSetting($conn, $p, $_POST[$p], $updated_by);
        }
    }
    
    // School Identity
    $fields = [
        'school_name', 'school_address', 'school_phone', 'school_email',
        'semester_start_date', 'semester_end_date', 'next_semester_begins'
    ];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            setSystemSetting($conn, $field, $_POST[$field], $updated_by);
        }
    }
    
    if ($success_message) {
        $success_message = rtrim($success_message);
    }
}

// Get current settings
$all_settings = getAllSettings($conn);
$current_semester = getCurrentSemester($conn);
$academic_year = getAcademicYear($conn);
$available_semesters = getAvailableSemesters($conn);
$year_format = getSystemSetting($conn, 'academic_year_format', 'full');

// Attendance Config Defaults
$early_limit = getSystemSetting($conn, 'attendance_early_limit', '06:30');
$ontime_limit = getSystemSetting($conn, 'attendance_ontime_limit', '07:00');

// Semester Dictionary
$semester_dictionary = [];
$dict_res = $conn->query("SELECT * FROM academic_semester_dictionary ORDER BY display_order ASC, id ASC");
if($dict_res) {
    while($r = $dict_res->fetch_assoc()) $semester_dictionary[] = $r;
}

// Year options
$ay_parts = explode('/', $academic_year);
$anchor_start_year = intval($ay_parts[0] ?? date('Y'));
if ($anchor_start_year <= 0) { $anchor_start_year = (int)date('Y'); }
$year_options = [];
for ($i = -2; $i <= 5; $i++) {
    $y1 = $anchor_start_year + $i; $y2 = $y1 + 1;
    $val = $y1 . '/' . $y2;
    $label = ($year_format === 'short') ? ($y1 . '/' . substr((string)$y2, -2)) : $val;
    $year_options[] = ['value' => $val, 'label' => $label];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/sidebar_admin.php'; ?>

    <main class="ml-72 min-h-screen">
        <div class="bg-white border-b border-gray-100 px-8 py-6 sticky top-0 z-40">
            <div class="flex items-center gap-3 mb-4">
                <a href="dashboard.php" class="text-gray-400 hover:text-blue-600 transition-colors flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-sliders-h text-orange-500"></i> System Settings
                </h1>
                <p class="text-gray-500 mt-2 text-sm">Configure global parameters, active semesters, and school identity.</p>
            </div>
        </div>

        <div class="p-8 max-w-6xl">
            <?php if ($success_message): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg flex items-center gap-3 mb-6 shadow-sm">
                    <i class="fas fa-check-circle text-emerald-500"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">
                    <div class="xl:col-span-2 space-y-6">
                        <!-- Attendance Configuration Card -->
                        <div class="bg-white rounded-xl border border-indigo-100 shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-indigo-50 bg-indigo-50/50">
                                <h5 class="font-bold text-indigo-900 flex items-center gap-2">
                                    <i class="fas fa-clock-rotate-left"></i> Attendance & Punctuality
                                </h5>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div>
                                        <label for="attendance_early_limit" class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Early Arrival Threshold</label>
                                        <div class="relative">
                                            <input type="time" id="attendance_early_limit" name="attendance_early_limit" 
                                                   value="<?= htmlspecialchars($early_limit) ?>"
                                                   class="w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-bold text-slate-700">
                                            <div class="mt-2 text-[10px] text-slate-400 font-medium">Arrivals before this time are marked "Early".</div>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="attendance_ontime_limit" class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">On-Time Arrival Limit</label>
                                        <div class="relative">
                                            <input type="time" id="attendance_ontime_limit" name="attendance_ontime_limit" 
                                                   value="<?= htmlspecialchars($ontime_limit) ?>"
                                                   class="w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-bold text-slate-700">
                                            <div class="mt-2 text-[10px] text-slate-400 font-medium italic">Arrivals after this time are marked "Late".</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Academic Configuration -->
                        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                    <i class="fas fa-calendar-alt text-blue-500"></i> Academic Configuration
                                </h5>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div>
                                        <label for="current_semester" class="block text-sm font-semibold text-gray-700 mb-1">Current Active Semester</label>
                                        <select id="current_semester" name="current_semester" required
                                                class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition-colors text-sm">
                                            <?php foreach ($available_semesters as $semester): ?>
                                                <option value="<?php echo htmlspecialchars($semester); ?>" <?php echo $semester === $current_semester ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($semester); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="academic_year" class="block text-sm font-semibold text-gray-700 mb-1">Academic Year</label>
                                        <select id="academic_year" name="academic_year" required
                                                class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition-colors text-sm">
                                            <?php foreach ($year_options as $opt): ?>
                                                <option value="<?php echo htmlspecialchars($opt['value']); ?>" <?php echo ($opt['value'] === $academic_year) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($opt['label']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-span-full md:col-span-1">
                                        <label for="semester_start_date" class="block text-sm font-semibold text-gray-700 mb-1">Semester Start Date</label>
                                        <input type="date" id="semester_start_date" name="semester_start_date" value="<?= htmlspecialchars(getSystemSetting($conn, 'semester_start_date', '')) ?>"
                                               class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 bg-gray-50 text-sm">
                                    </div>
                                    <div class="col-span-full md:col-span-1">
                                        <label for="semester_end_date" class="block text-sm font-semibold text-gray-700 mb-1">Semester End Date</label>
                                        <input type="date" id="semester_end_date" name="semester_end_date" value="<?= htmlspecialchars(getSystemSetting($conn, 'semester_end_date', '')) ?>"
                                               class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 bg-gray-50 text-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- School Identity -->
                    <div class="xl:col-span-1">
                        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden sticky top-32">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                    <i class="fas fa-school text-emerald-500"></i> School Identity
                                </h5>
                            </div>
                            <div class="p-6 space-y-4">
                                <div>
                                    <label for="school_name" class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1">School Name</label>
                                    <input type="text" id="school_name" name="school_name" value="<?= htmlspecialchars(getSystemSetting($conn, 'school_name', '')) ?>"
                                           class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:ring-2 focus:ring-emerald-500 outline-none">
                                </div>
                                <hr class="border-gray-100">
                                <button type="submit" class="w-full bg-emerald-600 text-white font-black text-[10px] uppercase tracking-widest px-4 py-4 rounded-xl shadow-lg hover:bg-black transition-all">
                                    <i class="fas fa-save mr-2"></i> Commit All Changes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
鼓鼓鼓鼓
鼓鼓鼓
鼓鼓
鼓
