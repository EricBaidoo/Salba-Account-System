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
        $fields = ['school_name', 'school_address', 'school_phone', 'school_email', 'semester_start_date', 'semester_end_date', 'attendance_lat', 'attendance_lng', 'attendance_radius'];
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
                    $tables = ['assessment_configurations', 'attendance', 'budgets', 'expenses', 'grades', 'lesson_plans', 'payments', 'student_fees', 'student_semester_remarks', 'teacher_allocations'];
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
    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">
        <div class="bg-white border-b border-gray-100 px-8 py-6 sticky top-0 z-40 shadow-sm">
            <div class="flex items-center gap-3 mb-2">
                <a href="dashboard" class="text-gray-400 hover:text-blue-600 transition-colors flex items-center gap-2 text-xs font-bold uppercase tracking-widest">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
            <h1 class="text-3xl font-black text-gray-900 flex items-center gap-3 tracking-tighter">
                <i class="fas fa-sliders-h text-orange-500"></i> System Settings
            </h1>
        </div>

        <div class="p-8 max-w-6xl mx-auto">
            <!-- Global Flash Messages are now handled by top_nav.php -->

            <form method="POST" action="system_settings" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_settings">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                    <div class="xl:col-span-2 space-y-8">
                        <!-- Attendance Card -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-50 bg-gray-50/50">
                                <h5 class="font-black text-gray-800 flex items-center gap-2 text-xs uppercase tracking-widest">
                                    <i class="fas fa-clock text-indigo-500"></i> Attendance & Punctuality
                                </h5>
                            </div>
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Early Arrival Threshold</label>
                                    <input type="time" name="attendance_early_limit" value="<?= htmlspecialchars($early_limit) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl font-bold text-gray-700 focus:ring-2 focus:ring-indigo-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">On-Time Limit</label>
                                    <input type="time" name="attendance_ontime_limit" value="<?= htmlspecialchars($ontime_limit) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl font-bold text-gray-700 focus:ring-2 focus:ring-indigo-500 outline-none">
                                </div>
                            </div>
                        </div>

                        <!-- Geolocation Card -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-50 bg-gray-50/50">
                                <h5 class="font-black text-gray-800 flex items-center gap-2 text-xs uppercase tracking-widest">
                                    <i class="fas fa-map-location-dot text-rose-500"></i> Geolocation Hub (Attendance)
                                </h5>
                            </div>
                            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">School Latitude</label>
                                    <input type="text" name="attendance_lat" value="<?= htmlspecialchars($att_lat) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl font-bold text-gray-700 focus:ring-2 focus:ring-rose-500 outline-none" placeholder="5.5786875">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">School Longitude</label>
                                    <input type="text" name="attendance_lng" value="<?= htmlspecialchars($att_lng) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl font-bold text-gray-700 focus:ring-2 focus:ring-rose-500 outline-none" placeholder="-0.2911875">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Radius (Meters)</label>
                                    <input type="number" name="attendance_radius" value="<?= htmlspecialchars($att_radius) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl font-bold text-gray-700 focus:ring-2 focus:ring-rose-500 outline-none" placeholder="300">
                                </div>
                            </div>
                            <div class="px-6 py-3 bg-rose-50 border-t border-rose-100">
                                <p class="text-[9px] font-bold text-rose-700 uppercase leading-relaxed">
                                    <i class="fas fa-info-circle mr-1"></i> Staff must be within this radius of the coordinates to verify their presence.
                                </p>
                            </div>
                        </div>

                        <!-- Academic Card -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                <h5 class="font-black text-gray-800 flex items-center gap-2 text-xs uppercase tracking-widest">
                                    <i class="fas fa-graduation-cap text-blue-500"></i> Academic Cycle
                                </h5>
                            </div>
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Active Semester</label>
                                    <select name="current_semester" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl font-bold text-gray-700 focus:ring-2 focus:ring-blue-500 outline-none">
                                        <?php foreach ($available_semesters as $s): ?>
                                            <option value="<?= $s ?>" <?= $s === $current_semester ? 'selected' : '' ?>><?= $s ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Academic Year</label>
                                    <select name="academic_year" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl font-bold text-gray-700 focus:ring-2 focus:ring-blue-500 outline-none">
                                        <?php foreach ($year_options as $opt): ?>
                                            <option value="<?= $opt['value'] ?>" <?= $opt['value'] === $academic_year ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Info -->
                    <div class="xl:col-span-1 space-y-8">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden sticky top-32">
                            <div class="px-6 py-4 border-b border-gray-50 bg-gray-50/50">
                                <h5 class="font-black text-gray-800 flex items-center gap-2 text-xs uppercase tracking-widest">
                                    <i class="fas fa-school text-emerald-500"></i> School Identity
                                </h5>
                            </div>
                            <div class="p-6 space-y-6">
                                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-2xl border border-gray-100">
                                    <div class="w-16 h-16 bg-white rounded-xl shadow-inner flex items-center justify-center p-2 border border-gray-100 overflow-hidden">
                                        <img id="logoPreview" src="../../<?= getSystemLogo($conn) ?>" class="w-full h-full object-contain">
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1">Active Logo</p>
                                        <label class="cursor-pointer group">
                                            <span class="text-[10px] font-black text-emerald-600 hover:text-black transition-colors uppercase tracking-widest bg-emerald-100 px-3 py-1.5 rounded-lg">Change Logo</span>
                                            <input type="file" name="system_logo" id="logoInput" class="hidden" accept="image/*" onchange="previewLogo(event)">
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">School Name</label>
                                    <input type="text" name="school_name" value="<?= htmlspecialchars(getSystemSetting($conn, 'school_name', '')) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl font-bold text-gray-700 focus:ring-2 focus:ring-emerald-500 outline-none">
                                </div>
                                <button type="submit" class="w-full bg-black text-white py-4 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] shadow-lg hover:bg-emerald-600 transition-all active:scale-[0.98]">
                                    Commit All Changes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Bottom Sections -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mt-12">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
                    <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-3 tracking-tighter">
                        <i class="fas fa-calendar-alt text-indigo-500"></i> Semester Duration
                    </h2>
                    <form method="POST" class="flex items-center gap-4">
                        <input type="hidden" name="action" value="save_semester_structure">
                        <input type="number" name="weeks_per_semester" value="<?= $weeks_per_semester ?>" class="w-24 px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl font-black text-xl text-center text-indigo-600">
                        <div class="flex-1">
                            <p class="text-[10px] font-bold text-gray-500 mb-2 uppercase tracking-widest leading-relaxed">Standard Weeks Per Semester</p>
                            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-black text-[10px] uppercase tracking-widest hover:bg-black transition-all">Update</button>
                        </div>
                    </form>
                </div>

                <div class="bg-gray-900 rounded-2xl border border-gray-800 shadow-xl p-8 text-white">
                    <h2 class="text-xl font-black mb-6 flex items-center gap-3 tracking-tighter">
                        <i class="fas fa-calendar-check text-rose-500"></i> Calendar Closures
                    </h2>
                    <form method="POST" class="space-y-4 mb-8">
                        <input type="hidden" name="action" value="add_calendar_event">
                        <div class="grid grid-cols-2 gap-4">
                            <input type="date" name="event_date" required class="bg-gray-800 border-none rounded-xl px-4 py-3 text-xs font-bold text-white outline-none">
                            <select name="event_type" class="bg-gray-800 border-none rounded-xl px-4 py-3 text-xs font-bold text-white outline-none">
                                <option value="holiday">Holiday</option>
                                <option value="break">Break</option>
                            </select>
                        </div>
                        <input type="text" name="description" placeholder="Description..." class="w-full bg-gray-800 border-none rounded-xl px-4 py-3 text-xs font-bold text-white outline-none">
                        <button type="submit" class="w-full bg-rose-600 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-white hover:text-rose-600 transition-all">Add Event</button>
                    </form>
                    <div class="space-y-4">
                        <?php foreach($calendar_events as $ev): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-2xl border border-gray-800">
                                <div class="flex items-center gap-4">
                                    <div class="text-center bg-gray-800 px-3 py-1 rounded-lg border border-gray-700">
                                        <p class="text-[8px] font-black text-rose-500 uppercase"><?= date('M', strtotime($ev['event_date'])) ?></p>
                                        <p class="text-sm font-black"><?= date('d', strtotime($ev['event_date'])) ?></p>
                                    </div>
                                    <p class="text-xs font-bold"><?= htmlspecialchars($ev['description']) ?></p>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="action" value="delete_calendar_event">
                                    <input type="hidden" name="delete_id" value="<?= $ev['id'] ?>">
                                    <button type="submit" class="text-gray-500 hover:text-rose-500"><i class="fas fa-trash"></i></button>
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
    </script>
</body>
</html>
