<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
include '../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Fetch fees and classes for the form
$fees_query = "
    SELECT f.id, f.name, f.amount, f.fee_type,
           GROUP_CONCAT(
               CASE 
                   WHEN fa.class_name IS NOT NULL THEN CONCAT(fa.class_name, ':GH₵', FORMAT(fa.amount, 2))
                   WHEN fa.category IS NOT NULL THEN CONCAT(
                       CASE fa.category 
                           WHEN 'early_years' THEN 'Early Years'
                           WHEN 'primary' THEN 'Primary'
                       END, ':GH₵', FORMAT(fa.amount, 2)
                   )
               END
               ORDER BY fa.amount
               SEPARATOR ' | '
           ) as amount_details
    FROM fees f
    LEFT JOIN fee_amounts fa ON f.id = fa.fee_id
    GROUP BY f.id, f.name, f.amount, f.fee_type
    ORDER BY f.name";
$fees_result = $conn->query($fees_query);

$classes_result = $conn->query("SELECT DISTINCT name FROM classes ORDER BY name");

// Academic year options
$default_academic_year = getSystemSetting($conn, 'academic_year', date('Y') . '/' . (date('Y') + 1));
$year_options = [];
$yrs = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs) {
    while ($yr = $yrs->fetch_assoc()) {
        if (!empty($yr['academic_year'])) {
            $year_options[] = $yr['academic_year'];
        }
    }
    $yrs->close();
}
if (!in_array($default_academic_year, $year_options, true)) {
    array_unshift($year_options, $default_academic_year);
}
$display_year = formatAcademicYearDisplay($conn, $default_academic_year);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Term Bills - Salba Montessori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="clean-body">
    <div class="clean-container">
        <div class="clean-header">
            <div class="clean-header-content">
                <a href="dashboard.php" class="clean-back-link">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
                <div class="clean-header-title">
                    <h1><i class="fas fa-file-invoice-dollar"></i>Generate Term Bills</h1>
                    <p>Select fees, assign them to students, and generate printable invoices</p>
                </div>
            </div>
        </div>

        <form method="POST" action="process_term_bills.php" id="termBillForm">
            <div class="clean-card">
                <div class="clean-card-header">
                    <h5><i class="fas fa-cog"></i>Bill Settings</h5>
                </div>
                <div class="clean-card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="clean-form-label">
                                <i class="fas fa-calendar-check"></i>Academic Term
                                <span class="required-indicator">*</span>
                            </label>
                            <select class="clean-form-control" name="term" id="term" required>
                                <option value="">Select Term</option>
                                <option value="First Term">First Term</option>
                                <option value="Second Term">Second Term</option>
                                <option value="Third Term">Third Term</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="clean-form-label">
                                <i class="fas fa-graduation-cap"></i>Academic Year
                                <span class="required-indicator">*</span>
                            </label>
                            <select class="clean-form-control" name="academic_year" id="academic_year" required>
                                <?php foreach ($year_options as $yr): $label = formatAcademicYearDisplay($conn, $yr); ?>
                                    <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo ($yr === $default_academic_year) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="clean-form-label">
                                <i class="fas fa-calendar-alt"></i>Due Date
                                <span class="required-indicator">*</span>
                            </label>
                            <input type="date" class="clean-form-control" name="due_date" id="due_date" required>
                        </div>
                        <div class="col-md-4">
                            <label class="clean-form-label">
                                <i class="fas fa-filter"></i>Filter by Class
                            </label>
                            <select class="clean-form-control" name="class_filter" id="class_filter">
                                <option value="all">All Classes</option>
                                <?php while ($class = $classes_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($class['name']); ?>">
                                        <?php echo htmlspecialchars($class['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="clean-card">
                <div class="clean-card-header">
                    <h5><i class="fas fa-money-check-alt"></i>Select Fees to Bill</h5>
                </div>
                <div class="clean-card-body">
                    <div class="clean-info-box">
                        <i class="fas fa-info-circle"></i>
                        <span>Click fees to select. Selected fees will be assigned to students and printed on invoices.</span>
                    </div>
                    <div class="row g-3">
                        <?php 
                        $fees_result->data_seek(0);
                        while ($fee = $fees_result->fetch_assoc()): 
                            $type_badge = '';
                            $amount_display = '';
                            
                            switch($fee['fee_type']) {
                                case 'fixed':
                                    $type_badge = '<span class="clean-badge clean-badge-success">Fixed</span>';
                                    $amount_display = 'GH₵' . number_format($fee['amount'], 2);
                                    break;
                                case 'class_based':
                                    $type_badge = '<span class="clean-badge clean-badge-primary">Class-Based</span>';
                                    $amount_display = 'Varies';
                                    break;
                                case 'category':
                                    $type_badge = '<span class="clean-badge clean-badge-warning">Category</span>';
                                    $amount_display = 'Varies';
                                    break;
                            }
                        ?>
                        <div class="col-md-6">
                            <div class="clean-select-card" data-fee-id="<?php echo $fee['id']; ?>">
                                <div class="clean-select-card-content">
                                    <div class="clean-select-card-info">
                                        <h6><?php echo htmlspecialchars($fee['name']); ?> <?php echo $type_badge; ?></h6>
                                        <p class="clean-select-card-amount"><?php echo $amount_display; ?></p>
                                        <?php if ($fee['amount_details']): ?>
                                            <small class="clean-select-card-details"><?php echo htmlspecialchars($fee['amount_details']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="clean-select-indicator">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <input type="hidden" name="selected_fees" id="selected_fees" value="">
                </div>
            </div>

            <div class="clean-card">
                <div class="clean-card-body text-center">
                    <div id="preview" class="clean-warning-box mb-3" style="display: none;">
                        <i class="fas fa-bolt"></i>
                        <strong>Ready to generate:</strong> <span id="feeCount">0</span> fees for <span id="studentCount">all active</span> students
                        <span class="ms-2">(Year: <span id="yearPreview"><?php echo htmlspecialchars($display_year); ?></span>)</span>
                    </div>
                    <button type="submit" class="clean-btn-primary clean-btn-lg" id="submitBtn" disabled>
                        <i class="fas fa-bolt"></i>
                        Assign Fees & Generate Invoices
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const selectedFees = new Set();
        const feeCards = document.querySelectorAll('.clean-select-card');
        const selectedFeesInput = document.getElementById('selected_fees');
        const submitBtn = document.getElementById('submitBtn');
        const preview = document.getElementById('preview');
        const termSelect = document.getElementById('term');
        const dueDateInput = document.getElementById('due_date');
        const classFilter = document.getElementById('class_filter');
        const yearSelect = document.getElementById('academic_year');
        const yearPreview = document.getElementById('yearPreview');

        feeCards.forEach(card => {
            card.addEventListener('click', function() {
                const feeId = this.dataset.feeId;
                if (selectedFees.has(feeId)) {
                    selectedFees.delete(feeId);
                    this.classList.remove('selected');
                } else {
                    selectedFees.add(feeId);
                    this.classList.add('selected');
                }
                updateForm();
            });
        });

        function updateForm() {
            selectedFeesInput.value = Array.from(selectedFees).join(',');
            document.getElementById('feeCount').textContent = selectedFees.size;
            
            const classValue = classFilter.value;
            document.getElementById('studentCount').textContent = classValue === 'all' ? 'all active' : classValue;
            
            const isValid = selectedFees.size > 0 && termSelect.value && dueDateInput.value && yearSelect.value;
            submitBtn.disabled = !isValid;
            preview.style.display = isValid ? 'block' : 'none';
            if (yearSelect.value) {
                const sel = yearSelect.options[yearSelect.selectedIndex];
                yearPreview.textContent = sel ? sel.text : yearSelect.value;
            }
        }

        termSelect.addEventListener('change', updateForm);
        dueDateInput.addEventListener('change', updateForm);
        classFilter.addEventListener('change', updateForm);
        yearSelect.addEventListener('change', updateForm);

        // Set minimum date to today
        dueDateInput.min = new Date().toISOString().split('T')[0];
        updateForm();
    </script>
</body>
</html>
