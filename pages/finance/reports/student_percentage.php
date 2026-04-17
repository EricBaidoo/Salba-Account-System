<?php
include '../../../includes/auth_functions.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';
include '../../../includes/student_balance_functions.php';

if (!is_logged_in()) {
    header('Location: ../../../includes/login.php');
    exit;
}
require_finance_access();

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($student_id <= 0) {
    header('Location: student_balances.php');
    exit;
}

$student = getStudentBalance($conn, $student_id);
if (!$student) {
    header('Location: student_balances.php');
    exit;
}

$total_fees = (float)($student['total_fees'] ?? 0);
$total_payments = (float)($student['total_payments'] ?? 0);
$paid_percent = $total_fees > 0 ? min(100, ($total_payments / $total_fees) * 100) : ($total_payments > 0 ? 100 : 0);
$owing_percent = 100 - $paid_percent;
$arrears = max(0, $total_fees - $total_payments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Velocity Analysis | <?= htmlspecialchars($student['student_name']) ?></title>
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
                    Liquidity Pulse
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight italic">Velocity <span class="text-indigo-600">Analysis</span></h1>
                <div class="mt-3 flex items-center gap-3">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Target Student:</span>
                    <span class="text-xs font-black text-slate-700 uppercase"><?= htmlspecialchars($student['student_name']) ?></span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <a href="student_balances.php" class="bg-white border border-slate-200 text-slate-400 font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:text-slate-600 transition-all">
                    <i class="fas fa-arrow-left mr-2"></i> Global Ledger
                </a>
                <a href="student_balance_details.php?id=<?= $student_id ?>" class="bg-indigo-600 text-white font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 transition-all">
                    Full Audit Hub
                </a>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            <!-- Left: Visualization Hub -->
            <div class="lg:col-span-12 space-y-10">
                <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm overflow-hidden relative group">
                    <div class="absolute top-0 right-0 p-12 opacity-5 scale-150 text-indigo-600 group-hover:rotate-12 transition-transform duration-1000">
                        <i class="fas fa-chart-line text-9xl"></i>
                    </div>
                    
                    <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.3em] mb-12 flex items-center gap-3">
                        <i class="fas fa-gauge-high text-indigo-500"></i> Fulfillment Metrics
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-16">
                        <div class="space-y-2">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Institutional Liabilities</p>
                            <h4 class="text-3xl font-black text-slate-900 italic">₵<?= number_format($total_fees, 2) ?></h4>
                            <div class="flex items-center gap-2 mt-4">
                                <span class="w-2 h-2 rounded-full bg-slate-200"></span>
                                <span class="text-[9px] font-black text-slate-400 uppercase">Gross Commitment</span>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <p class="text-[10px] font-black text-emerald-500 uppercase tracking-widest">Realized Remittance</p>
                            <h4 class="text-3xl font-black text-slate-900 italic text-emerald-600">₵<?= number_format($total_payments, 2) ?></h4>
                            <div class="flex items-center gap-2 mt-4">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span class="text-[9px] font-black text-emerald-500 uppercase tracking-widest"><?= round($paid_percent, 1) ?>% fulfilled</span>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <p class="text-[10px] font-black text-rose-500 uppercase tracking-widest">Residual Arrears</p>
                            <h4 class="text-3xl font-black text-slate-900 italic text-rose-600">₵<?= number_format($arrears, 2) ?></h4>
                            <div class="flex items-center gap-2 mt-4">
                                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                <span class="text-[9px] font-black text-rose-500 uppercase tracking-widest"><?= round($owing_percent, 1) ?>% remaining</span>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Architecture -->
                    <div class="relative pt-4">
                        <div class="overflow-hidden h-8 mb-4 text-xs flex rounded-2xl bg-slate-100 border border-slate-200 p-1">
                            <div style="width:<?= $paid_percent ?>%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-600 rounded-xl transition-all duration-1000 ease-out relative group/bar">
                                <span class="text-[9px] font-black uppercase tracking-widest opacity-0 group-hover/bar:opacity-100 transition-opacity">Nexus Confirmed</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-widest px-2">
                            <span class="text-indigo-600">Cleared Flow</span>
                            <span class="text-slate-300">Absolute Neutral</span>
                            <span class="text-rose-500">Arrears Liability</span>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Lower Grid -->
            <div class="lg:col-span-5">
                <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm h-full">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-10 italic">Core Ledger</h3>
                    <div class="space-y-6">
                        <div class="flex justify-between items-center py-4 border-b border-slate-50 group hover:translate-x-2 transition-transform">
                            <span class="text-xs font-black text-slate-400 uppercase tracking-widest">Gross Assessment</span>
                            <span class="text-sm font-black text-slate-900 italic">₵<?= number_format($total_fees, 2) ?></span>
                        </div>
                        <div class="flex justify-between items-center py-4 border-b border-slate-50 group hover:translate-x-2 transition-transform">
                            <span class="text-xs font-black text-emerald-500 uppercase tracking-widest">Aggregate Receipts</span>
                            <span class="text-sm font-black text-emerald-600 italic">₵<?= number_format($total_payments, 2) ?></span>
                        </div>
                        <div class="flex justify-between items-center py-4 border-b border-slate-50 group hover:translate-x-2 transition-transform">
                            <span class="text-xs font-black text-rose-400 uppercase tracking-widest">Current Arrears</span>
                            <span class="text-sm font-black text-rose-600 italic">₵<?= number_format($arrears, 2) ?></span>
                        </div>
                        <div class="flex justify-between items-center py-4 border-b border-slate-50 group hover:translate-x-2 transition-transform">
                            <span class="text-xs font-black text-indigo-400 uppercase tracking-widest">Fulfillment Velocity</span>
                            <span class="text-sm font-black text-indigo-600 italic"><?= round($paid_percent, 2) ?>%</span>
                        </div>
                    </div>
                </section>
            </div>

            <div class="lg:col-span-7">
                <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm h-full overflow-hidden">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-10 flex items-center gap-3 italic">
                        <i class="fas fa-history text-indigo-500"></i> Remittance Audit
                    </h3>
                    
                    <div class="space-y-4 max-h-[400px] overflow-y-auto pr-4 custom-scrollbar">
                        <?php $payments = getStudentPaymentHistory($conn, $student_id); ?>
                        <?php if (!empty($payments)): ?>
                            <?php foreach ($payments as $p): ?>
                                <div class="p-6 rounded-3xl bg-slate-50 border border-slate-100 flex items-center justify-between hover:bg-slate-900 hover:text-white transition-all duration-300 group">
                                    <div>
                                        <p class="text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-1 group-hover:text-indigo-300"><?= date('M j, Y', strtotime($p['payment_date'])) ?></p>
                                        <h5 class="text-sm font-black italic">₵<?= number_format($p['amount'], 2) ?></h5>
                                        <p class="text-[9px] font-bold text-slate-400 uppercase mt-1 group-hover:text-slate-500">Receipt: <?= htmlspecialchars($p['receipt_no']) ?></p>
                                    </div>
                                    <div class="text-right max-w-[150px]">
                                        <p class="text-[9px] font-bold text-slate-400 italic line-clamp-2 group-hover:text-slate-300"><?= htmlspecialchars($p['description']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="py-20 text-center flex flex-col items-center">
                                <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-200 text-2xl mb-4">
                                    <i class="fas fa-ghost"></i>
                                </div>
                                <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest italic">No Institutional Receipts Registered</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">
            Salba Montessori &middot; Financial Intelligence Node &middot; v9.5.0
        </footer>
    </main>
</body>
</html>
