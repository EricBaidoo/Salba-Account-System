<?php
session_start();
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/staff_migration.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../login'); exit;
}
run_staff_migration($conn);

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: view_staff.php'); exit; }

$res = $conn->query("SELECT * FROM staff_profiles WHERE id = $id LIMIT 1");
if (!$res || $res->num_rows === 0) { header('Location: view_staff.php'); exit; }
$s = $res->fetch_assoc();

// Check no existing login
$check = $conn->query("SELECT id FROM users WHERE staff_id = $id LIMIT 1");
if ($check->num_rows > 0) {
    header("Location: profile_staff.php?id=$id");
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $role     = $_POST['role'] ?? 'facilitator';

    $allowed_roles = ['admin', 'supervisor', 'facilitator', 'staff', 'finance'];
    if (!$username)                               $errors[] = 'Username (Staff ID) is required.';
    if (!preg_match('/^SMIS\d{3,}-\d{2}$/', $username)) $errors[] = 'Username must follow the Staff ID format: SMIS001-25 (SMIS + number + dash + 2-digit year).';
    if (strlen($password) < 6)                   $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)                  $errors[] = 'Passwords do not match.';
    if (!in_array($role, $allowed_roles))         $errors[] = 'Invalid role selected.';

    if (empty($errors)) {
        $chk = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $chk->bind_param('s', $username);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) $errors[] = 'Username already taken. Choose another.';
        $chk->close();
    }

    if (empty($errors)) {
        // Fix role column first silently (migration already handles staff_id column)
        $conn->query("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'staff'");

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $conn->prepare("INSERT INTO users (username, password, role, staff_id) VALUES (?, ?, ?, ?)");
        $ins->bind_param('sssi', $username, $hash, $role, $id);
        if ($ins->execute()) {
            header("Location: profile_staff.php?id=$id&success=Login+activated+successfully");
            exit;
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
}

// Use Staff Code as the default login username
$join_year = !empty($s['first_appointment_date']) ? date('y', strtotime($s['first_appointment_date'])) : date('y');
$suggested_username = !empty($s['staff_code']) ? $s['staff_code'] : 'SMIS' . str_pad($id, 3, '0', STR_PAD_LEFT) . '-' . $join_year;
// Backfill staff_code if missing
if (empty($s['staff_code'])) {
    $conn->query("UPDATE staff_profiles SET staff_code = '$suggested_username' WHERE id = $id");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activate Login – <?= htmlspecialchars($s['full_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; } .fi { width:100%; padding:10px 14px; border:1.5px solid #e8e8f0; border-radius:10px; font-size:14px; font-weight:500; outline:none; transition:all .2s; background:#fafafa; } .fi:focus { border-color:#6366f1; background:white; box-shadow:0 0 0 3px rgba(99,102,241,.08); } .fl { display:block; font-size:11px; font-weight:700; color:#8b8fa8; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; }</style>
</head>
<body class="bg-gray-50">
    <?php include '../../../includes/sidebar.php'; ?>
    <main class="lg:ml-72 min-h-screen p-8 flex items-start justify-center">
        <div class="w-full max-w-lg">
            <a href="profile_staff.php?id=<?= $id ?>" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 uppercase tracking-wider flex items-center gap-1 mb-6 w-fit">
                <i class="fas fa-arrow-left"></i> Back to Profile
            </a>

            <!-- Staff Quick Info -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-6 flex items-center gap-4">
                <div class="w-12 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-extrabold text-lg flex-shrink-0">
                    <?= strtoupper(substr($s['full_name'], 0, 2)) ?>
                </div>
                <div>
                    <div class="font-extrabold text-gray-900"><?= htmlspecialchars($s['full_name']) ?></div>
                    <div class="text-sm text-indigo-600 font-semibold"><?= htmlspecialchars(($s['job_title'] ?? '') ?: 'Staff') ?></div>
                    <?php if(!empty($s['staff_code'])): ?>
                        <div class="text-xs font-black text-gray-400 mt-1 tracking-widest uppercase">
                            Staff ID: <span class="bg-indigo-600 text-white px-2 py-0.5 rounded"><?= htmlspecialchars($s['staff_code']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="ml-auto text-xs font-bold bg-yellow-50 text-yellow-700 border border-yellow-200 px-3 py-1.5 rounded-full">
                    <i class="fas fa-key mr-1"></i> Activating Login
                </div>
            </div>

            <?php if(!empty($errors)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm font-medium px-4 py-3 rounded-xl mb-5 space-y-1">
                    <?php foreach($errors as $e): ?><p><i class="fas fa-exclamation-circle mr-1"></i> <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="bg-green-50 px-6 py-4 border-b border-green-100">
                    <h2 class="font-extrabold text-green-900 flex items-center gap-2"><i class="fas fa-user-check text-green-500"></i> Activate System Login</h2>
                    <p class="text-xs text-green-700 mt-1 font-medium">This creates a login account linked to this staff profile.</p>
                </div>

                <form method="POST" class="p-6 space-y-5">
                    <div>
                        <label class="fl">Username (Staff ID)</label>
                        <input type="text" name="username" class="fi" value="<?= htmlspecialchars($_POST['username'] ?? $suggested_username) ?>" placeholder="Login username" autocomplete="off" required>
                        <p class="text-xs text-gray-400 mt-1 font-medium">
                            <i class="fas fa-id-badge text-indigo-400 mr-1"></i>
                            Default: <code class="bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded font-bold"><?= htmlspecialchars($suggested_username) ?></code> · Staff log in with their Staff ID
                        </p>
                    </div>

                    <div>
                        <label class="fl">Role / Access Level</label>
                        <select name="role" class="fi" required>
                            <option value="">-- Select Role --</option>
                            <?php 
                            $roles = [
                                'facilitator' => 'Facilitator – Attendance, Grades, Lesson Plans only',
                                'supervisor' => 'Supervisor – Reviews lesson plans, views all classes',
                                'finance' => 'Finance – Fee management and payments',
                                'staff' => 'General Staff – Basic dashboard access',
                                'admin' => 'Admin – Full system access',
                            ];
                            foreach($roles as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($_POST['role'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="fl">Initial Password</label>
                        <div class="relative">
                            <input type="password" name="password" id="pwd" class="fi pr-10" placeholder="Minimum 6 characters" required autocomplete="new-password">
                            <button type="button" onclick="togglePwd('pwd','ei1')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye" id="ei1"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="fl">Confirm Password</label>
                        <div class="relative">
                            <input type="password" name="confirm_password" id="cpwd" class="fi pr-10" placeholder="Repeat password" required autocomplete="new-password">
                            <button type="button" onclick="togglePwd('cpwd','ei2')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye" id="ei2"></i>
                            </button>
                        </div>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-800 font-medium">
                        <i class="fas fa-info-circle text-amber-500 mr-1"></i> Communicate this password to the staff member. They can change it themselves by going to <strong>My Profile → Change Password</strong> after first login.
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl transition shadow-sm flex items-center justify-center gap-2">
                            <i class="fas fa-user-check"></i> Create Login Account
                        </button>
                        <a href="profile_staff.php?id=<?= $id ?>" class="px-6 py-3 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition text-sm text-center">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <script>
        function togglePwd(fid, iid) {
            const f = document.getElementById(fid);
            const i = document.getElementById(iid);
            if (f.type === 'password') { f.type = 'text'; i.classList.replace('fa-eye','fa-eye-slash'); }
            else { f.type = 'password'; i.classList.replace('fa-eye-slash','fa-eye'); }
        }
    </script>
</body>
</html>
