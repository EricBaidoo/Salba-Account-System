<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/student_balance_functions.php';
include '../../../includes/semester_bill_functions.php';
require_once '../../../includes/semester_helpers.php';

if (!is_logged_in()) {
    header('Location: ../../../includes/login.php');
    exit;
}
require_finance_access();

// Get parameters
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$semester = isset($_GET['semester']) ? trim($_GET['semester']) : '';
$class_filter = isset($_GET['class']) ? trim($_GET['class']) : 'all';

$current_semester_val = getCurrentSemester($conn);
$default_academic_year = getAcademicYear($conn);
$selected_academic_year = isset($_GET['academic_year']) && $_GET['academic_year'] !== ''
    ? $_GET['academic_year']
    : $default_academic_year;

$display_academic_year = formatAcademicYearDisplay($conn, $selected_academic_year);

// Full semester invoice settings from DB
$invoice_settings = getSemesterInvoiceSettings($conn, $semester ?: $current_semester_val, $selected_academic_year);
$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');

if (empty($semester)) {
    header('Location: view_semester_bills.php');
    exit;
}

// Fetch students
$students = [];
if ($student_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ? AND status = 'active'");
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $s = $stmt->get_result()->fetch_assoc();
    if ($s) $students[] = $s;
} else {
    $query = "SELECT DISTINCT s.* FROM students s
              INNER JOIN student_fees sf ON s.id = sf.student_id
              WHERE s.status = 'active' AND sf.semester = ? AND (sf.academic_year = ? OR sf.academic_year IS NULL) AND sf.status != 'cancelled'";
    if ($class_filter !== 'all') $query .= " AND s.class = ?";
    $query .= " ORDER BY s.class, s.last_name, s.first_name";
    
    $stmt = $conn->prepare($query);
    if ($class_filter !== 'all') {
        $stmt->bind_param('sss', $semester, $selected_academic_year, $class_filter);
    } else {
        $stmt->bind_param('ss', $semester, $selected_academic_year);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $students[] = $row;
}

// Process student data
foreach ($students as &$student) {
    ensureArrearsAssignment($conn, $student['id'], $semester, $selected_academic_year);
    $fees_sql = "SELECT sf.*, f.name as fee_name 
                FROM student_fees sf 
                JOIN fees f ON sf.fee_id = f.id 
                WHERE sf.student_id = ? AND sf.semester = ? AND (sf.academic_year = ? OR sf.academic_year IS NULL) AND sf.status != 'cancelled'
                ORDER BY f.name";
    $f_stmt = $conn->prepare($fees_sql);
    $f_stmt->bind_param('iss', $student['id'], $semester, $selected_academic_year);
    $f_stmt->execute();
    $f_res = $f_stmt->get_result();
    $student['fees'] = [];
    $student['total_bill'] = 0;
    while ($f = $f_res->fetch_assoc()) {
        $student['fees'][] = $f;
        $student['total_bill'] += $f['amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semester Bills | Institutional Ledger</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; }
        .bill-page { 
            background: white; 
            width: 210mm; 
            min-height: 297mm; 
            padding: 20mm; 
            margin: 20px auto; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.05); 
            border-radius: 4px;
            position: relative;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 15rem;
            color: #f1f5f9;
            z-index: 0;
            user-select: none;
            font-weight: 800;
            pointer-events: none;
        }
        .content-wrap { position: relative; z-index: 1; }
        @media print {
            body { background: white; padding: 0; margin: 0; }
            .bill-page { box-shadow: none; margin: 0; width: 100%; border-radius: 0; }
            .no-print { display: none !important; }
            .bill-page { page-break-after: always; }
        }
    </style>
</head>
<body class="text-slate-900 leading-relaxed">
    <div class="no-print bg-slate-900 text-white py-4 px-8 flex justify-between items-center sticky top-0 z-50 shadow-2xl">
        <div class="flex items-center gap-4">
            <a href="view_semester_bills.php" class="text-slate-400 hover:text-white transition-all text-sm font-bold flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Exit to Billing Center
            </a>
            <span class="w-px h-6 bg-slate-700 mx-2"></span>
            <span class="text-xs font-black uppercase tracking-[0.2em] text-emerald-400">
                <?= count($students) ?> Bills Stacked
            </span>
        </div>
        <div class="flex gap-4">
            <button onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-500 text-white font-black text-[10px] uppercase tracking-widest px-6 py-3 rounded-xl transition-all shadow-lg shadow-emerald-600/20 leading-none">
                <i class="fas fa-print mr-2 text-xs"></i> Release Print Job
            </button>
        </div>
    </div>

    <?php if (empty($students)): ?>
        <div class="max-w-xl mx-auto mt-20 p-12 bg-white rounded-3xl text-center shadow-sm border border-slate-100">
            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-slate-200 text-4xl mx-auto mb-6">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <h2 class="text-2xl font-black text-slate-900 mb-2">Null Set Encountered</h2>
            <p class="text-slate-500 font-medium mb-8">No students found with active billing assignments for the selected parameters.</p>
            <a href="view_semester_bills.php" class="inline-flex bg-slate-900 text-white font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl">Adjust Selection</a>
        </div>
    <?php endif; ?>

    <?php foreach ($students as $student): 
        $bill_no = strtoupper($semester[0]) . substr(preg_replace('/[^0-9]/', '', $selected_academic_year), 0, 4) . str_pad($student['id'], 3, '0', STR_PAD_LEFT);
    ?>
    <div class="bill-page">
        <div class="watermark uppercase"><?= htmlspecialchars($school_name) ?></div>
        <div class="content-wrap">
            <!-- Header -->
            <div class="flex justify-between items-start mb-12 border-b-2 border-slate-100 pb-8">
                <div class="flex items-center gap-6">
                    <img src="../../../img/salba_logo.jpg" alt="Logo" class="w-24 h-24 object-contain rounded-2xl">
                    <div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tighter leading-none mb-1">SALBA MONTESSORI</h1>
                        <p class="text-[10px] font-black text-emerald-600 uppercase tracking-[0.4em] mb-3">Institutional Ledger</p>
                        <p class="text-[10px] font-bold text-slate-400 leading-tight">
                            GC-051-0961 | Tel: 059 887 2309<br>
                            Email: info@salbamontessori.edu.gh
                        </p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="bg-slate-900 text-white px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest mb-4">
                        Status: Bill Generated
                    </div>
                    <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest mb-1">Bill Reference</p>
                    <p class="text-lg font-black text-slate-900 leading-none">#<?= $bill_no ?></p>
                </div>
            </div>

            <!-- Student Snapshot -->
            <div class="grid grid-cols-3 gap-8 mb-12 bg-slate-50 p-8 rounded-3xl border border-slate-100">
                <div>
                    <h6 class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">Entity Description</h6>
                    <p class="text-sm font-black text-slate-900 uppercase leading-none mb-1"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></p>
                    <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-tight"><?= htmlspecialchars($student['class']) ?></p>
                </div>
                <div>
                    <h6 class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">Scope Context</h6>
                    <p class="text-sm font-black text-slate-900 leading-none mb-1 text-xs"><?= htmlspecialchars($semester) ?></p>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-tight"><?= htmlspecialchars($display_academic_year) ?></p>
                </div>
                <div class="text-right">
                    <h6 class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">Issue Date</h6>
                    <p class="text-sm font-black text-slate-900 mb-1"><?= date('F j, Y') ?></p>
                    <p class="text-[10px] font-bold text-slate-500">Fiscal Period Active</p>
                </div>
            </div>

            <div class="mb-2 w-full text-center">
                <h3 class="text-[11px] font-black text-slate-900 uppercase tracking-[0.5em] py-3 border-y border-slate-100">Consolidated Fee Schedule</h3>
            </div>

            <!-- Fees Table -->
            <table class="w-full mb-8">
                <thead>
                    <tr class="border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                        <th class="py-4 text-left">Classification</th>
                        <th class="py-4 text-right">Maturity Amount (GHS)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($student['fees'] as $fee): 
                        $is_arrears = strpos(strtolower($fee['fee_name']), 'arrears') !== false;
                    ?>
                    <tr class="<?= $is_arrears ? 'bg-rose-50/50' : '' ?>">
                        <td class="py-4 text-xs font-bold text-slate-700">
                             <?= strtoupper(htmlspecialchars($fee['fee_name'])) ?>
                             <?php if ($is_arrears): ?>
                                <span class="ml-2 text-[8px] font-black text-rose-500 uppercase bg-rose-100 px-2 py-0.5 rounded leading-none">Carry Forward</span>
                             <?php endif; ?>
                        </td>
                        <td class="py-4 text-right text-xs font-black text-slate-900"><?= number_format($fee['amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-slate-900">
                        <td class="py-5 text-sm font-black text-slate-900 uppercase tracking-tight">Aggregate Semester Liability</td>
                        <td class="py-5 text-right text-lg font-black text-slate-900 leading-none">GHS <?= number_format($student['total_bill'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>

            <div class="grid grid-cols-2 gap-12 mt-12 pb-12 border-b border-slate-100">
                 <!-- Payment Plan -->
                 <div>
                    <h4 class="text-[10px] font-black text-slate-900 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <i class="fas fa-calendar-check text-emerald-500"></i> Optimized Payment Plan
                    </h4>
                    <table class="w-full text-left text-[10px]">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 font-bold tracking-widest uppercase">
                                <th class="pb-2">Tranche</th>
                                <th class="pb-2 text-center">%</th>
                                <th class="pb-2 text-right">Amount (GHS)</th>
                                <th class="pb-2 text-right">Maturity Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($invoice_settings['payment_plan'] as $plan): 
                                $amt = ($student['total_bill'] * $plan['percent']) / 100;
                            ?>
                            <tr>
                                <td class="py-3 font-bold text-slate-700"><?= htmlspecialchars($plan['name']) ?></td>
                                <td class="py-3 text-center text-slate-400 font-black"><?= $plan['percent'] ?>%</td>
                                <td class="py-3 text-right font-black text-slate-900"><?= number_format($amt, 2) ?></td>
                                <td class="py-3 text-right font-bold text-indigo-500"><?= htmlspecialchars($plan['due_date']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                 </div>

                 <!-- Payment Modes -->
                 <div>
                    <h4 class="text-[10px] font-black text-slate-900 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <i class="fas fa-vault text-emerald-500"></i> Remittance Channels
                    </h4>
                    <div class="space-y-4">
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                             <h5 class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2"><?= htmlspecialchars($invoice_settings['payment_modes']['bank']['title'] ?? 'Bank Details') ?></h5>
                             <p class="text-[10px] font-black text-slate-800 leading-tight">
                                <span class="text-slate-400 font-bold uppercase tracking-tighter mr-2">Acc Name:</span> <?= htmlspecialchars($invoice_settings['payment_modes']['bank']['account_name'] ?? '---') ?><br>
                                <span class="text-slate-400 font-bold uppercase tracking-tighter mr-2">Acc No:</span> <?= htmlspecialchars($invoice_settings['payment_modes']['bank']['account_number'] ?? '---') ?><br>
                                <span class="text-slate-400 font-bold uppercase tracking-tighter mr-2">Bank:</span> <?= htmlspecialchars($invoice_settings['payment_modes']['bank']['bank_name'] ?? '---') ?>
                             </p>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                             <h5 class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2"><?= htmlspecialchars($invoice_settings['payment_modes']['momo']['title'] ?? 'Mobile Money') ?></h5>
                             <p class="text-[10px] font-black text-slate-800 leading-tight">
                                <span class="text-slate-400 font-bold uppercase tracking-tighter mr-2">Number:</span> <?= htmlspecialchars($invoice_settings['payment_modes']['momo']['number'] ?? '---') ?><br>
                                <span class="text-slate-400 font-bold uppercase tracking-tighter mr-2">Registered:</span> <?= htmlspecialchars($invoice_settings['payment_modes']['momo']['name'] ?? '---') ?>
                             </p>
                        </div>
                        <p class="text-[9px] font-bold text-indigo-600 bg-indigo-50 p-3 rounded-xl border border-indigo-100">
                            <i class="fas fa-info-circle mr-2"></i> <?= htmlspecialchars($invoice_settings['payment_modes']['payment_reference'] ?? 'Please use student ID as reference.') ?>
                        </p>
                    </div>
                 </div>
            </div>

            <!-- Policy Footer -->
            <div class="mt-12 flex justify-between items-end gap-12">
                <div class="flex-1">
                    <h4 class="text-[10px] font-black text-slate-900 uppercase tracking-widest mb-4">Institutional Protocol & Notes</h4>
                    <ol class="text-[10px] text-slate-500 font-bold space-y-2 list-decimal list-inside pl-2">
                        <?php foreach(($invoice_settings['notes'] ?? []) as $note): ?>
                            <li><?= htmlspecialchars($note) ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
                <div class="w-48 text-center border-t border-slate-300 pt-4">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Authorized Oversight</p>
                    <p class="text-[10px] font-black text-slate-900 mt-1">Institutional Seal</p>
                </div>
            </div>
            
            <div class="mt-20 text-center text-[8px] font-black text-slate-300 uppercase tracking-[0.6em]">
                Secure Document Generated by Salba Fiscal Module &middot; v9.5.0
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</body>
</html>
