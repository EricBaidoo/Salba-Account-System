<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include_once '../../../includes/accounting_engine.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_voucher') {
    $date = $conn->real_escape_string($_POST['voucher_date']);
    $desc = $conn->real_escape_string($_POST['description']);
    $amt = (float)$_POST['amount'];
    $recipient = $conn->real_escape_string($_POST['recipient']);
    $user = $_SESSION['username'] ?? 'System';
    
    // Handle upload
    $receipt_path = NULL;
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
        $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
        $filename = 'PC_' . time() . '_' . rand(100,999) . '.' . $ext;
        $target = '../../../uploads/petty_cash/' . $filename;
        if (move_uploaded_file($_FILES['receipt']['tmp_name'], $target)) {
            $receipt_path = 'uploads/petty_cash/' . $filename;
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO petty_cash_vouchers (voucher_date, description, amount, recipient, receipt_path, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdsss", $date, $desc, $amt, $recipient, $receipt_path, $user);
    
    if ($stmt->execute()) {
        $vid = $conn->insert_id;
        
        // Accounting: Debit Operational Expense, Credit Petty Cash (1010)
        // Find default Operational Expense account (or use a fallback)
        $exp_acct = $conn->query("SELECT account_code FROM accounts WHERE type = 'expense' LIMIT 1")->fetch_assoc();
        $dr_code = $exp_acct ? $exp_acct['account_code'] : '5000'; // Fallback
        
        record_journal_entry($conn, $date, 'Petty Cash', $vid, "Petty Cash Voucher PC-" . str_pad($vid, 5, '0', STR_PAD_LEFT) . " ($recipient)", [
            ['account_code' => $dr_code, 'debit' => $amt, 'credit' => 0], // Expense
            ['account_code' => '1010', 'debit' => 0, 'credit' => $amt]   // Asset (Petty Cash)
        ]);
        
        $_SESSION['success_msg'] = "Petty Cash Voucher created successfully.";
        header("Location: index.php");
        exit;
    } else {
        $error = "Failed to create voucher: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Petty Cash | SALBA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <?php include '../../../includes/sidebar.php'; ?>
    <main class="admin-main-content lg:ml-72 min-h-screen pb-12">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <a href="index.php" class="hover:text-blue-600 transition-colors">Petty Cash</a>
                <span>/</span>
                <span class="text-blue-600">New Voucher</span>
            </div>
            <h1 class="text-2xl font-semibold text-slate-900 tracking-tight"><i class="fas fa-file-invoice-dollar text-amber-500"></i> Issue Disbursement</h1>
        </div>

        <div class="px-6 max-w-3xl">
            <?php if(isset($error)): ?>
            <div class="mb-6 bg-rose-50 text-rose-700 border border-rose-200 px-4 py-3 rounded-lg flex gap-3 items-center text-sm shadow-sm"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <input type="hidden" name="action" value="create_voucher">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Voucher Date</label>
                        <input type="date" name="voucher_date" value="<?= date('Y-m-d') ?>" required class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Disbursement Amount (₵)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required class="w-full bg-amber-50 border border-amber-300 text-amber-900 rounded-lg px-4 py-2 outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all font-semibold">
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Recipient Name</label>
                    <input type="text" name="recipient" required placeholder="Who is receiving the cash?" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all font-medium">
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Description / Purpose</label>
                    <textarea name="description" rows="3" required placeholder="What is the cash being used for?" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all font-medium"></textarea>
                </div>

                <div class="mb-8">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Attach Receipt (Optional)</label>
                    <input type="file" name="receipt" accept="image/*,.pdf" class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 font-medium text-slate-700 outline-none file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-[10px] text-slate-500 mt-2 italic font-medium">Supported formats: JPG, PNG, PDF. Max size 2MB.</p>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 pt-6">
                    <a href="index.php" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all">Cancel</a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 shadow-sm transition-all">Authorize & Disburse</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
