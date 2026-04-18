<?php
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../login');
    exit;
}

$uid = $_SESSION['user_id'];
$profile = get_user_profile_data($conn, $uid);

// Handle Password Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new !== $confirm) {
        redirect('profile.php', 'error', 'New passwords do not match.');
    } else {
        $result = update_user_password($conn, $uid, $current, $new);
        if ($result['success']) {
            redirect('profile.php', 'success', 'Password updated successfully.');
        } else {
            redirect('profile.php', 'error', $result['message']);
        }
    }
}

// Handle Photo Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $result = upload_user_photo($conn, $uid, $_FILES['profile_photo']);
    if ($result['success']) {
        redirect('profile.php', 'success', 'Profile picture updated successfully.');
    } else {
        redirect('profile.php', 'error', $result['message']);
    }
}

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?= htmlspecialchars($school_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        }
        .profile-gradient {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <?php include '../../includes/top_nav.php'; ?>

    <div class="max-w-6xl mx-auto px-4 py-12">
        <div class="flex flex-col lg:flex-row gap-8">
            
            <!-- Left Side: Profile Overview -->
            <div class="lg:w-1/3">
                <div class="glass-card rounded-3xl overflow-hidden sticky top-32">
                    <div class="profile-gradient h-32 relative">
                        <div class="absolute -bottom-12 left-1/2 -translate-x-1/2">
                            <form id="photoForm" action="" method="POST" enctype="multipart/form-data" class="relative group/avatar">
                                <div class="w-24 h-24 rounded-2xl bg-white p-1 shadow-xl overflow-hidden relative border border-white">
                                    <?php if (!empty($profile['photo_path']) && file_exists('../../' . $profile['photo_path'])): ?>
                                        <img src="../../<?= htmlspecialchars($profile['photo_path']) ?>" alt="Avatar" class="w-full h-full object-cover rounded-xl">
                                    <?php else: ?>
                                        <div class="w-full h-full bg-slate-100 rounded-xl flex items-center justify-center text-slate-400">
                                            <i class="fas fa-user text-3xl"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Upload Overlay -->
                                    <label for="photoInput" class="absolute inset-0 bg-black/60 opacity-0 group-hover/avatar:opacity-100 transition-opacity flex flex-col items-center justify-center cursor-pointer text-white">
                                        <i class="fas fa-camera text-xl mb-1"></i>
                                        <span class="text-[8px] font-black uppercase tracking-widest">Update</span>
                                        <input type="file" name="profile_photo" id="photoInput" class="hidden" accept="image/*" onchange="this.form.submit()">
                                    </label>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="pt-16 pb-8 px-8 text-center">
                        <h2 class="text-2xl font-black text-slate-900 tracking-tight"><?= htmlspecialchars($profile['full_name'] ?? $profile['username']) ?></h2>
                        <div class="mt-1">
                            <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-indigo-100">
                                <?= strtoupper($profile['role'] ?? 'Staff') ?>
                            </span>
                        </div>
                        
                        <div class="mt-8 grid grid-cols-1 gap-4 text-left">
                            <div class="p-4 bg-slate-50/50 rounded-2xl border border-slate-100">
                                <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">Department</p>
                                <p class="text-sm font-bold text-slate-700"><?= htmlspecialchars($profile['department'] ?? 'General') ?></p>
                            </div>
                            <div class="p-4 bg-slate-50/50 rounded-2xl border border-slate-100">
                                <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">Staff Role</p>
                                <p class="text-sm font-bold text-slate-700"><?= htmlspecialchars($profile['staff_role'] ?? 'Not Assigned') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Details & Actions -->
            <div class="flex-1 space-y-8">
                
                <!-- Information Section -->
                <div class="glass-card rounded-3xl p-8 md:p-10">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-500">
                            <i class="fas fa-id-card text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-slate-900 tracking-tight">Personal Information</h3>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Official Employment Records</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">Username</label>
                            <p class="px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl text-slate-900 font-bold"><?= htmlspecialchars($profile['username']) ?></p>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">Email Address</label>
                            <p class="px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl text-slate-900 font-bold"><?= htmlspecialchars($profile['email'] ?? 'Not Set') ?></p>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">Phone Number</label>
                            <p class="px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl text-slate-900 font-bold"><?= htmlspecialchars($profile['phone'] ?? 'Not Set') ?></p>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">Account Since</label>
                            <p class="px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl text-slate-900 font-bold"><?= date('M j, Y', strtotime($profile['account_created'])) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Security Section -->
                <div class="glass-card rounded-3xl p-8 md:p-10">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-12 h-12 bg-rose-50 rounded-2xl flex items-center justify-center text-rose-500">
                            <i class="fas fa-shield-halved text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-slate-900 tracking-tight">Security & Password</h3>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Update your account credentials</p>
                        </div>
                    </div>

                    <form action="" method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">Current Password</label>
                                <input type="password" name="current_password" required class="w-full px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-bold text-slate-900" placeholder="••••••••">
                            </div>
                            <div>
                                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">New Password</label>
                                <input type="password" name="new_password" required class="w-full px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-bold text-slate-900" placeholder="••••••••">
                            </div>
                            <div>
                                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">Confirm New Password</label>
                                <input type="password" name="confirm_password" required class="w-full px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-bold text-slate-900" placeholder="••••••••">
                            </div>
                        </div>

                        <!-- Password Rule Requirements -->
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100 flex flex-wrap gap-x-8 gap-y-3">
                            <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400">
                                <i class="fas fa-circle-check text-indigo-500 text-[8px]"></i> MIN. 8 CHARACTERS
                            </div>
                            <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400">
                                <i class="fas fa-circle-check text-indigo-500 text-[8px]"></i> ONE UPPERCASE
                            </div>
                            <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400">
                                <i class="fas fa-circle-check text-indigo-500 text-[8px]"></i> ONE NUMBER
                            </div>
                            <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400">
                                <i class="fas fa-circle-check text-indigo-500 text-[8px]"></i> ONE SYMBOL (@, #, !, $)
                            </div>
                        </div>
                        <div class="flex justify-end pt-4">
                            <button type="submit" name="reset_password" class="px-8 py-4 bg-slate-900 text-white rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-slate-800 transition-all hover:scale-[1.02] active:scale-95 shadow-xl shadow-slate-900/10">
                                Update Password <i class="fas fa-key ml-2 opacity-50"></i>
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

</body>
</html>
