<?php
include '../../../includes/auth_functions.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
require_finance_access();

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { die('Invalid taxonomy node access.'); }

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        $stmt = $conn->prepare("UPDATE expense_categories SET name=? WHERE id=?");
        $stmt->bind_param('si', $name, $id);
        if ($stmt->execute()) {
            $success = "Taxonomy synchronized: Node '$name' updated.";
        } else {
            $error = "Synchronization failure: " . $stmt->error;
        }
        $stmt->close();
    }
}

$cat = $conn->query("SELECT * FROM expense_categories WHERE id=$id")->fetch_assoc();
if (!$cat) { die('Taxonomy node not found.'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revise Taxonomy Node | Salba Montessori</title>
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
                <a href="add_expense_category_form.php" class="hover:text-blue-600 transition-colors">Categories</a>
                <span>/</span>
                <span class="text-blue-600">Revise Classifier</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-edit text-rose-600"></i> Revise Classifier
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Re-labeling institutional spending classification nodes.</p>
                </div>
                <a href="add_expense_category_form.php" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all flex items-center gap-2">
                    <i class="fas fa-arrow-left text-slate-400"></i> Return to Registry
                </a>
            </div>
        </div>

        <div class="px-6 max-w-2xl">
            <?php if ($success): ?>
                <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm flex gap-3 items-center shadow-sm">
                    <i class="fas fa-check-circle"></i>
                    <span class="font-medium"><?= $success ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Entry Hub -->
                <section class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm relative overflow-hidden group">
                     <div class="absolute top-0 right-0 p-8 opacity-5 group-hover:scale-110 transition-transform duration-700">
                        <i class="fas fa-tags text-6xl text-rose-600"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2"><i class="fas fa-folder-tree text-slate-400"></i> Node Definition</h3>
                    
                    <div class="space-y-4 relative z-10">
                        <div>
                            <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Institutional Label</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($cat['name']) ?>" required class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2.5 text-sm font-bold text-slate-900 outline-none focus:ring-1 focus:ring-rose-500 focus:border-rose-500 transition-all">
                            <p class="text-xs text-slate-500 mt-2 font-medium italic">* This change will propagate to all legacy expenditure entries mapped to this node.</p>
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
                            <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Classifier Sync Authorized</h4>
                            <p class="text-slate-500 text-xs font-medium mt-0.5 italic">Recalibrating institutional spending taxonomy.</p>
                        </div>
                    </div>
                    <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white font-semibold text-sm px-6 py-2.5 rounded-lg shadow-sm transition-all flex items-center gap-2">
                        <i class="fas fa-save"></i> Sync Classifier Node
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
