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

// 1. Process Class Subject Mapping (Migrated from Settings)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'map_subjects' && isset($_POST['save_binder'])) {
    $mapped_class = trim($_POST['class']);
    $sub_ids = $_POST['subjects'] ?? [];
    if ($mapped_class) {
        $stmt = $conn->prepare("DELETE FROM class_subjects WHERE class_name = ?");
        $stmt->bind_param("s", $mapped_class);
        $stmt->execute();
        if (!empty($sub_ids)) {
            $insert_stmt = $conn->prepare("INSERT INTO class_subjects (class_name, subject_id) VALUES (?, ?)");
            foreach($sub_ids as $sid) {
                $sid = intval($sid);
                $insert_stmt->bind_param("si", $mapped_class, $sid);
                $insert_stmt->execute();
            }
            $success = count($sub_ids)." subjects mapped to ".htmlspecialchars($mapped_class)."!";
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_subject') {
    $subject_name = trim($_POST['subject_name'] ?? '');
    $subject_code = trim($_POST['subject_code'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    if (empty($subject_name) || empty($subject_code)) {
        $error = "Subject Name and Code are required.";
    } else {
        // Enforce code uniqueness via PHP check first since DB constraint might be missing
        $check = $conn->prepare("SELECT id FROM subjects WHERE code = ?");
        $check->bind_param('s', $subject_code);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "A subject with this code already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO subjects (name, code, description) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('sss', $subject_name, $subject_code, $description);
                if ($stmt->execute()) {
                    $success = "Subject added successfully!";
                } else {
                    $error = "Database Error: " . $conn->error;
                }
                $stmt->close();
            }
        }
        $check->close();
    }
}

// Handle Subject Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_subject') {
    $subject_id = intval($_POST['subject_id'] ?? 0);
    if ($subject_id > 0) {
        $del = $conn->prepare("DELETE FROM subjects WHERE id = ?");
        $del->bind_param("i", $subject_id);
        if ($del->execute()) {
            $success = "Subject deleted successfully.";
        } else {
            $error = "Database Error: " . $conn->error;
        }
    }
}

// Handle Subject Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_subject') {
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $subject_name = trim($_POST['subject_name'] ?? '');
    $subject_code = trim($_POST['subject_code'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    if (empty($subject_name) || empty($subject_code) || $subject_id <= 0) {
        $error = "Subject Name and Code are required.";
    } else {
        $check = $conn->prepare("SELECT id FROM subjects WHERE code = ? AND id != ?");
        $check->bind_param('si', $subject_code, $subject_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Another subject with this code already exists.";
        } else {
            $stmt = $conn->prepare("UPDATE subjects SET name = ?, code = ?, description = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('sssi', $subject_name, $subject_code, $description, $subject_id);
                if ($stmt->execute()) {
                    $success = "Subject updated successfully!";
                } else {
                    $error = "Database Error: " . $conn->error;
                }
                $stmt->close();
            }
        }
        $check->close();
    }
}

// Get subjects
$subjects = $conn->query("SELECT id, name as subject_name, code as subject_code, description FROM subjects ORDER BY name");


// Fetch Classes & Mappings (Migrated from Settings)
$classes_res = $conn->query("SELECT DISTINCT name as class FROM classes ORDER BY name");
$classes_list = [];
if ($classes_res) { while($r = $classes_res->fetch_assoc()) $classes_list[] = $r['class']; }

$mappings = [];
$map_res = $conn->query("SELECT class_name, subject_id FROM class_subjects");
if ($map_res) {
    while($r = $map_res->fetch_assoc()){ $mappings[$r['class_name']][] = $r['subject_id']; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Management - Academics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script>
        function toggleModal(modalID){
            document.getElementById(modalID).classList.toggle("hidden");
            document.getElementById(modalID).classList.toggle("flex");
        }

        function openEditModal(id, name, code, description) {
            document.getElementById('edit_subject_id').value = id;
            document.getElementById('edit_subject_name').value = name;
            document.getElementById('edit_subject_code').value = code;
            document.getElementById('edit_description').value = description;
            toggleModal('editSubjectModal');
        }

        function triggerDelete(id, name) {
            if (confirm("Are you sure you want to permanently delete '" + name + "'? This action cannot be undone.")) {
                const form = document.createElement('form');
                form.method = 'POST';
                
                const action = document.createElement('input');
                action.type = 'hidden';
                action.name = 'action';
                action.value = 'delete_subject';
                
                const sid = document.createElement('input');
                sid.type = 'hidden';
                sid.name = 'subject_id';
                sid.value = id;
                
                form.appendChild(action);
                form.appendChild(sid);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="ml-72 min-h-screen relative">
        <!-- Header Section -->
        <div class="bg-white border-b border-gray-100 px-8 py-6 sticky top-0 z-30">
            <div class="flex items-center gap-3 mb-4">
                <a href="dashboard.php" class="text-gray-400 hover:text-indigo-600 transition-colors flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i> Back to Academics Dashboard
                </a>
            </div>
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                        <i class="fas fa-book-open text-indigo-600"></i> Subject Management
                    </h1>
                    <p class="text-gray-500 mt-2 text-sm">
                        Define school curricula, subjects, and unique subject identifiers.
                    </p>
                </div>
                <button onclick="toggleModal('addSubjectModal')" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg hover:bg-indigo-700 transition shadow-sm border border-transparent flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-plus"></i> Register Subject
                </button>
            </div>
        </div>

        <div class="p-8">
            <?php if ($success): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl mb-6 flex items-center gap-3 shadow-sm font-bold">
                    <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl mb-6 flex items-center gap-3 shadow-sm font-bold">
                    <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left: Subjects List (8 cols) -->
                <div class="lg:col-span-7 space-y-6">

            <!-- Subjects Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-list text-gray-400"></i> Active Subjects Directory
                    </h5>
                    <span class="px-2 py-1 bg-gray-200 text-gray-600 text-xs font-mono rounded">
                        Total: <?php echo $subjects ? $subjects->num_rows : 0; ?>
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100 font-semibold text-gray-500">
                                <th class="px-6 py-4">Subject Name</th>
                                <th class="px-6 py-4">Subject Code</th>
                                <th class="px-6 py-4">Description</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php 
                            $has_subjects = false;
                            if ($subjects && $subjects->num_rows > 0):
                                while ($row = $subjects->fetch_assoc()):
                                    $has_subjects = true;
                            ?>
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-900"><?php echo htmlspecialchars($row['subject_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 bg-indigo-50 text-indigo-700 font-mono text-xs font-bold rounded border border-indigo-100">
                                            <?php echo htmlspecialchars($row['subject_code']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 max-w-md truncate">
                                        <?php echo htmlspecialchars($row['description']) ?: '<span class="text-gray-300 italic">No description</span>'; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button onclick="openEditModal(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['subject_name'])) ?>', '<?= addslashes(htmlspecialchars($row['subject_code'])) ?>', '<?= addslashes(htmlspecialchars($row['description'])) ?>')" class="text-gray-400 hover:text-indigo-600 transition-colors mr-3" title="Edit Subject">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="triggerDelete(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['subject_name'])) ?>')" class="text-gray-400 hover:text-red-600 transition-colors" title="Delete Subject">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php 
                                endwhile;
                            endif;
                            ?>
                            <?php if (!$has_subjects): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                                            <i class="fas fa-book-open text-2xl text-gray-300"></i>
                                        </div>
                                        <p class="font-medium text-gray-900 mb-1">No subjects found</p>
                                        <p class="text-sm">Click "Register Subject" to begin adding your curriculum.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                    </div>
                </div>

                <!-- Right: Class Curriculum Binder (5 cols) -->
                <div class="lg:col-span-5">
                    <div class="bg-gray-900 rounded-2xl shadow-xl border border-gray-800 overflow-hidden sticky top-32">
                        <div class="bg-gradient-to-r from-purple-900/40 to-indigo-900/40 px-8 py-5 border-b border-gray-800">
                            <h2 class="text-white font-black flex items-center gap-3 tracking-tight">
                                <i class="fas fa-sitemap text-purple-400"></i> Class Curriculum Binder
                            </h2>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">Bind subjects to specific class levels</p>
                        </div>
                        
                        <form method="POST" class="p-8">
                            <input type="hidden" name="action" value="map_subjects">
                            
                            <div class="mb-8">
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">1. Target Class</label>
                                <select name="class" required onchange="this.form.submit()" class="w-full px-5 py-4 border border-gray-700 rounded-xl text-sm bg-gray-800 text-white font-bold hover:border-purple-500 focus:ring-2 focus:ring-purple-500 outline-none transition-all cursor-pointer">
                                    <option value="">-- Select Enrollment Level --</option>
                                    <?php foreach($classes_list as $cl): ?>
                                        <option value="<?= htmlspecialchars($cl) ?>" <?= (trim($_POST['class'] ?? '') === $cl) ? 'selected' : '' ?>><?= htmlspecialchars($cl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if(!empty($_POST['class'])): 
                                $target_cl = trim($_POST['class']);
                                $active_maps = $mappings[$target_cl] ?? [];
                            ?>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-4">2. Permitted Curriculum for <span class="text-purple-400"><?= htmlspecialchars($target_cl) ?></span></label>
                                <div class="space-y-2 max-h-[400px] overflow-y-auto mb-8 pr-2 custom-scrollbar">
                                    <?php 
                                    $subjects->data_seek(0);
                                    while($sub = $subjects->fetch_assoc()): 
                                        $checked = in_array($sub['id'], $active_maps) ? 'checked' : '';
                                    ?>
                                        <label class="flex items-center gap-4 p-4 bg-gray-800/50 border border-gray-700/50 rounded-xl hover:border-purple-500/50 hover:bg-gray-800 transition-all cursor-pointer group">
                                            <input type="checkbox" name="subjects[]" value="<?= $sub['id'] ?>" <?= $checked ?> class="w-5 h-5 rounded border-gray-600 text-purple-600 focus:ring-purple-500 bg-gray-900 cursor-pointer">
                                            <div class="flex flex-col">
                                                <span class="text-sm font-bold text-gray-200 group-hover:text-white transition-colors"><?= htmlspecialchars($sub['subject_name']) ?></span>
                                                <span class="text-[10px] font-mono text-gray-500 tracking-tighter"><?= htmlspecialchars($sub['subject_code']) ?></span>
                                            </div>
                                        </label>
                                    <?php endwhile; ?>
                                </div>
                                <button type="submit" name="save_binder" class="w-full bg-purple-600 text-white font-black py-5 rounded-2xl shadow-lg hover:bg-purple-700 hover:scale-[1.02] active:scale-95 transition-all text-sm flex items-center justify-center gap-3">
                                    <i class="fas fa-save"></i> UPDATE CURRICULUM
                                </button>
                            <?php else: ?>
                                <div class="text-center py-20 bg-gray-800/20 rounded-2xl border border-dashed border-gray-700/50">
                                    <i class="fas fa-layer-group text-5xl mb-4 text-gray-700"></i>
                                    <p class="text-xs font-bold text-gray-500 uppercase tracking-widest leading-relaxed">Select a class from the dropdown above<br>to configure its valid subjects.</p>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Add Subject Modal -->
    <div id="addSubjectModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/50 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-xl shadow-xl border border-gray-100 w-full max-w-lg overflow-hidden relative">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="font-bold text-gray-900"><i class="fas fa-plus text-indigo-500 mr-2"></i> Register New Subject</h3>
                <button onclick="toggleModal('addSubjectModal')" class="text-gray-400 hover:text-red-500 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <form action="" method="POST" class="p-6">
                <input type="hidden" name="action" value="add_subject">
                
                <div class="space-y-4">
                    <div>
                        <label for="subject_name" class="block text-sm font-semibold text-gray-700 mb-1">Subject Name <span class="text-red-500">*</span></label>
                        <input type="text" id="subject_name" name="subject_name" required placeholder="e.g., General Science"
                               class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors">
                    </div>
                    <div>
                        <label for="subject_code" class="block text-sm font-semibold text-gray-700 mb-1">Subject Code <span class="text-red-500">*</span></label>
                        <input type="text" id="subject_code" name="subject_code" required placeholder="e.g., SCI-101"
                               class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors font-mono">
                        <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-wider">Must be unique across the system</p>
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-semibold text-gray-700 mb-1">Description (Optional)</label>
                        <textarea id="description" name="description" rows="3" placeholder="Brief overview of the subject..."
                                  class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors resize-none"></textarea>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('addSubjectModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-sm transition-colors flex items-center gap-2">
                        <i class="fas fa-save"></i> Save Subject
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Subject Modal -->
    <div id="editSubjectModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/50 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-xl shadow-xl border border-gray-100 w-full max-w-lg overflow-hidden relative">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="font-bold text-gray-900"><i class="fas fa-edit text-indigo-500 mr-2"></i> Edit Subject</h3>
                <button onclick="toggleModal('editSubjectModal')" class="text-gray-400 hover:text-red-500 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <form action="" method="POST" class="p-6">
                <input type="hidden" name="action" value="edit_subject">
                <input type="hidden" name="subject_id" id="edit_subject_id">
                
                <div class="space-y-4">
                    <div>
                        <label for="edit_subject_name" class="block text-sm font-semibold text-gray-700 mb-1">Subject Name <span class="text-red-500">*</span></label>
                        <input type="text" id="edit_subject_name" name="subject_name" required placeholder="e.g., General Science"
                               class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors">
                    </div>
                    <div>
                        <label for="edit_subject_code" class="block text-sm font-semibold text-gray-700 mb-1">Subject Code <span class="text-red-500">*</span></label>
                        <input type="text" id="edit_subject_code" name="subject_code" required placeholder="e.g., SCI-101"
                               class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors font-mono">
                    </div>
                    <div>
                        <label for="edit_description" class="block text-sm font-semibold text-gray-700 mb-1">Description (Optional)</label>
                        <textarea id="edit_description" name="description" rows="3" placeholder="Brief overview of the subject..."
                                  class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors resize-none"></textarea>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('editSubjectModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-sm transition-colors flex items-center gap-2">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
