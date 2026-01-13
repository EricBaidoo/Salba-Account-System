<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
include '../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}

// Get current term and academic year
$current_term = getCurrentTerm($conn);
$current_year = getAcademicYear($conn);
$available_terms = getAvailableTerms();

// Filters: term, academic year, and category
$selected_term = isset($_GET['term']) ? trim($_GET['term']) : $current_term;
$selected_year = isset($_GET['year']) ? trim($_GET['year']) : $current_year;
$selected_category = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Academic year options from expenses + ensure current
$year_options = [];
$yrs = $conn->query("SELECT DISTINCT academic_year FROM expenses WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs) {
    while ($yr = $yrs->fetch_assoc()) {
        if (!empty($yr['academic_year'])) { $year_options[] = $yr['academic_year']; }
    }
    $yrs->close();
}
if (!in_array($current_year, $year_options, true)) { array_unshift($year_options, $current_year); }

// Get all expense categories for filter
$categories_result = $conn->query("SELECT id, name FROM expense_categories ORDER BY name");
$categories = [];
while ($cat = $categories_result->fetch_assoc()) {
    $categories[] = $cat;
}

// Build query with filters
$where = [];
$params = [];
$types = '';
if ($selected_term !== '') { 
    $where[] = 'e.term = ?'; 
    $params[] = $selected_term; 
    $types .= 's'; 
}
if ($selected_year !== '') { 
    $where[] = 'e.academic_year = ?'; 
    $params[] = $selected_year; 
    $types .= 's'; 
}
if ($selected_category > 0) { 
    $where[] = 'e.category_id = ?'; 
    $params[] = $selected_category; 
    $types .= 'i'; 
}
$where_sql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

// Fetch filtered expenses
$sql = "SELECT e.*, ec.name AS category_name 
        FROM expenses e 
        LEFT JOIN expense_categories ec ON e.category_id = ec.id" .
        $where_sql .
        " ORDER BY e.expense_date DESC, e.id DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($types) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Fetch summary by category with same filters
$summary_sql = "SELECT ec.name AS category, SUM(e.amount) as total 
                FROM expenses e 
                LEFT JOIN expense_categories ec ON e.category_id = ec.id" .
                $where_sql .
                " GROUP BY ec.id, ec.name 
                ORDER BY ec.name";

if (!empty($params)) {
    $sum_stmt = $conn->prepare($summary_sql);
    if ($types) { $sum_stmt->bind_param($types, ...$params); }
    $sum_stmt->execute();
    $sum_result = $sum_stmt->get_result();
    $summary = [];
    while ($row = $sum_result->fetch_assoc()) {
        $summary[] = $row;
    }
} else {
    $summary = [];
    $sum_query = $conn->query($summary_sql);
    while ($row = $sum_query->fetch_assoc()) {
        $summary[] = $row;
    }
}

// Calculate totals
$total_expenses = 0;
$total_count = 0;
foreach ($summary as $s) {
    $total_expenses += $s['total'];
}
$total_count = $result->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Expenses - Salba Montessori Accounting</title>
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="clean-page-title"><i class="fas fa-list-alt me-2"></i>Expenses Overview</h1>
                    <p class="clean-page-subtitle">
                        Track and analyze all expenses by category for better financial management
                        <span class="clean-badge clean-badge-primary ms-2"><i class="fas fa-calendar-alt me-1"></i><?php echo htmlspecialchars($selected_term !== '' ? $selected_term : $current_term); ?></span>
                        <span class="clean-badge clean-badge-info ms-1"><i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $selected_year !== '' ? $selected_year : $current_year)); ?></span>
                    </p>
                </div>
                <div class="d-flex gap-2 d-print-none">
                    <a href="#" onclick="window.print()" class="btn-clean-outline">
                        <i class="fas fa-print"></i> PRINT
                    </a>
                    <a href="add_expense_form.php" class="btn-clean-primary">
                        <i class="fas fa-plus"></i> ADD EXPENSE
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">
        <!-- Stats Summary -->
        <div class="clean-stats-grid d-print-none">
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo $total_count; ?></div>
                <div class="clean-stat-label">Total Expenses</div>
            </div>
            <div class="clean-stat-item">
                <div class="clean-stat-value">GH₵<?php echo number_format($total_expenses, 2); ?></div>
                <div class="clean-stat-label">Total Amount Spent</div>
            </div>
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo count($summary); ?></div>
                <div class="clean-stat-label">Categories</div>
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
                    <div class="col-md-2">
                        <label class="form-label"><i class="fas fa-folder me-2"></i>Category</label>
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($selected_category == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><i class="fas fa-search me-2"></i>Search</label>
                        <input type="text" class="clean-search-input" id="searchInput" placeholder="Search by category or description...">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Filter</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Category Summary -->
        <?php if (count($summary) > 0): ?>
        <div class="row mb-4 d-print-none">
            <?php foreach ($summary as $cat): ?>
                <div class="col-md-4 col-lg-3 mb-3">
                    <div class="clean-card text-center">
                        <div class="p-4">
                            <div class="mb-2"><i class="fas fa-folder-open fa-2x text-primary"></i></div>
                            <h5 class="mb-2"><?php echo htmlspecialchars($cat['category'] ?? 'Uncategorized'); ?></h5>
                            <div class="h3 text-success mb-0">GH₵<?php echo number_format($cat['total'], 2); ?></div>
                            <small class="text-muted">Total Spent</small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="clean-alert clean-alert-info mb-4">
            <i class="fas fa-info-circle"></i>
            <span>No expenses recorded yet.</span>
        </div>
        <?php endif; ?>
        <!-- Print Header -->
        <?php $school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori'); ?>
        <div class="print-header text-center">
            <h3 class="mb-0"><?php echo htmlspecialchars($school_name); ?></h3>
            <div class="small text-muted">Expenses Overview</div>
            <div class="mt-1">Term: <strong><?php echo htmlspecialchars($selected_term !== '' ? $selected_term : 'All Terms'); ?></strong> | Academic Year: <strong><?php echo htmlspecialchars($selected_year !== '' ? formatAcademicYearDisplay($conn, $selected_year) : 'All Years'); ?></strong></div>
            <div class="small text-muted">Printed on <?php echo date('M j, Y'); ?></div>
        </div>

        <!-- Expenses Table -->
        <div class="clean-card">
            <div class="clean-card-header">
                <h5 class="clean-card-title"><i class="fas fa-table me-2"></i>All Expenses</h5>
            </div>
            <div class="clean-table-scroll">
                <table class="clean-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Expense Date</th>
                            <th>Term</th>
                            <th>Year</th>
                            <th>Description</th>
                            <th class="d-print-none">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><span class="clean-badge clean-badge-primary">#<?php echo $row['id']; ?></span></td>
                            <td><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><strong class="text-danger">GH₵<?php echo number_format($row['amount'], 2); ?></strong></td>
                            <td><?php echo date('M j, Y', strtotime($row['expense_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['term'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(!empty($row['academic_year']) ? formatAcademicYearDisplay($conn, $row['academic_year']) : ''); ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td class="d-print-none">
                                <div class="clean-actions">
                                    <a href="edit_expense.php?id=<?php echo $row['id']; ?>" class="btn-clean-outline btn-clean-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_expense.php?id=<?php echo $row['id']; ?>" class="btn-clean-outline btn-clean-sm text-danger" onclick="return confirm('Delete this expense?');" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple search filter
        document.getElementById('searchInput').addEventListener('input', function() {
            const value = this.value.toLowerCase();
            const rows = document.querySelectorAll('.clean-table tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(value) ? '' : 'none';
            });
        });
    </script>
</body>
</html>