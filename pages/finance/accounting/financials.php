<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

// ── Semester / Year Filters ────────────────────────────────────────────────
$current_term   = getCurrentSemester($conn);
$current_year   = getAcademicYear($conn);
$selected_term  = $_GET['semester']      ?? $current_term;
$selected_year  = $_GET['academic_year'] ?? $current_year;
$filter_all     = ($selected_term === 'all' || $selected_term === '');
$display_year   = formatAcademicYearDisplay($conn, $selected_year);
$school_name    = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$school_address = getSystemSetting($conn, 'school_address', '');

// ── Build semester-scoped journal entry IDs via UNION subquery ─────────────
// journal_entries has no semester column — semester lives in the referenced tables
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
    $id_filter    = "AND l.journal_entry_id IN $sem_subquery";
} else {
    $sem_subquery = null;
    $sem_params_6 = [];
    $id_filter    = "";
}

// ── Fetch account balances ─────────────────────────────────────────────────
$sql = "
    SELECT a.name, a.type, a.account_code,
           COALESCE(SUM(l.debit),  0) AS dr,
           COALESCE(SUM(l.credit), 0) AS cr
    FROM accounts a
    LEFT JOIN journal_lines l ON a.id = l.account_id $id_filter
    GROUP BY a.id
    ORDER BY a.account_code
";
$stmt = $conn->prepare($sql);
if (!$filter_all) {
    $stmt->bind_param("ssssss", ...$sem_params_6);
}
$stmt->execute();
$result = $stmt->get_result();

$balances = ['asset' => [], 'liability' => [], 'equity' => [], 'revenue' => [], 'expense' => []];
$totals   = ['asset' => 0, 'liability' => 0, 'equity' => 0, 'revenue' => 0, 'expense' => 0];

while ($row = $result->fetch_assoc()) {
    $dr   = (float)$row['dr'];
    $cr   = (float)$row['cr'];
    $type = strtolower($row['type']);
    // Normal balance convention
    $bal  = ($type === 'asset' || $type === 'expense') ? ($dr - $cr) : ($cr - $dr);
    if (abs($bal) < 0.005) continue; // skip zero-balance
    if (!isset($balances[$type])) $balances[$type] = [];
    $balances[$type][] = ['name' => $row['name'], 'code' => $row['account_code'], 'balance' => $bal];
    if (!isset($totals[$type])) $totals[$type] = 0;
    $totals[$type] += $bal;
}
$stmt->close();

$net_income        = $totals['revenue'] - $totals['expense'];
$total_equity      = $totals['equity'] + $net_income;
$total_liab_equity = $totals['liability'] + $total_equity;
$balance_diff      = abs($totals['asset'] - $total_liab_equity);
$is_balanced       = $balance_diff < 0.02;

// Academic year options — from payments since journal_entries has no academic_year
$yr_opts = [];
$yr_rs = $conn->query("SELECT DISTINCT academic_year FROM payments WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
while ($y = $yr_rs->fetch_assoc()) if ($y['academic_year']) $yr_opts[] = $y['academic_year'];
if (!in_array($current_year, $yr_opts)) array_unshift($yr_opts, $current_year);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Statements | <?= htmlspecialchars($school_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }
        .stat-card { transition: all 0.3s cubic-bezier(0.4,0,0.2,1); }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 40px -8px rgba(0,0,0,0.12); }
        .stmt-row { transition: background 0.15s; }
        .stmt-row:hover { background: rgba(248,250,252,0.7); }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .admin-main-content { margin-left: 0 !important; }
            .stat-card { box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
        }
    </style>
</head>
<body class="text-slate-900 leading-relaxed">
    <div class="no-print"><?php include '../../../includes/sidebar.php'; ?></div>
    <main class="admin-main-content lg:ml-72 min-h-screen pb-16">

        <!-- Sticky Breadcrumb -->
        <div class="bg-white border-b border-slate-200 px-6 py-4 sticky top-0 z-30 no-print">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">
                <a href="../dashboard.php" class="hover:text-indigo-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span class="text-slate-200">/</span>
                <a href="index.php" class="hover:text-indigo-600 transition-colors">Accounting Ledger</a>
                <span class="text-slate-200">/</span>
                <span class="text-indigo-600">Financial Statements</span>
            </div>
        </div>

        <!-- Print Header -->
        <div class="hidden print:block p-8 border-b border-slate-200 mb-6 text-center">
            <h2 class="text-xl font-black uppercase tracking-widest"><?= htmlspecialchars($school_name) ?></h2>
            <?php if ($school_address): ?><p class="text-sm text-slate-500"><?= htmlspecialchars($school_address) ?></p><?php endif; ?>
            <p class="font-black text-sm mt-2 uppercase tracking-widest">Financial Statements</p>
            <p class="text-xs text-slate-500 mt-1"><?= $filter_all ? 'All Periods' : htmlspecialchars("$selected_term — $display_year") ?> · Printed <?= date('F j, Y') ?></p>
        </div>

        <div class="px-6 md:px-10 pt-8">

            <!-- Page Header -->
            <header class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div>
                    <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                        <span class="w-8 h-[2px] bg-indigo-600"></span>
                        Formal Accounting Reports
                    </div>
                    <h1 class="text-4xl font-black text-slate-900 tracking-tight">Financial <span class="text-indigo-600">Statements</span></h1>
                    <p class="text-slate-500 mt-2 font-medium text-sm">Income Statement &amp; Balance Sheet — <?= htmlspecialchars($filter_all ? 'All Periods' : "$selected_term · $display_year") ?></p>
                </div>
                <div class="flex flex-wrap gap-3 no-print">
                    <a href="index.php?semester=<?= urlencode($selected_term) ?>&academic_year=<?= urlencode($selected_year) ?>" class="flex items-center gap-2 bg-slate-100 text-slate-700 font-black text-[0.625rem] uppercase tracking-widest px-5 py-3 rounded-xl hover:bg-slate-200 transition-all">
                        <i class="fas fa-arrow-left"></i> Back to Ledger
                    </a>
                    <button onclick="window.print()" class="flex items-center gap-2 bg-indigo-600 text-white font-black text-[0.625rem] uppercase tracking-widest px-5 py-3 rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-600/25">
                        <i class="fas fa-print"></i> Print
                    </button>
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
                    <a href="financials.php" class="text-slate-400 font-black text-[0.5625rem] uppercase tracking-widest px-4 py-3 rounded-xl hover:bg-slate-50 transition-all">Clear</a>
                    <?php endif; ?>
                </form>
            </section>

            <!-- KPI Cards -->
            <section class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
                <!-- Total Revenue -->
                <div class="stat-card bg-gradient-to-br from-emerald-600 to-teal-600 rounded-[1.75rem] p-7 text-white shadow-xl shadow-emerald-600/20">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mb-5"><i class="fas fa-arrow-trend-up"></i></div>
                    <p class="text-[0.5rem] font-black uppercase tracking-widest opacity-80 mb-1">Total Revenue</p>
                    <h3 class="text-2xl font-black">₵<?= number_format($totals['revenue'], 2) ?></h3>
                    <p class="text-[0.5625rem] font-bold opacity-60 mt-1 uppercase tracking-wider"><?= count($balances['revenue']) ?> revenue account<?= count($balances['revenue']) !== 1 ? 's' : '' ?></p>
                </div>
                <!-- Total Expenses -->
                <div class="stat-card bg-gradient-to-br from-rose-600 to-orange-600 rounded-[1.75rem] p-7 text-white shadow-xl shadow-rose-600/20">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mb-5"><i class="fas fa-arrow-trend-down"></i></div>
                    <p class="text-[0.5rem] font-black uppercase tracking-widest opacity-80 mb-1">Total Expenses</p>
                    <h3 class="text-2xl font-black">₵<?= number_format($totals['expense'], 2) ?></h3>
                    <p class="text-[0.5625rem] font-bold opacity-60 mt-1 uppercase tracking-wider"><?= count($balances['expense']) ?> expense account<?= count($balances['expense']) !== 1 ? 's' : '' ?></p>
                </div>
                <!-- Net Income -->
                <div class="stat-card <?= $net_income >= 0 ? 'bg-gradient-to-br from-indigo-600 to-violet-600 shadow-indigo-600/20' : 'bg-gradient-to-br from-slate-700 to-slate-900 shadow-slate-900/20' ?> rounded-[1.75rem] p-7 text-white shadow-xl">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mb-5"><i class="fas fa-<?= $net_income >= 0 ? 'chart-line' : 'chart-line-down' ?>"></i></div>
                    <p class="text-[0.5rem] font-black uppercase tracking-widest opacity-80 mb-1">Net Income <?= $net_income < 0 ? '(Loss)' : '' ?></p>
                    <h3 class="text-2xl font-black">₵<?= number_format(abs($net_income), 2) ?></h3>
                    <p class="text-[0.5625rem] font-bold opacity-60 mt-1 uppercase tracking-wider"><?= $net_income >= 0 ? 'Surplus' : 'Operating at a Loss' ?></p>
                </div>
            </section>

            <!-- Balance Sheet Balance Indicator -->
            <?php if (!empty($balances['asset']) || !empty($balances['liability'])): ?>
            <div class="mb-6 no-print flex items-center gap-3 <?= $is_balanced ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800' ?> border font-bold text-sm px-5 py-4 rounded-2xl">
                <i class="fas <?= $is_balanced ? 'fa-check-circle text-emerald-500' : 'fa-triangle-exclamation text-amber-500' ?>"></i>
                <?php if ($is_balanced): ?>
                    Balance Sheet is <strong>balanced</strong>. Assets = Liabilities + Equity.
                <?php else: ?>
                    Balance Sheet is <strong>out of balance</strong> by ₵<?= number_format($balance_diff, 2) ?>.
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Statements Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                <!-- ── Income Statement ── -->
                <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm overflow-hidden">
                    <!-- Card Header -->
                    <div class="bg-gradient-to-r from-indigo-600 to-violet-600 p-7 text-white">
                        <div class="flex items-center gap-3 mb-1">
                            <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center"><i class="fas fa-chart-line text-sm"></i></div>
                            <div>
                                <h2 class="text-lg font-black uppercase tracking-widest">Income Statement</h2>
                                <p class="text-indigo-200 text-xs font-bold mt-0.5">
                                    <?= $filter_all ? 'All Periods' : htmlspecialchars("$selected_term · $display_year") ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-8">
                        <!-- Revenues -->
                        <h3 class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-[0.3em] mb-4 flex items-center gap-3">Revenues <span class="flex-1 h-px bg-slate-100"></span></h3>
                        <?php if (empty($balances['revenue'])): ?>
                        <p class="text-slate-400 text-sm font-medium mb-6 italic">No revenue recorded for this period.</p>
                        <?php else: ?>
                        <?php foreach ($balances['revenue'] as $item): ?>
                        <div class="stmt-row flex justify-between items-center py-2.5 px-3 rounded-xl -mx-3">
                            <div>
                                <span class="text-slate-700 font-bold text-sm"><?= htmlspecialchars($item['name']) ?></span>
                                <span class="text-[0.4375rem] text-slate-300 font-black ml-2 uppercase tracking-widest"><?= $item['code'] ?></span>
                            </div>
                            <span class="font-black text-emerald-700 text-sm">₵<?= number_format($item['balance'], 2) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        <div class="flex justify-between items-center py-3 px-3 -mx-3 bg-emerald-50 rounded-xl mt-2 mb-8">
                            <span class="font-black text-emerald-800 text-sm uppercase tracking-wider">Total Revenues</span>
                            <span class="font-black text-emerald-700 text-lg">₵<?= number_format($totals['revenue'], 2) ?></span>
                        </div>

                        <!-- Expenses -->
                        <h3 class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-[0.3em] mb-4 flex items-center gap-3">Expenses <span class="flex-1 h-px bg-slate-100"></span></h3>
                        <?php if (empty($balances['expense'])): ?>
                        <p class="text-slate-400 text-sm font-medium mb-6 italic">No expenses recorded for this period.</p>
                        <?php else: ?>
                        <?php foreach ($balances['expense'] as $item): ?>
                        <div class="stmt-row flex justify-between items-center py-2.5 px-3 rounded-xl -mx-3">
                            <div>
                                <span class="text-slate-700 font-bold text-sm"><?= htmlspecialchars($item['name']) ?></span>
                                <span class="text-[0.4375rem] text-slate-300 font-black ml-2 uppercase tracking-widest"><?= $item['code'] ?></span>
                            </div>
                            <span class="font-black text-rose-700 text-sm">₵<?= number_format($item['balance'], 2) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        <div class="flex justify-between items-center py-3 px-3 -mx-3 bg-rose-50 rounded-xl mt-2 mb-8">
                            <span class="font-black text-rose-800 text-sm uppercase tracking-wider">Total Expenses</span>
                            <span class="font-black text-rose-700 text-lg">₵<?= number_format($totals['expense'], 2) ?></span>
                        </div>

                        <!-- Net Income / Loss -->
                        <div class="flex justify-between items-center py-5 px-5 -mx-3 rounded-2xl <?= $net_income >= 0 ? 'bg-gradient-to-r from-indigo-600 to-violet-600' : 'bg-gradient-to-r from-rose-600 to-orange-600' ?> text-white mt-2 shadow-lg">
                            <div>
                                <p class="text-[0.5rem] font-black uppercase tracking-widest opacity-70 mb-0.5">Net <?= $net_income >= 0 ? 'Income' : 'Loss' ?></p>
                                <p class="font-black text-xl">₵<?= number_format(abs($net_income), 2) ?></p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-<?= $net_income >= 0 ? 'chart-line' : 'chart-line-down' ?> text-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Balance Sheet ── -->
                <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm overflow-hidden">
                    <!-- Card Header -->
                    <div class="bg-gradient-to-r from-slate-700 to-slate-900 p-7 text-white">
                        <div class="flex items-center gap-3 mb-1">
                            <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center"><i class="fas fa-scale-balanced text-sm"></i></div>
                            <div>
                                <h2 class="text-lg font-black uppercase tracking-widest">Balance Sheet</h2>
                                <p class="text-slate-400 text-xs font-bold mt-0.5">As of <?= date('F j, Y') ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="p-8">
                        <!-- Assets -->
                        <h3 class="text-[0.5625rem] font-black text-blue-500 uppercase tracking-[0.3em] mb-4 flex items-center gap-3">Assets <span class="flex-1 h-px bg-blue-50"></span></h3>
                        <?php if (empty($balances['asset'])): ?>
                        <p class="text-slate-400 text-sm font-medium mb-6 italic">No asset balances for this period.</p>
                        <?php else: ?>
                        <?php foreach ($balances['asset'] as $item): ?>
                        <div class="stmt-row flex justify-between items-center py-2.5 px-3 rounded-xl -mx-3">
                            <div>
                                <span class="text-slate-700 font-bold text-sm"><?= htmlspecialchars($item['name']) ?></span>
                                <span class="text-[0.4375rem] text-slate-300 font-black ml-2 uppercase tracking-widest"><?= $item['code'] ?></span>
                            </div>
                            <span class="font-black text-blue-700 text-sm">₵<?= number_format($item['balance'], 2) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        <div class="flex justify-between items-center py-3 px-3 -mx-3 bg-blue-50 rounded-xl mt-2 mb-8">
                            <span class="font-black text-blue-900 text-sm uppercase tracking-wider">Total Assets</span>
                            <span class="font-black text-blue-800 text-lg underline decoration-double decoration-blue-300">₵<?= number_format($totals['asset'], 2) ?></span>
                        </div>

                        <!-- Liabilities -->
                        <h3 class="text-[0.5625rem] font-black text-rose-500 uppercase tracking-[0.3em] mb-4 flex items-center gap-3">Liabilities <span class="flex-1 h-px bg-rose-50"></span></h3>
                        <?php if (empty($balances['liability'])): ?>
                        <p class="text-slate-400 text-sm font-medium mb-4 italic">No liabilities recorded.</p>
                        <?php else: ?>
                        <?php foreach ($balances['liability'] as $item): ?>
                        <div class="stmt-row flex justify-between items-center py-2.5 px-3 rounded-xl -mx-3">
                            <div>
                                <span class="text-slate-700 font-bold text-sm"><?= htmlspecialchars($item['name']) ?></span>
                                <span class="text-[0.4375rem] text-slate-300 font-black ml-2 uppercase tracking-widest"><?= $item['code'] ?></span>
                            </div>
                            <span class="font-black text-rose-700 text-sm">₵<?= number_format($item['balance'], 2) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        <div class="flex justify-between items-center py-3 px-3 -mx-3 bg-rose-50 rounded-xl mt-2 mb-8">
                            <span class="font-black text-rose-900 text-sm uppercase tracking-wider">Total Liabilities</span>
                            <span class="font-black text-rose-800 text-lg">₵<?= number_format($totals['liability'], 2) ?></span>
                        </div>

                        <!-- Equity -->
                        <h3 class="text-[0.5625rem] font-black text-purple-500 uppercase tracking-[0.3em] mb-4 flex items-center gap-3">Equity <span class="flex-1 h-px bg-purple-50"></span></h3>
                        <?php foreach ($balances['equity'] as $item): ?>
                        <div class="stmt-row flex justify-between items-center py-2.5 px-3 rounded-xl -mx-3">
                            <div>
                                <span class="text-slate-700 font-bold text-sm"><?= htmlspecialchars($item['name']) ?></span>
                                <span class="text-[0.4375rem] text-slate-300 font-black ml-2 uppercase tracking-widest"><?= $item['code'] ?></span>
                            </div>
                            <span class="font-black text-purple-700 text-sm">₵<?= number_format($item['balance'], 2) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <!-- Net Income flows into Equity -->
                        <div class="stmt-row flex justify-between items-center py-2.5 px-3 rounded-xl -mx-3">
                            <span class="text-indigo-600 font-bold text-sm italic">Net Income (Current Period)</span>
                            <span class="font-black <?= $net_income >= 0 ? 'text-indigo-600' : 'text-rose-600' ?> text-sm">₵<?= number_format($net_income, 2) ?></span>
                        </div>
                        <div class="flex justify-between items-center py-3 px-3 -mx-3 bg-purple-50 rounded-xl mt-2 mb-8">
                            <span class="font-black text-purple-900 text-sm uppercase tracking-wider">Total Equity</span>
                            <span class="font-black text-purple-800 text-lg">₵<?= number_format($total_equity, 2) ?></span>
                        </div>

                        <!-- Grand Total -->
                        <div class="flex justify-between items-center py-5 px-5 -mx-3 rounded-2xl bg-gradient-to-r from-slate-700 to-slate-900 text-white shadow-lg mt-2">
                            <div>
                                <p class="text-[0.5rem] font-black uppercase tracking-widest opacity-70 mb-0.5">Total Liab. &amp; Equity</p>
                                <p class="font-black text-xl">₵<?= number_format($total_liab_equity, 2) ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-[0.5rem] font-black uppercase tracking-widest opacity-70 mb-0.5">Total Assets</p>
                                <p class="font-black text-xl">₵<?= number_format($totals['asset'], 2) ?></p>
                            </div>
                        </div>

                        <?php if (!$is_balanced && (count($balances['asset']) > 0 || count($balances['liability']) > 0)): ?>
                        <div class="mt-4 text-center">
                            <span class="text-[0.5rem] font-black uppercase tracking-widest text-amber-500 bg-amber-50 px-3 py-1.5 rounded-full">
                                Δ ₵<?= number_format($balance_diff, 2) ?> out of balance
                            </span>
                        </div>
                        <?php elseif ($is_balanced && (count($balances['asset']) > 0)): ?>
                        <div class="mt-4 text-center">
                            <span class="text-[0.5rem] font-black uppercase tracking-widest text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full">
                                <i class="fas fa-check mr-1"></i> Balanced
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /grid -->
        </div><!-- /container -->
    </main>
</body>
</html>
