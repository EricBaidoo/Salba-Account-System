<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/semester_helpers.php';

if (!is_logged_in()) {
    header('Location: ../../../includes/login.php');
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
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body class="bg-[#F8FAFC] text-slate-900 min-h-screen">
    <?php include '../../../includes/sidebar_admin.php'; ?>
    
    <main class="ml-72 p-10">
        <!-- Breadcrumbs & Nav -->
        <nav class="flex items-center justify-between mb-12">
            <div class="flex items-center gap-4">
                <a href="../dashboard.php" class="w-10 h-10 rounded-full bg-white shadow-sm border border-slate-200 flex items-center justify-center text-slate-500 hover:text-emerald-600 transition-all">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Finance Hub</p>
                    <h4 class="text-sm font-bold text-slate-700">Billing & Invoicing Center</h4>
                </div>
            </div>
            <a href="generate_semester_bills.php" class="bg-emerald-600 px-6 py-2.5 rounded-xl shadow-lg shadow-emerald-500/20 text-sm font-bold text-white hover:bg-emerald-500 transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> Prepare New Bills
            </a>
        </nav>

        <!-- Main Header -->
        <header class="mb-12 relative">
            <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-[0.2em] mb-4">
                <span class="w-8 h-[2px] bg-indigo-600"></span>
                Invoicing Control Hub
            </div>
            <h1 class="text-4xl font-black text-slate-900 tracking-tight">Institutional <span class="text-indigo-600">Billing Center</span></h1>
            <p class="text-slate-500 mt-2 font-medium max-w-2xl">Manage student statements for <span class="text-slate-900 font-bold"><?= $current_semester ?> (<?= $acad_year ?>)</span>. Generate PDF batch-files or individual receipts.</p>
        </header>

        <?php if ($success_message): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-6 rounded-r-3xl shadow-sm mb-12 flex items-center gap-4">
                <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h4 class="text-sm font-black text-emerald-900 uppercase tracking-wide">Generation Successful</h4>
                    <p class="text-xs font-bold text-emerald-700"><?= htmlspecialchars($success_message) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            <!-- Batch Printing Console -->
            <div class="glass-card p-10 rounded-[3rem] shadow-sm relative overflow-hidden group">
                <div class="absolute -top-10 -right-10 w-40 h-40 bg-indigo-50 rounded-full opacity-50 group-hover:scale-110 transition-transform duration-700"></div>
                
                <div class="relative z-10">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-2xl shadow-xl shadow-indigo-600/20 mb-8">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <h3 class="text-2xl font-black text-slate-900 mb-2">Bulk Printing Console</h3>
                    <p class="text-slate-500 text-sm font-medium mb-10 leading-relaxed">Download a compressed ZIP archive of high-fidelity PDFs for entire classes or the global student population.</p>

                    <form method="GET" action="download_semester_bill.php" class="space-y-8">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-3">Target Semester</label>
                            <select name="semester" class="w-full bg-slate-50/50 border border-slate-200 rounded-2xl px-5 py-3.5 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all appearance-none" required>
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
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-3">Filter by Class</label>
                            <select name="class" class="w-full bg-slate-50/50 border border-slate-200 rounded-2xl px-5 py-3.5 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all appearance-none">
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

                        <button type="submit" class="w-full bg-indigo-600 text-white font-black text-xs uppercase tracking-[0.2em] py-5 rounded-2xl flex items-center justify-center gap-4 hover:bg-slate-900 transition-all shadow-xl shadow-indigo-500/10">
                            <i class="fas fa-download"></i> Generate Batch Statements
                        </button>
                    </form>
                </div>
            </div>

            <!-- Individual Extraction -->
            <div class="glass-card p-10 rounded-[3rem] shadow-sm relative overflow-hidden group">
                <div class="absolute -top-10 -right-10 w-40 h-40 bg-emerald-50 rounded-full opacity-50 group-hover:scale-110 transition-transform duration-700"></div>
                
                <div class="relative z-10">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-2xl shadow-xl shadow-emerald-600/20 mb-8">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3 class="text-2xl font-black text-slate-900 mb-2">Individual Statement</h3>
                    <p class="text-slate-500 text-sm font-medium mb-10 leading-relaxed">Extract a specific billing statement for a single student. Useful for re-issuing lost receipts or walk-in inquiries.</p>

                    <form method="GET" action="download_semester_bill.php" class="space-y-8">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-3">Target Semester</label>
                            <select name="semester" class="w-full bg-slate-50/50 border border-slate-200 rounded-2xl px-5 py-3.5 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all appearance-none" required>
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
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-3">Target Student</label>
                            <select name="student_id" class="w-full bg-slate-50/50 border border-slate-200 rounded-2xl px-5 py-3.5 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all appearance-none" required>
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

                        <button type="submit" class="w-full bg-emerald-600 text-white font-black text-xs uppercase tracking-[0.2em] py-5 rounded-2xl flex items-center justify-center gap-4 hover:bg-slate-900 transition-all shadow-xl shadow-emerald-500/10">
                            <i class="fas fa-print"></i> Generate Individual PDF
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- System Guidelines -->
        <div class="mt-20 p-8 rounded-[2rem] bg-indigo-50 border border-indigo-100 flex items-start gap-6">
            <div class="w-12 h-12 rounded-2xl bg-white text-indigo-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="fas fa-info-circle"></i>
            </div>
            <div>
                <h4 class="text-sm font-black text-indigo-900 uppercase tracking-widest mb-1">Billing Policy & PDF Logic</h4>
                <p class="text-[11px] text-indigo-700 font-bold leading-relaxed max-w-3xl">
                    The Batch Printing Console uses ZipArchive to package multiple statements. PDFs are rendered using a 400DPI-equivalent resolution for crystal-clear printing. Standard billing includes arrears carry-forward, currently active semester fees, and verified bank/MoMo payment details as defined in your institutional protocols.
                </p>
            </div>
        </div>
    </main>
</body>
</html>
