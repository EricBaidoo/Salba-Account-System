<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (!in_array(($_SESSION['role'] ?? ''), ['admin', 'data_entry'])) {
    header('Location: ../dashboard.php'); exit;
}

$current_year = getAcademicYear($conn);
$current_sem  = getCurrentSemester($conn);
$selected_class = $_GET['class'] ?? '';
$selected_category = $_GET['category'] ?? '';
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

// Categories (Levels)
$categories = [];
$cat_rs = $conn->query("SELECT DISTINCT Level FROM classes WHERE Level IS NOT NULL AND Level != '' ORDER BY Level ASC");
while ($cat = $cat_rs->fetch_assoc()) $categories[] = $cat['Level'];

// Academic years
$yr_rs = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
$all_years = [];
while ($y = $yr_rs->fetch_assoc()) $all_years[] = $y['academic_year'];
if (!in_array($selected_year, $all_years)) array_unshift($all_years, $selected_year);

// Fetch All Items in Catalog
$all_items = [];
$ir = $conn->query("SELECT * FROM stationery_items ORDER BY name ASC");
while ($i = $ir->fetch_assoc()) $all_items[] = $i;

// Fetch Assignments for Selected Class or Category
$assignments = [];
$category_classes = [];

if ($selected_class) {
    $sc = $conn->real_escape_string($selected_class);
    $sy = $conn->real_escape_string($selected_year);
    $ss = $conn->real_escape_string($selected_sem);
    $ar = $conn->query("
        SELECT * FROM stationery_assignments 
        WHERE class='$sc' AND academic_year='$sy' AND semester='$ss'
    ");
    while ($a = $ar->fetch_assoc()) $assignments[$a['item_id']] = $a;
} else if ($selected_category) {
    $scat = $conn->real_escape_string($selected_category);
    $sy = $conn->real_escape_string($selected_year);
    $ss = $conn->real_escape_string($selected_sem);
    
    // Find all classes in this category
    $cc_rs = $conn->query("SELECT name FROM classes WHERE Level='$scat'");
    while ($cc = $cc_rs->fetch_assoc()) $category_classes[] = $cc['name'];
    
    if (!empty($category_classes)) {
        $num_classes = count($category_classes);
        // Find items that are assigned to ALL classes in this category
        $classes_list = "'" . implode("','", array_map([$conn, 'real_escape_string'], $category_classes)) . "'";
        $ar = $conn->query("
            SELECT item_id, quantity, price, COUNT(*) as class_count 
            FROM stationery_assignments 
            WHERE class IN ($classes_list) AND academic_year='$sy' AND semester='$ss'
            GROUP BY item_id
            HAVING class_count = $num_classes
        ");
        while ($a = $ar->fetch_assoc()) $assignments[$a['item_id']] = $a;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stationery Manager | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-900">
<?php ($_SESSION['role'] ?? '') === 'data_entry'
    ? include '../../../includes/sidebar_data_entry.php'
    : include '../../../includes/sidebar_admin_modern.php'; ?>

<main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">

    <!-- Header -->
    <header class="mb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-indigo-600 transition-colors"><i class="fas fa-home"></i> Admin</a>
                <span>/</span>
                <span class="text-indigo-600">Stationery Manager</span>
            </div>
            <h1 class="text-3xl font-black text-slate-900">Stationery <span class="text-indigo-600">Manager</span></h1>
            <p class="text-slate-500 mt-1 text-sm">One-click assignments. Select a class to easily toggle items on or off.</p>
        </div>
    </header>

    <!-- Tab Navigation -->
    <nav class="no-print flex gap-1 mb-6 bg-white border border-slate-100 rounded-2xl p-1.5 shadow-sm w-fit flex-wrap">
        <span class="flex items-center gap-2 bg-indigo-600 text-white text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl">
            <i class="fas fa-list-check"></i> Manage Items
        </span>
        <a href="index.php<?= $selected_class ? "?class=".urlencode($selected_class)."&academic_year=".urlencode($selected_year)."&semester=".urlencode($selected_sem) : "" ?>" class="flex items-center gap-2 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors">
            <i class="fas fa-table-cells"></i> Tracker
        </a>
        <a href="settings.php" class="flex items-center gap-2 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors">
            <i class="fas fa-gear"></i> Settings
        </a>
    </nav>

    <div class="flex flex-col lg:flex-row gap-6">
        
        <!-- Left: Class List & Filters -->
        <div class="lg:w-1/4 flex flex-col gap-4">
            
            <div class="bg-white border border-slate-100 rounded-3xl p-5 shadow-sm">
                <form method="GET" class="flex flex-col gap-4">
                    <!-- Keep current class selected during reload -->
                    <input type="hidden" name="class" value="<?= htmlspecialchars($selected_class) ?>">
                    
                    <div>
                        <label class="text-[0.6rem] font-black text-slate-400 uppercase tracking-widest block mb-1">Academic Year</label>
                        <select name="academic_year" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-semibold outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                            <?php foreach ($all_years as $yr): ?>
                            <option value="<?= htmlspecialchars($yr) ?>" <?= $yr === $selected_year ? 'selected' : '' ?>><?= htmlspecialchars($yr) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[0.6rem] font-black text-slate-400 uppercase tracking-widest block mb-1">Semester</label>
                        <select name="semester" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-semibold outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                            <?php foreach ($semesters as $sm): ?>
                            <option value="<?= htmlspecialchars($sm) ?>" <?= $sm === $selected_sem ? 'selected' : '' ?>><?= htmlspecialchars($sm) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <div class="bg-white border border-slate-100 rounded-3xl p-3 shadow-sm flex-1 mb-4">
                <h3 class="text-[0.6rem] font-black text-slate-400 uppercase tracking-widest px-4 pt-3 pb-2">Select a Category</h3>
                <div class="flex flex-col gap-1 max-h-[30vh] overflow-y-auto custom-scrollbar pr-1">
                    <?php foreach ($categories as $cat): 
                        $active = ($cat === $selected_category);
                    ?>
                    <a href="?category=<?= urlencode($cat) ?>&academic_year=<?= urlencode($selected_year) ?>&semester=<?= urlencode($selected_sem) ?>" 
                       class="px-4 py-3 rounded-2xl text-sm font-bold transition-all <?= $active ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' ?>">
                        <i class="fas fa-layer-group opacity-50 mr-2"></i> <?= htmlspecialchars($cat) ?>
                        <?php if ($active): ?><i class="fas fa-chevron-right float-right mt-1 opacity-50"></i><?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-white border border-slate-100 rounded-3xl p-3 shadow-sm flex-1">
                <h3 class="text-[0.6rem] font-black text-slate-400 uppercase tracking-widest px-4 pt-3 pb-2">Select a Class</h3>
                <div class="flex flex-col gap-1 max-h-[40vh] overflow-y-auto custom-scrollbar pr-1">
                    <?php foreach ($classes as $cl): 
                        $active = ($cl === $selected_class);
                    ?>
                    <a href="?class=<?= urlencode($cl) ?>&academic_year=<?= urlencode($selected_year) ?>&semester=<?= urlencode($selected_sem) ?>" 
                       class="px-4 py-3 rounded-2xl text-sm font-bold transition-all <?= $active ? 'bg-emerald-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-50 hover:text-emerald-600' ?>">
                        <?= htmlspecialchars($cl) ?>
                        <?php if ($active): ?><i class="fas fa-chevron-right float-right mt-1 opacity-50"></i><?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Right: Items -->
        <div class="lg:w-3/4">
            
            <?php if (!$selected_class && !$selected_category): ?>
            <!-- Empty State -->
            <div class="bg-white border border-slate-100 rounded-3xl p-16 shadow-sm text-center flex flex-col items-center justify-center h-full min-h-[400px]">
                <div class="w-24 h-24 bg-indigo-50 rounded-full flex items-center justify-center text-indigo-500 text-4xl mb-4">
                    <i class="fas fa-hand-pointer"></i>
                </div>
                <h2 class="text-xl font-black text-slate-900 mb-2">Select a Category or Class</h2>
                <p class="text-slate-500 font-medium max-w-sm">Choose a category or class from the left sidebar to manage its stationery requirements instantly.</p>
            </div>
            <?php else: ?>
            
            <!-- Quick Add Bar -->
            <div class="bg-indigo-600 rounded-3xl p-2 pl-6 mb-6 shadow-md flex items-center justify-between">
                <div class="flex items-center gap-3 text-white flex-1">
                    <i class="fas fa-plus-circle opacity-50"></i>
                    <input type="text" id="quickAddName" placeholder="Type a new item (e.g. Paint Brush) and press Enter..." 
                           class="bg-transparent border-none outline-none text-white placeholder-indigo-300 font-semibold w-full">
                </div>
                <button onclick="quickAdd()" class="bg-white text-indigo-600 text-sm font-black px-6 py-3 rounded-2xl hover:bg-indigo-50 transition-colors shadow-sm">
                    Add & Assign
                </button>
            </div>

            <!-- Items Grid -->
            <div class="bg-white border border-slate-100 rounded-3xl shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <h2 class="text-lg font-black text-slate-900">Stationery for <span class="<?= $selected_category ? 'text-indigo-600' : 'text-emerald-600' ?>"><?= htmlspecialchars($selected_category ?: $selected_class) ?></span></h2>
                    <span class="<?= $selected_category ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700' ?> text-xs font-black px-3 py-1 rounded-full uppercase tracking-widest">
                        <?= count($assignments) ?> Assigned
                    </span>
                </div>
                
                <?php if ($selected_category): ?>
                <div class="bg-blue-50 px-6 py-3 border-b border-blue-100 flex gap-3 items-center">
                    <i class="fas fa-info-circle text-blue-500"></i>
                    <p class="text-xs text-blue-800 font-medium">You are managing the entire <strong><?= htmlspecialchars($selected_category) ?></strong> category. Any changes made here will apply to all <strong><?= count($category_classes) ?></strong> classes in this category instantly.</p>
                </div>
                <?php endif; ?>

                <div class="divide-y divide-slate-100">
                    <?php foreach ($all_items as $item): 
                        $is_assigned = isset($assignments[$item['id']]);
                        $assign_data = $is_assigned ? $assignments[$item['id']] : null;
                        $qty = $assign_data['quantity'] ?? '1';
                        $price = $assign_data['price'] ?? $item['default_price'];
                    ?>
                    <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50 transition-colors group">
                        
                        <div class="flex items-center gap-4 flex-1">
                            <!-- Toggle Switch -->
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" 
                                       id="toggle-<?= $item['id'] ?>"
                                       onchange="toggleItem(<?= $item['id'] ?>)" 
                                       <?= $is_assigned ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                            </label>
                            
                            <div>
                                <h3 class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($item['name']) ?></h3>
                                <?php if ($item['description']): ?>
                                <p class="text-[0.65rem] text-slate-400 font-medium"><?= htmlspecialchars($item['description']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Settings (Only shows distinctly when assigned, otherwise faded) -->
                        <div class="flex items-center gap-3 transition-opacity <?= $is_assigned ? 'opacity-100' : 'opacity-40' ?>">
                            <div class="flex items-center bg-slate-100 rounded-xl px-3 py-1.5 border border-slate-200 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                                <span class="text-[0.6rem] font-black text-slate-400 uppercase mr-2">Qty</span>
                                <input type="number" id="qty-<?= $item['id'] ?>" value="<?= $qty ?>" min="1" 
                                       onchange="if(document.getElementById('toggle-<?= $item['id'] ?>').checked) updateAssignment(<?= $item['id'] ?>)"
                                       class="bg-transparent border-none outline-none w-12 text-sm font-bold text-slate-700 text-center">
                            </div>
                            <div class="flex items-center bg-slate-100 rounded-xl px-3 py-1.5 border border-slate-200 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                                <span class="text-[0.6rem] font-black text-slate-400 uppercase mr-2">GH₵</span>
                                <input type="number" id="price-<?= $item['id'] ?>" value="<?= $price ?>" step="0.01" min="0" 
                                       onchange="if(document.getElementById('toggle-<?= $item['id'] ?>').checked) updateAssignment(<?= $item['id'] ?>)"
                                       class="bg-transparent border-none outline-none w-16 text-sm font-bold text-slate-700 text-center">
                            </div>
                            <!-- Delete from catalog button -->
                            <button onclick="deleteCatalogItem(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>')" class="text-slate-300 hover:text-rose-500 hover:bg-rose-50 w-8 h-8 rounded-lg flex items-center justify-center transition-colors" title="Delete entirely from system">
                                <i class="fas fa-trash-alt text-xs"></i>
                            </button>
                        </div>
                        
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php endif; ?>

        </div>
    </div>

</main>

<div id="toast" class="fixed bottom-6 right-6 z-50 hidden text-sm font-semibold px-5 py-3 rounded-2xl shadow-xl flex items-center gap-3"></div>

<script>
const API = 'api_stationery.php';
const currentClass = <?= json_encode($selected_class) ?>;
const currentCategory = <?= json_encode($selected_category) ?>;
const categoryClasses = <?= json_encode($category_classes) ?>;
const currentYear = <?= json_encode($selected_year) ?>;
const currentSemester = <?= json_encode($selected_sem) ?>;

function showToast(msg, ok = true) {
    const t = document.getElementById('toast');
    t.className = 'fixed bottom-6 right-6 z-50 flex items-center gap-3 text-sm font-semibold px-5 py-3 rounded-2xl shadow-xl ' + (ok ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white');
    t.innerHTML = `<i class="fas ${ok ? 'fa-circle-check' : 'fa-circle-xmark'}"></i>${msg}`;
    t.classList.remove('hidden');
    setTimeout(() => t.classList.add('hidden'), 2500);
}

async function apiPost(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    return (await fetch(API, { method: 'POST', body: fd })).json();
}

async function toggleItem(id) {
    const isChecked = document.getElementById('toggle-' + id).checked;
    const qty = document.getElementById('qty-' + id).value || 1;
    const price = document.getElementById('price-' + id).value || 0;
    
    if (isChecked) {
        // Assign
        let data = {
            action: currentCategory ? 'bulk_assign_item' : 'assign_item',
            item_id: id,
            academic_year: currentYear,
            semester: currentSemester,
            quantity: qty,
            price: price
        };
        
        if (currentCategory) {
            categoryClasses.forEach((c, i) => data[`classes[${i}]`] = c);
        } else {
            data.class = currentClass;
        }

        const res = await apiPost(data);
        if (res.success) {
            showToast(currentCategory ? 'Assigned to category.' : 'Assigned.');
        } else {
            document.getElementById('toggle-' + id).checked = false; // revert
            showToast(res.message || 'Failed to assign', false);
        }
    } else {
        // Unassign via specialized endpoint (we need one that removes by class/year/semester)
        let data = {
            action: currentCategory ? 'bulk_unassign_item' : 'unassign_item_by_class',
            item_id: id,
            academic_year: currentYear,
            semester: currentSemester
        };
        
        if (currentCategory) {
            categoryClasses.forEach((c, i) => data[`classes[${i}]`] = c);
        } else {
            data.class = currentClass;
        }

        const res = await apiPost(data);
        if (res.success) {
            showToast(currentCategory ? 'Unassigned from category.' : 'Unassigned.');
        } else {
            document.getElementById('toggle-' + id).checked = true; // revert
            showToast('Failed to unassign', false);
        }
    }
}

async function updateAssignment(id) {
    const qty = document.getElementById('qty-' + id).value || 1;
    const price = document.getElementById('price-' + id).value || 0;
    
    let data = {
        action: currentCategory ? 'bulk_assign_item' : 'assign_item',
        item_id: id,
        academic_year: currentYear,
        semester: currentSemester,
        quantity: qty,
        price: price
    };
    
    if (currentCategory) {
        categoryClasses.forEach((c, i) => data[`classes[${i}]`] = c);
    } else {
        data.class = currentClass;
    }

    // We can just call assign_item again, it uses ON DUPLICATE KEY UPDATE in the backend
    const res = await apiPost(data);
    
    if (res.success) {
        showToast('Updated.');
    } else {
        showToast('Failed to update.', false);
    }
}

async function quickAdd() {
    const name = document.getElementById('quickAddName').value.trim();
    if (!name) return;
    
    const res = await apiPost({
        action: 'quick_add_and_assign',
        name: name,
        class: currentClass,
        academic_year: currentYear,
        semester: currentSemester
    });
    
    if (res.success) {
        showToast('Added and assigned!');
        setTimeout(() => location.reload(), 500);
    } else {
        showToast(res.message || 'Failed.', false);
    }
}

document.getElementById('quickAddName')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') quickAdd();
});

async function deleteCatalogItem(id, name) {
    if (!confirm('Are you sure you want to permanently delete "' + name + '" from the system?\n\nThis will remove it from ALL classes.')) return;
    const res = await apiPost({ action: 'delete_item', id: id });
    if (res.success) {
        showToast('Item deleted.');
        setTimeout(() => location.reload(), 500);
    } else {
        showToast(res.message || 'Error.', false);
    }
}
</script>
</body>
</html>
