<?php
include '../../../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
require_finance_access();
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO expense_categories (name) VALUES (?)");
        $stmt->bind_param('s', $name);
        if ($stmt->execute()) {
            $message = "Nexus established: Category '$name' registered.";
        } else {
            $message = "Sync error: " . htmlspecialchars($stmt->error);
        }
        $stmt->close();
    }
}
$categories = $conn->query("SELECT * FROM expense_categories ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Taxonomy | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .taxonomy-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .taxonomy-card:hover { transform: translateY(-0.25rem); border-color: #e2e8f0; box-shadow: 0 1.25rem 1.5625rem -0.3125rem rgba(0, 0, 0, 0.05); }
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
                <span class="text-blue-600">Categories</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-tags text-rose-600"></i> Expense Categories
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Managing the functional classification of institutional spending.</p>
                </div>
                <a href="view_expenses.php" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all flex items-center gap-2">
                    <i class="fas fa-arrow-left text-slate-400"></i> Return to Ledger
                </a>
            </div>
        </div>

        <div class="px-6 grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Left: Entry Hub -->
            <div class="lg:col-span-4">
                <section class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm sticky top-32">
                    <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2"><i class="fas fa-plus-circle text-slate-400"></i> Node Registration</h3>
                    
                    <?php if ($message): ?>
                        <div class="mb-6 bg-emerald-50 text-emerald-700 px-4 py-3 rounded-lg text-xs font-semibold flex items-center gap-2 shadow-sm border border-emerald-200">
                            <i class="fas fa-check-circle"></i> <?= $message ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Classification Label</label>
                            <input type="text" name="name" placeholder="e.g. INFRASTRUCTURE" required class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2.5 text-sm font-medium text-slate-900 outline-none focus:ring-1 focus:ring-rose-500 focus:border-rose-500 transition-all">
                        </div>
                        <button type="submit" class="w-full bg-rose-600 hover:bg-rose-700 text-white font-semibold text-sm py-2.5 rounded-lg shadow-sm transition-all flex justify-center items-center gap-2">
                            <i class="fas fa-save"></i> Register Category
                        </button>
                    </form>
                </section>
            </div>

            <!-- Right: Existing Taxonomy -->
            <div class="lg:col-span-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php if ($categories->num_rows > 0): 
                        while($row = $categories->fetch_assoc()): 
                    ?>
                        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm flex items-center justify-between group hover:border-slate-300 transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-slate-50 border border-slate-100 text-slate-400 rounded-lg flex items-center justify-center text-sm group-hover:bg-rose-50 group-hover:text-rose-500 group-hover:border-rose-100 transition-all">
                                    <i class="fas fa-folder-tree"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Index SMS-<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?></p>
                                    <h4 class="text-sm font-bold text-slate-800 tracking-tight"><?= htmlspecialchars($row['name']) ?></h4>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <a href="edit_expense_category_form.php?id=<?= $row['id'] ?>" class="w-8 h-8 rounded bg-slate-50 text-slate-400 border border-slate-200 hover:bg-slate-800 hover:text-white flex items-center justify-center transition-colors shadow-sm" title="Edit"><i class="fas fa-edit text-xs"></i></a>
                                <a href="delete_expense_category.php?id=<?= $row['id'] ?>" onclick="return confirm('DANGER: This Category taxonomy node will be expunged. Proceed?');" class="w-8 h-8 rounded bg-slate-50 text-rose-500 border border-slate-200 hover:bg-rose-600 hover:text-white flex items-center justify-center transition-colors shadow-sm" title="Delete"><i class="fas fa-trash text-xs"></i></a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-span-full py-16 text-center text-slate-400 text-sm">
                            <div class="w-16 h-16 bg-white border border-slate-200 rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm">
                                <i class="fas fa-folder-open text-slate-300 text-2xl"></i>
                            </div>
                            <p class="font-medium">Taxonomy Empty</p>
                            <p class="text-xs mt-1">No categories defined for this fiscal year.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
