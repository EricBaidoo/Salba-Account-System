<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$id    = (int)($_GET['id'] ?? 0);
$error = '';
$name  = '';

if (!$id) {
    header('Location: view_fees.php');
    exit;
}

// Fetch fee name before deleting
$row = $conn->query("SELECT name FROM fees WHERE id = $id")->fetch_assoc();
if (!$row) {
    header('Location: view_fees.php');
    exit;
}
$name = $row['name'];

// Check if fee is assigned to any students
$in_use = (int)$conn->query("SELECT COUNT(*) as c FROM student_fees WHERE fee_id = $id")->fetch_assoc()['c'];

if ($in_use > 0) {
    $error = "Cannot delete \"" . htmlspecialchars($name) . "\" — it is assigned to $in_use student record(s). Remove those assignments first.";
} else {
    $conn->query("DELETE FROM fees WHERE id = $id");
    if ($conn->affected_rows > 0) {
        header('Location: view_fees.php?deleted=1&name=' . urlencode($name));
        exit;
    }
    $error = "Could not delete the fee. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Fee | Finance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-900">

    <?php include '../../../includes/sidebar_admin_modern.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-10 min-h-screen flex items-start justify-center">
        <div class="w-full max-w-lg mt-10">

            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="h-1.5 bg-gradient-to-r from-rose-400 to-pink-500"></div>
                <div class="p-8">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-14 h-14 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center shrink-0">
                            <i class="fas fa-triangle-exclamation text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-[0.5625rem] font-black text-rose-600 uppercase tracking-widest mb-0.5">Cannot Delete</p>
                            <h1 class="text-xl font-black text-slate-900"><?= htmlspecialchars($name) ?></h1>
                        </div>
                    </div>

                    <div class="bg-rose-50 border border-rose-100 rounded-2xl px-5 py-4 mb-6 text-sm font-semibold text-rose-700">
                        <i class="fas fa-circle-info mr-2"></i><?= $error ?>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="view_fees.php" class="flex-1 bg-slate-900 hover:bg-slate-700 text-white text-xs font-black uppercase tracking-widest px-5 py-3.5 rounded-2xl text-center transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Fees
                        </a>
                        <a href="view_assigned_fees.php" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase tracking-widest px-5 py-3.5 rounded-2xl text-center transition-colors">
                            <i class="fas fa-list mr-2"></i>View Assignments
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
