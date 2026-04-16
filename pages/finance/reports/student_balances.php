<?php 
include '../../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../../includes/db_connect.php';
include '../../includes/system_settings.php';
include '../../includes/student_balance_functions.php';

// Get current semester and academic year from system settings
$current_term = getCurrentSemester($conn);
$default_academic_year = getAcademicYear($conn);

// Allow manual semester override via URL parameter (for historical viewing)
$selected_term = $_GET['semester'] ?? $current_term;
// Academic year selection (override via GET if provided)
$selected_academic_year = $_GET['academic_year'] ?? $default_academic_year;
$display_academic_year = formatAcademicYearDisplay($conn, $selected_academic_year);

// Get filter parameters
$class_filter = $_GET['class'] ?? 'all';
$status_filter = $_GET['status'] ?? 'active';
$owing_filter = $_GET['owing'] ?? 'all'; // all, owing, paid_up

// Ensure arrears assignment exists (or is removed) per filtered student before computing balances
{
    $where = [];
    $params = [];
    $types = '';
    if ($status_filter && $status_filter !== 'all') { $where[] = "status = ?"; $params[] = $status_filter; $types .= 's'; }
    if ($class_filter && $class_filter !== 'all') { $where[] = "class = ?"; $params[] = $class_filter; $types .= 's'; }
    $sql = "SELECT id FROM students" . (empty($where) ? '' : (' WHERE ' . implode(' AND ', $where)));
    $stmt = $conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            ensureArrearsAssignment($conn, intval($row['id']), $selected_term, $selected_academic_year);
        }
    }
    $stmt->close();
}

// Get all student balances for the selected semester/year (now includes arrears as part of fees)
$student_balances = getAllStudentBalances($conn, $class_filter, $status_filter, $selected_term, $selected_academic_year);

// Apply owing filter
if ($owing_filter === 'owing') {
    $student_balances = array_filter($student_balances, function($student) {
        return $student['net_balance'] > 0;
    });
} elseif ($owing_filter === 'paid_up') {
    $student_balances = array_filter($student_balances, function($student) {
        return $student['net_balance'] == 0;
    });
}

// Compute percent paid for each student and attach to array
foreach ($student_balances as &$s) {
    $total_fees = (float)($s['total_fees'] ?? 0);
    $total_payments = (float)($s['total_payments'] ?? 0);
    if ($total_fees > 0) {
        $s['paid_percent'] = min(100, ($total_payments / $total_fees) * 100);
    } elseif ($total_payments > 0) {
        $s['paid_percent'] = 100;
    } else {
        $s['paid_percent'] = 0;
    }
}
unset($s);

// Percent filter (ranges)
// New percent filters: all, below50, below75, below100
$percent_filter = $_GET['percent'] ?? 'all';
if ($percent_filter !== 'all') {
    $student_balances = array_filter($student_balances, function($st) use ($percent_filter) {
        $p = $st['paid_percent'];
        switch ($percent_filter) {
            case 'below50': return $p < 50;
            case 'below75': return $p < 75;
            case 'below100': return $p < 100;
            default: return true;
        }
    });
}

// Sorting
$sort_by = $_GET['sort_by'] ?? 'name'; // name, class, percent
$order = $_GET['order'] ?? 'asc'; // asc, desc
if ($sort_by === 'percent') {
    usort($student_balances, function($a, $b) use ($order) {
        if ($a['paid_percent'] == $b['paid_percent']) return 0;
        if ($order === 'asc') return ($a['paid_percent'] < $b['paid_percent']) ? -1 : 1;
        return ($a['paid_percent'] > $b['paid_percent']) ? -1 : 1;
    });
} elseif ($sort_by === 'class') {
    usort($student_balances, function($a, $b) use ($order) {
        if ($a['class'] == $b['class']) return 0;
        if ($order === 'asc') return ($a['class'] < $b['class']) ? -1 : 1;
        return ($a['class'] > $b['class']) ? -1 : 1;
    });
} else {
    // default sort by student name
    usort($student_balances, function($a, $b) use ($order) {
        if ($a['student_name'] == $b['student_name']) return 0;
        if ($order === 'asc') return ($a['student_name'] < $b['student_name']) ? -1 : 1;
        return ($a['student_name'] > $b['student_name']) ? -1 : 1;
    });
}

// Calculate summary statistics
$total_students = count($student_balances);
$total_fees = array_sum(array_column($student_balances, 'total_fees'));
$total_payments = array_sum(array_column($student_balances, 'total_payments'));
$total_owing = array_sum(array_column($student_balances, 'net_balance'));
$students_with_balance = count(array_filter($student_balances, function($s) { return $s['net_balance'] > 0; }));

// Get classes for filter
$classes_result = $conn->query("SELECT DISTINCT class FROM students ORDER BY class");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Balances - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="container-fluid px-4">
            <div class="flex justify-between items-center mb-">
                <a href="../dashboard.php" class="clean-back-px-3 py-2 rounded font-medium">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <div class="flex justify-between items-center flex-wrap">
                <div class="mb- mb-md-0">
                    <h1 class="clean-page-title"><i class="fas fa-balance-scale me-2"></i>Student Balances</h1>
                    <p class="clean-page-subtitle">
                        <span class="clean-badge clean-badge-primary me-2">
                            <i class="fas fa-calendar-alt me-1"></i><?php echo htmlspecialchars($selected_term); ?>
                        </span>
                        <span class="clean-badge clean-badge-info">
                            <i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($display_academic_year); ?>
                        </span>
                    </p>
                </div>
                <div class="print:hidden flex gap-2 flex-wrap">
                    <a href="../fees/assign_fee_form.php" class="px-3 py-2 rounded font-medium-clean-success">
                        <i class="fas fa-plus"></i> ASSIGN FEES
                    </a>
                    <a href="../payments/record_payment_form.php" class="px-3 py-2 rounded font-medium-clean-primary">
                        <i class="fas fa-credit-card"></i> RECORD PAYMENT
                    </a>
                    <a href="download_student_balances.php?class=<?php echo urlencode($class_filter); ?>&status=<?php echo urlencode($status_filter); ?>&owing=<?php echo urlencode($owing_filter); ?>&semester=<?php echo urlencode($selected_term); ?>&academic_year=<?php echo urlencode($selected_academic_year); ?>" class="px-3 py-2 rounded font-medium-clean-success">
                        <i class="fas fa-file-csv"></i> DOWNLOAD CSV
                    </a>
                    <a href="download_student_balances_pdf.php?class=<?php echo urlencode($class_filter); ?>&status=<?php echo urlencode($status_filter); ?>&owing=<?php echo urlencode($owing_filter); ?>&semester=<?php echo urlencode($selected_term); ?>&academic_year=<?php echo urlencode($selected_academic_year); ?>" class="px-3 py-2 rounded font-medium-clean-danger">
                        <i class="fas fa-file-pdf"></i> DOWNLOAD PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">
        <!-- Summary Statistics -->
        <div class="clean-stats-grid print:hidden">
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo $total_students; ?></div>
                <div class="clean-stat-label">Total Students</div>
            </div>
            <div class="clean-stat-item">
                <div class="clean-stat-value">GH₵<?php echo number_format($total_fees, 2); ?></div>
                <div class="clean-stat-label">Total Fees</div>
            </div>
            <div class="clean-stat-item">
                <div class="clean-stat-value">GH₵<?php echo number_format($total_payments, 2); ?></div>
                <div class="clean-stat-label">Total Paid</div>
            </div>
            <div class="clean-stat-item">
                <div class="clean-stat-value">GH₵<?php echo number_format($total_owing, 2); ?></div>
                <div class="clean-stat-label">Outstanding</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="clean-filter-bar print:hidden">
            <form method="GET" class="flex flex-wrap gap-3 items-end">
                <div class="col-md-2">
                    <label class="clean-form-label">
                        <i class="fas fa-calendar-alt me-2"></i>Semester
                    </label>
                    <select name="semester" class="clean-border border-gray-300 rounded px-3 py-2">
                        <?php 
                        $available_terms = getAvailableSemesters();
                        foreach ($available_terms as $semester): ?>
                            <option value="<?php echo htmlspecialchars($semester); ?>" 
                                    <?php echo $selected_term === $semester ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($semester); ?>
                                <?php echo $semester === $current_term ? ' (Current)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="clean-form-label">
                        <i class="fas fa-graduation-cap me-2"></i>Academic Year
                    </label>
                    <select name="academic_year" class="clean-border border-gray-300 rounded px-3 py-2">
                        <?php 
                        // Build academic year options from data + default
                        $year_options = [];
                        $yrs_rs = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
                        if ($yrs_rs) {
                            while ($yr = $yrs_rs->fetch_assoc()) {
                                if (!empty($yr['academic_year'])) { $year_options[] = $yr['academic_year']; }
                            }
                            $yrs_rs->close();
                        }
                        if (!in_array($default_academic_year, $year_options, true)) { array_unshift($year_options, $default_academic_year); }
                        foreach ($year_options as $yr): ?>
                            <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo ($yr === $selected_academic_year) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $yr)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="clean-form-label">
                        <i class="fas fa-layer-group me-2"></i>Class
                    </label>
                    <select name="class" class="clean-border border-gray-300 rounded px-3 py-2">
                        <option value="all" <?php echo $class_filter === 'all' ? 'selected' : ''; ?>>All Classes</option>
                        <?php 
                        $classes_result->data_seek(0); // Reset pointer
                        while($class_row = $classes_result->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($class_row['class']); ?>" 
                                    <?php echo $class_filter === $class_row['class'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class_row['class']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="clean-form-label">
                        <i class="fas fa-user-check me-2"></i>Status
                    </label>
                    <select name="status" class="clean-border border-gray-300 rounded px-3 py-2">
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Students</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="clean-form-label">
                        <i class="fas fa-balance-scale me-2"></i>Balance
                    </label>
                    <select name="owing" class="clean-border border-gray-300 rounded px-3 py-2">
                        <option value="all" <?php echo $owing_filter === 'all' ? 'selected' : ''; ?>>All Students</option>
                        <option value="owing" <?php echo $owing_filter === 'owing' ? 'selected' : ''; ?>>Owing Money</option>
                        <option value="paid_up" <?php echo $owing_filter === 'paid_up' ? 'selected' : ''; ?>>Paid Up</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="clean-form-label">
                        <i class="fas fa-percent me-2"></i>Percent Paid
                    </label>
                    <select name="percent" class="clean-border border-gray-300 rounded px-3 py-2" onchange="this.form.submit()">
                        <option value="all" <?php echo $percent_filter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="below50" <?php echo $percent_filter === 'below50' ? 'selected' : ''; ?>>All students below 50%</option>
                        <option value="below75" <?php echo $percent_filter === 'below75' ? 'selected' : ''; ?>>All students below 75%</option>
                        <option value="below100" <?php echo $percent_filter === 'below100' ? 'selected' : ''; ?>>All students below 100%</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="px-3 py-2 rounded font-medium-clean-primary w-full">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Print Header -->
        <?php $school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori'); ?>
        <div class="print-header text-center">
            <h3 class="mb-"><?php echo htmlspecialchars($school_name); ?></h3>
            <div class="small text-muted">Student Balances Report</div>
            <div class="mt-1">
                Semester: <strong><?php echo htmlspecialchars($selected_term); ?></strong>
                | Academic Year: <strong><?php echo htmlspecialchars($display_academic_year); ?></strong>
                | Class: <strong><?php echo $class_filter !== 'all' ? htmlspecialchars($class_filter) : 'All Classes'; ?></strong>
                | Status: <strong><?php echo htmlspecialchars(ucfirst($status_filter)); ?></strong>
                | Balance: <strong><?php echo $owing_filter === 'all' ? 'All' : ($owing_filter === 'owing' ? 'Owing' : 'Paid Up'); ?></strong>
            </div>
            <div class="small text-muted">Printed on <?php echo date('M j, Y'); ?></div>
        </div>

        <!-- Student Balances (Table) -->
        <div class="flex flex-wrap gap-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <?php if (!empty($student_balances)): ?>
                        <div class="table-responsive">
                            <table class="table pro-table align-middle mb-">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Class</th>
                                        <th class="text-center">% Paid</th>
                                        <th class="text-right">Total Fees</th>
                                        <th class="text-right">Total Paid</th>
                                        <th class="text-right">Balance</th>
                                        <th class="text-center">Pending</th>
                                        <th class="text-center">Paid</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($student_balances as $student): ?>
                                        <?php 
                                            $total_fees = (float)($student['total_fees'] ?? 0);
                                            $total_payments = (float)($student['total_payments'] ?? 0);
                                            $paid_percent = 0;
                                            if ($total_fees > 0) { $paid_percent = min(100, ($total_payments / $total_fees) * 100); }
                                            elseif ($total_payments > 0) { $paid_percent = 100; }
                                            $paid_percent_rounded = round($paid_percent);
                                            if ($paid_percent_rounded >= 100) $bucket = 100; elseif ($paid_percent_rounded >= 75) $bucket = 75; elseif ($paid_percent_rounded >= 50) $bucket = 50; else $bucket = 0;
                                            $outstanding = max(0, $total_fees - $total_payments);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">
                                                    <a href="student_balance_details.php?id=<?php echo $student['student_id']; ?>&semester=<?php echo urlencode($selected_term); ?>&academic_year=<?php echo urlencode($selected_academic_year); ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($student['student_name']); ?>
                                                    </a>
                                                    <?php if ($student['student_status'] === 'inactive'): ?>
                                                        <span class="badge bg-secondary ms-2">Inactive</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($student['class']); ?></td>
                                            <td class="text-center">
                                                <div class="progress-min" title="<?php echo $paid_percent_rounded; ?>%">
                                                    <div class="bar" style="width: <?php echo $paid_percent_rounded; ?>%"></div>
                                                </div>
                                                <span class="percent-text"><?php echo $paid_percent_rounded; ?>%</span>
                                            </td>
                                            <td class="text-right text-primary currency">GH₵<?php echo number_format($total_fees, 2); ?></td>
                                            <td class="text-right text-green-600 currency">GH₵<?php echo number_format($total_payments, 2); ?></td>
                                            <td class="text-right <?php echo $outstanding>0?'text-red-600 fw-semibold':'text-green-600'; ?>"><?php echo $outstanding>0?('GH₵'.number_format($outstanding,2)):'Paid Up'; ?></td>
                                            <td class="text-center"><span class="badge bg-warning text-dark"><?php echo $student['pending_assignments']; ?></span></td>
                                            <td class="text-center"><span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700"><?php echo $student['paid_assignments']; ?></span></td>
                                            <td>
                                                <div class="dropdown balances-actions">
                                                    <button class="px-3 py-2 rounded font-medium px-3 py-2 rounded font-medium-light px-3 py-2 rounded font-medium-sm action-dots" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow">
                                                        <li><a class="dropdown-item" href="student_balance_details.php?id=<?php echo $student['student_id']; ?>&semester=<?php echo urlencode($selected_term); ?>&academic_year=<?php echo urlencode($selected_academic_year); ?>">
                                                            <i class="fas fa-eye me-2 text-primary"></i>View Details
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="../payments/record_payment_form.php?student_id=<?php echo $student['student_id']; ?>">
                                                            <i class="fas fa-credit-card me-2 text-green-600"></i>Record Payment
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="../fees/assign_fee_form.php?student_id=<?php echo $student['student_id']; ?>">
                                                            <i class="fas fa-plus me-2 text-blue-600"></i>Assign Fee
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="student_percentage.php?id=<?php echo $student['student_id']; ?>">
                                                            <i class="fas fa-chart-pie me-2 text-secondary"></i>Percentage
                                                        </a></li>
                                                        <li><a class="dropdown-item" target="_blank" href="download_term_invoice.php?student_id=<?php echo $student['student_id']; ?>&semester=<?php echo urlencode($selected_term); ?>&academic_year=<?php echo urlencode($selected_academic_year); ?>">
                                                            <i class="fas fa-download me-2 text-dark"></i>Download Invoice
                                                        </a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <div class="mb-"><i class="fas fa-search fa-3x text-muted opacity-25"></i></div>
                                <h5 class="text-muted mb-">No Students Found</h5>
                                <p class="text-muted mb-">No students match your current filter criteria. Try adjusting your filters.</p>
                                <a href="?class=all&status=active&owing=all" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                    <i class="fas fa-redo me-2"></i>Clear All Filters
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="text-center mt-5 mb-">
            <div class="px-3 py-2 rounded font-medium-group shadow-sm" role="group">
                <a href="../dashboard.php" class="px-3 py-2 rounded font-medium px-3 py-2 rounded font-medium-outline-secondary px-3 py-2 rounded font-medium-lg">
                    <i class="fas fa-home me-2"></i>Dashboard
                </a>
                <a href="../../administration/students/view_students.php" class="px-3 py-2 rounded font-medium px-3 py-2 rounded font-medium-outline-primary px-3 py-2 rounded font-medium-lg">
                    <i class="fas fa-users me-2"></i>All Students
                </a>
                <a href="../fees/view_assigned_fees.php" class="px-3 py-2 rounded font-medium px-3 py-2 rounded font-medium-outline-success px-3 py-2 rounded font-medium-lg">
                    <i class="fas fa-list-alt me-2"></i>Fee Assignments
                </a>
                <a href="../payments/view_payments.php" class="px-3 py-2 rounded font-medium px-3 py-2 rounded font-medium-outline-info px-3 py-2 rounded font-medium-lg">
                    <i class="fas fa-credit-card me-2"></i>Payment History
                </a>
            </div>
        </div>

        <!-- Sorting & counts -->
        <div class="flex justify-between items-center mb-">
            <div>
                <small class="text-muted">Showing <strong><?php echo $total_students; ?></strong> students</small>
            </div>
            <div>
            <form method="GET" class="flex gap-2">
                <input type="hidden" name="class" value="<?php echo htmlspecialchars($class_filter); ?>">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <input type="hidden" name="owing" value="<?php echo htmlspecialchars($owing_filter); ?>">
                <input type="hidden" name="percent" value="<?php echo htmlspecialchars($percent_filter); ?>">
                <select name="sort_by" class="border border-gray-300 rounded px-3 py-2 bg-white border border-gray-300 rounded px-3 py-2 bg-white-sm" onchange="this.form.submit()">
                    <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Sort: Name</option>
                    <option value="class" <?php echo $sort_by === 'class' ? 'selected' : ''; ?>>Sort: Class</option>
                    <option value="percent" <?php echo $sort_by === 'percent' ? 'selected' : ''; ?>>Sort: Percent Paid</option>
                </select>
                <select name="order" class="border border-gray-300 rounded px-3 py-2 bg-white border border-gray-300 rounded px-3 py-2 bg-white-sm" onchange="this.form.submit()">
                    <option value="asc" <?php echo $order === 'asc' ? 'selected' : ''; ?>>Asc</option>
                    <option value="desc" <?php echo $order === 'desc' ? 'selected' : ''; ?>>Desc</option>
                </select>
            </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>