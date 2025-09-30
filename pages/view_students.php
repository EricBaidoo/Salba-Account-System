<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
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
    $where_conditions[] = "REPLACE(class, ' ', '') = REPLACE(?, ' ', '')";
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
    <style>
        .students-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .filter-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid #e5e7eb;
        }
        
        .student-table {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .table thead th {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f0fe 100%);
            border: none;
            font-weight: 600;
            color: #374151;
            padding: 1rem;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
            transform: scale(1.01);
        }
        
        .class-badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .class-early-years {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }
        
        .class-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stats-card {
            background: linear-gradient(145deg, #ffffff 0%, #f8f9ff 100%);
            border: 1px solid #e5e7eb;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.15);
        }
        
        .search-box {
            border-radius: 25px;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .search-box:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .action-btn {
            border-radius: 20px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>
                <strong>Salba Montessori</strong>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="students-header animate-fade-in">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-users me-3"></i>Students Directory
                    </h1>
                    <p class="mb-0 opacity-75">
                        Total Students: <strong><?php echo $total_students; ?></strong> | 
                        Manage and view all enrolled students
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="add_student_form.php" class="btn btn-light btn-lg action-btn">
                        <i class="fas fa-user-plus me-2"></i>Add New Student
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="row g-3">
                    <?php foreach ($class_stats as $stat): ?>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3 class="text-primary mb-1"><?php echo $stat['count']; ?></h3>
                                <p class="small text-muted mb-0"><?php echo htmlspecialchars($stat['class']); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-line fa-2x text-success mb-2"></i>
                        <h6>Total Enrollment</h6>
                        <h2 class="text-success mb-0"><?php echo $total_students; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card filter-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-2"></i>Search Students
                        </label>
                        <input type="text" name="search" class="form-control search-box" 
                               placeholder="Name or contact..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">
                            <i class="fas fa-filter me-2"></i>Filter by Class
                        </label>
                        <select name="class" class="form-select">
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
                        <label class="form-label fw-bold">
                            <i class="fas fa-toggle-on me-2"></i>Status
                        </label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                            <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>All Students</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary action-btn me-2">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                        <a href="view_students.php" class="btn btn-outline-secondary action-btn">
                            <i class="fas fa-times me-2"></i>Clear
                        </a>
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" class="btn btn-success action-btn" onclick="exportToCSV()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Students Table -->
        <div class="student-table">
            <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="studentsTable">
                    <thead>
                        <tr>
                            <th style="width: 80px;"><i class="fas fa-hashtag me-2"></i>ID</th>
                            <th><i class="fas fa-user me-2"></i>Full Name</th>
                            <th><i class="fas fa-graduation-cap me-2"></i>Class</th>
                            <th><i class="fas fa-toggle-on me-2"></i>Status</th>
                            <th><i class="fas fa-birthday-cake me-2"></i>Date of Birth</th>
                            <th><i class="fas fa-phone me-2"></i>Parent Contact</th>
                            <th><i class="fas fa-calendar me-2"></i>Enrolled</th>
                            <th style="width: 150px;"><i class="fas fa-cogs me-2"></i>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary">#<?php echo $row['id']; ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-placeholder bg-primary text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php 
                                $class_type = in_array($row['class'], ['Creche', 'Nursery 1', 'Nursery 2', 'KG 1', 'KG 2']) ? 'early-years' : 'primary';
                                ?>
                                <span class="class-badge class-<?php echo $class_type; ?>">
                                    <?php echo htmlspecialchars($row['class']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['status'] === 'active'): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i>Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-times-circle me-1"></i>Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['date_of_birth']): ?>
                                    <?php 
                                    $dob = new DateTime($row['date_of_birth']);
                                    $age = $dob->diff(new DateTime())->y;
                                    ?>
                                    <div><?php echo $dob->format('M j, Y'); ?></div>
                                    <small class="text-muted">(<?php echo $age; ?> years old)</small>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['parent_contact']): ?>
                                    <div><?php echo htmlspecialchars($row['parent_contact']); ?></div>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $created = new DateTime($row['created_at']);
                                echo $created->format('M j, Y');
                                ?>
                                <div>
                                    <small class="text-muted"><?php echo $created->format('g:i A'); ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" title="Edit Student" onclick="editStudent(<?php echo $row['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($row['status'] === 'active'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-warning" title="Disable Student" onclick="toggleStudent(<?php echo $row['id']; ?>, 'inactive')">
                                            <i class="fas fa-user-times"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-success" title="Enable Student" onclick="toggleStudent(<?php echo $row['id']; ?>, 'active')">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-info" title="Assign Fees" onclick="assignFees(<?php echo $row['id']; ?>)">
                                        <i class="fas fa-money-bill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No Students Found</h4>
                <?php if (!empty($search) || !empty($class_filter)): ?>
                    <p>No students match your current filters.</p>
                    <a href="view_students.php" class="btn btn-outline-primary">View All Students</a>
                <?php else: ?>
                    <p>Start by adding your first student to the system.</p>
                    <a href="add_student_form.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Add First Student
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="text-center mt-4">
            <div class="d-flex justify-content-center gap-3">
                <a href="add_student_form.php" class="btn btn-primary action-btn">
                    <i class="fas fa-user-plus me-2"></i>Add Student
                </a>
                <a href="bulk_upload_students.php" class="btn btn-success action-btn">
                    <i class="fas fa-upload me-2"></i>Bulk Upload
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary action-btn">
                    <i class="fas fa-home me-2"></i>Dashboard
                </a>
            </div>
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