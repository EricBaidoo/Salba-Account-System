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
<body class="text-slate-900 leading-relaxed">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 p-10 min-h-screen">
        <!-- Header -->
        <header class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[0.125rem] bg-rose-600"></span>
                    Taxonomy Node
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Expense <span class="text-rose-600">Categories</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Managing the functional classification of institutional spending.</p>
            </div>
            <a href="view_expenses.php" class="bg-white border border-slate-200 text-slate-400 font-black text-[0.625rem] uppercase tracking-widest px-8 py-4 rounded-2xl hover:text-slate-600 hover:bg-slate-50 transition-all leading-none">
                <i class="fas fa-arrow-left mr-2"></i> Return to Ledger
            </a>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            <!-- Left: Entry Hub -->
            <div class="lg:col-span-4">
                <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm sticky top-10">
                    <h3 class="text-[0.625rem] font-black text-slate-400 uppercase tracking-[0.3em] mb-8">Node Registration</h3>
                    
                    <?php if ($message): ?>
                        <div class="mb-8 bg-emerald-50 text-emerald-700 px-5 py-3 rounded-2xl text-[0.625rem] font-black uppercase tracking-widest border border-emerald-100 italic animate-in fade-in duration-500">
                            <?= $message ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <div>
                            <label class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest mb-2 block">Classification Label</label>
                            <input type="text" name="name" placeholder="e.g. INFRASTRUCTURE" required class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-black text-slate-900 outline-none focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 transition-all">
                        </div>
                        <button type="submit" class="w-full bg-rose-600 text-white font-black text-[0.625rem] uppercase tracking-widest px-6 py-4 rounded-2xl shadow-lg shadow-rose-600/20 hover:bg-rose-700 transition-all active:scale-95 leading-none">
                            Register Category
                        </button>
                    </form>
                </section>
            </div>

            <!-- Right: Existing Taxonomy -->
            <div class="lg:col-span-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php if ($categories->num_rows > 0): 
                        $i = 1;
                        while($row = $categories->fetch_assoc()): 
                    ?>
                        <div class="taxonomy-card bg-white rounded-[2.5rem] p-8 border border-slate-50 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4 group">
                            <div class="flex items-center gap-5">
                                <div class="w-12 h-12 bg-slate-50 text-slate-300 rounded-2xl flex items-center justify-center text-xs group-hover:bg-rose-50 group-hover:text-rose-400 transition-all">
                                    <i class="fas fa-folder-tree"></i>
                                </div>
                                <div>
                                    <p class="text-[0.625rem] font-black text-slate-300 uppercase tracking-[0.2em] mb-1">Index SMS-<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?></p>
                                    <h4 class="text-sm font-black text-slate-800 uppercase tracking-tight"><?= htmlspecialchars($row['name']) ?></h4>
                                </div>
                            </div>
                            <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <a href="edit_expense_category_form.php?id=<?= $row['id'] ?>" class="w-8 h-8 rounded-lg bg-slate-50 text-slate-400 hover:bg-slate-900 hover:text-white flex items-center justify-center transition-all"><i class="fas fa-edit text-[0.625rem]"></i></a>
                                <a href="delete_expense_category.php?id=<?= $row['id'] ?>" onclick="return confirm('DANGER: This Category taxonomy node will be expunged. Proceed?');" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-300 hover:bg-rose-600 hover:text-white flex items-center justify-center transition-all"><i class="fas fa-trash text-[0.625rem]"></i></a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-span-full py-20 text-center text-slate-300 italic text-sm">Taxonomy Empty &middot; No categories defined for this fiscal year.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[0.625rem] font-black text-slate-300 uppercase tracking-[0.5em]">
            Institutional Registry Node &middot; Salba Montessori &middot; v9.5.0
        </footer>
    </main>
</body>
</html>
