<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
include '../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}

// Get filter parameters
$class_filter = isset($_GET['class']) ? $_GET['class'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'active';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

// Add status filter (default to active)
if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
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

$query = "SELECT * FROM students $where_clause ORDER BY id ASC";
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Get total count and class statistics
$total_query = "SELECT COUNT(*) as total FROM students WHERE status = 'active'";
$class_query = "SELECT class, COUNT(*) as count FROM students WHERE status = 'active' GROUP BY class ORDER BY class";

$total_result = $conn->query($total_query);
$total_students = $total_result->fetch_assoc()['total'];

$class_result = $conn->query($class_query);
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
    <title>Students Directory - Salba Montessori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="dashboard.php" class="clean-back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="clean-page-title"><i class="fas fa-user-graduate me-2"></i>Students Directory</h1>
                    <p class="clean-page-subtitle">
                        Manage and view all enrolled students
                        <?php $ct = getCurrentTerm($conn); $cy = getAcademicYear($conn); ?>
                        <span class="clean-badge clean-badge-primary ms-2"><i class="fas fa-calendar-alt me-1"></i><?php echo htmlspecialchars($ct); ?></span>
                        <span class="clean-badge clean-badge-info ms-1"><i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $cy)); ?></span>
                    </p>
                </div>
                <a href="add_student_form.php" class="btn-clean-primary">
                    <i class="fas fa-plus"></i> ADD NEW STUDENT
                </a>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="clean-alert clean-alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="clean-alert clean-alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="clean-stats-grid">
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo $total_students; ?></div>
                <div class="clean-stat-label">Total Students</div>
            </div>
            <?php foreach ($class_stats as $class): ?>
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo $class['count']; ?></div>
                <div class="clean-stat-label"><?php echo htmlspecialchars($class['class']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filters and Search -->
        <div class="clean-filter-bar">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="clean-form-label">
                        <i class="fas fa-search me-2"></i>Search Students
                    </label>
                    <input type="text" name="search" class="clean-search-input" 
                           placeholder="Name or contact..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="clean-form-label">
                        <i class="fas fa-filter me-2"></i>Filter by Class
                    </label>
                    <select name="class" class="clean-form-control">
                        <option value="">All Classes</option>
                        <option value="Creche" <?php echo $class_filter === 'Creche' ? 'selected' : ''; ?>>Creche</option>
                        <option value="Nursery 1" <?php echo $class_filter === 'Nursery 1' ? 'selected' : ''; ?>>Nursery 1</option>
                        <option value="Nursery 2" <?php echo $class_filter === 'Nursery 2' ? 'selected' : ''; ?>>Nursery 2</option>
                        <option value="KG 1" <?php echo $class_filter === 'KG 1' ? 'selected' : ''; ?>>KG 1</option>
                        <option value="KG 2" <?php echo $class_filter === 'KG 2' ? 'selected' : ''; ?>>KG 2</option>
                        <option value="Basic 1" <?php echo $class_filter === 'Basic 1' ? 'selected' : ''; ?>>Basic 1</option>
                        <option value="Basic 2" <?php echo $class_filter === 'Basic 2' ? 'selected' : ''; ?>>Basic 2</option>
                        <option value="Basic 3" <?php echo $class_filter === 'Basic 3' ? 'selected' : ''; ?>>Basic 3</option>
                        <option value="Basic 4" <?php echo $class_filter === 'Basic 4' ? 'selected' : ''; ?>>Basic 4</option>
                        <option value="Basic 5" <?php echo $class_filter === 'Basic 5' ? 'selected' : ''; ?>>Basic 5</option>
                        <option value="Basic 6" <?php echo $class_filter === 'Basic 6' ? 'selected' : ''; ?>>Basic 6</option>
                        <option value="Basic 7" <?php echo $class_filter === 'Basic 7' ? 'selected' : ''; ?>>Basic 7</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="clean-form-label">
                        <i class="fas fa-toggle-on me-2"></i>Status
                    </label>
                    <select name="status" class="clean-form-control">
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                        <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>All Students</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn-clean-primary me-2">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                    <a href="view_students.php" class="btn-clean-outline">
                        <i class="fas fa-times me-2"></i>Clear
                    </a>
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn-clean-success" onclick="exportToCSV()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                </div>
            </form>
        </div>

        <!-- Students Table -->
        <div class="clean-card">
            <?php if ($result->num_rows > 0): ?>
            <div class="clean-table-scroll">
                <table class="clean-table" id="studentsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th>Parent Contact</th>
                            <th>Enrolled Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><span class="clean-badge clean-badge-primary">#<?php echo $row['id']; ?></span></td>
                            <td><strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong></td>
                            <td><span class="clean-badge clean-badge-info"><?php echo htmlspecialchars($row['class']); ?></span></td>
                            <td>
                                <?php if ($row['status'] === 'active'): ?>
                                    <span class="clean-badge clean-badge-success"><i class="fas fa-check"></i> Active</span>
                                <?php else: ?>
                                    <span class="clean-badge clean-badge-danger"><i class="fas fa-times"></i> Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['parent_contact'] ?? 'N/A'); ?></td>
                            <td><?php echo (new DateTime($row['created_at']))->format('M j, Y'); ?></td>
                            <td>
                                <div class="clean-actions">
                                    <a href="edit_student_form.php?id=<?php echo $row['id']; ?>" class="btn-clean-outline btn-clean-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($row['status'] === 'active'): ?>
                                        <button onclick="toggleStudent(<?php echo $row['id']; ?>, 'inactive')" class="btn-clean-outline btn-clean-sm" title="Deactivate">
                                            <i class="fas fa-user-times"></i>
                                        </button>
                                    <?php else: ?>
                                        <button onclick="toggleStudent(<?php echo $row['id']; ?>, 'active')" class="btn-clean-success btn-clean-sm" title="Activate">
                                            <i class="fas fa-user-check"></i>
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
            <div class="clean-empty-state">
                <div class="clean-empty-icon"><i class="fas fa-users"></i></div>
                <h4 class="clean-empty-title">No Students Found</h4>
                <?php if (!empty($search) || !empty($class_filter)): ?>
                    <p class="clean-empty-text">No students match your current filters.</p>
                    <a href="view_students.php" class="btn-clean-outline">View All Students</a>
                <?php else: ?>
                    <p class="clean-empty-text">Start by adding your first student to the system.</p>
                    <a href="add_student_form.php" class="btn-clean-primary">
                        <i class="fas fa-user-plus"></i> Add First Student
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editStudent(studentId) {
            // Redirect to edit student page
            window.location.href = 'edit_student_form.php?id=' + studentId;
        }

        function toggleStudent(studentId, newStatus) {
            const action = newStatus === 'active' ? 'enable' : 'disable';
            const confirmation = confirm(`Are you sure you want to ${action} this student?`);
            
            if (confirmation) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'toggle_student_status.php';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'student_id';
                idInput.value = studentId;
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status';
                statusInput.value = newStatus;
                
                form.appendChild(idInput);
                form.appendChild(statusInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function assignFees(studentId) {
            // Redirect to fee assignment page
            window.location.href = 'assign_fees_form.php?student_id=' + studentId;
        }

        function exportToCSV() {
            const table = document.getElementById('studentsTable');
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [];
                const cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length - 1; j++) { // Exclude actions column
                    let data = cols[j].innerText.replace(/,/g, '');
                    row.push('"' + data + '"');
                }
                csv.push(row.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', 'students_export.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // Auto-submit form on filter change
        document.querySelector('select[name="class"]').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.querySelector('select[name="status"]').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>