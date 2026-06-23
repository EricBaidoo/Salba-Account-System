<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

// Fetch Trial Balance
$tb_query = $conn->query("
    SELECT a.account_code, a.name, a.type,
           SUM(l.debit) as total_debit, SUM(l.credit) as total_credit
    FROM accounts a
    LEFT JOIN journal_lines l ON a.id = l.account_id
    GROUP BY a.id
    HAVING total_debit > 0 OR total_credit > 0
    ORDER BY a.account_code ASC
");

// Fetch Recent Journal Entries
$je_query = $conn->query("
    SELECT j.*, 
           (SELECT SUM(debit) FROM journal_lines WHERE journal_entry_id = j.id) as total_amount
    FROM journal_entries j
    ORDER BY j.created_at DESC
    LIMIT 50
");

$total_dr = 0;
$total_cr = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Ledger | SALBA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-[#F8FAFC] text-slate-900">
    <?php include '../../../includes/sidebar.php'; ?>
    <main class="admin-main-content lg:ml-72 min-h-screen pb-12">
        <div class="bg-white border-b border-gray-100 px-8 py-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3 uppercase tracking-wider no-print">
                <a href="../dashboard.php" class="hover:text-indigo-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <span class="text-indigo-600">Accounting Ledger</span>
            </div>
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight"><i class="fas fa-book-journal-whills text-indigo-600"></i> Double-Entry Ledger</h1>
                    <p class="text-slate-500 mt-2 font-medium">Real-time Trial Balance and General Journal records.</p>
                </div>
                <div class="flex gap-3">
                    <a href="coa.php" class="px-5 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition-all shadow-sm"><i class="fas fa-sitemap"></i> Chart of Accounts</a>
                    <a href="financials.php" class="px-5 py-2.5 bg-emerald-50 text-emerald-700 font-bold rounded-xl hover:bg-emerald-100 transition-all shadow-sm"><i class="fas fa-file-invoice-dollar"></i> Financial Statements</a>
                </div>
            </div>
        </div>

        <div class="p-8 grid grid-cols-1 xl:grid-cols-2 gap-8">
            
            <!-- Trial Balance -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                <div class="p-6 border-b border-slate-100 bg-slate-50">
                    <h2 class="text-lg font-black text-slate-900 uppercase tracking-widest"><i class="fas fa-scale-balanced text-indigo-500 mr-2"></i> Trial Balance</h2>
                </div>
                <div class="overflow-y-auto max-h-[600px]">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-xs font-bold text-slate-500 uppercase tracking-wider sticky top-0 shadow-sm">
                                <th class="px-6 py-3">Account</th>
                                <th class="px-6 py-3 text-right text-emerald-600">Debit</th>
                                <th class="px-6 py-3 text-right text-rose-600">Credit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php while($row = $tb_query->fetch_assoc()): 
                                $dr = (float)$row['total_debit'];
                                $cr = (float)$row['total_credit'];
                                
                                // Calculate net balance for trial balance
                                if ($dr > $cr) {
                                    $net_dr = $dr - $cr;
                                    $net_cr = 0;
                                } else {
                                    $net_dr = 0;
                                    $net_cr = $cr - $dr;
                                }
                                
                                $total_dr += $net_dr;
                                $total_cr += $net_cr;
                                
                                if ($net_dr == 0 && $net_cr == 0) continue;
                            ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-3">
                                    <div class="font-bold text-slate-800"><?= $row['account_code'] ?> - <?= htmlspecialchars($row['name']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-black uppercase tracking-widest"><?= $row['type'] ?></div>
                                </td>
                                <td class="px-6 py-3 text-right font-medium text-emerald-700"><?= $net_dr > 0 ? number_format($net_dr, 2) : '-' ?></td>
                                <td class="px-6 py-3 text-right font-medium text-rose-700"><?= $net_cr > 0 ? number_format($net_cr, 2) : '-' ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                            <tr>
                                <td class="px-6 py-4 font-black text-slate-900 uppercase tracking-widest text-right">Totals:</td>
                                <td class="px-6 py-4 text-right font-black text-emerald-700 underline decoration-double decoration-emerald-300"><?= number_format($total_dr, 2) ?></td>
                                <td class="px-6 py-4 text-right font-black text-rose-700 underline decoration-double decoration-rose-300"><?= number_format($total_cr, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Recent Journal Entries -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                <div class="p-6 border-b border-slate-100 bg-slate-50">
                    <h2 class="text-lg font-black text-slate-900 uppercase tracking-widest"><i class="fas fa-list-check text-indigo-500 mr-2"></i> General Journal</h2>
                </div>
                <div class="overflow-y-auto max-h-[600px]">
                    <table class="w-full text-left border-collapse">
                        <tbody class="divide-y divide-slate-100">
                            <?php if($je_query->num_rows > 0): while($je = $je_query->fetch_assoc()): 
                                // Fetch lines
                                $lines = $conn->query("SELECT l.*, a.account_code, a.name FROM journal_lines l JOIN accounts a ON l.account_id = a.id WHERE l.journal_entry_id = {$je['id']}");
                            ?>
                            <tr class="bg-white">
                                <td class="p-0">
                                    <div class="bg-slate-50 px-6 py-2 flex justify-between items-center border-b border-slate-100">
                                        <div class="font-black text-xs text-slate-500 uppercase tracking-widest">
                                            JE #<?= str_pad($je['id'], 5, '0', STR_PAD_LEFT) ?> &bull; <?= date('M d, Y', strtotime($je['entry_date'])) ?>
                                        </div>
                                        <div class="font-bold text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded">
                                            <?= htmlspecialchars($je['reference_type']) ?>
                                        </div>
                                    </div>
                                    <div class="px-6 py-3">
                                        <p class="font-bold text-slate-800 text-sm mb-3"><?= htmlspecialchars($je['description']) ?></p>
                                        <table class="w-full text-xs">
                                            <?php while($line = $lines->fetch_assoc()): ?>
                                            <tr>
                                                <td class="py-1 text-slate-600 font-medium <?= (float)$line['credit'] > 0 ? 'pl-6' : '' ?>"><?= $line['account_code'] ?> - <?= htmlspecialchars($line['name']) ?></td>
                                                <td class="py-1 text-right text-emerald-600 font-semibold w-24"><?= (float)$line['debit'] > 0 ? number_format($line['debit'], 2) : '' ?></td>
                                                <td class="py-1 text-right text-rose-600 font-semibold w-24"><?= (float)$line['credit'] > 0 ? number_format($line['credit'], 2) : '' ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td class="p-12 text-center text-slate-500 font-medium">No journal entries recorded yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
