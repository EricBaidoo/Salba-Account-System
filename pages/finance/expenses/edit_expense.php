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
<body class="bg-slate-50 text-slate-900 min-h-screen">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 min-h-screen pb-12">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <a href="view_expenses.php" class="hover:text-blue-600 transition-colors">Institutional Expenses</a>
                <span>/</span>
                <span class="text-blue-600">Revise Outflow</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-edit text-rose-600"></i> Revise Outflow
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Updating institutional spending parameters for audit accuracy.</p>
                </div>
                <a href="view_expenses.php" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all flex items-center gap-2">
                    <i class="fas fa-arrow-left text-slate-400"></i> Return to Ledger
                </a>
            </div>
        </div>

        <div class="px-6 max-w-4xl">
            <?php if ($success): ?>
                <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm flex gap-3 items-center shadow-sm">
                    <i class="fas fa-check-circle"></i>
                    <span class="font-medium"><?= $success ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Data Hub -->
                <section class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm relative overflow-hidden group">
                     <div class="absolute top-0 right-0 p-8 opacity-5 group-hover:scale-110 transition-transform duration-700">
                        <i class="fas fa-receipt text-6xl text-rose-600"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2"><i class="fas fa-sliders-h text-slate-400"></i> Institutional Spending Node</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative z-10">
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Functional Category</label>
                                <select name="category_id" required class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2 text-sm font-medium text-slate-900 outline-none focus:ring-1 focus:ring-rose-500 focus:border-rose-500 appearance-none transition-all">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($exp['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Verified Amount (GHS)</label>
                                <input type="number" step="0.01" name="amount" value="<?= $exp['amount'] ?>" required class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2 text-sm font-bold text-slate-900 outline-none focus:ring-1 focus:ring-rose-500 focus:border-rose-500 transition-all">
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Transaction Date</label>
                                <input type="date" name="expense_date" value="<?= $exp['expense_date'] ?>" required class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2 text-sm font-medium text-slate-900 outline-none focus:ring-1 focus:ring-rose-500 focus:border-rose-500 transition-all">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Operational Logic / Notes</label>
                                <textarea name="description" rows="2" class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2 text-sm font-medium text-slate-900 outline-none focus:ring-1 focus:ring-rose-500 focus:border-rose-500 transition-all"><?= htmlspecialchars($exp['description']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Action Bar -->
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-6 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-white border border-slate-200 rounded-lg flex items-center justify-center text-rose-500 text-lg shadow-sm">
                            <i class="fas fa-shield-check"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Registry Sync Authorized</h4>
                            <p class="text-slate-500 text-xs font-medium mt-0.5 italic">Recalibrating institutional spending ledger.</p>
                        </div>
                    </div>
                    <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white font-semibold text-sm px-6 py-2.5 rounded-lg shadow-sm transition-all flex items-center gap-2">
                        <i class="fas fa-save"></i> Sync Expenditure Node
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
