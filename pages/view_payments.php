<?php include '../includes/auth_functions.php'; ?>
<?php if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
} ?>
<?php
include '../includes/db_connect.php';
include '../includes/system_settings.php';
$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');

// Filters: term + academic year
$selected_term = isset($_GET['term']) ? trim($_GET['term']) : '';
$selected_year = isset($_GET['year']) ? trim($_GET['year']) : '';

$current_term = getCurrentTerm($conn);
$current_year = getAcademicYear($conn);
$available_terms = getAvailableTerms();

// Academic year options from payments + ensure current
$year_options = [];
$yrs = $conn->query("SELECT DISTINCT academic_year FROM payments WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs) {
    while ($yr = $yrs->fetch_assoc()) {
        if (!empty($yr['academic_year'])) { $year_options[] = $yr['academic_year']; }
    }
    $yrs->close();
}
if (!in_array($current_year, $year_options, true)) { array_unshift($year_options, $current_year); }

// Build query
$where = [];
$params = [];
$types = '';
if ($selected_term !== '') { $where[] = 'p.term = ?'; $params[] = $selected_term; $types .= 's'; }
if ($selected_year !== '') { $where[] = 'p.academic_year = ?'; $params[] = $selected_year; $types .= 's'; }
$where_sql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

$sql = "SELECT p.id, s.first_name, s.last_name, s.class, p.amount, p.payment_date, p.receipt_no, p.description, p.term, p.academic_year, p.payment_type, f.name as fee_name
        FROM payments p
        LEFT JOIN students s ON p.student_id = s.id
        LEFT JOIN fees f ON p.fee_id = f.id" .
        $where_sql .
        " ORDER BY p.payment_date DESC, p.id DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($types) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Summary stats
$total_payments = 0;
$total_amount = 0;
$payments = [];
while($row = $result->fetch_assoc()) {
    $payments[] = $row;
    $total_payments++;
    $total_amount += $row['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="clean-page-title"><i class="fas fa-credit-card me-2"></i>Payment History</h1>
                    <p class="clean-page-subtitle">
                        All student payments, receipts, and details
                        <span class="clean-badge clean-badge-primary ms-2"><i class="fas fa-calendar-alt me-1"></i><?php echo htmlspecialchars($selected_term !== '' ? $selected_term : $current_term); ?></span>
                        <span class="clean-badge clean-badge-info ms-1"><i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $selected_year !== '' ? $selected_year : $current_year)); ?></span>
                    </p>
                </div>
                <div class="d-flex gap-2 d-print-none">
                    <a href="#" onclick="window.print()" class="btn-clean-outline">
                        <i class="fas fa-print"></i> PRINT
                    </a>
                    <a href="record_payment_form.php" class="btn-clean-primary">
                        <i class="fas fa-plus"></i> RECORD PAYMENT
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">
        <!-- Statistics Cards -->
        <div class="clean-stats-grid d-print-none">
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo $total_payments; ?></div>
                <div class="clean-stat-label">Total Payments</div>
            </div>
            <div class="clean-stat-item">
                <div class="clean-stat-value">GH₵<?php echo number_format($total_amount, 2); ?></div>
                <div class="clean-stat-label">Total Amount Paid</div>
            </div>
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo date('F Y'); ?></div>
                <div class="clean-stat-label">Current Period</div>
            </div>
        </div>
        <!-- Filters -->
        <div class="clean-filter-bar mb-4 d-print-none">
            <form method="GET" action="">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label"><i class="fas fa-calendar-week me-2"></i>Term</label>
                        <select class="form-select" name="term">
                            <option value="">All Terms</option>
                            <?php foreach ($available_terms as $t): ?>
                                <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($selected_term === $t) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t); ?>
                                    <?php echo $t === $current_term ? ' (Current)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><i class="fas fa-graduation-cap me-2"></i>Academic Year</label>
                        <select class="form-select" name="year">
                            <option value="">All Years</option>
                            <?php foreach ($year_options as $yr): $label = formatAcademicYearDisplay($conn, $yr); ?>
                                <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo ($selected_year === $yr) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-search me-2"></i>Search</label>
                        <input type="text" class="clean-search-input" id="searchInput" placeholder="Search by student, receipt, or description...">
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-2 ms-auto">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Filter</button>
                    </div>
                </div>
            </form>
        </div>
        <!-- Print Header -->
        <div class="print-header text-center">
            <h3 class="mb-0"><?php echo htmlspecialchars($school_name); ?></h3>
            <div class="small text-muted">Payment History Report</div>
            <div class="mt-1">Term: <strong><?php echo htmlspecialchars($selected_term !== '' ? $selected_term : 'All Terms'); ?></strong> | Academic Year: <strong><?php echo htmlspecialchars($selected_year !== '' ? formatAcademicYearDisplay($conn, $selected_year) : 'All Years'); ?></strong></div>
            <div class="small text-muted">Printed on <?php echo date('M j, Y'); ?></div>
        </div>
        <!-- Payment Table -->
        <div class="clean-card">
            <div class="clean-table-scroll">
                <table class="clean-table" id="paymentsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Student/Category</th>
                            <th>Class</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Term</th>
                            <th>Year</th>
                            <th>Receipt No.</th>
                            <th>Description</th>
                            <th class="d-print-none">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $row): ?>
                        <tr>
                            <td><span class="clean-badge clean-badge-primary">#<?php echo $row['id']; ?></span></td>
                            <td>
                                <?php if ($row['payment_type'] === 'general'): ?>
                                    <span class="clean-badge clean-badge-warning">General</span>
                                <?php else: ?>
                                    <span class="clean-badge clean-badge-success">Student</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['payment_type'] === 'general'): ?>
                                    <em class="text-muted"><?php echo !empty($row['fee_name']) ? htmlspecialchars($row['fee_name']) : 'General Payment'; ?></em>
                                <?php else: ?>
                                    <strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['payment_type'] !== 'general'): ?>
                                    <span class="clean-badge clean-badge-info"><?php echo htmlspecialchars($row['class']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><strong class="text-success">GH₵<?php echo number_format($row['amount'], 2); ?></strong></td>
                            <td><?php echo date('M j, Y', strtotime($row['payment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['term'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(!empty($row['academic_year']) ? formatAcademicYearDisplay($conn, $row['academic_year']) : ''); ?></td>
                            <td><?php echo htmlspecialchars($row['receipt_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td class="d-print-none">
                                <a href="receipt.php?payment_id=<?php echo $row['id']; ?>" class="btn-clean-outline btn-clean-sm" target="_blank" title="View/Print Receipt">
                                    <i class="fas fa-receipt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (empty($payments)): ?>
                <div class="clean-empty-state">
                    <div class="clean-empty-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <h4 class="clean-empty-title">No Payments Found</h4>
                    <p class="clean-empty-text">No payment records available yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        // Simple search filter
        document.getElementById('searchInput').addEventListener('input', function() {
            const value = this.value.toLowerCase();
            const rows = document.querySelectorAll('#paymentsTable tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(value) ? '' : 'none';
            });
        });
    </script>
</body>
</html>