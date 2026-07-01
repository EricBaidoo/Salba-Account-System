<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../dashboard.php'); exit;
}

// ── Stats ─────────────────────────────────────────────────────────────
$stat_items   = (int)$conn->query("SELECT COUNT(*) as c FROM stationery_items")->fetch_assoc()['c'];
$stat_classes = (int)$conn->query("SELECT COUNT(DISTINCT class) as c FROM stationery_assignments")->fetch_assoc()['c'];
$stat_assigns = (int)$conn->query("SELECT COUNT(*) as c FROM stationery_assignments")->fetch_assoc()['c'];
$stat_brought = (int)$conn->query("SELECT COUNT(*) as c FROM stationery_submissions WHERE brought=1")->fetch_assoc()['c'];
$stat_billed  = (int)$conn->query("SELECT COUNT(*) as c FROM stationery_submissions WHERE billed=1")->fetch_assoc()['c'];

// Recent class assignments
$recent = [];
$rr = $conn->query("
    SELECT sa.class, sa.academic_year, COUNT(*) as item_count,
           MAX(sa.assigned_at) as last_updated
    FROM stationery_assignments sa
    GROUP BY sa.class, sa.academic_year
    ORDER BY last_updated DESC
    LIMIT 6
");
while ($r = $rr->fetch_assoc()) $recent[] = $r;

// ── Printout Settings ─────────────────────────────────────────────────
$print_title       = getSystemSetting($conn, 'stationery_print_title',       'STATIONERY LIST');
$print_instruction = getSystemSetting($conn, 'stationery_print_instruction', 'Dear Parent / Guardian, kindly ensure your child/ward reports with the items listed below. All items should be labelled with the student\'s name. Thank you for your cooperation.');
$print_footer_1    = getSystemSetting($conn, 'stationery_print_footer_1',    'Items must be brought on or before the first week of the term.');
$print_footer_2    = getSystemSetting($conn, 'stationery_print_footer_2',    'All items should be neatly labelled with your child\'s full name and class.');
$print_footer_3    = getSystemSetting($conn, 'stationery_print_footer_3',    'For inquiries, please contact the class teacher or school administration.');

// ── Handle save ───────────────────────────────────────────────────────
$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $user = $_SESSION['username'] ?? 'Admin';
    $print_title       = trim($_POST['print_title'] ?? $print_title);
    $print_instruction = trim($_POST['print_instruction'] ?? $print_instruction);
    $print_footer_1    = trim($_POST['print_footer_1'] ?? $print_footer_1);
    $print_footer_2    = trim($_POST['print_footer_2'] ?? $print_footer_2);
    $print_footer_3    = trim($_POST['print_footer_3'] ?? $print_footer_3);
    setSystemSetting($conn, 'stationery_print_title',       $print_title,       $user);
    setSystemSetting($conn, 'stationery_print_instruction', $print_instruction, $user);
    setSystemSetting($conn, 'stationery_print_footer_1',    $print_footer_1,    $user);
    setSystemSetting($conn, 'stationery_print_footer_2',    $print_footer_2,    $user);
    setSystemSetting($conn, 'stationery_print_footer_3',    $print_footer_3,    $user);
    $saved = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stationery | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-900">
<?php include '../../../includes/sidebar_admin_modern.php'; ?>

<main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">

    <!-- Header -->
    <header class="mb-8">
        <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3 uppercase tracking-wider">
            <a href="../dashboard.php" class="hover:text-indigo-600 transition-colors"><i class="fas fa-home"></i> Admin</a>
            <span>/</span>
            <span class="text-indigo-600">Stationery</span>
        </div>
        <h1 class="text-3xl font-black text-slate-900">Stationery <span class="text-indigo-600">Dashboard</span></h1>
        <p class="text-slate-500 mt-1 text-sm">Manage your school's stationery catalog, class assignments, and student tracking.</p>
    </header>

    <!-- Stats Row -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 text-center">
            <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-box-open text-indigo-600 text-sm"></i>
            </div>
            <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-0.5">Catalog Items</p>
            <p class="text-2xl font-black text-slate-900"><?= $stat_items ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 text-center">
            <div class="w-10 h-10 bg-violet-50 rounded-xl flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-school text-violet-600 text-sm"></i>
            </div>
            <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-0.5">Classes</p>
            <p class="text-2xl font-black text-slate-900"><?= $stat_classes ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 text-center">
            <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-link text-blue-600 text-sm"></i>
            </div>
            <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-0.5">Assignments</p>
            <p class="text-2xl font-black text-slate-900"><?= $stat_assigns ?></p>
        </div>
        <div class="bg-emerald-50 rounded-2xl border border-emerald-100 shadow-sm p-4 text-center">
            <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-check text-emerald-600 text-sm"></i>
            </div>
            <p class="text-[0.5rem] font-black text-emerald-500 uppercase tracking-widest mb-0.5">Brought</p>
            <p class="text-2xl font-black text-emerald-700"><?= $stat_brought ?></p>
        </div>
        <div class="bg-amber-50 rounded-2xl border border-amber-100 shadow-sm p-4 text-center">
            <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-file-invoice-dollar text-amber-600 text-sm"></i>
            </div>
            <p class="text-[0.5rem] font-black text-amber-500 uppercase tracking-widest mb-0.5">Billed</p>
            <p class="text-2xl font-black text-amber-700"><?= $stat_billed ?></p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <a href="items.php" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition-all group">
            <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                <i class="fas fa-box-open text-white"></i>
            </div>
            <h3 class="font-black text-slate-900 text-sm mb-1">Manage Items</h3>
            <p class="text-xs text-slate-500 hidden sm:block">Build the master catalog of stationery items.</p>
            <span class="inline-flex items-center gap-1 text-indigo-600 text-xs font-black mt-2">Go <i class="fas fa-arrow-right text-[0.6rem]"></i></span>
        </a>
        <a href="assign.php" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 hover:border-violet-300 hover:shadow-md transition-all group">
            <div class="w-10 h-10 bg-violet-600 rounded-xl flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                <i class="fas fa-link text-white"></i>
            </div>
            <h3 class="font-black text-slate-900 text-sm mb-1">Assign to Classes</h3>
            <p class="text-xs text-slate-500 hidden sm:block">Assign items to classes with quantity and price.</p>
            <span class="inline-flex items-center gap-1 text-violet-600 text-xs font-black mt-2">Go <i class="fas fa-arrow-right text-[0.6rem]"></i></span>
        </a>
        <a href="index.php" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 hover:border-emerald-300 hover:shadow-md transition-all group">
            <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                <i class="fas fa-table-cells text-white"></i>
            </div>
            <h3 class="font-black text-slate-900 text-sm mb-1">Tracker</h3>
            <p class="text-xs text-slate-500 hidden sm:block">Mark brought items and bill missing ones.</p>
            <span class="inline-flex items-center gap-1 text-emerald-600 text-xs font-black mt-2">Go <i class="fas fa-arrow-right text-[0.6rem]"></i></span>
        </a>
        <a href="settings.php" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 hover:border-slate-300 hover:shadow-md transition-all group">
            <div class="w-10 h-10 bg-slate-700 rounded-xl flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                <i class="fas fa-gear text-white"></i>
            </div>
            <h3 class="font-black text-slate-900 text-sm mb-1">Settings</h3>
            <p class="text-xs text-slate-500 hidden sm:block">Customise printout text, display options, and billing.</p>
            <span class="inline-flex items-center gap-1 text-slate-600 text-xs font-black mt-2">Go <i class="fas fa-arrow-right text-[0.6rem]"></i></span>
        </a>
    </div>

    <!-- Recent Class Assignments -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest">Recent Assignments</h2>
                <a href="assign.php" class="text-xs font-black text-indigo-600 hover:text-indigo-800">View All →</a>
            </div>
            <?php if (empty($recent)): ?>
            <div class="p-10 text-center text-slate-400">
                <i class="fas fa-inbox text-3xl block mb-2 opacity-20"></i>
                <p class="text-sm">No class assignments yet.</p>
            </div>
            <?php else: ?>
            <div class="divide-y divide-slate-50">
                <?php foreach ($recent as $r): ?>
                <a href="assign.php?class=<?= urlencode($r['class']) ?>&academic_year=<?= urlencode($r['academic_year']) ?>"
                   class="flex items-center justify-between px-6 py-3 hover:bg-slate-50 transition-colors">
                    <div>
                        <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($r['class']) ?></p>
                        <p class="text-xs text-slate-400"><?= htmlspecialchars($r['academic_year']) ?></p>
                    </div>
                    <div class="text-right">
                        <span class="bg-indigo-50 text-indigo-700 text-[0.5rem] font-black px-2.5 py-1 rounded-full uppercase tracking-widest">
                            <?= $r['item_count'] ?> item<?= $r['item_count'] != 1 ? 's' : '' ?>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
    </div>

</main>
</body>
</html>
