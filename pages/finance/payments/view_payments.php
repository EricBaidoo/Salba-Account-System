<?php
include '../../includes/auth_functions.php';
require_finance_access();
?>
<?php
include '../../includes/db_connect.php';
include '../../includes/system_settings.php';
$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');

// Filters: term + academic year
$current_term = getCurrentTerm($conn);
$current_year = getAcademicYear($conn);

$selected_term = isset($_GET['term']) ? trim($_GET['term']) : $current_term;
$selected_year = isset($_GET['year']) ? trim($_GET['year']) : $current_year;
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

// Fetch summary by fee category with same filters
$category_summary = [];

// Build WHERE clause for subqueries - properly handle empty WHERE
$add_where_student = empty($where) ? ' WHERE ' : ($where_sql . ' AND ');
$add_where_general = empty($where) ? ' WHERE ' : ($where_sql . ' AND ');

// Replace w-full border-collapse aliases for student query
$where_payments = str_replace('p.term', 'payments.term', $add_where_student);
$where_payments = str_replace('p.academic_year', 'payments.academic_year', $where_payments);
$where_payments = str_replace('s.status', 'students.status', $where_payments);

// Student payments: get categories from payment_allocations
$student_summary_sql = "SELECT 
                    f.name AS category,
                    'student' as payment_type,
                    SUM(pa.amount) as total
                FROM payment_allocations pa
                JOIN student_fees sf ON pa.student_fee_id = sf.id
                JOIN fees f ON sf.fee_id = f.id
                JOIN payments ON pa.payment_id = payments.id
                LEFT JOIN students ON payments.student_id = students.id" .
                $where_payments . "payments.payment_type = 'student'
                GROUP BY f.id, f.name";

// General payments with fee category
$general_with_fee_sql = "SELECT 
                    CONCAT(f.name, ' (General)') AS category,
                    'general' as payment_type,
                    SUM(p.amount) as total
                FROM payments p
                JOIN fees f ON p.fee_id = f.id
                LEFT JOIN students s ON p.student_id = s.id" .
                $add_where_general . "p.payment_type = 'general' AND p.fee_id IS NOT NULL
                GROUP BY f.id, f.name";

// General payments without fee category
$general_no_fee_sql = "SELECT 
                    'General Payment (Unallocated)' AS category,
                    'general' as payment_type,
                    SUM(p.amount) as total
                FROM payments p
                LEFT JOIN students s ON p.student_id = s.id" .
                $add_where_general . "p.payment_type = 'general' AND p.fee_id IS NULL";

// Combine all three
$summary_sql = "($student_summary_sql) UNION ALL ($general_with_fee_sql) UNION ALL ($general_no_fee_sql) ORDER BY total DESC";

if (!empty($params)) {
    // Need to bind params 3 times (once for each subquery)
    $union_params = array_merge($params, $params, $params);
    $union_types = $types . $types . $types;
    $sum_stmt = $conn->prepare($summary_sql);
    if ($union_types) { $sum_stmt->bind_param($union_types, ...$union_params); }
    $sum_stmt->execute();
    $sum_result = $sum_stmt->get_result();
    while ($row = $sum_result->fetch_assoc()) {
        if ($row['total'] > 0) {  // Only include categories with payments
            $category_summary[] = $row;
        }
    }
} else {
    $sum_result = $conn->query($summary_sql);
    while ($row = $sum_result->fetch_assoc()) {
        if ($row['total'] > 0) {
            $category_summary[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Salba Montessori Accounting</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="w-full px-4">
            <div class="flex justify-between items-center mb-">
                <a href="../dashboard.php" class="clean-back-px-3 py-2 rounded">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="clean-page-title"><i class="fas fa-credit-bg-white rounded shadow mr-2"></i>Payment History</h1>
                    <p class="clean-page-subtitle">
                        All student payments, receipts, and details
                        <span class="clean-badge clean-badge-primary ml-2"><i class="fas fa-calendar-alt mr-1"></i><?php echo htmlspecialchars($selected_term !== '' ? $selected_term : $current_term); ?></span>
                        <span class="clean-badge clean-badge-info ml-1"><i class="fas fa-graduation-cap mr-1"></i><?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $selected_year !== '' ? $selected_year : $current_year)); ?></span>
                    </p>
                </div>
                <div class="flex gap-2 print:hidden">
                    <a href="#" onclick="window.print()" class="px-3 py-2 rounded-clean-outline">
                        <i class="fas fa-print"></i> PRINT
                    </a>
                    <a href="record_payment_form.php" class="px-3 py-2 rounded-clean-primary">
                        <i class="fas fa-plus"></i> RECORD PAYMENT
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="w-full px-4 py-4">
        <!-- Statistics Cards -->
        <div class="clean-stats-grid print:hidden">
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo $total_payments; ?></div>
                <div class="clean-stat-label">Total Payments</div>
            </div>
            <div class="clean-stat-item">
                <div class="clean-stat-value">GHâ‚µ<?php echo number_format($total_amount, 2); ?></div>
                <div class="clean-stat-label">Total Amount Paid</div>
            </div>
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo count($category_summary); ?></div>
                <div class="clean-stat-label">Fee Categories</div>
            </div>
        </div>
        
        <!-- Category Summary Cards -->
        <?php if (count($category_summary) > 0): ?>
        <div class="row mb- print:hidden">
            <?php foreach ($category_summary as $cat): 
                $is_general = $cat['payment_type'] === 'general';
                $card_class = $is_general ? 'border-primary' : '';
                $icon_class = $is_general ? 'text-primary' : 'text-green-600';
                $icon = $is_general ? 'fa-hand-holding-usd' : 'fa-money-bill-wave';
            ?>
                <div class="col-md-3 col-lg-2 mb-">
                    <div class="clean-bg-white rounded shadow text-center <?php echo $card_class; ?>">
                        <div class="p-2">
                            <div class="mb-">
                                <i class="fas <?php echo $icon; ?> <?php echo $icon_class; ?> payment-category-bg-white rounded shadow-icon"></i>
                            </div>
                            <div class="payment-category-bg-white rounded shadow-name mb-"><?php echo htmlspecialchars($cat['category']); ?></div>
                            <div class="h6 <?php echo $icon_class; ?> mb-">GHâ‚µ<?php echo number_format($cat['total'], 2); ?></div>
                            <small class="text-gray-600 payment-category-bg-white rounded shadow-label">Total</small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="clean-filter-bar mb- print:hidden">
            <form method="GET" action="">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="col-md-3">
                        <label class="block text-sm font-medium mb-"><i class="fas fa-calendar-week mr-2"></i>Term</label>
                        <select class="border border-gray-300 rounded px-3 py-2 bg-white" name="term">
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
                        <label class="block text-sm font-medium mb-"><i class="fas fa-graduation-cap mr-2"></i>Academic Year</label>
                        <select class="border border-gray-300 rounded px-3 py-2 bg-white" name="year">
                            <option value="">All Years</option>
                            <?php foreach ($year_options as $yr): $label = formatAcademicYearDisplay($conn, $yr); ?>
                                <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo ($selected_year === $yr) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="block text-sm font-medium mb-"><i class="fas fa-search mr-2"></i>Search</label>
                        <input type="text" class="clean-search-input" id="searchInput" placeholder="Search by student, receipt, or description...">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 w-full"><i class="fas fa-filter mr-2"></i>Filter</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Print Header -->
        <div class="print-header text-center">
            <h3 class="mb-"><?php echo htmlspecialchars($school_name); ?></h3>
            <div class="small text-gray-600">Payment History Report</div>
            <div class="mt-1">Term: <strong><?php echo htmlspecialchars($selected_term !== '' ? $selected_term : 'All Terms'); ?></strong> | Academic Year: <strong><?php echo htmlspecialchars($selected_year !== '' ? formatAcademicYearDisplay($conn, $selected_year) : 'All Years'); ?></strong></div>
            <div class="small text-gray-600">Printed on <?php echo date('M j, Y'); ?></div>
        </div>
        <!-- Payment w-full border-collapse -->
        <div class="clean-bg-white rounded shadow">
            <div class="payment-w-full border-collapse-scroll">
                <table class="clean-w-full border-collapse" id="paymentsw-full border-collapse">
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
                            <th class="print:hidden">Actions</th>
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
                                    <em class="text-gray-600"><?php echo !empty($row['fee_name']) ? htmlspecialchars($row['fee_name']) : 'General Payment'; ?></em>
                                <?php else: ?>
                                    <strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['payment_type'] !== 'general'): ?>
                                    <span class="clean-badge clean-badge-info"><?php echo htmlspecialchars($row['class']); ?></span>
                                <?php else: ?>
                                    <span class="text-gray-600">-</span>
                                <?php endif; ?>
                            </td>
                            <td><strong class="text-green-600">GHâ‚µ<?php echo number_format($row['amount'], 2); ?></strong></td>
                            <td><?php echo date('M j, Y', strtotime($row['payment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['term'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(!empty($row['academic_year']) ? formatAcademicYearDisplay($conn, $row['academic_year']) : ''); ?></td>
                            <td><?php echo htmlspecialchars($row['receipt_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td class="print:hidden">
                                <a href="receipt.php?payment_id=<?php echo $row['id']; ?>" class="px-3 py-2 rounded-clean-outline px-3 py-2 rounded-clean-sm" target="_blank" title="View/Print Receipt">
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
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        // Simple search filter
        document.getElementById('searchInput').addEventListener('input', function() {
            const value = this.value.toLowerCase();
            const rows = document.querySelectorAll('#paymentsw-full border-collapse tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(value) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
