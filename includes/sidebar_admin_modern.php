<?php
/**
 * SALBA Montessori Management System
 * PREMIUM ADMIN SIDEBAR (Modern Redesign)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_role    = $_SESSION['role']     ?? 'staff';
$user_name    = $_SESSION['username'] ?? 'User';
$current_page = str_replace('.php', '', basename($_SERVER['PHP_SELF']));
// We now rely on BASE_URL from config via db_connect.php
$root_path = BASE_URL;
$base_path = BASE_URL . 'pages/';

// Helper: Smart Nav Link (Extension-less)
if (!function_exists('nav_link')) {
    function nav_link($base, $target) {
        return $base . ltrim(str_replace('.php', '', $target), '/');
    }
}

// Helper: Active State Detection
if (!function_exists('nav_active')) {
    function nav_active($page, $current) {
        $p = str_replace('.php', '', basename($page));
        return ($p === $current) ? 'active' : '';
    }
}
?>

<style>
    :root {
        --sidebar-bg: #020617; /* Slate 950 */
        --sidebar-accent: #6366f1; /* Indigo 500 */
        --sidebar-text: #94a3b8; /* Slate 400 */
        --sidebar-hover-bg: rgba(99, 102, 241, 0.1);
    }

    #sidebar-modern {
        font-family: 'Inter', sans-serif;
        scrollbar-width: thin;
        scrollbar-color: #1e293b transparent;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    #sidebar-modern::-webkit-scrollbar {
        width: 0.25rem;
    }

    #sidebar-modern::-webkit-scrollbar-thumb {
        background: #1e293b;
        border-radius: 0.625rem;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        color: var(--sidebar-text);
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        margin-bottom: 0.125rem;
    }

    .nav-item:hover {
        background: var(--sidebar-hover-bg);
        color: white;
        transform: translateX(0.25rem);
    }

    .nav-item.active {
        background: var(--sidebar-accent);
        color: white;
        box-shadow: 0 0.625rem 0.9375rem -0.1875rem rgba(99, 102, 241, 0.3);
    }

    .nav-item.active i {
        color: white;
    }

    .nav-item i {
        width: 1.25rem;
        text-align: center;
        font-size: 1rem;
        color: #475569;
        transition: color 0.2s;
    }

    .nav-item:hover i {
        color: var(--sidebar-accent);
    }

    .nav-group-label {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #334155;
        padding: 1.5rem 1rem 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .nav-group-label::after {
        content: '';
        flex: 1;
        height: 0.0625rem;
        background: #1e293b;
    }

    .user-pill {
        background: #0f172a;
        border: 0.0625rem solid #1e293b;
        border-radius: 1rem;
        padding: 0.75rem;
        transition: all 0.3s;
    }

    .user-pill:hover {
        border-color: var(--sidebar-accent);
        background: #1e293b;
    }

    /* Mobile Backdrop */
    #sidebar-backdrop {
        transition: opacity 0.3s ease;
    }
</style>

<!-- Mobile Backdrop Overly -->
<div id="sidebar-backdrop" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-40 hidden opacity-0 lg:hidden" onclick="toggleSidebar()"></div>

<!-- Mobile Floating Menu Toggle -->
<button onclick="toggleSidebar()" class="lg:hidden fixed bottom-6 right-6 z-40 w-14 h-14 bg-indigo-600 text-white rounded-full shadow-2xl flex items-center justify-center hover:bg-indigo-700 hover:scale-105 active:scale-95 transition-all outline-none ring-4 ring-indigo-500/20">
    <i class="fas fa-bars text-xl"></i>
</button>

<aside id="sidebar-modern" class="fixed left-0 top-0 bottom-0 w-72 bg-slate-950 z-50 flex flex-col border-r border-slate-900 transform -translate-x-full lg:translate-x-0">

    <!-- Branding & Close Button -->
    <div class="px-6 py-8 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center overflow-hidden shadow-lg shadow-indigo-500/20 ring-4 ring-indigo-500/10">
                <img src="<?= $root_path . getSystemLogo($conn) ?>" alt="Logo" class="w-full h-full object-contain">
            </div>
            <div>
                <h1 class="text-white font-black tracking-tighter text-xl leading-none">SALBA</h1>
                <p class="text-[0.625rem] text-slate-500 font-bold uppercase tracking-widest mt-1">Management Hub</p>
            </div>
        </div>
        <!-- Mobile Close -->
        <button onclick="toggleSidebar()" class="lg:hidden text-slate-500 hover:text-white transition-colors">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>

    <!-- Scrollable Nav -->
    <nav class="flex-1 overflow-y-auto px-4 pb-8 custom-scrollbar">
        
        <!-- Dashboard Section -->
        <a href="<?= $root_path ?>index" class="nav-item <?= nav_active('index', $current_page) ?>">
            <i class="fas fa-house"></i>
            <span>System Dashboard</span>
        </a>

        <!-- SYSTEM MANAGEMENT -->
        <div class="nav-group-label">System Control</div>
        
        <a href="<?= nav_link($base_path, 'administration/dashboard') ?>" class="nav-item <?= nav_active('dashboard', $current_page) ?>">
            <i class="fas fa-gauge-high"></i>
            <span>Admin Overview</span>
        </a>
        <a href="<?= nav_link($base_path, 'administration/users') ?>" class="nav-item <?= nav_active('users', $current_page) ?>">
            <i class="fas fa-users-gear"></i>
            <span>Account Security</span>
        </a>
        <a href="<?= nav_link($base_path, 'administration/system_settings') ?>" class="nav-item <?= nav_active('system_settings', $current_page) ?>">
            <i class="fas fa-sliders"></i>
            <span>Registry Settings</span>
        </a>
        <a href="<?= nav_link($base_path, 'administration/audit_logs') ?>" class="nav-item <?= nav_active('audit_logs', $current_page) ?>">
            <i class="fas fa-fingerprint"></i>
            <span>System Audit Trail</span>
        </a>

        <!-- PERSONNEL HUB -->
        <div class="nav-group-label">Personnel</div>

        <a href="<?= nav_link($base_path, 'administration/staff/view_staff') ?>" class="nav-item <?= nav_active('view_staff', $current_page) ?>">
            <i class="fas fa-id-card"></i>
            <span>Staff Directory</span>
        </a>
        <a href="<?= nav_link($base_path, 'administration/staff/add_staff') ?>" class="nav-item <?= nav_active('add_staff', $current_page) ?>">
            <i class="fas fa-user-plus"></i>
            <span>New Recruitment</span>
        </a>
        <a href="<?= nav_link($base_path, 'administration/students/view_students') ?>" class="nav-item <?= nav_active('view_students', $current_page) ?>">
            <i class="fas fa-user-graduate"></i>
            <span>Student Registry</span>
        </a>
        <a href="<?= nav_link($base_path, 'administration/students/add_student_form') ?>" class="nav-item <?= nav_active('add_student_form', $current_page) ?>">
            <i class="fas fa-user-plus"></i>
            <span>New Enrollment</span>
        </a>
        <a href="<?= nav_link($base_path, 'administration/staff_attendance') ?>" class="nav-item <?= nav_active('staff_attendance', $current_page) ?>">
            <i class="fas fa-clock-rotate-left"></i>
            <span>Staff Attendance Hub</span>
        </a>

        <!-- ACADEMIC CENTER -->
        <div class="nav-group-label">Academics</div>

        <a href="<?= nav_link($base_path, 'academics/dashboard') ?>" class="nav-item <?= nav_active('dashboard', $current_page) ?>">
            <i class="fas fa-book-open"></i>
            <span>Learning Hub</span>
        </a>
        <a href="<?= nav_link($base_path, 'academics/grades') ?>" class="nav-item <?= nav_active('grades', $current_page) ?>">
            <i class="fas fa-star"></i>
            <span>Academic Performance</span>
        </a>
        <a href="<?= nav_link($base_path, 'academics/attendance') ?>" class="nav-item <?= nav_active('attendance', $current_page) ?>">
            <i class="fas fa-calendar-check"></i>
            <span>Student Attendance Hub</span>
        </a>
        <a href="<?= nav_link($base_path, 'academics/transcripts') ?>" class="nav-item <?= nav_active('transcripts', $current_page) ?>">
            <i class="fas fa-scroll"></i>
            <span>Transcripts</span>
        </a>

        <!-- FINANCE HUB -->
        <div class="nav-group-label">Financials</div>

        <a href="<?= nav_link($base_path, 'finance/dashboard') ?>" class="nav-item <?= nav_active('dashboard', $current_page) ?>">
            <i class="fas fa-wallet"></i>
            <span>Finance Board</span>
        </a>
        <a href="<?= nav_link($base_path, 'finance/fees/view_fees') ?>" class="nav-item <?= nav_active('view_fees', $current_page) ?>">
            <i class="fas fa-file-invoice"></i>
            <span>Invoicing Options</span>
        </a>
        <a href="<?= nav_link($base_path, 'finance/payments/view_payments') ?>" class="nav-item <?= nav_active('view_payments', $current_page) ?>">
            <i class="fas fa-cash-register"></i>
            <span>Payments & Receipts</span>
        </a>
        <a href="<?= nav_link($base_path, 'finance/expenses/view_expenses') ?>" class="nav-item <?= nav_active('view_expenses', $current_page) ?>">
            <i class="fas fa-receipt"></i>
            <span>Expense Tracker</span>
        </a>
        <a href="<?= nav_link($base_path, 'finance/budgets/semester_budget') ?>" class="nav-item <?= nav_active('semester_budget', $current_page) ?>">
            <i class="fas fa-sack-dollar"></i>
            <span>Trimester Budget</span>
        </a>
        <a href="<?= nav_link($base_path, 'finance/reports/report') ?>" class="nav-item <?= nav_active('report', $current_page) ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Financial Reporting</span>
        </a>
        <a href="<?= nav_link($base_path, 'finance/reports/student_balances') ?>" class="nav-item <?= nav_active('student_balances', $current_page) ?>">
            <i class="fas fa-chart-pie"></i>
            <span>Outstanding Balances</span>
        </a>

        <!-- COMMUNICATION -->
        <div class="nav-group-label">Engagement</div>

        <a href="<?= nav_link($base_path, 'communication/announcements/view_announcements') ?>" class="nav-item <?= nav_active('view_announcements', $current_page) ?>">
            <i class="fas fa-bullhorn"></i>
            <span>Announcements</span>
        </a>
    </nav>

    <!-- Footer Profile -->
    <div class="p-5 mt-auto bg-slate-900/50 border-t border-slate-900">
        <div class="user-pill group">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-400 group-hover:bg-indigo-500 group-hover:text-white transition-all shadow-inner">
                        <i class="fas fa-user-shield text-base"></i>
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-[0.5625rem] font-black text-indigo-400 uppercase tracking-[0.2em] mb-0.5">Welcome</p>
                        <p class="text-xs font-black text-white truncate"><?= htmlspecialchars($user_name) ?></p>
                        <p class="text-[0.5625rem] text-slate-500 uppercase font-black tracking-widest mt-0.5"><?= htmlspecialchars($user_role) ?></p>
                    </div>
                </div>
                <a href="<?= $root_path ?>logout" class="w-9 h-9 rounded-xl bg-slate-900 text-slate-500 hover:bg-rose-500 hover:text-white flex items-center justify-center transition-all border border-slate-800 shadow-sm" title="Secure Logout">
                    <i class="fas fa-power-off text-xs"></i>
                </a>
            </div>
        </div>
    </div>

</aside>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar-modern');
    const backdrop = document.getElementById('sidebar-backdrop');
    
    if (sidebar.classList.contains('-translate-x-full')) {
        // Open
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
        setTimeout(() => backdrop.classList.add('opacity-100'), 10);
    } else {
        // Close
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.remove('opacity-100');
        setTimeout(() => backdrop.classList.add('hidden'), 300);
    }
}
</script>
