<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

$error_msg = '';
$success_msg = '';

if (!isset($_GET['id'])) {
    header('Location: view_parents.php');
    exit;
}

$id = (int)$_GET['id'];

// POST HANDLER FOR LINKING & UNLINKING & UPDATES
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Unlink student action
    if (isset($_POST['unlink_student'])) {
        $stu_id = intval($_POST['unlink_student']);
        $conn->query("DELETE FROM student_parents WHERE parent_id = $id AND student_id = $stu_id");
        $_SESSION['success_msg'] = "Student unlinked successfully.";
        header("Location: edit_parent.php?id=$id");
        exit;
    }
    
    // 2. Link student action
    if (isset($_POST['link_student'])) {
        $stu_id = intval($_POST['student_to_link'] ?? 0);
        $rel = trim($_POST['link_relationship'] ?? 'Guardian');
        if ($stu_id > 0) {
            $check = $conn->query("SELECT * FROM student_parents WHERE parent_id = $id AND student_id = $stu_id");
            if ($check && $check->num_rows === 0) {
                $stmt = $conn->prepare("INSERT INTO student_parents (student_id, parent_id, relationship) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $stu_id, $id, $rel);
                if ($stmt->execute()) {
                    $_SESSION['success_msg'] = "Student linked successfully.";
                } else {
                    $_SESSION['error_msg'] = "Error linking student: " . $conn->error;
                }
                $stmt->close();
            } else {
                $_SESSION['error_msg'] = "Student is already linked to this parent.";
            }
        }
        header("Location: edit_parent.php?id=$id");
        exit;
    }

    // 3. Default update parent details action
    $title = trim($_POST['title'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $relationship = trim($_POST['relationship'] ?? 'guardian');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $is_primary = isset($_POST['is_primary']) ? 1 : 0;

    if (empty($first_name) && empty($last_name)) {
        $error_msg = "Name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE parents SET title=?, first_name=?, last_name=?, relationship=?, phone=?, email=?, address=?, is_primary=? WHERE id=?");
        $stmt->bind_param("sssssssii", $title, $first_name, $last_name, $relationship, $phone, $email, $address, $is_primary, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Parent details successfully updated.";
            header("Location: view_parents.php");
            exit;
        } else {
            $error_msg = "Error updating parent: " . $conn->error;
        }
        $stmt->close();
    }
}

$stmt = $conn->prepare("SELECT * FROM parents WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$parent = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$parent) {
    header('Location: view_parents.php');
    exit;
}

// Fetch all students for linking dropdown
$all_students_res = $conn->query("SELECT id, first_name, last_name, class FROM students ORDER BY last_name ASC, first_name ASC");
$all_students = [];
if ($all_students_res) {
    while ($row = $all_students_res->fetch_assoc()) {
        $all_students[] = $row;
    }
}

// Fetch currently linked students
$linked_students_res = $conn->query("
    SELECT s.id, s.first_name, s.last_name, s.class, sp.relationship
    FROM students s
    JOIN student_parents sp ON s.id = sp.student_id
    WHERE sp.parent_id = $id
");
$linked_students = [];
if ($linked_students_res) {
    while ($row = $linked_students_res->fetch_assoc()) {
        $linked_students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Parent - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 min-h-screen">
        <div class="bg-white border-b border-gray-100 px-8 py-6 flex justify-between items-center">
            <div>
                <a href="view_parents.php" class="text-sm font-bold text-indigo-600 hover:text-indigo-800 mb-2 inline-block"><i class="fas fa-arrow-left mr-1"></i> Back to Directory</a>
                <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-user-edit text-indigo-600"></i> Edit Parent Profile
                </h1>
                <p class="text-gray-500 mt-2 font-medium">Update details for <?= htmlspecialchars(trim($parent['title'] . ' ' . $parent['first_name'] . ' ' . $parent['last_name'])) ?>.</p>
            </div>
        </div>

        <div class="p-8 max-w-4xl mx-auto mt-4">
            <?php if ($error_msg): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($error_msg) ?></span>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl flex items-center gap-3">
                    <i class="fas fa-check-circle"></i> <span><?= htmlspecialchars($_SESSION['success_msg']) ?></span>
                </div>
                <?php unset($_SESSION['success_msg']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($_SESSION['error_msg']) ?></span>
                </div>
                <?php unset($_SESSION['error_msg']); ?>
            <?php endif; ?>

            <!-- Parent Details Form Card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <form method="POST" class="p-8">
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Title</label>
                            <select name="title" class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                                <?php
                                $titles = ['Mr.', 'Mrs.', 'Ms.', 'Dr.', 'Prof.'];
                                foreach($titles as $t) {
                                    $sel = ($parent['title'] === $t) ? 'selected' : '';
                                    echo "<option value=\"$t\" $sel>$t</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($parent['first_name']) ?>" class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($parent['last_name']) ?>" required class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Relationship</label>
                            <select name="relationship" class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                                <?php
                                $rels = ['mother'=>'Mother', 'father'=>'Father', 'guardian'=>'Guardian', 'other'=>'Other'];
                                foreach($rels as $val => $label) {
                                    $sel = ($parent['relationship'] === $val) ? 'selected' : '';
                                    echo "<option value=\"$val\" $sel>$label</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Phone Number</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($parent['phone']) ?>" class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Email Address</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($parent['email'] ?? '') ?>" class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Address / Place of Stay</label>
                        <textarea name="address" rows="2" class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"><?= htmlspecialchars($parent['address'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-8">
                        <label class="flex items-center gap-3 cursor-pointer p-4 rounded-xl border border-gray-200 hover:bg-gray-50 transition-colors">
                            <input type="checkbox" name="is_primary" value="1" <?= $parent['is_primary'] ? 'checked' : '' ?> class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300">
                            <div>
                                <div class="font-bold text-gray-900 text-sm">Receive SMS Notifications</div>
                                <div class="text-xs text-gray-500">Check this box if this parent should receive SMS communications like instant payment receipts and updates.</div>
                            </div>
                        </label>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-100">
                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-sm transition-all flex items-center gap-2">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Linked Students Card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mt-6 p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-2 flex items-center gap-2">
                    <i class="fas fa-users text-indigo-600"></i> Linked Students / Children
                </h2>
                <p class="text-xs text-gray-500 mb-6">Manage children linked to this parent for notifications and directory routing.</p>

                <!-- Link New Student Form -->
                <div class="bg-slate-50/30 p-5 rounded-xl border border-slate-100 mb-8">
                    <h3 class="font-bold text-sm text-gray-900 mb-4 flex items-center gap-1.5">
                        <i class="fas fa-plus-circle text-indigo-500"></i> Link Another Student
                    </h3>
                    <form method="POST" class="flex flex-col md:flex-row gap-4 items-end">
                        <div class="relative flex-1 w-full" id="searchable-student-select">
                            <label class="block text-[0.6875rem] font-black text-gray-400 uppercase tracking-wider mb-2">Select Student</label>
                            <div class="relative">
                                <input type="text" 
                                       id="student_search_input" 
                                       placeholder="Search by student name or ID..." 
                                       class="w-full bg-white border border-gray-200 text-gray-900 rounded-xl pl-10 pr-10 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all text-sm cursor-pointer" 
                                       autocomplete="off"
                                       required>
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                                    <i class="fas fa-search text-xs"></i>
                                </div>
                                <button type="button" 
                                        id="clear_student_search" 
                                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-gray-600 hidden">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                                <input type="hidden" name="student_to_link" id="student_to_link" required>
                            </div>
                            
                            <!-- Dropdown list -->
                            <div id="student_dropdown" 
                                 class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-xl max-h-60 overflow-y-auto hidden transform scale-95 opacity-0 transition-all duration-150 origin-top">
                                <div class="p-2 text-[10px] text-gray-400 uppercase font-black tracking-wider bg-gray-50 sticky top-0 border-b border-gray-100 flex items-center gap-1 z-10">
                                    <i class="fas fa-graduation-cap"></i> Select Student
                                </div>
                                <?php foreach ($all_students as $stu): ?>
                                    <div class="student-option px-4 py-3 hover:bg-indigo-50/80 cursor-pointer flex justify-between items-center transition-colors duration-150" 
                                         data-id="<?= $stu['id'] ?>" 
                                         data-search="<?= htmlspecialchars(strtolower($stu['first_name'] . ' ' . $stu['last_name'] . ' ' . $stu['class'])) ?>">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600 mr-3 font-extrabold text-xs">
                                                <?= strtoupper(substr($stu['first_name'] ?? '', 0, 1) . substr($stu['last_name'] ?? '', 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-gray-900 text-sm leading-tight"><?= htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']) ?></div>
                                                <div class="text-xs text-gray-500 mt-0.5">Class: <span class="font-bold text-indigo-600"><?= htmlspecialchars($stu['class']) ?></span></div>
                                            </div>
                                        </div>
                                        <span class="text-xs text-gray-400 font-bold px-2 py-1 bg-gray-50 rounded-lg border border-gray-100">ID: #<?= $stu['id'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <div id="no_students_found" class="px-4 py-8 text-center text-sm text-gray-400 font-semibold hidden">
                                    <i class="fas fa-user-slash mb-2 text-2xl text-gray-300 block"></i>
                                    No students match your search
                                </div>
                            </div>
                        </div>
                        <div class="w-full md:w-48">
                            <label class="block text-[0.6875rem] font-black text-gray-400 uppercase tracking-wider mb-2">Relationship</label>
                            <select name="link_relationship" required class="w-full bg-white border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all text-sm appearance-none cursor-pointer">
                                <option value="Mother">Mother</option>
                                <option value="Father">Father</option>
                                <option value="Guardian" selected>Guardian</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="w-full md:w-auto">
                            <button type="submit" name="link_student" class="w-full px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-sm shadow-sm transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-link"></i> Link Student
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Current Linked List -->
                <div class="pt-6 border-t border-gray-100">
                    <h3 class="font-bold text-sm text-gray-900 mb-4 flex items-center gap-1.5">
                        <i class="fas fa-list text-indigo-500"></i> Currently Linked Students
                    </h3>
                    <div class="space-y-3">
                        <?php if (!empty($linked_students)): ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm text-gray-600">
                                    <thead class="bg-gray-50 text-gray-900 text-xs uppercase font-bold tracking-wider">
                                        <tr>
                                            <th class="px-4 py-3 rounded-l-lg">Student Name</th>
                                            <th class="px-4 py-3">Class</th>
                                            <th class="px-4 py-3">Relationship</th>
                                            <th class="px-4 py-3 text-right rounded-r-lg">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach ($linked_students as $child): ?>
                                            <tr class="hover:bg-slate-50 transition-colors">
                                                <td class="px-4 py-3.5 font-bold text-gray-900">
                                                    <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
                                                </td>
                                                <td class="px-4 py-3.5 text-xs font-semibold text-gray-500">
                                                    <?= htmlspecialchars($child['class']) ?>
                                                </td>
                                                <td class="px-4 py-3.5">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[0.6875rem] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                                        <?= htmlspecialchars($child['relationship']) ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3.5 text-right">
                                                    <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to unlink this student?');">
                                                        <input type="hidden" name="unlink_student" value="<?= $child['id'] ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-950 font-black text-xs px-3 py-1.5 bg-red-50 hover:bg-red-100 rounded-lg transition-all">
                                                            <i class="fas fa-unlink mr-1"></i> Unlink
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-6 border border-dashed border-gray-200 rounded-xl bg-gray-50/50">
                                <i class="fas fa-users-slash text-2xl text-gray-300 mb-2"></i>
                                <p class="text-xs text-gray-400 font-bold">No students linked to this parent yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('student_search_input');
        const clearBtn = document.getElementById('clear_student_search');
        const hiddenInput = document.getElementById('student_to_link');
        const dropdown = document.getElementById('student_dropdown');
        const options = document.querySelectorAll('.student-option');
        const noResults = document.getElementById('no_students_found');

        function showDropdown() {
            dropdown.classList.remove('hidden');
            setTimeout(() => {
                dropdown.classList.remove('scale-95', 'opacity-0');
                dropdown.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function hideDropdown() {
            dropdown.classList.remove('scale-100', 'opacity-100');
            dropdown.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                dropdown.classList.add('hidden');
            }, 150);
        }

        searchInput.addEventListener('focus', function() {
            showDropdown();
        });

        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            let hasResults = false;

            if (query.length > 0) {
                clearBtn.classList.remove('hidden');
            } else {
                clearBtn.classList.add('hidden');
                hiddenInput.value = ''; // Reset ID if user clears input manually
            }

            options.forEach(opt => {
                const searchData = opt.getAttribute('data-search');
                if (searchData.includes(query)) {
                    opt.style.display = 'flex';
                    hasResults = true;
                } else {
                    opt.style.display = 'none';
                }
            });

            if (hasResults) {
                noResults.classList.add('hidden');
            } else {
                noResults.classList.remove('hidden');
            }

            showDropdown();
        });

        // Option selection
        options.forEach(opt => {
            opt.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.querySelector('.font-bold').textContent;
                const className = this.querySelector('.text-indigo-600').textContent;

                searchInput.value = `${name} (${className})`;
                hiddenInput.value = id;
                clearBtn.classList.remove('hidden');
                hideDropdown();
            });
        });

        // Clear search
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            hiddenInput.value = '';
            clearBtn.classList.add('hidden');
            options.forEach(opt => opt.style.display = 'flex');
            noResults.classList.add('hidden');
            searchInput.focus();
            showDropdown();
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!document.getElementById('searchable-student-select').contains(e.target)) {
                hideDropdown();
            }
        });
        
        // Prevent form submit on Enter key inside the search field
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                // Select first visible option if dropdown is open and there are results
                const visibleOption = Array.from(options).find(opt => opt.style.display !== 'none');
                if (visibleOption) {
                    visibleOption.click();
                }
            }
        });
    });
    </script>
</body>
</html>
