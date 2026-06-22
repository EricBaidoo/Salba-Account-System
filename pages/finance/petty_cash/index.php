<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

// Ensure Petty Cash directory for receipts exists
$upload_dir = '../../../uploads/petty_cash/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Fetch metrics
$total_disbursed = $conn->query("SELECT COALESCE(SUM(amount), 0) as t FROM petty_cash_vouchers")->fetch_assoc()['t'];
$month_disbursed = $conn->query("SELECT COALESCE(SUM(amount), 0) as t FROM petty_cash_vouchers WHERE MONTH(voucher_date) = MONTH(CURRENT_DATE()) AND YEAR(voucher_date) = YEAR(CURRENT_DATE())")->fetch_assoc()['t'];

// Fetch vouchers
$vouchers = $conn->query("SELECT * FROM petty_cash_vouchers ORDER BY voucher_date DESC, id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Petty Cash System | SALBA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <?php include '../../../includes/sidebar.php'; ?>
    <main class="admin-main-content lg:ml-72 min-h-screen">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <span class="text-slate-600">Petty Cash</span>
            </div>
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight"><i class="fas fa-wallet text-amber-500"></i> Petty Cash</h1>
                    <p class="text-slate-500 mt-1 text-sm">Track small, daily disbursements securely.</p>
                </div>
                <a href="create_voucher.php" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 shadow-sm transition-all"><i class="fas fa-plus mr-2"></i> New Voucher</a>
            </div>
        </div>

        <div class="px-6">
            <?php if(isset($_SESSION['success_msg'])): ?>
            <div class="mb-6 bg-emerald-50 border border-emerald-100 text-emerald-700 px-4 py-3 rounded-lg text-sm flex gap-3 items-center shadow-sm"><i class="fas fa-check-circle"></i> <?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-amber-50 p-5 rounded-xl border border-amber-200 shadow-sm flex flex-col justify-between">
                    <p class="text-[10px] font-semibold text-amber-700 uppercase tracking-wider mb-1">Disbursed This Month</p>
                    <h3 class="text-2xl font-bold text-amber-700">₵<?= number_format($month_disbursed, 2) ?></h3>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Total Historic Disbursed</p>
                    <h3 class="text-2xl font-bold text-slate-900">₵<?= number_format($total_disbursed, 2) ?></h3>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-12">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <h3 class="font-semibold text-slate-700 text-sm">Voucher Ledger</h3>
                </div>
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            <th class="px-6 py-4">Voucher ID</th>
                            <th class="px-6 py-4">Date</th>
                            <th class="px-6 py-4">Description</th>
                            <th class="px-6 py-4">Recipient</th>
                            <th class="px-6 py-4 text-right">Amount</th>
                            <th class="px-6 py-4 text-center">Receipt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php while($row = $vouchers->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 transition-colors group border-b border-slate-100 last:border-0">
                            <td class="px-6 py-4 font-semibold text-slate-500 text-xs uppercase">PC-<?= str_pad($row['id'], 5, '0', STR_PAD_LEFT) ?></td>
                            <td class="px-6 py-4 font-medium text-slate-900 text-sm"><?= date('M d, Y', strtotime($row['voucher_date'])) ?></td>
                            <td class="px-6 py-4 font-medium text-slate-700 text-sm"><?= htmlspecialchars($row['description']) ?></td>
                            <td class="px-6 py-4 font-medium text-slate-600 text-sm"><?= htmlspecialchars($row['recipient']) ?></td>
                            <td class="px-6 py-4 text-right font-semibold text-amber-600 text-sm">₵<?= number_format($row['amount'], 2) ?></td>
                            <td class="px-6 py-4 text-center">
                                <?php if($row['receipt_path']): ?>
                                <a href="<?= BASE_URL . $row['receipt_path'] ?>" target="_blank" class="text-blue-600 hover:text-blue-800" title="View Receipt"><i class="fas fa-file-invoice"></i></a>
                                <?php else: ?>
                                <span class="text-slate-300"><i class="fas fa-minus"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
