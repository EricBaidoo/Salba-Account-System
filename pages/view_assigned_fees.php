<?php include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../includes/db_connect.php';
include '../includes/system_settings.php';
// School branding for print header
$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');

// Get filter parameters
$class_filter = $_GET['class'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$year_filter = $_GET['year'] ?? '';

// Academic year options
$current_academic_year = getAcademicYear($conn);
$year_options = [];
$yrs = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs) {
    while ($yr = $yrs->fetch_assoc()) {
        if (!empty($yr['academic_year'])) { $year_options[] = $yr['academic_year']; }
    }
    $yrs->close();
}
if (!in_array($current_academic_year, $year_options, true)) { array_unshift($year_options, $current_academic_year); }

// Build query with filters
$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($class_filter)) {
    $where_clauses[] = "v.student_class = ?";
    $params[] = $class_filter;
    $param_types .= 's';
}

if (!empty($status_filter)) {
    $where_clauses[] = "v.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($search)) {
    $where_clauses[] = "(v.student_name LIKE ? OR v.fee_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= 'ss';
}

// Restrict by academic year if provided
if (!empty($year_filter)) {
    $where_clauses[] = "sf.academic_year = ?";
    $params[] = $year_filter;
    $param_types .= 's';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(' AND ', $where_clauses);
}

$sql = "SELECT v.*, sf.academic_year FROM v_fee_assignments v JOIN student_fees sf ON sf.id = v.assignment_id" . $where_sql . " ORDER BY v.due_date DESC, v.student_name";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if (!empty($param_types)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Get classes for filter dropdown
$classes = $conn->query("SELECT DISTINCT class FROM students ORDER BY class");

// Get summary statistics
    // Get summary statistics (respect academic year filter)
    $stats_sql_base = "
        SELECT 
            COUNT(*) as total_assignments,
            SUM(CASE WHEN v.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN v.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN v.payment_status = 'Overdue' THEN 1 ELSE 0 END) as overdue_count,
            COALESCE(SUM(v.amount), 0) as total_amount,
            COALESCE(SUM(CASE WHEN v.status = 'paid' THEN v.amount ELSE 0 END), 0) as paid_amount
        FROM v_fee_assignments v
        JOIN student_fees sf ON sf.id = v.assignment_id";
    if (!empty($year_filter)) {
        $st = $conn->prepare($stats_sql_base . " WHERE sf.academic_year = ?");
        $st->bind_param('s', $year_filter);
        $st->execute();
        $stats = $st->get_result()->fetch_assoc();
        $st->close();
    } else {
        $stats = $conn->query($stats_sql_base)->fetch_assoc();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Fees - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        @media print {
            .d-print-none { display: none !important; }
            .print-header { display: block !important; margin-bottom: 16px; }
        }
        @media screen {
            .print-header { display: none; }
        }
    </style>
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
            <div>
                <h1 class="clean-page-title"><i class="fas fa-list-alt me-2"></i>Fee Assignments</h1>
                <p class="clean-page-subtitle">Track and manage all student fee assignments</p>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">
        <!-- Summary Statistics -->
        <div class="clean-stats-grid d-print-none">
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo $stats['total_assignments']; ?></div>
                <div class="clean-stat-label">Total Assignments</div>
            </div>
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo $stats['pending_count']; ?></div>
                <div class="clean-stat-label">Pending Payments</div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-danger text-white h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo $stats['overdue_count']; ?></h4>
                        <small>Overdue</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo $stats['paid_count']; ?></h4>
                        <small>Paid</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="row mb-4 d-print-none">
            <div class="col-md-6 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                        <h4 class="mb-0">GH₵<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></h4>
                        <small>Total Amount Assigned</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-credit-card fa-2x mb-2"></i>
                        <h4 class="mb-0">GH₵<?php echo number_format($stats['paid_amount'] ?? 0, 2); ?></h4>
                        <small>Amount Collected</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Actions -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card filter-card">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row align-items-end">
                                <div class="col-md-3">
                                    <label for="search" class="form-label">
                                        <i class="fas fa-search me-2"></i>Search
                                    </label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Student or fee name...">
                                </div>
                                <div class="col-md-3">
                                    <label for="class" class="form-label">
                                        <i class="fas fa-layer-group me-2"></i>Class
                                    </label>
                                    <select class="form-select" id="class" name="class">
                                        <option value="">All Classes</option>
                                        <?php while($class = $classes->fetch_assoc()): ?>
                                            <option value="<?php echo $class['class']; ?>" 
                                                    <?php echo ($class_filter === $class['class']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($class['class']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="year" class="form-label">
                                        <i class="fas fa-graduation-cap me-2"></i>Academic Year
                                    </label>
                                    <select class="form-select" id="year" name="year">
                                        <option value="">All Years</option>
                                        <?php foreach ($year_options as $yr): $label = formatAcademicYearDisplay($conn, $yr); ?>
                                            <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo ($year_filter === $yr) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="status" class="form-label">
                                        <i class="fas fa-flag me-2"></i>Status
                                    </label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="paid" <?php echo ($status_filter === 'paid') ? 'selected' : ''; ?>>Paid</option>
                                        <option value="overdue" <?php echo ($status_filter === 'overdue') ? 'selected' : ''; ?>>Overdue</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-filter me-2"></i>Filter
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="d-grid gap-2 d-print-none">
                    <a href="assign_fee_form.php" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Assign New Fee
                    </a>
                    <a href="#" onclick="window.print()" class="btn btn-outline-secondary">
                        <i class="fas fa-print me-2"></i>Print Report
                    </a>
                </div>
            </div>
        </div>

        <!-- Print Header -->
        <div class="print-header text-center">
            <h3 class="mb-0"><?php echo htmlspecialchars($school_name); ?></h3>
            <div class="small text-muted">Fee Assignments Report</div>
            <div class="mt-1">
                Class: <strong><?php echo $class_filter !== '' ? htmlspecialchars($class_filter) : 'All Classes'; ?></strong>
                | Status: <strong><?php echo $status_filter !== '' ? htmlspecialchars(ucfirst($status_filter)) : 'All Status'; ?></strong>
                | Academic Year: <strong><?php echo $year_filter !== '' ? htmlspecialchars(formatAcademicYearDisplay($conn, $year_filter)) : 'All Years'; ?></strong>
            </div>
            <div class="small text-muted">Printed on <?php echo date('M j, Y'); ?></div>
        </div>

        <!-- Assignments Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-table me-2"></i>Fee Assignments</h5>
            </div>
            <div class="card-body p-0">
                <div class="clean-table-scroll">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">\n                            <tr>\n                                <th>Student</th>\n                                <th>Class</th>\n                                <th>Fee</th>\n                                <th>Type</th>\n                                <th>Amount</th>\n                                <th>Due Date</th>\n                                <th>Term</th>\n                                <th>Year</th>\n                                <th>Status</th>\n                                <th>Actions</th>\n                            </tr>\n                        </thead>
                        <tbody>
                            <?php if($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['student_name']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($row['student_class']); ?></span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($row['fee_name']); ?>
                                        <?php if (!empty($row['notes'])): ?>
                                            <i class="fas fa-sticky-note text-muted ms-1" title="Has notes"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $row['fee_type'])); ?></small>
                                    </td>
                                    <td>
                                        <strong>GH₵<?php echo number_format($row['amount'] ?? 0, 2); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($row['due_date'])); ?>
                                        <?php if ($row['days_to_due'] < 0): ?>
                                            <small class="text-danger d-block"><?php echo abs($row['days_to_due']); ?> days overdue</small>
                                        <?php elseif ($row['days_to_due'] <= 7): ?>
                                            <small class="text-warning d-block">Due in <?php echo $row['days_to_due']; ?> days</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo !empty($row['term']) ? htmlspecialchars($row['term']) : '<small class="text-muted">N/A</small>'; ?>
                                    </td>
                                    <td>
                                        <?php echo !empty($row['academic_year']) ? htmlspecialchars(formatAcademicYearDisplay($conn, $row['academic_year'])) : '<small class="text-muted">N/A</small>'; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch($row['payment_status']) {
                                            case 'Overdue': $status_class = 'overdue text-white'; break;
                                            case 'Due Soon': $status_class = 'due-soon text-white'; break;
                                            case 'Pending': $status_class = 'pending'; break;
                                            case 'Paid': $status_class = 'paid text-white'; break;
                                            default: $status_class = 'bg-secondary text-white';
                                        }
                                        ?>
                                        <span class="badge status-badge <?php echo $status_class; ?>">
                                            <?php echo $row['payment_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if($row['status'] !== 'paid'): ?>
                                                <a href="record_payment_form.php?assignment_id=<?php echo $row['assignment_id']; ?>&term=<?php echo urlencode($row['term']); ?>&academic_year=<?php echo urlencode($row['academic_year'] ?? ''); ?>" 
                                                   class="btn btn-outline-success" title="Record Payment">
                                                    <i class="fas fa-credit-card"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button class="btn btn-outline-info" title="View Details" 
                                                    onclick="viewDetails(<?php echo $row['assignment_id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" title="Cancel Assignment" 
                                                    onclick="cancelAssignment(<?php echo $row['assignment_id']; ?>)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No fee assignments found</p>
                                        <a href="assign_fee_form.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Assign First Fee
                                        </a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="text-center mt-4">
            <div class="btn-group" role="group">
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i>Dashboard
                </a>
                <a href="view_students.php" class="btn btn-outline-primary">
                    <i class="fas fa-users me-2"></i>Students
                </a>
                <a href="view_fees.php" class="btn btn-outline-success">
                    <i class="fas fa-money-bill-wave me-2"></i>Fees
                </a>
                <a href="view_payments.php" class="btn btn-outline-info">
                    <i class="fas fa-credit-card me-2"></i>Payments
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDetails(assignmentId) {
            // TODO: Implement view details modal or page
            alert('View details for assignment #' + assignmentId);
        }

        function cancelAssignment(assignmentId) {
            if (confirm('Are you sure you want to cancel this fee assignment?')) {
                // TODO: Implement cancellation
                alert('Cancel assignment #' + assignmentId);
            }
        }

        // Auto-refresh every 30 seconds for real-time updates
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>