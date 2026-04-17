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

// Filtering Logic
$tab = $_GET['tab'] ?? 'all';
$dept_filter = $_GET['dept'] ?? '';
$search = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? 'all'; 

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personnel Directory | HR Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        
        .stat-card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            position: relative;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
            border-color: #e2e8f0;
        }

        .stat-icon-wrapper {
            position: absolute;
            right: -10px;
            bottom: -15px;
            opacity: 0.04;
            font-size: 6rem;
            transition: all 0.3s ease;
        }

        .stat-card:hover .stat-icon-wrapper {
            transform: scale(1.1) rotate(-5deg);
            opacity: 0.08;
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        }

        .table-row-hover {
            transition: all 0.2s ease;
        }
        .table-row-hover:hover {
            background-color: #f8fafc;
        }

        .action-button {
            transition: all 0.2s ease;
        }
        .action-button:hover {
            transform: scale(1.05);
        }

        /* Custom Scrollbar for table */
        .custom-scrollbar::-webkit-scrollbar { height: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="text-slate-800 antialiased">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="lg:ml-72 min-h-screen p-8 lg:p-10 max-w-[1600px] mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-200">
                        <i class="fas fa-users-viewfinder text-lg"></i>
                    </div>
                    Personnel Directory
                </h1>
                <p class="text-slate-500 mt-2 font-medium text-sm ml-14">Manage institutional human resources and system credentials.</p>
            </div>
            
            <div class="flex items-center gap-3">
                <a href="bulk_upload_staff.php" class="action-button bg-white text-emerald-600 border border-slate-200 font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 hover:border-emerald-200 hover:bg-emerald-50 shadow-sm text-sm">
                    <i class="fas fa-file-csv"></i> Bulk Import
                </a>
                <a href="add_staff.php" class="action-button bg-gradient-to-r from-indigo-600 to-indigo-700 text-white font-bold px-6 py-2.5 rounded-xl flex items-center gap-2 shadow-sm shadow-indigo-200 text-sm">
                    <i class="fas fa-plus"></i> New Staff
                </a>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-5 mb-8">
            <div class="stat-card p-5 border-t-4 border-t-indigo-500">
                <div class="flex justify-between items-start relative z-10">
                    <div>
                        <div class="text-[10px] font-black tracking-widest text-slate-400 uppercase mb-1">Total Personnel</div>
                        <div class="text-3xl font-black text-slate-800"><?= $total_count ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-500 flex items-center justify-center">
                        <i class="fas fa-id-card"></i>
                    </div>
                </div>
                <i class="fas fa-id-card stat-icon-wrapper text-indigo-500"></i>
            </div>

            <div class="stat-card p-5 border-t-4 border-t-emerald-500">
                <div class="flex justify-between items-start relative z-10">
                    <div>
                        <div class="text-[10px] font-black tracking-widest text-slate-400 uppercase mb-1">Teaching</div>
                        <div class="text-3xl font-black text-slate-800"><?= $teaching_count ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center">
                        <i class="fas fa-chalkboard-user"></i>
                    </div>
                </div>
                <i class="fas fa-chalkboard-user stat-icon-wrapper text-emerald-500"></i>
            </div>

            <div class="stat-card p-5 border-t-4 border-t-orange-500">
                <div class="flex justify-between items-start relative z-10">
                    <div>
                        <div class="text-[10px] font-black tracking-widest text-slate-400 uppercase mb-1">Non-Teaching</div>
                        <div class="text-3xl font-black text-slate-800"><?= $non_teaching_count ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center">
                        <i class="fas fa-user-tie"></i>
                    </div>
                </div>
                <i class="fas fa-user-tie stat-icon-wrapper text-orange-500"></i>
            </div>

            <div class="stat-card p-5 border-t-4 border-t-blue-500">
                <div class="flex justify-between items-start relative z-10">
                    <div>
                        <div class="text-[10px] font-black tracking-widest text-slate-400 uppercase mb-1">Males</div>
                        <div class="text-3xl font-black text-slate-800"><?= $male_count ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center">
                        <i class="fas fa-mars"></i>
                    </div>
                </div>
                <i class="fas fa-mars stat-icon-wrapper text-blue-500"></i>
            </div>

            <div class="stat-card p-5 border-t-4 border-t-rose-500">
                <div class="flex justify-between items-start relative z-10">
                    <div>
                        <div class="text-[10px] font-black tracking-widest text-slate-400 uppercase mb-1">Females</div>
                        <div class="text-3xl font-black text-slate-800"><?= $female_count ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center">
                        <i class="fas fa-venus"></i>
                    </div>
                </div>
                <i class="fas fa-venus stat-icon-wrapper text-rose-500"></i>
            </div>
        </div>

        <!-- Filters Bar (Glass Panel) -->
        <div class="glass-panel rounded-2xl p-3 mb-6 flex flex-col xl:flex-row gap-4 items-center justify-between sticky top-4 z-40">
            <!-- Tabs -->
            <div class="flex bg-slate-100/50 p-1 rounded-xl w-full xl:w-auto">
                <a href="?tab=all&status=<?= $status_filter ?>&q=<?= $search ?>" class="flex-1 xl:flex-none px-6 py-2 rounded-lg text-xs font-bold text-center transition-all <?= $tab === 'all' ? 'bg-white text-indigo-700 shadow-sm border border-slate-200/50' : 'text-slate-500 hover:text-slate-700' ?>">All Staff</a>
                <a href="?tab=teaching&status=<?= $status_filter ?>&q=<?= $search ?>" class="flex-1 xl:flex-none px-6 py-2 rounded-lg text-xs font-bold text-center transition-all <?= $tab === 'teaching' ? 'bg-white text-emerald-700 shadow-sm border border-slate-200/50' : 'text-slate-500 hover:text-slate-700' ?>">Teaching</a>
                <a href="?tab=non-teaching&status=<?= $status_filter ?>&q=<?= $search ?>" class="flex-1 xl:flex-none px-6 py-2 rounded-lg text-xs font-bold text-center transition-all <?= $tab === 'non-teaching' ? 'bg-white text-orange-700 shadow-sm border border-slate-200/50' : 'text-slate-500 hover:text-slate-700' ?>">Non-Teaching</a>
            </div>

            <form method="GET" class="flex w-full xl:w-auto gap-3 flex-1 xl:flex-none justify-end">
                <input type="hidden" name="tab" value="<?= $tab ?>">
                
                <div class="relative min-w-[160px]">
                    <select name="status" onchange="this.form.submit()" class="w-full appearance-none pl-4 pr-10 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 cursor-pointer shadow-sm">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active Only</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                        <option value="retired" <?= $status_filter === 'retired' ? 'selected' : '' ?>>Retired Only</option>
                    </select>
                    <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 pointer-events-none"></i>
                </div>

                <div class="relative w-full max-w-sm">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search ID, Name, Role..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 shadow-sm placeholder-slate-400">
                    <?php if($search): ?>
                        <a href="?tab=<?= $tab ?>&status=<?= $status_filter ?>" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-rose-500 bg-white px-1">
                            <i class="fas fa-times-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Data Area -->
        <?php if($staff && $staff->num_rows > 0): ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-6 py-4">Personnel Identity</th>
                                <th class="px-6 py-4">Role & Function</th>
                                <th class="px-6 py-4">Contact Info</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php while($s = $staff->fetch_assoc()): 
                                $has_login = !empty($s['user_id']);
                                $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $s['full_name'])));
                                $initials = substr($initials, 0, 2);
                                $status = $s['employment_status'] ?? 'active';
                                
                                // Status styling
                                $s_color = match($status) {
                                    'active' => 'emerald', 'retired' => 'amber', 'inactive' => 'slate', default => 'indigo'
                                };
                                
                                // Handle photos robustly
                                $photo_src = $s['photo_path'];
                                if ($photo_src && strpos($photo_src, 'http') === 0) {
                                    if (preg_match('/id=([a-zA-Z0-9_-]+)/', $photo_src, $matches)) {
                                        $photo_src = "https://lh3.googleusercontent.com/d/" . $matches[1];
                                    }
                                } else {
                                    $photo_src = $photo_src ? "../../../" . $photo_src : null;
                                }
                            ?>
                            <tr class="table-row-hover group <?= $status !== 'active' ? 'opacity-75 bg-slate-50/50' : '' ?>">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-4">
                                        <div class="relative flex-shrink-0">
                                            <?php if($photo_src): ?>
                                                <img src="<?= htmlspecialchars($photo_src) ?>" class="w-12 h-12 object-cover rounded-full border-2 border-white shadow-sm bg-white" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="w-12 h-12 bg-gradient-to-br from-indigo-100 to-indigo-50 rounded-full border-2 border-white shadow-sm flex items-center justify-center text-indigo-600 font-extrabold text-sm hidden">
                                                    <?= $initials ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="w-12 h-12 bg-gradient-to-br from-indigo-100 to-indigo-50 rounded-full border-2 border-white shadow-sm flex items-center justify-center text-indigo-600 font-extrabold text-sm">
                                                    <?= $initials ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-extrabold text-slate-900 text-sm mb-0.5"><?= htmlspecialchars($s['full_name']) ?></div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-[9px] font-bold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded"><?= htmlspecialchars($s['staff_code'] ?? 'N/A') ?></span>
                                                <?php if($has_login): ?>
                                                    <span class="text-[9px] font-bold text-slate-400 flex items-center gap-1"><i class="fas fa-lock text-[8px]"></i> @<?= htmlspecialchars($s['username']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <div class="text-xs font-bold text-slate-700 mb-1"><?= htmlspecialchars($s['job_title'] ?: 'Not Specified') ?></div>
                                    <div class="flex gap-1.5">
                                        <?php 
                                        $types = explode(',', $s['staff_type'] ?? 'teaching');
                                        foreach($types as $t): 
                                            $t = trim($t);
                                            if ($t === 'teaching'): ?>
                                            <span class="text-[9px] font-black bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded border border-emerald-100 uppercase tracking-wide">Teaching</span>
                                        <?php elseif ($t === 'non-teaching'): ?>
                                            <span class="text-[9px] font-black bg-orange-50 text-orange-700 px-2 py-0.5 rounded border border-orange-100 uppercase tracking-wide">Non-Teaching</span>
                                        <?php endif; endforeach; ?>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="text-xs font-medium text-slate-600 flex items-center gap-2">
                                        <i class="fas fa-phone text-slate-400 w-3 text-center"></i>
                                        <?= htmlspecialchars($s['phone_number'] ?: '—') ?>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 text-[10px] font-black bg-<?= $s_color ?>-50 text-<?= $s_color ?>-700 border border-<?= $s_color ?>-200 px-2.5 py-1.5 rounded-full uppercase tracking-wider">
                                        <span class="w-1.5 h-1.5 rounded-full bg-<?= $s_color ?>-500"></span>
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5 pr-2">
                                        <a href="profile_staff.php?id=<?= $s['id'] ?>" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 flex items-center justify-center hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 transition-all shadow-sm" title="View Profile">
                                            <i class="fas fa-eye text-xs"></i>
                                        </a>
                                        <a href="edit_staff.php?id=<?= $s['id'] ?>" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 flex items-center justify-center hover:bg-slate-50 hover:text-slate-800 transition-all shadow-sm" title="Edit Data">
                                            <i class="fas fa-pen-to-square text-xs"></i>
                                        </a>
                                        
                                        <!-- Actions Dropdown -->
                                        <div class="relative group/menu inline-block">
                                            <button class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 flex items-center justify-center hover:bg-slate-50 transition-all shadow-sm shadow-slate-100">
                                                <i class="fas fa-ellipsis-v text-[10px]"></i>
                                            </button>
                                            <div class="absolute right-0 bottom-full mb-2 w-36 bg-white rounded-xl shadow-[0_10px_40px_-10px_rgba(0,0,0,0.15)] border border-slate-100 opacity-0 invisible group-hover/menu:opacity-100 group-hover/menu:visible transition-all z-50 overflow-hidden transform origin-bottom-right scale-95 group-hover/menu:scale-100">
                                                <div class="p-1">
                                                    <?php if($status !== 'active'): ?>
                                                        <button onclick="updateStatus(<?= $s['id'] ?>, 'activate')" class="w-full text-left px-3 py-2 text-[11px] font-bold text-emerald-700 bg-white hover:bg-emerald-50 rounded-lg transition-colors flex items-center gap-2">
                                                            <i class="fas fa-rotate-left w-3 text-center opacity-70"></i> <?= $status === 'retired' ? 'Unretire' : 'Reactivate' ?>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if($status === 'active'): ?>
                                                        <button onclick="updateStatus(<?= $s['id'] ?>, 'deactivate')" class="w-full text-left px-3 py-2 text-[11px] font-bold text-orange-700 bg-white hover:bg-orange-50 rounded-lg transition-colors flex items-center gap-2">
                                                            <i class="fas fa-power-off w-3 text-center opacity-70"></i> Deactivate
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if($status !== 'retired'): ?>
                                                        <button onclick="updateStatus(<?= $s['id'] ?>, 'retire')" class="w-full text-left px-3 py-2 text-[11px] font-bold text-amber-700 bg-white hover:bg-amber-50 rounded-lg transition-colors flex items-center gap-2">
                                                            <i class="fas fa-bed w-3 text-center opacity-70"></i> Retire
                                                        </button>
                                                    <?php endif; ?>

                                                    <div class="h-px bg-slate-100 my-1"></div>

                                                    <button onclick="updateStatus(<?= $s['id'] ?>, 'delete')" class="w-full text-left px-3 py-2 text-[11px] font-bold text-rose-600 bg-white hover:bg-rose-50 rounded-lg transition-colors flex items-center gap-2">
                                                        <i class="fas fa-trash-alt w-3 text-center opacity-70"></i> Delete
                                                    </button>
                                                </div>
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
                    confirmButtonColor: action === 'delete' ? '#e11d48' : '#4f46e5',
                    cancelButtonColor: '#94a3b8',
                    confirmButtonText: 'Yes, proceed',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        popup: 'rounded-2xl border border-slate-100',
                        title: 'text-slate-800 font-extrabold text-xl',
                        confirmButton: 'rounded-xl font-bold px-6',
                        cancelButton: 'rounded-xl font-bold px-6'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const formData = new FormData();
                        formData.append('id', id);
                        formData.append('action', action);

                        fetch('staff_actions.php', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Success!', text: data.message, icon: 'success',
                                    timer: 2000, showConfirmButton: false,
                                    customClass: { popup: 'rounded-2xl', title: 'font-extrabold' }
                                }).then(() => location.reload());
                            } else {
                                Swal.fire('Error!', data.message, 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error!', 'An unexpected error occurred.', 'error');
                        });
                    }
                });
            }
            </script>

        <?php else: ?>
            <!-- Blank State -->
            <div class="bg-white rounded-3xl border border-slate-200 p-20 text-center shadow-sm max-w-2xl mx-auto mt-10">
                <div class="w-24 h-24 bg-slate-50 border border-slate-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-users-slash text-4xl text-slate-300"></i>
                </div>
                <h3 class="text-xl font-extrabold text-slate-800 mb-2">No Personnel Found</h3>
                <p class="text-slate-500 text-sm font-medium mb-8">We couldn't find any staff records matching your current filter criteria. Try adjusting your search or add a new record.</p>
                <div class="flex items-center justify-center gap-3">
                    <?php if($search || $tab !== 'all' || $status_filter !== 'all'): ?>
                        <a href="view_staff.php" class="px-6 py-3 rounded-xl font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition shadow-sm text-sm">Clear Filters</a>
                    <?php endif; ?>
                    <a href="add_staff.php" class="px-6 py-3 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-sm shadow-indigo-200 text-sm flex items-center gap-2">
                        <i class="fas fa-plus"></i> Register Staff
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
