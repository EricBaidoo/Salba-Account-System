<?php
session_start();
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/staff_migration.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../includes/login.php'); exit;
}

run_staff_migration($conn);

$success = $_GET['success'] ?? '';
$search = trim($_GET['q'] ?? '');
$dept_filter = $_GET['dept'] ?? '';

$where = "WHERE 1=1";
if ($search) $where .= " AND (sp.full_name LIKE '%".  $conn->real_escape_string($search)."%' OR sp.job_title LIKE '%".$conn->real_escape_string($search)."%' OR sp.phone_number LIKE '%".$conn->real_escape_string($search)."%')";
if ($dept_filter) $where .= " AND sp.department = '".$conn->real_escape_string($dept_filter)."'";

$staff = $conn->query("
    SELECT sp.*, u.id as user_id, u.username, u.role as user_role 
    FROM staff_profiles sp 
    LEFT JOIN users u ON u.staff_id = sp.id 
    $where
    ORDER BY sp.full_name ASC
");

$dept_list = $conn->query("SELECT DISTINCT department FROM staff_profiles WHERE department != '' ORDER BY department");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Directory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50">

    <?php include '../../../includes/sidebar_admin.php'; ?>

    <main class="ml-72 min-h-screen p-8">

        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-id-card text-indigo-500"></i> Staff Directory
                </h1>
                <p class="text-gray-500 mt-1 font-medium">Manage HR profiles and system login access.</p>
            </div>
            <a href="add_staff.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-3 rounded-xl flex items-center gap-2 shadow-sm transition">
                <i class="fas fa-user-plus"></i> Add New Staff
            </a>
        </div>

        <?php if($success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-4 rounded-xl mb-6 flex items-center gap-3 font-bold">
                <i class="fas fa-check-circle text-emerald-500"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <form method="GET" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-6 flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[200px] relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, title, phone..." class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm font-medium bg-gray-50 focus:bg-white focus:border-indigo-400 outline-none transition">
            </div>
            <select name="dept" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium bg-gray-50 focus:border-indigo-400 outline-none">
                <option value="">All Departments</option>
                <?php if($dept_list) while($d = $dept_list->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($d['department']) ?>" <?= $dept_filter === $d['department'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['department']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="bg-indigo-600 text-white font-bold px-5 py-2 rounded-lg text-sm hover:bg-indigo-700 transition">Filter</button>
            <?php if($search || $dept_filter): ?>
                <a href="view_staff.php" class="text-sm font-bold text-gray-500 hover:text-red-500 transition px-2">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>

        <!-- Staff Grid -->
        <?php if($staff && $staff->num_rows > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <?php while($s = $staff->fetch_assoc()): 
                    $has_login = !empty($s['user_id']);
                    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $s['full_name'])));
                    $initials = substr($initials, 0, 2);
                ?>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition overflow-hidden group">
                    <div class="p-5 flex items-start gap-4">
                        <!-- Photo / Avatar -->
                        <div class="flex-shrink-0">
                            <?php if($s['photo_path']): ?>
                                <img src="../../../<?= htmlspecialchars($s['photo_path']) ?>" class="w-16 h-20 object-cover rounded-lg border border-gray-200" alt="">
                            <?php else: ?>
                                <div class="w-16 h-20 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-extrabold text-lg">
                                    <?= $initials ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-gray-900 text-base truncate"><?= htmlspecialchars($s['full_name'] ?? '') ?></h3>
                            <?php 
                                $code = $s['staff_code'] ?? '';
                                $valid_format = preg_match('/^SMIS\d{3,}-\d{2}$/', $code);
                            ?>
                            <?php if($code && $valid_format): ?>
                                <div class="inline-block text-[10px] font-black bg-indigo-600 text-white px-2 py-0.5 rounded tracking-widest mb-1"><?= htmlspecialchars($code) ?></div>
                            <?php elseif($code && !$valid_format): ?>
                                <div class="inline-flex items-center gap-1 text-[10px] font-black bg-orange-100 text-orange-700 border border-orange-300 px-2 py-0.5 rounded mb-1">
                                    <i class="fas fa-exclamation-triangle text-[8px]"></i> <?= htmlspecialchars($code) ?> (Non-standard)
                                </div>
                            <?php else: ?>
                                <div class="inline-flex items-center gap-1 text-[10px] font-black bg-red-100 text-red-600 border border-red-300 px-2 py-0.5 rounded mb-1">
                                    <i class="fas fa-times-circle text-[8px]"></i> No Staff ID
                                </div>
                            <?php endif; ?>
                            <p class="text-sm text-indigo-600 font-semibold"><?= htmlspecialchars(($s['job_title'] ?? '') ?: '—') ?></p>
                            <p class="text-xs text-gray-400 font-medium mt-0.5"><?= htmlspecialchars($s['department'] ?? '') ?></p>

                            <div class="mt-3 flex items-center gap-2">
                                <?php if($has_login): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold bg-green-100 text-green-700 px-2 py-1 rounded-full uppercase">
                                        <i class="fas fa-circle text-[6px]"></i> Login Active
                                    </span>
                                    <span class="text-[10px] text-gray-400 font-bold uppercase"><?= htmlspecialchars($s['user_role']) ?></span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold bg-gray-100 text-gray-500 px-2 py-1 rounded-full uppercase">
                                        <i class="fas fa-circle text-[6px]"></i> No System Login
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if(!empty($s['phone_number'])): ?>
                                <p class="text-xs text-gray-500 mt-2 flex items-center gap-1.5">
                                    <i class="fas fa-phone text-gray-300 text-[10px]"></i>
                                    <?= htmlspecialchars($s['phone_number']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Card Actions -->
                    <div class="px-5 pb-4 pt-0 flex gap-2 border-t border-gray-50 pt-3">
                        <a href="profile_staff.php?id=<?= $s['id'] ?>" class="flex-1 text-center text-xs font-bold py-2 bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100 transition">
                            <i class="fas fa-eye mr-1"></i> View Profile
                        </a>
                        <?php if(!$has_login): ?>
                            <a href="activate_login.php?id=<?= $s['id'] ?>" class="flex-1 text-center text-xs font-bold py-2 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition">
                                <i class="fas fa-key mr-1"></i> Activate Login
                            </a>
                        <?php else: ?>
                            <a href="reset_password.php?id=<?= $s['id'] ?>" class="flex-1 text-center text-xs font-bold py-2 bg-yellow-50 text-yellow-700 rounded-lg hover:bg-yellow-100 transition">
                                <i class="fas fa-unlock-alt mr-1"></i> Reset Password
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

        <?php else: ?>
            <div class="bg-white rounded-xl border border-dashed border-gray-200 p-16 text-center">
                <i class="fas fa-users text-6xl text-gray-200 mb-4"></i>
                <h3 class="text-xl font-bold text-gray-700 mb-2">No Staff Found</h3>
                <p class="text-gray-400 mb-6 font-medium">
                    <?= ($search || $dept_filter) ? 'No staff match your filter criteria.' : 'Add your first staff member to get started.' ?>
                </p>
                <a href="add_staff.php" class="inline-flex items-center gap-2 bg-indigo-600 text-white font-bold px-6 py-3 rounded-xl hover:bg-indigo-700 transition">
                    <i class="fas fa-user-plus"></i> Add New Staff
                </a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
