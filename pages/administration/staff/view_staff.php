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

// Filtering Logic
$tab = $_GET['tab'] ?? 'all';
$dept_filter = $_GET['dept'] ?? '';
$search = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? 'all'; // Default to all statuses

$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (sp.full_name LIKE '%" . $conn->real_escape_string($search) . "%' OR sp.job_title LIKE '%" . $conn->real_escape_string($search) . "%' OR sp.staff_code LIKE '%" . $conn->real_escape_string($search) . "%')";
}

if ($tab === 'teaching') {
    $where .= " AND sp.staff_type LIKE '%teaching%'";
} elseif ($tab === 'non-teaching') {
    $where .= " AND sp.staff_type LIKE '%non-teaching%'";
}

if ($status_filter !== 'all') {
    $where .= " AND sp.employment_status = '" . $conn->real_escape_string($status_filter) . "'";
}

$staff = $conn->query("
    SELECT sp.*, u.id as user_id, u.username, u.role as user_role 
    FROM staff_profiles sp 
    LEFT JOIN users u ON u.id = sp.user_id 
    $where
    ORDER BY sp.full_name ASC
");

// Fetch Real-Time Stats for Cards
$total_count = $conn->query("SELECT COUNT(*) FROM staff_profiles")->fetch_row()[0];
$teaching_count = $conn->query("SELECT COUNT(*) FROM staff_profiles WHERE staff_type LIKE '%teaching%'")->fetch_row()[0];
$non_teaching_count = $conn->query("SELECT COUNT(*) FROM staff_profiles WHERE staff_type LIKE '%non-teaching%'")->fetch_row()[0];
$male_count = $conn->query("SELECT COUNT(*) FROM staff_profiles WHERE gender = 'Male'")->fetch_row()[0];
$female_count = $conn->query("SELECT COUNT(*) FROM staff_profiles WHERE gender = 'Female'")->fetch_row()[0];

$dept_list = $conn->query("SELECT DISTINCT department FROM staff_profiles WHERE department != '' ORDER BY department");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personnel Directory | HR Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass-header { backdrop-filter: blur(12px); background: rgba(255, 255, 255, 0.9); }
        .staff-row:hover { background-color: rgba(249, 250, 251, 1); }
        .status-pulse { animation: pulse-custom 2s infinite; }
        @keyframes pulse-custom {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
    </style>
</head>
<body class="bg-gray-50">

    <?php include '../../../includes/sidebar_admin.php'; ?>

    <main class="ml-72 min-h-screen p-8">

        <!-- Header -->
        <div class="flex items-center justify-between mb-10">
            <div>
                <h1 class="text-4xl font-extrabold text-gray-900 flex items-center gap-4">
                    <i class="fas fa-users-viewfinder text-indigo-600"></i> Personnel Directory
                </h1>
                <p class="text-gray-500 mt-2 font-medium">Manage institutional human resources and system access credentials.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="bulk_upload_staff.php" class="bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold px-6 py-4 rounded-2xl flex items-center gap-3 hover:bg-emerald-100 transition-all">
                    <i class="fas fa-file-import text-lg"></i> Bulk Import
                </a>
                <a href="add_staff.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-8 py-4 rounded-2xl flex items-center gap-3 shadow-lg shadow-indigo-100 transition-all hover:-translate-y-1">
                    <i class="fas fa-plus-circle text-lg"></i> Register New Staff
                </a>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-6 mb-10">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex flex-col items-center text-center group hover:border-indigo-500 transition-all duration-300">
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl mb-3 shadow-inner group-hover:rotate-6 transition-transform">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="text-2xl font-black text-gray-900"><?= $total_count ?></div>
                <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-1">Personnel</div>
            </div>
            
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex flex-col items-center text-center group hover:border-emerald-500 transition-all duration-300">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-xl mb-3 shadow-inner group-hover:rotate-6 transition-transform">
                    <i class="fas fa-chalkboard-user"></i>
                </div>
                <div class="text-2xl font-black text-gray-900"><?= $teaching_count ?></div>
                <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-1">Teaching</div>
            </div>

            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex flex-col items-center text-center group hover:border-orange-500 transition-all duration-300">
                <div class="w-12 h-12 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center text-xl mb-3 shadow-inner group-hover:rotate-6 transition-transform">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="text-2xl font-black text-gray-900"><?= $non_teaching_count ?></div>
                <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-1">Non-Teaching</div>
            </div>

            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex flex-col items-center text-center group hover:border-blue-500 transition-all duration-300">
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl mb-3 shadow-inner group-hover:rotate-6 transition-transform">
                    <i class="fas fa-person"></i>
                </div>
                <div class="text-2xl font-black text-gray-900"><?= $male_count ?></div>
                <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-1">Males</div>
            </div>

            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex flex-col items-center text-center group hover:border-rose-500 transition-all duration-300">
                <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center text-xl mb-3 shadow-inner group-hover:rotate-6 transition-transform">
                    <i class="fas fa-person-dress"></i>
                </div>
                <div class="text-2xl font-black text-gray-900"><?= $female_count ?></div>
                <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-1">Females</div>
            </div>
        </div>

        <!-- Tabbed Filtering -->
        <div class="flex flex-col md:flex-row items-center justify-between gap-6 mb-8 bg-white p-3 rounded-2xl border border-gray-100 shadow-sm">
            <div class="flex p-1 bg-gray-50 rounded-xl overflow-hidden">
                <a href="?tab=all&status=<?= $status_filter ?>&q=<?= $search ?>" class="px-6 py-2.5 rounded-lg text-sm font-bold transition-all <?= $tab === 'all' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-400 hover:text-gray-600' ?>">All Personnel</a>
                <a href="?tab=teaching&status=<?= $status_filter ?>&q=<?= $search ?>" class="px-6 py-2.5 rounded-lg text-sm font-bold transition-all <?= $tab === 'teaching' ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-400 hover:text-gray-600' ?>">Teaching Staff</a>
                <a href="?tab=non-teaching&status=<?= $status_filter ?>&q=<?= $search ?>" class="px-6 py-2.5 rounded-lg text-sm font-bold transition-all <?= $tab === 'non-teaching' ? 'bg-white text-orange-700 shadow-sm' : 'text-gray-400 hover:text-gray-600' ?>">Non-Teaching</a>
            </div>
            
            <form method="GET" class="flex items-center gap-3 flex-1 w-full md:max-w-2xl">
                <input type="hidden" name="tab" value="<?= $tab ?>">
                
                <!-- Status Filter Dropdown -->
                <select name="status" onchange="this.form.submit()" class="px-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm font-bold text-gray-600 transition-all outline-none">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                    <option value="retired" <?= $status_filter === 'retired' ? 'selected' : '' ?>>Retired Only</option>
                </select>

                <div class="relative flex-1">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-300"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, title or ID..." class="w-full pl-11 pr-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm font-medium transition-all">
                </div>
            </form>
        </div>

        <!-- Staff Table -->
        <?php if($staff && $staff->num_rows > 0): ?>
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="glass-header sticky top-0 border-b border-gray-100">
                                <th class="px-6 py-5 text-[11px] font-black text-gray-400 uppercase tracking-widest">Personnel</th>
                                <th class="px-6 py-5 text-[11px] font-black text-gray-400 uppercase tracking-widest">Functional Area</th>
                                <th class="px-6 py-5 text-[11px] font-black text-gray-400 uppercase tracking-widest text-center">Contact</th>
                                <th class="px-6 py-5 text-[11px] font-black text-gray-400 uppercase tracking-widest text-center">Status</th>
                                <th class="px-6 py-5 text-[11px] font-black text-gray-400 uppercase tracking-widest text-right">Operations</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php while($s = $staff->fetch_assoc()): 
                                $has_login = !empty($s['user_id']);
                                $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $s['full_name'])));
                                $initials = substr($initials, 0, 2);
                                $status = $s['employment_status'] ?? 'active';
                                $status_color = match($status) {
                                    'active' => 'emerald',
                                    'retired' => 'amber',
                                    'inactive' => 'gray',
                                    'deleted' => 'red',
                                    default => 'indigo'
                                };
                            ?>
                            <tr class="staff-row transition-colors group <?= $status !== 'active' ? 'opacity-60 bg-gray-50/30' : '' ?>">
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-4">
                                        <div class="relative">
                                            <?php 
                                            $photo_src = $s['photo_path'];
                                            if ($photo_src && strpos($photo_src, 'http') === 0) {
                                                // Extract ID and use robust direct link format
                                                if (preg_match('/id=([a-zA-Z0-9_-]+)/', $photo_src, $matches)) {
                                                    $photo_src = "https://lh3.googleusercontent.com/d/" . $matches[1];
                                                }
                                            } else {
                                                $photo_src = $photo_src ? "../../../" . $photo_src : null;
                                            }
                                            
                                            if($s['photo_path']): ?>
                                                <img src="<?= htmlspecialchars($photo_src) ?>" class="w-12 h-14 object-cover rounded-xl border border-gray-100 shadow-sm" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <?php else: ?>
                                                <div class="w-12 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-black text-lg shadow-sm">
                                                    <?= $initials ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if($status === 'active'): ?>
                                                <span class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-emerald-500 border-2 border-white rounded-full status-pulse"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-extrabold text-gray-900 leading-none mb-1"><?= htmlspecialchars($s['full_name']) ?></div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[9px] font-black bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded uppercase tracking-tighter cursor-help" title="Staff ID"><?= htmlspecialchars($s['staff_code'] ?? 'N/A') ?></span>
                                                <?php if($has_login): ?>
                                                    <span class="text-[9px] font-black text-gray-400 uppercase tracking-tighter">@<?= htmlspecialchars($s['username']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <div>
                                        <div class="text-xs font-bold text-gray-700"><?= htmlspecialchars($s['job_title'] ?: 'Not Specified') ?></div>
                                        <div class="flex flex-wrap items-center gap-1 mt-1.5">
                                            <?php 
                                            $types = explode(',', $s['staff_type'] ?? 'teaching');
                                            foreach($types as $t): 
                                                $t = trim($t);
                                                if ($t === 'teaching'): ?>
                                                <span class="text-[8px] font-black bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded border border-emerald-100 uppercase">Teaching</span>
                                            <?php elseif ($t === 'non-teaching'): ?>
                                                <span class="text-[8px] font-black bg-orange-50 text-orange-700 px-2 py-0.5 rounded border border-orange-100 uppercase">Non-Teaching</span>
                                            <?php endif; endforeach; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <?php if($s['phone_number']): ?>
                                        <a href="tel:<?= $s['phone_number'] ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-50 text-gray-400 hover:bg-indigo-50 hover:text-indigo-600 transition shadow-inner">
                                            <i class="fas fa-phone text-xs"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-[10px] text-gray-300 font-bold uppercase">No Phone</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span class="inline-flex items-center gap-1.5 text-[10px] font-black bg-<?= $status_color ?>-50 text-<?= $status_color ?>-700 border border-<?= $status_color ?>-100 px-3 py-1 rounded-full uppercase tracking-tight">
                                        <span class="w-1.5 h-1.5 rounded-full bg-<?= $status_color ?>-500"></span>
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <div class="flex items-center justify-end gap-2 pr-1">
                                        <a href="profile_staff.php?id=<?= $s['id'] ?>" class="p-2 text-indigo-400 hover:text-indigo-600 transition" title="View Profile">
                                            <i class="fas fa-eye text-sm"></i>
                                        </a>
                                        <a href="edit_staff.php?id=<?= $s['id'] ?>" class="p-2 text-indigo-400 hover:text-indigo-600 transition" title="Edit Profile">
                                            <i class="fas fa-pen-to-square text-sm"></i>
                                        </a>
                                        <div class="relative group/menu inline-block ml-2">
                                            <button class="w-8 h-8 rounded-lg bg-gray-50 text-gray-400 flex items-center justify-center hover:bg-gray-100 transition shadow-inner">
                                                <i class="fas fa-ellipsis-v text-xs"></i>
                                            </button>
                                            <div class="absolute right-0 bottom-full mb-2 w-32 bg-white rounded-xl shadow-xl border border-gray-100 opacity-0 invisible group-hover/menu:opacity-100 group-hover/menu:visible transition-all z-20 overflow-hidden">
                                                <?php if($status !== 'active'): ?>
                                                    <button onclick="updateStatus(<?= $s['id'] ?>, 'activate')" class="w-full px-4 py-2.5 text-left text-[11px] font-bold text-emerald-600 hover:bg-emerald-50 transition border-b border-gray-50">
                                                        <i class="fas fa-rotate-left mr-2 opacity-60"></i> <?= $status === 'retired' ? 'Unretire' : 'Reactivate' ?>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if($status === 'active'): ?>
                                                    <button onclick="updateStatus(<?= $s['id'] ?>, 'deactivate')" class="w-full px-4 py-2.5 text-left text-[11px] font-bold text-orange-600 hover:bg-orange-50 transition border-b border-gray-50">
                                                        <i class="fas fa-power-off mr-2 opacity-60"></i> Deactivate
                                                    </button>
                                                <?php endif; ?>

                                                <?php if($status !== 'retired'): ?>
                                                    <button onclick="updateStatus(<?= $s['id'] ?>, 'retire')" class="w-full px-4 py-2.5 text-left text-[11px] font-bold text-amber-600 hover:bg-amber-50 transition border-b border-gray-50">
                                                        <i class="fas fa-bed mr-2 opacity-60"></i> Retire
                                                    </button>
                                                <?php endif; ?>

                                                <button onclick="updateStatus(<?= $s['id'] ?>, 'delete')" class="w-full px-4 py-2.5 text-left text-[11px] font-bold text-red-600 hover:bg-red-50 transition">
                                                    <i class="fas fa-trash-alt mr-2 opacity-60"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
            function updateStatus(id, action) {
                const actionText = {
                    'activate': 'activate this staff member and their login access',
                    'deactivate': 'deactivate this staff member and lock their system access',
                    'retire': 'mark this staff member as retired (access will be revoked)',
                    'delete': 'PERMANENTLY DELETE this staff record and linked user account'
                }[action];

                Swal.fire({
                    title: 'Confirm Action',
                    text: `Are you sure you want to ${actionText}?`,
                    icon: action === 'delete' ? 'warning' : 'question',
                    showCancelButton: true,
                    confirmButtonColor: action === 'delete' ? '#ef4444' : '#4f46e5',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, proceed',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const formData = new FormData();
                        formData.append('id', id);
                        formData.append('action', action);

                        fetch('staff_actions.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(async response => {
                            const text = await response.text();
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                throw new Error('Server returned invalid response: ' + text.substring(0, 100));
                            }
                        })
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Success!',
                                    text: data.message,
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error!', data.message, 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error!', error.message, 'error');
                            console.error('Error:', error);
                        });
                    }
                });
            }
            </script>

        <?php else: ?>
            <div class="bg-white rounded-3xl border border-dashed border-gray-200 p-20 text-center shadow-inner">
                <i class="fas fa-users-slash text-6xl text-gray-100 mb-6"></i>
                <h3 class="text-2xl font-black text-gray-900 mb-2 tracking-tight">Personnel Vacancy</h3>
                <p class="text-gray-400 mb-10 max-w-sm mx-auto font-medium">No personnel records currently match your filters. Expand your search or add new staff.</p>
                <a href="add_staff.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-8 py-4 rounded-2xl inline-flex items-center gap-2 shadow-lg shadow-indigo-100 transition-all hover:scale-105">
                    <i class="fas fa-user-plus text-lg"></i> Onboard New Staff
                </a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
