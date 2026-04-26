<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || !in_array($_SESSION['role'], ['teacher', 'facilitator', 'admin'])) {
    header('Location: ../../login');
    exit;
}

$user_id = $_SESSION['user_id'];
$total_weeks = intval(getSystemSetting($conn, 'weeks_per_semester', 12));

// Filters
$week_f = intval($_GET['week'] ?? 0);
$date_f = $_GET['date'] ?? '';
$search_f = trim($_GET['search'] ?? '');

$where = "l.teacher_id = $user_id";
if ($week_f) $where .= " AND l.week_number = $week_f";
if ($date_f) $where .= " AND l.week_ending = '" . $conn->real_escape_string($date_f) . "'";
if ($search_f) {
    $s = $conn->real_escape_string($search_f);
    $where .= " AND (l.topic LIKE '%$s%' OR l.sub_strand LIKE '%$s%' OR s.name LIKE '%$s%')";
}

// Stats (Filtered)
$stats = ['draft' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$st_res = $conn->query("SELECT l.status, COUNT(*) as count FROM lesson_plans l JOIN subjects s ON l.subject_id = s.id WHERE $where GROUP BY l.status");
if ($st_res) {
    while($r = $st_res->fetch_assoc()) $stats[$r['status']] = $r['count'];
}

// Fetch Profile Data
$prof_res = $conn->query("SELECT u.username, u.role, sp.* FROM users u LEFT JOIN staff_profiles sp ON u.id = sp.user_id WHERE u.id = $user_id LIMIT 1");
$staff = $prof_res->fetch_assoc();

function getPlans($conn, $where, $status) {
    return $conn->query("
        SELECT l.*, s.name as subject_name 
        FROM lesson_plans l 
        JOIN subjects s ON l.subject_id = s.id 
        WHERE $where AND l.status = '$status' 
        ORDER BY l.created_at DESC
    ");
}

$drafts = getPlans($conn, $where, 'draft');
$pending = getPlans($conn, $where, 'pending');
$approved = getPlans($conn, $where, 'approved');
$rejected = getPlans($conn, $where, 'rejected');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lesson Dashboard | Teacher Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/top_nav.php'; ?>

    <main class="min-h-screen p-4 md:p-8 pt-20 md:pt-24 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10">
            <div>
                <h1 class="text-4xl font-black text-gray-900 tracking-tight">Lesson <span class="text-indigo-600">Dashboard</span></h1>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-widest mt-2">Manage, Track, and Refine your teaching plans</p>
            </div>
            <a href="lesson_plans" class="bg-indigo-600 text-white px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-indigo-700 transition shadow-xl shadow-indigo-100 flex items-center gap-3 active:scale-95">
                <i class="fas fa-plus"></i> Create New Plan
            </a>
        </div>

        <!-- Stats Bar -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-5 group hover:border-indigo-200 transition-all">
                <div class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-500 text-2xl group-hover:scale-110 transition-transform shadow-inner">
                    <i class="fas fa-file-pen"></i>
                </div>
                <div>
                    <div class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest">Drafts</div>
                    <div class="text-3xl font-black text-gray-900"><?= $stats['draft'] ?></div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-5 group hover:border-yellow-200 transition-all">
                <div class="w-14 h-14 bg-yellow-50 rounded-2xl flex items-center justify-center text-yellow-500 text-2xl group-hover:scale-110 transition-transform shadow-inner">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div>
                    <div class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest">Pending Review</div>
                    <div class="text-3xl font-black text-gray-900"><?= $stats['pending'] ?></div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-5 group hover:border-emerald-200 transition-all">
                <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-500 text-2xl group-hover:scale-110 transition-transform shadow-inner">
                    <i class="fas fa-check-double"></i>
                </div>
                <div>
                    <div class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest">Approved</div>
                    <div class="text-3xl font-black text-gray-900"><?= $stats['approved'] ?></div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-5 group hover:border-red-200 transition-all">
                <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center text-red-500 text-2xl group-hover:scale-110 transition-transform shadow-inner">
                    <i class="fas fa-circle-exclamation"></i>
                </div>
                <div>
                    <div class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest">Rejected</div>
                    <div class="text-3xl font-black text-gray-900"><?= $stats['rejected'] ?></div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white p-4 rounded-3xl shadow-sm border border-gray-100 mb-10 flex flex-wrap items-center gap-4">
            <form class="flex flex-wrap items-center gap-4 w-full">
                <div class="relative flex-1 min-w-[200px]">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-300"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search_f) ?>" placeholder="Search by topic or subject..." class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all text-sm font-bold">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Week</label>
                    <select name="week" class="px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all text-sm font-bold appearance-none min-w-[100px]">
                        <option value="0">All Weeks</option>
                        <?php for($i=1; $i<=$total_weeks; $i++): ?>
                            <option value="<?= $i ?>" <?= $week_f == $i ? 'selected' : '' ?>>Week <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Week Ending</label>
                    <input type="date" name="date" value="<?= htmlspecialchars($date_f) ?>" class="px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all text-sm font-bold">
                </div>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-indigo-700 transition shadow-lg shadow-indigo-100">
                    Filter
                </button>
                <?php if($week_f || $date_f || $search_f): ?>
                    <a href="lesson_portfolio" class="text-[0.625rem] font-black text-gray-400 uppercase hover:text-red-500 transition tracking-widest">Clear All</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Dashboard Content -->
        <div class="space-y-12">
            
            <!-- Rejected Section -->
            <?php if($rejected && $rejected->num_rows > 0): ?>
            <section>
                <h3 class="text-xs font-black text-red-500 uppercase tracking-[0.2em] mb-6 flex items-center gap-3">
                    <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span> Rejected / Needs Revision
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php while($p = $rejected->fetch_assoc()): ?>
                        <div class="bg-white rounded-3xl border-2 border-red-50 p-6 hover:shadow-xl hover:border-red-100 transition-all group relative overflow-hidden">
                            <div class="absolute -right-6 -top-6 opacity-5 group-hover:scale-110 transition-transform duration-700"><i class="fas fa-circle-exclamation text-8xl text-red-500"></i></div>
                            <div class="flex justify-between items-start mb-4 relative z-10">
                                <span class="px-3 py-1 bg-red-50 text-red-600 rounded-lg text-[0.625rem] font-black uppercase tracking-widest">Week <?= $p['week_number'] ?></span>
                                <div class="flex gap-2">
                                    <a href="lesson_plans?edit=<?= $p['id'] ?>" class="w-9 h-9 bg-red-600 text-white rounded-xl flex items-center justify-center hover:bg-red-700 transition shadow-lg shadow-red-100"><i class="fas fa-edit text-xs"></i></a>
                                </div>
                            </div>
                            <h4 class="text-lg font-black text-gray-900 leading-tight mb-2"><?= htmlspecialchars($p['topic']) ?></h4>
                            <p class="text-[0.6875rem] font-bold text-gray-400 mb-4"><?= htmlspecialchars($p['subject_name']) ?> · <?= htmlspecialchars($p['class_name']) ?></p>
                            
                            <div class="p-4 bg-red-50/50 rounded-2xl border border-red-100/50">
                                <div class="text-[0.5625rem] font-black text-red-400 uppercase tracking-widest mb-1">Supervisor Remarks</div>
                                <p class="text-xs font-bold text-red-800 italic leading-relaxed">"<?= htmlspecialchars($p['supervisor_comments'] ?: 'No specific comments provided. Please review and resubmit.') ?>"</p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Drafts Section -->
            <?php if($drafts && $drafts->num_rows > 0): ?>
            <section>
                <h3 class="text-xs font-black text-indigo-500 uppercase tracking-[0.2em] mb-6 flex items-center gap-3">
                    <span class="w-1 h-4 bg-indigo-500 rounded-full"></span> My Drafts
                </h3>
                <div class="bg-white rounded-3xl border border-gray-100 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-50/50 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Week</th>
                                    <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Topic / Subject</th>
                                    <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Last Modified</th>
                                    <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest text-right whitespace-nowrap">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php while($p = $drafts->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50/50 transition-colors group">
                                        <td class="px-6 py-4 whitespace-nowrap"><span class="font-black text-indigo-600">Wk <?= $p['week_number'] ?></span></td>
                                        <td class="px-6 py-4 min-w-[300px]">
                                            <div class="font-black text-gray-900"><?= htmlspecialchars($p['topic']) ?></div>
                                            <div class="text-[0.625rem] font-bold text-gray-400 uppercase tracking-widest mt-1"><?= htmlspecialchars($p['subject_name']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-xs font-bold text-gray-500 whitespace-nowrap"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                                        <td class="px-6 py-4 text-right whitespace-nowrap">
                                            <div class="flex justify-end gap-2">
                                                <a href="lesson_plans?edit=<?= $p['id'] ?>" class="h-9 px-4 bg-indigo-600 text-white rounded-xl flex items-center gap-2 text-[0.625rem] font-black uppercase tracking-widest hover:bg-indigo-700 transition shadow-lg shadow-indigo-100">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <form method="POST" action="lesson_plans" onsubmit="return confirm('Delete this draft permanently?');">
                                                    <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                                                    <button type="submit" name="delete_plan" class="w-9 h-9 border border-gray-100 text-gray-400 rounded-xl flex items-center justify-center hover:bg-red-50 hover:text-red-500 transition">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Pending Section -->
            <?php if($pending && $pending->num_rows > 0): ?>
            <section>
                <h3 class="text-xs font-black text-yellow-600 uppercase tracking-[0.2em] mb-6 flex items-center gap-3">
                    <span class="w-1 h-4 bg-yellow-500 rounded-full"></span> Submitted (Awaiting Review)
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php while($p = $pending->fetch_assoc()): ?>
                        <div class="bg-white rounded-3xl border border-gray-100 p-6 hover:shadow-xl transition-all group">
                            <div class="flex justify-between items-center mb-4">
                                <span class="px-3 py-1 bg-yellow-50 text-yellow-700 rounded-lg text-[0.625rem] font-black uppercase">Week <?= $p['week_number'] ?></span>
                                <div class="flex gap-2">
                                    <a href="print_lesson_plan?id=<?= $p['id'] ?>&view=html" target="_blank" class="w-9 h-9 bg-gray-50 text-gray-400 rounded-xl flex items-center justify-center hover:text-indigo-600 transition"><i class="fas fa-eye text-xs"></i></a>
                                    <form method="POST" action="lesson_plans" onsubmit="return confirm('Unsubmit this plan back to drafts?');">
                                        <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                                        <button type="submit" name="unsubmit_plan" class="w-9 h-9 bg-gray-50 text-gray-400 rounded-xl flex items-center justify-center hover:text-yellow-600 transition">
                                            <i class="fas fa-rotate-left text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <h4 class="font-black text-gray-900 leading-tight mb-2 truncate"><?= htmlspecialchars($p['topic']) ?></h4>
                            <div class="flex items-center gap-2 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest">
                                <i class="fas fa-book text-indigo-400"></i> <?= htmlspecialchars($p['subject_name']) ?>
                            </div>
                            <div class="mt-4 pt-4 border-t border-gray-50 flex items-center justify-between">
                                <span class="text-[0.625rem] font-bold text-gray-300"><?= date('M j, Y', strtotime($p['created_at'])) ?></span>
                                <span class="flex items-center gap-1.5 text-[0.625rem] font-black text-yellow-600 uppercase">
                                    <i class="fas fa-spinner fa-spin"></i> In Queue
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Approved Section -->
            <section>
                <h3 class="text-xs font-black text-emerald-600 uppercase tracking-[0.2em] mb-6 flex items-center gap-3">
                    <span class="w-1 h-4 bg-emerald-500 rounded-full"></span> Approved Notes Archive
                </h3>
                <div class="bg-white rounded-3xl border border-gray-100 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-50/50 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Wk</th>
                                    <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Topic & Subject</th>
                                    <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Approved On</th>
                                    <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Supervisor Remark</th>
                                    <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest text-right whitespace-nowrap">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php if($approved && $approved->num_rows > 0): while($p = $approved->fetch_assoc()): ?>
                                    <tr class="hover:bg-emerald-50/20 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap"><span class="font-black text-emerald-600"><?= $p['week_number'] ?></span></td>
                                        <td class="px-6 py-4 min-w-[250px]">
                                            <div class="font-black text-gray-900"><?= htmlspecialchars($p['topic']) ?></div>
                                            <div class="text-[0.5625rem] font-black text-indigo-400 uppercase tracking-widest mt-0.5"><?= htmlspecialchars($p['subject_name']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-xs font-bold text-gray-500 whitespace-nowrap"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                                        <td class="px-6 py-4 min-w-[200px]">
                                            <?php if($p['supervisor_comments']): ?>
                                                <div class="text-[0.625rem] font-bold text-gray-400 italic leading-relaxed" title="<?= htmlspecialchars($p['supervisor_comments']) ?>">
                                                    "<?= htmlspecialchars($p['supervisor_comments']) ?>"
                                                </div>
                                            <?php else: ?>
                                                <span class="text-[0.625rem] font-bold text-gray-300 italic">No remarks</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-right whitespace-nowrap">
                                            <div class="flex justify-end gap-2">
                                                <a href="print_lesson_plan?id=<?= $p['id'] ?>&view=html" target="_blank" class="w-9 h-9 bg-gray-50 text-gray-400 rounded-xl flex items-center justify-center hover:text-indigo-600 transition"><i class="fas fa-eye text-xs"></i></a>
                                                <a href="print_lesson_plan?id=<?= $p['id'] ?>" target="_blank" class="w-9 h-9 bg-gray-50 text-gray-400 rounded-xl flex items-center justify-center hover:text-red-500 transition"><i class="fas fa-file-pdf text-xs"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center text-gray-300 font-bold text-xs uppercase tracking-widest">No approved plans found in this view</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

        </div>
    </main>

    <!-- Floating Action Button for Mobile -->
    <a href="lesson_plans" class="md:hidden fixed bottom-6 right-6 w-14 h-14 bg-indigo-600 text-white rounded-full flex items-center justify-center shadow-2xl z-50 active:scale-95 transition-transform">
        <i class="fas fa-plus text-xl"></i>
    </a>

</body>
</html>
