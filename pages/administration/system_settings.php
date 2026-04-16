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
    
    // Update academic year (always stored in canonical full format YYYY/YYYY)
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

    // Update academic year format preference (full or short)
    if (isset($_POST['academic_year_format'])) {
        $fmt = in_array($_POST['academic_year_format'], ['full','short'], true) ? $_POST['academic_year_format'] : 'full';
        setSystemSetting($conn, 'academic_year_format', $fmt, $updated_by);
    }

    // Update academic year start month/day used for reporting windows
    if (isset($_POST['academic_year_start_month'])) {
        $m = max(1, min(12, intval($_POST['academic_year_start_month'])));
        setSystemSetting($conn, 'academic_year_start_month', sprintf('%02d', $m), $updated_by);
    }
    if (isset($_POST['academic_year_start_day'])) {
        $d = max(1, min(31, intval($_POST['academic_year_start_day'])));
        setSystemSetting($conn, 'academic_year_start_day', sprintf('%02d', $d), $updated_by);
    }


    
    // Update school information
    $school_fields = ['school_name', 'school_address', 'school_phone', 'school_email'];
    foreach ($school_fields as $field) {
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
$start_month = getSystemSetting($conn, 'academic_year_start_month', '09');
$start_day = getSystemSetting($conn, 'academic_year_start_day', '01');

// Fetch all semesters for dictionary management
$semester_dictionary = [];
$dict_res = $conn->query("SELECT * FROM academic_semester_dictionary ORDER BY display_order ASC, id ASC");
if($dict_res) {
    while($r = $dict_res->fetch_assoc()) $semester_dictionary[] = $r;
}

// Build year options centered around current academic year start
$ay_parts = explode('/', $academic_year);
$anchor_start_year = intval($ay_parts[0] ?? date('Y'));
if ($anchor_start_year <= 0) { $anchor_start_year = (int)date('Y'); }
$year_options = [];
for ($i = -2; $i <= 5; $i++) {
    $y1 = $anchor_start_year + $i;
    $y2 = $y1 + 1;
    $val = $y1 . '/' . $y2; // canonical stored value
    $label = ($year_format === 'short')
        ? ($y1 . '/' . substr((string)$y2, -2))
        : $val;
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
        <!-- Header Section -->
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
                <p class="text-gray-500 mt-2 text-sm">
                    Configure global parameters, active terms, and school identity.
                </p>
            </div>
        </div>

        <div class="p-8 max-w-6xl">

            <?php if ($success_message): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg flex items-center gap-3 mb-6 shadow-sm">
                    <i class="fas fa-check-circle text-emerald-500"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-3 mb-6 shadow-sm">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">
                    <!-- Academic Configuration -->
                    <div class="xl:col-span-2 space-y-6">
                        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                    <i class="fas fa-calendar-alt text-blue-500"></i> Academic Configuration
                                </h5>
                            </div>
                            <div class="p-6">
                                <div class="bg-blue-50 border border-blue-100 text-blue-800 text-sm p-4 rounded-lg flex items-start gap-3 mb-6">
                                    <i class="fas fa-info-circle mt-0.5 text-blue-500"></i>
                                    <div>
                                        <strong>Heads up:</strong> Changing the <strong>Current Active Semester</strong> updates it globally for all users. Invoices, grades, and attendance forms will immediately switch context.
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div>
                                        <label for="current_semester" class="block text-sm font-semibold text-gray-700 mb-1">Current Active Semester</label>
                                        <select id="current_semester" name="current_semester" required
                                                class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition-colors cursor-pointer appearance-none text-sm">
                                            <?php foreach ($available_semesters as $semester): ?>
                                                <option value="<?php echo htmlspecialchars($semester); ?>" 
                                                        <?php echo $semester === $current_semester ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($semester); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="academic_year" class="block text-sm font-semibold text-gray-700 mb-1">Academic Year</label>
                                        <select id="academic_year" name="academic_year" required
                                                class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition-colors cursor-pointer appearance-none text-sm">
                                            <?php foreach ($year_options as $opt): ?>
                                                <option value="<?php echo htmlspecialchars($opt['value']); ?>" <?php echo ($opt['value'] === $academic_year) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($opt['label']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <hr class="border-gray-100 my-6">

                                <h6 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Display & Preferences</h6>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Year Format Preference</label>
                                        <select name="academic_year_format" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="full" <?php echo $year_format==='full'?'selected':''; ?>>Full (2025/2026)</option>
                                            <option value="short" <?php echo $year_format==='short'?'selected':''; ?>>Short (2025/26)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Start Month</label>
                                        <input type="number" min="1" max="12" name="academic_year_start_month" 
                                               value="<?php echo htmlspecialchars($start_month); ?>"
                                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Start Day</label>
                                        <input type="number" min="1" max="31" name="academic_year_start_day" 
                                               value="<?php echo htmlspecialchars($start_day); ?>"
                                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- System Parameters -->
                        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden" id="user-management">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                    <i class="fas fa-sliders text-indigo-500"></i> Semester & User Management
                                </h5>
                            </div>
                            <div class="p-6">
                                <!-- Semester Dictionary -->
                                <div class="mb-8">
                                    <h6 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Academic Semester Dictionary</h6>
                                    <div class="space-y-3 mb-6">
                                        <?php if(empty($semester_dictionary)): ?>
                                            <p class="text-xs text-gray-500 italic">No semesters defined. System will use defaults.</p>
                                        <?php else: ?>
                                            <?php foreach($semester_dictionary as $sem): ?>
                                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-100 group">
                                                    <div class="flex items-center gap-3 flex-1">
                                                        <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-bold shrink-0">
                                                            <?php echo $sem['display_order'] ?: $sem['id']; ?>
                                                        </div>
                                                        
                                                        <!-- Display Mode -->
                                                        <div id="disp_sem_<?php echo $sem['id']; ?>" class="flex-1 flex items-center justify-between">
                                                            <span class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($sem['semester_name']); ?></span>
                                                            <div class="flex items-center gap-2">
                                                                <button type="button" onclick="toggleEdit(<?php echo $sem['id']; ?>)" class="text-gray-400 hover:text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity p-1">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <form method="POST" onsubmit="return confirm('Remove this semester from system dictionary?')">
                                                                    <input type="hidden" name="semester_action" value="delete_semester">
                                                                    <input type="hidden" name="delete_id" value="<?php echo $sem['id']; ?>">
                                                                    <button type="submit" class="text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity p-1">
                                                                        <i class="fas fa-trash-alt"></i>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Edit Mode -->
                                                        <form id="edit_sem_<?php echo $sem['id']; ?>" method="POST" class="hidden flex-1 flex gap-2 items-center">
                                                            <input type="hidden" name="semester_action" value="rename_semester">
                                                            <input type="hidden" name="semester_id" value="<?php echo $sem['id']; ?>">
                                                            <input type="hidden" name="old_name" value="<?php echo htmlspecialchars($sem['semester_name']); ?>">
                                                            <input type="text" name="new_name" value="<?php echo htmlspecialchars($sem['semester_name']); ?>" 
                                                                   class="flex-1 px-3 py-1.5 bg-white border border-indigo-200 rounded-lg text-sm font-bold focus:ring-2 focus:ring-indigo-500 outline-none shadow-sm">
                                                            <button type="submit" class="bg-indigo-600 text-white px-3 py-1.5 rounded-lg text-[10px] font-black uppercase hover:bg-indigo-700 shadow-sm transition-colors">Save</button>
                                                            <button type="button" onclick="toggleEdit(<?php echo $sem['id']; ?>)" class="text-gray-400 text-xs font-bold hover:text-gray-600 px-2 transition-colors">Cancel</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Add New Semester -->
                                    <div class="bg-indigo-50/30 p-4 rounded-xl border border-dashed border-indigo-100">
                                        <div class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-2">Build New Semester Node</div>
                                        <div class="flex gap-2">
                                            <input type="text" name="new_semester_name" placeholder="e.g. 'Fourth Semester'" 
                                                   class="flex-1 bg-white border border-indigo-100 rounded-lg px-4 py-2 text-sm font-bold focus:ring-2 focus:ring-indigo-500 outline-none">
                                            <button type="submit" name="semester_action" value="add_semester" 
                                                    class="bg-indigo-600 text-white w-10 h-10 rounded-lg shadow-sm hover:bg-indigo-700 transition flex items-center justify-center">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <script>
                                    function toggleEdit(id) {
                                        const disp = document.getElementById('disp_sem_' + id);
                                        const edit = document.getElementById('edit_sem_' + id);
                                        if (edit.classList.contains('hidden')) {
                                            edit.classList.remove('hidden');
                                            disp.classList.add('hidden');
                                        } else {
                                            edit.classList.add('hidden');
                                            disp.classList.remove('hidden');
                                        }
                                    }
                                </script>

                                <hr class="border-gray-100 my-5">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">User & Role Management</label>
                                    <p class="text-xs text-gray-500 mb-3">To update system users, administrators, and staff roles, navigate to the User Management dashboard.</p>
                                    <a href="staff/view_staff.php" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-sm font-medium rounded-lg transition-colors border border-indigo-100">
                                        <i class="fas fa-users-gear"></i> Manage System Users
                                    </a>
                                </div>
                                <hr class="border-gray-100 my-5">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">System Audit Logs</label>
                                    <p class="text-xs text-gray-500 mb-3">View the read-only dictionary of the system's configuration changes and history logs.</p>
                                    <a href="audit_logs.php" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-50 text-gray-700 hover:bg-gray-100 text-sm font-medium rounded-lg transition-colors border border-gray-200 shadow-sm">
                                        <i class="fas fa-clipboard-list text-gray-500"></i> View Audit Logs
                                    </a>
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
                                    <label for="school_name" class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1">Company / School Name</label>
                                    <input type="text" id="school_name" name="school_name" 
                                           value="<?php echo htmlspecialchars(getSystemSetting($conn, 'school_name', '')); ?>"
                                           class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                </div>
                                <div>
                                    <label for="school_email" class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1">Contact Email</label>
                                    <input type="email" id="school_email" name="school_email" 
                                           value="<?php echo htmlspecialchars(getSystemSetting($conn, 'school_email', '')); ?>"
                                           class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                </div>
                                <div>
                                    <label for="school_phone" class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1">Primary Phone</label>
                                    <input type="text" id="school_phone" name="school_phone" 
                                           value="<?php echo htmlspecialchars(getSystemSetting($conn, 'school_phone', '')); ?>"
                                           class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                </div>
                                <div>
                                    <label for="school_address" class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1">Physical Address</label>
                                    <textarea id="school_address" name="school_address" rows="3"
                                              class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"><?php echo htmlspecialchars(getSystemSetting($conn, 'school_address', '')); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex gap-3">
                                <button type="submit" class="flex-1 bg-emerald-600 text-white font-medium text-sm px-4 py-2.5 rounded-lg border border-transparent shadow-sm hover:bg-emerald-700 transition-colors flex justify-center items-center gap-2">
                                    <i class="fas fa-save"></i> Save All
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
