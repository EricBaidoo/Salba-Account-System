<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}

// Get filter parameters
$class_filter = $_GET['class'] ?? '';
$search       = $_GET['search'] ?? '';
$status_filter= $_GET['status'] ?? 'active';

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if ($status_filter) {
    if ($status_filter === 'active') {
        $where_conditions[] = "status = 'active'";
    } else {
        $where_conditions[] = "status = 'inactive'";
    }
}

if (!empty($class_filter)) {
    $where_conditions[] = "LOWER(TRIM(class)) = LOWER(TRIM(?))";
    $params[] = $class_filter;
    $param_types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR parent_contact LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$query = "SELECT * FROM students $where_clause ORDER BY id DESC";
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$total_students = $conn->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'")->fetch_assoc()['total'];

$class_result = $conn->query("SELECT class, COUNT(*) as count FROM students WHERE status = 'active' GROUP BY class ORDER BY class");
$class_stats = [];
while ($row = $class_result->fetch_assoc()) {
    $class_stats[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Directory - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="ml-72 min-h-screen">
        <!-- Header Section -->
        <div class="bg-white border-b border-gray-100 px-8 py-6">
            <div class="flex justify-between items-center mb-6">
                <a href="../dashboard.php" class="text-gray-400 hover:text-blue-600 transition-colors flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <div class="flex justify-between items-end">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                        <i class="fas fa-user-graduate text-blue-600"></i> Students Directory
                    </h1>
                    <p class="text-gray-500 mt-2 text-sm">
                        Manage and view all enrolled students.
                        <?php $ct = getCurrentSemester($conn); $cy = getAcademicYear($conn); ?>
                        <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-md ml-3">
                            <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($ct); ?>
                        </span>
                        <span class="inline-flex items-center gap-1 bg-purple-50 text-purple-700 text-xs font-semibold px-2 py-0.5 rounded-md ml-1">
                            <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $cy)); ?>
                        </span>
                    </p>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="exportToCSV()" class="px-4 py-2 border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium flex items-center gap-2">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <a href="add_student_form.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium flex items-center gap-2 shadow-sm">
                        <i class="fas fa-plus"></i> Add Student
                    </a>
                </div>
            </div>
        </div>

        <div class="p-8">
            <!-- Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg flex items-center gap-3 mb-6 shadow-sm">
                    <i class="fas fa-check-circle text-emerald-500"></i>
                    <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-3 mb-6 shadow-sm">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                    <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-8">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 w-full">
                    <div class="text-2xl font-bold text-gray-900"><?php echo $total_students; ?></div>
                    <div class="text-xs font-semibold text-gray-400 uppercase mt-1">Total Active</div>
                </div>
                <?php foreach ($class_stats as $class): ?>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 w-full">
                    <div class="text-2xl font-bold text-blue-600"><?php echo $class['count']; ?></div>
                    <div class="text-xs font-semibold text-gray-400 uppercase mt-1 truncate" title="<?php echo htmlspecialchars($class['class']); ?>">
                        <?php echo htmlspecialchars($class['class']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-8">
                <form method="GET" class="flex flex-wrap lg:flex-nowrap gap-4 items-end">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">
                            Search Students
                        </label>
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="search" class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm" 
                                   placeholder="Name or contact info..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    
                    <div class="w-full lg:w-48">
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">
                            Filter by Class
                        </label>
                        <select name="class" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            <option value="">All Classes</option>
                            <?php 
                            $classesList = ['Creche','Nursery 1','Nursery 2','KG 1','KG 2','Basic 1','Basic 2','Basic 3','Basic 4','Basic 5','Basic 6','Basic 7'];
                            foreach($classesList as $c): 
                            ?>
                                <option value="<?php echo $c; ?>" <?php echo $class_filter === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="w-full lg:w-48">
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">
                            Status
                        </label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                            <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>All Students</option>
                        </select>
                    </div>

                    <div class="flex gap-2 w-full lg:w-auto">
                        <button type="submit" class="px-4 py-2 bg-blue-50 text-blue-600 font-medium rounded-lg hover:bg-blue-100 transition-colors text-sm">
                            Filter
                        </button>
                        <?php if ($search || $class_filter || $status_filter !== 'active'): ?>
                        <a href="view_students.php" class="px-4 py-2 bg-gray-50 text-gray-600 font-medium rounded-lg hover:bg-gray-100 transition-colors text-sm">
                            Reset
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <?php if ($result->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse" id="studentsTable">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">ID</th>
                                <th class="px-6 py-4">Student Name</th>
                                <th class="px-6 py-4">Class</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Parent Contact</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 text-sm text-gray-500 font-mono">
                                    #<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                            <?php echo strtoupper(substr($row['first_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                            <div class="text-xs text-gray-400">Enrolled: <?php echo (new DateTime($row['created_at']))->format('M Y'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 border border-purple-100">
                                        <?php echo htmlspecialchars($row['class']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($row['status'] === 'active'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo htmlspecialchars($row['parent_contact'] ?? '—'); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="edit_student_form.php?id=<?php echo $row['id']; ?>" class="w-8 h-8 rounded-lg bg-gray-50 border border-gray-200 text-gray-500 flex items-center justify-center hover:bg-white hover:text-blue-600 hover:shadow-sm transition-all" title="Edit Student">
                                            <i class="fas fa-edit text-xs"></i>
                                        </a>
                                        <?php if ($row['status'] === 'active'): ?>
                                            <button onclick="toggleStudent(<?php echo $row['id']; ?>, 'inactive')" class="w-8 h-8 rounded-lg bg-gray-50 border border-gray-200 text-gray-500 flex items-center justify-center hover:bg-white hover:text-red-600 hover:shadow-sm transition-all" title="Deactivate">
                                                <i class="fas fa-trash-alt text-xs"></i>
                                            </button>
                                        <?php else: ?>
                                            <button onclick="toggleStudent(<?php echo $row['id']; ?>, 'active')" class="w-8 h-8 rounded-lg bg-gray-50 border border-gray-200 text-gray-500 flex items-center justify-center hover:bg-white hover:text-emerald-600 hover:shadow-sm transition-all" title="Activate">
                                                <i class="fas fa-check text-xs"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="p-12 text-center">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-users text-2xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-1">No Students Found</h3>
                    <?php if (!empty($search) || !empty($class_filter)): ?>
                        <p class="text-gray-500 mb-4 text-sm">We couldn't find any students matching your current filters.</p>
                        <a href="view_students.php" class="text-blue-600 hover:text-blue-700 font-medium text-sm">Clear all filters</a>
                    <?php else: ?>
                        <p class="text-gray-500 mb-4 text-sm">Your directory is empty. Add your first student to get started.</p>
                        <a href="add_student_form.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm transition-colors">
                            <i class="fas fa-plus"></i> Add Student
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        function toggleStudent(studentId, newStatus) {
            const action = newStatus === 'active' ? 'activate' : 'deactivate';
            if (confirm(`Are you sure you want to ${action} this student?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'toggle_student_status.php';
                form.innerHTML = `
                    <input type="hidden" name="student_id" value="${studentId}">
                    <input type="hidden" name="status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function exportToCSV() {
            const table = document.getElementById('studentsTable');
            if (!table) return alert('No data to export.');
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [];
                const cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length - 1; j++) { // Exclude actions
                    let data = cols[j].innerText.trim().replace(/(\r\n|\n|\r)/gm, " ");
                    // Handle inner text containing commas
                    row.push('"' + data.replace(/"/g, '""') + '"');
                }
                csv.push(row.join(','));
            }
            
            const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.hidden = true;
            a.href = url;
            a.download = 'students_export.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
        }

        // Auto-submit form when selects change
        document.querySelector('select[name="class"]').addEventListener('change', function() { this.form.submit(); });
        document.querySelector('select[name="status"]').addEventListener('change', function() { this.form.submit(); });
        </script>
    </main>
</body>
</html>
