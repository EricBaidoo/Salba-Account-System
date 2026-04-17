<?php 
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

// Get semester and year
$current_term = getCurrentSemester($conn);
$academic_year = getAcademicYear($conn);
$available_terms = getAvailableSemesters($conn);

// Build Academic Year options
$year_options = [];
$yrs_rs = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs_rs) {
    while ($yr = $yrs_rs->fetch_assoc()) {
        if (!empty($yr['academic_year'])) $year_options[] = $yr['academic_year'];
    }
}
if (!in_array($academic_year, $year_options, true)) array_unshift($year_options, $academic_year);

// Fetch students
$students_rs = $conn->query("SELECT id, first_name, last_name, class FROM students WHERE status='active' ORDER BY class, first_name, last_name");
$structured_students = [];
while($s = $students_rs->fetch_assoc()) {
    $structured_students[$s['class']][] = $s;
}

// Fetch fees
$fees_query = "SELECT f.id, f.name, f.amount, f.fee_type, f.description FROM fees f ORDER BY f.name";
$fees_rs = $conn->query($fees_query);
$fees = [];
while($f = $fees_rs->fetch_assoc()) $fees[] = $f;

// Fetch classes
$classes_rs = $conn->query("SELECT name FROM classes ORDER BY id ASC");
$classes = [];
while($c = $classes_rs->fetch_assoc()) $classes[] = $c['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Assignment Center | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .mode-card { cursor: pointer; border: 2px solid transparent; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .mode-card.active { border-color: #10b981; background-color: #f0fdf4; }
        .mode-card.active i { color: #10b981; }
        .item-card { cursor: pointer; border: 1px solid #f1f5f9; transition: all 0.2s; }
        .item-card.active { border-color: #10b981; background-color: #f0fdf4; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1); }
        .item-card.active .check-indicator { background: #10b981; color: white; opacity: 1; }
        .check-indicator { opacity: 0; transition: opacity 0.2s; }
        .sticky-summary { position: sticky; bottom: 2rem; z-index: 40; }
    </style>
</head>
<body class="text-slate-900 leading-relaxed">
    <?php include '../../../includes/sidebar_admin.php'; ?>

    <main class="ml-72 p-10 min-h-screen">
        <!-- Header -->
        <header class="mb-12 flex justify-between items-end">
            <div>
                <div class="flex items-center gap-2 text-emerald-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-emerald-600"></span>
                    Fee Allocation Node
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Assignment <span class="text-emerald-600">Control Hub</span></h1>
                <p class="text-slate-500 mt-2 font-medium italic">Execute bulk or individual fee assignments with institutional precision.</p>
            </div>
            <div class="flex gap-4">
                <a href="view_assigned_fees.php" class="bg-white border border-slate-200 text-slate-600 font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:bg-slate-50 transition-all leading-none">
                    <i class="fas fa-list mr-2"></i> Audit History
                </a>
            </div>
        </header>

        <form action="assign_fee.php" method="POST" id="assignForm">
            <!-- Hidden Inputs -->
            <input type="hidden" name="assignment_type" id="assignment_type_input" value="individual">
            <input type="hidden" name="selectedStudentId" id="selectedStudentId">
            <input type="hidden" name="selectedStudentIds" id="selectedStudentIds">
            <input type="hidden" name="selectedFees" id="selectedFeesInput">

            <!-- Mode Selection -->
            <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                <div onclick="setMode('individual')" id="mode-individual" class="mode-card bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 flex items-center gap-6 active">
                    <div class="w-14 h-14 bg-slate-50 flex items-center justify-center rounded-2xl text-slate-400 text-xl transition-all">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-1">Individual</h4>
                        <p class="text-[10px] text-slate-400 font-bold">Targeted single student</p>
                    </div>
                </div>
                <div onclick="setMode('multi-student')" id="mode-multi-student" class="mode-card bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 flex items-center gap-6">
                    <div class="w-14 h-14 bg-slate-50 flex items-center justify-center rounded-2xl text-slate-400 text-xl transition-all">
                        <i class="fas fa-users-rectangle"></i>
                    </div>
                    <div>
                        <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-1">Multi-Select</h4>
                        <p class="text-[10px] text-slate-400 font-bold">Custom student selection</p>
                    </div>
                </div>
                <div onclick="setMode('class')" id="mode-class" class="mode-card bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 flex items-center gap-6">
                    <div class="w-14 h-14 bg-slate-50 flex items-center justify-center rounded-2xl text-slate-400 text-xl transition-all">
                        <i class="fas fa-school"></i>
                    </div>
                    <div>
                        <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-1">Class Bulk</h4>
                        <p class="text-[10px] text-slate-400 font-bold">All students in a level</p>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                <!-- Left: Target Hub -->
                <div class="lg:col-span-7 space-y-10">
                    <div class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm relative overflow-hidden">
                        <div class="flex justify-between items-center mb-8">
                            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]">Select Billing Target</h3>
                            <div id="target-search-wrap" class="relative w-64">
                                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 text-xs"></i>
                                <input type="text" id="targetSearch" placeholder="Search entity..." class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-100 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                            </div>
                        </div>

                        <!-- Class Selection Dropdown (Only for Class Mode) -->
                        <div id="class-mode-select" class="hidden mb-8 p-8 bg-emerald-50 rounded-3xl border border-emerald-100">
                             <label class="text-[10px] font-black text-emerald-800 uppercase tracking-widest mb-3 block">Target Academic Level</label>
                             <select name="classSelect" id="classSelect" class="w-full px-6 py-4 bg-white border border-emerald-100 rounded-2xl font-black text-slate-900 outline-none focus:ring-2 focus:ring-emerald-500 appearance-none">
                                <option value="">Select Class...</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                <?php endforeach; ?>
                             </select>
                        </div>

                        <div id="student-grid" class="max-h-[500px] overflow-y-auto space-y-8 pr-2 custom-scrollbar">
                             <?php foreach($structured_students as $class => $list): ?>
                                <div class="student-group" data-group="<?= htmlspecialchars($class) ?>">
                                    <h5 class="text-[9px] font-black text-slate-300 uppercase tracking-[0.4em] mb-4 flex items-center gap-2">
                                        <?= htmlspecialchars($class) ?> <span class="h-px bg-slate-50 flex-1"></span>
                                    </h5>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <?php foreach($list as $s): 
                                            $fullname = $s['first_name'].' '.$s['last_name'];
                                        ?>
                                            <div onclick="toggleStudentSelection(<?= $s['id'] ?>, '<?= addslashes($fullname) ?>')" 
                                                 id="student-<?= $s['id'] ?>" 
                                                 class="item-card bg-white p-4 rounded-2xl flex items-center justify-between group"
                                                 data-name="<?= strtolower($fullname) ?>">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-slate-300 font-black text-xs group-hover:bg-emerald-50 group-hover:text-emerald-500 transition-colors">
                                                        <?= substr($s['first_name'], 0, 1) ?>
                                                    </div>
                                                    <div>
                                                        <p class="text-[11px] font-black text-slate-800 uppercase leading-none mb-1"><?= htmlspecialchars($fullname) ?></p>
                                                        <p class="text-[9px] font-bold text-slate-400">UID: SMS-<?= str_pad($s['id'], 3, '0', STR_PAD_LEFT) ?></p>
                                                    </div>
                                                </div>
                                                <div class="check-indicator w-6 h-6 rounded-full flex items-center justify-center text-[10px]">
                                                    <i class="fas fa-check"></i>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                             <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Right: Fee Selection -->
                <div class="lg:col-span-5 space-y-10">
                    <div class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8">Select Billing Entries</h3>
                        <div class="space-y-3">
                            <?php foreach($fees as $f): ?>
                                <div onclick="toggleFeeSelection(<?= $f['id'] ?>, '<?= addslashes($f['name']) ?>')" 
                                     id="fee-<?= $f['id'] ?>" 
                                     class="item-card bg-white p-5 rounded-2xl flex items-center justify-between group transition-all">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-400 text-lg group-hover:bg-emerald-100 group-hover:text-emerald-600 transition-colors">
                                            <i class="fas fa-money-bill-transfer"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-[11px] font-black text-slate-900 uppercase leading-none mb-1"><?= htmlspecialchars($f['name']) ?></h4>
                                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"><?= ucfirst(str_replace('_', ' ', $f['fee_type'])) ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right flex items-center gap-4">
                                        <?php if($f['fee_type']=='fixed'): ?>
                                            <span class="text-xs font-black text-slate-900">₵<?= number_format($f['amount'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-[9px] font-black text-amber-500 uppercase tracking-tighter bg-amber-50 px-2 py-1 rounded">Varies</span>
                                        <?php endif; ?>
                                        <div class="check-indicator w-6 h-6 rounded-full flex items-center justify-center text-[10px]">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Meta -->
                    <div class="bg-indigo-900 rounded-[2.5rem] p-10 border border-indigo-800 text-white shadow-xl shadow-indigo-900/20">
                         <h4 class="text-[10px] font-black text-indigo-300 uppercase tracking-[0.3em] mb-6 flex items-center gap-2">
                             <i class="fas fa-calendar-alt"></i> Temporal Context
                         </h4>
                         <div class="space-y-6">
                            <div>
                                <label class="text-[9px] font-black text-indigo-400 uppercase tracking-widest mb-2 block">Maturity/Due Date</label>
                                <input type="date" name="due_date" required class="w-full bg-indigo-950 border border-indigo-800 rounded-2xl px-5 py-4 text-xs font-bold outline-none focus:border-indigo-400 transition-all">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-[9px] font-black text-indigo-400 uppercase tracking-widest mb-2 block">Semester</label>
                                    <select name="semester" class="w-full bg-indigo-950 border border-indigo-800 rounded-2xl px-5 py-4 text-xs font-bold outline-none focus:border-indigo-400 appearance-none">
                                        <?php foreach($available_terms as $t): ?>
                                            <option value="<?= htmlspecialchars($t) ?>" <?= $t === $current_term ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[9px] font-black text-indigo-400 uppercase tracking-widest mb-2 block">Year</label>
                                    <select name="academic_year" class="w-full bg-indigo-950 border border-indigo-800 rounded-2xl px-5 py-4 text-xs font-bold outline-none focus:border-indigo-400 appearance-none">
                                        <?php foreach($year_options as $y): ?>
                                            <option value="<?= htmlspecialchars($y) ?>" <?= $y === $academic_year ? 'selected' : '' ?>><?= formatAcademicYearDisplay($conn, $y) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                         </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Summary Anchor -->
            <div class="sticky-summary mt-10">
                 <div class="bg-slate-900 rounded-3xl p-6 flex flex-wrap items-center justify-between gap-6 shadow-2xl border border-slate-700">
                    <div class="flex items-center gap-10">
                         <div>
                            <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Impact Radius</p>
                            <p class="text-base font-black text-white leading-none" id="summary-targets">0 Targets</p>
                         </div>
                         <div>
                            <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Billing Tranches</p>
                            <p class="text-base font-black text-white leading-none" id="summary-fees">0 Entries</p>
                         </div>
                         <div>
                            <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Maturity Status</p>
                            <p class="text-base font-black text-emerald-400 leading-none">Ready for Sync</p>
                         </div>
                    </div>
                    <div class="flex gap-4">
                        <button type="reset" class="px-8 py-4 bg-slate-800 text-slate-400 font-black text-[10px] uppercase tracking-widest rounded-2xl hover:text-white transition-all">Flush Draft</button>
                        <button type="submit" id="submitBtn" disabled class="px-10 py-4 bg-emerald-600 font-black text-[10px] uppercase tracking-[0.2em] rounded-2xl text-white shadow-xl shadow-emerald-900/20 disabled:opacity-30 disabled:cursor-not-allowed active:scale-95 transition-all">Execute Assignment</button>
                    </div>
                 </div>
            </div>
        </form>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">
            Institutional Asset Allocation Node &middot; Salba Montessori &middot; v9.5.0
        </footer>
    </main>

    <script>
        let currentMode = 'individual';
        let selectedFees = new Set();
        let selectedStudents = new Set();
        let selectedClass = '';

        function setMode(mode) {
            currentMode = mode;
            document.querySelectorAll('.mode-card').forEach(c => c.classList.remove('active'));
            document.getElementById('mode-' + mode).classList.add('active');
            document.getElementById('assignment_type_input').value = mode;

            // Reset Subsidiarity
            selectedStudents.clear();
            selectedClass = '';
            document.getElementById('classSelect').value = '';
            document.querySelectorAll('.item-card').forEach(c => c.classList.remove('active'));

            // Toggle visibility
            const classSelectWrap = document.getElementById('class-mode-select');
            const studentGrid = document.getElementById('student-grid');
            const searchWrap = document.getElementById('target-search-wrap');

            if (mode === 'class') {
                classSelectWrap.classList.remove('hidden');
                studentGrid.classList.add('hidden');
                searchWrap.classList.add('hidden');
            } else {
                classSelectWrap.classList.add('hidden');
                studentGrid.classList.remove('hidden');
                searchWrap.classList.remove('hidden');
            }
            syncState();
        }

        function toggleFeeSelection(id, name) {
            const card = document.getElementById('fee-' + id);
            if (selectedFees.has(id)) {
                selectedFees.delete(id);
                card.classList.remove('active');
            } else {
                selectedFees.add(id);
                card.classList.add('active');
            }
            syncState();
        }

        function toggleStudentSelection(id, name) {
            const card = document.getElementById('student-' + id);
            
            if (currentMode === 'individual') {
                selectedStudents.clear();
                document.querySelectorAll('.student-group .item-card').forEach(c => c.classList.remove('active'));
                selectedStudents.add(id);
                card.classList.add('active');
            } else if (currentMode === 'multi-student') {
                if (selectedStudents.has(id)) {
                    selectedStudents.delete(id);
                    card.classList.remove('active');
                } else {
                    selectedStudents.add(id);
                    card.classList.add('active');
                }
            }
            syncState();
        }

        document.getElementById('classSelect').addEventListener('change', function() {
            selectedClass = this.value;
            syncState();
        });

        function syncState() {
            // Update counts
            const feeCount = selectedFees.size;
            let targetCount = 0;
            
            if (currentMode === 'class') {
                targetCount = selectedClass ? 1 : 0;
                document.getElementById('summary-targets').textContent = selectedClass ? selectedClass : '0 Targets';
            } else {
                targetCount = selectedStudents.size;
                document.getElementById('summary-targets').textContent = targetCount + ' Targets';
            }

            document.getElementById('summary-fees').textContent = feeCount + ' Entries';

            // Inputs
            document.getElementById('selectedFeesInput').value = Array.from(selectedFees).join(',');
            document.getElementById('selectedStudentIds').value = Array.from(selectedStudents).join(',');
            if (currentMode === 'individual') {
                document.getElementById('selectedStudentId').value = Array.from(selectedStudents)[0] || '';
            }

            // Button status
            const btn = document.getElementById('submitBtn');
            btn.disabled = !(feeCount > 0 && targetCount > 0);
        }

        // Search logic
        document.getElementById('targetSearch').addEventListener('input', function(e) {
            const q = e.target.value.toLowerCase();
            document.querySelectorAll('.student-group').forEach(group => {
                let hasMatch = false;
                group.querySelectorAll('.item-card').forEach(card => {
                    const match = card.dataset.name.includes(q);
                    card.style.display = match ? 'flex' : 'none';
                    if (match) hasMatch = true;
                });
                group.style.display = hasMatch ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>
