<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$user_name    = $_SESSION['username'] ?? 'Data Entry';
$current_page = str_replace('.php', '', basename($_SERVER['PHP_SELF']));
if (!defined('BASE_URL')) include_once __DIR__ . '/config.php';
$root_path = defined('BASE_URL') ? BASE_URL : '/';
$base_path = $root_path . 'pages/';
include_once __DIR__ . '/system_settings.php';
if (!function_exists('nav_link')) {
    function nav_link($base, $target) { return $base . ltrim(str_replace('.php', '', $target), '/'); }
}
if (!function_exists('nav_active')) {
    function nav_active($page, $current) {
        $p = str_replace('.php', '', basename($page));
        return ($p === $current) ? 'active' : '';
    }
}
?>
<link rel="stylesheet" href="<?= $root_path ?>assets/css/style.css">
<link rel="stylesheet" href="<?= $root_path ?>assets/css/tailwind.css">
<style>
    :root { --sidebar-bg:#020617; --sidebar-accent:#f59e0b; --sidebar-text:#94a3b8; }
    #sidebar-data-entry { font-family:'Inter',sans-serif; scrollbar-width:thin; scrollbar-color:#1e293b transparent; transition:transform 0.3s cubic-bezier(0.4,0,0.2,1); }
    #sidebar-data-entry::-webkit-scrollbar { width:0.25rem; }
    #sidebar-data-entry::-webkit-scrollbar-thumb { background:#1e293b; border-radius:0.625rem; }
    .nav-item { display:flex; align-items:center; gap:0.75rem; padding:0.75rem 1rem; border-radius:0.75rem; color:var(--sidebar-text); font-size:0.875rem; font-weight:500; transition:all 0.2s; text-decoration:none; margin-bottom:0.125rem; }
    .nav-item:hover { background:rgba(245,158,11,0.1); color:white; transform:translateX(0.25rem); }
    .nav-item.active { background:var(--sidebar-accent); color:white; box-shadow:0 0.625rem 0.9375rem -0.1875rem rgba(245,158,11,0.3); }
    .nav-item i { width:1.25rem; text-align:center; font-size:1rem; color:#475569; transition:color 0.2s; }
    .nav-item:hover i, .nav-item.active i { color:inherit; }
    .nav-group-label { font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:0.1em; color:#334155; padding:1.5rem 1rem 0.5rem; display:flex; align-items:center; gap:0.5rem; }
    .nav-group-label::after { content:''; flex:1; height:0.0625rem; background:#1e293b; }
</style>

<div id="sidebar-backdrop" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-40 hidden opacity-0 lg:hidden" onclick="toggleSidebar()"></div>
<button onclick="toggleSidebar()" class="lg:hidden fixed bottom-6 right-6 z-40 w-14 h-14 bg-amber-500 text-white rounded-full shadow-2xl flex items-center justify-center hover:bg-amber-600 hover:scale-105 active:scale-95 transition-all outline-none ring-4 ring-amber-500/20">
    <i class="fas fa-bars text-xl"></i>
</button>

<aside id="sidebar-data-entry" class="fixed left-0 top-0 bottom-0 w-72 bg-slate-950 z-50 flex flex-col border-r border-slate-900 transform -translate-x-full lg:translate-x-0">

    <!-- Brand -->
    <div class="px-6 py-8 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center overflow-hidden shadow-lg">
                <img src="<?= $root_path . getSystemLogo($conn) ?>" alt="Logo" class="w-full h-full object-contain">
            </div>
            <div>
                <h1 class="text-white font-black tracking-tighter text-xl leading-none">SALBA</h1>
                <p class="text-[0.625rem] text-amber-400 font-bold uppercase tracking-widest mt-1">Data Entry</p>
            </div>
        </div>
        <button onclick="toggleSidebar()" class="lg:hidden text-slate-500 hover:text-white transition-colors">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-4 pb-8">

        <a href="<?= $root_path ?>pages/data_entry/dashboard" class="nav-item <?= nav_active('dashboard', $current_page) ?>">
            <i class="fas fa-gauge-high"></i><span>My Dashboard</span>
        </a>

        <!-- FINANCE -->
        <div class="nav-group-label">Finance</div>
        <a href="<?= nav_link($base_path, 'finance/payments/record_payment_form') ?>" class="nav-item <?= nav_active('record_payment_form', $current_page) ?>">
            <i class="fas fa-money-bill-wave"></i><span>Record Payment</span>
        </a>
        <a href="<?= nav_link($base_path, 'finance/payments/view_payments') ?>" class="nav-item <?= nav_active('view_payments', $current_page) ?>">
            <i class="fas fa-list-check"></i><span>View Payments</span>
        </a>
        <a href="<?= nav_link($base_path, 'finance/expenses/add_expense_form') ?>" class="nav-item <?= nav_active('add_expense_form', $current_page) ?>">
            <i class="fas fa-receipt"></i><span>Record Expense</span>
        </a>
        <a href="<?= nav_link($base_path, 'finance/expenses/view_expenses') ?>" class="nav-item <?= nav_active('view_expenses', $current_page) ?>">
            <i class="fas fa-file-invoice-dollar"></i><span>View Expenses</span>
        </a>
        <a href="<?= nav_link($base_path, 'finance/reports/student_balances') ?>" class="nav-item <?= nav_active('student_balances', $current_page) ?>">
            <i class="fas fa-scale-balanced"></i><span>Student Balances</span>
        </a>

        <!-- STATIONERY -->
        <div class="nav-group-label">Stationery</div>
        <a href="<?= nav_link($base_path, 'administration/stationery/dashboard') ?>" class="nav-item <?= nav_active('dashboard', $current_page) ?>">
            <i class="fas fa-box-open"></i><span>Stationery Overview</span>
        </a>
        <a href="<?= nav_link($base_path, 'administration/stationery/items') ?>" class="nav-item <?= nav_active('items', $current_page) ?>">
            <i class="fas fa-boxes-stacked"></i><span>Catalog Items</span>
        </a>
        <a href="<?= nav_link($base_path, 'administration/stationery/assign') ?>" class="nav-item <?= nav_active('assign', $current_page) ?>">
            <i class="fas fa-link"></i><span>Assign to Classes</span>
        </a>
        <a href="<?= nav_link($base_path, 'administration/stationery/index') ?>" class="nav-item <?= nav_active('index', $current_page) ?>">
            <i class="fas fa-table-cells"></i><span>Tracker</span>
        </a>

    </nav>

    <!-- User Footer -->
    <div class="px-4 pb-6">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 flex items-center gap-3">
            <div class="w-9 h-9 bg-amber-500 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-user text-white text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-white font-bold text-sm truncate">@<?= htmlspecialchars($user_name) ?></p>
                <p class="text-amber-400 text-[0.6rem] font-bold uppercase tracking-wider">Data Entry</p>
            </div>
            <a href="<?= $root_path ?>logout" class="text-slate-500 hover:text-rose-400 transition-colors" title="Logout">
                <i class="fas fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>

<script>
function toggleSidebar() {
    const s = document.getElementById('sidebar-data-entry');
    const b = document.getElementById('sidebar-backdrop');
    const open = !s.classList.contains('-translate-x-full');
    s.classList.toggle('-translate-x-full', open);
    b.classList.toggle('hidden', open);
    setTimeout(() => b.classList.toggle('opacity-0', open), open ? 0 : 10);
}
</script>
