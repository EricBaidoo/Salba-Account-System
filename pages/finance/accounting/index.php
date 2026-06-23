<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

// ── Semester / Year Filters ────────────────────────────────────────────────
$current_term     = getCurrentSemester($conn);
$current_year     = getAcademicYear($conn);
$selected_term    = $_GET['semester']      ?? $current_term;
$selected_year    = $_GET['academic_year'] ?? $current_year;
$filter_all       = ($selected_term === 'all' || $selected_term === '');
$page             = max(1, intval($_GET['page'] ?? 1));
$per_page         = 25;
$offset           = ($page - 1) * $per_page;

// ── Build semester-scoped journal entry IDs via UNION subquery ─────────────
// journal_entries has no semester column — semester lives in the referenced tables
// We join back via reference_type + reference_id to get valid IDs for the period.
if (!$filter_all) {
    $sem_subquery = "
        (SELECT j.id FROM journal_entries j
            JOIN student_fees sf ON j.reference_type='StudentBill' AND j.reference_id=sf.id
            WHERE sf.semester=? AND sf.academic_year=?
         UNION
         SELECT j.id FROM journal_entries j
            JOIN payments p ON j.reference_type='Payment' AND j.reference_id=p.id
            WHERE p.semester=? AND p.academic_year=?
         UNION
         SELECT j.id FROM journal_entries j
            JOIN expenses e ON j.reference_type='Expense' AND j.reference_id=e.id
            WHERE e.semester=? AND e.academic_year=?)
    ";
    $sem_params_6 = [$selected_term, $selected_year, $selected_term, $selected_year, $selected_term, $selected_year];
    $je_id_where  = "AND j.id IN $sem_subquery";
    $tb_id_where  = "AND l.journal_entry_id IN $sem_subquery";
} else {
    $sem_subquery = null;
    $sem_params_6 = [];
    $je_id_where  = "";
    $tb_id_where  = "";
}

// ── Trial Balance ──────────────────────────────────────────────────────────
if (!$filter_all) {
    $tb_sql = "
        SELECT a.account_code, a.name, a.type,
               COALESCE(SUM(l.debit),  0) AS total_debit,
               COALESCE(SUM(l.credit), 0) AS total_credit
        FROM accounts a
        LEFT JOIN journal_lines l ON a.id = l.account_id $tb_id_where
        GROUP BY a.id
        HAVING total_debit > 0 OR total_credit > 0
        ORDER BY a.account_code ASC
    ";
    $tb_stmt = $conn->prepare($tb_sql);
    $tb_stmt->bind_param("ssssss", ...$sem_params_6);
} else {
    $tb_sql = "
        SELECT a.account_code, a.name, a.type,
               COALESCE(SUM(l.debit),  0) AS total_debit,
               COALESCE(SUM(l.credit), 0) AS total_credit
        FROM accounts a
        LEFT JOIN journal_lines l ON a.id = l.account_id
        GROUP BY a.id
        HAVING total_debit > 0 OR total_credit > 0
        ORDER BY a.account_code ASC
    ";
    $tb_stmt = $conn->prepare($tb_sql);
}
$tb_stmt->execute();
$tb_result = $tb_stmt->get_result();

$tb_rows   = [];
$total_dr  = 0;
$total_cr  = 0;
while ($row = $tb_result->fetch_assoc()) {
    $dr = (float)$row['total_debit'];
    $cr = (float)$row['total_credit'];
    $net_dr = ($dr > $cr) ? $dr - $cr : 0;
    $net_cr = ($cr > $dr) ? $cr - $dr : 0;
    if ($net_dr == 0 && $net_cr == 0) continue;
    $total_dr += $net_dr;
    $total_cr += $net_cr;
    $row['net_dr'] = $net_dr;
    $row['net_cr'] = $net_cr;
    $tb_rows[] = $row;
}
$tb_stmt->close();

// ── KPI: entry counts & total amounts ────────────────────────────────────
if (!$filter_all) {
    $kpi_sql = "SELECT COUNT(DISTINCT j.id) as cnt,
                       COALESCE(SUM(jl.debit),0) as total_debit,
                       COALESCE(SUM(jl.credit),0) as total_credit
                FROM journal_entries j
                JOIN journal_lines jl ON jl.journal_entry_id = j.id
                WHERE j.id IN $sem_subquery";
    $kpi_stmt = $conn->prepare($kpi_sql);
    $kpi_stmt->bind_param("ssssss", ...$sem_params_6);
} else {
    $kpi_stmt = $conn->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(jl.debit),0) as total_debit, COALESCE(SUM(jl.credit),0) as total_credit FROM journal_entries j JOIN journal_lines jl ON jl.journal_entry_id = j.id");
}
$kpi_stmt->execute();
$kpi = $kpi_stmt->get_result()->fetch_assoc();
$kpi_stmt->close();

$kpi_entries     = $kpi['cnt'] ?? 0;
$kpi_total_dr    = (float)($kpi['total_debit'] ?? 0);
$kpi_total_cr    = (float)($kpi['total_credit'] ?? 0);
$kpi_net_balance = $kpi_total_dr - $kpi_total_cr;

// ── Journal Entries — paginated ───────────────────────────────────────────
if (!$filter_all) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM journal_entries j WHERE j.id IN $sem_subquery");
    $count_stmt->bind_param("ssssss", ...$sem_params_6);
} else {
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM journal_entries j");
}
$count_stmt->execute();
$total_entries = $count_stmt->get_result()->fetch_row()[0];
$count_stmt->close();
$total_pages = max(1, ceil($total_entries / $per_page));

if (!$filter_all) {
    $je_sql = "SELECT j.id, j.entry_date, j.reference_type, j.description,
                      (SELECT SUM(debit) FROM journal_lines WHERE journal_entry_id = j.id) as total_amount
               FROM journal_entries j
               WHERE j.id IN $sem_subquery
               ORDER BY j.entry_date DESC, j.id DESC LIMIT ? OFFSET ?";
    $je_stmt = $conn->prepare($je_sql);
    $je_bind = array_merge($sem_params_6, [$per_page, $offset]);
    $je_stmt->bind_param("ssssssii", ...$je_bind);
} else {
    $je_stmt = $conn->prepare("SELECT j.id, j.entry_date, j.reference_type, j.description, (SELECT SUM(debit) FROM journal_lines WHERE journal_entry_id = j.id) as total_amount FROM journal_entries j ORDER BY j.entry_date DESC, j.id DESC LIMIT ? OFFSET ?");
    $je_stmt->bind_param("ii", $per_page, $offset);
}
$je_stmt->execute();
$je_result = $je_stmt->get_result();
$je_rows   = [];
$je_ids    = [];
while ($row = $je_result->fetch_assoc()) {
    $je_rows[] = $row;
    $je_ids[]  = (int)$row['id'];
}
$je_stmt->close();

// Batch-fetch all journal lines for visible entries (no N+1)
$lines_by_je = [];
if (!empty($je_ids)) {
    $ph   = implode(',', $je_ids);
    $lres = $conn->query("SELECT l.*, a.account_code, a.name FROM journal_lines l JOIN accounts a ON l.account_id = a.id WHERE l.journal_entry_id IN ($ph) ORDER BY l.journal_entry_id, l.id");
    while ($ln = $lres->fetch_assoc()) {
        $lines_by_je[$ln['journal_entry_id']][] = $ln;
    }
}

// Academic year options — from source tables since journal_entries has no academic_year
$yr_opts = [];
$yr_rs = $conn->query("SELECT DISTINCT academic_year FROM payments WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
while ($y = $yr_rs->fetch_assoc()) if ($y['academic_year']) $yr_opts[] = $y['academic_year'];
if (!in_array($current_year, $yr_opts)) array_unshift($yr_opts, $current_year);

$school_name  = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$display_year = formatAcademicYearDisplay($conn, $selected_year);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Ledger | <?= htmlspecialchars($school_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }
        .stat-card { transition: all 0.3s cubic-bezier(0.4,0,0.2,1); }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 40px -8px rgba(0,0,0,0.12); }
        .je-row { transition: background 0.15s; }
        .je-lines { display: none; }
        .je-lines.open { display: block; }
        .je-toggle { cursor: pointer; }
        .je-toggle .chevron { transition: transform 0.2s; }
        .je-toggle.open .chevron { transform: rotate(180deg); }
        .badge-studentbill { background:#ede9fe; color:#6d28d9; }
        .badge-payment     { background:#d1fae5; color:#065f46; }
        .badge-expense     { background:#fee2e2; color:#991b1b; }
        .badge-default     { background:#f1f5f9; color:#475569; }
        @media print { .no-print { display:none!important; } .admin-main-content { margin-left:0!important; } }
    </style>
</head>
<body class="text-slate-900 leading-relaxed">
    <div class="no-print"><?php include '../../../includes/sidebar.php'; ?></div>
    <main class="admin-main-content lg:ml-72 min-h-screen pb-16">

        <!-- Sticky Header / Breadcrumb -->
        <div class="bg-white border-b border-slate-200 px-6 py-4 sticky top-0 z-30 no-print -mx-0">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">
                <a href="../dashboard.php" class="hover:text-indigo-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span class="text-slate-200">/</span>
                <span class="text-indigo-600">Accounting Ledger</span>
            </div>
        </div>

        <div class="px-6 md:px-10 pt-8">

            <!-- Page Header -->
            <header class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div>
                    <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                        <span class="w-8 h-[2px] bg-indigo-600"></span>
                        Double-Entry System
                    </div>
                    <h1 class="text-4xl font-black text-slate-900 tracking-tight">Accounting <span class="text-indigo-600">Ledger</span></h1>
                    <p class="text-slate-500 mt-2 font-medium text-sm">Trial balance and general journal — <?= htmlspecialchars($filter_all ? 'All Periods' : "$selected_term · $display_year") ?></p>
                </div>
                <div class="flex flex-wrap gap-3 no-print">
                    <a href="coa.php" class="flex items-center gap-2 bg-slate-100 text-slate-700 font-black text-[0.625rem] uppercase tracking-widest px-5 py-3 rounded-xl hover:bg-slate-200 transition-all">
                        <i class="fas fa-sitemap"></i> Chart of Accounts
                    </a>
                    <a href="financials.php?semester=<?= urlencode($selected_term) ?>&academic_year=<?= urlencode($selected_year) ?>" class="flex items-center gap-2 bg-indigo-600 text-white font-black text-[0.625rem] uppercase tracking-widest px-5 py-3 rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-600/25">
                        <i class="fas fa-file-invoice-dollar"></i> Financial Statements
                    </a>
                </div>
            </header>

            <!-- Filter Bar -->
            <section class="no-print bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-8">
                <h3 class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-[0.3em] mb-5 flex items-center gap-3">Period Filter <span class="flex-1 h-px bg-slate-100"></span></h3>
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1 block">Semester</label>
                        <select name="semester" class="bg-slate-50 border border-slate-100 rounded-xl px-4 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500 min-w-[10rem]">
                            <option value="all" <?= $filter_all ? 'selected' : '' ?>>All Semesters</option>
                            <?php foreach (getAvailableSemesters($conn) as $s): ?>
                                <option value="<?= htmlspecialchars($s) ?>" <?= $selected_term === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1 block">Academic Year</label>
                        <select name="academic_year" class="bg-slate-50 border border-slate-100 rounded-xl px-4 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500 min-w-[9rem]">
                            <?php foreach ($yr_opts as $y): ?>
                                <option value="<?= htmlspecialchars($y) ?>" <?= $selected_year === $y ? 'selected' : '' ?>><?= formatAcademicYearDisplay($conn, $y) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="bg-indigo-600 text-white font-black text-[0.5625rem] uppercase tracking-widest px-5 py-3 rounded-xl hover:bg-indigo-500 transition-all shadow-md shadow-indigo-600/20">
                        <i class="fas fa-filter mr-1"></i> Apply
                    </button>
                    <?php if (!$filter_all): ?>
                    <a href="index.php" class="text-slate-400 font-black text-[0.5625rem] uppercase tracking-widest px-4 py-3 rounded-xl hover:bg-slate-50 transition-all">Clear</a>
                    <?php endif; ?>
                </form>
            </section>

            <!-- KPI Cards -->
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                <!-- Total Debits -->
                <div class="stat-card bg-white rounded-[1.75rem] p-6 border border-slate-100 shadow-sm">
                    <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600 mb-4"><i class="fas fa-arrow-up-right-dots"></i></div>
                    <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1">Total Debits</p>
                    <h3 class="text-xl font-black text-slate-900">₵<?= number_format($kpi_total_dr, 2) ?></h3>
                </div>
                <!-- Total Credits -->
                <div class="stat-card bg-white rounded-[1.75rem] p-6 border border-slate-100 shadow-sm">
                    <div class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center text-rose-600 mb-4"><i class="fas fa-arrow-down-right"></i></div>
                    <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1">Total Credits</p>
                    <h3 class="text-xl font-black text-slate-900">₵<?= number_format($kpi_total_cr, 2) ?></h3>
                </div>
                <!-- Net Balance -->
                <div class="stat-card bg-white rounded-[1.75rem] p-6 border border-slate-100 shadow-sm">
                    <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600 mb-4"><i class="fas fa-scale-balanced"></i></div>
                    <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1">Net (DR − CR)</p>
                    <h3 class="text-xl font-black <?= $kpi_net_balance >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>">₵<?= number_format(abs($kpi_net_balance), 2) ?></h3>
                    <p class="text-[0.5rem] font-bold text-slate-300 mt-0.5 uppercase"><?= $kpi_net_balance >= 0 ? 'Surplus' : 'Deficit' ?></p>
                </div>
                <!-- Journal Entries -->
                <div class="stat-card bg-white rounded-[1.75rem] p-6 border border-slate-100 shadow-sm">
                    <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center text-amber-600 mb-4"><i class="fas fa-list-check"></i></div>
                    <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1">Journal Entries</p>
                    <h3 class="text-xl font-black text-slate-900"><?= number_format($total_entries) ?></h3>
                </div>
            </section>

            <!-- Balance Alert -->
            <?php $diff = abs($total_dr - $total_cr); ?>
            <?php if ($diff > 0.01): ?>
            <div class="mb-6 flex items-center gap-3 bg-amber-50 border border-amber-200 text-amber-800 font-bold text-sm px-5 py-4 rounded-2xl no-print">
                <i class="fas fa-triangle-exclamation text-amber-500"></i>
                Trial Balance is <strong>out of balance</strong> by ₵<?= number_format($diff, 2) ?>. Check for unposted entries.
            </div>
            <?php else: ?>
            <div class="mb-6 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 font-bold text-sm px-5 py-4 rounded-2xl no-print">
                <i class="fas fa-check-circle text-emerald-500"></i>
                Trial Balance is <strong>balanced</strong>. Debits equal Credits.
            </div>
            <?php endif; ?>

            <!-- Main Two-Column Grid -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">

                <!-- ── Trial Balance ── -->
                <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                    <div class="px-7 py-5 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white flex items-center justify-between">
                        <div>
                            <h2 class="text-[0.625rem] font-black text-slate-400 uppercase tracking-[0.3em]">Trial Balance</h2>
                            <p class="text-sm font-black text-slate-800 mt-0.5"><?= $filter_all ? 'All Periods' : htmlspecialchars("$selected_term · $display_year") ?></p>
                        </div>
                        <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-500">
                            <i class="fas fa-scale-balanced"></i>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest bg-slate-50/60 border-b border-slate-100">
                                    <th class="px-7 py-4">Account</th>
                                    <th class="px-5 py-4 text-right text-emerald-600">Debit</th>
                                    <th class="px-7 py-4 text-right text-rose-600">Credit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php if (empty($tb_rows)): ?>
                                <tr><td colspan="3" class="px-7 py-12 text-center text-slate-400 font-medium">No data for this period.</td></tr>
                                <?php else: ?>
                                <?php foreach ($tb_rows as $row):
                                    $type = strtolower($row['type']);
                                    $type_colors = [
                                        'asset'     => 'bg-blue-50 text-blue-700',
                                        'liability' => 'bg-rose-50 text-rose-700',
                                        'equity'    => 'bg-purple-50 text-purple-700',
                                        'revenue'   => 'bg-emerald-50 text-emerald-700',
                                        'expense'   => 'bg-orange-50 text-orange-700',
                                    ];
                                    $tc = $type_colors[$type] ?? 'bg-slate-50 text-slate-500';
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors group">
                                    <td class="px-7 py-4">
                                        <div class="font-black text-slate-800 text-sm"><?= $row['account_code'] ?> — <?= htmlspecialchars($row['name']) ?></div>
                                        <span class="text-[0.4375rem] font-black uppercase tracking-widest px-2 py-0.5 rounded-full mt-1 inline-block <?= $tc ?>"><?= ucfirst($type) ?></span>
                                    </td>
                                    <td class="px-5 py-4 text-right font-black text-emerald-700 text-sm"><?= $row['net_dr'] > 0 ? '₵'.number_format($row['net_dr'], 2) : '<span class="text-slate-200">—</span>' ?></td>
                                    <td class="px-7 py-4 text-right font-black text-rose-700 text-sm"><?= $row['net_cr'] > 0 ? '₵'.number_format($row['net_cr'], 2) : '<span class="text-slate-200">—</span>' ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <?php if (!empty($tb_rows)): ?>
                            <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                                <tr>
                                    <td class="px-7 py-4 font-black text-slate-800 uppercase tracking-widest text-[0.625rem]">Totals</td>
                                    <td class="px-5 py-4 text-right font-black text-emerald-700 text-sm underline decoration-double decoration-emerald-300">₵<?= number_format($total_dr, 2) ?></td>
                                    <td class="px-7 py-4 text-right font-black text-rose-700 text-sm underline decoration-double decoration-rose-300">₵<?= number_format($total_cr, 2) ?></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- ── General Journal ── -->
                <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                    <div class="px-7 py-5 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white flex items-center justify-between">
                        <div>
                            <h2 class="text-[0.625rem] font-black text-slate-400 uppercase tracking-[0.3em]">General Journal</h2>
                            <p class="text-sm font-black text-slate-800 mt-0.5">Entries <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_entries) ?> of <?= number_format($total_entries) ?></p>
                        </div>
                        <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center text-amber-500">
                            <i class="fas fa-list-check"></i>
                        </div>
                    </div>

                    <div class="overflow-y-auto max-h-[680px] divide-y divide-slate-50">
                        <?php if (empty($je_rows)): ?>
                        <div class="p-12 text-center text-slate-400 font-medium">No journal entries for this period.</div>
                        <?php else: ?>
                        <?php foreach ($je_rows as $je):
                            $lines = $lines_by_je[$je['id']] ?? [];
                            $ref   = strtolower($je['reference_type']);
                            $badge = match($ref) {
                                'studentbill' => 'badge-studentbill',
                                'payment'     => 'badge-payment',
                                'expense'     => 'badge-expense',
                                default       => 'badge-default',
                            };
                            $icon = match($ref) {
                                'studentbill' => 'fa-file-invoice',
                                'payment'     => 'fa-credit-card',
                                'expense'     => 'fa-receipt',
                                default       => 'fa-bookmark',
                            };
                        ?>
                        <div class="je-entry group">
                            <!-- Entry Header (clickable) -->
                            <div class="je-toggle flex items-start gap-3 px-6 py-4 hover:bg-slate-50/60 transition-colors" data-id="<?= $je['id'] ?>">
                                <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5 <?= $badge ?>">
                                    <i class="fas <?= $icon ?> text-[0.625rem]"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-0.5">
                                        <span class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest">JE #<?= str_pad($je['id'], 5, '0', STR_PAD_LEFT) ?></span>
                                        <span class="text-[0.4375rem] font-black uppercase tracking-widest px-2 py-0.5 rounded-full <?= $badge ?>"><?= htmlspecialchars($je['reference_type']) ?></span>
                                    </div>
                                    <p class="text-xs font-bold text-slate-700 truncate max-w-[240px]"><?= htmlspecialchars(mb_strimwidth($je['description'], 0, 70, '…')) ?></p>
                                    <p class="text-[0.5rem] font-bold text-slate-400 mt-0.5 uppercase tracking-wider"><?= date('M j, Y', strtotime($je['entry_date'])) ?></p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <div class="text-sm font-black text-slate-800">₵<?= number_format((float)$je['total_amount'], 2) ?></div>
                                    <i class="fas fa-chevron-down chevron text-slate-300 text-[0.5rem] mt-1 block text-center"></i>
                                </div>
                            </div>
                            <!-- Lines (expandable) -->
                            <div class="je-lines bg-slate-50/40 border-t border-slate-100" id="je-lines-<?= $je['id'] ?>">
                                <table class="w-full text-xs">
                                    <tbody>
                                        <?php foreach ($lines as $ln): ?>
                                        <tr class="hover:bg-white/60 transition-colors">
                                            <td class="pl-14 pr-4 py-2 text-slate-600 font-medium <?= (float)$ln['credit'] > 0 ? 'pl-20' : '' ?>">
                                                <span class="font-black text-slate-400 mr-1"><?= $ln['account_code'] ?></span>
                                                <?= htmlspecialchars($ln['name']) ?>
                                            </td>
                                            <td class="py-2 text-right text-emerald-600 font-black w-24"><?= (float)$ln['debit'] > 0 ? '₵'.number_format($ln['debit'], 2) : '' ?></td>
                                            <td class="py-2 pr-5 text-right text-rose-600 font-black w-24"><?= (float)$ln['credit'] > 0 ? '₵'.number_format($ln['credit'], 2) : '' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50 no-print">
                        <span class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest">Page <?= $page ?> of <?= $total_pages ?></span>
                        <div class="flex gap-2">
                            <?php
                            $qs = http_build_query(['semester' => $selected_term, 'academic_year' => $selected_year, 'page' => max(1, $page - 1)]);
                            ?>
                            <a href="?<?= $qs ?>" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 transition-all text-xs <?= $page <= 1 ? 'pointer-events-none opacity-30' : '' ?>"><i class="fas fa-chevron-left text-[0.5rem]"></i></a>
                            <?php for ($p = max(1,$page-2); $p <= min($total_pages, $page+2); $p++):
                                $qs2 = http_build_query(['semester' => $selected_term, 'academic_year' => $selected_year, 'page' => $p]);
                            ?>
                            <a href="?<?= $qs2 ?>" class="w-8 h-8 flex items-center justify-center rounded-xl text-[0.5625rem] font-black transition-all <?= $p === $page ? 'bg-indigo-600 text-white shadow-md' : 'bg-white border border-slate-200 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600' ?>"><?= $p ?></a>
                            <?php endfor; ?>
                            <?php $qs3 = http_build_query(['semester' => $selected_term, 'academic_year' => $selected_year, 'page' => min($total_pages, $page + 1)]); ?>
                            <a href="?<?= $qs3 ?>" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 transition-all text-xs <?= $page >= $total_pages ? 'pointer-events-none opacity-30' : '' ?>"><i class="fas fa-chevron-right text-[0.5rem]"></i></a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div><!-- /grid -->

        </div><!-- /px-container -->
    </main>

    <script>
        document.querySelectorAll('.je-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                const id = this.dataset.id;
                const lines = document.getElementById('je-lines-' + id);
                const isOpen = lines.classList.contains('open');
                lines.classList.toggle('open', !isOpen);
                this.classList.toggle('open', !isOpen);
            });
        });
    </script>
</body>
</html>
