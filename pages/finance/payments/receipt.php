<?php
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

$payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;
if ($payment_id <= 0) die('Invalid Access Descriptor.');

$sql = "SELECT p.*, s.first_name, s.last_name, s.class 
        FROM payments p 
        LEFT JOIN students s ON p.student_id = s.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $payment_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
if (!$payment) die('Transaction Record Not Found.');

$allocations = [];
$alloc_sql = "SELECT pa.*, f.name AS fee_name, sf.semester AS sf_term, sf.academic_year AS sf_academic_year
             FROM payment_allocations pa
             LEFT JOIN student_fees sf ON pa.student_fee_id = sf.id
             LEFT JOIN fees f ON sf.fee_id = f.id
             WHERE pa.payment_id = ?";
$a_stmt = $conn->prepare($alloc_sql);
$a_stmt->bind_param('i', $payment_id);
$a_stmt->execute();
$a_res = $a_stmt->get_result();
while ($row = $a_res->fetch_assoc()) $allocations[] = $row;

$student_id = $payment['student_id'];
$outstanding = 0;
if ($student_id) {
    $fees_sql = "SELECT COALESCE(SUM(amount),0) as total_due FROM student_fees WHERE student_id = ? AND status != 'cancelled'";
    $f_stmt = $conn->prepare($fees_sql);
    $f_stmt->bind_param('i', $student_id);
    $f_stmt->execute();
    $total_due = $f_stmt->get_result()->fetch_assoc()['total_due'];
    
    $paid_sql = "SELECT COALESCE(SUM(amount),0) as total_paid FROM payments WHERE student_id = ?";
    $p_stmt = $conn->prepare($paid_sql);
    $p_stmt->bind_param('i', $student_id);
    $p_stmt->execute();
    $total_all_paid = $p_stmt->get_result()->fetch_assoc()['total_paid'];
    $outstanding = max(0, $total_due - $total_all_paid);
}

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Receipt | #<?= $payment['receipt_no'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .receipt-card { 
            background: white; 
            max-width: 148mm; 
            min-height: 210mm; 
            margin: 40px auto; 
            padding: 40px; 
            border-radius: 2rem; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.08); 
            border: 1px solid #f1f5f9;
            position: relative;
            overflow: hidden;
        }
        .header-bg {
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 160px;
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            z-index: 0;
        }
        .content { position: relative; z-index: 1; }
        @media print {
            body { background: white; padding: 0; margin: 0; }
            .receipt-card { box-shadow: none; margin: 0; max-width: 100%; border-radius: 0; border: none; padding: 20px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="text-slate-900 antialiased">
    <div class="no-print bg-slate-900 text-white py-4 px-8 flex justify-between items-center sticky top-0 z-50">
        <div class="flex items-center gap-4">
            <a href="view_payments.php" class="text-slate-400 hover:text-white transition-all text-sm font-bold flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Payment Ledger
            </a>
        </div>
        <button onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-500 text-white font-black text-[10px] uppercase tracking-widest px-6 py-3 rounded-xl transition-all shadow-lg shadow-emerald-600/20 leading-none">
            <i class="fas fa-print mr-2"></i> Release Receipt
        </button>
    </div>

    <div class="receipt-card">
        <div class="header-bg no-print"></div>
        <div class="content">
            <!-- Header -->
            <div class="flex justify-between items-center mb-12">
                 <div class="flex items-center gap-4">
                    <img src="../../../img/salba_logo.jpg" alt="Logo" class="w-16 h-16 rounded-2xl border-4 border-white shadow-xl">
                    <div class="print:text-slate-900 text-white">
                        <h1 class="text-xl font-black tracking-tighter leading-none mb-1">SALBA MONTESSORI</h1>
                        <p class="text-[9px] font-black uppercase tracking-widest opacity-80">Official Fee Receipt</p>
                    </div>
                 </div>
                 <div class="text-right">
                    <div class="bg-white/10 print:bg-slate-100 px-4 py-2 rounded-xl border border-white/20 print:border-slate-200 backdrop-blur-md">
                        <p class="text-[8px] font-black text-white/60 print:text-slate-400 uppercase tracking-widest leading-none mb-1">Receipt Number</p>
                        <p class="text-base font-black text-white print:text-slate-900 leading-none"><?= htmlspecialchars($payment['receipt_no']) ?></p>
                    </div>
                 </div>
            </div>

            <!-- Student Snapshot -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12 bg-slate-50 p-6 rounded-[2rem] border border-slate-100">
                <div>
                    <h6 class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Remitter Details</h6>
                    <p class="text-sm font-black text-slate-900 leading-none mb-1"><?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?></p>
                    <p class="text-[9px] font-bold text-emerald-600 uppercase tracking-tight"><?= htmlspecialchars($payment['class']) ?></p>
                </div>
                <div class="text-right">
                    <h6 class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Date Frequency</h6>
                    <p class="text-sm font-black text-slate-900 leading-none"><?= date('M j, Y', strtotime($payment['payment_date'])) ?></p>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1">TS: <?= date('H:i', strtotime($payment['payment_date'])) ?></p>
                </div>
            </div>

            <div class="mb-4">
                 <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-4 flex items-center gap-3">
                    Allocation Breakdown <span class="flex-1 h-[1px] bg-slate-100"></span>
                </h3>
                <table class="w-full">
                    <thead>
                        <tr class="text-[9px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                            <th class="pb-3 text-left">Fee classification</th>
                            <th class="pb-3 text-center">Semester / Year</th>
                            <th class="pb-3 text-right">Value (GHS)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (!empty($allocations)): ?>
                            <?php foreach ($allocations as $alloc): ?>
                                <tr>
                                    <td class="py-4 text-xs font-bold text-slate-700"><?= htmlspecialchars($alloc['fee_name']) ?></td>
                                    <td class="py-4 text-center text-[9px] font-black text-slate-400">
                                        <?= htmlspecialchars($alloc['sf_term']) ?> &middot; <?= htmlspecialchars(formatAcademicYearDisplay($conn, $alloc['sf_academic_year'])) ?>
                                    </td>
                                    <td class="py-4 text-right font-black text-slate-900"><?= number_format($alloc['amount'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td class="py-4 text-xs font-bold text-slate-700"><?= htmlspecialchars($payment['description'] ?: 'Direct Fee Settlement') ?></td>
                                <td class="py-4 text-center text-[9px] font-black text-slate-400">
                                    <?= htmlspecialchars($payment['semester']) ?> &middot; <?= htmlspecialchars(formatAcademicYearDisplay($conn, $payment['academic_year'])) ?>
                                </td>
                                <td class="py-4 text-right font-black text-slate-900"><?= number_format($payment['amount'], 2) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Ledger Summary -->
            <div class="mt-8 space-y-3">
                <div class="flex justify-between items-center py-4 px-6 bg-slate-900 text-white rounded-2xl shadow-xl shadow-emerald-900/10">
                    <span class="text-xs font-black uppercase tracking-widest">Aggregate Remittance</span>
                    <span class="text-xl font-black">GHS <?= number_format($payment['amount'], 2) ?></span>
                </div>
                <?php if ($student_id): ?>
                    <div class="flex justify-between items-center py-4 px-6 bg-rose-50 border border-rose-100 text-rose-600 rounded-2xl">
                         <div class="flex items-center gap-3">
                            <i class="fas fa-triangle-exclamation text-xs"></i>
                            <span class="text-[10px] font-black uppercase tracking-widest">Residual Liability</span>
                         </div>
                         <span class="text-sm font-black">GHS <?= number_format($outstanding, 2) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer Meta -->
            <div class="mt-16 flex justify-between items-end">
                <div class="w-40 border-b border-slate-200 pb-2">
                    <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Accounts Dept.</p>
                    <p class="text-[9px] font-bold text-slate-300 italic">Signature/Stamp</p>
                </div>
                <div class="text-right">
                    <p class="text-[8px] font-black text-slate-300 uppercase tracking-[0.4em] mb-2">Automated Audit Record</p>
                    <div class="w-24 h-24 bg-slate-50 rounded-2xl border-2 border-slate-100 flex items-center justify-center ml-auto">
                         <i class="fas fa-qrcode text-slate-200 text-4xl"></i>
                    </div>
                </div>
            </div>

            <p class="mt-8 text-center text-[8px] font-black text-slate-300 uppercase tracking-[0.5em]">
                Surge Illuminare &middot; Institutional Record v9.5.0
            </p>
        </div>
    </div>
</body>
</html>
