<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';

// Access Control - Only Admins can manage users
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php?error=unauthorized_access');
    exit;
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Handle Create/Update User
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? 'staff';
    $staff_id = !empty($_POST['staff_id']) ? intval($_POST['staff_id']) : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($action === 'create') {
        $password = $_POST['password'] ?? '';
        if (empty($username) || empty($password)) {
            $error = "Username and password are required.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $username, $hashed_password, $role, $is_active);
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                // Link to staff if selected
                if ($staff_id) {
                    $upd = $conn->prepare("UPDATE staff_profiles SET user_id = ? WHERE id = ?");
                    $upd->bind_param("ii", $user_id, $staff_id);
                    $upd->execute();
                }
                header("Location: users.php?success=User+created+successfully");
                exit;
            } else {
                $error = "Failed to create user: " . $conn->error;
            }
        }
    } elseif ($action === 'edit') {
        $user_id = intval($_POST['user_id']);
        $new_password = $_POST['new_password'] ?? '';
        
        if ($new_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, is_active = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssisi", $username, $role, $is_active, $hashed_password, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("ssii", $username, $role, $is_active, $user_id);
        }

        if ($stmt->execute()) {
            // Update staff link if changed
            // First clear old link
            $conn->query("UPDATE staff_profiles SET user_id = NULL WHERE user_id = $user_id");
            if ($staff_id) {
                $upd = $conn->prepare("UPDATE staff_profiles SET user_id = ? WHERE id = ?");
                $upd->bind_param("ii", $user_id, $staff_id);
                $upd->execute();
            }
            header("Location: users.php?success=User+updated+successfully");
            exit;
        } else {
            $error = "Failed to update user: " . $conn->error;
        }
    } elseif ($action === 'delete') {
        $user_id = intval($_POST['user_id']);
        if ($user_id === $_SESSION['user_id']) {
            $error = "You cannot delete your own account.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                header("Location: users.php?success=User+deleted+successfully");
                exit;
            } else {
                $error = "Failed to delete user.";
            }
        }
    }
}

// Fetch all users with linked staff info
$users_res = $conn->query("
    SELECT u.*, sp.full_name as staff_name, sp.id as staff_profile_id 
    FROM users u 
    LEFT JOIN staff_profiles sp ON sp.user_id = u.id 
    ORDER BY u.created_at DESC
");
$users = $users_res->fetch_all(MYSQLI_ASSOC);

// Fetch staff who don't have a linked user yet
$available_staff_res = $conn->query("SELECT id, full_name FROM staff_profiles WHERE user_id IS NULL ORDER BY full_name ASC");
$available_staff = $available_staff_res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | SALBA Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/sidebar_admin.php'; ?>

    <main class="ml-72 min-h-screen p-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">User Management</h1>
                <p class="text-gray-500 font-medium mt-1">Control system access, roles, and security credentials.</p>
            </div>
            <button onclick="openModal('createModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-lg transition-all transform hover:scale-105 flex items-center gap-2">
                <i class="fas fa-user-plus"></i> Create New User
            </button>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r-xl shadow-sm flex items-center gap-3">
                <i class="fas fa-check-circle"></i>
                <span class="font-medium"><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-xl shadow-sm flex items-center gap-3">
                <i class="fas fa-exclamation-circle"></i>
                <span class="font-medium"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-gray-50 border-bottom border-gray-100">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">User / Username</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Linked Staff</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold">
                                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                </div>
                                <div class="font-bold text-gray-900"><?= htmlspecialchars($user['username']) ?></div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                                <?= $user['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : 
                                   ($user['role'] === 'supervisor' ? 'bg-blue-100 text-blue-700' : 
                                   ($user['role'] === 'facilitator' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700')) ?>">
                                <?= htmlspecialchars($user['role']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-600">
                            <?= $user['staff_name'] ? htmlspecialchars($user['staff_name']) : '<span class="text-gray-300">Not Linked</span>' ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="flex items-center gap-2 text-sm font-bold <?= $user['is_active'] ? 'text-green-600' : 'text-red-500' ?>">
                                <span class="w-2 h-2 rounded-full <?= $user['is_active'] ? 'bg-green-500' : 'bg-red-500' ?>"></span>
                                <?= $user['is_active'] ? 'Active' : 'Disabled' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 font-medium">
                            <?= date('M d, Y', strtotime($user['created_at'])) ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button onclick='editUser(<?= json_encode($user) ?>)' class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick='confirmDelete(<?= $user['id'] ?>, "<?= htmlspecialchars($user['username']) ?>")' class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Delete User">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Create Modal -->
    <div id="createModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-gray-900 opacity-50"></div>
        <div class="modal-container bg-white w-full max-w-md mx-auto rounded-2xl shadow-2xl z-50 overflow-y-auto transform scale-95 transition-transform">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between bg-gray-50 rounded-t-2xl">
                <h3 class="text-lg font-bold text-gray-900">Create New User</h3>
                <button onclick="closeModal('createModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="create">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Username</label>
                    <input type="text" name="username" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all outline-none font-medium">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Password</label>
                    <input type="password" name="password" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all outline-none font-medium">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">System Role</label>
                    <select name="role" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all outline-none font-medium cursor-pointer">
                        <option value="admin">Administrator</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="facilitator">Facilitator (Teaching Staff)</option>
                        <option value="staff">Other Staff</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Link Staff Profile</label>
                    <select name="staff_id" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all outline-none font-medium cursor-pointer">
                        <option value="">-- No Link --</option>
                        <?php foreach ($available_staff as $st): ?>
                            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-center gap-3 py-2">
                    <input type="checkbox" name="is_active" id="create_is_active" checked class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <label for="create_is_active" class="text-sm font-bold text-gray-700">Account Active</label>
                </div>
                <div class="pt-4 flex gap-3">
                    <button type="button" onclick="closeModal('createModal')" class="flex-1 py-3 border border-gray-200 font-bold rounded-xl text-gray-500 hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" class="flex-1 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-md transition">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-gray-900 opacity-50"></div>
        <div class="modal-container bg-white w-full max-w-md mx-auto rounded-2xl shadow-2xl z-50 overflow-y-auto transform scale-95 transition-transform">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between bg-indigo-50 rounded-t-2xl">
                <h3 class="text-lg font-bold text-indigo-900">Edit User Account</h3>
                <button onclick="closeModal('editModal')" class="text-indigo-400 hover:text-indigo-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Username</label>
                    <input type="text" name="username" id="edit_username" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all outline-none font-medium">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">New Password (Leave blank to keep current)</label>
                    <input type="password" name="new_password" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all outline-none font-medium">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">System Role</label>
                    <select name="role" id="edit_role" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all outline-none font-medium">
                        <option value="admin">Administrator</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="facilitator">Facilitator (Teaching Staff)</option>
                        <option value="staff">Other Staff</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Linked Staff Profile</label>
                    <select name="staff_id" id="edit_staff_id" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all outline-none font-medium">
                        <option value="">-- No Link --</option>
                        <!-- Existing staff who are already linked will be added via JS -->
                    </select>
                    <p class="text-[10px] text-gray-400 mt-1 font-medium">Staff already with accounts don't show here unless linked to this user.</p>
                </div>
                <div class="flex items-center gap-3 py-2">
                    <input type="checkbox" name="is_active" id="edit_is_active" class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <label for="edit_is_active" class="text-sm font-bold text-gray-700">Account Active</label>
                </div>
                <div class="pt-4 flex gap-3">
                    <button type="button" onclick="closeModal('editModal')" class="flex-1 py-3 border border-gray-200 font-bold rounded-xl text-gray-500 hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" class="flex-1 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-md transition">Update Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-gray-900 opacity-50"></div>
        <div class="modal-container bg-white w-full max-w-sm mx-auto rounded-2xl shadow-2xl z-50 overflow-y-auto transform scale-95 transition-transform">
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                    <i class="fas fa-trash"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Delete User Account?</h3>
                <p class="text-gray-500 text-sm mb-6">Are you sure you want to delete <span id="delete_username" class="font-bold text-gray-900"></span>? This action cannot be undone.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <div class="flex gap-3">
                        <button type="button" onclick="closeModal('deleteModal')" class="flex-1 py-2.5 border border-gray-200 font-bold rounded-xl text-gray-500 hover:bg-gray-50 transition">Cancel</button>
                        <button type="submit" class="flex-1 py-2.5 bg-red-600 text-white font-bold rounded-xl hover:bg-red-700 transition">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) {
            const modal = document.getElementById(id);
            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.querySelector('.modal-container').classList.remove('scale-95');
            modal.querySelector('.modal-container').classList.add('scale-100');
            document.body.classList.add('modal-active');
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            modal.classList.add('opacity-0', 'pointer-events-none');
            modal.querySelector('.modal-container').classList.add('scale-95');
            modal.querySelector('.modal-container').classList.remove('scale-100');
            document.body.classList.remove('modal-active');
        }

        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_is_active').checked = parseInt(user.is_active) === 1;

            // Update Staff Dropdown in Edit Modal
            const staffSelect = document.getElementById('edit_staff_id');
            // Reset to available staff original state first if needed, or just append current
            staffSelect.innerHTML = '<option value="">-- No Link --</option>';
            
            // Add currently linked staff if exists
            if (user.staff_name) {
                const opt = document.createElement('option');
                opt.value = user.staff_profile_id;
                opt.textContent = user.staff_name;
                opt.selected = true;
                staffSelect.appendChild(opt);
            }

            // Append all other available staff (fromPHP $available_staff)
            const available = <?= json_encode($available_staff) ?>;
            available.forEach(st => {
                const opt = document.createElement('option');
                opt.value = st.id;
                opt.textContent = st.full_name;
                staffSelect.appendChild(opt);
            });

            openModal('editModal');
        }

        function confirmDelete(id, username) {
            document.getElementById('delete_user_id').value = id;
            document.getElementById('delete_username').textContent = username;
            openModal('deleteModal');
        }

        // Close on escape
        document.onkeydown = function(evt) {
            evt = evt || window.event;
            if (evt.keyCode == 27) {
                const modals = document.querySelectorAll('.modal:not(.opacity-0)');
                modals.forEach(m => closeModal(m.id));
            }
        };
    </script>
</body>
</html>
