<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

// Ensure history table exists
$conn->query("
CREATE TABLE IF NOT EXISTS student_promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    from_class VARCHAR(100) NOT NULL,
    to_class VARCHAR(100) NOT NULL,
    academic_year VARCHAR(50) NOT NULL,
    promoted_by INT NOT NULL,
    promotion_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_students'])) {
    $from_class = $conn->real_escape_string($_POST['from_class']);
    $to_class = $conn->real_escape_string($_POST['to_class']);
    $academic_year = $conn->real_escape_string($_POST['academic_year']);
    $student_ids = $_POST['student_ids'] ?? [];
    $promoted_by = $_SESSION['user_id'] ?? 0;

    if (empty($from_class) || empty($to_class) || empty($academic_year) || empty($student_ids)) {
        $error_message = "Please select all required fields and at least one student.";
    } else {
        $conn->begin_transaction();
        try {
            $update_stmt = $conn->prepare("UPDATE students SET class = ? WHERE id = ?");
            $history_stmt = $conn->prepare("INSERT INTO student_promotions (student_id, from_class, to_class, academic_year, promoted_by) VALUES (?, ?, ?, ?, ?)");
            
            $count = 0;
            foreach ($student_ids as $sid) {
                $sid = (int)$sid;
                // Update class
                $update_stmt->bind_param("si", $to_class, $sid);
                $update_stmt->execute();
                
                // Insert history
                $history_stmt->bind_param("isssi", $sid, $from_class, $to_class, $academic_year, $promoted_by);
                $history_stmt->execute();
                $count++;
            }
            
            // Log to system audit
            if (function_exists('logSystemEvent')) { // Assumed function from system audit logs
                $conn->query("INSERT INTO system_audit_logs (user_id, action, target_table, record_id, details) VALUES ($promoted_by, 'PROMOTE_STUDENTS', 'students', 0, 'Promoted $count students from $from_class to $to_class for $academic_year')");
            }

            $conn->commit();
            $success_message = "Successfully promoted $count students to $to_class.";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "An error occurred during promotion: " . $e->getMessage();
        }
    }
}

// Fetch classes for dropdowns
$classes = [];
$class_res = $conn->query("SELECT name FROM classes ORDER BY id ASC");
if ($class_res) {
    while ($r = $class_res->fetch_assoc()) {
        $classes[] = $r['name'];
    }
}

$cy = getAcademicYear($conn);
$formatted_cy = formatAcademicYearDisplay($conn, $cy);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promote Learners - SALBA Management Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">
        <div class="bg-white border-b border-gray-100 px-8 py-6 sticky top-0 z-40">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                        <i class="fas fa-arrow-trend-up text-indigo-600"></i> Promote Learners
                    </h1>
                    <p class="text-gray-500 mt-2 text-sm">
                        Move students from one class to the next after the academic year ends.
                    </p>
                </div>
            </div>
        </div>

        <div class="p-4 md:p-8">
            <?php if ($success_message): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg flex items-center gap-3 mb-6 shadow-sm">
                    <i class="fas fa-check-circle text-emerald-500"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-3 mb-6 shadow-sm">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6 p-6">
                <form id="promotionForm" method="POST" action="">
                    <input type="hidden" name="promote_students" value="1">
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Academic Year</label>
                            <input type="text" name="academic_year" value="<?php echo htmlspecialchars($formatted_cy); ?>" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">From Class</label>
                            <select id="from_class" name="from_class" required onchange="fetchStudents()"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">-- Select Current Class --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">To Class</label>
                            <select name="to_class" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">-- Select Next Class --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="studentsContainer" class="hidden">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Select Students to Promote</h3>
                            <button type="button" onclick="toggleAll()" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">Select All / None</button>
                        </div>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider w-16">Select</th>
                                        <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Student ID</th>
                                        <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                                    </tr>
                                </thead>
                                <tbody id="studentsTableBody" class="divide-y divide-gray-100 bg-white">
                                    <!-- Students will be loaded here via JS -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg hover:bg-indigo-700 transition font-medium shadow-sm flex items-center gap-2">
                                <i class="fas fa-check"></i> Promote Selected Students
                            </button>
                        </div>
                    </div>
                    
                    <div id="noStudentsMsg" class="hidden p-8 text-center text-gray-500 border border-dashed border-gray-300 rounded-lg">
                        <i class="fas fa-info-circle text-gray-400 text-2xl mb-2"></i>
                        <p>No active students found in the selected class.</p>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function toggleAll() {
            const checkboxes = document.querySelectorAll('input[name="student_ids[]"]');
            let allChecked = true;
            checkboxes.forEach(cb => {
                if (!cb.checked) allChecked = false;
            });
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
        }

        async function fetchStudents() {
            const fromClass = document.getElementById('from_class').value;
            const container = document.getElementById('studentsContainer');
            const noStudentsMsg = document.getElementById('noStudentsMsg');
            const tbody = document.getElementById('studentsTableBody');
            
            if (!fromClass) {
                container.classList.add('hidden');
                noStudentsMsg.classList.add('hidden');
                return;
            }

            try {
                const response = await fetch(`get_students_by_class.php?class=${encodeURIComponent(fromClass)}`);
                const data = await response.json();
                
                tbody.innerHTML = '';
                
                if (data.success && data.students.length > 0) {
                    data.students.forEach(student => {
                        const tr = document.createElement('tr');
                        tr.className = 'hover:bg-gray-50 transition-colors';
                        tr.innerHTML = `
                            <td class="p-4 whitespace-nowrap">
                                <input type="checkbox" name="student_ids[]" value="${student.id}" checked
                                    class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer">
                            </td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-600 font-medium">
                                ${student.id}
                            </td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                ${student.first_name} ${student.last_name}
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                    
                    container.classList.remove('hidden');
                    noStudentsMsg.classList.add('hidden');
                } else {
                    container.classList.add('hidden');
                    noStudentsMsg.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Error fetching students:', error);
                alert('Failed to load students. Please try again.');
            }
        }
    </script>
</body>
</html>
