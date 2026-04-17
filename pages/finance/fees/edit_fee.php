<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: ../../../includes/login.php');
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
<body class="text-slate-900 leading-relaxed">
    <?php include '../../../includes/sidebar_admin.php'; ?>

    <main class="ml-72 p-10 min-h-screen">
        <!-- Header -->
        <header class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-indigo-600"></span>
                    Registry Node
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Modify <span class="text-indigo-600">Fee Logic</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Re-configuring parameters for institutional billing entities.</p>
            </div>
            <a href="view_fees.php" class="bg-white border border-slate-200 text-slate-400 font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:text-slate-600 hover:bg-slate-50 transition-all leading-none">
                <i class="fas fa-arrow-left mr-2"></i> Return to List
            </a>
        </header>

        <div class="max-w-4xl">
            <?php if ($success): ?>
                <div class="mb-10 bg-emerald-50 border border-emerald-100 p-6 rounded-[2rem] flex items-center gap-4 text-emerald-800 animate-in fade-in slide-in-from-top-4 duration-500">
                    <div class="w-10 h-10 bg-emerald-500 text-white rounded-full flex items-center justify-center shadow-lg shadow-emerald-500/20">
                        <i class="fas fa-check"></i>
                    </div>
                    <p class="font-bold text-sm tracking-tight"><?= $success ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-10">
                <!-- Identity Hub -->
                <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm relative overflow-hidden group">
                    <div class="absolute top-0 right-0 p-8 opacity-5 group-hover:scale-110 transition-transform duration-700">
                        <i class="fas fa-fingerprint text-8xl"></i>
                    </div>
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-10">Fee Identity & Classification</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                        <div class="space-y-8">
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Institutional Name</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($fee['name']) ?>" required class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-black text-slate-900 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all">
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Functional Logic Type</label>
                                <select name="fee_type" id="feeTypeSelect" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-black text-slate-900 outline-none focus:ring-4 focus:ring-indigo-500/10 appearance-none">
                                    <option value="fixed" <?= $fee['fee_type']==='fixed'?'selected':'' ?>>Standard Fixed Rate</option>
                                    <option value="class_based" <?= $fee['fee_type']==='class_based'?'selected':'' ?>>Class-Based Tiering</option>
                                    <option value="category" <?= $fee['fee_type']==='category'?'selected':'' ?>>Generalized Category Based</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Administrative Notes</label>
                            <textarea name="description" rows="5" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-medium text-slate-600 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all leading-loose"><?= htmlspecialchars($fee['description']) ?></textarea>
                        </div>
                    </div>
                </section>

                <!-- Amount Thresholds Hub -->
                <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-10">Fiscal Allocation Matrix</h3>

                    <div id="fixedAmountDiv" class="fee-section">
                        <div class="bg-slate-50 p-10 rounded-[2rem] border border-slate-100">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-4 block text-center">Standard Institutional Amount (GHS)</label>
                            <input type="number" step="0.01" name="fixed_amount" value="<?= $fee['amount'] ?>" class="w-full text-center bg-transparent text-5xl font-black text-slate-900 outline-none focus:text-indigo-600 transition-colors" placeholder="0.00">
                        </div>
                    </div>

                    <div id="classAmountsDiv" class="fee-section hidden grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach($classes as $class): ?>
                            <div class="bg-slate-50 p-6 rounded-3xl border border-slate-100 flex items-center justify-between group">
                                <span class="text-xs font-black text-slate-500 uppercase tracking-widest"><?= $class ?></span>
                                <div class="flex items-center gap-3">
                                    <span class="text-[10px] font-black text-slate-300">GHS</span>
                                    <input type="number" step="0.01" name="class_amounts[<?= $class ?>]" value="<?= $amounts['class'][$class] ?? '' ?>" class="w-24 bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-black text-right text-slate-900 outline-none group-hover:border-indigo-400 transition-all">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="categoryAmountsDiv" class="fee-section hidden grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach($fee_categories as $catId=>$catLabel): ?>
                            <div class="bg-indigo-50/30 p-6 rounded-3xl border border-indigo-100/50 flex items-center justify-between group">
                                <span class="text-xs font-black text-indigo-600 uppercase tracking-widest"><?= htmlspecialchars($catLabel) ?></span>
                                <div class="flex items-center gap-3">
                                    <span class="text-[10px] font-black text-indigo-300">GHS</span>
                                    <input type="number" step="0.01" name="category_amounts[<?= $catId ?>]" value="<?= $amounts['category'][(string)$catId] ?? '' ?>" class="w-24 bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-black text-right text-slate-900 outline-none group-hover:border-indigo-400 transition-all">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Action Bar -->
                <div class="bg-slate-900 rounded-[2.5rem] p-10 text-white border border-slate-800 shadow-2xl flex flex-wrap items-center justify-between gap-6">
                    <div class="flex items-center gap-5">
                        <div class="w-14 h-14 bg-emerald-500 rounded-2xl flex items-center justify-center text-white text-xl shadow-lg shadow-emerald-500/20">
                            <i class="fas fa-shield-check"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-[0.2em] text-emerald-400">Node Sync Authorization</h4>
                            <p class="text-slate-500 text-[10px] font-bold mt-1 uppercase leading-none">Commits architectural changes to core receivers.</p>
                        </div>
                    </div>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white font-black text-[10px] uppercase tracking-widest px-10 py-5 rounded-2xl transition-all shadow-xl shadow-emerald-600/20 active:scale-95 leading-none h-fit">
                        Sync Fee Architecture
                    </button>
                </div>
            </form>
        </div>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">
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
