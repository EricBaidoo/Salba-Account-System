<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_scholarship') {
        $name = $conn->real_escape_string($_POST['name']);
        $type = $conn->real_escape_string($_POST['discount_type']);
        $val = floatval($_POST['discount_value']);
        $fees_json = $conn->real_escape_string(json_encode($_POST['applies_to_fees'] ?? []));
        
        $conn->query("INSERT INTO scholarships (name, applies_to_fees, discount_type, discount_value) VALUES ('$name', '$fees_json', '$type', $val)");
        $_SESSION['success_msg'] = "Scholarship/Waiver created successfully.";
    } elseif ($_POST['action'] === 'delete_scholarship') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM scholarships WHERE id = $id");
        $_SESSION['success_msg'] = "Scholarship deleted successfully.";
    } elseif ($_POST['action'] === 'toggle_status') {
        $id = (int)$_POST['id'];
        $conn->query("UPDATE scholarships SET status = IF(status = 'active', 'inactive', 'active') WHERE id = $id");
        $_SESSION['success_msg'] = "Status updated.";
    }
    header("Location: index.php");
    exit;
}

$scholarships = $conn->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM student_scholarships ss WHERE ss.scholarship_id = s.id AND ss.status='active') as active_students 
    FROM scholarships s 
    ORDER BY s.name ASC
");

$fees_res = $conn->query("SELECT id, name FROM fees WHERE name != 'Waivers & Scholarships' ORDER BY name");
$fees_map = [];
$fees_list = [];
while($f = $fees_res->fetch_assoc()) {
    $fees_map[$f['id']] = $f['name'];
    $fees_list[] = $f;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Waivers & Scholarships</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <?php include '../../../includes/sidebar.php'; ?>
    <main class="admin-main-content lg:ml-72 min-h-screen">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-indigo-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <span class="text-slate-600">Waivers & Scholarships</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-3">
                        <i class="fas fa-hand-holding-dollar text-blue-600"></i> Fee Waivers & Scholarships
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Manage financial aid, staff ward discounts, and academic scholarships.</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="assign.php" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all shadow-sm"><i class="fas fa-user-check mr-2"></i> Assign</a>
                    <button onclick="document.getElementById('addModal').classList.remove('hidden'); document.getElementById('addModal').classList.add('flex');" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-all shadow-sm"><i class="fas fa-plus mr-2"></i> New</button>
                </div>
            </div>
        </div>

        <div class="p-6">
            <?php if(isset($_SESSION['success_msg'])): ?>
            <div class="mb-6 bg-emerald-50 text-emerald-700 px-4 py-3 rounded-lg text-sm flex gap-3 items-center border border-emerald-100"><i class="fas fa-check-circle"></i> <?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
            <?php endif; ?>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            <th class="px-6 py-4">Scholarship Name</th>
                            <th class="px-6 py-4">Applies To</th>
                            <th class="px-6 py-4 text-center">Discount Rules</th>
                            <th class="px-6 py-4 text-center">Active Recipients</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if($scholarships->num_rows > 0): while($row = $scholarships->fetch_assoc()): 
                            $fee_ids = json_decode($row['applies_to_fees'] ?? '[]', true) ?: [];
                            $fee_names = [];
                            foreach($fee_ids as $fid) {
                                if(isset($fees_map[$fid])) $fee_names[] = $fees_map[$fid];
                            }
                            $fee_label = empty($fee_names) ? 'All Billed Fees' : implode(', ', $fee_names);
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors group border-b border-slate-100 last:border-0">
                            <td class="px-6 py-4 font-semibold text-slate-900 text-sm"><?= htmlspecialchars($row['name']) ?></td>
                            <td class="px-6 py-4 text-xs text-slate-500">
                                <div class="max-w-[200px] truncate" title="<?= htmlspecialchars($fee_label) ?>"><?= htmlspecialchars($fee_label) ?></div>
                            </td>
                            <td class="px-6 py-4 text-center text-sm font-medium">
                                <?= $row['discount_type'] === 'percentage' ? "<span class='text-emerald-600'>{$row['discount_value']}% OFF</span>" : "<span class='text-blue-600'>GHS {$row['discount_value']} OFF</span>" ?>
                            </td>
                            <td class="px-6 py-4 text-center font-semibold text-slate-700 text-sm"><?= $row['active_students'] ?></td>
                            <td class="px-6 py-4 text-center">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button class="px-2 py-1 rounded text-[10px] font-semibold <?= $row['status']=='active'?'bg-emerald-50 text-emerald-700 border border-emerald-200':'bg-rose-50 text-rose-700 border border-rose-200' ?>"><?= strtoupper($row['status']) ?></button>
                                </form>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this scholarship?')">
                                    <input type="hidden" name="action" value="delete_scholarship">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button class="text-rose-400 hover:text-rose-600 p-2"><i class="fas fa-trash-can"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500 font-medium">No scholarships defined yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal -->
    <div id="addModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <form method="POST" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <input type="hidden" name="action" value="add_scholarship">
            <h3 class="text-lg font-semibold text-slate-900 mb-5">Create Scholarship/Waiver</h3>
            
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-2">Name</label>
                <input type="text" name="name" required placeholder="e.g. Staff Ward Discount" class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-2">Applies To Fees (Select Multiple)</label>
                <div class="border border-slate-300 rounded-lg p-3 max-h-40 overflow-y-auto space-y-1 bg-slate-50/50">
                    <?php foreach($fees_list as $f): ?>
                        <label class="flex items-center gap-2 cursor-pointer p-1.5 hover:bg-slate-100 rounded">
                            <input type="checkbox" name="applies_to_fees[]" value="<?= $f['id'] ?>" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="text-sm text-slate-700"><?= htmlspecialchars($f['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="text-[10px] text-slate-500 mt-2 italic">*If none selected, it assumes all billed fees.</p>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-2">Type</label>
                    <select name="discount_type" class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (GHS)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-2">Value</label>
                    <input type="number" step="0.01" name="discount_value" required class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
            </div>
            
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden'); document.getElementById('addModal').classList.remove('flex');" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 shadow-sm">Save</button>
            </div>
        </form>
    </div>
</body>
</html>
