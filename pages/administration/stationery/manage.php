<?php
// manage.php is superseded by items.php + assign.php
$qs = http_build_query(array_intersect_key($_GET, array_flip(['class','academic_year'])));
header('Location: items.php' . ($qs ? '?'.$qs : '')); exit;

$current_year = getAcademicYear($conn);
$selected_class = $_GET['class'] ?? '';
$selected_year  = $_GET['academic_year'] ?? $current_year;

// Classes list
$classes = [];
$cr = $conn->query("SELECT DISTINCT class FROM students WHERE status='active' AND class IS NOT NULL AND class != '' ORDER BY class ASC");
while ($c = $cr->fetch_assoc()) $classes[] = $c['class'];

// Academic years
$yr_rs = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
$all_years = [];
while ($y = $yr_rs->fetch_assoc()) $all_years[] = $y['academic_year'];
if (!in_array($selected_year, $all_years)) array_unshift($all_years, $selected_year);

// Items for selected class/year
$items = [];
if ($selected_class) {
    $sc = $conn->real_escape_string($selected_class);
    $sy = $conn->real_escape_string($selected_year);
    $ir = $conn->query("SELECT * FROM class_stationery_items WHERE class='$sc' AND academic_year='$sy' ORDER BY sort_order ASC, id ASC");
    while ($i = $ir->fetch_assoc()) $items[] = $i;
}

// Previous year for copy button
$prev_year = '';
if (count($all_years) > 1) {
    $idx = array_search($selected_year, $all_years);
    $prev_year = $all_years[$idx + 1] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Stationery | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-900">
<?php include '../../../includes/sidebar_admin_modern.php'; ?>

<main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">

    <!-- Header -->
    <header class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-indigo-600 transition-colors"><i class="fas fa-home"></i> Admin</a>
                <span>/</span>
                <a href="index.php" class="hover:text-indigo-600 transition-colors">Stationery</a>
                <span>/</span>
                <span class="text-indigo-600">Manage Items</span>
            </div>
            <h1 class="text-3xl font-black text-slate-900">Manage <span class="text-indigo-600">Stationery List</span></h1>
            <p class="text-slate-500 mt-1 text-sm">Add, edit or remove stationery items per class and academic year.</p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($selected_class && !empty($items)): ?>
            <a href="print_list.php?class=<?= urlencode($selected_class) ?>&academic_year=<?= urlencode($selected_year) ?>" target="_blank"
               class="flex items-center gap-2 bg-indigo-600 text-white text-xs font-black uppercase tracking-widest px-5 py-3 rounded-2xl hover:bg-indigo-700 transition-colors">
                <i class="fas fa-print"></i> Print List
            </a>
            <?php endif; ?>
            <a href="index.php<?= $selected_class ? '?class='.urlencode($selected_class).'&academic_year='.urlencode($selected_year) : '' ?>"
               class="flex items-center gap-2 bg-slate-900 text-white text-xs font-black uppercase tracking-widest px-5 py-3 rounded-2xl hover:bg-slate-700 transition-colors">
                <i class="fas fa-arrow-left"></i> Back to Tracker
            </a>
        </div>
    </header>

    <!-- Tab Navigation -->
    <nav class="flex gap-1 mb-6 bg-white border border-slate-100 rounded-2xl p-1.5 shadow-sm w-fit">
        <a href="index.php<?= $selected_class ? '?class='.urlencode($selected_class).'&academic_year='.urlencode($selected_year) : '' ?>"
           class="flex items-center gap-2 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors">
            <i class="fas fa-table-cells"></i> Tracker
        </a>
        <span class="flex items-center gap-2 bg-indigo-600 text-white text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl">
            <i class="fas fa-gear"></i> Manage Items
        </span>
    </nav>

    <!-- Period Selector -->
    <form method="GET" class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm mb-6 flex flex-wrap gap-4 items-end">
        <div class="flex flex-col gap-1.5 min-w-[160px]">
            <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Class</label>
            <select name="class" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Select Class</option>
                <?php foreach ($classes as $cl): ?>
                <option value="<?= htmlspecialchars($cl) ?>" <?= $cl === $selected_class ? 'selected' : '' ?>><?= htmlspecialchars($cl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex flex-col gap-1.5 min-w-[160px]">
            <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Academic Year</label>
            <select name="academic_year" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold outline-none focus:ring-2 focus:ring-indigo-500">
                <?php foreach ($all_years as $yr): ?>
                <option value="<?= htmlspecialchars($yr) ?>" <?= $yr === $selected_year ? 'selected' : '' ?>><?= htmlspecialchars($yr) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors">
            <i class="fas fa-filter mr-1.5"></i>Load
        </button>
        <?php if ($selected_class && $prev_year): ?>
        <button type="button" id="copyYearBtn"
            class="bg-amber-50 border border-amber-200 text-amber-700 hover:bg-amber-100 text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors"
            data-from="<?= htmlspecialchars($prev_year) ?>" data-to="<?= htmlspecialchars($selected_year) ?>" data-class="<?= htmlspecialchars($selected_class) ?>">
            <i class="fas fa-copy mr-1.5"></i>Copy from <?= htmlspecialchars($prev_year) ?>
        </button>
        <?php endif; ?>
    </form>

    <?php if (!$selected_class): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-12 text-center text-slate-400">
        <i class="fas fa-hand-pointer text-4xl mb-3 block opacity-30"></i>
        <p class="font-semibold">Select a class above to manage its stationery list.</p>
    </div>
    <?php else: ?>

    <!-- Items table -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-black text-slate-700 uppercase tracking-widest">
                <?= htmlspecialchars($selected_class) ?> &mdash; <?= htmlspecialchars($selected_year) ?>
                <span id="itemCount" class="ml-2 bg-indigo-100 text-indigo-700 text-[0.5rem] font-black px-2 py-0.5 rounded-full"><?= count($items) ?> items</span>
            </h2>
        </div>

        <div id="itemsTable">
        <?php if (empty($items)): ?>
        <div id="emptyRow" class="px-6 py-10 text-center text-slate-400 text-sm">No items yet. Add one below.</div>
        <?php else: ?>
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="px-6 py-3 text-left text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Item</th>
                    <th class="px-4 py-3 text-left text-[0.5rem] font-black text-slate-400 uppercase tracking-widest w-24">Qty</th>
                    <th class="px-4 py-3 text-left text-[0.5rem] font-black text-slate-400 uppercase tracking-widest w-28">Price (GH₵)</th>
                    <th class="px-4 py-3 text-left text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Notes</th>
                    <th class="px-4 py-3 w-20"></th>
                </tr>
            </thead>
            <tbody id="itemsTbody" class="divide-y divide-slate-50">
                <?php foreach ($items as $item): ?>
                <?= itemRow($item) ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
    </div>

    <!-- Add Item Form -->
    <div class="bg-white rounded-2xl border border-indigo-100 shadow-sm p-6">
        <h3 class="text-xs font-black text-slate-700 uppercase tracking-widest mb-4"><i class="fas fa-plus text-indigo-500 mr-2"></i>Add Item</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="lg:col-span-2">
                <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest block mb-1">Item Name *</label>
                <input type="text" id="newItemName" placeholder="e.g. Exercise Book" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest block mb-1">Quantity</label>
                <input type="text" id="newItemQty" placeholder="e.g. 2" value="1" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest block mb-1">Price (optional)</label>
                <input type="number" id="newItemPrice" placeholder="0.00" step="0.01" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>
        <div class="mb-4">
            <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest block mb-1">Notes (optional)</label>
            <input type="text" id="newItemNotes" placeholder="Any additional info..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex items-center gap-3">
            <button id="addItemBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-widest px-6 py-2.5 rounded-xl transition-colors">
                <i class="fas fa-plus mr-2"></i>Add Item
            </button>
            <span id="addMsg" class="text-xs font-semibold hidden"></span>
        </div>
    </div>

    <?php endif; ?>
</main>

<!-- Toast -->
<div id="toast" class="fixed bottom-6 right-6 z-50 hidden bg-slate-900 text-white text-sm font-semibold px-5 py-3 rounded-2xl shadow-xl flex items-center gap-3"></div>

<script>
const SELECTED_CLASS = <?= json_encode($selected_class) ?>;
const SELECTED_YEAR  = <?= json_encode($selected_year) ?>;
const API = 'api_stationery.php';

function showToast(msg, ok = true) {
    const t = document.getElementById('toast');
    t.className = 'fixed bottom-6 right-6 z-50 flex items-center gap-3 text-sm font-semibold px-5 py-3 rounded-2xl shadow-xl ' +
        (ok ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white');
    t.innerHTML = `<i class="fas ${ok ? 'fa-circle-check' : 'fa-circle-xmark'}"></i>${msg}`;
    t.classList.remove('hidden');
    setTimeout(() => t.classList.add('hidden'), 3500);
}

async function api(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k,v]) => fd.append(k, v));
    const r = await fetch(API, { method: 'POST', body: fd });
    return r.json();
}

function updateCount(delta) {
    const el = document.getElementById('itemCount');
    if (!el) return;
    const n = parseInt(el.textContent) + delta;
    el.textContent = n + ' items';
}

// ── Add Item ─────────────────────────────────────────────────
document.getElementById('addItemBtn')?.addEventListener('click', async () => {
    const name  = document.getElementById('newItemName').value.trim();
    const qty   = document.getElementById('newItemQty').value.trim() || '1';
    const price = document.getElementById('newItemPrice').value.trim();
    const notes = document.getElementById('newItemNotes').value.trim();

    if (!name) { showToast('Item name is required.', false); return; }

    const res = await api({ action: 'add', class: SELECTED_CLASS, academic_year: SELECTED_YEAR,
        item_name: name, quantity: qty, price: price, notes: notes });

    if (res.success) {
        showToast('Item added.');
        document.getElementById('newItemName').value = '';
        document.getElementById('newItemPrice').value = '';
        document.getElementById('newItemNotes').value = '';
        document.getElementById('newItemQty').value = '1';
        // Reload table section
        location.reload();
    } else {
        showToast(res.message || 'Error adding item.', false);
    }
});

// ── Edit Item ─────────────────────────────────────────────────
document.addEventListener('click', async (e) => {
    const editBtn = e.target.closest('[data-edit]');
    if (!editBtn) return;
    const row = editBtn.closest('tr');
    const id = editBtn.dataset.edit;

    // Toggle edit mode
    if (row.classList.contains('editing')) {
        // Save
        const item_name = row.querySelector('.edit-name').value.trim();
        const quantity  = row.querySelector('.edit-qty').value.trim();
        const price     = row.querySelector('.edit-price').value.trim();
        const notes     = row.querySelector('.edit-notes').value.trim();

        const res = await api({ action: 'edit', id, item_name, quantity, price, notes });
        if (res.success) { showToast('Saved.'); location.reload(); }
        else showToast(res.message || 'Error.', false);
    } else {
        row.classList.add('editing');
        row.querySelectorAll('.view-mode').forEach(el => el.classList.add('hidden'));
        row.querySelectorAll('.edit-mode').forEach(el => el.classList.remove('hidden'));
        editBtn.innerHTML = '<i class="fas fa-check"></i>';
        editBtn.title = 'Save';
    }
});

// ── Delete Item ───────────────────────────────────────────────
document.addEventListener('click', async (e) => {
    const delBtn = e.target.closest('[data-delete]');
    if (!delBtn) return;
    if (!confirm('Delete this item? This will also remove all tracking records for it.')) return;

    const res = await api({ action: 'delete', id: delBtn.dataset.delete });
    if (res.success) { delBtn.closest('tr').remove(); updateCount(-1); showToast('Deleted.'); }
    else showToast('Could not delete.', false);
});

// ── Copy from previous year ───────────────────────────────────
document.getElementById('copyYearBtn')?.addEventListener('click', async function() {
    const from  = this.dataset.from;
    const to    = this.dataset.to;
    const cls   = this.dataset.class;
    if (!confirm(`Copy all stationery items from ${from} to ${to} for ${cls}? Existing items for ${to} will be replaced.`)) return;

    const res = await api({ action: 'copy_year', class: cls, from_year: from, to_year: to });
    if (res.success) { showToast(res.message); setTimeout(() => location.reload(), 1000); }
    else showToast(res.message || 'Error.', false);
});
</script>
</body>
</html>
<?php
function itemRow($item) {
    $price = $item['price'] !== null ? number_format($item['price'], 2) : '';
    ob_start(); ?>
    <tr data-id="<?= $item['id'] ?>">
        <td class="px-6 py-3">
            <span class="view-mode font-semibold text-sm text-slate-800"><?= htmlspecialchars($item['item_name']) ?></span>
            <input class="edit-mode hidden edit-name bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-sm w-full" value="<?= htmlspecialchars($item['item_name']) ?>">
        </td>
        <td class="px-4 py-3">
            <span class="view-mode text-sm text-slate-600"><?= htmlspecialchars($item['quantity']) ?></span>
            <input class="edit-mode hidden edit-qty bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-sm w-20" value="<?= htmlspecialchars($item['quantity']) ?>">
        </td>
        <td class="px-4 py-3">
            <span class="view-mode text-sm <?= $price ? 'text-indigo-700 font-semibold' : 'text-slate-300' ?>"><?= $price ?: '—' ?></span>
            <input class="edit-mode hidden edit-price bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-sm w-24" type="number" step="0.01" min="0" value="<?= htmlspecialchars($item['price'] ?? '') ?>">
        </td>
        <td class="px-4 py-3">
            <span class="view-mode text-xs text-slate-400"><?= htmlspecialchars($item['notes'] ?? '') ?></span>
            <input class="edit-mode hidden edit-notes bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-sm w-full" value="<?= htmlspecialchars($item['notes'] ?? '') ?>">
        </td>
        <td class="px-4 py-3">
            <div class="flex items-center gap-2 justify-end">
                <button data-edit="<?= $item['id'] ?>" title="Edit"
                    class="w-8 h-8 rounded-lg bg-slate-50 hover:bg-indigo-100 text-slate-400 hover:text-indigo-600 flex items-center justify-center transition-colors text-xs">
                    <i class="fas fa-pen"></i>
                </button>
                <button data-delete="<?= $item['id'] ?>" title="Delete"
                    class="w-8 h-8 rounded-lg bg-slate-50 hover:bg-rose-100 text-slate-400 hover:text-rose-600 flex items-center justify-center transition-colors text-xs">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </td>
    </tr>
    <?php return ob_get_clean();
}
?>
