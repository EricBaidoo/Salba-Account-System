<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || !has_role('admin')) {
    header('Location: ../../login');
    exit;
}

$uid = $_SESSION['user_id'];
$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);

// Filters
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_month = isset($_GET['month']) ? trim($_GET['month']) : '';

// Build Query
// Admins shouldn't see draft_teacher. They might want to see pending_supervisor, pending_admin, and completed.
$where = ["a.status != 'draft_teacher'"]; 
if ($filter_status !== '') {
    $where[] = "a.status = '" . $conn->real_escape_string($filter_status) . "'";
}
if ($filter_month !== '') {
    $where[] = "a.appraisal_month = '" . $conn->real_escape_string($filter_month) . "'";
}

$where_sql = "WHERE " . implode(' AND ', $where);

$sql = "
    SELECT a.id, a.appraisal_month, a.academic_year, a.status, a.overall_score, a.performance_rating, a.supervisor_signature_date,
           sp.full_name as teacher_name, sp.photo_path as teacher_photo,
           sup.full_name as supervisor_name
    FROM appraisals a
    JOIN staff_profiles sp ON a.teacher_id = sp.user_id
    LEFT JOIN staff_profiles sup ON a.supervisor_id = sup.user_id
    $where_sql
    ORDER BY 
        CASE WHEN a.status = 'pending_admin' THEN 1 ELSE 2 END,
        a.id DESC
";
$appraisals_query = $conn->query($sql);
$appraisals = [];
$stats = [
    'pending_admin' => 0,
    'completed' => 0,
    'total' => 0
];

while ($row = $appraisals_query->fetch_assoc()) {
    $appraisals[] = $row;
    $stats['total']++;
    if ($row['status'] === 'pending_admin') {
        $stats['pending_admin']++;
    } elseif ($row['status'] === 'completed') {
        $stats['completed']++;
    }
}

// Get unique months for filter
$months_query = $conn->query("SELECT appraisal_month FROM appraisals WHERE status != 'draft_teacher' GROUP BY appraisal_month ORDER BY MAX(id) DESC");
$available_months = [];
while ($m = $months_query->fetch_assoc()) {
    $available_months[] = $m['appraisal_month'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Appraisals Ledger | School Administrator</title>
    <link rel="icon" href="<?= BASE_URL . getSystemLogo($conn) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

    <?php include '../../includes/top_nav.php'; ?>

    <div class="pt-16 md:pt-20">
        <!-- Header -->
        <header class="bg-gradient-to-r from-slate-900 to-slate-800 shadow-md border-b border-slate-700">
            <div class="max-w-7xl mx-auto px-4 py-8">
                <div class="flex items-center gap-3 text-white/70 text-sm font-semibold mb-2">
                    <a href="dashboard.php" class="hover:text-white transition-colors">School Administrator</a>
                    <i class="fas fa-chevron-right text-[10px]"></i>
                    <span class="text-white">Master Ledger</span>
                </div>
                <h1 class="text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                    <i class="fas fa-gavel opacity-80 text-emerald-400"></i> Final Appraisals Sign-Off
                </h1>
                <p class="text-slate-400 mt-2 text-sm">Review Evaluator evaluations and authorize final performance ratings.</p>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-8">
            
            <?php $flash = get_flash(); foreach($flash as $msg): ?>
                <div class="mb-6 p-4 rounded-lg <?= $msg['type'] == 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?> flex items-center gap-3 shadow-sm">
                    <i class="fas <?= $msg['type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> text-xl"></i>
                    <span class="font-medium text-sm"><?= htmlspecialchars($msg['message']) ?></span>
                </div>
            <?php endforeach; ?>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Awaiting Your Authorization</p>
                        <h4 class="text-3xl font-black text-rose-600"><?= $stats['pending_admin'] ?></h4>
                    </div>
                    <div class="w-14 h-14 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center text-2xl border border-rose-100">
                        <i class="fas fa-signature"></i>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Fully Completed & Archived</p>
                        <h4 class="text-3xl font-black text-emerald-600"><?= $stats['completed'] ?></h4>
                    </div>
                    <div class="w-14 h-14 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center text-2xl border border-emerald-100">
                        <i class="fas fa-archive"></i>
                    </div>
                </div>

                <div class="bg-slate-900 p-6 rounded-2xl shadow-md border border-slate-800 text-white flex items-center justify-between relative overflow-hidden">
                    <i class="fas fa-chart-line absolute right-2 bottom-2 text-6xl text-slate-800 opacity-50"></i>
                    <div class="relative z-10">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total System Appraisals</p>
                        <h4 class="text-3xl font-black"><?= $stats['total'] ?></h4>
                    </div>
                </div>
            </div>

            <!-- Filter Panel -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm mb-6">
                <form method="GET" class="flex flex-wrap items-end gap-5" id="filterForm">
                    <div class="w-full md:w-56">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2 block">Appraisal Month</label>
                        <select name="month" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm font-semibold text-slate-700 transition-all" onchange="this.form.submit()">
                            <option value="">All Months</option>
                            <?php foreach ($available_months as $m): ?>
                                <option value="<?= htmlspecialchars($m) ?>" <?= ($filter_month === $m) ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-full md:w-56">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2 block">Status Filter</label>
                        <select name="status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm font-semibold text-slate-700 transition-all" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="pending_supervisor" <?= ($filter_status === 'pending_supervisor') ? 'selected' : '' ?>>Pending Evaluator</option>
                            <option value="pending_admin" <?= ($filter_status === 'pending_admin') ? 'selected' : '' ?>>Awaiting Admin Sign-off</option>
                            <option value="completed" <?= ($filter_status === 'completed') ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[250px]">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2 block">Live Staff Search</label>
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Search by staff name..." 
                                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm font-semibold text-slate-700 transition-all pl-11">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        </div>
                    </div>
                    <div>
                        <a href="staff_appraisals.php" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 hover:text-slate-900 text-sm font-bold rounded-xl hover:bg-slate-50 transition-all shadow-sm flex items-center gap-2">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Ledger Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" id="appraisalTable">
                        <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wider font-bold text-slate-500">
                            <tr>
                                <th class="px-6 py-4">Staff Member</th>
                                <th class="px-6 py-4">Evaluated By</th>
                                <th class="px-6 py-4">Appraisal Month</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Final Rating</th>
                                <th class="px-6 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($appraisals)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-16 text-center text-slate-400">
                                        <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300 text-2xl mx-auto mb-3">
                                            <i class="fas fa-folder-open"></i>
                                        </div>
                                        <p class="font-bold text-slate-600">No Appraisals Found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appraisals as $a): 
                                    $status_colors = [
                                        'pending_supervisor' => 'bg-amber-100 text-amber-700 border-amber-200',
                                        'pending_admin' => 'bg-purple-100 text-purple-700 border-purple-200',
                                        'completed' => 'bg-emerald-100 text-emerald-700 border-emerald-200'
                                    ];
                                    $status_labels = [
                                        'pending_supervisor' => 'At Evaluator',
                                        'pending_admin' => 'Action Required',
                                        'completed' => 'Finalized'
                                    ];
                                    $color = $status_colors[$a['status']] ?? 'bg-slate-100 text-slate-600 border-slate-200';
                                    $label = $status_labels[$a['status']] ?? 'Unknown';
                                ?>
                                <tr class="hover:bg-slate-50 transition-colors group <?= $a['status'] === 'pending_admin' ? 'bg-purple-50/20' : '' ?>">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <?php if($a['teacher_photo']): ?>
                                                <img src="../../<?= htmlspecialchars($a['teacher_photo']) ?>" class="w-9 h-9 rounded-full object-cover shadow-sm">
                                            <?php else: ?>
                                                <div class="w-9 h-9 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-xs shadow-sm">
                                                    <?= strtoupper(substr($a['teacher_name'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="font-bold text-slate-900 teacher-name"><?= htmlspecialchars($a['teacher_name']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if($a['supervisor_name']): ?>
                                            <div class="font-semibold text-slate-700"><?= htmlspecialchars($a['supervisor_name']) ?></div>
                                            <div class="text-[10px] font-bold text-slate-400 mt-0.5">On: <?= date('M j, Y', strtotime($a['supervisor_signature_date'])) ?></div>
                                        <?php else: ?>
                                            <span class="text-slate-300 italic text-xs font-semibold">Pending Evaluation</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-slate-700">
                                        <?= htmlspecialchars($a['appraisal_month']) ?>
                                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5"><?= htmlspecialchars($a['academic_year']) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider border <?= $color ?>">
                                            <?= $label ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if($a['status'] === 'completed'): ?>
                                            <div class="font-black text-emerald-600"><?= $a['overall_score'] ?>%</div>
                                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-0.5"><?= htmlspecialchars($a['performance_rating']) ?></div>
                                        <?php else: ?>
                                            <span class="text-slate-300 text-sm font-semibold italic">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <?php if($a['status'] === 'pending_admin'): ?>
                                            <a href="evaluate_appraisal.php?id=<?= $a['id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800 shadow-sm transition-all">
                                                Authorize <i class="fas fa-signature"></i>
                                            </a>
                                        <?php elseif($a['status'] === 'completed'): ?>
                                            <a href="evaluate_appraisal.php?id=<?= $a['id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-lg hover:bg-slate-50 hover:text-emerald-600 shadow-sm transition-all">
                                                <i class="fas fa-file-invoice"></i> View Record
                                            </a>
                                        <?php else: ?>
                                            <button disabled class="inline-flex items-center gap-2 px-4 py-2 bg-slate-50 border border-slate-100 text-slate-400 text-xs font-bold rounded-lg cursor-not-allowed">
                                                Waiting...
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </main>
    </div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const rows = document.querySelectorAll('#appraisalTable tbody tr');
        
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase().trim();
            rows.forEach(row => {
                const nameCell = row.querySelector('.teacher-name');
                if (nameCell) {
                    const name = nameCell.textContent.toLowerCase();
                    row.style.display = name.includes(term) ? '' : 'none';
                }
            });
        });
    </script>
</body>
</html>
