<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
require_once '../../../includes/semester_helpers.php';

if (!is_logged_in()) { header('Location: ../../../login'); exit; }
require_finance_access();

$semester      = trim($_GET['semester'] ?? getCurrentSemester($conn));
$academic_year = trim($_GET['academic_year'] ?? getAcademicYear($conn));
$category      = trim($_GET['category'] ?? '');

if (!$category) {
    header('Location: semester_budget.php');
    exit;
}

// Ensure semester_budgets header exists
$sem_esc  = $conn->real_escape_string($semester);
$year_esc = $conn->real_escape_string($academic_year);
$cat_esc  = $conn->real_escape_string($category);

$budget = $conn->query("SELECT * FROM semester_budgets WHERE semester='$sem_esc' AND academic_year='$year_esc'")->fetch_assoc();
if (!$budget) {
    $conn->query("INSERT INTO semester_budgets (semester, academic_year, expected_income, created_at) VALUES ('$sem_esc','$year_esc',0,NOW())");
    $budget_id = $conn->insert_id;
    $budget = $conn->query("SELECT * FROM semester_budgets WHERE id=$budget_id")->fetch_assoc();
} else {
    $budget_id = (int)$budget['id'];
}

$is_locked = isset($budget['status']) && $budget['status'] === 'locked';

// Ensure expense item row exists for this category
$item = $conn->query("SELECT * FROM semester_budget_items WHERE semester_budget_id=$budget_id AND category='$cat_esc' AND type='expense'")->fetch_assoc();
if (!$item) {
    $conn->query("INSERT INTO semester_budget_items (semester_budget_id, category, type, amount) VALUES ($budget_id,'$cat_esc','expense',0)");
    $item_id = $conn->insert_id;
    $item = $conn->query("SELECT * FROM semester_budget_items WHERE id=$item_id")->fetch_assoc();
} else {
    $item_id = (int)$item['id'];
}

// Fetch sub-items
$sources_result = $conn->query("SELECT * FROM semester_budget_item_sources WHERE budget_item_id=$item_id ORDER BY id ASC");
$sources = [];
while ($s = $sources_result->fetch_assoc()) $sources[] = $s;

$display_year = formatAcademicYearDisplay($conn, $academic_year);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($category) ?> Budget Items | <?= htmlspecialchars($semester) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .item-row { transition: background 0.15s; }
        .item-row:hover { background: #f8fafc; }
        .edit-view { display: none; }
        .editing .display-view { display: none; }
        .editing .edit-view { display: contents; }
    </style>
</head>
<body class="text-slate-900">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">

        <!-- Header -->
        <header class="mb-10">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-4 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-indigo-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <a href="semester_budget.php?semester=<?= urlencode($semester) ?>&academic_year=<?= urlencode($academic_year) ?>" class="hover:text-indigo-600 transition-colors">Budget</a>
                <span>/</span>
                <a href="edit_semester_budget.php?semester=<?= urlencode($semester) ?>&academic_year=<?= urlencode($academic_year) ?>" class="hover:text-indigo-600 transition-colors">Edit Budget</a>
                <span>/</span>
                <span class="text-indigo-600"><?= htmlspecialchars($category) ?></span>
            </div>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 bg-rose-100 text-rose-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-receipt text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-black text-slate-900"><?= htmlspecialchars($category) ?></h1>
                            <p class="text-xs font-medium text-slate-500"><?= htmlspecialchars($semester) ?> &bull; <?= htmlspecialchars($display_year) ?> &bull; Expense Category</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <?php if ($is_locked): ?>
                        <span class="flex items-center gap-2 text-xs font-black text-amber-600 bg-amber-50 border border-amber-100 px-4 py-2 rounded-xl">
                            <i class="fas fa-lock text-xs"></i> Budget Locked
                        </span>
                    <?php endif; ?>
                    <a href="edit_semester_budget.php?semester=<?= urlencode($semester) ?>&academic_year=<?= urlencode($academic_year) ?>" class="flex items-center gap-2 text-sm font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-4 py-2 rounded-xl transition-colors">
                        <i class="fas fa-arrow-left text-xs"></i> Back to Budget
                    </a>
                </div>
            </div>
        </header>

        <!-- Alerts -->
        <div id="alertBox" class="hidden mb-6 p-4 rounded-2xl text-sm font-bold"></div>

        <!-- Total Bar -->
        <div class="bg-slate-900 text-white rounded-2xl px-8 py-5 mb-6 flex items-center justify-between">
            <div>
                <p class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-1">Category Total (auto-calculated)</p>
                <p class="text-2xl font-black" id="categoryTotal">GH₵ <?= number_format($item['amount'], 2) ?></p>
            </div>
            <div class="text-right">
                <p class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-1">Items</p>
                <p class="text-2xl font-black" id="itemCount"><?= count($sources) ?></p>
            </div>
        </div>

        <!-- Items Table -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest">Budget Line Items</h3>
                <?php if (!$is_locked): ?>
                <button onclick="showAddForm()" id="addBtn" class="flex items-center gap-2 text-xs font-black text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-4 py-2 rounded-xl transition-colors">
                    <i class="fas fa-plus text-xs"></i> Add Item
                </button>
                <?php endif; ?>
            </div>

            <table class="w-full" id="itemsTable">
                <thead>
                    <tr class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest bg-slate-50/50 border-b border-slate-100">
                        <th class="px-6 py-4 text-left w-[50%]">Item Description</th>
                        <th class="px-6 py-4 text-right w-[20%]">Amount (GH₵)</th>
                        <th class="px-6 py-4 text-left w-[20%]">Notes</th>
                        <?php if (!$is_locked): ?>
                        <th class="px-6 py-4 text-center w-[10%]">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <?php if (empty($sources)): ?>
                    <tr id="emptyRow">
                        <td colspan="4" class="px-6 py-12 text-center text-slate-400 text-sm font-medium italic">
                            No items yet. Click "Add Item" to start building this budget category.
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($sources as $s): ?>
                    <tr class="item-row border-b border-slate-50" id="row-<?= $s['id'] ?>">
                        <!-- Display view -->
                        <td class="display-view px-6 py-4 text-sm font-semibold text-slate-800"><?= htmlspecialchars($s['source']) ?></td>
                        <td class="display-view px-6 py-4 text-right font-black text-slate-900"><?= number_format($s['amount'], 2) ?></td>
                        <td class="display-view px-6 py-4 text-xs text-slate-400 font-medium"><?= htmlspecialchars($s['notes'] ?? '') ?></td>
                        <?php if (!$is_locked): ?>
                        <td class="display-view px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="editRow(<?= $s['id'] ?>)" class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white flex items-center justify-center transition-all" title="Edit">
                                    <i class="fas fa-pencil text-xs"></i>
                                </button>
                                <button onclick="deleteRow(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['source'])) ?>')" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white flex items-center justify-center transition-all" title="Remove">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </div>
                        </td>
                        <?php endif; ?>
                        <!-- Edit view -->
                        <td class="edit-view px-4 py-3">
                            <input type="text" id="edit-source-<?= $s['id'] ?>" value="<?= htmlspecialchars($s['source']) ?>" placeholder="Item description" class="w-full border border-indigo-300 rounded-lg px-3 py-2 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500">
                        </td>
                        <td class="edit-view px-4 py-3">
                            <input type="number" step="0.01" id="edit-amount-<?= $s['id'] ?>" value="<?= $s['amount'] ?>" placeholder="0.00" class="w-full border border-indigo-300 rounded-lg px-3 py-2 text-sm font-black outline-none focus:ring-2 focus:ring-indigo-500 text-right">
                        </td>
                        <td class="edit-view px-4 py-3">
                            <input type="text" id="edit-notes-<?= $s['id'] ?>" value="<?= htmlspecialchars($s['notes'] ?? '') ?>" placeholder="Notes (optional)" class="w-full border border-indigo-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        </td>
                        <?php if (!$is_locked): ?>
                        <td class="edit-view px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="saveEdit(<?= $s['id'] ?>)" class="w-8 h-8 rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 flex items-center justify-center transition-all" title="Save">
                                    <i class="fas fa-check text-xs"></i>
                                </button>
                                <button onclick="cancelEdit(<?= $s['id'] ?>)" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center transition-all" title="Cancel">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-rose-50/40 border-t-2 border-rose-100">
                        <td class="px-6 py-4 text-sm font-black text-rose-700 uppercase tracking-wide">Category Total</td>
                        <td class="px-6 py-4 text-right text-base font-black text-rose-700" id="footerTotal">GH₵ <?= number_format($item['amount'], 2) ?></td>
                        <td colspan="2" class="px-6 py-4 text-xs text-slate-400 font-medium italic">Auto-calculated from items above</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if (!$is_locked): ?>
        <!-- Add Item Form -->
        <div id="addForm" class="hidden bg-white rounded-2xl border-2 border-indigo-200 shadow-sm p-6 mb-6">
            <h4 class="text-sm font-black text-slate-700 mb-4 flex items-center gap-2">
                <i class="fas fa-plus-circle text-indigo-500"></i> New Budget Item
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-1">
                    <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-1 block">Item Description *</label>
                    <input type="text" id="new-source" placeholder="e.g. Electricity bills (3 months)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                </div>
                <div>
                    <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-1 block">Amount (GH₵) *</label>
                    <input type="number" step="0.01" id="new-amount" placeholder="0.00" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-black outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 text-right">
                </div>
                <div>
                    <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-1 block">Notes (optional)</label>
                    <input type="text" id="new-notes" placeholder="Any additional notes..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                </div>
            </div>
            <div class="flex gap-3 mt-4">
                <button onclick="submitAdd()" class="bg-indigo-600 text-white font-black text-xs uppercase tracking-widest px-6 py-3 rounded-xl hover:bg-indigo-700 transition-all shadow-sm">
                    <i class="fas fa-save mr-2"></i> Save Item
                </button>
                <button onclick="hideAddForm()" class="bg-slate-100 text-slate-500 font-black text-xs uppercase tracking-widest px-6 py-3 rounded-xl hover:bg-slate-200 transition-all">
                    Cancel
                </button>
            </div>
        </div>
        <?php endif; ?>

        <footer class="mt-12 py-8 border-t border-slate-200 text-[0.625rem] font-black text-slate-300 uppercase tracking-widest">
            Budget Builder &middot; <?= htmlspecialchars($semester) ?> &middot; <?= htmlspecialchars($display_year) ?>
        </footer>
    </main>

    <script>
        const SEMESTER      = <?= json_encode($semester) ?>;
        const ACADEMIC_YEAR = <?= json_encode($academic_year) ?>;
        const CATEGORY      = <?= json_encode($category) ?>;
        const API_URL       = 'api_budget_items.php';

        function showAlert(msg, type = 'error') {
            const box = document.getElementById('alertBox');
            box.textContent = msg;
            box.className = 'mb-6 p-4 rounded-2xl text-sm font-bold ' +
                (type === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200');
            box.classList.remove('hidden');
            setTimeout(() => box.classList.add('hidden'), 4000);
        }

        function updateTotals(newTotal, newCount) {
            const fmt = 'GH₵ ' + parseFloat(newTotal).toLocaleString('en-GH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('categoryTotal').textContent = fmt;
            document.getElementById('footerTotal').textContent = fmt;
            if (newCount !== undefined) document.getElementById('itemCount').textContent = newCount;
        }

        function getRowCount() {
            return document.querySelectorAll('#itemsBody tr[id^="row-"]').length;
        }

        // ── ADD ──
        function showAddForm() {
            document.getElementById('addForm').classList.remove('hidden');
            document.getElementById('addBtn').classList.add('hidden');
            document.getElementById('new-source').focus();
        }

        function hideAddForm() {
            document.getElementById('addForm').classList.add('hidden');
            document.getElementById('addBtn').classList.remove('hidden');
            document.getElementById('new-source').value = '';
            document.getElementById('new-amount').value = '';
            document.getElementById('new-notes').value = '';
        }

        function submitAdd() {
            const source = document.getElementById('new-source').value.trim();
            const amount = document.getElementById('new-amount').value.trim();
            const notes  = document.getElementById('new-notes').value.trim();

            if (!source || !amount || parseFloat(amount) <= 0) {
                showAlert('Please fill in Item Description and a positive Amount.'); return;
            }

            const fd = new FormData();
            fd.append('action', 'add');
            fd.append('semester', SEMESTER);
            fd.append('academic_year', ACADEMIC_YEAR);
            fd.append('category', CATEGORY);
            fd.append('source', source);
            fd.append('amount', amount);
            fd.append('notes', notes);

            fetch(API_URL, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (!d.success) { showAlert(d.message || 'Failed to add item.'); return; }
                    // Remove empty-row placeholder if present
                    const emptyRow = document.getElementById('emptyRow');
                    if (emptyRow) emptyRow.remove();
                    // Append new row
                    appendRow(d.id, source, parseFloat(amount), notes);
                    updateTotals(d.new_total, getRowCount());
                    hideAddForm();
                    showAlert('Item added successfully.', 'success');
                })
                .catch(() => showAlert('Request failed. Please try again.'));
        }

        function appendRow(id, source, amount, notes) {
            const tbody = document.getElementById('itemsBody');
            const tr = document.createElement('tr');
            tr.className = 'item-row border-b border-slate-50';
            tr.id = 'row-' + id;
            tr.innerHTML = buildRowHTML(id, source, amount, notes);
            tbody.appendChild(tr);
        }

        function buildRowHTML(id, source, amount, notes) {
            const fmt = parseFloat(amount).toLocaleString('en-GH', {minimumFractionDigits: 2});
            const esc = v => v.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            return `
                <td class="display-view px-6 py-4 text-sm font-semibold text-slate-800">${esc(source)}</td>
                <td class="display-view px-6 py-4 text-right font-black text-slate-900">${fmt}</td>
                <td class="display-view px-6 py-4 text-xs text-slate-400 font-medium">${esc(notes||'')}</td>
                <td class="display-view px-6 py-4 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <button onclick="editRow(${id})" class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white flex items-center justify-center transition-all" title="Edit">
                            <i class="fas fa-pencil text-xs"></i>
                        </button>
                        <button onclick="deleteRow(${id}, '${esc(source)}')" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white flex items-center justify-center transition-all" title="Remove">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                </td>
                <td class="edit-view px-4 py-3">
                    <input type="text" id="edit-source-${id}" value="${esc(source)}" placeholder="Item description" class="w-full border border-indigo-300 rounded-lg px-3 py-2 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500">
                </td>
                <td class="edit-view px-4 py-3">
                    <input type="number" step="0.01" id="edit-amount-${id}" value="${amount}" placeholder="0.00" class="w-full border border-indigo-300 rounded-lg px-3 py-2 text-sm font-black outline-none focus:ring-2 focus:ring-indigo-500 text-right">
                </td>
                <td class="edit-view px-4 py-3">
                    <input type="text" id="edit-notes-${id}" value="${esc(notes||'')}" placeholder="Notes (optional)" class="w-full border border-indigo-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </td>
                <td class="edit-view px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <button onclick="saveEdit(${id})" class="w-8 h-8 rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 flex items-center justify-center transition-all" title="Save">
                            <i class="fas fa-check text-xs"></i>
                        </button>
                        <button onclick="cancelEdit(${id})" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center transition-all" title="Cancel">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                </td>`;
        }

        // ── EDIT ──
        function editRow(id) {
            document.getElementById('row-' + id).classList.add('editing');
            const src = document.getElementById('edit-source-' + id);
            if (src) src.focus();
        }

        function cancelEdit(id) {
            document.getElementById('row-' + id).classList.remove('editing');
        }

        function saveEdit(id) {
            const source = document.getElementById('edit-source-' + id).value.trim();
            const amount = document.getElementById('edit-amount-' + id).value.trim();
            const notes  = document.getElementById('edit-notes-' + id).value.trim();

            if (!source || !amount || parseFloat(amount) <= 0) {
                showAlert('Item Description and a positive Amount are required.'); return;
            }

            const fd = new FormData();
            fd.append('action', 'update');
            fd.append('id', id);
            fd.append('source', source);
            fd.append('amount', amount);
            fd.append('notes', notes);

            fetch(API_URL, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (!d.success) { showAlert(d.message || 'Update failed.'); return; }
                    // Rebuild row HTML
                    document.getElementById('row-' + id).innerHTML = buildRowHTML(id, source, parseFloat(amount), notes);
                    updateTotals(d.new_total);
                    showAlert('Item updated.', 'success');
                })
                .catch(() => showAlert('Request failed. Please try again.'));
        }

        // ── DELETE ──
        function deleteRow(id, label) {
            if (!confirm('Remove "' + label + '" from this budget?\n\nThe category total will be updated.')) return;

            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);

            fetch(API_URL, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (!d.success) { showAlert(d.message || 'Delete failed.'); return; }
                    const row = document.getElementById('row-' + id);
                    if (row) row.remove();
                    updateTotals(d.new_total, getRowCount());
                    if (getRowCount() === 0) {
                        const tbody = document.getElementById('itemsBody');
                        tbody.innerHTML = '<tr id="emptyRow"><td colspan="4" class="px-6 py-12 text-center text-slate-400 text-sm font-medium italic">No items yet. Click "Add Item" to start building this budget category.</td></tr>';
                    }
                    showAlert('Item removed.', 'success');
                })
                .catch(() => showAlert('Request failed. Please try again.'));
        }
    </script>
</body>
</html>
