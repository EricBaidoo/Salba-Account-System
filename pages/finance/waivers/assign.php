<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign_scholarship') {
        $student_id = (int)$_POST['student_id'];
        $scholarship_id = (int)$_POST['scholarship_id'];
        
        // Prevent duplicate active assignments
        $check = $conn->query("SELECT id FROM student_scholarships WHERE student_id=$student_id AND scholarship_id=$scholarship_id AND status='active'");
        if ($check->num_rows > 0) {
            $_SESSION['error_msg'] = "Student already has this scholarship active.";
        } else {
            $conn->query("INSERT INTO student_scholarships (student_id, scholarship_id, status) VALUES ($student_id, $scholarship_id, 'active')");
            $_SESSION['success_msg'] = "Scholarship assigned successfully.";
        }
    } elseif ($_POST['action'] === 'revoke') {
        $id = (int)$_POST['assignment_id'];
        $conn->query("UPDATE student_scholarships SET status='revoked' WHERE id=$id");
        $_SESSION['success_msg'] = "Scholarship revoked.";
    }
    header("Location: assign.php");
    exit;
}

// Fetch active assignments
$assignments = $conn->query("
    SELECT ss.id, ss.assigned_date, s.name as scholarship_name, s.discount_type, s.discount_value,
           st.first_name, st.last_name, st.class
    FROM student_scholarships ss
    JOIN scholarships s ON ss.scholarship_id = s.id
    JOIN students st ON ss.student_id = st.id
    WHERE ss.status = 'active'
    ORDER BY st.class, st.first_name
");

// Fetch active scholarships for dropdown
$scholarships = $conn->query("SELECT id, name, discount_type, discount_value FROM scholarships WHERE status='active'");

// Fetch active students for dropdown
$students = $conn->query("SELECT id, first_name, last_name, class FROM students WHERE status='active' ORDER BY class, first_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Waivers | SALBA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <?php include '../../../includes/sidebar.php'; ?>
    <main class="admin-main-content lg:ml-72 min-h-screen">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-indigo-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <a href="index.php" class="hover:text-indigo-600 transition-colors">Waivers</a>
                <span>/</span>
                <span class="text-slate-600">Assign</span>
            </div>
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight"><i class="fas fa-user-check text-blue-600"></i> Assign Scholarships</h1>
                    <p class="text-slate-500 mt-1 text-sm">Link active waivers and scholarships to specific students.</p>
                </div>
                <button onclick="document.getElementById('assignModal').classList.remove('hidden')" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-all shadow-sm"><i class="fas fa-link mr-2"></i> New Assignment</button>
            </div>
        </div>

        <div class="p-6">
            <?php if(isset($_SESSION['success_msg'])): ?>
            <div class="mb-6 bg-emerald-50 text-emerald-700 px-4 py-3 rounded-lg text-sm flex gap-3 items-center border border-emerald-100"><i class="fas fa-check-circle"></i> <?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error_msg'])): ?>
            <div class="mb-6 bg-rose-50 text-rose-700 px-4 py-3 rounded-lg text-sm flex gap-3 items-center border border-rose-100"><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
            <?php endif; ?>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            <th class="px-6 py-4">Student</th>
                            <th class="px-6 py-4">Class</th>
                            <th class="px-6 py-4">Scholarship / Waiver</th>
                            <th class="px-6 py-4 text-center">Value</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if($assignments->num_rows > 0): while($row = $assignments->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 transition-colors group border-b border-slate-100 last:border-0">
                            <td class="px-6 py-4 font-semibold text-slate-900 text-sm"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                            <td class="px-6 py-4 text-slate-500 text-xs"><?= htmlspecialchars($row['class']) ?></td>
                            <td class="px-6 py-4">
                                <span class="font-medium text-blue-700 bg-blue-50 px-2.5 py-1 rounded-md text-xs border border-blue-100"><?= htmlspecialchars($row['scholarship_name']) ?></span>
                            </td>
                            <td class="px-6 py-4 text-center font-medium text-sm">
                                <?= $row['discount_type'] === 'percentage' ? "<span class='text-emerald-600'>{$row['discount_value']}%</span>" : "<span class='text-slate-700'>GHS {$row['discount_value']}</span>" ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" class="inline" onsubmit="return confirm('Revoke this scholarship? It will not apply to future bills.')">
                                    <input type="hidden" name="action" value="revoke">
                                    <input type="hidden" name="assignment_id" value="<?= $row['id'] ?>">
                                    <button class="px-3 py-1 bg-rose-50 border border-rose-200 text-rose-600 text-xs font-semibold rounded hover:bg-rose-100 transition-colors">Revoke</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500 font-medium">No active student scholarships found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal -->
    <div id="assignModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <form method="POST" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <input type="hidden" name="action" value="assign_scholarship">
            <h3 class="text-lg font-semibold text-slate-900 mb-5">Assign Waiver to Student</h3>
            
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-2">Student</label>
                <select name="student_id" required class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">Select a student...</option>
                    <?php while($s = $students->fetch_assoc()): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['first_name'].' '.$s['last_name'].' ('.$s['class'].')') ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="mb-6">
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-2">Scholarship / Waiver</label>
                <select name="scholarship_id" required class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">Select active waiver...</option>
                    <?php while($sc = $scholarships->fetch_assoc()): ?>
                        <option value="<?= $sc['id'] ?>"><?= htmlspecialchars($sc['name']) ?> - <?= $sc['discount_type']=='percentage' ? $sc['discount_value'].'%' : 'GHS '.$sc['discount_value'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="document.getElementById('assignModal').classList.add('hidden')" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 shadow-sm">Assign</button>
            </div>
        </form>
    </div>
</body>
</html>
