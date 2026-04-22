<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
require_finance_access();

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { die('Invalid fiscal node access.'); }

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $amount = floatval($_POST['amount']);
    $expense_date = $_POST['expense_date'];
    $description = trim($_POST['description']);
    
    $stmt = $conn->prepare("UPDATE expenses SET category_id=?, amount=?, expense_date=?, description=? WHERE id=?");
    $stmt->bind_param('idssi', $category_id, $amount, $expense_date, $description, $id);
    if ($stmt->execute()) {
        $success = "Expenditure synchronization complete. Entry updated.";
    } else {
        $error = "Synchronization failure: " . $stmt->error;
    }
    $stmt->close();
}

$exp = $conn->query("SELECT * FROM expenses WHERE id=$id")->fetch_assoc();
if (!$exp) { die('Fiscal node not found.'); }

$cat_result = $conn->query("SELECT id, name FROM expense_categories ORDER BY name ASC");
$categories = [];
while($c = $cat_result->fetch_assoc()) $categories[] = $c;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revise Expenditure | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="text-slate-900 leading-relaxed">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 p-10 min-h-screen">
        <!-- Header -->
        <header class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[0.125rem] bg-rose-600"></span>
                    Expenditure Registry
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Revise <span class="text-rose-600">Outflow</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Updating institutional spending parameters for audit accuracy.</p>
            </div>
            <a href="view_expenses.php" class="bg-white border border-slate-200 text-slate-400 font-black text-[0.625rem] uppercase tracking-widest px-8 py-4 rounded-2xl hover:text-slate-600 hover:bg-slate-50 transition-all leading-none">
                <i class="fas fa-arrow-left mr-2"></i> Return to Ledger
            </a>
        </header>

        <div class="max-w-3xl">
            <?php if ($success): ?>
                <div class="mb-10 bg-emerald-50 border border-emerald-100 p-6 rounded-[2rem] flex items-center gap-4 text-emerald-800 animate-in fade-in slide-in-from-top-4 duration-500">
                    <div class="w-10 h-10 bg-emerald-500 text-white rounded-full flex items-center justify-center shadow-lg shadow-emerald-500/20">
                        <i class="fas fa-check"></i>
                    </div>
                    <p class="font-bold text-sm tracking-tight"><?= $success ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-10">
                <!-- Data Hub -->
                <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm relative overflow-hidden group">
                     <div class="absolute top-0 right-0 p-10 opacity-5 group-hover:scale-110 transition-transform duration-700">
                        <i class="fas fa-receipt text-8xl text-rose-600"></i>
                    </div>
                    <h3 class="text-[0.625rem] font-black text-slate-400 uppercase tracking-[0.3em] mb-10">Institutional Spending Node</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                        <div class="space-y-8">
                            <div>
                                <label class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest mb-3 block">Functional Category</label>
                                <select name="category_id" required class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-black text-slate-900 outline-none focus:ring-4 focus:ring-rose-500/10 appearance-none transition-all">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($exp['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest mb-3 block">Verified Amount (GHS)</label>
                                <input type="number" step="0.01" name="amount" value="<?= $exp['amount'] ?>" required class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-xl font-black text-slate-900 outline-none focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 transition-all">
                            </div>
                        </div>
                        <div class="space-y-8">
                            <div>
                                <label class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest mb-3 block">Transaction Date</label>
                                <input type="date" name="expense_date" value="<?= $exp['expense_date'] ?>" required class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-black text-slate-900 outline-none focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 transition-all">
                            </div>
                            <div>
                                <label class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest mb-3 block">Operational Logic / Notes</label>
                                <textarea name="description" rows="1" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-medium text-slate-600 outline-none focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 transition-all"><?= htmlspecialchars($exp['description']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Action Bar -->
                <div class="bg-slate-900 rounded-[2.5rem] p-10 text-white border border-slate-800 shadow-2xl flex flex-wrap items-center justify-between gap-6">
                    <div class="flex items-center gap-5">
                        <div class="w-14 h-14 bg-rose-600 rounded-2xl flex items-center justify-center text-white text-xl shadow-lg shadow-rose-600/20">
                            <i class="fas fa-shield-check"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-[0.2em] text-rose-400">Registry Sync Authorized</h4>
                            <p class="text-slate-500 text-[0.625rem] font-bold mt-1 uppercase leading-none italic">Recalibrating institutional spending ledger.</p>
                        </div>
                    </div>
                    <button type="submit" class="bg-rose-600 hover:bg-rose-500 text-white font-black text-[0.625rem] uppercase tracking-widest px-10 py-5 rounded-2xl transition-all shadow-xl shadow-rose-600/20 active:scale-95 leading-none h-fit">
                        Sync Expenditure Node
                    </button>
                </div>
            </form>
        </div>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[0.625rem] font-black text-slate-300 uppercase tracking-[0.5em]">
            Salba Montessori &middot; Institutional Audit Registry &middot; v9.5.0
        </footer>
    </main>
</body>
</html>
