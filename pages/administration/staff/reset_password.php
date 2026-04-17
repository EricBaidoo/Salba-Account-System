<?php
session_start();
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../login'); exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: view_staff.php'); exit; }

// Fetch staff + linked user
$res = $conn->query("
    SELECT sp.full_name, sp.job_title, u.id as user_id, u.username, u.role as user_role 
    FROM staff_profiles sp 
    LEFT JOIN users u ON u.staff_id = sp.id 
    WHERE sp.id = $id LIMIT 1
");
if (!$res || $res->num_rows === 0) { header('Location: view_staff.php'); exit; }
$s = $res->fetch_assoc();
if (empty($s['user_id'])) { header("Location: activate_login.php?id=$id"); exit; }

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pwd = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($new_pwd) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($new_pwd !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $hash = password_hash($new_pwd, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hash, $s['user_id']);
        if ($stmt->execute()) {
            header("Location: profile_staff.php?id=$id&success=Password+reset+successfully");
            exit;
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password – <?= htmlspecialchars($s['full_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; } .fi { width:100%; padding:10px 14px; border:1.5px solid #e8e8f0; border-radius:10px; font-size:14px; font-weight:500; outline:none; transition:all .2s; background:#fafafa; } .fi:focus { border-color:#f59e0b; background:white; box-shadow:0 0 0 3px rgba(245,158,11,.08); } .fl { display:block; font-size:11px; font-weight:700; color:#8b8fa8; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; }</style>
</head>
<body class="bg-gray-50">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen flex items-start justify-center">
        <div class="w-full max-w-lg">
            <a href="profile_staff.php?id=<?= $id ?>" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 uppercase tracking-wider flex items-center gap-1 mb-6 w-fit">
                <i class="fas fa-arrow-left"></i> Back to Profile
            </a>

            <!-- Staff Quick Info -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-6 flex items-center gap-4">
                <div class="w-12 h-14 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-lg flex items-center justify-center text-white font-extrabold text-lg flex-shrink-0">
                    <?= strtoupper(substr($s['full_name'], 0, 2)) ?>
                </div>
                <div>
                    <div class="font-extrabold text-gray-900"><?= htmlspecialchars($s['full_name']) ?></div>
                    <div class="text-sm text-gray-500 font-semibold">@<?= htmlspecialchars($s['username']) ?> · <span class="text-indigo-500"><?= htmlspecialchars($s['user_role']) ?></span></div>
                </div>
                <div class="ml-auto text-xs font-bold bg-yellow-50 text-yellow-700 border border-yellow-200 px-3 py-1.5 rounded-full"><i class="fas fa-unlock-alt mr-1"></i> Resetting Password</div>
            </div>

            <?php if(!empty($errors)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm font-medium px-4 py-3 rounded-xl mb-5 space-y-1">
                    <?php foreach($errors as $e): ?><p><i class="fas fa-exclamation-circle mr-1"></i> <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="bg-yellow-50 px-6 py-4 border-b border-yellow-100">
                    <h2 class="font-extrabold text-yellow-900 flex items-center gap-2"><i class="fas fa-key text-yellow-500"></i> Reset Password</h2>
                    <p class="text-xs text-yellow-800 mt-1 font-medium">Set a new password. Communicate it to the staff member directly.</p>
                </div>
                <form method="POST" class="p-6 space-y-5">
                    <div>
                        <label class="fl">New Password</label>
                        <div class="relative">
                            <input type="password" name="new_password" id="pwd1" class="fi pr-10" placeholder="Minimum 6 characters" required autocomplete="new-password">
                            <button type="button" onclick="togglePwd('pwd1','ei1')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye" id="ei1"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="fl">Confirm New Password</label>
                        <div class="relative">
                            <input type="password" name="confirm_password" id="pwd2" class="fi pr-10" placeholder="Re-enter password" required autocomplete="new-password">
                            <button type="button" onclick="togglePwd('pwd2','ei2')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye" id="ei2"></i>
                            </button>
                        </div>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-xs text-blue-800 font-medium">
                        <i class="fas fa-info-circle text-blue-400 mr-1"></i> After resetting, the staff member can log in with the new password and change it themselves via <strong>My Profile → Change Password</strong>.
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-3 rounded-xl transition shadow-sm flex items-center justify-center gap-2">
                            <i class="fas fa-save"></i> Reset Password
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
