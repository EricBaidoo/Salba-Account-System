<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../dashboard.php"); exit;
}

$current_year   = getAcademicYear($conn);
$current_sem    = getCurrentSemester($conn);
$selected_class = $_GET["class"] ?? "";
$selected_year  = $_GET["academic_year"] ?? $current_year;
$selected_sem   = $_GET["semester"] ?? $current_sem;
$school_name    = getSystemSetting($conn, "school_name", "School");

// Semesters
$sem_rs = $conn->query("SELECT semester_name FROM academic_semester_dictionary ORDER BY id ASC");
$semesters = [];
while ($s = $sem_rs->fetch_assoc()) $semesters[] = $s["semester_name"];
if (empty($semesters)) $semesters = ["First Semester","Second Semester","Third Semester"];

// Classes
$classes = [];
$cr = $conn->query("SELECT DISTINCT class FROM students WHERE status='active' AND class IS NOT NULL AND class != '' ORDER BY class ASC");
while ($c = $cr->fetch_assoc()) $classes[] = $c["class"];

// Years
$yr_rs = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
$all_years = [];
while ($y = $yr_rs->fetch_assoc()) $all_years[] = $y["academic_year"];
if (!in_array($selected_year, $all_years)) array_unshift($all_years, $selected_year);

// Assignments + students + submissions
$assignments = [];
$students    = [];
$submissions = [];

if ($selected_class) {
    $sc = $conn->real_escape_string($selected_class);
    $sy = $conn->real_escape_string($selected_year);

    $ar = $conn->query("
        SELECT sa.id as assignment_id, sa.quantity, sa.price, si.name as item_name
        FROM stationery_assignments sa
        JOIN stationery_items si ON sa.item_id = si.id
        WHERE sa.class='$sc' AND sa.academic_year='$sy'
        ORDER BY sa.sort_order ASC, sa.id ASC
    ");
    while ($a = $ar->fetch_assoc()) $assignments[$a["assignment_id"]] = $a;

    $sr = $conn->query("SELECT id, CONCAT(first_name,' ',last_name) as name FROM students WHERE status='active' AND class='$sc' ORDER BY first_name, last_name ASC");
    while ($s = $sr->fetch_assoc()) $students[$s["id"]] = $s["name"];

    if (!empty($assignments)) {
        $aids = implode(",", array_keys($assignments));
        $subr = $conn->query("SELECT * FROM stationery_submissions WHERE assignment_id IN ($aids)");
        while ($sub = $subr->fetch_assoc()) {
            $submissions[$sub["assignment_id"]][$sub["student_id"]] = $sub;
        }
    }
}

// Stats
$total_cells   = count($assignments) * count($students);
$brought_count = 0;
$billed_count  = 0;
foreach ($submissions as $asub) {
    foreach ($asub as $sub) {
        if ($sub["brought"]) $brought_count++;
        if ($sub["billed"])  $billed_count++;
    }
}
$pending_count = $total_cells - $brought_count - $billed_count;
$pct_brought   = $total_cells > 0 ? round(($brought_count / $total_cells) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stationery Tracker | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @media print {
            .no-print { display:none !important; }
            body { background:#fff !important; }
            .admin-main-content { margin:0 !important; padding:1cm !important; }
            .print-header { display:block !important; }
            table { page-break-inside:auto; }
            tr { page-break-inside:avoid; }
        }
        .print-header { display:none; }
        .sticky-col { position:sticky; left:0; z-index:10; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900">
<div class="no-print"><?php include "../../../includes/sidebar_admin_modern.php"; ?></div>

<main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">

    <!-- Print Header -->
    <div class="print-header mb-4">
        <h2 class="text-lg font-black"><?= htmlspecialchars($school_name) ?></h2>
        <p class="text-sm">Stationery Tracker — <?= htmlspecialchars($selected_class) ?> — <?= htmlspecialchars($selected_year) ?></p>
        <hr class="my-2">
    </div>

    <!-- Page Header -->
    <header class="no-print mb-6 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-indigo-600 transition-colors"><i class="fas fa-home"></i> Admin</a>
                <span>/</span>
                <span class="text-indigo-600">Stationery</span>
            </div>
            <h1 class="text-3xl font-black text-slate-900">Stationery <span class="text-indigo-600">Tracker</span></h1>
            <p class="text-slate-500 mt-1 text-sm">Mark which students have brought their items. Bill those who have not.</p>
        </div>
        <?php if ($selected_class && !empty($assignments)): ?>
        <button onclick="window.print()" class="flex items-center gap-2 bg-slate-900 text-white text-xs font-black uppercase tracking-widest px-5 py-3 rounded-2xl hover:bg-slate-700 transition-colors self-start sm:self-auto">
            <i class="fas fa-print"></i> Print
        </button>
        <?php endif; ?>
    </header>

    <!-- Tab Navigation -->
    <nav class="no-print flex gap-1 mb-6 bg-white border border-slate-100 rounded-2xl p-1.5 shadow-sm w-fit flex-wrap">
        <a href="items.php" class="flex items-center gap-2 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors">
            <i class="fas fa-box-open"></i> Items
        </a>
        <a href="assign.php<?= $selected_class ? "?class=".urlencode($selected_class)."&academic_year=".urlencode($selected_year)."&semester=".urlencode($selected_sem) : "" ?>"
           class="flex items-center gap-2 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors">
            <i class="fas fa-link"></i> Assign to Class
        </a>
        <span class="flex items-center gap-2 bg-indigo-600 text-white text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl">
            <i class="fas fa-table-cells"></i> Tracker
        </span>
        <a href="settings.php" class="flex items-center gap-2 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors">
            <i class="fas fa-gear"></i> Settings
        </a>
    </nav>

    <!-- Filter -->
    <form method="GET" class="no-print bg-white border border-slate-100 rounded-2xl p-5 shadow-sm mb-6 flex flex-wrap gap-4 items-end">
        <div class="flex flex-col gap-1.5">
            <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Class</label>
            <select name="class" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold outline-none focus:ring-2 focus:ring-indigo-500 min-w-[140px]">
                <option value="">Select Class</option>
                <?php foreach ($classes as $cl): ?>
                <option value="<?= htmlspecialchars($cl) ?>" <?= $cl === $selected_class ? "selected" : "" ?>><?= htmlspecialchars($cl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex flex-col gap-1.5">
            <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Academic Year</label>
            <select name="academic_year" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold outline-none focus:ring-2 focus:ring-indigo-500 min-w-[140px]">
                <?php foreach ($all_years as $yr): ?>
                <option value="<?= htmlspecialchars($yr) ?>" <?= $yr === $selected_year ? "selected" : "" ?>><?= htmlspecialchars($yr) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex flex-col gap-1.5">
            <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Semester (billing)</label>
            <select name="semester" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold outline-none focus:ring-2 focus:ring-indigo-500 min-w-[160px]">
                <?php foreach ($semesters as $sm): ?>
                <option value="<?= htmlspecialchars($sm) ?>" <?= $sm === $selected_sem ? "selected" : "" ?>><?= htmlspecialchars($sm) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if (!$selected_class): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-16 text-center text-slate-400">
        <i class="fas fa-table-cells text-5xl block mb-4 opacity-20"></i>
        <p class="font-semibold text-lg">Select a class to view the tracker.</p>
    </div>

    <?php elseif (empty($assignments)): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-16 text-center text-slate-400">
        <i class="fas fa-box-open text-5xl block mb-4 opacity-20"></i>
        <p class="font-semibold text-lg mb-3">No stationery items assigned to this class/year yet.</p>
        <a href="assign.php?class=<?= urlencode($selected_class) ?>&academic_year=<?= urlencode($selected_year) ?>&semester=<?= urlencode($selected_sem) ?>"
           class="inline-flex items-center gap-2 bg-indigo-600 text-white text-xs font-black uppercase tracking-widest px-5 py-3 rounded-xl hover:bg-indigo-700 transition-colors">
            <i class="fas fa-link"></i> Assign Items to this Class
        </a>
    </div>

    <?php elseif (empty($students)): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-16 text-center text-slate-400">
        <i class="fas fa-users text-5xl block mb-4 opacity-20"></i>
        <p class="font-semibold text-lg">No active students in <?= htmlspecialchars($selected_class) ?>.</p>
    </div>

    <?php else: ?>

    <!-- Stats Row -->
    <div class="no-print grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-sm text-center">
            <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1">Students</p>
            <p class="text-2xl font-black text-slate-900"><?= count($students) ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-sm text-center">
            <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1">Items</p>
            <p class="text-2xl font-black text-slate-900"><?= count($assignments) ?></p>
        </div>
        <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-4 shadow-sm text-center">
            <p class="text-[0.5rem] font-black text-emerald-600 uppercase tracking-widest mb-1">Brought</p>
            <p class="text-2xl font-black text-emerald-700"><?= $brought_count ?> <span class="text-sm font-semibold text-emerald-400"><?= $pct_brought ?>%</span></p>
        </div>
        <div class="bg-amber-50 border border-amber-100 rounded-2xl p-4 shadow-sm text-center">
            <p class="text-[0.5rem] font-black text-amber-600 uppercase tracking-widest mb-1">Billed</p>
            <p class="text-2xl font-black text-amber-700"><?= $billed_count ?></p>
        </div>
    </div>

    <!-- Legend -->
    <div class="no-print flex flex-wrap items-center gap-x-5 gap-y-2 text-xs font-semibold text-slate-500 mb-4">
        <span class="flex items-center gap-1.5"><span class="w-4 h-4 rounded bg-emerald-100 border border-emerald-300 inline-block"></span>Brought</span>
        <span class="flex items-center gap-1.5"><span class="w-4 h-4 rounded bg-amber-100 border border-amber-300 inline-block"></span>Billed (not brought)</span>
        <span class="flex items-center gap-1.5"><span class="w-4 h-4 rounded bg-white border border-slate-200 inline-block"></span>Not brought</span>
        <span class="text-slate-300">|</span>
        <span>Click <i class="fas fa-check text-emerald-500"></i> to mark brought &nbsp;·&nbsp; Click <i class="fas fa-file-invoice-dollar text-amber-500"></i> to bill</span>
    </div>

    <!-- Tracker Table -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto rounded-2xl">
            <table class="text-xs border-collapse" style="min-width: max-content; width:100%;">
                <thead>
                    <tr class="bg-slate-50 border-b-2 border-slate-200">
                        <th class="sticky-col bg-slate-50 px-4 py-3 text-left font-black text-slate-500 text-[0.5rem] uppercase tracking-widest w-44 border-r border-slate-200">Student</th>
                        <?php foreach ($assignments as $aid => $asgn): ?>
                        <th class="px-3 py-3 text-center font-black text-slate-500 text-[0.5rem] uppercase tracking-widest min-w-[80px] max-w-[110px]">
                            <div class="truncate" title="<?= htmlspecialchars($asgn["item_name"]) ?>"><?= htmlspecialchars($asgn["item_name"]) ?></div>
                            <div class="text-slate-400 font-semibold normal-case tracking-normal mt-0.5 text-[0.45rem]">
                                <?= htmlspecialchars($asgn["quantity"]) ?>
                                <?php if ($asgn["price"] > 0): ?> · <span class="text-indigo-500">GH₵<?= number_format($asgn["price"],2) ?></span><?php endif; ?>
                            </div>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($students as $sid => $sname): ?>
                    <tr class="hover:bg-blue-50/30 transition-colors">
                        <td class="sticky-col bg-white px-4 py-2.5 font-semibold text-slate-800 border-r border-slate-100 w-44 max-w-[176px]">
                            <div class="truncate text-xs" title="<?= htmlspecialchars($sname) ?>"><?= htmlspecialchars($sname) ?></div>
                        </td>
                        <?php foreach ($assignments as $aid => $asgn):
                            $sub     = $submissions[$aid][$sid] ?? null;
                            $brought = $sub["brought"] ?? 0;
                            $billed  = $sub["billed"]  ?? 0;
                            $hasPrice = $asgn["price"] > 0;
                            if ($brought)    $bg = "bg-emerald-50";
                            elseif ($billed) $bg = "bg-amber-50";
                            else             $bg = "";
                        ?>
                        <td class="px-2 py-2 text-center <?= $bg ?> min-w-[80px]" data-aid="<?= $aid ?>" data-sid="<?= $sid ?>">
                            <?php if ($billed && !$brought): ?>
                                <div class="no-print flex flex-col items-center gap-1">
                                    <button onclick="unbillStudent(<?= $aid ?>,<?= $sid ?>,1,this)"
                                        class="w-full px-1.5 py-1 bg-emerald-100 hover:bg-emerald-200 text-emerald-700 text-[0.45rem] font-black rounded-lg flex items-center justify-center gap-1 transition-colors"
                                        title="Mark brought &amp; remove charge">
                                        <i class="fas fa-check"></i> Brought
                                    </button>
                                    <button onclick="unbillStudent(<?= $aid ?>,<?= $sid ?>,0,this)"
                                        class="w-full px-1.5 py-1 bg-amber-100 hover:bg-rose-100 text-amber-600 hover:text-rose-600 text-[0.45rem] font-black rounded-lg flex items-center justify-center gap-1 transition-colors"
                                        title="Unbill only (remove charge, stays not brought)">
                                        <i class="fas fa-xmark"></i> Unbill
                                    </button>
                                </div>
                                <span class="print-only text-amber-600 font-bold text-xs">Billed</span>
                            <?php elseif ($brought): ?>
                                <button onclick="toggleBrought(<?= $aid ?>,<?= $sid ?>,0,this)"
                                    class="no-print w-8 h-8 bg-emerald-100 hover:bg-emerald-200 text-emerald-700 rounded-xl flex items-center justify-center mx-auto transition-colors">
                                    <i class="fas fa-check text-sm"></i>
                                </button>
                                <span class="print-only text-emerald-700 font-bold text-sm">✓</span>
                            <?php else: ?>
                                <div class="no-print flex items-center justify-center gap-1">
                                    <button onclick="toggleBrought(<?= $aid ?>,<?= $sid ?>,1,this)"
                                        class="w-7 h-7 bg-slate-100 hover:bg-emerald-100 text-slate-400 hover:text-emerald-600 rounded-lg flex items-center justify-center transition-colors"
                                        title="Mark as Brought">
                                        <i class="fas fa-check text-xs"></i>
                                    </button>
                                    <?php if ($hasPrice): ?>
                                    <button onclick="billStudent(<?= $aid ?>,<?= $sid ?>,<?= htmlspecialchars(json_encode($sname), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($asgn['item_name']), ENT_QUOTES) ?>,<?= $asgn['price'] ?>,this)"
                                        class="w-7 h-7 bg-slate-100 hover:bg-amber-100 text-slate-400 hover:text-amber-600 rounded-lg flex items-center justify-center transition-colors"
                                        title="Bill GH₵<?= number_format($asgn["price"],2) ?>">
                                        <i class="fas fa-file-invoice-dollar text-xs"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <span class="print-only text-slate-300 text-sm">☐</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>
</main>

<!-- Billing Confirmation Modal -->
<div id="bill-modal" class="no-print fixed inset-0 z-50 hidden flex items-center justify-center p-4" style="background:rgba(15,23,42,0.55);backdrop-filter:blur(3px)">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
        <!-- Header -->
        <div class="bg-amber-50 border-b border-amber-100 px-6 py-5 flex items-center gap-4">
            <div class="w-12 h-12 bg-amber-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-file-invoice-dollar text-amber-600 text-xl"></i>
            </div>
            <div>
                <h3 class="font-black text-slate-900 text-lg">Confirm Billing</h3>
                <p class="text-xs text-slate-500 mt-0.5">This will add a charge to the student's fee account.</p>
            </div>
        </div>
        <!-- Body -->
        <div class="px-6 py-5 space-y-3">
            <div class="flex justify-between items-center py-2 border-b border-slate-100">
                <span class="text-xs font-black text-slate-400 uppercase tracking-widest">Student</span>
                <span id="bm-student" class="text-sm font-bold text-slate-800"></span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-100">
                <span class="text-xs font-black text-slate-400 uppercase tracking-widest">Item</span>
                <span id="bm-item" class="text-sm font-semibold text-slate-700"></span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-100">
                <span class="text-xs font-black text-slate-400 uppercase tracking-widest">Semester</span>
                <span id="bm-semester" class="text-sm font-semibold text-slate-700"></span>
            </div>
            <div class="flex justify-between items-center py-2">
                <span class="text-xs font-black text-slate-400 uppercase tracking-widest">Amount</span>
                <span id="bm-amount" class="text-lg font-black text-amber-600"></span>
            </div>
        </div>
        <!-- Actions -->
        <div class="px-6 pb-6 flex gap-3 justify-end">
            <button onclick="closeBillModal()" class="px-5 py-2.5 text-sm font-black text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">Cancel</button>
            <button id="bm-confirm" class="px-6 py-2.5 text-sm font-black text-white bg-amber-500 hover:bg-amber-600 rounded-xl transition-colors flex items-center gap-2">
                <i class="fas fa-file-invoice-dollar"></i> Bill Student
            </button>
        </div>
    </div>
</div>

<div id="toast" class="no-print fixed bottom-6 right-6 z-50 hidden text-sm font-semibold px-5 py-3 rounded-2xl shadow-xl flex items-center gap-3"></div>

<script>
const API          = "api_stationery.php";
const SEL_SEMESTER = <?= json_encode($selected_sem) ?>;
const SEL_YEAR     = <?= json_encode($selected_year) ?>;

function showToast(msg, ok=true) {
    const t = document.getElementById("toast");
    t.className = "no-print fixed bottom-6 right-6 z-50 flex items-center gap-3 text-sm font-semibold px-5 py-3 rounded-2xl shadow-xl " + (ok ? "bg-emerald-600 text-white" : "bg-rose-600 text-white");
    t.innerHTML = `<i class="fas ${ok?"fa-circle-check":"fa-circle-xmark"}"></i>${msg}`;
    t.classList.remove("hidden");
    setTimeout(() => t.classList.add("hidden"), 3500);
}
async function apiPost(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k,v]) => fd.append(k,v));
    return (await fetch(API, {method:"POST",body:fd})).json();
}
async function toggleBrought(aid, sid, brought, btn) {
    btn.disabled = true;
    const res = await apiPost({action:"toggle_brought", assignment_id:aid, student_id:sid, brought});
    if (res.success) { showToast(brought ? "Marked as brought." : "Unmarked."); setTimeout(() => location.reload(), 500); }
    else { showToast("Error.", false); btn.disabled = false; }
}
async function unbillStudent(aid, sid, markBrought, btn) {
    btn.disabled = true;
    try {
        const res = await apiPost({action:'unbill', assignment_id:aid, student_id:sid, mark_brought:markBrought});
        if (res.success) { showToast(res.message); setTimeout(() => location.reload(), 500); }
        else { showToast(res.message || 'Failed to unbill.', false); btn.disabled = false; }
    } catch(e) { showToast('Server error.', false); btn.disabled = false; }
}
let _billBtn = null;
function billStudent(aid, sid, studentName, itemName, price, btn) {
    _billBtn = btn;
    document.getElementById('bm-student').textContent  = studentName;
    document.getElementById('bm-item').textContent     = itemName;
    document.getElementById('bm-semester').textContent = SEL_SEMESTER;
    document.getElementById('bm-amount').textContent   = 'GH\u20b5' + parseFloat(price).toFixed(2);
    const modal = document.getElementById('bill-modal');
    modal.classList.remove('hidden');
    document.getElementById('bm-confirm').onclick = async () => {
        document.getElementById('bm-confirm').disabled = true;
        document.getElementById('bm-confirm').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Billing...';
        try {
            const res = await apiPost({action:'bill', assignment_id:aid, student_id:sid, semester:SEL_SEMESTER, academic_year:SEL_YEAR});
            closeBillModal();
            if (res.success) { showToast(res.message); setTimeout(() => location.reload(), 700); }
            else { showToast(res.message || 'Billing failed.', false); if(_billBtn) _billBtn.disabled = false; }
        } catch(e) {
            closeBillModal();
            showToast('Server error — check console for details.', false);
            console.error('Bill error:', e);
            if(_billBtn) _billBtn.disabled = false;
        }
    };
}
function closeBillModal() {
    document.getElementById('bill-modal').classList.add('hidden');
    const btn = document.getElementById('bm-confirm');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-file-invoice-dollar"></i> Bill Student';
}
document.getElementById('bill-modal').addEventListener('click', e => {
    if (e.target === document.getElementById('bill-modal')) closeBillModal();
});
</script>
</body>
</html>
