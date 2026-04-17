<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Get all terms that have assigned fees
$terms_query = "SELECT DISTINCT semester FROM student_fees WHERE status != 'cancelled' ORDER BY 
    CASE semester 
        WHEN 'First Semester' THEN 1
        WHEN 'Second Semester' THEN 2
        WHEN 'Third Semester' THEN 3
        ELSE 4
    END";
$terms_result = $conn->query($terms_query);

// Get all classes
$classes_result = $conn->query("SELECT DISTINCT name FROM classes ORDER BY name");

// Handle success message from process_semester_bills.php
$success_message = '';
if (isset($_GET['generated']) && $_GET['generated'] == 1) {
    $count = intval($_GET['count'] ?? 0);
    $skipped = intval($_GET['skipped'] ?? 0);
    $success_message = "Successfully generated bills! assigned fees to $count students.";
    if ($skipped > 0) {
        $success_message .= " ($skipped students skipped as they already had these fees assigned).";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Billing Center - Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased">
    <div class="max-w-6xl mx-auto mt-10 px-4">
        
        <!-- Navigation -->
        <div class="mb-6 flex items-center justify-between">
            <a href="../dashboard.php" class="text-slate-500 hover:text-emerald-600 transition-colors flex items-center gap-2 text-sm font-bold bg-white px-4 py-2 rounded-full shadow-sm border border-slate-100">
                <i class="fas fa-arrow-left"></i> Back to Finance Hub
            </a>
            <a href="generate_semester_bills.php" class="text-white bg-emerald-600 hover:bg-emerald-700 transition-colors flex items-center gap-2 text-sm font-bold px-5 py-2 rounded-full shadow-md">
                <i class="fas fa-plus"></i> Generate New Bills
            </a>
        </div>
        
        <!-- Header -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8 mb-8 text-center relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-emerald-400 to-teal-600"></div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight flex items-center justify-center gap-3 mb-2">
                <i class="fas fa-file-invoice text-emerald-500"></i> Semester Billing Center
            </h1>
            <p class="text-slate-500 text-sm font-medium">Access, review, and print generated bills for individual students or entire classes.</p>
        </div>

        <?php if ($success_message): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-4 rounded-r-xl shadow-sm mb-8 flex items-center gap-3">
                <i class="fas fa-check-circle text-2xl text-emerald-500"></i>
                <div class="font-bold text-sm"><?= htmlspecialchars($success_message) ?></div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            
            <!-- Bulk Bills Card -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                <div class="px-8 py-6 bg-gradient-to-br from-indigo-600 to-blue-700 border-b border-indigo-800 text-white">
                    <h5 class="text-lg font-black tracking-wide flex items-center gap-2">
                        <i class="fas fa-layer-group"></i> Print Bulk Bills
                    </h5>
                    <p class="text-indigo-100 text-xs mt-1 font-medium">Batch generate printable PDFs for entire classes</p>
                </div>
                <div class="p-8 flex-1 flex flex-col">
                    <form method="GET" action="semester_bill.php" target="_blank" class="flex-1 flex flex-col">
                        <div class="space-y-6 flex-1">
                            <div>
                                <label for="bulk_term" class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Target Semester <span class="text-red-500">*</span></label>
                                <select class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all appearance-none" id="bulk_term" name="semester" required>
                                    <option value="">Select Semester...</option>
                                    <?php 
                                    $terms_result->data_seek(0);
                                    while ($semester = $terms_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo htmlspecialchars($semester['semester']); ?>">
                                            <?php echo htmlspecialchars($semester['semester']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div>
                                <label for="bulk_class" class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Filter by Class</label>
                                <select class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all appearance-none" id="bulk_class" name="class">
                                    <option value="all">Every Active Class</option>
                                    <?php while ($class = $classes_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($class['name']); ?>">
                                            <?php echo htmlspecialchars($class['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="mt-8 w-full bg-indigo-600 text-white font-black text-sm uppercase tracking-widest py-4 rounded-xl hover:bg-indigo-700 transition-colors shadow-sm flex justify-center items-center gap-2">
                            <i class="fas fa-print"></i> Generate Batch PDFs
                        </button>
                    </form>
                </div>
            </div>

            <!-- Individual Bill Card -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                <div class="px-8 py-6 bg-gradient-to-br from-emerald-600 to-teal-700 border-b border-emerald-800 text-white">
                    <h5 class="text-lg font-black tracking-wide flex items-center gap-2">
                        <i class="fas fa-user-graduate"></i> Print Individual Bill
                    </h5>
                    <p class="text-emerald-100 text-xs mt-1 font-medium">Extract a specific billing statement for a single student</p>
                </div>
                <div class="p-8 flex-1 flex flex-col">
                    <form method="GET" action="semester_bill.php" target="_blank" class="flex-1 flex flex-col">
                        <div class="space-y-6 flex-1">
                            <div>
                                <label for="student_term" class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Target Semester <span class="text-red-500">*</span></label>
                                <select class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all appearance-none" id="student_term" name="semester" required>
                                    <option value="">Select Semester...</option>
                                    <?php 
                                    $terms_result->data_seek(0);
                                    while ($semester = $terms_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo htmlspecialchars($semester['semester']); ?>">
                                            <?php echo htmlspecialchars($semester['semester']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div>
                                <label for="student_id" class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Select Student <span class="text-red-500">*</span></label>
                                <select class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all appearance-none" id="student_id" name="student_id" required>
                                    <option value="">Select a student...</option>
                                    <?php
                                    $students_query = "SELECT id, first_name, last_name, class FROM students WHERE status = 'active' ORDER BY class, first_name, last_name";
                                    $students_result = $conn->query($students_query);
                                    while ($student = $students_result->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['class'] . ')'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="mt-8 w-full bg-emerald-600 text-white font-black text-sm uppercase tracking-widest py-4 rounded-xl hover:bg-emerald-700 transition-colors shadow-sm flex items-center justify-center gap-2">
                            <i class="fas fa-print"></i> Generate Single PDF
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="mt-8 bg-blue-50 border border-blue-100 rounded-2xl p-5 flex gap-4 items-start shadow-sm mb-12">
            <i class="fas fa-info-circle text-blue-500 mt-0.5 text-xl"></i>
            <div>
                <h4 class="text-sm font-bold text-blue-900 mb-1">Printing Configuration</h4>
                <p class="text-xs text-blue-700 font-medium leading-relaxed">
                    Bills open in a separate, print-ready window. Background images and logos will accurately load if you ensure your browser's "Print Background Graphics" option is enabled. The system reads dynamically from the live student ledger, meaning generated amounts are always chronologically accurate.
                </p>
            </div>
        </div>

    </div>
</body>
</html>
