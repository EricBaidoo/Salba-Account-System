<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'supervisor' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../login');
    exit;
}

$success = '';
$error = '';
$uid = $_SESSION['user_id'];

// Safe Migration: Ensure lesson_plans has all modern columns (MySQL 5.7+ compatible)
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$cols_to_check = [
    'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    'status' => "VARCHAR(20) DEFAULT 'pending' AFTER objectives",
    'supervisor_comments' => "TEXT NULL AFTER status",
    'supervisor_id' => "INT NULL AFTER supervisor_comments"
];
foreach ($cols_to_check as $col => $def) {
    if (!$conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'lesson_plans' AND COLUMN_NAME = '$col'")->fetch_row()[0]) {
        $conn->query("ALTER TABLE lesson_plans ADD COLUMN `$col` $def");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_plan'])) {
    $plan_id = intval($_POST['plan_id']);
    $status = $_POST['status']; // 'approved' or 'rejected'
    $comments = trim($_POST['comments']);
    
    if (in_array($status, ['approved', 'rejected'])) {
        $stmt = $conn->prepare("UPDATE lesson_plans SET status = ?, supervisor_comments = ?, supervisor_id = ? WHERE id = ?");
        $stmt->bind_param("ssii", $status, $comments, $uid, $plan_id);
        if ($stmt->execute()) {
            $success = "Lesson plan marked as " . strtoupper($status) . ".";
        } else {
            $error = "Failed to update lesson plan.";
        }
    }
}

// Fetch pending and reviewed plans
$pending_plans = $conn->query("
    SELECT l.*, s.name as subject_name, u.username, COALESCE(sp.full_name, u.username) as teacher_name
    FROM lesson_plans l 
    JOIN subjects s ON l.subject_id = s.id 
    JOIN users u ON l.teacher_id = u.id 
    LEFT JOIN staff_profiles sp ON u.staff_id = sp.id
    WHERE l.status = 'pending' 
    ORDER BY l.created_at ASC
");

$reviewed_plans = $conn->query("
    SELECT l.*, s.name as subject_name, u.username, COALESCE(sp.full_name, u.username) as teacher_name
    FROM lesson_plans l 
    JOIN subjects s ON l.subject_id = s.id 
    JOIN users u ON l.teacher_id = u.id 
    LEFT JOIN staff_profiles sp ON u.staff_id = sp.id
    WHERE l.status != 'pending' 
    ORDER BY l.updated_at DESC LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Lesson Plans - Supervisor Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/top_nav.php'; ?>

    <main class="min-h-screen p-4 md:p-8 pt-20 md:pt-24 relative">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3 mb-6">
            <i class="fas fa-file-signature text-green-500"></i> Supervisor's Approvals
        </h1>

        <?php if ($success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center gap-3 shadow-sm mb-6"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center gap-3 shadow-sm mb-6"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="space-y-8">
            <!-- Pending Queue -->
            <div>
                <h2 class="font-bold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-clock text-yellow-500"></i> Pending Review Queue (Principal / Headteacher / Supervisor)</h2>
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <?php if($pending_plans && $pending_plans->num_rows > 0): while($p = $pending_plans->fetch_assoc()): ?>
                        <div class="bg-white rounded-xl shadow-sm border border-yellow-200 p-6 flex flex-col justify-between">
                            <div>
                                <div class="flex justify-between items-start mb-4 border-b border-gray-100 pb-3">
                                    <div>
                                        <div class="text-sm font-bold text-gray-400 uppercase"><?= htmlspecialchars($p['class_name']) ?> | <?= htmlspecialchars($p['subject_name']) ?> (Week <?= $p['week_number'] ?>)</div>
                                        <h3 class="text-xl font-bold text-gray-900 leading-tight"><?= htmlspecialchars($p['topic']) ?></h3>
                                        <div class="flex items-center gap-3 mt-2">
                                            <a href="<?= BASE_URL ?>pages/teacher/print_lesson_plan?id=<?= $p['id'] ?>&view=html" target="_blank" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                                                <i class="fas fa-eye"></i> View Note
                                            </a>
                                            <a href="<?= BASE_URL ?>pages/teacher/print_lesson_plan?id=<?= $p['id'] ?>" target="_blank" class="text-xs font-bold text-red-600 hover:text-red-800 flex items-center gap-1 border-l border-gray-200 pl-3">
                                                <i class="fas fa-file-pdf"></i> Download PDF
                                            </a>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-bold bg-blue-50 text-blue-800 px-3 py-1 rounded-full"><i class="fas fa-chalkboard-user"></i> Tr. <?= htmlspecialchars($p['teacher_name']) ?></div>
                                        <div class="text-xs text-gray-400 mt-1"><?= date('M j, g:i a', strtotime($p['created_at'])) ?></div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <details class="group bg-gray-50 rounded-xl border border-gray-100 overflow-hidden">
                                        <summary class="flex items-center justify-between p-4 cursor-pointer hover:bg-white transition-all">
                                            <span class="text-xs font-black text-indigo-700 uppercase tracking-widest flex items-center gap-2">
                                                <i class="fas fa-file-invoice"></i> View Full Details
                                            </span>
                                            <i class="fas fa-chevron-down text-[10px] text-gray-400 group-open:rotate-180 transition-transform"></i>
                                        </summary>
                                        <div class="px-4 pb-4 space-y-4">
                                            <!-- Logistics -->
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[11px]">
                                                <div class="p-2 bg-white rounded border border-gray-100"><span class="block font-black text-gray-400 uppercase">Week Ending</span> <?= date('d M, Y', strtotime($p['week_ending'] ?? '')) ?></div>
                                                <div class="p-2 bg-white rounded border border-gray-100"><span class="block font-black text-gray-400 uppercase">Day</span> <?= htmlspecialchars($p['day_of_week'] ?? '-') ?></div>
                                                <div class="p-2 bg-white rounded border border-gray-100"><span class="block font-black text-gray-400 uppercase">Duration</span> <?= htmlspecialchars($p['duration'] ?? '-') ?></div>
                                                <div class="p-2 bg-white rounded border border-gray-100"><span class="block font-black text-gray-400 uppercase">Class Size</span> <?= htmlspecialchars($p['class_size'] ?? '-') ?></div>
                                            </div>
                                            <!-- Curriculum -->
                                            <div class="space-y-1 text-sm">
                                                <div class="font-bold text-gray-800 tracking-tight">Strand: <span class="bg-gray-200 px-1 rounded font-medium"><?= htmlspecialchars($p['strand'] ?? '-') ?></span></div>
                                                <div class="font-bold text-gray-800 tracking-tight">Sub-Strand: <span class="bg-gray-200 px-1 rounded font-medium"><?= htmlspecialchars($p['sub_strand'] ?? '-') ?></span></div>
                                                <div class="mt-2 text-[11px] text-gray-500 font-bold uppercase tracking-widest">Content Standard</div>
                                                <div class="bg-white p-2 border border-gray-100 rounded text-xs"><?= htmlspecialchars($p['content_standard'] ?? '-') ?></div>
                                                <div class="mt-2 text-[11px] text-gray-500 font-bold uppercase tracking-widest">Indicator</div>
                                                <div class="bg-white p-2 border border-gray-100 rounded text-xs"><?= htmlspecialchars($p['indicator'] ?? '-') ?></div>
                                            </div>
                                            <!-- Phases -->
                                            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden text-[11px]">
                                                <table class="w-full text-left border-collapse">
                                                    <tr class="bg-gray-100"><th class="p-2 border border-gray-200">Phase</th><th class="p-2 border border-gray-200 text-center">Duration</th><th class="p-2 border border-gray-200">Activities</th></tr>
                                                    <tr><td class="p-2 border border-gray-200 font-bold">Starter</td><td class="p-2 border border-gray-200 text-center"><?= htmlspecialchars($p['phase1_duration'] ?? '-') ?></td><td class="p-2 border border-gray-200"><?= htmlspecialchars($p['starter_activities'] ?? '-') ?></td></tr>
                                                    <tr><td class="p-2 border border-gray-200 font-bold">New Learning</td><td class="p-2 border border-gray-200 text-center"><?= htmlspecialchars($p['phase2_duration'] ?? '-') ?></td><td class="p-2 border border-gray-200"><?= htmlspecialchars($p['learning_activities'] ?? '-') ?></td></tr>
                                                    <tr><td class="p-2 border border-gray-200 font-bold">Reflection</td><td class="p-2 border border-gray-200 text-center"><?= htmlspecialchars($p['phase3_duration'] ?? '-') ?></td><td class="p-2 border border-gray-200"><?= htmlspecialchars($p['reflection_activities'] ?? '-') ?></td></tr>
                                                </table>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            </div>
                            
                            <form method="POST" class="bg-gray-50 border border-gray-200 p-4 rounded-lg flex flex-col gap-3">
                                <input type="hidden" name="review_plan" value="1">
                                <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                                
                                <textarea name="comments" rows="2" placeholder="Leave Principal's / Headteacher's / Supervisor's remarks for the teacher to see..." class="w-full px-3 py-2 border border-gray-300 rounded focus:border-green-500 text-sm"></textarea>
                                
                                <div class="flex gap-3">
                                    <button type="submit" name="status" value="approved" class="flex-1 bg-green-600 text-white font-bold py-2 rounded shadow hover:bg-green-700 transition">Approve Plan</button>
                                    <button type="submit" name="status" value="rejected" class="flex-1 bg-red-600 text-white font-bold py-2 rounded shadow hover:bg-red-700 transition">Reject / Request Rev.</button>
                                </div>
                            </form>
                        </div>
                    <?php endwhile; else: ?>
                        <div class="col-span-full bg-white p-12 text-center rounded-xl border border-gray-100 text-gray-400">
                            <i class="fas fa-check-double text-4xl mb-3 text-gray-300"></i>
                            <p class="font-medium">All caught up! No pending lesson plans to review.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent History -->
            <div>
                <h2 class="font-bold text-gray-800 mb-4 border-b border-gray-200 pb-2">Recently Reviewed</h2>
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 font-bold text-gray-600 border-b border-gray-200">
                            <tr>
                                <th class="py-3 px-4">Teacher</th>
                                <th class="py-3 px-4">Class & Subject</th>
                                <th class="py-3 px-4">Topic</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4">Your Remark</th>
                                <th class="py-3 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if($reviewed_plans && $reviewed_plans->num_rows > 0): while($rp = $reviewed_plans->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 px-4 font-semibold text-gray-800"><?= htmlspecialchars($rp['username']) ?></td>
                                    <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($rp['class_name']) ?> | <?= htmlspecialchars($rp['subject_name']) ?></td>
                                    <td class="py-3 px-4 font-medium text-gray-800"><?= htmlspecialchars($rp['topic']) ?></td>
                                    <td class="py-3 px-4 text-center">
                                        <?php if($rp['status'] === 'approved'): ?>
                                            <span class="text-xs font-bold text-green-700 bg-green-100 px-2 py-1 rounded">Approved</span>
                                        <?php else: ?>
                                            <span class="text-xs font-bold text-red-700 bg-red-100 px-2 py-1 rounded">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-gray-500 italic max-w-xs truncate"><?= htmlspecialchars($rp['supervisor_comments']) ?></td>
                                    <td class="py-3 px-4 text-right">
                                        <a href="<?= BASE_URL ?>pages/teacher/print_lesson_plan?id=<?= $rp['id'] ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-bold text-xs flex items-center gap-1 justify-end">
                                            <i class="fas fa-file-pdf"></i> View / PDF
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>

