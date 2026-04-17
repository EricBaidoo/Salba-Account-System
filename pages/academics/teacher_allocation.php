<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../login');
    exit;
}

$success = '';
$error = '';

$current_academic_year = getAcademicYear($conn);

// Safe Migration: Ensure teacher_allocations has role-specific flags
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$cols_to_check = [
    'is_class_teacher' => "TINYINT(1) DEFAULT 0",
    'is_subject_teacher' => "TINYINT(1) DEFAULT 0"
];
foreach ($cols_to_check as $col => $def) {
    if (!$conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'teacher_allocations' AND COLUMN_NAME = '$col'")->fetch_row()[0]) {
        $conn->query("ALTER TABLE teacher_allocations ADD COLUMN `$col` $def");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'allocate_teacher') {
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $classes = $_POST['classes'] ?? [];
    $is_class_teacher = isset($_POST['is_class_teacher']) ? 1 : 0;
    $is_subject_teacher = isset($_POST['is_subject_teacher']) ? 1 : 0;
    
    if ($teacher_id && !empty($classes)) {
        if (!$is_class_teacher && !$is_subject_teacher) {
            $error = "At least one role (Class Teacher or Subject Teacher) must be selected.";
        } else if ($is_subject_teacher && !$subject_id) {
            $error = "Please select a Subject for the Subject Teacher role.";
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO teacher_allocations (teacher_id, subject_id, class_name, year, is_class_teacher, is_subject_teacher) 
                                        SELECT ?, ?, ?, ?, ?, ? 
                                        WHERE NOT EXISTS (
                                            SELECT 1 FROM teacher_allocations 
                                            WHERE teacher_id = ? AND subject_id = ? AND class_name = ? AND year = ?
                                        )");
                
                $count = 0;
                foreach ($classes as $class_name) {
                    $class_name = trim($class_name);
                    $stmt->bind_param('iissiiiiss', $teacher_id, $subject_id, $class_name, $current_academic_year, $is_class_teacher, $is_subject_teacher,
                                                    $teacher_id, $subject_id, $class_name, $current_academic_year);
                    if ($stmt->execute()) {
                        if ($conn->affected_rows > 0) $count++;
                    }
                }
                $stmt->close();
                $conn->commit();
                $success = "$count teaching assignment(s) registered successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Failed to allocate teachers: " . $e->getMessage();
            }
        }
    } else {
        $error = "Please select at least one teacher and one class.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_allocation') {
    $alloc_id = intval($_POST['allocation_id'] ?? 0);
    if ($alloc_id) {
        $conn->query("DELETE FROM teacher_allocations WHERE id = $alloc_id LIMIT 1");
        if ($conn->affected_rows > 0) {
            $success = "Teaching assignment revoked successfully.";
        } else {
            $error = "Failed to revoke assignment or it doesn't exist.";
        }
    }
}

// Get allocations
$allocations = $conn->query("
    SELECT ta.*, 
           u.username as teacher_alias,
           sp.full_name as real_teacher_name,
           s.name as subject_alias
    FROM teacher_allocations ta
    LEFT JOIN users u ON ta.teacher_id = u.id
    LEFT JOIN staff_profiles sp ON u.staff_id = sp.id
    LEFT JOIN subjects s ON ta.subject_id = s.id
    ORDER BY sp.full_name, u.username, ta.is_class_teacher DESC, ta.class_name
");

// Get lists for dropdowns
$teachers = $conn->query("
    SELECT u.id, u.username, sp.full_name 
    FROM users u 
    LEFT JOIN staff_profiles sp ON u.staff_id = sp.id 
    WHERE u.role IN ('facilitator', 'staff') 
    ORDER BY sp.full_name, u.username
");
$subjects = $conn->query("SELECT id, name as subject_name FROM subjects ORDER BY name");
$classes_res = $conn->query("SELECT DISTINCT class FROM students WHERE status='active' ORDER BY class");
$classes_list = [];
if ($classes_res) {
    while($r = $classes_res->fetch_assoc()) $classes_list[] = $r['class'];
}
if(empty($classes_list)) {
    // Fallback default classes
    $classes_list = ['Creche','Nursery 1','Nursery 2','KG 1','KG 2','Basic 1','Basic 2','Basic 3','Basic 4','Basic 5','Basic 6','Basic 7'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Allocation - Academics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script>
        function toggleModal(modalID){
            document.getElementById(modalID).classList.toggle("hidden");
            document.getElementById(modalID).classList.toggle("flex");
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="ml-72 min-h-screen relative">
        <!-- Header Section -->
        <div class="bg-white border-b border-gray-100 px-8 py-6 sticky top-0 z-30">
            <div class="flex items-center gap-3 mb-4">
                <a href="dashboard.php" class="text-gray-400 hover:text-purple-600 transition-colors flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i> Back to Academics Dashboard
                </a>
            </div>
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                        <i class="fas fa-user-tie text-purple-500"></i> Teacher Allocation
                    </h1>
                    <p class="text-gray-500 mt-2 text-sm">
                        Map teachers to specific subjects and class sections for <strong><?php echo htmlspecialchars($current_academic_year); ?></strong>
                    </p>
                </div>
                <button onclick="toggleModal('allocateModal')" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg hover:bg-purple-700 transition shadow-sm border border-transparent flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-plus"></i> Bind Teacher
                </button>
            </div>
        </div>

        <div class="p-8 max-w-6xl">
            <?php if ($success): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-3 shadow-sm">
                    <i class="fas fa-check-circle text-emerald-500"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-3 shadow-sm">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-link text-gray-400"></i> Current Allocations
                    </h5>
                    <span class="px-2 py-1 bg-gray-200 text-gray-600 text-xs font-mono rounded">
                        Total: <?php echo $allocations ? $allocations->num_rows : 0; ?>
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100 font-semibold text-gray-500">
                                <th class="px-6 py-4">Role Status</th>
                                <th class="px-6 py-4">Subject Focus</th>
                                <th class="px-6 py-4">Class Target</th>
                                <th class="px-6 py-4">Academic Year</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php 
                            $has_allocations = false;
                            $current_teacher_id = null;
                            if ($allocations && $allocations->num_rows > 0):
                                while ($row = $allocations->fetch_assoc()):
                                    $has_allocations = true;
                                    $display_id = $row['teacher_alias'] ?: 'No ID';
                                    $display_name = $row['real_teacher_name'] ?: $display_id;
                                    
                                    // Output Teacher Group Header
                                    if ($display_id !== $current_teacher_id) {
                                        echo '<tr class="bg-purple-50 border-y border-purple-100">';
                                        echo '  <td colspan="5" class="px-6 py-3">';
                                        echo '      <div class="flex items-center gap-3">';
                                        echo '          <div class="w-8 h-8 rounded-full bg-purple-200 text-purple-700 flex items-center justify-center font-bold text-sm uppercase">' . substr($display_name, 0, 2) . '</div>';
                                        echo '          <div class="font-bold text-gray-900 text-base">' . htmlspecialchars($display_name) . ' <span class="text-sm font-medium text-purple-600 ml-2 border border-purple-200 bg-white px-2 py-0.5 rounded-full shadow-sm">Staff ID: ' . htmlspecialchars($display_id) . '</span></div>';
                                        echo '      </div>';
                                        echo '  </td>';
                                        echo '</tr>';
                                        $current_teacher_id = $display_id;
                                    }

                                    if ($row['subject_id'] == 0 && $row['is_class_teacher']) {
                                        $display_subject = '<span class="text-gray-400 italic font-normal">Class Responsibility</span>';
                                    } else {
                                        $display_subject = htmlspecialchars($row['subject_alias'] ?: ($row['subject_name'] ?: 'General'));
                                    }
                            ?>
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4 pl-16">
                                        <?php if ($row['is_class_teacher']): ?>
                                            <span class="px-2 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-black uppercase rounded-full border border-emerald-100 flex items-center gap-1 w-fit">
                                                <i class="fas fa-home"></i> Home Class
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 bg-blue-50 text-blue-700 text-[10px] font-black uppercase rounded-full border border-blue-100 flex items-center gap-1 w-fit">
                                                <i class="fas fa-walking"></i> Visiting
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-700">
                                        <?php echo $display_subject; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1">
                                            <?php if ($row['is_class_teacher']): ?>
                                                <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 font-bold text-[10px] uppercase rounded border border-indigo-100 flex items-center gap-1">
                                                    <i class="fas fa-chalkboard-user"></i> Class Tr
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($row['is_subject_teacher']): ?>
                                                <span class="px-2 py-0.5 bg-amber-50 text-amber-700 font-bold text-[10px] uppercase rounded border border-amber-100 flex items-center gap-1">
                                                    <i class="fas fa-book"></i> Subject Tr
                                                </span>
                                            <?php endif; ?>
                                            <span class="px-2.5 py-1 bg-blue-50 text-blue-700 font-bold text-xs rounded border border-blue-100">
                                                <?php echo htmlspecialchars($row['class_name'] ?? '—'); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500">
                                        <?php echo htmlspecialchars($row['year'] ?? '—'); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <form method="POST" action="" class="inline-block" onsubmit="return confirm('Are you sure you want to revoke this teaching assignment?');">
                                            <input type="hidden" name="action" value="revoke_allocation">
                                            <input type="hidden" name="allocation_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="text-gray-400 hover:text-red-600 transition-colors" title="Revoke Allocation">
                                                <i class="fas fa-unlink text-lg"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php 
                                endwhile;
                            endif;
                            ?>
                            <?php if (!$has_allocations): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                                            <i class="fas fa-clipboard-user text-2xl text-gray-300"></i>
                                        </div>
                                        <p class="font-medium text-gray-900 mb-1">No teachers allocated yet</p>
                                        <p class="text-sm">Click "Bind Teacher" to map staff to their teaching assignments.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal -->
    <div id="allocateModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/50 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-xl shadow-xl border border-gray-100 w-full max-w-lg overflow-hidden relative">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="font-bold text-gray-900"><i class="fas fa-link text-purple-500 mr-2"></i> Register Teaching Assignment</h3>
                <button onclick="toggleModal('allocateModal')" class="text-gray-400 hover:text-red-500 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <form action="" method="POST" class="p-6">
                <input type="hidden" name="action" value="allocate_teacher">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Teaching Staff <span class="text-red-500">*</span></label>
                        <select name="teacher_id" required class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors">
                            <option value="">-- Select Teacher --</option>
                            <?php 
                            if ($teachers) {
                                while ($t = $teachers->fetch_assoc()) {
                                    $disp = $t['full_name'] ? ($t['full_name'] . ' (' . $t['username'] . ')') : $t['username'];
                                    echo '<option value="' . $t['id'] . '">' . htmlspecialchars($disp) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div id="subject_selection_group">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Target Subject <span id="subject_req_indicator"></span></label>
                        <select name="subject_id" id="subject_select" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors">
                            <option value="0">-- All Subjects (Default) --</option>
                            <?php 
                            $subjects_res = $conn->query("SELECT id, name as subject_name FROM subjects ORDER BY name");
                            if ($subjects_res) {
                                while ($sub = $subjects_res->fetch_assoc()) {
                                    echo '<option value="' . $sub['id'] . '">' . htmlspecialchars($sub['subject_name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center mb-1">
                            <label class="block text-sm font-semibold text-gray-700">Target Classes <span class="text-red-500">*</span></label>
                            <button type="button" onclick="toggleAllClasses()" class="text-[10px] text-purple-600 font-bold uppercase hover:underline">Select/Deselect All</button>
                        </div>
                        <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto p-3 bg-gray-50 rounded-lg border border-gray-100" id="classes_container">
                            <?php foreach ($classes_list as $cl): ?>
                                <label class="flex items-center gap-2 p-1.5 hover:bg-white rounded cursor-pointer transition-colors border border-transparent hover:border-gray-100">
                                    <input type="checkbox" name="classes[]" value="<?php echo htmlspecialchars($cl); ?>" class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500 class-checkbox">
                                    <span class="text-xs font-medium text-gray-700"><?php echo htmlspecialchars($cl); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 space-y-3">
                        <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">Designated Roles (Select at least one)</p>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="flex items-center gap-3 p-3 rounded-xl border-2 border-gray-100 cursor-pointer hover:bg-purple-50 hover:border-purple-200 transition-all group role-checkbox-container" data-role="class">
                                <input type="checkbox" name="is_class_teacher" id="is_class_teacher" class="w-5 h-5 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                <div>
                                    <span class="block text-sm font-bold text-gray-800">Class Teacher</span>
                                    <span class="block text-[10px] text-gray-500 leading-tight">Generalist: Teaches all subjects in assigned class(es).</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 p-3 rounded-xl border-2 border-gray-100 cursor-pointer hover:bg-blue-50 hover:border-blue-200 transition-all group role-checkbox-container" data-role="subject">
                                <input type="checkbox" name="is_subject_teacher" id="is_subject_teacher" class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <div>
                                    <span class="block text-sm font-bold text-gray-800">Subject Teacher</span>
                                    <span class="block text-[10px] text-gray-500 leading-tight">Specialist: Teaches only a specific subject.</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('allocateModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 shadow-sm transition-colors flex items-center gap-2">
                        <i class="fas fa-link"></i> Register Assignments
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const subjectCheck = document.getElementById('is_subject_teacher');
        const classCheck = document.getElementById('is_class_teacher');
        const subjectSelect = document.getElementById('subject_select');
        const subjectIndicator = document.getElementById('subject_req_indicator');
        const subjectGroup = document.getElementById('subject_selection_group');

        function updateSubjectRequirement() {
            // Visual feedback for role selection
            document.querySelectorAll('.role-checkbox-container').forEach(container => {
                const input = container.querySelector('input');
                if (input.checked) {
                    container.classList.add(input.id === 'is_class_teacher' ? 'bg-purple-50' : 'bg-blue-50');
                    container.classList.add(input.id === 'is_class_teacher' ? 'border-purple-500' : 'border-blue-500');
                } else {
                    container.classList.remove('bg-purple-50', 'bg-blue-50', 'border-purple-500', 'border-blue-500');
                }
            });

            if (subjectCheck.checked) {
                subjectSelect.setAttribute('required', 'required');
                subjectIndicator.innerHTML = '<span class="text-red-500">*</span>';
                subjectGroup.classList.remove('opacity-50');
                // Ensure they don't leave it on "All Subjects" if they are specifically a Subject Teacher
                if (subjectSelect.value === '0') subjectSelect.value = '';
            } else {
                subjectSelect.removeAttribute('required');
                subjectIndicator.innerHTML = '<span class="text-gray-400 font-normal">(Optional for Class Teachers)</span>';
                if (classCheck.checked) {
                    subjectSelect.value = '0'; // Default to "All Subjects"
                }
            }
        }

        function toggleAllClasses() {
            const checkboxes = document.querySelectorAll('.class-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
        }

        subjectCheck.addEventListener('change', updateSubjectRequirement);
        classCheck.addEventListener('change', updateSubjectRequirement);
        updateSubjectRequirement(); // init
    </script>

</body>
</html>
