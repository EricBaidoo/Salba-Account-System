<?php
// Role-specific sidebar navigation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_role    = $_SESSION['role']     ?? 'staff';
$user_name    = $_SESSION['username'] ?? 'User';
$current_page = basename($_SERVER['PHP_SELF']);
$script_dir   = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
$pages_pos    = strpos($script_dir, '/pages');

// Calculate depth and base path
if ($pages_pos !== false) {
    // depth = number of path components after /pages/
    // e.g. 'finance' = 1, 'finance/fees' = 2
    $relative_after_pages = trim(substr($script_dir, $pages_pos + 6), '/');
    $depth      = $relative_after_pages === '' ? 0 : (substr_count($relative_after_pages, '/') + 1);
    $base_path  = str_repeat('../', $depth);         // back to pages/ root
    $root_path  = str_repeat('../', $depth + 1);     // back to ACCOUNTING root
} else {
    // We're in the ACCOUNTING root directory
    $depth      = 0;
    $base_path  = 'pages/';
    $root_path  = '';
}

// Helper: build a nav link
if (!function_exists('nav_link')) {
    function nav_link($base, $target) {
        return $base . ltrim($target, '/');
    }
}

// Helper: active class for a given filename or partial path
if (!function_exists('nav_active')) {
    function nav_active($page, $current) {
        return ($page === $current)
            ? 'bg-indigo-50 text-indigo-700 font-semibold border-l-4 border-indigo-600'
            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900';
    }
}
?>

<div class="fixed left-0 top-0 bottom-0 w-72 bg-white border-r border-gray-100 z-50 flex flex-col shadow-sm" id="sidebar">

    <!-- ── Brand Header ────────────────────────────────── -->
    <div class="flex-shrink-0 flex items-center gap-3 px-5 py-5 border-b border-gray-100">
        <div class="w-10 h-10 bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-xl flex items-center justify-center text-white flex-shrink-0">
            <i class="fas fa-graduation-cap text-sm"></i>
        </div>
        <div>
            <div class="font-bold text-gray-900 leading-tight">SALBA</div>
            <div class="text-[10px] text-gray-400 font-medium uppercase tracking-wider">Management System</div>
        </div>
    </div>

    <!-- ── Navigation ─────────────────────────────────── -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">

        <!-- Dashboard Home -->
        <a href="<?php echo $root_path; ?>index.php"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page === 'index.php' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
            <i class="fas fa-house w-4 text-center"></i>
            <span class="text-sm">Dashboard Home</span>
        </a>

        <!-- ══ ADMINISTRATION (admin only) ══════════════ -->
        <?php if ($user_role === 'admin'): ?>

        <div class="pt-4 pb-1 px-2">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                <i class="fas fa-shield-halved"></i> Administration
            </span>
        </div>

        <a href="<?php echo nav_link($base_path, 'administration/dashboard.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo nav_active('dashboard.php', $current_page); ?>">
            <i class="fas fa-table-columns w-4 text-center text-indigo-500"></i>
            <span class="text-sm">Admin Overview</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'administration/system_settings.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-sliders w-4 text-center text-gray-400"></i>
            <span class="text-sm">System Settings</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'administration/system_settings.php'); ?>#user-management"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-users-gear w-4 text-center text-gray-400"></i>
            <span class="text-sm">User Management</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'academics/settings.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo nav_active('settings.php', $current_page); ?>">
            <i class="fas fa-graduation-cap w-4 text-center text-purple-500"></i>
            <span class="text-sm">Academic Settings</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'administration/audit_logs.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo nav_active('audit_logs.php', $current_page); ?>">
            <i class="fas fa-clipboard-list w-4 text-center text-gray-400"></i>
            <span class="text-sm">Audit Logs</span>
        </a>

        <!-- Students sub-group -->
        <div class="pt-3 pb-1 px-2">
            <span class="text-[10px] font-semibold text-gray-300 uppercase tracking-widest">Students</span>
        </div>

        <a href="<?php echo nav_link($base_path, 'administration/students/view_students.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-users-viewfinder w-4 text-center text-gray-400"></i>
            <span class="text-sm">Student Directory</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'administration/students/add_student_form.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-user-plus w-4 text-center text-gray-400"></i>
            <span class="text-sm">New Enrollment</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'administration/students/bulk_upload_students.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-file-arrow-up w-4 text-center text-gray-400"></i>
            <span class="text-sm">Bulk Upload</span>
        </a>

        <!-- Staff sub-group -->
        <div class="pt-3 pb-1 px-2">
            <span class="text-[10px] font-semibold text-gray-300 uppercase tracking-widest">Staff</span>
        </div>

        <a href="<?php echo nav_link($base_path, 'administration/staff/view_staff.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-id-card w-4 text-center text-gray-400"></i>
            <span class="text-sm">Staff Directory</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'administration/staff/add_staff.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-user-tie w-4 text-center text-gray-400"></i>
            <span class="text-sm">Add Staff</span>
        </a>

        <?php endif; ?>

        <!-- ══ FINANCE (authorized only) ═══════════════ -->
        <?php if (in_array($user_role, ['admin', 'bursar', 'academic_supervisor'])): ?>
        <div class="pt-4 pb-1 px-2 <?php echo $user_role === 'admin' ? 'border-t border-gray-100 mt-2' : ''; ?>">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                <i class="fas fa-wallet"></i> Finance
            </span>
        </div>

        <a href="<?php echo nav_link($base_path, 'finance/dashboard.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo nav_active('dashboard.php', $current_page); ?>">
            <i class="fas fa-chart-pie w-4 text-center text-green-500"></i>
            <span class="text-sm">Finance Overview</span>
        </a>

        <!-- Fees & Billing -->
        <div class="pt-3 pb-1 px-2">
            <span class="text-[10px] font-semibold text-gray-300 uppercase tracking-widest">Fees & Billing</span>
        </div>

        <a href="<?php echo nav_link($base_path, 'finance/fees/view_fees.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-file-invoice-dollar w-4 text-center text-gray-400"></i>
            <span class="text-sm">Fee Management</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'finance/fees/assign_fee_form.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-hand-holding-dollar w-4 text-center text-gray-400"></i>
            <span class="text-sm">Assign Fees</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'finance/invoices/view_term_bills.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-receipt w-4 text-center text-gray-400"></i>
            <span class="text-sm">Term Invoices</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'finance/invoices/generate_term_bills.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-file-circle-plus w-4 text-center text-gray-400"></i>
            <span class="text-sm">Generate Bills</span>
        </a>

        <!-- Payments -->
        <div class="pt-3 pb-1 px-2">
            <span class="text-[10px] font-semibold text-gray-300 uppercase tracking-widest">Payments</span>
        </div>

        <a href="<?php echo nav_link($base_path, 'finance/payments/view_payments.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-credit-card w-4 text-center text-gray-400"></i>
            <span class="text-sm">Payment Records</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'finance/payments/record_payment_form.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-cash-register w-4 text-center text-gray-400"></i>
            <span class="text-sm">Record Payment</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'finance/reports/student_balances.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-scale-balanced w-4 text-center text-gray-400"></i>
            <span class="text-sm">Student Balances</span>
        </a>

        <!-- Expenses & Reports (admin/supervisor only) -->
        <?php if (in_array($user_role, ['admin', 'supervisor'])): ?>

        <div class="pt-3 pb-1 px-2">
            <span class="text-[10px] font-semibold text-gray-300 uppercase tracking-widest">Expenses & Reports</span>
        </div>

        <a href="<?php echo nav_link($base_path, 'finance/expenses/view_expenses.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-money-bill-wave w-4 text-center text-gray-400"></i>
            <span class="text-sm">Expense Tracking</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'finance/budgets/term_budget.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-chart-column w-4 text-center text-gray-400"></i>
            <span class="text-sm">Term Budget</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'finance/reports/report.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-file-pdf w-4 text-center text-gray-400"></i>
            <span class="text-sm">Finance Reports</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'finance/settings.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo nav_active('settings.php', $current_page); ?>">
            <i class="fas fa-vault w-4 text-center text-emerald-500"></i>
            <span class="text-sm">Finance Settings</span>
        </a>

        <?php endif; ?>
        <?php endif; ?>

        <!-- ══ ACADEMICS ══════════════════════════════════ -->
        <div class="pt-4 pb-1 px-2 border-t border-gray-100 mt-2">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                <i class="fas fa-book"></i> Academics
            </span>
        </div>

        <a href="<?php echo nav_link($base_path, 'academics/dashboard.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo nav_active('dashboard.php', $current_page); ?>">
            <i class="fas fa-graduation-cap w-4 text-center text-purple-500"></i>
            <span class="text-sm">Academics Overview</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'academics/grades.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-star w-4 text-center text-gray-400"></i>
            <span class="text-sm">Grades & Marks</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'academics/attendance.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-calendar-check w-4 text-center text-gray-400"></i>
            <span class="text-sm">Attendance</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'academics/classes.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-chalkboard w-4 text-center text-gray-400"></i>
            <span class="text-sm">Classes</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'academics/subjects.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-book-open w-4 text-center text-gray-400"></i>
            <span class="text-sm">Subjects</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'academics/transcripts.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-scroll w-4 text-center text-gray-400"></i>
            <span class="text-sm">Transcripts</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'academics/report.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-chart-bar w-4 text-center text-gray-400"></i>
            <span class="text-sm">Academic Reports</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'supervisor/lesson_plans.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-file-signature w-4 text-center text-green-500"></i>
            <span class="text-sm">Review Lesson Plans</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'academics/settings.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo nav_active('settings.php', $current_page); ?>">
            <i class="fas fa-sliders-h w-4 text-center text-gray-400"></i>
            <span class="text-sm">Academic Rules</span>
        </a>

        <!-- ══ COMMUNICATION ══════════════════════════════ -->
        <div class="pt-4 pb-1 px-2 border-t border-gray-100 mt-2">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                <i class="fas fa-envelope"></i> Communication
            </span>
        </div>

        <a href="<?php echo nav_link($base_path, 'communication/announcements/view_announcements.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-bullhorn w-4 text-center text-orange-400"></i>
            <span class="text-sm">Announcements</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'communication/messages/view_messages.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i class="fas fa-message w-4 text-center text-orange-400"></i>
            <span class="text-sm">Messages</span>
        </a>

        <a href="<?php echo nav_link($base_path, 'communication/settings.php'); ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo nav_active('settings.php', $current_page); ?>">
            <i class="fas fa-tower-broadcast w-4 text-center text-orange-400"></i>
            <span class="text-sm">Comm Settings</span>
        </a>

    </nav>

    <!-- ── User Footer ─────────────────────────────────── -->
    <div class="flex-shrink-0 bg-white border-t border-gray-100 p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user text-indigo-600 text-xs"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($user_name); ?></p>
                    <p class="text-xs text-gray-400 capitalize"><?php echo htmlspecialchars($user_role); ?></p>
                </div>
            </div>
            <a href="<?php echo $root_path; ?>logout.php"
               class="flex items-center gap-1.5 text-xs text-gray-400 hover:text-red-500 transition-colors flex-shrink-0 ml-2"
               title="Logout">
                <i class="fas fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

</div>
