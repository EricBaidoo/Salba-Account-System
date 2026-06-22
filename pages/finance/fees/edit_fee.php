<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
require_finance_access();

$fee_id = isset($_GET['fee_id']) ? intval($_GET['fee_id']) : 0;
if (!$fee_id) { die('Invalid fee access node.'); }

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $fee_type = $_POST['fee_type'] ?? '';

    if ($name && $fee_type) {
        $stmt = $conn->prepare("UPDATE fees SET name=?, description=?, fee_type=? WHERE id=?");
        $stmt->bind_param('sssi', $name, $description, $fee_type, $fee_id);
        $stmt->execute();
        $stmt->close();

        $conn->query("DELETE FROM fee_amounts WHERE fee_id = $fee_id");

        if ($fee_type === 'fixed') {
            $fixed_amount = floatval($_POST['fixed_amount'] ?? 0);
            $stmt = $conn->prepare("INSERT INTO fee_amounts (fee_id, amount) VALUES (?, ?)");
            $stmt->bind_param('id', $fee_id, $fixed_amount);
            $stmt->execute();
            $stmt->close();
            $conn->query("UPDATE fees SET amount = $fixed_amount WHERE id = $fee_id");
        } elseif ($fee_type === 'class_based' && isset($_POST['class_amounts'])) {
            foreach ($_POST['class_amounts'] as $class => $amount) {
                if ($amount !== '' && is_numeric($amount)) {
                    $stmt = $conn->prepare("INSERT INTO fee_amounts (fee_id, class_name, amount) VALUES (?, ?, ?)");
                    $stmt->bind_param('isd', $fee_id, $class, $amount);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            $conn->query("UPDATE fees SET amount = 0 WHERE id = $fee_id");
        } elseif ($fee_type === 'category' && isset($_POST['category_amounts'])) {
            foreach ($_POST['category_amounts'] as $catId => $amount) {
                if ($amount !== '' && is_numeric($amount)) {
                    $stmt = $conn->prepare("INSERT INTO fee_amounts (fee_id, category, amount) VALUES (?, ?, ?)");
                    $stmt->bind_param('isd', $fee_id, $catId, $amount);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            $conn->query("UPDATE fees SET amount = 0 WHERE id = $fee_id");
        }
        $success = "Fee synchronization complete. Metrics updated.";
    } else {
        $error = "Institutional name and classification type are required.";
    }
}

$fee = $conn->query("SELECT * FROM fees WHERE id = $fee_id")->fetch_assoc();
if (!$fee) { die('Fee node not found.'); }

$amounts = [];
$res = $conn->query("SELECT * FROM fee_amounts WHERE fee_id = $fee_id");
while ($row = $res->fetch_assoc()) {
    if ($row['class_name']) $amounts['class'][(string)$row['class_name']] = $row['amount'];
    elseif ($row['category']) $amounts['category'][(string)$row['category']] = $row['amount'];
}

$classes = [];
$class_res = $conn->query("SELECT name FROM classes ORDER BY id ASC");
while ($row = $class_res->fetch_assoc()) { $classes[] = $row['name']; }
include '../../../includes/fee_categories.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Fee Configuration | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 min-h-screen pb-12">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <a href="view_fees.php" class="hover:text-blue-600 transition-colors">Fee Management</a>
                <span>/</span>
                <span class="text-blue-600">Modify Fee</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-pen text-indigo-600"></i> Modify Fee Configuration
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Re-configuring parameters for institutional billing entities.</p>
                </div>
                <a href="view_fees.php" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all flex items-center gap-2">
                    <i class="fas fa-arrow-left text-slate-400"></i> Return to List
                </a>
            </div>
        </div>

        <div class="px-6 max-w-4xl">
            <?php if ($success): ?>
                <div class="mb-10 bg-emerald-50 border border-emerald-100 p-6 rounded-[2rem] flex items-center gap-4 text-emerald-800 animate-in fade-in slide-in-from-top-4 duration-500">
                    <div class="w-10 h-10 bg-emerald-500 text-white rounded-full flex items-center justify-center shadow-lg shadow-emerald-500/20">
                        <i class="fas fa-check"></i>
                    </div>
                    <p class="font-bold text-sm tracking-tight"><?= $success ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Identity Hub -->
                <section class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2">
                        <i class="fas fa-fingerprint text-slate-400"></i> Fee Identity & Classification
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-6">
                            <div>
                                <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Institutional Name</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($fee['name']) ?>" required class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2 text-sm font-medium text-slate-900 outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Functional Logic Type</label>
                                <select name="fee_type" id="feeTypeSelect" class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2 text-sm font-medium text-slate-900 outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 appearance-none transition-all">
                                    <option value="fixed" <?= $fee['fee_type']==='fixed'?'selected':'' ?>>Standard Fixed Rate</option>
                                    <option value="class_based" <?= $fee['fee_type']==='class_based'?'selected':'' ?>>Class-Based Tiering</option>
                                    <option value="category" <?= $fee['fee_type']==='category'?'selected':'' ?>>Generalized Category Based</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Administrative Notes</label>
                            <textarea name="description" rows="5" class="w-full bg-white border border-slate-300 rounded-lg px-4 py-3 text-sm font-medium text-slate-900 outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all leading-loose"><?= htmlspecialchars($fee['description']) ?></textarea>
                        </div>
                    </div>
                </section>

                <!-- Amount Thresholds Hub -->
                <section class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2">
                        <i class="fas fa-coins text-slate-400"></i> Fiscal Allocation Matrix
                    </h3>

                    <div id="fixedAmountDiv" class="fee-section">
                        <div class="bg-slate-50 p-6 rounded-lg border border-slate-200">
                            <label class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-4 block text-center">Standard Institutional Amount (GHS)</label>
                            <input type="number" step="0.01" name="fixed_amount" value="<?= $fee['amount'] ?>" class="w-full text-center bg-white border border-slate-300 py-3 rounded-lg text-3xl font-bold text-slate-900 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all" placeholder="0.00">
                        </div>
                    </div>

                    <div id="classAmountsDiv" class="fee-section hidden grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                        <?php foreach($classes as $class): ?>
                            <div class="bg-slate-50 p-4 rounded-lg border border-slate-200 flex flex-col gap-2">
                                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider"><?= $class ?></span>
                                <div class="relative">
                                    <input type="number" step="0.01" name="class_amounts[<?= $class ?>]" value="<?= $amounts['class'][$class] ?? '' ?>" class="w-full bg-white border border-slate-300 rounded-md pl-8 pr-3 py-2 text-sm font-semibold text-slate-900 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-semibold text-sm">₵</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="categoryAmountsDiv" class="fee-section hidden grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach($fee_categories as $catId=>$catLabel): ?>
                            <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-100 flex items-center justify-between gap-4">
                                <span class="text-xs font-bold text-indigo-800 uppercase tracking-wider"><?= htmlspecialchars($catLabel) ?></span>
                                <div class="relative w-32">
                                    <input type="number" step="0.01" name="category_amounts[<?= $catId ?>]" value="<?= $amounts['category'][(string)$catId] ?? '' ?>" class="w-full bg-white border border-slate-300 rounded-md pl-8 pr-3 py-2 text-sm font-semibold text-slate-900 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-semibold text-sm">₵</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Action Bar -->
                <div class="bg-slate-50 rounded-xl p-6 border border-slate-200 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center text-emerald-500 text-xl border border-slate-200 shadow-sm">
                            <i class="fas fa-shield-check"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold uppercase tracking-wider text-slate-800">Node Sync Authorization</h4>
                            <p class="text-slate-500 text-xs font-medium mt-1">Commits architectural changes to core receivers.</p>
                        </div>
                    </div>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm px-6 py-2.5 rounded-lg transition-all shadow-sm flex items-center gap-2">
                        <i class="fas fa-sync-alt"></i> Sync Fee Architecture
                    </button>
                </div>
            </form>
        </div>

        <footer class="mt-12 mx-6 py-6 border-t border-slate-200 text-xs font-semibold text-slate-400 uppercase tracking-wider">
            Salba Montessori &middot; Multi-Tapered Billing Engine &middot; v9.5.0
        </footer>
    </main>

    <script>
        function updateAmountFields() {
            const type = document.getElementById('feeTypeSelect').value;
            const sections = {
                'fixed': document.getElementById('fixedAmountDiv'),
                'class_based': document.getElementById('classAmountsDiv'),
                'category': document.getElementById('categoryAmountsDiv')
            };

            Object.keys(sections).forEach(key => {
                if (key === type) {
                    sections[key].classList.remove('hidden');
                    // Add animation if possible or just show
                } else {
                    sections[key].classList.add('hidden');
                }
            });
        }

        document.getElementById('feeTypeSelect').addEventListener('change', updateAmountFields);
        updateAmountFields(); // Initialize
    </script>
</body>
</html>
