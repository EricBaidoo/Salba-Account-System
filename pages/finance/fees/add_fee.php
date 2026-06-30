<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

$success = false;
$error = '';
$fee_details = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fee_type = $_POST['fee_type'] ?? '';
    $name = (isset($_POST['fee_name']) && $_POST['fee_name'] === 'custom') ? trim($_POST['custom_fee_name'] ?? '') : ($_POST['fee_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validate required fields
    if (empty($fee_type) || empty($name)) {
        $error = "Fee type and name are required fields.";
    } else {
        // Prevent duplicate fee names (case-insensitive)
        $dup_stmt = $conn->prepare("SELECT id FROM fees WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $dup_stmt->bind_param("s", $name);
        $dup_stmt->execute();
        $dup_stmt->store_result();
        if ($dup_stmt->num_rows > 0) {
            $error = "A fee with this name already exists. Please choose a different name.";
            $dup_stmt->close();
        } else {
            $dup_stmt->close();
            try {
                $conn->autocommit(false); // Start transaction
                // Determine amount for main fees w-full border-collapse
                $main_amount = null;
                if ($fee_type === 'fixed') {
                    $main_amount = floatval($_POST['fixed_amount'] ?? 0);
                    if ($main_amount <= 0) {
                        throw new Exception("Fixed amount must be greater than 0.");
                    }
                }
                // Insert main fee record
                $stmt = $conn->prepare("INSERT INTO fees (name, amount, fee_type, description) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sdss", $name, $main_amount, $fee_type, $description);
                if (!$stmt->execute()) {
                    throw new Exception("Error creating fee: " . $stmt->error);
                }
                $fee_id = $conn->insert_id;
                $stmt->close();
                // Handle class-based or category-based amounts
                if ($fee_type === 'class_based') {
                    $class_amounts = $_POST['class_amounts'] ?? [];
                    $stmt = $conn->prepare("INSERT INTO fee_amounts (fee_id, class_name, amount) VALUES (?, ?, ?)");
                    foreach ($class_amounts as $class_name => $amount) {
                        if (!empty($amount) && $amount > 0) {
                            $stmt->bind_param("isd", $fee_id, $class_name, $amount);
                            if (!$stmt->execute()) {
                                throw new Exception("Error setting class amount for $class_name: " . $stmt->error);
                            }
                            $fee_details[] = "$class_name: GHâ‚µ" . number_format($amount, 2);
                        }
                    }
                    $stmt->close();
                } elseif ($fee_type === 'category') {
                    $category_amounts = $_POST['category_amounts'] ?? [];
                    $stmt = $conn->prepare("INSERT INTO fee_amounts (fee_id, category, amount) VALUES (?, ?, ?)");
                    foreach ($category_amounts as $category => $amount) {
                        if (!empty($amount) && $amount > 0) {
                            $stmt->bind_param("isd", $fee_id, $category, $amount);
                            if (!$stmt->execute()) {
                                throw new Exception("Error setting category amount for $category: " . $stmt->error);
                            }
                            $fee_details[] = "$category: GHâ‚µ" . number_format($amount, 2);
                        }
                    }
                    $stmt->close();
                } else {
                    $fee_details[] = "Fixed Amount: GHâ‚µ" . number_format($main_amount, 2);
                }
                $conn->commit(); // Commit transaction
                $success = true;
            } catch (Exception $e) {
                $conn->rollback(); // Rollback on error
                $error = $e->getMessage();
            }
            $conn->autocommit(true); // Reset autocommit
        }
    }
}
            
// ...existing code...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Created | Finance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-900">

    <?php include '../../../includes/sidebar_admin_modern.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-10 min-h-screen flex items-start justify-center">
        <div class="w-full max-w-lg mt-10">

            <?php if ($success): ?>
            <!-- Success Card -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">

                <!-- Top accent bar -->
                <div class="h-1.5 bg-gradient-to-r from-emerald-400 to-teal-500"></div>

                <div class="p-8">
                    <!-- Icon + heading -->
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center shrink-0">
                            <i class="fas fa-circle-check text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-[0.5625rem] font-black text-emerald-600 uppercase tracking-widest mb-0.5">Success</p>
                            <h1 class="text-xl font-black text-slate-900">Fee Created</h1>
                        </div>
                    </div>

                    <!-- Fee summary -->
                    <div class="bg-slate-50 border border-slate-100 rounded-2xl px-6 py-5 mb-6">
                        <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-1">Fee Name</p>
                        <p class="text-lg font-black text-slate-900 mb-3"><?= htmlspecialchars($name) ?></p>

                        <span class="inline-flex items-center gap-1.5 bg-indigo-50 text-indigo-700 border border-indigo-100 text-[0.5625rem] font-black uppercase tracking-widest px-3 py-1 rounded-full">
                            <i class="fas fa-tag text-[0.5rem]"></i>
                            <?= ucfirst(str_replace('_', ' ', $fee_type)) ?> Fee
                        </span>

                        <?php if (!empty($fee_details)): ?>
                        <div class="mt-4 pt-4 border-t border-slate-200">
                            <p class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest mb-2">Fee Structure</p>
                            <ul class="space-y-2">
                                <?php foreach ($fee_details as $detail): ?>
                                <li class="flex items-center justify-between text-sm font-semibold text-slate-700">
                                    <span><?= htmlspecialchars($detail) ?></span>
                                    <i class="fas fa-check text-emerald-500 text-xs"></i>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($description)): ?>
                        <p class="mt-4 pt-4 border-t border-slate-200 text-xs text-slate-500">
                            <span class="font-bold">Note:</span> <?= htmlspecialchars($description) ?>
                        </p>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="assign_fee_form.php" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-widest px-5 py-3.5 rounded-2xl text-center transition-colors">
                            <i class="fas fa-user-tag mr-2"></i>Assign to Students
                        </a>
                        <a href="add_fee_form.php" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase tracking-widest px-5 py-3.5 rounded-2xl text-center transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add Another Fee
                        </a>
                    </div>

                    <a href="view_fees.php" class="block text-center mt-4 text-xs font-bold text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fas fa-list mr-1"></i> View All Fees
                    </a>
                </div>
            </div>

            <?php else: ?>
            <!-- Error Card -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">

                <div class="h-1.5 bg-gradient-to-r from-rose-400 to-pink-500"></div>

                <div class="p-8">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-14 h-14 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center shrink-0">
                            <i class="fas fa-circle-xmark text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-[0.5625rem] font-black text-rose-600 uppercase tracking-widest mb-0.5">Failed</p>
                            <h1 class="text-xl font-black text-slate-900">Could Not Create Fee</h1>
                        </div>
                    </div>

                    <div class="bg-rose-50 border border-rose-100 rounded-2xl px-5 py-4 mb-6 text-sm font-semibold text-rose-700">
                        <i class="fas fa-triangle-exclamation mr-2"></i><?= htmlspecialchars($error) ?>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="add_fee_form.php" class="flex-1 bg-slate-900 hover:bg-slate-700 text-white text-xs font-black uppercase tracking-widest px-5 py-3.5 rounded-2xl text-center transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Form
                        </a>
                        <a href="../dashboard.php" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase tracking-widest px-5 py-3.5 rounded-2xl text-center transition-colors">
                            <i class="fas fa-home mr-2"></i>Dashboard
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>
</body>
</html>

    </body>
</html>
