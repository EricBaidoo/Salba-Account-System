<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Fetch fees and classes for the form
$fees_query = "
    SELECT f.id, f.name, f.amount, f.fee_type,
           GROUP_CONCAT(
               CASE 
                   WHEN fa.class_name IS NOT NULL THEN CONCAT(fa.class_name, ':GHâ‚µ', FORMAT(fa.amount, 2))
                   WHEN fa.category IS NOT NULL THEN CONCAT(
                       CASE fa.category 
                           WHEN 'early_years' THEN 'Early Years'
                           WHEN 'primary' THEN 'Primary'
                       END, ':GHâ‚µ', FORMAT(fa.amount, 2)
                   )
               END
               ORDER BY fa.amount
               SEPARATOR ' | '
           ) as amount_details
    FROM fees f
    LEFT JOIN fee_amounts fa ON f.id = fa.fee_id
    GROUP BY f.id, f.name, f.amount, f.fee_type
    ORDER BY f.name";
$fees_result = $conn->query($fees_query);

$classes_result = $conn->query("SELECT DISTINCT name FROM classes ORDER BY name");

// Academic year options
$default_academic_year = getSystemSetting($conn, 'academic_year', date('Y') . '/' . (date('Y') + 1));
$year_options = [];
$yrs = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs) {
    while ($yr = $yrs->fetch_assoc()) {
        if (!empty($yr['academic_year'])) {
            $year_options[] = $yr['academic_year'];
        }
    }
    $yrs->close();
}
if (!in_array($default_academic_year, $year_options, true)) {
    array_unshift($year_options, $default_academic_year);
}
$display_year = formatAcademicYearDisplay($conn, $default_academic_year);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Semester Bills - Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        .fee-card.selected {
            border-color: #10b981;
            background-color: #ecfdf5;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
        }
        .fee-card.selected .check-icon {
            opacity: 1;
            transform: scale(1);
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased">
    <div class="max-w-6xl mx-auto mt-10 px-4 mb-20">
        <!-- Navigation -->
        <div class="mb-6 flex items-center justify-between">
            <a href="../dashboard.php" class="text-slate-500 hover:text-emerald-600 transition-colors flex items-center gap-2 text-sm font-bold bg-white px-4 py-2 rounded-full shadow-sm border border-slate-100">
                <i class="fas fa-arrow-left"></i> Back to Finance Hub
            </a>
            <a href="view_semester_bills.php" class="text-emerald-600 bg-emerald-50 hover:bg-emerald-100 transition-colors flex items-center gap-2 text-sm font-bold px-4 py-2 rounded-full shadow-sm border border-emerald-200">
                <i class="fas fa-print"></i> Open Billing Center
            </a>
        </div>

        <!-- Header -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8 mb-8 text-center relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-emerald-400 to-teal-600"></div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight flex items-center justify-center gap-3 mb-2">
                <i class="fas fa-bolt text-amber-500"></i> Generate Semester Bills
            </h1>
            <p class="text-slate-500 text-sm font-medium">Select global fees, assign them to classes, and initiate the billing cycle.</p>
        </div>

        <form method="POST" action="process_semester_bills.php" id="termBillForm">
            
            <!-- Settings Block -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden mb-8">
                <div class="px-8 py-5 border-b border-slate-50 bg-slate-50/50">
                    <h2 class="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                        <i class="fas fa-cog text-emerald-500"></i> Target Configurations
                    </h2>
                </div>
                <div class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 relative">
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">
                                Semester Context <span class="text-red-500">*</span>
                            </label>
                            <select class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all appearance-none" name="semester" id="semester" required>
                                <option value="">Select Semester</option>
                                <option value="First Semester">First Semester</option>
                                <option value="Second Semester">Second Semester</option>
                                <option value="Third Semester">Third Semester</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">
                                Academic Year <span class="text-red-500">*</span>
                            </label>
                            <select class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all appearance-none" name="academic_year" id="academic_year" required>
                                <?php foreach ($year_options as $yr): $label = formatAcademicYearDisplay($conn, $yr); ?>
                                    <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo ($yr === $default_academic_year) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">
                                Due Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all" name="due_date" id="due_date" required>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">
                                Target Specific Class
                            </label>
                            <select class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all appearance-none" name="class_filter" id="class_filter">
                                <option value="all">Apply to All Classes</option>
                                <?php while ($class = $classes_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($class['name']); ?>">
                                        <?php echo htmlspecialchars($class['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fees Selection Block -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden mb-12">
                <div class="px-8 py-5 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between">
                    <h2 class="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                        <i class="fas fa-list-check text-indigo-500"></i> Select Bills to Append
                    </h2>
                    <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-full text-[10px] font-black uppercase tracking-widest" id="feeCountBadge">0 Selected</span>
                </div>
                <div class="p-8">
                    
                    <div class="bg-indigo-50 border border-indigo-100 text-indigo-700 px-6 py-4 rounded-2xl flex gap-3 text-sm font-medium mb-8">
                        <i class="fas fa-info-circle text-lg mt-0.5"></i>
                        <p>Click the fee grids below to mark them for assignment. The system will automatically calculate variable fees based on the student's designated class tier.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php 
                        $fees_result->data_seek(0);
                        while ($fee = $fees_result->fetch_assoc()): 
                            $type_badge = '';
                            $amount_display = '';
                            
                            switch($fee['fee_type']) {
                                case 'fixed':
                                    $type_badge = '<span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-[9px] uppercase tracking-widest font-black">Fixed</span>';
                                    $amount_display = 'GH₵' . number_format($fee['amount'], 2);
                                    break;
                                case 'class_based':
                                    $type_badge = '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-[9px] uppercase tracking-widest font-black">Tiered</span>';
                                    $amount_display = 'Varies by Class';
                                    break;
                                case 'category':
                                    $type_badge = '<span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-[9px] uppercase tracking-widest font-black">Category</span>';
                                    $amount_display = 'Varies by Stage';
                                    break;
                            }
                        ?>
                        <div class="fee-card border-2 border-slate-200 rounded-2xl p-6 cursor-pointer hover:border-emerald-300 hover:shadow-md transition-all relative group bg-white" data-fee-id="<?php echo $fee['id']; ?>">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h6 class="text-sm font-bold text-slate-800 pr-6"><?php echo htmlspecialchars($fee['name']); ?></h6>
                                    <div class="mt-1"><?php echo $type_badge; ?></div>
                                </div>
                                <div class="check-icon absolute top-6 right-6 opacity-0 transform scale-50 transition-all text-emerald-500 text-xl">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="text-lg font-black text-slate-900 mt-4"><?php echo $amount_display; ?></div>
                            <?php if ($fee['amount_details']): ?>
                                <p class="text-[10px] text-slate-500 mt-3 font-medium leading-relaxed bg-slate-50 px-3 py-2 rounded-lg border border-slate-100">
                                    <?php echo htmlspecialchars($fee['amount_details']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <input type="hidden" name="selected_fees" id="selected_fees" value="">
                </div>
            </div>

            <!-- Sticky Submit Bar -->
            <div id="preview" class="fixed bottom-0 left-0 lg:left-72 right-0 bg-slate-900 border-t border-slate-800 shadow-2xl p-6 transform translate-y-full transition-transform duration-300 z-50 rounded-t-3xl hidden">
                <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="text-white flex items-center gap-4">
                        <div class="w-12 h-12 bg-amber-500 rounded-xl flex items-center justify-center text-white text-xl shadow-lg border border-amber-400">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div>
                            <div class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Execution Ready</div>
                            <p class="font-medium text-slate-200 text-sm">
                                Generating <span id="feeCount" class="font-black text-white px-2 py-0.5 bg-slate-800 rounded">0</span> fees for 
                                <span id="studentCount" class="font-black text-white px-2 py-0.5 bg-slate-800 rounded">all active</span> students
                            </p>
                        </div>
                    </div>
                    <button type="submit" class="w-full md:w-auto bg-amber-500 hover:bg-amber-400 text-slate-900 font-black text-sm uppercase tracking-widest px-8 py-4 rounded-xl shadow-lg shadow-amber-500/20 transition-all flex items-center justify-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed" id="submitBtn" disabled>
                        Initialize Billing Target <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Padding for sticky footer -->
    <div style="height: 100px;"></div>

    <script>
        const selectedFees = new Set();
        const feeCards = document.querySelectorAll('.fee-card');
        const selectedFeesInput = document.getElementById('selected_fees');
        const submitBtn = document.getElementById('submitBtn');
        const preview = document.getElementById('preview');
        const feeCountBadge = document.getElementById('feeCountBadge');
        
        const termSelect = document.getElementById('semester');
        const dueDateInput = document.getElementById('due_date');
        const classFilter = document.getElementById('class_filter');
        const yearSelect = document.getElementById('academic_year');

        feeCards.forEach(card => {
            card.addEventListener('click', function() {
                const feeId = this.dataset.feeId;
                if (selectedFees.has(feeId)) {
                    selectedFees.delete(feeId);
                    this.classList.remove('selected');
                } else {
                    selectedFees.add(feeId);
                    this.classList.add('selected');
                }
                updateForm();
            });
        });

        function updateForm() {
            selectedFeesInput.value = Array.from(selectedFees).join(',');
            const count = selectedFees.size;
            
            document.getElementById('feeCount').textContent = count;
            feeCountBadge.textContent = count + ' Selected';
            
            const classValue = classFilter.value;
            document.getElementById('studentCount').textContent = classValue === 'all' ? 'All' : classValue;
            
            const isValid = count > 0 && termSelect.value && dueDateInput.value && yearSelect.value;
            submitBtn.disabled = !isValid;
            
            if (isValid) {
                preview.classList.remove('hidden');
                setTimeout(() => {
                    preview.classList.remove('translate-y-full');
                }, 50);
            } else {
                preview.classList.add('translate-y-full');
                setTimeout(() => {
                    if(!submitBtn.disabled === false) preview.classList.add('hidden');
                }, 300);
            }
        }

        termSelect.addEventListener('change', updateForm);
        dueDateInput.addEventListener('change', updateForm);
        classFilter.addEventListener('change', updateForm);
        yearSelect.addEventListener('change', updateForm);

        // Set minimum date to today
        dueDateInput.min = new Date().toISOString().split('T')[0];
        updateForm();
    </script>
</body>
</html>
