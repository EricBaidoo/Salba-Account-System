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
        .type-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; border: 2px solid transparent; }
        .type-card.active { border-color: #10b981; background-color: #f0fdf4; }
        .type-card.active .icon-circle { background-color: #10b981; color: white; }
    </style>
</head>
<body class="bg-[#F8FAFC] text-slate-900 leading-relaxed">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 p-10 min-h-screen">
        <!-- Header -->
        <header class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-emerald-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-emerald-600"></span>
                    Asset Configuration
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Add New <span class="text-emerald-600">Fee Structure</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Define institutional revenue tranches and allocation rules.</p>
            </div>
            <div>
                <a href="view_fees.php" class="bg-white text-slate-600 border border-slate-200 font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:bg-slate-50 transition-all leading-none inline-block">
                    <i class="fas fa-arrow-left mr-2"></i> Cancel & Return
                </a>
            </div>
        </header>

        <form action="process_fee.php" method="POST" id="feeForm" class="max-w-4xl">
            <!-- Part 1: Classification & Naming -->
            <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm mb-10">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-3">
                    01. Classification & Identity <span class="flex-1 h-[1px] bg-slate-100"></span>
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                    <div onclick="selectType('fixed')" id="card-fixed" class="type-card bg-slate-50 p-6 rounded-3xl active">
                        <div class="icon-circle w-12 h-12 bg-white rounded-xl flex items-center justify-center text-slate-400 mb-4 shadow-sm transition-all">
                            <i class="fas fa-money-bill-check text-xl"></i>
                        </div>
                        <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-1">Fixed Amount</h4>
                        <p class="text-[10px] text-slate-400 font-bold">Same value for all students</p>
                    </div>
                    <div onclick="selectType('class_based')" id="card-class_based" class="type-card bg-slate-50 p-6 rounded-3xl">
                        <div class="icon-circle w-12 h-12 bg-white rounded-xl flex items-center justify-center text-slate-400 mb-4 shadow-sm transition-all">
                            <i class="fas fa-school text-xl"></i>
                        </div>
                        <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-1">Class-Based</h4>
                        <p class="text-[10px] text-slate-400 font-bold">Varied by academic level</p>
                    </div>
                    <div onclick="selectType('category')" id="card-category" class="type-card bg-slate-50 p-6 rounded-3xl">
                        <div class="icon-circle w-12 h-12 bg-white rounded-xl flex items-center justify-center text-slate-400 mb-4 shadow-sm transition-all">
                            <i class="fas fa-tags text-xl"></i>
                        </div>
                        <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-1">Categorical</h4>
                        <p class="text-[10px] text-slate-400 font-bold">Assigned to specific groups</p>
                    </div>
                </div>

                <input type="hidden" name="fee_type" id="fee_type_input" value="fixed">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Official Fee Name</label>
                        <select name="fee_name" id="fee_name_select" onchange="handleCustomName()" class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-bold text-slate-700 appearance-none transition-all">
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
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Custom Identifier</label>
                        <input type="text" name="custom_fee_name" id="custom_fee_name" class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-bold text-slate-700 transition-all" placeholder="Enter custom name...">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Detailed Description (Optional)</label>
                        <textarea name="description" rows="2" class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-3xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-bold text-slate-700 transition-all" placeholder="Explain the purpose of this fee..."></textarea>
                    </div>
                </div>
            </section>

            <!-- Part 2: Monetary Configuration -->
            <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm mb-12">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-3">
                    02. Monetary Configuration <span class="flex-1 h-[1px] bg-slate-100"></span>
                </h3>

                <!-- Fixed Amount Context -->
                <div id="section-fixed" class="amount-section">
                    <div class="max-w-xs">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Global Fixed Amount (GHS)</label>
                        <div class="relative">
                            <input type="number" step="0.01" name="fixed_amount" class="w-full px-12 py-5 bg-emerald-50 border border-emerald-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-xl font-black text-emerald-900 transition-all">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-emerald-400 font-black">₵</span>
                        </div>
                    </div>
                </div>

                <!-- Class-Based Context -->
                <div id="section-class_based" class="amount-section hidden">
                    <div class="flex justify-between items-center mb-6">
                        <p class="text-xs font-bold text-slate-500">Define unique amounts for specific academic levels.</p>
                        <button type="button" onclick="applyTuitionPreset()" class="text-[9px] font-black uppercase tracking-widest text-emerald-600 bg-emerald-50 px-4 py-2 rounded-xl transition-all hover:bg-emerald-100">Apply Standard Tuitions</button>
                    </div>
                    <div class="space-y-8">
                        <?php foreach ($class_groups as $level => $classes): ?>
                            <div>
                                <h5 class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4"><?= htmlspecialchars($level) ?> Stream</h5>
                                <div class="grid grid-cols-1 md:grid-cols-2 md:grid-cols-3 gap-4">
                                    <?php foreach ($classes as $class): ?>
                                        <div class="relative">
                                            <input type="number" step="0.01" name="class_amounts[<?= htmlspecialchars($class) ?>]" placeholder="<?= htmlspecialchars($class) ?>" class="w-full px-10 py-4 bg-slate-50 border border-slate-100 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-black text-slate-700">
                                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 font-bold text-xs">₵</span>
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
                            <div class="flex items-center gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                <span class="flex-1 text-[10px] font-black text-slate-600 uppercase tracking-tight"><?= htmlspecialchars($cname) ?></span>
                                <div class="relative w-32">
                                    <input type="number" step="0.01" name="category_amounts[<?= $cid ?>]" class="w-full px-8 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-black text-slate-900">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 font-bold text-[10px]">₵</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <div class="bg-slate-900 rounded-[2.5rem] p-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 shadow-2xl shadow-slate-900/20">
                <div class="flex items-center gap-6">
                    <div class="w-14 h-14 bg-emerald-500 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg shadow-emerald-500/20">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div>
                        <h3 class="text-white font-black text-sm uppercase tracking-[0.1em]">Ready for Validation</h3>
                        <p class="text-slate-400 text-xs font-medium">Fee configuration will be locked upon submission.</p>
                    </div>
                </div>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white font-black text-[11px] uppercase tracking-[0.2em] px-10 py-5 rounded-2xl shadow-xl transition-all h-fit active:scale-95 leading-none">
                    Initialize Fee Structure
                </button>
            </div>
        </form>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">
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
