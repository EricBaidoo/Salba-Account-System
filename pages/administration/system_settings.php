<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../login');
    exit;
}

$role = $_SESSION['role'] ?? '';
if ($role !== 'admin' && $role !== 'supervisor' && $role !== 'facilitator') {
    header('Location: ../../index.php');
    exit;
}

// Page data fetching is moved BEFORE the POST check if needed, 
// but actually most logic stays same.

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated_by = $_SESSION['username'] ?? 'Admin';
    
    // Check for potential post_max_size overflow (POST empty but Content-Length exists)
    if (empty($_POST) && empty($_FILES) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error_message = "The uploaded file is too large for the server configuration. Please try a smaller image.";
    } 
    
    // Process Main Settings Form
    if (isset($_POST['action']) && $_POST['action'] === 'save_settings') {
        $update_count = 0;

        // Update current semester
        if (isset($_POST['current_semester'])) {
            if (setSystemSetting($conn, 'current_semester', $_POST['current_semester'], $updated_by)) {
                $update_count++;
            }
        }
        
        // Update academic year
        if (isset($_POST['academic_year'])) {
            setSystemSetting($conn, 'academic_year', trim($_POST['academic_year']), $updated_by);
            $update_count++;
        }

        // Attendance & Preferences
        $prefs = ['attendance_early_limit', 'attendance_ontime_limit', 'academic_year_format'];
        foreach ($prefs as $p) {
            if (isset($_POST[$p])) {
                if (setSystemSetting($conn, $p, $_POST[$p], $updated_by)) $update_count++;
            }
        }
        
        // School Identity
        $fields = ['school_name', 'school_address', 'school_phone', 'school_email', 'semester_start_date', 'semester_end_date', 'attendance_lat', 'attendance_lng', 'attendance_radius', 'weeks_per_term'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                if (setSystemSetting($conn, $field, $_POST[$field], $updated_by)) $update_count++;
            }
        }

        // Logo Upload Handling 
        if (isset($_FILES['system_logo']) && $_FILES['system_logo']['name'] !== '') {
            $upload_error = $_FILES['system_logo']['error'];
            
            if ($upload_error === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['system_logo']['tmp_name'];
                $file_name = $_FILES['system_logo']['name'];
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($ext, $allowed)) {
                    $new_name = 'logo_' . time() . '.' . $ext;
                    $upload_path = '../../assets/img/' . $new_name;
                    $db_path = 'assets/img/' . $new_name;

                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $old_logo = getSystemSetting($conn, 'system_logo', '');
                        if (!empty($old_logo) && $old_logo !== 'assets/img/salba_logo.jpg') {
                            $old_file = '../../' . $old_logo;
                            if (file_exists($old_file)) @unlink($old_file);
                        }
                        setSystemSetting($conn, 'system_logo', $db_path, $updated_by);
                        $success_message .= "Logo updated. ";
                        $update_count++;
                    } else {
                        $error_message .= "Permission Error: Could not save file to assets/img/. ";
                    }
                } else {
                    $error_message .= "Unsupported format: $ext. ";
                }
            } else {
                $err_map = [1=>"Size exceeds limit", 2=>"Size exceeds limit", 3=>"Partial upload", 4=>"No file", 6=>"Temp folder missing", 7=>"Disk write failed"];
                $error_message .= "Upload Error: " . ($err_map[$upload_error] ?? "Code $upload_error") . ". ";
            }
        }

        if ($update_count > 0 && empty($error_message)) {
            $new_settings = getAllSettings($conn);
            log_activity($conn, 'System', "Updated system settings ($update_count fields modified).", $all_settings, $new_settings);
            $success_message = "System settings updated successfully. " . $success_message;
        } elseif ($update_count === 0 && empty($error_message)) {
            $error_message = "No changes were detected or saved.";
        }
    }
    
    // Semester Dictionary Operations
    if (isset($_POST['semester_action'])) {
        if ($_POST['semester_action'] === 'add_semester') {
            $s_name = trim($_POST['new_semester_name']);
            if ($s_name) {
                $stmt = $conn->prepare("INSERT IGNORE INTO academic_semester_dictionary (semester_name) VALUES (?)");
                $stmt->bind_param("s", $s_name);
                if ($stmt->execute()) {
                    log_activity($conn, 'System', "Added new semester '$s_name' to dictionary.");
                    $success_message .= "New semester '$s_name' added. ";
                }
            }
        }
        if ($_POST['semester_action'] === 'delete_semester') {
            $del_id = intval($_POST['delete_id']);
            $conn->query("DELETE FROM academic_semester_dictionary WHERE id = $del_id");
            $success_message .= "Semester removed. ";
        }
        if ($_POST['semester_action'] === 'rename_semester') {
            $s_id = intval($_POST['semester_id']);
            $old_name = trim($_POST['old_name']);
            $new_name = trim($_POST['new_name']);
            if ($s_id && $old_name && $new_name && $old_name !== $new_name) {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("UPDATE academic_semester_dictionary SET semester_name = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_name, $s_id);
                    $stmt->execute();
                    $tables = [
                        'assessment_configurations', 'attendance', 'budgets', 'expenses', 'grades', 
                        'lesson_plans', 'payments', 'semester_budgets', 'semester_invoices', 
                        'student_fees', 'student_semester_remarks', 'student_term_remarks', 'teacher_allocations'
                    ];
                    foreach($tables as $t) {
                        $stmt = $conn->prepare("UPDATE `$t` SET semester = ? WHERE semester = ?");
                        $stmt->bind_param("ss", $new_name, $old_name);
                        $stmt->execute();
                    }
                    if (getCurrentSemester($conn) === $old_name) setSystemSetting($conn, 'current_semester', $new_name, $updated_by);
                    $conn->commit();
                    log_activity($conn, 'System', "Renamed semester '$old_name' to '$new_name' across all modules.");
                    $success_message .= "Semester renamed to '$new_name'.";
                } catch (Exception $e) { $conn->rollback(); $error_message .= "Rename failed."; }
            }
        }
    }

    // Semester Duration
    if (isset($_POST['action']) && $_POST['action'] === 'save_semester_structure') {
        $weeks = intval($_POST['weeks_per_semester']);
        if ($weeks > 0 && $weeks < 53) {
            setSystemSetting($conn, 'weeks_per_term', $weeks, $updated_by);
            $success_message .= "Semester duration updated. ";
        }
    }

    // School Calendar logic
    if (isset($_POST['action']) && $_POST['action'] === 'add_calendar_event') {
        $c_date = $_POST['event_date']; $c_type = $_POST['event_type']; $c_desc = trim($_POST['description']);
        if ($c_date && $c_type) {
            $stmt = $conn->prepare("INSERT IGNORE INTO academic_calendar (event_date, event_type, description) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $c_date, $c_type, $c_desc);
            $stmt->execute();
            $success_message .= "Calendar event added. ";
        }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'delete_calendar_event') {
        $del_id = intval($_POST['delete_id']);
        $conn->query("DELETE FROM academic_calendar WHERE id = $del_id");
        $success_message .= "Calendar event removed. ";
    }

    // FINAL PRG REDIRECT
    if ($error_message) {
        redirect('system_settings', 'error', $error_message);
    } else {
        redirect('system_settings', 'success', $success_message ?: "Changes saved successfully.");
    }
}

// Data fetching
$all_settings = getAllSettings($conn);
$current_semester = getCurrentSemester($conn);
$academic_year = getAcademicYear($conn);
$available_semesters = getAvailableSemesters($conn);
$year_format = getSystemSetting($conn, 'academic_year_format', 'full');
$early_limit = getSystemSetting($conn, 'attendance_early_limit', '06:30');
$ontime_limit = getSystemSetting($conn, 'attendance_ontime_limit', '07:00');
$att_lat = getSystemSetting($conn, 'attendance_lat', '5.5786875');
$att_lng = getSystemSetting($conn, 'attendance_lng', '-0.2911875');
$att_radius = getSystemSetting($conn, 'attendance_radius', '300');
$weeks_per_semester = intval(getSystemSetting($conn, 'weeks_per_term', 12));

$semester_dictionary = [];
$dict_res = $conn->query("SELECT * FROM academic_semester_dictionary ORDER BY display_order ASC, id ASC");
if($dict_res) while($r = $dict_res->fetch_assoc()) $semester_dictionary[] = $r;

$calendar_events = [];
$cal_res = $conn->query("SELECT * FROM academic_calendar ORDER BY event_date ASC LIMIT 50");
if ($cal_res) while($r = $cal_res->fetch_assoc()) $calendar_events[] = $r;

// Year options
$ay_parts = explode('/', $academic_year);
$anchor_start_year = intval($ay_parts[0] ?? date('Y'));
if ($anchor_start_year <= 0) $anchor_start_year = (int)date('Y');
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
    <title>System Settings | Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">
    <?php include '../../includes/sidebar.php'; ?>
    <main class="admin-main-content lg:ml-72 min-h-screen bg-gray-50/50">
        <div class="bg-white border-b border-gray-100 px-8 py-5 sticky top-0 z-40 shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <a href="dashboard" class="text-gray-400 hover:text-blue-600 transition-colors flex items-center gap-2 text-[0.625rem] font-black uppercase tracking-widest">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
                </div>
                <h1 class="text-2xl font-black text-gray-900 flex items-center gap-3 tracking-tighter">
                    <i class="fas fa-sliders-h text-orange-500"></i> System <span class="text-orange-500">Settings</span>
                </h1>
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" form="mainSettingsForm" class="bg-black text-white px-8 py-3.5 rounded-xl font-black text-[0.6875rem] uppercase tracking-[0.2em] shadow-lg shadow-black/10 hover:bg-emerald-600 transition-all active:scale-[0.98] flex items-center gap-3">
                    <i class="fas fa-check-circle"></i> Save All Configuration
                </button>
            </div>
        </div>

        <div class="p-8 max-w-7xl mx-auto">
            <form id="mainSettingsForm" method="POST" action="system_settings" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_settings">
                
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <!-- Left Column: Primary Config -->
                    <div class="lg:col-span-8 space-y-8">
                        
                        <!-- Section: Institutional Identity -->
                        <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm overflow-hidden box-shadow">
                            <div class="px-8 py-6 border-b border-gray-50 flex items-center justify-between bg-gray-50/30">
                                <h5 class="font-black text-gray-900 flex items-center gap-3 text-xs uppercase tracking-[0.15em]">
                                    <i class="fas fa-school text-emerald-500 text-lg"></i> Institutional Identity
                                </h5>
                                <span class="text-[0.625rem] font-bold text-gray-400 uppercase tracking-widest bg-white px-3 py-1 rounded-full border border-gray-100">Profile</span>
                            </div>
                            <div class="p-8 space-y-8">
                                <div class="flex flex-col md:flex-row items-center gap-8 p-6 bg-emerald-50/30 rounded-3xl border border-emerald-100/50">
                                    <div class="relative group">
                                        <div class="w-24 h-24 bg-white rounded-2xl shadow-xl flex items-center justify-center p-3 border border-emerald-100 overflow-hidden transition-transform group-hover:scale-105">
                                            <img id="logoPreview" src="../../<?= getSystemLogo($conn) ?>" class="w-full h-full object-contain">
                                        </div>
                                        <label class="absolute -bottom-2 -right-2 w-8 h-8 bg-emerald-600 text-white rounded-full flex items-center justify-center shadow-lg cursor-pointer hover:bg-black transition-colors border-2 border-white">
                                            <i class="fas fa-camera text-xs"></i>
                                            <input type="file" name="system_logo" id="logoInput" class="hidden" accept="image/*" onchange="previewLogo(event)">
                                        </label>
                                    </div>
                                    <div class="flex-1 space-y-1 text-center md:text-left">
                                        <h4 class="text-sm font-black text-gray-900 uppercase tracking-widest">Institution Branding</h4>
                                        <p class="text-xs text-gray-500 font-medium">Click the camera icon to upload your official school logo.</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="md:col-span-2">
                                        <label class="block text-[0.625rem] font-black text-gray-400 uppercase tracking-widest mb-2 px-1">Institutional Name</label>
                                        <input type="text" name="school_name" value="<?= htmlspecialchars(getSystemSetting($conn, 'school_name', '')) ?>" class="w-full px-5 py-4 bg-gray-50 border border-transparent focus:border-emerald-500 focus:bg-white rounded-2xl font-bold text-gray-800 outline-none transition-all placeholder-gray-300 shadow-sm" placeholder="Salba Montessori School">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-[0.625rem] font-black text-gray-400 uppercase tracking-widest mb-2 px-1">Physical Address & Location</label>
                                        <textarea name="school_address" rows="3" placeholder="Enter the full campus address..." class="w-full px-5 py-4 bg-gray-50 border border-transparent focus:border-emerald-500 focus:bg-white rounded-2xl font-bold text-gray-800 outline-none transition-all resize-none shadow-sm"><?= htmlspecialchars(getSystemSetting($conn, 'school_address', '')) ?></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-[0.625rem] font-black text-gray-400 uppercase tracking-widest mb-2 px-1">Contact Phone</label>
                                        <input type="text" name="school_phone" value="<?= htmlspecialchars(getSystemSetting($conn, 'school_phone', '')) ?>" class="w-full px-5 py-4 bg-gray-50 border border-transparent focus:border-emerald-500 focus:bg-white rounded-2xl font-bold text-gray-800 outline-none transition-all shadow-sm" placeholder="059 000 0000">
                                    </div>
                                    <div>
                                        <label class="block text-[0.625rem] font-black text-gray-400 uppercase tracking-widest mb-2 px-1">Official Email</label>
                                        <input type="email" name="school_email" value="<?= htmlspecialchars(getSystemSetting($conn, 'school_email', '')) ?>" class="w-full px-5 py-4 bg-gray-50 border border-transparent focus:border-emerald-500 focus:bg-white rounded-2xl font-bold text-gray-800 outline-none transition-all shadow-sm" placeholder="admin@salba.edu.gh">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Academic Cycle -->
                        <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm overflow-hidden">
                            <div class="px-8 py-6 border-b border-gray-50 bg-gray-50/30 flex items-center justify-between">
                                <h5 class="font-black text-gray-900 flex items-center gap-3 text-xs uppercase tracking-[0.15em]">
                                    <i class="fas fa-graduation-cap text-blue-500 text-lg"></i> Academic Cycle & Terms
                                </h5>
                                <span class="text-[0.625rem] font-bold text-gray-400 uppercase tracking-widest bg-white px-3 py-1 rounded-full border border-gray-100">Calendar</span>
                            </div>
                            <div class="p-8">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                                    <div>
                                        <label class="block text-[0.625rem] font-black text-gray-400 uppercase tracking-widest mb-3">Active Semester</label>
                                        <select name="current_semester" class="w-full px-5 py-4 bg-blue-50/50 border border-transparent focus:border-blue-500 focus:bg-white rounded-2xl font-black text-blue-700 text-xs outline-none transition-all appearance-none cursor-pointer">
                                            <?php foreach ($available_semesters as $s): ?>
                                                <option value="<?= $s ?>" <?= $s === $current_semester ? 'selected' : '' ?>><?= $s ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[0.625rem] font-black text-gray-400 uppercase tracking-widest mb-3">Academic Year</label>
                                        <select name="academic_year" class="w-full px-5 py-4 bg-blue-50/50 border border-transparent focus:border-blue-500 focus:bg-white rounded-2xl font-black text-blue-700 text-xs outline-none transition-all appearance-none cursor-pointer">
                                            <?php foreach ($year_options as $opt): ?>
                                                <option value="<?= $opt['value'] ?>" <?= $opt['value'] === $academic_year ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[0.625rem] font-black text-gray-400 uppercase tracking-widest mb-3">Duration (Weeks)</label>
                                        <input type="number" name="weeks_per_term" value="<?= $weeks_per_semester ?>" class="w-full px-5 py-4 bg-gray-50 border border-transparent focus:border-blue-500 focus:bg-white rounded-2xl font-black text-gray-800 outline-none transition-all shadow-sm">
                                    </div>
                                    <div>
                                        <label class="block text-[0.625rem] font-black text-gray-400 uppercase tracking-widest mb-3">Year Format</label>
                                        <select name="academic_year_format" class="w-full px-5 py-4 bg-gray-50 border border-transparent focus:border-blue-500 focus:bg-white rounded-2xl font-black text-gray-800 text-xs outline-none transition-all appearance-none cursor-pointer">
                                            <option value="full"  <?= $year_format === 'full' ? 'selected' : '' ?>>Full (2024/2025)</option>
                                            <option value="short" <?= $year_format === 'short' ? 'selected' : '' ?>>Short (2024/25)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 bg-blue-50/30 rounded-3xl border border-blue-100/50">
                                    <div>
                                        <label class="block text-[0.625rem] font-black text-gray-400 uppercase tracking-widest mb-2">Cycle Start Date</label>
                                        <input type="date" name="semester_start_date" id="semester_start_date" value="<?= htmlspecialchars(getSystemSetting($conn, 'semester_start_date', '')) ?>" class="w-full px-5 py-4 bg-white border border-transparent focus:border-blue-500 rounded-2xl font-black text-gray-800 outline-none transition-all shadow-sm">
                                    </div>
                                    <div>
                                        <label class="block text-[0.625rem] font-black text-gray-400 uppercase tracking-widest mb-2">Cycle End Date</label>
                                        <input type="date" name="semester_end_date" id="semester_end_date" value="<?= htmlspecialchars(getSystemSetting($conn, 'semester_end_date', '')) ?>" class="w-full px-5 py-4 bg-white border border-transparent focus:border-blue-500 rounded-2xl font-black text-gray-800 outline-none transition-all shadow-sm">
                                    </div>
                                    <div id="date-warning" class="hidden md:col-span-2 mt-2 px-4 py-3 bg-white rounded-xl border border-orange-200">
                                        <p class="text-[0.625rem] font-bold text-orange-600 uppercase tracking-tight flex items-center gap-2">
                                            <i class="fas fa-info-circle text-orange-500"></i> Academic cycle changed. Please verify your start and end dates.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Secondary Config -->
                    <div class="lg:col-span-4 space-y-8">
                        
                        <!-- Section: Attendance Rules -->
                        <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm overflow-hidden">
                            <div class="px-8 py-6 border-b border-gray-50 bg-gray-50/30 flex items-center justify-between">
                                <h5 class="font-black text-gray-900 flex items-center gap-3 text-xs uppercase tracking-[0.15em]">
                                    <i class="fas fa-clock text-indigo-500"></i> Attendance Rules
                                </h5>
                            </div>
                            <div class="p-8 space-y-6">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[0.5625rem] font-black text-gray-400 uppercase tracking-widest mb-2">Early Limit</label>
                                        <input type="time" name="attendance_early_limit" value="<?= htmlspecialchars($early_limit) ?>" class="w-full px-4 py-3.5 bg-gray-50 border border-transparent focus:border-indigo-500 focus:bg-white rounded-xl font-black text-gray-800 text-xs outline-none transition-all shadow-inner">
                                    </div>
                                    <div>
                                        <label class="block text-[0.5625rem] font-black text-gray-400 uppercase tracking-widest mb-2">On-Time Limit</label>
                                        <input type="time" name="attendance_ontime_limit" value="<?= htmlspecialchars($ontime_limit) ?>" class="w-full px-4 py-3.5 bg-gray-50 border border-transparent focus:border-indigo-500 focus:bg-white rounded-xl font-black text-gray-800 text-xs outline-none transition-all shadow-inner">
                                    </div>
                                </div>
                                <div class="p-5 bg-indigo-50/50 rounded-2xl border border-indigo-100/50 space-y-4">
                                    <div class="flex items-center gap-3 pb-4 border-b border-indigo-100/30">
                                        <i class="fas fa-map-location-dot text-indigo-500 text-xl"></i>
                                        <h4 class="text-[0.625rem] font-black text-indigo-900 uppercase tracking-[0.15em]">Geofencing Hub</h4>
                                    </div>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-[0.5rem] font-black text-indigo-400 uppercase tracking-widest mb-1.5">Center Latitude</label>
                                            <input type="text" name="attendance_lat" value="<?= htmlspecialchars($att_lat) ?>" class="w-full px-3 py-2 bg-white border border-transparent focus:border-indigo-500 rounded-lg text-xs font-bold text-indigo-900 outline-none transition-all">
                                        </div>
                                        <div>
                                            <label class="block text-[0.5rem] font-black text-indigo-400 uppercase tracking-widest mb-1.5">Center Longitude</label>
                                            <input type="text" name="attendance_lng" value="<?= htmlspecialchars($att_lng) ?>" class="w-full px-3 py-2 bg-white border border-transparent focus:border-indigo-500 rounded-lg text-xs font-bold text-indigo-900 outline-none transition-all">
                                        </div>
                                        <div>
                                            <label class="block text-[0.5rem] font-black text-indigo-400 uppercase tracking-widest mb-1.5">Verification Radius (m)</label>
                                            <input type="number" name="attendance_radius" value="<?= htmlspecialchars($att_radius) ?>" class="w-full px-3 py-2 bg-white border border-transparent focus:border-indigo-500 rounded-lg text-xs font-bold text-indigo-900 outline-none transition-all">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Info Tip -->
                        <div class="bg-gradient-to-br from-gray-900 to-black rounded-[2.5rem] p-8 text-white relative overflow-hidden group">
                            <i class="fas fa-shield-halved absolute -right-4 -bottom-4 text-7xl text-white/5 group-hover:scale-110 transition-transform"></i>
                            <h4 class="text-lg font-black tracking-tighter mb-4 relative z-10">Data Integrity <span class="text-orange-500">Notice</span></h4>
                            <p class="text-xs text-gray-400 font-medium leading-relaxed relative z-10">
                                All changes made here are applied globally. Institutional identity, geofencing coordinates, and academic dates affect every module across the system. 
                            </p>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Bottom Sections: Administrative Utilities (Transactional) -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mt-12 pb-20">
                
                <!-- Semester Dictionary -->
                <div class="bg-gray-50/50 rounded-[2.5rem] border border-gray-100 p-8">
                    <div class="flex items-center justify-between mb-8">
                        <h2 class="text-xl font-black text-gray-900 flex items-center gap-3 tracking-tighter">
                            <i class="fas fa-book-bookmark text-emerald-500"></i> Semester Nomenclature
                        </h2>
                        <span class="text-[0.625rem] font-black text-emerald-600 bg-emerald-100 px-3 py-1 rounded-full uppercase tracking-widest">Registry</span>
                    </div>
                    
                    <form method="POST" class="flex items-center gap-4 p-4 bg-white rounded-2xl shadow-sm border border-gray-100 mb-8 mt-2">
                        <input type="hidden" name="semester_action" value="add_semester">
                        <input type="text" name="new_semester_name" placeholder="E.g. Summer School" required class="flex-1 bg-transparent border-none px-4 py-2 text-xs font-bold text-gray-900 outline-none">
                        <button type="submit" class="bg-emerald-600 text-white w-10 h-10 rounded-xl flex items-center justify-center hover:bg-black transition-all shadow-lg shadow-emerald-600/20"><i class="fas fa-plus"></i></button>
                    </form>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach($semester_dictionary as $sem): ?>
                            <div class="p-6 bg-white rounded-2xl border border-gray-100 shadow-sm relative group transition-all hover:border-emerald-200">
                                <form method="POST" class="flex flex-col gap-3">
                                    <input type="hidden" name="semester_action" value="rename_semester">
                                    <input type="hidden" name="semester_id" value="<?= $sem['id'] ?>">
                                    <input type="hidden" name="old_name" value="<?= htmlspecialchars($sem['semester_name']) ?>">
                                    <div class="flex items-center justify-between gap-2 border-b border-gray-50 pb-2">
                                        <input type="text" name="new_name" value="<?= htmlspecialchars($sem['semester_name']) ?>" class="flex-1 bg-transparent font-black text-sm text-gray-900 outline-none focus:text-emerald-600">
                                        <button type="submit" title="Rename" class="text-gray-300 hover:text-emerald-500 transition-colors"><i class="fas fa-floppy-disk"></i></button>
                                    </div>
                                </form>
                                <form method="POST" class="mt-3">
                                    <input type="hidden" name="semester_action" value="delete_semester">
                                    <input type="hidden" name="delete_id" value="<?= $sem['id'] ?>">
                                    <button type="submit" onclick="return confirm('Warning: Deleting a semester may break historical records. Proceed?')" class="text-[0.625rem] font-black uppercase text-rose-500 flex items-center gap-2 hover:text-rose-600 transition-colors">
                                        <i class="fas fa-trash-can text-[0.6875rem]"></i> Delete Entry
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Academic Calendar Management -->
                <div class="bg-gray-900 rounded-[2.5rem] border border-gray-800 p-8 text-white shadow-2xl">
                    <div class="flex items-center justify-between mb-8">
                        <h2 class="text-xl font-black flex items-center gap-3 tracking-tighter">
                            <i class="fas fa-calendar-star text-rose-500"></i> Calendar Closures
                        </h2>
                        <span class="text-[0.625rem] font-black text-rose-500 bg-rose-500/10 px-3 py-1 rounded-full uppercase tracking-widest leading-none">Management</span>
                    </div>

                    <form method="POST" class="space-y-4 mb-2 p-6 bg-white/5 rounded-3xl border border-white/5">
                        <input type="hidden" name="action" value="add_calendar_event">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="text-[0.5625rem] font-black text-gray-500 uppercase tracking-widest px-1">Closure Date</label>
                                <input type="date" name="event_date" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white outline-none focus:border-rose-500/50">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[0.5625rem] font-black text-gray-500 uppercase tracking-widest px-1">Classification</label>
                                <select name="event_type" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-xs font-black text-white outline-none focus:border-rose-500/50 appearance-none">
                                    <option value="holiday" class="bg-gray-900">🔔 Public Holiday</option>
                                    <option value="break" class="bg-gray-900">🏖️ Mid-Term Break</option>
                                </select>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[0.5625rem] font-black text-gray-500 uppercase tracking-widest px-1">Short Description</label>
                            <input type="text" name="description" placeholder="E.g. Independence Day Celebration" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white outline-none focus:border-rose-500/50">
                        </div>
                        <button type="submit" class="w-full bg-rose-600 hover:bg-white hover:text-rose-600 text-white py-4 rounded-xl font-black text-[0.6875rem] uppercase tracking-widest transition-all shadow-xl shadow-rose-600/10 mt-2">
                            Add Institutional Event
                        </button>
                    </form>

                    <div class="space-y-3 mt-8 max-h-[14rem] overflow-y-auto pr-2 custom-scrollbar">
                        <?php foreach($calendar_events as $ev): ?>
                            <div class="flex items-center justify-between p-4 bg-white/5 rounded-2xl border border-white/5 hover:bg-white/10 transition-colors group">
                                <div class="flex items-center gap-4">
                                    <div class="text-center bg-gray-900 border border-white/5 min-w-[3.5rem] py-2 rounded-xl">
                                        <p class="text-[0.5625rem] font-black text-rose-500 uppercase"><?= date('M', strtotime($ev['event_date'])) ?></p>
                                        <p class="text-sm font-black text-white"><?= date('d', strtotime($ev['event_date'])) ?></p>
                                    </div>
                                    <div class="space-y-0.5">
                                        <p class="text-[0.5rem] font-black text-gray-500 uppercase tracking-widest"><?= ucfirst($ev['event_type']) ?></p>
                                        <p class="text-xs font-bold text-gray-200"><?= htmlspecialchars($ev['description']) ?></p>
                                    </div>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="action" value="delete_calendar_event">
                                    <input type="hidden" name="delete_id" value="<?= $ev['id'] ?>">
                                    <button type="submit" class="w-8 h-8 rounded-lg bg-rose-600/10 text-rose-500 hover:bg-rose-500 hover:text-white transition-all flex items-center justify-center">
                                        <i class="fas fa-trash-can text-[0.6875rem]"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    function previewLogo(event) {
        const reader = new FileReader();
        reader.onload = function() {
            const output = document.getElementById('logoPreview');
            output.src = reader.result;
        }
        if (event.target.files[0]) {
            reader.readAsDataURL(event.target.files[0]);
        }
    }

    // Modern detection for academic cycle changes
    const cycleInputs = document.querySelectorAll('select[name="current_semester"], select[name="academic_year"]');
    const dateWarning = document.getElementById('date-warning');
    const dateFields = [document.getElementById('semester_start_date'), document.getElementById('semester_end_date')];

    cycleInputs.forEach(input => {
        input.addEventListener('change', () => {
            dateWarning.classList.remove('hidden');
            dateFields.forEach(f => {
                f.classList.remove('bg-gray-50', 'border-gray-100');
                f.classList.add('bg-orange-50', 'border-orange-200', 'ring-2', 'ring-orange-500');
            });
        });
    });
    </script>
</body>
</html>
