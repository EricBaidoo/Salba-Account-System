<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

// Access Control - Only Admins can manage users
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../includes/login.php');
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
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, is_active, staff_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $username, $hashed_password, $role, $is_active, $staff_id);
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                // Double-link to staff profile for redundancy
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
            $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, is_active = ?, staff_id = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssiisi", $username, $role, $is_active, $staff_id, $hashed_password, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, is_active = ?, staff_id = ? WHERE id = ?");
            $stmt->bind_param("ssiii", $username, $role, $is_active, $staff_id, $user_id);
        }

        if ($stmt->execute()) {
            // Update staff link
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
            // Clear staff links first
            $conn->query("UPDATE staff_profiles SET user_id = NULL WHERE user_id = $user_id");
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

// Stats Calculation
$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$active_users = $conn->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetch_row()[0];
$admins_count = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetch_row()[0];
$linked_count = $conn->query("SELECT COUNT(*) FROM staff_profiles WHERE user_id IS NOT NULL")->fetch_row()[0];

// Fetch all users with linked staff info
$users_res = $conn->query("
    SELECT u.*, sp.full_name as staff_name, sp.id as staff_profile_id, sp.staff_code
    FROM users u 
    LEFT JOIN staff_profiles sp ON sp.user_id = u.id 
    ORDER BY u.created_at DESC
");
$users = $users_res->fetch_all(MYSQLI_ASSOC);

// Fetch staff who don't have a linked user yet
$available_staff_res = $conn->query("SELECT id, full_name, staff_code FROM staff_profiles WHERE user_id IS NULL ORDER BY full_name ASC");
$available_staff = $available_staff_res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management Control | SMS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #fbfcfe; }
        .glass-header { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border-bottom: 1px solid #f1f5f9; }
        .stat-card { background: white; border-radius: 20px; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        .user-table-container { background: white; border-radius: 24px; border: 1px solid #f1f5f9; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .action-btn { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .modal-blur { backdrop-filter: blur(8px); background: rgba(15, 23, 42, 0.6); }
        .form-input { width: 100%; padding: 12px 16px; border-radius: 12px; border: 1.5px solid #f1f5f9; background: #f8fafc; transition: all 0.2s; outline: none; font-size: 14px; font-weight: 500; }
        .form-input:focus { border-color: #6366f1; background: white; box-shadow: 0 0 0 4px rgba(99,102,241,0.1); }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body class="min-h-screen text-slate-800">

    <?php include '../../includes/sidebar_admin.php'; ?>

    <main class="ml-72 p-8 pt-6">

        <!-- Top Navigation / Search Area -->
        <div class="flex items-center justify-between mb-8 sticky top-0 z-10 glass-header py-4 -mx-8 px-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">System Users</h1>
                <div class="flex items-center gap-2 mt-0.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Access Control Center</span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="openModal('createModal')" class="bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 px-6 rounded-xl shadow-lg shadow-slate-200 transition-all flex items-center gap-2 text-sm">
                    <i class="fas fa-plus text-xs"></i> Create Account
                </button>
            </div>
        </div>

        <!-- Dashboard Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card p-6 flex items-center gap-5">
                <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-xl shadow-inner shadow-indigo-100/50">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h3 class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Total Users</h3>
                    <p class="text-2xl font-black text-slate-900 mt-0.5"><?= $total_users ?></p>
                </div>
            </div>
            <div class="stat-card p-6 flex items-center gap-5">
                <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl">
                    <i class="fas fa-user-check"></i>
                </div>
                <div>
                    <h3 class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Active Now</h3>
                    <p class="text-2xl font-black text-slate-900 mt-0.5"><?= $active_users ?></p>
                </div>
            </div>
            <div class="stat-card p-6 flex items-center gap-5">
                <div class="w-14 h-14 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center text-xl">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div>
                    <h3 class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Administrators</h3>
                    <p class="text-2xl font-black text-slate-900 mt-0.5"><?= $admins_count ?></p>
                </div>
            </div>
            <div class="stat-card p-6 flex items-center gap-5">
                <div class="w-14 h-14 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-xl">
                    <i class="fas fa-link"></i>
                </div>
                <div>
                    <h3 class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Linked Profiles</h3>
                    <p class="text-2xl font-black text-slate-900 mt-0.5"><?= $linked_count ?></p>
                </div>
            </div>
        </div>

        <!-- Feedback Messages -->
        <?php if ($success): ?>
            <div class="bg-emerald-50 border border-emerald-100 text-emerald-800 px-5 py-4 rounded-2xl mb-8 flex items-center gap-3 animate-in fade-in slide-in-from-top-4 duration-300">
                <i class="fas fa-circle-check text-emerald-500"></i>
                <span class="text-sm font-bold tracking-tight"><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-100 text-rose-800 px-5 py-4 rounded-2xl mb-8 flex items-center gap-3">
                <i class="fas fa-circle-exclamation text-rose-500"></i>
                <span class="text-sm font-bold tracking-tight"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Content Area -->
        <div class="user-table-container overflow-hidden">
            <div class="p-6 border-b border-slate-50 flex items-center justify-between">
                <h2 class="font-extrabold text-slate-800">Account Directory</h2>
                <div class="relative">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="userSearch" placeholder="Search accounts..." class="bg-slate-50 border border-slate-100 rounded-xl py-2 pl-10 pr-4 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-indigo-500/10 transition-all w-64">
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/50">
                        <tr>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">System User</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">Privileges</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">Linked Personnel</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">Current Status</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($users as $user): 
                            $initials = strtoupper(substr($user['username'], 0, 2));
                            $colors = ['bg-indigo-500', 'bg-emerald-500', 'bg-rose-500', 'bg-amber-500', 'bg-purple-500', 'bg-sky-500'];
                            $avatar_bg = $colors[ord($user['username'][0]) % count($colors)];
                        ?>
                        <tr class="hover:bg-slate-50/70 transition-colors group">
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 <?= $avatar_bg ?> text-white rounded-xl flex items-center justify-center font-black text-xs shadow-lg shadow-<?= str_replace('bg-', '', $avatar_bg) ?>/20 ring-4 ring-white">
                                        <?= $initials ?>
                                    </div>
                                    <div>
                                        <div class="text-sm font-black text-slate-900">@<?= htmlspecialchars($user['username']) ?></div>
                                        <div class="text-[10px] font-bold text-slate-400 mt-0.5">UID: ACC-<?= str_pad($user['id'], 4, '0', STR_PAD_LEFT) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex">
                                    <?php 
                                        $role_class = [
                                            'admin' => 'bg-purple-50 text-purple-700 border-purple-100',
                                            'supervisor' => 'bg-blue-50 text-blue-700 border-blue-100',
                                            'facilitator' => 'bg-emerald-50 text-emerald-700 border-emerald-100'
                                        ][$user['role']] ?? 'bg-slate-50 text-slate-700 border-slate-100';
                                    ?>
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border <?= $role_class ?>">
                                        <?= $user['role'] ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <?php if($user['staff_name']): ?>
                                    <a href="staff/profile_staff.php?id=<?= $user['staff_profile_id'] ?>" class="flex items-center gap-2 group/link">
                                        <div class="text-xs font-bold text-slate-700 group-hover/link:text-indigo-600 transition-colors"><?= htmlspecialchars($user['staff_name']) ?></div>
                                        <div class="text-[10px] font-bold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded"><?= $user['staff_code'] ?></div>
                                    </a>
                                <?php else: ?>
                                    <span class="text-xs font-bold text-slate-300 italic tracking-tight">Access not linked</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-2">
                                    <div class="w-1.5 h-1.5 rounded-full <?= $user['is_active'] ? 'bg-emerald-500' : 'bg-slate-300' ?>"></div>
                                    <span class="text-[11px] font-black uppercase tracking-widest <?= $user['is_active'] ? 'text-emerald-600' : 'text-slate-400' ?>">
                                        <?= $user['is_active'] ? 'Active' : 'Locked' ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex items-center justify-end gap-2 pr-2">
                                    <button onclick='editUser(<?= json_encode($user) ?>)' class="action-btn bg-white border border-slate-100 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 hover:border-indigo-100">
                                        <i class="fas fa-sliders text-xs"></i>
                                    </button>
                                    <button onclick='confirmDelete(<?= $user['id'] ?>, "<?= htmlspecialchars($user['username']) ?>")' class="action-btn bg-white border border-slate-100 text-slate-400 hover:text-rose-600 hover:bg-rose-50 hover:border-rose-100">
                                        <i class="fas fa-trash-can text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="p-6 bg-slate-50/50 border-t border-slate-50 flex items-center justify-between">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Showing all registered system users</p>
                <div class="flex gap-2">
                     <!-- Pagination would go here -->
                </div>
            </div>
        </div>

    </main>

    <!-- Modals (Create/Edit/Delete) -->

    <!-- Create Account Modal -->
    <div id="createModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 modal-blur" onclick="closeModal('createModal')"></div>
        <div class="bg-white w-full max-w-[480px] rounded-[32px] shadow-2xl relative overflow-hidden animate-in zoom-in-95 duration-200">
            <div class="p-8 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-black text-slate-900 tracking-tight">New Credentials</h3>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Grant Internal System Access</p>
                </div>
                <button onclick="closeModal('createModal')" class="w-8 h-8 rounded-full hover:bg-slate-200 flex items-center justify-center text-slate-400 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="p-8 space-y-6">
                <input type="hidden" name="action" value="create">
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2 px-1">Username</label>
                            <input type="text" name="username" placeholder="e.g. JohnD" required class="form-input">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2 px-1">Access Role</label>
                            <select name="role" class="form-input cursor-pointer">
                                <option value="facilitator">Facilitator</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2 px-1">Password</label>
                        <input type="password" name="password" placeholder="••••••••••••" required class="form-input">
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2 px-1">Link to Staff Member</label>
                        <select name="staff_id" class="form-input cursor-pointer">
                            <option value="">-- No Linked Personnel --</option>
                            <?php foreach ($available_staff as $st): ?>
                                <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['full_name']) ?> (<?= $st['staff_code'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex items-center gap-3 p-4 bg-emerald-50/50 rounded-2xl border border-emerald-50">
                        <input type="checkbox" name="is_active" id="create_active" checked class="w-5 h-5 rounded-lg text-emerald-600 border-emerald-200 focus:ring-emerald-500">
                        <label for="create_active" class="text-xs font-black text-emerald-800 uppercase tracking-widest">Activate Account Immediately</label>
                    </div>
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="button" onclick="closeModal('createModal')" class="flex-1 py-4 font-black text-slate-400 uppercase tracking-[0.15em] text-xs hover:text-slate-600 transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 py-4 bg-slate-900 text-white font-black rounded-2xl shadow-xl shadow-slate-200 uppercase tracking-[0.15em] text-xs hover:bg-slate-800 active:scale-[0.98] transition-all">Establish Access</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal (Simplified logic, Rich UI) -->
    <div id="editModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 modal-blur" onclick="closeModal('editModal')"></div>
        <div class="bg-white w-full max-w-[480px] rounded-[32px] shadow-2xl relative overflow-hidden">
            <div class="p-8 border-b border-indigo-50 bg-indigo-50/30 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-black text-indigo-900 tracking-tight">Security Override</h3>
                    <p class="text-[11px] font-bold text-indigo-400 uppercase tracking-widest mt-0.5">Manage Account Level: <span id="e_user_display" class="text-indigo-600 font-black"></span></p>
                </div>
                <button onclick="closeModal('editModal')" class="w-8 h-8 rounded-full hover:bg-indigo-100 flex items-center justify-center text-indigo-400 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="p-8 space-y-6">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Username</label>
                            <input type="text" name="username" id="edit_username" required class="form-input">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Role Type</label>
                            <select name="role" id="edit_role" class="form-input">
                                <option value="facilitator">Facilitator</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1 px-1">Security Key Update</label>
                        <p class="text-[9px] text-slate-400 font-medium mb-2 pl-1">Leave blank unless you want to reset their password</p>
                        <input type="password" name="new_password" placeholder="Enter new strong password" class="form-input">
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2 px-1">Associated Registry Profile</label>
                        <select name="staff_id" id="edit_staff_id" class="form-input cursor-pointer">
                            <option value="">-- No Registry Link --</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <input type="checkbox" name="is_active" id="edit_is_active" class="w-5 h-5 rounded-lg text-indigo-600 border-slate-200 focus:ring-indigo-500">
                        <label for="edit_is_active" class="text-xs font-black text-slate-600 uppercase tracking-widest">Account is Authorised</label>
                    </div>
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="button" onclick="closeModal('editModal')" class="flex-1 py-4 font-black text-slate-400 uppercase tracking-[0.15em] text-xs transition-colors">Abort</button>
                    <button type="submit" class="flex-1 py-4 bg-indigo-600 text-white font-black rounded-2xl shadow-xl shadow-indigo-200 uppercase tracking-[0.15em] text-xs hover:bg-indigo-700 active:scale-[0.98] transition-all">Confirm Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Final Choice Confirmation -->
    <div id="deleteModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 modal-blur" onclick="closeModal('deleteModal')"></div>
        <div class="bg-white w-full max-w-[400px] rounded-[40px] shadow-2xl relative p-10 text-center animate-in zoom-in-95 duration-200">
            <div class="w-20 h-20 bg-rose-50 text-rose-500 rounded-3xl flex items-center justify-center text-3xl mx-auto mb-6 shadow-inner ring-8 ring-rose-50/50">
                <i class="fas fa-user-slash"></i>
            </div>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight mb-2">Terminating Access</h3>
            <p class="text-slate-500 text-sm font-medium mb-8">This will permanently delete the system account for <strong id="delete_username" class="text-slate-900"></strong>. Are you absolutely certain?</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="flex flex-col gap-3">
                    <button type="submit" class="w-full py-4 bg-rose-600 text-white font-black rounded-2xl shadow-lg shadow-rose-200 uppercase tracking-[0.15em] text-xs hover:bg-rose-700 transition-all">Yes, Revoke Permanently</button>
                    <button type="button" onclick="closeModal('deleteModal')" class="w-full py-4 font-black text-slate-400 uppercase tracking-[0.15em] text-xs hover:text-slate-600 transition-colors">Nevermind</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('e_user_display').textContent = '@' + user.username;
            document.getElementById('edit_is_active').checked = parseInt(user.is_active) === 1;

            const staffSelect = document.getElementById('edit_staff_id');
            staffSelect.innerHTML = '<option value="">-- No Registry Link --</option>';
            
            if (user.staff_name) {
                const opt = document.createElement('option');
                opt.value = user.staff_profile_id;
                opt.textContent = `${user.staff_name} (${user.staff_code})`;
                opt.selected = true;
                staffSelect.appendChild(opt);
            }

            const available = <?= json_encode($available_staff) ?>;
            available.forEach(st => {
                const opt = document.createElement('option');
                opt.value = st.id;
                opt.textContent = `${st.full_name} (${st.staff_code})`;
                staffSelect.appendChild(opt);
            });

            openModal('editModal');
        }

        function confirmDelete(id, username) {
            document.getElementById('delete_user_id').value = id;
            document.getElementById('delete_username').textContent = '@' + username;
            openModal('deleteModal');
        }

        // Search Logic
        document.getElementById('userSearch').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });

        // Close on esc
        window.addEventListener('keydown', e => { if(e.key === 'Escape') {
            document.querySelectorAll('[id$="Modal"]').forEach(m => closeModal(m.id));
        }});
    </script>
</body>
</html>
