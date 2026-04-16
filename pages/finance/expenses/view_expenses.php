<?php
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}

// Get current semester and academic year
$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);
$available_terms = getAvailableSemesters();

// Filters: semester, academic year, and category
$selected_term = isset($_GET['semester']) ? trim($_GET['semester']) : $current_term;
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
    $where[] = 'e.semester = ?'; 
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
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
                    <h1 class="clean-page-title"><i class="fas fa-list-alt mr-2"></i>Expenses Overview</h1>
                    <p class="clean-page-subtitle">
                        Track and analyze all expenses by category for better financial management
                        <span class="clean-badge clean-badge-primary ml-2"><i class="fas fa-calendar-alt mr-1"></i><?php echo htmlspecialchars($selected_term !== '' ? $selected_term : $current_term); ?></span>
                        <span class="clean-badge clean-badge-info ml-1"><i class="fas fa-graduation-cap mr-1"></i><?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $selected_year !== '' ? $selected_year : $current_year)); ?></span>
                    </p>
                </div>
                <div class="flex gap-2 print:hidden">
                    <a href="#" onclick="window.print()" class="px-3 py-2 rounded-clean-outline">
                        <i class="fas fa-print"></i> PRINT
                    </a>
                    <a href="add_expense_form.php" class="px-3 py-2 rounded-clean-primary">
                        <i class="fas fa-plus"></i> ADD EXPENSE
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="w-full px-4 py-4">
        <!-- Stats Summary -->
        <div class="clean-stats-grid print:hidden">
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo $total_count; ?></div>
                <div class="clean-stat-label">Total Expenses</div>
            </div>
            <div class="clean-stat-item">
                <div class="clean-stat-value">GHâ‚µ<?php echo number_format($total_expenses, 2); ?></div>
                <div class="clean-stat-label">Total Amount Spent</div>
            </div>
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo count($summary); ?></div>
                <div class="clean-stat-label">Categories</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="clean-filter-bar mb- print:hidden">
            <form method="GET" action="">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="col-md-3">
                        <label class="block text-sm font-medium mb-"><i class="fas fa-calendar-week mr-2"></i>Semester</label>
                        <select class="border border-gray-300 rounded px-3 py-2 bg-white" name="semester">
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
                    <div class="col-md-2">
                        <label class="block text-sm font-medium mb-"><i class="fas fa-folder mr-2"></i>Category</label>
                        <select class="border border-gray-300 rounded px-3 py-2 bg-white" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($selected_category == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="block text-sm font-medium mb-"><i class="fas fa-search mr-2"></i>Search</label>
                        <input type="text" class="clean-search-input" id="searchInput" placeholder="Search by category or description...">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 w-full"><i class="fas fa-filter mr-2"></i>Filter</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Category Summary -->
        <?php if (count($summary) > 0): ?>
        <div class="row mb- print:hidden">
            <?php foreach ($summary as $cat): ?>
                <div class="col-md-4 col-lg-3 mb-">
                    <div class="clean-bg-white rounded shadow text-center">
                        <div class="p-4">
                            <div class="mb-"><i class="fas fa-folder-open fa-2x text-primary"></i></div>
                            <h5 class="mb-"><?php echo htmlspecialchars($cat['category'] ?? 'Uncategorized'); ?></h5>
                            <div class="h3 text-green-600 mb-">GHâ‚µ<?php echo number_format($cat['total'], 2); ?></div>
                            <small class="text-gray-600">Total Spent</small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="clean-alert clean-alert-info mb-">
            <i class="fas fa-info-circle"></i>
            <span>No expenses recorded yet.</span>
        </div>
        <?php endif; ?>
        <!-- Print Header -->
        <?php $school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori'); ?>
        <div class="print-header text-center">
            <h3 class="mb-"><?php echo htmlspecialchars($school_name); ?></h3>
            <div class="small text-gray-600">Expenses Overview</div>
            <div class="mt-1">Semester: <strong><?php echo htmlspecialchars($selected_term !== '' ? $selected_term : 'All Terms'); ?></strong> | Academic Year: <strong><?php echo htmlspecialchars($selected_year !== '' ? formatAcademicYearDisplay($conn, $selected_year) : 'All Years'); ?></strong></div>
            <div class="small text-gray-600">Printed on <?php echo date('M j, Y'); ?></div>
        </div>

        <!-- Expenses w-full border-collapse -->
        <div class="clean-bg-white rounded shadow">
            <div class="clean-bg-white rounded shadow-header">
                <h5 class="clean-bg-white rounded shadow-title"><i class="fas fa-w-full border-collapse mr-2"></i>All Expenses</h5>
            </div>
            <div class="clean-w-full border-collapse-scroll">
                <table class="clean-w-full border-collapse">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Expense Date</th>
                            <th>Semester</th>
                            <th>Year</th>
                            <th>Description</th>
                            <th class="print:hidden">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><span class="clean-badge clean-badge-primary">#<?php echo $row['id']; ?></span></td>
                            <td><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><strong class="text-red-600">GHâ‚µ<?php echo number_format($row['amount'], 2); ?></strong></td>
                            <td><?php echo date('M j, Y', strtotime($row['expense_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['semester'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(!empty($row['academic_year']) ? formatAcademicYearDisplay($conn, $row['academic_year']) : ''); ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td class="print:hidden">
                                <div class="clean-actions">
                                    <a href="edit_expense.php?id=<?php echo $row['id']; ?>" class="px-3 py-2 rounded-clean-outline px-3 py-2 rounded-clean-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_expense.php?id=<?php echo $row['id']; ?>" class="px-3 py-2 rounded-clean-outline px-3 py-2 rounded-clean-sm text-red-600" onclick="return confirm('Delete this expense?');" title="Delete">
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
        <script>
        // Simple search filter
        document.getElementById('searchInput').addEventListener('input', function() {
            const value = this.value.toLowerCase();
            const rows = document.querySelectorAll('.clean-w-full border-collapse tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(value) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
