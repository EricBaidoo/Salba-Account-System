<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../dashboard.php'); exit;
}

$current_year = getAcademicYear($conn);
$current_sem  = getCurrentSemester($conn);
$selected_class = $_GET['class'] ?? '';
$selected_year  = $_GET['academic_year'] ?? $current_year;
$selected_sem   = $_GET['semester'] ?? $current_sem;

// Semesters
$sem_rs = $conn->query("SELECT semester_name FROM academic_semester_dictionary ORDER BY id ASC");
$semesters = [];
while ($s = $sem_rs->fetch_assoc()) $semesters[] = $s['semester_name'];
if (empty($semesters)) $semesters = ['First Semester', 'Second Semester', 'Third Semester'];

// Classes
$classes = [];
$cr = $conn->query("SELECT DISTINCT class FROM students WHERE status='active' AND class IS NOT NULL AND class != '' ORDER BY class ASC");
while ($c = $cr->fetch_assoc()) $classes[] = $c['class'];

// Academic years
$yr_rs = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
$all_years = [];
while ($y = $yr_rs->fetch_assoc()) $all_years[] = $y['academic_year'];
if (!in_array($selected_year, $all_years)) array_unshift($all_years, $selected_year);

// All catalog items
$all_catalog = [];
$ir = $conn->query("SELECT * FROM stationery_items ORDER BY name ASC");
while ($i = $ir->fetch_assoc()) $all_catalog[$i['id']] = $i;

// Assignments for selected class/year
$assigned = [];
if ($selected_class) {
    $sc = $conn->real_escape_string($selected_class);
    $sy = $conn->real_escape_string($selected_year);
    $ar = $conn->query("
        SELECT sa.*, si.name as item_name, si.unit, si.default_price
        FROM stationery_assignments sa
        JOIN stationery_items si ON sa.item_id = si.id
        WHERE sa.class='$sc' AND sa.academic_year='$sy'
        ORDER BY sa.sort_order ASC, sa.id ASC
    ");
    while ($a = $ar->fetch_assoc()) $assigned[$a['item_id']] = $a;
}

$not_assigned = array_filter($all_catalog, fn($i) => !isset($assigned[$i['id']]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Stationery | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-900">
<?php include '../../../includes/sidebar_admin_modern.php'; ?>

<main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">

    <!-- Header -->
    <header class="mb-6">
        <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3 uppercase tracking-wider">
            <a href="../dashboard.php" class="hover:text-indigo-600 transition-colors"><i class="fas fa-home"></i> Admin</a>
            <span>/</span>
            <span class="text-indigo-600">Stationery</span>
        </div>
        <h1 class="text-3xl font-black text-slate-900">Assign <span class="text-indigo-600">to Class</span></h1>
        <p class="text-slate-500 mt-1 text-sm">Pick a class and academic year, then choose which items to include in their stationery list.</p>
    </header>

    <!-- Tab Navigation -->
    <nav class="flex gap-1 mb-6 bg-white border border-slate-100 rounded-2xl p-1.5 shadow-sm w-fit flex-wrap">
        <a href="items.php" class="flex items-center gap-2 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors">
            <i class="fas fa-box-open"></i> Items
        </a>
        <span class="flex items-center gap-2 bg-indigo-600 text-white text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl">
            <i class="fas fa-link"></i> Assign to Class
        </span>
        <a href="index.php<?= $selected_class ? '?class='.urlencode($selected_class).'&academic_year='.urlencode($selected_year).'&semester='.urlencode($selected_sem) : '' ?>"
           class="flex items-center gap-2 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors">
            <i class="fas fa-table-cells"></i> Tracker
        </a>
    </nav>

    <!-- Filter Form -->
    <form method="GET" class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm mb-6 flex flex-wrap gap-4 items-end">
        <div class="flex flex-col gap-1.5">
            <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Class *</label>
            <select name="class" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold outline-none focus:ring-2 focus:ring-indigo-500 min-w-[140px]">
                <option value="">Select Class</option>
                <?php foreach ($classes as $cl): ?>
                <option value="<?= htmlspecialchars($cl) ?>" <?= $cl === $selected_class ? 'selected' : '' ?>><?= htmlspecialchars($cl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex flex-col gap-1.5">
            <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Academic Year</label>
            <select name="academic_year" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold outline-none focus:ring-2 focus:ring-indigo-500 min-w-[140px]">
                <?php foreach ($all_years as $yr): ?>
                <option value="<?= htmlspecialchars($yr) ?>" <?= $yr === $selected_year ? 'selected' : '' ?>><?= htmlspecialchars($yr) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex flex-col gap-1.5">
            <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Semester</label>
            <select name="semester" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold outline-none focus:ring-2 focus:ring-indigo-500 min-w-[160px]">
                <?php foreach ($semesters as $sm): ?>
                <option value="<?= htmlspecialchars($sm) ?>" <?= $sm === $selected_sem ? 'selected' : '' ?>><?= htmlspecialchars($sm) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors">
            <i class="fas fa-filter mr-1.5"></i> Load
        </button>
    </form>

    <?php if (!$selected_class): ?>
    <!-- No class selected -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-16 text-center text-slate-400">
        <i class="fas fa-link text-5xl block mb-4 opacity-20"></i>
        <p class="font-semibold text-lg">Select a class above to manage its stationery list.</p>
    </div>

    <?php elseif (empty($all_catalog)): ?>
    <!-- No items in catalog -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-16 text-center text-slate-400">
        <i class="fas fa-box-open text-5xl block mb-4 opacity-20"></i>
        <p class="font-semibold text-lg mb-3">No items in the catalog yet.</p>
        <a href="items.php" class="inline-flex items-center gap-2 bg-indigo-600 text-white text-xs font-black uppercase tracking-widest px-5 py-3 rounded-xl hover:bg-indigo-700 transition-colors">
            <i class="fas fa-plus"></i> Add Items to Catalog
        </a>
    </div>

    <?php else: ?>

    <!-- Currently Assigned -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest">
                    <?= htmlspecialchars($selected_class) ?> — <?= htmlspecialchars($selected_year) ?>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5"><?= count($assigned) ?> item<?= count($assigned) != 1 ? 's' : '' ?> assigned</p>
            </div>
            <?php if (count($assigned) > 0): ?>
            <div class="flex gap-2">
                <a href="print_list.php?class=<?= urlencode($selected_class) ?>&academic_year=<?= urlencode($selected_year) ?>" target="_blank"
                   class="flex items-center gap-2 bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-black uppercase tracking-widest px-4 py-2 rounded-xl hover:bg-indigo-100 transition-colors">
                    <i class="fas fa-print"></i> Print List
                </a>
                <a href="index.php?class=<?= urlencode($selected_class) ?>&academic_year=<?= urlencode($selected_year) ?>&semester=<?= urlencode($selected_sem) ?>"
                   class="flex items-center gap-2 bg-slate-900 text-white text-xs font-black uppercase tracking-widest px-4 py-2 rounded-xl hover:bg-slate-700 transition-colors">
                    <i class="fas fa-table-cells"></i> Open Tracker
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($assigned)): ?>
        <div class="p-10 text-center text-slate-400">
            <i class="fas fa-inbox text-4xl block mb-3 opacity-20"></i>
            <p class="text-sm font-semibold">No items assigned yet.</p>
            <p class="text-xs mt-1">Use the catalog below to add items to this class.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[550px]">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="px-5 py-3 text-left text-[0.5rem] font-black text-slate-500 uppercase tracking-widest">Item</th>
                    <th class="px-5 py-3 text-left text-[0.5rem] font-black text-slate-500 uppercase tracking-widest">Quantity</th>
                    <th class="px-5 py-3 text-left text-[0.5rem] font-black text-slate-500 uppercase tracking-widest">Price (GH₵)</th>
                    <th class="px-5 py-3 text-left text-[0.5rem] font-black text-slate-500 uppercase tracking-widest">Semester</th>
                    <th class="px-5 py-3 text-right text-[0.5rem] font-black text-slate-500 uppercase tracking-widest">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50" id="assigned-tbody">
                <?php foreach ($assigned as $a): ?>
                <tr id="arow-<?= $a['id'] ?>" class="hover:bg-slate-50 transition-colors group">
                    <!-- View mode -->
                    <td class="px-5 py-3 font-semibold text-slate-800 av-<?= $a['id'] ?>">
                        <?= htmlspecialchars($a['item_name']) ?>
                        <?php if ($a['unit']): ?><span class="text-xs text-slate-400 font-normal ml-1"><?= htmlspecialchars($a['unit']) ?></span><?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-slate-700 av-<?= $a['id'] ?>"><?= htmlspecialchars($a['quantity']) ?></td>
                    <td class="px-5 py-3 font-semibold av-<?= $a['id'] ?>">
                        <?= $a['price'] > 0 ? '<span class="text-indigo-600">'.number_format($a['price'],2).'</span>' : '<span class="text-slate-300 font-normal text-xs">—</span>' ?>
                    </td>
                    <td class="px-5 py-3 text-slate-500 text-xs av-<?= $a['id'] ?>"><?= htmlspecialchars($a['semester']) ?></td>
                    <td class="px-5 py-3 text-right av-<?= $a['id'] ?>">
                        <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="startEdit(<?= $a['id'] ?>)"
                                class="text-xs bg-slate-100 hover:bg-indigo-100 hover:text-indigo-700 text-slate-600 font-black px-3 py-1.5 rounded-lg transition-colors">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button onclick="removeAssignment(<?= $a['id'] ?>)"
                                class="text-xs bg-slate-100 hover:bg-rose-100 hover:text-rose-700 text-slate-600 font-black px-3 py-1.5 rounded-lg transition-colors">
                                <i class="fas fa-xmark"></i>
                            </button>
                        </div>
                    </td>

                    <!-- Edit mode (hidden) -->
                    <td class="px-3 py-2 ae-<?= $a['id'] ?> hidden" colspan="5">
                        <div class="flex flex-wrap items-center gap-2">
                            <input id="aqty-<?= $a['id'] ?>" type="text" value="<?= htmlspecialchars($a['quantity']) ?>"
                                placeholder="Quantity" class="border border-slate-200 bg-slate-50 rounded-lg px-3 py-1.5 text-sm outline-none w-28">
                            <input id="aprice-<?= $a['id'] ?>" type="number" value="<?= $a['price'] ?>" step="0.01" min="0"
                                placeholder="Price" class="border border-slate-200 bg-slate-50 rounded-lg px-3 py-1.5 text-sm outline-none w-24">
                            <select id="asem-<?= $a['id'] ?>" class="border border-slate-200 bg-slate-50 rounded-lg px-3 py-1.5 text-sm outline-none">
                                <?php foreach ($semesters as $sm): ?>
                                <option value="<?= htmlspecialchars($sm) ?>" <?= $sm === $a['semester'] ? 'selected' : '' ?>><?= htmlspecialchars($sm) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button onclick="saveAssignment(<?= $a['id'] ?>)" class="bg-emerald-600 text-white text-xs font-black px-3 py-1.5 rounded-lg hover:bg-emerald-700">Save</button>
                            <button onclick="cancelEdit(<?= $a['id'] ?>)" class="bg-slate-100 text-slate-600 text-xs font-black px-3 py-1.5 rounded-lg">Cancel</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Catalog: Not Yet Assigned -->
    <?php if (!empty($not_assigned)): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest">Add from Catalog</h2>
            <p class="text-xs text-slate-500 mt-0.5">Items not yet assigned to <?= htmlspecialchars($selected_class) ?> / <?= htmlspecialchars($selected_year) ?>.</p>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[520px]">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="px-5 py-3 text-left text-[0.5rem] font-black text-slate-500 uppercase tracking-widest">Item</th>
                    <th class="px-5 py-3 text-left text-[0.5rem] font-black text-slate-500 uppercase tracking-widest">Quantity</th>
                    <th class="px-5 py-3 text-left text-[0.5rem] font-black text-slate-500 uppercase tracking-widest">Price (GH₵)</th>
                    <th class="px-5 py-3 text-right text-[0.5rem] font-black text-slate-500 uppercase tracking-widest">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($not_assigned as $item): ?>
                <tr id="crow-<?= $item['id'] ?>" class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3 font-semibold text-slate-700">
                        <?= htmlspecialchars($item['name']) ?>
                        <?php if ($item['description']): ?><span class="block text-xs text-slate-400 font-normal"><?= htmlspecialchars($item['description']) ?></span><?php endif; ?>
                    </td>
                    <td class="px-5 py-2">
                        <input type="text" id="cqty-<?= $item['id'] ?>" value="<?= htmlspecialchars($item['unit'] ?: '1') ?>"
                            class="border border-slate-200 bg-slate-50 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 w-28" placeholder="Qty/unit">
                    </td>
                    <td class="px-5 py-2">
                        <input type="number" id="cprice-<?= $item['id'] ?>" value="<?= $item['default_price'] ?>" step="0.01" min="0"
                            class="border border-slate-200 bg-slate-50 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 w-24" placeholder="0.00">
                    </td>
                    <td class="px-5 py-2 text-right">
                        <button onclick="assignItem(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>')"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-widest px-4 py-1.5 rounded-lg transition-colors inline-flex items-center gap-1.5">
                            <i class="fas fa-plus"></i> Assign
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</main>

<div id="toast" class="fixed bottom-6 right-6 z-50 hidden text-sm font-semibold px-5 py-3 rounded-2xl shadow-xl flex items-center gap-3"></div>

<script>
const API       = 'api_stationery.php';
const SEL_CLASS = <?= json_encode($selected_class) ?>;
const SEL_YEAR  = <?= json_encode($selected_year) ?>;
const SEL_SEM   = <?= json_encode($selected_sem) ?>;

function showToast(msg, ok = true) {
    const t = document.getElementById('toast');
    t.className = 'fixed bottom-6 right-6 z-50 flex items-center gap-3 text-sm font-semibold px-5 py-3 rounded-2xl shadow-xl ' + (ok ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white');
    t.innerHTML = `<i class="fas ${ok ? 'fa-circle-check' : 'fa-circle-xmark'}"></i>${msg}`;
    t.classList.remove('hidden');
    setTimeout(() => t.classList.add('hidden'), 3500);
}
async function apiPost(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    return (await fetch(API, { method: 'POST', body: fd })).json();
}

async function assignItem(itemId, name) {
    const qty   = document.getElementById('cqty-' + itemId)?.value?.trim() || '1';
    const price = document.getElementById('cprice-' + itemId)?.value || '0';
    const res   = await apiPost({ action: 'assign_item', item_id: itemId, class: SEL_CLASS, academic_year: SEL_YEAR, semester: SEL_SEM, quantity: qty, price });
    if (res.success) { showToast(`"${name}" assigned.`); setTimeout(() => location.reload(), 600); }
    else showToast(res.message || 'Error.', false);
}

function startEdit(id) {
    document.querySelectorAll('.av-' + id).forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.ae-' + id).forEach(el => el.classList.remove('hidden'));
}
function cancelEdit(id) {
    document.querySelectorAll('.ae-' + id).forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.av-' + id).forEach(el => el.classList.remove('hidden'));
}
async function saveAssignment(id) {
    const qty     = document.getElementById('aqty-' + id).value.trim();
    const price   = document.getElementById('aprice-' + id).value || '0';
    const semester = document.getElementById('asem-' + id).value;
    const res     = await apiPost({ action: 'edit_assignment', id, quantity: qty, price, semester });
    if (res.success) { showToast('Updated.'); setTimeout(() => location.reload(), 500); }
    else showToast(res.message || 'Error.', false);
}
async function removeAssignment(id) {
    if (!confirm('Remove this item from the class list?')) return;
    const res = await apiPost({ action: 'unassign_item', id });
    if (res.success) { showToast('Removed.'); document.getElementById('arow-' + id)?.remove(); }
    else showToast(res.message || 'Error.', false);
}
</script>
</body>
</html>
