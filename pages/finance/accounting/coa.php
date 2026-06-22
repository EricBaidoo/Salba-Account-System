<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_account') {
        $code = $conn->real_escape_string($_POST['account_code']);
        $name = $conn->real_escape_string($_POST['name']);
        $type = $conn->real_escape_string($_POST['type']);
        
        $conn->query("INSERT INTO accounts (account_code, name, type) VALUES ('$code', '$name', '$type')");
        if ($conn->error) {
            $_SESSION['error_msg'] = "Error: " . $conn->error;
        } else {
            $_SESSION['success_msg'] = "Account created successfully.";
        }
    }
    header("Location: coa.php");
    exit;
}

$accounts = $conn->query("SELECT * FROM accounts ORDER BY account_code ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chart of Accounts | SALBA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-[#F8FAFC] text-slate-900">
    <?php include '../../../includes/sidebar.php'; ?>
    <main class="admin-main-content lg:ml-72 min-h-screen">
        <div class="bg-white border-b border-gray-100 px-8 py-6">
            <div class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-3 uppercase tracking-wider">
                <a href="index.php" class="hover:text-indigo-600 transition-colors">Accounting</a>
                <span>/</span>
                <span class="text-indigo-600">Chart of Accounts</span>
            </div>
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight"><i class="fas fa-sitemap text-indigo-600"></i> Chart of Accounts</h1>
                    <p class="text-slate-500 mt-2 font-medium">Manage your standard accounting codes and categories.</p>
                </div>
                <button onclick="document.getElementById('addModal').classList.remove('hidden'); document.getElementById('addModal').classList.add('flex');" class="px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-sm"><i class="fas fa-plus"></i> New Account</button>
            </div>
        </div>

        <div class="p-8">
            <?php if(isset($_SESSION['success_msg'])): ?>
            <div class="mb-6 bg-emerald-50 text-emerald-700 px-4 py-3 rounded-xl flex gap-3 items-center"><i class="fas fa-check-circle"></i> <?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error_msg'])): ?>
            <div class="mb-6 bg-rose-50 text-rose-700 px-4 py-3 rounded-xl flex gap-3 items-center"><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-xs font-bold text-slate-500 uppercase tracking-wider">
                            <th class="px-6 py-4">Account Code</th>
                            <th class="px-6 py-4">Name</th>
                            <th class="px-6 py-4">Type</th>
                            <th class="px-6 py-4 text-center">System Lock</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php while($row = $accounts->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-black text-slate-700"><?= htmlspecialchars($row['account_code']) ?></td>
                            <td class="px-6 py-4 font-bold text-slate-900"><?= htmlspecialchars($row['name']) ?></td>
                            <td class="px-6 py-4">
                                <?php 
                                $bg = 'bg-slate-100 text-slate-700';
                                if ($row['type'] == 'asset') $bg = 'bg-blue-100 text-blue-700';
                                if ($row['type'] == 'liability') $bg = 'bg-rose-100 text-rose-700';
                                if ($row['type'] == 'equity') $bg = 'bg-purple-100 text-purple-700';
                                if ($row['type'] == 'revenue') $bg = 'bg-emerald-100 text-emerald-700';
                                if ($row['type'] == 'expense') $bg = 'bg-orange-100 text-orange-700';
                                ?>
                                <span class="px-2 py-1 rounded text-xs font-black uppercase tracking-widest <?= $bg ?>"><?= $row['type'] ?></span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if($row['is_system']): ?>
                                    <i class="fas fa-lock text-slate-300" title="Required by system automation"></i>
                                <?php else: ?>
                                    <i class="fas fa-unlock text-emerald-400"></i>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal -->
    <div id="addModal" class="fixed inset-0 bg-slate-900/50 hidden items-center justify-center z-50 p-4">
        <form method="POST" class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
            <input type="hidden" name="action" value="add_account">
            <h3 class="text-xl font-black text-slate-900 mb-6">Create Ledger Account</h3>
            
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Account Code</label>
                <input type="text" name="account_code" required placeholder="e.g. 5300" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-indigo-500 font-mono">
            </div>
            
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Account Name</label>
                <input type="text" name="name" required placeholder="e.g. Stationery Expense" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-indigo-500">
            </div>
            
            <div class="mb-8">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Type</label>
                <select name="type" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-indigo-500">
                    <option value="asset">Asset</option>
                    <option value="liability">Liability</option>
                    <option value="equity">Equity</option>
                    <option value="revenue">Revenue</option>
                    <option value="expense">Expense</option>
                </select>
            </div>
            
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden'); document.getElementById('addModal').classList.remove('flex');" class="px-5 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl">Cancel</button>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl">Save Account</button>
            </div>
        </form>
    </div>
</body>
</html>
