<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/semester_helpers.php';

if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
require_finance_access();

// Get current session context
$current_semester = getCurrentSemester($conn);
$acad_year = getAcademicYear($conn);

// Get all semesters that have assigned fees
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

// Handle success message
$success_message = '';
if (isset($_GET['generated']) && $_GET['generated'] == 1) {
    $count = intval($_GET['count'] ?? 0);
    $skipped = intval($_GET['skipped'] ?? 0);
    $success_message = "Successfully initialized billing targets for $count students.";
    if ($skipped > 0) {
        $success_message .= " ($skipped records already active and were preserved).";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Hub | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(0.75rem);
            border: 0.0625rem solid rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body class="bg-[#F8FAFC] text-slate-900 min-h-screen">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <main class="admin-main-content lg:ml-72 min-h-screen pb-12">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <span class="text-blue-600">Billing Center</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-file-invoice text-indigo-600"></i> Institutional Billing Center
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Manage student statements for <span class="text-slate-900 font-semibold"><?= $current_semester ?> (<?= $acad_year ?>)</span>.</p>
                </div>
                <a href="generate_semester_bills.php" class="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 shadow-sm transition-all flex items-center gap-2">
                    <i class="fas fa-plus"></i> Prepare New Bills
                </a>
            </div>
        </div>

        <div class="px-6">

        <?php if ($success_message): ?>
            <div class="mb-6 bg-emerald-50 border border-emerald-100 text-emerald-700 px-4 py-3 rounded-lg text-sm flex gap-3 items-center shadow-sm">
                <i class="fas fa-check-circle"></i> <span><?= htmlspecialchars($success_message) ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Batch Printing Console -->
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm relative overflow-hidden group">
                <div class="relative z-10">
                    <div class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg mb-4 border border-indigo-100">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-1">Bulk Printing Console</h3>
                    <p class="text-slate-500 text-sm mb-6">Download a compressed ZIP archive of PDFs for entire classes or the global student population.</p>

                    <form method="GET" action="download_semester_bill.php" class="space-y-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Target Semester</label>
                            <select name="semester" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium appearance-none" required>
                                <option value="">Select Target...</option>
                                <?php 
                                $terms_result->data_seek(0);
                                while ($semester = $terms_result->fetch_assoc()): 
                                ?>
                                    <option value="<?= htmlspecialchars($semester['semester']) ?>" <?= $semester['semester'] === $current_semester ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($semester['semester']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Filter by Class</label>
                            <select name="class" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium appearance-none">
                                <option value="all">Global Population (All Classes)</option>
                                <?php 
                                $classes_result->data_seek(0);
                                while ($class = $classes_result->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($class['name']) ?>">
                                        <?= htmlspecialchars($class['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <button type="submit" class="w-full bg-indigo-600 text-white font-medium text-sm py-2.5 rounded-lg flex items-center justify-center gap-2 hover:bg-indigo-700 transition-all shadow-sm">
                            <i class="fas fa-download"></i> Generate Batch Statements
                        </button>
                    </form>
                </div>
            </div>

            <!-- Individual Extraction -->
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm relative overflow-hidden group">
                <div class="relative z-10">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg mb-4 border border-emerald-100">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-1">Individual Statement</h3>
                    <p class="text-slate-500 text-sm mb-6">Extract a specific billing statement for a single student. Useful for re-issuing lost receipts.</p>

                    <form method="GET" action="download_semester_bill.php" class="space-y-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Target Semester</label>
                            <select name="semester" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all font-medium appearance-none" required>
                                <option value="">Select Target...</option>
                                <?php 
                                $terms_result->data_seek(0);
                                while ($semester = $terms_result->fetch_assoc()): 
                                ?>
                                    <option value="<?= htmlspecialchars($semester['semester']) ?>" <?= $semester['semester'] === $current_semester ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($semester['semester']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Target Student</label>
                            <select name="student_id" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all font-medium appearance-none" required>
                                <option value="">Select a student...</option>
                                <?php
                                $students_query = "SELECT id, first_name, last_name, class FROM students WHERE status = 'active' ORDER BY class, first_name, last_name";
                                $students_result = $conn->query($students_query);
                                while ($student = $students_result->fetch_assoc()):
                                ?>
                                    <option value="<?= $student['id'] ?>">
                                        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['class'] . ')') ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <button type="submit" class="w-full bg-emerald-600 text-white font-medium text-sm py-2.5 rounded-lg flex items-center justify-center gap-2 hover:bg-emerald-700 transition-all shadow-sm">
                            <i class="fas fa-print"></i> Generate Individual PDF
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- System Guidelines -->
        <div class="mt-8 p-6 rounded-xl bg-blue-50 border border-blue-100 flex items-start gap-4">
            <div class="text-blue-600 mt-0.5">
                <i class="fas fa-info-circle"></i>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-blue-900 mb-1">Billing Policy & PDF Logic</h4>
                <p class="text-xs text-blue-800 leading-relaxed">
                    The Batch Printing Console uses ZipArchive to package multiple statements. PDFs are rendered using a 400DPI-equivalent resolution for crystal-clear printing. Standard billing includes arrears carry-forward, currently active semester fees, and verified bank/MoMo payment details as defined in your institutional protocols.
                </p>
            </div>
        </div>
        </div>
    </main>
</body>
</html>
