<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../dashboard.php'); exit;
}

$items = [];
$rs = $conn->query("
    SELECT si.*, COUNT(sa.id) as assignment_count
    FROM stationery_items si
    LEFT JOIN stationery_assignments sa ON sa.item_id = si.id
    GROUP BY si.id
    ORDER BY si.name ASC
");
while ($r = $rs->fetch_assoc()) $items[] = $r;
$total = count($items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stationery Items | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-900">
<?php include '../../../includes/sidebar_admin_modern.php'; ?>

<main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">

    <!-- Header -->
    <header class="mb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-indigo-600 transition-colors"><i class="fas fa-home"></i> Admin</a>
                <span>/</span>
                <span class="text-indigo-600">Stationery</span>
            </div>
            <h1 class="text-3xl font-black text-slate-900">Stationery <span class="text-indigo-600">Items</span></h1>
            <p class="text-slate-500 mt-1 text-sm">Master catalog of all stationery items. Assign them to classes from the <strong>Assign</strong> tab.</p>
        </div>
        <div class="bg-white border border-slate-100 rounded-2xl px-5 py-3 text-center shadow-sm">
            <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Total Items</p>
            <p class="text-2xl font-black text-indigo-600"><?= $total ?></p>
        </div>
    </header>

    <!-- Tab Navigation -->
    <nav class="flex gap-1 mb-6 bg-white border border-slate-100 rounded-2xl p-1.5 shadow-sm w-fit flex-wrap">
        <span class="flex items-center gap-2 bg-indigo-600 text-white text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl">
            <i class="fas fa-box-open"></i> Items
        </span>
        <a href="assign.php" class="flex items-center gap-2 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors">
            <i class="fas fa-link"></i> Assign to Class
        </a>
        <a href="index.php" class="flex items-center gap-2 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors">
            <i class="fas fa-table-cells"></i> Tracker
        </a>
        <a href="settings.php" class="flex items-center gap-2 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors">
            <i class="fas fa-gear"></i> Settings
        </a>
    </nav>

    <!-- Items Table -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
        <?php if (empty($items)): ?>
        <div class="p-16 text-center text-slate-400">
            <i class="fas fa-box-open text-5xl block mb-4 opacity-20"></i>
            <p class="font-semibold text-lg mb-1">No stationery items yet.</p>
            <p class="text-sm">Add your first item using the form below.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[600px]">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="px-5 py-3 text-left text-[0.5rem] font-black text-slate-500 uppercase tracking-widest w-8">#</th>
                    <th class="px-5 py-3 text-left text-[0.5rem] font-black text-slate-500 uppercase tracking-widest">Item Name</th>
                    <th class="px-5 py-3 text-left text-[0.5rem] font-black text-slate-500 uppercase tracking-widest">Default Qty / Unit</th>
                    <th class="px-5 py-3 text-left text-[0.5rem] font-black text-slate-500 uppercase tracking-widest">Default Price</th>
                    <th class="px-5 py-3 text-center text-[0.5rem] font-black text-slate-500 uppercase tracking-widest">Assigned To</th>
                    <th class="px-5 py-3 text-right text-[0.5rem] font-black text-slate-500 uppercase tracking-widest">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($items as $i => $item): ?>
                <tr id="row-<?= $item['id'] ?>" class="hover:bg-slate-50 transition-colors group">
                    <td class="px-5 py-3 text-slate-400 text-xs font-bold"><?= $i + 1 ?></td>

                    <!-- View cells -->
                    <td class="px-5 py-3 font-semibold text-slate-800 vw-<?= $item['id'] ?>">
                        <?= htmlspecialchars($item['name']) ?>
                        <?php if ($item['description']): ?>
                        <span class="block text-xs text-slate-400 font-normal"><?= htmlspecialchars($item['description']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-slate-600 vw-<?= $item['id'] ?>"><?= htmlspecialchars($item['unit']) ?: '<span class="text-slate-300">—</span>' ?></td>
                    <td class="px-5 py-3 font-semibold vw-<?= $item['id'] ?>">
                        <?= $item['default_price'] > 0 ? '<span class="text-indigo-600">GH₵ '.number_format($item['default_price'],2).'</span>' : '<span class="text-slate-300 font-normal text-xs">No price</span>' ?>
                    </td>

                    <!-- Edit cells (hidden) -->
                    <td class="px-3 py-2 ed-<?= $item['id'] ?> hidden" colspan="3">
                        <div class="flex flex-wrap items-center gap-2">
                            <input id="en-<?= $item['id'] ?>" type="text" value="<?= htmlspecialchars($item['name']) ?>"
                                placeholder="Item name" class="border border-slate-200 bg-slate-50 rounded-lg px-3 py-1.5 text-sm font-semibold outline-none focus:ring-2 focus:ring-indigo-500 w-44">
                            <input id="eu-<?= $item['id'] ?>" type="text" value="<?= htmlspecialchars($item['unit']) ?>"
                                placeholder="Unit / qty" class="border border-slate-200 bg-slate-50 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 w-28">
                            <input id="ep-<?= $item['id'] ?>" type="number" value="<?= $item['default_price'] ?>" step="0.01" min="0"
                                placeholder="Price" class="border border-slate-200 bg-slate-50 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 w-24">
                            <input id="ed-<?= $item['id'] ?>" type="text" value="<?= htmlspecialchars($item['description'] ?? '') ?>"
                                placeholder="Description (opt.)" class="border border-slate-200 bg-slate-50 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 w-44">
                            <button onclick="saveEdit(<?= $item['id'] ?>)" class="bg-emerald-600 text-white text-xs font-black px-3 py-1.5 rounded-lg hover:bg-emerald-700">Save</button>
                            <button onclick="cancelEdit(<?= $item['id'] ?>)" class="bg-slate-100 text-slate-600 text-xs font-black px-3 py-1.5 rounded-lg">Cancel</button>
                        </div>
                    </td>

                    <td class="px-5 py-3 text-center vw-<?= $item['id'] ?>">
                        <?php if ($item['assignment_count'] > 0): ?>
                        <a href="assign.php" class="bg-indigo-50 text-indigo-700 text-[0.5rem] font-black px-2.5 py-1 rounded-full uppercase tracking-widest hover:bg-indigo-100 transition-colors">
                            <?= $item['assignment_count'] ?> class<?= $item['assignment_count'] != 1 ? 'es' : '' ?>
                        </a>
                        <?php else: ?>
                        <span class="text-slate-300 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-right vw-<?= $item['id'] ?>">
                        <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="startEdit(<?= $item['id'] ?>)"
                                class="text-xs bg-slate-100 hover:bg-indigo-100 hover:text-indigo-700 text-slate-600 font-black px-3 py-1.5 rounded-lg transition-colors">
                                <i class="fas fa-pen mr-1"></i>Edit
                            </button>
                            <button onclick="deleteItem(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>')"
                                class="text-xs bg-slate-100 hover:bg-rose-100 hover:text-rose-700 text-slate-600 font-black px-3 py-1.5 rounded-lg transition-colors">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Item Form -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
        <h3 class="text-xs font-black text-slate-700 uppercase tracking-widest mb-4 flex items-center gap-2">
            <i class="fas fa-plus-circle text-indigo-600"></i> Add New Item to Catalog
        </h3>
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex flex-col gap-1.5 flex-1 min-w-[180px]">
                <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Item Name *</label>
                <input type="text" id="new-name" placeholder="e.g. Nataraj Pencils"
                    class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-semibold outline-none focus:ring-2 focus:ring-indigo-500 w-full">
            </div>
            <div class="flex flex-col gap-1.5 flex-1 min-w-[130px]">
                <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Default Qty / Unit</label>
                <input type="text" id="new-unit" placeholder="e.g. 2 packs"
                    class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 w-full">
            </div>
            <div class="flex flex-col gap-1.5 min-w-[120px]">
                <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Default Price (GH₵)</label>
                <input type="number" id="new-price" placeholder="0.00" step="0.01" min="0"
                    class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 w-full">
            </div>
            <div class="flex flex-col gap-1.5 flex-1 min-w-[160px]">
                <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Notes (optional)</label>
                <input type="text" id="new-desc" placeholder="e.g. Must be labelled"
                    class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 w-full">
            </div>
            <button onclick="addItem()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors flex items-center gap-2">
                <i class="fas fa-plus"></i> Add Item
            </button>
        </div>
    </div>

</main>

<div id="toast" class="fixed bottom-6 right-6 z-50 hidden text-sm font-semibold px-5 py-3 rounded-2xl shadow-xl flex items-center gap-3"></div>

<script>
const API = 'api_stationery.php';

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

async function addItem() {
    const name  = document.getElementById('new-name').value.trim();
    const unit  = document.getElementById('new-unit').value.trim();
    const price = document.getElementById('new-price').value || '0';
    const desc  = document.getElementById('new-desc').value.trim();
    if (!name) { showToast('Item name is required.', false); return; }
    const res = await apiPost({ action: 'add_item', name, unit, default_price: price, description: desc });
    if (res.success) { showToast('Item added.'); setTimeout(() => location.reload(), 600); }
    else showToast(res.message || 'Error.', false);
}

function startEdit(id) {
    document.querySelectorAll('.vw-' + id).forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.ed-' + id).forEach(el => el.classList.remove('hidden'));
}
function cancelEdit(id) {
    document.querySelectorAll('.ed-' + id).forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.vw-' + id).forEach(el => el.classList.remove('hidden'));
}
async function saveEdit(id) {
    const name = document.getElementById('en-' + id).value.trim();
    const unit = document.getElementById('eu-' + id).value.trim();
    const price = document.getElementById('ep-' + id).value || '0';
    const desc = document.getElementById('ed-' + id).value.trim();
    if (!name) { showToast('Name required.', false); return; }
    const res = await apiPost({ action: 'edit_item', id, name, unit, default_price: price, description: desc });
    if (res.success) { showToast('Saved.'); setTimeout(() => location.reload(), 500); }
    else showToast(res.message || 'Error.', false);
}
async function deleteItem(id, name) {
    if (!confirm(`Delete "${name}"?\n\nThis will also remove all class assignments for this item.`)) return;
    const res = await apiPost({ action: 'delete_item', id });
    if (res.success) { showToast('Deleted.'); document.getElementById('row-' + id)?.remove(); }
    else showToast(res.message || 'Cannot delete.', false);
}

document.getElementById('new-name').addEventListener('keydown', e => { if (e.key === 'Enter') addItem(); });
</script>
</body>
</html>
