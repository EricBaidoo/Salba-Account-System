<?php
include '../../../includes/auth_check.php';
require_finance_write();
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';
include '../../../includes/fee_categories.php';

// Fetch all classes and their Levels for dynamic class-based amount fields
$classes_result = $conn->query("SELECT name, Level FROM classes ORDER BY id ASC");
$class_groups = [];
if ($classes_result) {
    while ($row = $classes_result->fetch_assoc()) {
        $level = $row['Level'] ?: 'Other';
        $class_groups[$level][] = $row['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configure New Fee | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .type-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; border: 0.125rem solid transparent; }
        .type-card.active { border-color: #10b981; background-color: #f0fdf4; }
        .type-card.active .icon-circle { background-color: #10b981; color: white; }
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
                <span class="text-blue-600">Create Fee</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-plus-circle text-emerald-600"></i> Add New Fee Structure
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Define institutional revenue tranches and allocation rules.</p>
                </div>
                <a href="view_fees.php" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all flex items-center gap-2">
                    <i class="fas fa-arrow-left text-slate-400"></i> Cancel & Return
                </a>
            </div>
        </div>

        <div class="px-6">

        <form action="process_fee.php" method="POST" id="feeForm" class="max-w-4xl space-y-6">
            <!-- Part 1: Classification & Naming -->
            <section class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2">
                    <i class="fas fa-tag text-slate-400"></i> Classification & Identity
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div onclick="selectType('fixed')" id="card-fixed" class="type-card bg-slate-50 border border-slate-200 p-5 rounded-xl active cursor-pointer hover:border-emerald-300">
                        <div class="icon-circle w-10 h-10 bg-white border border-slate-200 rounded-lg flex items-center justify-center text-slate-500 mb-3 shadow-sm transition-all">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-800 mb-1">Fixed Amount</h4>
                        <p class="text-xs text-slate-500 font-medium">Same value for all students</p>
                    </div>
                    <div onclick="selectType('class_based')" id="card-class_based" class="type-card bg-slate-50 border border-slate-200 p-5 rounded-xl cursor-pointer hover:border-emerald-300">
                        <div class="icon-circle w-10 h-10 bg-white border border-slate-200 rounded-lg flex items-center justify-center text-slate-500 mb-3 shadow-sm transition-all">
                            <i class="fas fa-users-class"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-800 mb-1">Class-Based</h4>
                        <p class="text-xs text-slate-500 font-medium">Varied by academic level</p>
                    </div>
                    <div onclick="selectType('category')" id="card-category" class="type-card bg-slate-50 border border-slate-200 p-5 rounded-xl cursor-pointer hover:border-emerald-300">
                        <div class="icon-circle w-10 h-10 bg-white border border-slate-200 rounded-lg flex items-center justify-center text-slate-500 mb-3 shadow-sm transition-all">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-800 mb-1">Categorical</h4>
                        <p class="text-xs text-slate-500 font-medium">Assigned to specific groups</p>
                    </div>
                </div>

                <input type="hidden" name="fee_type" id="fee_type_input" value="fixed">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Official Fee Name</label>
                        <select name="fee_name" id="fee_name_select" onchange="handleCustomName()" class="w-full px-4 py-2 bg-white border border-slate-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm font-medium text-slate-900 appearance-none transition-all">
                            <option value="">Select template...</option>
                            <option value="Tuition Fee">Tuition Fee</option>
                            <option value="Development Levy">Development Levy</option>
                            <option value="Books Fee">Books Fee</option>
                            <option value="Feeding Fee">Feeding Fee</option>
                            <option value="Transport Fee">Transport Fee</option>
                            <option value="custom">-- Custom Identifier --</option>
                        </select>
                    </div>
                    <div id="customNameGroup" class="hidden">
                        <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Custom Identifier</label>
                        <input type="text" name="custom_fee_name" id="custom_fee_name" class="w-full px-4 py-2 bg-white border border-slate-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm font-medium text-slate-900 transition-all" placeholder="Enter custom name...">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Detailed Description (Optional)</label>
                        <textarea name="description" rows="2" class="w-full px-4 py-3 bg-white border border-slate-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm font-medium text-slate-900 transition-all" placeholder="Explain the purpose of this fee..."></textarea>
                    </div>
                </div>
            </section>

            <!-- Part 2: Monetary Configuration -->
            <section class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2">
                    <i class="fas fa-coins text-slate-400"></i> Monetary Configuration
                </h3>

                <!-- Fixed Amount Context -->
                <div id="section-fixed" class="amount-section">
                    <div class="max-w-xs">
                        <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Global Fixed Amount (GHS)</label>
                        <div class="relative">
                            <input type="number" step="0.01" name="fixed_amount" class="w-full pl-10 pr-4 py-2 bg-white border border-slate-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-lg font-bold text-slate-900 transition-all">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-semibold">₵</span>
                        </div>
                    </div>
                </div>

                <!-- Class-Based Context -->
                <div id="section-class_based" class="amount-section hidden">
                    <div class="flex justify-between items-center mb-6">
                        <p class="text-sm font-medium text-slate-600">Define unique amounts for specific academic levels.</p>
                        <button type="button" onclick="applyTuitionPreset()" class="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-4 py-2 rounded-lg transition-all hover:bg-emerald-100 flex items-center gap-2"><i class="fas fa-magic"></i> Apply Standard Tuitions</button>
                    </div>
                    <div class="space-y-6">
                        <?php foreach ($class_groups as $level => $classes): ?>
                            <div class="bg-slate-50 p-5 rounded-lg border border-slate-200">
                                <h5 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-4 border-b border-slate-200 pb-2"><?= htmlspecialchars($level) ?> Stream</h5>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                    <?php foreach ($classes as $class): ?>
                                        <div class="relative">
                                            <input type="number" step="0.01" name="class_amounts[<?= htmlspecialchars($class) ?>]" placeholder="<?= htmlspecialchars($class) ?>" class="w-full pl-8 pr-3 py-2 bg-white border border-slate-300 rounded-md focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm font-semibold text-slate-900">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-semibold text-sm">₵</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Category Context -->
                <div id="section-category" class="amount-section hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($fee_categories as $cid => $cname): ?>
                            <div class="flex items-center justify-between bg-slate-50 p-4 rounded-lg border border-slate-200">
                                <span class="text-sm font-bold text-slate-800 tracking-tight"><?= htmlspecialchars($cname) ?></span>
                                <div class="relative w-40">
                                    <input type="number" step="0.01" name="category_amounts[<?= $cid ?>]" class="w-full pl-8 pr-3 py-2 bg-white border border-slate-300 rounded-md focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm font-semibold text-slate-900" placeholder="Amount">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-semibold text-sm">₵</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <div class="bg-slate-50 border border-slate-200 rounded-xl p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-white border border-slate-200 rounded-lg flex items-center justify-center text-emerald-500 text-lg shadow-sm">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Ready for Validation</h3>
                        <p class="text-slate-500 text-xs mt-0.5">Fee configuration will be locked upon submission.</p>
                    </div>
                </div>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm px-6 py-2.5 rounded-lg shadow-sm transition-all flex items-center gap-2">
                    <i class="fas fa-save"></i> Initialize Fee Structure
                </button>
            </div>
        </form>
        </div>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[0.625rem] font-black text-slate-300 uppercase tracking-[0.5em]">
            Institutional Asset Node &middot; Salba Oversight &middot; v9.5.0
        </footer>
    </main>

    <script>
        function selectType(type) {
            // Update UI
            document.querySelectorAll('.type-card').forEach(c => c.classList.remove('active'));
            document.getElementById('card-' + type).classList.add('active');
            
            // Update Input
            document.getElementById('fee_type_input').value = type;

            // Show relevant section
            document.querySelectorAll('.amount-section').forEach(s => s.classList.add('hidden'));
            document.getElementById('section-' + type).classList.remove('hidden');
        }

        function handleCustomName() {
            const select = document.getElementById('fee_name_select');
            const custom = document.getElementById('customNameGroup');
            if (select.value === 'custom') {
                custom.classList.remove('hidden');
            } else {
                custom.classList.add('hidden');
            }
        }

        function applyTuitionPreset() {
            // Early Years (700) vs Primary (800)
            const inputs = document.querySelectorAll('input[name^="class_amounts"]');
            inputs.forEach(input => {
                const name = input.name.toLowerCase();
                if(name.includes('creche') || name.includes('nursery') || name.includes('kg')) {
                    input.value = "700";
                } else if(name.includes('basic') || name.includes('grade')) {
                    input.value = "800";
                }
            });
        }
    </script>
</body>
</html>
