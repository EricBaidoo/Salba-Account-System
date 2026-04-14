<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../includes/login.php');
    exit;
}

$success = '';
$error = '';

$current_academic_year = getAcademicYear($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'allocate_teacher') {
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $class_name = trim($_POST['class'] ?? '');
    
    if ($teacher_id && $subject_id && $class_name) {
        $stmt = $conn->prepare("INSERT INTO teacher_allocations (teacher_id, subject_id, class_name, year) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('iiss', $teacher_id, $subject_id, $class_name, $current_academic_year);
            if ($stmt->execute()) {
                $success = "Teacher allocated successfully!";
            } else {
                $error = "Failed to allocate teacher. " . $conn->error;
            }
            $stmt->close();
        }
    } else {
        $error = "Please fill all required fields.";
    }
}

// Get allocations
$allocations = $conn->query("
    SELECT ta.*, 
           u.username as teacher_alias,
           s.name as subject_alias
    FROM teacher_allocations ta
    LEFT JOIN users u ON ta.teacher_id = u.id
    LEFT JOIN subjects s ON ta.subject_id = s.id
    ORDER BY ta.class_name, s.name
");

// Get lists for dropdowns
$teachers = $conn->query("SELECT id, username FROM users WHERE role IN ('teacher', 'staff') ORDER BY username");
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
                                <th class="px-6 py-4">Assigned Teacher</th>
                                <th class="px-6 py-4">Subject Focus</th>
                                <th class="px-6 py-4">Class Target</th>
                                <th class="px-6 py-4">Academic Year</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php 
                            $has_allocations = false;
                            if ($allocations && $allocations->num_rows > 0):
                                while ($row = $allocations->fetch_assoc()):
                                    $has_allocations = true;
                                    $display_teacher = $row['teacher_alias'] ?: ($row['teacher_name'] ?: 'Unassigned');
                                    $display_subject = $row['subject_alias'] ?: ($row['subject_name'] ?: 'Unknown');
                            ?>
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-xs uppercase">
                                                <?php echo substr($display_teacher, 0, 2); ?>
                                            </div>
                                            <div class="font-bold text-gray-900"><?php echo htmlspecialchars($display_teacher); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-700">
                                        <?php echo htmlspecialchars($display_subject); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2.5 py-1 bg-blue-50 text-blue-700 font-bold text-xs rounded border border-blue-100">
                                            <?php echo htmlspecialchars($row['class_name'] ?? '—'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500">
                                        <?php echo htmlspecialchars($row['year'] ?? '—'); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="text-gray-400 hover:text-red-600 transition-colors" title="Revoke Allocation">
                                            <i class="fas fa-unlink"></i>
                                        </button>
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
                                    echo '<option value="' . $t['id'] . '">' . htmlspecialchars($t['username']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Subject Curricula <span class="text-red-500">*</span></label>
                        <select name="subject_id" required class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors">
                            <option value="">-- Select Subject --</option>
                            <?php 
                            if ($subjects) {
                                while ($sub = $subjects->fetch_assoc()) {
                                    echo '<option value="' . $sub['id'] . '">' . htmlspecialchars($sub['subject_name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Target Class <span class="text-red-500">*</span></label>
                        <select name="class" required class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors">
                            <option value="">-- Select Class --</option>
                            <?php foreach ($classes_list as $cl): ?>
                                <option value="<?php echo htmlspecialchars($cl); ?>"><?php echo htmlspecialchars($cl); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('allocateModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 shadow-sm transition-colors flex items-center gap-2">
                        <i class="fas fa-link"></i> Bind Target
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
