<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Fetch all fees
$fees_query = "
    SELECT f.id, f.name, f.amount, f.fee_type,
           GROUP_CONCAT(
               CASE 
                   WHEN fa.class_name IS NOT NULL THEN CONCAT(fa.class_name, ':GHâ‚µ', FORMAT(fa.amount, 2))
                   WHEN fa.category IS NOT NULL THEN CONCAT(
                       CASE fa.category 
                           WHEN 'early_years' THEN 'Early Years'
                           WHEN 'primary' THEN 'Primary'
                       END, ':GHâ‚µ', FORMAT(fa.amount, 2)
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

// Fetch all classes
$classes_result = $conn->query("SELECT DISTINCT name FROM classes ORDER BY name");

// Count active students
$total_students = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'active'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Term Billing - Salba Montessori Accounting</title>
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
            <div class="text-center">
                <h1 class="clean-page-title"><i class="fas fa-file-invoice-dollar mr-2"></i>Bulk Term Billing</h1>
                <p class="clean-page-subtitle">
                    Assign fees to multiple students at once for the academic term
                    <?php $ct = getCurrentTerm($conn); $ay = getAcademicYear($conn); ?>
                    <span class="clean-badge clean-badge-primary ml-2"><i class="fas fa-calendar-alt mr-1"></i><?php echo htmlspecialchars($ct); ?></span>
                    <span class="clean-badge clean-badge-info ml-1"><i class="fas fa-graduation-cap mr-1"></i><?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $ay)); ?></span>
                </p>
            </div>
        </div>
    </div>

    <div class="w-full px-4 py-4">
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="clean-alert clean-alert-success mb-">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Bulk Billing Successful!</strong>
                    <p class="mb-">
                        <strong><?php echo intval($_GET['count'] ?? 0); ?> fees</strong> were successfully assigned to students.
                        <?php if (isset($_GET['skipped']) && $_GET['skipped'] > 0): ?>
                            <br><small><?php echo intval($_GET['skipped']); ?> assignments were skipped (already assigned).</small>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <form id="bulkBillingForm" method="POST" action="bulk_term_billing.php">
            <!-- Filter Section -->
            <div class="filter-section">
                <h5 class="mb-"><i class="fas fa-filter mr-2"></i>Billing Options</h5>
                <div class="flex flex-wrap gap-3">
                    <div class="col-md-3">
                        <label for="term" class="block text-sm font-medium mb- fw-bold">
                            <i class="fas fa-calendar-alt mr-1"></i>Academic Term *
                        </label>
                        <select class="border border-gray-300 rounded px-3 py-2 bg-white" id="term" name="term" required>
                            <option value="">Select Term</option>
                            <option value="First Term">First Term</option>
                            <option value="Second Term">Second Term</option>
                            <option value="Third Term">Third Term</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="due_date" class="block text-sm font-medium mb- fw-bold">
                            <i class="fas fa-calendar-day mr-1"></i>Due Date *
                        </label>
                        <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded" id="due_date" name="due_date" required>
                    </div>
                    <div class="col-md-3">
                        <label for="class_filter" class="block text-sm font-medium mb- fw-bold">
                            <i class="fas fa-school mr-1"></i>Filter by Class
                        </label>
                        <select class="border border-gray-300 rounded px-3 py-2 bg-white" id="class_filter" name="class_filter">
                            <option value="all">All Classes</option>
                            <?php while ($class = $classes_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($class['name']); ?>">
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="block text-sm font-medium mb- fw-bold">
                            <i class="fas fa-users mr-1"></i>Students Affected
                        </label>
                        <div class="w-full px-3 py-2 border border-gray-300 rounded bg-light" id="studentCount">
                            <strong class="text-primary"><?php echo $total_students; ?></strong> students
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <label for="notes" class="block text-sm font-medium mb- fw-bold">
                            <i class="fas fa-sticky-note mr-1"></i>Notes (Optional)
                        </label>
                        <textarea class="w-full px-3 py-2 border border-gray-300 rounded" id="notes" name="notes" rows="2" placeholder="Add any notes for this billing cycle..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Fee Selection Section -->
            <h5 class="mb-"><i class="fas fa-money-check-alt mr-2"></i>Select Fees to Assign</h5>
            <div class="p-4 bg-blue-100 text-blue-700 rounded border border-blue-200">
                <i class="fas fa-info-circle mr-2"></i>Click on the fees you want to assign to students. You can select multiple fees.
            </div>

            <div class="flex flex-wrap gap-3" id="feesmax-w-7xl mx-auto">
                <?php 
                $fees_result->data_seek(0); // Reset pointer
                while ($fee = $fees_result->fetch_assoc()): 
                    $fee_type_badge = '';
                    $amount_display = '';
                    
                    switch($fee['fee_type']) {
                        case 'fixed':
                            $fee_type_badge = '<span class="badge bg-success info-badge">Fixed</span>';
                            $amount_display = 'GHâ‚µ' . number_format($fee['amount'], 2);
                            break;
                        case 'class_based':
                            $fee_type_badge = '<span class="badge bg-primary info-badge">Class-Based</span>';
                            $amount_display = 'Varies by class';
                            break;
                        case 'category':
                            $fee_type_badge = '<span class="badge bg-warning info-badge">Category</span>';
                            $amount_display = 'Varies by level';
                            break;
                    }
                ?>
                <div class="col-md-4 col-lg-3">
                    <div class="bg-white rounded shadow fee-bg-white rounded shadow h-full" data-fee-id="<?php echo $fee['id']; ?>" data-fee-name="<?php echo htmlspecialchars($fee['name']); ?>">
                        <i class="fas fa-check-circle check-icon"></i>
                        <div class="bg-white rounded shadow-body">
                            <h6 class="bg-white rounded shadow-title mb-"><?php echo htmlspecialchars($fee['name']); ?></h6>
                            <div class="mb-"><?php echo $fee_type_badge; ?></div>
                            <p class="bg-white rounded shadow-text mb-"><strong><?php echo $amount_display; ?></strong></p>
                            <?php if ($fee['amount_details']): ?>
                                <small class="text-gray-600"><?php echo htmlspecialchars($fee['amount_details']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <!-- Hidden input to store selected fee IDs -->
            <input type="hidden" id="selectedFees" name="selected_fees" value="">

            <!-- Preview Section -->
            <div id="previewSection" class="preview-section mt-4">
                <h6 class="mb-"><i class="fas fa-eye mr-2"></i>Billing Preview</h6>
                <p class="mb-">
                    <strong>Term:</strong> <span id="previewTerm">-</span> | 
                    <strong>Due Date:</strong> <span id="previewDueDate">-</span> | 
                    <strong>Classes:</strong> <span id="previewClasses">-</span>
                </p>
                <p class="mb-">
                    <strong>Selected Fees (<span id="feeCount">0</span>):</strong> 
                    <span id="previewFees">None selected</span>
                </p>
                <p class="mb- text-gray-600">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <small>Students who already have these fees assigned for this term will be skipped.</small>
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="text-center mt-4">
                <button type="button" class="px-4 py-2 bg-gray-600 text-white rounded mr-2" onclick="window.location.href='../dashboard.php'">
                    <i class="fas fa-times mr-1"></i>Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 px-3 py-2 rounded-lg" id="submitBtn" disabled>
                    <i class="fas fa-paper-plane mr-2"></i>Preview & Assign Fees
                </button>
            </div>
        </form>
    </div>

        <script>
        const selectedFees = new Set();
        const feeCards = document.querySelectorAll('.fee-bg-white rounded shadow');
        const selectedFeesInput = document.getElementById('selectedFees');
        const submitBtn = document.getElementById('submitBtn');
        const previewSection = document.getElementById('previewSection');
        const termSelect = document.getElementById('term');
        const dueDateInput = document.getElementById('due_date');
        const classFilter = document.getElementById('class_filter');

        // Handle fee bg-white rounded shadow clicks
        feeCards.forEach(bg-white rounded shadow => {
            bg-white rounded shadow.addEventListener('click', function() {
                const feeId = this.getAttribute('data-fee-id');
                const feeName = this.getAttribute('data-fee-name');
                
                if (selectedFees.has(feeId)) {
                    selectedFees.delete(feeId);
                    this.classList.remove('selected');
                } else {
                    selectedFees.add(feeId);
                    this.classList.add('selected');
                }
                
                updatePreview();
                checkFormValidity();
            });
        });

        // Update class filter to show student count
        classFilter.addEventListener('change', updateStudentCount);

        async function updateStudentCount() {
            const classValue = classFilter.value;
            const countDisplay = document.getElementById('studentCount');
            
            try {
                const response = await fetch(`../../../includes/get_class_student_count.php?class=${encodeURIComponent(classValue)}`);
                const data = await response.json();
                if (data.count !== undefined) {
                    countDisplay.innerHTML = `<strong class="text-primary">${data.count}</strong> student${data.count !== 1 ? 's' : ''}`;
                }
            } catch (error) {
                console.error('Error fetching student count:', error);
            }
        }

        // Update preview section
        function updatePreview() {
            selectedFeesInput.value = Array.from(selectedFees).join(',');
            
            const feeCount = selectedFees.size;
            document.getElementById('feeCount').textContent = feeCount;
            
            if (feeCount > 0) {
                const feeNames = Array.from(selectedFees).map(id => {
                    const bg-white rounded shadow = document.querySelector(`[data-fee-id="${id}"]`);
                    return bg-white rounded shadow.getAttribute('data-fee-name');
                });
                document.getElementById('previewFees').textContent = feeNames.join(', ');
                previewSection.style.display = 'block';
            } else {
                previewSection.style.display = 'none';
            }
            
            document.getElementById('previewTerm').textContent = termSelect.value || '-';
            document.getElementById('previewDueDate').textContent = dueDateInput.value || '-';
            document.getElementById('previewClasses').textContent = classFilter.value === 'all' ? 'All Classes' : classFilter.value;
        }

        // Check if form is valid
        function checkFormValidity() {
            const isValid = selectedFees.size > 0 && termSelect.value && dueDateInput.value;
            submitBtn.disabled = !isValid;
        }

        // Listen to form changes
        termSelect.addEventListener('change', () => {
            updatePreview();
            checkFormValidity();
        });
        dueDateInput.addEventListener('change', () => {
            updatePreview();
            checkFormValidity();
        });
        classFilter.addEventListener('change', updatePreview);

        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        dueDateInput.setAttribute('min', today);
    </script>
</body>
</html>

