<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
require_finance_access();
// Load category id=>name map
include_once '../../../includes/fee_category_map.php';

// Get fees with their associated amounts for class-based and category fees
$fees_query = "
    SELECT f.id, f.name, f.amount, f.fee_type, f.description, f.created_at,
           GROUP_CONCAT(
               CASE 
                   WHEN fa.class_name IS NOT NULL THEN CONCAT(fa.class_name, ':GHS ', FORMAT(fa.amount, 2))
                   WHEN fa.category IS NOT NULL THEN CONCAT(fa.category, ':GHS ', FORMAT(fa.amount, 2))
               END
               ORDER BY fa.amount
               SEPARATOR ' | '
           ) as amount_details
    FROM fees f
    LEFT JOIN fee_amounts fa ON f.id = fa.fee_id
    GROUP BY f.id, f.name, f.amount, f.fee_type, f.description, f.created_at
    ORDER BY f.id DESC";

$result = $conn->query($fees_query);

// Get summary statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_fees,
        SUM(CASE WHEN fee_type = 'fixed' THEN 1 ELSE 0 END) as fixed_fees,
        SUM(CASE WHEN fee_type = 'class_based' THEN 1 ELSE 0 END) as class_based_fees,
        SUM(CASE WHEN fee_type = 'category' THEN 1 ELSE 0 END) as category_fees
    FROM fees
")->fetch_assoc();

$ct = getCurrentSemester($conn);
$cy = getAcademicYear($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Management | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#F8FAFC] text-slate-900">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="ml-72 p-10 min-h-screen">
        <!-- Header Section -->
        <header class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-indigo-600"></span>
                    Fee Infrastructure
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Structured <span class="text-indigo-600">Receivables</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Define fee categories, managed tiered pricing, and oversee assignment logic.</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="add_fee_form.php" class="bg-indigo-600 text-white font-black text-xs uppercase tracking-widest px-6 py-4 rounded-2xl shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 hover:scale-[1.02] transition-all active:scale-95 leading-none">
                    <i class="fas fa-plus mr-2"></i> Create Fee
                </a>
                <a href="assign_fee_form.php" class="bg-white text-indigo-600 border border-indigo-100 font-black text-xs uppercase tracking-widest px-6 py-4 rounded-2xl hover:bg-indigo-50 hover:border-indigo-200 transition-all leading-none">
                    <i class="fas fa-user-plus mr-2"></i> Assign Fee
                </a>
            </div>
        </header>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center gap-5">
                <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-xl">
                    <i class="fas fa-tags"></i>
                </div>
                <div>
                    <h4 class="text-2xl font-black text-slate-900 leading-none mb-1"><?= $stats['total_fees'] ?></h4>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Fees</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center gap-5">
                <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl">
                    <i class="fas fa-lock"></i>
                </div>
                <div>
                    <h4 class="text-2xl font-black text-slate-900 leading-none mb-1"><?= $stats['fixed_fees'] ?></h4>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Fixed Rates</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center gap-5">
                <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-xl">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div>
                    <h4 class="text-2xl font-black text-slate-900 leading-none mb-1"><?= $stats['class_based_fees'] ?></h4>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Tiered (Class)</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center gap-5">
                <div class="w-14 h-14 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-xl">
                    <i class="fas fa-shapes"></i>
                </div>
                <div>
                    <h4 class="text-2xl font-black text-slate-900 leading-none mb-1"><?= $stats['category_fees'] ?></h4>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Generalized</p>
                </div>
            </div>
        </div>

        <!-- Fees Display -->
        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-3">
            Defined Fee Categories <span class="flex-1 h-[1px] bg-slate-100"></span>
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-8">
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 overflow-hidden flex flex-col group">
                        <!-- Fee Top Header -->
                        <div class="p-8 pb-0">
                            <div class="flex justify-between items-start mb-6">
                                <div class="w-12 h-12 <?= $row['fee_type'] === 'fixed' ? 'bg-emerald-50 text-emerald-600' : ($row['fee_type'] === 'class_based' ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-600') ?> rounded-2xl flex items-center justify-center text-xl transition-all duration-500">
                                    <i class="fas <?= $row['fee_type'] === 'fixed' ? 'fa-tag' : ($row['fee_type'] === 'class_based' ? 'fa-layer-group' : 'fa-shapes') ?>"></i>
                                </div>
                                <div class="bg-slate-50 text-slate-400 text-[10px] font-black px-3 py-1 rounded-full border border-slate-100 uppercase tracking-widest">
                                    ID: <?= $row['id'] ?>
                                </div>
                            </div>
                            <h4 class="text-xl font-black text-slate-900 mb-2 truncate" title="<?= htmlspecialchars($row['name']) ?>">
                                <?= htmlspecialchars($row['name']) ?>
                            </h4>
                            <p class="text-slate-400 text-xs font-semibold uppercase tracking-widest mb-4">
                                <?php
                                    switch($row['fee_type']) {
                                        case 'fixed': echo 'Fixed Institutional Rate'; break;
                                        case 'class_based': echo 'Class-Based Tiered Rate'; break;
                                        case 'category': echo 'Category Generalized Rate'; break;
                                    }
                                ?>
                            </p>
                        </div>

                        <!-- Amount Context -->
                        <div class="px-8 py-6 bg-slate-50">
                            <?php if ($row['fee_type'] === 'fixed'): ?>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Standard Amount</span>
                                    <span class="text-3xl font-black text-slate-900">GHS <?= number_format($row['amount'], 2) ?></span>
                                </div>
                            <?php else: ?>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Tiered Adjustments</span>
                                    <div class="max-h-24 overflow-y-auto pr-2 custom-scrollbar">
                                        <p class="text-[11px] font-bold text-slate-600 leading-relaxed">
                                            <?php
                                                $details = explode(' | ', $row['amount_details'] ?? '');
                                                $out = [];
                                                foreach ($details as $d) {
                                                    if (preg_match('/^([0-9]+):GHS/', $d, $m) && isset($category_map[$m[1]])) {
                                                        $out[] = str_replace($m[1], $category_map[$m[1]], $d);
                                                    } else {
                                                        $out[] = $d;
                                                    }
                                                }
                                                echo htmlspecialchars(implode(' • ', $out));
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Description & Bottom Actions -->
                        <div class="p-8 pt-6 flex-1 flex flex-col">
                            <?php if (!empty($row['description'])): ?>
                                <p class="text-slate-500 text-sm font-medium mb-8 leading-relaxed line-clamp-2">
                                    <?= htmlspecialchars($row['description']) ?>
                                </p>
                            <?php else: ?>
                                <div class="flex-1"></div>
                            <?php endif; ?>

                            <div class="flex items-center justify-between border-t border-slate-100 pt-6 mt-auto">
                                <div class="flex gap-2">
                                    <a href="edit_fee.php?fee_id=<?= $row['id'] ?>" class="w-10 h-10 bg-slate-50 text-slate-400 hover:bg-slate-900 hover:text-white rounded-xl flex items-center justify-center transition-all duration-300">
                                        <i class="fas fa-pen text-xs"></i>
                                    </a>
                                    <button onclick="deleteFee(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>')" class="w-10 h-10 bg-slate-50 text-slate-400 hover:bg-rose-600 hover:text-white rounded-xl flex items-center justify-center transition-all duration-300">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                                <a href="assign_fee_form.php?fee_id=<?= $row['id'] ?>" class="bg-slate-900 text-white font-black text-[10px] uppercase tracking-widest px-6 py-3 rounded-xl hover:shadow-lg hover:shadow-slate-900/20 transition-all flex items-center gap-2">
                                    Execute Assignment <i class="fas fa-arrow-right text-[8px]"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full py-20 bg-white rounded-[3rem] border border-slate-100 shadow-sm flex flex-col items-center justify-center text-center">
                    <div class="w-24 h-24 bg-slate-50 rounded-[2rem] flex items-center justify-center text-slate-200 text-4xl mb-6">
                        <i class="fas fa-money-bill-transfer"></i>
                    </div>
                    <h4 class="text-2xl font-black text-slate-900 mb-2">No Institutional Fees defined</h4>
                    <p class="text-slate-500 font-medium mb-8 max-w-sm px-6">Your fee architecture is currently empty. Define categories to begin student billing.</p>
                    <a href="add_fee_form.php" class="bg-indigo-600 text-white font-black text-xs uppercase tracking-widest px-10 py-5 rounded-2xl shadow-xl shadow-indigo-600/20 hover:bg-indigo-700 transition-all active:scale-95 leading-none">
                        Create Initial Fee Structure
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer Nav -->
        <div class="mt-20 py-10 border-t border-slate-200 flex justify-between items-center text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">
            <span>Salba Montessori &middot; Fee Architecture &middot; v9.5.0</span>
            <div class="flex gap-6">
                <a href="view_assigned_fees.php" class="hover:text-indigo-600 transition-colors">Active Assignments</a>
                <a href="../payments/view_payments.php" class="hover:text-indigo-600 transition-colors">Global Receipts</a>
            </div>
        </div>
    </main>

    <script>
        function deleteFee(feeId, feeName) {
            if (confirm('Are you sure you want to delete the fee "' + feeName + '"?\n\nThis action cannot be undone and may affect existing assignments.')) {
                window.location.href = 'delete_fee.php?id=' + feeId;
            }
        }
    </script>
</body>
</html>
